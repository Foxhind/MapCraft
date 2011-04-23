<?php
session_start();
if (!isset($_GET['page']))
    $_GET['page'] = 'list';
if (isset($_GET['logout']))
{
    session_unset();
    Header("Location: ".$REQUEST_URL.$_GET['page']);
    exit();
}
?>
<!doctype html>
<html>
<head>
<title>MapCraft — massively mapping helper</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" href="/css/site.css" type="text/css" media="screen, projection" />
</head>

<body>
<header>MapCraft</header>
<div id="panel"><nav><ul>
<?php
echo '<li class="c1'.($_GET['page']=='map'?' current':'').'"><a href="/map">Карта</a></li>';
echo '<li class="c2'.($_GET['page']=='list'?' current':'').'"><a href="/list">Список</a></li>';
echo '<li class="c3'.($_GET['page']=='create'?' current':'').'"><a href="/create">Новый пирог</a></li>';
?>
</ul></nav></div>
<div id="login">
<?php
if (isset($_SESSION['osm_user']))
    echo $_SESSION['osm_user'].'&nbsp; &nbsp;<a href="?logout">Выход</a>';
else
	include '../lib/config.php';
	echo "<a href=\"/app/auth_$auth_type.php\" target=\"_blank\">Вход</a>";
?>
</div>
<div id="content">
<?php
include $_GET['page'].'.php';
?>
</div>
<footer>
by Hind, osmisto, 2011
</footer>
</body>