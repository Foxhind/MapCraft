<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

// Session + Pie infos for this user
class PieGeometry {
    public $id;
    public $slices = array();
    public $nodes = array();

    /*
     * -------------------  Loading ---------------------
     */
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
        $nodes_by_pair = array();
        $node_index = 1;

        $result = pg_query_params($connection, 'SELECT id, index, coordinates FROM pieces WHERE pie = $1', array($id));
        if (!$result)
            throw new Exception("Failed to query slices data for pie with id = " . $id);

        while ($row = pg_fetch_assoc($result)) {
            $coordinates = array();
            foreach (preg_split("/\s+/", $row['coordinates']) as $pair) {
                if (array_key_exists($pair, $nodes_by_pair)) {
                    $coordinates[] = $nodes_by_pair[$pair];
                } else {
                    $lonlat = preg_split("/,/", $pair);
                    $node_assoc = array(
                                        'lon' => $lonlat[0],
                                        'lat' => $lonlat[1],
                                        'id' => $node_index
                                        );
                    $coordinates[] = $node_assoc;
                    $nodes_by_pair[$pair] = $node_assoc;
                    $this->nodes[$node_index] = $node_assoc;
                    $node_index++;
                }
            }

            $this->slices[$row['id']] = array(
                                              'id' => $row['id'],
                                              'index' => $row['index'],
                                              'nodes' => $coordinates
                                              );
        }
    }


    /*
     * ------------ Import / export -------------------
     */
    function export_to_osm() {
        $res = "<?xml version='1.0' encoding='UTF-8'?>\n";
        $res .= "<osm version='0.6' upload='false' generator='Mapcraft'>\n";

        // Export nodes
        foreach ($this->nodes as $node) {
            $res .= sprintf("  <node  id='%d' version='1' visible='true' lat='%s' lon='%s' />\n", $node['id'], $node['lat'], $node['lon']);
        }

        // Export slices
        foreach ($this->slices as $slice) {
            $res .= sprintf("  <way id='%d' version='1' visible='true'>\n", $slice['id']);
            foreach ($slice['nodes'] as $node) {
                $res .= sprintf("    <nd ref='%d' />\n", $node['id']);
            }
            $res .= sprintf("    <tag k='mapcraft:index' v='%d' />\n", $slice['index']);
            $res .= "  </way>\n";
        }

        $res .= "</osm>";
        return $res;
    }

    function import_from_osm($filename) {
        // XML parser body
        $this->id = null;
        $this->nodes = array();
        $this->slices = array();
        $this->currentslice = null;

        $parser = xml_parser_create();
        xml_set_element_handler($parser, array($this, '_sax_start_element'), array($this, '_sax_end_element'));
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);

        $data = file_get_contents($filename);
        if (!xml_parse($parser, $data))
            throw new Exception("Failed to parse XML file:" . xml_error_string(xml_get_error_code($parser)).' at line '.xml_get_current_line_number($parser));

        xml_parser_free($parser);

        if (count($this->slices) < 2)
            throw new Exception("Don't be so greedy, cut you cake into more then on slice");
    }

    function _sax_start_element($parser, $name, $attrs)
    {
        //var_dump(array($name, $attrs));
        switch($name)
        {
            case 'node':
                $node = array(
                              'id' => $attrs['id'],
                              'lat' => $attrs['lat'],
                              'lon' => $attrs['lon'],
                             );
                if (isset($attrs['action'])) {
                    $node['action'] = $attrs['action'];
                }
                $this->nodes[$attrs['id']] = $node;
                break;
            case 'way':
                $this->currentslice = array(
                                            'id' => $attrs['id'] ,
                                            'index' => -1,
                                            'nodes' => array(),
                                            );
                if (isset($attrs['action'])) {
                    $this->currentslice['action'] = $attrs['action'];
                }
                break;
            case 'nd':
                $this->currentslice['nodes'][] = $this->nodes[$attrs['ref']];
                break;
            case 'tag':
                switch($attrs['k']) {
                    case 'mapcraft:index':
                        $this->currentslice['index'] = $attrs['v'];
                        break;
                    case 'mapcraft:id':
                        $this->currentslice['id'] = $attrs['v'];
                        break;
                    default:
                        throw new Exception("Unknown OSM tag: " . $attrs['k']);
                }
                break;
            case 'osm':
                break;
            default:
                throw new Exception("Unknown XML node name: " . $name);
        };
    }

    function _sax_end_element($parser, $name) {
        switch ($name) {
            case 'way':
                $this->slices[$this->currentslice['id']] = $this->currentslice;
                $this->currentslice = null;
                break;
         }
    }

    /*
     * ---------------- Updating ---------------------
     */

    function get_update_steps($new) {
        $steps = array();

        // Validate modifications in nodes
        foreach ($new->nodes as $id => $node) {
            if ($id <= 0)
                continue;

            if (!isset($this->nodes[$id]))
                throw new Exception("New geometry contains NODE with ID " . $id . " that is missing in current geometry");

            if (!isset($node['action']))
                if ($node['lat'] != $this->nodes[$id]['lat'] || $node['lon'] != $this->nodes[$id]['lon'])
                    throw new Exception("New geometry contains NODE with ID " . $id ." that has modified coordinates, but is not market as modified");
        }

        // Walk through slices
        foreach ($new->slices as $id => $slice) {
            if ($id < 0) {
                $steps[] = array('action' => 'create_slice', 'source' => $slice);
                continue;
            }

            if (!isset($this->slices[$id]))
                throw new Exception("New geometry contains SLICE with ID " . $id . " that is missing in current geometry");

            if (isset($slice['action']) && $slice['action'] == 'delete') {
                $steps[] = array('action' => 'delete_slice', 'id' => $id);
                continue;
            }

            $geometry_modified = $this->_is_geometry_modified($this->slices[$id], $slice);
            $attrs_modified = $this->_are_attrs_modified($this->slices[$id], $slice);

            if ($geometry_modified) {
                $steps[] = array('action' => 'update_slice_geometry', 'id' => $id, 'source' => $slice);
            }

            if (!isset($slice['action'])) {
                if ($attrs_modified)
                    throw new Exception("New geometry contains SLICE with ID " . $id . " that has updated attributes, but is not market as modified");
                continue;
            }

            if ($slice['action'] == 'modify') {
                if ($attrs_modified)
                    $steps[] = array('action' => 'update_slice_attrs', 'id' => $id, 'source' => $slice);
            } else
                throw new Exception("Unknown action for SLICE with ID " . $id . ": " . $slice['action']);
        }

        return $steps;
    }

    function get_create_steps($new) {
        $steps = array();

        // Walk through slices
        foreach ($new->slices as $id => $slice) {
            if (isset($slice['action']) && $slice['action'] == 'delete') {
                continue;
            }

            $steps[] = array('action' => 'create_slice', 'source' => $slice);
        }
        return $steps;
    }

    function validate_update_steps($steps) {
        $errors = array();

        $index_usage = $this->_calculate_used_indexes($steps);
        foreach ($index_usage as $index => $count) {
            if ($count > 1)
                $errors[] = sprintf("%d slices will have Index %d, but index should be unique", $count, $index);
        }

        return $errors;
    }

    function apply_steps($steps, $applier_id) {
        global $connection;

        if (!$this->id)
            throw new Exception("Can't apply update/create steps on virtual cake");

        $errors = $this->validate_update_steps($steps);
        if (count($errors))
            throw new Exception("Can't apply update steps because of errors in validation");

        $index_usage = $this->_calculate_used_indexes($steps);
        foreach ($steps as $step) {
            switch ($step['action']) {
                case 'delete_slice':
                    $id = $step['id'];
                    pg_query_params('DELETE FROM votes WHERE claim IN (SELECT id FROM claims WHERE piece = $1)', array($id));
                    pg_query_params('DELETE FROM claims WHERE piece = $1', array($id));
                    pg_query_params('DELETE FROM pieces_comments WHERE piece = $1', array($id));
                    pg_query_params('DELETE FROM pieces WHERE id = $1', array($id));
                    break;
                case 'create_slice':
                    $index = $this->_get_index($step['source']['index'], $index_usage);
                    $coords = $this->_get_coords($step['source']['nodes']);
                    pg_query_params('INSERT INTO pieces (pie, index, coordinates) VALUES ($1, $2, $3)',
                                    array($this->id, $index, $coords));
                    break;
                case 'update_slice_geometry':
                    $id = $step['id'];
                    $coords = $this->_get_coords($step['source']['nodes']);
                    pg_query_params('UPDATE pieces SET coordinates = $2 WHERE id = $1',
                                    array($id, $coords));
                    break;
                case 'update_slice_attrs':
                    $id = $step['id'];
                    $index = $this->_get_index($step['source']['index'], $index_usage);
                    pg_query_params('UPDATE pieces SET index = $2 WHERE id = $1',
                                    array($id, $index));
                    break;
                default:
                    throw new Exception("Unknown step update action: " . $step['action']);
                    break;
            }
        }

        // Reload all data
        $this->load_from_db($this->id);
        $center = $this->get_center();
        pg_query_params('UPDATE pies SET jcenter = $2 WHERE id = $1',
                        array($this->id, json_encode(array($center['lon'], $center['lat']))));
    }

    function get_boundingbox() {
        $left = $right = $top = $bottom = NULL;
        foreach ($this->nodes as $node) {
            if (empty($left)   || $left   > $node['lon']) $left   = $node['lon'];
            if (empty($right)  || $right  < $node['lon']) $right  = $node['lon'];
            if (empty($top)    || $top    > $node['lat']) $top    = $node['lat'];
            if (empty($bottom) || $bottom < $node['lat']) $bottom = $node['lat'];
        }
        return array(
            'left'   => $left,
            'top'    => $top,
            'right'  => $right,
            'bottom' => $bottom
        );
    }

    function get_center() {
        $bbox = $this->get_boundingbox();
        return array(
            'lon' => ($bbox['left'] + $bbox['right']) / 2,
            'lat' => ($bbox['top'] + $bbox['bottom']) / 2
        );
    }

    /*
     *  -------- Dumping and printing for debug purporses ------
     */
    // Dumpe pie self. Firstly nodes, then slices
    function dump() {
        $str = '';
        $str .= sprintf("Pie geometry [id = %d]\n", $this->id );
        $str .= " Nodes\n";
        foreach ($this->nodes as $node) {
            $str .= sprintf("  Node [%4d], lat = %-19s lon = %-19s %s\n",
                            $node['id'], $node['lat'], $node['lon'],
                            isset($node['action']) ? $node['action'] : '');
        }

        $str .= " Slices\n";
        foreach ($this->slices as $slice) {

            $ids = join(', ', array_map(function($node) {return $node['id'];}, $slice['nodes']));
            $str = $str . sprintf("  Slice %[4d], index = %3d, nodes = [%s] %s\n",
                                  $slice['id'],
                                  $slice['index'],
                                  $ids,
                                  isset($slice['action']) ? $slice['action'] : '');

        }
        return $str;
    }

    // Dump steps that should be applied to update pie
    function dump_steps($steps) {
        $str = '';
        foreach ($steps as $step) {
            if ($step['action'] == 'create_slice') {
                $str .= "  action = create_slice\n";
            } else {
                $str .= sprintf("  action = %s, id = %s\n", $step['action'], $step['id']);
            }
        }
        return $str;
    }

    /*
     * ---------- Private functions --------------
     */

    // Compare geometry between current slice and new one,
    // and return true if there are new, removed or moved nodes
    function _is_geometry_modified($old_slice, $new_slice) {
        $old_nodes_ids = array_keys($old_slice['nodes']);
        $new_nodes_ids = array_keys($new_slice['nodes']);
        if ($old_nodes_ids != $new_nodes_ids)
            return true;

        foreach ($new_slice['nodes'] as $node) {
            if (isset($node['action']))
                return true;
        }

        return false;
    }

    // Compare attributes between current slice and new one
    // and return true if they are changed
    function _are_attrs_modified($old_slice, $new_slice) {
        return $old_slice['index'] != $new_slice['index'];
    }

    function _inc(&$array, $index) {
        if (!isset($array[$index])) $array[$index] = 0;
        $array[$index] ++;
        return $array[$index];
    }

    function _dec(&$array, $index) {
        if (!isset($array[$index])) $array[$index] = 0;
        $array[$index] --;
        return $array[$index];
    }

    // returns associative array (index => count), where count
    // is a how many times the index will be used if $steps will be applied
    function _calculate_used_indexes($steps) {
        $indexes = array();

        // For current slices set 1 for all
        foreach ($this->slices as $slice)
            $this->_inc($indexes, $slice['index']);

        // change $indexes like they could be changed if each step in $steps were applied
        foreach ($steps as $step) {
            switch ($step['action']) {
                case 'delete_slice':
                    $this->_dec($indexes, $this->slices[$step['id']]['index']);
                    break;
                case 'create_slice':
                    $this->_inc($indexes, $step['source']['index']);
                    break;
                case 'update_slice_attrs':
                    $this->_dec($indexes, $this->slices[$step['id']]['index']);
                    $this->_inc($indexes, $step['source']['index']);
                    break;
                case 'update_slice_geometry':
                    break;
                default:
                    throw new Exception("Unknown step action: " . $step['action']);
            }
        }

        unset($indexes['-1']);
        return $indexes;
    }

    // Walks through range [1..inf)  and returns first index not in $index_usage
    function _get_index($index, &$index_usage) {
        if ($index != -1)
            return $index;
        $index = 1;
        while (isset($index_usage[$index]) && $index_usage[$index] > 0)
            $index++;
        $this->_inc($index_usage, $index);
        return $index;
    }

    function _get_coords($nodes) {
        $pairs = array();
        foreach ($nodes as $node) {
            $pairs[] = $node['lon'] . ',' . $node['lat'];
        }
        return implode(' ', $pairs);
    }
}
?>
