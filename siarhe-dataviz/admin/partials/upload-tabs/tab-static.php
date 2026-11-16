<?php // /admin/partials/upload-tabs/tab-static.php
/**
 * Carga de bases estaticas. Cada base es una combinacion entidad + año + tema + unidad de conteo y puede subirse como CSV o
 * como JSON optimizado (el que genera RHEMX): al subir uno, el plugin crea el otro, asi que siempre existen los dos y el mapa
 * lee el JSON. Los temas del sistema son Enfermeria (plazas y recursos humanos) y Clinicas (de cateteres y de heridas); el
 * administrador puede crear los suyos en esta misma pantalla.
 *
 *   1. Datos de la pantalla
 *   2. Formulario de carga
 *   3. Tabla de bases cargadas
 *   4. Temas propios
 *   5. Edicion de metadatos
 *   6. Comportamiento
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// =========================================================================
// 1. DATOS DE LA PANTALLA
// =========================================================================
$entidades_data = siarhe_get_entities_ordered();
$temas          = siarhe_base_temas();
global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$bases = $wpdb->get_results( "SELECT * FROM $table_assets WHERE tipo_archivo = 'static_min' AND es_activo = 1 ORDER BY anio_reporte DESC" );
$upload_base = defined( 'SIARHE_UPLOAD_DIR' ) ? SIARHE_UPLOAD_DIR : wp_upload_dir()['basedir'] . '/siarhe-data/';

// El selector llega hasta el año en curso: no tiene caso ofrecer años que aun no ocurren
$anio_max = max( Siarhe_Static_Json::LATEST_YEAR, (int) gmdate( 'Y' ) );

// Selector de tema del formulario: los temas sueltos y, como una sola opcion, el grupo «Clinicas» (despues de Enfermeria)
$clinicas = array();
$temas_sueltos = array();
foreach ( $temas as $clave => $t ) {
    if ( $t['grupo'] === 'clinicas' ) $clinicas[ $clave ] = $t; else $temas_sueltos[ $clave ] = $t;
}
$temas_js = array();
foreach ( $temas as $clave => $t ) $temas_js[ $clave ] = array( 'unidades' => $t['unidades'] );

// Etiquetas de todas las unidades en uso, para el filtro de la tabla
$unidades_todas = array();
foreach ( $temas as $t ) $unidades_todas += $t['unidades'];
$orden_temas = array_flip( array_keys( $temas ) );

$u_msg = array(
    'success'      => array( 'notice-success', 'Base estática cargada y procesada correctamente.' ),
    'updated'      => array( 'notice-success', 'Metadatos de la base estática actualizados en el sistema.' ),
    'deleted'      => array( 'notice-warning', 'Archivo estático y metadatos eliminados permanentemente.' ),
    'tema_added'   => array( 'notice-success', 'Tema creado. Ya puedes elegirlo en el formulario y cargar sus bases.' ),
    'tema_deleted' => array( 'notice-warning', 'Tema eliminado junto con las bases que tenía cargadas.' ),
    'tema_exists'  => array( 'notice-error', 'Ya existe un tema con esa clave.' ),
    'tema_invalid' => array( 'notice-error', 'Datos del tema no válidos.' ),
);

Siarhe_Admin::render_flash_notice();
Siarhe_Admin::render_validation_result();
if ( isset( $_GET['status'] ) && isset( $u_msg[ $_GET['status'] ] ) ) {
    $aviso = $u_msg[ sanitize_key( $_GET['status'] ) ];
    echo '<div class="notice ' . esc_attr( $aviso[0] ) . ' is-dismissible"><p>' . esc_html( $aviso[1] ) . '</p></div>';
}

// Bases cargadas por año, tema, unidad y entidad. Una base cuenta si conserva al menos uno de sus archivos.
$cargadas  = array();
$huerfanas = array(); // bases de entidades que ya no estan en el catalogo
foreach ( $bases as $b ) {
    $csv_ok  = ! empty( $b->ruta_archivo ) && file_exists( $upload_base . ltrim( $b->ruta_archivo, '/' ) );
    $json_ok = ! empty( $b->ruta_json ) && file_exists( $upload_base . ltrim( $b->ruta_json, '/' ) );
    $tema_b  = ( isset( $b->tema ) && $b->tema !== '' ) ? $b->tema : 'enfermeria';
    $size    = ( $csv_ok ? filesize( $upload_base . ltrim( $b->ruta_archivo, '/' ) ) : 0 ) + ( $json_ok ? filesize( $upload_base . ltrim( $b->ruta_json, '/' ) ) : 0 );
    $fila    = array( 'b' => $b, 'csv_ok' => $csv_ok, 'json_ok' => $json_ok, 'size' => $size, 'tema' => $tema_b );
    if ( ! isset( $entidades_data[ $b->entidad_slug ] ) ) { $huerfanas[] = $fila; continue; }
    $cargadas[ (int) $b->anio_reporte ][ $tema_b ][ $b->unidad ][ $b->entidad_slug ] = $fila;
}
krsort( $cargadas );

// Filas de la tabla: por cada año, tema y unidad con al menos una base, todas las entidades (con base o sin ella, para ver
// lo que falta), en el orden del panel
$rows = array();
foreach ( $cargadas as $anio_fila => $por_tema ) {
    uksort( $por_tema, function ( $a, $b ) use ( $orden_temas ) {
        return ( isset( $orden_temas[ $a ] ) ? $orden_temas[ $a ] : 999 ) <=> ( isset( $orden_temas[ $b ] ) ? $orden_temas[ $b ] : 999 );
    } );
    foreach ( $por_tema as $tema_fila => $por_unidad ) {
        $orden_unidades = isset( $temas[ $tema_fila ] ) ? array_flip( array_keys( $temas[ $tema_fila ]['unidades'] ) ) : array();
        uksort( $por_unidad, function ( $a, $b ) use ( $orden_unidades ) {
            return ( isset( $orden_unidades[ $a ] ) ? $orden_unidades[ $a ] : 999 ) <=> ( isset( $orden_unidades[ $b ] ) ? $orden_unidades[ $b ] : 999 );
        } );
        foreach ( $por_unidad as $unidad_fila => $por_entidad ) {
            foreach ( $entidades_data as $slug => $d ) {
                $rows[] = array(
                    'nombre' => $d['nombre'], 'cve' => $d['CVE_ENT'], 'anio' => (int) $anio_fila, 'tema' => $tema_fila, 'unidad' => $unidad_fila,
                    'base'   => isset( $por_entidad[ $slug ] ) ? $por_entidad[ $slug ] : null,
                );
            }
        }
    }
}
foreach ( $huerfanas as $fila ) {
    $rows[] = array( 'nombre' => $fila['b']->entidad_slug . ' (entidad retirada)', 'cve' => '—', 'anio' => (int) $fila['b']->anio_reporte,
                     'tema' => $fila['tema'], 'unidad' => $fila['b']->unidad, 'base' => $fila );
}

// La tabla abre en el ultimo año con al menos una base; sin bases, en el año en curso
$anios_tabla  = $cargadas ? array_keys( $cargadas ) : array( (int) gmdate( 'Y' ) );
$anio_inicial = (int) $anios_tabla[0];

$tema_label   = function ( $clave ) use ( $temas ) { return isset( $temas[ $clave ] ) ? $temas[ $clave ]['label'] : $clave; };
$unidad_label = function ( $tema, $unidad ) use ( $temas, $unidades_todas ) {
    if ( isset( $temas[ $tema ]['unidades'][ $unidad ] ) ) return $temas[ $tema ]['unidades'][ $unidad ];
    return isset( $unidades_todas[ $unidad ] ) ? $unidades_todas[ $unidad ] : $unidad;
};
?>

<?php Siarhe_Admin::render_select2_css(); ?>

<?php
// =========================================================================
// 2. FORMULARIO DE CARGA
// =========================================================================
?>
<div class="siarhe-card siarhe-upload-card">
    <h2>📤 Cargar Base Estática<?php Siarhe_Admin::help( 'Sube los datos que se pintan en el mapa y la tabla de una entidad, para un año, un tema y una unidad de conteo.' ); ?></h2>

    <div class="notice notice-info inline">
        <p><strong>Cómo funcionan las bases:</strong></p>
        <ul>
            <li><strong>Una base por entidad, año, tema y unidad.</strong> Subir otra para la misma combinación la reemplaza y guarda copia de la anterior (Configuración › Versiones y Copias).</li>
            <li><strong>Temas:</strong> <em>Enfermería</em> (plazas de enfermería y recursos humanos de enfermería) y <em>Clínicas</em> (de catéteres y de heridas, en tasa y frecuencia). Puedes crear temas propios más abajo, por ejemplo para pasantes de servicio social.</li>
            <li><strong>Formatos admitidos:</strong> <code>.csv</code> (separado por comas) o <code>.json</code> en el formato del plugin (el que genera RHEMX en <code>data/processed/mapas/data/&lt;año&gt;/</code>, carpetas <code>plazas/</code> y <code>personas/</code>, donde cada entidad tiene su CSV y su JSON con el mismo nombre). Al subir uno, el plugin crea el otro; el mapa lee el JSON, que pesa menos.</li>
            <li><strong>Estructura obligatoria:</strong> <code>CVE_ENT</code> o <code>CVE_MUN</code> y el nombre (<code>estado</code> o <code>municipio</code>); las bases de Enfermería, además, una columna de población, <code>enfermeras_total</code> y <code>tasa_total</code>.</li>
            <li><strong>Se comprueba la entidad:</strong> el plugin rechaza la base de una entidad cargada en el lugar de otra (por su <code>CVE_ENT</code>) y una base nacional en el lugar de una estatal, o al revés.</li>
            <li><strong>Nomenclatura:</strong> los archivos se guardan como <code>{entidad}-{año}-{unidad}.csv</code> y <code>.json</code>; en los demás temas, <code>{entidad}-{año}-{tema}-{unidad}</code>.</li>
        </ul>
    </div>
    <?php Siarhe_Admin::render_columns_downloads( 'static' ); ?>

    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="siarhe-form-static">
        <input type="hidden" name="action" value="siarhe_upload_static">
        <input type="hidden" name="tema" id="siarhe-tema" value="enfermeria">
        <?php wp_nonce_field( 'siarhe_upload_static_nonce', 'siarhe_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="siarhe-tema-grupo">Tema</label><?php Siarhe_Admin::help( 'Asunto de la base. Enfermería son las plazas y los recursos humanos de enfermería; Clínicas, los indicadores de las clínicas de catéteres y de heridas. Los temas propios se crean más abajo.' ); ?></th>
                <td>
                    <select id="siarhe-tema-grupo">
                        <?php $clinicas_puesto = false;
                        foreach ( $temas_sueltos as $clave => $t ) : ?>
                            <option value="<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $t['label'] ); ?></option>
                            <?php if ( ! $clinicas_puesto && $clave === 'enfermeria' && $clinicas ) : $clinicas_puesto = true; ?>
                                <option value="grupo:clinicas">Clínicas</option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr id="siarhe-fila-clinica" hidden>
                <th scope="row"><label for="siarhe-tema-clinica">Clínica</label><?php Siarhe_Admin::help( 'Tipo de clínica al que corresponden los datos.' ); ?></th>
                <td>
                    <select id="siarhe-tema-clinica">
                        <?php foreach ( $clinicas as $clave => $t ) : ?>
                            <option value="<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $t['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="entidad_slug">Entidad Federativa / Alcance</label><?php Siarhe_Admin::help( 'Entidad (o nivel nacional, clave 33) a la que pertenece la base. El plugin comprueba que el archivo sea de esa entidad.' ); ?></th>
                <td class="siarhe-field-narrow">
                    <select name="entidad_slug" id="entidad_slug" class="siarhe-searchable-select" required>
                        <option value="">-- Selecciona la entidad --</option>
                        <?php foreach ( $entidades_data as $slug => $data ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $data['nombre'] ); ?> (<?php echo esc_html( $data['CVE_ENT'] ); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="anio_reporte">Año de los Datos</label><?php Siarhe_Admin::help( 'Año al que corresponden los datos. El mapa ofrece un selector con los años cargados.' ); ?></th>
                <td>
                    <select name="anio_reporte" id="anio_reporte" required>
                        <?php for ( $y = $anio_max; $y >= Siarhe_Static_Json::YEAR_MIN; $y-- ) : ?>
                            <option value="<?php echo (int) $y; ?>"<?php selected( $y, $anio_max ); ?>><?php echo (int) $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="unidad">Unidad de conteo</label><?php Siarhe_Admin::help( 'Lo que cuenta la base. En Enfermería: plazas de enfermería o recursos humanos de enfermería (una plaza por persona). En Clínicas: tasa o frecuencia.' ); ?></th>
                <td>
                    <select name="unidad" id="unidad">
                        <?php foreach ( $temas['enfermeria']['unidades'] as $clave => $texto ) : ?>
                            <option value="<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $texto ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="siarhe_file">Archivo de Datos (CSV o JSON)</label><?php Siarhe_Admin::help( 'CSV con una fila por región (estado o municipio) y una columna por métrica, o el JSON equivalente. Los nombres de columna deben coincidir con las claves definidas en Configuración › Métricas.' ); ?></th>
                <td>
                    <input type="file" data-check="static-csv" name="siarhe_file" id="siarhe_file" accept=".csv,.json" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fecha_corte">Fecha de Corte</label><?php Siarhe_Admin::help( 'Fecha hasta la cual el archivo contiene información. Se muestra al usuario como vigencia de los datos.' ); ?></th>
                <td><input type="date" name="fecha_corte" id="fecha_corte" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="referencia">Fuente / Referencia</label><?php Siarhe_Admin::help( 'Origen de la información (institución, documento, versión). Aparece como referencia bibliográfica.' ); ?></th>
                <td><textarea name="referencia" id="referencia" rows="2" class="large-text" placeholder="Ej: Fuente: SIARHE, Secretaría de Salud (2026)."></textarea></td>
            </tr>
            <tr>
                <th scope="row"><label for="comentarios">Comentarios Internos</label><?php Siarhe_Admin::help( 'Notas para el equipo administrador. No se muestran en el sitio público.' ); ?></th>
                <td><textarea name="comentarios" id="comentarios" rows="2" class="large-text" placeholder="Notas sobre el proceso de limpieza y minificación de datos..."></textarea></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Subir / Reemplazar Archivo">
        </p>
    </form>
</div>

<?php
// =========================================================================
// 3. TABLA DE BASES CARGADAS
// =========================================================================
?>
<div class="siarhe-card siarhe-card--flush">
    <h2 class="siarhe-card__title">Bases Estáticas Cargadas<?php Siarhe_Admin::help( 'Por cada año, tema y unidad con al menos una base se listan todas las entidades: las que ya tienen base y las que faltan. Desde aquí puedes copiar el enlace, editar los metadatos, descargar o eliminar.' ); ?></h2>

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
                <select id="siarhe-filter-tema" class="siarhe-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ( $temas as $clave => $t ) : ?><option value="<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $t['label'] ); ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>
                Unidad
                <select id="siarhe-filter-unidad" class="siarhe-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ( $unidades_todas as $clave => $texto ) : ?><option value="<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $texto ); ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php Siarhe_Admin::render_validate_button( 'static' ); ?>
        </div>

        <div class="siarhe-pagination siarhe-pagination--bare">
            <div class="siarhe-page-numbers" id="siarhe-pagination-controls-top"></div>
        </div>

        <div class="siarhe-search-box">
            <span class="dashicons dashicons-search"></span>
            <input type="text" id="siarhe-search-static" placeholder="Buscar entidad, archivo o año...">
        </div>
    </div>

    <table id="siarhe-static-table" class="siarhe-table">
        <thead>
            <tr>
                <th style="width: 16%;" data-sort="text">Entidad</th>
                <th style="width: 5%;" data-sort="num">CVE</th>
                <th style="width: 6%;" data-sort="num">Año</th>
                <th style="width: 10%;" data-sort="text">Tema</th>
                <th style="width: 10%;" data-sort="text">Unidad</th>
                <th style="width: 9%;">Formatos</th>
                <th style="width: 9%;" data-sort="num">Fecha de Corte</th>
                <th style="width: 15%;" data-sort="num">Auditoría</th>
                <th style="width: 6%;" data-sort="num">Tamaño</th>
                <th style="width: 14%;">Acciones</th>
            </tr>
        </thead>
        <tbody id="siarhe-static-tbody">
            <?php foreach ( $rows as $row ) :
                $base = $row['base'];
                $b    = $base ? $base['b'] : null;
            ?>
            <tr class="siarhe-data-row" data-anio="<?php echo (int) $row['anio']; ?>" data-tema="<?php echo esc_attr( $row['tema'] ); ?>" data-unidad="<?php echo esc_attr( $row['unidad'] ); ?>">
                <td data-label="Entidad" data-mobile-role="primary"><strong><?php echo esc_html( $row['nombre'] ); ?></strong></td>
                <td data-label="CVE"><span class="siarhe-badge neutral"><?php echo esc_html( $row['cve'] ); ?></span></td>
                <td data-label="Año" data-sort-value="<?php echo (int) $row['anio']; ?>"><?php echo (int) $row['anio']; ?></td>
                <td data-label="Tema"><?php echo esc_html( $tema_label( $row['tema'] ) ); ?></td>
                <td data-label="Unidad" data-mobile-role="secondary"><?php echo esc_html( $unidad_label( $row['tema'], $row['unidad'] ) ); ?></td>
                <?php if ( ! $b ) : ?>
                    <td data-label="Formatos"><span class="siarhe-status siarhe-status--off"><span class="dashicons dashicons-minus"></span>Sin base</span></td>
                    <td data-label="Fecha de Corte" data-sort-value="0">—</td>
                    <td data-label="Auditoría" data-sort-value="0"><span class="siarhe-text-faint">—</span></td>
                    <td data-label="Tamaño" data-sort-value="0">—</td>
                    <td data-label="Acciones"><div class="siarhe-row-actions"><span class="description">—</span></div></td>
                <?php else :
                    $nonce_del = wp_create_nonce( 'siarhe_delete_static_nonce_' . $b->id );
                    $fecha_original = $b->fecha_subida ?: $b->fecha_modificacion;
                    $url_csv  = $base['csv_ok']  ? SIARHE_UPLOAD_URL . ltrim( $b->ruta_archivo, '/' ) . '?v=' . filemtime( $upload_base . ltrim( $b->ruta_archivo, '/' ) ) : '';
                    $url_json = $base['json_ok'] ? SIARHE_UPLOAD_URL . ltrim( $b->ruta_json, '/' ) . '?v=' . filemtime( $upload_base . ltrim( $b->ruta_json, '/' ) ) : '';
                ?>
                    <td data-label="Formatos">
                        <span class="siarhe-badge <?php echo $base['csv_ok'] ? 'ok' : 'neutral'; ?>">CSV</span>
                        <span class="siarhe-badge <?php echo $base['json_ok'] ? 'ok' : 'neutral'; ?>">JSON</span>
                    </td>
                    <td data-label="Fecha de Corte" data-sort-value="<?php echo $b->fecha_corte ? (int) strtotime( $b->fecha_corte ) : 0; ?>">
                        <?php echo $b->fecha_corte ? esc_html( date_i18n( 'd/m/Y', strtotime( $b->fecha_corte ) ) ) : '—'; ?>
                    </td>
                    <td data-label="Auditoría" data-sort-value="<?php echo (int) strtotime( $b->fecha_modificacion ?: $fecha_original ); ?>">
                        <div class="siarhe-audit">
                            <span class="siarhe-audit__label">Subido por:</span><br>
                            <span class="siarhe-audit__who"><?php echo esc_html( $b->subido_por ?: 'Sistema' ); ?></span><br>
                            <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr( $fecha_original ); ?>"><?php echo esc_html( $fecha_original ); ?></span>
                        </div>
                        <?php if ( ! empty( $b->fecha_modificacion ) && $b->fecha_modificacion !== $fecha_original ) : ?>
                            <div class="siarhe-audit">
                                <span class="siarhe-audit__label siarhe-audit__label--edit">Última edición:</span><br>
                                <span class="siarhe-audit__who"><?php echo esc_html( $b->modificado_por ?: 'Sistema' ); ?></span><br>
                                <span class="siarhe-audit__date siarhe-date-formatter" data-date="<?php echo esc_attr( $b->fecha_modificacion ); ?>"><?php echo esc_html( $b->fecha_modificacion ); ?></span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Tamaño" data-sort-value="<?php echo (int) $base['size']; ?>"><?php echo $base['size'] ? esc_html( size_format( $base['size'] ) ) : '—'; ?></td>
                    <td data-label="Acciones">
                        <div class="siarhe-row-actions">
                            <?php if ( $url_json ) : ?>
                                <button type="button" class="button button-small" data-copy="<?php echo esc_url( $url_json ); ?>" title="Copiar enlace al JSON"><span class="dashicons dashicons-admin-links"></span></button>
                            <?php endif; ?>
                            <button type="button" class="button button-small edit-meta-btn"
                                    data-id="<?php echo absint( $b->id ); ?>"
                                    data-nombre="<?php echo esc_attr( $row['nombre'] . ' · ' . $row['anio'] . ' · ' . $tema_label( $row['tema'] ) . ' · ' . $unidad_label( $row['tema'], $row['unidad'] ) ); ?>"
                                    data-anio="<?php echo esc_attr( $b->anio_reporte ); ?>"
                                    data-corte="<?php echo esc_attr( $b->fecha_corte ); ?>"
                                    data-ref="<?php echo esc_attr( $b->referencia_bibliografica ); ?>"
                                    data-notes="<?php echo esc_attr( $b->comentarios ); ?>"
                                    title="Ver Información / Editar Metadatos"><span class="dashicons dashicons-edit"></span></button>
                            <?php if ( $url_csv ) : ?>
                                <a href="<?php echo esc_url( $url_csv ); ?>" target="_blank" rel="noopener" class="button button-small" title="Descargar el CSV"><span class="dashicons dashicons-download"></span></a>
                            <?php endif; ?>
                            <?php if ( $url_json ) : ?>
                                <a href="<?php echo esc_url( $url_json ); ?>" target="_blank" rel="noopener" class="button button-small" title="Descargar el JSON"><span class="dashicons dashicons-media-code"></span></a>
                            <?php endif; ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="⚠️ ATENCIÓN: ¿Estás seguro de eliminar esta base (CSV y JSON)? Esta acción borrará el registro y los archivos del servidor de forma irreversible.">
                                <input type="hidden" name="action" value="siarhe_delete_static">
                                <input type="hidden" name="file_id" value="<?php echo absint( $b->id ); ?>">
                                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce_del ); ?>">
                                <button type="submit" class="button button-small button-danger" title="Eliminar la base"><span class="dashicons dashicons-trash"></span></button>
                            </form>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <tr id="siarhe-static-empty" style="display: none;">
                <td colspan="10" class="siarhe-empty-cell"><?php echo $rows ? 'No hay bases para esta combinación de año, tema, unidad y búsqueda.' : 'Todavía no hay bases estáticas cargadas.'; ?></td>
            </tr>
        </tbody>
    </table>

    <div class="siarhe-pagination">
        <div id="siarhe-static-count" class="siarhe-count"></div>
        <div class="siarhe-page-numbers" id="siarhe-pagination-controls-bottom"></div>
    </div>
</div>

<?php
// =========================================================================
// 4. TEMAS PROPIOS
// =========================================================================
?>
<div class="siarhe-card siarhe-upload-card">
    <h3 class="siarhe-m-0">➕ Crear un Tema de Bases Estáticas<?php Siarhe_Admin::help( 'Un tema propio es un apartado nuevo de bases estáticas, con sus años, entidades y unidades, separado de Enfermería y de Clínicas. Sirve para cargar otras métricas (por ejemplo, pasantes de servicio social) sin mezclarlas con las que ya existen.' ); ?></h3>
    <p class="description">Crea el tema, elígelo en el formulario de arriba y carga sus bases. Por ahora los temas propios se guardan y se administran aquí; el mapa público muestra las bases de Enfermería.</p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="siarhe_base_tema_add">
        <?php wp_nonce_field( 'siarhe_base_tema_add', 'tema_nonce' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="tema_label">Nombre del tema</label><?php Siarhe_Admin::help( 'Nombre con que aparecerá en los selectores de esta pantalla.' ); ?></th>
                <td><input type="text" name="tema_label" id="tema_label" placeholder="Ej: Servicio social" required class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="tema_clave">Clave interna (opcional)</label><?php Siarhe_Admin::help( 'Identificador del tema: letras minúsculas, números y guion bajo. Forma parte del nombre de los archivos y no se puede cambiar después. Si la dejas vacía se toma del nombre.' ); ?></th>
                <td><input type="text" name="tema_clave" id="tema_clave" placeholder="ej: servicio_social" class="regular-text" maxlength="40" pattern="[A-Za-z0-9_\- ]*"></td>
            </tr>
            <tr>
                <th scope="row">Unidades de conteo<?php Siarhe_Admin::help( 'Las unidades que ofrecerá el tema al cargar una base. Cada unidad es una base distinta de la misma entidad y año.' ); ?></th>
                <td>
                    <?php foreach ( siarhe_base_unidades() as $clave => $texto ) : ?>
                        <label class="siarhe-check-inline"><input type="checkbox" name="tema_unidades[]" value="<?php echo esc_attr( $clave ); ?>"<?php checked( in_array( $clave, array( 'frecuencia', 'tasa' ), true ) ); ?>> <?php echo esc_html( $texto ); ?></label>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
        <p class="submit"><button type="submit" class="button button-secondary">Crear Tema</button></p>
    </form>

    <?php $propios = array_filter( $temas, function ( $t ) { return empty( $t['sistema'] ); } ); ?>
    <?php if ( $propios ) : ?>
        <table class="widefat striped siarhe-temas-propios">
            <thead><tr><th>Tema</th><th>Clave</th><th>Unidades</th><th>Bases cargadas</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ( $propios as $clave => $t ) :
                $n_bases = 0;
                foreach ( $bases as $b ) if ( isset( $b->tema ) && $b->tema === $clave ) $n_bases++;
            ?>
                <tr>
                    <td><strong><?php echo esc_html( $t['label'] ); ?></strong></td>
                    <td><code><?php echo esc_html( $clave ); ?></code></td>
                    <td><?php echo esc_html( implode( ', ', $t['unidades'] ) ); ?></td>
                    <td><?php echo (int) $n_bases; ?></td>
                    <td>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="¿Eliminar este tema de forma permanente? Las <?php echo (int) $n_bases; ?> base(s) cargadas en él (archivos y registros) también se borrarán.">
                            <input type="hidden" name="action" value="siarhe_base_tema_delete">
                            <input type="hidden" name="tema_clave" value="<?php echo esc_attr( $clave ); ?>">
                            <?php wp_nonce_field( 'siarhe_base_tema_delete', 'tema_nonce' ); ?>
                            <button type="submit" class="button button-small button-danger" title="Eliminar el tema y sus bases"><span class="dashicons dashicons-trash"></span></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
// =========================================================================
// 5. EDICION DE METADATOS
// =========================================================================
?>
<div id="siarhe-edit-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content">
        <h2 class="siarhe-modal-title">Editar Metadatos: <span id="modal-entidad-name"></span></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="siarhe_update_static_meta">
            <input type="hidden" name="file_id" id="modal-file-id">
            <?php wp_nonce_field( 'siarhe_update_static_meta_nonce', 'siarhe_meta_nonce' ); ?>
            <table class="form-table">
                <tr><th><label>Año de los Datos</label><?php Siarhe_Admin::help( 'El año, el tema y la unidad identifican la base: para cambiarlos, súbela de nuevo en el lugar correcto y elimina esta.' ); ?></th><td><input type="number" id="modal-anio" class="regular-text" readonly></td></tr>
                <tr><th><label>Fecha de Corte</label><?php Siarhe_Admin::help( 'Fecha hasta la cual el archivo contiene información. Se muestra al usuario como vigencia de los datos.' ); ?></th><td><input type="date" name="fecha_corte" id="modal-corte" class="regular-text" required></td></tr>
                <tr><th><label>Fuente / Referencia</label><?php Siarhe_Admin::help( 'Origen de la información (institución, documento, versión). Aparece como referencia bibliográfica.' ); ?></th><td><textarea name="referencia" id="modal-ref" rows="3" class="large-text"></textarea></td></tr>
                <tr><th><label>Comentarios Internos</label><?php Siarhe_Admin::help( 'Notas para el equipo administrador. No se muestran en el sitio público.' ); ?></th><td><textarea name="comentarios" id="modal-notes" rows="3" class="large-text"></textarea></td></tr>
            </table>
            <div class="siarhe-modal-footer">
                <button type="button" class="button button-secondary" id="close-modal-btn">Cancelar</button>
                <button type="submit" class="button button-primary">Guardar Cambios</button>
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
    if (typeof jQuery !== 'undefined') {
        jQuery('.siarhe-searchable-select').select2({ placeholder: "-- Escribe para buscar entidad --", allowClear: true, width: '100%' });
    }
    // Los demás selectores del formulario y de la barra de la tabla también se pueden escribir
    if (window.SiarheAdmin && window.SiarheAdmin.initSelects) window.SiarheAdmin.initSelects();
    if (window.SiarheAdmin && window.SiarheAdmin.formatDate) {
        document.querySelectorAll('.siarhe-date-formatter').forEach(function(el) {
            var rawDate = el.getAttribute('data-date');
            if (rawDate) el.textContent = window.SiarheAdmin.formatDate(rawDate);
        });
    }
    if (window.SiarheAdmin && window.SiarheAdmin.initMobileTables) window.SiarheAdmin.initMobileTables();

    // Tema del formulario: el grupo «Clínicas» abre el selector de clínica, y cada tema ofrece sus unidades de conteo
    var temas = <?php echo wp_json_encode( $temas_js ); ?>;
    var selGrupo = document.getElementById('siarhe-tema-grupo');
    var selClinica = document.getElementById('siarhe-tema-clinica');
    var filaClinica = document.getElementById('siarhe-fila-clinica');
    var campoTema = document.getElementById('siarhe-tema');
    var selUnidad = document.getElementById('unidad');
    function aplicaTema() {
        var esClinica = selGrupo.value === 'grupo:clinicas';
        filaClinica.hidden = !esClinica;
        var tema = esClinica ? selClinica.value : selGrupo.value;
        campoTema.value = tema;
        var unidades = (temas[tema] && temas[tema].unidades) || {};
        selUnidad.innerHTML = '';
        Object.keys(unidades).forEach(function(clave) {
            var op = document.createElement('option');
            op.value = clave;
            op.textContent = unidades[clave];
            selUnidad.appendChild(op);
        });
        if (window.SiarheAdmin && window.SiarheAdmin.refreshSelect) window.SiarheAdmin.refreshSelect(selUnidad);
        // La comprobación previa del archivo depende del tema (solo Enfermería exige la población)
        campoTema.dispatchEvent(new Event('change'));
    }
    if (selGrupo && selClinica) {
        selGrupo.addEventListener('change', aplicaTema);
        selClinica.addEventListener('change', aplicaTema);
        aplicaTema();
    }

    // Modal de edicion de metadatos
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

    // Busqueda, filtros por año, tema y unidad, y paginacion (motor compartido)
    if (window.SiarheAdmin && window.SiarheAdmin.initTable) {
        window.SiarheAdmin.initTable({
            rows:    document.querySelectorAll('.siarhe-data-row'),
            search:  document.getElementById('siarhe-search-static'),
            perPage: document.getElementById('siarhe-items-per-page'),
            filters: [
                { el: document.getElementById('siarhe-filter-anio'),   match: function(row, v) { return row.dataset.anio === v; } },
                { el: document.getElementById('siarhe-filter-tema'),   match: function(row, v) { return row.dataset.tema === v; } },
                { el: document.getElementById('siarhe-filter-unidad'), match: function(row, v) { return row.dataset.unidad === v; } }
            ],
            pagers:  [document.getElementById('siarhe-pagination-controls-top'), document.getElementById('siarhe-pagination-controls-bottom')],
            table:   document.getElementById('siarhe-static-table'),
            count:   document.getElementById('siarhe-static-count'),
            empty:   document.getElementById('siarhe-static-empty')
        });
    }
});
</script>
