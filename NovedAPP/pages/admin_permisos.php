<?php
$page_title = 'Parametrización — Permisos';
$css_file   = 'dashboard.css';
$css_extra  = ['admin_operaciones.css', 'parametrizacion.css'];
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/parametrizacion.php';
$puede_editar = verificar_parametrizacion('permisos');

$yo     = usuarios_obtener($_SESSION['usuario']);
$editor = ['perfil' => $_SESSION['perfil'], 'usuario' => $_SESSION['usuario'], 'marca' => !empty($_SESSION['puede_parametrizar'])];
$soy_admin = $_SESSION['perfil'] === 'administrador';
$csrf = csrf_token('permisos');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido('permisos')) {
        flash_poner('permisos', false, 'La sesión del formulario venció. Vuelve a intentarlo.');
    } else {
        [$ok, $texto] = permisos_guardar(trim($_POST['perfil'] ?? ''), (array)($_POST['cap'] ?? []), $editor);
        flash_poner('permisos', $ok, $texto);
    }
    header('Location: admin_permisos.php?abrir=' . urlencode(trim($_POST['perfil'] ?? ''))); exit;
}
$flash = flash_tomar('permisos');
$abrir = isset(PERMISOS_SISTEMA[$_GET['abrir'] ?? '']) ? $_GET['abrir'] : '';

