<div id="pageheader" style="background-color: #92836c;">New cake creating</div>
<?php

require '../lib/update_kml.php';
require '../lib/create_map.php';

$osm_user = $_SESSION['osm_user'];
if (isset($osm_user)) {
    if (isset($_POST['captcha'])) {
        if ($_SESSION['security_code'] != strtolower($_POST['captcha'])) {
            unset($_SESSION['security_code']);
            echo 'Incorrect captcha.<br /><a href="javascript:history.back();">Back</a>';
            exit();
        }
        unset($_SESSION['security_code']);
        if ($_FILES['file']['size'] > 524288) {
            echo 'Too big file. It must be less than 512 kb.<br /><a href="javascript:history.back();">Back</a>';
            exit();
        }
        if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
            echo 'Not specified osm file.<br /><a href="javascript:history.back();">Back</a>';
            exit();
        }
        if (!isset($_POST['name']) or empty($_POST['name'])) {
            echo 'Not specified cake name.<br /><a href="javascript:history.back();">Back</a>';
            exit();
        }

        include '../lib/config.php';

        $user = pg_fetch_assoc(pg_query($connection, 'SELECT * FROM users WHERE nick=\''.$osm_user.'\''), 0);
        if (!$user) {
            echo 'User '.$osm_user.' hasn\'t access to cake creating.<br /><a href="javascript:history.back();">Back</a>';
            exit();
        }

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
        $result = pg_query($connection, 'INSERT INTO pies VALUES(DEFAULT, \''.pg_escape_string($_POST['name']).'\', '.pg_escape_string($user['id']).', DEFAULT, NULL, \''.pg_escape_string(htmlspecialchars($_POST['description'])).'\', '.(isset($_POST['hide']) ? 'false' : 'true').', \''.$bboxcenter.'\') RETURNING id');
        $pie_id = pg_fetch_result($result, 0, 0);

        // Adding pieces
        foreach ($coordinates as $c) {
            $result = pg_query($connection, 'INSERT INTO pieces VALUES(DEFAULT, DEFAULT, DEFAULT, '.$pie_id.', \''.$c.'\')');
        }

        // Accesses
        $raccess = array();
        $rwaccess = array();
        foreach (explode(',', $_POST['waccess']) as $username) {
            $username = trim($username);
            if (!empty($username) and $username != $osm_user)
                $rwaccess[] = $username;
        }
        foreach (explode(',', $_POST['raccess']) as $username) {
            $username = trim($username);
            if (!empty($username) and !in_array($username, $rwaccess) and $username != $osm_user)
                $raccess[] = $username;
        }
        // Adding it to table
        foreach ($raccess as $username) {
            $username = pg_escape_string($username);
            $result = pg_query($connection, 'SELECT id FROM users WHERE nick=\''.$username.'\'');
            if (pg_num_rows($result) > 0)
                $user_id = pg_fetch_result($result, 0 ,0);
            else
                $user_id = pg_fetch_result(pg_query($connection, 'INSERT INTO users VALUES(\''.$username.'\', DEFAULT, DEFAULT) RETURNING id'), 0 ,0);
            $result = pg_query($connection, 'INSERT INTO access VALUES('.$user_id.', '.$pie_id.', \''.$username.'\', \'r\')');
        }
        foreach ($rwaccess as $username) {
            $username = pg_escape_string($username);
            $result = pg_query($connection, 'SELECT id FROM users WHERE nick=\''.$username.'\'');
            if (pg_num_rows($result) > 0)
                $user_id = pg_fetch_result($result, 0 ,0);
            else
                $user_id = pg_fetch_result(pg_query($connection, 'INSERT INTO users VALUES(\''.$username.'\', DEFAULT, DEFAULT) RETURNING id'), 0 ,0);
            $result = pg_query($connection, 'INSERT INTO access VALUES('.$user_id.', '.$pie_id.', \''.$username.'\', \'rw\')');
        }
        // And owner
        $result = pg_query($connection, 'SELECT id FROM users WHERE nick=\''.$osm_user.'\'');
        if (pg_num_rows($result) > 0)
            $user_id = pg_fetch_result($result, 0 ,0);
        else
            $user_id = pg_fetch_result(pg_query($connection, 'INSERT INTO users VALUES(\''.$osm_user.'\', DEFAULT, DEFAULT) RETURNING id'), 0 ,0);
        $result = pg_query($connection, 'INSERT INTO access VALUES('.$user_id.', '.$pie_id.', \''.$osm_user.'\', \'o\')');

        update_kml($pie_id);
        create_map($pie_id);

        $pie_link = 'http://'.$_SERVER['HTTP_HOST'].'/pie/'.$pie_id;
        $wms_link = 'wms:http://'.$_SERVER['HTTP_HOST'].'/wms/'.$pie_id.'?';
        echo 'Done!<p>Cake link: <a href="'.$pie_link.'" target="_blank">'.$pie_link.'</a></p><p>WMS link: '.$wms_link.'</p>';
    }
    else {
?>
<form action="" method="post" enctype="multipart/form-data">
<fieldset>
    <div class="row">All data exсluding geometry can be changed later.</div>
    <div class="row"><div>
        <label for="name">Name <em>*</em><br/><small>City, locality etc.</small></label>
        <input type="text" id="name" name="name" />
    </div><div>
        <label for="file">Geometry <em>*</em><br/><small>File in .osm format. It should contain only closed ways without tags. The size must be less then 512Kb. Can be created in JOSM</small></label>
        <input type="file" id="file" name="file" />
    </div></div>
    <div class="row"><div>
        <label for="description">Description</label>
        <textarea id="description" name="description"></textarea>
    </div></div>
    <div class="row"><div>
        <!--<label for="raccess">Access to view<br/><small>List of nicks separated by commas; empty field — all.</small></label>-->
        <input type="hidden" id="raccess" name="raccess" value="" />
    </div><div>
        <!--<label for="waccess">Access to modify<br/><small>List of nicks separated by commas; empty field — all.</small></label>-->
        <input type="hidden" id="waccess" name="waccess" value="" />
    </div></div>
    <div class="row"><div>
        <label><input class="btn" type="checkbox" id="hide" name="hide" />Hidden cake<!--<br/><small>This doesn't affect the access!</small>--></label>
    </div></div>
    <div class="row"><div>
        <label for="captcha">Captcha <em>*</em> <img src="/app/captcha.php" /></label>
        <input type="text" id="captcha" name="captcha" />
    </div></div>
    <input class="btn" type="submit" value="&nbsp Create »" />
</fieldset>
<?php
    }
}
else {
    echo 'Anonymous can\'t create cakes. <a href="/app/auth.php?reload=1" target="_blank">Log in</a>, please.';
}
?>
</form>
