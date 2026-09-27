# Plan ajustado — Módulo "Modalidades de Grado" (MVP-1)

Versión 1.1 · 2026-09-25 · Ajuste del *Plan de implementación MG v1.0* (base ENT-03) a este repositorio.

El plan original sigue siendo la referencia **funcional** (reglas RN-MG, prioridades del Coordinador, preguntas y documentos pendientes). Este documento corrige su parte **técnica**, que describía otro código (`controllers/`, `database/migrations`, `init.sql`, `csrf_validar()`, `lista_helper.php`...), y registra las decisiones tomadas al implementar.

Etiquetas: **[CONFIRMADO]** dicho por el Coordinador en ENT-03 · **[PENDIENTE]** falta validar · **[PROPUESTA]** decisión nuestra.

---

## 1. Correcciones al plan original

| Plan v1.0 | En este repositorio |
|---|---|
| `controllers/mg_<recurso>_<accion>.php` | `controller/Mg*Controller.php` (clases) + scripts `php/mg/<recurso>/<accion>.php` + vistas `views/mg/...` |
| Migraciones `database/migrations/009-014`, `init.sql`, `scripts/migrar.php` | `db/044` a `db/047`, idempotentes, montadas en `compose.yaml`. No hay `init.sql`: una base nueva se arma con la lista de `compose.yaml` |
| `csrf_validar()` | `verify_csrf_token()` (`includes/bootstrap.php`) |
| `requerirRol()` / `requerirPermiso()` con matriz en tabla | `Auth::requireAnyRole()` + `Auth::requireAction('mg.…')`: permisos **fijos por acción** en código. La matriz dinámica se eliminó a propósito en `db/017` y no se reintroduce |
| `lista_helper.php`, `validador.php`, `kpi_card.php`, `upds-theme.css` | No existen. Se usan `includes/Validation.php`, `includes/Csv.php` (ya neutraliza `= + - @` y usa `;`), las clases `stat-card` y Chart.js ya cargado por CDN en el panel |
| `NotificacionModel`, `hayCruceTutor` | `models/Notificacion.php` (`create()` con clave de evento idempotente). El control de choques de defensas se escribe nuevo en `MgDefensa` |
| **HU-019** (6 bugfixes) | **No aplica**: los archivos que cita no existen aquí. Se reemplaza por HU-019b (ver §3) |
| Tribunal = fila de `tutores` | Se mantiene [PROPUESTA]: tutor y tribunal son docentes de la tabla `tutores` con cuenta activa. [PENDIENTE] confirmar que todo tribunal esté registrado como tutor |

## 2. Decisiones de diseño (implementadas)

1. **Módulo aparte.** MG no es un "tipo de tutoría" (db/043): son expedientes individuales largos (MG1 → MG2) con tutor fijo, tribunales y defensas, agrupados por **cohorte**, no por período. Todo vive bajo `/mg/` y no modifica el módulo de tutorías.
2. **Roles nuevos** `coordinador_mg` y `auxiliar_mg` (filas en `roles`). Se crean desde *Cuentas de acceso* como cualquier cuenta. Su inicio es `/mg/`.
3. **Permisos por acción** (`Auth::canDo`), matriz de la §4 del plan original:

   | Acción | Admin | Coord. | Auxiliar | Tutor | Estudiante |
   |---|---|---|---|---|---|
   | `mg.ver` (listados, fichas, agenda) | ✔ | ✔ | ✔ | propios | propio |
   | `mg.parametros` (parámetros y plantillas) | ✔ | ✔ | – | – | – |
   | `mg.catalogo` (modalidades, cohortes, calendario) | ✔ | ✔ | – (ve) | – | – |
   | `mg.importar` | ✔ | ✔ | ✔ | – | – |
   | `mg.expediente` (crear, estado, etapa) | ✔ | ✔ | ✔ | – | – |
   | `mg.tutor` (asignar / cambiar tutor) | ✔ | ✔ | – (ve) | – | – |
   | `mg.tribunal`, `mg.defensa` | ✔ | ✔ | ✔ | – | – |
   | `mg.calificacion` | ✔ | ✔ | – (ve) | ve propias | ve si publicada |
   | `mg.documentos` (cartas, citaciones) | ✔ | ✔ | ✔ | – | – |
   | `mg.reportes` | ✔ | ✔ | ✔ | – | – |
   | `mg.bitacora` | ✔ | ✔ | – | – | – |

   [PENDIENTE] validar los cargos internos del auxiliar con el Coordinador (pregunta 1).
