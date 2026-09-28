# Manual de usuario

Guía de uso del Sistema web de apoyo académico para tutorías, organizada por
rol. Para instalación y despliegue ver el [`README.md`](../README.md); para el
diseño técnico, [`docs/diagramas.md`](diagramas.md).

## Ingreso al sistema

- **Login:** `/login.php`, con usuario y contraseña.
- **Registro de estudiante:** `/register.php` crea una cuenta en estado
  `pendiente`; un administrador debe aprobarla en *Cuentas de acceso* antes
  del primer inicio de sesión.
- **Postulación de tutor:** `/postular-tutor.php` crea una cuenta `tutor`
  pendiente de aprobación docente (*Tutores → Pendientes*).
- Tras iniciar sesión, `/dashboard.php` muestra los accesos rápidos según el
  rol de la cuenta.
- El botón **Desactivar** en cualquier módulo hace una baja lógica (estado
  `inactivo`); nunca borra el historial. Una cuenta desactivada pierde la
  sesión en la siguiente petición.

---

## 1. Administrador

Acceso total: todos los módulos de tutorías y, si se crea una cuenta con rol
`coordinador_mg`/`auxiliar_mg`, también Modalidades de Grado.

### Catálogos y cuentas

| Módulo | Ruta | Qué hace |
|---|---|---|
| Cuentas de acceso | `/usuarios/` | Crear, editar, aprobar (estudiantes/tutores pendientes) y dar de baja usuarios. |
| Carreras | `/carreras/` | CRUD de carreras. |
| Materias | `/materias/` | CRUD de materias, carrera y modalidad requerida. |
| Períodos | `/periodos/` | Crear, activar y cerrar períodos; ver historial y observaciones. |
| Tipos de tutoría | `/tipos-tutoria/` | Nombre libre (Pregrado, Postgrado, Nivelación...); un período activo por tipo, en paralelo. El selector de la barra superior filtra el portal por tipo activo. |
| Espacios de tutoría | `/espacios/` | Catálogo de modalidades de ubicación (Aula presencial, Laboratorio, Teams, Meet, Zoom). No reserva aulas reales. |
| Permisos | `/permisos/` | Excepciones de permiso por usuario (el rol ya trae sus permisos por defecto). |

### Tutores y grupos

| Módulo | Ruta | Qué hace |
|---|---|---|
| Tutores | `/tutores/` | Perfiles, estado docente, exportar CSV. |
| Tutores pendientes | `/tutores/pendientes/` | Aprobar o rechazar postulaciones. |
| Cobertura de tutores | `/cobertura-tutores/` | Tutores sin horarios configurados en el período. |
| Ofertas de materias | `/tutores/ofertas/` | Revisar y aprobar/rechazar la oferta de turnos y modalidad de cada tutor (`tutor_materia_config`). Una oferta **aprobada** queda de solo lectura para el tutor (🔒). |
| Grupos | `/grupos/` | Revisar y aprobar un grupo formado por el motor: define ubicación (aula o enlace) y frecuencia; **dividir** un grupo lleno en dos; cambiar tutor; cancelar; ver historial. |
| Estudiantes | `/estudiantes/` | Perfiles, estado, exportar CSV. |

### Seguimiento y reportes

| Módulo | Ruta | Qué hace |
|---|---|---|
| Registro de accesos | `/accesos/` | Auditoría de inicios de sesión, filtrable por fecha y exportable a CSV. |
| Reportes de tutorías | `/reportes/campania.php` | Reportes filtrables y exportación CSV. |
| Notificaciones | (campana en la barra superior) | Alertas del sistema (p. ej. `cupo_completo`, evaluación pendiente). |

**Flujo típico (ciclo de un período):**
1. Crear/activar el período (*Períodos*) del tipo de tutoría correspondiente.
2. Los tutores ofertan sus materias (`/tutores/ofertas/`); el administrador
   las aprueba.
3. Los estudiantes piden tutoría; el motor de asignación arma grupos por
   demanda/quorum.
4. El administrador revisa y aprueba cada grupo (ubicación + frecuencia) en
   *Grupos*.
5. Si un grupo se llena, puede **dividirlo** en dos (mismo turno y días,
   nuevo tutor que debe aceptar).
6. Al cerrar el período, las ofertas no aprobadas ya no participan de la
   siguiente campaña: cada tutor debe renovarlas.

---

## 2. Tutor

Acceso a: *Mi perfil de tutor*, *Mis materias*, *Mis grupos* y *Mis
evaluaciones*.

| Módulo | Ruta | Qué hace |
|---|---|---|
| Mi perfil de tutor | `/mi-perfil-tutor/` | Datos del docente y su estado (`aprobado`/`pendiente`). |
| Mis materias | `/mis-materias/` | Ofertar una materia: elegir turnos, días y modalidad. Con la oferta **aprobada**, la vista queda de solo lectura (🔒); solo la Coordinación puede modificarla. |
| Mis grupos | `/mis-grupos/` | Ver los grupos asignados (una vez que la coordinación los aprueba), su horario, ubicación y estudiantes inscritos. Desde aquí se **acepta** un grupo nuevo generado por una división. |
| Registrar asistencia | `/tutor/asistencia.php` | Por cada sesión programada del grupo: marcar `asistio` / `no_asistio` / `parcial` / `retraso` (con minutos) por estudiante inscrito. Solo se puede registrar una sesión ya ocurrida (fecha ≤ hoy) y que siga `programada`; al guardar, la sesión pasa a `realizada`. |
| Proponer enlace | `/tutor/proponer_enlace.php` | Para un grupo virtual, proponer el enlace de la reunión (Teams/Meet/Zoom); la coordinación lo revisa. |
| Solicitudes especiales | `/tutorias/especiales.php` | Aprobar o rechazar una fecha/horario fuera de la disponibilidad publicada, pedida por un estudiante. |

