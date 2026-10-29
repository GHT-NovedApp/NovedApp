<!-- SDD Artifact | Version: 1.3 | Phase: Plan | Updated: 2026-10-21 -->
<!-- Project: NovedApp | Feature: 001-novedapp -->
<!-- Change Log:
  v1.0 - Estructuras actuales (JSON/PHP) leídas del código + esquema objetivo para Azure SQL Database.
  v1.1 - Catálogo central de Causa (+ TipoNovedad_Causa) y mapeo hoja-por-hoja de la plantilla de carga masiva.
  v1.2 - Cuentas propias (ClaveHash obligatorio, sin EntraObjectId) y adjuntos en Azure Blob.
  v1.3 - Tabla Configuracion para los parámetros que se fijan en la migración (contraseñas, adjuntos, correo).
-->

# Modelo de Datos

Este documento tiene dos partes: **(A)** cómo se guardan hoy los datos y **(B)** el esquema objetivo en
**Azure SQL Database**, con el mapeo de migración.

---

## A. Estado actual (v1)

| Dominio | Archivo | Registros hoy | Clave | Campos |
|---------|---------|---------------|-------|--------|
| Usuarios | `storage/usuarios.json` | 26 | nombre de usuario | `nombre`, `perfil`, `rol_organizacional`, `tipo_actor`, `organizacion`, `activo`, `puede_parametrizar`, `fecha_creacion`, `password` (texto, cuentas sembradas) **o** `password_hash` |
| Organizaciones | `storage/organizaciones.json` | 1 047 | `id` | `tipo_actor`, `nombre`, `ubicacion`, `activo` |
| Tipos de operación | `storage/tipos_operacion.json` | 3 | `id` | `codigo`, `clave` (estable), `nombre`, `descripcion`, `estado`, `fecha_creacion` |
| Tipologías | `storage/tipologias.json` | 6 | `id` | `codigo` (estable), `nombre`, `descripcion`, `estado`, `fecha_creacion` |
| Tipos de novedad | `storage/novedades_custom.json` | 7 | `titulo` | ver abajo |
| Novedades | `storage/novedades.json` | 21 | `id` (`NOV-AAAAMMDD-NNN`) | ver abajo |
| Notificaciones | `storage/notificaciones.json` | por usuario | usuario → lista | `novedad_id`, `tipo_novedad`, `organizacion`, `fecha`, `leida` |
| Datos de ejemplo | `data/novedades_ejemplo.php` | 12 | — | **No migrar** (G-6) |
| Actores / operaciones | `data/actores*.php`, `data/taxonomia.php` | — | — | Actores por operación; árbol Eje → Tipo → Subtipo |
| Catálogo heredado | `data/novedades.php`, `respondentes.php`, `permisos_respondentes.php` | 471 tipos | — | No se ofrece en el formulario; **no migrar** salvo decisión del PO |

**Tipo de novedad (catálogo)** — campos: `titulo`, `eje`, `tipo_cls`, `subtipo`, `tipo_operacion[]`, `tipologia` (una sola), `descripcion`, `requiere_respuesta`, `requiere_adjunto`, `involucra_terrestre`,
`involucra_vuelo`, `involucra_tiempos`, `notificar_admin`, `notificar_torre`, `actores_reportantes[]`,
`actores_respondentes[]`, `actores_etiquetables[]`, `causales[]`, `campos_grupo{grupo:[campos]}`, `campos[]`,
`info_necesaria[]`, `activo`, `creado_por`, `fecha_creacion`.

**Novedad** — campos: `id`, `tipo_novedad`, `tipologia`, `tipo_operacion[]`, `tipo_actor_reportante`,
`organizacion`, `usuario_reportante`, `estado` (`abierta` · `en_gestion` · `resuelta` · `reporte`), `fecha_creacion`,
`fecha_despacho`, `hora_despacho`, `causal`, `datos{}` (clave-valor libre: EXP ID, OTT, Placa…), `respondentes[]` (actores),
`respondentes_usuarios[]`, `destinatarios[]` (usuario, nombre, actor, organización, correo, principal, externo),
`hilo[]` (`autor`, `nombre`, `fecha`, `tipo`, `mensaje`, `adjuntos[]`), `resuelto_por`, `fecha_resolucion`, `updated_at`.

