<?php // /admin/partials/upload-tabs/tab-localidades.php
if ( ! defined( 'ABSPATH' ) ) exit;

// Solo declara lo que cambia entre tipos de carga; el resto vive en _uploader.php
$siarhe_up = array(
    'key'               => 'localidades',
    'tipo'              => 'localidades_geojson',
    'action_upload'     => 'siarhe_upload_localidades',
    'action_delete'     => 'siarhe_delete_localidades',
    'action_meta'       => 'siarhe_update_localidades_meta',
    'nonce_upload'      => 'siarhe_upload_localidades_nonce',
    'nonce_delete'      => 'siarhe_delete_localidades_nonce_',
    'nonce_meta'        => 'siarhe_update_localidades_meta_nonce',
    'msg_ok'            => 'Mapa GeoJSON de Localidades cargado y procesado correctamente.',
    'msg_updated'       => 'Metadatos del mapa de localidades actualizados en el sistema.',
    'msg_deleted'       => 'Mapa de localidades y metadatos eliminados permanentemente.',
    'title'             => '🏘️ Cargar Mapa GeoJSON (Localidades)',
    'title_help'        => 'Capa opcional con las localidades de la entidad para mostrar mayor detalle.',
    'info'              => array(
        '<strong>Formatos admitidos:</strong> Archivos estructurados <code>.json</code> o <code>.geojson</code>.',
        '<strong>Nomenclatura:</strong> El archivo se normalizará automáticamente con el nombre estándar <code>{entidad}.geojson</code> dentro de la carpeta de localidades.',
        '<strong>Proyección:</strong> Es estrictamente obligatorio que el mapa esté exportado en formato <strong>WGS84 (EPSG:4326)</strong> para garantizar la alineación de coordenadas.',
    ),
    'entity_label'      => 'Entidad Federativa',
    'entity_help'       => 'Entidad a la que pertenece el archivo. Define el shortcode donde se usara, por ejemplo CVE_ENT 09 para Ciudad de Mexico. Solo hay un archivo activo por entidad: al subir otro se reemplaza.',
    'file_label'        => 'Archivo GeoEspacial (Localidades)',
    'file_help'         => 'GeoJSON con los puntos o poligonos de localidades de la entidad, en WGS84 (EPSG:4326). Se usa como capa de detalle en el mapa.',
    'accept'            => '.json,.geojson',
    'check'             => 'geojson',
    'ref_placeholder'   => 'Ej: Marco Geoestadístico INEGI 2025.',
    'notes_placeholder' => 'Notas sobre la simplificación topológica del mapa...',
    'table_title'       => 'Estado de Mapas (Localidades)',
    'table_help'        => 'Resumen por entidad de las capas de localidades cargadas.',
    'status_icon'       => 'location',
    'status_ok'         => 'Mapeado',
    'status_off'        => 'Sin Mapa',
    'delete_confirm'    => '⚠️ ATENCIÓN: ¿Estás seguro de eliminar este mapa GEOJSON de localidades? Esta acción borrará el registro y el archivo físico del servidor de forma irreversible.',
    'delete_title'      => 'Eliminar Mapa y Registro',
    'modal_title'       => 'Editar Metadatos GeoJSON',
);

include __DIR__ . '/_uploader.php';
