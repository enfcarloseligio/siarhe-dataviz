<?php // /admin/partials/settings-tabs/tab-versiones.php
if ( ! defined( 'ABSPATH' ) ) exit;

// El historial (con sus propios formularios) se dibuja fuera del formulario de opciones
$siarhe_after_form = SIARHE_PATH . 'admin/partials/settings-tabs/partial-versiones-historial.php';

$keep       = Siarhe_Versions::keep();
$zlib       = Siarhe_Versions::zlib_available();
$gz_on      = get_option( Siarhe_Versions::OPT_GZ, '0' ) === '1';
$summary    = Siarhe_Versions::summary();

// Cuantas versiones se eliminarian con cada limite posible (para avisar antes de reducirlo)
$excess = array();
for ( $i = 0; $i <= Siarhe_Versions::MAX_KEEP; $i++ ) $excess[ $i ] = Siarhe_Versions::excess( $i );

Siarhe_Admin::render_flash_notice();
$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
if ( $status === 'restored' ) echo '<div class="notice notice-success is-dismissible"><p>Versión restaurada. El archivo actual quedó reemplazado por la copia elegida.</p></div>';
if ( $status === 'vdeleted' ) echo '<div class="notice notice-warning is-dismissible"><p>Copia eliminada.</p></div>';
?>

<span data-siarhe-scrolltop hidden></span>

<div class="siarhe-card">
    <h2 class="siarhe-card__title">Copias de seguridad<?php Siarhe_Admin::help( 'Cada vez que reemplazas un archivo de datos, el anterior se guarda como copia para poder volver atras. Aqui decides cuantas copias se conservan por archivo y si se comprimen para ahorrar espacio.' ); ?></h2>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="siarhe_versions_keep">Copias por archivo</label><?php Siarhe_Admin::help( 'Numero de versiones anteriores que se conservan de cada archivo (0 a 5). Con 0 no se guarda ninguna copia. Al reducir el numero se eliminan las copias mas antiguas que sobren.' ); ?></th>
            <td>
                <select name="<?php echo esc_attr( Siarhe_Versions::OPT_KEEP ); ?>" id="siarhe_versions_keep">
                    <?php for ( $i = 0; $i <= Siarhe_Versions::MAX_KEEP; $i++ ) : ?>
                        <option value="<?php echo (int) $i; ?>" <?php selected( $keep, $i ); ?>>
                            <?php echo $i === 0 ? '0 (no guardar copias)' : (int) $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <p class="description">Recomendado: 1 (permite volver a la versión anterior). Cada copia ocupa lo mismo que el archivo original, salvo que actives la compresión.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="siarhe_versions_compress">Comprimir las copias</label><?php Siarhe_Admin::help( 'Guarda las copias en formato .gz. Los CSV y GeoJSON suelen ocupar entre 5 y 10 veces menos. Restaurar las descomprime automaticamente. Solo afecta a las copias nuevas.' ); ?></th>
            <td>
                <input type="hidden" name="<?php echo esc_attr( Siarhe_Versions::OPT_GZ ); ?>" value="0">
                <label>
                    <input type="checkbox" name="<?php echo esc_attr( Siarhe_Versions::OPT_GZ ); ?>" id="siarhe_versions_compress" value="1" <?php checked( $gz_on && $zlib ); ?> <?php disabled( ! $zlib ); ?>>
                    Guardar las copias comprimidas (.gz)
                </label>
                <?php if ( $zlib ) : ?>
                    <p class="description">La extensión <strong>zlib</strong> de PHP está disponible en este servidor. Solo afecta a las copias nuevas; las existentes no se modifican.</p>
                <?php else : ?>
                    <p class="description siarhe-field-error">Este servidor no tiene la extensión <strong>zlib</strong> de PHP, así que no se pueden comprimir las copias. Pídele a tu proveedor de hosting que la active.</p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th scope="row">Espacio usado<?php Siarhe_Admin::help( 'Espacio total que ocupan las copias guardadas de todos los archivos.' ); ?></th>
            <td>
                <strong><?php echo esc_html( size_format( $summary['bytes'] ) ?: '0 B' ); ?></strong>
                en <?php echo (int) $summary['count']; ?> copia(s) de <?php echo (int) $summary['files']; ?> archivo(s).
            </td>
        </tr>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('form[action="options.php"]');
    var sel = document.getElementById('siarhe_versions_keep');
    if (!form || !sel || !window.SiarheAdmin) return;
    var stored = <?php echo (int) $keep; ?>;
    var excess = <?php echo wp_json_encode( $excess ); ?>;

    // Al reducir las copias se avisa de lo que se eliminará y el usuario decide
    form.addEventListener('submit', function(e) {
        if (form.dataset.confirmed) return;
        var n = parseInt(sel.value, 10);
        var extra = excess[n] || 0;
        if (n >= stored || extra === 0) return;
        e.preventDefault();
        var msg = 'Vas a pasar de ' + stored + ' a ' + n + ' copia(s) por archivo. Se eliminarán ' + extra +
                  ' copia(s) más antigua(s) que exceden el nuevo límite. Esta acción no se puede deshacer.';
        window.SiarheAdmin.confirm(msg, { title: 'Reducir copias guardadas', okText: 'Reducir y eliminar', danger: true }).then(function(ok) {
            if (!ok) return;
            form.dataset.confirmed = '1';
            HTMLFormElement.prototype.submit.call(form);
        });
    });
});
</script>