---

## B. Esquema objetivo — Azure SQL Database

Convenciones: esquema `dbo`; claves de negocio estables conservadas (`Usuario.NombreUsuario`,
`TipoOperacion.Clave`, `Tipologia.Codigo`); `datetime2` en UTC; borrado lógico mediante `Activo`/`Estado` salvo donde se indica.

### Diagrama de relaciones (resumen)

```
TipoActor 1─* Organizacion          TipoActor 1─* Usuario *─1 Organizacion
Usuario ─── (Permiso: administrador|torre_control|gestor|reportante)

TipoOperacion *─* TipoNovedad *─* Tipologia
TipoNovedad *─* TipoActor   (reportante / respondente / etiquetable)
TipoNovedad 1─* Causal · CampoGrupo · InfoNecesaria · CampoPersonalizado

TipoNovedad 1─* Novedad 1─* Mensaje · Destinatario · DatoEvento · Adjunto · NovedadTipologia
Novedad 1─* Notificacion *─1 Usuario
```

### DDL (T-SQL)

```sql
-- ===== Catálogos de actores y organizaciones =====
CREATE TABLE dbo.TipoActor (
  Codigo        NVARCHAR(30)  NOT NULL CONSTRAINT PK_TipoActor PRIMARY KEY,   -- EAI, CEDI, AdC, OTT, GHT...
  Descripcion   NVARCHAR(100) NULL
);

CREATE TABLE dbo.Organizacion (
  Id            INT IDENTITY(1,1) CONSTRAINT PK_Organizacion PRIMARY KEY,
  TipoActor     NVARCHAR(30)  NOT NULL CONSTRAINT FK_Org_TipoActor REFERENCES dbo.TipoActor(Codigo),
  Nombre        NVARCHAR(100) NOT NULL,
  Ubicacion     NVARCHAR(60)  NOT NULL CONSTRAINT DF_Org_Ubi DEFAULT (''),
  Activo        BIT           NOT NULL CONSTRAINT DF_Org_Activo DEFAULT (1),
  CONSTRAINT UQ_Organizacion UNIQUE (TipoActor, Nombre, Ubicacion)
);

-- ===== Identidad =====
CREATE TABLE dbo.Usuario (
  NombreUsuario      NVARCHAR(30)  NOT NULL CONSTRAINT PK_Usuario PRIMARY KEY,   -- minúsculas, 3-30
  Nombre             NVARCHAR(80)  NOT NULL,
  RolOrganizacional  NVARCHAR(80)  NOT NULL CONSTRAINT DF_Usr_Rol DEFAULT (''),  -- cargo real en la empresa
  Permiso            VARCHAR(20)   NOT NULL CONSTRAINT CK_Usr_Permiso
                       CHECK (Permiso IN ('administrador','torre_control','gestor','reportante')),
  PuedeParametrizar  BIT           NOT NULL CONSTRAINT DF_Usr_Param DEFAULT (0),  -- solo aplica a torre_control
  TipoActor          NVARCHAR(30)  NOT NULL CONSTRAINT FK_Usr_TipoActor REFERENCES dbo.TipoActor(Codigo),
  OrganizacionId     INT           NULL CONSTRAINT FK_Usr_Org REFERENCES dbo.Organizacion(Id),
  Activo             BIT           NOT NULL CONSTRAINT DF_Usr_Activo DEFAULT (1),
  ClaveHash          NVARCHAR(255) NOT NULL,                                      -- cuentas propias (CL-001): hash Argon2id/PBKDF2, nunca texto plano
  FechaCreacion      DATETIME2     NOT NULL CONSTRAINT DF_Usr_Fecha DEFAULT (SYSUTCDATETIME()),
  CONSTRAINT CK_Usr_Param CHECK (PuedeParametrizar = 0 OR Permiso = 'torre_control')
);

-- ===== Catálogos parametrizables =====
CREATE TABLE dbo.TipoOperacion (
  Id            INT IDENTITY(1,1) CONSTRAINT PK_TipoOperacion PRIMARY KEY,
  Codigo        NVARCHAR(10)  NOT NULL CONSTRAINT UQ_TipoOp_Codigo UNIQUE,       -- EXP, IMP, MVCS
  Clave         NVARCHAR(60)  NOT NULL CONSTRAINT UQ_TipoOp_Clave  UNIQUE,       -- exportacion, importacion, mvcs (estable)
  Nombre        NVARCHAR(60)  NOT NULL CONSTRAINT UQ_TipoOp_Nombre UNIQUE,
  Descripcion   NVARCHAR(300) NULL,
  Estado        TINYINT       NOT NULL CONSTRAINT DF_TipoOp_Estado DEFAULT (1)
                  CONSTRAINT CK_TipoOp_Estado CHECK (Estado IN (0,1)),
  FechaCreacion DATETIME2     NOT NULL CONSTRAINT DF_TipoOp_Fecha DEFAULT (SYSUTCDATETIME())
);

CREATE TABLE dbo.Tipologia (
  Id            INT IDENTITY(1,1) CONSTRAINT PK_Tipologia PRIMARY KEY,
  Codigo        NVARCHAR(10)  NOT NULL CONSTRAINT UQ_Tipologia_Codigo UNIQUE,    -- OPR, SEG, SST... (estable)
  Nombre        NVARCHAR(60)  NOT NULL CONSTRAINT UQ_Tipologia_Nombre UNIQUE,
  Descripcion   NVARCHAR(300) NULL,
  Estado        TINYINT       NOT NULL CONSTRAINT DF_Tipologia_Estado DEFAULT (1)
                  CONSTRAINT CK_Tipologia_Estado CHECK (Estado IN (0,1)),
  FechaCreacion DATETIME2     NOT NULL CONSTRAINT DF_Tipologia_Fecha DEFAULT (SYSUTCDATETIME())
);

-- ===== Catálogo de tipos de novedad =====
CREATE TABLE dbo.TipoNovedad (
  Titulo              NVARCHAR(120) NOT NULL CONSTRAINT PK_TipoNovedad PRIMARY KEY,  -- ACCIDENTE_DE_TRABAJO
  Eje                 NVARCHAR(40)  NOT NULL,
  TipoCls             NVARCHAR(60)  NOT NULL,
  Subtipo             NVARCHAR(60)  NOT NULL,
  Descripcion         NVARCHAR(500) NULL,
  RequiereRespuesta   BIT NOT NULL DEFAULT (1),
  RequiereAdjunto     BIT NOT NULL DEFAULT (0),
  InvolucraTerrestre  BIT NOT NULL DEFAULT (0),
  InvolucraVuelo      BIT NOT NULL DEFAULT (0),
  InvolucraTiempos    BIT NOT NULL DEFAULT (0),
  NotificarAdmin      BIT NOT NULL DEFAULT (1),
  NotificarTorre      BIT NOT NULL DEFAULT (1),
  Activo              BIT NOT NULL DEFAULT (1),
  CreadoPor           NVARCHAR(30)  NULL REFERENCES dbo.Usuario(NombreUsuario),
  FechaCreacion       DATETIME2     NOT NULL DEFAULT (SYSUTCDATETIME())
);

CREATE TABLE dbo.TipoNovedad_Operacion (
  Titulo NVARCHAR(120) NOT NULL REFERENCES dbo.TipoNovedad(Titulo) ON DELETE CASCADE,
  TipoOperacionId INT  NOT NULL REFERENCES dbo.TipoOperacion(Id),
  PRIMARY KEY (Titulo, TipoOperacionId)
);
CREATE TABLE dbo.TipoNovedad_Tipologia (
  Titulo NVARCHAR(120) NOT NULL REFERENCES dbo.TipoNovedad(Titulo) ON DELETE CASCADE,
  TipologiaId INT      NOT NULL REFERENCES dbo.Tipologia(Id),
  PRIMARY KEY (Titulo)                                  -- una sola tipología por tipo de novedad
);
CREATE TABLE dbo.TipoNovedad_Actor (
  Titulo NVARCHAR(120) NOT NULL REFERENCES dbo.TipoNovedad(Titulo) ON DELETE CASCADE,
  TipoActor NVARCHAR(30) NOT NULL REFERENCES dbo.TipoActor(Codigo),
  Rol    VARCHAR(12)   NOT NULL CHECK (Rol IN ('reporta','responde','etiqueta')),
  PRIMARY KEY (Titulo, TipoActor, Rol)
);
CREATE TABLE dbo.Causa (                          -- catálogo central de causas (hoy: texto dentro de cada tipo de novedad)
  Id INT IDENTITY(1,1) CONSTRAINT PK_Causa PRIMARY KEY,
  Nombre      NVARCHAR(120) NOT NULL CONSTRAINT UQ_Causa_Nombre UNIQUE,
  Descripcion NVARCHAR(300) NULL,
  Activo      BIT NOT NULL CONSTRAINT DF_Causa_Activo DEFAULT (1)
);
CREATE TABLE dbo.TipoNovedad_Causa (               -- qué causas aplican a cada tipo de novedad
  Titulo  NVARCHAR(120) NOT NULL REFERENCES dbo.TipoNovedad(Titulo) ON DELETE CASCADE,
  CausaId INT           NOT NULL REFERENCES dbo.Causa(Id),
  Orden   SMALLINT      NOT NULL DEFAULT (0),
  PRIMARY KEY (Titulo, CausaId)
);
CREATE TABLE dbo.TipoNovedad_CampoGrupo (          -- qué campos de cada grupo se piden
  Titulo NVARCHAR(120) NOT NULL REFERENCES dbo.TipoNovedad(Titulo) ON DELETE CASCADE,
  Grupo  VARCHAR(10)   NOT NULL CHECK (Grupo IN ('terrestre','aereo','tiempos')),
  Campo  NVARCHAR(40)  NOT NULL,                  -- ott, placa, conductor, ... mawb, ... hora_reporte...
  PRIMARY KEY (Titulo, Grupo, Campo)
);
CREATE TABLE dbo.TipoNovedad_InfoNecesaria (       -- campos obligatorios al registrar
  Id INT IDENTITY(1,1) PRIMARY KEY,
  Titulo NVARCHAR(120) NOT NULL REFERENCES dbo.TipoNovedad(Titulo) ON DELETE CASCADE,
  Nombre NVARCHAR(80)  NOT NULL,
  Tipo   VARCHAR(10)   NOT NULL DEFAULT 'text',
  UNIQUE (Titulo, Nombre)
);
CREATE TABLE dbo.TipoNovedad_CampoPersonalizado (
  Id INT IDENTITY(1,1) PRIMARY KEY,
  Titulo NVARCHAR(120) NOT NULL REFERENCES dbo.TipoNovedad(Titulo) ON DELETE CASCADE,
  Nombre NVARCHAR(80)  NOT NULL,
  Tipo   VARCHAR(10)   NOT NULL DEFAULT 'text',
  Requerido BIT        NOT NULL DEFAULT (0)
);

-- ===== Novedades =====
CREATE TABLE dbo.Novedad (
  Id                 NVARCHAR(24)  NOT NULL CONSTRAINT PK_Novedad PRIMARY KEY,   -- NOV-AAAAMMDD-NNN
  TipoNovedad        NVARCHAR(120) NOT NULL REFERENCES dbo.TipoNovedad(Titulo),
  TipologiaPrincipal NVARCHAR(10)  NOT NULL,                                     -- la que eligió quien reporta
  TipoOperacionId    INT           NOT NULL REFERENCES dbo.TipoOperacion(Id),
  Estado             VARCHAR(12)   NOT NULL CHECK (Estado IN ('abierta','en_gestion','resuelta','reporte')),
  UsuarioReportante  NVARCHAR(30)  NOT NULL REFERENCES dbo.Usuario(NombreUsuario),
  TipoActorReportante NVARCHAR(30) NOT NULL REFERENCES dbo.TipoActor(Codigo),
  OrganizacionTexto  NVARCHAR(100) NULL,                                         -- nombre al momento del reporte (histórico)
  Causal             NVARCHAR(120) NULL,                                         -- nombre de la causa elegida (histórico)
  FechaCreacion      DATETIME2     NOT NULL DEFAULT (SYSUTCDATETIME()),
  FechaDespacho      DATE          NULL,
  HoraDespacho       TIME(0)       NULL,
  ResueltoPor        NVARCHAR(30)  NULL REFERENCES dbo.Usuario(NombreUsuario),
  FechaResolucion    DATETIME2     NULL,
  ActualizadoEn      DATETIME2     NOT NULL DEFAULT (SYSUTCDATETIME())
);
CREATE TABLE dbo.Novedad_Tipologia (
  NovedadId NVARCHAR(24) NOT NULL REFERENCES dbo.Novedad(Id) ON DELETE CASCADE,
  TipologiaId INT        NOT NULL REFERENCES dbo.Tipologia(Id),
  PRIMARY KEY (NovedadId)                               -- una sola tipología por novedad
);
CREATE TABLE dbo.Novedad_DatoEvento (              -- clave-valor del evento (EXP ID, OTT, Placa, ...)
  NovedadId NVARCHAR(24) NOT NULL REFERENCES dbo.Novedad(Id) ON DELETE CASCADE,
  Clave     NVARCHAR(60) NOT NULL,
  Valor     NVARCHAR(500) NULL,
  Orden     SMALLINT     NOT NULL DEFAULT (0),     -- permite varios valores (p. ej. EAI)
  PRIMARY KEY (NovedadId, Clave, Orden)
);
CREATE TABLE dbo.Novedad_Destinatario (
  Id INT IDENTITY(1,1) PRIMARY KEY,
  NovedadId NVARCHAR(24) NOT NULL REFERENCES dbo.Novedad(Id) ON DELETE CASCADE,
  Usuario   NVARCHAR(30) NULL REFERENCES dbo.Usuario(NombreUsuario),   -- NULL si es correo externo
  TipoActor NVARCHAR(30) NULL,
  Correo    NVARCHAR(200) NULL,
  EsPrincipal BIT NOT NULL DEFAULT (0),
  EsExterno BIT NOT NULL DEFAULT (0),
  CONSTRAINT CK_Dest CHECK (Usuario IS NOT NULL OR Correo IS NOT NULL)
);
CREATE TABLE dbo.Novedad_Mensaje (                  -- hilo
  Id INT IDENTITY(1,1) PRIMARY KEY,
  NovedadId NVARCHAR(24) NOT NULL REFERENCES dbo.Novedad(Id) ON DELETE CASCADE,
  Autor     NVARCHAR(30) NOT NULL REFERENCES dbo.Usuario(NombreUsuario),
  Tipo      VARCHAR(10)  NOT NULL CHECK (Tipo IN ('reporte','respuesta','replica','cierre')),
  Mensaje   NVARCHAR(MAX) NOT NULL,
  Fecha     DATETIME2    NOT NULL DEFAULT (SYSUTCDATETIME())
);
CREATE TABLE dbo.Novedad_Adjunto (                  -- Azure Blob Storage (decidido, CL-002); RutaBlob apunta al contenedor privado
  Id INT IDENTITY(1,1) PRIMARY KEY,
  NovedadId NVARCHAR(24) NOT NULL REFERENCES dbo.Novedad(Id) ON DELETE CASCADE,
  MensajeId INT NULL REFERENCES dbo.Novedad_Mensaje(Id),
  NombreArchivo NVARCHAR(200) NOT NULL,
  RutaBlob NVARCHAR(400) NOT NULL,
  TamanoBytes BIGINT NOT NULL,
  TipoMime VARCHAR(100) NOT NULL
);

-- ===== Notificaciones y auditoría =====
CREATE TABLE dbo.Notificacion (
  Id INT IDENTITY(1,1) PRIMARY KEY,
  Usuario   NVARCHAR(30) NOT NULL REFERENCES dbo.Usuario(NombreUsuario),
  NovedadId NVARCHAR(24) NOT NULL REFERENCES dbo.Novedad(Id) ON DELETE CASCADE,
  Fecha     DATETIME2    NOT NULL DEFAULT (SYSUTCDATETIME()),
  Leida     BIT          NOT NULL DEFAULT (0),
  CONSTRAINT UQ_Notif UNIQUE (Usuario, NovedadId)
);
CREATE TABLE dbo.Auditoria (                         -- FR-048
  Id BIGINT IDENTITY(1,1) PRIMARY KEY,
  Fecha DATETIME2 NOT NULL DEFAULT (SYSUTCDATETIME()),
  Usuario NVARCHAR(30) NOT NULL,
  Entidad VARCHAR(40) NOT NULL,         -- Usuario, Organizacion, TipoNovedad, ...
  EntidadId NVARCHAR(120) NOT NULL,
  Accion VARCHAR(20) NOT NULL,          -- crear, editar, estado, eliminar, password
  Detalle NVARCHAR(MAX) NULL
);
```

