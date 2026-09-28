<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT rides.*, 
            users.name AS creator_name,
            (SELECT COUNT(*) FROM ride_members WHERE ride_members.ride_id = rides.id) AS member_count,
            (SELECT COUNT(*) FROM messages WHERE messages.ride_id = rides.id) AS message_count,
            payments.status AS payment_status,
            payments.payment_method AS payment_method,
            payments.amount AS payment_amount
     FROM rides
     JOIN users ON rides.created_by = users.id
     LEFT JOIN payments ON payments.ride_id = rides.id AND payments.user_id = ?
     WHERE rides.created_by = ?
        OR EXISTS (SELECT 1 FROM ride_members WHERE ride_members.ride_id = rides.id AND ride_members.user_id = ?)
     ORDER BY rides.ride_date DESC, rides.ride_time DESC"
);

$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$active_rides = [];
$completed_rides = [];
$today = date("Y-m-d");

$now_time = date("H:i:s");

while ($row = $result->fetch_assoc()) {
    $is_past = ($row["ride_date"] < $today) || ($row["ride_date"] === $today && $row["ride_time"] < $now_time);
    if ($row["status"] === "completed" || $is_past) {
        $completed_rides[] = $row;
    } else {
        $active_rides[] = $row;
    }
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rides - ShareCab</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../dashboard.php">
                    <img src="../img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab
                </a>
            </div>
            <div class="nav-links">
                <a href="../dashboard.php">Dashboard</a>
                <a href="search_ride.php">Find Ride</a>
                <a href="create_ride.php" class="btn-primary" style="padding: 8px 16px; font-size: 13px;">+ Create Ride</a>
                <a href="../auth/logout.php" class="nav-login">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">

        <div class="dashboard-header">
            <h1>📋 My Rides & Trip History</h1>
            <p>Track your active shared commutes, manage passenger bookings, and review completed trip records.</p>
        </div>

        <?php if (isset($_GET["deleted"])): ?>
            <div class="success-message">
                ✅ Ride has been successfully deleted.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["cancelled"])): ?>
            <div class="success-message">
                ✅ Your passenger seat has been cancelled and released back to the ride.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["completed"])): ?>
            <div class="success-message">
                🏁 Trip has been marked as Completed!
            </div>
        <?php endif; ?>

        <!-- SECTION 1: Active & Upcoming Shared Rides -->
        <div style="margin-bottom: 45px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 22px; font-weight: 800; color: var(--slate-900); display: flex; align-items: center; gap: 10px;">
                    <span>🔥</span>
                    <span>Active & Upcoming Rides</span>
                    <span style="font-size: 14px; font-weight: 600; color: var(--slate-500);">(<?php echo count($active_rides); ?> active)</span>
                </h2>
                <a href="create_ride.php" class="btn-secondary" style="font-size: 13px; padding: 7px 14px;">
                    + New Ride
                </a>
            </div>

            <?php if (count($active_rides) > 0): ?>
                <div class="ride-list">
                    <?php foreach ($active_rides as $ride): 
                        $is_creator = ($ride["created_by"] == $user_id);
                        $available_seats = max(0, $ride["max_seats"] - $ride["member_count"]);
                        $fare_split = $ride["member_count"] > 0 ? ($ride["fare"] / $ride["member_count"]) : $ride["fare"];
                    ?>
                        <div class="ride-card" style="border-left: 5px solid var(--primary);">
                            <div class="ride-card-left">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-wrap: wrap;">
                                    <span class="badge" style="background: <?php echo $is_creator ? 'var(--primary-light)' : 'var(--info-bg)'; ?>; color: <?php echo $is_creator ? 'var(--primary-hover)' : 'var(--info)'; ?>; padding: 4px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                        <?php echo $is_creator ? '👑 Ride Organizer' : '👤 Passenger'; ?>
                                    </span>
                                    <span class="status-badge <?php echo $available_seats > 0 ? 'open' : 'full'; ?>" style="padding: 2px 10px; font-size: 11px;">
                                        ● <?php echo ucfirst($ride["status"]); ?>
                                    </span>
                                    <?php if ($ride["payment_status"] === "completed"): ?>
                                        <span style="background: #dcfce7; color: #15803d; padding: 2px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700;">
                                            ✓ Paid (₹<?php echo number_format($ride["payment_amount"], 2); ?>)
                                        </span>
                                    <?php elseif ($ride["payment_method"] === "cash"): ?>
                                        <span style="background: #fef3c7; color: #b45309; padding: 2px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700;">
                                            ⏳ Cash on Arrival
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #f1f5f9; color: #64748b; padding: 2px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700;">
                                            ● Payment Pending
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h3>
                                    <span>🚕</span>
                                    <span><?php echo htmlspecialchars($ride["source"]); ?></span>
                                    <span style="color: var(--primary);">➔</span>
                                    <span><?php echo htmlspecialchars($ride["destination"]); ?></span>
                                </h3>

                                <div class="ride-chips">
                                    <span class="ride-chip">
                                        📅 <?php echo date("D, d M Y", strtotime($ride["ride_date"])); ?>
                                    </span>
                                    <span class="ride-chip">
                                        🕐 <?php echo date("h:i A", strtotime($ride["ride_time"])); ?>
                                    </span>
                                    <span class="ride-chip">
                                        👥 <?php echo $ride["member_count"]; ?> / <?php echo htmlspecialchars($ride["max_seats"]); ?> Passengers
                                    </span>
                                    <span class="ride-chip">
                                        💵 ₹<?php echo number_format($fare_split, 2); ?> / person
                                    </span>
                                    <span class="ride-chip">
                                        💬 <?php echo $ride["message_count"]; ?> messages
                                    </span>
                                </div>
                            </div>

                            <div class="ride-card-right">
                                <div class="fare-price">
                                    ₹<?php echo number_format($ride["fare"], 2); ?>
                                    <small>Total Fare</small>
                                </div>

                                <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; align-items: center;">
                                    <?php if ($ride["payment_status"] !== "completed"): ?>
                                        <a href="ride_details.php?id=<?php echo $ride['id']; ?>" class="btn-primary" style="font-size: 13px; padding: 9px 14px; background: #059669;">
                                            💳 Pay ₹<?php echo number_format($fare_split, 2); ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="ride_details.php?id=<?php echo $ride['id']; ?>" class="btn-secondary" style="font-size: 13px; padding: 9px 14px; color: #059669; border-color: #059669;">
                                            🧾 Receipt
                                        </a>
                                    <?php endif; ?>
                                    <a href="ride_details.php?id=<?php echo $ride['id']; ?>" class="btn-secondary" style="font-size: 13px; padding: 9px 14px;">
                                        📍 Details & Map
                                    </a>
                                    <a href="../chat/chat.php?ride_id=<?php echo $ride['id']; ?>" class="ride-button" style="font-size: 13px; padding: 9px 14px;">
                                        💬 Chat
                                    </a>

                                    <?php if ($is_creator): ?>
                                        <form method="POST" action="complete_ride.php" onsubmit="return confirm('🏁 Mark this ride as completed?');" style="display: inline;">
                                            <input type="hidden" name="ride_id" value="<?php echo $ride['id']; ?>">
                                            <button type="submit" class="btn-secondary" style="font-size: 12px; padding: 8px 12px; color: var(--success); border-color: var(--success);">
                                                🏁 Complete
                                            </button>
                                        </form>
                                        <form method="POST" action="delete_ride.php" onsubmit="return confirm('⚠️ Delete this entire ride? All passenger seats and chat messages will be deleted.');" style="display: inline;">
                                            <input type="hidden" name="ride_id" value="<?php echo $ride['id']; ?>">
                                            <button type="submit" class="btn-danger" style="font-size: 12px; padding: 8px 12px;">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="leave_ride.php" onsubmit="return confirm('🚪 Cancel your seat in this ride?');" style="display: inline;">
                                            <input type="hidden" name="ride_id" value="<?php echo $ride['id']; ?>">
                                            <button type="submit" class="btn-warning" style="font-size: 12px; padding: 8px 12px;">
                                                🚪 Leave
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 40px 20px; color: var(--slate-500);">
                    <p style="font-size: 40px; margin-bottom: 10px;">🚕</p>
                    <h3 style="font-size: 18px; font-weight: 800; color: var(--slate-800); margin-bottom: 6px;">No upcoming active rides</h3>
                    <p style="font-size: 13px; max-width: 420px; margin: 0 auto 20px auto;">
                        You have no scheduled upcoming trips. Search for available shared cabs or post your journey now!
                    </p>
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <a href="search_ride.php" class="btn-secondary" style="font-size: 13px;">🔍 Find Rides</a>
                        <a href="create_ride.php" class="btn-primary" style="font-size: 13px;">+ Create a Ride</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECTION 2: Completed Rides History (Shown Separately) -->
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 22px; font-weight: 800; color: var(--slate-900); display: flex; align-items: center; gap: 10px;">
                    <span>🏁</span>
                    <span>Completed Rides History</span>
                    <span style="font-size: 14px; font-weight: 600; color: var(--slate-500);">(<?php echo count($completed_rides); ?> finished)</span>
                </h2>
            </div>

            <?php if (count($completed_rides) > 0): ?>
                <div class="ride-list">
                    <?php foreach ($completed_rides as $ride): 
                        $fare_split = $ride["member_count"] > 0 ? ($ride["fare"] / $ride["member_count"]) : $ride["fare"];
                        $is_creator = ($ride["created_by"] == $user_id);
                    ?>
                        <div class="ride-card" style="background: var(--slate-50); border: 1px solid var(--slate-200); opacity: 0.95;">
                            <div class="ride-card-left">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-wrap: wrap;">
                                    <span class="status-badge" style="background: var(--slate-200); color: var(--slate-700); padding: 3px 10px; font-size: 11px;">
                                        🏁 Completed Trip
                                    </span>
                                    <span class="badge" style="background: var(--slate-200); color: var(--slate-700); padding: 3px 8px; font-size: 11px; border-radius: var(--radius-full);">
                                        <?php echo $is_creator ? 'Organized by You' : 'Passenger'; ?>
                                    </span>
                                    <?php if ($ride["payment_status"] === "completed"): ?>
                                        <span style="background: #dcfce7; color: #15803d; padding: 2px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700;">
                                            ✓ Paid (₹<?php echo number_format($ride["payment_amount"], 2); ?>)
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #fef3c7; color: #b45309; padding: 2px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 700;">
                                            ⏳ Payment Recorded (Cash)
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h3 style="color: var(--slate-800);">
                                    <?php echo htmlspecialchars($ride["source"]); ?> 
                                    <span style="color: var(--slate-400);">➔</span> 
                                    <?php echo htmlspecialchars($ride["destination"]); ?>
                                </h3>

                                <div class="ride-chips">
                                    <span class="ride-chip">📅 <?php echo date("d M Y", strtotime($ride["ride_date"])); ?></span>
                                    <span class="ride-chip">🕐 <?php echo date("h:i A", strtotime($ride["ride_time"])); ?></span>
                                    <span class="ride-chip">👥 <?php echo $ride["member_count"]; ?> Passengers traveled</span>
                                    <span class="ride-chip">💵 Split Paid: ₹<?php echo number_format($fare_split, 2); ?></span>
                                </div>
                            </div>

                            <div class="ride-card-right">
                                <div class="fare-price" style="color: var(--slate-600);">
                                    ₹<?php echo number_format($ride["fare"], 2); ?>
                                    <small>Total Final Fare</small>
                                </div>
                                <a href="ride_details.php?id=<?php echo $ride['id']; ?>" class="btn-primary" style="font-size: 12px; padding: 8px 14px; background: #059669;">
                                    🧾 View Digital Receipt ➔
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 30px; color: var(--slate-500); background: var(--slate-50);">
                    <p>No completed rides yet. Once you complete your journeys, they will appear here in your travel history.</p>
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

</body>
</html>