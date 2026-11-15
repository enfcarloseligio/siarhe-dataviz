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
    ];
}

/**
 * Posición de una entidad en los selectores y las tablas del panel: primero Extranjero, después República Mexicana
 * y después las entidades por su clave (01 a 32).
 */
function siarhe_entity_order( $slug ) {
    if ( $slug === 'extranjero' ) return -2;
    if ( $slug === 'republica-mexicana' ) return -1;
    $entities = siarhe_get_entities();
    return isset( $entities[ $slug ] ) ? (int) $entities[ $slug ]['CVE_ENT'] : 999;
}

/** Entidades en el orden del panel (ver siarhe_entity_order). */
function siarhe_get_entities_ordered() {
    $entities = siarhe_get_entities();
    uksort( $entities, function ( $a, $b ) { return siarhe_entity_order( $a ) <=> siarhe_entity_order( $b ); } );
    return $entities;
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
        // Colores de los indicadores: los del mapa de RHEMX (rhemx/web/assets/js/geoespacial.js, COLORES)
        // Degradado de 5 pasos (minimo, Q1, mediana, Q3, maximo)
        'map_c1' => '#9b2247', 'map_c2' => '#ffa500', 'map_c3' => '#e6d194', 'map_c4' => '#008000', 'map_c5' => '#006400',

        // 4 rangos
        'range_c1' => '#e6f4ea', 'range_c2' => '#9fd5b5', 'range_c3' => '#3faf7b', 'range_c4' => '#1e5b4f',

        // Monocromatico (claro y oscuro)
        'mono_min' => '#e5e0d3', 'mono_max' => '#a57100',

        // Casos especiales: valor 0 y sin dato
        'map_zero' => '#b5b4c3', 'map_null' => '#000000',

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

        // Disposición de la página del mapa: 'contenedor' (centrado, máximo 1140 px) o 'canvas' (ancho completo) y su margen lateral (%)
        'layout' => 'contenedor', 'canvas_margin' => '2.5',
    );
}

/**
 * Claves de siarhe_map_options que guardan los colores de los indicadores (degradado, rangos, monocromatico, cero y sin dato).
 */
function siarhe_map_color_keys() {
    return array( 'map_c1', 'map_c2', 'map_c3', 'map_c4', 'map_c5', 'range_c1', 'range_c2', 'range_c3', 'range_c4', 'mono_min', 'mono_max', 'map_zero', 'map_null' );
}

/**
 * Formas de marcador que se pueden elegir: las 14 de RHEMX con sus nombres (siarhe-core.js, app.utils.NOMBRES_FORMA).
 */
function siarhe_marker_formas() {
    return array(
        'circle' => 'Círculo', 'ellipse' => 'Elipse', 'square' => 'Cuadrado', 'square2' => 'Cuadrado chico', 'triangle' => 'Triángulo',
        'triangle2' => 'Triángulo invertido', 'diamond' => 'Diamante', 'diamond2' => 'Rombo', 'star' => 'Estrella', 'cross' => 'Cruz',
        'plus' => 'Más', 'times' => 'Equis', 'asterisk' => 'Asterisco', 'wye' => 'Ye',
    );
}

/**
 * Estilo por defecto de los marcadores del sistema (forma, relleno y borde), los de RHEMX: los niveles de atencion son
 * cruces verde, dorada y guinda, «No Aplica» un triangulo y «No Especificado» un circulo grises.
 */
function siarhe_marker_style_defaults() {
    return array(
        'ESTAB_1'  => array( 'shape' => 'cross',     'fill' => '#1E5B4F', 'stroke' => '#ffffff' ),
        'ESTAB_2'  => array( 'shape' => 'cross',     'fill' => '#A57F2C', 'stroke' => '#ffffff' ),
        'ESTAB_3'  => array( 'shape' => 'cross',     'fill' => '#9B2247', 'stroke' => '#ffffff' ),
        'ESTAB_6'  => array( 'shape' => 'triangle',  'fill' => '#57595C', 'stroke' => '#ffffff' ),
        'ESTAB_NE' => array( 'shape' => 'circle',    'fill' => '#475569', 'stroke' => '#ffffff' ),
        'CATETER'  => array( 'shape' => 'triangle',  'fill' => '#1E5B4F', 'stroke' => '#ffffff' ),
        'HERIDAS'  => array( 'shape' => 'triangle2', 'fill' => '#9B2247', 'stroke' => '#ffffff' ),
        'PASANTES' => array( 'shape' => 'circle',    'fill' => '#A57F2C', 'stroke' => '#ffffff' ),
    );
}

