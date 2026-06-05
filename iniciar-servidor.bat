@echo off
title UCI Panel - Servidor de desarrollo
echo.
echo  ============================================
echo   UCI Panel - Clinica de Occidente
echo  ============================================
echo.

REM Verificar si el puerto 8000 esta ocupado
netstat -ano | findstr ":8000" >nul 2>&1
if %errorlevel%==0 (
    echo  [AVISO] Puerto 8000 en uso, usando 8001...
    echo  URL: http://127.0.0.1:8001
    echo.
    C:\xampp\php\php.exe artisan serve --port=8001
) else (
    echo  URL: http://127.0.0.1:8000
    echo.
    C:\xampp\php\php.exe artisan serve --port=8000
)

pause
