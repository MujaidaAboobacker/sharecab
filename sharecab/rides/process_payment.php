<?php

session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my_rides.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$ride_id = isset($_POST["ride_id"]) ? intval($_POST["ride_id"]) : 0;
$payment_method = isset($_POST["payment_method"]) ? trim($_POST["payment_method"]) : "";

if ($ride_id <= 0 || !in_array($payment_method, ["upi", "card", "cash"])) {
    header("Location: my_rides.php?error=invalid_payment_request");
    exit();
}

// 1. Verify user is confirmed member of this ride
$member_stmt = $conn->prepare("SELECT id FROM ride_members WHERE ride_id = ? AND user_id = ?");
$member_stmt->bind_param("ii", $ride_id, $user_id);
$member_stmt->execute();
$is_member = ($member_stmt->get_result()->num_rows > 0);
$member_stmt->close();

if (!$is_member) {
    header("Location: ride_details.php?id=" . $ride_id . "&error=not_a_member");
    exit();
}

// 2. Check if user has already paid or settled
$check_pay = $conn->prepare("SELECT id, status FROM payments WHERE ride_id = ? AND user_id = ?");
$check_pay->bind_param("ii", $ride_id, $user_id);
$check_pay->execute();
$existing_payment = $check_pay->get_result()->fetch_assoc();
$check_pay->close();

if ($existing_payment) {
    if ($existing_payment["status"] === "completed") {
        header("Location: ride_details.php?id=" . $ride_id . "&already_paid=1");
        exit();
    }
}

// 3. Fetch total fare and calculate exact fare split
$ride_stmt = $conn->prepare("
    SELECT fare,
           (SELECT COUNT(*) FROM ride_members WHERE ride_members.ride_id = rides.id) AS passenger_count
    FROM rides
    WHERE id = ?
");
$ride_stmt->bind_param("i", $ride_id);
$ride_stmt->execute();
$ride_data = $ride_stmt->get_result()->fetch_assoc();
$ride_stmt->close();

if (!$ride_data) {
    header("Location: my_rides.php?error=ride_not_found");
    exit();
}

$passenger_count = max(1, intval($ride_data["passenger_count"]));
$amount = round(floatval($ride_data["fare"]) / $passenger_count, 2);

// 4. Generate unique transaction reference based on method
$random_suffix = strtoupper(substr(uniqid(), 8)) . mt_rand(100, 999);
$transaction_id = "";
$status = "completed";

if ($payment_method === "upi") {
    $transaction_id = "SC-UPI-" . $random_suffix;
    $status = "completed"; // Simulated instant UPI clearance
} elseif ($payment_method === "card") {
    $transaction_id = "SC-CRD-" . $random_suffix;
    $status = "completed"; // Simulated card authorization
} elseif ($payment_method === "cash") {
    $transaction_id = "SC-CSH-" . $random_suffix;
    $status = "pending"; // Pending driver confirmation upon arrival
}

// 5. Insert or update payment record
if ($existing_payment) {
    // Update existing pending payment
    $up_stmt = $conn->prepare("
        UPDATE payments 
        SET amount = ?, payment_method = ?, transaction_id = ?, status = ?, paid_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $up_stmt->bind_param("dsssi", $amount, $payment_method, $transaction_id, $status, $existing_payment["id"]);
    $up_stmt->execute();
    $up_stmt->close();
} else {
    // Insert new payment
    $ins_stmt = $conn->prepare("
        INSERT INTO payments (ride_id, user_id, amount, payment_method, transaction_id, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $ins_stmt->bind_param("iidsss", $ride_id, $user_id, $amount, $payment_method, $transaction_id, $status);
    $ins_stmt->execute();
    $ins_stmt->close();
}

header("Location: ride_details.php?id=" . $ride_id . "&payment_success=1&method=" . $payment_method);
exit();
?>
