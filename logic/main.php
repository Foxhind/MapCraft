#!/usr/bin/env php
<?php
include("./lib/dispatcher.php");
include("./lib/api.php");
include("./lib/hub.php");

// event handlers
include("./chat.php");


// Main pipe reading/writing loop
while(1) {
    $cmd = readline("");
    print ">> $cmd\n";
    try {
        $res = handle_hub_message($cmd);
        print_r($res);
        print "OK\n";
    }
    catch(Exception $e) {
        print "Exception: $e";
    }
    break;
}


?>