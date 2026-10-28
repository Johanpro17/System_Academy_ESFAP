@echo off
REM Respaldo de base de datos del Sistema Academico.
REM Uso: scripts\respaldo_bd.bat [--conservar=N]
"C:\xampp\php\php.exe" "%~dp0respaldo_bd.php" %*
pause
