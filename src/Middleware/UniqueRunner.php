<?php

namespace Bytetcore\QueueUniqueRunner\Middleware;

use Bytetcore\QueueUniqueRunner\Contracts\LockDriver;
use Bytetcore\QueueUniqueRunner\Support\Heartbeat;
use Bytetcore\QueueUniqueRunner\Support\LockKeyResolver;
use Bytetcore\QueueUniqueRunner\Support\ServerIdentifier;

class UniqueRunner
{
    private string $scope;
    private ?int $ttl;
    private ?string $identifier;

    public function __construct(
        string $scope = 'class',
        ?int $ttl = null,
        ?string $identifier = null
    ) {
        $this->scope = $scope;
        $this->ttl = $ttl;
        $this->identifier = $identifier;
    }

    /**
     * Handle the job through unique-runner locking.
     *
     * @param  object  $job
     * @param  callable  $next
     * @return void
     */
    public function handle(object $job, callable $next): void
    {
        $driver = app(LockDriver::class);
        $keyResolver = app(LockKeyResolver::class);
        $serverIdentifier = app(ServerIdentifier::class);
        $heartbeat = app(Heartbeat::class);

        $key = $keyResolver->resolve($job, $this->resolveScope($job), $this->identifier);
        $serverId = $serverIdentifier->get();
        $ttl = $this->resolveTtl($job);

        if (! $driver->acquire($key, $serverId, $ttl)) {
            $delay = $this->resolveRetryDelay($job);
            $job->release($delay);

            return;
        }

        if ($this->isHeartbeatEnabled($job)) {
            $heartbeat->start($driver, $key, $serverId, $ttl);
        }

        try {
            $next($job);
        } finally {
            $heartbeat->stop();
            $driver->release($key, $serverId);
        }
    }

    private function resolveScope(object $job): string
    {
        if (method_exists($job, 'queueUniqueRunnerScope')) {
            return $job->queueUniqueRunnerScope();
        }

        return $this->scope;
    }

    private function resolveTtl(object $job): int
    {
        if ($this->ttl !== null) {
            return $this->ttl;
        }

        if (method_exists($job, 'queueUniqueRunnerTtl')) {
            $ttl = $job->queueUniqueRunnerTtl();

            if ($ttl !== null) {
                return $ttl;
            }
        }

        return (int) config('queue-unique-runner.ttl', 300);
    }

    private function resolveRetryDelay(object $job): int
    {
        if (method_exists($job, 'queueUniqueRunnerRetryDelay')) {
            return $job->queueUniqueRunnerRetryDelay();
        }

        return (int) config('queue-unique-runner.retry_delay', 30);
    }

    private function isHeartbeatEnabled(object $job): bool
    {
        if (method_exists($job, 'queueUniqueRunnerHeartbeat')) {
            return $job->queueUniqueRunnerHeartbeat();
        }

        return (bool) config('queue-unique-runner.heartbeat.enabled', true);
    }
}
