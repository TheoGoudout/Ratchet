<?php
namespace Ratchet\Wamp2\Server;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp2\WampConnectionInterface as WAMP;

class SessionManager {
    /* https://wamp-proto.org/_static/gen/wamp_latest.html#session-statechart */
    const SESSION_CLOSED = 'closed';
    const SESSION_ESTABLISHING = 'establishing';
    const SESSION_CHALLENGING = 'challenging';
    const SESSION_AUTHENTICATING = 'authenticating';
    const SESSION_ESTABLISHED = 'established';
    const SESSION_FAILED = 'failed';
    const SESSION_SHUTTING_DOWN = 'shutting down';
    const SESSION_CLOSING = 'closing';

    protected $realms = array();

    /*
     * $realms string|RealmInterface|array[string]|array[RealmInterface]
     */
    public function __construct($realms) {
        if (!is_array($realms))
            $realms = [$realms];

        foreach ($realms as $realm) {
            if (is_string($realm))
                $realm = new Realm($realm);
            if (!($realm instanceof Realm))
                throw new Exception("Value is not a instance of RealmInterface", 1);
            $this->realms[$realm->name()] = $realm;
        }
    }

    protected static function abort(ConnectionInterface $conn, string $message = null, string $reason = null) {
        if ($conn->WAMP2->sessionState !== static::SESSION_CLOSED) {
            $conn->send(array(WAMP::MSG_ABORT, array('message' => $message), $reason));
        }
        $conn->WAMP2->sessionState = static::SESSION_CLOSED;
        $conn->close();
    }

    protected static function checkProtocolViolation(ConnectionInterface $conn, string $expected) {
        $actual = $conn->WAMP2->sessionState;
        if ($actual !== $expected) {
            self::abort($conn, "Expected state $expected; current state is $actual.", "wamp.error.protocol_violation");
            return true;
        }
        return false;
    }

    public function onOpen(ConnectionInterface $conn) {
        $conn->WAMP2->sessionState = static::SESSION_ESTABLISHING;
    }

    public function onHello(ConnectionInterface $conn, $realm, $details) {
        if (self::checkProtocolViolation($conn, static::SESSION_ESTABLISHING)) return;

        if (!isset($this->realms[$realm])) {
            self::abort($conn, "The realm does not exist.", "wamp.error.no_such_realm");
        } else {
            $conn->WAMP2->sessionState = static::SESSION_ESTABLISHED;
            $realm = $conn->WAMP2->realm = $this->realms[$realm];
            $session = $realm->session($conn, $details);
            $conn->send(array(WAMP::MSG_WELCOME, $conn->WAMP2->sessionId, $session));
        }
    }

    public function onAbort(ConnectionInterface $conn, $details, $reason) {
        $conn->WAMP2->sessionState = static::SESSION_CLOSED;
        $conn->close();
    }

    public function onGoodbye(ConnectionInterface $conn, $details, $reason) {
        if (self::checkProtocolViolation($conn, static::SESSION_ESTABLISHED)) return;

        $conn->send(array(
            WAMP::MSG_GOODBYE,
            array(),
            "wamp.close.goodbye_and_out",
        ));
        $conn->WAMP2->sessionState = static::SESSION_CLOSED;
        $conn->close();
    }

    // Broker methods
    public function onPublish(ConnectionInterface $conn, $requestId, $options, $topicUri, array $arguments, $argumentsKeywords) {
        if (self::checkProtocolViolation($conn, static::SESSION_ESTABLISHED)) return;
        return $conn->WAMP2->realm->onPublish($conn, $requestId, $options, $topicUri, $arguments, $argumentsKeywords);
    }
    public function onSubscribe(ConnectionInterface $conn, $requestId, $options, $topicUri) {
        if (self::checkProtocolViolation($conn, static::SESSION_ESTABLISHED)) return;
        return $conn->WAMP2->realm->onSubscribe($conn, $requestId, $options, $topicUri);
    }
    public function onUnsubscribe(ConnectionInterface $conn, $requestId, $subscriptionId) {
        if (self::checkProtocolViolation($conn, static::SESSION_ESTABLISHED)) return;
        return $conn->WAMP2->realm->onUnsubscribe($conn, $requestId, $subscriptionId);
    }

    // Dealer methods
    public function onRegister(ConnectionInterface $conn, $requestId, $options, $procedure) {
        if (self::checkProtocolViolation($conn, static::SESSION_ESTABLISHED)) return;
        return $conn->WAMP2->realm->onRegister($conn, $requestId, $options, $procedure);
        
    }
    public function onUnregister(ConnectionInterface $conn, $requestId, $registrationId) {
        if (self::checkProtocolViolation($conn, static::SESSION_ESTABLISHED)) return;
        return $conn->WAMP2->realm->onUnregister($conn, $requestId, $registrationId);
        
    }
    public function onCall(ConnectionInterface $conn, $requestId, $options, $procedure, array $arguments, $argumentsKeywords) {
        if (self::checkProtocolViolation($conn, static::SESSION_ESTABLISHED)) return;
        return $conn->WAMP2->realm->onCall($conn, $requestId, $options, $procedure, $arguments, $argumentsKeywords);
        
    }
    public function onYield(ConnectionInterface $conn, $requestId, $options, array $arguments, $argumentsKeywords) {
        if (self::checkProtocolViolation($conn, static::SESSION_ESTABLISHED)) return;
        return $conn->WAMP2->realm->onYield($conn, $requestId, $options, $arguments, $argumentsKeywords);
        
    }

    // Error handling
    public function onErrorMessage(ConnectionInterface $conn, $requestType, $requestId, $details, $error, array $arguments, $argumentsKeywords) {
        $conn->WAMP2->realm->onErrorMessage($conn, $requestType, $requestId, $details, $error, $arguments, $argumentsKeywords);
    }

    public function onClose(ConnectionInterface $conn) {
        if (isset($conn->WAMP2->realm)) {
            $conn->WAMP2->realm->cleanup($conn);
            unset($conn->WAMP2->realm);
        }
        unset($conn->WAMP2->sessionState);
    }
}
