<?php

namespace eDriving\CustomSqsDriver\Tests\Unit;

use Aws\Result;
use Aws\Sqs\SqsClient;
use eDriving\CustomSqsDriver\CustomSqsDriver;
use eDriving\CustomSqsDriver\Exceptions\InvalidMappingException;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use TypeError;

class CustomSqsDriverTest extends \eDriving\CustomSqsDriver\Tests\TestCase
{
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

        $result = $this->createDriver($client)->pop();

        $this->assertInstanceOf(SqsJob::class, $result);
    }

    public function test_it_can_handle_custom_jobs(): void
    {
        Config::set('queue.job_map.example_job', ExampleJob::class);

        $client = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['receiveMessage'])
            ->getMock();

        $client->expects($this->once())->method('receiveMessage')->with([
            'QueueUrl' => '/queueName',
            'AttributeNames' => ['ApproximateReceiveCount']
        ])->willReturn($this->getCustomJobMessage());

        $result = $this->createDriver($client)->pop();
        $jobPayload = $result->getSqsJob();
        $jobBody = json_decode($jobPayload['Body']);
        $jobDetail = unserialize($jobBody->data->command);

        $this->assertInstanceOf(SqsJob::class, $result);
        $this->assertEquals(ExampleJob::class, $jobBody->data->commandName);
        $this->assertEquals(100, $jobDetail->driverId);
    }

    public function test_it_throws_an_exception_if_it_cant_map_a_custom_message_to_a_job(): void
    {
        $this->expectException(InvalidMappingException::class);

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

    public function test_it_defaults_to_null_if_it_cant_map_custom_message_data_to_job_arguments(): void
    {
        Config::set('queue.job_map.example_job', ExampleJob::class);

        $this->expectException(TypeError::class);

        $client = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['receiveMessage'])
            ->getMock();

        $client->expects($this->once())->method('receiveMessage')->with([
            'QueueUrl' => '/queueName',
            'AttributeNames' => ['ApproximateReceiveCount']
        ])->willReturn(
            $this->getCustomJobMessage([
                'test' => true
            ])
        );

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

        $driver = $this->createDriver($client);

        $result = $driver->pop();

        $this->assertNull($result);
    }

    private function createDriver(SqsClient $client): CustomSqsDriver
    {
        $driver = app(CustomSqsDriver::class, [
            'sqs' => $client,
            'default' => 'queueName'
        ]);

        $driver->setContainer(app(Container::class));

        return $driver;
    }

    private function getLaravelJobMessage(): Result
    {
        $body = json_encode([
            'uuid' => '1eb00133-5bcc-478d-8f60-f66156ca27e6',
            'displayName' => 'eDriving\CustomSqsDriver\Tests\Unit\ExampleJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => 5,
            'maxExceptions' => null,
            'delay' => 300,
            'timeout' => null,
            'timeoutAt' => null,
            'data' => [
                'commandName' => 'eDriving\CustomSqsDriver\Tests\Unit\ExampleJob',
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

    private function getCustomJobMessage(?array $data = null): Result
    {
        $body = json_encode([
            'jobClassId' => 'example_job',
            'data' => $data ?? ['driverId' => 100]
        ]);

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
    public $driverId;

    public function __construct(int $driverId, bool $test = true)
    {
        $this->driverId = $driverId;
    }
}