### Configuración del sistema (parámetros fijados en la migración)

```sql
CREATE TABLE dbo.Configuracion (               -- un registro por parámetro; el API lo lee y lo publica
  Clave        VARCHAR(80)   NOT NULL CONSTRAINT PK_Configuracion PRIMARY KEY,   -- p. ej. 'password.largo_minimo'
  Valor        NVARCHAR(500) NOT NULL,
  Tipo         VARCHAR(10)   NOT NULL CHECK (Tipo IN ('texto','numero','booleano','lista')),
  Descripcion  NVARCHAR(300) NULL,
  ActualizadoPor NVARCHAR(30) NULL,
  ActualizadoEn  DATETIME2    NOT NULL DEFAULT (SYSUTCDATETIME())
);
```

Claves previstas (valores de referencia entre paréntesis; se fijan en la migración):
`password.largo_minimo` (6), `password.complejidad`, `password.caducidad_dias`, `password.max_intentos`, `password.bloqueo_minutos`, `password.recuperacion_habilitada`,
`adjuntos.tipos_permitidos` (jpg,png,pdf,xlsx,docx), `adjuntos.tamano_max_mb` (10), `adjuntos.max_por_mensaje` (5), `adjuntos.retencion_dias` (sin límite),
`correo.habilitado` (false), `correo.proveedor`, `correo.remitente`.

