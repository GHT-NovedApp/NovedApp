<?php
date_default_timezone_set('America/Bogota');
$page_title    = 'Registrar Novedad o Evento de Riesgo';
$css_file      = 'formulario.css';
$css_extra     = ['nueva_novedad.css'];
$show_meta_bar = true; // activa barra Actor + Fecha en header.php
require_once __DIR__ . '/../includes/auth.php';
verificar_sesion();
exigir_capacidad('crear_novedades');
require_once __DIR__ . '/../includes/usuarios_store.php';
$usuarios = usuarios_todos();
require_once __DIR__ . '/../data/actores_operaciones.php';
require_once __DIR__ . '/../includes/tipos_operacion.php';
require_once __DIR__ . '/../includes/tipologias.php';
$tipos_op_activos = tipos_operacion_activos(); // solo estado = 1
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/enrutamiento.php';
// catalogo_novedades needed as fallback for CSV-based types
$catalogo_novedades = [];
if (file_exists(__DIR__ . '/../data/novedades.php')) {
    require_once __DIR__ . '/../data/novedades.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = trim($_POST['tipo_novedad'] ?? '');
    $cat  = $catalogo_novedades[$tipo] ?? [];

    // Merge con custom
    $custom = storage_catalogo_completo();
    if (isset($custom[$tipo])) $cat = $custom[$tipo];

    // Los modales de grupo viven fuera del <form>: sus datos llegan como JSON en campos ocultos
    foreach (['datos_terrestre','datos_aereo','datos_tiempos'] as $gk) {
        $gd = json_decode($_POST[$gk] ?? '', true);
        if (!is_array($gd)) continue;
        foreach ($gd as $k => $v) {
            $k = str_replace('[]', '', $k);
            if (!isset($_POST[$k]) || $_POST[$k] === '' ) $_POST[$k] = $v;
        }
    }

    $ops = [];
    $op = trim($_POST['tipo_operacion'] ?? '');
    $claves_op_activas = array_column($tipos_op_activos, 'clave');
    if ($op && in_array($op, $claves_op_activas, true)) $ops[] = $op; // solo tipos de operación activos

    // La clasificación (eje, tipo, subtipo, nombre) viene del catálogo del tipo de novedad, no del formulario
    $datos = ['Descripcion' => trim($_POST['descripcion'] ?? '')];
    foreach (['Eje' => $cat['eje'] ?? '', 'Tipo' => $cat['tipo_cls'] ?? '', 'Subtipo' => $cat['subtipo'] ?? '', 'Nombre' => str_replace('_', ' ', $tipo)] as $k => $v) {
        if (trim((string)$v) !== '') $datos[$k] = trim((string)$v);
    }
    if (!empty($_POST['exp_id'])) $datos['EXP ID'] = trim($_POST['exp_id']);

    // Terrestre
    if (!empty($_POST['ott']))       $datos['OTT']       = trim($_POST['ott']);
    if (!empty($_POST['placa']))     $datos['Placa']     = trim($_POST['placa']);
    if (!empty($_POST['conductor'])) $datos['Conductor'] = trim($_POST['conductor']);
    if (!empty($_POST['auxiliar']))  $datos['Auxiliar']  = trim($_POST['auxiliar']);
    if (!empty($_POST['cedula']))    $datos['Cedula']    = trim($_POST['cedula']);
    if (!empty($_POST['ota']))       $datos['OTA']       = trim($_POST['ota']);
    if (!empty($_POST['uc']))        $datos['UC']        = trim($_POST['uc']);
    foreach ($_POST['eai'] ?? [] as $eai) { if(trim($eai)) $datos['EAI'][] = trim($eai); }

    // Aereo
    if (!empty($_POST['mawb']))             $datos['MAWB']              = trim($_POST['mawb']);
    if (!empty($_POST['hawb']))             $datos['HAWB']              = trim($_POST['hawb']);
    if (!empty($_POST['num_vuelo']))        $datos['Vuelo']             = trim($_POST['num_vuelo']);
    if (!empty($_POST['ota_aereo']))        $datos['OTA (vuelo)']       = trim($_POST['ota_aereo']);
    if (!empty($_POST['piezas_reservadas']))$datos['Piezas Reservadas'] = trim($_POST['piezas_reservadas']);
    if (!empty($_POST['cajas_reservadas'])) $datos['Cajas Reservadas']  = trim($_POST['cajas_reservadas']);
    if (!empty($_POST['piezas_recibidas'])) $datos['Piezas Recibidas']  = trim($_POST['piezas_recibidas']);
    if (!empty($_POST['cajas_recibidas']))  $datos['Cajas Recibidas']   = trim($_POST['cajas_recibidas']);

    // Tiempos logísticos
    $tl_campos = ['hora_reporte','hora_est_arribo','arribo','llegada','inicio_cargue','fin_cargue','salida',
                  'total_cajas','cajas_cargadas','espera_porteria','tiempo_espera','pct_cargue','tpcc'];
    foreach ($tl_campos as $tc) {
        if (!empty($_POST[$tc])) $datos[str_replace('_',' ', ucwords($tc,'_'))] = trim($_POST[$tc]);
    }

    // Campos personalizados
    foreach ($_POST['campo_custom_nombre'] ?? [] as $i => $cn) {
        $cv = trim($_POST['campo_custom_valor'][$i] ?? '');
        if (trim($cn) && $cv) $datos[trim($cn)] = $cv;
    }

    // Informacion necesaria: cada campo obligatorio debe venir con valor (la clave en $datos es el nombre del campo)
    $faltantes = [];

    // Datos terrestres: requieren las DOS condiciones: (1) el tipo de novedad tiene habilitado el grupo terrestre en
    // Parametrización y (2) el usuario de "Dirigido a" (principal) es OTT (ni quien reporta ni las copias influyen). Cumplidas, son obligatorios.
    $actores_ott = ['OTT', 'OTT_TERCEROS'];
    if (!empty($cat['involucra_terrestre'])) {
        $hay_ott = false;   // solo cuenta el usuario de "Dirigido a" (principal); ni quien reporta ni los usuarios en copia
        $u_principal = trim((string)($_POST['destinatario_principal'] ?? ''));
        foreach ((json_decode((string)($_POST['destinatarios_json'] ?? '[]'), true) ?: []) as $d) {
            $u_dest = is_array($d) ? trim($d['usuario'] ?? '') : trim((string)$d);
            $es_principal = $u_dest !== '' && ($u_dest === $u_principal || (is_array($d) && !empty($d['principal'])));
            if (!$es_principal) continue;
            $u_reg  = usuarios_obtener($u_dest);   // el actor sale del registro, no del navegador
            if ($u_reg && in_array($u_reg['tipo_actor'], $actores_ott, true)) { $hay_ott = true; break; }
        }
        if ($hay_ott) {
            $etq = ['ott'=>'OTT / Transportador','placa'=>'Placa','conductor'=>'Conductor','auxiliar'=>'Auxiliar','cedula'=>'Cédula','ota'=>'OTA','uc'=>'UC','eai'=>'EAI'];
            $req = $cat['campos_grupo']['terrestre'] ?? array_keys($etq);
            foreach ($req as $k) {
                if (!isset($etq[$k])) continue;
                $v = $_POST[$k] ?? '';
                $vacio = is_array($v) ? !count(array_filter(array_map('trim', $v))) : trim((string)$v) === '';
                if ($vacio) $faltantes[] = 'Terrestre: ' . $etq[$k];
            }
        }
    }
    foreach ($cat['info_necesaria'] ?? [] as $inf) {
        $nom = trim($inf['nombre'] ?? '');
        if ($nom !== '' && empty($datos[$nom])) $faltantes[] = $nom;
    }
    if ($faltantes) {
        $error_info = 'Falta informacion necesaria: ' . implode(', ', $faltantes) . '.';
    } else {

    $actores_resp  = $cat['actores_respondentes'] ?? [];
    // 'reporte' = informativo sin respuesta requerida, 'abierta' = requiere atencion
    // Por defecto toda novedad nueva es 'abierta' a menos que sea explicitamente informativa
    $es_informativa = isset($cat['requiere_respuesta']) && $cat['requiere_respuesta'] === false;
    $estado         = $es_informativa ? 'reporte' : 'abierta';
    $nuevo_id      = storage_nuevo_id();

    // Destinatarios seleccionados en el formulario (JSON del campo oculto)
    $destinatarios_json = trim($_POST['destinatarios_json'] ?? '[]');
    $destinatarios      = json_decode($destinatarios_json, true) ?? [];

    // Extraer IDs de usuario como strings limpios — esto es lo que storage usa
    $usuarios_resp = array_values(array_unique(array_filter(array_map(function($d) {
        if (is_array($d))  return trim($d['usuario'] ?? '');
        if (is_string($d)) return trim($d);
        return '';
    }, $destinatarios))));

    $nueva = [
        'id'                    => $nuevo_id,
        'tipo_novedad'          => $tipo,
        // Si el tipo de novedad pertenece a varias tipologías, se guarda la que eligió quien reporta
        'tipologia'             => in_array(trim($_POST['tipologia'] ?? ''), novedad_tipologias($cat), true)
                                    ? trim($_POST['tipologia']) : (novedad_tipologias($cat)[0] ?? ''),
        // Todas las tipologías del tipo de novedad (la elegida arriba va primero)
        'tipologias'            => array_values(array_unique(array_merge(
                                    in_array(trim($_POST['tipologia'] ?? ''), novedad_tipologias($cat), true) ? [trim($_POST['tipologia'])] : [],
                                    novedad_tipologias($cat)))),
        'tipo_operacion'        => $ops ?: [$claves_op_activas[0] ?? 'exportacion'],
        'tipo_actor_reportante' => $_SESSION['tipo_actor'],
        'organizacion'          => $_SESSION['organizacion'],
        'usuario_reportante'    => $_SESSION['usuario'],
        'estado'                => $estado,
        'fecha_creacion'        => date('Y-m-d H:i:s'),
        'fecha_despacho'        => trim($_POST['fecha_despacho'] ?? ''),
        'hora_despacho'         => trim($_POST['hora_despacho']  ?? ''),
        'causal'                => trim($_POST['causal']  ?? '') ?: null,
        'datos'                 => $datos,
        'involucra_terrestre'   => $cat['involucra_terrestre'] ?? false,
        'involucra_vuelo'       => $cat['involucra_vuelo']     ?? false,
        'respondentes'          => $actores_resp,
        'respondentes_usuarios' => $usuarios_resp,
        'destinatarios'         => $destinatarios,
        'hilo' => [[
            'autor'    => $_SESSION['usuario'],
            'nombre'   => $_SESSION['nombre'],
            'fecha'    => date('Y-m-d H:i'),
            'tipo'     => 'reporte',
            'mensaje'  => trim($_POST['descripcion'] ?? '(Sin descripcion)'),
            'adjuntos' => [],
        ]],
        'resuelto_por'     => null,
        'fecha_resolucion' => null,
    ];

    storage_guardar_novedad($nueva);
    // Siempre notificar — storage_notificar_respondentes agrega admin/torre aunque $usuarios_resp este vacio
    storage_notificar_respondentes($nueva);

    header('Location: detalle_novedad.php?id=' . urlencode($nuevo_id) . '&nuevo=1');
    exit;
    }
}

