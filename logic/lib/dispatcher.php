<?php

class DispatcherClass
{
    private $table = array();

    public function register($cmd, $cb) {
        $this->table[$cmd] = $cb;
    }

    public function route($cmd, $type, $from, $data) {
        $cb = $this->table[$cmd];
        if(is_null($cb)) {
            throw new Exception("Command '$cmd' is not implemented yet");
        }

        return $cb($cmd, $type, $from, $data);
    }
}
$dispatcher = new DispatcherClass();


?>