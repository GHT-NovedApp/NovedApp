<?php
$page_title = 'Mis Novedades';
$css_file   = 'dashboard.css';
$css_extra  = ['dashboard_roles.css', 'formulario.css', 'detalle_novedad.css', 'panel_detalle.css'];
require_once __DIR__ . '/../includes/auth.php';
verificar_sesion('gestor');
exigir_capacidad('ver_dashboard');
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/tipos_operacion.php';
require_once __DIR__ . '/../includes/tipologias.php';
require_once __DIR__ . '/../includes/dashboard_helpers.php';
dash_recordar_vista();
include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/visibilidad.php';
$todas      = novedades_visibles_para_sesion();
$mi_actor   = $_SESSION['tipo_actor'];
$mi_usuario = $_SESSION['usuario'];

// Regla única de acceso (includes/visibilidad.php): propias, etiquetado por nombre, dirigidas a su actor
$mis = $todas;

$f_search = trim($_GET['search'] ?? '');
$f_estado  = trim($_GET['estado'] ?? 'activas'); // por defecto: abiertas + en gestión
$f_estados = isset($_GET['estados']) && is_array($_GET['estados'])
    ? array_intersect($_GET['estados'], ['abierta','en_gestion','resuelta','reporte'])
    : [];

$filtradas = array_filter($mis, function($n) use ($f_search, $f_estado, $f_estados) {
    // Estado: tab pill (single) OR multi-checkbox selection
    if (!empty($f_estados)) {
        if (!in_array($n['estado'], $f_estados)) return false;
    } elseif ($f_estado === 'activas') {
        if (!in_array($n['estado'], ['abierta','en_gestion'], true)) return false;
    } elseif ($f_estado && $f_estado !== 'todas') {
        if ($n['estado'] !== $f_estado) return false;
    }
    if ($f_search) {
        $hay = stripos($n['id'],$f_search)!==false
            || stripos($n['tipo_novedad'],$f_search)!==false
            || stripos($n['organizacion'],$f_search)!==false;
        if (!$hay) return false;
    }
    return true;
});

$stats = ['abierta'=>0,'en_gestion'=>0,'reporte'=>0,'resuelta'=>0,'total'=>count($mis)];
foreach ($mis as $n) { if (isset($stats[$n['estado']])) $stats[$n['estado']]++; }

$base_url = 'dashboard_gestor.php';
?>

<div class="card">
<div class="db-wrap">

  <!-- Alerta respuestas nuevas -->
  <?php
  $con_resp = array_filter($mis, function($n) {
      $hilo = $n['hilo'] ?? []; if (count($hilo) < 2) return false;
      $u = end($hilo); return $u['tipo'] === 'respuesta';
  });
  if (!empty($con_resp)): ?>
  <div class="alert alert--info dr-x1">
    <span><strong><?php echo count($con_resp);?></strong> novedad(es) con respuesta nueva del gestor.</span>
    <a href="?estado=en_gestion" class="btn btn--primary btn--sm">Ver</a>
  </div>
  <?php endif;?>

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
      <span class="kpi-lbl">Total mis novedades</span>
    </a>
  </nav>

  <!-- Toolbar -->
  <form method="GET" action="<?php echo $base_url;?>">
    <div class="db-toolbar">
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
      <div class="dr-x2">
        <a href="../export/exportar_excel.php?<?php echo htmlspecialchars(http_build_query($_GET)); ?>" class="btn btn--outline btn--sm" title="Exporta tus novedades con los filtros actuales">Exportar</a>
        <a href="nueva_novedad.php" class="btn btn--primary btn--sm">+ Nueva Novedad</a>
      </div>
    </div>
      <div class="dr-x3">
        <span class="dr-x4">Filtrar por:</span>
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
  </form>

  <!-- Tabla -->
  <div class="table-wrap">
    <?php if (empty($filtradas)): ?>
      <div class="no-results">
        <span class="no-results-icon"><?php echo $f_estado || $f_search ? '&#128269;' : '&#10003;';?></span>
        <?php if ($f_estado || $f_search): ?>
          Sin novedades para esta busqueda.
        <?php else: ?>
          Sin novedades pendientes. <a class="dr-x6" href="nueva_novedad.php">Registrar una nueva</a>
        <?php endif;?>
      </div>
    <?php else: ?>
    <p class="db-count">Mostrando <strong><?php echo count($filtradas);?></strong> novedades</p>
    <table class="data-table">
      <thead>
        <tr><th>ID</th><th>Novedad</th><th>Organización</th><th>Operación</th><th>Estado</th><th>Rol</th><th>Fecha</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($filtradas as $n):
        $rol_dest = novedad_rol_destinatario($n, $mi_usuario);
        $es_copia = $rol_dest === 'copia';
        $es_resp  = !$es_copia && ($rol_dest === 'principal' || ($rol_dest === 'sin_destinatarios' && (in_array($mi_actor, $n['respondentes'] ?? []) || in_array($mi_usuario, $n['respondentes_usuarios'] ?? []))));
        $es_rep  = ($n['usuario_reportante'] ?? '') === $mi_usuario;
        $hilo    = $n['hilo'] ?? [];
        $ultimo  = end($hilo);
        $nueva   = !empty(array_filter(storage_notificaciones_usuario($mi_usuario), fn($notif) => $notif['novedad_id']===$n['id'] && !$notif['leida']));
      ?>
      <tr<?php if($nueva) echo ' class="row-nueva"';?>>
        <td class="id-cell">
          <?php echo htmlspecialchars($n['id']);?>
          <?php if($nueva): ?><span class="sb sb--abierta dr-x7">Nueva</span><?php endif;?>
        </td>
        <td class="nov-cell">
          <a class="row-link" href="detalle_novedad.php?id=<?php echo urlencode($n['id']);?>" aria-label="Ver detalle de <?php echo htmlspecialchars($n['id']);?>"><strong><?php echo htmlspecialchars(str_replace('_',' ',$n['tipo_novedad']));?></strong></a>
          <span><?php echo htmlspecialchars(implode(' · ', novedad_tipologias($n)));?></span>
        </td>
        <td class="dr-x8"><?php echo htmlspecialchars($n['organizacion']);?></td>
        <td class="dr-x9"><?php echo tipos_operacion_etiqueta($n['tipo_operacion'] ?? []);?></td>
        <td><span class="sb sb--<?php echo $n['estado'];?>"><?php echo ucfirst(str_replace('_',' ',$n['estado']));?></span></td>
        <td class="dr-x10">
          <?php if($es_resp) echo '<span class="dr-x11">Respondente</span> ';?>
          <?php if($es_copia) echo '<span class="dr-x11">En copia</span> ';?>
          <?php if($es_rep)  echo '<span>Reportante</span>';?>
        </td>
        <td class="dr-x12"><?php echo substr($n['fecha_creacion'],0,10);?></td>
        <td><a href="detalle_novedad.php?id=<?php echo urlencode($n['id']);?>" class="row-action" tabindex="-1" aria-hidden="true">&#8594;</a></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
    <?php endif;?>
  </div>
</div>

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
