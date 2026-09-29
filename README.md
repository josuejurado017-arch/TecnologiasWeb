# Sistema web de apoyo academico para tutorias

Aplicacion inicial en PHP nativo con PDO, sesiones y MariaDB/MySQL.

El sistema incluye administracion de usuarios, catalogos academicos, perfiles, disponibilidad, tutorias, evaluaciones y auditoria. Luego de iniciar sesion, los modulos se encuentran en:

```text
/TecnologiasWeb/php/usuarios/
```

El boton `Desactivar` realiza una baja logica cambiando el estado a `inactivo`; no elimina el historial del usuario.

## Documentacion

- [`docs/diagramas.md`](docs/diagramas.md): casos de uso, modelo entidad-relacion y arquitectura.
- [`docs/manual-usuario.md`](docs/manual-usuario.md): manual de usuario por rol (Administrador, Tutor, Estudiante, Coordinacion de Modalidades de Grado).
- [`docs/tabla-pruebas.md`](docs/tabla-pruebas.md): tabla de casos de prueba.
- [`docs/analisis/plan-mg-ajustado.md`](docs/analisis/plan-mg-ajustado.md): diseno y decisiones del modulo Modalidades de Grado.

## Estructura

- `php/`: puntos de entrada principales de la aplicacion.
- `usuarios/`: puntos de entrada del CRUD de usuarios.
- `controller/`: controladores MVC.
- `models/`: modelos y consultas PDO.
- `views/`: plantillas HTML.
- `includes/`: bootstrap, autenticacion y conexion.
- `css/` y `js/`: recursos del navegador.
- `db/`: esquema, semillas y migraciones de `testdb`.
- `deploy/`: configuracion de Apache para Ubuntu.

Modulos disponibles:

- `/usuarios/`, `/roles/`, `/carreras/` y `/materias/`: administracion general.
- `/estudiantes/` y `/tutores/`: perfiles academicos y profesionales.
- `/asignaciones/`: materias asignadas a tutores.
- `/mis-materias/`: materias del tutor, turnos y modalidad (los dias de cada grupo los fija la demanda, db/033).
- `/cobertura-tutores/`: supervision del administrador de tutores sin horarios configurados.
- `/tutorias/`: solicitudes y estados de tutorias.
- `/evaluaciones/`: evaluaciones de tutorias realizadas.
- `/accesos/`: reporte de auditoria para administradores.
- `/reportes/tutorias.php`: reportes filtrables de tutorias y exportacion CSV para administradores.
- `/db/009_tutoria_slots_especiales.sql`: espacios concretos derivados de disponibilidad y solicitudes de horario especial.
- `/permisos/`: configuracion de accesos por rol y excepciones por usuario.
- `/materias-disponibles/`, `/tutores-disponibles/` y `/horarios-disponibles/`: consultas de solo lectura para estudiantes.
- `/postular-tutor.php`: postulación pública para cuentas tutor pendientes de aprobación.
- `/mi-perfil-tutor/` y `/mis-materias/`: espacio privado del tutor.
- `/tutorias/especial.php`: solicitud de una fecha y horario fuera de la disponibilidad publicada.
- `/tutorias/especiales.php`: aprobacion o rechazo de solicitudes especiales para tutores y administradores.
- `/espacios/`: espacios de tutoria (Aula presencial, Laboratorio, Teams, Meet, Zoom). Son categorias, no aulas reservadas: el sistema no conoce la ocupacion real de la universidad. Reemplazan al antiguo modulo `/aulas/` (`db/029_espacios_tutoria.sql`).
- `/grupos/ubicacion.php?grupo=N`: la coordinacion registra el aula o el enlace de cada grupo y revisa el enlace que propone el tutor; cada cambio queda en `grupo_ubicacion_historial`.
- `db/030_drop_aulas_legado.sql`: borra el catalogo `aulas_legado` que deja 029. En una base con datos reales, ejecutarlo solo despues de validar la migracion (es irreversible).

## Datos demo para pruebas

