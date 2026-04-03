<?php

namespace Bytetcore\QueueUniqueRunner\Tests;

use Bytetcore\QueueUniqueRunner\QueueUniqueRunnerServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            QueueUniqueRunnerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('queue-unique-runner.driver', 'database');
        $app['config']->set('queue-unique-runner.ttl', 300);
        $app['config']->set('queue-unique-runner.retry_delay', 30);
        $app['config']->set('queue-unique-runner.heartbeat.enabled', false);
        $app['config']->set('queue-unique-runner.heartbeat.interval', 30);
        $app['config']->set('queue-unique-runner.table', 'queue_unique_runner_locks');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
