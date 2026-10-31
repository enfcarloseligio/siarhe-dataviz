<?php // /includes/class-siarhe-versions.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Copias de seguridad de los archivos de datos (GeoJSON, localidades, CSV y marcadores).
 *
 * Cada vez que un archivo se reemplaza, el anterior se guarda en la carpeta
 * `_versions/` junto a el, como `{nombre}-{AAAAMMDD-HHMMSS}.{ext}` (o `.ext.gz` si la
 * compresion esta activa). Cuantas copias se conservan por archivo se configura
 * en Configuracion > Versiones (0 a 5, por defecto 1).
 */
class Siarhe_Versions {

    const OPT_KEEP   = 'siarhe_versions_keep';
    const OPT_GZ     = 'siarhe_versions_compress';
    const OPT_LOG    = 'siarhe_versions_log';
    const OPT_EVENTS = 'siarhe_versions_events';

    const DEFAULT_KEEP = 1;
    const MAX_KEEP     = 5;
    const MAX_EVENTS   = 15;

    /**
     * Cargas que guardan copias: carpeta (dentro de siarhe-data) => etiqueta.
     * Una carga nueva solo debe llamar a Siarhe_Versions::archive() al reemplazar su archivo
     * y agregarse aqui: aparece sola en el historial y en su filtro.
     */
    const TYPES = array(
        'geojson'       => 'Geolocalización',
        'localidades'   => 'Localidades',
        'static-min'    => 'Bases estáticas',
        'markers'       => 'Marcadores',
        'pivote'        => 'Pivotes',
        'formaciones'   => 'Formaciones',
        'escuelas'      => 'Escuelas',
        'investigacion' => 'Investigación',
        'carreras'      => 'Carreras',
    );

    /** Tipos que siempre aparecen en el filtro del historial aunque no tengan copias. */
    const CORE_TYPES = array( 'geojson', 'localidades', 'static-min', 'markers' );

    public static function dirs() {
        return array_keys( self::TYPES );
    }

    public static function type_label( $dir ) {
        return isset( self::TYPES[ $dir ] ) ? self::TYPES[ $dir ] : $dir;
    }

    // -------------------------------------------------------------------------
    // CONFIGURACION
    // -------------------------------------------------------------------------

    /** Copias a conservar por archivo (0 a 5). */
    public static function keep() {
        $v = get_option( self::OPT_KEEP, self::DEFAULT_KEEP );
        return self::sanitize_keep( $v );
    }

    public static function sanitize_keep( $value ) {
        return max( 0, min( self::MAX_KEEP, (int) $value ) );
    }

    public static function sanitize_bool( $value ) {
        return ( $value === '1' || $value === 1 || $value === true ) ? '1' : '0';
    }

    /** La extension zlib de PHP permite comprimir y descomprimir .gz. */
    public static function zlib_available() {
        return function_exists( 'gzopen' ) && function_exists( 'gzread' ) && function_exists( 'gzwrite' );
    }

    /** Compresion activada por el usuario y posible en este servidor. */
    public static function compress_enabled() {
        return get_option( self::OPT_GZ, '0' ) === '1' && self::zlib_available();
    }

    /** Al cambiar el numero de copias, se eliminan las que excedan el nuevo limite. */
    public static function on_keep_changed() {
        self::prune_all();
    }

    private static function base_dir() {
        if ( defined( 'SIARHE_UPLOAD_DIR' ) ) return rtrim( SIARHE_UPLOAD_DIR, '/\\' );
        $up = wp_upload_dir();
        return rtrim( $up['basedir'], '/\\' ) . '/siarhe-data';
    }

    // -------------------------------------------------------------------------
    // ARCHIVAR
    // -------------------------------------------------------------------------

    /**
     * Guarda una copia del archivo actual antes de reemplazarlo y recorta el
     * historial al numero de copias configurado.
     */
    public static function archive( $file_path ) {
        if ( ! is_file( $file_path ) ) return;

        $keep = self::keep();
        $dir  = dirname( $file_path );
        $name = pathinfo( $file_path, PATHINFO_FILENAME );
        $ext  = pathinfo( $file_path, PATHINFO_EXTENSION );
        $vdir = $dir . '/_versions';

        if ( $keep > 0 && wp_mkdir_p( $vdir ) ) {
            $dest = $vdir . '/' . $name . '-' . gmdate( 'Ymd-His' ) . '.' . $ext;
            if ( self::compress_enabled() ) {
                $dest .= '.gz';
                $ok = self::gz_copy( $file_path, $dest );
            } else {
                $ok = copy( $file_path, $dest );
            }
            if ( $ok ) {
                $user = wp_get_current_user();
                self::log_set( self::rel( $dest ), array(
                    'by' => $user && $user->exists() ? ( $user->display_name ?: $user->user_login ) : 'Sistema',
                    'at' => current_time( 'mysql' ),
                ) );
            } elseif ( is_file( $dest ) ) {
                @unlink( $dest );
            }
        }

        self::prune_dir( $vdir, $name, $ext, $keep );
    }

