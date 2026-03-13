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

// Interceptor de transacciones locales vía POST para gestión de ranuras
if ( isset($_POST['action']) ) {
    if ( $_POST['action'] === 'add_archivo_marcador' && isset($_POST['new_file_key']) ) {
        $new_key = sanitize_text_field(strtoupper(str_replace(' ', '_', $_POST['new_file_key'])));
        $new_label = sanitize_text_field($_POST['new_file_label']);
        $new_filename = sanitize_file_name(strtolower(str_replace(' ', '-', $_POST['new_file_name'])));
        if (strpos($new_filename, '.csv') === false) $new_filename .= '.csv';
        
        if (!empty($new_key) && !isset($tipos_marcadores[$new_key])) {
            $tipos_marcadores[$new_key] = [ 'label' => $new_label, 'filename' => $new_filename, 'is_core' => false ];
            update_option( 'siarhe_archivos_marcadores', wp_json_encode($tipos_marcadores, JSON_UNESCAPED_UNICODE) );
            echo '<div class="notice notice-success is-dismissible"><p>Ranura de variable registrada. Archivo CSV admitido para carga.</p></div>';
        }
    }
    
    if ( $_POST['action'] === 'delete_archivo_marcador' && isset($_POST['del_file_key']) ) {
        $del_key = sanitize_text_field($_POST['del_file_key']);
        if (isset($tipos_marcadores[$del_key]) && empty($tipos_marcadores[$del_key]['is_core'])) {
            unset($tipos_marcadores[$del_key]);
            update_option( 'siarhe_archivos_marcadores', wp_json_encode($tipos_marcadores, JSON_UNESCAPED_UNICODE) );
            echo '<div class="notice notice-success is-dismissible"><p>Ranura de variable eliminada del registro.</p></div>';
        }
    }
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
if ( isset($_GET['status']) ) {
    if ( $_GET['status'] == 'success' ) echo '<div class="notice notice-success is-dismissible"><p>Marcador procesado y actualizado correctamente.</p></div>';
    if ( $_GET['status'] == 'updated' ) echo '<div class="notice notice-success is-dismissible"><p>Metadatos sincronizados exitosamente.</p></div>';
}

// Utilidad local para formato legible de marcas de tiempo
if (!function_exists('format_custom_date')) {
    function format_custom_date($db_date) {
        if (!$db_date) return '—';
        $timestamp = strtotime($db_date);
        $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $mes = $meses[date('n', $timestamp) - 1];
        $hora = date('h:i a', $timestamp);
        $hora = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $hora);
        return date('j', $timestamp) . ' ' . $mes . ' ' . date('Y', $timestamp) . ', ' . $hora;
    }
}
?>

