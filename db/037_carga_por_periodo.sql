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
