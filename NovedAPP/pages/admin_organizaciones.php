<?php
$page_title = 'Parametrización — Fincas y Organizaciones';
$css_file   = 'dashboard.css';
$css_extra  = ['parametrizacion.css'];
require_once __DIR__ . '/../includes/parametrizacion.php';
$puede_editar = verificar_parametrizacion('organizaciones');
require_once __DIR__ . '/../includes/organizaciones_store.php';

$csrf = csrf_token('organizaciones');

// ── Acciones (POST → flash → redirección conservando filtros) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $volver = 'admin_organizaciones.php' . (!empty($_POST['volver']) && preg_match('/^[\w=&%.\-\[\]+]*$/', $_POST['volver']) ? '?' . $_POST['volver'] : '');
    if (!csrf_valido('organizaciones')) {
        flash_poner('organizaciones', false, 'La sesión del formulario venció. Vuelve a intentarlo.');
        header('Location: ' . $volver); exit;
    }
    $accion = $_POST['accion'] ?? '';
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    if ($accion === 'guardar') {
        [$ok, $texto] = organizaciones_guardar($_POST, $id);
        flash_poner('organizaciones', $ok, $texto);
        if (!$ok) $_SESSION['form_organizaciones'] = array_diff_key($_POST, ['csrf' => 1, 'volver' => 1]);
    } elseif ($accion === 'estado' && $id !== null) {
        [$ok, $texto] = organizaciones_cambiar_estado($id);
        flash_poner('organizaciones', $ok, $texto);
    }
    header('Location: ' . $volver); exit;
}

$flash    = flash_tomar('organizaciones');
$borrador = $_SESSION['form_organizaciones'] ?? null; unset($_SESSION['form_organizaciones']);

// ── Filtros y paginación ─────────────────────────────────────────────────
$f_q      = trim($_GET['q'] ?? '');
$f_tipo   = trim($_GET['tipo'] ?? '');
$f_estado = in_array($_GET['estado'] ?? '', ['1', '0'], true) ? $_GET['estado'] : '';
$todas = organizaciones_todas();
$usuarios_por_org = [];
foreach (usuarios_todos() as $u) $usuarios_por_org[$u['tipo_actor'] . '|' . $u['organizacion']] = ($usuarios_por_org[$u['tipo_actor'] . '|' . $u['organizacion']] ?? 0) + 1;

$filtradas = array_values(array_filter($todas, function ($o) use ($f_q, $f_tipo, $f_estado) {
    if ($f_tipo !== '' && $o['tipo_actor'] !== $f_tipo) return false;
    if ($f_estado !== '' && (int)$o['activo'] !== (int)$f_estado) return false;
    if ($f_q !== '' && stripos($o['nombre'] . ' ' . $o['ubicacion'], $f_q) === false) return false;
    return true;
}));
usort($filtradas, fn($a, $b) => [$a['tipo_actor'], $a['nombre']] <=> [$b['tipo_actor'], $b['nombre']]);
$por_pagina = 25;
$total = count($filtradas);
$paginas = max(1, (int)ceil($total / $por_pagina));
$pagina = min(max(1, (int)($_GET['p'] ?? 1)), $paginas);
$items = array_slice($filtradas, ($pagina - 1) * $por_pagina, $por_pagina);
$tipos = organizaciones_tipos_actor();

