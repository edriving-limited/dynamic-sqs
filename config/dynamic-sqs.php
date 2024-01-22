<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Discoverer
    |--------------------------------------------------------------------------
    |
    | This defines how to determine the handler your payload. It should be a
    | closure which returns a string, which is then mapped up to the key of the
    | "map" array below.
    |
    */
    'discoverer' => [\eDriving\DynamicSqs\Commands\DiscovererHandler::class, 'discover'],
    
    /*
    |--------------------------------------------------------------------------
    | Job Map
    |--------------------------------------------------------------------------
    |
    | This array defines the id and class for your various handlers. These
    | should be key => value pairs, with the key being the ID of the handler
    | within your payload, and the value being your handler class.
    |
    */
    'map' => [
    ]
];
