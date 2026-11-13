<?php // /includes/class-siarhe-validador.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Comprobación de que cada archivo cargado está en el lugar que le corresponde.
 *
 * Se usa en dos momentos: al subir una base estática (para rechazar, por ejemplo, la base de Coahuila en el lugar de
 * Campeche) y a petición del administrador, con el botón «Validar archivos cargados» del Dashboard y de cada pestaña de
 * Carga de Datos, que revisa lo que ya está en el servidor y dice qué archivo no corresponde y en qué apartado está.
 *
 *   1. Entidad de una base estática
 *   2. Revisión de los archivos cargados
 */
class Siarhe_Validador {

    /** Apartados de Carga de Datos que se pueden revisar: clave => nombre de la pestaña. */
    public static function secciones() {
        return array(
            'geojson'     => 'Geolocalización',
            'localidades' => 'Localidades (Geo)',
            'static'      => 'Bases Estáticas',
            'marcadores'  => 'Marcadores',
        );
    }

    // -------------------------------------------------------------------------
    // 1. Entidad de una base estática
    // Una base nacional trae una fila por entidad (columna "estado") y una estatal una fila por municipio (CVE_MUN, MAPA o
    // "municipio") con la clave de su entidad en CVE_ENT. Las filas de totales (8888, 9999) no cuentan.
    // -------------------------------------------------------------------------

    /** Clave de entidad de dos dígitos (01 a 34) de una celda, o '' si no lo es. */
    private static function clave_entidad( $celda ) {
        $t = trim( (string) $celda );
        if ( $t === '' || ! ctype_digit( $t ) ) return '';
        $n = (int) $t;
        return ( $n >= 1 && $n <= 34 ) ? str_pad( (string) $n, 2, '0', STR_PAD_LEFT ) : '';
    }

    /**
     * Compara el contenido de una base con la entidad elegida.
     * $columnas: nombres de columna; $filas: lista de filas (cada una, sus valores en el orden de $columnas); $slug: entidad.
     * Devuelve el texto del problema o '' si corresponde (o si la base no trae con qué comprobarlo).
     */
    public static function revisa_base( $columnas, $filas, $slug ) {
        $entidades = siarhe_get_entities();
        if ( $slug === 'extranjero' || ! isset( $entidades[ $slug ] ) ) return '';

        $nombres = array_map( function ( $c ) { return strtolower( trim( (string) $c ) ); }, (array) $columnas );
        $i_ent   = array_search( 'cve_ent', $nombres, true );
        $i_mapa  = array_search( 'mapa', $nombres, true );
        $por_municipio = ( in_array( 'cve_mun', $nombres, true ) || in_array( 'municipio', $nombres, true ) || $i_mapa !== false );

        $claves = array();
        foreach ( $filas as $f ) {
            if ( ! is_array( $f ) ) continue;
            $clave = '';
            if ( $i_ent !== false && isset( $f[ $i_ent ] ) ) $clave = self::clave_entidad( $f[ $i_ent ] );
            // Sin CVE_ENT, la clave del municipio (5 dígitos) empieza con la de su entidad
            if ( $clave === '' && $i_ent === false && $i_mapa !== false && isset( $f[ $i_mapa ] ) ) {
                $mapa = trim( (string) $f[ $i_mapa ] );
                if ( strlen( $mapa ) === 5 && ctype_digit( $mapa ) ) $clave = self::clave_entidad( substr( $mapa, 0, 2 ) );
            }
            if ( $clave !== '' ) $claves[ $clave ] = true;
        }
        if ( ! $claves ) return '';
        ksort( $claves );
        // PHP guarda como número los índices «10» a «34»: se devuelven a su texto de dos dígitos
        $claves = array_map( function ( $c ) { return str_pad( (string) $c, 2, '0', STR_PAD_LEFT ); }, array_keys( $claves ) );

        $catalogo = siarhe_get_entity_catalog();
        $nombre   = function ( $cve ) use ( $catalogo ) { return $cve . ( isset( $catalogo[ $cve ] ) ? ' (' . $catalogo[ $cve ] . ')' : '' ); };
        $elegida  = $entidades[ $slug ]['nombre'] . ' (' . $entidades[ $slug ]['CVE_ENT'] . ')';
        $es_nacional = ( ! $por_municipio || count( $claves ) > 1 );

        if ( $slug === 'republica-mexicana' ) {
            return $es_nacional ? '' : 'El archivo es de la entidad ' . $nombre( $claves[0] ) . ', por municipios, y elegiste ' . $elegida . '.';
        }
        if ( $es_nacional ) {
            return 'El archivo es nacional (trae ' . count( $claves ) . ' entidades) y elegiste ' . $elegida . '.';
        }
        if ( $claves[0] !== $entidades[ $slug ]['CVE_ENT'] ) {
            return 'El archivo es de la entidad ' . $nombre( $claves[0] ) . ' y elegiste ' . $elegida . '.';
        }
        return '';
    }

