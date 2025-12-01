<?php

declare(strict_types=1);

namespace Rebuilder\TextToSpeech;

use GuzzleHttp\Client;
use JsonException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Rebuilder\TextToSpeech\Common\EnsiErrorHandler;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;

class Application
{
    /** @var App<ContainerInterface|null> */
    private App $app;
    private Client $httpClient;
    private string $murfApiKey;

    public function __construct()
    {
        // Создаем приложение БЕЗ дефолтного ErrorMiddleware
        $this->app = AppFactory::create();
        $this->httpClient = new Client();

        $apiKey = $_ENV['MURF_API_KEY'] ?? '';
        $this->murfApiKey = is_string($apiKey) ? $apiKey : '';

        $this->app->addBodyParsingMiddleware();

        // ✅ КРИТИЧЕСКИ ВАЖНО: Добавляем ПУСТОЙ ErrorMiddleware который НЕ преобразует
        $errorMiddleware = $this->app->addErrorMiddleware(
            false,  // displayErrorDetails
            true,   // logErrors
            true    // logErrorDetails
        );

        // ✅ ПЕРЕОПРЕДЕЛЯЕМ обработчик ошибок - возвращаем ENSE формат
        $errorMiddleware->setDefaultErrorHandler(function (
            Request $request,
            \Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) {
            // ✅ РАЗЛИЧАЕМ ТИПЫ ИСКЛЮЧЕНИЙ
            if ($exception instanceof HttpNotFoundException) {
                $error = EnsiErrorHandler::notFoundError(  // <-- ИСПРАВЛЕНО: notFoundError
                    $exception->getMessage(),
                    $request->getUri()->getPath(),
                    [
                        'exception' => get_class($exception),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]
                );
            } else {
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
            }

            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode($error, JSON_THROW_ON_ERROR));
            return $response
                ->withStatus($error['status'])
                ->withHeader('Content-Type', 'application/json');
        });