$mi_actor     = $_SESSION['tipo_actor'];
$novedades_js = [];

// Solo novedades creadas en Parametrizacion (CSV base ya no aplica)
foreach (storage_catalogo_completo() as $titulo => $n) {
    if (!($n['activo'] ?? true)) continue;
    // Administrador y torre de control pueden registrar cualquier tipo de novedad; el resto, solo los que permiten reportar a su actor
    if (!puede_reportar_cualquiera() && !in_array($mi_actor, $n['actores_reportantes'] ?? [])) continue;
    $ar = $n['actores_respondentes'] ?? [];
    $novedades_js[$titulo] = [
        'tipologia'             => $n['tipologia'] ?? '',
        'tipologias'            => novedad_tipologias($n),
        'descripcion'           => $n['descripcion']          ?? '',
        'datos_minimos'         => $n['datos_minimos']        ?? '',
        'involucra_terrestre'   => $n['involucra_terrestre']  ?? false,
        'involucra_vuelo'       => $n['involucra_vuelo']      ?? false,
        'involucra_tiempos'     => $n['involucra_tiempos']    ?? false,
        'causales'              => $n['causales']              ?? [],
        'requiere_respuesta'    => $n['requiere_respuesta']    ?? false,
        'actores_respondentes'  => $ar,
        'usuarios_respondentes' => enrutamiento_obtener_respondentes_usuarios($ar),
        'actores_etiquetables'  => $n['actores_etiquetables'] ?? [],
        'usuarios_etiquetables' => enrutamiento_obtener_respondentes_usuarios($n['actores_etiquetables'] ?? []),
        'campos_grupo'          => $n['campos_grupo']          ?? new stdClass(),
        'campos_custom'         => $n['campos']                ?? [],
        'info_necesaria'        => $n['info_necesaria']        ?? [],
    ];
}

include __DIR__ . '/../includes/header.php';
?>
<?php if (!empty($error_info)): ?>
<div class="alert alert--warning nn-x1"><?php echo htmlspecialchars($error_info); ?></div>
<?php endif; ?>
<?php if (empty($novedades_js)): ?>
<div class="card">
  <div class="alert alert--warning">No hay tipos de novedad disponibles para tu actor (<?php echo htmlspecialchars($mi_actor); ?>). Contacta al administrador.</div>
