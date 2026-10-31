<?php // /admin/partials/dashboard-home.php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_assets = $wpdb->prefix . 'siarhe_static_assets';
$upload_base  = defined( 'SIARHE_UPLOAD_DIR' ) ? SIARHE_UPLOAD_DIR : trailingslashit( wp_upload_dir()['basedir'] ) . 'siarhe-data/';
$entities     = siarhe_get_entities();

// 1. Archivos activos, indexados por entidad y tipo
$tabla_existe = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_assets ) ) === $table_assets );
$rows         = $tabla_existe ? $wpdb->get_results( "SELECT * FROM $table_assets WHERE es_activo = 1" ) : array();

$by_entity    = array();   // [slug][tipo] => fila
$markers_n    = 0;
$last_update  = array();   // [slug] => fecha mas reciente
foreach ( $rows as $r ) {
    if ( $r->tipo_archivo === 'marcador' ) { $markers_n++; continue; }
    $by_entity[ $r->entidad_slug ][ $r->tipo_archivo ] = $r;
    $fecha = ! empty( $r->fecha_modificacion ) ? $r->fecha_modificacion : $r->fecha_subida;
    if ( $fecha && ( ! isset( $last_update[ $r->entidad_slug ] ) || $fecha > $last_update[ $r->entidad_slug ] ) ) {
        $last_update[ $r->entidad_slug ] = $fecha;
    }
}

// Solo cuenta un tipo si el archivo fisico sigue en disco
$has = function ( $slug, $tipo ) use ( $by_entity, $upload_base ) {
    if ( empty( $by_entity[ $slug ][ $tipo ] ) ) return false;
    return file_exists( $upload_base . ltrim( $by_entity[ $slug ][ $tipo ]->ruta_archivo, '/' ) );
};

// 2. Matriz de entidades (orden por CVE; nacional primero)
$matrix = array();
foreach ( $entities as $slug => $data ) {
    if ( (int) $data['CVE_ENT'] > 34 ) continue; // capas especiales (98/99) no llevan shortcode de mapa
    $matrix[] = array(
        'slug'   => $slug,
        'nombre' => $data['nombre'],
        'cve'    => $data['CVE_ENT'],
        'geo'    => $has( $slug, 'geojson' ),
        'csv'    => $has( $slug, 'static_min' ),
        'loc'    => $has( $slug, 'localidades_geojson' ),
        'upd'    => isset( $last_update[ $slug ] ) ? $last_update[ $slug ] : '',
    );
}
usort( $matrix, function ( $a, $b ) {
    $ka = $a['cve'] === '33' ? -1 : (int) $a['cve'];
    $kb = $b['cve'] === '33' ? -1 : (int) $b['cve'];
    return $ka <=> $kb;
} );

$total   = count( $matrix );
$n_geo   = count( array_filter( $matrix, function ( $m ) { return $m['geo']; } ) );
$n_csv   = count( array_filter( $matrix, function ( $m ) { return $m['csv']; } ) );
$n_loc   = count( array_filter( $matrix, function ( $m ) { return $m['loc']; } ) );
$n_ready = count( array_filter( $matrix, function ( $m ) { return $m['geo'] && $m['csv']; } ) );
$pct     = function ( $n ) use ( $total ) { return $total ? round( $n * 100 / $total ) : 0; };

// 3. Chequeos de salud
$to_bytes = function ( $v ) {
    return function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( $v ) : (int) $v;
};
$upload_max = $to_bytes( ini_get( 'upload_max_filesize' ) );
$post_max   = $to_bytes( ini_get( 'post_max_size' ) );
$mem        = $to_bytes( ini_get( 'memory_limit' ) );
$mem_ok     = ( $mem === -1 || $mem >= 128 * MB_IN_BYTES );
$up_ok      = ( $upload_max >= 8 * MB_IN_BYTES );
$subdirs    = array( 'geojson', 'localidades', 'raw', 'processed', 'static-min' );
$no_write   = array();
foreach ( $subdirs as $d ) {
    $path = $upload_base . $d;
    if ( is_dir( $path ) && ! wp_is_writable( $path ) ) $no_write[] = $d; // los que faltan se crean al subir
}
$dir_ok = ( is_dir( $upload_base ) && wp_is_writable( $upload_base ) && ! $no_write );

$ver_summary = Siarhe_Versions::summary();
$ver_keep    = Siarhe_Versions::keep();
$zlib_ok     = Siarhe_Versions::zlib_available();

