<!-- SDD Artifact | Version: 1.3 | Phase: Tasks | Updated: 2026-10-21 -->
<!-- Project: NovedApp | Feature: 001-novedapp -->
<!-- Plan: specs/001-novedapp/plan.md -->
<!-- Change Log:
  v1.0 - Línea base (lo ya construido en v1) + tareas de migración a Azure App Service + Azure SQL.
  v1.1 - Importador de plantilla, gate de entrega de la plantilla, exportación por visibilidad ya construida, panel propio de la torre.
  v1.2 - D-001/D-002/D-003 decididas; correo diferido (T-071); adjuntos en Azure Blob (T-070).
  v1.3 - Parámetros de configuración (contraseñas, adjuntos, correo) fijados en la migración: T-001 deja de ser bloqueante; nuevas T-024 y T-072.
-->

# Desglose de Tareas: NovedApp en Azure

## Convenciones

- **[P]** = puede hacerse en paralelo (archivos o recursos distintos, sin dependencias compartidas).
- **[Story: USxxx]** = historia de la especificación a la que pertenece.
- **[Dep: T-nnn]** = depende de la tarea indicada.
- **[D-00X]** = depende de una decisión del plan. D-001 (PHP), D-002 (cuentas propias) y D-003 (Azure Blob) ya están **decididas**; la etiqueta queda solo como referencia.
- Las rutas son relativas a la raíz del repositorio objetivo (`novedapp/`, ver `plan.md`). Para el v1 se indica la ruta actual.
- Las pruebas se incluyen porque la Constitución (Art. III) las exige.

---

## Línea Base — ya construido en el v1 (referencia, no se reprograma)

Estado verificado leyendo el código actual (`C:\xampp\htdocs\NovedAPP`). Sirve como criterio de equivalencia
funcional en la migración.

| Historia | Estado v1 | Módulos actuales (v1) |
|----------|-----------|----------------------|
| US-001 Acceso | ✅ (con deuda de seguridad G-4) | `login.php`, `logout.php`, `includes/auth.php`, `includes/usuarios_store.php` |
| US-002 Registrar novedad | ✅ salvo adjuntos/correo | `pages/nueva_novedad.php`, `js/formulario_dinamico.js`, `css/nueva_novedad.css` |
| US-003 Gestionar | ✅ | `pages/detalle_novedad.php`, `includes/storage.php` |
| US-004 Notificaciones | ✅ | `ajax/notificaciones.php`, `includes/header.php` |
| US-005 Paneles | ✅ | `pages/dashboard_{admin,torre,gestor,reportante}.php`, `includes/dashboard_helpers.php` |
| US-006 Detalle | ✅ | `pages/detalle_novedad.php` |
| US-007 Tipos de operación | ✅ | `pages/admin_operaciones.php`, `includes/tipos_operacion.php` |
| US-008 Tipologías | ✅ | `pages/admin_tipologias.php`, `includes/tipologias.php` |
| US-009 Tipos de novedad | ✅ | `pages/admin_novedades.php` |
| US-010 Usuarios | ✅ | `pages/admin_usuarios.php`, `includes/usuarios_store.php` |
| US-011 Organizaciones | ✅ | `pages/admin_organizaciones.php`, `includes/organizaciones_store.php` |
| US-012 Exportar | ✅ (por visibilidad del usuario) | `export/exportar_excel.php`, `includes/visibilidad.php` |
| US-013 Adjuntos/correo | ❌ pendiente (G-1, G-2) | — |

---

## Fase 0: Fundamentos y Línea Base

Propósito: dejar listo el terreno (repo, entornos, decisiones) y congelar el comportamiento actual con pruebas.

### Decisiones y pruebas de concepto
- [ ] T-001: Registrar en ADR las decisiones D-001 (PHP), D-002 (cuentas propias) y D-003 (Azure Blob) [P]
  - Files: `docs/adr/0001-lenguaje.md`, `docs/adr/0002-identidad.md`, `docs/adr/0003-adjuntos.md`
  - Acceptance: tres ADR aprobados. La política de contraseñas, los límites de adjuntos y el correo **no** bloquean esta tarea: son parámetros que se fijan en la migración (T-024)
