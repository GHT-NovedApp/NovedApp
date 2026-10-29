<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'NovedApp'); ?> — NovedApp</title>
    <link rel="stylesheet" href="../css/main.css?v=<?php echo filemtime(__DIR__.'/../css/main.css'); ?>">
    <?php if (isset($css_file)): ?>
        <link rel="stylesheet" href="../css/<?php echo htmlspecialchars($css_file); ?>?v=<?php echo filemtime(__DIR__.'/../css/'.htmlspecialchars($css_file)); ?>">
    <?php endif; ?>
    <?php foreach ($css_extra ?? [] as $_css): ?>
        <link rel="stylesheet" href="../css/<?php echo htmlspecialchars($_css); ?>?v=<?php echo filemtime(__DIR__.'/../css/'.$_css); ?>">
    <?php endforeach; ?>
</head>
<body>
<?php
// Cargar notificaciones si hay sesión
$_notif_count = 0;
$_notifs = [];
if (isset($_SESSION['usuario'])) {
    if (!function_exists('storage_contar_no_leidas')) {
        require_once __DIR__ . '/storage.php';
    }
    $_notif_count = storage_contar_no_leidas($_SESSION['usuario']);
    $_notifs = array_slice(storage_notificaciones_usuario($_SESSION['usuario']), 0, 8);
}
?>
<header class="site-header">
    <div class="site-header__inner">
        <a href="../index.php" class="site-header__brand">
            <img src="../assets/logo_ght.jpeg" alt="GHT" class="site-header__logo">
            <span>NovedApp</span>
        </a>

        <?php $_param_activa = in_array(basename($_SERVER["SCRIPT_NAME"]), ['admin_usuarios.php','admin_organizaciones.php','admin_novedades.php','admin_operaciones.php','admin_tipologias.php','admin_permisos.php'], true);
        $_script_actual = basename($_SERVER["SCRIPT_NAME"]); $_cur = fn($p) => $_script_actual === $p ? " aria-current=\"page\"" : ""; ?>
        <nav class="site-header__nav" aria-label="Principal">
            <?php if (isset($_SESSION['perfil'])):
                $_dash = ['administrador' => ['dashboard_admin.php', 'Dashboard'], 'torre_control' => ['dashboard_torre.php', 'Dashboard'],
                          'gestor' => ['dashboard_gestor.php', 'Mis Novedades']][$_SESSION['perfil']] ?? ['dashboard_reportante.php', 'Mis Novedades']; ?>
                <?php if (tiene_capacidad('ver_dashboard')): ?>
                    <a href="../pages/<?php echo $_dash[0]; ?>" class="nav-link"<?php echo $_cur($_dash[0]); ?>><?php echo $_dash[1]; ?></a>
                <?php endif; ?>
                <?php if (puede_parametrizar()): ?>
                    <a href="../pages/<?php echo parametrizacion_primera_seccion(); ?>" class="nav-link"<?php echo $_param_activa ? ' aria-current="page"' : ''; ?>>Parametrización</a>
                <?php endif; ?>
                <?php if (tiene_capacidad('crear_novedades')): ?>
                <a href="../pages/nueva_novedad.php" class="nav-link nav-link--cta"<?php echo $_cur("nueva_novedad.php"); ?>>+ Nueva Novedad</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="site-header__user">
            <?php if (isset($_SESSION['usuario'])): ?>

                <!-- Campana de notificaciones -->
                <div class="notif-wrap">
                    <button class="notif-btn" id="notif-btn" type="button" aria-label="Notificaciones<?php echo $_notif_count > 0 ? ", $_notif_count sin leer" : ""; ?>" aria-haspopup="true" aria-expanded="false" aria-controls="notif-panel">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span class="notif-badge" id="notif-badge-count" <?php if ($_notif_count === 0) echo 'style="display:none"'; ?>>
                            <?php echo min($_notif_count, 99); ?>
                        </span>
                    </button>

                    <div class="notif-panel" id="notif-panel">
                        <!-- Cabecera -->
                        <div class="notif-panel__header">
                            <span>Notificaciones
                                <span id="notif-unread-label" class="notif-panel__sub">
                                    <?php echo $_notif_count > 0 ? '('.$_notif_count.' sin leer)' : ''; ?>
                                </span>
                            </span>
                            <div class="notif-panel__actions">
                                <button type="button" class="notif-btn-text" onclick="notifMarcarTodas()">Marcar todas como leídas</button>
                                <button type="button" class="notif-btn-text notif-btn-danger" onclick="notifLimpiarTodas()">Limpiar todo</button>
                            </div>
                        </div>

                        <!-- Filtros rapidos -->
                        <div class="notif-panel__filters">
                            <button type="button" class="notif-filter-tab active" id="nf-todas"    onclick="notifFiltrar('todas',this)">Todas</button>
                            <button type="button" class="notif-filter-tab"       id="nf-noleidas" onclick="notifFiltrar('noleidas',this)">Sin leer</button>
                        </div>

                        <!-- Lista -->
                        <div id="notif-list" class="notif-list">
                        <?php if (empty($_notifs)): ?>
                            <div class="notif-empty" id="notif-empty">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.3;display:block;margin:0 auto .4rem"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                Sin notificaciones
                            </div>
                        <?php else: ?>
                            <?php foreach ($_notifs as $notif): ?>
                            <div class="notif-item2 <?php echo !($notif['leida']??false) ? 'unread' : ''; ?>"
                                 data-id="<?php echo htmlspecialchars($notif['novedad_id']); ?>">
                                <a href="../pages/detalle_novedad.php?id=<?php echo urlencode($notif['novedad_id']); ?>"
                                   class="notif-item2__content">
                                    <div class="notif-item2__title"><?php echo htmlspecialchars(str_replace('_',' ',$notif['tipo_novedad'])); ?>
                                        <?php if (($notif['evento'] ?? '') === 'resuelta'): ?><span class="badge badge--resuelta">Resuelta</span><?php endif; ?></div>
                                    <div class="notif-item2__sub"><?php echo htmlspecialchars($notif['organizacion']); ?></div>
                                    <div class="notif-item2__date"><?php echo substr($notif['fecha'],0,16); ?></div>
                                </a>
                                <div class="notif-item2__actions">
                                    <button type="button" class="notif-act-btn"
                                            onclick="notifToggle('<?php echo htmlspecialchars($notif['novedad_id']); ?>',this)">
                                        <?php echo ($notif['leida']??false) ? 'No leída' : 'Leída'; ?>
                                    </button>
                                    <button type="button" class="notif-act-btn notif-act-btn--del"
                                            onclick="notifEliminar('<?php echo htmlspecialchars($notif['novedad_id']); ?>',this)">
                                        Limpiar
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php
                $perfil_labels = [
                    'administrador' => 'Admin',
                    'torre_control' => 'Torre de Control',
                    'gestor'        => 'Gestor',
                    'reportante'    => 'Reportante',
                ];
                $plabel = $perfil_labels[$_SESSION['perfil'] ?? ''] ?? ucfirst($_SESSION['perfil'] ?? '');
                ?>
                <div class="user-chip">
                    <span class="user-chip__badge user-chip__badge--<?php echo htmlspecialchars($_SESSION['perfil']); ?>">
                        <?php echo $plabel; ?>
                    </span>
                    <span class="user-chip__name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
                    <span class="user-chip__org"><?php echo htmlspecialchars($_SESSION['tipo_actor'].' · '.$_SESSION['organizacion']); ?></span>
                </div>
                <a href="../logout.php" class="btn-logout">Salir</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
