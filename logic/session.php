<?php

function handle_session_exit($type, $from, $data, $res) {
    $txt = $from->nick() . ' has quit: ' . $data['reason'];
	$msg = info_msg(array( 'message' => $txt));
    $res->to_pie($from, $msg);
}

function handle_pie_exit($type, $from, $data, $res) {
    // Nothing at now
}

function handle_whoami($type, $from, $data, $res) {
    $info = array( "role" => $from->role(),
                   "nick" => $from->nick() );
    $res->to_session($from, array('youare', $info));
}

?>