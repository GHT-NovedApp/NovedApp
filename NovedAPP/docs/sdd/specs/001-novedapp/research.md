<!-- SDD Artifact | Version: 1.0 | Phase: Plan | Updated: 2026-10-21 -->
<!-- Project: NovedApp | Feature: 001-novedapp -->
<!-- Change Log:
  v1.0 - Investigación del stack objetivo. Las afirmaciones no confirmadas con documentación oficial se marcan "POR VERIFICAR".
-->

# Investigación del Stack: Azure App Service + Azure SQL Database

> **Alcance de esta investigación:** solo se pudo hacer una búsqueda web limitada; ninguna cifra de versiones o de
> precios se da por confirmada. Todo lo marcado **POR VERIFICAR** debe validarse con la documentación oficial de
> Microsoft o con el comando indicado antes de fijar la decisión (Fase 0 del plan).

## 1. Azure App Service (Web Site y Web API)

| Tema | Hallazgo | Estado |
|------|----------|--------|
| Dos aplicaciones | Web Site y Web API se despliegan como dos *Web Apps* (pueden compartir un App Service Plan en dev/test) | Decisión del plan |
| Slots de despliegue | Los planes Standard o superiores ofrecen slots (*staging*) para despliegue sin interrupción | POR VERIFICAR el nivel mínimo vigente |
| Runtimes disponibles en Linux | Comando oficial para listarlos: `az webapp list-runtimes --os linux` | Ejecutar en Fase 0 |
| ASP.NET Core | Se configura con `DOTNETCORE\|8.0` (ejemplo de la documentación). La disponibilidad de versiones más nuevas se confirma con el comando anterior | POR VERIFICAR para versiones > 8 |
| PHP | Es un runtime soportado de App Service; **qué versiones exactas (8.2/8.3/8.4) están disponibles hoy no se pudo confirmar** | POR VERIFICAR con `az webapp list-runtimes --os linux` |
| Salud | App Service permite configurar una ruta de *health check* | Usar `/health` |

## 2. Azure SQL Database

| Tema | Hallazgo | Estado |
|------|----------|--------|
| Autenticación sin contraseña | La app usa **Managed Identity**: se crea un usuario contenido en la base `CREATE USER [<nombre-app>] FROM EXTERNAL PROVIDER` y se le asignan roles (p. ej. `db_datareader`, `db_datawriter`). Requiere un administrador Entra configurado en el servidor SQL | Documentado por Microsoft y guías de la comunidad |
| Slots | Para usar identidad en un slot, el usuario de base de datos lleva el nombre `<app>/slots/<slot>` | Documentado en guías |
| Firewall / red | Para producción se recomienda acceso privado (VNet integration + Private Endpoint) y cerrar el acceso público | Recomendación; POR VERIFICAR costo |
| Respaldo / recuperación | Azure SQL ofrece copias automáticas y restauración a un punto en el tiempo; ajustar retención según política | POR VERIFICAR retención por defecto |

## 3. Acceso a Azure SQL desde PHP (si D-001 = PHP)

| Tema | Hallazgo | Estado |
|------|----------|--------|
| Driver | Microsoft Drivers for PHP for SQL Server (`sqlsrv` / `pdo_sqlsrv`) sobre Microsoft ODBC Driver | Estándar |
| Managed Identity | El driver acepta `Authentication = ActiveDirectoryMsi` y, en ese modo, **no** se usan `UID`/`PWD`. Requiere ODBC Driver ≥ 17.3.1.1 | Documentado por Microsoft |
| Disponibilidad en App Service Linux | Una guía de terceros indica que las imágenes de PHP para App Service Linux (8.1+) traen ODBC 17 y 18, pero **no se confirmó** que `pdo_sqlsrv` venga habilitado ni para qué versión de PHP | **POR VERIFICAR (prueba de concepto en Fase 0)** |
| Plan B | Si el driver no está disponible o es frágil, usar el API en ASP.NET Core (Microsoft.Data.SqlClient con `Authentication=Active Directory Default`) y dejar el Web Site en PHP consumiendo el API (opción B de D-001) | Criterio de decisión de D-001 |

**Prueba de concepto requerida (T-002):** una Web App PHP mínima que (1) lea la identidad administrada, (2) abra una conexión
a una base de pruebas con `ActiveDirectoryMsi`, (3) ejecute un `SELECT` y un `INSERT` parametrizados. Si falla, se activa el plan B.

## 4. Identidad de usuarios (D-002 — decidido: cuentas propias)

> **Decisión del PO (2026-10-21):** solo cuentas propias de NovedApp en Azure SQL. Las demás opciones se conservan como referencia. Hace falta definir la política de contraseñas.

| Opción | Cuándo conviene | Consideraciones |
|--------|-----------------|-----------------|
| **Microsoft Entra ID (SSO)** | Todos los usuarios tienen cuenta corporativa de GHT | Elimina contraseñas locales; los roles de NovedApp siguen en SQL (`Usuario.Permiso`) |
| **Cuentas locales en Azure SQL** | Usuarios externos (fincas, transportadores) sin cuenta corporativa | Hash fuerte (Argon2id/PBKDF2), recuperación de clave, bloqueo por intentos |
| **Mixta** (Entra + externos) | Mezcla de internos y externos | Mayor alcance; considerar *Microsoft Entra External ID* — POR VERIFICAR requisitos y costo |

## 5. Observabilidad y operación

- **Application Insights** (workspace-based) en ambas apps; correlación por `traceId` entre Web Site y Web API.
- **Alertas** sugeridas en el plan (disponibilidad, 5xx, latencia p95); umbrales por validar con el PO.
- **Key Vault** con referencias desde App Settings; Managed Identity de cada app con permiso de lectura de secretos.

## 6. Mejores prácticas para esta combinación

1. Una identidad administrada **por aplicación**; el Web Site no recibe permisos sobre SQL.
2. Migraciones de esquema versionadas y ejecutadas desde CI/CD, no a mano.
3. Despliegue a *staging*, pruebas de humo y *swap* a producción.
4. Paginación y filtros en SQL; evitar el patrón actual de leer todo y filtrar en memoria.
5. Transacciones en operaciones compuestas (registrar novedad = novedad + destinatarios + datos + mensaje + notificaciones).
6. Parámetros siempre; ninguna concatenación de SQL.

## 7. Fuentes consultadas

- [Configure an ASP.NET Core app for Azure App Service (Microsoft Learn)](https://learn.microsoft.com/en-us/AZURE/app-service/configure-language-dotnetcore)
- [Connect Using Microsoft Entra authentication — Drivers for PHP for SQL Server (Microsoft Learn)](https://learn.microsoft.com/sk-sk/sql/connect/php/azure-active-directory?view=sql-server-2016) *(página versionada para SQL Server 2016: puede estar desactualizada)*
- [Tutorial: conectar con identidad administrada a Azure SQL desde App Service (Microsoft Docs en GitHub)](https://github.com/MicrosoftDocs/azure-docs/blob/main/articles/app-service/tutorial-connect-msi-sql-database.md)
- [Acceso a Azure SQL con identidad administrada en PHP en App Service (guía de terceros)](https://app-pack.telkomuniversity.ac.id/?p=451598)
- [Service Connector: integrar SQL Database (Microsoft Learn)](https://learn.microsoft.com/en-us/azure/service-connector/how-to-integrate-sql-database)