// Compresion de los datos: archivo a consultar y estado del .htaccess de la carpeta de datos
$probe_url = ''; $probe_small = false; $copies_on = siarhe_read_copies_enabled();
foreach ( array( 'geojson', 'static_min' ) as $probe_tipo ) {
    foreach ( $rows as $r ) {
        if ( $r->tipo_archivo !== $probe_tipo || empty( $r->ruta_archivo ) ) continue;
        if ( ! file_exists( $upload_base . ltrim( $r->ruta_archivo, '/' ) ) ) continue;
        $base_url  = defined( 'SIARHE_UPLOAD_URL' ) ? SIARHE_UPLOAD_URL : trailingslashit( wp_upload_dir()['baseurl'] ) . 'siarhe-data/';
        $probe_orig = $base_url . ltrim( $r->ruta_archivo, '/' );
        $probe_url  = siarhe_data_read_url( $probe_orig );
        $probe_small = ( $probe_url === $probe_orig ); // menor a 100 KB: no se crea copia de lectura
        break 2;
    }
}
$ht_file = $upload_base . '.htaccess';
$ht_made = file_exists( $ht_file ) && strpos( (string) file_get_contents( $ht_file ), '# SIARHE-DATA' ) !== false;

$health = array(
    array(
        'state'  => $dir_ok ? 'ok' : 'fail',
        'label'  => 'Carpeta de datos escribible',
        'detail' => $dir_ok ? $upload_base : ( 'Revisa permisos en: ' . $upload_base . ( $no_write ? ' (' . implode( ', ', $no_write ) . ')' : '' ) ),
    ),
    array(
        'state'  => $tabla_existe ? 'ok' : 'fail',
        'label'  => 'Tabla de recursos estáticos',
        'detail' => $tabla_existe ? $table_assets : 'No existe; desactiva y vuelve a activar el plugin.',
    ),
    array(
        'state'  => $up_ok ? 'ok' : 'warn',
        'label'  => 'Tamaño máximo de subida',
        'detail' => 'upload_max_filesize: ' . size_format( $upload_max ) . ' · post_max_size: ' . size_format( $post_max ) . ( $up_ok ? '' : ' (los GeoJSON grandes pueden fallar; se recomiendan 8 MB o más)' ),
    ),
    array(
        'state'  => $mem_ok ? 'ok' : 'warn',
        'label'  => 'Memoria de PHP',
        'detail' => 'memory_limit: ' . ( $mem === -1 ? 'sin límite' : size_format( $mem ) ) . ( $mem_ok ? '' : ' (se recomiendan 128 MB o más para validar archivos grandes)' ),
    ),
    array(
        'state'  => $zlib_ok ? 'ok' : 'warn',
        'label'  => 'Compresión de copias (zlib)',
        'detail' => $zlib_ok
            ? ( Siarhe_Versions::compress_enabled() ? 'Disponible y activada: las copias nuevas se guardan comprimidas (.gz).' : 'Disponible. Puedes activarla en Configuración › Versiones y Copias para que las copias ocupen mucho menos.' )
            : 'La extensión zlib de PHP no está disponible: las copias se guardan sin comprimir. Pídele a tu hosting que la active.',
    ),
    array(
        'state'  => 'ok',
        'label'  => 'Copias de seguridad',
        'detail' => ( $ver_keep === 0 ? 'No se guardan copias' : $ver_keep . ' copia(s) por archivo' ) . ' · ' . (int) $ver_summary['count'] . ' guardada(s) · ' . ( size_format( $ver_summary['bytes'] ) ?: '0 B' ) . ' de espacio',
    ),
    array(
        'state'  => 'ok',
        'label'  => 'Versiones',
        'detail' => 'Plugin ' . SIARHE_VERSION . ' · WordPress ' . get_bloginfo( 'version' ) . ' · PHP ' . PHP_VERSION,
    ),
    array(
        'state'  => 'pending',
        'label'  => 'Compresión de los datos del mapa',
        'detail' => $probe_url ? 'Comprobando…' : 'Aún no hay archivos cargados para comprobar.',
        'id'     => 'siarhe-health-compress',
    ),
    array(
        'state'  => 'pending',
        'label'  => 'CDN de D3.js',
        'detail' => 'Comprobando…',
        'id'     => 'siarhe-health-cdn',
    ),
);
$icons = array( 'ok' => 'yes-alt', 'warn' => 'warning', 'fail' => 'dismiss', 'pending' => 'update' );
?>

