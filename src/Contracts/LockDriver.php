<?php

namespace Bytetcore\QueueUniqueRunner\Contracts;

interface LockDriver
{
    /**
     * Attempt to acquire a lock for the given key.
     */
    public function acquire(string $key, string $serverId, int $ttl): bool;

    /**
     * Release a lock owned by the given server.
     */
    public function release(string $key, string $serverId): bool;

    /**
     * Extend the lock TTL (heartbeat).
     */
    public function heartbeat(string $key, string $serverId, int $ttl): bool;

    /**
     * Force release a lock regardless of owner.
     */
    public function forceRelease(string $key): bool;

    /**
     * Check if a lock is currently held (not expired).
     */
    public function isLocked(string $key): bool;

    /**
     * Get the server ID of the current lock owner.
     */
    public function getCurrentOwner(string $key): ?string;

    /**
     * Remove all expired locks. Returns the number of locks pruned.
     */
    public function pruneExpired(): int;
}