El script `db/007_demo_production_data.sql` carga datos de prueba sin borrar los existentes: cuentas, perfiles, materias, asignaciones, horarios, tutorias en todos los estados, evaluaciones y accesos historicos de los ultimos 60 dias. Puede ejecutarse nuevamente sin duplicar sus registros.

Todas las cuentas creadas por el script usan la contrasena `Demo1234!`. Los usuarios tienen el formato `tutor_demo_01` a `tutor_demo_08` y `student_demo_01` a `student_demo_24`.

En **Registro de accesos**, un administrador puede filtrar por fecha inicial y final y descargar el resultado en CSV.

Los tutores administran sus materias y horarios; los estudiantes solicitan espacios concretos derivados de la disponibilidad publicada. El sistema evita solapamientos de disponibilidad y de tutorías pendientes o confirmadas, controla las transiciones `pendiente -> confirmada -> realizada`, permite evaluar una sesión realizada una sola vez y admite solicitudes especiales sujetas a aprobación del tutor.

El registro publico esta disponible en `/register.php` y crea solamente cuentas de estudiante. La cuenta y su perfil academico se crean en una transaccion con estado `pendiente`; un administrador debe aprobarla desde **Cuentas de acceso** antes del primer inicio de sesion.

Los roles iniciales son `administrador`, `tutor` y `estudiante`. El administrador conserva acceso total para no bloquear la configuracion. Los permisos de los otros roles se heredan desde `permisos_rol` y pueden modificarse por usuario desde `permisos_usuario`; si no existe una excepcion, se aplica el permiso del rol.

## Configuracion local o del servidor

1. Copiar `.env.example` como `.env` en la raiz del proyecto.
2. Configurar `DB_USER=biblioteca_user` y su contrasena real.
3. No subir `.env` a GitHub.

El usuario `biblioteca_user` ya tiene permisos sobre `testdb`. La contrasena actual debe cambiarse porque fue expuesta durante la configuracion. La aplicacion no debe usar `admin_db`.

## Base de datos

**Opcion rapida (recomendada):** `db/init.sql` aplica el esquema completo (equivalente
a las migraciones 001-049, sin las de datos demo/prueba 007, 027, 036 y 038) en un
solo paso:

```bash
mysql -u administrador_mysql -p -e "CREATE DATABASE IF NOT EXISTS testdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u administrador_mysql -p testdb < db/init.sql
```

**Opcion paso a paso** (util para entender o auditar cada cambio): la base `testdb`
debe existir antes de importar los scripts, en orden:

```bash
# Usar una cuenta administrativa para crear tablas y relaciones.
mysql -u administrador_mysql -p testdb < db/001_schema.sql
# Usar el usuario de la aplicacion para cargar configuracion y permisos.
mysql -u biblioteca_user -p testdb < db/002_seed.sql
mysql -u biblioteca_user -p testdb < db/003_permissions.sql
mysql -u biblioteca_user -p testdb < db/004_student_registration.sql
mysql -u biblioteca_user -p testdb < db/005_student_catalog_permissions.sql
mysql -u biblioteca_user -p testdb < db/006_tutor_permissions.sql
mysql -u biblioteca_user -p testdb < db/008_tutorias_institucionales.sql
mysql -u biblioteca_user -p testdb < db/009_tutoria_slots_especiales.sql
# ...continuar en orden con el resto de db/0NN_*.sql hasta 049 (ver README abajo
# para las que llevan una nota aparte: 040, 041, 042, 043, 044-049).
```

`db/007_demo_production_data.sql` es solo para entornos de desarrollo o pruebas controladas. Crea cuentas activas con una contrasena conocida y no debe ejecutarse en produccion.

Antes de activar el login, generar un hash real y descomentar el `INSERT` del administrador en `db/002_seed.sql`:

```bash
php -r "echo password_hash('cambiar-esta-clave', PASSWORD_DEFAULT), PHP_EOL;"
```

## Apache y DNS en Ubuntu

La raiz del repositorio se ubicara en:

```text
/var/www/html/TecnologiasWeb
```

