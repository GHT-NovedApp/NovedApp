// js/formulario_dinamico.js
document.addEventListener('DOMContentLoaded', () => {

  const ACTORES_TERRESTRE = ['OTT', 'OTT_TERCEROS'];

  const selectTipologia   = document.getElementById('tipologia');
  const selectNovedad     = document.getElementById('tipo_novedad');
  const grupoTerrestre    = document.getElementById('grupo-terrestre');
  const grupoAereo        = document.getElementById('grupo-aereo');
  const causalContainer   = document.getElementById('select-causal-container');
  const selectCausal      = document.getElementById('causal');
  const selectCausal2     = document.getElementById('causal2');
  const camposDinamicos   = document.getElementById('campos-dinamicos-container');
  const btnAddEai         = document.getElementById('btn-add-eai');
  const eaiList           = document.getElementById('eai-list');
  const tipoClasCont      = document.getElementById('tipo-clasificacion-container');
  const tipoClasValor     = document.getElementById('tipo-clasificacion-valor');
  const respCont          = document.getElementById('respondentes-container');
  const respLista         = document.getElementById('respondentes-lista');

  // ── Toggles ──
  document.getElementById('toggle-adjunto')?.addEventListener('change', e => {
    document.getElementById('adjunto-container').classList.toggle('hidden', !e.target.checked);
  });
  document.getElementById('toggle-correo')?.addEventListener('change', e => {
    document.getElementById('correo-container').classList.toggle('hidden', !e.target.checked);
  });

  // ── EAI multi-input ──
  btnAddEai?.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'eai-row';
    row.innerHTML = '<input type="text" name="eai[]" class="form-control" placeholder="Nombre o código EAI">';
    eaiList.appendChild(row);
  });

  // ── Tipología → novedades ──
  selectTipologia.addEventListener('change', () => {
    const tip = selectTipologia.value;
    selectNovedad.innerHTML = '<option value="">Seleccione un tipo de novedad…</option>';
    selectNovedad.disabled  = !tip;
    if (!tip) { resetContextual(); return; }

    let count = 0;
    Object.entries(CATALOGO).forEach(([titulo, n]) => {
      const tipNov = n.tipologia || '';
      if (tipNov === tip || tipNov.startsWith(tip + ',') || tipNov.includes(',' + tip)) {
        const opt = document.createElement('option');
        opt.value = titulo;
        opt.textContent = titulo.replace(/_/g, ' ');
        opt.dataset.terrestre  = n.involucra_terrestre ? 'true' : 'false';
        opt.dataset.aereo      = n.involucra_vuelo     ? 'true' : 'false';
        opt.dataset.causales   = JSON.stringify(n.causales || []);
        opt.dataset.datos      = n.datos_minimos || '';
        opt.dataset.reqResp    = n.requiere_respuesta  ? 'true' : 'false';
        opt.dataset.actoresResp= JSON.stringify(n.actores_respondentes || []);
        opt.dataset.usuariosResp=JSON.stringify(n.usuarios_respondentes || []);
        selectNovedad.appendChild(opt);
        count++;
      }
    });

    if (count === 0) {
      const opt = document.createElement('option');
      opt.value = ''; opt.disabled = true;
      opt.textContent = 'No hay novedades para esta tipología';
      selectNovedad.appendChild(opt);
    }
    resetContextual();
  });

  // ── Novedad seleccionada ──
  selectNovedad.addEventListener('change', () => {
    const sel = selectNovedad.options[selectNovedad.selectedIndex];
    if (!sel || !sel.value) { resetContextual(); return; }

    const involucraTerrestre  = sel.dataset.terrestre  === 'true';
    const involucraAereo      = sel.dataset.aereo      === 'true';
    const causales            = JSON.parse(sel.dataset.causales    || '[]');
    const datosMinimos        = sel.dataset.datos      || '';
    const reqResp             = sel.dataset.reqResp    === 'true';
    const usuariosResp        = JSON.parse(sel.dataset.usuariosResp|| '[]');
    const actoresResp         = JSON.parse(sel.dataset.actoresResp || '[]');

    // ── Clasificación ──
    if (tipoClasCont && tipoClasValor) {
      tipoClasCont.classList.remove('hidden');
      tipoClasValor.textContent = reqResp ? 'Requiere Respuesta' : 'INF — Informativa';
    }

    // ── Respondentes predeterminados ──
    if (respCont && respLista) {
      if (usuariosResp.length > 0) {
        respLista.innerHTML = usuariosResp.map(u =>
          `<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem">
            
            <div>
              <span style="font-size:.875rem;font-weight:600">${u.nombre}</span>
              <span style="font-size:.78rem;color:var(--text-muted);margin-left:.4rem">${u.tipo_actor} — ${u.organizacion}</span>
              <span style="font-size:.72rem;background:#dbeafe;color:#1e40af;padding:1px 7px;border-radius:99px;margin-left:.4rem;font-weight:700;text-transform:uppercase">${u.perfil}</span>
            </div>
          </div>`
        ).join('');
        respCont.classList.remove('hidden');
      } else if (actoresResp.length > 0) {
        // Actores definidos pero sin usuarios en el sistema aún
        respLista.innerHTML = `<p style="font-size:.85rem;color:var(--text-muted)">
          Actores responsables: <strong>${actoresResp.join(', ')}</strong><br>
          <span style="font-size:.78rem">No hay usuarios registrados para estos actores en el sistema piloto.</span>
        </p>`;
        respCont.classList.remove('hidden');
      } else {
        respCont.classList.add('hidden');
      }
    }

    // ── Grupos contextuales ──
    const mostrarTerrestre = involucraTerrestre || ACTORES_TERRESTRE.includes(TIPO_ACTOR_USUARIO);
    grupoTerrestre.classList.toggle('hidden', !mostrarTerrestre);
    grupoAereo.classList.toggle('hidden', !involucraAereo);

    // ── Causales ──
    if (causales.length > 0) {
      [selectCausal, selectCausal2].forEach((s, i) => {
        s.innerHTML = i === 0
          ? '<option value="">Seleccione una causa…</option>'
          : '<option value="">Seleccione (opcional)…</option>';
        causales.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c; opt.textContent = c.replace(/_/g, ' ');
          s.appendChild(opt);
        });
      });
      causalContainer.classList.remove('hidden');
    } else {
      causalContainer.classList.add('hidden');
    }

    renderCamposDinamicos(datosMinimos, involucraTerrestre, involucraAereo);
  });

  // ── Campos mínimos dinámicos ──
  function renderCamposDinamicos(datosStr, esTerrestre, esAereo) {
    camposDinamicos.innerHTML = '';
    if (!datosStr || !datosStr.trim()) return;

    const SKIP_T = ['OTT','CONDUCTOR','PLACA','AUXILIAR','CEDULA','VEHICULO','UC','OTA'];
    const SKIP_A = ['MAWB','HAWB','VUELO','CAJAS','AEROLINEA','AWB','PIEZA','RESERVAD','RECIBID'];
    const campos = [];

    const matches = datosStr.matchAll(/([A-Z0-9Á-Ú][A-Za-záéíóúñÁÉÍÓÚÑ\s#%\.]+?)(?:\s*&#58;|\s*:)/g);
    for (const m of matches) {
      const campo = m[1].trim().replace(/&#58;|&#160;|<br>/g, '').trim();
      if (!campo || campo.length <= 1 || campo.length >= 60) continue;
      const cu = campo.toUpperCase();
      if (SKIP_T.some(s => cu.includes(s)) && (esTerrestre || ACTORES_TERRESTRE.includes(TIPO_ACTOR_USUARIO))) continue;
      if (SKIP_A.some(s => cu.includes(s)) && esAereo) continue;
      if (!campos.includes(campo)) campos.push(campo);
    }

    if (!campos.length) return;

    const title = document.createElement('div');
    title.className = 'datos-label';
    title.textContent = 'Datos mínimos requeridos';
    camposDinamicos.appendChild(title);

    const grid = document.createElement('div');
    grid.className = 'fields-grid';
    campos.forEach(campo => {
      const g = document.createElement('div'); g.className = 'form-group';
      const l = document.createElement('label'); l.textContent = campo;
      const i = document.createElement('input'); i.type = 'text'; i.className = 'form-control';
      i.name = 'campo_' + campo.toLowerCase().replace(/\s+/g,'_').replace(/[^a-z0-9_]/g,'');
      i.placeholder = campo;
      g.appendChild(l); g.appendChild(i); grid.appendChild(g);
    });
    camposDinamicos.appendChild(grid);
  }

  function resetContextual() {
    grupoTerrestre.classList.add('hidden');
    grupoAereo.classList.add('hidden');
    causalContainer.classList.add('hidden');
    camposDinamicos.innerHTML = '';
    if (tipoClasCont) tipoClasCont.classList.add('hidden');
    if (respCont) respCont.classList.add('hidden');
    if (ACTORES_TERRESTRE.includes(TIPO_ACTOR_USUARIO)) {
      grupoTerrestre.classList.remove('hidden');
    }
  }

  resetContextual();
});
