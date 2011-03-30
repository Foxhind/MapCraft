<?php

$config = array( 'hostname' => 'localhost', 'username' => 'mapcrafter', 'password' => 'nekosan', 'dbname' => 'pie' );
if( !mysql_connect($config['hostname'], $config['username'], $config['password']) )
{
exit();
}

if( !mysql_select_db($config['dbname']) )
{
exit();
}
mysql_query("SET NAMES 'utf8'");

Header("Cache-Control: no-cache, must-revalidate");
Header("Pragma: no-cache");
Header("Content-Type: text/javascript; charset=utf-8");

if (isset($_POST['nick']))
    $nick = mysql_real_escape_string(htmlspecialchars(substr($_POST['nick'], 0, 64)));

if (isset($_POST['act']))
{
switch ($_POST['act'])
{
case "send" :
Send();
break;
case "enter" :
Enter();
break;
case "getuserlist" :
GetUserlist();
break;
case "getcomments" :
GetComments();
break;
case "setnick" :
SetNick();
break;
case "setstatus" :
SetStatus();
break;
case "postcomment" :
PostComment();
break;
case "takepiece" :
TakePiece();
break;
case "claimpiece" :
ClaimPiece();
break;
case "refusepiece" :
RefusePiece();
break;
case "vote" :
Vote();
break;
case "closeclaim" :
CloseClaim();
break;
default :
exit();
}
}

function ServerMsg($text, $name = "", $type = "msg")
{
    echo 'ChatMsg(\''.$text.'\' ,\''.$name.'\' ,\''.$type.'\');';
}

function LongPoll()
{
    // Новая информация для long poll
    mysql_query("UPDATE `online` SET `qnews`=1");
}

function Send()
{
global $nick;
    $text = substr(trim($_POST['text']), 0, 1024);
    if (!empty($text))
    {
        $text = htmlspecialchars($text); 
        $text = mysql_real_escape_string($text);
        mysql_query("INSERT INTO messages (ts,name,text) VALUES ('" . date("H:i:s", time()) . "', '" . $nick . "', '" . $text . "')");
        LongPoll();
    }
}

function BroadcastMsg($type, $text)
{
    mysql_query("INSERT INTO messages (ts,name,text) VALUES ('" . date("H:i:s", time()) . "', '', '" . $text . "')");
    LongPoll();
}

function CloseClaim()
{
global $nick;
    // Номер забираемого сектора
    $id = $_POST['id'];
    // Строка данных пользователя
    $row = mysql_fetch_array(mysql_query("SELECT * FROM `online` WHERE `nick`='".$nick."'"), MYSQL_NUM);
    $claims = explode(',', $row[5]);
    $scores = explode(',', $row[6]);
    $plusvotes = explode(';', $row[7]);
    $minusvotes = explode(';', $row[8]);
    // Проверка на случай, если такой заявки нет
    $index = array_search($id, $claims);
    if ($index === False)
    {
        ServerMsg("Такой заявки не существует!", "", "errormsg");
        return 0;
    }
    // Удалить заявку из записи пользователя
    unset($claims[$index]);
    unset($scores[$index]);
    unset($plusvotes[$index]);
    unset($minusvotes[$index]);
    mysql_query("UPDATE `online` SET `plusvotes`='".implode(';', $plusvotes)."', `minusvotes`='".implode(';', $minusvotes)."', `claims`='".implode(',', $claims)."', `scores`='".implode(',', $scores)."' WHERE `nick`='".$nick."'");
    // Заставить онлайн-пользователей перекачать KML и список пользователей
    mysql_query("UPDATE `online` SET `qgeodata`=1, `quserlist`=1 WHERE `online`");
    // Обновить KML-файл
    RenewKML();
    LongPoll();
    ServerMsg("Заявка на сектор ".$id." от пользователя ".$nick." снята.", "", "servermsg");
}

