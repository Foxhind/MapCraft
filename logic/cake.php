<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

function handle_update_cake($type, $from, $data, $res) {
	global $connection;

	_send_cake_info($res, $from, false);
}

function _send_cake_info($res, $from, $to_session) {
	global $connection;

    $result = pg_query($connection,
                       'SELECT *, users.nick as author_nick
                        FROM pies
                             JOIN users ON users.id = pies.author
                        WHERE pies.id = ' . $from->pieid);
    if (!$result) {
    	throw new Exception("Failed to load pie information");
    }
    $row = pg_fetch_assoc($result);

	$info = array();
	$info['name'] = $row['name'];
	$info['description'] = $row['description'];
	$info['created_at'] = $row['start'];
	$info['author'] = $row['author_nick'];
	$info['visible'] = $row['visible'];

	$msg = array('update_cake', $info);
	if ($to_session) {
		$res->to_session($from, $msg);
	} else {
		$res->to_pie($from, $msg);
	}
}

?>