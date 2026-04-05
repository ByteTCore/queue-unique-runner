<?php

namespace Bytetcore\QueueUniqueRunner\Tests\Feature;

use Bytetcore\QueueUniqueRunner\Tests\TestCase;
use Bytetcore\QueueUniqueRunner\Traits\RunsOnUniqueRunner;
use Bytetcore\QueueUniqueRunner\Contracts\LockDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class QueueUniqueRunnerFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Since we are using RefreshDatabase with Orchestra, it automatically runs migrations.
        // We ensure our migrations from the package are loaded.
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_job_can_be_dispatched_and_processed_synchronously(): void
    {
        // Use sync driver to process jobs immediately
        $this->app['config']->set('queue.default', 'sync');

        $job = new TestFeatureJob();
        
        // Assert lock doesn't exist before dispatch
        $driver = $this->app->make(LockDriver::class);
        $key = 'queue-unique-runner:' . TestFeatureJob::class;
        $this->assertFalse($driver->isLocked($key));

        // Dispatch and execute the job
        TestFeatureJob::dispatch();

        // The job should run and set the static flag
        $this->assertTrue(TestFeatureJob::$hasRun);

        // After job completes, lock should be released
        $this->assertFalse($driver->isLocked($key));
    }
}

class TestFeatureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use RunsOnUniqueRunner;

    public static bool $hasRun = false;

    public function queueUniqueRunnerScope(): string
    {
        return 'class';
    }

    public function handle(): void
    {
        // To verify the lock exists WHILE running!
        $driver = app(\Bytetcore\QueueUniqueRunner\Contracts\LockDriver::class);
        $key = 'queue-unique-runner:' . self::class;
        
        if (!$driver->isLocked($key)) {
            throw new \Exception("Lock should exist while job is running!");
        }

        self::$hasRun = true;
    }
}
