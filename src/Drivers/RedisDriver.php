<?php

namespace Bytetcore\QueueUniqueRunner\Drivers;

use Bytetcore\QueueUniqueRunner\Contracts\LockDriver;
use Illuminate\Support\Facades\Redis;

class RedisDriver implements LockDriver
{
    private string $connection;

    public function __construct()
    {
        $this->connection = config('queue-unique-runner.redis.connection', 'default');
    }

    public function acquire(string $key, string $serverId, int $ttl): bool
    {
        $result = Redis::connection($this->connection)
            ->set($key, $serverId, 'EX', $ttl, 'NX');

        return (bool) $result;
    }

    public function release(string $key, string $serverId): bool
    {
        $script = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
LUA;

        return (bool) Redis::connection($this->connection)
            ->eval($script, 1, $key, $serverId);
    }

    public function heartbeat(string $key, string $serverId, int $ttl): bool
    {
        $script = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("expire", KEYS[1], ARGV[2])
else
    return 0
end
LUA;

        return (bool) Redis::connection($this->connection)
            ->eval($script, 1, $key, $serverId, $ttl);
    }

    public function forceRelease(string $key): bool
    {
        return (bool) Redis::connection($this->connection)
            ->del($key);
    }

    public function isLocked(string $key): bool
    {
        return (bool) Redis::connection($this->connection)
            ->exists($key);
    }

    public function getCurrentOwner(string $key): ?string
    {
        $owner = Redis::connection($this->connection)
            ->get($key);

        return $owner ?: null;
    }

    public function pruneExpired(): int
    {
        // Redis handles expiration automatically via TTL
        return 0;
    }


}
