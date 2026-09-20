-- Rediseno institucional de tutorias UPDS (Fase 2: nucleo).
-- Grupos de tutoria como serie semanal, sesiones concretas, inscripciones
-- y demanda insatisfecha. Ejecutar despues de 010_rediseno_institucional.sql.

USE testdb;

-- ------------------------------------------------------------------
-- Grupo de tutoria: 1 tutor -> N estudiantes, serie semanal dentro de
-- una campana. Es la unidad agendable con capacidad y control de conflictos.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS grupos_tutoria (
  id_grupo INT AUTO_INCREMENT PRIMARY KEY,
  id_periodo INT NOT NULL,
  id_materia INT NOT NULL,
  id_tutor INT NOT NULL,
  id_aula INT NOT NULL,
  modalidad ENUM('presencial','virtual') NOT NULL DEFAULT 'presencial',
  dia_semana ENUM('Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  cupo_max SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  cupo_ocupado SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  estado ENUM('formacion','confirmado','en_curso','finalizado','cancelado') NOT NULL DEFAULT 'formacion',
  motivo_estado VARCHAR(300) NULL,
  fecha_estado DATETIME NULL,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_grupo_periodo FOREIGN KEY (id_periodo) REFERENCES periodos(id_periodo) ON DELETE CASCADE,
  CONSTRAINT fk_grupo_materia FOREIGN KEY (id_materia) REFERENCES materias(id_materia),
  CONSTRAINT fk_grupo_tutor FOREIGN KEY (id_tutor) REFERENCES tutores(id_tutor),
  CONSTRAINT fk_grupo_aula FOREIGN KEY (id_aula) REFERENCES aulas(id_aula),
  INDEX idx_grupo_periodo_materia (id_periodo, id_materia, estado),
  INDEX idx_grupo_tutor_dia (id_tutor, dia_semana),
  INDEX idx_grupo_aula_dia (id_aula, dia_semana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Sesiones concretas generadas semanalmente entre las fechas de la campana.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sesiones_tutoria (
  id_sesion INT AUTO_INCREMENT PRIMARY KEY,
  id_grupo INT NOT NULL,
  fecha DATE NOT NULL,
  estado ENUM('programada','realizada','cancelada') NOT NULL DEFAULT 'programada',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sesion_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria(id_grupo) ON DELETE CASCADE,
  UNIQUE KEY uq_sesion_grupo_fecha (id_grupo, fecha),
  INDEX idx_sesion_fecha (fecha, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Inscripcion: estudiante <-> grupo. Portadora de asistencia y evaluacion.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inscripciones (
  id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
  id_grupo INT NOT NULL,
  id_estudiante INT NOT NULL,
  estado ENUM('inscrito','lista_espera','cancelada') NOT NULL DEFAULT 'inscrito',
  origen ENUM('auto','manual_admin') NOT NULL DEFAULT 'auto',
  fecha_inscripcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inscripcion_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria(id_grupo) ON DELETE CASCADE,
  CONSTRAINT fk_inscripcion_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante) ON DELETE CASCADE,
  UNIQUE KEY uq_inscripcion_grupo_estudiante (id_grupo, id_estudiante),
  INDEX idx_inscripcion_estudiante (id_estudiante, estado),
  INDEX idx_inscripcion_grupo (id_grupo, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Demanda insatisfecha: materia solicitada en una campana sin grupo formable.
-- El administrador la usa para abrir oferta; se atiende cuando aparece un grupo.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS demanda_tutoria (
  id_demanda INT AUTO_INCREMENT PRIMARY KEY,
  id_periodo INT NOT NULL,
  id_materia INT NOT NULL,
  id_estudiante INT NOT NULL,
  estado ENUM('pendiente','atendida','cancelada') NOT NULL DEFAULT 'pendiente',
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_demanda_periodo FOREIGN KEY (id_periodo) REFERENCES periodos(id_periodo) ON DELETE CASCADE,
  CONSTRAINT fk_demanda_materia FOREIGN KEY (id_materia) REFERENCES materias(id_materia),
  CONSTRAINT fk_demanda_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante) ON DELETE CASCADE,
  UNIQUE KEY uq_demanda (id_periodo, id_materia, id_estudiante),
  INDEX idx_demanda_periodo_estado (id_periodo, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
