<?php
// views/clientes.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$rolActual = $_SESSION['usuario_rol'] ?? 'vendedor';
$es_admin = (isset($_SESSION['usuario_rol']) && (strtolower($_SESSION['usuario_rol']) === 'admin' || strtolower($_SESSION['usuario_rol']) === 'administrador'));

// --- OBTENER EL NOMBRE DE LA EMPRESA DESDE LA BD ---
$nombre_empresa = "INVERSIONES J."; // Valor por defecto
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
    <title>Gestión de Clientes — <?php echo $nombre_empresa; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#f8fafc', 100: '#f1f5f9', 600: '#2563eb', 700: '#1d4ed8' }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Barra de Navegación -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex justify-between items-center sticky top-0 z-40 shadow-xs">
        <div class="flex items-center gap-2">
            <div class="bg-indigo-600 text-white p-2 rounded-xl shadow-sm"><i class="fa-solid fa-users text-sm"></i></div>
            <div>
                <span class="font-bold text-sm sm:text-base text-slate-900 block leading-none">Módulo de Clientes y Créditos</span>
                <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="max-w-[1400px] w-full mx-auto px-4 sm:px-6 py-6 flex-grow flex flex-col">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 flex-grow flex flex-col">
            
            <!-- Barra de Herramientas -->
            <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 mb-6">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"><i class="fa-solid fa-magnifying-glass text-xs"></i></span>
                    <input type="text" id="inputBuscar" placeholder="Buscar por Código BP, DNI o Nombre..." onkeyup="filtrarClientes()" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition">
                </div>
                <button onclick="abrirModalNuevo()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm px-4 py-2.5 rounded-xl transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus"></i> Nuevo Cliente
                </button>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto rounded-xl border border-slate-200 flex-grow">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-semibold">
                        <tr>
                            <th class="py-3.5 px-4">Código BP</th>
                            <th class="py-3.5 px-4">Estado</th>
                            <th class="py-3.5 px-4">Tipo</th>
                            <th class="py-3.5 px-4">DNI / RTN</th>
                            <th class="py-3.5 px-4">Nombre / Razón Social</th>
                            <th class="py-3.5 px-4">Límite Crédito</th>
                            <th class="py-3.5 px-4">Mora</th>
                            <th class="py-3.5 px-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaClientes" class="divide-y divide-slate-200 text-sm text-slate-700 bg-white"></tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Formulario -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4" id="modalCliente" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h4 class="font-bold text-slate-900 text-base" id="modalTitulo">Nuevo Cliente</h4>
                <button type="button" onclick="cerrarModal()" class="text-slate-400 hover:text-slate-600 p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6">
                <form id="formCliente" class="space-y-4">
                    <input type="hidden" id="cli_es_edicion" value="0">
                    
                    <!-- Código BP y Estado (Estado solo se muestra en edición) -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Código BP:</label>
                            <input type="text" id="cli_codigo_bp" class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl text-sm text-slate-500" readonly>
                        </div>
                        <div id="contenedor_estado" style="display: none;">
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Estado:</label>
                            <select id="cli_estado" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                                <option value="ACT">ACTIVO (ACT)</option>
                                <option value="INA">INACTIVO (INA)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Tipo de Cliente:</label>
                            <select id="cli_tipo_cliente" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                                <option value="natural">Persona Natural</option>
                                <option value="juridico">Persona Jurídica</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">DNI / RTN:</label>
                            <input type="text" id="cli_rtn_dni" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" required>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Nombre Completo / Razón Social:</label>
                        <input type="text" id="cli_nombre" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" required>
                    </div>

                    <!-- Campos de Crédito (Ocultos al crear, visibles al editar) -->
                    <div class="grid grid-cols-2 gap-4" id="contenedor_credito" style="display: none;">
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Límite de Crédito (L.):</label>
                            <input type="number" step="0.01" min="0" id="cli_limite_credito" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" value="0.00">
                        </div>
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Días de Plazo Crédito:</label>
                            <input type="number" min="0" id="cli_dias_credito" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" value="0">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Teléfono:</label>
                            <input type="text" id="cli_telefono" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Correo:</label>
                            <input type="email" id="cli_correo" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Dirección:</label>
                        <input type="text" id="cli_direccion" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" class="px-4 py-2 bg-slate-100 text-slate-700 font-semibold rounded-xl" onclick="cerrarModal()">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-xl">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL FLOTANTE DE SEGURIDAD PARA ELIMINACIÓN -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4" id="modalEliminarSeguridadCliente" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-rose-50">
                <h4 class="font-bold text-rose-900 text-base flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved"></i> Confirmar Eliminación
                </h4>
                <button type="button" onclick="cerrarModalEliminar()" class="text-slate-400 hover:text-slate-600 p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div class="p-3 bg-rose-50 border border-rose-100 rounded-xl">
                    <p class="text-rose-700 font-semibold text-sm">⚠ ¡Atención! Esta acción eliminará al cliente del sistema.</p>
                </div>
                <input type="hidden" id="eliminar_cli_codigo">
                <div>
                    <label class="block font-semibold text-xs text-slate-600 mb-1">Contraseña de Administrador:</label>
                    <input type="password" id="txtClaveAdminCli" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" placeholder="Escribe tu contraseña...">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" class="px-4 py-2 bg-slate-100 text-slate-700 font-semibold rounded-xl" onclick="cerrarModalEliminar()">Cancelar</button>
                    <button type="button" class="px-4 py-2 bg-rose-600 text-white font-semibold rounded-xl" onclick="ejecutarEliminacionCliente()">Confirmar y Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const esAdmin = <?php echo $es_admin ? 'true' : 'false'; ?>;
        let listaClientesOriginal = [];

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('txtClaveAdminCli').addEventListener('keydown', (e) => { if(e.key === 'Enter') ejecutarEliminacionCliente(); });
            cargarClientes();
        });

        function cargarClientes() {
            fetch('../api/clientes.php?accion=listar').then(res => res.json()).then(res => {
                if(res.success) { listaClientesOriginal = res.data; renderizarTabla(res.data); }
            });
        }

        function renderizarTabla(clientes) {
            let html = clientes.length === 0 ? '<tr><td colspan="8" class="text-center py-6 text-slate-400">No hay clientes registrados</td></tr>' : '';
            clientes.forEach(c => {
                const esJuridico = c.tipo_cliente === 'juridico';
                const esActivo = (c.estado || 'ACT') === 'ACT';
                const diasMora = parseInt(c.dias_mora || 0);

                const badgeEstado = esActivo 
                    ? '<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">ACT</span>'
                    : '<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">INA</span>';

                const badgeMora = diasMora > 0 
                    ? `<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">${diasMora} días mora</span>`
                    : '<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Al día</span>';

                html += `<tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="py-3 px-4 font-semibold">${c.codigo_bp}</td>
                    <td class="py-3 px-4">${badgeEstado}</td>
                    <td class="py-3 px-4"><span class="px-2 py-1 rounded-full text-[10px] font-bold ${esJuridico ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700'}">${esJuridico ? 'JURÍDICO' : 'NATURAL'}</span></td>
                    <td class="py-3 px-4">${c.rtn_dni}</td>
                    <td class="py-3 px-4">${c.Nombre}</td>
                    <td class="py-3 px-4">L. ${Number(c.limite_credito || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="py-3 px-4">${badgeMora}</td>
                    <td class="py-3 px-4 text-center">
                        <div class="flex justify-center gap-1">
                            <button type="button" class="bg-emerald-50 text-emerald-700 text-xs px-2 py-1 rounded-lg font-medium cursor-pointer hover:bg-emerald-100 transition" onclick="irAFacturar('${c.codigo_bp}')">Facturar</button>
                            ${esAdmin ? `<button type="button" class="bg-amber-50 text-amber-700 text-xs px-2 py-1 rounded-lg font-medium cursor-pointer hover:bg-amber-100 transition" onclick="editarCliente('${c.codigo_bp}')">Editar</button>` : ''}
                            ${esAdmin && c.codigo_bp !== 'BP000' ? `<button type="button" class="bg-rose-50 text-rose-700 text-xs px-2 py-1 rounded-lg font-medium cursor-pointer hover:bg-rose-100 transition" onclick="eliminarCliente('${c.codigo_bp}')">Eliminar</button>` : ''}
                        </div>
                    </td>
                </tr>`;
            });
            document.getElementById('tablaClientes').innerHTML = html;
        }

        function irAFacturar(codigoBp) {
            window.location.href = `/views/pos.php?codigo_bp=${encodeURIComponent(codigoBp)}`;
        }

        function abrirModalNuevo() {
            document.getElementById('modalTitulo').innerText = 'Nuevo Cliente';
            document.getElementById('cli_es_edicion').value = '0';
            document.getElementById('formCliente').reset();
            document.getElementById('cli_codigo_bp').value = 'AUTOGENERADO';
            
            // Ocultar opciones de crédito y estado al crear nuevo
            document.getElementById('contenedor_estado').style.display = 'none';
            document.getElementById('contenedor_credito').style.display = 'none';

            document.getElementById('modalCliente').style.display = 'flex';
        }

        function editarCliente(codigo_bp) {
            const cliente = listaClientesOriginal.find(c => c.codigo_bp === codigo_bp);
            if (!cliente) return;

            document.getElementById('modalTitulo').innerText = 'Editar Cliente';
            document.getElementById('cli_es_edicion').value = '1';
            
            // Mostrar opciones de crédito y estado al editar
            document.getElementById('contenedor_estado').style.display = 'block';
            document.getElementById('contenedor_credito').style.display = 'grid';

            document.getElementById('cli_codigo_bp').value = cliente.codigo_bp;
            document.getElementById('cli_estado').value = cliente.estado || 'INA';
            document.getElementById('cli_tipo_cliente').value = cliente.tipo_cliente || 'natural';
            document.getElementById('cli_rtn_dni').value = cliente.rtn_dni || '';
            document.getElementById('cli_nombre').value = cliente.Nombre || '';
            document.getElementById('cli_limite_credito').value = cliente.limite_credito || '0.00';
            document.getElementById('cli_dias_credito').value = cliente.dias_credito || '0';
            document.getElementById('cli_telefono').value = cliente.Telefono || '';
            document.getElementById('cli_correo').value = cliente.Correo || '';
            document.getElementById('cli_direccion').value = cliente.Direccion || '';

            document.getElementById('modalCliente').style.display = 'flex';
        }

        function cerrarModal() { document.getElementById('modalCliente').style.display = 'none'; }
        function cerrarModalEliminar() { document.getElementById('modalEliminarSeguridadCliente').style.display = 'none'; }

        document.getElementById('formCliente').addEventListener('submit', async function(e) {
            e.preventDefault();
            const esEdicion = document.getElementById('cli_es_edicion').value === '1';
            
            const formData = new FormData();
            formData.append('accion', 'guardar');
            formData.append('es_edicion', esEdicion ? 1 : 0);
            formData.append('codigo_bp', document.getElementById('cli_codigo_bp').value);
            formData.append('tipo_cliente', document.getElementById('cli_tipo_cliente').value);
            formData.append('rtn_dni', document.getElementById('cli_rtn_dni').value);
            formData.append('nombre', document.getElementById('cli_nombre').value);
            formData.append('telefono', document.getElementById('cli_telefono').value);
            formData.append('direccion', document.getElementById('cli_direccion').value);
            formData.append('correo', document.getElementById('cli_correo').value);

            // Si es edición manda los valores del formulario, si es nuevo fuerza por defecto INA, limite 0 y dias 0
            if (esEdicion) {
                formData.append('estado', document.getElementById('cli_estado').value);
                formData.append('limite_credito', document.getElementById('cli_limite_credito').value);
                formData.append('dias_credito', document.getElementById('cli_dias_credito').value);
            } else {
                formData.append('estado', 'INA');
                formData.append('limite_credito', '0.00');
                formData.append('dias_credito', '0');
            }

            try {
                const res = await fetch('../api/clientes.php', { method: 'POST', body: formData }).then(r => r.json());
                if (res.success) {
                    alert(res.message);
                    cerrarModal();
                    cargarClientes();
                } else {
                    alert(res.message || 'Ocurrió un error');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error de conexión con el servidor.');
            }
        });

        function eliminarCliente(codigo_bp) {
            document.getElementById('eliminar_cli_codigo').value = codigo_bp;
            document.getElementById('txtClaveAdminCli').value = '';
            document.getElementById('modalEliminarSeguridadCliente').style.display = 'flex';
        }

        async function ejecutarEliminacionCliente() {
            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('codigo_bp', document.getElementById('eliminar_cli_codigo').value);
            formData.append('clave_admin', document.getElementById('txtClaveAdminCli').value);

            const res = await fetch('../api/clientes.php', { method: 'POST', body: formData }).then(r => r.json());
            if (res.success) { alert(res.message); cerrarModalEliminar(); cargarClientes(); } 
            else { alert(res.message); }
        }

        function filtrarClientes() {
            let texto = document.getElementById('inputBuscar').value.toLowerCase();
            let filtrados = listaClientesOriginal.filter(c => 
                c.codigo_bp.toLowerCase().includes(texto) || 
                c.rtn_dni.toLowerCase().includes(texto) || 
                c.Nombre.toLowerCase().includes(texto)
            );
            renderizarTabla(filtrados);
        }
    </script>
</body>
</html>