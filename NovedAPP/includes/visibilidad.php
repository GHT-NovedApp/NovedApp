<?php
// includes/visibilidad.php
// Qué novedades puede ver (y por lo tanto exportar) el usuario en sesión.
//   · ver ‹novedad_visible_para› para la regla única

require_once __DIR__ . '/storage.php';

/** Usuarios etiquetados por nombre en la novedad (respondentes_usuarios y destinatarios; ignora correos externos). */
function novedad_usuarios_etiquetados(array $n): array {
    $res = [];
    foreach ($n['respondentes_usuarios'] ?? [] as $it) {
        $u = is_array($it) ? ($it['usuario'] ?? '') : (string)$it;
        if ($u !== '') $res[$u] = true;
    }
    foreach ($n['destinatarios'] ?? [] as $d) {
        $u = is_array($d) ? ($d['usuario'] ?? '') : '';
        if ($u !== '' && empty($d['externo'])) $res[$u] = true;
    }
    return array_keys($res);
}

/**
 * Regla única de acceso a una novedad (tablero, exportación y detalle):
 *   · permiso con «Ver todas las novedades»: todas
 *   · quien la reportó
 *   · cualquier usuario etiquetado por nombre (solo él, no todo su actor)
 *   · gestor: además las dirigidas a su actor
 */
function novedad_visible_para(array $n, string $perfil, string $usuario, string $actor): bool {
    require_once __DIR__ . '/permisos_store.php';
    if (permisos_tiene($perfil, 'ver_todas')) return true;
    if (($n['usuario_reportante'] ?? '') === $usuario) return true;
    if (in_array($usuario, novedad_usuarios_etiquetados($n), true)) return true;
    return $perfil === 'gestor' && in_array($actor, $n['respondentes'] ?? [], true);
}

function novedad_visible_para_sesion(array $n): bool {
    return novedad_visible_para($n, $_SESSION['perfil'] ?? '', $_SESSION['usuario'] ?? '', $_SESSION['tipo_actor'] ?? '');
}

function novedades_visibles_para_sesion(): array {
    return array_filter(storage_todas_las_novedades(), 'novedad_visible_para_sesion');
}