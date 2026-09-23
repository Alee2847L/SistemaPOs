<?php
// api/clientes.php
session_start();
require_once '../config/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// Validar que exista la sesión del usuario
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Usamos $_REQUEST para capturar tanto GET (en listar/obtener) como POST (en guardar/eliminar) sin problemas
$accion = $_REQUEST['accion'] ?? '';
$rolUsuario = $_SESSION['usuario_rol'] ?? 'vendedor';

// --- 1. LISTAR CLIENTES (Con cálculo de mora y campos nuevos corregidos para SQL estricto) ---
if ($accion === 'listar') {
    try {
        // Un cobrador solo debe ver los clientes que le fueron asignados
        // (clientes.cobrador_asignado = su usuario_id) más los que todavía no
        // se le han asignado a nadie (cobrador_asignado IS NULL). Admin y
        // vendedor siguen viendo la lista completa, igual que siempre.
        $filtroCobrador = '';
        $paramsCobrador = [];
        if ($rolUsuario === 'cobrador') {
            $filtroCobrador = ' WHERE (c.cobrador_asignado = ? OR c.cobrador_asignado IS NULL) ';
            $paramsCobrador[] = $_SESSION['usuario_id'];
        }

        $sql = "SELECT
                    c.*,
                    COALESCE(MAX(DATEDIFF(CURDATE(), cu.fecha_vencimiento)), 0) AS dias_mora
                FROM clientes c
                LEFT JOIN contratos con ON c.codigo_bp = con.codigo_bp AND con.estado = 'ACTIVO'
                LEFT JOIN cuotas_contrato cu ON con.id = cu.contrato_id
                    AND cu.estado = 'PENDIENTE'
                    AND cu.fecha_vencimiento < CURDATE()
                {$filtroCobrador}
                GROUP BY
                    c.codigo_bp, c.estado, c.tipo_cliente, c.rtn_dni,
                    c.Nombre, c.limite_credito, c.dias_credito,
                    c.Telefono, c.Direccion, c.Correo, c.cobrador_asignado
                ORDER BY c.codigo_bp ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsCobrador);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $clientes, 'rol' => $rolUsuario]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $e->getMessage()]);
    }
    exit;
}

// --- 2. OBTENER UN CLIENTE ---
if ($accion === 'obtener') {
    $codigo_bp = trim($_GET['codigo_bp'] ?? $_POST['codigo_bp'] ?? '');
    $stmt = $pdo->prepare("SELECT *, limite_credito FROM clientes WHERE codigo_bp = ?");
    $stmt->execute([$codigo_bp]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        $cliente['limite_credito'] = floatval($cliente['limite_credito'] ?? 0);
        echo json_encode(['success' => true, 'data' => $cliente]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
    }
    exit;
}

// --- 3. GUARDAR / EDITAR CLIENTE ---
if ($accion === 'guardar') {
    $es_edicion     = intval($_POST['es_edicion'] ?? 0);
    $codigo_bp      = trim($_POST['codigo_bp'] ?? '');
    $estado         = trim($_POST['estado'] ?? 'ACT');
    $tipo_cliente   = trim($_POST['tipo_cliente'] ?? 'natural');
    $rtn_dni        = trim($_POST['rtn_dni'] ?? '');
    $nombre         = trim($_POST['nombre'] ?? '');
    $limite_credito = floatval($_POST['limite_credito'] ?? 0.00);
    $dias_credito   = intval($_POST['dias_credito'] ?? 0);
    $telefono       = trim($_POST['telefono'] ?? '');
    $direccion      = trim($_POST['direccion'] ?? '');
    $correo         = trim($_POST['correo'] ?? '');

    if (empty($rtn_dni) || empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'DNI/RTN y Nombre son obligatorios']);
        exit;
    }

    // Un cobrador puede ver clientes, pero no puede crear ni editar ninguno
    // (solo el administrador administra la cartera de clientes).
    if ($rolUsuario === 'cobrador') {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para agregar o editar clientes.']);
        exit;
    }

    if ($es_edicion === 1) {
        if ($rolUsuario !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para editar clientes. Solo los administradores pueden hacerlo.']);
            exit;
        }

        // El cobrador asignado solo lo puede cambiar un administrador; para
        // los demás roles (que de todos modos no llegan aquí) se ignora.
        $cobradorAsignadoRaw = trim($_POST['cobrador_asignado'] ?? '');
        $cobradorAsignado = ($cobradorAsignadoRaw === '') ? null : intval($cobradorAsignadoRaw);

        try {
            $stmt = $pdo->prepare("UPDATE clientes SET estado = ?, tipo_cliente = ?, rtn_dni = ?, Nombre = ?, limite_credito = ?, dias_credito = ?, Telefono = ?, Direccion = ?, Correo = ?, cobrador_asignado = ? WHERE codigo_bp = ?");
            $stmt->execute([$estado, $tipo_cliente, $rtn_dni, $nombre, $limite_credito, $dias_credito, $telefono, $direccion, $correo, $cobradorAsignado, $codigo_bp]);
            echo json_encode(['success' => true, 'message' => 'Cliente actualizado con éxito']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
        }
    } else {
        try {
            $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo_bp, 3) AS UNSIGNED)) as ultimo_num FROM clientes WHERE codigo_bp LIKE 'BP%'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $siguiente_num = ($row['ultimo_num'] ?? 0) + 1;
            
            $nuevo_codigo_bp = 'BP' . str_pad($siguiente_num, 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO clientes (codigo_bp, estado, tipo_cliente, rtn_dni, Nombre, limite_credito, dias_credito, Telefono, Direccion, Correo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nuevo_codigo_bp, $estado, $tipo_cliente, $rtn_dni, $nombre, $limite_credito, $dias_credito, $telefono, $direccion, $correo]);
            echo json_encode(['success' => true, 'message' => 'Cliente registrado con éxito como ' . $nuevo_codigo_bp]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo json_encode(['success' => false, 'message' => 'El DNI/RTN ya se encuentra registrado']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
            }
        }
    }
    exit;
}

// --- 4. ELIMINAR CLIENTE ---
if ($accion === 'eliminar') {
    if ($rolUsuario !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Solo el administrador puede eliminar clientes']);
        exit;
    }

    $codigo_bp   = trim($_POST['codigo_bp'] ?? '');
    $clave_admin = trim($_POST['clave_admin'] ?? '');

    if (empty($clave_admin)) {
        echo json_encode(['success' => false, 'message' => 'Debe ingresar la contraseña de administrador']);
        exit;
    }

    try {
        $stmtUser = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmtUser->execute([$_SESSION['usuario_id']]);
        $usuarioDb = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioDb) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }

        $passwordAlmacenada = $usuarioDb['password'];

        $esValidaHash = password_get_info($passwordAlmacenada)['algo'] !== 0 && password_verify($clave_admin, $passwordAlmacenada);
        $esValidaPlano = ($passwordAlmacenada === $clave_admin);

        if (!$esValidaHash && !$esValidaPlano) {
            echo json_encode(['success' => false, 'message' => 'Contraseña de administrador incorrecta']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al verificar credenciales: ' . $e->getMessage()]);
        exit;
    }

    if ($codigo_bp === 'BP000') {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar el cliente predeterminado']);
        exit;
    }

    if (!empty($codigo_bp)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM clientes WHERE codigo_bp = ?");
            $stmt->execute([$codigo_bp]);
            echo json_encode(['success' => true, 'message' => 'Cliente eliminado con éxito']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar este cliente porque tiene ventas o contratos asociados']);
        }
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
exit;
?>