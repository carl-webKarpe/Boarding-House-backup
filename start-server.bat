@echo off
REM ==========================================================================
REM  Boarding House Rental System - start the website without XAMPP.
REM  Double-click this file. Keep the black window open while you use the
REM  site; close it (or press Ctrl+C) to stop the server.
REM  The database must already be running (MySQL Server / MySQL Workbench).
REM ==========================================================================
cd /d "%~dp0"

set "PHP_EXE="
where php >nul 2>nul && set "PHP_EXE=php"
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE if exist "C:\xampp1\php\php.exe" set "PHP_EXE=C:\xampp1\php\php.exe"
if not defined PHP_EXE if exist "C:\php\php.exe" set "PHP_EXE=C:\php\php.exe"

if not defined PHP_EXE (
  echo.
  echo  PHP was not found on this computer.
  echo  Install PHP 8 from https://windows.php.net/download and add it to PATH,
  echo  or keep C:\xampp\php\php.exe ^(only PHP is used, not the XAMPP panel^).
  echo.
  pause
  exit /b 1
)

"%PHP_EXE%" -r "exit(extension_loaded('pdo_mysql') ? 0 : 1);"
if errorlevel 1 (
  echo.
  echo  PHP is missing the pdo_mysql extension, so it cannot talk to MySQL.
  echo  Open your php.ini and remove the ; in front of these lines:
  echo      extension=pdo_mysql
  echo      extension=fileinfo
  echo      extension=openssl
  echo.
  "%PHP_EXE%" --ini
  pause
  exit /b 1
)

echo.
echo  Boarding House Rental System is running.
echo.
echo    Home page : http://localhost:8000/
echo    Login     : http://localhost:8000/html/loginform.html
echo    Admin     : http://localhost:8000/admin/
echo.
echo  Keep this window open. Press Ctrl+C to stop.
echo.
start "" "http://localhost:8000/"
"%PHP_EXE%" -S localhost:8000 router.php
