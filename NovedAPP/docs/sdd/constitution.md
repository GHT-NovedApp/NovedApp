<!-- SDD Artifact | Version: 1.3 | Phase: Constitution | Updated: 2026-10-21 -->
<!-- Project: NovedApp -->
<!-- Change Log:
  v1.0 - Reconstrucción desde el código existente (v1 PHP/JSON) + stack objetivo indicado por el PO:
         Azure App Service (Web Site, Web API) y Azure SQL Database.
  v1.1 - Meta de concurrencia (> 50 usuarios) y política de contraseñas pendiente.
  v1.2 - Decisiones del PO: cuentas propias (CL-001), adjuntos en Azure Blob (CL-002), correo diferido (CL-003), PHP en Web Site y Web API (D-001).
  v1.3 - Política de contraseñas, límites de adjuntos e integración de correo pasan a ser parámetros de configuración que se fijan en la migración (Art. II y IV).
-->

# Constitución del Proyecto: NovedApp

## Preámbulo

NovedApp es el sistema de **gestión de novedades operativas y eventos de riesgo** de GHT para la cadena
de exportación/importación (fincas, CEDI, agencias de carga, transportadores, torre de control).
Toda especificación, plan, tarea e implementación DEBE cumplir estos artículos. Las excepciones se
documentan en la sección *Complexity Tracking* del plan.

> **Nota de origen.** Los artículos marcados con ✔ ya se cumplen en el código actual (v1). Los marcados
> con ➜ son metas del stack objetivo (Azure) y se materializan en `plan.md` y `tasks.md`.
> Los umbrales numéricos marcados con ⚠ son **propuestas** pendientes de confirmación por el PO.

---

## Artículo I: Modularidad

Cada capacidad nace como módulo con fronteras claras; ninguna pantalla accede a archivos o tablas de otro
dominio sin pasar por su módulo.

- ✔ v1 ya separa persistencia por dominio: `usuarios_store`, `organizaciones_store`, `tipos_operacion`,
  `tipologias`, `storage` (novedades/notificaciones), `auth`, `parametrizacion`.
- ➜ En Azure: **Web Site** (presentación) y **Web API** (reglas de negocio y acceso a datos) son
  aplicaciones separadas. El Web Site NO consulta Azure SQL directamente.
- ➜ El Web API se organiza por dominio: Identidad, Catálogos, Novedades, Notificaciones, Reportes.

## Artículo II: Contratos primero

- ➜ Todo endpoint se define en `contracts/api-spec.json` (OpenAPI 3.0) **antes** de implementarse.
- ➜ Los cambios que rompen compatibilidad se versionan (`/api/v1`, `/api/v2`).
- ➜ Las integraciones externas (envío de correo, almacenamiento de adjuntos) se consumen detrás de una **interfaz propia**; así, conectar una API más adelante es cambiar una implementación, no el sistema.
- ✔ Los identificadores estables que guardan las novedades (`clave` de tipo de operación, `codigo` de tipología,
  nombre de usuario) son parte del contrato de datos: no se renombran si hay historial.

## Artículo III: Pruebas

- ➜ Pruebas unitarias de reglas de negocio ⚠ cobertura mínima **70 %** en el Web API.
- ➜ Pruebas de integración contra una Azure SQL de pruebas (o contenedor SQL Server) para cada repositorio.
- ➜ Pruebas E2E de los flujos críticos: login, registrar novedad, responder/replicar/resolver, parametrizar.
- ➜ Ningún PR se fusiona con pruebas en rojo ni con regresiones de reglas de permisos.
- Deuda actual: la v1 no tiene pruebas automatizadas; se verificó manualmente (ver `tasks.md`, Fase 0).

## Artículo IV: Configuración y secretos

- ➜ Nada de cadenas de conexión, claves o contraseñas en el repositorio. Configuración en **App Settings**
  de cada App Service; secretos en **Azure Key Vault** (referencias desde App Settings).
