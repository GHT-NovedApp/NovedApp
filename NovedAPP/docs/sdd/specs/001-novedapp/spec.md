<!-- SDD Artifact | Version: 1.3 | Phase: Specify | Updated: 2026-10-21 -->
<!-- Project: NovedApp | Feature: 001-novedapp -->
<!-- Change Log:
  v1.0 - Especificación "as-built" reconstruida leyendo el código v1 (PHP + JSON) y alineada con el
         stack objetivo (Azure App Service + Azure SQL). No se encontró un SDD previo dentro del proyecto.
  v1.1 - Decisiones funcionales del PO: exportación por visibilidad, panel propio de la torre, carga masiva (plantilla),
         listado completo de actores al etiquetar, metas de éxito, catálogo de causas (vía plantilla).
  v1.2 - Resueltas CL-001 (cuentas propias), CL-002 (adjuntos en Azure Blob) y CL-003 (envío de correo diferido).
  v1.3 - Política de contraseñas, límites de adjuntos y envío de correo se tratan como parámetros de configuración definidos en la migración (no como decisiones abiertas).
-->

# Especificación Funcional: NovedApp — Gestión de Novedades Operativas

## Resumen

| Campo | Valor |
|-------|-------|
| Funcionalidad | Sistema de gestión de novedades operativas y eventos de riesgo |
| Número | 001 |
| Fecha | 2026-10-21 |
| Autor | Reconstruido del proyecto existente + indicaciones del PO |
| Estado | **Borrador para revisión** |

### Planteamiento del problema
En la cadena de exportación e importación (fincas, centros de distribución, agencias de carga, transportadores,
torre de control) las novedades —retrasos, daños, accidentes, hurtos, errores documentales— se reportan sin un
flujo común: no queda claro quién debe responder, qué información es obligatoria según el tipo de evento ni en
qué estado va cada caso.

### Solución propuesta
Una aplicación web interna donde cualquier actor autorizado **registra** una novedad con la información que su
tipo exige, la **dirige** a los actores responsables, y estos la **gestionan** en una conversación hasta
**resolverla**. Un módulo de **Parametrización** permite a la administración definir, sin tocar código, los
tipos de operación, las tipologías, los tipos de novedad (con sus permisos y campos), los usuarios y las
organizaciones.

### Usuarios objetivo

| Persona | Permiso del sistema | Qué necesita |
|---------|--------------------|--------------|
| **Administrador** | `administrador` | Ver todo, parametrizar y gestionar usuarios; recibir avisos de novedades |
| **Torre de Control** | `torre_control` | Ver todo y supervisar alertas; parametrizar solo si un administrador se lo otorga |
| **Gestor / Respondiente** | `gestor` | Responder y gestionar las novedades dirigidas a su actor o a él |
| **Reportante** | `reportante` | Crear sus novedades y hacerles seguimiento |

> **Rol en la empresa ≠ permisos del sistema.** El *rol en la empresa* es el cargo real (p. ej. "Analista SST"
> en una finca) y sirve de contexto; los *permisos del sistema* determinan lo que la persona puede hacer en
> la aplicación.

---

## Historias de Usuario

> Estado de cada historia respecto al código actual (v1): **Implementada**, **Parcial**, **Pendiente**.

### US-001: Ingresar y salir de forma segura — *Implementada (con deuda de seguridad)*
**Como** usuario del sistema, **quiero** iniciar sesión con mi usuario y contraseña, **para** acceder solo a lo que mi permiso permite.

**Criterios de aceptación**
- Dado un usuario activo con credenciales correctas, cuando inicia sesión, entonces llega a su panel según su permiso.
- Dado un usuario inactivo, cuando intenta ingresar o ya estaba conectado, entonces pierde el acceso y se le informa.
- Dado un usuario sin el permiso requerido, cuando intenta abrir una pantalla restringida, entonces es redirigido.
- Los cambios de permisos o de estado se aplican en la siguiente petición, sin esperar a que cierre sesión.
- Las reglas de contraseña (largo, complejidad, caducidad, intentos, bloqueo, recuperación) son un **parámetro de configuración** que se fija en la migración; la aplicación las lee de la configuración y no las tiene fijas.

