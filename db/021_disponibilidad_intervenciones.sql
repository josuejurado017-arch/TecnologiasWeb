-- Rediseno institucional de tutorias UPDS (Fase 2: supervision de disponibilidad).
-- Trazabilidad de intervenciones administrativas excepcionales sobre la
-- disponibilidad de un tutor. La disponibilidad_tutor sigue siendo autogestion
-- del tutor; esta tabla solo registra cuando un administrador crea, edita o
-- elimina un bloque en nombre del tutor, con motivo obligatorio.
-- Mismo patron que historial_grupo (db/014_historial_grupo.sql).
-- Ejecutar despues de 020_tutor_materia_preferencias.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS disponibilidad_intervenciones (
  id_intervencion   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_disponibilidad INT NULL,
  id_tutor          INT NOT NULL,
  tipo_accion       ENUM('creacion','edicion','eliminacion') NOT NULL,
  id_usuario_admin  INT NOT NULL,
  motivo            VARCHAR(500) NOT NULL,
  fecha_evento      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dint_disponibilidad FOREIGN KEY (id_disponibilidad)
    REFERENCES disponibilidad_tutor (id_disponibilidad) ON DELETE SET NULL,
  CONSTRAINT fk_dint_tutor FOREIGN KEY (id_tutor)
    REFERENCES tutores (id_tutor) ON DELETE CASCADE,
  CONSTRAINT fk_dint_usuario_admin FOREIGN KEY (id_usuario_admin)
    REFERENCES usuarios (id_usuario),
  INDEX idx_dint_tutor (id_tutor, fecha_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
