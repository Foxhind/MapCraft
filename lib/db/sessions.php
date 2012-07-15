<?php
/* This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

// Session + Pie infos for this user
class Channel {
    public $sesid;
    public $pieid;

    function _load_from_session() {
        session_id($this->sesid);
        @session_start();

        // nick
        if( isset($_SESSION['osm_user']) ) {
            $this->_nick = $_SESSION['osm_user'];
        } else {
            if( ! isset($_SESSION['nick']) ) {
                $_SESSION['nick'] = 'anon' . rand(1000, 9999);
            }
            $this->_nick = $_SESSION['nick'];
        }

        // anon_nick
        $this->_anon_nick = isset($_SESSION['nick']) ? $_SESSION['nick'] : 'anon???';

        // id
        $this->_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0';

        // role
        $this->_role = isset($_SESSION['user_id']) ? 'member' : 'anon';

        session_write_close();
    }

    // returns osm name or anon####
    function nick() {
        isset($this->_nick) || $this->_load_from_session();
        return $this->_nick;
    }

    // returns osm name or anon####
    function anon_nick() {
        isset($this->_anon_nick) || $this->_load_from_session();
        return $this->_anon_nick;
    }

    // returns current role in the pie
    function role() {
        isset($this->_role) || $this->_load_from_session();
        return $this->_role;
    }

    // returns current user id
    function user_id() {
        isset($this->_user_id) || $this->_load_from_session();
        return $this->_user_id;
    }

    function need_level($min_role) {
        $levels = array( "anon" => 0,
                         "member" => 10,
                         "moderator" => 20,
                         "owner" => 30,
                         "developer" => 40 );
        $cur_role = $this->role();
        if ($levels[$min_role] <= $levels[$cur_role]) {
            return;
        }

        // Ok, we have no rights, do checks
        if ($min_role == "member") {
            throw new Exception("Please, <a href='/app/auth.php' target='_blank'>log in</a> to access this feature");
        }
        throw new Exception("You need $min_role rights to access this feature");
    }
}

// Collection of channels
class Channels {

    function find($pieid, $sesid) {
        $chan = new Channel();
        $chan->sesid = $sesid;
        $chan->pieid = $pieid;

        return $chan;
    }
}
$channels = new Channels;
?>
