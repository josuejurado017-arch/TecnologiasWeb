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
