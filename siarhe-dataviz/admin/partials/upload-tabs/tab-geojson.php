<?php // /admin/partials/upload-tabs/tab-geojson.php
/**
 * Pestaña Geolocalizacion: mapa base de cada entidad (entidades en el nacional, municipios en el de cada estado),
 * un archivo por marco geoestadistico. Solo declara lo que cambia; el formulario y la tabla viven en _uploader.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$siarhe_up = array(
    'key'               => 'geojson',
    'tipo'              => 'geojson',
    'action_upload'     => 'siarhe_upload_geojson',
    'action_delete'     => 'siarhe_delete_geojson',
    'action_meta'       => 'siarhe_update_geojson_meta',
    'nonce_upload'      => 'siarhe_upload_nonce',
    'nonce_delete'      => 'siarhe_delete_nonce_',
    'nonce_meta'        => 'siarhe_update_meta_nonce',
    'msg_ok'            => 'Mapa cargado y procesado correctamente.',
    'msg_updated'       => 'Metadatos del mapa actualizados en el sistema.',
    'msg_deleted'       => 'Mapa y metadatos eliminados permanentemente.',
    'title'             => '📤 Cargar Mapa Base (TopoJSON)',
    'title_help'        => 'Sube el mapa de la entidad (límites de entidades o de municipios) para un marco geoestadístico. Es la base sobre la que se colorean los datos. Al reemplazar el archivo se guardan copias de la versión anterior (se configuran en Configuración › Versiones y Copias).',
    'info'              => array(
        '<strong>Un mapa por entidad y marco.</strong> El marco es quinquenal (2005, 2010, 2015…) y dibuja los datos de sus cinco años; el mapa cambia solo cuando el visitante elige un año de otro quinquenio. Subir otro archivo para la misma entidad y marco lo reemplaza.',
        '<strong>Formatos admitidos:</strong> <code>.json</code> o <code>.topojson</code> con una topología <strong>TopoJSON</strong>, tal como la exporta RHEMX (<code>python src/geo_mapas.py</code>; los archivos quedan en <code>data/processed/mapas/geo/&lt;marco&gt;/Capa 1 Geolocalizacion/</code>).',
        '<strong>Qué archivo va aquí:</strong> en República Mexicana, <code>33_entidades.json</code>; en cada entidad, su <code>NN_municipios.json</code> (por ejemplo <code>01_municipios.json</code> en Aguascalientes).',
        '<strong>Estructura:</strong> objeto <code>datos</code> cuyas geometrías llevan <code>c</code> (clave: 2 dígitos la entidad, 5 el municipio) y <code>n</code> (nombre), con coordenadas en longitud/latitud <strong>WGS84 (EPSG:4326)</strong>. La clave es la que une cada polígono con su fila de la base estática.',
        '<strong>Nomenclatura:</strong> el archivo se guarda como <code>{entidad}-{marco}.json</code> (ej. <code>aguascalientes-2025.json</code>). Los GeoJSON cargados antes se siguen leyendo y quedaron en el marco 2025.',
    ),
    'entity_label'      => 'Entidad Federativa',
    'entity_help'       => 'Entidad a la que pertenece el archivo. Define el shortcode donde se usará, por ejemplo CVE_ENT 09 para Ciudad de México.',
    'file_label'        => 'Archivo del Mapa (TopoJSON)',
    'file_help'         => 'Archivo .json o .topojson con la topología del mapa en WGS84 (EPSG:4326). El plugin comprueba que las claves correspondan a la entidad y a la capa antes de guardarlo.',
    'accept'            => '.json,.topojson',
    'check'             => 'geojson',
    'ref_placeholder'   => 'Ej: INEGI. (2025). Marco Geoestadístico. Diciembre 2025.',
    'notes_placeholder' => 'Notas internas: fecha de publicación del marco, simplificación aplicada…',
    'table_title'       => 'Mapas Base Cargados',
    'table_help'        => 'En cada marco con al menos un archivo se listan todas las entidades: las que ya tienen mapa y las que faltan. La tabla abre en el marco más reciente; elige «Todos» para verlos juntos. Desde aquí puedes copiar el enlace, editar los metadatos, descargar o eliminar.',
    'status_icon'       => 'location',
    'status_ok'         => 'Mapeado',
    'status_off'        => 'Sin Mapa',
    'delete_confirm'    => '⚠️ ATENCIÓN: ¿Estás seguro de eliminar este mapa? Esta acción borrará el registro y el archivo físico del servidor de forma irreversible.',
    'delete_title'      => 'Eliminar Mapa y Registro',
    'modal_title'       => 'Editar Metadatos del Mapa',
);

include __DIR__ . '/_uploader.php';
