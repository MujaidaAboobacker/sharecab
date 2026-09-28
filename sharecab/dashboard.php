<?php

session_start();

require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"];

// Fetch quick dashboard metrics
$open_rides_count = 0;
$my_trips_count = 0;
$drivers_count = 0;

$r1 = $conn->query("
    SELECT COUNT(*) AS total 
    FROM rides 
    WHERE status = 'open' 
    AND (ride_date > CURDATE() OR (ride_date = CURDATE() AND ride_time >= CURTIME()))
");
if ($r1) {
    $open_rides_count = $r1->fetch_assoc()["total"];
}

$stmt2 = $conn->prepare("
    SELECT COUNT(DISTINCT rides.id) AS total 
    FROM rides 
    LEFT JOIN ride_members ON rides.id = ride_members.ride_id 
    WHERE (rides.created_by = ? OR ride_members.user_id = ?)
    AND rides.status IN ('open', 'full')
    AND (rides.ride_date > CURDATE() OR (rides.ride_date = CURDATE() AND rides.ride_time >= CURTIME()))
");
$stmt2->bind_param("ii", $user_id, $user_id);
$stmt2->execute();
$my_trips_count = $stmt2->get_result()->fetch_assoc()["total"];
$stmt2->close();

$r3 = $conn->query("SELECT COUNT(*) AS total FROM drivers");
if ($r3) {
    $drivers_count = $r3->fetch_assoc()["total"];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ShareCab</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="dashboard.php" style="display: flex; align-items: center;">
                    <img src="img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab
                </a>
            </div>
            <div class="nav-links">
                <span class="welcome-user">
                    👋 Hi, <?php echo htmlspecialchars($user_name); ?>
                </span>
                <a href="auth/driver_login.php" style="color: var(--slate-600); font-size: 13px; font-weight: 600;">
                    🚗 Driver Portal
                </a>
                <a href="auth/logout.php" class="nav-login">
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Dashboard Container -->
    <div class="container">

        <!-- Dashboard Hero Banner -->
        <div class="dashboard-header">
            <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
            <p>Find passengers traveling along your route, share cab rides, and split the travel fare effortlessly.</p>
        </div>

        <!-- Quick Summary Stats Grid -->
        <div class="ride-details-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
            <div class="metric-card">
                <span class="metric-icon">🔍</span>
                <h3>Available Rides</h3>
                <p><?php echo $open_rides_count; ?> Upcoming</p>
            </div>

            <div class="metric-card">
                <span class="metric-icon">🚕</span>
                <h3>My Trips</h3>
                <p><?php echo $my_trips_count; ?> Active</p>
            </div>

            <div class="metric-card">
                <span class="metric-icon">🚗</span>
                <h3>Registered Drivers</h3>
                <p><?php echo $drivers_count; ?> Drivers</p>
            </div>
        </div>

        <!-- Dashboard Action Grid -->
        <div class="dashboard-actions">
            <a href="rides/search_ride.php" class="dashboard-action">
                <div class="action-icon">
                    🔍
                </div>
                <div>
                    <h3>Find a Ride</h3>
                    <p>Search open rides matching your route and join them.</p>
                </div>
            </a>

            <a href="rides/create_ride.php" class="dashboard-action">
                <div class="action-icon">
                    🚕
                </div>
                <div>
                    <h3>Create a Ride</h3>
                    <p>Share your journey and split the fare with passengers.</p>
                </div>
            </a>

            <a href="rides/my_rides.php" class="dashboard-action">
                <div class="action-icon">
                    📋
                </div>
                <div>
                    <h3>My Rides</h3>
                    <p>View organized trips, passenger lists, and ride group chats.</p>
                </div>
            </a>

            <a href="admin/drivers.php" class="dashboard-action">
                <div class="action-icon">
                    🚗
                </div>
                <div>
                    <h3>Driver Management</h3>
                    <p>Register cab drivers, vehicle plates, and view assignments.</p>
                </div>
            </a>
        </div>

        <!-- How ShareCab Works Section -->
        <div class="card dashboard-info">
            <h2>💡 How ShareCab Works</h2>
            <div class="info-grid">
                <div>
                    <span style="font-size: 24px; display: block; margin-bottom: 8px;">1️⃣</span>
                    <h3>Search or Post</h3>
                    <p>Search for a matching route or post your own trip with travel date, time, and seat capacity.</p>
                </div>

                <div>
                    <span style="font-size: 24px; display: block; margin-bottom: 8px;">2️⃣</span>
                    <h3>Join & Split Fare</h3>
                    <p>Join available shared rides. The fare per passenger updates dynamically with each joined member.</p>
                </div>

                <div>
                    <span style="font-size: 24px; display: block; margin-bottom: 8px;">3️⃣</span>
                    <h3>Interactive Route & Chat</h3>
                    <p>Visualize the route on OpenStreetMap, check road distance, and coordinate meetup points in group chat.</p>
                </div>

                <div>
                    <span style="font-size: 24px; display: block; margin-bottom: 8px;">4️⃣</span>
                    <h3>Assign Driver & Travel</h3>
                    <p>Assign registered cab drivers to trips so everyone has verified vehicle and driver contact details.</p>
                </div>
            </div>
        </div>

    </div>

    <footer>
        <div class="footer-container">
            <h3>🚕 ShareCab</h3>
            <p>Cab sharing made simple & affordable.</p>
            <p>Web Programming Lab Project</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>