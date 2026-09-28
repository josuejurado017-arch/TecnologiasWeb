-- Datos iniciales. Ejecutar despues de 001_schema.sql.
-- Este script no contiene una contrasena real.

USE testdb;

INSERT INTO roles (nombre_rol)
SELECT 'administrador' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'administrador');
INSERT INTO roles (nombre_rol)
SELECT 'tutor' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'tutor');
INSERT INTO roles (nombre_rol)
SELECT 'estudiante' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'estudiante');

INSERT INTO carreras (nombre_carrera)
SELECT 'Ingenieria de Sistemas'
WHERE NOT EXISTS (SELECT 1 FROM carreras WHERE nombre_carrera = 'Ingenieria de Sistemas');

INSERT INTO materias (nombre_materia, id_carrera)
SELECT 'Base de Datos I', c.id_carrera
FROM carreras c
WHERE c.nombre_carrera = 'Ingenieria de Sistemas'
  AND NOT EXISTS (SELECT 1 FROM materias WHERE nombre_materia = 'Base de Datos I');

INSERT INTO materias (nombre_materia, id_carrera)
SELECT 'Programacion I', c.id_carrera
FROM carreras c
WHERE c.nombre_carrera = 'Ingenieria de Sistemas'
  AND NOT EXISTS (SELECT 1 FROM materias WHERE nombre_materia = 'Programacion I');

INSERT INTO materias (nombre_materia, id_carrera)
SELECT 'Tecnologia Web I', c.id_carrera
FROM carreras c
WHERE c.nombre_carrera = 'Ingenieria de Sistemas'
  AND NOT EXISTS (SELECT 1 FROM materias WHERE nombre_materia = 'Tecnologia Web I');

-- Generar el hash en el servidor y reemplazar HASH_REAL antes de ejecutar:
-- php -r "echo password_hash('cambiar-esta-clave', PASSWORD_DEFAULT), PHP_EOL;"
-- INSERT INTO usuarios (id_rol, nombre, apellido, correo, usuario, contrasena_hash)
-- SELECT r.id_rol, 'Admin', 'Sistema', 'admin@tutoriasupds.local', 'admin', 'HASH_REAL'
-- FROM roles r
-- WHERE r.nombre_rol = 'administrador'
--   AND NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'admin');