**Prueba independiente:** inactivar un usuario conectado y verificar que su siguiente clic lo envía al login.
**Prioridad:** Must Have

### US-002: Registrar una novedad o evento de riesgo — *Implementada (adjuntos y correo pendientes)*
**Como** reportante (o administrador / torre), **quiero** registrar una novedad con su clasificación, descripción,
datos del evento y destinatarios, **para** que quien debe actuar la reciba con la información completa.

**Criterios de aceptación**
- Dado que elijo un **tipo de operación** activo, cuando elijo la **tipología** (activa) y luego el **tipo de novedad**,
  entonces solo veo los tipos que pertenecen a esa tipología.
- Dado un reportante, entonces solo ve los tipos de novedad cuyo "Puede reportar" incluye su actor;
  **administrador y torre de control ven todos**.
- Dado un tipo con **causales**, entonces debo elegir una causal.
- Dado un tipo con **información necesaria**, cuando intento guardar sin completarla, entonces el sistema lo impide
  y me indica qué falta.
- Dado un tipo con el **grupo Terrestre** habilitado, cuando al menos un usuario de "Dirigido a" (principal o
  etiquetado) es de actor **OTT u OTT_TERCEROS**, entonces los datos terrestres parametrizados se habilitan y son
  **obligatorios**; en cualquier otro caso no se piden. *(Quien reporta no influye en esta regla.)*
- Dado un tipo con grupos Aéreo o Tiempos, entonces se habilitan sus campos parametrizados.
- Dado que elijo **Actor responsable** y **Usuario principal** (obligatorios), cuando pulso "Etiquetar otro actor",
  entonces puedo agregar usuarios y correos externos. Si el tipo define actores **etiquetables** se ofrecen esos; si no define
  ninguno, se despliega el **listado completo de actores** con usuarios activos (los externos a la novedad).
- Al guardar, la novedad recibe un identificador único `NOV-AAAAMMDD-NNN`, queda en estado **Abierta** (o
  **Reporte** si el tipo es solo informativo) y se notifica a los destinatarios y, según el tipo, a administrador y torre.
- La novedad conserva **todas las tipologías** de su tipo, con la elegida por quien reporta como principal.

**Prueba independiente:** registrar una novedad con un destinatario OTT y verificar que sin datos terrestres no se guarda.
**Prioridad:** Must Have

### US-003: Gestionar una novedad en una conversación — *Implementada*
**Como** gestor, **quiero** responder una novedad y resolverla, **para** cerrar el caso con trazabilidad.

**Criterios de aceptación**
- Dado un gestor cuyo actor está entre los respondentes del tipo (o un administrador/torre), cuando responde,
  entonces el mensaje queda en el hilo con autor y fecha y una novedad *Abierta* pasa a *En gestión*.
- Dado el reportante, cuando replica, entonces su mensaje queda en el hilo y se notifica a los participantes.
- Dado un usuario autorizado, cuando marca **resolver** (con confirmación), entonces la novedad pasa a *Resuelta*,
  se guarda quién y cuándo, y se notifica el cierre.
- Las novedades de tipo informativo (*Reporte*) no requieren respuesta.

**Prueba independiente:** responder, replicar y resolver una novedad y revisar el hilo completo.
**Prioridad:** Must Have

### US-004: Recibir avisos dentro de la aplicación — *Implementada*
**Como** usuario, **quiero** ver mis notificaciones, **para** enterarme de novedades nuevas y respuestas.

**Criterios de aceptación**
- La campana muestra el número de no leídas y la lista (todas / sin leer).
- Puedo marcar una como leída o no leída, eliminarla, marcar todas como leídas o limpiarlas (con confirmación).
- Se generan al registrar, responder, replicar y resolver.

**Prioridad:** Should Have

### US-005: Ver y filtrar mis novedades en un panel — *Implementada*
**Como** usuario de cualquier perfil, **quiero** un panel con indicadores y una lista filtrable, **para** saber qué requiere atención.

