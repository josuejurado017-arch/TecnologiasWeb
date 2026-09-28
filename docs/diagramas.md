# Diagramas

Diagramas del Sistema web de apoyo académico para tutorías (incluye el módulo
Modalidades de Grado). Se muestran en formato [Mermaid](https://mermaid.js.org/),
que GitHub renderiza automáticamente al ver este archivo en el repositorio.

## 1. Casos de uso

Actores: **Administrador** (gestión general y catálogos), **Tutor** (oferta,
grupos y sesiones), **Estudiante** (solicitud y seguimiento de su tutoría),
**Coordinación MG** (`coordinador_mg`, dueña del módulo de Modalidades de
Grado) y **Auxiliar MG** (`auxiliar_mg`, apoyo con permisos reducidos).

```mermaid
flowchart LR
    Admin(["Administrador"])
    Tutor(["Tutor"])
    Estudiante(["Estudiante"])
    CoordMG(["Coordinación MG"])
    AuxMG(["Auxiliar MG"])

    subgraph Cuentas y catálogos
        UC1[Gestionar usuarios, carreras y materias]
        UC2[Definir períodos y tipos de tutoría]
        UC3[Aprobar o rechazar postulación de tutor]
    end

    subgraph Tutorías
        UC4[Ofertar materias, turnos y modalidad]
        UC5[Revisar y aprobar grupo]
        UC6[Registrar asistencia por sesión]
        UC7[Solicitar tutoría / demanda]
        UC8[Inscribirse o ver su grupo]
        UC9[Evaluar la tutoría recibida]
        UC10[Dividir un grupo lleno]
        UC11[Inscribir o retirar estudiante manualmente]
    end

    subgraph Modalidades de Grado
        UC12[Registrar expediente y asignar tutor]
        UC13[Registrar reunión de seguimiento]
        UC14[Validar reunión / informe de avance]
        UC15[Programar defensa y tribunal]
        UC16[Calificar defensa]
        UC17[Ver panel de alertas y línea de tiempo]
        UC18[Importar padrón de estudiantes CSV]
    end

    Admin --> UC1
    Admin --> UC2
    Admin --> UC3
    Admin --> UC5
    Admin --> UC10
    Admin --> UC11

    Tutor --> UC4
    Tutor --> UC6
    Tutor --> UC13

    Estudiante --> UC7
    Estudiante --> UC8
    Estudiante --> UC9

    CoordMG --> UC12
    CoordMG --> UC15
    CoordMG --> UC16
    CoordMG --> UC17
    CoordMG --> UC18
    AuxMG --> UC14
    AuxMG --> UC17
```

## 2. Modelo entidad-relación

Se separan las entidades del núcleo de **Tutorías** y del módulo
**Modalidades de Grado (MG)**; ambos comparten `usuarios`/`estudiantes`/`tutores`.
No se listan todas las tablas auxiliares (historiales, bitácoras, contadores);
el detalle completo está en `db/init.sql`.

### 2.1 Núcleo: usuarios, catálogos y tutorías

```mermaid
erDiagram
    ROLES ||--o{ USUARIOS : tiene
    USUARIOS ||--o| ESTUDIANTES : es
    USUARIOS ||--o| TUTORES : es
    CARRERAS ||--o{ ESTUDIANTES : agrupa
    CARRERAS ||--o{ MATERIAS : ofrece
    TUTORES ||--o{ TUTOR_MATERIA_CONFIG : ofrece
    MATERIAS ||--o{ TUTOR_MATERIA_CONFIG : es_ofrecida
    PERIODOS ||--o{ TUTOR_MATERIA_CONFIG : delimita
    TIPOS_TUTORIA ||--o{ PERIODOS : clasifica
    PERIODOS ||--o{ GRUPOS_TUTORIA : delimita
    TUTORES ||--o{ GRUPOS_TUTORIA : dicta
    MATERIAS ||--o{ GRUPOS_TUTORIA : es
    ESPACIOS_TUTORIA ||--o{ GRUPOS_TUTORIA : usa
    GRUPOS_TUTORIA ||--o{ SESIONES_TUTORIA : programa
    GRUPOS_TUTORIA ||--o{ INSCRIPCIONES : recibe
    ESTUDIANTES ||--o{ INSCRIPCIONES : se_inscribe
    SESIONES_TUTORIA ||--o{ ASISTENCIAS_SESION : registra
    INSCRIPCIONES ||--o{ ASISTENCIAS_SESION : de
    GRUPOS_TUTORIA ||--o{ EVALUACIONES_GRUPO : recibe
    ESTUDIANTES ||--o{ DEMANDA_TUTORIA : solicita
    MATERIAS ||--o{ DEMANDA_TUTORIA : de

    USUARIOS {
        int id_usuario PK
        string usuario
        string contrasena_hash
        string estado
        int id_rol FK
    }
    ESTUDIANTES {
        int id_estudiante PK
        int id_usuario FK
        int id_carrera FK
        string registro_universitario
    }
    TUTORES {
        int id_tutor PK
        int id_usuario FK
        string estado_docente
    }
    GRUPOS_TUTORIA {
        int id_grupo PK
        int id_periodo FK
        int id_materia FK
        int id_tutor FK
        string estado
        int cupo_max
        int cupo_ocupado
    }
    SESIONES_TUTORIA {
        int id_sesion PK
        int id_grupo FK
        date fecha
        string estado
    }
    ASISTENCIAS_SESION {
        bigint id_asistencia PK
        int id_sesion FK
        int id_inscripcion FK
        string estado
    }
```

### 2.2 Modalidades de Grado

```mermaid
erDiagram
    COHORTES_MG ||--o{ EXPEDIENTES_MG : agrupa
    MODALIDADES_GRADO ||--o{ EXPEDIENTES_MG : clasifica
    ESTUDIANTES ||--o{ EXPEDIENTES_MG : tiene
    EXPEDIENTES_MG ||--o{ ASIGNACIONES_TUTOR_MG : asigna
    TUTORES ||--o{ ASIGNACIONES_TUTOR_MG : dirige
    EXPEDIENTES_MG ||--o{ REUNIONES_MG : registra
    ASIGNACIONES_TUTOR_MG ||--o{ REUNIONES_MG : de
    EXPEDIENTES_MG ||--o{ INFORMES_AVANCE_MG : presenta
    EXPEDIENTES_MG ||--o{ DEFENSAS_MG : programa
    DEFENSAS_MG ||--o{ TRIBUNALES_MG : integra
    DEFENSAS_MG ||--o| CALIFICACIONES_MG : obtiene

    EXPEDIENTES_MG {
        int id_expediente PK
        int id_estudiante FK
        int id_modalidad FK
        int id_cohorte FK
        string etapa_actual
        string estado
    }
    ASIGNACIONES_TUTOR_MG {
        int id_asignacion PK
        int id_expediente FK
        int id_tutor FK
        string estado
    }
    DEFENSAS_MG {
        int id_defensa PK
        int id_expediente FK
        string etapa
        date fecha
        string estado
    }
```

## 3. Arquitectura

Aplicación PHP nativo (MVC ligero, sin framework) con MySQL/MariaDB, servida
por Apache. El mismo código corre igual en Docker que en un Apache local
(XAMPP): solo cambia el host de base de datos y el puerto.

```mermaid
flowchart TB
    subgraph Cliente
        Browser["Navegador\n(Administrador / Tutor / Estudiante / Coordinación MG)"]
    end

    subgraph Servidor["Contenedor / servidor web"]
        Apache["Apache + PHP-FPM/mod_php\n(vhost -> router.php)"]
        Router["router.php\n(enruta a php/*.php y php/mg/*.php)"]
        Controllers["controller/*.php\n(reglas de negocio, transacciones)"]
        Models["models/*.php\n(consultas PDO preparadas)"]
        Views["views/*.php\n(plantillas HTML)"]
        Auth["includes/Auth.php\n(sesión, roles, permisos)"]
        DBConn["includes/Database.php\n(conexión PDO)"]
    end

    subgraph Datos
        MySQL[("MySQL / MariaDB\nbase testdb")]
    end

    Browser -->|HTTPS/HTTP| Apache --> Router --> Controllers
    Controllers --> Models --> DBConn --> MySQL
    Controllers --> Views --> Apache
    Router --> Auth
    Controllers --> Auth

    subgraph Docker["Despliegue con Docker (compose.yaml)"]
        WebContainer["servicio web\n(build: . -> Apache+PHP)"]
        DbContainer["servicio db\n(mysql:8.4, volumen db_data)"]
        WebContainer -->|DB_HOST=db| DbContainer
    end
```
