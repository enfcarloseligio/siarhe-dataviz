<?php // /admin/partials/upload-tabs/_uploader.php
/**
 * Plantilla unica de carga para Geolocalizacion, Localidades y Bases Estaticas.
 * Cada pestana (tab-geojson.php, tab-localidades.php, tab-static.php) solo declara
 * $siarhe_up (textos, nonces, acciones y tipo_archivo) y la incluye.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$u = $siarhe_up;
$k = $u['key'];

// 1. Obtener Entidades (Helper Centralizado)
$entidades_data = siarhe_get_entities();

// 2. Obtener los archivos activos de este tipo desde la base de datos
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$existing_files = $wpdb->get_results( 
    $wpdb->prepare( "SELECT * FROM $table_assets WHERE tipo_archivo = %s AND es_activo = 1", $u['tipo'] )
);

// 3. Indexar resultados por slug para acceso rápido
$files_by_slug = [];
foreach ($existing_files as $file) {
    $files_by_slug[$file->entidad_slug] = $file;
}

// 4. Directorio base para comprobaciones de integridad física
$upload_base_dir = defined('SIARHE_UPLOAD_DIR') ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/';

// 5. Gestión de Notificaciones (Feedback Transaccional)
Siarhe_Admin::render_flash_notice();
if ( isset($_GET['status']) ) {
    if ( $_GET['status'] == 'success' ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $u['msg_ok'] ) . '</p></div>';
    if ( $_GET['status'] == 'updated' ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $u['msg_updated'] ) . '</p></div>';
    if ( $_GET['status'] == 'deleted' ) echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $u['msg_deleted'] ) . '</p></div>';
}
?>

<?php Siarhe_Admin::render_select2_css(); ?>

<div class="siarhe-card siarhe-upload-card">
    <h2><?php echo esc_html( $u['title'] ); ?><?php Siarhe_Admin::help( $u['title_help'] ); ?></h2>
    
    <div class="notice notice-info inline">
        <p><strong>Política de Archivos Únicos:</strong></p>
        <ul>
            <?php foreach ( $u['info'] as $item ) : ?>
                <li><?php echo wp_kses( $item, array( 'strong' => array(), 'code' => array() ) ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr( $u['action_upload'] ); ?>">
        <?php wp_nonce_field( $u['nonce_upload'], 'siarhe_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="entidad_slug"><?php echo esc_html( $u['entity_label'] ); ?></label><?php Siarhe_Admin::help( $u['entity_help'] ); ?></th>
                <td class="siarhe-field-narrow">
                    <select name="entidad_slug" id="entidad_slug" class="siarhe-searchable-select" required>
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
                <th scope="row"><label for="siarhe_file"><?php echo esc_html( $u['file_label'] ); ?></label><?php Siarhe_Admin::help( $u['file_help'] ); ?></th>
                <td>
                    <input type="file" data-check="<?php echo esc_attr( $u['check'] ); ?>" name="siarhe_file" id="siarhe_file" accept="<?php echo esc_attr( $u['accept'] ); ?>" required>
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
                    <textarea name="referencia" id="referencia" rows="2" class="large-text" placeholder="<?php echo esc_attr( $u['ref_placeholder'] ); ?>"></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="comentarios">Comentarios Internos</label><?php Siarhe_Admin::help('Notas para el equipo administrador. No se muestran en el sitio publico.'); ?></th>
                <td>
                    <textarea name="comentarios" id="comentarios" rows="2" class="large-text" placeholder="<?php echo esc_attr( $u['notes_placeholder'] ); ?>"></textarea>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Subir / Reemplazar Archivo">
        </p>
    </form>
</div>

<div class="siarhe-card siarhe-card--flush">
    <h2 class="siarhe-card__title"><?php echo esc_html( $u['table_title'] ); ?><?php Siarhe_Admin::help( $u['table_help'] ); ?></h2>
    
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
            <input type="text" id="siarhe-search-<?php echo esc_attr( $k ); ?>" placeholder="Buscar entidad, archivo o estado...">
        </div>
    </div>
    
    <table id="siarhe-<?php echo esc_attr( $k ); ?>-table" class="siarhe-table">
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
        <tbody id="siarhe-<?php echo esc_attr( $k ); ?>-tbody">
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
                        <span class="siarhe-status siarhe-status--ok"><span class="dashicons dashicons-<?php echo esc_attr( $u['status_icon'] ); ?>"></span><?php echo esc_html( $u['status_ok'] ); ?></span>
                    <?php else : ?>
                        <span class="siarhe-status siarhe-status--off"><span class="dashicons dashicons-minus"></span><?php echo esc_html( $u['status_off'] ); ?></span>
                    <?php endif; ?>
                </td>

                <td data-label="Archivo del Sistema">
                    <?php if ($exists) : ?>
                        <code class="siarhe-code-sm"><?php echo esc_html(basename($archivo->ruta_archivo)); ?></code>
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
                        <div class="siarhe-audit">
                            <span class="siarhe-audit__label">Subido por:</span><br>
                            <span class="siarhe-audit__who">
                                <?php echo esc_html($autor_original); ?>
                            </span><br>
                            <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr($fecha_original); ?>">
                                <?php echo esc_html($fecha_original); ?>
                            </span>
                        </div>

                        <?php if (!empty($archivo->fecha_modificacion) && $archivo->fecha_modificacion !== $fecha_original) : ?>
                            <div class="siarhe-audit">
                                <span class="siarhe-audit__label siarhe-audit__label--edit">Última edición:</span><br>
                                <span class="siarhe-audit__who">
                                    <?php echo esc_html($archivo->modificado_por ?: 'Sistema'); ?>
                                </span><br>
                                <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr($archivo->fecha_modificacion); ?>">
                                    <?php echo esc_html($archivo->fecha_modificacion); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($exists) : ?>
                        <div class="siarhe-audit">
                            <span class="siarhe-audit__label">Subido por:</span><br>
                            <span class="siarhe-audit__who">Sistema (Vía FTP/Cpanel)</span><br>
                            <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr(date("Y-m-d H:i:s", filemtime($row['ruta_fisica']))); ?>">
                                <?php echo esc_html(date("Y-m-d H:i:s", filemtime($row['ruta_fisica']))); ?>
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
                    <div class="siarhe-row-actions">
                    <?php if ($exists) : 
                        // 🌟 CACHE BUSTING: Fuerza al navegador a usar la versión más reciente basándose en el archivo físico
                        $cache_version = filemtime($row['ruta_fisica']);
                        $url_con_version = SIARHE_UPLOAD_URL . ltrim($archivo->ruta_archivo, '/') . '?v=' . $cache_version;
                    ?>
                        <button type="button" class="button button-small" 
                                data-copy="<?php echo esc_url($url_con_version); ?>" title="Copiar Enlace al Archivo">
                            <span class="dashicons dashicons-admin-links"></span>
                        </button>

                        <?php if ($archivo) : ?>
                        <button type="button" class="button button-small edit-meta-btn" 
                                data-id="<?php echo absint( $archivo->id ); ?>" 
                                data-nombre="<?php echo esc_attr($nombre); ?>" 
                                data-anio="<?php echo esc_attr($archivo->anio_reporte); ?>" 
                                data-corte="<?php echo esc_attr($archivo->fecha_corte); ?>" 
                                data-ref="<?php echo esc_attr($archivo->referencia_bibliografica); ?>" 
                                data-notes="<?php echo esc_attr($archivo->comentarios); ?>"
                                title="Ver Información / Editar Metadatos">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <?php endif; ?>

                        <a href="<?php echo esc_url($url_con_version); ?>" target="_blank" class="button button-small" title="Descargar Archivo Físico">
                            <span class="dashicons dashicons-download"></span>
                        </a>

                        <?php if ($archivo) : ?>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" data-confirm="<?php echo esc_attr( $u['delete_confirm'] ); ?>">
                            <input type="hidden" name="action" value="<?php echo esc_attr( $u['action_delete'] ); ?>">
                            <input type="hidden" name="file_id" value="<?php echo absint( $archivo->id ); ?>">
                            <?php wp_nonce_field( $u['nonce_delete'] . $archivo->id ); ?>
                            <button type="submit" class="button button-small button-danger" title="<?php echo esc_attr( $u['delete_title'] ); ?>"><span class="dashicons dashicons-trash"></span></button>
                        </form>
                        <?php endif; ?>
                    <?php else : ?><span class="description">—</span><?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="siarhe-<?php echo esc_attr( $k ); ?>-empty" style="display: none;">
                <td colspan="8" class="siarhe-empty-cell">No se encontraron resultados para su búsqueda.</td>
            </tr>
        </tbody>
    </table>

    <div class="siarhe-pagination">
        <div id="siarhe-<?php echo esc_attr( $k ); ?>-count" class="siarhe-count"></div>
        <div class="siarhe-page-numbers" id="siarhe-pagination-controls-bottom"></div>
    </div>
</div>

<div id="siarhe-edit-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content">
        <h2 class="siarhe-modal-title">
            <?php echo esc_html( $u['modal_title'] ); ?>: <span id="modal-entidad-name"></span>
        </h2>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr( $u['action_meta'] ); ?>">
            <input type="hidden" name="file_id" id="modal-file-id">
            <?php wp_nonce_field( $u['nonce_meta'], 'siarhe_meta_nonce' ); ?>

            <table class="form-table">
                <tr><th><label>Año de los Datos</label><?php Siarhe_Admin::help('Anio al que corresponden los datos. Se muestra en las referencias del mapa.'); ?></th><td><input type="number" name="anio_reporte" id="modal-anio" class="regular-text" required></td></tr>
                <tr><th><label>Fecha de Corte</label><?php Siarhe_Admin::help('Fecha hasta la cual el archivo contiene informacion. Se muestra al usuario como vigencia de los datos.'); ?></th><td><input type="date" name="fecha_corte" id="modal-corte" class="regular-text" required></td></tr>
                <tr><th><label>Fuente / Referencia</label><?php Siarhe_Admin::help('Origen de la informacion (institucion, documento, version). Aparece como referencia bibliografica.'); ?></th><td><textarea name="referencia" id="modal-ref" rows="3" class="large-text"></textarea></td></tr>
                <tr><th><label>Comentarios Internos</label><?php Siarhe_Admin::help('Notas para el equipo administrador. No se muestran en el sitio publico.'); ?></th><td><textarea name="comentarios" id="modal-notes" rows="3" class="large-text"></textarea></td></tr>
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

    // Lógica del Modal de Edición
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
            search:  document.getElementById('siarhe-search-<?php echo esc_js( $k ); ?>'),
            perPage: document.getElementById('siarhe-items-per-page'),
            pagers:  [document.getElementById('siarhe-pagination-controls-top'), document.getElementById('siarhe-pagination-controls-bottom')],
            count:   document.getElementById('siarhe-<?php echo esc_js( $k ); ?>-count'),
            empty:   document.getElementById('siarhe-<?php echo esc_js( $k ); ?>-empty')
        });
    }
});
</script>