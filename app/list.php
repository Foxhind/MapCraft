<?php
require '../lib/config.php';

$result = pg_query($connection, 'SELECT COUNT(id) FROM pies WHERE visible = true');
$piescount = intval(pg_fetch_result($result, 0, 0));

$piespp = 10;
$radius = 1;
$pagenum = isset($_GET['pagenum']) ? intval($_GET['pagenum']) : 1;
$start = ($pagenum - 1) * $piespp;
if ($start > $piescount or $start < 0)
    header('Location: /list');

$result = pg_query($connection, 'SELECT pies.id, pies.name, pies.description, users.nick, pies.start, pies.ends, count(pieces.id) AS num, sum(pieces.state) / (count(pieces.id) * 9.0) * 100 AS state FROM pies INNER JOIN pieces ON pies.id = pieces.pie JOIN users ON pies.author=users.id WHERE pies.visible = true GROUP BY pies.id, pies.name, pies.description, users.nick, pies.start, pies.ends ORDER BY pies.start LIMIT '.$piespp.' OFFSET '.$start);

if (pg_num_rows($result) > 0) {

    echo '<table class="list">';
    echo '<tr><th>Name</th><th>Slices</th><th>Progress</th><th>Author</th><th>Created</th><th>Closed</th></tr>';
    while ($row = pg_fetch_array($result)) {
        $state = round(floatval($row['state']));
        $wms_link = 'wms:http://'.$_SERVER['HTTP_HOST'].'/wms/'.$row['id'].'?';
        $created = preg_replace('/\.\d+$/', '', $row['start']);
        $id = $row['id'];
        $pie_link = '/pie/' . $id;
        // Base line
        echo '<tr>';
        echo '<td><span class="list_toggle closed" id="toggle_'.$id.'"></span><a href="'.$pie_link.'" target="_blank">'.$row['name'].'</a></td>';
        echo '<td>'.$row['num'].'</td>';
        echo '<td><meter value="'.$state.'" min="0" max="100" low="33" high="67">'.$state.'&nbsp;%</meter></td>';
        echo '<td><a href="http://www.openstreetmap.org/user/'.$row['nick'].'" target="_blank">'.$row['nick'].'</a></td>';
        echo '<td>'.$created.'</td>';
        echo '<td>'.($row['ends'] ? $row['ends'] : '—').'</td>';
        echo '</tr>';
        // Descr
        echo '<tr class="list_descr closed" id="descr_'.$id.'">';
        echo '<td colspan="6"><div><p>WMS link:  <span class="pseudolink" onclick="getquery(\'http://127.0.0.1:8111/imagery?title='.$row['name'].'&urldecode=false&url='.$wms_link.'\')">'.$wms_link.'</span></p><br />'.(empty($row['description']) ? 'No description' : $row['description']).'</div></td>';
        echo '</tr>';
    }
    echo '</table>';

    // Pages
    if ($piescount > $piespp) {
        $maxpage = intval(ceil($piescount / $piespp)); // 2
        if ($maxpage > 1) {
            echo '<ul class="pages">';
            if ($pagenum > (2 + $radius * 2)) {
                foreach (range(1, 1 + $radius) as $num)
                    echo '<li><a href="/list?pagenum='.$num.'" class="pagenum">'.$num.'</a></li>';
                echo ' … ';
                foreach (range($pagenum - $radius, $pagenum - 1) as $num)
                    echo '<li><a href="/list?pagenum='.$num.'" class="pagenum">'.$num.'</a></li>';
            }
            else
                if ($pagenum > 1)
                    foreach (range(1, $pagenum - 1) as $num)
                        echo '<li><a href="/list?pagenum='.$num.'" class="pagenum">'.$num.'</a></li>';
            echo '<li><span class="apagenum">'.$pagenum.'</span></li>';
            if (($maxpage - $pagenum) >= (2 + $radius * 2)) {
                foreach (range($pagenum + 1, $pagenum + $radius) as $num)
                    echo '<li><a href="/list?pagenum='.$num.'" class="pagenum">'.$num.'</a></li>';
                echo ' … ';
                foreach (range($maxpage - $radius, $maxpage) as $num)
                    echo '<li><a href="/list?pagenum='.$num.'" class="pagenum">'.$num.'</a></li>';
            }
            else
                if ($pagenum < $maxpage)
                    foreach (range($pagenum + 1, $maxpage) as $num)
                        echo '<li><a href="/list?pagenum='.$num.'" class="pagenum">'.$num.'</a></li>';
            echo '</ul>';
        }
    }
}
else {
    echo '<div id="pageheader" style="background-color: #6D926C;">List is empty</div>';
}
?>
