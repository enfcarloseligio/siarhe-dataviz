<?php // /public/partials/siarhe-view.php
if ( ! defined( 'ABSPATH' ) ) exit;

// CARGA INTELIGENTE DE MÓDULOS JAVASCRIPT SEGÚN EL MODO
// Version por archivo (filemtime): el navegador cachea el JS y se invalida solo al modificarlo.
$siarhe_js_ver = function( $file ) {
    $path = SIARHE_PATH . 'public/js/' . $file;
    return file_exists( $path ) ? filemtime( $path ) : SIARHE_VERSION;
};

// 1. Core y Controles (Siempre se necesitan para leer datos y selectores)
wp_enqueue_script( 'siarhe-core-js', SIARHE_URL . 'public/js/siarhe-core.js', array('d3-js'), $siarhe_js_ver('siarhe-core.js'), true );
wp_enqueue_script( 'siarhe-controls-js', SIARHE_URL . 'public/js/siarhe-controls.js', array('siarhe-core-js'), $siarhe_js_ver('siarhe-controls.js'), true );

// 2. Mapa (Solo si el shortcode incluye la letra 'M')
if ( strpos( strtoupper($mode), 'M' ) !== false ) {
    // Cargamos el gestor de Tooltips antes del mapa
    wp_enqueue_script( 'siarhe-tooltips-js', SIARHE_URL . 'public/js/siarhe-tooltips.js', array('siarhe-core-js'), $siarhe_js_ver('siarhe-tooltips.js'), true );
    wp_enqueue_script( 'siarhe-map-js', SIARHE_URL . 'public/js/siarhe-map.js', array('siarhe-core-js', 'siarhe-controls-js', 'siarhe-tooltips-js'), $siarhe_js_ver('siarhe-map.js'), true );
}

// 3. Tabla (Solo si el shortcode incluye la letra 'T')
if ( strpos( strtoupper($mode), 'T' ) !== false ) {
    wp_enqueue_script( 'siarhe-table-js', SIARHE_URL . 'public/js/siarhe-table.js', array('siarhe-core-js', 'siarhe-controls-js'), $siarhe_js_ver('siarhe-table.js'), true );
}

// Año de los metadatos de la Base Estática (CSV)
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$csv_meta = $wpdb->get_row( $wpdb->prepare( "SELECT anio_reporte FROM $table_assets WHERE entidad_slug = %s AND tipo_archivo = 'static_min' AND es_activo = 1", $slug ) );

if ( $csv_meta && !empty($csv_meta->anio_reporte) ) {
    $anio = $csv_meta->anio_reporte; 
}

// CONSULTA DE METADATOS DEL MARCADOR DE ESTABLECIMIENTOS PARA REFERENCIA
$mk_meta = $wpdb->get_row( "SELECT referencia_bibliografica, fecha_corte FROM $table_assets WHERE entidad_slug = 'ESTABLECIMIENTOS' AND tipo_archivo = 'marcador' AND es_activo = 1" );
$mk_ref  = ($mk_meta && !empty($mk_meta->referencia_bibliografica)) ? $mk_meta->referencia_bibliografica : 'Catálogo de Establecimientos de Salud.';
$mk_date = ($mk_meta && !empty($mk_meta->fecha_corte)) ? date_i18n('d/M/Y', strtotime($mk_meta->fecha_corte)) : '—';


// Catálogo de Entidades Federativas (INEGI)
$catalogo_oficial = siarhe_get_entity_catalog();

$es_nacional = ($slug === 'republica-mexicana' || $cve_ent === '33');
$lugar_texto = esc_html( isset($catalogo_oficial[$cve_ent]) ? $catalogo_oficial[$cve_ent] : $nombre_entidad );

// 1. OBTENER CONFIGURACIÓN DE COLORES BASE
$opts = siarhe_get_map_options();

// 🌟 UTILIDADES BASE PARA CACHE BUSTING
$upload_base_dir_front = defined('SIARHE_UPLOAD_DIR') ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/';
$upload_base_url_front = defined('SIARHE_UPLOAD_URL') ? SIARHE_UPLOAD_URL : wp_upload_dir()['baseurl'] . '/siarhe-data/';

