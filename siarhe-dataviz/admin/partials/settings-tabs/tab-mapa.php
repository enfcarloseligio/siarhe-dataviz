<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener opciones guardadas
$map_options = get_option( 'siarhe_map_options', [] );

// 2. Valores por defecto (Actualizados con los 4 niveles y monocromático)
$defaults = [
    // Mapa (Secuencial 5 pasos)
    'map_c1' => '#eff3ff', // Mínimo
    'map_c2' => '#bdd7e7', // Q1 (25%)
    'map_c3' => '#6baed6', // Q2 (50%)
    'map_c4' => '#3182bd', // Q3 (75%)
    'map_c5' => '#08519c', // Máximo
    
    // 🌟 NUEVO: Mapa (Secuencial Monocromático)
    'mono_min' => '#f0f9ff', // Color más claro
    'mono_max' => '#0369a1', // Color más oscuro
    
    // Casos Especiales
    'map_zero' => '#d9d9d9', // Valor es 0
    'map_null' => '#000000', // Sin datos en CSV

    // Tabla Frontend
    'th_bg'        => '#f4f4f4',
    'th_text'      => '#333333',
    'tr_odd'       => '#ffffff',
    'tr_odd_txt'   => '#555555',
    'tr_even'      => '#f9f9f9',
    'tr_even_txt'  => '#555555',
    'tr_total_bg'  => '#e8f4fd',
    'tr_total_txt' => '#000000',
    'border_color' => '#dddddd',
    'border_width' => '1',
];

$opts = wp_parse_args( $map_options, $defaults );

// 🌟 3. OBTENER MARCADORES DINÁMICOS DESDE LA BD
$marcadores_json = get_option( 'siarhe_marcadores_config', '' );
$marcadores = json_decode( wp_unslash( $marcadores_json ), true );

// Fallback por si la BD está vacía al cargar esta pestaña
if (empty($marcadores)) {
    $marcadores = [
        'CATETER' => ['label' => 'Clínicas de catéteres'], 'HERIDAS' => ['label' => 'Clínicas de heridas'],
        'ESTAB_1' => ['label' => 'Establecimientos (1er Nivel)'], 'ESTAB_2' => ['label' => 'Establecimientos (2do Nivel)'],
        'ESTAB_3' => ['label' => 'Establecimientos (3er Nivel)'], 'ESTAB_6' => ['label' => 'Establecimientos (No Aplica)']
    ];
}

// Colores heredados para que los nativos no pierdan su diseño original si no se han guardado
$legacy_colors = [ 'cateter' => '#1E5B4F', 'heridas' => '#9B2247', 'estab_1' => '#4daf4a', 'estab_2' => '#377eb8', 'estab_3' => '#e41a1c', 'estab_6' => '#984ea3' ];
$legacy_shapes = [ 'cateter' => 'circle', 'heridas' => 'square', 'estab_1' => 'circle', 'estab_2' => 'square', 'estab_3' => 'star', 'estab_6' => 'cross' ];
?>

