<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\Pest;

use Bitrix\Main\Application;
use Kosmosafive\Bitrix\Tests\Database\TestDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

abstract class BitrixTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var list<string> */
    private array $backupGlobalsKeys = ['_SESSION'];

    /** @var array<string, mixed> */
    private array $backupGlobalsMap = [];

    public static function setUpBeforeClass(): void
    {
        TestDatabase::useClone();
    }

    protected function setUp(): void
    {
        $this->backupGlobalsKeys = array_values(array_unique(array_merge(
            $this->backupGlobalsKeys,
            $this->getBackupGlobalsKeys(),
        )));

        foreach ($this->backupGlobalsKeys as $backupGlobalKey) {
            if (array_key_exists($backupGlobalKey, $GLOBALS)) {
                $this->backupGlobalsMap[$backupGlobalKey] = $GLOBALS[$backupGlobalKey];
            }
        }

        $this->startSession();
        Mockery::resetContainer();
    }

    protected function tearDown(): void
    {
        foreach ($this->backupGlobalsMap as $backupGlobalKey => $value) {
            $GLOBALS[$backupGlobalKey] = $value;
        }

        Mockery::close();
    }

    /**
     * Ключи $GLOBALS, которые нужно вернуть после теста. Для $_SESSION ключ — `_SESSION`.
     *
     * @return list<string>
     */
    protected function getBackupGlobalsKeys(): array
    {
        return [];
    }

    /**
     * Ставит только идентификатор. События авторизации не вызываются.
     */
    protected function setUserId(?int $id = null): void
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        if ($id !== null && $id !== 0) {
            $_SESSION['SESS_AUTH']['USER_ID'] = $id;
        } else {
            unset($_SESSION['SESS_AUTH']['USER_ID']);
        }
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed> $body
     */
    protected function apiRequest(
        string $method,
        string $uri,
        array $query = [],
        array $body = [],
        bool $withCsrf = true,
    ): JsonResponse {
        return Kernel::request($method, $uri, $query, $body, $withCsrf);
    }

    /**
     * @param list<string> $tables
     */
    protected function truncateTables(array $tables): void
    {
        TestDatabase::truncate($tables);
    }

    private function startSession(): void
    {
        $session = Application::getInstance()->getSession();
        if (!$session->isStarted() && $session->isAccessible()) {
            $session->start();
        }
    }
}
