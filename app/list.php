<?php
require '../lib/config.php';

$result = pg_query($connection, 'SELECT COUNT(id) FROM pies WHERE visible = true');
$piescount = intval(pg_fetch_result($result, 0, 0));

$piespp = 10;
$start = isset($_GET['start']) ? intval($_GET['start']) : (isset($_GET['pagenum']) ? (intval($_GET['pagenum']) - 1) * $piespp : 0);
if ($start > $piescount or $start < 0)
    header('Location: /list');

$result = pg_query($connection, 'SELECT pies.id, pies.name, users.nick, pies.start, pies.ends, count(pieces.id) AS num, sum(pieces.state) / (count(pieces.id) * 9.0) * 100 AS state FROM pies INNER JOIN pieces ON pies.id = pieces.pie JOIN users ON pies.author=users.id WHERE pies.visible = true GROUP BY pies.id, pies.name, users.nick, pies.start, pies.ends LIMIT '.$piespp.' OFFSET '.$start);

if (pg_num_rows($result) > 0) {
    echo '<table class="list">';
    echo '<tr><th>Название</th><th>Сектора</th><th>Готовность</th><th>Создатель</th><th>Открыт</th><th>Закрыт</th></tr>';
    while ($row = pg_fetch_array($result)) {
        $state = round(floatval($row['state']));
        echo '<tr><td><a href="/pie/'.$row['id'].'">'.$row['name'].'</a></td><td>'.$row['num'].'</td><td><meter value="'.$state.'" min="0" max="100" low="33" high="67">'.$state.'&nbsp;%</meter></td><td>'.$row['nick'].'</td><td>'.$row['start'].'</td><td>'.($row['ends'] ? $row['ends'] : '—').'</td></tr>';
    }
    echo '</table>';

    // Pages
    // TODO: Big ranges cutting (1 2 ... 4 [5] 6 ... 15 16)
    if ($piescount > $piespp) {
        $numpages = ($piescount - ($piescount % $piespp)) / $piespp + 1;
        if ($numpages > 1) {
            $currentnum = ($start - ($start % $piespp)) / $piespp + 1;
            echo '<ul class="pages">';
            foreach (range(1, $numpages) as $num)
                echo '<li><a href="/list?pagenum='.$num.'" class="'.($num == $currentnum ? 'apagenum' : 'pagenum').'">'.$num.'</a></li>';
            echo '</ul>';
        }
    }
}
else {
    echo '<div id="pageheader" style="background-color: #6D926C;">Список пуст</div>';
}
?>