**Criterios de aceptación**
- Administrador y torre ven **todas** las novedades; el gestor ve las dirigidas a su actor o a él y las que reportó;
  el reportante ve las que reportó.
- Los indicadores (Abiertas, En gestión, Reportes, Resueltas, Total) **son clicables** y filtran la lista.
- Al entrar se muestran las **Activas** (abiertas + en gestión); puedo combinar estados a la vez.
- El panel del administrador permite buscar por ID, novedad u organización; filtrar por actor, tipología,
  operación y fechas; **ordenar por columna**, **paginar** (25 por página) y ver el **tiempo abierta** de cada caso.
- Cada fila abre el detalle; todo funciona con teclado y desde un celular.

**Prioridad:** Must Have

### US-006: Consultar el detalle de una novedad — *Implementada*
**Como** participante, **quiero** ver todos los datos del evento y su conversación, **para** entender el caso completo.

**Criterios de aceptación:** muestra estado, tipologías, operación, datos del evento, causal, destinatarios y el hilo
(reporte, respuesta, réplica, cierre, cada uno identificado por texto y no solo por color).
**Prioridad:** Must Have

### US-007: Parametrizar los tipos de operación — *Implementada*
**Como** administrador, **quiero** crear, editar, inactivar y eliminar tipos de operación, **para** adaptarme sin cambios de código.

**Criterios de aceptación**
- Cada tipo tiene sigla única, nombre único, descripción opcional y estado activo/inactivo.
- Solo los **activos** se ofrecen al registrar una novedad y en los filtros.
- No se puede eliminar un tipo usado por novedades (se sugiere inactivarlo) ni dejar el sistema sin ninguno activo.
- Los tipos originales son Exportación, Importación y MVCS.
**Prioridad:** Must Have

### US-008: Parametrizar las tipologías — *Implementada*
**Como** administrador, **quiero** gestionar las tipologías (OPR, SEG, SST, BDS, GC, OSL y las que se agreguen), **para** agrupar los tipos de novedad.

**Criterios de aceptación:** mismas reglas que US-007; además la **sigla no se puede cambiar** si ya la usan tipos de novedad o novedades.
**Prioridad:** Must Have

### US-009: Parametrizar los tipos de novedad — *Implementada*
**Como** administrador, **quiero** definir cada tipo de novedad con su clasificación, permisos y campos, **para** que el formulario de registro se adapte al tipo.

**Criterios de aceptación** (asistente de 4 pasos)
1. **Clasificación:** tipos de operación (varios), eje, tipo, subtipo, nombre (acción + complemento), **tipologías (varias)**, descripción y causales.
2. **Permisos:** actores que **pueden reportar**, **pueden responder** y **etiquetables**; notificaciones automáticas a administrador y/o torre; estado activo.
3. **Características:** requiere respuesta, requiere adjunto y grupos de información (Terrestre, Aéreo, Tiempos).
4. **Campos:** qué campos de cada grupo se piden, información necesaria obligatoria y campos personalizados.
- Se puede editar, activar/desactivar y eliminar un tipo; al editar se cargan sus valores actuales.
**Prioridad:** Must Have

### US-010: Administrar usuarios — *Implementada*
**Como** administrador, **quiero** crear y mantener usuarios, **para** controlar quién accede y con qué permisos.

**Criterios de aceptación**
- Cada usuario tiene: nombre, usuario, **rol en la empresa**, **tipo de actor**, **organización/finca** (filtrada por actor) y **permisos del sistema**.
- Acciones: crear, **editar**, **cambiar contraseña** e **inactivar/activar**.
- El nombre de usuario no se puede cambiar si ya tiene novedades o notificaciones asociadas.
- No se puede inactivar la propia cuenta ni dejar el sistema sin administrador activo.
- Un usuario de Torre de Control con permiso de parametrizar **no** puede crear ni editar cuentas de administrador o torre, ni asignar esos permisos.
- Los permisos se distinguen con etiquetas de color y el cargo con una etiqueta neutra.
**Prioridad:** Must Have

### US-011: Administrar fincas y organizaciones — *Implementada*
**Como** administrador, **quiero** mantener el catálogo de organizaciones por tipo de actor, **para** asociar a cada usuario con su finca u organización.