### Índices recomendados (Art. VIII)

```sql
CREATE INDEX IX_Novedad_Estado_Fecha   ON dbo.Novedad (Estado, FechaCreacion DESC) INCLUDE (TipoNovedad, TipoOperacionId);
CREATE INDEX IX_Novedad_Reportante     ON dbo.Novedad (UsuarioReportante, Estado);
CREATE INDEX IX_Novedad_Tipo           ON dbo.Novedad (TipoNovedad);
CREATE INDEX IX_NovDest_Usuario        ON dbo.Novedad_Destinatario (Usuario) INCLUDE (NovedadId);
CREATE INDEX IX_NovDest_Actor          ON dbo.Novedad_Destinatario (TipoActor) INCLUDE (NovedadId);
CREATE INDEX IX_NovMsg_Novedad         ON dbo.Novedad_Mensaje (NovedadId, Fecha);
CREATE INDEX IX_Notif_Usuario_Leida    ON dbo.Notificacion (Usuario, Leida) INCLUDE (Fecha, NovedadId);
CREATE INDEX IX_Org_Tipo_Activo        ON dbo.Organizacion (TipoActor, Activo) INCLUDE (Nombre, Ubicacion);
CREATE INDEX IX_TipoNovTip_Tipologia   ON dbo.TipoNovedad_Tipologia (TipologiaId) INCLUDE (Titulo);
```

