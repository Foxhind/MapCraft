<?php

include ("../../../lib/config.php");

function apply()
{
	global $connection;

	# Create new column
	if (!pg_query($connection, 'ALTER TABLE pies ADD COLUMN settings TEXT DEFAULT \'{}\' NOT NULL'))
		throw new Exception("This migration has been already applied");
}


function revert()
{
	global $connection;

	pg_query($connection, 'ALTER TABLE pies DROP COLUMN settings');
}



#
# Main
#
try {
	if (count($argv) > 1 && $argv[1] == '-r') {
		echo "Reverting ...\n";
		revert();
	} else {
		echo "Applying ...\n";
		apply();
	}
} catch (Exception $e) {
	echo "Error: " . $e->getMessage() . "\n";
	exit (1);
}

?>