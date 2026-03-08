<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 🌟 1. GESTOR DINÁMICO DE DICCIONARIO DE ARCHIVOS
$archivos_json = get_option( 'siarhe_archivos_marcadores', '' );
$tipos_marcadores = json_decode( wp_unslash( $archivos_json ), true );

// Auto-corrector por si la base de datos se corrompió con el error "u00ed" en las pruebas anteriores
if ( empty($tipos_marcadores) || !is_array($tipos_marcadores) || (isset($tipos_marcadores['CATETER']['label']) && strpos($tipos_marcadores['CATETER']['label'], 'u00ed') !== false) ) {
    $tipos_marcadores = [
        'CATETER' => [ 'label' => 'Clínicas de Catéteres', 'filename' => 'clinicas-cateteres.csv', 'is_core' => true ],
        'HERIDAS' => [ 'label' => 'Clínicas de Heridas', 'filename' => 'clinicas-heridas.csv', 'is_core' => true ],
        'ESTABLECIMIENTOS' => [ 'label' => 'Establecimientos de Salud (Todas)', 'filename' => 'establecimientos-salud.csv', 'is_core' => true ]
    ];
    // JSON_UNESCAPED_UNICODE Evita que los acentos se rompan al guardar
    update_option( 'siarhe_archivos_marcadores', wp_json_encode($tipos_marcadores, JSON_UNESCAPED_UNICODE) );
}

// Procesar agregado/eliminación de ranuras (vía POST local)
if ( isset($_POST['action']) ) {
    if ( $_POST['action'] === 'add_archivo_marcador' && isset($_POST['new_file_key']) ) {
        $new_key = sanitize_text_field(strtoupper(str_replace(' ', '_', $_POST['new_file_key'])));
        $new_label = sanitize_text_field($_POST['new_file_label']);
        $new_filename = sanitize_file_name(strtolower(str_replace(' ', '-', $_POST['new_file_name'])));
        if (strpos($new_filename, '.csv') === false) $new_filename .= '.csv';
        
        if (!empty($new_key) && !isset($tipos_marcadores[$new_key])) {
            $tipos_marcadores[$new_key] = [ 'label' => $new_label, 'filename' => $new_filename, 'is_core' => false ];
            update_option( 'siarhe_archivos_marcadores', wp_json_encode($tipos_marcadores, JSON_UNESCAPED_UNICODE) );
            echo '<div class="notice notice-success is-dismissible"><p>Nueva variable agregada. Ya puedes subir su archivo CSV.</p></div>';
        }
    }
    
    if ( $_POST['action'] === 'delete_archivo_marcador' && isset($_POST['del_file_key']) ) {
        $del_key = sanitize_text_field($_POST['del_file_key']);
        if (isset($tipos_marcadores[$del_key]) && empty($tipos_marcadores[$del_key]['is_core'])) {
            unset($tipos_marcadores[$del_key]);
            update_option( 'siarhe_archivos_marcadores', wp_json_encode($tipos_marcadores, JSON_UNESCAPED_UNICODE) );
            echo '<div class="notice notice-success is-dismissible"><p>Variable eliminada exitosamente.</p></div>';
        }
    }
}

// 2. Obtener metadatos existentes de la BD
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$existing_files = $wpdb->get_results( 
    $wpdb->prepare( "SELECT * FROM $table_assets WHERE tipo_archivo = %s AND es_activo = 1", 'marcador' )
);

$files_by_type = [];
foreach ($existing_files as $file) {
    $files_by_type[$file->entidad_slug] = $file;
}

