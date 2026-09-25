-- Limpieza definitiva del antiguo modulo de aulas.
--
-- 029_espacios_tutoria.sql traspaso el lugar y el enlace de cada grupo a
-- grupos_tutoria (ubicacion / enlace) y dejo el catalogo como aulas_legado para
-- poder comparar. Ejecutar solo cuando la migracion este validada en uso real:
-- es irreversible (el respaldo previo a 029 es la unica forma de volver atras).
-- Ejecutar despues de 029_espacios_tutoria.sql sobre testdb.

USE testdb;

DROP TABLE IF EXISTS aulas_legado;