    /** Igual que revisa_base, leyendo un CSV (UTF-8, separado por comas). */
    public static function revisa_base_csv( $ruta, $slug ) {
        $fh = ( is_readable( $ruta ) ) ? fopen( $ruta, 'r' ) : false;
        if ( ! $fh ) return '';
        $primera = fgets( $fh );
        if ( $primera === false ) { fclose( $fh ); return ''; }
        $columnas = array_map( 'trim', str_getcsv( preg_replace( '/^\xEF\xBB\xBF/', '', $primera ), ',', '"', '\\' ) );
        $filas = array();
        while ( ( $f = fgetcsv( $fh, 0, ',', '"', '\\' ) ) !== false ) {
            if ( $f !== array( null ) ) $filas[] = $f;
        }
        fclose( $fh );
        return self::revisa_base( $columnas, $filas, $slug );
    }

    /** Igual que revisa_base, con el JSON optimizado ya decodificado. */
    public static function revisa_base_json( $json, $slug ) {
        if ( ! is_array( $json ) || empty( $json['columnas'] ) || empty( $json['filas'] ) || ! is_array( $json['filas'] ) ) return '';
        return self::revisa_base( $json['columnas'], $json['filas'], $slug );
    }

    // -------------------------------------------------------------------------
    // 2. Revisión de los archivos cargados
    // -------------------------------------------------------------------------

    /**
     * Revisa los archivos activos de un apartado (o de todos, con $seccion vacía).
     * Devuelve array( 'fecha' => fecha y hora, 'seccion' => apartado pedido, 'revisados' => array( apartado => cantidad ),
     * 'problemas' => array( array( 'seccion', 'donde', 'archivo', 'problema' ), ... ) ).
     */
    public static function revisar( $seccion = '' ) {
        global $wpdb;
        $secciones = self::secciones();
        $pedidas   = isset( $secciones[ $seccion ] ) ? array( $seccion ) : array_keys( $secciones );
        $res = array( 'fecha' => current_time( 'mysql' ), 'seccion' => isset( $secciones[ $seccion ] ) ? $seccion : '', 'revisados' => array(), 'problemas' => array() );

        $tipos = array( 'geojson' => 'geojson', 'localidades' => 'localidades_geojson', 'static' => 'static_min', 'marcadores' => 'marcador' );
        $tabla = $wpdb->prefix . 'siarhe_static_assets';
        foreach ( $pedidas as $s ) {
            $filas = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $tabla WHERE tipo_archivo = %s AND es_activo = 1 ORDER BY anio_reporte DESC, entidad_slug", $tipos[ $s ] ) );
            $res['revisados'][ $s ] = count( (array) $filas );
            foreach ( (array) $filas as $f ) {
                if ( $s === 'static' ) $hallazgos = self::revisa_fila_base( $f );
                elseif ( $s === 'marcadores' ) $hallazgos = self::revisa_fila_marcador( $f );
                else $hallazgos = self::revisa_fila_geo( $f, $s === 'geojson' ? 'base' : 'loc' );
                foreach ( $hallazgos as $h ) {
                    $res['problemas'][] = array( 'seccion' => $s, 'donde' => $h[0], 'archivo' => basename( (string) $f->ruta_archivo ), 'problema' => $h[1] );
                }
            }
        }
        return $res;
    }

