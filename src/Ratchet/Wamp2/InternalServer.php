<?php
namespace Ratchet\Wamp2;

use Ratchet\Wamp2\Server\WampServer;
use Ratchet\Wamp2\Client\WampClient;

class InternalServer {
    private $server;
    private $serverConn;

    private $client;
    private $clientConn;

    public function __construct(WampServer $server, WampClient $client) {
        $this->server = $server;
        $this->serverConn = new InternalConnection($this);

        $this->client = $client;
        $this->clientConn = new InternalConnection($this);

        /* Find common protocol */
        $protocols = array_intersect($server->getSubProtocols(), $client->getSubProtocols());
        if (empty($protocols))
            throw new Exception("No common protocol could be agreed upon", 1);
        $protocol = $protocols[0];
        $server->onSubProtocolAgreed($this->serverConn, $protocol);
        $client->onSubProtocolAgreed($this->clientConn, $protocol);

        /* Initialize connections */
        $server->onOpen($this->serverConn);
        $client->onOpen($this->clientConn);

        /* Connect client */
        $client->hello();
    }

    public function send(InternalConnection $conn, $data) {
        if ($conn === $this->serverConn) {
            $this->client->onMessage($this->clientConn, $data);
        } else /* if ($conn === $this->clientConn) */ {
            $this->server->onMessage($this->serverConn, $data);
        }
    }

    public function close(InternalConnection $conn) {
        if ($conn === $this->serverConn) {
            $this->client->onClose($this->clientConn);
        } else /* if ($conn === $this->clientConn) */ {
            $this->server->onClose($this->serverConn);
        }
    }
}
