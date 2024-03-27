<?php

namespace eDriving\DynamicSqs\Commands;

class DiscovererHandler
{
    public static function discover(array $payload): ?string
    {
        return $payload['handler'] ?? null;
    }
}
