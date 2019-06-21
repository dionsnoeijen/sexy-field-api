<?php

namespace Tardigrades\SectionField\Api\Handler;

use Throwable;

class TriggerHandlerException extends \Exception
{
    public function __construct($message = "Invalid trigger service", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
