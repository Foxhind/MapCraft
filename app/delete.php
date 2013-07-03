<?php
require '../lib/config.php';

ini_set('session.gc_maxlifetime', 7776000);
ini_set('session.cookie_lifetime', 7776000);
session_set_cookie_params(7776000);
session_start();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (empty($_SESSION['osm_user']) || !$id) {
    header('Location: /');
    exit();
}

$result = pg_query("SELECT author FROM pies WHERE id = $id");
$owner_id = pg_fetch_result($result, 0, "author");
$user_id = $_SESSION['user_id'];
if (!isset($user_id) || $owner_id != $user_id) {
    echo "Only author of this cake can delete it.";
    exit();
}


if (!empty($_POST['ok'])) {
    $id = intval($_REQUEST['id']);
    pg_query("DELETE FROM votes WHERE claim IN (SELECT id FROM claims WHERE piece IN (SELECT id FROM pieces WHERE pie={$id}))");
    pg_query("DELETE FROM claims WHERE piece IN (SELECT id FROM pieces WHERE pie={$id})");
    pg_query("DELETE FROM pieces_comments WHERE piece IN (SELECT id FROM pieces WHERE pie={$id})");
    pg_query("DELETE FROM pieces WHERE pie={$id}");
    pg_query("DELETE FROM access WHERE pie={$id}");
    pg_query("DELETE FROM chat_members WHERE pie={$id}");
    pg_query("DELETE FROM chat WHERE pie={$id}");
    pg_query("DELETE FROM pies WHERE id={$id}");
    header('Location: /');
    exit();
}
elseif (!empty($_POST['cancel'])) {
    header('Location: /list');
    exit();
}

?>
<!doctype html>
<html>
<head>
<title>MapCraft — massively mapping management tool for OpenStreetMap for OpenStreetMap</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" href="/css/site.css" type="text/css" media="screen, projection">
</head>

<body>
<header>MapCraft&nbsp;<sup>beta</sup></header>
<div id="panel"><nav><ul>
<li class="c1"><a href="/map">Map</a></li>
<li class="c2"><a href="/list">List</a></li>
<li class="c3"><a href="/create">New cake</a></li>
<li class="c4"><a href="http://wiki.openstreetmap.org/wiki/MapCraft" target="_blank">Help</a></li>
</ul></nav></div>
<div id="login">
<?php
echo $_SESSION['osm_user'].'&nbsp; &nbsp;<a href="/app/auth.php?action=logout&reload=1" target=\"_blank\">Logout</a>';
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
