#!/usr/bin/env php
<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

include("../lib/config.php");
include("../lib/update_kml.php");

include("../lib/validators.php");
include("../lib/api.php");
include("../lib/hub.php");

// Database
include("../lib/db/sessions.php");

// event handlers
include("./session.php");
include("./chat.php");
include("./claims.php");
include("./pieces.php");
include("./users.php");
include("./eggs.php");

/*
 *     MAIN CODE
 */

// Parse opts and set variables
$opts = getopt("dti:");

// to distinguish several logic instances
$LOGIC_ID= isset($opts['i']) ? $opts['i'] : (string) rand(1,65535);
$TEST_MODE = isset($opts['t']);
$DEBUG_MODE = isset($opts['d']) || getenv('MC_DEBUG');

// Logging
include("Log.php");
$logger = Log::singleton('file', $logic_log_file, "[id=$LOGIC_ID]");
$logger->notice("Starting new logic server");

// Handle PHP errors
function formatError($errno, $errstr, $errfile, $errline) {
    return "on " . $errfile . ":" . $errline . " [" . $errno . "]: " . $errstr;
}

function myErrorHandler($errno, $errstr, $errfile, $errline) {
    global $logger;
    $logger->err("PHP error " . formatError($errno, $errstr, $errfile, $errline));
}
set_error_handler("myErrorHandler");

function myExceptionHandler($exception) {
    global $logger;
    $logger->err("Uncaught exception: " , $exception->getMessage());
}
set_exception_handler('myExceptionHandler');

function myShutdownHandler() {
    global $logger;
    $a=error_get_last();
    if($a != null)
        $logger->emerg("Fatal PHP error " . formatError($a['type'], $a['message'], $a['file'], $a['line']));
}
register_shutdown_function('myShutdownHandler');


// Main pipe reading/writing loop
$fp=fopen("php://stdin","r");
while(!feof($fp)) {
    $cmd = stream_get_line($fp, 4 * 1024 * 1024, "\n");
    $res = new HubResult();

    if ($cmd == '')
        continue;
    $logger->info("IN  << $cmd");

    // Try to handle command. catch all exceptions
    try {
        process_hub_message($cmd, $res);
    }
    catch(Exception $e) {
        $logger->err("EXCEPTION: " . $e->getMessage());
        $msg = error_msg($e->getMessage());
        $res->to_sender($msg);
    }

    $res->output();

    foreach ($res->data as $line) {
        $logger->info("OUT >> $line");
    }

    // break after first run in testing mode
    if($TEST_MODE)
        break;
}
fclose($fp);

?>
