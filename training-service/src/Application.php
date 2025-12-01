<?php

declare(strict_types=1);

namespace Rebuilder\Training;

use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Rebuilder\Training\Common\EnsiErrorHandler;
use Rebuilder\Training\Database\DatabaseConnection;
use Rebuilder\Training\Service\WorkoutService;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;
use Throwable;

class Application
{
    /** @var App<ContainerInterface|null> */
    private App $app;
    private PDO $db;
    private WorkoutService $workoutService;

    public function __construct()
    {
        // Создаем Slim приложение
        $this->app = AppFactory::create();
        $this->app->addBodyParsingMiddleware();
        $this->db = DatabaseConnection::getInstance()->getConnection();
        $this->workoutService = new WorkoutService();

        // ✅ ДОБАВЛЕНО: Настраиваем ErrorMiddleware
        $errorMiddleware = $this->app->addErrorMiddleware(
            false,  // displayErrorDetails
            true,   // logErrors
            true    // logErrorDetails
        );
        
        // ✅ ДОБАВЛЕНО: Отключаем преобразование Slim в RFC 7807
        $errorMiddleware->setDefaultErrorHandler(function (
            Request $request,
            Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) {
            $error = EnsiErrorHandler::serverError(
                $exception->getMessage(),
                $request->getUri()->getPath(),
                [
                    'exception' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $displayErrorDetails ? $exception->getTrace() : []
                ]
            );
            
            $response = \Slim\Factory\AppFactory::determineResponseFactory()->createResponse();
            $json = json_encode($error, JSON_THROW_ON_ERROR);
            $response->getBody()->write($json);
            
            // ✅ ИСПРАВЛЕНО: Удален лишний "return $response" и исправлено получение статуса
            /** @var mixed $statusCode */
            $statusCode = $error['status'] ?? 500;
            $status = 500;
            
            if (is_int($statusCode)) {
                $status = $statusCode;
            } elseif (is_string($statusCode) && ctype_digit($statusCode)) {
                $status = (int)$statusCode;
            }
            
            return $response
                ->withStatus($status)
                ->withHeader('Content-Type', 'application/json');
        });

        $this->setupRoutes();
    }

