<?php
date_default_timezone_set('America/Bogota');
$page_title = 'Parametrización de Novedades';
$css_file   = 'dashboard.css';
$css_extra  = ['admin_novedades.css'];
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/parametrizacion.php';
$puede_editar = verificar_parametrizacion('novedades');
require_once __DIR__ . '/../data/taxonomia.php';
require_once __DIR__ . '/../includes/tipos_operacion.php';
$tipos_op_activos = tipos_operacion_todos();
usort($tipos_op_activos, fn($a, $b) => (int)$b['estado'] <=> (int)$a['estado']); // activos primero; los inactivos se muestran marcados para no perder asignaciones al editar

define('NOVEDADES_CUSTOM_FILE', __DIR__ . '/../storage/novedades_custom.json');

function leer_novedades_custom(): array {
    if (!file_exists(NOVEDADES_CUSTOM_FILE)) return [];
    $d = json_decode(file_get_contents(NOVEDADES_CUSTOM_FILE), true) ?? [];
    foreach ($d as $k => $n) { $d[$k]['tipologias'] = novedad_tipologias($n); }   // formato antiguo (una tipología) -> lista
    return $d;
}
function guardar_novedades_custom(array $data): void {
    file_put_contents(NOVEDADES_CUSTOM_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$ACTORES = ['7K','AdA','AdC','BDS','CECO','CEDI','EAI','EAI_TERCEROS','EI',
            'ENTIDADES_GOBIERNO','EXTERNO','GHT','OCA','OSL','OTA','OTM',
            'OTT','OTT_TERCEROS','SPX','TTE_INBOUND_DESTINO','UC'];

require_once __DIR__ . '/../includes/tipologias.php';
// Activas primero; las inactivas se muestran marcadas para no perder la asignación al editar un tipo existente
$_tip_todas = tipologias_todas();
usort($_tip_todas, fn($a, $b) => (int)$b['estado'] <=> (int)$a['estado']);
$TIPOLOGIAS = [];
foreach ($_tip_todas as $_t) $TIPOLOGIAS[$_t['codigo']] = $_t['codigo'] . ' — ' . $_t['nombre'] . ((int)$_t['estado'] === 1 ? '' : ' (inactiva)');
$TIPOLOGIAS_ACTIVAS = array_column(tipologias_activas(), 'codigo');

$CAMPOS_PRE = [
    'Terrestre'=>['OTT','Conductor','Auxiliar','Cedula','Placa','EAI','UC','OTA'],
    'Aereo'    =>['MAWB','HAWB','Vuelo','Num. Cajas','Piezas Reservadas','Cajas Reservadas'],
    'General'  =>['EXP ID','Numero Guia','Cliente','Producto','Cantidad','Observaciones'],
];

// Campos que ofrece cada grupo de informacion adicional (clave = name del input en nueva_novedad.php)
$CAMPOS_GRUPO = [
    'terrestre' => ['label'=>'Grupo Terrestre','icon'=>'&#128666;','flag'=>'f-terr','campos'=>[
        'ott'=>'OTT / Transportador','placa'=>'Placa','conductor'=>'Conductor','auxiliar'=>'Auxiliar',
        'cedula'=>'Cedula','ota'=>'OTA','uc'=>'UC','eai'=>'EAI']],
    'aereo' => ['label'=>'Grupo Aereo','icon'=>'&#9992;','flag'=>'f-vuelo','campos'=>[
        'mawb'=>'MAWB','hawb'=>'HAWB','num_vuelo'=>'Número de Vuelo','ota_aereo'=>'OTA',
        'piezas_reservadas'=>'Piezas reservadas','cajas_reservadas'=>'Cajas reservadas',
        'piezas_recibidas'=>'Piezas recibidas','cajas_recibidas'=>'Cajas recibidas']],
    'tiempos' => ['label'=>'Tiempos Logísticos','icon'=>'&#9200;','flag'=>'f-tiemp','campos'=>[
        'hora_reporte'=>'Hora Reporte','hora_est_arribo'=>'Hora Est. Arribo','arribo'=>'Arribo','llegada'=>'Llegada',
        'inicio_cargue'=>'Inicio Cargue','fin_cargue'=>'Fin Cargue','salida'=>'Salida','total_cajas'=>'Total Cajas',
        'cajas_cargadas'=>'Cajas Cargadas','espera_porteria'=>'Espera Porteria (min)','tiempo_espera'=>'Tiempo Espera (min)',
        'pct_cargue'=>'% Cargue','tpcc'=>'TPCC (seg/caja)']],
];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if (in_array($accion, ['crear','editar'])) {
        $titulo    = strtoupper(trim(str_replace([' ','-'],'_',$_POST['titulo']??'')));
        // Un tipo de novedad pertenece a una sola tipología
        $tipologias = array_filter([trim($_POST['tipologia'] ?? '')]);
        $previas    = novedad_tipologias(leer_novedades_custom()[$titulo] ?? []);
        $invalidas  = array_filter($tipologias, fn($t) => !isset($TIPOLOGIAS[$t])
                      || (!in_array($t, $TIPOLOGIAS_ACTIVAS, true) && !in_array($t, $previas, true)));
        if (!$titulo || !$tipologias) {
            $msg = 'error:El título y la tipología son obligatorios.';
        } elseif ($invalidas) {
            $msg = 'error:Una tipología elegida no existe o está inactiva: ' . implode(', ', $invalidas) . '.';
        } else {
            $tipologia  = reset($tipologias);
            $tipologias = [$tipologia];
            $campos = [];
            foreach ($_POST['campo_nombre']??[] as $i=>$n) {
                if(trim($n)) $campos[] = ['nombre'=>trim($n),'tipo'=>$_POST['campo_tipo'][$i]??'text','requerido'=>isset($_POST['campo_requerido'][$i])];
            }
            $causales = array_values(array_filter(array_map('trim',explode("\n",$_POST['causales_texto']??''))));
            $novedades = leer_novedades_custom();
            // Info necesaria
            $info = [];
            foreach ($_POST['info_nombre']??[] as $i=>$n2) {
                if(trim($n2)) $info[] = ['nombre'=>trim($n2),'tipo'=>$_POST['info_tipo'][$i]??'text'];
            }
            // Campos habilitados por grupo (solo grupos activos)
            $flags_grupo = ['terrestre'=>'involucra_terrestre','aereo'=>'involucra_vuelo','tiempos'=>'involucra_tiempos'];
            $campos_grupo = [];
            foreach ($flags_grupo as $g=>$flag) {
                if (!isset($_POST[$flag])) continue;
                $validos = array_keys($CAMPOS_GRUPO[$g]['campos']);
                $campos_grupo[$g] = array_values(array_intersect($validos, (array)($_POST['cg'][$g]??[])));
            }
            $novedades[$titulo] = [
                'titulo'               => $titulo,
                'eje'                  => trim($_POST['eje']??''),
                'tipo_cls'             => trim($_POST['tipo_cls']??''),
                'subtipo'              => trim($_POST['subtipo']??''),
                'tipo_operacion'       => array_values($_POST['tipo_operacion']??['exportacion']),
                'tipologia'            => $tipologia,
                'tipologias'           => $tipologias,
                'descripcion'          => trim($_POST['descripcion']??''),
                'requiere_respuesta'   => isset($_POST['requiere_respuesta']),
                'requiere_adjunto'     => isset($_POST['requiere_adjunto']),
                'involucra_terrestre'  => isset($_POST['involucra_terrestre']),
                'involucra_vuelo'      => isset($_POST['involucra_vuelo']),
                'involucra_tiempos'    => isset($_POST['involucra_tiempos']),
                'notificar_admin'      => isset($_POST['notificar_admin']),
                'notificar_torre'      => isset($_POST['notificar_torre']),
                'actores_reportantes'  => array_values($_POST['actores_reportantes']??[]),
                'actores_respondentes' => array_values($_POST['actores_respondentes']??[]),
                'actores_etiquetables' => array_values($_POST['actores_etiquetables']??[]),
                'causales'             => $causales,
                'campos_grupo'         => $campos_grupo,
                'campos'               => $campos,
                'info_necesaria'       => $info,
                'activo'               => isset($_POST['activo']),
                'datos_minimos'        => implode(', ',array_column($campos,'nombre')),
                'creado_por'           => $_SESSION['usuario'],
                'fecha_creacion'       => date('Y-m-d H:i:s'),
            ];
            guardar_novedades_custom($novedades);
            $msg = 'success:Tipo "'.str_replace('_',' ',$titulo).'" '.($accion==='crear'?'creado':'actualizado').'.';
        }
    }
    if ($accion==='toggle') {
        $t=$_POST['titulo_toggle']??''; $n=leer_novedades_custom();
        if(isset($n[$t])){ $n[$t]['activo']=!($n[$t]['activo']??true); guardar_novedades_custom($n); $msg='success:Estado actualizado.';}
    }
    if ($accion==='eliminar') {
        $t=$_POST['titulo_eliminar']??''; $n=leer_novedades_custom(); unset($n[$t]); guardar_novedades_custom($n); $msg='success:Eliminado.';
    }
    header('Location: admin_novedades.php'.($msg?'?msg='.urlencode($msg):'')); exit;
}

if(isset($_GET['msg'])) $msg=urldecode($_GET['msg']);
[$msg_tipo,$msg_texto]=$msg?explode(':',$msg,2):['',''];
$novedades_custom=leer_novedades_custom();
$editar=isset($_GET['editar'])?($novedades_custom[$_GET['editar']]??null):null;
$mostrar_form=isset($_GET['nuevo'])||$editar!==null;

include __DIR__ . '/../includes/header.php';
?>

<?php if($msg_texto): ?>
<div class="alert alert--<?php echo $msg_tipo==='success'?'success':'error';?>">
    <?php echo htmlspecialchars($msg_texto); ?>
</div>
<?php endif;?>

<!-- ══ LISTADO ═══════════════════════════════════════════════════ -->
<div class="card">
    <?php parametrizacion_tabs('novedades'); ?>
    <div class="an-x1">
        <h2 class="an-x2">Tipos de Novedad Personalizados</h2>
        <button class="btn btn--primary btn--sm" onclick="abrirModal()">+ Crear Tipo de Novedad</button>
    </div>

    <?php if(empty($novedades_custom)): ?>
        <div class="no-results">
            <span class="an-x3">+</span>
            Aun no hay tipos personalizados.<br>
            <small>Usa el botón "Crear Tipo de Novedad" para comenzar.</small>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th></th><th>Tipo de Novedad</th><th>Tipología</th><th>Campos</th><th>Reportantes</th><th>Respondentes</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach($novedades_custom as $key=>$n):
                $activo=$n['activo']??true;
                $rowId='row-'.md5($key);
            ?>
            <tr style="<?php echo !$activo?'opacity:.45':'';?>">
                <td class="an-x4">
                    <button type="button" onclick="toggleDetail('<?php echo $rowId;?>')"
                        style="width:22px;height:22px;border:none;background:transparent;cursor:pointer;color:var(--text-muted)">
                        <svg id="chevron-<?php echo $rowId;?>" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="transition:transform .2s"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                </td>
                <td>
                    <div class="an-x5"><?php echo htmlspecialchars(str_replace('_',' ',$n['titulo']));?></div>
                    <div class="an-x6">
                        <?php if($n['involucra_terrestre']??false): ?><span class="an-x7">Terrestre</span><?php endif;?>
                        <?php if($n['involucra_vuelo']??false):     ?><span class="an-x8">Aereo</span><?php endif;?>
                        <?php if($n['involucra_tiempos']??false):   ?><span class="an-x9">Tiempos</span><?php endif;?>
                        <?php if($n['requiere_respuesta']??false):  ?><span class="an-x10">Resp.</span><?php endif;?>
                    </div>
                </td>
                <td><?php foreach (novedad_tipologias($n) as $_tc): ?><span class="badge badge--reporte"><?php echo htmlspecialchars($_tc); ?></span> <?php endforeach; ?></td>
                <td class="an-x11"><?php echo count($n['campos']??[]);?></td>
                <td class="an-x12">
                    <?php $rep=$n['actores_reportantes']??[]; echo implode(', ',array_slice($rep,0,3)).(count($rep)>3?' +'.( count($rep)-3):'');?>
                </td>
                <td class="an-x12">
                    <?php $res=$n['actores_respondentes']??[]; echo implode(', ',array_slice($res,0,3)).(count($res)>3?' +'.( count($res)-3):'');?>
                </td>
                <td><span class="badge <?php echo $activo?'badge--resuelta':'badge--reporte';?>"><?php echo $activo?'Activo':'Inactivo';?></span></td>
                <td>
                    <div class="an-x13">
                        <button class="btn btn--outline btn--sm" onclick="abrirModal('<?php echo htmlspecialchars($key);?>')">Editar</button>
                        <form class="an-x14" method="POST">
                            <input type="hidden" name="accion" value="toggle">
                            <input type="hidden" name="titulo_toggle" value="<?php echo htmlspecialchars($key);?>">
                            <button class="btn btn--outline btn--sm"><?php echo $activo?'Desactivar':'Activar';?></button>
                        </form>
                        <form class="an-x15" method="POST" data-confirm-title="Eliminar tipo de novedad" data-confirm="Se eliminará «<?php echo htmlspecialchars(str_replace('_',' ',$n['titulo']), ENT_QUOTES);?>» del catálogo. Las novedades ya registradas no se borran, pero ya no podrá elegirse al crear nuevas. Esta acción no se puede deshacer." data-confirm-ok="Eliminar">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="titulo_eliminar" value="<?php echo htmlspecialchars($key);?>">
                            <button class="btn btn--danger btn--sm">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <!-- Fila expandible de detalle -->
            <tr class="nov-row-detail" id="<?php echo $rowId;?>">
                <td colspan="8">
                    <div class="detail-grid">
                        <!-- Descripcion (cuando aplica) destacada -->
                        <?php if(!empty($n['descripcion'])): ?>
                        <div class="detail-item an-x16">
                            <span class="an-x17">Cuando aplica esta novedad</span>
                            <span class="an-x18"><?php echo htmlspecialchars($n['descripcion']);?></span>
                        </div>
                        <?php endif;?>
                        <!-- Clasificacion -->
                        <?php if(!empty($n['eje'])): ?>
                        <div class="detail-item">
                            <span>Clasificación</span>
                            <span><?php echo htmlspecialchars(($n['eje']??'').($n['tipo_cls']?' › '.$n['tipo_cls']:'').($n['subtipo']?' › '.$n['subtipo']:''));?></span>
                        </div>
                        <?php endif;?>
                        <!-- Operaciones -->
                        <?php if(!empty($n['tipo_operacion'])): ?>
                        <div class="detail-item">
                            <span>Operaciones</span>
                            <span><?php echo strtoupper(implode(', ', $n['tipo_operacion']));?></span>
                        </div>
                        <?php endif;?>
                        <?php if(!empty($n['campos'])): ?>
                        <div class="detail-item an-x19">
                            <span>Campos configurados</span>
                            <span><?php echo implode(', ', array_map(fn($c)=>$c['nombre'].($c['requerido']?'*':''), $n['campos']));?></span>
                        </div>
                        <?php endif;?>
                        <?php if(!empty($n['info_necesaria'])): ?>
                        <div class="detail-item an-x19">
                            <span class="an-x20">Información Necesaria (obligatoria)</span>
                            <span><?php echo implode(', ', array_column($n['info_necesaria'],'nombre'));?></span>
                        </div>
                        <?php endif;?>
                        <?php if(!empty($n['causales'])): ?>
                        <div class="detail-item an-x19">
                            <span>Causales</span>
                            <span><?php echo implode(' · ', $n['causales']);?></span>
                        </div>
                        <?php endif;?>
                        <div class="detail-item">
                            <span>Creado por</span>
                            <span><?php echo htmlspecialchars($n['creado_por']??'-');?></span>
                        </div>
                        <div class="detail-item">
                            <span>Fecha creacion</span>
                            <span><?php echo substr($n['fecha_creacion']??'-',0,16);?></span>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    </div>
    <?php endif;?>
</div>

<!-- ══ MODAL ══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-overlay">
<div class="modal-box an-x21">
    <div class="modal-header">
        <div class="an-x22">
            <h3 class="an-x23" id="modal-title">Crear Tipo de Novedad</h3>
            <!-- Pasos del asistente -->
            <ol class="an-stepper" id="an-stepper" aria-label="Pasos para crear un tipo de novedad">
                <li><button type="button" class="an-step is-active" id="step-ind-1" onclick="irPaso(1)"><span class="an-step__n">1</span>Clasificación</button></li>
                <li><button type="button" class="an-step" id="step-ind-2" onclick="irPaso(2)"><span class="an-step__n">2</span>Permisos</button></li>
                <li><button type="button" class="an-step" id="step-ind-3" onclick="irPaso(3)"><span class="an-step__n">3</span>Características</button></li>
                <li><button type="button" class="an-step" id="step-ind-4" onclick="irPaso(4)"><span class="an-step__n">4</span>Campos</button></li>
            </ol>
        </div>
        <button class="modal-close" onclick="cerrarModal()">×</button>
    </div>

    <div class="modal-body">
    <form method="POST" action="admin_novedades.php" id="form-param">
        <input type="hidden" name="accion"    id="form-accion"   value="crear">
        <input type="hidden" name="titulo"    id="f-titulo-hidden">
        <input type="hidden" name="eje"       id="f-eje-hidden">
        <input type="hidden" name="tipo_cls"  id="f-tipo-hidden">
        <input type="hidden" name="subtipo"   id="f-subtipo-hidden">

        <!-- ════ PASO 1: CLASIFICACION ════ -->
        <div id="modal-step-1">
          <div class="cls-grid">

            <!-- Tipo de Operación -->
            <div class="field-group">
                <label class="field-label">Tipo de Operación <span class="an-x20">*</span>
                    <span class="an-x28">— Selección múltiple</span>
                </label>
                <div class="an-x29">
                    <?php foreach ($tipos_op_activos as $_top): ?>
                    <label class="m-op-btn" id="mop-<?php echo htmlspecialchars($_top['clave']); ?>">
                        <input type="checkbox" name="tipo_operacion[]" value="<?php echo htmlspecialchars($_top['clave']); ?>"> <?php echo htmlspecialchars($_top['nombre']); ?><?php echo (int)$_top['estado'] === 1 ? '' : ' (inactivo)'; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Eje -->
            <div class="field-group">
                <label class="field-label">Eje <span class="an-x20">*</span></label>
                <div class="an-x29">
                    <?php foreach(array_keys($taxonomia) as $eje): ?>
                    <label class="m-op-btn" onclick="selEje(this,'<?php echo $eje;?>')">
                        <input type="radio" name="m_eje" value="<?php echo $eje;?>"> <?php echo $eje;?>
                    </label>
                    <?php endforeach;?>
                </div>
            </div>

            <!-- Tipo -->
            <div class="field-group an-x30" id="m-grp-tipo">
                <label class="field-label" for="m-sel-tipo">Tipo <span class="an-x20">*</span></label>
                <select id="m-sel-tipo" class="form-control" disabled onchange="selTipo(this.value)">
                    <option value="">Selecciona primero el eje...</option>
                </select>
            </div>

            <!-- Subtipo -->
            <div class="field-group an-x30" id="m-grp-subtipo">
                <label class="field-label" for="m-sel-subtipo">Subtipo <span class="an-x20">*</span></label>
                <select id="m-sel-subtipo" class="form-control" disabled onchange="selSubtipo(this.value)">
                    <option value="">Selecciona primero el tipo...</option>
                </select>
            </div>

            <!-- Nombre de la novedad -->
            <div class="field-group an-x30 cls-full" id="m-grp-nombre">
                <label class="field-label" for="m-nombre">Nombre de la Novedad <span class="an-x20">*</span>
                    <span class="an-x28">— Acción + Complemento</span>
                </label>
                <input type="text" id="m-nombre" class="form-control" disabled
                       placeholder="Ej: Accidente de Trabajo"
                       oninput="validarNombreModal(this)">
                <span class="an-x31" id="m-nombre-hint"></span>
            </div>

            <!-- Tipologia -->
            <div class="field-group cls-full" id="m-grp-tipologia" style="display:none">
                <label class="field-label">Tipología <span class="an-x20">*</span>
                </label>
                <div class="an-x32">
                    <?php foreach($TIPOLOGIAS as $v=>$l): ?>
                    <label class="m-op-btn">
                        <input type="radio" name="tipologia" value="<?php echo $v;?>" onchange="selTipologia()"> <?php echo $l;?>
                    </label>
                    <?php endforeach;?>
                </div>
            </div>

            <!-- Descripción -->
            <div class="field-group">
                <label class="field-label" for="f-desc">Descripción
                    <span class="an-x28">— Cuándo aplica esta novedad</span>
                </label>
                <textarea name="descripcion" id="f-desc" class="form-control" rows="4"
                          placeholder="Describe cuándo aplica esta novedad..."></textarea>
            </div>

            <!-- Causales -->
            <div class="field-group">
                <label class="field-label" for="f-causales">Causales
                    <span class="an-x28">— Una por línea</span>
                </label>
                <div class="an-x51">
                    <textarea name="causales_texto" id="f-causales" class="form-control an-x52" rows="4"
                              placeholder="FALLA_TECNICA&#10;ERROR_OPERARIO&#10;CONDICION_CLIMATICA"
                              oninput="contarLineas(this,'badge-caus')"></textarea>
                    <span class="an-x53" id="badge-caus">0</span>
                </div>
            </div>
          </div>

            <!-- Resumen -->
            <div id="m-resumen" style="display:none;margin-top:.75rem;padding:.5rem .85rem;background:#f2f5f1;border-radius:6px;border:1px solid var(--border-color);font-size:.78rem;display:none">
                <span class="an-x33">Clasificación: </span>
                <span class="an-x34" id="m-res-text"></span>
            </div>
        </div>

        <!-- ════ PASO 2: CONFIGURACION (4 sub-pasos) ════ -->
        <div id="modal-step-2" style="display:none">

            <!-- Resumen paso 1 -->
            <div class="an-x35">
                <div class="an-x36">
                    <span class="an-x37">Clasificación: </span>
                    <span class="an-x38" id="s2-resumen"></span>
                </div>
                <button class="an-x39" type="button" onclick="irPaso(1)">Editar</button>
            </div>

            <!-- ════ PASO 2: PERMISOS ════ -->
            <div id="cfg-panel-2" class="cfg-panel" style="display:none">
              <div class="perm-list">

                <?php
                $_perm_actores = [
                    ['rep',  'actores_reportantes[]',  'Puede reportar',  'Qué actores pueden registrar esta novedad'],
                    ['res',  'actores_respondentes[]','Puede responder', 'Qué actores gestionan y responden esta novedad'],
                    ['etiq', 'actores_etiquetables[]','Etiquetables',    'Qué actores se pueden etiquetar en la conversación'],
                ];
                foreach ($_perm_actores as [$_k, $_name, $_titulo, $_ayuda]): ?>
                <details class="perm-dd">
                    <summary class="perm-dd__sum">
                        <span class="perm-dd__title"><?php echo $_titulo; ?></span>
                        <span class="cfg-badge" id="badge-<?php echo $_k; ?>">0</span>
                        <span class="perm-dd__value" id="sum-<?php echo $_k; ?>">Ninguno</span>
                        <svg class="perm-dd__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="perm-dd__body">
                        <p class="perm-dd__hint"><?php echo $_ayuda; ?></p>
                        <div class="perm-dd__tools">
                            <input type="search" placeholder="Buscar actor..." class="actor-search" aria-label="Buscar actor en <?php echo $_titulo; ?>" oninput="filtrarActores(this,'<?php echo $_k; ?>')">
                            <button type="button" class="cfg-mini-btn" onclick="selAll('<?php echo $_name; ?>',true,'badge-<?php echo $_k; ?>')">Todos</button>
                            <button type="button" class="cfg-mini-btn" onclick="selAll('<?php echo $_name; ?>',false,'badge-<?php echo $_k; ?>')">Ninguno</button>
                        </div>
                        <div class="perm-dd__list" id="actors-<?php echo $_k; ?>">
                            <?php foreach($ACTORES as $a): ?>
                            <label class="perm-opt">
                                <input type="checkbox" name="<?php echo $_name; ?>" value="<?php echo $a; ?>"
                                       onchange="cntBadge('<?php echo $_name; ?>','badge-<?php echo $_k; ?>')">
                                <?php echo $a; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>
                <?php endforeach; ?>

                <!-- Notificaciones automáticas -->
                <details class="perm-dd">
                    <summary class="perm-dd__sum">
                        <span class="perm-dd__title">Notificaciones automáticas</span>
                        <span class="perm-dd__value" id="sum-notif">Ninguna</span>
                        <svg class="perm-dd__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="perm-dd__body">
                        <p class="perm-dd__hint">A quién se avisa cada vez que se registra esta novedad.</p>
                        <div class="perm-dd__list perm-dd__list--1">
                            <label class="perm-opt"><input type="checkbox" name="notificar_admin" id="f-notif-admin" checked> Notificar Administrador <span class="an-x45">(recomendado)</span></label>
                            <label class="perm-opt"><input type="checkbox" name="notificar_torre" id="f-notif-torre" checked> Notificar Torre de Control</label>
                        </div>
                    </div>
                </details>

                <!-- Estado en el sistema -->
                <details class="perm-dd">
                    <summary class="perm-dd__sum">
                        <span class="perm-dd__title">Estado en el sistema</span>
                        <span class="perm-dd__value" id="sum-activo">Activo</span>
                        <svg class="perm-dd__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="perm-dd__body">
                        <div class="perm-dd__list perm-dd__list--1">
                            <label class="perm-opt"><input type="checkbox" name="activo" id="f-activo" checked> Activo y visible en el sistema</label>
                        </div>
                    </div>
                </details>

              </div>
            </div>

            <!-- SUB-PASO 3: Caracteristicas -->
            <div id="cfg-panel-3" class="cfg-panel" style="display:none">
                <div class="an-x41">
                    <div>
                        <label class="field-label an-x56">Comportamiento de la novedad</label>
                        <div class="an-x57">
                            <label class="cfg-flag-card">
                                <input type="checkbox" name="requiere_respuesta" id="f-req-resp">
                                <div class="cfg-flag-icon">&#128204;</div>
                                <div>
                                    <div class="an-x58">Requiere respuesta</div>
                                    <div class="an-x59">El gestor debe responder esta novedad</div>
                                </div>
                            </label>
                            <label class="cfg-flag-card">
                                <input type="checkbox" name="requiere_adjunto" id="f-req-adj">
                                <div class="cfg-flag-icon">&#128206;</div>
                                <div>
                                    <div class="an-x58">Requiere adjunto</div>
                                    <div class="an-x59">El usuario debe adjuntar evidencia</div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="field-label an-x56">Grupos de informacion adicional</label>
                        <div class="an-x57">
                            <label class="cfg-flag-card">
                                <input type="checkbox" name="involucra_terrestre" id="f-terr">
                                <div class="cfg-flag-icon">&#128666;</div>
                                <div>
                                    <div class="an-x58">Grupo Terrestre</div>
                                    <div class="an-x59">OTT, conductor, placa, EAI, UC. Se pide solo si el usuario de «Dirigido a» es OTT (una copia no lo exige)</div>
                                </div>
                            </label>
                            <label class="cfg-flag-card">
                                <input type="checkbox" name="involucra_vuelo" id="f-vuelo">
                                <div class="cfg-flag-icon">&#9992;</div>
                                <div>
                                    <div class="an-x58">Grupo Aereo</div>
                                    <div class="an-x59">MAWB, HAWB, vuelo, cajas</div>
                                </div>
                            </label>
                            <label class="cfg-flag-card">
                                <input type="checkbox" name="involucra_tiempos" id="f-tiemp">
                                <div class="cfg-flag-icon">&#9200;</div>
                                <div>
                                    <div class="an-x58">Tiempos Logísticos</div>
                                    <div class="an-x59">Arribo, cargue, TPCC, metricas</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SUB-PASO 4: Campos Requeridos + Info Necesaria -->
            <div id="cfg-panel-4" class="cfg-panel" style="display:none">

                <!-- Campos de los grupos habilitados en Caracteristicas -->
                <div class="an-x60">
                    <label class="field-label an-x61">Campos de los grupos de informacion adicional</label>
                    <p class="an-x62">Elige que campos se piden en cada grupo habilitado en Caracteristicas.</p>
                    <div class="an-x63" id="cg-vacio">
                        No hay grupos habilitados. Activa Terrestre, Aereo o Tiempos en el paso 3 para configurar sus campos.
                    </div>
                    <?php foreach($CAMPOS_GRUPO as $g=>$def): ?>
                    <div class="cg-bloque" data-grupo="<?php echo $g;?>" data-flag="<?php echo $def['flag'];?>" style="display:none;margin-bottom:.85rem">
                        <div class="an-x64">
                            <span class="an-x65"><?php echo $def['icon'].' '.$def['label'];?></span>
                            <button type="button" class="info-chip-btn" onclick="cgTodos('<?php echo $g;?>',true)">Todos</button>
                            <button type="button" class="info-chip-btn" onclick="cgTodos('<?php echo $g;?>',false)">Ninguno</button>
                        </div>
                        <div class="an-x66">
                            <?php foreach($def['campos'] as $k=>$lbl): ?>
                            <label class="an-x67">
                                <input type="checkbox" name="cg[<?php echo $g;?>][]" value="<?php echo $k;?>" checked> <?php echo $lbl;?>
                            </label>
                            <?php endforeach;?>
                        </div>
                    </div>
                    <?php endforeach;?>
                </div>

                <div class="an-x41">

                    <!-- Informacion necesaria -->
                    <div>
                        <label class="field-label an-x61">
                            Información Necesaria
                            <span class="an-x68"> — campos obligatorios para enviar</span>
                            <span class="cfg-badge an-x69" id="badge-info">0</span>
                        </label>
                        <p class="an-x62">Selecciona los predefinidos o agrega los tuyos:</p>
                        <div class="an-x70">
                            <?php
                            // chip -> clave del campo en CAMPOS_GRUPO (sin clave = depende solo del grupo)
                            $CHIP_KEY = ['OTT'=>'ott','Conductor'=>'conductor','Auxiliar'=>'auxiliar','Cedula'=>'cedula','Placa'=>'placa','EAI'=>'eai','UC'=>'uc','OTA'=>'ota',
                                'MAWB'=>'mawb','HAWB'=>'hawb','Vuelo'=>'num_vuelo','Piezas Reservadas'=>'piezas_reservadas','Cajas Reservadas'=>'cajas_reservadas'];
                            $GRUPO_DE = ['Terrestre'=>'terrestre','Aereo'=>'aereo'];
                            foreach($CAMPOS_PRE as $grupo=>$chips): ?>
                                <?php foreach($chips as $c): ?>
                                <span class="info-chip-btn" data-grupo="<?php echo $GRUPO_DE[$grupo]??'';?>" data-key="<?php echo $CHIP_KEY[$c]??'';?>" onclick="addInfo('<?php echo $c;?>')"><?php echo $c;?></span>
                                <?php endforeach;?>
                            <?php endforeach;?>
                        </div>
                        <div class="an-x71" id="info-lista"></div>
                        <button class="an-x72" type="button" onclick="addInfo()">
                            + Campo personalizado
                        </button>
                    </div>

                    <!-- Campos adicionales opcionales -->
                    <div>
                        <label class="field-label an-x61">
                            Campos del formulario
                            <span class="an-x28"> — opcionales</span>
                            <span class="cfg-badge" id="badge-campos">0</span>
                        </label>
                        <p class="an-x62">Campos extra que aparecen en el formulario:</p>
                        <div class="an-x71" id="campos-lista"></div>
                        <button class="an-x73" type="button" onclick="addCampo()">
                            + Agregar campo
                        </button>
                    </div>

                </div>
            </div>
        </div><!-- /modal-step-2 -->

    </form>
    </div><!-- modal-body -->

    <div class="modal-footer">
        <button class="btn btn--outline" onclick="cerrarModal()">Cancelar</button>
        <div class="an-x77">
            <button class="btn btn--outline" id="btn-paso-ant" style="display:none" onclick="navPaso(-1)">← Atrás</button>
            <button class="btn btn--primary" id="btn-siguiente" onclick="navPaso(1)">Siguiente →</button>
            <button class="btn btn--primary" id="btn-guardar" style="display:none"
                    onclick="document.getElementById('form-param').submit()">Guardar Novedad</button>
        </div>
    </div>
</div><!-- modal-box -->
</div><!-- modal-overlay -->

<!-- Datos de novedades existentes para edición -->
<script>

const NOVEDADES_DATA = <?php echo json_encode($novedades_custom, JSON_UNESCAPED_UNICODE); ?>;
const TAXONOMIA_M    = <?php echo json_encode($taxonomia,        JSON_UNESCAPED_UNICODE); ?>;
let campoIdx = 0;
let infoIdx  = 0;
let _mE = { eje:'', tipo:'', subtipo:'', nombre:'', tipologias:[] };


function toggleAcc(head) {
    head.closest('.acc').classList.toggle('open');
}

function toggleDetail(rowId) {
    const row = document.getElementById(rowId);
    const ch  = document.getElementById('chevron-' + rowId);
    row.classList.toggle('open');
    ch.style.transform = row.classList.contains('open') ? 'rotate(180deg)' : '';
}

// Al editar, el paso 1 muestra eje, tipo y subtipo ya elegidos (sin reiniciar _mE como hacen selEje/selTipo)
function precargarClasificacion(n) {
    const eje=n.eje||'', tipo=n.tipo_cls||'', sub=n.subtipo||'';
    const rEje=document.querySelector('input[name="m_eje"][value="'+eje+'"]');
    if (rEje) { rEje.checked=true; rEje.closest('.m-op-btn').classList.add('sel-radio'); }
    const llenar=(sel, valores, actual, vacio)=>{
        sel.innerHTML='<option value="">'+vacio+'</option>';
        const lista=valores.slice(); if (actual && !lista.includes(actual)) lista.push(actual);
        lista.forEach(v=>{const o=document.createElement('option');o.value=v;o.textContent=v;sel.appendChild(o);});
        sel.value=actual; sel.disabled=false;
    };
    llenar(document.getElementById('m-sel-tipo'), Object.keys(TAXONOMIA_M[eje]||{}), tipo, 'Seleccione...');
    llenar(document.getElementById('m-sel-subtipo'), (TAXONOMIA_M[eje]||{})[tipo]||[], sub, 'Seleccione...');
    ['m-grp-tipo','m-grp-subtipo'].forEach(id=>document.getElementById(id).style.opacity='1');
}

function abrirModal(key = '') {
    limpiarForm();
    if (key && NOVEDADES_DATA[key]) {
        const n = NOVEDADES_DATA[key];
        document.getElementById('modal-title').textContent = 'Editar Tipo de Novedad';
        document.getElementById('form-accion').value       = 'editar';
        _mE.nombre=n.titulo.replace(/_/g,' '); _mE.eje=n.eje||''; _mE.tipo=n.tipo_cls||''; _mE.subtipo=n.subtipo||''; _mE.tipologias=(n.tipologia?[n.tipologia]:[]);
        document.getElementById('f-titulo-hidden').value  = n.titulo;
        document.getElementById('f-eje-hidden').value     = n.eje||'';
        document.getElementById('f-tipo-hidden').value    = n.tipo_cls||'';
        document.getElementById('f-subtipo-hidden').value = n.subtipo||'';
        document.getElementById('m-nombre').value         = _mE.nombre;
        document.getElementById('m-nombre-hint').textContent = 'ID: '+n.titulo;
        document.getElementById('m-grp-nombre').style.opacity='1'; document.getElementById('m-nombre').disabled=false;
        document.getElementById('m-grp-tipologia').style.display='';
        // Tipología (una sola)
        _mE.tipologias=n.tipologia?[n.tipologia]:[];
        _mE.tipologias.forEach(t=>{
            const cb=document.querySelector(`input[name="tipologia"][value="${t}"]`);
            if(cb){ cb.checked=true; cb.closest('.m-op-btn').classList.add('sel-check'); }
        });
        // Ops
        (n.tipo_operacion||[]).forEach(op=>{const cb=document.querySelector(`input[name="tipo_operacion[]"][value="${op}"]`);if(cb){cb.checked=true;cb.closest('.m-op-btn').classList.add('sel-check');}});
        precargarClasificacion(n);
        // Config
        document.getElementById('f-desc').value=n.descripcion||'';
        ['req-resp','req-adj','terr','vuelo','tiemp'].forEach(k=>{ const el=document.getElementById('f-'+k); if(el) el.checked=!!n['requiere_'+(k==='req-resp'?'respuesta':k==='req-adj'?'adjunto':k==='terr'?'involucra_terrestre_x':k)]; });
        document.getElementById('f-req-resp').checked=!!n.requiere_respuesta;
        document.getElementById('f-req-adj').checked=!!n.requiere_adjunto;
        document.getElementById('f-terr').checked=!!n.involucra_terrestre;
        document.getElementById('f-vuelo').checked=!!n.involucra_vuelo;
        document.getElementById('f-tiemp').checked=!!n.involucra_tiempos;
        document.getElementById('f-notif-admin').checked=n.notificar_admin!==false;
        document.getElementById('f-notif-torre').checked=n.notificar_torre!==false;
        document.getElementById('f-activo').checked=n.activo!==false;
        document.getElementById('f-causales').value=(n.causales||[]).join('\n');
        contarLineas(document.getElementById('f-causales'),'badge-caus');
        document.querySelectorAll('input[name="actores_reportantes[]"]').forEach(cb=>cb.checked=(n.actores_reportantes||[]).includes(cb.value));
        document.querySelectorAll('input[name="actores_respondentes[]"]').forEach(cb=>cb.checked=(n.actores_respondentes||[]).includes(cb.value));
        document.querySelectorAll('input[name="actores_etiquetables[]"]').forEach(cb=>cb.checked=(n.actores_etiquetables||[]).includes(cb.value));
        cntBadge('actores_reportantes[]','badge-rep'); cntBadge('actores_respondentes[]','badge-res'); cntBadge('actores_etiquetables[]','badge-etiq');
        renderBadges('rep'); renderBadges('res'); renderBadges('etiq');
        (n.info_necesaria||[]).forEach(c=>addInfo(c.nombre,c.tipo));
        // Campos por grupo (si no hay configuracion previa, todos marcados)
        document.querySelectorAll('input[name^="cg["]').forEach(cb=>{
            const g=cb.name.match(/^cg\[(\w+)\]/)[1], sel=(n.campos_grupo||{})[g];
            cb.checked = sel ? sel.includes(cb.value) : true;
        });
        irPaso( 1);
    } else {
        document.getElementById('modal-title').textContent='Crear Tipo de Novedad';
        document.getElementById('form-accion').value='crear';
        irPaso(1);
    }
    document.getElementById('modal-overlay').classList.add('open');
}

function cerrarModal() {
    document.getElementById('modal-overlay').classList.remove('open');
    limpiarForm();
}

function limpiarForm() {
    _mE={eje:'',tipo:'',subtipo:'',nombre:'',tipologias:[]};
    ['f-titulo-hidden','f-eje-hidden','f-tipo-hidden','f-subtipo-hidden'].forEach(id=>{const e=document.getElementById(id);if(e)e.value='';});
    const mn=document.getElementById('m-nombre'); if(mn){mn.value='';mn.disabled=true;}
    const mh=document.getElementById('m-nombre-hint'); if(mh)mh.textContent='';
    const fd=document.getElementById('f-desc'); if(fd) fd.value='';
    const fc=document.getElementById('f-causales'); if(fc) fc.value='';
    const fa=document.getElementById('f-activo'); if(fa) fa.checked=true;
    const fna=document.getElementById('f-notif-admin'); if(fna) fna.checked=true;
    const fnt=document.getElementById('f-notif-torre'); if(fnt) fnt.checked=true;
    ['f-req-resp','f-req-adj','f-terr','f-vuelo','f-tiemp'].forEach(id=>{const e=document.getElementById(id);if(e)e.checked=false;});
    document.querySelectorAll('input[name="tipo_operacion[]"],input[name="m_eje"],input[name="tipologia"],input[name="actores_reportantes[]"],input[name="actores_respondentes[]"],input[name="actores_etiquetables[]"]').forEach(i=>{i.checked=false;i.closest('.m-op-btn,.actor-check')?.classList.remove('sel-radio','sel-check');});
    const st=document.getElementById('m-sel-tipo'); if(st){st.innerHTML='<option value=""></option>';st.disabled=true;}
    const ss=document.getElementById('m-sel-subtipo'); if(ss){ss.innerHTML='<option value=""></option>';ss.disabled=true;}
    ['m-grp-tipo','m-grp-subtipo','m-grp-nombre'].forEach(id=>{const e=document.getElementById(id);if(e)e.style.opacity='.4';});
    const gt=document.getElementById('m-grp-tipologia'); if(gt)gt.style.display='none';
    const mr=document.getElementById('m-resumen'); if(mr)mr.style.display='none';
    const cl=document.getElementById('campos-lista'); if(cl) cl.innerHTML='';
    const il=document.getElementById('info-lista'); if(il) il.innerHTML='';
    campoIdx=0; infoIdx=0;
    document.querySelectorAll('input[name^="cg["]').forEach(cb=>cb.checked=true);
    cgActualizar();
    ['badge-rep','badge-res','badge-etiq','badge-caus','badge-campos','badge-info'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent='0';});
    ['badges-rep','badges-res','badges-etiq'].forEach(id=>{const e=document.getElementById(id);if(e)e.innerHTML='';});
    _paso=1;
}

