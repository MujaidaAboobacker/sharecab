<?php

session_start();
require_once "../config/database.php";

if (!isset($_SESSION["driver_id"])) {
    header("Location: ../auth/driver_login.php");
    exit();
}

$driver_id = $_SESSION["driver_id"];
$driver_name = $_SESSION["driver_name"];
$driver_vehicle = isset($_SESSION["driver_vehicle"]) ? $_SESSION["driver_vehicle"] : "Cab";
$driver_plate = isset($_SESSION["driver_vehicle_number"]) ? $_SESSION["driver_vehicle_number"] : "";

// Fetch driver live status
$d_stmt = $conn->prepare("SELECT status FROM drivers WHERE id = ?");
$d_stmt->bind_param("i", $driver_id);
$d_stmt->execute();
$driver_status = "available";
if ($d_row = $d_stmt->get_result()->fetch_assoc()) {
    $driver_status = $d_row["status"];
}
$d_stmt->close();

// 1. Fetch active assigned trips for this driver
$active_trips_stmt = $conn->prepare("
    SELECT rides.*, users.name AS creator_name,
           (SELECT COUNT(*) FROM ride_members WHERE ride_members.ride_id = rides.id) AS passenger_count
    FROM rides
    JOIN ride_drivers ON rides.id = ride_drivers.ride_id
    JOIN users ON rides.created_by = users.id
    WHERE ride_drivers.driver_id = ?
    AND rides.status IN ('open', 'full')
    ORDER BY rides.ride_date ASC, rides.ride_time ASC
");
$active_trips_stmt->bind_param("i", $driver_id);
$active_trips_stmt->execute();
$active_trips = $active_trips_stmt->get_result();

// 2. Fetch completed trips for this driver
$completed_trips_stmt = $conn->prepare("
    SELECT rides.*, users.name AS creator_name,
           (SELECT COUNT(*) FROM ride_members WHERE ride_members.ride_id = rides.id) AS passenger_count
    FROM rides
    JOIN ride_drivers ON rides.id = ride_drivers.ride_id
    JOIN users ON rides.created_by = users.id
    WHERE ride_drivers.driver_id = ?
    AND rides.status = 'completed'
    ORDER BY rides.ride_date DESC, rides.ride_time DESC
");
$completed_trips_stmt->bind_param("i", $driver_id);
$completed_trips_stmt->execute();
$completed_trips = $completed_trips_stmt->get_result();

// 3. Fetch open unassigned shared rides looking for a driver
$unassigned_stmt = $conn->prepare("
    SELECT rides.*, users.name AS creator_name,
           (SELECT COUNT(*) FROM ride_members WHERE ride_members.ride_id = rides.id) AS passenger_count
    FROM rides
    JOIN users ON rides.created_by = users.id
    LEFT JOIN ride_drivers ON rides.id = ride_drivers.ride_id
    WHERE ride_drivers.id IS NULL
    AND rides.status IN ('open', 'full')
    AND (rides.ride_date > CURDATE() OR (rides.ride_date = CURDATE() AND rides.ride_time >= CURTIME()))
    ORDER BY rides.ride_date ASC, rides.ride_time ASC
    LIMIT 10
");
$unassigned_stmt->execute();
$unassigned_rides = $unassigned_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - ShareCab</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="dashboard.php">
                    <img src="../img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab <span class="badge" style="background: #10b981; color: white;">Driver Portal</span>
                </a>
            </div>
            <div class="nav-links">
                <span class="welcome-user">
                    👨‍✈️ <?php echo htmlspecialchars($driver_name); ?> (<?php echo htmlspecialchars($driver_plate); ?>)
                </span>
                <a href="../auth/driver_logout.php" class="nav-login">Driver Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">

        <!-- Driver Header Banner -->
        <div class="dashboard-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1>Driver Dashboard 👋</h1>
                    <p style="color: var(--slate-300);">Welcome back, <strong><?php echo htmlspecialchars($driver_name); ?></strong> &bull; Vehicle: <strong><?php echo htmlspecialchars($driver_vehicle); ?></strong> (<?php echo htmlspecialchars($driver_plate); ?>)</p>
                </div>
                <div>
                    <?php if ($driver_status === 'on_trip'): ?>
                        <span class="status-badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid #f59e0b; padding: 8px 18px; font-size: 14px; font-weight: 700; border-radius: var(--radius-full);">
                            ● Driver Status: On Trip
                        </span>
                    <?php elseif ($driver_status === 'offline'): ?>
                        <span class="status-badge" style="background: var(--slate-700); color: var(--slate-300); border: 1px solid var(--slate-600); padding: 8px 18px; font-size: 14px; font-weight: 700; border-radius: var(--radius-full);">
                            ● Driver Status: Offline
                        </span>
                    <?php else: ?>
                        <span class="status-badge open" style="background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; padding: 8px 18px; font-size: 14px; font-weight: 700; border-radius: var(--radius-full);">
                            ● Driver Status: Online & Available
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($_GET["accepted"])): ?>
            <div class="success-message">
                ✅ You have successfully accepted the ride! You are now the designated driver.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["completed"])): ?>
            <div class="success-message">
                🏁 Great job! Ride has been marked as Completed.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["cash_confirmed"])): ?>
            <div class="success-message">
                💵 Passenger cash payment confirmed and recorded as settled!
            </div>
        <?php endif; ?>

        <!-- Quick Driver Stats -->
        <div class="ride-details-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
            <div class="metric-card">
                <span class="metric-icon">🚗</span>
                <h3>Assigned Active Trips</h3>
                <p style="color: var(--primary-hover);"><?php echo $active_trips->num_rows; ?></p>
            </div>
            <div class="metric-card">
                <span class="metric-icon">🏁</span>
                <h3>Completed Trips</h3>
                <p style="color: var(--success);"><?php echo $completed_trips->num_rows; ?></p>
            </div>
            <div class="metric-card">
                <span class="metric-icon">📢</span>
                <h3>Available Rides to Pick</h3>
                <p style="color: var(--info);"><?php echo $unassigned_rides->num_rows; ?></p>
            </div>
        </div>

        <!-- SECTION 1: Active Assigned Trips -->
        <div style="margin-bottom: 40px;">
            <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 18px; color: var(--slate-900); display: flex; align-items: center; gap: 10px;">
                <span>🚗</span>
                <span>My Active Assigned Trips</span>
                <span style="font-size: 14px; font-weight: 600; color: var(--slate-500);">(<?php echo $active_trips->num_rows; ?> active)</span>
            </h2>

            <?php if ($active_trips && $active_trips->num_rows > 0): ?>
                <div class="ride-list">
                    <?php while ($trip = $active_trips->fetch_assoc()): 
                        // Fetch passenger list and payment details for this trip
                        $pass_stmt = $conn->prepare("
                            SELECT users.id, users.name, users.phone, users.email,
                                   payments.id AS payment_id, payments.amount AS paid_amount, payments.payment_method, payments.status AS payment_status
                            FROM ride_members
                            JOIN users ON ride_members.user_id = users.id
                            LEFT JOIN payments ON payments.ride_id = ride_members.ride_id AND payments.user_id = users.id
                            WHERE ride_members.ride_id = ?
                            ORDER BY ride_members.joined_at ASC
                        ");
                        $pass_stmt->bind_param("i", $trip["id"]);
                        $pass_stmt->execute();
                        $passengers = $pass_stmt->get_result();
                        $passenger_count = $passengers->num_rows;
                        $split_fare = ($passenger_count > 0) ? round($trip["fare"] / $passenger_count, 2) : $trip["fare"];
                    ?>
                        <div class="card" style="border-left: 5px solid var(--primary);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
                                <div>
                                    <span class="status-badge open" style="margin-bottom: 8px;">● In Progress / Ready</span>
                                    <h3 style="font-size: 20px; font-weight: 800; color: var(--slate-900);">
                                        🚕 <?php echo htmlspecialchars($trip["source"]); ?> 
                                        <span style="color: var(--primary);">➔</span> 
                                        <?php echo htmlspecialchars($trip["destination"]); ?>
                                    </h3>
                                    <div class="ride-chips" style="margin-top: 10px;">
                                        <span class="ride-chip">📅 <?php echo date("D, d M Y", strtotime($trip["ride_date"])); ?></span>
                                        <span class="ride-chip">🕐 Departure: <?php echo date("h:i A", strtotime($trip["ride_time"])); ?></span>
                                        <span class="ride-chip">💰 Total Fare: ₹<?php echo number_format($trip["fare"], 2); ?></span>
                                        <span class="ride-chip" style="background: #e0f2fe; color: #0369a1;">👥 Split: ₹<?php echo number_format($split_fare, 2); ?> / rider</span>
                                    </div>
                                </div>

                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <a href="../rides/ride_details.php?id=<?php echo $trip['id']; ?>" class="btn-secondary" style="font-size: 13px; padding: 10px 16px;">
                                        🗺️ Live Route & Payments
                                    </a>
                                    <a href="../chat/chat.php?ride_id=<?php echo $trip['id']; ?>" class="btn-secondary" style="font-size: 13px; padding: 10px 16px;">
                                        💬 Group Chat
                                    </a>
                                    <form method="POST" action="../rides/complete_ride.php" onsubmit="return confirm('🏁 Mark this ride as completed? Passengers will see trip marked as finished.');" style="display: inline;">
                                        <input type="hidden" name="ride_id" value="<?php echo $trip['id']; ?>">
                                        <button type="submit" class="btn-primary" style="font-size: 13px; padding: 10px 18px; background: var(--success);">
                                            🏁 Mark as Completed
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Passenger Manifest & Fare Collection -->
                            <div style="background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 16px; margin-top: 14px;">
                                <h4 style="font-size: 13px; font-weight: 700; color: var(--slate-600); text-transform: uppercase; margin-bottom: 12px;">
                                    👥 Passenger Manifest & Fare Settlement Status:
                                </h4>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <?php while ($p = $passengers->fetch_assoc()): ?>
                                        <div style="background: #ffffff; padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--slate-200); display: flex; flex-direction: column; gap: 8px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <strong style="font-size: 14px; color: var(--slate-900);"><?php echo htmlspecialchars($p['name']); ?></strong>
                                                <a href="tel:<?php echo htmlspecialchars($p['phone']); ?>" style="color: var(--primary-hover); font-weight: 700; font-size: 13px; text-decoration: none;">
                                                    📞 <?php echo htmlspecialchars($p['phone']); ?>
                                                </a>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 6px; border-top: 1px dashed var(--slate-100); flex-wrap: wrap; gap: 6px;">
                                                <?php if ($p['payment_status'] === 'completed'): ?>
                                                    <span style="font-size: 11px; font-weight: 700; background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 999px;">
                                                        ✓ Paid ₹<?php echo number_format($p['paid_amount'], 2); ?> (<?php echo strtoupper($p['payment_method']); ?>)
                                                    </span>
                                                    <span style="font-size: 11px; color: var(--slate-400);">Settled</span>
                                                <?php elseif ($p['payment_method'] === 'cash'): ?>
                                                    <span style="font-size: 11px; font-weight: 700; background: #fef3c7; color: #b45309; padding: 3px 8px; border-radius: 999px;">
                                                        ⏳ Cash Due: ₹<?php echo number_format($p['paid_amount'] ?? $split_fare, 2); ?>
                                                    </span>
                                                    <form method="POST" action="confirm_cash.php" style="margin: 0; display: inline;">
                                                        <input type="hidden" name="ride_id" value="<?php echo $trip['id']; ?>">
                                                        <input type="hidden" name="passenger_id" value="<?php echo $p['id']; ?>">
                                                        <button type="submit" style="background: #10b981; color: white; border: none; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer;" onclick="return confirm('Confirm cash payment received from <?php echo htmlspecialchars(addslashes($p['name'])); ?>?');">
                                                            ✓ Confirm Cash
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="font-size: 11px; font-weight: 700; background: #f1f5f9; color: #64748b; padding: 3px 8px; border-radius: 999px;">
                                                        ● Unpaid (₹<?php echo number_format($split_fare, 2); ?>)
                                                    </span>
                                                    <form method="POST" action="confirm_cash.php" style="margin: 0; display: inline;">
                                                        <input type="hidden" name="ride_id" value="<?php echo $trip['id']; ?>">
                                                        <input type="hidden" name="passenger_id" value="<?php echo $p['id']; ?>">
                                                        <button type="submit" style="background: #059669; color: white; border: none; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer;" onclick="return confirm('Collect and mark cash payment of ₹<?php echo number_format($split_fare, 2); ?> from <?php echo htmlspecialchars(addslashes($p['name'])); ?>?');">
                                                            💵 Collect Cash
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        $pass_stmt->close();
                    endwhile; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 40px; color: var(--slate-500);">
                    <p style="font-size: 40px; margin-bottom: 8px;">🚕</p>
                    <p style="font-weight: 700; color: var(--slate-700);">No active trips currently assigned.</p>
                    <p style="font-size: 13px;">Browse the open shared rides below and accept a ride to begin driving.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECTION 2: Open Shared Rides Looking for a Driver -->
        <div style="margin-bottom: 40px;">
            <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 18px; color: var(--slate-900); display: flex; align-items: center; gap: 10px;">
                <span>📢</span>
                <span>Available Shared Rides (Need a Driver)</span>
            </h2>

            <?php if ($unassigned_rides && $unassigned_rides->num_rows > 0): ?>
                <div class="ride-list">
                    <?php while ($open_ride = $unassigned_rides->fetch_assoc()): ?>
                        <div class="ride-card">
                            <div class="ride-card-left">
                                <h3>
                                    <span>🚕</span>
                                    <span><?php echo htmlspecialchars($open_ride["source"]); ?></span>
                                    <span style="color: var(--primary);">➔</span>
                                    <span><?php echo htmlspecialchars($open_ride["destination"]); ?></span>
                                </h3>
                                <div class="ride-chips">
                                    <span class="ride-chip">📅 <?php echo date("D, d M Y", strtotime($open_ride["ride_date"])); ?></span>
                                    <span class="ride-chip">🕐 <?php echo date("h:i A", strtotime($open_ride["ride_time"])); ?></span>
                                    <span class="ride-chip">👤 Creator: <?php echo htmlspecialchars($open_ride["creator_name"]); ?></span>
                                    <span class="ride-chip">👥 <?php echo $open_ride["passenger_count"]; ?> Passengers joined</span>
                                </div>
                            </div>

                            <div class="ride-card-right">
                                <div class="fare-price">
                                    ₹<?php echo number_format($open_ride["fare"], 2); ?>
                                    <small>Total Ride Fare</small>
                                </div>
                                <form method="POST" action="accept_ride.php">
                                    <input type="hidden" name="ride_id" value="<?php echo $open_ride['id']; ?>">
                                    <button type="submit" class="btn-primary" style="font-size: 13px; padding: 10px 18px;">
                                        🚗 Accept & Drive This Ride
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 30px; color: var(--slate-500);">
                    <p>All current passenger rides have drivers assigned. Check back shortly!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECTION 3: Completed Rides History -->
        <div>
            <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 18px; color: var(--slate-900); display: flex; align-items: center; gap: 10px;">
                <span>🏁</span>
                <span>Completed Trips History</span>
                <span style="font-size: 14px; font-weight: 600; color: var(--slate-500);">(<?php echo $completed_trips->num_rows; ?> finished)</span>
            </h2>

            <?php if ($completed_trips && $completed_trips->num_rows > 0): ?>
                <div class="ride-list">
                    <?php while ($past = $completed_trips->fetch_assoc()): ?>
                        <div class="ride-card" style="opacity: 0.9;">
                            <div class="ride-card-left">
                                <span class="status-badge" style="background: var(--slate-100); color: var(--slate-600); margin-bottom: 6px;">
                                    🏁 Completed
                                </span>
                                <h3>
                                    <?php echo htmlspecialchars($past["source"]); ?> ➔ <?php echo htmlspecialchars($past["destination"]); ?>
                                </h3>
                                <div class="ride-chips">
                                    <span class="ride-chip">📅 <?php echo date("d M Y", strtotime($past["ride_date"])); ?></span>
                                    <span class="ride-chip">👥 <?php echo $past["passenger_count"]; ?> Passengers</span>
                                    <span class="ride-chip">💰 Total Earned: ₹<?php echo number_format($past["fare"], 2); ?></span>
                                </div>
                            </div>
                            <div class="ride-card-right">
                                <a href="../rides/ride_details.php?id=<?php echo $past['id']; ?>" class="btn-secondary" style="font-size: 12px; padding: 8px 14px;">
                                    View Summary
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 30px; color: var(--slate-500);">
                    <p>No completed trips yet.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <footer>
        <div class="footer-container">
            <h3>🚕 ShareCab</h3>
            <p>Cab sharing made simple & affordable &bull; Driver Partner Portal</p>
            <p>Web Programming Lab Project</p>
        </div>
    </footer>

</body>
</html>
<?php
$active_trips_stmt->close();
$completed_trips_stmt->close();
$unassigned_stmt->close();
?>
