<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rebuilder\Training\Application;

// Простой запуск приложения
$app = new Application();
$app->run();