function validarClasificacion() {
    const ops=document.querySelectorAll('input[name="tipo_operacion[]"]:checked');
    if(!ops.length){alert('Selecciona al menos un tipo de operación.');return false;}
    if(!_mE.eje){alert('Selecciona el eje.');return false;}
    if(!_mE.tipo){alert('Selecciona el tipo.');return false;}
    if(!_mE.subtipo){alert('Selecciona el subtipo.');return false;}
    if(!_mE.nombre||_mE.nombre.split(/\s+/).length<2){alert('Escribe el nombre de la novedad (Acción + Complemento).');return false;}
    if(!_mE.tipologias.length){alert('Selecciona la tipología.');return false;}
    document.getElementById('f-titulo-hidden').value=_mE.nombre.toUpperCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^A-Z0-9\s]/g,'').trim().replace(/\s+/g,'_');
    document.getElementById('f-eje-hidden').value=_mE.eje; document.getElementById('f-tipo-hidden').value=_mE.tipo; document.getElementById('f-subtipo-hidden').value=_mE.subtipo;
    document.getElementById('s2-resumen').textContent=`${_mE.eje}  ${_mE.tipo}  ${_mE.subtipo}  ${_mE.nombre} [${_mE.tipologias.join(' / ')}]`;
    return true;
}

