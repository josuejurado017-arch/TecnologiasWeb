-- De "Aulas" a "Espacios de tutoria".
--
-- El sistema gestiona tutorias, no infraestructura: no conoce la ocupacion real
-- de las aulas de la UPDS, asi que deja de reservarlas.
--
-- 1. espacios_tutoria: categorias (Aula presencial, Laboratorio de computacion,
--    Microsoft Teams, Google Meet, Zoom). No tienen capacidad ni ocupacion. Hay
--    una predeterminada por modalidad: la que el motor asigna al crear un grupo.
-- 2. La modalidad del grupo sale de la configuracion academica:
--      materias.modalidad_requerida (libre/presencial/virtual) manda sobre
--      tutor_materia_config.modalidad; si queda 'ambas' decide
--      periodos.modalidad_ambas (virtual por defecto).
-- 3. La ubicacion real (aula fisica en texto o enlace por grupo) vive en
--    grupos_tutoria y la registra la coordinacion cuando corresponde; el tutor
--    puede proponer un enlace virtual (enlace_propuesto). Sin ubicacion el grupo
--    queda en "Ubicacion pendiente".
-- 4. grupo_ubicacion_historial guarda cada cambio de modalidad, espacio,
--    ubicacion o enlace, y cada propuesta del tutor.
-- 5. Los grupos existentes conservan lo que mostraban: el nombre y bloque del
--    aula pasan a su ubicacion, y el enlace de la sala virtual a su enlace.
--    grupos_tutoria.id_aula se reemplaza por id_espacio y aulas queda como
--    aulas_legado hasta 030_drop_aulas_legado.sql.
--
-- Compatible con MySQL 8.4 (Docker) y MariaDB 10.4 (XAMPP): los cambios
-- condicionales van en un procedimiento temporal porque MySQL no admite
-- ADD COLUMN IF NOT EXISTS. Idempotente: se puede volver a ejecutar.
-- Ejecutar despues de 028_aprobacion_tutores_grupos.sql sobre testdb.

USE testdb;

