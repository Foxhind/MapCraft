<?php
require '../lib/config.php';

ini_set('session.gc_maxlifetime', 7776000);
ini_set('session.cookie_lifetime', 7776000);
session_set_cookie_params(7776000);
session_start();

if (empty($_SESSION['osm_user']) || empty($_REQUEST['id'])) {
    header('Location: /');
    exit();
}

if (!empty($_POST['ok'])) {
    $id = intval($_REQUEST['id']);
    $result = pg_query("DELETE FROM pies WHERE id={$id}");
    header('Location: /');
    exit();
}

?>
<!doctype html>
<html>
<head>
<title>MapCraft — massively mapping management tool</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" href="/css/site.css" type="text/css" media="screen, projection">
</head>

<body>
<header>MapCraft&nbsp;<sup>beta</sup></header>
<div id="panel"><nav><ul>
<li class="c1"><a href="/map">Map</a></li>
<li class="c2"><a href="/list">List</a></li>
<li class="c3"><a href="/create">New cake</a></li>
</ul></nav></div>
<div id="login">
<?php
echo $_SESSION['osm_user'].'&nbsp; &nbsp;<a href="/app/auth.php?action=logout&reload=1" target=\"_blank\">Logout</a>';
}
?>
</div>
<div id="content">
<div style="text-align: center;">Remove this cake, are you sure?</div><br>
<form method="POST" action="" style="text-align: center;">
<input type="submit" value="OK" name="ok" style="width: 100px;">&emsp;<input type="submit" value="Cancel" name="cancel" style="width: 100px;">
</form>
</div>
<footer>
by <a href="http://wiki.openstreetmap.org/wiki/User:Hind">Hind</a>, <a href="http://wiki.openstreetmap.org/wiki/User:Osmisto">osmisto</a>, 2011<br /><a href="https://github.com/Foxhind/MapCraft">on GitHub</a>
</footer>
</body>
