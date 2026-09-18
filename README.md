# Sistema web de apoyo academico para tutorias

Aplicacion inicial en PHP nativo con PDO, sesiones y MariaDB/MySQL.

El sistema incluye administracion de usuarios, catalogos academicos, perfiles, disponibilidad, tutorias, evaluaciones y auditoria. Luego de iniciar sesion, los modulos se encuentran en:

```text
/TecnologiasWeb/php/usuarios/
```

El boton `Desactivar` realiza una baja logica cambiando el estado a `inactivo`; no elimina el historial del usuario.

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
- `/disponibilidad/`: horarios de atencion de tutores.
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

La base `testdb` debe existir antes de importar los scripts:

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
http://tutorias.local/
```

Instalar la configuracion despues de clonar el repositorio:

```bash
sudo cp deploy/apache/tecnologiasweb.conf /etc/apache2/sites-available/tutorias.local.conf
sudo a2enmod rewrite
sudo a2ensite tutorias.local
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
APP_URL=http://tutorias.local
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

## Docker (recomendado para el servidor)

`compose.yaml` levanta la aplicacion (Apache + PHP 8.2, imagen construida desde `Dockerfile`) y MySQL 8.4 con un volumen persistente. Reemplaza al Apache y MySQL instalados en el host; BIND9 sigue en el host resolviendo `tutorias.local` hacia la IP del servidor.

Requisitos en Ubuntu:

```bash
sudo apt install -y docker.io docker-compose-v2
sudo usermod -aG docker "$USER"   # cerrar sesion y volver a entrar
```

Configuracion: el mismo `.env` de la raiz alimenta a Compose. Copiar `.env.example` y definir `DB_PASSWORD` y `MYSQL_ROOT_PASSWORD`; `DB_HOST` y `DB_PORT` se ignoran porque Compose apunta al servicio `db`. `APP_URL` dentro del contenedor queda vacio (enlaces relativos), asi el sitio responde igual por `http://tutorias.local/`, por IP o por `http://localhost:8080/` en desarrollo.

Primer arranque (aplica automaticamente las migraciones 001-006, 008 y 009 al crear el volumen):

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

El archivo `.env` local usa el puerto `3307`, que corresponde al tunel SSH hacia MySQL de Ubuntu:

```powershell
ssh -N -L 3307:127.0.0.1:3306 josue@192.168.1.8
```

En otra terminal, desde la raiz del proyecto, iniciar PHP con el router:

```powershell
C:\php\php.exe -S 127.0.0.1:8000 router.php
```

La aplicacion local se abre en `http://127.0.0.1:8000/` y el CRUD en `http://127.0.0.1:8000/usuarios/`.
