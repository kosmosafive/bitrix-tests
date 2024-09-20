<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM;

use Iterator;

interface SeedInterface
{
    public function getClassName(): string;
    public function getRowIterator(): Iterator;
}
