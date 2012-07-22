<?php

include ("../../../lib/config.php");

function update_index_for_pieces() {
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
			$r = pg_query_params($connection, 'UPDATE pieces SET index = $1 WHERE id = $2', array($index++, $piece['id']));
			if (!$r) {
				echo "Failed to update piece " . $piece['id'] . ". Exiting ...\n";
				exit (1);
			}
		}
	}
}


function apply()
{
	global $connection;

	# Create new column
	$result = pg_query($connection, 'ALTER TABLE pieces ADD COLUMN index integer');
	if (!$result) {
		echo "This migration has been already applied\n";
		exit(1);
	}

	# Fill piece indexes with generated values
	update_index_for_pieces();
}


function revert()
{
	global $connection;

	pg_query($connection, 'ALTER TABLE pieces DROP COLUMN index');
}



#
# Main
#
if (count($argv) > 1 && $argv[1] == '-r') {
	echo "Reverting ...\n";
	revert();
} else {
	echo "Applying ...\n";
	apply();
}

?>