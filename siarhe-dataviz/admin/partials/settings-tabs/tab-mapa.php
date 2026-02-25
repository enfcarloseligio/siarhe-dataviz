<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener opciones guardadas
$map_options = get_option( 'siarhe_map_options', [] );

// 2. Valores por defecto
$defaults = [
    // Mapa (Secuencial 5 pasos)
    'map_c1' => '#eff3ff', // Mínimo
    'map_c2' => '#bdd7e7', // Q1 (25%)
    'map_c3' => '#6baed6', // Q2 (50%)
    'map_c4' => '#3182bd', // Q3 (75%)
    'map_c5' => '#08519c', // Máximo
    
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

    // 🌟 NUEVO: Marcadores (Clínicas)
    'm_cateter_shape'  => 'circle',
    'm_cateter_fill'   => '#1E5B4F',
    'm_cateter_stroke' => '#ffffff',
    
    'm_heridas_shape'  => 'square',
    'm_heridas_fill'   => '#9B2247',
    'm_heridas_stroke' => '#ffffff',
    
    'm_estab_shape'    => 'cross',
    'm_estab_fill'     => '#2271b1',
    'm_estab_stroke'   => '#ffffff',
];

$opts = wp_parse_args( $map_options, $defaults );
?>

<style>
    .siarhe-admin-card {
        padding: 20px; 
        max-width: 100%; 
        box-sizing: border-box; 
        background: #fff; 
        border: 1px solid #ccd0d4; 
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .siarhe-flex-row {
        display: flex;
        gap: 20px;
        flex-wrap: wrap; /* Clave para que bajen en móviles */
        align-items: flex-end;
    }
    .siarhe-grad-row {
        display: flex;
        gap: 15px;
        flex-wrap: wrap; /* Clave para que los colores no se salgan */
        align-items: center;
    }
    .siarhe-color-box {
        min-width: 60px;
        text-align: center;
    }
    
    /* Adaptación de tablas nativas de WP para móviles */
    @media (max-width: 782px) {
        .siarhe-admin-card .form-table th {
            display: block;
            width: 100%;
            padding-bottom: 5px;
        }
        .siarhe-admin-card .form-table td {
            display: block;
            width: 100%;
            padding-bottom: 20px;
            padding-left: 0;
        }
        .siarhe-flex-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
</style>

<div class="siarhe-admin-card">
    <h2>🎨 Personalización Visual (Frontend)</h2>
    <p>Define los colores que verán los usuarios en los mapas y tablas del sitio web.</p>

    <h3 class="siarhe-section-title" style="margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Mapa de Calor (Escala de Colores)</h3>
    <p class="description">Define la escala de color para los valores, desde el más bajo hasta el más alto.</p>
    
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Gradiente (Valores > 0)</th>
            <td>
                <div class="siarhe-grad-row">
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[map_c1]" value="<?php echo esc_attr($opts['map_c1']); ?>" class="siarhe-color-field" data-alpha="true">
                        <p class="description"><small>Mínimo</small></p>
                    </div>
                    <span class="dashicons dashicons-arrow-right-alt" style="color:#aaa;"></span>
                    
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[map_c2]" value="<?php echo esc_attr($opts['map_c2']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Q1</small></p>
                    </div>
                    <span class="dashicons dashicons-arrow-right-alt" style="color:#aaa;"></span>
                    
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[map_c3]" value="<?php echo esc_attr($opts['map_c3']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Mediana</small></p>
                    </div>
                    <span class="dashicons dashicons-arrow-right-alt" style="color:#aaa;"></span>
                    
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[map_c4]" value="<?php echo esc_attr($opts['map_c4']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Q3</small></p>
                    </div>
                    <span class="dashicons dashicons-arrow-right-alt" style="color:#aaa;"></span>
                    
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[map_c5]" value="<?php echo esc_attr($opts['map_c5']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Máximo</small></p>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row">Casos Especiales</th>
            <td>
                <div class="siarhe-flex-row">
                    <div>
                        <label><strong>Valor 0 (Gris)</strong></label><br>
                        <input type="text" name="siarhe_map_options[map_zero]" value="<?php echo esc_attr($opts['map_zero']); ?>" class="siarhe-color-field">
                        <p class="description">El dato existe pero es 0.</p>
                    </div>
                    <div>
                        <label><strong>Sin Datos (Negro)</strong></label><br>
                        <input type="text" name="siarhe_map_options[map_null]" value="<?php echo esc_attr($opts['map_null']); ?>" class="siarhe-color-field">
                        <p class="description">La entidad no está en el CSV.</p>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <h3 class="siarhe-section-title" style="margin-top: 40px; border-bottom: 1px solid #eee; padding-bottom: 10px;">📍 Estilos de Marcadores (Clínicas)</h3>
    <p class="description">Personaliza la forma y los colores de las chinchetas sobre el mapa.</p>

    <table class="form-table">
        <tr>
            <th scope="row">Clínicas de Catéteres</th>
            <td>
                <div class="siarhe-flex-row">
                    <div>
                        <label>Forma:</label><br>
                        <select name="siarhe_map_options[m_cateter_shape]">
                            <option value="circle" <?php selected($opts['m_cateter_shape'], 'circle'); ?>>Círculo</option>
                            <option value="square" <?php selected($opts['m_cateter_shape'], 'square'); ?>>Cuadrado</option>
                            <option value="triangle" <?php selected($opts['m_cateter_shape'], 'triangle'); ?>>Triángulo</option>
                            <option value="star" <?php selected($opts['m_cateter_shape'], 'star'); ?>>Estrella</option>
                            <option value="cross" <?php selected($opts['m_cateter_shape'], 'cross'); ?>>Cruz (Hospital)</option>
                        </select>
                    </div>
                    <div>
                        <label>Color de Relleno:</label><br>
                        <input type="text" name="siarhe_map_options[m_cateter_fill]" value="<?php echo esc_attr($opts['m_cateter_fill']); ?>" class="siarhe-color-field">
                    </div>
                    <div>
                        <label>Color de Borde:</label><br>
                        <input type="text" name="siarhe_map_options[m_cateter_stroke]" value="<?php echo esc_attr($opts['m_cateter_stroke']); ?>" class="siarhe-color-field">
                    </div>
                </div>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Clínicas de Heridas</th>
            <td>
                <div class="siarhe-flex-row">
                    <div>
                        <label>Forma:</label><br>
                        <select name="siarhe_map_options[m_heridas_shape]">
                            <option value="circle" <?php selected($opts['m_heridas_shape'], 'circle'); ?>>Círculo</option>
                            <option value="square" <?php selected($opts['m_heridas_shape'], 'square'); ?>>Cuadrado</option>
                            <option value="triangle" <?php selected($opts['m_heridas_shape'], 'triangle'); ?>>Triángulo</option>
                            <option value="star" <?php selected($opts['m_heridas_shape'], 'star'); ?>>Estrella</option>
                            <option value="cross" <?php selected($opts['m_heridas_shape'], 'cross'); ?>>Cruz (Hospital)</option>
                        </select>
                    </div>
                    <div>
                        <label>Color de Relleno:</label><br>
                        <input type="text" name="siarhe_map_options[m_heridas_fill]" value="<?php echo esc_attr($opts['m_heridas_fill']); ?>" class="siarhe-color-field">
                    </div>
                    <div>
                        <label>Color de Borde:</label><br>
                        <input type="text" name="siarhe_map_options[m_heridas_stroke]" value="<?php echo esc_attr($opts['m_heridas_stroke']); ?>" class="siarhe-color-field">
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">Establecimientos de Salud</th>
            <td>
                <div class="siarhe-flex-row">
                    <div>
                        <label>Forma:</label><br>
                        <select name="siarhe_map_options[m_estab_shape]">
                            <option value="circle" <?php selected($opts['m_estab_shape'], 'circle'); ?>>Círculo</option>
                            <option value="square" <?php selected($opts['m_estab_shape'], 'square'); ?>>Cuadrado</option>
                            <option value="triangle" <?php selected($opts['m_estab_shape'], 'triangle'); ?>>Triángulo</option>
                            <option value="star" <?php selected($opts['m_estab_shape'], 'star'); ?>>Estrella</option>
                            <option value="cross" <?php selected($opts['m_estab_shape'], 'cross'); ?>>Cruz (Hospital)</option>
                        </select>
                    </div>
                    <div>
                        <label>Color de Relleno:</label><br>
                        <input type="text" name="siarhe_map_options[m_estab_fill]" value="<?php echo esc_attr($opts['m_estab_fill']); ?>" class="siarhe-color-field">
                    </div>
                    <div>
                        <label>Color de Borde:</label><br>
                        <input type="text" name="siarhe_map_options[m_estab_stroke]" value="<?php echo esc_attr($opts['m_estab_stroke']); ?>" class="siarhe-color-field">
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <h3 class="siarhe-section-title" style="margin-top: 40px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Estilos de Tabla de Datos</h3>
    
    <table class="form-table">
        <tr>
            <th scope="row">Encabezados (Header)</th>
            <td>
                <div class="siarhe-flex-row">
                    <div>
                        <label>Fondo:</label><br>
                        <input type="text" name="siarhe_map_options[th_bg]" value="<?php echo esc_attr($opts['th_bg']); ?>" class="siarhe-color-field">
                    </div>
                    <div>
                        <label>Texto:</label><br>
                        <input type="text" name="siarhe_map_options[th_text]" value="<?php echo esc_attr($opts['th_text']); ?>" class="siarhe-color-field">
                    </div>
                </div>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Fila de Totales (ID 9999)</th>
            <td>
                <div class="siarhe-flex-row">
                    <div>
                        <label>Fondo:</label><br>
                        <input type="text" name="siarhe_map_options[tr_total_bg]" value="<?php echo esc_attr($opts['tr_total_bg']); ?>" class="siarhe-color-field">
                    </div>
                    <div>
                        <label>Texto:</label><br>
                        <input type="text" name="siarhe_map_options[tr_total_txt]" value="<?php echo esc_attr($opts['tr_total_txt']); ?>" class="siarhe-color-field">
                    </div>
                </div>
                <p class="description" style="margin-top:10px;">Aplica solo a la fila que contiene el Total Nacional o Estatal.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">Cuerpo Tabla (Impar)</th>
            <td>
                <div class="siarhe-flex-row">
                    <div>
                        <label>Fondo (Odd):</label><br>
                        <input type="text" name="siarhe_map_options[tr_odd]" value="<?php echo esc_attr($opts['tr_odd']); ?>" class="siarhe-color-field">
                    </div>
                    <div>
                        <label>Texto (Odd):</label><br>
                        <input type="text" name="siarhe_map_options[tr_odd_txt]" value="<?php echo esc_attr($opts['tr_odd_txt']); ?>" class="siarhe-color-field">
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">Cuerpo Tabla (Par)</th>
            <td>
                <div class="siarhe-flex-row">
                    <div>
                        <label>Fondo (Even):</label><br>
                        <input type="text" name="siarhe_map_options[tr_even]" value="<?php echo esc_attr($opts['tr_even']); ?>" class="siarhe-color-field">
                    </div>
                    <div>
                        <label>Texto (Even):</label><br>
                        <input type="text" name="siarhe_map_options[tr_even_txt]" value="<?php echo esc_attr($opts['tr_even_txt']); ?>" class="siarhe-color-field">
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">Bordes</th>
            <td>
                <div class="siarhe-flex-row">
                    <div>
                        <label>Color:</label><br>
                        <input type="text" name="siarhe_map_options[border_color]" value="<?php echo esc_attr($opts['border_color']); ?>" class="siarhe-color-field">
                    </div>
                    <div>
                        <label>Grosor (px):</label><br>
                        <input type="number" name="siarhe_map_options[border_width]" value="<?php echo esc_attr($opts['border_width']); ?>" class="small-text" min="0" max="5">
                    </div>
                </div>
            </td>
        </tr>
    </table>
</div>