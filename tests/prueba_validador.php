<?php
// Archivo: tests/prueba_validador.php
// Descripción: prueba, sin WordPress y con los archivos reales de RHEMX, el orden de las entidades, los tipos de marcador,
// los temas de las bases estáticas, la comprobación de la entidad de una base (Siarhe_Validador) y la división de una capa
// de marcadores por entidad (Siarhe_Marker_Json).
// Uso: php prueba_validador.php <carpeta del plugin> <carpeta rhemx> <carpeta de salida>

// ==========================================
// 1. ENTORNO MÍNIMO (funciones de WordPress que usan las clases)
// ==========================================
define( 'ABSPATH', __DIR__ );
define( 'SIARHE_PATH', rtrim( $argv[1], '/\\' ) . '/' );
function wp_json_encode( $v, $o = 0 ) { return json_encode( $v, $o ); }
function get_option( $nombre, $defecto = '' ) { return $defecto; }
function wp_unslash( $v ) { return $v; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
require SIARHE_PATH . 'includes/helpers.php';
require SIARHE_PATH . 'includes/class-siarhe-static-json.php';
require SIARHE_PATH . 'includes/class-siarhe-geo.php';
require SIARHE_PATH . 'includes/class-siarhe-marker-json.php';
require SIARHE_PATH . 'includes/class-siarhe-validador.php';

$rhemx = rtrim( $argv[2], '/\\' );
$out   = rtrim( $argv[3], '/\\' ) . '/division/'; // carpeta propia: no pisa los archivos de las otras pruebas
@mkdir( $out, 0777, true );

$fallos = 0;
function ok( $cond, $msg ) { global $fallos; if ( ! $cond ) { $fallos++; echo "  FALLA: $msg\n"; } }
function lee_csv( $f ) { $r = array(); $h = fopen( $f, 'r' ); while ( ( $l = fgetcsv( $h, 0, ',', '"', '\\' ) ) !== false ) { if ( $l !== array( null ) ) $r[] = $l; } fclose( $h ); return $r; }
/** Primer archivo que coincide con el patrón (los nombres de RHEMX llevan o no la clave de la entidad al inicio). */
function primero( $patron ) { $g = glob( $patron ); return $g ? $g[0] : ''; }

// ==========================================
// 2. CATÁLOGOS DEL PANEL
// ==========================================
echo "1. orden de las entidades, tipos de marcador y temas\n";
$orden = array_keys( siarhe_get_entities_ordered() );
ok( count( $orden ) === 34, 'deben ser 34 entidades y son ' . count( $orden ) );
ok( $orden[0] === 'extranjero' && $orden[1] === 'republica-mexicana' && $orden[2] === 'aguascalientes' && end( $orden ) === 'zacatecas', 'orden: ' . implode( ', ', array_slice( $orden, 0, 3 ) ) );
$tipos = siarhe_marker_types();
ok( array_keys( $tipos ) === array( 'ESTABLECIMIENTOS', 'CATETER', 'HERIDAS', 'PASANTES' ), 'tipos de marcador: ' . implode( ', ', array_keys( $tipos ) ) );
ok( ! siarhe_marker_type_has_total( $tipos['ESTABLECIMIENTOS'] ) && ! siarhe_marker_type_has_total( $tipos['PASANTES'] ), 'establecimientos y pasantes van por año' );
ok( siarhe_marker_type_has_total( $tipos['CATETER'] ) && siarhe_marker_type_has_total( $tipos['HERIDAS'] ), 'las clínicas admiten «Todas»' );
$temas = siarhe_base_temas();
ok( array_keys( $temas ) === array( 'enfermeria', 'clinicas_cateteres', 'clinicas_heridas' ), 'temas: ' . implode( ', ', array_keys( $temas ) ) );
ok( array_keys( $temas['enfermeria']['unidades'] ) === array( 'plazas', 'personas' ), 'unidades de enfermería' );
ok( array_keys( $temas['clinicas_cateteres']['unidades'] ) === array( 'tasa', 'frecuencia' ), 'unidades de clínicas' );

// ==========================================
// 3. ENTIDAD DE UNA BASE ESTÁTICA
// ==========================================
echo "2. entidad de una base estatica (CSV y JSON de RHEMX)\n";
$anio = '';
foreach ( array_reverse( (array) glob( $rhemx . '/data/processed/mapas/*', GLOB_ONLYDIR ) ) as $carpeta ) {
    if ( primero( $carpeta . '/csv/*aguascalientes.csv' ) ) { $anio = basename( $carpeta ); break; }
}
if ( $anio === '' ) {
    echo "   (sin bases en data/processed/mapas: se omite)\n";
} else {
    $base = $rhemx . '/data/processed/mapas/' . $anio;
    $ags  = primero( $base . '/csv/*aguascalientes.csv' );
    $coah = primero( $base . '/csv/*coahuila.csv' );
    $nac  = primero( $base . '/csv/*republica-mexicana.csv' );
    ok( Siarhe_Validador::revisa_base_csv( $ags, 'aguascalientes' ) === '', 'Aguascalientes en Aguascalientes debe aceptarse' );
    ok( Siarhe_Validador::revisa_base_csv( $coah, 'coahuila' ) === '', 'Coahuila en Coahuila debe aceptarse' );
    $e = Siarhe_Validador::revisa_base_csv( $coah, 'campeche' );
    ok( strpos( $e, '05' ) !== false && strpos( $e, 'Campeche' ) !== false, 'Coahuila en Campeche debe rechazarse: ' . $e );
    echo '   ' . $e . "\n";
    $e = Siarhe_Validador::revisa_base_csv( $ags, 'republica-mexicana' );
    ok( $e !== '', 'una base estatal en República Mexicana debe rechazarse' );
    echo '   ' . $e . "\n";
    ok( Siarhe_Validador::revisa_base_csv( $nac, 'republica-mexicana' ) === '', 'la base nacional en República Mexicana debe aceptarse' );
    $e = Siarhe_Validador::revisa_base_csv( $nac, 'veracruz' );
    ok( strpos( $e, 'nacional' ) !== false, 'la base nacional en Veracruz debe rechazarse: ' . $e );
    echo '   ' . $e . "\n";
    ok( Siarhe_Validador::revisa_base_csv( $coah, 'extranjero' ) === '', 'Extranjero no se comprueba' );

    // Las 33 bases del año, cada una en su lugar y en el de la entidad siguiente
    $entidades = siarhe_get_entities();
    $revisadas = 0;
    foreach ( $entidades as $slug => $d ) {
        if ( $slug === 'extranjero' ) continue;
        $csv = primero( $base . '/csv/*' . ( $slug === 'mexico' ? 'estado-de-mexico' : $slug ) . '.csv' );
        if ( $csv === '' || ( $slug === 'mexico' ? false : basename( $csv, '.csv' ) !== $slug && ! preg_match( '/^\d\d_' . preg_quote( $slug, '/' ) . '$/', basename( $csv, '.csv' ) ) ) ) continue;
        $revisadas++;
        ok( Siarhe_Validador::revisa_base_csv( $csv, $slug ) === '', $slug . ': su propia base debe aceptarse (' . Siarhe_Validador::revisa_base_csv( $csv, $slug ) . ')' );
        $otra = ( $slug === 'zacatecas' ) ? 'aguascalientes' : ( $slug === 'republica-mexicana' ? 'oaxaca' : 'zacatecas' );
        ok( Siarhe_Validador::revisa_base_csv( $csv, $otra ) !== '', $slug . ': en ' . $otra . ' debe rechazarse' );
        $json = dirname( dirname( $csv ) ) . '/json/' . basename( $csv, '.csv' ) . '.json';
        if ( is_file( $json ) ) {
            $j = json_decode( file_get_contents( $json ), true );
            ok( Siarhe_Validador::revisa_base_json( $j, $slug ) === '', $slug . ': su JSON debe aceptarse' );
            ok( Siarhe_Validador::revisa_base_json( $j, $otra ) !== '', $slug . ': su JSON en ' . $otra . ' debe rechazarse' );
        }
    }
    echo '   ' . $revisadas . ' bases de ' . $anio . " revisadas en su lugar y en otro\n";
}

// Bases de las clínicas de catéteres (mismo formato, otras columnas)
$clin = primero( $rhemx . '/data/raw/Clinicas/Cateteres/mapas/*/csv/*campeche.csv' );
if ( $clin !== '' ) {
    ok( Siarhe_Validador::revisa_base_csv( $clin, 'campeche' ) === '', 'clínicas: Campeche en Campeche debe aceptarse' );
    ok( Siarhe_Validador::revisa_base_csv( $clin, 'colima' ) !== '', 'clínicas: Campeche en Colima debe rechazarse' );
    $j = Siarhe_Static_Json::from_csv( file_get_contents( $clin ), 2024, 'tasa' );
    ok( is_array( $j ) && ! Siarhe_Static_Json::validate( $j ), 'clínicas: el CSV se convierte al JSON del plugin' );
    echo "   base de clinicas de cateteres revisada\n";
}

// ==========================================
// 4. CAPA DE MARCADORES POR ENTIDAD
// ==========================================
echo "3. division de una capa de marcadores por entidad\n";
foreach ( array( 'unidades_clues_2026.json', 'clinicas_cateteres_historico.json', 'clinicas_heridas_historico.json' ) as $archivo ) {
    $origen = $rhemx . '/web/data/maps/' . $archivo;
    if ( ! is_file( $origen ) ) { echo "   (falta $archivo: se omite)\n"; continue; }
    $capa = $out . 'division-' . $archivo;
    copy( $origen, $capa );
    $m0 = memory_get_peak_usage(); $t0 = microtime( true );
    $n = Siarhe_Marker_Json::divide_por_entidad( $capa );
    ok( $n === 32, $archivo . ': deben escribirse 32 archivos y fueron ' . $n );

    // El CSV de la capa completa, filtrado por entidad, debe ser igual al CSV de cada versión
    $json = json_decode( file_get_contents( $capa ), true );
    Siarhe_Marker_Json::a_csv_archivo( $json, $out . 'division-completa.csv' );
    $completa = lee_csv( $out . 'division-completa.csv' );
    $i_ent = array_search( 'CLAVE DE LA ENTIDAD', $completa[0], true );
    $por_entidad = array();
    foreach ( array_slice( $completa, 1 ) as $f ) $por_entidad[ $f[ $i_ent ] ][] = $f;
    $suma = 0; $peso = 0;
    for ( $e = 1; $e <= 32; $e++ ) {
        $cve = str_pad( (string) $e, 2, '0', STR_PAD_LEFT );
        $ruta = Siarhe_Marker_Json::ruta_entidad( $capa, $cve );
        $parte = json_decode( file_get_contents( $ruta ), true );
        ok( Siarhe_Marker_Json::es_capa( $parte ) && $parte['fila'] === $json['fila'], $archivo . ' ' . $cve . ': conserva el formato de la capa' );
        $esperadas = isset( $por_entidad[ $cve ] ) ? $por_entidad[ $cve ] : array();
        $suma += count( $parte['unidades'] ); $peso += filesize( $ruta );
        if ( ! $parte['unidades'] ) { ok( ! $esperadas, $archivo . ' ' . $cve . ': no debería estar vacía' ); continue; }
        Siarhe_Marker_Json::a_csv_archivo( $parte, $out . 'division-parte.csv' );
        $filas = array_slice( lee_csv( $out . 'division-parte.csv' ), 1 );
        ok( $filas === $esperadas, $archivo . ' ' . $cve . ': sus filas son las de la capa completa (' . count( $filas ) . ' de ' . count( $esperadas ) . ')' );
    }
    $con_entidad = 0;
    foreach ( $por_entidad as $cve => $filas ) if ( (int) $cve >= 1 && (int) $cve <= 32 ) $con_entidad += count( $filas );
    ok( $suma === $con_entidad, $archivo . ': la suma de las entidades (' . $suma . ') es la de la capa (' . $con_entidad . ')' );
    echo '   ' . str_pad( $archivo, 36 ) . $suma . ' puntos; completa ' . round( filesize( $capa ) / 1024 ) . ' KB, promedio por entidad ' . round( $peso / 32 / 1024 ) . ' KB, mayor '
        . round( max( array_map( function ( $e ) use ( $capa ) { return filesize( Siarhe_Marker_Json::ruta_entidad( $capa, $e ) ); }, range( 1, 32 ) ) ) / 1024 ) . ' KB; '
        . round( microtime( true ) - $t0, 1 ) . ' s, memoria ' . round( ( memory_get_peak_usage() - $m0 ) / 1048576 ) . " MB\n";
    Siarhe_Marker_Json::borra_entidades( $capa );
    ok( ! is_file( Siarhe_Marker_Json::ruta_entidad( $capa, '01' ) ), $archivo . ': las versiones se borran con la capa' );
}
ok( Siarhe_Marker_Json::ruta_entidad( 'x/capa.json', '33' ) === '' && Siarhe_Marker_Json::ruta_entidad( 'x/capa.json', '00' ) === '', 'solo hay versión de las entidades 01 a 32' );

echo $fallos ? "\nRESULTADO: $fallos falla(s)\n" : "\nRESULTADO: todo correcto\n";
exit( $fallos ? 1 : 0 );
