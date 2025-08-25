<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\PHPUnit\Integration\Data\ORM\Seeder;

use Bitrix\Main\Error;
use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\Result;
use CFile;
use Kosmosafive\Bitrix\Tests\PHPUnit\Integration\Data\ORM\FileUpload;

class File extends Base
{
    use FileUpload;

    #[\Override]
    public function add(array $row): Result
    {
        $addResult = new AddResult();

        if (!file_exists($row['tmp_name'])) {
            $row['tmp_name'] = DATA_DIR . '/' . $row['tmp_name'];
        }

        $id = CFile::SaveFile($row, $this->getFileUploadDir());
        if ($id > 0) {
            $addResult->setId($id);
        } else {
            $addResult->addError(new Error('CFile::SaveFile error'));
        }

        return $addResult;
    }
}
