<?php
session_start();
if (isset($_SESSION['usuario'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/includes/usuarios_store.php';
$usuarios = usuarios_todos();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['usuario'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    if (isset($usuarios[$user])) {
        $u = $usuarios[$user];
        if (!$u['activo']) {
            $error = 'Tu cuenta esta desactivada. Contacta al administrador.';
        } elseif (usuarios_clave_valida($u, $pass)) {
            $_SESSION['usuario']      = $user;
            $_SESSION['nombre']       = $u['nombre'];
            $_SESSION['perfil']       = $u['perfil'];
            $_SESSION['tipo_actor']   = $u['tipo_actor'];
            $_SESSION['organizacion'] = $u['organizacion'];
            $_SESSION['activo']       = $u['activo'];
            $_SESSION['puede_parametrizar'] = !empty($u['puede_parametrizar']);
            header('Location: index.php'); exit;
        } else { $error = 'Contraseña incorrecta.'; }
    } else { $error = 'Usuario no encontrado.'; }
}
if (isset($_GET['error']) && $_GET['error'] === 'inactivo') {
    $error = 'Tu cuenta ha sido desactivada.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovedApp — Iniciar Sesión</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">

        <div class="login-logo">
            <img src="assets/logo_ght.jpeg" alt="GHT Growers Hub Trading" style="height:56px;width:auto;object-fit:contain;display:block;margin:0 auto .75rem;">
            <span class="login-logo__title">NovedApp</span>
            <span class="login-logo__sub">Sistema de Novedades Operativas — GHT Corporation</span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="login-form__group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" required
                       autocomplete="username"
                       value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>"
                       placeholder="Ej: gestor_osl">
            </div>
            <div class="login-form__group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required
                       autocomplete="current-password" placeholder="••••••••">
            </div>
            <button type="submit" class="login-form__submit">Ingresar</button>
        </form>

        <div class="login-hint">
            <div class="login-hint__title">Usuarios de prueba</div>

            <!-- Administracion -->
            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin:.55rem 0 .2rem">Administracion</div>
            <div class="login-hint__row"><span>Administrador</span>     <code>admin / admin123</code></div>
            <div class="login-hint__row"><span>Torre de Control</span>  <code>torre_control / torre123</code></div>
            <div class="login-hint__row"><span>Gestor GHT</span>        <code>gestor_ght / ght123</code></div>

            <!-- Gestores por actor -->
            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin:.55rem 0 .2rem">Gestores por Actor</div>
            <div class="login-hint__row"><span>7K</span>                <code>gestor_7k / 7k123</code></div>
            <div class="login-hint__row"><span>AdA</span>               <code>gestor_ada / ada123</code></div>
            <div class="login-hint__row"><span>AdC</span>               <code>gestor_adc / adc123</code></div>
            <div class="login-hint__row"><span>BDS</span>               <code>gestor_bds / bds123</code></div>
            <div class="login-hint__row"><span>CECO</span>              <code>gestor_ceco / ceco123</code></div>
            <div class="login-hint__row"><span>CEDI</span>              <code>gestor_cedi / cedi123</code></div>
            <div class="login-hint__row"><span>EAI</span>               <code>gestor_eai / eai123</code></div>
            <div class="login-hint__row"><span>EAI Terceros</span>      <code>gestor_eai_t / eait123</code></div>
            <div class="login-hint__row"><span>EI</span>                <code>gestor_ei / ei123</code></div>
            <div class="login-hint__row"><span>Entidades Gobierno</span><code>gestor_egob / egob123</code></div>
            <div class="login-hint__row"><span>Externo</span>           <code>gestor_ext / ext123</code></div>
            <div class="login-hint__row"><span>OCA</span>               <code>gestor_oca / oca123</code></div>
            <div class="login-hint__row"><span>OSL</span>               <code>gestor_osl / osl123</code></div>
            <div class="login-hint__row"><span>OTA</span>               <code>gestor_ota / ota123</code></div>
            <div class="login-hint__row"><span>OTM</span>               <code>gestor_otm / otm123</code></div>
            <div class="login-hint__row"><span>OTT</span>               <code>gestor_ott / ott123</code></div>
            <div class="login-hint__row"><span>OTT Terceros</span>      <code>gestor_ott_t / ottt123</code></div>
            <div class="login-hint__row"><span>SPX</span>               <code>gestor_spx / spx123</code></div>
            <div class="login-hint__row"><span>TTE Inbound</span>       <code>gestor_tte / tte123</code></div>
            <div class="login-hint__row"><span>UC</span>                <code>gestor_uc / uc123</code></div>

            <!-- Reportantes -->
            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin:.55rem 0 .2rem">Reportantes</div>
            <div class="login-hint__row"><span>EAI Manuelita</span>     <code>reportante_eai / rep123</code></div>
            <div class="login-hint__row"><span>AdC BOG</span>           <code>reportante_adc / radc123</code></div>
            <div class="login-hint__row"><span>CEDI BG</span>           <code>reportante_cedi / rcedi123</code></div>
        </div>
    </div>
</div>
</body>
</html>
