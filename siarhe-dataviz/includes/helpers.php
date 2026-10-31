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
        'republica-mexicana' => [ 'nombre' => 'República Mexicana', 'CVE_ENT' => '33' ], // Clave Virtual Nacional
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

        // Botones del frontend (todos comparten estilo): normal, al pasar el cursor y al presionar
        'btn_bg'        => '#0A66C2', 'btn_border'        => '#0A66C2', 'btn_text'        => '#F8FAFC',
        'btn_hover_bg'  => '#084C94', 'btn_hover_border'  => '#084C94', 'btn_hover_text'  => '#F8FAFC',
        'btn_active_bg' => '#063A73', 'btn_active_border' => '#063A73', 'btn_active_text' => '#F8FAFC',

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
        '--s-btn-bg' => 'btn_bg', '--s-btn-border' => 'btn_border', '--s-btn-text' => 'btn_text',
        '--s-btn-hover-bg' => 'btn_hover_bg', '--s-btn-hover-border' => 'btn_hover_border', '--s-btn-hover-text' => 'btn_hover_text',
        '--s-btn-active-bg' => 'btn_active_bg', '--s-btn-active-border' => 'btn_active_border', '--s-btn-active-text' => 'btn_active_text',
    );

    $css = ":root {\n";
    foreach ( $vars as $var => $key ) {
        $css .= '    ' . $var . ': ' . $c[ $key ] . ";\n";
    }
    $css .= '    --s-border: ' . (int) $c['border_width'] . 'px solid ' . $c['border_color'] . ";\n}";

    return $css;
}

/**
 * Metricas nativas (protegidas) con su configuracion por defecto.
 * Las usan la pestana Configuracion > Metricas y el catalogo de columnas de las plantillas CSV.
 */
