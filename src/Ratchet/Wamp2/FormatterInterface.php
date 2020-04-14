<?php
namespace Ratchet\Wamp2;

use Ratchet\ConnectionInterface;

interface FormatterInterface {
    function serialize($data);
    function deserialize($raw);
}