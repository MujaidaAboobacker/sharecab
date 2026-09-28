<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET["id"])) {
    header("Location: search_ride.php");
    exit();
}

$ride_id = intval($_GET["id"]);
$user_id = $_SESSION["user_id"];

function renderError($message) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Join Ride - ShareCab</title>
        <link rel="stylesheet" href="../css/style.css">
    </head>
    <body>
        <div class="form-container" style="text-align: center; margin-top: 80px;">
            <div class="form-card">
                <p style="font-size: 40px; margin-bottom: 12px;">⚠️</p>
                <h2 style="font-size: 20px; font-weight: 800; margin-bottom: 10px;">' . htmlspecialchars($message) . '</h2>
                <p style="color: var(--slate-500); font-size: 14px; margin-bottom: 24px;">Please choose another available ride or create your own.</p>
                <a href="search_ride.php" class="btn-primary">Browse Available Rides</a>
            </div>
        </div>
    </body>
    </html>';
    exit();
}

/* Get ride information */
$stmt = $conn->prepare(
    "SELECT id, created_by, max_seats, status, ride_date, ride_time
     FROM rides
     WHERE id = ?"
);
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    renderError("Ride not found.");
}

$ride = $result->fetch_assoc();
$stmt->close();

/* Check if user is the creator */
if ($ride["created_by"] == $user_id) {
    header("Location: ride_details.php?id=" . $ride_id);
    exit();
}

/* Check if ride departure time has already passed */
$departure_timestamp = strtotime($ride["ride_date"] . " " . $ride["ride_time"]);
if ($departure_timestamp < time()) {
    renderError("This ride has already departed and can no longer be joined.");
}

/* Check ride status */
if ($ride["status"] != "open") {
    renderError("This ride is no longer open for new bookings or has completed.");
}

/* Check if user already joined */
$stmt = $conn->prepare(
    "SELECT id
     FROM ride_members
     WHERE ride_id = ?
     AND user_id = ?"
);
$stmt->bind_param("ii", $ride_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header("Location: ride_details.php?id=" . $ride_id);
    exit();
}
$stmt->close();

/* Count current passengers */
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total_members
     FROM ride_members
     WHERE ride_id = ?"
);
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$current_members = $row["total_members"];
$stmt->close();

/* Check available seats */
if ($current_members >= $ride["max_seats"]) {
    renderError("Sorry, all passenger seats for this ride are full.");
}

/* Join the ride */
$stmt = $conn->prepare(
    "INSERT INTO ride_members (ride_id, user_id)
     VALUES (?, ?)"
);
$stmt->bind_param("ii", $ride_id, $user_id);

if ($stmt->execute()) {
    // Check if ride is now full
    if ($current_members + 1 >= $ride["max_seats"]) {
        $update_stmt = $conn->prepare("UPDATE rides SET status = 'full' WHERE id = ?");
        $update_stmt->bind_param("i", $ride_id);
        $update_stmt->execute();
        $update_stmt->close();
    }

    header("Location: ride_details.php?id=" . $ride_id);
    exit();
} else {
    renderError("Failed to join this ride. Please try again.");
}

$stmt->close();
?>