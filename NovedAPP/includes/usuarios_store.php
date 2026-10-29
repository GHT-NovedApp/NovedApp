<?php
require_once __DIR__ . '/config_regional.php';
// includes/usuarios_store.php
// Persistencia de usuarios (JSON). Se siembra una sola vez desde data/usuarios.php.
//
// Cada usuario (clave = nombre de usuario):
//   nombre · perfil (PERMISO DEL SISTEMA: administrador | torre_control | gestor | reportante)
//   rol_organizacional (cargo real en la empresa) · tipo_actor · organizacion
//   activo (bool) · puede_parametrizar (bool, solo aplica a torre_control)
//   password (texto, solo cuentas sembradas) | password_hash (cuentas con clave cambiada o creadas aquí)

define('USUARIOS_FILE', __DIR__ . '/../storage/usuarios.json');

/** Permisos de la aplicación (antes llamados "Rol"). */
const PERMISOS_SISTEMA = [
    'administrador' => ['label' => 'Administrador',      'desc' => 'Acceso total al sistema y a la parametrización.'],
    'torre_control' => ['label' => 'Torre de Control',   'desc' => 'Visualización global y gestión de alertas. Parametriza solo si el administrador se lo otorga.'],
    'gestor'        => ['label' => 'Gestor / Respondiente', 'desc' => 'Responde y gestiona novedades en el flujo de conversación.'],
    'reportante'    => ['label' => 'Reportante',         'desc' => 'Crea y hace seguimiento a sus propias novedades.'],
];

