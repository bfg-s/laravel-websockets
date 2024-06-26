<?php

namespace Bfg\LaravelWebSockets\Apps;

interface AppProvider
{
    /**  @return array[Bfg\LaravelWebSockets\AppProviders\App] */
    public function all(): array;

    public function findById($appId): ?App;

    public function findByKey(string $appKey): ?App;

    public function findBySecret(string $appSecret): ?App;
}
