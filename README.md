# WordPress Draft Creator

PHP-скрипт для создания черновика поста через WordPress REST API.

## Требования

- PHP 8.1+
- расширение cURL

## Запуск

```powershell
php .\create-draft.php https://example.com login "password"
```

В стандартном WordPress в качестве пароля обычно используется Application Password пользователя.

При успехе скрипт выведет ID созданного поста.

## Проверка без WordPress

Открыть первый терминал и запустить тестовый сервер:

```powershell
php -S 127.0.0.1:8080 .\tests\mock-server.php
```

Открыть второй терминал и выполнить:

```powershell
php .\create-draft.php http://127.0.0.1:8080 editor "correct-password"
```

Ожидаемый результат:

```text
ID созданного поста: 123
```

Проверка неправильного пароля:

```powershell
php .\create-draft.php http://127.0.0.1:8080 editor "wrong-password"
```