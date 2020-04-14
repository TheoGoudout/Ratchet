<?php
namespace Ratchet\Wamp2;

use Ratchet\AbstractConnectionDecorator;
use Ratchet\ConnectionInterface;

/**
 * A ConnectionInterface object wrapper that is passed to your WAMP2 application
 * representing a client. Methods on this Connection are therefore different.
 * @property \stdClass $WAMP2
 */
class WampConnection extends AbstractConnectionDecorator implements WampConnectionInterface {
    /**
     * {@inheritdoc}
     */
    public function __construct(ConnectionInterface $conn, FormatterInterface $formatter = null) {
        parent::__construct($conn);

        $this->WAMP2 = new \StdClass;
        $this->WAMP2->sessionId = str_replace('.', '', uniqid(mt_rand(), true));

        $this->formatter = $formatter ?: new JsonFormatter();
    }

    /**
     * @internal
     */
    public function send($data) {
        $raw = $this->formatter->serialize($data);
        $this->getConnection()->send($raw);

        return $this;
    }

    public function receive($data) {
        return $this->formatter->deserialize($data);
    }

    /**
     * {@inheritdoc}
     */
    public function close($opt = null) {
        $this->getConnection()->close($opt);
    }

}
