<?php 
if ( ! defined( 'ABSPATH' ) ) exit; 

// 1. Obtener la pestaña activa
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'geojson';

// 2. Definir las pestañas disponibles
$tabs = [
    'geojson'       => 'Geolocalización',
    'static'        => 'Bases Estáticas',
    'marcadores'    => 'Marcadores',
    'pivote'        => 'Pivotes',
    'formaciones'   => 'Formaciones',
    'escuelas'      => 'Escuelas',
    'investigacion' => 'Investigación',
    'servicio'      => 'Servicio Social'
];
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Gestión de Datos SIARHE</h1>
    <hr class="wp-header-end">

    <nav class="nav-tab-wrapper">
        <?php foreach ( $tabs as $slug => $name ) : ?>
            <a href="?page=siarhe-uploader&tab=<?php echo $slug; ?>" 
               class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo $name; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="tab-content" style="margin-top: 20px;">
        <?php
        $tab_file = SIARHE_PATH . 'admin/partials/upload-tabs/tab-' . $active_tab . '.php';
        if ( file_exists( $tab_file ) ) {
            include $tab_file;
        } else {
            echo '<div class="notice notice-error inline"><p>Error: No se encuentra el módulo <strong>' . esc_html($active_tab) . '</strong>.</p></div>';
        }
        ?>
    </div>
</div>