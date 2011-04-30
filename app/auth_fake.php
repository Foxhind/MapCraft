<?php
include '../lib/config.php';
session_start();

if (isset($_POST['nick'])) {
    // Получение id, если нету, то добавление в базу
    $_SESSION['token'] = 'no-token';
    $_SESSION['secret'] = 'no-secret';
    $_SESSION['osm_id'] = '0';
    $_SESSION['osm_user'] = $_POST['nick'];

    $pg_osm_user = pg_escape_string($_POST['nick']);
    $result = pg_query($connection, 'SELECT id FROM users WHERE nick=\''.$pg_osm_user.'\'');
    if (pg_num_rows($result) > 0)
        $_SESSION['user_id'] = pg_fetch_result($result, 0 ,0);
    else
        $_SESSION['user_id'] = pg_fetch_result(pg_query($connection, 'INSERT INTO users VALUES(\''.$pg_osm_user.'\', DEFAULT, DEFAULT) RETURNING id'), 0 ,0);

    Header("Location: /app/auth.php?action=success");
    exit();

} else {
?>
<html>
<head>
<title>Авторизация</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>
<body>

<form method="post">
Nick: <input type="text" name="nick">
<input type="submit">
</form>

</body>
</html>

<?php
}
?>
