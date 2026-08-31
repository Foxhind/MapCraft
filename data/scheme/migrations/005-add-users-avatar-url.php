<?php

include '../../../lib/config.php';

function apply()
{
    global $connection;

    if (!pg_query($connection, 'ALTER TABLE users ADD COLUMN avatar_url character varying(2048)')) {
        throw new Exception('Failed to add the users.avatar_url column.');
    }
}

function revert()
{
    global $connection;

    if (!pg_query($connection, 'ALTER TABLE users DROP COLUMN avatar_url')) {
        throw new Exception('Failed to remove the users.avatar_url column.');
    }
}

try {
    if (count($argv) > 1 && $argv[1] == '-r') {
        echo "Reverting...\n";
        revert();
    } else {
        echo "Applying...\n";
        apply();
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}

?>
