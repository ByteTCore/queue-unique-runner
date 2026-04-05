<?php

namespace Bytetcore\QueueUniqueRunner\Tests\Unit;

use Bytetcore\QueueUniqueRunner\Support\ServerIdentifier;
use Bytetcore\QueueUniqueRunner\Tests\TestCase;

class ServerIdentifierTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        // Reset config if it was mocked
        putenv('QUEUE_UNIQUE_RUNNER_SERVER_ID');
    }

    public function test_generates_identifier_from_hostname_and_pid(): void
    {
        $identifier = new ServerIdentifier();

        // config() helper is not available in raw PHPUnit TestCase,
        // so it falls back to hostname:pid
        $id = $identifier->get();

        $this->assertStringContainsString(gethostname(), $id);
        $this->assertStringContainsString((string) getmypid(), $id);
        $this->assertStringContainsString(':', $id);
    }

    public function test_caches_identifier(): void
    {
        $identifier = new ServerIdentifier();

        $id1 = $identifier->get();
        $id2 = $identifier->get();

        $this->assertEquals($id1, $id2);
    }

    public function test_manual_set_overrides_generation(): void
    {
        $identifier = new ServerIdentifier();

        $identifier->set('test-server-123');
        $id = $identifier->get();

        $this->assertEquals('test-server-123', $id);
    }



    public function test_reset_clears_cached_identifier(): void
    {
        $identifier = new ServerIdentifier();

        $identifier->set('test-server-123');
        $identifier->reset();
        $id = $identifier->get();

        $this->assertNotEquals('test-server-123', $id);
        $this->assertStringContainsString(gethostname(), $id);
    }
}
