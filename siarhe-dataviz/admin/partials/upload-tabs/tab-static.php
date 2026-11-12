<?php // /admin/partials/upload-tabs/tab-static.php
/**
 * Carga de bases estaticas. Cada base es una combinacion entidad + año + unidad (plazas o recursos humanos estimados) y puede
 * subirse como CSV o como JSON optimizado (el que genera RHEMX): al subir uno, el plugin crea el otro, asi que siempre existen
 * los dos y el mapa lee el JSON.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$entidades_data = siarhe_get_entities();
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$bases = $wpdb->get_results( "SELECT * FROM $table_assets WHERE tipo_archivo = 'static_min' AND es_activo = 1 ORDER BY anio_reporte DESC" );
$upload_base = defined( 'SIARHE_UPLOAD_DIR' ) ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/';

// El selector llega hasta el año en curso: no tiene caso ofrecer años que aun no ocurren
$anio_max = max( Siarhe_Static_Json::LATEST_YEAR, (int) gmdate( 'Y' ) );
$unidades = array( 'plazas' => 'Plazas de enfermería', 'personas' => 'Recursos humanos estimados (personas)' );

$u_msg = array(
    'success' => 'Base estática cargada y procesada correctamente.',
    'updated' => 'Metadatos de la base estática actualizados en el sistema.',
    'deleted' => 'Archivo estático y metadatos eliminados permanentemente.',
);

Siarhe_Admin::render_flash_notice();
if ( isset( $_GET['status'] ) && isset( $u_msg[ $_GET['status'] ] ) ) {
    $cls = $_GET['status'] === 'deleted' ? 'notice-warning' : 'notice-success';
    echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $u_msg[ $_GET['status'] ] ) . '</p></div>';
}

// Filas de la tabla: una por entidad + año + unidad
$rows = array();
$anios = array();
foreach ( $bases as $b ) {
    if ( ! isset( $entidades_data[ $b->entidad_slug ] ) ) continue;
    $d = $entidades_data[ $b->entidad_slug ];
    $csv_ok  = ! empty( $b->ruta_archivo ) && file_exists( $upload_base . ltrim( $b->ruta_archivo, '/' ) );
    $json_ok = ! empty( $b->ruta_json ) && file_exists( $upload_base . ltrim( $b->ruta_json, '/' ) );
    if ( ! $csv_ok && ! $json_ok ) continue;
    $size = ( $csv_ok ? filesize( $upload_base . ltrim( $b->ruta_archivo, '/' ) ) : 0 ) + ( $json_ok ? filesize( $upload_base . ltrim( $b->ruta_json, '/' ) ) : 0 );
    $rows[] = array( 'b' => $b, 'nombre' => $d['nombre'], 'cve' => $d['CVE_ENT'], 'csv_ok' => $csv_ok, 'json_ok' => $json_ok, 'size' => $size,
                     'sort' => ( $b->entidad_slug === 'republica-mexicana' ? -1 : (int) $d['CVE_ENT'] ) );
    $anios[ (int) $b->anio_reporte ] = true;
}
usort( $rows, function ( $a, $b ) {
    return array( $a['sort'], -(int) $a['b']->anio_reporte, $a['b']->unidad ) <=> array( $b['sort'], -(int) $b['b']->anio_reporte, $b['b']->unidad );
} );
krsort( $anios );
?>

<?php Siarhe_Admin::render_select2_css(); ?>

<div class="siarhe-card siarhe-upload-card">
    <h2>📤 Cargar Base Estática<?php Siarhe_Admin::help( 'Sube los datos que se pintan en el mapa y la tabla de una entidad, para un año y una unidad de conteo.' ); ?></h2>

    <div class="notice notice-info inline">
        <p><strong>Cómo funcionan las bases:</strong></p>
        <ul>
            <li><strong>Una base por entidad, año y unidad.</strong> Subir otra para la misma combinación la reemplaza y guarda copia de la anterior (Configuración › Versiones y Copias).</li>
            <li><strong>Formatos admitidos:</strong> <code>.csv</code> (separado por comas) o <code>.json</code> en el formato del plugin (el que genera RHEMX en <code>json/</code> y <code>json_personas/</code>). Al subir uno, el plugin crea el otro; el mapa lee el JSON, que pesa menos.</li>
            <li><strong>Estructura obligatoria:</strong> <code>CVE_ENT</code> o <code>CVE_MUN</code>, el nombre (<code>estado</code> o <code>municipio</code>), una columna de población, <code>enfermeras_total</code> y <code>tasa_total</code>.</li>
            <li><strong>Nomenclatura:</strong> los archivos se guardan como <code>{entidad}-{año}-{unidad}.csv</code> y <code>.json</code>.</li>
        </ul>
    </div>
    <?php Siarhe_Admin::render_columns_downloads( 'static' ); ?>

    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="siarhe_upload_static">
        <?php wp_nonce_field( 'siarhe_upload_static_nonce', 'siarhe_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="entidad_slug">Entidad Federativa / Alcance</label><?php Siarhe_Admin::help( 'Entidad (o nivel nacional, clave 33) a la que pertenece la base.' ); ?></th>
                <td class="siarhe-field-narrow">
                    <select name="entidad_slug" id="entidad_slug" class="siarhe-searchable-select" required>
                        <option value="">-- Selecciona la entidad --</option>
                        <?php foreach ( $entidades_data as $slug => $data ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $data['nombre'] ); ?> (<?php echo esc_html( $data['CVE_ENT'] ); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="anio_reporte">Año de los Datos</label><?php Siarhe_Admin::help( 'Año al que corresponden los datos. El mapa ofrece un selector con los años cargados.' ); ?></th>
                <td>
                    <select name="anio_reporte" id="anio_reporte" required>
                        <?php for ( $y = $anio_max; $y >= Siarhe_Static_Json::YEAR_MIN; $y-- ) : ?>
                            <option value="<?php echo (int) $y; ?>"<?php selected( $y, $anio_max ); ?>><?php echo (int) $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="unidad">Unidad de conteo</label><?php Siarhe_Admin::help( 'Lo que cuentan las columnas enfermeras_*: plazas de enfermería o recursos humanos estimados (una plaza por persona estimada).' ); ?></th>
                <td>
                    <select name="unidad" id="unidad">
                        <?php foreach ( $unidades as $clave => $texto ) : ?>
                            <option value="<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $texto ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="siarhe_file">Archivo de Datos (CSV o JSON)</label><?php Siarhe_Admin::help( 'CSV con una fila por región (estado o municipio) y una columna por métrica, o el JSON equivalente. Los nombres de columna deben coincidir con las claves definidas en Configuración › Métricas.' ); ?></th>
                <td>
                    <input type="file" data-check="static-csv" name="siarhe_file" id="siarhe_file" accept=".csv,.json" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fecha_corte">Fecha de Corte</label><?php Siarhe_Admin::help( 'Fecha hasta la cual el archivo contiene información. Se muestra al usuario como vigencia de los datos.' ); ?></th>
                <td><input type="date" name="fecha_corte" id="fecha_corte" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="referencia">Fuente / Referencia</label><?php Siarhe_Admin::help( 'Origen de la información (institución, documento, versión). Aparece como referencia bibliográfica.' ); ?></th>
                <td><textarea name="referencia" id="referencia" rows="2" class="large-text" placeholder="Ej: Fuente: SIARHE, Secretaría de Salud (2026)."></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="comentarios">Comentarios Internos</label><?php Siarhe_Admin::help( 'Notas para el equipo administrador. No se muestran en el sitio público.' ); ?></th>
                <td><textarea name="comentarios" id="comentarios" rows="2" class="large-text" placeholder="Notas sobre el proceso de limpieza y minificación de datos..."></textarea></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Subir / Reemplazar Archivo">
        </p>
    </form>
</div>

<div class="siarhe-card siarhe-card--flush">
    <h2 class="siarhe-card__title">Bases Estáticas Cargadas<?php Siarhe_Admin::help( 'Una fila por entidad, año y unidad. Desde aquí puedes copiar el enlace, editar los metadatos, descargar o eliminar.' ); ?></h2>

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
                Año
                <select id="siarhe-filter-anio" class="siarhe-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ( array_keys( $anios ) as $y ) : ?><option value="<?php echo (int) $y; ?>"><?php echo (int) $y; ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>
                Unidad
                <select id="siarhe-filter-unidad" class="siarhe-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ( $unidades as $clave => $texto ) : ?><option value="<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $clave === 'plazas' ? 'Plazas' : 'Personas' ); ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="siarhe-pagination siarhe-pagination--bare">
            <div class="siarhe-page-numbers" id="siarhe-pagination-controls-top"></div>
        </div>

        <div class="siarhe-search-box">
            <span class="dashicons dashicons-search"></span>
            <input type="text" id="siarhe-search-static" placeholder="Buscar entidad, archivo o año...">
        </div>
    </div>

    <table id="siarhe-static-table" class="siarhe-table">
        <thead>
            <tr>
                <th style="width: 18%;" data-sort="text">Entidad</th>
                <th style="width: 5%;" data-sort="num">CVE</th>
                <th style="width: 6%;" data-sort="num">Año</th>
                <th style="width: 8%;" data-sort="text">Unidad</th>
                <th style="width: 10%;">Formatos</th>
                <th style="width: 10%;" data-sort="num">Fecha de Corte</th>
                <th style="width: 18%;" data-sort="num">Auditoría</th>
                <th style="width: 8%;" data-sort="num">Tamaño</th>
                <th style="width: 17%;">Acciones</th>
            </tr>
        </thead>
        <tbody id="siarhe-static-tbody">
            <?php foreach ( $rows as $row ) :
                $b = $row['b'];
                $nonce_del = wp_create_nonce( 'siarhe_delete_static_nonce_' . $b->id );
                $fecha_original = $b->fecha_subida ?: $b->fecha_modificacion;
                $url_csv  = $row['csv_ok']  ? SIARHE_UPLOAD_URL . ltrim( $b->ruta_archivo, '/' ) . '?v=' . filemtime( $upload_base . ltrim( $b->ruta_archivo, '/' ) ) : '';
                $url_json = $row['json_ok'] ? SIARHE_UPLOAD_URL . ltrim( $b->ruta_json, '/' ) . '?v=' . filemtime( $upload_base . ltrim( $b->ruta_json, '/' ) ) : '';
            ?>
            <tr class="siarhe-data-row" data-anio="<?php echo (int) $b->anio_reporte; ?>" data-unidad="<?php echo esc_attr( $b->unidad ); ?>">
                <td data-label="Entidad" data-mobile-role="primary"><strong><?php echo esc_html( $row['nombre'] ); ?></strong></td>
                <td data-label="CVE"><span class="siarhe-badge neutral"><?php echo esc_html( $row['cve'] ); ?></span></td>
                <td data-label="Año" data-sort-value="<?php echo (int) $b->anio_reporte; ?>"><?php echo (int) $b->anio_reporte; ?></td>
                <td data-label="Unidad" data-mobile-role="secondary"><?php echo $b->unidad === 'personas' ? 'Personas' : 'Plazas'; ?></td>
                <td data-label="Formatos">
                    <span class="siarhe-badge <?php echo $row['csv_ok'] ? 'ok' : 'neutral'; ?>">CSV</span>
                    <span class="siarhe-badge <?php echo $row['json_ok'] ? 'ok' : 'neutral'; ?>">JSON</span>
                </td>
                <td data-label="Fecha de Corte" data-sort-value="<?php echo $b->fecha_corte ? (int) strtotime( $b->fecha_corte ) : 0; ?>">
                    <?php echo $b->fecha_corte ? esc_html( date_i18n( 'd/m/Y', strtotime( $b->fecha_corte ) ) ) : '—'; ?>
                </td>
                <td data-label="Auditoría" data-sort-value="<?php echo (int) strtotime( $b->fecha_modificacion ?: $fecha_original ); ?>">
                    <div class="siarhe-audit">
                        <span class="siarhe-audit__label">Subido por:</span><br>
                        <span class="siarhe-audit__who"><?php echo esc_html( $b->subido_por ?: 'Sistema' ); ?></span><br>
                        <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr( $fecha_original ); ?>"><?php echo esc_html( $fecha_original ); ?></span>
                    </div>
                    <?php if ( ! empty( $b->fecha_modificacion ) && $b->fecha_modificacion !== $fecha_original ) : ?>
                        <div class="siarhe-audit">
                            <span class="siarhe-audit__label siarhe-audit__label--edit">Última edición:</span><br>
                            <span class="siarhe-audit__who"><?php echo esc_html( $b->modificado_por ?: 'Sistema' ); ?></span><br>
                            <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr( $b->fecha_modificacion ); ?>"><?php echo esc_html( $b->fecha_modificacion ); ?></span>
                        </div>
                    <?php endif; ?>
                </td>
                <td data-label="Tamaño" data-sort-value="<?php echo (int) $row['size']; ?>"><?php echo esc_html( size_format( $row['size'] ) ); ?></td>
                <td data-label="Acciones">
                    <div class="siarhe-row-actions">
                        <?php if ( $url_json ) : ?>
                            <button type="button" class="button button-small" data-copy="<?php echo esc_url( $url_json ); ?>" title="Copiar enlace al JSON"><span class="dashicons dashicons-admin-links"></span></button>
                        <?php endif; ?>
                        <button type="button" class="button button-small edit-meta-btn"
                                data-id="<?php echo absint( $b->id ); ?>"
                                data-nombre="<?php echo esc_attr( $row['nombre'] . ' · ' . $b->anio_reporte . ' · ' . ( $b->unidad === 'personas' ? 'personas' : 'plazas' ) ); ?>"
                                data-anio="<?php echo esc_attr( $b->anio_reporte ); ?>"
                                data-corte="<?php echo esc_attr( $b->fecha_corte ); ?>"
                                data-ref="<?php echo esc_attr( $b->referencia_bibliografica ); ?>"
                                data-notes="<?php echo esc_attr( $b->comentarios ); ?>"
                                title="Ver Información / Editar Metadatos"><span class="dashicons dashicons-edit"></span></button>
                        <?php if ( $url_csv ) : ?>
                            <a href="<?php echo esc_url( $url_csv ); ?>" target="_blank" rel="noopener" class="button button-small" title="Descargar el CSV"><span class="dashicons dashicons-download"></span></a>
                        <?php endif; ?>
                        <?php if ( $url_json ) : ?>
                            <a href="<?php echo esc_url( $url_json ); ?>" target="_blank" rel="noopener" class="button button-small" title="Descargar el JSON"><span class="dashicons dashicons-media-code"></span></a>
                        <?php endif; ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="⚠️ ATENCIÓN: ¿Estás seguro de eliminar esta base (CSV y JSON)? Esta acción borrará el registro y los archivos del servidor de forma irreversible.">
                            <input type="hidden" name="action" value="siarhe_delete_static">
                            <input type="hidden" name="file_id" value="<?php echo absint( $b->id ); ?>">
                            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce_del ); ?>">
                            <button type="submit" class="button button-small button-danger" title="Eliminar la base"><span class="dashicons dashicons-trash"></span></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="siarhe-static-empty" style="<?php echo $rows ? 'display: none;' : ''; ?>">
                <td colspan="9" class="siarhe-empty-cell"><?php echo $rows ? 'No se encontraron resultados para su búsqueda.' : 'Todavía no hay bases estáticas cargadas.'; ?></td>
            </tr>
        </tbody>
    </table>

    <div class="siarhe-pagination">
        <div id="siarhe-static-count" class="siarhe-count"></div>
        <div class="siarhe-page-numbers" id="siarhe-pagination-controls-bottom"></div>
    </div>
</div>

<div id="siarhe-edit-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content">
        <h2 class="siarhe-modal-title">Editar Metadatos: <span id="modal-entidad-name"></span></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="siarhe_update_static_meta">
            <input type="hidden" name="file_id" id="modal-file-id">
            <?php wp_nonce_field( 'siarhe_update_static_meta_nonce', 'siarhe_meta_nonce' ); ?>
            <table class="form-table">
                <tr><th><label>Año de los Datos</label><?php Siarhe_Admin::help( 'El año y la unidad identifican la base: para cambiarlos, súbela de nuevo con el año correcto y elimina esta.' ); ?></th><td><input type="number" id="modal-anio" class="regular-text" readonly></td></tr>
                <tr><th><label>Fecha de Corte</label><?php Siarhe_Admin::help( 'Fecha hasta la cual el archivo contiene información. Se muestra al usuario como vigencia de los datos.' ); ?></th><td><input type="date" name="fecha_corte" id="modal-corte" class="regular-text" required></td></tr>
                <tr><th><label>Fuente / Referencia</label><?php Siarhe_Admin::help( 'Origen de la información (institución, documento, versión). Aparece como referencia bibliográfica.' ); ?></th><td><textarea name="referencia" id="modal-ref" rows="3" class="large-text"></textarea></td></tr>
                <tr><th><label>Comentarios Internos</label><?php Siarhe_Admin::help( 'Notas para el equipo administrador. No se muestran en el sitio público.' ); ?></th><td><textarea name="comentarios" id="modal-notes" rows="3" class="large-text"></textarea></td></tr>
            </table>
            <div class="siarhe-modal-footer">
                <button type="button" class="button button-secondary" id="close-modal-btn">Cancelar</button>
                <button type="submit" class="button button-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<?php Siarhe_Admin::render_select2_js(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined') {
        jQuery('.siarhe-searchable-select').select2({ placeholder: "-- Escribe para buscar entidad --", allowClear: true, width: '100%' });
    }
    if (window.SiarheAdmin && window.SiarheAdmin.formatDate) {
        document.querySelectorAll('.siarhe-date-formatter').forEach(function(el) {
            var rawDate = el.getAttribute('data-date');
            if (rawDate) el.textContent = window.SiarheAdmin.formatDate(rawDate);
        });
    }
    if (window.SiarheAdmin && window.SiarheAdmin.initMobileTables) window.SiarheAdmin.initMobileTables();

    // Modal de edicion de metadatos
    var modal = document.getElementById('siarhe-edit-modal');
    var closeBtn = document.getElementById('close-modal-btn');
    if (modal) {
        document.querySelectorAll('.edit-meta-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('modal-entidad-name').textContent = this.dataset.nombre;
                document.getElementById('modal-file-id').value = this.dataset.id;
                document.getElementById('modal-anio').value = this.dataset.anio;
                document.getElementById('modal-corte').value = this.dataset.corte;
                document.getElementById('modal-ref').value = this.dataset.ref;
                document.getElementById('modal-notes').value = this.dataset.notes;
                modal.style.display = 'block';
            });
        });
        closeBtn.addEventListener('click', function() { modal.style.display = 'none'; });
        window.addEventListener('click', function(e) { if (e.target == modal) { modal.style.display = 'none'; } });
    }

    // Busqueda, filtros por año y unidad, y paginacion (motor compartido)
    if (window.SiarheAdmin && window.SiarheAdmin.initTable) {
        window.SiarheAdmin.initTable({
            rows:    document.querySelectorAll('.siarhe-data-row'),
            search:  document.getElementById('siarhe-search-static'),
            perPage: document.getElementById('siarhe-items-per-page'),
            filters: [
                { el: document.getElementById('siarhe-filter-anio'),   match: function(row, v) { return row.dataset.anio === v; } },
                { el: document.getElementById('siarhe-filter-unidad'), match: function(row, v) { return row.dataset.unidad === v; } }
            ],
            pagers:  [document.getElementById('siarhe-pagination-controls-top'), document.getElementById('siarhe-pagination-controls-bottom')],
            table:   document.getElementById('siarhe-static-table'),
            count:   document.getElementById('siarhe-static-count'),
            empty:   document.getElementById('siarhe-static-empty')
        });
    }
});
</script>