    /** Elimina las versiones de un archivo que excedan $keep (las mas antiguas primero). */
    private static function prune_dir( $vdir, $name, $ext, $keep ) {
        if ( ! is_dir( $vdir ) ) return;
        $pattern  = '/^' . preg_quote( $name, '/' ) . '-\d{8}-\d{6}\.' . preg_quote( $ext, '/' ) . '(\.gz)?$/';
        $versions = array();
        foreach ( (array) scandir( $vdir ) as $entry ) {
            if ( preg_match( $pattern, $entry ) ) $versions[] = $entry;
        }
        rsort( $versions );
        foreach ( array_slice( $versions, $keep ) as $old ) {
            if ( @unlink( $vdir . '/' . $old ) ) self::log_remove( self::rel( $vdir . '/' . $old ) );
        }
    }

    /** Aplica el limite actual a todas las versiones existentes. */
    public static function prune_all() {
        $keep = self::keep();
        foreach ( self::group_versions( self::list_all() ) as $group ) {
            foreach ( array_slice( $group, $keep ) as $v ) {
                if ( @unlink( self::base_dir() . '/' . $v['rel'] ) ) self::log_remove( $v['rel'] );
            }
        }
    }

    // -------------------------------------------------------------------------
    // CONSULTAS
    // -------------------------------------------------------------------------

    /** Todas las versiones guardadas, ordenadas por archivo y de la mas reciente a la mas antigua. */
    public static function list_all() {
        $base = self::base_dir();
        $log  = get_option( self::OPT_LOG, array() );
        if ( ! is_array( $log ) ) $log = array();
        $out = array();

        foreach ( self::dirs() as $d ) {
            $vdir = $base . '/' . $d . '/_versions';
            if ( ! is_dir( $vdir ) ) continue;
            foreach ( (array) scandir( $vdir ) as $f ) {
                if ( ! preg_match( '/^(.+)-(\d{8})-(\d{6})\.([A-Za-z0-9]+)(\.gz)?$/', $f, $m ) ) continue;
                $rel = $d . '/_versions/' . $f;
                $dt  = DateTime::createFromFormat( 'Ymd-His', $m[2] . '-' . $m[3], new DateTimeZone( 'UTC' ) );
                $out[] = array(
                    'rel'   => $rel,
                    'dir'   => $d,
                    'file'  => $m[1] . '.' . $m[4],
                    'group' => $d . '/' . $m[1] . '.' . $m[4],
                    'size'  => (int) @filesize( $vdir . '/' . $f ),
                    'ts'    => $dt ? $dt->getTimestamp() : 0,
                    'gz'    => ! empty( $m[5] ),
                    'by'    => isset( $log[ $rel ]['by'] ) ? $log[ $rel ]['by'] : '',
                );
            }
        }

        usort( $out, function ( $a, $b ) {
            $c = strcmp( $a['group'], $b['group'] );
            return $c !== 0 ? $c : $b['ts'] <=> $a['ts'];
        } );
        return $out;
    }

    /** Agrupa una lista de list_all() por archivo. */
    private static function group_versions( $list ) {
        $groups = array();
        foreach ( $list as $v ) $groups[ $v['group'] ][] = $v;
        return $groups;
    }

    /** Cuantas versiones se eliminarian si el limite fuera $n. */
    public static function excess( $n ) {
        $total = 0;
        foreach ( self::group_versions( self::list_all() ) as $group ) {
            $total += max( 0, count( $group ) - (int) $n );
        }
        return $total;
    }

    /** Resumen para el Dashboard y Configuracion: copias, archivos con copias y espacio. */
    public static function summary() {
        $list  = self::list_all();
        $bytes = 0;
        foreach ( $list as $v ) $bytes += $v['size'];
        return array(
            'count' => count( $list ),
            'files' => count( self::group_versions( $list ) ),
            'bytes' => $bytes,
        );
    }

    // -------------------------------------------------------------------------
    // RESTAURAR / ELIMINAR
    // -------------------------------------------------------------------------

    /** Valida una ruta relativa de version y devuelve sus datos, o null si no es valida. */
    private static function resolve( $rel ) {
        $rel = str_replace( '\\', '/', (string) $rel );
        $dirs = implode( '|', array_map( function ( $d ) { return preg_quote( $d, '#' ); }, self::dirs() ) );
        if ( ! preg_match( '#^(' . $dirs . ')/_versions/(.+)-(\d{8})-(\d{6})\.([A-Za-z0-9]+)(\.gz)?$#', $rel, $m ) ) return null;
        if ( strpbrk( $m[2], '/\\' ) !== false || strpos( $m[2], '..' ) !== false ) return null;
        $path = self::base_dir() . '/' . $rel;
        if ( ! is_file( $path ) ) return null;
        return array(
            'path' => $path,
            'dir'  => $m[1],
            'name' => $m[2] . '.' . $m[5],
            'gz'   => ! empty( $m[6] ),
        );
    }

