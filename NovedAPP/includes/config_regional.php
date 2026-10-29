<?php
// includes/config_regional.php — Zona horaria única de la aplicación.
// Sin esto PHP usa la del servidor (en XAMPP, Europe/Berlin) y las fechas guardadas salen desplazadas.
// Es un parámetro de configuración: se fija aquí (o en la migración) y no en cada pantalla.
date_default_timezone_set('America/Bogota');
