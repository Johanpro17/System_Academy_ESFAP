# Estado del sistema

## Refactor de modelo académico y base de datos (2026-10-28)

### Roles

- Cuatro roles de sistema: `1 Administrador`, `2 Profesor`, `4 Secretaría`, `5 Auditoría`.
- El rol `3 Estudiante` se retiró: el alumno ya no inicia sesión en este panel.
- `roles.activo` permite desactivar un rol sin borrar su historial.
- El login exige `usuarios.activo = 1`, `id_rol` no nulo y un rol activo. `Auth::iniciar()` no asigna Administrador por defecto.

### Permisos por rol (2026-10-28)

- Profesor limitado a resumen, sus estudiantes, sus cursos, sus notas y su horario.
- Secretaría sin notas, usuarios ni auditoría; no puede eliminar estudiantes ni matrículas.
- Auditoría en solo lectura global, sin noticias; su panel muestra la bitácora.
- Menú, dashboard y enlaces contextuales se generan con `Auth::puede()`; un acceso directo sin permiso responde 403.
- Matriz completa en [`docs/PERMISOS.md`](PERMISOS.md).

### Ficha de alumno independiente

- `estudiantes` incorpora `nombres`, `apellidos`, `email`, `telefono`, `creado_en` y `actualizado_en`.
- `estudiantes.id_usuario` es opcional y su clave foránea usa `ON DELETE SET NULL`: eliminar una cuenta no elimina la ficha.
- Las 120 cuentas históricas de alumno siguen en `usuarios`, con `activo = 0` e `id_rol = NULL`; conservan su vínculo en `estudiantes.id_usuario`.
- El alta y la edición de estudiantes trabajan solo sobre la ficha. No crean cuentas ni contraseñas.
- Matrículas, constancias, notas, reportes, promedios y panel usan los datos de la ficha, no de `usuarios`.

### Esquema

- `profesores.id_usuario` es `INT`, único y con clave foránea `ON DELETE SET NULL`.
- Índices: `uk_est_dni`, `uk_est_matricula`, `idx_est_nombre`, `idx_est_estado`, `idx_est_esp_estado`, `idx_est_email`, `idx_usuarios_rol_activo`, `idx_mat_periodo_estado`, `idx_notas_periodo`, `idx_aud_entidad`.
- Las 15 tablas base usan `utf8mb4_unicode_ci`.
- `v_promedio_por_curso` se reconstruyó sobre la ficha y devuelve matrícula y nombre correctos.

### Código

- `Auth`: cuatro roles, permisos sin alumno, `url_inicio()` y guardas de sesión.
- Navegación y dashboard sin portal de alumno; KPIs y últimas matrículas desde la ficha.
- `public/docente.php` pasó a `public/profesor.php`.
- Se retiraron el portal `mis_calificaciones`, el importador del carnet (creaba cuentas con el rol eliminado), las migraciones de una sola vez, los datos de demostración y `legado/`.
- Las imágenes institucionales viven en `public/assets/img/`.
- `scripts/respaldo_bd.php` y `respaldo_bd.bat` reemplazan al antiguo `backup_bd.*`.

### Verificación

- Migración idempotente ejecutada dos veces sin duplicar datos ni perder registros.
- Roles resultantes: 2 administradores, 6 profesores, 1 secretaría y 1 auditoría activos.
- 120 fichas de alumno, 0 cuentas de alumno activas.
- La vista de promedios responde con datos de la ficha.
