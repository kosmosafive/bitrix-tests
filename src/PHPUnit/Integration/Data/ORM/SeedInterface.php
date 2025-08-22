<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\PHPUnit\Integration\Data\ORM;

use Iterator;

interface SeedInterface
{
    public function getClassName(): string;
    public function getRowIterator(): Iterator;
}
