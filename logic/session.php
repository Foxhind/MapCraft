<?php

function handle_session_exit($cmd, $type, $from, $data) {
	$msg = info_msg(array(
                          'message' => 'User ' . $from['nick'] . ' exited: ' . $data['reason'],
                          ));

	$res = array();
    array_push($res, to_pie($from, $msg));
    return $res;
}
$dispatcher->register('session_exit', 'handle_session_exit');


function handle_pie_exit($cmd, $type, $from, $data) {
	$res = array();
    return $res;
}
$dispatcher->register('pie_exit', 'handle_pie_exit');
