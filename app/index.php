<?php
session_start();
if (!isset($_GET['page']))
    $_GET['page'] = 'list';
?>
<!doctype html>
<html>
<head>
<title>MapCraft — massively mapping management tool</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" href="/css/site.css" type="text/css" media="screen, projection" />
</head>

<body>
<header>MapCraft&nbsp;<sup>beta</sup></header>
<div id="panel"><nav><ul>
<?php
echo '<li class="c1'.($_GET['page']=='map'?' current':'').'"><a href="/map">Map</a></li>';
echo '<li class="c2'.($_GET['page']=='list'?' current':'').'"><a href="/list">List</a></li>';
echo '<li class="c3'.($_GET['page']=='create'?' current':'').'"><a href="/create">New cake</a></li>';
?>
</ul></nav></div>
<div id="login">
<?php
if (isset($_SESSION['osm_user']))
    echo $_SESSION['osm_user'].'&nbsp; &nbsp;<a href="/app/auth.php?action=logout&reload=1" target=\"_blank\">Logout</a>';
else {
	include '../lib/config.php';
	echo '<a href="/app/auth.php?reload=1" target="_blank">Login</a>';
}
?>
</div>
<div id="content">
<?php
include $_GET['page'].'.php';
?>
</div>
<footer>
by <a href="http://wiki.openstreetmap.org/wiki/User:Hind">Hind</a>, <a href="http://wiki.openstreetmap.org/wiki/User:Osmisto">osmisto</a>, 2011<br /><a href="https://github.com/Foxhind/MapCraft">on GitHub</a>
</footer>
</body>