$qs = function (array $extra = []) use ($f_q, $f_tipo, $f_estado) {
    $q = array_filter(array_merge(['q' => $f_q, 'tipo' => $f_tipo, 'estado' => $f_estado], $extra), fn($v) => $v !== '' && $v !== null);
    return $q ? '?' . http_build_query($q) : '';
};
$volver_actual = ltrim($qs(['p' => $pagina > 1 ? $pagina : '']), '?');

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <?php parametrizacion_tabs('organizaciones'); ?>
  <?php flash_mostrar($flash); ?>

  <form method="GET" action="admin_organizaciones.php" class="pz-toolbar">
    <div class="pz-search">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" value="<?php echo htmlspecialchars($f_q); ?>" aria-label="Buscar organizaciones" placeholder="Buscar por nombre o ubicación…">
    </div>
    <select name="tipo" aria-label="Filtrar por tipo de actor">
      <option value="">Todos los tipos de actor</option>
      <?php foreach ($tipos as $t): ?><option value="<?php echo htmlspecialchars($t); ?>" <?php echo $f_tipo === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option><?php endforeach; ?>
    </select>
    <select name="estado" aria-label="Filtrar por estado">
      <option value="">Todos los estados</option>
      <option value="1" <?php echo $f_estado === '1' ? 'selected' : ''; ?>>Activas</option>
      <option value="0" <?php echo $f_estado === '0' ? 'selected' : ''; ?>>Inactivas</option>
    </select>
    <button type="submit" class="btn btn--secondary btn--sm">Filtrar</button>
    <?php if ($f_q !== '' || $f_tipo !== '' || $f_estado !== ''): ?><a href="admin_organizaciones.php" class="btn btn--outline btn--sm">Limpiar</a><?php endif; ?>
    <button type="button" class="btn btn--primary btn--sm pz-nuevo" id="pz-btn-nuevo">+ Nueva organización</button>
  </form>
  <p class="pz-count"><?php echo $total; ?> de <?php echo count($todas); ?> organizaciones</p>

  <div class="table-wrap">
    <?php if (!$items): ?>
      <p class="pz-empty-msg">Ninguna organización coincide con los filtros.</p>
    <?php else: ?>
    <table class="pz-table">
      <caption class="visually-hidden">Fincas y organizaciones</caption>
      <thead>
        <tr><th scope="col">Tipo de actor</th><th scope="col">Nombre</th><th scope="col">Ubicación</th><th scope="col">Usuarios</th><th scope="col">Estado</th><th scope="col"><span class="visually-hidden">Acciones</span></th></tr>
      </thead>
      <tbody>
      <?php foreach ($items as $o):
        $n_usr = $usuarios_por_org[$o['tipo_actor'] . '|' . $o['nombre']] ?? 0;
      ?>
        <tr class="<?php echo $o['activo'] ? '' : 'pz-row--inactivo'; ?>">
          <td><span class="pz-badge pz-actor-chip"><?php echo htmlspecialchars($o['tipo_actor']); ?></span></td>
          <td><span class="pz-name"><?php echo htmlspecialchars($o['nombre']); ?></span></td>
          <td class="pz-muted"><?php echo htmlspecialchars($o['ubicacion'] ?: '—'); ?></td>
          <td class="pz-muted"><?php echo $n_usr; ?></td>
          <td><span class="pz-badge pz-estado <?php echo $o['activo'] ? 'pz-estado--activo' : 'pz-estado--inactivo'; ?>"><?php echo $o['activo'] ? 'Activa' : 'Inactiva'; ?></span></td>
          <td>
            <div class="pz-actions">
              <button type="button" class="pz-btn" data-editar="<?php echo (int)$o['id']; ?>" aria-label="Editar <?php echo htmlspecialchars($o['nombre']); ?>">Editar</button>
              <form method="POST" action="admin_organizaciones.php"
                    <?php if ($o['activo']): ?>data-confirm-title="Inactivar organización" data-confirm="«<?php echo htmlspecialchars($o['nombre'], ENT_QUOTES); ?>» dejará de ofrecerse al crear usuarios.<?php echo $n_usr ? ' Sus ' . $n_usr . ' usuario(s) actuales siguen funcionando.' : ''; ?>" data-confirm-ok="Inactivar"<?php endif; ?>>
                <input type="hidden" name="accion" value="estado">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="volver" value="<?php echo htmlspecialchars($volver_actual); ?>">
                <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>">
                <button type="submit" class="pz-btn <?php echo $o['activo'] ? 'pz-btn--warn' : ''; ?>"><?php echo $o['activo'] ? 'Inactivar' : 'Activar'; ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($paginas > 1): ?>
    <nav class="pager" aria-label="Paginación">
      <span>Página <?php echo $pagina; ?> de <?php echo $paginas; ?></span>
      <span class="pager__links">
        <a class="pager__link" href="admin_organizaciones.php<?php echo $qs(['p' => $pagina - 1]); ?>"<?php echo $pagina <= 1 ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>Anterior</a>
        <a class="pager__link" href="admin_organizaciones.php<?php echo $qs(['p' => $pagina + 1]); ?>"<?php echo $pagina >= $paginas ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>Siguiente</a>
      </span>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<dialog class="pz-dialog pz-dialog--sm" id="dlg-org" aria-labelledby="dlg-org-titulo">
  <form method="POST" action="admin_organizaciones.php" autocomplete="off">
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
    <input type="hidden" name="volver" value="<?php echo htmlspecialchars($volver_actual); ?>">
    <input type="hidden" name="id" id="o-id" value="">
    <header class="pz-dialog__head"><h2 id="dlg-org-titulo">Nueva organización</h2><button type="button" class="pz-x" data-cerrar aria-label="Cerrar">&times;</button></header>
    <div class="pz-dialog__body">
      <div class="pz-field"><label for="o-tipo">Tipo de actor <span class="req">*</span></label>
        <input type="text" id="o-tipo" name="tipo_actor" list="lista-tipos" maxlength="30" required placeholder="EAI, CEDI, AdC…">
        <datalist id="lista-tipos"><?php foreach ($tipos as $t): ?><option value="<?php echo htmlspecialchars($t); ?>"><?php endforeach; ?></datalist></div>
      <div class="pz-field"><label for="o-nombre">Nombre de la finca u organización <span class="req">*</span></label><input type="text" id="o-nombre" name="nombre" maxlength="100" required></div>
      <div class="pz-field"><label for="o-ubi">Ubicación <span class="pz-opt">(opcional)</span></label><input type="text" id="o-ubi" name="ubicacion" maxlength="60" placeholder="Bogotá, Medellín, Quito…"></div>
      <p class="pz-group__hint" id="o-aviso" hidden>Si cambias el nombre, se actualiza también en los usuarios que pertenecen a esta organización.</p>
    </div>
    <footer class="pz-dialog__foot">
      <button type="button" class="btn btn--outline btn--sm" data-cerrar>Cancelar</button>
      <button type="submit" class="btn btn--primary btn--sm" id="o-guardar">Guardar</button>
    </footer>
  </form>
