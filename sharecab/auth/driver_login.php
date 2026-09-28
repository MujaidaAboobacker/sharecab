<?php

session_start();

if (isset($_SESSION["driver_id"])) {
    header("Location: ../driver/dashboard.php");
    exit();
}

require_once "../config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $message = "Please enter both driver email and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, email, vehicle, vehicle_number, password
             FROM drivers
             WHERE email = ?"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $driver = $res->fetch_assoc();

            if (!empty($driver["password"]) && password_verify($password, $driver["password"])) {
                $_SESSION["driver_id"] = $driver["id"];
                $_SESSION["driver_name"] = $driver["name"];
                $_SESSION["driver_email"] = $driver["email"];
                $_SESSION["driver_vehicle"] = $driver["vehicle"];
                $_SESSION["driver_vehicle_number"] = $driver["vehicle_number"];
                $_SESSION["role"] = "driver";

                header("Location: ../driver/dashboard.php");
                exit();
            } else {
                $message = "Incorrect password. Please verify your credentials.";
            }
        } else {
            $message = "No registered driver found with this email address.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Sign In - ShareCab Partner</title>
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
                <a href="driver_register.php" class="nav-register">Register as Driver</a>
                <a href="login.php" style="color: var(--slate-500); font-size: 13px;">Passenger Sign In ➔</a>
            </div>
        </div>
    </nav>

    <div class="form-container">
        <div class="form-card">
            <div style="text-align: center; margin-bottom: 24px;">
                <img src="../img/sharecab_car.jpg" alt="ShareCab Car" style="width: 100px; height: 75px; object-fit: cover; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); margin-bottom: 12px;">
                <h1>Driver Partner Sign In</h1>
                <p class="form-subtitle">Sign in to access your assigned shared trips, route maps, and passenger manifests.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="error-message">
                    ⚠️ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Driver Registered Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="driver@example.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <button type="submit" class="form-button">
                    Sign In to Driver Dashboard ➔
                </button>
            </form>

            <p class="form-footer">
                New driver? <a href="driver_register.php">Register Vehicle & Account</a><br><br>
                Looking for passenger rides? <a href="login.php">Passenger Sign In</a>
            </p>
        </div>
    </div>

    <footer>
        <div class="footer-container">
            <h3>🚕 ShareCab</h3>
            <p>Cab sharing made simple & affordable &bull; Driver Partner Portal</p>
            <p>Web Programming Lab Project</p>
        </div>
    </footer>

    <script src="../js/script.js"></script>
</body>
</html>
