<?php
namespace Ratchet\Wamp2\Client;

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


    protected $callee;

    protected $caller;

    protected $publisher;

    protected $subscriber;

    /*
     * $realms string|RealmInterface|array[string]|array[RealmInterface]
     */
    public function __construct(array $config = array()) {
        $config += [
            'callee' => null,
            'caller' => new Caller(),
            'publisher' => new Publisher(),
            'subscriber' => null,
        ];

        if ($config['callee'] instanceof Callee)
            $this->callee = $config['callee'];
        else if ($config['callee'] !== null)
            $this->callee = new Callee($config['callee']);

        if ($config['caller'] instanceof Caller)
            $this->caller = $config['caller'];
        else if ($config['caller'] !== null)
            $this->caller = new Caller($config['caller']);

        if ($config['publisher'] instanceof Publisher)
            $this->publisher = $config['publisher'];
        else if ($config['publisher'] !== null)
            $this->publisher = new Publisher($config['publisher']);

        if ($config['subscriber'] instanceof Subscriber)
            $this->subscriber = $config['subscriber'];
        else if ($config['subscriber'] !== null)
            $this->subscriber = new Subscriber($config['subscriber']);
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

    /* Session Manager methods */
    public function onOpen(ConnectionInterface $conn) {
        $conn->WAMP2->sessionState = static::SESSION_CLOSED;
    }

    public function hello(ConnectionInterface $conn, string $realm) {
        $roles = new \StdClass;
        if ($this->callee) $roles->callee = $this->callee->features();
        if ($this->caller) $roles->caller = $this->caller->features();
        if ($this->publisher) $roles->publisher = $this->publisher->features();
        if ($this->subscriber) $roles->subscriber = $this->subscriber->features();

        $conn->WAMP2->sessionState = static::SESSION_ESTABLISHING;
        $conn->send(array(
            WAMP::MSG_HELLO,
            $realm,
            ['roles' => $roles],
        ));
    }

    public function onWelcome(ConnectionInterface $conn, $sessionId, $details) {
        if ($this->checkProtocolViolation($conn, static::SESSION_ESTABLISHING)) return;

        $conn->WAMP2->sessionState = static::SESSION_ESTABLISHED;
        $conn->WAMP2->sessionId = $sessionId;
        if ($this->callee)
            $this->callee->registerProcedures($conn);
        if ($this->subscriber)
            $this->subscriber->subscribeToTopics($conn);
    }

    public function onAbort(ConnectionInterface $conn, $details, $reason) {
        if ($this->checkProtocolViolation($conn, static::SESSION_ESTABLISHED)) return;

        $conn->WAMP2->sessionState = static::SESSION_ESTABLISHED;
    }

    public function onGoodbye(ConnectionInterface $conn, $details, $reason) {
        if ($this->checkProtocolViolation($conn, static::SESSION_ESTABLISHED)) return;

        $conn->WAMP2->sessionState = static::SESSION_CLOSING;
    }

    public function onClose(ConnectionInterface $conn) {
        unset($conn->WAMP2->sessionState);
    }


    /* Callee methods */
    public function onRegistered(ConnectionInterface $conn, $requestId, $registrationId) {
        if ($this->callee)
            $this->callee->onRegistered($conn, $requestId, $registrationId);
    }
    
    public function onUnregistered(ConnectionInterface $conn, $requestId) {
        if ($this->callee)
            $this->callee->onUnregistered($conn, $requestId);
    }
    
    public function onInvocation(ConnectionInterface $conn, $requestId, $registrationId, $details, array $arguments, $argumentsKeywords) {
        if ($this->callee)
            $this->callee->onInvocation($conn, $requestId, $registrationId, $details, $arguments, $argumentsKeywords);
    }


    /* Caller methods */
    public function call(ConnectionInterface $conn, $procedure, array $arguments, $argumentsKeywords, callable $callback) {
        if ($this->caller)
            $this->caller->call($conn, $procedure, $arguments, $argumentsKeywords, $callback);
    }

    public function onResult(ConnectionInterface $conn, $requestId, $details, array $arguments, $argumentsKeywords) {
        if ($this->caller)
            $this->caller->onResult($conn, $requestId, $details, $arguments, $argumentsKeywords);
    }


    /* Publisher methods */
    public function publish(ConnectionInterface $conn, $topic, array $arguments, $argumentsKeywords) {
        if ($this->publisher)
            $this->publisher->publish($conn, $topic, $arguments, $argumentsKeywords);
    }

    public function onPublished(ConnectionInterface $conn, $requestId, $publicationId) {
        if ($this->publisher)
            $this->publisher->onPublished($conn, $requestId, $publicationId);
    }


    /* Subscriber methods */
    public function onSubscribed(ConnectionInterface $conn, $requestId, $subscriptionId) {
        if ($this->subscriber)
            $this->subscriber->onSubscribed($conn, $requestId, $subscriptionId);
    }
    public function onUnsubscribed(ConnectionInterface $conn, $requestId) {
        if ($this->subscriber)
            $this->subscriber->onUnsubscribed($conn, $requestId);
    }
    public function onEvent(ConnectionInterface $conn, $subscriptionId, $publicationId, $details, array $arguments, $argumentsKeywords) {
        if ($this->subscriber)
            $this->subscriber->onEvent($conn, $subscriptionId, $publicationId, $details, $arguments, $argumentsKeywords);
    }


    /* Error handling methods */
    public function onErrorMessage(ConnectionInterface $conn, $requestType, $requestId, $details, $error, array $arguments, $argumentsKeywords) {
        switch ($requestType) {
            case WAMP::MSG_HELLO:
            case WAMP::MSG_WELCOME:
            case WAMP::MSG_ABORT:
            case WAMP::MSG_GOODBYE:
                /* Session Manager */
                break;
            case WAMP::MSG_PUBLISH:
            case WAMP::MSG_PUBLISHED:
                /* Publisher */
                if ($this->publisher)
                    $this->publisher->onErrorMessage($conn, $requestType, $requestId, $details, $error, $argumentsKeywords, $argumentsKeywords);
                break;
            case WAMP::MSG_SUBSCRIBE:
            case WAMP::MSG_SUBSCRIBED:
            case WAMP::MSG_UNSUBSCRIBE:
            case WAMP::MSG_UNSUBSCRIBED:
            case WAMP::MSG_EVENT:
                /* Subscriber */
                if ($this->subscriber)
                    $this->subscriber->onErrorMessage($conn, $requestType, $requestId, $details, $error, $argumentsKeywords, $argumentsKeywords);
                break;
            case WAMP::MSG_CALL:
            case WAMP::MSG_RESULT:
                /* Caller */
                if ($this->caller)
                    $this->caller->onErrorMessage($conn, $requestType, $requestId, $details, $error, $argumentsKeywords, $argumentsKeywords);
                break;
            case WAMP::MSG_REGISTER:
            case WAMP::MSG_REGISTERED:
            case WAMP::MSG_UNREGISTER:
            case WAMP::MSG_UNREGISTERED:
            case WAMP::MSG_INVOCATION:
            case WAMP::MSG_YIELD:
                /* Callee */
                if ($this->callee)
                    $this->callee->onErrorMessage($conn, $requestType, $requestId, $details, $error, $argumentsKeywords, $argumentsKeywords);
                break;
            default:
                /* Error */
                $this->
                break;
        }
    }
}