**Criterios de aceptación:** crear, editar, activar/inactivar; búsqueda y filtro por tipo de actor y estado; paginación;
si se renombra una organización se actualiza en sus usuarios; las inactivas no se ofrecen al crear usuarios.
**Prioridad:** Must Have

### US-012: Exportar novedades — *Implementada*
**Como** usuario de cualquier perfil, **quiero** exportar a Excel lo que veo con los filtros aplicados, **para** analizarlo fuera del sistema.

**Criterios de aceptación**
- Cualquier usuario puede exportar, **limitado a las novedades que puede ver**: el reportante y el gestor exportan solo las suyas;
  **administrador y torre de control exportan todas**.
- El archivo respeta los mismos filtros del panel (estado, "Activas" por defecto, estados múltiples, búsqueda, actor, tipología, operación, fechas).
- Las tipologías de cada novedad salen todas en una columna.

**Prueba independiente:** exportar con un reportante y verificar que el archivo solo contiene sus novedades.
**Prioridad:** Should Have

### US-013: Adjuntar evidencia — *Pendiente (decidida)*
**Como** reportante, **quiero** adjuntar fotos y documentos a la novedad, **para** respaldar el reporte.

**Criterios de aceptación**
- Dado un reportante, cuando registra una novedad, entonces puede adjuntar archivos permitidos; si el tipo exige adjunto ("Requiere adjunto"), no puede guardar sin al menos uno.
- Los archivos se guardan de forma privada: solo los pueden ver o descargar quienes pueden ver la novedad.
- Los archivos no permitidos o que superan el tamaño máximo se rechazan con un mensaje claro.
- Quien responde o replica también puede adjuntar a su mensaje del hilo.

**Parámetros configurables (se fijan en la migración):** tipos de archivo permitidos, tamaño máximo, cantidad por mensaje y retención. *Valores de referencia para el arranque:* imágenes (JPG, PNG), PDF, Excel y Word —los que ya admite el formulario—, 10 MB por archivo y 5 por mensaje, conservados mientras exista la novedad.
**Estado:** el control existe en la pantalla, pero el archivo no se almacena.
**Prioridad:** Should Have

> **Envío de correo — diferido.** "Enviar copia por correo" y el aviso a correos externos de "Dirigido a" se incorporarán **más adelante**, cuando se conecte una API para esa función (decisión del PO, CL-003). No forman parte del alcance actual.

---

## Requisitos Funcionales

### Acceso y permisos
- **FR-001:** El sistema DEBE autenticar con usuario y contraseña y rechazar cuentas inactivas. *(Implementado)*
- **FR-002:** El sistema DEBE validar permisos y estado del usuario en **cada** petición. *(Implementado)*
- **FR-003:** El sistema DEBE aplicar cuatro permisos: administrador, torre de control, gestor y reportante, con la bandera adicional "puede parametrizar" solo para torre de control. *(Implementado)*
- **FR-004:** El sistema DEBE ocultar y bloquear la Parametrización a quien no sea administrador ni torre con permiso. *(Implementado)*
- **FR-005:** El sistema DEBERÍA proteger todos los formularios contra falsificación de peticiones y limitar los intentos fallidos de ingreso. *(Parcial: solo Parametrización)*
- **FR-006:** La política de contraseñas, los límites de adjuntos y la integración de correo DEBEN ser **parámetros de configuración** (no valores fijos en el código), definidos al migrar según sus requerimientos. *(Parámetro de migración)*

