<?php

namespace eDriving\DynamicSqs;

use Illuminate\Support\ServiceProvider;

class DynamicSqsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $manager = $this->app->get('queue');

        $manager->addConnector('dynamic-sqs', function () {
            return new DynamicSqsConnector();
        });

        $this->publishes([
            __DIR__ . '/../config/dynamic-sqs.php' => config_path('dynamic-sqs.php'),
        ]);
    }
}
