<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM\Collector;

use Bitrix\Main\Application;
use Bitrix\Main\IO;
use Bitrix\Main\IO\FileDeleteException;
use Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM\FileUpload;

class File extends Base
{
    use FileUpload;

    public function clear(): void
    {
        parent::clear();

        $connection = Application::getConnection();
        $tableName = 'b_file_hash';
        if ($connection->isTableExists($tableName)) {
            $connection->truncateTable($tableName);
        }

        $dir = new IO\Directory($this->getFileUploadDir(true));
        if ($dir->isExists()) {
            try {
                $dir->delete();
            } catch (FileDeleteException) {
            }
        }
    }
}
