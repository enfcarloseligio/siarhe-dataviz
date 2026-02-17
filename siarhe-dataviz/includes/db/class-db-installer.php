<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Siarhe_DB_Installer {

    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. Tabla de Logs de Archivos
        $table_files = $wpdb->prefix . 'siarhe_files_log';
        $sql_files = "CREATE TABLE $table_files (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            nombre_archivo varchar(255) NOT NULL,
            tipo_base varchar(50) NOT NULL,
            anio year NOT NULL,
            fecha_corte date,
            total_registros int(11) DEFAULT 0,
            fecha_subida datetime DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        dbDelta( $sql_files );

        // Aquí añadiremos el resto de las tablas (Pivote, Formaciones, etc.)
    }
}