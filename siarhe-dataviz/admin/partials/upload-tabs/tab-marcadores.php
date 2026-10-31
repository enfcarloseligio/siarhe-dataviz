<?php // /admin/partials/upload-tabs/tab-marcadores.php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Gestor dinámico del diccionario de archivos
$archivos_json = get_option( 'siarhe_archivos_marcadores', '' );
$tipos_marcadores = json_decode( wp_unslash( $archivos_json ), true );

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
    $wpdb->prepare( "SELECT * FROM $table_assets WHERE tipo_archivo = %s AND es_activo = 1", 'marcador' )
);

$files_by_type = [];
foreach ($existing_files as $file) {
    $files_by_type[$file->entidad_slug] = $file;
}

// Directorio base de validación física
$upload_base_dir = (defined('SIARHE_UPLOAD_DIR') ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/') . 'markers/';

// Manejo de notificaciones de estado
Siarhe_Admin::render_flash_notice();
if ( isset($_GET['status']) ) {
    if ( $_GET['status'] == 'success' ) echo '<div class="notice notice-success is-dismissible"><p>Marcador procesado y actualizado correctamente.</p></div>';
    if ( $_GET['status'] == 'updated' ) echo '<div class="notice notice-success is-dismissible"><p>Metadatos sincronizados exitosamente.</p></div>';
    if ( $_GET['status'] == 'deleted' ) echo '<div class="notice notice-warning is-dismissible"><p>Archivo CSV y registro eliminados.</p></div>';
    if ( $_GET['status'] == 'var_added' ) echo '<div class="notice notice-success is-dismissible"><p>Variable registrada. Ya puedes cargar su archivo CSV.</p></div>';
    if ( $_GET['status'] == 'var_deleted' ) echo '<div class="notice notice-warning is-dismissible"><p>Variable y archivo CSV asociado eliminados permanentemente.</p></div>';
    if ( $_GET['status'] == 'var_reset' ) echo '<div class="notice notice-warning is-dismissible"><p>Se restauraron las variables iniciales; los archivos personalizados fueron eliminados.</p></div>';
    if ( $_GET['status'] == 'var_exists' ) echo '<div class="notice notice-error is-dismissible"><p>Ya existe una variable con esa clave.</p></div>';
    if ( $_GET['status'] == 'var_invalid' ) echo '<div class="notice notice-error is-dismissible"><p>Datos de variable no válidos.</p></div>';
}
?>

<?php Siarhe_Admin::render_select2_css(); ?>


<div class="siarhe-card siarhe-upload-card">
    <h2>📍 Gestión de Marcadores (Clínicas y Espectros)<?php Siarhe_Admin::help('Carga los CSV de puntos que se dibujan sobre el mapa (posicion, espectro o spike).'); ?></h2>
    
    <div class="notice notice-info inline">
        <p><strong>Política de Archivos Únicos:</strong></p>
        <ul>
            <li><strong>Formatos admitidos:</strong> Archivos .csv.</li>
            <li><strong>Nomenclatura:</strong> El archivo se guardará automáticamente con el nombre estándar configurado.</li>
            <li><strong>Proyección:</strong> Es necesario que las bases de datos usen las variables <code>CVE_ENT</code>, <code>CVE_MUN</code>, <code>LATITUD</code>, <code>LONGITUD</code>.</li>
        </ul>
    </div>

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
                <th scope="row"><label for="marker_file">Archivo CSV</label><?php Siarhe_Admin::help('CSV con los puntos del marcador (coordenadas y, segun el tipo, la columna numerica). Se valida antes de guardarse.'); ?></th>
                <td>
                    <input type="file" data-check="marker-csv" name="marker_file" id="marker_file" accept=".csv" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="anio_reporte">Año de los Datos</label><?php Siarhe_Admin::help('Anio al que corresponden los datos. Se muestra en las referencias del mapa.'); ?></th>
                <td>
                    <input type="number" name="anio_reporte" placeholder="Ej: 2026" class="small-text" required min="2000" max="2100">
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
        <h3 style="margin: 0;">➕ Incorporar Nueva Variable (CSV)<?php Siarhe_Admin::help('Registra una nueva variable de marcador antes de subir su CSV.'); ?></h3>
        
        <form id="form-reset-variables" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="PRECAUCIÓN: se borrarán permanentemente TODAS las variables personalizadas y sus archivos CSV del servidor. ¿Deseas continuar?" data-confirm-ok="Restaurar">
            <input type="hidden" name="action" value="siarhe_marker_var_reset">
            <?php wp_nonce_field('reset_variables_nonce', 'reset_nonce'); ?>
            <button type="submit" class="siarhe-btn-icon button button-link-delete" style="color: #d63638; border: 1px solid #d63638; padding: 0 10px; border-radius: 3px; background: #fff;">
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
                <select id="siarhe-items-per-page" style="margin: 0 5px; padding: 2px 24px 2px 8px; font-size: 13px; min-height: 28px;">
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
                <th style="width: 20%">Marcador</th>
                <th style="width: 15%">Estado</th>
                <th style="width: 20%">Archivo Sistema</th>
                <th style="width: 10%">Fecha de Corte</th>
                <th style="width: 15%">Auditoría</th>
                <th style="width: 5%">Tamaño</th>
                <th style="width: 15%">Acciones</th>
            </tr>
        </thead>
        <tbody id="siarhe-marcadores-tbody">
            <?php foreach ($tipos_marcadores as $key => $info) : 
                $db_file = isset($files_by_type[$key]) ? $files_by_type[$key] : null;
                
                $filename = $info['filename'];
                $ruta_fisica = $upload_base_dir . $filename;
                $existe_fisico = file_exists($ruta_fisica);
                
                $url_publica = defined('SIARHE_UPLOAD_URL') ? SIARHE_UPLOAD_URL . 'markers/' . $filename : '';
            ?>
            <tr class="siarhe-data-row">
                <td data-label="Marcador" data-mobile-role="primary">
                    <strong><?php echo esc_html($info['label']); ?></strong>
                    <?php if(!empty($info['is_core'])) echo '<br><small style="color:#aaa;">(Nativo)</small>'; ?>
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
                    <?php else : ?>—<?php endif; ?>
                </td>
                
                <td data-label="Fecha de Corte">
                    <?php if ($db_file && $db_file->fecha_corte) : ?>
                        <?php echo date_i18n('d/M/Y', strtotime($db_file->fecha_corte)); ?>
                    <?php else : ?>—<?php endif; ?>
                </td>
                
                <td data-label="Auditoría">
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
                        <span style="color:#cbd5e1;">—</span>
                    <?php endif; ?>
                </td>
                
                <td data-label="Tamaño">
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
                                data-nombre="<?php echo esc_attr($info['label']); ?>" 
                                data-anio="<?php echo esc_attr($db_file->anio_reporte); ?>" 
                                data-corte="<?php echo esc_attr($db_file->fecha_corte); ?>" 
                                data-ref="<?php echo esc_attr($db_file->referencia_bibliografica); ?>" 
                                data-notes="<?php echo esc_attr($db_file->comentarios); ?>"
                                title="Ver Info / Editar">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( $url_con_version ); ?>" target="_blank" class="button button-small" title="Descargar">
                            <span class="dashicons dashicons-download"></span>
                        </a>

                        <?php if ( $db_file ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="¿Eliminar solo el archivo CSV de este marcador? La variable se conserva y podrás volver a cargar un archivo.">
                            <input type="hidden" name="action" value="siarhe_delete_static">
                            <input type="hidden" name="file_id" value="<?php echo absint( $db_file->id ); ?>">
                            <?php wp_nonce_field( 'siarhe_delete_static_nonce_' . $db_file->id ); ?>
                            <button type="submit" class="button button-small button-danger" data-help="Eliminar solo el archivo CSV. La variable se conserva."><span class="dashicons dashicons-trash"></span></button>
                        </form>
                        <?php endif; ?>

                    <?php else : ?>—<?php endif; ?>
                    
                    <?php if (empty($info['is_core'])): ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="¿Eliminar esta variable de forma permanente? El archivo CSV y su registro en la base de datos también serán borrados.">
                            <input type="hidden" name="action" value="siarhe_marker_var_delete">
                            <?php wp_nonce_field( 'siarhe_delete_variable_nonce', 'del_nonce' ); ?>
                            <input type="hidden" name="del_file_key" value="<?php echo esc_attr($key); ?>">
                            <button type="submit" class="button button-small button-danger" data-help="Eliminar la variable completa: su configuración y su archivo CSV."><span class="dashicons dashicons-dismiss"></span></button>
                        </form>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="siarhe-marcadores-empty" style="display: none;">
                <td colspan="7" class="siarhe-empty-cell">No se encontraron resultados para su búsqueda.</td>
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
                <tr><th><label>Año de los Datos</label><?php Siarhe_Admin::help('Anio al que corresponden los datos. Se muestra en las referencias del mapa.'); ?></th><td><input type="number" name="anio_reporte" id="modal-anio" class="regular-text" required></td></tr>
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




    // Motor de Paginación y Filtrado vía DOM Hiding
    const searchInput = document.getElementById('siarhe-search-marcadores-file');
    const itemsPerPageSelect = document.getElementById('siarhe-items-per-page');
    // Paginadores
    const paginationControlsTop = document.getElementById('siarhe-pagination-controls-top');
    const paginationControlsBottom = document.getElementById('siarhe-pagination-controls-bottom');
    const countDisplay = document.getElementById('siarhe-marcadores-count');
    
    const allRows = Array.from(document.querySelectorAll('.siarhe-data-row'));
    const emptyRow = document.getElementById('siarhe-marcadores-empty');

    let currentPage = 1;
    let itemsPerPage = 25;
    let matchedRows = allRows;

    function applySearchFilter() {
        const term = searchInput.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        
        matchedRows = allRows.filter(row => {
            const text = row.textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const match = text.includes(term);
            if (!match) row.style.display = 'none';
            return match;
        });

        currentPage = 1;
        renderPagination();
    }

    function renderPagination() {
        const totalItems = matchedRows.length;
        let totalPages = 1;
        
        matchedRows.forEach(row => row.style.display = 'none');
        emptyRow.style.display = totalItems === 0 ? '' : 'none';

        if (itemsPerPage === 'all') {
            matchedRows.forEach(row => row.style.display = '');
        } else {
            totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            
            matchedRows.slice(start, end).forEach(row => {
                row.style.display = '';
            });
        }
        
        updatePaginationUI(totalItems, itemsPerPage === 'all' ? totalItems : Math.min(itemsPerPage, totalItems - (currentPage-1)*itemsPerPage), totalPages);
    }

    function generatePaginationHTML(totalPages) {
        let html = '';
        if (totalPages <= 1) return html;

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (currentPage <= 2) endPage = Math.min(totalPages, 5);
        if (currentPage >= totalPages - 1) startPage = Math.max(1, totalPages - 4);

        html += `<a href="#" class="siarhe-page-btn ${currentPage === 1 ? 'disabled' : ''}" data-page="prev">« Ant</a>`;

        if (startPage > 1) {
            html += `<a href="#" class="siarhe-page-btn" data-page="1">1</a>`;
            if (startPage > 2) html += `<span style="color:#8c8f94;">...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<a href="#" class="siarhe-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</a>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<span style="color:#8c8f94;">...</span>`;
            html += `<a href="#" class="siarhe-page-btn" data-page="${totalPages}">${totalPages}</a>`;
        }

        html += `<a href="#" class="siarhe-page-btn ${currentPage === totalPages ? 'disabled' : ''}" data-page="next">Sig »</a>`;
        
        return html;
    }

    function handlePaginationClick(e) {
        if (!e.target.classList.contains('siarhe-page-btn')) return;
        e.preventDefault();
        
        if (e.target.classList.contains('disabled')) return;

        const pageTarget = e.target.getAttribute('data-page');
        let totalPages = Math.ceil(matchedRows.length / itemsPerPage);

        if (pageTarget === 'prev' && currentPage > 1) {
            currentPage--;
        } else if (pageTarget === 'next' && currentPage < totalPages) {
            currentPage++;
        } else if (!isNaN(parseInt(pageTarget))) {
            currentPage = parseInt(pageTarget);
        }

        renderPagination();
    }

    function updatePaginationUI(totalItems, currentItemsCount, totalPages) {
        if (totalItems === 0) {
            countDisplay.innerHTML = 'No hay registros para mostrar.';
            paginationControlsTop.innerHTML = '';
            paginationControlsBottom.innerHTML = '';
            return;
        }

        let startRange = 1;
        let endRange = totalItems;

        if (itemsPerPage !== 'all') {
            startRange = ((currentPage - 1) * itemsPerPage) + 1;
            endRange = startRange + currentItemsCount - 1;
        }

        countDisplay.innerHTML = `Mostrando del <strong>${startRange}</strong> al <strong>${endRange}</strong> de <strong>${totalItems}</strong> registros`;

        const phtml = generatePaginationHTML(totalPages);
        paginationControlsTop.innerHTML = phtml;
        paginationControlsBottom.innerHTML = phtml;

        paginationControlsTop.querySelectorAll('a').forEach(a => a.addEventListener('click', handlePaginationClick));
        paginationControlsBottom.querySelectorAll('a').forEach(a => a.addEventListener('click', handlePaginationClick));
    }

    if(searchInput) searchInput.addEventListener('input', applySearchFilter);
    
    if(itemsPerPageSelect) {
        itemsPerPageSelect.addEventListener('change', (e) => {
            itemsPerPage = e.target.value === 'all' ? 'all' : parseInt(e.target.value, 10);
            currentPage = 1;
            renderPagination();
        });
    }

    // Inicialización del módulo
    applySearchFilter();
});
</script>