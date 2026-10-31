<?php // /admin/partials/data-uploader.php
if ( ! defined( 'ABSPATH' ) ) exit; 

// 1. Obtener la pestaña activa
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'geojson';

// 2. Definir las pestañas disponibles
$tabs = [
    'geojson'       => 'Geolocalización',
    'localidades'   => 'Localidades (Geo)',
    'static'        => 'Bases Estáticas',
    'marcadores'    => 'Marcadores',
    'pivote'        => 'Pivotes',
    'formaciones'   => 'Formaciones',
    'escuelas'      => 'Escuelas',
    'investigacion' => 'Investigación',
    'carreras'      => 'Carreras'
];
?>

<div class="wrap" data-siarhe-scrolltop>
    <div class="siarhe-page-header">
        <div>
            <h1>Carga de Datos</h1>
            <p>Sube y mantén los GeoJSON, localidades, bases estáticas y marcadores de cada entidad.</p>
        </div>
    </div>

    <nav class="nav-tab-wrapper">
        <?php foreach ( $tabs as $slug => $name ) : ?>
            <a href="?page=siarhe-uploader&tab=<?php echo esc_attr( $slug ); ?>" 
               class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $name ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="tab-content siarhe-tab-content">
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