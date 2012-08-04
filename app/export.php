<?php
require '../lib/config.php';
require '../lib/db/pie_geometry.php';


$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {
    $pie_geometry = new PieGeometry();
    $pie_geometry->load_from_db($id);

    // header("Content-type: text/plain");
    // echo $pie_geometry->dump();
    // var_dump($pie_geometry);

    header("Content-type: application/binary");
    header("Content-Disposition: attachment; filename=\"mapcraft-cake-" . $id . ".osm\"");
    echo $pie_geometry->export_to_osm();
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    header("Content-type: text/plain");
    echo ($e->getMessage());
}

?>
