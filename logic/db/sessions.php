<?php

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

        // role
        $this->_role = isset($_SESSION['secret']) ? 'member' : 'anon';

        session_write_close();
    }

    // returns osm name or anon####
    function nick() {
        isset($this->_nick) || $this->_load_from_session();
        return $this->_nick;
    }

    // returns current role in the pie
    function role() {
        isset($this->_role) || $this->_load_from_session();
        return $this->_role;
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