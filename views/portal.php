<?php
// views/portal.php — Portal de clientes (login por código + panel)
session_start();
$logueado = !empty($_SESSION['cliente_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal de Clientes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

<?php if (!$logueado): ?>
<!-- ======================= LOGIN CLIENTE ======================= -->
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-xl shadow-md p-8">
        <h2 class="text-2xl font-bold text-center text-slate-800">Portal de Clientes</h2>
        <p id="subtitulo" class="text-center text-sm text-slate-500 mt-1 mb-6">
            Ingresa tu número de identidad o correo para recibir un código.
        </p>

        <div id="alerta" class="hidden text-sm rounded-lg p-3 mb-4 text-center"></div>

        <!-- Paso 1 -->
        <form id="formPaso1">
            <label class="block text-sm font-semibold mb-1" for="identificador">Identidad o correo</label>
            <input id="identificador" required autocomplete="off"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2.5 mb-4 focus:outline-none focus:border-blue-500">
            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-lg">Enviar código</button>
        </form>

        <!-- Paso 2 -->
        <form id="formPaso2" class="hidden">
            <label class="block text-sm font-semibold mb-1" for="codigo">Código de 6 dígitos</label>
            <input id="codigo" required inputmode="numeric" maxlength="6" pattern="\d{6}" autocomplete="one-time-code"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2.5 mb-4 text-center tracking-[0.5em] text-lg focus:outline-none focus:border-blue-500">
            <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-lg">Ingresar</button>
            <button type="button" id="btnVolver" class="w-full text-sm text-blue-600 mt-3 hover:underline">Usar otros datos / reenviar código</button>
        </form>

        <div class="text-center mt-6 text-sm">
            <a href="login.php" class="text-slate-500 hover:underline">Soy personal de la empresa</a>
        </div>
    </div>
</div>

<script>
const alerta = document.getElementById('alerta');
function mostrar(msg, ok) {
    alerta.textContent = msg;
    alerta.className = 'text-sm rounded-lg p-3 mb-4 text-center ' +
        (ok ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
}
async function post(datos) {
    const fd = new FormData();
    Object.entries(datos).forEach(([k, v]) => fd.append(k, v));
    const r = await fetch('../api/portal_auth.php', { method: 'POST', body: fd });
    const texto = await r.text();
    try {
        return JSON.parse(texto);
    } catch (e) {
        console.error('Respuesta no válida de portal_auth.php (HTTP ' + r.status + '):', texto);
        throw e;
    }
}

document.getElementById('formPaso1').addEventListener('submit', async e => {
    e.preventDefault();
    alerta.className = 'hidden';
    try {
        const r = await post({ accion: 'solicitar_codigo', identificador: document.getElementById('identificador').value.trim() });
        mostrar(r.message, r.success);
        if (r.success) {
            document.getElementById('formPaso1').classList.add('hidden');
            document.getElementById('formPaso2').classList.remove('hidden');
            document.getElementById('subtitulo').textContent = 'Escribe el código que enviamos a tu correo.';
            document.getElementById('codigo').focus();
        }
    } catch { mostrar('Error de conexión con el servidor.', false); }
});

document.getElementById('formPaso2').addEventListener('submit', async e => {
    e.preventDefault();
    alerta.className = 'hidden';
    try {
        const r = await post({
            accion: 'verificar_codigo',
            identificador: document.getElementById('identificador').value.trim(),
            codigo: document.getElementById('codigo').value.trim()
        });
        if (r.success) location.reload(); else mostrar(r.message, false);
    } catch { mostrar('Error de conexión con el servidor.', false); }
});

document.getElementById('btnVolver').addEventListener('click', () => {
    document.getElementById('formPaso2').classList.add('hidden');
    document.getElementById('formPaso1').classList.remove('hidden');
    document.getElementById('codigo').value = '';
    alerta.className = 'hidden';
});
</script>

<?php else: ?>
<!-- ======================= PANEL CLIENTE ======================= -->
<header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3 flex justify-between items-center no-print">
    <span class="font-bold text-slate-800"><i class="fa-solid fa-file-invoice-dollar text-blue-600 mr-2"></i>Mis contratos</span>
    <div class="flex items-center gap-3 text-sm">
        <span id="nombreCliente" class="text-slate-500"></span>
        <button id="btnSalir" class="bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg">Salir</button>
    </div>
</header>

<main class="max-w-4xl mx-auto p-4 sm:p-6">
    <div id="listaContratos" class="grid gap-4 no-print"></div>

    <section id="detalle" class="hidden mt-6 bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 id="detTitulo" class="font-bold text-lg"></h3>
                <p id="detSub" class="text-sm text-slate-500"></p>
                <p id="detDatos" class="text-xs text-slate-400 mt-1"></p>
            </div>
            <div class="flex gap-2 no-print">
                <button onclick="window.print()" class="bg-slate-100 hover:bg-slate-200 text-sm px-3 py-1.5 rounded-lg">
                    <i class="fa-solid fa-print mr-1"></i>Imprimir / PDF
                </button>
            </div>
        </div>

        <div id="bannerMoraDetalle" class="hidden mb-4 p-3 bg-rose-50 border border-rose-300 rounded-lg text-xs text-rose-700 font-semibold"></div>

        <div class="grid grid-cols-2 gap-3 mb-4 text-sm">
            <div class="bg-emerald-50 rounded-lg p-3"><span class="text-slate-500">Total pagado</span><div id="resPagado" class="font-bold text-emerald-700 text-lg"></div></div>
            <div class="bg-amber-50 rounded-lg p-3"><span class="text-slate-500">Saldo pendiente</span><div id="resPendiente" class="font-bold text-amber-700 text-lg"></div></div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[11px]">
                    <tr><th class="p-2">#</th><th class="p-2">Vencimiento</th><th class="p-2 text-right">Cuota</th><th class="p-2">Fecha de pago</th><th class="p-2 text-center">Estado</th><th class="p-2 text-center no-print">Recibo</th></tr>
                </thead>
                <tbody id="tablaCuotas"></tbody>
            </table>
        </div>
    </section>
</main>

<script>
const money = n => 'L. ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2 });
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

async function api(accion, params = '') {
    const r = await fetch(`../api/portal.php?accion=${accion}${params}`);
    if (r.status === 401) { location.reload(); return { success: false }; }
    return r.json();
}

async function cargarContratos() {
    const r = await api('mis_contratos');
    if (!r.success) return;
    document.getElementById('nombreCliente').textContent = r.cliente;
    const cont = document.getElementById('listaContratos');
    if (!r.contratos.length) {
        cont.innerHTML = '<p class="text-slate-400 text-center py-10">No tienes contratos registrados.</p>';
        return;
    }
    cont.innerHTML = r.contratos.map(c => `
        <div class="bg-white rounded-xl border ${c.en_mora ? 'border-rose-300' : 'border-slate-200'} p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="font-semibold">Contrato #${esc(c.id)} — ${esc(c.producto_descripcion)}</div>
                <div class="text-sm text-slate-500 flex items-center gap-1.5 flex-wrap">${esc(c.cuotas_pagadas)} de ${esc(c.numero_cuotas)} cuotas pagadas · Total ${money(c.total_credito)} · ${badgeEstado(c.estado)}</div>
                ${c.en_mora ? `<div class="text-xs text-rose-600 font-semibold mt-1"><i class="fa-solid fa-triangle-exclamation"></i> ${esc(c.cuotas_en_mora)} cuota(s) en mora · hasta ${esc(c.dias_mora_max)} día(s) de atraso</div>` : ''}
            </div>
            <button onclick="verCuotas(${Number(c.id)})" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">Ver cuotas</button>
        </div>`).join('');
}

function botonRecibo(q) {
    // Solo las cuotas pagadas que tienen un recibo asociado
    if (q.estado !== 'PAGADO' || !q.recaudo_id) return '<span class="text-slate-300">—</span>';
    return `<a href="portal_recibo.php?cuota=${Number(q.cuota_id)}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs px-2.5 py-1 rounded-lg">
                <i class="fa-solid fa-print"></i> Recibo
            </a>`;
}

function badgeEstado(estado) {
    const estilos = {
        PAGADO:    'bg-emerald-100 text-emerald-700',
        VENCIDO:   'bg-red-100 text-red-700',
        PENDIENTE: 'bg-amber-100 text-amber-700',
        ANULADO:   'bg-slate-200 text-slate-600',
        'EN MORA': 'bg-rose-100 text-rose-700'
    };
    return `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${estilos[estado] || 'bg-slate-100 text-slate-600'}">${esc(estado)}</span>`;
}

// Badge de una cuota individual: igual que badgeEstado, pero si está en mora
// añade la cantidad de días de atraso (0 días = venció hoy).
function badgeCuota(q) {
    if (q.estado === 'EN MORA') {
        const dias = Number(q.dias_mora) || 0;
        const texto = dias === 0 ? 'EN MORA (hoy)' : `EN MORA (${dias} día${dias === 1 ? '' : 's'})`;
        return `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">${esc(texto)}</span>`;
    }
    return badgeEstado(q.estado);
}

async function verCuotas(id) {
    const r = await api('ver_cuotas', `&contrato_id=${id}`);
    if (!r.success) { alert(r.message || 'No se pudo cargar'); return; }
    const c = r.contrato;
    document.getElementById('detTitulo').textContent = `Contrato #${c.id} — ${c.producto_descripcion}`;
    document.getElementById('detSub').textContent = `Total del crédito: ${money(c.total_credito)} · Estado: ${c.estado}`;
    document.getElementById('detDatos').textContent =
        `Inicio: ${c.fecha_inicio} · Plazo: ${c.plazo_meses} meses · Valor: ${money(c.total_factura)} · Prima: ${money(c.prima)} · ` +
        `Financiado: ${money(c.monto_financiar)} · Interés: ${Number(c.porcentaje_interes)}%`;
    document.getElementById('resPagado').textContent = money(r.resumen.pagado);
    document.getElementById('resPendiente').textContent = money(r.resumen.pendiente);

    const bannerMora = document.getElementById('bannerMoraDetalle');
    if (r.resumen.cuotas_en_mora > 0) {
        bannerMora.innerHTML = `<i class="fa-solid fa-triangle-exclamation mr-1.5"></i>Tienes ${r.resumen.cuotas_en_mora} cuota(s) en mora, con hasta ${r.resumen.dias_mora_max} día(s) de atraso. Ponte al día para seguir con tu crédito al corriente.`;
        bannerMora.classList.remove('hidden');
    } else {
        bannerMora.classList.add('hidden');
    }

    document.getElementById('tablaCuotas').innerHTML = r.cuotas.map(q => `
        <tr class="border-b border-slate-100 ${q.estado === 'EN MORA' ? 'bg-rose-50/60' : ''}">
            <td class="p-2 font-bold">${esc(q.numero_cuota)}</td>
            <td class="p-2 ${q.estado === 'EN MORA' ? 'text-rose-700 font-semibold' : ''}">${esc(q.fecha_vencimiento)}</td>
            <td class="p-2 text-right">${money(q.monto_cuota)}</td>
            <td class="p-2 text-slate-500">${q.fecha_pago ? esc(String(q.fecha_pago).substring(0, 10)) : '—'}</td>
            <td class="p-2 text-center">${badgeCuota(q)}</td>
            <td class="p-2 text-center no-print">${botonRecibo(q)}</td>
        </tr>`).join('');
    const det = document.getElementById('detalle');
    det.classList.remove('hidden');
    det.scrollIntoView({ behavior: 'smooth' });
}

document.getElementById('btnSalir').addEventListener('click', async () => {
    const fd = new FormData(); fd.append('accion', 'logout');
    await fetch('../api/portal_auth.php', { method: 'POST', body: fd });
    location.reload();
});

cargarContratos();
</script>
<?php endif; ?>

</body>
</html>