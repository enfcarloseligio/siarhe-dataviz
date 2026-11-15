<?php // /admin/class-siarhe-admin.php
if ( ! defined( 'ABSPATH' ) ) exit;

class Siarhe_Admin {

    // Subir este valor cuando maybe_update_database() deba aplicar nuevas columnas.
    const DB_VERSION = '4';

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
        add_action( 'admin_post_siarhe_base_tema_add', array( $this, 'handle_base_tema_add' ) );
        add_action( 'admin_post_siarhe_base_tema_delete', array( $this, 'handle_base_tema_delete' ) );

        // --- VALIDACION DE LOS ARCHIVOS YA CARGADOS ---
        add_action( 'admin_post_siarhe_validate_files', array( $this, 'handle_validate_files' ) );

        // --- HANDLERS: MARCADORES (Subida Específica) ---
        add_action( 'admin_post_siarhe_upload_marker', array( $this, 'handle_marker_upload' ) );
        add_action( 'admin_post_siarhe_marker_var_add', array( $this, 'handle_marker_var_add' ) );
        add_action( 'admin_post_siarhe_marker_var_delete', array( $this, 'handle_marker_var_delete' ) );
        add_action( 'admin_post_siarhe_marker_var_reset', array( $this, 'handle_marker_var_reset' ) );

        // --- PERSONALIZAR: volver a los colores del sistema ---
        add_action( 'admin_post_siarhe_reset_colors', array( $this, 'handle_reset_colors' ) );

        // Subidas con barra de progreso (XHR): la redireccion final se devuelve como JSON
        add_filter( 'wp_redirect', array( $this, 'redirect_as_json_for_xhr' ), 10, 1 );

        // --- VERSIONES (copias de seguridad de los archivos) ---
        add_action( 'admin_post_siarhe_version_restore', array( $this, 'handle_version_restore' ) );
        add_action( 'admin_post_siarhe_version_delete', array( $this, 'handle_version_delete' ) );