- [ ] T-002: **Prueba de concepto PHP → Azure SQL con Managed Identity** (decisión D-001); si falla, activar el plan B (API en .NET) [P]
  - Files: `tools/poc-sql/` (app mínima + guía)
  - Acceptance: una Web App en Azure ejecuta `SELECT` e `INSERT` parametrizados sin contraseña (ver `research.md` §3); si falla con PHP, se activa el plan B

### Repositorio y entornos
- [ ] T-003: Crear el monorepo con la estructura del plan (`infra/ db/ api/ web/ tools/ docs/`) [P]
  - Files: raíz del repo, `README.md`, `.gitignore`, `.editorconfig`
  - Acceptance: el repo compila/arranca vacío; la carpeta `docs/sdd/` contiene este SDD
- [ ] T-004: Definir la infraestructura en Bicep: dos Web Apps (Web Site y Web API) con slots *staging*, Azure SQL Database, Key Vault, Application Insights [Dep: T-003]
  - Files: `infra/main.bicep`, `infra/modules/*.bicep`, `infra/parameters.dev.json`
  - Acceptance: `az deployment` crea el entorno dev completo y repetible
- [ ] T-005: Configurar identidades administradas, App Settings y referencias a Key Vault [Dep: T-004]
  - Files: `infra/modules/identidad.bicep`, `infra/modules/appsettings.bicep`
  - Acceptance: ninguna cadena de conexión ni secreto aparece en el repositorio (Art. IV)
- [ ] T-006: Pipelines CI/CD (compilar, probar, desplegar a *staging*, *swap*) [Dep: T-003]
  - Files: `.github/workflows/*.yml` **o** `azure-pipelines.yml` (según plataforma)
  - Acceptance: un push ejecuta pruebas y despliega a *staging*

### Línea base de pruebas
- [ ] T-007: Pruebas E2E que congelan el comportamiento del v1 en los flujos críticos [P] [Story: US-001, US-002, US-003, US-005, US-009, US-010]
  - Files: `web/tests/e2e/*.spec.*`
  - Acceptance: login por permiso, registrar novedad con regla Terrestre/OTT, responder→replicar→resolver, filtros de panel, crear tipo de novedad con 2 tipologías, crear y editar usuario
- [ ] T-008: Inventario de datos actuales y respaldo de `storage/*.json` y `data/*.php` [P]
  - Files: `tools/migracion-json/inventario.md`, `backup/`
  - Acceptance: conteos documentados (26 usuarios, 1 047 organizaciones, 3 operaciones, 6 tipologías, 7 tipos de novedad, 21 novedades)

**Checkpoint Fase 0:** decisiones cerradas, entorno dev desplegable, pruebas de línea base en verde contra el v1.

---

## Fase 1: Datos (Azure SQL Database)

Propósito: esquema y datos migrados. Todas las historias del API dependen de esta fase.

- [ ] T-010: Migraciones del esquema: catálogos de actores/organizaciones, identidad, catálogos parametrizables [Dep: T-004]
  - Files: `db/migrations/001_catalogos.sql`, `002_identidad.sql`, `003_tipos_operacion_tipologias.sql`
  - Acceptance: aplican limpias en una base vacía; coinciden con `data-model.md`
- [ ] T-011: Migraciones del catálogo de tipos de novedad (tabla principal + 7 tablas de relación) [Dep: T-010]
  - Files: `db/migrations/004_tipo_novedad.sql`
  - Acceptance: restricciones y claves foráneas según `data-model.md`
- [ ] T-012: Migraciones de novedades, hilo, destinatarios, datos del evento, notificaciones y auditoría [Dep: T-011]
  - Files: `db/migrations/005_novedades.sql`, `006_notificaciones_auditoria.sql`
  - Acceptance: aplican limpias; índices recomendados creados
- [ ] T-013: Datos semilla de catálogos (tipos de operación, tipologías, tipos de actor, taxonomía) [P] [Dep: T-010]
  - Files: `db/seed/*.sql`
  - Acceptance: 3 tipos de operación, 6 tipologías y los 21 tipos de actor existentes
- [ ] T-014: Importador de `storage/*.json` a Azure SQL (usuarios, organizaciones, catálogos, tipos de novedad, novedades, hilos, notificaciones) [Dep: T-012, T-013]
  - Files: `tools/migracion-json/importar.*`
  - Acceptance: **no** importa `data/novedades_ejemplo.php`; convierte `tipologia` (texto) en lista; conserva claves estables (`usuario`, `clave`, `codigo`, `id` de novedad)
