<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

function handle_update_cake($type, $from, $data, $res) {
    global $connection;
    global $logger;

    $from->need_level("owner");

    $logger->debug("Start updating the cake " . $from->pieid . " initiated by used " . $from->user_id());
    $changes = $data['data'];

    // name, description
    foreach (array('name', 'description') as $prop) {
        if (array_key_exists($prop, $changes)) {
            $logger->debug("Updating property '" . $prop . "'");
            if(!pg_query_params($connection,
                                'UPDATE pies SET "' . $prop . '" = $2  WHERE id = $1',
                                array($from->pieid, $changes[$prop])))
                throw new Exception("Failed to update cake settings for prop: " . $prop);
        }
    }

    // visible = true/false
    if (array_key_exists('visible', $changes)) {
        $logger->debug("Updating property 'visible' = " . $changes['visible']);
        if(!pg_query($connection,
                            'UPDATE pies SET "visible" = ' . ($changes['visible'] ? 'true' : 'false') . ' WHERE id = ' . $from->pieid))
            throw new Exception("Failed to update cake settings for prop: visible");
    }

    //
    // update settings JSON
    //
    // firstly load:
    $result = pg_query($connection,
                       'SELECT settings FROM pies WHERE id = ' . $from->pieid);
    if (!$result) {
        throw new Exception("Failed to load pie information. Can't update settings JSON");
    }
    $settings = json_decode(pg_fetch_result($result, 0, 'settings'), true);

    // Update with new data
    if (array_key_exists('links', $changes)) {
        $settings['links'] = $changes['links'];
    }

    // Save new JSON
    if(! pg_query_params($connection,
                         'UPDATE pies SET settings = $1 where id = $2',
                         array(json_encode($settings), $from->pieid)))
        throw new Exception("Failed to update pie entry with new JSON");

    _send_cake_info($res, $from, false);
}

function _send_cake_info($res, $from, $to_session) {
    global $connection;
    global $logger;

    $result = pg_query($connection,
                       'SELECT *, users.nick as author_nick
                        FROM pies
                             JOIN users ON users.id = pies.author
                        WHERE pies.id = ' . $from->pieid);
    if (!$result) {
    	throw new Exception("Failed to load pie information");
    }
    $row = pg_fetch_assoc($result);
    $settings = json_decode($row['settings'], true);

	$info = array();
	$info['name'] = $row['name'];
	$info['description'] = $row['description'];
	$info['created_at'] = $row['start'];
	$info['author'] = $row['author_nick'];
	$info['visible'] = $row['visible'] == 't';
    $info['links'] = $settings['links'] ?: array();

	$msg = array('cake_info', $info);
	if ($to_session) {
		$res->to_session($from, $msg);
	} else {
		$res->to_pie($from, $msg);
	}
}

?>