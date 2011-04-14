<div id="pageheader" style="background-color: #637d8c;">Карта</div>
<div id="olmap"></div>
<script type="text/javascript" src="http://openlayers.org/api/OpenLayers.js"></script>
<script type="text/javascript">
var options = {controls: [new OpenLayers.Control.Navigation(), new OpenLayers.Control.ScaleLine(), new OpenLayers.Control.Permalink(), new OpenLayers.Control.Attribution(), new OpenLayers.Control.PanZoomBar()],  units: "m", numZoomLevels: 18, maxResolution: 156543.0339, maxExtent: new OpenLayers.Bounds(-20037508, -20037508, 20037508, 20037508.34) };
olmap = new OpenLayers.Map(document.getElementById('olmap'), options);
var mapnik = new OpenLayers.Layer.OSM();
kmllayer = new OpenLayers.Layer.Vector("Pies", { strategies: [new OpenLayers.Strategy.Fixed()], protocol: new OpenLayers.Protocol.HTTP({url: "http://mapcraft.nanodesu.ru/pies.txt", format: new OpenLayers.Format.GeoJSON() })});
olmap.addLayers([mapnik, kmllayer]);

var selectCtrl = new OpenLayers.Control.SelectFeature(kmllayer, {clickout: true });
selectCtrl.handlers.feature.stopDown = false; 

olmap.addControl(selectCtrl);
selectCtrl.activate();
olmap.zoomTo(2);

if (!olmap.getCenter()) {olmap.zoomToMaxExtent()}
</script>