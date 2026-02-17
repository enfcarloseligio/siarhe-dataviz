<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Siarhe_Shortcodes {

    public function init() {
        add_action( 'init', array( $this, 'register_dynamic_shortcodes' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
    }

    /**
     * Registra Shortcodes Dinámicos basados en CVE_ENT.
     * Ejemplo de uso: [siarhe_mapa_MT_CVE_ENT_33]
     */
    public function register_dynamic_shortcodes() {
        $entities = siarhe_get_entities();

        foreach ( $entities as $slug => $data ) {
            $cve_ent = $data['CVE_ENT']; // Ej: "33", "01" (Ahora viene de la key en mayúsculas)

            // 1. Mapa + Tabla (MT)
            add_shortcode( "siarhe_mapa_MT_CVE_ENT_{$cve_ent}", function($atts) use ($slug, $cve_ent) {
                return $this->render_viz( $slug, $cve_ent, 'MT' );
            });

            // 2. Solo Mapa (M)
            add_shortcode( "siarhe_mapa_M_CVE_ENT_{$cve_ent}", function($atts) use ($slug, $cve_ent) {
                return $this->render_viz( $slug, $cve_ent, 'M' );
            });

            // 3. Solo Tabla (T)
            add_shortcode( "siarhe_mapa_T_CVE_ENT_{$cve_ent}", function($atts) use ($slug, $cve_ent) {
                return $this->render_viz( $slug, $cve_ent, 'T' );
            });
        }
    }

    public function render_viz( $slug, $cve_ent, $mode ) {
        global $wpdb;
        $table_assets = $wpdb->prefix . 'siarhe_static_assets';
        
        $geojson = $wpdb->get_row( $wpdb->prepare( "SELECT ruta_archivo, anio_reporte FROM $table_assets WHERE entidad_slug = %s AND tipo_archivo = 'geojson' AND es_activo = 1", $slug ) );
        $csv     = $wpdb->get_row( $wpdb->prepare( "SELECT ruta_archivo FROM $table_assets WHERE entidad_slug = %s AND tipo_archivo = 'static_min' AND es_activo = 1", $slug ) );

        $geojson_url = $geojson ? SIARHE_UPLOAD_URL . $geojson->ruta_archivo : '';
        $csv_url     = $csv ? SIARHE_UPLOAD_URL . $csv->ruta_archivo : '';
        $anio        = $geojson ? $geojson->anio_reporte : date('Y');

        // ID del DOM único para JS
        $dom_id = 'siarhe-viz-' . $cve_ent . '-' . strtolower($mode);
        
        // Atributos de datos usando la nomenclatura estricta
        $output  = '<div class="siarhe-viz-wrapper" id="' . esc_attr($dom_id) . '" ';
        $output .= 'data-cve-ent="' . esc_attr($cve_ent) . '" '; // Clave INEGI
        $output .= 'data-slug="' . esc_attr($slug) . '" ';
        $output .= 'data-mode="' . esc_attr($mode) . '" ';
        $output .= 'data-geojson="' . esc_url($geojson_url) . '" ';
        $output .= 'data-csv="' . esc_url($csv_url) . '" ';
        $output .= '>';

        $output .= '<div class="siarhe-loading-overlay">Cargando datos SIARHE (' . $anio . ')...</div>';
        
        if ( strpos($mode, 'M') !== false ) {
            $output .= '<div class="siarhe-map-container" style="width:100%; height:600px; background:#f9f9f9; border:1px solid #ddd; position:relative;"></div>';
        }
        if ( strpos($mode, 'T') !== false ) {
            $output .= '<div class="siarhe-table-container" style="margin-top:20px;"></div>';
        }

        $output .= '</div>';
        return $output;
    }

    public function enqueue_frontend_scripts() {
        // Pendiente: Cargar D3.js y scripts de visualización
    }
}