<?php

namespace Bfg\LaravelWebSockets\WebSockets\Messages;

use Bfg\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use Illuminate\Support\Str;
use Ratchet\ConnectionInterface;
use stdClass;

class PusherChannelProtocolMessage implements PusherMessage
{
    /** @var \stdClass */
    protected $payload;

    /** @var \React\Socket\ConnectionInterface */
    protected $connection;

    /** @var \Bfg\LaravelWebSockets\WebSockets\Channels\ChannelManager */
    protected $channelManager;

    public function __construct(stdClass $payload, ConnectionInterface $connection, ChannelManager $channelManager)
    {
        $this->payload = $payload;

        $this->connection = $connection;

        $this->channelManager = $channelManager;
    }

    public function respond()
    {
        $eventName = Str::camel(Str::after($this->payload->event, ':'));

        if (method_exists($this, $eventName) && $eventName !== 'respond') {
            call_user_func([$this, $eventName], $this->connection, $this->payload->data ?? new stdClass());
        }
    }

    /*
     * @link https://pusher.com/docs/pusher_protocol#ping-pong
     */
    protected function ping(ConnectionInterface $connection)
    {
        $connection->send(json_encode([
            'event' => 'pusher:pong',
        ]));
    }

    /*
     * @link https://pusher.com/docs/pusher_protocol#pusher-subscribe
     */
    protected function subscribe(ConnectionInterface $connection, stdClass $payload)
    {
        $channel = $this->channelManager->findOrCreate($connection->app->id, $payload->channel);

        $channel->subscribe($connection, $payload);
    }

    public function unsubscribe(ConnectionInterface $connection, stdClass $payload)
    {
        $channel = $this->channelManager->findOrCreate($connection->app->id, $payload->channel);

        $channel->unsubscribe($connection);
    }
}
