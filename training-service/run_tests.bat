@echo off
echo ========================================
echo ЗАПУСК ТЕСТОВ TRAINING-SERVICE
echo ========================================

echo 1. Проверяем установку PHPUnit...
if not exist "vendor\bin\phpunit" (
    echo PHPUnit не установлен. Устанавливаем...
    composer require --dev phpunit/phpunit
)

echo.
echo 2. Запускаем Unit тесты...
vendor\bin\phpunit --testsuite Unit --colors=always

echo.
echo 3. Запускаем Feature тесты...
vendor\bin\phpunit --testsuite Feature --colors=always

echo.
echo 4. Запускаем Integration тесты...
vendor\bin\phpunit --testsuite Integration --colors=always

echo.
echo ========================================
echo ТЕСТИРОВАНИЕ ЗАВЕРШЕНО
echo ========================================
pause