<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if ($this->app->bound(\App\Support\Tenancy\TenantContext::class)) {
            $this->app->make(\App\Support\Tenancy\TenantContext::class)->clear();
        }
    }

    public function createApplication()
    {
        if (getenv('TESTING_MYSQL') !== '1') {
            foreach ([
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
                'DB_URL' => '',
                'DB_HOST' => '',
                'DB_PORT' => '',
                'DB_USERNAME' => '',
                'DB_PASSWORD' => '',
            ] as $key => $value) {
                putenv($key.'='.$value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        $app = parent::createApplication();

        if (getenv('TESTING_MYSQL') !== '1') {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite.database', ':memory:');
            $app['config']->set('database.connections.sqlite.url', null);
        }

        return $app;
    }
}
