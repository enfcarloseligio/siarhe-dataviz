<?php // /includes/class-siarhe-geo.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Geometrias del mapa: marcos geoestadisticos, validacion de TopoJSON y consulta de los archivos cargados.
 *
 * Cada geometria se identifica por capa + entidad + marco. Hay dos capas: "base" (entidades en el mapa nacional, municipios
 * en el de cada entidad) y "loc", la capa detallada (municipios en el nacional, localidades en el de cada entidad). El marco
 * es el quinquenio del Marco Geoestadistico del INEGI (2005, 2010, 2015...) y sirve a los cinco años que empiezan en el suyo;
 * un marco publicado entre dos quinquenios (el de 2016) se carga en el anterior (2015) y su fecha real va en la fecha de corte.
 *
 * El formato de carga es el TopoJSON que exporta RHEMX (src/geo_mapas.py): un objeto "datos" cuyas geometrias llevan las
 * propiedades "c" (clave: 2 digitos la entidad, 5 el municipio, 9 la localidad) y "n" (nombre); las localidades agregan "t"
 * (u = urbana, r = rural). Los GeoJSON (FeatureCollection) cargados antes siguen siendo validos.
 *
 *   1. Marcos geoestadisticos
 *   2. Validacion de una topologia
 *   3. Archivos cargados de una entidad
 */
class Siarhe_Geo {

    const MARCO_MIN      = 2005; // primer marco que se puede cargar
    const MARCO_PASO     = 5;    // los marcos van por quinquenio
    const MARCO_HEREDADO = 2025; // marco de los mapas cargados antes de existir el selector

    /** Capas de geometria: tipo_archivo en la tabla de archivos y carpeta dentro de siarhe-data. */
    const CAPAS = array(
        'base' => array( 'tipo' => 'geojson',             'dir' => 'geojson' ),
        'loc'  => array( 'tipo' => 'localidades_geojson', 'dir' => 'localidades' ),
    );

    // -------------------------------------------------------------------------
    // 1. Marcos geoestadisticos
    // -------------------------------------------------------------------------

    /** Marcos que se pueden cargar, del mas reciente al mas antiguo. El de 2030 aparece al llegar ese año. */
    public static function marcos() {
        $ultimo = max( self::MARCO_HEREDADO, (int) floor( (int) gmdate( 'Y' ) / self::MARCO_PASO ) * self::MARCO_PASO );
        $out = array();
        for ( $m = $ultimo; $m >= self::MARCO_MIN; $m -= self::MARCO_PASO ) $out[] = $m;
        return $out;
    }

    public static function es_marco( $anio ) {
        return in_array( (int) $anio, self::marcos(), true );
    }

    /**
     * Marco que corresponde a un año entre los disponibles: el de su quinquenio; si no esta cargado, el anterior mas
     * cercano y, si tampoco hay, el mas antiguo. Misma regla que SiarheTopo.marcoPara en el navegador. Devuelve 0 si no hay.
     */
    public static function marco_para( $anio, $disponibles ) {
        $marcos = array_values( array_filter( array_map( 'intval', (array) $disponibles ) ) );
        if ( ! $marcos ) return 0;
        sort( $marcos );
        $quinquenio = (int) floor( (int) $anio / self::MARCO_PASO ) * self::MARCO_PASO;
        $elegido = 0;
        foreach ( $marcos as $m ) {
            if ( $m <= $quinquenio ) $elegido = $m;
        }
        return $elegido ?: $marcos[0];
    }

    // -------------------------------------------------------------------------
    // 2. Validacion de una topologia
    // -------------------------------------------------------------------------

    /** Indica si el JSON decodificado es un TopoJSON. */
    public static function es_topologia( $data ) {
        return is_array( $data ) && isset( $data['type'] ) && $data['type'] === 'Topology';
    }

    /** Digitos que debe tener la clave "c" segun la capa y el alcance del mapa. */
    public static function digitos_clave( $capa, $nacional ) {
        if ( $capa === 'base' ) return $nacional ? 2 : 5;
        return $nacional ? 5 : 9;
    }