### Registro de novedades
- **FR-010:** El sistema DEBE ofrecer solo tipos de operación y tipologías **activos**. *(Implementado)*
- **FR-011:** El sistema DEBE filtrar los tipos de novedad por tipología y, para reportantes, por su actor. *(Implementado)*
- **FR-012:** El sistema DEBE exigir descripción, actor responsable y usuario principal. *(Implementado)*
- **FR-013:** El sistema DEBE exigir la **información necesaria** definida en el tipo de novedad, validando también en el servidor. *(Implementado)*
- **FR-014:** El sistema DEBE habilitar y exigir los **datos terrestres** parametrizados cuando el tipo los permite y un usuario de "Dirigido a" es OTT/OTT_TERCEROS, validando en el servidor con el actor registrado del usuario. *(Implementado)*
- **FR-015:** El sistema DEBE permitir **etiquetar** usuarios y correos externos: los actores etiquetables del tipo o, si no hay, el listado completo de actores. *(Implementado)*
- **FR-016:** El sistema DEBE asignar un identificador único y estado inicial (Abierta / Reporte) y registrar fecha de creación y de despacho. *(Implementado)*
- **FR-017:** El sistema DEBE guardar con la novedad todas las tipologías del tipo, con la elegida como principal. *(Implementado)*
- **FR-018:** El sistema DEBE permitir adjuntar evidencia a la novedad y a los mensajes del hilo, guardada en privado. *(Pendiente; ver US-013)*

### Gestión y seguimiento
- **FR-020:** El sistema DEBE registrar cada mensaje del hilo con autor, fecha y tipo (reporte, respuesta, réplica, cierre). *(Implementado)*
- **FR-021:** Solo pueden responder administrador, torre y gestores cuyo actor sea respondente del tipo. *(Implementado)*
- **FR-022:** Solo el reportante puede replicar. *(Implementado)*
- **FR-023:** El sistema DEBE permitir resolver con confirmación, guardando quién y cuándo. *(Implementado)*
- **FR-024:** El sistema DEBE notificar a los participantes en cada evento relevante. *(Implementado)*

### Paneles
- **FR-030:** Cada perfil DEBE ver únicamente las novedades que le corresponden (ver US-005). *(Implementado)*
- **FR-031:** Los indicadores por estado DEBEN ser clicables y el filtro por defecto DEBE ser "Activas". *(Implementado)*
- **FR-032:** El panel del administrador DEBE ofrecer búsqueda, filtros, ordenación, paginación y tiempo abierta. *(Implementado)*
- **FR-033:** Los paneles DEBEN usarse completos desde un celular. *(Implementado)*
- **FR-034:** La torre de control DEBE usar **su propio panel**, con las opciones (p. ej. Parametrización) que el administrador le haya otorgado. *(Implementado)*

### Parametrización
- **FR-040:** DEBE gestionar tipos de operación (sigla/nombre únicos, estado, descripción; protección por uso). *(Implementado)*
- **FR-041:** DEBE gestionar tipologías con las mismas reglas y sigla inmutable si está en uso. *(Implementado)*
- **FR-042:** DEBE gestionar tipos de novedad con el asistente de 4 pasos descrito en US-009. *(Implementado)*
- **FR-043:** Un tipo de novedad DEBE pertenecer a **una sola tipología** y poder aplicar a **varios tipos de operación**. *(Implementado)*
- **FR-044:** DEBE gestionar usuarios (crear, editar, contraseña, estado) con las reglas de US-010. *(Implementado)*
- **FR-045:** DEBE gestionar organizaciones con las reglas de US-011. *(Implementado)*
- **FR-046:** Toda acción destructiva DEBE pedir confirmación que nombre el objeto y la consecuencia. *(Implementado)*
- **FR-047:** Toda acción de Parametrización DEBE mostrar un aviso de éxito o de error entendible. *(Implementado)*
- **FR-048:** Los cambios de Parametrización DEBERÍAN quedar auditados (quién, qué, cuándo). *(Pendiente)*
- **FR-052:** Las capacidades de cada permiso del sistema DEBEN ser editables desde Parametrización → Permisos: pestañas del menú (tablero, «+ Nueva Novedad»), acciones sobre novedades (ver todas, reportar cualquier tipo, responder, responder cualquiera, cambiar estado) y acceso por sección de Parametrización con nivel «sin acceso / solo ver / ver y editar». La pestaña Parametrización se muestra si hay acceso a al menos una sección. Los valores por defecto equivalen al comportamiento original. Administrador conserva siempre el tablero y «Usuarios» y «Permisos» con edición; quien no es administrador solo edita Gestor y Reportante y no otorga más de lo que su permiso tiene. La marca por cuenta de Torre de Control se conserva como modo «Según cuenta». El servidor rechaza los envíos en secciones de solo lectura. *(Implementado; `storage/permisos.json` → tabla `PermisoCapacidad` en Azure SQL)*
- **FR-053:** El acceso a una novedad (tablero, exportación y detalle, incluido el panel lateral) DEBE seguir una única regla: permiso con «Ver todas las novedades»; quien la reportó; cualquier usuario etiquetado por nombre (solo él, no todo su actor); y, para el gestor, las dirigidas a su actor. Quien no cumple la regla recibe la misma respuesta que ante una novedad inexistente. *(Implementado)*

