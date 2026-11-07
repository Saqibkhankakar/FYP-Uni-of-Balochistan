@echo off
setlocal
cd /d "%~dp0"
if not exist "C:\xampp\php\php.exe" (
    echo PHP was not found at C:\xampp\php\php.exe.
    echo Follow README.md if XAMPP is installed somewhere else.
    pause
    exit /b 1
)
echo Start Apache and MySQL in XAMPP Control Panel before continuing.
echo This installs the schema and fictional demo data without removing existing data.
pause
"C:\xampp\php\php.exe" scripts\install.php --demo
if errorlevel 1 (
    pause
    exit /b 1
)
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\create-admin.ps1
echo Open http://localhost/doctor-hospital/ after setup.
pause
