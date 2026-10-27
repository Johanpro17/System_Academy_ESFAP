# TODO - Correcciones de Seguridad y Funcionalidad

## Plan de Correcciones

### Fase 1: Core de Autenticación ✅ COMPLETADO
- [x] `public/login.php` - Agregar session_start(), redirección si ya logueado, mejorar HTML
- [x] `controllers/AuthController.php` - Regenerar ID de sesión (anti session fixation), agregar exit
- [x] `public/index.php` - Redirección a dashboard si ya logueado

### Fase 2: Vistas Listar (Session + XSS) ✅ COMPLETADO
- [x] `views/estudiantes/listar.php` - Verificación de sesión + htmlspecialchars
- [x] `views/cursos/listar.php` - Verificación de sesión + htmlspecialchars
- [x] `views/matriculas/listar.php` - Verificación de sesión + htmlspecialchars
- [x] `views/profesores/listar.php` - Verificación de sesión + htmlspecialchars

### Fase 3: Vistas Crear (Session + XSS + Exit) ✅ COMPLETADO
- [x] `views/estudiantes/crear.php` - Verificación de sesión + htmlspecialchars + exit
- [x] `views/cursos/crear.php` - Verificación de sesión + exit
- [x] `views/matriculas/crear.php` - Verificación de sesión + exit
- [x] `views/profesores/crear.php` - Verificación de sesión + exit

### Fase 4: Otras Vistas ✅ COMPLETADO
- [x] `views/horarios/ver.php` - Verificación de sesión
- [x] `views/estudiantes/reportes/promedios.php` - Verificación de sesión + mensaje de error amigable
- [x] `views/reportes/generar.php` - Mejorar estructura HTML y enlaces corregidos

### Fase 5: Documentación ✅ COMPLETADO
- [x] `public/instalar.php` - Documentar como esquema oficial, advertencia sobre SQL dump incompatible

---

## Resumen de Cambios Realizados

### Seguridad Implementada:
1. **Autenticación obligatoria:** Todas las vistas ahora verifican `$_SESSION['user']` y redirigen al login si no hay sesión
2. **Protección XSS:** Todas las salidas de datos usan `htmlspecialchars()` para prevenir inyección de código JavaScript
3. **Session Fixation:** El `AuthController` ahora usa `session_regenerate_id(true)` después del login
4. **Redirecciones seguras:** Todas las redirecciones con `header()` incluyen `exit` para detener la ejecución

### Mejoras Funcionales:
1. **Login mejorado:** Formulario HTML completo con manejo de errores, redirección si ya está logueado
2. **Index protegido:** Redirige a dashboard si el usuario ya tiene sesión activa
3. **Reporte de promedios:** Maneja gracefulmente el error cuando no existe la vista `v_promedio_por_curso` (esquema simple vs dump)
4. **Instalador documentado:** Comentarios explicativos sobre qué esquema usar y por qué no usar el SQL dump

### Nota Importante sobre Base de Datos:
- **Usar:** `public/instalar.php` + `public/seed.php` → Crea el esquema SIMPLE compatible con el código PHP
- **NO usar:** `escuela_artistica (1).sql` → Esquema complejo incompatible (tablas `docentes` vs `profesores`, `matricula_cursos` separada, etc.)
- El reporte de promedios requiere importar el dump SQL completo para funcionar (incluye tabla `evaluaciones`, `notas`, vista `v_promedio_por_curso`)
