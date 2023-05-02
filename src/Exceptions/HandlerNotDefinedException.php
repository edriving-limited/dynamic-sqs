<?php

namespace eDriving\DynamicSqs\Exceptions;

class HandlerNotDefinedException extends \Exception
{
    /** @var string */
    protected $message = 'Handler not defined';
}
