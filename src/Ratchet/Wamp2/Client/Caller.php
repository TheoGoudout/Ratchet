<?php
namespace Ratchet\Wamp2\Client;

use Ratchet\Wamp2\WampConnectionInterface as WAMP;

class Caller {

    private $_requestId = 0;

    private $_callings = array();

    public function features() {
        return new \StdClass();
    }

    public function call(ConnectionInterface $conn, $procedure, array $arguments, $argumentsKeywords, callable $callback = null) {
        $requestId = $this->_requestId++;
        $this->_callings[$requestId] = $callback;
        $data = array(
            WAMP::MSG_CALL,
            $requestId,
            array(),
            $procedure
        );
        if ($arguments !== null) {
            $data[] = $arguments;
            if ($argumentsKeywords !== null) {
                $data[] = $argumentsKeywords;
            }
        }
        $conn->send($data);
    }

    public function onResult(ConnectionInterface $conn, $requestId, $details, array $arguments, $argumentsKeywords) {
        $callback = $this->_callings[$requestId];
        $callback($arguments, $argumentsKeywords);
        unset($this->_callings[$requestId]);
    }
}
