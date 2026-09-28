# Tabla de pruebas

Dos fuentes de prueba conviven en este proyecto: una suite automatizada de
escenarios de negocio (`tests/escenarios.php`, ejecuta los controladores
reales contra una copia de la base) y rondas de prueba manual sobre la
aplicación corriendo, hechas durante el desarrollo. Se listan ambas.

## 1. Pruebas automatizadas (`tests/escenarios.php`)

Cómo correrlas: ver "Pruebas de escenarios" en el [`README.md`](../README.md).
Última ejecución: **2026-09-28, 71/72 OK** contra una copia (`testdb_audit`)
de la base de desarrollo. El único caso no ejecutado (B3-B5) es una
precondición de datos de esa copia puntual (no hay, en ese snapshot, un tutor
habilitado sin ofertas para probar turnos), no un defecto del código.

| # | Módulo | Escenario | Resultado esperado | Resultado (28/09/2026) |
|---|---|---|---|---|
| A1-A2 | Cuentas y sesión | Cuenta desactivada pierde la sesión; el rol se releen desde la base; el admin no puede autodesactivarse | Se cumple en los 3 casos | ✔ OK |
| A5 | Cuentas | No se puede dejar el sistema sin administrador activo | Bloqueado | ✔ OK |
| A6-A7 | Cuentas / Tutores | Un tutor con grupos vigentes no se puede desactivar; uno sin grupos sí, y deja de ofrecer turnos al motor; se reactiva y vuelve a ofrecerlos | Se cumple en los 3 casos | ✔ OK |
| A8 | Cuentas / Estudiantes | Un estudiante inscrito en grupo vigente no se desactiva; uno solo en espera sí | Se cumple | ✔ OK |
| A10-A11 | Integridad histórica | El historial de un estudiante desactivado sigue intacto (FK RESTRICT, db/041); una materia sin uso sí se puede eliminar | Se cumple | ✔ OK |
| B1-B2 | Ofertas por período | Un tutor no puede ofrecer más materias que el tope del período (`max_grupos_tutor`); el mensaje refleja el tope exacto | Se cumple | ✔ OK |
| B3-B5 | Ofertas por período | Dos tutores no pueden tomar el mismo turno de la misma materia; la coordinación aprueba; un tutor puede retirar una materia sin grupos | Sin precondición de datos en la copia | ✘ SKIP (dato, no código) |
| C4 | Grupo lleno | El cuarto estudiante de un grupo con cupo 3 queda en espera y dispara la alerta `cupo_completo` | Se cumple | ✔ OK |
| C5 | Grupo lleno | No se puede quitar una materia de la oferta si tiene un grupo vigente | Bloqueado | ✔ OK |
| E1, E3-E7 | Ampliación por demanda (quórum) | Un grupo lleno no abre otro en el mismo turno solo; un segundo tutor no puede tomar el turno por su cuenta; la coordinación sí puede proponer ampliarlo; con menos del quórum (3) no se abre; nunca dos grupos del mismo tutor en el mismo turno | Se cumple en los 6 casos | ✔ OK |
| F1, F3-F9 | Dividir grupo lleno (db/042) | Solo se divide un grupo lleno sin asistencia registrada; se trasladan los últimos inscritos; no se duplica una propuesta pendiente; rechazar exige motivo; una propuesta retirada no se puede aceptar; solo el tutor propuesto responde; al aceptar, quedan dos grupos parejos (4 y 4) con inscripciones y asistencia correctamente separadas | Se cumple en los 9 casos | ✔ OK |
| G2-G3 | Inscripción tardía | Al 5.º día tras la primera sesión el motor y la coordinación ya no inscriben en ese grupo; una sesión cancelada no cuenta como inicio de la ventana | Se cumple | ✔ OK |
| D1-D4 | Ciclo del período | No se activa un segundo período del mismo tipo con otro ya activo; al cerrar, la demanda pendiente vence y no quedan grupos vigentes; un período cerrado no se reactiva; al activar el siguiente, las ofertas anteriores no se arrastran (cada tutor renueva) | Se cumple en los 4 casos | ✔ OK |

## 2. Pruebas manuales sobre la aplicación corriendo

