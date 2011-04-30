<?php
require '../lib/config.php';

Header('Content-type: application/json; charset=utf-8');

$result = pg_query($connection, 'SELECT id, name, description, jcenter FROM pies WHERE visible = true LIMIT 100');

$first = true;
echo '{"type":"FeatureCollection","features":[';
while ($row = pg_fetch_array($result)) {
    if (!$first) echo ',';
    else $first = false;
    $description = preg_replace("/('|\"|\r?\n)/",' ', nl2br(wordwrap($row['description'], 64, '<br />', true)));
    echo '{"type":"Feature","geometry":{"type":"Point","coordinates":'.$row['jcenter'].'},"properties":{"id":"'.$row['id'].'","name":"'.$row['name'].'","description":"'.$description.'"}}';
}
echo ']}';
?>