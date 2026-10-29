<?php
// export/exportar_excel.php
// Exporta a CSV (se abre en Excel) las novedades que el usuario en sesión puede ver, con los mismos filtros del panel.
//   · administrador y torre de control: todas las novedades
//   · gestor y reportante: solo las suyas (mismas reglas de visibilidad que su panel)
require_once __DIR__ . '/../includes/auth.php';
verificar_sesion();
require_once __DIR__ . '/../includes/visibilidad.php';
require_once __DIR__ . '/../includes/tipologias.php';
require_once __DIR__ . '/../includes/tipos_operacion.php';

$todas = novedades_visibles_para_sesion();

// Mismos filtros que los paneles (por defecto "activas": abiertas + en gestión)
$f_actor  = trim($_GET['actor']     ?? '');
$f_tip    = trim($_GET['tipologia'] ?? '');
$f_estado = trim($_GET['estado']    ?? 'activas');
$f_op     = trim($_GET['operacion'] ?? '');
$f_desde  = trim($_GET['desde']     ?? '');
$f_hasta  = trim($_GET['hasta']     ?? '');
$f_search = trim($_GET['search']    ?? '');
$f_estados = (isset($_GET['estados']) && is_array($_GET['estados']))
    ? array_intersect($_GET['estados'], ['abierta', 'en_gestion', 'resuelta', 'reporte'])
    : [];

$filtradas = array_filter($todas, function ($n) use ($f_actor, $f_tip, $f_estado, $f_estados, $f_op, $f_desde, $f_hasta, $f_search) {
    if (!empty($f_estados)) {
        if (!in_array($n['estado'], $f_estados, true)) return false;
    } elseif ($f_estado === 'activas') {
        if (!in_array($n['estado'], ['abierta', 'en_gestion'], true)) return false;
    } elseif ($f_estado !== '' && $f_estado !== 'todas' && $n['estado'] !== $f_estado) {
        return false;
    }
    if ($f_actor && ($n['tipo_actor_reportante'] ?? '') !== $f_actor) return false;
    if ($f_tip   && !in_array($f_tip, novedad_tipologias($n), true)) return false;
    if ($f_op    && !in_array($f_op, $n['tipo_operacion'] ?? [], true)) return false;
    $fecha = substr((string)($n['fecha_creacion'] ?? ''), 0, 10);
    if ($f_desde && $fecha < $f_desde) return false;
    if ($f_hasta && $fecha > $f_hasta) return false;
    if ($f_search && stripos(($n['id'] ?? '') . ($n['tipo_novedad'] ?? '') . ($n['organizacion'] ?? ''), $f_search) === false) return false;
    return true;
});

$nombres_op = tipos_operacion_nombres();
$filename = 'novedades_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea bien los acentos

fputcsv($out, ['ID', 'Tipo de novedad', 'Tipologías', 'Tipo de operación', 'Actor reportante', 'Organización', 'Usuario reportante',
               'Estado', 'Fecha creación', 'Causal', 'Resuelto por', 'Fecha resolución', 'Mensajes en el hilo'], ';');

foreach ($filtradas as $n) {
    $ops = array_map(fn($c) => $nombres_op[$c] ?? $c, $n['tipo_operacion'] ?? []);
    fputcsv($out, [
        $n['id'] ?? '',
        str_replace('_', ' ', $n['tipo_novedad'] ?? ''),
        implode(' / ', novedad_tipologias($n)),
        implode(' / ', $ops),
        $n['tipo_actor_reportante'] ?? '',
        $n['organizacion'] ?? '',
        $n['usuario_reportante'] ?? '',
        ucfirst(str_replace('_', ' ', $n['estado'] ?? '')),
        $n['fecha_creacion'] ?? '',
        $n['causal'] ?? '',
        $n['resuelto_por'] ?? '',
        $n['fecha_resolucion'] ?? '',
        count($n['hilo'] ?? []),
    ], ';');
}

fclose($out);
exit;
