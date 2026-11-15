<?php // /admin/partials/settings-tabs/tab-general.php
/**
 * Configuración › General: opciones que valen para todo el sitio público.
 * Opción: siarhe_general_options (array). Hoy guarda la disposición por defecto de las páginas del plugin.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$siarhe_layout_actual = siarhe_get_layout();
?>
<div class="siarhe-card">
    <h2 class="siarhe-card__title">Disposición de las páginas<?php Siarhe_Admin::help('Cómo se muestran por defecto el mapa, los controles y la tabla. Contenedor: centrados, con un ancho máximo de 1140 px. Canvas: todo el ancho de la ventana, fuera del contenedor del tema, con un margen de seguridad a cada lado. El visitante puede cambiar de una a otra con el botón «Ancho completo» del mapa; su elección se guarda solo en su navegador.'); ?></h2>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="siarhe-layout">Disposición por defecto</label></th>
            <td>
                <select name="siarhe_general_options[layout]" id="siarhe-layout">
                    <option value="contenedor"<?php selected( $siarhe_layout_actual['modo'], 'contenedor' ); ?>>Contenedor (máximo 1140 px)</option>
                    <option value="canvas"<?php selected( $siarhe_layout_actual['modo'], 'canvas' ); ?>>Canvas (ancho completo)</option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="siarhe-canvas-margin">Margen lateral del canvas (%)</label><?php Siarhe_Admin::help('Espacio de seguridad a cada lado en la disposición canvas, como porcentaje del ancho de la ventana: de 2.5 a 5.'); ?></th>
            <td><input type="number" name="siarhe_general_options[canvas_margin]" id="siarhe-canvas-margin" min="2.5" max="5" step="0.5" value="<?php echo esc_attr( $siarhe_layout_actual['margen'] ); ?>" class="small-text"> %</td>
        </tr>
    </table>
</div>