// 2. MOTOR DINÁMICO DE MARCADORES
$marcadores_json = get_option( 'siarhe_marcadores_config', '' );
$marcadores_array = json_decode( wp_unslash( $marcadores_json ), true );

if (empty($marcadores_array)) {
    $marcadores_array = [
        'CATETER' => ['label' => 'Clínicas de catéteres', 'archivo' => 'clinicas-cateteres.csv', 'visibilidad' => 'publico'],
        'HERIDAS' => ['label' => 'Clínicas de heridas', 'archivo' => 'clinicas-heridas.csv', 'visibilidad' => 'publico']
    ];
}

$marker_config = [];
$marker_urls = [];
$marker_labels = []; 
$is_user_logged_in = is_user_logged_in();

// OBTENER Y FIRMAR GEOJSON DE LOCALIDADES
$geo_loc_meta = $wpdb->get_row( $wpdb->prepare( "SELECT ruta_archivo FROM $table_assets WHERE entidad_slug = %s AND tipo_archivo = 'localidades_geojson' AND es_activo = 1", $slug ) );
$geojson_loc_url = '';
if ($geo_loc_meta && !empty($geo_loc_meta->ruta_archivo)) {
    $ruta_fisica_loc = $upload_base_dir_front . ltrim($geo_loc_meta->ruta_archivo, '/');
    $v_loc = file_exists($ruta_fisica_loc) ? filemtime($ruta_fisica_loc) : time();
    $geojson_loc_url = siarhe_data_read_url( $upload_base_url_front . ltrim($geo_loc_meta->ruta_archivo, '/') . '?v=' . $v_loc );
}

foreach ($marcadores_array as $key => $mk) {
    $visibilidad = isset($mk['visibilidad']) ? $mk['visibilidad'] : 'publico';
    
    if ($visibilidad === 'oculto') continue; 
    if ($visibilidad === 'registrados' && !$is_user_logged_in) continue;
    
    $s_key = strtolower($key);
    
    // 🌟 CACHE BUSTING PARA MARCADORES
    $ruta_mk = $upload_base_dir_front . 'markers/' . $mk['archivo'];
    $v_mk = file_exists($ruta_mk) ? filemtime($ruta_mk) : time();
    
    $marker_urls[$key] = siarhe_data_read_url( $upload_base_url_front . 'markers/' . $mk['archivo'] . '?v=' . $v_mk );
    $marker_labels[$key] = $mk; 
    
    $marker_config[$key] = [
        'shape' => isset($opts["m_{$s_key}_shape"]) ? $opts["m_{$s_key}_shape"] : 'circle',
        'fill'  => isset($opts["m_{$s_key}_fill"]) ? $opts["m_{$s_key}_fill"] : '#0A66C2',
        'fill2' => isset($opts["m_{$s_key}_fill2"]) ? $opts["m_{$s_key}_fill2"] : '', // Spike: color del pico mas bajo
        'stroke'=> isset($opts["m_{$s_key}_stroke"]) ? $opts["m_{$s_key}_stroke"] : '#ffffff',
    ];
}


// LECTURA DINÁMICA DE MÉTRICAS 
$metricas_json = get_option( 'siarhe_metricas_config', '' );
$metricas_array = json_decode( wp_unslash( $metricas_json ), true );

if ( empty($metricas_array) || !is_array($metricas_array) ) {
    $metricas_array = [
        'tasa_total'                 => ['label' => 'Tasa Total', 'fullLabel' => 'Tasa de enfermeras por cada mil habitantes', 'tipo' => 'tasa', 'pair' => 'enfermeras_total'],
        'enfermeras_total'           => ['label' => 'Total Enf.', 'fullLabel' => 'Total de profesionales de enfermería', 'tipo' => 'absoluto', 'pair' => 'enfermeras_total'],
        'poblacion'                  => ['label' => 'Población', 'fullLabel' => 'Población total', 'tipo' => 'absoluto', 'pair' => 'poblacion']
    ];
}

