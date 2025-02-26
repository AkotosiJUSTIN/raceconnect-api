<?php
function secure_session_start() {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);
    session_start();
}

function check_session_timeout() {
    $timeout = 1800; // 30 minutes
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        header("Location: index_login.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function verify_session_security() {
    if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
        session_unset();
        session_destroy();
        header("Location: index_login.php?security=1");
        exit();
    }
}
?>