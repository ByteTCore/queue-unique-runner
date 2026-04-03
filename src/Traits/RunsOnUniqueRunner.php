<?php

namespace Bytetcore\QueueUniqueRunner\Traits;

use Bytetcore\QueueUniqueRunner\Middleware\UniqueRunner;

trait RunsOnUniqueRunner
{
    /**
     * Get the middleware the job should pass through.
     *
     * If your job needs additional middleware, override this method
     * and include $this->uniqueRunnerMiddleware() in the array.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [
            $this->uniqueRunnerMiddleware(),
        ];
    }

    /**
     * Create the single instance middleware configured for this job.
     */
    public function uniqueRunnerMiddleware(): UniqueRunner
    {
        return new UniqueRunner(
            scope: $this->queueUniqueRunnerScope(),
            ttl: $this->queueUniqueRunnerTtl(),
            identifier: $this->queueUniqueRunnerIdentifier(),
        );
    }

    /**
     * The lock scope: 'class' for one-per-job-class, 'instance' for one-per-unique-payload.
     */
    public function queueUniqueRunnerScope(): string
    {
        return 'class';
    }

    /**
     * Custom lock TTL in seconds. Return null to use config default.
     */
    public function queueUniqueRunnerTtl(): ?int
    {
        return null;
    }

    /**
     * Custom identifier for instance-scoped locking.
     * Return null to auto-detect from job properties.
     */
    public function queueUniqueRunnerIdentifier(): ?string
    {
        return null;
    }

    /**
     * Delay in seconds before retrying when lock cannot be acquired.
     */
    public function queueUniqueRunnerRetryDelay(): int
    {
        return (int) config('queue-unique-runner.retry_delay', 30);
    }

    /**
     * Whether heartbeat should be enabled for this job.
     */
    public function queueUniqueRunnerHeartbeat(): bool
    {
        return (bool) config('queue-unique-runner.heartbeat.enabled', true);
    }
}
