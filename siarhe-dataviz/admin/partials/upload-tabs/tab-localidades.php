<?php // /admin/partials/upload-tabs/tab-localidades.php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener Entidades (Helper Centralizado)
$entidades_data = siarhe_get_entities();

// 2. Obtener archivos GeoJSON de Localidades existentes
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$existing_files = $wpdb->get_results( 
    $wpdb->prepare( "SELECT * FROM $table_assets WHERE tipo_archivo = %s AND es_activo = 1", 'localidades_geojson' )
);

// Indexar por slug
$files_by_slug = [];
foreach ($existing_files as $file) {
    $files_by_slug[$file->entidad_slug] = $file;
}

// Directorio base para comprobaciones físicas
$upload_base_dir = defined('SIARHE_UPLOAD_DIR') ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/';

// Mensajes de estado
if ( isset($_GET['status']) ) {
    if ( $_GET['status'] == 'success' ) echo '<div class="notice notice-success is-dismissible"><p>Mapa GeoJSON de Localidades cargado correctamente.</p></div>';
    if ( $_GET['status'] == 'updated' ) echo '<div class="notice notice-success is-dismissible"><p>Metadatos del mapa de localidades actualizados.</p></div>';
    if ( $_GET['status'] == 'deleted' ) echo '<div class="notice notice-warning is-dismissible"><p>Mapa de localidades eliminado correctamente.</p></div>';
}

