<?php

function handle_session_exit($type, $from, $data) {
	$msg = info_msg(array(
                          'message' => 'User ' . $from['nick'] . ' exited: ' . $data['reason'],
                          ));

	$res = array();
    array_push($res, to_pie($from, $msg));
    return $res;
}

function handle_pie_exit($type, $from, $data) {
	$res = array();
    return $res;
}
