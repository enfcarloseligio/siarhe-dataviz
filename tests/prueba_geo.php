<?php
// Prueba de Siarhe_Geo (marcos y validacion de TopoJSON) con los archivos reales de RHEMX, sin WordPress.
// Uso: php prueba_geo.php <carpeta del plugin> <carpeta rhemx>
define( 'ABSPATH', __DIR__ );
require $argv[1] . '/includes/class-siarhe-geo.php';
$geo = $argv[2] . '/web/data/geo/2025/';

$fallos = 0;
function ok( $cond, $msg ) { global $fallos; if ( ! $cond ) { $fallos++; echo "  FALLA: $msg\n"; } }
function lee( $f ) { return json_decode( file_get_contents( $f ), true ); }

echo "1. marcos\n";
$marcos = Siarhe_Geo::marcos();
echo '   disponibles: ' . implode( ', ', $marcos ) . "\n";
ok( $marcos[0] === 2025 && end( $marcos ) === 2005, 'de 2025 a 2005' );
ok( ! in_array( 2030, $marcos, true ), '2030 no aparece antes de 2030' );
ok( Siarhe_Geo::es_marco( 2015 ) && ! Siarhe_Geo::es_marco( 2016 ), '2015 es marco, 2016 no' );
ok( Siarhe_Geo::marco_para( 2026, array( 2025, 2020, 2015 ) ) === 2025, '2026 -> 2025' );
ok( Siarhe_Geo::marco_para( 2019, array( 2025, 2020, 2015 ) ) === 2015, '2019 -> 2015' );
ok( Siarhe_Geo::marco_para( 2012, array( 2025, 2020, 2015 ) ) === 2015, '2012 -> el mas antiguo' );
ok( Siarhe_Geo::marco_para( 2018, array( 2025 ) ) === 2025, '2018 con solo 2025' );
ok( Siarhe_Geo::marco_para( 2026, array() ) === 0, 'sin marcos' );

echo "2. validacion\n";
$casos = array(
    // archivo, capa, slug, cve, se espera error
    array( 'entidades.json',            'base', 'republica-mexicana', '33', false ),
    array( 'municipios_01.json',        'base', 'aguascalientes',     '01', false ),
    array( 'municipios_republica.json', 'loc',  'republica-mexicana', '33', false ),
    array( 'localidades_01.json',       'loc',  'aguascalientes',     '01', false ),
    array( 'localidades_01.json',       'base', 'aguascalientes',     '01', true ),  // capa equivocada
    array( 'municipios_01.json',        'base', 'republica-mexicana', '33', true ),  // municipios donde van entidades
    array( 'municipios_01.json',        'base', 'jalisco',            '14', true ),  // otra entidad
    array( 'entidades.json',            'loc',  'republica-mexicana', '33', true ),  // entidades donde van municipios
    array( 'agebs_01.json',             'loc',  'aguascalientes',     '01', true ),  // AGEB no es una capa del plugin
);
foreach ( $casos as $c ) {
    $r = Siarhe_Geo::validar_topologia( lee( $geo . $c[0] ), $c[1], $c[2], $c[3] );
    $hubo = $r['error'] !== '';
    ok( $hubo === $c[4], $c[0] . ' en ' . $c[1] . '/' . $c[2] . ': ' . ( $hubo ? $r['error'] : 'aceptado' ) );
    echo '   ' . str_pad( $c[0] . ' -> ' . $c[1] . '/' . $c[2], 62 ) . ( $hubo ? 'RECHAZADO: ' . $r['error'] : 'aceptado (' . $r['elementos'] . ' elementos' . ( $r['avisos'] ? '; avisos: ' . implode( ' | ', $r['avisos'] ) : '' ) . ')' ) . "\n";
}
$r = Siarhe_Geo::validar_topologia( array( 'type' => 'Topology' ), 'base', 'aguascalientes', '01' );
ok( $r['error'] !== '', 'topologia sin objects ni arcs' );
ok( ! Siarhe_Geo::es_topologia( array( 'type' => 'FeatureCollection' ) ), 'FeatureCollection no es topologia' );

echo $fallos ? "RESULTADO: $fallos falla(s)\n" : "RESULTADO: todo correcto\n";
exit( $fallos ? 1 : 0 );
