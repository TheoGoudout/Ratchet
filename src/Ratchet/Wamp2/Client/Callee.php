<?php
namespace Ratchet\Wamp2\Client;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp2\WampConnectionInterface as WAMP;

class Callee {

    private $_requestId = 0;

    private $_procedures;

    private $_registerings = array();

    private $_unregisterings = array();

    private $_registrations = array();

    public function __construct(array $bindings) {
        foreach ($bindings as $procedure => $callable) {
            if (!is_string($procedure) || !is_callable($callable))
                throw new Exception("Bad binding type");
            $this->_procedures[$procedure] = $callable;                
        }
    }

    public function features() {
        return new \StdClass();
    }

    public function registerProcedures(ConnectionInterface $conn) {
        foreach (array_keys($this->_procedures) as $procedure) {
            $this->register($conn, $procedure);
        }
    }

    protected function register(ConnectionInterface $conn, string $procedure) {
        $requestId = $this->_requestId++;
        $this->_registerings[$requestId] = $procedure;
        $conn->send(array(
            WAMP::MSG_REGISTER,
            $requestId,
            array(),
            $procedure,
        ));
    }

    public function onRegistered(ConnectionInterface $conn, $requestId, $registrationId) {
        $this->_registrations[$registrationId] = $this->_registerings[$requestId];
        unset($this->_registerings[$requestId]);
    }

    public function unregisterProcedures(ConnectionInterface $conn) {
        foreach (array_keys($this->_registrations) as $registrationId) {
            $this->unregister($conn, $registrationId);
        }
    }

    protected function unregister(ConnectionInterface $conn, $registrationId) {
        $requestId = $this->_requestId++;
        $this->_unregisterings[$requestId] = $registrationId;
        $response = $conn->send(array(
            WAMP::MSG_UNREGISTER,
            $requestId,
            $registrationId,
        ));
    }

    public function onUnregistered(ConnectionInterface $conn, $requestId) {
        unset($this->_registrations[$$this->_unregisterings[$requestId]]);
        unset($this->_unregisterings[$requestId]);
    }

    public function onInvocation(ConnectionInterface $conn, $requestId, $registrationId, $details, array $arguments, $argumentsKeywords) {
        if (!isset($this->_registrations[$registrationId])) {
            $conn->send(array(
                WAMP::MSG_ERROR,
                WAMP::MSG_INVOCATION,
                $requestId,
                array(),
                "wamp.error.no_such_registration",
                ["No such registration id found"],
                compact('registrationId'),
            ));
            return;
        }
        $procedure = $this->_registrations[$registrationId];

        if (!isset($this->_registrations[$registrationId])) {
            $conn->send(array(
                WAMP::MSG_ERROR,
                WAMP::MSG_INVOCATION,
                $requestId,
                array(),
                "wamp.error.no_such_procedure",
                ["No such procedure associated to the registration id"],
                compact('registrationId', 'procedure'),
            ));
            return;
        }
        $callable = $this->_procedures[$procedure];

        try {
            $results = null;
            $resultsKeywords = null;
            $callable(
                $arguments, $argumentsKeywords,
                $results, $resultsKeywords
            );
        } catch (\Exception $e) {
            $conn->send(array(
                WAMP::MSG_ERROR,
                WAMP::MSG_INVOCATION,
                $requestId,
                array(),
                "wamp.error.invocation_exception",
                ["An exception occured during procedure invocation"],
                [
                    "message" => $e->getMessage(),
                    "code" => $e->getCode(),
                    // "previous" => $e->getPrevious(),
                    // "file" => $e->getFile(),
                    // "line" => $e->getLine(),
                    // "trace" => $e->getTrace(),
                ],
            ));
            return;
        }
        $data = array(
            WAMP::MSG_YIELD,
            $requestId,
            array(),
        );
        if ($results !== null) {
            $data[] = $results;
            if ($resultsKeywords !== null) {
                $data[] = $resultsKeywords;
            }
        }
        var_dump($data);
        $conn->send($data);
    }
}
