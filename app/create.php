<div id="pageheader" style="background-color: #92836c;">New cake creating</div>
<?php

require '../lib/update_kml.php';
require '../lib/create_map.php';

//
// Deny guests
//
$user_id = $_SESSION['user_id'];
if (!isset($user_id)) {
    echo 'Anonymous can\'t create cakes. <a href="/app/auth.php?reload=1" target="_blank">Log in</a>, please.';
    exit();
}

//
// Draw basic dialog if nothing was submitted yet
//
if (!isset($_POST['name'])) {
?>
<form action="" method="post" enctype="multipart/form-data">
<fieldset>
    <div class="row">All data exсluding geometry can be changed later... when the configuration dialog will be implemented</div>
    <div class="row"><div>
        <label for="name">Name <em>*</em><br/><small>City, locality etc.</small></label>
        <input type="text" id="name" name="name" required="required" />
    </div><div>
        <label for="file">Geometry <em>*</em><br/><small>File in .osm format. It should contain only closed ways without tags. The size must be less then 512Kb. Can be created in JOSM</small></label>
        <input type="file" id="file" name="file" required="required" />
    </div></div>
    <div class="row"><div>
        <label for="description">Description</label>
        <textarea id="description" name="description"></textarea>
    </div></div>
    <div class="row"><div>
        <label><input class="btn" type="checkbox" id="hide" name="hide" />Hidden cake<!--<br/><small>This doesn't affect the access!</small>--></label>
    </div></div>
<?php
    if (!$skip_captcha) {
?>
    <div class="row"><div>
        <label for="captcha">Captcha <em>*</em> <img src="/app/captcha.php" /></label>
        <input type="text" id="captcha" name="captcha" required="required" />
    </div></div>
<?php
    }
?>
    <input class="btn" type="submit" value="&nbsp Create »" />
</fieldset>
</form>
<?php
    exit();
}

//
// Adding new cake
//

// Check captcha
if (!$skip_captcha && $_SESSION['security_code'] != strtolower($_POST['captcha'])) {
    unset($_SESSION['security_code']);
    echo 'Incorrect captcha.<br /><a href="javascript:history.back();">Back</a>';
    exit();
}
unset($_SESSION['security_code']);

// Check filesize and type
if ($_FILES['file']['size'] > 524288) {
    echo 'Too big file. It must be less than 512 kb.<br /><a href="javascript:history.back();">Back</a>';
    exit();
}
if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
    echo 'Not specified osm file.<br /><a href="javascript:history.back();">Back</a>';
    exit();
}

// Require name
if (!isset($_POST['name']) or empty($_POST['name'])) {
    echo 'Not specified cake name.<br /><a href="javascript:history.back();">Back</a>';
    exit();
}

// Check user
$result = pg_fetch_assoc(pg_query($connection, 'SELECT * FROM users WHERE id=\''.$user_id.'\''), 0);
if (!$result) {
    echo 'The user with id =  '.$user_id.' is not present in the base. Please logout and login back. <br /><a href="javascript:history.back();">Back</a>';
    exit();
}

// XML parser body
$coordinates = array();
$nodes = array();
$currentway = null;
$bbox = null;
function saxStartElement($parser, $name, $attrs)
{
    global $currentway, $nodes, $bbox;
    switch($name)
    {
        case 'node':
            $lon = floatval($attrs['lon']);
            $lat = floatval($attrs['lat']);
            if ($bbox == null)
                $bbox = array($lon, $lat, $lon, $lat);
            else {
                if ($lon < $bbox[0]) $bbox[0] = $lon;
                if ($lon > $bbox[2]) $bbox[2] = $lon;
                if ($lat < $bbox[1]) $bbox[1] = $lat;
                if ($lat > $bbox[3]) $bbox[3] = $lat;
            }
            $nodes[$attrs['id']] = $attrs['lon'].','.$attrs['lat'];
            break;
        case 'way':
            $currentway = array();
            break;
        case 'nd':
            if ($currentway !== null)
                $currentway[] = $nodes[$attrs['ref']];
            break;
        case 'tag':
            $currentway = null;
            break;
    };
}

function saxEndElement($parser, $name)
{
    global $coordinates, $currentway;
    if ($name == 'way' and $currentway != null) {
        if ($currentway[0] == $currentway[count($currentway)-1])
            $coordinates[] = implode(' ', $currentway);
        $currentway = null;
    }
}

$parser = xml_parser_create();
xml_set_element_handler($parser,'saxStartElement','saxEndElement');
xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING, false);

$data = file_get_contents($_FILES['file']['tmp_name']);
if (!xml_parse($parser, $data)) {
    echo 'XML error: '.xml_error_string(xml_get_error_code($parser)).' at line '.xml_get_current_line_number($parser);
    echo '<br /><a href="javascript:history.back();">Back</a>';
    exit();
}

xml_parser_free($parser);

if (count($coordinates) < 2) {
    echo 'Slices count must be over one.<br /><a href="javascript:history.back();">Back</a>';
    exit();
}

// Adding pie
$bboxcenter = json_encode( array(($bbox[0]+$bbox[2])/2, ($bbox[1]+$bbox[3])/2) );
$result = pg_query($connection, 'INSERT INTO pies VALUES(DEFAULT, \''.pg_escape_string($_POST['name']).'\', '.pg_escape_string($user_id).', DEFAULT, NULL, \''.pg_escape_string(htmlspecialchars($_POST['description'])).'\', '.(isset($_POST['hide']) ? 'false' : 'true').', \''.$bboxcenter.'\', DEFAULT) RETURNING id');
$pie_id = pg_fetch_result($result, 0, 0);

// Adding pieces
$index = 1;
foreach ($coordinates as $c) {
    $result = pg_query($connection, 'INSERT INTO pieces VALUES(DEFAULT, DEFAULT, DEFAULT, '.$pie_id.', \''.$c.'\',' . $index++ . ')');
}

// Adding access for owner
if (!pg_query_params($connection,
                     "INSERT INTO access VALUES ($1, $2, '', 'o')",
                      array($user_id, $pie_id))) {
    echo 'Failed to add access for owner. <br /><a href="javascript:history.back();">Back</a>';
    exit();
}

// Update map
update_kml($pie_id);
create_map($pie_id);

$pie_link = '/pie/'  . $pie_id;
?>

Done!<br/>
Please follow <a href="<?=$pie_link;?>">this link</a>, if you have not been forwarded automatically.
<script type="text/javascript">
     window.location = "<?=$pie_link;?>";
</script>
