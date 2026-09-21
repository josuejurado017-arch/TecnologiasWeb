-- Rediseno institucional de tutorias UPDS (Fase: simplificacion de autorizacion).
-- Elimina la matriz de permisos dinamica. La autorizacion pasa a reglas fijas por
-- rol en codigo (includes/Auth.php: administrador/tutor/estudiante).
-- Se CONSERVA la tabla `roles` (referenciada por usuarios.id_rol, login y registro).
-- Ejecutar despues de 016_drop_modelo_individual.sql sobre testdb.

USE testdb;

-- Orden: primero las tablas hijas (FK), luego el catalogo de modulos.
DROP TABLE IF EXISTS permisos_usuario;
DROP TABLE IF EXISTS permisos_rol;
DROP TABLE IF EXISTS modulos_sistema;
