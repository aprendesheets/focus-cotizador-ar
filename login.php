<?php
session_start();
require_once('functions.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = sanitize_text_field($_POST['username']);
    $pass = sanitize_text_field($_POST['password']);

    $users = fl_get_users();

    if (isset($users[$user]) && $users[$user]['password'] === $pass) {
        $_SESSION['fl_user'] = $users[$user];
        header('Location: ?view=dashboard');
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}

include 'templates/header.php';
?>

<div class="fl-login">
    <h2>Login Cotizador Interno</h2>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="post">
        <input type="text" name="username" placeholder="Usuario" required>
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit">Entrar</button>
    </form>
</div>

<?php include 'templates/footer.php'; ?>
