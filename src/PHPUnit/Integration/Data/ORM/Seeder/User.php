<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\PHPUnit\Integration\Data\ORM\Seeder;

use Bitrix\Main\Error;
use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\Result;
use CUser;

class User extends Base
{
    #[\Override]
    public function add(array $row): Result
    {
        $addResult = new AddResult();

        $cUser = new CUser();

        $id = $cUser->Add($row);
        if ($id > 0) {
            $addResult->setId($id);
        } else {
            $addResult->addError(new Error($cUser->LAST_ERROR));
        }

        return $addResult;
    }
}
