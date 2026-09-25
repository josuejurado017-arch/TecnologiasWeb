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
