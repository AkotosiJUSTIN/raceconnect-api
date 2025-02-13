<?php
// filepath: /c:/xampp/htdocs/RaceConnect-Admin/logout.php

// Start session
session_start();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: index_login.php");
exit();
?>