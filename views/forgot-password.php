<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Contraseña</title>
    <!-- Puedes usar los mismos estilos de tu login.php -->
</head>
<body>
    <div class="login-card">
        <h2>Recuperar Contraseña</h2>
        <p>Ingresa tu correo y te enviaremos instrucciones.</p>
        <form action="../api/auth.php" method="POST">
            <input type="hidden" name="accion" value="solicitar_recuperacion">
            <div class="form-group">
                <label>Correo Electrónico</label>
                <input type="email" name="email" required placeholder="ejemplo@correo.com">
            </div>
            <button type="submit" class="btn-login">Enviar enlace</button>
        </form>
    </div>
</body>
</html>