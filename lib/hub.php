<?php

class HubResult {
    public $data = array();
    protected $responded = false;

    // Api for handlers
    function respond($data)
    {
        $this->append(sprintf('respond!json:%s',
                              json_encode($data)));
        $this->responded = true;
    }

    function default_respond($res) {
        if ($this->responded) {
            return;
        }
        $this->respond($res);
    }

    function to_sender($msg)
    {
        $this->append(sprintf('to_sender!json:%s',
                              json_encode($msg)));
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

    function stat()
    {
        $this->append('stat!' . join('!', func_get_args()));
    }

    function stat_pie($from)
    {
        $args = func_get_args();
        $from = array_shift($args);

        $pie_id = gettype($from) == 'string' ? $from : $from->pieid;
        array_unshift($args, $pie_id);
        array_unshift($args, 'pie');

        $stat_func = array($this, 'stat');
        call_user_func_array($stat_func, $args);
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
    global $logger;

    $cb = 'handle_' . $cmd;

    // $logger->debug("Dispatching cmd: $cmd, type: $type");
    if ( !function_exists($cb) ){
        throw new Exception("Callback for '$cmd' is no defined yet");
    }

    $cb($type, $from, $data, $res);
}

function process_hub_message($str, $res) {
    global $channels;

    $args = split_hub_message($str);
    $cmd = array_shift($args);

    switch ($cmd) {
    case 'from':
        list($type, $pieid, $sesid, $json) = $args;

        $from = $channels->find($pieid, $sesid);
        $json_cmd = $json[0];
        $json_arg = isset($json[1]) ? $json[1] : array();

        dispatch($json_cmd, $type, $from, $json_arg, $res);

        // Add simple 'respond' if it missed and it's a sync call
        if ($type == 'sync') {
            $res->default_respond("ok");
        };
        break;
    case 'session_exit':
        list($pieid, $sesid, $reason) = $args;

        $from = $channels->find($pieid, $sesid);
        $data = array( 'reason' =>  $reason );

        dispatch('session_exit', 'async', $from, $data, $res);
        break;
    case 'session_join':
        list($pieid, $sesid) = $args;

        $from = $channels->find($pieid, $sesid);
        $data = array();

        dispatch('session_join', 'async', $from, $data, $res);
        break;
    case 'session_action':
        list($pieid, $sesid, $action) = $args;

        $from = $channels->find($pieid, $sesid);
        dispatch('session_act_' . $action, 'async', $from, array(), $res);
        break;
    case 'pie_exit':
        list($pieid) = $args;

        $data = array( 'pie_id' =>  $pieid );

        dispatch('pie_exit', 'async', null, $data, $res);
        break;
    case 'pie_create':
        list($pieid) = $args;

        $data = array( 'pie_id' =>  $pieid );

        dispatch('pie_create', 'sync', null, $data, $res);
        break;
    default:
        throw new Exception("Hub command '$cmd' is not implemented");
    }
}

?>