# Custom SQS Payload Driver

This package adds support for custom SQS payloads within Laravel. It works by mapping a particular ID within the
payload, to a job class, and then passing in matching arguments between the constructor and the payload data.

## Installation

In your `composer.json` file, add this repo as a custom repository, and also add the require statement...

```json
// composer.json

{
  "repositories": [
    {
      "type": "git",
      "url": "https://github.com/edriving-limited/custom-sqs-driver"
    }
  ],
  "require": {
    "edriving-limited/custom-sqs-driver": "dev-main"
  }
}
```

Then execute `composer update`.

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
properties, `jobClassId` and `data`. The `jobClassId` is ID that is used to map this particular payload to a job
class, `data` is any additional data required for the job.

```json
{
  "jobClassId": "sendWelcomeEmail",
  "data": {
    "userId": 100
  }
}
```

## Data mapping

When this job is instantiated, the job class constructor arguments are parsed and mapped to values in your payload data.
So for the given payload above and job class below, the `userId` argument will correctly be passed.

**Warning**: If you don't have any matching properties in your payload for a constructor argument, then as exception
will be thrown.

```php
class SendWelcomeEmailJob implements \Illuminate\Contracts\Queue\ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    /** @var int */
    private $driverId;
    
    public function __construct(int $driverId)
    {
        $this->driverId = $driverId; // This will be 100
    }
}
```