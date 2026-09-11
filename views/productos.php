<?php
// views/productos.php
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
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
    }
} catch (Exception $e) {}

// --- OBTENER PROVEEDORES DESDE LA BD ---
$proveedores = [];
try {
    $stmt_prov = $pdo->query("SELECT id, nombre_empresa FROM proveedores ORDER BY nombre_empresa ASC");
    $proveedores = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos — <?php echo $nombre_empresa; ?></title>
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
        
        /* Lista de Autocompletado */
        .search-container { position: relative; }
        .sugerencias-box { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #cbd5e1; max-height: 200px; overflow-y: auto; z-index: 1050; display: none; border-radius: 0 0 0.75rem 0.75rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .sugerencia-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .sugerencia-item:hover { background-color: #f8fafc; }

        /* Campos bloqueados */
        .input-bloqueado {
            background-color: #f1f5f9 !important;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col selection:bg-blue-500 selection:text-white">

    <!-- Barra de Navegación Adaptable -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex flex-col sm:flex-row justify-between items-center gap-3 sticky top-0 z-50 shadow-xs">
        <div class="flex items-center space-x-3">
            <div class="h-5 w-px bg-slate-200 hidden sm:block"></div>
            <div class="flex items-center gap-2">
                <div class="bg-amber-600 text-white p-2 rounded-xl shadow-sm">
                    <i class="fa-solid fa-box text-sm"></i>
                </div>
                <div>
                    <span class="font-bold text-sm sm:text-base tracking-tight text-slate-900 block leading-none">Módulo de Productos</span>
                    <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="max-w-[1400px] w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 flex-grow flex flex-col">
        
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 flex-grow flex flex-col">
            
            <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 mb-6">
                <h4 class="font-bold text-slate-900 text-base sm:text-lg mb-0">Inventario de Productos</h4>
                <?php if ($rolActual === 'admin'): ?>
                    <div class="flex flex-wrap items-center gap-2">
                        <button onclick="abrirModalMercaderia()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs sm:text-sm px-4 py-2.5 rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                            <i class="fa-solid fa-truck-fast"></i> Agregar Mercadería (Lote POS)
                        </button>
                        <button onclick="abrirModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs sm:text-sm px-4 py-2.5 rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                            <i class="fa-solid fa-plus"></i> Nuevo Producto Individual
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Filtros de Búsqueda por Nombre y Fecha -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div>
                    <label class="block font-semibold text-xs text-slate-600 mb-1">🔍 Buscar por Nombre / Código:</label>
                    <input type="text" id="filtro_nombre" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" placeholder="Escribe para filtrar..." onkeyup="cargarProductos()">
                </div>
                <div>
                    <label class="block font-semibold text-xs text-slate-600 mb-1">📅 Desde (Fecha de Creación/Registro):</label>
                    <input type="date" id="filtro_fecha_desde" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" onchange="cargarProductos()">
                </div>
                <div>
                    <label class="block font-semibold text-xs text-slate-600 mb-1">📅 Hasta:</label>
                    <input type="date" id="filtro_fecha_hasta" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" onchange="cargarProductos()">
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 flex-grow">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-semibold tracking-wider">
                            <th class="py-3.5 px-4">Código de Barra</th>
                            <th class="py-3.5 px-4">Nombre</th>
                            <?php if ($rolActual === 'admin'): ?>
                                <th class="py-3.5 px-4">P. Compra</th>
                            <?php endif; ?>
                            <th class="py-3.5 px-4">P. Venta</th>
                            <th class="py-3.5 px-4">Stock</th>
                            <th class="py-3.5 px-4">F. Registro</th>
                            <?php if ($rolActual === 'admin'): ?>
                                <th class="py-3.5 px-4 text-center">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="tablaProductos" class="divide-y divide-slate-200 text-xs sm:text-sm text-slate-700 bg-white"></tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Producto Individual (z-[60]) -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-[60] flex items-center justify-center p-4" id="modalProducto" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h4 class="font-bold text-slate-900 text-base" id="modalTitulo">Nuevo Producto</h4>
                <button onclick="cerrarModal()" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded-lg transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6">
                <form id="formProducto" class="space-y-4">
                    <input type="hidden" id="prod_id">
                    <div>
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Código de Barra:</label>
                        <input type="text" id="prod_codigo_barra" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" required autocomplete="off">
                    </div>
                    <div>
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Nombre del Producto:</label>
                        <input type="text" id="prod_nombre" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" required>
                    </div>
                    <div>
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Proveedor:</label>
                        <div class="flex gap-2">
                            <select id="prod_proveedor_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition">
                                <option value="">Seleccione un proveedor...</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo $prov['id']; ?>"><?php echo htmlspecialchars($prov['nombre_empresa']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="abrirModalNuevoProveedor()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition shrink-0 flex items-center gap-1" title="Agregar nuevo proveedor">
                                <i class="fa-solid fa-plus"></i> Nuevo
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Precio Compra (L.):</label>
                            <input type="number" step="0.01" id="prod_precio_compra" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" required>
                        </div>
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Precio Venta (L.):</label>
                            <input type="number" step="0.01" id="prod_precio_venta" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" required>
                        </div>
                    </div>
                    <div>
                        <label id="lbl_prod_stock" class="block font-semibold text-xs text-slate-600 mb-1">Stock Inicial:</label>
                        <input type="number" id="prod_stock" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" required placeholder="0" min="1">
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-semibold rounded-xl transition" onclick="cerrarModal()">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-semibold rounded-xl transition shadow-sm">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Proveedor Express (z-[70]) -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-[70] flex items-center justify-center p-4" id="modalNuevoProveedor" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-sm overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h4 class="font-bold text-slate-900 text-base">Registrar Nuevo Proveedor</h4>
                <button onclick="cerrarModalNuevoProveedor()" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded-lg transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6">
                <form id="formNuevoProveedorQuick" class="space-y-4" onsubmit="guardarProveedorRapido(event)">
                    <div>
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Nombre de la Empresa:</label>
                        <input type="text" id="nuevo_prov_nombre" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" required autocomplete="off">
                    </div>
                    <div>
                        <label class="block font-semibold text-xs text-slate-600 mb-1">Teléfono (Opcional):</label>
                        <input type="text" id="nuevo_prov_telefono" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" autocomplete="off">
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-semibold rounded-xl transition" onclick="cerrarModalNuevoProveedor()">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl transition shadow-sm">Guardar y Seleccionar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Carga Masiva de Mercadería (z-50) -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4" id="modalMercaderia" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <div class="flex items-center gap-2">
                    <h4 class="font-bold text-slate-900 text-base">🚚 Ingreso Masivo de Mercadería</h4>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Escanea o busca productos</span>
                </div>
                <button onclick="cerrarModalMercaderia()" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded-lg transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 overflow-y-auto flex-grow flex flex-col gap-4">
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 bg-slate-50 p-4 border border-slate-200 rounded-xl">
                    <div class="md:col-span-5">
                        <label class="block font-semibold text-xs text-slate-600 mb-1">📷 Escanear Código de Barra:</label>
                        <input type="text" id="input_scan_lote" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" placeholder="Escanear y presionar Enter..." autocomplete="off">
                    </div>
                    <div class="md:col-span-7 search-container">
                        <label class="block font-semibold text-xs text-slate-600 mb-1">🔍 Buscar por Nombre (Opcional):</label>
                        <input type="text" id="input_buscar_nombre_lote" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition" placeholder="Escribe el nombre del producto..." autocomplete="off">
                        <div id="sugerencias_lote" class="sugerencias-box"></div>
                    </div>
                </div>
                
                <div class="overflow-x-auto rounded-xl border border-slate-200 flex-grow" style="max-height: 350px;">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-900 text-white text-[11px] font-semibold tracking-wider">
                                <th class="py-3 px-4" style="width: 20%;">Código de Barra</th>
                                <th class="py-3 px-4" style="width: 35%;">Nombre del Producto</th>
                                <th class="py-3 px-4" style="width: 15%;">P. Compra (L.)</th>
                                <th class="py-3 px-4" style="width: 15%;">P. Venta (L.)</th>
                                <th class="py-3 px-4 text-center" style="width: 10%;">Cantidad</th>
                                <th class="py-3 px-4 text-center" style="width: 5%;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tablaLoteMercaderia" class="divide-y divide-slate-200 text-xs sm:text-sm text-slate-700 bg-white">
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 pt-2">
                    <span id="lbl_total_items_lote" class="font-semibold text-xs text-slate-500">0 productos en la lista</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-semibold rounded-xl transition" onclick="cerrarModalMercaderia()">Cancelar (Esc)</button>
                        <button type="button" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold rounded-xl transition shadow-sm" onclick="guardarLoteMercaderia()">
                            💾 Guardar Lote de Mercadería
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL DE SEGURIDAD PARA ELIMINACIÓN DE PRODUCTO -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4" id="modalEliminarSeguridad" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-rose-50">
                <div class="flex items-center gap-2 text-rose-700 font-bold text-base">
                    <i class="fa-solid fa-shield-halved"></i> Confirmar Eliminación
                </div>
                <button onclick="cerrarModalSeguridad()" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded-lg transition"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <input type="hidden" id="eliminar_id_objetivo">
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-3 rounded-xl text-xs font-semibold flex items-start gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-base mt-0.5"></i>
                    <span>¡Atención! Esta acción eliminará el producto del sistema de forma permanente.</span>
                </div>
                <div>
                    <label class="block font-semibold text-xs text-slate-600 mb-1">Contraseña de Administrador:</label>
                    <input type="password" id="txtClaveAdmin" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm outline-none focus:ring-2 focus:ring-rose-600/20 focus:border-rose-600 transition" placeholder="Escribe tu contraseña..." autocomplete="new-password">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs sm:text-sm font-semibold rounded-xl transition" onclick="cerrarModalSeguridad()">Cancelar</button>
                    <button type="button" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs sm:text-sm font-semibold rounded-xl transition shadow-sm flex items-center gap-1.5" onclick="ejecutarEliminacionProducto()">
                        <i class="fa-solid fa-trash text-xs"></i> Confirmar y Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const esAdmin = "<?php echo $rolActual; ?>" === "admin";
        let esModoEdicionDirecta = false; 
        let esSumarStockExistente = false;  
        let vieneDesdeLote = false;

        let loteMercaderia = [];

        document.addEventListener('DOMContentLoaded', () => {
            const txtClaveInput = document.getElementById('txtClaveAdmin');
            if (txtClaveInput) {
                txtClaveInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.keyCode === 13) {
                        e.preventDefault();
                        ejecutarEliminacionProducto();
                    }
                });
            }

            cargarProductos();

            const inputScan = document.getElementById('input_scan_lote');
            if (inputScan) {
                inputScan.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const codigo = this.value.trim();
                        if (codigo !== '') {
                            procesarCodigoIngresado(codigo);
                            this.value = '';
                        }
                    }
                });
            }

            const inputBuscarNombre = document.getElementById('input_buscar_nombre_lote');
            if (inputBuscarNombre) {
                inputBuscarNombre.addEventListener('input', function() {
                    const q = this.value.trim();
                    const box = document.getElementById('sugerencias_lote');
                    if (q.length < 2) {
                        box.style.display = 'none';
                        return;
                    }

                    fetch(`../api/productos.php?accion=listar`)
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                const filtrados = res.data.filter(p => p.nombre.toLowerCase().includes(q.toLowerCase()) || p.codigo_barra.includes(q));
                                let html = '';
                                filtrados.forEach(p => {
                                    html += `<div class="sugerencia-item" onclick="seleccionarProductoParaLote(${p.id}, '${escapeHtml(p.codigo_barra)}', '${escapeHtml(p.nombre)}', ${p.precio_compra}, ${p.precio_venta})">
                                        <b>${escapeHtml(p.nombre)}</b> - <small>Código: ${p.codigo_barra} | Stock: ${p.stock}</small>
                                    </div>`;
                                });
                                box.innerHTML = html || '<div class="sugerencia-item text-slate-400">No se encontraron productos</div>';
                                box.style.display = 'block';
                            }
                        });
                });
            }

            document.getElementById('prod_codigo_barra').addEventListener('change', function() {
                const codigo = this.value.trim();
                if (esModoEdicionDirecta || codigo === '' || vieneDesdeLote) return;

                fetch(`../api/productos.php?accion=buscar_exacto&codigo=${encodeURIComponent(codigo)}`)
                    .then(res => res.json())
                    .then(res => {
                        if (res.success && res.data !== null) {
                            const p = res.data;
                            esSumarStockExistente = true;
                            document.getElementById('prod_id').value = p.id;
                            document.getElementById('prod_nombre').value = p.nombre;
                            document.getElementById('prod_proveedor_id').value = p.proveedor_id || '';
                            document.getElementById('prod_precio_compra').value = p.precio_compra;
                            document.getElementById('prod_precio_venta').value = p.precio_venta;

                            bloquearCamposInformacion(true);
                            document.getElementById('modalTitulo').innerText = 'Sumar Inventario';
                            document.getElementById('lbl_prod_stock').innerText = '➕ Cantidad a Sumar al Stock:';
                            document.getElementById('prod_stock').value = 1;
                            document.getElementById('prod_stock').focus();
                        } else {
                            esSumarStockExistente = false;
                            if (document.getElementById('prod_id').value === '') {
                                desbloquearFormularioNuevo();
                            }
                        }
                    });
            });
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                const box = document.getElementById('sugerencias_lote');
                if (box) box.style.display = 'none';
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.key === 'Esc') {
                const modalInd = document.getElementById('modalProducto');
                const modalLote = document.getElementById('modalMercaderia');
                const modalSeg = document.getElementById('modalEliminarSeguridad');
                const modalProv = document.getElementById('modalNuevoProveedor');

                if (modalProv && modalProv.style.display === 'flex') {
                    cerrarModalNuevoProveedor();
                } else if (modalInd && modalInd.style.display === 'flex') {
                    cerrarModal();
                } else if (modalLote && modalLote.style.display === 'flex') {
                    cerrarModalMercaderia();
                } else if (modalSeg && modalSeg.style.display === 'flex') {
                    cerrarModalSeguridad();
                }
            }
        });

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // --- FUNCIONES PARA PROVEEDORES EXPRESS ---
        function abrirModalNuevoProveedor() {
            document.getElementById('formNuevoProveedorQuick').reset();
            document.getElementById('modalNuevoProveedor').style.display = 'flex';
            document.getElementById('nuevo_prov_nombre').focus();
        }

        function cerrarModalNuevoProveedor() {
            document.getElementById('modalNuevoProveedor').style.display = 'none';
        }

        function guardarProveedorRapido(e) {
            e.preventDefault();
            const nombre = document.getElementById('nuevo_prov_nombre').value.trim();
            const telefono = document.getElementById('nuevo_prov_telefono').value.trim();

            if (!nombre) return;

            const formData = new FormData();
            formData.append('accion', 'guardar_proveedor'); // 👈 Apunta a la acción corregida en el API
            formData.append('nombre_empresa', nombre);     // 👈 Coincide con la columna de tu base de datos
            formData.append('telefono', telefono);

            // 👈 Apunta correctamente a tu API de productos centralizada
            fetch('../api/productos.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('prod_proveedor_id');
                    const nuevaOpcion = document.createElement('option');
                    nuevaOpcion.value = data.id;
                    nuevaOpcion.text = data.nombre_empresa;
                    select.appendChild(nuevaOpcion);
                    select.value = data.id; // Autoseleccionar el nuevo proveedor creado

                    cerrarModalNuevoProveedor();
                } else {
                    alert(data.message || 'Error al guardar el proveedor.');
                }
            })
            .catch(() => {
                alert('Error de conexión al guardar el proveedor.');
            });
        }

        function cargarProductos() {
            const filtroNombre = document.getElementById('filtro_nombre').value.toLowerCase();
            const filtroDesde = document.getElementById('filtro_fecha_desde').value;
            const filtroHasta = document.getElementById('filtro_fecha_hasta').value;

            fetch('../api/productos.php?accion=listar')
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    let html = '';
                    
                    const datosFiltrados = res.data.filter(p => {
                        const fechaProd = p.fecha_creacion ? p.fecha_creacion.split(' ')[0] : '';
                        const coincideNombre = p.nombre.toLowerCase().includes(filtroNombre) || p.codigo_barra.toLowerCase().includes(filtroNombre);
                        
                        let coincideFecha = true;
                        if (filtroDesde && fechaProd < filtroDesde) coincideFecha = false;
                        if (filtroHasta && fechaProd > filtroHasta) coincideFecha = false;

                        return coincideNombre && coincideFecha;
                    });

                    datosFiltrados.forEach(p => {
                        html += `
                            <tr class="hover:bg-slate-50/80 transition border-b border-slate-100 last:border-none">
                                <td class="py-3 px-4 font-semibold text-slate-900">${p.codigo_barra}</td>
                                <td class="py-3 px-4 font-medium text-slate-900">${p.nombre}</td>
                                ${esAdmin ? `<td class="py-3 px-4 text-slate-600">L. ${parseFloat(p.precio_compra).toFixed(2)}</td>` : ''}
                                <td class="py-3 px-4 text-slate-600">L. ${parseFloat(p.precio_venta).toFixed(2)}</td>
                                <td class="py-3 px-4"><span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-cyan-50 text-cyan-700 border border-cyan-200">${p.stock} un.</span></td>
                                <td class="py-3 px-4 text-slate-500 text-xs">${p.fecha_creacion ?? 'N/D'}</td>
                                ${esAdmin ? `
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button class="bg-amber-50 hover:bg-amber-100 text-amber-700 font-semibold text-xs px-2.5 py-1.5 rounded-xl transition flex items-center gap-1" onclick="editarProducto(${p.id})"><i class="fa-solid fa-pen text-[10px]"></i> Editar</button>
                                            <button class="bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold text-xs px-2.5 py-1.5 rounded-xl transition flex items-center gap-1" onclick="eliminarProducto(${p.id})"><i class="fa-solid fa-trash text-[10px]"></i> Eliminar</button>
                                        </div>
                                    </td>
                                ` : ''}
                            </tr>
                        `;
                    });
                    const columnas = esAdmin ? 7 : 5;
                    document.getElementById('tablaProductos').innerHTML = html || `<tr><td colspan="${columnas}" class="text-center text-slate-400 py-6 text-sm">No se encontraron productos con los filtros aplicados</td></tr>`;
                }
            });
        }

        function abrirModalMercaderia() {
            loteMercaderia = [];
            renderizarTablaLote();
            document.getElementById('modalMercaderia').style.display = 'flex';
            document.getElementById('input_scan_lote').focus();
        }

        function cerrarModalMercaderia() {
            document.getElementById('modalMercaderia').style.display = 'none';
        }

        function procesarCodigoIngresado(codigo) {
            const indexLote = loteMercaderia.findIndex(item => item.codigo_barra === codigo);
            if (indexLote !== -1) {
                loteMercaderia[indexLote].cantidad++;
                renderizarTablaLote();
                document.getElementById('input_scan_lote').focus();
                return;
            }

            fetch(`../api/productos.php?accion=buscar_exacto&codigo=${encodeURIComponent(codigo)}`)
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.data !== null) {
                        const p = res.data;
                        agregarItemALote(p.id, p.codigo_barra, p.nombre, p.precio_compra, p.precio_venta, 1);
                        document.getElementById('input_scan_lote').focus();
                    } else {
                        setTimeout(() => {
                            const deseaCrear = confirm(`El código "${codigo}" no existe en el sistema.\n\n¿Deseas registrar este nuevo producto para agregarlo al lote?`);
                            if (deseaCrear) {
                                vieneDesdeLote = true;
                                abrirModalNuevoConCodigo(codigo);
                            } else {
                                document.getElementById('input_scan_lote').focus();
                            }
                        }, 100);
                    }
                })
                .catch(err => console.error("Error al buscar producto:", err));
        }

        function seleccionarProductoParaLote(id, codigo, nombre, precio_compra, precio_venta) {
            document.getElementById('sugerencias_lote').style.display = 'none';
            document.getElementById('input_buscar_nombre_lote').value = '';
            
            const indexLote = loteMercaderia.findIndex(item => item.codigo_barra === codigo);
            if (indexLote !== -1) {
                loteMercaderia[indexLote].cantidad++;
            } else {
                agregarItemALote(id, codigo, nombre, precio_compra, precio_venta, 1);
            }
            renderizarTablaLote();
            document.getElementById('input_scan_lote').focus();
        }

        function agregarItemALote(id, codigo, nombre, precio_compra, precio_venta, cantidad) {
            loteMercaderia.push({
                id: id,
                codigo_barra: codigo,
                nombre: nombre,
                precio_compra: parseFloat(precio_compra),
                precio_venta: parseFloat(precio_venta),
                cantidad: parseInt(cantidad)
            });
            renderizarTablaLote();
        }

        function renderizarTablaLote() {
            const tbody = document.getElementById('tablaLoteMercaderia');
            if (!tbody) return;

            if (loteMercaderia.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-slate-400 py-6 text-sm">Escanea o busca productos para agregarlos al lote</td></tr>';
                document.getElementById('lbl_total_items_lote').innerText = '0 productos en la lista';
                return;
            }

            let html = '';
            let totalUnidades = 0;

            loteMercaderia.forEach((item, index) => {
                totalUnidades += item.cantidad;
                html += `
                    <tr class="border-b border-slate-100 last:border-none">
                        <td class="py-3 px-4 font-semibold text-slate-900">${escapeHtml(item.codigo_barra)}</td>
                        <td class="py-3 px-4 font-medium text-slate-900">${escapeHtml(item.nombre)}</td>
                        <td class="py-3 px-4">
                            <input type="number" step="0.01" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs input-bloqueado" value="${item.precio_compra.toFixed(2)}" readonly tabindex="-1">
                        </td>
                        <td class="py-3 px-4">
                            <input type="number" step="0.01" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs input-bloqueado" value="${item.precio_venta.toFixed(2)}" readonly tabindex="-1">
                        </td>
                        <td class="py-3 px-4 text-center">
                            <input type="number" class="w-20 mx-auto px-2 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold text-center input-bloqueado" value="${item.cantidad}" readonly tabindex="-1">
                        </td>
                        <td class="py-3 px-4 text-center">
                            <button class="bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold text-xs px-2.5 py-1.5 rounded-xl transition" onclick="quitarDelLote(${index})" title="Eliminar del lote">🗑️</button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            document.getElementById('lbl_total_items_lote').innerText = `${loteMercaderia.length} partidas (${totalUnidades} unidades en total)`;
        }

        function quitarDelLote(index) {
            loteMercaderia.splice(index, 1);
            renderizarTablaLote();
            document.getElementById('input_scan_lote').focus();
        }

        function guardarLoteMercaderia() {
            if (loteMercaderia.length === 0) {
                alert('No hay productos en la lista para guardar.');
                return;
            }

            if (!confirm(`¿Deseas procesar el ingreso de estas ${loteMercaderia.length} partidas al inventario?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'guardar_lote');
            formData.append('productos', JSON.stringify(loteMercaderia));

            fetch('../api/productos.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    cerrarModalMercaderia();
                    cargarProductos();
                } else {
                    alert(data.message);
                }
            });
        }

        function abrirModalNuevoConCodigo(codigo) {
            esModoEdicionDirecta = false;
            esSumarStockExistente = false;
            document.getElementById('formProducto').reset();
            document.getElementById('prod_id').value = '';
            desbloquearFormularioNuevo();
            
            document.getElementById('prod_codigo_barra').value = codigo;

            if (vieneDesdeLote) {
                const inputStock = document.getElementById('prod_stock');
                inputStock.value = 1;
                inputStock.readOnly = true;
                inputStock.classList.add('input-bloqueado');
                document.getElementById('lbl_prod_stock').innerText = 'Stock Inicial (Fijo para lote):';
            }

            document.getElementById('modalProducto').style.display = 'flex';
            document.getElementById('prod_nombre').focus();
        }

        function abrirModal() {
            vieneDesdeLote = false;
            esModoEdicionDirecta = false;
            esSumarStockExistente = false;
            document.getElementById('formProducto').reset();
            document.getElementById('prod_id').value = '';
            desbloquearFormularioNuevo();
            
            const inputStock = document.getElementById('prod_stock');
            inputStock.readOnly = false;
            inputStock.classList.remove('input-bloqueado');

            document.getElementById('modalProducto').style.display = 'flex';
            document.getElementById('prod_codigo_barra').focus();
        }

        function cerrarModal() {
            document.getElementById('modalProducto').style.display = 'none';
            vieneDesdeLote = false;
            
            const scan = document.getElementById('input_scan_lote');
            if (scan) scan.focus();
        }

        function desbloquearFormularioNuevo() {
            document.getElementById('modalTitulo').innerText = 'Nuevo Producto';
            document.getElementById('lbl_prod_stock').innerText = 'Stock Inicial:';
            document.getElementById('prod_stock').placeholder = '0';
            bloquearCamposInformacion(false);
        }

        function bloquearCamposInformacion(bloquear) {
            document.getElementById('prod_nombre').readOnly = bloquear;
            document.getElementById('prod_proveedor_id').disabled = bloquear;
            document.getElementById('prod_precio_compra').readOnly = bloquear;
            document.getElementById('prod_precio_venta').readOnly = bloquear;
        }

        function editarProducto(id) {
            vieneDesdeLote = false;
            esModoEdicionDirecta = true;
            esSumarStockExistente = false;
            fetch(`../api/productos.php?accion=obtener&id=${id}`)
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    const p = res.data;
                    document.getElementById('prod_id').value = p.id;
                    document.getElementById('prod_codigo_barra').value = p.codigo_barra;
                    document.getElementById('prod_nombre').value = p.nombre;
                    document.getElementById('prod_proveedor_id').value = p.proveedor_id || '';
                    document.getElementById('prod_precio_compra').value = p.precio_compra;
                    document.getElementById('prod_precio_venta').value = p.precio_venta;
                    
                    const inputStock = document.getElementById('prod_stock');
                    inputStock.value = p.stock;
                    inputStock.readOnly = false;
                    inputStock.classList.remove('input-bloqueado');
                    
                    bloquearCamposInformacion(false);
                    document.getElementById('modalTitulo').innerText = 'Editar Producto';
                    document.getElementById('lbl_prod_stock').innerText = 'Stock Actual:';
                    document.getElementById('prod_stock').placeholder = '';

                    document.getElementById('modalProducto').style.display = 'flex';
                } else {
                    alert(res.message);
                }
            });
        }

        document.getElementById('formProducto').addEventListener('submit', function(e) {
            e.preventDefault();
            const codigo = document.getElementById('prod_codigo_barra').value.trim();
            const nombre = document.getElementById('prod_nombre').value.trim();
            const proveedorId = document.getElementById('prod_proveedor_id').value;
            const precioC = parseFloat(document.getElementById('prod_precio_compra').value);
            const precioV = parseFloat(document.getElementById('prod_precio_venta').value);
            const cantidad = parseInt(document.getElementById('prod_stock').value);

            if (vieneDesdeLote) {
                const yaExisteEnLote = loteMercaderia.some(item => item.codigo_barra === codigo);
                if (yaExisteEnLote) {
                    alert('El producto con este código de barra ya fue agregado al lote.');
                    return;
                }

                agregarItemALote(0, codigo, nombre, precioC, precioV, 1);

                document.getElementById('modalProducto').style.display = 'none';
                vieneDesdeLote = false;
                
                const scan = document.getElementById('input_scan_lote');
                if (scan) scan.focus();
                return;
            }

            if (esSumarStockExistente) {
                const seguro = confirm(`¿Estás seguro de agregar ${cantidad} unidad(es) al stock del producto "${nombre}"?`);
                if (!seguro) return;
            }

            const formData = new FormData();
            formData.append('accion', 'guardar');
            formData.append('id', document.getElementById('prod_id').value);
            formData.append('codigo_barra', codigo);
            formData.append('nombre', nombre);
            formData.append('proveedor_id', proveedorId);
            formData.append('precio_compra', precioC);
            formData.append('precio_venta', precioV);
            formData.append('stock', cantidad);

            fetch('../api/productos.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    cerrarModal();
                    cargarProductos();
                } else {
                    alert(data.message);
                }
            });
        });

        function eliminarProducto(id) {
            document.getElementById('eliminar_id_objetivo').value = id;
            document.getElementById('txtClaveAdmin').value = '';
            document.getElementById('modalEliminarSeguridad').style.display = 'flex';
            setTimeout(() => {
                document.getElementById('txtClaveAdmin').value = '';
                document.getElementById('txtClaveAdmin').focus();
            }, 100);
        }

        function cerrarModalSeguridad() {
            document.getElementById('modalEliminarSeguridad').style.display = 'none';
        }

        async function ejecutarEliminacionProducto() {
            const id = document.getElementById('eliminar_id_objetivo').value;
            const clave = document.getElementById('txtClaveAdmin').value.trim();

            if (!clave) {
                alert('Por favor ingresa tu contraseña de administrador.');
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('id', id);
            formData.append('clave_admin', clave);

            try {
                const response = await fetch('../api/productos.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    cerrarModalSeguridad();
                    cargarProductos();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                alert('Error al intentar eliminar el producto.');
            }
        }
    </script>
</body>
</html>