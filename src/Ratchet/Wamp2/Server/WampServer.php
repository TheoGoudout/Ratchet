<?php
namespace Ratchet\Wamp2\Server;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp2\InternalFormatter;
use Ratchet\Wamp2\JsonFormatter;
use Ratchet\Wamp2\WampConnection;
use Ratchet\Wamp2\WampConnectionInterface as WAMP;
use Ratchet\Wamp2\WampInterface;


class WampServer implements WampInterface {
    /**
     * @var array
     */
    protected $_protocols;

    /**
     * @var FormatterInterface
     */
    protected$_formatters; 

    /**
     * @var WampServer
     */
    protected $_session;

    /**
     * @var \SplObjectStorage
     */
    protected $connections;

    /**
     * @param $realms
     */
    public function __construct($realms, array $protocols = null) {
        $this->_protocols = $protocols ?: [
            '__internal__' => InternalFormatter::class,
            'wamp.2.json' => JsonFormatter::class,
        ];
        $this->_formatters = new \SplObjectStorage;
        $this->_session = new SessionManager($realms);
        $this->connections = new \SplObjectStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubProtocols() {
        return array_keys($this->_protocols);
    }

    /**
     * {@inheritdoc}
     */
    public function onSubProtocolAgreed(ConnectionInterface $conn, string $subprotocol) {
        $classname = $this->_protocols[$subprotocol];
        $this->_formatters->attach($conn, new $classname());
    }

    /**
     * {@inheritdoc}
     */
    public function onOpen(ConnectionInterface $conn) {
        $decor = new WampConnection($conn, $this->_formatters[$conn]);
        $this->connections->attach($conn, $decor);

        $this->_session->onOpen($decor);
    }

    /**
     * {@inheritdoc}
     * @throws \App\Command\Wamp2\Exception
     * @throws \App\Command\Wamp2\JsonException
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $from = $this->connections[$from];
            $json = $from->receive($msg);

            if (!is_array($json) || $json !== array_values($json)) {
                throw new Exception("Invalid WAMP message format");
            }

            switch ($json[0]) {
                case WAMP::MSG_HELLO:
                    $realm = $json[1];
                    $details = $json[2];
                    $this->_session->onHello($from, $realm, $details);
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

                case WAMP::MSG_REGISTER:
                    $requestId = $json[1];
                    $options = $json[2];
                    $procedure = $json[3];
                    $this->_session->onRegister($from, $requestId, $options, $procedure);
                break;

                case WAMP::MSG_UNREGISTER:
                    $requestId = $json[1];
                    $registrationId = $json[2];
                    $this->_session->onUnregister($from, $requestId, $registrationId);
                break;

                case WAMP::MSG_CALL:
                    $requestId = $json[1];
                    $options = $json[2];
                    $procedure = $json[3];
                    $arguments  = (array_key_exists(4, $json) ? $json[4] : array());
                    $argumentsKeywords  = (array_key_exists(5, $json) ? $json[5] : null);
                    $this->_session->onCall($from, $requestId, $options, $procedure, $arguments, $argumentsKeywords);
                break;

                case WAMP::MSG_YIELD:
                    $requestId = $json[1];
                    $options = $json[2];
                    $arguments  = (array_key_exists(3, $json) ? $json[3] : array());
                    $argumentsKeywords  = (array_key_exists(4, $json) ? $json[4] : null);
                    $this->_session->onYield($from, $requestId, $options, $arguments, $argumentsKeywords);
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

                case WAMP::MSG_PUBLISH:
                    $requestId = $json[1];
                    $options = $json[2];
                    $topicUri = $json[3];
                    $arguments  = (array_key_exists(4, $json) ? $json[4] : array());
                    $argumentKeywords  = (array_key_exists(5, $json) ? $json[5] : null);
                    $this->_session->onPublish($from, $requestId, $options, $topicUri, $arguments, $argumentKeywords);
                break;

                case WAMP::MSG_SUBSCRIBE:
                    $requestId = $json[1];
                    $options = $json[2];
                    $topicUri = $json[3];
                    $this->_session->onSubscribe($from, $requestId, $options, $topicUri);
                break;

                case WAMP::MSG_UNSUBSCRIBE:
                    $requestId = $json[1];
                    $subscriptionId = $json[2];
                    $this->_session->onUnsubscribe($from, $requestId, $subscriptionId);
                break;

                default:
                    throw new Exception('Invalid WAMP2 message type');
            }
        } catch (Exception $we) {
            $conn->close(1007);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onClose(ConnectionInterface $conn) {
        $decor = $this->connections[$conn];
        $this->connections->detach($conn);

        $this->_session->onClose($decor);
    }

    /**
     * {@inheritdoc}
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        var_dump($e);
        return $this->_session->onError($this->connections[$conn], $e);
    }
}
