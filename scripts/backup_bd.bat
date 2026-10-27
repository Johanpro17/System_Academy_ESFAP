@echo off
REM Respaldo de base de datos del Sistema Académico.
REM Uso: scripts\backup_bd.bat [--conservar=N]
"C:\xampp\php\php.exe" "%~dp0backup_bd.php" %*
pause