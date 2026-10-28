# Esquema v3: `escuela_artistica_v3`

Base **nueva y separada** que usa como punto de partida el esquema propuesto, adaptado
para conservar los datos reales. La base `escuela_artistica` y el sistema actual siguen
funcionando sin cambios.

## Archivos

| Archivo | Qué es | ¿Va a Git? |
| --- | --- | --- |
| `scripts/esquema_v3.sql` | Crea y llena la base. No contiene datos personales. | Sí |
| `Database/escuela_artistica_v3.sql` | Copia exportada con todos los datos. | No |

## Crear o recrear la base

```bat
cd C:\xampp\htdocs\Johan\sistema-academico
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < scripts\esquema_v3.sql
```

El script solo borra y recrea `escuela_artistica_v3`:

```sql
DROP DATABASE IF EXISTS escuela_artistica_v3;
```

> **Nunca** cambies ese nombre por `escuela_artistica`: borraría la base real.

El script copia los datos con `INSERT ... SELECT` desde `escuela_artistica`, así que esa
base debe existir. No modifica ni borra nada en ella.

## Qué contiene

19 tablas y 2 vistas:

| Grupo | Tablas |
| --- | --- |
| Identidad | `roles`, `usuarios`, `especialidades`, `estudiantes`, `docentes` |
| Académico | `programas`, `cursos`, `periodos_academicos`, `matriculas`, `matricula_cursos`, `asignacion_docentes`, `evaluaciones`, `notas`, `horarios` |
| Comunicación | `noticias`, `informacion_institucional`, `notificaciones` |
| Control | `auditoria`, `login_intentos` |
| Vistas | `v_alumnos`, `v_promedio_por_curso` |

### Datos migrados

| Tabla | Filas |
| --- | --- |
| `roles` | 5 |
| `usuarios` | 130 |
| `especialidades` | 6 |
| `programas` | 6 |
| `estudiantes` | 120 |
| `docentes` | 6 |
| `cursos` | 7 |
| `periodos_academicos` | 1 |
| `matriculas` | 1 |
| `matricula_cursos` | 1 |
| `asignacion_docentes` | 7 |
| `evaluaciones` | 2 |
| `notas` | 1 |
| `horarios` | 7 |
| `auditoria` | 226 |
| `login_intentos` | 2 |
| `notificaciones` | 0 |
| `noticias` | 0 |
| `informacion_institucional` | 2 |

Los 120 alumnos están completos y **2 conservan su DNI en `NULL`**.

## Decisiones de adaptación

1. **DNI opcional.** `estudiantes.dni` y `docentes.dni` permiten `NULL` porque la base
   actual tiene 2 alumnos sin DNI y los docentes no tienen ese dato. No se inventaron
   números.
2. **Alumnos con cuenta.** El esquema exige `estudiantes.id_usuario`. Se reutilizaron
   las 120 cuentas históricas, todas con rol `estudiante` y `activo = 0`; no pueden
   iniciar sesión.
3. **Roles.** Se conservan los IDs de la base actual para que las cuentas mantengan su
   rol: `1 administrador`, `2 docente`, `4 secretaria`, `5 auditoria`; se agrega
   `3 estudiante` para las cuentas históricas.
4. **Programas.** Se creó un programa por especialidad. Los cursos se asignaron así:

   | Curso | Programa |
   | --- | --- |
   | Fundamentos del Dibujo | Pintura |
   | Historia del Arte | Pintura |
   | Audiación y Solfeo | Música |
   | Fundamentos de la Danza | Danza |
   | Historia de la Música | Teoría Musical |
   | Técnicas de Escultura | Escultura |
   | Interpretación Dramática | Teatro |

5. **Periodo.** La base actual solo tiene la etiqueta `2026-1`, sin fechas. Se registró
   con fechas provisionales (`2026-03-01` a `2026-07-31`); se pueden corregir cuando
   exista el calendario oficial.
6. **Estados.** La matrícula `matriculado` pasa a `activo`. El estado original queda
   escrito en `matriculas.observaciones`.
7. **Docentes.** La especialidad de Marta estaba como texto genérico `Especialidad`; se
   asignó **Pintura** porque dicta Fundamentos del Dibujo e Historia del Arte. El código
   es `DOC001`…`DOC006`.
8. **Noticias.** La base actual no tiene noticias. La tabla existe vacía.

## Equivalencias con el sistema actual

| Sistema actual | Esquema v3 |
| --- | --- |
| `profesores` | `docentes` |
| `curso_docentes` | `asignacion_docentes` |
| `matriculas.id_curso` | `matricula_cursos` |
| `notas.valor` | `notas.calificacion` |
| `noticias.imagen` | `noticias.imagen_url` |
| `noticias.autor_id` | `noticias.id_autor` |
| `estudiantes.matricula` | `estudiantes.codigo` |
| `estudiantes.nombres/apellidos/email` | `usuarios.nombres/apellidos/email` |

## Vistas para revisar

```sql
USE escuela_artistica_v3;

-- Ficha legible del alumno
SELECT * FROM v_alumnos ORDER BY id_estudiante;

-- Alumnos sin DNI
SELECT * FROM v_alumnos WHERE dni IS NULL;

-- Promedios
SELECT * FROM v_promedio_por_curso;
```

## Importante: el sistema todavía no usa v3

`config/config.php` sigue apuntando a `escuela_artistica`, y las consultas del código
usan nombres del esquema actual (`profesores`, `curso_docentes`, `notas.valor`,
`matriculas.id_curso`, etc.).

Para cambiar el sistema a v3 habría que:

1. Adaptar las consultas y vistas a los nombres nuevos.
2. Decidir si el rol se llama **Docente** o **Profesor** en la interfaz.
3. Actualizar `db_name` en `config/config.php`.
4. Volver a ejecutar el smoke test de los 4 roles.

Hasta entonces, v3 es una base de revisión.
