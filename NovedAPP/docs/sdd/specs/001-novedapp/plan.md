<!-- SDD Artifact | Version: 1.3 | Phase: Plan | Updated: 2026-10-21 -->
<!-- Project: NovedApp | Feature: 001-novedapp -->
<!-- Spec: specs/001-novedapp/spec.md -->
<!-- Change Log:
  v1.0 - Plan de migración del v1 (PHP + JSON) a Azure App Service (Web Site + Web API) y Azure SQL Database.
  v1.1 - Carga masiva por plantilla, exportación por visibilidad, meta de > 50 usuarios simultáneos.
  v1.2 - Decididas D-001 (PHP), D-002 (cuentas propias) y D-003 (Azure Blob); correo diferido.
  v1.3 - Sección de parámetros de configuración fijados en la migración (contraseñas, adjuntos, correo).
-->

# Plan de Implementación: NovedApp en Azure

## Metadatos

| Campo | Valor |
|-------|-------|
| Funcionalidad | 001-novedapp |
| Fecha | 2026-10-21 |
| Spec | [spec.md](./spec.md) |
| Constitución | [constitution.md](../../constitution.md) |
| Modelo de datos | [data-model.md](./data-model.md) |
| Contrato de API | [contracts/api-spec.json](./contracts/api-spec.json) |

**Objetivo:** llevar NovedApp, hoy una aplicación PHP con archivos JSON, a una arquitectura de dos aplicaciones
en **Azure App Service** (Web Site y Web API) sobre **Azure SQL Database**, conservando el comportamiento
descrito en la especificación y cerrando las brechas G-1 a G-8.

---

## Decisiones de Arquitectura e Identidad (estado)

| ID | Decisión | Opciones | Recomendación | Efecto |
|----|----------|----------|---------------|--------|
| **D-001** ✅ | Lenguaje/framework del **Web API** y del **Web Site** | — | **Decidido por el PO: PHP en ambos** (opción A). **Condición:** la prueba de concepto T-002 debe demostrar acceso a Azure SQL con Managed Identity; si falla, se activa el plan B (API en .NET con el sitio en PHP) | Estructura del repo, pruebas, librería de acceso a SQL |
| **D-002** ✅ | Identidad (CL-001) | — | **Decidido por el PO: solo cuentas propias** guardadas en Azure SQL, con hash fuerte. Sin SSO por ahora. La política de contraseñas es un parámetro que se fija en la migración (ver §Parámetros) | Seguridad, alta y recuperación de usuarios |
| **D-003** ✅ | Adjuntos (CL-002) | — | **Decidido por el PO: sí, en Azure Blob Storage**, contenedor privado, acceso por Managed Identity y URL SAS de vida corta. Los límites (tipos, tamaño, cantidad, retención) son parámetros que se fijan en la migración (ver §Parámetros) | Nuevo recurso Azure |

> Las decisiones D-001 a D-003 ya están tomadas; las tareas que dependen de ellas conservan la etiqueta `[D-00X]` como referencia. D-001 queda condicionada al resultado de la prueba de concepto T-002.

---

## Parámetros de Configuración (se fijan en la migración)

Decisión del PO (2026-10-22): la **política de contraseñas**, los **límites de adjuntos** y el **envío de correo** son parámetros que se definen en el momento de la migración, según los requerimientos de la migración, para lograr compatibilidad total y evitar inconsistencias o cambios grandes cuando luego se conecten APIs o herramientas.

**Cómo se diseña para que eso sea posible**

| Principio | Aplicación |
|-----------|-----------|
| Un solo origen de verdad | El Web API guarda y valida los parámetros (tabla `Configuracion` o App Settings, ver `data-model.md`) y los publica en `GET /configuracion/politicas`; el Web Site los lee y no los duplica |
| Sin valores fijos en el código | Contraseñas (largo, complejidad, caducidad, intentos, bloqueo, recuperación), adjuntos (tipos, tamaño, cantidad, retención) y correo (proveedor, remitente, habilitado) se leen siempre de configuración |
| Integraciones detrás de una interfaz | Correo: interfaz `NotificadorCorreo` con implementación nula hoy; conectar una API es sustituir la implementación y activar el parámetro. Adjuntos: interfaz de almacenamiento con la implementación de Azure Blob |
| Mismos límites en cliente y servidor | El formulario valida con los valores del API; el servidor vuelve a validar |
| Valores de referencia mientras tanto | Contraseña mínima de 6 caracteres con hash; adjuntos JPG/PNG/PDF/Excel/Word, 10 MB por archivo y 5 por mensaje; correo deshabilitado |

