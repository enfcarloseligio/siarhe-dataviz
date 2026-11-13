<?php // /admin/partials/upload-tabs/_uploader.php
/**
 * Plantilla de carga de geometrias: Geolocalizacion (mapa base) y Localidades (capa detallada).
 * Cada pestaña (tab-geojson.php, tab-localidades.php) solo declara $siarhe_up (textos, nonces, acciones y tipo_archivo)
 * y la incluye. Hay un archivo por entidad y marco geoestadistico quinquenal (Siarhe_Geo::marcos()).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$u = $siarhe_up;
$k = $u['key'];

// 1. Entidades en el orden del panel: Extranjero, República Mexicana y las entidades por su clave
$entidades_data = siarhe_get_entities_ordered();

// 2. Obtener los archivos activos de este tipo desde la base de datos (del marco mas reciente al mas antiguo)
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$existing_files = $wpdb->get_results(
    $wpdb->prepare( "SELECT * FROM $table_assets WHERE tipo_archivo = %s AND es_activo = 1 ORDER BY anio_reporte DESC, id DESC", $u['tipo'] )
);

// 3. Indexar resultados por entidad y marco (un archivo por combinacion)
$files_by_slug = [];
$marcos_cargados = [];
foreach ($existing_files as $file) {
    $marco_file = (int) $file->anio_reporte;
    if ( isset( $files_by_slug[$file->entidad_slug][$marco_file] ) ) continue;
    $files_by_slug[$file->entidad_slug][$marco_file] = $file;
    $marcos_cargados[$marco_file] = true;
}
krsort( $marcos_cargados );
$marcos_disponibles = Siarhe_Geo::marcos();

// La tabla abre en el marco más reciente que tiene al menos un archivo; sin archivos, en el marco vigente
$marcos_tabla = $marcos_cargados ? array_keys( $marcos_cargados ) : array( (int) $marcos_disponibles[0] );
$marco_inicial = (int) $marcos_tabla[0];

// 4. Directorio base para comprobaciones de integridad física
$upload_base_dir = defined('SIARHE_UPLOAD_DIR') ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/';

// 5. Gestión de Notificaciones (Feedback Transaccional)
Siarhe_Admin::render_flash_notice();
Siarhe_Admin::render_validation_result();
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
        <p><strong>Cómo funcionan los archivos:</strong></p>
        <ul>
            <?php foreach ( $u['info'] as $item ) : ?>
                <li><?php echo wp_kses( $item, array( 'strong' => array(), 'code' => array() ) ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php if ( ! empty( $u['columns'] ) ) Siarhe_Admin::render_columns_downloads( $u['columns'] ); ?>

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
                <th scope="row"><label for="anio_reporte">Marco Geoestadístico</label><?php Siarhe_Admin::help('Quinquenio del Marco Geoestadístico del INEGI. Cada marco sirve a los cinco años que empiezan en el suyo (2025 dibuja los datos de 2025 a 2029). Un marco publicado entre dos quinquenios, como el de 2016, se carga en el anterior (2015) y su fecha real se anota en la fecha de corte.'); ?></th>
                <td>
                    <select name="anio_reporte" id="anio_reporte" required>
                        <?php foreach ( $marcos_disponibles as $i_marco => $marco_op ) : ?>
                            <option value="<?php echo (int) $marco_op; ?>"<?php selected( $i_marco, 0 ); ?>><?php echo (int) $marco_op; ?> (datos de <?php echo (int) $marco_op; ?> a <?php echo (int) $marco_op + 4; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fecha_corte">Fecha de Corte</label><?php Siarhe_Admin::help('Fecha de corte o de publicación del marco geoestadístico. Se muestra al usuario como vigencia de los datos espaciales.'); ?></th>
                <td>
                    <input type="date" name="fecha_corte" id="fecha_corte" required>
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
                <select id="siarhe-items-per-page" class="siarhe-select-sm">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="all">Todos</option>
                </select> 
                registros
            </label>
            <label>
                Marco
                <select id="siarhe-filter-marco" class="siarhe-select-sm">
                    <?php foreach ( $marcos_tabla as $marco_op ) : ?><option value="<?php echo (int) $marco_op; ?>"<?php selected( (int) $marco_op, $marco_inicial ); ?>><?php echo (int) $marco_op; ?></option><?php endforeach; ?>
                    <option value="">Todos</option>
                </select>
            </label>
            <?php Siarhe_Admin::render_validate_button( $k ); ?>
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
                <th style="width: 18%;" data-sort="text">Entidad</th>
                <th style="width: 5%;" data-sort="num">CVE</th>
                <th style="width: 7%;" data-sort="num">Marco</th>
                <th style="width: 10%;" data-sort="text">Estado</th>
                <th style="width: 15%;" data-sort="text">Archivo del Sistema</th>
                <th style="width: 10%;" data-sort="num">Fecha de Corte</th>
                <th style="width: 15%;" data-sort="num">Auditoría</th>
                <th style="width: 5%;" data-sort="num">Tamaño</th>
                <th style="width: 15%;">Acciones</th>
            </tr>
        </thead>
        <tbody id="siarhe-<?php echo esc_attr( $k ); ?>-tbody">
            <?php 
            $rows = [];
            $upload_base = defined('SIARHE_UPLOAD_DIR') ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/';

            // En cada marco de la tabla, una fila por entidad (con archivo o sin él, para ver lo que falta), en el orden del panel
            foreach ($marcos_tabla as $marco_fila) {
                foreach ($entidades_data as $slug => $data) {
                    $archivo = isset($files_by_slug[$slug][$marco_fila]) ? $files_by_slug[$slug][$marco_fila] : null;
                    $ruta_fisica = $archivo ? $upload_base . ltrim($archivo->ruta_archivo, '/') : '';
                    $rows[] = [
                        'slug' => $slug,
                        'nombre' => $data['nombre'],
                        'cve_ent' => $data['CVE_ENT'],
                        'marco' => (int) $marco_fila,
                        'archivo' => $archivo,
                        'existe_fisico' => $archivo && file_exists($ruta_fisica),
                        'ruta_fisica' => $ruta_fisica,
                        'sort_key' => siarhe_entity_order( $slug )
                    ];
                }
            }
            // Archivos de entidades que ya no están en el catálogo: se listan al final para poder eliminarlos
            foreach ($files_by_slug as $slug => $por_marco) {
                if ( isset( $entidades_data[$slug] ) ) continue;
                foreach ($por_marco as $marco_fila => $archivo) {
                    $ruta_fisica = $upload_base . ltrim($archivo->ruta_archivo, '/');
                    $rows[] = [
                        'slug' => $slug, 'nombre' => $slug . ' (entidad retirada)', 'cve_ent' => '—', 'marco' => (int) $marco_fila,
                        'archivo' => $archivo, 'existe_fisico' => file_exists($ruta_fisica), 'ruta_fisica' => $ruta_fisica, 'sort_key' => 1000
                    ];
                }
            }
            usort($rows, function($a, $b) { return [ -$a['marco'], $a['sort_key'] ] <=> [ -$b['marco'], $b['sort_key'] ]; });

            foreach ($rows as $row) : 
                $slug = $row['slug']; 
                $nombre = $row['nombre']; 
                $cve_ent = $row['cve_ent'];
                $archivo = $row['archivo']; 
                $exists = $row['existe_fisico'];
            ?>
            <tr class="siarhe-data-row" data-marco="<?php echo $row['marco'] ? (int) $row['marco'] : ''; ?>">
                <td data-label="Entidad" data-mobile-role="primary">
                    <strong><?php echo esc_html($nombre); ?></strong>
                </td>

                <td data-label="CVE">
                    <span class="siarhe-badge neutral"><?php echo esc_html($cve_ent); ?></span>
                </td>

                <td data-label="Marco" data-sort-value="<?php echo (int) $row['marco']; ?>">
                    <?php echo $row['marco'] ? (int) $row['marco'] : '—'; ?>
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

                <td data-label="Fecha de Corte" data-sort-value="<?php echo ( $archivo && $archivo->fecha_corte ) ? (int) strtotime( $archivo->fecha_corte ) : 0; ?>">
                    <?php if ($archivo && $archivo->fecha_corte) : ?>
                        <?php echo date_i18n('d/m/Y', strtotime($archivo->fecha_corte)); ?>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <td data-label="Auditoría" data-sort-value="<?php $ts_aud = 0; if ( $archivo ) { $f_aud = ! empty( $archivo->fecha_modificacion ) ? $archivo->fecha_modificacion : ( $archivo->fecha_subida ?? ( $archivo->fecha_creacion ?? ( $archivo->fecha_registro ?? '' ) ) ); $ts_aud = (int) strtotime( $f_aud ); } elseif ( $exists ) { $ts_aud = (int) filemtime( $row['ruta_fisica'] ); } echo $ts_aud; ?>">
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
                        <span class="siarhe-text-faint">—</span>
                    <?php endif; ?>
                </td>

                <td data-label="Tamaño" data-sort-value="<?php echo $exists ? (int) filesize( $row['ruta_fisica'] ) : 0; ?>">
                    <?php if ($exists) : ?>
                        <?php echo size_format(filesize($row['ruta_fisica'])); ?>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <td data-label="Acciones">
                    <div class="siarhe-row-actions">
                    <?php if (!$exists && $archivo) : // registro cuyo archivo ya no está en el servidor: solo se puede eliminar ?>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" data-confirm="<?php echo esc_attr( $u['delete_confirm'] ); ?>">
                            <input type="hidden" name="action" value="<?php echo esc_attr( $u['action_delete'] ); ?>">
                            <input type="hidden" name="file_id" value="<?php echo absint( $archivo->id ); ?>">
                            <?php wp_nonce_field( $u['nonce_delete'] . $archivo->id ); ?>
                            <button type="submit" class="button button-small button-danger" title="<?php echo esc_attr( $u['delete_title'] ); ?>"><span class="dashicons dashicons-trash"></span></button>
                        </form>
                    <?php elseif ($exists) :
                        // La fecha del archivo va en el enlace para que el navegador no use una copia anterior
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
                                data-nombre="<?php echo esc_attr( $nombre . ' · marco ' . $row['marco'] ); ?>"  
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
                <td colspan="9" class="siarhe-empty-cell">No se encontraron resultados para su búsqueda.</td>
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
                <tr><th><label>Marco Geoestadístico</label><?php Siarhe_Admin::help('El marco identifica al archivo: para cambiarlo, súbelo de nuevo en el marco correcto y elimina este.'); ?></th><td><input type="number" id="modal-anio" class="regular-text" readonly></td></tr>
                <tr><th><label>Fecha de Corte</label><?php Siarhe_Admin::help('Fecha de corte o de publicación del marco geoestadístico. Se muestra al usuario como vigencia de los datos espaciales.'); ?></th><td><input type="date" name="fecha_corte" id="modal-corte" class="regular-text" required></td></tr>
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
            filters: [
                { el: document.getElementById('siarhe-filter-marco'), match: function(row, v) { return row.dataset.marco === v; } }
            ],
            pagers:  [document.getElementById('siarhe-pagination-controls-top'), document.getElementById('siarhe-pagination-controls-bottom')],
            table:   document.getElementById('siarhe-<?php echo esc_js( $k ); ?>-table'),
            count:   document.getElementById('siarhe-<?php echo esc_js( $k ); ?>-count'),
            empty:   document.getElementById('siarhe-<?php echo esc_js( $k ); ?>-empty')
        });
    }
});
</script>