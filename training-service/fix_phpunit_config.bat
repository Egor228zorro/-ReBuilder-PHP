@echo off
echo === ИСПРАВЛЕНИЕ PHPUNIT КОНФИГА ===
echo.

cd /d %~dp0

echo 1. Создаю правильный phpunit.xml.dist...
echo ^<?xml version="1.0" encoding="UTF-8"?^> > phpunit.xml.dist
echo ^<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" >> phpunit.xml.dist
echo          xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd" >> phpunit.xml.dist
echo          bootstrap="vendor/autoload.php" >> phpunit.xml.dist
echo          colors="true"^> >> phpunit.xml.dist
echo     ^<testsuites^> >> phpunit.xml.dist
echo         ^<testsuite name="Unit"^> >> phpunit.xml.dist
echo             ^<directory^>tests/Unit^</directory^> >> phpunit.xml.dist
echo         ^</testsuite^> >> phpunit.xml.dist
echo     ^</testsuites^> >> phpunit.xml.dist
echo ^</phpunit^> >> phpunit.xml.dist

echo 2. Создаю tests/bootstrap.php (если нужно)...
if not exist "tests" mkdir tests
echo ^<?php > tests\bootstrap.php
echo // Bootstrap file for PHPUnit >> tests\bootstrap.php
echo require __DIR__ . '/../vendor/autoload.php'; >> tests\bootstrap.php

echo 3. Обновляю autoload...
composer dump-autoload

echo.
echo 4. Запускаю тесты...
vendor\bin\phpunit --colors=always

echo.
pause