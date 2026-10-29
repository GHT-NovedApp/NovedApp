<?php
// includes/tipologias.php
// Catálogo parametrizable de Tipologías (persistencia en JSON, como el resto del sistema).
//
// Registro: id (int) · codigo (sigla que guardan las novedades: OPR, SEG, SST…) · nombre · descripcion
//           estado (1 activa / 0 inactiva) · fecha_creacion
//
// `codigo` es la clave con la que cada tipo de novedad y cada novedad registrada guardan su tipología.
// Por eso solo se puede cambiar mientras ninguna novedad lo use.

define('TIPOLOGIAS_FILE', __DIR__ . '/../storage/tipologias.json');

function tipologias_semilla(): array {
    $ahora = date('Y-m-d H:i:s');
    $base = [
        ['OPR', 'Operativa'], ['SEG', 'Seguridad'], ['SST', 'Salud y Seguridad'],
        ['BDS', 'Breakdown Services'], ['GC', 'Gestión de Calidad'], ['OSL', 'Operaciones Logísticas'],
    ];
    $lista = [];
    foreach ($base as $i => [$codigo, $nombre]) {
        $lista[] = ['id' => $i + 1, 'codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => '', 'estado' => 1, 'fecha_creacion' => $ahora];
    }
    return $lista;
}

function tipologias_escribir(array $lista): bool {
    $json = json_encode(array_values($lista), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false && file_put_contents(TIPOLOGIAS_FILE, $json, LOCK_EX) !== false;
}

/** Todas las tipologías. Si el archivo no existe lo crea con las 6 originales. */
function tipologias_todas(): array {
    if (!file_exists(TIPOLOGIAS_FILE)) {
        $semilla = tipologias_semilla();
        tipologias_escribir($semilla);
        return $semilla;
    }
    $lista = json_decode((string)file_get_contents(TIPOLOGIAS_FILE), true);
    return is_array($lista) ? array_values($lista) : tipologias_semilla();
}

function tipologias_activas(): array {
    return array_values(array_filter(tipologias_todas(), fn($t) => (int)($t['estado'] ?? 0) === 1));
}

function tipologias_obtener(int $id): ?array {
    foreach (tipologias_todas() as $t) if ((int)$t['id'] === $id) return $t;
    return null;
}

/** Texto "SST — Salud y Seguridad" para mostrar; si el código ya no existe, devuelve el código. */
function tipologias_etiqueta(string $codigo): string {
    static $mapa = null;
    if ($mapa === null) { $mapa = []; foreach (tipologias_todas() as $t) $mapa[$t['codigo']] = $t['codigo'] . ' — ' . $t['nombre']; }
    return $mapa[$codigo] ?? $codigo;
}

/** Cuántos tipos de novedad y novedades registradas usan este código. */
function tipologias_en_uso(string $codigo): int {
    $usos = 0;
    foreach ([__DIR__ . '/../storage/novedades.json', __DIR__ . '/../storage/novedades_custom.json'] as $archivo) {
        if (!file_exists($archivo)) continue;
        $datos = json_decode((string)file_get_contents($archivo), true);
        if (!is_array($datos)) continue;
        foreach ($datos as $n) if (is_array($n) && in_array($codigo, novedad_tipologias($n), true)) $usos++;
    }
    return $usos;
}

/** Crea (id = null) o actualiza. Devuelve [bool ok, string mensaje]. */
function tipologias_guardar(array $in, ?int $id = null): array {
    $codigo = strtoupper(trim((string)($in['codigo'] ?? '')));
    $nombre = trim(preg_replace('/\s+/', ' ', (string)($in['nombre'] ?? '')));
    $desc   = trim((string)($in['descripcion'] ?? ''));
    $estado = ((string)($in['estado'] ?? '1') === '0') ? 0 : 1;

    if (!preg_match('/^[A-Z0-9_]{2,10}$/', $codigo)) return [false, 'La sigla debe tener entre 2 y 10 caracteres: letras, números o guion bajo (ej. SST).'];
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 60) return [false, 'El nombre debe tener entre 3 y 60 caracteres.'];
    if (mb_strlen($desc) > 300) return [false, 'La descripción no puede superar los 300 caracteres.'];

    $lista = tipologias_todas();
    $previo = null;
    foreach ($lista as $t) {
        if ($id !== null && (int)$t['id'] === $id) { $previo = $t; continue; }
        if (strcasecmp($t['codigo'], $codigo) === 0) return [false, "Ya existe una tipología con la sigla {$codigo}."];
        if (mb_strtolower($t['nombre']) === mb_strtolower($nombre)) return [false, "Ya existe una tipología llamada «{$nombre}»."];
    }
    if ($id !== null && !$previo) return [false, 'La tipología no existe.'];

    if ($id === null) {
        $lista[] = [
            'id' => (int)max(array_merge([0], array_column($lista, 'id'))) + 1,
            'codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => $desc, 'estado' => $estado,
            'fecha_creacion' => date('Y-m-d H:i:s'),
        ];
        return tipologias_escribir($lista) ? [true, "Tipología «{$nombre}» creada correctamente."] : [false, 'No se pudo guardar. Revisa los permisos de la carpeta storage.'];
    }

    if ($codigo !== $previo['codigo'] && tipologias_en_uso($previo['codigo']) > 0) {
        return [false, "No se puede cambiar la sigla de {$previo['codigo']}: ya la usan tipos de novedad o novedades registradas. Puedes editar el nombre y la descripción."];
    }
    if ($estado === 0 && (int)$previo['estado'] === 1 && count(tipologias_activas()) <= 1) {
        return [false, 'Debe quedar al menos una tipología activa.'];
    }
    foreach ($lista as &$t) {
        if ((int)$t['id'] !== $id) continue;
        $t['codigo'] = $codigo; $t['nombre'] = $nombre; $t['descripcion'] = $desc; $t['estado'] = $estado;
        break;
    }
    unset($t);
    return tipologias_escribir($lista) ? [true, "Tipología «{$nombre}» actualizada correctamente."] : [false, 'No se pudo guardar. Revisa los permisos de la carpeta storage.'];
}

function tipologias_cambiar_estado(int $id): array {
    $lista = tipologias_todas();
    foreach ($lista as &$t) {
        if ((int)$t['id'] !== $id) continue;
        if ((int)$t['estado'] === 1 && count(tipologias_activas()) <= 1) return [false, 'Debe quedar al menos una tipología activa.'];
        $t['estado'] = (int)$t['estado'] === 1 ? 0 : 1;
        $texto = $t['estado'] === 1 ? 'activada' : 'inactivada';
        $nombre = $t['nombre'];
        unset($t);
        return tipologias_escribir($lista) ? [true, "«{$nombre}» fue {$texto}."] : [false, 'No se pudo guardar el cambio.'];
    }
    return [false, 'La tipología no existe.'];
}

/** Elimina solo si ningún tipo de novedad ni novedad la usa. */
function tipologias_eliminar(int $id): array {
    $t = tipologias_obtener($id);
    if (!$t) return [false, 'La tipología no existe.'];
    if ((int)$t['estado'] === 1 && count(tipologias_activas()) <= 1) return [false, 'Debe quedar al menos una tipología activa.'];
    $usos = tipologias_en_uso($t['codigo']);
    if ($usos > 0) return [false, "No se puede eliminar «{$t['nombre']}»: la usan {$usos} tipo(s) de novedad o novedad(es). Inactívala para ocultarla sin perder el historial."];
    $lista = array_values(array_filter(tipologias_todas(), fn($x) => (int)$x['id'] !== $id));
    return tipologias_escribir($lista) ? [true, "«{$t['nombre']}» fue eliminada."] : [false, 'No se pudo guardar el cambio.'];
}

/** Tipologías de un tipo de novedad o novedad: acepta el formato nuevo (lista) y el antiguo (una sola). */
function novedad_tipologias(array $n): array {
    $lista = $n['tipologias'] ?? null;
    if (is_array($lista) && $lista) return array_values(array_unique(array_filter(array_map('strval', $lista))));
    $una = (string)($n['tipologia'] ?? '');
    return $una !== '' ? [$una] : [];
}
