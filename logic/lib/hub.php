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
                 'nick' => "Anon_$session_id",  // TODO! load data from BD
                 );
}

function _get_head($str) {
    $parts = split("!", $str, 2);
    return $parts[0];
}

function dispatch($cmd, $type, $from, $data) {
    $cb = 'handle_' . $cmd;

    if ( !function_exists($cb) ){
        throw new Exception("Callback for '$cmd' is no defined yet");
    }

    return $cb($type, $from, $data);
}

function process_hub_message($str) {
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

        $res = dispatch($json_cmd, $type, $from, $json_arg);

        // Add simple 'respond' if it missed and it's a sync call
        if ($type == 'sync') {
            $heads = array_map('_get_head', $res);
            if(!in_array('respond', $heads)) {
                array_push($res, respond("ok"));
            }
        };

        return $res;
    case 'session_exit':
        $from = ids_to_from(array_shift($args), array_shift($args));
        $data = array( 'reason' =>  array_shift($args) );

        $res = dispatch('session_exit', 'async', $from, $data);
        return $res;
    case 'pie_exit':
        $data = array( 'pie_id' =>  array_shift($args) );

        $res = dispatch('pie_exit', 'async', null, $data);
        return $res;
    default:
        throw new Exception("Hub command '$cmd' is not implemented");
    }
}

?>