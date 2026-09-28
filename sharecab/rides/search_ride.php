<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$current_user_id = $_SESSION["user_id"];
$rides = [];
$searched = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $searched = true;
    $source = trim($_POST["source"]);
    $destination = trim($_POST["destination"]);
    $ride_date = !empty($_POST["ride_date"]) ? $_POST["ride_date"] : null;

    $sql = "
        SELECT rides.*, 
               users.name AS creator_name,
               (rides.max_seats - (SELECT COUNT(*) FROM ride_members WHERE ride_members.ride_id = rides.id)) AS seats_left,
               (SELECT COUNT(*) FROM ride_members WHERE ride_members.ride_id = rides.id AND ride_members.user_id = ?) AS is_joined
        FROM rides
        JOIN users ON rides.created_by = users.id
        WHERE rides.source LIKE ?
        AND rides.destination LIKE ?
        AND rides.status = 'open'
    ";

    $source_search = "%" . $source . "%";
    $destination_search = "%" . $destination . "%";

    if ($ride_date) {
        if ($ride_date === date("Y-m-d")) {
            $sql .= " AND rides.ride_date = ? AND rides.ride_time >= CURTIME() ORDER BY rides.ride_date ASC, rides.ride_time ASC";
        } else {
            $sql .= " AND rides.ride_date = ? ORDER BY rides.ride_date ASC, rides.ride_time ASC";
        }
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $current_user_id, $source_search, $destination_search, $ride_date);
    } else {
        $sql .= " AND (rides.ride_date > CURDATE() OR (rides.ride_date = CURDATE() AND rides.ride_time >= CURTIME())) ORDER BY rides.ride_date ASC, rides.ride_time ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $current_user_id, $source_search, $destination_search);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rides[] = $row;
    }
    $stmt->close();
} else {
    // Show upcoming open rides by default so page is full of content
    $sql = "
        SELECT rides.*, 
               users.name AS creator_name,
               (rides.max_seats - (SELECT COUNT(*) FROM ride_members WHERE ride_members.ride_id = rides.id)) AS seats_left,
               (SELECT COUNT(*) FROM ride_members WHERE ride_members.ride_id = rides.id AND ride_members.user_id = ?) AS is_joined
        FROM rides
        JOIN users ON rides.created_by = users.id
        WHERE rides.status = 'open'
        AND (rides.ride_date > CURDATE() OR (rides.ride_date = CURDATE() AND rides.ride_time >= CURTIME()))
        ORDER BY rides.ride_date ASC, rides.ride_time ASC
        LIMIT 10
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rides[] = $row;
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a Ride - ShareCab</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../dashboard.php" style="display: flex; align-items: center;">
                    <img src="../img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab
                </a>
            </div>
            <div class="nav-links">
                <a href="../dashboard.php">Dashboard</a>
                <a href="create_ride.php">+ Create Ride</a>
                <a href="my_rides.php">My Rides</a>
                <a href="../auth/logout.php" class="nav-login">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Search Section -->
    <div class="container">

        <div class="dashboard-header">
            <h1>🔍 Search & Find Shared Rides</h1>
            <p>Look for fellow commuters travelling along your route and split the fare.</p>
        </div>

        <!-- Filter Card -->
        <div class="card" style="padding: 28px;">
            <form method="POST" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) 160px; gap: 16px; align-items: end;">
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="source">📍 Origin</label>
                    <input
                        type="text"
                        id="source"
                        name="source"
                        placeholder="Where from?"
                        value="<?php echo isset($_POST['source']) ? htmlspecialchars($_POST['source']) : ''; ?>"
                        data-autocomplete="location"
                        autocomplete="off"
                        required
                    >
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="destination">🏁 Destination</label>
                    <input
                        type="text"
                        id="destination"
                        name="destination"
                        placeholder="Where to?"
                        value="<?php echo isset($_POST['destination']) ? htmlspecialchars($_POST['destination']) : ''; ?>"
                        data-autocomplete="location"
                        autocomplete="off"
                        required
                    >
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="ride_date">📅 Travel Date (Optional)</label>
                    <input
                        type="date"
                        id="ride_date"
                        name="ride_date"
                        value="<?php echo isset($_POST['ride_date']) ? htmlspecialchars($_POST['ride_date']) : ''; ?>"
                    >
                </div>

                <div>
                    <button type="submit" class="form-button" style="margin-top: 0; padding: 13px;">
                        🔍 Search Rides
                    </button>
                </div>
            </form>
        </div>

        <!-- Search Results List -->
        <div style="margin-top: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 20px; font-weight: 800;">
                    <?php echo $searched ? 'Search Results' : '🔥 Available Shared Rides'; ?>
                    <span style="font-size: 14px; font-weight: 600; color: var(--slate-500); margin-left: 8px;">(<?php echo count($rides); ?> found)</span>
                </h2>
                <?php if ($searched): ?>
                    <a href="search_ride.php" style="font-size: 13px; color: var(--primary-hover); font-weight: 700;">Show All Upcoming</a>
                <?php endif; ?>
            </div>

            <?php if (count($rides) > 0): ?>
                <div class="ride-list">
                    <?php foreach ($rides as $ride): 
                        $seats_left = max(0, intval($ride["seats_left"]));
                        $is_creator = ($ride["created_by"] == $current_user_id);
                        $is_joined = ($ride["is_joined"] > 0 || $is_creator);
                    ?>
                        <div class="ride-card">
                            <div class="ride-card-left">
                                <h3>
                                    <span>🚕</span>
                                    <span><?php echo htmlspecialchars($ride["source"]); ?></span>
                                    <span style="color: var(--primary);">➔</span>
                                    <span><?php echo htmlspecialchars($ride["destination"]); ?></span>
                                </h3>

                                <div class="ride-chips">
                                    <span class="ride-chip">
                                        📅 <?php echo date("d M Y", strtotime($ride["ride_date"])); ?>
                                    </span>
                                    <span class="ride-chip">
                                        🕐 <?php echo date("h:i A", strtotime($ride["ride_time"])); ?>
                                    </span>
                                    <span class="ride-chip">
                                        👤 By <?php echo htmlspecialchars($ride["creator_name"]); ?>
                                    </span>
                                    <span class="status-badge <?php echo $seats_left > 0 ? 'open' : 'full'; ?>">
                                        ● <?php echo $seats_left > 0 ? ($seats_left . ' seat(s) left') : 'Full'; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="ride-card-right">
                                <div class="fare-price">
                                    ₹<?php echo number_format($ride["fare"], 2); ?>
                                    <small>Total Fare</small>
                                </div>

                                <?php if ($is_joined): ?>
                                    <a href="ride_details.php?id=<?php echo $ride['id']; ?>" class="btn-secondary" style="font-size: 13px; padding: 10px 18px;">
                                        View Details &bull; Joined
                                    </a>
                                <?php elseif ($seats_left > 0): ?>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="ride_details.php?id=<?php echo $ride['id']; ?>" class="btn-secondary" style="font-size: 13px; padding: 9px 14px;">
                                            Route ➔
                                        </a>
                                        <a href="join_ride.php?id=<?php echo $ride['id']; ?>" class="ride-button" style="font-size: 13px; padding: 9px 18px;">
                                            Join Ride
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <button class="ride-button" style="background: var(--slate-300); cursor: not-allowed;" disabled>
                                        Ride Full
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 60px 20px;">
                    <p style="font-size: 48px; margin-bottom: 12px;">🚕</p>
                    <h3 style="font-size: 20px; font-weight: 800; color: var(--slate-900); margin-bottom: 8px;">No rides found for this route</h3>
                    <p style="color: var(--slate-500); font-size: 14px; max-width: 420px; margin: 0 auto 24px auto;">
                        Be the first to create this ride and allow others travelling your way to join and share the fare!
                    </p>
                    <a href="create_ride.php" class="btn-primary">
                        + Create This Ride
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <footer>
        <div class="footer-container">
            <h3>🚕 ShareCab</h3>
            <p>Cab sharing made simple & affordable.</p>
            <p>Web Programming Lab Project</p>
        </div>
    </footer>

    <script src="../js/location-autocomplete.js"></script>
</body>
</html>