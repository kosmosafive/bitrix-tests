# Kosmos: Набор инструментов для тестирования Bitrix Framework

Решение обеспечивает запуск тестов для Bitrix Framework.

Поддерживаются:

- [Pest](https://pestphp.com/)
- [PHPUnit](https://phpunit.de/index.html)
- [Infection](https://infection.github.io/)

---

## Установка

Bitrix Framework в вашей инсталляции может не поддерживать 7ую версию symfony/console.
Для инсталляции необходимо поднять зависимость в bitrix/composer-bx.json.

Если используются консольные команды, необходимо скорректировать классы команд.
Например, в ядре указать тип возвращаемых данных (:int) у метода execute() в файлах:
- bitrix/modules/main/lib/cli/ormannotatecommand.php
- bitrix/modules/translate/lib/cli/indexcommand.php

## Настройка

1. Создать директорию для конфигурации тестов (например, local/tests).
Перейти в созданную директорию.

2. Создать файл настроек .env со следующей конфигурацией:

| Опция       | Значение                   | Пример |
|-------------|----------------------------|--------|
| SITE_ID     | Идентификатор сайта        | s1     |
| LANGUAGE_ID | Идентификатор языка        | ru     |
| LOG_LEVEL   | Уровень логирования, PSR-3 | error  |

3. Создать директорию для файлов, которые будут использоваться в тестах (например, local/tests/.data).

4. Создать файл bootstrap.php с содержимым:

```php
<?php

declare(strict_types=1);

use Kosmos\BitrixTests\Bootstrap;

$classLoader = require __DIR__ . '/../vendor/autoload.php';

/**
 * Опционально можно сконфигурировать автозагрузку для архитектурного тестирования
 */
$autoload = [
    ['Vendor\Example\\', __DIR__ . '/../modules/vendor.example/lib'],
];

(new Bootstrap(
    $classLoader,
    dirname(__DIR__, 2),
    __DIR__ . '/.env',
    __DIR__ . '/.data',
    $autoload
))->initialize();

```

5. Конфигурация PHPUnit

Создать файл phpunit.xml.dist. Например,


```xml
<?xml version="1.0"?>
<phpunit
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        bootstrap="bootstrap.php"
        colors="true"
        xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.3/phpunit.xsd"
        cacheDirectory=".phpunit.cache"
        executionOrder="default"
        defaultTestSuite="coverage"
>
    <testsuites>
        <testsuite name="unit">
            <directory>../modules/*/tests/Unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>../modules/*/tests/Integration</directory>
        </testsuite>
        <testsuite name="application">
            <directory>../modules/*/tests/Application</directory>
        </testsuite>
        <testsuite name="architecture">
            <directory>../modules/*/tests/Architecture</directory>
        </testsuite>
        <testsuite name="coverage">
            <directory>../modules/*/tests/Unit</directory>
            <directory>../modules/*/tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source
            restrictNotices="true"
    >
        <include>
            <directory suffix=".php">../modules/*/lib</directory>
        </include>
    </source>
</phpunit>
```

Добавьте в .gitignore директории .phpunit.cache и coverage.

6. Конфигурация Infection

Создать файл infection.json5. Например,

```json
{
    "$schema": "../vendor/infection/infection/resources/schema.json",
    "source": {
        "directories": [
            "{modules/*/lib}"
        ]
    },
    "timeout": 10,
    "logs": {
        "text": "infection/infection.log",
        "html": "infection/infection.html"
    },
    "mutators": {
        "@default": true,
        "@function_signature": false
    },
    "bootstrap": "tests/bootstrap.php",
    "testFrameworkOptions": "--testsuite=unit"
}
```

Добавьте в .gitignore директорию infection.

7. Конфигурация Pest

Создать файл Pest.php. По умолчанию пустой файл.

---

## Структура тестов

Тесты группируются по модулям.
В корневой директории модуля необходимо создать директорию tests.
Далее директории и файлы именуются в CamelCase. Следующая директория выбирается исходя из типа теста:

- Unit &mdash; тесты отдельных классов \ функций. Должны гарантировать, что тестируемая единица соответствует заданной схеме поведения.
- Integration &mdash; тесты сценариев. Охватывают разом большую часть приложения в сравнении с unit-тестами. Могут использовать сервисы из контейнера, подключение к тестовой базе данных и т.д.
- Application &mdash; тесты приложения. Полноценно тестируют некоторый процесс. Могут работать со страницей сайта, с внешними сервисами.
- Architecture &mdash; архитектурное тестирование.

Дальнейшая структура директорий \ файлов должна повторять таковую у модуля относительно директории lib.
Соглашение об организации тестов для классов, расположенных вне директории lib, в настоящий момент не обсуждалось.

Namespace строится по следующей схеме: {module_name}\Tests\{type}\{path}. Например, Example\Main\Tests\Integration\Domain\Service.

Название класса: {class_name}Test. Например, ExampleServiceTest.

---

## Файл теста

### Unit: модульное тестирование

Класс теста наследует \Kosmos\BitrixTests\PHPUnit\BitrixTestCase.

Реализуйте тесты для всех публичных методов класса.
В качестве названия тестового метода используйте название оригинального метода с префиксом test, например getId → testGetId.

Для реализации проверок на предопределенном \ генерируемом множестве данных воспользуйтесь провайдером данных.

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

Пример демонстрирует исключительно идею провайдера.
Результатом запуска теста будет цикл по массиву данных провайдера с запуском тестового метода на каждой итерации.
Суммарно три проверки.

### Integration: интеграционное тестирование

Класс теста может наследовать \Kosmos\BitrixTests\PHPUnit\BitrixTestCase, но рекомендуется наследовать \Kosmos\BitrixTests\PHPUnit\Integration\TestCase.

Реализуйте тесты для всех публичных методов класса.

Если возникает необходимость протестировать поведение метода при разных состояниях приложения, разделите тестируемый метод на несколько, используя постфикс _{case}.
Например, testGetList_User, testGetList_Manager.

Для более эффективного тестирования убедитесь, что у вас разделены контроллеры, логика и представление.

Старайтесь не раскрывать реализацию.

---

## BitrixTestCase

### Кастомные утверждения

- assertResultSuccess  &mdash; в качестве аргумента принимает экземпляр \Bitrix\Main\Result.

### Установка идентификатора текущего пользователя

Метод `setUserId(?int $id = null): void` позволяет установить \ удалить идентификатор текущего пользователя.
Например, при сохранении элемента в качестве идентификатора пользователя может использоваться идентификатор текущего пользователя.

Только устанавливается идентификатор. Это значит, что не будут вызваны события процесса авторизации пользователя.

### Резервное копирование и восстановление глобальных переменных

По умолчанию перед запуском каждого теста восстанавливается SESSION до состояния до запуска теста.
Если есть необходимость расширить список глобальных переменных, необходимо реализовать метод `getBackupGlobalsKeys(): array`, возвращающий массив ключей глобальных переменных из GLOBALS.
Например, для SESSION ключом будет _SESSION.

### Вызов метода до\после теста\класса теста

Для выполнения некоторой логики до\после теста\класса теста нет необходимости переопределять `setUp()` и т.д.
Можно воспользоваться атрибутами `Before\BeforeClass` и `After\AfterClass`.
Название метода при этом может быть любым, но рекомендуется в качестве префикса использовать название стандартного метода с аналогичным порядком вызова.

```php
use PHPUnit\Framework\Attributes\Before;

class SameServiceTest extends TestCase
{
    protected SameServiceInterface $service;

    #[Before] protected function setUpService(): void
    {
        Loader::requireModule('example.main');
        $this->service = ServiceLocator::getInstance()->get(SameServiceInterface::class);
    }
}
```

### Mockery

[Документация](https://docs.mockery.io/en/latest/).

Одной из возможностей Mockery является возможность переопределения класса без необходимости непосредственной передачи к точке вызова (перезагрузка).

Например, в некотором сервисе есть метод получения детальной карточки сущности, в котором проверяются права доступа. Проверка при этом вызывается непосредственно в самом методе.

```php
use Bitrix\Main\Result;
use Bitrix\Main\Error;

class SameService
{
    public function getDetail($id): Result
    {
        $result = new Result();

        if (!Access::canView($id)) {
            return $result->addError(new Error('Access denied'));
        }

        return $result->setData(['entity' => $this->repository->get($id)]);
    }
}
```

Мы можем перезагрузить класс Access следующим образом.

```php
use Mockery;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses] class SameServiceTest extends TestCase
{
    public function testGetDetail(): void
    {
        $access = Mockery::mock('overload:\Access');
        $access->shouldReceive('canView')->once()->with(1)->andReturn(true);

        $id = 1;

        $result = $this->service->getDetail($id);
        $this->assertResultSuccess($result);
    }
}
```

> Обратите внимание на `#[RunTestsInSeparateProcesses]`.
> При использовании функционала перезагрузки тесты необходимо запускать раздельно.
> Не нужно добавлять, если тесты запускаются с помощью Pest.

---

## Integration\TestCase

### Тестовая база данных

Перед запуском теста проверяется наличие и полнота тестовой базы данных.

Если тестовая база данных не существует, она будет создана.

Если тестовая база данных существует, но количество таблиц и представлений (сумма) основной базы данных отличается от тестовой, тестовая будет пересоздана.

Если количество таблиц и представлений (сумма) совпадает, и запуск теста осуществлен пользователем в режиме, в котором он может ответить на вопрос в консоли, можно опционально пересоздать базу данных.

Если перед запуском тестов, работающих в "тихом" режиме, предполагает пересоздать тестовую базу данных, необходимо запустить тест в режиме, в котором есть возможность ответить на вопрос о пересоздании в консоли.

### Тестовые данные (ORM)

Поддерживаются: пользовательские сущности, пользователи, файлы.

Поддерживаемые форматы данных: JSON.

**JSON**

ключи:

- className &mdash; имя класса
- data &mdash; массив данных

_Пользовательская сущность_
```json
{
    "className": "Example\\Main\\Same",
    "data": [
        {
            "ID": 1,
            "ACTIVE": true
        },
        {
            "ID": 2,
            "ACTIVE": false
        }
    ]
}
```

_Файлы_
```json
{
    "className": "Bitrix\\Main\\FileTable",
    "data": [
        {
            "name": "sample.pdf",
            "type": "application/pdf",
            "description": "",
            "tmp_name": "1.pdf"
        }
    ]
}
```

При указании пути до файла (tmp_name) можно указать полный или короткий путь.
Краткая форма записи предполагает поиск файла в директории `local/tests/.data`.

_Пользователи_
```json
{
    "className": "Bitrix\\Main\\UserTable",
    "data": [
        {
            "LOGIN": "admin",
            "PASSWORD": "Admin123456!@#",
            "LID": "ru",
            "ACTIVE": true,
            "BLOCKED": false,
            "DATE_REGISTER": "2020-01-01 00:00:00",
            "EMAIL": "admin@local.local",
            "NAME": "John",
            "LAST_NAME": "Doe",
            "SECOND_NAME": ""
        }
    ]
}
```

Для указания списка файлов данных теста реализуйте метод `getOrmDataFilenameList()`.
Путь до файла может быть указан явно или коротко.
Краткая форма записи предполагает только название файла.
Поиск файла в таком случае будет осуществляться от директории с классом по пути .seed/{className}.

Например,
```
tests\Integration\.seed\SameServiceTest\user.json
tests\Integration\SameServiceTest.php
```

> Если в процессе работы с данными заполняются таблицы, которые впоследствии вам необходимо очистить, добавьте файл данных с пустым массивом данных.

## Мутационное тестирование

Мутационное тестирование позволяет определить условный уровень качества ваших модульных (Unit) тестов.

### Использование

Подробнее об установке и запуске можно прочитать в [документации](https://infection.github.io/guide/).
В примере выполняется запуск из директории local с использованием Xdebug.

Оценка директории модуля

```Bash
infection --configuration="tests/infection.json5" --filter=modules/example.module/lib/Domain/ --threads=max
```

Оценка класса

```Bash
infection --configuration="tests/infection.json5" --filter=modules/example.module/lib/Domain/Example/Example.php --threads=max
```

## Архитектурное тестирование

### Ожидания

#### notToUseBannedFunctions(array $exclude = [])

Проверка на использование нежелательных функций.
В параметре можно передать массив функций, которые использовать разрешено.

```php
arch()
    ->expect('Vendor\Example')
    ->notToUseBannedFunctions(['unserialize'])
;
```
