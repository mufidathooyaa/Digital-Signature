<?php
// Redirect ke login atau dashboard
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
?>