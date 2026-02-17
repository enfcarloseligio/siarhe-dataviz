<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Lista Maestra (Sin cambios)
$entidades_federativas = [
    'republica-mexicana' => 'República Mexicana',
    'aguascalientes' => 'Aguascalientes',
    'baja-california' => 'Baja California',
    'baja-california-sur' => 'Baja California Sur',
    'campeche' => 'Campeche',
    'coahuila' => 'Coahuila',
    'colima' => 'Colima',
    'chiapas' => 'Chiapas',
    'chihuahua' => 'Chihuahua',
    'ciudad-de-mexico' => 'Ciudad de México',
    'durango' => 'Durango',
    'guanajuato' => 'Guanajuato',
    'guerrero' => 'Guerrero',
    'hidalgo' => 'Hidalgo',
    'jalisco' => 'Jalisco',
    'mexico' => 'Estado de México',
    'michoacan' => 'Michoacán',
    'morelos' => 'Morelos',
    'nayarit' => 'Nayarit',
    'nuevo-leon' => 'Nuevo León',
    'oaxaca' => 'Oaxaca',
    'puebla' => 'Puebla',
    'queretaro' => 'Querétaro',
    'quintana-roo' => 'Quintana Roo',
    'san-luis-potosi' => 'San Luis Potosí',
    'sinaloa' => 'Sinaloa',
    'sonora' => 'Sonora',
    'tabasco' => 'Tabasco',
    'tamaulipas' => 'Tamaulipas',
    'tlaxcala' => 'Tlaxcala',
    'veracruz' => 'Veracruz',
    'yucatan' => 'Yucatán',
    'zacatecas' => 'Zacatecas'
];

$claves_inegi = [
    'aguascalientes' => '01', 'baja-california' => '02', 'baja-california-sur' => '03', 
    'campeche' => '04', 'coahuila' => '05', 'colima' => '06', 'chiapas' => '07', 
    'chihuahua' => '08', 'ciudad-de-mexico' => '09', 'durango' => '10', 'guanajuato' => '11', 
    'guerrero' => '12', 'hidalgo' => '13', 'jalisco' => '14', 'mexico' => '15', 
    'michoacan' => '16', 'morelos' => '17', 'nayarit' => '18', 'nuevo-leon' => '19', 
    'oaxaca' => '20', 'puebla' => '21', 'queretaro' => '22', 'quintana-roo' => '23', 
    'san-luis-potosi' => '24', 'sinaloa' => '25', 'sonora' => '26', 'tabasco' => '27', 
    'tamaulipas' => '28', 'tlaxcala' => '29', 'veracruz' => '30', 'yucatan' => '31', 
    'zacatecas' => '32', 'republica-mexicana' => '00'
];

global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$existing_files = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_assets WHERE tipo_archivo = %s AND es_activo = 1", 'geojson' ) );

$files_by_slug = [];
foreach ($existing_files as $file) { $files_by_slug[$file->entidad_slug] = $file; }

if ( isset($_GET['status']) ) {
    if ( $_GET['status'] == 'success' ) echo '<div class="notice notice-success is-dismissible"><p>Archivo GeoJSON subido correctamente.</p></div>';
    if ( $_GET['status'] == 'updated' ) echo '<div class="notice notice-success is-dismissible"><p>Metadatos actualizados correctamente.</p></div>';
    if ( $_GET['status'] == 'deleted' ) echo '<div class="notice notice-warning is-dismissible"><p>Archivo eliminado correctamente.</p></div>';
}
?>

