# kosmosafive/bitrix-tests

Набор инструментов для тестирования [Bitrix Framework](https://dev.1c-bitrix.ru/).

Поддерживаются:

- [Pest](https://pestphp.com/)
- [PHPUnit](https://phpunit.de/)
- [Infection](https://infection.github.io/)

## Быстрый старт

1. [Установка](Установка)
2. [Настройка](Настройка)
3. [Структура тестов](Структура-тестов)

## Возможности

| Тема | Описание |
|------|----------|
| [Pest](Pest) | HTTP-диспетчер действий Bitrix, `apiRequest`, изоляция БД |
| [BitrixTestCase](BitrixTestCase) | Базовый класс: пользователь, глобалы, Mockery |
| [Тестовая БД](Тестовая-база-данных) | Клон схемы MySQL в `{database}_test` |
| [Manticore](Manticore) | Изоляция индексов суффиксом `_test` |
| [Integration / ORM](Integration-тесты) | Сиды JSON: сущности, пользователи, файлы |
| [Мутационное тестирование](Мутационное-тестирование) | Infection |
| [Архитектурное тестирование](Архитектурное-тестирование) | `notToUseBannedFunctions` |

## Версия

Актуальная версия пакета — **2.2.0**.

- [Changelog](../CHANGELOG.md)
- [Миграция с 1.x на 2.0](Миграция-2.0)