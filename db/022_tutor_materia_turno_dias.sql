-- Rediseno institucional de tutorias UPDS (Fase 2: dias especificos por turno).
-- Hasta ahora un turno seleccionado en "Mis materias -> configurar" aplicaba de
-- Lunes a Viernes en bloque. Se agrega dia_semana para que el tutor pueda acotar
-- un turno a dias especificos por materia (ej. "Noche: solo lunes y miercoles"),
-- sin duplicar el dato de disponibilidad real (disponibilidad_tutor no cambia).
-- Ejecutar despues de 021_disponibilidad_intervenciones.sql sobre testdb.

USE testdb;

ALTER TABLE tutor_materia_turno
  ADD COLUMN dia_semana ENUM('Lunes','Martes','Miercoles','Jueves','Viernes') NOT NULL DEFAULT 'Lunes' AFTER turno;

-- Ampliar la PK primero: las filas existentes (turno sin dia, que aplicaban de
-- lunes a viernes) quedaron en 'Lunes' por el DEFAULT; hay que poder insertar
-- los otros 4 dias habiles para esa misma fila antes de que el motor las lea.
ALTER TABLE tutor_materia_turno
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (id_tutor, id_materia, turno, dia_semana);

-- Preservar el comportamiento anterior: expandir cada fila ya existente a los
-- otros 4 dias habiles para no reducir silenciosamente la disponibilidad real
-- de tutores ya configurados antes de esta migracion.
INSERT INTO tutor_materia_turno (id_tutor, id_materia, turno, dia_semana)
SELECT id_tutor, id_materia, turno, dia
FROM tutor_materia_turno
CROSS JOIN (SELECT 'Martes' AS dia UNION ALL SELECT 'Miercoles' UNION ALL SELECT 'Jueves' UNION ALL SELECT 'Viernes') dias
WHERE dia_semana = 'Lunes';