### Reglas de validación (a hacer cumplir en el API y con restricciones SQL)

- `Usuario.NombreUsuario`: `^[a-z0-9._-]{3,30}$`; no se renombra si tiene `Novedad`, `Novedad_Mensaje` o `Notificacion` asociadas.
- Debe existir **al menos un administrador activo**, un `TipoOperacion` activo y una `Tipologia` activa.
- `TipoOperacion.Codigo`/`Tipologia.Codigo`: `^[A-Z0-9_]{2,10}$`; la `Clave` de operación y el `Codigo` de tipología **no cambian** si hay historial; no se eliminan si están en uso.
- **Regla Terrestre/OTT:** si `TipoNovedad.InvolucraTerrestre = 1` y algún `Novedad_Destinatario` (no externo) pertenece a `OTT` u `OTT_TERCEROS`
  (según `Usuario.TipoActor`, no según lo enviado por el cliente), todos los campos de `TipoNovedad_CampoGrupo` del grupo `terrestre` son obligatorios.
- Un usuario no-administrador no puede asignar `administrador`/`torre_control` ni editar cuentas con esos permisos.
- La visibilidad por perfil se resuelve **en la consulta** (`WHERE`) según `Permiso`, `TipoActor`, `Usuario` y destinatarios.

### Mapeo de migración

