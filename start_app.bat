@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d %~dp0

set "APP_DIR=%~dp0"
set "VENDOR_DIR=%APP_DIR%vendor"
set "PHP_DIR=%VENDOR_DIR%\php"
set "PYTHON_EXE=python"
set "PHP_EXE=php"
set "USER_PHP_EXE=C:\Users\death\Downloads\php-8.3.30-Win32-vs16-x64\php.exe"
set "BENNYS_PHP_INI="

if not exist "%VENDOR_DIR%" mkdir "%VENDOR_DIR%"

echo [Benny's Motorworks] Kontrollerar beroenden...

call :ensure_python
if errorlevel 1 goto :fail

call :resolve_php
if errorlevel 1 goto :fail

echo [OK] Python och PHP ar tillgangliga.
set "BENNYS_PHP_EXE=%PHP_EXE%"
echo [INFO] Startar lokal app...
python local_app.py
if errorlevel 1 goto :fail

pause
exit /b 0

:ensure_python
where %PYTHON_EXE% >nul 2>nul
if not errorlevel 1 exit /b 0

echo [INFO] Python hittades inte. Forsoker installera via winget...
where winget >nul 2>nul
if errorlevel 1 (
  echo [FEL] winget saknas. Installera Python manuellt fran https://www.python.org/downloads/windows/
  exit /b 1
)
winget install -e --id Python.Python.3.12 --accept-source-agreements --accept-package-agreements
where %PYTHON_EXE% >nul 2>nul
if errorlevel 1 (
  echo [FEL] Python kunde inte installeras automatiskt.
  exit /b 1
)
exit /b 0

:resolve_php
if exist "%USER_PHP_EXE%" (
  echo [INFO] Hittade PHP enligt din lokala sokvag.
  set "PHP_EXE=%USER_PHP_EXE%"
  call :configure_php_ini
  exit /b 0
)

where php >nul 2>nul
if not errorlevel 1 (
  for /f "delims=" %%i in ('where php') do (
    set "PHP_EXE=%%i"
    goto :php_global_found
  )
)

:php_global_missing
echo [INFO] PHP hittades inte. Forsoker installera via winget...
where winget >nul 2>nul
if not errorlevel 1 (
  winget install -e --id PHP.PHP --accept-source-agreements --accept-package-agreements
)

where php >nul 2>nul
if errorlevel 1 (
  echo [WARN] PHP finns inte globalt. Laddar ner portable PHP till vendor\php ...
  set "PHP_ZIP=%VENDOR_DIR%\php.zip"
  powershell -NoProfile -ExecutionPolicy Bypass -Command "Invoke-WebRequest -Uri 'https://windows.php.net/downloads/releases/php-8.3.10-Win32-vs16-x64.zip' -OutFile '%PHP_ZIP%'"
  if errorlevel 1 (
    echo [FEL] Kunde inte ladda ner PHP zip.
    exit /b 1
  )

  if exist "%PHP_DIR%" rmdir /s /q "%PHP_DIR%"
  mkdir "%PHP_DIR%"
  powershell -NoProfile -ExecutionPolicy Bypass -Command "Expand-Archive -Path '%PHP_ZIP%' -DestinationPath '%PHP_DIR%' -Force"
  if errorlevel 1 (
    echo [FEL] Kunde inte packa upp PHP zip.
    exit /b 1
  )
  del /q "%PHP_ZIP%" >nul 2>nul
  set "PHP_EXE=%PHP_DIR%\php.exe"
  call :configure_php_ini
  exit /b 0
)

for /f "delims=" %%i in ('where php') do (
  set "PHP_EXE=%%i"
  goto :php_global_found
)

:php_global_found
call :configure_php_ini
if not exist "%PHP_EXE%" (
  echo [FEL] PHP exe hittades inte.
  exit /b 1
)
exit /b 0

:configure_php_ini
for %%I in ("%PHP_EXE%") do set "PHP_BIN_DIR=%%~dpI"
set "PHP_INI_PATH=%PHP_BIN_DIR%php.ini"
set "PHP_INI_DEV=%PHP_BIN_DIR%php.ini-development"

if not exist "%PHP_INI_PATH%" (
  if exist "%PHP_INI_DEV%" (
    copy /Y "%PHP_INI_DEV%" "%PHP_INI_PATH%" >nul
  )
)

if not exist "%PHP_INI_PATH%" exit /b 0

echo [INFO] Kontrollerar att pdo_sqlite och sqlite3 ar aktiverade i php.ini...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ini='%PHP_INI_PATH%'; $txt=Get-Content -Raw $ini; $txt=$txt -replace ';?\s*extension_dir\s*=\s*"?ext"?','extension_dir = \"ext\"'; $txt=$txt -replace ';\s*extension\s*=\s*pdo_sqlite','extension=pdo_sqlite'; $txt=$txt -replace ';\s*extension\s*=\s*sqlite3','extension=sqlite3'; Set-Content -Path $ini -Value $txt -Encoding ASCII"
set "BENNYS_PHP_INI=%PHP_INI_PATH%"
exit /b 0

:fail
echo.
echo [FEL] Kunde inte starta appen automatiskt.
echo Installera beroenden manuellt och kor filen igen.
pause
exit /b 1