<style>
    .siarhe-admin-card { padding: 20px; max-width: 100%; box-sizing: border-box; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
    .siarhe-flex-row { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end; }
    
    /* Fila especial para marcadores */
    .siarhe-marker-row { display: flex; gap: 20px; flex-wrap: wrap; align-items: center; border-bottom: 1px dashed #eee; padding-bottom: 15px; margin-bottom: 15px; }
    .siarhe-marker-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

    .siarhe-grad-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
    .siarhe-color-box { min-width: 60px; text-align: center; }
    
    /* Cajita de la Vista Previa */
    .siarhe-preview-box {
        background: #f0f6fc; border: 1px dashed #c3c4c7; border-radius: 6px; padding: 10px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        min-width: 80px; height: 75px; box-sizing: border-box;
    }
    
    @media (max-width: 782px) {
        .siarhe-admin-card .form-table th { display: block; width: 100%; padding-bottom: 5px; }
        .siarhe-admin-card .form-table td { display: block; width: 100%; padding-bottom: 20px; padding-left: 0; }
        .siarhe-flex-row, .siarhe-marker-row { flex-direction: column; align-items: flex-start; gap: 10px; width: 100%; }
        
        .siarhe-preview-box { 
            width: 100%; flex-direction: row; justify-content: flex-start;
            gap: 15px; height: auto; padding: 10px 15px;
        }
        .siarhe-preview-box label { margin-bottom: 0 !important; }
    }
</style>

<div class="siarhe-admin-card">
    <h2>🎨 Personalización Visual (Frontend)</h2>
    <p>Define los colores que verán los usuarios en los mapas y tablas del sitio web.</p>

    <h3 class="siarhe-section-title" style="margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Mapa de Calor (Escala de Colores)</h3>
    
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Gradiente Cuartiles (Valores > 0)</th>
            <td>
                <div class="siarhe-grad-row">
                    <div class="siarhe-color-box"><input type="text" name="siarhe_map_options[map_c1]" value="<?php echo esc_attr($opts['map_c1']); ?>" class="siarhe-color-field" data-alpha="true"><p class="description"><small>Mínimo</small></p></div>
                    <span class="dashicons dashicons-arrow-right-alt" style="color:#aaa;"></span>
                    <div class="siarhe-color-box"><input type="text" name="siarhe_map_options[map_c2]" value="<?php echo esc_attr($opts['map_c2']); ?>" class="siarhe-color-field"><p class="description"><small>Q1</small></p></div>
                    <span class="dashicons dashicons-arrow-right-alt" style="color:#aaa;"></span>
                    <div class="siarhe-color-box"><input type="text" name="siarhe_map_options[map_c3]" value="<?php echo esc_attr($opts['map_c3']); ?>" class="siarhe-color-field"><p class="description"><small>Mediana</small></p></div>
                    <span class="dashicons dashicons-arrow-right-alt" style="color:#aaa;"></span>
                    <div class="siarhe-color-box"><input type="text" name="siarhe_map_options[map_c4]" value="<?php echo esc_attr($opts['map_c4']); ?>" class="siarhe-color-field"><p class="description"><small>Q3</small></p></div>
                    <span class="dashicons dashicons-arrow-right-alt" style="color:#aaa;"></span>
                    <div class="siarhe-color-box"><input type="text" name="siarhe_map_options[map_c5]" value="<?php echo esc_attr($opts['map_c5']); ?>" class="siarhe-color-field"><p class="description"><small>Máximo</small></p></div>
                </div>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Gradiente Monocromático</th>
            <td>
                <p class="description" style="margin-top:0; margin-bottom:10px;">Esta escala se usa cuando el usuario activa la opción de ver el mapa sin cortes de cuartiles (escala lineal suave).</p>
                <div class="siarhe-grad-row">
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[mono_min]" value="<?php echo esc_attr($opts['mono_min']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Color Claro (Mínimo)</small></p>
                    </div>
                    <span class="dashicons dashicons-arrow-right-alt" style="color:#aaa;"></span>
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[mono_max]" value="<?php echo esc_attr($opts['mono_max']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Color Oscuro (Máximo)</small></p>
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">Casos Especiales</th>
            <td>
                <div class="siarhe-flex-row">
                    <div><label><strong>Valor 0</strong></label><br><input type="text" name="siarhe_map_options[map_zero]" value="<?php echo esc_attr($opts['map_zero']); ?>" class="siarhe-color-field"></div>
                    <div><label><strong>Sin Datos</strong></label><br><input type="text" name="siarhe_map_options[map_null]" value="<?php echo esc_attr($opts['map_null']); ?>" class="siarhe-color-field"></div>
                </div>
            </td>
        </tr>
    </table>

    <h3 class="siarhe-section-title" style="margin-top: 40px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Estilos de Tabla de Datos</h3>
    
    <table class="form-table">
        <tr>
            <th scope="row">Encabezados (Header)</th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[th_bg]" value="<?php echo esc_attr($opts['th_bg']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[th_text]" value="<?php echo esc_attr($opts['th_text']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
        <tr>
            <th scope="row">Fila de Totales (ID 9999)</th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[tr_total_bg]" value="<?php echo esc_attr($opts['tr_total_bg']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[tr_total_txt]" value="<?php echo esc_attr($opts['tr_total_txt']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
        <tr>
            <th scope="row">Cuerpo Tabla (Impar)</th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[tr_odd]" value="<?php echo esc_attr($opts['tr_odd']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[tr_odd_txt]" value="<?php echo esc_attr($opts['tr_odd_txt']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
        <tr>
            <th scope="row">Cuerpo Tabla (Par)</th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[tr_even]" value="<?php echo esc_attr($opts['tr_even']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[tr_even_txt]" value="<?php echo esc_attr($opts['tr_even_txt']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
        <tr>
            <th scope="row">Bordes</th>
            <td><div class="siarhe-flex-row"><div><label>Color:</label><br><input type="text" name="siarhe_map_options[border_color]" value="<?php echo esc_attr($opts['border_color']); ?>" class="siarhe-color-field"></div><div><label>Grosor (px):</label><br><input type="number" name="siarhe_map_options[border_width]" value="<?php echo esc_attr($opts['border_width']); ?>" class="small-text" min="0" max="5"></div></div></td>
        </tr>
    </table>

    <h3 class="siarhe-section-title" style="margin-top: 40px; border-bottom: 1px solid #eee; padding-bottom: 10px;">📍 Estilos Dinámicos de Marcadores</h3>
    <p class="description">Aquí aparecerán automáticamente todos los marcadores que registres en la pestaña "Marcadores (CSV)".</p>
    
    <table class="form-table">
        <?php foreach ($marcadores as $key => $mk): 
            $s_key = strtolower($key);
            // Si el marcador no tiene color guardado, intentamos usar el default legacy o un azul genérico
            $shape_val  = isset($opts["m_{$s_key}_shape"])  ? $opts["m_{$s_key}_shape"]  : (isset($legacy_shapes[$s_key]) ? $legacy_shapes[$s_key] : 'circle');
            $fill_val   = isset($opts["m_{$s_key}_fill"])   ? $opts["m_{$s_key}_fill"]   : (isset($legacy_colors[$s_key]) ? $legacy_colors[$s_key] : '#0A66C2');
            $stroke_val = isset($opts["m_{$s_key}_stroke"]) ? $opts["m_{$s_key}_stroke"] : '#ffffff';
        ?>
        <tr>
            <th scope="row">
                <strong><?php echo esc_html($mk['label']); ?></strong><br>
                <small style="color:#8c8f94; font-family:monospace;"><?php echo esc_html($key); ?></small>
            </th>
            <td>
                <div class="siarhe-marker-row" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
                    <div class="siarhe-preview-box">
                        <label style="font-size:10px; color:#888; margin-bottom:5px;">Vista Previa</label>
                        <svg id="preview-<?php echo esc_attr($s_key); ?>" width="30" height="30" viewBox="0 0 30 30"></svg>
                    </div>
                    <div>
                        <label>Forma:</label><br>
                        <select name="siarhe_map_options[m_<?php echo esc_attr($s_key); ?>_shape]" id="m_<?php echo esc_attr($s_key); ?>_shape">
                            <option value="circle" <?php selected($shape_val, 'circle'); ?>>Círculo</option>
                            <option value="square" <?php selected($shape_val, 'square'); ?>>Cuadrado</option>
                            <option value="triangle" <?php selected($shape_val, 'triangle'); ?>>Triángulo</option>
                            <option value="diamond" <?php selected($shape_val, 'diamond'); ?>>Rombo</option>
                            <option value="star" <?php selected($shape_val, 'star'); ?>>Estrella</option>
                            <option value="cross" <?php selected($shape_val, 'cross'); ?>>Cruz</option>
                        </select>
                    </div>
                    <div><label>Relleno:</label><br><input type="text" name="siarhe_map_options[m_<?php echo esc_attr($s_key); ?>_fill]" id="m_<?php echo esc_attr($s_key); ?>_fill" value="<?php echo esc_attr($fill_val); ?>" class="siarhe-color-field"></div>
                    <div><label>Borde:</label><br><input type="text" name="siarhe_map_options[m_<?php echo esc_attr($s_key); ?>_stroke]" id="m_<?php echo esc_attr($s_key); ?>_stroke" value="<?php echo esc_attr($stroke_val); ?>" class="siarhe-color-field"></div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Definición de formas en SVG
    const shapes = {
        circle: '<circle cx="15" cy="15" r="10" />',
        square: '<rect x="5" y="5" width="20" height="20" />',
        triangle: '<polygon points="15,4 26,24 4,24" />',
        diamond: '<polygon points="15,2 26,15 15,28 4,15" />',
        star: '<polygon points="15,2 19,10 28,11 21,17 23,26 15,22 7,26 9,17 2,11 11,10" />',
        cross: '<path d="M11,5 H19 V11 H25 V19 H19 V25 H11 V19 H5 V11 H11 Z" />'
    };

    function updatePreview(type) {
        const shapeSelect = document.getElementById(`m_${type}_shape`);
        const fillInput = document.getElementById(`m_${type}_fill`);
        const strokeInput = document.getElementById(`m_${type}_stroke`);
        const svgBox = document.getElementById(`preview-${type}`);

        if (shapeSelect && fillInput && strokeInput && svgBox) {
            const shape = shapeSelect.value;
            const fill = fillInput.value || '#000000';
            const stroke = strokeInput.value || '#ffffff';

            svgBox.innerHTML = shapes[shape] || shapes.circle;
            const el = svgBox.firstElementChild;
            el.setAttribute('fill', fill);
            el.setAttribute('stroke', stroke);
            el.setAttribute('stroke-width', '2');
            el.setAttribute('stroke-linejoin', 'round');
        }
    }

    // 🌟 Generamos la lista de IDs dinámicamente desde PHP para Javascript
    const types = <?php echo wp_json_encode(array_map('strtolower', array_keys($marcadores))); ?>;

    types.forEach(t => updatePreview(t));

    types.forEach(t => {
        const el = document.getElementById(`m_${t}_shape`);
        if(el) el.addEventListener('change', () => updatePreview(t));
    });

    setInterval(() => {
        types.forEach(t => updatePreview(t));
    }, 400); 
});
</script>