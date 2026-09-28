<?php
session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: ../dashboard.php");
    exit();
}

require_once "../config/database.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $message_type = "error";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "This email is already registered. Please sign in instead.";
            $message_type = "error";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, phone, password)
                 VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssss",
                $name,
                $email,
                $phone,
                $hashed_password
            );

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $_SESSION["user_id"] = $user_id;
                $_SESSION["user_name"] = $name;
                $_SESSION["user_email"] = $email;

                header("Location: ../dashboard.php");
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
    <title>Create an Account - ShareCab</title>
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
                <a href="driver_register.php" style="color: var(--slate-600); font-size: 13px; font-weight: 600;">🚗 Register as Driver</a>
                <a href="login.php" class="nav-login">Sign In</a>
            </div>
        </div>
    </nav>

    <!-- Registration Container -->
    <div class="form-container">
        <div class="form-card">
            <h1>Join ShareCab 🚀</h1>
            <p class="form-subtitle">Create an account to start sharing rides and splitting travel fares.</p>

            <?php if (!empty($message)): ?>
                <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo $message_type === 'success' ? '✅ ' : '⚠️ '; ?>
                    <?php echo htmlspecialchars($message); ?>
                    <?php if ($message_type === 'success'): ?>
                        &nbsp;&bull;&nbsp;<a href="login.php" style="font-weight: 700; text-decoration: underline;">Login now ➔</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="e.g. Rahul Sharma"
                        required
                    >
                </div>

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
                    <label for="phone">Phone Number (10 Digits)</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="9876543210"
                        pattern="[0-9]{10}"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password (Minimum 6 Characters)</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Create a strong password"
                        minlength="6"
                        required
                    >
                </div>

                <button type="submit" class="form-button">
                    Create Account ➔
                </button>
            </form>

            <p class="form-footer">
                Already have an account? 
                <a href="login.php">Sign In</a>
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