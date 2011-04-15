<?php

function handle_session_exit($type, $from, $data, $res) {
    $txt = 'User ' . $from->nick . ' exited: ' . $data['reason'];
	$msg = info_msg(array( 'message' => $txt));
    $res->to_pie($from, $msg);
}

function handle_pie_exit($type, $from, $data, $res) {
    // Nothing at now
}

?>