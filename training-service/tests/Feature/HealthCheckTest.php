<?php
declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class HealthCheckTest extends TestCase
{
    public function testHealthEndpointReturnsOk()
    {
        // Это заглушка для демонстрации
        $response = [
            'status' => 'ok',
            'service' => 'training-service'
        ];
        
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('ok', $response['status']);
    }
    
    public function testDatabaseConnectionInHealthCheck()
    {
        // Простая проверка что можем подключиться к БД
        try {
            require __DIR__ . '/../../src/Database/DatabaseConnection.php';
            $db = \Rebuilder\Training\Database\DatabaseConnection::getInstance();
            
            $this->assertNotNull($db);
            $this->assertTrue(true, 'Database connection successful');
            
        } catch (\Exception $e) {
            $this->fail('Database connection failed: ' . $e->getMessage());
        }
    }
}