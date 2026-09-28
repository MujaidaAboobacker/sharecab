<?php

session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: ../dashboard.php");
    exit();
}

require_once "../config/database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $message = "Please enter both email address and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, email, password
             FROM users
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password"])) {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_email"] = $user["email"];

                header("Location: ../dashboard.php");
                exit();
            } else {
                $message = "Incorrect password. Please verify your credentials.";
            }
        } else {
            $message = "No account found with this email address.";
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
    <title>Sign In - ShareCab</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../index.html" style="display: flex; align-items: center;">
                    <img src="../img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab
                </a>
            </div>
            <div class="nav-links">
                <a href="../index.html">Home</a>
                <a href="driver_login.php" style="color: var(--slate-600); font-size: 13px; font-weight: 600;">🚗 Driver Sign In</a>
                <a href="register.php" class="nav-register">Create Account</a>
            </div>
        </div>
    </nav>

    <!-- Login Container -->
    <div class="form-container">
        <div class="form-card">
            <h1>Welcome Back 👋</h1>
            <p class="form-subtitle">Enter your credentials to access your ShareCab dashboard.</p>

            <?php if (!empty($message)): ?>
                <div class="error-message">
                    ⚠️ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
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
                    Sign In ➔
                </button>
            </form>

            <p class="form-footer">
                Don't have an account yet? 
                <a href="register.php">Create Account</a>
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