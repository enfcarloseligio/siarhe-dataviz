<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Funciones de ayuda globales para el plugin SIARHE.
 */

// Ejemplo: Función para formatear números
function siarhe_format_number( $number ) {
    return number_format( $number, 0, '.', ',' );
}