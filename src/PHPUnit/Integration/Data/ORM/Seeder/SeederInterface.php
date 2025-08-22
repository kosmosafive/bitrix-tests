<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\PHPUnit\Integration\Data\ORM\Seeder;

use Bitrix\Main\Result;

interface SeederInterface
{
    public function add(array $row): Result;
}
