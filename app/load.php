<?php

ob_implicit_flush();
set_time_limit(0);

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

if (mysql_result(mysql_query("SELECT COUNT(*) FROM online WHERE `nick` = '".$nick."'"), 0) > 0)
    mysql_query("UPDATE `online` SET `lastactivity`=".time().", `online`=1 WHERE `nick`='".$nick."'");
else
{
    mysql_query("INSERT INTO `online` SET `nick`='".$nick."',`lastactivity`=".time());
    mysql_query("UPDATE `online` SET `quserlist`=1");
}

$pre = mysql_result(mysql_query("SELECT COUNT(*) FROM `online` WHERE `lastactivity`<".(time()-35)." AND `online`"), 0);
if ($pre > 0) {
    mysql_query("UPDATE `online` SET `online`=0 WHERE `lastactivity`<".(time()-35)." AND `online`");
    mysql_query("UPDATE `online` SET `qnews`=1,`quserlist`=1");
}

// Основной цикл ожидания событий
$time = time();
while((time() - $time) < 30) {
    if (mysql_result(mysql_query("SELECT `qnews` FROM `online` WHERE `nick`='".$nick."'"), 0) == 1) {
        echo Load();
        mysql_query("UPDATE `online` SET `qnews`=0 WHERE `nick`='".$nick."'");
        break;
    }
    sleep(1);
}

function Load()
{
global $nick;

$last_message_id = intval($_POST['last']);

$query = mysql_query("SELECT * FROM `messages` WHERE ( id > $last_message_id ) ORDER BY id DESC LIMIT 10");

if( mysql_num_rows($query) > 0 )
{

$js = 'var chat = $("#chat tbody");';

$messages = array();
while ( $row = mysql_fetch_array($query) )
{
$messages[] = $row;
}

$last_message_id = $messages[0]['id'];

$messages = array_reverse($messages);

foreach ( $messages as $value )
{
$js .= 'chat.append("<tr><td class=\'nick\'>&lt;' . $value['name'] . '&gt;</td><td class=\'msg\'>' . $value['text'] . '</td><td>'.substr($value['ts'],0,5).'</td></tr>");';
}

$js .= "last_message_id = $last_message_id;";

}

if (mysql_result(mysql_query("SELECT COUNT(*) FROM `online` WHERE `nick` = '".$nick."' AND `quserlist`=1"), 0) > 0)
{
    $js .= "RenewUserlist();";
    mysql_query("UPDATE `online` SET `quserlist`=0 WHERE `nick`='".$nick."'");
}
if (mysql_result(mysql_query("SELECT COUNT(*) FROM `online` WHERE `nick` = '".$nick."' AND `qgeodata`=1"), 0) > 0)
{
    $js .= "RenewKML();";
    mysql_query("UPDATE `online` SET `qgeodata`=0 WHERE `nick`='".$nick."'");
}

return $js;
}
?>
