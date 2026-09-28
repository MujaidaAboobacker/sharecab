<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];
    $vehicle = trim($_POST["vehicle"]);
    $vehicle_number = trim($_POST["vehicle_number"]);

    if (
        empty($name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($vehicle) ||
        empty($vehicle_number)
    ) {
        $message = "Please fill in all driver fields.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } elseif (strlen($password) < 6) {
        $message = "Driver login password must be at least 6 characters.";
        $message_type = "error";
    } else {
        // Check if email or vehicle number already registered
        $check = $conn->prepare("SELECT id FROM drivers WHERE email = ? OR vehicle_number = ?");
        $check->bind_param("ss", $email, $vehicle_number);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = "This email or vehicle plate number is already registered.";
            $message_type = "error";
        } else {
            $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO drivers (name, email, phone, password, vehicle, vehicle_number, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'available')"
            );
            $stmt->bind_param("ssssss", $name, $email, $phone, $hashed_pw, $vehicle, $vehicle_number);

            if ($stmt->execute()) {
                $message = "Driver registered successfully! Driver can now log into the Driver Portal.";
                $message_type = "success";
            } else {
                $message = "Failed to register driver. Please try again.";
                $message_type = "error";
            }
            $stmt->close();
        }
        $check->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Driver - ShareCab</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../dashboard.php" style="display: flex; align-items: center;">
                    <img src="../img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab <span class="badge">Admin</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="../dashboard.php">Dashboard</a>
                <a href="drivers.php">Driver Directory</a>
                <a href="../auth/logout.php" class="nav-login">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="form-container">
        <div class="form-card">
            <h1>🚗 Add New Driver</h1>
            <p class="form-subtitle">Register a driver and vehicle for shared cab assignments.</p>

            <?php if (!empty($message)): ?>
                <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Driver Full Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="e.g. Vikram Singh"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Driver Email Address (Portal Login)</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="driver@sharecab.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Driver Portal Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimum 6 characters"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="10-digit mobile number"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="vehicle">Vehicle Model & Make</label>
                    <input
                        type="text"
                        id="vehicle"
                        name="vehicle"
                        placeholder="e.g. Maruti Suzuki WagonR"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="vehicle_number">Vehicle Registration Plate</label>
                    <input
                        type="text"
                        id="vehicle_number"
                        name="vehicle_number"
                        placeholder="e.g. KL 07 AB 1234"
                        required
                    >
                </div>

                <button type="submit" class="form-button">
                    🚗 Save & Register Driver
                </button>
            </form>

            <p class="form-footer">
                <a href="drivers.php">View All Drivers</a> &bull; <a href="../dashboard.php">Dashboard</a>
            </p>
        </div>
    </div>

    <footer>
        <div class="footer-container">
            <h3>🚕 ShareCab</h3>
            <p>Cab sharing made simple & affordable.</p>
            <p>Web Programming Lab Project</p>
        </div>
    </footer>

    <script src="../js/script.js"></script>
</body>
</html>