### Notificaciones
- **FR-050:** DEBE mostrar contador de no leídas y permitir gestionar cada aviso. *(Implementado)*
- **FR-051:** DEBE notificar a administrador y/o torre solo si el tipo de novedad lo indica. *(Implementado)*

### Exportación
- **FR-070:** DEBE exportar las novedades que el usuario puede ver, con los filtros activos (incluidos estados múltiples y "Activas"). *(Implementado)*
- **FR-071:** Reportante y gestor DEBEN exportar solo sus novedades; administrador y torre de control, todas. *(Implementado)*

### Experiencia de usuario
- **FR-080:** La interfaz DEBE estar en español, ser accesible (contraste, foco, teclado) y mantener la identidad GHT. *(Implementado)*

---

## Resumen de Entidades

- **Usuario:** persona con acceso; tiene nombre, usuario, cargo (rol en la empresa), actor, organización, permiso del sistema, estado y —si es torre— si puede parametrizar.
- **Organización (finca):** entidad que pertenece a un **tipo de actor** (EAI, CEDI, AdC, OTT…), con ubicación y estado.
- **Tipo de actor:** categoría de participante en la cadena; determina quién reporta, responde o es etiquetado.
- **Tipo de operación:** modalidad de la operación (Exportación, Importación, MVCS…).
- **Tipología:** agrupación de tipos de novedad (Operativa, Seguridad, SST…).
- **Tipo de novedad (catálogo):** definición de un evento reportable: clasificación, tipologías, operaciones, permisos por actor, causales, grupos de información, campos y notificaciones.
- **Causa:** motivo que se elige al registrar una novedad; se gestiona como catálogo central y cada tipo de novedad selecciona las que le aplican.
- **Novedad:** instancia reportada de un tipo; tiene estado, datos del evento, destinatarios e hilo.
- **Mensaje del hilo:** reporte, respuesta, réplica o cierre, con autor y fecha.
- **Notificación:** aviso a un usuario sobre una novedad, leído o no.

---

## Criterios de Éxito

*(Medibles y agnósticos de tecnología. Las cifras marcadas ⚠ son propuestas a validar con el PO.)*

- **SC-001:** Un reportante registra una novedad completa en menos de **2 minutos** ⚠.
- **SC-002:** El **100 %** de las respuestas, réplicas y cierres queda en el hilo con autor y fecha.
- **SC-003:** Un administrador agrega un tipo de operación o una tipología nueva en menos de **1 minuto** y sin intervención técnica.
- **SC-004:** Ninguna novedad con destinatario OTT y tipo terrestre se guarda sin los datos terrestres obligatorios (0 excepciones).
- **SC-005:** El **90 %** de los usuarios completa su tarea principal (registrar, responder o consultar) al primer intento ⚠ *(umbral propuesto; la meta de usabilidad definida por el PO es SC-009)*.
- **SC-006:** Un cambio de permisos o de estado de un usuario se refleja en su siguiente acción (sin esperar al cierre de sesión).
- **SC-007:** Todas las pantallas se usan sin desplazamiento horizontal desde un celular de 375 px y cumplen contraste AA.
- **SC-008 (meta del PO):** **Todas** las novedades operativas se registran por NovedApp (cero novedades operativas gestionadas fuera del sistema).
- **SC-009 (meta del PO):** La aplicación es fácil de usar: un usuario nuevo completa su tarea principal sin capacitación previa (se mide con SC-005 y con observación de los primeros usuarios de prueba).
- **SC-010 (meta del PO):** **Más de 50 personas** pueden usarla al mismo tiempo sin degradación perceptible.

