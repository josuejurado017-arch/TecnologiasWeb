-- Gobierno academico: visto bueno del administrador (coordinador academico).
--
-- 1. Habilitacion docente del tutor, separada del estado de la cuenta:
--      usuarios.estado        -> puede iniciar sesion (activo/inactivo)
--      tutores.estado_docente -> el motor puede proponerle grupos
--    Un tutor pendiente inicia sesion, completa su perfil y configura sus
--    materias, pero el motor lo ignora hasta que el administrador lo aprueba.
--
-- 2. Aprobacion por grupo: el motor ya no confirma grupos por su cuenta. Cada
--    grupo nace 'por_aprobar'; el administrador lo aprueba (pasa a formacion o
--    confirmado segun el cupo) o lo rechaza (cancelado, demanda a espera).
--
-- 3. grupo_rechazos: combinacion (tutor, materia, horario) rechazada en el
--    periodo. El motor la salta para no volver a proponer el mismo grupo.
--
-- DEFAULT 'aprobado' a proposito: los tutores existentes quedan aprobados al
-- agregar la columna, y 027_datos_operativos.sql (que inserta tutores sin esta
-- columna) sigue sembrando tutores aprobados si se vuelve a ejecutar. El
-- autorregistro (RegistroTutor) inserta 'pendiente' de forma explicita.
--
-- Compatible con MySQL 8.4 (Docker) y MariaDB 10.4 (XAMPP): MySQL no admite
-- ADD COLUMN/INDEX IF NOT EXISTS, asi que los cambios condicionales van en un
-- procedimiento temporal. Idempotente: se puede volver a ejecutar.
-- Ejecutar despues de 027_datos_operativos.sql sobre testdb.

USE testdb;

DROP PROCEDURE IF EXISTS migrar_028_aprobacion;

DELIMITER //
CREATE PROCEDURE migrar_028_aprobacion()
BEGIN
  -- ------------------------------------------------------------------
  -- 1. Habilitacion docente
  -- ------------------------------------------------------------------
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutores' AND COLUMN_NAME = 'estado_docente') THEN
    ALTER TABLE tutores
      ADD COLUMN estado_docente ENUM('pendiente','aprobado','rechazado','suspendido') NOT NULL DEFAULT 'aprobado' AFTER biografia,
      ADD COLUMN motivo_rechazo VARCHAR(500) NULL AFTER estado_docente,
      ADD COLUMN fecha_revision DATETIME NULL AFTER motivo_rechazo,
      ADD COLUMN id_revisor INT NULL AFTER fecha_revision,
      ADD INDEX idx_tutor_estado_docente (estado_docente);
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tutores' AND CONSTRAINT_NAME = 'fk_tutor_revisor') THEN
    ALTER TABLE tutores
      ADD CONSTRAINT fk_tutor_revisor FOREIGN KEY (id_revisor) REFERENCES usuarios (id_usuario) ON DELETE SET NULL;
  END IF;

  -- ------------------------------------------------------------------
  -- 2. Aprobacion por grupo (los grupos existentes ya estan en marcha: no cambian)
  -- ------------------------------------------------------------------
  ALTER TABLE grupos_tutoria
    MODIFY COLUMN estado ENUM('por_aprobar','formacion','confirmado','en_curso','finalizado','cancelado') NOT NULL DEFAULT 'por_aprobar';

  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND COLUMN_NAME = 'fecha_aprobacion') THEN
    ALTER TABLE grupos_tutoria
      ADD COLUMN fecha_aprobacion DATETIME NULL AFTER fecha_estado,
      ADD COLUMN id_aprobador INT NULL AFTER fecha_aprobacion;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND CONSTRAINT_NAME = 'fk_grupo_aprobador') THEN
    ALTER TABLE grupos_tutoria
      ADD CONSTRAINT fk_grupo_aprobador FOREIGN KEY (id_aprobador) REFERENCES usuarios (id_usuario) ON DELETE SET NULL;
  END IF;
END //
DELIMITER ;

CALL migrar_028_aprobacion();
DROP PROCEDURE migrar_028_aprobacion;

CREATE TABLE IF NOT EXISTS tutor_estado_historial (
  id_historial INT NOT NULL AUTO_INCREMENT,
  id_tutor INT NOT NULL,
  estado_anterior ENUM('pendiente','aprobado','rechazado','suspendido') NULL,
  estado_nuevo ENUM('pendiente','aprobado','rechazado','suspendido') NOT NULL,
  motivo VARCHAR(500) NULL,
  id_usuario_accion INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_historial),
  KEY idx_tutor_historial (id_tutor, fecha),
  CONSTRAINT fk_tutor_historial_tutor FOREIGN KEY (id_tutor) REFERENCES tutores (id_tutor) ON DELETE CASCADE,
  CONSTRAINT fk_tutor_historial_usuario FOREIGN KEY (id_usuario_accion) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- 3. Combinaciones rechazadas (el motor no las vuelve a proponer en el periodo)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS grupo_rechazos (
  id_rechazo INT NOT NULL AUTO_INCREMENT,
  id_periodo INT NOT NULL,
  id_materia INT NOT NULL,
  id_tutor INT NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  id_grupo INT NULL,
  motivo VARCHAR(300) NOT NULL,
  id_usuario_accion INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_rechazo),
  KEY idx_rechazo_lookup (id_periodo, id_materia),
  CONSTRAINT fk_rechazo_periodo FOREIGN KEY (id_periodo) REFERENCES periodos (id_periodo) ON DELETE CASCADE,
  CONSTRAINT fk_rechazo_materia FOREIGN KEY (id_materia) REFERENCES materias (id_materia) ON DELETE CASCADE,
  CONSTRAINT fk_rechazo_tutor FOREIGN KEY (id_tutor) REFERENCES tutores (id_tutor) ON DELETE CASCADE,
  CONSTRAINT fk_rechazo_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria (id_grupo) ON DELETE SET NULL,
  CONSTRAINT fk_rechazo_usuario FOREIGN KEY (id_usuario_accion) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
