<?php
$page_title = 'Detalle de Novedad';
$css_file   = 'formulario.css';
$css_extra  = ['detalle_novedad.css'];
require_once __DIR__ . '/../includes/auth.php';
verificar_sesion();
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/tipologias.php';
require_once __DIR__ . '/../includes/tipos_operacion.php';

require_once __DIR__ . '/../includes/dashboard_helpers.php';
$id    = $_GET['id'] ?? '';
$parcial = isset($_GET['parcial']);   // fragmento para el panel lateral del tablero
$nuevo = isset($_GET['nuevo']);

// Obtener novedad (JSON persistente primero, luego ejemplos). Si no existe o no es de esta cuenta, se trata igual: no se revela.
require_once __DIR__ . '/../includes/visibilidad.php';
$novedad = storage_todas_las_novedades()[$id] ?? null;
if ($novedad && !novedad_visible_para_sesion($novedad)) $novedad = null;
if (!$novedad) {
    if ($parcial) { http_response_code(404); echo '<p class="alert alert--error">La novedad no existe o fue eliminada.</p>'; exit; }
    header('Location: ../index.php'); exit;
}

// Marcar notificaciones como leídas (ya validado el acceso)
storage_marcar_leidas($_SESSION['usuario'], $id);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion  = $_POST['accion'] ?? '';
    $mensaje = trim($_POST['mensaje'] ?? '');

    // Cargar versión editable (del JSON o copiar desde ejemplos)
    $nov = storage_obtener_novedad($id);
    if (!$nov) {
        // Era un ejemplo, copiarlo al JSON para poder modificarlo
        $nov = $novedad;
    }

    if ($accion === 'responder' && puede_responder($nov) && $mensaje) {
        $nov['hilo'][] = ['autor'=>$_SESSION['usuario'],'nombre'=>$_SESSION['nombre'],'fecha'=>date('Y-m-d H:i'),'tipo'=>'respuesta','mensaje'=>$mensaje,'adjuntos'=>[]];
        if ($nov['estado'] === 'abierta') $nov['estado'] = 'en_gestion';
        storage_guardar_novedad($nov);
        // Notificar a todos los participantes excepto quien responde
        storage_notificar_respuesta($nov, $_SESSION['usuario']);
    }

    if ($accion === 'replicar' && es_reportante($nov) && $mensaje) {
        $nov['hilo'][] = ['autor'=>$_SESSION['usuario'],'nombre'=>$_SESSION['nombre'],'fecha'=>date('Y-m-d H:i'),'tipo'=>'replica','mensaje'=>$mensaje,'adjuntos'=>[]];
        storage_guardar_novedad($nov);
        // Notificar a todos los participantes excepto quien replica
        storage_notificar_respuesta($nov, $_SESSION['usuario']);
    }

    if ($accion === 'resolver' && puede_resolver($nov)) {
        if ($mensaje) {
            $nov['hilo'][] = ['autor'=>$_SESSION['usuario'],'nombre'=>$_SESSION['nombre'],'fecha'=>date('Y-m-d H:i'),'tipo'=>'cierre','mensaje'=>$mensaje,'adjuntos'=>[]];
        }
        $nov['estado']           = 'resuelta';
        $nov['resuelto_por']     = $_SESSION['usuario'];
        $nov['fecha_resolucion'] = date('Y-m-d H:i:s');
        storage_guardar_novedad($nov);
        // Notificar cierre a todos los participantes (alerta «Resuelta»)
        storage_notificar_respuesta($nov, $_SESSION['usuario'], 'resuelta');
    }

    if (!$parcial) {
        header('Location: detalle_novedad.php?id=' . urlencode($id));
        exit;
    }
    // Con el panel: se responde el fragmento ya actualizado (más abajo), sin redirección
}

// Re-fetch updated
$novedad     = storage_todas_las_novedades()[$id];
$puede_resp  = puede_responder($novedad);
$puede_res   = puede_resolver($novedad);
$es_rep      = es_reportante($novedad);
$back        = dash_volver_url();

if ($parcial) {
    header('Cache-Control: no-store');
    header('X-No-Leidas: ' . storage_contar_no_leidas($_SESSION['usuario']));
} else {
    include __DIR__ . '/../includes/header.php';
}
?>

