<?php
// data/taxonomia.php
// Árbol de clasificación: Eje → Tipo → Subtipo
// Esta es la estructura base. El admin puede ampliarla en parametrización.

$taxonomia = [
    'Personas' => [
        'Actos Inseguros' => [
            'Conductas',
            'Elementos de Proteccion',
        ],
    ],
    'Producto' => [
        'Contenido' => [
            'Flor',
        ],
        'Unidad de Empaque' => [
            'Carton',
            'Empaque',
            'Etiqueta',
            'Zuncho',
        ],
    ],
    'Proceso' => [
        'Flujo de Informacion' => [
            'Documentos y Reportes',
        ],
        'Operativo' => [
            'Documentos y Reportes',
            'Plataforma Informatica',
            'Disponibilidad Personas',
            'Elementos de Trabajo',
            'Infraestructura',
            'Itinerario',
            'Transaccional',
        ],
        'Administrativo' => [
            'Estandar y Procedimiento',
            'Documentos de Conductores y Vehiculos',
            'Pagos',
        ],
    ],
];

$tipologias = [
    'SST' => 'SST — Salud y Seguridad en el Trabajo',
    'BDS' => 'BDS — Breakdown Services',
    'OSL' => 'OSL — Operaciones Logisticas',
    'GC'  => 'GC — Gestion de Calidad',
    'OPR' => 'OPR — Operativa',
    'SEG' => 'SEG — Seguridad',
];
