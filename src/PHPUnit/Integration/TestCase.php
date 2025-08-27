<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\PHPUnit\Integration;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\SqlQueryException;
use Exception;
use Kosmosafive\Bitrix\Tests\Console;
use Kosmosafive\Bitrix\Tests\PHPUnit\BitrixTestCase;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;

abstract class TestCase extends BitrixTestCase
{
    protected static ?Connection $connection = null;

    protected static ?Data\ORM\Factory $ormDataFactory = null;

    /**
     * @throws SqlQueryException
     * @throws Exception
     */
    public static function setUpBeforeClass(): void
    {
        if (!static::$connection instanceof \Bitrix\Main\DB\Connection) {
            $connection = Application::getConnection();

            $configuration = $connection->getConfiguration();

            $testConfiguration = $configuration;
            $testConfiguration['database'] .= '_test';

            $sqlHelper = $connection->getSqlHelper();
            $testDBName = $sqlHelper->quote($testConfiguration['database']);
            $connection->query('CREATE DATABASE IF NOT EXISTS ' . $testDBName);

            $testConnection = new $configuration['className']($testConfiguration);
            $testConnection->connect();

            $totalCount = static::getTotalTablesCount($connection, $configuration['database']);
            $testTotalCount = static::getTotalTablesCount($testConnection, $testConfiguration['database']);

            $helper = new Console\Helper();

            $recreateTables = true;

            if ($totalCount === $testTotalCount) {
                $silentArgv = array_filter($_SERVER['argv'], static fn ($arg): bool => ($arg === 'Standard input code')
                    || str_starts_with((string) $arg, '--coverage-'));

                if (
                    isset($_SERVER['TERM_SESSION_ID'])
                    && $silentArgv === []
                ) {
                    $questionHelper = new QuestionHelper();
                    $confirmationQuestion = new ConfirmationQuestion(
                        'Recreate tables? (y|yes): ',
                        false,
                        '/^(y|yes)/i'
                    );

                    $recreateTables = $questionHelper->ask(
                        $helper->getInput(),
                        $helper->getOutput(),
                        $confirmationQuestion
                    );
                } else {
                    $recreateTables = false;
                }
            }

            if ($recreateTables) {
                $section = $helper->getOutput()->section();

                $section->writeln('Dropping tables...');

                $progressBar = $helper->createProgressBar($totalCount);

                $progressBar->start();

                static::dropForeignKeys($testConnection);

                foreach (
                    $testConnection->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll() as $row
                ) {
                    $table = $sqlHelper->quote(current($row));
                    $testConnection->query('DROP TABLE IF EXISTS ' . $table);
                    $progressBar->advance();
                }

                foreach ($testConnection->query('SHOW FULL TABLES WHERE Table_type = "VIEW"')->fetchAll() as $row) {
                    $table = $sqlHelper->quote(current($row));
                    $testConnection->query('DROP VIEW IF EXISTS ' . $table);
                    $progressBar->advance();
                }

                $progressBar->finish();
                $progressBar->clear();

                $section->writeln('Recreating tables...');

                $progressBar = $helper->createProgressBar($totalCount);

                $progressBar->start();

                foreach ($connection->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll() as $row) {
                    $table = $sqlHelper->quote(current($row));
                    $schema = $connection->query('SHOW CREATE TABLE ' . $table)->fetch();
                    $testConnection->query($schema['Create Table']);
                    $testConnection->query(sprintf('ALTER TABLE %s AUTO_INCREMENT = 1', $table));
                    $progressBar->advance();
                }

                foreach ($connection->query('SHOW FULL TABLES WHERE Table_type = "VIEW"')->fetchAll() as $row) {
                    $table = $sqlHelper->quote(current($row));
                    $schema = $connection->query('SHOW CREATE TABLE ' . $table)->fetch();
                    $testConnection->query($schema['Create View']);
                    $progressBar->advance();
                }

                $progressBar->finish();
                $progressBar->clear();

                $section->clear();
            }

            $connectionPool = Application::getInstance()->getConnectionPool();
            $connectionPool->setConnectionParameters('default', $testConfiguration);

            global $DB;

            $DB->Disconnect();
            $DB->Connect(
                $testConfiguration['host'],
                $testConfiguration['database'],
                $testConfiguration['login'],
                $testConfiguration['password'],
                'default'
            );

            static::$connection = $testConnection;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (static::$ormDataFactory instanceof \Kosmosafive\Bitrix\Tests\PHPUnit\Integration\Data\ORM\Factory) {
            static::$ormDataFactory = null;
        }
    }

    /**
     * @throws Exception
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $ormDataFilenameList = $this->getOrmDataFilenameList();
        if ($ormDataFilenameList !== []) {
            static::$ormDataFactory = new Data\ORM\Factory(
                static::class,
                ...$ormDataFilenameList,
            );
            static::$ormDataFactory->up();
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        static::$ormDataFactory?->tearDown();
    }

    /**
     * @throws SqlQueryException
     */
    protected static function getTotalTablesCount(Connection $connection, string $database): int
    {
        $query = $connection->query(
            'SELECT
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = "' . $database . '" AND table_type = "BASE TABLE") +
  (SELECT COUNT(*) FROM information_schema.views WHERE table_schema = "' . $database . '") AS TOTAL_COUNT;'
        );
        return (int) $query->fetch()['TOTAL_COUNT'];
    }

    protected function getOrmDataFilenameList(): array
    {
        return [];
    }

    /**
     * @throws SqlQueryException
     */
    protected static function dropForeignKeys(Connection $connection): void
    {
        $sql = "
            SELECT
                TABLE_NAME,
                CONSTRAINT_NAME
            FROM
                INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE
                CONSTRAINT_TYPE = 'FOREIGN KEY'
                AND TABLE_SCHEMA = '" . $connection->getDatabase() . "'
        ";

        $query = $connection->query($sql);
        while ($row = $query->fetch()) {
            $sql = "
                ALTER TABLE {$row['TABLE_NAME']}
                DROP FOREIGN KEY {$row['CONSTRAINT_NAME']};
            ";
            $connection->query($sql);
        }
    }
}
