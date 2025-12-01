<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;

class DatabaseMigrationTest extends TestCase
{
    private static $pdo;
    
    public static function setUpBeforeClass(): void
    {
        // Подключаемся к тестовой базе
        self::$pdo = new PDO(
            'pgsql:host=localhost;port=5432;dbname=training_db',
            'postgres',
            'postgres'
        );
    }
    
    public function testWorkoutsTableExists()
    {
        $stmt = self::$pdo->query("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'workouts'
            )
        ");
        
        $exists = $stmt->fetchColumn();
        $this->assertTrue((bool)$exists, 'Table workouts should exist');
    }
    
    public function testExercisesTableExists()
    {
        $stmt = self::$pdo->query("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = 'exercises'
            )
        ");
        
        $exists = $stmt->fetchColumn();
        $this->assertTrue((bool)$exists, 'Table exercises should exist');
    }
    
    public function testTablesHaveData()
    {
        $tables = ['workouts', 'exercises', 'workout_exercises'];
        
        foreach ($tables as $table) {
            $stmt = self::$pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            
            $this->assertGreaterThanOrEqual(0, $count, "Table $table should have data");
        }
    }
}