| Origen | Destino | Notas |
|--------|---------|-------|
| `storage/usuarios.json` | `Usuario` | `password` en texto → generar `ClaveHash`/forzar cambio; mapear `organizacion` (texto) → `OrganizacionId` |
| `storage/organizaciones.json` | `Organizacion` (+ `TipoActor` derivados) | Conservar `id` como referencia de migración |
| `storage/tipos_operacion.json`, `tipologias.json` | `TipoOperacion`, `Tipologia` | Conservar `clave` / `codigo` |
| `storage/novedades_custom.json` | `TipoNovedad` + tablas de relación (`_Operacion`, `_Tipologia`, `_Actor`, `Causa`/`_Causa`, `_CampoGrupo`, `_InfoNecesaria`, `_CampoPersonalizado`) | `tipologias[]` ← o `[tipologia]` si es formato antiguo; `causales[]` (texto) → filas de `Causa` + `TipoNovedad_Causa` |
| `storage/novedades.json` | `Novedad` + `Novedad_Tipologia`, `_DatoEvento`, `_Destinatario`, `_Mensaje` | `datos{}` → filas clave-valor; el primer mensaje del hilo es el reporte |
| `storage/notificaciones.json` | `Notificacion` | Filtrar notificaciones de novedades inexistentes |
| `data/novedades_ejemplo.php` | — | **No migrar** (G-6) |

