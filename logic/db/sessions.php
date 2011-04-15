<?php

// Session + Pie infos for this user
class Channel {
    public $sesid;
    public $pieid;
    public $nick;
}

class Channels {

    function find($pieid, $sesid) {
        $chan = new Channel();
        $chan->sesid = $sesid;
        $chan->pieid = $pieid;
        $chan->nick = 'Anon_' . $sesid;

        return $chan;
    }
}
$channels = new Channels;
?>