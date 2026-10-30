<?php // /admin/partials/siarhe-settings.php
if ( ! defined( 'ABSPATH' ) ) exit;

// Una misma plantilla sirve a dos pantallas (la define Siarhe_Admin en $siarhe_screen_page):
//  - Personalizar  (siarhe-customize): aspecto visual
//  - Configuración (siarhe-settings):  datos y comportamiento
// El grupo de opciones debe coincidir con lo registrado en class-siarhe-admin.php
$siarhe_screens = array(
    'siarhe-customize' => array(
        'title' => 'Personalizar SIARHE',
        'tabs'  => array(
            'tema' => array( 'label' => 'Tema Admin',     'icon' => 'dashicons-art',          'group' => 'siarhe_theme_group' ),
            'mapa' => array( 'label' => 'Mapa & Colores', 'icon' => 'dashicons-location-alt', 'group' => 'siarhe_map_group' ),
        ),
    ),
    'siarhe-settings' => array(
        'title' => 'Configuración del Sistema SIARHE',
        'tabs'  => array(
            'enlaces'    => array( 'label' => 'Enlaces y Rutas',   'icon' => 'dashicons-admin-links', 'group' => 'siarhe_links_group' ),
            'tooltip'    => array( 'label' => 'Tooltips',          'icon' => 'dashicons-info',        'group' => 'siarhe_tooltip_group' ),
            'metricas'   => array( 'label' => 'Métricas (CSV)',    'icon' => 'dashicons-chart-bar',   'group' => 'siarhe_metricas_group' ),
            'marcadores' => array( 'label' => 'Marcadores (CSV)',  'icon' => 'dashicons-location',    'group' => 'siarhe_marcadores_group' ),
        ),
    ),
);

$screen_page = ( isset( $siarhe_screen_page ) && isset( $siarhe_screens[ $siarhe_screen_page ] ) ) ? $siarhe_screen_page : 'siarhe-settings';
$screen      = $siarhe_screens[ $screen_page ];

// Pestaña actual: si no pertenece a esta pantalla, se usa la primera
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
if ( ! isset( $screen['tabs'][ $active_tab ] ) ) {
    $active_tab = key( $screen['tabs'] );
}
$option_group = $screen['tabs'][ $active_tab ]['group'];
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html( $screen['title'] ); ?></h1>
    <hr class="wp-header-end">

    <nav class="nav-tab-wrapper">
        <?php foreach ( $screen['tabs'] as $slug => $tab ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $screen_page . '&tab=' . $slug ) ); ?>" class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span> <?php echo esc_html( $tab['label'] ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="tab-content" style="margin-top: 20px;">

        <form method="post" action="options.php">
            <?php
                // Campos de seguridad ocultos necesarios para que WP guarde las opciones
                settings_fields( $option_group );
                do_settings_sections( $option_group );

                // Cargar el archivo parcial correspondiente (los slugs vienen de la lista de arriba)
                $tab_file = SIARHE_PATH . 'admin/partials/settings-tabs/tab-' . $active_tab . '.php';

                if ( file_exists( $tab_file ) ) {
                    include $tab_file;
                    submit_button( 'Guardar Configuración' );
                } else {
                    echo '<div class="notice notice-info inline"><p>El módulo <strong>' . esc_html( ucfirst( $active_tab ) ) . '</strong> está en construcción.</p></div>';
                }
            ?>
        </form>

    </div>
</div>