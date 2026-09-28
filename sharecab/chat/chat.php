<?php

session_start();
require_once "../config/database.php";

$is_driver = isset($_SESSION["driver_id"]);
$is_user = isset($_SESSION["user_id"]);

if (!$is_driver && !$is_user) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET["ride_id"])) {
    header("Location: " . ($is_driver ? "../driver/dashboard.php" : "../dashboard.php"));
    exit();
}

$ride_id = intval($_GET["ride_id"]);
$user_id = $is_user ? $_SESSION["user_id"] : null;
$driver_id = $is_driver ? $_SESSION["driver_id"] : null;

/* Verify ride exists */
$ride_stmt = $conn->prepare("SELECT id, source, destination, created_by, status FROM rides WHERE id = ?");
$ride_stmt->bind_param("i", $ride_id);
$ride_stmt->execute();
$ride_res = $ride_stmt->get_result();

if ($ride_res->num_rows === 0) {
    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 60px;'><h2>❌ Ride not found.</h2><a href='../index.html'>Home</a></div>";
    exit();
}

$ride = $ride_res->fetch_assoc();
$ride_stmt->close();

/* Check chat access authorization */
$has_access = false;

if ($is_user) {
    $access_stmt = $conn->prepare(
        "SELECT id FROM ride_members WHERE ride_id = ? AND user_id = ?"
    );
    $access_stmt->bind_param("ii", $ride_id, $user_id);
    $access_stmt->execute();
    if ($ride["created_by"] == $user_id || $access_stmt->get_result()->num_rows > 0) {
        $has_access = true;
    }
    $access_stmt->close();
} elseif ($is_driver) {
    $driver_acc = $conn->prepare("SELECT id FROM ride_drivers WHERE ride_id = ? AND driver_id = ?");
    $driver_acc->bind_param("ii", $ride_id, $driver_id);
    $driver_acc->execute();
    if ($driver_acc->get_result()->num_rows > 0) {
        $has_access = true;
    }
    $driver_acc->close();
}

if (!$has_access) {
    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 60px;'><h2>🔒 You are not authorized to view this ride chat.</h2><a href='../index.html'>Home</a></div>";
    exit();
}

/* Send message */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["message"])) {
    if ($ride["status"] === "completed") {
        header("Location: chat.php?ride_id=" . $ride_id);
        exit();
    }

    $message = trim($_POST["message"]);

    if (!empty($message)) {
        if ($is_driver) {
            $stmt = $conn->prepare(
                "INSERT INTO messages (ride_id, driver_id, sender_type, message) VALUES (?, ?, 'driver', ?)"
            );
            $stmt->bind_param("iis", $ride_id, $driver_id, $message);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO messages (ride_id, user_id, sender_type, message) VALUES (?, ?, 'user', ?)"
            );
            $stmt->bind_param("iis", $ride_id, $user_id, $message);
        }
        $stmt->execute();
        $stmt->close();

        header("Location: chat.php?ride_id=" . $ride_id);
        exit();
    }
}

/* Get all messages with sender names (both passengers and drivers) */
$msg_query = "
    SELECT messages.id,
           messages.user_id,
           messages.driver_id,
           messages.sender_type,
           messages.message,
           messages.sent_at,
           CASE 
               WHEN messages.sender_type = 'driver' THEN drivers.name
               ELSE users.name 
           END AS sender_name
    FROM messages
    LEFT JOIN users ON messages.user_id = users.id
    LEFT JOIN drivers ON messages.driver_id = drivers.id
    WHERE messages.ride_id = ?
    ORDER BY messages.sent_at ASC
";
$stmt = $conn->prepare($msg_query);
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$result = $stmt->get_result();