**Flujo típico:**
1. Ofertar materias y turnos disponibles del período activo.
2. Esperar la aprobación de la coordinación.
3. Cuando el motor forma un grupo con esa oferta y la coordinación lo
   aprueba, aparece en *Mis grupos*.
4. Después de cada sesión, registrar la asistencia de los inscritos.

---

## 3. Estudiante

Acceso a: *Solicitar apoyo*, *Mis tutorías* y *Mis evaluaciones*.

| Módulo | Ruta | Qué hace |
|---|---|---|
| Solicitar apoyo | `/tutorias/create.php` | Pedir tutoría de una materia; entra en demanda o se asigna directo a un grupo con cupo. |
| Mis tutorías | `/mis-tutorias/` | Ver el grupo asignado (tutor, horario, ubicación) o el estado de espera. |
| Solicitud especial | `/tutorias/especial.php` | Pedir una fecha/horario fuera de la disponibilidad publicada (sujeta a aprobación del tutor). |
| Mis evaluaciones | `/mis-evaluaciones/` | Evaluar una sesión ya realizada (una sola vez por sesión). |
| Consultas de solo lectura | `/materias-disponibles/`, `/tutores-disponibles/`, `/horarios-disponibles/` | Ver qué materias, tutores y horarios hay disponibles antes de solicitar. |

**Flujo típico:**
1. Revisar materias/horarios disponibles.
2. Solicitar apoyo de una materia.
3. El motor de asignación lo une a un grupo existente con cupo o lo deja en
   espera hasta que se forme uno nuevo (quorum) o el grupo se divida.
4. Asistir a las sesiones y, tras cada una, esperar que el tutor registre su
   asistencia.
5. Evaluar la tutoría cuando el sistema lo notifique (sesión realizada).

---

## 4. Modalidades de Grado (módulo `/mg/`, aparte de tutorías)

Roles: **Coordinación** (`coordinador_mg`, acceso completo al módulo) y
**Auxiliar** (`auxiliar_mg`, apoyo con permisos reducidos — no valida
reuniones). Tutor y estudiante ven **solo lo propio** desde *Mis tesistas
(grado)* / *Mi modalidad de grado*, con su cuenta normal.

| Pantalla | Quién | Qué hace |
|---|---|---|
| Panel de grado | Coordinación/Auxiliar | Dashboard: expedientes por etapa, alertas activas. |
| Expedientes | Coordinación/Auxiliar | Alta de expediente por estudiante (modalidad, cohorte), consulta y filtro por tutor/cohorte/etapa. |
| Asignación de tutor | Coordinación | Asigna o reemplaza el tutor de un expediente; genera la carta de asignación. |
| Mis tesistas (grado) | Tutor | Ve sus expedientes vigentes; **registra reuniones** ya realizadas (fecha, asistencia de ambos). |
| Reuniones | Coordinación/Auxiliar (según `mg.validar`) | Valida, observa o corrige las reuniones registradas por los tutores. |
| Informes de avance | Coordinación/Auxiliar/Tutor | Registro por hito del calendario de la cohorte. |
| Tribunales y defensas | Coordinación/Auxiliar | Programa la defensa de la etapa (MG1/MG2) con fecha, ambiente y tribunal; controla choques de horario/ambiente/docente. |
| Calificaciones | Coordinación | Registra la nota de una defensa con bitácora. |
| Línea de tiempo | Coordinación/Auxiliar | Semáforo de hitos por cohorte. |
| Alertas (A1-A9) | Coordinación/Auxiliar | Se calculan al abrir el panel (sin cron); marcar como atendidas. |
| Importar padrón | Coordinación/Auxiliar | Carga masiva de estudiantes por CSV (ver `docs/ejemplos/padron_ejemplo.csv`). |
| Bitácora | Coordinación | Historial de acciones del módulo, filtrable y exportable a CSV. |
| Parámetros y plantillas | Coordinación | Ajustes numéricos (anticipación de tribunal, plazos) y plantillas de documento. |
| Mi modalidad de grado | Estudiante | Ve su propio expediente, etapa, tutor y notas publicadas. |

**Flujo típico (una etapa, MG1 o MG2):**
1. Coordinación registra el expediente del estudiante y le asigna tutor.
2. El tutor registra sus reuniones de seguimiento a medida que ocurren.
3. Coordinación (o el auxiliar, si tiene el permiso) valida cada reunión.
4. Se registran los informes de avance por hito del calendario.
5. Cuando corresponde, Coordinación programa la defensa (con tribunal) y,
   tras la exposición, registra la calificación.
6. El panel de Alertas avisa de plazos vencidos o incompletos en cualquier
   paso anterior.

---

## Notas generales

- Todas las acciones que cambian estado (aprobar, cancelar, desactivar,
  dividir un grupo, programar una defensa) quedan registradas en un
  historial o bitácora propios del módulo — no hay borrado físico de datos
  operativos.
- El sistema no descubre choques de horario/aula/docente por fuera de lo que
  registra: si una reserva real ocurre fuera del sistema, no se detecta.
- Todas las formas envían token CSRF y ninguna acción de escritura se hace
  por `GET`.