</dialog>

<script>
(function () {
    var ORGS = <?php echo json_encode(array_column($todas, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var BORRADOR = <?php echo json_encode($borrador, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var dlg = document.getElementById('dlg-org'), $ = function (id) { return document.getElementById(id); };
    document.querySelectorAll('[data-cerrar]').forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
    function abrir(id, b) {
        var o = id ? ORGS[id] : null; b = b || {};
        $('dlg-org-titulo').textContent = o ? 'Editar organización' : 'Nueva organización';
        $('o-guardar').textContent = o ? 'Guardar cambios' : 'Crear organización';
        $('o-id').value = id || '';
        $('o-tipo').value = b.tipo_actor !== undefined ? b.tipo_actor : (o ? o.tipo_actor : '');
        $('o-nombre').value = b.nombre !== undefined ? b.nombre : (o ? o.nombre : '');
        $('o-ubi').value = b.ubicacion !== undefined ? b.ubicacion : (o ? o.ubicacion : '');
        $('o-aviso').hidden = !o;
        dlg.showModal(); $('o-nombre').focus();
    }
    $('pz-btn-nuevo').addEventListener('click', function () { abrir(null); });
    document.querySelectorAll('[data-editar]').forEach(function (b) { b.addEventListener('click', function () { abrir(b.dataset.editar); }); });
    if (BORRADOR) abrir(BORRADOR.id || null, BORRADOR);
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