**Cuándo se fijan:** durante la Fase 1 (datos), junto con la carga de la plantilla y los requerimientos de la migración (tarea T-024).

## Compuertas Previas (cumplimiento de la Constitución)

- [x] Art. I Modularidad: Web Site y Web API separados; módulos por dominio.
- [x] Art. II Contratos: `contracts/api-spec.json` se define antes de implementar.
- [ ] Art. III Pruebas: estrategia definida en este plan; las pruebas se crean en la Fase 0 y 2 (no existen hoy).
- [x] Art. IV Configuración: App Settings + Key Vault + Managed Identity.
- [x] Art. V Errores: `application/problem+json` en el API.
- [x] Art. VI Observabilidad: Application Insights y `/health`.
- [ ] Art. VII Seguridad: cuentas propias con hash y CSRF obligatorios; la política de contraseñas es un parámetro configurable que se fija en la migración.
- [x] Art. VIII Rendimiento: paginación y filtros en SQL; índices definidos.
- [x] Art. IX Documentación: OpenAPI + SDD + ADR.
- [x] Art. X UX/Accesibilidad: se conserva la UI actual (ya cumple AA); no se rediseña.

---

## Visión General de la Arquitectura

### Contexto

```
 Usuarios (navegador, escritorio y móvil)
        │ HTTPS
        ▼
 ┌──────────────────────────┐        ┌────────────────────────────┐
 │ Azure App Service         │  HTTPS │ Azure App Service            │
 │ Web Site  (app-novedapp-web)├──────►│ Web API   (app-novedapp-api) │
 │  · páginas y formularios   │  JSON  │  · reglas de negocio / RBAC   │
 │  · sesión del usuario      │        │  · validaciones en servidor   │
 └──────────────────────────┘        └──────┬─────────────────────┘
                                             │ Managed Identity (Entra ID)
                         ┌───────────────────┼───────────────────┐
                         ▼                   ▼                   ▼
                 Azure SQL Database     Key Vault          Blob Storage (adjuntos)
                 (repositorio de datos) (secretos)         Blob Storage
                         ▲
        Application Insights + Log Analytics (ambas apps)
```

### Componentes

| Componente | Responsabilidad |
|-----------|-----------------|
| **Web Site** | Presentación (pantallas actuales), manejo de sesión/cookie, llamadas al API. Sin acceso directo a SQL. |
| **Web API** | Único punto de acceso a Azure SQL. Autenticación/autorización, validaciones, reglas (Terrestre/OTT, permisos por actor, unicidad), notificaciones, exportación. |
| **Azure SQL Database** | Repositorio de datos: catálogos, usuarios, organizaciones, novedades, hilo, notificaciones. |
| **Key Vault** | Secretos (cadenas, claves de firma, credenciales de correo si aplica). |
| **Application Insights** | Logs, métricas, trazas y alertas. |

### Flujo de datos — registrar una novedad

1. El Web Site carga catálogos (`GET /catalogos/tipos-novedad?reportante=…`) y pinta el formulario en cascada.
2. El usuario completa y envía; el Web Site llama `POST /novedades` con el token del usuario.
3. El Web API valida **todo** otra vez (tipo activo, permiso de reportar, info necesaria, datos terrestres si un destinatario es OTT),
   guarda `Novedad`, destinatarios, datos y primer mensaje del hilo en **una transacción**.
4. Crea `Notificacion` para destinatarios y, según flags del tipo, administrador y torre. Responde `201` con el id.

---

## Decisiones Tecnológicas

