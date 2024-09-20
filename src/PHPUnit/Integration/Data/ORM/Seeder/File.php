<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM\Seeder;

use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use CFile;
use Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM\FileUpload;

class File extends Base
{
    use FileUpload;

    public function add(array $row): Result
    {
        $result = new AddResult();

        if(!file_exists($row['tmp_name'])) {
            $row['tmp_name'] = DATA_DIR . '/' . $row['tmp_name'];
        }

        $id = CFile::SaveFile($row, $this->getFileUploadDir());
        if ($id > 0) {
            $result->setId($id);
        } else {
            $result->addError(new Error('CFile::SaveFile error'));
        }

        return $result;
    }
}