// ── Asistente lineal: 1 Clasificación · 2 Permisos · 3 Características · 4 Campos ──
let _paso = 1;
function irPaso(n) {
    if (n>1 && !validarClasificacion()) return;
    _paso = n;
    cgActualizar();
    resumenesPermisos();
    document.getElementById('modal-step-1').style.display = n===1 ? '' : 'none';
    document.getElementById('modal-step-2').style.display = n>1 ? '' : 'none';
    for (let i=2;i<=4;i++) { const p=document.getElementById('cfg-panel-'+i); if(p) p.style.display = i===n ? '' : 'none'; }
    for (let i=1;i<=4;i++) {
        const b=document.getElementById('step-ind-'+i);
        b.classList.toggle('is-active', i===n);
        b.classList.toggle('is-done', i<n);
        if (i===n) b.setAttribute('aria-current','step'); else b.removeAttribute('aria-current');
    }
    document.getElementById('btn-paso-ant').style.display = n>1 ? '' : 'none';
    document.getElementById('btn-siguiente').style.display = n<4 ? '' : 'none';
    document.getElementById('btn-guardar').style.display = n===4 ? '' : 'none';
    const body=document.querySelector('.modal-body'); if(body) body.scrollTop=0;
}
function navPaso(dir) { irPaso(Math.max(1, Math.min(4, _paso + dir))); }

