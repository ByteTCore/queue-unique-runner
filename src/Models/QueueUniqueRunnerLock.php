<?php

namespace Bytetcore\QueueUniqueRunner\Models;

use Illuminate\Database\Eloquent\Model;

class QueueUniqueRunnerLock extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'job_key',
        'server_id',
        'locked_at',
        'expires_at',
        'heartbeat_at',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
        'heartbeat_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('queue-unique-runner.table', 'queue_unique_runner_locks');
    }

    /**
     * Scope: only expired locks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope: only active (non-expired) locks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope: filter by job key.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $key
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForJob($query, string $key)
    {
        return $query->where('job_key', $key);
    }

    /**
     * Scope: filter by server owner.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $serverId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOwnedBy($query, string $serverId)
    {
        return $query->where('server_id', $serverId);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
