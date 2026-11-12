<?php // /includes/class-siarhe-static-json.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Formato JSON optimizado de las bases estaticas (formato "siarhe-estatico", version 1) y su conversion desde y hacia CSV.
 *
 * Es la misma conversion que hace RHEMX en Python (src/estatico_json.py) al generar sus archivos: el JSON lleva lo mismo
 * que el CSV, pero cada columna tasa_<x> que es exactamente enfermeras_<x> / poblacion x 1,000 se declara en "tasas" y no se
 * escribe en las filas; el navegador la calcula. Asi un CSV subido solo, un JSON subido solo y los dos juntos producen la
 * misma base para el mapa.
 *
 *   {
 *     "formato": "siarhe-estatico", "version": 1, "anio": 2026, "unidad": "plazas" | "personas",
 *     "columnas": ["id", "CVE_ENT", ..., "poblacion", "enfermeras_total", ...],   columnas que si se escriben
 *     "tasas": { "tasa_total": ["enfermeras_total", 1000], ... },                 columnas que se calculan: [conteo, por cada]
 *     "sin_poblacion": 0,                                                         valor de esas tasas con poblacion vacia o 0
 *     "filas": [ [...], ... ]                                                     una lista por fila, en el orden de "columnas"
 *   }
 *
 * Valores: las columnas de identificacion (id, CVE_ENT, CVE_MUN, CVE_LOC, MAPA, estado, entidad, municipio, localidad) y toda
 * columna con texto se conservan como texto (con sus ceros iniciales); el resto son numeros (entero si no lleva punto) o
 * null si la celda esta vacia o dice S/D.
 */
class Siarhe_Static_Json {

    const FORMATO   = 'siarhe-estatico';
    const VERSION   = 1;
    const TASA_POR  = 1000;
    const TOLERANCIA = 1e-6; // las tasas del CSV vienen redondeadas a 9 decimales
    const YEAR_MIN   = 2006; // primer año con poblacion (el censo de 2005 cubre 2006 a 2009)
    const LATEST_YEAR = 2026; // año que se asigna a las bases cargadas antes de existir el selector de año

    private static $columnas_texto = array( 'id', 'cve_ent', 'cve_mun', 'cve_loc', 'mapa', 'estado', 'entidad', 'municipio', 'localidad' );

    // -------------------------------------------------------------------------
    // 1. Lectura y conversion de valores
    // -------------------------------------------------------------------------

    /** Numero de una celda (entero si no lleva punto), o null si esta vacia, dice S/D o no es numerica. */
    public static function numero( $celda ) {
        $t = str_replace( ' ', '', trim( (string) $celda ) );
        if ( preg_match( '/^[+-]?[0-9]+$/D', $t ) ) return (int) $t;
        if ( preg_match( '/^[+-]?([0-9]+\.?[0-9]*|\.[0-9]+)([eE][+-]?[0-9]+)?$/D', $t ) ) return (float) $t;
        return null;
    }

    private static function es_vacia( $celda ) {
        $t = trim( (string) $celda );
        return $t === '' || strtoupper( $t ) === 'S/D';
    }

    private static function es_columna_texto( $nombre, $celdas ) {
        if ( in_array( strtolower( trim( $nombre ) ), self::$columnas_texto, true ) ) return true;
        foreach ( $celdas as $c ) {
            if ( ! self::es_vacia( $c ) && self::numero( $c ) === null ) return true;
        }
        return false;
    }

    /** CSV (texto UTF-8) a [ encabezado, filas ]. Devuelve null si no tiene encabezado. */
    private static function leer_csv( $texto ) {
        if ( substr( $texto, 0, 3 ) === "\xEF\xBB\xBF" ) $texto = substr( $texto, 3 );
        $fh = fopen( 'php://temp', 'r+' );
        fwrite( $fh, $texto );
        rewind( $fh );
        $filas = array();
        while ( ( $f = fgetcsv( $fh, 0, ',', '"', '' ) ) !== false ) {
            if ( $f === array( null ) ) continue; // linea vacia
            $filas[] = $f;
        }
        fclose( $fh );
        if ( ! $filas ) return null;
        $encabezado = array_map( 'trim', array_shift( $filas ) );
        return array( $encabezado, $filas );
    }

