<?php
/**
 * Plugin Name: SIARHE Data Visualization Engine
 * Description: Sistema modular de visualización de datos de enfermería con soporte SQL y GeoJSON.
 * Version: 1.0.0
 * Author: Juan Carlos de la Cruz Eligio
 * Text Domain: siarhe-dataviz
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Definición de constantes
define( 'SIARHE_VERSION', '1.0.0' );
define( 'SIARHE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIARHE_URL', plugin_dir_url( __FILE__ ) );

// Carga de módulos (Descomenta conforme vayas desarrollando)
require_once SIARHE_PATH . 'admin/class-siarhe-admin.php';
require_once SIARHE_PATH . 'includes/helpers.php';
require_once SIARHE_PATH . 'includes/db/class-db-installer.php';
// require_once SIARHE_PATH . 'includes/db/class-data-loader.php'; // Aún no creado
// require_once SIARHE_PATH . 'includes/shortcodes/map-renderer.php'; // Aún no creado

// Inicialización
function siarhe_init_plugin() {
    $plugin_admin = new Siarhe_Admin();
    $plugin_admin->init();
}
add_action( 'plugins_loaded', 'siarhe_init_plugin' );

// Activación (Tablas SQL)
register_activation_hook( __FILE__, array( 'Siarhe_DB_Installer', 'install' ) );