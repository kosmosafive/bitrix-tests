<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit;

use Bitrix\Main\Result;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

abstract class BitrixTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;
    private array $backupGlobalsKeys = ['_SESSION'];
    private array $backupGlobalsMap = [];

    protected function setUp(): void
    {
        $this->backupGlobalsKeys = array_unique(
            array_merge(
                $this->backupGlobalsKeys,
                $this->getBackupGlobalsKeys()
            )
        );

        foreach ($this->backupGlobalsKeys as $key) {
            $this->backupGlobalsMap[$key] = $GLOBALS[$key];
        }

        Mockery::resetContainer();
    }

    protected function tearDown(): void
    {
        foreach ($this->backupGlobalsKeys as $key) {
            if(array_key_exists($key, $this->backupGlobalsMap)) {
                $GLOBALS[$key] = $this->backupGlobalsMap[$key];
            }
        }

        Mockery::close();
    }

    /**
     * Позволяет расширить список глобальных переменных,
     * которые будут восстановлены после выполнения теста до состояния до вызова теста.
     * В качестве значений массива указываются ключи $GLOBALS. Например, для $_SESSION ключом будет _SESSION.
     */
    protected function getBackupGlobalsKeys(): array
    {
        return [];
    }

    /**
     * Устанавливает идентификатор текущего пользователя.
     * Устанавливает только идентификатор.
     * События процесса авторизации не будут вызваны.
     */
    protected function setUserId(?int $id = null): void
    {
        if ($id) {
            $_SESSION['SESS_AUTH']['USER_ID'] = $id;
        } else {
            unset($_SESSION['SESS_AUTH']['USER_ID']);
        }
    }

    protected function assertResultSuccess(Result $result): void
    {
        $this->assertTrue($result->isSuccess(), print_r($result->getErrorMessages(), true));
    }
}
