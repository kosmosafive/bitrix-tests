<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\Search;

use mysqli;
use RuntimeException;

/**
 * Изоляция таблиц Manticore.
 * По умолчанию на том же демоне создаются таблицы с суффиксом, без копирования документов.
 * Если задан другой host/port, суффикс может быть пустым: имена те же, демон другой.
 * TRUNCATE боевой таблицы метод не выполняет.
 */
final class ManticoreTestIndexes
{
    private ?mysqli $connection = null;

    private ?mysqli $sourceConnection = null;

    /**
     * @param list<string> $sourceTables
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly array $sourceTables,
        private readonly string $suffix = '_test',
        private readonly ?string $sourceHost = null,
        private readonly ?int $sourcePort = null,
    ) {
        foreach ($sourceTables as $table) {
            self::assertIdentifier($table);
        }

        if ($this->suffix !== '' && !preg_match('/^[A-Za-z0-9_]+$/', $this->suffix)) {
            throw new RuntimeException('Unsafe Manticore suffix: ' . $this->suffix);
        }
    }

    public function testName(string $sourceTable): string
    {
        self::assertIdentifier($sourceTable);

        if ($this->suffix === '' || str_ends_with($sourceTable, $this->suffix)) {
            return $sourceTable;
        }

        return $sourceTable . $this->suffix;
    }

    /**
     * @return list<string>
     */
    public function testNames(): array
    {
        return array_map($this->testName(...), $this->sourceTables);
    }

    public function prepare(bool $recreate = false): void
    {
        foreach ($this->sourceTables as $sourceTable) {
            $testTable = $this->testName($sourceTable);
            $sourceDdl = $this->showCreate($this->source(), $sourceTable);
            $expected = $this->rewriteCreate($sourceDdl, $sourceTable, $testTable);
            $current = $this->showCreateOrNull($this->target(), $testTable);

            if (!$recreate && $current !== null && $this->normalize($current, $testTable) === $this->normalize($expected, $testTable)) {
                continue;
            }

            $this->execute($this->target(), 'DROP TABLE IF EXISTS ' . $this->quote($testTable));
            $this->execute($this->target(), $expected);
        }
    }

    /**
     * @param list<string> $testTables
     */
    public function truncate(array $testTables): void
    {
        if ($this->suffix === '' && $this->usesSourceDaemon()) {
            throw new RuntimeException('Refusing to truncate Manticore tables on the source daemon without a suffix.');
        }

        foreach ($testTables as $table) {
            self::assertIdentifier($table);
            if ($this->suffix !== '' && !str_ends_with($table, $this->suffix)) {
                throw new RuntimeException('Refusing to truncate Manticore table without the test suffix: ' . $table);
            }

            $this->execute($this->target(), 'TRUNCATE TABLE ' . $this->quote($table));
        }
    }

    private function usesSourceDaemon(): bool
    {
        return $this->sourceHost() === $this->host && $this->sourcePort() === $this->port;
    }

    private function sourceHost(): string
    {
        return $this->sourceHost ?? $this->host;
    }

    private function sourcePort(): int
    {
        return $this->sourcePort ?? $this->port;
    }

    private function source(): mysqli
    {
        return $this->sourceConnection ??= $this->connect($this->sourceHost(), $this->sourcePort());
    }

    private function target(): mysqli
    {
        if ($this->usesSourceDaemon()) {
            return $this->source();
        }

        return $this->connection ??= $this->connect($this->host, $this->port);
    }

    private function connect(string $host, int $port): mysqli
    {
        $mysqli = mysqli_init();
        if ($mysqli === false) {
            throw new RuntimeException('mysqli_init failed.');
        }

        $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (!$mysqli->real_connect($host, '', '', '', $port)) {
            throw new RuntimeException('Manticore connection failed: ' . $host . ':' . $port . ' ' . $mysqli->connect_error);
        }

        return $mysqli;
    }

    private function showCreate(mysqli $connection, string $table): string
    {
        $ddl = $this->showCreateOrNull($connection, $table);
        if ($ddl === null) {
            throw new RuntimeException(
                'Manticore table `' . $table . '` does not exist. Create it in the application before cloning the test table.'
            );
        }

        return $ddl;
    }

    private function showCreateOrNull(mysqli $connection, string $table): ?string
    {
        if (!$this->tableExists($connection, $table)) {
            return null;
        }

        try {
            $result = $connection->query('SHOW CREATE TABLE ' . $this->quote($table));
        } catch (mysqli_sql_exception $exception) {
            throw new RuntimeException('Manticore SHOW CREATE TABLE failed for `' . $table . '`: ' . $exception->getMessage(), 0, $exception);
        }

        if ($result === false) {
            throw new RuntimeException('Manticore SHOW CREATE TABLE failed for `' . $table . '`: ' . $connection->error);
        }

        $row = $result->fetch_assoc() ?: [];
        $result->free();

        foreach ($row as $column => $value) {
            if (is_string($value) && preg_match('/create/i', (string) $column)) {
                return $value;
            }
        }

        throw new RuntimeException('Manticore SHOW CREATE TABLE returned no DDL for `' . $table . '`.');
    }

    private function rewriteCreate(string $ddl, string $source, string $target): string
    {
        $pattern = '/^(\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?)([`"]?)'
            . preg_quote($source, '/')
            . '\2/i';
        $rewritten = preg_replace($pattern, '$1`' . $target . '`', $ddl, 1, $count);
        if (!is_string($rewritten) || $count !== 1) {
            throw new RuntimeException('Unable to rewrite Manticore DDL for `' . $source . '`.');
        }

        return $rewritten;
    }

    private function normalize(string $ddl, string $table): string
    {
        $ddl = str_replace(['`' . $table . '`', $table], 'table', $ddl);
        $collapsed = preg_replace('/\s+/', ' ', $ddl);

        return strtolower(trim(is_string($collapsed) ? $collapsed : $ddl));
    }

    private function tableExists(mysqli $connection, string $table): bool
    {
        self::assertIdentifier($table);

        try {
            $result = $connection->query("SHOW TABLES LIKE '" . $table . "'");
        } catch (mysqli_sql_exception $exception) {
            throw new RuntimeException('Manticore SHOW TABLES failed: ' . $exception->getMessage(), 0, $exception);
        }

        if (!$result instanceof \mysqli_result) {
            return false;
        }

        $row = $result->fetch_assoc();
        $result->free();

        return is_array($row);
    }

    private function execute(mysqli $connection, string $sql): void
    {
        try {
            $result = $connection->query($sql);
        } catch (mysqli_sql_exception $exception) {
            throw new RuntimeException('Manticore SQL failed: ' . $exception->getMessage() . ' SQL: ' . $sql, 0, $exception);
        }

        if ($result === false) {
            throw new RuntimeException('Manticore SQL failed: ' . $connection->error . ' SQL: ' . $sql);
        }

        if ($result instanceof \mysqli_result) {
            $result->free();
        }

        try {
            while ($connection->more_results() && $connection->next_result()) {
                $extra = $connection->store_result();
                if ($extra instanceof \mysqli_result) {
                    $extra->free();
                }
            }
        } catch (mysqli_sql_exception) {
            // Одно выражение уже выполнено.
        }
    }

    private function quote(string $name): string
    {
        self::assertIdentifier($name);

        return '`' . $name . '`';
    }

    private static function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException('Unsafe Manticore table name: ' . $name);
        }
    }

}
