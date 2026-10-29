<?php
// includes/tipos_operacion.php
// Catálogo parametrizable de Tipos de Operación (persistencia en JSON, igual que el resto del sistema).
//
// Estructura de cada registro (equivale a la tabla `tipos_operacion`):
//   id (int, PK) · codigo (sigla, única) · clave (identificador estable que se guarda en cada novedad)
//   nombre · descripcion · estado (1 activo / 0 inactivo) · fecha_creacion (Y-m-d H:i:s)
//
// `clave` NO cambia al editar el nombre: las novedades ya registradas guardan 'exportacion', 'importacion', 'mvcs'…
// y seguirían asociadas al tipo correcto aunque se renombre o se cambie su sigla.

define('TIPOS_OPERACION_FILE', __DIR__ . '/../storage/tipos_operacion.json');

function tipos_operacion_semilla(): array {
    $ahora = date('Y-m-d H:i:s');
    return [
        ['id'=>1, 'codigo'=>'EXP',  'clave'=>'exportacion', 'nombre'=>'Exportación', 'descripcion'=>'', 'estado'=>1, 'fecha_creacion'=>$ahora],
        ['id'=>2, 'codigo'=>'IMP',  'clave'=>'importacion', 'nombre'=>'Importación', 'descripcion'=>'', 'estado'=>1, 'fecha_creacion'=>$ahora],
        ['id'=>3, 'codigo'=>'MVCS', 'clave'=>'mvcs',        'nombre'=>'MVCS',        'descripcion'=>'', 'estado'=>1, 'fecha_creacion'=>$ahora],
    ];
}

function tipos_operacion_escribir(array $lista): bool {
    $json = json_encode(array_values($lista), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false && file_put_contents(TIPOS_OPERACION_FILE, $json, LOCK_EX) !== false;
}

/** Todos los tipos (activos e inactivos). Si el archivo no existe lo crea con los 3 tipos originales. */
function tipos_operacion_todos(): array {
    if (!file_exists(TIPOS_OPERACION_FILE)) {
        $semilla = tipos_operacion_semilla();
        tipos_operacion_escribir($semilla);
        return $semilla;
    }
    $lista = json_decode((string)file_get_contents(TIPOS_OPERACION_FILE), true);
    return is_array($lista) ? array_values($lista) : tipos_operacion_semilla();
}

/** Solo los tipos con estado = 1, listos para selectores y filtros. */
function tipos_operacion_activos(): array {
    return array_values(array_filter(tipos_operacion_todos(), fn($t) => (int)($t['estado'] ?? 0) === 1));
}

function tipos_operacion_obtener(int $id): ?array {
    foreach (tipos_operacion_todos() as $t) if ((int)$t['id'] === $id) return $t;
    return null;
}

/** Mapa clave => nombre (incluye inactivos, para mostrar datos históricos). */
function tipos_operacion_nombres(): array {
    $m = [];
    foreach (tipos_operacion_todos() as $t) $m[$t['clave']] = $t['nombre'];
    return $m;
}

/** Activos + el seleccionado actualmente aunque esté inactivo (para no perder un filtro vigente). */
function tipos_operacion_para_filtro(string $seleccionada = ''): array {
    $lista = tipos_operacion_activos();
    if ($seleccionada !== '' && !in_array($seleccionada, array_column($lista, 'clave'), true)) {
        foreach (tipos_operacion_todos() as $t) if ($t['clave'] === $seleccionada) { $lista[] = $t; break; }
    }
    return $lista;
}

function tipos_operacion_slug(string $texto): string {
    $t = strtr($texto, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n']);
    $t = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $t), '_'));
    return $t !== '' ? $t : 'operacion';
}

/** Cuántas novedades (registradas o parametrizadas) usan esta clave. */
function tipos_operacion_en_uso(string $clave): int {
    $usos = 0;
    foreach ([__DIR__ . '/../storage/novedades.json', __DIR__ . '/../storage/novedades_custom.json'] as $archivo) {
        if (!file_exists($archivo)) continue;
        $datos = json_decode((string)file_get_contents($archivo), true);
        if (!is_array($datos)) continue;
        foreach ($datos as $n) {
            if (is_array($n) && in_array($clave, (array)($n['tipo_operacion'] ?? []), true)) $usos++;
        }
    }
    return $usos;
}

/**
 * Crea (id = null) o actualiza un tipo. Devuelve [bool ok, string mensaje].
 * Valida sigla y nombre únicos, formato y longitudes.
 */
