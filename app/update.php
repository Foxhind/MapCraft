<?php
require '../lib/config.php';
require '../lib/update_kml.php';
require '../lib/db/pie_geometry.php';
session_start();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

include '../lib/_header.php';

//
// Check owner rights
//
$result = pg_query("SELECT author FROM pies WHERE id = $id");
$owner_id = pg_fetch_result($result, 0, "author");
$user_id = $_SESSION['user_id'];
if (!isset($user_id) || $owner_id != $user_id) {
    $result = pg_query("SELECT nick FROM users WHERE id = $owner_id");
    $nick = pg_fetch_result($result, 0, "nick");
    echo "Only author of this cake can update it. Ask contributor <a href=\"http://www.openstreetmap.org/user/$nick\" target=\"_blank\">$nick</a> to do this.";
    exit();
}



//
// FIRST STEP: Basic welcome dialog
//
if (!isset($_POST['step']) || $_POST['step'] == 'first') {
?>
    <div id="pageheader" style="background-color: #92836c;">Updating existing cake</div>
    <p>
        Please, follow <strong>carefully</strong> steps below, information there can save your time.
    </p>
    <form class="inline" action="" method="post" enctype="multipart/form-data">
    <input type="hidden" name="step" value="second" />
    <ol class="instructions">
        <li><p>
            Export cake using this link: <a href="/export/<?=$id?>">export</a>.<br/>
            <em class='important'>Note! Do not use old exports or another cakes as base for modification. Do export from link above and make sure, that you are editing exactly this file</em>
        </p></li>
        <li><p>
            Load exported cake into <a href="http://josm.openstreetmap.de/">JOSM</a><br/>
        </p></a>
        </li>
        <li><p>
            Edit cake. You can modify slices, add ,remove them. Also you can modify indexes in the tags.</br>
            <em class='important'>Remember! Do not delete slice without necessity. Any slice you've deleted in JOSM will be also deleted in Mapcraft with all their history</em>
        </p></li>
        <li><p>
            Upload new cake: <input type="file" id="file" name="file" required="required" /></br/>
        </p></li>
        <li><p>
            Run validation: <input class="btn" type="submit" value="Validate" /><br/>
            You will be asked to review changes and confirm dangerous moments if any.
        </p></li>
    </ol>
    </form>
<?php

    include '../lib/_footer.php';
	exit;
}

//
// COMMON for second and third steps
//

try {
    //
    // Detect where is a file to parse
    //
    if (isset($_FILES['file'])) {
        // After first step the file is uploaded
        if ($_FILES['file']['size'] > 600000) {
            throw new Exception("Too big file. It must be less than 512 kb.");
        }
        $filename = tempnam('/tmp', 'uploaded-' . $id . '-');
        if (!copy($_FILES['file']['tmp_name'], $filename)) {
            throw new Exception("Failed to save uploaded file");
        }
    } else {
        // After second step the file is in hidden input field 'filename' pointing to tmp file
        $filename = filter_input(INPUT_POST, 'filename', FILTER_VALIDATE_REGEXP,
                                 array("options" => array("regexp" => "/^\/tmp\/uploaded-\d+-\w+$/")));
    }

    //
    // Parse file with modifications
    //
    $new = new PieGeometry();
    $current = new PieGeometry();
    $steps = array();

    $current->load_from_db($id);
    $new->import_from_osm($filename);
    $steps = $current->get_update_steps($new);
} catch (Exception $e) {
?>
    <div id="pageheader" style="background-color: #92836c;">Can't to parse a new cake</div>
    <div class="error"><?= $e->getMessage() ?></div>
    <p class="small">
        If you are sure there is no error from your side, fill bug at
        <a href="https://github.com/Foxhind/MapCraft/issues?state=open" target="_blank">github.com/Foxhind/MapCraft/issues</a>
        and attach your modified cake.
    </p>
    <br/><p><form class="inline" action="" method="post" enctype="multipart/form-data">
        <input type="hidden" name="step" value="second" />
        Try again:
        <input type="file" id="file" name="file" required="required" />
        <input class="btn" type="submit" value="Validate" />
    </form></p>
<?php
    include '../lib/_footer.php';
    exit;
}

