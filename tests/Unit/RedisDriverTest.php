<?php

namespace Bytetcore\QueueUniqueRunner\Tests\Unit;

use Bytetcore\QueueUniqueRunner\Drivers\RedisDriver;
use Bytetcore\QueueUniqueRunner\Tests\TestCase;
use Illuminate\Support\Facades\Redis;
use Mockery;

class RedisDriverTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('queue-unique-runner.driver', 'redis');
        $app['config']->set('queue-unique-runner.redis.connection', 'default');
        $app['config']->set('queue-unique-runner.redis.prefix', 'test-queue-unique-runner:');
    }

    public function test_acquire_calls_redis_set_with_nx_ex(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('command')
            ->with('set', ['test-queue-unique-runner:test-key', 'server-1', 'EX', 300, 'NX'])
            ->once()
            ->andReturn(true);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();
        $result = $driver->acquire('test-key', 'server-1', 300);

        $this->assertTrue($result);
    }

    public function test_acquire_returns_false_when_lock_exists(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('command')
            ->with('set', ['test-queue-unique-runner:test-key', 'server-2', 'EX', 300, 'NX'])
            ->once()
            ->andReturn(null);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();
        $result = $driver->acquire('test-key', 'server-2', 300);

        $this->assertFalse($result);
    }

    public function test_release_uses_lua_script_for_atomic_delete(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('eval')
            ->withArgs(function ($script, $numKeys, $key, $serverId) {
                return $numKeys === 1
                    && $key === 'test-queue-unique-runner:test-key'
                    && $serverId === 'server-1'
                    && str_contains($script, 'redis.call("get"')
                    && str_contains($script, 'redis.call("del"');
            })
            ->once()
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();
        $result = $driver->release('test-key', 'server-1');

        $this->assertTrue($result);
    }

    public function test_release_returns_false_when_not_owner(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('eval')
            ->once()
            ->andReturn(0);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();
        $result = $driver->release('test-key', 'wrong-server');

        $this->assertFalse($result);
    }

    public function test_heartbeat_extends_ttl_via_lua_script(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('eval')
            ->withArgs(function ($script, $numKeys, $key, $serverId, $ttl) {
                return $numKeys === 1
                    && $key === 'test-queue-unique-runner:test-key'
                    && $serverId === 'server-1'
                    && $ttl === 600
                    && str_contains($script, 'redis.call("expire"');
            })
            ->once()
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();
        $result = $driver->heartbeat('test-key', 'server-1', 600);

        $this->assertTrue($result);
    }

    public function test_heartbeat_returns_false_when_not_owner(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('eval')
            ->once()
            ->andReturn(0);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();
        $result = $driver->heartbeat('test-key', 'wrong-server', 600);

        $this->assertFalse($result);
    }

    public function test_force_release_deletes_key(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('del')
            ->with('test-queue-unique-runner:test-key')
            ->once()
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();
        $result = $driver->forceRelease('test-key');

        $this->assertTrue($result);
    }

    public function test_is_locked_checks_key_exists(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('exists')
            ->with('test-queue-unique-runner:test-key')
            ->once()
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();

        $this->assertTrue($driver->isLocked('test-key'));
    }

    public function test_is_locked_returns_false_when_missing(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('exists')
            ->with('test-queue-unique-runner:test-key')
            ->once()
            ->andReturn(0);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();

        $this->assertFalse($driver->isLocked('test-key'));
    }

    public function test_get_current_owner_returns_server_id(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('get')
            ->with('test-queue-unique-runner:test-key')
            ->once()
            ->andReturn('server-1');

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();

        $this->assertEquals('server-1', $driver->getCurrentOwner('test-key'));
    }

    public function test_get_current_owner_returns_null_when_missing(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('get')
            ->with('test-queue-unique-runner:test-key')
            ->once()
            ->andReturn(null);

        Redis::shouldReceive('connection')
            ->with('default')
            ->andReturn($connection);

        $driver = new RedisDriver();

        $this->assertNull($driver->getCurrentOwner('test-key'));
    }

    public function test_prune_expired_returns_zero(): void
    {
        $driver = new RedisDriver();

        $this->assertEquals(0, $driver->pruneExpired());
    }
}
