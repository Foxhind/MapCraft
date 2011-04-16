<?php

function handle_piece_state($type, $from, $data, $res)
{
    $id = $data['piece_id'];
    $pieid = $from->pieid;
    $state = $data['percent'];  //TODO: rename percent -> state

    //TODO: check that we have rights to update piece
    //TODO: check that piece exists
    //TODO: update piece
    //TODO: format piece info hash
    $pinfo = array( "piece_id" => $id,
                    "percent" => $state );
    $res->to_pie($from, array('piece_state', $pinfo));
    $res->to_pie($from, info_msg("User " . $from->nick() . " has set state for #" . $pieid . " to " . $state));
}

?>