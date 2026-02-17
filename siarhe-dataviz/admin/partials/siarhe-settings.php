<?php 
if ( ! defined( 'ABSPATH' ) ) exit; 

// 1. Obtener pestaña actual (por defecto 'enlaces')
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'enlaces';

// 2. Definir qué grupo de opciones se guarda en cada pestaña
// Esto debe coincidir con lo que registramos en class-siarhe-admin.php
$option_group = 'siarhe_links_group'; // Default
if ( $active_tab == 'tema' ) $option_group = 'siarhe_theme_group';
if ( $active_tab == 'mapa' ) $option_group = 'siarhe_map_group';
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Configuración del Sistema SIARHE</h1>
    <hr class="wp-header-end">

    <nav class="nav-tab-wrapper">
        <a href="?page=siarhe-settings&tab=tema" class="nav-tab <?php echo $active_tab == 'tema' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-art"></span> Tema Admin
        </a>
        <a href="?page=siarhe-settings&tab=mapa" class="nav-tab <?php echo $active_tab == 'mapa' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-location-alt"></span> Mapa & Colores
        </a>
        <a href="?page=siarhe-settings&tab=enlaces" class="nav-tab <?php echo $active_tab == 'enlaces' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-links"></span> Enlaces y Rutas
        </a>
        <a href="?page=siarhe-settings&tab=tooltip" class="nav-tab <?php echo $active_tab == 'tooltip' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-info"></span> Tooltips
        </a>
    </nav>

    <div class="tab-content" style="margin-top: 20px;">
        
        <form method="post" action="options.php">
            <?php 
                // Campos de seguridad ocultos necesarios para que WP guarde las opciones
                settings_fields( $option_group ); 
                do_settings_sections( $option_group );
                
                // Cargar el archivo parcial correspondiente
                $tab_file = SIARHE_PATH . 'admin/partials/settings-tabs/tab-' . $active_tab . '.php';
                
                if ( file_exists( $tab_file ) ) {
                    include $tab_file;
                } else {
                    echo '<div class="notice notice-info inline"><p>El módulo <strong>' . ucfirst($active_tab) . '</strong> está en construcción.</p></div>';
                }
                
                // Mostrar botón de guardar solo si hay un archivo cargado
                if ( file_exists( $tab_file ) ) {
                    submit_button( 'Guardar Configuración' );
                }
            ?>
        </form>
        
    </div>
</div>