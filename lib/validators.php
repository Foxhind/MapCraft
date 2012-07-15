<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

function validate_required() {
    $fields = func_get_args();
    $arr = array_shift($fields);
    foreach ($fields as $field) {
        if ( !isset($arr[$field]) ) {
            throw new Exception("Field '$field' is required");
        }
    }
}

function validate_id() {
    $fields = func_get_args();
    $arr = array_shift($fields);
    foreach ($fields as $field) {
        if ( !preg.match("^[0-9]+$", $arr[$field]) ) {
            throw new Exception("Field '$field' is not an ID");
        }
    }
}

?>