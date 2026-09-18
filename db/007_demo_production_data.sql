-- Datos demostrativos para pruebas funcionales y de reportes.
-- Ejecutar despues de 006_tutor_permissions.sql sobre testdb.
-- Es idempotente: identifica sus registros con usuarios demo y marcadores DEMO-.
-- Credenciales de todas las cuentas demo: Demo1234!

USE testdb;

SET NAMES utf8mb4;

INSERT INTO carreras (nombre_carrera)
SELECT d.nombre_carrera
FROM (
  SELECT 'Ingenieria de Sistemas - Demo' AS nombre_carrera
  UNION ALL SELECT 'Administracion de Empresas - Demo'
  UNION ALL SELECT 'Derecho - Demo'
  UNION ALL SELECT 'Contaduria Publica - Demo'
  UNION ALL SELECT 'Marketing Digital - Demo'
) AS d
WHERE NOT EXISTS (
  SELECT 1 FROM carreras existente WHERE existente.nombre_carrera = d.nombre_carrera
);

INSERT IGNORE INTO materias (nombre_materia, id_carrera)
SELECT d.nombre_materia, c.id_carrera
FROM (
  SELECT 'Algoritmos y Estructuras de Datos' AS nombre_materia, 'Ingenieria de Sistemas - Demo' AS carrera
  UNION ALL SELECT 'Ingenieria de Software', 'Ingenieria de Sistemas - Demo'
  UNION ALL SELECT 'Redes de Computadoras', 'Ingenieria de Sistemas - Demo'
  UNION ALL SELECT 'Seguridad Informatica', 'Ingenieria de Sistemas - Demo'
  UNION ALL SELECT 'Desarrollo Web Avanzado', 'Ingenieria de Sistemas - Demo'
  UNION ALL SELECT 'Contabilidad Financiera', 'Contaduria Publica - Demo'
  UNION ALL SELECT 'Gestion Empresarial', 'Administracion de Empresas - Demo'
  UNION ALL SELECT 'Marketing Digital', 'Marketing Digital - Demo'
  UNION ALL SELECT 'Derecho Constitucional', 'Derecho - Demo'
  UNION ALL SELECT 'Legislacion Laboral', 'Derecho - Demo'
  UNION ALL SELECT 'Matematicas Aplicadas', 'Ingenieria de Sistemas - Demo'
  UNION ALL SELECT 'Estadistica', 'Administracion de Empresas - Demo'
  UNION ALL SELECT 'Bases de Datos II', 'Ingenieria de Sistemas - Demo'
  UNION ALL SELECT 'Sistemas Operativos', 'Ingenieria de Sistemas - Demo'
  UNION ALL SELECT 'Arquitectura de Computadoras', 'Ingenieria de Sistemas - Demo'
) AS d
INNER JOIN carreras c ON c.nombre_carrera = d.carrera
WHERE NOT EXISTS (
  SELECT 1 FROM materias existente WHERE existente.nombre_materia = d.nombre_materia
);

INSERT IGNORE INTO usuarios
  (id_rol, nombre, apellido, correo, usuario, contrasena_hash, telefono, estado, fecha_registro)