<div class="wrap" data-siarhe-scrolltop>
    <?php
    if ( isset( $_GET['copies'] ) ) {
        $copies = get_transient( 'siarhe_copies_result_' . get_current_user_id() );
        if ( is_array( $copies ) ) {
            delete_transient( 'siarhe_copies_result_' . get_current_user_id() );
            $msg = sprintf( 'Copias de lectura: %d creada(s) (%s), %d ya estaban al día, %d archivo(s) pequeños sin copia.',
                (int) $copies['creada'], size_format( (int) $copies['bytes'] ) ?: '0 B', (int) $copies['vigente'], (int) $copies['pequena'] );
            if ( ! empty( $copies['fallo'] ) ) $msg .= ' No se pudieron crear ' . (int) $copies['fallo'] . ' (revisa los permisos de la carpeta de datos).';
            echo '<div class="notice ' . ( empty( $copies['fallo'] ) ? 'notice-success' : 'notice-warning' ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
        }
    }
    ?>
    <div class="siarhe-page-header">
        <div>
            <h1>Dashboard SIARHE</h1>
            <p>Estado de los datos por entidad, shortcodes listos para pegar y salud del sistema.</p>
        </div>
        <div class="siarhe-page-actions">
            <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=siarhe-uploader' ) ); ?>">Cargar datos</a>
            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=siarhe-settings' ) ); ?>">Configuración</a>
        </div>
    </div>

    <div class="siarhe-stats">
        <?php
        $stats = array(
            array( $n_ready, 'Entidades listas (mapa + base)', $pct( $n_ready ) ),
            array( $n_geo,   'Con GeoJSON',                    $pct( $n_geo ) ),
            array( $n_csv,   'Con base estática (CSV)',        $pct( $n_csv ) ),
            array( $n_loc,   'Con localidades',                $pct( $n_loc ) ),
        );
        foreach ( $stats as $st ) : ?>
            <div class="siarhe-stat">
                <div class="siarhe-stat__value"><?php echo (int) $st[0]; ?><small> / <?php echo (int) $total; ?></small></div>
                <div class="siarhe-stat__label"><?php echo esc_html( $st[1] ); ?></div>
                <div class="siarhe-stat__bar"><span style="width: <?php echo (int) $st[2]; ?>%"></span></div>
            </div>
        <?php endforeach; ?>
        <div class="siarhe-stat">
            <div class="siarhe-stat__value"><?php echo (int) $markers_n; ?></div>
            <div class="siarhe-stat__label">Archivos de marcadores</div>
        </div>
    </div>

    <div class="siarhe-card siarhe-card--flush">
        <h2 class="siarhe-card__title"><span class="dashicons dashicons-heart"></span> Salud del sistema<?php Siarhe_Admin::help( 'Comprobaciones automáticas del servidor y del plugin: permisos de la carpeta de datos, límites de subida, memoria, compresión de copias y acceso a D3.js. Un aviso amarillo o rojo indica qué conviene revisar con tu hosting.' ); ?></h2>
        <ul class="siarhe-health">
            <?php foreach ( $health as $h ) : ?>
                <li class="is-<?php echo esc_attr( $h['state'] ); ?>"<?php echo ! empty( $h['id'] ) ? ' id="' . esc_attr( $h['id'] ) . '"' : ''; ?>>
                    <span class="dashicons dashicons-<?php echo esc_attr( $icons[ $h['state'] ] ); ?>"></span>
                    <div>
                        <div class="siarhe-health__label"><?php echo esc_html( $h['label'] ); ?></div>
                        <div class="siarhe-health__detail"><?php echo esc_html( $h['detail'] ); ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="siarhe-health__footer">
            <?php if ( $copies_on ) : ?>
            <p class="description">Los archivos de datos de 100 KB o más se leen desde una copia (<code>.json</code> o <code>.txt</code>) que el servidor sí comprime. Se crean solas al subir un archivo y al abrir su mapa; este botón las crea de una vez para todo lo que ya está cargado.</p>
            <?php else : ?>
            <p class="description">Las copias de lectura están <strong>desactivadas</strong>: los mapas leen los archivos originales. Puedes activarlas en <a href="<?php echo esc_url( admin_url( 'admin.php?page=siarhe-settings&tab=versiones' ) ); ?>">Configuración › Versiones y Copias</a>.</p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="siarhe_generate_copies">
                <?php wp_nonce_field( 'siarhe_generate_copies' ); ?>
                <button type="submit" class="button" <?php disabled( ! $copies_on ); ?>><span class="dashicons dashicons-update"></span> Generar copias de lectura ahora</button>
            </form>
        </div>
    </div>

    <div class="siarhe-card siarhe-card--flush">
        <h2 class="siarhe-card__title"><span class="dashicons dashicons-grid-view"></span> Entidades y shortcodes<?php Siarhe_Admin::help( 'Las 34 entidades y qué archivos tiene cada una (GeoJSON, base CSV y localidades). Usa los botones Completo, Mapa o Tabla para copiar el shortcode y pegarlo en una página. Puedes buscar, filtrar por estado y ordenar las columnas.' ); ?></h2>
        <div class="siarhe-toolbar">
            <div class="siarhe-table-controls">
                <label>
                    <select id="siarhe-matrix-filter" class="siarhe-select-sm" aria-label="Filtrar entidades">
                        <option value="">Todas las entidades</option>
                        <option value="ready">Listas (mapa + base)</option>
                        <option value="incomplete">Incompletas</option>
                        <option value="nogeo">Sin GeoJSON</option>
                        <option value="nocsv">Sin base estática (CSV)</option>
                        <option value="noloc">Sin localidades</option>
                    </select>
                </label>
            </div>
            <div class="siarhe-count" id="siarhe-matrix-count"></div>
            <div class="siarhe-search-box">
                <span class="dashicons dashicons-search"></span>
                <input type="text" id="siarhe-matrix-search" placeholder="Buscar entidad o clave...">
            </div>
        </div>

        <table class="siarhe-table siarhe-matrix" id="siarhe-matrix-table">
            <thead>
                <tr>
                    <th data-sort="text">Entidad</th>
                    <th data-sort="num">CVE</th>
                    <th>GeoJSON</th>
                    <th>CSV</th>
                    <th>Localidades</th>
                    <th data-sort="num">Última actualización</th>
                    <th>Copiar shortcode</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $matrix as $m ) :
                $flag = function ( $ok ) {
                    return $ok
                        ? '<span class="dashicons dashicons-yes-alt siarhe-yes" title="Cargado"></span><span class="screen-reader-text">Sí</span>'
                        : '<span class="dashicons dashicons-minus siarhe-no" title="Falta"></span><span class="screen-reader-text">No</span>';
                };
                ?>
                <tr class="siarhe-data-row" data-geo="<?php echo $m['geo'] ? 1 : 0; ?>" data-csv="<?php echo $m['csv'] ? 1 : 0; ?>" data-loc="<?php echo $m['loc'] ? 1 : 0; ?>">
                    <td data-label="Entidad" data-mobile-role="primary"><strong><?php echo esc_html( $m['nombre'] ); ?></strong></td>
                    <td data-label="CVE" data-mobile-role="secondary"><span class="siarhe-badge neutral"><?php echo esc_html( $m['cve'] ); ?></span></td>
                    <td data-label="GeoJSON"><?php echo $flag( $m['geo'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- marcado fijo ?></td>
                    <td data-label="CSV"><?php echo $flag( $m['csv'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
                    <td data-label="Localidades"><?php echo $flag( $m['loc'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
                    <td data-label="Última actualización" data-sort-value="<?php echo $m['upd'] ? (int) strtotime( $m['upd'] ) : 0; ?>">
                        <?php if ( $m['upd'] ) : ?>
                            <span class="siarhe-date-formatter" data-date="<?php echo esc_attr( $m['upd'] ); ?>"><?php echo esc_html( $m['upd'] ); ?></span>
                        <?php else : ?>—<?php endif; ?>
                    </td>
                    <td data-label="Shortcode">
                        <div class="siarhe-shortcodes">
                            <?php foreach ( array(
                                'MT' => array( 'Completo', 'Mapa y tabla juntos' ),
                                'M'  => array( 'Mapa', 'Solo el mapa, sin tabla' ),
                                'T'  => array( 'Tabla', 'Solo la tabla de datos, sin mapa' ),
                            ) as $modo => $inf ) :
                                $sc = '[siarhe_mapa_' . $modo . '_CVE_ENT_' . $m['cve'] . ']'; ?>
                                <button type="button" class="button button-small" data-copy="<?php echo esc_attr( $sc ); ?>" data-help="<?php echo esc_attr( 'Copia el shortcode que muestra: ' . $inf[1] . '. ' . $sc ); ?>"><?php echo esc_html( $inf[0] ); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr id="siarhe-matrix-empty" style="display: none;">
                <td colspan="7" class="siarhe-empty-cell">Ninguna entidad coincide con el filtro o la búsqueda.</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.SiarheAdmin) {
        window.SiarheAdmin.initMobileTables();
        var matrixRows = document.querySelectorAll('.siarhe-matrix .siarhe-data-row');
        if (window.SiarheAdmin.initTable && matrixRows.length) {
            window.SiarheAdmin.initTable({
                rows:    matrixRows,
                search:  document.getElementById('siarhe-matrix-search'),
                filters: [{ el: document.getElementById('siarhe-matrix-filter'), match: function(row, val) {
                    var geo = row.getAttribute('data-geo') === '1', csv = row.getAttribute('data-csv') === '1', loc = row.getAttribute('data-loc') === '1';
                    if (val === 'ready') return geo && csv;
                    if (val === 'incomplete') return !(geo && csv);
                    if (val === 'nogeo') return !geo;
                    if (val === 'nocsv') return !csv;
                    if (val === 'noloc') return !loc;
                    return true;
                } }],
                table:   document.getElementById('siarhe-matrix-table'),
                count:   document.getElementById('siarhe-matrix-count'),
                empty:   document.getElementById('siarhe-matrix-empty'),
                defaultPerPage: 'all'
            });
        }
        document.querySelectorAll('.siarhe-date-formatter').forEach(function(el) {
            var raw = el.getAttribute('data-date');
            if (raw) el.textContent = window.SiarheAdmin.formatDate(raw);
        });
    }

    // Compresion de los datos: lee los encabezados reales que entrega el servidor
    (function() {
        var item = document.getElementById('siarhe-health-compress');
        var url = <?php echo wp_json_encode( $probe_url ); ?>;
        var small = <?php echo $probe_small ? 'true' : 'false'; ?>;
        var copiesOn = <?php echo $copies_on ? 'true' : 'false'; ?>;
        var htMade = <?php echo $ht_made ? 'true' : 'false'; ?>;
        if (!item || !url) return;
        var show = function(state, icon, text) {
            item.className = 'is-' + state;
            item.querySelector('.dashicons').className = 'dashicons dashicons-' + icon;
            item.querySelector('.siarhe-health__detail').textContent = text;
        };
        fetch(url, { method: 'HEAD', cache: 'no-store' }).then(function(r) {
            var enc = (r.headers.get('content-encoding') || '').toLowerCase();
            var type = (r.headers.get('content-type') || '').toLowerCase().split(';')[0].trim();
            var compressible = /^text\//.test(type) || /json|xml/.test(type);
            if (enc) {
                show('ok', 'yes-alt', copiesOn ? 'Los mapas leen los datos comprimidos (' + enc + '): el plugin sirve los archivos grandes con una copia .json/.txt que el servidor sí comprime.' : 'Los archivos de datos se entregan comprimidos (' + enc + ').');
            } else if (!copiesOn) {
                show('ok', 'yes-alt', 'Las copias de lectura están desactivadas: los mapas leen los archivos originales' + (compressible ? '.' : ' (este servidor no los comprime; si el mapa carga lento, activa las copias en Configuración › Versiones y Copias).'));
            } else if (small) {
                show('ok', 'yes-alt', 'Los archivos cargados pesan menos de 100 KB; no necesitan compresión.');
            } else if (compressible) {
                show('warn', 'warning', 'Los datos se entregan con el tipo correcto (' + type + ') pero sin comprimir. Revisa que el servidor o Cloudflare tengan la compresión activada para ese tipo.');
            } else {
                show('warn', 'warning', 'El servidor entrega los datos como «' + (type || 'tipo desconocido') + '» y no los comprime, incluso con la copia de lectura del plugin. Revisa la compresión del servidor y de Cloudflare.');
            }
        }).catch(function() { show('warn', 'warning', 'No se pudo comprobar la compresión desde este navegador.'); });
    })();

    // Comprobación del CDN de D3 (desde el navegador del administrador)
    var li = document.getElementById('siarhe-health-cdn');
    if (!li) return;
    var set = function(state, icon, text) {
        li.className = 'is-' + state;
        li.querySelector('.dashicons').className = 'dashicons dashicons-' + icon;
        li.querySelector('.siarhe-health__detail').textContent = text;
    };
    var ctrl = new AbortController();
    var timer = setTimeout(function() { ctrl.abort(); }, 6000);
    fetch('https://d3js.org/d3.v7.min.js', { method: 'HEAD', mode: 'no-cors', signal: ctrl.signal })
        .then(function() { clearTimeout(timer); set('ok', 'yes-alt', 'd3js.org responde. (Si fallara, el plugin usa la copia local.)'); })
        .catch(function() { clearTimeout(timer); set('warn', 'warning', 'd3js.org no respondió; los mapas usarán la copia local incluida en el plugin.'); });
});
</script>
