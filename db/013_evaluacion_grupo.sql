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
