@echo off
setlocal

rem Always run from the project directory, even when double-clicked elsewhere.
cd /d "%~dp0"

set "APP_HOST=0.0.0.0"
set "APP_PORT=8000"
set "PHP_EXE=C:\xampp\php\php.exe"

echo.
echo ========================================
echo   VertexPL - Laravel Development Server
echo ========================================
echo.

if not exist "%PHP_EXE%" (
    echo [ERROR] PHP was not found at:
    echo         %PHP_EXE%
    echo.
    echo Please verify the XAMPP PHP installation path.
    goto :failed
)

if not exist "artisan" (
    echo [ERROR] artisan was not found in:
    echo         %CD%
    goto :failed
)

if not exist ".env" (
    if not exist ".env.example" (
        echo [ERROR] Both .env and .env.example are missing.
        goto :failed
    )
    echo [SETUP] Creating .env from .env.example...
    copy /y ".env.example" ".env" >nul
)

rem Read APP_PORT from .env; keep port 8000 when the setting is absent.
for /f "usebackq tokens=1,* delims==" %%A in (".env") do (
    if /i "%%A"=="APP_PORT" set "APP_PORT=%%~B"
)

echo(%APP_PORT%| findstr /r /x "[0-9][0-9]*" >nul
if errorlevel 1 (
    echo [ERROR] APP_PORT in .env must be a number. Current value: %APP_PORT%
    goto :failed
)

if not exist "vendor\autoload.php" (
    where composer >nul 2>&1
    if errorlevel 1 (
        echo [ERROR] PHP dependencies are missing and Composer was not found in PATH.
        goto :failed
    )
    echo [SETUP] Installing PHP dependencies...
    call composer install
    if errorlevel 1 goto :failed
)

findstr /b /c:"APP_KEY=base64:" ".env" >nul 2>&1
if errorlevel 1 (
    echo [SETUP] Generating application key...
    "%PHP_EXE%" artisan key:generate
    if errorlevel 1 goto :failed
)

if not exist "public\mix-manifest.json" (
    where npm >nul 2>&1
    if errorlevel 1 (
        echo [ERROR] Frontend assets are missing and npm was not found in PATH.
        goto :failed
    )
    if not exist "node_modules" (
        echo [SETUP] Installing frontend dependencies...
        call npm install
        if errorlevel 1 goto :failed
    )
    echo [SETUP] Building frontend assets...
    call npm run dev
    if errorlevel 1 goto :failed
)

echo [READY] Login:     http://localhost:%APP_PORT%/login
echo         Dev Login: http://localhost:%APP_PORT%/dev
echo.
echo [LAN] Dev Login URLs for this computer:
for /f "delims=" %%I in ('powershell.exe -NoProfile -Command "[System.Net.Dns]::GetHostAddresses([System.Net.Dns]::GetHostName()).Where({$_.AddressFamily -eq 'InterNetwork' -and $_.IPAddressToString -ne '127.0.0.1'}).IPAddressToString"') do (
    echo         http://%%I:%APP_PORT%/dev
)
echo         Use the IP on the same network as your device.
echo         If blocked, check Windows Firewall for TCP port %APP_PORT%.
echo         Dev Login requires APP_ENV=local or testing.
echo         Press Ctrl+C to stop the server.
echo.

"%PHP_EXE%" artisan serve --host=%APP_HOST% --port=%APP_PORT%
set "EXIT_CODE=%ERRORLEVEL%"

if not "%EXIT_CODE%"=="0" (
    echo.
    echo [ERROR] The Laravel server stopped with exit code %EXIT_CODE%.
    goto :failed
)

endlocal
exit /b 0

:failed
echo.
echo VertexPL could not be started.
pause
endlocal
exit /b 1
