<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */


/*********************************
 * Logic => Hub HELPERS
 *********************************/

/*
 * Will format MSG in proper command for the hub.
 */


/*********************************
 * Logic => Javascript HELPERS
 *********************************/

/*
 * Message generators
 */
$_message_id_counter = 1;
function _gen_new_msg_id()
{
    global $LOGIC_ID, $_message_id_counter;
    return sprintf("%s-%s", $LOGIC_ID, $_message_id_counter++);
}
function _gen_new_msg_date()
{
    return time();
}

function _generic_msg($type, $args)
{
    $msg = $args[0];
    if (gettype($msg) == 'string') {
        $msg = array();
        if(count($args) > 1) {
            $msg['message'] = call_user_func_array('sprintf', $args);
        } else {
            $msg['message'] = $args[0];
        }
    } else {
        $msg = $msg; // copy hash ?
    }

    // Set author
    if($type == 'chat') {
        if( is_null($msg['from']) ) {
            throw new Exception('Missed value for "from" key for type=chat');
        }

        $msg['author'] = $msg['from']->nick();
    }
    unset($msg['from']);

    // Verify that message is set
    if( is_null($msg['message']) ) {
        throw new Exception('Missed value for "message" key');
    }

    // Fill other fields structure
    $msg['class']   = $type;
    if( !isset($msg['id']) )   $msg['id']   = _gen_new_msg_id();
    if( !isset($msg['date']) ) $msg['date'] = _gen_new_msg_date();

    return array('chat', $msg);
}


function chat_msg($msg)  { return _generic_msg('chat',  func_get_args()); }
function info_msg($msg)  { return _generic_msg('info',  func_get_args()); }
function ok_msg($msg)    { return _generic_msg('ok',    func_get_args()); }
function error_msg($msg) { return _generic_msg('error', func_get_args()); }


?>