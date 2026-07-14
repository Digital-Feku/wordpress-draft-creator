# WordPress Draft Creator

Небольшой CLI-скрипт на чистом PHP, который создаёт черновик поста через WordPress REST API.

Скрипт принимает адрес сайта, логин и пароль, отправляет запрос на создание записи со статусом `draft` и выводит ID созданного поста.

## Требования

- PHP 8.1 или новее
- расширение PHP cURL
- доступный WordPress REST API
- пользователь с правом создавать записи

Проверить PHP и cURL:

```bash
php -v
php -m
```

В списке расширений должен присутствовать `curl`.

## Запуск

```bash
php create-draft.php <адрес-сайта> <логин> <пароль>
```

Пример:

```bash
php create-draft.php https://example.com editor "password"
```

При успешном создании скрипт выведет:

```text
ID созданного поста: 123
```

При ошибке будет выведено короткое описание причины, например:

```text
Ошибка: HTTP 401. Неверный логин или пароль.
```

или:

```text
Ошибка: сайт недоступен: не удалось установить соединение. Код cURL: 7.
```

## Авторизация

Логин и пароль передаются через HTTP Basic Auth.

В стандартном WordPress для запросов к REST API обычно используется **Application Password**, который создаётся в профиле пользователя:

```text
Пользователи → Профиль → Пароли приложений
```

Пример запуска:

```bash
php create-draft.php https://example.com editor "abcd efgh ijkl mnop qrst uvwx"
```

Основной пароль от панели управления будет работать только в том случае, если сайт отдельно поддерживает Basic Auth.

Для реального сайта следует использовать HTTPS.

## Локальная проверка без WordPress

Для проверки добавлен небольшой mock-сервер, который имитирует ответы WordPress REST API.

Запустить mock:

```bash
php -S 127.0.0.1:8080 tests/mock-server.php
```

Сервер нужно оставить запущенным. В другом терминале выполнить следующие команды.

### Успешное создание черновика

```bash
php create-draft.php http://127.0.0.1:8080 editor "correct-password"
```

Ожидаемый результат:

```text
ID созданного поста: 123
```

### Неверный пароль

```bash
php create-draft.php http://127.0.0.1:8080 editor "wrong-password"
```

Ожидаемый результат:

```text
Ошибка: HTTP 401. Неверный логин или пароль.
```

### Недоступный сайт

```bash
php create-draft.php http://127.0.0.1:65534 editor "correct-password"
```

Скрипт сообщит, что соединение с сайтом установить не удалось.

### Таймаут

PowerShell:

```powershell
$env:WP_REQUEST_TIMEOUT = "2"

php .\create-draft.php `
  http://127.0.0.1:8080/timeout `
  editor `
  "correct-password"

Remove-Item Env:WP_REQUEST_TIMEOUT
```

Ожидаемый результат:

```text
Ошибка: таймаут при обращении к сайту.
```

## Проверка синтаксиса

```bash
php -l create-draft.php
php -l tests/mock-server.php
```

## Структура проекта

```text
.
├── create-draft.php
├── README.md
└── tests
    └── mock-server.php
```

- `create-draft.php` — основной CLI-скрипт.
- `tests/mock-server.php` — локальная имитация WordPress REST API.