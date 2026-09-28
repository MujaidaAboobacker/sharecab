<?php

session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all drivers with count of assigned rides
$query = "
    SELECT drivers.*, COUNT(ride_drivers.id) AS active_assignments
    FROM drivers
    LEFT JOIN ride_drivers ON drivers.id = ride_drivers.driver_id
    GROUP BY drivers.id
    ORDER BY drivers.name ASC
";
$result = $conn->query($query);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Directory - ShareCab</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../dashboard.php" style="display: flex; align-items: center;">
                    <img src="../img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab <span class="badge">Driver Admin</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="../dashboard.php">Dashboard</a>
                <a href="add_driver.php" class="btn-primary" style="padding: 8px 16px; font-size: 13px;">+ Add New Driver</a>
                <a href="../auth/logout.php" class="nav-login">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <h1>🚗 Registered Drivers Directory</h1>
            <p>Manage cab drivers, view contact details, vehicle specifications, and active ride assignments.</p>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 20px; font-weight: 800;">All Drivers (<?php echo $result ? $result->num_rows : 0; ?>)</h2>
                <a href="add_driver.php" class="btn-secondary" style="padding: 8px 16px; font-size: 13px;">
                    🚗 Register Driver
                </a>
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Driver ID</th>
                                <th>Driver Name</th>
                                <th>Email / Login</th>
                                <th>Contact Phone</th>
                                <th>Vehicle & Plate</th>
                                <th>Live Status</th>
                                <th>Assigned Rides</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($driver = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo htmlspecialchars($driver["id"]); ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="avatar-circle" style="width: 32px; height: 32px; font-size: 13px;">
                                                <?php echo strtoupper(substr($driver["name"], 0, 1)); ?>
                                            </div>
                                            <strong><?php echo htmlspecialchars($driver["name"]); ?></strong>
                                        </div>
                                    </td>
                                    <td>📧 <?php echo htmlspecialchars($driver["email"] ?? 'N/A'); ?></td>
                                    <td><a href="tel:<?php echo htmlspecialchars($driver['phone']); ?>" style="color: var(--primary-hover); text-decoration: none;">📞 <?php echo htmlspecialchars($driver["phone"]); ?></a></td>
                                    <td>
                                        🚗 <?php echo htmlspecialchars($driver["vehicle"]); ?><br>
                                        <span class="route-badge" style="font-size: 11px;"><?php echo htmlspecialchars($driver["vehicle_number"]); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($driver["status"] === 'on_trip'): ?>
                                            <span class="status-badge full">● On Trip</span>
                                        <?php elseif ($driver["status"] === 'offline'): ?>
                                            <span class="status-badge">● Offline</span>
                                        <?php else: ?>
                                            <span class="status-badge open">● Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $driver["active_assignments"] > 0 ? 'open' : ''; ?>">
                                            <?php echo $driver["active_assignments"]; ?> Active
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--slate-500);">
                    <p style="font-size: 40px; margin-bottom: 10px;">🚗</p>
                    <p>No drivers have been registered yet.</p>
                    <a href="add_driver.php" class="btn-primary" style="margin-top: 15px;">Register First Driver</a>
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