- ➜ Acceso a Azure SQL con **Managed Identity** (Microsoft Entra ID), sin usuario/contraseña de base de datos.
- ➜ **Parámetros de negocio configurables** (decisión del PO, 2026-10-22): la **política de contraseñas** (largo, complejidad, caducidad, intentos y bloqueo, recuperación), los **límites de adjuntos** (tipos, tamaño, cantidad, retención) y la **integración de correo** (proveedor, remitente, habilitada o no) no se escriben en el código. Viven en configuración, se leen desde el API y se fijan en el momento de la migración según sus requerimientos.
- ✔ En v1 la configuración está embebida en archivos de `data/` (semillas) → se elimina en la migración.

## Artículo V: Manejo de errores

- ➜ El Web API responde errores con un formato único (`application/problem+json`, RFC 9457) con código, mensaje
  accionable y `traceId`.
- ✔ Mensajes de usuario en español, que nombran el problema y la acción de recuperación (patrón ya aplicado en
  Parametrización: "Ya existe una tipología con la sigla SEG").
- Los errores internos NO exponen trazas, rutas ni consultas.

## Artículo VI: Observabilidad

- ➜ **Application Insights** en Web Site y Web API: logs estructurados, trazas distribuidas (correlación por `traceId`),
  métricas de disponibilidad y tiempos.
- ➜ Endpoint `/health` (liveness) y `/health/ready` (incluye comprobación de Azure SQL).
- ➜ Alertas: disponibilidad < 99 %, errores 5xx > 2 % en 5 min, latencia p95 > 1 s ⚠.
- ➜ **Auditoría de negocio**: quién creó/modificó usuarios, permisos, catálogos y estados de novedades
  (hoy no existe).

## Artículo VII: Seguridad

- ➜ **Autenticación:** **cuentas propias de NovedApp** guardadas en Azure SQL (decisión del PO, CL-001): usuario y contraseña con **hash fuerte** (Argon2id o PBKDF2), nunca en texto plano. Sin SSO por ahora.
  Cada contraseña se guarda solo como hash; las claves iniciales de la carga masiva se cifran al importarlas.
  La **política de contraseñas** (largo, complejidad, caducidad, intentos, bloqueo, recuperación) es un **parámetro de configuración** que se fija en la migración (ver Art. IV).
- ✔ **Autorización (RBAC)** con cuatro permisos del sistema: `administrador`, `torre_control`
  (+ bandera `puede_parametrizar` otorgada por un administrador), `gestor`, `reportante`.
  La autorización se valida **en el servidor** en cada petición (ya implementado en `verificar_sesion`).
- ➜ Los permisos se aplican en el Web API; el Web Site solo oculta opciones por usabilidad.
- ✔ Protección CSRF en formularios de parametrización; ➜ se extiende a **todos** los formularios y a la API
  (tokens anti-falsificación o cookies `SameSite=Strict` + cabecera personalizada).
- ➜ HTTPS obligatorio (HSTS), cookies `Secure; HttpOnly; SameSite`, cabeceras CSP y `X-Content-Type-Options`.
- ➜ Limitación de intentos de login y bloqueo temporal.
- ➜ Retirar la contraseña de demostración visible en el login en cualquier entorno distinto de desarrollo.
- ➜ Escaneo de dependencias (Dependabot / `composer audit` / `dotnet list package --vulnerable`) en cada PR.
- Datos personales (nombre, usuario, organización): cifrado en tránsito (TLS) y en reposo (TDE de Azure SQL).

## Artículo VIII: Rendimiento

Metas ⚠ (derivadas de las pantallas actuales, ajustables con el PO):
- Carga de dashboards: **p95 < 2 s** con 10 000 novedades.
- Respuesta de API de lectura: **p95 < 500 ms**; de escritura: **p95 < 800 ms**.
- Listados paginados en servidor (hoy el dashboard pagina en memoria sobre el archivo completo).
- Concurrencia objetivo (meta del PO): **más de 50 usuarios simultáneos** sin degradación; las pruebas de carga se ejecutan con 75 ⚠ como margen.
- Consultas con índices definidos en `data-model.md`; prohibido leer tablas completas para filtrar en la aplicación.

