<?php

// Просто пример простой обработки
function handle_msg($type, $from, $data) {
    validate_required($data, array('message') );

	$msg = chat_msg(array(
                          'from' => $from,
                          'message' => $data['message'],
                          ));

	$res = array();
    if ($data['type'] == 'public') {
        array_push($res, to_pie($from, $msg));
    } else {
        $to = find_session_by_nick($from, $data['target_nick']);
        array_push($res, to_session($from, $msg));
        array_push($res, to_session($to, $msg));
    }

    return $res;
}

?>