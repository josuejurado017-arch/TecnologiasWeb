-- Rediseno institucional de tutorias UPDS (identidad unificada).
-- Agrega Carnet de Identidad (CI) a nivel de persona (usuarios), compartido por
-- administrador, tutor y estudiante. NULL a nivel BD para no romper datos demo
-- existentes; el formulario lo exige en cuentas nuevas. UNIQUE (una persona = un CI).
-- El complemento/extension es opcional y se guarda dentro del mismo texto.
-- Ejecutar despues de 018_unique_carrera_materia.sql sobre testdb.

USE testdb;

ALTER TABLE usuarios
  ADD COLUMN carnet_identidad VARCHAR(20) NULL AFTER telefono,
  ADD UNIQUE KEY uq_usuario_ci (carnet_identidad);
