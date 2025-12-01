<?php
// ОЧЕНЬ ПРОСТОЙ ТЕСТ ДЛЯ ДЕМОНСТРАЦИИ

class SimpleTest
{
    public static function run()
    {
        echo "=== ПРОСТЫЕ ТЕСТЫ ===\n\n";
        
        $tests = [
            'Database exists' => function() {
                try {
                    new PDO('pgsql:host=localhost;dbname=training_db', 'postgres', 'postgres');
                    return '✅ OK';
                } catch (Exception $e) {
                    return '❌ FAIL: ' . $e->getMessage();
                }
            },
            'Workouts table has data' => function() {
                try {
                    $pdo = new PDO('pgsql:host=localhost;dbname=training_db', 'postgres', 'postgres');
                    $stmt = $pdo->query('SELECT COUNT(*) FROM workouts');
                    $count = $stmt->fetchColumn();
                    return $count > 0 ? "✅ OK ($count workouts)" : "⚠️  No data";
                } catch (Exception $e) {
                    return '❌ FAIL: ' . $e->getMessage();
                }
            },
            'Exercises table exists' => function() {
                try {
                    $pdo = new PDO('pgsql:host=localhost;dbname=training_db', 'postgres', 'postgres');
                    $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'exercises')");
                    $exists = $stmt->fetchColumn();
                    return $exists ? '✅ OK' : '❌ FAIL';
                } catch (Exception $e) {
                    return '❌ FAIL: ' . $e->getMessage();
                }
            }
        ];
        
        foreach ($tests as $name => $test) {
            echo "$name: " . $test() . "\n";
        }
        
        echo "\n=== ВСЕ ТЕСТЫ ЗАВЕРШЕНЫ ===\n";
    }
}

SimpleTest::run();