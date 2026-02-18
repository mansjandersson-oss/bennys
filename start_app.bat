@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d %~dp0

set "APP_DIR=%~dp0"
set "VENDOR_DIR=%APP_DIR%vendor"
set "PHP_DIR=%VENDOR_DIR%\php"
set "PYTHON_EXE=python"
set "PHP_EXE=php"

if not exist "%VENDOR_DIR%" mkdir "%VENDOR_DIR%"

echo [Benny's Motorworks] Kontrollerar beroenden...

where %PYTHON_EXE% >nul 2>nul
if errorlevel 1 (
  echo [INFO] Python hittades inte. Forsoker installera via winget...
  where winget >nul 2>nul
  if not errorlevel 1 (
    winget install -e --id Python.Python.3.12 --accept-source-agreements --accept-package-agreements
  ) else (
    echo [FEL] winget saknas. Installera Python manuellt fran https://www.python.org/downloads/windows/
    goto :fail
  )
)

where %PYTHON_EXE% >nul 2>nul
if errorlevel 1 (
  echo [FEL] Python kunde inte installeras automatiskt.
  goto :fail
)

where %PHP_EXE% >nul 2>nul
if errorlevel 1 (
  echo [INFO] PHP hittades inte. Forsoker installera via winget...
  where winget >nul 2>nul
  if not errorlevel 1 (
    winget install -e --id PHP.PHP --accept-source-agreements --accept-package-agreements
  )
)

where %PHP_EXE% >nul 2>nul
if errorlevel 1 (
  echo [WARN] PHP finns inte globalt. Laddar ner portable PHP till vendor\php ...
  set "PHP_ZIP=%VENDOR_DIR%\php.zip"
  powershell -NoProfile -ExecutionPolicy Bypass -Command "Invoke-WebRequest -Uri 'https://windows.php.net/downloads/releases/php-8.3.10-Win32-vs16-x64.zip' -OutFile '%PHP_ZIP%'"
  if errorlevel 1 (
    echo [FEL] Kunde inte ladda ner PHP zip.
    goto :fail
  )

  if exist "%PHP_DIR%" rmdir /s /q "%PHP_DIR%"
  mkdir "%PHP_DIR%"
  powershell -NoProfile -ExecutionPolicy Bypass -Command "Expand-Archive -Path '%PHP_ZIP%' -DestinationPath '%PHP_DIR%' -Force"
  if errorlevel 1 (
    echo [FEL] Kunde inte packa upp PHP zip.
    goto :fail
  )
  del /q "%PHP_ZIP%" >nul 2>nul
  set "PHP_EXE=%PHP_DIR%\php.exe"
) else (
  for /f "delims=" %%i in ('where php') do (
    set "PHP_EXE=%%i"
    goto :phpfound
  )
)

:phpfound
if not exist "%PHP_EXE%" (
  echo [FEL] PHP exe hittades inte.
  goto :fail
)

echo [OK] Python och PHP ar tillgangliga.
set "BENNYS_PHP_EXE=%PHP_EXE%"
echo [INFO] Startar lokal app...
python local_app.py
if errorlevel 1 goto :fail

pause
exit /b 0

:fail
echo.
echo [FEL] Kunde inte starta appen automatiskt.
echo Installera beroenden manuellt och kor filen igen.
pause
exit /b 1