-- ------------------------------------------------------------------
-- 1. Catalogo de espacios
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS espacios_tutoria (
  id_espacio INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  modalidad ENUM('presencial','virtual') NOT NULL,
  descripcion VARCHAR(255) NULL,
  predeterminado TINYINT(1) NOT NULL DEFAULT 0,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_espacio),
  UNIQUE KEY uq_espacio_nombre (nombre),
  KEY idx_espacio_modalidad (modalidad, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO espacios_tutoria (nombre, modalidad, descripcion, predeterminado) VALUES
  ('Aula presencial', 'presencial', 'Tutoría en un aula de la universidad. La coordinación indica el aula concreta.', 1),
  ('Laboratorio de computación', 'presencial', 'Tutoría práctica en un laboratorio de computación.', 0),
  ('Microsoft Teams', 'virtual', 'Reunión de Microsoft Teams con enlace propio del grupo.', 0),
  ('Google Meet', 'virtual', 'Reunión de Google Meet con enlace propio del grupo.', 1),
  ('Zoom', 'virtual', 'Reunión de Zoom con enlace propio del grupo.', 0);

-- ------------------------------------------------------------------
-- 2. Historial de ubicacion
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS grupo_ubicacion_historial (
  id_historial INT NOT NULL AUTO_INCREMENT,
  id_grupo INT NOT NULL,
  accion VARCHAR(30) NOT NULL,
  modalidad_anterior ENUM('presencial','virtual') NULL,
  modalidad_nueva ENUM('presencial','virtual') NULL,
  id_espacio_anterior INT NULL,
  id_espacio_nuevo INT NULL,
  ubicacion_anterior VARCHAR(200) NULL,
  ubicacion_nueva VARCHAR(200) NULL,
  enlace_anterior VARCHAR(300) NULL,
  enlace_nuevo VARCHAR(300) NULL,
  motivo VARCHAR(300) NULL,
  id_usuario INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_historial),
  KEY idx_ubicacion_historial_grupo (id_grupo, fecha),
  CONSTRAINT fk_ubicacion_historial_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria (id_grupo) ON DELETE CASCADE,
  CONSTRAINT fk_ubicacion_historial_espacio_ant FOREIGN KEY (id_espacio_anterior) REFERENCES espacios_tutoria (id_espacio),
  CONSTRAINT fk_ubicacion_historial_espacio_nuevo FOREIGN KEY (id_espacio_nuevo) REFERENCES espacios_tutoria (id_espacio),
  CONSTRAINT fk_ubicacion_historial_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- 3. Columnas nuevas y traspaso de datos
-- ------------------------------------------------------------------
DROP PROCEDURE IF EXISTS migrar_029_espacios;

DELIMITER //
CREATE PROCEDURE migrar_029_espacios()
BEGIN
  -- Modalidad academica de la materia.
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'materias' AND COLUMN_NAME = 'modalidad_requerida') THEN
    ALTER TABLE materias
      ADD COLUMN modalidad_requerida ENUM('libre','presencial','virtual') NOT NULL DEFAULT 'libre' AFTER id_carrera;
  END IF;

  -- Regla del periodo para tutores con modalidad 'ambas'.
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'periodos' AND COLUMN_NAME = 'modalidad_ambas') THEN
    ALTER TABLE periodos
      ADD COLUMN modalidad_ambas ENUM('virtual','presencial') NOT NULL DEFAULT 'virtual' AFTER cupo_max_default;
  END IF;

  -- Espacio, ubicacion real y propuesta del tutor en el grupo.
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND COLUMN_NAME = 'id_espacio') THEN
    ALTER TABLE grupos_tutoria
      ADD COLUMN id_espacio INT NULL AFTER id_tutor,
      ADD COLUMN ubicacion VARCHAR(200) NULL AFTER modalidad,
      ADD COLUMN enlace VARCHAR(300) NULL AFTER ubicacion,
      ADD COLUMN fecha_ubicacion DATETIME NULL AFTER enlace,
      ADD COLUMN id_usuario_ubicacion INT NULL AFTER fecha_ubicacion,
      ADD COLUMN id_espacio_propuesto INT NULL AFTER id_usuario_ubicacion,
      ADD COLUMN enlace_propuesto VARCHAR(300) NULL AFTER id_espacio_propuesto,
      ADD COLUMN fecha_propuesta DATETIME NULL AFTER enlace_propuesto,
      ADD COLUMN id_usuario_propuesta INT NULL AFTER fecha_propuesta;
  END IF;

  -- Traspaso desde aulas: solo mientras exista grupos_tutoria.id_aula.
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND COLUMN_NAME = 'id_aula') THEN

    -- La modalidad guardada en el grupo manda; el aula aporta el lugar o el enlace.
    UPDATE grupos_tutoria g
    INNER JOIN aulas a ON a.id_aula = g.id_aula
    SET g.id_espacio = (
          SELECT e.id_espacio FROM espacios_tutoria e
          WHERE e.nombre = CASE
            WHEN g.modalidad = 'virtual' AND a.tipo = 'virtual' AND a.plataforma LIKE '%Zoom%' THEN 'Zoom'
            WHEN g.modalidad = 'virtual' AND a.tipo = 'virtual' AND a.plataforma LIKE '%Teams%' THEN 'Microsoft Teams'
            WHEN g.modalidad = 'virtual' THEN 'Google Meet'
            WHEN a.nombre LIKE 'Laboratorio%' THEN 'Laboratorio de computación'
            ELSE 'Aula presencial'
          END),
        g.ubicacion = IF(g.modalidad = 'presencial' AND a.tipo = 'fisica',
                         LEFT(CONCAT(a.nombre, IFNULL(CONCAT(' - ', a.ubicacion), '')), 200), NULL),
        g.enlace = IF(g.modalidad = 'virtual' AND a.tipo = 'virtual', a.enlace, NULL)
    WHERE g.id_espacio IS NULL;

    UPDATE grupos_tutoria
    SET fecha_ubicacion = fecha_registro
    WHERE fecha_ubicacion IS NULL AND (ubicacion IS NOT NULL OR enlace IS NOT NULL);

    INSERT INTO grupo_ubicacion_historial
      (id_grupo, accion, modalidad_nueva, id_espacio_nuevo, ubicacion_nueva, enlace_nuevo, motivo, fecha)
    SELECT g.id_grupo, 'migrada', g.modalidad, g.id_espacio, g.ubicacion, g.enlace,
           LEFT(CONCAT('Migrado desde el antiguo módulo de aulas (', a.nombre, ').'), 300), g.fecha_registro
    FROM grupos_tutoria g
    INNER JOIN aulas a ON a.id_aula = g.id_aula
    WHERE NOT EXISTS (SELECT 1 FROM grupo_ubicacion_historial h
                      WHERE h.id_grupo = g.id_grupo AND h.accion = 'migrada');

    IF EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
               WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND CONSTRAINT_NAME = 'fk_grupo_aula') THEN
      ALTER TABLE grupos_tutoria DROP FOREIGN KEY fk_grupo_aula;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND INDEX_NAME = 'idx_grupo_aula_dia') THEN
      ALTER TABLE grupos_tutoria DROP INDEX idx_grupo_aula_dia;
    END IF;
    ALTER TABLE grupos_tutoria DROP COLUMN id_aula;
  END IF;

  -- Red de seguridad: ningun grupo queda sin espacio (predeterminado de su modalidad).
  UPDATE grupos_tutoria g
  SET g.id_espacio = (SELECT e.id_espacio FROM espacios_tutoria e
                      WHERE e.modalidad = g.modalidad AND e.predeterminado = 1
                      ORDER BY e.id_espacio LIMIT 1)
  WHERE g.id_espacio IS NULL;

  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'grupos_tutoria' AND CONSTRAINT_NAME = 'fk_grupo_espacio') THEN
    ALTER TABLE grupos_tutoria
      MODIFY COLUMN id_espacio INT NOT NULL,
      ADD KEY idx_grupo_espacio (id_espacio),
      ADD CONSTRAINT fk_grupo_espacio FOREIGN KEY (id_espacio) REFERENCES espacios_tutoria (id_espacio),
      ADD CONSTRAINT fk_grupo_espacio_propuesto FOREIGN KEY (id_espacio_propuesto) REFERENCES espacios_tutoria (id_espacio),
      ADD CONSTRAINT fk_grupo_usuario_ubicacion FOREIGN KEY (id_usuario_ubicacion) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
      ADD CONSTRAINT fk_grupo_usuario_propuesta FOREIGN KEY (id_usuario_propuesta) REFERENCES usuarios (id_usuario) ON DELETE SET NULL;
  END IF;

  -- El catalogo de aulas queda de respaldo hasta 030_drop_aulas_legado.sql.
  IF EXISTS (SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aulas')
     AND NOT EXISTS (SELECT 1 FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aulas_legado') THEN
    RENAME TABLE aulas TO aulas_legado;
  END IF;
END //
DELIMITER ;

CALL migrar_029_espacios();
DROP PROCEDURE migrar_029_espacios;