    /**
     * Restaura una version sobre el archivo actual. Antes se guarda una copia del
     * archivo actual (si el limite de copias es mayor que 0), de modo que se puede deshacer.
     * Devuelve true o un mensaje de error.
     */
    public static function restore( $rel ) {
        $info = self::resolve( $rel );
        if ( ! $info ) return 'La versión indicada no existe o no es válida.';
        if ( $info['gz'] && ! self::zlib_available() ) return 'Esta copia está comprimida y el servidor no tiene zlib para descomprimirla.';

        $target = self::base_dir() . '/' . $info['dir'] . '/' . $info['name'];
        $tmp    = $target . '.restore.tmp';

        // Se lee primero la version a un temporal: archivar el actual puede recortar el historial
        $ok = $info['gz'] ? self::gunzip_copy( $info['path'], $tmp ) : copy( $info['path'], $tmp );
        if ( ! $ok ) {
            if ( is_file( $tmp ) ) @unlink( $tmp );
            return 'No se pudo leer la versión a restaurar.';
        }

        self::archive( $target );

        $ok = copy( $tmp, $target );
        @unlink( $tmp );
        if ( ! $ok ) return 'No se pudo escribir el archivo restaurado. Revisa los permisos de la carpeta de datos.';

        siarhe_build_read_copy( $target ); // la copia de lectura del mapa se actualiza con la version restaurada
        self::log_event( 'restauró', $info['dir'] . '/' . $info['name'] );
        return true;
    }

    /** Elimina una version concreta. Devuelve true o un mensaje de error. */
    public static function delete( $rel ) {
        $info = self::resolve( $rel );
        if ( ! $info ) return 'La versión indicada no existe o no es válida.';
        if ( ! @unlink( $info['path'] ) ) return 'No se pudo eliminar la versión. Revisa los permisos.';
        self::log_remove( self::rel( $info['path'] ) );
        self::log_event( 'eliminó una copia de', $info['dir'] . '/' . $info['name'] );
        return true;
    }

    // -------------------------------------------------------------------------
    // AUDITORIA
    // -------------------------------------------------------------------------

    private static function rel( $path ) {
        $path = str_replace( '\\', '/', $path );
        $base = str_replace( '\\', '/', self::base_dir() ) . '/';
        return strpos( $path, $base ) === 0 ? substr( $path, strlen( $base ) ) : $path;
    }

    private static function log_set( $rel, $entry ) {
        $log = get_option( self::OPT_LOG, array() );
        if ( ! is_array( $log ) ) $log = array();
        $log[ $rel ] = $entry;
        update_option( self::OPT_LOG, $log, false );
    }

    private static function log_remove( $rel ) {
        $log = get_option( self::OPT_LOG, array() );
        if ( is_array( $log ) && isset( $log[ $rel ] ) ) {
            unset( $log[ $rel ] );
            update_option( self::OPT_LOG, $log, false );
        }
    }

    /** Registro de acciones manuales (restaurar / eliminar), las ultimas MAX_EVENTS. */
    private static function log_event( $action, $file ) {
        $events = get_option( self::OPT_EVENTS, array() );
        if ( ! is_array( $events ) ) $events = array();
        $user = wp_get_current_user();
        array_unshift( $events, array(
            'by'     => $user && $user->exists() ? ( $user->display_name ?: $user->user_login ) : 'Sistema',
            'at'     => current_time( 'mysql' ),
            'action' => $action,
            'file'   => $file,
        ) );
        update_option( self::OPT_EVENTS, array_slice( $events, 0, self::MAX_EVENTS ), false );
    }

    public static function events() {
        $events = get_option( self::OPT_EVENTS, array() );
        return is_array( $events ) ? $events : array();
    }

    // -------------------------------------------------------------------------
    // COPIA CON COMPRESION (en bloques, sin cargar el archivo completo en memoria)
    // -------------------------------------------------------------------------

    private static function gz_copy( $src, $dest ) {
        $in  = @fopen( $src, 'rb' );
        $out = @gzopen( $dest, 'wb6' );
        if ( ! $in || ! $out ) {
            if ( $in ) fclose( $in );
            if ( $out ) gzclose( $out );
            return false;
        }
        $ok = true;
        while ( ! feof( $in ) ) {
            $chunk = fread( $in, 1048576 );
            if ( $chunk === false || ( $chunk !== '' && gzwrite( $out, $chunk ) === false ) ) { $ok = false; break; }
        }
        fclose( $in );
        gzclose( $out );
        return $ok;
    }

    private static function gunzip_copy( $src, $dest ) {
        $in  = @gzopen( $src, 'rb' );
        $out = @fopen( $dest, 'wb' );
        if ( ! $in || ! $out ) {
            if ( $in ) gzclose( $in );
            if ( $out ) fclose( $out );
            return false;
        }
        $ok = true;
        while ( ! gzeof( $in ) ) {
            $chunk = gzread( $in, 1048576 );
            if ( $chunk === false || ( $chunk !== '' && fwrite( $out, $chunk ) === false ) ) { $ok = false; break; }
        }
        gzclose( $in );
        fclose( $out );
        return $ok;
    }
}
