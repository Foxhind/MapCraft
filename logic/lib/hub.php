<?php

function split_hub_message($str)
{
    $decoded = NULL;

    // Split and last 'json:' part if there is one
    $parts = split("!json:", $str, 2);
    if (isset($parts[1])) {
        $str = $parts[0];
        $decoded = json_decode($parts[1], true);
    }

    // Split parts separated using '!'
    $res = split('!', $str);

    if(!is_null($decoded)) {
       array_push($res, $decoded);
    }

    return $res;
}


function ids_to_from($pie_id, $session_id) {
    return array(
                 'pie_id' => $pie_id,
                 'session_id' => $session_id,
                 'nick' => "Anon_$session_id",
                 );
}

function handle_hub_message($str) {
    global $dispatcher;

    $args = split_hub_message($str);
    $cmd = array_shift($args);

    switch ($cmd) {
    case 'from':
        $type = array_shift($args);
        $from = ids_to_from(array_shift($args), array_shift($args));

        $json = array_shift($args);
        $json_cmd = $json[0];
        $json_arg = isset($json[1]) ? $json[1] : array();

        return $dispatcher->route($json_cmd, $type, $from, $json_arg);
    default:
        throw new Exception("Hub command '$cmd' is not implemented");
    }
}

?>