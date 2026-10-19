# Manticore

Класс `\Kosmosafive\Bitrix\Tests\Search\ManticoreTestIndexes` изолирует поисковые таблицы для тестов.

## Идея

По умолчанию на том же демоне Manticore создаются таблицы с суффиксом (по умолчанию `_test`).  
Документы **не копируются** — только DDL.

Если указать другой `host` / `port`, суффикс можно оставить пустым: имена таблиц те же, демон другой.

`TRUNCATE` боевой таблицы метод **не выполняет**.

## Использование

```php
use Kosmosafive\Bitrix\Tests\Search\ManticoreTestIndexes;

$indexes = new ManticoreTestIndexes(
    host: '127.0.0.1',
    port: 9306,
    sourceTables: ['products', 'categories'],
    suffix: '_test',
);

// Создать / обновить тестовые таблицы по DDL исходных
$indexes->prepare();

// Принудительно пересоздать
$indexes->prepare(recreate: true);

// Имена тестовых таблиц
$indexes->testName('products'); // products_test
$indexes->testNames();          // ['products_test', 'categories_test']

// Очистка только тестовых таблиц
$indexes->truncate($indexes->testNames());
```

## Отдельный демон

```php
$indexes = new ManticoreTestIndexes(
    host: '127.0.0.1',
    port: 9307,
    sourceTables: ['products'],
    suffix: '',
    sourceHost: '127.0.0.1',
    sourcePort: 9306,
);
```

Без суффикса на исходном демоне `truncate()` запрещён.

## Требования

- Исходные таблицы уже существуют в приложении (`SHOW CREATE TABLE` должен проходить).
- Имена таблиц и суффикс — только `[A-Za-z0-9_]`.
