<?php // /includes/class-siarhe-marker-json.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Formato JSON de los marcadores (formato "siarhe-marcadores", version 1) y su conversion desde y hacia CSV.
 *
 * Es el formato compacto con que RHEMX publica sus capas de puntos (unidades_clues_<año>.json, clinicas_cateteres_<año>.json,
 * clinicas_heridas_historico.json...): los textos que se repiten en miles de filas (institucion, nivel, tipologia, municipio...)
 * se guardan una sola vez en listas y cada fila lleva su posicion en la lista. Al subir un CSV el plugin crea el JSON y al subir
 * un JSON crea el CSV, asi que siempre existen los dos y son equivalentes; el mapa lee el JSON, que pesa menos.
 *
 *   {
 *     "formato": "siarhe-marcadores", "version": 1, "periodo": "2026" | "total",
 *     "fila": ["latitud", "longitud", "clues", "nombre", "institucion", "nivel", ...],   campo de cada posicion de una fila
 *     "columnas": { "institucion": "INSTITUCION", ... },   encabezado del CSV de origen cuando no es el nombre estandar
 *     "instituciones": [...], "niveles": [...], "tipologias": [...], "estratos": [...], "tipos_establecimiento": [...],
 *     "jurisdicciones": [...], "municipios": [...], "localidades": [...],            listas de los campos indexados
 *     "unidades": [ [21.88, -102.30, "ASCIJ000012", "Cij Aguascalientes", 0, 1, ...], ... ]
 *   }
 *
 * Campos base: latitud y longitud (numeros), entidad (clave de la entidad como numero) y los campos indexados (posicion en su
 * lista, -1 = sin dato). Cualquier otro campo se conserva tal cual (numero o texto). Una fila puede omitir sus ultimos campos
 * cuando estan vacios (RHEMX solo escribe la CLUES anterior en las unidades que la tienen): los que faltan se leen como vacios. Los archivos de RHEMX no llevan "formato" ni
 * "columnas": se reconocen por "fila" y "unidades", y sus campos toman el encabezado estandar al pasar a CSV.
 *
 *   1. Catalogo de campos
 *   2. Validacion
 *   3. JSON a CSV
 *   4. CSV a JSON
 *   5. Versiones por entidad
 */
class Siarhe_Marker_Json {

    const FORMATO = 'siarhe-marcadores';
    const VERSION = 1;
    const PERIODO_TOTAL = 'total'; // capa con todas las unidades de la historia (clinicas que reportaron al menos una vez)
    const DIR_ENTIDADES = 'entidades'; // subcarpeta de markers/ con las versiones por entidad de cada capa

    // -------------------------------------------------------------------------
    // 1. Catalogo de campos
    // -------------------------------------------------------------------------

    /** Campos indexados: campo de la fila => nombre de su lista en el JSON. */
    public static function listas() {
        return array(
            'institucion' => 'instituciones', 'nivel' => 'niveles', 'tipologia' => 'tipologias', 'estrato' => 'estratos',
            'tipo_estab' => 'tipos_establecimiento', 'jurisdiccion' => 'jurisdicciones', 'municipio' => 'municipios', 'localidad' => 'localidades',
        );
    }

