# bytetcore/queue-unique-runner

Ensure your Laravel queue jobs run on only **one server instance at a time** using distributed locking. Includes automatic crash recovery, lock heartbeat, and supports both Database and Redis drivers.

[![Tests](https://github.com/bytetcore/queue-unique-runner/actions/workflows/tests.yml/badge.svg)](https://github.com/bytetcore/queue-unique-runner/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

## The Problem

When running multiple queue workers across different servers, sometimes you have jobs that **must not run concurrently** under any circumstances (e.g., end-of-day financial calculations, syncing large datasets with third-party APIs).

While Laravel's `WithoutOverlapping` middleware is great, if a server crashes midway through a job, the lock gets permanently stuck until manually cleared.

## The Solution

`queue-unique-runner` provides robust distributed locking with:
- **Heartbeat mechanism:** Periodically extends the lock while the job is actively running.
- **Crash recovery:** If a server crashes or the worker is abruptly killed, the heartbeat stops, the lock expires automatically via TTL, and another server can safely retry the job.
- **Per-class or Per-instance locking:** Lock the entire job class, or lock per unique payload.

## Installation

You can install the package via composer:

```bash
composer require bytetcore/queue-unique-runner
```

**If using the Database driver** (the default), publish and run the migrations:

```bash
php artisan vendor:publish --tag="queue-unique-runner-migrations"
php artisan migrate
```

Optionally, publish the config file:

```bash
php artisan vendor:publish --tag="queue-unique-runner-config"
```

## Usage

Simply add the `RunsOnUniqueRunner` trait to your job:

```php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Bytetcore\QueueUniqueRunner\Traits\RunsOnUniqueRunner;

class ProcessFinancialAudit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use RunsOnUniqueRunner; // <-- Add this trait

    public function handle(): void
    {
        // This code is guaranteed to only run on one server at a time.
        // If a server crashes here, the lock automatically expires.
    }
}
```

### Configuration per Job

You can override the default configuration for specific jobs by overriding these methods:

```php
class SyncUserData implements ShouldQueue
{
    use RunsOnUniqueRunner;

    public int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    // Lock scope: 'class' (only one SyncUserData job anywhere) 
    // or 'instance' (one SyncUserData job per unique payload)
    public function queueUniqueRunnerScope(): string
    {
        return 'instance';
    }

    // Custom identifier for 'instance' scope
    public function queueUniqueRunnerIdentifier(): ?string
    {
        return 'user:' . $this->userId;
    }

    // How long the lock should be held (in seconds)
    public function queueUniqueRunnerTtl(): int
    {
        return 600; // 10 minutes
    }

    // How long to wait before retrying if another server holds the lock
    public function queueUniqueRunnerRetryDelay(): int
    {
        return 60; // Wait 60 seconds
    }
}
```

## Drivers

### Database (Default)

Creates a `queue_unique_runner_locks` table. Uses unique constraints to guarantee atomic locks.

It is recommended to periodically run the prune command to clean up expired locks from the database:

```bash
# Add this to your Console/Kernel.php schedule
$schedule->command('queue-unique-runner:prune')->daily();
```

### Redis

Uses `SET NX EX` commands and Lua scripts for atomic operations. Extremely fast and automatically handles expired lock cleanup.

Change your `.env`:

```env
SINGLE_JOB_DRIVER=redis
SINGLE_JOB_REDIS_CONNECTION=default
```

## Requirements

- PHP 8.0+
- Laravel 9.0+
- (Optional but recommended) `pcntl` extension for Heartbeat functionality

## Testing

```bash
composer test
```