- [ ] T-015: Contraseñas: guardar `ClaveInicial` de la plantilla con hash y generar clave temporal cuando venga vacía [Dep: T-014]
  - Files: `tools/migracion-json/claves.*`
  - Acceptance: ninguna cuenta queda con clave en texto plano
- [ ] T-016: Validación post-migración (conteos, huérfanos, checksums) [Dep: T-014]
  - Files: `tools/migracion-json/validar.sql`
  - Acceptance: reporte con diferencias = 0 en conteos y 0 referencias huérfanas
- [ ] T-017: Importador de la plantilla de carga masiva: lee las 14 hojas, valida (referencias, duplicados, formatos, reglas de negocio) y carga en una transacción; informe por hoja y fila [Dep: T-012, T-013]
  - Files: `tools/carga-masiva/importar.*`, `tools/carga-masiva/validaciones.*`
  - Acceptance: un archivo con errores se rechaza completo y el informe indica hoja, fila y motivo; un archivo válido deja los conteos esperados (ver `data-model.md` §C)
- [ ] T-018: **Gate:** recibir la plantilla completa del PO, ejecutar el importador en preproducción y aprobar los datos **antes de las pruebas funcionales** [Dep: T-017]
  - Files: `docs/plantillas/entrega-v1.0.xlsx` (copia recibida), `docs/plantillas/informe-carga.md`
  - Acceptance: informe de carga sin errores aprobado por el PO; las pruebas funcionales no inician sin este gate
- [ ] T-019: Definir con el PO, al revisar la entrega, si el catálogo de Causas será editable en pantalla (hoy se carga solo con la plantilla) [P]
  - Files: `docs/adr/0004-causas.md`
  - Acceptance: decisión registrada; si es afirmativa, crear T-0xx de pantalla de Causas

**Checkpoint Fase 1:** una base de pruebas con los datos reales migrados y validados.

---

## Fase 2: Web API (por historia, en orden de prioridad)

> Cada historia: definir/ajustar el contrato → pruebas → implementación. Rutas del API en `api/src/`.

### US-001 y US-010 — Identidad y usuarios (Must) [Story: US-001, US-010]
- [ ] T-020: Esqueleto del API: estructura por dominios, `application/problem+json`, `/health`, `/health/ready`, Application Insights [Dep: T-005, T-012]
  - Files: `api/src/*`, `api/tests/*`
  - Acceptance: el API arranca en *staging* y `/health/ready` verifica Azure SQL
- [ ] T-021: Autenticación con **cuentas propias** (hash fuerte, límite de intentos de login) y revalidación de permisos/estado en cada petición [Dep: T-020, T-015]
  - Files: `api/src/Identidad/*`
  - Acceptance: usuario inactivado pierde acceso en la siguiente llamada (SC-006); límite de intentos de login
- [ ] T-022: Autorización RBAC (administrador, torre con `PuedeParametrizar`, gestor, reportante) [Dep: T-021]
  - Files: `api/src/Identidad/Politicas.*`
  - Acceptance: pruebas por cada combinación permiso × endpoint
- [ ] T-023: Endpoints de usuarios con todas las reglas de US-010 (sin renombrar con historial, último admin, no auto-inactivar, restricciones de quien no es admin) [Dep: T-022]
  - Files: `api/src/Usuarios/*`, `api/tests/Usuarios/*`
  - Acceptance: cumple los criterios de US-010 y el contrato `/usuarios*`
- [ ] T-024: **Módulo de configuración**: tabla `Configuracion`, lectura en el API y `GET /configuracion/politicas`; política de contraseñas, límites de adjuntos y estado del correo se leen de aquí [Dep: T-020]
  - Files: `api/src/Configuracion/*`, `db/migrations/007_configuracion.sql`, `web/includes/politicas.*`
  - Acceptance: ningún valor de contraseña, adjunto o correo está fijo en el código; el Web Site toma los límites del API; cambiar un valor no requiere desplegar código. Los valores definitivos se cargan durante la migración con los requerimientos del PO

