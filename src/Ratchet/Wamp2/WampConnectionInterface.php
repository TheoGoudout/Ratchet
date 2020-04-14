<?php
namespace Ratchet\Wamp2;

use Ratchet\ConnectionInterface;

/**
 * WebSocket Application Messaging Protocol
 *
 * @link http://wamp.ws/spec
 * @link https://github.com/oberstet/autobahn-js
 *
 * +--------------+----+------------------+
 * | Message Type | ID | DIRECTION        |
 * |--------------+----+------------------+
 * | HELLO        | 1  |                  |
 * | WELCOME      | 2  |                  |
 * | ABORT        | 3  |                  |
 * | GOODBYE      | 6  |                  |
 * | ERROR        | 8  |                  |
 * | PUBLISH      | 16 |                  |
 * | PUBLISHED    | 17 |                  |
 * | SUBSCRIBE    | 32 |                  |
 * | SUBSCRIBED   | 33 |                  |
 * | UNSUBSCRIBE  | 34 |                  |
 * | UNSUBSCRIBED | 35 |                  |
 * | EVENT        | 36 |                  |
 * | CALL         | 48 |                  |
 * | RESULT       | 50 |                  |
 * | REGISTER     | 64 |                  |
 * | REGISTERED   | 65 |                  |
 * | UNREGISTER   | 66 |                  |
 * | UNREGISTERED | 67 |                  |
 * | INVOCATION   | 68 |                  |
 * | YIELD        | 70 |                  |
 * +--------------+----+------------------+
 */
interface WampConnectionInterface extends ConnectionInterface {
    const MSG_HELLO = 1;
    const MSG_WELCOME = 2;
    const MSG_ABORT = 3;
    const MSG_GOODBYE = 6;
    const MSG_ERROR = 8;
    const MSG_PUBLISH = 16;
    const MSG_PUBLISHED = 17;
    const MSG_SUBSCRIBE = 32;
    const MSG_SUBSCRIBED = 33;
    const MSG_UNSUBSCRIBE = 34;
    const MSG_UNSUBSCRIBED = 35;
    const MSG_EVENT = 36;
    const MSG_CALL = 48;
    const MSG_RESULT = 50;
    const MSG_REGISTER = 64;
    const MSG_REGISTERED = 65;
    const MSG_UNREGISTER = 66;
    const MSG_UNREGISTERED = 67;
    const MSG_INVOCATION = 68;
    const MSG_YIELD = 70;

    /**
     * Use a @Ratchet\Wamp2\FormatterInterface to extract data from raw data.
     * @param $raw mixed
     * @return mixed
     */
    function receive($raw);
}