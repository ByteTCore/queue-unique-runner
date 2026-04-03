<?php

namespace Bytetcore\QueueUniqueRunner\Drivers;

use Bytetcore\QueueUniqueRunner\Contracts\LockDriver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DatabaseDriver implements LockDriver
{
    private string $table;

    public function __construct()
    {
        $this->table = config('queue-unique-runner.table', 'queue_unique_runner_locks');
    }

    public function acquire(string $key, string $serverId, int $ttl): bool
    {
        $this->cleanExpiredLock($key);

        try {
            DB::table($this->table)->insert([
                'job_key' => $key,
                'server_id' => $serverId,
                'locked_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addSeconds($ttl),
                'heartbeat_at' => Carbon::now(),
            ]);

            return true;
        } catch (QueryException $e) {
            return false;
        }
    }

    public function release(string $key, string $serverId): bool
    {
        return DB::table($this->table)
            ->where('job_key', $key)
            ->where('server_id', $serverId)
            ->delete() > 0;
    }

    public function heartbeat(string $key, string $serverId, int $ttl): bool
    {
        return DB::table($this->table)
            ->where('job_key', $key)
            ->where('server_id', $serverId)
            ->update([
                'heartbeat_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addSeconds($ttl),
            ]) > 0;
    }

    public function forceRelease(string $key): bool
    {
        return DB::table($this->table)
            ->where('job_key', $key)
            ->delete() > 0;
    }

    public function isLocked(string $key): bool
    {
        return DB::table($this->table)
            ->where('job_key', $key)
            ->where('expires_at', '>', Carbon::now())
            ->exists();
    }

    public function getCurrentOwner(string $key): ?string
    {
        $lock = DB::table($this->table)
            ->where('job_key', $key)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        return $lock?->server_id;
    }

    public function pruneExpired(): int
    {
        return DB::table($this->table)
            ->where('expires_at', '<', Carbon::now())
            ->delete();
    }

    private function cleanExpiredLock(string $key): void
    {
        DB::table($this->table)
            ->where('job_key', $key)
            ->where('expires_at', '<', Carbon::now())
            ->delete();
    }
}
