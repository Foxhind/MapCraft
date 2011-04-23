<?php

function handle_claim($type, $from, $data, $res) {
    global $connection;

    validate_required($data, array('piece_id') );
    $piece_id = $data['piece_id'];

    $result = pg_query($connection, 'SELECT COUNT(*) FROM claims WHERE author = '.$from->user_id().' and piece = '.$piece_id);
    $num = intval(pg_fetch_result($result, 0 ,0));

    if ($num == 0)
        $result = pg_query($connection, 'INSERT INTO claims VALUES(DEFAULT,'.$from->user_id().','.$piece_id.',DEFAULT) RETURNING id');
    else
        throw new Exception("You have such claim already.");

    $claim_id = intval(pg_fetch_result($result, 0, 0));

    $msg = array('claim_add', array( 'claim_id' => $claim_id,
                           'vote_balance' => 0,
                           'piece_id' => $piece_id,
                           'owner' => $from->user_id() ));

    $res->to_pie($from, $msg);
}

?>
