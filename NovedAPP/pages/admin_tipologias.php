<?php
$page_title = 'Parametrización — Tipologías';
$css_file   = 'dashboard.css';
$css_extra  = ['admin_operaciones.css', 'parametrizacion.css'];
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/parametrizacion.php';
$puede_editar = verificar_parametrizacion('tipologias');
require_once __DIR__ . '/../includes/tipologias.php';

// Token CSRF para los formularios de esta pantalla
if (empty($_SESSION['csrf_tipologias'])) $_SESSION['csrf_tipologias'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_tipologias'];

// ── Acciones (POST → redirección con mensaje flash) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $_SESSION['flash_tipologias'] = ['error', 'La sesión del formulario venció. Vuelve a intentarlo.'];
        header('Location: admin_tipologias.php'); exit;
    }
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

    if ($accion === 'guardar') {
        [$ok, $texto] = tipologias_guardar($_POST, $id);
        $_SESSION['flash_tipologias'] = [$ok ? 'success' : 'error', $texto];
        if (!$ok) {
            // Conserva lo escrito para que el usuario corrija sin volver a digitar
            $_SESSION['form_tipologias'] = ['id'=>$id, 'codigo'=>$_POST['codigo'] ?? '', 'nombre'=>$_POST['nombre'] ?? '', 'descripcion'=>$_POST['descripcion'] ?? '', 'estado'=>$_POST['estado'] ?? '1'];
        }
    } elseif ($accion === 'estado' && $id !== null) {
        [$ok, $texto] = tipologias_cambiar_estado($id);
        $_SESSION['flash_tipologias'] = [$ok ? 'success' : 'error', $texto];
    } elseif ($accion === 'eliminar' && $id !== null) {
        [$ok, $texto] = tipologias_eliminar($id);
        $_SESSION['flash_tipologias'] = [$ok ? 'success' : 'error', $texto];
    }
    header('Location: admin_tipologias.php'); exit;
}

$flash = $_SESSION['flash_tipologias'] ?? null; unset($_SESSION['flash_tipologias']);
$borrador = $_SESSION['form_tipologias'] ?? null; unset($_SESSION['form_tipologias']);

$tipos = tipologias_todas();
$editando = null;
if (isset($_GET['editar'])) $editando = tipologias_obtener((int)$_GET['editar']);
if ($borrador) $editando = array_merge($editando ?? [], $borrador);
$es_edicion = $editando && !empty($editando['id']);
$val = fn(string $k, string $def = '') => htmlspecialchars((string)($editando[$k] ?? $def));

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <?php parametrizacion_tabs('tipologias'); ?>

  <?php if ($flash): ?>
    <div class="alert alert--<?php echo $flash[0] === 'success' ? 'success' : 'error'; ?>" role="<?php echo $flash[0] === 'success' ? 'status' : 'alert'; ?>"><?php echo htmlspecialchars($flash[1]); ?></div>
  <?php endif; ?>

  <div class="op-head">
  <p class="op-intro">Agrupa los tipos de novedad (Operativa, Seguridad, Calidad…). Solo las <strong>activas</strong> se ofrecen al registrar una novedad y al crear tipos de novedad.</p>
  <button type="button" class="btn btn--primary btn--sm" id="btn-nuevo">+ Nueva tipología</button>
  </div>


  <!-- Lista -->
  <div class="table-wrap">
    <table class="op-table">
      <caption class="visually-hidden">Tipologías</caption>
      <thead>
        <tr><th scope="col">Sigla</th><th scope="col">Nombre</th><th scope="col">Estado</th><th scope="col"><span class="visually-hidden">Acciones</span></th></tr>
      </thead>
      <tbody>
      <?php foreach ($tipos as $t):
        $activo = (int)$t['estado'] === 1;
      ?>
        <tr class="<?php echo $activo ? '' : 'op-row--inactivo'; ?>">
          <td><code class="op-code"><?php echo htmlspecialchars($t['codigo']); ?></code></td>
          <td>
            <span class="op-name"><?php echo htmlspecialchars($t['nombre']); ?></span>
            <?php if (!empty($t['descripcion'])): ?><span class="op-desc"><?php echo htmlspecialchars($t['descripcion']); ?></span><?php endif; ?>
          </td>
          <td><span class="op-badge <?php echo $activo ? 'op-badge--activo' : 'op-badge--inactivo'; ?>"><?php echo $activo ? 'Activo' : 'Inactivo'; ?></span></td>
          <td>
            <div class="op-actions">
              <button type="button" class="op-btn" data-editar="<?php echo (int)$t['id']; ?>" aria-label="Editar <?php echo htmlspecialchars($t['nombre']); ?>">Editar</button>
              <form method="POST" action="admin_tipologias.php"
                    <?php if ($activo): ?>data-confirm-title="Inactivar tipología" data-confirm="«<?php echo htmlspecialchars($t['nombre'], ENT_QUOTES); ?>» dejará de ofrecerse al registrar novedades. Las novedades ya creadas se conservan." data-confirm-ok="Inactivar"<?php endif; ?>>
                <input type="hidden" name="accion" value="estado">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                <button type="submit" class="op-btn <?php echo $activo ? 'op-btn--warn' : ''; ?>"><?php echo $activo ? 'Inactivar' : 'Activar'; ?></button>
              </form>
              <form method="POST" action="admin_tipologias.php"
                    data-confirm-title="Eliminar tipología" data-confirm="Se eliminará «<?php echo htmlspecialchars($t['nombre'], ENT_QUOTES); ?>». Solo es posible si ningún tipo de novedad ni novedad la usa; esta acción no se puede deshacer." data-confirm-ok="Eliminar">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                <button type="submit" class="op-btn op-btn--danger">Eliminar</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Diálogo: crear / editar -->
