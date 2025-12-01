<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebuilder\Training\Service\WorkoutService;
use PDO;
use PDOStatement;

class WorkoutServiceTest extends TestCase
{
    private $pdoMock;
    private $service;
    
    protected function setUp(): void
    {
        // Мокаем PDO
        $this->pdoMock = $this->createMock(PDO::class);
        $this->service = new WorkoutService($this->pdoMock);
    }
    
    public function testServiceCanBeInstantiated()
    {
        $this->assertInstanceOf(WorkoutService::class, $this->service);
    }
    
    public function testGetAllWorkoutsReturnsArray()
    {
        // Мокаем statement
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'Test Workout']
        ]);
        
        $this->pdoMock->method('query')->willReturn($stmtMock);
        
        $result = $this->service->getAllWorkouts();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result[0]);
    }
}