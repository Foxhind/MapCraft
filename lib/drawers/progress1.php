<?php

$svg_template = dirname(__FILE__).'/progress1.svg';
$output_dir = dirname(__FILE__) . '/../../static/banner/';
$bg_schemes = array(
	'low' => array('621a1a', 'c43636'),
	'middle' => array('774600', 'd17b00'),
	'high' => array('00221a', '006e5a'));


function draw($pie_id, $base_name, $ext, $args) {
	if (!in_array($ext, array('png', 'svg')))
		throw new Exception("Selected banner type does not support output extension '$ext'");

	// Firstly draw svg. We need it either way
	$svg_output = draw_svg($pie_id, $base_name, $args);

	if ($ext == 'svg') {
		header("Content-type: image/svg+xml;");
		readfile($svg_output);
		exit;
	}

	if ($ext == 'png') {
		$png_output = convert_to_png($svg_output, $base_name);
		header("Content-type: image/png;");
		readfile($png_output);
		exit;
	}
}

function draw_svg($pie_id, $base_name, $args) {
	global $output_dir;
	global $svg_template;
	global $bg_schemes;

	$bar_start = 59.4;
	$bar_end = 383.9;
	$bar_len = $bar_end - $bar_start;

	$svg_output = $output_dir . $base_name . '.svg';
	if (file_exists($svg_output))
		return $svg_output;

	// Parse
	$xdoc = new DomDocument;
	$xdoc->Load($svg_template);
	$xp = new DomXPath($xdoc);

    // Get progress from DB
    $result = pg_query("SELECT state FROM pieces WHERE pie = " . $pie_id);
    $states = pg_fetch_all_columns($result, 0);
    $progress = array(0,0,0,0,0,0,0,0,0,0);
    $full_sum = 9 * count($states);
    $current_sum = 0;
    foreach ($states as $st) {
        $progress[$st] ++;
        $current_sum += $st;
    }

    // Calculate bars positions and widths
    $offset = $bar_start;
    $step = $bar_len / count($states);
    for ($i = 0 ; $i <= 9; $i++ ) {
		$elem = find_by_id($xp, 'bar_' . $i);
		$elem->setAttribute('x', $offset);
		$elem->setAttribute('width', $step * $progress[$i]);
		$offset += $step * $progress[$i];
    }

	// Set percent
    $percent = round($current_sum / $full_sum * 100);
	find_by_id($xp, 'percent-fg')->nodeValue = $percent . '%';

	// Set BG color gradient;
	$current_bg_scheme = $bg_schemes['middle'];
	if ($progress[9] / count($states) > 0.6) {
		$current_bg_scheme = $bg_schemes['high'];
	} else if ($progress[0] / count($states) > 0.6) {
		$current_bg_scheme = $bg_schemes['low'];
	}

	find_by_id($xp, 'bg-grd-start')->setAttribute('style', 'stop-color:#' . $current_bg_scheme[0] . ';stop-opacity:1');
	find_by_id($xp, 'bg-grd-stop')->setAttribute('style', 'stop-color:#' . $current_bg_scheme[1] . ';stop-opacity:1');

	// Save it to file
	if (($fd = fopen($svg_output, 'w')) === false)
		throw new Exception("Cant open SVG file for saving");
	fwrite($fd, $xdoc->saveXML());
	fclose($fd);

	return $svg_output;
}

function convert_to_png($svg_input, $base_name) {
	global $output_dir;

	$png_output = $output_dir . $base_name . '.png';
	exec("convert $svg_input $png_output");

	return $png_output;
}

function find_by_id($xp, $id) {
	$item = $xp->query("//*[@id = '$id']")->item(0);
	if (empty($item))
		throw new Exception("Cant find element with ID = $id");
	return $item;
}

?>