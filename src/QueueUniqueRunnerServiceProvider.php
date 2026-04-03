<?php

namespace Bytetcore\QueueUniqueRunner;

use Bytetcore\QueueUniqueRunner\Console\PruneLocksCommand;
use Bytetcore\QueueUniqueRunner\Contracts\LockDriver;
use Bytetcore\QueueUniqueRunner\Drivers\DatabaseDriver;
use Bytetcore\QueueUniqueRunner\Drivers\RedisDriver;
use Bytetcore\QueueUniqueRunner\Support\Heartbeat;
use Bytetcore\QueueUniqueRunner\Support\LockKeyResolver;
use Bytetcore\QueueUniqueRunner\Support\ServerIdentifier;
use Illuminate\Support\ServiceProvider;

class QueueUniqueRunnerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/queue-unique-runner.php', 'queue-unique-runner');

        $this->app->singleton(LockDriver::class, function () {
            $driver = config('queue-unique-runner.driver', 'database');

            return match ($driver) {
                'redis' => new RedisDriver(),
                default => new DatabaseDriver(),
            };
        });

        $this->app->singleton(ServerIdentifier::class, function () {
            return new ServerIdentifier();
        });

        $this->app->singleton(LockKeyResolver::class, function () {
            return new LockKeyResolver();
        });

        $this->app->singleton(Heartbeat::class, function () {
            return new Heartbeat();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/queue-unique-runner.php' => $this->app->configPath('queue-unique-runner.php'),
            ], 'queue-unique-runner-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'queue-unique-runner-migrations');

            $this->commands([
                PruneLocksCommand::class,
            ]);
        }

        if (config('queue-unique-runner.driver') === 'database') {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }
}
