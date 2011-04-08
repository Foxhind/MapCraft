<?php

function validate_required($arr, $fields) {
    foreach ($fields as $field) {
        if ( !isset($arr[$field]) ) {
            throw new Exception("Field '$field' is required");
        }
    }
}


?>