// ── Campos por grupo ─────────────────────────────────────
function cgActualizar() {
    let alguno=false;
    document.querySelectorAll('.cg-bloque').forEach(b=>{
        const on=!!document.getElementById(b.dataset.flag)?.checked;
        b.style.display=on?'':'none'; if(on) alguno=true;
    });
    const v=document.getElementById('cg-vacio'); if(v) v.style.display=alguno?'none':'';
    // Chips de Informacion Necesaria: solo los de grupos activos y campos marcados
    document.querySelectorAll('.info-chip-btn[data-grupo]').forEach(ch=>{
        const g=ch.dataset.grupo; if(!g) return;
        const bloque=document.querySelector(`.cg-bloque[data-grupo="${g}"]`);
        let ok=!!document.getElementById(bloque.dataset.flag)?.checked;
        if(ok && ch.dataset.key) ok=!!document.querySelector(`input[name="cg[${g}][]"][value="${ch.dataset.key}"]`)?.checked;
        ch.style.display=ok?'':'none';
    });
}
document.addEventListener('change',e=>{ if(e.target.name&&e.target.name.startsWith('cg[')) cgActualizar(); });
function cgTodos(g,val){ document.querySelectorAll(`input[name="cg[${g}][]"]`).forEach(cb=>cb.checked=val); }
['f-terr','f-vuelo','f-tiemp'].forEach(id=>document.getElementById(id)?.addEventListener('change',cgActualizar));


