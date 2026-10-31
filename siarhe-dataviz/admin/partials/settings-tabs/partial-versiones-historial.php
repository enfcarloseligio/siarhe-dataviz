<?php // /admin/partials/settings-tabs/partial-versiones-historial.php
// Historial de versiones: se incluye despues del formulario de opciones porque lleva sus propios formularios.
if ( ! defined( 'ABSPATH' ) ) exit;

$versions = Siarhe_Versions::list_all();
$events   = Siarhe_Versions::events();
$fmt_ts   = function ( $ts ) { return $ts ? wp_date( 'd/m/Y H:i', $ts ) : '—'; };
$fmt_mysql = function ( $s ) { return $s ? wp_date( 'd/m/Y H:i', strtotime( $s ) ) : '—'; };
// Filtro por tipo de carga: los tipos base siempre; el resto solo si ya tienen copias
$type_counts = array();
foreach ( $versions as $v ) $type_counts[ $v['dir'] ] = isset( $type_counts[ $v['dir'] ] ) ? $type_counts[ $v['dir'] ] + 1 : 1;
$filter_types = array();
foreach ( Siarhe_Versions::dirs() as $d ) {
    if ( in_array( $d, Siarhe_Versions::CORE_TYPES, true ) || ! empty( $type_counts[ $d ] ) ) $filter_types[] = $d;
}
?>

<div class="siarhe-card siarhe-card--flush">
    <h2 class="siarhe-card__title">Historial de versiones<?php Siarhe_Admin::help( 'Copias guardadas de los archivos de datos. Restaurar reemplaza el archivo actual por la copia elegida (los metadatos como año y fecha de corte no cambian). Antes de restaurar se guarda una copia del archivo actual, si el limite de copias es mayor que 0.' ); ?></h2>

    <?php if ( ! $versions ) : ?>
        <p class="siarhe-empty-cell">Aún no hay copias guardadas. Se crean automáticamente cuando reemplazas un archivo de datos.</p>
    <?php else : ?>
        <div class="siarhe-toolbar">
            <div class="siarhe-table-controls">
                <label>
                    Mostrar
                    <select id="siarhe-items-per-page" class="siarhe-select-sm">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">Todos</option>
                    </select>
                    registros
                </label>
                <label>
                    <select id="siarhe-versions-type" class="siarhe-select-sm" aria-label="Filtrar por tipo de carga">
                        <option value="">Todas las cargas (<?php echo (int) count( $versions ); ?>)</option>
                        <?php foreach ( $filter_types as $d ) : ?>
                            <option value="<?php echo esc_attr( $d ); ?>"><?php echo esc_html( Siarhe_Versions::type_label( $d ) . ' (' . ( isset( $type_counts[ $d ] ) ? (int) $type_counts[ $d ] : 0 ) . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="siarhe-pagination siarhe-pagination--bare">
                <div class="siarhe-page-numbers" id="siarhe-pagination-controls-top"></div>
            </div>

            <div class="siarhe-search-box">
                <span class="dashicons dashicons-search"></span>
                <input type="text" id="siarhe-search-versions" placeholder="Buscar archivo, usuario o fecha...">
            </div>
        </div>

        <table class="siarhe-table" id="siarhe-versions-table">
            <thead>
                <tr>
                    <th data-sort="text">Archivo</th>
                    <th data-sort="text">Tipo</th>
                    <th data-sort="num">Reemplazada el</th>
                    <th data-sort="text">Reemplazada por</th>
                    <th data-sort="num">Tamaño</th>
                    <th data-sort="text">Formato</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $versions as $v ) : ?>
                    <tr class="siarhe-data-row" data-type="<?php echo esc_attr( $v['dir'] ); ?>">
                        <td data-label="Archivo" data-mobile-role="primary"><code class="siarhe-code-sm"><?php echo esc_html( $v['file'] ); ?></code></td>
                        <td data-label="Tipo" data-mobile-role="secondary"><?php echo esc_html( Siarhe_Versions::type_label( $v['dir'] ) ); ?></td>
                        <td data-label="Reemplazada el" data-sort-value="<?php echo (int) $v['ts']; ?>"><?php echo esc_html( $fmt_ts( $v['ts'] ) ); ?></td>
                        <td data-label="Reemplazada por"><?php echo esc_html( $v['by'] !== '' ? $v['by'] : '—' ); ?></td>
                        <td data-label="Tamaño" data-sort-value="<?php echo (int) $v['size']; ?>"><?php echo esc_html( size_format( $v['size'] ) ?: '0 B' ); ?></td>
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
                <tr id="siarhe-versions-empty" style="display: none;">
                    <td colspan="7" class="siarhe-empty-cell">No se encontraron copias con ese filtro o búsqueda.</td>
                </tr>
            </tbody>
        </table>

        <div class="siarhe-pagination">
            <div id="siarhe-versions-count" class="siarhe-count"></div>
            <div class="siarhe-page-numbers" id="siarhe-pagination-controls-bottom"></div>
        </div>
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
    if (!window.SiarheAdmin) return;
    if (window.SiarheAdmin.initMobileTables) window.SiarheAdmin.initMobileTables();
    var typeSel = document.getElementById('siarhe-versions-type');
    if (window.SiarheAdmin.initTable && typeSel) {
        window.SiarheAdmin.initTable({
            rows:    document.querySelectorAll('#siarhe-versions-table .siarhe-data-row'),
            search:  document.getElementById('siarhe-search-versions'),
            perPage: document.getElementById('siarhe-items-per-page'),
            filters: [{ el: typeSel, match: function(row, val) { return row.getAttribute('data-type') === val; } }],
            pagers:  [document.getElementById('siarhe-pagination-controls-top'), document.getElementById('siarhe-pagination-controls-bottom')],
            table:   document.getElementById('siarhe-versions-table'),
            count:   document.getElementById('siarhe-versions-count'),
            empty:   document.getElementById('siarhe-versions-empty')
        });
    }
});
</script>
