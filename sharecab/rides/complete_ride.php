<?php

session_start();
require_once "../config/database.php";

$is_driver = isset($_SESSION["driver_id"]);
$is_user = isset($_SESSION["user_id"]);

if (!$is_driver && !$is_user) {
    header("Location: ../index.html");
    exit();
}

$ride_id = isset($_POST["ride_id"]) ? intval($_POST["ride_id"]) : (isset($_GET["id"]) ? intval($_GET["id"]) : 0);

if ($ride_id <= 0) {
    header("Location: " . ($is_driver ? "../driver/dashboard.php" : "my_rides.php"));
    exit();
}

// Fetch ride details
$stmt = $conn->prepare("
    SELECT rides.id, rides.created_by, ride_drivers.driver_id 
    FROM rides 
    LEFT JOIN ride_drivers ON rides.id = ride_drivers.ride_id 
    WHERE rides.id = ?
");
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Ride not found.");
}

$ride = $res->fetch_assoc();
$stmt->close();

$authorized = false;
if ($is_driver && $ride["driver_id"] == $_SESSION["driver_id"]) {
    $authorized = true;
}
if ($is_user && $ride["created_by"] == $_SESSION["user_id"]) {
    $authorized = true;
}

if (!$authorized) {
    die("Unauthorized to complete this ride.");
}

// Mark ride as completed
$up = $conn->prepare("UPDATE rides SET status = 'completed' WHERE id = ?");
$up->bind_param("i", $ride_id);
$up->execute();
$up->close();

// Free assigned driver back to available status
if (!empty($ride["driver_id"])) {
    $free_driver = $conn->prepare("UPDATE drivers SET status = 'available' WHERE id = ?");
    $free_driver->bind_param("i", $ride["driver_id"]);
    $free_driver->execute();
    $free_driver->close();
}

if ($is_driver) {
    header("Location: ../driver/dashboard.php?completed=1");
} else {
    header("Location: ride_details.php?id=" . $ride_id . "&completed=1");
}
exit();
?>
