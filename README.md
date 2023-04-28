# Custom SQS Payload Driver

This package adds support for custom SQS payloads within Laravel. It works by mapping a particular ID within the
payload, to a job class, and then passing in matching arguments between the constructor and the payload data.

## Installation

`composer require edriving-limited/custom-sqs-driver`

## Configuration

To configure this, there are two steps, first you need to configure your SQS connection to use this driver, so update
your SQS queue configuration like so...

```php
// config/queue.php

[
    'sqs' => [
        'driver' => 'custom-sqs',
        // ...
        
    ]
]
```

Second, you need to provide a map, so we can instantiate the correct job class for your payload. You do this by adding a
new top level array to your `queue` config file, called `job_map`. Inside this array, you need to provide key value
pairs, the key being the ID that's in your payload, and the value being the class-string of your job.

```php
// config/queue.php

[
    'job_map' => [
        'sendWelcomeEmail' => SendWelcomeEmail::class 
    ]
]
```

## Payload Format

Your SQS payloads will need to be in a specific format for this to work. In your JSON object, you simple need two
properties, `job_class_id` and `data`. The `job_class_id` is ID that is used to map this particular payload to a job
class, `data` is any additional data required for the job.

```json
{
  "job_class_id": "sendWelcomeEmail",
  "data": {
    "userId": 100
  }
}
```

## Data mapping

When this job is instantiated, the job class constructor arguments are parsed and mapped to values in your payload data.
So for the given payload above and job class below, the `userId` argument will correctly be passed.

You must guarantee that either you have all the required arguments in your payload, or for any that don't, you provide
a default value in the constructor argument. Otherwise, `null` will be passed which could cause type errors or
unexpected behavior.

```php
class SendWelcomeEmailJob implements \Illuminate\Contracts\Queue\ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /** @var int */
    private $driverId;
    
    /** @var bool */
    private $isActive;
    
    /** @var bool */
    private $isSubscriber;
    
    public function __construct(int $driverId, bool $isActive, bool $isSubscriber = false)
    {
        $this->driverId = $driverId; // This will be 100
        $this->isActive = $isActive; // This will cause a type error, since it's not in the payload and there is no default value
        $this->isSubscriber = $isSubscriber; // This will be false since we provide that as a default value
    }
}
```