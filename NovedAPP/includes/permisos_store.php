<?php
// includes/permisos_store.php
// Capacidades de cada permiso del sistema (administrador, torre_control, gestor, reportante).
// Persistencia: storage/permisos.json  { perfil: { capacidades:{clave:valor}, actualizado_por, actualizado_en } }
// Si el archivo no existe o le falta una clave, se usan los valores por defecto de abajo
// (equivalen al comportamiento que el sistema tenía antes de volverlos editables).
//
// Tipos de capacidad:
//   'bool'  → true / false
//   'nivel' → 'ninguno' | 'ver' | 'editar'  (secciones de Parametrización)
//             Solo Torre de Control admite además 'cuenta': el nivel lo da la marca individual de cada cuenta
//             (puede_parametrizar): con la marca = 'editar', sin ella = 'ninguno'.

require_once __DIR__ . '/usuarios_store.php';

define('PERMISOS_FILE', __DIR__ . '/../storage/permisos.json');

const PERFILES_TODOS = ['administrador', 'torre_control', 'gestor', 'reportante'];

const CAPACIDADES_GRUPOS = [
    'menu'   => 'Pestañas del menú',
    'nov'    => 'Novedades',
    'param'  => 'Parametrización (por sección)',
];

const CAPACIDADES = [
    // ── Pestañas del menú ────────────────────────────────────────────────────
    'ver_dashboard' => [
        'grupo' => 'menu', 'tipo' => 'bool', 'label' => 'Ver el tablero (Dashboard / Mis Novedades)',
        'desc'  => 'Muestra la pestaña del tablero propio de su permiso y permite abrirla.',
    ],
    'crear_novedades' => [
        'grupo' => 'menu', 'tipo' => 'bool', 'label' => 'Crear novedades (pestaña «+ Nueva Novedad»)',
        'desc'  => 'Muestra la pestaña y permite registrar novedades nuevas.',
    ],
    // ── Novedades ────────────────────────────────────────────────────────────
    'ver_todas' => [
        'grupo' => 'nov', 'tipo' => 'bool', 'label' => 'Ver todas las novedades',
        'desc'  => 'Ve y exporta las novedades de todos, no solo las propias o dirigidas a su actor.',
    ],
    'reportar_cualquiera' => [
        'grupo' => 'nov', 'tipo' => 'bool', 'label' => 'Reportar cualquier tipo de novedad',
        'desc'  => 'Al registrar una novedad ve todos los tipos, sin el filtro por actor reportante.',
    ],
    'responder' => [
        'grupo' => 'nov', 'tipo' => 'bool', 'label' => 'Responder en la conversación',
        'desc'  => 'Responde las novedades dirigidas a su actor (o a cualquiera, si además tiene «Responder cualquier novedad»).',
    ],
    'responder_todas' => [
        'grupo' => 'nov', 'tipo' => 'bool', 'label' => 'Responder cualquier novedad',
        'desc'  => 'Amplía «Responder» y «Cambiar estado» a novedades de cualquier actor.',
    ],
    'cambiar_estado' => [
        'grupo' => 'nov', 'tipo' => 'bool', 'label' => 'Cambiar el estado / resolver',
        'desc'  => 'Marca novedades como resueltas y cambia su estado (solo en las que puede responder).',
    ],
    // ── Parametrización ──────────────────────────────────────────────────────
    'param_operaciones'    => ['grupo' => 'param', 'tipo' => 'nivel', 'label' => 'Tipos de Operación',        'desc' => ''],
    'param_tipologias'     => ['grupo' => 'param', 'tipo' => 'nivel', 'label' => 'Tipologías',                'desc' => ''],
    'param_novedades'      => ['grupo' => 'param', 'tipo' => 'nivel', 'label' => 'Tipos de Novedad',          'desc' => ''],
    'param_usuarios'       => ['grupo' => 'param', 'tipo' => 'nivel', 'label' => 'Usuarios',                  'desc' => ''],
    'param_organizaciones' => ['grupo' => 'param', 'tipo' => 'nivel', 'label' => 'Fincas y Organizaciones',   'desc' => ''],
    'param_permisos'       => ['grupo' => 'param', 'tipo' => 'nivel', 'label' => 'Permisos',                  'desc' => ''],
];

/** Secciones de Parametrización → clave de capacidad. */
const PARAM_SECCIONES = [
    'operaciones' => 'param_operaciones', 'tipologias' => 'param_tipologias', 'novedades' => 'param_novedades',
    'usuarios' => 'param_usuarios', 'organizaciones' => 'param_organizaciones', 'permisos' => 'param_permisos',
];

const NIVELES = ['ninguno' => 0, 'ver' => 1, 'editar' => 2];

/** Valores por defecto = comportamiento original. */
function permisos_defecto(string $perfil): array {
    $niv = fn(string $n) => array_fill_keys(array_values(PARAM_SECCIONES), $n);
    switch ($perfil) {
        case 'administrador':
            return ['ver_dashboard' => true, 'crear_novedades' => true, 'ver_todas' => true, 'reportar_cualquiera' => true,
                    'responder' => true, 'responder_todas' => true, 'cambiar_estado' => true] + $niv('editar');
        case 'torre_control':
            return ['ver_dashboard' => true, 'crear_novedades' => true, 'ver_todas' => true, 'reportar_cualquiera' => true,
                    'responder' => true, 'responder_todas' => true, 'cambiar_estado' => true] + $niv('cuenta');
        case 'gestor':
            return ['ver_dashboard' => true, 'crear_novedades' => true, 'ver_todas' => false, 'reportar_cualquiera' => false,
                    'responder' => true, 'responder_todas' => false, 'cambiar_estado' => true] + $niv('ninguno');
        default: // reportante
            return ['ver_dashboard' => true, 'crear_novedades' => true, 'ver_todas' => false, 'reportar_cualquiera' => false,
                    'responder' => false, 'responder_todas' => false, 'cambiar_estado' => false] + $niv('ninguno');
    }
}