    /**
     * Campos conocidos: encabezado estandar del CSV (los nombres del catalogo CLUES) y los encabezados alternos con que
     * se reconocen en un CSV, ya normalizados (minusculas, sin acentos, guion bajo como espacio).
     */
    public static function campos() {
        return array(
            'latitud'      => array( 'LATITUD',                     array( 'latitud', 'lat' ) ),
            'longitud'     => array( 'LONGITUD',                    array( 'longitud', 'lon' ) ),
            'clues'        => array( 'CLUES',                       array( 'clues' ) ),
            'nombre'       => array( 'NOMBRE DE LA UNIDAD',         array( 'nombre de la unidad', 'nombre unidad', 'unidad' ) ),
            'institucion'  => array( 'NOMBRE DE LA INSTITUCION',    array( 'nombre de la institucion', 'institucion' ) ),
            'nivel'        => array( 'NIVEL ATENCION',              array( 'nivel atencion' ) ),
            'tipologia'    => array( 'NOMBRE DE TIPOLOGIA',         array( 'nombre de tipologia', 'nombre tipologia' ) ),
            'entidad'      => array( 'CLAVE DE LA ENTIDAD',         array( 'clave de la entidad', 'cve ent', 'clave entidad' ) ),
            'estrato'      => array( 'ESTRATO UNIDAD',              array( 'estrato unidad', 'estrato' ) ),
            'tipo_estab'   => array( 'NOMBRE TIPO ESTABLECIMIENTO', array( 'nombre tipo establecimiento', 'tipo establecimiento' ) ),
            'jurisdiccion' => array( 'JURISDICCION',                array( 'jurisdiccion' ) ),
            'municipio'    => array( 'MUNICIPIO',                   array( 'municipio' ) ),
            'localidad'    => array( 'LOCALIDAD',                   array( 'localidad' ) ),
            // Campos adicionales de las capas de RHEMX: solo se usan para darles encabezado al pasar a CSV
            'plazas'       => array( 'PLAZAS DE ENFERMERIA',        array() ),
            'personas'     => array( 'RECURSOS HUMANOS ESTIMADOS',  array() ),
            'anterior'     => array( 'CLUES ANTERIOR',              array() ),
            'reportes'     => array( 'REPORTES',                    array() ),
            'anios'        => array( 'ANIOS REPORTADOS',            array() ),
            'primer'       => array( 'PRIMER ANIO',                 array() ),
            'ultimo'       => array( 'ULTIMO ANIO',                 array() ),
        );
    }

