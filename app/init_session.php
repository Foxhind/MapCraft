<?php
ini_set('session.gc_maxlifetime', 7776000);
ini_set('session.cookie_lifetime', 7776000);
session_set_cookie_params(7776000);
session_start();
echo session_id();
session_write_close();
?>
