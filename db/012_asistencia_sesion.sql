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
