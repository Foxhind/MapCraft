<?php
$host = 'localhost';
$user = 'mapcrafter';
$pass = 'nekosan';
$db   = 'mapcraft';
$connection = pg_pconnect('host='.$host.' port=5432 dbname='.$db.' user='.$user.' password='.$pass);
pg_query($connection, 'SET search_path TO mapcraft');
?>