### US-007, US-008, US-011 — Catálogos y organizaciones (Must) [Story: US-007, US-008, US-011]
- [ ] T-030: Tipos de operación: CRUD, estado, eliminación protegida por uso, mínimo un activo [P] [Dep: T-022]
  - Files: `api/src/Catalogos/TiposOperacion/*`
  - Acceptance: reglas de US-007
- [ ] T-031: Tipologías: CRUD, estado, sigla inmutable si hay uso, mínimo una activa [P] [Dep: T-022]
  - Files: `api/src/Catalogos/Tipologias/*`
  - Acceptance: reglas de US-008
- [ ] T-032: Organizaciones: CRUD, estado, búsqueda/paginación, renombrado en cascada a usuarios [P] [Dep: T-022]
  - Files: `api/src/Organizaciones/*`
  - Acceptance: reglas de US-011

### US-009 — Tipos de novedad (Must) [Story: US-009]
- [ ] T-040: Endpoints de tipos de novedad con operaciones y **tipologías múltiples**, actores que reportan/responden/etiquetan, causales, campos por grupo, información necesaria y campos personalizados [Dep: T-030, T-031]
  - Files: `api/src/TiposNovedad/*`
  - Acceptance: guardar y recuperar un tipo con 2 tipologías y 3 operaciones sin pérdida
- [ ] T-041: Consulta para el formulario de registro (`paraRegistrar`): filtra por tipología/operación y por actor del reportante; admin y torre ven todos [Dep: T-040]
  - Files: `api/src/TiposNovedad/ParaRegistrar.*`
  - Acceptance: reportante EAI ve OPR → 2 tipos; admin GHT ve los mismos (regla acordada)

### US-002, US-003, US-006 — Novedades (Must) [Story: US-002, US-003, US-006]
- [ ] T-050: Registrar novedad (transacción única) con validaciones de tipo/tipología/operación activos y permiso de reportar [Dep: T-041]
  - Files: `api/src/Novedades/Registrar.*`
  - Acceptance: genera `NOV-AAAAMMDD-NNN` sin colisiones concurrentes; estado inicial Abierta/Reporte
- [ ] T-051: Regla **Terrestre/OTT** en el servidor (actor tomado del registro; solo cuenta el usuario etiquetado, no quien reporta) [Dep: T-050]
  - Files: `api/src/Novedades/ReglaTerrestre.*`, `api/tests/Novedades/ReglaTerrestreTests.*`
  - Acceptance: con destinatario OTT y tipo terrestre, faltar un campo parametrizado devuelve 400 con la lista de faltantes; con destinatario no OTT no se exige (SC-004)
- [ ] T-052: Información necesaria y campos personalizados obligatorios [Dep: T-050]
  - Files: `api/src/Novedades/InfoNecesaria.*`
  - Acceptance: 400 con los nombres faltantes
- [ ] T-053: Destinatarios: principal, etiquetados (actores etiquetables) y correos externos [Dep: T-050]
  - Files: `api/src/Novedades/Destinatarios.*`
  - Acceptance: un usuario etiquetado de actor no permitido es rechazado
- [ ] T-054: Hilo: responder, replicar y resolver con las reglas de permiso de US-003; abierta→en_gestión al responder [Dep: T-050]
  - Files: `api/src/Novedades/Hilo.*`
  - Acceptance: cada caso queda con autor y fecha (SC-002)
- [ ] T-055: Guardar todas las tipologías de la novedad (la elegida como principal) [Dep: T-050]
  - Files: `api/src/Novedades/Tipologias.*`
  - Acceptance: detalle muestra `SEG · OPR` para una novedad de un tipo con ambas
- [ ] T-056: Detalle de novedad con permisos de acceso [Dep: T-054]
  - Files: `api/src/Novedades/Detalle.*`
  - Acceptance: 403 si el usuario no participa y no es admin/torre

### US-004, US-005 — Notificaciones y paneles (Must/Should) [Story: US-004, US-005]
- [ ] T-060: Notificaciones: generación en registrar/responder/replicar/resolver; leer, alternar, eliminar, limpiar [Dep: T-054]
  - Files: `api/src/Notificaciones/*`
  - Acceptance: reglas de US-004; avisos a admin/torre solo si el tipo lo indica
- [ ] T-061: Listado con visibilidad por perfil, estados (`activas` por defecto y selección múltiple), filtros, orden, paginación y contadores [Dep: T-056]
  - Files: `api/src/Novedades/Listado.*`
  - Acceptance: p95 < 500 ms con 10 000 novedades (Art. VIII); contadores coinciden con la lista

