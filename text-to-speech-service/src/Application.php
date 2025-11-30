<?php

namespace Rebuilder\TextToSpeech;

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use GuzzleHttp\Client;

class Application
{
    private $app;
    private $httpClient;
    private $murfApiKey;

    public function __construct()
    {
        $this->app = AppFactory::create();
        $this->httpClient = new Client();
        $this->murfApiKey = $_ENV['MURF_API_KEY'] ?? '';
        
        $this->setupRoutes();
    }

    private function setupRoutes(): void
    {
        // Health check
        $this->app->get('/health', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode([
                'status' => 'OK',
                'service' => 'text-to-speech',
                'murf_api_configured' => !empty($this->murfApiKey),
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Получение списка доступных голосов
        $this->app->get('/voices', function (Request $request, Response $response) {
            return $this->getAvailableVoices($response);
        });

        // Генерация озвучки через Murf.ai
        $this->app->post('/generate', function (Request $request, Response $response) {
            $data = $request->getParsedBody();
            $text = $data['text'] ?? '';
            $voiceId = $data['voice_id'] ?? 'en-US-Michael-Bright'; // Рабочий голос по умолчанию
            
            if (empty($text)) {
                $response->getBody()->write(json_encode(['error' => 'Text is required']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            return $this->generateSpeech($response, $text, $voiceId);
        });

        // Получение статуса задачи
        $this->app->get('/status/{jobId}', function (Request $request, Response $response, array $args) {
            $jobId = $args['jobId'];
            return $this->getJobStatus($response, $jobId);
        });

        // Тестовый маршрут - генерация примерной озвучки
        $this->app->get('/test', function (Request $request, Response $response) {
            $testText = "Hello! This is a test speech generation via Murf.ai API.";
            
            // Используем известный рабочий голос
            return $this->generateSpeech($response, $testText, 'en-US-Michael-Bright');
        });

        // Корневой маршрут
        $this->app->get('/', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode([
                'message' => 'Text-to-Speech Service',
                'provider' => 'Murf.ai',
                'api_configured' => !empty($this->murfApiKey),
                'endpoints' => [
                    '/health' => 'Health check',
                    '/voices' => 'Get available voices',
                    '/generate' => 'Generate speech (POST: text, voice_id)',
                    '/status/{jobId}' => 'Get job status',
                    '/test' => 'Test speech generation'
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        });
    }

    private function getAvailableVoices(Response $response): Response
    {
        try {
            if (empty($this->murfApiKey)) {
                $response->getBody()->write(json_encode([
                    'error' => 'API key required to fetch voices',
                    'mock_voices' => [
                        ['id' => 'en-US-Michael-Bright', 'name' => 'Michael Bright', 'language' => 'en-US', 'gender' => 'male'],
                        ['id' => 'en-US-Sarah-Clear', 'name' => 'Sarah Clear', 'language' => 'en-US', 'gender' => 'female'],
                        ['id' => 'en-GB-Emma-Standard', 'name' => 'Emma Standard', 'language' => 'en-GB', 'gender' => 'female']
                    ]
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $murfResponse = $this->httpClient->get('https://api.murf.ai/v1/speech/voices', [
                'headers' => [
                    'api-key' => $this->murfApiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 10
            ]);

            $voices = json_decode($murfResponse->getBody()->getContents(), true);
            $response->getBody()->write(json_encode($voices));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Failed to fetch voices',
                'message' => $e->getMessage(),
                'mock_voices' => [
                    ['id' => 'en-US-Michael-Bright', 'name' => 'Michael Bright', 'language' => 'en-US'],
                    ['id' => 'en-US-Sarah-Clear', 'name' => 'Sarah Clear', 'language' => 'en-US']
                ]
            ]));
            return $response->withStatus(500);
        }
    }

    private function generateSpeech(Response $response, string $text, string $voiceId = 'en-US-Michael-Bright'): Response
    {
        try {
            if (empty($this->murfApiKey)) {
                // Заглушка если API ключ не настроен
                $mockResponse = [
                    'job_id' => 'tts_' . uniqid(),
                    'status' => 'completed',
                    'text' => $text,
                    'voice_id' => $voiceId,
                    'audio_url' => 'https://example.com/audio/mock-' . uniqid() . '.mp3',
                    'message' => 'Mock response - set MURF_API_KEY for real integration',
                    'provider' => 'murf.ai (mock)'
                ];
                
                $response->getBody()->write(json_encode($mockResponse));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // РЕАЛЬНЫЙ ВЫЗОВ MURF.AI API
            $requestData = [
                'text' => $text,
                'voice' => $voiceId,
                'format' => 'mp3',
                'sample_rate' => 24000
            ];

            $murfResponse = $this->httpClient->post('https://api.murf.ai/v1/speech/generate', [
                'headers' => [
                    'api-key' => $this->murfApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
                'timeout' => 30
            ]);

            $result = json_decode($murfResponse->getBody()->getContents(), true);
            
            $response->getBody()->write(json_encode([
                'job_id' => $result['id'] ?? 'tts_' . uniqid(),
                'status' => 'completed',
                'audio_url' => $result['url'] ?? null,
                'text' => $text,
                'voice_id' => $voiceId,
                'provider' => 'murf.ai'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Failed to generate speech',
                'message' => $e->getMessage(),
                'provider' => 'murf.ai'
            ]));
            return $response->withStatus(500);
        }
    }

    private function getJobStatus(Response $response, string $jobId): Response
    {
        try {
            // РЕАЛЬНЫЙ ЗАПРОС СТАТУСА К MURF.AI
            $murfResponse = $this->httpClient->get("https://api.murf.ai/v1/speech/generate/{$jobId}", [
                'headers' => [
                    'api-key' => $this->murfApiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 10
            ]);

            $result = json_decode($murfResponse->getBody()->getContents(), true);
            
            $statusResponse = [
                'job_id' => $jobId,
                'status' => $result['status'] ?? 'unknown',
                'provider' => 'murf.ai'
            ];

            // Добавляем audio_url если задача завершена
            if (($result['status'] ?? '') === 'completed' && isset($result['audioUrl'])) {
                $statusResponse['audio_url'] = $result['audioUrl'];
            }

            $response->getBody()->write(json_encode($statusResponse));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Failed to get job status',
                'message' => $e->getMessage(),
                'provider' => 'murf.ai'
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function run()
    {
        $this->app->run();
    }
}
