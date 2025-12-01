<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rebuilder\Training\Application;

class ApplicationTest extends TestCase
{
    public function testApplicationCanBeInstantiated()
    {
        $app = new Application();
        $this->assertInstanceOf(Application::class, $app);
    }
}