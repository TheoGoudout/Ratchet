<?php
namespace Ratchet\Wamp2\Client;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp2\InternalFormatter;
use Ratchet\Wamp2\JsonFormatter;
use Ratchet\Wamp2\WampConnection;
use Ratchet\Wamp2\WampConnectionInterface as WAMP;
use Ratchet\Wamp2\WampInterface;

class WampClient implements WampInterface {

    protected $_realm;

    protected $_session;

    protected $_protocols;

    protected $_formatter;

    protected $_connection;

    public function __construct(string $realm, array $config = array(), array $protocols = null)
    {
        $this->_realm = $realm;
        $this->_session = new SessionManager($config);
        $this->_protocols = $protocols ?: [
            '__internal__' => InternalFormatter::class,
            'wamp.2.json' => JsonFormatter::class,
        ];
    }

    public function getSubProtocols() {
        return array_keys($this->_protocols);
    }

    public function onSubProtocolAgreed(ConnectionInterface $conn, string $subprotocol) {
        $classname = $this->_protocols[$subprotocol];
        $this->_formatter = new $classname();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->_connection = new WampConnection($conn, $this->_formatter);
        $this->_session->onOpen($this->_connection);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $from = $this->_connection;
            $json = $from->receive($msg);

            if (!is_array($json) || $json !== array_values($json)) {
                throw new Exception("Invalid WAMP message format");
            }

            switch ($json[0]) {
                case WAMP::MSG_WELCOME:
                    $sessionId = $json[1];
                    $details = $json[2];
                    $this->_session->onWelcome($from, $sessionId, $details);
                break;

                case WAMP::MSG_ABORT:
                    $details = $json[1];
                    $reason = $json[2];
                    $this->_session->onAbort($from, $details, $reason);
                break;

                case WAMP::MSG_GOODBYE:
                    $details = $json[1];
                    $reason = $json[2];
                    $this->_session->onGoodbye($from, $details, $reason);
                break;

                case WAMP::MSG_REGISTERED:
                    $requestId = $json[1];
                    $registrationId = $json[2];
                    $this->_session->onRegistered($from, $requestId, $registrationId);
                break;

                case WAMP::MSG_UNREGISTERED:
                    $requestId = $json[1];
                    $this->_session->onUnregistered($from, $requestId);
                break;

                case WAMP::MSG_RESULT:
                    $requestId = $json[1];
                    $details = $json[2];
                    $arguments  = (array_key_exists(3, $json) ? $json[3] : array());
                    $argumentsKeywords  = (array_key_exists(4, $json) ? $json[4] : null);
                    $this->_session->onResult($from, $requestId, $details, $arguments, $argumentsKeywords);
                break;

                case WAMP::MSG_INVOCATION:
                    $requestId = $json[1];
                    $registrationId = $json[2];
                    $details = $json[3];
                    $arguments  = (array_key_exists(4, $json) ? $json[4] : array());
                    $argumentsKeywords  = (array_key_exists(5, $json) ? $json[5] : null);
                    $this->_session->onInvocation($from, $requestId, $registrationId, $details, $arguments, $argumentsKeywords);
                break;

                case WAMP::MSG_ERROR:
                    $requestType = $json[1];
                    $requestId = $json[2];
                    $details = $json[3];
                    $error = $json[4];
                    $arguments  = (array_key_exists(5, $json) ? $json[5] : array());
                    $argumentsKeywords  = (array_key_exists(6, $json) ? $json[6] : null);
                    $this->_session->onErrorjson($from, $requestType, $requestId, $details, $error, $arguments, $argumentsKeywords);
                break;

                case WAMP::MSG_PUBLISHED:
                    $requestId = $json[1];
                    $publicationId = $json[2];
                    $this->_session->onPublished($from, $requestId, $publicationId);
                break;

                case WAMP::MSG_SUBSCRIBED:
                    $requestId = $json[1];
                    $subscriptionId = $json[2];
                    $this->_session->onSubscribed($from, $requestId, $subscriptionId);
                break;

                case WAMP::MSG_UNSUBSCRIBED:
                    $requestId = $json[1];
                    $this->_session->onUnsubscribed($from, $requestId);
                break;

                default:
                    throw new Exception('Invalid WAMP2 message type');
            }
        } catch (Exception $we) {
            $conn->close(1007);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->_session->onClose($this->_connection);
        $this->_connection = null;
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        return $this->_session->onError($this->_connection, $e);
    }


    public function hello() {
        $this->_session->hello($this->_connection, $this->_realm);
    }

    public function call($procedure, array $arguments = null, $argumentsKeywords = null, callable $callback = null) {
        return $this->_session->call($this->_connection, $procedure, $arguments, $argumentsKeywords, $callback);
    }

    public function publish($topic, array $arguments = null, $argumentsKeywords = null) {
        return $this->_session->publish($this->_connection, $topic, $arguments, $argumentsKeywords);
    }
}
