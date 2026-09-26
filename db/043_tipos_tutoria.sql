-- Tipos de tutoria (catalogo libre) y periodos por tipo.
--
-- El administrador define tipos con el nombre que quiera (Pregrado, Postgrado,
-- Nivelacion...). Cada periodo pertenece a un tipo y puede haber UN periodo
-- activo por tipo al mismo tiempo: tutorias de distinto tipo corren en paralelo.
-- El portal trabaja sobre el tipo elegido en la barra superior (TipoTutoria::actual).
--
--   tipos_tutoria.duracion_max_dias: tope de duracion de sus periodos; NULL = sin
--   tope. "Pregrado" conserva la regla anterior (42 dias: ultimo mes del semestre).
--
-- Los periodos existentes pasan a "Pregrado" (id 1). periodos.id_tipo_tutoria
-- tiene DEFAULT 1 para que los scripts anteriores que insertan periodos sigan
-- funcionando.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4 (procedimiento temporal). Idempotente.
-- Ejecutar despues de 042_division_grupos.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS tipos_tutoria (
  id_tipo_tutoria INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  duracion_max_dias SMALLINT UNSIGNED NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_tipo_tutoria),
  UNIQUE KEY uq_tipo_tutoria_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tipos_tutoria (id_tipo_tutoria, nombre, descripcion, duracion_max_dias)
SELECT 1, 'Pregrado', 'Último mes del semestre (julio o enero).', 42
WHERE NOT EXISTS (SELECT 1 FROM tipos_tutoria WHERE id_tipo_tutoria = 1);

DROP PROCEDURE IF EXISTS migrar_043_tipos;

DELIMITER //
CREATE PROCEDURE migrar_043_tipos()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'periodos' AND COLUMN_NAME = 'id_tipo_tutoria') THEN
    ALTER TABLE periodos
      ADD COLUMN id_tipo_tutoria INT NOT NULL DEFAULT 1 AFTER nombre,
      ADD INDEX idx_periodo_tipo_estado (id_tipo_tutoria, estado),
      ADD CONSTRAINT fk_periodo_tipo_tutoria FOREIGN KEY (id_tipo_tutoria) REFERENCES tipos_tutoria (id_tipo_tutoria);
  END IF;
END //
DELIMITER ;

CALL migrar_043_tipos();
DROP PROCEDURE migrar_043_tipos;
