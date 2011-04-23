<html>
<head>
<title>Авторизация</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>
<body>

<?php
include '../lib/config.php';

if (isset($_POST['nick']))
{
	echo "logged in";
	echo "<script>window.opener.location.reload(true); window.close();</script>";
	exit;
}

?>

<form method="post">
Nick: <input type="text" name="nick">
<input type="submit">
</form>
</body>
</html>