La configuracion incluida en `deploy/apache/tecnologiasweb.conf` publica el proyecto mediante el dominio local y mantiene protegidos los controladores, modelos, vistas, scripts SQL y el archivo `.env`. La aplicacion se abre en:

```text
http://tutoriasupds.local/
```

Instalar la configuracion despues de clonar el repositorio:

```bash
sudo cp deploy/apache/tecnologiasweb.conf /etc/apache2/sites-available/tutoriasupds.local.conf
sudo a2enmod rewrite
sudo a2ensite tutoriasupds.local
sudo a2dissite 000-default
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Para desplegar la copia actual, ajustar el `.env` del servidor e instalar Apache en un solo paso:

```bash
sudo bash deploy/install-ubuntu.sh
```

Si el repositorio aun no existe en el servidor:

```bash
git clone https://github.com/josuejurado017-arch/TecnologiasWeb.git "$HOME/TecnologiasWeb"
cd "$HOME/TecnologiasWeb"
cp .env.example .env
sudo bash deploy/install-ubuntu.sh
```

Configurar `$HOME/TecnologiasWeb/.env` antes de ejecutar el instalador. La copia publicada en `/var/www/html/TecnologiasWeb` no se administra con Git.

En Ubuntu la base local escucha en `3306`, por lo que el `.env` del servidor debe usar:

```dotenv
APP_ENV=production
APP_URL=http://tutoriasupds.local
DB_HOST=127.0.0.1
DB_PORT=3306
```

La zona de BIND debe apuntar a la IP actual del servidor. Para esta instalacion es `192.168.1.8`:

```dns
@       IN      A       192.168.1.8
ns      IN      A       192.168.1.8
www     IN      A       192.168.1.8
```

Despues de publicar cambios desde GitHub:

```bash
cd "$HOME/TecnologiasWeb"
git pull origin main
sudo bash deploy/install-ubuntu.sh
```

Los cambios de estructura de la base se aplican ejecutando el script SQL de migracion correspondiente. Git no modifica automaticamente MySQL.

Para la regla de ofertas por campaña, aplicar `db/040_ofertas_por_periodo.sql` **una sola vez, después de 039**, sobre una copia de seguridad de la base. La migración asocia las ofertas anteriores al período activo y marca como rechazadas las aprobaciones sin grupo que excedían dos materias o repetían materia/turno; conserva los grupos y el historial. Al cerrar el período, las ofertas ya no aparecen en la siguiente campaña: cada tutor debe renovarlas.

Después de 040 aplicar `db/041_integridad_historial.sql` (idempotente) y luego reprocesar la demanda con `php db/herramientas/reprocesar_demanda.php --confirmar`, para que los estudiantes que esperaban una oferta rechazada por 040 vean su estado real. La 041 hace que el historial académico no se borre en cascada: estudiantes y tutores ya no se eliminan, se desactivan. No se puede desactivar a un tutor con grupos vigentes ni a un estudiante inscrito en uno, ni dejar el sistema sin administrador. Una cuenta desactivada pierde su sesión en la siguiente petición.

Luego aplicar `db/042_division_grupos.sql` (idempotente). Con un grupo lleno, la coordinación puede **dividirlo** desde *Revisar grupo → Dividir grupo*: elige un segundo tutor, ve la vista previa (se quedan los primeros en inscribirse, pasan los últimos y entran quienes esperaban la materia, hasta dejar dos grupos parejos) y envía la propuesta. El grupo nuevo se crea recién cuando el tutor la acepta en *Mis grupos*, con el mismo turno y los mismos días; su aula o enlace los define la coordinación. No se divide un grupo con asistencia registrada.

Luego aplicar `db/043_tipos_tutoria.sql` (idempotente). Agrega **tipos de tutoría** con nombre libre (*Períodos → Tipos de tutoría*): Pregrado, Postgrado, Nivelación... Cada período es de un tipo, y puede haber **un período activo por tipo**, así que tutorías de distinto tipo corren en paralelo. La duración máxima de un período la fija su tipo (vacío = sin tope); los períodos existentes pasan a *Pregrado*, que conserva el tope de 42 días. El selector **Tipo de tutoría** de la barra superior define qué período activo muestra todo el portal (grupos, ofertas, estudiantes, contadores, resumen). El motor de asignación no depende de ese selector: trabaja siempre sobre el período de cada solicitud o grupo.

### Modalidades de Grado (044-049)

Aplicar en orden `db/044_mg_base.sql`, `db/045_mg_expedientes.sql`, `db/046_mg_tutores_documentos.sql` y `db/047_mg_defensas.sql` (idempotentes, siempre con `mysql ... testdb < archivo.sql`). Agregan el módulo **Modalidades de Grado** en `/mg/`, separado del de tutorías: expedientes por estudiante (MG1 → MG2) agrupados por cohorte, tutor con historial y carta de asignación automática, tribunales, defensas con control de choques, citaciones, notas con bitácora, reportes por estudiante y por cohorte (CSV) e importación del padrón por CSV.

Roles nuevos: `coordinador_mg` y `auxiliar_mg` (se crean en *Cuentas de acceso*; su inicio es `/mg/`). Los permisos por acción están en `includes/Auth.php` (`Auth::canDo`). Tutor y estudiante ven lo propio en *Mis tesistas (grado)* y *Mi modalidad de grado*. Las cifras dudosas de las entrevistas son parámetros en *Parámetros y plantillas* y solo advierten. Diseño y decisiones: `docs/analisis/plan-mg-ajustado.md`; preguntas pendientes: `docs/analisis/preguntas-coordinador.md`.

`db/048_mg_seguimiento.sql` (MVP-2) agrega el seguimiento: el tutor vigente registra sus **reuniones** con asistencia de ambos (desde *Mis tesistas*), la Coordinación las valida u observa en *Reuniones*, los **informes de avance** se registran por hito de informe del calendario, y el panel de **Alertas** (A1-A9) se calcula al abrirlo, sin cron. El panel de grado es el dashboard del Coordinador; la *Línea de tiempo* de cada cohorte muestra el semáforo de hitos; la *Bitácora* filtra por usuario, acción y fechas y exporta CSV.

`db/049_mg_solicitudes.sql` agrega la **solicitud del estudiante**: desde su cuenta normal el estudiante elige modalidad, propone un tema y declara si cursa el último semestre o ya egresó y adjunta su record o certificado de notas, obligatorio (foto o PDF, hasta 8 MB; el semestre mínimo para solicitar es el parámetro `semestre_minimo_solicitud_mg`, 9 por defecto: por debajo de ese semestre la opción no aparece ni se acepta); la Coordinación (`coordinador_mg` y administrador) recibe una notificación, revisa el documento junto a los datos y el carnet del estudiante en *Solicitudes* y lo **aprueba** (crea el expediente con cohorte y etapa inicial: quien aún cursa entra a la etapa previa de talleres y el egresado a MG1), lo **observa** (el estudiante corrige y reenvía) o lo **rechaza** con motivo. Con un expediente de grado activo el estudiante trabaja solo en Modalidades de Grado: se le ocultan y bloquean tutorías y evaluaciones. Los documentos se guardan **fuera de la raíz pública** (`storage/mg_solicitudes/`, volumen `mg_storage` en Docker) y solo los sirve PHP al dueño y a la Coordinación.

**Inscripción tardía:** como en las materias de la UPDS, a un grupo se puede entrar hasta 4 días después de su primera sesión (`Grupo::DIAS_INSCRIPCION_TARDIA`). Después el motor ya no inscribe en ese grupo y la coordinación tampoco puede inscribir a mano.

### Pruebas de escenarios

`tests/escenarios.php` ejecuta los flujos reales (cuentas, ofertas por período, motor de asignación y ciclo de períodos) y verifica el resultado. Modifica datos, así que solo corre sobre una copia cuyo nombre termine en `_audit`:

```bash
mysqldump -u root --routines testdb > copia.sql
mysql -u root -e "CREATE DATABASE testdb_audit"
mysql -u root testdb_audit < copia.sql
DB_NAME=testdb_audit php tests/escenarios.php
```

## Docker (recomendado para el servidor)

`compose.yaml` levanta la aplicacion (Apache + PHP 8.2, imagen construida desde `Dockerfile`) y MySQL 8.4 con un volumen persistente. Reemplaza al Apache y MySQL instalados en el host; BIND9 sigue en el host resolviendo `tutoriasupds.local` hacia la IP del servidor.

Requisitos en Ubuntu:

```bash
sudo apt install -y docker.io docker-compose-v2
sudo usermod -aG docker "$USER"   # cerrar sesion y volver a entrar
```

Configuracion: el mismo `.env` de la raiz alimenta a Compose. Copiar `.env.example` y definir `DB_PASSWORD` y `MYSQL_ROOT_PASSWORD`; `DB_HOST` y `DB_PORT` se ignoran porque Compose apunta al servicio `db`. `APP_URL` dentro del contenedor queda vacio (enlaces relativos), asi el sitio responde igual por `http://tutoriasupds.local/`, por IP o por `http://localhost:8080/` en desarrollo.

