<?php

const OSM_OAUTH_AUTHORIZE_URL = 'https://www.openstreetmap.org/oauth2/authorize';
const OSM_OAUTH_TOKEN_URL = 'https://www.openstreetmap.org/oauth2/token';
const OSM_USER_DETAILS_URL = 'https://api.openstreetmap.org/api/0.6/user/details';

function osm_oauth_authorization_url($client_id, $redirect_uri, $scope, $state) {
    return OSM_OAUTH_AUTHORIZE_URL . '?' . http_build_query(array(
        'response_type' => 'code',
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'scope' => $scope,
        'state' => $state,
    ), '', '&', PHP_QUERY_RFC3986);
}

function osm_oauth_decode_token_response($status, $body) {
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['access_token'])) {
        throw new RuntimeException('OpenStreetMap did not return an access token.');
    }

    return $data;
}

function osm_oauth_post_form($url, $fields) {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for OpenStreetMap login.');
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_HTTPHEADER => array('Accept: application/json'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ));

    $body = curl_exec($curl);
    if ($body === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('OpenStreetMap token request failed: ' . $error);
    }

    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return osm_oauth_decode_token_response($status, $body);
}

function osm_oauth_fetch_user_details($access_token) {
    $curl = curl_init(OSM_USER_DETAILS_URL);
    curl_setopt_array($curl, array(
        CURLOPT_HTTPHEADER => array(
            'Accept: application/xml',
            'Authorization: Bearer ' . $access_token,
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ));

    $body = curl_exec($curl);
    if ($body === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('OpenStreetMap user-details request failed: ' . $error);
    }

    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return osm_oauth_decode_user_details_response($status, $body);
}

function osm_oauth_decode_user_details_response($status, $body) {
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('OpenStreetMap rejected the user-details request.');
    }

    $xml = simplexml_load_string($body);
    if ($xml === false || !isset($xml->user)) {
        throw new RuntimeException('OpenStreetMap returned invalid user details.');
    }

    $id = (string) $xml->user['id'];
    $display_name = (string) $xml->user['display_name'];
    if ($id === '' || $display_name === '') {
        throw new RuntimeException('OpenStreetMap user details are incomplete.');
    }

    $avatar_url = '';
    if (isset($xml->user->img['href'])) {
        $candidate = trim((string) $xml->user->img['href']);
        $scheme = parse_url($candidate, PHP_URL_SCHEME);

        if (filter_var($candidate, FILTER_VALIDATE_URL) && in_array($scheme, array('http', 'https'), true)) {
            $avatar_url = $candidate;
        }
    }

    return array(
        'id' => $id,
        'display_name' => $display_name,
        'avatar_url' => $avatar_url,
    );
}
?>
