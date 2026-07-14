<?php

declare(strict_types=1);

const DEFAULT_CONNECT_TIMEOUT = 5;
const DEFAULT_REQUEST_TIMEOUT = 15;

/**
 * Выводит понятную ошибку в STDERR и завершает скрипт с кодом 1.
 */
function fail(string $message): never
{
    fwrite(STDERR, "Ошибка: {$message}" . PHP_EOL);
    exit(1);
}

/**
 * Позволяет переопределить таймаут через переменную окружения.
 * Используется в том числе для локальной проверки timeout-сценария.
 */
function getTimeout(string $environmentVariable, int $default): int
{
    $value = getenv($environmentVariable);

    if ($value === false || !ctype_digit($value) || (int) $value <= 0) {
        return $default;
    }

    return (int) $value;
}

/**
 * Извлекает стандартные поля code и message из ошибки WordPress REST API.
 */
function getWordPressError(string $responseBody): ?string
{
    try {
        $data = json_decode(
            $responseBody,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException) {
        return null;
    }

    if (!is_array($data)) {
        return null;
    }

    $message = $data['message'] ?? null;
    $code = $data['code'] ?? null;

    if (!is_string($message) || $message === '') {
        return null;
    }

    return is_string($code) && $code !== ''
        ? "{$message} (WordPress: {$code})"
        : $message;
}

if (PHP_SAPI !== 'cli') {
    fail('скрипт нужно запускать из командной строки.');
}

if ($argc !== 4) {
    fail(
        'использование: php create-draft.php <site> <login> <password>.'
    );
}

[, $site, $login, $password] = $argv;

$site = rtrim(trim($site), '/');
$login = trim($login);

if (filter_var($site, FILTER_VALIDATE_URL) === false) {
    fail('указан некорректный адрес сайта.');
}

$scheme = strtolower((string) parse_url($site, PHP_URL_SCHEME));

if (!in_array($scheme, ['http', 'https'], true)) {
    fail('адрес сайта должен начинаться с http:// или https://.');
}

if ($login === '') {
    fail('логин не должен быть пустым.');
}

if ($password === '') {
    fail('пароль не должен быть пустым.');
}

if (!function_exists('curl_init')) {
    fail('расширение PHP cURL не установлено.');
}

$endpoint = $site . '/wp-json/wp/v2/posts';

try {
    $payload = json_encode([
        'title' => 'Тестовый пост через WordPress REST API',
        'content' => 'Черновик создан PHP-скриптом через REST API.',
        'status' => 'draft',
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (JsonException $exception) {
    fail('не удалось сформировать JSON: ' . $exception->getMessage());
}

$curl = curl_init();

if ($curl === false) {
    fail('не удалось инициализировать cURL.');
}

curl_setopt_array($curl, [
    CURLOPT_URL => $endpoint,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_CONNECTTIMEOUT => getTimeout(
        'WP_CONNECT_TIMEOUT',
        DEFAULT_CONNECT_TIMEOUT
    ),
    CURLOPT_TIMEOUT => getTimeout(
        'WP_REQUEST_TIMEOUT',
        DEFAULT_REQUEST_TIMEOUT
    ),

    // По условию задания передаём логин и пароль через HTTP Basic Auth.
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $login . ':' . $password,

    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json; charset=utf-8',
    ],
    CURLOPT_USERAGENT => 'WordPressDraftCreator/1.0',
]);

$responseBody = curl_exec($curl);
$curlErrorNumber = curl_errno($curl);
$curlError = curl_error($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

/*
 * Не вызываем curl_close(): в современных версиях PHP CurlHandle
 * освобождается автоматически после выхода из области видимости.
 */

/*
 * Сетевые ошибки отделены от HTTP-ошибок.
 * При таймауте или недоступном сайте HTTP-ответ может отсутствовать.
 */
if ($responseBody === false) {
    $message = match ($curlErrorNumber) {
        CURLE_OPERATION_TIMEDOUT =>
            'таймаут при обращении к сайту.',

        CURLE_COULDNT_RESOLVE_HOST =>
            'сайт недоступен: домен не найден.',

        CURLE_COULDNT_CONNECT =>
            'сайт недоступен: не удалось установить соединение.',

        CURLE_SSL_CONNECT_ERROR =>
            'не удалось установить безопасное SSL-соединение.',

        default =>
            'ошибка cURL'
            . ($curlError !== '' ? ": {$curlError}" : '.'),
    };

    fail("{$message} Код cURL: {$curlErrorNumber}.");
}

if ($httpCode < 200 || $httpCode >= 300) {
    $details = getWordPressError($responseBody)
        ?? 'сервер не вернул описание ошибки.';

    $message = match ($httpCode) {
        400 =>
            "HTTP 400. WordPress отклонил запрос: {$details}",

        401 =>
            "HTTP 401. Неверный логин или пароль: {$details}",

        403 =>
            "HTTP 403. Пользователь не имеет права создавать посты: {$details}",

        404 =>
            "HTTP 404. REST API WordPress не найден: {$details}",

        429 =>
            "HTTP 429. Слишком много запросов: {$details}",

        default =>
            "HTTP {$httpCode}. WordPress вернул ошибку: {$details}",
    };

    fail($message);
}

try {
    $response = json_decode(
        $responseBody,
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException $exception) {
    fail(
        "HTTP {$httpCode}. WordPress вернул некорректный JSON: "
        . $exception->getMessage()
    );
}

$postId = $response['id'] ?? null;

if (!is_int($postId) && !(is_string($postId) && ctype_digit($postId))) {
    fail("HTTP {$httpCode}. В ответе отсутствует корректный ID поста.");
}

echo "ID созданного поста: {$postId}" . PHP_EOL;