function usuarios_escribir(array $todos): bool {
    $json = json_encode($todos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false && file_put_contents(USUARIOS_FILE, $json, LOCK_EX) !== false;
}

function usuarios_normalizar(array $u): array {
    return $u + [
        'rol_organizacional' => '', 'puede_parametrizar' => false,
        'activo' => true, 'tipo_actor' => '', 'organizacion' => '', 'fecha_creacion' => '',
    ];
}

/** Todos los usuarios, clave = nombre de usuario. */
function usuarios_todos(): array {
    if (!file_exists(USUARIOS_FILE)) {
        $usuarios = [];
        include __DIR__ . '/../data/usuarios.php';
        $semilla = [];
        foreach ($usuarios as $nombre => $u) {
            $u['fecha_creacion'] = date('Y-m-d H:i:s');
            $semilla[$nombre] = usuarios_normalizar($u);
        }
        usuarios_escribir($semilla);
        return $semilla;
    }
    $todos = json_decode((string)file_get_contents(USUARIOS_FILE), true);
    if (!is_array($todos)) return [];
    return array_map('usuarios_normalizar', $todos);
}

function usuarios_obtener(string $usuario): ?array {
    return usuarios_todos()[$usuario] ?? null;
}

/** Comprueba la contraseña contra hash o, en cuentas sembradas, contra el texto original. */
function usuarios_clave_valida(array $u, string $clave): bool {
    if (!empty($u['password_hash'])) return password_verify($clave, $u['password_hash']);
    return isset($u['password']) && hash_equals((string)$u['password'], $clave);
}

/** ¿El nombre de usuario aparece en novedades o notificaciones guardadas? (impide renombrarlo) */
function usuarios_tiene_historial(string $usuario): bool {
    $needle = json_encode($usuario, JSON_UNESCAPED_UNICODE); // "usuario" con comillas
    foreach (['novedades.json', 'notificaciones.json', 'novedades_custom.json'] as $f) {
        $ruta = __DIR__ . '/../storage/' . $f;
        if (file_exists($ruta) && strpos((string)file_get_contents($ruta), $needle) !== false) return true;
    }
    return false;
}

function usuarios_administradores_activos(array $todos): int {
    return count(array_filter($todos, fn($u) => $u['perfil'] === 'administrador' && $u['activo']));
}

function usuarios_validar_clave_nueva(string $clave): ?string {
    if (mb_strlen($clave) < 6) return 'La contraseña debe tener al menos 6 caracteres.';
    if (mb_strlen($clave) > 72) return 'La contraseña no puede superar los 72 caracteres.';
    return null;
}

/**
 * Crea (original = null) o actualiza un usuario.
 * $in: usuario, nombre, rol_organizacional, tipo_actor, organizacion, perfil, puede_parametrizar, password (solo al crear)
 * $quien_edita: array del usuario en sesión; solo un administrador puede cambiar permisos del sistema.
 * Devuelve [bool ok, string mensaje].
 */
function usuarios_guardar(array $in, ?string $original, array $quien_edita): array {
    $es_admin = ($quien_edita['perfil'] ?? '') === 'administrador';
    $usuario = trim((string)($in['usuario'] ?? ''));
    $nombre  = trim(preg_replace('/\s+/', ' ', (string)($in['nombre'] ?? '')));
    $cargo   = trim(preg_replace('/\s+/', ' ', (string)($in['rol_organizacional'] ?? '')));
    $actor   = trim((string)($in['tipo_actor'] ?? ''));
    $org     = trim((string)($in['organizacion'] ?? ''));
    $perfil  = (string)($in['perfil'] ?? '');
    if ($perfil === '' && !$es_admin && $original !== null) {
        $perfil = (string)(usuarios_obtener($original)['perfil'] ?? ''); // el select va deshabilitado para quien no es administrador
    }

    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $usuario)) return [false, 'El usuario debe tener entre 3 y 30 caracteres: minúsculas, números, punto, guion o guion bajo.'];
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 80) return [false, 'El nombre debe tener entre 3 y 80 caracteres.'];
    if (mb_strlen($cargo) > 80) return [false, 'El rol en la empresa no puede superar los 80 caracteres.'];
    if ($actor === '') return [false, 'Selecciona el tipo de actor.'];
    if ($org === '') return [false, 'Selecciona la organización o finca.'];
    if (!isset(PERMISOS_SISTEMA[$perfil])) return [false, 'Selecciona los permisos del sistema.'];

    $todos = usuarios_todos();
    $previo = $original !== null ? ($todos[$original] ?? null) : null;
    if ($original !== null && !$previo) return [false, 'El usuario no existe.'];
    if ($usuario !== $original && isset($todos[$usuario])) return [false, "El usuario «{$usuario}» ya existe."];

    if (!$es_admin) {
        // Quien parametriza sin ser administrador no puede tocar permisos ni cuentas privilegiadas.
        if ($previo && in_array($previo['perfil'], ['administrador', 'torre_control'], true)) return [false, 'Solo un administrador puede editar cuentas de administrador o torre de control.'];
        if (in_array($perfil, ['administrador', 'torre_control'], true)) return [false, 'Solo un administrador puede asignar esos permisos del sistema.'];
    }

    $parametriza = $perfil === 'torre_control' && !empty($in['puede_parametrizar']);
    if ($previo && $es_admin) {
        if ($previo['perfil'] === 'administrador' && $perfil !== 'administrador' && usuarios_administradores_activos($todos) <= 1 && $previo['activo']) {
            return [false, 'Debe quedar al menos un administrador activo.'];
        }
    }
    if ($previo && $usuario !== $original && usuarios_tiene_historial($original)) {
        return [false, 'No se puede cambiar el nombre de usuario: ya tiene novedades o notificaciones asociadas.'];
    }

    if ($previo === null) {
        $err = usuarios_validar_clave_nueva((string)($in['password'] ?? ''));
        if ($err) return [false, $err];
        $todos[$usuario] = [
            'password_hash' => password_hash((string)$in['password'], PASSWORD_DEFAULT),
            'nombre' => $nombre, 'rol_organizacional' => $cargo, 'perfil' => $perfil,
            'tipo_actor' => $actor, 'organizacion' => $org, 'activo' => true,
            'puede_parametrizar' => $parametriza, 'fecha_creacion' => date('Y-m-d H:i:s'),
        ];
        return usuarios_escribir($todos) ? [true, "Usuario «{$usuario}» creado correctamente."] : [false, 'No se pudo guardar. Revisa los permisos de la carpeta storage.'];
    }

    $nuevo = $previo;
    $nuevo['nombre'] = $nombre; $nuevo['rol_organizacional'] = $cargo;
    $nuevo['tipo_actor'] = $actor; $nuevo['organizacion'] = $org;
    if ($es_admin) { $nuevo['perfil'] = $perfil; $nuevo['puede_parametrizar'] = $parametriza; }
    if ($usuario !== $original) { unset($todos[$original]); }
    $todos[$usuario] = $nuevo;
    return usuarios_escribir($todos) ? [true, "Usuario «{$usuario}» actualizado correctamente."] : [false, 'No se pudo guardar. Revisa los permisos de la carpeta storage.'];
}

