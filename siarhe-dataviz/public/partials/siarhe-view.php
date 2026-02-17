<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="siarhe-viz-wrapper" 
     id="siarhe-viz-<?php echo esc_attr($cve_ent); ?>"
     data-cve-ent="<?php echo esc_attr($cve_ent); ?>"
     data-slug="<?php echo esc_attr($slug); ?>"
     data-mode="<?php echo esc_attr($mode); ?>"
     data-geojson="<?php echo esc_url($geojson_url); ?>"
     data-csv="<?php echo esc_url($csv_url); ?>">

    <header class="siarhe-header">
        <h2 class="siarhe-title">
            <span class="dashicons dashicons-location"></span> 
            <?php echo esc_html($nombre_entidad); ?>
        </h2>
        <div class="siarhe-dynamic-total" style="font-size: 1.2em; color: #444; margin-top:5px;">
            </div>
    </header>

    <?php if ( strpos($mode, 'M') !== false ) : ?>
        <section class="siarhe-section-map">
            
            <div class="siarhe-controls-placeholder"></div>

            <div class="siarhe-map-container">
                <div class="siarhe-loading-overlay">
                    <div class="spinner"></div>
                    <p>Cargando mapa interactivo...</p>
                </div>
            </div>

            <div class="siarhe-map-footer">
                <small>💡 Tip: Doble clic para hacer zoom. Pasa el cursor para ver detalles.</small>
            </div>
        </section>
    <?php endif; ?>

    <?php if ( strpos($mode, 'T') !== false ) : ?>
        <section class="siarhe-section-table">
            <h3>📊 Datos Detallados</h3>
            <div class="siarhe-table-container">
                <p>Cargando tabla...</p>
            </div>
        </section>
    <?php endif; ?>

    <footer class="siarhe-footer" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; font-size: 0.9em; color: #666;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            
            <div class="siarhe-ref-col">
                <strong style="color: #2271b1;"><span class="dashicons dashicons-groups"></span> Datos de Enfermería</strong>
                <p style="margin: 5px 0;">
                    <strong>Fuente:</strong> <?php echo esc_html($csv_ref); ?><br>
                    <strong>Fecha de corte:</strong> <?php echo esc_html($csv_date); ?>
                </p>
            </div>

            <div class="siarhe-ref-col">
                <strong style="color: #2271b1;"><span class="dashicons dashicons-admin-site"></span> Datos Espaciales</strong>
                <p style="margin: 5px 0;">
                    <strong>Fuente:</strong> <?php echo esc_html($geo_ref); ?><br>
                    <strong>Fecha de corte:</strong> <?php echo esc_html($geo_date); ?>
                </p>
            </div>

        </div>
        
        <p class="siarhe-disclaimer" style="margin-top: 15px; font-style: italic; font-size: 0.85em;">
            * El total nacional se calcula sumando las 32 entidades federativas más los registros clasificados como "No Disponible" o "No Asignado".
        </p>
    </footer>

</div>