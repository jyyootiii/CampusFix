@echo off
title CampusFix Server
echo ========================================================
echo       Starting CampusFix Web Application Server
echo ========================================================
echo.

:: Ensure PHP is on PATH
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] PHP is not found on your Windows PATH!
    echo Please make sure PHP is installed and configured in your PATH environment variable.
    pause
    exit /b 1
)

echo [INFO] PHP Version detected:
php -v
echo.
echo [INFO] Starting built-in PHP development server on http://localhost:8000
echo [INFO] Press Ctrl+C in this terminal window to stop the server anytime.
echo.

:: Automatically launch browser after 1 second delay in background
start "" cmd /c "timeout /t 2 >nul & start http://localhost:8000"

:: Start PHP server
php -S localhost:8000