</div>
<?php else: ?>



<div class="nn-wrap">
<form id="form-novedad" method="POST" action="nueva_novedad.php" enctype="multipart/form-data">

  <!-- META ROW: actor + fecha (flotante, sin cajas) -->
  <div class="nn-meta-row">
    <div class="nn-meta-actor">
      <span class="nn-x2">Actor</span>
      <strong><?php echo htmlspecialchars($_SESSION['tipo_actor']); ?></strong>
      <span><?php echo htmlspecialchars($_SESSION['organizacion']); ?></span>
    </div>
    <span class="nn-meta-fecha" id="nn-fecha-live"></span>
    <script>
    (function(){
      var el=document.getElementById('nn-fecha-live');
      if(!el)return;
      function tick(){var n=new Date(),p=function(x){return String(x).padStart(2,'0')};el.textContent=p(n.getDate())+'/'+p(n.getMonth()+1)+'/'+n.getFullYear()+'  '+p(n.getHours())+':'+p(n.getMinutes());}
      tick(); setInterval(tick,30000);
    })();
    </script>
  </div>

  <!-- HEADER BAR: título + EXP ID + Despacho -->
  <div class="nn-header-bar">
    <h2>Registrar Novedad o Evento de Riesgo</h2>
    <div class="hb-sep"></div>
    <div class="nn-header-field">
      <label for="exp_id">EXP ID</label>
      <input class="nn-x3" type="text" name="exp_id" id="exp_id" placeholder="EXP-00000">
    </div>
    <div class="hb-sep"></div>
    <div class="nn-header-field">
      <label>Despacho</label>
      <input class="nn-x4" type="date" name="fecha_despacho" id="fecha_despacho">
      <input class="nn-x5" type="time" name="hora_despacho"  id="hora_despacho">
    </div>
  </div>

  <!-- CLASIFICACION -->
  <div class="nn-card nn-clasificacion">
    <div class="nn-card-head"><span class="nn-card-head-title">Clasificación</span></div>
    <div class="nn-card-body">
      <div class="cascade-grid">
        <div class="form-group">
          <label class="form-label">Tipo de Operación <span class="req">*</span></label>
          <div class="seg-ctrl" id="op-grid">
            <?php foreach ($tipos_op_activos as $_i => $_top): ?>
            <label id="op-<?php echo htmlspecialchars($_top['clave']); ?>" title="<?php echo htmlspecialchars($_top['descripcion'] ?: $_top['nombre']); ?>"><input type="radio" name="tipo_operacion" value="<?php echo htmlspecialchars($_top['clave']); ?>" data-nombre="<?php echo htmlspecialchars($_top['nombre']); ?>"<?php echo $_i === 0 ? ' required' : ''; ?>> <?php echo htmlspecialchars($_top['nombre']); ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group" id="grupo-tipologia">
          <label class="form-label">Tipología <span class="req">*</span>
            <span id="hint-tipologia" class="hint nn-x6">— Selecciona operación primero</span>
          </label>
          <select id="tipologia" name="tipologia" class="form-control" required>
            <option value="">Seleccione...</option>
            <?php foreach (tipologias_activas() as $_tip): ?>
            <option value="<?php echo htmlspecialchars($_tip['codigo']); ?>"><?php echo htmlspecialchars($_tip['codigo'] . ' — ' . $_tip['nombre']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" id="grupo-tipo-novedad">
          <label class="form-label">Tipo de Novedad <span class="req">*</span>
            <span id="hint-tipo-novedad" class="hint nn-x6">— Selecciona tipología primero</span>
          </label>
          <select id="tipo_novedad" name="tipo_novedad" class="form-control" required>
            <option value="">Seleccione...</option>
          </select>
        </div>
      </div>
      <!-- Ayuda: descripcion de la novedad -->
      <div id="novedad-descripcion-box" style="display:none;margin-top:.55rem">
        <div class="ayuda-lbl">Cuando aplica esta novedad</div>
        <div id="novedad-descripcion-text"></div>
      </div>
    </div>
  </div>

  <!-- FILA: Dirigido a — ancho completo -->
  <div id="respondentes-container" class="hidden">
    <div class="nn-card nn-x1">
      <div class="nn-card-head">
        <span class="nn-card-head-title">Dirigido a</span>
        <span class="nn-x19">Actores para <strong id="resp-op-label"></strong></span>
      </div>
      <div class="nn-card-body">
        <div class="nn-x20">
          <div>
            <span class="dest-label">Actor Responsable <span class="req">*</span></span>
            <select id="sel-actor-resp" class="dest-sel" onchange="cargarUsuariosRespondentes()">
              <option value="">Seleccione un actor...</option>
            </select>
          </div>
          <div>
            <span class="dest-label">Usuario Principal <span class="req">*</span></span>
            <select id="sel-usuario-resp" name="destinatario_principal" class="dest-sel" disabled onchange="actualizarDestinatarios()">
              <option value="">Seleccione primero un actor...</option>
            </select>
          </div>
          <button type="button" class="dest-add-btn" onclick="toggleAgregarMas()" id="btn-agregar-mas" style="height:34px" hidden>
            + Etiquetar otro actor
          </button>
        </div>
        <div id="destinatarios-seleccionados" style="display:none;margin-top:.5rem">
          <span class="dest-label">Notificar a:</span>
          <div id="destinatarios-lista" class="dest-tags"></div>
        </div>
        <div id="panel-agregar-mas" class="dest-add-panel">
          <div class="nn-x21">
            <div>
              <span class="dest-label">Actor extra</span>
              <select id="sel-actor-extra" class="dest-sel" onchange="cargarUsuariosExtra()">
                <option value="">Actor...</option>
              </select>
            </div>
            <div>
              <span class="dest-label">Usuario</span>
              <select id="sel-usuario-extra" class="dest-sel" disabled>
                <option value="">Usuario...</option>
              </select>
            </div>
            <div>
              <span class="dest-label">Correo externo</span>
              <input type="email" id="input-correo-externo" class="dest-sel" placeholder="correo@...">
            </div>
            <div class="nn-x22">
              <button type="button" class="btn-guardar nn-x23" onclick="agregarDestinatarioExtra()">+ Usuario</button>
              <button type="button" class="btn-cancelar nn-x24" onclick="agregarExterno()">+ Correo</button>
            </div>
          </div>
        </div>
        <input type="hidden" name="destinatarios_json" id="destinatarios-json" value="[]">
      </div>
    </div>
  </div>

  <!-- FILA: Descripcion (65%) | Info Adicional (35%) -->
  <div class="nn-x7">

    <!-- COL IZQUIERDA: Descripcion + Causal -->
    <div class="nn-card nn-x8">
      <div class="nn-card-head"><span class="nn-card-head-title">Descripción del Evento</span></div>
      <div class="nn-card-body nn-x9">
        <textarea id="descripcion" name="descripcion" class="form-control nn-x10" required
                  placeholder="Describa detalladamente el evento..."
                  onfocus="this.style.borderColor='#3a8c3f'"
                  onblur="this.style.borderColor='#dde3d9'"></textarea>
        <div id="select-causal-container" class="hidden">
          <label class="nn-x11">Causal</label>
          <select class="nn-x12" id="causal" name="causal">
            <option value="">Seleccione una causa...</option>
          </select>
        </div>
      </div>
    </div>

    <!-- COL DERECHA: Informacion Adicional vertical -->
    <div class="nn-card nn-x8">
      <div class="nn-card-head"><span class="nn-card-head-title">Información Adicional</span></div>
      <div class="nn-card-body nn-x13">
        <p class="nn-x14"><strong>Información adicional</strong> · se activa según el tipo de novedad y a quién va dirigida</p>
        <p class="nn-nota" id="nota-terrestre" hidden></p>
        <button type="button" id="btn-terrestre" class="info-pill nn-x15" disabled onclick="abrirModalGrupo('terrestre')">
          <span class="nn-x16">&#128666;</span>
          <span class="nn-x17">Terrestre</span>
          <span class="pill-check" id="badge-terrestre">&#10003;</span>
        </button>
        <button type="button" id="btn-aereo" class="info-pill nn-x15" disabled onclick="abrirModalGrupo('aereo')">
          <span class="nn-x16">&#9992;</span>
          <span class="nn-x17">Aereo</span>
          <span class="pill-check" id="badge-aereo">&#10003;</span>
        </button>
        <button type="button" id="btn-tiempos" class="info-pill nn-x15" disabled onclick="abrirModalGrupo('tiempos')">
          <span class="nn-x16">&#9200;</span>
          <span class="nn-x17">Tiempos</span>
          <span class="pill-check" id="badge-tiempos">&#10003;</span>
        </button>
        <div id="info-extra-box" class="hidden nn-info-extra">
          <span class="nn-sub">Información necesaria</span>
          <div class="nn-x18" id="info-extra-body"></div>
        </div>
      </div>
    </div>

  </div>

  <!-- Campos ocultos grupos -->
  <input type="hidden" name="datos_terrestre" id="datos-terrestre-json" value="">
  <input type="hidden" name="datos_aereo"     id="datos-aereo-json"     value="">
  <input type="hidden" name="datos_tiempos"   id="datos-tiempos-json"   value="">

  <!-- OPCIONES + ACCIONES -->
  <div class="nn-card">
    <div class="nn-card-body">
      <div class="nn-x25">
        <div class="toggle-inline-row">
          <label class="toggle-inline"><input type="checkbox" id="toggle-adjunto"><span class="ti-sw"></span>Adjuntar evidencia</label>
          <label class="toggle-inline"><input type="checkbox" id="toggle-correo"><span class="ti-sw"></span>Enviar copia por correo</label>
        </div>
        <div class="nn-actions nn-x26">
          <a href="javascript:history.back()" class="btn-cancelar">Cancelar</a>
          <button type="submit" class="btn-guardar">Guardar Novedad</button>
        </div>
      </div>
      <div id="adjunto-container" class="hidden nn-x27">
        <input type="file" name="adjunto" class="form-control nn-x28" accept="image/*,.pdf,.xlsx,.docx">
      </div>
      <div id="correo-container" class="hidden nn-x27">
        <input type="text" name="correo_copia" class="form-control nn-x29" placeholder="email@ejemplo.com; otro@ejemplo.com">
      </div>
    </div>
  </div>

</form>

</div>

<script>
const CATALOGO             = <?php echo json_encode($novedades_js, JSON_UNESCAPED_UNICODE); ?>;
const TIPO_ACTOR_USUARIO   = <?php echo json_encode($mi_actor); ?>;
const ACTORES_TERRESTRE    = ['OTT','OTT_TERCEROS'];
const USUARIOS_ACTIVOS     = <?php
    $_ua = [];
    foreach (usuarios_todos() as $_un => $_uu) {
        if (!empty($_uu['activo'])) $_ua[] = ['usuario'=>$_un, 'nombre'=>$_uu['nombre'], 'tipo_actor'=>$_uu['tipo_actor'], 'organizacion'=>$_uu['organizacion']];
    }
    echo json_encode($_ua, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?>;   // todos los usuarios activos (sin datos sensibles), para "Etiquetar otro actor"
const ACTORES_POR_OPERACION= <?php echo json_encode($actores_por_operacion, JSON_UNESCAPED_UNICODE); ?>;

function habilitarSelect(id,hintId,msg){var e=document.getElementById(id),h=document.getElementById(hintId);if(!e)return;e.disabled=false;e.style.opacity='1';e.style.cursor='pointer';if(h)h.textContent=msg||'';}
function bloquearSelect(id,hintId,msg){var e=document.getElementById(id),h=document.getElementById(hintId);if(!e)return;e.disabled=true;e.style.opacity='.45';e.style.cursor='not-allowed';if(h)h.textContent=msg||'';}
function activarPill(nombre,activar){
    var btn=document.getElementById('btn-'+nombre);
    if(!btn)return;
    btn.disabled=!activar;
    btn.classList.toggle('activo',activar);
    if(!activar){
        btn.classList.remove('guardado');
        var badge=document.getElementById('badge-'+nombre);
        if(badge)badge.style.display='none';
        var j=document.getElementById('datos-'+nombre+'-json');
        if(j)j.value='';
    }
}
// Muestra solo los campos parametrizados por grupo (sin configuracion = todos)
function aplicarCamposGrupo(cfg){
    window._cfgTerrestre=cfg.terrestre||null;
    ['terrestre','aereo','tiempos'].forEach(function(g){
        var modal=document.getElementById('modal-'+g);if(!modal)return;
        var sel=cfg[g];
        modal.querySelectorAll('input[name]').forEach(function(inp){
            var wrap=inp.closest('.campo-inline');if(!wrap)return;
            var visible=!sel||sel.indexOf(inp.name.replace('[]',''))!==-1;
            wrap.style.display=visible?'':'none';
            inp.disabled=!visible;
        });
    });
}
// Informacion necesaria: nombre del chip -> {grupo, campo} en los modales; EXP ID usa el campo del encabezado
var INFO_MAP={'OTT':['terrestre','ott'],'Conductor':['terrestre','conductor'],'Auxiliar':['terrestre','auxiliar'],'Cedula':['terrestre','cedula'],
  'Placa':['terrestre','placa'],'EAI':['terrestre','eai'],'UC':['terrestre','uc'],'OTA':['terrestre','ota'],
  'MAWB':['aereo','mawb'],'HAWB':['aereo','hawb'],'Vuelo':['aereo','num_vuelo'],
  'Piezas Reservadas':['aereo','piezas_reservadas'],'Cajas Reservadas':['aereo','cajas_reservadas']};
var _infoGrupo=[];   // [{nombre,grupo,campo}] a validar contra los modales
function prepararInfoNecesaria(nov){
    var body=document.getElementById('info-extra-body'),box=document.getElementById('info-extra-box');
    body.innerHTML='';_infoGrupo=[];
    (nov.info_necesaria||[]).forEach(function(c){
        var nombre=c.nombre,m=INFO_MAP[nombre];
        if(nombre==='EXP ID'){var e=document.getElementById('exp_id');if(e)e.required=true;return;}
        if(m){
            var sel=(nov.campos_grupo||{})[m[0]];
            if(!sel||sel.indexOf(m[1])!==-1)_infoGrupo.push({nombre:nombre,grupo:m[0],campo:m[1]});
            return;
        }
        var wrap=document.createElement('div');wrap.className='campo-inline';
        var lb=document.createElement('label');lb.textContent=nombre+' *';
        var inp=document.createElement(c.tipo==='textarea'?'textarea':'input');
        if(c.tipo!=='textarea')inp.type=c.tipo||'text';
        inp.name='campo_custom_valor[]';inp.required=true;inp.className='form-control';
        var hid=document.createElement('input');hid.type='hidden';hid.name='campo_custom_nombre[]';hid.value=nombre;
        wrap.appendChild(lb);wrap.appendChild(hid);wrap.appendChild(inp);body.appendChild(wrap);
    });
    box.classList.toggle('hidden',!body.children.length);
}
document.getElementById('form-novedad').addEventListener('submit',function(e){
    var falta=[],primero=null;
    _infoGrupo.forEach(function(r){
        var d={};try{d=JSON.parse(document.getElementById('datos-'+r.grupo+'-json').value||'{}');}catch(x){}
        var v=d[r.campo]||d[r.campo+'[]'];
        if(!v||(Array.isArray(v)&&!v.length)){falta.push(r.nombre);if(!primero)primero=r.grupo;}
    });
    if(falta.length){e.preventDefault();alert('Falta informacion necesaria: '+falta.join(', ')+'.');abrirModalGrupo(primero);return;}
    if(terrestreExigido()){
        var dt={};try{dt=JSON.parse(document.getElementById('datos-terrestre-json').value||'{}');}catch(x){}
        var ft=faltantesTerrestre(dt);
        if(ft.length){e.preventDefault();alert('Faltan datos terrestres obligatorios: '+ft.join(', ')+'.');abrirModalGrupo('terrestre');}
    }
});
function resetGrupos(){
    aplicarCamposGrupo({});
    var ib=document.getElementById('info-extra-body');if(ib){ib.innerHTML='';_infoGrupo=[];document.getElementById('info-extra-box').classList.add('hidden');}
    var ex=document.getElementById('exp_id');if(ex)ex.required=false;
    ['terrestre','aereo','tiempos'].forEach(function(g){activarPill(g,false);});
    var rc=document.getElementById('respondentes-container');if(rc)rc.classList.add('hidden');
    var sc=document.getElementById('select-causal-container');if(sc)sc.classList.add('hidden');
    var nt=document.getElementById('nota-terrestre');if(nt)nt.hidden=true;
    var bt=document.getElementById('btn-terrestre');if(bt)bt.classList.remove('requerido');
}
function cargarCausales(causales){
    var cont=document.getElementById('select-causal-container'),s=document.getElementById('causal');
    if(!cont||!s)return;
    if(causales.length>0){s.innerHTML='<option value="">Seleccione una causa...</option>';causales.forEach(function(c){var o=document.createElement('option');o.value=c;o.textContent=c.replace(/_/g,' ');s.appendChild(o);});cont.classList.remove('hidden');}
    else cont.classList.add('hidden');
}
function cargarRespondentes(actoresResp,usuarios,etiq,usuariosEtiq){
    var respCont=document.getElementById('respondentes-container');
    if(!actoresResp.length){if(respCont)respCont.classList.add('hidden');return;}
    var op=document.querySelector('input[name="tipo_operacion"]:checked');
    var opv=op?op.value:'';
    var opConocida=Object.keys(ACTORES_POR_OPERACION).some(function(k){return ACTORES_POR_OPERACION[k].includes(opv);});
    var filtrados=actoresResp.filter(function(a){if(!opv||!opConocida)return true;return(ACTORES_POR_OPERACION[a]||[]).includes(opv);});
    if(!filtrados.length){if(respCont)respCont.classList.add('hidden');return;}
    // Etiquetar otro actor: si el tipo de novedad define actores "etiquetables" se ofrecen esos;
    // si no, se despliega el listado COMPLETO de actores con usuarios activos (los externos a la novedad).
    var hayEtiq=!!(etiq&&etiq.length);
    var usrEtiq=hayEtiq?(usuariosEtiq||[]):USUARIOS_ACTIVOS;
    var listaEtiq=hayEtiq?etiq:Array.from(new Set(USUARIOS_ACTIVOS.map(function(u){return u.tipo_actor;}))).sort();
    [['sel-actor-resp',filtrados,usuarios],['sel-actor-extra',listaEtiq,usrEtiq]].forEach(function(cfg){
        var sel=document.getElementById(cfg[0]);if(!sel)return;
        sel.innerHTML='<option value="">Seleccione un actor...</option>';
        cfg[1].forEach(function(actor){
            var u2=cfg[2].filter(function(u){return u.tipo_actor===actor;});
            var o=document.createElement('option');o.value=actor;
            o.textContent=actor+(u2.length?' ('+u2.length+')':' - sin usuarios');
            o.dataset.usuarios=JSON.stringify(u2);sel.appendChild(o);
        });
    });
    var bAdd=document.getElementById('btn-agregar-mas');if(bAdd)bAdd.hidden=!listaEtiq.length;
    var su=document.getElementById('sel-usuario-resp');if(su){su.innerHTML='<option value="">Seleccione primero un actor...</option>';su.disabled=true;}
    var se=document.getElementById('sel-usuario-extra');if(se){se.innerHTML='<option value="">Seleccione primero actor...</option>';se.disabled=true;}
    var pm=document.getElementById('panel-agregar-mas');if(pm)pm.style.display='none';
    window._destinatarios=[];actualizarUI_destinatarios();
    if(respCont)respCont.classList.remove('hidden');
}

// Paso 1: Tipo de operacion
document.querySelectorAll('.seg-ctrl input[type="radio"]').forEach(function(radio){
    radio.addEventListener('change',function(){
        var lbl=document.getElementById('resp-op-label');
        if(lbl)lbl.textContent=this.dataset.nombre||(this.value.charAt(0).toUpperCase()+this.value.slice(1));
        habilitarSelect('tipologia','hint-tipologia','');
        document.getElementById('tipologia').value='';
        bloquearSelect('tipo_novedad','hint-tipo-novedad','— Selecciona tipología');
        document.getElementById('tipo_novedad').innerHTML='<option value="">Seleccione...</option>';
        resetGrupos();
    });
});

// Paso 2: Tipologia
document.getElementById('tipologia').addEventListener('change',function(){
    var tip=this.value;
    bloquearSelect('tipo_novedad','hint-tipo-novedad','— Selecciona tipología');
    resetGrupos();
    if(!tip)return;
    var sel=document.getElementById('tipo_novedad');
    sel.innerHTML='<option value="">Seleccione un tipo...</option>';
    var count=0;
    Object.entries(CATALOGO).forEach(function(entry){
        var titulo=entry[0],n=entry[1];
        var ts=n.tipologias||(n.tipologia?String(n.tipologia).split(','):[]);   // un tipo de novedad puede pertenecer a varias tipologías
        if(ts.indexOf(tip)===-1)return;
        var opt=document.createElement('option');
        opt.value=titulo;opt.textContent=titulo.replace(/_/g,' ');
        opt.dataset.terrestre=n.involucra_terrestre?'1':'0';
        opt.dataset.aereo=n.involucra_vuelo?'1':'0';
        opt.dataset.tiempos=n.involucra_tiempos?'1':'0';
        opt.dataset.causales=JSON.stringify(n.causales||[]);
        opt.dataset.actores=JSON.stringify(n.actores_respondentes||[]);
        opt.dataset.usuarios=JSON.stringify(n.usuarios_respondentes||[]);
        opt.dataset.etiq=JSON.stringify(n.actores_etiquetables||[]);
        opt.dataset.usuariosEtiq=JSON.stringify(n.usuarios_etiquetables||[]);
        sel.appendChild(opt);count++;
    });
    habilitarSelect('tipo_novedad','hint-tipo-novedad',count===0?'— Ningún tipo de novedad de esta tipología permite reportar al actor '+TIPO_ACTOR_USUARIO:'');
});

// Helpers para caja de descripcion
function mostrarDescripcionNovedad(desc) {
    var box  = document.getElementById('novedad-descripcion-box');
    var text = document.getElementById('novedad-descripcion-text');
    if (!box || !text) return;
    if (desc && desc.trim()) {
        text.textContent = desc.trim();
        text.title = desc.trim();   // texto completo al pasar el cursor (en pantalla se muestra resumido)
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}

// Paso 3: Tipo de novedad
document.getElementById('tipo_novedad').addEventListener('change',function(){
    var opt=this.options[this.selectedIndex];
    resetGrupos();
    mostrarDescripcionNovedad('');
    if(!opt||!opt.value)return;
    // Mostrar descripcion de ayuda
    var nov=CATALOGO[opt.value]||{};
    mostrarDescripcionNovedad(nov.descripcion||'');
    var esA=opt.dataset.aereo==='1',esP=opt.dataset.tiempos==='1';
    var causales=JSON.parse(opt.dataset.causales||'[]');
    var actores=JSON.parse(opt.dataset.actores||'[]');
    var usuarios=JSON.parse(opt.dataset.usuarios||'[]');
    var etiq=JSON.parse(opt.dataset.etiq||'[]');
    var usuariosEtiq=JSON.parse(opt.dataset.usuariosEtiq||'[]');
    activarPill('aereo',esA);activarPill('tiempos',esP);
    aplicarCamposGrupo(nov.campos_grupo||{});
    evaluarTerrestre();
    prepararInfoNecesaria(nov);
    if(esP){var n=new Date(),pa=function(x){return String(x).padStart(2,'0')};var hr=document.getElementById('g_hora_reporte');if(hr&&!hr.value)hr.value=pa(n.getHours())+':'+pa(n.getMinutes());}
    cargarCausales(causales);cargarRespondentes(actores,usuarios,etiq,usuariosEtiq);
});

// Toggles
document.getElementById('toggle-adjunto').addEventListener('change',function(e){document.getElementById('adjunto-container').classList.toggle('hidden',!e.target.checked);});
document.getElementById('toggle-correo').addEventListener('change',function(e){document.getElementById('correo-container').classList.toggle('hidden',!e.target.checked);});

// Init
bloquearSelect('tipologia','hint-tipologia','— Selecciona operación primero');
bloquearSelect('tipo_novedad','hint-tipo-novedad','— Selecciona tipología primero');
resetGrupos();

// Destinatarios
window._destinatarios=[];
function cargarUsuariosRespondentes(){var selA=document.getElementById('sel-actor-resp'),selU=document.getElementById('sel-usuario-resp'),opt=selA.options[selA.selectedIndex];selU.innerHTML='<option value="">Seleccione un usuario...</option>';if(!opt||!opt.value){selU.disabled=true;return;}var users=JSON.parse(opt.dataset.usuarios||'[]');if(!users.length){selU.innerHTML='<option value="">Sin usuarios para este actor</option>';selU.disabled=true;return;}users.forEach(function(u){var o=document.createElement('option');o.value=u.usuario;o.textContent=u.nombre+' — '+u.organizacion;o.dataset.nombre=u.nombre;o.dataset.actor=u.tipo_actor;o.dataset.org=u.organizacion;selU.appendChild(o);});selU.disabled=false;}
function actualizarDestinatarios(){var selU=document.getElementById('sel-usuario-resp'),selA=document.getElementById('sel-actor-resp'),opt=selU.options[selU.selectedIndex];if(!opt||!opt.value)return;var dest={usuario:opt.value,nombre:opt.dataset.nombre,tipo_actor:selA.value,organizacion:opt.dataset.org,correo:'',principal:true,externo:false};var idx=window._destinatarios.findIndex(function(d){return d.principal;});if(idx>=0)window._destinatarios[idx]=dest;else window._destinatarios.unshift(dest);actualizarUI_destinatarios();var am=document.getElementById('agregar-mas-container');if(am)am.style.display='block';}
function cargarUsuariosExtra(){var selA=document.getElementById('sel-actor-extra'),selU=document.getElementById('sel-usuario-extra'),opt=selA.options[selA.selectedIndex];selU.innerHTML='<option value="">Seleccione un usuario...</option>';if(!opt||!opt.value){selU.disabled=true;return;}var users=JSON.parse(opt.dataset.usuarios||'[]');if(!users.length){selU.disabled=true;return;}users.forEach(function(u){var o=document.createElement('option');o.value=u.usuario;o.textContent=u.nombre+' — '+u.organizacion;o.dataset.nombre=u.nombre;o.dataset.actor=u.tipo_actor;o.dataset.org=u.organizacion;selU.appendChild(o);});selU.disabled=false;}
function agregarDestinatarioExtra(){var selU=document.getElementById('sel-usuario-extra'),selA=document.getElementById('sel-actor-extra'),opt=selU.options[selU.selectedIndex];if(!opt||!opt.value)return;if(window._destinatarios.find(function(d){return d.usuario===opt.value;}))return;window._destinatarios.push({usuario:opt.value,nombre:opt.dataset.nombre,tipo_actor:selA.value,organizacion:opt.dataset.org,correo:'',principal:false,externo:false});actualizarUI_destinatarios();selU.value='';}
function agregarExterno(){var nombre=document.getElementById('input-nombre-externo').value.trim(),correo=document.getElementById('input-correo-externo').value.trim();if(!correo)return;if(window._destinatarios.find(function(d){return d.correo===correo;}))return;window._destinatarios.push({usuario:'ext_'+Date.now(),nombre:nombre||correo,tipo_actor:'Externo',organizacion:'',correo:correo,principal:false,externo:true});document.getElementById('input-nombre-externo').value='';document.getElementById('input-correo-externo').value='';actualizarUI_destinatarios();}
function quitarDestinatario(usuario){window._destinatarios=window._destinatarios.filter(function(d){return d.usuario!==usuario;});actualizarUI_destinatarios();}
// Grupo Terrestre: se desbloquea y es obligatorio solo si se cumplen las DOS condiciones: (1) el tipo de novedad lo
// tiene habilitado en Parametrización y (2) el usuario de "Dirigido a" (principal) es OTT. Ni quien reporta ni las copias influyen.
function terrestreMotivos(){
    var sel=document.getElementById('tipo_novedad'),opt=sel&&sel.options[sel.selectedIndex];
    var permitido=!!(opt&&opt.value&&opt.dataset.terrestre==='1');
    var ott=(window._destinatarios||[]).filter(function(d){return d.principal&&!d.externo&&ACTORES_TERRESTRE.includes(d.tipo_actor);});
    return {permitido:permitido,destinatariosOTT:ott};
}
function terrestreExigido(){
    var m=terrestreMotivos();
    return m.permitido&&m.destinatariosOTT.length>0;
}
function evaluarTerrestre(){
    var m=terrestreMotivos(),activo=terrestreExigido();
    activarPill('terrestre',activo);
    var btn=document.getElementById('btn-terrestre');
    if(btn)btn.classList.toggle('requerido',activo);
    var nota=document.getElementById('nota-terrestre');
    if(nota){
        nota.hidden=!activo;
        if(activo){
            nota.textContent='Datos terrestres obligatorios: "Dirigido a" es '+m.destinatariosOTT.map(function(d){return d.nombre;}).join(', ')+' (OTT).';
        }
    }
}
// Campos terrestres que deben venir completos (los habilitados en la parametrización del tipo de novedad)
function camposTerrestreRequeridos(){
    var out=[];
    document.querySelectorAll('#modal-terrestre .campo-inline').forEach(function(w){
        if(w.style.display==='none')return;
        var inp=w.querySelector('input[name]');
        if(inp&&!inp.disabled)out.push({name:inp.name,label:(w.querySelector('label')||{}).textContent.trim()});
    });
    return out;
}
function faltantesTerrestre(datos){
    return camposTerrestreRequeridos().filter(function(c){
        var k=c.name.replace('[]',''),v=datos[k]||datos[c.name];
        return !v||(Array.isArray(v)&&!v.length);
    }).map(function(c){return c.label;});
}
function actualizarUI_destinatarios(){evaluarTerrestre();var lista=document.getElementById('destinatarios-lista'),cont=document.getElementById('destinatarios-seleccionados'),json=document.getElementById('destinatarios-json');if(!window._destinatarios.length){cont.style.display='none';json.value='[]';return;}cont.style.display='block';lista.innerHTML=window._destinatarios.map(function(d){return'<div class="dest-tag'+(d.principal?' principal':'')+'">'+( d.externo?'&#9993; ':'' )+d.nombre+' <span class="nn-x30">'+d.tipo_actor+'</span>'+(d.principal?'':' <button type="button" onclick="quitarDestinatario(\''+d.usuario+'\')">&times;</button>')+'</div>';}).join('');json.value=JSON.stringify(window._destinatarios);}
function toggleAgregarMas(){var p=document.getElementById('panel-agregar-mas');p.style.display=(getComputedStyle(p).display==='none')?'block':'none';}

// EAI
function agregarEAI(){var d=document.createElement('div');d.style.cssText='display:flex;gap:.4rem;margin-bottom:.3rem';d.innerHTML='<input type="text" name="eai[]" class="form-control nn-x31" placeholder="Nombre o código EAI">';document.getElementById('eai-list').appendChild(d);}
</script>

<!-- ══ MODAL TERRESTRE ══════════════════════════ -->
<div class="modal-overlay" id="modal-terrestre">
<div class="modal-box nn-x32">
  <div class="modal-header"><h3>&#128666; Datos Terrestres</h3><button class="modal-close" onclick="cerrarModalGrupo('terrestre')">×</button></div>
  <div class="modal-body">
    <div class="nn-x33">
      <div class="campo-inline"><label>OTT / Transportador</label><input type="text" id="g_ott" name="ott" class="form-control" placeholder="Empresa"></div>
      <div class="campo-inline"><label>Placa</label><input type="text" id="g_placa" name="placa" class="form-control" placeholder="ABC-123"></div>
      <div class="campo-inline"><label>Conductor</label><input type="text" id="g_conductor" name="conductor" class="form-control"></div>
      <div class="campo-inline"><label>Auxiliar</label><input type="text" id="g_auxiliar" name="auxiliar" class="form-control"></div>
      <div class="campo-inline"><label>Cedula</label><input type="text" id="g_cedula" name="cedula" class="form-control"></div>
      <div class="campo-inline"><label>OTA</label><input type="text" id="g_ota" name="ota" class="form-control"></div>
      <div class="campo-inline"><label>UC</label><input type="text" id="g_uc" name="uc" class="form-control"></div>
    </div>
    <div class="campo-inline nn-x34">
      <label>EAI</label>
      <div id="eai-list"><div class="nn-x35"><input type="text" name="eai[]" class="form-control" placeholder="Nombre o código EAI"></div></div>
      <button type="button" class="btn btn--outline btn--sm nn-x36" onclick="agregarEAI()">+ Agregar EAI</button>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn--outline" onclick="cerrarModalGrupo('terrestre')">Cancelar</button>
    <button class="btn btn--primary" onclick="guardarModalGrupo('terrestre')">Guardar</button>
  </div>
</div></div>

<!-- ══ MODAL AEREO ══════════════════════════════ -->
<div class="modal-overlay" id="modal-aereo">
<div class="modal-box nn-x32">
  <div class="modal-header"><h3>&#9992; Datos Aereos</h3><button class="modal-close" onclick="cerrarModalGrupo('aereo')">×</button></div>
  <div class="modal-body">
    <div class="nn-x37">
      <div class="campo-inline"><label>MAWB</label><input type="text" id="g_mawb" name="mawb" class="form-control" placeholder="083-12345678"></div>
      <div class="campo-inline"><label>HAWB</label><input type="text" id="g_hawb" name="hawb" class="form-control"></div>
      <div class="campo-inline"><label>Número de Vuelo</label><input type="text" id="g_num_vuelo" name="num_vuelo" class="form-control" placeholder="QT4208"></div>
      <div class="campo-inline"><label>OTA</label><input type="text" id="g_ota_aereo" name="ota_aereo" class="form-control" placeholder="AV-BOG"></div>
    </div>
    <div class="nn-x38">
      <div class="nn-x39">
        <div class="nn-x40">Reservado</div>
        <div class="nn-x41">
          <div class="campo-inline"><label>Piezas</label><input type="number" id="g_piezas_res" name="piezas_reservadas" class="form-control" min="0"></div>
          <div class="campo-inline"><label>Cajas</label><input type="number" id="g_cajas_res" name="cajas_reservadas" class="form-control" step="0.01" min="0"></div>
        </div>
      </div>
      <div class="nn-x39">
        <div class="nn-x40">Recibido</div>
        <div class="nn-x41">
          <div class="campo-inline"><label>Piezas</label><input type="number" id="g_piezas_rec" name="piezas_recibidas" class="form-control" min="0"></div>
          <div class="campo-inline"><label>Cajas</label><input type="number" id="g_cajas_rec" name="cajas_recibidas" class="form-control" step="0.01" min="0"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn--outline" onclick="cerrarModalGrupo('aereo')">Cancelar</button>
    <button class="btn btn--primary" onclick="guardarModalGrupo('aereo')">Guardar</button>
  </div>
</div></div>

<!-- ══ MODAL TIEMPOS ════════════════════════════ -->
<div class="modal-overlay" id="modal-tiempos">
<div class="modal-box nn-x42">
  <div class="modal-header"><h3>&#9200; Tiempos Logísticos</h3><button class="modal-close" onclick="cerrarModalGrupo('tiempos')">×</button></div>
  <div class="modal-body">
    <div class="nn-x43">
      <div>
        <div class="nn-x44">Tiempos del Proceso</div>
        <div class="nn-x45">
          <div class="campo-inline"><label>Hora Reporte</label><input type="time" id="g_hora_reporte" name="hora_reporte" class="form-control"></div>
          <div class="campo-inline"><label>Hora Est. Arribo</label><input type="time" id="g_hora_est_arribo" name="hora_est_arribo" class="form-control"></div>
          <div class="campo-inline"><label>Arribo</label><input type="time" id="g_arribo" name="arribo" class="form-control"></div>
          <div class="campo-inline"><label>Llegada</label><input type="time" id="g_llegada" name="llegada" class="form-control"></div>
          <div class="campo-inline"><label>Inicio Cargue</label><input type="time" id="g_inicio_cargue" name="inicio_cargue" class="form-control"></div>
          <div class="campo-inline"><label>Fin Cargue</label><input type="time" id="g_fin_cargue" name="fin_cargue" class="form-control"></div>
          <div class="campo-inline"><label>Salida</label><input type="time" id="g_salida" name="salida" class="form-control"></div>
        </div>
      </div>
      <div>
        <div class="nn-x44">Metricas de Cargue</div>
        <div class="nn-x45">
          <div class="campo-inline"><label>Total Cajas</label><input type="number" id="g_total_cajas" name="total_cajas" class="form-control" min="0"></div>
          <div class="campo-inline"><label>Cajas Cargadas</label><input type="number" id="g_cajas_cargadas" name="cajas_cargadas" class="form-control" min="0"></div>
          <div class="campo-inline"><label>Espera Porteria (min)</label><input type="number" id="g_espera_porteria" name="espera_porteria" class="form-control" min="0"></div>
          <div class="campo-inline"><label>Tiempo Espera (min)</label><input type="number" id="g_tiempo_espera" name="tiempo_espera" class="form-control" min="0"></div>
          <div class="campo-inline"><label>% Cargue</label><input type="number" id="g_pct_cargue" name="pct_cargue" class="form-control" min="0" max="100"></div>
          <div class="campo-inline"><label>TPCC (seg/caja)</label><input type="number" id="g_tpcc" name="tpcc" class="form-control" min="0" step="0.01"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn--outline" onclick="cerrarModalGrupo('tiempos')">Cancelar</button>
    <button class="btn btn--primary" onclick="guardarModalGrupo('tiempos')">Guardar</button>
  </div>
</div></div>


<script>
function abrirModalGrupo(nombre){
    var overlay=document.getElementById('modal-'+nombre);if(!overlay)return;
    if(nombre==='tiempos'){var n=new Date(),pa=function(x){return String(x).padStart(2,'0')};var hr=document.getElementById('g_hora_reporte');if(hr&&!hr.value)hr.value=pa(n.getHours())+':'+pa(n.getMinutes());}
    overlay.classList.add('open');
}
function cerrarModalGrupo(nombre){var o=document.getElementById('modal-'+nombre);if(o)o.classList.remove('open');}
function guardarModalGrupo(nombre){
    var modal=document.getElementById('modal-'+nombre);
    var inputs=modal.querySelectorAll('input[name],select[name],textarea[name]');
    var datos={};inputs.forEach(function(inp){
        if(!inp.name||!inp.value)return;
        if(inp.name.slice(-2)==='[]'){var k=inp.name.slice(0,-2);(datos[k]=datos[k]||[]).push(inp.value);}
        else datos[inp.name]=inp.value;
    });
    if(nombre==='terrestre'&&terrestreExigido()){
        var faltan=faltantesTerrestre(datos);
        if(faltan.length){alert('Estos datos terrestres son obligatorios: '+faltan.join(', ')+'.');return;}
    }
    document.getElementById('datos-'+nombre+'-json').value=JSON.stringify(datos);
    var btn=document.getElementById('btn-'+nombre);
    if(btn){btn.classList.add('guardado');var badge=btn.querySelector('.pill-check');if(badge)badge.style.display='flex';}
    cerrarModalGrupo(nombre);
}
['terrestre','aereo','tiempos'].forEach(function(nombre){
    var o=document.getElementById('modal-'+nombre);
    if(o)o.addEventListener('click',function(e){if(e.target===this)cerrarModalGrupo(nombre);});
});
</script>

<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
