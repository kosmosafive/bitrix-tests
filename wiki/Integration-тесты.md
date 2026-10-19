# Integration: интеграционное тестирование

Класс теста может наследовать `\Kosmosafive\Bitrix\Tests\PHPUnit\BitrixTestCase`,  
но рекомендуется `\Kosmosafive\Bitrix\Tests\PHPUnit\Integration\TestCase`.

Для Pest см. [`Pest\BitrixTestCase`](Pest) и [`TestDatabase`](Тестовая-база-данных).

## Соглашения

- Реализуйте тесты для всех публичных методов.
- Разные состояния приложения — отдельные методы с постфиксом `_{case}`:  
  `testGetList_User`, `testGetList_Manager`.
- Разделяйте контроллеры, логику и представление.
- Старайтесь не раскрывать реализацию.

## Тестовые данные (ORM)

Поддерживаются: пользовательские сущности, пользователи, файлы.  
Формат: JSON.

### Ключи JSON

- `className` — имя класса
- `data` — массив данных

### Пользовательская сущность

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

### Файлы

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

`tmp_name` — полный путь или короткий. Короткая форма ищет файл в `local/tests/.data`.

### Пользователи

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

## Подключение сидов

Реализуйте `getOrmDataFilenameList()`.

- Явный путь или короткое имя файла.
- Короткая форма: поиск от директории класса по пути `.seed/{className}`.

Пример:

```
tests\Integration\.seed\SameServiceTest\user.json
tests\Integration\SameServiceTest.php
```

> Если нужно очистить таблицы, заполненные побочно, добавьте файл данных с пустым массивом `data`.

См. также: [Тестовая БД](Тестовая-база-данных).
