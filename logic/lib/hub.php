<?php

function split_hub_message($str)
{
    $decoded = NULL;

    // Split and last 'json:' part if there is one
    list($before, $after) = split("!json:", $str, 2);
    if ($after) {
        $str = $before;
        $decoded = json_decode($after);
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

        return $dispatcher->route($json[0], $type, $from, $json[1]);
    case 'session_timeout':
        // TODO
        break;
    case 'pie_timeout':
        // TODO
        break;
    default:
        throw new Exception("Hub command '$cmd' is not implemented");
    }
}

?>