// ── Actor search filter ───────────────────────────────────
function filtrarActores(input, tipo) {
    var q = input.value.toLowerCase();
    var map = {rep:'actors-rep', res:'actors-res', etiq:'actors-etiq'};
    var container = document.getElementById(map[tipo]);
    if (!container) return;
    container.querySelectorAll('.perm-opt').forEach(function(chip) {
        var txt = chip.textContent.toLowerCase();
        chip.style.display = txt.includes(q) ? '' : 'none';
    });
}

// ── Render selected actor badges ─────────────────────────
function renderBadges(tipo) {
    var nameMap = {rep:'actores_reportantes[]', res:'actores_respondentes[]', etiq:'actores_etiquetables[]'};
    var wrapMap = {rep:'badges-rep', res:'badges-res', etiq:'badges-etiq'};
    var name  = nameMap[tipo];
    var wrap  = document.getElementById(wrapMap[tipo]);
    if (!wrap) return;
    var checked = document.querySelectorAll('input[name="'+name+'"]:checked');
    if (checked.length === 0) { wrap.innerHTML=''; return; }
    wrap.innerHTML = Array.from(checked).map(function(cb) {
        return '<span class="actor-sel-badge">'+cb.value+
               '<button type="button" onclick="deselActor(\''+name+'\',\''+cb.value+'\',\''+tipo+'\')">\u00d7</button></span>';
    }).join('');
}
function deselActor(name, val, tipo) {
    var cb = document.querySelector('input[name="'+name+'"][value="'+val+'"]');
    if (cb) { cb.checked=false; cntBadge(name, {rep:'badge-rep',res:'badge-res',etiq:'badge-etiq'}[tipo]); renderBadges(tipo); }
}

