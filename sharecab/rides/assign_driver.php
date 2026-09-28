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

$ride_id = isset($_POST["ride_id"]) ? intval($_POST["ride_id"]) : 0;
$driver_id = isset($_POST["driver_id"]) ? intval($_POST["driver_id"]) : 0;
$user_id = $_SESSION["user_id"];

if ($ride_id <= 0 || $driver_id <= 0) {
    header("Location: ride_details.php?id=" . $ride_id . "&error=invalid_data");
    exit();
}

// Verify that the current user is the creator of the ride
$check_stmt = $conn->prepare("SELECT created_by FROM rides WHERE id = ?");
$check_stmt->bind_param("i", $ride_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    header("Location: my_rides.php");
    exit();
}

$ride = $check_result->fetch_assoc();
$check_stmt->close();

if ($ride["created_by"] != $user_id) {
    header("Location: ride_details.php?id=" . $ride_id . "&error=unauthorized");
    exit();
}

// Check if driver exists
$driver_check = $conn->prepare("SELECT id FROM drivers WHERE id = ?");
$driver_check->bind_param("i", $driver_id);
$driver_check->execute();
if ($driver_check->get_result()->num_rows === 0) {
    header("Location: ride_details.php?id=" . $ride_id . "&error=driver_not_found");
    exit();
}
$driver_check->close();

// Assign or update driver in ride_drivers
// Check existing assignment
$exist_stmt = $conn->prepare("SELECT id FROM ride_drivers WHERE ride_id = ?");
$exist_stmt->bind_param("i", $ride_id);
$exist_stmt->execute();
$exist_res = $exist_stmt->get_result();
$exist_stmt->close();

if ($exist_res->num_rows > 0) {
    // Fetch existing driver id to free them
    $old_d_stmt = $conn->prepare("SELECT driver_id FROM ride_drivers WHERE ride_id = ?");
    $old_d_stmt->bind_param("i", $ride_id);
    $old_d_stmt->execute();
    $old_d_row = $old_d_stmt->get_result()->fetch_assoc();
    $old_d_stmt->close();
    if (!empty($old_d_row['driver_id']) && $old_d_row['driver_id'] != $driver_id) {
        $conn->query("UPDATE drivers SET status = 'available' WHERE id = " . intval($old_d_row['driver_id']));
    }

    // Update existing driver
    $update_stmt = $conn->prepare("UPDATE ride_drivers SET driver_id = ? WHERE ride_id = ?");
    $update_stmt->bind_param("ii", $driver_id, $ride_id);
    $update_stmt->execute();
    $update_stmt->close();
} else {
    // Insert new assignment
    $insert_stmt = $conn->prepare("INSERT INTO ride_drivers (ride_id, driver_id) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $ride_id, $driver_id);
    $insert_stmt->execute();
    $insert_stmt->close();
}

// Set new assigned driver to on_trip
$new_d_stmt = $conn->prepare("UPDATE drivers SET status = 'on_trip' WHERE id = ?");
$new_d_stmt->bind_param("i", $driver_id);
$new_d_stmt->execute();
$new_d_stmt->close();

header("Location: ride_details.php?id=" . $ride_id . "&assigned=1");
exit();
?>
