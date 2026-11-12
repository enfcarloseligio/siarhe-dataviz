<?php
// Prueba de Siarhe_Marker_Json con los archivos reales de RHEMX, sin WordPress.
// Uso: php prueba_marcadores.php <carpeta del plugin> <carpeta rhemx> <carpeta de salida>
define( 'ABSPATH', __DIR__ );
function wp_json_encode( $v, $o = 0 ) { return json_encode( $v, $o ); }
require $argv[1] . '/includes/class-siarhe-marker-json.php';
$rhemx = $argv[2]; $out = rtrim( $argv[3], '/\\' ) . '/';
@mkdir( $out, 0777, true );

$fallos = 0;
function ok( $cond, $msg ) { global $fallos; if ( ! $cond ) { $fallos++; echo "  FALLA: $msg\n"; } }
function lee_csv( $f ) { $r = array(); $h = fopen( $f, 'r' ); while ( ( $l = fgetcsv( $h ) ) !== false ) { if ( $l !== array( null ) ) $r[] = $l; } fclose( $h ); return $r; }

echo "1. periodos\n";
ok( Siarhe_Marker_Json::periodo( '2025' ) === '2025', '2025' );
ok( Siarhe_Marker_Json::periodo( 'total' ) === 'total' && Siarhe_Marker_Json::periodo( 'Historico' ) === 'total', 'total' );
ok( Siarhe_Marker_Json::periodo( '1990' ) === '' && Siarhe_Marker_Json::periodo( 'x' ) === '', 'invalidos' );

echo "2. capas JSON de RHEMX -> CSV -> JSON\n";
foreach ( array( 'unidades_clues_2026.json', 'clinicas_cateteres_2025.json', 'clinicas_cateteres_historico.json', 'clinicas_heridas_historico.json' ) as $archivo ) {
    $json = json_decode( file_get_contents( $rhemx . '/web/data/maps/' . $archivo ), true );
    $rev = Siarhe_Marker_Json::validar( $json );
    ok( $rev['error'] === '', $archivo . ': ' . $rev['error'] );
    $base = $out . basename( $archivo, '.json' );
    ok( Siarhe_Marker_Json::a_csv_archivo( $json, $base . '.csv' ), $archivo . ': escribir CSV' );
    $n = Siarhe_Marker_Json::desde_csv_archivo( $base . '.csv', $base . '.ida-vuelta.json', '2026' );
    ok( is_int( $n ) && $n === count( $json['unidades'] ), $archivo . ': filas ' . var_export( $n, true ) . ' de ' . count( $json['unidades'] ) );
    // El JSON reconstruido debe dar el mismo CSV, celda por celda
    $j2 = json_decode( file_get_contents( $base . '.ida-vuelta.json' ), true );
    ok( is_array( $j2 ) && Siarhe_Marker_Json::validar( $j2 )['error'] === '', $archivo . ': JSON reconstruido valido' );
    Siarhe_Marker_Json::a_csv_archivo( $j2, $base . '.ida-vuelta.csv' );
    ok( lee_csv( $base . '.csv' ) === lee_csv( $base . '.ida-vuelta.csv' ), $archivo . ': el CSV de ida y vuelta es identico' );
    echo '   ' . str_pad( $archivo, 38 ) . $n . ' filas; JSON ' . round( filesize( $rhemx . '/web/data/maps/' . $archivo ) / 1024 ) . ' KB, CSV ' . round( filesize( $base . '.csv' ) / 1024 ) . ' KB'
        . ( $rev['avisos'] ? '; avisos: ' . implode( ' | ', $rev['avisos'] ) : '' ) . "\n";
}

echo "3. CSV del catalogo CLUES de RHEMX -> JSON -> CSV\n";
$csv = $rhemx . '/data/processed/CLUES/2026/clues_2026.csv';
if ( is_file( $csv ) ) {
    $t0 = microtime( true ); $m0 = memory_get_peak_usage();
    $n = Siarhe_Marker_Json::desde_csv_archivo( $csv, $out . 'clues_2026.json', '2026' );
    ok( is_int( $n ), 'clues_2026.csv: ' . var_export( $n, true ) );
    $j = json_decode( file_get_contents( $out . 'clues_2026.json' ), true );
    ok( Siarhe_Marker_Json::validar( $j )['error'] === '', 'JSON del catalogo valido' );
    Siarhe_Marker_Json::a_csv_archivo( $j, $out . 'clues_2026.ida-vuelta.csv' );
    $a = lee_csv( $csv ); $b = lee_csv( $out . 'clues_2026.ida-vuelta.csv' );
    ok( $a[0] === $b[0], 'mismos encabezados' );
    $dif = 0; $ej = '';
    for ( $i = 1; $i < count( $a ); $i++ ) {
        for ( $c = 0; $c < count( $a[0] ); $c++ ) {
            $x = trim( (string) $a[ $i ][ $c ] ); $y = isset( $b[ $i ][ $c ] ) ? (string) $b[ $i ][ $c ] : '(falta)';
            $igual = ( $x === $y ) || ( is_numeric( $x ) && is_numeric( $y ) && abs( (float) $x - (float) $y ) < 1e-9 );
            if ( ! $igual ) { $dif++; if ( $ej === '' ) $ej = $a[0][ $c ] . ": [$x] vs [$y]"; }
        }
    }
    ok( $dif === 0, "celdas distintas: $dif (ej. $ej)" );
    echo '   clues_2026.csv: ' . $n . ' filas; CSV ' . round( filesize( $csv ) / 1048576, 1 ) . ' MB -> JSON ' . round( filesize( $out . 'clues_2026.json' ) / 1048576, 1 ) . ' MB; conversion '
        . round( microtime( true ) - $t0, 1 ) . ' s' . "\n";
} else {
    echo "   (no existe clues_2026.csv; se omite)\n";
}

echo "4. rechazos\n";
ok( Siarhe_Marker_Json::validar( array( 'formato' => 'siarhe-estatico', 'columnas' => array(), 'filas' => array() ) )['error'] !== '', 'una base estatica no es una capa' );
ok( Siarhe_Marker_Json::validar( array( 'fila' => array( 'clues' ), 'unidades' => array( array( 'X' ) ) ) )['error'] !== '', 'capa sin coordenadas' );

echo $fallos ? "RESULTADO: $fallos falla(s)\n" : "RESULTADO: todo correcto\n";
exit( $fallos ? 1 : 0 );
