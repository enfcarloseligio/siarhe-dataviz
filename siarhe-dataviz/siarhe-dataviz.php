<?php
/**
 * Plugin Name: SIARHE Data Visualization Engine
 * Description: Sistema modular de visualización de datos de enfermería con soporte SQL y GeoJSON.
 * Version: 0.0.1.8
 * Author: Juan Carlos de la Cruz Eligio
 * Text Domain: siarhe-dataviz
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Definición de Constantes Básicas
define( 'SIARHE_VERSION', '1.0.0' );
define( 'SIARHE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIARHE_URL', plugin_dir_url( __FILE__ ) );

// 2. Definición de Rutas de Carga (ESTO ES LO QUE FALTABA)
// Obtenemos la información del directorio de uploads de WordPress
$upload_dir = wp_upload_dir();

// Ruta física (para mover archivos con PHP)
define( 'SIARHE_UPLOAD_DIR', $upload_dir['basedir'] . '/siarhe-data/' ); 

// Ruta Web (para generar enlaces y botones)
define( 'SIARHE_UPLOAD_URL', $upload_dir['baseurl'] . '/siarhe-data/' );


// 3. Carga de Módulos
require_once SIARHE_PATH . 'admin/class-siarhe-admin.php';
require_once SIARHE_PATH . 'includes/helpers.php';
require_once SIARHE_PATH . 'includes/db/class-db-installer.php';

// 4. Inicialización
function siarhe_init_plugin() {
    $plugin_admin = new Siarhe_Admin();
    $plugin_admin->init();
}
add_action( 'plugins_loaded', 'siarhe_init_plugin' );

// 5. Activación (Creación de tablas y carpetas)
function siarhe_activate_plugin() {
    // Instalar tablas SQL
    Siarhe_DB_Installer::install();

    // Crear carpetas físicas si no existen
    $folders = [ '', '/geojson', '/raw', '/processed' ];
    foreach ( $folders as $folder ) {
        $path = SIARHE_UPLOAD_DIR . $folder;
        if ( ! file_exists( $path ) ) {
            wp_mkdir_p( $path );
        }
    }
}
register_activation_hook( __FILE__, 'siarhe_activate_plugin' );