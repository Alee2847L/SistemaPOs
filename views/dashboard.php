<?php
// views/dashboard.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Asegurar el inicio de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/conexion.php';

// Validar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Variable para verificar si el usuario tiene rol de Administrador
$rolActual = strtolower($_SESSION['usuario_rol'] ?? '');
$esAdmin = ($rolActual === 'admin' || $rolActual === 'administrador');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - Sistema POS</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { margin: 0; background-color: #f4f6f9; color: #333; }
        
        /* Navbar */
        .navbar {
            background-color: #2c3e50;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .navbar h2 { margin: 0; font-size: 20px; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .btn-logout {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }
        .btn-logout:hover { background-color: #c0392b; }

        /* Contenido Principal */
        .container { padding: 30px; max-width: 1200px; margin: 0 auto; }
        .welcome-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .grid-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        .card-menu {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            text-decoration: none;
            color: #2c3e50;
        }
        .card-menu:hover { transform: translateY(-3px); }
        .card-menu h3 { margin-bottom: 10px; color: #3498db; }
        
        /* Estilo para destacar módulos administrativos */
        .card-menu.admin-card { border-top: 4px solid #f39c12; }
        .card-menu.admin-card h3 { color: #d35400; }
    </style>
</head>
<body>

    <div class="navbar">
        <h2>Sistema POS - INVERSIONES J.A</h2>
        <div class="user-info">
            <span>Hola, <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></strong> (<?php echo ucfirst($_SESSION['usuario_rol'] ?? 'vendedor'); ?>)</span>
            <button class="btn-logout" onclick="cerrarSesion()">Cerrar Sesión</button>
        </div>
    </div>

    <div class="container">
        <div class="welcome-card">
            <h1>Bienvenido al Panel de Control</h1>
            <p>Selecciona un módulo para comenzar a trabajar.</p>
        </div>

        <div class="grid-menu">
            <!-- MÓDULOS OPERATIVOS (Visibles para todos) -->
            <a href="clientes.php" class="card-menu">
                <h3>👥 Clientes</h3>
                <p>Gestionar registro de clientes</p>
            </a>
            
            <a href="productos.php" class="card-menu">
                <h3>📦 Productos</h3>
                <p>Inventario y precios</p>
            </a>
            
            <a href="pos.php" class="card-menu">
                <h3>🛒 Punto de Venta</h3>
                <p>Realizar nuevas ventas</p>
            </a>

            <!-- MÓDULOS EXCLUSIVOS DE ADMINISTRADOR -->
            <?php if ($esAdmin): ?>
                <a href="usuarios.php" class="card-menu admin-card">
                    <h3>⚙️ Usuarios</h3>
                    <p>Administrar accesos</p>
                </a>

                <a href="transacciones.php" class="card-menu admin-card">
                    <h3>📊 Transacciones</h3>
                    <p>Historial y detalles de ventas</p>
                </a>

                <a href="arqueo.php" class="card-menu admin-card">
                    <h3>💰 Arqueo de Caja</h3>
                    <p>Control de efectivo y cierres</p>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function cerrarSesion() {
            const formData = new FormData();
            formData.append('accion', 'logout');

            // Salir de /views/ para invocar a /api/auth.php
            fetch('../api/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'login.php';
                }
            })
            .catch(err => console.error("Error al cerrar sesión:", err));
        }
    </script>
</body>
</html>