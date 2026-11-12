<?php // /admin/partials/upload-tabs/tab-marcadores.php
/**
 * Carga de marcadores. Cada tipo de marcador (clínicas de catéteres, de heridas, establecimientos de salud...) es una familia
 * de archivos, uno por periodo: un año de datos o «total» (todas las unidades de la historia). Se sube el CSV o la capa en
 * JSON que exporta RHEMX; el plugin crea el otro formato y el mapa lee el JSON.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Gestor dinámico del diccionario de archivos
$archivos_json = get_option( 'siarhe_archivos_marcadores', '' );
$tipos_marcadores = siarhe_decode_json_option( $archivos_json );

// Auto-corrector de seguridad por corrupción de codificación ("u00ed")
if ( empty($tipos_marcadores) || !is_array($tipos_marcadores) || (isset($tipos_marcadores['CATETER']['label']) && strpos($tipos_marcadores['CATETER']['label'], 'u00ed') !== false) ) {
    $tipos_marcadores = [
        'CATETER' => [ 'label' => 'Clínicas de Catéteres', 'filename' => 'clinicas-cateteres.csv', 'is_core' => true ],
        'HERIDAS' => [ 'label' => 'Clínicas de Heridas', 'filename' => 'clinicas-heridas.csv', 'is_core' => true ],
        'ESTABLECIMIENTOS' => [ 'label' => 'Establecimientos de Salud (Todas)', 'filename' => 'establecimientos-salud.csv', 'is_core' => true ]
    ];
    update_option( 'siarhe_archivos_marcadores', wp_json_encode($tipos_marcadores, JSON_UNESCAPED_UNICODE) );
}

// 2. Consulta de metadatos existentes en base de datos
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$existing_files = $wpdb->get_results( 
    $wpdb->prepare( "SELECT * FROM $table_assets WHERE tipo_archivo = %s AND es_activo = 1 ORDER BY anio_reporte DESC, id DESC", 'marcador' )
);

// Un archivo por tipo y periodo (anio_reporte 0 = toda la historia)
$files_by_type = [];
foreach ($existing_files as $file) {
    $periodo_file = (int) $file->anio_reporte;
    if ( ! isset( $files_by_type[$file->entidad_slug][$periodo_file] ) ) $files_by_type[$file->entidad_slug][$periodo_file] = $file;
}
$periodo_anio_max = max( 2026, (int) gmdate( 'Y' ) );

// Directorio base de validación física
$upload_base_dir = (defined('SIARHE_UPLOAD_DIR') ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/') . 'markers/';

// Manejo de notificaciones de estado
Siarhe_Admin::render_flash_notice();
if ( isset($_GET['status']) ) {
    if ( $_GET['status'] == 'success' ) echo '<div class="notice notice-success is-dismissible"><p>Marcador procesado y actualizado correctamente.</p></div>';
    if ( $_GET['status'] == 'updated' ) echo '<div class="notice notice-success is-dismissible"><p>Metadatos sincronizados exitosamente.</p></div>';
    if ( $_GET['status'] == 'deleted' ) echo '<div class="notice notice-warning is-dismissible"><p>Archivos (CSV y JSON) y registro eliminados.</p></div>';
    if ( $_GET['status'] == 'var_added' ) echo '<div class="notice notice-success is-dismissible"><p>Variable registrada. Ya puedes cargar su archivo CSV.</p></div>';
    if ( $_GET['status'] == 'var_deleted' ) echo '<div class="notice notice-warning is-dismissible"><p>Variable y archivo CSV asociado eliminados permanentemente.</p></div>';
    if ( $_GET['status'] == 'var_reset' ) echo '<div class="notice notice-warning is-dismissible"><p>Se restauraron las variables iniciales; los archivos personalizados fueron eliminados.</p></div>';
    if ( $_GET['status'] == 'var_exists' ) echo '<div class="notice notice-error is-dismissible"><p>Ya existe una variable con esa clave.</p></div>';
    if ( $_GET['status'] == 'var_invalid' ) echo '<div class="notice notice-error is-dismissible"><p>Datos de variable no válidos.</p></div>';
}
?>

<?php Siarhe_Admin::render_select2_css(); ?>


<div class="siarhe-card siarhe-upload-card">
    <h2>📍 Gestión de Marcadores (Clínicas y Espectros)<?php Siarhe_Admin::help('Carga los archivos de puntos que se dibujan sobre el mapa (posición, espectro o spike), por periodo.'); ?></h2>

    <div class="notice notice-info inline">
        <p><strong>Cómo funcionan los archivos:</strong></p>
        <ul>
            <li><strong>Un archivo por tipo de marcador y periodo.</strong> El periodo es un año de datos o <strong>Toda la historia</strong> (todas las unidades que han existido o reportado al menos una vez). El mapa usa el archivo del año que el visitante está viendo y, si no existe, el de toda la historia. Subir otro para la misma combinación lo reemplaza.</li>
            <li><strong>Formatos admitidos:</strong> <code>.csv</code> (separado por comas) o <code>.json</code> con la capa de puntos que exporta RHEMX (<code>unidades_clues_&lt;año&gt;.json</code>, <code>clinicas_cateteres_&lt;año&gt;.json</code>, <code>clinicas_cateteres_historico.json</code>, <code>clinicas_heridas_historico.json</code>). Al subir uno, el plugin crea el otro; el mapa lee el JSON, que pesa menos.</li>
            <li><strong>Estructura del CSV:</strong> <code>LATITUD</code>, <code>LONGITUD</code> y la clave de la entidad (<code>CVE_ENT</code> o <code>CLAVE DE LA ENTIDAD</code>) son obligatorias; se aceptan los encabezados del catálogo CLUES (<code>clues_&lt;año&gt;.csv</code> de RHEMX).</li>
            <li><strong>Estructura del JSON:</strong> <code>fila</code> (el campo de cada posición: <code>latitud</code>, <code>longitud</code>, <code>clues</code>, <code>nombre</code>, <code>institucion</code>, <code>nivel</code>…), las listas de textos (<code>instituciones</code>, <code>niveles</code>, <code>municipios</code>…) y <code>unidades</code> (una lista por punto).</li>
            <li><strong>Nomenclatura:</strong> los archivos se guardan como <code>{nombre del tipo}-{periodo}.csv</code> y <code>.json</code> (ej. <code>clinicas-cateteres-2025.json</code>, <code>clinicas-heridas-total.json</code>).</li>
        </ul>
    </div>
    <?php Siarhe_Admin::render_columns_downloads( 'marker' ); ?>

    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>" id="form-upload-marker">
        <input type="hidden" name="action" value="siarhe_upload_marker">
        <?php wp_nonce_field( 'siarhe_upload_marker_nonce', 'marker_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="marker_type">Tipo de Marcador</label><?php Siarhe_Admin::help('Variable de marcador a la que corresponde este CSV. Las variables se crean en Configuracion > Marcadores o con Incorporar Nueva Variable.'); ?></th>
                <td class="siarhe-field-narrow">
                    <select name="marker_type" id="marker_type" class="siarhe-searchable-select" required>
                        <option value="">-- Selecciona el tipo --</option>
                        <?php foreach ($tipos_marcadores as $key => $info) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="periodo">Periodo de los Datos</label><?php Siarhe_Admin::help('Año al que corresponden los puntos, o «Toda la historia» para la capa con todas las unidades (por ejemplo, las clínicas que han reportado al menos una vez).'); ?></th>
                <td>
                    <select name="periodo" id="periodo" required>
                        <?php for ( $y = $periodo_anio_max; $y >= 2005; $y-- ) : ?>
                            <option value="<?php echo (int) $y; ?>"<?php selected( $y, $periodo_anio_max ); ?>><?php echo (int) $y; ?></option>
                        <?php endfor; ?>
                        <option value="total">Toda la historia (total)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="marker_file">Archivo de Puntos (CSV o JSON)</label><?php Siarhe_Admin::help('CSV con los puntos del marcador, o la capa en JSON que exporta RHEMX. Se valida antes de guardarse y se crea el otro formato.'); ?></th>
                <td>
                    <input type="file" data-check="marker-csv" name="marker_file" id="marker_file" accept=".csv,.json" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fecha_corte">Fecha de Corte</label><?php Siarhe_Admin::help('Fecha hasta la cual el archivo contiene informacion. Se muestra al usuario como vigencia de los datos.'); ?></th>
                <td>
                    <input type="date" name="fecha_corte" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="referencia">Fuente / Referencia</label><?php Siarhe_Admin::help('Origen de la informacion (institucion, documento, version). Aparece como referencia bibliografica.'); ?></th>
                <td>
                    <textarea name="referencia" id="referencia" rows="2" class="large-text" placeholder="Ej: Catálogo de Establecimientos, Secretaría de Salud."></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="comentarios">Comentarios Internos</label><?php Siarhe_Admin::help('Notas para el equipo administrador. No se muestran en el sitio publico.'); ?></th>
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

<div class="siarhe-card siarhe-upload-card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
        <h3 class="siarhe-m-0">➕ Incorporar Nueva Variable (CSV)<?php Siarhe_Admin::help('Registra una nueva variable de marcador antes de subir su CSV.'); ?></h3>
        
        <form id="form-reset-variables" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="PRECAUCIÓN: se borrarán permanentemente TODAS las variables personalizadas y sus archivos CSV del servidor. ¿Deseas continuar?" data-confirm-ok="Restaurar">
            <input type="hidden" name="action" value="siarhe_marker_var_reset">
            <?php wp_nonce_field('reset_variables_nonce', 'reset_nonce'); ?>
            <button type="submit" class="siarhe-btn-danger-outline siarhe-btn-icon button button-link-delete">
                <span class="dashicons dashicons-undo"></span> Restaurar Iniciales
            </button>
        </form>

    </div>
    <p class="description">Agrega una nueva ranura de archivo para cargar marcadores adicionales (ej. Casos Epidemiológicos, Unidades Móviles).</p>
    
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="siarhe_marker_var_add">
        <?php wp_nonce_field( 'siarhe_add_variable_nonce', 'add_nonce' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label>Clave Interna (Sin espacios):</label><?php Siarhe_Admin::help('Identificador unico de la variable. Solo letras, numeros y guion bajo. No se puede cambiar despues sin recrear la variable.'); ?></th>
                <td><input type="text" name="new_file_key" placeholder="Ej: CASOS_DENGUE" required class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label>Etiqueta Pública:</label><?php Siarhe_Admin::help('Nombre que veran los visitantes en selectores y leyendas.'); ?></th>
                <td><input type="text" name="new_file_label" placeholder="Ej: Casos de Dengue 2026" required class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label>Nombre del Archivo (con .csv):</label><?php Siarhe_Admin::help('Nombre del archivo CSV que se guardara en la carpeta de marcadores, incluyendo la extension .csv.'); ?></th>
                <td><input type="text" name="new_file_name" placeholder="ej: casos-dengue.csv" required class="regular-text"></td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-secondary">Agregar Variable</button>
        </p>
    </form>
</div>

<div class="siarhe-card siarhe-card--flush">
    <h2 class="siarhe-card__title">Archivos en el Servidor<?php Siarhe_Admin::help('CSV de marcadores guardados actualmente en el servidor.'); ?></h2>
    
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
            // Una fila por tipo y periodo cargado; los tipos sin archivo conservan una fila para ver lo que falta
            foreach ($tipos_marcadores as $key => $info) :
              $archivos_tipo = isset($files_by_type[$key]) ? $files_by_type[$key] : [ -1 => null ];
              foreach ($archivos_tipo as $periodo_fila => $db_file) :
                // Con registro, el archivo es el suyo; sin registro, el que pudo copiarse a la carpeta con el nombre del tipo
                $filename = $db_file ? basename( $db_file->ruta_archivo ) : $info['filename'];
                $ruta_fisica = $upload_base_dir . $filename;
                $existe_fisico = file_exists($ruta_fisica);
                $json_name = ( $db_file && ! empty( $db_file->ruta_json ) && file_exists( $upload_base_dir . basename( $db_file->ruta_json ) ) ) ? basename( $db_file->ruta_json ) : '';
                $periodo_txt = $db_file ? ( $periodo_fila > 0 ? (string) $periodo_fila : 'Toda la historia' ) : '—';

                $url_publica = defined('SIARHE_UPLOAD_URL') ? SIARHE_UPLOAD_URL . 'markers/' . $filename : '';
            ?>
            <tr class="siarhe-data-row">
                <td data-label="Marcador" data-mobile-role="primary">
                    <strong><?php echo esc_html($info['label']); ?></strong>
                    <?php if(!empty($info['is_core'])) echo '<br><small class="siarhe-text-faint">(Nativo)</small>'; ?>
                </td>

                <td data-label="Periodo" data-sort-value="<?php echo $db_file ? ( $periodo_fila > 0 ? (int) $periodo_fila : 9999 ) : 0; ?>">
                    <?php echo esc_html( $periodo_txt ); ?>
                </td>
                
                <td data-label="Estado" data-mobile-role="secondary">
                    <?php if ($existe_fisico) : ?>
                        <span class="dashicons dashicons-database" style="color: #46b450;"></span> <strong style="color:#46b450">Archivo Cargado</strong>
                    <?php else : ?>
                        <span class="dashicons dashicons-minus" style="color: #ccc;"></span> <span style="color:#777;">Sin archivo</span>
                    <?php endif; ?>
                </td>
                
                <td data-label="Archivo Sistema">
                    <?php if ($existe_fisico) : ?>
                        <code class="siarhe-code-sm"><?php echo esc_html($filename); ?></code>
                        <?php if ( $json_name ) : ?><br><code class="siarhe-code-sm"><?php echo esc_html( $json_name ); ?></code><?php endif; ?>
                    <?php else : ?>—<?php endif; ?>
                </td>
                
                <td data-label="Fecha de Corte" data-sort-value="<?php echo ( $db_file && $db_file->fecha_corte ) ? (int) strtotime( $db_file->fecha_corte ) : 0; ?>">
                    <?php if ($db_file && $db_file->fecha_corte) : ?>
                        <?php echo date_i18n('d/M/Y', strtotime($db_file->fecha_corte)); ?>
                    <?php else : ?>—<?php endif; ?>
                </td>
                
                <td data-label="Auditoría" data-sort-value="<?php $ts_aud = 0; if ( $db_file ) { $f_aud = ! empty( $db_file->fecha_modificacion ) ? $db_file->fecha_modificacion : ( $db_file->fecha_subida ?? ( $db_file->fecha_creacion ?? ( $db_file->fecha_registro ?? '' ) ) ); $ts_aud = (int) strtotime( $f_aud ); } elseif ( $existe_fisico ) { $ts_aud = (int) filemtime( $ruta_fisica ); } echo $ts_aud; ?>">
                    <?php if ($db_file) : 
                        $autor_original = $db_file->subido_por ?? ($db_file->creado_por ?? ($db_file->registrado_por ?? 'Sistema'));
                        $fecha_original = $db_file->fecha_subida ?? ($db_file->fecha_creacion ?? ($db_file->fecha_registro ?? $db_file->fecha_modificacion));
                    ?>
                        <div class="siarhe-audit">
                            <span class="siarhe-audit__label">Subido por:</span><br>
                            <span class="siarhe-audit__who">
                                <?php echo esc_html($autor_original); ?>
                            </span><br>
                            <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr($fecha_original); ?>">
                                <?php echo esc_html($fecha_original); ?>
                            </span>
                        </div>

                        <?php if (!empty($db_file->fecha_modificacion) && $db_file->fecha_modificacion !== $fecha_original) : ?>
                            <div class="siarhe-audit">
                                <span class="siarhe-audit__label siarhe-audit__label--edit">Última edición:</span><br>
                                <span class="siarhe-audit__who">
                                    <?php echo esc_html($db_file->modificado_por ?: 'Sistema'); ?>
                                </span><br>
                                <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr($db_file->fecha_modificacion); ?>">
                                    <?php echo esc_html($db_file->fecha_modificacion); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($existe_fisico) : ?>
                        <div class="siarhe-audit">
                            <span class="siarhe-audit__label">Subido por:</span><br>
                            <span class="siarhe-audit__who">Sistema (Vía FTP/Cpanel)</span><br>
                            <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr(date("Y-m-d H:i:s", filemtime($ruta_fisica))); ?>">
                                <?php echo esc_html(date("Y-m-d H:i:s", filemtime($ruta_fisica))); ?>
                            </span>
                        </div>
                    <?php else : ?>
                        <span class="siarhe-text-faint">—</span>
                    <?php endif; ?>
                </td>
                
                <td data-label="Tamaño" data-sort-value="<?php echo $existe_fisico ? (int) filesize( $ruta_fisica ) : 0; ?>">
                    <?php if ($existe_fisico) : ?>
                        <?php echo size_format(filesize($ruta_fisica)); ?>
                    <?php else : ?>—<?php endif; ?>
                </td>
                
                <td data-label="Acciones">
                    <div class="siarhe-row-actions">
                    <?php if ($existe_fisico) : 
                        // 🌟 CACHE BUSTING APLICADO
                        $cache_version = filemtime($ruta_fisica);
                        $url_con_version = esc_url($url_publica) . '?v=' . $cache_version;
                    ?>
                        <button type="button" class="button button-small" 
                                data-copy="<?php echo esc_url( $url_con_version ); ?>" title="Copiar Enlace">
                            <span class="dashicons dashicons-admin-links"></span>
                        </button>

                        <?php if ($db_file) : ?>
                        <button type="button" class="button button-small edit-meta-btn" 
                                data-id="<?php echo absint( $db_file->id ); ?>" 
                                data-nombre="<?php echo esc_attr( $info['label'] . ' · ' . $periodo_txt ); ?>"
                                data-anio="<?php echo esc_attr( $periodo_txt ); ?>"  
                                data-corte="<?php echo esc_attr($db_file->fecha_corte); ?>" 
                                data-ref="<?php echo esc_attr($db_file->referencia_bibliografica); ?>" 
                                data-notes="<?php echo esc_attr($db_file->comentarios); ?>"
                                title="Ver Info / Editar">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( $url_con_version ); ?>" target="_blank" class="button button-small" title="Descargar el CSV">
                            <span class="dashicons dashicons-download"></span>
                        </a>
                        <?php if ( $json_name ) : ?>
                        <a href="<?php echo esc_url( SIARHE_UPLOAD_URL . 'markers/' . $json_name . '?v=' . filemtime( $upload_base_dir . $json_name ) ); ?>" target="_blank" rel="noopener" class="button button-small" title="Descargar el JSON">
                            <span class="dashicons dashicons-media-code"></span>
                        </a>
                        <?php endif; ?>

                        <?php if ( $db_file ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="¿Eliminar los archivos (CSV y JSON) de este periodo? La variable se conserva y podrás volver a cargar un archivo.">
                            <input type="hidden" name="action" value="siarhe_delete_static">
                            <input type="hidden" name="file_id" value="<?php echo absint( $db_file->id ); ?>">
                            <?php wp_nonce_field( 'siarhe_delete_static_nonce_' . $db_file->id ); ?>
                            <button type="submit" class="button button-small button-danger" data-help="Eliminar los archivos de este periodo. La variable se conserva."><span class="dashicons dashicons-trash"></span></button>
                        </form>
                        <?php endif; ?>

                    <?php else : ?>—<?php endif; ?>
                    
                    <?php if (empty($info['is_core'])): ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="¿Eliminar esta variable de forma permanente? Sus archivos de todos los periodos y sus registros en la base de datos también serán borrados.">
                            <input type="hidden" name="action" value="siarhe_marker_var_delete">
                            <?php wp_nonce_field( 'siarhe_delete_variable_nonce', 'del_nonce' ); ?>
                            <input type="hidden" name="del_file_key" value="<?php echo esc_attr($key); ?>">
                            <button type="submit" class="button button-small button-danger" data-help="Eliminar la variable completa: su configuración y su archivo CSV."><span class="dashicons dashicons-dismiss"></span></button>
                        </form>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; endforeach; ?>
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

<div id="siarhe-edit-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content">
        <h2 class="siarhe-modal-title">
            Editar Metadatos: <span id="modal-entidad-name"></span>
        </h2>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="form-update-meta">
            <input type="hidden" name="action" value="siarhe_update_static_meta">
            <input type="hidden" name="file_id" id="modal-file-id">
            <?php wp_nonce_field( 'siarhe_update_static_meta_nonce', 'siarhe_meta_nonce' ); ?>

            <table class="form-table">
                <tr><th><label>Periodo de los Datos</label><?php Siarhe_Admin::help('El periodo identifica al archivo: para cambiarlo, súbelo de nuevo en el periodo correcto y elimina este.'); ?></th><td><input type="text" id="modal-anio" class="regular-text" readonly></td></tr>
                <tr><th><label>Fecha de Corte</label><?php Siarhe_Admin::help('Fecha hasta la cual el archivo contiene informacion. Se muestra al usuario como vigencia de los datos.'); ?></th><td><input type="date" name="fecha_corte" id="modal-corte" class="regular-text" required></td></tr>
                <tr><th><label>Fuente / Referencia</label><?php Siarhe_Admin::help('Origen de la informacion (institucion, documento, version). Aparece como referencia bibliografica.'); ?></th><td><textarea name="referencia" id="modal-ref" rows="3" class="large-text"></textarea></td></tr>
                <tr><th><label>Comentarios Internos</label><?php Siarhe_Admin::help('Notas para el equipo administrador. No se muestran en el sitio publico.'); ?></th><td><textarea name="comentarios" id="modal-notes" rows="3" class="large-text"></textarea></td></tr>
            </table>
            <div class="siarhe-modal-footer">
                <button type="button" class="button button-secondary" id="close-modal-btn">Cancelar</button>
                <button type="submit" class="button button-primary" id="btn-modal-submit">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<?php Siarhe_Admin::render_select2_js(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Inicialización del buscador dentro del select de Entidad
    if (typeof jQuery !== 'undefined') {
        jQuery('.siarhe-searchable-select').select2({
            placeholder: "-- Escribe para buscar entidad --",
            allowClear: true,
            width: '100%' 
        });
    }

    // Formateo de fechas vía JS global
    if (window.SiarheAdmin && window.SiarheAdmin.formatDate) {
        document.querySelectorAll('.siarhe-date-formatter').forEach(el => {
            const rawDate = el.getAttribute('data-date');
            if(rawDate) el.textContent = window.SiarheAdmin.formatDate(rawDate);
        });
    }

    // Inicialización de comportamiento responsivo para tablas
    if (window.SiarheAdmin && window.SiarheAdmin.initMobileTables) {
        window.SiarheAdmin.initMobileTables();
    }

    // Configuración Modal
    const modal = document.getElementById('siarhe-edit-modal');
    const closeBtn = document.getElementById('close-modal-btn');
    
    if(modal) {
        document.querySelectorAll('.edit-meta-btn').forEach(btn => {
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

        closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
        window.addEventListener('click', function(e) { if (e.target == modal) { modal.style.display = 'none'; } });
    }




    // Búsqueda y paginación (motor compartido)
    if (window.SiarheAdmin && window.SiarheAdmin.initTable) {
        window.SiarheAdmin.initTable({
            rows:    document.querySelectorAll('.siarhe-data-row'),
            search:  document.getElementById('siarhe-search-marcadores-file'),
            perPage: document.getElementById('siarhe-items-per-page'),
            pagers:  [document.getElementById('siarhe-pagination-controls-top'), document.getElementById('siarhe-pagination-controls-bottom')],
            table:   document.getElementById('siarhe-marcadores-table'),
            count:   document.getElementById('siarhe-marcadores-count'),
            empty:   document.getElementById('siarhe-marcadores-empty')
        });
    }
});
</script>