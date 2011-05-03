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

$result = pg_query($connection, 'SELECT pies.id, pies.name, pies.description, users.nick, pies.start, pies.ends, count(pieces.id) AS num, sum(pieces.state) / (count(pieces.id) * 9.0) * 100 AS state FROM pies INNER JOIN pieces ON pies.id = pieces.pie JOIN users ON pies.author=users.id WHERE pies.visible = true GROUP BY pies.id, pies.name, pies.description, users.nick, pies.start, pies.ends LIMIT '.$piespp.' OFFSET '.$start);

if (pg_num_rows($result) > 0) {
    echo '<script>
    function toggledesc (row) {
        var rows = row.parentNode.getElementsByClassName("desc");
        for(var i = 0; i < rows.length; i++)
            rows[i].style.display = (rows[i] == row.nextSibling) ? ((rows[i].style.display == "table-row") ? "none" : "table-row") : "none";
    }
</script>';
    echo '<table class="list">';
    echo '<tr><th>Название</th><th>Сектора</th><th>Готовность</th><th>Создатель</th><th>Открыт</th><th>Закрыт</th></tr>';
    while ($row = pg_fetch_array($result)) {
        $state = round(floatval($row['state']));
        echo '<tr onclick="toggledesc(this)"><td><a href="/pie/'.$row['id'].'" target="_blank">'.$row['name'].'</a></td><td>'.$row['num'].'</td><td><meter value="'.$state.'" min="0" max="100" low="33" high="67">'.$state.'&nbsp;%</meter></td><td>'.$row['nick'].'</td><td>'.$row['start'].'</td><td>'.($row['ends'] ? $row['ends'] : '—').'</td></tr>';
        echo '<tr class="desc"><td colspan="6">'.(empty($row['description']) ? 'Нет описания' : $row['description']).'</td></tr>';
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
    echo '<div id="pageheader" style="background-color: #6D926C;">Список пуст</div>';
}
?>
