<?php

session_start();
require_once "../config/database.php";

if (!isset($_SESSION["driver_id"])) {
    header("Location: ../auth/driver_login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit();
}

$driver_id = $_SESSION["driver_id"];
$ride_id = isset($_POST["ride_id"]) ? intval($_POST["ride_id"]) : 0;
$passenger_id = isset($_POST["passenger_id"]) ? intval($_POST["passenger_id"]) : 0;

if ($ride_id <= 0 || $passenger_id <= 0) {
    header("Location: dashboard.php?error=invalid_data");
    exit();
}

// 1. Verify driver is assigned to this ride
$d_stmt = $conn->prepare("SELECT id FROM ride_drivers WHERE ride_id = ? AND driver_id = ?");
$d_stmt->bind_param("ii", $ride_id, $driver_id);
$d_stmt->execute();
$is_assigned = ($d_stmt->get_result()->num_rows > 0);
$d_stmt->close();

if (!$is_assigned) {
    header("Location: dashboard.php?error=unauthorized_driver");
    exit();
}

// 2. Fetch payment record or create cash completed record
$p_stmt = $conn->prepare("SELECT id, status, payment_method FROM payments WHERE ride_id = ? AND user_id = ?");
$p_stmt->bind_param("ii", $ride_id, $passenger_id);
$p_stmt->execute();
$pay_res = $p_stmt->get_result();

if ($pay_res->num_rows > 0) {
    $payment = $pay_res->fetch_assoc();
    $up_stmt = $conn->prepare("
        UPDATE payments 
        SET status = 'completed', paid_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $up_stmt->bind_param("i", $payment["id"]);
    $up_stmt->execute();
    $up_stmt->close();
} else {
    // If passenger paid cash directly without pre-booking cash mode, driver can directly record it
    $ride_stmt = $conn->prepare("
        SELECT fare,
               (SELECT COUNT(*) FROM ride_members WHERE ride_members.ride_id = rides.id) AS passenger_count
        FROM rides WHERE id = ?
    ");
    $ride_stmt->bind_param("i", $ride_id);
    $ride_stmt->execute();
    $ride_data = $ride_stmt->get_result()->fetch_assoc();
    $ride_stmt->close();

    $p_count = max(1, intval($ride_data["passenger_count"] ?? 1));
    $amount = round(floatval($ride_data["fare"] ?? 0) / $p_count, 2);
    $tx_id = "SC-CSH-DRV-" . strtoupper(substr(uniqid(), 8)) . mt_rand(100, 999);

    $ins_stmt = $conn->prepare("
        INSERT INTO payments (ride_id, user_id, amount, payment_method, transaction_id, status)
        VALUES (?, ?, ?, 'cash', ?, 'completed')
    ");
    $ins_stmt->bind_param("iidss", $ride_id, $passenger_id, $amount, $tx_id);
    $ins_stmt->execute();
    $ins_stmt->close();
}
$p_stmt->close();

$redirect_to = isset($_POST["return_to_details"]) ? "../rides/ride_details.php?id=" . $ride_id . "&cash_confirmed=1" : "dashboard.php?cash_confirmed=1";
header("Location: " . $redirect_to);
exit();
?>