// ── Cascada Paso 1
function selEje(lbl,eje){
    document.querySelectorAll('input[name="m_eje"]').forEach(r=>{r.checked=false;r.closest('.m-op-btn').classList.remove('sel-radio');});
    lbl.querySelector('input').checked=true; lbl.classList.add('sel-radio'); _mE.eje=eje; _mE.tipo=''; _mE.subtipo=''; _mE.nombre=''; resetTipologias();
    const sel=document.getElementById('m-sel-tipo'); sel.innerHTML='<option value="">Seleccione...</option>';
    Object.keys(TAXONOMIA_M[eje]||{}).forEach(t=>{const o=document.createElement('option');o.value=t;o.textContent=t;sel.appendChild(o);});
    sel.disabled=false; document.getElementById('m-grp-tipo').style.opacity='1';
    document.getElementById('m-sel-subtipo').innerHTML='<option value=""></option>'; document.getElementById('m-sel-subtipo').disabled=true;
    document.getElementById('m-grp-subtipo').style.opacity='.4'; document.getElementById('m-grp-nombre').style.opacity='.4';
    const mn=document.getElementById('m-nombre'); mn.disabled=true; mn.value='';
    document.getElementById('m-grp-tipologia').style.display='none'; document.getElementById('m-resumen').style.display='none';
}
function selTipo(val){
    _mE.tipo=val; _mE.subtipo=''; _mE.nombre=''; resetTipologias();
    const sel=document.getElementById('m-sel-subtipo'); sel.innerHTML='<option value="">Seleccione...</option>';
    (TAXONOMIA_M[_mE.eje]?.[val]||[]).forEach(s=>{const o=document.createElement('option');o.value=s;o.textContent=s;sel.appendChild(o);});
    sel.disabled=false; document.getElementById('m-grp-subtipo').style.opacity='1';
    document.getElementById('m-grp-nombre').style.opacity='.4'; document.getElementById('m-nombre').disabled=true; document.getElementById('m-nombre').value='';
    document.getElementById('m-grp-tipologia').style.display='none';
}
function selSubtipo(val){
    _mE.subtipo=val; _mE.nombre=''; resetTipologias();
    document.getElementById('m-grp-nombre').style.opacity='1'; document.getElementById('m-nombre').disabled=false; document.getElementById('m-nombre').value='';
    document.getElementById('m-grp-tipologia').style.display='none'; document.getElementById('m-nombre-hint').textContent='';
}
function validarNombreModal(input){
    const val=input.value.trim(); const hint=document.getElementById('m-nombre-hint');
    if(val.split(/\s+/).length<2){hint.style.color='#c0392b';hint.textContent=val.length>1?'Agrega un complemento. Ej: Accidente de Trabajo':'';document.getElementById('m-grp-tipologia').style.display='none';_mE.nombre='';return;}
    _mE.nombre=val; const id=val.toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^A-Z0-9\s]/g,'').trim().replace(/\s+/g,'_');
    hint.style.color='var(--secondary-color)'; hint.textContent='ID: '+id;
    document.getElementById('m-grp-tipologia').style.display=''; actualizarResumenM();
}
function resetTipologias(){
    _mE.tipologias=[];
    document.querySelectorAll('input[name="tipologia"]').forEach(cb=>{cb.checked=false;cb.closest('.m-op-btn').classList.remove('sel-check');});
}
function selTipologia(){
    const marcadas=Array.from(document.querySelectorAll('input[name="tipologia"]:checked'));
    document.querySelectorAll('input[name="tipologia"]').forEach(cb=>cb.closest('.m-op-btn').classList.toggle('sel-check',cb.checked));
    _mE.tipologias=marcadas.map(cb=>cb.value); actualizarResumenM();
}
function actualizarResumenM(){
    if(!_mE.nombre) return;
    const parts=[_mE.eje,_mE.tipo,_mE.subtipo,_mE.nombre].filter(Boolean);
    if(_mE.tipologias.length) parts.push('['+_mE.tipologias.join(' / ')+']');
    document.getElementById('m-res-text').textContent=parts.join('  ');
    document.getElementById('m-resumen').style.display='';
}

