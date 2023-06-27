<?php

namespace eDriving\DynamicSqs;

use Aws\Result;
use eDriving\DynamicSqs\Contracts\JobHandlerContract;
use eDriving\DynamicSqs\Exceptions\HandlerNotDefinedException;
use eDriving\DynamicSqs\Exceptions\HandlerNotFoundException;
use eDriving\DynamicSqs\Exceptions\SendBatchSqsFailedException;
use Generator;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\Promise;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Str;
use JsonException;

class DynamicSqsQueue extends SqsQueue
{
    protected const CONCURRENCY = 10;
    protected const BATCH_LIMIT = 10;
    protected const BATCH_SIZE_LIMIT = 200 * 1024;

    /**
     * @param array $jobs
     * @param string $data
     * @param null $queue
     * @throws SendBatchSqsFailedException
     */
    public function bulk($jobs, $data = '', $queue = null): void
    {
        $responses = collect();

        $promise = Each::ofLimit(
            $this->batchGenerator($jobs, $data, $queue),
            self::CONCURRENCY,
            static function (Result $response) use ($responses) {
                $responses->push($response);
            }
        );

        $promise->wait();

        $failed = $responses->filter(function (Result $response) {
            return count($response['Failed'] ?? []);
        })->flatten(1);

        if ($failed->isNotEmpty()) {
            throw new SendBatchSqsFailedException();
        }
    }

    /** @throws HandlerNotDefinedException|JsonException|HandlerNotFoundException */
    public function pop($queue = null): ?SqsJob
    {
        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue = $this->getQueue($queue),
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (!is_null($response['Messages']) && count($response['Messages']) > 0) {
            $body = json_decode($response['Messages'][0]['Body'], true, 512, JSON_THROW_ON_ERROR);
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

        $handler = config("dynamic-sqs.map.$handlerId");

        if (!$handler) {
            throw new HandlerNotFoundException("Handler not found for ID \"$handlerId\"");
        }

        return app($handler);
    }

    protected function batchGenerator(array $jobs, string $data = '', ?string $queue = null): Generator
    {
        $queue = $queue ?: $this->default;

        $batchPayloads = [];
        $batchBytes = 0;

        foreach ($jobs as $job) {
            $payload = $this->createPayload($job, $queue ?: $this->default, $data);

            $batchPayloads[] = $payload;
            $batchBytes += strlen($payload);

            if ($batchBytes >= self::BATCH_SIZE_LIMIT || count($batchPayloads) >= self::BATCH_LIMIT) {
                yield $this->dispatchBatchAsync($queue, $batchPayloads);
                $batchPayloads = [];
                $batchBytes = 0;
            }
        }

        if (count($batchPayloads)) {
            yield $this->dispatchBatchAsync($queue, $batchPayloads);
        }
    }

    protected function dispatchBatchAsync(string $queue, array $payloads): Promise
    {
        return $this->sqs->sendMessageBatchAsync([
            'QueueUrl' => $this->getQueue($queue),
            'Entries' => array_map(
                static function (string $payload) {
                    return [
                        'Id' => (string)Str::uuid(),
                        'MessageBody' => $payload,
                    ];
                },
                $payloads
            ),
        ]);
    }
}
