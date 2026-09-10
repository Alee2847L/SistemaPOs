<?php
// index.php (en la raíz)
session_start();
require_once 'config/conexion.php';

// --- LÓGICA DE INACTIVIDAD (Servidor) ---
$inactivity_limit = 3600; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset();
    session_destroy();
    header("Location: views/login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

// Validar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: views/login.php");
    exit;
}

$es_admin = (isset($_SESSION['usuario_rol']) && (strtolower($_SESSION['usuario_rol']) === 'admin' || strtolower($_SESSION['usuario_rol']) === 'administrador'));

// --- OBTENER EL NOMBRE DE LA EMPRESA DESDE LA BD ---
$nombre_empresa = "INVERSIONES J.A"; // Valor por defecto
try {
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
    <title>Punto de Venta — <?php echo $nombre_empresa; ?></title>
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
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col selection:bg-blue-500 selection:text-white">

    <!-- MODAL DE ADVERTENCIA (Inactividad) -->
    <div id="modal-timeout" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-4">
        <div class="bg-white p-6 rounded-2xl shadow-xl max-w-sm w-full text-center">
            <i class="fa-solid fa-clock text-rose-500 text-4xl mb-4"></i>
            <h3 class="text-lg font-bold text-slate-900">¿Sigues ahí?</h3>
            <p class="text-sm text-slate-500 my-3">Tu sesión expirará en menos de 1 minuto por inactividad.</p>
            <button onclick="extenderSesion()" class="w-full bg-blue-600 text-white font-bold py-2 rounded-xl hover:bg-blue-700 transition">Continuar trabajando</button>
        </div>
    </div>

    <!-- Barra de Navegación Adaptable -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex flex-col sm:flex-row justify-between items-center gap-3 sticky top-0 z-50 shadow-xs">
        <div class="flex items-center space-x-3">
            <div class="bg-blue-600 text-white p-2 rounded-xl shadow-sm">
                <i class="fa-solid fa-cash-register text-base"></i>
            </div>
            <div>
                <span class="font-bold text-sm sm:text-base tracking-tight text-slate-900 block leading-none"><?php echo $nombre_empresa; ?></span>
                <span class="text-[11px] text-slate-400 font-medium">Sistema de Gestión Comercial</span>
            </div>
        </div>
        <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-100">
            <span class="text-xs sm:text-sm text-slate-600 truncate max-w-[200px] sm:max-w-none">
                Hola, <strong class="text-slate-800"><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario'); ?></strong>
            </span>
            <button onclick="cerrarSesion()" class="text-xs sm:text-sm font-medium text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-xl transition whitespace-nowrap flex items-center gap-1.5">
                <i class="fa-solid fa-power-off text-xs"></i> Cerrar Sesión
            </button>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="max-w-[1400px] w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 flex-grow flex flex-col">
        
        <!-- Cabecera de Bienvenida -->
        <div id="seccion-cabecera" class="mb-6 text-center sm:text-left">
            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">Panel de Control</h2>
            <p class="text-slate-500 text-xs sm:text-sm mt-1">Selecciona un módulo para trabajar en el área principal.</p>
        </div>

        <!-- CONTENEDOR PRINCIPAL FLEX -->
        <div id="contenedor-columnas" class="w-full flex flex-col lg:flex-row gap-6 items-start transition-all duration-500 flex-grow">
            
            <!-- Columna Izquierda: Menú de Módulos -->
            <div id="contenedor-menu" class="w-full flex-none flex flex-col gap-3 transition-all duration-500 relative">
                
                <!-- Listado de tarjetas -->
                <div id="panel-lateral-modulos" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 transition-all duration-300 h-full">

                    <!-- Módulos comunes para Administrador y Vendedor -->
                    <div onclick="abrirModulo('pos', 'Punto de Venta', 'views/pos.php', '🛒')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-blue-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Punto de Venta">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-emerald-600 group-hover:text-white">🛒</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-emerald-600 transition text-sm sm:text-base truncate">Punto de Venta</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Realizar nuevas ventas.</p>
                        </div>
                    </div>

                    <!-- NUEVO MÓDULO DE COTIZACIONES Y ÓRDENES -->
                    <div onclick="abrirModulo('cotizaciones', 'Cotizaciones y Órdenes', 'views/cotizaciones.php', '📝')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-blue-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Cotizaciones y Órdenes">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-blue-600 group-hover:text-white">📝</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-blue-600 transition text-sm sm:text-base truncate">Cotizaciones y Órdenes</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Proyectos y compras a proveedores.</p>
                        </div>
                    </div>

                    <div onclick="abrirModulo('productos', 'Productos', 'views/productos.php', '📦')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-blue-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Productos">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-amber-600 group-hover:text-white">📦</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-amber-600 transition text-sm sm:text-base truncate">Productos</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Inventario y precios.</p>
                        </div>
                    </div>

                    <div onclick="abrirModulo('clientes', 'Clientes', 'views/clientes.php', '👥')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-blue-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Clientes">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-indigo-600 group-hover:text-white">👥</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-indigo-600 transition text-sm sm:text-base truncate">Clientes</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Gestionar clientes y créditos.</p>
                        </div>
                    </div>

                    <div onclick="abrirModulo('transacciones', 'Transacciones', 'views/transacciones.php', '📊')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-blue-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Transacciones">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-blue-600 group-hover:text-white">📊</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-blue-600 transition text-sm sm:text-base truncate">Transacciones</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Historial de ventas.</p>
                        </div>
                    </div>

                    <!-- Módulo de Recaudo (Visible para Administrador y Vendedor) -->
                    <div onclick="abrirModulo('recaudo', 'Recaudo', 'views/recaudo.php', '💵')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-blue-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Recaudo">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-teal-600 group-hover:text-white">💵</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-teal-600 transition text-sm sm:text-base truncate">Recaudo</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Cobros y abonos.</p>
                        </div>
                    </div>

                    <!-- Historial y Control de Recaudos (Visible para Administrador y Vendedor) -->
                    <div onclick="abrirModulo('historial_recaudos', 'Historial de Recaudos', 'views/historial_recaudos.php', '📜')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-blue-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Historial de Recaudos">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-violet-600 group-hover:text-white">📜</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-violet-600 transition text-sm sm:text-base truncate">Historial Recaudos</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Reimpresión y reversiones.</p>
                        </div>
                    </div>

                    <!-- Módulos Exclusivos para Administradores -->
                    <?php if ($es_admin): ?>
                    <div onclick="abrirModulo('auditoria_inventario', 'Auditoría Inventario', 'views/auditoria_inventario.php', '📋')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-blue-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Auditoría Inventario">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-cyan-600 group-hover:text-white">📋</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-cyan-600 transition text-sm sm:text-base truncate">Auditoría Inventario</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Historial de entradas.</p>
                        </div>
                    </div>

                    <div onclick="abrirModulo('arqueo', 'Arqueo de Caja', 'views/arqueo.php', '💰')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-blue-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Arqueo de Caja">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-yellow-600 group-hover:text-white">💰</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-yellow-600 transition text-sm sm:text-base truncate">Arqueo de Caja</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Control de efectivo.</p>
                        </div>
                    </div>

                    <div onclick="abrirModulo('devoluciones', 'Devoluciones', 'views/devoluciones.php', '🔄')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-rose-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Devoluciones">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-rose-600 group-hover:text-white">🔄</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-rose-600 transition text-sm sm:text-base truncate">Devoluciones</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Reembolsos e inventario.</p>
                        </div>
                    </div>

                    <div onclick="abrirModulo('usuarios', 'Usuarios', 'views/usuarios.php', '⚙️')" class="tarjeta-menu group bg-white p-5 rounded-2xl border border-rose-200/60 shadow-xs hover:shadow-md hover:border-rose-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4" title="Usuarios">
                        <div class="icono-modulo w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg shrink-0 transition-all group-hover:bg-rose-600 group-hover:text-white">⚙️</div>
                        <div class="texto-menu flex-1 lg:w-full overflow-hidden">
                            <h3 class="font-bold text-slate-900 group-hover:text-rose-600 transition text-sm sm:text-base truncate">Usuarios</h3>
                            <p class="desc-modulo text-slate-500 text-xs mt-0.5 hidden lg:block truncate">Administrar accesos.</p>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- BOTÓN PARA ENCOGER/EXPANDIR -->
                <div id="contenedor-btn-expandir" class="hidden border-t border-slate-200 pt-3 mt-auto">
                    <button onclick="toggleModoCompacto()" class="w-full bg-white hover:bg-slate-50 text-slate-600 text-xs font-semibold py-2.5 px-3 rounded-xl border border-slate-200 transition shadow-xs flex items-center justify-center gap-2">
                        <span id="btn-toggle-icon">◀</span> 
                        <span id="btn-toggle-text" class="whitespace-nowrap">Hacer más pequeño</span>
                    </button>
                </div>

            </div>

            <!-- Columna Derecha / Principal: Visor Integrado -->
            <div id="contenedor-visor" class="hidden flex-1 w-full min-w-0 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex-col h-[82vh] lg:h-[78vh] transition-all duration-500">
                <!-- Barra superior del visor -->
                <div class="bg-slate-50/90 px-4 sm:px-5 py-3.5 border-b border-slate-200 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <span id="visor-icono" class="text-xl"></span>
                        <h3 id="visor-titulo" class="font-bold text-slate-900 text-sm sm:text-base"></h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <a id="visor-link-completo" href="#" target="_blank" class="hidden sm:flex text-xs font-medium text-slate-600 hover:text-blue-600 bg-white border border-slate-200 px-3 py-1.5 rounded-xl transition shadow-xs items-center gap-1">
                            Pantalla Completa <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                        </a>
                        <button onclick="cerrarModulo()" class="text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 px-3.5 py-1.5 rounded-xl transition">
                            Cerrar ✕
                        </button>
                    </div>
                </div>
                <!-- Contenido incrustado -->
                <div class="flex-1 w-full bg-slate-50/50 relative">
                    <iframe id="visor-iframe" src="" class="w-full h-full border-0"></iframe>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer Minimalista -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400 mt-auto">
        <p>&copy; 2026 INVERSIONES J.A. Todos los derechos reservados.</p>
     </footer>

    <script>
        // --- LÓGICA DE INACTIVIDAD (Cliente) ---
        let inactivityTimer;
        let warningTimer;
        const LIMIT = 3600 * 1000; // 1 hora
        const WARNING = 3540 * 1000; // 59 minutos

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            clearTimeout(warningTimer);
            document.getElementById('modal-timeout').classList.add('hidden');
            
            warningTimer = setTimeout(() => {
                document.getElementById('modal-timeout').classList.remove('hidden');
            }, WARNING);

            inactivityTimer = setTimeout(() => {
                window.location.href = 'views/login.php?timeout=1';
            }, LIMIT);
        }

        function extenderSesion() {
            document.getElementById('modal-timeout').classList.add('hidden');
            fetch('api/keepalive.php').then(() => resetInactivityTimer());
        }

        window.onload = resetInactivityTimer;
        window.onmousemove = resetInactivityTimer;
        window.onkeypress = resetInactivityTimer;
        window.onclick = resetInactivityTimer;

        // --- FUNCIONES ORIGINALES ---
        let esCompacto = false;

        window.addEventListener('DOMContentLoaded', () => {
            if (window.innerWidth >= 1024) {
                abrirModulo('pos', 'Punto de Venta', 'views/pos.php', '🛒');
            }
        });

        function abrirModulo(id, titulo, url, icono) {
            const contenedorVisor = document.getElementById('contenedor-visor');
            const menuLateral = document.getElementById('contenedor-menu');
            const btnToggle = document.getElementById('contenedor-btn-expandir');
            const iframe = document.getElementById('visor-iframe');
            
            contenedorVisor.classList.remove('hidden');
            contenedorVisor.classList.add('flex');
            document.getElementById('visor-titulo').innerText = titulo;
            document.getElementById('visor-icono').innerText = icono;
            document.getElementById('visor-link-completo').href = url;
            iframe.src = url;

            if (window.innerWidth < 1024) {
                menuLateral.classList.add('hidden');
                contenedorVisor.classList.remove('h-[82vh]');
                contenedorVisor.classList.add('fixed', 'inset-0', 'z-50', 'h-full', 'rounded-none', 'border-none');
            } else {
                btnToggle.classList.remove('hidden');
                aplicarDisenoSidebar(esCompacto);
            }
        }

        function toggleModoCompacto() {
            esCompacto = !esCompacto;
            aplicarDisenoSidebar(esCompacto);
        }

        function aplicarDisenoSidebar(compactar) {
            if (window.innerWidth < 1024) return;

            const menuLateral = document.getElementById('contenedor-menu');
            const panelModulos = document.getElementById('panel-lateral-modulos');
            const tarjetas = document.querySelectorAll('.tarjeta-menu');
            const btnTexto = document.getElementById('btn-toggle-text');
            const btnIcono = document.getElementById('btn-toggle-icon');

            if (compactar) {
                menuLateral.className = "w-full lg:w-[88px] flex-none flex flex-col transition-all duration-500 h-[78vh]";
                panelModulos.className = "flex flex-row lg:flex-col gap-2 overflow-x-auto lg:overflow-y-auto pr-1 pb-2 lg:pb-0";
                
                btnTexto.classList.add('hidden');
                btnIcono.innerText = "▶";

                tarjetas.forEach(t => {
                    t.className = "tarjeta-menu group bg-white p-3 rounded-xl border border-slate-200/80 shadow-xs hover:bg-slate-50 transition-all flex items-center justify-center cursor-pointer shrink-0";
                    t.querySelector('.texto-menu').classList.add('hidden');
                });
            } else {
                menuLateral.className = "w-full lg:w-[260px] flex-none flex flex-col transition-all duration-500 h-[78vh]";
                panelModulos.className = "flex flex-col gap-2 overflow-y-auto pr-2";
                
                btnTexto.classList.remove('hidden');
                btnIcono.innerText = "◀";

                tarjetas.forEach(t => {
                    t.className = "tarjeta-menu group bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm hover:bg-slate-50 transition-all flex flex-row items-center justify-start gap-3 cursor-pointer shrink-0";
                    t.querySelector('.texto-menu').classList.remove('hidden');
                    
                    const desc = t.querySelector('.desc-modulo');
                    if(desc) desc.classList.add('hidden');
                });
            }
        }

        function cerrarModulo() {
            const menuLateral = document.getElementById('contenedor-menu');
            const panelModulos = document.getElementById('panel-lateral-modulos');
            const contenedorVisor = document.getElementById('contenedor-visor');
            const btnToggle = document.getElementById('contenedor-btn-expandir');
            const iframe = document.getElementById('visor-iframe');

            contenedorVisor.classList.add('hidden');
            contenedorVisor.classList.remove('flex', 'fixed', 'inset-0', 'z-50', 'h-full', 'rounded-none', 'border-none');
            contenedorVisor.classList.add('h-[82vh]');
            
            menuLateral.classList.remove('hidden');
            btnToggle.classList.add('hidden');
            iframe.src = '';
            esCompacto = false;

            menuLateral.className = "w-full flex-none flex flex-col transition-all duration-500";
            panelModulos.className = "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 transition-all duration-500";

            const tarjetas = document.querySelectorAll('.tarjeta-menu');
            tarjetas.forEach(t => {
                t.className = "tarjeta-menu group bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md hover:border-blue-500/50 transition-all flex items-center lg:flex-col lg:justify-between cursor-pointer gap-4";
                t.querySelector('.texto-menu').classList.remove('hidden');
                
                const desc = t.querySelector('.desc-modulo');
                if(desc) desc.classList.remove('hidden');
            });
        }

        function cerrarSesion() {
            const formData = new FormData();
            formData.append('accion', 'logout');

            fetch('api/auth.php', { method: 'POST', body: formData })
            .then(() => { window.location.href = 'views/login.php'; })
            .catch(() => { window.location.href = 'views/login.php'; });
        }
    </script>
</body>
</html>