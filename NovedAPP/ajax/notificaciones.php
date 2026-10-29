<?php
// ajax/notificaciones.php — Gestión AJAX de notificaciones (sistema JSON)
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['status'=>'error','msg'=>'No autorizado']); exit;
}

require_once __DIR__ . '/../includes/storage.php';

$accion  = $_POST['accion'] ?? '';
$usuario = $_SESSION['usuario'];
$nov_id  = $_POST['novedad_id'] ?? '';

$notifs  = storage_leer_notificaciones();
$mis     = $notifs[$usuario] ?? [];

switch ($accion) {

    // Marcar una notificación como leída/no leída (toggle)
    case 'toggle_leido':
        foreach ($mis as &$n) {
            if ($n['novedad_id'] === $nov_id) {
                $n['leida'] = !($n['leida'] ?? false);
                break;
            }
        }
        unset($n);
        $notifs[$usuario] = $mis;
        file_put_contents(
            __DIR__ . '/../storage/notificaciones.json',
            json_encode($notifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        // Devolver nuevo badge count
        $no_leidas = count(array_filter($mis, fn($n) => !$n['leida']));
        echo json_encode(['status'=>'ok','no_leidas'=>$no_leidas]);
        break;

    // Eliminar una notificación específica
    case 'eliminar':
        $mis = array_values(array_filter($mis, fn($n) => $n['novedad_id'] !== $nov_id));
        $notifs[$usuario] = $mis;
        file_put_contents(
            __DIR__ . '/../storage/notificaciones.json',
            json_encode($notifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        $no_leidas = count(array_filter($mis, fn($n) => !$n['leida']));
        echo json_encode(['status'=>'ok','no_leidas'=>$no_leidas]);
        break;

    // Marcar todas como leídas
    case 'marcar_todas_leidas':
        foreach ($mis as &$n) { $n['leida'] = true; }
        unset($n);
        $notifs[$usuario] = $mis;
        file_put_contents(
            __DIR__ . '/../storage/notificaciones.json',
            json_encode($notifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        echo json_encode(['status'=>'ok','no_leidas'=>0]);
        break;

    // Limpiar todas las notificaciones del usuario
    case 'limpiar_todas':
        $notifs[$usuario] = [];
        file_put_contents(
            __DIR__ . '/../storage/notificaciones.json',
            json_encode($notifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        echo json_encode(['status'=>'ok','no_leidas'=>0]);
        break;

    default:
        echo json_encode(['status'=>'error','msg'=>'Accion desconocida']);
}
