#!/bin/bash

cmd='from!async!1!user123!json:["msg", {"type": "public", "message": "Hello world!"}]'
echo $cmd | ./main.php -t

cmd='session_exit!1!user123!timeout'
echo $cmd | ./main.php -t

cmd='from!async!1!user123!json:["nocmd", {}]'
echo $cmd | ./main.php -t

cmd='nocmd!arg1!arg2'
echo $cmd | ./main.php -t
