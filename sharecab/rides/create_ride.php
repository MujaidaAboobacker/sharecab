<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$message_type = "";
$new_ride_id = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $source = trim($_POST["source"]);
    $destination = trim($_POST["destination"]);
    $ride_date = $_POST["ride_date"];
    $ride_time = $_POST["ride_time"];
    $max_seats = intval($_POST["max_seats"]);
    $fare = floatval($_POST["fare"]);

    $today = date("Y-m-d");
    $now_time = date("H:i");

    if (
        empty($source) ||
        empty($destination) ||
        empty($ride_date) ||
        empty($ride_time) ||
        $max_seats <= 0 ||
        $fare <= 0
    ) {
        $message = "Please fill in all fields with valid information.";
        $message_type = "error";
    } elseif (strcasecmp($source, $destination) === 0) {
        $message = "Starting location and destination cannot be the same place.";
        $message_type = "error";
    } elseif ($ride_date < $today) {
        $message = "Travel date cannot be in the past.";
        $message_type = "error";
    } elseif ($ride_date === $today && $ride_time < $now_time) {
        $message = "Departure time has already passed for today. Please choose a future time.";
        $message_type = "error";
    } elseif ($max_seats < 2) {
        $message = "A shared ride requires capacity for at least 2 passengers.";
        $message_type = "error";
    } else {
        $created_by = $_SESSION["user_id"];

        $stmt = $conn->prepare(
            "INSERT INTO rides
            (created_by, source, destination, ride_date, ride_time, max_seats, fare)
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "issssid",
            $created_by,
            $source,
            $destination,
            $ride_date,
            $ride_time,
            $max_seats,
            $fare
        );

        if ($stmt->execute()) {
            $new_ride_id = $stmt->insert_id;

            // Automatically register creator as first member of this ride
            $member_stmt = $conn->prepare(
                "INSERT INTO ride_members (ride_id, user_id) VALUES (?, ?)"
            );
            $member_stmt->bind_param("ii", $new_ride_id, $created_by);
            $member_stmt->execute();
            $member_stmt->close();

            $message = "Ride created successfully! You can now view and manage your ride.";
            $message_type = "success";
        } else {
            $message = "Failed to create ride. Please try again.";
            $message_type = "error";
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
    <title>Create a Ride - ShareCab</title>
    <link rel="stylesheet" href="../css/style.css">
    
    <!-- Leaflet.js Interactive Map CDN -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .map-pin-custom {
            font-size: 24px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../dashboard.php" style="display: flex; align-items: center;">
                    <img src="../img/sharecab_car.jpg" alt="ShareCab Car" class="brand-logo-img">
                    ShareCab
                </a>
            </div>
            <div class="nav-links">
                <a href="../dashboard.php">Dashboard</a>
                <a href="my_rides.php">My Rides</a>
                <a href="../auth/logout.php" class="nav-login">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container" style="max-width: 900px;">

        <div class="dashboard-header">
            <h1>🚕 Create a New Shared Ride</h1>
            <p>Publish your journey route, set passenger capacity, split your fare, and travel together.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                <?php echo htmlspecialchars($message); ?>
                <?php if ($new_ride_id): ?>
                    &nbsp;&bull;&nbsp;<a href="ride_details.php?id=<?php echo $new_ride_id; ?>" style="font-weight: 800; text-decoration: underline;">Open Ride Details ➔</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 36px;">
            <form method="POST" action="" id="rideForm">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Source -->
                    <div class="form-group">
                        <label for="source">📍 Starting Point (Origin)</label>
                        <input
                            type="text"
                            id="source"
                            name="source"
                            placeholder="Type to search real locations (e.g. Railway Station, Airport)"
                            data-autocomplete="location"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <!-- Destination -->
                    <div class="form-group">
                        <label for="destination">🏁 Destination (Drop-off)</label>
                        <input
                            type="text"
                            id="destination"
                            name="destination"
                            placeholder="Type to search real locations (e.g. Marine Drive, Infopark)"
                            data-autocomplete="location"
                            autocomplete="off"
                            required
                        >
                    </div>
                </div>

                <!-- Interactive Route Preview Map -->
                <div class="map-card" style="padding: 16px; margin: 10px 0 24px 0;">
                    <div class="map-header">
                        <h4 style="font-size: 14px; font-weight: 700; color: var(--slate-700);">🗺️ Route Map Preview</h4>
                        <span id="previewDistance" style="font-size: 13px; font-weight: 700; color: var(--primary-hover);">Type locations to preview route</span>
                    </div>
                    <div id="createMap" style="height: 260px;"></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Travel Date -->
                    <div class="form-group">
                        <label for="ride_date">📅 Travel Date</label>
                        <input
                            type="date"
                            id="ride_date"
                            name="ride_date"
                            min="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                    </div>

                    <!-- Travel Time -->
                    <div class="form-group">
                        <label for="ride_time">🕐 Departure Time</label>
                        <input
                            type="time"
                            id="ride_time"
                            name="ride_time"
                            required
                        >
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Available Seats -->
                    <div class="form-group">
                        <label for="max_seats">💺 Passenger Seats Capacity (Min 2)</label>
                        <input
                            type="number"
                            id="max_seats"
                            name="max_seats"
                            min="2"
                            max="10"
                            placeholder="e.g. 3"
                            value="3"
                            required
                        >
                    </div>

                    <!-- Total Fare -->
                    <div class="form-group">
                        <label for="fare">💰 Total Estimated Fare (₹)</label>
                        <input
                            type="number"
                            id="fare"
                            name="fare"
                            min="1"
                            step="0.01"
                            placeholder="e.g. 300"
                            required
                        >
                    </div>
                </div>

                <!-- Dynamic Real-time Fare Calculator -->
                <div id="farePerPerson" class="fare-calculator">
                    💡 Enter fare to calculate estimated split per person
                </div>

                <button type="submit" class="form-button" style="margin-top: 24px; padding: 16px; font-size: 16px;">
                    🚕 Publish Shared Ride
                </button>

            </form>
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
    <script src="../js/location-autocomplete.js"></script>

    <!-- Route preview map and real location verification script -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const sourceInput = document.getElementById("source");
            const destInput = document.getElementById("destination");
            const previewDist = document.getElementById("previewDistance");
            const rideForm = document.getElementById("rideForm");

            const map = L.map("createMap").setView([9.9816, 76.2999], 12);

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);

            let startMarker = null;
            let endMarker = null;
            let polyline = null;

            // Setup autocomplete on both inputs with instant map update on selection
            if (window.ShareCabLocations) {
                window.ShareCabLocations.setupAutocomplete(sourceInput, function (item) {
                    updateMapPreview();
                });
                window.ShareCabLocations.setupAutocomplete(destInput, function (item) {
                    updateMapPreview();
                });
            }

            async function resolveCoords(inputEl) {
                const val = inputEl.value.trim();
                if (!val) return null;

                // Check dataset coordinates from autocomplete selection
                if (inputEl.dataset.lat && inputEl.dataset.lon && inputEl.dataset.verified === "true") {
                    return [parseFloat(inputEl.dataset.lat), parseFloat(inputEl.dataset.lon)];
                }

                // Check via ShareCabLocations verifier
                if (window.ShareCabLocations) {
                    const place = await window.ShareCabLocations.verifyRealLocation(val);
                    if (place) {
                        inputEl.dataset.lat = place.lat;
                        inputEl.dataset.lon = place.lon;
                        inputEl.dataset.verified = "true";
                        return [parseFloat(place.lat), parseFloat(place.lon)];
                    }
                }

                return null;
            }

            async function updateMapPreview() {
                const sVal = sourceInput.value.trim();
                const dVal = destInput.value.trim();
                if (!sVal || !dVal) return;

                const c1 = await resolveCoords(sourceInput);
                const c2 = await resolveCoords(destInput);

                if (!c1 || !c2) {
                    previewDist.innerHTML = `<span style="color: var(--danger);">⚠️ Please select verified real locations from suggestions</span>`;
                    return;
                }

                if (startMarker) map.removeLayer(startMarker);
                if (endMarker) map.removeLayer(endMarker);
                if (polyline) map.removeLayer(polyline);

                const icon1 = L.divIcon({ className: 'map-pin-custom', html: '🚕', iconSize: [28, 28] });
                const icon2 = L.divIcon({ className: 'map-pin-custom', html: '🏁', iconSize: [28, 28] });

                startMarker = L.marker(c1, { icon: icon1 }).addTo(map).bindPopup(sVal);
                endMarker = L.marker(c2, { icon: icon2 }).addTo(map).bindPopup(dVal);

                const mid = [(c1[0] + c2[0]) / 2 + 0.005, (c1[1] + c2[1]) / 2 - 0.005];
                polyline = L.polyline([c1, mid, c2], { color: '#f59e0b', weight: 4, dashArray: '6, 6' }).addTo(map);

                map.fitBounds(polyline.getBounds(), { padding: [30, 30] });

                // Distance calculation
                const R = 6371;
                const dLat = (c2[0] - c1[0]) * Math.PI / 180;
                const dLon = (c2[1] - c1[1]) * Math.PI / 180;
                const a = Math.sin(dLat/2)**2 + Math.cos(c1[0]*Math.PI/180) * Math.cos(c2[0]*Math.PI/180) * Math.sin(dLon/2)**2;
                const dist = (R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)) * 1.25).toFixed(1);
                const estMin = Math.round(dist * 2.5);
                previewDist.innerHTML = `📍 Route Ready: ~<strong>${dist} km</strong> (~${estMin} mins)`;
            }

            sourceInput.addEventListener("change", updateMapPreview);
            destInput.addEventListener("change", updateMapPreview);

            // Strict verification on submit: ONLY REAL LOCATIONS ALLOWED
            rideForm.addEventListener("submit", async function (event) {
                const sVal = sourceInput.value.trim();
                const dVal = destInput.value.trim();

                const c1 = await resolveCoords(sourceInput);
                if (!c1) {
                    event.preventDefault();
                    alert(`❌ Starting Point "${sVal}" is not a recognized real location.\n\nPlease choose a valid place from the suggestions or enter a known landmark, airport, railway station, or city.`);
                    sourceInput.focus();
                    return;
                }

                const c2 = await resolveCoords(destInput);
                if (!c2) {
                    event.preventDefault();
                    alert(`❌ Destination "${dVal}" is not a recognized real location.\n\nPlease choose a valid place from the suggestions or enter a known landmark, airport, railway station, or city.`);
                    destInput.focus();
                    return;
                }
            });
        });
    </script>
    <script src="../js/script.js"></script>
</body>
</html>