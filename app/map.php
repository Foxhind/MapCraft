<div id="pageheader" style="background-color: #637d8c;">Map</div>
<div id="olmap"></div>
<script type="text/javascript" src="http://openlayers.org/api/OpenLayers.js"></script>
<script type="text/javascript">
var selectControl = null;
function onPopupClose(evt) {
    selectControl.unselect(this.feature);
}
function onFeatureSelect(evt) {
    feature = evt.feature;
    popup = new OpenLayers.Popup.FramedCloud("featurePopup",
        feature.geometry.getBounds().getCenterLonLat(),
        new OpenLayers.Size(100,100),
        '<a href="/pie/' + feature.attributes.id.toString() + '" target="_blank">' + feature.attributes.name + '</a><p>' + feature.attributes.description + '</p>',
        null, true, onPopupClose);
    feature.popup = popup;
    popup.feature = feature;
    olmap.addPopup(popup);
}
function onFeatureUnselect(evt) {
    feature = evt.feature;
    if (feature.popup) {
        popup.feature = null;
        olmap.removePopup(feature.popup);
        feature.popup.destroy();
        feature.popup = null;
    }
}

var options = {controls: [new OpenLayers.Control.Navigation(), new OpenLayers.Control.ScaleLine(), new OpenLayers.Control.Permalink(), new OpenLayers.Control.Attribution(), new OpenLayers.Control.PanZoomBar()],  units: "m", numZoomLevels: 18, maxResolution: 156543.0339, maxExtent: new OpenLayers.Bounds(-20037508, -20037508, 20037508, 20037508.34) };
olmap = new OpenLayers.Map(document.getElementById('olmap'), options);
var mapnik = new OpenLayers.Layer.OSM();
kmllayer = new OpenLayers.Layer.Vector("Pies", { strategies: [new OpenLayers.Strategy.Fixed()], protocol: new OpenLayers.Protocol.HTTP({url: "http://mapcraft.nanodesu.ru/app/json_pies.php", format: new OpenLayers.Format.GeoJSON() })});
kmllayer.events.on({'featureselected': onFeatureSelect, 'featureunselected': onFeatureUnselect});
olmap.addLayers([mapnik, kmllayer]);

selectControl = new OpenLayers.Control.SelectFeature(kmllayer, {clickout: true });
selectControl.handlers.feature.stopDown = false; 

olmap.addControl(selectControl);
selectControl.activate();

if (!olmap.getCenter()) {
    olmap.setCenter(new OpenLayers.LonLat(508764.86018, 3874440.08903), 2);
}
</script>
