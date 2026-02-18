@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d %~dp0

set "APP_DIR=%~dp0"
set "VENDOR_DIR=%APP_DIR%vendor"
set "PHP_DIR=%VENDOR_DIR%\php"
set "PYTHON_EXE=python"
set "PHP_EXE=php"
set "BENNYS_PHP_INI="
set "XAMPP_PHP_EXE=C:\xampp\php\php.exe"

if not exist "%VENDOR_DIR%" mkdir "%VENDOR_DIR%"

echo [Benny's Motorworks] Kontrollerar beroenden...

call :ensure_python
if errorlevel 1 goto :fail

call :resolve_php
if errorlevel 1 goto :fail

call :configure_php_ini
if errorlevel 1 goto :fail

call :verify_sqlite_driver
if errorlevel 1 goto :fail

echo [OK] Python, PHP och SQLite-drivrutiner ar tillgangliga.
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
if exist "%XAMPP_PHP_EXE%" (
  echo [INFO] Hittade XAMPP PHP: %XAMPP_PHP_EXE%
  set "PHP_EXE=%XAMPP_PHP_EXE%"
  rem Viktigt: anvand !PHP_EXE! efter set inuti parentesblock.
  if not exist "!PHP_EXE!" (
    echo [FEL] XAMPP PHP-sokvagen finns inte: !PHP_EXE!
    exit /b 1
  )
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
  if not exist "%PHP_EXE%" (
    echo [FEL] PHP exe hittades inte efter uppackning.
    exit /b 1
  )
  exit /b 0
)

for /f "delims=" %%i in ('where php') do (
  set "PHP_EXE=%%i"
  goto :php_global_found
)

:php_global_found
if not exist "%PHP_EXE%" (
  echo [FEL] PHP exe hittades inte.
  exit /b 1
)
exit /b 0

:configure_php_ini
for %%I in ("%PHP_EXE%") do set "PHP_BIN_DIR=%%~dpI"
set "PHP_INI_PATH=%PHP_BIN_DIR%php.ini"
set "PHP_INI_DEV=%PHP_BIN_DIR%php.ini-development"
set "PHP_INI_CUSTOM=%APP_DIR%bennys_php.ini"

if not exist "%PHP_INI_PATH%" (
  if exist "%PHP_INI_DEV%" (
    copy /Y "%PHP_INI_DEV%" "%PHP_INI_PATH%" >nul
  )
)

if exist "%PHP_INI_PATH%" (
  copy /Y "%PHP_INI_PATH%" "%PHP_INI_CUSTOM%" >nul
) else (
  if exist "%PHP_INI_DEV%" (
    copy /Y "%PHP_INI_DEV%" "%PHP_INI_CUSTOM%" >nul
  ) else (
    echo [FEL] Hittar varken php.ini eller php.ini-development.
    exit /b 1
  )
)

if not exist "%PHP_INI_CUSTOM%" (
  echo [FEL] Kunde inte skapa bennys_php.ini.
  exit /b 1
)

echo [INFO] Aktiverar pdo_sqlite/sqlite3 i bennys_php.ini...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ini='%PHP_INI_CUSTOM%'; $extDir='%PHP_BIN_DIR%ext';" ^
  "$txt=Get-Content -Raw $ini;" ^
  "$txt=$txt -replace ';?\s*extension_dir\s*=\s*\"?ext\"?','extension_dir = \"'+$extDir+'\"';" ^
  "$txt=$txt -replace ';\s*extension\s*=\s*pdo_sqlite','extension=pdo_sqlite';" ^
  "$txt=$txt -replace ';\s*extension\s*=\s*sqlite3','extension=sqlite3';" ^
  "$txt=$txt -replace ';\s*extension\s*=\s*php_pdo_sqlite\.dll','extension=pdo_sqlite';" ^
  "$txt=$txt -replace ';\s*extension\s*=\s*php_sqlite3\.dll','extension=sqlite3';" ^
  "if($txt -notmatch '(?m)^\s*extension\s*=\s*pdo_sqlite\s*$'){ $txt += \"`r`nextension=pdo_sqlite\" };" ^
  "if($txt -notmatch '(?m)^\s*extension\s*=\s*sqlite3\s*$'){ $txt += \"`r`nextension=sqlite3\" };" ^
  "Set-Content -Path $ini -Value $txt -Encoding ASCII"

set "BENNYS_PHP_INI=%PHP_INI_CUSTOM%"
exit /b 0

:verify_sqlite_driver
if "%BENNYS_PHP_INI%"=="" (
  echo [FEL] BENNYS_PHP_INI sattes inte.
  exit /b 1
)

"%PHP_EXE%" -c "%BENNYS_PHP_INI%" -m | findstr /I /R "^pdo_sqlite$" >nul
if errorlevel 1 (
  echo [FEL] pdo_sqlite kunde inte laddas med %BENNYS_PHP_INI%
  echo Kontrollera att filen %PHP_BIN_DIR%ext\php_pdo_sqlite.dll finns.
  exit /b 1
)

"%PHP_EXE%" -c "%BENNYS_PHP_INI%" -m | findstr /I /R "^sqlite3$" >nul
if errorlevel 1 (
  echo [FEL] sqlite3 kunde inte laddas med %BENNYS_PHP_INI%
  echo Kontrollera att filen %PHP_BIN_DIR%ext\php_sqlite3.dll finns.
  exit /b 1
)

exit /b 0

:fail
echo.
echo [FEL] Kunde inte starta appen automatiskt.
echo Tips: prova att kora scriptet som Administrator en gang.
pause
exit /b 1
