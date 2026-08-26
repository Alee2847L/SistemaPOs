<?php
require_once '../config/conexion.php';

$token = $_GET['token'] ?? '';

// Verificar si el token es válido y no ha expirado[cite: 3]
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE token_recuperacion = ? AND token_expiracion > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$tokenValido = ($user) ? true : false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña - Sistema POS</title>
    <!-- FontAwesome para los iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body {
            background-color: #eef2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-card h2 {
            margin-top: 0;
            color: #2c3e50;
            text-align: center;
            font-size: 24px;
        }
        .login-card p {
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            padding-right: 40px;
            border: 1px solid #cccccc;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 38px;
            background: none;
            border: none;
            cursor: pointer;
            color: #7f8c8d;
            font-size: 16px;
        }
        .toggle-password:hover {
            color: #34495e;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #3498db;
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-login:hover {
            background-color: #2980b9;
        }
        .alert-error, .alert-success {
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
            display: none;
            text-align: center;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .back-login {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        .back-login a {
            color: #3498db;
            text-decoration: none;
        }
        .back-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h2>Restablecer</h2>
        <p>Ingresa tu nueva contraseña para continuar</p>

        <div id="alertError" class="alert-error"></div>
        <div id="alertSuccess" class="alert-success"></div>

        <?php if (!$tokenValido): ?>
            <div class="alert-error" style="display: block;">
                El enlace de recuperación es inválido o ha expirado.[cite: 3]
            </div>
            <div class="back-login">
                <a href="login.php">Volver al inicio de sesión</a>
            </div>
        <?php else: ?>
            <form id="resetForm">
                <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password">Nueva Contraseña</label>
                    <input type="password" id="password" required placeholder="••••••••">
                    <button type="button" id="togglePassword1" class="toggle-password">
                        <i class="fa-solid fa-eye" id="iconEye1"></i>
                    </button>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña</label>
                    <input type="password" id="confirm_password" required placeholder="••••••••">
                    <button type="button" id="togglePassword2" class="toggle-password">
                        <i class="fa-solid fa-eye" id="iconEye2"></i>
                    </button>
                </div>

                <button type="submit" class="btn-login" style="background-color: #2ecc71;">Actualizar Contraseña</button>
            </form>
            
            <div class="back-login">
                <a href="login.php">Recordé mi contraseña, volver</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Funcionalidad para ver/ocultar contraseña 1
        const togglePassword1 = document.getElementById('togglePassword1');
        const passwordInput = document.getElementById('password');
        const iconEye1 = document.getElementById('iconEye1');

        if (togglePassword1) {
            togglePassword1.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                iconEye1.classList.toggle('fa-eye');
                iconEye1.classList.toggle('fa-eye-slash');
            });
        }

        // Funcionalidad para ver/ocultar contraseña 2
        const togglePassword2 = document.getElementById('togglePassword2');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const iconEye2 = document.getElementById('iconEye2');

        if (togglePassword2) {
            togglePassword2.addEventListener('click', function () {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                iconEye2.classList.toggle('fa-eye');
                iconEye2.classList.toggle('fa-eye-slash');
            });
        }

        // Petición AJAX para actualizar la contraseña
        const resetForm = document.getElementById('resetForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const alertError = document.getElementById('alertError');
                const alertSuccess = document.getElementById('alertSuccess');
                alertError.style.display = 'none';
                alertSuccess.style.display = 'none';

                const pass = passwordInput.value;
                const confirmPass = confirmPasswordInput.value;

                if (pass !== confirmPass) {
                    alertError.textContent = 'Las contraseñas no coinciden.';
                    alertError.style.display = 'block';
                    return;
                }

                const formData = new FormData();
                formData.append('accion', 'actualizar_password');
                formData.append('token', document.getElementById('token').value);
                formData.append('password', pass);

                fetch('../api/auth.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alertSuccess.textContent = data.message || 'Contraseña actualizada correctamente.';
                        alertSuccess.style.display = 'block';
                        resetForm.style.display = 'none';
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2500);
                    } else {
                        alertError.textContent = data.message || 'Error al actualizar la contraseña.';
                        alertError.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alertError.textContent = 'Error de conexión con el servidor.';
                    alertError.style.display = 'block';
                });
            });
        }
    </script>
</body>
</html>