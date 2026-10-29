<!-- SDD Artifact | Version: 1.3 | Phase: Clarify | Updated: 2026-10-21 -->
<!-- Project: NovedApp | Feature: 001-novedapp -->

# Lista de Calidad de la Especificación

| # | Criterio | Resultado | Observación |
|---|----------|-----------|-------------|
| 1 | Sin detalles de implementación en `spec.md` | ✅ | La spec habla de comportamiento; la tecnología vive en `plan.md`. Las referencias a "PHP/JSON" aparecen solo en la sección de deuda del código actual |
| 2 | Todas las secciones obligatorias completas | ✅ | Resumen, historias, requisitos, entidades, criterios de éxito, supuestos, alcance, bitácora |
| 3 | Sin marcadores `[NEEDS CLARIFICATION]` sin resolver | ✅ | **0 abiertos**: CL-001, CL-002 y CL-003 se resolvieron el 2026-10-21 |
| 4 | Criterios de éxito medibles y agnósticos de tecnología | ✅ | Las cifras con ⚠ son propuestas a validar |
| 5 | Escenarios de aceptación definidos | ✅ | Cada historia tiene criterios y prueba independiente |
| 6 | Casos límite identificados | ✅ | Inactivación en sesión, último administrador, usuario con historial, tipos en uso, destinatario OTT con actor falsificado |
| 7 | Alcance acotado | ✅ | Dentro / fuera / futuro |
| 8 | Dependencias y supuestos documentados | ✅ | |
| 9 | Trazabilidad requisito → historia → tarea | ✅ | Tabla en `tasks.md` |
| 10 | Cumplimiento de la Constitución en el plan | ⚠️ | Art. III (pruebas) y VII (seguridad) dependen de la Fase 0 y de D-002 |

## Decisiones cerradas por el PO (2026-10-21)

- Exportación: cualquier usuario, solo lo que ve; administrador y torre exportan todo.
- La torre de control usa su propio panel con los permisos que le otorgue el administrador.
- Datos maestros y catálogo heredado de 471 tipos: se entregan en la plantilla de carga masiva antes de las pruebas funcionales.
- Etiquetar otro actor: listado completo de actores externos a la novedad cuando el tipo no define etiquetables.
- Metas de éxito: todas las novedades operativas por NovedApp, facilidad de uso, más de 50 usuarios simultáneos.
- Causas: se cargan con la plantilla; si serán editables en pantalla se decide más adelante.

## Decisiones cerradas el 2026-10-21 (segunda ronda)

- **CL-001** Identidad: solo cuentas propias de NovedApp.
- **CL-002** Adjuntos: sí, en Azure Blob Storage.
- **CL-003** Correo: se incorpora más adelante, cuando se conecte una API para esa función.
- **D-001** Lenguaje: PHP en el Web API y en el Web Site, sujeto a la prueba de concepto T-002 (plan B: API en .NET).

## Parámetros que se fijan en la migración (no son decisiones abiertas)

Por decisión del PO (2026-10-22): **política de contraseñas**, **límites de adjuntos** y **envío de correo** se definen en el momento de la migración según sus requerimientos; el sistema los trata como configuración (ver plan §Parámetros y tarea T-024).

## Por resolver al construir

1. Resultado de la **prueba de concepto T-002** (acceso de PHP a Azure SQL con Managed Identity; si falla, el API pasa a .NET).

## Por verificar (investigación)

- Versiones de PHP/.NET disponibles hoy en App Service Linux (`az webapp list-runtimes --os linux`).
- Disponibilidad de `pdo_sqlsrv` y ODBC en App Service Linux para PHP.
- Niveles de plan, costos y retención de copias de Azure SQL.
