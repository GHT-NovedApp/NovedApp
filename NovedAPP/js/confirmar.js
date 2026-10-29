// js/confirmar.js — Confirmación con <dialog> para acciones destructivas.
// Formularios: <form method="POST" data-confirm="Texto" data-confirm-title="Título"
//                    data-confirm-ok="Eliminar" [data-confirm-style="primary"]>
// Desde JS:    confirmarAccion({title, text, ok, style}, function () { ... });
(function () {
    function crearDialogo() {
        var d = document.createElement('dialog');
        d.className = 'confirm-dialog';
        d.setAttribute('aria-labelledby', 'cd-titulo');
        d.innerHTML =
            '<form method="dialog">' +
            '<div class="confirm-dialog__body"><h3 id="cd-titulo"></h3><p id="cd-texto"></p></div>' +
            '<div class="confirm-dialog__actions">' +
            '<button type="submit" value="cancel" class="btn btn--outline btn--sm" autofocus>Cancelar</button>' +
            '<button type="submit" value="ok" class="btn btn--danger btn--sm" id="cd-ok"></button>' +
            '</div></form>';
        document.body.appendChild(d);
        return d;
    }

    window.confirmarAccion = function (opts, alConfirmar) {
        if (typeof HTMLDialogElement === 'undefined') {
            if (window.confirm(opts.text)) alConfirmar();
            return;
        }
        var d = document.querySelector('dialog.confirm-dialog') || crearDialogo();
        d.querySelector('#cd-titulo').textContent = opts.title || 'Confirmar acción';
        d.querySelector('#cd-texto').textContent = opts.text || '';
        var ok = d.querySelector('#cd-ok');
        ok.textContent = opts.ok || 'Confirmar';
        ok.className = 'btn btn--sm ' + (opts.style === 'primary' ? 'btn--primary' : 'btn--danger');
        d.returnValue = '';
        d.addEventListener('close', function onClose() {
            d.removeEventListener('close', onClose);
            if (d.returnValue === 'ok') alConfirmar();
        });
        d.showModal();
    };

    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.matches || !f.matches('form[data-confirm]') || f.dataset.confirmado) return;
        e.preventDefault();
        window.confirmarAccion({
            title: f.dataset.confirmTitle,
            text: f.dataset.confirm,
            ok: f.dataset.confirmOk,
            style: f.dataset.confirmStyle
        }, function () { f.dataset.confirmado = '1'; f.submit(); });
    });

    // Aviso breve de confirmación o error (no bloquea)
    window.mostrarToast = function (msg) {
        var t = document.createElement('div');
        t.className = 'toast';
        t.setAttribute('role', 'status');
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 3500);
    };
})();
