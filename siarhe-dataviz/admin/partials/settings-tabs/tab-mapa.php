<?php
/**
 * siarhe-dataviz/admin/partials/settings-tabs/tab-mapa.php
 * Personalizar › Mapa y Colores: colores de los indicadores, botones y tabla del sitio público y estilo de cada marcador.
 * Cada apartado es un bloque plegable. Los colores de los indicadores y los estilos de los marcadores del sistema
 * tienen valores por defecto (siarhe_get_map_defaults, siarhe_marker_style_defaults) a los que se puede volver.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Opciones guardadas y valores por defecto
$defaults = siarhe_get_map_defaults();
$opts     = siarhe_get_map_options();

// 2. Marcadores configurados, repartidos en los grupos del sistema y «Otros marcadores»
$marcadores   = siarhe_get_marcadores_config();
$grupos_mk    = array();
$nombres_grup = siarhe_marker_grupos();
foreach ( $nombres_grup as $clave_g => $nombre_g ) $grupos_mk[ $nombre_g ] = array();
$grupos_mk['Otros marcadores'] = array();
foreach ( $marcadores as $clave_m => $mk_m ) {
    $g = ( isset( $mk_m['grupo'] ) && isset( $nombres_grup[ $mk_m['grupo'] ] ) ) ? $nombres_grup[ $mk_m['grupo'] ] : 'Otros marcadores';
    $grupos_mk[ $g ][ $clave_m ] = $mk_m;
}
$grupos_mk = array_filter( $grupos_mk );

// 3. Enlaces para volver a los valores del sistema (se piden fuera del formulario de ajustes)
$url_reset = function( $que ) {
    return wp_nonce_url( admin_url( 'admin-post.php?action=siarhe_reset_colors&que=' . $que ), 'siarhe_reset_colors' );
};
$avisos_reset = array(
    'mapa'       => 'Los colores de los indicadores volvieron a los del sistema.',
    'marcadores' => 'Los estilos de los marcadores volvieron a los del sistema.',
    'todo'       => 'Los colores de los indicadores y los estilos de los marcadores volvieron a los del sistema.',
);
$reset_hecho = isset( $_GET['siarhe_reset'] ) ? sanitize_key( $_GET['siarhe_reset'] ) : '';
?>

<style>
    .siarhe-flex-row { display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end; }

    /* Apartados plegables */
    .siarhe-plegable { border: 1px solid #dcdcde; border-radius: 6px; background: #fff; margin: 0 0 12px; }
    .siarhe-plegable > summary { cursor: pointer; padding: 12px 16px; list-style: none; display: flex; align-items: center; gap: 8px; }
    .siarhe-plegable > summary::-webkit-details-marker { display: none; }
    .siarhe-plegable > summary::before { content: "\25B8"; color: #646970; font-size: 14px; }
    .siarhe-plegable[open] > summary::before { content: "\25BE"; }
    .siarhe-plegable[open] > summary { border-bottom: 1px solid #f0f0f1; }
    .siarhe-plegable > summary h3, .siarhe-plegable > summary strong { margin: 0; padding: 0; border: 0; font-size: 14px; }
    .siarhe-plegable > summary .siarhe-plegable-cuenta { margin-left: auto; color: #646970; font-size: 12px; font-weight: 400; }
    .siarhe-plegable-cuerpo { padding: 4px 16px 12px; }
    .siarhe-plegable .siarhe-plegable { margin: 10px 0 0; background: #fcfcfc; }
    .siarhe-reset-fila { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 10px 0 4px; }
    .siarhe-badge.sistema { background: #e7f1fb; color: #0a4b78; border-color: #b8d6f0; }

    .siarhe-marker-row { display: flex; gap: 20px; flex-wrap: wrap; align-items: center; border-bottom: 1px dashed #eee; padding-bottom: 15px; margin-bottom: 15px; }
    .siarhe-marker-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

    .siarhe-grad-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
    .siarhe-color-box { min-width: 60px; text-align: center; }

    .siarhe-preview-box {
        background: #f0f6fc; border: 1px dashed #c3c4c7; border-radius: 6px; padding: 10px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        min-width: 80px; height: 75px; box-sizing: border-box;
    }
    .siarhe-preview-box label { font-size:10px; color:#888; margin-bottom:5px; }

    .siarhe-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; border: 1px solid transparent; }
    .siarhe-badge.neutral { background: #f0f0f1; color: #3c434a; border-color: #c3c4c7; }
    .siarhe-badge.espectro { background: #fef3c7; color: #92400e; border-color: #fde68a; }
    .siarhe-badge.spike { background: #fee2e2; color: #991b1b; border-color: #fecaca; }

    /* Icono de ayuda "?" con tooltip (hover o foco de teclado) */
    span.siarhe-help {
        position: relative; display: inline-flex; align-items: center; justify-content: center;
        width: 16px; height: 16px; margin-left: 4px; border-radius: 50%;
        background: #dcdcde; color: #3c434a; font-size: 11px; font-weight: 700; line-height: 1;
        cursor: help; vertical-align: middle;
    }
    span.siarhe-help:hover, span.siarhe-help:focus { background: #2271b1; color: #fff; outline: none; }
    span.siarhe-help::after {
        content: attr(data-tip);
        position: absolute; left: 50%; top: calc(100% + 8px); transform: translateX(-50%);
        width: 240px; padding: 8px 10px; border-radius: 4px;
        background: #1d2327; color: #fff; font-size: 12px; font-weight: 400; line-height: 1.4; text-align: left;
        box-shadow: 0 2px 8px rgba(0,0,0,.25); white-space: normal;
        opacity: 0; visibility: hidden; pointer-events: none; transition: opacity .15s; z-index: 100;
    }
    span.siarhe-help:hover::after, span.siarhe-help:focus::after { opacity: 1; visibility: visible; }

    @media (max-width: 782px) {
        .siarhe-card .form-table th { display: block; width: 100%; padding-bottom: 5px; }
        .siarhe-card .form-table td { display: block; width: 100%; padding-bottom: 20px; padding-left: 0; }
        .siarhe-flex-row, .siarhe-marker-row { flex-direction: column; align-items: flex-start; gap: 10px; width: 100%; }

        .siarhe-preview-box {
            width: 100%; flex-direction: row; justify-content: flex-start;
            gap: 15px; height: auto; padding: 10px 15px;
        }
        .siarhe-preview-box label { margin-bottom: 0; }
    }
</style>

<div class="siarhe-card siarhe-upload-card">
    <h2>🎨 Personalización Visual (Frontend)</h2>
    <p>Define los colores que verán los usuarios en los mapas y tablas del sitio web.</p>
    <?php if ( isset( $avisos_reset[ $reset_hecho ] ) ) : ?>
        <div class="notice notice-success inline"><p><?php echo esc_html( $avisos_reset[ $reset_hecho ] ); ?></p></div>
    <?php endif; ?>

    <details class="siarhe-plegable" open><summary><h3 class="siarhe-section-title">Botones del Mapa y la Tabla<?php Siarhe_Admin::help('Estilo unico de todos los botones del sitio publico (Personalizar, Restablecer, Mapa PNG, Etiquetas, Copiar enlace, Descargar Excel). El color del texto tambien pinta los iconos del boton.'); ?></h3></summary><div class="siarhe-plegable-cuerpo">
    <p class="description">Todos los botones comparten este estilo. El color de texto también se aplica a sus íconos.</p>

    <table class="form-table">
        <tr>
            <th scope="row">Estado normal<?php Siarhe_Admin::help('Colores del boton en reposo.'); ?></th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[btn_bg]" value="<?php echo esc_attr($opts['btn_bg']); ?>" class="siarhe-color-field"></div><div><label>Borde:</label><br><input type="text" name="siarhe_map_options[btn_border]" value="<?php echo esc_attr($opts['btn_border']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[btn_text]" value="<?php echo esc_attr($opts['btn_text']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
        <tr>
            <th scope="row">Al pasar el cursor<?php Siarhe_Admin::help('Colores cuando el cursor esta sobre el boton o recibe el foco del teclado.'); ?></th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[btn_hover_bg]" value="<?php echo esc_attr($opts['btn_hover_bg']); ?>" class="siarhe-color-field"></div><div><label>Borde:</label><br><input type="text" name="siarhe_map_options[btn_hover_border]" value="<?php echo esc_attr($opts['btn_hover_border']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[btn_hover_text]" value="<?php echo esc_attr($opts['btn_hover_text']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
        <tr>
            <th scope="row">Al presionar<?php Siarhe_Admin::help('Colores mientras el boton se mantiene presionado.'); ?></th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[btn_active_bg]" value="<?php echo esc_attr($opts['btn_active_bg']); ?>" class="siarhe-color-field"></div><div><label>Borde:</label><br><input type="text" name="siarhe_map_options[btn_active_border]" value="<?php echo esc_attr($opts['btn_active_border']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[btn_active_text]" value="<?php echo esc_attr($opts['btn_active_text']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
    </table>

    </div></details>

    <details class="siarhe-plegable"><summary><h3 class="siarhe-section-title">Estilos de Tabla de Datos<?php Siarhe_Admin::help('Apariencia de la tabla de datos que se muestra en el sitio publico.'); ?></h3></summary><div class="siarhe-plegable-cuerpo">

    <table class="form-table">
        <tr>
            <th scope="row">Encabezados (Header)<?php Siarhe_Admin::help('Colores de fondo y texto de la fila de encabezados de la tabla publica.'); ?></th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[th_bg]" value="<?php echo esc_attr($opts['th_bg']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[th_text]" value="<?php echo esc_attr($opts['th_text']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
        <tr>
            <th scope="row">Fila de Totales (ID 9999)<?php Siarhe_Admin::help('Estilo de la fila de totales (registro con ID 9999) de la tabla publica.'); ?></th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[tr_total_bg]" value="<?php echo esc_attr($opts['tr_total_bg']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[tr_total_txt]" value="<?php echo esc_attr($opts['tr_total_txt']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
        <tr>
            <th scope="row">Cuerpo Tabla (Impar)<?php Siarhe_Admin::help('Colores de las filas impares de la tabla publica.'); ?></th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[tr_odd]" value="<?php echo esc_attr($opts['tr_odd']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[tr_odd_txt]" value="<?php echo esc_attr($opts['tr_odd_txt']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
        <tr>
            <th scope="row">Cuerpo Tabla (Par)<?php Siarhe_Admin::help('Colores de las filas pares de la tabla publica.'); ?></th>
            <td><div class="siarhe-flex-row"><div><label>Fondo:</label><br><input type="text" name="siarhe_map_options[tr_even]" value="<?php echo esc_attr($opts['tr_even']); ?>" class="siarhe-color-field"></div><div><label>Texto:</label><br><input type="text" name="siarhe_map_options[tr_even_txt]" value="<?php echo esc_attr($opts['tr_even_txt']); ?>" class="siarhe-color-field"></div></div></td>
        </tr>
        <tr>
            <th scope="row">Bordes<?php Siarhe_Admin::help('Color de las lineas de la tabla publica.'); ?></th>
            <td><div class="siarhe-flex-row"><div><label>Color:</label><br><input type="text" name="siarhe_map_options[border_color]" value="<?php echo esc_attr($opts['border_color']); ?>" class="siarhe-color-field"></div><div><label>Grosor (px):</label><br><input type="number" name="siarhe_map_options[border_width]" value="<?php echo esc_attr($opts['border_width']); ?>" class="small-text" min="0" max="5"></div></div></td>
        </tr>
    </table>

    </div></details>

    <details class="siarhe-plegable"><summary><h3 class="siarhe-section-title">Colores de los Indicadores (Mapa de Calor)<?php Siarhe_Admin::help('Colores generales del mapa. Cada metrica puede usar estos o los suyos propios.'); ?></h3></summary><div class="siarhe-plegable-cuerpo">

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Gradiente Cuartiles (Distribución Matemática)<?php Siarhe_Admin::help('Colorea las regiones segun el cuartil en que cae su valor: minimo, Q1, mediana, Q3 y maximo.'); ?></th>
            <td>
                <p class="siarhe-mt-0 siarhe-mb-2 description">Se aplica cuando el indicador requiere división en cuartiles porcentuales estadísticos estandarizados.</p>
                <div class="siarhe-grad-row">
                    <div class="siarhe-color-box"><input type="text" name="siarhe_map_options[map_c1]" value="<?php echo esc_attr($opts['map_c1']); ?>" class="siarhe-color-field" data-alpha="true"><p class="description"><small>Mínimo</small></p></div>
                    <span class="siarhe-text-faint dashicons dashicons-arrow-right-alt"></span>
                    <div class="siarhe-color-box"><input type="text" name="siarhe_map_options[map_c2]" value="<?php echo esc_attr($opts['map_c2']); ?>" class="siarhe-color-field"><p class="description"><small>Q1</small></p></div>
                    <span class="siarhe-text-faint dashicons dashicons-arrow-right-alt"></span>
                    <div class="siarhe-color-box"><input type="text" name="siarhe_map_options[map_c3]" value="<?php echo esc_attr($opts['map_c3']); ?>" class="siarhe-color-field"><p class="description"><small>Mediana</small></p></div>
                    <span class="siarhe-text-faint dashicons dashicons-arrow-right-alt"></span>
                    <div class="siarhe-color-box"><input type="text" name="siarhe_map_options[map_c4]" value="<?php echo esc_attr($opts['map_c4']); ?>" class="siarhe-color-field"><p class="description"><small>Q3</small></p></div>
                    <span class="siarhe-text-faint dashicons dashicons-arrow-right-alt"></span>
                    <div class="siarhe-color-box"><input type="text" name="siarhe_map_options[map_c5]" value="<?php echo esc_attr($opts['map_c5']); ?>" class="siarhe-color-field"><p class="description"><small>Máximo</small></p></div>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">Gradiente por Rangos (4 Niveles Personalizados)<?php Siarhe_Admin::help('Colores para la escala por rangos, de 4 niveles con cortes definidos en cada metrica.'); ?></th>
            <td>
                <p class="siarhe-mt-0 siarhe-mb-2 description">Se aplica en indicadores donde el usuario ha definido manualmente 4 rangos de valores absolutos o tasas (Ej: Rango 1, Rango 2, etc.).</p>
                <div class="siarhe-grad-row">
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[range_c1]" value="<?php echo esc_attr($opts['range_c1']); ?>" class="siarhe-color-field" data-alpha="true">
                        <p class="description"><small>Rango 1<br>(Ej. Vmin < Q1)</small></p>
                    </div>
                    <span class="siarhe-text-faint dashicons dashicons-arrow-right-alt"></span>
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[range_c2]" value="<?php echo esc_attr($opts['range_c2']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Rango 2<br>(Ej. Q1 < Q2)</small></p>
                    </div>
                    <span class="siarhe-text-faint dashicons dashicons-arrow-right-alt"></span>
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[range_c3]" value="<?php echo esc_attr($opts['range_c3']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Rango 3<br>(Ej. Q2 < Q3)</small></p>
                    </div>
                    <span class="siarhe-text-faint dashicons dashicons-arrow-right-alt"></span>
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[range_c4]" value="<?php echo esc_attr($opts['range_c4']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Rango 4<br>(Ej. Q3 ≤ Vmax)</small></p>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row">Gradiente Monocromático<?php Siarhe_Admin::help('Degradado entre dos colores (minimo y maximo) para la escala monocromatica.'); ?></th>
            <td>
                <p class="siarhe-mt-0 siarhe-mb-2 description">Esta escala se usa cuando el usuario activa la opción de ver el mapa sin cortes de cuartiles (escala lineal suave).</p>
                <div class="siarhe-grad-row">
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[mono_min]" value="<?php echo esc_attr($opts['mono_min']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Color Claro (Mínimo)</small></p>
                    </div>
                    <span class="siarhe-text-faint dashicons dashicons-arrow-right-alt"></span>
                    <div class="siarhe-color-box">
                        <input type="text" name="siarhe_map_options[mono_max]" value="<?php echo esc_attr($opts['mono_max']); ?>" class="siarhe-color-field">
                        <p class="description"><small>Color Oscuro (Máximo)</small></p>
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">Casos Especiales<?php Siarhe_Admin::help('Color para regiones con valor 0 y para las que no tienen dato.'); ?></th>
            <td>
                <div class="siarhe-flex-row">
                    <div><label><strong>Valor 0</strong></label><br><input type="text" name="siarhe_map_options[map_zero]" value="<?php echo esc_attr($opts['map_zero']); ?>" class="siarhe-color-field"></div>
                    <div><label><strong>Sin Datos</strong></label><br><input type="text" name="siarhe_map_options[map_null]" value="<?php echo esc_attr($opts['map_null']); ?>" class="siarhe-color-field"></div>
                </div>
            </td>
        </tr>
    </table>

    <div class="siarhe-reset-fila">
        <a class="button" href="<?php echo esc_url( $url_reset( 'mapa' ) ); ?>" onclick="return confirm('Se quitarán los colores guardados de los indicadores y volverán los del sistema. ¿Continuar?');">Restablecer colores de los indicadores</a>
        <span class="description">Vuelve al degradado, los rangos, el monocromático, el valor 0 y el sin dato del sistema.</span>
    </div>

    <?php // La disposición (contenedor o canvas) se configura en Configuración › General; aquí solo se conserva lo guardado antes ?>
    <?php $siarhe_layout_actual = siarhe_get_layout(); ?>
    <input type="hidden" name="siarhe_map_options[layout]" value="<?php echo esc_attr( $siarhe_layout_actual['modo'] ); ?>">
    <input type="hidden" name="siarhe_map_options[canvas_margin]" value="<?php echo esc_attr( $siarhe_layout_actual['margen'] ); ?>">

    </div></details>

    <details class="siarhe-plegable"><summary><h3 class="siarhe-section-title">📍 Estilos Dinámicos de Marcadores<?php Siarhe_Admin::help('Forma, relleno y borde de cada marcador. Se generan a partir de los marcadores definidos en Configuracion > Marcadores.'); ?></h3></summary><div class="siarhe-plegable-cuerpo">
    <p class="description">Aparecen aquí todos los marcadores de Configuración › Marcadores, por grupo. Los del sistema ya traen su estilo; lo que cambies y guardes lo sustituye.</p>
    <div class="siarhe-reset-fila">
        <a class="button" href="<?php echo esc_url( $url_reset( 'marcadores' ) ); ?>" onclick="return confirm('Se quitarán los estilos guardados de todos los marcadores y volverán los del sistema. ¿Continuar?');">Restablecer estilos de los marcadores</a>
        <span class="description">Quita lo guardado: los marcadores del sistema vuelven a su estilo y los propios al círculo azul.</span>
    </div>

    <?php foreach ( $grupos_mk as $titulo_grupo => $lista_grupo ) : ?>
    <details class="siarhe-plegable"><summary><strong><?php echo esc_html( $titulo_grupo ); ?></strong><span class="siarhe-plegable-cuenta"><?php echo count( $lista_grupo ); ?> marcador<?php echo count( $lista_grupo ) === 1 ? '' : 'es'; ?></span></summary><div class="siarhe-plegable-cuerpo">
    <table class="form-table">
        <?php foreach ( $lista_grupo as $key => $mk ) :
            $s_key = strtolower( $key );
            $tipo = isset( $mk['tipo'] ) ? $mk['tipo'] : 'posicion';
            $is_espectro = ( $tipo === 'espectro' );
            $is_spike    = ( $tipo === 'spike' );
            $estilo_mk   = siarhe_marker_style( $key, $mk, $opts );
            $shape_val   = $estilo_mk['shape'];
            $fill_val    = $estilo_mk['fill'];
            $stroke_val  = $estilo_mk['stroke'];
            $fill2_val   = $estilo_mk['fill2'];   // espinas: color del pico más bajo (vacío = versión clara del color alto)
        ?>
        <tr>
            <th scope="row">
                <strong style="font-size: 14px;"><?php echo esc_html($mk['label']); ?></strong><br>
                <small style="color:#8c8f94; font-family:monospace;"><?php echo esc_html($key); ?></small>

                <div style="margin-top: 6px;">
                    <?php if ($is_espectro): ?>
                        <span class="siarhe-badge espectro">🔴 Espectro (Burbuja)</span>
                    <?php elseif ($is_spike): ?>
                        <span class="siarhe-badge spike">📈 Spike (Pico)</span>
                    <?php else: ?>
                        <span class="siarhe-badge neutral">📍 Posición Fija</span>
                    <?php endif; ?>
                    <?php if ( ! empty( $mk['is_core'] ) || ! empty( $mk['sistema'] ) ) : ?><span class="siarhe-badge sistema">Sistema</span><?php endif; ?>
                </div>
            </th>
            <td>
                <div class="siarhe-marker-row" style="border-bottom:none; margin-bottom:0; padding-bottom:0;">
                    <div class="siarhe-preview-box">
                        <label>Vista Previa</label>
                        <svg id="preview-<?php echo esc_attr($s_key); ?>" width="<?php echo ($is_espectro || $is_spike) ? '36' : '30'; ?>" height="<?php echo ($is_espectro || $is_spike) ? '36' : '30'; ?>" viewBox="0 0 30 30"></svg>
                    </div>

                    <div>
                        <label>Forma:</label><br>
                        <?php if ($is_espectro || $is_spike): ?>
                            <input type="hidden" name="siarhe_map_options[m_<?php echo esc_attr($s_key); ?>_shape]" id="m_<?php echo esc_attr($s_key); ?>_shape" value="circle">
                            <select disabled style="background:#f0f0f1; color:#666; cursor:not-allowed;">
                                <option><?php echo $is_spike ? 'Pico (triángulo)' : 'Círculo (Burbuja)'; ?></option>
                            </select>
                        <?php else: ?>
                            <select name="siarhe_map_options[m_<?php echo esc_attr($s_key); ?>_shape]" id="m_<?php echo esc_attr($s_key); ?>_shape">
                                <?php foreach ( siarhe_marker_formas() as $forma_id => $forma_nombre ) : ?>
                                <option value="<?php echo esc_attr( $forma_id ); ?>" <?php selected( $shape_val, $forma_id ); ?>><?php echo esc_html( $forma_nombre ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label><?php echo $is_spike ? 'Color del pico más alto:' : 'Relleno:'; ?><?php if ($is_spike): ?> <span class="siarhe-help" tabindex="0" role="note" data-tip="Es el color del pico con el valor más alto del marcador. Los valores menores se degradan hacia el color más bajo." aria-label="Ayuda: color del pico más alto">?</span><?php endif; ?></label><br>
                        <input type="text" name="siarhe_map_options[m_<?php echo esc_attr($s_key); ?>_fill]" id="m_<?php echo esc_attr($s_key); ?>_fill" value="<?php echo esc_attr($fill_val); ?>" class="siarhe-color-field" <?php if($is_espectro || $is_spike) echo 'data-alpha="true"'; ?>>
                    </div>

                    <?php if ($is_spike): ?>
                    <div>
                        <label>Color del pico más bajo <small>(opcional)</small>: <span class="siarhe-help" tabindex="0" role="note" data-tip="Los valores bajos se degradan hacia este color. Si lo dejas vacío se usa una versión clara del color del pico más alto." aria-label="Ayuda: color del pico más bajo">?</span></label><br>
                        <input type="text" name="siarhe_map_options[m_<?php echo esc_attr($s_key); ?>_fill2]" id="m_<?php echo esc_attr($s_key); ?>_fill2" value="<?php echo esc_attr($fill2_val); ?>" class="siarhe-color-field" data-alpha="true">
                    </div>
                    <?php else: ?>
                    <div>
                        <label>Borde:</label><br>
                        <input type="text" name="siarhe_map_options[m_<?php echo esc_attr($s_key); ?>_stroke]" id="m_<?php echo esc_attr($s_key); ?>_stroke" value="<?php echo esc_attr($stroke_val); ?>" class="siarhe-color-field">
                    </div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div></details>
    <?php endforeach; ?>

    </div></details>

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
        cross: '<path d="M11,5 H19 V11 H25 V19 H19 V25 H11 V19 H5 V11 H11 Z" />',
        triangle2: '<polygon points="15,26 26,6 4,6" />',
        ellipse: '<ellipse cx="15" cy="15" rx="7.5" ry="12" />',
        diamond2: '<polygon points="15,4 23,15 15,26 7,15" />',
        square2: '<rect x="8" y="8" width="14" height="14" />',
        plus: '<path d="M15,4 V26 M4,15 H26" />',
        times: '<path d="M7,7 L23,23 M23,7 L7,23" />',
        asterisk: '<path d="M15,4 V26 M5.5,9.5 L24.5,20.5 M24.5,9.5 L5.5,20.5" />',
        wye: '<path d="M15,15 V27 M15,15 L5,9 M15,15 L25,9" />'
    };
    // Formas de líneas finas: el borde toma el color del relleno, como en el mapa
    const deLinea = ['plus', 'times', 'asterisk', 'wye'];

    // Convierte cualquier color CSS (hex, rgb, rgba) a [r, g, b, a] usando el propio navegador
    const colorCtx = document.createElement('canvas').getContext('2d');
    function parseColor(value) {
        colorCtx.fillStyle = '#000000';
        colorCtx.fillStyle = value || '#000000';
        const v = colorCtx.fillStyle;
        if (v.charAt(0) === '#') {
            return [parseInt(v.substr(1, 2), 16), parseInt(v.substr(3, 2), 16), parseInt(v.substr(5, 2), 16), 1];
        }
        const m = v.match(/[\d.]+/g) || [0, 0, 0, 1];
        return [+m[0], +m[1], +m[2], m[3] === undefined ? 1 : +m[3]];
    }
    function mixWithWhite(value, amount) {
        const c = parseColor(value);
        const mix = (x) => Math.round(x + (255 - x) * amount);
        return `rgba(${mix(c[0])}, ${mix(c[1])}, ${mix(c[2])}, ${c[3]})`;
    }

    function updatePreview(type) {
        const shapeSelect = document.getElementById(`m_${type}_shape`);
        const fillInput = document.getElementById(`m_${type}_fill`);
        const strokeInput = document.getElementById(`m_${type}_stroke`);
        const fill2Input = document.getElementById(`m_${type}_fill2`);
        const svgBox = document.getElementById(`preview-${type}`);

        // Spike: dos picos de ejemplo (alto y bajo) con el degradado que se verá en el mapa
        if (fill2Input && fillInput && svgBox) {
            const high = fillInput.value || '#D63638';
            const low = fill2Input.value || mixWithWhite(high, 0.78);
            svgBox.innerHTML =
                `<polygon points="4,28 9,3 14,28" fill="${high}" stroke="${high}" stroke-width="1" stroke-linejoin="round" />` +
                `<polygon points="17,28 21,15 25,28" fill="${low}" stroke="${high}" stroke-width="1" stroke-linejoin="round" />`;
            return;
        }

        if (shapeSelect && fillInput && strokeInput && svgBox) {
            const shape = shapeSelect.value;
            const fill = fillInput.value || '#000000';
            const stroke = strokeInput.value || '#ffffff';

            svgBox.innerHTML = shapes[shape] || shapes.circle;
            const el = svgBox.firstElementChild;
            const linea = deLinea.indexOf(shape) !== -1;
            el.setAttribute('fill', linea ? 'none' : fill);
            el.setAttribute('stroke', linea ? fill : stroke);
            el.setAttribute('stroke-width', linea ? '3' : '2');
            el.setAttribute('stroke-linejoin', 'round');
        }
    }

    // Generación dinámica de array de claves
    const types = <?php echo wp_json_encode(array_map('strtolower', array_keys($marcadores))); ?>;

    types.forEach(t => updatePreview(t));

    // Las vistas previas se actualizan por eventos (el selector de color dispara 'change')
    const previewField = /^m_(.+)_(shape|fill|fill2|stroke)$/;
    ['input', 'change'].forEach(evt => {
        document.addEventListener(evt, (e) => {
            const m = e.target && e.target.id ? previewField.exec(e.target.id) : null;
            if (m && types.includes(m[1])) updatePreview(m[1]);
        });
    });
});
</script>