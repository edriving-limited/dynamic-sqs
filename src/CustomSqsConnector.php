<?php

namespace eDriving\CustomSqsDriver;

use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Support\Arr;

class CustomSqsConnector extends SqsConnector
{
    /**
     * @param array<string, mixed> $config
     * @return QueueContract
     */
    public function connect(array $config): QueueContract
    {
        $config = $this->getDefaultConfiguration($config);

        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return new CustomSqsDriver(
            new SqsClient(
                Arr::except($config, ['token'])
            ),
            $config['queue'],
            $config['prefix'] ?? '',
            $config['suffix'] ?? '',
        );
    }
}
