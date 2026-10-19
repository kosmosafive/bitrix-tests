# BitrixTestCase

Есть два базовых класса:

| Класс | Назначение |
|-------|------------|
| `\Kosmosafive\Bitrix\Tests\PHPUnit\BitrixTestCase` | PHPUnit Unit / базовая интеграция |
| `\Kosmosafive\Bitrix\Tests\Pest\BitrixTestCase` | Pest + HTTP API + `TestDatabase` |

Ниже — общие возможности PHPUnit-варианта. Pest-расширения: [Pest](Pest).

## Кастомные утверждения

- `assertResultSuccess` — принимает `\Bitrix\Main\Result`

## Идентификатор текущего пользователя

```php
$this->setUserId(1);   // установить
$this->setUserId(null); // сбросить
```

Ставится только идентификатор. События авторизации не вызываются.

## Глобальные переменные

Перед каждым тестом `_SESSION` восстанавливается до состояния до запуска.

Чтобы расширить список, переопределите:

```php
protected function getBackupGlobalsKeys(): array
{
    return ['_COOKIE'];
}
```

Ключ для `$_SESSION` — `_SESSION`.

## Before / After

Вместо переопределения `setUp()` можно использовать атрибуты `Before` / `BeforeClass` / `After` / `AfterClass`.

```php
use PHPUnit\Framework\Attributes\Before;

class SameServiceTest extends TestCase
{
    protected SameServiceInterface $service;

    #[Before]
    protected function setUpService(): void
    {
        Loader::requireModule('example.main');
        $this->service = ServiceLocator::getInstance()->get(SameServiceInterface::class);
    }
}
```

## Mockery

[Документация Mockery](https://docs.mockery.io/en/latest/).

Переопределение класса без передачи в точку вызова (overload):

```php
use Mockery;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
class SameServiceTest extends TestCase
{
    public function testGetDetail(): void
    {
        $access = Mockery::mock('overload:\Access');
        $access->shouldReceive('canView')->once()->with(1)->andReturn(true);

        $result = $this->service->getDetail(1);
        $this->assertResultSuccess($result);
    }
}
```

> `#[RunTestsInSeparateProcesses]` обязателен при overload.  
> Не добавляйте атрибут, если тесты запускаются через Pest.
