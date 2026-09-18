-- Espacios derivados de disponibilidad y solicitudes de horario especial.
-- Ejecutar despues de 008_tutorias_institucionales.sql.

USE testdb;

ALTER TABLE tutorias
  ADD COLUMN id_disponibilidad INT NULL AFTER id_materia,
  ADD INDEX idx_tutoria_disponibilidad (id_disponibilidad),
  ADD CONSTRAINT fk_tutoria_disponibilidad
    FOREIGN KEY (id_disponibilidad) REFERENCES disponibilidad_tutor(id_disponibilidad)
    ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS solicitudes_horario_especial (
  id_solicitud BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_estudiante INT NOT NULL,
  id_tutor INT NOT NULL,
  id_materia INT NOT NULL,
  fecha_propuesta DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  modalidad ENUM('presencial','virtual') NOT NULL DEFAULT 'presencial',
  lugar_o_enlace VARCHAR(200) NULL,
  observaciones TEXT NULL,
  estado ENUM('pendiente','aprobada','rechazada','cancelada','convertida') NOT NULL DEFAULT 'pendiente',
  respuesta_tutor VARCHAR(500) NULL,
  fecha_respuesta DATETIME NULL,
  id_tutoria INT NULL,
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_especial_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante),
  CONSTRAINT fk_especial_tutor FOREIGN KEY (id_tutor) REFERENCES tutores(id_tutor),
  CONSTRAINT fk_especial_materia FOREIGN KEY (id_materia) REFERENCES materias(id_materia),
  CONSTRAINT fk_especial_tutoria FOREIGN KEY (id_tutoria) REFERENCES tutorias(id_tutoria) ON DELETE SET NULL,
  INDEX idx_especial_tutor_estado_fecha (id_tutor, estado, fecha_propuesta),
  INDEX idx_especial_estudiante_estado_fecha (id_estudiante, estado, fecha_propuesta),
  INDEX idx_especial_materia (id_materia)
) ENGINE=InnoDB;
