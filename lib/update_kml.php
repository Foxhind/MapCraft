<?php
function update_kml($pie_id) {
    global $connection;
    $color = array ("800000ff","80004cff","800086ff","8000c0ff","8000eeff","8000ffff","8000ffcb","8000ff97","8000ff5f","8000ff00");

    $kml = fopen(dirname(__FILE__).'/../static/kml/'.$pie_id.'.kml', 'w');
    if (!empty($kml)) {
        fwrite($kml, "<?xml version=\"1.0\" encoding=\"utf-8\" ?>
        <kml xmlns=\"http://www.opengis.net/kml/2.1\">
        <Document><Folder><name>".$pie_id."</name>");
        $result = pg_query($connection, 'SELECT pieces.id, users.nick, pieces.state, pieces.coordinates FROM pieces LEFT JOIN users ON users.id = pieces.owner WHERE pieces.pie = '.$pie_id);
        while ($row = pg_fetch_array($result)) {
            fwrite($kml, "<Placemark>
        <name>".$row['id']."</name>
        <description>".$row['state']."</description>
        <owner>".$row['nick']."</owner>
        <Style><LineStyle><color>ff000000</color><width>1</width></LineStyle><PolyStyle><color>".$color[$row['state']]."</color></PolyStyle></Style>
        <Polygon><outerBoundaryIs><LinearRing><coordinates>".$row['coordinates']."</coordinates></LinearRing></outerBoundaryIs></Polygon>
        </Placemark>");
        }
        fwrite($kml, "</Folder></Document></kml>");
        fclose($kml);
    }
}
?>
