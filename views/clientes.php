<?php
// views/clientes.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$modulos_permitidos = $_SESSION['modulos_activos'] ?? [];

if (!in_array('clientes', $modulos_permitidos)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false, 
        'message' => 'Acceso denegado: El módulo de Clientes no está incluido en el plan de su empresa.'
    ]);
    exit;
}

require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rolActual = $_SESSION['usuario_rol'] ?? 'vendedor';
$es_admin = (isset($_SESSION['usuario_rol']) && (strtolower($_SESSION['usuario_rol']) === 'admin' || strtolower($_SESSION['usuario_rol']) === 'administrador'));

$nombre_empresa = "INVERSIONES J.";
// mora_diaria_activa / mora_diaria_porcentaje: recargo diario por cuotas vencidas,
// configurable por empresa (una fila de `configuracion` por base de datos). Ver
// migracion_mora_diaria.sql. Si la empresa no lo tiene activado, esto queda en
// false/0 y aquí no se muestra ningún recargo, solo el monto de las cuotas.
$mora_diaria_activa = false;
$mora_diaria_porcentaje = 0.0;
try {
    $stmt_config = $pdo->query("SELECT nombre_empresa, mora_diaria_activa, mora_diaria_porcentaje FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
        $mora_diaria_activa = !empty($row_config['mora_diaria_activa']);
        $mora_diaria_porcentaje = (float)($row_config['mora_diaria_porcentaje'] ?? 0);
    }
} catch (Exception $e) {
    // Si falta la migración (columnas mora_diaria_*) se reintenta solo con
    // nombre_empresa, para no romper el resto de la pantalla.
    try {
        $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
        if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row_config['nombre_empresa'])) {
                $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
            }
        }
    } catch (Exception $e2) { }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes — <?php echo $nombre_empresa; ?></title>
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

    <!-- Header -->
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

    <!-- Modal Formulario Cliente -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4" id="modalCliente" style="display: none;">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h4 class="font-bold text-slate-900 text-base" id="modalTitulo">Nuevo Cliente</h4>
                <button type="button" onclick="cerrarModal()" class="text-slate-400 hover:text-slate-600 p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6">
                <form id="formCliente" class="space-y-4">
                    <input type="hidden" id="cli_es_edicion" value="0">
                    
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

    <!-- Modal Eliminar -->
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

    <!-- MODAL VER CONTRATOS DEL CLIENTE -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden" id="modalContratosCliente">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-indigo-600 text-white">
                <h4 class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-file-contract"></i> 
                    Contratos de: <span id="modal_cli_nombre">Cliente</span>
                </h4>
                <button type="button" onclick="cerrarModalContratos()" class="text-white/80 hover:text-white p-1 text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-grow space-y-4" id="contenidoContratosCliente">
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-spinner fa-spin text-2xl mb-2"></i>
                    <p>Cargando contratos...</p>
                </div>
            </div>

            <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex justify-end">
                <button type="button" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium rounded-xl transition text-sm" onclick="cerrarModalContratos()">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <script>
        const esAdmin = <?php echo $es_admin ? 'true' : 'false'; ?>;
        // Módulos habilitados para esta empresa (viene de la sesión). Se usa para que
        // "Facturar" lleve a POS solo si ese módulo está disponible; si no (empresas
        // que solo manejan préstamos, como esta), lleva directo a Nuevo Préstamo.
        const MODULOS_ACTIVOS = <?php echo json_encode(array_values($modulos_permitidos)); ?>;
        // Recargo por mora diaria: viene de `configuracion` (por empresa/base de datos).
        // Si MORA_DIARIA_ACTIVA es false, el cálculo de mora de abajo siempre da 0 y
        // esta pantalla solo muestra el monto de las cuotas vencidas, igual que antes.
        const MORA_DIARIA_ACTIVA = <?php echo json_encode($mora_diaria_activa); ?>;
        const MORA_DIARIA_PORCENTAJE = <?php echo json_encode($mora_diaria_porcentaje); ?>;
        let listaClientesOriginal = [];

        // Fecha de "hoy" en horario LOCAL (no UTC): new Date().toISOString() usa UTC,
        // lo que puede correr la fecha un día en Honduras (UTC-6) cerca de la
        // medianoche. Se compara todo como texto 'YYYY-MM-DD', igual que lo guarda la BD.
        function fechaHoyISO() {
            const d = new Date();
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        function diasEntreFechasISO(fechaMenor, fechaMayor) {
            const [y1, m1, d1] = fechaMenor.split('-').map(Number);
            const [y2, m2, d2] = fechaMayor.split('-').map(Number);
            const utc1 = Date.UTC(y1, m1 - 1, d1);
            const utc2 = Date.UTC(y2, m2 - 1, d2);
            return Math.round((utc2 - utc1) / (1000 * 60 * 60 * 24));
        }

        // Recargo por mora de UNA cuota: si vence HOY, diasMora es 0 y el recargo da 0
        // (no se cobra mora el mismo día que vence), aunque la cuota se siga marcando
        // en rojo como "en mora". Si venció antes de hoy, se cobra el % diario por
        // cada día de atraso.
        function calcularMontoMoraCuota(montoCuota, diasMora) {
            if (!MORA_DIARIA_ACTIVA || !(MORA_DIARIA_PORCENTAJE > 0) || diasMora <= 0) {
                return 0;
            }
            return Math.round(montoCuota * (MORA_DIARIA_PORCENTAJE / 100) * diasMora * 100) / 100;
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('txtClaveAdminCli').addEventListener('keydown', (e) => { 
                if(e.key === 'Enter') ejecutarEliminacionCliente(); 
            });
            cargarClientes();
        });

        function cargarClientes() {
            fetch('../api/clientes.php?accion=listar')
                .then(res => res.json())
                .then(res => {
                    if(res.success) { 
                        listaClientesOriginal = res.data; 
                        renderizarTabla(res.data); 
                    }
                });
        }

        function renderizarTabla(clientes) {
            let html = clientes.length === 0 
                ? '<tr><td colspan="8" class="text-center py-6 text-slate-400">No hay clientes registrados</td></tr>' 
                : '';

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

                html += `
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="py-3 px-4 font-semibold">${c.codigo_bp}</td>
                    <td class="py-3 px-4">${badgeEstado}</td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-1 rounded-full text-[10px] font-bold ${esJuridico ? 'bg-sky-100 text-sky-700' : 'bg-emerald-100 text-emerald-700'}">
                            ${esJuridico ? 'JURÍDICO' : 'NATURAL'}
                        </span>
                    </td>
                    <td class="py-3 px-4">${c.rtn_dni}</td>
                    <td class="py-3 px-4">${escapeHtml(c.Nombre)}</td>
                    <td class="py-3 px-4">L. ${Number(c.limite_credito || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="py-3 px-4">${badgeMora}</td>
                    <td class="py-3 px-4 text-center">
                        <div class="flex justify-center gap-1">
                            <!-- Botón Ojo - Ver Contratos -->
                            <button type="button" 
                                    class="bg-indigo-50 text-indigo-700 text-xs px-2 py-1 rounded-lg font-medium cursor-pointer hover:bg-indigo-100 transition" 
                                    onclick="verContratosCliente('${c.codigo_bp}', '${escapeHtml(c.Nombre)}')"
                                    title="Ver contratos y estado de pagos">
                                <i class="fa-solid fa-eye"></i>
                            </button>

                            <button type="button" class="bg-emerald-50 text-emerald-700 text-xs px-2 py-1 rounded-lg font-medium cursor-pointer hover:bg-emerald-100 transition" onclick="irAFacturar('${c.codigo_bp}')">
                                ${MODULOS_ACTIVOS.includes('pos') ? 'Facturar' : 'Nuevo Préstamo'}
                            </button>
                            
                            ${esAdmin ? `
                                <button type="button" class="bg-amber-50 text-amber-700 text-xs px-2 py-1 rounded-lg font-medium cursor-pointer hover:bg-amber-100 transition" onclick="editarCliente('${c.codigo_bp}')">
                                    Editar
                                </button>
                            ` : ''}
                            
                            ${esAdmin && c.codigo_bp !== 'BP000' ? `
                                <button type="button" class="bg-rose-50 text-rose-700 text-xs px-2 py-1 rounded-lg font-medium cursor-pointer hover:bg-rose-100 transition" onclick="eliminarCliente('${c.codigo_bp}')">
                                    Eliminar
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>`;
            });

            document.getElementById('tablaClientes').innerHTML = html;
        }

        function irAFacturar(codigoBp) {
            // Si esta empresa no tiene el módulo de POS habilitado (por ejemplo, un
            // cliente que solo maneja préstamos), "Facturar" no debe llevar a una
            // pantalla a la que no tiene acceso: en vez de eso, va directo al módulo
            // de Préstamos con este cliente ya seleccionado.
            if (MODULOS_ACTIVOS.includes('pos')) {
                window.location.href = `/views/pos.php?codigo_bp=${encodeURIComponent(codigoBp)}`;
            } else {
                window.location.href = `/views/prestamos.php?codigo_bp=${encodeURIComponent(codigoBp)}`;
            }
        }

        function abrirModalNuevo() {
            document.getElementById('modalTitulo').innerText = 'Nuevo Cliente';
            document.getElementById('cli_es_edicion').value = '0';
            document.getElementById('formCliente').reset();
            document.getElementById('cli_codigo_bp').value = 'AUTOGENERADO';
            document.getElementById('contenedor_estado').style.display = 'none';
            document.getElementById('contenedor_credito').style.display = 'none';
            document.getElementById('modalCliente').style.display = 'flex';
        }

        function editarCliente(codigo_bp) {
            const cliente = listaClientesOriginal.find(c => c.codigo_bp === codigo_bp);
            if (!cliente) return;

            document.getElementById('modalTitulo').innerText = 'Editar Cliente';
            document.getElementById('cli_es_edicion').value = '1';
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

        function cerrarModal() { 
            document.getElementById('modalCliente').style.display = 'none'; 
        }

        function cerrarModalEliminar() { 
            document.getElementById('modalEliminarSeguridadCliente').style.display = 'none'; 
        }

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
            if (res.success) { 
                alert(res.message); 
                cerrarModalEliminar(); 
                cargarClientes(); 
            } else { 
                alert(res.message); 
            }
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

        // ========== FUNCIONES DE CONTRATOS ==========
        function verContratosCliente(codigoBp, nombreCliente) {
            document.getElementById('modal_cli_nombre').innerText = nombreCliente;
            document.getElementById('modalContratosCliente').classList.remove('hidden');
            document.getElementById('contenidoContratosCliente').innerHTML = `
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-spinner fa-spin text-2xl mb-2"></i>
                    <p>Cargando contratos...</p>
                </div>
            `;

            fetch(`../api/prestamos.php?accion=contratos_por_cliente&codigo_bp=${encodeURIComponent(codigoBp)}`)
                .then(res => res.json())
                .then(res => {
                    if (!res.success) {
                        document.getElementById('contenidoContratosCliente').innerHTML = `
                            <div class="text-center py-8 text-rose-500">
                                <i class="fa-solid fa-triangle-exclamation text-2xl mb-2"></i>
                                <p>${res.message || 'Error al cargar los contratos'}</p>
                            </div>
                        `;
                        return;
                    }

                    const contratos = res.data || [];

                    if (contratos.length === 0) {
                        document.getElementById('contenidoContratosCliente').innerHTML = `
                            <div class="text-center py-10 text-slate-400">
                                <i class="fa-solid fa-folder-open text-3xl mb-3"></i>
                                <p class="font-medium">Este cliente no tiene contratos activos</p>
                            </div>
                        `;
                        return;
                    }

                    let html = '';

                    contratos.forEach(c => {
                        const cuotas = c.cuotas || [];
                        const hoy = fechaHoyISO();

                        let cuotasPendientes = 0;
                        let cuotasVencidas = 0;
                        let montoVencido = 0;      // suma de las cuotas vencidas, sin mora
                        let montoMoraAcumulada = 0; // recargo por mora de esas cuotas
                        let proximaCuota = null;

                        cuotas.forEach(cuota => {
                            if (cuota.estado === 'PENDIENTE') {
                                cuotasPendientes++;
                                // Una cuota que vence HOY ya cuenta como "en mora" (se marca en
                                // rojo), igual que en Recaudo, aunque todavía no se le cobre
                                // recargo (0 días de atraso todavía).
                                if (cuota.fecha_vencimiento <= hoy) {
                                    cuotasVencidas++;
                                    const montoCuota = parseFloat(cuota.monto_cuota);
                                    const diasMora = diasEntreFechasISO(cuota.fecha_vencimiento, hoy);
                                    montoVencido += montoCuota;
                                    montoMoraAcumulada += calcularMontoMoraCuota(montoCuota, diasMora);
                                } else if (!proximaCuota) {
                                    proximaCuota = cuota;
                                }
                            }
                        });

                        const totalEnMora = montoVencido + montoMoraAcumulada;
                        const estaEnMora = cuotasVencidas > 0;
                        const estadoBadge = estaEnMora
                            ? `<span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-700">EN MORA (${cuotasVencidas} cuotas)</span>`
                            : `<span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700">AL DÍA</span>`;

                        const cuotaPromedio = c.cuota_promedio || (c.plazo_meses > 0 ? (c.total_credito / c.plazo_meses) : 0);

                        html += `
                            <div class="border border-slate-200 rounded-xl overflow-hidden">
                                <div class="bg-slate-50 px-4 py-3 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                                    <div>
                                        <div class="font-bold text-slate-900 text-sm">Contrato #${c.id}</div>
                                        <div class="text-xs text-slate-500 mt-0.5">${escapeHtml(c.producto_descripcion || 'Sin descripción')}</div>
                                    </div>
                                    <div>${estadoBadge}</div>
                                </div>
                                
                                <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                    <div>
                                        <div class="text-slate-500">Capital Financiado</div>
                                        <div class="font-semibold text-slate-800">L. ${Number(c.monto_financiar).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">Total con Interés</div>
                                        <div class="font-semibold text-purple-700">L. ${Number(c.total_credito).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">Valor de Cuota</div>
                                        <div class="font-semibold text-slate-800">L. ${Number(cuotaPromedio).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-500">Cuotas Pendientes</div>
                                        <div class="font-semibold text-slate-800">${cuotasPendientes} de ${c.plazo_meses}</div>
                                    </div>
                                </div>

                                ${estaEnMora ? `
                                    <div class="mx-4 mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <div class="text-rose-700 font-bold text-sm">⚠ Total en Mora</div>
                                                <div class="text-xs text-rose-600">${cuotasVencidas} cuota(s) vencida(s)</div>
                                            </div>
                                            <div class="text-rose-700 font-bold text-lg">
                                                L. ${totalEnMora.toLocaleString('en-US', {minimumFractionDigits: 2})}
                                            </div>
                                        </div>
                                        ${montoMoraAcumulada > 0 ? `
                                            <div class="mt-2 pt-2 border-t border-rose-200 text-xs text-rose-600 flex justify-between">
                                                <span>Cuotas: L. ${montoVencido.toLocaleString('en-US', {minimumFractionDigits: 2})} + Mora (${MORA_DIARIA_PORCENTAJE}%/día): L. ${montoMoraAcumulada.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
                                            </div>
                                        ` : ''}
                                    </div>
                                ` : `
                                    <div class="mx-4 mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700">
                                        <i class="fa-solid fa-circle-check mr-1"></i> 
                                        Cliente al día. 
                                        ${proximaCuota 
                                            ? `Próxima cuota: ${proximaCuota.fecha_vencimiento} por L. ${Number(proximaCuota.monto_cuota).toLocaleString('en-US', {minimumFractionDigits: 2})}` 
                                            : 'No hay más cuotas pendientes.'}
                                    </div>
                                `}
                            </div>
                        `;
                    });

                    document.getElementById('contenidoContratosCliente').innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('contenidoContratosCliente').innerHTML = `
                        <div class="text-center py-8 text-rose-500">
                            <p>Error de conexión al cargar los contratos</p>
                        </div>
                    `;
                });
        }

        function cerrarModalContratos() {
            document.getElementById('modalContratosCliente').classList.add('hidden');
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>