$metricas_filtradas = [];
foreach ($metricas_array as $key => $metrica) {
    $visibilidad = isset($metrica['visibilidad']) ? $metrica['visibilidad'] : 'publico';
    if ($visibilidad === 'oculto') { continue; }
    if ($visibilidad === 'registrados' && !$is_user_logged_in) { continue; }
    if (!isset($metrica['abrev']) || empty(trim($metrica['abrev']))) { $metrica['abrev'] = mb_substr($metrica['label'], 0, 8) . '.'; }
    $metricas_filtradas[$key] = $metrica;
}
$metricas_clean_json = wp_json_encode($metricas_filtradas);


// LECTURA DE CONFIGURACIÓN DE TOOLTIPS
$tooltip_json = get_option( 'siarhe_tooltip_config', '' );
if ( empty($tooltip_json) ) {
    $defaults_tt = [
        'geo_pob' => true, 'geo_abs' => true, 'geo_rate' => true,
        'geo_order' => ['pob', 'abs', 'rate'],
        'mk_inst' => true, 'mk_mun' => true, 'mk_clues' => true,
        'mk_tipo' => true, 'mk_nivel' => true, 'mk_juris' => true,
        'bg_color' => '#0f172a', 'bg_opacity' => '90', 'text_color' => '#f8fafc',
        'highlight_var' => 'rate', 'highlight_color' => '#06b6d4',
        'mk_order' => ['mk_inst', 'mk_clues', 'mk_tipo', 'mk_nivel', 'mk_separator', 'mk_juris', 'mk_mun'],
        'mk_bg_color' => '#0f172a', 'mk_bg_opacity' => '90', 'mk_text_color' => '#f8fafc',
        'mk_highlight_var' => 'none', 'mk_highlight_color' => '#06b6d4'
    ];
    $tooltip_json = wp_json_encode($defaults_tt);
} else {
    $tooltip_json = wp_unslash($tooltip_json);
}

// PROCESAMIENTO DE ENLACES DE NAVEGACIÓN
$siarhe_links_raw = get_option( 'siarhe_links_map', [] );
$entity_urls = [];
$home_url = '';

$entidades_mapa = [
    '01' => 'aguascalientes', '02' => 'baja-california', '03' => 'baja-california-sur', '04' => 'campeche',
    '05' => 'coahuila', '06' => 'colima', '07' => 'chiapas', '08' => 'chihuahua', '09' => 'ciudad-de-mexico',
    '10' => 'durango', '11' => 'guanajuato', '12' => 'guerrero', '13' => 'hidalgo', '14' => 'jalisco',
    '15' => 'mexico', '16' => 'michoacan', '17' => 'morelos', '18' => 'nayarit', '19' => 'nuevo-leon',
    '20' => 'oaxaca', '21' => 'puebla', '22' => 'queretaro', '23' => 'quintana-roo', '24' => 'san-luis-potosi',
    '25' => 'sinaloa', '26' => 'sonora', '27' => 'tabasco', '28' => 'tamaulipas', '29' => 'tlaxcala',
    '30' => 'veracruz', '31' => 'yucatan', '32' => 'zacatecas'
];

foreach ($entidades_mapa as $cve => $estado_slug) {
    if ( !empty($siarhe_links_raw[$estado_slug]) ) {
        $val = $siarhe_links_raw[$estado_slug];
        if ( strpos((string)$val, 'cat_') === 0 ) {
            $cat_id = (int) str_replace('cat_', '', $val);
            $url = get_term_link($cat_id, 'category');
        } else {
            $url = get_permalink((int)$val);
        }
        if ( !is_wp_error($url) && !empty($url) ) {
            $entity_urls[strval($cve)] = $url; 
        }
    }
}

if ( !empty($siarhe_links_raw['republica-mexicana']) ) {
    $val = $siarhe_links_raw['republica-mexicana'];
    $url = (strpos((string)$val, 'cat_') === 0) ? get_term_link((int)str_replace('cat_', '', $val), 'category') : get_permalink((int)$val);
    if ( !is_wp_error($url) && !empty($url) ) $home_url = $url;
}