// Cuántas cuentas activas tiene cada permiso
$conteo = array_fill_keys(array_keys(PERMISOS_SISTEMA), 0);
foreach (usuarios_todos() as $u) { if (!empty($u['activo']) && isset($conteo[$u['perfil']])) $conteo[$u['perfil']]++; }

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <?php parametrizacion_tabs('permisos'); ?>

  <?php if ($flash): ?>
    <div class="alert alert--<?php echo $flash[0] === 'success' ? 'success' : 'error'; ?>" role="<?php echo $flash[0] === 'success' ? 'status' : 'alert'; ?>"><?php echo htmlspecialchars($flash[1]); ?></div>
  <?php endif; ?>

  <div class="op-head">
    <p class="op-intro">Abre un permiso para definir qué puede hacer. Los cambios aplican a todas sus cuentas desde la siguiente petición.
      <?php if (!$soy_admin): ?>Como no eres administrador, solo puedes editar Gestor y Reportante, y solo otorgar capacidades que tu permiso ya tiene.<?php endif; ?></p>
  </div>

  <div class="pz-perm-list">
  <?php foreach (PERMISOS_SISTEMA as $perfil => $info):
    $caps = permisos_capacidades($perfil);
    $meta = permisos_meta($perfil);
    $editable = $puede_editar && ($soy_admin || !in_array($perfil, ['administrador', 'torre_control'], true));
    $mias = permisos_capacidades($_SESSION['perfil']);
    $mi_marca = !empty($_SESSION['puede_parametrizar']);
    // Resumen para la fila cerrada
    $n_bool = $n_bool_on = $n_sec_on = $n_sec = 0;
    foreach (CAPACIDADES as $k => $d) {
      if ($d['tipo'] === 'bool') { $n_bool++; if ($caps[$k] === true) $n_bool_on++; }
      else { $n_sec++; if ($caps[$k] !== 'ninguno') $n_sec_on++; }
    }
  ?>
    <details class="pz-perm" name="permiso" <?php echo $abrir === $perfil ? 'open' : ''; ?>>
      <summary class="pz-perm__sum">
        <span class="pz-perm__id">
          <h2><?php echo badge_permiso($perfil); ?></h2>
          <span class="pz-perm__desc"><?php echo htmlspecialchars($info['desc']); ?></span>
        </span>
        <span class="pz-perm__meta" title="<?php echo "$n_bool_on de $n_bool acciones · $n_sec_on de $n_sec secciones · {$conteo[$perfil]} usuarios activos"; ?>" aria-label="<?php echo "$n_bool_on de $n_bool acciones, $n_sec_on de $n_sec secciones, {$conteo[$perfil]} usuarios activos"; ?>">
          <span><b><?php echo $n_bool_on; ?></b>/<?php echo $n_bool; ?><span class="pz-perm__lbl"> acciones</span></span>
          <span aria-hidden="true">·</span>
          <span><b><?php echo $n_sec_on; ?></b>/<?php echo $n_sec; ?><span class="pz-perm__lbl"> secciones</span></span>
          <span aria-hidden="true">·</span>
          <span><b><?php echo $conteo[$perfil]; ?></b><span class="pz-perm__lbl"> <?php echo $conteo[$perfil] === 1 ? 'usuario' : 'usuarios'; ?></span></span>
        </span>
        <svg class="pz-perm__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
      </summary>

      <form method="POST" action="admin_permisos.php" class="pz-perm__body">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="perfil" value="<?php echo $perfil; ?>">

        <div class="pz-perm__col">
          <?php foreach (CAPACIDADES_GRUPOS as $gk => $glabel):
            if ($gk === 'param') continue; ?>
            <h3><?php echo htmlspecialchars($glabel); ?></h3>
            <?php foreach (CAPACIDADES as $clave => $def):
              if ($def['grupo'] !== $gk) continue;
              $protegida = array_key_exists($clave, CAPACIDADES_PROTEGIDAS[$perfil] ?? []);
              $sin_poder = !$soy_admin && $editable && permisos_rango($caps[$clave]) > permisos_rango($mias[$clave] ?? false, $mi_marca);
              $bloqueada = !$editable || $protegida || $sin_poder;
              $nota = $protegida ? 'Protegida: no se puede quitar.' : ($sin_poder ? 'Tu permiso no la tiene.' : '');
              $id = "cap-$perfil-$clave"; ?>
              <label class="pz-opt" for="<?php echo $id; ?>" title="<?php echo htmlspecialchars($def['desc'] . ($nota ? ' ' . $nota : '')); ?>" <?php echo $bloqueada ? 'data-bloq' : ''; ?>>
                <input type="checkbox" id="<?php echo $id; ?>" name="cap[<?php echo $clave; ?>]" value="1" <?php echo $caps[$clave] === true ? 'checked' : ''; ?> <?php echo $bloqueada ? 'disabled' : ''; ?>>
                <span><?php echo htmlspecialchars($def['label']); ?>
                  <small class="visually-hidden"><?php echo htmlspecialchars($def['desc']); ?></small>
                  <?php if ($nota): ?><small><?php echo $nota; ?></small><?php endif; ?></span>
              </label>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>

        <div class="pz-perm__col">
          <h3>Parametrización por sección</h3>
          <?php foreach (CAPACIDADES as $clave => $def):
            if ($def['grupo'] !== 'param') continue;
            $v = $caps[$clave];
            $protegida = array_key_exists($clave, CAPACIDADES_PROTEGIDAS[$perfil] ?? []);
            $mi_rango = permisos_rango($mias[$clave] ?? false, $mi_marca);
            $sin_poder = !$soy_admin && $editable && permisos_rango($v) > $mi_rango;
            $bloqueada = !$editable || $protegida || $sin_poder;
            $id = "cap-$perfil-$clave"; ?>
            <div class="pz-sec">
              <label for="<?php echo $id; ?>"><?php echo htmlspecialchars($def['label']); ?></label>
              <select id="<?php echo $id; ?>" name="cap[<?php echo $clave; ?>]" <?php echo $bloqueada ? 'disabled' : ''; ?>>
                <?php foreach (['ninguno' => 'Sin acceso', 'ver' => 'Solo ver', 'editar' => 'Ver y editar'] as $nv => $nl):
                  $fuera = !$soy_admin && NIVELES[$nv] > $mi_rango; ?>
                  <option value="<?php echo $nv; ?>" <?php echo $v === $nv ? 'selected' : ''; ?> <?php echo $fuera && $v !== $nv ? 'disabled' : ''; ?>><?php echo $nl; ?></option>
                <?php endforeach; ?>
                <?php if ($perfil === 'torre_control'): ?><option value="cuenta" <?php echo $v === 'cuenta' ? 'selected' : ''; ?>>Según cuenta</option><?php endif; ?>
              </select>
            </div>
          <?php endforeach; ?>
          <?php if ($perfil === 'torre_control'): ?><p class="pz-nota">«Según cuenta» usa la marca «puede parametrizar» de cada usuario.</p><?php endif; ?>
          <?php if ($perfil === 'administrador'): ?><p class="pz-nota">Usuarios y Permisos con edición no se pueden quitar al administrador.</p><?php endif; ?>
        </div>

        <div class="pz-perm__foot">
          <span class="pz-muted"><?php echo $meta['en'] ? 'Último cambio: ' . htmlspecialchars($meta['en']) . ' por ' . htmlspecialchars($meta['por']) : 'Valores originales'; ?></span>
          <?php if ($editable): ?><button type="submit" class="btn btn--primary btn--sm">Guardar cambios</button>
          <?php else: ?><span class="pz-muted"><?php echo $puede_editar ? 'Solo un administrador puede editarlo.' : 'Solo lectura.'; ?></span><?php endif; ?>
        </div>
      </form>
    </details>
  <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
