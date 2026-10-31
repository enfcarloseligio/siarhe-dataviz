<?php // /admin/partials/upload-tabs/tab-static.php
if ( ! defined( 'ABSPATH' ) ) exit;

// Solo declara lo que cambia entre tipos de carga; el resto vive en _uploader.php
$siarhe_up = array(
    'key'               => 'static',
    'tipo'              => 'static_min',
    'action_upload'     => 'siarhe_upload_static',
    'action_delete'     => 'siarhe_delete_static',
    'action_meta'       => 'siarhe_update_static_meta',
    'nonce_upload'      => 'siarhe_upload_static_nonce',
    'nonce_delete'      => 'siarhe_delete_static_nonce_',
    'nonce_meta'        => 'siarhe_update_static_meta_nonce',
    'msg_ok'            => 'Base estática cargada y procesada correctamente.',
    'msg_updated'       => 'Metadatos de la base estática actualizados en el sistema.',
    'msg_deleted'       => 'Archivo estático y metadatos eliminados permanentemente.',
    'title'             => '📤 Cargar Base Estática Minificada',
    'title_help'        => 'Sube el CSV con los datos que se pintan en el mapa y la tabla de la entidad.',
    'info'              => array(
        '<strong>Formatos admitidos:</strong> Archivos de datos <code>.csv</code> separados por comas.',
        '<strong>Nomenclatura:</strong> El archivo se normalizará automáticamente con el nombre estándar <code>{entidad}.csv</code> (ej. aguascalientes.csv).',
        '<strong>Estructura Obligatoria:</strong> Es indispensable que las bases de datos utilicen y preserven las variables estructurales: <code>CVE_ENT</code>, <code>CVE_MUN</code>, <code>CVE_LOC</code>.',
    ),
    'entity_label'      => 'Entidad Federativa / Alcance',
    'entity_help'       => 'Entidad (o nivel nacional, clave 33) a la que pertenece la base. Solo hay un CSV activo por entidad: al subir otro se reemplaza y se guardan las 3 versiones anteriores.',
    'file_label'        => 'Archivo CSV de Datos',
    'file_help'         => 'CSV con una fila por region (estado o municipio) y una columna por metrica. Los nombres de columna deben coincidir con las claves definidas en Configuracion > Metricas.',
    'accept'            => '.csv',
    'check'             => 'static-csv',
    'ref_placeholder'   => 'Ej: Fuente: SIARHE, Secretaría de Salud (2026).',
    'notes_placeholder' => 'Notas sobre el proceso de limpieza y minificación de datos...',
    'table_title'       => 'Estado de Bases Estáticas',
    'table_help'        => 'Resumen por entidad de los CSV cargados. Desde aqui puedes copiar el enlace, editar los metadatos, descargar o eliminar.',
    'status_icon'       => 'database',
    'status_ok'         => 'Archivo Cargado',
    'status_off'        => 'Sin archivo',
    'delete_confirm'    => '⚠️ ATENCIÓN: ¿Estás seguro de eliminar este archivo CSV de la base de datos? Esta acción borrará el registro y el archivo físico del servidor de forma irreversible.',
    'delete_title'      => 'Eliminar Archivo de Base de Datos',
    'modal_title'       => 'Editar Metadatos',
);

include __DIR__ . '/_uploader.php';
