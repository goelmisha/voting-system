<?php
session_start();

// Unset all session variables
$_SESSION = [];

// Delete the active session cookie using server parameters
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy server session
session_destroy();

// Redirect back to admin login
header("Location: login.php");
exit();
?>