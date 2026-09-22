-- Rediseno institucional de tutorias UPDS (Fase 4: patron semanal por materia).
-- Revierte la matriz turno x dia introducida en 022_tutor_materia_turno_dias.sql.
--
-- Motivo (medido sobre datos reales antes de migrar): de 8 combinaciones
-- turno-materia configuradas, 6 tenian los 5 dias marcados. La granularidad por
-- dia costaba 20 checkboxes por materia y en el 75% de los casos no aportaba
-- informacion: el tutor marcaba todo. Mantener 20 checkboxes por materia
-- encarecia la pantalla sin darle nada extra al motor de asignacion.
--
-- Modelo nuevo: el tutor elige UN patron semanal por materia, y los turnos
-- seleccionados aplican a los dias de ese patron. El sabado NO entra en el
-- patron: se mantiene como opt-in aparte en tutor_materia_sabado, porque tiene
-- franjas propias que no coinciden con los rangos de turno.
-- Ejecutar despues de 025_horarios_por_materia.sql sobre testdb: esa migracion
-- siembra tutor_materia_turno a partir de disponibilidad_tutor y debe correr
-- ANTES de que esta colapse la columna dia_semana.

USE testdb;

-- ------------------------------------------------------------------
-- 1. Patron semanal a nivel (tutor, materia).
--    'uno' es el unico patron que necesita precisar el dia (patron_dia);
--    los demas derivan sus dias de codigo (TutorMateriaConfig::PATRONES).
-- ------------------------------------------------------------------
ALTER TABLE tutor_materia_config
  ADD COLUMN patron ENUM('lmv','mj','diario','uno') NOT NULL DEFAULT 'diario' AFTER modalidad,
  ADD COLUMN patron_dia ENUM('Lunes','Martes','Miercoles','Jueves','Viernes') NULL AFTER patron;

-- ------------------------------------------------------------------
-- 2. Derivar el patron de cada configuracion existente a partir de la union
--    de dias declarados en sus turnos. Ante cualquier caso que no encaje
--    limpiamente se usa 'diario': es el patron mas amplio, asi la migracion
--    nunca reduce en silencio la disponibilidad de un tutor ya configurado.
-- ------------------------------------------------------------------
UPDATE tutor_materia_config c
INNER JOIN (
    SELECT id_tutor, id_materia,
           COUNT(*) AS n_dias,
           SUM(dia_semana IN ('Lunes','Miercoles','Viernes')) AS n_lmv,
           SUM(dia_semana IN ('Martes','Jueves')) AS n_mj,
           MIN(dia_semana) AS dia_unico
    FROM (SELECT DISTINCT id_tutor, id_materia, dia_semana FROM tutor_materia_turno) d
    GROUP BY id_tutor, id_materia
) x ON x.id_tutor = c.id_tutor AND x.id_materia = c.id_materia
SET c.patron = CASE
        WHEN x.n_dias >= 5        THEN 'diario'
        WHEN x.n_dias = 1         THEN 'uno'
        WHEN x.n_dias = x.n_mj    THEN 'mj'
        WHEN x.n_dias = x.n_lmv   THEN 'lmv'
        ELSE 'diario'
    END,
    c.patron_dia = CASE WHEN x.n_dias = 1 THEN x.dia_unico ELSE NULL END;

-- ------------------------------------------------------------------
-- 3. Colapsar tutor_materia_turno a (tutor, materia, turno). No se puede
--    DROP COLUMN directo: la PK incluye dia_semana y al quitarla quedarian
--    filas duplicadas. Se reconstruye la tabla con las filas distintas.
--    La FK se suelta primero porque InnoDB exige nombres de constraint
--    unicos por esquema: sin esto, crear la tabla nueva con el mismo nombre
--    de constraint falla con errno 121.
-- ------------------------------------------------------------------
ALTER TABLE tutor_materia_turno DROP FOREIGN KEY fk_tmt_tutor_materia;
ALTER TABLE tutor_materia_turno RENAME TO tutor_materia_turno_viejo;

CREATE TABLE tutor_materia_turno (
  id_tutor    INT NOT NULL,
  id_materia  INT NOT NULL,
  turno       ENUM('Manana','Mediodia','Tarde','Noche') NOT NULL,
  PRIMARY KEY (id_tutor, id_materia, turno),
  CONSTRAINT fk_tmt_tutor_materia FOREIGN KEY (id_tutor, id_materia)
    REFERENCES tutor_materia (id_tutor, id_materia) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tutor_materia_turno (id_tutor, id_materia, turno)
SELECT DISTINCT id_tutor, id_materia, turno FROM tutor_materia_turno_viejo;

DROP TABLE tutor_materia_turno_viejo;
