<html>
<head>
<title>Ня</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>
<body>
<?php
$req_url = 'http://www.openstreetmap.org/oauth/request_token';     // OSM Request Token URL
$authurl = 'http://www.openstreetmap.org/oauth/authorize';         // OSM Authorize URL
$acc_url = 'http://www.openstreetmap.org/oauth/access_token';      // OSM Access Token URL
$api_url = 'http://api.openstreetmap.org/api/0.6/';                // OSM API URL

$conskey = '5vecf0vlYdjvFd0ZPdXt3w';
$conssec = 'wzb8A7oX8gq9lRyCALFyQs2ZoIYgLCFdFW2YKeJvHKQ';

session_start();
if(isset($_GET['oauth_token']) && isset($_SESSION['secret']))
{
try {
       $oauth = new OAuth($conskey, $conssec, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
       $oauth->enableDebug();

       $oauth->setToken($_GET['oauth_token'], $_SESSION['secret']);
       $access_token_info = $oauth->getAccessToken($acc_url);

       $_SESSION['token'] = strval($access_token_info['oauth_token']);
       $_SESSION['secret'] = strval($access_token_info['oauth_token_secret']);

       $oauth->setToken($_SESSION['token'], $_SESSION['secret']);

       /// получаем данные пользователя через /api/0.6/user/details
       $oauth->fetch($api_url."user/details");
       $user_details = $oauth->getLastResponse();

       // парсим ответ, получаем имя осмопользователя и его id
       $xml = simplexml_load_string($user_details);       
       $_SESSION['osm_id'] = strval ($xml->user['id']);
       $_SESSION['osm_user'] = strval($xml->user['display_name']);

       // вывожу все это
       echo "Ура, ".$_SESSION['osm_user'].", ты успешно залогинился! :3<br/>";
       echo "OSM id: ".$_SESSION['osm_id']."<br/>";
       echo "<script>window.opener.location.reload(true); window.close();</script>";

       /// тут мы можем создать юзера в своей базе и сохранить его osm_id, osm_user, token? и secret?.
} catch(OAuthException $E) {
       print_r($E);
}
}else
{
       if(!isset($_SESSION['secret'])) echo "Нет секрета!<br/><br/>";
       if(!isset($_GET['oauth_token'])) echo "Нет токена!<br/><br/>";
}
?>
</body>