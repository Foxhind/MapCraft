<?php

// Просто пример простой обработки
function send_chat_message($cmd, $type, $from, $data) {
	$msg = chat_msg(array(
                          'from' => $from,
                          'message' => $data->message,
                          ));

	$res = array(respond($msg));

    if ($data->type == 'public') {
        array_push($res, to_pie($from, $msg));
    } else {
        $to = find_session_by_nick($from, $data['target_nick']);
        array_push($res, to_session($to, $msg));
    }

    return $res;
}
$dispatcher->register('msg', 'send_chat_message');

?>