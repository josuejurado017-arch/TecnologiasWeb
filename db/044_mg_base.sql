-- Modalidades de Grado (MG), base: roles, parametros, modalidades, cohortes,
-- calendario de hitos y bitacora. Ver docs/analisis/plan-mg-ajustado.md.
--
-- MG es un modulo aparte del de tutorias: expedientes individuales largos
-- (MG1 ~2 meses, MG2 ~4 meses) agrupados por cohorte de inicio.
--
--   roles: coordinador_mg (responsable del area) y auxiliar_mg (apoyo, permisos
--     reducidos). Los permisos por accion estan en includes/Auth.php.
--   parametros_mg: cifras dudosas de ENT-02/ENT-03 como valores configurables,
--     con fuente y estado de evidencia. Solo producen advertencias.
--   calendario_mg: hitos por cohorte. La cantidad de informes de MG2 (C-02: 3 o 4)
--     es la cantidad de hitos tipo 'informe' de la cohorte.
--   bitacora_mg: auditoria (antes/despues) de los cambios sensibles.
--
-- Compatible con MySQL 8.4 y MariaDB 10.4. Idempotente.
-- Ejecutar despues de 043_tipos_tutoria.sql sobre testdb.

USE testdb;

INSERT INTO roles (nombre_rol)
SELECT 'coordinador_mg' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'coordinador_mg');
INSERT INTO roles (nombre_rol)
SELECT 'auxiliar_mg' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre_rol = 'auxiliar_mg');

