<?php
namespace Ratchet\Wamp2;

use Ratchet\ConnectionInterface;

class JsonFormatter implements FormatterInterface {
    public function serialize($data) {
        $raw = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE)
            throw new \Exception(json_last_error_msg(), json_last_error());
        return $raw;
    }

    public function deserialize($raw) {
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE)
            throw new \Exception(json_last_error_msg(), json_last_error());
        return $data;
    }
}
