<?php

declare(strict_types=1);

if ($argc !== 4) {
    fwrite(
        STDERR,
        "Использование: php create-draft.php <site> <login> <password>" . PHP_EOL
    );

    exit(1);
}

[, $site, $login, $password] = $argv;

$endpoint = rtrim($site, '/') . '/wp-json/wp/v2/posts';

$payload = json_encode([
    'title' => 'Тестовый пост через REST API',
    'content' => 'Черновик создан PHP-скриптом.',
    'status' => 'draft',
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

$curl = curl_init($endpoint);

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $login . ':' . $password,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
    ],
]);

$responseBody = curl_exec($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

if ($responseBody === false) {
    fwrite(STDERR, 'Ошибка запроса: ' . curl_error($curl) . PHP_EOL);
    exit(1);
}

$response = json_decode($responseBody, true);

if ($httpCode < 200 || $httpCode >= 300) {
    fwrite(
        STDERR,
        "WordPress вернул HTTP {$httpCode}" . PHP_EOL
    );

    exit(1);
}

if (!isset($response['id'])) {
    fwrite(STDERR, 'В ответе WordPress отсутствует ID поста.' . PHP_EOL);
    exit(1);
}

echo "ID созданного поста: {$response['id']}" . PHP_EOL;