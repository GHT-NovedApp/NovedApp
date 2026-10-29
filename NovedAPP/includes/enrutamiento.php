<?php
// includes/enrutamiento.php
// Mapea tipo_actor → usuarios del sistema que pueden responder
// En v1 esto se construye desde los usuarios hardcodeados
// En v2 vendría de la base de datos

require_once __DIR__ . '/usuarios_store.php';

/**
 * Dado un tipo de novedad y su lista de actores respondentes,
 * devuelve los usuarios concretos del sistema que deben ser notificados.
 */
function enrutamiento_obtener_respondentes_usuarios(array $actores_respondentes): array {
    $usuarios = usuarios_todos();

    $resultado = [];
    foreach ($usuarios as $uname => $u) {
        if (!$u['activo']) continue;
        // El usuario puede responder si su tipo_actor está en la lista
        if (in_array($u['tipo_actor'], $actores_respondentes)) {
            $resultado[] = [
                'usuario'      => $uname,
                'nombre'       => $u['nombre'],
                'perfil'       => $u['perfil'],
                'tipo_actor'   => $u['tipo_actor'],
                'organizacion' => $u['organizacion'],
            ];
        }
    }
    return $resultado;
}

/**
 * Devuelve solo los nombres de usuario (para notificaciones).
 */
function enrutamiento_obtener_usuarios_ids(array $actores_respondentes): array {
    $respondentes = enrutamiento_obtener_respondentes_usuarios($actores_respondentes);
    return array_column($respondentes, 'usuario');
}
