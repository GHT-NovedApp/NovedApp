<?php
$page_title = 'Dashboard';
$css_file   = 'dashboard.css';
$css_extra  = ['formulario.css', 'detalle_novedad.css', 'panel_detalle.css'];
require_once __DIR__ . '/../includes/auth.php';
verificar_sesion();
exigir_capacidad('ver_dashboard');
require_once __DIR__ . '/../includes/dashboard_helpers.php';
dash_recordar_vista();
// La torre de control usa su propio panel
if (($_SESSION['perfil'] ?? '') === 'torre_control') { header('Location: dashboard_torre.php'); exit; }
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/tipos_operacion.php';
require_once __DIR__ . '/../includes/tipologias.php';
include __DIR__ . '/../includes/header.php';

// Admin y Torre de Control ven TODO
require_once __DIR__ . '/../includes/visibilidad.php';
$todas = novedades_visibles_para_sesion();

// Filtros — por defecto se muestran las novedades activas (abiertas + en gestión)
$ESTADOS_VALIDOS = ['activas','abierta','en_gestion','resuelta','reporte','todas'];
$f_search  = trim($_GET['search']    ?? '');
$f_estado  = trim($_GET['estado']    ?? 'activas');
if (!in_array($f_estado, $ESTADOS_VALIDOS, true)) $f_estado = 'activas';
$f_actor   = trim($_GET['actor']     ?? '');
$f_tip     = trim($_GET['tipologia'] ?? '');
$f_op      = trim($_GET['operacion'] ?? '');
$f_desde   = trim($_GET['desde']     ?? '');
$f_hasta   = trim($_GET['hasta']     ?? '');
$f_estados = (isset($_GET['estados']) && is_array($_GET['estados']))
    ? array_values(array_intersect($_GET['estados'], ['abierta','en_gestion','resuelta','reporte']))
    : [];

// Catálogo completo de actores (organizaciones y usuarios); se suman los que aparezcan en novedades históricas
require_once __DIR__ . '/../includes/organizaciones_store.php';
$actores_u = array_values(array_unique(array_merge(organizaciones_tipos_actor(), array_column($todas, 'tipo_actor_reportante'))));
sort($actores_u, SORT_NATURAL | SORT_FLAG_CASE);
// Catálogo completo de tipologías (activas e inactivas); se suman las que aparezcan en novedades históricas
$tips_u    = array_values(array_unique(array_merge(array_column(tipologias_todas(), 'codigo'), ...array_values(array_map('novedad_tipologias', $todas)))));
sort($tips_u, SORT_NATURAL | SORT_FLAG_CASE);

$filtradas = array_filter($todas, function($n) use ($f_search,$f_estado,$f_estados,$f_actor,$f_tip,$f_op,$f_desde,$f_hasta) {
    if (!empty($f_estados)) {
        if (!in_array($n['estado'], $f_estados, true)) return false;
    } elseif ($f_estado === 'activas') {
        if (!in_array($n['estado'], ['abierta','en_gestion'], true)) return false;
    } elseif ($f_estado !== 'todas' && $n['estado'] !== $f_estado) {
        return false;
    }
    if ($f_actor && $n['tipo_actor_reportante'] !== $f_actor) return false;
    if ($f_tip   && !in_array($f_tip, novedad_tipologias($n), true)) return false;
    if ($f_op    && !in_array($f_op, $n['tipo_operacion'] ?? [])) return false;
    if ($f_desde && substr($n['fecha_creacion'],0,10) < $f_desde) return false;
    if ($f_hasta && substr($n['fecha_creacion'],0,10) > $f_hasta) return false;
    if ($f_search) {
        $hay = stripos($n['id'],$f_search)!==false
            || stripos($n['tipo_novedad'],$f_search)!==false
            || stripos($n['organizacion'],$f_search)!==false;
        if (!$hay) return false;
    }
    return true;
});

