<?php
require_once __DIR__ . '/src/Database/DatabaseConnection.php';

use Rebuilder\Training\Database\DatabaseConnection;

try {
    $db = DatabaseConnection::getInstance()->getConnection();
    
    echo "=== ПРОВЕРКА ПОДКЛЮЧЕНИЯ К БАЗЕ ДАННЫХ ===\n\n";
    
    // Проверяем количество записей в каждой таблице
    $tables = ['workouts', 'exercises', 'workout_exercises', 'user_workout_settings', 'tts_jobs'];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch();
        echo "- Таблица '$table': {$result['count']} записей\n";
    }
    
    echo "\n=== ТЕСТОВЫЕ ДАННЫЕ ===\n";
    
    // Показываем тренировки
    $stmt = $db->query("SELECT id, name, type, user_id FROM workouts");
    $workouts = $stmt->fetchAll();
    
    echo "Тренировки:\n";
    foreach ($workouts as $workout) {
        echo "- {$workout['name']} ({$workout['type']}) для пользователя {$workout['user_id']}\n";
    }
    
    echo "\n=== ПРОВЕРКА КОРРЕКТНОСТИ СВЯЗЕЙ ===\n";
    
    // Проверяем связи между таблицами
    $stmt = $db->query("
        SELECT 
            w.name as workout_name,
            e.name as exercise_name,
            we.order_index,
            we.duration_seconds
        FROM workouts w
        JOIN workout_exercises we ON w.id = we.workout_id
        JOIN exercises e ON e.id = we.exercise_id
        ORDER BY w.name, we.order_index
    ");
    
    $relations = $stmt->fetchAll();
    
    if (count($relations) > 0) {
        echo "Связи тренировок и упражнений:\n";
        foreach ($relations as $rel) {
            echo "- Тренировка '{$rel['workout_name']}': упражнение '{$rel['exercise_name']}' (порядок: {$rel['order_index']}, длительность: {$rel['duration_seconds']} сек)\n";
        }
    } else {
        echo "Нет связей между тренировками и упражнениями\n";
    }
    
    echo "\n✅ Все проверки пройдены! База данных работает корректно.\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}