---

## Supuestos y Dependencias

### Supuestos
- Es una herramienta **interna** de GHT y sus socios operativos; el uso es diario.
- Los tipos de actor son un conjunto relativamente estable (21 hoy); las organizaciones (más de 1 000) cambian con más frecuencia.
- Una novedad pertenece a un tipo de operación, y su tipo pertenece a una sola tipología.
- El catálogo heredado de 471 tipos del CSV original **no** se ofrece hoy en el formulario. Su información (y la del resto de datos maestros) se entregará en la **plantilla de carga masiva** antes de las pruebas funcionales (`docs/plantillas/`); no se migra desde el código.
- Usuarios, organizaciones, catálogos y tipos de novedad llegan por la **plantilla de carga masiva**; los datos de `data/` y `storage/` sirven de referencia y de datos de prueba.
- La autenticación usa **cuentas propias** de NovedApp (usuario y contraseña con hash); no hay SSO por ahora.

### Dependencias
- Azure SQL Database para guardar las cuentas propias de los usuarios (CL-001).
- *(Diferido)* Una API de envío de correo, cuando se incorpore esa función (CL-003).
- Azure Blob Storage para los adjuntos (CL-002).

---

## Límites del Alcance

### Dentro del alcance (versión actual y migración)
- Todo lo descrito en US-001 a US-012, y la implementación de US-013 (adjuntos).
- Migración a la plataforma objetivo (Azure App Service para Web Site y Web API; Azure SQL Database como repositorio).

### Fuera del alcance
- Aplicación móvil nativa.
- Integraciones con sistemas externos de trazabilidad de carga.
- Notificaciones push/SMS.
- **Envío de correos** (copia por correo y aviso a correos externos): se incorporará más adelante conectando una API para esa función.
- Reportes analíticos avanzados / tableros de BI.

### Consideraciones futuras
- Pantalla en Parametrización para **editar el catálogo de Causas** (hoy se carga con la plantilla; el modelo de datos ya lo contempla).
- Auditoría completa y bitácora de cambios.
- SLA y alertas por antigüedad de novedades abiertas.

---

## Deuda y brechas detectadas en el código actual

| # | Brecha | Efecto | Destino |
|---|--------|--------|---------|
| G-1 | Adjuntos: el campo de archivo existe pero no se guarda | Se pierde la evidencia | US-013 |
| G-2 | "Copia por correo": no se envía | Expectativa no cumplida | US-013 |
| ~~G-3~~ | ~~Exportación leía datos de ejemplo~~ | **Resuelta**: exporta las novedades reales según la visibilidad del usuario | US-012 |
| G-4 | Contraseñas sembradas en texto plano y contraseña demo visible en el login | Riesgo de seguridad | Constitución VII |
| G-5 | Persistencia en archivos JSON sin transacciones; escritura concurrente puede perder datos | Integridad | Plan (Azure SQL) |
| G-6 | El listado mezcla 12 novedades de ejemplo con las reales (`novedades_ejemplo.php`) | Datos de demostración en producción | Plan (migración) |
| G-7 | Sin auditoría de cambios | Sin trazabilidad administrativa | FR-048 |
| G-8 | Sin pruebas automatizadas | Riesgo de regresión | Constitución III |

---

## Clarificaciones Pendientes

Ninguna: no hay marcadores `[NEEDS CLARIFICATION]` ni decisiones funcionales abiertas.

## Parámetros que se definen en la migración

Por decisión del PO (2026-10-22), estos tres puntos **no son decisiones abiertas**: son **parámetros de configuración** que se fijan en el momento de la migración, según los requerimientos de la migración, para que todo sea compatible y no haya inconsistencias ni cambios grandes después al conectar APIs o herramientas.

