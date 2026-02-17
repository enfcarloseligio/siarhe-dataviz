<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener listado de páginas de WordPress
$pages = get_pages(); 
$options_pages = [];
foreach ( $pages as $page ) {
    $options_pages[$page->ID] = $page->post_title;
}

// 2. Obtener configuración guardada
$siarhe_links = get_option( 'siarhe_links_map', [] ); // Array [ 'aguascalientes' => 123, ... ]

// 3. Lista de Entidades (Reutilizamos la lista maestra o definimos una aquí)
$entidades = [
    'republica-mexicana' => 'República Mexicana (Home)',
    'aguascalientes' => 'Aguascalientes',
    'baja-california' => 'Baja California',
    // ... añadir resto ...
    'zacatecas' => 'Zacatecas'
];
?>

<div class="card" style="max-width: 100%; padding: 20px;">
    <h2>Mapa de Navegación</h2>
    <p>Asigna qué página de WordPress corresponde a cada mapa. Cuando un usuario haga clic en un estado en el mapa interactivo, será redirigido a la página seleccionada aquí.</p>

    <form method="post" action="options.php">
        <?php settings_fields( 'siarhe_links_group' ); ?>
        <?php do_settings_sections( 'siarhe_links_group' ); ?>

        <table class="siarhe-table">
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
                        <strong><?php echo $label; ?></strong><br>
                        <code style="color:#888; font-size:10px;"><?php echo $slug; ?></code>
                    </td>
                    
                    <td data-mobile-role="secondary" data-label="Página Destino">
                        <select name="siarhe_links_map[<?php echo $slug; ?>]" style="width: 100%; max-width: 400px;">
                            <option value="">-- Selecciona una página --</option>
                            <?php foreach ($options_pages as $id => $title) : ?>
                                <option value="<?php echo $id; ?>" <?php selected( $selected_page_id, $id ); ?>>
                                    <?php echo $title; ?> (ID: <?php echo $id; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if($selected_page_id): ?>
                            <a href="<?php echo get_permalink($selected_page_id); ?>" target="_blank" class="button button-small" style="margin-left:5px;">Ver</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button('Guardar Configuración de Enlaces'); ?>
    </form>
</div>