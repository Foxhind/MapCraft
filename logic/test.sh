#!/bin/bash

cmd='from!async!1!user123!json:["msg", {"type": "public", "message": "Hello world!"}]'
echo $cmd | ./main.php

cmd='session_timeout!1!user123'
echo $cmd | ./main.php
