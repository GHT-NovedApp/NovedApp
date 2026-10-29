<?php
// includes/parametrizacion.php — utilidades compartidas por las pantallas de Parametrización

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/usuarios_store.php';

/** Pestañas del módulo (una sola vista de configuración). */
function parametrizacion_tabs(string $activa): void {
    $tabs = [
        'operaciones'    => ['admin_operaciones.php',    'Tipos de Operación'],
        'tipologias'     => ['admin_tipologias.php',     'Tipologías'],
        'novedades'      => ['admin_novedades.php',      'Tipos de Novedad'],
        'usuarios'       => ['admin_usuarios.php',       'Usuarios'],
        'permisos'       => ['admin_permisos.php',       'Permisos'],
        'organizaciones' => ['admin_organizaciones.php', 'Fincas y Organizaciones'],
    ];
    echo '<nav class="ap-tabs" aria-label="Secciones de parametrización">';
    foreach ($tabs as $clave => [$href, $texto]) {
        if (nivel_parametrizacion($clave) === 'ninguno') continue;   // sin acceso: la pestaña no se muestra
        $es = $clave === $activa;
        echo '<a href="' . $href . '" class="ap-tab' . ($es ? ' active' : '') . '"' . ($es ? ' aria-current="page"' : '') . '>' . $texto . '</a>';
    }
    echo '</nav>';
    if (!empty($GLOBALS['_param_solo_ver'])) {
        // Solo lectura: el servidor ya rechaza los envíos; aquí se retiran los controles de edición.
        echo '<div class="alert alert--info" role="status">Tu permiso solo te deja <strong>ver</strong> esta sección.</div>';
        echo '<style>.op-head .btn,.pz-nuevo,.op-actions,.pz-actions,.an-x1 .btn,.an-x13{display:none!important}</style>';
    }
}

function csrf_token(string $ambito): string {
    $k = 'csrf_' . $ambito;
    if (empty($_SESSION[$k])) $_SESSION[$k] = bin2hex(random_bytes(16));
    return $_SESSION[$k];
}

function csrf_valido(string $ambito): bool {
    return hash_equals($_SESSION['csrf_' . $ambito] ?? '', (string)($_POST['csrf'] ?? ''));
}

function flash_poner(string $ambito, bool $ok, string $texto): void {
    $_SESSION['flash_' . $ambito] = [$ok ? 'success' : 'error', $texto];
}

function flash_tomar(string $ambito): ?array {
    $f = $_SESSION['flash_' . $ambito] ?? null;
    unset($_SESSION['flash_' . $ambito]);
    return $f;
}

function flash_mostrar(?array $f): void {
    if (!$f) return;
    $ok = $f[0] === 'success';
    echo '<div class="alert alert--' . ($ok ? 'success' : 'error') . '" role="' . ($ok ? 'status' : 'alert') . '">' . htmlspecialchars($f[1]) . '</div>';
}

/** Etiqueta de color suave para los permisos del sistema. */
function badge_permiso(string $perfil): string {
    $lbl = PERMISOS_SISTEMA[$perfil]['label'] ?? ucfirst($perfil);
    return '<span class="pz-badge pz-perm pz-perm--' . htmlspecialchars($perfil) . '">' . htmlspecialchars($lbl) . '</span>';
}
