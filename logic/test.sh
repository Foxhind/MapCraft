#!/bin/bash

cmd='from!async!1!user123!json:["msg", {"type": "public", "message": "Hello world!"}]'
echo $cmd | ./main.php -t

cmd='session_exit!1!user123!timeout'
echo $cmd | ./main.php -t

cmd='pie_exit!1'
echo $cmd | ./main.php -t
