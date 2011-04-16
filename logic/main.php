#!/usr/bin/env php
<?php
include("../config.php");

include("./lib/validators.php");
include("./lib/api.php");
include("./lib/hub.php");

// Database
include("./db/sessions.php");

// event handlers
include("./session.php");
include("./chat.php");
include("./eggs.php");

/*
 *     MAIN CODE
 */

// Parse opts and set variables
$opts = getopt("ti:");

// to distinguish several logic instances
$LOGIC_ID= isset($opts['i']) ? $opts['i'] : (string) rand(1,65535);
$TEST_MODE = isset($opts['t']);

// Main pipe reading/writing loop
$fp=fopen("php://stdin","r");
while(!feof($fp)) {
    $cmd = stream_get_line($fp, 4 * 1024 * 1024, "\n");
    $res = new HubResult();

    // Try to handle command. catch all exceptions
    try {
        process_hub_message($cmd, $res);
    }
    catch(Exception $e) {
        $msg = error_msg(array( 'message' =>  $e->getMessage() ));
        $res->respond($msg);
    }

    $res->output();
    // break after first run in testing mode
    if($TEST_MODE)
        break;
}
fclose($fp);

?>