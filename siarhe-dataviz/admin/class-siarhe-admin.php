<?php // /admin/class-siarhe-admin.php
if ( ! defined( 'ABSPATH' ) ) exit;

class Siarhe_Admin {

    // Subir este valor cuando maybe_update_database() deba aplicar nuevas columnas.
    const DB_VERSION = '1';

    public function init() {
        // Hooks Visuales
        add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
        add_action( 'admin_init', array( $this, 'redirect_legacy_tabs' ) );
        add_action( 'admin_head', array( $this, 'inject_dynamic_css' ) );

        // Auto-Actualizador SQL BLINDADO
        add_action( 'admin_init', array( $this, 'maybe_update_database' ) );

        // --- HANDLERS: GEOJSON (ESTADOS/MUNICIPIOS) ---
        add_action( 'admin_post_siarhe_upload_geojson', array( $this, 'handle_geojson_upload' ) );
        add_action( 'admin_post_siarhe_update_geojson_meta', array( $this, 'handle_geojson_meta_update' ) );
        add_action( 'admin_post_siarhe_delete_geojson', array( $this, 'handle_geojson_delete' ) );

        // --- HANDLERS: LOCALIDADES (GEO) --- 
        add_action( 'admin_post_siarhe_upload_localidades', array( $this, 'handle_localidades_upload' ) );
        add_action( 'admin_post_siarhe_update_localidades_meta', array( $this, 'handle_localidades_meta_update' ) );
        add_action( 'admin_post_siarhe_delete_localidades', array( $this, 'handle_localidades_delete' ) );

        // --- HANDLERS: BASES ESTÁTICAS Y MARCADORES ---
        add_action( 'admin_post_siarhe_upload_static', array( $this, 'handle_static_upload' ) );
        add_action( 'admin_post_siarhe_update_static_meta', array( $this, 'handle_static_meta_update' ) ); 
        add_action( 'admin_post_siarhe_delete_static', array( $this, 'handle_static_delete' ) );

        // --- HANDLERS: MARCADORES (Subida Específica) ---
        add_action( 'admin_post_siarhe_upload_marker', array( $this, 'handle_marker_upload' ) );
        add_action( 'admin_post_siarhe_marker_var_add', array( $this, 'handle_marker_var_add' ) );
        add_action( 'admin_post_siarhe_marker_var_delete', array( $this, 'handle_marker_var_delete' ) );
        add_action( 'admin_post_siarhe_marker_var_reset', array( $this, 'handle_marker_var_reset' ) );

        // Subidas con barra de progreso (XHR): la redireccion final se devuelve como JSON
        add_filter( 'wp_redirect', array( $this, 'redirect_as_json_for_xhr' ), 10, 1 );

        // --- VERSIONES (copias de seguridad de los archivos) ---
        add_action( 'admin_post_siarhe_version_restore', array( $this, 'handle_version_restore' ) );
        add_action( 'admin_post_siarhe_version_delete', array( $this, 'handle_version_delete' ) );

        // --- PLANTILLAS Y DICCIONARIO DE VARIABLES (descargas) ---
        add_action( 'admin_post_siarhe_download_columns', array( $this, 'handle_download_columns' ) );
        add_action( 'add_option_' . Siarhe_Versions::OPT_KEEP, array( 'Siarhe_Versions', 'on_keep_changed' ) );
        add_action( 'update_option_' . Siarhe_Versions::OPT_KEEP, array( 'Siarhe_Versions', 'on_keep_changed' ) );
    }

    public function handle_version_restore() {
        $this->version_action( 'restore' );
    }

    /** URL de descarga de una plantilla o del diccionario (con nonce). */
    public static function columns_url( $kind, $what, $extra = array() ) {
        $args = array_merge( array( 'action' => 'siarhe_download_columns', 'kind' => $kind, 'what' => $what ), $extra );
        $args['_wpnonce'] = wp_create_nonce( 'siarhe_columns' );
        return add_query_arg( $args, admin_url( 'admin-post.php' ) );
    }

    /** Botones de descarga de plantillas y diccionario ($kind: 'static' o 'marker'). */
    public static function render_columns_downloads( $kind ) {
        $dict_ext = Siarhe_Xlsx::available() ? '.xlsx' : '.csv';
        echo '<div class="siarhe-downloads"><span class="siarhe-downloads__label">Plantillas y diccionario';
        self::help( $kind === 'static'
            ? 'Descarga un CSV con las columnas que lee el mapa (identificador, nombre, población y métricas nativas) y una fila de ejemplo. Tus métricas propias se agregan como columnas adicionales. El diccionario explica cada columna.'
            : 'Descarga un CSV con las columnas que necesita el mapa y una fila de ejemplo. Agrega después las columnas de valor que use la configuración de cada marcador. El diccionario explica cada columna.' );
        echo '</span>';
        if ( $kind === 'static' ) {
            echo '<a class="button" href="' . esc_url( self::columns_url( 'static', 'plantilla', array( 'variante' => 'nacional' ) ) ) . '"><span class="dashicons dashicons-download"></span> Plantilla nacional (CSV)</a>';
            echo '<a class="button" href="' . esc_url( self::columns_url( 'static', 'plantilla', array( 'variante' => 'municipal' ) ) ) . '"><span class="dashicons dashicons-download"></span> Plantilla municipal (CSV)</a>';
            echo '<a class="button" href="' . esc_url( self::columns_url( 'static', 'diccionario' ) ) . '"><span class="dashicons dashicons-media-spreadsheet"></span> Diccionario de variables (' . esc_html( $dict_ext ) . ')</a>';
        } else {
            echo '<a class="button" href="' . esc_url( self::columns_url( 'marker', 'plantilla' ) ) . '"><span class="dashicons dashicons-download"></span> Plantilla de marcadores (CSV)</a>';
            echo '<a class="button" href="' . esc_url( self::columns_url( 'marker', 'diccionario' ) ) . '"><span class="dashicons dashicons-media-spreadsheet"></span> Diccionario de variables (' . esc_html( $dict_ext ) . ')</a>';
        }
        echo '</div>';
    }

    public function handle_download_columns() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No tienes permisos.' );
        check_admin_referer( 'siarhe_columns' );

        $kind = isset( $_GET['kind'] ) ? sanitize_key( $_GET['kind'] ) : '';
        $what = isset( $_GET['what'] ) ? sanitize_key( $_GET['what'] ) : '';
        if ( ! in_array( $kind, array( 'static', 'marker' ), true ) || ! in_array( $what, array( 'plantilla', 'diccionario' ), true ) ) wp_die( 'Solicitud no válida.' );

