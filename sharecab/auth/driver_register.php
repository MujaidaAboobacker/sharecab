<?php

session_start();

if (isset($_SESSION["driver_id"])) {
    header("Location: ../driver/dashboard.php");
    exit();
}

require_once "../config/database.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $vehicle = trim($_POST["vehicle"]);
    $vehicle_number = trim($_POST["vehicle_number"]);
    $password = $_POST["password"];

    if (
        empty($name) ||
        empty($email) ||
        empty($phone) ||
        empty($vehicle) ||
        empty($vehicle_number) ||
        empty($password)
    ) {
        $message = "Please fill in all required driver details.";
        $message_type = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $message_type = "error";
    } else {
        // Check if email or vehicle number already exists
        $check = $conn->prepare("SELECT id FROM drivers WHERE email = ? OR vehicle_number = ?");
        $check->bind_param("ss", $email, $vehicle_number);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $message = "A driver with this email or vehicle number is already registered.";
            $message_type = "error";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO drivers (name, email, phone, vehicle, vehicle_number, password)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssssss", $name, $email, $phone, $vehicle, $vehicle_number, $hashed_password);

            if ($stmt->execute()) {
                $driver_id = $stmt->insert_id;
                $_SESSION["driver_id"] = $driver_id;
                $_SESSION["driver_name"] = $name;
                $_SESSION["driver_email"] = $email;
                $_SESSION["driver_vehicle"] = $vehicle;
                $_SESSION["driver_vehicle_number"] = $vehicle_number;
                $_SESSION["role"] = "driver";

                header("Location: ../driver/dashboard.php");
                exit();
            } else {
                $message = "Registration failed. Please try again.";
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
    <title>Driver Registration - ShareCab Partner</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../index.html">
                    <img src="../img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab <span class="badge">Driver Partner</span>
                </a>
            </div>
            <div class="nav-links">
                <a href="../index.html">Home</a>
                <a href="driver_login.php" class="nav-login">Driver Sign In</a>
                <a href="login.php" style="color: var(--slate-500); font-size: 13px;">Passenger Portal ➔</a>
            </div>
        </div>
    </nav>

    <div class="form-container" style="max-width: 580px;">
        <div class="form-card">
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="../img/sharecab_car.jpg" alt="ShareCab Car" style="width: 100px; height: 75px; object-fit: cover; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 12px;">
                <h1>Drive with ShareCab</h1>
                <p class="form-subtitle">Register your vehicle, accept shared passenger rides, and maximize your daily trip earnings.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
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
                        <label for="phone">Phone Number</label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            placeholder="10-digit mobile number"
                            pattern="[0-9]{10}"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Driver Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="driver@example.com"
                        required
                    >
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
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
                        <label for="vehicle_number">Vehicle Registration Number</label>
                        <input
                            type="text"
                            id="vehicle_number"
                            name="vehicle_number"
                            placeholder="e.g. KL 07 AB 1234"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Create Password (Min. 6 chars)</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        minlength="6"
                        required
                    >
                </div>

                <button type="submit" class="form-button" style="padding: 15px; font-size: 16px;">
                    🚗 Register as ShareCab Driver ➔
                </button>
            </form>

            <p class="form-footer">
                Already registered as a driver? 
                <a href="driver_login.php">Sign In Here</a>
            </p>
        </div>
    </div>

    <footer>
        <div class="footer-container">
            <h3>🚕 ShareCab</h3>
            <p>Cab sharing made simple & affordable &bull; Driver Partner Network</p>
            <p>Web Programming Lab Project</p>
        </div>
    </footer>

    <script src="../js/script.js"></script>
</body>
</html>
