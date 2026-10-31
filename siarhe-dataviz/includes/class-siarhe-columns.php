<?php // /includes/class-siarhe-columns.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Catalogo de columnas de los CSV que se suben al plugin.
 * Fuente unica para las plantillas descargables y el diccionario de variables.
 *
 * Cada columna es un arreglo: name, nivel, origen, tipo, desc, ejemplo.
 *  - nivel: Obligatoria (debe incluirse en el archivo), Recomendada (el mapa la usa; si falta solo avisa) u Opcional.
 *  - origen: de donde sale la columna (Fija, Metrica nativa, Marcador).
 */
class Siarhe_Columns {

    const OBLIGATORIA = 'Obligatoria';
    const RECOMENDADA = 'Recomendada';
    const OPCIONAL    = 'Opcional';

    private static function col( $name, $nivel, $origen, $tipo, $desc, $ejemplo = '' ) {
        return array( 'name' => $name, 'nivel' => $nivel, 'origen' => $origen, 'tipo' => $tipo, 'desc' => $desc, 'ejemplo' => (string) $ejemplo );
    }

    /** Metricas nativas: las unicas que forman parte de la plantilla (las propias del usuario no se incluyen). */
    public static function metricas() {
        return siarhe_get_default_metricas();
    }

    /**
     * Columnas de una base estatica.
     * $variante: 'nacional' (una fila por entidad federativa) o 'municipal' (una fila por municipio de una entidad).
     */
    public static function static_columns( $variante = 'nacional' ) {
        $cols = array();
        if ( $variante === 'municipal' ) {
            $cols[] = self::col( 'CVE_ENT', self::RECOMENDADA, 'Fija', 'Texto', 'Clave de la entidad con 2 dígitos. Escríbela como texto para conservar el cero inicial.', '20' );
            $cols[] = self::col( 'CVE_MUN', self::OBLIGATORIA, 'Fija', 'Texto', 'Clave del municipio con 3 dígitos. Une cada fila con su polígono del mapa.', '001' );
            $cols[] = self::col( 'municipio', self::OBLIGATORIA, 'Fija', 'Texto', 'Nombre del municipio. También se acepta el encabezado "estado" o "Entidad".', 'Abejones' );
        } else {
            $cols[] = self::col( 'CVE_ENT', self::OBLIGATORIA, 'Fija', 'Texto', 'Clave de la entidad con 2 dígitos (01 a 32). Escríbela como texto para conservar el cero inicial. 33 se reserva para el total nacional.', '01' );
            $cols[] = self::col( 'estado', self::OBLIGATORIA, 'Fija', 'Texto', 'Nombre de la entidad federativa. También se acepta el encabezado "Entidad".', 'Aguascalientes' );
        }
        $cols[] = self::col( 'población', self::OBLIGATORIA, 'Fija', 'Entero', 'Población total. Cualquier encabezado que empiece con "pob" se reconoce.', '1425607' );

        // tasa_total y enfermeras_total son indispensables: sin ellas el mapa no muestra nada
        $indispensables = array( 'tasa_total', 'enfermeras_total' );
        foreach ( self::metricas() as $key => $m ) {
            if ( $key === 'poblacion' || ! is_array( $m ) ) continue;
            $tasa = isset( $m['tipo'] ) && $m['tipo'] === 'tasa';
            $desc = ( isset( $m['fullLabel'] ) && $m['fullLabel'] !== '' ? $m['fullLabel'] : $key ) . ( $tasa ? '. Tasa por cada mil habitantes (decimal).' : '. Valor absoluto (entero).' );
            $oblig = in_array( $key, $indispensables, true );
            $desc .= $oblig ? ' Sin esta columna el mapa no muestra datos.' : ' Vacío o S/D se trata como sin dato.';
            $cols[] = self::col( $key, $oblig ? self::OBLIGATORIA : self::RECOMENDADA, 'Métrica nativa', $tasa ? 'Decimal' : 'Entero', $desc, $tasa ? '3.657' : '5214' );
        }
        return $cols;
    }

