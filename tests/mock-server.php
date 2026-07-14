<?php

declare(strict_types=1);

/*
 * Локальная имитация WordPress REST API.
 * Нужна для проверки клиентского скрипта без настоящего WordPress.
 */

header('Content-Type: application/json; charset=utf-8');

$path = (string) parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

/*
 * Специальный медленный endpoint для проверки клиентского таймаута.
 */
if ($path === '/timeout/wp-json/wp/v2/posts') {
    sleep(5);

    http_response_code(201);

    echo json_encode([
        'id' => 999,
        'status' => 'draft',
    ]);

    exit;
}

if ($path !== '/wp-json/wp/v2/posts') {
    http_response_code(404);

    echo json_encode([
        'code' => 'rest_no_route',
        'message' => 'Маршрут не найден.',
        'data' => ['status' => 404],
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'code' => 'rest_invalid_method',
        'message' => 'Разрешён только метод POST.',
        'data' => ['status' => 405],
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
 * Встроенный PHP-сервер обычно заполняет PHP_AUTH_USER/PHP_AUTH_PW.
 * Fallback через Authorization оставлен для совместимости.
 */
$username = $_SERVER['PHP_AUTH_USER'] ?? null;
$password = $_SERVER['PHP_AUTH_PW'] ?? null;

if ($username === null) {
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (str_starts_with($authorization, 'Basic ')) {
        $decoded = base64_decode(
            substr($authorization, 6),
            true
        );

        if (is_string($decoded) && str_contains($decoded, ':')) {
            [$username, $password] = explode(':', $decoded, 2);
        }
    }
}

if ($username !== 'editor' || $password !== 'correct-password') {
    http_response_code(401);

    echo json_encode([
        'code' => 'rest_cannot_create',
        'message' => 'Неверный логин или пароль.',
        'data' => ['status' => 401],
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $payload = json_decode(
        file_get_contents('php://input') ?: '',
        true,
        512,
        JSON_THROW_ON_ERROR
    );
} catch (JsonException) {
    http_response_code(400);

    echo json_encode([
        'code' => 'rest_invalid_json',
        'message' => 'Передан некорректный JSON.',
        'data' => ['status' => 400],
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (($payload['status'] ?? null) !== 'draft') {
    http_response_code(400);

    echo json_encode([
        'code' => 'rest_invalid_param',
        'message' => 'Ожидался статус draft.',
        'data' => ['status' => 400],
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

http_response_code(201);

echo json_encode([
    'id' => 123,
    'status' => 'draft',
], JSON_UNESCAPED_UNICODE);