    private static function nombre_nivel( $digitos ) {
        $nombres = array( 2 => 'entidades', 5 => 'municipios', 9 => 'localidades' );
        return isset( $nombres[ $digitos ] ) ? $nombres[ $digitos ] : 'otro nivel';
    }

    /**
     * Revisa un TopoJSON antes de guardarlo.
     * $capa: 'base' o 'loc'. $slug y $cve_ent: entidad elegida en el formulario.
     * @return array( 'error' => texto ('' si se puede cargar), 'avisos' => textos no bloqueantes, 'elementos' => cantidad )
     */
    public static function validar_topologia( $data, $capa, $slug, $cve_ent ) {
        $res = array( 'error' => '', 'avisos' => array(), 'elementos' => 0 );

        if ( ! self::es_topologia( $data ) || empty( $data['objects'] ) || ! is_array( $data['objects'] )
            || empty( $data['arcs'] ) || ! is_array( $data['arcs'] ) ) {
            $res['error'] = 'El TopoJSON necesita "objects" y "arcs". Súbelo tal como lo exporta RHEMX, sin modificarlo.';
            return $res;
        }

        $objeto = isset( $data['objects']['datos'] ) ? $data['objects']['datos'] : reset( $data['objects'] );
        if ( ! isset( $data['objects']['datos'] ) ) {
            $res['avisos'][] = 'El TopoJSON no tiene el objeto "datos"; se usará el primero que trae ("' . key( $data['objects'] ) . '").';
        }
        $geometrias = ( is_array( $objeto ) && isset( $objeto['geometries'] ) && is_array( $objeto['geometries'] ) ) ? $objeto['geometries'] : array();
        if ( ! $geometrias ) {
            $res['error'] = 'El objeto de geometrías del TopoJSON está vacío.';
            return $res;
        }
        $res['elementos'] = count( $geometrias );

        // Coordenadas: la caja del archivo (bbox) debe estar en longitud / latitud
        $caja = ( isset( $data['bbox'] ) && is_array( $data['bbox'] ) && count( $data['bbox'] ) >= 4 ) ? array_map( 'floatval', $data['bbox'] ) : null;
        if ( $caja ) {
            if ( abs( $caja[0] ) > 180 || abs( $caja[2] ) > 180 || abs( $caja[1] ) > 90 || abs( $caja[3] ) > 90 ) {
                $res['error'] = 'Las coordenadas no están en longitud/latitud (WGS84, EPSG:4326).';
                return $res;
            }
            if ( $slug !== 'extranjero' && ( $caja[2] < -120 || $caja[0] > -84 || $caja[3] < 14 || $caja[1] > 34 ) ) {
                $res['avisos'][] = 'El archivo queda fuera del territorio mexicano. Verifica que sea el correcto.';
            }
        }

        // Claves: longitud segun la capa y, en los mapas de una entidad, que empiecen con su clave
        $nacional  = ( $slug === 'republica-mexicana' );
        $esperados = self::digitos_clave( $capa, $nacional );
        $sin_clave = 0; $otra_long = array(); $otra_ent = array(); $propias = 0; $vacias = 0; $validas = 0;
        foreach ( $geometrias as $g ) {
            if ( ! is_array( $g ) ) continue;
            if ( empty( $g['arcs'] ) && empty( $g['coordinates'] ) ) $vacias++;
            $p = ( isset( $g['properties'] ) && is_array( $g['properties'] ) ) ? $g['properties'] : array();
            if ( ! isset( $p['c'] ) || (string) $p['c'] === '' ) {
                // Topologia con los nombres de propiedad de INEGI (CVE_ENT / CVE_MUN): el mapa tambien la lee
                if ( ! empty( $p['CVE_ENT'] ) || ! empty( $p['CVE_MUN'] ) ) $propias++; else $sin_clave++;
                continue;
            }
            $c = (string) $p['c'];
            if ( strlen( $c ) !== $esperados ) { $otra_long[ strlen( $c ) ] = true; continue; }
            $validas++;
            if ( ! $nacional && $slug !== 'extranjero' && substr( $c, 0, 2 ) !== $cve_ent ) $otra_ent[ substr( $c, 0, 2 ) ] = true;
        }

        // Ninguna clave tiene la longitud de esta capa: el archivo es de otro nivel (entidades, municipios, localidades, AGEB...)
        $total = count( $geometrias );
        if ( $otra_long && $validas === 0 && $propias === 0 ) {
            ksort( $otra_long );
            $trae = implode( ' o ', array_keys( $otra_long ) );
            $nivel_trae = ( count( $otra_long ) === 1 ) ? self::nombre_nivel( (int) key( $otra_long ) ) : 'otro nivel';
            $res['error'] = 'El archivo es de otra capa: aquí van ' . self::nombre_nivel( $esperados ) . ' (claves de ' . $esperados
                . ' dígitos) y el archivo trae ' . $nivel_trae . ' (claves de ' . $trae . ' dígitos).';
            return $res;
        }
        if ( $otra_long ) {
            $res['avisos'][] = 'Algunas claves no tienen ' . $esperados . ' dígitos; esos elementos no se podrán unir con los datos.';
        }
        if ( $otra_ent ) {
            $lista = implode( ', ', array_keys( $otra_ent ) );
            if ( count( $otra_ent ) === 1 && $sin_clave === 0 ) {
                $res['error'] = 'El archivo es de la entidad ' . $lista . ' y elegiste la entidad ' . $cve_ent . '.';
                return $res;
            }
            $res['avisos'][] = 'El archivo trae elementos de otras entidades (' . $lista . ').';
        }
        if ( $sin_clave === $total ) {
            $res['avisos'][] = 'Ningún elemento tiene la propiedad "c" (clave); sin ella el mapa no puede colorearlos con los datos.';
        } elseif ( $sin_clave > 0 ) {
            $res['avisos'][] = $sin_clave . ' elemento(s) no tienen la propiedad "c" (clave) y no se colorearán con los datos.';
        }
        if ( $vacias > 0 && $vacias < $total ) {
            $res['avisos'][] = $vacias . ' elemento(s) no tienen geometría y no se dibujarán.';
        }
        if ( $vacias === $total ) {
            $res['error'] = 'Ningún elemento del TopoJSON tiene geometría.';
        }
        return $res;
    }

