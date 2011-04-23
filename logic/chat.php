<?php

function handle_chat($type, $from, $data, $res) {
    global $connection;

    validate_required($data, array('message') );

    $text = htmlspecialchars(trim( preg_replace('/\s+/', ' ', $data['message']) ));

    $result = pg_query($connection, 'INSERT INTO chat VALUES(DEFAULT,'.$from->pieid.','.$from->user_id().',DEFAULT,\''.pg_escape_string($text).'\',DEFAULT) RETURNING id, timestamp');
    $row = pg_fetch_array($result);

    $msg = chat_msg(array( 'from' => $from,
                           'message' => $text,
                           'id' => $row['id'],
                           'date' => $row['timestamp'] ));

    if ($data['type'] == 'public') {
        $res->to_pie($from, $msg);
    } else {
        $to = find_session_by_nick($from, $data['target_nick']);
        $res->to_session($from, $msg);
        $res->to_session($to, $msg);
    }
}

    // Temporary
function handle_get_chat_history($type, $from, $data, $res) {
    global $connection;

    $result = pg_query($connection, 'SELECT chat.id, users.nick, message, timestamp FROM chat JOIN users ON users.id = author WHERE pie = '.$from->pieid.' ORDER BY timestamp DESC LIMIT 10');
    $messages = array();
    while ($row = pg_fetch_array($result)) {
        $messages[] = $row;
    }
    array_reverse($messages);
    foreach ($messages as $row) {
        $msg = array(   'class' => 'chat',
                        'author' => $row['nick'],
                        'message' => $row['message'],
                        'id' => $row['id'],
                        'date' => $row['timestamp'],
                        'history' => true );
        $res->to_sender(array('chat', $msg));
    }
}

?>
