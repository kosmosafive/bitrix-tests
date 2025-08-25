<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\PHPUnit\Integration\Data\ORM;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\FileTable;
use Bitrix\Main\UserTable;
use Exception;
use Kosmosafive\Bitrix\Tests\Console;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class Factory
{
    protected readonly array $filenameList;

    protected array $classNameList = [];

    public function __construct(
        protected readonly string $className,
        string ...$filenameList
    ) {
        $this->filenameList = $filenameList;
    }

    public function tearDown(): void
    {
        foreach ($this->classNameList as $className) {
            $this->tearDownByClassName($className);
        }
    }

    /**
     * @throws Exception
     */
    public function up(): void
    {
        $baseSeedPath = $this->getBaseSeedPath();

        $helper = new Console\Helper();
        $symfonyStyle = $helper->getStyler();

        foreach ($this->filenameList as $filename) {
            $originFilename = $filename;

            if (!file_exists($filename)) {
                $filename = $baseSeedPath . $filename;

                if (!file_exists($filename)) {
                    $symfonyStyle->error('File not found: ' . $originFilename);
                    continue;
                }
            }

            $seed = $this->createSeed($filename);
            $seeder = $this->createSeeder($seed);

            $this->tearDownByClassName($seed->getClassName());

            foreach ($seed->getRowIterator() as $row) {
                $addResult = $seeder->add($row);
                if (!$addResult->isSuccess()) {
                    $symfonyStyle->error('Failed to add');
                    $symfonyStyle->table(['error'], [[print_r($addResult->getErrorMessages(), true)]]);
                    $symfonyStyle->table(['filename'], [[$originFilename]]);
                    $symfonyStyle->table(['row'], [[print_r($row, true)]]);
                }
            }

            $this->classNameList[] = $seed->getClassName();
        }
    }

    public function getClassNameList(): array
    {
        return $this->classNameList;
    }

    /**
     * @throws ReflectionException
     */
    protected function getBaseSeedPath(): string
    {
        $reflectionClass = (new ReflectionClass($this->className));
        return dirname($reflectionClass->getFileName()) . '/.seed/' . $reflectionClass->getShortName() . '/';
    }

    /**
     * @throws ArgumentException
     */
    protected function createSeed(string $filename): SeedInterface
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return match ($ext) {
            'json' => new Json($filename),
            default => throw new RuntimeException('Unsupported extension: ' . $ext)
        };
    }

    protected function createSeeder(SeedInterface $seed): Seeder\SeederInterface
    {
        $className = match ($seed->getClassName()) {
            FileTable::class => Seeder\File::class,
            UserTable::class => Seeder\User::class,
            default => Seeder\Base::class
        };

        return new $className($seed);
    }

    protected function tearDownByClassName(string $className): void
    {
        $collector = $this->createCollector($className);
        $collector->clear();
    }

    protected function createCollector(string $className): Collector\CollectorInterface
    {
        $collectorClass = match ($className) {
            FileTable::class => Collector\File::class,
            default => Collector\Base::class
        };

        return new $collectorClass($className);
    }
}
