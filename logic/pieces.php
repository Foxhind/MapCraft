<?php

function handle_piece_state($type, $from, $data, $res)
{
    $from->need_level("member"); // ensure we at least member

    $id = $data['piece_id'];
    $pieid = $from->pieid;
    $state = $data['percent'];  //TODO: rename percent -> state

    //TODO: check that we have reserved the piece
    //TODO: check that piece exists
    //TODO: update piece
    //TODO: format piece info hash
    $pinfo = array( "piece_id" => $id,
                    "percent" => $state );
    $res->to_pie($from, array('piece_state', $pinfo));
    $res->to_pie($from, info_msg("User %s has set state for #%s to %s/9", $from->nick(), $pieid, $state));
}

function handle_piece_comment($type, $from, $data, $res)
{
    global $connection;

    $from->need_level('member');
    validate_required($data, array('piece_id', 'comment'));

    $piece_id = $data['piece_id'];
    $comment = htmlspecialchars(trim( preg_replace('/\s+/', ' ', $data['comment']) ));

    pg_query($connection, 'INSERT INTO pieces_comments VALUES(DEFAULT,'.$piece_id.','.$from->user_id().',\''.pg_escape_string($comment).'\',DEFAULT');
    $res->to_sender(info_msg( 'Comment added to piece '.$piece_id.'.', $from->nick() ));
}

?>