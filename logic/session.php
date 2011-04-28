<?php

function handle_user_join($type, $from, $data, $res) {
    global $connection;

    $pie_id = $from->pieid;
    $user_id = $from->user_id();

    // Check is used already joined
    $result = pg_query($connection, 'SELECT session FROM  chat_members '
                       . 'WHERE pie = ' . $pie_id . ' and member = ' . $user_id);
    $is_new = pg_num_rows($result) == 0;

    // Add session
    pg_query($connection, 'INSERT INTO chat_members '
             . 'VALUES (' . join(", ", array($pie_id, $user_id, '\'' . $from->sesid . '\'')) . ')' );

    if ($is_new) {
        $msg = info_msg($from->nick() . ' has joined');
        $res->to_pie($from, $msg);
    }
}


function handle_user_exit($type, $from, $data, $res) {
    global $connection;

    $pie_id = $from->pieid;
    $user_id = $from->user_id();

    // Remove session
    pg_query($connection, 'DELETE from chat_members '
             . 'WHERE pie = ' . $pie_id . ' and member = ' . $user_id . ' and session = \'' . $from->sesid . '\'');

    // Check is this is a last session
    $result = pg_query($connection, 'SELECT session FROM chat_members '
                       . 'WHERE pie = ' . $pie_id . ' and member = ' . $user_id . ' and session = \'' . $from->sesid . '\'');
    $is_last = pg_num_rows($result) == 0;

    if ($is_last) {
        $msg = info_msg($from->nick() . ' has quit: ' . $data['reason']);
        $res->to_pie($from, $msg);
    }
}

function handle_pie_exit($type, $from, $data, $res) {
    clear_pie($data['pie_id']);
}

// Special sync call, respond with "ok" if pie allowed to be created
function handle_pie_create($type, $from, $data, $res) {
    clear_pie($data['pie_id']);
    $res->respond("ok");
}

function handle_whoami($type, $from, $data, $res) {
    $info = array( "role" => $from->role(),
                   "nick" => $from->nick() );
    $res->to_session($from, array('youare', $info));
}

// ------------
// Helpers
// -----------

function clear_pie($pie_id) {
    global $connection;

    $result = pg_query($connection, 'DELETE from chat_members WHERE pie = ' . $pie_id);
}

?>
