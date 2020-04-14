<?php
namespace Ratchet\Wamp2\Server;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp2\WampConnectionInterface as WAMP;

class Realm {
    private $name;
    private $broker;
    private $dealer;

    /*
     * $name string
     * $broker Broker|array
     * $dealer Dealer|array
     */
    public function __construct(string $name, $broker = array(), $dealer = array()) {
        $this->name = $name;

        if ($broker instanceof Broker)
            $this->broker = $broker;
        else if ($broker !== null)
            $this->broker = new Broker($broker);

        if ($dealer instanceof Dealer)
            $this->dealer = $dealer;
        else if ($dealer !== null)
            $this->dealer = new Dealer($dealer);
    }

    public function name() : string {
        return $this->name;
    }

    public function session(ConnectionInterface $conn, $details = null) : array {
        $data = array('roles' => array());
        if ($this->broker)
            $data['roles']['broker'] = $this->broker->features();
        if ($this->dealer)
            $data['roles']['dealer'] = $this->dealer->features();
        return $data;
    }

    public function cleanup(ConnectionInterface $conn) { }

 
    // Broker methods
    public function onPublish(ConnectionInterface $conn, $requestId, $options, $topicUri, array $arguments, $argumentsKeywords) {
        return $this->broker->onPublish($conn, $requestId, $options, $topicUri, $arguments, $argumentsKeywords);
    }
    public function onSubscribe(ConnectionInterface $conn, $requestId, $options, $topicUri) {
        return $this->broker->onSubscribe($conn, $requestId, $options, $topicUri);
    }
    public function onUnsubscribe(ConnectionInterface $conn, $requestId, $subscriptionId) {
        return $this->broker->onUnsubscribe($conn, $requestId, $subscriptionId);
    }


    // Dealer methods
    public function onRegister(ConnectionInterface $conn, $requestId, $options, $procedure) {
        return $this->dealer->onRegister($conn, $requestId, $options, $procedure);
        
    }
    public function onUnregister(ConnectionInterface $conn, $requestId, $registrationId) {
        return $this->dealer->onUnregister($conn, $requestId, $registrationId);
        
    }
    public function onCall(ConnectionInterface $conn, $requestId, $options, $procedure, array $arguments, $argumentsKeywords) {
        return $this->dealer->onCall($conn, $requestId, $options, $procedure, $arguments, $argumentsKeywords);
        
    }
    public function onCancel(ConnectionInterface $conn, $requestId, $options) {
        return $this->dealer->onCancel($conn, $requestId, $options);
        
    }
    public function onYield(ConnectionInterface $conn, $requestId, $options, array $arguments, $argumentsKeywords) {
        return $this->dealer->onYield($conn, $requestId, $options, $arguments, $argumentsKeywords);
        
    }


    // Error handling
    public function onErrorMessage(ConnectionInterface $conn, $requestType, $requestId, $details, $error, array $arguments, $argumentsKeywords) {
        if (in_array($requestType, array(WAMP::MSG_PUBLISH, WAMP::MSG_SUBSCRIBE, WAMP::MSG_UNSUBSCRIBE)))
            return $this->broker->onErrorMessage($conn, $requestType, $requestId, $details, $error, $arguments, $argumentsKeywords);
        else if (in_array($requestType, array(WAMP::MSG_REGISTER, WAMP::MSG_UNREGISTER, WAMP::MSG_INVOCATION)))
            return $this->dealer->onErrorMessage($conn, $requestType, $requestId, $details, $error, $arguments, $argumentsKeywords);

        throw new Exception("Unhandled error message", 1);
    }
}
