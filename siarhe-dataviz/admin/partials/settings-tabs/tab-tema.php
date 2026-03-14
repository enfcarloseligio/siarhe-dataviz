<?php // /admin/partials/settings-tabs/tab-tema.php
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

<style>
    /* Contenedores flexibles para alinear "Fondo" y "Texto" como en Mapas y Colores */
    .siarhe-tema-flex { display: flex; gap: 25px; flex-wrap: wrap; }
    .siarhe-tema-col label { display: block; margin-bottom: 5px; font-weight: 600; color: #50575e; }

    /* Excepción para que la tabla de vista previa no se convierta en acordeón en móviles */
    @media screen and (max-width: 767px) {
        .siarhe-tema-flex { flex-direction: column; gap: 15px; }
        
        #siarhe-preview-table {
            display: table !important;
            border: 1px solid #c3c4c7 !important;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04) !important;
            background: transparent !important;
            width: 100% !important; 
            min-width: 0 !important;
        }
        #siarhe-preview-table thead { display: table-header-group !important; }
        #siarhe-preview-table tr {
            display: table-row !important;
            margin-bottom: 0 !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            cursor: default !important;
        }
        #siarhe-preview-table tr::after { display: none !important; }
        #siarhe-preview-table th,
        #siarhe-preview-table td {
            display: table-cell !important;
            padding: 10px !important;
            text-align: left !important;
            border-bottom: 1px solid #f0f0f1 !important;
            font-size: 13px !important; /* Ajuste para que no desborde en móvil */
        }
        #siarhe-preview-table td::before { display: none !important; }
        /* Evitar que el color cambie en el primer td */
        #siarhe-preview-table td:first-child { color: inherit !important; font-weight: normal !important; font-size: inherit !important; }
    }
</style>

<div class="card siarhe-upload-card" style="max-width: 100%; padding: 20px;">
    <h2>🎨 Personalización del Tema Admin</h2>
    <p>Personaliza los colores de las tablas dentro del panel de administración del plugin.</p>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Cabecera de Tabla (Header)</th>
            <td>
                <div class="siarhe-tema-flex">
                    <div class="siarhe-tema-col">
                        <label>Fondo:</label>
                        <input type="text" name="siarhe_theme_options[th_bg]" value="<?php echo esc_attr($colors['th_bg']); ?>" class="siarhe-color-field" data-variable="--siarhe-th-bg">
                    </div>
                    <div class="siarhe-tema-col">
                        <label>Texto:</label>
                        <input type="text" name="siarhe_theme_options[th_text]" value="<?php echo esc_attr($colors['th_text']); ?>" class="siarhe-color-field" data-variable="--siarhe-th-text">
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">Filas Impares (1, 3, 5...)</th>
            <td>
                <div class="siarhe-tema-flex">
                    <div class="siarhe-tema-col">
                        <label>Fondo:</label>
                        <input type="text" name="siarhe_theme_options[odd_bg]" value="<?php echo esc_attr($colors['odd_bg']); ?>" class="siarhe-color-field" data-variable="--siarhe-tr-odd-bg">
                    </div>
                    <div class="siarhe-tema-col">
                        <label>Texto:</label>
                        <input type="text" name="siarhe_theme_options[odd_text]" value="<?php echo esc_attr($colors['odd_text']); ?>" class="siarhe-color-field" data-variable="--siarhe-tr-odd-text">
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">Filas Pares (2, 4, 6...)</th>
            <td>
                <div class="siarhe-tema-flex">
                    <div class="siarhe-tema-col">
                        <label>Fondo:</label>
                        <input type="text" name="siarhe_theme_options[even_bg]" value="<?php echo esc_attr($colors['even_bg']); ?>" class="siarhe-color-field" data-variable="--siarhe-tr-even-bg">
                    </div>
                    <div class="siarhe-tema-col">
                        <label>Texto:</label>
                        <input type="text" name="siarhe_theme_options[even_text]" value="<?php echo esc_attr($colors['even_text']); ?>" class="siarhe-color-field" data-variable="--siarhe-tr-even-text">
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <hr style="margin: 30px 0;">

    <h3>Vista Previa</h3>
    <div style="overflow-x: auto;">
        <table id="siarhe-preview-table" class="siarhe-table" style="min-width: 400px; max-width: 100%;">
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
</div>