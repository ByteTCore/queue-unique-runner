<?php

namespace Bytetcore\QueueUniqueRunner\Console;

use Bytetcore\QueueUniqueRunner\Contracts\LockDriver;
use Illuminate\Console\Command;

class PruneLocksCommand extends Command
{
    protected $signature = 'queue-unique-runner:prune';

    protected $description = 'Remove expired queue-unique-runner locks from the lock store';

    public function handle(LockDriver $driver): int
    {
        $count = $driver->pruneExpired();

        if ($count === 0) {
            $this->info('No expired locks found.');
        } else {
            $this->info("Pruned {$count} expired lock(s).");
        }

        return self::SUCCESS;
    }
}
