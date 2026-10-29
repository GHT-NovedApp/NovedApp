# NovedApp

## Demo estática en GitHub Pages

La versión estática está en [`NovedAPP/site`](NovedAPP/site). El workflow de GitHub Pages publica únicamente esa carpeta; el código PHP y los JSON internos del prototipo no forman parte del sitio publicado.

Para habilitar el despliegue, en GitHub abre **Settings → Pages** y selecciona **GitHub Actions** como fuente. Después de subir cambios a la rama principal, el workflow publica el sitio. También puede abrirse `NovedAPP/site/index.html` directamente en un navegador moderno.

Esta demo no requiere PHP ni Apache. Los perfiles son selectores de demostración, no autenticación, y las novedades se guardan en el almacenamiento local del navegador: no se comparten entre personas ni dispositivos. No se deben introducir datos reales o sensibles. La versión PHP original se conserva en `NovedAPP/` como referencia y no se despliega.