| Capa | Elección | Justificación |
|------|----------|---------------|
| Web Site | Azure App Service (Web App, Linux), 1 slot de *staging* | Definido por el PO; slots permiten despliegue sin interrupción |
| Web API | Azure App Service (Web App, Linux), 1 slot de *staging* | Definido por el PO; escalado independiente del sitio |
| Plan de hosting | App Service Plan compartido por ambas apps en dev/test; separado en producción (Premium v3) ⚠ | Costo bajo en pruebas, aislamiento en producción |
| Base de datos | Azure SQL Database (base única) ⚠ nivel *General Purpose* | Definido por el PO; el volumen actual (≈1 000 organizaciones, decenas de usuarios, miles de novedades) es pequeño |
| Acceso a datos | Consultas parametrizadas / ORM ligero; migraciones versionadas | Evita inyección SQL y permite reproducir el esquema |
| Autenticación a SQL | Managed Identity + usuario contenido en la base | Sin contraseñas en configuración (Art. IV) |
| Secretos | Azure Key Vault + referencias en App Settings | Art. IV |
| Observabilidad | Application Insights (workspace-based) | Art. VI |
| IaC | Bicep ⚠ | Ambientes reproducibles |
| CI/CD | [pendiente: GitHub Actions o Azure Pipelines] | Según el repositorio que se use |
| Lenguaje | **PHP 8.2 en Web Site y Web API** (decidido; sujeto a T-002) | Continuidad del equipo y reutilización de la UI y las reglas actuales |

---

## Estructura del Proyecto (propuesta)

```
novedapp/
├── infra/                    # Bicep: App Services, SQL, Key Vault, App Insights
├── db/
│   ├── migrations/           # DDL versionado (ver data-model.md)
│   └── seed/                 # catálogos iniciales (tipos de operación, tipologías, actores)
├── api/                      # Web API
│   ├── src/                  #   Identidad · Catálogos · Novedades · Notificaciones · Reportes
│   └── tests/                #   unitarias + integración (SQL de pruebas)
├── web/                      # Web Site (pantallas actuales)
│   ├── pages/  includes/  css/  js/   # base: carpetas actuales del v1
│   └── tests/                #   E2E de flujos críticos
├── tools/migracion-json/     # importador de storage/*.json y data/*.php → Azure SQL
└── docs/                     # SDD, ADR
```

**Decisión de estructura:** monorepo con `api/` y `web/` desplegados a App Services distintos, para versionar
juntos el contrato y su consumidor.

---

## Fases de Implementación

### Fase 0 — Línea base y fundamentos
- Congelar el comportamiento actual con pruebas E2E de los flujos críticos (US-001, 002, 003, 005, 009, 010).
- Crear el repositorio, la estructura y los pipelines; aprovisionar recursos Azure con Bicep (dev).
- Registrar D-001 (PHP), D-002 (cuentas propias) y D-003 (Blob) en ADR y ejecutar la prueba de concepto T-002; si falla, activar el plan B.

### Fase 1 — Datos
- Crear el esquema en Azure SQL (`data-model.md`) con migraciones.
- **Carga masiva:** los datos maestros (usuarios, organizaciones, catálogos, 471 tipos de novedad con sus permisos, causas y campos) llegan en la plantilla `docs/plantillas/Plantilla_Carga_Masiva_NovedApp_v1.1.xlsx`, **entregada antes de las pruebas funcionales**. Un importador la valida y la carga (ver `data-model.md` §C).
- Las novedades y notificaciones existentes (`storage/novedades.json`, `notificaciones.json`) se importan desde JSON solo si el PO quiere conservar el historial de prueba. Excluir las novedades de ejemplo (G-6).

### Fase 2 — Web API (por historia, en orden de prioridad)
1. Identidad y permisos (US-001, US-010) → 2. Catálogos y Parametrización (US-007, 008, 009, 011)
→ 3. Novedades y gestión (US-002, 003, 006) → 4. Paneles y notificaciones (US-004, 005) → 5. Exportación (US-012)
→ 6. Adjuntos en Blob (US-013). El envío de correo queda diferido hasta conectar una API para esa función.

### Fase 3 — Web Site
- Sustituir `includes/*_store.php` y `storage.php` por llamadas al API; conservar páginas, estilos y comportamiento.
- Mover validaciones críticas al API; el sitio conserva validaciones de usabilidad.

### Fase 4 — Endurecimiento y salida a producción
- CSRF global, límites de intentos, cabeceras de seguridad, retiro de la contraseña demo.
- Pruebas de rendimiento contra Art. VIII; alertas; auditoría (FR-048).
- Ensayo de migración de datos y *cutover* con ventana acordada.

---

## Resumen del Modelo de Datos

