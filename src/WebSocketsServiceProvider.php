<?php

namespace Bfg\LaravelWebSockets;

use Bfg\LaravelWebSockets\Apps\AppProvider;
use Bfg\LaravelWebSockets\Console\WsChannelMakeCommand;
use Bfg\LaravelWebSockets\Dashboard\Http\Controllers\AuthenticateDashboard;
use Bfg\LaravelWebSockets\Dashboard\Http\Controllers\DashboardApiController;
use Bfg\LaravelWebSockets\Dashboard\Http\Controllers\SendMessage;
use Bfg\LaravelWebSockets\Dashboard\Http\Controllers\ShowDashboard;
use Bfg\LaravelWebSockets\Dashboard\Http\Middleware\Authorize as AuthorizeDashboard;
use Bfg\LaravelWebSockets\Helpers\BladeDirective;
use Bfg\LaravelWebSockets\Server\Router;
use Bfg\LaravelWebSockets\Statistics\Http\Controllers\WebSocketStatisticsEntriesController;
use Bfg\LaravelWebSockets\Statistics\Http\Middleware\Authorize as AuthorizeStatistics;
use Bfg\LaravelWebSockets\WebSockets\Channels\ChannelManager;
use Bfg\LaravelWebSockets\WebSockets\Channels\ChannelManagers\ArrayChannelManager;
use Illuminate\Foundation\Console\ChannelMakeCommand;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WebSocketsServiceProvider extends ServiceProvider
{
    /**
     * Boot WebSocket Laravel extension
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/websockets.php' => base_path('config/websockets.php'),
        ], 'websockets-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'websockets-migrations');

        $this->publishes([
            __DIR__ . '/../assets' => public_path('vendor/websockets')
        ], 'websockets-assets');

        $this->publishes([
            __DIR__ . '/../assets' => public_path('vendor/websockets')
        ], 'laravel-assets');

        $this
            ->registerRoutes()
            ->registerDashboardGate();

        $this->loadViewsFrom(__DIR__.'/../resources/views/', 'websockets');

        $this->commands([
            Console\StartWebSocketServer::class,
            Console\CleanStatistics::class,
            Console\RestartWebSocketServer::class,
        ]);

        $this->pusherConfigs();

        \Blade::directive('websocketScripts', [BladeDirective::class, 'directiveScripts']);
        \Blade::directive('websocketInline', [BladeDirective::class, 'directiveInline']);
    }

    /**
     * Register WebSocket abstracts
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/websockets.php', 'websockets');

        $this->app->singleton('websockets.router', function () {
            return new Router();
        });

        $this->app->singleton(ChannelManager::class, function ($app) {
            $config = $app['config']['websockets'];

            return ($config['channel_manager'] ?? null) !== null && class_exists($config['channel_manager'])
                ? app($config['channel_manager']) : new ArrayChannelManager();
        });

        $this->app->singleton(AppProvider::class, function ($app) {
            $config = $app['config']['websockets'];

            return app($config['app_provider']);
        });
    }

    /**
     * Register routes for WebSockets
     * @return $this
     */
    protected function registerRoutes(): static
    {
        Route::prefix(config('websockets.path'))->group(function () {
            Route::middleware(config('websockets.middleware', [AuthorizeDashboard::class]))->group(function () {
                Route::get('/', ShowDashboard::class);
                Route::get('/api/{appId}/statistics', [DashboardApiController::class,  'getStatistics']);
                Route::post('auth', AuthenticateDashboard::class);
                Route::post('event', SendMessage::class);
            });

            Route::middleware(AuthorizeStatistics::class)->group(function () {
                Route::post('statistics', [WebSocketStatisticsEntriesController::class, 'store']);
            });
        });

        return $this;
    }

    /**
     * Gates for dashboard
     * @return $this
     */
    protected function registerDashboardGate(): static
    {
        Gate::define('viewWebSocketsDashboard', function () {
            return app()->environment('local');
        });

        return $this;
    }

    /**
     * Merge pusher configs
     * @return $this
     */
    protected function pusherConfigs(): static
    {
        $def = config('websockets.pusher', []);

        $url = explode("://", config('app.url'));

        $current_port = \Cache::get('ws-current-port', 6001);

        $def['options']['scheme']
            = !isset($def['options']['scheme']) ? ($url[0] ?? 'http') : $def['options']['scheme'];
        $def['options']['host']
            = $url[1] ?? '127.0.0.1';
        $def['options']['port'] = !isset($def['options']['port'])
            ? $current_port : $def['options']['port'];
        $def['options']['encrypted']
            = !isset($def['options']['encrypted']) ? true : $def['options']['encrypted'];

        //ini_set('curl.cainfo', '/usr/local/etc/openssl/cert.pem');

        config(\Arr::dot($def, 'broadcasting.connections.pusher.'));

        return $this;
    }
}
