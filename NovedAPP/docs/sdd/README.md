# SDD de NovedApp

Paquete de **Spec-Driven Development** reconstruido leyendo el proyecto (v1: PHP + archivos JSON) y alineado con el
stack objetivo: **Azure App Service (Web Site y Web API, en PHP) + Azure SQL Database + Azure Blob Storage (adjuntos)**.

**Versión 1.3 — 2026-10-22.** Decisiones del PO incorporadas: exportación por visibilidad, panel propio de la torre, carga masiva por plantilla,
listado completo de actores al etiquetar, metas de éxito, catálogo de causas, **cuentas propias**, **adjuntos en Azure Blob**,
**envío de correo diferido** y **PHP en Web Site y Web API**. No se encontró un SDD previo dentro del proyecto.

| Artefacto | Qué responde |
|-----------|--------------|
| [`constitution.md`](constitution.md) | Principios no negociables y gobierno del stack |
| [`specs/001-novedapp/spec.md`](specs/001-novedapp/spec.md) | **Qué** hace el sistema y por qué (historias, requisitos, brechas, bitácora de decisiones) |
| [`specs/001-novedapp/plan.md`](specs/001-novedapp/plan.md) | **Cómo** se migra a Azure (arquitectura, fases, riesgos, estado de las decisiones) |
| [`specs/001-novedapp/research.md`](specs/001-novedapp/research.md) | Investigación del stack (con lo no confirmado marcado) |
| [`specs/001-novedapp/data-model.md`](specs/001-novedapp/data-model.md) | Datos actuales (JSON), esquema objetivo en Azure SQL (T-SQL) y mapeo de la plantilla de carga masiva |
| [`specs/001-novedapp/contracts/api-spec.json`](specs/001-novedapp/contracts/api-spec.json) | Contrato OpenAPI 3.0 del Web API (34 rutas) |
| [`specs/001-novedapp/tasks.md`](specs/001-novedapp/tasks.md) | Línea base + 55 tareas por fase con trazabilidad |
| [`specs/001-novedapp/checklists/requirements.md`](specs/001-novedapp/checklists/requirements.md) | Lista de calidad y pendientes |
| [`../plantillas/Plantilla_Carga_Masiva_NovedApp_v1.1.xlsx`](../plantillas/Plantilla_Carga_Masiva_NovedApp_v1.1.xlsx) | **Plantilla de carga masiva** para entregar antes de las pruebas funcionales |

## Parámetros que se fijan en la migración
Política de contraseñas, límites de adjuntos e integración de correo son **parámetros de configuración** que se definen al migrar, según sus requerimientos (decisión del PO, 2026-10-22). El diseño los lee de configuración y deja el correo detrás de una interfaz, para que no haya cambios grandes al conectar APIs luego.

## Por resolver al construir
- **Prueba de concepto T-002**: acceso de PHP a Azure SQL con Managed Identity (si falla, el API pasa a .NET).