<dialog class="pz-dialog" id="dlg-item" aria-labelledby="dlg-item-titulo">
  <form method="POST" action="admin_tipologias.php" id="form-tipologia" autocomplete="off">
    <header class="pz-dialog__head"><h2 id="dlg-item-titulo">Nueva tipología</h2><button type="button" class="pz-x" data-cerrar aria-label="Cerrar">&times;</button></header>
    <div class="pz-dialog__body">
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
    <input type="hidden" name="id" id="f-id" value="">
    <div class="op-form__grid">
      <div class="op-field op-field--sm">
        <label for="op-codigo">Sigla <span class="req">*</span></label>
        <input type="text" id="op-codigo" name="codigo" maxlength="10" required placeholder="SEG" class="op-upper">
      </div>
      <div class="op-field op-field--lg">
        <label for="op-nombre">Nombre de la tipología <span class="req">*</span></label>
        <input type="text" id="op-nombre" name="nombre" maxlength="60" required placeholder="Seguridad">
      </div>
      <div class="op-field op-field--sm">
        <label for="op-estado">Estado</label>
        <select id="op-estado" name="estado">
          <option value="1">Activo</option>
          <option value="0">Inactivo</option>
        </select>
      </div>
      <div class="op-field op-field--full">
        <label for="op-desc">Descripción <span class="op-opt">(opcional)</span></label>
        <textarea id="op-desc" name="descripcion" rows="2" maxlength="300" placeholder="Qué tipo de novedades agrupa"></textarea>
      </div>
    </div>
    </div>
    <footer class="pz-dialog__foot">
      <button type="button" class="btn btn--outline btn--sm" data-cerrar>Cancelar</button>
      <button type="submit" class="btn btn--primary btn--sm" id="btn-guardar-item">Guardar</button>
    </footer>
  </form>
</dialog>

<script>
(function () {
    var ITEMS = <?php echo json_encode(array_column($tipos, null, 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var ABRIR = <?php echo $editando ? json_encode($editando, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) : 'null'; ?>;
    var dlg = document.getElementById('dlg-item'), $ = function (id) { return document.getElementById(id); };
    document.querySelectorAll('[data-cerrar]').forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
    function abrir(d) {
        d = d || {};
        var edita = !!d.id;
        $('dlg-item-titulo').textContent = edita ? 'Editar tipología' : 'Nueva tipología';
        $('btn-guardar-item').textContent = edita ? 'Actualizar' : 'Guardar';
        $('f-id').value = d.id || '';
        $('op-codigo').value = d.codigo || '';
        $('op-nombre').value = d.nombre || '';
        $('op-desc').value = d.descripcion || '';
        $('op-estado').value = String(d.estado === undefined ? 1 : d.estado);
        dlg.showModal();
        $('op-codigo').focus();
    }
    $('btn-nuevo').addEventListener('click', function () { abrir(null); });
    document.querySelectorAll('[data-editar]').forEach(function (b) { b.addEventListener('click', function () { abrir(ITEMS[b.dataset.editar]); }); });
    if (ABRIR) abrir(ABRIR);
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
