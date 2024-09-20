<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM\Seeder;

use Bitrix\Main\ORM\Data\AddResult;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use CUser;

class User extends Base
{
    public function add(array $row): Result
    {
        $result = new AddResult();

        $user = new CUser();

        $id = $user->Add($row);
        if ($id > 0) {
            $result->setId($id);
        } else {
            $result->addError(new Error($user->LAST_ERROR));
        }

        return $result;
    }
}
