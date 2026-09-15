<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->ensureIsolatedTestingDatabase();

        parent::setUp();
    }

    /**
     * Guarantee tests never use the normal .env database.
     *
     * php artisan test loads .env before PHPUnit applies phpunit.xml. Cached
     * config can also bake in a real connection. Force sqlite :memory: and
     * drop any config cache before the application boots.
     */
    private function ensureIsolatedTestingDatabase(): void
    {
        $variables = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];

        foreach ($variables as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

        if (is_file($cachedConfig)) {
            unlink($cachedConfig);
        }
    }
}
