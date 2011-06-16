<?php

function handle_get_piece_comments($type, $from, $data, $res)
{
    global $connection;

    validate_required($data, 'piece_id');
    $piece_id = $data['piece_id'];

    $result = pg_query($connection, 'SELECT users.nick, text, type, timestamp FROM pieces_comments JOIN users ON users.id = author WHERE piece = '.$piece_id.' ORDER BY timestamp DESC LIMIT 100');

    if (pg_num_rows($result) == 0) {
        $res->to_sender( array('no_comments', array()) );
    }
    else {
        while ($row = pg_fetch_array($result)) {
            $res->to_sender( array('piece_comment', array(
                'piece_id' => $piece_id,
                'author' => $row['nick'],
                'message' => $row['text'],
                'type' => $row['type'],
                'date' => $row['timestamp']
            )) );
        }
    }
}

function handle_piece_reserve($type, $from, $data, $res)
{
    global $connection;
    $from->need_level("member");

    $piece_id = $data['piece_id'];
    $user_id = $from->user_id();
    $nick = $from->nick();

    $result = pg_query($connection, 'SELECT owner FROM pieces WHERE id = '.$piece_id);
    if (pg_num_rows($result) == 0)
        throw new Exception("This piece doesn't exist.");
    if (!pg_field_is_null($result, 0, 0)) {
        $owner = pg_fetch_result($result, 0 ,0);
        if ($owner !== $from->user_id())
            throw new Exception("This slice is already owned by $owner.");
        else
            throw new Exception("This slice is yours already.");
    }

    $result = pg_query($connection, 'UPDATE pieces SET owner = '.$user_id.' WHERE id = '.$piece_id);

    //TODO: format piece info hash
    update_kml($from->pieid);

    $pinfo = array( "piece_id" => $piece_id,
                    "owner" => $nick );
    $res->to_pie($from, array('piece_owner', $pinfo));

    // Updating owner
    $result = pg_query($connection, 'SELECT id FROM pieces WHERE owner = '.$user_id . ' and pie = ' . $from->pieid );
    $piece_ids = pg_fetch_all_columns($result, 0);
    $res->to_pie($from, array( 'user_update', array('current_nick' => $nick,
                                                    'reserved' => $piece_ids) ));
    $res->to_pie($from, info_msg("%s has reserved slice #%s.", $nick, $piece_id));
    _add_piece_info_log($res, $from, $piece_id, "Slice has been reserved");
}

function handle_piece_free($type, $from, $data, $res)
{
    global $connection;
    $from->need_level("member");

    $piece_id = $data['piece_id'];
    $nick = $from->nick();

    $result = pg_query($connection, 'SELECT owner FROM pieces WHERE id = '.$piece_id);
    if (pg_num_rows($result) == 0)
        throw new Exception("This slice doesn't exist.");
    if (pg_field_is_null($result, 0, 0)) {
        throw new Exception("This slice isn't owned by you.");
    }
    else {
        $owner = pg_fetch_result($result, 0 ,0);
        if ($owner !== $from->user_id())
            throw new Exception("This is not your slice");
    }

    $result = pg_query($connection, 'UPDATE pieces SET owner = NULL WHERE id = '.$piece_id);

    //TODO: format piece info hash
    update_kml($from->pieid);

    $pinfo = array( "piece_id" => $piece_id,
                    "owner" => "" );
    $res->to_pie($from, array('piece_owner', $pinfo));

    // Updating owner
    $result = pg_query($connection, 'SELECT id FROM pieces WHERE owner = '.$from->user_id() . ' and pie = ' . $from->pieid );
    $piece_ids = pg_fetch_all_columns($result, 0);
    if ($piece_ids === false) $piece_ids = array();
    $res->to_pie($from, array( 'user_update', array('current_nick' => $nick,
                                                    'reserved' => $piece_ids) ));

    $res->to_pie($from, info_msg("%s has freed slice #%s.", $nick, $piece_id));
    _add_piece_info_log($res, $from, $piece_id, "Slice has been freed");
}

function handle_piece_state($type, $from, $data, $res)
{
    global $connection;
    $from->need_level("member"); // ensure we at least member

    $piece_id = $data['piece_id'];
    $pieid = $from->pieid;
    $state = $data['state'];

    $result = pg_query($connection, 'SELECT owner FROM pieces WHERE id = '.$piece_id);
    if (pg_num_rows($result) == 0)
        throw new Exception("This slice doesn't exist.");

    $owner = pg_fetch_result($result, 0 ,0);
    if ($owner !== $from->user_id())
        throw new Exception("This is not your slice");

    $result = pg_query($connection, 'UPDATE pieces SET state = '.$state.' WHERE id = '.$piece_id);

    //TODO: format piece info hash
    update_kml($from->pieid);

    $pinfo = array( "piece_id" => $piece_id,
                    "state" => $state );
    $res->to_pie($from, array('piece_state', $pinfo));
    $res->to_pie($from, info_msg("%s has set state for #%s to %s/9", $from->nick(), $piece_id, $state));
    _update_piece_progress($res, $from);
    _add_piece_info_log($res, $from, $piece_id, "New state: " . $state . "/9");
}

function handle_piece_comment($type, $from, $data, $res)
{
    global $connection;

    $from->need_level('member');
    validate_required($data, 'piece_id', 'comment');

    $piece_id = $data['piece_id'];
    $comment = htmlspecialchars(trim( preg_replace('/\s+/', ' ', $data['comment']) ));

    $result = pg_query($connection, 'INSERT INTO pieces_comments VALUES(DEFAULT,'.$piece_id.','.$from->user_id().',\''.pg_escape_string($comment).'\',DEFAULT, \'comment\') RETURNING timestamp');
    $timestamp = pg_fetch_result($result, 0 ,0);

    $res->to_sender(info_msg( 'Comment added to piece #'.$piece_id.'.', $from->nick() ));
    $res->to_pie($from, array('piece_comment', array(
        'piece_id' => $piece_id,
        'author' => $from->nick(),
        'message' => $comment,
        'type' => 'comment',
        'date' => $timestamp
    )) );
}

function handle_piece_progress($type, $from, $data, $res)
{
    _update_piece_progress($res, $from);
}

// -------------------------
// Helpers
// -------------------------

function _update_piece_progress($res, $from)
{
    global $connection;

    $result = pg_query($connection, "SELECT state FROM pieces WHERE pie = " . $from->pieid);
    $states = pg_fetch_all_columns($result, 0);
    $progress = array(0,0,0);
    foreach ($states as $st) {
        switch ($st) {
        case 0:  $progress[0] ++; break;
        case 9:  $progress[2] ++; break;
        default: $progress[1] ++;
        }
    }

    $event = array('piece_progress', array('progress' => $progress));
    $res->to_pie($from, $event);
}

function _add_piece_info_log($res, $from, $piece_id, $msg) {
    global $connection;

    $values = array('DEFAULT', $piece_id, $from->user_id(), '\''.pg_escape_string($msg).'\'', 'DEFAULT', '\'info\'');
    $result = pg_query($connection, 'INSERT INTO pieces_comments VALUES(' . join(', ', $values) . ') RETURNING timestamp');
    $timestamp = pg_fetch_result($result, 0 ,0);

    $res->to_pie($from, array('piece_comment', array(
        'piece_id' => $piece_id,
        'author' => $from->nick(),
        'message' => $msg,
        'type' => 'info',
        'date' => $timestamp
    )) );
}

?>
