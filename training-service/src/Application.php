<?php

namespace Rebuilder\Training;

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Application
{
    private $app;

    public function __construct()
    {
        // Создаем Slim приложение
        $this->app = AppFactory::create();
        
        // Добавляем обработку ошибок
        $this->app->addErrorMiddleware(true, true, true);
        
        // КОРНЕВОЙ маршрут - ДОБАВЛЯЕМ ЭТОТ
        $this->app->get('/', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode([
                'message' => 'Training Service is running!',
                'endpoints' => [
                    '/health' => 'Health check',
                    '/workouts' => 'Get workouts list'
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });
        
        // Health check маршрут
        $this->app->get('/health', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode([
                'status' => 'OK', 
                'service' => 'training',
                'message' => 'Сервис работает!'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });
        
        // Маршрут для тренировок
        $this->app->get('/workouts', function (Request $request, Response $response) {
            $workouts = [
                ['id' => 1, 'title' => 'Первая тренировка', 'type' => 'strength'],
                ['id' => 2, 'title' => 'Кардио тренировка', 'type' => 'cardio']
            ];
            
            $response->getBody()->write(json_encode($workouts));
            return $response->withHeader('Content-Type', 'application/json');
        });
        
        // Обработка для favicon (чтобы не было ошибок)
        $this->app->get('/favicon.ico', function (Request $request, Response $response) {
            return $response->withStatus(404);
        });
    }

    public function run()
    {
        $this->app->run();
    }
}