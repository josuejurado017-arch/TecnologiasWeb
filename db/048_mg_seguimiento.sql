-- Modalidades de Grado, MVP-2 (P2): seguimiento del tutor, informes de avance y alertas.
--
--   reuniones_mg (HU-034/035): el tutor vigente registra cada reunion (sin horario
--     fijo) con la asistencia de ambos. Sin fotos ni archivos (RN-MG-11, C-03).
--     La Coordinacion la valida u observa; una validada ya no la edita el tutor.
--     Nunca se borra: las correcciones van a bitacora_mg con antes/despues.
--   informes_avance_mg (HU-037): un informe por expediente e hito de tipo 'informe'
--     (UNIQUE). El estado (a tiempo / tarde / no presentado) se calcula contra la
--     fecha limite del hito; un informe faltante no cambia el estado del expediente.
--   alertas_atendidas_mg (HU-038): las alertas se calculan al abrir el panel (sin
--     cron). Marcar una atendida guarda su clave; si la situacion cambia, la clave
--     cambia y la alerta vuelve a aparecer.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 047_mg_defensas.sql sobre testdb.

USE testdb;

INSERT IGNORE INTO parametros_mg (clave, valor, tipo, descripcion, fuente, estado_evidencia) VALUES
  ('dias_hito_proximo', '14', 'entero', 'Días antes de la fecha límite en que un hito del calendario se marca como próximo.', 'Equipo', 'propuesta'),
  ('dias_alerta_citaciones', '3', 'entero', 'Días antes de la defensa en que la falta de citaciones pasa a alerta alta.', 'Equipo', 'propuesta');

CREATE TABLE IF NOT EXISTS reuniones_mg (
  id_reunion INT NOT NULL AUTO_INCREMENT,
  id_expediente INT NOT NULL,
  id_asignacion INT NOT NULL,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  modalidad ENUM('presencial','virtual') NOT NULL,
  lugar_o_enlace VARCHAR(255) NOT NULL,
  temas VARCHAR(1000) NOT NULL,
  avance_sesion VARCHAR(1000) NULL,
  observaciones VARCHAR(1000) NULL,
  asistio_estudiante ENUM('si','no') NOT NULL,
  asistio_tutor ENUM('si','no') NOT NULL,
  estado_validacion ENUM('registrada','validada','observada') NOT NULL DEFAULT 'registrada',
  motivo_observacion VARCHAR(500) NULL,
  registrada_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  validada_por INT NULL,
  fecha_validacion DATETIME NULL,
  PRIMARY KEY (id_reunion),
  KEY idx_reunion_expediente (id_expediente, fecha),
  KEY idx_reunion_asignacion (id_asignacion, fecha),
  KEY idx_reunion_estado (estado_validacion, fecha),
  CONSTRAINT chk_reunion_horas CHECK (hora_fin > hora_inicio),
  CONSTRAINT fk_reunion_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_reunion_asignacion FOREIGN KEY (id_asignacion) REFERENCES asignaciones_tutor_mg (id_asignacion),
  CONSTRAINT fk_reunion_registrada FOREIGN KEY (registrada_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
  CONSTRAINT fk_reunion_validada FOREIGN KEY (validada_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS informes_avance_mg (
  id_informe INT NOT NULL AUTO_INCREMENT,
  id_expediente INT NOT NULL,
  id_hito INT NOT NULL,
  porcentaje_avance TINYINT UNSIGNED NOT NULL,
  fecha_presentacion DATE NOT NULL,
  formato ENUM('digital','fisico') NOT NULL,
  respaldo_fisico TINYINT(1) NOT NULL DEFAULT 0,
  presentado_por INT NULL,
  observaciones VARCHAR(1000) NULL,
  registrado_por INT NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_informe),
  UNIQUE KEY uq_informe_hito (id_expediente, id_hito),
  KEY idx_informe_hito (id_hito),
  CONSTRAINT chk_informe_porcentaje CHECK (porcentaje_avance <= 100),
  CONSTRAINT fk_informe_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_informe_hito FOREIGN KEY (id_hito) REFERENCES calendario_mg (id_hito),
  CONSTRAINT fk_informe_tutor FOREIGN KEY (presentado_por) REFERENCES tutores (id_tutor),
  CONSTRAINT fk_informe_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alertas_atendidas_mg (
  id_atencion INT NOT NULL AUTO_INCREMENT,
  clave VARCHAR(120) NOT NULL,
  codigo CHAR(2) NOT NULL,
  id_expediente INT NULL,
  nota VARCHAR(500) NOT NULL,
  atendida_por INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_atencion),
  UNIQUE KEY uq_alerta_clave (clave),
  KEY idx_alerta_expediente (id_expediente),
  CONSTRAINT fk_alerta_expediente FOREIGN KEY (id_expediente) REFERENCES expedientes_mg (id_expediente),
  CONSTRAINT fk_alerta_usuario FOREIGN KEY (atendida_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
