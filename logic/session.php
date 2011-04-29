<?php

function handle_session_join($type, $from, $data, $res) {
    $user_id = $from->user_id();
    $is_new = true;

    if ($user_id == 0) {
        _increase_anons($res, $from);
    } else {
        $is_new = _add_chat_member($res, $from, $user_id);
    }

    if ($is_new) {
        $msg = info_msg($from->nick() . ' has joined');
        $res->to_pie($from, $msg);
    }
}


function handle_session_exit($type, $from, $data, $res) {
    $user_id = $from->user_id();
    $is_last = true;

    if ($user_id == 0) {
        _decrease_anons($res, $from);
    } else {
        $is_last = _remove_chat_member($res, $from, $user_id);
    }

    if ($is_last) {
        $msg = info_msg($from->nick() . ' has quit: ' . $data['reason']);
        $res->to_pie($from, $msg);
    }
}

function handle_session_act_login($type, $from, $data, $res) {

    // Make sure that we was anonymous
    if ( _find_member_user_id($from->pieid, $from->sesid) > 0 )
        throw new Exception("Login called but the session was already logged in");

    // Make sure that we are actually logged in
    $user_id = $from->user_id();
    if( $user_id == 0 )
        throw new Exception("Login called for session that is anonymous");

    _decrease_anons($res, $from);
    _add_chat_member($res, $from, $user_id);
    _youare($res, $from);

    $msg = info_msg('%s has logged in as %s', $from->anon_nick(), $from->nick());
    $res->to_pie($from, $msg);
}

function handle_session_act_logout($type, $from, $data, $res) {

    // Make sure that we was registered
    $user_id = _find_member_user_id($from->pieid, $from->sesid);
    if ( $user_id == 0 )
        throw new Exception("Logout called but the session was already logged out");

    // Make sure that we are actually anonymous
    if( $from->user_id() != 0 )
        throw new Exception("Logout called for session that is registered yet");

    _increase_anons($res, $from);
    _remove_chat_member($res, $from, $user_id);
    _youare($res, $from);

    $user = _get_user_info($user_id);
    $nick = $user['nick'];
    $msg = info_msg('%s has logged out, he is %s now', $nick, $from->anon_nick());
    $res->to_pie($from, $msg);
}


function handle_pie_exit($type, $from, $data, $res) {
    _clear_pie($data['pie_id']);
}

// Special sync call, respond with "ok" if pie allowed to be created
function handle_pie_create($type, $from, $data, $res) {
    _clear_pie($data['pie_id']);
    $res->respond("ok");
}

function handle_whoami($type, $from, $data, $res) {
    _youare($res, $from);
}

// ------------
// Helpers
// -----------

function _clear_pie($pie_id) {
    global $connection;

    $result = pg_query($connection, 'DELETE from chat_members WHERE pie = ' . $pie_id);
    pg_query($connection, 'UPDATE pies SET anons=0 WHERE id = ' . $pie_id);
}

function _update_anons_to_pie($res, $from) {
    global $connection;

    $result = pg_query($connection, 'SELECT anons FROM pies WHERE id = ' . $from->pieid);
    $row = pg_fetch_assoc($result);
    $count = $row['anons'];
    $res->to_pie($from, array('anons_update', array('count' => $count)));
}

function _increase_anons($res, $from) {
    global $connection;
    pg_query($connection, 'UPDATE pies SET anons=anons+1 WHERE id = ' . $from->pieid);
    _update_anons_to_pie($res, $from);
}

function _decrease_anons($res, $from) {
    global $connection;
    pg_query($connection, 'UPDATE pies SET anons=anons-1 WHERE id = ' . $from->pieid);
    _update_anons_to_pie($res, $from);
}


function _youare($res, $from) {
    $info = array( "role" => $from->role(),
                   "nick" => $from->nick() );
    $res->to_session($from, array('youare', $info));

}

function _find_member_user_id($pie_id, $ses_id) {
    global $connection;

    $result = pg_query($connection, 'SELECT member FROM  chat_members '
                       . 'WHERE pie = ' . $pie_id . ' and session = \'' . $ses_id .'\'');
    return (int) pg_fetch_result($result, 0, "member");
}

function _add_chat_member($res, $from, $user_id) {
    global $connection;

    // Check is used already joined
    $result = pg_query($connection, 'SELECT session FROM  chat_members '
                       . 'WHERE pie = ' . $from->pieid . ' and member = ' . $user_id);
    $is_new = pg_num_rows($result) == 0;

    // Add session
    pg_query($connection, 'INSERT INTO chat_members '
             . 'VALUES (' . join(", ", array($from->pieid, $user_id, '\'' . $from->sesid . '\'')) . ')' );

    return $is_new;
}

function _remove_chat_member($res, $from, $user_id) {
    global $connection;

    // Remove session
    pg_query($connection, 'DELETE from chat_members '
             . 'WHERE pie = ' . $from->pieid . ' and session = \'' . $from->sesid . '\'');

    // Check is this is a last session
    $result = pg_query($connection, 'SELECT session FROM chat_members '
                       . 'WHERE pie = ' . $from->pieid . ' and member = ' . $user_id);
    $is_last = pg_num_rows($result) == 0;

    return $is_last;
}



?>
