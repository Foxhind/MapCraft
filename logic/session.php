<?php

function handle_user_join($type, $from, $data, $res) {
    // Add session

    $msg = info_msg($from->nick() . ' has joined');
    $res->to_pie($from, $msg);
}


function handle_user_exit($type, $from, $data, $res) {
    // Remove session

    $msg = info_msg($from->nick() . ' has quit: ' . $data['reason']);
    $res->to_pie($from, $msg);
}

function handle_pie_exit($type, $from, $data, $res) {
    validate_required($data, 'id');
    validate_id($data, 'id');
    clear_pie($data[$id]);
}

function handle_pie_create($type, $from, $data, $res) {
    validate_required($data, 'id');
    validate_id($data, 'id');
    clear_pie($data[$id]);
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

    $result = pg_query($connection, 'DELETE from chat_members WHERE pie = \''.pg_escape_string($pie_id).'\'');
}

?>
