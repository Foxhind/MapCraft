<?php

function handle_get_cat($type, $from, $data, $res)
{
    $res->to_pie($from, info_msg("User " . $from->nick() . " has got the cat!"));
}

?>