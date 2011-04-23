<?php

    // TODO: Move to handle_user_join?
function handle_get_user_list($type, $from, $data, $res) {
    global $connection;

    $user_list = array('user_list', array());
    $claim_list = array('claim_list', array());

    // Buffer array
    $users = array();

    $result = pg_query($connection, 'SELECT pieces.id, users.nick, users.color FROM pieces JOIN users ON users.id = pieces.owner WHERE pie = '.$from->pieid);
    $piece_ids = pg_fetch_all_columns($result, 0);

    // Users who has pieces
    while ($row = pg_fetch_assoc($result)) {
        if (isset($users[$row['nick']]))
            $users[$row['nick']]['owns'][] = $row['id'];
        else
            $users[$row['nick']] = array('owns' => array($row['id']), 'color' => array($row['color']), 'online' => false);
    }
    
    if (count($piece_ids) != 0) {
        $result = pg_query($connection, 'SELECT claims.id, users.nick, users.color, piece, score FROM claims JOIN users ON users.id = claims.author WHERE piece IN ('.implode(',', $piece_ids).')');
        while ($row = pg_fetch_assoc($result)) {
            $claim_list[1][] = array(   'claim_id' => $row['id'],
                                        'piece_id' => $row['piece'],
                                        'vote_balance' => $row['score'],
                                        'owner' => $row['nick'] );
            // Users who has claims
            if (!isset($users[$row['nick']]))
                $users[$row['nick']] = array('owns' => array(),'color' => array($row['color']), 'online' => false);
        }
    }

    // TODO: Adding all online users

    // Generating user_list from userbuffer array
    foreach ($users as $user_nick => $attrs) {
        $user_list[1][] = array('user_nick' => $user_nick,
                                'color' => $attrs['color'],
                                'reserved' => $attrs['owns'],
                                'online' => $attrs['online']);
    }
    
    $res->to_sender($user_list);
    $res->to_sender($claim_list);
}

?>
