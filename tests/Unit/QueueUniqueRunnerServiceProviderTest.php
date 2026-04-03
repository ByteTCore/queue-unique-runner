<?php

namespace Bytetcore\QueueUniqueRunner\Tests\Unit;

use Bytetcore\QueueUniqueRunner\Tests\TestCase;
use Bytetcore\QueueUniqueRunner\QueueUniqueRunnerServiceProvider;
use Bytetcore\QueueUniqueRunner\Contracts\LockDriver;
use Bytetcore\QueueUniqueRunner\Drivers\DatabaseDriver;
use Bytetcore\QueueUniqueRunner\Support\ServerIdentifier;
use Illuminate\Support\Facades\Artisan;

class QueueUniqueRunnerServiceProviderTest extends TestCase
{
    public function test_registers_server_identifier_singleton(): void
    {
        $this->assertTrue($this->app->bound(ServerIdentifier::class));
        $this->assertTrue($this->app->isShared(ServerIdentifier::class));
    }

    public function test_registers_driver(): void
    {
        $this->assertTrue($this->app->bound(LockDriver::class));
        $this->assertInstanceOf(DatabaseDriver::class, $this->app->make(LockDriver::class));
    }

    public function test_merges_config(): void
    {
        $config = config('queue-unique-runner');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('driver', $config);
        $this->assertEquals('database', $config['driver']);
    }

    public function test_registers_commands_in_console(): void
    {
        $commands = Artisan::all();
        $this->assertArrayHasKey('queue-unique-runner:prune', $commands);
    }
}
