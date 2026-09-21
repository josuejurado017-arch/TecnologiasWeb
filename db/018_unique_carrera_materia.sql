-- Rediseno institucional de tutorias UPDS (integridad: anti-duplicados).
-- Agrega UNIQUE al nombre de carreras y materias. La colacion utf8mb4_unicode_ci
-- es insensible a mayusculas/acentos/espacios finales, por lo que estas claves
-- bloquean variantes como "Ingenieria de Sistemas" / "INGENIERIA DE SISTEMAS".
-- Ejecutar despues de 017_drop_matriz_permisos.sql sobre testdb.
--
-- NOTA: requiere que no existan duplicados previos. En bases nuevas (Docker) no
-- los hay; en la base local ya se consolidaron manualmente antes de esta migracion.

USE testdb;

ALTER TABLE carreras ADD UNIQUE KEY uq_carrera_nombre (nombre_carrera);
ALTER TABLE materias ADD UNIQUE KEY uq_materia_nombre (nombre_materia);
