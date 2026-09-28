<?php

session_start();

require_once "../config/database.php";

$is_driver = isset($_SESSION["driver_id"]);
$is_user = isset($_SESSION["user_id"]);

if (!$is_driver && !$is_user) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET["id"])) {
    header("Location: " . ($is_driver ? "../driver/dashboard.php" : "my_rides.php"));
    exit();
}

$ride_id = intval($_GET["id"]);
$user_id = $is_user ? $_SESSION["user_id"] : null;
$driver_id = $is_driver ? $_SESSION["driver_id"] : null;

/* Get ride details */
$stmt = $conn->prepare(
    "SELECT rides.*, users.name AS creator_name, users.phone AS creator_phone, users.email AS creator_email
     FROM rides
     JOIN users ON rides.created_by = users.id
     WHERE rides.id = ?"
);
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$ride_result = $stmt->get_result();

if ($ride_result->num_rows == 0) {
    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 60px;'><h2>❌ Ride not found.</h2><a href='" . ($is_driver ? "../driver/dashboard.php" : "my_rides.php") . "'>Back to Dashboard</a></div>";
    exit();
}

$ride = $ride_result->fetch_assoc();
$stmt->close();

/* Check whether current user/driver is allowed to view the ride */
$is_creator = ($is_user && $ride["created_by"] == $user_id);
$is_member = false;

if ($is_user) {
    $access_stmt = $conn->prepare(
        "SELECT id FROM ride_members WHERE ride_id = ? AND user_id = ?"
    );
    $access_stmt->bind_param("ii", $ride_id, $user_id);
    $access_stmt->execute();
    $is_member = ($access_stmt->get_result()->num_rows > 0);
    $access_stmt->close();
}

// Check if current driver is assigned to this ride
$is_assigned_driver = false;
if ($is_driver) {
    $d_check = $conn->prepare("SELECT id FROM ride_drivers WHERE ride_id = ? AND driver_id = ?");
    $d_check->bind_param("ii", $ride_id, $driver_id);
    $d_check->execute();
    $is_assigned_driver = ($d_check->get_result()->num_rows > 0);
    $d_check->close();
}

$is_guest_preview = false;
if (!$is_creator && !$is_member && !$is_assigned_driver) {
    if ($is_user && $ride["status"] === "open") {
        $is_guest_preview = true;
    } else {
        echo "<div style='font-family: sans-serif; text-align: center; margin-top: 60px;'><h2>🔒 You are not authorized to view this ride.</h2><a href='../index.html'>Home</a></div>";
        exit();
    }
}

/* Get passengers */
$passenger_stmt = $conn->prepare(
    "SELECT users.id, users.name, users.email, users.phone, ride_members.joined_at
     FROM ride_members
     JOIN users ON ride_members.user_id = users.id
     WHERE ride_members.ride_id = ?
     ORDER BY ride_members.joined_at ASC"
);
$passenger_stmt->bind_param("i", $ride_id);
$passenger_stmt->execute();
$passenger_result = $passenger_stmt->get_result();
$passenger_count = $passenger_result->num_rows;

/* Available seats */
$available_seats = max(0, $ride["max_seats"] - $passenger_count);

/* Fare per person */
$fare_per_person = ($passenger_count > 0) ? ($ride["fare"] / $passenger_count) : $ride["fare"];

/* Get driver information */
$driver_stmt = $conn->prepare(
    "SELECT drivers.id, drivers.name, drivers.phone, drivers.vehicle, drivers.vehicle_number
     FROM ride_drivers
     JOIN drivers ON ride_drivers.driver_id = drivers.id
     WHERE ride_drivers.ride_id = ?"
);
$driver_stmt->bind_param("i", $ride_id);
$driver_stmt->execute();
$driver_result = $driver_stmt->get_result();
$driver = ($driver_result->num_rows > 0) ? $driver_result->fetch_assoc() : null;
$driver_stmt->close();

/* If user is creator, fetch all drivers list for assignment */
$all_drivers = [];
if ($is_creator) {
    $d_list = $conn->query("SELECT id, name, vehicle, vehicle_number FROM drivers ORDER BY name ASC");
    if ($d_list) {
        while ($d = $d_list->fetch_assoc()) {
            $all_drivers[] = $d;
        }
    }
}

// Fetch payments for this ride
$pay_query = $conn->prepare("SELECT user_id, amount, payment_method, transaction_id, status, paid_at FROM payments WHERE ride_id = ?");
$pay_query->bind_param("i", $ride_id);
$pay_query->execute();
$pay_res = $pay_query->get_result();
$payments_by_user = [];
while ($p = $pay_res->fetch_assoc()) {
    $payments_by_user[$p["user_id"]] = $p;
}
$pay_query->close();

$my_payment = ($is_user && isset($payments_by_user[$user_id])) ? $payments_by_user[$user_id] : null;

$total_collected = 0;
$paid_passengers_count = 0;
foreach ($payments_by_user as $p) {
    if ($p["status"] === "completed") {
        $total_collected += floatval($p["amount"]);
        $paid_passengers_count++;
    }
}
$collection_pct = ($ride["fare"] > 0) ? min(100, round(($total_collected / $ride["fare"]) * 100)) : 0;

