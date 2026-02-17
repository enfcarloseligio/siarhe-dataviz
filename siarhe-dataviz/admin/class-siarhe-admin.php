<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Siarhe_Admin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
        
        // NUEVO: Hook para inyectar CSS dinámico (Colores del tema)
        add_action( 'admin_head', array( $this, 'inject_dynamic_css' ) );

        // Hooks formularios
        add_action( 'admin_post_siarhe_upload_geojson', array( $this, 'handle_geojson_upload' ) );
        add_action( 'admin_post_siarhe_update_geojson_meta', array( $this, 'handle_geojson_meta_update' ) );
        add_action( 'admin_post_siarhe_delete_geojson', array( $this, 'handle_geojson_delete' ) );
    }

    // ... (add_plugin_menu se queda igual) ...
    public function add_plugin_menu() {
        add_menu_page( 'SIARHE DataViz', 'SIARHE', 'manage_options', 'siarhe-dataviz', array( $this, 'display_dashboard' ), 'dashicons-chart-area', 25 );
        add_submenu_page( 'siarhe-dataviz', 'Dashboard', 'Dashboard', 'manage_options', 'siarhe-dataviz', array( $this, 'display_dashboard' ) );
        add_submenu_page( 'siarhe-dataviz', 'Carga de Datos', 'Carga de Datos', 'manage_options', 'siarhe-uploader', array( $this, 'display_uploader' ) );
        add_submenu_page( 'siarhe-dataviz', 'Gestor de Bases', 'Gestor de Bases', 'manage_options', 'siarhe-manager', array( $this, 'display_manager' ) );
        add_submenu_page( 'siarhe-dataviz', 'Configuración', 'Configuración', 'manage_options', 'siarhe-settings', array( $this, 'display_settings' ) );
    }

    public function enqueue_styles( $hook ) {
        // Cargar CSS Base
        wp_enqueue_style( 'siarhe-admin-css', SIARHE_URL . 'admin/css/siarhe-admin.css', array(), SIARHE_VERSION, 'all' );
        
        // Solo cargar Scripts de Color Picker en la página de Configuración
        // (Esto optimiza el rendimiento evitando carga innecesaria en otras páginas)
        if ( strpos( $hook, 'siarhe-settings' ) !== false ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'siarhe-admin-js', SIARHE_URL . 'admin/js/siarhe-admin.js', array( 'wp-color-picker' ), SIARHE_VERSION, true );
        }
    }

    /**
     * INYECCIÓN DE CSS DINÁMICO
     * Lee la base de datos y sobrescribe las variables CSS
     */
    public function inject_dynamic_css() {
        $options = get_option( 'siarhe_theme_options' );
        
        if ( ! empty( $options ) ) {
            ?>
            <style type="text/css">
                :root {
                    <?php if(!empty($options['th_bg'])) echo '--siarhe-th-bg: ' . esc_attr($options['th_bg']) . ';'; ?>
                    <?php if(!empty($options['th_text'])) echo '--siarhe-th-text: ' . esc_attr($options['th_text']) . ';'; ?>
                    <?php if(!empty($options['odd_bg'])) echo '--siarhe-tr-odd-bg: ' . esc_attr($options['odd_bg']) . ';'; ?>
                    <?php if(!empty($options['odd_text'])) echo '--siarhe-tr-odd-text: ' . esc_attr($options['odd_text']) . ';'; ?>
                    <?php if(!empty($options['even_bg'])) echo '--siarhe-tr-even-bg: ' . esc_attr($options['even_bg']) . ';'; ?>
                    <?php if(!empty($options['even_text'])) echo '--siarhe-tr-even-text: ' . esc_attr($options['even_text']) . ';'; ?>
                }
            </style>
            <?php
        }
    }

    // ... (Resto de funciones register_plugin_settings y callbacks se quedan igual) ...
    public function display_dashboard() { include_once SIARHE_PATH . 'admin/partials/dashboard-home.php'; }
    public function display_uploader() { include_once SIARHE_PATH . 'admin/partials/data-uploader.php'; } 
    public function display_manager() { include_once SIARHE_PATH . 'admin/partials/data-manager.php'; }
    public function display_settings() { include_once SIARHE_PATH . 'admin/partials/siarhe-settings.php'; }

    public function register_plugin_settings() {        
        register_setting( 'siarhe_links_group', 'siarhe_links_map' );
        register_setting( 'siarhe_theme_group', 'siarhe_theme_options' ); // Grupo Tema
        register_setting( 'siarhe_map_group', 'siarhe_map_options' );
    }

    // ... (Handlers de backend GeoJSON se mantienen idénticos) ...
    public function handle_geojson_upload() { /* ... código existente ... */ 
        if ( ! isset( $_POST['siarhe_nonce'] ) || ! wp_verify_nonce( $_POST['siarhe_nonce'], 'siarhe_upload_nonce' ) ) wp_die( 'Error de seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        if ( isset( $_FILES['siarhe_file'] ) && $_FILES['siarhe_file']['error'] == 0 ) {
            $entidad_slug = sanitize_text_field( $_POST['entidad_slug'] );
            $anio         = intval( $_POST['anio_reporte'] );
            $fecha_corte  = sanitize_text_field( $_POST['fecha_corte'] ); 
            $referencia   = wp_kses_post( $_POST['referencia'] ); 
            $comentarios  = sanitize_textarea_field( $_POST['comentarios'] );

            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/siarhe-data/geojson/';
            if ( ! file_exists( $target_dir ) ) wp_mkdir_p( $target_dir );

            $new_filename = $entidad_slug . '-' . $anio . '.geojson'; 
            $target_file = $target_dir . $new_filename;

            if ( move_uploaded_file( $_FILES['siarhe_file']['tmp_name'], $target_file ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'siarhe_static_assets';
                $wpdb->update( $table_name, array( 'es_activo' => 0 ), array( 'entidad_slug' => $entidad_slug, 'tipo_archivo' => 'geojson' ) );
                $inserted = $wpdb->insert( 
                    $table_name, 
                    array( 'entidad_slug' => $entidad_slug, 'tipo_archivo' => 'geojson', 'ruta_archivo' => 'geojson/' . $new_filename, 'anio_reporte' => $anio, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia, 'comentarios' => $comentarios, 'es_activo' => 1, 'fecha_subida' => current_time( 'mysql' ) ),
                    array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
                );
                if ( $inserted ) { wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=geojson&status=success' ) ); exit; }
                else { wp_die( 'Error DB: ' . $wpdb->last_error ); }
            } else { wp_die( 'Error mover archivo.' ); }
        } else { wp_die( 'Error carga.' ); }
    }

    public function handle_geojson_meta_update() {
        if ( ! isset( $_POST['siarhe_meta_nonce'] ) || ! wp_verify_nonce( $_POST['siarhe_meta_nonce'], 'siarhe_update_meta_nonce' ) ) wp_die( 'Error seguridad.' );
        global $wpdb;
        $file_id = intval( $_POST['file_id'] );
        if ( $file_id > 0 ) {
            $wpdb->update( $wpdb->prefix . 'siarhe_static_assets', array( 'anio_reporte' => intval( $_POST['anio_reporte'] ), 'fecha_corte' => sanitize_text_field( $_POST['fecha_corte'] ), 'referencia_bibliografica' => wp_kses_post( $_POST['referencia'] ), 'comentarios' => sanitize_textarea_field( $_POST['comentarios'] ) ), array( 'id' => $file_id ) );
            wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=geojson&status=updated' ) ); exit;
        }
    }

    public function handle_geojson_delete() {
        $file_id = intval( $_POST['file_id'] );
        check_admin_referer( 'siarhe_delete_nonce_' . $file_id );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        global $wpdb;
        $table_name = $wpdb->prefix . 'siarhe_static_assets';
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT ruta_archivo FROM $table_name WHERE id = %d", $file_id ) );
        if ( $file ) {
            if ( defined('SIARHE_UPLOAD_DIR') ) { $filepath = SIARHE_UPLOAD_DIR . $file->ruta_archivo; if ( file_exists( $filepath ) ) unlink( $filepath ); }
            $wpdb->delete( $table_name, array( 'id' => $file_id ) );
        }
        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=geojson&status=deleted' ) ); exit;
    }
}