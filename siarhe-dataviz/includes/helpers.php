<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Obtiene el listado maestro de entidades federativas.
 * Sigue la taxonomía del INEGI estrictamente.
 * * Estructura:
 * - 'slug' (key): Identificador para nombres de archivo (minusculas, sin espacios).
 * - 'nombre': Nombre legible para humanos.
 * - 'CVE_ENT': Clave de Entidad (2 dígitos, String). Estándar INEGI.
 */
function siarhe_get_entities() {
    return [
        // NIVEL NACIONAL / INTERNACIONAL
        'republica-mexicana' => [ 'nombre' => 'República Mexicana', 'CVE_ENT' => '33' ], // Clave Virtual Nacional
        'extranjero'         => [ 'nombre' => 'Extranjero',         'CVE_ENT' => '34' ], // Clave Virtual Internacional

        // ENTIDADES FEDERATIVAS (INEGI 01-32)
        'aguascalientes'      => [ 'nombre' => 'Aguascalientes',      'CVE_ENT' => '01' ],
        'baja-california'     => [ 'nombre' => 'Baja California',     'CVE_ENT' => '02' ],
        'baja-california-sur' => [ 'nombre' => 'Baja California Sur', 'CVE_ENT' => '03' ],
        'campeche'            => [ 'nombre' => 'Campeche',            'CVE_ENT' => '04' ],
        'coahuila'            => [ 'nombre' => 'Coahuila',            'CVE_ENT' => '05' ],
        'colima'              => [ 'nombre' => 'Colima',              'CVE_ENT' => '06' ],
        'chiapas'             => [ 'nombre' => 'Chiapas',             'CVE_ENT' => '07' ],
        'chihuahua'           => [ 'nombre' => 'Chihuahua',           'CVE_ENT' => '08' ],
        'ciudad-de-mexico'    => [ 'nombre' => 'Ciudad de México',    'CVE_ENT' => '09' ],
        'durango'             => [ 'nombre' => 'Durango',             'CVE_ENT' => '10' ],
        'guanajuato'          => [ 'nombre' => 'Guanajuato',          'CVE_ENT' => '11' ],
        'guerrero'            => [ 'nombre' => 'Guerrero',            'CVE_ENT' => '12' ],
        'hidalgo'             => [ 'nombre' => 'Hidalgo',             'CVE_ENT' => '13' ],
        'jalisco'             => [ 'nombre' => 'Jalisco',             'CVE_ENT' => '14' ],
        'mexico'              => [ 'nombre' => 'Estado de México',    'CVE_ENT' => '15' ],
        'michoacan'           => [ 'nombre' => 'Michoacán',           'CVE_ENT' => '16' ],
        'morelos'             => [ 'nombre' => 'Morelos',             'CVE_ENT' => '17' ],
        'nayarit'             => [ 'nombre' => 'Nayarit',             'CVE_ENT' => '18' ],
        'nuevo-leon'          => [ 'nombre' => 'Nuevo León',          'CVE_ENT' => '19' ],
        'oaxaca'              => [ 'nombre' => 'Oaxaca',              'CVE_ENT' => '20' ],
        'puebla'              => [ 'nombre' => 'Puebla',              'CVE_ENT' => '21' ],
        'queretaro'           => [ 'nombre' => 'Querétaro',           'CVE_ENT' => '22' ],
        'quintana-roo'        => [ 'nombre' => 'Quintana Roo',        'CVE_ENT' => '23' ],
        'san-luis-potosi'     => [ 'nombre' => 'San Luis Potosí',     'CVE_ENT' => '24' ],
        'sinaloa'             => [ 'nombre' => 'Sinaloa',             'CVE_ENT' => '25' ],
        'sonora'              => [ 'nombre' => 'Sonora',              'CVE_ENT' => '26' ],
        'tabasco'             => [ 'nombre' => 'Tabasco',             'CVE_ENT' => '27' ],
        'tamaulipas'          => [ 'nombre' => 'Tamaulipas',          'CVE_ENT' => '28' ],
        'tlaxcala'            => [ 'nombre' => 'Tlaxcala',            'CVE_ENT' => '29' ],
        'veracruz'            => [ 'nombre' => 'Veracruz',            'CVE_ENT' => '30' ],
        'yucatan'             => [ 'nombre' => 'Yucatán',             'CVE_ENT' => '31' ],
        'zacatecas'           => [ 'nombre' => 'Zacatecas',           'CVE_ENT' => '32' ],

        // CAPAS ESPECIALES (Usamos claves altas para evitar conflictos con futuros estados)
        'clinicas-heridas'    => [ 'nombre' => 'Clínicas de Heridas',   'CVE_ENT' => '98' ],
        'clinicas-cateteres'  => [ 'nombre' => 'Clínicas de Catéteres', 'CVE_ENT' => '99' ]
    ];
}

/**
 * Busca el Slug a partir de la CVE_ENT.
 * Ej: '01' -> 'aguascalientes'
 */
function siarhe_get_slug_by_cve_ent( $cve_ent ) {
    $entities = siarhe_get_entities();
    foreach ( $entities as $slug => $data ) {
        if ( $data['CVE_ENT'] === $cve_ent ) {
            return $slug;
        }
    }
    return false;
}