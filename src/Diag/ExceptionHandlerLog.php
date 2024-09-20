<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\Diag;

use Bitrix\Main\Diag;
use Psr\Log;

class ExceptionHandlerLog extends Diag\ExceptionHandlerLog
{
    protected Log\LoggerInterface $logger;
    protected bool $initialized = false;

    public function initialize(array $options): void
    {
        $this->logger = new ConsoleLogger();
        $this->logger->setLevel($options['level']);
    }

    public function write($exception, $logType): void
    {
        $context = [
            'trace' => $exception->getTraceAsString(),
        ];

        $logLevel = static::logTypeToLevel($logType);

        $this->logger->log($logLevel, $exception->getMessage(), $context);
    }
}
