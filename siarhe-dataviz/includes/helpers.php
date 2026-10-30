<?php // /includes/helpers.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Obtiene el listado maestro de entidades federativas.
 * Sigue la taxonomía del INEGI estrictamente.
 * Estructura:
 * - 'slug' (key): Identificador para nombres de archivo (minusculas, sin espacios).
 * - 'nombre': Nombre legible para humanos (Catálogo Oficial INEGI).
 * - 'CVE_ENT': Clave de Entidad (2 dígitos, String). Estándar INEGI.
 */
function siarhe_get_entities() {
    return [
        // NIVEL NACIONAL / INTERNACIONAL
        'republica-mexicana' => [ 'nombre' => 'México',     'CVE_ENT' => '33' ], // Clave Virtual Nacional
        'extranjero'         => [ 'nombre' => 'Extranjero', 'CVE_ENT' => '34' ], // Clave Virtual Internacional

        // ENTIDADES FEDERATIVAS (INEGI 01-32) - Nombres Oficiales
        'aguascalientes'      => [ 'nombre' => 'Aguascalientes',                  'CVE_ENT' => '01' ],
        'baja-california'     => [ 'nombre' => 'Baja California',                 'CVE_ENT' => '02' ],
        'baja-california-sur' => [ 'nombre' => 'Baja California Sur',             'CVE_ENT' => '03' ],
        'campeche'            => [ 'nombre' => 'Campeche',                        'CVE_ENT' => '04' ],
        'coahuila'            => [ 'nombre' => 'Coahuila de Zaragoza',            'CVE_ENT' => '05' ],
        'colima'              => [ 'nombre' => 'Colima',                          'CVE_ENT' => '06' ],
        'chiapas'             => [ 'nombre' => 'Chiapas',                         'CVE_ENT' => '07' ],
        'chihuahua'           => [ 'nombre' => 'Chihuahua',                       'CVE_ENT' => '08' ],
        'ciudad-de-mexico'    => [ 'nombre' => 'Ciudad de México',                'CVE_ENT' => '09' ],
        'durango'             => [ 'nombre' => 'Durango',                         'CVE_ENT' => '10' ],
        'guanajuato'          => [ 'nombre' => 'Guanajuato',                      'CVE_ENT' => '11' ],
        'guerrero'            => [ 'nombre' => 'Guerrero',                        'CVE_ENT' => '12' ],
        'hidalgo'             => [ 'nombre' => 'Hidalgo',                         'CVE_ENT' => '13' ],
        'jalisco'             => [ 'nombre' => 'Jalisco',                         'CVE_ENT' => '14' ],
        'mexico'              => [ 'nombre' => 'Estado de México',                'CVE_ENT' => '15' ],
        'michoacan'           => [ 'nombre' => 'Michoacán de Ocampo',             'CVE_ENT' => '16' ],
        'morelos'             => [ 'nombre' => 'Morelos',                         'CVE_ENT' => '17' ],
        'nayarit'             => [ 'nombre' => 'Nayarit',                         'CVE_ENT' => '18' ],
        'nuevo-leon'          => [ 'nombre' => 'Nuevo León',                      'CVE_ENT' => '19' ],
        'oaxaca'              => [ 'nombre' => 'Oaxaca',                          'CVE_ENT' => '20' ],
        'puebla'              => [ 'nombre' => 'Puebla',                          'CVE_ENT' => '21' ],
        'queretaro'           => [ 'nombre' => 'Querétaro',                       'CVE_ENT' => '22' ],
        'quintana-roo'        => [ 'nombre' => 'Quintana Roo',                    'CVE_ENT' => '23' ],
        'san-luis-potosi'     => [ 'nombre' => 'San Luis Potosí',                 'CVE_ENT' => '24' ],
        'sinaloa'             => [ 'nombre' => 'Sinaloa',                         'CVE_ENT' => '25' ],
        'sonora'              => [ 'nombre' => 'Sonora',                          'CVE_ENT' => '26' ],
        'tabasco'             => [ 'nombre' => 'Tabasco',                         'CVE_ENT' => '27' ],
        'tamaulipas'          => [ 'nombre' => 'Tamaulipas',                      'CVE_ENT' => '28' ],
        'tlaxcala'            => [ 'nombre' => 'Tlaxcala',                        'CVE_ENT' => '29' ],
        'veracruz'            => [ 'nombre' => 'Veracruz de Ignacio de la Llave', 'CVE_ENT' => '30' ],
        'yucatan'             => [ 'nombre' => 'Yucatán',                         'CVE_ENT' => '31' ],
        'zacatecas'           => [ 'nombre' => 'Zacatecas',                       'CVE_ENT' => '32' ],

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


/**
 * Catalogo CVE_ENT => nombre oficial (01-32 entidades, 33 nacional, 34 extranjero).
 * Se deriva de siarhe_get_entities(): un solo lugar donde corregir un nombre.
 */
function siarhe_get_entity_catalog() {
    $catalog = array();
    foreach ( siarhe_get_entities() as $data ) {
        if ( (int) $data['CVE_ENT'] <= 34 ) {
            $catalog[ $data['CVE_ENT'] ] = $data['nombre'];
        }
    }
    ksort( $catalog );
    return $catalog;
}

/**
 * Valores por defecto de la configuracion visual (colores del mapa y de la tabla).
 * Los usan el admin (Configuracion > Mapa), la vista publica y las variables CSS.
 */
function siarhe_get_map_defaults() {
    return array(
        // Mapa (secuencial de 5 pasos, cuartiles)
        'map_c1' => '#eff3ff', 'map_c2' => '#bdd7e7', 'map_c3' => '#6baed6', 'map_c4' => '#3182bd', 'map_c5' => '#08519c',

        // Mapa (4 rangos personalizados)
        'range_c1' => '#ffedd5', 'range_c2' => '#fdba74', 'range_c3' => '#f59e0b', 'range_c4' => '#b45309',

        // Mapa (secuencial monocromatico)
        'mono_min' => '#f0f9ff', 'mono_max' => '#0369a1',

        // Casos especiales: valor 0 y sin dato
        'map_zero' => '#d9d9d9', 'map_null' => '#000000',

        // Tabla del frontend
        'th_bg'        => '#f4f4f4', 'th_text'      => '#333333',
        'tr_odd'       => '#ffffff', 'tr_odd_txt'   => '#555555',
        'tr_even'      => '#f9f9f9', 'tr_even_txt'  => '#555555',
        'tr_total_bg'  => '#e8f4fd', 'tr_total_txt' => '#000000',
        'border_color' => '#dddddd', 'border_width' => '1',
    );
}

/** Opciones guardadas (siarhe_map_options) combinadas con los valores por defecto. */
function siarhe_get_map_options() {
    $saved = get_option( 'siarhe_map_options', array() );
    return wp_parse_args( is_array( $saved ) ? $saved : array(), siarhe_get_map_defaults() );
}

/**
 * Bloque :root con TODAS las variables CSS del frontend (mapa y tabla).
 * Se inyecta una sola vez con wp_add_inline_style desde Siarhe_Shortcodes.
 */
function siarhe_get_css_variables_css() {
    // Solo caracteres validos en un valor CSS de color o medida (evita inyectar CSS arbitrario)
    $c = array_map( function( $v ) {
        return preg_replace( '/[^#a-zA-Z0-9(),.%\s-]/', '', (string) $v );
    }, siarhe_get_map_options() );

    $vars = array(
        '--s-map-c1' => 'map_c1', '--s-map-c2' => 'map_c2', '--s-map-c3' => 'map_c3', '--s-map-c4' => 'map_c4', '--s-map-c5' => 'map_c5',
        '--s-map-range-1' => 'range_c1', '--s-map-range-2' => 'range_c2', '--s-map-range-3' => 'range_c3', '--s-map-range-4' => 'range_c4',
        '--s-map-mono-min' => 'mono_min', '--s-map-mono-max' => 'mono_max',
        '--s-map-zero' => 'map_zero', '--s-map-null' => 'map_null',
        '--s-th-bg' => 'th_bg', '--s-th-text' => 'th_text',
        '--s-tr-odd' => 'tr_odd', '--s-tr-odd-txt' => 'tr_odd_txt',
        '--s-tr-even' => 'tr_even', '--s-tr-even-txt' => 'tr_even_txt',
        '--s-total-bg' => 'tr_total_bg', '--s-total-txt' => 'tr_total_txt',
    );

    $css = ":root {\n";
    foreach ( $vars as $var => $key ) {
        $css .= '    ' . $var . ': ' . $c[ $key ] . ";\n";
    }
    $css .= '    --s-border: ' . (int) $c['border_width'] . 'px solid ' . $c['border_color'] . ";\n}";

    return $css;
}
