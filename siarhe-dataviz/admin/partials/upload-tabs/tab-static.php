<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener Entidades desde el Helper Centralizado
$entidades_data = siarhe_get_entities();

// 2. Obtener archivos CSV existentes (Filtro: static_min)
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$existing_files = $wpdb->get_results( 
    $wpdb->prepare( "SELECT * FROM $table_assets WHERE tipo_archivo = %s AND es_activo = 1", 'static_min' )
);

// Indexar para búsqueda rápida por slug
$files_by_slug = [];
foreach ($existing_files as $file) {
    $files_by_slug[$file->entidad_slug] = $file;
}

// Directorio base para comprobaciones físicas
$upload_base_dir = defined('SIARHE_UPLOAD_DIR') ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/';

// Mensajes de estado
if ( isset($_GET['status']) ) {
    if ( $_GET['status'] == 'success' ) echo '<div class="notice notice-success is-dismissible"><p>Base estática cargada y actualizada correctamente.</p></div>';
    if ( $_GET['status'] == 'updated' ) echo '<div class="notice notice-success is-dismissible"><p>Metadatos actualizados.</p></div>';
    if ( $_GET['status'] == 'deleted' ) echo '<div class="notice notice-warning is-dismissible"><p>Archivo eliminado correctamente.</p></div>';
}
?>

