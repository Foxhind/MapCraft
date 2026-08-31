<?php

require __DIR__ . '/../lib/osm_oauth.php';

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$url = osm_oauth_authorization_url(
    'client id',
    'https://mapcraft.nanodesu.ru/app/oauth_callback.php',
    'openid read_prefs',
    'state value'
);
parse_str(parse_url($url, PHP_URL_QUERY), $query);
assert_same('code', $query['response_type'], 'Authorization flow must request a code.');
assert_same('client id', $query['client_id'], 'Client ID must be preserved.');
assert_same('state value', $query['state'], 'State must be preserved.');
assert_same('openid read_prefs', $query['scope'], 'Required scopes must be preserved.');

$token = osm_oauth_decode_token_response(200, '{"access_token":"token","token_type":"Bearer"}');
assert_same('token', $token['access_token'], 'Token response must expose the access token.');

try {
    osm_oauth_decode_token_response(401, '{"error":"invalid_client"}');
    fwrite(STDERR, "Invalid token response must fail.\n");
    exit(1);
} catch (RuntimeException $exception) {
}

$user = osm_oauth_decode_user_details_response(
    200,
    '<osm><user id="42" display_name="mapper"><img href="https://www.openstreetmap.org/avatar.jpg" /></user></osm>'
);
assert_same('42', $user['id'], 'User-details response must expose the OSM id.');
assert_same('mapper', $user['display_name'], 'User-details response must expose the OSM display name.');
assert_same('https://www.openstreetmap.org/avatar.jpg', $user['avatar_url'], 'User-details response must expose the avatar URL.');

$userWithoutAvatar = osm_oauth_decode_user_details_response(
    200,
    '<osm><user id="42" display_name="mapper" /></osm>'
);
assert_same('', $userWithoutAvatar['avatar_url'], 'Missing avatar image must produce an empty URL.');

echo "OSM OAuth tests passed.\n";
?>