<div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <h2>📤 Cargar Nuevo Mapa GeoJSON</h2>
    
    <div class="notice notice-info inline" style="margin: 10px 0 20px 0;">
        <p><strong>Requisitos Técnicos:</strong></p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li>Formato de archivo: <strong>.json</strong> o <strong>.geojson</strong>.</li>
            <li>Sistema de Coordenadas: <strong>WGS84 (EPSG:4326)</strong>.</li>
            <li>Metadato esperado: <code>urn:ogc:def:crs:OGC:1.3:CRS84</code>.</li>
        </ul>
    </div>
    
    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="siarhe_upload_geojson">
        <?php wp_nonce_field( 'siarhe_upload_nonce', 'siarhe_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="entidad_slug">Entidad Federativa</label></th>
                <td>
                    <select name="entidad_slug" id="entidad_slug" class="regular-text" required>
                        <option value="">-- Selecciona la entidad --</option>
                        <?php foreach ($entidades_federativas as $slug => $nombre) : ?>
                            <option value="<?php echo $slug; ?>"><?php echo $nombre; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="siarhe_file">Archivo GeoJSON</label></th>
                <td>
                    <input type="file" name="siarhe_file" id="siarhe_file" accept=".json,.geojson" required>
                    <p class="description">El sistema forzará la extensión <strong>.geojson</strong> al guardar.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="anio_reporte">Año y Fecha de Corte</label></th>
                <td>
                    <input type="number" name="anio_reporte" placeholder="Año (2026)" class="small-text" required min="2000" max="2100">
                    <input type="date" name="fecha_corte" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="referencia">Referencia Bibliográfica</label></th>
                <td>
                    <textarea name="referencia" id="referencia" rows="2" class="large-text" placeholder="Ej: Fuente: SIARHE, Secretaría de Salud (2026)."></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="comentarios">Comentarios Internos</label></th>
                <td>
                    <textarea name="comentarios" id="comentarios" rows="2" class="large-text" placeholder="Notas sobre la versión, sistema de coordenadas, etc."></textarea>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Subir y Guardar GeoJSON">
        </p>
    </form>
</div>

<div class="card" style="max-width: 100%; padding: 0;">
    <h2 style="padding: 15px; margin: 0; border-bottom: 1px solid #eee;">Estado de Mapas Base</h2>
    
    <table class="wp-list-table widefat fixed striped sortable-table">
        <thead>
            <tr>
                <th scope="col" class="manage-column sortable" data-sort="string" style="width: 25%; cursor:pointer;">
                    <span>Entidad</span> <span class="dashicons dashicons-sort"></span>
                </th>
                <th scope="col" class="manage-column sortable is-sorted" data-sort="int" style="width: 10%; cursor:pointer;">
                    <span>ID INEGI</span> <span class="dashicons dashicons-arrow-up"></span>
                </th>
                <th scope="col" class="manage-column sortable" data-sort="string" style="width: 10%; cursor:pointer;">
                    <span>Estado</span> <span class="dashicons dashicons-sort"></span>
                </th>
                <th scope="col" class="manage-column" style="width: 10%;">Formato</th>
                <th scope="col" class="manage-column" style="width: 15%;">Detalles (Año)</th>
                <th scope="col" class="manage-column" style="width: 30%;">Acciones</th>
            </tr>
        </thead>
        <tbody id="geo-table-body">
            <?php 
            $rows = [];
            foreach ($entidades_federativas as $slug => $nombre) {
                $clave = isset($claves_inegi[$slug]) ? $claves_inegi[$slug] : '99';
                $archivo = isset($files_by_slug[$slug]) ? $files_by_slug[$slug] : null;
                $sort_key = (int)$clave; 
                if ($slug == 'republica-mexicana') $sort_key = -1; 
                $rows[] = ['slug' => $slug, 'nombre' => $nombre, 'clave' => $clave, 'archivo' => $archivo, 'sort_key' => $sort_key];
            }
            usort($rows, function($a, $b) { return $a['sort_key'] <=> $b['sort_key']; });

            foreach ($rows as $row) : 
                $slug = $row['slug']; $nombre = $row['nombre']; $clave = $row['clave']; $archivo = $row['archivo']; $has_file = !empty($archivo);
            ?>
            <tr>
                <td class="column-primary" data-colname="Entidad" data-value="<?php echo $nombre; ?>">
                    <strong><?php echo $nombre; ?></strong>
                </td>
                <td data-colname="ID INEGI" data-value="<?php echo $clave; ?>">
                    <span class="badge" style="background:#eee;"><?php echo $clave; ?></span>
                </td>
                <td data-colname="Estado" data-value="<?php echo $has_file ? '1' : '0'; ?>">
                    <?php if ($has_file) : ?>
                        <span class="dashicons dashicons-yes" style="color: #46b450;"></span> <strong style="color:#46b450">Listo</strong>
                    <?php else : ?>
                        <span class="dashicons dashicons-warning" style="color: #ffb900;"></span> Pendiente
                    <?php endif; ?>
                </td>
                <td data-colname="Formato">
                    <?php if ($has_file) : ?><code style="font-size:10px;">.geojson</code><?php else : ?>—<?php endif; ?>
                </td>
                <td data-colname="Detalles">
                    <?php if ($has_file) : ?>
                        <strong><?php echo $archivo->anio_reporte; ?></strong>
                        <div class="description" style="font-size:10px;">Corte: <?php echo date_i18n('d/M/y', strtotime($archivo->fecha_corte)); ?></div>
                    <?php else : ?>—<?php endif; ?>
                </td>
                <td data-colname="Acciones">
                    <?php if ($has_file) : ?>
                        <button type="button" class="button button-small copy-url-btn" data-url="<?php echo esc_url(SIARHE_UPLOAD_URL . $archivo->ruta_archivo); ?>" title="Copiar URL">
                            <span class="dashicons dashicons-admin-links"></span> URL
                        </button>
                        <button type="button" class="button button-small edit-meta-btn" 
                                data-id="<?php echo $archivo->id; ?>" data-nombre="<?php echo $nombre; ?>" 
                                data-anio="<?php echo $archivo->anio_reporte; ?>" data-corte="<?php echo $archivo->fecha_corte; ?>" 
                                data-ref="<?php echo esc_attr($archivo->referencia_bibliografica); ?>" data-notes="<?php echo esc_attr($archivo->comentarios); ?>" 
                                title="Ver Info / Editar">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <a href="<?php echo esc_url(SIARHE_UPLOAD_URL . $archivo->ruta_archivo); ?>" target="_blank" class="button button-small" title="Descargar">
                            <span class="dashicons dashicons-download"></span>
                        </a>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;" onsubmit="return confirm('⚠️ ¿Borrar mapa?');">
                            <input type="hidden" name="action" value="siarhe_delete_geojson">
                            <input type="hidden" name="file_id" value="<?php echo $archivo->id; ?>">
                            <?php wp_nonce_field( 'siarhe_delete_nonce_' . $archivo->id ); ?>
                            <button type="submit" class="button button-small button-link-delete" title="Eliminar"><span class="dashicons dashicons-trash" style="color: #a00;"></span></button>
                        </form>
                    <?php else : ?><span class="description">Sin archivo</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="siarhe-edit-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content">
        <h2 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            Editar Metadatos: <span id="modal-entidad-name" style="color: #2271b1;"></span>
        </h2>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="siarhe_update_geojson_meta">
            <input type="hidden" name="file_id" id="modal-file-id">
            <?php wp_nonce_field( 'siarhe_update_meta_nonce', 'siarhe_meta_nonce' ); ?>

            <table class="form-table">
                <tr><th><label>Año</label></th><td><input type="number" name="anio_reporte" id="modal-anio" class="regular-text" required></td></tr>
                <tr><th><label>Corte</label></th><td><input type="date" name="fecha_corte" id="modal-corte" class="regular-text" required></td></tr>
                <tr><th><label>Referencia</label></th><td><textarea name="referencia" id="modal-ref" rows="3" class="large-text"></textarea></td></tr>
                <tr><th><label>Comentarios</label></th><td><textarea name="comentarios" id="modal-notes" rows="3" class="large-text"></textarea></td></tr>
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
    
    // ------------------------------------------------------
    // 1. LÓGICA DE ACORDEÓN MÓVIL (NUEVO)
    // ------------------------------------------------------
    const rows = document.querySelectorAll('.sortable-table tbody tr');
    
    rows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Solo activar en vista móvil (<= 767px)
            if (window.innerWidth > 767) return;

            // Si el clic fue en un botón o enlace dentro de la fila, NO cerrar el acordeón
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) {
                return;
            }

            // Toggle clase para abrir/cerrar
            this.classList.toggle('is-open');
        });
    });

    // ------------------------------------------------------
    // 2. MODALES Y OTROS (CÓDIGO EXISTENTE)
    // ------------------------------------------------------
    const modal = document.getElementById('siarhe-edit-modal');
    const closeBtn = document.getElementById('close-modal-btn');
    
    document.querySelectorAll('.edit-meta-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Evitar que el acordeón se cierre al dar clic en editar
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
    window.onclick = function(event) { if (event.target == modal) { modal.style.display = 'none'; } }

    document.querySelectorAll('.copy-url-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Evitar cerrar acordeón
            const url = this.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(() => {
                const originalHtml = this.innerHTML;
                this.innerHTML = '<span class="dashicons dashicons-yes"></span>';
                setTimeout(() => { this.innerHTML = originalHtml; }, 1500);
            });
        });
    });

    // Lógica de Ordenamiento (Se mantiene igual)
    const table = document.querySelector('.sortable-table');
    const headers = table.querySelectorAll('th.sortable');
    const tbody = table.querySelector('#geo-table-body');
    headers.forEach(th => {
        th.addEventListener('click', () => {
            const type = th.dataset.sort;
            const colIndex = Array.prototype.indexOf.call(th.parentNode.children, th);
            const isAsc = !th.classList.contains('asc');
            headers.forEach(h => { h.classList.remove('asc', 'desc'); h.querySelector('.dashicons').className = 'dashicons dashicons-sort'; });
            th.classList.toggle('asc', isAsc); th.classList.toggle('desc', !isAsc);
            th.querySelector('.dashicons').className = isAsc ? 'dashicons dashicons-arrow-up' : 'dashicons dashicons-arrow-down';
            const rowsArr = Array.from(tbody.querySelectorAll('tr'));
            rowsArr.sort((rowA, rowB) => {
                const cellA = rowA.children[colIndex].dataset.value || rowA.children[colIndex].textContent.trim();
                const cellB = rowB.children[colIndex].dataset.value || rowB.children[colIndex].textContent.trim();
                return (type === 'int') ? (isAsc ? parseInt(cellA) - parseInt(cellB) : parseInt(cellB) - parseInt(cellA)) : (isAsc ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA));
            });
            rowsArr.forEach(row => tbody.appendChild(row));
        });
    });
});
</script>