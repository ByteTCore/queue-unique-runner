<?php

namespace Bytetcore\QueueUniqueRunner\Tests\Unit;

use Bytetcore\QueueUniqueRunner\Support\LockKeyResolver;
use Bytetcore\QueueUniqueRunner\Tests\TestCase;

class LockKeyResolverTest extends TestCase
{
    private LockKeyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new LockKeyResolver();
    }

    public function test_class_scope_uses_class_name(): void
    {
        $job = new class {};

        $key = $this->resolver->resolve($job, 'class');

        $this->assertStringStartsWith('queue-unique-runner:', $key);
        $this->assertStringContainsString(get_class($job), $key);
    }

    public function test_class_scope_same_class_same_key(): void
    {
        $job1 = new TestJobA(1);
        $job2 = new TestJobA(2);

        $key1 = $this->resolver->resolve($job1, 'class');
        $key2 = $this->resolver->resolve($job2, 'class');

        $this->assertEquals($key1, $key2);
    }

    public function test_class_scope_different_class_different_key(): void
    {
        $job1 = new TestJobA(1);
        $job2 = new TestJobB(1);

        $key1 = $this->resolver->resolve($job1, 'class');
        $key2 = $this->resolver->resolve($job2, 'class');

        $this->assertNotEquals($key1, $key2);
    }

    public function test_instance_scope_same_properties_same_key(): void
    {
        $job1 = new TestJobA(1);
        $job2 = new TestJobA(1);

        $key1 = $this->resolver->resolve($job1, 'instance');
        $key2 = $this->resolver->resolve($job2, 'instance');

        $this->assertEquals($key1, $key2);
    }

    public function test_instance_scope_different_properties_different_key(): void
    {
        $job1 = new TestJobA(1);
        $job2 = new TestJobA(2);

        $key1 = $this->resolver->resolve($job1, 'instance');
        $key2 = $this->resolver->resolve($job2, 'instance');

        $this->assertNotEquals($key1, $key2);
    }

    public function test_instance_scope_uses_custom_identifier(): void
    {
        $job = new TestJobWithIdentifier('custom-key-123');

        $key = $this->resolver->resolve($job, 'instance');

        $this->assertStringContainsString('custom-key-123', $key);
    }

    public function test_explicit_identifier_parameter(): void
    {
        $job = new TestJobA(1);

        $key = $this->resolver->resolve($job, 'instance', 'explicit-id');

        $this->assertStringContainsString('explicit-id', $key);
    }

    public function test_explicit_identifier_overrides_job_method(): void
    {
        $job = new TestJobWithIdentifier('from-method');

        $key = $this->resolver->resolve($job, 'instance', 'from-param');

        $this->assertStringContainsString('from-param', $key);
        $this->assertStringNotContainsString('from-method', $key);
    }

    public function test_framework_properties_excluded_from_hash(): void
    {
        $job1 = new TestJobWithFrameworkProps(1, 'redis', 'high');
        $job2 = new TestJobWithFrameworkProps(1, 'database', 'low');

        $key1 = $this->resolver->resolve($job1, 'instance');
        $key2 = $this->resolver->resolve($job2, 'instance');

        // Same user_id, different framework props → same key
        $this->assertEquals($key1, $key2);
    }

    public function test_key_format_class_scope(): void
    {
        $job = new TestJobA(1);
        $key = $this->resolver->resolve($job, 'class');
        $class = get_class($job);

        $this->assertEquals("queue-unique-runner:{$class}", $key);
    }

    public function test_key_format_instance_scope(): void
    {
        $job = new TestJobA(1);
        $key = $this->resolver->resolve($job, 'instance');
        $class = get_class($job);

        $this->assertMatchesRegularExpression(
            '/^queue-unique-runner:' . preg_quote($class, '/') . ':[a-f0-9]{64}$/',
            $key
        );
    }

    public function test_null_identifier_falls_back_to_hash(): void
    {
        $job = new TestJobWithNullIdentifier(42);

        $key = $this->resolver->resolve($job, 'instance');
        $class = get_class($job);

        $this->assertMatchesRegularExpression(
            '/^queue-unique-runner:' . preg_quote($class, '/') . ':[a-f0-9]{64}$/',
            $key
        );
    }

    public function test_custom_prefix_from_config(): void
    {
        config(['queue-unique-runner.prefix' => 'custom-prefix']);
        
        $job = new TestJobA(1);
        $key = $this->resolver->resolve($job, 'class');

        $this->assertStringStartsWith('custom-prefix:', $key);
    }
}

class TestJobA
{
    public int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }
}

class TestJobB
{
    public int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }
}

class TestJobWithIdentifier
{
    public function __construct(private string $id)
    {
    }

    public function queueUniqueRunnerIdentifier(): string
    {
        return $this->id;
    }
}

class TestJobWithNullIdentifier
{
    public int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function queueUniqueRunnerIdentifier(): ?string
    {
        return null;
    }
}

class TestJobWithFrameworkProps
{
    public int $userId;
    public string $connection;
    public string $queue;

    public function __construct(int $userId, string $connection, string $queue)
    {
        $this->userId = $userId;
        $this->connection = $connection;
        $this->queue = $queue;
    }
}
