<?php // /admin/partials/upload-tabs/tab-marcadores.php
/**
 * Carga de marcadores. Cada tipo de marcador (establecimientos de salud, clínicas de catéteres, clínicas de heridas, pasantes
 * de enfermería y los que agregue el administrador) es una familia de archivos, uno por periodo: un año de datos o «Todas»
 * (las unidades de toda la historia), según lo que admita el tipo. Se sube el CSV o la capa en JSON que exporta RHEMX; el
 * plugin crea el otro formato y una versión por entidad, y el mapa lee el JSON.
 *
 *   1. Datos de la pantalla
 *   2. Formulario de carga
 *   3. Tabla de archivos
 *   4. Alta de variables
 *   5. Edición de metadatos
 *   6. Comportamiento
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. DATOS DE LA PANTALLA
// =========================================================================
$tipos_marcadores = siarhe_marker_types();

global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$existing_files = $wpdb->get_results(
    $wpdb->prepare( "SELECT * FROM $table_assets WHERE tipo_archivo = %s AND es_activo = 1 ORDER BY anio_reporte DESC, id DESC", 'marcador' )
);

// Un archivo por tipo y periodo (anio_reporte 0 = «Todas», toda la historia)
$files_by_type = array();
$anios_cargados = array();
foreach ( $existing_files as $file ) {
    $periodo_file = (int) $file->anio_reporte;
    if ( isset( $files_by_type[ $file->entidad_slug ][ $periodo_file ] ) ) continue;
    $files_by_type[ $file->entidad_slug ][ $periodo_file ] = $file;
    if ( $periodo_file > 0 && isset( $tipos_marcadores[ $file->entidad_slug ] ) ) $anios_cargados[ $periodo_file ] = true;
}
krsort( $anios_cargados );
$periodo_anio_max = max( 2026, (int) gmdate( 'Y' ) );

// La tabla abre en el último año con al menos un archivo; sin archivos por año, en el año en curso
$anios_tabla  = $anios_cargados ? array_keys( $anios_cargados ) : array( (int) gmdate( 'Y' ) );
$anio_inicial = (int) $anios_tabla[0];

$upload_base_dir = ( defined( 'SIARHE_UPLOAD_DIR' ) ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/' ) . 'markers/';

// Filas de la tabla: por cada tipo, primero «Todas» (si el tipo la admite) y después los años, del más reciente al más
// antiguo. Los periodos sin archivo conservan su fila para ver lo que falta.
$rows = array();
foreach ( $tipos_marcadores as $key => $info ) {
    $del_tipo = isset( $files_by_type[ $key ] ) ? $files_by_type[ $key ] : array();
    $heredado = file_exists( $upload_base_dir . $info['filename'] ); // archivo copiado a la carpeta sin registro (por FTP)
    if ( siarhe_marker_type_has_total( $info ) || isset( $del_tipo[0] ) || ( $heredado && ! $del_tipo ) ) {
        $rows[] = array( 'key' => $key, 'info' => $info, 'periodo' => 0, 'file' => isset( $del_tipo[0] ) ? $del_tipo[0] : null );
    }
    foreach ( $anios_tabla as $anio_fila ) {
        $rows[] = array( 'key' => $key, 'info' => $info, 'periodo' => (int) $anio_fila, 'file' => isset( $del_tipo[ $anio_fila ] ) ? $del_tipo[ $anio_fila ] : null );
    }
}

$m_msg = array(
    'success'     => array( 'notice-success', 'Marcador procesado y actualizado correctamente.' ),
    'updated'     => array( 'notice-success', 'Metadatos sincronizados exitosamente.' ),
    'deleted'     => array( 'notice-warning', 'Archivos (CSV y JSON) y registro eliminados.' ),
    'var_added'   => array( 'notice-success', 'Variable registrada. Ya puedes elegirla en el formulario y cargar sus archivos.' ),
    'var_deleted' => array( 'notice-warning', 'Variable y archivos asociados eliminados permanentemente.' ),
    'var_reset'   => array( 'notice-warning', 'Se restauraron las variables iniciales; los archivos personalizados fueron eliminados.' ),
    'var_exists'  => array( 'notice-error', 'Ya existe una variable con esa clave.' ),
    'var_invalid' => array( 'notice-error', 'Datos de variable no válidos.' ),
);

Siarhe_Admin::render_flash_notice();
Siarhe_Admin::render_validation_result();
if ( isset( $_GET['status'] ) && isset( $m_msg[ $_GET['status'] ] ) ) {
    $aviso = $m_msg[ sanitize_key( $_GET['status'] ) ];
    echo '<div class="notice ' . esc_attr( $aviso[0] ) . ' is-dismissible"><p>' . esc_html( $aviso[1] ) . '</p></div>';
}
?>

<?php Siarhe_Admin::render_select2_css(); ?>

<?php
// =========================================================================
// 2. FORMULARIO DE CARGA
// =========================================================================
?>
<div class="siarhe-card siarhe-upload-card">
    <h2>📍 Gestión de Marcadores (Clínicas y Espectros)<?php Siarhe_Admin::help( 'Carga los archivos de puntos que se dibujan sobre el mapa (posición, espectro o spike), por periodo.' ); ?></h2>

    <div class="notice notice-info inline">
        <p><strong>Cómo funcionan los archivos:</strong></p>
        <ul>
            <li><strong>Un archivo por tipo de marcador y periodo.</strong> Los <em>establecimientos de salud</em> y los <em>pasantes de enfermería</em> se cargan por año. Las <em>clínicas</em> (y las variables que agregues con serie completa) admiten además <strong>Todas</strong>: las unidades de toda la historia, que han existido o reportado al menos una vez. El mapa usa el archivo del año que el visitante está viendo y, si no existe, el de «Todas». Subir otro para la misma combinación lo reemplaza.</li>
            <li><strong>Formatos admitidos:</strong> <code>.csv</code> (separado por comas) o <code>.json</code> con la capa de puntos que exporta RHEMX (<code>unidades_clues_&lt;año&gt;.json</code>, <code>clinicas_cateteres_&lt;año&gt;.json</code>, <code>clinicas_cateteres_historico.json</code>, <code>clinicas_heridas_historico.json</code>; están en <code>data/processed/marcadores/</code>). Al subir uno, el plugin crea el otro; el mapa lee el JSON, que pesa menos.</li>
            <li><strong>Un solo archivo para todo el país.</strong> No hace falta separarlo por entidad: al cargarlo, el plugin crea una versión por entidad y el mapa de cada estado descarga solo sus puntos.</li>
            <li><strong>Estructura del CSV:</strong> <code>LATITUD</code>, <code>LONGITUD</code> y la clave de la entidad (<code>CVE_ENT</code> o <code>CLAVE DE LA ENTIDAD</code>) son obligatorias; se aceptan los encabezados del catálogo CLUES (<code>clues_&lt;año&gt;.csv</code> de RHEMX).</li>
            <li><strong>Estructura del JSON:</strong> <code>fila</code> (el campo de cada posición: <code>latitud</code>, <code>longitud</code>, <code>clues</code>, <code>nombre</code>, <code>institucion</code>, <code>nivel</code>…), las listas de textos (<code>instituciones</code>, <code>niveles</code>, <code>municipios</code>…) y <code>unidades</code> (una lista por punto).</li>
            <li><strong>Nomenclatura:</strong> los archivos se guardan como <code>{nombre del tipo}-{periodo}.csv</code> y <code>.json</code> (ej. <code>clinicas-cateteres-2025.json</code>, <code>clinicas-heridas-total.json</code>).</li>
        </ul>
    </div>
    <?php Siarhe_Admin::render_columns_downloads( 'marker' ); ?>

    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="form-upload-marker">
        <input type="hidden" name="action" value="siarhe_upload_marker">
        <?php wp_nonce_field( 'siarhe_upload_marker_nonce', 'marker_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="marker_type">Tipo de Marcador</label><?php Siarhe_Admin::help( 'Variable de marcador a la que corresponde el archivo. Las variables se crean con «Incorporar Nueva Variable», al final de la pantalla.' ); ?></th>
                <td class="siarhe-field-narrow">
                    <select name="marker_type" id="marker_type" class="siarhe-searchable-select" required>
                        <option value="">-- Selecciona el tipo --</option>
                        <?php foreach ( $tipos_marcadores as $key => $info ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" data-total="<?php echo siarhe_marker_type_has_total( $info ) ? '1' : '0'; ?>"><?php echo esc_html( $info['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="periodo">Periodo de los Datos</label><?php Siarhe_Admin::help( 'Año al que corresponden los puntos o, en los tipos que lo admiten, «Todas»: la capa con las unidades de toda la historia (por ejemplo, las clínicas que han reportado al menos una vez).' ); ?></th>
                <td>
                    <select name="periodo" id="periodo" required>
                        <option value="total">Todas (toda la historia)</option>
                        <?php for ( $y = $periodo_anio_max; $y >= 2005; $y-- ) : ?>
                            <option value="<?php echo (int) $y; ?>"<?php selected( $y, $periodo_anio_max ); ?>><?php echo (int) $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="marker_file">Archivo de Puntos (CSV o JSON)</label><?php Siarhe_Admin::help( 'CSV con los puntos del marcador, o la capa en JSON que exporta RHEMX. Se valida antes de guardarse y se crea el otro formato.' ); ?></th>
                <td>
                    <input type="file" data-check="marker-csv" name="marker_file" id="marker_file" accept=".csv,.json" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fecha_corte">Fecha de Corte</label><?php Siarhe_Admin::help( 'Fecha hasta la cual el archivo contiene información. Se muestra al usuario como vigencia de los datos.' ); ?></th>
                <td>
                    <input type="date" name="fecha_corte" id="fecha_corte" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="referencia">Fuente / Referencia</label><?php Siarhe_Admin::help( 'Origen de la información (institución, documento, versión). Aparece como referencia bibliográfica.' ); ?></th>
                <td>
                    <textarea name="referencia" id="referencia" rows="2" class="large-text" placeholder="Ej: Catálogo de Establecimientos, Secretaría de Salud."></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="comentarios">Comentarios Internos</label><?php Siarhe_Admin::help( 'Notas para el equipo administrador. No se muestran en el sitio público.' ); ?></th>
                <td>
                    <textarea name="comentarios" id="comentarios" rows="2" class="large-text" placeholder="Notas sobre la limpieza de coordenadas..."></textarea>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Subir y Reemplazar">
        </p>
    </form>
</div>

<?php
// =========================================================================
// 3. TABLA DE ARCHIVOS
// =========================================================================
?>
<div class="siarhe-card siarhe-card--flush">
    <h2 class="siarhe-card__title">Archivos en el Servidor<?php Siarhe_Admin::help( 'Por cada tipo de marcador: «Todas» (si el tipo la admite) y los años, del más reciente al más antiguo. Al elegir un año se ven los archivos de ese año y los de «Todas», que sirven a cualquier año.' ); ?></h2>

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
                    <?php foreach ( $anios_tabla as $y ) : ?><option value="<?php echo (int) $y; ?>"<?php selected( (int) $y, $anio_inicial ); ?>><?php echo (int) $y; ?></option><?php endforeach; ?>
                    <option value="">Todos</option>
                </select>
            </label>
            <label>
                Tema
                <select id="siarhe-filter-tipo" class="siarhe-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ( $tipos_marcadores as $key => $info ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $info['label'] ); ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php Siarhe_Admin::render_validate_button( 'marcadores' ); ?>
        </div>

        <div class="siarhe-pagination siarhe-pagination--bare">
            <div class="siarhe-page-numbers" id="siarhe-pagination-controls-top"></div>
        </div>

        <div class="siarhe-search-box">
            <span class="dashicons dashicons-search"></span>
            <input type="text" id="siarhe-search-marcadores-file" placeholder="Buscar marcador, estado o archivo...">
        </div>
    </div>

    <table id="siarhe-marcadores-table" class="siarhe-table">
        <thead>
            <tr>
                <th style="width: 18%" data-sort="text">Marcador</th>
                <th style="width: 9%" data-sort="num">Periodo</th>
                <th style="width: 12%" data-sort="text">Estado</th>
                <th style="width: 16%" data-sort="text">Archivo Sistema</th>
                <th style="width: 10%" data-sort="num">Fecha de Corte</th>
                <th style="width: 15%" data-sort="num">Auditoría</th>
                <th style="width: 5%" data-sort="num">Tamaño</th>
                <th style="width: 15%">Acciones</th>
            </tr>
        </thead>
        <tbody id="siarhe-marcadores-tbody">
            <?php
            $variable_con_boton = array(); // el botón de eliminar la variable va una sola vez, en su primera fila
            foreach ( $rows as $row ) :
                $key = $row['key']; $info = $row['info']; $db_file = $row['file']; $periodo_fila = (int) $row['periodo'];
                // Con registro, el archivo es el suyo; sin registro, en «Todas», el que pudo copiarse a la carpeta con el nombre del tipo
                $filename = $db_file ? basename( $db_file->ruta_archivo ) : ( $periodo_fila === 0 ? $info['filename'] : '' );
                $ruta_fisica = $filename !== '' ? $upload_base_dir . $filename : '';
                $existe_fisico = ( $ruta_fisica !== '' && file_exists( $ruta_fisica ) );
                $json_name = ( $db_file && ! empty( $db_file->ruta_json ) && file_exists( $upload_base_dir . basename( $db_file->ruta_json ) ) ) ? basename( $db_file->ruta_json ) : '';
                $con_entidades = ( $json_name !== '' && is_file( Siarhe_Marker_Json::ruta_entidad( $upload_base_dir . $json_name, '01' ) ) );
                $periodo_txt = $periodo_fila > 0 ? (string) $periodo_fila : 'Todas';
                $url_publica = defined( 'SIARHE_UPLOAD_URL' ) ? SIARHE_UPLOAD_URL . 'markers/' . $filename : '';
            ?>
            <tr class="siarhe-data-row" data-periodo="<?php echo $periodo_fila > 0 ? (int) $periodo_fila : 'total'; ?>" data-tipo="<?php echo esc_attr( $key ); ?>">
                <td data-label="Marcador" data-mobile-role="primary">
                    <strong><?php echo esc_html( $info['label'] ); ?></strong>
                    <?php if ( ! empty( $info['is_core'] ) ) echo '<br><small class="siarhe-text-faint">(Nativo)</small>'; ?>
                </td>

                <td data-label="Periodo" data-sort-value="<?php echo $periodo_fila > 0 ? (int) $periodo_fila : 9999; ?>">
                    <?php echo esc_html( $periodo_txt ); ?>
                </td>

                <td data-label="Estado" data-mobile-role="secondary">
                    <?php if ( $existe_fisico ) : ?>
                        <span class="siarhe-status siarhe-status--ok"><span class="dashicons dashicons-database"></span>Archivo Cargado</span>
                        <?php if ( $con_entidades ) : ?><br><small class="siarhe-text-faint">Con versión por entidad</small><?php endif; ?>
                    <?php else : ?>
                        <span class="siarhe-status siarhe-status--off"><span class="dashicons dashicons-minus"></span>Sin archivo</span>
                    <?php endif; ?>
                </td>

                <td data-label="Archivo Sistema">
                    <?php if ( $existe_fisico ) : ?>
                        <code class="siarhe-code-sm"><?php echo esc_html( $filename ); ?></code>
                        <?php if ( $json_name ) : ?><br><code class="siarhe-code-sm"><?php echo esc_html( $json_name ); ?></code><?php endif; ?>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <td data-label="Fecha de Corte" data-sort-value="<?php echo ( $db_file && $db_file->fecha_corte ) ? (int) strtotime( $db_file->fecha_corte ) : 0; ?>">
                    <?php if ( $db_file && $db_file->fecha_corte ) : ?>
                        <?php echo esc_html( date_i18n( 'd/M/Y', strtotime( $db_file->fecha_corte ) ) ); ?>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <td data-label="Auditoría" data-sort-value="<?php $ts_aud = 0; if ( $db_file ) { $f_aud = ! empty( $db_file->fecha_modificacion ) ? $db_file->fecha_modificacion : ( $db_file->fecha_subida ?? '' ); $ts_aud = (int) strtotime( $f_aud ); } elseif ( $existe_fisico ) { $ts_aud = (int) filemtime( $ruta_fisica ); } echo (int) $ts_aud; ?>">
                    <?php if ( $db_file ) :
                        $autor_original = $db_file->subido_por ?? 'Sistema';
                        $fecha_original = $db_file->fecha_subida ?? $db_file->fecha_modificacion;
                    ?>
                        <div class="siarhe-audit">
                            <span class="siarhe-audit__label">Subido por:</span><br>
                            <span class="siarhe-audit__who"><?php echo esc_html( $autor_original ); ?></span><br>
                            <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr( $fecha_original ); ?>"><?php echo esc_html( $fecha_original ); ?></span>
                        </div>

                        <?php if ( ! empty( $db_file->fecha_modificacion ) && $db_file->fecha_modificacion !== $fecha_original ) : ?>
                            <div class="siarhe-audit">
                                <span class="siarhe-audit__label siarhe-audit__label--edit">Última edición:</span><br>
                                <span class="siarhe-audit__who"><?php echo esc_html( $db_file->modificado_por ?: 'Sistema' ); ?></span><br>
                                <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr( $db_file->fecha_modificacion ); ?>"><?php echo esc_html( $db_file->fecha_modificacion ); ?></span>
                            </div>
                        <?php endif; ?>

                    <?php elseif ( $existe_fisico ) : ?>
                        <div class="siarhe-audit">
                            <span class="siarhe-audit__label">Subido por:</span><br>
                            <span class="siarhe-audit__who">Sistema (Vía FTP/Cpanel)</span><br>
                            <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr( date( 'Y-m-d H:i:s', filemtime( $ruta_fisica ) ) ); ?>"><?php echo esc_html( date( 'Y-m-d H:i:s', filemtime( $ruta_fisica ) ) ); ?></span>
                        </div>
                    <?php else : ?>
                        <span class="siarhe-text-faint">—</span>
                    <?php endif; ?>
                </td>

                <td data-label="Tamaño" data-sort-value="<?php echo $existe_fisico ? (int) filesize( $ruta_fisica ) : 0; ?>">
                    <?php echo $existe_fisico ? esc_html( size_format( filesize( $ruta_fisica ) ) ) : '—'; ?>
                </td>

                <td data-label="Acciones">
                    <div class="siarhe-row-actions">
                    <?php if ( $existe_fisico ) :
                        // La fecha del archivo va en el enlace para que el navegador no use una copia anterior
                        $url_con_version = $url_publica . '?v=' . filemtime( $ruta_fisica );
                    ?>
                        <button type="button" class="button button-small" data-copy="<?php echo esc_url( $url_con_version ); ?>" title="Copiar Enlace">
                            <span class="dashicons dashicons-admin-links"></span>
                        </button>

                        <?php if ( $db_file ) : ?>
                        <button type="button" class="button button-small edit-meta-btn"
                                data-id="<?php echo absint( $db_file->id ); ?>"
                                data-nombre="<?php echo esc_attr( $info['label'] . ' · ' . $periodo_txt ); ?>"
                                data-anio="<?php echo esc_attr( $periodo_txt ); ?>"
                                data-corte="<?php echo esc_attr( $db_file->fecha_corte ); ?>"
                                data-ref="<?php echo esc_attr( $db_file->referencia_bibliografica ); ?>"
                                data-notes="<?php echo esc_attr( $db_file->comentarios ); ?>"
                                title="Ver Info / Editar">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( $url_con_version ); ?>" target="_blank" rel="noopener" class="button button-small" title="Descargar el CSV">
                            <span class="dashicons dashicons-download"></span>
                        </a>
                        <?php if ( $json_name ) : ?>
                        <a href="<?php echo esc_url( SIARHE_UPLOAD_URL . 'markers/' . $json_name . '?v=' . filemtime( $upload_base_dir . $json_name ) ); ?>" target="_blank" rel="noopener" class="button button-small" title="Descargar el JSON">
                            <span class="dashicons dashicons-media-code"></span>
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ( $db_file ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="¿Eliminar los archivos (CSV y JSON) de este periodo? La variable se conserva y podrás volver a cargar un archivo.">
                            <input type="hidden" name="action" value="siarhe_delete_static">
                            <input type="hidden" name="file_id" value="<?php echo absint( $db_file->id ); ?>">
                            <?php wp_nonce_field( 'siarhe_delete_static_nonce_' . $db_file->id ); ?>
                            <button type="submit" class="button button-small button-danger" data-help="Eliminar los archivos de este periodo. La variable se conserva."><span class="dashicons dashicons-trash"></span></button>
                        </form>
                    <?php elseif ( ! $existe_fisico && ! ( empty( $info['is_core'] ) && ! isset( $variable_con_boton[ $key ] ) ) ) : ?>—<?php endif; ?>

                    <?php if ( empty( $info['is_core'] ) && ! isset( $variable_con_boton[ $key ] ) ) : $variable_con_boton[ $key ] = true; ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="¿Eliminar esta variable de forma permanente? Sus archivos de todos los periodos y sus registros en la base de datos también serán borrados.">
                            <input type="hidden" name="action" value="siarhe_marker_var_delete">
                            <?php wp_nonce_field( 'siarhe_delete_variable_nonce', 'del_nonce' ); ?>
                            <input type="hidden" name="del_file_key" value="<?php echo esc_attr( $key ); ?>">
                            <button type="submit" class="button button-small button-danger" data-help="Eliminar la variable completa: su configuración y sus archivos de todos los periodos."><span class="dashicons dashicons-dismiss"></span></button>
                        </form>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="siarhe-marcadores-empty" style="display: none;">
                <td colspan="8" class="siarhe-empty-cell">No se encontraron resultados para su búsqueda.</td>
            </tr>
        </tbody>
    </table>

    <div class="siarhe-pagination">
        <div id="siarhe-marcadores-count" class="siarhe-count"></div>
        <div class="siarhe-page-numbers" id="siarhe-pagination-controls-bottom"></div>
    </div>
</div>

<?php
// =========================================================================
// 4. ALTA DE VARIABLES
// =========================================================================
?>
<div class="siarhe-card siarhe-upload-card">
    <div class="siarhe-card-head">
        <h3 class="siarhe-m-0">➕ Incorporar Nueva Variable<?php Siarhe_Admin::help( 'Registra una nueva variable de marcador antes de subir sus archivos. Tú decides si tiene serie completa («Todas») o si solo va por años.' ); ?></h3>

        <form id="form-reset-variables" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="PRECAUCIÓN: se borrarán permanentemente TODAS las variables personalizadas y sus archivos del servidor. ¿Deseas continuar?" data-confirm-ok="Restaurar">
            <input type="hidden" name="action" value="siarhe_marker_var_reset">
            <?php wp_nonce_field( 'reset_variables_nonce', 'reset_nonce' ); ?>
            <button type="submit" class="siarhe-btn-danger-outline siarhe-btn-icon button button-link-delete">
                <span class="dashicons dashicons-undo"></span> Restaurar Iniciales
            </button>
        </form>
    </div>
    <p class="description">Agrega una nueva ranura de archivos para cargar marcadores adicionales (ej. Casos Epidemiológicos, Unidades Móviles). Al crearla eliges si tiene serie completa («Todas» y años) o si solo se carga por año.</p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="siarhe_marker_var_add">
        <?php wp_nonce_field( 'siarhe_add_variable_nonce', 'add_nonce' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="new_file_key">Clave Interna (Sin espacios):</label><?php Siarhe_Admin::help( 'Identificador único de la variable. Solo letras, números y guion bajo. No se puede cambiar después sin recrear la variable.' ); ?></th>
                <td><input type="text" name="new_file_key" id="new_file_key" placeholder="Ej: CASOS_DENGUE" required class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="new_file_label">Etiqueta Pública:</label><?php Siarhe_Admin::help( 'Nombre que verán los visitantes en selectores y leyendas.' ); ?></th>
                <td><input type="text" name="new_file_label" id="new_file_label" placeholder="Ej: Casos de Dengue" required class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="new_file_serie">¿Tiene serie completa?</label><?php Siarhe_Admin::help( 'Sí: además de un archivo por año admite «Todas», la capa con las unidades de toda la historia (como las clínicas). No: solo se carga por año (como los establecimientos de salud).' ); ?></th>
                <td>
                    <select name="new_file_serie" id="new_file_serie">
                        <option value="1">Sí: «Todas» y un archivo por año</option>
                        <option value="0">No: solo un archivo por año</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="new_file_name">Nombre del Archivo (con .csv):</label><?php Siarhe_Admin::help( 'Nombre base con que se guardarán los archivos en la carpeta de marcadores, con la extensión .csv; a cada archivo se le agrega su periodo.' ); ?></th>
                <td><input type="text" name="new_file_name" id="new_file_name" placeholder="ej: casos-dengue.csv" required class="regular-text"></td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-secondary">Agregar Variable</button>
        </p>
    </form>
</div>

<?php
// =========================================================================
// 5. EDICIÓN DE METADATOS
// =========================================================================
?>
<div id="siarhe-edit-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content">
        <h2 class="siarhe-modal-title">
            Editar Metadatos: <span id="modal-entidad-name"></span>
        </h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="form-update-meta">
            <input type="hidden" name="action" value="siarhe_update_static_meta">
            <input type="hidden" name="file_id" id="modal-file-id">
            <?php wp_nonce_field( 'siarhe_update_static_meta_nonce', 'siarhe_meta_nonce' ); ?>

            <table class="form-table">
                <tr><th><label>Periodo de los Datos</label><?php Siarhe_Admin::help( 'El periodo identifica al archivo: para cambiarlo, súbelo de nuevo en el periodo correcto y elimina este.' ); ?></th><td><input type="text" id="modal-anio" class="regular-text" readonly></td></tr>
                <tr><th><label>Fecha de Corte</label><?php Siarhe_Admin::help( 'Fecha hasta la cual el archivo contiene información. Se muestra al usuario como vigencia de los datos.' ); ?></th><td><input type="date" name="fecha_corte" id="modal-corte" class="regular-text" required></td></tr>
                <tr><th><label>Fuente / Referencia</label><?php Siarhe_Admin::help( 'Origen de la información (institución, documento, versión). Aparece como referencia bibliográfica.' ); ?></th><td><textarea name="referencia" id="modal-ref" rows="3" class="large-text"></textarea></td></tr>
                <tr><th><label>Comentarios Internos</label><?php Siarhe_Admin::help( 'Notas para el equipo administrador. No se muestran en el sitio público.' ); ?></th><td><textarea name="comentarios" id="modal-notes" rows="3" class="large-text"></textarea></td></tr>
            </table>
            <div class="siarhe-modal-footer">
                <button type="button" class="button button-secondary" id="close-modal-btn">Cancelar</button>
                <button type="submit" class="button button-primary" id="btn-modal-submit">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<?php
// =========================================================================
// 6. COMPORTAMIENTO
// =========================================================================
?>
<?php Siarhe_Admin::render_select2_js(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // Buscador dentro del selector de tipo de marcador
    if (typeof jQuery !== 'undefined') {
        jQuery('.siarhe-searchable-select').select2({
            placeholder: "-- Escribe para buscar el tipo --",
            allowClear: true,
            width: '100%'
        });
    }

    // Los demás selectores del formulario y de la barra de la tabla también se pueden escribir
    if (window.SiarheAdmin && window.SiarheAdmin.initSelects) window.SiarheAdmin.initSelects();

    // Periodos que admite el tipo elegido: los tipos por año no ofrecen «Todas»; en los demás es la opción inicial
    var selTipo = document.getElementById('marker_type');
    var selPeriodo = document.getElementById('periodo');
    var opcionTotal = selPeriodo ? selPeriodo.querySelector('option[value="total"]') : null;
    function aplicaPeriodos() {
        var op = selTipo.options[selTipo.selectedIndex];
        var admiteTotal = !op || op.getAttribute('data-total') !== '0';
        var total = selPeriodo.querySelector('option[value="total"]');
        if (admiteTotal && !total) selPeriodo.insertBefore(opcionTotal, selPeriodo.firstChild);
        if (!admiteTotal && total) selPeriodo.removeChild(total);
        if (selTipo.value) selPeriodo.value = admiteTotal ? 'total' : String(<?php echo (int) $periodo_anio_max; ?>);
        if (window.SiarheAdmin && window.SiarheAdmin.refreshSelect) window.SiarheAdmin.refreshSelect(selPeriodo);
    }
    if (selTipo && selPeriodo) {
        selTipo.addEventListener('change', aplicaPeriodos);
        if (window.jQuery) jQuery(selTipo).on('change', aplicaPeriodos); // Select2 dispara eventos de jQuery
        aplicaPeriodos();
    }

    // Fechas en formato local
    if (window.SiarheAdmin && window.SiarheAdmin.formatDate) {
        document.querySelectorAll('.siarhe-date-formatter').forEach(function(el) {
            var rawDate = el.getAttribute('data-date');
            if (rawDate) el.textContent = window.SiarheAdmin.formatDate(rawDate);
        });
    }

    // Tablas en pantallas estrechas
    if (window.SiarheAdmin && window.SiarheAdmin.initMobileTables) {
        window.SiarheAdmin.initMobileTables();
    }

    // Modal de edición de metadatos
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

    // Búsqueda, filtros por año y tema, y paginación (motor compartido). Los archivos de «Todas» sirven a cualquier año.
    if (window.SiarheAdmin && window.SiarheAdmin.initTable) {
        window.SiarheAdmin.initTable({
            rows:    document.querySelectorAll('.siarhe-data-row'),
            search:  document.getElementById('siarhe-search-marcadores-file'),
            perPage: document.getElementById('siarhe-items-per-page'),
            filters: [
                { el: document.getElementById('siarhe-filter-anio'), match: function(row, v) { return row.dataset.periodo === v || row.dataset.periodo === 'total'; } },
                { el: document.getElementById('siarhe-filter-tipo'), match: function(row, v) { return row.dataset.tipo === v; } }
            ],
            pagers:  [document.getElementById('siarhe-pagination-controls-top'), document.getElementById('siarhe-pagination-controls-bottom')],
            table:   document.getElementById('siarhe-marcadores-table'),
            count:   document.getElementById('siarhe-marcadores-count'),
            empty:   document.getElementById('siarhe-marcadores-empty')
        });
    }
});
</script>
