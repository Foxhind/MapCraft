<?php
    include '../lib/config.php';
    if (!isset($_GET['id']) || empty($_GET['id'])) exit();

    $colors = 3;
    $cmap = array();
    $c = 1;

    $format = '%H:%M:%S&nbsp;<span>%d.%m.%Y&nbsp;%Z</span>';
    $pie_id = $_GET['id'];
    $filename = $_SERVER['DOCUMENT_ROOT'].'/log/'.$pie_id.'.html';
    if (!file_exists($filename) || time() - filemtime($filename) > 300) {
        $log = fopen($filename, 'w');
        fwrite($log, '<!doctype html><html><head><meta http-equiv="content-type" content="text/html; charset=utf-8"><link rel="stylesheet" href="/css/log.css" type="text/css"><title>Log of cake '.$pie_id.'</title></head><body><table>');
        $result = pg_query($connection, 'SELECT U.nick,C.message,round(date_part(\'epoch\',C.timestamp)) AS ts FROM chat AS C JOIN users AS U ON U.id=C.author WHERE pie='.$pie_id.' ORDER BY C.timestamp ASC');
        while ($row = pg_fetch_array($result)) {
	    if (!isset($cmap[$row['nick']])) {
		$cmap[$row['nick']] = $c++;
		if ($c > $colors) $c = 1;
	    }
	    $ts = $row['ts'];
            fwrite($log, '<tr id="t'.$ts.'"><td class="nick c'.$cmap[$row['nick']].'">'.$row['nick'].'</td><td>'.$row['message'].'</td><td class="time"><a href="#t'.$ts.'">'.strftime($format, $ts).'</a></td></tr>'."\n");
        }
        fwrite($log, "</table></body></html>");
        fclose($log);
    }
    include($filename);
?>
