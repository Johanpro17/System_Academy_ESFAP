# Permisos y visibilidad por rol

El sistema aplica **mínimo privilegio**: cada rol ve solo los módulos que necesita y las acciones se validan también en el servidor, no solo en el menú.

La fuente de verdad es `core/Auth.php` (`Auth::permisos_por_rol()`). El menú (`layout/header.php`) y el dashboard (`public/dashboard.php`) se construyen a partir de esos permisos.

## Roles

| Rol | ID | Alcance |
| --- | --- | --- |
| Administrador | 1 | Acceso total mediante comodín `*` |
| Profesor | 2 | Solo sus cursos asignados: estudiantes, cursos, notas y horario |
| Secretaría | 4 | Estudiantes y matrículas (crear/editar, sin eliminar); profesores y cursos en lectura; horarios, reportes y noticias |
| Auditoría | 5 | Consulta global en solo lectura, incluida la bitácora; sin escrituras |

El alumno no es un rol de este panel. Las cuentas históricas de alumno están desactivadas y sin rol.

## Matriz de permisos

| Permiso | Administrador | Profesor | Secretaría | Auditoría |
| --- | :-: | :-: | :-: | :-: |
| `dashboard.ver` | ✓ | ✓ | ✓ | ✓ |
| `estudiantes.ver` | ✓ | ✓ | ✓ | ✓ |
| `estudiantes.crear` | ✓ | — | ✓ | — |
| `estudiantes.editar` | ✓ | — | ✓ | — |
| `estudiantes.eliminar` | ✓ | — | — | — |
| `profesores.ver` | ✓ | — | ✓ | ✓ |
| `profesores.gestionar` | ✓ | — | — | — |
| `cursos.ver` | ✓ | ✓ | ✓ | ✓ |
| `cursos.gestionar` | ✓ | — | — | — |
| `matriculas.ver` | ✓ | — | ✓ | ✓ |
| `matriculas.crear` | ✓ | — | ✓ | — |
| `matriculas.editar` | ✓ | — | ✓ | — |
| `matriculas.eliminar` | ✓ | — | — | — |
| `notas.ver` | ✓ | ✓ | — | ✓ |
| `notas.editar` | ✓ | ✓ | — | — |
| `horarios.ver` | ✓ | ✓ | ✓ | ✓ |
| `reportes.ver` | ✓ | — | ✓ | ✓ |
| `reportes.exportar` | ✓ | — | ✓ | ✓ |
| `noticias.ver` | ✓ | — | ✓ | — |
| `noticias.gestionar` | ✓ | — | — | — |
| `usuarios.ver` | ✓ | — | — | ✓ |
| `usuarios.gestionar` | ✓ | — | — | — |
| `auditoria.ver` | ✓ | — | — | ✓ |
| `perfil.ver` | ✓ | ✓ | ✓ | ✓ |

`✓` = permitido. `—` = denegado. El Administrador no necesita listar cada permiso: el comodín `*` lo habilita todo.

## Módulos visibles en el menú

| Rol | Menú lateral |
| --- | --- |
| Administrador | Todos los módulos |
| Profesor | Resumen, Mis estudiantes, Mis cursos, Mis notas, Mi horario, Mi perfil, Cerrar sesión |
| Secretaría | Resumen, Estudiantes, Profesores, Cursos, Matrículas, Horarios, Noticias, Generar reportes, Promedios, Mi perfil, Cerrar sesión |
| Auditoría | Resumen, Estudiantes, Profesores, Cursos, Matrículas, Notas, Horarios, Generar reportes, Promedios, Usuarios, Auditoría, Mi perfil, Cerrar sesión |

Un grupo del menú (Registros, Gestión, Comunicación, Reportes, Administración) solo se muestra si el rol tiene al menos un permiso dentro de él. Por eso el Profesor no ve los grupos de matrículas, reportes, noticias, usuarios ni auditoría.

## Alcance de los datos

- **Profesor:** los listados de estudiantes, cursos, notas y horario se filtran por los cursos asignados en `curso_docentes`. No ve matrículas globales, reportes, noticias, usuarios ni auditoría.
- **Secretaría:** administra estudiantes y matrículas; puede crear y editar, pero **no eliminar** estudiantes ni matrículas. Profesores y cursos son de solo lectura. No accede a notas, usuarios ni auditoría.
- **Auditoría:** consulta estudiantes, profesores, cursos, matrículas, notas, horarios, reportes y usuarios sin poder crear, editar ni eliminar. Su panel principal muestra la bitácora en lugar de noticias.
- **Administrador:** conserva acceso total.

## Cómo se aplica

1. **Menú:** `layout/header.php` consulta `Auth::puede()` antes de imprimir cada enlace y cada grupo.
2. **Dashboard:** `public/dashboard.php` oculta KPIs, accesos rápidos, tarjetas de módulos y paneles sin permiso. Los botones de creación (por ejemplo, “Nueva matrícula”) solo aparecen con el permiso correspondiente.
3. **Vistas:** cada archivo de `views/` y `public/` empieza con `Auth::requerir_permiso(...)` o `Auth::requerir_rol(...)`. Un acceso directo por URL sin permiso devuelve **HTTP 403**.
4. **Escrituras:** los POST vuelven a comprobar el permiso (`Auth::requerir_permiso('…editar')`, `…crear`, `…eliminar`, `…gestionar`). Ocultar un botón no sustituye esta validación.
5. **Enlaces contextuales:** los enlaces a otros módulos (por ejemplo, “Ir a notas” o “Exportar CSV”) se muestran solo si el rol puede abrir el destino.

## Cómo cambiar un permiso

Edita la lista del rol en `core/Auth.php` y vuelve a probar el menú y el acceso directo. No agregues permisos al Administrador: ya usa `*`.

Después de cambiar permisos, verifica:

```bat
C:\xampp\php\php.exe -l core\Auth.php
C:\xampp\php\php.exe -l layout\header.php
C:\xampp\php\php.exe -l public\dashboard.php
```

Y comprueba con cada rol que el menú solo muestre sus módulos y que una URL no permitida responda 403.