// Info Necesaria
function addInfo(nombre='',tipo='text'){
    const lista=document.getElementById('info-lista'); const idx=infoIdx++;
    const div=document.createElement('div'); div.className='info-row';
    div.innerHTML=`<input type="text" name="info_nombre[]" value="${nombre}" placeholder="Campo obligatorio" required>
        <select name="info_tipo[]"><option value="text" ${tipo==='text'?'selected':''}>Texto</option><option value="number" ${tipo==='number'?'selected':''}>Número</option><option value="date" ${tipo==='date'?'selected':''}>Fecha</option><option value="textarea" ${tipo==='textarea'?'selected':''}>Parrafo</option></select>
        <button type="button" class="btn-rm" onclick="this.closest('.info-row').remove();updInfo()"></button>`;
    lista.appendChild(div); updInfo();
    document.getElementById('acc-info').classList.add('open');
}
function updInfo(){ document.getElementById('badge-info').textContent=document.querySelectorAll('#info-lista .info-row').length; }

function addCampo(nombre='', tipo='text', req=false) {
    const lista = document.getElementById('campos-lista');
    const idx   = campoIdx++;
    const div   = document.createElement('div');
    div.className = 'campo-row';
    div.innerHTML = `
        <input type="text" name="campo_nombre[]" value="${nombre}" placeholder="Nombre" required>
        <select name="campo_tipo[]">
            <option value="text"     ${tipo==='text'    ?'selected':''}>Texto</option>
            <option value="number"   ${tipo==='number'  ?'selected':''}>Número</option>
            <option value="date"     ${tipo==='date'    ?'selected':''}>Fecha</option>
            <option value="textarea" ${tipo==='textarea'?'selected':''}>Parrafo</option>
            <option value="select"   ${tipo==='select'  ?'selected':''}>Lista</option>
        </select>
        <label class="req-lbl">
            <input type="checkbox" name="campo_requerido[${idx}]" value="1" ${req?'checked':''}>
            <span>Si</span>
        </label>
        <button type="button" class="btn-rm" onclick="this.closest('.campo-row').remove();updCampos()"></button>
    `;
    lista.appendChild(div);
    updCampos();
    document.querySelector('#acc-campos').classList.add('open');
}

