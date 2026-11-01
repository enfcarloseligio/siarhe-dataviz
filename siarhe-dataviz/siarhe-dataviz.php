<?php // siarhe-dataviz.php
/**
 * SIARHE Data Visualization Engine - Archivo principal del plugin.
 *
 * Es el punto de entrada: verifica los requisitos minimos, define las constantes
 * globales y carga los modulos (administracion, shortcodes y frontend).
 *
 * @package    SIARHE_DataViz
 * @author     Juan Carlos De La Cruz Eligio | @enfcarloseligio
 * @copyright  Juan Carlos De La Cruz Eligio | @enfcarloseligio
 * @license    GPL-2.0-or-later
 * @link       https://github.com/enfcarloseligio/siarhe-dataviz
 *
 * @wordpress-plugin
 * Plugin Name:       SIARHE Data Visualization Engine
 * Plugin URI:        https://github.com/enfcarloseligio/siarhe-dataviz
 * Description:       Mapas coropléticos (D3.js) y tablas interactivas de recursos humanos de enfermería de México (SIARHE), con carga y validación de GeoJSON y CSV, copias de seguridad y panel de administración.
 * Version:           0.1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Juan Carlos De La Cruz Eligio | @enfcarloseligio
 * Author URI:        https://enfcarloseligio.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       siarhe-dataviz
 * Domain Path:       /languages
 * Network:           false
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 0. Requisitos minimos (PHP y WordPress). WordPress ya impide ACTIVAR el plugin si no se cumplen las
// cabeceras de arriba; esta comprobacion cubre el caso de que el sitio se degrade despues de activarlo.
define( 'SIARHE_MIN_PHP', '7.4' );
define( 'SIARHE_MIN_WP', '6.0' );

function siarhe_requisitos_cumplidos() {
    global $wp_version;
    return version_compare( PHP_VERSION, SIARHE_MIN_PHP, '>=' ) && version_compare( $wp_version, SIARHE_MIN_WP, '>=' );
}

function siarhe_aviso_requisitos() {
    global $wp_version;
    echo '<div class="notice notice-error"><p><strong>SIARHE Data Visualization Engine</strong> necesita PHP '
        . esc_html( SIARHE_MIN_PHP ) . ' o superior y WordPress ' . esc_html( SIARHE_MIN_WP ) . ' o superior. '
        . 'Este sitio usa PHP ' . esc_html( PHP_VERSION ) . ' y WordPress ' . esc_html( $wp_version ) . ', por lo que el plugin no se cargó.</p></div>';
}

if ( ! siarhe_requisitos_cumplidos() ) {
    add_action( 'admin_notices', 'siarhe_aviso_requisitos' );
    return;
}

// 1. Definición de Constantes Básicas
define( 'SIARHE_VERSION', '0.1.1.0' );
define( 'SIARHE_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIARHE_URL', plugin_dir_url( __FILE__ ) );

// 2. Definición de Rutas de Carga
$upload_dir = wp_upload_dir();
define( 'SIARHE_UPLOAD_DIR', $upload_dir['basedir'] . '/siarhe-data/' ); 
define( 'SIARHE_UPLOAD_URL', $upload_dir['baseurl'] . '/siarhe-data/' );


// 3. Carga de Módulos
require_once SIARHE_PATH . 'admin/class-siarhe-admin.php';
require_once SIARHE_PATH . 'includes/helpers.php';
require_once SIARHE_PATH . 'includes/class-siarhe-versions.php';
require_once SIARHE_PATH . 'includes/class-siarhe-columns.php';
require_once SIARHE_PATH . 'includes/class-siarhe-xlsx.php';
require_once SIARHE_PATH . 'includes/db/class-db-installer.php';

// --- NUEVO: Cargar la clase de Shortcodes ---
require_once SIARHE_PATH . 'includes/class-siarhe-shortcodes.php'; 


// 4. Inicialización
function siarhe_init_plugin() {
    // Iniciar Admin
    $plugin_admin = new Siarhe_Admin();
    $plugin_admin->init();

    // --- Iniciar Shortcodes (Frontend) ---
    $plugin_shortcodes = new Siarhe_Shortcodes();
    $plugin_shortcodes->init();
}
add_action( 'plugins_loaded', 'siarhe_init_plugin' );

// Traducciones: la interfaz esta en espanol; el dominio queda listo para futuras traducciones (carpeta /languages)
function siarhe_cargar_textdomain() {
    load_plugin_textdomain( 'siarhe-dataviz', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'siarhe_cargar_textdomain' );
add_action( 'admin_init', 'siarhe_ensure_data_htaccess' ); // tipos MIME de los datos (activa la compresion)

// 5. Activación (Creación de tablas y carpetas)
function siarhe_activate_plugin() {
    // Instalar tablas SQL
    Siarhe_DB_Installer::install();

    // Crear carpetas físicas si no existen
    // Agregué '/static-min' que usamos para los CSV
    $folders = [ '', '/geojson', '/raw', '/processed', '/static-min' ]; 
    foreach ( $folders as $folder ) {
        $path = SIARHE_UPLOAD_DIR . $folder;
        if ( ! file_exists( $path ) ) {
            wp_mkdir_p( $path );
        }
    }
    siarhe_ensure_data_htaccess();
}
register_activation_hook( __FILE__, 'siarhe_activate_plugin' );