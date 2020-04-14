<?php
namespace Ratchet\Wamp2;

use Ratchet\ConnectionInterface;

class InternalFormatter implements FormatterInterface {
    public function serialize($data) {
        return $data;
    }

    public function deserialize($raw) {
        return $raw;
    }
}