<div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <h2>📤 Cargar Base Estática Minificada</h2>
    
    <div class="notice notice-info inline" style="margin: 10px 0 20px 0;">
        <p><strong>Política de Archivos Únicos:</strong></p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li>El sistema mantiene <strong>un solo archivo CSV por entidad</strong> (ej: <code>aguascalientes.csv</code>).</li>
            <li>Al subir un nuevo archivo, <strong>se sobrescribirá el anterior</strong> automáticamente.</li>
            <li>Esto garantiza que los enlaces en la web nunca se rompan, aunque actualices los datos cada año.</li>
        </ul>
    </div>
    
    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="siarhe_upload_static">
        <?php wp_nonce_field( 'siarhe_upload_static_nonce', 'siarhe_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="entidad_slug">Entidad / Alcance</label></th>
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
                <th scope="row"><label for="siarhe_file">Archivo CSV</label></th>
                <td>
                    <input type="file" name="siarhe_file" id="siarhe_file" accept=".csv" required>
                    <p class="description">
                        Se guardará como: <code>{entidad}.csv</code> (sin el año en el nombre).<br>
                        <em>Asegúrate que sea UTF-8 y delimitado por comas.</em>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="anio_reporte">Año de los Datos</label></th>
                <td>
                    <input type="number" name="anio_reporte" placeholder="Ej: 2026" class="small-text" required min="2000" max="2100">
                    <span class="description">Este dato se guarda en la base de datos para referencia.</span>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fecha_corte">Fecha de Corte</label></th>
                <td>
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
                    <textarea name="comentarios" id="comentarios" rows="2" class="large-text" placeholder="Notas sobre la versión, limpieza de datos, etc..."></textarea>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Subir y Reemplazar CSV">
        </p>
    </form>
</div>

<div class="card" style="max-width: 100%; padding: 0;">
    <h2 style="padding: 15px; margin: 0; border-bottom: 1px solid #eee;">Estado de Bases Estáticas</h2>
    
    <table id="siarhe-static-table" class="siarhe-table">
        <thead>
            <tr>
                <th style="width: 20%;">Entidad</th>
                <th style="width: 5%;">CVE</th>
                <th style="width: 10%;">Estado</th>
                <th style="width: 20%;">Archivo Sistema</th>
                <th style="width: 10%;">Fecha de Corte</th>
                <th style="width: 15%;">Última Modificación</th>
                <th style="width: 5%;">Tamaño</th>
                <th style="width: 15%;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            // Ordenar entidades
            $rows = [];
            foreach ($entidades_data as $slug => $data) {
                $nombre = $data['nombre'];
                $cve_ent = $data['CVE_ENT'];
                $archivo = isset($files_by_slug[$slug]) ? $files_by_slug[$slug] : null;
                
                // Comprobación física
                $ruta_fisica = '';
                $existe_fisico = false;
                $file_size = '—';
                $file_mtime = '—';

                if ($archivo) {
                    $rel_path = ltrim($archivo->ruta_archivo, '/');
                    $ruta_fisica = $upload_base_dir . $rel_path;
                    
                    if (file_exists($ruta_fisica)) {
                        $existe_fisico = true;
                        $file_size = size_format(filesize($ruta_fisica));
                        $file_mtime = date("d/m/y H:i", filemtime($ruta_fisica));
                    }
                }
                
                $sort_key = (int)$cve_ent;
                if ($slug == 'republica-mexicana') $sort_key = -1; 
                
                $rows[] = [
                    'slug' => $slug, 
                    'nombre' => $nombre, 
                    'cve_ent' => $cve_ent, 
                    'archivo' => $archivo, 
                    'existe_fisico' => $existe_fisico,
                    'file_size' => $file_size,
                    'file_mtime' => $file_mtime,
                    'sort_key' => $sort_key
                ];
            }
            usort($rows, function($a, $b) { return $a['sort_key'] <=> $b['sort_key']; });

            // Renderizar filas
            foreach ($rows as $row) : 
                $slug = $row['slug']; 
                $nombre = $row['nombre']; 
                $cve_ent = $row['cve_ent'];
                $archivo = $row['archivo']; 
                $exists = $row['existe_fisico'];
            ?>
            <tr>
                <td data-label="Entidad">
                    <strong><?php echo esc_html($nombre); ?></strong>
                </td>
                
                <td data-label="CVE">
                    <span class="siarhe-badge neutral"><?php echo esc_html($cve_ent); ?></span>
                </td>
                
                <td data-label="Estado">
                    <?php if ($exists) : ?>
                        <span class="dashicons dashicons-yes" style="color: #46b450;"></span> <strong style="color:#46b450">Cargado</strong>
                    <?php else : ?>
                        <span class="dashicons dashicons-minus" style="color: #ccc;"></span> - Sin archivo
                    <?php endif; ?>
                </td>

                <td data-label="Archivo Sistema">
                    <?php if ($exists) : ?>
                        <code style="font-size:11px;"><?php echo esc_html(basename($archivo->ruta_archivo)); ?></code>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <td data-label="Fecha de Corte">
                    <?php if ($archivo && $archivo->fecha_corte) : ?>
                        <?php echo date_i18n('d/m/Y', strtotime($archivo->fecha_corte)); ?>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <td data-label="Última Modificación">
                    <?php echo esc_html($row['file_mtime']); ?>
                </td>

                <td data-label="Tamaño">
                    <?php echo esc_html($row['file_size']); ?>
                </td>

                <td data-label="Acciones">
                    <?php if ($exists) : ?>
                        <button type="button" class="button button-small copy-url-btn" 
                                data-url="<?php echo esc_url(SIARHE_UPLOAD_URL . $archivo->ruta_archivo); ?>" title="Copiar Enlace">
                            <span class="dashicons dashicons-admin-links"></span>
                        </button>
                        
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
                        
                        <a href="<?php echo esc_url(SIARHE_UPLOAD_URL . $archivo->ruta_archivo); ?>" target="_blank" class="button button-small" title="Ver/Descargar">
                            <span class="dashicons dashicons-download"></span>
                        </a>

                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;" onsubmit="return confirm('⚠️ ¿Estás seguro de borrar esta base de datos?');">
                            <input type="hidden" name="action" value="siarhe_delete_static">
                            <input type="hidden" name="file_id" value="<?php echo $archivo->id; ?>">
                            <?php wp_nonce_field( 'siarhe_delete_static_nonce_' . $archivo->id ); ?>
                            <button type="submit" class="button button-small button-link-delete" title="Eliminar"><span class="dashicons dashicons-trash" style="color: #a00;"></span></button>
                        </form>
                    <?php else : ?><span class="description">—</span><?php endif; ?>
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
            <input type="hidden" name="action" value="siarhe_update_static_meta">
            <input type="hidden" name="file_id" id="modal-file-id">
            <?php wp_nonce_field( 'siarhe_update_static_meta_nonce', 'siarhe_meta_nonce' ); ?>

            <table class="form-table">
                <tr><th><label>Año de Datos</label></th><td><input type="number" name="anio_reporte" id="modal-anio" class="regular-text" required></td></tr>
                <tr><th><label>Fecha de Corte</label></th><td><input type="date" name="fecha_corte" id="modal-corte" class="regular-text" required></td></tr>
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
    
    // Funcionalidad Modal
    const modal = document.getElementById('siarhe-edit-modal');
    const closeBtn = document.getElementById('close-modal-btn');
    
    if(modal) {
        document.querySelectorAll('.edit-meta-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                // Llenar datos en el modal
                document.getElementById('modal-entidad-name').textContent = this.dataset.nombre;
                document.getElementById('modal-file-id').value = this.dataset.id;
                document.getElementById('modal-anio').value = this.dataset.anio;
                document.getElementById('modal-corte').value = this.dataset.corte;
                document.getElementById('modal-ref').value = this.dataset.ref;
                document.getElementById('modal-notes').value = this.dataset.notes;
                
                modal.style.display = 'block';
            });
        });

        // Cerrar Modal
        closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
        window.onclick = function(event) { if (event.target == modal) { modal.style.display = 'none'; } }
    }

    // Funcionalidad Copiar URL
    document.querySelectorAll('.copy-url-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            const url = this.getAttribute('data-url');
            navigator.clipboard.writeText(url).then(() => {
                const original = this.innerHTML;
                this.innerHTML = '<span class="dashicons dashicons-yes" style="color:green"></span>';
                setTimeout(() => { this.innerHTML = original; }, 1500);
            });
        });
    });
});
</script>