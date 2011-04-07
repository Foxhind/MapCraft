<?php

class DispatcherClass
{
    private $table = array();

    public function register($cmd, $cb) {
        $this->table[$cmd] = $cb;
    }

    public function route($cmd, $type, $from, $data) {
        if(!isset($this->table[$cmd])) {
            throw new Exception("Command '$cmd' is not implemented yet");
        }

        $cb = $this->table[$cmd];
        return $cb($cmd, $type, $from, $data);
    }
}
$dispatcher = new DispatcherClass();


?>