<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebuilder\Training\Database\DatabaseConnection;
use PDO;

class DatabaseConnectionTest extends TestCase
{
    public function testGetInstanceReturnsSingleton()
    {
        $instance1 = DatabaseConnection::getInstance();
        $instance2 = DatabaseConnection::getInstance();
        
        $this->assertSame($instance1, $instance2, 'Should return same instance (singleton)');
    }
    
    public function testConnectionIsPDO()
    {
        $instance = DatabaseConnection::getInstance();
        $connection = $instance->getConnection();
        
        $this->assertInstanceOf(PDO::class, $connection, 'Should return PDO instance');
    }
}