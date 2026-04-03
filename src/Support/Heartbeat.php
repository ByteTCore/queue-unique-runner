<?php

namespace Bytetcore\QueueUniqueRunner\Support;

use Bytetcore\QueueUniqueRunner\Contracts\LockDriver;

class Heartbeat
{
    private bool $running = false;
    private ?int $previousAlarm = null;

    public function start(LockDriver $driver, string $key, string $serverId, int $ttl): void
    {
        if (! $this->isPcntlAvailable()) {
            return;
        }

        $interval = (int) config('queue-unique-runner.heartbeat.interval', 30);
        $this->running = true;

        pcntl_async_signals(true);

        pcntl_signal(SIGALRM, function () use ($driver, $key, $serverId, $ttl, $interval) {
            if ($this->running) {
                $driver->heartbeat($key, $serverId, $ttl);
                pcntl_alarm($interval);
            }
        });

        $this->previousAlarm = pcntl_alarm($interval);
    }

    public function stop(): void
    {
        $this->running = false;

        if (! $this->isPcntlAvailable()) {
            return;
        }

        pcntl_alarm(0);

        if ($this->previousAlarm !== null && $this->previousAlarm > 0) {
            pcntl_alarm($this->previousAlarm);
        }

        pcntl_signal(SIGALRM, SIG_DFL);
        $this->previousAlarm = null;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function isPcntlAvailable(): bool
    {
        return extension_loaded('pcntl')
            && function_exists('pcntl_alarm')
            && function_exists('pcntl_async_signals')
            && function_exists('pcntl_signal');
    }
}