/**
 * Estilo vigente de un marcador: lo guardado en Personalizar (siarhe_map_options, claves m_<clave>_*) o, si no hay, el
 * del sistema; los marcadores propios sin estilo guardado salen como circulo azul (o rojo si son de densidad).
 * Devuelve array( 'shape', 'fill', 'fill2', 'stroke' ).
 */
function siarhe_marker_style( $key, $mk, $opts ) {
    $s    = strtolower( $key );
    $tipo = isset( $mk['tipo'] ) ? $mk['tipo'] : 'posicion';
    $defs = siarhe_marker_style_defaults();
    if ( isset( $defs[ $key ] ) ) {
        $def = $defs[ $key ];
    } else {
        $def = array( 'shape' => 'circle', 'stroke' => '#ffffff',
            'fill' => $tipo === 'espectro' ? 'rgba(214, 54, 56, 0.6)' : ( $tipo === 'spike' ? '#D63638' : '#0A66C2' ) );
    }
    $valor = function( $campo, $por_defecto ) use ( $opts, $s ) {
        $k = "m_{$s}_{$campo}";
        return ( isset( $opts[ $k ] ) && $opts[ $k ] !== '' ) ? $opts[ $k ] : $por_defecto;
    };
    return array(
        'shape'  => ( $tipo === 'espectro' || $tipo === 'spike' ) ? 'circle' : $valor( 'shape', $def['shape'] ),
        'fill'   => $valor( 'fill', $def['fill'] ),
        'fill2'  => isset( $opts[ "m_{$s}_fill2" ] ) ? $opts[ "m_{$s}_fill2" ] : '',   // espinas: color del pico mas bajo
        'stroke' => $valor( 'stroke', $def['stroke'] ),
    );
}

/**
 * Disposición de la página del mapa: array( 'modo' => 'contenedor' | 'canvas', 'margen' => porcentaje lateral del canvas ).
 * El margen se mantiene entre 2.5 % y 5 %, el rango de la regla de diseño.
 */
