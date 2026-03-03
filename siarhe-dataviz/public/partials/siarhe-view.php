<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Año de los metadatos de la Base Estática (CSV)
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$csv_meta = $wpdb->get_row( $wpdb->prepare( "SELECT anio_reporte FROM $table_assets WHERE entidad_slug = %s AND tipo_archivo = 'static_min' AND es_activo = 1", $slug ) );

if ( $csv_meta && !empty($csv_meta->anio_reporte) ) {
    $anio = $csv_meta->anio_reporte; 
}

// 🌟 NUEVO: Catálogo Oficial INEGI (Basado en tu tabla exacta)
$catalogo_oficial = [
    '01' => 'Aguascalientes', '02' => 'Baja California', '03' => 'Baja California Sur', '04' => 'Campeche',
    '05' => 'Coahuila de Zaragoza', '06' => 'Colima', '07' => 'Chiapas', '08' => 'Chihuahua', '09' => 'Ciudad de México',
    '10' => 'Durango', '11' => 'Guanajuato', '12' => 'Guerrero', '13' => 'Hidalgo', '14' => 'Jalisco',
    '15' => 'Estado de México', '16' => 'Michoacán de Ocampo', '17' => 'Morelos', '18' => 'Nayarit', '19' => 'Nuevo León',
    '20' => 'Oaxaca', '21' => 'Puebla', '22' => 'Querétaro', '23' => 'Quintana Roo', '24' => 'San Luis Potosí',
    '25' => 'Sinaloa', '26' => 'Sonora', '27' => 'Tabasco', '28' => 'Tamaulipas', '29' => 'Tlaxcala',
    '30' => 'Veracruz de Ignacio de la Llave', '31' => 'Yucatán', '32' => 'Zacatecas', '33' => 'México', '34' => 'Extranjero'
];

// Lógica adaptativa: Detecta si es el mapa nacional o estatal para ajustar los textos
$es_nacional = ($slug === 'republica-mexicana' || $cve_ent === '33');

// 🌟 Nombre Oficial Inyectado
$lugar_texto = isset($catalogo_oficial[$cve_ent]) ? $catalogo_oficial[$cve_ent] : esc_html($nombre_entidad);

// Obtener opciones guardadas de colores y marcadores
$map_options = get_option( 'siarhe_map_options', [] );
$defaults = [
    'm_cateter_shape'  => 'circle', 'm_cateter_fill'   => '#1E5B4F', 'm_cateter_stroke' => '#ffffff',
    'm_heridas_shape'  => 'square', 'm_heridas_fill'   => '#9B2247', 'm_heridas_stroke' => '#ffffff',
    'm_estab1_shape'   => 'circle', 'm_estab1_fill'    => '#4daf4a', 'm_estab1_stroke'  => '#ffffff', 
    'm_estab2_shape'   => 'square', 'm_estab2_fill'    => '#377eb8', 'm_estab2_stroke'  => '#ffffff', 
    'm_estab3_shape'   => 'star',   'm_estab3_fill'    => '#e41a1c', 'm_estab3_stroke'  => '#ffffff', 
    'm_estab6_shape'   => 'cross',  'm_estab6_fill'    => '#984ea3', 'm_estab6_stroke'  => '#ffffff', 
];
$opts = wp_parse_args( $map_options, $defaults );

// EMPAQUETADO PARA EL FRONTEND (6 MARCADORES)
$marker_config = [
    'CATETER' => ['shape' => $opts['m_cateter_shape'], 'fill' => $opts['m_cateter_fill'], 'stroke' => $opts['m_cateter_stroke']],
    'HERIDAS' => ['shape' => $opts['m_heridas_shape'], 'fill' => $opts['m_heridas_fill'], 'stroke' => $opts['m_heridas_stroke']],
    'ESTAB_1' => ['shape' => $opts['m_estab1_shape'],  'fill' => $opts['m_estab1_fill'],  'stroke' => $opts['m_estab1_stroke']],
    'ESTAB_2' => ['shape' => $opts['m_estab2_shape'],  'fill' => $opts['m_estab2_fill'],  'stroke' => $opts['m_estab2_stroke']],
    'ESTAB_3' => ['shape' => $opts['m_estab3_shape'],  'fill' => $opts['m_estab3_fill'],  'stroke' => $opts['m_estab3_stroke']],
    'ESTAB_6' => ['shape' => $opts['m_estab6_shape'],  'fill' => $opts['m_estab6_fill'],  'stroke' => $opts['m_estab6_stroke']],
];

