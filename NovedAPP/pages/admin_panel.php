<?php
// "Administración" se fusionó en Parametrización. Esta URL se conserva solo como redirección.
$destino = (($_GET['s'] ?? '') === 'fincas') ? 'admin_organizaciones.php' : 'admin_usuarios.php';
header('Location: ' . $destino, true, 301);
exit;
