# Pest

Базовый класс для Pest / PHPUnit-тестов Bitrix:  
`\Kosmosafive\Bitrix\Tests\Pest\BitrixTestCase`.

Перед классом тестов вызывается [`TestDatabase::useClone()`](Тестовая-база-данных) — переключение на `{database}_test`.

## Пример

```php
<?php

declare(strict_types=1);

use Kosmosafive\Bitrix\Tests\Pest\BitrixTestCase;

uses(BitrixTestCase::class);

it('returns entity list', function (): void {
    $this->setUserId(1);

    $response = $this->apiRequest('GET', '/api/example/list/');

    expect($response->status)->toBe(200)
        ->and($response->body['status'])->toBe('success');
});
```

## HTTP-запросы: `apiRequest`

In-process диспетчер действий Bitrix без `Application::end()` / `exit()`.

```php
$response = $this->apiRequest(
    method: 'POST',
    uri: '/api/example/create/',
    query: [],
    body: ['title' => 'Demo'],
    withCsrf: true,
);
```

| Параметр | Описание |
|----------|----------|
| `$method` | HTTP-метод |
| `$uri` | Абсолютный путь маршрута (`/api/...`), query в URI допускается |
| `$query` | Дополнительные GET-параметры |
| `$body` | Тело для POST / PUT / PATCH / DELETE |
| `$withCsrf` | Автодобавление `sessid`, если его ещё нет |

Возвращается [`JsonResponse`](#jsonresponse): статус, декодированное тело, сырой JSON.

Реализация — `\Kosmosafive\Bitrix\Tests\Pest\Kernel`:

- матчинг маршрута Bitrix Router;
- запуск через `Controller::run()`, а не `HttpApplication::runController()`;
- поддержка только маршрутов вида `[ControllerClass, action]`.

## JsonResponse

```php
final class JsonResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly string $raw,
    ) {
    }
}
```

## Очистка таблиц

```php
$this->truncateTables(['b_example', 'b_example_item']);
```

Работает только на БД с суффиксом `_test` (см. [Тестовая БД](Тестовая-база-данных)).

## Прочее из базового класса

- `setUserId(?int $id)` — идентификатор текущего пользователя без событий авторизации
- бэкап / восстановление `$GLOBALS` (по умолчанию `_SESSION`)
- интеграция Mockery

Подробнее: [BitrixTestCase](BitrixTestCase).
