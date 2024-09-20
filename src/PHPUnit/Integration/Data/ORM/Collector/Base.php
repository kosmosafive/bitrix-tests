<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM\Collector;

use Bitrix\Main\Application;

class Base implements CollectorInterface
{
    public function __construct(
        protected readonly string $className
    ) {
    }

    public function clear(): void
    {
        $connection = Application::getConnection();

        $tableName = $this->className::getTableName();
        if ($connection->isTableExists($tableName)) {
            $connection->truncateTable($tableName);
        }
    }
}
