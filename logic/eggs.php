<?php

function handle_get_cat($type, $from, $data, $res)
{
    $res->to_pie($from, info_msg("%s has got the cat!", $from->nick()));
}

?>