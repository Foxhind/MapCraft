#!/bin/bash

cmd='from!async!1!user1235678!json:["msg", {"type": "public", "message": "Hello world!"}]'
echo $cmd | ./main.php -t

cmd='session_join!1!user1235678'
echo $cmd | ./main.php -t

cmd='session_exit!1!user1235678!timeout'
echo $cmd | ./main.php -t

cmd='from!async!1!user1235678!json:["nocmd", {}]'
echo $cmd | ./main.php -t

cmd='nocmd!arg1!arg2'
echo $cmd | ./main.php -t

( echo 'from!async!1!user1235678!json:["whoami", {}]'; \
  echo 'from!async!1!user1235679!json:["whoami", {}]'  \
  )  | ./main.php

cmd='from!async!1!user1235678!json:["get_cat", {}]'
echo $cmd | ./main.php -t
