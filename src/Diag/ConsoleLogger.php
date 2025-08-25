<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\Diag;

use Bitrix\Main\Diag\Logger;
use Kosmosafive\Bitrix\Tests\Console;

class ConsoleLogger extends Logger
{
    protected function logMessage(string $level, string $message): void
    {
        $context = $this->context;
        unset($context['date'], $context['host']);
        $context['level'] = $level;

        $helper = new Console\Helper();

        $style = match ($level) {
            'emergency',
            'alert',
            'critical',
            'error' => 'fg=white;bg=red',
            'notice',
            'warning' => 'fg=black;bg=yellow',
            'info' => 'fg=black;bg=blue',
            'debug' => 'fg=black;bg=white',
            default => '',
        };

        $symfonyStyle = $helper->getStyler();
        $symfonyStyle->block($message, $level, $style, ' ', true);
        $symfonyStyle->text(print_r($context, true));
    }
}
