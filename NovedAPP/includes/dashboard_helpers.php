<?php
// includes/dashboard_helpers.php — utilidades compartidas por los dashboards con tarjetas KPI clicables

/** Enlace de una tarjeta KPI: cambia el estado conservando la búsqueda y quitando la selección múltiple. */
function kpi_href(string $base_url, string $estado): string {
    return $base_url . '?' . http_build_query(array_merge($_GET, ['estado' => $estado, 'estados' => []]));
}

/** Atributo aria-current para la tarjeta del estado que se está viendo (solo si no hay selección múltiple). */
function kpi_actual(string $f_estado, array $f_estados, string $estado): string {
    return (empty($f_estados) && $f_estado === $estado) ? ' aria-current="true"' : '';
}


/** Página de tablero de cada permiso. */
function dash_pagina_perfil(string $perfil): string {
    return ['administrador' => 'dashboard_admin.php', 'torre_control' => 'dashboard_torre.php',
            'gestor' => 'dashboard_gestor.php'][$perfil] ?? 'dashboard_reportante.php';
}

/** Guarda filtros, estados y página del tablero (sin el parámetro del panel) para que «Volver» no los pierda. */
function dash_recordar_vista(): void {
    $qs = [];
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    unset($qs['ver']);
    $_SESSION['dash_vista'] = http_build_query($qs);
}

/** URL del tablero propio con la última vista recordada. */
function dash_volver_url(): string {
    $qs = $_SESSION['dash_vista'] ?? '';
    return dash_pagina_perfil($_SESSION['perfil'] ?? '') . ($qs !== '' ? '?' . $qs : '');
}

/** Texto para mostrar (ya escapado): sin guiones bajos (EAI_TERCEROS → EAI TERCEROS). */
function texto_visible(?string $s): string {
    return htmlspecialchars(str_replace('_', ' ', (string)$s));
}
