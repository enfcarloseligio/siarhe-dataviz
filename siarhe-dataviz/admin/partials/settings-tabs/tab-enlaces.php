<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener listado de todas las páginas de WordPress
$pages = get_pages(); 
$options_links = [];

if ( $pages ) {
    foreach ( $pages as $page ) {
        // Mantenemos el ID de las páginas tal cual para compatibilidad
        $options_links[$page->ID] = '(Página) ' . $page->post_title;
    }
}

// 1.1 Obtener listado de todas las Categorías
$categories = get_terms([
    'taxonomy'   => 'category',
    'hide_empty' => false,
]);

if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
    foreach ( $categories as $cat ) {
        // Agregamos el prefijo 'cat_' para diferenciarlas de las páginas
        $options_links['cat_' . $cat->term_id] = '(Categoría) ' . $cat->name;
    }
}

// 2. Obtener configuración guardada de la base de datos
$siarhe_links = get_option( 'siarhe_links_map', [] ); 

// 3. Lista Maestra de Entidades + Clínicas
$entidades = [
    // Nivel Nacional
    'republica-mexicana'  => 'República Mexicana (Vista Nacional)',
    
    // Entidades Federativas (Orden Alfabético)
    'aguascalientes'      => 'Aguascalientes',
    'baja-california'     => 'Baja California',
    'baja-california-sur' => 'Baja California Sur',
    'campeche'            => 'Campeche',
    'chiapas'             => 'Chiapas',
    'chihuahua'           => 'Chihuahua',
    'ciudad-de-mexico'    => 'Ciudad de México',
    'coahuila'            => 'Coahuila',
    'colima'              => 'Colima',
    'durango'             => 'Durango',
    'guanajuato'          => 'Guanajuato',
    'guerrero'            => 'Guerrero',
    'hidalgo'             => 'Hidalgo',
    'jalisco'             => 'Jalisco',
    'mexico'              => 'Estado de México',
    'michoacan'           => 'Michoacán',
    'morelos'             => 'Morelos',
    'nayarit'             => 'Nayarit',
    'nuevo-leon'          => 'Nuevo León',
    'oaxaca'              => 'Oaxaca',
    'puebla'              => 'Puebla',
    'queretaro'           => 'Querétaro',
    'quintana-roo'        => 'Quintana Roo',
    'san-luis-potosi'     => 'San Luis Potosí',
    'sinaloa'             => 'Sinaloa',
    'sonora'              => 'Sonora',
    'tabasco'             => 'Tabasco',
    'tamaulipas'          => 'Tamaulipas',
    'tlaxcala'            => 'Tlaxcala',
    'veracruz'            => 'Veracruz',
    'yucatan'             => 'Yucatán',
    'zacatecas'           => 'Zacatecas',

    // Mapas Temáticos Especiales
    'clinicas-heridas'    => 'Clínicas de Heridas',
    'clinicas-cateteres'  => 'Clínicas de Catéteres'
];
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Pequeño ajuste para que Select2 combine con el diseño de WordPress */
    .select2-container .select2-selection--single {
        height: 30px;
        border-color: #8c8f94;
        border-radius: 3px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 28px;
    }
</style>

<div class="card" style="max-width: 100%; padding: 20px;">
    <h2>🔗 Mapa de Navegación del Sitio</h2>
    <p class="description">
        Configura aquí hacia dónde debe redirigir cada mapa. <br>
        Puedes buscar y seleccionar <strong>Páginas</strong> o <strong>Categorías</strong>. Cuando un usuario haga clic en un estado, el sistema lo enviará al enlace seleccionado.
    </p>

    <table id="siarhe-enlaces-table" class="siarhe-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 40%;">Entidad / Mapa</th>
                <th style="width: 60%;">Destino (Escribe para buscar)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entidades as $slug => $label) : 
                $selected_val = isset($siarhe_links[$slug]) ? $siarhe_links[$slug] : '';
                
                // Calcular la URL real para el botón de "Ver enlace"
                $link_url = '';
                if ( ! empty($selected_val) ) {
                    if ( strpos((string)$selected_val, 'cat_') === 0 ) {
                        // Es una categoría
                        $cat_id = (int) str_replace('cat_', '', $selected_val);
                        $link_url = get_term_link($cat_id, 'category');
                        if ( is_wp_error($link_url) ) $link_url = '';
                    } else {
                        // Es una página normal
                        $link_url = get_permalink((int)$selected_val);
                    }
                }
            ?>
            <tr>
                <td data-label="Entidad / Mapa" data-mobile-role="primary">
                    <strong><?php echo esc_html($label); ?></strong><br>
                    <code style="color:#999; font-size:10px;">ID: <?php echo esc_html($slug); ?></code>
                </td>
                
                <td data-label="Destino" data-mobile-role="secondary">
                    <div style="display: flex; gap: 10px; align-items: center; width: 100%;">
                        
                        <div style="flex-grow: 1; max-width: 400px;">
                            <select name="siarhe_links_map[<?php echo esc_attr($slug); ?>]" class="siarhe-searchable-select" style="width: 100%;">
                                <option value="">-- Sin enlace (No clicable) --</option>
                                <?php foreach ($options_links as $val => $title) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected( $selected_val, (string)$val ); ?>>
                                        <?php echo esc_html($title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ( ! empty($link_url) ) : ?>
                            <a href="<?php echo esc_url($link_url); ?>" target="_blank" class="button button-small" title="Probar enlace seleccionado">
                                <span class="dashicons dashicons-external" style="line-height: 1.3;"></span>
                            </a>
                        <?php endif; ?>

                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 20px; padding: 10px; background: #f0f0f1; border-left: 4px solid #72aee6;">
        <p style="margin: 0;"><strong>Nota:</strong> No olvides hacer clic en <strong>"Guardar Configuración"</strong> al final de la página para aplicar los cambios.</p>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
jQuery(document).ready(function($) {
    
    // 1. Inicializar Buscador en los Selects
    $('.siarhe-searchable-select').select2({
        placeholder: "-- Escribe para buscar --",
        allowClear: true,
        width: '100%' // Asegura que tome el tamaño de su contenedor
    });

    // 2. Acordeón Móvil
    const table = document.getElementById('siarhe-enlaces-table');
    if(table) {
        table.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('click', function(e) {
                if (window.innerWidth > 767) return;
                // Evitar disparo si se hace clic en el buscador (Select2) o en el botón
                if (e.target.closest('.select2') || e.target.closest('button') || e.target.closest('a')) return;
                this.classList.toggle('is-open');
            });
        });
    }
});
</script>