<?php
// api/cerrar_conexion_http.php
//
// Cierra la respuesta HTTP hacia el navegador/POS (el fetch() ya recibe su
// JSON y puede continuar) mientras el script PHP sigue corriendo en el
// servidor, para poder enviar correos u otras tareas lentas (SMTP, etc.) sin
// que la pantalla se quede esperando.
//
// fastcgi_finish_request() SOLO existe cuando PHP corre bajo PHP-FPM. Si el
// servidor usa Apache con mod_php (muy común en un LAMP típico), esa función
// no existe y el código anterior simplemente enviaba el correo ANTES de que
// el navegador recibiera la respuesta: el fetch() del formulario se quedaba
// esperando todo el tiempo que tardara el envío por SMTP (con internet lento,
// esto puede ser varios segundos o más), dando la impresión de que el modal
// "no cierra" aunque el contrato ya se haya guardado en la base de datos.
//
// Esta función funciona en ambos casos: usa fastcgi_finish_request() cuando
// está disponible, y si no, cierra la conexión manualmente con el truco de
// "Content-Length" + "Connection: close" + flush(). Para que esto último
// funcione, el script que la use debe haber llamado ob_start() al principio
// (antes de cualquier echo), para que todo el JSON de respuesta quede
// capturado en el buffer y se pueda enviar de una sola vez aquí.
if (!function_exists('cerrarConexionHttpYContinuar')) {
    function cerrarConexionHttpYContinuar(): void {
        ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        // Fallback para Apache/mod_php (sin PHP-FPM).
        $contenido = '';
        if (ob_get_level() > 0) {
            $contenido = ob_get_clean();
        }

        if (!headers_sent()) {
            header('Connection: close');
            header('Content-Length: ' . strlen($contenido));
        }

        echo $contenido;
        flush();

        if (function_exists('session_write_close')) {
            session_write_close();
        }
    }
}