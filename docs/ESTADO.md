# Estado de mejoras profesionales (2026-10-09)

## Aplicado
- `core/Errores.php`: manejador global (errors/exceptions/fatals) → logs/app.log, páginas amigables 500
- `core/bootstrap.php`: inicializa Errores, añade `APP_DEBUG`, `Permissions-Policy`
- `config/config.php`: `debug` (env `APP_DEBUG`)
- `.htaccess` raíz + internos (config/core/legado/scripts/logs/backups) + bloqueo .(sql|log|ini|bak|old|env|dist|lock)
- `scripts/backup_bd.php` + `scripts/backup_bd.bat` (dump PDO, vista-aware, retención)
- `public/403.php`, `public/404.php` pulidas; `public/health.php` (db up/down)
- Pulido CSS: `public/assets/css/sistema.css`, `public/portada.css` (visual coherente, modo oscuro)
- Favicon SVG existente, usa base_url
- Árbol limpio: `controllers/`, `models/` vacíos eliminados; `dashboard.css`, `login-intranet.css`, `escuela_artistica (1).sql` movidos a `legado/`; `TODO.md` → `docs/`

## Verificación
- .htaccess: 403 para `/legado/*.sql`, `/config/database.php`, `/scripts/backup_bd.php`, listado `/legado/` → OK
- Smoke: login/index/css/js 200; 403/404 200 con texto → OK
- Tests: pruebas.php 65/0, test_password.php 34/0, test_matricula.php 71/0, test_instalador.php 8/0, test_expansion.php OK
- Health: `db=up`, HTTP 200 (o 503 si cae)

## Portales de estudiantes y docentes
- Estudiantes inician en su portal personal de calificaciones, con consultas limitadas a su propia ficha.
- La ficha de profesor crea o vincula una cuenta con rol Docente y contraseña; desde cada curso se administran las asignaciones docentes. El panel, listados, reportes, exportaciones y constancias del docente se limitan a sus cursos asignados; solo administración conserva la vista institucional completa. Los horarios se filtran por docente y siguen mostrando las sesiones programadas.
- La publicación de noticias y el envío de avisos a toda la comunidad quedan reservados a administración.
- En instalaciones existentes, ejecutar `php scripts/migrar_curso_docentes.php` para crear la relación de asignaciones y copiar las que ya aparecen en horarios.
- Para habilitar cuentas de profesores existentes, abrir y guardar su ficha desde Administración → Profesores, asignando correo y contraseña.

## Roles de secretaría y auditoría, permisos por acción y paleta morada (2026-10-28)
- Roles nuevos: `4 Secretaría` y `5 Auditoría`, con cuentas `secretaria@esfap.edu` / `Secretaria2026` y `auditoria@esfap.edu` / `Auditoria2026` (cambiar antes de publicar).
- `core/Auth.php`: constantes de rol, matriz de permisos por acción, helpers de rol y `requerir_permiso()`; el administrador conserva `*`.
- Secretaría gestiona estudiantes, matrículas y constancias, y consulta el resto (incluida la lista de usuarios); notas en solo lectura.
- Auditoría consulta todo y la bitácora, sin escrituras. Docente sigue limitado a sus cursos; el resto de roles ve datos globales.
- Cada acción POST valida su permiso además de ocultar la interfaz; los menús y paneles se generan según permisos.
- Instalador actualizado y `scripts/sembrar_roles_cuentas.php` (idempotente) para instalaciones existentes.
- Paleta institucional migrada de azul a morado (`#6d28d9` claro / `#a78bfa` oscuro) conservando el dorado como acento; neutros y sidebar teñidos, modo oscuro revisado.
- Verificación: sintaxis PHP en 30 archivos, smoke de 5 roles en 19 páginas sin 500 ni avisos, 403 solo en accesos no permitidos, y POST de escritura bloqueados para secretaría/auditoría.