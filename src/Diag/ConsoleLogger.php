<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\Diag;

use Bitrix\Main\Diag\Logger;
use Kosmos\BitrixTests\Console;

class ConsoleLogger extends Logger
{
    protected function logMessage(string $level, string $message): void
    {
        $context = $this->context;
        unset($context['date'], $context['host']);
        $context['level'] = $level;

        $console = new Console\Helper();

        $style = match($level) {
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

        $styler = $console->getStyler();
        $styler->block($message, $level, $style, ' ', true);
        $styler->text(print_r($context, true));
    }
}
