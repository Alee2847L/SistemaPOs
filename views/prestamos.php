<?php
// views/prestamos.php - Diagnóstico de Error 500
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    session_start();
    echo "1. Sesión iniciada correctamente.<br>";

    if (!isset($_SESSION['usuario_id'])) {
        echo "2. Advertencia: No hay sesión de usuario activa.<br>";
    } else {
        echo "2. Usuario autenticado con ID: " . $_SESSION['usuario_id'] . "<br>";
    }

    // Intentar conexión
    require_once '../config/conexion.php';
    if (isset($pdo)) {
        echo "3. Conexión a la Base de Datos establecida con éxito.<br>";
    } else {
        echo "3. Error: La variable \$pdo no está definida.<br>";
    }

} catch (Throwable $e) {
    echo "<h3 style='color:red;'>¡Error Crítico Detectado!</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<b>Archivo:</b> " . $e->getFile() . " en la línea " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>