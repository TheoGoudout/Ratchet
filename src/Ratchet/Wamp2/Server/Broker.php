<?php
namespace Ratchet\Wamp2\Server;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp2\WampConnectionInterface as WAMP;

class Broker {
    private $subscriptions = array(); 

    public function features() {
        return array(
            'features' => new \StdClass(),
        );
    }

    public function onSubscribe(ConnectionInterface $conn, $requestId, $options, $topic) {
        // TODO : Add subscription authorization
        $subscriptionId = str_replace('.', '', uniqid(mt_rand(), true));
        $this->subscriptions[$subscriptionId] = array(
            'options' => $options,
            'topic' => $topic,
            'conn' => $conn,
        );
        $conn->send(array(
            WAMP::MSG_SUBSCRIBED,
            $requestId,
            $subscriptionId,
        ));
    }

    public function onUnsubscribe(ConnectionInterface $conn, $requestId, $subscriptionId) {
        if (!isset($this->subscriptions[$subscriptionId])) {
            $conn->send(array(
                WAMP::MSG_ERROR,
                WAMP::MSG_UNSUBSCRIBED,
                $requestId,
                array(),
                "wamp.error.no_such_subscription",
            ));
            return;
        }

        unset($this->subscriptions[$subscriptionId]);
        $conn->send(array(
            WAMP::MSG_UNSUBSCRIBED,
            $requestId,
        ));
    }

    public function onPublish(ConnectionInterface $conn, $requestId, $options, $topic, array $arguments, $argumentsKeywords) {
        foreach ($this->subscriptions as $subscriptionId => $subscription) {
            if ($subscription['conn'] === $conn)
                continue;
            if ($subscription['topic'] !== $topic)
                continue;

            $publicationId = str_replace('.', '', uniqid(mt_rand(), true));
            $data = array(
                WAMP::MSG_EVENT,
                $subscriptionId,
                $publicationId,
                array(),
            );
            if ($arguments !== null) {
                $data[] = $arguments;
                if ($argumentsKeywords !== null) {
                    $data[] = $argumentsKeywords;
                }
            }
            $subscription['conn']->send($data);
        }
        if (isset($options['acknowledge']) && $options['acknowledge']) {
            $conn->send(array(
                WAMP::MSG_PUBLISHED,
                $requestId,
                $publicationId
            ));
        }
    }

    public function onErrorMessage(ConnectionInterface $conn, $requestType, $requestId, $details, $error, array $arguments, $argumentsKeywords) {

    }
}
