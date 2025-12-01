@echo off
echo === Откат последнего batch миграций ===

set /p DB_HOST="Хост (localhost): "
if "%DB_HOST%"=="" set DB_HOST=localhost

set /p DB_PORT="Порт (5432): "
if "%DB_PORT%"=="" set DB_PORT=5432

set /p DB_NAME="База данных (training_db): "
if "%DB_NAME%"=="" set DB_NAME=training_db

set /p DB_USER="Пользователь (postgres): "
if "%DB_USER%"=="" set DB_USER=postgres

set /p DB_PASS="Пароль: "

echo Подключаемся к %DB_HOST%:%DB_PORT%...
psql -h %DB_HOST% -p %DB_PORT% -U %DB_USER% -d %DB_NAME% -c "
SELECT 'Откатываем batch ' || MAX(batch) FROM migrations;

DELETE FROM migrations WHERE batch = (SELECT MAX(batch) FROM migrations);

DROP TABLE IF EXISTS tts_jobs CASCADE;
DROP TABLE IF EXISTS user_workout_settings CASCADE;
DROP TABLE IF EXISTS workout_exercises CASCADE;
DROP TABLE IF EXISTS exercises CASCADE;
DROP TABLE IF EXISTS workouts CASCADE;
DROP TABLE IF EXISTS migrations CASCADE;
"

echo === Откат завершен ===
pause