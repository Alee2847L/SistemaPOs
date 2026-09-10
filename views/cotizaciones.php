<?php
// views/cotizaciones.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$rolActual = $_SESSION['usuario_rol'] ?? 'vendedor';
$es_admin = (isset($_SESSION['usuario_rol']) && (strtolower($_SESSION['usuario_rol']) === 'admin' || strtolower($_SESSION['usuario_rol']) === 'administrador'));

// --- OBTENER EL NOMBRE DE LA EMPRESA DESDE LA BD ---
$nombre_empresa = "INVERSIONES J.A."; 
try {
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
    }
} catch (Exception $e) {
    // Valor por defecto si la tabla no existe
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cotizaciones y Órdenes — <?php echo $nombre_empresa; ?></title>
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
            <div class="bg-blue-600 text-white p-2 rounded-xl shadow-sm"><i class="fa-solid fa-file-invoice-dollar text-sm"></i></div>
            <div>
                <span class="font-bold text-sm sm:text-base text-slate-900 block leading-none">Módulo de Cotizaciones y Órdenes</span>
                <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
            </div>
        </div>
        <div>
            <a href="clientes.php" class="text-xs font-semibold text-blue-600 hover:text-blue-800 bg-blue-50 px-3 py-2 rounded-xl transition">
                <i class="fa-solid fa-users me-1"></i> Ir a Clientes
            </a>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="max-w-[1400px] w-full mx-auto px-4 sm:px-6 py-6 flex-grow flex flex-col">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 flex-grow flex flex-col">
            
            <!-- Barra de Herramientas -->
            <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 mb-6">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"><i class="fa-solid fa-magnifying-glass text-xs"></i></span>
                    <input type="text" id="inputBuscar" placeholder="Buscar por No. Cotización, Cliente o Proyecto..." onkeyup="filtrarCotizaciones()" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition">
                </div>
                <button onclick="abrirModalNuevaCotizacion()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm px-4 py-2.5 rounded-xl transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus"></i> Nueva Cotización
                </button>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto rounded-xl border border-slate-200 flex-grow">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-semibold">
                        <tr>
                            <th class="py-3.5 px-4">No. Cotización</th>
                            <th class="py-3.5 px-4">Fecha</th>
                            <th class="py-3.5 px-4">Cliente</th>
                            <th class="py-3.5 px-4">Proyecto</th>
                            <th class="py-3.5 px-4">Clasificación</th>
                            <th class="py-3.5 px-4">Total General</th>
                            <th class="py-3.5 px-4">Estado</th>
                            <th class="py-3.5 px-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaCotizaciones" class="divide-y divide-slate-200 text-sm text-slate-700 bg-white"></tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- MODAL FORMULARIO DE COTIZACIÓN -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto" id="modalCotizacion" style="display: none;">
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-6xl overflow-hidden flex flex-col my-8">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h4 class="font-bold text-slate-900 text-base" id="modalTitulo">Nueva Cotización de Proyecto</h4>
                <button type="button" onclick="cerrarModalCotizacion()" class="text-slate-400 hover:text-slate-600 p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                <form id="formCotizacion" class="space-y-6">
                    <input type="hidden" id="cot_id" value="">

                    <!-- Datos Generales -->
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Fecha:</label>
                            <input type="date" id="cot_fecha" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Nombre del Cliente:</label>
                            <input type="text" id="cot_cliente_nombre" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" placeholder="Ej. Inversiones Osorio" required>
                        </div>
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">RTN / DNI:</label>
                            <input type="text" id="cot_cliente_rtn" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" placeholder="0318...">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Nombre del Proyecto:</label>
                            <input type="text" id="cot_proyecto_nombre" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" placeholder="Ej. Cielo Falso PVC / Eléctrica" required>
                        </div>
                        <div>
                            <label class="block font-semibold text-xs text-slate-600 mb-1">Clasificación:</label>
                            <input type="text" id="cot_clasificacion" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" placeholder="Ej. Obra Blanca">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold text-xs text-slate-600 mb-1">Ancho (M2):</label>
                                <input type="number" step="0.01" id="cot_ancho" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" value="0">
                            </div>
                            <div>
                                <label class="block font-semibold text-xs text-slate-600 mb-1">Long. (M2):</label>
                                <input type="number" step="0.01" id="cot_longitud" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" value="0">
                            </div>
                        </div>
                    </div>

                    <hr class="border-slate-100">

                    <!-- Desglose de Ítems -->
                    <div>
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-3">
                            <h5 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-list-check me-1 text-blue-600"></i> Desglose de Materiales y Mano de Obra</h5>
                            <div class="flex gap-2">
                                <button type="button" onclick="agregarFilaDetalle('MATERIAL')" class="bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-semibold px-3 py-2 rounded-xl transition">
                                    <i class="fa-solid fa-box me-1"></i> + Material
                                </button>
                                <button type="button" onclick="agregarFilaDetalle('MANO_OBRA')" class="bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-semibold px-3 py-2 rounded-xl transition">
                                    <i class="fa-solid fa-hard-hat me-1"></i> + Mano de Obra
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                                    <tr>
                                        <th class="py-2.5 px-3" style="width: 10%;">Tipo</th>
                                        <th class="py-2.5 px-3" style="width: 25%;">Descripción</th>
                                        <th class="py-2.5 px-3" style="width: 20%;">Proveedor (Material)</th>
                                        <th class="py-2.5 px-3" style="width: 10%;">Unidad</th>
                                        <th class="py-2.5 px-3" style="width: 10%;">Cantidad</th>
                                        <th class="py-2.5 px-3" style="width: 10%;">Costo Unit.</th>
                                        <th class="py-2.5 px-3" style="width: 8%;">Margen %</th>
                                        <th class="py-2.5 px-3" style="width: 10%;">Total Margen</th>
                                        <th class="py-2.5 px-3 text-center" style="width: 5%;">X</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaDetalles" class="divide-y divide-slate-100 bg-white">
                                    <!-- Dinámico -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pie de Totales -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex justify-end items-center">
                        <div class="text-right">
                            <p class="text-xs text-slate-500 font-medium">Subtotal Sin Margen: <span class="font-bold text-slate-700" id="lblSubtotal">L. 0.00</span></p>
                            <p class="text-base font-extrabold text-slate-900 mt-0.5">Total General: <span class="text-blue-600" id="lblTotalGeneral">L. 0.00</span></p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" class="px-4 py-2 bg-slate-100 text-slate-700 font-semibold rounded-xl text-sm" onclick="cerrarModalCotizacion()">Cancelar</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm transition">Guardar Cotización y Órdenes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL SEGURIDAD ELIMINAR -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4" id="modalEliminarSeguridadCot" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-rose-50">
                <h4 class="font-bold text-rose-900 text-base flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved"></i> Confirmar Eliminación
                </h4>
                <button type="button" onclick="cerrarModalEliminarCot()" class="text-slate-400 hover:text-slate-600 p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div class="p-3 bg-rose-50 border border-rose-100 rounded-xl">
                    <p class="text-rose-700 font-semibold text-sm">⚠ ¡Atención! Esta acción eliminará la cotización seleccionada.</p>
                </div>
                <input type="hidden" id="eliminar_cot_id">
                <div>
                    <label class="block font-semibold text-xs text-slate-600 mb-1">Contraseña de Administrador:</label>
                    <input type="password" id="txtClaveAdminCot" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm" placeholder="Escribe tu contraseña...">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" class="px-4 py-2 bg-slate-100 text-slate-700 font-semibold rounded-xl text-sm" onclick="cerrarModalEliminarCot()">Cancelar</button>
                    <button type="button" class="px-4 py-2 bg-rose-600 text-white font-semibold rounded-xl text-sm" onclick="ejecutarEliminacionCotizacion()">Confirmar y Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const esAdmin = <?php echo $es_admin ? 'true' : 'false'; ?>;
        let listaCotizacionesOriginal = [];
        let proveedoresGlobal = [];

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('txtClaveAdminCot').addEventListener('keydown', (e) => { if(e.key === 'Enter') ejecutarEliminacionCotizacion(); });
            cargarCotizaciones();
            cargarProveedores();
        });

        function cargarCotizaciones() {
            fetch('../api/cotizaciones.php?accion=listar')
                .then(res => res.json())
                .then(res => {
                    if(res.success) {
                        listaCotizacionesOriginal = res.data;
                        renderizarTablaCotizaciones(res.data);
                    }
                });
        }

        function cargarProveedores() {
            return fetch('../api/cotizaciones.php?accion=listar_proveedores')
                .then(res => {
                    // Validar si la respuesta es texto plano o HTML con error en vez de JSON
                    const contentType = res.headers.get("content-type");
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return res.json();
                    } else {
                        throw new Error("La respuesta del servidor no es un JSON válido.");
                    }
                })
                .then(res => {
                    if (res && res.success) {
                        proveedoresGlobal = res.data;
                    } else {
                        proveedoresGlobal = [];
                    }
                })
                .catch(err => {
                    console.warn('Aviso al cargar proveedores:', err);
                    proveedoresGlobal = []; // Evita que se caiga la promesa
                });
        }
        function renderizarTablaCotizaciones(cotizaciones) {
            let html = cotizaciones.length === 0 ? '<tr><td colspan="8" class="text-center py-6 text-slate-400">No hay cotizaciones registradas</td></tr>' : '';
            cotizaciones.forEach(c => {
                html += `<tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="py-3 px-4 font-semibold text-slate-900">#${c.numero_cotizacion}</td>
                    <td class="py-3 px-4">${c.fecha_cotizacion}</td>
                    <td class="py-3 px-4">${c.cliente_nombre}</td>
                    <td class="py-3 px-4 font-medium">${c.proyecto_nombre}</td>
                    <td class="py-3 px-4"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">${c.clasificacion_proyecto || 'General'}</span></td>
                    <td class="py-3 px-4 font-bold text-blue-600">L. ${Number(c.total_general || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="py-3 px-4"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">${c.estado}</span></td>
                    <td class="py-3 px-4 text-center">
                        <div class="flex justify-center gap-1">
                            <!-- AQUÍ ES DONDE SE AGREGA EL BOTÓN DE PDF / IMPRESIÓN -->
                            <a href="ver_cotizacion.php?id=${c.id}" target="_blank" class="bg-blue-50 text-blue-700 text-xs px-2.5 py-1.5 rounded-lg font-medium cursor-pointer hover:bg-blue-100 transition" title="Ver Documento e Imprimir"><i class="fa-solid fa-file-pdf"></i></a>
                            
                            <button type="button" class="bg-slate-50 text-slate-700 text-xs px-2.5 py-1.5 rounded-lg font-medium cursor-pointer hover:bg-slate-100 transition" onclick="verCotizacion(${c.id})" title="Editar"><i class="fa-solid fa-eye"></i></button>
                            
                            ${esAdmin ? `<button type="button" class="bg-rose-50 text-rose-700 text-xs px-2.5 py-1.5 rounded-lg font-medium cursor-pointer hover:bg-rose-100 transition" onclick="eliminarCotizacion(${c.id})" title="Eliminar"><i class="fa-solid fa-trash"></i></button>` : ''}
                        </div>
                    </td>
                </tr>`;
            });
            document.getElementById('tablaCotizaciones').innerHTML = html;
        }

        async function abrirModalNuevaCotizacion() {
            await cargarProveedores();
            document.getElementById('modalTitulo').innerText = 'Nueva Cotización de Proyecto';
            document.getElementById('cot_id').value = '';
            document.getElementById('formCotizacion').reset();
            document.getElementById('cot_fecha').valueAsDate = new Date();
            document.getElementById('tablaDetalles').innerHTML = '';
            agregarFilaDetalle('MATERIAL');
            document.getElementById('modalCotizacion').style.display = 'flex';
        }

        function cerrarModalCotizacion() {
            document.getElementById('modalCotizacion').style.display = 'none';
        }

        function cerrarModalEliminarCot() {
            document.getElementById('modalEliminarSeguridadCot').style.display = 'none';
        }

        function agregarFilaDetalle(tipo, item = null) {
            const tbody = document.getElementById('tablaDetalles');
            const row = document.createElement('tr');

            const isMaterial = tipo === 'MATERIAL';
            const badgeClass = isMaterial ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700';
            const descripcion = item ? item.descripcion : '';
            const unidad = item ? item.unidad : (isMaterial ? 'Und' : 'Glb');
            const cantidad = item ? item.cantidad : 1;
            const costo = item ? item.costo_unitario : 0.00;
            const margen = item ? item.margen_porcentaje : 20.00;
            const proveedorActualId = item ? item.proveedor_id : '';

            let opcionesProveedores = '<option value="">Seleccione proveedor...</option>';
            proveedoresGlobal.forEach(p => {
                const selected = (String(p.id) === String(proveedorActualId)) ? 'selected' : '';
                opcionesProveedores += `<option value="${p.id}" ${selected}>${p.nombre_empresa}</option>`;
            });

            const proveedorHtml = isMaterial 
                ? `<select class="w-full px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs proveedor-id" required>${opcionesProveedores}</select>`
                : `<span class="text-slate-400 text-[10px] italic">N/A (Mano Obra)</span><input type="hidden" class="proveedor-id" value="0">`;

            row.innerHTML = `
                <td class="py-2.5 px-3">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${badgeClass}">${tipo}</span>
                    <input type="hidden" class="tipo_item" value="${tipo}">
                </td>
                <td class="py-2.5 px-3"><input type="text" class="w-full px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs descripcion" value="${descripcion}" placeholder="Descripción" required></td>
                <td class="py-2.5 px-3">${proveedorHtml}</td>
                <td class="py-2.5 px-3"><input type="text" class="w-full px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs unidad" value="${unidad}" required></td>
                <td class="py-2.5 px-3"><input type="number" step="0.0001" class="w-full px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs cantidad" value="${cantidad}" oninput="calcularTotalesModal()"></td>
                <td class="py-2.5 px-3"><input type="number" step="0.01" class="w-full px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs costo" value="${costo}" oninput="calcularTotalesModal()"></td>
                <td class="py-2.5 px-3"><input type="number" step="0.01" class="w-full px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs margen" value="${margen}" oninput="calcularTotalesModal()"></td>
                <td class="py-2.5 px-3 font-bold text-slate-900 total-linea">0.00</td>
                <td class="py-2.5 px-3 text-center"><button type="button" class="text-rose-500 hover:text-rose-700 p-1" onclick="this.closest('tr').remove(); calcularTotalesModal();"><i class="fa-solid fa-xmark"></i></button></td>
            `;
            tbody.appendChild(row);
            calcularTotalesModal();
        }

        function calcularTotalesModal() {
            const filas = document.querySelectorAll('#tablaDetalles tr');
            let subtotalGeneral = 0;
            let totalGeneral = 0;

            filas.forEach(fila => {
                const cantidad = parseFloat(fila.querySelector('.cantidad').value) || 0;
                const costo = parseFloat(fila.querySelector('.costo').value) || 0;
                const margen = parseFloat(fila.querySelector('.margen').value) || 0;

                const subtotalLinea = cantidad * costo;
                const totalLineaMargen = subtotalLinea * (1 + (margen / 100));

                fila.querySelector('.total-linea').textContent = 'L. ' + totalLineaMargen.toFixed(2);

                subtotalGeneral += subtotalLinea;
                totalGeneral += totalLineaMargen;
            });

            document.getElementById('lblSubtotal').textContent = 'L. ' + subtotalGeneral.toFixed(2);
            document.getElementById('lblTotalGeneral').textContent = 'L. ' + totalGeneral.toFixed(2);
        }

        document.getElementById('formCotizacion').addEventListener('submit', async function(e) {
            e.preventDefault();
            const filas = document.querySelectorAll('#tablaDetalles tr');
            if (filas.length === 0) {
                alert('Debe agregar al menos un concepto a la cotización.');
                return;
            }

            let detalles = [];
            let errorProveedor = false;

            filas.forEach(fila => {
                const tipoItem = fila.querySelector('.tipo_item').value;
                const proveedorId = fila.querySelector('.proveedor-id').value;

                if (tipoItem === 'MATERIAL' && (!proveedorId || proveedorId === '')) {
                    errorProveedor = true;
                }

                detalles.push({
                    tipo_item: tipoItem,
                    descripcion: fila.querySelector('.descripcion').value,
                    proveedor_id: proveedorId,
                    unidad: fila.querySelector('.unidad').value,
                    cantidad: parseFloat(fila.querySelector('.cantidad').value),
                    costo_unitario: parseFloat(fila.querySelector('.costo').value),
                    margen_porcentaje: parseFloat(fila.querySelector('.margen').value)
                });
            });

            if (errorProveedor) {
                alert('Todos los materiales deben tener un proveedor seleccionado.');
                return;
            }

            const data = {
                fecha_cotizacion: document.getElementById('cot_fecha').value,
                cliente_nombre: document.getElementById('cot_cliente_nombre').value,
                cliente_rtn: document.getElementById('cot_cliente_rtn').value,
                proyecto_nombre: document.getElementById('cot_proyecto_nombre').value,
                clasificacion_proyecto: document.getElementById('cot_clasificacion').value,
                ancho: parseFloat(document.getElementById('cot_ancho').value) || 0,
                longitud: parseFloat(document.getElementById('cot_longitud').value) || 0,
                subtotal_general: parseFloat(document.getElementById('lblSubtotal').textContent.replace('L. ', '')),
                total_general: parseFloat(document.getElementById('lblTotalGeneral').textContent.replace('L. ', '')),
                detalles: detalles
            };

            try {
                const res = await fetch('../api/cotizaciones.php?accion=guardar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                }).then(r => r.json());

                if (res.success) {
                    alert(res.message);
                    cerrarModalCotizacion();
                    cargarCotizaciones();
                } else {
                    alert(res.message || 'Error al guardar');
                }
            } catch (err) {
                console.error(err);
                alert('Error de conexión con el servidor.');
            }
        });

        async function verCotizacion(id) {
            await cargarProveedores();
            fetch(`../api/cotizaciones.php?accion=obtener&id=${id}`)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        const c = res.cotizacion;
                        document.getElementById('modalTitulo').innerText = `Cotización #${c.numero_cotizacion}`;
                        document.getElementById('cot_id').value = c.id;
                        document.getElementById('cot_fecha').value = c.fecha_cotizacion;
                        document.getElementById('cot_cliente_nombre').value = c.cliente_nombre;
                        document.getElementById('cot_cliente_rtn').value = c.cliente_rtn || '';
                        document.getElementById('cot_proyecto_nombre').value = c.proyecto_nombre;
                        document.getElementById('cot_clasificacion').value = c.clasificacion_proyecto || '';
                        document.getElementById('cot_ancho').value = c.ancho || 0;
                        document.getElementById('cot_longitud').value = c.longitud || 0;

                        document.getElementById('tablaDetalles').innerHTML = '';
                        res.detalles.forEach(det => {
                            agregarFilaDetalle(det.tipo_item, det);
                        });

                        document.getElementById('modalCotizacion').style.display = 'flex';
                    }
                });
        }

        function eliminarCotizacion(id) {
            document.getElementById('eliminar_cot_id').value = id;
            document.getElementById('txtClaveAdminCot').value = '';
            document.getElementById('modalEliminarSeguridadCot').style.display = 'flex';
        }

        async function ejecutarEliminacionCotizacion() {
            const formData = new FormData();
            formData.append('accion', 'eliminar');
            formData.append('id', document.getElementById('eliminar_cot_id').value);
            formData.append('clave_admin', document.getElementById('txtClaveAdminCot').value);

            const res = await fetch('../api/cotizaciones.php', { method: 'POST', body: formData }).then(r => r.json());
            if (res.success) {
                alert(res.message);
                cerrarModalEliminarCot();
                cargarCotizaciones();
            } else {
                alert(res.message || 'Contraseña incorrecta o error al eliminar');
            }
        }

        function filtrarCotizaciones() {
            let texto = document.getElementById('inputBuscar').value.toLowerCase();
            let filtrados = listaCotizacionesOriginal.filter(c => 
                String(c.numero_cotizacion).toLowerCase().includes(texto) || 
                c.cliente_nombre.toLowerCase().includes(texto) || 
                c.proyecto_nombre.toLowerCase().includes(texto)
            );
            renderizarTablaCotizaciones(filtrados);
        }
    </script>
</body>
</html>