function updCampos() {
    document.getElementById('badge-campos').textContent = document.querySelectorAll('#campos-lista .campo-row').length;
}
// Texto resumen de cada campo desplegable de Permisos
function resumenesPermisos() {
    const lista = (name) => Array.from(document.querySelectorAll('input[name="'+name+'"]:checked')).map(c=>c.value);
    const texto = (v, vacio) => !v.length ? vacio : (v.length<=3 ? v.join(', ') : v.slice(0,3).join(', ')+' +'+(v.length-3));
    [['rep','actores_reportantes[]'],['res','actores_respondentes[]'],['etiq','actores_etiquetables[]']].forEach(([k,n])=>{
        const e=document.getElementById('sum-'+k); if(e) e.textContent = texto(lista(n),'Ninguno');
        const b=document.getElementById('badge-'+k); if(b) b.textContent = lista(n).length;
    });
    const nt=[]; if(document.getElementById('f-notif-admin')?.checked) nt.push('Administrador'); if(document.getElementById('f-notif-torre')?.checked) nt.push('Torre de Control');
    const sn=document.getElementById('sum-notif'); if(sn) sn.textContent = nt.length ? nt.join(', ') : 'Ninguna';
    const sa=document.getElementById('sum-activo'); if(sa) sa.textContent = document.getElementById('f-activo')?.checked ? 'Activo' : 'Inactivo';
}
document.addEventListener('change', e => { if (e.target.closest && e.target.closest('.perm-dd')) resumenesPermisos(); });

function cntBadge(name, id) {
    var el = document.getElementById(id);
    if (el) el.textContent = document.querySelectorAll(`input[name="${name}"]:checked`).length;
    resumenesPermisos();
}
function selAll(name, check, badge) {
    document.querySelectorAll(`input[name="${name}"]`).forEach(cb => cb.checked = check);
    cntBadge(name, badge);
}
function contarLineas(ta, badge) {
    const count = ta.value.split('\n').filter(l=>l.trim()).length;
    document.getElementById(badge).textContent = count;
}

document.addEventListener('DOMContentLoaded', function() {
    // Operacion checkboxes
    document.querySelectorAll('input[name="tipo_operacion[]"]').forEach(function(cb) {
        cb.addEventListener('change', function() {
            this.closest('.m-op-btn').classList.toggle('sel-check', this.checked);
        });
    });
    // Cerrar al clic fuera
    var overlay = document.getElementById('modal-overlay');
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) cerrarModal();
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