function RefusePiece()
{
global $nick;
    // Номер отдаваемого сектора
    $id = $_POST['id'];
    // Проверка, что сектор действительно принадлежит этому пользователю
    $owner = mysql_result(mysql_query("SELECT `owner` FROM `data` WHERE `id`=".$id), 0);
    if ($owner != $nick)
    {
        echo "$('#drefuse').dialog('close');";
        ServerMsg("errormsg", "Сектор вам не принадлежит.");
        return 0;
    }
    // Очистить владельца в таблице данных
    mysql_query("UPDATE `data` SET `owner`='' WHERE `id`=".$id);
    // Удалить сектор у владельца в таблице пользователей
    $owns = explode(',', mysql_result(mysql_query("SELECT `owns` FROM `online` WHERE `nick`='".$nick."'"), 0));
    $index = array_search($id, $owns);
    if ($index === False)
    {
        echo "$('#drefuse').dialog('close');";
        ServerMsg("Ошибка: рассинхронизированы таблицы в базе данных.", "", "errormsg");
        return 0;
    }
    unset($owns[$index]);
    mysql_query("UPDATE `online` SET `owns`='".implode(',', $owns)."' WHERE `nick`='".$nick."'");
    echo "$('#drefuse').dialog('close');";
    ServerMsg("Отказ от сектора ".$id." подтвержден.", "", "servermsg");
    // Заставить онлайн-пользователей перекачать KML и список пользователей
    mysql_query("UPDATE `online` SET `qgeodata`=1, `quserlist`=1 WHERE `online`");
    // Обновить KML-файл
    RenewKML();
    LongPoll();
}

function ClaimPiece()
{
global $nick;
    // Номер забираемого сектора
    $id = $_POST['id'];
    // Проверка, что сектор не принадлежит этому пользователю
    $owner = mysql_result(mysql_query("SELECT `owner` FROM `data` WHERE `id`=".$id), 0);
    if ($owner == $nick)
    {
        echo "$('#dclaim').dialog('close');";
        ServerMsg("errormsg", "Сектор уже ваш.");
        return 0;
    }
    // Строка данных пользователя
    $row = mysql_fetch_array(mysql_query("SELECT * FROM `online` WHERE `nick`='".$nick."'"), MYSQL_NUM);
    $claims = explode(',', $row[5]);
    $scores = explode(',', $row[6]);
    // Проверка на случай, если такая заявка уже есть
    $index = array_search($id, $claims);
    if (!($index === False))
    {
        echo "$('#dclaim').dialog('close');";
        ServerMsg("От вас уже есть заявка на этот сектор!", "", "errormsg");
        return 0;
    }
    // Проверки на случай, если еще пусто
    if (count($claims) == 1 and empty($claims[0])) unset($claims[0]);
    if (count($scores) == 1 and $scores[0] == '') unset($scores[0]);
    array_push($claims, $id);
    array_push($scores, 0);
    mysql_query("UPDATE `online` SET `claims`='".implode(',', $claims)."', `scores`='".implode(',', $scores)."' WHERE `nick`='".$nick."'");
    // Заставить онлайн-пользователей перекачать KML и список пользователей
    mysql_query("UPDATE `online` SET `qgeodata`=1, `quserlist`=1 WHERE `online`");
    // Обновить KML-файл
    RenewKML();
    echo "$('#dclaim').dialog('close');";
    ServerMsg("Добавлена заявка на сектор ".$id." от пользователя ".$nick.".", "", "servermsg");
    LongPoll();
}

function TakePiece()
{
global $nick;
    // Номер забираемого сектора
    $id = $_POST['id'];
    // Проверить, не занят ли уже сектор
    $owner = mysql_result(mysql_query("SELECT `owner` FROM `data` WHERE `id`=".$id), 0);
    if (isset($owner) and !empty($owner))
    {
        ServerMsg("Сектор ".$id." уже занят пользователем ".$owner.".", "", "errormsg");
    }
    else
    {
        ServerMsg("Сектор ".$id." взят на отрисовку.", "", "servermsg");
        // Установить владельца в таблице данных
        mysql_query("UPDATE `data` SET `owner`='".$nick."' WHERE `id`=".$id);
        // Добавить сектор новому владельцу в таблице пользователей
        $owns = explode(',', mysql_result(mysql_query("SELECT `owns` FROM `online` WHERE `nick`='".$nick."'"), 0));
        // Проверка на случай, если еще пусто
        if (count($owns) == 1 and empty($owns[0])) unset($owns[0]);
        array_push($owns, $id);
        mysql_query("UPDATE `online` SET `owns`='".implode(',', $owns)."' WHERE `nick`='".$nick."'");
        // Заставить онлайн-пользователей перекачать KML и список пользователей
        mysql_query("UPDATE `online` SET `qgeodata`=1, `quserlist`=1 WHERE `online`");
        // Обновить KML-файл
        RenewKML();
        LongPoll();
    }
    echo "$('#dtake').dialog('close');";
}

