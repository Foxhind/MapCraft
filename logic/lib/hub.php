<?php

class HubResult {
    protected $data = array();
    protected $responded = false;

    // Api for handlers
    function respond($msg)
    {
        $this->append(sprintf('respond!json:%s',
                              json_encode($msg)));
        $this->responded = true;
    }

    function default_respond() {
        if ($this->responded) {
            return;
        }
        $this->respond("ok");
    }

    function to_session($from, $msg)
    {
        $this->append(sprintf('to_session!%s!%s!json:%s',
                              $from->pieid,
                              $from->sesid,
                              json_encode($msg)));
    }

    function to_pie($from, $msg)
    {
        $this->append(sprintf('to_pie!%s!json:%s',
                              $from->pieid,
                              json_encode($msg)));
    }

    function to_all($msg)
    {
        $this->append(sprintf('to_all!json:%s',
                              json_encode($msg)));
    }

    // Internal hub helpers
    function append($str)
    {
        array_push($this->data, $str);
    }

    function output()
    {
        foreach ($this->data as $line) {
            echo $line, "\n";
        }
        echo "EOR\n";  // End of result
    }
}

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


function _get_head($str) {
    $parts = split("!", $str, 2);
    return $parts[0];
}

function dispatch($cmd, $type, $from, $data, $res) {
    $cb = 'handle_' . $cmd;

    if ( !function_exists($cb) ){
        throw new Exception("Callback for '$cmd' is no defined yet");
    }

    return $cb($type, $from, $data, $res);
}

$session_started = false;
function init_session($sesid) {
    global $session_started;
    if($session_started) {
        session_write_close();
    }
    session_id($sesid);
    @session_start();
    $session_started = true;
}

function process_hub_message($str, $res) {
    global $channels;

    $args = split_hub_message($str);
    $cmd = array_shift($args);

    switch ($cmd) {
    case 'from':
        list($type, $pieid, $sesid, $json) = $args;

        init_session($sesid);
        $from = $channels->find($pieid, $sesid);

        $json_cmd = $json[0];
        $json_arg = isset($json[1]) ? $json[1] : array();

        $res = dispatch($json_cmd, $type, $from, $json_arg, $res);

        // Add simple 'respond' if it missed and it's a sync call
        if ($type == 'sync') {
            $res->default_respond();
        };

        return $res;
    case 'session_exit':
        list($pieid, $sesid, $reason) = $args;

        init_session($sesid);
        $from = $channels->find($pieid, $sesid);

        $data = array( 'reason' =>  $reason );

        $res = dispatch('session_exit', 'async', $from, $data, $res);
        return $res;
    case 'pie_exit':
        list($pieid) = $args;

        $data = array( 'pie_id' =>  $pieid );

        $res = dispatch('pie_exit', 'async', null, $data, $res);
        return $res;
    default:
        throw new Exception("Hub command '$cmd' is not implemented");
    }
}

?>