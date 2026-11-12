<?php // /includes/db/class-db-installer.php
if ( ! defined( 'ABSPATH' ) ) exit;

class Siarhe_DB_Installer {

    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. Tabla de Recursos Estáticos (GeoJSON y CSVs Minificados)
        $table_assets = $wpdb->prefix . 'siarhe_static_assets';
        
        $sql_assets = "CREATE TABLE $table_assets (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            entidad_slug varchar(50) NOT NULL,
            tipo_archivo varchar(20) NOT NULL, 
            ruta_archivo varchar(255) NOT NULL,
            anio_reporte year NOT NULL,
            unidad varchar(10) NOT NULL DEFAULT 'plazas',
            ruta_json varchar(255) DEFAULT NULL,
            fecha_corte date,
            referencia_bibliografica text,
            comentarios text,
            es_activo boolean DEFAULT 1,
            fecha_subida datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY entidad_slug (entidad_slug),
            KEY base_estatica (tipo_archivo, entidad_slug, anio_reporte, unidad)
        ) $charset_collate;";
        
        dbDelta( $sql_assets );
        
        // (Aquí dejaremos espacio para las tablas SQL maestras en la Fase 2)
    }
}