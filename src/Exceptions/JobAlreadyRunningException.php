<?php

namespace Bytetcore\QueueUniqueRunner\Exceptions;

use RuntimeException;

class JobAlreadyRunningException extends RuntimeException
{
    private string $jobKey;
    private string $serverId;

    public function __construct(string $jobKey, string $serverId, string $message = '')
    {
        $this->jobKey = $jobKey;
        $this->serverId = $serverId;

        if ($message === '') {
            $message = "Job [{$jobKey}] is already running on server [{$serverId}].";
        }

        parent::__construct($message);
    }

    public function getJobKey(): string
    {
        return $this->jobKey;
    }

    public function getServerId(): string
    {
        return $this->serverId;
    }
}
