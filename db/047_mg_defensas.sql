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