(function() {
    var btn   = document.getElementById('notif-btn');
    var panel = document.getElementById('notif-panel');
    if (!btn || !panel) return;

    // Toggle panel
    btn.addEventListener('click', function(e) { e.stopPropagation(); btn.setAttribute('aria-expanded', panel.classList.toggle('open') ? 'true' : 'false'); });
    document.addEventListener('click', function() { panel.classList.remove('open'); btn.setAttribute('aria-expanded','false'); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && panel.classList.contains('open')) { panel.classList.remove('open'); btn.setAttribute('aria-expanded','false'); btn.focus(); } });
    panel.addEventListener('click', function(e) { e.stopPropagation(); });

    // AJAX helper
    function notifAjax(data, cb) {
        var base = window.location.pathname.replace(/\/pages\/.*|\/[^/]*$/, '');
        fetch(base + '/ajax/notificaciones.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: Object.keys(data).map(k => k+'='+encodeURIComponent(data[k])).join('&')
        })
        .then(function(r){ return r.json(); })
        .then(function(d){ if(d.status==='ok' && cb) cb(d); })
        .catch(function(e){ console.warn('Notif AJAX error',e); if (window.mostrarToast) window.mostrarToast('No se pudo actualizar la notificación. Intenta de nuevo.'); });
    }

    function actualizarBadge(count) {
        var badge = document.getElementById('notif-badge-count');
        var label = document.getElementById('notif-unread-label');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = Math.min(count, 99);
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
        if (label) label.textContent = count > 0 ? '(' + count + ' sin leer)' : '';
    }

    // Toggle leido
    window.notifToggle = function(novId, el) {
        notifAjax({accion:'toggle_leido', novedad_id:novId}, function(d) {
            var item = el.closest('.notif-item2');
            item.classList.toggle('unread');
            // Swap button text: si ahora es unread -> boton dice "Leida", si es read -> "No leida"
            el.textContent = item.classList.contains('unread') ? 'Leída' : 'No leída';
            actualizarBadge(d.no_leidas);
        });
    };

    // Eliminar notificacion
    window.notifEliminar = function(novId, el) {
        notifAjax({accion:'eliminar', novedad_id:novId}, function(d) {
            var item = el.closest('.notif-item2');
            item.style.transition = 'opacity .2s';
            item.style.opacity = '0';
            setTimeout(function() {
                item.remove();
                actualizarBadge(d.no_leidas);
                if (!document.querySelector('.notif-item2')) {
                    var list = document.getElementById('notif-list');
                    if (list) list.innerHTML = '<div class="notif-empty">Sin notificaciones</div>';
                }
            }, 200);
        });
    };

    // Marcar todas como leídas
    window.notifMarcarTodas = function() {
        notifAjax({accion:'marcar_todas_leidas'}, function(d) {
            document.querySelectorAll('.notif-item2.unread').forEach(function(item) {
                item.classList.remove('unread');
                // Al quedar leida, el boton debe decir "No leida" (para poder revertir)
                var btn = item.querySelector('.notif-act-btn:first-child');
                if (btn) btn.textContent = 'No leída';
            });
            actualizarBadge(0);
        });
    };

    // Limpiar todas
    window.notifLimpiarTodas = function() {
        confirmarAccion({title:'Limpiar notificaciones', text:'Se eliminarán todas tus notificaciones, leídas y sin leer. Esta acción no se puede deshacer.', ok:'Limpiar todo'}, function() {
        notifAjax({accion:'limpiar_todas'}, function(d) {
            var list = document.getElementById('notif-list');
            if (list) list.innerHTML = '<div class="notif-empty">Sin notificaciones</div>';
            actualizarBadge(0);
        });
        });
    };

    // Filtro rapido
    window.notifFiltrar = function(tipo, tabEl) {
        document.querySelectorAll('.notif-filter-tab').forEach(function(t) { t.classList.remove('active'); });
        tabEl.classList.add('active');
        document.querySelectorAll('.notif-item2').forEach(function(item) {
            if (tipo === 'noleidas') {
                item.classList.toggle('hidden-filter', !item.classList.contains('unread'));
            } else {
                item.classList.remove('hidden-filter');
            }
        });
    };
})();
</script>

<main class="container">