    // -------------------------------------------------------------------------
    // 2. Deteccion de las tasas que se pueden calcular
    // -------------------------------------------------------------------------

    /** @return array [ array tasa => conteo, mixed sin_poblacion ] */
    private static function tasas_calculables( $encabezado, $columnas ) {
        $pob = null;
        foreach ( $encabezado as $i => $n ) {
            if ( strpos( strtolower( trim( $n ) ), 'pob' ) === 0 ) { $pob = $i; break; }
        }
        if ( $pob === null ) return array( array(), null );
        $poblacion = array_map( array( __CLASS__, 'numero_o_null' ), $columnas[ $pob ] );

        $calculables = array();
        $sin_poblacion = array();
        foreach ( $encabezado as $n ) {
            if ( strpos( $n, 'tasa_' ) !== 0 ) continue;
            $conteo = 'enfermeras_' . substr( $n, 5 );
            $c = array_search( $conteo, $encabezado, true );
            if ( $c === false ) continue;
            $t_col = array_search( $n, $encabezado, true );
            $ok = true;
            $sin = array();
            foreach ( $poblacion as $r => $p ) {
                $ti = self::numero( $columnas[ $t_col ][ $r ] );
                $ci = self::numero( $columnas[ $c ][ $r ] );
                if ( $p !== null && $p > 0 ) {
                    if ( $ci === null || $ti === null || abs( $ti - $ci / $p * self::TASA_POR ) > self::TOLERANCIA ) { $ok = false; break; }
                } else {
                    $sin[ $ti === null ? 'null' : (string) ( $ti + 0 ) ] = $ti;
                }
            }
            if ( $ok ) {
                $calculables[ $n ] = $conteo;
                $sin_poblacion += $sin;
            }
        }
        // Una sola regla para las filas sin poblacion: todas 0 o todas vacias
        if ( count( $sin_poblacion ) > 1 ) return array( array(), null );
        $valor = $sin_poblacion ? reset( $sin_poblacion ) : 0;
        return array( $calculables, ( $valor === 0 || $valor === 0.0 ) ? 0 : $valor );
    }

    private static function numero_o_null( $c ) { return self::numero( $c ); }

    // -------------------------------------------------------------------------
    // 3. Conversion
    // -------------------------------------------------------------------------

    /**
     * CSV (texto) a la estructura del JSON optimizado.
     * @return array|null null si el texto no tiene encabezado
     */
    public static function from_csv( $texto, $anio, $unidad = 'plazas' ) {
        $leido = self::leer_csv( $texto );
        if ( ! $leido ) return null;
        list( $encabezado, $datos ) = $leido;
        $columnas = array();
        foreach ( $encabezado as $i => $n ) {
            $col = array();
            foreach ( $datos as $f ) $col[] = isset( $f[ $i ] ) ? $f[ $i ] : '';
            $columnas[ $i ] = $col;
        }
        list( $calculables, $sin_poblacion ) = self::tasas_calculables( $encabezado, $columnas );

        $escritas = array();
        foreach ( $encabezado as $i => $n ) if ( ! isset( $calculables[ $n ] ) ) $escritas[] = $i;
        $es_texto = array();
        foreach ( $escritas as $i ) $es_texto[ $i ] = self::es_columna_texto( $encabezado[ $i ], $columnas[ $i ] );

        $filas = array();
        foreach ( $datos as $r => $f ) {
            $fila = array();
            foreach ( $escritas as $i ) {
                $v = isset( $columnas[ $i ][ $r ] ) ? $columnas[ $i ][ $r ] : '';
                $fila[] = $es_texto[ $i ] ? trim( $v ) : self::numero( $v );
            }
            $filas[] = $fila;
        }

        $tasas = array();
        foreach ( $calculables as $t => $c ) $tasas[ $t ] = array( $c, self::TASA_POR );

        $json = array(
            'formato' => self::FORMATO, 'version' => self::VERSION, 'anio' => (int) $anio, 'unidad' => $unidad,
            'columnas' => array_values( array_map( function ( $i ) use ( $encabezado ) { return $encabezado[ $i ]; }, $escritas ) ),
            'tasas' => (object) $tasas,
        );
        if ( $tasas ) $json['sin_poblacion'] = $sin_poblacion;
        $json['filas'] = $filas;
        return $json;
    }

