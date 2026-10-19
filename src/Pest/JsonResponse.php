<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\Pest;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly string $raw,
    ) {
    }
}
