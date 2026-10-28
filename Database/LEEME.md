# Carpeta Database

Esta carpeta contiene una **copia portable de la base de datos** para revisarla o
importarla. No es la base viva del sistema.

## Archivo

```text
Database/escuela_artistica.sql
```

Incluye:

- Estructura de las 15 tablas.
- Datos actuales (120 fichas de alumno, usuarios, cursos, etc.).
- La vista `v_promedio_por_curso`.
- Codificación `utf8mb4`.

## Importante

La base real sigue viviendo en MariaDB:

```text
C:\xampp\mysql\data\escuela_artistica\
```

No muevas ni edites esa carpeta con MariaDB encendido: se puede dañar. Este `.sql`
es la forma segura de tener una copia dentro del proyecto.

## Cómo revisarla

Abre `escuela_artistica.sql` en VS Code. Verás:

1. La estructura de cada tabla (`CREATE TABLE`).
2. Los índices y claves foráneas.
3. Los datos (`INSERT INTO`).
4. La vista de promedios al final.

## Cómo importarla (solo en una base de prueba)

1. Abre phpMyAdmin.
2. Crea una base llamada, por ejemplo, `escuela_artistica_prueba`.
3. Selecciónala y entra en **Importar**.
4. Elige `Database/escuela_artistica.sql`.
5. Ejecuta la importación.

El archivo contiene `DROP TABLE`, así que **no lo importes sobre la base real** si no
quieres reemplazar sus datos.

## Privacidad

Este `.sql` contiene datos personales y contraseñas cifradas. Por eso está excluido de
Git con `.gitignore` y no se sube a GitHub. La carpeta también bloquea el acceso por
HTTP con su propio `.htaccess`.
