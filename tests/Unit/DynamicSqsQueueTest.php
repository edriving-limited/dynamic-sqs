<?php

namespace eDriving\DynamicSqs\Tests\Unit;

use Aws\Result;
use Aws\Sqs\SqsClient;
use eDriving\DynamicSqs\Contracts\JobHandlerContract;
use eDriving\DynamicSqs\DynamicSqsQueue;
use eDriving\DynamicSqs\Exceptions\HandlerNotDefinedException;
use eDriving\DynamicSqs\Exceptions\HandlerNotFoundException;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

class DynamicSqsQueueTest extends \eDriving\DynamicSqs\Tests\TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Config::set('dynamic-sqs.discoverer', function (array $payload): ?string {
            return $payload['handler'] ?? null;
        });
    }

    public function test_it_can_handle_standard_laravel_jobs(): void
    {
        $client = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['receiveMessage'])
            ->getMock();

        $client->expects($this->once())->method('receiveMessage')->with([
            'QueueUrl' => '/queueName',
            'AttributeNames' => ['ApproximateReceiveCount']
        ])->willReturn($this->getLaravelJobMessage());

        $this->assertInstanceOf(SqsJob::class, $this->createDriver($client)->pop());
    }

    public function test_it_can_handle_custom_jobs(): void
    {
        Config::set('dynamic-sqs.map.example_job_handler', ExampleJobHandler::class);

        $client = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['receiveMessage'])
            ->getMock();

        $client->expects($this->once())->method('receiveMessage')->with([
            'QueueUrl' => '/queueName',
            'AttributeNames' => ['ApproximateReceiveCount']
        ])->willReturn($this->getCustomJobMessage());

        $this->assertInstanceOf(SqsJob::class, $this->createDriver($client)->pop());
    }

    public function test_it_throws_an_exception_if_it_cant_map_a_custom_message_to_a_job(): void
    {
        $this->expectException(HandlerNotFoundException::class);

        $client = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['receiveMessage'])
            ->getMock();

        $client->expects($this->once())->method('receiveMessage')->with([
            'QueueUrl' => '/queueName',
            'AttributeNames' => ['ApproximateReceiveCount']
        ])->willReturn($this->getCustomJobMessage());

        $this->createDriver($client)->pop();
    }

    public function test_it_can_handle_empty_messages(): void
    {
        $client = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['receiveMessage'])
            ->getMock();

        $client->expects($this->once())->method('receiveMessage')->with([
            'QueueUrl' => '/queueName',
            'AttributeNames' => ['ApproximateReceiveCount']
        ])->willReturn(
            new Result([
                'Messages' => null
            ])
        );

        $this->assertNull($this->createDriver($client)->pop());
    }

    public function test_it_throws_an_exception_for_custom_jobs_if_it_cant_determine_the_handler_id(): void
    {
        $this->expectException(HandlerNotDefinedException::class);

        $client = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['receiveMessage'])
            ->getMock();

        $client->expects($this->once())->method('receiveMessage')->with([
            'QueueUrl' => '/queueName',
            'AttributeNames' => ['ApproximateReceiveCount']
        ])->willReturn($this->getCustomJobMessage(['id' => 'test']));

        $this->assertInstanceOf(SqsJob::class, $this->createDriver($client)->pop());
    }

    public function test_it_throws_an_exception_for_custom_jobs_if_it_cant_find_a_matching_handler(): void
    {
        $this->expectException(HandlerNotFoundException::class);

        $client = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['receiveMessage'])
            ->getMock();

        $client->expects($this->once())->method('receiveMessage')->with([
            'QueueUrl' => '/queueName',
            'AttributeNames' => ['ApproximateReceiveCount']
        ])->willReturn($this->getCustomJobMessage());

        $this->assertInstanceOf(SqsJob::class, $this->createDriver($client)->pop());
    }

    private function createDriver(SqsClient $client): DynamicSqsQueue
    {
        $driver = app(DynamicSqsQueue::class, [
            'sqs' => $client,
            'default' => 'queueName'
        ]);

        $driver->setContainer(app(Container::class));

        return $driver;
    }

    /** @return Result<string, mixed> */
    private function getLaravelJobMessage(): Result
    {
        $body = json_encode([
            'uuid' => '1eb00133-5bcc-478d-8f60-f66156ca27e6',
            'displayName' => 'eDriving\DynamicSqs\Tests\Unit\ExampleJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => 5,
            'maxExceptions' => null,
            'delay' => 300,
            'timeout' => null,
            'timeoutAt' => null,
            'data' => [
                'commandName' => 'eDriving\DynamicSqs\Tests\Unit\ExampleJob',
                'command' => serialize(new ExampleJob(100))
            ]
        ]);

        return new Result([
            'Messages' => [[
                'MessageId' => '25ee87fa-1e40-4d8d-a79e-c91b534d1b54',
                'Body' => $body,
            ]]
        ]);
    }

    /**
     * @param null|array<string, mixed> $replacementData
     * @return Result<string, mixed>
     */
    private function getCustomJobMessage(array $replacementData = null): Result
    {
        $body = json_encode(
            $replacementData ?? [
            'handler' => 'example_job_handler',
            'data' => ['userId' => 100]
            ]
        );

        return new Result([
            'Messages' => [[
                'MessageId' => '25ee87fa-1e40-4d8d-a79e-c91b534d1b54',
                'Body' => $body,
            ]]
        ]);
    }
}

class ExampleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }
}

class ExampleJobHandler implements JobHandlerContract
{
    /**
     * @param array<string, mixed> $payload
     * @return ShouldQueue
     */
    public function handle(array $payload): ShouldQueue
    {
        return new ExampleJob($payload['data']['userId']);
    }
}
