<?php
include '../lib/config.php';
session_start();

$reload = isset($_GET['reload']);
$reload_key = 'reload_after_login';
if (!isset($_GET['action'])) {
    $_GET['action'] = 'login';
}


switch ($_GET['action']) {

case 'login':

    if($reload)
        $_SESSION[$reload_key] = true;
    else
        session_unregister($reload_key);

    // Go to actual auth script
    Header("Location: /app/auth_$auth_type.php");
    break;

case 'logout':

    $sesid = session_id();
    session_unset();
    session_write_close();
    system('curl -s -d "" "' . $hub_full_url . '/api/session/' . $sesid . '/logout"');

    if ($reload)
        echo("<script>window.opener.location.reload(true);</script>");

    echo("<script>window.close()</script>");
    break;

case 'success':

    $reload = isset($_SESSION[$reload_key]);

    $sesid = session_id();
    session_write_close();
    system('curl -s -d "" "' . $hub_full_url . '/api/session/' . $sesid . '/login"');

    if($reload)
        echo "<script>window.opener.location.reload(true);</script>";

    echo("<script>window.close()</script>");
    break;

default:
    echo "Unknown action";
}

?>