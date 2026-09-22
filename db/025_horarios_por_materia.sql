-- Rediseno institucional de tutorias UPDS (Fase 2: horarios solo por materia).
-- "Mi disponibilidad" (disponibilidad_tutor, bloques globales de hora libre) se
-- elimina del portal: el motor de asignacion usa unicamente los turnos x dias y
-- franjas de sabado que el tutor configura por materia en "Mis materias"
-- (tutor_materia_config / tutor_materia_turno / tutor_materia_sabado).
--
-- Antes, una materia SIN configuracion usaba toda la disponibilidad_tutor del
-- tutor. Para no quitarle oferta a esos tutores, esta migracion convierte sus
-- bloques en configuracion por materia: cada bloque que se solapa con un turno
-- (o franja de sabado) en un dia habilita ese turno/franja ese dia. Rangos
-- iguales a TutorMateriaConfig::TURNOS / FRANJAS_SABADO. Las materias que ya
-- tenian configuracion no se tocan.
--
-- disponibilidad_tutor y disponibilidad_intervenciones se conservan (solo
-- lectura historica); una migracion posterior puede eliminarlas.
-- Ejecutar despues de 024_grupo_dias.sql sobre testdb.

USE testdb;

-- Pares (tutor, materia) sin configuracion previa: los unicos que se migran.
CREATE TEMPORARY TABLE tmp_pares_sin_config AS
SELECT tm.id_tutor, tm.id_materia
FROM tutor_materia tm
WHERE NOT EXISTS (
  SELECT 1 FROM tutor_materia_config c
  WHERE c.id_tutor = tm.id_tutor AND c.id_materia = tm.id_materia
);

INSERT IGNORE INTO tutor_materia_turno (id_tutor, id_materia, turno, dia_semana)
SELECT DISTINCT p.id_tutor, p.id_materia, t.turno, d.dia_semana
FROM tmp_pares_sin_config p
INNER JOIN disponibilidad_tutor d ON d.id_tutor = p.id_tutor
INNER JOIN (
  SELECT 'Manana' AS turno, TIME '07:30:00' AS inicio, TIME '10:30:00' AS fin
  UNION ALL SELECT 'Mediodia', TIME '11:00:00', TIME '14:00:00'
  UNION ALL SELECT 'Tarde', TIME '15:00:00', TIME '18:00:00'
  UNION ALL SELECT 'Noche', TIME '19:00:00', TIME '22:00:00'
) t ON d.hora_inicio < t.fin AND d.hora_fin > t.inicio
WHERE d.dia_semana IN ('Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes');

INSERT IGNORE INTO tutor_materia_sabado (id_tutor, id_materia, franja)
SELECT DISTINCT p.id_tutor, p.id_materia, f.franja
FROM tmp_pares_sin_config p
INNER JOIN disponibilidad_tutor d ON d.id_tutor = p.id_tutor AND d.dia_semana = 'Sabado'
INNER JOIN (
  SELECT '08:00-10:00' AS franja, TIME '08:00:00' AS inicio, TIME '10:00:00' AS fin
  UNION ALL SELECT '10:00-12:00', TIME '10:00:00', TIME '12:00:00'
  UNION ALL SELECT '14:00-16:00', TIME '14:00:00', TIME '16:00:00'
) f ON d.hora_inicio < f.fin AND d.hora_fin > f.inicio;

-- Configuracion base (modalidad 'ambas', sin cupo recomendado: mismo
-- comportamiento que antes) solo para los pares que obtuvieron algun horario.
INSERT INTO tutor_materia_config (id_tutor, id_materia, modalidad, disponible_sabados, cupo_recomendado)
SELECT p.id_tutor, p.id_materia, 'ambas',
       EXISTS (SELECT 1 FROM tutor_materia_sabado s WHERE s.id_tutor = p.id_tutor AND s.id_materia = p.id_materia),
       NULL
FROM tmp_pares_sin_config p
WHERE EXISTS (SELECT 1 FROM tutor_materia_turno x WHERE x.id_tutor = p.id_tutor AND x.id_materia = p.id_materia)
   OR EXISTS (SELECT 1 FROM tutor_materia_sabado s WHERE s.id_tutor = p.id_tutor AND s.id_materia = p.id_materia);

DROP TEMPORARY TABLE tmp_pares_sin_config;