    /** Nombre de la entidad de un registro, o su identificador si ya no está en el catálogo. */
    private static function nombre_entidad( $slug ) {
        $entidades = siarhe_get_entities();
        return isset( $entidades[ $slug ] ) ? $entidades[ $slug ]['nombre'] : $slug;
    }

    /** Geometría (mapa base o capa de localidades): entidad vigente, marco, archivo y contenido. */
    private static function revisa_fila_geo( $f, $capa ) {
        $entidades = siarhe_get_entities();
        $donde = self::nombre_entidad( $f->entidad_slug ) . ' · marco ' . (int) $f->anio_reporte;
        if ( ! isset( $entidades[ $f->entidad_slug ] ) ) {
            return array( array( $donde, 'La entidad «' . $f->entidad_slug . '» ya no existe en el plugin; el archivo no se usa y se puede eliminar.' ) );
        }
        $out = array();
        if ( ! Siarhe_Geo::es_marco( (int) $f->anio_reporte ) ) $out[] = array( $donde, 'El marco ' . (int) $f->anio_reporte . ' no es un marco quinquenal válido.' );
        $ruta = SIARHE_UPLOAD_DIR . ltrim( (string) $f->ruta_archivo, '/' );
        if ( ! is_file( $ruta ) ) {
            $out[] = array( $donde, 'El archivo no está en el servidor.' );
            return $out;
        }
        $data = json_decode( (string) file_get_contents( $ruta ), true );
        if ( ! is_array( $data ) ) {
            $out[] = array( $donde, 'El archivo no es un JSON válido.' );
        } elseif ( Siarhe_Geo::es_topologia( $data ) ) {
            $rev = Siarhe_Geo::validar_topologia( $data, $capa, $f->entidad_slug, $entidades[ $f->entidad_slug ]['CVE_ENT'] );
            if ( $rev['error'] !== '' ) $out[] = array( $donde, $rev['error'] );
        }
        unset( $data );
        return $out;
    }

    /** Base estática: entidad, tema, unidad, archivos y contenido. */
    private static function revisa_fila_base( $f ) {
        $entidades = siarhe_get_entities();
        $temas     = siarhe_base_temas();
        $tema      = isset( $f->tema ) && $f->tema !== '' ? $f->tema : 'enfermeria';
        $donde = self::nombre_entidad( $f->entidad_slug ) . ' · ' . (int) $f->anio_reporte . ' · '
               . ( isset( $temas[ $tema ] ) ? $temas[ $tema ]['label'] : $tema ) . ' · ' . $f->unidad;
        if ( ! isset( $entidades[ $f->entidad_slug ] ) ) {
            return array( array( $donde, 'La entidad «' . $f->entidad_slug . '» ya no existe en el plugin; la base no se usa y se puede eliminar.' ) );
        }
        $out = array();
        if ( ! isset( $temas[ $tema ] ) ) $out[] = array( $donde, 'El tema «' . $tema . '» ya no existe.' );
        elseif ( ! isset( $temas[ $tema ]['unidades'][ $f->unidad ] ) ) $out[] = array( $donde, 'La unidad «' . $f->unidad . '» no es de este tema.' );

        $csv  = ! empty( $f->ruta_archivo ) ? SIARHE_UPLOAD_DIR . ltrim( $f->ruta_archivo, '/' ) : '';
        $json = ! empty( $f->ruta_json ) ? SIARHE_UPLOAD_DIR . ltrim( $f->ruta_json, '/' ) : '';
        $hay_csv = ( $csv !== '' && is_file( $csv ) ); $hay_json = ( $json !== '' && is_file( $json ) );
        if ( ! $hay_csv && ! $hay_json ) {
            $out[] = array( $donde, 'Los archivos de la base no están en el servidor.' );
            return $out;
        }
        if ( ! $hay_csv ) $out[] = array( $donde, 'Falta el CSV de la base (solo está el JSON).' );

        if ( $hay_json ) {
            $data = json_decode( (string) file_get_contents( $json ), true );
            if ( Siarhe_Static_Json::validate( $data ) ) {
                $out[] = array( $donde, 'El JSON de la base no es válido.' );
            } else {
                if ( isset( $data['anio'] ) && (int) $data['anio'] !== (int) $f->anio_reporte ) $out[] = array( $donde, 'El archivo es de ' . (int) $data['anio'] . ' y está cargado en ' . (int) $f->anio_reporte . '.' );
                if ( isset( $data['unidad'] ) && $data['unidad'] !== $f->unidad ) $out[] = array( $donde, 'El archivo cuenta ' . $data['unidad'] . ' y está cargado como ' . $f->unidad . '.' );
                $error = self::revisa_base_json( $data, $f->entidad_slug );
                if ( $error !== '' ) $out[] = array( $donde, self::como_cargado( $error ) );
            }
            unset( $data );
        } else {
            $error = self::revisa_base_csv( $csv, $f->entidad_slug );
            if ( $error !== '' ) $out[] = array( $donde, self::como_cargado( $error ) );
        }
        return $out;
    }

