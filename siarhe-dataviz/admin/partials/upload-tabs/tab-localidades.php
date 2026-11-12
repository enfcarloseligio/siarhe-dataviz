<?php // /admin/partials/upload-tabs/tab-localidades.php
/**
 * Pestaña Localidades (Geo): capa detallada de cada entidad (municipios en el mapa nacional, localidades en el de cada
 * estado), un archivo por marco geoestadistico. Solo declara lo que cambia; el formulario y la tabla viven en _uploader.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$siarhe_up = array(
    'key'               => 'localidades',
    'tipo'              => 'localidades_geojson',
    'action_upload'     => 'siarhe_upload_localidades',
    'action_delete'     => 'siarhe_delete_localidades',
    'action_meta'       => 'siarhe_update_localidades_meta',
    'nonce_upload'      => 'siarhe_upload_localidades_nonce',
    'nonce_delete'      => 'siarhe_delete_localidades_nonce_',
    'nonce_meta'        => 'siarhe_update_localidades_meta_nonce',
    'msg_ok'            => 'Capa detallada cargada y procesada correctamente.',
    'msg_updated'       => 'Metadatos de la capa detallada actualizados en el sistema.',
    'msg_deleted'       => 'Capa detallada y metadatos eliminados permanentemente.',
    'title'             => '🏘️ Cargar Capa Detallada (TopoJSON)',
    'title_help'        => 'Capa opcional que el visitante activa en «Vista Geográfica»: municipios en el mapa nacional y localidades en el de cada entidad, para un marco geoestadístico.',
    'info'              => array(
        '<strong>Una capa por entidad y marco.</strong> El marco es quinquenal (2005, 2010, 2015…) y acompaña a los datos de sus cinco años. Subir otro archivo para la misma entidad y marco lo reemplaza.',
        '<strong>Formatos admitidos:</strong> <code>.json</code> o <code>.topojson</code> con una topología <strong>TopoJSON</strong>, tal como la exporta RHEMX (<code>python src/geo_mapas.py</code>; los archivos quedan en <code>data/processed/geo/&lt;marco&gt;/</code>).',
        '<strong>Qué archivo va aquí:</strong> en República Mexicana, <code>municipios_republica.json</code>; en cada entidad, su <code>localidades_NN.json</code> (por ejemplo <code>localidades_01.json</code> en Aguascalientes).',
        '<strong>Estructura:</strong> objeto <code>datos</code> cuyas geometrías llevan <code>c</code> (clave: 5 dígitos el municipio, 9 la localidad), <code>n</code> (nombre) y, en las localidades, <code>t</code> (<code>u</code> urbana, <code>r</code> rural), con coordenadas en longitud/latitud <strong>WGS84 (EPSG:4326)</strong>.',
        '<strong>Nomenclatura:</strong> el archivo se guarda como <code>{entidad}-{marco}.json</code> en la carpeta de localidades. Los GeoJSON cargados antes se siguen leyendo y quedaron en el marco 2025.',
    ),
    'entity_label'      => 'Entidad Federativa',
    'entity_help'       => 'Entidad a la que pertenece el archivo. Define el shortcode donde se usará, por ejemplo CVE_ENT 09 para Ciudad de México.',
    'file_label'        => 'Archivo de la Capa Detallada (TopoJSON)',
    'file_help'         => 'Archivo .json o .topojson con los polígonos de municipios (nacional) o de localidades (entidad), en WGS84 (EPSG:4326). El plugin comprueba que las claves correspondan a la entidad y a la capa antes de guardarlo.',
    'accept'            => '.json,.topojson',
    'check'             => 'geojson',
    'ref_placeholder'   => 'Ej: INEGI. (2025). Marco Geoestadístico. Diciembre 2025.',
    'notes_placeholder' => 'Notas internas: fecha de publicación del marco, simplificación aplicada…',
    'table_title'       => 'Capas Detalladas Cargadas',
    'table_help'        => 'Una fila por entidad y marco cargado; las entidades sin capa aparecen una vez.',
    'status_icon'       => 'location',
    'status_ok'         => 'Mapeado',
    'status_off'        => 'Sin Capa',
    'delete_confirm'    => '⚠️ ATENCIÓN: ¿Estás seguro de eliminar esta capa detallada? Esta acción borrará el registro y el archivo físico del servidor de forma irreversible.',
    'delete_title'      => 'Eliminar Capa y Registro',
    'modal_title'       => 'Editar Metadatos de la Capa',
);

include __DIR__ . '/_uploader.php';
