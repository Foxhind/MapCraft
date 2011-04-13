#!/usr/bin/env php
<?php
include("./lib/dispatcher.php");
include("./lib/validators.php");
include("./lib/api.php");
include("./lib/hub.php");

// event handlers
include("./chat.php");

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
    $res = array();

    // Try to handle command. catch all exceptions
    try {
        $res = handle_hub_message($cmd);
    }
    catch(Exception $e) {
        $msg = error_msg(array(
                              'message' =>  $e->getMessage()
                              ));
        $res = array( respond($msg) );
    }

    // Return result as lines
    foreach ($res as $line) {
        echo $line, "\n";
    }
    print "EOR\n";  // End of result

    // break after first run in testing mode
    if($TEST_MODE)
        break;
}
fclose($fp);

?>