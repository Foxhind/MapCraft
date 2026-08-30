<?php
require __DIR__ . '/../lib/osm_oauth.php';
include __DIR__ . '/../lib/config.php';

ini_set('session.gc_maxlifetime', 7776000);
ini_set('session.cookie_lifetime', 7776000);
session_set_cookie_params(7776000);
session_start();

if (!isset($_GET['code'])) {
    http_response_code(400);
    die('Authorization code is missing.');
}

$stored_state = $_SESSION['osm_oauth_state'] ?? null;
$redirect_uri = $_SESSION['osm_oauth_redirect_uri'] ?? null;

if ($stored_state !== ($_GET['state'] ?? null)) {
    http_response_code(400);
    die('State mismatch.');
}

if (!$redirect_uri) {
    http_response_code(500);
    die('Redirect URI is not configured.');
}

try {
    $token_data = osm_oauth_post_form(
        'https://www.openstreetmap.org/oauth2/token',
        array(
            'grant_type' => 'authorization_code',
            'code' => $_GET['code'],
            'redirect_uri' => $redirect_uri,
            'client_id' => $osm_oauth_client_id,
            'client_secret' => $osm_oauth_client_secret,
        )
    );

    $access_token = $token_data['access_token'];
    $user_details = osm_oauth_fetch_user_details($access_token);

    $_SESSION['osm_id'] = $user_details['id'];
    $_SESSION['osm_user'] = $user_details['display_name'];

    $pg_osm_user = pg_escape_string($_SESSION['osm_user']);
    $result = pg_query($connection, 'SELECT id FROM users WHERE nick=\'' . $pg_osm_user . '\'');
    if (pg_num_rows($result) > 0) {
        $_SESSION['user_id'] = pg_fetch_result($result, 0, 0);
    } else {
        $_SESSION['user_id'] = pg_fetch_result(
            pg_query($connection, 'INSERT INTO users VALUES(\'' . $pg_osm_user . '\', DEFAULT, DEFAULT) RETURNING id'),
            0,
            0
        );
    }

    header('Location: /app/auth.php?action=success');
    exit;

} catch (RuntimeException $e) {
    http_response_code(500);
    die('OpenStreetMap login failed: ' . $e->getMessage());
}
?>
