<?php

namespace Bytetcore\QueueUniqueRunner\Support;

class LockKeyResolver
{
    private const PREFIX = 'queue-unique-runner';

    public function resolve(object $job, string $scope = 'class', ?string $identifier = null): string
    {
        $jobClass = get_class($job);

        if ($scope === 'instance') {
            $instanceKey = $identifier ?? $this->resolveInstanceKey($job);

            return self::PREFIX . ':' . $jobClass . ':' . $instanceKey;
        }

        return self::PREFIX . ':' . $jobClass;
    }

    private function resolveInstanceKey(object $job): string
    {
        if (method_exists($job, 'queueUniqueRunnerIdentifier')) {
            $identifier = $job->queueUniqueRunnerIdentifier();

            if ($identifier !== null) {
                return $identifier;
            }
        }

        return $this->hashJobProperties($job);
    }

    private function hashJobProperties(object $job): string
    {
        $properties = get_object_vars($job);

        // Remove Laravel framework properties that don't identify the job
        $frameworkProperties = [
            'job',
            'connection',
            'queue',
            'delay',
            'afterCommit',
            'middleware',
            'chained',
            'chainConnection',
            'chainQueue',
            'chainCatchCallbacks',
        ];

        foreach ($frameworkProperties as $prop) {
            unset($properties[$prop]);
        }

        return hash('sha256', json_encode($properties));
    }
}
