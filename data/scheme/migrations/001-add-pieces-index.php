<?php

include ("../../../lib/config.php");
include ("../../../lib/update_kml.php");

chdir('../../../app');  # need for update_kml;

function update_pies() {
	global $connection;

	$result = pg_query($connection, 'SELECT id FROM pies');

	if (pg_num_rows($result) == 0) {
		echo "No cakes to update\n";
		return;
	}

	while ($pie = pg_fetch_array($result)) {
		echo "  Updating cake " . $pie['id'] . " ...\n";
		$index = 1;
		$result_pieces = pg_query_params($connection, 'SELECT id FROM pieces WHERE pie = $1 ORDER by id', array($pie['id']));
		echo "    Number of pieces = " . pg_num_rows($result_pieces) . "\n";
		while ($piece = pg_fetch_array($result_pieces)) {
			if (!pg_query_params($connection, 'UPDATE pieces SET index = $1 WHERE id = $2', array($index++, $piece['id']))) 
				throw new Exception ("Failed to update piece " . $piece['id']);
		}

		update_kml($pie['id']);
	}
}


function apply()
{
	global $connection;

	# Create new column
	if (!pg_query($connection, 'ALTER TABLE pieces ADD COLUMN index integer'))
		throw new Exception("This migration has been already applied");

	# Fill piece indexes with generated values
	update_pies();

	# Set NOT is_null
	if (!pg_query($connection, 'ALTER TABLE pieces ALTER COLUMN index SET NOT NULL'))
		throw new Exception("Failed to set NOT NULL");
}


function revert()
{
	global $connection;

	pg_query($connection, 'ALTER TABLE pieces DROP COLUMN index');
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