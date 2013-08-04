<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

function handle_get_piece_comments($type, $from, $data, $res)
{
    global $connection;

    validate_required($data, 'piece_index');
    $piece_index = $data['piece_index'];
    $piece_id = _find_piece_id($from, $piece_index);

    $result = pg_query($connection, 'SELECT users.nick, text, type, timestamp FROM pieces_comments JOIN users ON users.id = author WHERE piece = '.$piece_id.' ORDER BY timestamp DESC LIMIT 100');

    if (pg_num_rows($result) == 0) {
        $res->to_sender( array('no_comments', array()) );
    }
    else {
        $rows = array();
        while ($row = pg_fetch_array($result)) {
            $rows[] = $row;
        }
        $rows = array_reverse($rows);

        foreach ($rows as $row) {
            $res->to_sender( array('piece_comment', array(
                'piece_index' => $piece_index,
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

    $piece_index = $data['piece_index'];
    $piece_id = _find_piece_id($from, $piece_index);
    $user_id = $from->user_id();

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

    $pinfo = array( "piece_index" => $piece_index,
                    "owner" => $from->nick() );
    $res->to_pie($from, array('piece_owner', $pinfo));

    _update_user_reserved($res, $from, $from->user_id(), $from->nick());
    $res->to_pie($from, info_msg("%s has reserved slice #%s.", $from->nick(), $piece_index));
    _add_piece_info_log($res, $from, $piece_index, $piece_id, "Slice has been reserved");
    _update_pie_timestamp($from, $connection);    
}

function handle_piece_free($type, $from, $data, $res)
{
    global $connection;
    $from->need_level("member");

    $piece_index = $data['piece_index'];
    $piece_id = _find_piece_id($from, $piece_index);
    $admin_mode = $data['admin_mode'] && $from->has_level('admin');

    $result = pg_query($connection, 'SELECT owner FROM pieces WHERE id = '.$piece_id);
    if (pg_num_rows($result) == 0)
        throw new Exception("This slice doesn't exist.");
    if (pg_field_is_null($result, 0, 0))
        throw new Exception("This slice isn't owned by you.");

    $owner_id = pg_fetch_result($result, 0 ,0);
    if ($owner_id !== $from->user_id() && !$admin_mode)
        throw new Exception("This is not your slice");

    $owner_nick = $from->nick();
    if ($owner_id !== $from->user_id()) {
        $result = pg_query($connection, 'SELECT nick FROM users WHERE id = ' . $owner_id);
        $owner_nick = pg_fetch_result($result, 0, "nick");
    }

    //
    // Actually free
    //
    $result = pg_query($connection, 'UPDATE pieces SET owner = NULL WHERE id = '.$piece_id);

    //TODO: format piece info hash
    update_kml($from->pieid);

    $pinfo = array( "piece_index" => $piece_index,
                    "owner" => "" );
    $res->to_pie($from, array('piece_owner', $pinfo));

    _update_user_reserved($res, $from, $owner_id, $owner_nick);
    $res->to_pie($from, info_msg("%s has freed slice #%s.", $from->nick(), $piece_index));
    _add_piece_info_log($res, $from, $piece_index, $piece_id, "Slice has been freed");
    _update_pie_timestamp($from, $connection);
}

function handle_piece_state($type, $from, $data, $res)
{
    global $connection;
    $from->need_level("member"); // ensure we at least member

    $pieid = $from->pieid;
    $piece_index = $data['piece_index'];
    $piece_id = _find_piece_id($from, $piece_index);
    $state = $data['state'];

    $admin_mode = $data['admin_mode'] && $from->has_level('admin');

    $result = pg_query($connection, 'SELECT owner FROM pieces WHERE id = '.$piece_id);
    if (pg_num_rows($result) == 0)
        throw new Exception("This slice doesn't exist.");

    $owner = pg_fetch_result($result, 0 ,0);
    if ($owner !== $from->user_id() && !$admin_mode)
        throw new Exception("This is not your slice");

    $result = pg_query($connection, 'UPDATE pieces SET state = '.$state.' WHERE id = '.$piece_id);

    //TODO: format piece info hash
    update_kml($from->pieid);

    $pinfo = array( "piece_index" => $piece_index,
                    "state" => $state );
    $res->to_pie($from, array('piece_state', $pinfo));
    $res->to_pie($from, info_msg("%s has set state for #%s to %s/9", $from->nick(), $piece_index, $state));
    _update_piece_progress($res, $from);
    _add_piece_info_log($res, $from, $piece_index, $piece_id, "New state: " . $state . "/9");
    _update_pie_timestamp($from, $connection);    
}

function handle_piece_mass_state($type, $from, $data, $res) {
    global $connection;
    global $logger;

    $from->need_level('admin');
    $actions = $data['actions'];

    $res->to_pie($from, info_msg("Mass slices update: change state to " . $state));

    foreach ($actions as $action) {
        foreach ($action['indexes'] as $piece_index) {
            $piece_id = _find_piece_id($from, $piece_index);
            $logger->debug('UPDATE pieces SET state = '. $action['state'] .' WHERE id = ' . $piece_id);
            if (!pg_query($connection, 'UPDATE pieces SET state = '. $action['state'] .' WHERE id = ' . $piece_id))
                throw new Exception("Failed to update state for piece #" . $piece_index);

            $res->to_pie($from, array('piece_state', array( "piece_index" => $piece_index, "state" => $action['state'] )));
            $res->to_pie($from, info_msg("%s has set state for #%s to %s/9", $from->nick(), $piece_index, $action['state']));
            _add_piece_info_log($res, $from, $piece_index, $piece_id, "New state: " . $action['state'] . "/9");
        }
    }
    _update_piece_progress($res, $from);
    update_kml($from->pieid);
    _update_pie_timestamp($from, $connection);
}

function handle_piece_mass_free($type, $from, $data, $res) {
    global $connection;

    $from->need_level('admin');

    $indexes = $data['indexes'];
    $owners = array();

    $res->to_pie($from, info_msg("Mass slices update: free slices"));

    foreach ($indexes as $piece_index) {
        $piece_id = _find_piece_id($from, $piece_index);

        if(!pg_query($connection, 'UPDATE pieces SET owner = NULL WHERE id = '.$piece_id))
            throw new Exception("Failed to free piece #" . $piece_index);

        if(!pg_query($connection, 'DELETE FROM claims WHERE piece = '.$piece_id))
            throw new Exception("Failed to remove claim for piece #" . $piece_index);

        $res->to_pie($from, array('piece_owner', array( "piece_index" => $piece_index, "owner" => "" )));
        $res->to_pie($from, info_msg("%s has freed slice #%s", $from->nick(), $piece_index));
        _add_piece_info_log($res, $from, $piece_index, $piece_id, "Slice has been freed");
    }

    update_kml($from->pieid);
    _update_users_claims($res, $from, true);
    _update_pie_timestamp($from, $connection);
}

function handle_piece_comment($type, $from, $data, $res)
{
    global $connection;

    $from->need_level('member');
    validate_required($data, 'piece_index', 'comment');

    $piece_index = $data['piece_index'];
    $piece_id = _find_piece_id($from, $piece_index);
    $comment = htmlspecialchars(trim( preg_replace('/\s+/', ' ', $data['comment']) ));

    $result = pg_query($connection, 'INSERT INTO pieces_comments VALUES(DEFAULT,'.$piece_id.','.$from->user_id().',\''.pg_escape_string($comment).'\',DEFAULT, \'comment\') RETURNING timestamp');
    $timestamp = pg_fetch_result($result, 0 ,0);

    $res->to_sender(info_msg( 'Comment added to piece #'.$piece_id.'.', $from->nick() ));
    $res->to_pie($from, array('piece_comment', array(
        'piece_index' => $piece_index,
        'author' => $from->nick(),
        'message' => $comment,
        'type' => 'comment',
        'date' => $timestamp
    )) );

    _update_pie_timestamp($from, $connection);
}

function handle_piece_progress($type, $from, $data, $res)
{
    _update_piece_progress($res, $from);
}

// -------------------------
// Helpers
// -------------------------
function _update_pie_timestamp($from, $connection) {
    pg_query($connection, "UPDATE pies SET updated = NOW() WHERE pies.id = " . $from->pieid);
}
function _find_piece_id($from, $piece_index) {
    global $connection;

    $result = pg_query_params($connection,
                              "SELECT id FROM pieces WHERE pie = $1 AND index = $2",
                              array($from->pieid, $piece_index));
    if (!$result || pg_num_rows($result) == 0) {
        // TODO: temprorary warning
        if ($piece_index > 100) {
            throw new Exception ('Piece index looks too big: ' . $piece_index . ' may be old cake was cached. Try Ctrl-F5');
        } else {
            throw new Exception ('Failed to find piece with index: ' . $piece_index);
        }
    }

    return pg_fetch_result($result, 0, 0);
}

function _update_piece_progress($res, $from)
{
    global $connection;

    $result = pg_query($connection, "SELECT state FROM pieces WHERE pie = " . $from->pieid);
    $states = pg_fetch_all_columns($result, 0);
    $progress = array(0,0,0,0,0,0,0,0,0,0);
    foreach ($states as $st) {
        $progress[$st] ++;
    }

    $event = array('piece_progress', array('progress' => $progress));
    $res->to_pie($from, $event);
}

function _add_piece_info_log($res, $from, $piece_index, $piece_id, $msg) {
    global $connection;

    $values = array('DEFAULT', $piece_id, $from->user_id(), '\''.pg_escape_string($msg).'\'', 'DEFAULT', '\'info\'');
    $result = pg_query($connection, 'INSERT INTO pieces_comments VALUES(' . join(', ', $values) . ') RETURNING timestamp');
    $timestamp = pg_fetch_result($result, 0 ,0);

    $res->to_pie($from, array('piece_comment', array(
        'piece_index' => $piece_index,
        'author' => $from->nick(),
        'message' => $msg,
        'type' => 'info',
        'date' => $timestamp
    )) );
}

function _update_user_reserved($res, $from, $user_id, $nick) {
    global $connection;

    $result = pg_query($connection, 'SELECT index FROM pieces WHERE owner = '. $user_id . ' and pie = ' . $from->pieid . ' ORDER BY index');
    $piece_indexes = pg_fetch_all_columns($result, 0);
    if ($piece_indexes === false) $piece_indexes = array();
    $res->to_pie($from, array( 'user_update', array('current_nick' => $nick,
                                                    'reserved' => $piece_indexes) ));
}

?>
