<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\Database;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\SqlQueryException;
use RuntimeException;

/**
 * Пустая схема в `{database}_test`. Строки рабочей базы не копируются.
 * Вопрос в консоли не задаётся: схема пересоздаётся, только если базы нет,
 * число таблиц разошлось или RECREATE_TEST_DB=1.
 */
final class TestDatabase
{
    private static bool $ready = false;

    public static function useClone(): void
    {
        if (self::$ready) {
            return;
        }

        $connection = Application::getConnection();
        $configuration = $connection->getConfiguration();
        $database = (string) $configuration['database'];

        if (str_ends_with($database, '_test')) {
            throw new RuntimeException('Refusing to clone a database that is already a test database: ' . $database);
        }

        $testConfiguration = $configuration;
        $testConfiguration['database'] = $database . '_test';

        $sqlHelper = $connection->getSqlHelper();
        $connection->queryExecute('CREATE DATABASE IF NOT EXISTS ' . $sqlHelper->quote($testConfiguration['database']));

        $testConnection = new $configuration['className']($testConfiguration);
        $testConnection->connect();

        $sourceCount = self::tableCount($connection, $database);
        $testCount = self::tableCount($testConnection, $testConfiguration['database']);
        $recreate = self::envFlag('RECREATE_TEST_DB') || $sourceCount !== $testCount;

        if ($recreate) {
            self::recreateSchema($connection, $testConnection, $sourceCount);
        }

        Application::getInstance()->getConnectionPool()->setConnectionParameters('default', $testConfiguration);

        global $DB;
        $DB->Disconnect();
        $DB->Connect(
            $testConfiguration['host'],
            $testConfiguration['database'],
            $testConfiguration['login'],
            $testConfiguration['password'],
            'default'
        );

        self::$ready = true;
    }

    /**
     * @param list<string> $tables
     */
    public static function truncate(array $tables): void
    {
        $connection = Application::getConnection();
        $database = (string) $connection->getDatabase();

        if (!str_ends_with($database, '_test')) {
            throw new RuntimeException('Refusing to truncate tables outside the test database. Current database: ' . $database);
        }

        foreach ($tables as $table) {
            self::assertIdentifier($table);
            $connection->queryExecute('TRUNCATE TABLE ' . $connection->getSqlHelper()->quote($table));
        }
    }

    /**
     * @throws SqlQueryException
     */
    private static function recreateSchema(Connection $source, Connection $target, int $totalCount): void
    {
        fwrite(STDERR, 'Cloning MySQL schema into ' . $target->getDatabase() . ' (' . $totalCount . " tables)...\n");

        $target->queryExecute('SET FOREIGN_KEY_CHECKS=0');
        self::dropForeignKeys($target);

        foreach (self::listRelations($target, 'BASE TABLE') as $table) {
            $target->queryExecute('DROP TABLE IF EXISTS ' . $target->getSqlHelper()->quote($table));
        }

        foreach (self::listRelations($target, 'VIEW') as $table) {
            $target->queryExecute('DROP VIEW IF EXISTS ' . $target->getSqlHelper()->quote($table));
        }

        foreach (self::listRelations($source, 'BASE TABLE') as $table) {
            $quoted = $source->getSqlHelper()->quote($table);
            $schema = $source->query('SHOW CREATE TABLE ' . $quoted)->fetch();
            $target->queryExecute($schema['Create Table']);
            try {
                $target->queryExecute(sprintf('ALTER TABLE %s AUTO_INCREMENT = 1', $quoted));
            } catch (SqlQueryException) {
                // Таблица без автоинкремента.
            }
        }

        foreach (self::listRelations($source, 'VIEW') as $table) {
            $quoted = $source->getSqlHelper()->quote($table);
            $schema = $source->query('SHOW CREATE TABLE ' . $quoted)->fetch();
            $target->queryExecute($schema['Create View']);
        }

        $target->queryExecute('SET FOREIGN_KEY_CHECKS=1');
        fwrite(STDERR, "MySQL test schema is ready.\n");
    }

    /**
     * @return list<string>
     */
    private static function listRelations(Connection $connection, string $type): array
    {
        if (!in_array($type, ['BASE TABLE', 'VIEW'], true)) {
            throw new RuntimeException('Unexpected table type: ' . $type);
        }

        $names = [];
        foreach ($connection->query('SHOW FULL TABLES WHERE Table_type = "' . $type . '"')->fetchAll() as $row) {
            $names[] = (string) current($row);
        }

        return $names;
    }

    private static function tableCount(Connection $connection, string $database): int
    {
        $name = $connection->getSqlHelper()->forSql($database);
        $row = $connection->query(
            'SELECT
                (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = "' . $name . '" AND table_type = "BASE TABLE") +
                (SELECT COUNT(*) FROM information_schema.views WHERE table_schema = "' . $name . '") AS TOTAL_COUNT'
        )->fetch();

        return (int) $row['TOTAL_COUNT'];
    }

    private static function dropForeignKeys(Connection $connection): void
    {
        $schema = $connection->getSqlHelper()->forSql((string) $connection->getDatabase());
        $rows = $connection->query(
            'SELECT TABLE_NAME, CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_TYPE = "FOREIGN KEY" AND TABLE_SCHEMA = "' . $schema . '"'
        )->fetchAll();

        $helper = $connection->getSqlHelper();
        foreach ($rows as $row) {
            $connection->queryExecute(
                'ALTER TABLE ' . $helper->quote((string) $row['TABLE_NAME'])
                . ' DROP FOREIGN KEY ' . $helper->quote((string) $row['CONSTRAINT_NAME'])
            );
        }
    }

    private static function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException('Unsafe table name: ' . $name);
        }
    }

    private static function envFlag(string $name): bool
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes'], true);
    }
}
