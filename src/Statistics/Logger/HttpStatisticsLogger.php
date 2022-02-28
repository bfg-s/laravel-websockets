<?php

namespace Bfg\LaravelWebSockets\Statistics\Logger;

use Bfg\LaravelWebSockets\Apps\App;
use Bfg\LaravelWebSockets\Statistics\Http\Controllers\WebSocketStatisticsEntriesController;
use Bfg\LaravelWebSockets\Statistics\Statistic;
use Bfg\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use function GuzzleHttp\Psr7\stream_for;
use Ratchet\ConnectionInterface;
use React\Http\Browser;

class HttpStatisticsLogger implements StatisticsLogger
{
    /** @var \Bfg\LaravelWebSockets\Statistics\Statistic[] */
    protected $statistics = [];

    /** @var \Bfg\LaravelWebSockets\WebSockets\Channels\ChannelManager */
    protected $channelManager;

    /** @var \React\Http\Browser */
    protected $browser;

    public function __construct(ChannelManager $channelManager, Browser $browser)
    {
        $this->channelManager = $channelManager;

        $this->browser = $browser;
    }

    public function webSocketMessage(ConnectionInterface $connection)
    {
        $this
            ->findOrMakeStatisticForAppId($connection->app->id)
            ->webSocketMessage();
    }

    public function apiMessage($appId)
    {
        $this
            ->findOrMakeStatisticForAppId($appId)
            ->apiMessage();
    }

    public function connection(ConnectionInterface $connection)
    {
        $this
            ->findOrMakeStatisticForAppId($connection->app->id)
            ->connection();
    }

    public function disconnection(ConnectionInterface $connection)
    {
        $this
            ->findOrMakeStatisticForAppId($connection->app->id)
            ->disconnection();
    }

    protected function findOrMakeStatisticForAppId($appId): Statistic
    {
        if (! isset($this->statistics[$appId])) {
            $this->statistics[$appId] = new Statistic($appId);
        }

        return $this->statistics[$appId];
    }

    public function save()
    {
        foreach ($this->statistics as $appId => $statistic) {
            if (! $statistic->isEnabled()) {
                continue;
            }

            $postData = array_merge($statistic->toArray(), [
                'secret' => App::findById($appId)->secret,
            ]);

            $this
                ->browser
                ->post(
                    action([WebSocketStatisticsEntriesController::class, 'store']),
                    ['Content-Type' => 'application/json'],
                    stream_for(json_encode($postData))
                );

            $currentConnectionCount = $this->channelManager->getConnectionCount($appId);
            $statistic->reset($currentConnectionCount);
        }
    }
}
