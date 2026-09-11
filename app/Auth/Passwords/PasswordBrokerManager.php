<?php

declare(strict_types=1);

namespace App\Auth\Passwords;

use Illuminate\Auth\Passwords\CacheTokenRepository;
use Illuminate\Auth\Passwords\PasswordBrokerManager as LaravelPasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;

class PasswordBrokerManager extends LaravelPasswordBrokerManager
{
    /**
     * Create a token repository instance based on the given configuration.
     *
     * @param  array<string, mixed>  $config
     */
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        $key = $this->app['config']['app.key'];

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $expires = ($config['expire'] ?? 60) * 60;
        $throttle = $config['throttle'] ?? 0;

        if (isset($config['driver']) && $config['driver'] === 'cache') {
            return new CacheTokenRepository(
                $this->app['cache']->store($config['store'] ?? null),
                $this->app['hash'],
                $key,
                $expires,
                $throttle,
            );
        }

        return new ReusableDatabaseTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            $expires,
            $throttle,
        );
    }
}
