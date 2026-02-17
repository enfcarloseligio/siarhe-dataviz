<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener listado de todas las páginas de WordPress
$pages = get_pages(); 
$options_pages = [];
if ( $pages ) {
    foreach ( $pages as $page ) {
        $options_pages[$page->ID] = $page->post_title;
    }
}

// 2. Obtener configuración guardada de la base de datos
$siarhe_links = get_option( 'siarhe_links_map', [] ); // Array [ 'aguascalientes' => 123, ... ]

// 3. Lista Maestra de Entidades + Clínicas
// Las claves (keys) deben coincidir con los ID de los mapas GeoJSON para que el enlace funcione.
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

<div class="card" style="max-width: 100%; padding: 20px;">
    <h2>🔗 Mapa de Navegación del Sitio</h2>
    <p class="description">
        Configura aquí hacia dónde debe redirigir cada mapa. <br>
        Cuando un usuario haga clic en un estado (ej. "Jalisco") en el mapa interactivo principal, el sistema lo enviará a la página de WordPress que selecciones aquí.
    </p>

    <table class="siarhe-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th style="width: 40%;">Entidad / Mapa</th>
                <th style="width: 60%;">Página de Destino (WordPress)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entidades as $slug => $label) : 
                $selected_page_id = isset($siarhe_links[$slug]) ? $siarhe_links[$slug] : '';
            ?>
            <tr>
                <td data-mobile-role="primary" data-label="Entidad">
                    <strong><?php echo esc_html($label); ?></strong><br>
                    <code style="color:#999; font-size:10px;">ID: <?php echo esc_html($slug); ?></code>
                </td>
                
                <td data-mobile-role="secondary" data-label="Página Destino">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        
                        <select name="siarhe_links_map[<?php echo esc_attr($slug); ?>]" style="width: 100%; max-width: 400px;">
                            <option value="">-- Sin enlace (No clicable) --</option>
                            <?php foreach ($options_pages as $id => $title) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected( $selected_page_id, $id ); ?>>
                                    <?php echo esc_html($title); ?> (ID: <?php echo $id; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ( ! empty($selected_page_id) && get_permalink($selected_page_id) ) : ?>
                            <a href="<?php echo get_permalink($selected_page_id); ?>" target="_blank" class="button button-small" title="Ver página actual">
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