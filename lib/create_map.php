<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

function create_map($pie_id) {
    $map = fopen(dirname(__FILE__).'/../data/wms/'.$pie_id.'.map', 'w');
    if (!empty($map)) {
        fwrite($map, 'MAP

IMAGETYPE PNG24
NAME WMS_server
STATUS ON
SIZE 500 500
EXTENT 37.927869 44.494793 38.167698 44.667131
UNITS METERS
IMAGECOLOR 255 255 255
IMAGEQUALITY 95
IMAGETYPE png
FONTSET ../fonts/fonts.txt

OUTPUTFORMAT
NAME png
DRIVER "GD/PNG"
MIMETYPE "image/png"
IMAGEMODE RGBA
EXTENSION "png"
END


WEB
  IMAGEPATH "/tmp/" 
  IMAGEURL "/tmp/"
  METADATA
    "wms_title"   "WMS Server"
    "wms_onlineresource" "http://mapcraft.nanodesu.ru/cgi-bin/mapserv?map=/srv/www.mapcraft.nanodesu.ru/data/wms/'.$pie_id.'.map&"
    "wms_srs"   "EPSG:4326 EPSG:3857"
    "wms_feature_info_mime_type" "text/html"
    "wms_abstract"      "Pie"
  END
END

PROJECTION
  "init=epsg:4326"
END

LAYER
  NAME "pie"
  METADATA
    "wms_title"  "pie'.$pie_id.'"
  END
  TYPE POLYGON
  STATUS DEFAULT
  CONNECTIONTYPE OGR
  CONNECTION "../../static/kml/'.$pie_id.'.kml"
  PROJECTION
    "init=epsg:4326"
  END
  LABELITEM "name"
  CLASSITEM "description"
    CLASS
		NAME "0"
        EXPRESSION "0"
		STYLE
			WIDTH 3
			OUTLINECOLOR 255 0 0
		END
		LABEL 
			FONT sans
			TYPE truetype
			SIZE 12
			COLOR 255 0 0
		END 
	END
    CLASS
		NAME "1"
        EXPRESSION "1"
		STYLE
			WIDTH 3
			OUTLINECOLOR 255 76 0
		END
		LABEL 
			FONT sans
			TYPE truetype
			SIZE 12
			COLOR 255 76 0
		END 
	END
    CLASS
		NAME "2"
        EXPRESSION "2"
		STYLE
			WIDTH 3
			OUTLINECOLOR 255 134 0
		END
		LABEL 
			FONT sans
			TYPE truetype
			SIZE 12
			COLOR 255 134 0
		END 
	END
    CLASS
		NAME "3"
        EXPRESSION "3"
		STYLE
			WIDTH 3
			OUTLINECOLOR 255 192 0
		END
		LABEL 
			FONT sans
			TYPE truetype
			SIZE 12
			COLOR 255 192 0
		END 
	END
    CLASS
		NAME "4"
        EXPRESSION "4"
		STYLE
			WIDTH 3
			OUTLINECOLOR 255 238 0
		END
		LABEL 
			FONT sans
			TYPE truetype
			SIZE 12
			COLOR 255 238 0
		END 
	END
    CLASS
		NAME "5"
        EXPRESSION "5"
		STYLE
			WIDTH 3
			OUTLINECOLOR 255 255 0
		END
		LABEL 
			FONT sans
			TYPE truetype
			SIZE 12
			COLOR 255 255 0
		END 
	END
    CLASS
		NAME "6"
        EXPRESSION "6"
		STYLE
			WIDTH 3
			OUTLINECOLOR 200 255 0
		END
		LABEL 
			FONT sans
			TYPE truetype
			SIZE 12
			COLOR 200 255 0
		END 
	END
    CLASS
		NAME "7"
        EXPRESSION "7"
		STYLE
			WIDTH 3
			OUTLINECOLOR 150 255 0
		END
		LABEL 
			FONT sans
			TYPE truetype
			SIZE 12
			COLOR 150 255 0
		END 
	END
    CLASS
		NAME "8"
        EXPRESSION "8"
		STYLE
			WIDTH 3
			OUTLINECOLOR 95 255 0
		END
		LABEL 
			FONT sans
			TYPE truetype
			SIZE 12
			COLOR 95 255 0
		END 
	END
    CLASS
		NAME "9"
        EXPRESSION "9"
		STYLE
			WIDTH 3
			OUTLINECOLOR 0 255 0
		END
		LABEL 
			FONT sans
			TYPE truetype
			SIZE 12
			COLOR 0 255 0
		END 
	END
END

END');
        fclose($map);
    }
}
?>
