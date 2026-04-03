<?php

namespace Bytetcore\QueueUniqueRunner\Tests\Unit;

use Bytetcore\QueueUniqueRunner\Tests\TestCase;
use Bytetcore\QueueUniqueRunner\Traits\RunsOnUniqueRunner;
use Bytetcore\QueueUniqueRunner\Middleware\UniqueRunner;

class RunsOnUniqueRunnerTest extends TestCase
{
    public function test_trait_adds_middleware(): void
    {
        $job = new TestJobWithTrait();
        $middleware = $job->middleware();

        $this->assertIsArray($middleware);
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(UniqueRunner::class, $middleware[0]);
    }
}

class TestJobWithTrait
{
    use RunsOnUniqueRunner;
}