<div class="card siarhe-upload-card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <h2>📍 Gestión de Marcadores (Clínicas y Espectros)</h2>
    
    <div class="notice notice-info inline" style="margin: 10px 0 20px 0;">
        <p><strong>Política de Archivos Únicos:</strong></p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><strong>Formatos admitidos:</strong> Archivos .csv.</li>
            <li><strong>Nomenclatura:</strong> El archivo se guardará automáticamente con el nombre estándar configurado.</li>
            <li><strong>Proyección:</strong> Es necesario que las bases de datos usen las variables <code>CVE_ENT</code>, <code>CVE_MUN</code>, <code>LATITUD</code>, <code>LONGITUD</code>.</li>
        </ul>
    </div>

    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="siarhe_upload_marker">
        <?php wp_nonce_field( 'siarhe_upload_marker_nonce', 'marker_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="marker_type">Tipo de Marcador</label></th>
                <td>
                    <select name="marker_type" id="marker_type" class="regular-text" required>
                        <option value="">-- Selecciona el tipo --</option>
                        <?php foreach ($tipos_marcadores as $key => $info) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($info['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="marker_file">Archivo CSV</label></th>
                <td>
                    <input type="file" name="marker_file" id="marker_file" accept=".csv" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="anio_reporte">Año de los Datos</label></th>
                <td>
                    <input type="number" name="anio_reporte" placeholder="Ej: 2026" class="small-text" required min="2000" max="2100">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fecha_corte">Fecha de Corte</label></th>
                <td>
                    <input type="date" name="fecha_corte" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="referencia">Fuente / Referencia</label></th>
                <td>
                    <textarea name="referencia" id="referencia" rows="2" class="large-text" placeholder="Ej: Catálogo de Establecimientos, Secretaría de Salud."></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="comentarios">Comentarios Internos</label></th>
                <td>
                    <textarea name="comentarios" id="comentarios" rows="2" class="large-text" placeholder="Notas sobre la limpieza de coordenadas..."></textarea>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="Subir y Reemplazar">
        </p>
    </form>
</div>

<div class="card" style="max-width: 100%; padding: 0; margin-bottom: 20px; overflow: hidden;">
    <h2 style="padding: 15px; margin: 0; border-bottom: 1px solid #eee;">Archivos en el Servidor</h2>
    
    <div class="siarhe-toolbar">
        <div class="siarhe-table-controls">
            <label style="font-size: 13px; color: #3c434a;">
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
                        <code style="font-size:11px;"><?php echo esc_html($filename); ?></code>
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
                        <div style="margin-bottom: 8px; line-height: 1.3;">
                            <span style="font-size:10px; font-weight:bold; color:#94a3b8; text-transform:uppercase;">Subido por:</span><br>
                            <span style="font-size:12px; color:#0f172a; font-weight:500;">
                                <?php echo esc_html($autor_original); ?>
                            </span><br>
                            <span style="color:#64748b; font-size:11px;">
                                <?php echo format_custom_date($fecha_original); ?>
                            </span>
                        </div>

                        <?php if (!empty($db_file->fecha_modificacion) && $db_file->fecha_modificacion !== $fecha_original) : ?>
                            <div style="line-height: 1.3; border-top: 1px dashed #e2e8f0; padding-top: 6px;">
                                <span style="font-size:10px; font-weight:bold; color:#0ea5e9; text-transform:uppercase;">Última edición:</span><br>
                                <span style="font-size:12px; color:#0f172a; font-weight:500;">
                                    <?php echo esc_html($db_file->modificado_por ?: 'Sistema'); ?>
                                </span><br>
                                <span style="color:#64748b; font-size:11px;">
                                    <?php echo format_custom_date($db_file->fecha_modificacion); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($existe_fisico) : ?>
                        <div style="line-height: 1.3;">
                            <span style="font-size:10px; font-weight:bold; color:#94a3b8; text-transform:uppercase;">Subido por:</span><br>
                            <span style="font-size:12px; color:#0f172a; font-weight:500;">Sistema (Vía FTP/Cpanel)</span><br>
                            <span style="color:#64748b; font-size:11px;">
                                <?php echo format_custom_date(date("Y-m-d H:i:s", filemtime($ruta_fisica))); ?>
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
                    <?php if ($existe_fisico) : ?>
                        <button type="button" class="button button-small copy-url-btn" 
                                data-url="<?php echo esc_url($url_publica); ?>" title="Copiar URL">
                            <span class="dashicons dashicons-admin-links"></span>
                        </button>

                        <?php if ($db_file) : ?>
                        <button type="button" class="button button-small edit-meta-btn" 
                                data-id="<?php echo $db_file->id; ?>" 
                                data-nombre="<?php echo esc_attr($info['label']); ?>" 
                                data-anio="<?php echo esc_attr($db_file->anio_reporte); ?>" 
                                data-corte="<?php echo esc_attr($db_file->fecha_corte); ?>" 
                                data-ref="<?php echo esc_attr($db_file->referencia_bibliografica); ?>" 
                                data-notes="<?php echo esc_attr($db_file->comentarios); ?>"
                                title="Editar Info">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <?php endif; ?>

                        <a href="<?php echo esc_url($url_publica); ?>" target="_blank" class="button button-small" title="Descargar">
                            <span class="dashicons dashicons-download"></span>
                        </a>

                        <?php if ($db_file) : ?>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;" onsubmit="return confirm('⚠️ ¿Estás seguro de eliminar este archivo?');">
                            <input type="hidden" name="action" value="siarhe_delete_static">
                            <input type="hidden" name="file_id" value="<?php echo $db_file->id; ?>">
                            <?php wp_nonce_field( 'siarhe_delete_static_nonce_' . $db_file->id ); ?>
                            <button type="submit" class="button button-small button-link-delete" title="Eliminar Archivo"><span class="dashicons dashicons-trash" style="color: #a00;"></span></button>
                        </form>
                        <?php endif; ?>
                    <?php else : ?>—<?php endif; ?>
                    
                    <?php if (empty($info['is_core'])): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta variable de la lista? (El archivo CSV seguirá en el servidor si existe)');">
                            <input type="hidden" name="action" value="delete_archivo_marcador">
                            <input type="hidden" name="del_file_key" value="<?php echo esc_attr($key); ?>">
                            <button type="submit" class="button button-small button-link-delete" title="Borrar Variable" style="color:#d63638; border-color:#d63638;"><span class="dashicons dashicons-dismiss"></span></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="siarhe-marcadores-empty" style="display: none;">
                <td colspan="7" style="text-align:center; padding: 20px; color:#8c8f94;">No se encontraron resultados para su búsqueda.</td>
            </tr>
        </tbody>
    </table>

    <div class="siarhe-pagination">
        <div id="siarhe-marcadores-count" style="font-size: 13px; color: #64748b;"></div>
        <div class="siarhe-page-numbers" id="siarhe-pagination-controls"></div>
    </div>
</div>

<div class="card siarhe-upload-card" style="max-width: 100%; padding: 20px;">
    <h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">➕ Incorporar Nueva Variable (CSV)</h3>
    <p class="description">Agrega una nueva ranura de archivo para cargar marcadores adicionales (ej. Casos Epidemiológicos, Unidades Móviles).</p>
    
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="add_archivo_marcador">
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label>Clave Interna (Sin espacios):</label></th>
                <td><input type="text" name="new_file_key" placeholder="Ej: CASOS_DENGUE" required class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label>Etiqueta Pública:</label></th>
                <td><input type="text" name="new_file_label" placeholder="Ej: Casos de Dengue 2026" required class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label>Nombre del Archivo (con .csv):</label></th>
                <td><input type="text" name="new_file_name" placeholder="ej: casos-dengue.csv" required class="regular-text"></td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" class="button button-secondary">Agregar Variable</button>
        </p>
    </form>
</div>

<div id="siarhe-edit-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content">
        <h2 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            Editar Metadatos: <span id="modal-entidad-name" style="color: #2271b1;"></span>
        </h2>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="siarhe_update_static_meta">
            <input type="hidden" name="file_id" id="modal-file-id">
            <?php wp_nonce_field( 'siarhe_update_static_meta_nonce', 'siarhe_meta_nonce' ); ?>

            <table class="form-table">
                <tr><th><label>Año de los Datos</label></th><td><input type="number" name="anio_reporte" id="modal-anio" class="regular-text" required></td></tr>
                <tr><th><label>Fecha de Corte</label></th><td><input type="date" name="fecha_corte" id="modal-corte" class="regular-text" required></td></tr>
                <tr><th><label>Fuente / Referencia</label></th><td><textarea name="referencia" id="modal-ref" rows="3" class="large-text"></textarea></td></tr>
                <tr><th><label>Comentarios Internos</label></th><td><textarea name="comentarios" id="modal-notes" rows="3" class="large-text"></textarea></td></tr>
            </table>
            <div style="text-align:right; margin-top:20px; border-top: 1px solid #eee; padding-top: 15px;">
                <button type="button" class="button button-secondary" id="close-modal-btn">Cancelar</button>
                <button type="submit" class="button button-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
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

    // Utilidad: Copiar URL
    document.querySelectorAll('.copy-url-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            const url = this.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(() => {
                const original = this.innerHTML;
                this.innerHTML = '<span class="dashicons dashicons-yes" style="color:green;"></span>';
                setTimeout(() => { this.innerHTML = original; }, 1500);
            });
        });
    });

    // Motor de Paginación y Filtrado vía DOM Hiding
    const searchInput = document.getElementById('siarhe-search-marcadores-file');
    const itemsPerPageSelect = document.getElementById('siarhe-items-per-page');
    const paginationControls = document.getElementById('siarhe-pagination-controls');
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

    function updatePaginationUI(totalItems, currentItemsCount, totalPages) {
        if (totalItems === 0) {
            countDisplay.innerHTML = 'No hay registros para mostrar.';
            paginationControls.innerHTML = '';
            return;
        }

        let startRange = 1;
        let endRange = totalItems;

        if (itemsPerPage !== 'all') {
            startRange = ((currentPage - 1) * itemsPerPage) + 1;
            endRange = startRange + currentItemsCount - 1;
        }

        countDisplay.innerHTML = `Mostrando del <strong>${startRange}</strong> al <strong>${endRange}</strong> de <strong>${totalItems}</strong> registros`;

        paginationControls.innerHTML = '';
        if (totalPages <= 1) return;

        const btnPrev = document.createElement('a');
        btnPrev.className = `siarhe-page-btn ${currentPage === 1 ? 'disabled' : ''}`;
        btnPrev.innerHTML = '« Ant';
        btnPrev.addEventListener('click', (e) => { e.preventDefault(); if(currentPage > 1) { currentPage--; renderPagination(); } });
        paginationControls.appendChild(btnPrev);

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (currentPage <= 2) endPage = Math.min(totalPages, 5);
        if (currentPage >= totalPages - 1) startPage = Math.max(1, totalPages - 4);

        if (startPage > 1) {
            const btnFirst = document.createElement('a');
            btnFirst.className = 'siarhe-page-btn'; btnFirst.innerHTML = '1';
            btnFirst.addEventListener('click', (e) => { e.preventDefault(); currentPage = 1; renderPagination(); });
            paginationControls.appendChild(btnFirst);
            if (startPage > 2) paginationControls.insertAdjacentHTML('beforeend', '<span style="color:#8c8f94;">...</span>');
        }

        for (let i = startPage; i <= endPage; i++) {
            const btnP = document.createElement('a');
            btnP.className = `siarhe-page-btn ${i === currentPage ? 'active' : ''}`;
            btnP.innerHTML = i;
            btnP.addEventListener('click', (e) => { e.preventDefault(); currentPage = i; renderPagination(); });
            paginationControls.appendChild(btnP);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) paginationControls.insertAdjacentHTML('beforeend', '<span style="color:#8c8f94;">...</span>');
            const btnLast = document.createElement('a');
            btnLast.className = 'siarhe-page-btn'; btnLast.innerHTML = totalPages;
            btnLast.addEventListener('click', (e) => { e.preventDefault(); currentPage = totalPages; renderPagination(); });
            paginationControls.appendChild(btnLast);
        }

        const btnNext = document.createElement('a');
        btnNext.className = `siarhe-page-btn ${currentPage === totalPages ? 'disabled' : ''}`;
        btnNext.innerHTML = 'Sig »';
        btnNext.addEventListener('click', (e) => { e.preventDefault(); if(currentPage < totalPages) { currentPage++; renderPagination(); } });
        paginationControls.appendChild(btnNext);
    }

    if(searchInput) searchInput.addEventListener('input', applySearchFilter);
    
    if(itemsPerPageSelect) {
        itemsPerPageSelect.addEventListener('change', (e) => {
            itemsPerPage = e.target.value === 'all' ? 'all' : parseInt(e.target.value, 10);
            currentPage = 1;
            renderPagination();
        });
    }

    // Lógica interna para acordeón móvil aislando controles interactivos
    document.querySelectorAll('.siarhe-data-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (window.innerWidth > 767) return;
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('form')) return;
            this.classList.toggle('is-open');
        });
    });

    // Inicialización del módulo
    applySearchFilter();
});
</script>