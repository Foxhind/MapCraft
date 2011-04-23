<?php

function handle_get_piece_comments($type, $from, $data, $res)
{
    global $connection;

    validate_required($data, array('piece_id'));
    $piece_id = $data['piece_id'];

    $result = pg_query($connection, 'SELECT users.nick, text, timestamp FROM pieces_comments JOIN users ON users.id = author WHERE piece = '.$piece_id.' ORDER BY timestamp DESC LIMIT 100');

    if (pg_num_rows($result) == 0) {
        $res->to_sender( array('no_comments', array()) );
    }
    else {
        while ($row = pg_fetch_array($result)) {
            $res->to_sender( array('piece_comment', array(
                'piece_id' => $piece_id,
                'author' => $row['nick'],
                'message' => $row['text'],
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
        throw new Exception("This piece isn't exists.");
    if (!pg_field_is_null($result, 0, 0)) {
        $owner = pg_fetch_result($result, 0 ,0);
        if ($owner !== $from->user_id())
            throw new Exception("This piece is already owned by $owner.");
        else
            throw new Exception("This piece is your already.");
    }

    $result = pg_query($connection, 'UPDATE pieces SET owner = '.$user_id.' WHERE id = '.$piece_id);

    //TODO: format piece info hash
    update_kml($from->pieid);

    $pinfo = array( "piece_id" => $piece_id,
                    "owner" => $nick );
    $res->to_pie($from, array('piece_owner', $pinfo));

    // Updating owner
    $result = pg_query($connection, 'SELECT id FROM pieces WHERE owner = '.$user_id);
    $piece_ids = pg_fetch_all_columns($result, 0);
    $res->to_pie($from, array( 'user_update', array('current_nick' => $nick,
                                                    'reserved' => $piece_ids) ));
    $res->to_pie($from, info_msg("User %s has reserved piece #%s.", $nick, $piece_id));
}

function handle_piece_free($type, $from, $data, $res)
{
    global $connection;
    $from->need_level("member");

    $piece_id = $data['piece_id'];
    $nick = $from->nick();

    $result = pg_query($connection, 'SELECT owner FROM pieces WHERE id = '.$piece_id);
    if (pg_num_rows($result) == 0)
        throw new Exception("This piece isn't exists.");
    if (pg_field_is_null($result, 0, 0)) {
        throw new Exception("This piece isn't owned by you.");
    }
    else {
        $owner = pg_fetch_result($result, 0 ,0);
        if ($owner !== $from->user_id())
            throw new Exception("This piece isn't owned by you.");
    }

    $result = pg_query($connection, 'UPDATE pieces SET owner = NULL WHERE id = '.$piece_id);

    //TODO: format piece info hash
    update_kml($from->pieid);

    $pinfo = array( "piece_id" => $piece_id,
                    "owner" => "" );
    $res->to_pie($from, array('piece_owner', $pinfo));

    // Updating owner
    $result = pg_query($connection, 'SELECT id FROM pieces WHERE owner = '.$from->user_id());
    $piece_ids = pg_fetch_all_columns($result, 0);
    if ($piece_ids === false) $piece_ids = array();
    $res->to_pie($from, array( 'user_update', array('current_nick' => $nick,
                                                    'reserved' => $piece_ids) ));

    $res->to_pie($from, info_msg("User %s has freed piece #%s.", $nick, $piece_id));
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
        throw new Exception("This piece isn't exists.");
    if (!pg_field_is_null($result, 0, 0)) {
        $owner = pg_fetch_result($result, 0 ,0);
        if ($owner !== $from->user_id())
            throw new Exception("This piece is owned by $owner.");
    }

    $result = pg_query($connection, 'UPDATE pieces SET state = '.$state.' WHERE id = '.$piece_id);

    //TODO: format piece info hash
    update_kml($from->pieid);

    $pinfo = array( "piece_id" => $piece_id,
                    "state" => $state );
    $res->to_pie($from, array('piece_state', $pinfo));
    $res->to_pie($from, info_msg("User %s has set state for #%s to %s/9", $from->nick(), $piece_id, $state));
}

function handle_piece_comment($type, $from, $data, $res)
{
    global $connection;

    $from->need_level('member');
    validate_required($data, array('piece_id', 'comment'));

    $piece_id = $data['piece_id'];
    $comment = htmlspecialchars(trim( preg_replace('/\s+/', ' ', $data['comment']) ));

    $result = pg_query($connection, 'INSERT INTO pieces_comments VALUES(DEFAULT,'.$piece_id.','.$from->user_id().',\''.pg_escape_string($comment).'\',DEFAULT) RETURNING timestamp');
    $timestamp = pg_fetch_result($result, 0 ,0);

    $res->to_sender(info_msg( 'Comment added to piece #'.$piece_id.'.', $from->nick() ));
    $res->to_pie($from, array('piece_comment', array(
        'piece_id' => $piece_id,
        'author' => $from->nick(),
        'message' => $comment,
        'date' => $timestamp
    )) );
}

?>
