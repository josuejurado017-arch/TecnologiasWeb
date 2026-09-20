-- Rediseno institucional de tutorias UPDS (Fase 4: historial de grupos).
-- Trazabilidad de cambios de estado, cancelaciones y reprogramaciones de grupos.
-- Ejecutar despues de 013_evaluacion_grupo.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS historial_grupo (
  id_historial BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_grupo INT NOT NULL,
  tipo_evento VARCHAR(40) NOT NULL,
  estado_anterior VARCHAR(20) NULL,
  estado_nuevo VARCHAR(20) NULL,
  id_usuario INT NULL,
  motivo VARCHAR(500) NULL,
  fecha_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_histg_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria(id_grupo) ON DELETE CASCADE,
  CONSTRAINT fk_histg_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
  INDEX idx_histg_grupo (id_grupo, fecha_evento),
  INDEX idx_histg_tipo (tipo_evento, fecha_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
