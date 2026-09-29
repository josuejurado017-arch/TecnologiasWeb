-- init.sql: esquema completo del Sistema de Tutorias + Modalidades de Grado en un solo script.
-- Generado a partir de db/001_schema.sql ... db/049_mg_solicitudes.sql, en el mismo orden y
-- exclusiones que usa compose.yaml para el primer arranque: NO incluye 007, 027, 036 ni 038
-- (datos de demostracion/prueba, no esquema; ver README seccion Docker para cargarlos aparte
-- con 'mysql ... testdb < db/007_demo_production_data.sql', solo en desarrollo/pruebas).
--
-- Uso: mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS testdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--      mysql -u root -p testdb < db/init.sql
--
-- No reemplaza el historial de migraciones (db/0NN_*.sql): sigue siendo la referencia de cada
-- cambio. Si el esquema evoluciona, regenerar este archivo agregando la migracion nueva al final.

CREATE DATABASE IF NOT EXISTS testdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE testdb;


-- ==================================================================
-- db/001_schema.sql
-- ==================================================================
-- Sistema web de apoyo academico para tutorias.
-- Ejecutar sobre la base de datos testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS roles (
  id_rol INT AUTO_INCREMENT PRIMARY KEY,
  nombre_rol VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  id_rol INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  contrasena_hash VARCHAR(255) NOT NULL,
  telefono VARCHAR(20),
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS carreras (
  id_carrera INT AUTO_INCREMENT PRIMARY KEY,
  nombre_carrera VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estudiantes (
  id_estudiante INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL UNIQUE,
  id_carrera INT NOT NULL,
  semestre TINYINT NOT NULL,
  registro_universitario VARCHAR(30) UNIQUE,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
  FOREIGN KEY (id_carrera) REFERENCES carreras(id_carrera)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tutores (
  id_tutor INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL UNIQUE,
  especialidad VARCHAR(150),
  biografia TEXT,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS materias (
  id_materia INT AUTO_INCREMENT PRIMARY KEY,
  nombre_materia VARCHAR(150) NOT NULL,
  id_carrera INT,
  FOREIGN KEY (id_carrera) REFERENCES carreras(id_carrera)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tutor_materia (
  id_tutor INT NOT NULL,
  id_materia INT NOT NULL,
  PRIMARY KEY (id_tutor, id_materia),
  FOREIGN KEY (id_tutor) REFERENCES tutores(id_tutor) ON DELETE CASCADE,
  FOREIGN KEY (id_materia) REFERENCES materias(id_materia) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS disponibilidad_tutor (
  id_disponibilidad INT AUTO_INCREMENT PRIMARY KEY,
  id_tutor INT NOT NULL,
  dia_semana ENUM('Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  FOREIGN KEY (id_tutor) REFERENCES tutores(id_tutor) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tutorias (
  id_tutoria INT AUTO_INCREMENT PRIMARY KEY,
  id_estudiante INT NOT NULL,
  id_tutor INT NOT NULL,
  id_materia INT NOT NULL,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  modalidad ENUM('presencial','virtual') NOT NULL DEFAULT 'presencial',
  lugar_o_enlace VARCHAR(200),
  estado ENUM('pendiente','confirmada','realizada','cancelada') NOT NULL DEFAULT 'pendiente',
  observaciones TEXT,
  fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante),
  FOREIGN KEY (id_tutor) REFERENCES tutores(id_tutor),
  FOREIGN KEY (id_materia) REFERENCES materias(id_materia),
  INDEX idx_tutoria_fecha (fecha),
  INDEX idx_tutoria_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evaluaciones_tutoria (
  id_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
  id_tutoria INT NOT NULL UNIQUE,
  calificacion TINYINT NOT NULL CHECK (calificacion BETWEEN 1 AND 5),
  comentario TEXT,
  fecha_evaluacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_tutoria) REFERENCES tutorias(id_tutoria) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS registro_accesos (
  id_acceso INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_origen VARCHAR(45),
  resultado ENUM('exitoso','fallido') NOT NULL,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;

-- ==================================================================
-- db/002_seed.sql
-- ==================================================================
-- Datos iniciales. Ejecutar despues de 001_schema.sql.
-- Este script no contiene una contrasena real.

USE testdb;

INSERT INTO roles (nombre_rol)
SELECT 'administrador' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'administrador');
INSERT INTO roles (nombre_rol)
SELECT 'tutor' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'tutor');
INSERT INTO roles (nombre_rol)
SELECT 'estudiante' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'estudiante');

INSERT INTO carreras (nombre_carrera)
SELECT 'Ingenieria de Sistemas'
WHERE NOT EXISTS (SELECT 1 FROM carreras WHERE nombre_carrera = 'Ingenieria de Sistemas');

INSERT INTO materias (nombre_materia, id_carrera)
SELECT 'Base de Datos I', c.id_carrera
FROM carreras c
WHERE c.nombre_carrera = 'Ingenieria de Sistemas'
  AND NOT EXISTS (SELECT 1 FROM materias WHERE nombre_materia = 'Base de Datos I');

INSERT INTO materias (nombre_materia, id_carrera)
SELECT 'Programacion I', c.id_carrera
FROM carreras c
WHERE c.nombre_carrera = 'Ingenieria de Sistemas'
  AND NOT EXISTS (SELECT 1 FROM materias WHERE nombre_materia = 'Programacion I');

INSERT INTO materias (nombre_materia, id_carrera)
SELECT 'Tecnologia Web I', c.id_carrera
FROM carreras c
WHERE c.nombre_carrera = 'Ingenieria de Sistemas'
  AND NOT EXISTS (SELECT 1 FROM materias WHERE nombre_materia = 'Tecnologia Web I');

-- Generar el hash en el servidor y reemplazar HASH_REAL antes de ejecutar:
-- php -r "echo password_hash('cambiar-esta-clave', PASSWORD_DEFAULT), PHP_EOL;"
-- INSERT INTO usuarios (id_rol, nombre, apellido, correo, usuario, contrasena_hash)
-- SELECT r.id_rol, 'Admin', 'Sistema', 'admin@tutoriasupds.local', 'admin', 'HASH_REAL'
-- FROM roles r
-- WHERE r.nombre_rol = 'administrador'
--   AND NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'admin');

-- ==================================================================
-- db/003_permissions.sql
-- ==================================================================
-- Permisos heredados por rol con excepciones individuales.
-- Ejecutar sobre testdb despues de 001_schema.sql y 002_seed.sql.

USE testdb;

CREATE TABLE IF NOT EXISTS modulos_sistema (
  id_modulo INT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(50) NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(200),
  orden SMALLINT NOT NULL DEFAULT 0,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permisos_rol (
  id_rol INT NOT NULL,
  id_modulo INT NOT NULL,
  permitido TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id_rol, id_modulo),
  FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON DELETE CASCADE,
  FOREIGN KEY (id_modulo) REFERENCES modulos_sistema(id_modulo) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permisos_usuario (
  id_usuario INT NOT NULL,
  id_modulo INT NOT NULL,
  permitido TINYINT(1) NOT NULL,
  PRIMARY KEY (id_usuario, id_modulo),
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
  FOREIGN KEY (id_modulo) REFERENCES modulos_sistema(id_modulo) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO modulos_sistema (clave, nombre, descripcion, orden) VALUES
  ('dashboard', 'Dashboard', 'Resumen general del sistema', 10),
  ('usuarios', 'Usuarios', 'Cuentas y estados de acceso', 20),
  ('roles', 'Roles', 'Roles del sistema', 30),
  ('permisos', 'Permisos', 'Acceso a modulos por rol y usuario', 40),
  ('carreras', 'Carreras', 'Catalogo de carreras', 50),
  ('materias', 'Materias', 'Catalogo de materias', 60),
  ('estudiantes', 'Estudiantes', 'Perfiles de estudiantes', 70),
  ('tutores', 'Tutores', 'Perfiles de tutores', 80),
  ('asignaciones', 'Asignaciones', 'Materias asignadas a tutores', 90),
  ('disponibilidad', 'Disponibilidad', 'Horarios de atencion', 100),
  ('tutorias', 'Tutorias', 'Solicitudes y sesiones', 110),
  ('evaluaciones', 'Evaluaciones', 'Evaluaciones de tutorias', 120),
  ('accesos', 'Registro de accesos', 'Auditoria de inicios de sesion', 130)
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre), descripcion = VALUES(descripcion), orden = VALUES(orden), estado = 'activo';

INSERT IGNORE INTO permisos_rol (id_rol, id_modulo, permitido)
SELECT r.id_rol, m.id_modulo,
       CASE
         WHEN r.nombre_rol = 'administrador' THEN 1
         WHEN r.nombre_rol = 'tutor' AND m.clave IN ('dashboard', 'disponibilidad', 'tutorias', 'evaluaciones') THEN 1
         WHEN r.nombre_rol = 'estudiante' AND m.clave IN ('dashboard', 'tutorias', 'evaluaciones') THEN 1
         ELSE 0
       END
FROM roles r
CROSS JOIN modulos_sistema m;

-- ==================================================================
-- db/004_student_registration.sql
-- ==================================================================
-- Registro publico de estudiantes y aprobacion administrativa.
-- Ejecutar sobre testdb despues de 003_permissions.sql.

USE testdb;

ALTER TABLE usuarios
  MODIFY estado ENUM('pendiente','activo','inactivo') NOT NULL DEFAULT 'activo';

-- ==================================================================
-- db/005_student_catalog_permissions.sql
-- ==================================================================
-- Permisos de consulta para estudiantes.
-- Ejecutar sobre testdb despues de 004_student_registration.sql.

USE testdb;

UPDATE permisos_rol pr
INNER JOIN roles r ON r.id_rol = pr.id_rol
INNER JOIN modulos_sistema m ON m.id_modulo = pr.id_modulo
SET pr.permitido = 1
WHERE r.nombre_rol = 'estudiante'
  AND m.clave IN ('materias', 'tutores', 'disponibilidad');

-- ==================================================================
-- db/006_tutor_permissions.sql
-- ==================================================================
-- Accesos adicionales para el rol tutor.
-- Ejecutar sobre testdb despues de 005_student_catalog_permissions.sql.

USE testdb;

UPDATE permisos_rol pr
INNER JOIN roles r ON r.id_rol = pr.id_rol
INNER JOIN modulos_sistema m ON m.id_modulo = pr.id_modulo
SET pr.permitido = 1
WHERE r.nombre_rol = 'tutor'
  AND m.clave IN ('tutores', 'asignaciones');

-- ==================================================================
-- db/008_tutorias_institucionales.sql
-- ==================================================================
-- Trazabilidad, asistencia, cancelaciones y notificaciones institucionales.
-- Ejecutar despues de 006_tutor_permissions.sql sobre testdb.

USE testdb;

ALTER TABLE tutorias
  ADD COLUMN motivo_cancelacion VARCHAR(500) NULL AFTER observaciones,
  ADD COLUMN fecha_cancelacion DATETIME NULL AFTER motivo_cancelacion,
  ADD COLUMN usuario_cancelacion INT NULL AFTER fecha_cancelacion,
  ADD INDEX idx_tutoria_tutor_estado_fecha (id_tutor, estado, fecha),
  ADD INDEX idx_tutoria_estudiante_estado_fecha (id_estudiante, estado, fecha),
  ADD INDEX idx_tutoria_materia_estado_fecha (id_materia, estado, fecha),
  ADD CONSTRAINT fk_tutoria_usuario_cancelacion
    FOREIGN KEY (usuario_cancelacion) REFERENCES usuarios(id_usuario)
    ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS historial_tutorias (
  id_historial BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tutoria INT NOT NULL,
  fecha_cambio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_usuario INT NULL,
  tipo_evento VARCHAR(40) NOT NULL,
  estado_anterior VARCHAR(20) NULL,
  estado_nuevo VARCHAR(20) NULL,
  motivo VARCHAR(500) NULL,
  datos_anteriores JSON NULL,
  datos_nuevos JSON NULL,
  CONSTRAINT fk_historial_tutoria
    FOREIGN KEY (id_tutoria) REFERENCES tutorias(id_tutoria)
    ON DELETE RESTRICT,
  CONSTRAINT fk_historial_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON DELETE SET NULL,
  INDEX idx_historial_tutoria_fecha (id_tutoria, fecha_cambio),
  INDEX idx_historial_usuario_fecha (id_usuario, fecha_cambio),
  INDEX idx_historial_tipo_fecha (tipo_evento, fecha_cambio)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asistencias_tutorias (
  id_asistencia BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tutoria INT NOT NULL UNIQUE,
  estado_asistencia VARCHAR(20) NOT NULL,
  minutos_retraso SMALLINT UNSIGNED NULL,
  observaciones VARCHAR(500) NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  id_usuario_registro INT NULL,
  CONSTRAINT fk_asistencia_tutoria
    FOREIGN KEY (id_tutoria) REFERENCES tutorias(id_tutoria)
    ON DELETE CASCADE,
  CONSTRAINT fk_asistencia_usuario
    FOREIGN KEY (id_usuario_registro) REFERENCES usuarios(id_usuario)
    ON DELETE SET NULL,
  INDEX idx_asistencia_estado (estado_asistencia),
  INDEX idx_asistencia_usuario_fecha (id_usuario_registro, fecha_registro)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notificaciones (
  id_notificacion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  id_tutoria INT NULL,
  tipo VARCHAR(40) NOT NULL,
  titulo VARCHAR(140) NOT NULL,
  mensaje VARCHAR(500) NOT NULL,
  url VARCHAR(255) NULL,
  clave_evento VARCHAR(180) NOT NULL UNIQUE,
  leida TINYINT(1) NOT NULL DEFAULT 0,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_lectura DATETIME NULL,
  CONSTRAINT fk_notificacion_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON DELETE CASCADE,
  CONSTRAINT fk_notificacion_tutoria
    FOREIGN KEY (id_tutoria) REFERENCES tutorias(id_tutoria)
    ON DELETE SET NULL,
  INDEX idx_notificacion_usuario_estado_fecha (id_usuario, leida, fecha_creacion),
  INDEX idx_notificacion_tutoria_tipo (id_tutoria, tipo)
) ENGINE=InnoDB;

-- Conserva una linea base para tutorias creadas antes de esta migracion.
INSERT INTO historial_tutorias
    (id_tutoria, fecha_cambio, tipo_evento, estado_nuevo, motivo, datos_nuevos)
SELECT t.id_tutoria,
       COALESCE(t.fecha_solicitud, CURRENT_TIMESTAMP),
       'migracion',
       t.estado,
       'Registro historico previo a la auditoria institucional',
       JSON_OBJECT(
         'fecha', t.fecha,
         'hora_inicio', t.hora_inicio,
         'hora_fin', t.hora_fin,
         'modalidad', t.modalidad,
         'id_tutor', t.id_tutor,
         'id_materia', t.id_materia
       )
FROM tutorias t
WHERE NOT EXISTS (
    SELECT 1 FROM historial_tutorias h WHERE h.id_tutoria = t.id_tutoria
);

-- ==================================================================
-- db/009_tutoria_slots_especiales.sql
-- ==================================================================
-- Espacios derivados de disponibilidad y solicitudes de horario especial.
-- Ejecutar despues de 008_tutorias_institucionales.sql.

USE testdb;

ALTER TABLE tutorias
  ADD COLUMN id_disponibilidad INT NULL AFTER id_materia,
  ADD INDEX idx_tutoria_disponibilidad (id_disponibilidad),
  ADD CONSTRAINT fk_tutoria_disponibilidad
    FOREIGN KEY (id_disponibilidad) REFERENCES disponibilidad_tutor(id_disponibilidad)
    ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS solicitudes_horario_especial (
  id_solicitud BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_estudiante INT NOT NULL,
  id_tutor INT NOT NULL,
  id_materia INT NOT NULL,
  fecha_propuesta DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  modalidad ENUM('presencial','virtual') NOT NULL DEFAULT 'presencial',
  lugar_o_enlace VARCHAR(200) NULL,
  observaciones TEXT NULL,
  estado ENUM('pendiente','aprobada','rechazada','cancelada','convertida') NOT NULL DEFAULT 'pendiente',
  respuesta_tutor VARCHAR(500) NULL,
  fecha_respuesta DATETIME NULL,
  id_tutoria INT NULL,
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_especial_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante),
  CONSTRAINT fk_especial_tutor FOREIGN KEY (id_tutor) REFERENCES tutores(id_tutor),
  CONSTRAINT fk_especial_materia FOREIGN KEY (id_materia) REFERENCES materias(id_materia),
  CONSTRAINT fk_especial_tutoria FOREIGN KEY (id_tutoria) REFERENCES tutorias(id_tutoria) ON DELETE SET NULL,
  INDEX idx_especial_tutor_estado_fecha (id_tutor, estado, fecha_propuesta),
  INDEX idx_especial_estudiante_estado_fecha (id_estudiante, estado, fecha_propuesta),
  INDEX idx_especial_materia (id_materia)
) ENGINE=InnoDB;

-- ==================================================================
-- db/010_rediseno_institucional.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 0 + Fase 1).
-- Fase 0: registro directo sin aprobacion manual.
-- Fase 1: campanas (periodos) y aulas administradas por el administrador.
-- Ejecutar despues de 009_tutoria_slots_especiales.sql sobre testdb.

USE testdb;

-- ------------------------------------------------------------------
-- Fase 0: eliminar la aprobacion manual de cuentas.
-- Las cuentas pendientes pasan a activas y las nuevas nacen activas.
-- ------------------------------------------------------------------
UPDATE usuarios SET estado = 'activo' WHERE estado = 'pendiente';

ALTER TABLE usuarios
  MODIFY COLUMN estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo';

-- ------------------------------------------------------------------
-- Fase 1: campanas de tutoria (dos por anio: enero y julio).
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS periodos (
  id_periodo INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  cupo_min_grupo TINYINT UNSIGNED NOT NULL DEFAULT 3,
  cupo_max_default TINYINT UNSIGNED NOT NULL DEFAULT 20,
  estado ENUM('borrador','activa','cerrada') NOT NULL DEFAULT 'borrador',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_periodo_nombre (nombre),
  INDEX idx_periodo_estado (estado),
  INDEX idx_periodo_fechas (fecha_inicio, fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Fase 1: aulas (fisicas y virtuales) como recurso agendable.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS aulas (
  id_aula INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  tipo ENUM('fisica','virtual') NOT NULL DEFAULT 'fisica',
  capacidad SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  ubicacion VARCHAR(200) NULL,
  enlace VARCHAR(300) NULL,
  plataforma VARCHAR(100) NULL,
  estado ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_aula_nombre (nombre),
  INDEX idx_aula_estado (estado),
  INDEX idx_aula_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Datos base: las dos campanas institucionales y aulas de ejemplo.
-- INSERT IGNORE evita duplicados por la clave unica de nombre.
-- ------------------------------------------------------------------
INSERT IGNORE INTO periodos (nombre, fecha_inicio, fecha_fin, estado) VALUES
  ('Tutorias Verano Enero 2027', '2027-01-06', '2027-01-31', 'borrador'),
  ('Tutorias Invierno 2027', '2027-07-01', '2027-08-31', 'borrador');

INSERT IGNORE INTO aulas (nombre, tipo, capacidad, ubicacion) VALUES
  ('Aula 101', 'fisica', 25, 'Bloque A - Planta baja'),
  ('Aula 204', 'fisica', 30, 'Bloque A - Segundo piso'),
  ('Laboratorio de Computo 1', 'fisica', 20, 'Bloque B - Primer piso');

INSERT IGNORE INTO aulas (nombre, tipo, capacidad, plataforma, enlace) VALUES
  ('Sala Virtual UPDS 1', 'virtual', 100, 'Google Meet', 'https://meet.google.com/upds-sala-1'),
  ('Sala Virtual UPDS 2', 'virtual', 100, 'Zoom', 'https://zoom.us/j/upds-sala-2');

-- ==================================================================
-- db/011_grupos_inscripciones.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 2: nucleo).
-- Grupos de tutoria como serie semanal, sesiones concretas, inscripciones
-- y demanda insatisfecha. Ejecutar despues de 010_rediseno_institucional.sql.

USE testdb;

-- ------------------------------------------------------------------
-- Grupo de tutoria: 1 tutor -> N estudiantes, serie semanal dentro de
-- una campana. Es la unidad agendable con capacidad y control de conflictos.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS grupos_tutoria (
  id_grupo INT AUTO_INCREMENT PRIMARY KEY,
  id_periodo INT NOT NULL,
  id_materia INT NOT NULL,
  id_tutor INT NOT NULL,
  id_aula INT NOT NULL,
  modalidad ENUM('presencial','virtual') NOT NULL DEFAULT 'presencial',
  dia_semana ENUM('Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  cupo_max SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  cupo_ocupado SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  estado ENUM('formacion','confirmado','en_curso','finalizado','cancelado') NOT NULL DEFAULT 'formacion',
  motivo_estado VARCHAR(300) NULL,
  fecha_estado DATETIME NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_grupo_periodo FOREIGN KEY (id_periodo) REFERENCES periodos(id_periodo) ON DELETE CASCADE,
  CONSTRAINT fk_grupo_materia FOREIGN KEY (id_materia) REFERENCES materias(id_materia),
  CONSTRAINT fk_grupo_tutor FOREIGN KEY (id_tutor) REFERENCES tutores(id_tutor),
  CONSTRAINT fk_grupo_aula FOREIGN KEY (id_aula) REFERENCES aulas(id_aula),
  INDEX idx_grupo_periodo_materia (id_periodo, id_materia, estado),
  INDEX idx_grupo_tutor_dia (id_tutor, dia_semana),
  INDEX idx_grupo_aula_dia (id_aula, dia_semana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Sesiones concretas generadas semanalmente entre las fechas de la campana.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sesiones_tutoria (
  id_sesion INT AUTO_INCREMENT PRIMARY KEY,
  id_grupo INT NOT NULL,
  fecha DATE NOT NULL,
  estado ENUM('programada','realizada','cancelada') NOT NULL DEFAULT 'programada',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sesion_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria(id_grupo) ON DELETE CASCADE,
  UNIQUE KEY uq_sesion_grupo_fecha (id_grupo, fecha),
  INDEX idx_sesion_fecha (fecha, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Inscripcion: estudiante <-> grupo. Portadora de asistencia y evaluacion.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inscripciones (
  id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
  id_grupo INT NOT NULL,
  id_estudiante INT NOT NULL,
  estado ENUM('inscrito','lista_espera','cancelada') NOT NULL DEFAULT 'inscrito',
  origen ENUM('auto','manual_admin') NOT NULL DEFAULT 'auto',
  fecha_inscripcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inscripcion_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria(id_grupo) ON DELETE CASCADE,
  CONSTRAINT fk_inscripcion_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante) ON DELETE CASCADE,
  UNIQUE KEY uq_inscripcion_grupo_estudiante (id_grupo, id_estudiante),
  INDEX idx_inscripcion_estudiante (id_estudiante, estado),
  INDEX idx_inscripcion_grupo (id_grupo, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Demanda insatisfecha: materia solicitada en una campana sin grupo formable.
-- El administrador la usa para abrir oferta; se atiende cuando aparece un grupo.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS demanda_tutoria (
  id_demanda INT AUTO_INCREMENT PRIMARY KEY,
  id_periodo INT NOT NULL,
  id_materia INT NOT NULL,
  id_estudiante INT NOT NULL,
  estado ENUM('pendiente','atendida','cancelada') NOT NULL DEFAULT 'pendiente',
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_demanda_periodo FOREIGN KEY (id_periodo) REFERENCES periodos(id_periodo) ON DELETE CASCADE,
  CONSTRAINT fk_demanda_materia FOREIGN KEY (id_materia) REFERENCES materias(id_materia),
  CONSTRAINT fk_demanda_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante) ON DELETE CASCADE,
  UNIQUE KEY uq_demanda (id_periodo, id_materia, id_estudiante),
  INDEX idx_demanda_periodo_estado (id_periodo, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- db/012_asistencia_sesion.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 3: asistencia por sesion).
-- Asistencia registrada por sesion y por inscripcion (modelo grupal).
-- Ejecutar despues de 011_grupos_inscripciones.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS asistencias_sesion (
  id_asistencia BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_sesion INT NOT NULL,
  id_inscripcion INT NOT NULL,
  estado ENUM('asistio','no_asistio','parcial','retraso') NOT NULL,
  minutos_retraso SMALLINT UNSIGNED NULL,
  observaciones VARCHAR(500) NULL,
  id_usuario_registro INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_asis_sesion FOREIGN KEY (id_sesion) REFERENCES sesiones_tutoria(id_sesion) ON DELETE CASCADE,
  CONSTRAINT fk_asis_inscripcion FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion) ON DELETE CASCADE,
  CONSTRAINT fk_asis_usuario FOREIGN KEY (id_usuario_registro) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
  UNIQUE KEY uq_asis_sesion_inscripcion (id_sesion, id_inscripcion),
  INDEX idx_asis_estado (estado),
  INDEX idx_asis_inscripcion (id_inscripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- db/013_evaluacion_grupo.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 5: evaluacion ampliada).
-- Evaluacion por inscripcion (estudiante -> su grupo/tutor) con sub-criterios.
-- Ejecutar despues de 012_asistencia_sesion.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS evaluaciones_grupo (
  id_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
  id_inscripcion INT NOT NULL,
  calificacion_general TINYINT UNSIGNED NOT NULL,
  puntualidad TINYINT UNSIGNED NOT NULL,
  dominio TINYINT UNSIGNED NOT NULL,
  claridad TINYINT UNSIGNED NOT NULL,
  utilidad TINYINT UNSIGNED NOT NULL,
  comentario VARCHAR(1000) NULL,
  fecha_evaluacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evalg_inscripcion FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion) ON DELETE CASCADE,
  UNIQUE KEY uq_evalg_inscripcion (id_inscripcion),
  CONSTRAINT chk_evalg_general CHECK (calificacion_general BETWEEN 1 AND 5),
  CONSTRAINT chk_evalg_puntualidad CHECK (puntualidad BETWEEN 1 AND 5),
  CONSTRAINT chk_evalg_dominio CHECK (dominio BETWEEN 1 AND 5),
  CONSTRAINT chk_evalg_claridad CHECK (claridad BETWEEN 1 AND 5),
  CONSTRAINT chk_evalg_utilidad CHECK (utilidad BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- db/014_historial_grupo.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 4: historial de grupos).
-- Trazabilidad de cambios de estado, cancelaciones y reprogramaciones de grupos.
-- Ejecutar despues de 013_evaluacion_grupo.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS historial_grupo (
  id_historial BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_grupo INT NOT NULL,
  tipo_evento VARCHAR(40) NOT NULL,
  estado_anterior VARCHAR(20) NULL,
  estado_nuevo VARCHAR(20) NULL,
  id_usuario INT NULL,
  motivo VARCHAR(500) NULL,
  fecha_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_histg_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria(id_grupo) ON DELETE CASCADE,
  CONSTRAINT fk_histg_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
  INDEX idx_histg_grupo (id_grupo, fecha_evento),
  INDEX idx_histg_tipo (tipo_evento, fecha_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- db/015_poda_modelo_viejo.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 6: poda segura).
-- Elimina tablas obsoletas que ya no usa el modelo institucional.
-- Ejecutar despues de 014_historial_grupo.sql sobre testdb.
--
-- NOTA: se conservan a proposito (siguen en uso por codigo vivo):
--   - tutorias, asistencias_tutorias, evaluaciones_tutoria, historial_tutorias
--     (alimentan el dashboard y los reportes "clasicos"; su retiro exige
--      reescribir models/Dashboard.php y las vistas antiguas).
--   - permisos_usuario (motor de permisos por usuario en models/Permiso.php).
--   - tutor_materia (lo usa el motor de asignacion y "Mis materias" del tutor).

USE testdb;

-- Vestigio de un MVP de biblioteca; sin referencias en el codigo.
DROP TABLE IF EXISTS libros;

-- Flujo de horario especial: el estudiante ya no propone fecha/hora en el
-- modelo institucional (el sistema asigna el grupo automaticamente).
DROP TABLE IF EXISTS solicitudes_horario_especial;

-- ==================================================================
-- db/016_drop_modelo_individual.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 6b: retiro del modelo individual).
-- Elimina las tablas del modelo de tutoria individual, ya sin referencias en el codigo
-- (dashboard, reportes y notificaciones migrados al modelo de grupos/inscripciones).
-- Ejecutar despues de 015_poda_modelo_viejo.sql sobre testdb.
--
-- IMPORTANTE: hacer respaldo antes. Las tablas 'tutorias' y sus dependientes
-- (asistencias_tutorias, evaluaciones_tutoria, historial_tutorias) contienen datos
-- historicos/demo que NO se migran (el modelo nuevo parte de campanas).

USE testdb;

-- La tabla notificaciones se conserva; solo se suelta su FK al modelo viejo.
-- La columna id_tutoria queda como historica (nullable, sin uso nuevo).
ALTER TABLE notificaciones DROP FOREIGN KEY fk_notificacion_tutoria;

DROP TABLE IF EXISTS asistencias_tutorias;
DROP TABLE IF EXISTS evaluaciones_tutoria;
DROP TABLE IF EXISTS historial_tutorias;
DROP TABLE IF EXISTS tutorias;

-- ==================================================================
-- db/017_drop_matriz_permisos.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase: simplificacion de autorizacion).
-- Elimina la matriz de permisos dinamica. La autorizacion pasa a reglas fijas por
-- rol en codigo (includes/Auth.php: administrador/tutor/estudiante).
-- Se CONSERVA la tabla `roles` (referenciada por usuarios.id_rol, login y registro).
-- Ejecutar despues de 016_drop_modelo_individual.sql sobre testdb.

USE testdb;

-- Orden: primero las tablas hijas (FK), luego el catalogo de modulos.
DROP TABLE IF EXISTS permisos_usuario;
DROP TABLE IF EXISTS permisos_rol;
DROP TABLE IF EXISTS modulos_sistema;

-- ==================================================================
-- db/018_unique_carrera_materia.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (integridad: anti-duplicados).
-- Agrega UNIQUE al nombre de carreras y materias. La colacion utf8mb4_unicode_ci
-- es insensible a mayusculas/acentos/espacios finales, por lo que estas claves
-- bloquean variantes como "Ingenieria de Sistemas" / "INGENIERIA DE SISTEMAS".
-- Ejecutar despues de 017_drop_matriz_permisos.sql sobre testdb.
--
-- NOTA: requiere que no existan duplicados previos. En bases nuevas (Docker) no
-- los hay; en la base local ya se consolidaron manualmente antes de esta migracion.

USE testdb;

ALTER TABLE carreras ADD UNIQUE KEY uq_carrera_nombre (nombre_carrera);
ALTER TABLE materias ADD UNIQUE KEY uq_materia_nombre (nombre_materia);

-- ==================================================================
-- db/019_carnet_identidad.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (identidad unificada).
-- Agrega Carnet de Identidad (CI) a nivel de persona (usuarios), compartido por
-- administrador, tutor y estudiante. NULL a nivel BD para no romper datos demo
-- existentes; el formulario lo exige en cuentas nuevas. UNIQUE (una persona = un CI).
-- El complemento/extension es opcional y se guarda dentro del mismo texto.
-- Ejecutar despues de 018_unique_carrera_materia.sql sobre testdb.

USE testdb;

ALTER TABLE usuarios
  ADD COLUMN carnet_identidad VARCHAR(20) NULL AFTER telefono,
  ADD UNIQUE KEY uq_usuario_ci (carnet_identidad);

-- ==================================================================
-- db/020_tutor_materia_preferencias.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 1: preferencias de tutor por materia).
-- Capa adicional de filtro para el motor de asignacion automatica. NO reemplaza ni
-- modifica disponibilidad_tutor: esa tabla sigue siendo la fuente de verdad de la
-- disponibilidad real del tutor. Estas tablas solo restringen, por materia, en que
-- turnos/modalidad/sabados/cupo prefiere el tutor que se le asignen estudiantes.
-- Un tutor sin fila en tutor_materia_config conserva el comportamiento actual
-- (toda su disponibilidad_tutor sirve para cualquier materia que dicte).
-- Ejecutar despues de 019_carnet_identidad.sql sobre testdb.

USE testdb;

-- ------------------------------------------------------------------
-- Preferencia 1:1 por (tutor, materia).
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tutor_materia_config (
  id_tutor            INT NOT NULL,
  id_materia          INT NOT NULL,
  modalidad           ENUM('presencial','virtual','ambas') NOT NULL DEFAULT 'ambas',
  disponible_sabados  TINYINT(1) NOT NULL DEFAULT 0,
  cupo_recomendado    TINYINT UNSIGNED NULL,
  fecha_registro      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_tutor, id_materia),
  CONSTRAINT fk_tmc_tutor_materia FOREIGN KEY (id_tutor, id_materia)
    REFERENCES tutor_materia (id_tutor, id_materia) ON DELETE CASCADE,
  CONSTRAINT chk_tmc_cupo CHECK (cupo_recomendado IS NULL OR cupo_recomendado IN (10, 15, 20, 25))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Turnos preferidos (Lunes a Viernes) seleccionados para esa materia. Los
-- rangos horarios de cada turno viven en codigo (TutorMateriaConfig::TURNOS),
-- no en la base, para tener una sola fuente de verdad.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tutor_materia_turno (
  id_tutor    INT NOT NULL,
  id_materia  INT NOT NULL,
  turno       ENUM('Manana','Mediodia','Tarde','Noche') NOT NULL,
  PRIMARY KEY (id_tutor, id_materia, turno),
  CONSTRAINT fk_tmt_tutor_materia FOREIGN KEY (id_tutor, id_materia)
    REFERENCES tutor_materia (id_tutor, id_materia) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Franjas de sabado seleccionadas para esa materia (solo aplica si
-- tutor_materia_config.disponible_sabados = 1).
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tutor_materia_sabado (
  id_tutor    INT NOT NULL,
  id_materia  INT NOT NULL,
  franja      ENUM('08:00-10:00','10:00-12:00','14:00-16:00') NOT NULL,
  PRIMARY KEY (id_tutor, id_materia, franja),
  CONSTRAINT fk_tms_tutor_materia FOREIGN KEY (id_tutor, id_materia)
    REFERENCES tutor_materia (id_tutor, id_materia) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- db/021_disponibilidad_intervenciones.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 2: supervision de disponibilidad).
-- Trazabilidad de intervenciones administrativas excepcionales sobre la
-- disponibilidad de un tutor. La disponibilidad_tutor sigue siendo autogestion
-- del tutor; esta tabla solo registra cuando un administrador crea, edita o
-- elimina un bloque en nombre del tutor, con motivo obligatorio.
-- Mismo patron que historial_grupo (db/014_historial_grupo.sql).
-- Ejecutar despues de 020_tutor_materia_preferencias.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS disponibilidad_intervenciones (
  id_intervencion   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_disponibilidad INT NULL,
  id_tutor          INT NOT NULL,
  tipo_accion       ENUM('creacion','edicion','eliminacion') NOT NULL,
  id_usuario_admin  INT NOT NULL,
  motivo            VARCHAR(500) NOT NULL,
  fecha_evento      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dint_disponibilidad FOREIGN KEY (id_disponibilidad)
    REFERENCES disponibilidad_tutor (id_disponibilidad) ON DELETE SET NULL,
  CONSTRAINT fk_dint_tutor FOREIGN KEY (id_tutor)
    REFERENCES tutores (id_tutor) ON DELETE CASCADE,
  CONSTRAINT fk_dint_usuario_admin FOREIGN KEY (id_usuario_admin)
    REFERENCES usuarios (id_usuario),
  INDEX idx_dint_tutor (id_tutor, fecha_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- db/022_tutor_materia_turno_dias.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 2: dias especificos por turno).
-- Hasta ahora un turno seleccionado en "Mis materias -> configurar" aplicaba de
-- Lunes a Viernes en bloque. Se agrega dia_semana para que el tutor pueda acotar
-- un turno a dias especificos por materia (ej. "Noche: solo lunes y miercoles"),
-- sin duplicar el dato de disponibilidad real (disponibilidad_tutor no cambia).
-- Ejecutar despues de 021_disponibilidad_intervenciones.sql sobre testdb.

USE testdb;

ALTER TABLE tutor_materia_turno
  ADD COLUMN dia_semana ENUM('Lunes','Martes','Miercoles','Jueves','Viernes') NOT NULL DEFAULT 'Lunes' AFTER turno;

-- Ampliar la PK primero: las filas existentes (turno sin dia, que aplicaban de
-- lunes a viernes) quedaron en 'Lunes' por el DEFAULT; hay que poder insertar
-- los otros 4 dias habiles para esa misma fila antes de que el motor las lea.
ALTER TABLE tutor_materia_turno
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (id_tutor, id_materia, turno, dia_semana);

-- Preservar el comportamiento anterior: expandir cada fila ya existente a los
-- otros 4 dias habiles para no reducir silenciosamente la disponibilidad real
-- de tutores ya configurados antes de esta migracion.
INSERT INTO tutor_materia_turno (id_tutor, id_materia, turno, dia_semana)
SELECT id_tutor, id_materia, turno, dia
FROM tutor_materia_turno
CROSS JOIN (SELECT 'Martes' AS dia UNION ALL SELECT 'Miercoles' UNION ALL SELECT 'Jueves' UNION ALL SELECT 'Viernes') dias
WHERE dia_semana = 'Lunes';

-- ==================================================================
-- db/023_demanda_motivo.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase B: demanda real con motivo).
-- Hasta ahora demanda_tutoria solo guardaba "pendiente" sin decir por que: no
-- distinguia "nadie dicta esta materia" (accion: habilitar/reclutar tutor) de
-- "hay tutor pero ningun horario compatible" (accion: ampliar disponibilidad)
-- ni de "su grupo fue cancelado". Se agrega el motivo y la fecha de atencion
-- para medir demanda real, tiempo de espera y conversion demanda -> grupo.
-- Ejecutar despues de 022_tutor_materia_turno_dias.sql sobre testdb.

USE testdb;

ALTER TABLE demanda_tutoria
  ADD COLUMN motivo ENUM('sin_tutor','sin_horario','grupo_cancelado') NOT NULL DEFAULT 'sin_horario' AFTER estado,
  ADD COLUMN fecha_atencion DATETIME NULL AFTER fecha_solicitud,
  ADD INDEX idx_demanda_materia_estado (id_materia, estado);

-- Retroalimentar las filas existentes: antes de esta migracion solo podia
-- registrarse demanda en materias CON oferta (fallo de horario/aula), asi que
-- el DEFAULT 'sin_horario' es correcto salvo que hoy la materia ya no tenga
-- ningun tutor habilitado con disponibilidad.
UPDATE demanda_tutoria d
SET d.motivo = 'sin_tutor'
WHERE d.estado = 'pendiente'
  AND NOT EXISTS (
      SELECT 1
      FROM tutor_materia tm
      INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
      INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND u.estado = 'activo'
      INNER JOIN disponibilidad_tutor dt ON dt.id_tutor = t.id_tutor
      WHERE tm.id_materia = d.id_materia
  );

-- ==================================================================
-- db/024_grupo_dias.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 2: patrones semanales por grupo).
-- Un grupo hoy solo puede reunirse un dia a la semana (grupos_tutoria.dia_semana,
-- columna unica). Se agrega grupo_dias para soportar patrones de varios dias
-- (ej. Lunes+Miercoles+Viernes) para el MISMO grupo, mismo horario uniforme
-- (hora_inicio/hora_fin se quedan en grupos_tutoria, sin cambio).
--
-- Migracion en dos fases (mismo patron que 022_tutor_materia_turno_dias):
-- Fase A (este archivo): agrega la tabla y la puebla con el dia actual de cada
-- grupo existente. grupos_tutoria.dia_semana NO se elimina todavia: se mantiene
-- como "primer dia del patron" para no romper vistas/consultas que aun no fueron
-- migradas a leer grupo_dias. Una migracion futura (Fase B) la eliminara una vez
-- verificado que ningun codigo la sigue leyendo directamente.
-- Ejecutar despues de 023_demanda_motivo.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS grupo_dias (
  id_grupo   INT NOT NULL,
  dia_semana ENUM('Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') NOT NULL,
  PRIMARY KEY (id_grupo, dia_semana),
  CONSTRAINT fk_grupodias_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria (id_grupo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO grupo_dias (id_grupo, dia_semana)
SELECT id_grupo, dia_semana FROM grupos_tutoria;

-- ==================================================================
-- db/025_horarios_por_materia.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 2: horarios solo por materia).
-- "Mi disponibilidad" (disponibilidad_tutor, bloques globales de hora libre) se
-- elimina del portal: el motor de asignacion usa unicamente los turnos x dias y
-- franjas de sabado que el tutor configura por materia en "Mis materias"
-- (tutor_materia_config / tutor_materia_turno / tutor_materia_sabado).
--
-- Antes, una materia SIN configuracion usaba toda la disponibilidad_tutor del
-- tutor. Para no quitarle oferta a esos tutores, esta migracion convierte sus
-- bloques en configuracion por materia: cada bloque que se solapa con un turno
-- (o franja de sabado) en un dia habilita ese turno/franja ese dia. Rangos
-- iguales a TutorMateriaConfig::TURNOS / FRANJAS_SABADO. Las materias que ya
-- tenian configuracion no se tocan.
--
-- disponibilidad_tutor y disponibilidad_intervenciones se conservan (solo
-- lectura historica); una migracion posterior puede eliminarlas.
-- Ejecutar despues de 024_grupo_dias.sql sobre testdb.

USE testdb;

-- Pares (tutor, materia) sin configuracion previa: los unicos que se migran.
CREATE TEMPORARY TABLE tmp_pares_sin_config AS
SELECT tm.id_tutor, tm.id_materia
FROM tutor_materia tm
WHERE NOT EXISTS (
  SELECT 1 FROM tutor_materia_config c
  WHERE c.id_tutor = tm.id_tutor AND c.id_materia = tm.id_materia
);

INSERT IGNORE INTO tutor_materia_turno (id_tutor, id_materia, turno, dia_semana)
SELECT DISTINCT p.id_tutor, p.id_materia, t.turno, d.dia_semana
FROM tmp_pares_sin_config p
INNER JOIN disponibilidad_tutor d ON d.id_tutor = p.id_tutor
INNER JOIN (
  SELECT 'Manana' AS turno, TIME '07:30:00' AS inicio, TIME '10:30:00' AS fin
  UNION ALL SELECT 'Mediodia', TIME '11:00:00', TIME '14:00:00'
  UNION ALL SELECT 'Tarde', TIME '15:00:00', TIME '18:00:00'
  UNION ALL SELECT 'Noche', TIME '19:00:00', TIME '22:00:00'
) t ON d.hora_inicio < t.fin AND d.hora_fin > t.inicio
WHERE d.dia_semana IN ('Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes');

INSERT IGNORE INTO tutor_materia_sabado (id_tutor, id_materia, franja)
SELECT DISTINCT p.id_tutor, p.id_materia, f.franja
FROM tmp_pares_sin_config p
INNER JOIN disponibilidad_tutor d ON d.id_tutor = p.id_tutor AND d.dia_semana = 'Sabado'
INNER JOIN (
  SELECT '08:00-10:00' AS franja, TIME '08:00:00' AS inicio, TIME '10:00:00' AS fin
  UNION ALL SELECT '10:00-12:00', TIME '10:00:00', TIME '12:00:00'
  UNION ALL SELECT '14:00-16:00', TIME '14:00:00', TIME '16:00:00'
) f ON d.hora_inicio < f.fin AND d.hora_fin > f.inicio;

-- Configuracion base (modalidad 'ambas', sin cupo recomendado: mismo
-- comportamiento que antes) solo para los pares que obtuvieron algun horario.
INSERT INTO tutor_materia_config (id_tutor, id_materia, modalidad, disponible_sabados, cupo_recomendado)
SELECT p.id_tutor, p.id_materia, 'ambas',
       EXISTS (SELECT 1 FROM tutor_materia_sabado s WHERE s.id_tutor = p.id_tutor AND s.id_materia = p.id_materia),
       NULL
FROM tmp_pares_sin_config p
WHERE EXISTS (SELECT 1 FROM tutor_materia_turno x WHERE x.id_tutor = p.id_tutor AND x.id_materia = p.id_materia)
   OR EXISTS (SELECT 1 FROM tutor_materia_sabado s WHERE s.id_tutor = p.id_tutor AND s.id_materia = p.id_materia);

DROP TEMPORARY TABLE tmp_pares_sin_config;

-- ==================================================================
-- db/026_tutor_materia_patron.sql
-- ==================================================================
-- Rediseno institucional de tutorias UPDS (Fase 4: patron semanal por materia).
-- Revierte la matriz turno x dia introducida en 022_tutor_materia_turno_dias.sql.
--
-- Motivo (medido sobre datos reales antes de migrar): de 8 combinaciones
-- turno-materia configuradas, 6 tenian los 5 dias marcados. La granularidad por
-- dia costaba 20 checkboxes por materia y en el 75% de los casos no aportaba
-- informacion: el tutor marcaba todo. Mantener 20 checkboxes por materia
-- encarecia la pantalla sin darle nada extra al motor de asignacion.
--
-- Modelo nuevo: el tutor elige UN patron semanal por materia, y los turnos
-- seleccionados aplican a los dias de ese patron. El sabado NO entra en el
-- patron: se mantiene como opt-in aparte en tutor_materia_sabado, porque tiene
-- franjas propias que no coinciden con los rangos de turno.
-- Ejecutar despues de 025_horarios_por_materia.sql sobre testdb: esa migracion
-- siembra tutor_materia_turno a partir de disponibilidad_tutor y debe correr
-- ANTES de que esta colapse la columna dia_semana.

USE testdb;

-- ------------------------------------------------------------------
-- 1. Patron semanal a nivel (tutor, materia).
--    'uno' es el unico patron que necesita precisar el dia (patron_dia);
--    los demas derivan sus dias de codigo (TutorMateriaConfig::PATRONES).
-- ------------------------------------------------------------------
ALTER TABLE tutor_materia_config
  ADD COLUMN patron ENUM('lmv','mj','diario','uno') NOT NULL DEFAULT 'diario' AFTER modalidad,
  ADD COLUMN patron_dia ENUM('Lunes','Martes','Miercoles','Jueves','Viernes') NULL AFTER patron;

-- ------------------------------------------------------------------
-- 2. Derivar el patron de cada configuracion existente a partir de la union
--    de dias declarados en sus turnos. Ante cualquier caso que no encaje
--    limpiamente se usa 'diario': es el patron mas amplio, asi la migracion
--    nunca reduce en silencio la disponibilidad de un tutor ya configurado.
-- ------------------------------------------------------------------
UPDATE tutor_materia_config c
INNER JOIN (
    SELECT id_tutor, id_materia,
           COUNT(*) AS n_dias,
           SUM(dia_semana IN ('Lunes','Miercoles','Viernes')) AS n_lmv,
           SUM(dia_semana IN ('Martes','Jueves')) AS n_mj,
           MIN(dia_semana) AS dia_unico
    FROM (SELECT DISTINCT id_tutor, id_materia, dia_semana FROM tutor_materia_turno) d
    GROUP BY id_tutor, id_materia
) x ON x.id_tutor = c.id_tutor AND x.id_materia = c.id_materia
SET c.patron = CASE
        WHEN x.n_dias >= 5        THEN 'diario'
        WHEN x.n_dias = 1         THEN 'uno'
        WHEN x.n_dias = x.n_mj    THEN 'mj'
        WHEN x.n_dias = x.n_lmv   THEN 'lmv'
        ELSE 'diario'
    END,
    c.patron_dia = CASE WHEN x.n_dias = 1 THEN x.dia_unico ELSE NULL END;

-- ------------------------------------------------------------------
-- 3. Colapsar tutor_materia_turno a (tutor, materia, turno). No se puede
--    DROP COLUMN directo: la PK incluye dia_semana y al quitarla quedarian
--    filas duplicadas. Se reconstruye la tabla con las filas distintas.
--    La FK se suelta primero porque InnoDB exige nombres de constraint
--    unicos por esquema: sin esto, crear la tabla nueva con el mismo nombre
--    de constraint falla con errno 121.
-- ------------------------------------------------------------------
ALTER TABLE tutor_materia_turno DROP FOREIGN KEY fk_tmt_tutor_materia;
ALTER TABLE tutor_materia_turno RENAME TO tutor_materia_turno_viejo;

CREATE TABLE tutor_materia_turno (
  id_tutor    INT NOT NULL,
  id_materia  INT NOT NULL,
  turno       ENUM('Manana','Mediodia','Tarde','Noche') NOT NULL,
  PRIMARY KEY (id_tutor, id_materia, turno),
  CONSTRAINT fk_tmt_tutor_materia FOREIGN KEY (id_tutor, id_materia)
    REFERENCES tutor_materia (id_tutor, id_materia) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tutor_materia_turno (id_tutor, id_materia, turno)
SELECT DISTINCT id_tutor, id_materia, turno FROM tutor_materia_turno_viejo;

DROP TABLE tutor_materia_turno_viejo;

-- ==================================================================
-- db/028_aprobacion_tutores_grupos.sql
-- ==================================================================
-- Gobierno academico: visto bueno del administrador (coordinador academico).
--
-- 1. Habilitacion docente del tutor, separada del estado de la cuenta:
--      usuarios.estado        -> puede iniciar sesion (activo/inactivo)
--      tutores.estado_docente -> el motor puede proponerle grupos
--    Un tutor pendiente inicia sesion, completa su perfil y configura sus
--    materias, pero el motor lo ignora hasta que el administrador lo aprueba.
--
-- 2. Aprobacion por grupo: el motor ya no confirma grupos por su cuenta. Cada
--    grupo nace 'por_aprobar'; el administrador lo aprueba (pasa a formacion o
--    confirmado segun el cupo) o lo rechaza (cancelado, demanda a espera).
--
-- 3. grupo_rechazos: combinacion (tutor, materia, horario) rechazada en el
--    periodo. El motor la salta para no volver a proponer el mismo grupo.
--
-- DEFAULT 'aprobado' a proposito: los tutores existentes quedan aprobados al
-- agregar la columna, y 027_datos_operativos.sql (que inserta tutores sin esta
-- columna) sigue sembrando tutores aprobados si se vuelve a ejecutar. El
-- autorregistro (RegistroTutor) inserta 'pendiente' de forma explicita.
--
-- Compatible con MySQL 8.4 (Docker) y MariaDB 10.4 (XAMPP): MySQL no admite
-- ADD COLUMN/INDEX IF NOT EXISTS, asi que los cambios condicionales van en un
-- procedimiento temporal. Idempotente: se puede volver a ejecutar.
-- Ejecutar despues de 027_datos_operativos.sql sobre testdb.

USE testdb;

DROP PROCEDURE IF EXISTS migrar_028_aprobacion;

DELIMITER //
CREATE PROCEDURE migrar_028_aprobacion()
BEGIN
  -- ------------------------------------------------------------------
  -- 1. Habilitacion docente
  -- ------------------------------------------------------------------
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutores' AND COLUMN_NAME = 'estado_docente') THEN
    ALTER TABLE tutores
      ADD COLUMN estado_docente ENUM('pendiente','aprobado','rechazado','suspendido') NOT NULL DEFAULT 'aprobado' AFTER biografia,
      ADD COLUMN motivo_rechazo VARCHAR(500) NULL AFTER estado_docente,
      ADD COLUMN fecha_revision DATETIME NULL AFTER motivo_rechazo,
      ADD COLUMN id_revisor INT NULL AFTER fecha_revision,
      ADD INDEX idx_tutor_estado_docente (estado_docente);
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tutores' AND CONSTRAINT_NAME = 'fk_tutor_revisor') THEN
    ALTER TABLE tutores
      ADD CONSTRAINT fk_tutor_revisor FOREIGN KEY (id_revisor) REFERENCES usuarios (id_usuario) ON DELETE SET NULL;
  END IF;

  -- ------------------------------------------------------------------
  -- 2. Aprobacion por grupo (los grupos existentes ya estan en marcha: no cambian)
  -- ------------------------------------------------------------------
  ALTER TABLE grupos_tutoria
    MODIFY COLUMN estado ENUM('por_aprobar','formacion','confirmado','en_curso','finalizado','cancelado') NOT NULL DEFAULT 'por_aprobar';

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND COLUMN_NAME = 'fecha_aprobacion') THEN
    ALTER TABLE grupos_tutoria
      ADD COLUMN fecha_aprobacion DATETIME NULL AFTER fecha_estado,
      ADD COLUMN id_aprobador INT NULL AFTER fecha_aprobacion;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND CONSTRAINT_NAME = 'fk_grupo_aprobador') THEN
    ALTER TABLE grupos_tutoria
      ADD CONSTRAINT fk_grupo_aprobador FOREIGN KEY (id_aprobador) REFERENCES usuarios (id_usuario) ON DELETE SET NULL;
  END IF;
END //
DELIMITER ;

CALL migrar_028_aprobacion();
DROP PROCEDURE migrar_028_aprobacion;

CREATE TABLE IF NOT EXISTS tutor_estado_historial (
  id_historial INT NOT NULL AUTO_INCREMENT,
  id_tutor INT NOT NULL,
  estado_anterior ENUM('pendiente','aprobado','rechazado','suspendido') NULL,
  estado_nuevo ENUM('pendiente','aprobado','rechazado','suspendido') NOT NULL,
  motivo VARCHAR(500) NULL,
  id_usuario_accion INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_historial),
  KEY idx_tutor_historial (id_tutor, fecha),
  CONSTRAINT fk_tutor_historial_tutor FOREIGN KEY (id_tutor) REFERENCES tutores (id_tutor) ON DELETE CASCADE,
  CONSTRAINT fk_tutor_historial_usuario FOREIGN KEY (id_usuario_accion) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- 3. Combinaciones rechazadas (el motor no las vuelve a proponer en el periodo)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS grupo_rechazos (
  id_rechazo INT NOT NULL AUTO_INCREMENT,
  id_periodo INT NOT NULL,
  id_materia INT NOT NULL,
  id_tutor INT NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  id_grupo INT NULL,
  motivo VARCHAR(300) NOT NULL,
  id_usuario_accion INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_rechazo),
  KEY idx_rechazo_lookup (id_periodo, id_materia),
  CONSTRAINT fk_rechazo_periodo FOREIGN KEY (id_periodo) REFERENCES periodos (id_periodo) ON DELETE CASCADE,
  CONSTRAINT fk_rechazo_materia FOREIGN KEY (id_materia) REFERENCES materias (id_materia) ON DELETE CASCADE,
  CONSTRAINT fk_rechazo_tutor FOREIGN KEY (id_tutor) REFERENCES tutores (id_tutor) ON DELETE CASCADE,
  CONSTRAINT fk_rechazo_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria (id_grupo) ON DELETE SET NULL,
  CONSTRAINT fk_rechazo_usuario FOREIGN KEY (id_usuario_accion) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- db/029_espacios_tutoria.sql
-- ==================================================================
-- De "Aulas" a "Espacios de tutoria".
--
-- El sistema gestiona tutorias, no infraestructura: no conoce la ocupacion real
-- de las aulas de la UPDS, asi que deja de reservarlas.
--
-- 1. espacios_tutoria: categorias (Aula presencial, Laboratorio de computacion,
--    Microsoft Teams, Google Meet, Zoom). No tienen capacidad ni ocupacion. Hay
--    una predeterminada por modalidad: la que el motor asigna al crear un grupo.
-- 2. La modalidad del grupo sale de la configuracion academica:
--      materias.modalidad_requerida (libre/presencial/virtual) manda sobre
--      tutor_materia_config.modalidad; si queda 'ambas' decide
--      periodos.modalidad_ambas (virtual por defecto).
-- 3. La ubicacion real (aula fisica en texto o enlace por grupo) vive en
--    grupos_tutoria y la registra la coordinacion cuando corresponde; el tutor
--    puede proponer un enlace virtual (enlace_propuesto). Sin ubicacion el grupo
--    queda en "Ubicacion pendiente".
-- 4. grupo_ubicacion_historial guarda cada cambio de modalidad, espacio,
--    ubicacion o enlace, y cada propuesta del tutor.
-- 5. Los grupos existentes conservan lo que mostraban: el nombre y bloque del
--    aula pasan a su ubicacion, y el enlace de la sala virtual a su enlace.
--    grupos_tutoria.id_aula se reemplaza por id_espacio y aulas queda como
--    aulas_legado hasta 030_drop_aulas_legado.sql.
--
-- Compatible con MySQL 8.4 (Docker) y MariaDB 10.4 (XAMPP): los cambios
-- condicionales van en un procedimiento temporal porque MySQL no admite
-- ADD COLUMN IF NOT EXISTS. Idempotente: se puede volver a ejecutar.
-- Ejecutar despues de 028_aprobacion_tutores_grupos.sql sobre testdb.

USE testdb;

-- ------------------------------------------------------------------
-- 1. Catalogo de espacios
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS espacios_tutoria (
  id_espacio INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  modalidad ENUM('presencial','virtual') NOT NULL,
  descripcion VARCHAR(255) NULL,
  predeterminado TINYINT(1) NOT NULL DEFAULT 0,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_espacio),
  UNIQUE KEY uq_espacio_nombre (nombre),
  KEY idx_espacio_modalidad (modalidad, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO espacios_tutoria (nombre, modalidad, descripcion, predeterminado) VALUES
  ('Aula presencial', 'presencial', 'Tutoría en un aula de la universidad. La coordinación indica el aula concreta.', 1),
  ('Laboratorio de computación', 'presencial', 'Tutoría práctica en un laboratorio de computación.', 0),
  ('Microsoft Teams', 'virtual', 'Reunión de Microsoft Teams con enlace propio del grupo.', 0),
  ('Google Meet', 'virtual', 'Reunión de Google Meet con enlace propio del grupo.', 1),
  ('Zoom', 'virtual', 'Reunión de Zoom con enlace propio del grupo.', 0);

-- ------------------------------------------------------------------
-- 2. Historial de ubicacion
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS grupo_ubicacion_historial (
  id_historial INT NOT NULL AUTO_INCREMENT,
  id_grupo INT NOT NULL,
  accion VARCHAR(30) NOT NULL,
  modalidad_anterior ENUM('presencial','virtual') NULL,
  modalidad_nueva ENUM('presencial','virtual') NULL,
  id_espacio_anterior INT NULL,
  id_espacio_nuevo INT NULL,
  ubicacion_anterior VARCHAR(200) NULL,
  ubicacion_nueva VARCHAR(200) NULL,
  enlace_anterior VARCHAR(300) NULL,
  enlace_nuevo VARCHAR(300) NULL,
  motivo VARCHAR(300) NULL,
  id_usuario INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_historial),
  KEY idx_ubicacion_historial_grupo (id_grupo, fecha),
  CONSTRAINT fk_ubicacion_historial_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria (id_grupo) ON DELETE CASCADE,
  CONSTRAINT fk_ubicacion_historial_espacio_ant FOREIGN KEY (id_espacio_anterior) REFERENCES espacios_tutoria (id_espacio),
  CONSTRAINT fk_ubicacion_historial_espacio_nuevo FOREIGN KEY (id_espacio_nuevo) REFERENCES espacios_tutoria (id_espacio),
  CONSTRAINT fk_ubicacion_historial_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- 3. Columnas nuevas y traspaso de datos
-- ------------------------------------------------------------------
DROP PROCEDURE IF EXISTS migrar_029_espacios;

DELIMITER //
CREATE PROCEDURE migrar_029_espacios()
BEGIN
  -- Modalidad academica de la materia.
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'materias' AND COLUMN_NAME = 'modalidad_requerida') THEN
    ALTER TABLE materias
      ADD COLUMN modalidad_requerida ENUM('libre','presencial','virtual') NOT NULL DEFAULT 'libre' AFTER id_carrera;
  END IF;

  -- Regla del periodo para tutores con modalidad 'ambas'.
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'periodos' AND COLUMN_NAME = 'modalidad_ambas') THEN
    ALTER TABLE periodos
      ADD COLUMN modalidad_ambas ENUM('virtual','presencial') NOT NULL DEFAULT 'virtual' AFTER cupo_max_default;
  END IF;

  -- Espacio, ubicacion real y propuesta del tutor en el grupo.
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND COLUMN_NAME = 'id_espacio') THEN
    ALTER TABLE grupos_tutoria
      ADD COLUMN id_espacio INT NULL AFTER id_tutor,
      ADD COLUMN ubicacion VARCHAR(200) NULL AFTER modalidad,
      ADD COLUMN enlace VARCHAR(300) NULL AFTER ubicacion,
      ADD COLUMN fecha_ubicacion DATETIME NULL AFTER enlace,
      ADD COLUMN id_usuario_ubicacion INT NULL AFTER fecha_ubicacion,
      ADD COLUMN id_espacio_propuesto INT NULL AFTER id_usuario_ubicacion,
      ADD COLUMN enlace_propuesto VARCHAR(300) NULL AFTER id_espacio_propuesto,
      ADD COLUMN fecha_propuesta DATETIME NULL AFTER enlace_propuesto,
      ADD COLUMN id_usuario_propuesta INT NULL AFTER fecha_propuesta;
  END IF;

  -- Traspaso desde aulas: solo mientras exista grupos_tutoria.id_aula.
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND COLUMN_NAME = 'id_aula') THEN

    -- La modalidad guardada en el grupo manda; el aula aporta el lugar o el enlace.
    UPDATE grupos_tutoria g
    INNER JOIN aulas a ON a.id_aula = g.id_aula
    SET g.id_espacio = (
          SELECT e.id_espacio FROM espacios_tutoria e
          WHERE e.nombre = CASE
            WHEN g.modalidad = 'virtual' AND a.tipo = 'virtual' AND a.plataforma LIKE '%Zoom%' THEN 'Zoom'
            WHEN g.modalidad = 'virtual' AND a.tipo = 'virtual' AND a.plataforma LIKE '%Teams%' THEN 'Microsoft Teams'
            WHEN g.modalidad = 'virtual' THEN 'Google Meet'
            WHEN a.nombre LIKE 'Laboratorio%' THEN 'Laboratorio de computación'
            ELSE 'Aula presencial'
          END),
        g.ubicacion = IF(g.modalidad = 'presencial' AND a.tipo = 'fisica',
                         LEFT(CONCAT(a.nombre, IFNULL(CONCAT(' - ', a.ubicacion), '')), 200), NULL),
        g.enlace = IF(g.modalidad = 'virtual' AND a.tipo = 'virtual', a.enlace, NULL)
    WHERE g.id_espacio IS NULL;

    UPDATE grupos_tutoria
    SET fecha_ubicacion = fecha_registro
    WHERE fecha_ubicacion IS NULL AND (ubicacion IS NOT NULL OR enlace IS NOT NULL);

    INSERT INTO grupo_ubicacion_historial
      (id_grupo, accion, modalidad_nueva, id_espacio_nuevo, ubicacion_nueva, enlace_nuevo, motivo, fecha)
    SELECT g.id_grupo, 'migrada', g.modalidad, g.id_espacio, g.ubicacion, g.enlace,
           LEFT(CONCAT('Migrado desde el antiguo módulo de aulas (', a.nombre, ').'), 300), g.fecha_registro
    FROM grupos_tutoria g
    INNER JOIN aulas a ON a.id_aula = g.id_aula
    WHERE NOT EXISTS (SELECT 1 FROM grupo_ubicacion_historial h
                      WHERE h.id_grupo = g.id_grupo AND h.accion = 'migrada');

    IF EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND CONSTRAINT_NAME = 'fk_grupo_aula') THEN
      ALTER TABLE grupos_tutoria DROP FOREIGN KEY fk_grupo_aula;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND INDEX_NAME = 'idx_grupo_aula_dia') THEN
      ALTER TABLE grupos_tutoria DROP INDEX idx_grupo_aula_dia;
    END IF;
    ALTER TABLE grupos_tutoria DROP COLUMN id_aula;
  END IF;

  -- Red de seguridad: ningun grupo queda sin espacio (predeterminado de su modalidad).
  UPDATE grupos_tutoria g
  SET g.id_espacio = (SELECT e.id_espacio FROM espacios_tutoria e
                      WHERE e.modalidad = g.modalidad AND e.predeterminado = 1
                      ORDER BY e.id_espacio LIMIT 1)
  WHERE g.id_espacio IS NULL;

  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND CONSTRAINT_NAME = 'fk_grupo_espacio') THEN
    ALTER TABLE grupos_tutoria
      MODIFY COLUMN id_espacio INT NOT NULL,
      ADD KEY idx_grupo_espacio (id_espacio),
      ADD CONSTRAINT fk_grupo_espacio FOREIGN KEY (id_espacio) REFERENCES espacios_tutoria (id_espacio),
      ADD CONSTRAINT fk_grupo_espacio_propuesto FOREIGN KEY (id_espacio_propuesto) REFERENCES espacios_tutoria (id_espacio),
      ADD CONSTRAINT fk_grupo_usuario_ubicacion FOREIGN KEY (id_usuario_ubicacion) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
      ADD CONSTRAINT fk_grupo_usuario_propuesta FOREIGN KEY (id_usuario_propuesta) REFERENCES usuarios (id_usuario) ON DELETE SET NULL;
  END IF;

  -- El catalogo de aulas queda de respaldo hasta 030_drop_aulas_legado.sql.
  IF EXISTS (SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aulas')
     AND NOT EXISTS (SELECT 1 FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aulas_legado') THEN
    RENAME TABLE aulas TO aulas_legado;
  END IF;
END //
DELIMITER ;

CALL migrar_029_espacios();
DROP PROCEDURE migrar_029_espacios;

-- ==================================================================
-- db/030_drop_aulas_legado.sql
-- ==================================================================
-- Limpieza definitiva del antiguo modulo de aulas.
--
-- 029_espacios_tutoria.sql traspaso el lugar y el enlace de cada grupo a
-- grupos_tutoria (ubicacion / enlace) y dejo el catalogo como aulas_legado para
-- poder comparar. Ejecutar solo cuando la migracion este validada en uso real:
-- es irreversible (el respaldo previo a 029 es la unica forma de volver atras).
-- Ejecutar despues de 029_espacios_tutoria.sql sobre testdb.

USE testdb;

DROP TABLE IF EXISTS aulas_legado;

-- ==================================================================
-- db/031_ciclo_periodos.sql
-- ==================================================================
-- Ciclo de vida de los periodos de tutoria: BORRADOR -> ACTIVO -> CERRADO.
--
-- Un periodo cerrado es historial: no se edita, no se reactiva ni se elimina.
-- Solo se consulta y recibe observaciones administrativas. Las transiciones las
-- hace PeriodosController (activar, cerrar); el estado ya no se elige en un
-- formulario.
--
-- 1. periodos: quien y cuando lo activo y lo cerro, hasta cuando se puede evaluar
--    tras el cierre (plazo de gracia) y un resumen congelado del cierre.
-- 2. periodo_observaciones: notas administrativas, solo se agregan.
-- 3. sesiones_tutoria.estado 'sin_registro': sesion pasada que al cierre no tenia
--    asistencia (no se inventa asistencia ni se la da por cancelada).
--    demanda_tutoria.estado 'vencida': solicitud que el periodo cerro sin atender.
-- 4. Borrar un periodo ya no arrastra su historial: las FK hacia periodos pasan de
--    ON DELETE CASCADE a RESTRICT (grupos, demanda, rechazos).
-- 5. Los periodos que ya estaban cerrados reciben los efectos del cierre.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4 (procedimiento temporal). Idempotente.
-- Ejecutar despues de 029_espacios_tutoria.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS periodo_observaciones (
  id_observacion INT NOT NULL AUTO_INCREMENT,
  id_periodo INT NOT NULL,
  texto VARCHAR(1000) NOT NULL,
  id_usuario INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_observacion),
  KEY idx_observacion_periodo (id_periodo, fecha),
  CONSTRAINT fk_observacion_periodo FOREIGN KEY (id_periodo) REFERENCES periodos (id_periodo),
  CONSTRAINT fk_observacion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS migrar_031_periodos;

DELIMITER //
CREATE PROCEDURE migrar_031_periodos()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'periodos' AND COLUMN_NAME = 'fecha_cierre') THEN
    ALTER TABLE periodos
      ADD COLUMN fecha_activacion DATETIME NULL AFTER estado,
      ADD COLUMN id_usuario_activacion INT NULL AFTER fecha_activacion,
      ADD COLUMN fecha_cierre DATETIME NULL AFTER id_usuario_activacion,
      ADD COLUMN id_usuario_cierre INT NULL AFTER fecha_cierre,
      ADD COLUMN evaluaciones_hasta DATE NULL AFTER id_usuario_cierre,
      ADD COLUMN resumen_cierre TEXT NULL AFTER evaluaciones_hasta,
      ADD CONSTRAINT fk_periodo_usuario_activacion FOREIGN KEY (id_usuario_activacion) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
      ADD CONSTRAINT fk_periodo_usuario_cierre FOREIGN KEY (id_usuario_cierre) REFERENCES usuarios (id_usuario) ON DELETE SET NULL;
  END IF;

  -- Estados nuevos (se conservan los existentes).
  ALTER TABLE sesiones_tutoria
    MODIFY COLUMN estado ENUM('programada','realizada','cancelada','sin_registro') NOT NULL DEFAULT 'programada';
  ALTER TABLE demanda_tutoria
    MODIFY COLUMN estado ENUM('pendiente','atendida','cancelada','vencida') NOT NULL DEFAULT 'pendiente';

  -- Sin cascada desde periodos: borrar un periodo con historial debe fallar.
  IF EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_grupo_periodo' AND DELETE_RULE = 'CASCADE') THEN
    ALTER TABLE grupos_tutoria DROP FOREIGN KEY fk_grupo_periodo;
    ALTER TABLE grupos_tutoria ADD CONSTRAINT fk_grupo_periodo FOREIGN KEY (id_periodo) REFERENCES periodos (id_periodo);
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_demanda_periodo' AND DELETE_RULE = 'CASCADE') THEN
    ALTER TABLE demanda_tutoria DROP FOREIGN KEY fk_demanda_periodo;
    ALTER TABLE demanda_tutoria ADD CONSTRAINT fk_demanda_periodo FOREIGN KEY (id_periodo) REFERENCES periodos (id_periodo);
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_rechazo_periodo' AND DELETE_RULE = 'CASCADE') THEN
    ALTER TABLE grupo_rechazos DROP FOREIGN KEY fk_rechazo_periodo;
    ALTER TABLE grupo_rechazos ADD CONSTRAINT fk_rechazo_periodo FOREIGN KEY (id_periodo) REFERENCES periodos (id_periodo);
  END IF;

  -- Periodos cerrados antes de esta migracion: efectos del cierre, fechados a su fin.
  UPDATE periodos
  SET fecha_cierre = TIMESTAMP(fecha_fin, '23:59:59'),
      evaluaciones_hasta = DATE_ADD(fecha_fin, INTERVAL 7 DAY)
  WHERE estado = 'cerrada' AND fecha_cierre IS NULL;

  UPDATE demanda_tutoria d INNER JOIN periodos p ON p.id_periodo = d.id_periodo
  SET d.estado = 'vencida'
  WHERE p.estado = 'cerrada' AND d.estado = 'pendiente';

  UPDATE sesiones_tutoria s
  INNER JOIN grupos_tutoria g ON g.id_grupo = s.id_grupo
  INNER JOIN periodos p ON p.id_periodo = g.id_periodo
  SET s.estado = IF(s.fecha < DATE(p.fecha_cierre), 'sin_registro', 'cancelada')
  WHERE p.estado = 'cerrada' AND s.estado = 'programada';

  UPDATE grupos_tutoria g INNER JOIN periodos p ON p.id_periodo = g.id_periodo
  SET g.estado = IF(g.estado IN ('confirmado','en_curso')
                    OR EXISTS (SELECT 1 FROM sesiones_tutoria s WHERE s.id_grupo = g.id_grupo AND s.estado = 'realizada'),
                    'finalizado', 'cancelado'),
      g.motivo_estado = 'Cierre del período',
      g.fecha_estado = p.fecha_cierre
  WHERE p.estado = 'cerrada' AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso');

  UPDATE inscripciones i
  INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
  INNER JOIN periodos p ON p.id_periodo = g.id_periodo
  SET i.estado = 'cancelada'
  WHERE p.estado = 'cerrada' AND (i.estado = 'lista_espera' OR (g.estado = 'cancelado' AND i.estado = 'inscrito'));
END //
DELIMITER ;

CALL migrar_031_periodos();
DROP PROCEDURE migrar_031_periodos;

-- ==================================================================
-- db/032_ofertas_tutor_materia.sql
-- ==================================================================
-- Aprobacion de la oferta del tutor por materia (db/025+db/026 dejaron la
-- configuracion sin ningun filtro administrativo: en cuanto el tutor guardaba
-- turnos, el motor ya podia formar grupos con ella).
--
-- 1. tutor_materia_config.estado: pendiente/aprobado/rechazado. El motor y el
--    catalogo de "Solicitar apoyo" del estudiante solo cuentan configuraciones
--    aprobadas (TutorMateriaConfig::preferencesForMatter y sqlMateriaAprobada).
--    Las filas existentes nacen 'aprobado' (ya estaban operando); toda config
--    nueva o editada desde ahora nace 'pendiente' (la aplicacion, no el default
--    de la columna, es quien decide esto en TutorMateriaConfig::save()).
-- 2. id_espacio_sugerido: el espacio que el coordinador elige al aprobar la
--    oferta. El motor lo usa como espacio del grupo que arme desde esa oferta
--    en vez del predeterminado de la modalidad; si no hay sugerencia o ya no
--    es valida, sigue usando el predeterminado (comportamiento sin cambios).
-- 3. tutor_materia_historial: quien aprobo o rechazo, cuando y por que.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 029_espacios_tutoria.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS tutor_materia_historial (
  id_historial INT NOT NULL AUTO_INCREMENT,
  id_tutor INT NOT NULL,
  id_materia INT NOT NULL,
  estado_anterior ENUM('pendiente','aprobado','rechazado') NULL,
  estado_nuevo ENUM('pendiente','aprobado','rechazado') NOT NULL,
  motivo VARCHAR(300) NULL,
  id_usuario_accion INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_historial),
  KEY idx_oferta_historial_tutor (id_tutor, id_materia, fecha),
  CONSTRAINT fk_oferta_historial_usuario FOREIGN KEY (id_usuario_accion) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS migrar_032_ofertas;

DELIMITER //
CREATE PROCEDURE migrar_032_ofertas()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutor_materia_config' AND COLUMN_NAME = 'estado') THEN
    ALTER TABLE tutor_materia_config
      ADD COLUMN estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'aprobado' AFTER cupo_recomendado,
      ADD COLUMN id_espacio_sugerido INT NULL AFTER estado,
      ADD COLUMN motivo_rechazo VARCHAR(300) NULL AFTER id_espacio_sugerido,
      ADD COLUMN id_usuario_revision INT NULL AFTER motivo_rechazo,
      ADD COLUMN fecha_revision DATETIME NULL AFTER id_usuario_revision,
      ADD CONSTRAINT fk_config_espacio_sugerido FOREIGN KEY (id_espacio_sugerido) REFERENCES espacios_tutoria (id_espacio),
      ADD CONSTRAINT fk_config_usuario_revision FOREIGN KEY (id_usuario_revision) REFERENCES usuarios (id_usuario) ON DELETE SET NULL;
  END IF;
END //
DELIMITER ;

CALL migrar_032_ofertas();
DROP PROCEDURE migrar_032_ofertas;

-- ==================================================================
-- db/033_frecuencia_por_demanda.sql
-- ==================================================================
-- Regla institucional de frecuencia: la decide la demanda, no el tutor.
--
-- El tutor ya no elige patron semanal (LMV, MJ, diario, un dia) ni franjas de
-- sabado: solo turnos y modalidad por materia. La frecuencia de cada grupo la
-- fija el motor al crearlo (AsignacionController::createGroupForMatter):
--   * menos de 8 estudiantes -> grupo reducido: LMV por defecto; la coordinacion
--     puede cambiarlo a MJS (Martes, Jueves y Sabado) mientras el grupo esta
--     por aprobar (GruposController::cambiarFrecuencia);
--   * 8 o mas -> grupo normal: Lunes a Viernes, sin intervencion manual.
--
-- Se eliminan tutor_materia_config.patron, .patron_dia, .disponible_sabados y la
-- tabla tutor_materia_sabado. Los grupos ya creados no cambian: su patron real
-- vive en grupo_dias.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 032_ofertas_tutor_materia.sql sobre testdb.

USE testdb;

DROP PROCEDURE IF EXISTS migrar_033_frecuencia;

DELIMITER //
CREATE PROCEDURE migrar_033_frecuencia()
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutor_materia_config' AND COLUMN_NAME = 'patron') THEN
    ALTER TABLE tutor_materia_config DROP COLUMN patron;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutor_materia_config' AND COLUMN_NAME = 'patron_dia') THEN
    ALTER TABLE tutor_materia_config DROP COLUMN patron_dia;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutor_materia_config' AND COLUMN_NAME = 'disponible_sabados') THEN
    ALTER TABLE tutor_materia_config DROP COLUMN disponible_sabados;
  END IF;
END //
DELIMITER ;

CALL migrar_033_frecuencia();
DROP PROCEDURE migrar_033_frecuencia;

DROP TABLE IF EXISTS tutor_materia_sabado;

-- ==================================================================
-- db/034_oferta_sin_espacio.sql
-- ==================================================================
-- La oferta del tutor no lleva espacio: la ubicacion pertenece al grupo.
--
-- 032 agrego tutor_materia_config.id_espacio_sugerido: el coordinador elegia un
-- espacio al aprobar la oferta y el motor lo usaba en los grupos que armaba desde
-- ella. Eso mezclaba dos procesos distintos:
--   * Oferta academica = tutor + materia + turnos + modalidad (Ofertas de materias:
--     solo aprobar o rechazar).
--   * Grupo de tutoria = estudiantes + horario + espacio + ubicacion (Grupos de
--     tutoria > Revisar grupo: espacio, aula, enlace y observaciones).
-- El motor ahora crea cada grupo con el espacio predeterminado de su modalidad
-- como valor provisional (grupos_tutoria.id_espacio es obligatorio) y la
-- coordinacion define la ubicacion real al revisarlo.
--
-- Se elimina la columna y su clave foranea. Los grupos ya creados conservan su
-- espacio en grupos_tutoria. El historial de ofertas no cambia.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 033_frecuencia_por_demanda.sql sobre testdb.

USE testdb;

DROP PROCEDURE IF EXISTS migrar_034_oferta_sin_espacio;

DELIMITER //
CREATE PROCEDURE migrar_034_oferta_sin_espacio()
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutor_materia_config'
               AND CONSTRAINT_NAME = 'fk_config_espacio_sugerido' AND CONSTRAINT_TYPE = 'FOREIGN KEY') THEN
    ALTER TABLE tutor_materia_config DROP FOREIGN KEY fk_config_espacio_sugerido;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutor_materia_config'
               AND COLUMN_NAME = 'id_espacio_sugerido') THEN
    ALTER TABLE tutor_materia_config DROP COLUMN id_espacio_sugerido;
  END IF;
END //
DELIMITER ;

CALL migrar_034_oferta_sin_espacio();
DROP PROCEDURE migrar_034_oferta_sin_espacio;

-- ==================================================================
-- db/035_formacion_por_quorum.sql
-- ==================================================================
-- Formacion de grupos por quorum: estados claros de cada materia.
--
--   Interes registrado  1 a (minimo-1) estudiantes esperando la materia en un turno
--                       compatible: NO hay grupo (demanda 'esperando_companeros').
--   En formacion        grupo creado al llegar al minimo del periodo (por defecto 3),
--                       espera la revision de la coordinacion (estado 'por_aprobar').
--   Listo para revision el mismo grupo con 8 o mas estudiantes (Grupo::UMBRAL_GRUPO_NORMAL):
--                       prioridad en la bandeja y Lunes a Viernes al aprobar.
--   Confirmado          aprobado con aula o enlace; se generan las sesiones.
--   En curso            automatico al llegar la fecha de la primera sesion.
--   Finalizado          cierre del periodo.
--
-- Como ningun grupo nace con menos del minimo, desaparece el estado 'formacion'
-- posterior a la aprobacion (grupo aprobado sin llegar al minimo): los que existan
-- ya estaban aprobados y con calendario, pasan a 'confirmado'.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 034_oferta_sin_espacio.sql sobre testdb.

USE testdb;

ALTER TABLE demanda_tutoria
  MODIFY COLUMN motivo ENUM('sin_tutor','sin_horario','grupo_cancelado','esperando_companeros') NOT NULL DEFAULT 'sin_horario';

ALTER TABLE periodos
  MODIFY COLUMN cupo_min_grupo TINYINT UNSIGNED NOT NULL DEFAULT 3;

INSERT INTO historial_grupo (id_grupo, tipo_evento, estado_anterior, estado_nuevo, id_usuario, motivo)
SELECT id_grupo, 'confirmado', 'formacion', 'confirmado', NULL, 'Regla de quorum (db/035): todo grupo aprobado queda confirmado.'
FROM grupos_tutoria WHERE estado = 'formacion';

UPDATE grupos_tutoria SET estado = 'confirmado', fecha_estado = NOW() WHERE estado = 'formacion';

-- ==================================================================
-- db/037_carga_por_periodo.sql
-- ==================================================================
-- Carga academica por periodo de tutoria (regla institucional UPDS).
--
-- El periodo de tutoria es el ultimo mes de cada semestre: julio (Gestion I,
-- febrero-julio) y enero (Gestion II, agosto-enero). En ese mes el estudiante
-- tambien cursa la ultima materia del semestre y la universidad permite dos
-- materias al mes, asi que:
--   * estudiante: UNA sola tutoria por periodo (inscripcion o solicitud en
--     espera). Lo aplica AsignacionController::tutoriaDelPeriodo; no necesita
--     columna.
--   * tutor: como maximo periodos.max_grupos_tutor grupos (2 por defecto), y
--     siempre en turnos distintos (una materia por turno, db/035-036).
--   * duracion del periodo: como maximo 6 semanas (PeriodosController::validate).
--
-- Compatible con MySQL 8.4 y MariaDB 10.4 (procedimiento temporal). Idempotente.
-- Ejecutar despues de 036_datos_operativos.sql sobre testdb.

USE testdb;

DROP PROCEDURE IF EXISTS migrar_037_carga;

DELIMITER //
CREATE PROCEDURE migrar_037_carga()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'periodos' AND COLUMN_NAME = 'max_grupos_tutor') THEN
    ALTER TABLE periodos
      ADD COLUMN max_grupos_tutor TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER cupo_max_default;
  END IF;
END //
DELIMITER ;

CALL migrar_037_carga();
DROP PROCEDURE migrar_037_carga;

-- ==================================================================
-- db/039_gestion_manual_grupos.sql
-- ==================================================================
-- Gestion manual de la coordinacion sobre grupos y ofertas.
--
-- 1. Asignar tutor a una materia sin tutor: la coordinacion crea una oferta en
--    estado 'propuesta' (turnos, modalidad y cupo) y el tutor la ACEPTA o la
--    RECHAZA desde "Mis materias". Aceptada pasa directo a 'aprobado' (la propuso
--    la coordinacion) y el motor reprocesa la demanda en espera; rechazada queda
--    'rechazado' con el motivo del tutor. El motor solo usa ofertas 'aprobado'.
-- 2. Cambiar el tutor de un grupo, inscribir y retirar estudiantes a mano: no
--    necesitan columnas nuevas (inscripciones.origen ya admite 'manual_admin';
--    los eventos quedan en historial_grupo).
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente (MODIFY de ENUM ampliado).
-- Ejecutar despues de 038_datos_operativos.sql sobre testdb.

USE testdb;

ALTER TABLE tutor_materia_config
  MODIFY COLUMN estado ENUM('pendiente','aprobado','rechazado','propuesta') NOT NULL DEFAULT 'aprobado';

ALTER TABLE tutor_materia_historial
  MODIFY COLUMN estado_anterior ENUM('pendiente','aprobado','rechazado','propuesta') NULL,
  MODIFY COLUMN estado_nuevo ENUM('pendiente','aprobado','rechazado','propuesta') NOT NULL;

-- ==================================================================
-- db/040_ofertas_por_periodo.sql
-- ==================================================================
-- Cada oferta y sus turnos pertenecen a una campaña. Las ofertas legadas se
-- atribuyen únicamente al período activo; los períodos siguientes exigen renovar.
-- Ejecutar después de 039_gestion_manual_grupos.sql.
USE testdb;

ALTER TABLE tutor_materia_config
  ADD COLUMN id_periodo INT NULL AFTER id_materia;
UPDATE tutor_materia_config SET id_periodo =
  (SELECT id_periodo FROM periodos WHERE estado = 'activa' ORDER BY fecha_inicio DESC LIMIT 1);
-- Si se migra sin campaña activa, conservar las ofertas como historial del
-- período más reciente; nunca se activan en una campaña futura.
UPDATE tutor_materia_config SET id_periodo =
  (SELECT id_periodo FROM periodos ORDER BY fecha_inicio DESC LIMIT 1)
  WHERE id_periodo IS NULL;

ALTER TABLE tutor_materia_turno ADD COLUMN id_periodo INT NULL AFTER id_materia;
UPDATE tutor_materia_turno tt INNER JOIN tutor_materia_config c
  ON c.id_tutor = tt.id_tutor AND c.id_materia = tt.id_materia
  SET tt.id_periodo = c.id_periodo;

ALTER TABLE tutor_materia_config DROP PRIMARY KEY,
  MODIFY id_periodo INT NOT NULL,
  ADD PRIMARY KEY (id_tutor, id_materia, id_periodo),
  ADD KEY idx_config_periodo (id_periodo, estado),
  ADD CONSTRAINT fk_config_periodo FOREIGN KEY (id_periodo) REFERENCES periodos(id_periodo);
ALTER TABLE tutor_materia_turno DROP PRIMARY KEY,
  MODIFY id_periodo INT NOT NULL,
  ADD PRIMARY KEY (id_tutor, id_materia, id_periodo, turno),
  ADD CONSTRAINT fk_turno_periodo FOREIGN KEY (id_periodo) REFERENCES periodos(id_periodo);

ALTER TABLE tutor_materia_historial
  ADD COLUMN id_periodo INT NULL AFTER id_materia,
  ADD KEY idx_historial_periodo (id_periodo);
UPDATE tutor_materia_historial h SET id_periodo =
  (SELECT c.id_periodo FROM tutor_materia_config c
   WHERE c.id_tutor = h.id_tutor AND c.id_materia = h.id_materia LIMIT 1);

-- Normalizar las aprobaciones legadas que ya superaban el límite. Priorizar
-- materias con grupos vigentes; una oferta con grupo no se desactiva a mitad
-- de campaña. Las demás permanecen como registro rechazado, no se borran.
CREATE TEMPORARY TABLE ofertas_excedentes AS
 SELECT c.id_tutor, c.id_materia, c.id_periodo,
        ROW_NUMBER() OVER (PARTITION BY c.id_tutor, c.id_periodo
          ORDER BY (SELECT COUNT(*) FROM grupos_tutoria g WHERE g.id_tutor = c.id_tutor
              AND g.id_materia = c.id_materia AND g.id_periodo = c.id_periodo AND g.estado <> 'cancelado') DESC,
              c.fecha_revision, c.id_materia) AS orden
 FROM tutor_materia_config c WHERE c.estado = 'aprobado';
UPDATE tutor_materia_config c INNER JOIN ofertas_excedentes x
 ON x.id_tutor = c.id_tutor AND x.id_materia = c.id_materia AND x.id_periodo = c.id_periodo
 SET c.estado = 'rechazado', c.motivo_rechazo = 'Excede el máximo de dos materias por período.'
 WHERE x.orden > 2 AND NOT EXISTS
   (SELECT 1 FROM grupos_tutoria g WHERE g.id_tutor = c.id_tutor AND g.id_materia = c.id_materia
     AND g.id_periodo = c.id_periodo AND g.estado <> 'cancelado');
DROP TEMPORARY TABLE ofertas_excedentes;

CREATE TEMPORARY TABLE turnos_repetidos AS
 SELECT tt.id_tutor, tt.id_materia, tt.id_periodo, tt.turno,
        ROW_NUMBER() OVER (PARTITION BY tt.id_periodo, tt.id_materia, tt.turno
          ORDER BY (SELECT COUNT(*) FROM grupos_tutoria g WHERE g.id_tutor = tt.id_tutor
              AND g.id_materia = tt.id_materia AND g.id_periodo = tt.id_periodo AND g.estado <> 'cancelado') DESC,
              c.fecha_revision, tt.id_tutor) AS orden
 FROM tutor_materia_turno tt INNER JOIN tutor_materia_config c
  ON c.id_tutor = tt.id_tutor AND c.id_materia = tt.id_materia AND c.id_periodo = tt.id_periodo
 WHERE c.estado = 'aprobado';
UPDATE tutor_materia_config c INNER JOIN turnos_repetidos x
 ON x.id_tutor = c.id_tutor AND x.id_materia = c.id_materia AND x.id_periodo = c.id_periodo
 SET c.estado = 'rechazado', c.motivo_rechazo = 'Turno ya cubierto en este período. Requiere nueva revisión.'
 WHERE x.orden > 1 AND NOT EXISTS
   (SELECT 1 FROM grupos_tutoria g WHERE g.id_tutor = c.id_tutor AND g.id_materia = c.id_materia
     AND g.id_periodo = c.id_periodo AND g.estado <> 'cancelado');
DROP TEMPORARY TABLE turnos_repetidos;

-- ==================================================================
-- db/041_integridad_historial.sql
-- ==================================================================
-- Integridad del historial academico.
--
-- 1. Las claves foraneas que borraban historial en cascada pasan a RESTRICT:
--    borrar un estudiante eliminaba sus inscripciones (y con ellas asistencias y
--    evaluaciones) y su demanda; borrar un tutor o una materia eliminaba ofertas,
--    turnos, rechazos e historial de habilitacion; borrar un usuario eliminaba su
--    perfil. Las cuentas y perfiles ya no se borran: se desactivan.
-- 2. La demanda que quedo "pendiente" en un periodo cerrado pasa a "vencida",
--    igual que hace PeriodosController::cerrar.
--
-- Los nombres de las FK generadas (tabla_ibfk_N) pueden variar entre instalaciones,
-- asi que el procedimiento las busca por tabla y columna. Compatible con MySQL 8.4
-- (Docker) y MariaDB 10.4 (XAMPP). Idempotente.
-- Ejecutar despues de 040_ofertas_por_periodo.sql sobre testdb.

USE testdb;

DROP PROCEDURE IF EXISTS fk_restrict_041;

DELIMITER //
CREATE PROCEDURE fk_restrict_041(IN p_tabla VARCHAR(64), IN p_columna VARCHAR(64), IN p_ref VARCHAR(64), IN p_ref_columna VARCHAR(64), IN p_nombre VARCHAR(64))
BEGIN
  DECLARE v_fk VARCHAR(64) DEFAULT NULL;
  DECLARE v_regla VARCHAR(20) DEFAULT NULL;

  SELECT k.CONSTRAINT_NAME, r.DELETE_RULE INTO v_fk, v_regla
  FROM information_schema.KEY_COLUMN_USAGE k
  INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
    ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
  WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = p_tabla AND k.COLUMN_NAME = p_columna
    AND k.REFERENCED_TABLE_NAME = p_ref
  LIMIT 1;

  IF v_fk IS NOT NULL AND v_regla <> 'RESTRICT' THEN
    SET @sql_041 = CONCAT('ALTER TABLE `', p_tabla, '` DROP FOREIGN KEY `', v_fk, '`');
    PREPARE s FROM @sql_041; EXECUTE s; DEALLOCATE PREPARE s;
    SET @sql_041 = CONCAT('ALTER TABLE `', p_tabla, '` ADD CONSTRAINT `', p_nombre, '` FOREIGN KEY (`', p_columna,
                          '`) REFERENCES `', p_ref, '` (`', p_ref_columna, '`) ON DELETE RESTRICT');
    PREPARE s FROM @sql_041; EXECUTE s; DEALLOCATE PREPARE s;
  END IF;
END //
DELIMITER ;

-- Historial del estudiante.
CALL fk_restrict_041('inscripciones', 'id_estudiante', 'estudiantes', 'id_estudiante', 'fk_inscripcion_estudiante');
CALL fk_restrict_041('demanda_tutoria', 'id_estudiante', 'estudiantes', 'id_estudiante', 'fk_demanda_estudiante');
-- Perfiles ligados a su cuenta.
CALL fk_restrict_041('estudiantes', 'id_usuario', 'usuarios', 'id_usuario', 'fk_estudiante_usuario');
CALL fk_restrict_041('tutores', 'id_usuario', 'usuarios', 'id_usuario', 'fk_tutor_usuario');
-- Ofertas, rechazos e historial del tutor y de la materia.
CALL fk_restrict_041('tutor_materia', 'id_tutor', 'tutores', 'id_tutor', 'fk_tutor_materia_tutor');
CALL fk_restrict_041('tutor_materia', 'id_materia', 'materias', 'id_materia', 'fk_tutor_materia_materia');
CALL fk_restrict_041('grupo_rechazos', 'id_tutor', 'tutores', 'id_tutor', 'fk_rechazo_tutor');
CALL fk_restrict_041('grupo_rechazos', 'id_materia', 'materias', 'id_materia', 'fk_rechazo_materia');
CALL fk_restrict_041('tutor_estado_historial', 'id_tutor', 'tutores', 'id_tutor', 'fk_tutor_historial_tutor');

DROP PROCEDURE fk_restrict_041;

-- La alerta "Demanda con grupo lleno" apuntaba a la seccion de interes registrado.
UPDATE notificaciones SET url = '/grupos/#cupos-completos'
WHERE tipo = 'cupo_completo' AND url = '/grupos/#demanda';

-- Demanda que quedo abierta en periodos ya cerrados.
UPDATE demanda_tutoria d
INNER JOIN periodos p ON p.id_periodo = d.id_periodo
SET d.estado = 'vencida'
WHERE p.estado = 'cerrada' AND d.estado = 'pendiente';

-- ==================================================================
-- db/042_division_grupos.sql
-- ==================================================================
-- Division de un grupo lleno en dos grupos parejos.
--
-- La coordinacion elige un segundo tutor, ve la vista previa (quienes se quedan,
-- quienes pasan al grupo nuevo y quienes entran desde la espera) y envia la
-- propuesta. El tutor la acepta o la rechaza desde "Mis grupos"; al aceptarla se
-- crea el grupo nuevo en el mismo turno y con los mismos dias, y se trasladan los
-- ultimos en inscribirse. Solo se divide un grupo sin asistencia registrada.
--
-- 1. grupo_divisiones: cada propuesta y su resultado.
-- 2. inscripciones.estado 'trasladada': la inscripcion del grupo de origen de un
--    estudiante movido (no es una baja); inscripciones.origen 'traslado': su
--    inscripcion en el grupo nuevo.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 041_integridad_historial.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS grupo_divisiones (
  id_division INT NOT NULL AUTO_INCREMENT,
  id_grupo_origen INT NOT NULL,
  id_tutor INT NOT NULL,
  estado ENUM('pendiente','aceptada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
  motivo VARCHAR(300) NULL,
  id_grupo_nuevo INT NULL,
  trasladados SMALLINT UNSIGNED NULL,
  desde_espera SMALLINT UNSIGNED NULL,
  id_usuario_solicitud INT NULL,
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_respuesta DATETIME NULL,
  PRIMARY KEY (id_division),
  KEY idx_division_origen (id_grupo_origen, estado),
  KEY idx_division_tutor (id_tutor, estado),
  CONSTRAINT fk_division_origen FOREIGN KEY (id_grupo_origen) REFERENCES grupos_tutoria (id_grupo) ON DELETE RESTRICT,
  CONSTRAINT fk_division_nuevo FOREIGN KEY (id_grupo_nuevo) REFERENCES grupos_tutoria (id_grupo) ON DELETE RESTRICT,
  CONSTRAINT fk_division_tutor FOREIGN KEY (id_tutor) REFERENCES tutores (id_tutor) ON DELETE RESTRICT,
  CONSTRAINT fk_division_usuario FOREIGN KEY (id_usuario_solicitud) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inscripciones
  MODIFY COLUMN estado ENUM('inscrito','lista_espera','cancelada','trasladada') NOT NULL DEFAULT 'inscrito',
  MODIFY COLUMN origen ENUM('auto','manual_admin','traslado') NOT NULL DEFAULT 'auto';

-- ==================================================================
-- db/043_tipos_tutoria.sql
-- ==================================================================
-- Tipos de tutoria (catalogo libre) y periodos por tipo.
--
-- El administrador define tipos con el nombre que quiera (Pregrado, Postgrado,
-- Nivelacion...). Cada periodo pertenece a un tipo y puede haber UN periodo
-- activo por tipo al mismo tiempo: tutorias de distinto tipo corren en paralelo.
-- El portal trabaja sobre el tipo elegido en la barra superior (TipoTutoria::actual).
--
--   tipos_tutoria.duracion_max_dias: tope de duracion de sus periodos; NULL = sin
--   tope. "Pregrado" conserva la regla anterior (42 dias: ultimo mes del semestre).
--
-- Los periodos existentes pasan a "Pregrado" (id 1). periodos.id_tipo_tutoria
-- tiene DEFAULT 1 para que los scripts anteriores que insertan periodos sigan
-- funcionando.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4 (procedimiento temporal). Idempotente.
-- Ejecutar despues de 042_division_grupos.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS tipos_tutoria (
  id_tipo_tutoria INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  duracion_max_dias SMALLINT UNSIGNED NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_tipo_tutoria),
  UNIQUE KEY uq_tipo_tutoria_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tipos_tutoria (id_tipo_tutoria, nombre, descripcion, duracion_max_dias)
SELECT 1, 'Pregrado', 'Último mes del semestre (julio o enero).', 42
WHERE NOT EXISTS (SELECT 1 FROM tipos_tutoria WHERE id_tipo_tutoria = 1);

DROP PROCEDURE IF EXISTS migrar_043_tipos;

DELIMITER //
CREATE PROCEDURE migrar_043_tipos()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'periodos' AND COLUMN_NAME = 'id_tipo_tutoria') THEN
    ALTER TABLE periodos
      ADD COLUMN id_tipo_tutoria INT NOT NULL DEFAULT 1 AFTER nombre,
      ADD INDEX idx_periodo_tipo_estado (id_tipo_tutoria, estado),
      ADD CONSTRAINT fk_periodo_tipo_tutoria FOREIGN KEY (id_tipo_tutoria) REFERENCES tipos_tutoria (id_tipo_tutoria);
  END IF;
END //
DELIMITER ;

CALL migrar_043_tipos();
DROP PROCEDURE migrar_043_tipos;

-- ==================================================================
-- db/044_mg_base.sql
-- ==================================================================
-- Modalidades de Grado (MG), base: roles, parametros, modalidades, cohortes,
-- calendario de hitos y bitacora. Ver docs/analisis/plan-mg-ajustado.md.
--
-- MG es un modulo aparte del de tutorias: expedientes individuales largos
-- (MG1 ~2 meses, MG2 ~4 meses) agrupados por cohorte de inicio.
--
--   roles: coordinador_mg (responsable del area) y auxiliar_mg (apoyo, permisos
--     reducidos). Los permisos por accion estan en includes/Auth.php.
--   parametros_mg: cifras dudosas de ENT-02/ENT-03 como valores configurables,
--     con fuente y estado de evidencia. Solo producen advertencias.
--   calendario_mg: hitos por cohorte. La cantidad de informes de MG2 (C-02: 3 o 4)
--     es la cantidad de hitos tipo 'informe' de la cohorte.
--   bitacora_mg: auditoria (antes/despues) de los cambios sensibles.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 043_tipos_tutoria.sql sobre testdb.

USE testdb;

INSERT INTO roles (nombre_rol)
SELECT 'coordinador_mg' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'coordinador_mg');
INSERT INTO roles (nombre_rol)
SELECT 'auxiliar_mg' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'auxiliar_mg');

CREATE TABLE IF NOT EXISTS parametros_mg (
  clave VARCHAR(60) NOT NULL,
  valor VARCHAR(100) NULL,
  tipo ENUM('entero','texto') NOT NULL DEFAULT 'entero',
  descripcion VARCHAR(255) NOT NULL,
  fuente VARCHAR(60) NOT NULL,
  estado_evidencia ENUM('confirmado','pendiente','propuesta') NOT NULL,
  actualizado_por INT NULL,
  fecha_actualizacion DATETIME NULL,
  PRIMARY KEY (clave),
  CONSTRAINT fk_parametro_mg_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO parametros_mg (clave, valor, tipo, descripcion, fuente, estado_evidencia) VALUES
  ('reuniones_min_semana_perfil', '2', 'entero', 'Reuniones mínimas por semana durante la elaboración del perfil (MG1).', 'ENT-03', 'confirmado'),
  ('dias_alerta_sin_reunion', '10', 'entero', 'Días sin reuniones registradas para alertar.', 'Equipo', 'propuesta'),
  ('tutor_carga_recomendada', '3', 'entero', 'Estudiantes vigentes por tutor a partir de los cuales se advierte (C-01). Nunca bloquea.', 'ENT-03 (2-3 deseable)', 'confirmado'),
  ('tutor_max_estudiantes', NULL, 'entero', 'Máximo formal de estudiantes por tutor (C-01: ENT-02 dice 5, ENT-03 sin máximo). Vacío = sin máximo.', 'ENT-02 / ENT-03', 'pendiente'),
  ('dias_anticipacion_tribunal', '14', 'entero', 'Días de anticipación con que se asignan los tribunales antes de la defensa.', 'ENT-03 (aprox.)', 'confirmado'),
  ('tribunales_por_defensa_mg1', '2', 'entero', 'Tribunales por defensa de MG1.', 'ENT-03', 'confirmado'),
  ('tribunales_por_defensa_mg2', '2', 'entero', 'Tribunales por defensa de MG2.', 'ENT-03', 'pendiente'),
  ('min_interesados_examen', '12', 'entero', 'Interesados mínimos para abrir Examen de Grado.', 'ENT-03', 'pendiente'),
  ('promedio_excelencia', '90', 'entero', 'Promedio mínimo para Graduación por Excelencia.', 'ENT-03', 'pendiente'),
  ('duracion_mg1_meses', '2', 'entero', 'Duración aproximada de MG1 (meses).', 'ENT-03', 'confirmado'),
  ('duracion_mg2_meses', '4', 'entero', 'Duración aproximada de MG2 (meses).', 'ENT-03', 'confirmado'),
  ('plazo_registro_reunion_dias', '7', 'entero', 'Días hacia atrás en que se puede registrar una reunión.', 'Equipo', 'propuesta'),
  ('nota_minima', '0', 'entero', 'Nota mínima de la escala.', 'Equipo', 'pendiente'),
  ('nota_maxima', '100', 'entero', 'Nota máxima de la escala (se mencionó promedio > 90).', 'ENT-03', 'pendiente'),
  ('nota_aprobacion', '51', 'entero', 'Nota con la que se sugiere aprobar la defensa. Solo sugiere: el estado lo decide la Coordinación.', 'Equipo', 'pendiente'),
  ('institucion_ciudad', 'Tarija', 'texto', 'Ciudad que encabeza cartas y citaciones.', 'Equipo', 'propuesta'),
  ('firma_coordinacion', 'Coordinación de Modalidades de Grado', 'texto', 'Firma de cartas y citaciones.', 'Equipo', 'propuesta');

CREATE TABLE IF NOT EXISTS modalidades_grado (
  id_modalidad INT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  requiere_tutor TINYINT(1) NOT NULL DEFAULT 0,
  flujo ENUM('perfil_mg','examen_areas','excelencia') NOT NULL,
  regla_por_validar TINYINT(1) NOT NULL DEFAULT 0,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_modalidad),
  UNIQUE KEY uq_modalidad_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RN-MG-01: 5 modalidades; solo Proyecto, Tesis y Trabajo Dirigido usan tutor.
-- RN-MG-20: Trabajo Dirigido marcado "regla por validar".
INSERT IGNORE INTO modalidades_grado (codigo, nombre, requiere_tutor, flujo, regla_por_validar) VALUES
  ('PROYECTO', 'Proyecto de Grado', 1, 'perfil_mg', 0),
  ('TESIS', 'Tesis', 1, 'perfil_mg', 0),
  ('TRABAJO_DIRIGIDO', 'Trabajo Dirigido', 1, 'perfil_mg', 1),
  ('EXAMEN', 'Examen de Grado', 0, 'examen_areas', 1),
  ('EXCELENCIA', 'Graduación por Excelencia', 0, 'excelencia', 1);

CREATE TABLE IF NOT EXISTS cohortes_mg (
  id_cohorte INT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(30) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_cohorte),
  UNIQUE KEY uq_cohorte_codigo (codigo),
  UNIQUE KEY uq_cohorte_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendario_mg (
  id_hito INT NOT NULL AUTO_INCREMENT,
  id_cohorte INT NOT NULL,
  etapa ENUM('previa','mg1','mg2') NOT NULL,
  tipo ENUM('taller','asignacion_tutor','asignacion_tribunal','informe','defensa','ingreso_mg2','otro') NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_limite DATE NOT NULL,
  avance_esperado_pct TINYINT UNSIGNED NULL,
  PRIMARY KEY (id_hito),
  KEY idx_hito_cohorte (id_cohorte, fecha_limite),
  CONSTRAINT fk_hito_cohorte FOREIGN KEY (id_cohorte) REFERENCES cohortes_mg (id_cohorte)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bitacora_mg (
  id_bitacora BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario INT NULL,
  accion VARCHAR(60) NOT NULL,
  tabla VARCHAR(60) NOT NULL,
  id_registro VARCHAR(60) NOT NULL,
  datos_antes LONGTEXT NULL,
  datos_despues LONGTEXT NULL,
  ip VARCHAR(45) NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_bitacora),
  KEY idx_bitacora_registro (tabla, id_registro),
  KEY idx_bitacora_fecha (fecha),
  CONSTRAINT fk_bitacora_mg_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- db/045_mg_expedientes.sql
-- ==================================================================
-- Modalidades de Grado: expedientes, historial de etapas e importaciones del padron.
--
--   expedientes_mg: un estudiante en un proceso de grado (RN-MG-02: individual).
--     etapa_actual y expediente_etapas_mg se cambian siempre en la misma
--     transaccion. La lista de estados es PROVISIONAL (pregunta 2 al Coordinador).
--   importaciones_mg(+_detalle): evidencia de cada carga del CSV, fila por fila.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 044_mg_base.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS expedientes_mg (
  id_expediente INT NOT NULL AUTO_INCREMENT,
  id_estudiante INT NOT NULL,
  id_modalidad INT NOT NULL,
  id_cohorte INT NOT NULL,
  etapa_actual ENUM('previa','mg1','mg2','finalizado') NOT NULL DEFAULT 'previa',
  estado ENUM('activo','aprobado','reprobado','abandono','retirado') NOT NULL DEFAULT 'activo',
  titulo_trabajo VARCHAR(255) NULL,
  fecha_inicio DATE NOT NULL,
  fecha_cierre DATE NULL,
  observaciones VARCHAR(1000) NULL,
  origen ENUM('manual','importacion') NOT NULL DEFAULT 'manual',
  registrado_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_expediente),
  UNIQUE KEY uq_expediente (id_estudiante, id_modalidad, id_cohorte),
  KEY idx_expediente_filtros (id_cohorte, etapa_actual, estado),
  CONSTRAINT fk_expediente_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
  CONSTRAINT fk_expediente_modalidad FOREIGN KEY (id_modalidad) REFERENCES modalidades_grado (id_modalidad),
  CONSTRAINT fk_expediente_cohorte FOREIGN KEY (id_cohorte) REFERENCES cohortes_mg (id_cohorte),
  CONSTRAINT fk_expediente_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expediente_etapas_mg (
  id_etapa INT NOT NULL AUTO_INCREMENT,
  id_expediente INT NOT NULL,
  etapa ENUM('previa','mg1','mg2','finalizado') NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NULL,
  resultado VARCHAR(255) NULL,
  registrado_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_etapa),
  KEY idx_etapa_expediente (id_expediente, fecha_inicio),
  CONSTRAINT fk_etapa_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_etapa_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS importaciones_mg (
  id_importacion INT NOT NULL AUTO_INCREMENT,
  archivo VARCHAR(255) NOT NULL,
  id_usuario INT NULL,
  total_filas INT NOT NULL DEFAULT 0,
  creados INT NOT NULL DEFAULT 0,
  omitidos INT NOT NULL DEFAULT 0,
  errores INT NOT NULL DEFAULT 0,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_importacion),
  CONSTRAINT fk_importacion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS importaciones_mg_detalle (
  id_detalle INT NOT NULL AUTO_INCREMENT,
  id_importacion INT NOT NULL,
  fila INT NOT NULL,
  registro_universitario VARCHAR(30) NULL,
  resultado ENUM('creado','omitido','pendiente_cuenta','error') NOT NULL,
  mensaje VARCHAR(255) NOT NULL,
  id_expediente INT NULL,
  PRIMARY KEY (id_detalle),
  KEY idx_detalle_importacion (id_importacion, fila),
  CONSTRAINT fk_detalle_importacion FOREIGN KEY (id_importacion) REFERENCES importaciones_mg (id_importacion),
  CONSTRAINT fk_detalle_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- db/046_mg_tutores_documentos.sql
-- ==================================================================
-- Modalidades de Grado: asignacion de tutor (con historial) y documentos generados.
--
--   asignaciones_tutor_mg: nunca se borra (RN-MG-07). Un cambio cierra la vigente
--     ('reemplazada') y crea otra. "Una sola vigente por expediente" se refuerza
--     con la columna generada vigente_expediente + UNIQUE (NULL no choca).
--   plantillas_documento_mg: HTML con {{variables}} de lista blanca, editable en
--     pantalla. Las iniciales son PROVISIONALES hasta tener las reales de UPDS.
--   documentos_generados_mg: snapshot inmutable de cada documento emitido, con
--     su numero correlativo. Reimprimir muestra exactamente lo que se emitio.
--   contadores_documento_mg: correlativo por tipo y anio (se bloquea la fila).
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 045_mg_expedientes.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS asignaciones_tutor_mg (
  id_asignacion INT NOT NULL AUTO_INCREMENT,
  id_expediente INT NOT NULL,
  id_tutor INT NOT NULL,
  fecha_asignacion DATE NOT NULL,
  fecha_fin DATE NULL,
  estado ENUM('vigente','finalizada','reemplazada') NOT NULL DEFAULT 'vigente',
  motivo_fin VARCHAR(500) NULL,
  fecha_nota_renuncia DATE NULL,
  referencia_decanatura VARCHAR(100) NULL,
  disponibilidad_consultada TINYINT(1) NOT NULL DEFAULT 0,
  observaciones VARCHAR(500) NULL,
  registrado_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  vigente_expediente INT AS (IF(estado = 'vigente', id_expediente, NULL)) STORED,
  PRIMARY KEY (id_asignacion),
  UNIQUE KEY uq_asignacion_vigente (vigente_expediente),
  KEY idx_asignacion_expediente (id_expediente, fecha_asignacion),
  KEY idx_asignacion_tutor (id_tutor, estado),
  CONSTRAINT fk_asignacion_mg_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_asignacion_mg_tutor FOREIGN KEY (id_tutor) REFERENCES tutores (id_tutor),
  CONSTRAINT fk_asignacion_mg_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plantillas_documento_mg (
  id_plantilla INT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  prefijo VARCHAR(10) NOT NULL,
  cuerpo_html MEDIUMTEXT NOT NULL,
  version INT NOT NULL DEFAULT 1,
  actualizado_por INT NULL,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_plantilla),
  UNIQUE KEY uq_plantilla_codigo (codigo),
  CONSTRAINT fk_plantilla_mg_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO plantillas_documento_mg (codigo, nombre, prefijo, cuerpo_html) VALUES
('CARTA_ASIGNACION_TUTOR', 'Carta de asignación de tutor', 'CAT',
'<p class="doc-provisional">[PLANTILLA PROVISIONAL]</p>
<p class="doc-derecha">{{ciudad}}, {{fecha_larga}}</p>
<p class="doc-derecha"><strong>{{numero}}</strong></p>
<p>Señor(a):<br><strong>{{destinatario_nombre}}</strong><br>Presente.-</p>
<p class="doc-ref"><strong>Ref.: Asignación de tutor de {{modalidad}}</strong></p>
<p>Por medio de la presente se comunica que, de acuerdo con la revisión de afinidad y experticia realizada por Decanatura ({{referencia_decanatura}}), se asigna como tutor(a) al docente <strong>{{tutor_nombre}}</strong> para acompañar al estudiante <strong>{{estudiante_nombre}}</strong>, R.U. {{registro_universitario}}, de la carrera de {{carrera}}, en la modalidad <strong>{{modalidad}}</strong> ({{cohorte}}).</p>
<p>Tema: <em>{{tema}}</em></p>
<p>El acompañamiento comprende las etapas MG1 y MG2 hasta la conclusión del proceso.</p>
<p>Sin otro particular, saludamos a usted atentamente.</p>
<p class="doc-firma">{{firma}}</p>'),
('CITACION_TRIBUNAL', 'Citación a tribunal', 'CIT',
'<p class="doc-provisional">[PLANTILLA PROVISIONAL]</p>
<p class="doc-derecha">{{ciudad}}, {{fecha_larga}}</p>
<p class="doc-derecha"><strong>{{numero}}</strong></p>
<p>Señor(a):<br><strong>{{destinatario_nombre}}</strong><br>Tribunal evaluador<br>Presente.-</p>
<p class="doc-ref"><strong>Ref.: Citación a defensa de {{etapa}}</strong></p>
<p>Se le cita en calidad de tribunal a la defensa de <strong>{{etapa}}</strong> del estudiante <strong>{{estudiante_nombre}}</strong>, R.U. {{registro_universitario}}, modalidad {{modalidad}}, tema <em>{{tema}}</em>.</p>
<p><strong>Fecha:</strong> {{fecha_defensa}}<br><strong>Hora:</strong> {{hora_inicio}} a {{hora_fin}}<br><strong>Ambiente:</strong> {{ambiente}}</p>
<p>Tribunales: {{tribunales}}. Tutor(a): {{tutor_nombre}}.</p>
<p>Sin otro particular, saludamos a usted atentamente.</p>
<p class="doc-firma">{{firma}}</p>'),
('CITACION_ESTUDIANTE', 'Citación al estudiante', 'CIE',
'<p class="doc-provisional">[PLANTILLA PROVISIONAL]</p>
<p class="doc-derecha">{{ciudad}}, {{fecha_larga}}</p>
<p class="doc-derecha"><strong>{{numero}}</strong></p>
<p>Señor(a):<br><strong>{{destinatario_nombre}}</strong><br>R.U. {{registro_universitario}}<br>Presente.-</p>
<p class="doc-ref"><strong>Ref.: Citación a defensa de {{etapa}}</strong></p>
<p>Se le comunica que su defensa de <strong>{{etapa}}</strong> en la modalidad {{modalidad}}, tema <em>{{tema}}</em>, se realizará en la siguiente fecha:</p>
<p><strong>Fecha:</strong> {{fecha_defensa}}<br><strong>Hora:</strong> {{hora_inicio}} a {{hora_fin}}<br><strong>Ambiente:</strong> {{ambiente}}</p>
<p>Tribunales: {{tribunales}}. Tutor(a): {{tutor_nombre}}.</p>
<p>Sin otro particular, saludamos a usted atentamente.</p>
<p class="doc-firma">{{firma}}</p>');

CREATE TABLE IF NOT EXISTS contadores_documento_mg (
  prefijo VARCHAR(10) NOT NULL,
  anio SMALLINT UNSIGNED NOT NULL,
  ultimo_numero INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (prefijo, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documentos_generados_mg (
  id_documento INT NOT NULL AUTO_INCREMENT,
  id_plantilla INT NOT NULL,
  version_plantilla INT NOT NULL,
  codigo VARCHAR(40) NOT NULL,
  id_expediente INT NOT NULL,
  id_asignacion INT NULL,
  id_defensa INT NULL,
  destinatario VARCHAR(200) NOT NULL,
  numero VARCHAR(40) NOT NULL,
  contenido_snapshot MEDIUMTEXT NOT NULL,
  generado_por INT NULL,
  fecha_generacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_documento),
  UNIQUE KEY uq_documento_numero (numero),
  KEY idx_documento_expediente (id_expediente, fecha_generacion),
  KEY idx_documento_defensa (id_defensa),
  CONSTRAINT fk_documento_plantilla FOREIGN KEY (id_plantilla) REFERENCES plantillas_documento_mg (id_plantilla),
  CONSTRAINT fk_documento_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_documento_asignacion FOREIGN KEY (id_asignacion) REFERENCES asignaciones_tutor_mg (id_asignacion),
  CONSTRAINT fk_documento_usuario FOREIGN KEY (generado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- db/047_mg_defensas.sql
-- ==================================================================
-- Modalidades de Grado: tribunales, defensas y calificaciones.
--
--   tribunales_mg: evaluadores por expediente y etapa (RN-MG-14). Un cambio marca
--     'reemplazado' y crea otro; un solo vigente por puesto (expediente, etapa,
--     orden) reforzado con columna generada + UNIQUE.
--   defensas_mg: fecha, horario y ambiente (texto: los espacios_tutoria son
--     categorias, no aulas). Reprogramar deja la anterior como 'reprogramada' y
--     crea otra. Autorizaciones fuera de calendario se registran (RN-MG-18).
--   calificaciones_mg: una nota por defensa; el estudiante la ve solo publicada.
--     Todo cambio de nota queda en bitacora_mg (antes/despues).
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 046_mg_tutores_documentos.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS tribunales_mg (
  id_tribunal INT NOT NULL AUTO_INCREMENT,
  id_expediente INT NOT NULL,
  etapa ENUM('mg1','mg2') NOT NULL,
  id_tutor INT NOT NULL,
  orden TINYINT UNSIGNED NOT NULL,
  fecha_asignacion DATE NOT NULL,
  estado ENUM('vigente','reemplazado') NOT NULL DEFAULT 'vigente',
  motivo_cambio VARCHAR(500) NULL,
  registrado_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  puesto_vigente VARCHAR(40) AS (IF(estado = 'vigente', CONCAT(id_expediente, '-', etapa, '-', orden), NULL)) STORED,
  PRIMARY KEY (id_tribunal),
  UNIQUE KEY uq_tribunal_puesto_vigente (puesto_vigente),
  KEY idx_tribunal_expediente (id_expediente, etapa, estado),
  KEY idx_tribunal_docente (id_tutor, estado),
  CONSTRAINT fk_tribunal_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_tribunal_tutor FOREIGN KEY (id_tutor) REFERENCES tutores (id_tutor),
  CONSTRAINT fk_tribunal_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS defensas_mg (
  id_defensa INT NOT NULL AUTO_INCREMENT,
  id_expediente INT NOT NULL,
  etapa ENUM('mg1','mg2') NOT NULL,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  ambiente VARCHAR(100) NOT NULL,
  estado ENUM('programada','realizada','reprogramada','cancelada') NOT NULL DEFAULT 'programada',
  motivo_estado VARCHAR(500) NULL,
  autorizado_por ENUM('decanatura','vicerrectorado') NULL,
  referencia_autorizacion VARCHAR(100) NULL,
  obs_fondo TEXT NULL,
  obs_forma TEXT NULL,
  id_defensa_anterior INT NULL,
  registrado_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_defensa),
  KEY idx_defensa_fecha (fecha, hora_inicio),
  KEY idx_defensa_expediente (id_expediente, etapa, estado),
  CONSTRAINT fk_defensa_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_defensa_anterior FOREIGN KEY (id_defensa_anterior) REFERENCES defensas_mg (id_defensa),
  CONSTRAINT fk_defensa_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
  CONSTRAINT chk_defensa_horario CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calificaciones_mg (
  id_calificacion INT NOT NULL AUTO_INCREMENT,
  id_defensa INT NOT NULL,
  nota DECIMAL(5,2) NOT NULL,
  observaciones VARCHAR(1000) NULL,
  publicada TINYINT(1) NOT NULL DEFAULT 0,
  registrada_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NULL,
  PRIMARY KEY (id_calificacion),
  UNIQUE KEY uq_calificacion_defensa (id_defensa),
  CONSTRAINT fk_calificacion_defensa FOREIGN KEY (id_defensa) REFERENCES defensas_mg (id_defensa),
  CONSTRAINT fk_calificacion_usuario FOREIGN KEY (registrada_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La FK de documentos hacia defensas se agrega aqui (la tabla de documentos es de 046).
DROP PROCEDURE IF EXISTS migrar_047_fk_documento;

DELIMITER //
CREATE PROCEDURE migrar_047_fk_documento()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_documento_defensa') THEN
    ALTER TABLE documentos_generados_mg
      ADD CONSTRAINT fk_documento_defensa FOREIGN KEY (id_defensa) REFERENCES defensas_mg (id_defensa);
  END IF;
END //
DELIMITER ;

CALL migrar_047_fk_documento();
DROP PROCEDURE migrar_047_fk_documento;

-- ==================================================================
-- db/048_mg_seguimiento.sql
-- ==================================================================
-- Modalidades de Grado, MVP-2 (P2): seguimiento del tutor, informes de avance y alertas.
--
--   reuniones_mg (HU-034/035): el tutor vigente registra cada reunion (sin horario
--     fijo) con la asistencia de ambos. Sin fotos ni archivos (RN-MG-11, C-03).
--     La Coordinacion la valida u observa; una validada ya no la edita el tutor.
--     Nunca se borra: las correcciones van a bitacora_mg con antes/despues.
--   informes_avance_mg (HU-037): un informe por expediente e hito de tipo 'informe'
--     (UNIQUE). El estado (a tiempo / tarde / no presentado) se calcula contra la
--     fecha limite del hito; un informe faltante no cambia el estado del expediente.
--   alertas_atendidas_mg (HU-038): las alertas se calculan al abrir el panel (sin
--     cron). Marcar una atendida guarda su clave; si la situacion cambia, la clave
--     cambia y la alerta vuelve a aparecer.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 047_mg_defensas.sql sobre testdb.

USE testdb;

INSERT IGNORE INTO parametros_mg (clave, valor, tipo, descripcion, fuente, estado_evidencia) VALUES
  ('dias_hito_proximo', '14', 'entero', 'Días antes de la fecha límite en que un hito del calendario se marca como próximo.', 'Equipo', 'propuesta'),
  ('dias_alerta_citaciones', '3', 'entero', 'Días antes de la defensa en que la falta de citaciones pasa a alerta alta.', 'Equipo', 'propuesta');

CREATE TABLE IF NOT EXISTS reuniones_mg (
  id_reunion INT NOT NULL AUTO_INCREMENT,
  id_expediente INT NOT NULL,
  id_asignacion INT NOT NULL,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  modalidad ENUM('presencial','virtual') NOT NULL,
  lugar_o_enlace VARCHAR(255) NOT NULL,
  temas VARCHAR(1000) NOT NULL,
  avance_sesion VARCHAR(1000) NULL,
  observaciones VARCHAR(1000) NULL,
  asistio_estudiante ENUM('si','no') NOT NULL,
  asistio_tutor ENUM('si','no') NOT NULL,
  estado_validacion ENUM('registrada','validada','observada') NOT NULL DEFAULT 'registrada',
  motivo_observacion VARCHAR(500) NULL,
  registrada_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  validada_por INT NULL,
  fecha_validacion DATETIME NULL,
  PRIMARY KEY (id_reunion),
  KEY idx_reunion_expediente (id_expediente, fecha),
  KEY idx_reunion_asignacion (id_asignacion, fecha),
  KEY idx_reunion_estado (estado_validacion, fecha),
  CONSTRAINT chk_reunion_horas CHECK (hora_fin > hora_inicio),
  CONSTRAINT fk_reunion_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_reunion_asignacion FOREIGN KEY (id_asignacion) REFERENCES asignaciones_tutor_mg (id_asignacion),
  CONSTRAINT fk_reunion_registrada FOREIGN KEY (registrada_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
  CONSTRAINT fk_reunion_validada FOREIGN KEY (validada_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS informes_avance_mg (
  id_informe INT NOT NULL AUTO_INCREMENT,
  id_expediente INT NOT NULL,
  id_hito INT NOT NULL,
  porcentaje_avance TINYINT UNSIGNED NOT NULL,
  fecha_presentacion DATE NOT NULL,
  formato ENUM('digital','fisico') NOT NULL,
  respaldo_fisico TINYINT(1) NOT NULL DEFAULT 0,
  presentado_por INT NULL,
  observaciones VARCHAR(1000) NULL,
  registrado_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_informe),
  UNIQUE KEY uq_informe_hito (id_expediente, id_hito),
  KEY idx_informe_hito (id_hito),
  CONSTRAINT chk_informe_porcentaje CHECK (porcentaje_avance <= 100),
  CONSTRAINT fk_informe_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_informe_hito FOREIGN KEY (id_hito) REFERENCES calendario_mg (id_hito),
  CONSTRAINT fk_informe_tutor FOREIGN KEY (presentado_por) REFERENCES tutores (id_tutor),
  CONSTRAINT fk_informe_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alertas_atendidas_mg (
  id_atencion INT NOT NULL AUTO_INCREMENT,
  clave VARCHAR(120) NOT NULL,
  codigo CHAR(2) NOT NULL,
  id_expediente INT NULL,
  nota VARCHAR(500) NOT NULL,
  atendida_por INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_atencion),
  UNIQUE KEY uq_alerta_clave (clave),
  KEY idx_alerta_expediente (id_expediente),
  CONSTRAINT fk_alerta_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_alerta_usuario FOREIGN KEY (atendida_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==================================================================
-- db/049_mg_solicitudes.sql
-- ==================================================================
-- Modalidades de Grado: solicitud del estudiante y aprobacion de la Coordinacion.
--
--   solicitudes_mg: el estudiante elige modalidad, propone un tema y adjunta un
--     documento obligatorio (record/certificado de notas, foto o PDF). La
--     Coordinacion lo revisa a mano y lo aprueba (crea el expediente), lo observa
--     (el estudiante corrige y reenvia) o lo rechaza con motivo. Nada se borra.
--     "Una sola solicitud abierta por estudiante" (pendiente u observada) se
--     refuerza con la columna generada abierta_estudiante + UNIQUE (NULL no choca).
--   situacion: lo que declara el estudiante. 'cursando_ultimo' sube su record de notas
--     hasta hoy y la Coordinacion lo aprueba a la etapa previa (talleres) mientras
--     termina; 'egresado' sube su certificado completo y puede entrar a MG1. La
--     Coordinacion verifica el documento y decide la etapa. [PENDIENTE] confirmar
--     la regla con el Coordinador.
--   El archivo NO vive en la base ni en la carpeta publica: se guarda en
--     storage/mg_solicitudes con nombre aleatorio y se sirve solo por PHP a su
--     dueño y a la Coordinacion. Aqui quedan nombre, tipo, tamano y hash.
--   expedientes_mg.origen gana el valor 'solicitud' (expediente creado al aprobar).
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 048_mg_seguimiento.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS solicitudes_mg (
  id_solicitud INT NOT NULL AUTO_INCREMENT,
  id_estudiante INT NOT NULL,
  id_modalidad INT NOT NULL,
  situacion ENUM('cursando_ultimo','egresado') NOT NULL DEFAULT 'egresado',
  titulo_propuesto VARCHAR(255) NULL,
  mensaje VARCHAR(1000) NULL,
  documento_archivo VARCHAR(80) NOT NULL,
  documento_nombre VARCHAR(150) NOT NULL,
  documento_mime VARCHAR(50) NOT NULL,
  documento_tamano INT NOT NULL,
  documento_hash CHAR(64) NOT NULL,
  estado ENUM('pendiente','observada','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  motivo_revision VARCHAR(500) NULL,
  id_revisor INT NULL,
  id_expediente INT NULL,
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fecha_revision DATETIME NULL,
  abierta_estudiante INT AS (IF(estado IN ('pendiente','observada'), id_estudiante, NULL)) STORED,
  PRIMARY KEY (id_solicitud),
  UNIQUE KEY uq_solicitud_abierta (abierta_estudiante),
  KEY idx_solicitud_estado (estado, fecha_solicitud),
  KEY idx_solicitud_estudiante (id_estudiante, fecha_solicitud),
  CONSTRAINT fk_solicitud_mg_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes (id_estudiante),
  CONSTRAINT fk_solicitud_mg_modalidad FOREIGN KEY (id_modalidad) REFERENCES modalidades_grado (id_modalidad),
  CONSTRAINT fk_solicitud_mg_revisor FOREIGN KEY (id_revisor) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
  CONSTRAINT fk_solicitud_mg_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO parametros_mg (clave, valor, tipo, descripcion, fuente, estado_evidencia) VALUES
  ('semestre_minimo_solicitud_mg', '9', 'entero', 'Semestre desde el que un estudiante puede solicitar su modalidad de grado. Por debajo de ese semestre no se ofrece la solicitud ni se acepta. Un egresado con semestre desactualizado pide a la Coordinacion que lo corrija.', 'Equipo', 'propuesta');

ALTER TABLE expedientes_mg
  MODIFY origen ENUM('manual','importacion','solicitud') NOT NULL DEFAULT 'manual';
