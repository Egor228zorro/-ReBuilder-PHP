<?php
declare(strict_types=1);

echo "=== МИГРАЦИИ TRAINING SERVICE ===\n\n";

// Автозагрузка
require_once __DIR__ . '/../vendor/autoload.php';

// Загружаем конфиг
$configFile = __DIR__ . '/../config/database.php';
if (!file_exists($configFile)) {
    die("❌ Файл конфигурации не найден: config/database.php\n");
}

$config = require $configFile;

echo "Конфигурация БД:\n";
echo "- Хост: {$config['host']}\n";
echo "- База: {$config['database']}\n";
echo "- Пользователь: {$config['username']}\n";
echo "- Порт: {$config['port']}\n\n";

try {
    // Пробуем подключиться к PostgreSQL
    echo "Проверка подключения к PostgreSQL...\n";
    $pdo = new PDO(
        "pgsql:host={$config['host']};port={$config['port']}",
        $config['username'],
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Подключение к PostgreSQL успешно\n";
    
    // Проверяем/создаем базу данных
    $result = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$config['database']}'");
    if (!$result->fetch()) {
        echo "Создаем базу данных '{$config['database']}'...\n";
        $pdo->exec("CREATE DATABASE {$config['database']}");
        echo "✅ База создана\n";
    } else {
        echo "✅ База уже существует\n";
    }
    
    // Подключаемся к конкретной базе
    $pdo = new PDO(
        "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
        $config['username'],
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "\n=== ВЫПОЛНЕНИЕ МИГРАЦИЙ ===\n";
    
    // Создаем таблицу для отслеживания миграций
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            batch INTEGER NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Получаем список выполненных миграций
    $stmt = $pdo->query("SELECT name FROM migrations ORDER BY id");
    $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Список миграций в порядке выполнения
    $migrationFiles = [
        '001_create_tables.sql',
        '002_seed_initial_data.sql',
        '003_add_audit_columns.sql'
    ];
    
    $batch = (int) $pdo->query("SELECT COALESCE(MAX(batch), 0) FROM migrations")->fetchColumn() + 1;
    
    foreach ($migrationFiles as $migrationFile) {
        $filePath = __DIR__ . '/' . $migrationFile;
        
        if (!file_exists($filePath)) {
            echo "⚠️  Файл {$migrationFile} не найден, пропускаем\n";
            continue;
        }
        
        if (in_array($migrationFile, $executedMigrations)) {
            echo "✅ {$migrationFile} уже выполнена\n";
            continue;
        }
        
        echo "Выполняем {$migrationFile}... ";
        
        try {
            $pdo->beginTransaction();
            
            // Выполняем SQL из файла
            $sql = file_get_contents($filePath);
            $pdo->exec($sql);
            
            // Записываем в историю
            $stmt = $pdo->prepare("INSERT INTO migrations (name, batch) VALUES (?, ?)");
            $stmt->execute([$migrationFile, $batch]);
            
            $pdo->commit();
            echo "✅ Успешно\n";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "❌ Ошибка: " . $e->getMessage() . "\n";
            break;
        }
    }
    
    echo "\n🎉 ВСЕ МИГРАЦИИ ЗАВЕРШЕНЫ!\n";
    
    // Показываем созданные таблицы
    echo "\n=== СОЗДАННЫЕ ТАБЛИЦЫ ===\n";
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ");
    
    while ($table = $stmt->fetch(PDO::FETCH_COLUMN)) {
        echo "- {$table}\n";
    }
    
    // Показываем историю миграций
    echo "\n=== ИСТОРИЯ МИГРАЦИЙ ===\n";
    $stmt = $pdo->query("
        SELECT name, batch, executed_at 
        FROM migrations 
        ORDER BY id
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['name']} (batch {$row['batch']}) at {$row['executed_at']}\n";
    }
    
} catch (PDOException $e) {
    echo "\n❌ ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}