$back_url = $is_driver ? "../driver/dashboard.php" : "../rides/ride_details.php?id=" . $ride_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ride Chat - ShareCab</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="<?php echo $back_url; ?>" style="display: flex; align-items: center;">
                    <img src="../img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab <?php if ($is_driver): ?><span class="badge" style="background: #10b981; color: white;">Driver</span><?php endif; ?>
                </a>
            </div>
            <div class="nav-links">
                <?php if ($is_driver): ?>
                    <a href="../driver/dashboard.php">Driver Dashboard</a>
                    <a href="../auth/driver_logout.php" class="nav-login">Logout</a>
                <?php else: ?>
                    <a href="../rides/ride_details.php?id=<?php echo $ride_id; ?>">📍 Ride Details</a>
                    <a href="../rides/my_rides.php">My Rides</a>
                    <a href="../auth/logout.php" class="nav-login">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Chat Container -->
    <div class="chat-container">

        <!-- Chat Header -->
        <div class="chat-header">
            <div>
                <h1>
                    <span>💬</span>
                    <span><?php echo htmlspecialchars($ride["source"]); ?> ➔ <?php echo htmlspecialchars($ride["destination"]); ?></span>
                </h1>
                <p style="font-size: 13px; color: var(--slate-400); margin: 0;">
                    Group Chat &bull; Coordinate meetup time & location with passengers and driver
                </p>
            </div>
            <a href="<?php echo $back_url; ?>" class="btn-secondary" style="font-size: 12px; padding: 6px 14px; background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.2);">
                <?php echo $is_driver ? '← Dashboard' : 'View Ride ➔'; ?>
            </a>
        </div>

        <!-- Chat Messages Window -->
        <div class="chat-box" id="chatBox">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($msg = $result->fetch_assoc()): 
                    $is_self = false;
                    if ($is_driver && $msg["sender_type"] === 'driver' && $msg["driver_id"] == $driver_id) {
                        $is_self = true;
                    } elseif ($is_user && $msg["sender_type"] === 'user' && $msg["user_id"] == $user_id) {
                        $is_self = true;
                    }
                    $is_msg_from_driver = ($msg["sender_type"] === 'driver');
                ?>
                    <div class="chat-message <?php echo $is_self ? 'self' : 'other'; ?>">
                        <div class="chat-meta">
                            <strong>
                                <?php if ($is_msg_from_driver): ?>
                                    🚗 Driver: <?php echo htmlspecialchars($msg["sender_name"] ?: "Assigned Driver"); ?>
                                <?php else: ?>
                                    <?php echo $is_self ? 'You' : htmlspecialchars($msg["sender_name"] ?: "Passenger"); ?>
                                <?php endif; ?>
                            </strong>
                            <span><?php echo date("h:i A", strtotime($msg["sent_at"])); ?></span>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($msg["message"])); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; margin: auto; color: var(--slate-400); padding: 40px 20px;">
                    <p style="font-size: 44px; margin-bottom: 8px;">💬</p>
                    <h3 style="color: var(--slate-700); font-weight: 700; margin-bottom: 4px;">No messages yet</h3>
                    <p style="font-size: 13px;">Say hello and coordinate your ride meetup!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Message Input Form / Archive Banner -->
        <?php if ($ride["status"] === "completed"): ?>
            <div style="background: var(--slate-100); border-top: 1px solid var(--slate-200); padding: 18px; text-align: center; color: var(--slate-600); font-size: 14px; font-weight: 700; border-radius: 0 0 var(--radius-lg) var(--radius-lg);">
                🏁 This trip has completed. Chat messages are archived in read-only mode.
            </div>
        <?php else: ?>
            <form method="POST" action="" class="chat-input-form" id="chatForm">
                <input
                    type="text"
                    name="message"
                    id="messageInput"
                    placeholder="<?php echo $is_driver ? 'Message all passengers...' : 'Type your message here...'; ?>"
                    autocomplete="off"
                    required
                >
                <button type="submit">
                    Send ➔
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin: 15px auto 40px auto;">
        <a href="<?php echo $back_url; ?>" style="font-size: 13px; color: var(--slate-500); font-weight: 600;">
            ← Back to <?php echo $is_driver ? 'Driver Dashboard' : 'Ride Details'; ?>
        </a>
    </div>

    <footer>
        <div class="footer-container">
            <h3>🚕 ShareCab</h3>
            <p>Cab sharing made simple & affordable.</p>
            <p>Web Programming Lab Project</p>
        </div>
    </footer>

    <!-- Auto scroll chat to bottom -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const chatBox = document.getElementById("chatBox");
            if (chatBox) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }

            const messageInput = document.getElementById("messageInput");
            if (messageInput) {
                messageInput.focus();
            }
        });
    </script>

</body>
</html>
<?php
$stmt->close();
?>