// OBTENER ENLACES LEGALES DINÁMICOS 
$url_terminos = 'https://enfcarloseligio.com/terminos-y-condiciones/';
if ( !empty($siarhe_links_raw['legal_terminos']) ) {
    $val = $siarhe_links_raw['legal_terminos'];
    $url = (strpos((string)$val, 'cat_') === 0) ? get_term_link((int)str_replace('cat_', '', $val), 'category') : get_permalink((int)$val);
    if ( !is_wp_error($url) && !empty($url) ) $url_terminos = $url;
}

$url_aviso = 'https://enfcarloseligio.com/descargo-de-responsabilidades/';
if ( !empty($siarhe_links_raw['legal_aviso']) ) {
    $val = $siarhe_links_raw['legal_aviso'];
    $url = (strpos((string)$val, 'cat_') === 0) ? get_term_link((int)str_replace('cat_', '', $val), 'category') : get_permalink((int)$val);
    if ( !is_wp_error($url) && !empty($url) ) $url_aviso = $url;
}

// 🌟 FIRMAR LOS ARCHIVOS PRINCIPALES CON CACHE BUSTING ANTES DE ENVIARLOS A JS
if (!empty($csv_url) && strpos($csv_url, '?v=') === false) {
    $rel_path = str_replace($upload_base_url_front, '', $csv_url);
    $phys_path = $upload_base_dir_front . ltrim($rel_path, '/');
    $csv_url .= '?v=' . (file_exists($phys_path) ? filemtime($phys_path) : time());
}
$csv_url = siarhe_data_read_url( $csv_url );

if (!empty($geojson_url) && strpos($geojson_url, '?v=') === false) {
    $rel_path = str_replace($upload_base_url_front, '', $geojson_url);
    $phys_path = $upload_base_dir_front . ltrim($rel_path, '/');
    $geojson_url .= '?v=' . (file_exists($phys_path) ? filemtime($phys_path) : time());
}
$geojson_url = siarhe_data_read_url( $geojson_url );
?>

<div id="content" tabindex="-1" style="outline: none;"></div>