// MAPEO DE URLs CON ROMPE-CACHÉ
$upload_url = defined('SIARHE_UPLOAD_URL') ? SIARHE_UPLOAD_URL : wp_upload_dir()['baseurl'] . '/siarhe-data/';
$v = time();

$marker_urls = [
    'CATETER' => $upload_url . 'markers/clinicas-cateteres.csv?v=' . $v,
    'HERIDAS' => $upload_url . 'markers/clinicas-heridas.csv?v=' . $v,
    'ESTAB_1' => $upload_url . 'markers/establecimientos-salud.csv?v=' . $v,
    'ESTAB_2' => $upload_url . 'markers/establecimientos-salud.csv?v=' . $v,
    'ESTAB_3' => $upload_url . 'markers/establecimientos-salud.csv?v=' . $v,
    'ESTAB_6' => $upload_url . 'markers/establecimientos-salud.csv?v=' . $v,
];

// PROCESAMIENTO DE ENLACES DE NAVEGACIÓN
$siarhe_links_raw = get_option( 'siarhe_links_map', [] );
$entity_urls = [];
$home_url = '';

// Mapeo específico de Claves INEGI a Slugs de configuración para no romper los enlaces guardados
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
?>

<style>
    .siarhe-viz-wrapper {
        width: 100%; max-width: 100%; box-sizing: border-box; position: relative; z-index: 1;
    }
    .siarhe-viz-wrapper * { box-sizing: inherit; }
    .siarhe-break-text { overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; hyphens: auto; }
</style>

