<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Siarhe_Admin {

    public function init() {
        // Hooks para menús y estilos
        add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    public function add_plugin_menu() {
        // Menú Principal
        add_menu_page(
            'SIARHE DataViz', 
            'SIARHE', 
            'manage_options', 
            'siarhe-dataviz', 
            array( $this, 'display_dashboard' ), 
            'dashicons-chart-area', 
            25 
        );

        // Submenús (Dashboard, Carga, Configuración)
        add_submenu_page( 'siarhe-dataviz', 'Dashboard', 'Dashboard', 'manage_options', 'siarhe-dataviz', array( $this, 'display_dashboard' ) );
        add_submenu_page( 'siarhe-dataviz', 'Carga de Datos', 'Carga de Datos', 'manage_options', 'siarhe-uploader', array( $this, 'display_uploader' ) );
        add_submenu_page( 'siarhe-dataviz', 'Gestor de Bases', 'Gestor de Bases', 'manage_options', 'siarhe-manager', array( $this, 'display_manager' ) );
    }

    public function enqueue_styles() {
        // wp_enqueue_style( 'siarhe-admin-css', SIARHE_URL . 'admin/css/siarhe-admin.css', array(), SIARHE_VERSION, 'all' );
    }

    // Callbacks para vistas
    public function display_dashboard() { include_once SIARHE_PATH . 'admin/partials/dashboard-home.php'; }
    public function display_uploader() { include_once SIARHE_PATH . 'admin/partials/data-uploader.php'; }
    public function display_manager() { include_once SIARHE_PATH . 'admin/partials/data-manager.php'; }
}