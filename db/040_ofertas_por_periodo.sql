-- Cada oferta y sus turnos pertenecen a una campaña. Las ofertas legadas se
-- atribuyen únicamente al período activo; los períodos siguientes exigen renovar.
-- Ejecutar después de 039_gestion_manual_grupos.sql.
USE testdb;

ALTER TABLE tutor_materia_config
  ADD COLUMN id_periodo INT NULL AFTER id_materia;
UPDATE tutor_materia_config SET id_periodo =
  (SELECT id_periodo FROM periodos WHERE estado = 'activa' ORDER BY fecha_inicio DESC LIMIT 1);
-- Si se migra sin campaña activa, conservar las ofertas como historial del
-- período más reciente; nunca se activan en una campaña futura.
UPDATE tutor_materia_config SET id_periodo =
  (SELECT id_periodo FROM periodos ORDER BY fecha_inicio DESC LIMIT 1)
  WHERE id_periodo IS NULL;

ALTER TABLE tutor_materia_turno ADD COLUMN id_periodo INT NULL AFTER id_materia;
UPDATE tutor_materia_turno tt INNER JOIN tutor_materia_config c
  ON c.id_tutor = tt.id_tutor AND c.id_materia = tt.id_materia
  SET tt.id_periodo = c.id_periodo;

ALTER TABLE tutor_materia_config DROP PRIMARY KEY,
  MODIFY id_periodo INT NOT NULL,
  ADD PRIMARY KEY (id_tutor, id_materia, id_periodo),
  ADD KEY idx_config_periodo (id_periodo, estado),
  ADD CONSTRAINT fk_config_periodo FOREIGN KEY (id_periodo) REFERENCES periodos(id_periodo);
ALTER TABLE tutor_materia_turno DROP PRIMARY KEY,
  MODIFY id_periodo INT NOT NULL,
  ADD PRIMARY KEY (id_tutor, id_materia, id_periodo, turno),
  ADD CONSTRAINT fk_turno_periodo FOREIGN KEY (id_periodo) REFERENCES periodos(id_periodo);

ALTER TABLE tutor_materia_historial
  ADD COLUMN id_periodo INT NULL AFTER id_materia,
  ADD KEY idx_historial_periodo (id_periodo);
UPDATE tutor_materia_historial h SET id_periodo =
  (SELECT c.id_periodo FROM tutor_materia_config c
   WHERE c.id_tutor = h.id_tutor AND c.id_materia = h.id_materia LIMIT 1);

-- Normalizar las aprobaciones legadas que ya superaban el límite. Priorizar
-- materias con grupos vigentes; una oferta con grupo no se desactiva a mitad
-- de campaña. Las demás permanecen como registro rechazado, no se borran.
CREATE TEMPORARY TABLE ofertas_excedentes AS
 SELECT c.id_tutor, c.id_materia, c.id_periodo,
        ROW_NUMBER() OVER (PARTITION BY c.id_tutor, c.id_periodo
          ORDER BY (SELECT COUNT(*) FROM grupos_tutoria g WHERE g.id_tutor = c.id_tutor
              AND g.id_materia = c.id_materia AND g.id_periodo = c.id_periodo AND g.estado <> 'cancelado') DESC,
              c.fecha_revision, c.id_materia) AS orden
 FROM tutor_materia_config c WHERE c.estado = 'aprobado';
UPDATE tutor_materia_config c INNER JOIN ofertas_excedentes x
 ON x.id_tutor = c.id_tutor AND x.id_materia = c.id_materia AND x.id_periodo = c.id_periodo
 SET c.estado = 'rechazado', c.motivo_rechazo = 'Excede el máximo de dos materias por período.'
 WHERE x.orden > 2 AND NOT EXISTS
   (SELECT 1 FROM grupos_tutoria g WHERE g.id_tutor = c.id_tutor AND g.id_materia = c.id_materia
     AND g.id_periodo = c.id_periodo AND g.estado <> 'cancelado');
DROP TEMPORARY TABLE ofertas_excedentes;

CREATE TEMPORARY TABLE turnos_repetidos AS
 SELECT tt.id_tutor, tt.id_materia, tt.id_periodo, tt.turno,
        ROW_NUMBER() OVER (PARTITION BY tt.id_periodo, tt.id_materia, tt.turno
          ORDER BY (SELECT COUNT(*) FROM grupos_tutoria g WHERE g.id_tutor = tt.id_tutor
              AND g.id_materia = tt.id_materia AND g.id_periodo = tt.id_periodo AND g.estado <> 'cancelado') DESC,
              c.fecha_revision, tt.id_tutor) AS orden
 FROM tutor_materia_turno tt INNER JOIN tutor_materia_config c
  ON c.id_tutor = tt.id_tutor AND c.id_materia = tt.id_materia AND c.id_periodo = tt.id_periodo
 WHERE c.estado = 'aprobado';
UPDATE tutor_materia_config c INNER JOIN turnos_repetidos x
 ON x.id_tutor = c.id_tutor AND x.id_materia = c.id_materia AND x.id_periodo = c.id_periodo
 SET c.estado = 'rechazado', c.motivo_rechazo = 'Turno ya cubierto en este período. Requiere nueva revisión.'
 WHERE x.orden > 1 AND NOT EXISTS
   (SELECT 1 FROM grupos_tutoria g WHERE g.id_tutor = c.id_tutor AND g.id_materia = c.id_materia
     AND g.id_periodo = c.id_periodo AND g.estado <> 'cancelado');
DROP TEMPORARY TABLE turnos_repetidos;
