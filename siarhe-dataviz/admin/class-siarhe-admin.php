<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Siarhe_Admin {

    public function init() {
        // Hooks Visuales
        add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
        add_action( 'admin_head', array( $this, 'inject_dynamic_css' ) );

        // 🌟 Auto-Actualizador SQL (Añade las columnas de auditoría si no existen)
        add_action( 'admin_init', array( $this, 'maybe_update_database' ) );

        // --- HANDLERS: GEOJSON ---
        add_action( 'admin_post_siarhe_upload_geojson', array( $this, 'handle_geojson_upload' ) );
        add_action( 'admin_post_siarhe_update_geojson_meta', array( $this, 'handle_geojson_meta_update' ) );
        add_action( 'admin_post_siarhe_delete_geojson', array( $this, 'handle_geojson_delete' ) );

        // --- HANDLERS: BASES ESTÁTICAS Y MARCADORES (Comparten lógica de edición) ---
        add_action( 'admin_post_siarhe_upload_static', array( $this, 'handle_static_upload' ) );
        add_action( 'admin_post_siarhe_update_static_meta', array( $this, 'handle_static_meta_update' ) ); 
        add_action( 'admin_post_siarhe_delete_static', array( $this, 'handle_static_delete' ) );

        // --- HANDLERS: MARCADORES (Subida Específica) ---
        add_action( 'admin_post_siarhe_upload_marker', array( $this, 'handle_marker_upload' ) );
    }

    public function maybe_update_database() {
        global $wpdb;
        $table = $wpdb->prefix . 'siarhe_static_assets';
        $row = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'modificado_por'");
        if(empty($row)) {
            $wpdb->query("ALTER TABLE `$table` ADD `modificado_por` varchar(100) DEFAULT NULL, ADD `fecha_modificacion` datetime DEFAULT NULL;");
        }
    }

    // -------------------------------------------------------------------------
    // MENU & VISUALES
    // -------------------------------------------------------------------------
    public function add_plugin_menu() {
        add_menu_page( 'SIARHE DataViz', 'SIARHE', 'manage_options', 'siarhe-dataviz', array( $this, 'display_dashboard' ), 'dashicons-chart-area', 25 );
        add_submenu_page( 'siarhe-dataviz', 'Dashboard', 'Dashboard', 'manage_options', 'siarhe-dataviz', array( $this, 'display_dashboard' ) );
        add_submenu_page( 'siarhe-dataviz', 'Carga de Datos', 'Carga de Datos', 'manage_options', 'siarhe-uploader', array( $this, 'display_uploader' ) );
        add_submenu_page( 'siarhe-dataviz', 'Gestor de Bases', 'Gestor de Bases', 'manage_options', 'siarhe-manager', array( $this, 'display_manager' ) );
        add_submenu_page( 'siarhe-dataviz', 'Configuración', 'Configuración', 'manage_options', 'siarhe-settings', array( $this, 'display_settings' ) );
    }

    public function enqueue_styles( $hook ) {
        wp_enqueue_style( 'siarhe-admin-css', SIARHE_URL . 'admin/css/siarhe-admin.css', array(), SIARHE_VERSION, 'all' );
        if ( strpos( $hook, 'siarhe-settings' ) !== false ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'siarhe-admin-js', SIARHE_URL . 'admin/js/siarhe-admin.js', array( 'wp-color-picker' ), SIARHE_VERSION, true );
        }
    }

    public function inject_dynamic_css() {
        $options = get_option( 'siarhe_theme_options' );
        $map_options = get_option( 'siarhe_map_options' ); 
        ?>
        <style type="text/css">
            :root {
                <?php 
                if ( ! empty( $options ) ) {
                    if(!empty($options['th_bg'])) echo '--siarhe-th-bg: ' . esc_attr($options['th_bg']) . ';';
                    if(!empty($options['th_text'])) echo '--siarhe-th-text: ' . esc_attr($options['th_text']) . ';';
                    if(!empty($options['odd_bg'])) echo '--siarhe-tr-odd-bg: ' . esc_attr($options['odd_bg']) . ';';
                    if(!empty($options['odd_text'])) echo '--siarhe-tr-odd-text: ' . esc_attr($options['odd_text']) . ';';
                    if(!empty($options['even_bg'])) echo '--siarhe-tr-even-bg: ' . esc_attr($options['even_bg']) . ';';
                    if(!empty($options['even_text'])) echo '--siarhe-tr-even-text: ' . esc_attr($options['even_text']) . ';';
                }
                if ( ! empty( $map_options ) ) {
                    if(!empty($map_options['mono_min'])) echo '--s-map-mono-min: ' . esc_attr($map_options['mono_min']) . ';';
                    if(!empty($map_options['mono_max'])) echo '--s-map-mono-max: ' . esc_attr($map_options['mono_max']) . ';';
                }
                ?>
            }
        </style>
        <?php
    }

    public function display_dashboard() { include_once SIARHE_PATH . 'admin/partials/dashboard-home.php'; }
    public function display_uploader() { include_once SIARHE_PATH . 'admin/partials/data-uploader.php'; } 
    public function display_manager() { include_once SIARHE_PATH . 'admin/partials/data-manager.php'; }
    public function display_settings() { include_once SIARHE_PATH . 'admin/partials/siarhe-settings.php'; }

    public function register_plugin_settings() {        
        register_setting( 'siarhe_links_group', 'siarhe_links_map' );
        register_setting( 'siarhe_theme_group', 'siarhe_theme_options' );
        register_setting( 'siarhe_map_group', 'siarhe_map_options' );
        register_setting( 'siarhe_metricas_group', 'siarhe_metricas_config' );
        register_setting( 'siarhe_tooltip_group', 'siarhe_tooltip_config' );
        register_setting( 'siarhe_marcadores_group', 'siarhe_marcadores_config' );
    }

    // -------------------------------------------------------------------------
    // UTILIDAD PARA FORZAR UTF-8 EN ARCHIVOS CSV
    // -------------------------------------------------------------------------
    private function ensure_utf8_csv( $filepath ) {
        if ( ! file_exists( $filepath ) ) return;
        $content = file_get_contents( $filepath );
        if ( $content === false ) return;

        if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
            $content = mb_convert_encoding( $content, 'UTF-8', 'ISO-8859-1' );
            file_put_contents( $filepath, $content );
        } else {
            $bom = pack('H*','EFBBBF');
            if ( preg_match( "/^$bom/", $content ) ) {
                $content = preg_replace( "/^$bom/", '', $content );
                file_put_contents( $filepath, $content );
            }
        }
    }

    // -------------------------------------------------------------------------
    // HANDLERS: GEOJSON
    // -------------------------------------------------------------------------
    public function handle_geojson_upload() { 
        if ( ! isset( $_POST['siarhe_nonce'] ) || ! wp_verify_nonce( $_POST['siarhe_nonce'], 'siarhe_upload_nonce' ) ) wp_die( 'Error de seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        if ( isset( $_FILES['siarhe_file'] ) && $_FILES['siarhe_file']['error'] == 0 ) {
            $entidad_slug = sanitize_text_field( $_POST['entidad_slug'] );
            $anio = intval( $_POST['anio_reporte'] );
            $fecha_corte = sanitize_text_field( $_POST['fecha_corte'] ); 
            $referencia = wp_kses_post( $_POST['referencia'] ); 
            $comentarios = sanitize_textarea_field( $_POST['comentarios'] );

            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/siarhe-data/geojson/';
            if ( ! file_exists( $target_dir ) ) wp_mkdir_p( $target_dir );

            $new_filename = $entidad_slug . '.geojson'; 
            $target_file = $target_dir . $new_filename;

            if ( move_uploaded_file( $_FILES['siarhe_file']['tmp_name'], $target_file ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'siarhe_static_assets';
                
                $existe = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $table_name WHERE entidad_slug = %s AND tipo_archivo = 'geojson'", $entidad_slug) );

                $datos = array(
                    'entidad_slug' => $entidad_slug, 'tipo_archivo' => 'geojson', 'ruta_archivo' => 'geojson/' . $new_filename,
                    'anio_reporte' => $anio, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                    'comentarios' => $comentarios, 'es_activo' => 1, 'fecha_subida' => current_time( 'mysql' )
                );
                $formato = array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' );

                if ($existe) { $wpdb->update( $table_name, $datos, array( 'id' => $existe->id ), $formato, array( '%d' ) ); } 
                else { $wpdb->insert( $table_name, $datos, $formato ); }

                wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=geojson&status=success' ) ); exit;
            } else { wp_die( 'Error mover.' ); }
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
            else { 
                $ud = wp_upload_dir(); $filepath = $ud['basedir'] . '/siarhe-data/' . $file->ruta_archivo;
                if ( file_exists( $filepath ) ) unlink( $filepath );
            }
            $wpdb->delete( $table_name, array( 'id' => $file_id ) );
        }
        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=geojson&status=deleted' ) ); exit;
    }

    // -------------------------------------------------------------------------
    // HANDLERS: BASES ESTÁTICAS (CSV)
    // -------------------------------------------------------------------------
    public function handle_static_upload() {
        if ( ! isset( $_POST['siarhe_nonce'] ) || ! wp_verify_nonce( $_POST['siarhe_nonce'], 'siarhe_upload_static_nonce' ) ) wp_die( 'Error de seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        if ( isset( $_FILES['siarhe_file'] ) && $_FILES['siarhe_file']['error'] == 0 ) {
            $entidad_slug = sanitize_text_field( $_POST['entidad_slug'] );
            $anio = intval( $_POST['anio_reporte'] );
            $fecha_corte = sanitize_text_field( $_POST['fecha_corte'] ); 
            $referencia = wp_kses_post( $_POST['referencia'] ); 
            $comentarios = sanitize_textarea_field( $_POST['comentarios'] );

            // Auditoría
            $current_user = wp_get_current_user();
            $editor_name = $current_user->display_name ?: $current_user->user_login;

            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/siarhe-data/static-min/';
            if ( ! file_exists( $target_dir ) ) wp_mkdir_p( $target_dir );

            $new_filename = $entidad_slug . '.csv'; 
            $target_file = $target_dir . $new_filename;

            if ( move_uploaded_file( $_FILES['siarhe_file']['tmp_name'], $target_file ) ) {
                $this->ensure_utf8_csv( $target_file );
                global $wpdb;
                $table_name = $wpdb->prefix . 'siarhe_static_assets';
                
                $existe = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $table_name WHERE entidad_slug = %s AND tipo_archivo = 'static_min'", $entidad_slug) );

                $datos = array( 
                    'entidad_slug' => $entidad_slug, 'tipo_archivo' => 'static_min', 'ruta_archivo' => 'static-min/' . $new_filename, 
                    'anio_reporte' => $anio, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                    'comentarios' => $comentarios, 'es_activo' => 1, 'fecha_subida' => current_time( 'mysql' ),
                    'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql')
                );
                $formato = array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

                if ($existe) { $wpdb->update( $table_name, $datos, array( 'id' => $existe->id ), $formato, array( '%d' ) ); } 
                else { $wpdb->insert( $table_name, $datos, $formato ); }

                wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=static&status=success' ) ); exit;
            } else { wp_die( 'Error mover.' ); }
        } else { wp_die( 'Error carga.' ); }
    }

    public function handle_static_meta_update() {
        if ( ! isset( $_POST['siarhe_meta_nonce'] ) || ! wp_verify_nonce( $_POST['siarhe_meta_nonce'], 'siarhe_update_static_meta_nonce' ) ) wp_die( 'Error de seguridad.' );
        global $wpdb;
        $file_id = intval( $_POST['file_id'] );
        $table_name = $wpdb->prefix . 'siarhe_static_assets';

        // Auditoría
        $current_user = wp_get_current_user();
        $editor_name = $current_user->display_name ?: $current_user->user_login;

        if ( $file_id > 0 ) {
            $wpdb->update( 
                $table_name, 
                array( 'anio_reporte' => intval( $_POST['anio_reporte'] ), 'fecha_corte' => sanitize_text_field( $_POST['fecha_corte'] ), 'referencia_bibliografica' => wp_kses_post( $_POST['referencia'] ), 'comentarios' => sanitize_textarea_field( $_POST['comentarios'] ), 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql') ), 
                array( 'id' => $file_id )
            );

            $tipo = $wpdb->get_var( $wpdb->prepare( "SELECT tipo_archivo FROM $table_name WHERE id = %d", $file_id ) );
            $tab_destino = ($tipo === 'marcador') ? 'marcadores' : 'static';

            wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=' . $tab_destino . '&status=updated' ) );
            exit;
        }
    }

    public function handle_static_delete() {
        $file_id = intval( $_POST['file_id'] );
        check_admin_referer( 'siarhe_delete_static_nonce_' . $file_id );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        global $wpdb;
        $table_name = $wpdb->prefix . 'siarhe_static_assets';
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT ruta_archivo FROM $table_name WHERE id = %d", $file_id ) );
        if ( $file ) {
            $upload_dir = wp_upload_dir();
            $filepath = $upload_dir['basedir'] . '/siarhe-data/' . $file->ruta_archivo;
            if ( file_exists( $filepath ) ) unlink( $filepath );
            $wpdb->delete( $table_name, array( 'id' => $file_id ) );
        }
        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=static&status=deleted' ) ); exit;
    }

    // -------------------------------------------------------------------------
    // HANDLERS: MARCADORES 
    // -------------------------------------------------------------------------
    public function handle_marker_upload() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No tienes permisos.' );
        check_admin_referer( 'siarhe_upload_marker_nonce', 'marker_nonce' );

        // 🌟 DINÁMICO: Leemos los marcadores de la BD en lugar del arreglo fijo
        $archivos_json = get_option( 'siarhe_archivos_marcadores', '' );
        $tipos_marcadores = json_decode( wp_unslash( $archivos_json ), true );

        if (empty($tipos_marcadores)) {
            $tipos_marcadores = [
                'CATETER' => ['filename' => 'clinicas-cateteres.csv'],
                'HERIDAS' => ['filename' => 'clinicas-heridas.csv'],
                'ESTABLECIMIENTOS' => ['filename' => 'establecimientos-salud.csv']
            ];
        }

        $tipo = isset($_POST['marker_type']) ? sanitize_text_field($_POST['marker_type']) : '';
        
        if ( ! array_key_exists($tipo, $tipos_marcadores) ) wp_die('Tipo de marcador no válido.');

        $nombre_final = $tipos_marcadores[$tipo]['filename'];

        if ( isset($_FILES['marker_file']) && !empty($_FILES['marker_file']['name']) ) {
            $anio = intval( $_POST['anio_reporte'] );
            $fecha_corte = sanitize_text_field( $_POST['fecha_corte'] ); 
            $referencia = wp_kses_post( $_POST['referencia'] ); 
            $comentarios = sanitize_textarea_field( $_POST['comentarios'] );
            
            // Auditoría
            $current_user = wp_get_current_user();
            $editor_name = $current_user->display_name ?: $current_user->user_login;

            if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );

            $uploadedfile = $_FILES['marker_file'];
            $upload_overrides = [ 'test_form' => false ];
            $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );

            if ( $movefile && ! isset( $movefile['error'] ) ) {
                $source = $movefile['file'];
                
                $upload_dir = wp_upload_dir();
                $dest_dir = $upload_dir['basedir'] . '/siarhe-data/markers/';
                if (!file_exists($dest_dir)) mkdir($dest_dir, 0755, true);

                $dest_path = $dest_dir . $nombre_final;

                if ( copy($source, $dest_path) ) {
                    $this->ensure_utf8_csv( $dest_path );
                    unlink($source);
                    
                    // GUARDAR EN BD
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'siarhe_static_assets';
                    
                    $existe = $wpdb->get_row( $wpdb->prepare(
                        "SELECT id FROM $table_name WHERE entidad_slug = %s AND tipo_archivo = 'marcador'", 
                        $tipo
                    ));

                    $datos = [
                        'entidad_slug' => $tipo, // Guardamos la KEY de la ranura
                        'tipo_archivo' => 'marcador',
                        'ruta_archivo' => 'markers/' . $nombre_final,
                        'anio_reporte' => $anio,
                        'fecha_corte'  => $fecha_corte,
                        'referencia_bibliografica' => $referencia,
                        'comentarios'  => $comentarios,
                        'es_activo'    => 1,
                        'fecha_subida' => current_time('mysql'),
                        'modificado_por' => $editor_name,
                        'fecha_modificacion' => current_time('mysql')
                    ];
                    $fmt = ['%s','%s','%s','%d','%s','%s','%s','%d','%s','%s','%s'];

                    if ($existe) {
                        $wpdb->update($table_name, $datos, ['id' => $existe->id], $fmt, ['%d']);
                    } else {
                        $wpdb->insert($table_name, $datos, $fmt);
                    }

                    wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=marcadores&status=success' ) );
                    exit;
                } else { wp_die('Error al mover el archivo.'); }
            } else { wp_die( $movefile['error'] ); }
        } else { wp_die('No se seleccionó ningún archivo.'); }
    }
}