### US-012 — Exportación (Should) [Story: US-012]
- [ ] T-065: Exportar a Excel/CSV con los mismos filtros del listado (incluye "activas" y estados múltiples), **limitado a lo que el usuario puede ver**: reportante y gestor solo lo suyo; administrador y torre todo [Dep: T-061]
  - Files: `api/src/Novedades/Exportacion.*`
  - Acceptance: el archivo contiene exactamente las filas visibles con esos filtros para ese perfil (US-012)

### US-013 — Adjuntos y correo (Should, condicionado) [Story: US-013]
- [ ] T-070: Adjuntos en **Azure Blob Storage** (contenedor privado, acceso por Managed Identity, URL SAS de vida corta): subida con tipos y tamaño permitidos, descarga solo para quienes ven la novedad, y obligatoriedad cuando el tipo exige adjunto [Dep: T-050]
  - Files: `api/src/Adjuntos/*`, `infra/modules/storage.bicep`
  - Acceptance: criterios de US-013 con los límites tomados de configuración (T-024); valores de referencia: JPG/PNG/PDF/Excel/Word, 10 MB por archivo, 5 por mensaje
- [ ] T-071: *(Diferida — no iniciar)* Envío de copia por correo y avisos a correos externos mediante una API de correo que se conectará más adelante (CL-003) [Dep: T-050]
  - Files: `api/src/Correo/*`
  - Acceptance: se define cuando el PO confirme la API de correo; mientras tanto no hay entrega comprometida
- [ ] T-072: **Interfaz de notificación por correo** con implementación nula (no envía nada) y parámetro `correo.habilitado = false` [Dep: T-024]
  - Files: `api/src/Correo/NotificadorCorreo.*`, `api/src/Correo/NotificadorNulo.*`
  - Acceptance: el sistema funciona completo con el correo deshabilitado; conectar una API más adelante es sustituir la implementación y activar el parámetro, sin cambios en el resto del sistema

### Auditoría (FR-048)
- [ ] T-075: Registrar en `Auditoria` los cambios de usuarios, permisos, organizaciones y catálogos [Dep: T-023, T-032, T-040]
  - Files: `api/src/Auditoria/*`
  - Acceptance: cada acción de Parametrización deja quién, qué y cuándo

**Checkpoint Fase 2:** contrato cumplido, pruebas unitarias ≥ 70 % y pruebas de integración contra Azure SQL de pruebas en verde.

---

## Fase 3: Web Site

Propósito: la interfaz actual consume el API y deja de leer archivos.

- [ ] T-080: Capa de acceso al API y manejo de sesión/cookie segura en el Web Site [Dep: T-021]
  - Files: `web/includes/api_cliente.*`, `web/includes/auth.*`
  - Acceptance: el Web Site no tiene cadena de conexión a SQL
- [ ] T-081: Migrar login, cabecera y menú; navegación de Parametrización (5 pestañas) [Dep: T-080]
  - Files: `web/login.*`, `web/includes/header.*`, `web/includes/parametrizacion.*`
  - Acceptance: igual comportamiento y accesibilidad que el v1
- [ ] T-082: Migrar Tipos de Operación, Tipologías, Fincas y Organizaciones [P] [Dep: T-081, T-030, T-031, T-032]
  - Files: `web/pages/admin_{operaciones,tipologias,organizaciones}.*`
  - Acceptance: criterios de US-007, 008, 011
- [ ] T-083: Migrar Usuarios [P] [Dep: T-081, T-023]
  - Files: `web/pages/admin_usuarios.*`
  - Acceptance: criterios de US-010
- [ ] T-084: Migrar Tipos de Novedad (asistente de 4 pasos) [Dep: T-081, T-040]
  - Files: `web/pages/admin_novedades.*`
  - Acceptance: criterios de US-009 incluida la selección múltiple de tipologías
- [ ] T-085: Migrar Registrar Novedad (cascada, Dirigido a, etiquetar otro actor, regla Terrestre) [Dep: T-081, T-053, T-051]
  - Files: `web/pages/nueva_novedad.*`, `web/js/formulario_dinamico.js`
  - Acceptance: criterios de US-002; la validación del servidor prevalece