        $this->setupRoutes();
    }

    private function setupRoutes(): void
    {
        $self = $this;

        // Health check
        $this->app->get('/health', function (Request $request, Response $response) use ($self): Response {
            $data = [
                'status' => 'OK',
                'service' => 'text-to-speech',
                'murf_api_configured' => !empty($self->murfApiKey),
                'timestamp' => date('Y-m-d H:i:s')
            ];

            return $self->writeJson($response, $data, $request->getUri()->getPath());
        });

        // Получение списка доступных голосов
        $this->app->get('/voices', function (Request $request, Response $response) use ($self): Response {
            return $self->getAvailableVoices($response, $request->getUri()->getPath());
        });

        // Генерация озвучки через Murf.ai
        $this->app->post('/generate', function (Request $request, Response $response) use ($self): Response {
            try {
                /** @var array{text?: string, voice_id?: string} $data */
                $data = (array) $request->getParsedBody();

                $text = $data['text'] ?? '';
                $voiceId = $data['voice_id'] ?? 'en-US-alina';

                if (empty($text)) {
                    $error = EnsiErrorHandler::validationError(
                        'Text is required for speech generation',
                        '/generate',
                        ['field' => 'text', 'reason' => 'required_field_missing']
                    );

                    return $self->writeJson($response->withStatus(400), $error, $request->getUri()->getPath());
                }

                return $self->generateSpeech($response, $text, $voiceId, $request->getUri()->getPath());

            } catch (\Exception $e) {
                $error = EnsiErrorHandler::serverError(
                    $e->getMessage(),
                    '/generate',
                    ['exception' => get_class($e)]
                );

                return $self->writeJson($response->withStatus(500), $error, $request->getUri()->getPath());
            }
        });

        // Получение статуса TTS задачи
        $this->app->get('/status/{jobId}', function (Request $request, Response $response, array $args) use ($self): Response {
            $jobId = (string) ($args['jobId'] ?? '');

            $data = [
                'job_id' => $jobId,
                'status' => 'completed',
                'audio_url' => 'https://example.com/audio/completed-' . $jobId . '.mp3',
                'message' => 'TTS job completed'
            ];

            return $self->writeJson($response, $data, $request->getUri()->getPath());
        });

        // Валидация голоса
        $this->app->post('/validate-voice', function (Request $request, Response $response) use ($self): Response {
            /** @var array{voice_id?: string} $data */
            $data = (array) $request->getParsedBody();
            $voiceId = $data['voice_id'] ?? '';

            $validVoices = ['en-US-alina', 'en-US-cooper', 'en-UK-hazel', 'en-US-daniel'];
            $isValid = in_array($voiceId, $validVoices, true);

            $data = [
                'voice_id' => $voiceId,
                'valid' => $isValid,
                'available_voices' => $validVoices
            ];

            return $self->writeJson($response, $data, $request->getUri()->getPath());
        });

        // Метрики сервиса
        $this->app->get('/metrics', function (Request $request, Response $response) use ($self): Response {
            $metrics = [
                'tts_requests_total' => 42,
                'tts_requests_failed' => 2,
                'active_voices' => 4,
                'service_uptime' => '99.9%'
            ];

            return $self->writeJson($response, $metrics, $request->getUri()->getPath());
        });

        // Тестовый маршрут для 500 ошибки
        $this->app->get('/test-500', function (Request $request, Response $response): Response {
            // Искусственно вызываем исключение для теста
            throw new \RuntimeException('Тестовая 500 ошибка: что-то пошло не так!');
        });

        // Тестовый маршрут
        $this->app->get('/test', function (Request $request, Response $response) use ($self): Response {
            $testText = "Hello! This is a test speech generation.";

            return $self->generateSpeech($response, $testText, 'en-US-alina', $request->getUri()->getPath());
        });

        // Корневой маршрут
        $this->app->get('/', function (Request $request, Response $response) use ($self): Response {
            $data = [
                'message' => 'Text-to-Speech Service',
                'provider' => 'Murf.ai',
                'api_configured' => !empty($self->murfApiKey),
                'endpoints' => [
                    '/health' => 'Health check',
                    '/voices' => 'Get available voices',
                    '/generate' => 'Generate speech (POST: text, voice_id)',
                    '/status/{jobId}' => 'Get TTS job status',
                    '/validate-voice' => 'Validate voice ID',
                    '/metrics' => 'Service metrics',
                    '/test-500' => 'Test 500 error',
                    '/test' => 'Test speech generation'
                ]
            ];

            return $self->writeJson($response, $data, $request->getUri()->getPath());
        });
    }
    
    private function getAvailableVoices(Response $response, string $path = '/'): Response
    {
        try {
            if (empty($this->murfApiKey)) {
                $error = EnsiErrorHandler::validationError(
                    'API key required to fetch voices',
                    '/voices',
                    [
                        'service' => 'murf.ai',
                        'reason' => 'missing_api_key',
                        'mock_voices' => [
                            ['voiceId' => 'en-US-alina', 'displayName' => 'Alina (F)', 'locale' => 'en-US'],
                            ['voiceId' => 'en-US-cooper', 'displayName' => 'Cooper (M)', 'locale' => 'en-US']
                        ]
                    ]
                );

                return $this->writeJson($response->withStatus(400), $error, $path);
            }

            $murfResponse = $this->httpClient->get('https://api.murf.ai/v1/speech/voices', [
                'headers' => [
                    'api-key' => $this->murfApiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 10
            ]);

            /** @var array<mixed> $voices */
            $voices = json_decode($murfResponse->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            return $this->writeJson($response, $voices, $path);

        } catch (\Exception $e) {
            $error = EnsiErrorHandler::externalServiceError(
                $e->getMessage(),
                '/voices',
                ['provider' => 'murf.ai', 'endpoint' => '/v1/speech/voices']
            );

            return $this->writeJson($response->withStatus(502), $error, $path);
        }
    }

    private function generateSpeech(Response $response, string $text, string $voiceId = 'en-US-alina', string $path = '/'): Response
    {
        try {
            $data = [
                'job_id' => 'tts_' . uniqid('', true),
                'status' => 'completed',
                'text' => $text,
                'voice_id' => $voiceId,
                'audio_url' => 'https://example.com/audio/generated-' . uniqid('', true) . '.mp3',
                'message' => 'TTS generated successfully (mock mode)',
                'provider' => 'murf.ai'
            ];

            return $this->writeJson($response, $data, $path);

        } catch (\Exception $e) {
            $error = EnsiErrorHandler::serverError(
                $e->getMessage(),
                '/generate',
                ['function' => 'generateSpeech', 'voice_id' => $voiceId]
            );

            return $this->writeJson($response->withStatus(500), $error, $path);
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function writeJson(Response $response, array $data, string $path = '/'): Response
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            $response->getBody()->write($json);
        } catch (JsonException $e) {
            // ✅ Используем EnsiErrorHandler для ошибок JSON
            $error = EnsiErrorHandler::serverError(
                'JSON encoding failed: ' . $e->getMessage(),
                $path,
                [
                    'json_error' => $e->getMessage(),
                    'json_last_error' => json_last_error_msg()
                ]
            );

            $response->getBody()->write(json_encode($error, JSON_THROW_ON_ERROR));
            $response = $response->withStatus(500);
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function run(): void
    {
        $this->app->run();
    }
}