CREATE TABLE IF NOT EXISTS parametros_mg (
  clave VARCHAR(60) NOT NULL,
  valor VARCHAR(100) NULL,
  tipo ENUM('entero','texto') NOT NULL DEFAULT 'entero',
  descripcion VARCHAR(255) NOT NULL,
  fuente VARCHAR(60) NOT NULL,
  estado_evidencia ENUM('confirmado','pendiente','propuesta') NOT NULL,
  actualizado_por INT NULL,
  fecha_actualizacion DATETIME NULL,
  PRIMARY KEY (clave),
  CONSTRAINT fk_parametro_mg_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO parametros_mg (clave, valor, tipo, descripcion, fuente, estado_evidencia) VALUES
  ('reuniones_min_semana_perfil', '2', 'entero', 'Reuniones mínimas por semana durante la elaboración del perfil (MG1).', 'ENT-03', 'confirmado'),
  ('dias_alerta_sin_reunion', '10', 'entero', 'Días sin reuniones registradas para alertar.', 'Equipo', 'propuesta'),
  ('tutor_carga_recomendada', '3', 'entero', 'Estudiantes vigentes por tutor a partir de los cuales se advierte (C-01). Nunca bloquea.', 'ENT-03 (2-3 deseable)', 'confirmado'),
  ('tutor_max_estudiantes', NULL, 'entero', 'Máximo formal de estudiantes por tutor (C-01: ENT-02 dice 5, ENT-03 sin máximo). Vacío = sin máximo.', 'ENT-02 / ENT-03', 'pendiente'),
  ('dias_anticipacion_tribunal', '14', 'entero', 'Días de anticipación con que se asignan los tribunales antes de la defensa.', 'ENT-03 (aprox.)', 'confirmado'),
  ('tribunales_por_defensa_mg1', '2', 'entero', 'Tribunales por defensa de MG1.', 'ENT-03', 'confirmado'),
  ('tribunales_por_defensa_mg2', '2', 'entero', 'Tribunales por defensa de MG2.', 'ENT-03', 'pendiente'),
  ('min_interesados_examen', '12', 'entero', 'Interesados mínimos para abrir Examen de Grado.', 'ENT-03', 'pendiente'),
  ('promedio_excelencia', '90', 'entero', 'Promedio mínimo para Graduación por Excelencia.', 'ENT-03', 'pendiente'),
  ('duracion_mg1_meses', '2', 'entero', 'Duración aproximada de MG1 (meses).', 'ENT-03', 'confirmado'),
  ('duracion_mg2_meses', '4', 'entero', 'Duración aproximada de MG2 (meses).', 'ENT-03', 'confirmado'),
  ('plazo_registro_reunion_dias', '7', 'entero', 'Días hacia atrás en que se puede registrar una reunión.', 'Equipo', 'propuesta'),
  ('nota_minima', '0', 'entero', 'Nota mínima de la escala.', 'Equipo', 'pendiente'),
  ('nota_maxima', '100', 'entero', 'Nota máxima de la escala (se mencionó promedio > 90).', 'ENT-03', 'pendiente'),
  ('nota_aprobacion', '51', 'entero', 'Nota con la que se sugiere aprobar la defensa. Solo sugiere: el estado lo decide la Coordinación.', 'Equipo', 'pendiente'),
  ('institucion_ciudad', 'Tarija', 'texto', 'Ciudad que encabeza cartas y citaciones.', 'Equipo', 'propuesta'),
  ('firma_coordinacion', 'Coordinación de Modalidades de Grado', 'texto', 'Firma de cartas y citaciones.', 'Equipo', 'propuesta');

CREATE TABLE IF NOT EXISTS modalidades_grado (
  id_modalidad INT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  requiere_tutor TINYINT(1) NOT NULL DEFAULT 0,
  flujo ENUM('perfil_mg','examen_areas','excelencia') NOT NULL,
  regla_por_validar TINYINT(1) NOT NULL DEFAULT 0,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_modalidad),
  UNIQUE KEY uq_modalidad_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RN-MG-01: 5 modalidades; solo Proyecto, Tesis y Trabajo Dirigido usan tutor.
-- RN-MG-20: Trabajo Dirigido marcado "regla por validar".
INSERT IGNORE INTO modalidades_grado (codigo, nombre, requiere_tutor, flujo, regla_por_validar) VALUES
  ('PROYECTO', 'Proyecto de Grado', 1, 'perfil_mg', 0),
  ('TESIS', 'Tesis', 1, 'perfil_mg', 0),
  ('TRABAJO_DIRIGIDO', 'Trabajo Dirigido', 1, 'perfil_mg', 1),
  ('EXAMEN', 'Examen de Grado', 0, 'examen_areas', 1),
  ('EXCELENCIA', 'Graduación por Excelencia', 0, 'excelencia', 1);

CREATE TABLE IF NOT EXISTS cohortes_mg (
  id_cohorte INT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(30) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_cohorte),
  UNIQUE KEY uq_cohorte_codigo (codigo),
  UNIQUE KEY uq_cohorte_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendario_mg (
  id_hito INT NOT NULL AUTO_INCREMENT,
  id_cohorte INT NOT NULL,
  etapa ENUM('previa','mg1','mg2') NOT NULL,
  tipo ENUM('taller','asignacion_tutor','asignacion_tribunal','informe','defensa','ingreso_mg2','otro') NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_limite DATE NOT NULL,
  avance_esperado_pct TINYINT UNSIGNED NULL,
  PRIMARY KEY (id_hito),
  KEY idx_hito_cohorte (id_cohorte, fecha_limite),
  CONSTRAINT fk_hito_cohorte FOREIGN KEY (id_cohorte) REFERENCES cohortes_mg (id_cohorte)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bitacora_mg (
  id_bitacora BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario INT NULL,
  accion VARCHAR(60) NOT NULL,
  tabla VARCHAR(60) NOT NULL,
  id_registro VARCHAR(60) NOT NULL,
  datos_antes LONGTEXT NULL,
  datos_despues LONGTEXT NULL,
  ip VARCHAR(45) NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_bitacora),
  KEY idx_bitacora_registro (tabla, id_registro),
  KEY idx_bitacora_fecha (fecha),
  CONSTRAINT fk_bitacora_mg_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