- [ ] T-086: Migrar paneles, detalle y notificaciones; la torre de control usa **su propio panel** con las opciones que le otorgó el administrador; botón Exportar en todos los paneles [Dep: T-081, T-061, T-054, T-060, T-065]
  - Files: `web/pages/dashboard_*.*`, `web/pages/detalle_novedad.*`, `web/includes/header.*`
  - Acceptance: criterios de US-004, 005, 006, 012 y FR-034
- [ ] T-087: Ejecutar la batería E2E de la Fase 0 contra la nueva arquitectura [Dep: T-082, T-083, T-084, T-085, T-086]
  - Files: `web/tests/e2e/*`
  - Acceptance: **el 100 % de la línea base en verde**

**Checkpoint Fase 3:** equivalencia funcional con el v1 demostrada por la misma batería de pruebas.

---

## Fase 4: Endurecimiento y Salida a Producción

- [ ] T-090: Seguridad: CSRF/anti-falsificación global, cabeceras (HSTS, CSP, nosniff), cookies seguras, retiro de la contraseña demo [P]
  - Files: `web/**`, `api/src/Seguridad/*`
  - Acceptance: revisión de seguridad sin hallazgos altos (Art. VII)
- [ ] T-091: Pruebas de rendimiento contra los umbrales del Art. VIII, incluida la meta de **más de 50 usuarios simultáneos** (se prueba con 75) [P] [Dep: T-087]
  - Files: `tools/perf/*`
  - Acceptance: p95 de dashboards < 2 s, lecturas < 500 ms, escrituras < 800 ms con 75 usuarios simultáneos
- [ ] T-092: Alertas y tableros en Application Insights [P] [Dep: T-087]
  - Files: `infra/modules/alertas.bicep`
  - Acceptance: alertas de disponibilidad, 5xx y latencia activas
- [ ] T-093: Accesibilidad y móvil: revisión WCAG 2.2 AA y 375 px sobre las pantallas migradas [P] [Dep: T-087]
  - Files: informe en `docs/`
  - Acceptance: sin barreras AA ni desplazamiento horizontal (SC-007)
- [ ] T-094: Ensayo de migración completa en un entorno de preproducción (importar, validar, probar) [Dep: T-016, T-087]
  - Files: `tools/migracion-json/runbook.md`
  - Acceptance: ensayo cronometrado y repetible; plan de reversión documentado
- [ ] T-095: *Cutover* a producción: congelar el v1, importar delta, *swap* de *slots*, verificación de humo [Dep: T-094, T-090, T-091]
  - Files: `docs/runbook-cutover.md`
  - Acceptance: producción operando en Azure; el v1 queda en solo lectura durante el periodo acordado
- [ ] T-096: Documentación final: README, OpenAPI publicado, ADR y actualización del SDD (Change Management) [P]
  - Files: `README.md`, `docs/**`
  - Acceptance: cualquier persona nueva levanta el entorno local siguiendo el README

---

## Resumen de Dependencias

```
Fase 0 (decisiones + entorno + pruebas base)
   └─► Fase 1 (esquema + importación)
          └─► Fase 2 (API): Identidad ─► Catálogos ─► Tipos de novedad ─► Novedades ─► Notificaciones/Paneles ─► Exportación
                                                                                    └─► Adjuntos en Blob (correo diferido)
                 └─► Fase 3 (Web Site) ─► E2E de la línea base en verde
                        └─► Fase 4 (endurecimiento) ─► Cutover
```

## Tabla de Trazabilidad (historia → tareas)

| Historia | Tareas |
|----------|--------|
| US-001 | T-020, T-021, T-022, T-081 |
| US-002 | T-041, T-050, T-051, T-052, T-053, T-055, T-085 |
| US-003 | T-054, T-056 |
| US-004 | T-060, T-086 |
| US-005 | T-061, T-086 |
| US-006 | T-056, T-086 |
| US-007 | T-030, T-082 |
| US-008 | T-031, T-082 |
| US-009 | T-040, T-041, T-084 |
| US-010 | T-023, T-083 |
| US-011 | T-032, T-082 |
| US-012 | T-065, T-086 |
| US-013 | T-070, T-071 |
| Transversal | T-001…T-019, T-024, T-072, T-075, T-090…T-096 |
