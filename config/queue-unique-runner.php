<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lock Driver
    |--------------------------------------------------------------------------
    |
    | The driver used to manage distributed job locks. The "database" driver
    | stores locks in a database table, while "redis" uses Redis SET NX
    | with Lua scripts for atomic operations.
    |
    | Supported: "database", "redis"
    |
    */

    'driver' => env('QUEUE_UNIQUE_RUNNER_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Lock Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used for all lock keys across the system (both Redis and Database).
    | This ensures your lock keys don't conflict with other data.
    |
    */
    'prefix' => env('QUEUE_UNIQUE_RUNNER_PREFIX', 'queue-unique-runner'),

    /*
    |--------------------------------------------------------------------------
    | Lock TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds a lock can be held before it is considered
    | expired. After expiration, another server can acquire the lock.
    | Set this higher than your longest expected job duration.
    |
    */
    'ttl' => (int) env('QUEUE_UNIQUE_RUNNER_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Retry Delay
    |--------------------------------------------------------------------------
    |
    | When a job cannot acquire a lock (another instance is running), the
    | job will be released back to the queue and retried after this many
    | seconds. Adjust based on your expected job duration.
    |
    */
    'retry_delay' => (int) env('QUEUE_UNIQUE_RUNNER_RETRY_DELAY', 30),

    /*
    |--------------------------------------------------------------------------
    | Lock Failure Strategy
    |--------------------------------------------------------------------------
    |
    | What to do when a lock cannot be acquired (another instance is running).
    |
    | "release": Release the job back to the queue to be retried later.
    |           This will increment the job attempts count.
    | "delete": Delete the job from the queue immediately (silent skip).
    |           Use this to avoid MaxAttemptsExceededException.
    |
    */
    'on_lock_fail' => env('QUEUE_UNIQUE_RUNNER_ON_LOCK_FAIL', 'release'),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat Configuration
    |--------------------------------------------------------------------------
    |
    | The heartbeat extends the lock TTL periodically while a job is running.
    | This prevents premature lock expiration for long-running jobs and
    | enables crash recovery (if heartbeat stops, the lock expires).
    |
    | Note: Heartbeat requires the pcntl extension (Unix/Linux/macOS only).
    | On Windows or without pcntl, only TTL-based expiration is used.
    |
    */
    'heartbeat' => [
        'enabled' => (bool) env('QUEUE_UNIQUE_RUNNER_HEARTBEAT', true),
        'interval' => (int) env('QUEUE_UNIQUE_RUNNER_HEARTBEAT_INTERVAL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table
    |--------------------------------------------------------------------------
    |
    | The database table name used to store lock records when using the
    | "database" driver. This table is created via the package migration.
    |
    */
    'table' => env('QUEUE_UNIQUE_RUNNER_TABLE', 'queue_unique_runner_locks'),

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Connection setting for the "redis" driver. The connection
    | name should match one defined in your config/database.php redis config.
    |
    */
    'redis' => [
        'connection' => env('QUEUE_UNIQUE_RUNNER_REDIS_CONNECTION', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Identifier
    |--------------------------------------------------------------------------
    |
    | A unique identifier for this server/worker instance. When null, the
    | package auto-detects using hostname:pid. Set this explicitly when
    | running in containerized environments (Docker, Kubernetes).
    |
    */
    'server_id' => env('QUEUE_UNIQUE_RUNNER_SERVER_ID'),

];
