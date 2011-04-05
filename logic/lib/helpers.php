<?php

/*********************************
 * Logic => Hub HELPERS
 *********************************/

/*
 * Will format MSG in proper command for the hub.
 */
function respond($msg)
{
}

function to_user($from, $msg)
{
}

function to_chat($from, $msg)
{
}

function to_all($msg)
{
}


/*********************************
 * Logic => Javascript HELPERS
 *********************************/

/*
 * Message generators
 */
function _gen_new_msg_id()
{
    return 1;
}
function _gen_new_msg_date()
{
    return 'Пятница 13';
}

function _generic_msg($type, $msg)
{
    if (gettype($msg) == 'string') {
        return _generic_msg($type, array('message' => $msg));
    }

    $msg = $msg; // copy hash ?

    // Set author
    if($type == 'chat') {
        if( !defined($msg['from']) ) {
            throw new Exception('Missed value for "from" key for type=chat');
        }

        if( !defined($msg['from']['nick']) ) {
            throw new Exception('Missed value for "from->nick" key for type=chat');
        }

        $msg['author'] = $msg['from']['nick'];
    }

    // Verify that message is set
    if( !defined($msg['message']) ) {
        throw new Exception('Missed value for "message" key');
    }

    // Fill other fields structure
    $msg['class']   = $type;
    $msg['id']      = $msg['id'] || _gen_new_msg_id();
    $msg['date']    = $msg['date'] || _gen_new_msg_date();

    return $msg;
}

function chat_msg($msg)  { return _generic_msg('chat',  $msg); }
function info_msg($msg)  { return _generic_msg('info',  $msg); }
function ok_msg($msg)    { return _generic_msg('ok',    $msg); }
function error_msg($msg) { return _generic_msg('error', $msg); }


?>