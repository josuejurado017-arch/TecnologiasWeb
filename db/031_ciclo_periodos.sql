-- Ciclo de vida de los periodos de tutoria: BORRADOR -> ACTIVO -> CERRADO.
--
-- Un periodo cerrado es historial: no se edita, no se reactiva ni se elimina.
-- Solo se consulta y recibe observaciones administrativas. Las transiciones las
-- hace PeriodosController (activar, cerrar); el estado ya no se elige en un
-- formulario.
--
-- 1. periodos: quien y cuando lo activo y lo cerro, hasta cuando se puede evaluar
--    tras el cierre (plazo de gracia) y un resumen congelado del cierre.
-- 2. periodo_observaciones: notas administrativas, solo se agregan.
-- 3. sesiones_tutoria.estado 'sin_registro': sesion pasada que al cierre no tenia
--    asistencia (no se inventa asistencia ni se la da por cancelada).
--    demanda_tutoria.estado 'vencida': solicitud que el periodo cerro sin atender.
-- 4. Borrar un periodo ya no arrastra su historial: las FK hacia periodos pasan de
--    ON DELETE CASCADE a RESTRICT (grupos, demanda, rechazos).
-- 5. Los periodos que ya estaban cerrados reciben los efectos del cierre.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4 (procedimiento temporal). Idempotente.
-- Ejecutar despues de 029_espacios_tutoria.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS periodo_observaciones (
  id_observacion INT NOT NULL AUTO_INCREMENT,
  id_periodo INT NOT NULL,
  texto VARCHAR(1000) NOT NULL,
  id_usuario INT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_observacion),
  KEY idx_observacion_periodo (id_periodo, fecha),
  CONSTRAINT fk_observacion_periodo FOREIGN KEY (id_periodo) REFERENCES periodos (id_periodo),
  CONSTRAINT fk_observacion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS migrar_031_periodos;

DELIMITER //
CREATE PROCEDURE migrar_031_periodos()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'periodos' AND COLUMN_NAME = 'fecha_cierre') THEN
    ALTER TABLE periodos
      ADD COLUMN fecha_activacion DATETIME NULL AFTER estado,
      ADD COLUMN id_usuario_activacion INT NULL AFTER fecha_activacion,
      ADD COLUMN fecha_cierre DATETIME NULL AFTER id_usuario_activacion,
      ADD COLUMN id_usuario_cierre INT NULL AFTER fecha_cierre,
      ADD COLUMN evaluaciones_hasta DATE NULL AFTER id_usuario_cierre,
      ADD COLUMN resumen_cierre TEXT NULL AFTER evaluaciones_hasta,
      ADD CONSTRAINT fk_periodo_usuario_activacion FOREIGN KEY (id_usuario_activacion) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
      ADD CONSTRAINT fk_periodo_usuario_cierre FOREIGN KEY (id_usuario_cierre) REFERENCES usuarios (id_usuario) ON DELETE SET NULL;
  END IF;

  -- Estados nuevos (se conservan los existentes).
  ALTER TABLE sesiones_tutoria
    MODIFY COLUMN estado ENUM('programada','realizada','cancelada','sin_registro') NOT NULL DEFAULT 'programada';
  ALTER TABLE demanda_tutoria
    MODIFY COLUMN estado ENUM('pendiente','atendida','cancelada','vencida') NOT NULL DEFAULT 'pendiente';

  -- Sin cascada desde periodos: borrar un periodo con historial debe fallar.
  IF EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_grupo_periodo' AND DELETE_RULE = 'CASCADE') THEN
    ALTER TABLE grupos_tutoria DROP FOREIGN KEY fk_grupo_periodo;
    ALTER TABLE grupos_tutoria ADD CONSTRAINT fk_grupo_periodo FOREIGN KEY (id_periodo) REFERENCES periodos (id_periodo);
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_demanda_periodo' AND DELETE_RULE = 'CASCADE') THEN
    ALTER TABLE demanda_tutoria DROP FOREIGN KEY fk_demanda_periodo;
    ALTER TABLE demanda_tutoria ADD CONSTRAINT fk_demanda_periodo FOREIGN KEY (id_periodo) REFERENCES periodos (id_periodo);
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_rechazo_periodo' AND DELETE_RULE = 'CASCADE') THEN
    ALTER TABLE grupo_rechazos DROP FOREIGN KEY fk_rechazo_periodo;
    ALTER TABLE grupo_rechazos ADD CONSTRAINT fk_rechazo_periodo FOREIGN KEY (id_periodo) REFERENCES periodos (id_periodo);
  END IF;

  -- Periodos cerrados antes de esta migracion: efectos del cierre, fechados a su fin.
  UPDATE periodos
  SET fecha_cierre = TIMESTAMP(fecha_fin, '23:59:59'),
      evaluaciones_hasta = DATE_ADD(fecha_fin, INTERVAL 7 DAY)
  WHERE estado = 'cerrada' AND fecha_cierre IS NULL;

  UPDATE demanda_tutoria d INNER JOIN periodos p ON p.id_periodo = d.id_periodo
  SET d.estado = 'vencida'
  WHERE p.estado = 'cerrada' AND d.estado = 'pendiente';

  UPDATE sesiones_tutoria s
  INNER JOIN grupos_tutoria g ON g.id_grupo = s.id_grupo
  INNER JOIN periodos p ON p.id_periodo = g.id_periodo
  SET s.estado = IF(s.fecha < DATE(p.fecha_cierre), 'sin_registro', 'cancelada')
  WHERE p.estado = 'cerrada' AND s.estado = 'programada';

  UPDATE grupos_tutoria g INNER JOIN periodos p ON p.id_periodo = g.id_periodo
  SET g.estado = IF(g.estado IN ('confirmado','en_curso')
                    OR EXISTS (SELECT 1 FROM sesiones_tutoria s WHERE s.id_grupo = g.id_grupo AND s.estado = 'realizada'),
                    'finalizado', 'cancelado'),
      g.motivo_estado = 'Cierre del período',
      g.fecha_estado = p.fecha_cierre
  WHERE p.estado = 'cerrada' AND g.estado IN ('por_aprobar','formacion','confirmado','en_curso');

  UPDATE inscripciones i
  INNER JOIN grupos_tutoria g ON g.id_grupo = i.id_grupo
  INNER JOIN periodos p ON p.id_periodo = g.id_periodo
  SET i.estado = 'cancelada'
  WHERE p.estado = 'cerrada' AND (i.estado = 'lista_espera' OR (g.estado = 'cancelado' AND i.estado = 'inscrito'));
END //
DELIMITER ;

CALL migrar_031_periodos();
DROP PROCEDURE migrar_031_periodos;
