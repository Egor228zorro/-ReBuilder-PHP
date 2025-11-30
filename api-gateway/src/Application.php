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
        
        $this->setupGatewayRoutes();
    }

    private function setupGatewayRoutes(): void
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
            $path = $request->getUri()->getPath();
            $method = $request->getMethod();
            $query = $request->getUri()->getQuery();
            
            // Формируем URL для целевого сервиса
            $targetUrl = $serviceUrl . $path;
            if ($query) {
                $targetUrl .= '?' . $query;
            }
            
            // Проксируем запрос
            $serviceResponse = $this->httpClient->request($method, $targetUrl, [
                'headers' => $request->getHeaders(),
                'body' => $request->getBody(),
                'timeout' => 30
            ]);
            
            // Получаем содержимое ответа как строку
            $responseBody = $serviceResponse->getBody()->getContents();
            
            // Возвращаем ответ от целевого сервиса
            $response->getBody()->write($responseBody);
            return $response
                ->withStatus($serviceResponse->getStatusCode())
                ->withHeader('Content-Type', 'application/json');
                
        } catch (\Exception $e) {
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