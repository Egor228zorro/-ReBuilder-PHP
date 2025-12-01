<?php

namespace Rebuilder\ApiGateway;

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use GuzzleHttp\Client;

class Application
{
    private $app;
    private $httpClient;

    public function __construct()
    {
        $this->app = AppFactory::create();
        $this->httpClient = new Client();
        
        $this->setupRoutes();
    }

    private function setupRoutes(): void
    {
        // Health check
        $this->app->get('/health', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode([
                'status' => 'OK',
                'service' => 'api-gateway',
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Прокси к Training Service
        $this->app->any('/workouts[/{params:.*}]', function (Request $request, Response $response) {
            return $this->proxyToService($request, $response, 'http://training-service:80');
        });

        // Прокси к TTS Service
        $this->app->any('/tts[/{params:.*}]', function (Request $request, Response $response) {
            return $this->proxyToService($request, $response, 'http://text-to-speech-service:80');
        });

        // Корневой маршрут
        $this->app->get('/', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode([
                'message' => 'ReBuilder API Gateway',
                'endpoints' => [
                    '/health' => 'Health check',
                    '/workouts' => 'Training Service',
                    '/tts' => 'Text-to-Speech Service'
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });
    }

    private function proxyToService(Request $request, Response $response, string $serviceUrl)
    {
        try {
            // === ОТЛАДКА ===
            error_log("=== API GATEWAY DEBUG ===");
            error_log("Method: " . $request->getMethod());
            error_log("Path: " . $request->getUri()->getPath());
            error_log("Content-Type: " . ($request->getHeaderLine('Content-Type') ?? 'none'));
            
            $bodyContent = $request->getBody()->getContents();
            error_log("Body size: " . strlen($bodyContent));
            error_log("Body content: " . $bodyContent);
            // === КОНЕЦ ОТЛАДКИ ===

            $path = $request->getUri()->getPath();
            $method = $request->getMethod();
            $query = $request->getUri()->getQuery();
            
            // Убираем префиксы для TTS сервиса
            if (str_starts_with($path, '/tts')) {
                $path = str_replace('/tts', '', $path);
                $path = $path ?: '/';
            }
            
            // Формируем URL для целевого сервиса
            $targetUrl = $serviceUrl . $path;
            if ($query) {
                $targetUrl .= '?' . $query;
            }
            
            // Подготавливаем опции для Guzzle
            $options = [
                'headers' => $request->getHeaders(),
                'timeout' => 30
            ];
            
            // Добавляем тело запроса для POST/PUT методов
            if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($bodyContent)) {
                $options['body'] = $bodyContent;
                $options['headers']['Content-Type'] = 'application/json';
            }
            
            // Проксируем запрос
            $serviceResponse = $this->httpClient->request($method, $targetUrl, $options);
            
            // Возвращаем ответ от целевого сервиса
            $response->getBody()->write($serviceResponse->getBody()->getContents());
            return $response
                ->withStatus($serviceResponse->getStatusCode())
                ->withHeader('Content-Type', 'application/json');
                
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Обрабатываем ошибки запроса
            if ($e->hasResponse()) {
                $errorResponse = $e->getResponse();
                $response->getBody()->write($errorResponse->getBody()->getContents());
                return $response
                    ->withStatus($errorResponse->getStatusCode())
                    ->withHeader('Content-Type', 'application/json');
            }
            
            $response->getBody()->write(json_encode([
                'error' => 'Service unavailable',
                'message' => $e->getMessage(),
                'service' => $serviceUrl
            ]));
            return $response->withStatus(503);
        }
    }

    public function run()
    {
        $this->app->run();
    }
}