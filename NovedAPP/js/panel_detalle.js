// js/panel_detalle.js — Detalle de novedad en un panel lateral, sin salir del tablero.
// · Interceptar el clic en cualquier enlace «detalle_novedad.php?id=…»; Ctrl/⌘/Shift/clic central siguen abriendo la página completa.
// · El contenido viene de detalle_novedad.php?id=…&parcial=1 y las acciones (responder, resolver) se envían por fetch.
// · La URL del tablero lleva ?ver=ID: se puede compartir y «Atrás» cierra el panel sin perder filtros ni página.
(function () {
    'use strict';
    var pd = document.getElementById('pd');
    if (!pd) return;

    var $ = function (id) { return document.getElementById(id); };
    var body = $('pd-body'), main = document.querySelector('main.container');
    var actualId = null, ultimoFoco = null, ticket = 0;
    var ancha = window.matchMedia('(min-width:1100px)');
    var SEL_ENLACE = 'a[href*="detalle_novedad.php?id="]';

    function idDe(a) {
        try { return new URL(a.href, location.href).searchParams.get('id'); } catch (e) { return null; }
    }
    function listaIds() {
        var ids = [], vistos = {};
        document.querySelectorAll('tbody ' + SEL_ENLACE).forEach(function (a) {
            var id = idDe(a);
            if (id && !vistos[id]) { vistos[id] = 1; ids.push(id); }
        });
        return ids;
    }
    function filaDe(id) {
        var a = document.querySelector('tbody a[href*="detalle_novedad.php?id=' + encodeURIComponent(id) + '"]');
        return a ? a.closest('tr') : null;
    }
    function urlTablero(id) {
        var u = new URL(location.href);
        if (id) u.searchParams.set('ver', id); else u.searchParams.delete('ver');
        return u.pathname + (u.search || '') + u.hash;
    }

    function ajustarModo() {
        var top = document.querySelector('.site-header');
        pd.style.setProperty('--pd-top', ancha.matches && top ? Math.round(top.getBoundingClientRect().bottom) + 'px' : '0px');
        if (main) main.inert = !pd.hidden && !ancha.matches;   // en pantalla completa lo de atrás no recibe foco
    }
    if (ancha.addEventListener) ancha.addEventListener('change', ajustarModo);
    window.addEventListener('resize', ajustarModo);

    function marcarFila(id) {
        document.querySelectorAll('tbody tr.pd-activa').forEach(function (tr) {
            tr.classList.remove('pd-activa');
            var l = tr.querySelector(SEL_ENLACE); if (l) l.removeAttribute('aria-current');
        });
        var tr = id ? filaDe(id) : null;
        if (!tr) return;
        tr.classList.add('pd-activa');
        var l = tr.querySelector(SEL_ENLACE); if (l) l.setAttribute('aria-current', 'true');
        if (ancha.matches) tr.scrollIntoView({ block: 'nearest' });
    }

    function actualizarNavegacion() {
        var ids = listaIds(), i = ids.indexOf(actualId);
        $('pd-prev').disabled = i <= 0;
        $('pd-next').disabled = i < 0 || i >= ids.length - 1;
        $('pd-pos').textContent = i >= 0 ? (i + 1) + ' de ' + ids.length : '';
        $('pd-full').href = 'detalle_novedad.php?id=' + encodeURIComponent(actualId || '');
    }

    function actualizarFila(id, estado) {
        var tr = filaDe(id); if (!tr || !estado) return;
        var sb = tr.querySelector('.sb');
        if (sb) {
            sb.className = 'sb sb--' + estado;
            sb.textContent = estado === 'en_gestion' ? 'En gestión' : estado.charAt(0).toUpperCase() + estado.slice(1);
        }
        var edad = tr.querySelector('.age');
        if (edad && estado !== 'abierta' && estado !== 'en_gestion') { edad.textContent = '—'; edad.classList.remove('age--late'); }
    }

    function actualizarCampana(n) {
        var b = $('notif-badge-count');
        if (!b || isNaN(n)) return;
        b.textContent = Math.min(n, 99);
        b.style.display = n > 0 ? 'flex' : 'none';
    }

    function pintar(html, ok) {
        body.innerHTML = html;
        body.scrollTop = 0;
        var raiz = body.querySelector('.dn-partial');
        if (ok && raiz) actualizarFila(raiz.dataset.id, raiz.dataset.estado);
    }

    function pedir(url, opciones, alTerminar) {
        var mio = ++ticket;
        fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }, opciones || {}))
            .then(function (r) {
                if (r.redirected && /login\.php/.test(r.url)) { location.href = r.url; return null; }
                actualizarCampana(parseInt(r.headers.get('X-No-Leidas'), 10));
                return r.text().then(function (t) { return { ok: r.ok, html: t }; });
            })
            .then(function (res) { if (res && mio === ticket) alTerminar(res); })
            .catch(function () {
                if (mio !== ticket) return;
                body.innerHTML = '<div class="pd__estado" role="alert">No se pudo cargar el detalle. <button type="button" class="pd__btn" id="pd-reintentar">Reintentar</button></div>';
                $('pd-reintentar').addEventListener('click', function () { abrir(actualId, { historial: 'ninguno' }); });
            });
    }

    // historial: 'push' (abrir) | 'replace' (cambiar de novedad) | 'ninguno' (viene del historial)
    function abrir(id, opts) {
        opts = opts || {};
        var estabaCerrado = pd.hidden;
        actualId = id;
        pd.hidden = false;
        document.body.classList.add('pd-abierto');
        ajustarModo();
        marcarFila(id);
        actualizarNavegacion();
        if (opts.historial === 'push') history.pushState({ ver: id }, '', urlTablero(id));
        else if (opts.historial === 'replace') history.replaceState({ ver: id }, '', urlTablero(id));
        body.innerHTML = '<div class="pd__estado" role="status">Cargando…</div>';
        pedir('detalle_novedad.php?id=' + encodeURIComponent(id) + '&parcial=1', null, function (res) {
            pintar(res.html, res.ok);
            if (estabaCerrado || opts.foco) body.focus({ preventScroll: true });
        });
    }

    function cerrar(opts) {
        if (pd.hidden) return;
        opts = opts || {};
        ticket++;
        pd.hidden = true;
        document.body.classList.remove('pd-abierto');
        marcarFila(null);
        if (main) main.inert = false;
        if (!opts.sinHistorial) history.pushState({}, '', urlTablero(null));
        actualId = null;
        if (ultimoFoco && document.contains(ultimoFoco)) ultimoFoco.focus();
    }

    function vecino(delta) {
        var ids = listaIds(), i = ids.indexOf(actualId) + delta;
        if (i >= 0 && i < ids.length) abrir(ids[i], { historial: 'replace', foco: true });
    }

    // ── Clics en enlaces de detalle ──────────────────────────────────────────
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest && e.target.closest(SEL_ENLACE);
        if (!a || pd.contains(a) || a.target === '_blank') return;
        var id = idDe(a); if (!id) return;
        e.preventDefault();
        ultimoFoco = a.closest('tr') ? (a.closest('tr').querySelector('a.row-link') || a) : a;
        abrir(id, { historial: pd.hidden ? 'push' : 'replace', foco: true });
    });

    $('pd-close').addEventListener('click', function () { cerrar(); });
    $('pd-prev').addEventListener('click', function () { vecino(-1); });
    $('pd-next').addEventListener('click', function () { vecino(1); });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || pd.hidden || document.querySelector('dialog[open]')) return;
        cerrar();
    });

    // ── Acciones dentro del panel (responder, replicar, resolver) ────────────
    pd.addEventListener('submit', function (e) {
        var f = e.target;
        e.preventDefault();
        e.stopPropagation();   // evita que confirmar.js haga un envío normal y recargue la página
        function enviar() {
            if (f.dataset.enviando) return;
            f.dataset.enviando = '1';
            body.classList.add('pd__enviando');
            var accion = (f.querySelector('[name="accion"]') || {}).value;
            pedir('detalle_novedad.php?id=' + encodeURIComponent(actualId) + '&parcial=1', { method: 'POST', body: new FormData(f) }, function (res) {
                body.classList.remove('pd__enviando');
                pintar(res.html, res.ok);
                if (window.mostrarToast && res.ok) window.mostrarToast(accion === 'resolver' ? 'Novedad marcada como resuelta.' : 'Mensaje enviado.');
            });
        }
        if (f.dataset.confirm && window.confirmarAccion) {
            window.confirmarAccion({ title: f.dataset.confirmTitle, text: f.dataset.confirm, ok: f.dataset.confirmOk, style: f.dataset.confirmStyle }, enviar);
        } else {
            enviar();
        }
    }, true);

    // ── Historial: Atrás cierra el panel, Adelante lo reabre ─────────────────
    window.addEventListener('popstate', function () {
        var id = new URL(location.href).searchParams.get('ver');
        if (id) abrir(id, { historial: 'ninguno' });
        else cerrar({ sinHistorial: true });
    });

    // Enlace compartido con ?ver=ID
    var inicial = new URL(location.href).searchParams.get('ver');
    if (inicial) abrir(inicial, { historial: 'replace' });
})();
