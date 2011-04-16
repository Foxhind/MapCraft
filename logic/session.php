<?php

function handle_user_join($type, $from, $data, $res) {
    $msg = info_msg($from->nick() . ' has joined');
    $res->to_pie($from, $msg);
}


function handle_user_exit($type, $from, $data, $res) {
    $msg = info_msg($from->nick() . ' has quit: ' . $data['reason']);
    $res->to_pie($from, $msg);
}

function handle_pie_exit($type, $from, $data, $res) {
    // Nothing at now
}

function handle_whoami($type, $from, $data, $res) {
    $info = array( "role" => $from->role(),
                   "nick" => $from->nick() );
    $res->to_session($from, array('youare', $info));
}

?>