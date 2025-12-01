@echo off
echo === СБРОС БАЗЫ ДАННЫХ ===
echo ОСТОРОЖНО! Все данные будут удалены!

set /p CONFIRM="Вы уверены? (y/N): "
if /i "%CONFIRM%" neq "y" (
    echo Отменено
    pause
    exit /b
)

echo Подключаемся к PostgreSQL...
psql -h localhost -p 5432 -U postgres -c "DROP DATABASE IF EXISTS training_db"
echo База удалена

echo Создаем новую базу...
psql -h localhost -p 5432 -U postgres -c "CREATE DATABASE training_db"
echo База создана

echo === ГОТОВО! Теперь можно запустить миграции ===
pause