// Función auxiliar para mostrar la fecha de modificación con formato estético
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
    <h2>🏘️ Cargar Mapa GeoJSON (Localidades)</h2>
    
    <div class="notice notice-info inline" style="margin: 10px 0 20px 0;">
        <p><strong>Política de Archivos Únicos:</strong></p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><strong>Formatos admitidos:</strong> Archivos .json o .geojson.</li>
            <li><strong>Nomenclatura:</strong> El archivo se guardará automáticamente con el nombre estándar <code>{entidad}.geojson</code> dentro de la carpeta de localidades.</li>
            <li><strong>Proyección:</strong> Es obligatorio que el mapa esté exportado en formato <strong>WGS84 (EPSG:4326)</strong>.</li>
        </ul>
    </div>
    
    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="siarhe_upload_localidades">
        <?php wp_nonce_field( 'siarhe_upload_localidades_nonce', 'siarhe_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="entidad_slug">Entidad Federativa</label></th>
                <td>
                    <select name="entidad_slug" id="entidad_slug" class="regular-text" required>
                        <option value="">-- Selecciona la entidad --</option>
                        <?php foreach ($entidades_data as $slug => $data) : ?>
                            <option value="<?php echo esc_attr($slug); ?>">
                                <?php echo esc_html($data['nombre']); ?> (<?php echo esc_html($data['CVE_ENT']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="siarhe_file">Archivo GeoEspacial (Localidades)</label></th>
                <td>
                    <input type="file" name="siarhe_file" id="siarhe_file" accept=".json,.geojson" required>
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
                    <textarea name="referencia" id="referencia" rows="2" class="large-text" placeholder="Ej: Marco Geoestadístico INEGI 2025."></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="comentarios">Comentarios Internos</label></th>
                <td>
                    <textarea name="comentarios" id="comentarios" rows="2" class="large-text" placeholder="Notas sobre la simplificación del mapa..."></textarea>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Subir / Reemplazar">
        </p>
    </form>
</div>

<div class="card" style="max-width: 100%; padding: 0; overflow: hidden;">
    <h2 style="padding: 15px; margin: 0; border-bottom: 1px solid #eee;">Estado de Mapas (Localidades)</h2>
    
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
            <input type="text" id="siarhe-search-localidades" placeholder="Buscar entidad, archivo o estado...">
        </div>
    </div>
    
    <table id="siarhe-localidades-table" class="siarhe-table">
        <thead>
            <tr>
                <th style="width: 20%;">Entidad</th>
                <th style="width: 5%;">CVE</th>
                <th style="width: 10%;">Estado</th>
                <th style="width: 20%;">Archivo del Sistema</th>
                <th style="width: 10%;">Fecha de Corte</th>
                <th style="width: 15%;">Auditoría</th>
                <th style="width: 5%;">Tamaño</th>
                <th style="width: 15%;">Acciones</th>
            </tr>
        </thead>
        <tbody id="siarhe-localidades-tbody">
            <?php 
            $rows = [];
            $upload_base = defined('SIARHE_UPLOAD_DIR') ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/';

            foreach ($entidades_data as $slug => $data) {
                $nombre = $data['nombre'];
                $cve_ent = $data['CVE_ENT'];
                $archivo = isset($files_by_slug[$slug]) ? $files_by_slug[$slug] : null;
                
                $ruta_fisica = $archivo ? $upload_base . ltrim($archivo->ruta_archivo, '/') : '';
                $existe_fisico = $archivo && file_exists($ruta_fisica);

                $sort_key = (int)$cve_ent;
                if ($slug == 'republica-mexicana') $sort_key = -1;
                
                $rows[] = [
                    'slug' => $slug, 
                    'nombre' => $nombre, 
                    'cve_ent' => $cve_ent, 
                    'archivo' => $archivo, 
                    'existe_fisico' => $existe_fisico,
                    'ruta_fisica' => $ruta_fisica,
                    'sort_key' => $sort_key
                ];
            }
            usort($rows, function($a, $b) { return $a['sort_key'] <=> $b['sort_key']; });

            foreach ($rows as $row) : 
                $slug = $row['slug']; 
                $nombre = $row['nombre']; 
                $cve_ent = $row['cve_ent'];
                $archivo = $row['archivo']; 
                $exists = $row['existe_fisico'];
            ?>
            <tr class="siarhe-data-row">
                <td data-label="Entidad" data-mobile-role="primary">
                    <strong><?php echo esc_html($nombre); ?></strong>
                </td>
                
                <td data-label="CVE">
                    <span class="siarhe-badge neutral"><?php echo esc_html($cve_ent); ?></span>
                </td>
                
                <td data-label="Estado" data-mobile-role="secondary">
                    <?php if ($exists) : ?>
                        <span class="dashicons dashicons-location" style="color: #46b450;"></span> <strong style="color:#46b450">Mapeado</strong>
                    <?php else : ?>
                        <span class="dashicons dashicons-minus" style="color: #ccc;"></span> <span style="color:#777;">Sin Mapa</span>
                    <?php endif; ?>
                </td>

                <td data-label="Archivo del Sistema">
                    <?php if ($exists) : ?>
                        <code style="font-size:11px;"><?php echo esc_html(basename($archivo->ruta_archivo)); ?></code>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <td data-label="Fecha de Corte">
                    <?php if ($archivo && $archivo->fecha_corte) : ?>
                        <?php echo date_i18n('d/m/Y', strtotime($archivo->fecha_corte)); ?>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <td data-label="Auditoría">
                    <?php if ($archivo) : 
                        $autor_original = $archivo->subido_por ?? ($archivo->creado_por ?? ($archivo->registrado_por ?? 'Sistema'));
                        $fecha_original = $archivo->fecha_subida ?? ($archivo->fecha_creacion ?? ($archivo->fecha_registro ?? $archivo->fecha_modificacion));
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

                        <?php if (!empty($archivo->fecha_modificacion) && $archivo->fecha_modificacion !== $fecha_original) : ?>
                            <div style="line-height: 1.3; border-top: 1px dashed #e2e8f0; padding-top: 6px;">
                                <span style="font-size:10px; font-weight:bold; color:#0ea5e9; text-transform:uppercase;">Última edición:</span><br>
                                <span style="font-size:12px; color:#0f172a; font-weight:500;">
                                    <?php echo esc_html($archivo->modificado_por ?: 'Sistema'); ?>
                                </span><br>
                                <span style="color:#64748b; font-size:11px;">
                                    <?php echo format_custom_date($archivo->fecha_modificacion); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($exists) : ?>
                        <div style="line-height: 1.3;">
                            <span style="font-size:10px; font-weight:bold; color:#94a3b8; text-transform:uppercase;">Subido por:</span><br>
                            <span style="font-size:12px; color:#0f172a; font-weight:500;">Sistema (Vía FTP/Cpanel)</span><br>
                            <span style="color:#64748b; font-size:11px;">
                                <?php echo format_custom_date(date("Y-m-d H:i:s", filemtime($row['ruta_fisica']))); ?>
                            </span>
                        </div>
                    <?php else : ?>
                        <span style="color:#cbd5e1;">—</span>
                    <?php endif; ?>
                </td>

                <td data-label="Tamaño">
                    <?php if ($exists) : ?>
                        <?php echo size_format(filesize($row['ruta_fisica'])); ?>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <td data-label="Acciones">
                    <?php if ($exists) : ?>
                        <button type="button" class="button button-small copy-url-btn" 
                                data-url="<?php echo esc_url(SIARHE_UPLOAD_URL . $archivo->ruta_archivo); ?>" title="Copiar Enlace">
                            <span class="dashicons dashicons-admin-links"></span>
                        </button>
                        
                        <?php if ($archivo) : ?>
                        <button type="button" class="button button-small edit-meta-btn" 
                                data-id="<?php echo $archivo->id; ?>" 
                                data-nombre="<?php echo esc_attr($nombre); ?>" 
                                data-anio="<?php echo esc_attr($archivo->anio_reporte); ?>" 
                                data-corte="<?php echo esc_attr($archivo->fecha_corte); ?>" 
                                data-ref="<?php echo esc_attr($archivo->referencia_bibliografica); ?>" 
                                data-notes="<?php echo esc_attr($archivo->comentarios); ?>"
                                title="Editar Info">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <?php endif; ?>
                        
                        <a href="<?php echo esc_url(SIARHE_UPLOAD_URL . $archivo->ruta_archivo); ?>" target="_blank" class="button button-small" title="Descargar">
                            <span class="dashicons dashicons-download"></span>
                        </a>

                        <?php if ($archivo) : ?>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;" onsubmit="return confirm('⚠️ ¿Estás seguro de eliminar este mapa GEOJSON?');">
                            <input type="hidden" name="action" value="siarhe_delete_localidades">
                            <input type="hidden" name="file_id" value="<?php echo $archivo->id; ?>">
                            <?php wp_nonce_field( 'siarhe_delete_localidades_nonce_' . $archivo->id ); ?>
                            <button type="submit" class="button button-small button-link-delete" title="Eliminar"><span class="dashicons dashicons-trash" style="color: #a00;"></span></button>
                        </form>
                        <?php endif; ?>
                    <?php else : ?><span class="description">—</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="siarhe-localidades-empty" style="display: none;">
                <td colspan="8" style="text-align:center; padding: 20px; color:#8c8f94;">No se encontraron resultados para su búsqueda.</td>
            </tr>
        </tbody>
    </table>

    <div class="siarhe-pagination">
        <div id="siarhe-localidades-count" style="font-size: 13px; color: #64748b;"></div>
        <div class="siarhe-page-numbers" id="siarhe-pagination-controls"></div>
    </div>
</div>

<div id="siarhe-edit-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content">
        <h2 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            Editar Metadatos GeoJSON: <span id="modal-entidad-name" style="color: #2271b1;"></span>
        </h2>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="siarhe_update_localidades_meta">
            <input type="hidden" name="file_id" id="modal-file-id">
            <?php wp_nonce_field( 'siarhe_update_localidades_meta_nonce', 'siarhe_meta_nonce' ); ?>

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
    
    // Configuración de Modales
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

    // Motor de Paginación y Filtrado por DOM Hiding
    const searchInput = document.getElementById('siarhe-search-localidades');
    const itemsPerPageSelect = document.getElementById('siarhe-items-per-page');
    const paginationControls = document.getElementById('siarhe-pagination-controls');
    const countDisplay = document.getElementById('siarhe-localidades-count');
    
    const allRows = Array.from(document.querySelectorAll('.siarhe-data-row'));
    const emptyRow = document.getElementById('siarhe-localidades-empty');

    let currentPage = 1;
    let itemsPerPage = 25;
    let matchedRows = allRows;

    function applySearchFilter() {
        const term = searchInput.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        
        matchedRows = allRows.filter(row => {
            // Evaluamos todo el texto de la fila (Entidad, Archivo, Estado, etc.)
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
        
        // Ocultar todos los matches temporalmente
        matchedRows.forEach(row => row.style.display = 'none');
        emptyRow.style.display = totalItems === 0 ? '' : 'none';

        if (itemsPerPage === 'all') {
            matchedRows.forEach(row => row.style.display = '');
        } else {
            totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            
            // Mostrar solo los elementos de la página actual
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

    // Lógica interna para acordeón móvil aislando clicks en botones
    document.querySelectorAll('.siarhe-data-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (window.innerWidth > 767) return;
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
            this.classList.toggle('is-open');
        });
    });

    // Inicialización del módulo
    applySearchFilter();
});
</script>