<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit\Integration;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\SqlQueryException;
use CDatabase;
use Exception;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\QuestionHelper;
use Kosmos\BitrixTests\PHPUnit\BitrixTestCase;
use Kosmos\BitrixTests\Console;

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
        if (static::$connection === null) {
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

            $console = new Console\Helper();

            $recreateTables = true;
            if ($totalCount === $testTotalCount) {

                $silentArgv = array_filter($_SERVER['argv'], static function ($arg) {
                    return ($arg === 'Standard input code')
                        || str_starts_with($arg, '--coverage-');
                });

                if (
                    isset($_SERVER['TERM_SESSION_ID'])
                    && empty($silentArgv)
                ) {
                    $questionHelper = new QuestionHelper();
                    $question = new ConfirmationQuestion(
                        'Recreate tables? (y|yes): ',
                        false,
                        '/^(y|yes)/i'
                    );

                    $recreateTables = $questionHelper->ask($console->getInput(), $console->getOutput(), $question);
                } else {
                    $recreateTables = false;
                }
            }

            if ($recreateTables) {
                $section = $console->getOutput()->section();
                $section->writeln('Recreating tables...');

                $progressBar = $console->createProgressBar($totalCount);

                $progressBar->start();

                foreach ($connection->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll() as $row) {
                    $table = $sqlHelper->quote(current($row));
                    $schema = $connection->query("SHOW CREATE TABLE $table")->fetch();
                    $testConnection->query("DROP TABLE IF EXISTS $table");
                    $testConnection->query($schema['Create Table']);
                    $testConnection->query("ALTER TABLE $table AUTO_INCREMENT = 1");
                    $progressBar->advance();
                }

                foreach ($connection->query('SHOW FULL TABLES WHERE Table_type = "VIEW"')->fetchAll() as $row) {
                    $table = $sqlHelper->quote(current($row));
                    $schema = $connection->query("SHOW CREATE TABLE $table")->fetch();
                    $testConnection->query("DROP VIEW IF EXISTS $table");
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
            $db = new CDatabase();
            $db->Connect(
                $testConfiguration['host'],
                $testConfiguration['database'],
                $testConfiguration['login'],
                $testConfiguration['password'],
                'default'
            );
            $DB = $db;

            static::$connection = $testConnection;
        }
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
        return (int)$query->fetch()['TOTAL_COUNT'];
    }

    public static function tearDownAfterClass(): void
    {
        if (static::$ormDataFactory) {
            static::$ormDataFactory = null;
        }
    }

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $ormDataFilenameList = $this->getOrmDataFilenameList();
        if (!empty($ormDataFilenameList)) {
            static::$ormDataFactory = new Data\ORM\Factory(
                static::class,
                ...$ormDataFilenameList,
            );
            static::$ormDataFactory->up();
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        static::$ormDataFactory?->tearDown();
    }

    protected function getOrmDataFilenameList(): array
    {
        return [];
    }
}
