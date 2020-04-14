<?php
namespace Ratchet\Wamp2\Client;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp2\WampConnectionInterface as WAMP;

class Publisher {

    private $_requestId = 0;

    public function features() {
        return new \StdClass();
    }

    public function publish(ConnectionInterface $conn, $topic, array $arguments, $argumentsKeywords) {
        $requestId = $this->_requestId++;
        $data = array(
            WAMP::MSG_PUBLISH,
            $requestId,
            array(),
            $topic
        );
        if ($arguments !== null) {
            $data[] = $arguments;
            if ($argumentsKeywords !== null) {
                $data[] = $argumentsKeywords;
            }
        }
        $conn->send($data);
    }

    public function onPublished(ConnectionInterface $conn, $requestId, $publicationId) {}

    public function onErrorMessage(ConnectionInterface $conn, $requestType, $requestId, $details, $error, array $arguments, $argumentsKeywords) {}
}
