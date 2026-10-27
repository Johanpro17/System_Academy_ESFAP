# Sistema Académico - ESFAP

Sistema académico desarrollado para la Escuela de Formación Artística ESFAP. Gestión de estudiantes, matrículas, docentes, cursos, notas, notificaciones, reportes y auditoría.

## Tecnologías
- PHP 8.x + PDO (sin frameworks)
- MariaDB/MySQL
- JavaScript nativo
- CSS moderno con variables y modo oscuro

## Estructura
- `core/` - Bootstrap, Auth, Database, Errores, Helpers, Paginador, Notificacion
- `config/` - Configuración, conexión DB
- `public/` - Punto de entrada (index, login, dashboard, assets)
- `views/` - Vistas organizadas por módulo
- `layout/` - Header/Footer compartidos
- `scripts/` - Utilidades (importar_carnet.php, backup_bd.php, backup_bd.bat)
- `logs/` - Registro de errores (`app.log`, protegido por .htaccess)
- `backups/` - Respaldos de BD (.sql, protegidos por .htaccess)
- `legado/` - Archivos no utilizados (protegido)
- `docs/` - Documentación

## Seguridad
- Control de sesiones (httponly, SameSite=Lax, regeneración de ID)
- Headers de seguridad (CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy)
- `.htaccess` protegen config/core/scripts/legado/backups/logs
- Bloqueo de instalador (`config/.instalado`) una vez ejecutado
- Validación/escape (`e()`), placeholders PDO, CSRF en formularios críticos
- Manejador centralizado de errores (sin exposición de rutas/SQL)
- Portal de estudiantes limitado a la consulta de sus propias calificaciones
- Las cuentas docentes se crean o vinculan desde la ficha de profesor; panel, listados, reportes, exportaciones, constancias y registro de notas se limitan a cursos asignados

## Instalación rápida
1. Clonar/colocar en `C:\xampp\htdocs\Johan\sistema-academico\`
2. Crear BD `escuela_artistica` (MariaDB)
3. Acceder a `http://localhost/Johan/sistema-academico/public/instalar.php` y completar
4. Al finalizar queda bloqueado (`config/.instalado`)
5. Login inicial: `admin@sistema.edu` / `admin123`. Cambia la contraseña antes de poner el sistema a disposición de usuarios.
6. Desde **Profesores**, crea cada perfil con su correo y contraseña de acceso. Desde **Estudiantes**, crea una cuenta estudiante con correo y contraseña; comparte las credenciales de forma privada.

## Portales por rol
- **Estudiante:** al iniciar sesión llega a `Mis calificaciones`. Solo puede consultar sus notas y promedios; el acceso a módulos de gestión queda denegado.
- **Docente:** usa el panel docente. Un administrador asigna los cursos desde el formulario del curso; el docente solo puede consultar estudiantes y matrículas inscritos en esos cursos, acceder a sus reportes y constancias, y registrar notas dentro de ellos. La ficha de profesor debe estar vinculada a una cuenta con rol Docente. Los horarios muestran las sesiones efectivamente programadas. Solo administración puede publicar noticias para toda la comunidad.
- **Administrador:** gestiona usuarios, fichas, matrículas, cursos, horarios y el resto del sistema.

### Activar asignaciones de docentes en una instalación existente
Ejecuta una vez desde la carpeta del proyecto: `C:\xampp\php\php.exe scripts\migrar_curso_docentes.php`. La migración crea la relación curso-docente y conserva las asignaciones que ya existan en horarios; después, asigna docentes desde el formulario de cada curso.

## Backups de BD
CLI:
- `php scripts/backup_bd.php` → genera respaldo en `backups/`
- `php scripts/backup_bd.php --conservar=20` → mantiene últimas N copias
Batch (Windows): `scripts\backup_bd.bat`

## Monitoreo
- `public/health.php` → `{status,app,time,db}` (JSON). Retorna HTTP 503 si DB caída.

## Pruebas (secuenciales)
En `C:\Users\Lenovo\AppData\Local\Temp\opencode\`:
- `php pruebas.php` (65)
- `php test_password.php` (34)
- `php test_matricula.php` (71)
- `php test_instalador.php` (8)
- `php test_expansion.php`

Nota: las pruebas de flujo autenticado NO deben ejecutarse en paralelo (bloqueo por IP).

## Notas
- Zona horaria `America/Bogota` (UTC-5). Conservada la lógica existente.
- Modo oscuro disponible desde topbar.
- Diseño institucional (azul/índigo + dorado) con pulido visual.