<?php

session_start();
require_once "../config/database.php";

if (!isset($_SESSION["driver_id"])) {
    header("Location: ../auth/driver_login.php");
    exit();
}

$driver_id = $_SESSION["driver_id"];
$ride_id = isset($_POST["ride_id"]) ? intval($_POST["ride_id"]) : 0;

if ($ride_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Verify ride is open, future, and has no driver assigned
$check = $conn->prepare("
    SELECT rides.id 
    FROM rides 
    LEFT JOIN ride_drivers ON rides.id = ride_drivers.ride_id 
    WHERE rides.id = ? 
    AND ride_drivers.id IS NULL 
    AND rides.status IN ('open', 'full')
    AND (rides.ride_date > CURDATE() OR (rides.ride_date = CURDATE() AND rides.ride_time >= CURTIME()))
");
$check->bind_param("i", $ride_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows === 0) {
    header("Location: dashboard.php?error=already_taken");
    exit();
}
$check->close();

// Assign driver to ride
$stmt = $conn->prepare("INSERT INTO ride_drivers (ride_id, driver_id) VALUES (?, ?)");
$stmt->bind_param("ii", $ride_id, $driver_id);
$stmt->execute();
$stmt->close();

// Update driver status to on_trip
$up_driver = $conn->prepare("UPDATE drivers SET status = 'on_trip' WHERE id = ?");
$up_driver->bind_param("i", $driver_id);
$up_driver->execute();
$up_driver->close();

header("Location: dashboard.php?accepted=1");
exit();
?>
