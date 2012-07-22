<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

function handle_claim($type, $from, $data, $res) {
    global $connection;
    $from->need_level("member");

    validate_required($data, 'piece_index');
    $piece_index = $data['piece_index'];
    $piece_id = _find_piece_id($from, $piece_index);

    $result = pg_query($connection, 'SELECT COUNT(*) FROM claims WHERE author = '.$from->user_id().' and piece = '.$piece_id);
    $num = intval(pg_fetch_result($result, 0 ,0));

    if ($num != 0)
        throw new Exception("You have such claim already.");

    // TODO: Protection from meaningless claims (for pieces without owner etc.)

    $result = pg_query($connection, 'INSERT INTO claims VALUES(DEFAULT,'.$from->user_id().','.$piece_id.',DEFAULT) RETURNING id');
    $claim_id = intval(pg_fetch_result($result, 0, 0));

    $msg = array('claim_add', array( 'claim_id' => $claim_id,
                           'vote_balance' => 0,
                           'piece_index' => $piece_index,
                           'owner' => $from->nick() ));

    $res->to_pie($from, $msg);
}

function handle_claim_remove($type, $from, $data, $res) {
    global $connection;
    $from->need_level("member");

    validate_required($data, 'claim_id');
    $claim_id = $data['claim_id'];

    $result = pg_query($connection, 'SELECT users.nick FROM claims JOIN users ON users.id = claims.author WHERE claims.id = '.$claim_id);
    $num = pg_num_rows($result);

    if ($num == 0)
        throw new Exception("Not found such claim.");
    $author = pg_fetch_result($result, 0, 'nick');
    if ($author !== $from->nick())
        throw new Exception("It's not your claim.");

    $result = pg_query($connection, 'DELETE FROM claims WHERE id = '.$claim_id);

    $msg = array('claim_remove', array('claim_id' => $claim_id));

    $res->to_pie($from, $msg);
}

function handle_vote_claim($type, $from, $data, $res) {
    global $connection;
    $from->need_level("member");

    validate_required($data, 'claim_id', 'vote');
    $claim_id = $data['claim_id'];
    $vote = intval($data['vote']);
    $textvote = ($vote > 0) ? 'pro' : (($vote < 0) ? 'contra' : 'neutrally');

    $result = pg_query($connection, 'SELECT author, piece, pieces.index, users.nick FROM claims JOIN pieces ON pieces.id = claims.piece JOIN users ON users.id = author WHERE claims.id = '.$claim_id);
    $num = pg_num_rows($result);
    if ($num == 0)
        throw new Exception("Not found such claim.");
    $claim = pg_fetch_assoc($result);
    // Protection from self-voting
    if ( intval($claim['author']) == intval($from->user_id()) )
        throw new Exception("Not vote for yourself.");

    $result = pg_query($connection, 'SELECT COUNT(*) FROM votes WHERE claim = '.$claim_id.' AND author = '.$from->user_id());
    $num = intval(pg_fetch_result($result, 0, 0));

    if ($num == 0) {
        pg_query($connection, 'INSERT INTO votes VALUES('.$claim_id.','.$from->user_id().','.$vote.')');
        $res->to_sender(info_msg('You voted „'.$textvote.'“.'));
    }
    else {
        pg_query($connection, 'UPDATE votes SET value = '.$vote.' WHERE claim = '.$claim_id.' AND author = '.$from->user_id());
        $res->to_sender(info_msg('You changed vote to „'.$textvote.'“.'));
    }

    // Recalcing score (vote_balance)
    $result = pg_query($connection, 'SELECT sum(value) AS score FROM votes WHERE claim = '.$claim_id);
    $score = intval(pg_fetch_result($result, 0, 0));

    if ($score > 2) {
        // Getting old owner from db
        $result = pg_query($connection, 'SELECT owner, users.nick FROM pieces JOIN users ON users.id = pieces.owner WHERE pieces.id = '.$claim['piece']);
        $old_owner = pg_fetch_assoc($result);

        // Updating piece in db
        pg_query($connection, 'UPDATE pieces SET owner = '.$claim['author'].' WHERE id = '.$claim['piece']);
        $res->to_pie($from, array( 'piece_owner', array('piece_index' => $claim['index'], 'owner' => $claim['nick']) ));

        // Removing claim
        pg_query($connection, 'DELETE FROM claims WHERE id = '.$claim_id);
        $res->to_pie($from, array( 'claim_remove', array('claim_id' => $claim_id) ));

        $res->to_pie($from, info_msg('Claim by '.$claim['nick'].' for piece #'.$claim['index'].' is accepted.'));

        update_kml($from->pieid);
        _update_user_reserved($res, $from, $claim['author'], $claim['nick']);
        _update_user_reserved($res, $from, $old_owner['owner'], $old_owner['nick']);
    }
    else if ($score < -2) {
        pg_query($connection, 'DELETE FROM claims WHERE id = '.$claim_id);
        $msg = array('claim_remove', array('claim_id' => $claim_id));
        $res->to_pie($from, $msg);
        $res->to_pie($from, error_msg('Claim by '.$claim['nick'].' for piece #'.$claim['index'].' is dismissed.'));
    }
    else {
        pg_query($connection, 'UPDATE claims SET score = '.$score.' WHERE id = '.$claim_id);
        $msg = array('claim_update', array( 'claim_id' => $claim_id,
                                            'vote_balance' => $score));
        $res->to_pie($from, $msg);
    }
}

?>
