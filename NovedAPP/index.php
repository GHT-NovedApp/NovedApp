<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}
// Destino según lo que el permiso de la cuenta puede abrir
if (!tiene_capacidad('ver_dashboard')) {
    if (tiene_capacidad('crear_novedades')) { header('Location: pages/nueva_novedad.php'); exit; }
    if (puede_parametrizar()) { header('Location: pages/' . parametrizacion_primera_seccion()); exit; }
    echo 'Tu permiso no tiene pantallas habilitadas. Contacta al administrador.'; exit;
}
switch ($_SESSION['perfil']) {
    case 'administrador':
        header('Location: pages/dashboard_admin.php'); break;
    case 'torre_control':
        header('Location: pages/dashboard_torre.php'); break;
    case 'gestor':
        header('Location: pages/dashboard_gestor.php'); break;
    default:
        header('Location: pages/dashboard_reportante.php');
}
exit;
