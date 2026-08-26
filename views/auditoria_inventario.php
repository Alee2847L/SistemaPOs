<?php
// views/auditoria_inventario.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rolActual = strtolower($_SESSION['usuario_rol'] ?? 'vendedor');
$esAdmin = ($rolActual === 'admin' || $rolActual === 'administrador');

if (!$esAdmin) {
    echo "<div style='padding: 20px; font-family: sans-serif; color: red;'>
            <h3>Acceso Denegado</h3>
            <p>No tienes permisos suficientes para ver el historial de auditoría.</p>
            <a href='../index.php'>Volver al inicio</a>
          </div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría de Entradas de Inventario - INVERSIONES J.A</title>
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
        @media print {
            nav, .no-print, button, form, .filters-container { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .max-w-\[1300px\] { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .bg-white { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col selection:bg-blue-500 selection:text-white p-4 sm:p-8">

    <div class="max-w-[1300px] w-full mx-auto flex-grow flex flex-col">
        <!-- Cabecera del Módulo -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-check text-blue-600"></i> Auditoría / Historial de Entradas
                </h2>
                <p class="text-slate-500 text-xs sm:text-sm mt-0.5">Supervisa y controla el registro de lotes de inventario.</p>
            </div>
            <div class="flex items-center gap-2 no-print">
                <button onclick="window.print()" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs sm:text-sm px-3.5 py-2 rounded-xl transition shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-print text-xs"></i> Imprimir Reporte
                </button>
                <button onclick="cargarHistorial()" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs sm:text-sm px-3.5 py-2 rounded-xl transition shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-sync text-xs"></i> Actualizar
                </button>
            </div>
        </div>

        <!-- FILTROS DE BÚSQUEDA -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-6 no-print filters-container">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                <div class="lg:col-span-4">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Buscar (Nº Entrada / Usuario):</label>
                    <input type="text" id="txtBusqueda" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="Ej: INV-20260818-0001 o Juan">
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha Desde:</label>
                    <input type="date" id="fechaInicio" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha Hasta:</label>
                    <input type="date" id="fechaFin" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div class="lg:col-span-2 flex items-center gap-2">
                    <button class="flex-grow bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs py-2.5 px-3 rounded-xl transition shadow-xs flex items-center justify-center gap-1" onclick="cargarHistorial()">
                        <i class="fa-solid fa-magnifying-glass text-[11px]"></i> Filtrar
                    </button>
                    <button class="bg-slate-100 hover:bg-slate-200 text-slate-600 p-2.5 rounded-xl transition" onclick="limpiarFiltros()" title="Limpiar Filtros">
                        <i class="fa-solid fa-rotate-left text-sm"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- TABLA PRINCIPAL DE REGISTROS -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex-grow">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-6 py-3.5">Nº Inventario</th>
                            <th class="px-6 py-3.5">Fecha y Hora</th>
                            <th class="px-6 py-3.5">Ingresado Por</th>
                            <th class="px-6 py-3.5 text-center">Estado</th>
                            <th class="px-6 py-3.5 text-center">Tipos de Productos</th>
                            <th class="px-6 py-3.5 text-center">Total Unidades</th>
                            <th class="px-6 py-3.5 text-center no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyHistorial" class="divide-y divide-slate-100 text-sm">
                        <tr>
                            <td colspan="7" class="text-center py-8 text-slate-400">Cargando datos...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL DETALLE DE LOTE -->
    <div id="modalDetalleLote" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 sm:p-7 w-full max-w-3xl shadow-xl border border-slate-100 max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100 mb-4">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked text-blue-600"></i> Detalle de Entrada: <span id="lblNumEntrada" class="text-blue-600"></span>
                </h3>
                <button type="button" class="text-slate-400 hover:text-slate-600 text-lg no-print" onclick="cerrarModalDetalle()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="space-y-4 overflow-y-auto flex-grow pr-1">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs sm:text-sm">
                    <div>
                        <span class="text-slate-500 block mb-0.5">Usuario Responsable:</span>
                        <span id="lblUsuario" class="text-slate-900 font-bold"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block mb-0.5">Fecha de Ingreso:</span>
                        <span id="lblFecha" class="text-slate-800 font-medium"></span>
                    </div>
                    <div class="sm:text-right">
                        <span class="text-slate-500 block mb-0.5">Estado actual:</span>
                        <span id="lblEstado" class="inline-block px-2.5 py-1 rounded-lg text-xs font-semibold"></span>
                    </div>
                </div>

                <div>
                    <h4 class="font-bold text-slate-800 text-xs sm:text-sm mb-2 uppercase tracking-wide">Productos Ingresados en este Lote:</h4>
                    <div class="border border-slate-200 rounded-xl overflow-hidden max-h-[250px] overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs sm:text-sm">
                            <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200 sticky top-0">
                                <tr>
                                    <th class="px-4 py-2.5">Código de Barra</th>
                                    <th class="px-4 py-2.5">Nombre del Producto</th>
                                    <th class="px-4 py-2.5 text-center">Cantidad</th>
                                    <th class="px-4 py-2.5 text-end">P. Compra (L.)</th>
                                    <th class="px-4 py-2.5 text-end">P. Venta (L.)</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDetalleProductos" class="divide-y divide-slate-100">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-100 mt-4 no-print">
                <button type="button" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs px-3.5 py-2 rounded-xl transition shadow-xs flex items-center gap-1.5" onclick="window.print()">
                    <i class="fa-solid fa-print text-xs"></i> Imprimir Comprobante
                </button>
                <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold px-4 py-2.5 rounded-xl transition" onclick="cerrarModalDetalle()">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- MODAL DE SEGURIDAD PARA ANULACIÓN DE LOTE -->
    <div id="modalAnularSeguridad" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 sm:p-7 w-full max-w-md shadow-xl border border-rose-100">
            <h3 class="text-lg font-bold text-rose-600 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-shield-halved"></i> Confirmar Anulación de Lote
            </h3>
            <p class="text-rose-600 font-medium text-xs sm:text-sm mb-1">⚠ ¡Atención! Esta acción restará la cantidad del lote del inventario actual.</p>
            <p class="text-slate-500 text-xs mb-4">Para proceder, ingresa tu contraseña de administrador:</p>
            
            <input type="hidden" id="anular_lote_id">
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Contraseña de Administrador:</label>
                <input type="password" id="txtClaveAdmin" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500 transition" placeholder="Escribe tu contraseña..." autocomplete="new-password">
            </div>

            <div class="flex items-center justify-end gap-2.5">
                <button type="button" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold px-4 py-2.5 rounded-xl transition" onclick="cerrarModalSeguridad()">Cancelar</button>
                <button type="button" class="bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold px-5 py-2.5 rounded-xl transition shadow-xs flex items-center gap-1.5" onclick="ejecutarAnulacionLote()">
                    <i class="fa-solid fa-ban text-xs"></i> Confirmar y Anular Lote
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts de Lógica (Adaptados a modales custom y Tailwind) -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const txtClaveInput = document.getElementById('txtClaveAdmin');

            txtClaveInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    ejecutarAnulacionLote();
                }
            });

            document.getElementById('txtBusqueda').addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    cargarHistorial();
                }
            });

            cargarHistorial();
        });

        async function cargarHistorial() {
            const busqueda = document.getElementById('txtBusqueda').value.trim();
            const fechaInicio = document.getElementById('fechaInicio').value;
            const fechaFin = document.getElementById('fechaFin').value;

            const params = new URLSearchParams({
                accion: 'listar',
                busqueda: busqueda,
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin
            });

            const tbody = document.getElementById('tbodyHistorial');
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-8 text-slate-400">
                        <div class="inline-block animate-spin rounded-full h-5 w-5 border-2 border-blue-600 border-t-transparent mb-2"></div>
                        <p class="text-xs text-slate-500">Cargando datos...</p>
                    </td>
                </tr>`;

            try {
                const response = await fetch(`../api/auditoria_inventario.php?${params.toString()}`);
                const res = await response.json();

                if (!res.success) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-rose-600 py-6 text-sm font-medium">${res.message}</td></tr>`;
                    return;
                }

                if (res.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-slate-400 text-sm">No se encontraron lotes registrados.</td></tr>`;
                    return;
                }

                let html = '';
                res.data.forEach(item => {
                    const esAnulado = item.estado === 'ANULADO';
                    const badgeEstado = esAnulado 
                        ? '<span class="bg-rose-50 text-rose-600 border border-rose-200 text-xs font-semibold px-2.5 py-1 rounded-lg">ANULADO</span>' 
                        : '<span class="bg-emerald-50 text-emerald-600 border border-emerald-200 text-xs font-semibold px-2.5 py-1 rounded-lg">ACTIVO</span>';

                    html += `
                        <tr class="hover:bg-slate-50/50 transition border-b border-slate-100 last:border-none ${esAnulado ? 'bg-slate-50/60 text-slate-400' : ''}">
                            <td class="px-6 py-4 font-semibold ${esAnulado ? 'line-through text-slate-400' : 'text-slate-900'}">${escapeHtml(item.numero_entrada)}</td>
                            <td class="px-6 py-4 text-slate-600">${escapeHtml(item.fecha_ingreso)}</td>
                            <td class="px-6 py-4 font-medium text-slate-700 flex items-center gap-1.5"><i class="fa-solid fa-user text-slate-400 text-xs"></i> ${escapeHtml(item.usuario_nombre)}</td>
                            <td class="px-6 py-4 text-center">${badgeEstado}</td>
                            <td class="px-6 py-4 text-center"><span class="bg-slate-100 text-slate-600 text-xs font-medium px-2 py-1 rounded-md">${item.total_items} ítems</span></td>
                            <td class="px-6 py-4 text-center"><span class="font-bold text-xs ${esAnulado ? 'text-slate-400' : 'text-emerald-600'}">+${item.total_unidades} un.</span></td>
                            <td class="px-6 py-4 text-center no-print">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button class="bg-cyan-50 hover:bg-cyan-100 text-cyan-700 font-medium text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1" onclick="verDetalle(${item.id})">
                                        <i class="fa-solid fa-eye text-[11px]"></i> Detalle
                                    </button>
                                    ${!esAnulado ? `
                                        <button class="bg-rose-50 hover:bg-rose-100 text-rose-700 font-medium text-xs px-3 py-1.5 rounded-lg transition flex items-center gap-1" onclick="solicitarAnulacion(${item.id})">
                                            <i class="fa-solid fa-ban text-[11px]"></i> Anular
                                        </button>
                                    ` : ''}
                                </div>
                            </td>
                        </tr>
                    `;
                });

                tbody.innerHTML = html;

            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-rose-600 py-6 text-sm font-medium">Error de comunicación con el servidor.</td></tr>`;
            }
        }

        async function verDetalle(id) {
            try {
                const response = await fetch(`../api/auditoria_inventario.php?accion=ver_detalle&id=${id}`);
                const res = await response.json();

                if (!res.success) {
                    alert(res.message);
                    return;
                }

                document.getElementById('lblNumEntrada').textContent = res.cabecera.numero_entrada;
                document.getElementById('lblUsuario').textContent = res.cabecera.usuario_nombre;
                document.getElementById('lblFecha').textContent = res.cabecera.fecha_ingreso;

                const lblEstado = document.getElementById('lblEstado');
                if (res.cabecera.estado === 'ANULADO') {
                    lblEstado.className = 'bg-rose-50 text-rose-600 border border-rose-200 text-xs font-semibold px-2.5 py-1 rounded-lg';
                    lblEstado.textContent = 'ANULADO';
                } else {
                    lblEstado.className = 'bg-emerald-50 text-emerald-600 border border-emerald-200 text-xs font-semibold px-2.5 py-1 rounded-lg';
                    lblEstado.textContent = 'ACTIVO';
                }

                const tbody = document.getElementById('tbodyDetalleProductos');
                let html = '';

                res.productos.forEach(p => {
                    html += `
                        <tr class="hover:bg-slate-50/50 border-b border-slate-100 last:border-none">
                            <td class="px-4 py-3 font-semibold text-slate-800">${escapeHtml(p.codigo_barra || 'N/A')}</td>
                            <td class="px-4 py-3 text-slate-700">${escapeHtml(p.producto_nombre)}</td>
                            <td class="px-4 py-3 text-center font-bold text-emerald-600">+${p.cantidad}</td>
                            <td class="px-4 py-3 text-end text-slate-600">L. ${parseFloat(p.precio_compra).toFixed(2)}</td>
                            <td class="px-4 py-3 text-end text-slate-600">L. ${parseFloat(p.precio_venta).toFixed(2)}</td>
                        </tr>
                    `;
                });

                tbody.innerHTML = html;
                
                const modal = document.getElementById('modalDetalleLote');
                modal.classList.remove('hidden');
                modal.classList.add('flex');

            } catch (error) {
                alert('Ocurrió un error al cargar el detalle.');
            }
        }

        function cerrarModalDetalle() {
            const modal = document.getElementById('modalDetalleLote');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function solicitarAnulacion(id) {
            document.getElementById('anular_lote_id').value = id;
            document.getElementById('txtClaveAdmin').value = '';
            
            const modal = document.getElementById('modalAnularSeguridad');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            setTimeout(() => {
                document.getElementById('txtClaveAdmin').focus();
            }, 100);
        }

        function cerrarModalSeguridad() {
            const modal = document.getElementById('modalAnularSeguridad');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        async function ejecutarAnulacionLote() {
            const id = document.getElementById('anular_lote_id').value;
            const clave = document.getElementById('txtClaveAdmin').value.trim();

            if (!clave) {
                alert('Por favor ingresa tu contraseña de administrador.');
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'anular_lote');
            formData.append('entrada_id', id);
            formData.append('clave_admin', clave);

            try {
                const response = await fetch('../api/auditoria_inventario.php', { method: 'POST', body: formData });
                const res = await response.json();

                if (res.success) {
                    alert(res.message);
                    cerrarModalSeguridad();
                    cargarHistorial();
                } else {
                    alert(res.message);
                }
            } catch (error) {
                alert('Error al intentar anular el lote.');
            }
        }

        function limpiarFiltros() {
            document.getElementById('txtBusqueda').value = '';
            document.getElementById('fechaInicio').value = '';
            document.getElementById('fechaFin').value = '';
            cargarHistorial();
        }

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>
</body>
</html>