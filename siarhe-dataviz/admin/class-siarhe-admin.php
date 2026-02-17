<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Siarhe_Admin {

    public function init() {
        // 1. Hooks para menús y estilos
        add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

        // 2. Hooks para procesar formularios (Backend)
        // Acción: Subir Archivo Nuevo
        add_action( 'admin_post_siarhe_upload_geojson', array( $this, 'handle_geojson_upload' ) );
        
        // Acción: Actualizar Metadatos (Modal)
        add_action( 'admin_post_siarhe_update_geojson_meta', array( $this, 'handle_geojson_meta_update' ) );

        // Acción: Eliminar GeoJSON
        add_action( 'admin_post_siarhe_delete_geojson', array( $this, 'handle_geojson_delete' ) );
    }

    /**
     * Registra el menú principal y submenús en WP-Admin
     */
    public function add_plugin_menu() {
        add_menu_page( 'SIARHE DataViz', 'SIARHE', 'manage_options', 'siarhe-dataviz', array( $this, 'display_dashboard' ), 'dashicons-chart-area', 25 );
        add_submenu_page( 'siarhe-dataviz', 'Dashboard', 'Dashboard', 'manage_options', 'siarhe-dataviz', array( $this, 'display_dashboard' ) );
        add_submenu_page( 'siarhe-dataviz', 'Carga de Datos', 'Carga de Datos', 'manage_options', 'siarhe-uploader', array( $this, 'display_uploader' ) );
        add_submenu_page( 'siarhe-dataviz', 'Gestor de Bases', 'Gestor de Bases', 'manage_options', 'siarhe-manager', array( $this, 'display_manager' ) );
    }

    public function enqueue_styles() {
        // wp_enqueue_style( 'siarhe-admin-css', SIARHE_URL . 'admin/css/siarhe-admin.css', array(), SIARHE_VERSION, 'all' );
    }

    // --- Callbacks para vistas HTML ---
    public function display_dashboard() { include_once SIARHE_PATH . 'admin/partials/dashboard-home.php'; }
    public function display_uploader() { include_once SIARHE_PATH . 'admin/partials/data-uploader.php'; } 
    public function display_manager() { include_once SIARHE_PATH . 'admin/partials/data-manager.php'; }


    /**
     * LÓGICA: Subida de GeoJSON Nuevo (Forzando .geojson)
     */
    public function handle_geojson_upload() {
        if ( ! isset( $_POST['siarhe_nonce'] ) || ! wp_verify_nonce( $_POST['siarhe_nonce'], 'siarhe_upload_nonce' ) ) {
            wp_die( 'Error de seguridad.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        if ( isset( $_FILES['siarhe_file'] ) && $_FILES['siarhe_file']['error'] == 0 ) {
            
            $entidad_slug = sanitize_text_field( $_POST['entidad_slug'] );
            $anio         = intval( $_POST['anio_reporte'] );
            $fecha_corte  = sanitize_text_field( $_POST['fecha_corte'] ); 
            $referencia   = wp_kses_post( $_POST['referencia'] ); 
            $comentarios  = sanitize_textarea_field( $_POST['comentarios'] );

            // Directorios
            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/siarhe-data/geojson/';
            
            if ( ! file_exists( $target_dir ) ) wp_mkdir_p( $target_dir );

            // 3. Renombrar Archivo (Forzar extensión .geojson)
            $new_filename = $entidad_slug . '-' . $anio . '.geojson'; 
            $target_file = $target_dir . $new_filename;

            // 4. Mover Archivo
            if ( move_uploaded_file( $_FILES['siarhe_file']['tmp_name'], $target_file ) ) {
                
                global $wpdb;
                $table_name = $wpdb->prefix . 'siarhe_static_assets';
                
                // Desactivar versiones anteriores
                $wpdb->update( 
                    $table_name, 
                    array( 'es_activo' => 0 ), 
                    array( 'entidad_slug' => $entidad_slug, 'tipo_archivo' => 'geojson' ) 
                );

                // Insertar nuevo registro
                $inserted = $wpdb->insert( 
                    $table_name, 
                    array( 
                        'entidad_slug' => $entidad_slug,
                        'tipo_archivo' => 'geojson',
                        'ruta_archivo' => 'geojson/' . $new_filename, 
                        'anio_reporte' => $anio,
                        'fecha_corte' => $fecha_corte,
                        'referencia_bibliografica' => $referencia,
                        'comentarios' => $comentarios, 
                        'es_activo' => 1,
                        'fecha_subida' => current_time( 'mysql' )
                    ),
                    array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
                );

                if ( $inserted ) {
                    wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=geojson&status=success' ) );
                    exit;
                } else {
                    wp_die( 'Error al guardar DB: ' . $wpdb->last_error );
                }

            } else {
                wp_die( 'Error al mover archivo.' );
            }
        } else {
            wp_die( 'Error de carga.' );
        }
    }

    /**
     * LÓGICA: Actualizar Metadatos (Modal)
     */
    public function handle_geojson_meta_update() {
        if ( ! isset( $_POST['siarhe_meta_nonce'] ) || ! wp_verify_nonce( $_POST['siarhe_meta_nonce'], 'siarhe_update_meta_nonce' ) ) {
            wp_die( 'Error de seguridad.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        global $wpdb;
        $file_id = intval( $_POST['file_id'] );
        $table_name = $wpdb->prefix . 'siarhe_static_assets';

        if ( $file_id > 0 ) {
            $wpdb->update( 
                $table_name, 
                array( 
                    'anio_reporte' => intval( $_POST['anio_reporte'] ),
                    'fecha_corte' => sanitize_text_field( $_POST['fecha_corte'] ),
                    'referencia_bibliografica' => wp_kses_post( $_POST['referencia'] ),
                    'comentarios' => sanitize_textarea_field( $_POST['comentarios'] )
                ), 
                array( 'id' => $file_id )
            );
            wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=geojson&status=updated' ) );
            exit;
        }
    }

    /**
     * LÓGICA: Eliminar GeoJSON (Físico + DB)
     */
    public function handle_geojson_delete() {
        $file_id = intval( $_POST['file_id'] );
        
        // Verificación de Nonce dinámica (seguridad por ID)
        check_admin_referer( 'siarhe_delete_nonce_' . $file_id );

        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        global $wpdb;
        $table_name = $wpdb->prefix . 'siarhe_static_assets';
        
        // 1. Obtener ruta
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT ruta_archivo FROM $table_name WHERE id = %d", $file_id ) );

        if ( $file ) {
            // 2. Borrar Físico
            // Importante: Usamos SIARHE_UPLOAD_DIR que definimos en el archivo principal
            if ( defined('SIARHE_UPLOAD_DIR') ) {
                $filepath = SIARHE_UPLOAD_DIR . $file->ruta_archivo;
                if ( file_exists( $filepath ) ) {
                    unlink( $filepath );
                }
            }
            
            // 3. Borrar BD
            $wpdb->delete( $table_name, array( 'id' => $file_id ) );
        }

        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=geojson&status=deleted' ) );
        exit;
    }
}