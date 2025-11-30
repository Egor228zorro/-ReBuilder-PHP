<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Rebuilder\TextToSpeech\Application;

$app = new Application();
$app->run();