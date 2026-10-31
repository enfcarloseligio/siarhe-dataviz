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
        'state'  => 'ok',
        'label'  => 'Versiones',
        'detail' => 'Plugin ' . SIARHE_VERSION . ' · WordPress ' . get_bloginfo( 'version' ) . ' · PHP ' . PHP_VERSION,
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
        <h2 class="siarhe-card__title"><span class="dashicons dashicons-heart"></span> Salud del sistema</h2>
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
    </div>

    <div class="siarhe-card siarhe-card--flush">
        <h2 class="siarhe-card__title"><span class="dashicons dashicons-grid-view"></span> Entidades y shortcodes</h2>
        <table class="siarhe-table siarhe-matrix">
            <thead>
                <tr>
                    <th>Entidad</th>
                    <th>CVE</th>
                    <th>GeoJSON</th>
                    <th>CSV</th>
                    <th>Localidades</th>
                    <th>Última actualización</th>
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
                <tr>
                    <td data-label="Entidad" data-mobile-role="primary"><strong><?php echo esc_html( $m['nombre'] ); ?></strong></td>
                    <td data-label="CVE" data-mobile-role="secondary"><span class="siarhe-badge neutral"><?php echo esc_html( $m['cve'] ); ?></span></td>
                    <td data-label="GeoJSON"><?php echo $flag( $m['geo'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- marcado fijo ?></td>
                    <td data-label="CSV"><?php echo $flag( $m['csv'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
                    <td data-label="Localidades"><?php echo $flag( $m['loc'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
                    <td data-label="Última actualización">
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
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.SiarheAdmin) {
        window.SiarheAdmin.initMobileTables();
        document.querySelectorAll('.siarhe-date-formatter').forEach(function(el) {
            var raw = el.getAttribute('data-date');
            if (raw) el.textContent = window.SiarheAdmin.formatDate(raw);
        });
    }

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
