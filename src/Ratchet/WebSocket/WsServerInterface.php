<?php
namespace Ratchet\WebSocket;

use Ratchet\ConnectionInterface;

/**
 * WebSocket Server Interface
 */
interface WsServerInterface {
    /**
     * If any component in a stack supports a WebSocket sub-protocol return each supported in an array
     * @return array
     * @todo This method may be removed in future version (note that will not break code, just make some code obsolete)
     */
    function getSubProtocols();

    /**
     * Method called when sub protocol is agreed upon during initial handshake.
     * This method will be called before opening the connection
     * @param ConnectionInterface $conn
     * @param string $subprotocol
     */
    function onSubProtocolAgreed(ConnectionInterface $conn, string $subprotocol);
}
