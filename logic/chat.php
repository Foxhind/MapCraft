<?php

// Просто пример простой обработки
function send_message($cmd, $type, $from, $data) {
	$msg = chat_msg(array(
                          'from' => $from,
                          'message' => $data['message'],
                          ));

	$res = array(respond($msg));

    if ($data['type'] == 'public') {
        array_push($res, to_pie($from, $msg));
    } else {
        $to = nick_to_addr($data['target_nick']);
        array_push($res, to_user($to, $msg));
    }

    return $res;
}
register_callback('msg', 'send_message');

?>