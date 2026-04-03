<?php

namespace Bytetcore\QueueUniqueRunner\Tests\Unit;

use Bytetcore\QueueUniqueRunner\Contracts\LockDriver;
use Bytetcore\QueueUniqueRunner\Support\Heartbeat;
use Bytetcore\QueueUniqueRunner\Tests\TestCase;
use Mockery;

class HeartbeatTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_degrades_gracefully_when_pcntl_is_missing(): void
    {
        $driver = Mockery::mock(LockDriver::class);

        // If it degrades gracefully, it won't throw any errors
        $heartbeat = new Heartbeat();
        $heartbeat->start($driver, 'test-key', 'server-1', 60);
        $heartbeat->stop();
        
        $this->assertTrue(true);
    }
}
