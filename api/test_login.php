<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/conexion.php';

echo "<h2>Prueba de Limpieza de Contraseña</h2>";

$emailBuscado = 'admin@sistema.com';
// Usamos trim() para eliminar cualquier espacio oculto
$passIngresada = trim('admin123');

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$emailBuscado]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // También limpiamos el hash extraído de MySQL por si traía saltos de línea
    $hashBD = trim($user['password']);

    if (password_verify($passIngresada, $hashBD)) {
        echo "<h3 style='color:green;'>🎉 ¡ÉXITO TOTAL! La contraseña coincide correctamente tras aplicar trim().</h3>";
    } else {
        echo "<h3 style='color:red;'>❌ Sigue fallando la comparación.</h3>";
    }
}
?>