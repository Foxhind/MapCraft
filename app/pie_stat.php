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

$result = pg_query($connection, 'SELECT timestamp FROM mapcraft.pieces as p, mapcraft.pieces_comments as c WHERE c.piece = p.id and type = \'info\' and p.pie = ' . $pie_id . ' ORDER by timestamp desc LIMIT 1');

$ts = pg_fetch_result($result, 0, 0);
$res['updated'] = $ts == null ?  $pie['start'] : $ts;

echo json_encode($res);
?>