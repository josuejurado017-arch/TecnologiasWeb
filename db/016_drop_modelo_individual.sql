-- Rediseno institucional de tutorias UPDS (Fase 6b: retiro del modelo individual).
-- Elimina las tablas del modelo de tutoria individual, ya sin referencias en el codigo
-- (dashboard, reportes y notificaciones migrados al modelo de grupos/inscripciones).
-- Ejecutar despues de 015_poda_modelo_viejo.sql sobre testdb.
--
-- IMPORTANTE: hacer respaldo antes. Las tablas 'tutorias' y sus dependientes
-- (asistencias_tutorias, evaluaciones_tutoria, historial_tutorias) contienen datos
-- historicos/demo que NO se migran (el modelo nuevo parte de campanas).

USE testdb;

-- La tabla notificaciones se conserva; solo se suelta su FK al modelo viejo.
-- La columna id_tutoria queda como historica (nullable, sin uso nuevo).
ALTER TABLE notificaciones DROP FOREIGN KEY fk_notificacion_tutoria;

DROP TABLE IF EXISTS asistencias_tutorias;
DROP TABLE IF EXISTS evaluaciones_tutoria;
DROP TABLE IF EXISTS historial_tutorias;
DROP TABLE IF EXISTS tutorias;
