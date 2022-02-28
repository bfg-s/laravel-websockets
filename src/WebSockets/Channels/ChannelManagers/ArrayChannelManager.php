<?php

namespace Bfg\LaravelWebSockets\WebSockets\Channels\ChannelManagers;

use Bfg\LaravelWebSockets\WebSockets\Channels\Channel;
use Bfg\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use Bfg\LaravelWebSockets\WebSockets\Channels\PresenceChannel;
use Bfg\LaravelWebSockets\WebSockets\Channels\PrivateChannel;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Ratchet\ConnectionInterface;

class ArrayChannelManager implements ChannelManager
{
    /** @var string */
    protected $appId;

    /** @var array */
    protected $channels = [];

    public function findOrCreate(string $appId, string $channelName): Channel
    {
        if (! isset($this->channels[$appId][$channelName])) {
            $channelClass = $this->determineChannelClass($channelName);

            $this->channels[$appId][$channelName] = new $channelClass($channelName);
        }

        return $this->channels[$appId][$channelName];
    }

    public function find(string $appId, string $channelName): ?Channel
    {
        return $this->channels[$appId][$channelName] ?? null;
    }

    protected function determineChannelClass(string $channelName): string
    {
        if (Str::startsWith($channelName, 'private-')) {
            return PrivateChannel::class;
        }

        if (Str::startsWith($channelName, 'presence-')) {
            return PresenceChannel::class;
        }

        return Channel::class;
    }

    public function getChannels(string $appId): array
    {
        return $this->channels[$appId] ?? [];
    }

    public function getConnectionCount(string $appId): int
    {
        return collect($this->getChannels($appId))
            ->flatMap(function (Channel $channel) {
                return collect($channel->getSubscribedConnections())->pluck('socketId');
            })
            ->unique()
            ->count();
    }

    public function removeFromAllChannels(ConnectionInterface $connection)
    {
        if (! isset($connection->app)) {
            return;
        }

        /*
         * Remove the connection from all channels.
         */
        collect(Arr::get($this->channels, $connection->app->id, []))->each->unsubscribe($connection);

        /*
         * Unset all channels that have no connections so we don't leak memory.
         */
        collect(Arr::get($this->channels, $connection->app->id, []))
            ->reject->hasConnections()
                    ->each(function (Channel $channel, string $channelName) use ($connection) {
                        unset($this->channels[$connection->app->id][$channelName]);
                    });

        if (count(Arr::get($this->channels, $connection->app->id, [])) === 0) {
            unset($this->channels[$connection->app->id]);
        }
    }
}
