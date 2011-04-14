<?php

// Просто пример простой обработки
function handle_msg($type, $from, $data, $res) {
    validate_required($data, array('message') );

    $txt =  $data['message'];
    // TODO: validate text

	$msg = chat_msg(array( 'from' => $from,
                           'message' => $txt ));

    // TODO: save to the base

    if ($data['type'] == 'public') {
        $res->to_pie($from, $msg);
    } else {
        $to = find_session_by_nick($from, $data['target_nick']);
        $res->to_session($from, $msg);
        $res->to_session($to, $msg);
    }
}

?>