Primer arranque (aplica automaticamente todas las migraciones de esquema al crear el volumen: 001-006, 008-026, 028-035, 037, 039-049; ver los comentarios de `compose.yaml` para el detalle de por que 007, 027, 036 y 038 quedan fuera):

```bash
cd "$HOME/TecnologiasWeb"
docker compose up -d --build
docker compose exec web php deploy/docker/create-admin.php admin 'clave-segura'
```

Cargar datos demo solo en desarrollo:

```bash
docker compose exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" testdb < db/007_demo_production_data.sql
```

Actualizar despues de un `git pull`:

```bash
docker compose up -d --build
# Si hubo una migracion nueva (010, 011...), aplicarla a mano una sola vez:
docker compose exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" testdb < db/010_xxx.sql
```

Migrar la base existente del host al contenedor (una sola vez, antes del primer `up`, si ya habia datos en el MySQL de Ubuntu):

```bash
mysqldump -u root -p testdb > testdb.sql
sudo systemctl disable --now apache2 mysql
docker compose up -d db
docker compose exec -T db mysql -uroot -p"$MYSQL_ROOT_PASSWORD" testdb < testdb.sql
docker compose up -d --build
```

En Windows, para probar la imagen sin ocupar el puerto 80, usar `WEB_PORT=8080` en `.env` y abrir `http://localhost:8080/`. El instalador `deploy/install-ubuntu.sh` se conserva como alternativa sin Docker.