function Vote()
{
global $nick;
    $nickname = mysql_real_escape_string(htmlspecialchars(substr($_POST['nickname'], 0, 64)));
    $num = $_POST['number'];
    $vote = $_POST['vote'];
    $ip = $_SERVER["REMOTE_ADDR"];
    $row = mysql_fetch_array(mysql_query("SELECT * FROM `online` WHERE `nick`='".$nickname."'"), MYSQL_NUM);
    $claims = explode(',', $row[5]);
    $index = array_search($num, $claims);
    if ($index === False)
    {
        ServerMsg("errormsg", "Такой заявки не существует.");
        return 0;
    }
    $scores = explode(',', $row[6]);
    $plusvotes = explode(';', $row[7]);
    $minusvotes = explode(';', $row[8]);
    if (strpos($plusvotes[$index].$minusvotes[$index], $ip) === False)
    {
        if ($vote > 0)
        {
            $plusvotesforthis = explode(',', $plusvotes[$index]);
            $minusvotesforthis = explode(',', $minusvotes[$index]);
            // Проверка на случай, если еще пусто
            if (count($plusvotesforthis) == 1 and empty($plusvotesforthis[0])) unset($plusvotesforthis[0]);
            if (count($minusvotesforthis) == 1 and empty($minusvotesforthis[0])) unset($minusvotesforthis[0]);
            array_push($plusvotesforthis, $ip);
            $plusvotes[$index] = implode(',', $plusvotesforthis);
            $scores[$index] = count($plusvotesforthis) - count($minusvotesforthis);
            ServerMsg("Ваш голос отдан за отжатие сектора ".$num." пользователем ".$nickname."!", "", "servermsg");
            if ($scores[$index] > 2)
            {
                ServerMsg("Заявка прошла, сектор ".$num." передан пользователю ".$nickname."!", "", "servermsg");
                unset($scores[$index]);
                unset($plusvotes[$index]);
                unset($minusvotes[$index]);
                unset($claims[$index]);
                mysql_query("UPDATE `online` SET `plusvotes`='".implode(';', $plusvotes)."', `minusvotes`='".implode(';', $minusvotes)."', `claims`='".implode(',', $claims)."', `scores`='".implode(',', $scores)."', `owns`='".$row[4].",".$num."' WHERE `nick`='".$nickname."'");
                $oldowner = mysql_result(mysql_query("SELECT `owner` FROM `data` WHERE `id`=".$num), 0);
                $oldownerowns = explode(',', mysql_result(mysql_query("SELECT `owns` FROM `online` WHERE `nick`='".$oldowner."'"), 0));
                $oldownerindex = array_search($num, $oldownerowns);
                if (!($oldownerindex === False))
                {
                    unset($oldownerowns[$oldownerindex]);
                    mysql_query("UPDATE `online` SET `owns`='".implode(',', $oldownerowns)."' WHERE `nick`='".$oldowner."'");
                }
                mysql_query("UPDATE `data` SET `owner`='".$nickname."' WHERE `id`=".$num);
                RenewKML();
                mysql_query("UPDATE `online` SET `qgeodata`=1 WHERE `online`");
                LongPoll();
            }
            else
            {
                mysql_query("UPDATE `online` SET `plusvotes`='".implode(';', $plusvotes)."', `scores`='".implode(',', $scores)."' WHERE `nick`='".$nickname."'");
            }
        }
        else
        {
            $plusvotesforthis = explode(',', $plusvotes[$index]);
            $minusvotesforthis = explode(',', $minusvotes[$index]);
            // Проверка на случай, если еще пусто
            if (count($plusvotesforthis) == 1 and empty($plusvotesforthis[0])) unset($plusvotesforthis[0]);
            if (count($minusvotesforthis) == 1 and empty($minusvotesforthis[0])) unset($minusvotesforthis[0]);
            array_push($minusvotesforthis, $ip);
            $minusvotes[$index] = implode(',', $minusvotesforthis);
            $scores[$index] = count($plusvotesforthis) - count($minusvotesforthis);
            ServerMsg("Ваш голос отдан против отжатия сектора ".$num." пользователем ".$nickname."!", "", "servermsg");
            if ($scores[$index] < -2)
            {
                ServerMsg("Заявка снята голосованием.", "", "errormsg");
                unset($scores[$index]);
                unset($plusvotes[$index]);
                unset($minusvotes[$index]);
                unset($claims[$index]);
                mysql_query("UPDATE `online` SET `plusvotes`='".implode(';', $plusvotes)."', `minusvotes`='".implode(';', $minusvotes)."', `claims`='".implode(',', $claims)."', `scores`='".implode(',', $scores)."' WHERE `nick`='".$nickname."'");
            }
            else
            {
                mysql_query("UPDATE `online` SET `minusvotes`='".implode(';', $minusvotes)."', `scores`='".implode(',', $scores)."' WHERE `nick`='".$nickname."'");
            }
        }
        mysql_query("UPDATE `online` SET `quserlist`=1");
        LongPoll();
    }
    else
    {
        ServerMsg("Вы уже проголосовали ".(strpos($plusvotes[$index], $ip) === False ? "«против»" : "«за»")."!", "", "errormsg");
    }
}

