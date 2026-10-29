<?php
$page_title = 'Parametrización — Usuarios';
$css_file   = 'dashboard.css';
$css_extra  = ['parametrizacion.css'];
require_once __DIR__ . '/../includes/parametrizacion.php';
$puede_editar = verificar_parametrizacion('usuarios');
require_once __DIR__ . '/../includes/organizaciones_store.php';

$yo = ['usuario' => $_SESSION['usuario'], 'perfil' => $_SESSION['perfil']];
$soy_admin = $yo['perfil'] === 'administrador';
$csrf = csrf_token('usuarios');

// ── Acciones (POST → flash → redirección) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido('usuarios')) {
        flash_poner('usuarios', false, 'La sesión del formulario venció. Vuelve a intentarlo.');
        header('Location: admin_usuarios.php'); exit;
    }
    $accion  = $_POST['accion'] ?? '';
    $usuario = trim((string)($_POST['usuario_original'] ?? ''));

    if ($accion === 'guardar') {
        $original = $usuario !== '' ? $usuario : null;
        $previo   = $original !== null ? usuarios_obtener($original) : null;
        $actor    = trim((string)($_POST['tipo_actor'] ?? ''));
        $org      = trim((string)($_POST['organizacion'] ?? ''));
        // La organización debe estar registrada y activa para ese tipo de actor (o ser la que el usuario ya tenía)
        $validas  = array_column(organizaciones_por_tipo_activas()[$actor] ?? [], 'nombre');
        $sin_cambio = $previo && $previo['tipo_actor'] === $actor && $previo['organizacion'] === $org;
        if ($actor !== '' && $org !== '' && !in_array($org, $validas, true) && !$sin_cambio) {
            [$ok, $texto] = [false, 'La organización elegida no está registrada o activa para ese tipo de actor.'];
        } else {
            [$ok, $texto] = usuarios_guardar($_POST, $original, $yo);
        }
        flash_poner('usuarios', $ok, $texto);
        if (!$ok) {
            $_SESSION['form_usuarios'] = array_diff_key($_POST, ['password' => 1, 'csrf' => 1]);
        }
    } elseif ($accion === 'password') {
        [$ok, $texto] = usuarios_cambiar_password($usuario, (string)($_POST['password'] ?? ''), $yo);
        flash_poner('usuarios', $ok, $texto);
    } elseif ($accion === 'estado') {
        [$ok, $texto] = usuarios_cambiar_estado($usuario, $yo);
        flash_poner('usuarios', $ok, $texto);
    }
    header('Location: admin_usuarios.php'); exit;
}

$flash    = flash_tomar('usuarios');
$borrador = $_SESSION['form_usuarios'] ?? null; unset($_SESSION['form_usuarios']);

$usuarios = usuarios_todos();
$bloqueados = usuarios_con_historial(array_keys($usuarios));
$orgs_por_tipo = organizaciones_por_tipo_activas();
$tipos_actor = organizaciones_tipos_actor();
$cargos = array_values(array_unique(array_filter(array_column($usuarios, 'rol_organizacional'))));
sort($cargos);