VALUES
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'tutor'), 'Ana Lucia', 'Mendoza Rojas', 'ana.mendoza.demo@tutorias.local', 'tutor_demo_01', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70110001', 'activo', DATE_SUB(NOW(), INTERVAL 220 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'tutor'), 'Carlos Andres', 'Vargas Paredes', 'carlos.vargas.demo@tutorias.local', 'tutor_demo_02', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70110002', 'activo', DATE_SUB(NOW(), INTERVAL 205 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'tutor'), 'Daniela', 'Quispe Flores', 'daniela.quispe.demo@tutorias.local', 'tutor_demo_03', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70110003', 'activo', DATE_SUB(NOW(), INTERVAL 190 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'tutor'), 'Fernando', 'Salazar Lima', 'fernando.salazar.demo@tutorias.local', 'tutor_demo_04', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70110004', 'activo', DATE_SUB(NOW(), INTERVAL 175 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'tutor'), 'Gabriela', 'Torrez Molina', 'gabriela.torrez.demo@tutorias.local', 'tutor_demo_05', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70110005', 'activo', DATE_SUB(NOW(), INTERVAL 160 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'tutor'), 'Hugo', 'Rojas Camacho', 'hugo.rojas.demo@tutorias.local', 'tutor_demo_06', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70110006', 'activo', DATE_SUB(NOW(), INTERVAL 145 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'tutor'), 'Isabel', 'Pinto Arias', 'isabel.pinto.demo@tutorias.local', 'tutor_demo_07', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70110007', 'activo', DATE_SUB(NOW(), INTERVAL 130 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'tutor'), 'Jorge Luis', 'Mamani Choque', 'jorge.mamani.demo@tutorias.local', 'tutor_demo_08', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70110008', 'activo', DATE_SUB(NOW(), INTERVAL 115 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Mariana', 'Perez Soto', 'mariana.perez.demo@tutorias.local', 'student_demo_01', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220001', 'activo', DATE_SUB(NOW(), INTERVAL 110 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Luis Alberto', 'Gutierrez Rios', 'luis.gutierrez.demo@tutorias.local', 'student_demo_02', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220002', 'activo', DATE_SUB(NOW(), INTERVAL 105 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Sofia', 'Castro Nina', 'sofia.castro.demo@tutorias.local', 'student_demo_03', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220003', 'activo', DATE_SUB(NOW(), INTERVAL 100 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Mateo', 'Alarcon Silva', 'mateo.alarcon.demo@tutorias.local', 'student_demo_04', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220004', 'activo', DATE_SUB(NOW(), INTERVAL 95 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Valeria', 'Romero Paz', 'valeria.romero.demo@tutorias.local', 'student_demo_05', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220005', 'activo', DATE_SUB(NOW(), INTERVAL 90 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Diego', 'Mamani Vargas', 'diego.mamani.demo@tutorias.local', 'student_demo_06', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220006', 'activo', DATE_SUB(NOW(), INTERVAL 85 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Camila', 'Fernandez Leon', 'camila.fernandez.demo@tutorias.local', 'student_demo_07', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220007', 'activo', DATE_SUB(NOW(), INTERVAL 80 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Rodrigo', 'Vega Cardenas', 'rodrigo.vega.demo@tutorias.local', 'student_demo_08', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220008', 'activo', DATE_SUB(NOW(), INTERVAL 75 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Natalia', 'Huanca Flores', 'natalia.huanca.demo@tutorias.local', 'student_demo_09', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220009', 'activo', DATE_SUB(NOW(), INTERVAL 70 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Pablo', 'Mendoza Arias', 'pablo.mendoza.demo@tutorias.local', 'student_demo_10', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220010', 'activo', DATE_SUB(NOW(), INTERVAL 65 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Andrea', 'Caceres Lima', 'andrea.caceres.demo@tutorias.local', 'student_demo_11', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220011', 'activo', DATE_SUB(NOW(), INTERVAL 60 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Gabriel', 'Sanchez Ortiz', 'gabriel.sanchez.demo@tutorias.local', 'student_demo_12', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220012', 'activo', DATE_SUB(NOW(), INTERVAL 55 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Lucia', 'Paredes Salinas', 'lucia.paredes.demo@tutorias.local', 'student_demo_13', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220013', 'activo', DATE_SUB(NOW(), INTERVAL 50 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Esteban', 'Choque Condori', 'esteban.choque.demo@tutorias.local', 'student_demo_14', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220014', 'activo', DATE_SUB(NOW(), INTERVAL 45 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Paola', 'Villarroel Ruiz', 'paola.villarroel.demo@tutorias.local', 'student_demo_15', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220015', 'activo', DATE_SUB(NOW(), INTERVAL 40 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Kevin', 'Torrez Salazar', 'kevin.torrez.demo@tutorias.local', 'student_demo_16', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220016', 'activo', DATE_SUB(NOW(), INTERVAL 35 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Karen', 'Rojas Molina', 'karen.rojas.demo@tutorias.local', 'student_demo_17', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220017', 'activo', DATE_SUB(NOW(), INTERVAL 30 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Oscar', 'Ledezma Quispe', 'oscar.ledezma.demo@tutorias.local', 'student_demo_18', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220018', 'activo', DATE_SUB(NOW(), INTERVAL 25 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Rebeca', 'Montano Poma', 'rebeca.montano.demo@tutorias.local', 'student_demo_19', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220019', 'activo', DATE_SUB(NOW(), INTERVAL 20 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Martin', 'Aguirre Soto', 'martin.aguirre.demo@tutorias.local', 'student_demo_20', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220020', 'activo', DATE_SUB(NOW(), INTERVAL 18 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Julia', 'Arce Romero', 'julia.arce.demo@tutorias.local', 'student_demo_21', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220021', 'activo', DATE_SUB(NOW(), INTERVAL 16 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Nicolas', 'Vargas Nina', 'nicolas.vargas.demo@tutorias.local', 'student_demo_22', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220022', 'activo', DATE_SUB(NOW(), INTERVAL 14 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Rocio', 'Luna Condori', 'rocio.luna.demo@tutorias.local', 'student_demo_23', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220023', 'activo', DATE_SUB(NOW(), INTERVAL 12 DAY)),
  ((SELECT id_rol FROM roles WHERE nombre_rol = 'estudiante'), 'Samuel', 'Flores Rios', 'samuel.flores.demo@tutorias.local', 'student_demo_24', '$2y$10$Z.N0edIseLdILMA5/8zKi.eqbcRq38gKItUPe1h945lbjYiyjKNI2', '70220024', 'activo', DATE_SUB(NOW(), INTERVAL 10 DAY));

INSERT IGNORE INTO tutores (id_usuario, especialidad, biografia)
SELECT u.id_usuario, d.especialidad, d.biografia
FROM (
  SELECT 'tutor_demo_01' AS usuario, 'Algoritmos y bases de datos' AS especialidad, 'Tutor de apoyo en logica, programacion y modelado de datos.' AS biografia
  UNION ALL SELECT 'tutor_demo_02', 'Ingenieria de software y desarrollo web', 'Acompanamiento en proyectos web, metodologias agiles y pruebas.'
  UNION ALL SELECT 'tutor_demo_03', 'Redes y sistemas operativos', 'Experiencia en redes, arquitectura de computadores y servidores.'
  UNION ALL SELECT 'tutor_demo_04', 'Seguridad informatica', 'Apoyo en fundamentos de ciberseguridad y administracion de redes.'
  UNION ALL SELECT 'tutor_demo_05', 'Contabilidad y estadistica', 'Tutorias practicas de contabilidad financiera y analisis numerico.'
  UNION ALL SELECT 'tutor_demo_06', 'Gestion empresarial y marketing', 'Acompanamiento en gestion, emprendimiento y marketing digital.'
  UNION ALL SELECT 'tutor_demo_07', 'Derecho y legislacion', 'Apoyo academico en derecho constitucional y legislacion laboral.'
  UNION ALL SELECT 'tutor_demo_08', 'Programacion y sistemas', 'Tutor de fundamentos de programacion y sistemas operativos.'
) AS d
INNER JOIN usuarios u ON u.usuario = d.usuario;

INSERT IGNORE INTO estudiantes (id_usuario, id_carrera, semestre, registro_universitario)
SELECT u.id_usuario, c.id_carrera, d.semestre, d.registro_universitario
FROM (
  SELECT 'student_demo_01' AS usuario, 'Ingenieria de Sistemas - Demo' AS carrera, 2 AS semestre, 'DEMO-EST-NEW-001' AS registro_universitario
  UNION ALL SELECT 'student_demo_02', 'Ingenieria de Sistemas - Demo', 3, 'DEMO-EST-002'
  UNION ALL SELECT 'student_demo_03', 'Ingenieria de Sistemas - Demo', 4, 'DEMO-EST-003'
  UNION ALL SELECT 'student_demo_04', 'Ingenieria de Sistemas - Demo', 5, 'DEMO-EST-004'
  UNION ALL SELECT 'student_demo_05', 'Ingenieria de Sistemas - Demo', 6, 'DEMO-EST-005'
  UNION ALL SELECT 'student_demo_06', 'Ingenieria de Sistemas - Demo', 7, 'DEMO-EST-006'
  UNION ALL SELECT 'student_demo_07', 'Ingenieria de Sistemas - Demo', 8, 'DEMO-EST-007'
  UNION ALL SELECT 'student_demo_08', 'Administracion de Empresas - Demo', 2, 'DEMO-EST-008'
  UNION ALL SELECT 'student_demo_09', 'Administracion de Empresas - Demo', 3, 'DEMO-EST-009'
  UNION ALL SELECT 'student_demo_10', 'Administracion de Empresas - Demo', 4, 'DEMO-EST-010'
  UNION ALL SELECT 'student_demo_11', 'Administracion de Empresas - Demo', 5, 'DEMO-EST-011'
  UNION ALL SELECT 'student_demo_12', 'Administracion de Empresas - Demo', 6, 'DEMO-EST-012'
  UNION ALL SELECT 'student_demo_13', 'Derecho - Demo', 2, 'DEMO-EST-013'
  UNION ALL SELECT 'student_demo_14', 'Derecho - Demo', 4, 'DEMO-EST-014'
  UNION ALL SELECT 'student_demo_15', 'Derecho - Demo', 6, 'DEMO-EST-015'
  UNION ALL SELECT 'student_demo_16', 'Derecho - Demo', 8, 'DEMO-EST-016'
  UNION ALL SELECT 'student_demo_17', 'Contaduria Publica - Demo', 2, 'DEMO-EST-017'
  UNION ALL SELECT 'student_demo_18', 'Contaduria Publica - Demo', 4, 'DEMO-EST-018'
  UNION ALL SELECT 'student_demo_19', 'Contaduria Publica - Demo', 6, 'DEMO-EST-019'
  UNION ALL SELECT 'student_demo_20', 'Contaduria Publica - Demo', 8, 'DEMO-EST-020'
  UNION ALL SELECT 'student_demo_21', 'Marketing Digital - Demo', 2, 'DEMO-EST-021'
  UNION ALL SELECT 'student_demo_22', 'Marketing Digital - Demo', 4, 'DEMO-EST-022'
  UNION ALL SELECT 'student_demo_23', 'Marketing Digital - Demo', 6, 'DEMO-EST-023'
  UNION ALL SELECT 'student_demo_24', 'Marketing Digital - Demo', 8, 'DEMO-EST-024'
) AS d
INNER JOIN usuarios u ON u.usuario = d.usuario
INNER JOIN carreras c ON c.nombre_carrera = d.carrera;

INSERT IGNORE INTO tutor_materia (id_tutor, id_materia)
SELECT t.id_tutor, m.id_materia
FROM (
  SELECT 'tutor_demo_01' AS usuario, 'Algoritmos y Estructuras de Datos' AS materia
  UNION ALL SELECT 'tutor_demo_01', 'Bases de Datos II'
  UNION ALL SELECT 'tutor_demo_01', 'Matematicas Aplicadas'
  UNION ALL SELECT 'tutor_demo_02', 'Ingenieria de Software'
  UNION ALL SELECT 'tutor_demo_02', 'Desarrollo Web Avanzado'
  UNION ALL SELECT 'tutor_demo_02', 'Programacion I'
  UNION ALL SELECT 'tutor_demo_03', 'Redes de Computadoras'
  UNION ALL SELECT 'tutor_demo_03', 'Sistemas Operativos'
  UNION ALL SELECT 'tutor_demo_03', 'Arquitectura de Computadoras'
  UNION ALL SELECT 'tutor_demo_04', 'Seguridad Informatica'
  UNION ALL SELECT 'tutor_demo_04', 'Redes de Computadoras'
  UNION ALL SELECT 'tutor_demo_04', 'Desarrollo Web Avanzado'
  UNION ALL SELECT 'tutor_demo_05', 'Contabilidad Financiera'
  UNION ALL SELECT 'tutor_demo_05', 'Estadistica'
  UNION ALL SELECT 'tutor_demo_05', 'Matematicas Aplicadas'
  UNION ALL SELECT 'tutor_demo_06', 'Gestion Empresarial'
  UNION ALL SELECT 'tutor_demo_06', 'Marketing Digital'
  UNION ALL SELECT 'tutor_demo_06', 'Estadistica'
  UNION ALL SELECT 'tutor_demo_07', 'Derecho Constitucional'
  UNION ALL SELECT 'tutor_demo_07', 'Legislacion Laboral'
  UNION ALL SELECT 'tutor_demo_07', 'Gestion Empresarial'
  UNION ALL SELECT 'tutor_demo_08', 'Programacion I'
  UNION ALL SELECT 'tutor_demo_08', 'Algoritmos y Estructuras de Datos'
  UNION ALL SELECT 'tutor_demo_08', 'Sistemas Operativos'
) AS d
INNER JOIN usuarios u ON u.usuario = d.usuario
INNER JOIN tutores t ON t.id_usuario = u.id_usuario
INNER JOIN materias m ON m.nombre_materia = d.materia;

INSERT INTO disponibilidad_tutor (id_tutor, dia_semana, hora_inicio, hora_fin)
SELECT t.id_tutor, dias.dia_semana, bloques.hora_inicio, bloques.hora_fin
FROM tutores t
INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
CROSS JOIN (
  SELECT 'Lunes' AS dia_semana UNION ALL SELECT 'Martes' UNION ALL SELECT 'Miercoles'
  UNION ALL SELECT 'Jueves' UNION ALL SELECT 'Viernes'
) AS dias
CROSS JOIN (
  SELECT '08:00:00' AS hora_inicio, '10:00:00' AS hora_fin
  UNION ALL SELECT '14:00:00', '16:00:00'
) AS bloques
WHERE u.usuario LIKE 'tutor_demo_%'
  AND NOT EXISTS (
    SELECT 1 FROM disponibilidad_tutor existente
    WHERE existente.id_tutor = t.id_tutor
      AND existente.dia_semana = dias.dia_semana
      AND existente.hora_inicio = bloques.hora_inicio
      AND existente.hora_fin = bloques.hora_fin
  );

INSERT INTO tutorias
  (id_estudiante, id_tutor, id_materia, fecha, hora_inicio, hora_fin, modalidad, lugar_o_enlace, estado, observaciones, fecha_solicitud)
SELECT e.id_estudiante, t.id_tutor, m.id_materia,
       DATE_ADD(CURRENT_DATE, INTERVAL demo.dias DAY), demo.hora_inicio, demo.hora_fin,
       demo.modalidad, demo.lugar_o_enlace, demo.estado, CONCAT('[', demo.marcador, '] ', demo.observaciones),
       DATE_ADD(NOW(), INTERVAL demo.solicitud_dias DAY)
FROM (
  SELECT 'DEMO-TUT-001' AS marcador, 'student_demo_01' AS estudiante, 'tutor_demo_01' AS tutor, 'Algoritmos y Estructuras de Datos' AS materia, -180 AS dias, '08:00:00' AS hora_inicio, '10:00:00' AS hora_fin, 'presencial' AS modalidad, 'Aula 204' AS lugar_o_enlace, 'realizada' AS estado, 'Repaso de estructuras lineales y complejidad.' AS observaciones, -190 AS solicitud_dias
  UNION ALL SELECT 'DEMO-TUT-002', 'student_demo_02', 'tutor_demo_01', 'Bases de Datos II', -165, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-002', 'realizada', 'Ejercicios de normalizacion.', -175
  UNION ALL SELECT 'DEMO-TUT-003', 'student_demo_03', 'tutor_demo_02', 'Ingenieria de Software', -150, '08:00:00', '10:00:00', 'presencial', 'Laboratorio 3', 'realizada', 'Revision de historias de usuario.', -160
  UNION ALL SELECT 'DEMO-TUT-004', 'student_demo_04', 'tutor_demo_02', 'Desarrollo Web Avanzado', -135, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-004', 'realizada', 'Practica de rutas y sesiones.', -145
  UNION ALL SELECT 'DEMO-TUT-005', 'student_demo_05', 'tutor_demo_03', 'Redes de Computadoras', -120, '08:00:00', '10:00:00', 'presencial', 'Aula 101', 'realizada', 'Direccionamiento IPv4 y subredes.', -130
  UNION ALL SELECT 'DEMO-TUT-006', 'student_demo_06', 'tutor_demo_03', 'Sistemas Operativos', -105, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-006', 'realizada', 'Procesos e hilos.', -115
  UNION ALL SELECT 'DEMO-TUT-007', 'student_demo_07', 'tutor_demo_04', 'Seguridad Informatica', -90, '08:00:00', '10:00:00', 'presencial', 'Aula 305', 'realizada', 'Buenas practicas de autenticacion.', -100
  UNION ALL SELECT 'DEMO-TUT-008', 'student_demo_08', 'tutor_demo_05', 'Contabilidad Financiera', -75, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-008', 'realizada', 'Registro de operaciones contables.', -85
  UNION ALL SELECT 'DEMO-TUT-009', 'student_demo_09', 'tutor_demo_06', 'Gestion Empresarial', -60, '08:00:00', '10:00:00', 'presencial', 'Aula 110', 'realizada', 'Analisis de procesos internos.', -70
  UNION ALL SELECT 'DEMO-TUT-010', 'student_demo_10', 'tutor_demo_07', 'Derecho Constitucional', -45, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-010', 'realizada', 'Revision de derechos fundamentales.', -55
  UNION ALL SELECT 'DEMO-TUT-011', 'student_demo_11', 'tutor_demo_08', 'Programacion I', -30, '08:00:00', '10:00:00', 'presencial', 'Laboratorio 1', 'realizada', 'Practica de funciones y arreglos.', -40
  UNION ALL SELECT 'DEMO-TUT-012', 'student_demo_12', 'tutor_demo_01', 'Matematicas Aplicadas', -15, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-012', 'realizada', 'Resolucion de problemas aplicados.', -25
  UNION ALL SELECT 'DEMO-TUT-013', 'student_demo_13', 'tutor_demo_02', 'Programacion I', -12, '08:00:00', '10:00:00', 'presencial', 'Aula 202', 'cancelada', 'El estudiante solicito reprogramacion.', -20
  UNION ALL SELECT 'DEMO-TUT-014', 'student_demo_14', 'tutor_demo_03', 'Arquitectura de Computadoras', -8, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-014', 'cancelada', 'Cancelacion por actividad institucional.', -18
  UNION ALL SELECT 'DEMO-TUT-015', 'student_demo_15', 'tutor_demo_04', 'Redes de Computadoras', -5, '08:00:00', '10:00:00', 'presencial', 'Aula 101', 'cancelada', 'Horario no compatible.', -15
  UNION ALL SELECT 'DEMO-TUT-016', 'student_demo_16', 'tutor_demo_05', 'Estadistica', -3, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-016', 'cancelada', 'Solicitud cancelada por el tutor.', -10
  UNION ALL SELECT 'DEMO-TUT-017', 'student_demo_17', 'tutor_demo_06', 'Marketing Digital', 4, '08:00:00', '10:00:00', 'virtual', 'https://meet.google.com/demo-017', 'confirmada', 'Revision de estrategia de contenidos.', -8
  UNION ALL SELECT 'DEMO-TUT-018', 'student_demo_18', 'tutor_demo_07', 'Legislacion Laboral', 6, '14:00:00', '16:00:00', 'presencial', 'Aula 115', 'confirmada', 'Consulta sobre contratos de trabajo.', -6
  UNION ALL SELECT 'DEMO-TUT-019', 'student_demo_19', 'tutor_demo_08', 'Algoritmos y Estructuras de Datos', 8, '08:00:00', '10:00:00', 'presencial', 'Laboratorio 2', 'confirmada', 'Practica de arboles y grafos.', -4
  UNION ALL SELECT 'DEMO-TUT-020', 'student_demo_20', 'tutor_demo_01', 'Bases de Datos II', 10, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-020', 'confirmada', 'Consultas y optimizacion.', -3
  UNION ALL SELECT 'DEMO-TUT-021', 'student_demo_21', 'tutor_demo_02', 'Ingenieria de Software', 12, '08:00:00', '10:00:00', 'presencial', 'Aula 203', 'confirmada', 'Planificacion de sprint.', -2
  UNION ALL SELECT 'DEMO-TUT-022', 'student_demo_22', 'tutor_demo_03', 'Sistemas Operativos', 14, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-022', 'confirmada', 'Administracion de servicios.', -2
  UNION ALL SELECT 'DEMO-TUT-023', 'student_demo_23', 'tutor_demo_04', 'Seguridad Informatica', 16, '08:00:00', '10:00:00', 'presencial', 'Aula 305', 'confirmada', 'Revision de controles de acceso.', -1
  UNION ALL SELECT 'DEMO-TUT-024', 'student_demo_24', 'tutor_demo_05', 'Contabilidad Financiera', 18, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-024', 'confirmada', 'Preparacion de cierre mensual.', -1
  UNION ALL SELECT 'DEMO-TUT-025', 'student_demo_01', 'tutor_demo_06', 'Gestion Empresarial', 20, '08:00:00', '10:00:00', 'presencial', 'Aula 110', 'pendiente', 'Analisis FODA.', -1
  UNION ALL SELECT 'DEMO-TUT-026', 'student_demo_02', 'tutor_demo_07', 'Derecho Constitucional', 22, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-026', 'pendiente', 'Preparacion de examen parcial.', -1
  UNION ALL SELECT 'DEMO-TUT-027', 'student_demo_03', 'tutor_demo_08', 'Programacion I', 24, '08:00:00', '10:00:00', 'presencial', 'Laboratorio 1', 'pendiente', 'Ejercicios de ciclos y funciones.', -1
  UNION ALL SELECT 'DEMO-TUT-028', 'student_demo_04', 'tutor_demo_01', 'Matematicas Aplicadas', 26, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-028', 'pendiente', 'Preparacion de evaluacion.', -1
  UNION ALL SELECT 'DEMO-TUT-029', 'student_demo_05', 'tutor_demo_02', 'Desarrollo Web Avanzado', 28, '08:00:00', '10:00:00', 'presencial', 'Laboratorio 3', 'pendiente', 'Revision de formularios.', -1
  UNION ALL SELECT 'DEMO-TUT-030', 'student_demo_06', 'tutor_demo_03', 'Redes de Computadoras', 30, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-030', 'pendiente', 'Practica de configuracion.', -1
  UNION ALL SELECT 'DEMO-TUT-031', 'student_demo_07', 'tutor_demo_04', 'Seguridad Informatica', 32, '08:00:00', '10:00:00', 'presencial', 'Aula 305', 'pendiente', 'Revision de amenazas comunes.', -1
  UNION ALL SELECT 'DEMO-TUT-032', 'student_demo_08', 'tutor_demo_05', 'Estadistica', 34, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-032', 'pendiente', 'Interpretacion de graficos.', -1
  UNION ALL SELECT 'DEMO-TUT-033', 'student_demo_09', 'tutor_demo_06', 'Marketing Digital', 36, '08:00:00', '10:00:00', 'presencial', 'Aula 110', 'pendiente', 'Plan de campana digital.', -1
  UNION ALL SELECT 'DEMO-TUT-034', 'student_demo_10', 'tutor_demo_07', 'Legislacion Laboral', 38, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-034', 'pendiente', 'Consulta de normativa.', -1
  UNION ALL SELECT 'DEMO-TUT-035', 'student_demo_11', 'tutor_demo_08', 'Algoritmos y Estructuras de Datos', 40, '08:00:00', '10:00:00', 'presencial', 'Laboratorio 2', 'pendiente', 'Practica de busqueda y ordenamiento.', -1
  UNION ALL SELECT 'DEMO-TUT-036', 'student_demo_12', 'tutor_demo_01', 'Bases de Datos II', 42, '14:00:00', '16:00:00', 'virtual', 'https://meet.google.com/demo-036', 'pendiente', 'Diseno de indices.', -1
) AS demo
INNER JOIN estudiantes e ON e.id_usuario = (SELECT id_usuario FROM usuarios WHERE usuario = demo.estudiante LIMIT 1)
INNER JOIN tutores t ON t.id_usuario = (SELECT id_usuario FROM usuarios WHERE usuario = demo.tutor LIMIT 1)
INNER JOIN materias m ON m.nombre_materia = demo.materia
WHERE NOT EXISTS (
  SELECT 1 FROM tutorias existente
  WHERE existente.observaciones LIKE CONCAT('%[', demo.marcador, ']%')
);

INSERT INTO evaluaciones_tutoria (id_tutoria, calificacion, comentario, fecha_evaluacion)
SELECT t.id_tutoria, demo.calificacion, demo.comentario,
       DATE_ADD(NOW(), INTERVAL demo.dias_evaluacion DAY)
FROM (
  SELECT 'DEMO-TUT-001' AS marcador, 5 AS calificacion, 'Excelente explicacion y ejercicios claros.' AS comentario, -178 AS dias_evaluacion
  UNION ALL SELECT 'DEMO-TUT-002', 4, 'La sesion resolvio mis dudas principales.', -163
  UNION ALL SELECT 'DEMO-TUT-003', 5, 'Muy buen acompanamiento durante el proyecto.', -148
  UNION ALL SELECT 'DEMO-TUT-004', 4, 'Material practico y buena organizacion.', -133
  UNION ALL SELECT 'DEMO-TUT-005', 5, 'Explico las subredes paso a paso.', -118
  UNION ALL SELECT 'DEMO-TUT-006', 3, 'La sesion fue util, aunque falto mas tiempo.', -103
  UNION ALL SELECT 'DEMO-TUT-007', 5, 'Recomendaciones concretas para mejorar la seguridad.', -88
  UNION ALL SELECT 'DEMO-TUT-008', 4, 'Ejemplos faciles de seguir.', -73
  UNION ALL SELECT 'DEMO-TUT-009', 5, 'Ayudo a ordenar el trabajo del equipo.', -58
  UNION ALL SELECT 'DEMO-TUT-010', 4, 'Buena revision de los conceptos.', -43
) AS demo
INNER JOIN tutorias t ON t.observaciones LIKE CONCAT('%[', demo.marcador, ']%') AND t.estado = 'realizada'
WHERE NOT EXISTS (SELECT 1 FROM evaluaciones_tutoria e WHERE e.id_tutoria = t.id_tutoria);

INSERT INTO registro_accesos (id_usuario, fecha_hora, ip_origen, resultado)
SELECT u.id_usuario,
       TIMESTAMP(DATE_SUB(CURRENT_DATE, INTERVAL sec.dia DAY), MAKETIME(8 + MOD(sec.dia, 8), MOD(sec.dia, 4) * 15, 0)),
       CONCAT('10.44.', 10 + MOD(sec.dia, 20), '.', 20 + MOD(sec.dia, 200)),
       CASE WHEN MOD(sec.dia, 7) = 0 THEN 'fallido' ELSE 'exitoso' END
FROM (
  SELECT 0 AS dia UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
  UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19
  UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29
  UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34 UNION ALL SELECT 35 UNION ALL SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39
  UNION ALL SELECT 40 UNION ALL SELECT 41 UNION ALL SELECT 42 UNION ALL SELECT 43 UNION ALL SELECT 44 UNION ALL SELECT 45 UNION ALL SELECT 46 UNION ALL SELECT 47 UNION ALL SELECT 48 UNION ALL SELECT 49
  UNION ALL SELECT 50 UNION ALL SELECT 51 UNION ALL SELECT 52 UNION ALL SELECT 53 UNION ALL SELECT 54 UNION ALL SELECT 55 UNION ALL SELECT 56 UNION ALL SELECT 57 UNION ALL SELECT 58 UNION ALL SELECT 59
) AS sec
INNER JOIN usuarios u ON u.usuario = CONCAT('student_demo_', LPAD(MOD(sec.dia, 24) + 1, 2, '0'))
WHERE NOT EXISTS (
  SELECT 1 FROM registro_accesos ra
  WHERE ra.id_usuario = u.id_usuario
    AND ra.fecha_hora = TIMESTAMP(DATE_SUB(CURRENT_DATE, INTERVAL sec.dia DAY), MAKETIME(8 + MOD(sec.dia, 8), MOD(sec.dia, 4) * 15, 0))
    AND ra.ip_origen = CONCAT('10.44.', 10 + MOD(sec.dia, 20), '.', 20 + MOD(sec.dia, 200))
);
