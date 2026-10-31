<?php // /admin/partials/upload-tabs/tab-geojson.php
if ( ! defined( 'ABSPATH' ) ) exit;

// Solo declara lo que cambia entre tipos de carga; el resto vive en _uploader.php
$siarhe_up = array(
    'key'               => 'geojson',
    'tipo'              => 'geojson',
    'action_upload'     => 'siarhe_upload_geojson',
    'action_delete'     => 'siarhe_delete_geojson',
    'action_meta'       => 'siarhe_update_geojson_meta',
    'nonce_upload'      => 'siarhe_upload_nonce',
    'nonce_delete'      => 'siarhe_delete_nonce_',
    'nonce_meta'        => 'siarhe_update_meta_nonce',
    'msg_ok'            => 'Mapa GeoJSON cargado y procesado correctamente.',
    'msg_updated'       => 'Metadatos del mapa actualizados en el sistema.',
    'msg_deleted'       => 'Mapa y metadatos eliminados permanentemente.',
    'title'             => '📤 Cargar Mapa GeoJSON',
    'title_help'        => 'Sube el mapa de la entidad (limites de municipios o estados). Es la base sobre la que se colorean los datos. Reemplazar el archivo conserva las 3 versiones anteriores.',
    'info'              => array(
        '<strong>Formatos admitidos:</strong> Archivos estructurados <code>.json</code> o <code>.geojson</code>.',
        '<strong>Nomenclatura:</strong> El archivo se normalizará automáticamente con el nombre estándar <code>{entidad}.geojson</code> (ej. aguascalientes.geojson).',
        '<strong>Proyección:</strong> Es estrictamente obligatorio que el mapa esté exportado en formato <strong>WGS84 (EPSG:4326)</strong> para garantizar la alineación de coordenadas.',
    ),
    'entity_label'      => 'Entidad Federativa',
    'entity_help'       => 'Entidad a la que pertenece el archivo. Define el shortcode donde se usara, por ejemplo CVE_ENT 09 para Ciudad de Mexico. Solo hay un archivo activo por entidad: al subir otro se reemplaza.',
    'file_label'        => 'Archivo GeoEspacial',
    'file_help'         => 'Archivo .json o .geojson en proyeccion WGS84 (EPSG:4326). Se guarda con el nombre estandar de la entidad.',
    'accept'            => '.json,.geojson',
    'check'             => 'geojson',
    'ref_placeholder'   => 'Ej: Marco Geoestadístico INEGI 2025.',
    'notes_placeholder' => 'Notas sobre la simplificación topológica del mapa...',
    'table_title'       => 'Estado de Mapas GeoJSON',
    'table_help'        => 'Resumen por entidad de los mapas cargados. Desde aqui puedes copiar el enlace, editar los metadatos, descargar o eliminar.',
    'status_icon'       => 'location',
    'status_ok'         => 'Mapeado',
    'status_off'        => 'Sin Mapa',
    'delete_confirm'    => '⚠️ ATENCIÓN: ¿Estás seguro de eliminar este mapa GEOJSON? Esta acción borrará el registro y el archivo físico del servidor de forma irreversible.',
    'delete_title'      => 'Eliminar Mapa y Registro',
    'modal_title'       => 'Editar Metadatos GeoJSON',
);

include __DIR__ . '/_uploader.php';