---

## C. Plantilla de carga masiva ↔ tablas

Archivo: `docs/plantillas/Plantilla_Carga_Masiva_NovedApp_v1.1.xlsx`. Una hoja por tabla; las filas 1 a 3 (encabezado, ayuda, ejemplo) no se cargan; los datos empiezan en la fila 4.

| Hoja | Tabla destino | Clave natural | Comentarios |
|------|---------------|---------------|-------------|
| `TipoActor` | `TipoActor` | `Codigo` | Precargados los 21 actuales |
| `Organizacion` | `Organizacion` | `TipoActor + Nombre + Ubicacion` | `Estado` vacío = Activo |
| `Usuario` | `Usuario` | `NombreUsuario` | `Organizacion` (+ `UbicacionOrganizacion`) se resuelve a `OrganizacionId`; `ClaveInicial` se guarda con hash (si viene vacía se genera una clave temporal) |
| `TipoOperacion` | `TipoOperacion` | `Clave` | Precargadas Exportación, Importación y MVCS |
| `Tipologia` | `Tipologia` | `Codigo` | Precargadas las 6 actuales |
| `Causa` | `Causa` | `Nombre` | Catálogo central; editable en pantalla solo si el PO lo decide más adelante |
| `TipoNovedad` | `TipoNovedad` | `Titulo` | Admite los 471 tipos del catálogo heredado |
| `TN_Operacion` | `TipoNovedad_Operacion` | `Titulo + ClaveOperacion` | |
| `TN_Tipologia` | `TipoNovedad_Tipologia` | `Titulo + CodigoTipologia` | `tipologia` |
| `TN_Actor` | `TipoNovedad_Actor` | `Titulo + TipoActor + Rol` | `Rol`: reporta · responde · etiqueta |
| `TN_Causa` | `TipoNovedad_Causa` | `Titulo + Causa` | |
| `TN_CampoGrupo` | `TipoNovedad_CampoGrupo` | `Titulo + Grupo + Campo` | Campos válidos en la hoja `CamposGrupo` |
| `TN_InfoNecesaria` | `TipoNovedad_InfoNecesaria` | `Titulo + Nombre` | |
| `TN_CampoPersonalizado` | `TipoNovedad_CampoPersonalizado` | `Titulo + Nombre` | |

**Validaciones del importador** (rechaza el archivo completo si hay errores; devuelve un informe por hoja y fila):
referencias inexistentes (organización, actor, operación, tipología, causa, título); duplicados por clave natural; formatos (usuario, siglas, claves); `PuedeParametrizar = Sí` solo con `torre_control`;
al menos un administrador, un tipo de operación y una tipología activos; cada tipo de novedad con ≥ 1 operación, ≥ 1 tipología, ≥ 1 actor que reporta y ≥ 1 que responde;
campos de `TN_CampoGrupo` que pertenezcan al grupo indicado y grupos habilitados en `TipoNovedad`.