//
// If there are any validation errors, show them
//
$errors = $current->validate_update_steps($steps);
if (count($errors)) {
?>
    <div id="pageheader" style="background-color: #92836c;">Validatiod errors</div>
    <p>
        Following errors are found in your modification:
    </p>
    <ul class="validation-errors-list">
<?php
    foreach($errors as $error) {
        echo "<li><p>" . $error . "</p></li>\n";
    }
?>
    </ul>

    <br/><p><form class="inline" action="" method="post" enctype="multipart/form-data">
        <input type="hidden" name="step" value="second" />
        Try again:
        <input type="file" id="file" name="file" required="required" />
        <input class="btn" type="submit" value="Validate" />
    </form></p>
<?php
    include '../lib/_footer.php';
    exit;
}

//
// SECOND STEP: Confirm changes list
//
if ($_POST['step'] == 'second') {
?>

    <div id="pageheader" style="background-color: #92836c;">Confirm changes</div>
    <p>
       Verify all change plan below:
    </p>
    <ul class="changes-list">
<?php
    foreach ($steps as $step) {
        echo "\n";
        switch($step['action']) {
            case 'create_slice':
                $count = count($step['source']['nodes']);
                $new_index = $step['source']['index'];
                $new_index = $new_index == '-1' ? '[generate]' : $new_index;
                echo "<li class='create'><p>Add new slice with $count points and new index: $new_index</p></li>";
                break;
            case 'delete_slice':
                $index = $current->slices[$step['id']]['index'];
                echo "<li class='delete'><p>Delete slice #$index and all it's history</p></li>";
                break;
            case 'update_slice_geometry':
                $index = $current->slices[$step['id']]['index'];
                $old_count = count($current->slices[$step['id']]['nodes']);
                $count = count($step['source']['nodes']);
                echo "<li class='modify'><p>Update slice #$index geometry: $old_count -> $count nodes</p></li>";
                break;
            case 'update_slice_attrs':
                $index = $current->slices[$step['id']]['index'];
                $new_index = $step['source']['index'];
                $new_index = $new_index == '-1' ? '[generate]' : $new_index;
                echo "<li class='modify'><p>Update slice #$index attributes. New index: $new_index</p></li>";
                break;
        }
        echo "</p></li>\n";
    }
?>
    </ul>
    <p>
    </p>
    <table>
        <tr>
            <td style="padding-left: 2em">
                <form action="" method="post" enctype="multipart/form-data">
                    <fieldset>
                        <input type="hidden" name="step" value="third" />
                        <input type="hidden" name="filename" value="<?= $filename ?>" />
                        <input class="btn" type="submit" value="Confirm" />
                    </fieldset>
                </form>
            </td>
            <td style="padding: 0 2em;">
                <span style="color: gray">or try again</span>
            </td>
            <td>
                <form class="inline" action="" method="post" enctype="multipart/form-data">
                    <fieldset>
                        <input type="hidden" name="step" value="second" />
                        <!-- <label for="file" style="display: inline">try again: </label> -->
                        <input type="file" id="file" name="file" required="required" />
                        <input class="btn" type="submit" value="Upload" />
                    </fieldset>
                </form>
            </td>
        </tr>
    </table>
<?php
    include '../lib/_footer.php';
	exit;
}

//
// THIRD STEP: Apply changes
//
try {
    $current->apply_steps($steps, 0);
    update_kml($current->id);
    system('curl -s -d "" "' . $hub_full_url . '/api/pie/' . $current->id . '/send_refresh_pie_data" >/dev/null');
    echo("Done. Close this window, or return <a href=\"/update/" . $current->id . "\">back</a> to first step.");
} catch (Exception $e) {
?>
    <div id="pageheader" style="background-color: #92836c;">Can't to parse a new cake</div>
    <div class="error"><?= $e->getMessage() ?></div>
    <p class="small">
        Updating failed. Please make sure, that you have modified freshest exported version of the file.
        If you are sure there is no error from your side, fill bug at
        <a href="https://github.com/Foxhind/MapCraft/issues?state=open" target="_blank">github.com/Foxhind/MapCraft/issues</a>
        and attach your modified cake</a>
    </p>
<?php
}

include '../lib/_footer.php';

?>