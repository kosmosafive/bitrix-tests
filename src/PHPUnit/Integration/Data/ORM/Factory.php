<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\FileTable;
use Bitrix\Main\UserTable;
use Exception;
use Kosmos\BitrixTests\Console;
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

    /**
     * @throws Exception
     */
    public function up(): void
    {
        $baseSeedPath = $this->getBaseSeedPath();

        $console = new Console\Helper();
        $styler = $console->getStyler();

        foreach ($this->filenameList as $filename) {
            $originFilename = $filename;

            if (!file_exists($filename)) {
                $filename = $baseSeedPath . $filename;

                if (!file_exists($filename)) {
                    $styler->error('File not found: ' . $originFilename);
                    continue;
                }
            }

            $seed = $this->createSeed($filename);
            $seeder = $this->createSeeder($seed);

            $this->tearDownByClassName($seed->getClassName());

            foreach ($seed->getRowIterator() as $row) {
                $addResult = $seeder->add($row);
                if (!$addResult->isSuccess()) {
                    $styler->error('Failed to add');
                    $styler->table(['error'], [[print_r($addResult->getErrorMessages(), true)]]);
                    $styler->table(['filename'], [[$originFilename]]);
                    $styler->table(['row'], [[print_r($row, true)]]);
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
        $classInfo = (new ReflectionClass($this->className));
        return dirname($classInfo->getFileName()) . '/.seed/' . $classInfo->getShortName() . '/';
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

    public function tearDown(): void
    {
        foreach ($this->classNameList as $className) {
            $this->tearDownByClassName($className);
        }
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
