<?php
require_once __DIR__ . '/config_regional.php';
// includes/auth.php — Control de sesión y permisos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/permisos_store.php';   // capacidades editables de cada permiso (pestaña Permisos)

function verificar_sesion(string $perfil_requerido = '') {
    if (!isset($_SESSION['usuario'])) {
        header('Location: ../login.php'); exit;
    }
    // Se revalida contra el almacén en cada petición: un usuario inactivado o con permisos cambiados lo nota de inmediato.
    require_once __DIR__ . '/usuarios_store.php';
    $actual = usuarios_obtener($_SESSION['usuario']);
    if (!$actual || !$actual['activo']) {
        session_destroy();
        header('Location: ../login.php?error=inactivo'); exit;
    }
    $_SESSION['activo']             = true;
    $_SESSION['nombre']             = $actual['nombre'];
    $_SESSION['perfil']             = $actual['perfil'];
    $_SESSION['tipo_actor']         = $actual['tipo_actor'];
    $_SESSION['organizacion']       = $actual['organizacion'];
    $_SESSION['puede_parametrizar'] = !empty($actual['puede_parametrizar']);
    // Admin y torre_control pasan siempre (las pantallas propias de su rol)
    if (in_array($_SESSION['perfil'], ['administrador', 'torre_control'], true)) return;
    if ($perfil_requerido !== '' && $_SESSION['perfil'] !== $perfil_requerido) {
        header('Location: ../index.php?error=permiso'); exit;
    }
}

/**
 * Rol de un usuario en los destinatarios de una novedad: 'principal' (Dirigido a), 'copia',
 * '' (no está entre ellos) o 'sin_destinatarios' (novedades antiguas que no guardaron esa lista).
 */
function novedad_rol_destinatario(array $novedad, string $usuario): string {
    $dest = array_filter($novedad['destinatarios'] ?? [], 'is_array');
    if (!$dest) return 'sin_destinatarios';
    foreach ($dest as $d) {
        if (($d['usuario'] ?? '') === $usuario && empty($d['externo'])) return !empty($d['principal']) ? 'principal' : 'copia';
    }
    return '';
}

/**
 * Responder una novedad: requiere la capacidad «Responder». Con «Responder cualquier novedad» (administrador y
 * torre) en todas; si no, solo el usuario de «Dirigido a». Quien va en copia solo observa y recibe avisos.
 * Novedades antiguas sin lista de destinatarios: la regla anterior (usuario nombrado o actor respondente).
 */
function puede_responder(array $novedad): bool {
    if (!isset($_SESSION['perfil'])) return false;
    if (!permisos_tiene($_SESSION['perfil'], 'responder')) return false;
    if (permisos_tiene($_SESSION['perfil'], 'responder_todas')) return true;
    $usuario = $_SESSION['usuario'] ?? '';
    $rol = novedad_rol_destinatario($novedad, $usuario);
    if ($rol === 'principal') return true;
    if ($rol !== 'sin_destinatarios') return false;   // en copia, o no es destinatario
    foreach ($novedad['respondentes_usuarios'] ?? [] as $it) {
        if ((is_array($it) ? ($it['usuario'] ?? '') : (string)$it) === $usuario) return true;
    }
    return in_array($_SESSION['tipo_actor'] ?? '', $novedad['respondentes'] ?? [], true);
}

/** Cambiar el estado / resolver: requiere «Cambiar el estado» y poder responder esa novedad. */
function puede_resolver(array $novedad): bool {
    if (!isset($_SESSION['perfil'])) return false;
    return permisos_tiene($_SESSION['perfil'], 'cambiar_estado') && puede_responder($novedad);
}
/**
 * Solo admin puede crear/editar usuarios y catálogos.
 * Torre de control necesita solicitar permiso.
 */
function puede_administrar(): bool {
    return isset($_SESSION['perfil']) && $_SESSION['perfil'] === 'administrador';
}

function es_reportante(array $novedad): bool {
    return isset($_SESSION['usuario']) && $_SESSION['usuario'] === $novedad['usuario_reportante'];
}

/**
 * Devuelve true si el perfil tiene visibilidad total (ve todas las novedades).
 */
function es_supervision(): bool {
    return isset($_SESSION['perfil']) && permisos_tiene($_SESSION['perfil'], 'ver_todas');
}

/** Puede reportar cualquier tipo de novedad (sin el filtro por actor reportante). */
function puede_reportar_cualquiera(): bool {
    return isset($_SESSION['perfil']) && permisos_tiene($_SESSION['perfil'], 'reportar_cualquiera');
}

/** ¿La cuenta en sesión tiene la capacidad booleana de su permiso? */
function tiene_capacidad(string $capacidad): bool {
    return isset($_SESSION['perfil']) && permisos_tiene($_SESSION['perfil'], $capacidad);
}

/** Corta la petición si el permiso de la cuenta no incluye la capacidad. */
function exigir_capacidad(string $capacidad): void {
    if (!tiene_capacidad($capacidad)) {
        header('Location: ' . (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'pages' ? '../' : '') . 'index.php?error=permiso'); exit;
    }
}

/** Nivel de la cuenta en una sección de Parametrización: 'ninguno' | 'ver' | 'editar'. */
function nivel_parametrizacion(string $seccion): string {
    $cap = PARAM_SECCIONES[$seccion] ?? null;
    if (!$cap || !isset($_SESSION['perfil'])) return 'ninguno';
    return permisos_nivel($_SESSION['perfil'], $cap, !empty($_SESSION['puede_parametrizar']));
}

/** ¿Tiene acceso (ver o editar) a alguna sección de Parametrización? Decide si aparece la pestaña. */
function puede_parametrizar(): bool {
    foreach (array_keys(PARAM_SECCIONES) as $sec) if (nivel_parametrizacion($sec) !== 'ninguno') return true;
    return false;
}

/** Primera sección de Parametrización a la que la cuenta tiene acceso (para el enlace del menú). */
function parametrizacion_primera_seccion(): string {
    $paginas = ['operaciones' => 'admin_operaciones.php', 'tipologias' => 'admin_tipologias.php', 'novedades' => 'admin_novedades.php',
                'usuarios' => 'admin_usuarios.php', 'organizaciones' => 'admin_organizaciones.php', 'permisos' => 'admin_permisos.php'];
    foreach ($paginas as $sec => $pag) if (nivel_parametrizacion($sec) !== 'ninguno') return $pag;
    return 'admin_operaciones.php';
}

/**
 * Entrada a una pantalla de Parametrización: exige al menos «ver». Con solo «ver», cualquier envío (POST)
 * se rechaza y se vuelve a la misma pantalla con un aviso. Devuelve true si la cuenta puede editar.
 */
function verificar_parametrizacion(string $seccion = ''): bool {
    verificar_sesion();
    $nivel = $seccion === '' ? (puede_parametrizar() ? 'editar' : 'ninguno') : nivel_parametrizacion($seccion);
    if ($nivel === 'ninguno') {
        header('Location: ../index.php?error=permiso'); exit;
    }
    if ($nivel === 'ver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['flash_' . $seccion] = ['error', 'Tu permiso solo permite ver esta sección; no se guardó ningún cambio.'];
        header('Location: ' . basename($_SERVER['SCRIPT_NAME'])); exit;
    }
    $GLOBALS['_param_solo_ver'] = $nivel === 'ver';
    return $nivel === 'editar';
}