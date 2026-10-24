<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\JsonFormatter as MonologJsonFormatter;
use Monolog\Handler\StreamHandler;

class JsonFormatter
{
    public function __invoke(IlluminateLogger $logger): void
    {
        $monolog = $logger->getLogger();

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof StreamHandler) {
                $handler->setFormatter(new MonologJsonFormatter);
            }
        }
    }
}
