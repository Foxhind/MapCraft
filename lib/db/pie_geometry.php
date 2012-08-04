<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

// Session + Pie infos for this user
class PieGeometry {
    public $id;
    public $slices;
    public $nodes;

    function load_from_db($id) {
        global $connection;
        $this->id = $id;

        //
        // Load own attrs
        //
        $result = pg_query_params($connection, 'SELECT jcenter FROM pies WHERE id = $1', array($id));
        if (!$result)
            throw new Exception("Failed to query pie data with id = " . $id);
        if (pg_num_rows($result) == 0)
            throw new Exception("Failed to find data for pie with id = " . $id);
        $pie_data = pg_fetch_row($result);

        //
        // Load slices
        //
        $this->slices = array();
        $this->nodes = array();
        $node_index = 1;

        $result = pg_query_params($connection, 'SELECT id, index, coordinates FROM pieces WHERE pie = $1', array($id));
        if (!$result)
            throw new Exception("Failed to query slices data for pie with id = " . $id);

        while ($row = pg_fetch_assoc($result)) {
            $coordinates = array();
            foreach (preg_split("/\s+/", $row['coordinates']) as $pair) {
                if (array_key_exists($pair, $this->nodes)) {
                    $coordinates[] = $this->nodes[$pair];
                } else {
                    $lonlat = preg_split("/,/", $pair);
                    $node_assoc = array(
                                        'lon' => $lonlat[0],
                                        'lat' => $lonlat[1],
                                        'id' => $node_index++
                                        );
                    $coordinates[] = $node_assoc;
                    $this->nodes[$pair] = $node_assoc;
                }
            }

            $this->slices[] = array(
                              'id' => $row['id'],
                              'index' => $row['index'],
                              'nodes' => $coordinates
                              );
        }
    }

    function save_to_db() {
        // TODO
    }

    function import_from_osm() {
    }

    function export_to_osm() {
        $res = "<?xml version='1.0' encoding='UTF-8'?>\n";
        $res .= "<osm version='0.6' upload='false' generator='Mapcraft'>\n";

        //
        // Export nodes
        //
        foreach ($this->nodes as $node) {
            $res .= sprintf("  <node  id='%d' version='1' visible='true' lat='%s' lon='%s' />\n", $node['id'], $node['lat'], $node['lon']);
        }

        // Export slices
        //
        foreach ($this->slices as $slice) {
            $res .= sprintf("  <way id='%d' version='1' visible='true'>\n", $slice['id']);
            foreach ($slice['nodes'] as $node) {
                $res .= sprintf("    <nd ref='%d' />\n", $node['id']);
            }
            //$res .= sprintf("    <tag k='mapcraft:id' v='%d' />\n", $slice['id']);
            $res .= sprintf("    <tag k='mapcraft:index' v='%d' />\n", $slice['index']);
            $res .= "  </way>\n";
        }
        $res .= "</osm>";
        return $res;
    }

    function get_update_steps() {
    }

    function update() {
    }

    function dump() {
        $str = '';
        $str .= sprintf("Pie geometry [id = %d]\n", $this->id );
        $str .= " Nodes\n";
        foreach ($this->nodes as $node) {
            $str .= sprintf("  Node [id = %d], lat = %s lon = %s\n", $node['id'], $node['lat'], $node['lon']);
        }

        $str .= " Slices\n";
        foreach ($this->slices as $slice) {

            $ids = join(', ', array_map(function($node) {return $node['id'];}, $slice['nodes']));
            $str = $str . sprintf("  Slice [id = %d], index = %d, nodes = [%s]\n",
                                  $slice['id'],
                                  $slice['index'],
                                  $ids);

        }
        return $str;
    }

}
?>
