<?php
namespace Ratchet\Wamp2\Client;

use Ratchet\Wamp2\WampConnectionInterface as WAMP;

class Subscriber {

    private $_requestId = 0;

    private $_topics;

    private $_subscribings = array();

    private $_unsubscribings = array();

    private $_subscriptions = array();

    public function __construct(array $bindings) {
        foreach ($bindings as $topic => $callable) {
            if (!is_string($topic) || !is_callable($callable))
                throw new Exception("Bad binding type");
            $this->_topics[$topic] = $callable;                
        }
    }

    public function features() {
        return new \StdClass();
    }

    public function subscribeToTopics(ConnectionInterface $conn) {
        foreach (array_keys($this->_topic) as $topics) {
            $this->subscribe($conn, $topic);
        }
    }

    protected function subscribe(ConnectionInterface $conn, string $topic) {
        $requestId = $this->_requestId++;
        $this->_subscribings[$requestId] = $topic;
        $conn->send(array(
            WAMP::MSG_SUBSCRIBE,
            $requestId,
            array(),
            $topic,
        ));
    }

    public function onSubscribed(ConnectionInterface $conn, $requestId, $subscriptionId) {
        $this->_subscriptions[$subscriptionId] = $this->_subscribings[$requestId];
        unset($this->_subscribings[$requestId]);
    }

    public function unsubscribeFromTopics(ConnectionInterface $conn) {
        foreach (array_keys($this->_subscriptions) as $subscriptionId) {
            $this->unsubscribe($conn, $subscriptionId);
        }
    }

    protected function unsubscribe(ConnectionInterface $conn, $subscriptionId) {
        $requestId = $this->_requestId++;
        $this->_unsubscribings[$requestId] = $subscriptionId;
        $conn->send(array(
            WAMP::MSG_UNSUBSCRIBE,
            $requestId,
            $subscriptionId,
        ));
    }

    public function onUnsubscribed(ConnectionInterface $conn, $requestId) {
        unset($this->_subscriptions[$this->_unsubscribings[$requestId]]);
        unset($this->_unsubscribings[$requestId]);
    }

    public function onEvent(ConnectionInterface $conn, $subscriptionId, $publicationId, $details, array $arguments, $argumentsKeywords) {
        if (!isset($this->_subscriptions[$subscriptionId])) {
            user_error("No such subscription id found", $subscriptionId);
            return;
        }
        $topic = $this->_subscriptions[$subscriptionId];

        if (!isset($this->_topics[$topic])) {
            user_error("No such topic associated to the subscription id", $topic, $subscriptionId);
            return;
        }
        $callable = $this->_topics[$topic];

        try {
            $callable($arguments, $argumentsKeywords);
        } catch (\Exception $e) {
            user_error("An exception occured during topic callback", $e);
            return;
        }
    }
}
