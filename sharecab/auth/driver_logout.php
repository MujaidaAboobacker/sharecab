<?php

session_start();

// Unset driver session variables
unset($_SESSION["driver_id"]);
unset($_SESSION["driver_name"]);
unset($_SESSION["driver_email"]);
unset($_SESSION["driver_vehicle"]);
unset($_SESSION["driver_vehicle_number"]);
unset($_SESSION["role"]);

session_destroy();

header("Location: driver_login.php");
exit();
?>
