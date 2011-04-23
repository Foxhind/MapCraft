<?php

function handle_claim($type, $from, $data, $res) {
    global $connection;
    $from->need_level("member");

    validate_required($data, array('piece_id') );
    $piece_id = $data['piece_id'];

    $result = pg_query($connection, 'SELECT COUNT(*) FROM claims WHERE author = '.$from->user_id().' and piece = '.$piece_id);
    $num = intval(pg_fetch_result($result, 0 ,0));

    if ($num != 0)
        throw new Exception("You have such claim already.");

    // TODO: Protection from meaningless claims (for pieces without owner etc.)

    $result = pg_query($connection, 'INSERT INTO claims VALUES(DEFAULT,'.$from->user_id().','.$piece_id.',DEFAULT) RETURNING id');
    $claim_id = intval(pg_fetch_result($result, 0, 0));

    $msg = array('claim_add', array( 'claim_id' => $claim_id,
                           'vote_balance' => 0,
                           'piece_id' => $piece_id,
                           'owner' => $from->nick() ));

    $res->to_pie($from, $msg);
}

function handle_claim_remove($type, $from, $data, $res) {
    global $connection;
    $from->need_level("member");

    validate_required($data, array('claim_id') );
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

    validate_required($data, array('claim_id', 'vote') );
    $claim_id = $data['claim_id'];
    $vote = intval($data['vote']);
    $textvote = ($vote > 0) ? 'pro' : (($vote < 0) ? 'contra' : 'neutrally');

    $result = pg_query($connection, 'SELECT author, piece, users.nick FROM claims JOIN users ON users.id = author WHERE claims.id = '.$claim_id);
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
        $res->to_pie($from, array( 'piece_owner', array('piece_id' => $claim['piece'], 'owner' => $claim['nick']) ));

        // Updating new owner
        $result = pg_query($connection, 'SELECT id FROM pieces WHERE owner = '.$claim['author']);
        $piece_ids = pg_fetch_all_columns($result, 0);
        $res->to_pie($from, array( 'user_update', array('current_nick' => $claim['nick'],
                                                        'reserved' => $piece_ids) ));
        // Updating old owner
        $result = pg_query($connection, 'SELECT id FROM pieces WHERE owner = '.$old_owner['owner']);
        $piece_ids = pg_fetch_all_columns($result, 0);
        if ($piece_ids === false) $piece_ids = array();
        $res->to_pie($from, array( 'user_update', array('current_nick' => $old_owner['nick'],
                                                        'reserved' => $piece_ids) ));
        // Removing claim
        pg_query($connection, 'DELETE FROM claims WHERE id = '.$claim_id);
        $res->to_pie($from, array( 'claim_remove', array('claim_id' => $claim_id) ));

        $res->to_pie($from, info_msg('Claim by '.$claim['nick'].' for piece #'.$claim['piece'].' is accepted.'));
        update_kml($from->pieid);
    }
    else if ($score < -2) {
        pg_query($connection, 'DELETE FROM claims WHERE id = '.$claim_id);
        $msg = array('claim_remove', array('claim_id' => $claim_id));
        $res->to_pie($from, $msg);
        $res->to_pie($from, error_msg('Claim by '.$claim['nick'].' for piece #'.$claim['piece'].' is dismissed.'));
    }
    else {
        pg_query($connection, 'UPDATE claims SET score = '.$score.' WHERE id = '.$claim_id);
        $msg = array('claim_update', array( 'claim_id' => $claim_id,
                                            'vote_balance' => $score));
        $res->to_pie($from, $msg);
    }
}

?>
