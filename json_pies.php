<?php
require 'config.php';

Header('Content-type: application/json; charset=utf-8');

$result = pg_query($connection, 'SELECT id, name, jcenter FROM pies LIMIT 100');

$first = true;
echo '{"type":"FeatureCollection","features":[';
while ($row = pg_fetch_array($result)) {
    if (!$first) echo ',';
    else $first = false;
    echo '{"type":"Feature","geometry":{"type":"Point","coordinates":'.$row['jcenter'].'},"properties":{"id":"'.$row['id'].'","name":"'.$row['name'].'"}}';
}
echo ']}';
?>