<?php

namespace eDriving\DynamicSqs;

use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Support\Arr;

class DynamicSqsConnector extends SqsConnector
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

        return new DynamicSqsQueue(
            new SqsClient(
                Arr::except($config, ['token'])
            ),
            $config['queue'],
            $config['prefix'] ?? '',
            $config['suffix'] ?? '',
        );
    }
}
