<?php
require_once __DIR__ . '/config_regional.php';
// includes/storage.php
require_once __DIR__ . '/usuarios_store.php';
// Capa de persistencia en JSON

define('STORAGE_DIR',    __DIR__ . '/../storage/');
define('NOVEDADES_FILE', STORAGE_DIR . 'novedades.json');
define('NOTIF_FILE',     STORAGE_DIR . 'notificaciones.json');

// ── Lectura ──────────────────────────────────────────────

function storage_leer_novedades(): array {
    if (!file_exists(NOVEDADES_FILE)) return [];
    return json_decode(file_get_contents(NOVEDADES_FILE), true) ?? [];
}

function storage_leer_notificaciones(): array {
    if (!file_exists(NOTIF_FILE)) return [];
    return json_decode(file_get_contents(NOTIF_FILE), true) ?? [];
}

// ── Escritura ────────────────────────────────────────────

function storage_guardar_novedad(array $novedad): bool {
    $todas = storage_leer_novedades();
    $novedad['updated_at'] = date('Y-m-d H:i:s'); // timestamp de ultima actividad
    $todas[$novedad['id']] = $novedad;
    return file_put_contents(
        NOVEDADES_FILE,
        json_encode($todas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

function storage_actualizar_novedad(string $id, array $novedad): bool {
    return storage_guardar_novedad($novedad);
}

function storage_obtener_novedad(string $id): ?array {
    return storage_leer_novedades()[$id] ?? null;
}

// ── Notificaciones ───────────────────────────────────────

/**
 * Notifica ÚNICAMENTE a:
 *  - Los usuarios seleccionados manualmente en el formulario (respondentes_usuarios)
 *  - Admin y Torre de Control según flags de parametrización
 */
function storage_notificar_respondentes(array $novedad): void {
    $notifs = storage_leer_notificaciones();

    // 1. Partimos de los usuarios seleccionados en el formulario (IDs limpios)
    $raw = $novedad['respondentes_usuarios'] ?? [];
    $uids = [];
    foreach ($raw as $item) {
        // Puede venir como string 'usuario123' o como array ['usuario'=>'...']
        $uid = is_array($item) ? ($item['usuario'] ?? '') : (string)$item;
        if ($uid !== '') $uids[] = $uid;
    }

    // 2. Leer flags de notificación de la novedad parametrizada
    $custom_file = __DIR__ . '/../storage/novedades_custom.json';
    $custom      = file_exists($custom_file)
                   ? (json_decode(file_get_contents($custom_file), true) ?? []) : [];
    $tipo            = $novedad['tipo_novedad'] ?? '';
    $notificar_admin = $custom[$tipo]['notificar_admin'] ?? true;
    $notificar_torre = $custom[$tipo]['notificar_torre'] ?? true;

    // 3. Agregar admin y torre_control si sus flags están activos
    if ($notificar_admin || $notificar_torre) {
        // Usar include (no require_once) para garantizar que $usuarios quede en scope
        $usuarios = usuarios_todos();
        foreach ($usuarios as $uname => $u) {
            if (!($u['activo'] ?? true)) continue;
            $perfil = $u['perfil'] ?? '';
            if ($notificar_admin && $perfil === 'administrador') $uids[] = $uname;
            if ($notificar_torre && $perfil === 'torre_control')  $uids[] = $uname;
        }
    }

    // 4. Deduplicar y escribir notificaciones
    $uids = array_unique(array_filter($uids));
    foreach ($uids as $uid) {
        if (!isset($notifs[$uid])) $notifs[$uid] = [];
        // Evitar duplicado para la misma novedad
        $existentes = array_column($notifs[$uid], 'novedad_id');
        if (in_array($novedad['id'], $existentes)) continue;
        $notifs[$uid][] = [
            'novedad_id'   => $novedad['id'],
            'tipo_novedad' => $novedad['tipo_novedad'],
            'organizacion' => $novedad['organizacion'],
            'tipologia'    => $novedad['tipologia'] ?? '',
            'fecha'        => date('Y-m-d H:i:s'),
            'leida'        => false,
        ];
    }

    file_put_contents(NOTIF_FILE, json_encode($notifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Notifica a todos los participantes de una novedad cuando hay un nuevo mensaje,
 * excluyendo al usuario que acaba de escribir para evitar auto-notificación.
 * Destinatarios: respondentes_usuarios + reportante + admin + torre_control
 */
function storage_notificar_respuesta(array $novedad, string $autor_uid, string $evento = 'respuesta'): void {
    $notifs = storage_leer_notificaciones();

    // Construir lista de UIDs a notificar
    $uids = [];

    // 1. Respondentes asignados
    foreach ($novedad['respondentes_usuarios'] ?? [] as $uid) {
        $u = is_array($uid) ? ($uid['usuario'] ?? '') : (string)$uid;
        if ($u) $uids[] = $u;
    }

    // 2. Reportante original
    if (!empty($novedad['usuario_reportante'])) {
        $uids[] = $novedad['usuario_reportante'];
    }

    // 3. Admin y Torre de Control siempre
    $usuarios = usuarios_todos();
    foreach ($usuarios as $uname => $u) {
        if (!($u['activo'] ?? true)) continue;
        $p = $u['perfil'] ?? '';
        if ($p === 'administrador' || $p === 'torre_control') {
            $uids[] = $uname;
        }
    }

    // 4. Deduplicar y excluir al autor actual
    $uids = array_values(array_unique(array_filter($uids)));

    $tipo_msg = str_replace('_', ' ', $novedad['tipo_novedad'] ?? '');
    foreach ($uids as $uid) {
        if ($uid === $autor_uid) continue; // no auto-notificar
        if (!isset($notifs[$uid])) $notifs[$uid] = [];
        // Actualizar notificación existente o crear nueva
        $encontrado = false;
        foreach ($notifs[$uid] as &$n) {
            if ($n['novedad_id'] === $novedad['id']) {
                // Marcar como no leída para que aparezca como nueva actividad
                $n['leida'] = false;
                $n['fecha'] = date('Y-m-d H:i:s');
                $n['ultimo_mensaje'] = true;
                $n['evento'] = $evento;
                $encontrado = true;
                break;
            }
        }
        unset($n);
        if (!$encontrado) {
            $notifs[$uid][] = [
                'novedad_id'     => $novedad['id'],
                'tipo_novedad'   => $novedad['tipo_novedad'],
                'organizacion'   => $novedad['organizacion'],
                'tipologia'      => $novedad['tipologia'] ?? '',
                'fecha'          => date('Y-m-d H:i:s'),
                'leida'          => false,
                'ultimo_mensaje' => true,
                'evento'         => $evento,
            ];
        }
    }

    file_put_contents(NOTIF_FILE, json_encode($notifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Actualiza el campo updated_at de una novedad guardada para ordenar dashboards.
 */
function storage_touch_novedad(string $id): void {
    $todas = storage_leer_novedades();
    if (isset($todas[$id])) {
        $todas[$id]['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents(NOVEDADES_FILE, json_encode($todas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

function storage_marcar_leidas(string $usuario, string $novedad_id): void {
    $notifs = storage_leer_notificaciones();
    if (!isset($notifs[$usuario])) return;
    foreach ($notifs[$usuario] as &$n) {
        if ($n['novedad_id'] === $novedad_id) $n['leida'] = true;
    }
    file_put_contents(NOTIF_FILE, json_encode($notifs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function storage_contar_no_leidas(string $usuario): int {
    $notifs = storage_leer_notificaciones();
    if (!isset($notifs[$usuario])) return 0;
    return count(array_filter($notifs[$usuario], fn($n) => !$n['leida']));
}

function storage_notificaciones_usuario(string $usuario): array {
    $notifs = storage_leer_notificaciones();
    return array_reverse($notifs[$usuario] ?? []);
}

// ── Catálogo y helpers ───────────────────────────────────

function storage_todas_las_novedades(): array {
    $novedades_ejemplo = [];
    require __DIR__ . '/../data/novedades_ejemplo.php';
    $guardadas = storage_leer_novedades();
    $todas = array_merge($novedades_ejemplo, $guardadas);

    // Ordenar de más reciente a más antigua por updated_at o fecha_creacion
    uasort($todas, function($a, $b) {
        $ta = $a['updated_at'] ?? $a['fecha_creacion'] ?? '0';
        $tb = $b['updated_at'] ?? $b['fecha_creacion'] ?? '0';
        return strcmp($tb, $ta); // DESC
    });

    return $todas;
}

function storage_catalogo_completo(): array {
    $custom_file = __DIR__ . '/../storage/novedades_custom.json';
    if (!file_exists($custom_file)) return [];
    $custom = json_decode(file_get_contents($custom_file), true) ?? [];
    require_once __DIR__ . '/tipologias.php';
    foreach ($custom as $k => $n) { $custom[$k]['tipologias'] = novedad_tipologias($n); }
    return array_filter($custom, fn($n) => $n['activo'] ?? true);
}

function storage_nuevo_id(): string {
    $todas = storage_leer_novedades();
    $num   = count($todas) + 1;
    do {
        $id = 'NOV-' . date('Ymd') . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
        $num++;
    } while (isset($todas[$id]));
    return $id;
}
