<?php
// views/prestamos.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$nombre_empresa = "INVERSIONES J.";
try {
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
    }
} catch (Exception $e) { }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Préstamos — <?php echo $nombre_empresa; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <header class="bg-white border-b border-slate-200 px-6 py-3.5 flex justify-between items-center">
        <div class="flex items-center gap-2 font-bold text-slate-900">
            <i class="fa-solid fa-file-invoice-dollar text-purple-600"></i> Módulo de Préstamos y Créditos - <?php echo $nombre_empresa; ?>
        </div>
        <div class="flex gap-2">
            <a href="clientes.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs px-3 py-2 rounded-xl transition">Ir a Clientes</a>
            <a href="pos.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs px-3 py-2 rounded-xl transition">Ir al POS</a>
        </div>
    </header>

    <main class="max-w-[1400px] w-full mx-auto p-6 flex-grow flex flex-col">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex-grow flex flex-col">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-bold text-lg text-slate-900">Listado de Préstamos y Contratos</h3>
                <button onclick="alert('Modal de nuevo préstamo')" class="bg-purple-600 hover:bg-purple-700 text-white font-medium text-sm px-4 py-2.5 rounded-xl transition">
                    + Nuevo Préstamo
                </button>
            </div>
            <div class="overflow-x-auto rounded-xl border border-slate-200 flex-grow">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-semibold">
                        <tr>
                            <th class="py-3.5 px-4">ID</th>
                            <th class="py-3.5 px-4">Cliente</th>
                            <th class="py-3.5 px-4">Descripción</th>
                            <th class="py-3.5 px-4">Monto Financiar</th>
                            <th class="py-3.5 px-4">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-sm bg-white">
                        <tr><td colspan="5" class="text-center py-6 text-slate-400">Módulo de préstamos conectado correctamente.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>