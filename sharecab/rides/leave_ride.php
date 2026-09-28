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

// Fetch ride information
$stmt = $conn->prepare("SELECT id, created_by, status, max_seats FROM rides WHERE id = ?");
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$ride_res = $stmt->get_result();

if ($ride_res->num_rows === 0) {
    header("Location: my_rides.php?error=ride_not_found");
    exit();
}

$ride = $ride_res->fetch_assoc();
$stmt->close();

// Prevent leaving completed rides
if ($ride["status"] === "completed") {
    header("Location: ride_details.php?id=" . $ride_id . "&error=cannot_leave_completed");
    exit();
}

// Prevent creator from leaving their own ride (they should delete instead)
if ($ride["created_by"] == $user_id) {
    header("Location: ride_details.php?id=" . $ride_id . "&error=creator_cannot_leave");
    exit();
}

// Check if user is actually a member of this ride
$member_stmt = $conn->prepare("SELECT id FROM ride_members WHERE ride_id = ? AND user_id = ?");
$member_stmt->bind_param("ii", $ride_id, $user_id);
$member_stmt->execute();
$member_res = $member_stmt->get_result();

if ($member_res->num_rows === 0) {
    header("Location: my_rides.php?error=not_a_member");
    exit();
}
$member_stmt->close();

// Remove passenger from ride_members
$del_stmt = $conn->prepare("DELETE FROM ride_members WHERE ride_id = ? AND user_id = ?");
$del_stmt->bind_param("ii", $ride_id, $user_id);
$del_stmt->execute();
$del_stmt->close();

// If the ride was full, update status back to 'open' since a seat is now free
if ($ride["status"] === "full") {
    $update_stmt = $conn->prepare("UPDATE rides SET status = 'open' WHERE id = ?");
    $update_stmt->bind_param("i", $ride_id);
    $update_stmt->execute();
    $update_stmt->close();
}

header("Location: my_rides.php?cancelled=1");
exit();
?>