## Servidor PHP local en Windows

**No usar `php -S 127.0.0.1:8000 router.php`:** el servidor embebido de PHP
atiende una sola conexion a la vez, y las conexiones keep-alive del navegador
(peor con varias pestañas) lo bloquean por completo ("se cae todo al
recargar"). Usar Apache de XAMPP con un vhost en vez del servidor embebido:

1. MySQL/MariaDB: `C:\xampp\mysql\bin\mysqld.exe --defaults-file=my.ini --standalone` (puerto `3306`, coincide con `.env`).
2. Agregar un vhost a `C:\xampp\apache\conf\extra\httpd-vhosts.conf` que apunte
   `DocumentRoot` a la raiz del repositorio en `127.0.0.1:8000`, con alias para
   `Front/`, `usuarios/`, `css/` y `js/`, y las mismas `RewriteRule` que
   `deploy/docker/apache-vhost.conf` (mismo router, sin prefijo `/php`).
3. Arrancar Apache: `C:\xampp\apache\bin\httpd.exe`.

La aplicacion local se abre en `http://127.0.0.1:8000/` y el CRUD en `http://127.0.0.1:8000/usuarios/`. Ambos procesos (mysqld y httpd) terminan cuando se cierra la terminal que los lanzo; hay que volver a iniciarlos en cada sesion de trabajo.

Alternativa: si se prefiere apuntar a un MySQL remoto por SSH en vez del local, tunelizar el puerto y ajustar `DB_PORT` en `.env`:

```powershell
ssh -N -L 3307:127.0.0.1:3306 usuario@servidor
```