/** Capacidades que no se pueden quitar (evitan que el sistema quede sin acceso de administración). */
const CAPACIDADES_PROTEGIDAS = [
    'administrador' => ['ver_dashboard' => true, 'param_usuarios' => 'editar', 'param_permisos' => 'editar'],
];

function permisos_leer_archivo(): array {
    if (!file_exists(PERMISOS_FILE)) return [];
    $d = json_decode((string)file_get_contents(PERMISOS_FILE), true);
    return is_array($d) ? $d : [];
}

/** Capacidades efectivas (en bruto) de un permiso: defecto + lo guardado + protegidas. Puede incluir 'cuenta'. */
function permisos_capacidades(string $perfil): array {
    $cache = $GLOBALS['_permisos_cache'] ??= permisos_leer_archivo();   // una lectura por petición; se limpia al guardar
    $res = permisos_defecto($perfil);
    foreach (CAPACIDADES as $clave => $def) {
        if (!array_key_exists($clave, $cache[$perfil]['capacidades'] ?? [])) continue;
        $v = $cache[$perfil]['capacidades'][$clave];
        if ($def['tipo'] === 'bool') $res[$clave] = (bool)$v;
        elseif (isset(NIVELES[$v]) || ($v === 'cuenta' && $perfil === 'torre_control')) $res[$clave] = $v;
    }
    foreach (CAPACIDADES_PROTEGIDAS[$perfil] ?? [] as $clave => $valor) $res[$clave] = $valor;
    return $res;
}

/** ¿El permiso tiene la capacidad booleana? */
function permisos_tiene(string $perfil, string $capacidad): bool {
    return (permisos_capacidades($perfil)[$capacidad] ?? false) === true;
}

/** Nivel efectivo de una sección para una cuenta: resuelve el modo 'cuenta' con la marca individual. */
function permisos_nivel(string $perfil, string $capacidad, bool $marca_cuenta = false): string {
    $v = permisos_capacidades($perfil)[$capacidad] ?? 'ninguno';
    if ($v === 'cuenta') return $marca_cuenta ? 'editar' : 'ninguno';
    return isset(NIVELES[$v]) ? $v : 'ninguno';
}

/** Rango comparable de un valor de capacidad (para «no otorgar más de lo que se tiene»). */
function permisos_rango($valor, bool $marca_cuenta = false): int {
    if ($valor === true) return 1;
    if ($valor === false) return 0;
    if ($valor === 'cuenta') return $marca_cuenta ? 2 : 0;
    return NIVELES[$valor] ?? 0;
}

function permisos_meta(string $perfil): array {
    $d = permisos_leer_archivo();
    return ['por' => $d[$perfil]['actualizado_por'] ?? '', 'en' => $d[$perfil]['actualizado_en'] ?? ''];
}

/**
 * Guarda las capacidades de un permiso.
 * $editor = ['perfil'=>…, 'usuario'=>…, 'marca'=>bool]. $entrada = [clave => valor] llegado del formulario.
 * @return array [bool ok, string mensaje]
 */
function permisos_guardar(string $perfil, array $entrada, array $editor): array {
    if (!isset(PERMISOS_SISTEMA[$perfil])) return [false, 'El permiso no existe.'];
    $perfil_editor = $editor['perfil'] ?? '';
    $marca = !empty($editor['marca']);
    $es_admin = $perfil_editor === 'administrador';

    // Quien no es administrador solo toca permisos de menor rango.
    if (!$es_admin && in_array($perfil, ['administrador', 'torre_control'], true)) {
        return [false, 'Solo un administrador puede modificar los permisos Administrador y Torre de Control.'];
    }

    $mias   = permisos_capacidades($perfil_editor);
    $actual = permisos_capacidades($perfil);
    $nuevas = [];
    foreach (CAPACIDADES as $clave => $def) {
        if ($def['tipo'] === 'bool') {
            $v = ($entrada[$clave] ?? '0') === '1';
        } else {
            $raw = (string)($entrada[$clave] ?? 'ninguno');
            $v = isset(NIVELES[$raw]) ? $raw : (($raw === 'cuenta' && $perfil === 'torre_control') ? 'cuenta' : 'ninguno');
        }
        if (array_key_exists($clave, CAPACIDADES_PROTEGIDAS[$perfil] ?? [])) $v = CAPACIDADES_PROTEGIDAS[$perfil][$clave];
        // Nadie concede (ni retira) más de lo que su propio permiso tiene: se conserva el valor actual.
        if (!$es_admin && permisos_rango($v) !== permisos_rango($actual[$clave])
            && (permisos_rango($v) > permisos_rango($mias[$clave] ?? false, $marca) || permisos_rango($actual[$clave]) > permisos_rango($mias[$clave] ?? false, $marca))) {
            $nuevas[$clave] = $actual[$clave];
            continue;
        }
        $nuevas[$clave] = $v;
    }

    $d = permisos_leer_archivo();
    $d[$perfil] = [
        'capacidades'     => $nuevas,
        'actualizado_por' => $editor['usuario'] ?? '',
        'actualizado_en'  => date('Y-m-d H:i:s'),
    ];
    $json = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents(PERMISOS_FILE, $json, LOCK_EX) === false) return [false, 'No se pudo guardar el archivo de permisos.'];
    unset($GLOBALS['_permisos_cache']);
    return [true, 'Permiso «' . PERMISOS_SISTEMA[$perfil]['label'] . '» actualizado. Se aplica en la siguiente petición de cada usuario.'];
}
