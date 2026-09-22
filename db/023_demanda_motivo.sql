-- Rediseno institucional de tutorias UPDS (Fase B: demanda real con motivo).
-- Hasta ahora demanda_tutoria solo guardaba "pendiente" sin decir por que: no
-- distinguia "nadie dicta esta materia" (accion: habilitar/reclutar tutor) de
-- "hay tutor pero ningun horario compatible" (accion: ampliar disponibilidad)
-- ni de "su grupo fue cancelado". Se agrega el motivo y la fecha de atencion
-- para medir demanda real, tiempo de espera y conversion demanda -> grupo.
-- Ejecutar despues de 022_tutor_materia_turno_dias.sql sobre testdb.

USE testdb;

ALTER TABLE demanda_tutoria
  ADD COLUMN motivo ENUM('sin_tutor','sin_horario','grupo_cancelado') NOT NULL DEFAULT 'sin_horario' AFTER estado,
  ADD COLUMN fecha_atencion DATETIME NULL AFTER fecha_solicitud,
  ADD INDEX idx_demanda_materia_estado (id_materia, estado);

-- Retroalimentar las filas existentes: antes de esta migracion solo podia
-- registrarse demanda en materias CON oferta (fallo de horario/aula), asi que
-- el DEFAULT 'sin_horario' es correcto salvo que hoy la materia ya no tenga
-- ningun tutor habilitado con disponibilidad.
UPDATE demanda_tutoria d
SET d.motivo = 'sin_tutor'
WHERE d.estado = 'pendiente'
  AND NOT EXISTS (
      SELECT 1
      FROM tutor_materia tm
      INNER JOIN tutores t ON t.id_tutor = tm.id_tutor
      INNER JOIN usuarios u ON u.id_usuario = t.id_usuario AND u.estado = 'activo'
      INNER JOIN disponibilidad_tutor dt ON dt.id_tutor = t.id_tutor
      WHERE tm.id_materia = d.id_materia
  );
