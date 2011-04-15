<?php

// Session + Pie infos for this user
class Channel {
    public $sesid;
    public $pieid;

    // returns osm name or anon####
    function nick() {
        if( isset($_SESSION['osm_user']) ) {
            return $_SESSION['osm_user'];
        }

        if( ! isset($_SESSION['nick']) ) {
            $_SESSION['nick'] = 'anon' . rand(1000, 9999);
        }
        return $_SESSION['nick'];
    }

    // returns current role in the pie
    function role() {
        if( !isset($_SESSION['secret']) ) {
            return 'anon';
        }
        return 'member';
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