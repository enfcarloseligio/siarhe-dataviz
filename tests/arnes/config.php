<?php
// Configuración por defecto del plugin (métricas, temas, marcadores) sin WordPress, para el arnés de pruebas en navegador.
define( 'ABSPATH', __DIR__ . '/' );
define( 'SIARHE_PATH', rtrim( $argv[1], '/\\' ) . '/' );
define( 'SIARHE_URL', '/siarhe-dataviz/siarhe-dataviz/' );
function get_option( $n, $d = false ) { return $d; }
function wp_unslash( $v ) { return $v; }
function __( $t ) { return $t; }
function esc_html__( $t ) { return $t; }
function apply_filters( $n, $v ) { return $v; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function sanitize_hex_color( $c ) { return $c; }
function esc_attr( $t ) { return $t; }
require SIARHE_PATH . 'includes/helpers.php';
$out = array(
    'metricas'   => siarhe_get_metricas_config(),
    'temas'      => siarhe_get_temas(),
    'marcadores' => siarhe_get_marcadores_config(),
    'grupos'     => siarhe_marker_grupos(),
    'css'        => function_exists( 'siarhe_get_css_variables_css' ) ? siarhe_get_css_variables_css() : '',
);
echo json_encode( $out, JSON_UNESCAPED_UNICODE );