    /** Los mensajes de revisa_base hablan de la entidad elegida en el formulario; aquí, de dónde está cargado el archivo. */
    private static function como_cargado( $mensaje ) {
        return str_replace( ' y elegiste ', ' y está cargado en ', $mensaje );
    }

    /** Marcador: tipo vigente, periodo, archivos y contenido de la capa. */
    private static function revisa_fila_marcador( $f ) {
        $tipos   = siarhe_marker_types();
        $periodo = ( (int) $f->anio_reporte > 0 ) ? (string) (int) $f->anio_reporte : Siarhe_Marker_Json::PERIODO_TOTAL;
        $donde   = ( isset( $tipos[ $f->entidad_slug ] ) ? $tipos[ $f->entidad_slug ]['label'] : $f->entidad_slug ) . ' · ' . ( $periodo === Siarhe_Marker_Json::PERIODO_TOTAL ? 'Todas' : $periodo );
        if ( ! isset( $tipos[ $f->entidad_slug ] ) ) {
            return array( array( $donde, 'El tipo de marcador «' . $f->entidad_slug . '» ya no existe; el archivo no se usa.' ) );
        }
        $out = array();
        if ( $periodo === Siarhe_Marker_Json::PERIODO_TOTAL && ! siarhe_marker_type_has_total( $tipos[ $f->entidad_slug ] ) ) {
            $out[] = array( $donde, 'Este tipo de marcador se carga por año y el archivo está como «Todas»: súbelo de nuevo en su año.' );
        }
        $csv  = ! empty( $f->ruta_archivo ) ? SIARHE_UPLOAD_DIR . ltrim( $f->ruta_archivo, '/' ) : '';
        $json = ! empty( $f->ruta_json ) ? SIARHE_UPLOAD_DIR . ltrim( $f->ruta_json, '/' ) : '';
        $hay_csv = ( $csv !== '' && is_file( $csv ) ); $hay_json = ( $json !== '' && is_file( $json ) );
        if ( ! $hay_csv && ! $hay_json ) {
            $out[] = array( $donde, 'Los archivos del marcador no están en el servidor.' );
            return $out;
        }
        if ( ! $hay_json ) {
            $out[] = array( $donde, 'Falta el JSON del marcador: el mapa lee el CSV, que pesa más. Vuelve a subir el archivo para crearlo.' );
            return $out;
        }
        $data = json_decode( (string) file_get_contents( $json ), true );
        $rev  = Siarhe_Marker_Json::validar( $data );
        if ( $rev['error'] !== '' ) {
            $out[] = array( $donde, $rev['error'] );
        } elseif ( isset( $data['periodo'] ) && Siarhe_Marker_Json::periodo( $data['periodo'] ) !== '' && Siarhe_Marker_Json::periodo( $data['periodo'] ) !== $periodo ) {
            $out[] = array( $donde, 'La capa dice ser del periodo «' . $data['periodo'] . '» y está cargada en «' . ( $periodo === Siarhe_Marker_Json::PERIODO_TOTAL ? 'Todas' : $periodo ) . '».' );
        }
        unset( $data );
        return $out;
    }
}