    /**
     * Valida un JSON ya decodificado. Devuelve la lista de problemas (vacia si es valido).
     */
    public static function validate( $json ) {
        $e = array();
        if ( ! is_array( $json ) || ! isset( $json['formato'] ) || $json['formato'] !== self::FORMATO ) {
            return array( 'El JSON no es una base estática del plugin (falta "formato": "' . self::FORMATO . '").' );
        }
        if ( ! isset( $json['version'] ) || (int) $json['version'] > self::VERSION ) $e[] = 'Versión del formato no compatible.';
        if ( empty( $json['columnas'] ) || ! is_array( $json['columnas'] ) ) $e[] = 'Falta la lista "columnas".';
        if ( ! isset( $json['filas'] ) || ! is_array( $json['filas'] ) || ! $json['filas'] ) $e[] = 'No hay filas de datos.';
        if ( $e ) return $e;
        $n = count( $json['columnas'] );
        $malas = 0;
        foreach ( $json['filas'] as $f ) if ( ! is_array( $f ) || count( $f ) !== $n ) $malas++;
        if ( $malas ) $e[] = $malas . ' fila(s) tienen distinta cantidad de valores que "columnas".';
        $tasas = isset( $json['tasas'] ) ? (array) $json['tasas'] : array();
        foreach ( $tasas as $t => $def ) {
            if ( ! is_array( $def ) || count( $def ) < 2 || ! in_array( $def[0], $json['columnas'], true ) || ! is_numeric( $def[1] ) ) {
                $e[] = 'La tasa "' . $t . '" no apunta a una columna de conteo válida.';
            }
        }
        $pob = false;
        foreach ( $json['columnas'] as $c ) if ( strpos( strtolower( trim( $c ) ), 'pob' ) === 0 ) { $pob = true; break; }
        if ( $tasas && ! $pob ) $e[] = 'Hay tasas calculables pero falta la columna de población.';
        return $e;
    }

    /**
     * JSON decodificado a texto CSV (UTF-8, comas, salto \n): restituye las tasas calculadas y redondea a 9 decimales como el CSV de origen.
     */
    public static function to_csv( $json ) {
        $columnas = $json['columnas'];
        $tasas = isset( $json['tasas'] ) ? (array) $json['tasas'] : array();
        $sin = isset( $json['sin_poblacion'] ) ? $json['sin_poblacion'] : 0;
        $pob_i = null;
        foreach ( $columnas as $i => $c ) if ( strpos( strtolower( trim( $c ) ), 'pob' ) === 0 ) { $pob_i = $i; break; }
        $idx = array_flip( $columnas );
        $encabezado = array_merge( $columnas, array_keys( $tasas ) );

        $fh = fopen( 'php://temp', 'r+' );
        fputcsv( $fh, $encabezado, ',', '"', '' );
        foreach ( $json['filas'] as $f ) {
            $fila = array();
            foreach ( $f as $v ) $fila[] = self::celda( $v );
            $p = ( $pob_i !== null && is_numeric( $f[ $pob_i ] ) ) ? $f[ $pob_i ] : null;
            foreach ( $tasas as $t => $def ) {
                $c = $f[ $idx[ $def[0] ] ];
                if ( $p !== null && $p > 0 && is_numeric( $c ) ) $fila[] = self::celda( round( $c / $p * $def[1], 9 ) );
                else $fila[] = self::celda( $sin );
            }
            fputcsv( $fh, $fila, ',', '"', '' );
        }
        rewind( $fh );
        $texto = stream_get_contents( $fh );
        fclose( $fh );
        return $texto;
    }

    private static function celda( $v ) {
        if ( $v === null ) return '';
        if ( is_float( $v ) ) return rtrim( rtrim( sprintf( '%.9F', $v ), '0' ), '.' );
        return (string) $v;
    }

    /** Escribe la estructura como JSON compacto (UTF-8). */
    public static function encode( $json ) {
        return wp_json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }
}
