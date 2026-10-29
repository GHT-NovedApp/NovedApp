<?php
$page_title = 'Torre de Control';
$css_file   = 'dashboard.css';
$css_extra  = ['dashboard_roles.css', 'formulario.css', 'detalle_novedad.css', 'panel_detalle.css'];
require_once __DIR__ . '/../includes/auth.php';
verificar_sesion(); // torre_control pasa por es_supervision()
exigir_capacidad('ver_dashboard');
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/tipos_operacion.php';
require_once __DIR__ . '/../includes/tipologias.php';
require_once __DIR__ . '/../includes/dashboard_helpers.php';
dash_recordar_vista();
include __DIR__ . '/../includes/header.php';

// Admin y Torre de Control ven TODO
require_once __DIR__ . '/../includes/visibilidad.php';
$todas = novedades_visibles_para_sesion();

// Filtros — estado por defecto: 'abierta'
$f_search  = trim($_GET['search']    ?? '');
$f_estado  = trim($_GET['estado']    ?? 'activas'); // por defecto: abiertas + en gestión
$f_actor   = trim($_GET['actor']     ?? '');
$f_tip     = trim($_GET['tipologia'] ?? '');
$f_op      = trim($_GET['operacion'] ?? '');
$f_desde   = trim($_GET['desde']     ?? '');
$f_hasta   = trim($_GET['hasta']     ?? '');
$f_estados = (isset($_GET['estados']) && is_array($_GET['estados']))
    ? array_intersect($_GET['estados'], ['abierta','en_gestion','resuelta','reporte'])
    : [];

// Catálogo completo de actores (organizaciones y usuarios); se suman los que aparezcan en novedades históricas
require_once __DIR__ . '/../includes/organizaciones_store.php';
$actores_u = array_values(array_unique(array_merge(organizaciones_tipos_actor(), array_column($todas, 'tipo_actor_reportante'))));
sort($actores_u, SORT_NATURAL | SORT_FLAG_CASE);
// Catálogo completo de tipologías (activas e inactivas); se suman las que aparezcan en novedades históricas
$tips_u    = array_values(array_unique(array_merge(array_column(tipologias_todas(), 'codigo'), ...array_values(array_map('novedad_tipologias', $todas)))));
sort($tips_u, SORT_NATURAL | SORT_FLAG_CASE);

