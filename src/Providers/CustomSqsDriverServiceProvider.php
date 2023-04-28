<?php

namespace eDriving\CustomSqsDriver\Providers;

use eDriving\CustomSqsDriver\CustomSqsConnector;
use Illuminate\Support\ServiceProvider;

class CustomSqsDriverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $manager = $this->app->get('queue');

        $manager->addConnector('custom-sqs', function () {
            return new CustomSqsConnector();
        });
    }
}
