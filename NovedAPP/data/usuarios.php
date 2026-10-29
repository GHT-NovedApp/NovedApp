<?php
// Usuarios piloto — un usuario por cada tipo de actor del sistema
$usuarios = [

  // ── Administración ──────────────────────────────
  'admin' => [
    'password'=>'admin123','nombre'=>'Administrador General',
    'perfil'=>'administrador','tipo_actor'=>'GHT',
    'organizacion'=>'GHT Proyectos Logisticos','activo'=>true,
  ],
  'torre_control' => [
    'password'=>'torre123','nombre'=>'Torre de Control GHT',
    'perfil'=>'torre_control','tipo_actor'=>'GHT',
    'organizacion'=>'GHT Proyectos Logisticos','activo'=>true,
  ],
  'gestor_ght' => [
    'password'=>'ght123','nombre'=>'Gestor GHT',
    'perfil'=>'gestor','tipo_actor'=>'GHT',
    'organizacion'=>'GHT Comercio Exterior','activo'=>true,
  ],

  // ── Actores operativos ───────────────────────────
  'gestor_7k' => [
    'password'=>'7k123','nombre'=>'Gestor 7K',
    'perfil'=>'gestor','tipo_actor'=>'7K',
    'organizacion'=>'7K Logistica','activo'=>true,
  ],
  'gestor_ada' => [
    'password'=>'ada123','nombre'=>'Gestor AdA',
    'perfil'=>'gestor','tipo_actor'=>'AdA',
    'organizacion'=>'AdA Operaciones','activo'=>true,
  ],
  'gestor_adc' => [
    'password'=>'adc123','nombre'=>'Gestor AdC',
    'perfil'=>'gestor','tipo_actor'=>'AdC',
    'organizacion'=>'AdC Exportaciones','activo'=>true,
  ],
  'gestor_bds' => [
    'password'=>'bds123','nombre'=>'Gestor BDS',
    'perfil'=>'gestor','tipo_actor'=>'BDS',
    'organizacion'=>'BDS Breakdown Services','activo'=>true,
  ],
  'gestor_ceco' => [
    'password'=>'ceco123','nombre'=>'Gestor CECO',
    'perfil'=>'gestor','tipo_actor'=>'CECO',
    'organizacion'=>'CECO Operaciones','activo'=>true,
  ],
  'gestor_cedi' => [
    'password'=>'cedi123','nombre'=>'Gestor CEDI',
    'perfil'=>'gestor','tipo_actor'=>'CEDI',
    'organizacion'=>'CEDI BG Tocancipa','activo'=>true,
  ],
  'gestor_eai' => [
    'password'=>'eai123','nombre'=>'Gestor EAI',
    'perfil'=>'gestor','tipo_actor'=>'EAI',
    'organizacion'=>'EAI Operaciones','activo'=>true,
  ],
  'gestor_eai_t' => [
    'password'=>'eait123','nombre'=>'Gestor EAI Terceros',
    'perfil'=>'gestor','tipo_actor'=>'EAI_TERCEROS',
    'organizacion'=>'EAI Terceros','activo'=>true,
  ],
  'gestor_ei' => [
    'password'=>'ei123','nombre'=>'Gestor EI',
    'perfil'=>'gestor','tipo_actor'=>'EI',
    'organizacion'=>'EI Exportaciones','activo'=>true,
  ],
  'gestor_egob' => [
    'password'=>'egob123','nombre'=>'Gestor Entidades Gobierno',
    'perfil'=>'gestor','tipo_actor'=>'ENTIDADES_GOBIERNO',
    'organizacion'=>'Entidades Gobierno','activo'=>true,
  ],
  'gestor_ext' => [
    'password'=>'ext123','nombre'=>'Gestor Externo',
    'perfil'=>'gestor','tipo_actor'=>'EXTERNO',
    'organizacion'=>'Externo','activo'=>true,
  ],
  'gestor_oca' => [
    'password'=>'oca123','nombre'=>'Gestor OCA',
    'perfil'=>'gestor','tipo_actor'=>'OCA',
    'organizacion'=>'OCA Bogota','activo'=>true,
  ],
  'gestor_osl' => [
    'password'=>'osl123','nombre'=>'Gestor OSL',
    'perfil'=>'gestor','tipo_actor'=>'OSL',
    'organizacion'=>'OSL Exportaciones Bogota','activo'=>true,
  ],
  'gestor_ota' => [
    'password'=>'ota123','nombre'=>'Gestor OTA',
    'perfil'=>'gestor','tipo_actor'=>'OTA',
    'organizacion'=>'OTA Aerolineas','activo'=>true,
  ],
  'gestor_otm' => [
    'password'=>'otm123','nombre'=>'Gestor OTM',
    'perfil'=>'gestor','tipo_actor'=>'OTM',
    'organizacion'=>'OTM Multimodal','activo'=>true,
  ],
  'gestor_ott' => [
    'password'=>'ott123','nombre'=>'Gestor OTT',
    'perfil'=>'gestor','tipo_actor'=>'OTT',
    'organizacion'=>'OTT Transportes','activo'=>true,
  ],
  'gestor_ott_t' => [
    'password'=>'ottt123','nombre'=>'Gestor OTT Terceros',
    'perfil'=>'gestor','tipo_actor'=>'OTT_TERCEROS',
    'organizacion'=>'OTT Terceros','activo'=>true,
  ],
  'gestor_spx' => [
    'password'=>'spx123','nombre'=>'Gestor SPX',
    'perfil'=>'gestor','tipo_actor'=>'SPX',
    'organizacion'=>'SPX Speedy','activo'=>true,
  ],
  'gestor_tte' => [
    'password'=>'tte123','nombre'=>'Gestor TTE Inbound',
    'perfil'=>'gestor','tipo_actor'=>'TTE_INBOUND_DESTINO',
    'organizacion'=>'TTE Inbound Destino','activo'=>true,
  ],
  'gestor_uc' => [
    'password'=>'uc123','nombre'=>'Gestor UC',
    'perfil'=>'gestor','tipo_actor'=>'UC',
    'organizacion'=>'UC Unidad de Carga','activo'=>true,
  ],

  // ── Reportantes piloto ───────────────────────────
  'reportante_eai' => [
    'password'=>'rep123','nombre'=>'Reportante EAI Manuelita',
    'perfil'=>'reportante','tipo_actor'=>'EAI',
    'organizacion'=>'Finca Manuelita','activo'=>true,
  ],
  'reportante_adc' => [
    'password'=>'radc123','nombre'=>'Reportante AdC BOG',
    'perfil'=>'reportante','tipo_actor'=>'AdC',
    'organizacion'=>'AdC EXP BOG','activo'=>true,
  ],
  'reportante_cedi' => [
    'password'=>'rcedi123','nombre'=>'Reportante CEDI BG',
    'perfil'=>'reportante','tipo_actor'=>'CEDI',
    'organizacion'=>'BG-Tocancipa','activo'=>true,
  ],
];