function tipos_operacion_guardar(array $in, ?int $id = null): array {
    $codigo = strtoupper(trim((string)($in['codigo'] ?? '')));
    $nombre = trim(preg_replace('/\s+/', ' ', (string)($in['nombre'] ?? '')));
    $desc   = trim((string)($in['descripcion'] ?? ''));
    $estado = ((string)($in['estado'] ?? '1') === '0') ? 0 : 1;

    if (!preg_match('/^[A-Z0-9_]{2,10}$/', $codigo)) return [false, 'La sigla debe tener entre 2 y 10 caracteres: letras, números o guion bajo (ej. EXP).'];
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 60) return [false, 'El nombre debe tener entre 3 y 60 caracteres.'];
    if (mb_strlen($desc) > 300) return [false, 'La descripción no puede superar los 300 caracteres.'];

    $lista = tipos_operacion_todos();
    foreach ($lista as $t) {
        if ($id !== null && (int)$t['id'] === $id) continue;
        if (strcasecmp($t['codigo'], $codigo) === 0) return [false, "Ya existe un tipo de operación con la sigla {$codigo}."];
        if (mb_strtolower($t['nombre']) === mb_strtolower($nombre)) return [false, "Ya existe un tipo de operación llamado «{$nombre}»."];
    }

    if ($id === null) {
        $claves = array_column($lista, 'clave');
        $base = $clave = tipos_operacion_slug($nombre);
        for ($i = 2; in_array($clave, $claves, true); $i++) $clave = $base . '_' . $i;
        $lista[] = [
            'id'             => (int)max(array_merge([0], array_column($lista, 'id'))) + 1,
            'codigo'         => $codigo,
            'clave'          => $clave,
            'nombre'         => $nombre,
            'descripcion'    => $desc,
            'estado'         => $estado,
            'fecha_creacion' => date('Y-m-d H:i:s'),
        ];
        return tipos_operacion_escribir($lista) ? [true, "Tipo de operación «{$nombre}» creado correctamente."] : [false, 'No se pudo guardar el cambio. Revisa los permisos de la carpeta storage.'];
    }

    $encontrado = false;
    foreach ($lista as &$t) {
        if ((int)$t['id'] !== $id) continue;
        if ($estado === 0 && (int)$t['estado'] === 1 && count(tipos_operacion_activos()) <= 1) {
            return [false, 'Debe quedar al menos un tipo de operación activo.'];
        }
        $t['codigo'] = $codigo; $t['nombre'] = $nombre; $t['descripcion'] = $desc; $t['estado'] = $estado;
        $encontrado = true;
        break;
    }
    unset($t);
    if (!$encontrado) return [false, 'El tipo de operación no existe.'];
    return tipos_operacion_escribir($lista) ? [true, "Tipo de operación «{$nombre}» actualizado correctamente."] : [false, 'No se pudo guardar el cambio. Revisa los permisos de la carpeta storage.'];
}

/** Activa o inactiva un tipo. Devuelve [bool ok, string mensaje]. */
function tipos_operacion_cambiar_estado(int $id): array {
    $lista = tipos_operacion_todos();
    foreach ($lista as &$t) {
        if ((int)$t['id'] !== $id) continue;
        if ((int)$t['estado'] === 1 && count(tipos_operacion_activos()) <= 1) {
            return [false, 'Debe quedar al menos un tipo de operación activo.'];
        }
        $t['estado'] = (int)$t['estado'] === 1 ? 0 : 1;
        $texto = $t['estado'] === 1 ? 'activado' : 'inactivado';
        $nombre = $t['nombre'];
        unset($t);
        return tipos_operacion_escribir($lista) ? [true, "«{$nombre}» fue {$texto}."] : [false, 'No se pudo guardar el cambio.'];
    }
    return [false, 'El tipo de operación no existe.'];
}

/** Elimina un tipo solo si ninguna novedad lo usa. Devuelve [bool ok, string mensaje]. */
function tipos_operacion_eliminar(int $id): array {
    $t = tipos_operacion_obtener($id);
    if (!$t) return [false, 'El tipo de operación no existe.'];
    if ((int)$t['estado'] === 1 && count(tipos_operacion_activos()) <= 1) return [false, 'Debe quedar al menos un tipo de operación activo.'];
    $usos = tipos_operacion_en_uso($t['clave']);
    if ($usos > 0) return [false, "No se puede eliminar «{$t['nombre']}»: está en {$usos} novedad(es). Inactívalo para ocultarlo sin perder el historial."];
    $lista = array_values(array_filter(tipos_operacion_todos(), fn($x) => (int)$x['id'] !== $id));
    return tipos_operacion_escribir($lista) ? [true, "«{$t['nombre']}» fue eliminado."] : [false, 'No se pudo guardar el cambio.'];
}

/** Texto legible para una lista de claves guardadas en una novedad (usa el nombre actual del tipo). */
/** Mapa clave => sigla (el código del tipo de operación: EXP, IMP…), incluye inactivos. */
function tipos_operacion_siglas(): array {
    $m = [];
    foreach (tipos_operacion_todos() as $t) $m[$t['clave']] = $t['codigo'] ?? strtoupper($t['clave']);
    return $m;
}

/**
 * Operaciones como HTML ya escapado. Por defecto el nombre completo (tablas de los tableros);
 * con $sigla = true la sigla compacta (EXP, IMP…) con el nombre completo como ayuda.
 */
function tipos_operacion_etiqueta(array $claves, bool $sigla = false): string {
    static $nombres = null, $siglas = null;
    if ($nombres === null) { $nombres = tipos_operacion_nombres(); $siglas = tipos_operacion_siglas(); }
    return implode(', ', array_map(function ($c) use ($nombres, $siglas, $sigla) {
        $cod    = $siglas[$c] ?? strtoupper((string)$c);
        $nombre = $nombres[$c] ?? $cod;
        return $sigla ? '<abbr title="' . htmlspecialchars($nombre) . '">' . htmlspecialchars($cod) . '</abbr>' : htmlspecialchars($nombre);
    }, $claves));
}
