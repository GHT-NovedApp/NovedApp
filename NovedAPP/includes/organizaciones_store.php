<?php
// includes/organizaciones_store.php
// Persistencia de fincas y organizaciones (JSON). Se siembra una sola vez desde data/organizaciones.php.
// Registro: id · tipo_actor · nombre · ubicacion · activo (bool)

require_once __DIR__ . '/usuarios_store.php';
define('ORGANIZACIONES_FILE', __DIR__ . '/../storage/organizaciones.json');

function organizaciones_escribir(array $lista): bool {
    $json = json_encode(array_values($lista), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false && file_put_contents(ORGANIZACIONES_FILE, $json, LOCK_EX) !== false;
}

function organizaciones_todas(): array {
    if (!file_exists(ORGANIZACIONES_FILE)) {
        $organizaciones = [];
        include __DIR__ . '/../data/organizaciones.php';
        $lista = []; $id = 0;
        foreach ($organizaciones as $tipo => $items) {
            foreach ($items as $o) {
                $lista[] = ['id' => ++$id, 'tipo_actor' => $tipo, 'nombre' => $o['nombre'], 'ubicacion' => $o['ubicacion'] ?? '', 'activo' => true];
            }
        }
        // Las organizaciones que ya usan los usuarios sembrados también deben existir como registro editable.
        $usuarios = [];
        include __DIR__ . '/../data/usuarios.php';
        foreach ($usuarios as $u) {
            $existe = false;
            foreach ($lista as $o) { if ($o['tipo_actor'] === $u['tipo_actor'] && $o['nombre'] === $u['organizacion']) { $existe = true; break; } }
            if (!$existe && $u['organizacion'] !== '') $lista[] = ['id' => ++$id, 'tipo_actor' => $u['tipo_actor'], 'nombre' => $u['organizacion'], 'ubicacion' => '', 'activo' => true];
        }
        organizaciones_escribir($lista);
        return $lista;
    }
    $lista = json_decode((string)file_get_contents(ORGANIZACIONES_FILE), true);
    return is_array($lista) ? array_values($lista) : [];
}

function organizaciones_obtener(int $id): ?array {
    foreach (organizaciones_todas() as $o) if ((int)$o['id'] === $id) return $o;
    return null;
}

/** Tipos de actor conocidos (los de las organizaciones + los de los usuarios), ordenados. */
function organizaciones_tipos_actor(): array {
    $tipos = array_column(organizaciones_todas(), 'tipo_actor');
    foreach (usuarios_todos() as $u) $tipos[] = $u['tipo_actor'];
    $tipos = array_values(array_unique(array_filter($tipos)));
    sort($tipos, SORT_NATURAL | SORT_FLAG_CASE);
    return $tipos;
}

/** Mapa tipo_actor => [organizaciones activas], para el selector dependiente. */
function organizaciones_por_tipo_activas(): array {
    $mapa = [];
    foreach (organizaciones_todas() as $o) {
        if ($o['activo']) $mapa[$o['tipo_actor']][] = ['nombre' => $o['nombre'], 'ubicacion' => $o['ubicacion']];
    }
    return $mapa;
}

/** Crea (id = null) o actualiza. Devuelve [bool ok, string mensaje]. */
function organizaciones_guardar(array $in, ?int $id): array {
    $tipo   = trim((string)($in['tipo_actor'] ?? ''));
    $nombre = trim(preg_replace('/\s+/', ' ', (string)($in['nombre'] ?? '')));
    $ubi    = trim(preg_replace('/\s+/', ' ', (string)($in['ubicacion'] ?? '')));
    if ($tipo === '' || !preg_match('/^[A-Za-z0-9_]{1,30}$/', $tipo)) return [false, 'Indica un tipo de actor válido (letras, números o guion bajo).'];
    if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) return [false, 'El nombre debe tener entre 2 y 100 caracteres.'];
    if (mb_strlen($ubi) > 60) return [false, 'La ubicación no puede superar los 60 caracteres.'];

    $lista = organizaciones_todas();
    foreach ($lista as $o) {
        if ($id !== null && (int)$o['id'] === $id) continue;
        if ($o['tipo_actor'] === $tipo && mb_strtolower($o['nombre']) === mb_strtolower($nombre) && mb_strtolower($o['ubicacion']) === mb_strtolower($ubi)) {
            return [false, "Ya existe «{$nombre}» con ese tipo de actor y ubicación."];
        }
    }
    if ($id === null) {
        $lista[] = ['id' => (int)max(array_merge([0], array_column($lista, 'id'))) + 1, 'tipo_actor' => $tipo, 'nombre' => $nombre, 'ubicacion' => $ubi, 'activo' => true];
        return organizaciones_escribir($lista) ? [true, "Organización «{$nombre}» creada correctamente."] : [false, 'No se pudo guardar. Revisa los permisos de la carpeta storage.'];
    }
    foreach ($lista as &$o) {
        if ((int)$o['id'] !== $id) continue;
        $antes = ['tipo' => $o['tipo_actor'], 'nombre' => $o['nombre']];
        $o['tipo_actor'] = $tipo; $o['nombre'] = $nombre; $o['ubicacion'] = $ubi;
        unset($o);
        if (!organizaciones_escribir($lista)) return [false, 'No se pudo guardar. Revisa los permisos de la carpeta storage.'];
        $movidos = ($antes['tipo'] !== $tipo || $antes['nombre'] !== $nombre) ? usuarios_renombrar_organizacion($antes["tipo"], $antes["nombre"], $nombre) : 0;
        $extra = $movidos ? " Se actualizó también en {$movidos} usuario(s)." : '';
        return [true, "Organización «{$nombre}» actualizada correctamente.{$extra}"];
    }
    return [false, 'La organización no existe.'];
}

function organizaciones_cambiar_estado(int $id): array {
    $lista = organizaciones_todas();
    foreach ($lista as &$o) {
        if ((int)$o['id'] !== $id) continue;
        $o['activo'] = !$o['activo'];
        $txt = $o['activo'] ? 'activada' : 'inactivada'; $nombre = $o['nombre'];
        unset($o);
        return organizaciones_escribir($lista) ? [true, "«{$nombre}» fue {$txt}."] : [false, 'No se pudo guardar el cambio.'];
    }
    return [false, 'La organización no existe.'];
}
