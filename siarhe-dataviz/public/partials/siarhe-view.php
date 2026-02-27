<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Año de los metadatos de la Base Estática (CSV)
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$csv_meta = $wpdb->get_row( $wpdb->prepare( "SELECT anio_reporte FROM $table_assets WHERE entidad_slug = %s AND tipo_archivo = 'static_min' AND es_activo = 1", $slug ) );

if ( $csv_meta && !empty($csv_meta->anio_reporte) ) {
    $anio = $csv_meta->anio_reporte; 
}

// Lógica adaptativa: Detecta si es el mapa nacional o estatal para ajustar los textos
$es_nacional = ($slug === 'republica-mexicana' || $cve_ent === '33');
$lugar_texto = $es_nacional ? 'México' : esc_html($nombre_entidad);
$distribucion_texto = $es_nacional ? 'las entidades federativas' : 'sus municipios';
$doble_clic_texto = $es_nacional ? 'estado para ir a su mapa municipal' : 'municipio para ver sus detalles';

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

// 🌟 EMPAQUETADO PARA EL FRONTEND (6 MARCADORES)
$marker_config = [
    'CATETER' => ['shape' => $opts['m_cateter_shape'], 'fill' => $opts['m_cateter_fill'], 'stroke' => $opts['m_cateter_stroke']],
    'HERIDAS' => ['shape' => $opts['m_heridas_shape'], 'fill' => $opts['m_heridas_fill'], 'stroke' => $opts['m_heridas_stroke']],
    'ESTAB_1' => ['shape' => $opts['m_estab1_shape'],  'fill' => $opts['m_estab1_fill'],  'stroke' => $opts['m_estab1_stroke']],
    'ESTAB_2' => ['shape' => $opts['m_estab2_shape'],  'fill' => $opts['m_estab2_fill'],  'stroke' => $opts['m_estab2_stroke']],
    'ESTAB_3' => ['shape' => $opts['m_estab3_shape'],  'fill' => $opts['m_estab3_fill'],  'stroke' => $opts['m_estab3_stroke']],
    'ESTAB_6' => ['shape' => $opts['m_estab6_shape'],  'fill' => $opts['m_estab6_fill'],  'stroke' => $opts['m_estab6_stroke']],
];

// 🌟 MAPEO DE URLs CON ROMPE-CACHÉ
$upload_url = defined('SIARHE_UPLOAD_URL') ? SIARHE_UPLOAD_URL : wp_upload_dir()['baseurl'] . '/siarhe-data/';
$v = time(); // Genera un sello de tiempo único

$marker_urls = [
    'CATETER' => $upload_url . 'markers/clinicas-cateteres.csv?v=' . $v,
    'HERIDAS' => $upload_url . 'markers/clinicas-heridas.csv?v=' . $v,
    'ESTAB_1' => $upload_url . 'markers/establecimientos-salud.csv?v=' . $v,
    'ESTAB_2' => $upload_url . 'markers/establecimientos-salud.csv?v=' . $v,
    'ESTAB_3' => $upload_url . 'markers/establecimientos-salud.csv?v=' . $v,
    'ESTAB_6' => $upload_url . 'markers/establecimientos-salud.csv?v=' . $v,
];
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
     data-marker-urls='<?php echo esc_attr(wp_json_encode($marker_urls)); ?>'>

    <h1 class="siarhe-main-title" style="text-align: center; margin-bottom: 15px;">
        ¿Cuántas enfermeras hay en <?php echo $lugar_texto; ?> en <span class="siarhe-dynamic-year"><?php echo esc_html($anio); ?></span>?
    </h1>

    <div class="siarhe-intro-text" style="line-height: 1.6; margin-bottom: 25px;">
        <p>
            En el año <strong><?php echo esc_html($anio); ?></strong>, <?php echo $lugar_texto; ?> cuenta con un total de 
            <strong style="color: #06B6D4;"><span class="siarhe-dynamic-nurses-sum">...</span></strong> 
            profesionales de enfermería distribuidos en <?php echo $distribucion_texto; ?>, según el último corte estadístico del SIARHE y proyecciones INEGI.
        </p>
        <p>
            Contar con información actualizada y geolocalizada sobre la distribución del capital humano es fundamental para identificar brechas de atención, planificar recursos estratégicos y fortalecer el sistema de salud estatal, asegurando una cobertura equitativa para la población. Esta herramienta permite visualizar las disparidades regionales en la cobertura de salud. Haz doble clic en cualquier <?php echo ($es_nacional ? 'estado' : 'municipio'); ?> para consultar el desglose detallado.
        </p>
    </div>

    <?php if ( strpos($mode, 'M') !== false ) : ?>
        <section class="siarhe-section-map siarhe-block-card">
            
            <header class="siarhe-header" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                <h2 class="siarhe-title" style="margin-bottom: 5px; margin-top: 0;">
                    <span class="dashicons dashicons-location"></span> 
                    <?php echo esc_html($nombre_entidad); ?>
                </h2>
                <div class="siarhe-dynamic-total" style="color: #444; margin-top:5px;"></div>
            </header>

            <details class="siarhe-nav-guide" style="margin-bottom: 20px; background: #f0f6fc; padding: 15px; border-radius: 6px; border: 1px solid #c3c4c7;">
                <summary style="font-weight: bold; cursor: pointer; color: #0F172A;">
                    <span class="dashicons dashicons-lightbulb" style="color:#0A66C2;"></span> Guía de navegación del mapa
                </summary>
                <ul style="margin-top: 15px; padding-left: 20px; line-height: 1.6; margin-bottom: 0;">
                    <li><strong>Navega:</strong> Haz doble clic en un <?php echo $doble_clic_texto; ?>. En celular, toca dos veces seguidas.</li>
                    <li><strong>Compara Regiones:</strong> Usa el selector "Indicador" (arriba del mapa) para visualizar tasas o totales.</li>
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
        <section class="siarhe-section-table siarhe-block-card">
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
            * El total nacional se calcula sumando las 32 entidades federativas más los registros clasificados como "No Disponible" o "Extranjero".
        </p>

        <p class="siarhe-legal-disclaimer siarhe-break-text">
            Toda la información fue obtenida de fuentes oficiales a través de sus portales de datos abiertos; sin embargo, este análisis no representa una postura oficial de dichas instituciones. Recomendamos revisar nuestros <a href="https://enfcarloseligio.com/terminos-y-condiciones/" target="_blank">Términos y Condiciones</a> y el <a href="https://enfcarloseligio.com/descargo-de-responsabilidades/" target="_blank">Aviso Legal</a> para más detalles.
        </p>
    </footer>

</div>