function siarhe_get_layout() {
    // Se guarda en Configuración › General (siarhe_general_options); lo elegido antes en Personalizar › Mapa y Colores
    // (siarhe_map_options) sigue valiendo mientras no se guarde la pestaña General
    $opts    = siarhe_get_map_options();
    $general = get_option( 'siarhe_general_options', array() );
    if ( is_array( $general ) ) {
        foreach ( array( 'layout', 'canvas_margin' ) as $clave ) {
            if ( isset( $general[ $clave ] ) && $general[ $clave ] !== '' ) $opts[ $clave ] = $general[ $clave ];
        }
    }
    $margen = (float) str_replace( ',', '.', (string) $opts['canvas_margin'] );
    return array(
        'modo'   => ( $opts['layout'] === 'canvas' ) ? 'canvas' : 'contenedor',
        'margen' => min( 5, max( 2.5, $margen ) ),
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

    // Cada variable da dos columnas: enfermeras_<clave> (frecuencia) y tasa_<clave> (tasa por mil habitantes). La descripcion usa
    // {unidad} / {Unidad}, que el mapa sustituye por "plazas" o "personas" segun la base que se este viendo.
    // [clave, nombre corto, tema, etiqueta de la tasa, abreviatura de la tasa, etiqueta de la frecuencia, abreviatura de la frecuencia, descripcion, opcional]
    $variables = [
        ['total',           'Total',           'general',        'Tasa Total',                     'Tasa Total',            'Total Enfermeras',           'Total Enf.',       '{Unidad} de enfermería en total', false],
        ['primer',          'Primer nivel',    'nivel_atencion', 'Tasa Primer Nivel',              'Tasa Enf. 1er Nivel',   'Enfermeras Primer Nivel',    'Enf. 1er Nivel',   '{Unidad} en establecimientos de primer nivel de atención', false],
        ['segundo',         'Segundo nivel',   'nivel_atencion', 'Tasa Segundo Nivel',             'Tasa Enf. 2do Nivel',   'Enfermeras Segundo Nivel',   'Enf. 2do Nivel',   '{Unidad} en establecimientos de segundo nivel de atención', false],
        ['tercer',          'Tercer nivel',    'nivel_atencion', 'Tasa Tercer Nivel',              'Tasa Enf. 3er Nivel',   'Enfermeras Tercer Nivel',    'Enf. 3er Nivel',   '{Unidad} en establecimientos de tercer nivel de atención', false],
        ['apoyo',           'Apoyo',           'nivel_atencion', 'Tasa Apoyo',                     'Tasa Enf. Apoyo',       'Enfermeras Apoyo',           'Enf. Apoyo',       '{Unidad} en establecimientos de apoyo', false],
        ['administrativas', 'Administrativas', 'nivel_atencion', 'Tasa Enfermeras Administrativas', 'Tasa Enf. Admin.',     'Enfermeras Administrativas', 'Enf. Admin.',      '{Unidad} en unidades administrativas', false],
        ['escuelas',        'Escuelas',        'nivel_atencion', 'Tasa Enfermeras Escuelas',       'Tasa Enf. Escuelas',    'Enfermeras Escuelas',        'Enf. Escuelas',    '{Unidad} en establecimientos formadores de recursos humanos en enfermería', false],
        ['no_aplica',       'Otros',           'nivel_atencion', 'Tasa Enfermeras Otros',          'Tasa Enf. Otros',       'Enfermeras Otros',           'Enf. Otros',       '{Unidad} en otros establecimientos', false],
        ['no_asignado',     'No asignado',     'nivel_atencion', 'Tasa Enfermeras No Asignado',    'Tasa Enf. No Asignado', 'Enfermeras No Asignado',     'Enf. No Asignado', '{Unidad} en establecimientos con tipo de establecimiento no asignado', false],
    ];

    // Estudios (con LEO) y areas de especializacion de RHEMX: opcionales, solo se muestran si la base trae la columna
    $extra = include SIARHE_PATH . 'includes/metricas-rhemx.php';
    foreach ( $extra as $v ) {
        list( $clave, $grupo, $nombre, $desc ) = $v;
        $variables[] = [ $clave, $nombre, ( $grupo === 'Estudios' ? 'estudios' : 'especializacion' ), 'Tasa ' . $nombre, 'Tasa ' . $nombre,
            'Núm. ' . $nombre, $nombre, preg_replace( '/^Plazas\b/', '{Unidad}', $desc ), true ];
    }

    $defaults = [];
    foreach ( $variables as $v ) {
        list( $clave, $nombre, $tema, $l_tasa, $a_tasa, $l_abs, $a_abs, $desc, $opcional ) = $v;
        $comun = [ 'nombre' => $nombre, 'tema' => $tema, 'descripcion' => $desc, 'is_core' => true, 'visibilidad' => 'publico' ];
        if ( $opcional ) $comun['opcional'] = true;
        $defaults[ 'tasa_' . $clave ] = array_merge( [ 'label' => $l_tasa, 'fullLabel' => 'Tasa por mil habitantes: ' . $nombre, 'abrev' => $a_tasa, 'tipo' => 'tasa', 'pair' => 'enfermeras_' . $clave ], $comun, $base_ranges );
        $defaults[ 'enfermeras_' . $clave ] = array_merge( [ 'label' => $l_abs, 'fullLabel' => 'Frecuencia: ' . $nombre, 'abrev' => $a_abs, 'tipo' => 'absoluto', 'pair' => 'enfermeras_' . $clave ], $comun, $base_ranges );
    }
    $defaults['tasa_total']['fullLabel'] = 'Tasa de enfermeras por cada mil habitantes';
    $defaults['enfermeras_total']['fullLabel'] = 'Total de profesionales de enfermería';
    $defaults['poblacion'] = array_merge( [ 'label' => 'Población', 'fullLabel' => 'Población total', 'abrev' => 'Población', 'tipo' => 'absoluto', 'pair' => 'poblacion',
        'nombre' => 'Población', 'tema' => 'general', 'descripcion' => 'Población total', 'is_core' => true, 'visibilidad' => 'publico' ], $base_ranges );
    return $defaults;
}

/**
 * Configuracion de metricas vigente. Las metricas del sistema (is_core) siempre salen con sus textos, tipo, par y tema originales:
 * del panel solo se respetan su visibilidad y su escala/colores. Las que un administrador agrego se conservan tal cual.
 */
function siarhe_get_metricas_config() {
    $defaults = siarhe_get_default_metricas();
    $saved = siarhe_decode_json_option( get_option( 'siarhe_metricas_config', '' ) );
    if ( empty( $saved ) || ! is_array( $saved ) ) return $defaults;

    $conserva = array( 'visibilidad', 'scale_type', 'custom_ranges', 'custom_colors',
        'r1_min', 'r1_op', 'r1_max', 'r1_color', 'r2_min', 'r2_op', 'r2_max', 'r2_color',
        'r3_min', 'r3_op', 'r3_max', 'r3_color', 'r4_min', 'r4_op', 'r4_max', 'r4_color' );
    foreach ( $defaults as $key => $def ) {
        if ( isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) {
            $forzada = $def;
            foreach ( $conserva as $campo ) {
                if ( array_key_exists( $campo, $saved[ $key ] ) ) $forzada[ $campo ] = $saved[ $key ][ $campo ];
            }
            $saved[ $key ] = $forzada;
        } else {
            $saved[ $key ] = $def;
        }
    }
    // Toda metrica pertenece a un tema; las que no tienen (o tienen uno que ya no existe) quedan en "general"
    $temas = siarhe_get_temas();
    foreach ( $saved as $key => $m ) {
        if ( ! is_array( $m ) ) continue;
        if ( empty( $m['tema'] ) || ! isset( $temas[ $m['tema'] ] ) ) $saved[ $key ]['tema'] = 'general';
    }
    return $saved;
}

/**
 * Tipos de archivo de marcadores con que viene el plugin (Carga de Datos > Marcadores), en el orden en que se ofrecen. Cada
 * tipo es una familia de archivos, uno por periodo, que se guardan como {nombre}-{periodo}.csv y .json. "periodos" dice qué
 * periodos admite: "anios" (un archivo por año) o "total_anios" (además, «Todas»: las unidades de toda la historia).
 */
function siarhe_marker_core_types() {
    return array(
        'ESTABLECIMIENTOS' => array( 'label' => 'Establecimientos de Salud', 'filename' => 'establecimientos-salud.csv', 'is_core' => true, 'periodos' => 'anios' ),
        'CATETER'          => array( 'label' => 'Clínicas de Catéteres', 'filename' => 'clinicas-cateteres.csv', 'is_core' => true, 'periodos' => 'total_anios' ),
        'HERIDAS'          => array( 'label' => 'Clínicas de Heridas', 'filename' => 'clinicas-heridas.csv', 'is_core' => true, 'periodos' => 'total_anios' ),
        'PASANTES'         => array( 'label' => 'Pasantes de Enfermería', 'filename' => 'pasantes-enfermeria.csv', 'is_core' => true, 'periodos' => 'anios' ),
    );
}

/**
 * Tipos de archivo de marcadores vigentes: primero los del plugin, siempre con sus textos originales, y después los que agregó
 * el administrador (opción siarhe_archivos_marcadores), que al crearlos declara si tienen serie completa («Todas» y años)
 * o si solo se cargan por año.
 */
function siarhe_marker_types() {
    $tipos     = siarhe_marker_core_types();
    $guardados = siarhe_decode_json_option( get_option( 'siarhe_archivos_marcadores', '' ) );
    if ( is_array( $guardados ) ) {
        foreach ( $guardados as $clave => $info ) {
            if ( isset( $tipos[ $clave ] ) || ! is_array( $info ) || empty( $info['filename'] ) ) continue;
            $tipos[ $clave ] = array(
                'label'    => ( isset( $info['label'] ) && $info['label'] !== '' ) ? (string) $info['label'] : (string) $clave,
                'filename' => (string) $info['filename'],
                'is_core'  => false,
                'periodos' => ( isset( $info['periodos'] ) && $info['periodos'] === 'anios' ) ? 'anios' : 'total_anios',
            );
        }
    }
    return $tipos;
}

/** Indica si un tipo de marcador admite el periodo «Todas» (toda la historia) además de los años. */
function siarhe_marker_type_has_total( $info ) {
    return ! isset( $info['periodos'] ) || $info['periodos'] !== 'anios';
}

/**
 * Archivos de marcadores cargados, por nombre de archivo del tipo y periodo: array( 'clinicas-cateteres.csv' =>
 * array( '2025' => url, 'total' => url ) ). La URL es la del JSON si existe (pesa menos) y, si no, la del CSV. Un archivo
 * copiado a la carpeta sin registro (por FTP) cuenta como "total".
 * Con $cve_ent de una entidad (01 a 32) se entrega la versión de esa entidad (solo sus puntos), que el plugin crea al cargar
 * el archivo completo; si no existe o es anterior al archivo completo se entrega este y el mapa filtra los puntos.
 */
function siarhe_get_marker_file_urls( $cve_ent = '' ) {
    global $wpdb;
    $out   = array();
    $tipos = siarhe_marker_types();
    $filas = $wpdb->get_results( "SELECT entidad_slug, ruta_archivo, ruta_json, anio_reporte FROM {$wpdb->prefix}siarhe_static_assets WHERE tipo_archivo = 'marcador' AND es_activo = 1" );
    foreach ( (array) $filas as $f ) {
        if ( ! isset( $tipos[ $f->entidad_slug ] ) ) continue;
        $es_json = ! empty( $f->ruta_json ) && file_exists( SIARHE_UPLOAD_DIR . ltrim( $f->ruta_json, '/' ) );
        $rel     = ltrim( $es_json ? $f->ruta_json : $f->ruta_archivo, '/' );
        $ruta    = SIARHE_UPLOAD_DIR . $rel;
        if ( $rel === '' || ! file_exists( $ruta ) ) continue;
        $periodo = ( (int) $f->anio_reporte > 0 ) ? (string) (int) $f->anio_reporte : Siarhe_Marker_Json::PERIODO_TOTAL;

        if ( $es_json && $cve_ent !== '' ) {
            $parte = Siarhe_Marker_Json::ruta_entidad( $ruta, $cve_ent );
            if ( $parte !== '' && is_file( $parte ) && filemtime( $parte ) >= filemtime( $ruta ) ) {
                $out[ $tipos[ $f->entidad_slug ]['filename'] ][ $periodo ] = SIARHE_UPLOAD_URL . 'markers/' . Siarhe_Marker_Json::DIR_ENTIDADES . '/' . basename( $parte ) . '?v=' . filemtime( $parte );
                continue;
            }
        }
        $url = SIARHE_UPLOAD_URL . $rel . '?v=' . filemtime( $ruta );
        if ( ! $es_json ) $url = siarhe_data_read_url( $url );
        $out[ $tipos[ $f->entidad_slug ]['filename'] ][ $periodo ] = $url;
    }
    foreach ( $tipos as $info ) {
        $ruta = SIARHE_UPLOAD_DIR . 'markers/' . $info['filename'];
        if ( empty( $out[ $info['filename'] ] ) && file_exists( $ruta ) ) {
            $out[ $info['filename'] ][ Siarhe_Marker_Json::PERIODO_TOTAL ] = siarhe_data_read_url( SIARHE_UPLOAD_URL . 'markers/' . $info['filename'] . '?v=' . filemtime( $ruta ) );
        }
    }
    return $out;
}

/**
 * Temas de las bases estáticas (Carga de Datos > Bases Estáticas). Un tema agrupa las bases de un mismo asunto y define sus
 * unidades de conteo; cada base es entidad + año + tema + unidad. Los del sistema son Enfermería (plazas y recursos humanos)
 * y las clínicas de catéteres y de heridas (grupo «Clínicas», tasa y frecuencia); el administrador puede crear los suyos,
 * que se guardan en la opción siarhe_bases_temas como { clave: { label, unidades: [..] } }.
 * @return array clave => array( 'label', 'grupo' ('' o 'clinicas'), 'unidades' => array( clave => etiqueta ), 'sistema' )
 */
function siarhe_base_temas() {
    $temas = array(
        'enfermeria' => array( 'label' => 'Enfermería', 'grupo' => '', 'sistema' => true,
            'unidades' => array( 'plazas' => 'Plazas de enfermería', 'personas' => 'Recursos humanos de enfermería' ) ),
        'clinicas_cateteres' => array( 'label' => 'Clínicas de catéteres', 'grupo' => 'clinicas', 'sistema' => true,
            'unidades' => array( 'tasa' => 'Tasa', 'frecuencia' => 'Frecuencia' ) ),
        'clinicas_heridas' => array( 'label' => 'Clínicas de heridas', 'grupo' => 'clinicas', 'sistema' => true,
            'unidades' => array( 'tasa' => 'Tasa', 'frecuencia' => 'Frecuencia' ) ),
    );
    $catalogo  = siarhe_base_unidades();
    $guardados = siarhe_decode_json_option( get_option( 'siarhe_bases_temas', '' ) );
    if ( is_array( $guardados ) ) {
        foreach ( $guardados as $clave => $t ) {
            $clave = sanitize_key( $clave );
            if ( $clave === '' || isset( $temas[ $clave ] ) || ! is_array( $t ) ) continue;
            $label = isset( $t['label'] ) ? trim( (string) $t['label'] ) : '';
            if ( $label === '' ) continue;
            $unidades = array();
            foreach ( (array) ( isset( $t['unidades'] ) ? $t['unidades'] : array() ) as $u ) {
                if ( isset( $catalogo[ $u ] ) ) $unidades[ $u ] = $catalogo[ $u ];
            }
            if ( ! $unidades ) $unidades = array( 'frecuencia' => $catalogo['frecuencia'] );
            $temas[ $clave ] = array( 'label' => $label, 'grupo' => '', 'sistema' => false, 'unidades' => $unidades );
        }
    }
    return $temas;
}

/** Unidades de conteo que puede tener un tema de bases estáticas creado por el administrador. */
function siarhe_base_unidades() {
    return array( 'frecuencia' => 'Frecuencia', 'tasa' => 'Tasa', 'plazas' => 'Plazas', 'personas' => 'Recursos humanos' );
}

/** Tema de bases estáticas que alimenta el mapa público. */
function siarhe_base_tema_mapa() {
    return 'enfermeria';
}

/**
 * Marcadores con que viene el plugin. Los de establecimientos de salud por nivel de atencion son del sistema ("sistema"):
 * llevan los mismos textos que RHEMX y filtran el catalogo por su columna de nivel de atencion.
 */
/**
 * Grupos de marcadores del sistema: en el mapa, cada grupo es un desplegable propio con sus marcadores como opciones.
 * Un marcador pertenece a un grupo por su campo `grupo`; los que no lo tienen se agrupan por archivo.
 *
 * @return array clave => rótulo
 */
function siarhe_marker_grupos() {
    return array(
        'establecimientos' => 'Establecimientos de Salud',
        'clinicas'         => 'Clínicas',
    );
}

function siarhe_get_default_marcadores() {
    $clinica = array( 'tipo' => 'posicion', 'filtro_col' => '', 'filtro_val' => '', 'visibilidad' => 'publico', 'is_core' => true );
    $defaults = array(
        'CATETER' => array_merge( array( 'label' => 'Clínicas de catéteres', 'archivo' => 'clinicas-cateteres.csv', 'grupo' => 'clinicas' ), $clinica ),
        'HERIDAS' => array_merge( array( 'label' => 'Clínicas de heridas', 'archivo' => 'clinicas-heridas.csv', 'grupo' => 'clinicas' ), $clinica ),
        'PASANTES' => array_merge( array( 'label' => 'Pasantes de enfermería', 'archivo' => 'pasantes-enfermeria.csv' ), $clinica ),
    );
    $niveles = array( 'ESTAB_1' => 'Primer Nivel', 'ESTAB_2' => 'Segundo Nivel', 'ESTAB_3' => 'Tercer Nivel', 'ESTAB_6' => 'No Aplica', 'ESTAB_NE' => 'No Especificado' );
    foreach ( $niveles as $clave => $nivel ) {
        $defaults[ $clave ] = array(
            'label' => 'Establecimientos de Salud: ' . $nivel, 'tipo' => 'posicion', 'archivo' => 'establecimientos-salud.csv',
            'filtro_col' => 'nivel_atencion', 'filtro_val' => $nivel, 'visibilidad' => 'publico', 'is_core' => true, 'sistema' => true, 'grupo' => 'establecimientos',
        );
    }
    return $defaults;
}

/**
 * Configuracion de marcadores vigente. Los marcadores del sistema siempre existen y salen con su etiqueta, tipo, archivo y
 * filtro originales: del panel solo se respeta su visibilidad. Los demas se conservan como los dejo el administrador.
 */
function siarhe_get_marcadores_config() {
    $defaults = siarhe_get_default_marcadores();
    $saved    = siarhe_decode_json_option( get_option( 'siarhe_marcadores_config', '' ) );
    if ( empty( $saved ) || ! is_array( $saved ) ) return $defaults;

    $out = array();
    foreach ( $saved as $clave => $m ) {
        if ( ! is_array( $m ) ) continue;
        if ( isset( $defaults[ $clave ] ) && ! empty( $defaults[ $clave ]['sistema'] ) ) {
            $forzado = $defaults[ $clave ];
            if ( isset( $m['visibilidad'] ) ) $forzado['visibilidad'] = $m['visibilidad'];
            $m = $forzado;
        }
        // El grupo de los marcadores con que viene el plugin es del sistema: no depende de lo guardado
        if ( isset( $defaults[ $clave ]['grupo'] ) ) $m['grupo'] = $defaults[ $clave ]['grupo'];
        $out[ $clave ] = $m;
    }
    // Los marcadores con que viene el plugin siempre existen (un marcador sin archivo cargado no se ofrece en el mapa)
    foreach ( $defaults as $clave => $def ) {
        if ( ! isset( $out[ $clave ] ) ) $out[ $clave ] = $def;
    }
    return $out;
}

/**
 * Decodifica una opcion guardada como texto JSON. Primero se lee tal cual; solo si no es JSON valido se le quitan las barras que
 * agrega WordPress. (Quitarlas siempre convertia los acentos escapados, ó, en el texto "u00f3".) Tambien repara los
 * textos que ya se guardaron dañados con ese texto.
 */
function siarhe_decode_json_option( $raw ) {
    $raw = (string) $raw;
    if ( $raw === '' ) return null;
    $data = json_decode( $raw, true );
    if ( $data === null ) $data = json_decode( wp_unslash( $raw ), true );
    if ( ! is_array( $data ) ) return $data;
    array_walk_recursive( $data, function ( &$v ) {
        if ( is_string( $v ) && strpos( $v, 'u00' ) !== false ) {
            $v = preg_replace_callback( '/u00([a-f0-9]{2})/i', function ( $m ) {
                $c = hexdec( $m[1] );
                return ( $c >= 0xA0 ) ? html_entity_decode( '&#' . $c . ';', ENT_QUOTES, 'UTF-8' ) : $m[0];
            }, $v );
        }
    } );
    return $data;
}

/**
 * Temas con que se agrupan las metricas (el selector "Tema" del mapa). Los del sistema (General, Nivel de atencion, Estudios y
 * Especializacion) siempre existen y no se editan ni se borran; "general" es donde caen las metricas sin tema. Los que crea el
 * administrador se guardan en la opcion siarhe_temas_config como { clave: { label } } (Configuracion > Metricas).
 * @return array clave => array( 'label' => ..., 'sistema' => true|false )
 */
function siarhe_get_temas() {
    $temas = array(
        'general'         => array( 'label' => 'General',           'sistema' => true ),
        'nivel_atencion'  => array( 'label' => 'Nivel de atención', 'sistema' => true ),
        'estudios'        => array( 'label' => 'Estudios',          'sistema' => true ),
        'especializacion' => array( 'label' => 'Especialización',   'sistema' => true ),
    );
    $saved = siarhe_decode_json_option( get_option( 'siarhe_temas_config', '' ) );
    if ( is_array( $saved ) ) {
        foreach ( $saved as $k => $t ) {
            $k = sanitize_key( $k );
            if ( isset( $temas[ $k ] ) ) continue;
            $label = is_array( $t ) && isset( $t['label'] ) ? trim( (string) $t['label'] ) : '';
            if ( $label !== '' ) $temas[ $k ] = array( 'label' => $label, 'sistema' => false );
        }
    }
    return $temas;
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