        if ( $kind === 'static' ) {
            $variante = ( isset( $_GET['variante'] ) && $_GET['variante'] === 'municipal' ) ? 'municipal' : 'nacional';
            if ( $what === 'plantilla' ) {
                $cols = Siarhe_Columns::static_columns( $variante );
                Siarhe_Columns::send_csv( 'plantilla-base-estatica-' . $variante . '.csv', array( wp_list_pluck( $cols, 'name' ), Siarhe_Columns::example_row( $cols ) ) );
            }
            $sheets = array(
                'Nacional'  => Siarhe_Columns::dictionary_rows( Siarhe_Columns::static_columns( 'nacional' ) ),
                'Municipal' => Siarhe_Columns::dictionary_rows( Siarhe_Columns::static_columns( 'municipal' ) ),
                'Notas'     => Siarhe_Columns::notas(),
            );
            $csv_name = 'diccionario-base-estatica.csv';
            $csv_rows = array_merge( array( array( 'NACIONAL' ) ), $sheets['Nacional'], array( array(), array( 'MUNICIPAL' ) ), $sheets['Municipal'], array( array() ), $sheets['Notas'] );
        } else {
            $cols = Siarhe_Columns::marker_columns();
            if ( $what === 'plantilla' ) {
                Siarhe_Columns::send_csv( 'plantilla-marcadores.csv', array( wp_list_pluck( $cols, 'name' ), Siarhe_Columns::example_row( $cols ) ) );
            }
            $sheets = array(
                'Marcadores' => Siarhe_Columns::dictionary_rows( $cols ),
                'Notas'      => Siarhe_Columns::notas(),
            );
            $csv_name = 'diccionario-marcadores.csv';
            $csv_rows = array_merge( $sheets['Marcadores'], array( array() ), $sheets['Notas'] );
        }

