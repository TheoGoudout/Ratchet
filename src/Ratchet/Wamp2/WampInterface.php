<?php
namespace Ratchet\Wamp2;

use Ratchet\MessageComponentInterface;
use Ratchet\WebSocket\WsServerInterface;

interface WampInterface extends MessageComponentInterface, WsServerInterface {
}