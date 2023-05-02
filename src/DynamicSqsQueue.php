<?php

namespace eDriving\DynamicSqs;

use eDriving\DynamicSqs\Contracts\JobHandlerContract;
use eDriving\DynamicSqs\Exceptions\HandlerNotDefinedException;
use eDriving\DynamicSqs\Exceptions\HandlerNotFoundException;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SqsQueue;

class DynamicSqsQueue extends SqsQueue
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
     * @param array<string, mixed> $payload
     * @param string $queue
     * @return string
     * @throws HandlerNotFoundException
     * @throws HandlerNotDefinedException
     */
    public function getCustomJobPayload(array $payload, string $queue): string
    {
        $handler = $this->getHandler($payload);

        return $this->createPayload($handler->handle($payload), $queue);
    }

    /**
     * @param array<string, mixed> $payload
     * @return JobHandlerContract
     * @throws HandlerNotDefinedException
     * @throws HandlerNotFoundException
     */
    private function getHandler(array $payload): JobHandlerContract
    {
        $discoverer = config('dynamic-sqs.discoverer');
        $handlerId = $discoverer($payload);

        if (!$handlerId) {
            throw new HandlerNotDefinedException("Handler not defined");
        }

        $handler = config("dynamic-sqs.map.{$handlerId}");

        if (!$handler) {
            throw new HandlerNotFoundException("Handler not found for ID \"$handlerId\"");
        }

        return app($handler);
    }
}
