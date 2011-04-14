<?php

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

function _generic_msg($type, $msg)
{
    if (gettype($msg) == 'string') {
        return _generic_msg($type, array('message' => $msg));
    }

    $msg = $msg; // copy hash ?

    // Set author
    if($type == 'chat') {
        if( is_null($msg['from']) ) {
            throw new Exception('Missed value for "from" key for type=chat');
        }

        if( is_null($msg['from']['nick']) ) {
            throw new Exception('Missed value for "from->nick" key for type=chat');
        }

        $msg['author'] = $msg['from']['nick'];
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

function chat_msg($msg)  { return _generic_msg('chat',  $msg); }
function info_msg($msg)  { return _generic_msg('info',  $msg); }
function ok_msg($msg)    { return _generic_msg('ok',    $msg); }
function error_msg($msg) { return _generic_msg('error', $msg); }


?>