(Detalle en `data-model.md`.) Núcleo: `Usuario`, `Organizacion`, `TipoActor`, `TipoOperacion`, `Tipologia`,
`TipoNovedad` (+ tablas de relación: operaciones, tipologías, actores que reportan/responden/etiquetan,
causales, campos por grupo, información necesaria, campos personalizados), `Novedad` (+ tipologías, destinatarios,
datos del evento, mensajes del hilo, adjuntos) y `Notificacion`.

## Resumen del Contrato de API

(Detalle en `contracts/api-spec.json`, base `/api/v1`.)

| Grupo | Endpoints principales | Autorización |
|-------|----------------------|--------------|
| Identidad | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` | Público / sesión |
| Usuarios | `GET/POST /usuarios`, `PUT /usuarios/{u}`, `POST /usuarios/{u}/password`, `POST /usuarios/{u}/estado` | Parametrización |
| Organizaciones | `GET/POST /organizaciones`, `PUT /organizaciones/{id}`, `POST /organizaciones/{id}/estado` | Parametrización |
| Catálogos | `/tipos-operacion`, `/tipologias`, `/causas` (CRUD + estado) | Lectura: autenticado · Escritura: Parametrización |
| Tipos de novedad | `GET/POST /tipos-novedad`, `PUT/DELETE /tipos-novedad/{titulo}`, `POST …/estado` | Lectura: autenticado · Escritura: Parametrización |
| Novedades | `GET/POST /novedades`, `GET /novedades/{id}`, `POST …/respuestas`, `…/replicas`, `…/resolucion` | Según permiso y actor |
| Notificaciones | `GET /notificaciones`, `POST …/{id}/leida`, `DELETE …/{id}` | Propias |
| Reportes | `GET /novedades/exportacion` | Cualquier usuario autenticado (solo lo que ve); administrador y torre, todo |

---

## Evaluación de Riesgos

| Riesgo | Impacto | Probabilidad | Mitigación |
|--------|---------|--------------|------------|
| La prueba de concepto T-002 (PHP + Azure SQL con Managed Identity) falla o se retrasa | Alto | Media | Ejecutarla primero en la Fase 0; plan B: API en .NET con el sitio en PHP |
| Driver de SQL Server para PHP en App Service Linux requiere configuración adicional (ver `research.md`) | Medio | Media | Prueba de concepto en Fase 0; plan B: .NET 8 para el API |
| Migración de contraseñas: las cuentas sembradas están en texto plano | Alto | Alta | Forzar restablecimiento al primer ingreso o generar claves temporales |
| Pérdida de datos al importar JSON (claves de texto como `usuario`, `clave` de operación, `codigo` de tipología) | Alto | Media | Conservar esas claves como identificadores de negocio; validar conteos y checksums |
| Parámetros (contraseñas, adjuntos, correo) escritos en el código y luego difíciles de cambiar | Medio | Media | Externalizarlos desde el inicio, validarlos en un único lugar (el API) y fijarlos con los requerimientos de la migración |
| Dependencia de datos de ejemplo en producción | Medio | Baja | Excluir `novedades_ejemplo.php` del importador (G-6) |
| Costos de Azure no dimensionados | Bajo | Media | Nivel inicial pequeño + alertas de presupuesto |
| La plantilla llega incompleta o con errores cerca de las pruebas funcionales | Alto | Media | Importador con validación y informe por hoja/fila; ensayo de carga con una entrega parcial antes de la fecha límite |
| Carga concurrente: la meta es más de 50 usuarios simultáneos | Medio | Baja | Pruebas de carga con 75 usuarios; paginación y consultas indexadas; escalado del App Service Plan |

## Seguimiento de Complejidad

| Componente | Estimación | Confianza | Notas |
|-----------|-----------|-----------|-------|
| Infraestructura + CI/CD | M | Media | Depende del resultado de T-002 |
| Esquema SQL + importador de plantilla | M | Alta | Estructuras conocidas; la plantilla ya está definida |
| API Identidad/RBAC | M | Media | Cambia si SSO |
| API Parametrización (5 módulos) | L | Alta | Reglas ya especificadas |
| API Novedades + notificaciones | L | Alta | Regla Terrestre/OTT y permisos por actor |
| Adaptación del Web Site | L | Media | 11 pantallas, mucha lógica en JS |
| Adjuntos (Blob) | M | Media | Límites configurables; el correo se integra detrás de una interfaz |
| Endurecimiento + rendimiento | M | Media | |
