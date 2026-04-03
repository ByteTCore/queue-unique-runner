<?php

namespace Bytetcore\QueueUniqueRunner\Tests\Unit;

use Bytetcore\QueueUniqueRunner\Drivers\DatabaseDriver;
use Bytetcore\QueueUniqueRunner\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class DatabaseDriverTest extends TestCase
{
    private DatabaseDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new DatabaseDriver();
    }

    public function test_can_acquire_lock(): void
    {
        $result = $this->driver->acquire('test-job', 'server-1', 300);

        $this->assertTrue($result);
        $this->assertDatabaseHas('queue_unique_runner_locks', [
            'job_key' => 'test-job',
            'server_id' => 'server-1',
        ]);
    }

    public function test_cannot_acquire_same_lock_twice(): void
    {
        $this->driver->acquire('test-job', 'server-1', 300);
        $result = $this->driver->acquire('test-job', 'server-2', 300);

        $this->assertFalse($result);
    }

    public function test_same_server_cannot_acquire_same_lock_twice(): void
    {
        $this->driver->acquire('test-job', 'server-1', 300);
        $result = $this->driver->acquire('test-job', 'server-1', 300);

        $this->assertFalse($result);
    }

    public function test_can_acquire_different_locks(): void
    {
        $result1 = $this->driver->acquire('job-1', 'server-1', 300);
        $result2 = $this->driver->acquire('job-2', 'server-2', 300);

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    public function test_can_acquire_expired_lock(): void
    {
        DB::table('queue_unique_runner_locks')->insert([
            'job_key' => 'test-job',
            'server_id' => 'server-1',
            'locked_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5),
            'heartbeat_at' => now()->subMinutes(10),
        ]);

        $result = $this->driver->acquire('test-job', 'server-2', 300);

        $this->assertTrue($result);
        $this->assertDatabaseHas('queue_unique_runner_locks', [
            'job_key' => 'test-job',
            'server_id' => 'server-2',
        ]);
    }

    public function test_cannot_acquire_non_expired_lock(): void
    {
        DB::table('queue_unique_runner_locks')->insert([
            'job_key' => 'test-job',
            'server_id' => 'server-1',
            'locked_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'heartbeat_at' => now(),
        ]);

        $result = $this->driver->acquire('test-job', 'server-2', 300);

        $this->assertFalse($result);
    }

    public function test_can_release_own_lock(): void
    {
        $this->driver->acquire('test-job', 'server-1', 300);
        $result = $this->driver->release('test-job', 'server-1');

        $this->assertTrue($result);
        $this->assertDatabaseMissing('queue_unique_runner_locks', [
            'job_key' => 'test-job',
        ]);
    }

    public function test_cannot_release_other_servers_lock(): void
    {
        $this->driver->acquire('test-job', 'server-1', 300);
        $result = $this->driver->release('test-job', 'server-2');

        $this->assertFalse($result);
        $this->assertDatabaseHas('queue_unique_runner_locks', [
            'job_key' => 'test-job',
            'server_id' => 'server-1',
        ]);
    }

    public function test_release_nonexistent_lock_returns_false(): void
    {
        $result = $this->driver->release('nonexistent', 'server-1');

        $this->assertFalse($result);
    }

    public function test_can_heartbeat_own_lock(): void
    {
        $this->driver->acquire('test-job', 'server-1', 60);

        $originalLock = DB::table('queue_unique_runner_locks')->where('job_key', 'test-job')->first();

        // Small delay to ensure timestamp difference
        usleep(100000);

        $result = $this->driver->heartbeat('test-job', 'server-1', 600);

        $this->assertTrue($result);

        $updatedLock = DB::table('queue_unique_runner_locks')->where('job_key', 'test-job')->first();

        $this->assertNotEquals($originalLock->expires_at, $updatedLock->expires_at);
    }

    public function test_cannot_heartbeat_other_servers_lock(): void
    {
        $this->driver->acquire('test-job', 'server-1', 300);
        $result = $this->driver->heartbeat('test-job', 'server-2', 600);

        $this->assertFalse($result);
    }

    public function test_heartbeat_nonexistent_lock_returns_false(): void
    {
        $result = $this->driver->heartbeat('nonexistent', 'server-1', 300);

        $this->assertFalse($result);
    }

    public function test_can_force_release_any_lock(): void
    {
        $this->driver->acquire('test-job', 'server-1', 300);
        $result = $this->driver->forceRelease('test-job');

        $this->assertTrue($result);
        $this->assertDatabaseMissing('queue_unique_runner_locks', [
            'job_key' => 'test-job',
        ]);
    }

    public function test_force_release_nonexistent_returns_false(): void
    {
        $result = $this->driver->forceRelease('nonexistent');

        $this->assertFalse($result);
    }

    public function test_is_locked_returns_true_for_active_lock(): void
    {
        $this->driver->acquire('test-job', 'server-1', 300);

        $this->assertTrue($this->driver->isLocked('test-job'));
    }

    public function test_is_locked_returns_false_for_expired_lock(): void
    {
        DB::table('queue_unique_runner_locks')->insert([
            'job_key' => 'test-job',
            'server_id' => 'server-1',
            'locked_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5),
            'heartbeat_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($this->driver->isLocked('test-job'));
    }

    public function test_is_locked_returns_false_for_nonexistent(): void
    {
        $this->assertFalse($this->driver->isLocked('nonexistent'));
    }

    public function test_get_current_owner_returns_server_id(): void
    {
        $this->driver->acquire('test-job', 'server-1', 300);

        $this->assertEquals('server-1', $this->driver->getCurrentOwner('test-job'));
    }

    public function test_get_current_owner_returns_null_for_expired(): void
    {
        DB::table('queue_unique_runner_locks')->insert([
            'job_key' => 'test-job',
            'server_id' => 'server-1',
            'locked_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5),
            'heartbeat_at' => now()->subMinutes(10),
        ]);

        $this->assertNull($this->driver->getCurrentOwner('test-job'));
    }

    public function test_get_current_owner_returns_null_for_nonexistent(): void
    {
        $this->assertNull($this->driver->getCurrentOwner('nonexistent'));
    }

    public function test_prune_expired_removes_only_expired_locks(): void
    {
        // Insert expired locks
        DB::table('queue_unique_runner_locks')->insert([
            [
                'job_key' => 'expired-1',
                'server_id' => 'server-1',
                'locked_at' => now()->subMinutes(10),
                'expires_at' => now()->subMinutes(5),
                'heartbeat_at' => now()->subMinutes(10),
            ],
            [
                'job_key' => 'expired-2',
                'server_id' => 'server-1',
                'locked_at' => now()->subMinutes(10),
                'expires_at' => now()->subMinutes(5),
                'heartbeat_at' => now()->subMinutes(10),
            ],
        ]);

        // Insert active lock
        $this->driver->acquire('active-job', 'server-1', 300);

        $count = $this->driver->pruneExpired();

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('queue_unique_runner_locks', ['job_key' => 'active-job']);
        $this->assertDatabaseMissing('queue_unique_runner_locks', ['job_key' => 'expired-1']);
        $this->assertDatabaseMissing('queue_unique_runner_locks', ['job_key' => 'expired-2']);
    }

    public function test_prune_returns_zero_when_no_expired(): void
    {
        $this->driver->acquire('active-job', 'server-1', 300);

        $count = $this->driver->pruneExpired();

        $this->assertEquals(0, $count);
    }

    public function test_acquire_release_acquire_cycle(): void
    {
        $this->assertTrue($this->driver->acquire('test-job', 'server-1', 300));
        $this->assertTrue($this->driver->release('test-job', 'server-1'));
        $this->assertTrue($this->driver->acquire('test-job', 'server-2', 300));

        $this->assertDatabaseHas('queue_unique_runner_locks', [
            'job_key' => 'test-job',
            'server_id' => 'server-2',
        ]);
    }

    public function test_multiple_jobs_independent_locks(): void
    {
        $this->assertTrue($this->driver->acquire('job-a', 'server-1', 300));
        $this->assertTrue($this->driver->acquire('job-b', 'server-1', 300));
        $this->assertTrue($this->driver->acquire('job-c', 'server-2', 300));

        $this->assertTrue($this->driver->release('job-a', 'server-1'));

        $this->assertFalse($this->driver->isLocked('job-a'));
        $this->assertTrue($this->driver->isLocked('job-b'));
        $this->assertTrue($this->driver->isLocked('job-c'));
    }
}