## Artículo IX: Documentación

- OpenAPI vigente en `contracts/api-spec.json`; el SDD (`constitution`, `spec`, `plan`, `data-model`, `tasks`)
  se actualiza con cada cambio de alcance (Change Management del SDD).
- Decisiones relevantes de arquitectura → ADR en `docs/adr/` (p. ej. elección de lenguaje del API).
- README del repo: cómo ejecutar local, variables requeridas, cómo desplegar.

## Artículo X: Experiencia de usuario y accesibilidad

(Específico de NovedApp; sale del trabajo de revisión de diseño ya aplicado.)

- Interfaz **en español**, herramienta interna de uso diario (modo operar): prima la escaneabilidad y la consistencia.
- Identidad GHT: verde oscuro (`#20431D`) y blanco; tokens de color/tipografía centralizados en `css/main.css`.
- **Accesibilidad WCAG 2.2 AA**: contraste ≥ 4.5:1, foco visible en todo control, objetivos táctiles ≥ 36–44 px,
  tablas con semántica, diálogos nativos accesibles.
- **Responsive** desde 375 px sin desplazamiento horizontal.
- Acciones destructivas siempre con confirmación que nombra el objeto y la consecuencia.
- No usar emojis como iconos; iconografía SVG consistente.

---

## Gobierno del Stack Tecnológico

### Stack aprobado (objetivo)

| Capa | Tecnología | Versión | ¿No negociable? |
|------|-----------|---------|-----------------|
| Cómputo — Web Site | **Azure App Service** (Web App) | Plan Premium v3 o Standard ⚠ | **Sí** (definido por el PO) |
| Cómputo — Web API | **Azure App Service** (Web App) | Ídem | **Sí** (definido por el PO) |
| Repositorio de datos | **Azure SQL Database** | Compatibilidad SQL actual de Azure | **Sí** (definido por el PO) |
| Lenguaje/framework del Web Site y del Web API | **PHP** en ambos (decisión del PO, D-001), sujeto a la prueba de concepto T-002; plan B: API en .NET | 8.2 ⚠ | Sí (decisión del PO) |
| Secretos | Azure Key Vault | — | No |
| Observabilidad | Application Insights / Log Analytics | — | No |
| Identidad | **Cuentas propias** de NovedApp en Azure SQL (CL-001) | — | Sí (decisión del PO) |
| Infraestructura como código | Bicep ⚠ | — | No |
| CI/CD | [pendiente — GitHub Actions o Azure Pipelines] | — | No |

### Stack actual (v1, a retirar)

PHP 8.x sobre Apache (XAMPP), persistencia en archivos JSON (`storage/*.json`) y arrays PHP (`data/*.php`),
sesión PHP nativa, CSS/JS propios sin framework.

### Proceso de cambio de stack

Un cambio en el stack aprobado requiere: (1) ADR, (2) análisis de impacto sobre las especificaciones,
(3) enmienda de esta constitución si afecta elementos no negociables.

---

## Enmiendas

| # | Fecha | Descripción | Justificación |
|---|-------|-------------|---------------|
| 1 | 2026-10-21 | Constitución inicial reconstruida desde el código v1 y el stack Azure indicado por el PO | Establecer el marco antes de planear la migración |
| 2 | 2026-10-21 | Concurrencia objetivo: más de 50 usuarios simultáneos; política de contraseñas marcada como pendiente | Metas de éxito del PO (SC-008 a SC-010) |
| 3 | 2026-10-21 | Identidad con cuentas propias; adjuntos en Azure Blob; PHP en Web Site y Web API; correo diferido | Respuestas del PO a CL-001, CL-002, CL-003 y D-001 |
| 4 | 2026-10-22 | Política de contraseñas, límites de adjuntos e integración de correo = parámetros de configuración fijados en la migración; integraciones detrás de interfaces | Compatibilidad sin cambios grandes con APIs o herramientas futuras |
