<?php

session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$ride_id = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ride_id"])) {
    $ride_id = intval($_POST["ride_id"]);
} elseif (isset($_GET["id"])) {
    $ride_id = intval($_GET["id"]);
}

if ($ride_id <= 0) {
    header("Location: my_rides.php");
    exit();
}

// Check if ride exists and user is creator
$stmt = $conn->prepare("SELECT created_by FROM rides WHERE id = ?");
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header("Location: my_rides.php?error=ride_not_found");
    exit();
}

$ride = $res->fetch_assoc();
$stmt->close();

// Check creator authorization (or admin check)
$is_creator = ($ride["created_by"] == $user_id);
$is_admin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] == 1;

if (!$is_creator && !$is_admin) {
    header("Location: ride_details.php?id=" . $ride_id . "&error=unauthorized");
    exit();
}

// If driver was assigned, free them back to available
$driver_q = $conn->prepare("SELECT driver_id FROM ride_drivers WHERE ride_id = ?");
$driver_q->bind_param("i", $ride_id);
$driver_q->execute();
$driver_res = $driver_q->get_result();
if ($driver_row = $driver_res->fetch_assoc()) {
    $assigned_d_id = intval($driver_row['driver_id']);
    $conn->query("UPDATE drivers SET status = 'available' WHERE id = " . $assigned_d_id);
}
$driver_q->close();

// Delete ride (database foreign keys ON DELETE CASCADE will delete members, drivers, messages)
$del_stmt = $conn->prepare("DELETE FROM rides WHERE id = ?");
$del_stmt->bind_param("i", $ride_id);
$del_stmt->execute();
$del_stmt->close();

header("Location: my_rides.php?deleted=1");
exit();
?>
