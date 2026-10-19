# Unit: модульное тестирование

Класс теста наследует `\Kosmosafive\Bitrix\Tests\PHPUnit\BitrixTestCase`.

Реализуйте тесты для всех публичных методов класса.
Название тестового метода — оригинальное с префиксом `test` (например, `getId` → `testGetId`).

## Провайдер данных

```php
use PHPUnit\Framework\Attributes\DataProvider;

public static function exampleProvider(): array
{
    return [
        ['id' => 1],
        ['id' => 2],
        ['id' => 3],
    ];
}

#[DataProvider('exampleProvider')]
public function testGetId(int $id): void
{
    $entity = new Entity($id);
    $this->assertEquals($id, $entity->getId());
}
```

Результат — цикл по данным провайдера: три проверки.

Дополнительно: [BitrixTestCase](BitrixTestCase), [Мутационное тестирование](Мутационное-тестирование).
