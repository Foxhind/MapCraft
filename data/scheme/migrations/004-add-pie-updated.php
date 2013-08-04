<?php

include ("../../../lib/config.php");

function apply()
{
	global $connection;

	# Create new column
	if (!pg_query($connection, 'ALTER TABLE pies ADD COLUMN updated timestamp  without time zone DEFAULT (now())::timestamp without time zone'))
		throw new Exception("This migration has been already applied");

	pg_query('UPDATE pies SET updated = (
		SELECT 
				timestamp 
			FROM 
				mapcraft.pieces as p, 
				mapcraft.pieces_comments as c 

			WHERE 
				c.piece = p.id AND type = \'info\' AND p.pie = pies.id

			ORDER BY
				timestamp DESC LIMIT 1
			)
		WHERE pies.updated IS NULL');
}


function revert()
{
	global $connection;

	pg_query($connection, 'ALTER TABLE pies DROP COLUMN updated');
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