<div class="siarhe-viz-wrapper" 
     id="siarhe-viz-<?php echo esc_attr($cve_ent); ?>"
     data-cve-ent="<?php echo esc_attr($cve_ent); ?>"
     data-slug="<?php echo esc_attr($slug); ?>"
     data-mode="<?php echo esc_attr($mode); ?>"
     data-geojson="<?php echo esc_url($geojson_url); ?>"
     data-csv="<?php echo esc_url($csv_url); ?>"
     data-marker-config='<?php echo esc_attr(wp_json_encode($marker_config)); ?>'
     data-marker-urls='<?php echo esc_attr(wp_json_encode($marker_urls)); ?>'
     data-entity-urls='<?php echo esc_attr(wp_json_encode($entity_urls)); ?>' 
     data-home-url="<?php echo esc_url($home_url); ?>"> 

    <h1 class="siarhe-main-title" style="text-align: center; margin-bottom: 15px;">
        ¿Cuántas enfermeras hay en <?php echo $lugar_texto; ?> en <span class="siarhe-dynamic-year"><?php echo esc_html($anio); ?></span>?
    </h1>

    <div class="siarhe-intro-text" style="line-height: 1.6; margin-bottom: 25px;">
        <p>
            En el año <strong><?php echo esc_html($anio); ?></strong>, <?php echo $lugar_texto; ?> cuenta con un total de 
            <strong style="color: #06B6D4;"><span class="siarhe-dynamic-nurses-sum">...</span></strong> 
            profesionales de enfermería distribuidos en <?php echo $es_nacional ? 'las 32 entidades federativas' : 'los <strong style="color: #0A66C2;"><span class="siarhe-dynamic-mun-count">...</span></strong> municipios'; ?>, según el último corte estadístico del SIARHE y proyecciones INEGI.
        </p>
        <p>
            Contar con información actualizada y geolocalizada sobre la distribución del capital humano es fundamental para identificar brechas de atención, planificar recursos estratégicos y fortalecer el sistema de salud estatal, asegurando una cobertura equitativa para la población. Esta herramienta permite visualizar las disparidades regionales en la cobertura de salud. Haz doble clic en cualquier <?php echo $es_nacional ? 'estado' : 'municipio'; ?> para consultar el desglose detallado.
        </p>
    </div>

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
                <button class="button button-secondary btn-siarhe btn-download-map siarhe-btn-download-png">
                    <span class="dashicons dashicons-camera" style="margin-top: 3px;"></span> 🗺️ Mapa PNG
                </button>
                <button class="button button-secondary btn-siarhe btn-download-map siarhe-btn-toggle-labels">
                    <span class="dashicons dashicons-tag" style="margin-top: 3px;"></span> 📝 Mapa con Etiquetas
                </button>
            </div>

        </section>
    <?php endif; ?>

    <?php if ( strpos($mode, 'T') !== false ) : ?>
        <section id="siarhe-table-section" class="siarhe-section-table siarhe-block-card">
            <h2 style="margin-top: 0; margin-bottom: 10px;">📊 Tasas y totales de profesionales de enfermería por <?php echo $es_nacional ? 'Entidad Federativa' : 'Municipio'; ?></h2>
            <p style="margin-bottom: 20px; color: #555; line-height: 1.5;">
                La siguiente tabla muestra el concentrado estadístico por <?php echo $es_nacional ? 'estado' : 'municipio'; ?>. Puede ordenar los datos para identificar rápidamente qué <?php echo $es_nacional ? 'entidades' : 'municipios'; ?> tienen la mayor o menor densidad de enfermeras por habitante.
            </p>
            
            <div class="siarhe-table-wrapper">
                <div class="siarhe-table-container">
                    <p>Cargando tabla...</p>
                </div>
            </div>

            <div style="text-align: center; margin-top: 15px;">
                <button class="button button-primary btn-siarhe btn-primary siarhe-btn-download-excel">
                    <span class="dashicons dashicons-media-spreadsheet" style="margin-top: 3px;"></span> 📥 Descargar Excel <?php echo $es_nacional ? 'Nacional' : 'Estatal'; ?>
                </button>
            </div>
        </section>
    <?php endif; ?>

    <footer class="siarhe-footer">
        <div class="siarhe-footer-grid">
            
            <div class="siarhe-ref-col">
                <strong><span class="dashicons dashicons-groups"></span> Datos de Enfermería</strong>
                <p class="siarhe-break-text">
                    <strong>Fuente:</strong> <?php echo esc_html($csv_ref); ?><br>
                    <strong>Fecha de corte:</strong> <?php echo esc_html($csv_date); ?>
                </p>
            </div>

            <div class="siarhe-ref-col">
                <strong><span class="dashicons dashicons-admin-site"></span> Datos Espaciales</strong>
                <p class="siarhe-break-text">
                    <strong>Fuente:</strong> <?php echo esc_html($geo_ref); ?><br>
                    <strong>Fecha de corte:</strong> <?php echo esc_html($geo_date); ?>
                </p>
            </div>

        </div>
        
        <p class="siarhe-disclaimer siarhe-break-text">
            * El total <?php echo $es_nacional ? 'nacional' : 'estatal'; ?> se calcula sumando <?php echo $es_nacional ? 'las 32 entidades federativas más los registros clasificados como "No Disponible" o "Extranjero"' : 'los <span class="siarhe-dynamic-mun-count">...</span> municipios más los registros clasificados como "No Disponible"'; ?>.
        </p>

        <p class="siarhe-legal-disclaimer siarhe-break-text">
            Toda la información fue obtenida de fuentes oficiales a través de sus portales de datos abiertos; sin embargo, este análisis no representa una postura oficial de dichas instituciones. Recomendamos revisar nuestros <a href="https://enfcarloseligio.com/terminos-y-condiciones/" target="_blank">Términos y Condiciones</a> y el <a href="https://enfcarloseligio.com/descargo-de-responsabilidades/" target="_blank">Aviso Legal</a> para más detalles.
        </p>
    </footer>

</div>