// Directorio base para comprobaciones físicas
$upload_base_dir = (defined('SIARHE_UPLOAD_DIR') ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/') . 'markers/';

// Mensajes de estado
if ( isset($_GET['status']) ) {
    if ( $_GET['status'] == 'success' ) echo '<div class="notice notice-success is-dismissible"><p>Marcador actualizado correctamente.</p></div>';
    if ( $_GET['status'] == 'updated' ) echo '<div class="notice notice-success is-dismissible"><p>Metadatos actualizados.</p></div>';
}

// 🌟 Función auxiliar para mostrar la fecha de modificación con el formato bonito que pediste
function format_custom_date($db_date) {
    if (!$db_date) return '—';
    $timestamp = strtotime($db_date);
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $mes = $meses[date('n', $timestamp) - 1];
    $hora = date('h:i a', $timestamp);
    $hora = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $hora);
    return date('j', $timestamp) . ' ' . $mes . ' ' . date('Y', $timestamp) . ', ' . $hora;
}
?>

<div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
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

<div class="card" style="max-width: 100%; padding: 0; margin-bottom: 20px;">
    <h2 style="padding: 15px; margin: 0; border-bottom: 1px solid #eee;">Archivos en el Servidor</h2>
    <table id="siarhe-marcadores-table" class="siarhe-table">
        <thead>
            <tr>
                <th style="width: 20%">Marcador</th>
                <th style="width: 15%">Estado</th>
                <th style="width: 20%">Archivo Sistema</th>
                <th style="width: 10%">Fecha de Corte</th>
                <th style="width: 15%">Última Modificación</th>
                <th style="width: 5%">Tamaño</th>
                <th style="width: 15%">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tipos_marcadores as $key => $info) : 
                $db_file = isset($files_by_type[$key]) ? $files_by_type[$key] : null;
                
                $filename = $info['filename'];
                $ruta_fisica = $upload_base_dir . $filename;
                $existe_fisico = file_exists($ruta_fisica);
                
                $url_publica = defined('SIARHE_UPLOAD_URL') ? SIARHE_UPLOAD_URL . 'markers/' . $filename : '';
            ?>
            <tr>
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
                
                <td data-label="Última Modificación">
                    <?php if ($db_file && !empty($db_file->fecha_modificacion)) : ?>
                        <span style="color:#64748b; font-size:12px;">Por: <?php echo esc_html($db_file->modificado_por ?: 'Sistema'); ?></span><br>
                        <?php echo format_custom_date($db_file->fecha_modificacion); ?>
                    <?php elseif ($existe_fisico) : ?>
                        <span style="color:#64748b; font-size:12px;">Por: Sistema</span><br>
                        <?php echo format_custom_date(date("Y-m-d H:i:s", filemtime($ruta_fisica))); ?>
                    <?php else : ?>—<?php endif; ?>
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
        </tbody>
    </table>
</div>

<div class="card" style="max-width: 100%; padding: 20px;">
    <h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">➕ Incorporar Nueva Variable (CSV)</h3>
    <p class="description">Agrega una nueva ranura de archivo para cargar marcadores adicionales (ej. Casos Epidemiológicos, Unidades Móviles).</p>
    
    <form method="post" style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap; margin-top:15px;">
        <input type="hidden" name="action" value="add_archivo_marcador">
        <div>
            <label>Clave Interna (Sin espacios):</label><br>
            <input type="text" name="new_file_key" placeholder="Ej: CASOS_DENGUE" required class="regular-text">
        </div>
        <div>
            <label>Etiqueta Pública:</label><br>
            <input type="text" name="new_file_label" placeholder="Ej: Casos de Dengue 2026" required class="regular-text">
        </div>
        <div>
            <label>Nombre del Archivo (con .csv):</label><br>
            <input type="text" name="new_file_name" placeholder="ej: casos-dengue.csv" required class="regular-text">
        </div>
        <div>
            <button type="submit" class="button button-secondary">Agregar Variable</button>
        </div>
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
    const table = document.getElementById('siarhe-marcadores-table');
    if(table) {
        table.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('click', function(e) {
                if (window.innerWidth > 767) return;
                if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
                this.classList.toggle('is-open');
            });
        });
    }

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
        window.onclick = function(event) { if (event.target == modal) { modal.style.display = 'none'; } }
    }

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
});
</script>