// Datos para el JS (sin contraseñas)
$js_usuarios = [];
foreach ($usuarios as $k => $u) {
    $js_usuarios[$k] = [
        'nombre' => $u['nombre'], 'cargo' => $u['rol_organizacional'], 'tipo_actor' => $u['tipo_actor'],
        'organizacion' => $u['organizacion'], 'perfil' => $u['perfil'], 'param' => !empty($u['puede_parametrizar']),
        'usuario_fijo' => in_array($k, $bloqueados, true),
        'privilegiado' => in_array($u['perfil'], ['administrador', 'torre_control'], true),
    ];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <?php parametrizacion_tabs('usuarios'); ?>
  <?php flash_mostrar($flash); ?>

  <div class="pz-toolbar">
    <div class="pz-search">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" id="pz-buscar" aria-label="Buscar usuarios" placeholder="Buscar por nombre, usuario, cargo u organización…">
    </div>
    <select id="pz-f-permiso" aria-label="Filtrar por permisos del sistema">
      <option value="">Todos los permisos</option>
      <?php foreach (PERMISOS_SISTEMA as $k => $p): ?><option value="<?php echo $k; ?>"><?php echo htmlspecialchars($p['label']); ?></option><?php endforeach; ?>
    </select>
    <select id="pz-f-estado" aria-label="Filtrar por estado">
      <option value="">Todos los estados</option><option value="1">Activos</option><option value="0">Inactivos</option>
    </select>
    <button type="button" class="btn btn--primary btn--sm pz-nuevo" id="pz-btn-nuevo">+ Nuevo usuario</button>
  </div>
  <p class="pz-count" id="pz-count" aria-live="polite"></p>

  <div class="table-wrap">
    <table class="pz-table" id="pz-tabla">
      <caption class="visually-hidden">Usuarios del sistema</caption>
      <thead>
        <tr>
          <th scope="col">Usuario</th>
          <th scope="col">Rol en la empresa</th>
          <th scope="col">Organización</th>
          <th scope="col">Permisos del sistema</th>
          <th scope="col">Estado</th>
          <th scope="col"><span class="visually-hidden">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($usuarios as $k => $u):
        $activo = !empty($u['activo']);
        $es_yo = $k === $yo['usuario'];
        $puedo_tocar = $soy_admin || !in_array($u['perfil'], ['administrador', 'torre_control'], true);
        $buscable = mb_strtolower($u['nombre'] . ' ' . $k . ' ' . $u['rol_organizacional'] . ' ' . $u['organizacion'] . ' ' . $u['tipo_actor']);
      ?>
        <tr class="<?php echo $activo ? '' : 'pz-row--inactivo'; ?>" data-buscar="<?php echo htmlspecialchars($buscable); ?>" data-perfil="<?php echo htmlspecialchars($u['perfil']); ?>" data-activo="<?php echo $activo ? 1 : 0; ?>">
          <td>
            <span class="pz-name"><?php echo htmlspecialchars($u['nombre']); ?><?php if ($es_yo): ?> <span class="pz-you">tú</span><?php endif; ?></span>
            <code class="pz-user"><?php echo htmlspecialchars($k); ?></code>
          </td>
          <td><?php echo $u['rol_organizacional'] !== '' ? '<span class="pz-badge pz-cargo">' . htmlspecialchars($u['rol_organizacional']) . '</span>' : '<span class="pz-empty">Sin definir</span>'; ?></td>
          <td>
            <span class="pz-org"><?php echo htmlspecialchars($u['organizacion'] ?: '—'); ?></span>
            <span class="pz-actor"><?php echo htmlspecialchars($u['tipo_actor']); ?></span>
          </td>
          <td>
            <?php echo badge_permiso($u['perfil']); ?>
            <?php if ($u['perfil'] === 'torre_control' && !empty($u['puede_parametrizar'])): ?><span class="pz-extra">+ parametriza</span><?php endif; ?>
          </td>
          <td><span class="pz-badge pz-estado <?php echo $activo ? 'pz-estado--activo' : 'pz-estado--inactivo'; ?>"><?php echo $activo ? 'Activo' : 'Inactivo'; ?></span></td>
          <td>
            <?php if ($puedo_tocar): ?>
            <div class="pz-actions">
              <button type="button" class="pz-btn" data-editar="<?php echo htmlspecialchars($k); ?>" aria-label="Editar <?php echo htmlspecialchars($u['nombre']); ?>">Editar</button>
              <button type="button" class="pz-btn" data-clave="<?php echo htmlspecialchars($k); ?>" aria-label="Cambiar contraseña de <?php echo htmlspecialchars($u['nombre']); ?>">Contraseña</button>
              <?php if (!$es_yo): ?>
              <form method="POST" action="admin_usuarios.php"
                    <?php if ($activo): ?>data-confirm-title="Inactivar usuario" data-confirm="<?php echo htmlspecialchars($u['nombre'] . ' (' . $k . ')', ENT_QUOTES); ?> no podrá iniciar sesión hasta que lo vuelvas a activar. Sus novedades se conservan." data-confirm-ok="Inactivar"<?php endif; ?>>
                <input type="hidden" name="accion" value="estado">
                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                <input type="hidden" name="usuario_original" value="<?php echo htmlspecialchars($k); ?>">
                <button type="submit" class="pz-btn <?php echo $activo ? 'pz-btn--warn' : ''; ?>"><?php echo $activo ? 'Inactivar' : 'Activar'; ?></button>
              </form>
              <?php endif; ?>
            </div>
            <?php else: ?>
              <span class="pz-empty">Solo un administrador</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="pz-empty-msg" id="pz-vacio" hidden>Ningún usuario coincide con los filtros.</p>
  </div>
</div>

<!-- Diálogo: crear / editar usuario -->
<dialog class="pz-dialog" id="dlg-usuario" aria-labelledby="dlg-usuario-titulo">
  <form method="POST" action="admin_usuarios.php" id="form-usuario" autocomplete="off">
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
    <input type="hidden" name="usuario_original" id="u-original" value="">
    <header class="pz-dialog__head"><h2 id="dlg-usuario-titulo">Nuevo usuario</h2><button type="button" class="pz-x" data-cerrar aria-label="Cerrar">&times;</button></header>
    <div class="pz-dialog__body">
      <fieldset class="pz-group">
        <legend>Cuenta</legend>
        <div class="pz-grid">
          <div class="pz-field"><label for="u-usuario">Usuario <span class="req">*</span></label><input type="text" id="u-usuario" name="usuario" maxlength="30" required placeholder="eai.manuelita"><small id="u-usuario-ayuda" class="pz-help" hidden>No se puede cambiar: ya tiene novedades asociadas.</small></div>
          <div class="pz-field" id="u-clave-wrap"><label for="u-clave">Contraseña inicial <span class="req">*</span></label><input type="password" id="u-clave" name="password" minlength="6" maxlength="72" autocomplete="new-password" placeholder="Mínimo 6 caracteres"></div>
          <div class="pz-field pz-field--full"><label for="u-nombre">Nombre completo <span class="req">*</span></label><input type="text" id="u-nombre" name="nombre" maxlength="80" required placeholder="Nombre y apellido"></div>
        </div>
      </fieldset>

      <fieldset class="pz-group">
        <legend>Rol en la empresa</legend>
        <p class="pz-group__hint">El cargo real de la persona y dónde trabaja. No define lo que puede hacer en NovedApp.</p>
        <div class="pz-grid">
          <div class="pz-field pz-field--full"><label for="u-cargo">Cargo <span class="pz-opt">(opcional)</span></label>
            <input type="text" id="u-cargo" name="rol_organizacional" maxlength="80" list="lista-cargos" placeholder="Analista SST, Coordinador logístico…">
            <datalist id="lista-cargos"><?php foreach ($cargos as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"><?php endforeach; ?></datalist>
          </div>
          <div class="pz-field"><label for="u-actor">Tipo de actor <span class="req">*</span></label>
            <select id="u-actor" name="tipo_actor" required><option value="">Seleccione…</option>
              <?php foreach ($tipos_actor as $t): ?><option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option><?php endforeach; ?>
            </select></div>
          <div class="pz-field"><label for="u-org">Organización / Finca <span class="req">*</span></label>
            <select id="u-org" name="organizacion" required disabled><option value="">Primero elige el tipo de actor</option></select>
            <small class="pz-help"><a href="admin_organizaciones.php">¿No aparece? Créala en Fincas y Organizaciones</a></small></div>
        </div>
      </fieldset>

      <fieldset class="pz-group" id="u-permisos-group">
        <legend>Permisos del sistema</legend>
        <p class="pz-group__hint" id="u-permisos-hint">Qué puede hacer la persona dentro de la aplicación.</p>
        <div class="pz-perms">
          <?php foreach (PERMISOS_SISTEMA as $k => $p): ?>
          <label class="pz-perm-card">
            <input type="radio" name="perfil" value="<?php echo $k; ?>" required>
            <span class="pz-perm-card__body"><?php echo badge_permiso($k); ?><span class="pz-perm-card__desc"><?php echo htmlspecialchars($p['desc']); ?></span></span>
          </label>
          <?php endforeach; ?>
        </div>
        <label class="pz-check" id="u-param-wrap" hidden><input type="checkbox" name="puede_parametrizar" value="1" id="u-param"> Permitir que esta cuenta de Torre de Control parametrice (usuarios, organizaciones y catálogos)</label>
      </fieldset>
    </div>
    <footer class="pz-dialog__foot">
      <button type="button" class="btn btn--outline btn--sm" data-cerrar>Cancelar</button>
      <button type="submit" class="btn btn--primary btn--sm" id="u-guardar">Guardar</button>
    </footer>
  </form>
</dialog>

<!-- Diálogo: cambiar contraseña -->
<dialog class="pz-dialog pz-dialog--sm" id="dlg-clave" aria-labelledby="dlg-clave-titulo">
  <form method="POST" action="admin_usuarios.php" autocomplete="off">
    <input type="hidden" name="accion" value="password">
    <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
    <input type="hidden" name="usuario_original" id="c-usuario" value="">
    <header class="pz-dialog__head"><h2 id="dlg-clave-titulo">Cambiar contraseña</h2><button type="button" class="pz-x" data-cerrar aria-label="Cerrar">&times;</button></header>
    <div class="pz-dialog__body">
      <p class="pz-group__hint" id="c-texto"></p>
      <div class="pz-field"><label for="c-clave">Nueva contraseña <span class="req">*</span></label><input type="password" id="c-clave" name="password" minlength="6" maxlength="72" required autocomplete="new-password" placeholder="Mínimo 6 caracteres"></div>
    </div>
    <footer class="pz-dialog__foot">
      <button type="button" class="btn btn--outline btn--sm" data-cerrar>Cancelar</button>
      <button type="submit" class="btn btn--primary btn--sm">Restablecer contraseña</button>
    </footer>
  </form>
</dialog>

<script>
(function () {
    var USUARIOS = <?php echo json_encode($js_usuarios, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var ORGS = <?php echo json_encode($orgs_por_tipo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var SOY_ADMIN = <?php echo $soy_admin ? 'true' : 'false'; ?>;
    var BORRADOR = <?php echo json_encode($borrador, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;

    var dlgU = document.getElementById('dlg-usuario'), dlgC = document.getElementById('dlg-clave');
    var $ = function (id) { return document.getElementById(id); };

    document.querySelectorAll('[data-cerrar]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });
    [dlgU, dlgC].forEach(function (d) { d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); });

    function cargarOrgs(actor, seleccionada) {
        var sel = $('u-org'), lista = (ORGS[actor] || []).slice();
        sel.innerHTML = '';
        if (!actor) { sel.disabled = true; sel.innerHTML = '<option value="">Primero elige el tipo de actor</option>'; return; }
        sel.disabled = false;
        var o0 = document.createElement('option'); o0.value = ''; o0.textContent = lista.length ? 'Seleccione…' : 'No hay organizaciones activas para este actor'; sel.appendChild(o0);
        var nombres = lista.map(function (o) { return o.nombre; });
        if (seleccionada && nombres.indexOf(seleccionada) === -1) lista.unshift({ nombre: seleccionada, ubicacion: '' , actual: true });
        lista.forEach(function (o) {
            var op = document.createElement('option'); op.value = o.nombre;
            op.textContent = o.nombre + (o.ubicacion ? ' · ' + o.ubicacion : '') + (o.actual ? ' (actual)' : '');
            if (o.nombre === seleccionada) op.selected = true; sel.appendChild(op);
        });
    }
    $('u-actor').addEventListener('change', function () { cargarOrgs(this.value, ''); });

    function actualizarParam() {
        var tc = document.querySelector('input[name="perfil"][value="torre_control"]');
        $('u-param-wrap').hidden = !(SOY_ADMIN && tc && tc.checked);
    }
    document.querySelectorAll('input[name="perfil"]').forEach(function (r) { r.addEventListener('change', actualizarParam); });

    function abrirUsuario(clave, borrador) {
        var u = clave ? USUARIOS[clave] : null, b = borrador || {};
        $('dlg-usuario-titulo').textContent = clave ? 'Editar usuario' : 'Nuevo usuario';
        $('u-guardar').textContent = clave ? 'Guardar cambios' : 'Crear usuario';
        $('u-original').value = clave || '';
        $('u-usuario').value = b.usuario !== undefined ? b.usuario : (clave || '');
        $('u-usuario').readOnly = !!(u && u.usuario_fijo);
        $('u-usuario-ayuda').hidden = !(u && u.usuario_fijo);
        $('u-clave-wrap').hidden = !!clave; $('u-clave').required = !clave; $('u-clave').value = '';
        $('u-nombre').value = b.nombre !== undefined ? b.nombre : (u ? u.nombre : '');
        $('u-cargo').value = b.rol_organizacional !== undefined ? b.rol_organizacional : (u ? u.cargo : '');
        var actor = b.tipo_actor !== undefined ? b.tipo_actor : (u ? u.tipo_actor : '');
        $('u-actor').value = actor;
        cargarOrgs(actor, b.organizacion !== undefined ? b.organizacion : (u ? u.organizacion : ''));
        var perfil = b.perfil !== undefined ? b.perfil : (u ? u.perfil : '');
        document.querySelectorAll('input[name="perfil"]').forEach(function (r) { r.checked = (r.value === perfil); r.disabled = !SOY_ADMIN; });
        $('u-permisos-hint').textContent = SOY_ADMIN ? 'Qué puede hacer la persona dentro de la aplicación.' : 'Solo un administrador puede cambiar los permisos del sistema.';
        $('u-param').checked = b.puede_parametrizar ? true : (u ? !!u.param : false);
        actualizarParam();
        dlgU.showModal();
        $('u-usuario').focus();
    }
    $('pz-btn-nuevo').addEventListener('click', function () { abrirUsuario(null); });
    document.querySelectorAll('[data-editar]').forEach(function (b) { b.addEventListener('click', function () { abrirUsuario(b.dataset.editar); }); });
    document.querySelectorAll('[data-clave]').forEach(function (b) {
        b.addEventListener('click', function () {
            var k = b.dataset.clave;
            $('c-usuario').value = k; $('c-clave').value = '';
            $('c-texto').textContent = 'Define una nueva contraseña para ' + USUARIOS[k].nombre + ' (' + k + '). La anterior dejará de funcionar.';
            dlgC.showModal(); $('c-clave').focus();
        });
    });

    // Si el guardado falló, se reabre el formulario con lo que el usuario había escrito
    if (BORRADOR) { abrirUsuario(BORRADOR.usuario_original || null, BORRADOR); }

    // Filtros de la tabla
    var filas = Array.prototype.slice.call(document.querySelectorAll('#pz-tabla tbody tr'));
    function filtrar() {
        var q = $('pz-buscar').value.trim().toLowerCase(), p = $('pz-f-permiso').value, e = $('pz-f-estado').value, n = 0;
        filas.forEach(function (tr) {
            var ok = (!q || tr.dataset.buscar.indexOf(q) !== -1) && (!p || tr.dataset.perfil === p) && (e === '' || tr.dataset.activo === e);
            tr.hidden = !ok; if (ok) n++;
        });
        $('pz-count').textContent = n + ' de ' + filas.length + ' usuarios';
        $('pz-vacio').hidden = n !== 0;
    }
    ['pz-buscar', 'pz-f-permiso', 'pz-f-estado'].forEach(function (id) { $(id).addEventListener('input', filtrar); });
    filtrar();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