| Parámetro | Qué incluye | Dónde vive | Efecto en el producto |
|-----------|-------------|------------|-----------------------|
| **Política de contraseñas** | Largo, complejidad, caducidad, intentos, bloqueo y recuperación | Configuración del API (tabla `Configuracion` o App Settings) | US-001, US-010; el Web Site lee las reglas del API, no las repite |
| **Límites de adjuntos** | Tipos permitidos, tamaño máximo, cantidad por mensaje y retención | Configuración del API | US-013; mismos límites en cliente y servidor |
| **Integración de correo** | Proveedor/API, remitente y si está habilitada (hoy: no) | Configuración del API + interfaz de notificación con implementación nula | Copia por correo y correos externos se activan sin tocar el sistema cuando se conecte la API |

Mientras no se fijen, la aplicación usa los **valores de referencia** de este documento (contraseña mínima de 6 caracteres con hash; límites de US-013; correo deshabilitado).

## Bitácora de Clarificaciones

| # | Pregunta | Respuesta | Fecha | Impacto |
|---|----------|-----------|-------|---------|
| 1 | ¿Qué plataforma de cómputo y datos se usará? | Azure App Service (Web Site y Web API) y Azure SQL Database | 2026-10-21 | `constitution.md`, `plan.md`, `data-model.md` |
| 2 | ¿Qué hacer si una cuenta OTT reporta? | No influye en la obligatoriedad de datos terrestres; solo cuenta el usuario etiquetado | 2026-10 | FR-014 |
| 3 | ¿Admin/torre ven todos los tipos de novedad al registrar? | Sí | 2026-10 | US-002 |
| 4 | ¿Un tipo de novedad puede pertenecer a varias tipologías? | No: una sola (se revirtió la selección múltiple, 2026-10-22) | 2026-10-22 | FR-043 |
| 5 | ¿Quién puede exportar y qué? | Cualquier usuario, solo lo que ve; administrador y torre exportan todo | 2026-10-21 | US-012, FR-070/071 |
| 6 | ¿Qué panel usa la torre de control? | Su propio panel, con los permisos que le otorgue el administrador | 2026-10-21 | FR-034 |
| 7 | ¿Qué hacer con el catálogo heredado de 471 tipos y los demás datos maestros? | Se entregan completos en una plantilla de carga masiva antes de las pruebas funcionales | 2026-10-21 | Plan, `docs/plantillas/`, tareas T-017 y T-018 |
| 8 | Política de contraseñas | Parámetro de configuración; se fija en la migración (PO, 2026-10-22) | 2026-10-22 | FR-006, constitución Art. IV |
| 9 | ¿Qué actores se ofrecen al etiquetar si el tipo no define etiquetables? | El listado completo de actores externos a la novedad | 2026-10-21 | US-002, FR-015 |
| 10 | Metas de éxito | Todas las novedades operativas por NovedApp; facilidad de uso; más de 50 usuarios simultáneos | 2026-10-21 | SC-008, SC-009, SC-010; Art. VIII |
| 11 | ¿Catálogo editable de Causas? | Se carga primero con la plantilla; si será campo editable se decide más adelante | 2026-10-21 | Entidad Causa, `data-model.md` |
| 12 | CL-001 · ¿Cómo inician sesión los usuarios? | **Solo cuentas propias** de NovedApp | 2026-10-21 | US-001, US-010, constitución Art. VII, plantilla (hoja Usuario) |
| 13 | CL-002 · ¿Se implementan los adjuntos? | **Sí**, guardados en Azure Blob Storage | 2026-10-21 | US-013, FR-018, plan D-003 |
| 14 | CL-003 · ¿Qué se hace con "enviar copia por correo" y los correos externos? | **Se incorpora más adelante**, cuando se conecte una API para esa función | 2026-10-21 | Alcance, US-013 (nota) |
| 15 | D-001 · ¿Lenguaje del Web API y del Web Site? | **PHP en ambos** (sujeto a la prueba de concepto T-002) | 2026-10-21 | Plan, constitución |
| 16 | ¿Qué hacer con los límites de adjuntos y el envío de correo? | También son **parámetros de configuración** que se definen al migrar, según sus requerimientos; el correo se integra detrás de una interfaz | 2026-10-22 | FR-006, US-013, plan §Parámetros |
