<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Siarhe_Shortcodes {

    public function init() {
        // Registrar shortcodes dinámicos al iniciar WP
        add_action( 'init', array( $this, 'register_dynamic_shortcodes' ) );
        
        // Cargar scripts frontend (D3, Leaflet, CSS propios) solo en el frontend
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
    }

    /**
     * Registra Shortcodes Dinámicos basados en CVE_ENT.
     * Ejemplo de uso: [siarhe_mapa_MT_CVE_ENT_33]
     */
    public function register_dynamic_shortcodes() {
        // Obtenemos las entidades desde el helper centralizado
        $entities = siarhe_get_entities();

        foreach ( $entities as $slug => $data ) {
            $cve_ent = $data['CVE_ENT']; // Ej: "33", "01" (Usamos la clave INEGI estricta)
            $nombre_entidad = $data['nombre']; // Ej: "Aguascalientes"

            // 1. Shortcode: Mapa + Tabla (MT)
            add_shortcode( "siarhe_mapa_MT_CVE_ENT_{$cve_ent}", function($atts) use ($slug, $cve_ent, $nombre_entidad) {
                return $this->render_viz( $slug, $cve_ent, 'MT', $nombre_entidad );
            });

            // 2. Shortcode: Solo Mapa (M)
            add_shortcode( "siarhe_mapa_M_CVE_ENT_{$cve_ent}", function($atts) use ($slug, $cve_ent, $nombre_entidad) {
                return $this->render_viz( $slug, $cve_ent, 'M', $nombre_entidad );
            });

            // 3. Shortcode: Solo Tabla (T)
            add_shortcode( "siarhe_mapa_T_CVE_ENT_{$cve_ent}", function($atts) use ($slug, $cve_ent, $nombre_entidad) {
                return $this->render_viz( $slug, $cve_ent, 'T', $nombre_entidad );
            });
        }
    }

    /**
     * Renderiza la vista utilizando la plantilla PHP separada.
     */
    public function render_viz( $slug, $cve_ent, $mode, $nombre_entidad = '' ) {
        global $wpdb;
        $table_assets = $wpdb->prefix . 'siarhe_static_assets';
        
        // 1. Buscar GeoJSON activo (y sus metadatos)
        $geojson = $wpdb->get_row( $wpdb->prepare( 
            "SELECT ruta_archivo, anio_reporte, fecha_corte, referencia_bibliografica 
             FROM $table_assets WHERE entidad_slug = %s AND tipo_archivo = 'geojson' AND es_activo = 1", 
            $slug 
        ));

        // 2. Buscar CSV Estático activo (y sus metadatos)
        $csv = $wpdb->get_row( $wpdb->prepare( 
            "SELECT ruta_archivo, anio_reporte, fecha_corte, referencia_bibliografica 
             FROM $table_assets WHERE entidad_slug = %s AND tipo_archivo = 'static_min' AND es_activo = 1", 
            $slug 
        ));

        // Construir URLs completas
        $geojson_url = $geojson ? SIARHE_UPLOAD_URL . $geojson->ruta_archivo : '';
        $csv_url     = $csv ? SIARHE_UPLOAD_URL . $csv->ruta_archivo : '';
        $anio        = $geojson ? $geojson->anio_reporte : date('Y');

        // Preparar Referencias y Fechas para la vista
        $geo_ref     = $geojson ? $geojson->referencia_bibliografica : 'SIARHE';
        $geo_date    = $geojson ? date_i18n('d/M/Y', strtotime($geojson->fecha_corte)) : '';
        
        $csv_ref     = $csv ? $csv->referencia_bibliografica : 'SIARHE';
        $csv_date    = $csv ? date_i18n('d/M/Y', strtotime($csv->fecha_corte)) : '';

        // VALIDACIÓN DE ERRORES VISIBLES
        if ( empty($geojson_url) ) {
            return "<div style='background:#fff5f5; border:1px solid #fc8181; padding:20px; color:#c53030; border-radius:4px; margin:20px 0;'>
                        <strong>⚠️ Configuración Incompleta:</strong><br>
                        No se encontró un archivo GeoJSON activo para <em>$nombre_entidad ($slug)</em>.<br>
                        <small>Ve al Admin > SIARHE > Carga de Datos y sube el mapa correspondiente.</small>
                   </div>";
        }

        // 3. Cargar la Plantilla PHP
        ob_start();
        
        $template_path = SIARHE_PATH . 'public/partials/siarhe-view.php';
        
        if ( file_exists( $template_path ) ) {
            // Las variables estarán disponibles automáticamente dentro del include.
            include $template_path;
        } else {
            if ( current_user_can('manage_options') ) {
                echo "<div style='border:1px solid red; padding:10px;'>Error CRÍTICO SIARHE: No se encuentra la plantilla en <code>$template_path</code></div>";
            }
        }

        return ob_get_clean();
    }

    /**
     * Carga los scripts y estilos necesarios en el Frontend.
     * E inyecta las variables CSS dinámicas desde la configuración.
     */
    public function enqueue_frontend_scripts() {
        // 1. Cargar estilos Frontend
        wp_enqueue_style( 'siarhe-frontend-css', SIARHE_URL . 'public/css/siarhe-frontend.css', array(), SIARHE_VERSION );

        // 2. INYECTAR VARIABLES CSS DINÁMICAS (Colores)
        $opts = get_option( 'siarhe_map_options', [] );
        
        $defaults = [
            'map_c1' => '#eff3ff', 'map_c2' => '#bdd7e7', 'map_c3' => '#9ecae1', 'map_c4' => '#6baed6', 'map_c5' => '#08519c',
            'map_zero' => '#d9d9d9', 'map_null' => '#000000',
            
            'th_bg' => '#f4f4f4', 'th_text' => '#333', 
            'tr_odd' => '#fff', 'tr_odd_txt' => '#555',
            'tr_even' => '#f9f9f9', 'tr_even_txt' => '#555',
            'tr_total_bg' => '#e8f4fd', 'tr_total_txt' => '#000', 
            'border_color' => '#ddd', 'border_width' => '1'
        ];
        
        $c = wp_parse_args( $opts, $defaults );

        // Generamos el bloque de CSS Variables
        $custom_css = "
            :root {
                /* Mapa */
                --s-map-c1: {$c['map_c1']};
                --s-map-c2: {$c['map_c2']};
                --s-map-c3: {$c['map_c3']};
                --s-map-c4: {$c['map_c4']};
                --s-map-c5: {$c['map_c5']};
                --s-map-zero: {$c['map_zero']};
                --s-map-null: {$c['map_null']};
                
                /* Tabla */
                --s-th-bg: {$c['th_bg']};
                --s-th-text: {$c['th_text']};
                --s-tr-odd: {$c['tr_odd']};
                --s-tr-odd-txt: {$c['tr_odd_txt']};
                --s-tr-even: {$c['tr_even']};
                --s-tr-even-txt: {$c['tr_even_txt']};
                --s-total-bg: {$c['tr_total_bg']};
                --s-total-txt: {$c['tr_total_txt']};
                --s-border: {$c['border_width']}px solid {$c['border_color']};
            }
        ";
        wp_add_inline_style( 'siarhe-frontend-css', $custom_css );

        // 3. Cargar D3.js (Librería Externa - Versión 7)
        wp_enqueue_script( 'd3-js', 'https://d3js.org/d3.v7.min.js', array(), '7.0.0', true );

        // 4. Cargar nuestro Script Principal (Depende de D3.js)
        wp_enqueue_script( 'siarhe-viz-js', SIARHE_URL . 'public/js/siarhe-viz.js', array('d3-js'), SIARHE_VERSION, true );

        // 5. INYECTAR DATOS DINÁMICOS A JS (Marcadores, URLs, Home)
        // Esto permite que el JS sepa a dónde navegar y dónde están los CSVs de marcadores
        $entities = siarhe_get_entities();
        $urls_map = [];
        foreach ( $entities as $slug => $data ) {
            // Estructura de URL: /entidad/nombre-entidad/
            $urls_map[$data['CVE_ENT']] = home_url( "/entidad/$slug/" );
        }

        $siarhe_data = [
            'entity_urls' => $urls_map,
            'home_url'    => home_url( '/mapa-nacional/' ), // URL del mapa principal
            'markers'     => [
                // Rutas a los CSV de marcadores (subidos en la carpeta de uploads del plugin)
                'CATETER' => SIARHE_UPLOAD_URL . 'clinicas-cateteres.csv',
                'HERIDAS' => SIARHE_UPLOAD_URL . 'clinicas-heridas.csv'
            ]
        ];

        wp_localize_script( 'siarhe-viz-js', 'siarheData', $siarhe_data );
    }
}