    private function setupRoutes(): void
    {
        // КОРНЕВОЙ маршрут
        $this->app->get('/', function (Request $request, Response $response) {
            $jsonData = [
                'message' => 'Training Service is running!',
                'endpoints' => [
                    '/health' => 'Health check',
                    '/db-test' => 'Test database connection',
                    '/workouts' => 'Get workouts list',
                    '/workouts/{id}' => 'Get workout details',
                    'POST /workouts' => 'Create workout',
                    '/private/exercises' => 'Get user exercises',
                    'POST /private/exercises' => 'Create exercise',
                    'GET /private/exercises/{id}' => 'Get exercise details',
                    'PATCH /private/exercises/{id}' => 'Update exercise',
                    'DELETE /private/exercises/{id}' => 'Delete exercise',
                    'GET /private/workouts/{id}/exercises' => 'Get workout exercises',
                    'POST /private/workouts/{id}/exercises' => 'Add exercise to workout',
                    'DELETE /private/workouts/{workout_id}/exercises/{we_id}' => 'Remove exercise from workout',
                    'POST /workouts/{id}/tts' => 'Generate TTS for workout'
                ]
            ];

            $json = json_encode($jsonData, JSON_THROW_ON_ERROR);
            $response->getBody()->write($json);
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Health check маршрут
        $this->app->get('/health', function (Request $request, Response $response) {
            $jsonData = [
                'status' => 'OK',
                'service' => 'training',
                'message' => 'Сервис работает!'
            ];

            $json = json_encode($jsonData, JSON_THROW_ON_ERROR);
            $response->getBody()->write($json);
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Тест подключения к БД
        $this->app->get('/db-test', function (Request $request, Response $response) {
            try {
                $stmt = $this->db->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'public'");
                if ($stmt === false) {
                    throw new \RuntimeException('Database query failed');
                }

                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result === false) {
                    throw new \RuntimeException('Failed to fetch result');
                }

                /** @var array{table_count: int|string} $result */
                $jsonData = [
                    'status' => 'success',
                    'tables_count' => (int)$result['table_count'],
                    'message' => 'Database connection successful'
                ];

                $json = json_encode($jsonData, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withHeader('Content-Type', 'application/json');

            } catch (\Exception $e) {
                $error = EnsiErrorHandler::databaseError($e->getMessage(), '/db-test');
                $json = json_encode($error, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        // GET /workouts - список тренировок
        $this->app->get('/workouts', function (Request $request, Response $response) {
            try {
                // ✅ ИСПРАВЛЕНО: Используем WorkoutService
                $workouts = $this->workoutService->getWorkouts();
                $json = json_encode($workouts, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withHeader('Content-Type', 'application/json');
            } catch (\Exception $e) {
                $error = EnsiErrorHandler::databaseError($e->getMessage(), '/workouts');
                $json = json_encode($error, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        // GET /workouts/{id} - детали тренировки
        $this->app->get('/workouts/{id}', function (Request $request, Response $response, array $args) {
            try {
                $id = $args['id'] ?? '';
                if (!is_string($id) || empty($id)) {
                    $error = EnsiErrorHandler::validationError(
                        'Workout ID is required and must be a string', 
                        '/workouts/{id}',
                        ['provided_id' => $id, 'type' => gettype($id)]
                    );
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                // ✅ ИСПРАВЛЕНО: Используем WorkoutService
                $workout = $this->workoutService->getWorkoutById($id);
                
                if (!$workout) {
                    $error = EnsiErrorHandler::notFound(
                        "Workout with ID '{$id}' not found",
                        "/workouts/{$id}",
                        ['requested_id' => $id]
                    );
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
                }

                $json = json_encode($workout, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withHeader('Content-Type', 'application/json');

            } catch (\Exception $e) {
                $error = EnsiErrorHandler::serverError($e->getMessage(), '/workouts/{id}');
                $json = json_encode($error, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        // ДОБАВИМ: Отдельный маршрут для валидационной ошибки (/workouts/)
        $this->app->get('/workouts/', function (Request $request, Response $response) {
            $error = EnsiErrorHandler::validationError(
                'Workout ID is required and must be a string', 
                '/workouts/{id}',
                ['hint' => 'Provide a workout ID in the URL, e.g., /workouts/123']
            );
            $json = json_encode($error, JSON_THROW_ON_ERROR);
            $response->getBody()->write($json);
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        });

        // POST /workouts - создание тренировки
        $this->app->post('/workouts', function (Request $request, Response $response) {
            try {
                /** @var array{name?: mixed, type?: mixed} $data */
                $data = $request->getParsedBody() ?? [];

                // Валидация по ENSI
                $name = $data['name'] ?? '';
                $type = $data['type'] ?? 'strength';

                if (!is_string($name)) {
                    $error = EnsiErrorHandler::validationError('Name must be a string', '/workouts', [
                        'provided_name' => $name,
                        'type' => gettype($name)
                    ]);
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                if (!is_string($type)) {
                    $error = EnsiErrorHandler::validationError('Type must be a string', '/workouts', [
                        'provided_type' => $type,
                        'type' => gettype($type)
                    ]);
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                // ✅ ИСПРАВЛЕНО: Используем WorkoutService
                $workout = $this->workoutService->createWorkout([
                    'name' => $name,
                    'type' => $type
                ]);

                $json = json_encode($workout, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

            } catch (\InvalidArgumentException $e) {
                $error = EnsiErrorHandler::validationError($e->getMessage(), '/workouts');
                $json = json_encode($error, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            } catch (\Exception $e) {
                $error = EnsiErrorHandler::databaseError($e->getMessage(), '/workouts');
                $json = json_encode($error, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        // ===== ПОЛНЫЙ CRUD ДЛЯ УПРАЖНЕНИЙ =====

        // GET /private/exercises - список упражнений пользователя
        $this->app->get('/private/exercises', function (Request $request, Response $response) {
            try {
                $userId = '550e8400-e29b-41d4-a716-446655440000';

                $stmt = $this->db->prepare("
                    SELECT * FROM exercises 
                    WHERE user_id = :user_id 
                    ORDER BY created_at DESC
                ");
                if ($stmt === false) {
                    throw new \RuntimeException('Failed to prepare statement');
                }

                $result = $stmt->execute(['user_id' => $userId]);
                if ($result === false) {
                    throw new \RuntimeException('Failed to execute query');
                }

                $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $json = json_encode($exercises, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withHeader('Content-Type', 'application/json');

            } catch (\Exception $e) {
                $error = EnsiErrorHandler::databaseError($e->getMessage(), '/private/exercises');
                $json = json_encode($error, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        // POST /private/exercises - создание упражнения
        $this->app->post('/private/exercises', function (Request $request, Response $response) {
            try {
                /** @var array{name?: mixed, description?: mixed, media_url?: mixed} $data */
                $data = $request->getParsedBody() ?? [];
                $userId = '550e8400-e29b-41d4-a716-446655440000';

                // Валидация
                $name = $data['name'] ?? '';
                $description = $data['description'] ?? '';
                $media_url = $data['media_url'] ?? '';

                if (!is_string($name)) {
                    $error = EnsiErrorHandler::validationError('Name must be a string', '/private/exercises', [
                        'provided_name' => $name,
                        'type' => gettype($name)
                    ]);
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                if (!is_string($description)) {
                    $error = EnsiErrorHandler::validationError('Description must be a string', '/private/exercises', [
                        'provided_description' => $description,
                        'type' => gettype($description)
                    ]);
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                if (!is_string($media_url)) {
                    $error = EnsiErrorHandler::validationError('Media URL must be a string', '/private/exercises', [
                        'provided_media_url' => $media_url,
                        'type' => gettype($media_url)
                    ]);
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                $name = !empty($name) ? $name : 'Новое упражнение';

                $stmt = $this->db->prepare("
                    INSERT INTO exercises (user_id, name, description, media_url) 
                    VALUES (:user_id, :name, :description, :media_url) 
                    RETURNING *
                ");
                if ($stmt === false) {
                    throw new \RuntimeException('Failed to prepare statement');
                }

                $result = $stmt->execute([
                    'user_id' => $userId,
                    'name' => $name,
                    'description' => $description,
                    'media_url' => $media_url
                ]);
                if ($result === false) {
                    throw new \RuntimeException('Failed to execute query');
                }

                $exercise = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($exercise === false) {
                    throw new \RuntimeException('Failed to fetch created exercise');
                }

                $json = json_encode($exercise, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

            } catch (\Exception $e) {
                $error = EnsiErrorHandler::databaseError($e->getMessage(), '/private/exercises');
                $json = json_encode($error, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        // GET /private/exercises/{id} - детали упражнения
        $this->app->get('/private/exercises/{id}', function (Request $request, Response $response, array $args) {
            try {
                $exerciseId = $args['id'] ?? '';
                if (!is_string($exerciseId) || empty($exerciseId)) {
                    $error = EnsiErrorHandler::validationError(
                        'Exercise ID is required and must be a string', 
                        '/private/exercises/{id}',
                        ['provided_id' => $exerciseId, 'type' => gettype($exerciseId)]
                    );
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                $userId = '550e8400-e29b-41d4-a716-446655440000';

                $stmt = $this->db->prepare("
                    SELECT * FROM exercises 
                    WHERE id = :id AND user_id = :user_id
                ");
                if ($stmt === false) {
                    throw new \RuntimeException('Failed to prepare statement');
                }

                $result = $stmt->execute(['id' => $exerciseId, 'user_id' => $userId]);
                if ($result === false) {
                    throw new \RuntimeException('Failed to execute query');
                }

                $exercise = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$exercise) {
                    $error = EnsiErrorHandler::notFound(
                        "Exercise with ID '{$exerciseId}' not found",
                        "/private/exercises/{$exerciseId}",
                        ['requested_id' => $exerciseId]
                    );
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
                }

                $json = json_encode($exercise, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withHeader('Content-Type', 'application/json');

            } catch (\Exception $e) {
                $error = EnsiErrorHandler::serverError($e->getMessage(), '/private/exercises/{id}');
                $json = json_encode($error, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        // ДОБАВИМ: Отдельный маршрут для валидационной ошибки (/private/exercises/)
        $this->app->get('/private/exercises/', function (Request $request, Response $response) {
            $error = EnsiErrorHandler::validationError(
                'Exercise ID is required and must be a string', 
                '/private/exercises/{id}',
                ['hint' => 'Provide an exercise ID in the URL, e.g., /private/exercises/123']
            );
            $json = json_encode($error, JSON_THROW_ON_ERROR);
            $response->getBody()->write($json);
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        });

        // PATCH /private/exercises/{id} - обновление упражнения
        $this->app->patch('/private/exercises/{id}', function (Request $request, Response $response, array $args) {
            try {
                $exerciseId = $args['id'] ?? '';
                if (!is_string($exerciseId) || empty($exerciseId)) {
                    $error = EnsiErrorHandler::validationError(
                        'Exercise ID is required and must be a string', 
                        '/private/exercises/{id}',
                        ['provided_id' => $exerciseId, 'type' => gettype($exerciseId)]
                    );
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                /** @var array{name?: mixed, description?: mixed, media_url?: mixed} $data */
                $data = $request->getParsedBody() ?? [];
                $userId = '550e8400-e29b-41d4-a716-446655440000';

                // Проверяем что упражнение принадлежит пользователю
                $checkStmt = $this->db->prepare("SELECT id FROM exercises WHERE id = :id AND user_id = :user_id");
                if ($checkStmt === false) {
                    throw new \RuntimeException('Failed to prepare check statement');
                }

                $result = $checkStmt->execute(['id' => $exerciseId, 'user_id' => $userId]);
                if ($result === false) {
                    throw new \RuntimeException('Failed to execute check query');
                }

                if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $error = EnsiErrorHandler::notFound(
                        "Exercise with ID '{$exerciseId}' not found",
                        "/private/exercises/{$exerciseId}",
                        ['requested_id' => $exerciseId]
                    );
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
                }

                // Обновляем только переданные поля
                $updateFields = [];
                $updateValues = ['id' => $exerciseId];

                if (isset($data['name']) && is_string($data['name'])) {
                    $updateFields[] = 'name = :name';
                    $updateValues['name'] = $data['name'];
                }
                if (isset($data['description']) && is_string($data['description'])) {
                    $updateFields[] = 'description = :description';
                    $updateValues['description'] = $data['description'];
                }
                if (isset($data['media_url']) && is_string($data['media_url'])) {
                    $updateFields[] = 'media_url = :media_url';
                    $updateValues['media_url'] = $data['media_url'];
                }

                if (empty($updateFields)) {
                    $error = EnsiErrorHandler::validationError(
                        'No fields to update', 
                        "/private/exercises/{$exerciseId}",
                        ['hint' => 'Provide at least one field to update: name, description, or media_url']
                    );
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';

                $stmt = $this->db->prepare("
                    UPDATE exercises 
                    SET " . implode(', ', $updateFields) . "
                    WHERE id = :id
                    RETURNING *
                ");
                if ($stmt === false) {
                    throw new \RuntimeException('Failed to prepare update statement');
                }

                $result = $stmt->execute($updateValues);
                if ($result === false) {
                    throw new \RuntimeException('Failed to execute update query');
                }

                $exercise = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($exercise === false) {
                    throw new \RuntimeException('Failed to fetch updated exercise');
                }

                $json = json_encode($exercise, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withHeader('Content-Type', 'application/json');

            } catch (\Exception $e) {
                $error = EnsiErrorHandler::serverError($e->getMessage(), '/private/exercises/{id}');
                $json = json_encode($error, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        // DELETE /private/exercises/{id} - удаление упражнения
        $this->app->delete('/private/exercises/{id}', function (Request $request, Response $response, array $args) {
            try {
                $exerciseId = $args['id'] ?? '';
                if (!is_string($exerciseId) || empty($exerciseId)) {
                    $error = EnsiErrorHandler::validationError(
                        'Exercise ID is required and must be a string', 
                        '/private/exercises/{id}',
                        ['provided_id' => $exerciseId, 'type' => gettype($exerciseId)]
                    );
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }

                $userId = '550e8400-e29b-41d4-a716-446655440000';

                // Проверяем что упражнение принадлежит пользователю
                $checkStmt = $this->db->prepare("SELECT id FROM exercises WHERE id = :id AND user_id = :user_id");
                if ($checkStmt === false) {
                    throw new \RuntimeException('Failed to prepare check statement');
                }

                $result = $checkStmt->execute(['id' => $exerciseId, 'user_id' => $userId]);
                if ($result === false) {
                    throw new \RuntimeException('Failed to execute check query');
                }

                if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $error = EnsiErrorHandler::notFound(
                        "Exercise with ID '{$exerciseId}' not found",
                        "/private/exercises/{$exerciseId}",
                        ['requested_id' => $exerciseId]
                    );
                    $json = json_encode($error, JSON_THROW_ON_ERROR);
                    $response->getBody()->write($json);
                    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
                }

                $stmt = $this->db->prepare("DELETE FROM exercises WHERE id = :id");
                if ($stmt === false) {
                    throw new \RuntimeException('Failed to prepare delete statement');
                }

                $result = $stmt->execute(['id' => $exerciseId]);
                if ($result === false) {
                    throw new \RuntimeException('Failed to execute delete query');
                }

                $jsonData = [
                    'message' => 'Exercise deleted successfully',
                    'exercise_id' => $exerciseId
                ];

                $json = json_encode($jsonData, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withHeader('Content-Type', 'application/json');

            } catch (\Exception $e) {
                $error = EnsiErrorHandler::databaseError($e->getMessage(), '/private/exercises/{id}');
                $json = json_encode($error, JSON_THROW_ON_ERROR);
                $response->getBody()->write($json);
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }
        });

        $this->app->get('/favicon.ico', function (Request $request, Response $response) {
            return $response->withStatus(404);
        });
    }

    public function run(): void
    {
        $this->app->run();
    }
}
