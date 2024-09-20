<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\Console;

use Symfony\Component\Console\Input\Input as Base;

class Input extends Base
{
    protected function parse(): void
    {
        // TODO: Implement parse() method.
    }

    public function getFirstArgument(): ?string
    {
        // TODO: Implement getFirstArgument() method.
    }

    public function hasParameterOption(array|string $values, bool $onlyParams = false): bool
    {
        // TODO: Implement hasParameterOption() method.
    }

    public function getParameterOption(
        array|string $values,
        float|array|bool|int|string|null $default = false,
        bool $onlyParams = false
    ): mixed {
        // TODO: Implement getParameterOption() method.
    }

    public function __toString(): string
    {
        // TODO: Implement __toString() method.
    }
}
