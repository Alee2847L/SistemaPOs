<?php
/**
 * Autoloader manual para Dompdf (sin composer), usado por el Sistema POS.
 * Registra los namespaces PSR-4 de dompdf y sus 4 dependencias.
 *
 * Uso: require_once __DIR__ . '/dompdf_vendor/autoload.php';
 */

spl_autoload_register(function ($class) {
    static $prefixes = [
        'Dompdf\\'          => __DIR__ . '/dompdf/src/',
        'FontLib\\'         => __DIR__ . '/php-font-lib/src/FontLib/',
        'Svg\\'             => __DIR__ . '/php-svg-lib/src/Svg/',
        'Sabberworm\\CSS\\' => __DIR__ . '/css-parser/src/',
        'Masterminds\\'     => __DIR__ . '/html5-php/src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require $file;
            }
            return;
        }
    }
});

// Clases sueltas (classmap) que dompdf necesita fuera de src/ (motor PDF interno).
require_once __DIR__ . '/dompdf/lib/Cpdf.php';

// "files" que declara css-parser en su composer.json (funciones globales de esas clases).
require_once __DIR__ . '/css-parser/src/Rule/Rule.php';
require_once __DIR__ . '/css-parser/src/RuleSet/RuleContainer.php';
