<?php // /admin/partials/data-manager.php
if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap">
    <div class="siarhe-page-header">
        <div>
            <h1>Gestor de Bases</h1>
            <p>Aquí vivirá el mapa dinámico respaldado por SQL: importación de CSV/XLSX por lotes con progreso, validación y bitácora.</p>
        </div>
    </div>
    <div class="siarhe-card siarhe-coming">
        <span class="dashicons dashicons-database-import"></span>
        <h2>Próximamente</h2>
        <p>Mientras tanto, la versión estática (GeoJSON + CSV) se administra desde <a href="<?php echo esc_url( admin_url( 'admin.php?page=siarhe-uploader' ) ); ?>">Carga de Datos</a>.</p>
    </div>
</div>