$filtradas = array_filter($todas, function($n) use ($f_search,$f_estado,$f_actor,$f_tip,$f_op,$f_desde,$f_hasta,$f_estados) {
    // Filtro por estado: multi-check tiene prioridad, luego tab pill
    if (!empty($f_estados)) {
        if (!in_array($n['estado'], $f_estados)) return false;
    } elseif ($f_estado === 'activas') {
        if (!in_array($n['estado'], ['abierta','en_gestion'], true)) return false;
    } elseif ($f_estado && $f_estado !== 'todas') {
        if ($n['estado'] !== $f_estado) return false;
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

// KPIs — foco en abiertas
$stats = ['abierta'=>0,'en_gestion'=>0,'reporte'=>0,'resuelta'=>0,'total'=>count($todas)];
foreach ($todas as $n) { if (isset($stats[$n['estado']])) $stats[$n['estado']]++; }

$adv_open = ($f_actor || $f_tip || $f_op || $f_desde || $f_hasta || !empty($f_estados));
$base_url  = 'dashboard_torre.php';
$export_url = '../export/exportar_excel.php?' . http_build_query($_GET);
?>

<div class="card">
<div class="db-wrap">

  <!-- KPIs -->
  <nav class="kpi-strip" aria-label="Filtrar por estado">
    <a href="<?php echo htmlspecialchars(kpi_href($base_url, 'abierta')); ?>" class="kpi-card kpi-card--primary"<?php echo kpi_actual($f_estado, $f_estados, 'abierta'); ?>>
      <span class="kpi-val"><?php echo $stats['abierta'];?></span>
      <span class="kpi-lbl">Abiertas</span>
    </a>
    <a href="<?php echo htmlspecialchars(kpi_href($base_url, 'en_gestion')); ?>" class="kpi-card kpi-card--gestion"<?php echo kpi_actual($f_estado, $f_estados, 'en_gestion'); ?>>
      <span class="kpi-val"><?php echo $stats['en_gestion'];?></span>
      <span class="kpi-lbl">En Gestión</span>
    </a>
    <a href="<?php echo htmlspecialchars(kpi_href($base_url, 'reporte')); ?>" class="kpi-card kpi-card--reporte"<?php echo kpi_actual($f_estado, $f_estados, 'reporte'); ?>>
      <span class="kpi-val"><?php echo $stats['reporte'];?></span>
      <span class="kpi-lbl">Reportes</span>
    </a>
    <a href="<?php echo htmlspecialchars(kpi_href($base_url, 'resuelta')); ?>" class="kpi-card kpi-card--resuelta"<?php echo kpi_actual($f_estado, $f_estados, 'resuelta'); ?>>
      <span class="kpi-val"><?php echo $stats['resuelta'];?></span>
      <span class="kpi-lbl">Resueltas</span>
    </a>
    <a href="<?php echo htmlspecialchars(kpi_href($base_url, 'todas')); ?>" class="kpi-card kpi-card--total"<?php echo kpi_actual($f_estado, $f_estados, 'todas'); ?>>
      <span class="kpi-val"><?php echo $stats['total'];?></span>
      <span class="kpi-lbl">Total</span>
    </a>
  </nav>

  <!-- Toolbar -->
  <form method="GET" action="<?php echo $base_url;?>" id="db-form">
    <div class="db-toolbar">
      <!-- Busqueda -->
      <input type="text" name="search" class="db-search"
             placeholder="Buscar por ID, novedad u organizacion..."
             value="<?php echo htmlspecialchars($f_search);?>"
             oninput="this.form.submit()">

      <!-- Estado tabs: Abiertas por defecto -->
      <div class="estado-tabs">
        <?php
        $tabs    = ['activas'=>'Activas','abierta'=>'Abiertas','en_gestion'=>'En Gestión','resuelta'=>'Resueltas','reporte'=>'Reportes','todas'=>'Todas'];
        $tab_cls = ['activas'=>'','abierta'=>'--abierta','en_gestion'=>'--gestion','resuelta'=>'--resuelta','reporte'=>'--reporte','todas'=>''];
        foreach ($tabs as $val => $lbl):
            $p   = array_merge($_GET, ['estado'=>$val,'estados'=>[]]);
            $cls = 'estado-tab estado-tab'.$tab_cls[$val].($f_estado===$val && empty($f_estados)?' active':'');
        ?>
        <a href="<?php echo $base_url.'?'.http_build_query($p); ?>" class="<?php echo $cls; ?>"><?php echo $lbl; ?></a>
        <?php endforeach; ?>
      </div>

      <!-- Filtros avanzados toggle -->
      <button type="button" class="db-adv-toggle <?php echo $adv_open?'open':'';?>"
              onclick="toggleAdv()">
        &#9881; Filtros<?php echo $adv_open ? ' <span class="dr-x15">ON</span>' : '';?>
      </button>

      <!-- Acciones -->
      <div class="dr-x16">
        <a href="<?php echo $export_url;?>" class="btn btn--outline btn--sm">&#128229; Exportar</a>
        <a href="nueva_novedad.php" class="btn btn--primary btn--sm">+ Nueva</a>
      </div>
    </div>

    <!-- Panel filtros avanzados -->
    <div class="db-adv-panel <?php echo $adv_open?'open':'';?>" id="adv-panel">
      <div class="db-adv-field dr-x17">
        <label>Estados (seleccion multiple)</label>
        <div class="dr-x18">
          <?php
          $estados_labels = ['abierta'=>'Abierta','en_gestion'=>'En Gestión','resuelta'=>'Resuelta','reporte'=>'Reporte'];
          foreach ($estados_labels as $ev => $el):
            $checked = in_array($ev, $f_estados) ? 'checked' : '';
          ?>
          <label class="dr-x5">
            <input type="checkbox" name="estados[]" value="<?php echo $ev; ?>" <?php echo $checked; ?>
                   style="accent-color:var(--primary-color);width:18px;height:18px"
                   onchange="this.form.submit()">
            <?php echo $el; ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="db-adv-field">
        <label>Actor</label>
        <select name="actor" onchange="this.form.submit()">
          <option value="">Todos los actores</option>
          <?php foreach($actores_u as $a): ?>
          <option value="<?php echo $a;?>" <?php if($f_actor===$a) echo 'selected';?>><?php echo texto_visible($a);?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="db-adv-field">
        <label>Tipología</label>
        <select name="tipologia" onchange="this.form.submit()">
          <option value="">Todas</option>
          <?php foreach($tips_u as $t): ?>
          <option value="<?php echo $t;?>" <?php if($f_tip===$t) echo 'selected';?>><?php echo $t;?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="db-adv-field">
        <label>Operación</label>
        <select name="operacion" onchange="this.form.submit()">
          <option value="">Todas</option>
          <?php foreach (tipos_operacion_para_filtro($f_op) as $_top): ?>
          <option value="<?php echo htmlspecialchars($_top['clave']); ?>" <?php if ($f_op === $_top['clave']) echo 'selected'; ?>><?php echo htmlspecialchars($_top['nombre']); ?><?php echo (int)$_top['estado'] === 1 ? '' : ' (inactivo)'; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="db-adv-field">
        <label>Desde</label>
        <input type="date" name="desde" value="<?php echo $f_desde;?>" onchange="this.form.submit()">
      </div>
      <div class="db-adv-field">
        <label>Hasta</label>
        <input type="date" name="hasta" value="<?php echo $f_hasta;?>" onchange="this.form.submit()">
      </div>
      <div class="db-adv-field dr-x19">
        <label>&nbsp;</label>
        <a href="<?php echo $base_url;?>" class="btn btn--outline btn--sm">Limpiar filtros</a>
      </div>
    </div>
  </form>

  <!-- Tabla -->
  <div class="table-wrap">
    <?php if (empty($filtradas)): ?>
      <div class="no-results">
        <span class="no-results-icon">&#128269;</span>
        Sin resultados para los filtros aplicados.
      </div>
    <?php else: ?>
    <p class="db-count">Mostrando <strong><?php echo count($filtradas);?></strong> de <?php echo count($todas);?> novedades</p>
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Novedad</th>
          <th>Actor</th>
          <th>Organización</th>
          <th>Operación</th>
          <th>Estado</th>
          <th>Fecha</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($filtradas as $n): ?>
      <tr>
        <td class="id-cell"><?php echo htmlspecialchars($n['id']);?></td>
        <td class="nov-cell">
          <a class="row-link" href="detalle_novedad.php?id=<?php echo urlencode($n['id']);?>" aria-label="Ver detalle de <?php echo htmlspecialchars($n['id']);?>"><strong><?php echo htmlspecialchars(str_replace('_',' ',$n['tipo_novedad']));?></strong></a>
          <span><?php echo htmlspecialchars(implode(' · ', novedad_tipologias($n)));?></span>
        </td>
        <td class="dr-x8"><?php echo texto_visible($n['tipo_actor_reportante']);?></td>
        <td class="dr-x20"><?php echo htmlspecialchars($n['organizacion']);?></td>
        <td class="dr-x9"><?php echo tipos_operacion_etiqueta($n['tipo_operacion'] ?? []);?></td>
        <td><span class="sb sb--<?php echo $n['estado'];?>"><?php echo ucfirst(str_replace('_',' ',$n['estado']));?></span></td>
        <td class="dr-x12"><?php echo substr($n['fecha_creacion'],0,10);?></td>
        <td>
          <a href="detalle_novedad.php?id=<?php echo urlencode($n['id']);?>" class="row-action" title="Ver detalle" tabindex="-1" aria-hidden="true">&#8594;</a>
        </td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
    <?php endif;?>
  </div>
</div>

<script>
function toggleAdv() {
    var btn   = document.querySelector('.db-adv-toggle');
    var panel = document.getElementById('adv-panel');
    btn.classList.toggle('open');
    panel.classList.toggle('open');
}
</script>

<script>
(function(){
    var t;
    var inp = document.querySelector('.db-search');
    if (!inp) return;
    inp.addEventListener('input', function(){
        clearTimeout(t);
        t = setTimeout(function(){ inp.form.submit(); }, 400);
    });
    inp.removeAttribute('oninput');
})();
</script>

</div><!-- /.card -->
<?php include __DIR__ . '/../includes/panel_detalle.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
