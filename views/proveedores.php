<?php
// views/proveedores.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$rolActual = $_SESSION['usuario_rol'] ?? 'vendedor';
$es_admin = (strtolower($rolActual) === 'admin' || strtolower($rolActual) === 'administrador');

$nombre_empresa = "INVERSIONES J.A.";
try {
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['nombre_empresa'])) $nombre_empresa = htmlspecialchars($row['nombre_empresa']);
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proveedores — <?php echo $nombre_empresa; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <header class="bg-white border-b border-slate-200 px-6 py-3.5 flex justify-between items-center sticky top-0 z-50 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="bg-blue-600 text-white p-2 rounded-xl shadow-sm">
                <i class="fa-solid fa-truck text-sm"></i>
            </div>
            <div>
                <span class="font-bold text-base tracking-tight text-slate-900 block leading-none">Módulo de Proveedores</span>
                <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
            </div>
        </div>
        <a href="productos.php" class="text-xs font-semibold bg-slate-100 hover:bg-slate-200 px-3.5 py-2 rounded-xl transition text-slate-700 flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Volver a Productos
        </a>
    </header>

    <main class="max-w-[1400px] w-full mx-auto px-4 sm:px-6 py-8 flex-grow flex flex-col gap-6">
        
        <!-- Cabecera y Acciones -->
        <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <div>
                <h4 class="font-bold text-slate-900 text-lg">Directorio de Proveedores</h4>
                <p class="text-xs text-slate-500 mt-0.5">Administra la información de tus proveedores y los productos que cada uno te suministra.</p>
            </div>
            <?php if ($es_admin): ?>
                <button onclick="abrirModalProveedor()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs sm:text-sm px-4 py-2.5 rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus"></i> Nuevo Proveedor
                </button>
            <?php endif; ?>
        </div>

        <!-- Tabla de Proveedores -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex-grow flex flex-col">
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-semibold tracking-wider">
                            <th class="py-3.5 px-4">Empresa</th>
                            <th class="py-3.5 px-4">Contacto</th>
                            <th class="py-3.5 px-4">Teléfono</th>
                            <th class="py-3.5 px-4">Correo</th>
                            <th class="py-3.5 px-4">RTN</th>
                            <th class="py-3.5 px-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaProveedores" class="divide-y divide-slate-200 text-xs sm:text-sm text-slate-700 bg-white">
                        <!-- Datos cargados vía JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Registrar / Editar Proveedor -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4" id="modalProveedor" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h4 class="font-bold text-slate-900 text-base" id="tituloModalProv">Nuevo Proveedor</h4>
                <button onclick="cerrarModalProveedor()" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded-lg transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6">
                <form id="formProveedor" class="space-y-4">
                    <input type="hidden" id="prov_id">
                    <div>
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Nombre de la Empresa:</label>
                        <input type="text" id="prov_nombre_empresa" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" required>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Persona de Contacto:</label>
                            <input type="text" id="prov_contacto" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition">
                        </div>
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Teléfono:</label>
                            <input type="text" id="prov_telefono" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Correo Electrónico:</label>
                            <input type="email" id="prov_correo" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition">
                        </div>
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">RTN:</label>
                            <input type="text" id="prov_rtn" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition">
                        </div>
                    </div>
                    <div>
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Dirección:</label>
                        <textarea id="prov_direccion" rows="2" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-semibold rounded-xl transition" onclick="cerrarModalProveedor()">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-semibold rounded-xl transition shadow-sm">Guardar Proveedor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Vista de Productos del Proveedor -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4" id="modalProductosProveedor" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <div>
                    <h4 class="font-bold text-slate-900 text-base" id="lblNombreProveedorModal">Productos del Proveedor</h4>
                    <span class="text-xs text-slate-500">Catálogo de artículos provistos y precios de costo</span>
                </div>
                <button onclick="cerrarModalProductosProv()" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded-lg transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 overflow-y-auto flex-grow flex flex-col gap-4">
                <input type="hidden" id="asoc_proveedor_id">
                
                <?php if ($es_admin): ?>
                <!-- Formulario rápido para asociar producto -->
                <div class="bg-slate-50 p-4 border border-slate-200 rounded-xl grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                    <div class="sm:col-span-7">
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Seleccionar Producto:</label>
                        <select id="select_asoc_producto" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm outline-none">
                            <!-- Se llena por JS -->
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Precio Especial (L.):</label>
                        <input type="number" step="0.01" id="input_asoc_precio" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm outline-none" placeholder="0.00">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="button" onclick="vincularProductoProveedor()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs py-2.5 rounded-xl transition shadow-sm">
                            <i class="fa-solid fa-plus"></i> Vincular
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-900 text-white text-[11px] font-semibold tracking-wider">
                                <th class="py-3 px-4">Código</th>
                                <th class="py-3 px-4">Nombre del Producto</th>
                                <th class="py-3 px-4">Precio con este Proveedor</th>
                                <?php if ($es_admin): ?>
                                    <th class="py-3 px-4 text-center">Acción</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tablaProductosProveedor" class="divide-y divide-slate-200 text-xs sm:text-sm text-slate-700 bg-white">
                            <!-- Dinámico -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const esAdmin = <?php echo $es_admin ? 'true' : 'false'; ?>;

        document.addEventListener('DOMContentLoaded', () => {
            cargarProveedores();
            cargarSelectProductosGenerales();
        });

        function cargarProveedores() {
            fetch('../api/proveedores.php?accion=listar')
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    let html = '';
                    res.data.forEach(p => {
                        html += `
                            <tr class="hover:bg-slate-50/80 transition border-b border-slate-100">
                                <td class="py-3 px-4 font-semibold text-slate-900">${p.nombre_empresa}</td>
                                <td class="py-3 px-4 text-slate-600">${p.contacto ?? 'N/D'}</td>
                                <td class="py-3 px-4 text-slate-600">${p.telefono ?? 'N/D'}</td>
                                <td class="py-3 px-4 text-slate-600">${p.correo ?? 'N/D'}</td>
                                <td class="py-3 px-4 text-slate-600">${p.rtn ?? 'N/D'}</td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button class="bg-cyan-50 hover:bg-cyan-100 text-cyan-700 font-semibold text-xs px-2.5 py-1.5 rounded-xl transition" onclick="verProductosProveedor(${p.id}, '${escapeHtml(p.nombre_empresa)}')"><i class="fa-solid fa-boxes-stacked"></i> Productos</button>
                                        ${esAdmin ? `
                                            <button class="bg-amber-50 hover:bg-amber-100 text-amber-700 font-semibold text-xs px-2.5 py-1.5 rounded-xl transition" onclick="editarProveedor(${p.id})"><i class="fa-solid fa-pen"></i></button>
                                            <button class="bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold text-xs px-2.5 py-1.5 rounded-xl transition" onclick="eliminarProveedor(${p.id})"><i class="fa-solid fa-trash"></i></button>
                                        ` : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    document.getElementById('tablaProveedores').innerHTML = html || '<tr><td colspan="6" class="text-center text-slate-400 py-6 text-sm">No hay proveedores registrados</td></tr>';
                }
            });
        }

        function abrirModalProveedor() {
            document.getElementById('formProveedor').reset();
            document.getElementById('prov_id').value = '';
            document.getElementById('tituloModalProv').innerText = 'Nuevo Proveedor';
            document.getElementById('modalProveedor').style.display = 'flex';
        }

        function cerrarModalProveedor() {
            document.getElementById('modalProveedor').style.display = 'none';
        }

        function editarProveedor(id) {
            fetch(`../api/proveedores.php?accion=obtener&id=${id}`)
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    const p = res.data;
                    document.getElementById('prov_id').value = p.id;
                    document.getElementById('prov_nombre_empresa').value = p.nombre_empresa;
                    document.getElementById('prov_contacto').value = p.contacto;
                    document.getElementById('prov_telefono').value = p.telefono;
                    document.getElementById('prov_correo').value = p.correo;
                    document.getElementById('prov_rtn').value = p.rtn;
                    document.getElementById('prov_direccion').value = p.direccion;
                    document.getElementById('tituloModalProv').innerText = 'Editar Proveedor';
                    document.getElementById('modalProveedor').style.display = 'flex';
                }
            });
        }

        document.getElementById('formProveedor').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('accion', 'guardar');
            formData.append('id', document.getElementById('prov_id').value);
            formData.append('nombre_empresa', document.getElementById('prov_nombre_empresa').value);
            formData.append('contacto', document.getElementById('prov_contacto').value);
            formData.append('telefono', document.getElementById('prov_telefono').value);
            formData.append('correo', document.getElementById('prov_correo').value);
            formData.append('rtn', document.getElementById('prov_rtn').value);
            formData.append('direccion', document.getElementById('prov_direccion').value);

            fetch('../api/proveedores.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    cerrarModalProveedor();
                    cargarProveedores();
                } else {
                    alert(data.message);
                }
            });
        });

        function eliminarProveedor(id) {
            if(!confirm('¿Estás seguro de eliminar este proveedor?')) return;
            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('id', id);

            fetch('../api/proveedores.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) cargarProveedores();
                else alert(data.message);
            });
        }

        function verProductosProveedor(proveedorId, nombreEmpresa) {
            document.getElementById('asoc_proveedor_id').value = proveedorId;
            document.getElementById('lblNombreProveedorModal').innerText = `Productos de: ${nombreEmpresa}`;
            cargarRelacionProductosProveedor(proveedorId);
            document.getElementById('modalProductosProveedor').style.display = 'flex';
        }

        function cerrarModalProductosProv() {
            document.getElementById('modalProductosProveedor').style.display = 'none';
        }

        function cargarRelacionProductosProveedor(proveedorId) {
            fetch(`../api/proveedores.php?accion=productos_proveedor&proveedor_id=${proveedorId}`)
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    let html = '';
                    res.data.forEach(item => {
                        html += `
                            <tr class="border-b border-slate-100">
                                <td class="py-3 px-4 font-semibold text-slate-900">${item.codigo_barra}</td>
                                <td class="py-3 px-4 text-slate-800">${item.nombre}</td>
                                <td class="py-3 px-4 text-slate-600 font-semibold">L. ${parseFloat(item.precio).toFixed(2)}</td>
                                ${esAdmin ? `
                                    <td class="py-3 px-4 text-center">
                                        <button onclick="desvincularProducto(${item.id})" class="text-rose-600 hover:text-rose-800 text-xs font-semibold bg-rose-50 px-2.5 py-1.5 rounded-lg">Quitar</button>
                                    </td>
                                ` : ''}
                            </tr>
                        `;
                    });
                    document.getElementById('tablaProductosProveedor').innerHTML = html || '<tr><td colspan="4" class="text-center text-slate-400 py-4 text-xs">Este proveedor aún no tiene productos vinculados</td></tr>';
                }
            });
        }

        function cargarSelectProductosGenerales() {
            fetch('../api/productos.php?accion=listar')
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    let html = '<option value="">Selecciona un producto...</option>';
                    res.data.forEach(p => {
                        html += `<option value="${p.id}">${p.codigo_barra} - ${p.nombre}</option>`;
                    });
                    const sel = document.getElementById('select_asoc_producto');
                    if(sel) sel.innerHTML = html;
                }
            });
        }

        function vincularProductoProveedor() {
            const proveedorId = document.getElementById('asoc_proveedor_id').value;
            const productoId = document.getElementById('select_asoc_producto').value;
            const precio = document.getElementById('input_asoc_precio').value;

            if(!productoId || !precio) {
                alert('Selecciona un producto y escribe el precio de costo.');
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'vincular_producto');
            formData.append('proveedor_id', proveedorId);
            formData.append('producto_id', productoId);
            formData.append('precio', precio);

            fetch('../api/proveedores.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('input_asoc_precio').value = '';
                    cargarRelacionProductosProveedor(proveedorId);
                } else {
                    alert(data.message);
                }
            });
        }

        function desvincularProducto(relacionId) {
            if(!confirm('¿Deseas quitar este producto del proveedor?')) return;
            const formData = new FormData();
            formData.append('accion', 'desvincular_producto');
            formData.append('id', relacionId);

            fetch('../api/proveedores.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const provId = document.getElementById('asoc_proveedor_id').value;
                    cargarRelacionProductosProveedor(provId);
                }
            });
        }

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>
</body>
</html>