@echo off
title Aarcon Builders - Cash & Check Management System
echo =========================================================================
echo       AARCON BUILDERS - Cash & Check Management System
echo =========================================================================
echo.
echo [INFO] Starting built-in PHP development server...
echo [INFO] System URL: http://localhost:8000
echo.

start http://localhost:8000

if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -S localhost:8000
) else (
    php -S localhost:8000
)
pause
