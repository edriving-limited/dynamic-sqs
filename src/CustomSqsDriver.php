<?php

namespace eDriving\CustomSqsDriver;

use eDriving\CustomSqsDriver\Exceptions\InvalidMappingException;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SqsQueue;
use InvalidArgumentException;
use ReflectionClass;

class CustomSqsDriver extends SqsQueue
{
    public function pop($queue = null): ?SqsJob
    {
        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue = $this->getQueue($queue),
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (!is_null($response['Messages']) && count($response['Messages']) > 0) {
            $body = json_decode($response['Messages'][0]['Body'], true);
            $payload = $response['Messages'][0];

            if (!isset($body['data']['commandName'], $body['data']['command'])) {
                $payload['Body'] = $this->getCustomJobPayload($body, $queue);
            }

            return new SqsJob(
                $this->container,
                $this->sqs,
                $payload,
                $this->connectionName,
                $queue
            );
        }

        return null;
    }

    /**
     * @param string $jobClassId
     * @return class-string|null
     */
    private function mapToJob(string $jobClassId): ?string
    {
        return config("queue.job_map.{$jobClassId}") ?? null;
    }

    /**
     * @param class-string $jobClass
     * @param array<string, mixed> $body
     * @return object
     * @throws \ReflectionException
     */
    public function createCustomJobClass(string $jobClass, array $body): object
    {
        $reflectionClass = new ReflectionClass($jobClass);
        $constructor = $reflectionClass->getMethod('__construct');
        $params = $constructor->getParameters();

        $args = [];
        foreach ($params as $param) {
            $args[] = $body[$param->name] ?? null;
        }

        return new $jobClass(...$args);
    }

    /**
     * @param array<string, mixed> $body
     * @param string $queue
     * @return string
     * @throws InvalidMappingException
     * @throws \ReflectionException
     */
    public function getCustomJobPayload(array $body, string $queue): string
    {
        $classId = $body['jobClassId'];
        $jobClass = $this->mapToJob($classId);

        if (!$jobClass) {
            throw new InvalidMappingException("Mapping not found for job class id \"$classId\"");
        }

        return $this->createPayload($this->createCustomJobClass($jobClass, $body['data']), $queue);
    }
}
