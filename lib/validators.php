<?php

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