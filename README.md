# Dynamic SQS

This package adds support for custom SQS payloads with your standard Laravel jobs.

## Installation

First install the package using composer `composer require edriving-limited/dynamic-sqs`. Then publish the
configuration files using `php artisan vendor:publish`.

You will also need to update the `driver` for your SQS connection to `dynamic-sqs`.

## Setup

First, you should create your job class, the exact same way you would your standard Laravel jobs. Then, we need to
create a "handler" class, this class is responsible for taking the payload from your SQS message and returning an
instance of your job class.

This class should implement the `JobHandlerContract` and define a `handle` method, which returns a job instance.

```php
use App\Jobs\SendWelcomeEmail;
use eDriving\DynamicSqs\JobHandlerContract;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeEmailHandler implements JobHandlerContract
{
    public function handle(array $payload): ShouldQueue
    {
        return SendWelcomeEmail($payload['data']['userId']);    
    }
}
```

With your handler class set up, we then need to define how to map any given SQS message, to a particular handler. To
do this, open your newly published `config/dynamic-sqs.php` config file. In here, there are two properties we need to
define, `discoverer` and `map`.

### Discoverer

This property is a closure which is responsible for taking a given payload, and returning the "handler id". This ID is a
value in your payload which will be used determine which handler to use for this message. One is set up for you already,
which returns the "handler" value from the payload. You're free to change this to match your particular message format.

```php
[
    'discoverer' => function (array $payload): ?string {
        return $payload['handler'] ?? null;
    },
]
```

### Map

Finally, we need to map the handler ID's, to their handler classes. You do this by populating the `map` property with
key => value pairs. The key being the handler ID, and the map being the class-string of the
handler class.

```php
[
    'map' => [
        'sendWelcomeEmail' => SendWelcomeEmailHandler::class 
    ]
]
```