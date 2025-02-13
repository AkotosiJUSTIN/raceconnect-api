<?php
// filepath: /c:/xampp/htdocs/RaceConnect-Admin/logout.php

session_start();
session_unset();
session_destroy();

// Clear session cookie manually
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 3600, "/");
}

// Clear login cookies
setcookie('email', '', time() - 3600, "/");
setcookie('username', '', time() - 3600, "/");

header("Location: index_login.php");
exit();
?>