<div class="dn-partial" data-id="<?php echo htmlspecialchars($id); ?>" data-estado="<?php echo htmlspecialchars($novedad['estado']); ?>">
<?php if ($nuevo): ?>
<div class="alert alert--success">Novedad registrada correctamente — ID <strong><?php echo htmlspecialchars($id); ?></strong>. Los gestores responsables han sido notificados.</div>
<?php endif; ?>

<div class="dn-x1">
    <?php if (!$parcial): ?><a href="<?php echo htmlspecialchars($back); ?>" class="btn btn--outline btn--sm">← Volver</a><?php endif; ?>
    <?php if ($puede_res && !in_array($novedad['estado'], ['resuelta','reporte'])): ?>
        <form method="POST" data-confirm-title="Marcar como resuelta" data-confirm="La novedad pasará a estado Resuelta y se notificará a los participantes." data-confirm-ok="Marcar como resuelta" data-confirm-style="primary">
            <input type="hidden" name="accion" value="resolver">
            <input type="hidden" name="mensaje" value="Novedad marcada como resuelta.">
            <button type="submit" class="btn btn--success btn--sm">Marcar como Resuelta</button>
        </form>
    <?php endif; ?>
</div>

<!-- Header de la novedad -->
<div class="card">
    <div class="dn-x2">
        <div>
            <h2 class="dn-x3"><?php echo htmlspecialchars(str_replace('_',' ',$novedad['tipo_novedad'])); ?></h2>
            <div class="dn-x4">
                <span class="badge badge--<?php echo $novedad['estado']; ?>"><?php echo ucfirst(str_replace('_',' ',$novedad['estado'])); ?></span>
                <span class="badge badge--reporte"><?php echo htmlspecialchars(implode(' · ', novedad_tipologias($novedad))); ?></span>
                <?php foreach ($novedad['tipo_operacion'] as $op): ?>
                    <span class="badge badge--en_gestion"><?php echo tipos_operacion_etiqueta([$op], true); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="dn-x5">
            <div><strong><?php echo htmlspecialchars($novedad['id']); ?></strong></div>
            <div><?php echo htmlspecialchars($novedad['organizacion']); ?> · <?php echo texto_visible($novedad['tipo_actor_reportante']); ?></div>
            <div><?php echo substr($novedad['fecha_creacion'],0,16); ?></div>
            <?php if (!empty($novedad['fecha_despacho'])): ?>
            <div>Despacho: <?php echo htmlspecialchars(trim($novedad['fecha_despacho'] . ' ' . ($novedad['hora_despacho'] ?? ''))); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // Buscar descripcion del tipo de novedad en el catalogo
    $cat_desc = '';
    $catalogo_custom = storage_catalogo_completo();
    if (!empty($catalogo_custom[$novedad['tipo_novedad']]['descripcion'])) {
        $cat_desc = $catalogo_custom[$novedad['tipo_novedad']]['descripcion'];
    }
    if ($cat_desc):
    ?>
    <div class="dn-x6">
        <div class="dn-x7">
            Contexto — Por que se crea esta novedad
        </div>
        <div class="dn-x8"><?php echo htmlspecialchars($cat_desc); ?></div>
    </div>
    <?php endif; ?>

    <?php
    $causal  = $novedad['causal']  ?? $novedad['causal1'] ?? '';
    $causal2 = $novedad['causal2'] ?? '';
    if ($causal || $causal2):
    ?>
    <div class="dn-x9">
        <?php if ($causal): ?>
            <span class="text-muted"><strong>Causal:</strong> <?php echo htmlspecialchars($causal); ?></span>
        <?php endif; ?>
        <?php if ($causal2): ?>
            <span class="text-muted"><strong>Causa 2:</strong> <?php echo htmlspecialchars($causal2); ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Datos del evento -->
    <?php
    // Eje, tipo, subtipo y nombre: si la novedad los guardó vacíos, se completan con el catálogo del tipo; no se muestran filas sin valor
    $_cat_tipo = storage_catalogo_completo()[$novedad['tipo_novedad']] ?? [];
    $_desde_cat = ['Eje' => $_cat_tipo['eje'] ?? '', 'Tipo' => $_cat_tipo['tipo_cls'] ?? '', 'Subtipo' => $_cat_tipo['subtipo'] ?? '', 'Nombre' => str_replace('_', ' ', $novedad['tipo_novedad'])];
    $datos_vista = [];
    foreach (($novedad['datos'] ?? []) as $_k => $_v) {
        if (($_v === '' || $_v === null || $_v === []) && isset($_desde_cat[$_k])) $_v = $_desde_cat[$_k];
        if ($_v === '' || $_v === null || $_v === []) continue;
        $datos_vista[$_k] = $_v;
    }
    ?>
    <?php if (!empty($datos_vista)): ?>
    <div class="dn-x10">
        <p class="dn-x11">Datos del Evento</p>
        <div class="dn-x12">
        <?php foreach ($datos_vista as $campo => $valor): ?>
            <div>
                <span class="dn-x13"><?php echo htmlspecialchars($campo); ?></span>
                <span class="dn-x14"><?php echo is_array($valor) ? htmlspecialchars(implode(', ',$valor)) : htmlspecialchars($valor); ?></span>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Gestores responsables -->
    <?php $resp_usuarios = $novedad['respondentes_usuarios'] ?? []; ?>
    <?php if (!empty($resp_usuarios) || !empty($novedad['respondentes'])): ?>
    <div class="dn-x15">
        <p class="dn-x16">Gestores Notificados</p>
        <?php if (!empty($resp_usuarios)): ?>
            <?php foreach ($resp_usuarios as $ru): ?>
                <?php
                // Nombre, actor y organización: de los destinatarios guardados con la novedad o, si no, del registro de usuarios
                $_dest = []; foreach (($novedad['destinatarios'] ?? []) as $_d) if (is_array($_d) && !empty($_d['usuario'])) $_dest[$_d['usuario']] = $_d;
                $_uid = is_array($ru) ? ($ru['usuario'] ?? '') : (string)$ru;
                $_reg = $_dest[$_uid] ?? (usuarios_obtener($_uid) ?: []);
                $u = is_array($ru) ? $ru : ['usuario'=>$_uid,'nombre'=>$_reg['nombre'] ?? $_uid,'tipo_actor'=>$_reg['tipo_actor'] ?? '','organizacion'=>$_reg['organizacion'] ?? ''];
                $_rol = isset($_dest[$_uid]) ? (!empty($_dest[$_uid]['principal']) ? 'Principal' : 'Copia') : '';
            ?>
                <div class="dn-x17">
                    <span class="dn-x14"><?php echo htmlspecialchars($u['nombre'] ?? $u['usuario'] ?? $ru); ?></span>
                    <?php if ($_rol): ?><span class="badge badge--reporte"><?php echo $_rol; ?></span><?php endif; ?>
                    <?php if (!empty($u['tipo_actor'])): ?>
                        <span class="dn-x18"><?php echo texto_visible($u['tipo_actor']); ?> — <?php echo htmlspecialchars($u['organizacion'] ?? ''); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="dn-x19">Actores: <strong><?php echo implode(', ', $novedad['respondentes']); ?></strong></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($novedad['estado'] === 'resuelta' && $novedad['resuelto_por']): ?>
    <div class="alert alert--success mt-2">Resuelta por <strong><?php $__r = usuarios_obtener((string)$novedad['resuelto_por']); echo texto_visible($__r['nombre'] ?? $novedad['resuelto_por']); ?></strong> el <?php echo substr($novedad['fecha_resolucion'],0,16); ?></div>
    <?php endif; ?>
