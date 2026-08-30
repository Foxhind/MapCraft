<?php
require __DIR__ . '/../lib/osm_oauth.php';
include __DIR__ . '/../lib/config.php';

$redirect_uri = $osm_oauth_redirect_uri;
$scope = $osm_oauth_scope;
$client_id = $osm_oauth_client_id;

if (!$client_id || !$redirect_uri) {
    http_response_code(500);
    die('OSM OAuth 2 client_id or redirect_uri is not configured.');
}

$state = bin2hex(random_bytes(16));
$_SESSION['osm_oauth_state'] = $state;
$_SESSION['osm_oauth_redirect_uri'] = $redirect_uri;

header('Location: ' . osm_oauth_authorization_url($client_id, $redirect_uri, $scope, $state));
exit;
?>