        // Diccionario: .xlsx si el servidor tiene ZipArchive; si no, CSV
        if ( ! Siarhe_Xlsx::send( preg_replace( '/\.csv$/', '.xlsx', $csv_name ), $sheets ) ) {
            Siarhe_Columns::send_csv( $csv_name, $csv_rows );
        }
    }

    public function handle_version_delete() {
        $this->version_action( 'delete' );
    }

    private function version_action( $what ) {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No tienes permisos.' );
        $rel = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';
        check_admin_referer( 'siarhe_version_' . $rel );

        $res = ( $what === 'restore' ) ? Siarhe_Versions::restore( $rel ) : Siarhe_Versions::delete( $rel );
        if ( $res !== true ) {
            set_transient( 'siarhe_error_' . get_current_user_id(), (string) $res, 2 * MINUTE_IN_SECONDS );
            $status = 'error';
        } else {
            $status = ( $what === 'restore' ) ? 'restored' : 'vdeleted';
        }
        wp_safe_redirect( admin_url( 'admin.php?page=siarhe-settings&tab=versiones&status=' . $status ) );
        exit;
    }

    /**
     * Los formularios de carga se envian por XHR (siarhe_xhr=1) para mostrar progreso.
     * XHR seguiria la redireccion y consumiria los avisos de una sola vez (transients),
     * asi que se responde con la URL destino y el navegador navega a ella.
     */
    public function redirect_as_json_for_xhr( $location ) {
        $action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
        if ( empty( $_POST['siarhe_xhr'] ) || strpos( $action, 'siarhe_' ) !== 0 ) {
            return $location;
        }
        wp_send_json( array( 'redirect' => esc_url_raw( $location ) ) );
    }

    // 🌟 AQUÍ ESTÁ LA CORRECCIÓN: Verifica columna por columna
    public function maybe_update_database() {
        // Solo se revisa el esquema cuando cambia la version de BD (evita SHOW TABLES/DESCRIBE en cada carga del admin)
        if ( get_option( 'siarhe_db_version' ) === self::DB_VERSION ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'siarhe_static_assets';

        // Verificar si la tabla existe primero
        if($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
            $cols = $wpdb->get_col("DESCRIBE `$table`", 0);

            if (!in_array('subido_por', $cols)) {
                $wpdb->query("ALTER TABLE `$table` ADD `subido_por` varchar(100) DEFAULT NULL");
            }
            if (!in_array('modificado_por', $cols)) {
                $wpdb->query("ALTER TABLE `$table` ADD `modificado_por` varchar(100) DEFAULT NULL");
            }
            if (!in_array('fecha_modificacion', $cols)) {
                $wpdb->query("ALTER TABLE `$table` ADD `fecha_modificacion` datetime DEFAULT NULL");
            }
            update_option( 'siarhe_db_version', self::DB_VERSION );
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
        add_submenu_page( 'siarhe-dataviz', 'Personalizar', 'Personalizar', 'manage_options', 'siarhe-customize', array( $this, 'display_customize' ) );
        add_submenu_page( 'siarhe-dataviz', 'Configuración', 'Configuración', 'manage_options', 'siarhe-settings', array( $this, 'display_settings' ) );
    }

    public function enqueue_styles( $hook ) {
        if ( strpos( $hook, 'siarhe' ) === false ) return; // Solo en las pantallas del plugin
        wp_enqueue_style( 'siarhe-admin-css', SIARHE_URL . 'admin/css/siarhe-admin.css', array(), SIARHE_VERSION, 'all' );
        wp_enqueue_style( 'siarhe-admin-ui', SIARHE_URL . 'admin/css/siarhe-admin-ui.css', array( 'siarhe-admin-css' ), SIARHE_VERSION, 'all' );
        $deps = array( 'jquery' );
        if ( strpos( $hook, 'siarhe-settings' ) !== false || strpos( $hook, 'siarhe-customize' ) !== false ) {
            wp_enqueue_style( 'wp-color-picker' );
            $deps[] = 'wp-color-picker';
        }
        wp_enqueue_script( 'siarhe-admin-js', SIARHE_URL . 'admin/js/siarhe-admin.js', $deps, SIARHE_VERSION, true );
    }

    public function inject_dynamic_css() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'siarhe' ) === false ) return;
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
    // Una misma plantilla atiende las dos pantallas; $siarhe_screen_page indica cual
    public function display_customize() { $siarhe_screen_page = 'siarhe-customize'; include SIARHE_PATH . 'admin/partials/siarhe-settings.php'; }
    public function display_settings()  { $siarhe_screen_page = 'siarhe-settings';  include SIARHE_PATH . 'admin/partials/siarhe-settings.php'; }

    /**
     * Tema Admin y Mapa & Colores ahora viven en Personalizar: los enlaces guardados a
     * ?page=siarhe-settings&tab=tema|mapa se redirigen a la pantalla nueva.
     */
    public function redirect_legacy_tabs() {
        if ( ! isset( $_GET['page'], $_GET['tab'] ) || $_GET['page'] !== 'siarhe-settings' ) return;
        $tab = sanitize_key( $_GET['tab'] );
        if ( in_array( $tab, array( 'tema', 'mapa' ), true ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=siarhe-customize&tab=' . $tab ) );
            exit;
        }
    }

    public function register_plugin_settings() {
        $arr  = array( 'sanitize_callback' => array( $this, 'sanitize_array_option' ) );
        $json = array( 'sanitize_callback' => array( $this, 'sanitize_json_option' ) );
        register_setting( 'siarhe_links_group', 'siarhe_links_map', $arr );
        register_setting( 'siarhe_theme_group', 'siarhe_theme_options', $arr );
        register_setting( 'siarhe_map_group', 'siarhe_map_options', $arr );
        register_setting( 'siarhe_metricas_group', 'siarhe_metricas_config', $json );
        register_setting( 'siarhe_tooltip_group', 'siarhe_tooltip_config', $arr );
        register_setting( 'siarhe_marcadores_group', 'siarhe_marcadores_config', $json );
        register_setting( 'siarhe_versions_group', Siarhe_Versions::OPT_KEEP, array(
            'sanitize_callback' => array( 'Siarhe_Versions', 'sanitize_keep' ),
            'default'           => Siarhe_Versions::DEFAULT_KEEP,
        ) );
        register_setting( 'siarhe_versions_group', Siarhe_Versions::OPT_GZ, array(
            'sanitize_callback' => array( 'Siarhe_Versions', 'sanitize_bool' ),
            'default'           => '0',
        ) );
    }

    /**
     * Sanitiza recursivamente un array de opciones: limpia los strings y conserva
     * claves, booleanos y numeros. Cualquier valor no-array se devuelve vacio.
     */
    public function sanitize_array_option( $value ) {
        if ( ! is_array( $value ) ) return array();
        $clean = array();
        foreach ( $value as $k => $v ) {
            if ( is_array( $v ) )       $clean[ $k ] = $this->sanitize_array_option( $v );
            elseif ( is_string( $v ) )  $clean[ $k ] = sanitize_text_field( $v );
            elseif ( is_scalar( $v ) )  $clean[ $k ] = $v;
        }
        return $clean;
    }

    /**
     * Las opciones JSON se guardan tal cual llegan, pero solo si son JSON valido;
     * de lo contrario se conserva el valor anterior.
     */
    public function sanitize_json_option( $value ) {
        $raw = is_string( $value ) ? $value : '';
        if ( $raw === '' ) return '';
        if ( json_decode( $raw ) !== null || json_decode( wp_unslash( $raw ) ) !== null ) return $raw;
        add_settings_error( 'siarhe', 'siarhe_json', 'La configuración recibida no es JSON válido; se conservó el valor anterior.' );
        return get_option( str_replace( 'sanitize_option_', '', current_filter() ) );
    }

    /**
     * Normaliza el slug recibido por POST para usarlo como nombre de archivo.
     * sanitize_title elimina '/', '\' y '.', evitando path traversal.
     */
    private function sanitize_slug( $raw ) {
        $slug = sanitize_title( wp_unslash( $raw ) );
        if ( $slug === '' ) $this->upload_fail( 'Entidad no válida.' );
        return $slug;
    }

    /**
     * Error de subida: en lugar de una pagina en blanco en admin-post.php, se guarda el
     * mensaje y se regresa a la pestana de origen, donde se muestra como aviso y popup.
     */
    private function upload_fail( $message ) {
        $tabs   = array(
            'siarhe_upload_geojson'     => 'geojson',
            'siarhe_upload_localidades' => 'localidades',
            'siarhe_upload_static'      => 'static',
            'siarhe_upload_marker'      => 'marcadores',
        );
        $action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
        $tab    = isset( $tabs[ $action ] ) ? $tabs[ $action ] : 'geojson';
        $clean  = trim( wp_strip_all_tags( str_replace( array( '<br>', '<br/>' ), ' ', (string) $message ) ) );
        set_transient( 'siarhe_error_' . get_current_user_id(), $clean, 2 * MINUTE_IN_SECONDS );
        wp_safe_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=' . $tab . '&status=error' ) );
        exit;
    }

    /**
     * Valida un archivo subido: extension permitida y, para GeoJSON, JSON bien formado.
     * Aborta con wp_die si no cumple.
     */
    private function validate_upload( $file, $allowed_ext, $check_geojson = false ) {
        $name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
        $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, $allowed_ext, true ) ) {
            $this->upload_fail( 'Tipo de archivo no permitido. Extensiones válidas: ' . esc_html( implode( ', ', $allowed_ext ) ) . '.' );
        }
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            $this->upload_fail( 'Archivo de carga no válido.' );
        }
        if ( $check_geojson ) {
            $data = json_decode( file_get_contents( $file['tmp_name'] ), true );
            if ( ! is_array( $data ) || empty( $data['type'] ) ) {
                $this->upload_fail( 'El archivo no es un GeoJSON válido.' );
            }
            return $data; // ya decodificado: evita leer y parsear el archivo dos veces
        }
        return null;
    }

    /**
     * Hoja de estilos de Select2: CDN primero; si no carga, el atributo onerror pasa a la copia local.
     */
    public static function render_select2_css() {
        $cdn   = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
        $local = SIARHE_URL . 'admin/libs/select2/select2.min.css?ver=4.1.0-rc.0';
        printf(
            '<link href="%1$s" rel="stylesheet" onerror="this.onerror=null;this.href=\'%2$s\';" />' . "\n",
            esc_url( $cdn ),
            esc_url( $local )
        );
    }

    /**
     * Script de Select2: CDN primero; si no esta disponible (o no definio $.fn.select2),
     * se escribe de forma sincrona la copia local antes de que corra el codigo que lo usa.
     */
    public static function render_select2_js() {
        $cdn   = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
        $local = SIARHE_URL . 'admin/libs/select2/select2.min.js?ver=4.1.0-rc.0';
        printf( '<script src="%s"></script>' . "\n", esc_url( $cdn ) );
        printf(
            '<script>window.jQuery && jQuery.fn.select2 || document.write(\'<script src="%s"><\/script>\');</script>' . "\n",
            esc_url( $local )
        );
    }

    // -------------------------------------------------------------------------
    // VALIDACION DE CONTENIDO + VERSIONES ANTERIORES
    // -------------------------------------------------------------------------

    /** Guarda avisos no bloqueantes para mostrarlos en la pestana tras el redirect. */
    private function flash_notice( $messages ) {
        if ( empty( $messages ) || ! is_array( $messages ) ) return;
        set_transient( 'siarhe_notice_' . get_current_user_id(), array_values( $messages ), 2 * MINUTE_IN_SECONDS );
    }

    /** Imprime (y consume) los avisos pendientes del usuario actual. */
    /**
     * Imprime un icono "?" de ayuda; el texto se muestra en un globo (siarhe-admin.js).
     */
    public static function help( $text ) {
        echo '<button type="button" class="siarhe-help" aria-label="Ayuda" data-help="' . esc_attr( $text ) . '">?</button>';
    }

    public static function render_flash_notice() {
        $err_key = 'siarhe_error_' . get_current_user_id();
        $error   = get_transient( $err_key );
        if ( ! empty( $error ) && is_string( $error ) ) {
            delete_transient( $err_key );
            echo '<div class="notice notice-error is-dismissible"><p><strong>No se subió el archivo.</strong> ' . esc_html( $error ) . '</p></div>';
            echo '<script>document.addEventListener("DOMContentLoaded",function(){if(window.SiarheAdmin)SiarheAdmin.alert(' . wp_json_encode( $error ) . ',"No se subió el archivo");});</script>';
        }

        $key      = 'siarhe_notice_' . get_current_user_id();
        $messages = get_transient( $key );
        if ( empty( $messages ) || ! is_array( $messages ) ) return;
        delete_transient( $key );

        echo '<div class="notice notice-warning is-dismissible"><p><strong>El archivo se cargó, pero revisa lo siguiente:</strong></p><ul style="list-style:disc; margin-left:20px;">';
        foreach ( $messages as $message ) {
            echo '<li>' . esc_html( $message ) . '</li>';
        }
        echo '</ul></div>';
    }

    /**
     * Copia la version vigente a _versions/ antes de sobrescribirla y conserva las 3 mas recientes.
     * Permite recuperar el archivo anterior si la nueva carga resulta equivocada.
     */
    private function archive_previous_version( $file_path ) {
        Siarhe_Versions::archive( $file_path );
    }

    /** Primer par [lon, lat] dentro de un arreglo de coordenadas GeoJSON de cualquier profundidad. */
    private function first_coordinate( $coords ) {
        while ( is_array( $coords ) && isset( $coords[0] ) && is_array( $coords[0] ) ) {
            $coords = $coords[0];
        }
        if ( is_array( $coords ) && isset( $coords[0], $coords[1] ) && is_numeric( $coords[0] ) && is_numeric( $coords[1] ) ) {
            return array( (float) $coords[0], (float) $coords[1] );
        }
        return null;
    }

    /**
     * Valida la estructura de un GeoJSON. Los problemas graves detienen la carga (wp_die);
     * los demas se devuelven como avisos.
     * $is_base: true para el mapa base (necesita la clave de union con el CSV), false para localidades.
     */
    private function validate_geojson_data( $data, $slug, $is_base ) {
        $warnings = array();

        if ( ! is_array( $data ) || ! isset( $data['type'] ) || $data['type'] !== 'FeatureCollection'
            || empty( $data['features'] ) || ! is_array( $data['features'] ) ) {
            $this->upload_fail( 'El GeoJSON debe ser un FeatureCollection con al menos un elemento en "features".' );
        }

        $national  = ( $slug === 'republica-mexicana' );
        $key_names = $national ? array( 'CVE_ENT', 'cve_ent', 'ID', 'id' ) : array( 'CVE_MUN', 'cve_mun', 'ID', 'id' );
        $total     = count( $data['features'] );
        $no_geom   = 0;
        $no_key    = 0;
        $checked   = false;

        foreach ( $data['features'] as $feature ) {
            $geom = ( is_array( $feature ) && isset( $feature['geometry'] ) ) ? $feature['geometry'] : null;
            if ( ! is_array( $geom ) || empty( $geom['coordinates'] ) ) {
                $no_geom++;
                continue;
            }

            if ( ! $checked ) {
                $checked = true;
                $pt = $this->first_coordinate( $geom['coordinates'] );
                if ( $pt ) {
                    if ( abs( $pt[0] ) > 180 || abs( $pt[1] ) > 90 ) {
                        $this->upload_fail( 'Las coordenadas del GeoJSON no están en longitud/latitud (WGS84). Reproyéctalo a EPSG:4326 antes de subirlo.' );
                    }
                    if ( $slug !== 'extranjero' && ( $pt[0] < -120 || $pt[0] > -84 || $pt[1] < 14 || $pt[1] > 34 ) ) {
                        $warnings[] = 'La primera geometría queda fuera del territorio mexicano (lon ' . round( $pt[0], 2 ) . ', lat ' . round( $pt[1], 2 ) . '). Verifica que sea el archivo correcto.';
                    }
                }
            }

            if ( $is_base ) {
                $props = ( isset( $feature['properties'] ) && is_array( $feature['properties'] ) ) ? $feature['properties'] : array();
                $found = false;
                foreach ( $key_names as $k ) {
                    if ( isset( $props[ $k ] ) && $props[ $k ] !== '' ) { $found = true; break; }
                }
                if ( ! $found ) $no_key++;
            }
        }

        if ( $no_geom === $total ) {
            $this->upload_fail( 'Ningún elemento del GeoJSON tiene geometría.' );
        }
        if ( $no_geom > 0 ) {
            $warnings[] = $no_geom . ' elemento(s) del GeoJSON no tienen geometría y no se dibujarán.';
        }
        if ( $no_key > 0 ) {
            $warnings[] = $no_key . ' elemento(s) no tienen la propiedad ' . $key_names[0] . ' (o ID); sin ella no se pueden colorear con el CSV.';
        }

        return $warnings;
    }

    /**
     * Valida un CSV leyendo solo lo necesario (encabezado + filas en streaming).
     * $kind: 'static' (base estatica) o 'marker' (marcadores con coordenadas).
     * Los problemas graves detienen la carga; los demas se devuelven como avisos.
     */
    private function validate_csv_file( $tmp_path, $kind, $slug = '' ) {
        $warnings = array();

        $fh = ( ! empty( $tmp_path ) && is_readable( $tmp_path ) ) ? fopen( $tmp_path, 'r' ) : false;
        if ( ! $fh ) $this->upload_fail( 'No se pudo leer el CSV para validarlo.' );

        $first = fgets( $fh );
        if ( $first === false || trim( $first ) === '' ) {
            fclose( $fh );
            $this->upload_fail( 'El CSV está vacío.' );
        }
        $first = preg_replace( '/^\xEF\xBB\xBF/', '', $first ); // BOM

        if ( substr_count( $first, ',' ) === 0 && ( substr_count( $first, ';' ) > 0 || substr_count( $first, "\t" ) > 0 ) ) {
            fclose( $fh );
            $this->upload_fail( 'El CSV parece usar punto y coma o tabulador como separador. Guárdalo con comas (CSV UTF-8) antes de subirlo.' );
        }

        $raw_header = array_map( 'trim', str_getcsv( $first ) );
        $header     = array_map( 'strtolower', $raw_header );
        $ncols      = count( $raw_header );
        $rows       = 0;

        if ( $kind === 'static' ) {
            // Nombres exactos que lee siarhe-core.js (processCSV)
            $national  = ( $slug === 'republica-mexicana' );
            $id_names  = $national ? array( 'CVE_ENT', 'cve_ent', 'mapa', 'id' ) : array( 'CVE_MUN', 'cve_mun', 'CVE_ENT', 'cve_ent', 'id' );
            $nom_names = array( 'estado', 'Entidad', 'municipio' );

            $id_idx = false;
            foreach ( $id_names as $n ) {
                $i = array_search( $n, $raw_header, true );
                if ( $i !== false ) { $id_idx = $i; break; }
            }

            $missing = array();
            if ( $id_idx === false )                         $missing[] = 'identificador (' . implode( ', ', $id_names ) . ')';
            if ( ! array_intersect( $nom_names, $raw_header ) ) $missing[] = 'nombre (' . implode( ', ', $nom_names ) . ')';
            $has_pob = false;
            foreach ( $header as $h ) {
                if ( strpos( $h, 'pob' ) === 0 ) { $has_pob = true; break; }
            }
            if ( ! $has_pob ) $missing[] = 'población (una columna que empiece con "pob")';
            foreach ( array( 'tasa_total', 'enfermeras_total' ) as $must ) {
                if ( ! in_array( $must, $raw_header, true ) ) $missing[] = $must . ' (sin ella el mapa no muestra datos)';
            }

            if ( $missing ) {
                fclose( $fh );
                $this->upload_fail( 'El CSV no tiene las columnas requeridas: ' . esc_html( implode( '; ', $missing ) ) . '.' );
            }

            // Metricas configuradas en el admin: cada clave es el nombre de una columna del CSV
            $metric_idx = array();
            $missing_m  = array();
            $cfg = json_decode( wp_unslash( (string) get_option( 'siarhe_metricas_config', '' ) ), true );
            if ( is_array( $cfg ) ) {
                foreach ( array_keys( $cfg ) as $k ) {
                    if ( $k === 'poblacion' ) continue;
                    $i = array_search( $k, $raw_header, true );
                    if ( $i === false ) $missing_m[] = $k; else $metric_idx[] = $i;
                }
            }
            if ( $missing_m ) {
                $warnings[] = 'Métricas configuradas sin columna en el CSV (se mostrarán sin datos): ' . implode( ', ', array_slice( $missing_m, 0, 8 ) ) . ( count( $missing_m ) > 8 ? '…' : '' ) . '.';
            }

            $bad_cols = 0; $non_num = 0; $dups = 0; $ids = array();
            while ( ( $row = fgetcsv( $fh ) ) !== false ) {
                if ( $row === array( null ) ) continue; // linea vacia
                $rows++;
                if ( count( $row ) !== $ncols ) $bad_cols++;

                if ( isset( $row[ $id_idx ] ) && $row[ $id_idx ] !== '' ) {
                    if ( isset( $ids[ $row[ $id_idx ] ] ) ) $dups++; else $ids[ $row[ $id_idx ] ] = true;
                }
                foreach ( $metric_idx as $mi ) {
                    if ( ! isset( $row[ $mi ] ) ) continue;
                    $v = trim( $row[ $mi ] );
                    if ( $v !== '' && strtoupper( $v ) !== 'S/D' && ! is_numeric( str_replace( array( ',', ' ' ), '', $v ) ) ) $non_num++;
                }
            }

            if ( $bad_cols > 0 ) $warnings[] = $bad_cols . ' fila(s) tienen distinta cantidad de columnas que el encabezado.';
            if ( $dups > 0 )     $warnings[] = $dups . ' fila(s) repiten el identificador (' . $raw_header[ $id_idx ] . '); solo se usará la última.';
            if ( $non_num > 0 )  $warnings[] = $non_num . ' valor(es) de métricas no son numéricos (ni vacíos ni S/D) y se tratarán como sin dato.';
        } else {
            $lat_i = false; $lon_i = false;
            foreach ( $header as $i => $h ) {
                if ( $lat_i === false && in_array( $h, array( 'latitud', 'lat' ), true ) ) $lat_i = $i;
                if ( $lon_i === false && in_array( $h, array( 'longitud', 'lon' ), true ) ) $lon_i = $i;
            }
            if ( $lat_i === false || $lon_i === false ) {
                fclose( $fh );
                $this->upload_fail( 'El CSV de marcadores necesita las columnas de coordenadas: Latitud/Longitud (o lat/lon).' );
            }

            $invalid = 0;
            while ( ( $row = fgetcsv( $fh ) ) !== false && $rows < 300000 ) {
                if ( $row === array( null ) ) continue;
                $rows++;
                $lat = isset( $row[ $lat_i ] ) ? (float) $row[ $lat_i ] : 0;
                $lon = isset( $row[ $lon_i ] ) ? (float) $row[ $lon_i ] : 0;
                if ( $lat < 14 || $lat > 33 || $lon < -119 || $lon > -86 ) $invalid++;
            }
            if ( $rows > 0 && ( $invalid / $rows ) > 0.2 ) {
                $warnings[] = round( ( $invalid / $rows ) * 100 ) . '% de las filas tienen coordenadas vacías o fuera de México y no se dibujarán.';
            }
        }

        fclose( $fh );
        if ( $rows === 0 ) $this->upload_fail( 'El CSV solo tiene encabezado: no hay filas de datos.' );

        return $warnings;
    }

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
    // HANDLERS: GEOJSON (Mapas base)
    // -------------------------------------------------------------------------
    public function handle_geojson_upload() { 
        if ( ! isset( $_POST['siarhe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['siarhe_nonce'] ) ), 'siarhe_upload_nonce' ) ) wp_die( 'Error de seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        set_time_limit(0);
        wp_raise_memory_limit('admin');

        if ( isset( $_FILES['siarhe_file'] ) && $_FILES['siarhe_file']['error'] == 0 ) {
            $entidad_slug = $this->sanitize_slug( isset( $_POST['entidad_slug'] ) ? $_POST['entidad_slug'] : '' );
            $geo_data = $this->validate_upload( $_FILES['siarhe_file'], array( 'geojson', 'json' ), true );
            $this->flash_notice( $this->validate_geojson_data( $geo_data, $entidad_slug, true ) );
            $anio = intval( $_POST['anio_reporte'] );
            $fecha_corte = sanitize_text_field( wp_unslash( $_POST['fecha_corte'] ) ); 
            $referencia = wp_kses_post( wp_unslash( $_POST['referencia'] ) ); 
            $comentarios = sanitize_textarea_field( wp_unslash( $_POST['comentarios'] ) );
            
            $current_user = wp_get_current_user();
            $editor_name = $current_user->display_name ?: $current_user->user_login;

            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/siarhe-data/geojson/';
            if ( ! file_exists( $target_dir ) ) wp_mkdir_p( $target_dir );

            $new_filename = $entidad_slug . '.geojson'; 
            $target_file = $target_dir . $new_filename;

            $this->archive_previous_version( $target_file );

            if ( move_uploaded_file( $_FILES['siarhe_file']['tmp_name'], $target_file ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'siarhe_static_assets';
                
                $existe = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $table_name WHERE entidad_slug = %s AND tipo_archivo = 'geojson'", $entidad_slug) );

                if ($existe) {
                    $datos = array(
                        'ruta_archivo' => 'geojson/' . $new_filename,
                        'anio_reporte' => $anio, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                        'comentarios' => $comentarios, 'es_activo' => 1,
                        'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql')
                    );
                    $formato = array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' );
                    $wpdb->update( $table_name, $datos, array( 'id' => $existe->id ), $formato, array( '%d' ) ); 
                } else {
                    $datos = array(
                        'entidad_slug' => $entidad_slug, 'tipo_archivo' => 'geojson', 'ruta_archivo' => 'geojson/' . $new_filename,
                        'anio_reporte' => $anio, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                        'comentarios' => $comentarios, 'es_activo' => 1, 'fecha_subida' => current_time( 'mysql' ),
                        'subido_por' => $editor_name, 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql')
                    );
                    $formato = array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );
                    $wpdb->insert( $table_name, $datos, $formato ); 
                }

                wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=geojson&status=success' ) ); exit;
            } else { 
                $this->upload_fail( '<strong>Error al mover el archivo al servidor.</strong><br>Verifica los permisos de escritura (755) de la carpeta.' ); 
            }
        } else { 
            $error_code = isset($_FILES['siarhe_file']['error']) ? $_FILES['siarhe_file']['error'] : 'Desconocido';
            $max_size = ini_get('upload_max_filesize');
            $this->upload_fail( '<strong>El archivo no pudo ser procesado o fue rechazado por el servidor.</strong><br>Código de error PHP: ' . $error_code . '<br>Tu servidor actualmente solo permite subir archivos de hasta: <strong>' . $max_size . '</strong>.' ); 
        }
    }

    public function handle_geojson_meta_update() { 
        if ( ! isset( $_POST['siarhe_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['siarhe_meta_nonce'] ) ), 'siarhe_update_meta_nonce' ) ) wp_die( 'Error seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        global $wpdb;
        $file_id = ( isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0 );
        $current_user = wp_get_current_user();
        $editor_name = $current_user->display_name ?: $current_user->user_login;

        if ( $file_id > 0 ) {
            $wpdb->update( $wpdb->prefix . 'siarhe_static_assets', 
                array( 'anio_reporte' => intval( $_POST['anio_reporte'] ), 'fecha_corte' => sanitize_text_field( wp_unslash( $_POST['fecha_corte'] ) ), 'referencia_bibliografica' => wp_kses_post( wp_unslash( $_POST['referencia'] ) ), 'comentarios' => sanitize_textarea_field( wp_unslash( $_POST['comentarios'] ) ), 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql') ), 
                array( 'id' => $file_id ) 
            );
            wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=geojson&status=updated' ) ); exit;
        }
    }

    public function handle_geojson_delete() { 
        $file_id = ( isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0 );
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
    // HANDLERS: LOCALIDADES GEOJSON
    // -------------------------------------------------------------------------
    public function handle_localidades_upload() { 
        if ( ! isset( $_POST['siarhe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['siarhe_nonce'] ) ), 'siarhe_upload_localidades_nonce' ) ) wp_die( 'Error de seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        set_time_limit(0);
        wp_raise_memory_limit('admin');

        if ( isset( $_FILES['siarhe_file'] ) && $_FILES['siarhe_file']['error'] == 0 ) {
            $entidad_slug = $this->sanitize_slug( isset( $_POST['entidad_slug'] ) ? $_POST['entidad_slug'] : '' );
            $geo_data = $this->validate_upload( $_FILES['siarhe_file'], array( 'geojson', 'json' ), true );
            $this->flash_notice( $this->validate_geojson_data( $geo_data, $entidad_slug, false ) );
            $anio = intval( $_POST['anio_reporte'] );
            $fecha_corte = sanitize_text_field( wp_unslash( $_POST['fecha_corte'] ) ); 
            $referencia = wp_kses_post( wp_unslash( $_POST['referencia'] ) ); 
            $comentarios = sanitize_textarea_field( wp_unslash( $_POST['comentarios'] ) );
            
            $current_user = wp_get_current_user();
            $editor_name = $current_user->display_name ?: $current_user->user_login;

            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/siarhe-data/localidades/';
            if ( ! file_exists( $target_dir ) ) wp_mkdir_p( $target_dir );

            $new_filename = $entidad_slug . '.geojson'; 
            $target_file = $target_dir . $new_filename;

            $this->archive_previous_version( $target_file );

            if ( move_uploaded_file( $_FILES['siarhe_file']['tmp_name'], $target_file ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'siarhe_static_assets';
                
                $existe = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $table_name WHERE entidad_slug = %s AND tipo_archivo = 'localidades_geojson'", $entidad_slug) );

                if ($existe) {
                    $datos = array(
                        'ruta_archivo' => 'localidades/' . $new_filename,
                        'anio_reporte' => $anio, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                        'comentarios' => $comentarios, 'es_activo' => 1,
                        'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql')
                    );
                    $formato = array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' );
                    $wpdb->update( $table_name, $datos, array( 'id' => $existe->id ), $formato, array( '%d' ) ); 
                } else {
                    $datos = array(
                        'entidad_slug' => $entidad_slug, 'tipo_archivo' => 'localidades_geojson', 'ruta_archivo' => 'localidades/' . $new_filename,
                        'anio_reporte' => $anio, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                        'comentarios' => $comentarios, 'es_activo' => 1, 'fecha_subida' => current_time( 'mysql' ),
                        'subido_por' => $editor_name, 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql')
                    );
                    $formato = array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );
                    $wpdb->insert( $table_name, $datos, $formato ); 
                }

                wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=localidades&status=success' ) ); exit;
            } else { $this->upload_fail( 'Error al mover el archivo.' ); }
        } else { $this->upload_fail( 'Error de carga en servidor.' ); }
    }

    public function handle_localidades_meta_update() { 
        if ( ! isset( $_POST['siarhe_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['siarhe_meta_nonce'] ) ), 'siarhe_update_localidades_meta_nonce' ) ) wp_die( 'Error seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        global $wpdb;
        $file_id = ( isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0 );
        $current_user = wp_get_current_user();
        $editor_name = $current_user->display_name ?: $current_user->user_login;

        if ( $file_id > 0 ) {
            $wpdb->update( $wpdb->prefix . 'siarhe_static_assets', 
                array( 'anio_reporte' => intval( $_POST['anio_reporte'] ), 'fecha_corte' => sanitize_text_field( wp_unslash( $_POST['fecha_corte'] ) ), 'referencia_bibliografica' => wp_kses_post( wp_unslash( $_POST['referencia'] ) ), 'comentarios' => sanitize_textarea_field( wp_unslash( $_POST['comentarios'] ) ), 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql') ), 
                array( 'id' => $file_id ) 
            );
            wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=localidades&status=updated' ) ); exit;
        }
    }

    public function handle_localidades_delete() { 
        $file_id = ( isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0 );
        check_admin_referer( 'siarhe_delete_localidades_nonce_' . $file_id );
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
        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=localidades&status=deleted' ) ); exit;
    }

    // -------------------------------------------------------------------------
    // HANDLERS: BASES ESTÁTICAS (CSV)
    // -------------------------------------------------------------------------
    public function handle_static_upload() {
        if ( ! isset( $_POST['siarhe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['siarhe_nonce'] ) ), 'siarhe_upload_static_nonce' ) ) wp_die( 'Error de seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        set_time_limit(0);
        wp_raise_memory_limit('admin');

        if ( isset( $_FILES['siarhe_file'] ) && $_FILES['siarhe_file']['error'] == 0 ) {
            $entidad_slug = $this->sanitize_slug( isset( $_POST['entidad_slug'] ) ? $_POST['entidad_slug'] : '' );
            $this->validate_upload( $_FILES['siarhe_file'], array( 'csv' ) );
            $this->flash_notice( $this->validate_csv_file( $_FILES['siarhe_file']['tmp_name'], 'static', $entidad_slug ) );
            $anio = intval( $_POST['anio_reporte'] );
            $fecha_corte = sanitize_text_field( wp_unslash( $_POST['fecha_corte'] ) ); 
            $referencia = wp_kses_post( wp_unslash( $_POST['referencia'] ) ); 
            $comentarios = sanitize_textarea_field( wp_unslash( $_POST['comentarios'] ) );

            $current_user = wp_get_current_user();
            $editor_name = $current_user->display_name ?: $current_user->user_login;

            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'] . '/siarhe-data/static-min/';
            if ( ! file_exists( $target_dir ) ) wp_mkdir_p( $target_dir );

            $new_filename = $entidad_slug . '.csv'; 
            $target_file = $target_dir . $new_filename;

            $this->archive_previous_version( $target_file );

            if ( move_uploaded_file( $_FILES['siarhe_file']['tmp_name'], $target_file ) ) {
                $this->ensure_utf8_csv( $target_file );
                global $wpdb;
                $table_name = $wpdb->prefix . 'siarhe_static_assets';
                
                $existe = $wpdb->get_row( $wpdb->prepare("SELECT id FROM $table_name WHERE entidad_slug = %s AND tipo_archivo = 'static_min'", $entidad_slug) );

                if ($existe) {
                    $datos = array( 
                        'ruta_archivo' => 'static-min/' . $new_filename, 
                        'anio_reporte' => $anio, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                        'comentarios' => $comentarios, 'es_activo' => 1,
                        'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql')
                    );
                    $formato = array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' );
                    $wpdb->update( $table_name, $datos, array( 'id' => $existe->id ), $formato, array( '%d' ) ); 
                } else {
                    $datos = array( 
                        'entidad_slug' => $entidad_slug, 'tipo_archivo' => 'static_min', 'ruta_archivo' => 'static-min/' . $new_filename, 
                        'anio_reporte' => $anio, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                        'comentarios' => $comentarios, 'es_activo' => 1, 'fecha_subida' => current_time( 'mysql' ),
                        'subido_por' => $editor_name, 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql')
                    );
                    $formato = array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );
                    $wpdb->insert( $table_name, $datos, $formato ); 
                }

                wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=static&status=success' ) ); exit;
            } else { $this->upload_fail( 'Error mover.' ); }
        } else { $this->upload_fail( 'Error carga PHP.' ); }
    }

    public function handle_static_meta_update() {
        if ( ! isset( $_POST['siarhe_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['siarhe_meta_nonce'] ) ), 'siarhe_update_static_meta_nonce' ) ) wp_die( 'Error de seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        global $wpdb;
        $file_id = ( isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0 );
        $table_name = $wpdb->prefix . 'siarhe_static_assets';

        $current_user = wp_get_current_user();
        $editor_name = $current_user->display_name ?: $current_user->user_login;

        if ( $file_id > 0 ) {
            $wpdb->update( 
                $table_name, 
                array( 'anio_reporte' => intval( $_POST['anio_reporte'] ), 'fecha_corte' => sanitize_text_field( wp_unslash( $_POST['fecha_corte'] ) ), 'referencia_bibliografica' => wp_kses_post( wp_unslash( $_POST['referencia'] ) ), 'comentarios' => sanitize_textarea_field( wp_unslash( $_POST['comentarios'] ) ), 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql') ), 
                array( 'id' => $file_id )
            );

            $tipo = $wpdb->get_var( $wpdb->prepare( "SELECT tipo_archivo FROM $table_name WHERE id = %d", $file_id ) );
            $tab_destino = ($tipo === 'marcador') ? 'marcadores' : 'static';

            wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=' . $tab_destino . '&status=updated' ) );
            exit;
        }
    }

    public function handle_static_delete() {
        $file_id = ( isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0 );
        check_admin_referer( 'siarhe_delete_static_nonce_' . $file_id );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        global $wpdb;
        $table_name = $wpdb->prefix . 'siarhe_static_assets';
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT ruta_archivo, tipo_archivo FROM $table_name WHERE id = %d", $file_id ) );
        $tab_destino = 'static';
        if ( $file ) {
            if ( $file->tipo_archivo === 'marcador' ) $tab_destino = 'marcadores';
            $upload_dir = wp_upload_dir();
            $filepath = $upload_dir['basedir'] . '/siarhe-data/' . $file->ruta_archivo;
            if ( file_exists( $filepath ) ) unlink( $filepath );
            $wpdb->delete( $table_name, array( 'id' => $file_id ) );
        }
        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=' . $tab_destino . '&status=deleted' ) ); exit;
    }

    // -------------------------------------------------------------------------
    // HANDLERS: VARIABLES DE MARCADORES (alta / baja / restaurar)
    // Se procesan en admin-post y redirigen (la pagina se recarga ya actualizada).
    // -------------------------------------------------------------------------
    private static function marker_core_types() {
        return array(
            'CATETER'          => array( 'label' => 'Clínicas de Catéteres', 'filename' => 'clinicas-cateteres.csv', 'is_core' => true ),
            'HERIDAS'          => array( 'label' => 'Clínicas de Heridas', 'filename' => 'clinicas-heridas.csv', 'is_core' => true ),
            'ESTABLECIMIENTOS' => array( 'label' => 'Establecimientos de Salud (Todas)', 'filename' => 'establecimientos-salud.csv', 'is_core' => true ),
        );
    }

    private function marker_types() {
        $tipos = json_decode( wp_unslash( get_option( 'siarhe_archivos_marcadores', '' ) ), true );
        return ( is_array( $tipos ) && ! empty( $tipos ) ) ? $tipos : self::marker_core_types();
    }

    private function save_marker_types( $tipos ) {
        update_option( 'siarhe_archivos_marcadores', wp_json_encode( $tipos, JSON_UNESCAPED_UNICODE ) );
    }

    private function marker_redirect( $status ) {
        wp_safe_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=marcadores&status=' . $status ) );
        exit;
    }

    /** Borra el CSV fisico y el registro de una variable de marcador. */
    private function purge_marker_asset( $key, $filename ) {
        global $wpdb;
        $table = $wpdb->prefix . 'siarhe_static_assets';
        $dir   = ( defined( 'SIARHE_UPLOAD_DIR' ) ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/' ) . 'markers/';
        $asset = $wpdb->get_row( $wpdb->prepare( "SELECT id, ruta_archivo FROM $table WHERE tipo_archivo = %s AND entidad_slug = %s", 'marcador', $key ) );
        if ( $asset ) {
            $path = $dir . basename( $asset->ruta_archivo );
            if ( file_exists( $path ) ) @unlink( $path );
            $wpdb->delete( $table, array( 'id' => $asset->id ) );
        } else {
            $path = $dir . basename( $filename );
            if ( file_exists( $path ) ) @unlink( $path );
        }
    }

    public function handle_marker_var_add() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        check_admin_referer( 'siarhe_add_variable_nonce', 'add_nonce' );
        $tipos = $this->marker_types();

        $key   = sanitize_text_field( strtoupper( str_replace( ' ', '_', wp_unslash( isset( $_POST['new_file_key'] ) ? $_POST['new_file_key'] : '' ) ) ) );
        $label = sanitize_text_field( wp_unslash( isset( $_POST['new_file_label'] ) ? $_POST['new_file_label'] : '' ) );
        $file  = sanitize_file_name( strtolower( str_replace( ' ', '-', wp_unslash( isset( $_POST['new_file_name'] ) ? $_POST['new_file_name'] : '' ) ) ) );
        if ( $file !== '' && strpos( $file, '.csv' ) === false ) $file .= '.csv';

        if ( $key === '' || $file === '' ) $this->marker_redirect( 'var_invalid' );
        if ( isset( $tipos[ $key ] ) ) $this->marker_redirect( 'var_exists' );

        $tipos[ $key ] = array( 'label' => $label, 'filename' => $file, 'is_core' => false );
        $this->save_marker_types( $tipos );
        $this->marker_redirect( 'var_added' );
    }

    public function handle_marker_var_delete() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        check_admin_referer( 'siarhe_delete_variable_nonce', 'del_nonce' );
        $tipos = $this->marker_types();
        $key   = sanitize_text_field( wp_unslash( isset( $_POST['del_file_key'] ) ? $_POST['del_file_key'] : '' ) );

        if ( isset( $tipos[ $key ] ) && empty( $tipos[ $key ]['is_core'] ) ) {
            $this->purge_marker_asset( $key, $tipos[ $key ]['filename'] );
            unset( $tipos[ $key ] );
            $this->save_marker_types( $tipos );
            $this->marker_redirect( 'var_deleted' );
        }
        $this->marker_redirect( 'var_invalid' );
    }

    public function handle_marker_var_reset() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        check_admin_referer( 'reset_variables_nonce', 'reset_nonce' );
        foreach ( $this->marker_types() as $key => $inf ) {
            if ( empty( $inf['is_core'] ) ) $this->purge_marker_asset( $key, $inf['filename'] );
        }
        $this->save_marker_types( self::marker_core_types() );
        $this->marker_redirect( 'var_reset' );
    }

    // -------------------------------------------------------------------------
    // HANDLERS: MARCADORES 
    // -------------------------------------------------------------------------
    public function handle_marker_upload() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No tienes permisos.' );
        check_admin_referer( 'siarhe_upload_marker_nonce', 'marker_nonce' );

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
        
        if ( ! array_key_exists($tipo, $tipos_marcadores) ) $this->upload_fail('Tipo de marcador no válido.');

        $nombre_final = $tipos_marcadores[$tipo]['filename'];

        if ( isset($_FILES['marker_file']) && !empty($_FILES['marker_file']['name']) ) {
            $anio = intval( $_POST['anio_reporte'] );
            $fecha_corte = sanitize_text_field( wp_unslash( $_POST['fecha_corte'] ) ); 
            $referencia = wp_kses_post( wp_unslash( $_POST['referencia'] ) ); 
            $comentarios = sanitize_textarea_field( wp_unslash( $_POST['comentarios'] ) );
            
            $current_user = wp_get_current_user();
            $editor_name = $current_user->display_name ?: $current_user->user_login;

            $ext_marker = strtolower( pathinfo( sanitize_file_name( $_FILES['marker_file']['name'] ), PATHINFO_EXTENSION ) );
            if ( $ext_marker !== 'csv' ) $this->upload_fail( 'Solo se permiten archivos CSV.' );
            $this->flash_notice( $this->validate_csv_file( $_FILES['marker_file']['tmp_name'], 'marker', $tipo ) );

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

                $this->archive_previous_version( $dest_path );

                if ( copy($source, $dest_path) ) {
                    $this->ensure_utf8_csv( $dest_path );
                    unlink($source);
                    
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'siarhe_static_assets';
                    
                    $existe = $wpdb->get_row( $wpdb->prepare(
                        "SELECT id FROM $table_name WHERE entidad_slug = %s AND tipo_archivo = 'marcador'", 
                        $tipo
                    ));

                    if ($existe) {
                        $datos = [
                            'ruta_archivo' => 'markers/' . $nombre_final,
                            'anio_reporte' => $anio,
                            'fecha_corte'  => $fecha_corte,
                            'referencia_bibliografica' => $referencia,
                            'comentarios'  => $comentarios,
                            'es_activo'    => 1,
                            'modificado_por' => $editor_name,
                            'fecha_modificacion' => current_time('mysql')
                        ];
                        $fmt = ['%s','%d','%s','%s','%s','%d','%s','%s'];
                        $wpdb->update($table_name, $datos, ['id' => $existe->id], $fmt, ['%d']);
                    } else {
                        $datos = [
                            'entidad_slug' => $tipo,
                            'tipo_archivo' => 'marcador',
                            'ruta_archivo' => 'markers/' . $nombre_final,
                            'anio_reporte' => $anio,
                            'fecha_corte'  => $fecha_corte,
                            'referencia_bibliografica' => $referencia,
                            'comentarios'  => $comentarios,
                            'es_activo'    => 1,
                            'fecha_subida' => current_time('mysql'),
                            'subido_por' => $editor_name,
                            'modificado_por' => $editor_name,
                            'fecha_modificacion' => current_time('mysql')
                        ];
                        $fmt = ['%s','%s','%s','%d','%s','%s','%s','%d','%s','%s','%s','%s'];
                        $wpdb->insert($table_name, $datos, $fmt);
                    }

                    wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=marcadores&status=success' ) );
                    exit;
                } else { $this->upload_fail('Error al mover el archivo.'); }
            } else { $this->upload_fail( $movefile['error'] ); }
        } else { $this->upload_fail('No se seleccionó ningún archivo.'); }
    }
}