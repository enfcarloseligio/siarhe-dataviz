<?php // /includes/class-siarhe-shortcodes.php
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
        // Respaldo: shortcodes fuera del contenido principal (widgets, builders)
        wp_enqueue_style( 'siarhe-frontend-css' );

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
        if ( empty($geojson_url) && strpos($mode, 'M') !== false ) {
            return "<div style='background:#fff5f5; border:1px solid #fc8181; padding:20px; color:#c53030; border-radius:4px; margin:20px 0;'>
                        <strong>⚠️ Configuración Incompleta (Mapa):</strong><br>
                        No se encontró un archivo GeoJSON activo para <em>" . esc_html( $nombre_entidad . ' (' . $slug . ')' ) . "</em>.<br>
                        <small>Ve al Admin > SIARHE > Carga de Datos y sube el mapa correspondiente.</small>
                   </div>";
        }
        
        if ( empty($csv_url) && strpos($mode, 'T') !== false ) {
            return "<div style='background:#fff5f5; border:1px solid #fc8181; padding:20px; color:#c53030; border-radius:4px; margin:20px 0;'>
                        <strong>⚠️ Configuración Incompleta (Datos):</strong><br>
                        No se encontró un archivo CSV activo para <em>" . esc_html( $nombre_entidad . ' (' . $slug . ')' ) . "</em>.<br>
                        <small>Ve al Admin > SIARHE > Carga de Datos y sube la base estática correspondiente.</small>
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
                echo "<div style='border:1px solid red; padding:10px;'>Error CRÍTICO SIARHE: No se encuentra la plantilla en <code>" . esc_html( $template_path ) . "</code></div>";
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
        wp_register_style( 'siarhe-frontend-css', SIARHE_URL . 'public/css/siarhe-frontend.css', array(), SIARHE_VERSION );

        // 2. Variables CSS dinamicas (colores): unico punto de inyeccion, ver siarhe_get_css_variables_css()
        wp_add_inline_style( 'siarhe-frontend-css', siarhe_get_css_variables_css() );

        // 3. Registrar D3.js (se encola como dependencia de siarhe-core-js solo cuando hay un shortcode)
        wp_register_script( 'd3-js', 'https://d3js.org/d3.v7.min.js', array(), '7.0.0', true );

        // 4. Si el contenido actual ya usa un shortcode del plugin, el CSS se encola en <head>
        if ( is_singular() && preg_match( '/\[siarhe_mapa_(?:M|T|MT)_CVE_ENT_/', (string) get_post_field( 'post_content', get_queried_object_id() ) ) ) {
            wp_enqueue_style( 'siarhe-frontend-css' );
        }
    }
}