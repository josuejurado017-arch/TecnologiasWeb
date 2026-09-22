-- Rediseno institucional de tutorias UPDS (Fase 1: preferencias de tutor por materia).
-- Capa adicional de filtro para el motor de asignacion automatica. NO reemplaza ni
-- modifica disponibilidad_tutor: esa tabla sigue siendo la fuente de verdad de la
-- disponibilidad real del tutor. Estas tablas solo restringen, por materia, en que
-- turnos/modalidad/sabados/cupo prefiere el tutor que se le asignen estudiantes.
-- Un tutor sin fila en tutor_materia_config conserva el comportamiento actual
-- (toda su disponibilidad_tutor sirve para cualquier materia que dicte).
-- Ejecutar despues de 019_carnet_identidad.sql sobre testdb.

USE testdb;

-- ------------------------------------------------------------------
-- Preferencia 1:1 por (tutor, materia).
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tutor_materia_config (
  id_tutor            INT NOT NULL,
  id_materia          INT NOT NULL,
  modalidad           ENUM('presencial','virtual','ambas') NOT NULL DEFAULT 'ambas',
  disponible_sabados  TINYINT(1) NOT NULL DEFAULT 0,
  cupo_recomendado    TINYINT UNSIGNED NULL,
  fecha_registro      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_tutor, id_materia),
  CONSTRAINT fk_tmc_tutor_materia FOREIGN KEY (id_tutor, id_materia)
    REFERENCES tutor_materia (id_tutor, id_materia) ON DELETE CASCADE,
  CONSTRAINT chk_tmc_cupo CHECK (cupo_recomendado IS NULL OR cupo_recomendado IN (10, 15, 20, 25))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Turnos preferidos (Lunes a Viernes) seleccionados para esa materia. Los
-- rangos horarios de cada turno viven en codigo (TutorMateriaConfig::TURNOS),
-- no en la base, para tener una sola fuente de verdad.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tutor_materia_turno (
  id_tutor    INT NOT NULL,
  id_materia  INT NOT NULL,
  turno       ENUM('Manana','Mediodia','Tarde','Noche') NOT NULL,
  PRIMARY KEY (id_tutor, id_materia, turno),
  CONSTRAINT fk_tmt_tutor_materia FOREIGN KEY (id_tutor, id_materia)
    REFERENCES tutor_materia (id_tutor, id_materia) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Franjas de sabado seleccionadas para esa materia (solo aplica si
-- tutor_materia_config.disponible_sabados = 1).
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tutor_materia_sabado (
  id_tutor    INT NOT NULL,
  id_materia  INT NOT NULL,
  franja      ENUM('08:00-10:00','10:00-12:00','14:00-16:00') NOT NULL,
  PRIMARY KEY (id_tutor, id_materia, franja),
  CONSTRAINT fk_tms_tutor_materia FOREIGN KEY (id_tutor, id_materia)
    REFERENCES tutor_materia (id_tutor, id_materia) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
