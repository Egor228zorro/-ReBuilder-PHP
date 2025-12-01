<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

// Health check
$app->get('/health', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode([
        'status' => 'ok',
        'service' => 'training-service',
        'database' => 'connected',
        'timestamp' => date('Y-m-d H:i:s')
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Получить все тренировки
$app->get('/workouts', function (Request $request, Response $response) {
    require_once __DIR__ . '/../src/Database/DatabaseConnection.php';
    
    $db = Rebuilder\Training\Database\DatabaseConnection::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM workouts ORDER BY created_at DESC");
    
    $response->getBody()->write(json_encode([
        'data' => $stmt->fetchAll()
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Получить конкретную тренировку с упражнениями
$app->get('/workouts/{id}', function (Request $request, Response $response, array $args) {
    $db = Rebuilder\Training\Database\DatabaseConnection::getInstance()->getConnection();
    
    // Получаем тренировку
    $stmt = $db->prepare("SELECT * FROM workouts WHERE id = ?");
    $stmt->execute([$args['id']]);
    $workout = $stmt->fetch();
    
    if (!$workout) {
        $response->getBody()->write(json_encode(['error' => 'Тренировка не найдена']));
        return $response->withStatus(404);
    }
    
    // Получаем упражнения для этой тренировки
    $stmt = $db->prepare("
        SELECT e.*, we.order_index, we.duration_seconds 
        FROM exercises e
        JOIN workout_exercises we ON e.id = we.exercise_id
        WHERE we.workout_id = ?
        ORDER BY we.order_index
    ");
    $stmt->execute([$args['id']]);
    $exercises = $stmt->fetchAll();
    
    $workout['exercises'] = $exercises;
    
    $response->getBody()->write(json_encode($workout));
    return $response->withHeader('Content-Type', 'application/json');
});

// Создать тренировку (POST)
$app->post('/workouts', function (Request $request, Response $response) {
    $data = json_decode($request->getBody()->getContents(), true);
    
    $db = Rebuilder\Training\Database\DatabaseConnection::getInstance()->getConnection();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO workouts (user_id, name, type) 
            VALUES (:user_id, :name, :type) 
            RETURNING id
        ");
        
        $stmt->execute([
            ':user_id' => $data['user_id'] ?? '550e8400-e29b-41d4-a716-446655440000',
            ':name' => $data['name'],
            ':type' => $data['type'] ?? 'strength'
        ]);
        
        $id = $stmt->fetch()['id'];
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'id' => $id,
            'message' => 'Тренировка создана'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        
    } catch (Exception $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withStatus(400);
    }
});

$app->run();