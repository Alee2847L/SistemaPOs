<?php
// views/usuarios.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$rolActual = $_SESSION['usuario_rol'] ?? 'vendedor';

// Bloquear acceso si no es administrador
if ($rolActual !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// --- OBTENER EL NOMBRE DE LA EMPRESA DESDE LA BD ---
$nombre_empresa = "INVERSIONES J.A"; // Valor por defecto
try {
    // Si tu variable de conexión usa otro nombre (ej. $conn), cámbiala aquí
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
    }
} catch (Exception $e) {
    // Si ocurre algún error o la tabla no existe, se mantiene el valor por defecto
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - <?php echo $nombre_empresa; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    },
                    boxShadow: {
                        'xs': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts & FontAwesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col selection:bg-blue-500 selection:text-white p-4 sm:p-8">

    <div class="max-w-[1200px] w-full mx-auto flex-grow flex flex-col">
        <!-- Cabecera del Módulo -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-user-shield text-blue-600"></i> Gestión de Usuarios del Sistema
                </h2>
                <p class="text-slate-500 text-xs sm:text-sm mt-0.5">Administra los accesos y roles del personal.</p>
            </div>
            <button onclick="abrirModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs sm:text-sm px-4 py-2.5 rounded-xl transition shadow-xs flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-xs"></i> Nuevo Usuario
            </button>
        </div>

        <!-- Contenedor de la Tabla -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex-grow">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-6 py-3.5">ID</th>
                            <th class="px-6 py-3.5">Nombre</th>
                            <th class="px-6 py-3.5">Email</th>
                            <th class="px-6 py-3.5">Rol</th>
                            <th class="px-6 py-3.5">Estado</th>
                            <th class="px-6 py-3.5 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaUsuarios" class="divide-y divide-slate-100 text-sm">
                        <!-- Carga mediante JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Formulario (Estilo Tailwind) -->
    <div id="modalUsuario" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 sm:p-7 w-full max-w-md shadow-xl border border-slate-100">
            <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2" id="modalTitulo">
                <i class="fa-solid fa-user text-blue-600"></i> Nuevo Usuario
            </h3>
            <form id="formUsuario" class="space-y-4">
                <input type="hidden" id="usu_id">
                
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nombre:</label>
                    <input type="text" id="usu_nombre" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" required>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Email:</label>
                    <input type="email" id="usu_email" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" required>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Contraseña:</label>
                    <input type="password" id="usu_password" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="Dejar en blanco para mantener la actual (al editar)">
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Rol del Sistema:</label>
                    <select id="usu_rol" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" required>
                        <option value="vendedor">Vendedor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Estado:</label>
                    <select id="usu_estado" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" required>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
                
                <div class="flex items-center justify-end gap-2.5 pt-3">
                    <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold px-4 py-2.5 rounded-xl transition" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-5 py-2.5 rounded-xl transition shadow-xs">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts de Lógica (Intactos) -->
    <script>
        document.addEventListener('DOMContentLoaded', cargarUsuarios);

        function cargarUsuarios() {
            fetch('../api/usuarios.php?accion=listar')
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    let html = '';
                    res.data.forEach(u => {
                        const badgeRol = u.rol === 'admin' 
                            ? '<span class="bg-rose-50 text-rose-600 border border-rose-200 text-xs font-semibold px-2.5 py-1 rounded-lg">Administrador</span>' 
                            : '<span class="bg-emerald-50 text-emerald-600 border border-emerald-200 text-xs font-semibold px-2.5 py-1 rounded-lg">Vendedor</span>';
                        
                        const badgeEstado = Number(u.estado) === 1 
                            ? '<span class="bg-blue-50 text-blue-600 border border-blue-200 text-xs font-semibold px-2.5 py-1 rounded-lg">Activo</span>' 
                            : '<span class="bg-slate-100 text-slate-500 border border-slate-200 text-xs font-semibold px-2.5 py-1 rounded-lg">Inactivo</span>';
                        
                        html += `
                            <tr class="hover:bg-slate-50/50 transition border-b border-slate-100 last:border-none">
                                <td class="px-6 py-4 font-semibold text-slate-900">#${u.id}</td>
                                <td class="px-6 py-4 font-medium text-slate-800">${u.nombre}</td>
                                <td class="px-6 py-4 text-slate-600">${u.email}</td>
                                <td class="px-6 py-4">${badgeRol}</td>
                                <td class="px-6 py-4">${badgeEstado}</td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button class="bg-amber-50 hover:bg-amber-100 text-amber-700 font-medium text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1" onclick="editarUsuario(${u.id})">
                                            <i class="fa-solid fa-pen-to-square text-[11px]"></i> Editar
                                        </button>
                                        <button class="bg-rose-50 hover:bg-rose-100 text-rose-700 font-medium text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1" onclick="eliminarUsuario(${u.id})">
                                            <i class="fa-solid fa-trash-can text-[11px]"></i> Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    document.getElementById('tablaUsuarios').innerHTML = html || '<tr><td colspan="6" class="text-center text-slate-400 py-8">No hay usuarios registrados</td></tr>';
                }
            });
        }

        function abrirModal() {
            document.getElementById('formUsuario').reset();
            document.getElementById('usu_id').value = '';
            document.getElementById('usu_password').required = true;
            document.getElementById('modalTitulo').innerHTML = '<i class="fa-solid fa-user-plus text-blue-600"></i> Nuevo Usuario';
            document.getElementById('modalUsuario').classList.remove('hidden');
            document.getElementById('modalUsuario').classList.add('flex');
        }

        function cerrarModal() {
            document.getElementById('modalUsuario').classList.add('hidden');
            document.getElementById('modalUsuario').classList.remove('flex');
        }

        function editarUsuario(id) {
            fetch(`../api/usuarios.php?accion=obtener&id=${id}`)
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    const u = res.data;
                    document.getElementById('usu_id').value = u.id;
                    document.getElementById('usu_nombre').value = u.nombre;
                    document.getElementById('usu_email').value = u.email;
                    document.getElementById('usu_password').value = '';
                    document.getElementById('usu_password').required = false; // Opcional al editar
                    document.getElementById('usu_rol').value = u.rol;
                    document.getElementById('usu_estado').value = u.estado;
                    
                    document.getElementById('modalTitulo').innerHTML = '<i class="fa-solid fa-pen-to-square text-blue-600"></i> Editar Usuario';
                    document.getElementById('modalUsuario').classList.remove('hidden');
                    document.getElementById('modalUsuario').classList.add('flex');
                } else {
                    alert(res.message);
                }
            });
        }

        document.getElementById('formUsuario').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('accion', 'guardar');
            formData.append('id', document.getElementById('usu_id').value);
            formData.append('nombre', document.getElementById('usu_nombre').value);
            formData.append('email', document.getElementById('usu_email').value);
            formData.append('password', document.getElementById('usu_password').value);
            formData.append('rol', document.getElementById('usu_rol').value);
            formData.append('estado', document.getElementById('usu_estado').value);

            fetch('../api/usuarios.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    cerrarModal();
                    cargarUsuarios();
                } else {
                    alert(data.message);
                }
            });
        });

        function eliminarUsuario(id) {
            if(confirm('¿Deseas eliminar este usuario?')) {
                const formData = new FormData();
                formData.append('accion', 'eliminar');
                formData.append('id', id);

                fetch('../api/usuarios.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        cargarUsuarios();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>