    /** Columnas de un CSV de marcadores: solo lo que necesita el mapa. */
    public static function marker_columns() {
        return array(
            self::col( 'LATITUD', self::OBLIGATORIA, 'Fija', 'Decimal', 'Latitud en grados decimales (WGS84). Las filas sin coordenadas, con 0 o fuera de México no se dibujan. También se acepta "lat".', '21.85606' ),
            self::col( 'LONGITUD', self::OBLIGATORIA, 'Fija', 'Decimal', 'Longitud en grados decimales (WGS84), negativa para México. También se acepta "lon".', '-102.25563' ),
            self::col( 'CVE_ENT', self::OBLIGATORIA, 'Fija', 'Texto', 'Clave de la entidad con 2 dígitos. En el mapa de una entidad solo se dibujan los puntos de esa entidad.', '01' ),
            self::col( 'ENTIDAD', self::OBLIGATORIA, 'Fija', 'Texto', 'Nombre de la entidad federativa.', 'Aguascalientes' ),
            self::col( 'CVE_MUN', self::OBLIGATORIA, 'Fija', 'Texto', 'Clave del municipio con 3 dígitos.', '001' ),
            self::col( 'MUNICIPIO', self::OBLIGATORIA, 'Fija', 'Texto', 'Nombre del municipio.', 'Aguascalientes' ),
            self::col( 'CVE_LOC', self::OBLIGATORIA, 'Fija', 'Texto', 'Clave de la localidad con 4 dígitos.', '0001' ),
            self::col( 'LOCALIDAD', self::OBLIGATORIA, 'Fija', 'Texto', 'Nombre de la localidad.', 'Aguascalientes' ),
            self::col( 'VALOR_MARCADOR_1', self::OPCIONAL, 'Marcador', 'Según el dato', 'Dato propio del marcador (por ejemplo un conteo para la densidad o un valor para el filtro y el tooltip). Cámbiale el nombre al que uses en Configuración › Marcadores y agrega las columnas que necesites.', '10' ),
            self::col( 'VALOR_MARCADOR_2', self::OPCIONAL, 'Marcador', 'Según el dato', 'Otro dato propio del marcador. Puedes agregar más columnas de valor con el nombre que configures.', 'Ejemplo' ),
        );
    }

    /** Notas generales de formato que acompañan a las plantillas y al diccionario. */
    public static function notas() {
        return array(
            array( 'Formato general de los CSV' ),
            array( 'Codificación: UTF-8 (con o sin BOM).' ),
            array( 'Separador: coma (,). Si tu Excel guarda con punto y coma, usa "CSV UTF-8 (delimitado por comas)".' ),
            array( 'Decimales: punto (3.657), sin separador de miles.' ),
            array( 'Claves (CVE_ENT, CVE_MUN, CVE_LOC): texto con ceros iniciales (01, 001, 0001).' ),
            array( 'Datos faltantes: deja la celda vacía o escribe S/D.' ),
            array( 'La primera fila es el encabezado. Los nombres de columna deben escribirse exactamente como aparecen aquí.' ),
            array( 'Nivel: Obligatoria = debe incluirse; Recomendada = el mapa la usa y avisa si falta; Opcional = depende de tu configuración.' ),
            array( 'Las métricas propias que agregues en Configuración > Métricas son columnas adicionales: ponles como encabezado la misma clave que configuraste.' ),
        );
    }

    /** Fila de ejemplo con el valor de cada columna (para la plantilla CSV). */
    public static function example_row( $cols ) {
        return array_map( function ( $c ) { return $c['ejemplo']; }, $cols );
    }

    /** Filas del diccionario (con encabezado) para una lista de columnas. */
    public static function dictionary_rows( $cols ) {
        $rows = array( array( 'Columna', 'Nivel', 'Origen', 'Tipo', 'Descripción', 'Ejemplo' ) );
        foreach ( $cols as $c ) $rows[] = array( $c['name'], $c['nivel'], $c['origen'], $c['tipo'], $c['desc'], $c['ejemplo'] );
        return $rows;
    }

    /** Envia un CSV UTF-8 con BOM (para que Excel respete los acentos). */
    public static function send_csv( $filename, $rows ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );
        foreach ( $rows as $r ) fputcsv( $out, $r );
        fclose( $out );
        exit;
    }
}
