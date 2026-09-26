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
