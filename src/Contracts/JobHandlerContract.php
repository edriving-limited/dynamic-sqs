<?php

namespace eDriving\DynamicSqs\Contracts;

use Illuminate\Contracts\Queue\ShouldQueue;

interface JobHandlerContract
{
    /**
     * @param array<string, string | number | null | bool | array<mixed>> $payload
     * @return ShouldQueue
     */
    public function handle(array $payload): ShouldQueue;
}