        // --- PLANTILLAS Y DICCIONARIO DE VARIABLES (descargas) ---
        add_action( 'admin_post_siarhe_download_columns', array( $this, 'handle_download_columns' ) );
        add_action( 'add_option_' . Siarhe_Versions::OPT_KEEP, array( 'Siarhe_Versions', 'on_keep_changed' ) );
        add_action( 'update_option_' . Siarhe_Versions::OPT_KEEP, array( 'Siarhe_Versions', 'on_keep_changed' ) );
        add_action( 'add_option_siarhe_read_copies', 'siarhe_on_read_copies_changed', 10, 2 );
        add_action( 'update_option_siarhe_read_copies', 'siarhe_on_read_copies_changed', 10, 2 );
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
            echo '</div><div class="siarhe-downloads"><span class="siarhe-downloads__label">Plantillas mínimas (temas propios y Clínicas)';
            self::help( 'CSV con lo menos que necesita una base de un tema que no es Enfermería: la clave, el nombre y columnas de valor a las que pones el nombre de tus variables. Súbelo como CSV: el plugin crea su JSON y es el que se lee.' );
            echo '</span>';
            echo '<a class="button" href="' . esc_url( self::columns_url( 'static', 'plantilla', array( 'variante' => 'nacional', 'minima' => 1 ) ) ) . '"><span class="dashicons dashicons-download"></span> Mínima nacional (CSV)</a>';
            echo '<a class="button" href="' . esc_url( self::columns_url( 'static', 'plantilla', array( 'variante' => 'municipal', 'minima' => 1 ) ) ) . '"><span class="dashicons dashicons-download"></span> Mínima municipal (CSV)</a>';
        } else {
            echo '<a class="button" href="' . esc_url( self::columns_url( 'marker', 'plantilla', array( 'minima' => 1 ) ) ) . '" title="Coordenadas, clave de la entidad y nombre: lo menos con que funciona una variable nueva"><span class="dashicons dashicons-download"></span> Plantilla mínima (CSV)</a>';
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
            // Plantilla minima: para las bases de un tema propio o de Clinicas (identificador, nombre y columnas de valor)
            if ( $what === 'plantilla' && ! empty( $_GET['minima'] ) ) {
                $cols = Siarhe_Columns::static_min_columns( $variante );
                Siarhe_Columns::send_csv( 'plantilla-minima-base-' . $variante . '.csv', array( wp_list_pluck( $cols, 'name' ), Siarhe_Columns::example_row( $cols ) ) );
            }
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
            // Plantilla minima: coordenadas, clave de la entidad y nombre (lo menos con que funciona un marcador nuevo)
            if ( $what === 'plantilla' && ! empty( $_GET['minima'] ) ) {
                $cols = Siarhe_Columns::marker_min_columns();
                Siarhe_Columns::send_csv( 'plantilla-minima-marcadores.csv', array( wp_list_pluck( $cols, 'name' ), Siarhe_Columns::example_row( $cols ) ) );
            }
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
            // Version 2: las bases estaticas se distinguen por año y unidad (plazas / personas) y pueden tener JSON
            if (!in_array('unidad', $cols)) {
                $wpdb->query("ALTER TABLE `$table` ADD `unidad` varchar(10) NOT NULL DEFAULT 'plazas'");
                // Las bases cargadas antes de existir el selector de año se toman como las del año mas reciente
                $wpdb->query( $wpdb->prepare( "UPDATE `$table` SET anio_reporte = %d WHERE tipo_archivo = 'static_min'", Siarhe_Static_Json::LATEST_YEAR ) );
            }
            if (!in_array('ruta_json', $cols)) {
                $wpdb->query("ALTER TABLE `$table` ADD `ruta_json` varchar(255) DEFAULT NULL");
            }
            // Version 3: las geometrias (mapa base y localidades) se distinguen por marco quinquenal; las cargadas antes de
            // existir el selector se toman como las del marco mas reciente
            if ( (int) get_option( 'siarhe_db_version' ) < 3 ) {
                $wpdb->query( $wpdb->prepare( "UPDATE `$table` SET anio_reporte = %d WHERE tipo_archivo IN ('geojson','localidades_geojson')", Siarhe_Geo::MARCO_HEREDADO ) );
            }
            // Version 4: las bases estaticas llevan tema (las cargadas antes son de Enfermeria) y la unidad admite nombres mas
            // largos (frecuencia). Los marcadores cargados antes de existir los periodos ({nombre}.csv) toman el suyo: las
            // clinicas, «Todas»; los tipos que solo se cargan por año, el año mas reciente.
            if ( ! in_array( 'tema', $cols ) ) {
                $wpdb->query( "ALTER TABLE `$table` ADD `tema` varchar(40) NOT NULL DEFAULT 'enfermeria'" );
                $wpdb->query( "ALTER TABLE `$table` MODIFY `unidad` varchar(20) NOT NULL DEFAULT 'plazas'" );
            }
            if ( (int) get_option( 'siarhe_db_version' ) < 4 ) {
                foreach ( siarhe_marker_types() as $clave => $info ) {
                    $heredado = 'markers/' . $info['filename']; // sin sufijo de periodo
                    $periodo  = siarhe_marker_type_has_total( $info ) ? 0 : Siarhe_Static_Json::LATEST_YEAR;
                    $wpdb->query( $wpdb->prepare(
                        "UPDATE `$table` SET anio_reporte = %d WHERE tipo_archivo = 'marcador' AND entidad_slug = %s AND ruta_archivo = %s",
                        $periodo, $clave, $heredado
                    ) );
                }
            }
            update_option( 'siarhe_db_version', self::DB_VERSION );
            $this->build_missing_static_json();
        }
    }

    /**
     * Crea el JSON optimizado de las bases estaticas que solo tienen CSV (las cargadas antes de existir el formato JSON).
     * Si algun CSV no se puede convertir, se queda sin JSON y el mapa sigue leyendo su CSV.
     */
    private function build_missing_static_json() {
        global $wpdb;
        $table = $wpdb->prefix . 'siarhe_static_assets';
        $rows = $wpdb->get_results( "SELECT id, ruta_archivo, anio_reporte, unidad FROM `$table` WHERE tipo_archivo = 'static_min' AND ( ruta_json IS NULL OR ruta_json = '' ) AND ruta_archivo <> ''" );
        foreach ( (array) $rows as $r ) {
            $ruta = $this->write_static_json_from_csv( SIARHE_UPLOAD_DIR . $r->ruta_archivo, (int) $r->anio_reporte, $r->unidad );
            if ( $ruta !== '' ) $wpdb->update( $table, array( 'ruta_json' => $ruta ), array( 'id' => (int) $r->id ), array( '%s' ), array( '%d' ) );
        }
    }

    /**
     * Escribe junto a un CSV su JSON optimizado (mismo nombre, extension .json). Devuelve la ruta relativa a siarhe-data o ''.
     */
    private function write_static_json_from_csv( $csv_path, $anio, $unidad ) {
        if ( ! is_readable( $csv_path ) ) return '';
        $json = Siarhe_Static_Json::from_csv( (string) file_get_contents( $csv_path ), $anio, $unidad );
        if ( ! $json || Siarhe_Static_Json::validate( $json ) ) return '';
        $dest = preg_replace( '/\.csv$/i', '.json', $csv_path );
        if ( $dest === $csv_path || file_put_contents( $dest, Siarhe_Static_Json::encode( $json ) ) === false ) return '';
        return ltrim( str_replace( '\\', '/', substr( $dest, strlen( SIARHE_UPLOAD_DIR ) ) ), '/' );
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
        register_setting( 'siarhe_general_group', 'siarhe_general_options', $arr );
        register_setting( 'siarhe_links_group', 'siarhe_links_map', $arr );
        register_setting( 'siarhe_theme_group', 'siarhe_theme_options', $arr );
        register_setting( 'siarhe_map_group', 'siarhe_map_options', $arr );
        register_setting( 'siarhe_metricas_group', 'siarhe_metricas_config', $json );
        register_setting( 'siarhe_metricas_group', 'siarhe_temas_config', $json );
        register_setting( 'siarhe_tooltip_group', 'siarhe_tooltip_config', $json );
        register_setting( 'siarhe_marcadores_group', 'siarhe_marcadores_config', $json );
        register_setting( 'siarhe_versions_group', Siarhe_Versions::OPT_KEEP, array(
            'sanitize_callback' => array( 'Siarhe_Versions', 'sanitize_keep' ),
            'default'           => Siarhe_Versions::DEFAULT_KEEP,
        ) );
        register_setting( 'siarhe_versions_group', 'siarhe_read_copies', array(
            'sanitize_callback' => array( 'Siarhe_Versions', 'sanitize_bool' ),
            'default'           => '1',
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
     * Valida un archivo subido: extension permitida y, para las geometrias, JSON bien formado.
     * Si no cumple, regresa a la pestaña de origen con el mensaje.
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
                $this->upload_fail( 'El archivo no es un JSON de geometrías válido (TopoJSON o GeoJSON).' );
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
            $this->upload_fail( 'El archivo debe ser un TopoJSON ("type": "Topology", como lo exporta RHEMX) o un GeoJSON FeatureCollection con al menos un elemento en "features".' );
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
     * $tema: tema de la base estatica; solo las de Enfermeria exigen poblacion, enfermeras_total y tasa_total.
     * Los problemas graves detienen la carga; los demas se devuelven como avisos.
     */
    private function validate_csv_file( $tmp_path, $kind, $slug = '', $tema = 'enfermeria' ) {
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
            $de_enfermeria = ( $tema === 'enfermeria' );
            if ( $de_enfermeria ) {
                $has_pob = false;
                foreach ( $header as $h ) {
                    if ( strpos( $h, 'pob' ) === 0 ) { $has_pob = true; break; }
                }
                if ( ! $has_pob ) $missing[] = 'población (una columna que empiece con "pob")';
                foreach ( array( 'tasa_total', 'enfermeras_total' ) as $must ) {
                    if ( ! in_array( $must, $raw_header, true ) ) $missing[] = $must . ' (sin ella el mapa no muestra datos)';
                }
            }

            if ( $missing ) {
                fclose( $fh );
                $this->upload_fail( 'El CSV no tiene las columnas requeridas: ' . esc_html( implode( '; ', $missing ) ) . '.' );
            }

            // Metricas configuradas en el admin: cada clave es el nombre de una columna del CSV
            $metric_idx = array();
            $missing_m  = array();
            $cfg = $de_enfermeria ? siarhe_get_metricas_config() : null; // las metricas del mapa son las de Enfermeria
            if ( is_array( $cfg ) ) {
                foreach ( $cfg as $k => $m_cfg ) {
                    if ( $k === 'poblacion' || ! empty( $m_cfg['opcional'] ) ) continue; // las opcionales solo se muestran si la base trae la columna
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

            // Claves geograficas: el mapa de cada entidad usa CVE_ENT para dibujar solo sus puntos
            // Tambien se aceptan los encabezados del catalogo CLUES (CLAVE DE LA ENTIDAD, CLAVE DEL MUNICIPIO, CLAVE DE LA LOCALIDAD)
            $geo_req = array(
                'CVE_ENT' => array( 'cve_ent', 'clave de la entidad' ), 'ENTIDAD' => array( 'entidad' ),
                'CVE_MUN' => array( 'cve_mun', 'clave del municipio' ), 'MUNICIPIO' => array( 'municipio' ),
                'CVE_LOC' => array( 'cve_loc', 'clave de la localidad' ), 'LOCALIDAD' => array( 'localidad' ),
            );
            $missing_geo = array();
            $ent_i = false;
            foreach ( $geo_req as $g => $alias ) {
                $found = false;
                foreach ( $alias as $a ) {
                    $pos = array_search( $a, $header, true );
                    if ( $pos !== false ) { $found = true; if ( $g === 'CVE_ENT' ) $ent_i = $pos; break; }
                }
                if ( ! $found ) $missing_geo[] = $g;
            }
            // Sin la clave de entidad no se sabe en que mapa va cada punto; las demas solo limitan lo que muestra el mapa
            if ( in_array( 'CVE_ENT', $missing_geo, true ) ) {
                fclose( $fh );
                $this->upload_fail( 'El CSV de marcadores necesita la clave de la entidad (CVE_ENT o CLAVE DE LA ENTIDAD). Descarga la plantilla en esta pantalla para ver el formato.' );
            }
            if ( $missing_geo ) {
                $warnings[] = 'El CSV no trae ' . implode( ', ', $missing_geo ) . ': los puntos se dibujan, pero el resumen por municipio y algunos datos de la ficha no estarán disponibles.';
            }

            $invalid = 0; $no_ent = 0;
            while ( ( $row = fgetcsv( $fh ) ) !== false && $rows < 300000 ) {
                if ( $row === array( null ) ) continue;
                $rows++;
                if ( ! isset( $row[ $ent_i ] ) || trim( $row[ $ent_i ] ) === '' ) $no_ent++;
                $lat = isset( $row[ $lat_i ] ) ? (float) $row[ $lat_i ] : 0;
                $lon = isset( $row[ $lon_i ] ) ? (float) $row[ $lon_i ] : 0;
                if ( $lat < 14 || $lat > 33 || $lon < -119 || $lon > -86 ) $invalid++;
            }
            if ( $no_ent > 0 ) {
                $warnings[] = $no_ent . ' fila(s) no tienen CVE_ENT y aparecerían en el mapa de todas las entidades; completa la clave para que cada una salga solo en la suya.';
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
    // HANDLERS: GEOMETRIAS (mapa base y capa de localidades), por marco geoestadistico
    // Las dos pestañas comparten los mismos manejadores; solo cambian la capa, la carpeta y los nonces.
    // -------------------------------------------------------------------------
    public function handle_geojson_upload()          { $this->handle_geo_upload( 'base' ); }
    public function handle_localidades_upload()      { $this->handle_geo_upload( 'loc' ); }
    public function handle_geojson_meta_update()     { $this->handle_geo_meta_update( 'base' ); }
    public function handle_localidades_meta_update() { $this->handle_geo_meta_update( 'loc' ); }
    public function handle_geojson_delete()          { $this->handle_geo_delete( 'base' ); }
    public function handle_localidades_delete()      { $this->handle_geo_delete( 'loc' ); }

    /** Pestaña y nonces del formulario de cada capa de geometria. */
    private static function geo_form( $capa ) {
        return ( $capa === 'base' )
            ? array( 'tab' => 'geojson', 'nonce_upload' => 'siarhe_upload_nonce', 'nonce_meta' => 'siarhe_update_meta_nonce', 'nonce_delete' => 'siarhe_delete_nonce_' )
            : array( 'tab' => 'localidades', 'nonce_upload' => 'siarhe_upload_localidades_nonce', 'nonce_meta' => 'siarhe_update_localidades_meta_nonce', 'nonce_delete' => 'siarhe_delete_localidades_nonce_' );
    }

    /**
     * Guarda una geometria (TopoJSON de RHEMX o GeoJSON) de una entidad para un marco geoestadistico. Hay un archivo por
     * capa + entidad + marco, con el nombre {entidad}-{marco}.json; subir otro para la misma combinacion lo reemplaza.
     */
    private function handle_geo_upload( $capa ) {
        $form = self::geo_form( $capa );
        if ( ! isset( $_POST['siarhe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['siarhe_nonce'] ) ), $form['nonce_upload'] ) ) wp_die( 'Error de seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        set_time_limit( 0 );
        wp_raise_memory_limit( 'admin' );

        if ( ! isset( $_FILES['siarhe_file'] ) || $_FILES['siarhe_file']['error'] != 0 ) {
            $codigo = isset( $_FILES['siarhe_file']['error'] ) ? (int) $_FILES['siarhe_file']['error'] : 0;
            $this->upload_fail( 'El servidor rechazó el archivo (código de error PHP: ' . $codigo . '). El tamaño máximo de carga es ' . ini_get( 'upload_max_filesize' ) . '.' );
        }

        $entidad_slug = $this->sanitize_slug( isset( $_POST['entidad_slug'] ) ? $_POST['entidad_slug'] : '' );
        $entidades    = siarhe_get_entities();
        if ( ! isset( $entidades[ $entidad_slug ] ) ) $this->upload_fail( 'Entidad no válida.' );

        $marco = isset( $_POST['anio_reporte'] ) ? intval( $_POST['anio_reporte'] ) : 0;
        if ( ! Siarhe_Geo::es_marco( $marco ) ) {
            $this->upload_fail( 'Elige el marco geoestadístico (' . implode( ', ', array_reverse( Siarhe_Geo::marcos() ) ) . ').' );
        }

        // Contenido: TopoJSON (formato del plugin) o GeoJSON FeatureCollection
        $data = $this->validate_upload( $_FILES['siarhe_file'], array( 'json', 'topojson' ), true );
        if ( Siarhe_Geo::es_topologia( $data ) ) {
            $revision = Siarhe_Geo::validar_topologia( $data, $capa, $entidad_slug, $entidades[ $entidad_slug ]['CVE_ENT'] );
            if ( $revision['error'] !== '' ) $this->upload_fail( $revision['error'] );
            $this->flash_notice( $revision['avisos'] );
        } else {
            $this->flash_notice( $this->validate_geojson_data( $data, $entidad_slug, $capa === 'base' ) );
        }
        unset( $data );

        $fecha_corte = sanitize_text_field( wp_unslash( isset( $_POST['fecha_corte'] ) ? $_POST['fecha_corte'] : '' ) );
        $referencia  = wp_kses_post( wp_unslash( isset( $_POST['referencia'] ) ? $_POST['referencia'] : '' ) );
        $comentarios = sanitize_textarea_field( wp_unslash( isset( $_POST['comentarios'] ) ? $_POST['comentarios'] : '' ) );

        $current_user = wp_get_current_user();
        $editor_name  = $current_user->display_name ?: $current_user->user_login;

        $dir  = Siarhe_Geo::CAPAS[ $capa ]['dir'];
        $tipo = Siarhe_Geo::CAPAS[ $capa ]['tipo'];
        $target_dir = SIARHE_UPLOAD_DIR . $dir . '/';
        if ( ! file_exists( $target_dir ) ) wp_mkdir_p( $target_dir );

        $new_filename = $entidad_slug . '-' . $marco . '.json';
        $target_file  = $target_dir . $new_filename;
        $ruta_rel     = $dir . '/' . $new_filename;

        global $wpdb;
        $table_name = $wpdb->prefix . 'siarhe_static_assets';
        $existe = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, ruta_archivo FROM $table_name WHERE entidad_slug = %s AND tipo_archivo = %s AND anio_reporte = %d ORDER BY id DESC LIMIT 1",
            $entidad_slug, $tipo, $marco
        ) );

        // El archivo vigente de este marco puede tener otro nombre ({entidad}.geojson, los cargados antes de los marcos)
        $anterior = ( $existe && $existe->ruta_archivo !== $ruta_rel ) ? SIARHE_UPLOAD_DIR . ltrim( $existe->ruta_archivo, '/' ) : '';
        $this->archive_previous_version( $anterior !== '' ? $anterior : $target_file );

        if ( ! move_uploaded_file( $_FILES['siarhe_file']['tmp_name'], $target_file ) ) {
            $this->upload_fail( 'No se pudo guardar el archivo. Verifica los permisos de escritura de la carpeta de datos.' );
        }
        if ( $anterior !== '' && is_file( $anterior ) ) {
            @unlink( $anterior );
            siarhe_delete_data_read_copies( $anterior );
        }

        if ( $existe ) {
            $wpdb->update( $table_name, array(
                'ruta_archivo' => $ruta_rel, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                'comentarios' => $comentarios, 'es_activo' => 1,
                'modificado_por' => $editor_name, 'fecha_modificacion' => current_time( 'mysql' ),
            ), array( 'id' => $existe->id ), array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' ), array( '%d' ) );
        } else {
            $wpdb->insert( $table_name, array(
                'entidad_slug' => $entidad_slug, 'tipo_archivo' => $tipo, 'ruta_archivo' => $ruta_rel,
                'anio_reporte' => $marco, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                'comentarios' => $comentarios, 'es_activo' => 1, 'fecha_subida' => current_time( 'mysql' ),
                'subido_por' => $editor_name, 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time( 'mysql' ),
            ), array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
        }

        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=' . $form['tab'] . '&status=success' ) );
        exit;
    }

    /** Actualiza fecha de corte, referencia y comentarios. El marco identifica al archivo y no se cambia desde aqui. */
    private function handle_geo_meta_update( $capa ) {
        $form = self::geo_form( $capa );
        if ( ! isset( $_POST['siarhe_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['siarhe_meta_nonce'] ) ), $form['nonce_meta'] ) ) wp_die( 'Error de seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        global $wpdb;
        $file_id = isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0;
        if ( $file_id > 0 ) {
            $current_user = wp_get_current_user();
            $wpdb->update( $wpdb->prefix . 'siarhe_static_assets', array(
                'fecha_corte'              => sanitize_text_field( wp_unslash( isset( $_POST['fecha_corte'] ) ? $_POST['fecha_corte'] : '' ) ),
                'referencia_bibliografica' => wp_kses_post( wp_unslash( isset( $_POST['referencia'] ) ? $_POST['referencia'] : '' ) ),
                'comentarios'              => sanitize_textarea_field( wp_unslash( isset( $_POST['comentarios'] ) ? $_POST['comentarios'] : '' ) ),
                'modificado_por'           => $current_user->display_name ?: $current_user->user_login,
                'fecha_modificacion'       => current_time( 'mysql' ),
            ), array( 'id' => $file_id, 'tipo_archivo' => Siarhe_Geo::CAPAS[ $capa ]['tipo'] ) );
        }
        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=' . $form['tab'] . '&status=updated' ) );
        exit;
    }

    /** Elimina el archivo de una geometria, sus copias de lectura y su registro. */
    private function handle_geo_delete( $capa ) {
        $form    = self::geo_form( $capa );
        $file_id = isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0;
        check_admin_referer( $form['nonce_delete'] . $file_id );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        global $wpdb;
        $table_name = $wpdb->prefix . 'siarhe_static_assets';
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT ruta_archivo FROM $table_name WHERE id = %d AND tipo_archivo = %s", $file_id, Siarhe_Geo::CAPAS[ $capa ]['tipo'] ) );
        if ( $file ) {
            $filepath = SIARHE_UPLOAD_DIR . ltrim( $file->ruta_archivo, '/' );
            if ( file_exists( $filepath ) ) {
                unlink( $filepath );
                siarhe_delete_data_read_copies( $filepath );
            }
            $wpdb->delete( $table_name, array( 'id' => $file_id ) );
        }
        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=' . $form['tab'] . '&status=deleted' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // HANDLERS: BASES ESTÁTICAS (CSV)
    // -------------------------------------------------------------------------
    /**
     * Guarda una base estatica. Cada base es entidad + año + tema + unidad de conteo y se acepta como CSV o como JSON
     * optimizado: el que se sube se valida (columnas, año, unidad y que sea de la entidad elegida) y de el se obtiene el otro.
     */
    public function handle_static_upload() {
        if ( ! isset( $_POST['siarhe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['siarhe_nonce'] ) ), 'siarhe_upload_static_nonce' ) ) wp_die( 'Error de seguridad.' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );

        set_time_limit(0);
        wp_raise_memory_limit('admin');

        if ( ! isset( $_FILES['siarhe_file'] ) || $_FILES['siarhe_file']['error'] != 0 ) $this->upload_fail( 'Error carga PHP.' );

        $entidad_slug = $this->sanitize_slug( isset( $_POST['entidad_slug'] ) ? $_POST['entidad_slug'] : '' );
        $entidades    = siarhe_get_entities();
        if ( ! isset( $entidades[ $entidad_slug ] ) ) $this->upload_fail( 'Entidad no válida.' );

        $temas = siarhe_base_temas();
        $tema  = isset( $_POST['tema'] ) ? sanitize_key( wp_unslash( $_POST['tema'] ) ) : 'enfermeria';
        if ( ! isset( $temas[ $tema ] ) ) $this->upload_fail( 'Elige el tema de la base.' );
        $unidad = isset( $_POST['unidad'] ) ? sanitize_key( wp_unslash( $_POST['unidad'] ) ) : '';
        if ( ! isset( $temas[ $tema ]['unidades'][ $unidad ] ) ) {
            $this->upload_fail( 'Elige la unidad de conteo del tema «' . $temas[ $tema ]['label'] . '» (' . implode( ', ', $temas[ $tema ]['unidades'] ) . ').' );
        }

        $anio = isset( $_POST['anio_reporte'] ) ? intval( $_POST['anio_reporte'] ) : 0;
        if ( $anio < Siarhe_Static_Json::YEAR_MIN || $anio > max( Siarhe_Static_Json::LATEST_YEAR, (int) gmdate( 'Y' ) ) ) {
            $this->upload_fail( 'Elige el año de los datos (de ' . Siarhe_Static_Json::YEAR_MIN . ' al año en curso).' );
        }
        $fecha_corte = sanitize_text_field( wp_unslash( $_POST['fecha_corte'] ) );
        $referencia = wp_kses_post( wp_unslash( $_POST['referencia'] ) );
        $comentarios = sanitize_textarea_field( wp_unslash( $_POST['comentarios'] ) );

        $this->validate_upload( $_FILES['siarhe_file'], array( 'csv', 'json' ) );
        $ext = strtolower( pathinfo( sanitize_file_name( $_FILES['siarhe_file']['name'] ), PATHINFO_EXTENSION ) );
        $tmp = $_FILES['siarhe_file']['tmp_name'];

        // El archivo subido (CSV o JSON) se valida y de el se obtiene su equivalente: asi los dos formatos quedan siempre iguales
        $json = null;
        $csv_texto = '';
        if ( $ext === 'json' ) {
            $json = json_decode( (string) file_get_contents( $tmp ), true );
            $errores = Siarhe_Static_Json::validate( $json );
            if ( $errores ) $this->upload_fail( 'El JSON no es válido: ' . implode( ' ', $errores ) );
            if ( isset( $json['anio'] ) && (int) $json['anio'] !== $anio ) {
                $this->upload_fail( 'El JSON es de ' . (int) $json['anio'] . ' y elegiste ' . $anio . ' como año de los datos.' );
            }
            // Solo las bases de Enfermeria declaran su unidad (plazas o personas) en el archivo
            if ( $tema === 'enfermeria' && isset( $json['unidad'] ) && $json['unidad'] !== $unidad ) {
                $this->upload_fail( 'El JSON cuenta ' . esc_html( $json['unidad'] ) . ' y elegiste ' . esc_html( $unidad ) . ' como unidad.' );
            }
            $otra_entidad = Siarhe_Validador::revisa_base_json( $json, $entidad_slug );
            if ( $otra_entidad !== '' ) $this->upload_fail( $otra_entidad );
            $json['anio'] = $anio;
            $json['unidad'] = $unidad;
            $csv_texto = Siarhe_Static_Json::to_csv( $json );
            $revision = wp_tempnam( 'siarhe-static' );
            file_put_contents( $revision, $csv_texto );
            register_shutdown_function( function () use ( $revision ) { if ( is_file( $revision ) ) @unlink( $revision ); } );
            $this->flash_notice( $this->validate_csv_file( $revision, 'static', $entidad_slug, $tema ) );
        } else {
            $avisos = $this->validate_csv_file( $tmp, 'static', $entidad_slug, $tema );
            $otra_entidad = Siarhe_Validador::revisa_base_csv( $tmp, $entidad_slug );
            if ( $otra_entidad !== '' ) $this->upload_fail( $otra_entidad );
            $this->flash_notice( $avisos );
        }

        $current_user = wp_get_current_user();
        $editor_name = $current_user->display_name ?: $current_user->user_login;

        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/siarhe-data/static-min/';
        if ( ! file_exists( $target_dir ) ) wp_mkdir_p( $target_dir );

        // Las bases de Enfermeria conservan el nombre {entidad}-{año}-{unidad}; las de otros temas agregan el suyo
        $base = $entidad_slug . '-' . $anio . '-' . ( $tema === 'enfermeria' ? '' : str_replace( '_', '-', $tema ) . '-' ) . $unidad;
        $csv_file  = $target_dir . $base . '.csv';
        $json_file = $target_dir . $base . '.json';
        $this->archive_previous_version( $csv_file );
        $this->archive_previous_version( $json_file );

        if ( $ext === 'json' ) {
            $ok = file_put_contents( $csv_file, $csv_texto ) !== false
               && file_put_contents( $json_file, Siarhe_Static_Json::encode( $json ) ) !== false;
        } else {
            $ok = move_uploaded_file( $tmp, $csv_file );
            if ( $ok ) {
                $this->ensure_utf8_csv( $csv_file );
                $ruta = $this->write_static_json_from_csv( $csv_file, $anio, $unidad );
                if ( $ruta === '' && is_file( $json_file ) ) @unlink( $json_file ); // un JSON anterior ya no corresponde a este CSV
            }
        }
        if ( ! $ok ) $this->upload_fail( 'Error mover.' );

        siarhe_build_read_copy( $csv_file );
        $ruta_json = is_file( $json_file ) ? 'static-min/' . $base . '.json' : '';

        global $wpdb;
        $table_name = $wpdb->prefix . 'siarhe_static_assets';
        $existe = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM $table_name WHERE entidad_slug = %s AND tipo_archivo = 'static_min' AND anio_reporte = %d AND tema = %s AND unidad = %s",
            $entidad_slug, $anio, $tema, $unidad
        ) );

        if ( $existe ) {
            $datos = array(
                'ruta_archivo' => 'static-min/' . $base . '.csv', 'ruta_json' => $ruta_json,
                'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                'comentarios' => $comentarios, 'es_activo' => 1,
                'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql')
            );
            $formato = array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' );
            $wpdb->update( $table_name, $datos, array( 'id' => $existe->id ), $formato, array( '%d' ) );
        } else {
            $datos = array(
                'entidad_slug' => $entidad_slug, 'tipo_archivo' => 'static_min',
                'ruta_archivo' => 'static-min/' . $base . '.csv', 'ruta_json' => $ruta_json,
                'anio_reporte' => $anio, 'tema' => $tema, 'unidad' => $unidad, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                'comentarios' => $comentarios, 'es_activo' => 1, 'fecha_subida' => current_time( 'mysql' ),
                'subido_por' => $editor_name, 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql')
            );
            $formato = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );
            $wpdb->insert( $table_name, $datos, $formato );
        }

        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=static&status=success' ) ); exit;
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
            $tipo = $wpdb->get_var( $wpdb->prepare( "SELECT tipo_archivo FROM $table_name WHERE id = %d", $file_id ) );
            $datos = array( 'fecha_corte' => sanitize_text_field( wp_unslash( $_POST['fecha_corte'] ) ), 'referencia_bibliografica' => wp_kses_post( wp_unslash( $_POST['referencia'] ) ), 'comentarios' => sanitize_textarea_field( wp_unslash( $_POST['comentarios'] ) ), 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time('mysql') );
            // El año de una base estatica es parte de su identidad (entidad + año + unidad): no se cambia desde aqui
            if ( $tipo !== 'static_min' && $tipo !== 'marcador' ) $datos['anio_reporte'] = intval( $_POST['anio_reporte'] );
            $wpdb->update( $table_name, $datos, array( 'id' => $file_id ) );

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
        $file = $wpdb->get_row( $wpdb->prepare( "SELECT ruta_archivo, ruta_json, tipo_archivo FROM $table_name WHERE id = %d", $file_id ) );
        $tab_destino = 'static';
        if ( $file ) {
            if ( $file->tipo_archivo === 'marcador' ) $tab_destino = 'marcadores';
            $upload_dir = wp_upload_dir();
            $filepath = $upload_dir['basedir'] . '/siarhe-data/' . $file->ruta_archivo;
            if ( file_exists( $filepath ) ) { unlink( $filepath ); siarhe_delete_data_read_copies( $filepath ); }
            if ( ! empty( $file->ruta_json ) ) {
                $json_path = $upload_dir['basedir'] . '/siarhe-data/' . $file->ruta_json;
                if ( $file->tipo_archivo === 'marcador' ) Siarhe_Marker_Json::borra_entidades( $json_path );
                if ( file_exists( $json_path ) ) unlink( $json_path );
            }
            $wpdb->delete( $table_name, array( 'id' => $file_id ) );
        }
        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=' . $tab_destino . '&status=deleted' ) ); exit;
    }

    // -------------------------------------------------------------------------
    // HANDLERS: TEMAS DE BASES ESTATICAS (alta / baja de los que crea el administrador)
    // -------------------------------------------------------------------------
    private function base_tema_redirect( $status ) {
        wp_safe_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=static&status=' . $status ) );
        exit;
    }

    /** Temas propios guardados, tal como estan en la opcion: array( clave => array( 'label', 'unidades' ) ). */
    private function saved_base_temas() {
        $guardados = siarhe_decode_json_option( get_option( 'siarhe_bases_temas', '' ) );
        return is_array( $guardados ) ? $guardados : array();
    }

    public function handle_base_tema_add() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        check_admin_referer( 'siarhe_base_tema_add', 'tema_nonce' );

        $label = sanitize_text_field( wp_unslash( isset( $_POST['tema_label'] ) ? $_POST['tema_label'] : '' ) );
        $clave = sanitize_key( str_replace( array( ' ', '-' ), '_', remove_accents( wp_unslash( isset( $_POST['tema_clave'] ) ? $_POST['tema_clave'] : '' ) ) ) );
        if ( $clave === '' ) $clave = sanitize_key( str_replace( array( ' ', '-' ), '_', remove_accents( $label ) ) );
        $clave = substr( $clave, 0, 40 );
        if ( $clave === '' || $label === '' ) $this->base_tema_redirect( 'tema_invalid' );
        $temas = siarhe_base_temas();
        if ( isset( $temas[ $clave ] ) ) $this->base_tema_redirect( 'tema_exists' );

        $catalogo = siarhe_base_unidades();
        $unidades = array();
        foreach ( (array) ( isset( $_POST['tema_unidades'] ) ? wp_unslash( $_POST['tema_unidades'] ) : array() ) as $u ) {
            $u = sanitize_key( $u );
            if ( isset( $catalogo[ $u ] ) ) $unidades[] = $u;
        }
        if ( ! $unidades ) $unidades = array( 'frecuencia' );

        $guardados = $this->saved_base_temas();
        $guardados[ $clave ] = array( 'label' => $label, 'unidades' => $unidades );
        update_option( 'siarhe_bases_temas', wp_json_encode( $guardados, JSON_UNESCAPED_UNICODE ) );
        $this->base_tema_redirect( 'tema_added' );
    }

    /** Elimina un tema propio junto con las bases (archivos y registros) cargadas en el. */
    public function handle_base_tema_delete() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        check_admin_referer( 'siarhe_base_tema_delete', 'tema_nonce' );

        $clave = sanitize_key( wp_unslash( isset( $_POST['tema_clave'] ) ? $_POST['tema_clave'] : '' ) );
        $temas = siarhe_base_temas();
        if ( ! isset( $temas[ $clave ] ) || ! empty( $temas[ $clave ]['sistema'] ) ) $this->base_tema_redirect( 'tema_invalid' );

        global $wpdb;
        $table = $wpdb->prefix . 'siarhe_static_assets';
        $bases = $wpdb->get_results( $wpdb->prepare( "SELECT id, ruta_archivo, ruta_json FROM $table WHERE tipo_archivo = 'static_min' AND tema = %s", $clave ) );
        foreach ( (array) $bases as $b ) {
            foreach ( array( $b->ruta_archivo, $b->ruta_json ) as $rel ) {
                if ( empty( $rel ) ) continue;
                $ruta = SIARHE_UPLOAD_DIR . 'static-min/' . basename( $rel );
                if ( is_file( $ruta ) ) { @unlink( $ruta ); siarhe_delete_data_read_copies( $ruta ); }
            }
            $wpdb->delete( $table, array( 'id' => $b->id ) );
        }
        $guardados = $this->saved_base_temas();
        unset( $guardados[ $clave ] );
        update_option( 'siarhe_bases_temas', wp_json_encode( $guardados, JSON_UNESCAPED_UNICODE ) );
        $this->base_tema_redirect( 'tema_deleted' );
    }

    // -------------------------------------------------------------------------
    // VALIDACION DE LOS ARCHIVOS YA CARGADOS
    // Revisa lo que esta en el servidor (de un apartado o de todos) y muestra que archivo no corresponde y donde esta.
    // -------------------------------------------------------------------------
    public function handle_validate_files() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No tienes permisos.' );
        check_admin_referer( 'siarhe_validate_files' );
        set_time_limit( 0 );
        wp_raise_memory_limit( 'admin' );

        $secciones = Siarhe_Validador::secciones();
        $seccion   = isset( $_POST['seccion'] ) ? sanitize_key( wp_unslash( $_POST['seccion'] ) ) : '';
        if ( ! isset( $secciones[ $seccion ] ) ) $seccion = '';
        set_transient( 'siarhe_validacion_' . get_current_user_id(), Siarhe_Validador::revisar( $seccion ), 10 * MINUTE_IN_SECONDS );

        $destino = ( $seccion !== '' ) ? 'admin.php?page=siarhe-uploader&tab=' . $seccion . '&validacion=1' : 'admin.php?page=siarhe-dataviz&validacion=1';
        wp_safe_redirect( admin_url( $destino ) );
        exit;
    }

    /** Boton «Validar archivos cargados»: de un apartado de Carga de Datos o, con $seccion vacia, de todos. */
    public static function render_validate_button( $seccion = '' ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="siarhe-validate-form">';
        echo '<input type="hidden" name="action" value="siarhe_validate_files">';
        echo '<input type="hidden" name="seccion" value="' . esc_attr( $seccion ) . '">';
        wp_nonce_field( 'siarhe_validate_files' );
        echo '<button type="submit" class="button"><span class="dashicons dashicons-yes-alt"></span> Validar archivos cargados</button>';
        self::help( $seccion === ''
            ? 'Revisa todos los archivos que ya están en el servidor (mapas, localidades, bases estáticas y marcadores): que existan y que sean de la entidad, la capa, el año y la unidad donde están cargados. Si alguno no corresponde, dice cuál es y en qué apartado está para reemplazarlo.'
            : 'Revisa los archivos de esta pestaña que ya están en el servidor: que existan y que sean de la entidad y del periodo donde están cargados. Si alguno no corresponde, dice cuál es para reemplazarlo.' );
        echo '</form>';
    }

    /** Muestra (y consume) el resultado de la ultima validacion pedida por el usuario. */
    public static function render_validation_result() {
        if ( ! isset( $_GET['validacion'] ) ) return;
        $clave = 'siarhe_validacion_' . get_current_user_id();
        $res   = get_transient( $clave );
        if ( ! is_array( $res ) ) return;
        delete_transient( $clave );

        $secciones = Siarhe_Validador::secciones();
        $total = array_sum( $res['revisados'] );
        $detalle = array();
        foreach ( $res['revisados'] as $s => $n ) $detalle[] = $secciones[ $s ] . ': ' . (int) $n;
        $resumen = 'Se revisaron ' . (int) $total . ' archivo(s) (' . implode( ' · ', $detalle ) . ').';

        if ( empty( $res['problemas'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Todos los archivos están en su lugar.</strong> ' . esc_html( $resumen ) . '</p></div>';
            return;
        }
        echo '<div class="notice notice-error siarhe-validacion"><p><strong>' . count( $res['problemas'] ) . ' problema(s) encontrados.</strong> ' . esc_html( $resumen )
            . ' Sube de nuevo el archivo correcto en el apartado indicado (lo reemplaza) o elimínalo.</p>';
        echo '<table class="widefat striped siarhe-validacion__tabla"><thead><tr><th>Apartado</th><th>Dónde está cargado</th><th>Archivo</th><th>Problema</th></tr></thead><tbody>';
        foreach ( $res['problemas'] as $p ) {
            $url = admin_url( 'admin.php?page=siarhe-uploader&tab=' . $p['seccion'] );
            echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( $secciones[ $p['seccion'] ] ) . '</a></td><td>' . esc_html( $p['donde'] ) . '</td><td><code>'
                . esc_html( $p['archivo'] ) . '</code></td><td>' . esc_html( $p['problema'] ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    // -------------------------------------------------------------------------
    // HANDLERS: VARIABLES DE MARCADORES (alta / baja / restaurar)
    // Se procesan en admin-post y redirigen (la pagina se recarga ya actualizada).
    // -------------------------------------------------------------------------
    private static function marker_core_types() {
        return siarhe_marker_core_types();
    }

    private function marker_types() {
        return siarhe_marker_types();
    }

    private function save_marker_types( $tipos ) {
        update_option( 'siarhe_archivos_marcadores', wp_json_encode( $tipos, JSON_UNESCAPED_UNICODE ) );
    }

    private function marker_redirect( $status ) {
        wp_safe_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=marcadores&status=' . $status ) );
        exit;
    }

    /** Borra los archivos (CSV y JSON de todos los periodos) y los registros de una variable de marcador. */
    private function purge_marker_asset( $key, $filename ) {
        global $wpdb;
        $table = $wpdb->prefix . 'siarhe_static_assets';
        $dir   = SIARHE_UPLOAD_DIR . 'markers/';
        $rutas = array( $dir . basename( $filename ) ); // archivo sin registro (copiado por FTP)
        $assets = $wpdb->get_results( $wpdb->prepare( "SELECT id, ruta_archivo, ruta_json FROM $table WHERE tipo_archivo = %s AND entidad_slug = %s", 'marcador', $key ) );
        foreach ( (array) $assets as $asset ) {
            $rutas[] = $dir . basename( $asset->ruta_archivo );
            if ( ! empty( $asset->ruta_json ) ) {
                $rutas[] = $dir . basename( $asset->ruta_json );
                Siarhe_Marker_Json::borra_entidades( $dir . basename( $asset->ruta_json ) );
            }
            $wpdb->delete( $table, array( 'id' => $asset->id ) );
        }
        foreach ( array_unique( $rutas ) as $path ) {
            if ( file_exists( $path ) ) { @unlink( $path ); siarhe_delete_data_read_copies( $path ); }
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

        // Serie completa: ademas de un archivo por año admite «Todas» (las unidades de toda la historia)
        $serie = ! isset( $_POST['new_file_serie'] ) || $_POST['new_file_serie'] !== '0';
        $tipos[ $key ] = array( 'label' => $label, 'filename' => $file, 'is_core' => false, 'periodos' => $serie ? 'total_anios' : 'anios' );
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

    /**
     * Personalizar › Mapa y Colores: quita lo guardado para que vuelvan a regir los valores del sistema.
     * `que` = mapa (colores de los indicadores), marcadores (estilos de todos los marcadores) o todo.
     */
    public function handle_reset_colors() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sin permisos.' );
        check_admin_referer( 'siarhe_reset_colors' );

        $que  = isset( $_GET['que'] ) ? sanitize_key( $_GET['que'] ) : 'todo';
        $opts = get_option( 'siarhe_map_options', array() );
        if ( ! is_array( $opts ) ) $opts = array();

        if ( $que === 'mapa' || $que === 'todo' ) {
            foreach ( siarhe_map_color_keys() as $clave ) unset( $opts[ $clave ] );
        }
        if ( $que === 'marcadores' || $que === 'todo' ) {
            foreach ( array_keys( $opts ) as $clave ) {
                if ( preg_match( '/^m_.+_(shape|fill|fill2|stroke)$/', $clave ) ) unset( $opts[ $clave ] );
            }
        }
        update_option( 'siarhe_map_options', $opts );

        wp_safe_redirect( admin_url( 'admin.php?page=siarhe-customize&tab=mapa&siarhe_reset=' . ( in_array( $que, array( 'mapa', 'marcadores' ), true ) ? $que : 'todo' ) ) );
        exit;
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
    /**
     * Guarda el archivo de un tipo de marcador para un periodo (un año de datos o "total"). Se acepta CSV o la capa en
     * JSON (formato de RHEMX): el que se sube se valida y de el se obtiene el otro, asi los dos quedan siempre equivalentes.
     * Los archivos se guardan como {nombre del tipo}-{periodo}.csv y .json.
     */
    public function handle_marker_upload() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No tienes permisos.' );
        check_admin_referer( 'siarhe_upload_marker_nonce', 'marker_nonce' );

        set_time_limit( 0 );
        wp_raise_memory_limit( 'admin' );

        $tipos = siarhe_marker_types();
        $tipo  = isset( $_POST['marker_type'] ) ? sanitize_text_field( wp_unslash( $_POST['marker_type'] ) ) : '';
        if ( ! isset( $tipos[ $tipo ] ) ) $this->upload_fail( 'Tipo de marcador no válido.' );

        $periodo = Siarhe_Marker_Json::periodo( isset( $_POST['periodo'] ) ? wp_unslash( $_POST['periodo'] ) : '' );
        if ( $periodo === '' ) $this->upload_fail( 'Elige el periodo de los datos: un año o «Toda la historia».' );
        if ( $periodo === Siarhe_Marker_Json::PERIODO_TOTAL && ! siarhe_marker_type_has_total( $tipos[ $tipo ] ) ) {
            $this->upload_fail( '«' . $tipos[ $tipo ]['label'] . '» se carga por año: elige el año de los datos.' );
        }
        $anio_db = ( $periodo === Siarhe_Marker_Json::PERIODO_TOTAL ) ? 0 : (int) $periodo;

        if ( ! isset( $_FILES['marker_file'] ) || empty( $_FILES['marker_file']['name'] ) ) $this->upload_fail( 'No se seleccionó ningún archivo.' );
        if ( $_FILES['marker_file']['error'] != 0 ) {
            $this->upload_fail( 'El servidor rechazó el archivo (código de error PHP: ' . (int) $_FILES['marker_file']['error'] . '). El tamaño máximo de carga es ' . ini_get( 'upload_max_filesize' ) . '.' );
        }
        $this->validate_upload( $_FILES['marker_file'], array( 'csv', 'json' ) );
        $ext = strtolower( pathinfo( sanitize_file_name( $_FILES['marker_file']['name'] ), PATHINFO_EXTENSION ) );
        $tmp = $_FILES['marker_file']['tmp_name'];

        $fecha_corte = sanitize_text_field( wp_unslash( isset( $_POST['fecha_corte'] ) ? $_POST['fecha_corte'] : '' ) );
        $referencia  = wp_kses_post( wp_unslash( isset( $_POST['referencia'] ) ? $_POST['referencia'] : '' ) );
        $comentarios = sanitize_textarea_field( wp_unslash( isset( $_POST['comentarios'] ) ? $_POST['comentarios'] : '' ) );
        $current_user = wp_get_current_user();
        $editor_name  = $current_user->display_name ?: $current_user->user_login;

        $dest_dir = SIARHE_UPLOAD_DIR . 'markers/';
        if ( ! file_exists( $dest_dir ) ) wp_mkdir_p( $dest_dir );
        $base      = preg_replace( '/\.csv$/i', '', $tipos[ $tipo ]['filename'] ) . '-' . $periodo;
        $csv_file  = $dest_dir . $base . '.csv';
        $json_file = $dest_dir . $base . '.json';

        global $wpdb;
        $table_name = $wpdb->prefix . 'siarhe_static_assets';
        $existe = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, ruta_archivo FROM $table_name WHERE entidad_slug = %s AND tipo_archivo = 'marcador' AND anio_reporte = %d ORDER BY id DESC LIMIT 1",
            $tipo, $anio_db
        ) );
        // El archivo vigente de este periodo puede tener otro nombre (los cargados antes de los periodos: {nombre}.csv)
        $anterior = ( $existe && $existe->ruta_archivo !== 'markers/' . $base . '.csv' ) ? SIARHE_UPLOAD_DIR . ltrim( $existe->ruta_archivo, '/' ) : '';

        $avisos = array();
        if ( $ext === 'json' ) {
            $json = json_decode( (string) file_get_contents( $tmp ), true );
            $revision = Siarhe_Marker_Json::validar( $json );
            if ( $revision['error'] !== '' ) $this->upload_fail( $revision['error'] );
            $avisos = $revision['avisos'];
            $json['formato'] = Siarhe_Marker_Json::FORMATO;
            $json['version'] = Siarhe_Marker_Json::VERSION;
            $json['periodo'] = $periodo;

            $this->archive_previous_version( $anterior !== '' ? $anterior : $csv_file );
            $this->archive_previous_version( $json_file );
            $ok = file_put_contents( $json_file, wp_json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) !== false
               && Siarhe_Marker_Json::a_csv_archivo( $json, $csv_file );
            unset( $json );
            if ( ! $ok ) $this->upload_fail( 'No se pudieron guardar los archivos. Verifica los permisos de escritura de la carpeta de datos.' );
        } else {
            $avisos = $this->validate_csv_file( $tmp, 'marker', $tipo );
            $this->archive_previous_version( $anterior !== '' ? $anterior : $csv_file );
            $this->archive_previous_version( $json_file );
            if ( ! move_uploaded_file( $tmp, $csv_file ) ) $this->upload_fail( 'No se pudo guardar el archivo. Verifica los permisos de escritura de la carpeta de datos.' );
            $this->ensure_utf8_csv( $csv_file );
            $resultado = Siarhe_Marker_Json::desde_csv_archivo( $csv_file, $json_file, $periodo );
            if ( ! is_int( $resultado ) ) {
                if ( is_file( $json_file ) ) @unlink( $json_file ); // un JSON anterior ya no corresponde a este CSV
                $avisos[] = 'No se pudo crear el JSON del marcador (' . $resultado . '); el mapa leerá el CSV.';
            }
        }
        if ( $anterior !== '' && $anterior !== $csv_file && is_file( $anterior ) ) {
            @unlink( $anterior );
            siarhe_delete_data_read_copies( $anterior );
        }
        siarhe_build_read_copy( $csv_file );
        $ruta_json = is_file( $json_file ) ? 'markers/' . $base . '.json' : '';

        // Version por entidad: el mapa de cada entidad lee una capa que solo trae sus puntos
        Siarhe_Marker_Json::borra_entidades( $json_file );
        if ( $ruta_json !== '' && Siarhe_Marker_Json::divide_por_entidad( $json_file ) === 0 ) {
            $avisos[] = 'No se crearon las versiones por entidad de la capa (no trae la clave de la entidad); los mapas de las entidades leerán la capa completa.';
        }
        $this->flash_notice( $avisos );

        if ( $existe ) {
            $wpdb->update( $table_name, array(
                'ruta_archivo' => 'markers/' . $base . '.csv', 'ruta_json' => $ruta_json,
                'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia, 'comentarios' => $comentarios, 'es_activo' => 1,
                'modificado_por' => $editor_name, 'fecha_modificacion' => current_time( 'mysql' ),
            ), array( 'id' => $existe->id ), array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ), array( '%d' ) );
        } else {
            $wpdb->insert( $table_name, array(
                'entidad_slug' => $tipo, 'tipo_archivo' => 'marcador',
                'ruta_archivo' => 'markers/' . $base . '.csv', 'ruta_json' => $ruta_json,
                'anio_reporte' => $anio_db, 'fecha_corte' => $fecha_corte, 'referencia_bibliografica' => $referencia,
                'comentarios' => $comentarios, 'es_activo' => 1, 'fecha_subida' => current_time( 'mysql' ),
                'subido_por' => $editor_name, 'modificado_por' => $editor_name, 'fecha_modificacion' => current_time( 'mysql' ),
            ), array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
        }

        wp_redirect( admin_url( 'admin.php?page=siarhe-uploader&tab=marcadores&status=success' ) );
        exit;
    }

}