function GetComments()
{
    $id = $_POST['id'];
    $comments = mysql_result(mysql_query("SELECT `comments` FROM `data` WHERE `id`=".$id), 0);
    echo "$('#comments').html('".$comments."');";
}

function PostComment()
{
global $nick;
    $text = substr(trim($_POST['text']), 0, 1024);
    if (strlen($text) > 0)
    {
        $text = str_replace("\n", " ", str_replace("\r", "", $text));
        $text = htmlspecialchars($text); 
        $text = mysql_real_escape_string($text);
        $text = "<span><strong>".$nick."</strong> (".date("j F Y, H:i:s", time())."):<br />".$text."</span><br /><br />";
        $id = $_POST['id'];
        $comments = mysql_result(mysql_query("SELECT `comments` FROM `data` WHERE `id`=".$id), 0);
        $comments .= $text;
        mysql_query("UPDATE `data` SET `comments`='".$comments."' WHERE `id`=".$id);
        echo "$('#comments').html($('#comments').html() + '".$text."');";
    }
}

function SetNick()
{
global $nick;
    if (isset($_POST['newnick']))
    {
        $newnick = trim(mysql_real_escape_string(htmlspecialchars(substr($_POST['newnick'], 0, 64))));
        if (empty($newnick))
        {
            ServerMsg("Пустые ники запрещены.", "", "errormsg");
            return 0;
        }
        $row = mysql_fetch_row(mysql_query("SELECT `online` FROM `online` WHERE `nick`='".$newnick."'"));
        if ($row === False)
        {
            echo "nick='".$newnick."'; $('#pac_nick').button('option', 'label', nick);";
            ServerMsg("Установлен новый ник: ".$newnick, "", "servermsg");
            mysql_query("UPDATE `online` SET `nick`='".$newnick."' WHERE `nick`='".$nick."'");
            mysql_query("UPDATE `data` SET `owner`='".$newnick."' WHERE `owner`='".$nick."'");
            mysql_query("UPDATE `online` SET `quserlist`=1, `qgeodata`=1");
            RenewKML();
            LongPoll();
        }
        elseif ($row[0] == '1')
        {
            ServerMsg("Ник ".$newnick." уже занят.", "", "errormsg");
        }
        elseif ($row[0] == '0')
        {
            echo "nick='".$newnick."'; $('#pac_nick').button('option', 'label', nick);";
            ServerMsg("Установлен новый ник: ".$newnick, "", "servermsg");
            mysql_query("DELETE FROM `online` WHERE `nick`='".$nick."'");
            mysql_query("UPDATE `online` SET `online`=1 WHERE `nick`='".$newnick."'");
            mysql_query("UPDATE `data` SET `owner`='".$newnick."' WHERE `owner`='".$nick."'");
            mysql_query("UPDATE `online` SET `quserlist`=1, `qgeodata`=1");
            RenewKML();
            LongPoll();
        }
    }
}

