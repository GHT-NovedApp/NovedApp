<?php
// includes/panel_detalle.php — Panel lateral con el detalle de la novedad (se incluye al final de cada tablero).
// El contenido llega de detalle_novedad.php?id=…&parcial=1; el enlace normal de cada fila sigue funcionando sin JavaScript.
?>
<aside id="pd" class="pd" role="complementary" aria-label="Detalle de novedad" hidden>
  <div class="pd__bar">
    <button type="button" class="pd__btn pd__close" id="pd-close">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      <span class="pd__lbl-desktop">Cerrar</span><span class="pd__lbl-mobile">Volver a la lista</span>
    </button>
    <div class="pd__nav" role="group" aria-label="Cambiar de novedad">
      <button type="button" class="pd__btn pd__btn--icon" id="pd-prev" aria-label="Novedad anterior">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <span class="pd__pos" id="pd-pos" aria-live="polite"></span>
      <button type="button" class="pd__btn pd__btn--icon" id="pd-next" aria-label="Novedad siguiente">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
      </button>
    </div>
    <a class="pd__link" id="pd-full" href="#">Abrir en página completa</a>
  </div>
  <div class="pd__body" id="pd-body" tabindex="-1"></div>
</aside>
<script src="../js/panel_detalle.js" defer></script>
