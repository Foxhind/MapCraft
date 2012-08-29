<div id="pageheader" style="background-color: #92836c;">New cake creating</div>
<?php

require '../lib/update_kml.php';
require '../lib/create_map.php';
require '../lib/db/pie_geometry.php';

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


//
// Load and validate passed osm file
//
try {
    $new = new PieGeometry();
    $empty = new PieGeometry();
    $steps = array();

    $new->import_from_osm($_FILES['file']['tmp_name']);
    $steps = $empty->get_create_steps($new);
    $errors = $empty->validate_update_steps($steps);
} catch (Exception $e) {
?>
    <div class="error"><?= $e->getMessage() ?></div>
    <p class="small">
        If you are sure there is no error from your side, fill bug at
        <a href="https://github.com/Foxhind/MapCraft/issues?state=open" target="_blank">github.com/Foxhind/MapCraft/issues</a>
        and attach your osm file.
    </p>
    <p>
        Failed to create a cake. <br /><a href="javascript:history.back();">Back</a>
    </p>
<?php
    exit();
}

if (count($errors)) {
?>
    <p>
        Following errors are found in your osm file:
    </p>
    <ul class="validation-errors-list">
<?php
    foreach($errors as $error) {
        echo "<li><p>" . $error . "</p></li>\n";
    }
?>
    </ul>
    <p>
        Failed to create a cake. <br /><a href="javascript:history.back();">Back</a>
    </p>
<?php
    exit;
}



//
// Actually creating process
//
$result = pg_query_params($connection, 'INSERT INTO pies (name, author, description, visible) VALUES ($1, $2, $3, $4) RETURNING id',
                          array($_POST['name'], $user_id, htmlspecialchars($_POST['description']), isset($_POST['hide']) ? '0' : '1'));
$pie_id = pg_fetch_result($result, 0, 0);

// Adding access for owner
if (!pg_query_params($connection,
                     "INSERT INTO access VALUES ($1, $2, '', 'o')",
                      array($user_id, $pie_id))) {
    echo 'Failed to add access for owner. <br /><a href="javascript:history.back();">Back</a>';
    exit();
}

try {
    $current = new PieGeometry();
    $current->load_from_db($pie_id);
    $current->apply_steps($steps, $user_id);
} catch (Exception $e) {
?>
    <div class="error"><?= $e->getMessage() ?></div>
    <p class="small">
        If you are sure there is no error from your side, fill bug at
        <a href="https://github.com/Foxhind/MapCraft/issues?state=open" target="_blank">github.com/Foxhind/MapCraft/issues</a>
        and attach your osm file.
    </p>
    <p>
        Failed to create a file. <br /><a href="javascript:history.back();">Back</a>
    </p>
<?php
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
