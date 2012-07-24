<?php

include ("../../../lib/config.php");

function apply()
{
	global $connection;

	$result = pg_query($connection, 'SELECT id, author FROM pies');

	if (pg_num_rows($result) == 0) {
		echo "No cakes to update\n";
		return;
	}

	while ($pie = pg_fetch_array($result)) {
		echo "  Updating cake " . $pie['id'] . " ...\n";

		if (!pg_query_params($connection,
		                     "DELETE FROM access WHERE pie = $1 AND role = 'o'",
			                  array($pie['id'])))
			throw new Exception("Failed to delete old owner role");

		if (!pg_query_params($connection,
		                     "INSERT INTO access VALUES ($1, $2, '', 'o')",
		                     array($pie['author'], $pie['id'])))
			throw new Exception("Failed to insert new fixed owner role");
	}
}


function revert()
{
	global $connection;
	echo "No code to revert!\n";
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