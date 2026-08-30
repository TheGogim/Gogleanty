@echo off
REM Script de inicio rápido para Gogleanty
REM Este script abre XAMPP y el navegador automáticamente

echo ========================================
echo    GOGLEANTY - Inicio Rapido
echo ========================================
echo.

REM Verificar si XAMPP está instalado
if not exist "C:\xampp\xampp-control.exe" (
    echo [ERROR] XAMPP no encontrado en C:\xampp\
    echo.
    echo Por favor instala XAMPP primero desde:
    echo https://www.apachefriends.org/
    echo.
    pause
    exit /b 1
)

echo [1/3] Abriendo Panel de Control de XAMPP...
start "" "C:\xampp\xampp-control.exe"
echo       Espera a que Apache y MySQL esten en verde
echo.

echo [2/3] Esperando 5 segundos para que XAMPP inicie...
timeout /t 5 /nobreak > nul
echo.

echo [3/3] Abriendo navegador...
echo.

REM Verificar si es la primera vez (no existe .env)
if not exist "%~dp0.env" (
    echo Esta es tu primera vez usando Gogleanty
    echo Abriendo pagina de bienvenida...
    start "" "http://localhost/Gogleanty/bienvenida.html"
) else (
    echo Abriendo aplicacion...
    start "" "http://localhost/Gogleanty"
)

echo.
echo ========================================
echo   Gogleanty se esta abriendo...
echo ========================================
echo.
echo IMPORTANTE:
echo 1. Asegura que Apache y MySQL esten activos en XAMPP
echo 2. Si ves errores, ejecuta setup.php primero
echo 3. Para verificar la BD, abre check-db.php
echo.
echo URLs utiles:
echo - Aplicacion:  http://localhost/Gogleanty
echo - Bienvenida:  http://localhost/Gogleanty/bienvenida.html
echo - Setup:       http://localhost/Gogleanty/setup.php
echo - Verificar:   http://localhost/Gogleanty/check-db.php
echo.
pause