</div>

<!-- Hilo -->
<div class="card">
    <h2>Hilo de Conversación</h2>

    <?php
    $tipo_bg   = ['reporte'=>'#dbeafe','respuesta'=>'#dcfce7','replica'=>'#fef9c3','cierre'=>'#f3e8ff'];
    $tipo_label = ['reporte'=>'Reporte','respuesta'=>'Respuesta','replica'=>'Réplica','cierre'=>'Cierre'];
    foreach ($novedad['hilo'] as $msg):
        $bg   = $tipo_bg[$msg['tipo']]   ?? '#f3f4f6';
        $icon = mb_strtoupper(mb_substr(trim($msg['nombre']), 0, 1)); $etiqueta = $tipo_label[$msg['tipo']] ?? 'Mensaje';
    ?>
    <div class="dn-x20">
        <div class="dn-x21" aria-hidden="true"><?php echo $icon; ?></div>
        <div style="flex:1;background:<?php echo $bg; ?>;border-radius:0 8px 8px 8px;padding:.75rem 1rem;border:1px solid rgba(0,0,0,.06)">
            <div class="dn-x22">
                <strong class="dn-x23"><?php echo htmlspecialchars($msg['nombre']); ?></strong> <span class="dn-x24"><?php echo $etiqueta; ?></span>
                <span class="dn-x18"><?php echo htmlspecialchars($msg['fecha']); ?></span>
            </div>
            <p class="dn-x25"><?php echo nl2br(htmlspecialchars($msg['mensaje'])); ?></p>
            <?php if (!empty($msg['adjuntos'])): ?>
            <div class="dn-x26">
                <?php foreach ($msg['adjuntos'] as $adj): ?>
                    <span class="dn-x27">Adjunto: <?php echo htmlspecialchars($adj); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Acciones según estado y perfil -->
    <?php if ($novedad['estado'] === 'reporte'): ?>
        <div class="alert alert--info">Esta novedad es solo de registro. No requiere respuesta.</div>

    <?php elseif ($novedad['estado'] === 'resuelta'): ?>
        <div class="alert alert--success">Esta novedad ha sido resuelta.</div>

    <?php else: ?>
        <?php if (!$puede_resp && novedad_rol_destinatario($novedad, $_SESSION['usuario']) === 'copia'): ?>
        <div class="alert alert--info">Estás en copia de esta novedad: puedes seguirla y recibirás los avisos de cada respuesta y de su cierre, pero no responder.</div>
        <?php endif; ?>
        <?php if ($puede_resp && $novedad['estado'] === 'abierta'): ?>
        <form class="dn-x28" method="POST">
            <input type="hidden" name="accion" value="responder">
            <div class="form-group">
                <label>Respuesta del Gestor</label>
                <textarea name="mensaje" class="form-control" rows="3" required placeholder="Escribe tu respuesta o plan de acción…"></textarea>
            </div>
            <button type="submit" class="btn btn--primary">Enviar Respuesta</button>
        </form>
        <?php endif; ?>

        <?php if ($puede_resp && $novedad['estado'] === 'en_gestion'): ?>
        <form class="dn-x28" method="POST">
            <input type="hidden" name="accion" value="responder">
            <div class="form-group">
                <label>Actualización del Gestor</label>
                <textarea name="mensaje" class="form-control" rows="2" required placeholder="Agregar actualización…"></textarea>
            </div>
            <div class="dn-x29">
                <button type="submit" class="btn btn--secondary">Actualizar</button>
                <?php if ($puede_res): ?>
                <button type="submit" form="form-resolver" class="btn btn--success">Resolver Novedad</button>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($es_rep && $novedad['estado'] === 'en_gestion'): ?>
        <form class="dn-x30" method="POST">
            <input type="hidden" name="accion" value="replicar">
            <div class="form-group">
                <label>Tu réplica</label>
                <textarea name="mensaje" class="form-control" rows="2" required placeholder="La respuesta recibida no resuelve el problema porque…"></textarea>
            </div>
            <button type="submit" class="btn btn--outline">Enviar Réplica</button>
        </form>
        <?php endif; ?>

        <?php if ($puede_res && $novedad['estado'] !== 'resuelta'): ?>
        <form method="POST" id="form-resolver" style="display:none" data-confirm-title="Marcar como resuelta" data-confirm="La novedad pasará a estado Resuelta y se notificará a los participantes." data-confirm-ok="Marcar como resuelta" data-confirm-style="primary">
            <input type="hidden" name="accion" value="resolver">
            <input type="hidden" name="mensaje" value="Novedad marcada como resuelta.">
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

</div>
<?php if (!$parcial) include __DIR__ . '/../includes/footer.php'; ?>