4. **Ninguna cifra dura**: todo número dudoso está en `parametros_mg` con su fuente y estado de evidencia, y solo produce **advertencias** (C-01 carga del tutor, anticipación de tribunales, tutor = tribunal).
5. **Historial, nunca borrado**: asignaciones de tutor y tribunales se cierran (`reemplazada`/`reemplazado`) y se crea el registro nuevo. "Una sola asignación vigente por expediente" y "un tribunal vigente por puesto" se refuerzan en la base con una columna generada + `UNIQUE`, no solo en el código.
6. **Ambiente de defensa en texto libre** (normalizado para comparar choques). Se descartó usar `espacios_tutoria`: son *categorías* de lugar, no aulas, y no sirven para detectar choques.
7. **Nota por defensa** (una por defensa, `publicada` 0/1). El promedio MG1/MG2 es **informativo** y se muestra como "provisional" (RN-MG-16). La nota por tribunal queda para cuando exista la fórmula oficial (P3).
8. **Documentos**: plantillas HTML editables en pantalla con `{{variables}}` de lista blanca, valores escapados, correlativo por tipo + año en transacción y **snapshot** inmutable de cada documento emitido. Se imprimen o guardan como PDF desde el navegador. Las plantillas iniciales dicen **[PLANTILLA PROVISIONAL]** hasta tener las reales.
9. **Bitácora** (`bitacora_mg`) desde el primer día: asignaciones, tribunales, defensas, notas, estados, parámetros y plantillas guardan antes/después, usuario e IP.
10. **Importación** CSV (separador `;` o `,`), máx. 2 MB, vista previa fila por fila, sin guardar el archivo; cada importación y cada fila quedan registradas. Reimportar no duplica. La integración directa con SATS queda en P3.

## 3. Alcance entregado (MVP-1)

| HU | Qué | Dónde |
|---|---|---|
| HU-019b | Deuda técnica real: el aviso de tipo de tutoría no aplica a los roles MG; router con prefijo `/mg/` | `router.php`, `views/layouts/header.php` |
| HU-020 | Parámetros configurables con fuente y evidencia | `/mg/parametros.php` |
| HU-021 | Roles `coordinador_mg` / `auxiliar_mg` y permisos por acción | `db/044`, `includes/Auth.php`, menú |
| HU-022 | Modalidades (5), cohortes y calendario de hitos | `/mg/cohortes/`, `/mg/modalidades.php` |
| HU-023 | Importar padrón CSV con vista previa | `/mg/importar.php` |
| HU-024 | Expedientes: listado con filtros, ficha, estado, ingreso a MG2 | `/mg/expedientes/` |
| HU-025/026 | Asignar y cambiar tutor con historial, carga visible y notificación | `/mg/expedientes/tutor.php` |
| HU-027 | Carta de asignación de tutor | `/mg/documentos/` |
| HU-028 | Tribunales por etapa con advertencias | `/mg/expedientes/tribunales.php` |
| HU-029 | Defensas con control de choques y agenda | `/mg/defensas/` |
| HU-030 | Citaciones (tribunal 1, tribunal 2, estudiante), también en lote por día | `/mg/defensas/` |
| HU-031 | Calificaciones con bitácora y publicación | `/mg/defensas/calificar.php` |
| HU-032 | Reporte imprimible por estudiante | `/mg/expedientes/reporte.php` |
| HU-033 | Reporte por cohorte con gráfico y CSV | `/mg/reportes.php` |
| — | Vistas propias: tutor "Mis tesistas", estudiante "Mi modalidad de grado" | `/mg/mis-tesistas.php`, `/mg/mi-modalidad.php` |

P2 (reuniones, informes de avance, alertas, dashboard completo) y P3 quedan como en el plan original. La tabla `calendario_mg` ya admite hitos tipo `informe`, así que C-02 (3 o 4 informes) se resolverá cargando hitos, sin tocar código.

## 4. Migraciones

| Archivo | Contenido |
|---|---|
| `db/044_mg_base.sql` | roles MG, `parametros_mg`, `modalidades_grado` (5), `cohortes_mg`, `calendario_mg`, `bitacora_mg` |
| `db/045_mg_expedientes.sql` | `expedientes_mg`, `expediente_etapas_mg`, `importaciones_mg`, `importaciones_mg_detalle` |
| `db/046_mg_tutores_documentos.sql` | `asignaciones_tutor_mg`, `plantillas_documento_mg` (3 provisionales), `documentos_generados_mg`, `contadores_documento_mg` |
| `db/047_mg_defensas.sql` | `tribunales_mg`, `defensas_mg`, `calificaciones_mg` |

Aplicar en orden con `mysql ... testdb < db/04x_*.sql` (nunca pegando el SQL en la consola).

## 5. Próximos pasos

1. Reunión con el Coordinador: `docs/analisis/preguntas-coordinador.md`.
2. Con la carta y citaciones reales, reemplazar las plantillas desde `/mg/plantillas/` (sin tocar código).
3. Sprint 5 (P2): reuniones, informes de avance, panel de alertas, dashboard del Coordinador.
