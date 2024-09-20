<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\PHPUnit\Integration\Data\ORM;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Web;
use Iterator;

class Json implements SeedInterface
{
    protected string $className;
    protected array $data;

    /**
     * @throws ArgumentException
     */
    public function __construct(string $filename)
    {
        $contents = file_get_contents($filename);
        $data = Web\Json::decode($contents);

        $this->className = $data['className'];
        $this->data = $data['data'];
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getRowIterator(): Iterator
    {
        foreach ($this->data as $row) {
            yield $row;
        }
    }
}
