<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Application.php';

use Rebuilder\Training\Application;

$app = new Application();

// Простой маршрут для проверки
$app->get('/api/health', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'status' => 'ok',
        'service' => 'training-service',
        'timestamp' => date('Y-m-d H:i:s')
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Проверка базы данных
$app->get('/api/db-check', function ($request, $response) {
    require_once __DIR__ . '/../src/Database/DatabaseConnection.php';
    
    try {
        $db = \Rebuilder\Training\Database\DatabaseConnection::getInstance()->getConnection();
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM workouts");
        $workoutsCount = $stmt->fetch()['count'];
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM exercises");
        $exercisesCount = $stmt->fetch()['count'];
        
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'database' => 'connected',
            'workouts_count' => $workoutsCount,
            'exercises_count' => $exercisesCount,
            'tables' => ['workouts', 'exercises', 'workout_exercises', 'user_workout_settings', 'tts_jobs']
        ]));
        
    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]));
        return $response->withStatus(500);
    }
    
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();