// Orden (columnas permitidas) — por defecto, más recientes primero
$COLS_ORDEN = ['id'=>'id','novedad'=>'tipo_novedad','actor'=>'tipo_actor_reportante','organizacion'=>'organizacion','estado'=>'estado','fecha'=>'fecha_creacion'];
$orden = $_GET['orden'] ?? 'fecha';
if (!isset($COLS_ORDEN[$orden])) $orden = 'fecha';
$dir = (($_GET['dir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';
$campo = $COLS_ORDEN[$orden];
$filtradas = array_values($filtradas);
usort($filtradas, function($a, $b) use ($campo, $dir) {
    $c = strcasecmp((string)($a[$campo] ?? ''), (string)($b[$campo] ?? ''));
    return $dir === 'asc' ? $c : -$c;
});

// Paginación
$por_pagina = 25;
$total_f    = count($filtradas);
$paginas    = max(1, (int)ceil($total_f / $por_pagina));
$pagina     = min(max(1, (int)($_GET['p'] ?? 1)), $paginas);
$pagina_items = array_slice($filtradas, ($pagina-1)*$por_pagina, $por_pagina);

// KPIs
$stats = ['abierta'=>0,'en_gestion'=>0,'reporte'=>0,'resuelta'=>0,'total'=>count($todas)];
foreach ($todas as $n) { if (isset($stats[$n['estado']])) $stats[$n['estado']]++; }

$adv_n = count(array_filter([$f_actor, $f_tip, $f_op, $f_desde, $f_hasta])) + (empty($f_estados) ? 0 : 1);
$base_url   = 'dashboard_admin.php';
$params_base = array_filter([
    'search'=>$f_search, 'actor'=>$f_actor, 'tipologia'=>$f_tip, 'operacion'=>$f_op,
    'desde'=>$f_desde, 'hasta'=>$f_hasta,
], fn($v) => $v !== '');
function db_url(array $base, array $extra = []): string {
    $q = array_filter(array_merge($base, $extra), fn($v) => $v !== '' && $v !== null);
    return 'dashboard_admin.php' . ($q ? '?' . http_build_query($q) : '');
}
$params_est = empty($f_estados) ? $params_base : array_merge($params_base, ['estados'=>$f_estados]);
$export_url = '../export/exportar_excel.php?' . http_build_query(array_merge($params_est, ['estado'=>$f_estado]));
$estado_labels = ['activas'=>'Activas (abiertas + en gestión)','abierta'=>'Abiertas','en_gestion'=>'En gestión','resuelta'=>'Resueltas','reporte'=>'Reportes','todas'=>'Todas'];
$ahora = time();
$etiq_estados = ['abierta'=>'Abiertas','en_gestion'=>'En gestión','resuelta'=>'Resueltas','reporte'=>'Reportes'];
$estado_texto = empty($f_estados) ? $estado_labels[$f_estado] : implode(' + ', array_map(fn($e) => $etiq_estados[$e], $f_estados));
$hay_filtros = ($f_search !== '' || $adv_n > 0);

$icono_orden = '<svg width="10" height="12" viewBox="0 0 10 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 1v10M2 8l3 3 3-3"/></svg>';
$icono_orden_asc = '<svg width="10" height="12" viewBox="0 0 10 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 11V1M2 4l3-3 3 3"/></svg>';
function th_sort(string $clave, string $etiqueta, string $orden, string $dir, array $base, string $f_estado, string $ic, string $ic_asc): string {
    $activa = ($orden === $clave);
    $nuevo_dir = ($activa && $dir === 'asc') ? 'desc' : 'asc';
    $url = db_url($base, ['estado'=>$f_estado, 'orden'=>$clave, 'dir'=>$nuevo_dir]);
    $icono = $activa ? ($dir === 'asc' ? $ic_asc : $ic) : '';
    return '<a href="' . htmlspecialchars($url) . '" class="th-sort"' . ($activa ? ' data-activo="1"' : '') . '>' . $etiqueta . $icono . '</a>';
}
?>

<div class="card">
<div class="db-wrap">

  <!-- KPIs: cada tarjeta filtra por estado -->
  <nav class="kpi-strip" aria-label="Filtrar por estado">
    <?php
    $kpis = [
      ['abierta',    'Abiertas',   $stats['abierta'],    'kpi-card kpi-card--primary'],
      ['en_gestion', 'En gestión', $stats['en_gestion'], 'kpi-card kpi-card--gestion'],
      ['reporte',    'Reportes',   $stats['reporte'],    'kpi-card kpi-card--reporte'],
      ['resuelta',   'Resueltas',  $stats['resuelta'],   'kpi-card kpi-card--resuelta'],
      ['todas',      'Total',      $stats['total'],      'kpi-card kpi-card--total'],
    ];
    foreach ($kpis as [$k_val, $k_lbl, $k_n, $k_cls]):
        $es_actual = empty($f_estados) && ($f_estado === $k_val);
    ?>
    <a href="<?php echo htmlspecialchars(db_url($params_base, ['estado'=>$k_val])); ?>"
       class="<?php echo $k_cls; ?>"<?php echo $es_actual ? ' aria-current="true"' : ''; ?>>
      <span class="kpi-val"><?php echo $k_n; ?></span>
      <span class="kpi-lbl"><?php echo $k_lbl; ?></span>
    </a>
    <?php endforeach; ?>
  </nav>

  <!-- Toolbar -->
  <form method="GET" action="<?php echo $base_url; ?>" id="db-form">
    <input type="hidden" name="estado" value="<?php echo htmlspecialchars($f_estado); ?>">
    <div class="db-toolbar">
      <div class="db-search-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="search" class="db-search" id="db-search"
               aria-label="Buscar novedades"
               placeholder="Buscar por ID, novedad u organización… (/)"
               value="<?php echo htmlspecialchars($f_search); ?>">
      </div>

      <button type="submit" class="btn btn--secondary btn--sm">Buscar</button>

      <button type="button" class="db-adv-toggle <?php echo $adv_n ? 'open' : ''; ?>" id="adv-toggle"
              aria-expanded="<?php echo $adv_n ? 'true' : 'false'; ?>" aria-controls="adv-panel">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
        Filtros<?php if ($adv_n): ?> <span class="db-adv-count"><?php echo $adv_n; ?></span><?php endif; ?>
      </button>

      <div class="db-actions">
        <a href="<?php echo htmlspecialchars($export_url); ?>" class="btn btn--outline btn--sm"
           title="Exporta las novedades que ves con estos filtros">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg>
          Exportar
        </a>
      </div>
    </div>

    <!-- Panel de filtros avanzados (se aplican con el botón) -->
    <div class="db-adv-panel <?php echo $adv_n ? 'open' : ''; ?>" id="adv-panel">
      <fieldset class="db-adv-field db-adv-estados">
        <legend>Estados (puedes marcar varios)</legend>
        <div class="db-check-row">
          <?php foreach ($etiq_estados as $ev => $el): ?>
          <label class="db-check"><input type="checkbox" name="estados[]" value="<?php echo $ev; ?>" <?php echo in_array($ev, $f_estados, true) ? 'checked' : ''; ?>> <?php echo $el; ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="db-adv-field">
        <label for="f-actor">Actor</label>
        <select name="actor" id="f-actor">
          <option value="">Todos los actores</option>
          <?php foreach ($actores_u as $a): ?>
          <option value="<?php echo htmlspecialchars($a); ?>" <?php if ($f_actor === $a) echo 'selected'; ?>><?php echo texto_visible($a); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="db-adv-field">
        <label for="f-tip">Tipología</label>
        <select name="tipologia" id="f-tip">
          <option value="">Todas</option>
          <?php foreach ($tips_u as $t): ?>
          <option value="<?php echo htmlspecialchars($t); ?>" <?php if ($f_tip === $t) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="db-adv-field">
        <label for="f-op">Operación</label>
        <select name="operacion" id="f-op">
          <option value="">Todas</option>
          <?php foreach (tipos_operacion_para_filtro($f_op) as $_top): ?>
          <option value="<?php echo htmlspecialchars($_top['clave']); ?>" <?php if ($f_op === $_top['clave']) echo 'selected'; ?>><?php echo htmlspecialchars($_top['nombre']); ?><?php echo (int)$_top['estado'] === 1 ? '' : ' (inactivo)'; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="db-adv-field">
        <label for="f-desde">Desde</label>
        <input type="date" name="desde" id="f-desde" value="<?php echo htmlspecialchars($f_desde); ?>">
      </div>
      <div class="db-adv-field">
        <label for="f-hasta">Hasta</label>
        <input type="date" name="hasta" id="f-hasta" value="<?php echo htmlspecialchars($f_hasta); ?>">
      </div>
      <div class="db-adv-buttons">
        <button type="submit" class="btn btn--primary btn--sm">Aplicar filtros</button>
        <a href="<?php echo htmlspecialchars(db_url([], ['estado'=>$f_estado])); ?>" class="btn btn--outline btn--sm">Limpiar</a>
      </div>
    </div>
  </form>

  <!-- Tabla -->
  <div class="table-wrap">
    <?php if (empty($filtradas)): ?>
      <div class="empty-state">
        <?php if ($hay_filtros): ?>
          <strong>Ninguna novedad coincide con tu búsqueda</strong>
          <p>Prueba con otros términos o quita los filtros.</p>
          <a class="btn btn--outline btn--sm empty-state__link" href="<?php echo htmlspecialchars(db_url([], ['estado'=>$f_estado])); ?>">Limpiar búsqueda y filtros</a>
        <?php elseif (empty($f_estados) && in_array($f_estado, ['activas','abierta','en_gestion'], true)): ?>
          <strong>No hay novedades pendientes de gestión</strong>
          <p>Todo está al día: <?php echo $stats['resuelta']; ?> resueltas y <?php echo $stats['reporte']; ?> reportes en el historial.</p>
          <a class="btn btn--outline btn--sm empty-state__link" href="<?php echo htmlspecialchars(db_url([], ['estado'=>'todas'])); ?>">Ver todas las novedades</a>
        <?php else: ?>
          <strong>No hay novedades en este estado</strong>
          <a class="btn btn--outline btn--sm empty-state__link" href="<?php echo htmlspecialchars(db_url([], ['estado'=>'todas'])); ?>">Ver todas las novedades</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="db-head">
      <p class="db-count">
        <?php echo htmlspecialchars($estado_texto); ?>:
        <strong><?php echo $total_f; ?></strong> de <?php echo count($todas); ?> novedades
        <?php if ($f_estado === 'activas' && empty($f_estados)): ?>· <a href="<?php echo htmlspecialchars(db_url($params_base, ['estado'=>'todas'])); ?>">Ver todas</a><?php endif; ?>
      </p>
    </div>
    <?php
    $aria_sort = fn($c) => $orden === $c ? ' aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '';
    ?>
    <table class="data-table data-table--stack">
      <caption class="visually-hidden">Novedades: <?php echo htmlspecialchars($estado_texto); ?></caption>
      <thead>
        <tr>
          <th scope="col"<?php echo $aria_sort('id'); ?>><?php echo th_sort('id','ID',$orden,$dir,$params_est,$f_estado,$icono_orden,$icono_orden_asc); ?></th>
          <th scope="col"<?php echo $aria_sort('novedad'); ?>><?php echo th_sort('novedad','Novedad',$orden,$dir,$params_est,$f_estado,$icono_orden,$icono_orden_asc); ?></th>
          <th scope="col"<?php echo $aria_sort('actor'); ?>><?php echo th_sort('actor','Actor',$orden,$dir,$params_est,$f_estado,$icono_orden,$icono_orden_asc); ?></th>
          <th scope="col"<?php echo $aria_sort('organizacion'); ?>><?php echo th_sort('organizacion','Organización',$orden,$dir,$params_est,$f_estado,$icono_orden,$icono_orden_asc); ?></th>
          <th scope="col">Operación</th>
          <th scope="col"<?php echo $aria_sort('estado'); ?>><?php echo th_sort('estado','Estado',$orden,$dir,$params_est,$f_estado,$icono_orden,$icono_orden_asc); ?></th>
          <th scope="col"<?php echo $aria_sort('fecha'); ?>><?php echo th_sort('fecha','Creada',$orden,$dir,$params_est,$f_estado,$icono_orden,$icono_orden_asc); ?></th>
          <th scope="col">Tiempo abierta</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pagina_items as $n):
        $nombre_nov = str_replace('_', ' ', $n['tipo_novedad']);
        $pendiente  = in_array($n['estado'], ['abierta','en_gestion'], true);
        $dias = $pendiente ? max(0, (int)floor(($ahora - strtotime($n['fecha_creacion'])) / 86400)) : null;
      ?>
      <tr>
        <td class="id-cell" data-label="ID"><?php echo htmlspecialchars($n['id']); ?></td>
        <td class="nov-cell" data-label="Novedad">
          <a class="row-link" href="detalle_novedad.php?id=<?php echo urlencode($n['id']); ?>"
             aria-label="Ver detalle de <?php echo htmlspecialchars($n['id'] . ', ' . $nombre_nov); ?>">
            <strong><?php echo htmlspecialchars($nombre_nov); ?></strong>
          </a>
          <span><?php echo htmlspecialchars(implode(' · ', novedad_tipologias($n))); ?></span>
        </td>
        <td data-label="Actor"><?php echo texto_visible($n['tipo_actor_reportante']); ?></td>
        <td class="org-cell" data-label="Organización"><?php echo htmlspecialchars($n['organizacion']); ?></td>
        <td class="muted-cell" data-label="Operación"><?php echo tipos_operacion_etiqueta($n['tipo_operacion'] ?? []); ?></td>
        <td data-label="Estado"><span class="sb sb--<?php echo htmlspecialchars($n['estado']); ?>"><?php echo $n['estado'] === 'en_gestion' ? 'En gestión' : ucfirst($n['estado']); ?></span></td>
        <td class="muted-cell" data-label="Creada"><?php echo substr($n['fecha_creacion'], 0, 10); ?></td>
        <td data-label="Tiempo abierta">
          <?php if ($pendiente): ?>
            <span class="age<?php echo $dias >= 7 ? ' age--late' : ''; ?>"><?php echo $dias === 0 ? 'Hoy' : $dias . ($dias === 1 ? ' día' : ' días'); ?></span>
          <?php else: ?>
            <span class="age">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($paginas > 1):
        $pq = array_merge($params_est, ['estado'=>$f_estado, 'orden'=>$orden, 'dir'=>$dir]);
    ?>
    <nav class="pager" aria-label="Paginación">
      <span>Página <?php echo $pagina; ?> de <?php echo $paginas; ?></span>
      <span class="pager__links">
        <a class="pager__link" href="<?php echo htmlspecialchars(db_url($pq, ['p'=>$pagina-1])); ?>"<?php echo $pagina <= 1 ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>Anterior</a>
        <?php for ($i = 1; $i <= $paginas; $i++): ?>
          <a class="pager__link" href="<?php echo htmlspecialchars(db_url($pq, ['p'=>$i])); ?>"<?php echo $i === $pagina ? ' aria-current="page"' : ''; ?>><?php echo $i; ?></a>
        <?php endfor; ?>
        <a class="pager__link" href="<?php echo htmlspecialchars(db_url($pq, ['p'=>$pagina+1])); ?>"<?php echo $pagina >= $paginas ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>Siguiente</a>
      </span>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
    var btn = document.getElementById('adv-toggle');
    var panel = document.getElementById('adv-panel');
    if (btn && panel) {
        btn.addEventListener('click', function () {
            var abierto = panel.classList.toggle('open');
            btn.classList.toggle('open', abierto);
            btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        });
    }
    // Atajo: "/" enfoca la búsqueda (si no se está escribiendo en otro campo)
    document.addEventListener('keydown', function (e) {
        var tag = e.target && e.target.tagName;
        if (e.key === '/' && tag !== 'INPUT' && tag !== 'SELECT' && tag !== 'TEXTAREA') {
            var s = document.getElementById('db-search');
            if (s) { e.preventDefault(); s.focus(); s.select(); }
        }
    });
})();
</script>

</div><!-- /.card -->
<?php include __DIR__ . '/../includes/panel_detalle.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
