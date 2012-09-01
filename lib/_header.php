<!doctype html>
<html>
<head>
<title>MapCraft — massively mapping management tool</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" href="/css/site.css" type="text/css" media="screen, projection">
</head>

<body>
<header><img width="124" height="100" src="/img/logo.png"><br>MapCraft</header>
<div id="panel"><nav><ul>
<li class="c1"><a href="/map">Map</a></li>
<li class="c2"><a href="/list">List</a></li>
<li class="c3"><a href="/create">New cake</a></li>
</ul></nav></div>
<div id="login">
<?php
if (isset($_SESSION['osm_user']))
    echo $_SESSION['osm_user'].'&nbsp; &nbsp;<a href="/app/auth.php?action=logout&reload=1" target=\"_blank\">Logout</a>';
else {
    echo '<a href="/app/auth.php?reload=1" target="_blank">Login</a>';
}
//echo $_SESSION['osm_user'].'&nbsp; &nbsp;<a href="/app/auth.php?action=logout&reload=1" target=\"_blank\">Logout</a>';
?>
</div>
<div id="content">