    /** Nombre de columna normalizado para compararlo: minusculas, sin acentos y con el guion bajo como espacio. */
    public static function normaliza( $nombre ) {
        $t = strtolower( trim( (string) $nombre ) );
        $t = strtr( $t, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                               'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n' ) );
        return trim( preg_replace( '/[_\s]+/', ' ', $t ) );
    }

    /** Encabezado del CSV de un campo: el del archivo de origen (columnas), el estandar o el nombre del campo. */
    public static function encabezado( $campo, $columnas ) {
        if ( is_array( $columnas ) && isset( $columnas[ $campo ] ) && $columnas[ $campo ] !== '' ) return (string) $columnas[ $campo ];
        $campos = self::campos();
        return isset( $campos[ $campo ] ) ? $campos[ $campo ][0] : (string) $campo;
    }

    /** Periodo valido: un año (de 2000 al año en curso) o "total". Devuelve el texto normalizado o ''. */
    public static function periodo( $valor ) {
        $v = strtolower( trim( (string) $valor ) );
        if ( $v === self::PERIODO_TOTAL || $v === 'historico' ) return self::PERIODO_TOTAL;
        $anio = (int) $v;
        return ( $anio >= 2000 && $anio <= max( 2026, (int) gmdate( 'Y' ) ) ) ? (string) $anio : '';
    }

    // -------------------------------------------------------------------------
    // 2. Validacion
    // -------------------------------------------------------------------------

    /** Indica si el JSON decodificado tiene la forma de una capa de marcadores. */
    public static function es_capa( $json ) {
        return is_array( $json ) && isset( $json['fila'], $json['unidades'] ) && is_array( $json['fila'] ) && is_array( $json['unidades'] );
    }

    /**
     * Revisa una capa de marcadores. Devuelve array( 'error' => texto ('' si se puede cargar), 'avisos' => textos, 'filas' => n ).
     */
    public static function validar( $json ) {
        $res = array( 'error' => '', 'avisos' => array(), 'filas' => 0 );
        if ( ! self::es_capa( $json ) ) {
            $res['error'] = 'El JSON no es una capa de marcadores: necesita "fila" (los campos) y "unidades" (las filas), como los archivos que exporta RHEMX.';
            return $res;
        }
        $fila = array_map( 'strval', $json['fila'] );
        foreach ( array( 'latitud', 'longitud' ) as $necesario ) {
            if ( ! in_array( $necesario, $fila, true ) ) {
                $res['error'] = 'La capa no tiene el campo "' . $necesario . '" en "fila".';
                return $res;
            }
        }
        if ( ! $json['unidades'] ) {
            $res['error'] = 'La capa no tiene filas en "unidades".';
            return $res;
        }
        foreach ( self::listas() as $campo => $lista ) {
            if ( in_array( $campo, $fila, true ) && ( ! isset( $json[ $lista ] ) || ! is_array( $json[ $lista ] ) ) ) {
                $res['error'] = 'La capa usa el campo "' . $campo . '" pero no trae su lista "' . $lista . '".';
                return $res;
            }
        }

        $i_lat = array_search( 'latitud', $fila, true );
        $i_lon = array_search( 'longitud', $fila, true );
        $i_ent = array_search( 'entidad', $fila, true );
        $minimo = max( $i_lat, $i_lon ) + 1; // una fila sin sus coordenadas no se puede dibujar
        $cortas = 0; $fuera = 0; $sin_ent = 0;
        foreach ( $json['unidades'] as $u ) {
            if ( ! is_array( $u ) || count( $u ) < $minimo ) { $cortas++; continue; }
            $lat = (float) $u[ $i_lat ]; $lon = (float) $u[ $i_lon ];
            if ( $lat < 14 || $lat > 33 || $lon < -119 || $lon > -86 ) $fuera++;
            if ( $i_ent !== false && ( ! isset( $u[ $i_ent ] ) || (int) $u[ $i_ent ] <= 0 ) ) $sin_ent++;
        }
        $total = count( $json['unidades'] );
        $res['filas'] = $total;
        if ( $cortas === $total ) {
            $res['error'] = 'Las filas de "unidades" no traen sus coordenadas en las posiciones que declara "fila".';
            return $res;
        }
        if ( $cortas > 0 ) $res['avisos'][] = $cortas . ' fila(s) no traen coordenadas y no se dibujarán.';
        if ( $i_ent === false ) {
            $res['avisos'][] = 'La capa no tiene el campo "entidad": sus puntos aparecerán en el mapa de todas las entidades.';
        } elseif ( $sin_ent > 0 ) {
            $res['avisos'][] = $sin_ent . ' fila(s) no tienen clave de entidad y solo se verán en el mapa nacional.';
        }
        if ( $total > 0 && ( $fuera / $total ) > 0.2 ) {
            $res['avisos'][] = round( ( $fuera / $total ) * 100 ) . '% de las filas tienen coordenadas vacías o fuera de México y no se dibujarán.';
        }
        return $res;
    }

    // -------------------------------------------------------------------------
    // 3. JSON a CSV
    // -------------------------------------------------------------------------

    /** Texto de un valor de la capa para el CSV. */
    private static function celda( $v ) {
        if ( $v === null ) return '';
        if ( is_bool( $v ) ) return $v ? '1' : '0';
        if ( is_float( $v ) ) return rtrim( rtrim( sprintf( '%.10F', $v ), '0' ), '.' );
        return (string) $v;
    }

    /**
     * Escribe el CSV equivalente a una capa: una columna por campo de "fila", con los campos indexados ya como texto y la
     * entidad con dos digitos. Devuelve true o false si no se pudo escribir.
     */
    public static function a_csv_archivo( $json, $ruta_csv ) {
        $fila     = array_map( 'strval', $json['fila'] );
        $columnas = isset( $json['columnas'] ) && is_array( $json['columnas'] ) ? $json['columnas'] : array();
        $listas   = self::listas();
        $n        = count( $fila );

        $fh = @fopen( $ruta_csv, 'w' );
        if ( ! $fh ) return false;
        $encabezados = array();
        foreach ( $fila as $campo ) $encabezados[] = self::encabezado( $campo, $columnas );
        fputcsv( $fh, $encabezados, ',', '"', '\\' );

        foreach ( $json['unidades'] as $u ) {
            if ( ! is_array( $u ) ) continue;
            $linea = array();
            foreach ( $fila as $i => $campo ) {
                $v = isset( $u[ $i ] ) ? $u[ $i ] : null; // los ultimos campos pueden faltar: vacios
                if ( isset( $listas[ $campo ] ) ) {
                    $lista = $json[ $listas[ $campo ] ];
                    $v = ( is_int( $v ) && $v >= 0 && isset( $lista[ $v ] ) ) ? $lista[ $v ] : '';
                } elseif ( $campo === 'entidad' ) {
                    $v = ( (int) $v > 0 ) ? str_pad( (string) (int) $v, 2, '0', STR_PAD_LEFT ) : '';
                }
                $linea[] = self::celda( $v );
            }
            fputcsv( $fh, $linea, ',', '"', '\\' );
        }
        fclose( $fh );
        return true;
    }

    // -------------------------------------------------------------------------
    // 4. CSV a JSON
    // -------------------------------------------------------------------------

    /** Valor de una celda libre: numero solo si su texto es exactamente el del numero (asi "001" o "1.0" siguen siendo texto). */
    private static function valor_libre( $celda ) {
        $t = trim( (string) $celda );
        if ( $t === '' ) return '';
        if ( preg_match( '/^-?(0|[1-9][0-9]{0,14})$/D', $t ) ) return (int) $t;
        if ( preg_match( '/^-?(0|[1-9][0-9]*)\.[0-9]+$/D', $t ) && (string) (float) $t === $t ) return (float) $t;
        return $t;
    }

    /**
     * Convierte un CSV de marcadores (UTF-8, separado por comas) en su capa JSON y la escribe en $ruta_json. El CSV se lee
     * fila por fila y cada fila convertida se escribe de inmediato, para que un catalogo de decenas de miles de unidades no
     * ocupe la memoria del servidor. Devuelve el numero de filas escritas o un texto con el error.
     */
    public static function desde_csv_archivo( $ruta_csv, $ruta_json, $periodo ) {
        $in = @fopen( $ruta_csv, 'r' );
        if ( ! $in ) return 'No se pudo leer el CSV.';
        $primera = fgets( $in );
        if ( $primera === false ) { fclose( $in ); return 'El CSV está vacío.'; }
        $encabezados = array_map( 'trim', str_getcsv( preg_replace( '/^\xEF\xBB\xBF/', '', $primera ), ',', '"', '\\' ) );

        // Cada columna del CSV es un campo conocido (por su encabezado estandar o uno alterno) o un campo libre con su nombre
        $por_alias = array();
        foreach ( self::campos() as $campo => $def ) {
            $por_alias[ self::normaliza( $def[0] ) ] = $campo;
            foreach ( $def[1] as $alias ) $por_alias[ $alias ] = $campo;
        }
        $fila = array(); $columnas = array(); $usados = array();
        foreach ( $encabezados as $h ) {
            $norm  = self::normaliza( $h );
            $campo = ( isset( $por_alias[ $norm ] ) && ! isset( $usados[ $por_alias[ $norm ] ] ) && self::campos()[ $por_alias[ $norm ] ][1] ) ? $por_alias[ $norm ] : null;
            if ( $campo === null ) {
                $fila[] = $h; // campo libre: conserva su encabezado
            } else {
                $usados[ $campo ] = true;
                $fila[] = $campo;
                if ( $h !== self::campos()[ $campo ][0] ) $columnas[ $campo ] = $h;
            }
        }
        if ( ! isset( $usados['latitud'], $usados['longitud'] ) ) { fclose( $in ); return 'El CSV necesita las columnas de coordenadas: Latitud/Longitud (o lat/lon).'; }

        $out = @fopen( $ruta_json, 'w' );
        if ( ! $out ) { fclose( $in ); return 'No se pudo escribir el JSON.'; }
        $opciones = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        fwrite( $out, '{"formato":' . wp_json_encode( self::FORMATO ) . ',"version":' . self::VERSION . ',"periodo":' . wp_json_encode( (string) $periodo )
            . ',"fila":' . wp_json_encode( $fila, $opciones )
            . ( $columnas ? ',"columnas":' . wp_json_encode( $columnas, $opciones ) : '' ) . ',"unidades":[' );

        $listas = self::listas();
        $indices = array();
        foreach ( $listas as $campo => $lista ) $indices[ $campo ] = array();
        $n = count( $fila ); $escritas = 0;
        while ( ( $r = fgetcsv( $in, 0, ',', '"', '\\' ) ) !== false ) {
            if ( $r === array( null ) ) continue;
            $linea = array();
            for ( $i = 0; $i < $n; $i++ ) {
                $campo = $fila[ $i ];
                $celda = isset( $r[ $i ] ) ? trim( (string) $r[ $i ] ) : '';
                if ( $campo === 'latitud' || $campo === 'longitud' ) {
                    $linea[] = is_numeric( $celda ) ? (float) $celda : null;
                } elseif ( $campo === 'entidad' && isset( $usados['entidad'] ) ) {
                    $linea[] = ctype_digit( $celda ) ? (int) $celda : 0;
                } elseif ( isset( $listas[ $campo ] ) && isset( $usados[ $campo ] ) ) {
                    if ( $celda === '' ) { $linea[] = -1; continue; }
                    if ( ! isset( $indices[ $campo ][ $celda ] ) ) $indices[ $campo ][ $celda ] = count( $indices[ $campo ] );
                    $linea[] = $indices[ $campo ][ $celda ];
                } elseif ( isset( $usados[ $campo ] ) ) {
                    $linea[] = $celda; // clues y nombre: siempre texto
                } else {
                    $linea[] = self::valor_libre( $celda );
                }
            }
            fwrite( $out, ( $escritas ? ',' : '' ) . wp_json_encode( $linea, $opciones ) );
            $escritas++;
        }
        fclose( $in );

        fwrite( $out, ']' );
        foreach ( $listas as $campo => $lista ) {
            if ( isset( $usados[ $campo ] ) ) fwrite( $out, ',' . wp_json_encode( $lista ) . ':' . wp_json_encode( array_map( 'strval', array_keys( $indices[ $campo ] ) ), $opciones ) );
        }
        fwrite( $out, '}' );
        fclose( $out );

        if ( $escritas === 0 ) { @unlink( $ruta_json ); return 'El CSV solo tiene encabezado: no hay filas de datos.'; }
        return $escritas;
    }

    // -------------------------------------------------------------------------
    // 5. Versiones por entidad
    // El mapa de una entidad solo dibuja sus puntos: en lugar de descargar la capa completa (el catálogo nacional pesa varios
    // MB) lee una capa con el mismo formato que solo trae los de su entidad. Se guardan en markers/entidades/ como
    // {nombre de la capa}-ent{NN}.json y se crean las 32 cada vez, aunque una entidad no tenga puntos.
    // -------------------------------------------------------------------------

    /** Ruta de la versión de una entidad (clave de dos dígitos, 01 a 32) de una capa JSON; '' si la clave no es de una entidad. */
    public static function ruta_entidad( $ruta_json, $cve_ent ) {
        $n = (int) $cve_ent;
        if ( $n < 1 || $n > 32 ) return '';
        return dirname( $ruta_json ) . '/' . self::DIR_ENTIDADES . '/' . basename( $ruta_json, '.json' ) . '-ent' . str_pad( (string) $n, 2, '0', STR_PAD_LEFT ) . '.json';
    }

    /**
     * Divide una capa en una por entidad. Cada versión conserva "fila", "columnas" y los datos generales de la capa, y sus
     * listas de textos solo llevan los que usan sus puntos. Los puntos sin clave de entidad solo quedan en la capa completa.
     * Devuelve el número de archivos escritos (0 si la capa no se pudo leer o no tiene el campo "entidad").
     */
    public static function divide_por_entidad( $ruta_json ) {
        $json = is_readable( $ruta_json ) ? json_decode( (string) file_get_contents( $ruta_json ), true ) : null;
        if ( ! self::es_capa( $json ) ) return 0;
        $fila  = array_map( 'strval', $json['fila'] );
        $i_ent = array_search( 'entidad', $fila, true );
        if ( $i_ent === false ) return 0;

        // Posición de cada campo indexado dentro de la fila
        $indexados = array();
        foreach ( self::listas() as $campo => $lista ) {
            $i = array_search( $campo, $fila, true );
            if ( $i !== false && isset( $json[ $lista ] ) && is_array( $json[ $lista ] ) ) $indexados[ $i ] = $lista;
        }

        $por_entidad = array();
        foreach ( $json['unidades'] as $u ) {
            if ( ! is_array( $u ) || ! isset( $u[ $i_ent ] ) ) continue;
            $e = (int) $u[ $i_ent ];
            if ( $e >= 1 && $e <= 32 ) $por_entidad[ $e ][] = $u;
        }

        $general = $json;
        unset( $general['unidades'] );
        foreach ( $indexados as $lista ) unset( $general[ $lista ] );

        $carpeta = dirname( $ruta_json ) . '/' . self::DIR_ENTIDADES . '/';
        if ( ! is_dir( $carpeta ) && ! @mkdir( $carpeta, 0755, true ) ) return 0;
        $escritos = 0;
        for ( $e = 1; $e <= 32; $e++ ) {
            $unidades = isset( $por_entidad[ $e ] ) ? $por_entidad[ $e ] : array();
            $capa = $general;
            $capa['entidad'] = str_pad( (string) $e, 2, '0', STR_PAD_LEFT );
            // Cada lista se rehace con los textos que aparecen en esta entidad, en el orden en que aparecen
            $nuevas = array(); $mapas = array();
            foreach ( $indexados as $i => $lista ) { $nuevas[ $lista ] = array(); $mapas[ $i ] = array(); }
            foreach ( $unidades as $k => $u ) {
                foreach ( $indexados as $i => $lista ) {
                    if ( ! isset( $u[ $i ] ) || ! is_int( $u[ $i ] ) || $u[ $i ] < 0 || ! isset( $json[ $lista ][ $u[ $i ] ] ) ) continue;
                    $original = $u[ $i ];
                    if ( ! isset( $mapas[ $i ][ $original ] ) ) {
                        $mapas[ $i ][ $original ] = count( $nuevas[ $lista ] );
                        $nuevas[ $lista ][] = $json[ $lista ][ $original ];
                    }
                    $unidades[ $k ][ $i ] = $mapas[ $i ][ $original ];
                }
            }
            foreach ( $nuevas as $lista => $textos ) $capa[ $lista ] = $textos;
            $capa['unidades'] = $unidades;
            $destino = self::ruta_entidad( $ruta_json, $e );
            if ( @file_put_contents( $destino, wp_json_encode( $capa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) !== false ) $escritos++;
        }
        return $escritos;
    }

    /** Borra las versiones por entidad de una capa (al eliminar o reemplazar el archivo completo). */
    public static function borra_entidades( $ruta_json ) {
        for ( $e = 1; $e <= 32; $e++ ) {
            $ruta = self::ruta_entidad( $ruta_json, $e );
            if ( is_file( $ruta ) ) @unlink( $ruta );
        }
    }
}