function usuarios_cambiar_password(string $usuario, string $clave, array $quien_edita): array {
    $todos = usuarios_todos();
    if (!isset($todos[$usuario])) return [false, 'El usuario no existe.'];
    if (($quien_edita['perfil'] ?? '') !== 'administrador' && in_array($todos[$usuario]['perfil'], ['administrador', 'torre_control'], true)) {
        return [false, 'Solo un administrador puede cambiar la contraseña de esta cuenta.'];
    }
    $err = usuarios_validar_clave_nueva($clave);
    if ($err) return [false, $err];
    $todos[$usuario]['password_hash'] = password_hash($clave, PASSWORD_DEFAULT);
    unset($todos[$usuario]['password']); // deja de existir la clave en texto plano
    return usuarios_escribir($todos) ? [true, "Contraseña de «{$usuario}» actualizada."] : [false, 'No se pudo guardar el cambio.'];
}

function usuarios_cambiar_estado(string $usuario, array $quien_edita): array {
    $todos = usuarios_todos();
    if (!isset($todos[$usuario])) return [false, 'El usuario no existe.'];
    $u = $todos[$usuario];
    if ($usuario === ($quien_edita['usuario'] ?? '')) return [false, 'No puedes inactivar tu propia cuenta.'];
    if (($quien_edita['perfil'] ?? '') !== 'administrador' && in_array($u['perfil'], ['administrador', 'torre_control'], true)) {
        return [false, 'Solo un administrador puede cambiar el estado de esta cuenta.'];
    }
    if ($u['activo'] && $u['perfil'] === 'administrador' && usuarios_administradores_activos($todos) <= 1) {
        return [false, 'Debe quedar al menos un administrador activo.'];
    }
    $todos[$usuario]['activo'] = !$u['activo'];
    $txt = $todos[$usuario]['activo'] ? 'activado' : 'inactivado';
    return usuarios_escribir($todos) ? [true, "Usuario «{$usuario}» {$txt}."] : [false, 'No se pudo guardar el cambio.'];
}

/** Si cambia el nombre de una organización, actualiza el texto guardado en sus usuarios. */
function usuarios_renombrar_organizacion(string $tipo_actor, string $antes, string $despues): int {
    $todos = usuarios_todos(); $n = 0;
    foreach ($todos as $k => $u) {
        if ($u['tipo_actor'] === $tipo_actor && $u['organizacion'] === $antes) { $todos[$k]['organizacion'] = $despues; $n++; }
    }
    if ($n) usuarios_escribir($todos);
    return $n;
}

/** Nombres de usuario que aparecen en novedades o notificaciones (no se pueden renombrar). Lee los archivos una sola vez. */
function usuarios_con_historial(array $nombres): array {
    $texto = '';
    foreach (['novedades.json', 'notificaciones.json', 'novedades_custom.json'] as $f) {
        $ruta = __DIR__ . '/../storage/' . $f;
        if (file_exists($ruta)) $texto .= (string)file_get_contents($ruta);
    }
    $con = [];
    foreach ($nombres as $n) if (strpos($texto, json_encode($n, JSON_UNESCAPED_UNICODE)) !== false) $con[] = $n;
    return $con;
}
