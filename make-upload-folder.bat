@echo off
REM ==========================================================================
REM  Boarding House Rental System - build a SAFE folder to upload to the host.
REM
REM  Double-click this file. It creates the folder  upload-to-infinityfree
REM  next to it with only the website files. Then, in FileZilla, open that
REM  folder on the left, select everything inside it and upload it to htdocs
REM  (overwrite when asked).
REM
REM  Left out on purpose, so the server's own copies are never replaced:
REM    security\config.local.php   (server database settings)
REM    security\mail.local.php     (Brevo email settings)
REM    security\oauth.local.php    (Google / Facebook login settings)
REM    storage\   (server logs and sessions)    uploads\  (landlords' photos)
REM    database\  setup\  node_modules\  .git\  and the build/test files
REM ==========================================================================
cd /d "%~dp0"

set "OUT=upload-to-infinityfree"

if not exist "security\vendor\autoload.php" (
  echo.
  echo  security\vendor is missing. Run  composer install  in this folder first.
  echo.
  pause
  exit /b 1
)

if exist "%OUT%" rmdir /s /q "%OUT%"

robocopy . "%OUT%" /E /NFL /NDL /NJH /NJS /NP ^
  /XD .git .github .claude node_modules storage uploads database setup "%OUT%" ^
  /XF config.local.php mail.local.php oauth.local.php ^
      router.php start-server.bat make-upload-folder.bat ^
      package.json package-lock.json composer.json composer.lock ^
      tailwind.config.js tenant.tailwind.config.js tenant.src.css ^
      README.md .gitignore .gitattributes

REM robocopy: 0-7 = success, 8 or more = something failed
if errorlevel 8 (
  echo.
  echo  Copy failed. Close any program that has these files open and try again.
  echo.
  pause
  exit /b 1
)

echo.
echo  Done. Upload everything INSIDE the folder "%OUT%" to htdocs with FileZilla.
echo  (Your server's config.local.php, mail.local.php, oauth.local.php,
echo   storage and uploads folders are not in it, so they stay safe.)
echo.
echo  After the upload, check FileZilla's "Failed transfers" tab is empty.
echo.
pause
