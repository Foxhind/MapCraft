<?php

session_start();
include '../lib/config.php';
$res = array();

if(!isset($_GET['pie_id'])) {
    echo '{"error": "pie_id is not set"}';
    exit();
}
$pie_id = (int) $_GET['pie_id'];

$result = pg_query($connection, 'SELECT * FROM pies WHERE id = ' . $pie_id);
if (pg_num_rows($result) == 0) {
    echo '{"error": "The cake is not found"}';
    exit();
}
$pie = pg_fetch_assoc($result);

$res['name'] = $pie['name'];
$res['description'] = $pie['description'];

$res['updated'] = $pie["updated"] || $pie["start"];

echo json_encode($res);
?>