function SetStatus()
{
global $nick;
    $status = $_POST['status'];
    // Номер изменяемого сектора
    $id = $_POST['id'];
    // Проверка, что сектор действительно принадлежит этому пользователю
    $owner = mysql_result(mysql_query("SELECT `owner` FROM `data` WHERE `id`=".$id), 0);
    if ($owner != $nick)
    {
        echo "$('#dstatus').dialog('close');";
        ServerMsg("errormsg", "Сектор вам не принадлежит.");
        return 0;
    }
    mysql_query("UPDATE `data` SET `status`=".$status." WHERE `id`=".$id);
    echo "$('#dstatus').dialog('close'); kmllayer.features[".$id."-1].style.fillColor=color[".$status."]; kmllayer.redraw(true); kmllayer.features[".$id."-1].attributes.description='".$status."'; $('#status').text('".$status."');";
    ServerMsg("Статус сектора ".$id." успешно изменен на ".$status."!", "", "servermsg");
    mysql_query("UPDATE `online` SET `qgeodata`=1 WHERE `nick`<>'".$nick."'");
    RenewKML();
    LongPoll();
}

function RenewKML()
{
    $color = array ("800000ff","80004cff","800086ff","8000c0ff","8000eeff","8000ffff","8000ffcb","8000ff97","8000ff5f","8000ff00");
    // 0: id, 1: geodata, 2: owner, 3: status, 4: comments
    $query = mysql_query("SELECT * FROM `data`");
    $kml = fopen("pie.kml", "w");
    fwrite($kml, "<?xml version=\"1.0\" encoding=\"utf-8\" ?>
<kml xmlns=\"http://www.opengis.net/kml/2.1\">
<Document><Folder><name>pie</name>");
    while ($row = mysql_fetch_array($query, MYSQL_NUM)) {
        fwrite($kml, "<Placemark>
<name>".$row[0]."</name>
<description>".$row[3]."</description>
<owner>".$row[2]."</owner>
<Style><LineStyle><color>ff000000</color><width>1</width></LineStyle><PolyStyle><color>".$color[$row[3]]."</color></PolyStyle></Style>
<Polygon><outerBoundaryIs><LinearRing><coordinates>".$row[1]."</coordinates></LinearRing></outerBoundaryIs></Polygon>
</Placemark>");
    }
    fwrite($kml, "</Folder></Document></kml>");
    fclose($kml);
}

function Enter()
{
global $nick;
    if (empty($_POST['nick'])) {
        do
        {
            $nick = "Anon".rand(100, 999);
        }
        while (mysql_num_rows(mysql_query("SELECT * FROM online WHERE `nick` = '".$nick."'")) > 0);
        echo "nick='".$nick."';";
    }
    echo "$('#pac_nick').button('option', 'label', nick);";
    ServerMsg("Добро пожаловать в наш уютный чатик, ".$nick."!", "", "servermsg");
    if (mysql_num_rows(mysql_query("SELECT * FROM online WHERE `nick` = '".$nick."'")) > 0)
        mysql_query("UPDATE `online` SET `online`=1, `lastactivity`=".time()." WHERE `nick`='".$nick."'");
    else
        mysql_query("INSERT INTO `online` SET `nick`='".$nick."', `lastactivity`=".time());
    mysql_query("UPDATE `online` SET `quserlist`=1");
    LongPoll();
}

function GetUserlist()
{
    $js = "var userlist = {";
    $query = mysql_query("SELECT * FROM `online` WHERE `online` OR `owns`<>'' OR `claims`<>''");
    $first = true;
    while ($row = mysql_fetch_array($query, MYSQL_NUM)) {
        if ($first)
            $first = false;
        else
            $js .= ",";
        $js .= "'".$row[0]."':[[".$row[4]."],[".$row[5]."],[".$row[6]."],".$row[9]."]";
    }
    $js .= "};";
    echo $js;
}
?>
