<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Kosmos\BitrixTests\PHPUnit\Integration\Data;

trait FileUpload
{
    protected const string FILE_UPLOAD_DIR = 'tests';

    protected function getFileUploadDir(bool $absolute = false): string
    {
        $path = '';

        if ($absolute) {
            $path = Application::getDocumentRoot();

            $upload = Option::get('main', 'upload_dir', 'upload');
            $path .= '/' . $upload . '/';
        }

        return $path . static::FILE_UPLOAD_DIR;
    }
}