function siarhe_get_default_metricas() {
    $base_ranges = [
        'scale_type' => 'cuartiles',
        'custom_ranges' => false,
        'custom_colors' => false,
        'r1_min' => 'Vmin', 'r1_op' => '<', 'r1_max' => 'Q1',   'r1_color' => '',
        'r2_min' => 'Q1',   'r2_op' => '<', 'r2_max' => 'Q2',   'r2_color' => '',
        'r3_min' => 'Q2',   'r3_op' => '<', 'r3_max' => 'Q3',   'r3_color' => '',
        'r4_min' => 'Q3',   'r4_op' => '<=', 'r4_max' => 'Vmax', 'r4_color' => '' 
    ];

    $defaults = [
        'tasa_total'                 => array_merge(['label' => 'Tasa Total', 'fullLabel' => 'Tasa de enfermeras por cada mil habitantes', 'abrev' => 'Tasa Total', 'tipo' => 'tasa', 'pair' => 'enfermeras_total', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'enfermeras_total'           => array_merge(['label' => 'Total Enfermeras', 'fullLabel' => 'Total de profesionales de enfermería', 'abrev' => 'Total Enf.', 'tipo' => 'absoluto', 'pair' => 'enfermeras_total', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'tasa_primer'                => array_merge(['label' => 'Tasa Primer Nivel', 'fullLabel' => 'Tasa de enfermeras en establecimientos de primer nivel de atención', 'abrev' => 'Tasa Enf. 1er Nivel', 'tipo' => 'tasa', 'pair' => 'enfermeras_primer', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'enfermeras_primer'          => array_merge(['label' => 'Enfermeras Primer Nivel', 'fullLabel' => 'Enfermeras en establecimientos de primer nivel de atención', 'abrev' => 'Enf. 1er Nivel', 'tipo' => 'absoluto', 'pair' => 'enfermeras_primer', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'tasa_segundo'               => array_merge(['label' => 'Tasa Segundo Nivel', 'fullLabel' => 'Tasa de enfermeras en establecimientos de segundo nivel de atención', 'abrev' => 'Tasa Enf. 2do Nivel', 'tipo' => 'tasa', 'pair' => 'enfermeras_segundo', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'enfermeras_segundo'         => array_merge(['label' => 'Enfermeras Segundo Nivel', 'fullLabel' => 'Enfermeras en establecimientos de segundo nivel de atención', 'abrev' => 'Enf. 2do Nivel', 'tipo' => 'absoluto', 'pair' => 'enfermeras_segundo', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'tasa_tercer'                => array_merge(['label' => 'Tasa Tercer Nivel', 'fullLabel' => 'Tasa de enfermeras en establecimientos de tercer nivel de atención', 'abrev' => 'Tasa Enf. 3er Nivel', 'tipo' => 'tasa', 'pair' => 'enfermeras_tercer', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'enfermeras_tercer'          => array_merge(['label' => 'Enfermeras Tercer Nivel', 'fullLabel' => 'Enfermeras en establecimientos de tercer nivel de atención', 'abrev' => 'Enf. 3er Nivel', 'tipo' => 'absoluto', 'pair' => 'enfermeras_tercer', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'tasa_apoyo'                 => array_merge(['label' => 'Tasa Apoyo', 'fullLabel' => 'Tasa de enfermeras en establecimientos de apoyo', 'abrev' => 'Tasa Enf. Apoyo', 'tipo' => 'tasa', 'pair' => 'enfermeras_apoyo', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'enfermeras_apoyo'           => array_merge(['label' => 'Enfermeras Apoyo', 'fullLabel' => 'Enfermeras en establecimientos de apoyo', 'abrev' => 'Enf. Apoyo', 'tipo' => 'absoluto', 'pair' => 'enfermeras_apoyo', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'tasa_administrativas'       => array_merge(['label' => 'Tasa Enfermeras Administrativas', 'fullLabel' => 'Tasa de enfermeras en unidades administrativas', 'abrev' => 'Tasa Enf. Admin.', 'tipo' => 'tasa', 'pair' => 'enfermeras_administrativas', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'enfermeras_administrativas' => array_merge(['label' => 'Enfermeras Administrativas', 'fullLabel' => 'Enfermeras en unidades administrativas', 'abrev' => 'Enf. Admin.', 'tipo' => 'absoluto', 'pair' => 'enfermeras_administrativas', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'tasa_escuelas'              => array_merge(['label' => 'Tasa Enfermeras Escuelas', 'fullLabel' => 'Tasa de enfermeras en establecimientos formadores de recursos humanos en enfermería', 'abrev' => 'Tasa Enf. Escuelas', 'tipo' => 'tasa', 'pair' => 'enfermeras_escuelas', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'enfermeras_escuelas'        => array_merge(['label' => 'Enfermeras Escuelas', 'fullLabel' => 'Enfermeras en establecimientos formadores de recursos humanos en enfermería', 'abrev' => 'Enf. Escuelas', 'tipo' => 'absoluto', 'pair' => 'enfermeras_escuelas', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'tasa_no_aplica'             => array_merge(['label' => 'Tasa Enfermeras Otros', 'fullLabel' => 'Tasa de enfermeras en otros establecimientos', 'abrev' => 'Tasa Enf. Otros', 'tipo' => 'tasa', 'pair' => 'enfermeras_no_aplica', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'enfermeras_no_aplica'       => array_merge(['label' => 'Enfermeras Otros', 'fullLabel' => 'Enfermeras en otros establecimientos', 'abrev' => 'Enf. Otros', 'tipo' => 'absoluto', 'pair' => 'enfermeras_no_aplica', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'tasa_no_asignado'           => array_merge(['label' => 'Tasa Enfermeras No Asignado', 'fullLabel' => 'Tasa de enfermeras en establecimientos con tipo de establecimiento no asignado', 'abrev' => 'Tasa Enf. No Asignado', 'tipo' => 'tasa', 'pair' => 'enfermeras_no_asignado', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'enfermeras_no_asignado'     => array_merge(['label' => 'Enfermeras No Asignado', 'fullLabel' => 'Enfermeras en establecimientos con tipo de establecimiento no asignado', 'abrev' => 'Enf. No Asignado', 'tipo' => 'absoluto', 'pair' => 'enfermeras_no_asignado', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
        'poblacion'                  => array_merge(['label' => 'Población', 'fullLabel' => 'Población total', 'abrev' => 'Población', 'tipo' => 'absoluto', 'pair' => 'poblacion', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges)
    ];
    return $defaults;
}

/**
 * Contenido del .htaccess de la carpeta de datos (siarhe-data).
 * Con application/octet-stream (lo que muchos servidores asignan a .csv y .geojson) ni el servidor
 * ni Cloudflare comprimen los archivos: un GeoJSON de 7 MB viaja completo en cada carga del mapa.
 * Con el tipo correcto se comprimen solos (suele ser 5 a 10 veces menos).
 */
function siarhe_data_htaccess_content() {
    return "# SIARHE-DATA (lo genera el plugin y se regenera solo; no lo edites)\n"
        . "<IfModule mod_mime.c>\n"
        . "    AddType text/csv .csv\n"
        . "    AddType application/json .geojson .json\n"
        . "</IfModule>\n"
        . "<IfModule mod_deflate.c>\n"
        . "    AddOutputFilterByType DEFLATE text/csv application/json\n"
        . "</IfModule>\n";
}

/** Crea o actualiza el .htaccess de siarhe-data (no pisa uno que no sea del plugin). */
function siarhe_ensure_data_htaccess() {
    if ( ! defined( 'SIARHE_UPLOAD_DIR' ) || ! is_dir( SIARHE_UPLOAD_DIR ) || ! is_writable( SIARHE_UPLOAD_DIR ) ) return;
    $file    = SIARHE_UPLOAD_DIR . '.htaccess';
    $content = siarhe_data_htaccess_content();
    if ( file_exists( $file ) ) {
        $actual = (string) file_get_contents( $file );
        if ( $actual === $content ) return;
        if ( strpos( $actual, '# SIARHE-DATA' ) === false ) return; // archivo ajeno
    }
    @file_put_contents( $file, $content );
}

/**
 * URL de LECTURA de un archivo de datos grande (la que usa el mapa).
 * Muchos servidores no conocen las extensiones .csv y .geojson: los entregan como application/octet-stream
 * y no los comprimen (un GeoJSON nacional viaja completo, ~7 MB). Con .json o .txt si se comprimen (5 a 10 veces menos).
 * Por eso, para archivos de 100 KB o mas se mantiene una copia identica junto al original (.geojson.json / .csv.txt)
 * y el mapa lee esa. El original no cambia y sigue siendo el que se descarga y se respalda.
 * $url: URL del original dentro de siarhe-data, con o sin ?v=. Si no se puede crear la copia, devuelve $url.
 */
function siarhe_data_read_url( $url ) {
    if ( ! $url || ! defined( 'SIARHE_UPLOAD_DIR' ) || ! defined( 'SIARHE_UPLOAD_URL' ) ) return $url;
    $clean = strtok( $url, '?' );
    if ( strpos( $clean, SIARHE_UPLOAD_URL ) !== 0 ) return $url;

    $rel = ltrim( substr( $clean, strlen( SIARHE_UPLOAD_URL ) ), '/' );
    $res = siarhe_build_read_copy( SIARHE_UPLOAD_DIR . $rel );
    if ( $res['dst'] === '' ) return $url;
    return SIARHE_UPLOAD_URL . $rel . $res['suffix'] . '?v=' . filemtime( $res['dst'] );
}

/**
 * Crea (o actualiza) la copia de lectura de un archivo de datos.
 * Devuelve array( 'status' => creada|vigente|pequena|omitida|fallo, 'dst' => ruta de la copia o '', 'suffix' => '.json'|'.txt' ).
 */
function siarhe_build_read_copy( $src ) {
    $none = array( 'status' => 'omitida', 'dst' => '', 'suffix' => '' );
    if ( ! siarhe_read_copies_enabled() ) { $none['status'] = 'desactivada'; return $none; }
    $ext  = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
    $map  = array( 'geojson' => '.json', 'csv' => '.txt' );
    if ( ! isset( $map[ $ext ] ) || ! is_file( $src ) ) return $none;
    $none['suffix'] = $map[ $ext ];
    if ( filesize( $src ) < 100 * 1024 ) { $none['status'] = 'pequena'; return $none; }

    $dst = $src . $map[ $ext ];
    // Se regenera si no existe o si el original es mas nuevo (se reemplazo o se restauro una version)
    if ( is_file( $dst ) && filemtime( $dst ) >= filemtime( $src ) && filesize( $dst ) === filesize( $src ) ) {
        return array( 'status' => 'vigente', 'dst' => $dst, 'suffix' => $map[ $ext ] );
    }
    $tmp = $dst . '.tmp' . getmypid();
    if ( ! @copy( $src, $tmp ) ) { @unlink( $tmp ); $none['status'] = 'fallo'; return $none; }
    if ( ! @rename( $tmp, $dst ) ) {
        @unlink( $dst );
        if ( ! @rename( $tmp, $dst ) ) { @unlink( $tmp ); $none['status'] = 'fallo'; return $none; }
    }
    return array( 'status' => 'creada', 'dst' => $dst, 'suffix' => $map[ $ext ] );
}

/** Recorre las carpetas de datos y crea las copias de lectura que falten. Devuelve los conteos. */
function siarhe_generate_all_read_copies() {
    $out = array( 'creada' => 0, 'vigente' => 0, 'pequena' => 0, 'fallo' => 0, 'bytes' => 0 );
    if ( ! defined( 'SIARHE_UPLOAD_DIR' ) ) return $out;
    foreach ( Siarhe_Versions::dirs() as $dir ) {
        $folder = SIARHE_UPLOAD_DIR . $dir . '/';
        if ( ! is_dir( $folder ) ) continue;
        foreach ( (array) glob( $folder . '*.{csv,geojson}', GLOB_BRACE ) as $file ) {
            $res = siarhe_build_read_copy( $file );
            if ( isset( $out[ $res['status'] ] ) ) $out[ $res['status'] ]++;
            if ( $res['status'] === 'creada' ) $out['bytes'] += filesize( $res['dst'] );
        }
    }
    return $out;
}

/** Borra las copias de lectura de un archivo de datos (al eliminar el original). */
function siarhe_delete_data_read_copies( $filepath ) {
    foreach ( array( '.json', '.txt' ) as $suffix ) {
        if ( is_file( $filepath . $suffix ) ) @unlink( $filepath . $suffix );
    }
}

/** Opcion "Crear copias de lectura de los datos" (Configuracion > Versiones y Copias). Activa por defecto. */
function siarhe_read_copies_enabled() {
    return get_option( 'siarhe_read_copies', '1' ) !== '0';
}

/** Archivos de copia de lectura existentes (.geojson.json y .csv.txt) en las carpetas de datos. */
function siarhe_list_read_copies() {
    $files = array();
    if ( ! defined( 'SIARHE_UPLOAD_DIR' ) ) return $files;
    foreach ( Siarhe_Versions::dirs() as $dir ) {
        $folder = SIARHE_UPLOAD_DIR . $dir . '/';
        if ( ! is_dir( $folder ) ) continue;
        foreach ( (array) glob( $folder . '*.{geojson.json,csv.txt}', GLOB_BRACE ) as $f ) $files[] = $f;
    }
    return $files;
}

/** Cantidad y peso de las copias de lectura existentes. */
function siarhe_read_copies_summary() {
    $out = array( 'count' => 0, 'bytes' => 0 );
    foreach ( siarhe_list_read_copies() as $f ) { $out['count']++; $out['bytes'] += (int) filesize( $f ); }
    return $out;
}

/** Borra todas las copias de lectura (los originales no se tocan). Devuelve cuantas elimino. */
function siarhe_delete_all_read_copies() {
    $n = 0;
    foreach ( siarhe_list_read_copies() as $f ) { if ( @unlink( $f ) ) $n++; }
    return $n;
}

/** Al desactivar la opcion se eliminan las copias existentes. */
function siarhe_on_read_copies_changed( $old, $new = null ) {
    // add_option_{opcion} pasa (opcion, valor); update_option_{opcion} pasa (viejo, nuevo)
    $value = ( $new === null ) ? $old : $new;
    if ( (string) $value === '0' ) siarhe_delete_all_read_copies();
}
