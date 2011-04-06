<?php

/*********************************
 * Logic => Hub HELPERS
 *********************************/

/*
 * Will format MSG in proper command for the hub.
 */
function respond($msg)
{
    return sprintf('respond!json:%s',
                   json_encode($msg));
}

function to_session($from, $msg)
{
    return sprintf('to_session!%s!%s!json:%s',
                   $from['pie_id'],
                   $from['session_id'],
                   json_encode($msg));
}

function to_pie($from, $msg)
{
    return sprintf('to_pie!%s!json:%s',
                   $from['pie_id'],
                   json_encode($msg));
}

function to_all($msg)
{
    return sprintf('to_all!json:%s',
                   json_encode($msg));
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
    print_r($msg);
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

    // Verify that message is set
    if( is_null($msg['message']) ) {
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