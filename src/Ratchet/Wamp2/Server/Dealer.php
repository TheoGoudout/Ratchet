<?php
namespace Ratchet\Wamp2\Server;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp2\WampConnectionInterface as WAMP;

class Dealer {
    private $registrations = array(); 
    private $procedures = array();
    private $calls = array();

    public function features() {
        return array(
            'features' => new \StdClass(),
        );
    }

    public function onRegister(ConnectionInterface $conn, $requestId, $options, $procedure) {
        // TODO : Add registering authorization
        if (isset($this->procedures[$procedure])) {
            $conn->send(array(
                WAMP::MSG_ERROR,
                WAMP::MSG_REGISTER,
                $requestId,
                array(),
                "wamp.error.procedure_already_exists",
            ));
            return;
        }

        $registrationId = str_replace('.', '', uniqid(mt_rand(), true));
        $this->procedures[$procedure] = $registrationId;
        $this->registrations[$registrationId] = array(
            'options' => $options,
            'procedure' => $procedure,
            'conn' => $conn,
        );
        $conn->send(array(
            WAMP::MSG_REGISTERED,
            $requestId,
            $registrationId,
        ));
    }

    public function onUnregister(ConnectionInterface $conn, $requestId, $registrationId) {
        if (!isset($this->registrations[$registrationId])) {
            $conn->send(array(
                WAMP::MSG_ERROR,
                WAMP::MSG_UNREGISTER,
                $requestId,
                array(),
                "wamp.error.no_such_registration",
            ));
            return;
        }

        unset($this->procedures[$this->registrations[$registrationId]['procedure']]);
        unset($this->registrations[$registrationId]);
        $conn->send(array(
            WAMP::MSG_UNREGISTERED,
            $requestId,
        ));
    }

    public function onCall(ConnectionInterface $conn, $requestId, $options, $procedure, array $arguments, $argumentsKeywords) {
        if (!isset($this->procedures[$procedure])) {
            $data = array(
                WAMP::MSG_ERROR,
                WAMP::MSG_CALL,
                $requestId,
                array(),
                "wamp.error.no_such_procedure",
            );
            if ($arguments !== null) {
                $data[] = $arguments;
                if ($argumentsKeywords !== null) {
                    $data[] = $argumentsKeywords;
                }
            }
            $conn->send($data);
            return;
        }

        $registrationId = $this->procedures[$procedure];
        $this->calls[$requestId] = array(
            'conn' => $conn,
            'options' => $options,
        );
        $data = array(
            WAMP::MSG_INVOCATION,
            $requestId,
            $registrationId,
            array(),
        );
        if ($arguments !== null) {
            $data[] = $arguments;
            if ($argumentsKeywords !== null) {
                $data[] = $argumentsKeywords;
            }
        }
        $this->registrations[$registrationId]['conn']->send($data);
    }

    public function onYield(ConnectionInterface $conn, $requestId, $options, array $arguments, $argumentsKeywords) {
        if (!isset($this->calls[$requestId])) {
            $data = array(
                WAMP::MSG_ERROR,
                WAMP::MSG_YIELD,
                $requestId,
                array(),
                "wamp.error.no_such_call_request",
            );
            if ($arguments !== null) {
                $data[] = $arguments;
                if ($argumentsKeywords !== null) {
                    $data[] = $argumentsKeywords;
                }
            }
            $conn->send($data);
            return;
        }

        $data = array(
            WAMP::MSG_RESULT,
            $requestId,
            array(),
        );
        if ($arguments !== null) {
            $data[] = $arguments;
            if ($argumentsKeywords !== null) {
                $data[] = $argumentsKeywords;
            }
        }
        $this->calls[$requestId]['conn']->send($data);
        unset($this->calls[$requestId]);
    }

    public function onErrorMessage(ConnectionInterface $conn, $requestType, $requestId, $details, $error, array $arguments, $argumentsKeywords) {

    }
}
