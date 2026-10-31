<?php // /admin/partials/settings-tabs/partial-versiones-historial.php
// Historial de versiones: se incluye despues del formulario de opciones porque lleva sus propios formularios.
if ( ! defined( 'ABSPATH' ) ) exit;

$versions = Siarhe_Versions::list_all();
$events   = Siarhe_Versions::events();
$fmt_ts   = function ( $ts ) { return $ts ? wp_date( 'd/m/Y H:i', $ts ) : '—'; };
$fmt_mysql = function ( $s ) { return $s ? wp_date( 'd/m/Y H:i', strtotime( $s ) ) : '—'; };
$labels   = array( 'geojson' => 'Geolocalización', 'localidades' => 'Localidades', 'static-min' => 'Base estática', 'markers' => 'Marcador' );
?>

<div class="siarhe-card siarhe-card--flush">
    <h2 class="siarhe-card__title">Historial de versiones<?php Siarhe_Admin::help( 'Copias guardadas de los archivos de datos. Restaurar reemplaza el archivo actual por la copia elegida (los metadatos como año y fecha de corte no cambian). Antes de restaurar se guarda una copia del archivo actual, si el limite de copias es mayor que 0.' ); ?></h2>

    <?php if ( ! $versions ) : ?>
        <p class="siarhe-empty-cell">Aún no hay copias guardadas. Se crean automáticamente cuando reemplazas un archivo de datos.</p>
    <?php else : ?>
        <table class="siarhe-table" id="siarhe-versions-table">
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th>Tipo</th>
                    <th>Reemplazada el</th>
                    <th>Reemplazada por</th>
                    <th>Tamaño</th>
                    <th>Formato</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $versions as $v ) : ?>
                    <tr>
                        <td data-label="Archivo" data-mobile-role="primary"><code class="siarhe-code-sm"><?php echo esc_html( $v['file'] ); ?></code></td>
                        <td data-label="Tipo" data-mobile-role="secondary"><?php echo esc_html( isset( $labels[ $v['dir'] ] ) ? $labels[ $v['dir'] ] : $v['dir'] ); ?></td>
                        <td data-label="Reemplazada el"><?php echo esc_html( $fmt_ts( $v['ts'] ) ); ?></td>
                        <td data-label="Reemplazada por"><?php echo esc_html( $v['by'] !== '' ? $v['by'] : '—' ); ?></td>
                        <td data-label="Tamaño"><?php echo esc_html( size_format( $v['size'] ) ?: '0 B' ); ?></td>
                        <td data-label="Formato"><?php echo $v['gz'] ? '<span class="siarhe-badge neutral">Comprimida</span>' : 'Original'; ?></td>
                        <td data-label="Acciones">
                            <div class="siarhe-row-actions">
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                      data-confirm="<?php echo esc_attr( '¿Restaurar la versión del ' . $fmt_ts( $v['ts'] ) . ' de ' . $v['file'] . '? El archivo actual será reemplazado' . ( Siarhe_Versions::keep() > 0 ? ' (se guardará una copia de él).' : ' y NO se guardará copia porque el límite de copias es 0.' ) ); ?>"
                                      data-confirm-ok="Restaurar">
                                    <input type="hidden" name="action" value="siarhe_version_restore">
                                    <input type="hidden" name="version" value="<?php echo esc_attr( $v['rel'] ); ?>">
                                    <?php wp_nonce_field( 'siarhe_version_' . $v['rel'] ); ?>
                                    <button type="submit" class="button button-small" data-help="Restaurar esta versión: reemplaza el archivo actual por esta copia."><span class="dashicons dashicons-backup"></span></button>
                                </form>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                      data-confirm="<?php echo esc_attr( '¿Eliminar esta copia de ' . $v['file'] . '? No se puede deshacer.' ); ?>">
                                    <input type="hidden" name="action" value="siarhe_version_delete">
                                    <input type="hidden" name="version" value="<?php echo esc_attr( $v['rel'] ); ?>">
                                    <?php wp_nonce_field( 'siarhe_version_' . $v['rel'] ); ?>
                                    <button type="submit" class="button button-small button-danger" data-help="Eliminar solo esta copia. El archivo actual no cambia."><span class="dashicons dashicons-trash"></span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ( $events ) : ?>
<div class="siarhe-card">
    <h2 class="siarhe-card__title">Actividad reciente<?php Siarhe_Admin::help( 'Ultimas acciones manuales sobre las copias: quien restauro o elimino una version y cuando.' ); ?></h2>
    <ul class="siarhe-audit-list">
        <?php foreach ( $events as $ev ) : ?>
            <li>
                <strong><?php echo esc_html( $ev['by'] ); ?></strong>
                <?php echo esc_html( $ev['action'] ); ?>
                <code class="siarhe-code-sm"><?php echo esc_html( $ev['file'] ); ?></code>
                · <?php echo esc_html( $fmt_mysql( $ev['at'] ) ); ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.SiarheAdmin && window.SiarheAdmin.initMobileTables) window.SiarheAdmin.initMobileTables();
});
</script>
