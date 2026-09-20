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
