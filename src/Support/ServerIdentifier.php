<?php

namespace Bytetcore\QueueUniqueRunner\Support;

class ServerIdentifier
{
    private ?string $id = null;

    public function get(): string
    {
        if ($this->id !== null) {
            return $this->id;
        }

        $configId = config('queue-unique-runner.server_id');

        if ($configId) {
            $this->id = $configId;

            return $this->id;
        }

        $this->id = gethostname() . ':' . getmypid();

        return $this->id;
    }

    public function set(string $id): void
    {
        $this->id = $id;
    }

    public function reset(): void
    {
        $this->id = null;
    }
}
