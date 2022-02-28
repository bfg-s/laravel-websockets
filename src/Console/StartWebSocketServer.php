<?php

namespace Bfg\LaravelWebSockets\Console;

use Bfg\LaravelWebSockets\Facades\StatisticsLogger;
use Bfg\LaravelWebSockets\Facades\WebSocketsRouter;
use Bfg\LaravelWebSockets\Server\Logger\ConnectionLogger;
use Bfg\LaravelWebSockets\Server\Logger\HttpLogger;
use Bfg\LaravelWebSockets\Server\Logger\WebsocketsLogger;
use Bfg\LaravelWebSockets\Server\WebSocketServerFactory;
use Bfg\LaravelWebSockets\Statistics\DnsResolver;
use Bfg\LaravelWebSockets\Statistics\Logger\StatisticsLogger as StatisticsLoggerInterface;
use Bfg\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use React\Dns\Config\Config as DnsConfig;
use React\Dns\Resolver\Factory as DnsFactory;
use React\Dns\Resolver\ResolverInterface;
use React\EventLoop\Factory as LoopFactory;
use React\Http\Browser;
use React\Socket\Connector;

class StartWebSocketServer extends Command
{
    protected $signature = 'websockets:serve {--host=0.0.0.0} {--port=6001} {--debug : Forces the loggers to be enabled and thereby overriding the app.debug config setting } ';

    protected $description = 'Start the Laravel WebSocket Server';

    /** @var \React\EventLoop\LoopInterface */
    protected $loop;

    /** @var int */
    protected $lastRestart;

    public function __construct()
    {
        parent::__construct();

        $this->loop = LoopFactory::create();
    }

    public function handle()
    {
        if (config('broadcasting.default') !== 'pusher') {

            throw new \Exception(
                <<<ERR
For this extension, you must use the "Pusher" driver. Change your environment variable "BROADCAST_DRIVER" to "pusher".
ERR
            );
        }

        \Cache::set('ws-current-port', $this->option('port'));

        $this
            ->configureStatisticsLogger()
            ->configureHttpLogger()
            ->configureMessageLogger()
            ->configureConnectionLogger()
            ->configureRestartTimer()
            ->registerEchoRoutes()
            ->registerCustomRoutes()
            ->startWebSocketServer();
    }

    protected function configureStatisticsLogger()
    {
        $connector = new Connector($this->loop, [
            'dns' => $this->getDnsResolver(),
            'tls' => [
                'verify_peer' => config('app.env') === 'production',
                'verify_peer_name' => config('app.env') === 'production',
            ],
        ]);

        $browser = new Browser($this->loop, $connector);

        app()->singleton(StatisticsLoggerInterface::class, function ($app) use ($browser) {
            $config = $app['config']['websockets'];
            $class = $config['statistics']['logger'] ?? \Bfg\LaravelWebSockets\Statistics\Logger\HttpStatisticsLogger::class;

            return new $class(app(ChannelManager::class), $browser);
        });

        $this->loop->addPeriodicTimer(config('websockets.statistics.interval_in_seconds'), function () {
            StatisticsLogger::save();
        });

        return $this;
    }

    protected function configureHttpLogger()
    {
        app()->singleton(HttpLogger::class, function ($app) {
            return (new HttpLogger($this->output))
                ->enable($this->option('debug') ?: ($app['config']['app']['debug'] ?? false))
                ->verbose($this->output->isVerbose());
        });

        return $this;
    }

    protected function configureMessageLogger()
    {
        app()->singleton(WebsocketsLogger::class, function ($app) {
            return (new WebsocketsLogger($this->output))
                ->enable($this->option('debug') ?: ($app['config']['app']['debug'] ?? false))
                ->verbose($this->output->isVerbose());
        });

        return $this;
    }

    protected function configureConnectionLogger()
    {
        app()->bind(ConnectionLogger::class, function ($app) {
            return (new ConnectionLogger($this->output))
                ->enable($app['config']['app']['debug'] ?? false)
                ->verbose($this->output->isVerbose());
        });

        return $this;
    }

    public function configureRestartTimer()
    {
        $this->lastRestart = $this->getLastRestart();

        $this->loop->addPeriodicTimer(10, function () {
            if ($this->getLastRestart() !== $this->lastRestart) {
                $this->loop->stop();
            }
        });

        return $this;
    }

    protected function registerEchoRoutes()
    {
        WebSocketsRouter::echo();

        return $this;
    }

    protected function registerCustomRoutes()
    {
        WebSocketsRouter::customRoutes();

        return $this;
    }

    protected function startWebSocketServer()
    {
        $this->info("Starting the WebSocket server on port {$this->option('port')}...");

        $routes = WebSocketsRouter::getRoutes();

        /* 🛰 Start the server 🛰  */
        (new WebSocketServerFactory())
            ->setLoop($this->loop)
            ->useRoutes($routes)
            ->setHost($this->option('host'))
            ->setPort($this->option('port'))
            ->setConsoleOutput($this->output)
            ->createServer()
            ->run();
    }

    protected function getDnsResolver(): ResolverInterface
    {
        if (! config('websockets.statistics.perform_dns_lookup')) {
            return new DnsResolver;
        }

        $dnsConfig = DnsConfig::loadSystemConfigBlocking();

        return (new DnsFactory)->createCached(
            $dnsConfig->nameservers
                ? reset($dnsConfig->nameservers)
                : '1.1.1.1',
            $this->loop
        );
    }

    protected function getLastRestart()
    {
        return Cache::get('bfg:websockets:restart', 0);
    }
}
