<?php
namespace Ratchet\Wamp2;

use Ratchet\ConnectionInterface;

class InternalConnection implements ConnectionInterface {

    private $server;

    public function __construct(InternalServer $server) { $this->server = $server; }

    public function send($data) { $this->server->send($this, $data); }

    public function close() { $this->server->close($this); }
}