<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM\Seeder;

use Bitrix\Main\Orm\Data\DataManager;
use Bitrix\Main\Result;
use Exception;
use Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM\SeedInterface;

class Base implements SeederInterface
{
    public function __construct(
        protected readonly SeedInterface $seed
    ) {
    }

    /**
     * @throws Exception
     */
    public function add(array $row): Result
    {
        /**
         * @var class-string<DataManager> $className
         */
        $className = $this->seed->getClassName();
        return $className::add($row);
    }
}
