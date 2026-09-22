-- Rediseno institucional de tutorias UPDS (Fase 2: patrones semanales por grupo).
-- Un grupo hoy solo puede reunirse un dia a la semana (grupos_tutoria.dia_semana,
-- columna unica). Se agrega grupo_dias para soportar patrones de varios dias
-- (ej. Lunes+Miercoles+Viernes) para el MISMO grupo, mismo horario uniforme
-- (hora_inicio/hora_fin se quedan en grupos_tutoria, sin cambio).
--
-- Migracion en dos fases (mismo patron que 022_tutor_materia_turno_dias):
-- Fase A (este archivo): agrega la tabla y la puebla con el dia actual de cada
-- grupo existente. grupos_tutoria.dia_semana NO se elimina todavia: se mantiene
-- como "primer dia del patron" para no romper vistas/consultas que aun no fueron
-- migradas a leer grupo_dias. Una migracion futura (Fase B) la eliminara una vez
-- verificado que ningun codigo la sigue leyendo directamente.
-- Ejecutar despues de 023_demanda_motivo.sql sobre testdb.

USE testdb;

CREATE TABLE IF NOT EXISTS grupo_dias (
  id_grupo   INT NOT NULL,
  dia_semana ENUM('Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') NOT NULL,
  PRIMARY KEY (id_grupo, dia_semana),
  CONSTRAINT fk_grupodias_grupo FOREIGN KEY (id_grupo) REFERENCES grupos_tutoria (id_grupo) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO grupo_dias (id_grupo, dia_semana)
SELECT id_grupo, dia_semana FROM grupos_tutoria;
