<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener opciones guardadas (o defaults)
$theme_options = get_option( 'siarhe_theme_options', [] );

// Valores por defecto si no existen
$defaults = [
    'th_bg'       => '#fcfcfc',
    'th_text'     => '#1d2327',
    'odd_bg'      => '#f9f9f9',
    'odd_text'    => '#50575e',
    'even_bg'     => '#ffffff',
    'even_text'   => '#50575e',
];

// Fusionar guardados con defaults
$colors = wp_parse_args( $theme_options, $defaults );
?>

<div class="card" style="padding: 20px;">
    <h2>🎨 Personalización del Tema Admin</h2>
    <p>Personaliza los colores de las tablas dentro del panel de administración del plugin.</p>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Cabecera de Tabla (Header)</th>
            <td>
                <fieldset>
                    <label style="margin-right: 20px;">
                        Fondo:<br>
                        <input type="text" name="siarhe_theme_options[th_bg]" 
                               value="<?php echo esc_attr($colors['th_bg']); ?>" 
                               class="siarhe-color-field" data-variable="--siarhe-th-bg">
                    </label>
                    <label>
                        Texto:<br>
                        <input type="text" name="siarhe_theme_options[th_text]" 
                               value="<?php echo esc_attr($colors['th_text']); ?>" 
                               class="siarhe-color-field" data-variable="--siarhe-th-text">
                    </label>
                </fieldset>
            </td>
        </tr>

        <tr>
            <th scope="row">Filas Impares (1, 3, 5...)</th>
            <td>
                <fieldset>
                    <label style="margin-right: 20px;">
                        Fondo:<br>
                        <input type="text" name="siarhe_theme_options[odd_bg]" 
                               value="<?php echo esc_attr($colors['odd_bg']); ?>" 
                               class="siarhe-color-field" data-variable="--siarhe-tr-odd-bg">
                    </label>
                    <label>
                        Texto:<br>
                        <input type="text" name="siarhe_theme_options[odd_text]" 
                               value="<?php echo esc_attr($colors['odd_text']); ?>" 
                               class="siarhe-color-field" data-variable="--siarhe-tr-odd-text">
                    </label>
                </fieldset>
            </td>
        </tr>

        <tr>
            <th scope="row">Filas Pares (2, 4, 6...)</th>
            <td>
                <fieldset>
                    <label style="margin-right: 20px;">
                        Fondo:<br>
                        <input type="text" name="siarhe_theme_options[even_bg]" 
                               value="<?php echo esc_attr($colors['even_bg']); ?>" 
                               class="siarhe-color-field" data-variable="--siarhe-tr-even-bg">
                    </label>
                    <label>
                        Texto:<br>
                        <input type="text" name="siarhe_theme_options[even_text]" 
                               value="<?php echo esc_attr($colors['even_text']); ?>" 
                               class="siarhe-color-field" data-variable="--siarhe-tr-even-text">
                    </label>
                </fieldset>
            </td>
        </tr>
    </table>

    <hr>

    <h3>Vista Previa</h3>
    <table class="siarhe-table">
        <thead>
            <tr>
                <th>Columna 1</th>
                <th>Columna 2</th>
                <th>Columna 3</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Dato Impar 1</td>
                <td>Dato Impar 2</td>
                <td><span class="siarhe-badge success">Activo</span></td>
            </tr>
            <tr>
                <td>Dato Par 1</td>
                <td>Dato Par 2</td>
                <td><span class="siarhe-badge warning">Pendiente</span></td>
            </tr>
            <tr>
                <td>Dato Impar 1</td>
                <td>Dato Impar 2</td>
                <td><span class="siarhe-badge neutral">Inactivo</span></td>
            </tr>
        </tbody>
    </table>
</div>