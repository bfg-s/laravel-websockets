<?php

namespace Bfg\LaravelWebSockets\Facades;

use Illuminate\Support\Facades\Facade;

/** @see \Bfg\LaravelWebSockets\Server\Router */
class WebSocketsRouter extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'websockets.router';
    }
}
