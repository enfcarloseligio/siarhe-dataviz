<?php 
if ( ! defined( 'ABSPATH' ) ) exit; 
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'enlaces';
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Configuración SIARHE</h1>
    <hr class="wp-header-end">

    <nav class="nav-tab-wrapper">
        <a href="?page=siarhe-settings&tab=tema" class="nav-tab <?php echo $active_tab == 'tema' ? 'nav-tab-active' : ''; ?>">🎨 Tema (Admin)</a>
        <a href="?page=siarhe-settings&tab=mapa" class="nav-tab <?php echo $active_tab == 'mapa' ? 'nav-tab-active' : ''; ?>">🗺️ Mapa (Frontend)</a>
        <a href="?page=siarhe-settings&tab=enlaces" class="nav-tab <?php echo $active_tab == 'enlaces' ? 'nav-tab-active' : ''; ?>">🔗 Enlaces y Rutas</a>
    </nav>

    <div class="tab-content" style="margin-top: 20px;">
        <?php
        $tab_file = SIARHE_PATH . 'admin/partials/settings-tabs/tab-' . $active_tab . '.php';
        if ( file_exists( $tab_file ) ) {
            include $tab_file;
        } else {
            echo '<div class="notice notice-warning inline"><p>Módulo en construcción.</p></div>';
        }
        ?>
    </div>
</div>