    // -------------------------------------------------------------------------
    // 3. Archivos cargados de una entidad
    // -------------------------------------------------------------------------

    /**
     * Geometrias activas de una entidad cuyo archivo existe, por capa y marco:
     * array( 'base' => array( 2025 => fila, ... ), 'loc' => array( ... ) ), con los marcos de mayor a menor.
     */
    public static function archivos( $slug ) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'siarhe_static_assets';
        $out   = array( 'base' => array(), 'loc' => array() );
        $filas = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, tipo_archivo, ruta_archivo, anio_reporte, fecha_corte, referencia_bibliografica FROM $tabla
             WHERE entidad_slug = %s AND tipo_archivo IN ('geojson','localidades_geojson') AND es_activo = 1 ORDER BY anio_reporte DESC, id DESC",
            $slug
        ) );
        foreach ( (array) $filas as $f ) {
            $capa = ( $f->tipo_archivo === 'geojson' ) ? 'base' : 'loc';
            $marco = (int) $f->anio_reporte;
            if ( isset( $out[ $capa ][ $marco ] ) || empty( $f->ruta_archivo ) ) continue;
            if ( ! file_exists( SIARHE_UPLOAD_DIR . ltrim( $f->ruta_archivo, '/' ) ) ) continue;
            $out[ $capa ][ $marco ] = $f;
        }
        return $out;
    }

    /** URL de lectura de una geometria, con version para la cache del navegador. */
    public static function url( $fila ) {
        $rel  = ltrim( $fila->ruta_archivo, '/' );
        $ruta = SIARHE_UPLOAD_DIR . $rel;
        return siarhe_data_read_url( SIARHE_UPLOAD_URL . $rel . '?v=' . ( file_exists( $ruta ) ? filemtime( $ruta ) : time() ) );
    }
}
