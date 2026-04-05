<?php

namespace Bytetcore\QueueUniqueRunner\Tests\Unit;

use Bytetcore\QueueUniqueRunner\Contracts\LockDriver;
use Bytetcore\QueueUniqueRunner\Middleware\UniqueRunner;
use Bytetcore\QueueUniqueRunner\Support\Heartbeat;
use Bytetcore\QueueUniqueRunner\Support\LockKeyResolver;
use Bytetcore\QueueUniqueRunner\Support\ServerIdentifier;
use Bytetcore\QueueUniqueRunner\Tests\TestCase;
use Mockery;

class UniqueRunnerMiddlewareTest extends TestCase
{
    private $mockDriver;
    private $mockHeartbeat;
    private ServerIdentifier $serverIdentifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDriver = Mockery::mock(LockDriver::class);
        $this->app->instance(LockDriver::class, $this->mockDriver);

        $this->mockHeartbeat = Mockery::mock(Heartbeat::class);
        $this->app->instance(Heartbeat::class, $this->mockHeartbeat);

        $this->serverIdentifier = new ServerIdentifier();
        $this->serverIdentifier->set('test-server');
        $this->app->instance(ServerIdentifier::class, $this->serverIdentifier);

        $this->app->instance(LockKeyResolver::class, new LockKeyResolver());
    }

    public function test_acquires_lock_and_runs_job(): void
    {
        $jobExecuted = false;
        $job = $this->createMockJob();

        $this->mockDriver->shouldReceive('acquire')->once()->andReturn(true);
        $this->mockDriver->shouldReceive('release')->once()->andReturn(true);
        $this->mockHeartbeat->shouldReceive('stop')->once();

        $middleware = new UniqueRunner();
        $middleware->handle($job, function () use (&$jobExecuted) {
            $jobExecuted = true;
        });

        $this->assertTrue($jobExecuted);
    }

    public function test_releases_job_when_lock_not_acquired(): void
    {
        $jobExecuted = false;
        $job = $this->createMockJob();
        $job->shouldReceive('release')->with(30)->once();

        $this->mockDriver->shouldReceive('acquire')->once()->andReturn(false);

        $middleware = new UniqueRunner();
        $middleware->handle($job, function () use (&$jobExecuted) {
            $jobExecuted = true;
        });

        $this->assertFalse($jobExecuted);
    }

    public function test_deletes_job_when_lock_not_acquired_and_delete_strategy_is_active(): void
    {
        $jobExecuted = false;
        $job = $this->createMockJob();
        $job->shouldReceive('delete')->once();
        $job->shouldReceive('queueUniqueRunnerOnLockFail')->andReturn('delete');

        $this->mockDriver->shouldReceive('acquire')->once()->andReturn(false);

        $middleware = new UniqueRunner();
        $middleware->handle($job, function () use (&$jobExecuted) {
            $jobExecuted = true;
        });

        $this->assertFalse($jobExecuted);
    }

    public function test_releases_lock_even_when_job_throws(): void
    {
        $job = $this->createMockJob();

        $this->mockDriver->shouldReceive('acquire')->once()->andReturn(true);
        $this->mockDriver->shouldReceive('release')->once()->andReturn(true);
        $this->mockHeartbeat->shouldReceive('stop')->once();

        $middleware = new UniqueRunner();

        $this->expectException(\RuntimeException::class);

        $middleware->handle($job, function () {
            throw new \RuntimeException('Job failed');
        });
    }

    public function test_uses_custom_ttl(): void
    {
        $job = $this->createMockJob();

        $this->mockDriver->shouldReceive('acquire')
            ->withArgs(function ($key, $serverId, $ttl) {
                return $ttl === 600;
            })
            ->once()
            ->andReturn(true);
        $this->mockDriver->shouldReceive('release')->once()->andReturn(true);
        $this->mockHeartbeat->shouldReceive('stop')->once();

        $middleware = new UniqueRunner(ttl: 600);
        $middleware->handle($job, function () {});

        $this->assertTrue(true);
    }

    public function test_uses_custom_retry_delay(): void
    {
        $this->mockDriver->shouldReceive('acquire')->once()->andReturn(false);

        $jobWithDelay = $this->createMockJob();
        $jobWithDelay->shouldReceive('release')->with(60)->once();
        $jobWithDelay->shouldReceive('queueUniqueRunnerRetryDelay')->andReturn(60);

        $middleware = new UniqueRunner();
        $middleware->handle($jobWithDelay, function () {});
        
        $this->assertTrue(true);
    }

    public function test_starts_heartbeat_when_enabled(): void
    {
        $this->app['config']->set('queue-unique-runner.heartbeat.enabled', true);

        $job = $this->createMockJob();
        $job->shouldReceive('queueUniqueRunnerHeartbeat')->andReturn(true);

        $this->mockDriver->shouldReceive('acquire')->once()->andReturn(true);
        $this->mockDriver->shouldReceive('release')->once()->andReturn(true);
        $this->mockHeartbeat->shouldReceive('start')->once();
        $this->mockHeartbeat->shouldReceive('stop')->once();

        $middleware = new UniqueRunner();
        $middleware->handle($job, function () {});

        $this->assertTrue(true);
    }

    public function test_skips_heartbeat_when_disabled(): void
    {
        $this->app['config']->set('queue-unique-runner.heartbeat.enabled', false);

        $job = $this->createMockJob();

        $this->mockDriver->shouldReceive('acquire')->once()->andReturn(true);
        $this->mockDriver->shouldReceive('release')->once()->andReturn(true);
        $this->mockHeartbeat->shouldNotReceive('start');
        $this->mockHeartbeat->shouldReceive('stop')->once();

        $middleware = new UniqueRunner();
        $middleware->handle($job, function () {});

        $this->assertTrue(true);
    }

    public function test_instance_scope_generates_different_keys(): void
    {
        $keys = [];

        $this->mockDriver->shouldReceive('acquire')
            ->twice()
            ->andReturnUsing(function ($key) use (&$keys) {
                $keys[] = $key;
                return true;
            });
        $this->mockDriver->shouldReceive('release')->twice()->andReturn(true);
        $this->mockHeartbeat->shouldReceive('stop')->twice();

        $job1 = $this->createMockJobWithProperty('user_id', 1);
        $job2 = $this->createMockJobWithProperty('user_id', 2);

        $middleware = new UniqueRunner(scope: 'instance');
        $middleware->handle($job1, function () {});
        $middleware->handle($job2, function () {});

        $this->assertCount(2, $keys);
        $this->assertNotEquals($keys[0], $keys[1]);
    }

    public function test_class_scope_generates_same_key(): void
    {
        $keys = [];

        $this->mockDriver->shouldReceive('acquire')
            ->twice()
            ->andReturnUsing(function ($key) use (&$keys) {
                $keys[] = $key;
                return true;
            });
        $this->mockDriver->shouldReceive('release')->twice()->andReturn(true);
        $this->mockHeartbeat->shouldReceive('stop')->twice();

        $job1 = $this->createMockJobWithProperty('user_id', 1);
        $job2 = $this->createMockJobWithProperty('user_id', 2);

        $middleware = new UniqueRunner(scope: 'class');
        $middleware->handle($job1, function () {});
        $middleware->handle($job2, function () {});

        $this->assertCount(2, $keys);
        $this->assertEquals($keys[0], $keys[1]);
    }

    public function test_uses_job_scope_method_over_constructor(): void
    {
        $keys = [];

        $this->mockDriver->shouldReceive('acquire')
            ->once()
            ->andReturnUsing(function ($key) use (&$keys) {
                $keys[] = $key;
                return true;
            });
        $this->mockDriver->shouldReceive('release')->once()->andReturn(true);
        $this->mockHeartbeat->shouldReceive('stop')->once();

        $job = $this->createMockJob();
        $job->shouldReceive('queueUniqueRunnerScope')->andReturn('instance');

        $middleware = new UniqueRunner(scope: 'class');
        $middleware->handle($job, function () {});

        $this->assertStringContainsString(':', $keys[0]);
    }

    public function test_uses_job_ttl_method_over_constructor(): void
    {
        $job = $this->createMockJob();
        $job->shouldReceive('queueUniqueRunnerTtl')->andReturn(999);

        $this->mockDriver->shouldReceive('acquire')
            ->withArgs(function ($key, $serverId, $ttl) {
                return $ttl === 999;
            })
            ->once()
            ->andReturn(true);
        $this->mockDriver->shouldReceive('release')->once()->andReturn(true);
        $this->mockHeartbeat->shouldReceive('stop')->once();

        $middleware = new UniqueRunner();
        $middleware->handle($job, function () {});

        $this->assertTrue(true);
    }

    private function createMockJob(): Mockery\MockInterface
    {
        $job = Mockery::mock(DummyMiddlewareJob::class);
        $job->shouldReceive('queueUniqueRunnerScope')->andReturn('class')->byDefault();
        $job->shouldReceive('queueUniqueRunnerTtl')->andReturn(null)->byDefault();
        $job->shouldReceive('queueUniqueRunnerRetryDelay')->andReturn(30)->byDefault();
        $job->shouldReceive('queueUniqueRunnerHeartbeat')->andReturn(false)->byDefault();
        $job->shouldReceive('queueUniqueRunnerOnLockFail')->andReturn('release')->byDefault();

        return $job;
    }

    private function createMockJobWithProperty(string $property, $value): object
    {
        return new class ($property, $value) {
            public function __construct(
                public string $propName,
                public mixed $propValue
            ) {
                $this->{$propName} = $propValue;
            }
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

#[\AllowDynamicProperties]
class DummyMiddlewareJob {
    public function queueUniqueRunnerScope() { return 'class'; }
    public function queueUniqueRunnerTtl() { return null; }
    public function queueUniqueRunnerRetryDelay() { return 30; }
    public function queueUniqueRunnerHeartbeat() { return false; }
    public function queueUniqueRunnerOnLockFail() { return 'release'; }
    public function release($delay = 0) {}
    public function delete() {}
}
