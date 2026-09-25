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