$assigned_notice = isset($_GET["assigned"]) ? true : false;
$payment_success = isset($_GET["payment_success"]) ? true : false;
$cash_confirmed = isset($_GET["cash_confirmed"]) ? true : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ride Details - ShareCab</title>
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Leaflet.js Interactive Map CDN -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .map-pin-custom {
            font-size: 24px;
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="<?php echo $is_driver ? '../driver/dashboard.php' : '../dashboard.php'; ?>" style="display: flex; align-items: center;">
                    <img src="../img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab <?php if ($is_driver): ?><span class="badge" style="background: #10b981; color: white;">Driver</span><?php endif; ?>
                </a>
            </div>
            <div class="nav-links">
                <?php if ($is_driver): ?>
                    <a href="../driver/dashboard.php">Driver Dashboard</a>
                    <a href="../auth/driver_logout.php" class="nav-login">Logout</a>
                <?php else: ?>
                    <a href="../dashboard.php">Dashboard</a>
                    <a href="my_rides.php">My Rides</a>
                    <a href="../auth/logout.php" class="nav-login">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container">

        <?php if ($assigned_notice): ?>
            <div class="success-message">
                ✅ Driver has been successfully assigned to this ride!
            </div>
        <?php endif; ?>

        <?php if ($payment_success): ?>
            <div class="success-message">
                ✅ Payment processed successfully! Your share of ₹<?php echo number_format($fare_per_person, 2); ?> is settled.
            </div>
        <?php endif; ?>

        <?php if ($cash_confirmed): ?>
            <div class="success-message">
                ✅ Cash payment confirmed and marked as settled!
            </div>
        <?php endif; ?>

        <!-- Route Header Card -->
        <div class="ride-main-card">
            <div class="ride-route-header">
                <h2>
                    <span>🚕</span>
                    <span><?php echo htmlspecialchars($ride["source"]); ?></span>
                    <span style="color: var(--primary);">➔</span>
                    <span><?php echo htmlspecialchars($ride["destination"]); ?></span>
                </h2>
                <div>
                    <span class="status-badge <?php echo $available_seats > 0 ? 'open' : 'full'; ?>">
                        ● <?php echo $available_seats > 0 ? 'Seats Available' : 'Ride Full'; ?>
                    </span>
                </div>
            </div>
            <p style="color: var(--slate-600); font-size: 14px;">
                Organized by <strong><?php echo htmlspecialchars($ride["creator_name"]); ?></strong> 
                &bull; Created on <?php echo date("M d, Y", strtotime($ride["created_at"])); ?>
            </p>
        </div>

        <!-- 6 Metrics Grid -->
        <div class="ride-details-grid">
            <div class="metric-card">
                <span class="metric-icon">📅</span>
                <h3>Travel Date</h3>
                <p><?php echo date("d M Y", strtotime($ride["ride_date"])); ?></p>
            </div>

            <div class="metric-card">
                <span class="metric-icon">🕐</span>
                <h3>Departure</h3>
                <p><?php echo date("h:i A", strtotime($ride["ride_time"])); ?></p>
            </div>

            <div class="metric-card">
                <span class="metric-icon">👥</span>
                <h3>Passengers</h3>
                <p><?php echo $passenger_count; ?> / <?php echo htmlspecialchars($ride["max_seats"]); ?></p>
            </div>

            <div class="metric-card">
                <span class="metric-icon">🪑</span>
                <h3>Seats Left</h3>
                <p style="color: <?php echo $available_seats > 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                    <?php echo $available_seats; ?>
                </p>
            </div>

            <div class="metric-card">
                <span class="metric-icon">💰</span>
                <h3>Total Fare</h3>
                <p>₹<?php echo number_format($ride["fare"], 2); ?></p>
            </div>

            <div class="metric-card" style="background: var(--primary-light); border-color: var(--primary);">
                <span class="metric-icon">💵</span>
                <h3 style="color: var(--primary-hover);">Fare / Person</h3>
                <p style="color: var(--primary-hover);">₹<?php echo number_format($fare_per_person, 2); ?></p>
            </div>
        </div>

        <!-- Interactive Route Map Card -->
        <div class="map-card">
            <div class="map-header">
                <h3>🗺️ Interactive Ride Route</h3>
                <span class="route-badge">OpenStreetMap Live Routing</span>
            </div>
            
            <div id="rideMap"></div>

            <div class="map-route-summary">
                <div>
                    📍 <strong><?php echo htmlspecialchars($ride["source"]); ?></strong> to 
                    <strong><?php echo htmlspecialchars($ride["destination"]); ?></strong>
                </div>
                <div id="routeDistanceInfo">
                    📏 Calculating distance...
                </div>
            </div>
        </div>

        <!-- Payment & Fare Settlement Card -->
        <div class="payment-card">
            <div class="payment-header">
                <div>
                    <h2 style="font-size: 20px; font-weight: 800; color: var(--slate-900); display: flex; align-items: center; gap: 8px;">
                        <span>💳</span>
                        <span>Fare Settlement & Payments</span>
                    </h2>
                    <p style="color: var(--slate-500); font-size: 14px; margin-top: 4px;">
                        Total Ride Fare: <strong>₹<?php echo number_format($ride["fare"], 2); ?></strong> &bull; Split among <strong><?php echo $passenger_count; ?></strong> passenger(s): <strong>₹<?php echo number_format($fare_per_person, 2); ?> each</strong>
                    </p>
                </div>
                <div>
                    <span class="badge" style="background: <?php echo $collection_pct >= 100 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(245, 158, 11, 0.15)'; ?>; color: <?php echo $collection_pct >= 100 ? '#10b981' : '#d97706'; ?>; font-size: 13px; font-weight: 700; padding: 6px 14px; border-radius: var(--radius-full);">
                        ● <?php echo $paid_passengers_count; ?> of <?php echo $passenger_count; ?> Settled (₹<?php echo number_format($total_collected, 2); ?> Collected)
                    </span>
                </div>
            </div>

            <!-- Settlement Progress Bar -->
            <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; color: var(--slate-600); margin-top: 10px;">
                <span>Trip Settlement Progress</span>
                <span><?php echo $collection_pct; ?>% Settled</span>
            </div>
            <div class="payment-progress-container">
                <div class="payment-progress-bar" style="width: <?php echo $collection_pct; ?>%;"></div>
            </div>

            <?php if ($is_member): ?>
                <?php if ($my_payment && $my_payment["status"] === "completed"): ?>
                    <!-- State 1: Paid & Settled -->
                    <div style="background: var(--success-bg); border: 1.5px solid rgba(16, 185, 129, 0.3); border-radius: var(--radius-md); padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-top: 14px;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--success); color: white; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                                ✓
                            </div>
                            <div>
                                <h3 style="font-size: 17px; font-weight: 800; color: #065f46;">
                                    Your Share is Paid & Settled!
                                </h3>
                                <p style="font-size: 13px; color: #047857; margin-top: 2px;">
                                    Amount Paid: <strong>₹<?php echo number_format($my_payment["amount"], 2); ?></strong> via <strong><?php echo strtoupper($my_payment["payment_method"]); ?></strong> &bull; Ref: <code><?php echo htmlspecialchars($my_payment["transaction_id"]); ?></code>
                                </p>
                                <span style="font-size: 12px; color: #065f46; opacity: 0.85;">
                                    Settled on <?php echo date("d M Y, h:i A", strtotime($my_payment["paid_at"])); ?>
                                </span>
                            </div>
                        </div>
                        <button type="button" onclick="openReceiptModal();" class="btn-primary" style="background: #059669; padding: 10px 20px; font-size: 13px;">
                            🧾 View Digital Receipt
                        </button>
                    </div>

                <?php elseif ($my_payment && $my_payment["payment_method"] === "cash"): ?>
                    <!-- State 2: Cash on Arrival Committed -->
                    <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: var(--radius-md); padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-top: 14px;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: #f59e0b; color: white; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                                💵
                            </div>
                            <div>
                                <h3 style="font-size: 17px; font-weight: 800; color: #92400e;">
                                    Cash on Arrival Selected
                                </h3>
                                <p style="font-size: 13px; color: #b45309; margin-top: 2px;">
                                    Please hand over <strong>₹<?php echo number_format($fare_per_person, 2); ?> in cash</strong> to driver <strong><?php echo htmlspecialchars($driver["name"] ?? "Partner"); ?></strong> upon boarding.
                                </p>
                                <span style="font-size: 12px; color: #92400e; opacity: 0.85;">
                                    Ref: <code><?php echo htmlspecialchars($my_payment["transaction_id"]); ?></code> &bull; Pending Driver Verification
                                </span>
                            </div>
                        </div>
                        <div>
                            <button type="button" onclick="togglePaymentTabs();" class="btn-secondary" style="font-size: 12px; padding: 8px 14px;">
                                Pay via UPI / Card Instead ➔
                            </button>
                        </div>
                    </div>

                <?php endif; ?>

                <?php if (!$my_payment || ($my_payment && $my_payment["status"] !== "completed")): ?>
                    <!-- State 3: Pay Now Tabbed Options -->
                    <div id="paymentOptionsBox" style="<?php echo ($my_payment && $my_payment['payment_method'] === 'cash') ? 'display: none; margin-top: 20px;' : 'margin-top: 14px;'; ?>">
                        <h3 style="font-size: 16px; font-weight: 800; color: var(--slate-900); margin-bottom: 12px;">
                            Choose Your Payment Method (Split Share: ₹<?php echo number_format($fare_per_person, 2); ?>)
                        </h3>

                        <!-- Tabs -->
                        <div class="payment-tabs">
                            <button type="button" class="payment-tab-btn active" onclick="switchPaymentTab('upi', this);">
                                📱 UPI / Dynamic QR Code
                            </button>
                            <button type="button" class="payment-tab-btn" onclick="switchPaymentTab('card', this);">
                                💳 Debit / Credit Card
                            </button>
                            <button type="button" class="payment-tab-btn" onclick="switchPaymentTab('cash', this);">
                                💵 Cash to Driver
                            </button>
                        </div>

                        <!-- TAB 1: UPI -->
                        <div id="tab-upi" class="payment-tab-content active">
                            <div class="qr-box">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=upi%3A%2F%2Fpay%3Fpa%3Dsharecab%40upi%26pn%3DShareCab%26am%3D<?php echo urlencode($fare_per_person); ?>%26cu%3DINR" alt="UPI QR Code">
                                <div>
                                    <h4 style="font-size: 15px; font-weight: 800; color: var(--slate-900); margin-bottom: 4px;">
                                        Scan & Pay with Any UPI App
                                    </h4>
                                    <p style="font-size: 13px; color: var(--slate-600); margin-bottom: 10px;">
                                        Scan using <strong>Google Pay</strong>, <strong>PhonePe</strong>, <strong>Paytm</strong>, or <strong>BHIM</strong> to pay your share of <strong>₹<?php echo number_format($fare_per_person, 2); ?></strong>.
                                    </p>
                                    <span class="route-badge" style="font-size: 12px; background: #e0f2fe; color: #0284c7;">
                                        UPI ID: sharecab@upi
                                    </span>
                                </div>
                            </div>

                            <form method="POST" action="process_payment.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                                <input type="hidden" name="payment_method" value="upi">
                                <input type="text" name="upi_id" placeholder="Enter your UPI ID (e.g. user@okaxis)" required style="flex: 1; min-width: 220px; margin-bottom: 0;">
                                <button type="submit" class="btn-primary" style="padding: 12px 24px; font-size: 14px; white-space: nowrap;">
                                    ✓ Verify & Pay ₹<?php echo number_format($fare_per_person, 2); ?>
                                </button>
                            </form>
                        </div>

                        <!-- TAB 2: Card -->
                        <div id="tab-card" class="payment-tab-content">
                            <form method="POST" action="process_payment.php">
                                <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                                <input type="hidden" name="payment_method" value="card">

                                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                                    <div>
                                        <label style="font-size: 12px; font-weight: 700; color: var(--slate-600); display: block; margin-bottom: 4px;">Card Number</label>
                                        <input type="text" name="card_number" placeholder="4532 •••• •••• 8912" maxlength="19" required style="margin-bottom: 0;">
                                    </div>
                                    <div>
                                        <label style="font-size: 12px; font-weight: 700; color: var(--slate-600); display: block; margin-bottom: 4px;">Expiry Date</label>
                                        <input type="text" name="card_expiry" placeholder="MM/YY" maxlength="5" required style="margin-bottom: 0;">
                                    </div>
                                    <div>
                                        <label style="font-size: 12px; font-weight: 700; color: var(--slate-600); display: block; margin-bottom: 4px;">CVV</label>
                                        <input type="password" name="card_cvv" placeholder="•••" maxlength="4" required style="margin-bottom: 0;">
                                    </div>
                                </div>
                                <button type="submit" class="btn-primary" style="padding: 12px 24px; font-size: 14px;">
                                    🔒 Pay ₹<?php echo number_format($fare_per_person, 2); ?> via Card
                                </button>
                            </form>
                        </div>

                        <!-- TAB 3: Cash -->
                        <div id="tab-cash" class="payment-tab-content">
                            <div style="background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 18px; margin-bottom: 14px;">
                                <h4 style="font-size: 15px; font-weight: 800; color: var(--slate-900); margin-bottom: 6px;">
                                    Pay Cash Directly to Cab Driver
                                </h4>
                                <p style="font-size: 13px; color: var(--slate-600);">
                                    Commit to paying your exact share of <strong>₹<?php echo number_format($fare_per_person, 2); ?></strong> directly in cash to the driver upon boarding the vehicle. The driver will verify and mark your payment as collected.
                                </p>
                            </div>
                            <form method="POST" action="process_payment.php">
                                <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                                <input type="hidden" name="payment_method" value="cash">
                                <button type="submit" class="btn-primary" style="background: #f59e0b; padding: 12px 24px; font-size: 14px;">
                                    💵 Confirm Cash on Arrival
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <p style="color: var(--slate-500); font-size: 14px; margin-top: 10px;">
                    💡 Join this shared ride to view individual payment settlement and choose your preferred payment option.
                </p>
            <?php endif; ?>

        </div>

        <!-- Passengers & Driver Grid -->
        <div class="info-section-grid">

            <!-- Passenger List -->
            <div class="card" style="margin-bottom: 0;">
                <h2 style="font-size: 19px; font-weight: 800; margin-bottom: 18px;">
                    👥 Confirmed Passengers (<?php echo $passenger_count; ?>)
                </h2>

                <?php if ($passenger_count > 0): ?>
                    <div class="passenger-list">
                        <?php while ($passenger = $passenger_result->fetch_assoc()): 
                            $p_pay = isset($payments_by_user[$passenger["id"]]) ? $payments_by_user[$passenger["id"]] : null;
                        ?>
                            <div class="passenger-item" style="flex-wrap: wrap; gap: 10px;">
                                <div class="passenger-profile">
                                    <div class="avatar-circle">
                                        <?php echo strtoupper(substr($passenger["name"], 0, 1)); ?>
                                    </div>
                                    <div class="passenger-info">
                                        <h4>
                                            <?php echo htmlspecialchars($passenger["name"]); ?>
                                            <?php if ($passenger["id"] == $ride["created_by"]): ?>
                                                <span style="font-size: 11px; background: var(--primary-light); color: var(--primary-hover); padding: 2px 6px; border-radius: 4px; margin-left: 4px;">Creator</span>
                                            <?php endif; ?>
                                        </h4>
                                        <p>📧 <?php echo htmlspecialchars($passenger["email"]); ?></p>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                                    <div style="font-size: 13px; font-weight: 600; color: var(--slate-600);">
                                        <a href="tel:<?php echo htmlspecialchars($passenger['phone']); ?>" style="color: var(--primary-hover); text-decoration: none;">
                                            📞 <?php echo htmlspecialchars($passenger["phone"]); ?>
                                        </a>
                                    </div>

                                    <?php if ($p_pay && $p_pay["status"] === "completed"): ?>
                                        <span style="font-size: 11px; font-weight: 700; background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;">
                                            ✓ Paid (₹<?php echo number_format($p_pay["amount"], 2); ?> · <?php echo strtoupper($p_pay["payment_method"]); ?>)
                                        </span>
                                    <?php elseif ($p_pay && $p_pay["payment_method"] === "cash"): ?>
                                        <span style="font-size: 11px; font-weight: 700; background: #fef3c7; color: #b45309; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;">
                                            ⏳ Cash on Arrival (₹<?php echo number_format($p_pay["amount"], 2); ?>)
                                        </span>
                                        <?php if ($is_assigned_driver): ?>
                                            <form method="POST" action="../driver/confirm_cash.php" style="display: inline; margin: 0;">
                                                <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                                                <input type="hidden" name="passenger_id" value="<?php echo $passenger['id']; ?>">
                                                <input type="hidden" name="return_to_details" value="1">
                                                <button type="submit" style="background: #10b981; color: white; border: none; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer;" onclick="return confirm('Confirm that cash of ₹<?php echo number_format($p_pay['amount'], 2); ?> was received from <?php echo htmlspecialchars(addslashes($passenger['name'])); ?>?');">
                                                    ✓ Confirm Cash
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="font-size: 11px; font-weight: 700; background: #f1f5f9; color: #64748b; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px;">
                                            ● Unpaid (₹<?php echo number_format($fare_per_person, 2); ?>)
                                        </span>
                                        <?php if ($is_assigned_driver): ?>
                                            <form method="POST" action="../driver/confirm_cash.php" style="display: inline; margin: 0;">
                                                <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                                                <input type="hidden" name="passenger_id" value="<?php echo $passenger['id']; ?>">
                                                <input type="hidden" name="return_to_details" value="1">
                                                <button type="submit" style="background: #059669; color: white; border: none; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer;" onclick="return confirm('Mark cash of ₹<?php echo number_format($fare_per_person, 2); ?> collected from <?php echo htmlspecialchars(addslashes($passenger['name'])); ?>?');">
                                                    💵 Collect Cash
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--slate-500); font-size: 14px;">No passengers have joined this ride yet.</p>
                <?php endif; ?>
            </div>

            <!-- Driver Information -->
            <div class="card" style="margin-bottom: 0;">
                <h2 style="font-size: 19px; font-weight: 800; margin-bottom: 18px;">
                    🚗 Driver & Vehicle
                </h2>

                <?php if ($driver): ?>
                    <div class="driver-card">
                        <div class="driver-badge-top">
                            <div class="driver-avatar">
                                👨‍✈️
                            </div>
                            <div>
                                <h4><?php echo htmlspecialchars($driver["name"]); ?></h4>
                                <p style="font-size: 13px; color: var(--slate-500);">Assigned Cab Driver</p>
                            </div>
                        </div>

                        <div class="driver-meta-grid">
                            <div class="driver-meta-item">
                                <span>Contact Phone</span>
                                <strong>
                                    <a href="tel:<?php echo htmlspecialchars($driver['phone']); ?>" style="color: var(--primary-hover); text-decoration: none;">
                                        📞 <?php echo htmlspecialchars($driver["phone"]); ?>
                                    </a>
                                </strong>
                            </div>
                            <div class="driver-meta-item">
                                <span>Vehicle Model</span>
                                <strong>🚗 <?php echo htmlspecialchars($driver["vehicle"]); ?></strong>
                            </div>
                            <div class="driver-meta-item" style="grid-column: span 2;">
                                <span>Vehicle Number</span>
                                <strong style="color: var(--primary-hover);"><?php echo htmlspecialchars($driver["vehicle_number"]); ?></strong>
                            </div>
                        </div>

                        <?php if ($is_creator): ?>
                            <div class="driver-assign-form">
                                <p style="font-size: 12px; font-weight: 700; color: var(--slate-600); margin-bottom: 8px;">CHANGE ASSIGNED DRIVER:</p>
                                <form method="POST" action="assign_driver.php" style="display: flex; gap: 8px;">
                                    <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                                    <select name="driver_id" required style="margin-bottom: 0; flex: 1;">
                                        <?php foreach ($all_drivers as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php echo ($d['id'] == $driver['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['vehicle_number']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-primary" style="padding: 9px 16px; font-size: 13px;">Update</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="background: var(--slate-50); border: 1.5px dashed var(--slate-300); border-radius: var(--radius-md); padding: 24px; text-align: center;">
                        <p style="font-size: 32px; margin-bottom: 10px;">🚕</p>
                        <p style="font-weight: 700; color: var(--slate-800); margin-bottom: 6px;">No Driver Assigned Yet</p>
                        <p style="font-size: 13px; color: var(--slate-500); margin-bottom: 18px;">
                            A cab driver and vehicle have not been linked to this ride.
                        </p>

                        <?php if ($is_creator && count($all_drivers) > 0): ?>
                            <form method="POST" action="assign_driver.php" style="text-align: left;">
                                <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                                <label style="font-size: 12px; font-weight: 700; display: block; margin-bottom: 6px;">SELECT DRIVER TO ASSIGN:</label>
                                <div style="display: flex; gap: 8px;">
                                    <select name="driver_id" required style="flex: 1; margin-bottom: 0;">
                                        <option value="">-- Choose Registered Driver --</option>
                                        <?php foreach ($all_drivers as $d): ?>
                                            <option value="<?php echo $d['id']; ?>">
                                                <?php echo htmlspecialchars($d['name']); ?> - <?php echo htmlspecialchars($d['vehicle']); ?> (<?php echo htmlspecialchars($d['vehicle_number']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-primary" style="padding: 10px 18px; font-size: 13px; white-space: nowrap;">
                                        Assign Driver
                                    </button>
                                </div>
                            </form>
                        <?php elseif ($is_creator): ?>
                            <p style="font-size: 13px; color: var(--slate-500);">
                                <a href="../admin/add_driver.php" style="color: var(--primary-hover); font-weight: 700;">+ Register a Driver</a> first to assign to this ride.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Action Bar -->
        <div style="display: flex; gap: 14px; justify-content: center; align-items: center; margin-top: 30px; flex-wrap: wrap;">
            <?php if ($is_guest_preview): ?>
                <?php if ($available_seats > 0): ?>
                    <a href="join_ride.php?id=<?php echo $ride_id; ?>" class="btn-primary" style="font-size: 15px; padding: 14px 32px; background: var(--primary);">
                        🚀 Join This Shared Ride (₹<?php echo number_format($fare_per_person, 2); ?>)
                    </a>
                <?php else: ?>
                    <button class="btn-secondary" style="font-size: 15px; padding: 14px 28px; cursor: not-allowed;" disabled>
                        Ride is Full
                    </button>
                <?php endif; ?>
                <a href="search_ride.php" class="btn-secondary">
                    🔍 Back to Search
                </a>
            <?php else: ?>
                <a href="../chat/chat.php?ride_id=<?php echo $ride_id; ?>" class="btn-primary" style="font-size: 15px; padding: 14px 28px;">
                    💬 Open Ride Group Chat
                </a>
            <?php endif; ?>

            <?php if ($is_creator): ?>
                <?php if ($ride["status"] !== "completed"): ?>
                    <form method="POST" action="complete_ride.php" onsubmit="return confirm('🏁 Mark this ride as completed?');" style="display: inline;">
                        <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                        <button type="submit" class="btn-primary" style="font-size: 15px; padding: 14px 22px; background: var(--success);">
                            🏁 Mark as Completed
                        </button>
                    </form>
                <?php endif; ?>
                <form method="POST" action="delete_ride.php" onsubmit="return confirm('⚠️ Are you sure you want to delete this ride? This action cannot be undone and will cancel all passenger bookings and chat history.');" style="display: inline;">
                    <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                    <button type="submit" class="btn-danger" style="font-size: 15px; padding: 14px 24px;">
                        🗑️ Delete Entire Ride
                    </button>
                </form>
            <?php elseif ($is_member): ?>
                <form method="POST" action="leave_ride.php" onsubmit="return confirm('🚪 Are you sure you want to cancel your seat in this ride? Your seat will be released and made available to other passengers.');" style="display: inline;">
                    <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                    <button type="submit" class="btn-warning" style="font-size: 15px; padding: 14px 24px;">
                        🚪 Cancel My Seat / Leave Ride
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($is_assigned_driver && $ride["status"] !== "completed"): ?>
                <form method="POST" action="complete_ride.php" onsubmit="return confirm('🏁 Mark this ride as completed?');" style="display: inline;">
                    <input type="hidden" name="ride_id" value="<?php echo $ride_id; ?>">
                    <button type="submit" class="btn-primary" style="font-size: 15px; padding: 14px 22px; background: var(--success);">
                        🏁 Mark as Completed
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($is_driver): ?>
                <a href="../driver/dashboard.php" class="btn-secondary">
                    ← Driver Dashboard
                </a>
            <?php else: ?>
                <a href="my_rides.php" class="btn-secondary">
                    📋 Back to My Rides
                </a>
                <a href="../dashboard.php" class="btn-secondary">
                    🏠 Dashboard
                </a>
            <?php endif; ?>
        </div>

    </div>

    <!-- Digital Receipt Modal -->
    <?php if ($my_payment && $my_payment["status"] === "completed"): ?>
    <div id="receiptModal" class="receipt-modal-backdrop">
        <div class="receipt-card" id="receiptPrintArea">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px dashed var(--slate-200); padding-bottom: 16px; margin-bottom: 18px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <img src="../images/sharecab_logo.png" alt="ShareCab Logo" style="height: 28px; width: auto; object-fit: contain;" onerror="this.outerHTML='🚕';">
                        <span style="font-size: 20px; font-weight: 800; color: var(--slate-900);">Share<span style="color: var(--primary);">Cab</span></span>
                    </div>
                    <p style="font-size: 12px; color: var(--slate-500); margin-top: 4px;">Smart Shared Mobility &bull; Trip Invoice</p>
                </div>
                <div style="text-align: right;">
                    <span style="display: inline-block; background: #dcfce7; color: #15803d; font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 999px;">
                        ✓ PAID & SETTLED
                    </span>
                    <p style="font-size: 11px; color: var(--slate-400); margin-top: 4px;">Receipt Ref: <code><?php echo htmlspecialchars($my_payment['transaction_id']); ?></code></p>
                </div>
            </div>

            <div style="background: var(--slate-50); border-radius: var(--radius-md); padding: 14px; margin-bottom: 18px; font-size: 13px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <span style="color: var(--slate-500); display: block; font-size: 11px; text-transform: uppercase; font-weight: 700;">Passenger</span>
                        <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Passenger'); ?></strong>
                    </div>
                    <div>
                        <span style="color: var(--slate-500); display: block; font-size: 11px; text-transform: uppercase; font-weight: 700;">Ride ID</span>
                        <strong>#<?php echo $ride_id; ?> (<?php echo htmlspecialchars($ride['departure_time']); ?>)</strong>
                    </div>
                    <div>
                        <span style="color: var(--slate-500); display: block; font-size: 11px; text-transform: uppercase; font-weight: 700;">Pickup</span>
                        <span><?php echo htmlspecialchars($ride['source']); ?></span>
                    </div>
                    <div>
                        <span style="color: var(--slate-500); display: block; font-size: 11px; text-transform: uppercase; font-weight: 700;">Destination</span>
                        <span><?php echo htmlspecialchars($ride['destination']); ?></span>
                    </div>
                    <?php if ($driver): ?>
                    <div>
                        <span style="color: var(--slate-500); display: block; font-size: 11px; text-transform: uppercase; font-weight: 700;">Driver</span>
                        <span><?php echo htmlspecialchars($driver['name']); ?> (<?php echo htmlspecialchars($driver['vehicle_number']); ?>)</span>
                    </div>
                    <?php endif; ?>
                    <div>
                        <span style="color: var(--slate-500); display: block; font-size: 11px; text-transform: uppercase; font-weight: 700;">Settlement Date</span>
                        <span><?php echo date("d M Y, h:i A", strtotime($my_payment['paid_at'])); ?></span>
                    </div>
                </div>
            </div>

            <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 18px;">
                <thead>
                    <tr style="border-bottom: 1.5px solid var(--slate-200); text-align: left; color: var(--slate-500);">
                        <th style="padding: 8px 0; font-weight: 700;">Item Description</th>
                        <th style="padding: 8px 0; text-align: right; font-weight: 700;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid var(--slate-100);">
                        <td style="padding: 10px 0;">Total Shared Cab Trip Fare</td>
                        <td style="padding: 10px 0; text-align: right; font-weight: 600;">₹<?php echo number_format($ride['fare'], 2); ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--slate-100);">
                        <td style="padding: 10px 0;">Trip Passenger Split</td>
                        <td style="padding: 10px 0; text-align: right; font-weight: 600;">÷ <?php echo $passenger_count; ?> Riders</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--slate-100);">
                        <td style="padding: 10px 0;">Payment Method</td>
                        <td style="padding: 10px 0; text-align: right; font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($my_payment['payment_method']); ?></td>
                    </tr>
                    <tr style="font-size: 15px; font-weight: 800; color: var(--slate-900);">
                        <td style="padding: 12px 0;">Individual Share Settled</td>
                        <td style="padding: 12px 0; text-align: right; color: #059669;">₹<?php echo number_format($my_payment['amount'], 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <div style="font-size: 11px; color: var(--slate-400); text-align: center; margin-bottom: 20px;">
                This is a computer-generated tax invoice for shared passenger mobility under the ShareCab Platform.
            </div>

            <div class="no-print" style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="window.print();" class="btn-primary" style="padding: 8px 18px; font-size: 13px;">
                    🖨️ Print / Save PDF
                </button>
                <button type="button" onclick="closeReceiptModal();" class="btn-secondary" style="padding: 8px 18px; font-size: 13px;">
                    Close
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <footer>
        <div class="footer-container">
            <h3>🚕 ShareCab</h3>
            <p>Cab sharing made simple & affordable.</p>
            <p>Web Programming Lab Project</p>
        </div>
    </footer>

    <!-- Location Component & Leaflet Routing -->
    <script src="../js/location-autocomplete.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const sourceText = <?php echo json_encode($ride["source"]); ?>;
            const destText = <?php echo json_encode($ride["destination"]); ?>;
            const distanceEl = document.getElementById("routeDistanceInfo");

            // Initialize map with default center (India / Kochi default)
            const map = L.map("rideMap").setView([9.9816, 76.2999], 12);

            // OpenStreetMap tile layer
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 18
            }).addTo(map);

            // Geocode function using ShareCabLocations + Photon / Nominatim
            async function getCoordinates(query) {
                if (window.ShareCabLocations) {
                    const found = await window.ShareCabLocations.verifyRealLocation(query);
                    if (found) {
                        return [parseFloat(found.lat), parseFloat(found.lon)];
                    }
                }

                // Fallback to OpenStreetMap Nominatim Geocoder
                try {
                    const response = await fetch(
                        `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`
                    );
                    const data = await response.json();
                    if (data && data.length > 0) {
                        return [parseFloat(data[0].lat), parseFloat(data[0].lon)];
                    }
                } catch (e) {
                    console.warn("Geocoding network fetch failed, using fallback coordinates:", e);
                }

                // Default hash offset if entirely unknown
                let hash = 0;
                for (let i = 0; i < query.length; i++) hash = query.charCodeAt(i) + ((hash << 5) - hash);
                const offsetLat = (hash % 50) / 1000;
                const offsetLon = ((hash >> 2) % 50) / 1000;
                return [9.9816 + offsetLat, 76.2999 + offsetLon];
            }

            // Haversine distance formula (in km)
            function calculateDistance(lat1, lon1, lat2, lon2) {
                const R = 6371; // Earth radius in km
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                          Math.sin(dLon / 2) * Math.sin(dLon / 2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                return (R * c);
            }

            async function renderRoute() {
                const startCoord = await getCoordinates(sourceText);
                let endCoord = await getCoordinates(destText);

                // Ensure start and end aren't identical
                if (Math.abs(startCoord[0] - endCoord[0]) < 0.001 && Math.abs(startCoord[1] - endCoord[1]) < 0.001) {
                    endCoord = [startCoord[0] + 0.045, startCoord[1] + 0.035];
                }

                // Custom marker icons
                const startIcon = L.divIcon({
                    className: 'map-pin-custom',
                    html: '🚕',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });

                const endIcon = L.divIcon({
                    className: 'map-pin-custom',
                    html: '🏁',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });

                // Place markers
                const startMarker = L.marker(startCoord, { icon: startIcon }).addTo(map)
                    .bindPopup(`<b>Start Point</b><br>${sourceText}`).openPopup();

                const endMarker = L.marker(endCoord, { icon: endIcon }).addTo(map)
                    .bindPopup(`<b>Destination</b><br>${destText}`);

                // Generate a midpoint curve for realistic route visualization
                const midLat = (startCoord[0] + endCoord[0]) / 2 + 0.008;
                const midLon = (startCoord[1] + endCoord[1]) / 2 - 0.008;

                const routePoints = [startCoord, [midLat, midLon], endCoord];

                // Route polyline with primary theme color
                const polyline = L.polyline(routePoints, {
                    color: '#f59e0b',
                    weight: 5,
                    opacity: 0.9,
                    dashArray: '8, 8',
                    lineJoin: 'round'
                }).addTo(map);

                // Fit map view to route
                map.fitBounds(polyline.getBounds(), { padding: [40, 40] });

                // Calculate distance and driving duration
                const rawDistance = calculateDistance(startCoord[0], startCoord[1], endCoord[0], endCoord[1]);
                const routeDistance = (rawDistance * 1.25).toFixed(1); // 1.25 factor for road detour
                const estMinutes = Math.round(routeDistance * 2.5);

                distanceEl.innerHTML = `📏 Est. Road Distance: <strong>${routeDistance} km</strong> &bull; ⏱️ Est. Drive Time: <strong>~${estMinutes} mins</strong>`;
            }

            renderRoute();
        });

        // Payment tab switching logic
        function switchPaymentTab(tab, btn) {
            document.querySelectorAll('.payment-tab-btn').forEach(function(b) {
                b.classList.remove('active');
            });
            document.querySelectorAll('.payment-tab-content').forEach(function(c) {
                c.classList.remove('active');
            });

            btn.classList.add('active');
            const target = document.getElementById('tab-' + tab);
            if (target) {
                target.classList.add('active');
            }
        }

        // Toggle payment options box when cash is selected
        function togglePaymentTabs() {
            const box = document.getElementById('paymentOptionsBox');
            if (box) {
                const isHidden = (box.style.display === 'none' || box.style.display === '');
                box.style.display = isHidden ? 'block' : 'none';
                if (isHidden) {
                    box.scrollIntoView({ behavior: 'smooth' });
                }
            }
        }

        // Digital Receipt Modal functions
        function openReceiptModal() {
            const modal = document.getElementById('receiptModal');
            if (modal) {
                modal.classList.add('active');
            }
        }

        function closeReceiptModal() {
            const modal = document.getElementById('receiptModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }

        window.addEventListener('click', function(e) {
            const modal = document.getElementById('receiptModal');
            if (modal && e.target === modal) {
                closeReceiptModal();
            }
        });
    </script>
</body>
</html>
<?php
$passenger_stmt->close();
?>