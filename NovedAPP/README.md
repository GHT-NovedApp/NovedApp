# Sistema de Gestión de Novedades Operativas — Demo estática

La demo compatible con GitHub Pages está en [`site/`](site/). No requiere PHP, Apache, base de datos ni configuración regional del servidor.

Abre `site/index.html` o publica `site/` mediante GitHub Actions. El workflow del repositorio publica solamente esta carpeta.

La aplicación de demostración permite consultar y filtrar novedades, crear reportes, responder, actualizar estados y exportar CSV. Los datos se guardan en `localStorage` del navegador.

## Límites de la demo estática

- El selector de perfil solo cambia la vista de demostración; no autentica ni protege información.
- Los datos son privados de ese navegador y no se sincronizan entre usuarios o dispositivos.
- La demo no incluye la parametrización administrativa ni las notificaciones compartidas de la aplicación PHP.
- No introduzcas datos reales, personales o sensibles.

La versión anterior basada en PHP se conserva fuera del directorio publicado como referencia; GitHub Pages no ejecuta sus archivos `.php` ni sus escrituras en `storage/`.