| # | Módulo | Escenario / pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| M1 | Entorno local | Levantar con `php -S router.php` y usar dos pestañas | Se esperaría que sirva ambas | ✘ Se bloqueaba ("se cae todo al recargar"): el servidor embebido de PHP atiende una sola conexión. Corregido migrando a Apache XAMPP con vhost en `:8000` (27/09/2026). |
| M2 | Rutas / Apache | Abrir "Ofertas de materias" (`/tutores/ofertas/`) tras el redeploy | HTTP 200 | ✘ 404 (faltaba el `RewriteRule` en `deploy/docker/apache-vhost.conf` y `deploy/apache/tecnologiasweb.conf`). Corregido en ambos vhosts (27/09/2026). |
| M3 | Sesión / permisos | Iniciar sesión con una cuenta, volver atrás con el navegador, seguir navegando | Ver la página propia del rol | ✘ Devolvía 403 al usar varias cuentas en el mismo navegador (una sola sesión por navegador). Se corrigió `Auth::denegar()` para no dejar cabeceras a medio enviar (27/09/2026). |
| M4 | Ofertas | Un tutor reguarda su oferta ya aprobada | Debería quedar bloqueada para edición | ✘ Se podía volver a guardar. Se agregó el estado de solo lectura 🔒 y `TutorPortalController::saveMateriaConfig` devuelve `OFERTA_BLOQUEADA` (28/09/2026). |
| M5 | Inscripción manual | Buscar un estudiante para inscribirlo a mano en un grupo | Encontrarlo por nombre/usuario/registro | ✘ Buscador no encontraba coincidencias esperadas. Corregido (27/09/2026). |
| M6 | Asistencia | Tutor abre el formulario de asistencia de una sesión futura o cancelada y lo guarda vacío | Debería rechazar el guardado | ✘ Guardaba `null` (sin error) y dejaba la sesión `realizada` con 0 asistencias. **Corregido hoy** (`AsistenciaSesionController::save`, 28/09/2026): rechaza sesión futura, sesión no `programada`, y exige al menos un inscrito marcado. |
| M7 | Inscripción manual | La coordinación reintenta/duplica la inscripción manual de un mismo estudiante en un grupo (doble clic o reintento) | El cupo debería subir solo una vez | ✘ `cupo_ocupado` podía subir dos veces para una sola plaza real (INSERT...ON DUPLICATE KEY UPDATE sin verificar inscripción activa previa). **Corregido hoy** (`GruposController::inscribirManual`, 28/09/2026): valida `existsActive()` bajo el lock antes de inscribir. |
| M8 | Modalidades de Grado — defensas | Dos solicitudes casi simultáneas programan defensa de la misma etapa del mismo expediente | La segunda debería rechazarse | ✘ Ambas podían pasar la validación previa a la transacción y crear dos defensas "programada". **Corregido y verificado hoy** (`MgDefensasController::programar`, 28/09/2026): re-valida bajo el lock del expediente. |
| M9 | Cuentas — Modalidades de Grado | Desactivar un tutor que dirige una tesis vigente, o un estudiante con expediente MG activo | Debería bloquearse, como ya ocurre con grupos de tutorías | ✘ `EstadoCuenta::bloqueoDesactivar` no miraba `asignaciones_tutor_mg` ni `expedientes_mg`. **Corregido y verificado hoy** (28/09/2026). |
| M10 | Entorno / MySQL | Arrancar MariaDB tras cambios en `db/041` (crea tablas de replicación) | Debería iniciar normalmente | ✘ `multi-master.info` corrupto por líneas de la migración; ~880 archivos de canal de replicación bogus. Corregido moviendo los archivos (25/09/2026); ver `docs/` / memoria del proyecto para el detalle. |
| M11 | Entorno / MySQL | Ejecutar un `GRANT` sobre una base nueva (p. ej. para `tests/escenarios.php`) | Debería aplicarse | ✘ Fallaba con `Got error 175 "File too short"` (tabla `mysql.proxies_priv`, motor Aria, corrupta). Corregido con `mysqlcheck --repair mysql proxies_priv` (28/09/2026). |
| M12 | Sintaxis | Lint de los 233 archivos `.php` del repo (`php -l`) | 0 errores | ✔ OK (28/09/2026, antes y después de las correcciones de hoy). |
| M13 | Despliegue | `curl` a `/login.php` y `/` en el servidor Ubuntu tras el deploy | `200` y `302` respectivamente, sin errores PHP en `docker compose logs web` | ✔ OK (verificado en despliegues anteriores del servidor). |

## 3. Pendiente de probar (no cubierto todavía)

- Concurrencia real en `MgSeguimientoController::registrarReunion`: el
  control de choques de horario no se re-valida dentro de la transacción
  (mismo tipo de corrección aplicada a `MgDefensasController::programar`,
  pendiente de replicar). Bajo riesgo con un solo admin operando a la vez.
- Demo en vivo de los tres roles en el equipo real de la defensa (dominio,
  puerto, Docker) — recomendado ensayarla antes de la hora de defensa.
