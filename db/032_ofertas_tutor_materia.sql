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
