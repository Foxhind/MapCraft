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
<style>
* { padding: 0; margin: 0; font-family: Myriad Pro,Arial; }
a { color: inherit; }
body {
    padding: 50px 15% 0 15%;
    min-width: 224px;
}
#content {
    margin-top: 15px;
    min-height: 100px;
    overflow: auto;
}
#login {
    position: absolute;
    right: 5%;
    top: 0;
    background: #637d8c;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.6);
    padding: 5px 15px 5px 15px;
    color: #fff;
}
div#logo {
    position: absolute;
    left: 15%;
    top: 50px;
}
div#logo #color {
    height: 100px;
    width: 100px;
    background: #ccc;
    margin-left: -150px;
}
div#logo #mask {
    height: 100px;
    width: 100px;
    background: url('img/logot.png') no-repeat;
}
footer {
    margin-top: 15px;
    width: 100%;
    text-align: center;
    font-size: 80%;
    color: #aaa;
}
form fieldset {
    border: none;
}
form .row {
    width: 100%;
    overflow: hidden;
    padding-bottom: 20px;
}
form .row div {
    float:left;
    display:inline;
    width:49.9%;
}
form label {
    display: block;
}
form img {
    vertical-align: bottom;
}
form input {
    width: 80%;
}
form textarea {
    width: 80%;
    min-height: 100px;
}
form .btn {
    width: auto;
}
form em {
    color:#F00;
}
header {
    font-size: 150%;
    text-align: center;
}
#olmap {
    width: 100%;
    height: 500px;
}
#pageheader {
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.6);
    color: #fff;
    padding: 10px;
    font-weight: bold;
    margin-bottom: 10px;
}
#panel li {
    display: inline-block;
    list-style-type: none;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.6);
    padding: 15px 15px 5px 15px;
    color: #fff;
}
#panel li.c1 {
    background: #637d8c;
}
#panel li.c2 {
    background: #6d926c;
}
#panel li.c3 {
    background: #92836c;
}
#panel li.current {
    padding: 25px 15px 5px 15px;
}
small {
    font-size: 80%;
    color: #aaa;
}
table.list {
    border-spacing: 0;
    width: 100%;
}
table.list th {
    padding: 10px;
    text-align: left;
}
table.list td {
    padding: 10px;
}
table.list tr:nth-child(odd) {
    background: #ecf5eb;
}
table.list tr:nth-child(even) {
    background: #e2f1e2;
}
table.list tr:first-child {
    background: #6d926c;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.6);
    color: #fff;
}
</style>
</head>

<body>
<header>MapCraft</header>
<div id="panel"><nav><ul>
<?php
echo '<li class="c1'.($_GET['page']=='map'?' current':'').'"><a href="map">Карта</a></li>';
echo '<li class="c2'.($_GET['page']=='list'?' current':'').'"><a href="list">Список</a></li>';
echo '<li class="c3'.($_GET['page']=='create'?' current':'').'"><a href="create">Новый пирог</a></li>';
?>
</ul></nav></div>
<div id="login">
<?php
if (isset($_SESSION['osm_user']))
    echo $_SESSION['osm_user'].'&nbsp; &nbsp;<a href="?logout">Выход</a>';
else
    echo '<a href="auth.php" target="_blank">Вход</a>';
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