-- Division de un grupo lleno en dos grupos parejos.
--
-- La coordinacion elige un segundo tutor, ve la vista previa (quienes se quedan,
-- quienes pasan al grupo nuevo y quienes entran desde la espera) y envia la
-- propuesta. El tutor la acepta o la rechaza desde "Mis grupos"; al aceptarla se
-- crea el grupo nuevo en el mismo turno y con los mismos dias, y se trasladan los
-- ultimos en inscribirse. Solo se divide un grupo sin asistencia registrada.
--
-- 1. grupo_divisiones: cada propuesta y su resultado.
-- 2. inscripciones.estado 'trasladada': la inscripcion del grupo de origen de un
--    estudiante movido (no es una baja); inscripciones.origen 'traslado': su
--    inscripcion en el grupo nuevo.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 041_integridad_historial.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS grupo_divisiones (
  id_division INT NOT NULL AUTO_INCREMENT,
  id_grupo_origen INT NOT NULL,
  id_tutor INT NOT NULL,
  estado ENUM('pendiente','aceptada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
  motivo VARCHAR(300) NULL,
  id_grupo_nuevo INT NULL,
  trasladados SMALLINT UNSIGNED NULL,
  desde_espera SMALLINT UNSIGNED NULL,
  id_usuario_solicitud INT NULL,
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_respuesta DATETIME NULL,
  PRIMARY KEY (id_division),
  KEY idx_division_origen (id_grupo_origen, estado),
  KEY idx_division_tutor (id_tutor, estado),
  CONSTRAINT fk_division_origen FOREIGN KEY (id_grupo_origen) REFERENCES grupos_tutoria (id_grupo) ON DELETE RESTRICT,
  CONSTRAINT fk_division_nuevo FOREIGN KEY (id_grupo_nuevo) REFERENCES grupos_tutoria (id_grupo) ON DELETE RESTRICT,
  CONSTRAINT fk_division_tutor FOREIGN KEY (id_tutor) REFERENCES tutores (id_tutor) ON DELETE RESTRICT,
  CONSTRAINT fk_division_usuario FOREIGN KEY (id_usuario_solicitud) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inscripciones
  MODIFY COLUMN estado ENUM('inscrito','lista_espera','cancelada','trasladada') NOT NULL DEFAULT 'inscrito',
  MODIFY COLUMN origen ENUM('auto','manual_admin','traslado') NOT NULL DEFAULT 'auto';
