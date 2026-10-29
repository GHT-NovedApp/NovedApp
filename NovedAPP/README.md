# Sistema de Gestión de Novedades Operativas — Prototipo v1

## Requisitos

- PHP 8.1 o superior
- Servidor local: [XAMPP](https://www.apachefriends.org/), [MAMP](https://www.mamp.info/), o [Laragon](https://laragon.org/) (recomendado)
- Navegador moderno (Chrome, Edge, Firefox)

## Instalación en 5 pasos

1. **Descarga** la carpeta `gestion-novedades/` completa.
2. **Copia** la carpeta al directorio raíz de tu servidor:
   - XAMPP → `C:/xampp/htdocs/gestion-novedades/`
   - MAMP  → `/Applications/MAMP/htdocs/gestion-novedades/`
   - Laragon → `C:/laragon/www/gestion-novedades/`
3. **Inicia** Apache desde el panel de control de tu servidor.
4. **Abre** en el navegador: `http://localhost/gestion-novedades/`
5. **Inicia sesión** con uno de los usuarios de prueba.

## Usuarios de Prueba

| Usuario | Contraseña | Perfil | Actor | Organización |
|---------|-----------|--------|-------|-------------|
| `admin` | `admin123` | Administrador | GHT | GHT Comercio Exterior |
| `gestor_osl` | `osl123` | Gestor | OSL | OSL Exportaciones Bogotá |
| `gestor_oca` | `oca123` | Gestor | OCA | OCA Bogotá |
| `gestor_ght` | `ght123` | Gestor | GHT | GHT Comercio Exterior |
| `eai_manuelita` | `finca123` | Reportante | EAI | Finca Manuelita |
| `eai_manuelita2` | `finca456` | Reportante | EAI | Finca Manuelita |
| `cedi_bg` | `cedi123` | Reportante | CEDI | BG-Tocancipá |
| `cedi_bg2` | `cedi456` | Reportante | CEDI | BG-FD |
| `adc_bog` | `adc123` | Reportante | AdC | EXP BOG |
| `adc_mde` | `adc456` | Reportante | AdC | EXP MDE |

## Novedades de Ejemplo Pre-cargadas

El sistema arranca con 12 novedades de ejemplo en distintos estados:

| ID | Novedad | Estado | Actor |
|----|---------|--------|-------|
| NOV-001 | CAJA_FALTANTE | ✅ Resuelta | EAI - Finca Manuelita |
| NOV-002 | ARRIBO_DEMORA | 🔵 En Gestión | CEDI - BG-Tocancipá |
| NOV-003 | EPP_AUSENTE | 🟠 Abierta | EAI - Finca Manuelita |
| NOV-004 | AVION_FALLA_MECANICA | ✅ Resuelta | AdC - EXP BOG |
| NOV-005 | CARTON_DANADO | 🔵 En Gestión | EAI - Finca Manuelita |
| NOV-006 | CONDUCTORES_Y_VEHICULOS_ERRADO | 🟠 Abierta | CEDI - BG-FD |
| NOV-007 | BANDA_FALLA | ⚫ Reporte | OCA |
| NOV-008 | ACUERDO_PARTICULAR | ✅ Resuelta | EAI - Finca Manuelita |
| NOV-009 | CAJA_HURTADA | 🟠 Abierta | EAI - Finca Manuelita |
| NOV-010 | DOCUMENTO_VEHICULO_VENCIDO | ⚫ Reporte | OTT |
| NOV-011 | ACCIDENTE_DE_TRABAJO | 🔵 En Gestión | CECO |
| NOV-012 | AWB_ERRADO | ✅ Resuelta | AdC - EXP MDE |

## Estructura del Proyecto

```
gestion-novedades/
├── index.php                   # Enrutador principal
├── login.php                   # Login
├── logout.php                  # Cierre de sesión
├── css/                        # Estilos
├── js/                         # JavaScript
├── pages/                      # Vistas
├── includes/                   # Fragmentos reutilizables
├── data/                       # Catálogos PHP + datos ejemplo
└── export/                     # Exportación CSV
```

## Notas de la Fase 1

- Las novedades nuevas registradas durante la sesión se pierden al cerrar el navegador (sin base de datos en v1).
- Las contraseñas están en texto plano (solo para prototipo). En producción se usará `password_hash()`.
- El catálogo de novedades tiene 471 tipos cargados desde los CSVs originales.
