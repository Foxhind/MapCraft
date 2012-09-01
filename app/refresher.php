<?php

	include('../lib/config.php');

	// Parse given query
	$matches = array();
	if (!preg_match("/^((\d+)\-(\w+)(\-[\w\-]+)?)\.(\w+)$/", $_GET['q'], $matches)) {
		throw new Exception("Cant parse query string. Please make sure that it matches encoded regexp");
	}
	list($full_name, $base_name, $pie_id, $type, $args, $ext) = $matches;

	try {
		include("../lib/drawers/$type.php");
		draw($pie_id, $base_name, $ext, $args);
	} catch (Exception $e) {
		header("Content-type: text/plain;");
		echo "Failed to generate picture: " . $e->getMessage();
	}
?>