<div class="siarhe-viz-wrapper" 
     role="main" aria-label="Visualización de Datos y Mapa SIARHE"
     id="siarhe-viz-<?php echo esc_attr($cve_ent); ?>"
     data-cve-ent="<?php echo esc_attr($cve_ent); ?>"
     data-slug="<?php echo esc_attr($slug); ?>"
     data-mode="<?php echo esc_attr($mode); ?>"
     data-geojson="<?php echo esc_url($geojson_url); ?>"
     data-csv="<?php echo esc_url($csv_url); ?>"
     data-geojson-loc="<?php echo esc_url($geojson_loc_url); ?>"
     data-worker="<?php echo esc_url( SIARHE_URL . 'public/js/siarhe-worker.js?v=' . $siarhe_js_ver('siarhe-worker.js') ); ?>"
     data-marker-config='<?php echo esc_attr(wp_json_encode($marker_config)); ?>'
     data-marker-urls='<?php echo esc_attr(wp_json_encode($marker_urls)); ?>'
     data-marker-labels='<?php echo esc_attr(wp_json_encode($marker_labels)); ?>'
     data-entity-urls='<?php echo esc_attr(wp_json_encode($entity_urls)); ?>' 
     data-metricas='<?php echo esc_attr($metricas_clean_json); ?>' 
     data-tooltips='<?php echo esc_attr($tooltip_json); ?>' 
     data-home-url="<?php echo esc_url($home_url); ?>"> 

    <?php 
    if ( strpos($mode, 'M') !== false && strpos($mode, 'T') !== false ) : 
    ?>
    <h1 class="siarhe-main-title" style="text-align: center; margin-bottom: 15px;">
        ¿Cuántas enfermeras hay en <?php echo $lugar_texto; ?> en <span class="siarhe-dynamic-year"><?php echo esc_html($anio); ?></span>?
    </h1>

    <div class="siarhe-intro-text" style="line-height: 1.6; margin-bottom: 25px;">
        <p>
            En el año <strong style="color: #0F172A;"><?php echo esc_html($anio); ?></strong>, <?php echo $lugar_texto; ?> cuenta con un total de 
            <strong style="color: #06B6D4;"><span class="siarhe-dynamic-nurses-sum">...</span></strong> 
            profesionales de enfermería distribuidos en <?php echo $es_nacional ? 'las 32 entidades federativas' : 'los <strong style="color: #0A66C2;"><span class="siarhe-dynamic-mun-count">...</span></strong> municipios'; ?>, según el último corte estadístico del SIARHE y proyecciones INEGI.
        </p>
        <p>
            Contar con información actualizada y geolocalizada sobre la distribución del capital humano es fundamental para identificar brechas de atención, planificar recursos estratégicos y fortalecer el sistema de salud estatal, asegurando una cobertura equitativa para la población. Esta herramienta permite visualizar las disparidades regionales en la cobertura de salud. Haz doble clic en cualquier <?php echo $es_nacional ? 'estado' : 'municipio'; ?> para consultar el desglose detallado.
        </p>
    </div>
    <?php endif; ?>

    <?php if ( strpos($mode, 'M') !== false ) : ?>
        <section id="siarhe-map-section" class="siarhe-section-map siarhe-block-card">
            
            <header class="siarhe-header" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                <h2 class="siarhe-title" style="margin-bottom: 5px; margin-top: 0;">
                    <span class="dashicons dashicons-location"></span> 
                    <?php echo $lugar_texto; ?>
                </h2>
                <div class="siarhe-dynamic-total" style="color: #444; margin-top:5px;"></div>
            </header>

            <details class="siarhe-nav-guide" style="margin-bottom: 20px; background: #f0f6fc; padding: 15px; border-radius: 6px; border: 1px solid #c3c4c7;">
                <summary style="font-weight: bold; cursor: pointer; color: #0F172A;">
                    <span class="dashicons dashicons-lightbulb" style="color:#0A66C2;"></span> Guía de navegación del mapa
                </summary>
                <ul style="margin-top: 15px; padding-left: 20px; line-height: 1.6; margin-bottom: 0;">
                    <li><strong>Navega:</strong> Haz doble clic en un <?php echo $es_nacional ? 'estado' : 'municipio'; ?>. En celular, toca dos veces seguidas.</li>
                    <li><strong>Compara Regiones:</strong> Usa el selector "Indicador" (arriba del mapa) para visualizar tasas o totales. La tasa mostrada se calcula <strong>por cada mil habitantes</strong>.</li>
                    <li><strong>Localiza Unidades:</strong> Activa "Marcadores" para ver Unidades Especiales o información epidemiológica.</li>
                    <li><strong>Descarga:</strong> Utiliza los botones debajo del mapa para obtener imágenes o descarga la tabla en Excel.</li>
                </ul>
            </details>

            <div class="siarhe-controls-placeholder"></div>

            <div class="siarhe-map-container">
                <div class="siarhe-loading-overlay">
                    <div class="spinner"></div>
                    <p>Cargando mapa interactivo...</p>
                </div>
            </div>

            <div class="siarhe-map-footer">
                💡 Tip: Usa los controles para hacer zoom +/- o ⟳ para resetear. Pasa el cursor para ver detalles.
            </div>

            <div class="siarhe-map-actions">
                <button class="siarhe-btn siarhe-btn-download-png">
                    <span class="dashicons dashicons-camera" style="margin-top: 3px;"></span> Mapa PNG
                </button>
                <button class="siarhe-btn siarhe-btn-toggle-labels">
                    <span class="dashicons dashicons-tag" style="margin-top: 3px;"></span> Mapa con Etiquetas
                </button>
                <button type="button" class="siarhe-btn siarhe-btn-share">
                    <span class="dashicons dashicons-admin-links" style="margin-top: 3px;"></span> Copiar enlace de esta vista
                </button>
            </div>

        </section>
    <?php endif; ?>

    <?php if ( strpos($mode, 'T') !== false ) : ?>
        <?php $table_collapsible = ( strpos($mode, 'M') !== false && strpos($mode, 'T') !== false ); ?>
        <section id="siarhe-table-section" class="siarhe-section-table siarhe-block-card<?php echo $table_collapsible ? ' siarhe-table-collapsible' : ''; ?>">
            <?php if ($table_collapsible) : ?><details class="siarhe-table-details"><summary class="siarhe-table-summary"><?php endif; ?>
            <h2 style="margin-top: 0; margin-bottom: <?php echo $table_collapsible ? '0' : '10px'; ?>;">
                📊 Tasas y totales de profesionales de enfermería por <?php echo $es_nacional ? 'Entidad Federativa' : 'Municipio'; ?> en <?php echo $lugar_texto; ?> en <span class="siarhe-dynamic-year"><?php echo esc_html($anio); ?></span>
            </h2>
            <?php if ($table_collapsible) : ?><span class="siarhe-table-toggle" aria-hidden="true"></span></summary><div class="siarhe-table-body"><?php endif; ?>
            <p style="margin-bottom: 20px; color: #555; line-height: 1.5;">
                La siguiente tabla muestra el concentrado estadístico por <?php echo $es_nacional ? 'estado' : 'municipio'; ?>. Puede ordenar los datos para identificar rápidamente qué <?php echo $es_nacional ? 'entidades' : 'municipios'; ?> tienen la mayor o menor densidad de enfermeras por habitante.
            </p>

            <?php 
            if ( trim($mode) === 'T' ) : 
            ?>
                <div class="siarhe-table-controls-placeholder" style="margin-bottom: 20px;"></div>
            <?php endif; ?>
            
            <div class="siarhe-table-wrapper">
                <div class="siarhe-table-container">
                    <p>Cargando tabla...</p>
                </div>
            </div>

            <div style="text-align: center; margin-top: 15px;">
                <button class="siarhe-btn siarhe-btn-download-excel">
                    <span class="dashicons dashicons-media-spreadsheet" style="margin-top: 3px;"></span> Descargar Excel <?php echo $es_nacional ? 'Nacional' : 'Estatal'; ?>
                </button>
            </div>
            <?php if ($table_collapsible) : ?></div></details><?php endif; ?>
        </section>
    <?php endif; ?>

    <footer class="siarhe-footer">
        <div class="siarhe-footer-grid">
            
            <div class="siarhe-ref-col">
                <strong><span class="dashicons dashicons-admin-site"></span> Datos Espaciales</strong>
                <p class="siarhe-break-text">
                    <strong>Fuente:</strong> <?php echo esc_html($geo_ref); ?><br>
                    <strong>Fecha de corte:</strong> <?php echo esc_html($geo_date); ?>
                </p>
            </div>

            <div class="siarhe-ref-col">
                <strong><span class="dashicons dashicons-location"></span> Datos de Establecimientos</strong>
                <p class="siarhe-break-text">
                    <strong>Fuente:</strong> <?php echo esc_html($mk_ref); ?><br>
                    <strong>Fecha de corte:</strong> <?php echo esc_html($mk_date); ?>
                </p>
            </div>

            <div class="siarhe-ref-col">
                <strong><span class="dashicons dashicons-groups"></span> Datos de Enfermería</strong>
                <p class="siarhe-break-text">
                    <strong>Fuente:</strong> <?php echo esc_html($csv_ref); ?><br>
                    <strong>Fecha de corte:</strong> <?php echo esc_html($csv_date); ?>
                </p>
            </div>

        </div>
        
        <p class="siarhe-disclaimer siarhe-break-text">
            * El total <?php echo $es_nacional ? 'nacional' : 'estatal'; ?> se calcula sumando <?php echo $es_nacional ? 'las 32 entidades federativas más los registros clasificados como "No Disponible" o "Extranjero"' : 'los <span class="siarhe-dynamic-mun-count">...</span> municipios más los registros clasificados como "No Disponible"'; ?>.
        </p>

        <p class="siarhe-legal-disclaimer siarhe-break-text">
            Toda la información fue obtenida de fuentes oficiales a través de sus portales de datos abiertos; sin embargo, este análisis no representa una postura oficial de dichas instituciones. Recomendamos revisar nuestros <a href="<?php echo esc_url($url_terminos); ?>" target="_blank">Términos y Condiciones</a> y el <a href="<?php echo esc_url($url_aviso); ?>" target="_blank">Aviso Legal</a> para más detalles.
        </p>
    </footer>

</div>