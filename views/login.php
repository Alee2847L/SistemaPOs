<?php
session_start();
// Si el usuario ya está autenticado, lo redirige al panel principal
if (isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema POS</title>
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
        .forgot-link {
            text-align: right;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .forgot-link a {
            color: #3498db;
            text-decoration: none;
        }
        .forgot-link a:hover {
            text-decoration: underline;
        }
        /* Estilos para el Modal de Recuperación */
        .modal {
            display: none;
            position: fixed;
            z-index: 10;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            width: 100%;
            max-width: 380px;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .close-modal {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 20px;
            cursor: pointer;
            color: #7f8c8d;
        }
        .close-modal:hover { color: #2c3e50; }
    </style>
</head>
<body>

    <div class="login-card">
        <h2>Bienvenido</h2>
        <p>Ingresa tus credenciales para acceder al sistema</p>

        <div id="alertError" class="alert-error"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" required placeholder="ejemplo@correo.com">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" required placeholder="••••••••">
                <button type="button" id="togglePassword" class="toggle-password">
                    <i class="fa-solid fa-eye" id="iconEye"></i>
                </button>
            </div>

            <div class="forgot-link">
                <a href="#" id="openModal">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn-login">Ingresar</button>
        </form>
    </div>

    <!-- Modal para Recuperación de Contraseña -->
    <div id="recoveryModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <h3 style="color: #2c3e50; margin-top: 0;">Recuperar Contraseña</h3>
            <p style="text-align: left; font-size: 13px;">Ingresa tu correo registrado y te enviaremos las instrucciones.</p>
            
            <div id="alertModal" class="alert-error"></div>
            <div id="alertSuccessModal" class="alert-success"></div>

            <form id="recoveryForm">
                <div class="form-group">
                    <label for="recoveryEmail">Correo Electrónico</label>
                    <input type="email" id="recoveryEmail" required placeholder="ejemplo@correo.com">
                </div>
                <button type="submit" class="btn-login" style="background-color: #2ecc71;">Enviar Enlace</button>
            </form>
        </div>
    </div>

    <script>
        // Funcionalidad para ver/ocultar contraseña
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const iconEye = document.getElementById('iconEye');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if (type === 'password') {
                iconEye.classList.remove('fa-eye-slash');
                iconEye.classList.add('fa-eye');
            } else {
                iconEye.classList.remove('fa-eye');
                iconEye.classList.add('fa-eye-slash');
            }
        });

        // Control del Modal de Recuperación
        const modal = document.getElementById('recoveryModal');
        const openModal = document.getElementById('openModal');
        const closeModal = document.getElementById('closeModal');

        openModal.addEventListener('click', function(e) {
            e.preventDefault();
            modal.style.display = 'flex';
        });

        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });

        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Petición AJAX de Login
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const alertError = document.getElementById('alertError');
            alertError.style.display = 'none';

            const formData = new FormData();
            formData.append('accion', 'login');
            formData.append('email', document.getElementById('email').value);
            formData.append('password', document.getElementById('password').value);

            fetch('../api/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '../index.php';
                } else {
                    alertError.textContent = data.message || 'Credenciales incorrectas';
                    alertError.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertError.textContent = 'Error de conexión con el servidor.';
                alertError.style.display = 'block';
            });
        });

        // Petición AJAX para Solicitar Recuperación
        document.getElementById('recoveryForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const alertModal = document.getElementById('alertModal');
            const alertSuccessModal = document.getElementById('alertSuccessModal');
            alertModal.style.display = 'none';
            alertSuccessModal.style.display = 'none';

            const formData = new FormData();
            formData.append('accion', 'solicitar_recuperacion');
            formData.append('email', document.getElementById('recoveryEmail').value);

            fetch('../api/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertSuccessModal.textContent = data.message;
                    alertSuccessModal.style.display = 'block';
                    document.getElementById('recoveryEmail').value = '';
                } else {
                    alertModal.textContent = data.message || 'Error al procesar la solicitud';
                    alertModal.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertModal.textContent = 'Error de conexión con el servidor.';
                alertModal.style.display = 'block';
            });
        });
    </script>
</body>
</html>