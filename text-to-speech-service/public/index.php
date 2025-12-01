<?php
declare(strict_types=1);

// Включаем вывод ошибок для разработки
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Загружаем автозагрузчик
require_once __DIR__ . '/../vendor/autoload.php';

// Создаем приложение
$app = new \Rebuilder\Training\Application();

// Добавляем маршруты если их нет в Application.php
$app->getApp()->get('/health', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'status' => 'ok',
        'service' => 'training-service',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0.0'
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Запускаем приложение
$app->run();