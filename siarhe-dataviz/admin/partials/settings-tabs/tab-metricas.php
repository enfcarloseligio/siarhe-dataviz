<?php // /admin/partials/settings-tabs/tab-metricas.php
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$editor_name = $current_user->display_name ?: $current_user->user_login;
$current_time = current_time('mysql');

$base_ranges = [
    'scale_type' => 'cuartiles',
    'custom_ranges' => false,
    'custom_colors' => false,
    'r1_min' => 'Vmin', 'r1_op' => '<', 'r1_max' => 'Q1',   'r1_color' => '',
    'r2_min' => 'Q1',   'r2_op' => '<', 'r2_max' => 'Q2',   'r2_color' => '',
    'r3_min' => 'Q2',   'r3_op' => '<', 'r3_max' => 'Q3',   'r3_color' => '',
    'r4_min' => 'Q3',   'r4_op' => '<=', 'r4_max' => 'Vmax', 'r4_color' => '' 
];

$defaults = [
    'tasa_total'                 => array_merge(['label' => 'Tasa Total', 'fullLabel' => 'Tasa de enfermeras por cada mil habitantes', 'abrev' => 'Tasa Total', 'tipo' => 'tasa', 'pair' => 'enfermeras_total', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'enfermeras_total'           => array_merge(['label' => 'Total Enfermeras', 'fullLabel' => 'Total de profesionales de enfermería', 'abrev' => 'Total Enf.', 'tipo' => 'absoluto', 'pair' => 'enfermeras_total', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'tasa_primer'                => array_merge(['label' => 'Tasa Primer Nivel', 'fullLabel' => 'Tasa de enfermeras en Primer Nivel de Atención', 'abrev' => 'Tasa Enf. 1er Nivel', 'tipo' => 'tasa', 'pair' => 'enfermeras_primer', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'enfermeras_primer'          => array_merge(['label' => 'Enfermeras Primer Nivel', 'fullLabel' => 'Enfermeras en Primer Nivel de Atención', 'abrev' => 'Enf. 1er Nivel', 'tipo' => 'absoluto', 'pair' => 'enfermeras_primer', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'tasa_segundo'               => array_merge(['label' => 'Tasa Segundo Nivel', 'fullLabel' => 'Tasa de enfermeras en Segundo Nivel de Atención', 'abrev' => 'Tasa Enf. 2do Nivel', 'tipo' => 'tasa', 'pair' => 'enfermeras_segundo', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'enfermeras_segundo'         => array_merge(['label' => 'Enfermeras Segundo Nivel', 'fullLabel' => 'Enfermeras en Segundo Nivel de Atención', 'abrev' => 'Enf. 2do Nivel', 'tipo' => 'absoluto', 'pair' => 'enfermeras_segundo', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'tasa_tercer'                => array_merge(['label' => 'Tasa Tercer Nivel', 'fullLabel' => 'Tasa de enfermeras en Tercer Nivel de Atención', 'abrev' => 'Tasa Enf. 3er Nivel', 'tipo' => 'tasa', 'pair' => 'enfermeras_tercer', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'enfermeras_tercer'          => array_merge(['label' => 'Enfermeras Tercer Nivel', 'fullLabel' => 'Enfermeras en Tercer Nivel de Atención', 'abrev' => 'Enf. 3er Nivel', 'tipo' => 'absoluto', 'pair' => 'enfermeras_tercer', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'tasa_apoyo'                 => array_merge(['label' => 'Tasa Apoyo', 'fullLabel' => 'Tasa de enfermeras en establecimientos de apoyo', 'abrev' => 'Tasa Enf. Apoyo', 'tipo' => 'tasa', 'pair' => 'enfermeras_apoyo', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'enfermeras_apoyo'           => array_merge(['label' => 'Enfermeras Apoyo', 'fullLabel' => 'Enfermeras en establecimientos de apoyo', 'abrev' => 'Enf. Apoyo', 'tipo' => 'absoluto', 'pair' => 'enfermeras_apoyo', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'tasa_administrativas'       => array_merge(['label' => 'Tasa Enfermeras Administrativas', 'fullLabel' => 'Tasa de enfermeras con funciones administrativas', 'abrev' => 'Tasa Enf. Admin.', 'tipo' => 'tasa', 'pair' => 'enfermeras_administrativas', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'enfermeras_administrativas' => array_merge(['label' => 'Enfermeras Administrativas', 'fullLabel' => 'Enfermeras con funciones administrativas', 'abrev' => 'Enf. Admin.', 'tipo' => 'absoluto', 'pair' => 'enfermeras_administrativas', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'tasa_escuelas'              => array_merge(['label' => 'Tasa Enfermeras Escuelas', 'fullLabel' => 'Tasa de enfermeras en escuelas de enfermería', 'abrev' => 'Tasa Enf. Escuelas', 'tipo' => 'tasa', 'pair' => 'enfermeras_escuelas', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'enfermeras_escuelas'        => array_merge(['label' => 'Enfermeras Escuelas', 'fullLabel' => 'Enfermeras en escuelas de enfermería', 'abrev' => 'Enf. Escuelas', 'tipo' => 'absoluto', 'pair' => 'enfermeras_escuelas', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'tasa_no_aplica'             => array_merge(['label' => 'Tasa Enfermeras Otros', 'fullLabel' => 'Tasa de enfermeras en otros establecimientos', 'abrev' => 'Tasa Enf. Otros', 'tipo' => 'tasa', 'pair' => 'enfermeras_no_aplica', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'enfermeras_no_aplica'       => array_merge(['label' => 'Enfermeras Otros', 'fullLabel' => 'Enfermeras en otros establecimientos', 'abrev' => 'Enf. Otros', 'tipo' => 'absoluto', 'pair' => 'enfermeras_no_aplica', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'tasa_no_asignado'           => array_merge(['label' => 'Tasa Enfermeras No Asignado', 'fullLabel' => 'Tasa de enfermeras con funciones no asignadas', 'abrev' => 'Tasa Enf. No Asignado', 'tipo' => 'tasa', 'pair' => 'enfermeras_no_asignado', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'enfermeras_no_asignado'     => array_merge(['label' => 'Enfermeras No Asignado', 'fullLabel' => 'Enfermeras con funciones no asignadas', 'abrev' => 'Enf. No Asignado', 'tipo' => 'absoluto', 'pair' => 'enfermeras_no_asignado', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges),
    'poblacion'                  => array_merge(['label' => 'Población', 'fullLabel' => 'Población total', 'abrev' => 'Población', 'tipo' => 'absoluto', 'pair' => 'poblacion', 'is_core' => true, 'visibilidad' => 'publico'], $base_ranges)
];

$defaults_json = wp_json_encode($defaults);

$metricas_json = get_option( 'siarhe_metricas_config', '' );
if ( empty($metricas_json) ) {
    $metricas_json = $defaults_json;
} else {
    $metricas_json = wp_unslash($metricas_json);
}
?>

<button type="button" class="button button-primary siarhe-floating-save" id="btn-floating-save">
    Guardar
</button>

<div class="card siarhe-upload-card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="margin-top: 0;">📊 Gestor de Métricas e Indicadores (CSV)</h2>
            <p style="margin-bottom: 0;">Configura los indicadores que se mostrarán en el mapa y la tabla. Las variables <strong>nativas</strong> están protegidas, pero puedes editar sus descripciones o añadir nuevas variables siempre y cuando existan en tu archivo CSV.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="button button-secondary" id="btn-add-metrica">
                <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;"></span> Añadir Nueva
            </button>
            <button type="button" class="button button-link-delete" id="btn-reset-metricas" style="color: #d63638; border: 1px solid #d63638; padding: 0 10px; border-radius: 3px; background: #fff;">
                <span class="dashicons dashicons-undo" style="margin-top:3px;"></span> Restaurar Iniciales
            </button>
        </div>
    </div>

    <input type="hidden" name="siarhe_metricas_config" id="siarhe_metricas_config" value="<?php echo esc_attr($metricas_json); ?>">
    <input type="hidden" id="siarhe_default_metricas" value="<?php echo esc_attr($defaults_json); ?>">
    <input type="hidden" id="siarhe_current_user" value="<?php echo esc_attr($editor_name); ?>">
    <input type="hidden" id="siarhe_current_time" value="<?php echo esc_attr($current_time); ?>">
</div>

<div class="card" style="max-width: 100%; padding: 0; overflow: hidden;">
    <div class="siarhe-toolbar">
        <div class="siarhe-table-controls">
            <label style="font-size: 13px; color: #3c434a;">
                Mostrar 
                <select id="siarhe-items-per-page" style="margin: 0 5px; padding: 2px 24px 2px 8px; font-size: 13px; min-height: 28px;">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="all">Todos</option>
                </select> 
                registros
            </label>
        </div>

        <div class="siarhe-pagination" style="border-top: none; padding: 0; background: transparent;">
            <div class="siarhe-page-numbers" id="siarhe-pagination-controls-top"></div>
        </div>

        <div class="siarhe-search-box">
            <span class="dashicons dashicons-search"></span>
            <input type="text" id="siarhe-search-metricas" placeholder="Buscar por clave, etiqueta o tipo...">
        </div>
    </div>

    <table id="siarhe-metricas-table" class="siarhe-table">
        <thead>
            <tr>
                <th style="width: 15%;">Clave (CSV)</th>
                <th style="width: 25%;">Etiqueta Larga</th>
                <th style="width: 15%;">Etiqueta Corta</th>
                <th style="width: 10%;">Abrev.</th>
                <th style="width: 10%;">Tipo / Escala</th>
                <th style="width: 15%;">Auditoría</th>
                <th style="width: 10%;">Acciones</th>
            </tr>
        </thead>
        <tbody id="siarhe-metricas-tbody">
            </tbody>
    </table>

    <div class="siarhe-pagination">
        <div id="siarhe-metricas-count" style="font-size: 13px; color: #64748b;"></div>
        <div class="siarhe-page-numbers" id="siarhe-pagination-controls-bottom"></div>
    </div>
</div>

<div id="siarhe-edit-metric-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content" style="max-width: 750px;">
        <h2 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <span class="dashicons dashicons-edit"></span> <span id="modal-metric-title">Editar Métrica</span>
        </h2>
        
        <div class="notice notice-info inline" id="modal-core-notice" style="display:none; margin-bottom:15px;">
            <p>🔒 Esta es una métrica <strong>nativa</strong> del sistema. La clave, el tipo de valor y su par no pueden modificarse para preservar la integridad del mapa principal.</p>
        </div>

        <input type="hidden" id="modal-metric-original-key">
        <input type="hidden" id="modal-metric-is-core">
        <input type="hidden" id="modal-metric-created-by">
        <input type="hidden" id="modal-metric-created-at">

        <table class="form-table">
            <tr>
                <th><label>Clave (Columna CSV)</label></th>
                <td>
                    <input type="text" id="modal-metric-key" class="regular-text" required placeholder="ej. tasa_quirurgicas">
                    <p class="description">Debe coincidir de forma exacta con la cabecera en el archivo origen.</p>
                </td>
            </tr>
            <tr>
                <th><label>Etiqueta Larga</label></th>
                <td>
                    <input type="text" id="modal-metric-full" class="regular-text" style="width:100%;" required placeholder="ej. Tasa de enfermeras quirúrgicas por mil hab.">
                </td>
            </tr>
            
            <tr>
                <th><label>Etiqueta Corta</label></th>
                <td>
                    <input type="text" id="modal-metric-label" class="regular-text" required placeholder="ej. Tasa Quirúr.">
                    <p class="description">Utilizada para las columnas de la tabla y la leyenda visual.</p>
                </td>
            </tr>
            <tr>
                <th><label>Etiqueta Abreviada</label></th>
                <td>
                    <input type="text" id="modal-metric-abrev" class="small-text" style="width:100px;" placeholder="ej. T. Qui.">
                    <p class="description">Para uso en interfaces ultra compactas (móviles).</p>
                </td>
            </tr>
            <tr>
                <th><label>Tipo de Valor</label></th>
                <td>
                    <select id="modal-metric-tipo">
                        <option value="tasa">Tasa (Decimal)</option>
                        <option value="absoluto">Absoluto (Entero)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Visibilidad</label></th>
                <td>
                    <select id="modal-metric-visibilidad">
                        <option value="publico">Público</option>
                        <option value="registrados">Privado</option>
                        <option value="oculto">Oculto</option>
                    </select>
                    <p class="description" id="desc-visibilidad" style="margin-top: 5px;">Controla quién puede ver y seleccionar esta métrica.</p>
                </td>
            </tr>
            <tr>
                <th><label>Clave Relacionada (Par)</label></th>
                <td>
                    <input type="text" id="modal-metric-pair" class="regular-text" required placeholder="ej. enfermeras_quirurgicas">
                </td>
            </tr>
            
            <tr style="border-top: 1px dashed #ccc;">
                <th><label>Escala de Mapa por Defecto</label></th>
                <td>
                    <select id="modal-metric-scale-type" style="width: 100%;">
                        <option value="cuartiles">Degradado por Colores (Cuartiles autocalculados)</option>
                        <option value="rangos">Rangos (Personalizados)</option>
                        <option value="monocromatico">Escala Monocromática</option>
                    </select>
                    <p class="description">Esta es la vista inicial que tomará el mapa al cargar esta métrica.</p>
                </td>
            </tr>

            <tr>
                <th><label>Opciones Avanzadas</label></th>
                <td>
                    <fieldset>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" id="modal-enable-custom-ranges"> Activar configuración de cortes de rango manuales
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" id="modal-enable-custom-colors"> Activar colores personalizados exclusivos para esta métrica
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr id="modal-metric-ranges-row" style="display: none;">
                <th><label>Valores de Corte</label></th>
                <td>
                    <p class="description" style="margin-top:0; margin-bottom:10px;">
                        Comodines autocalculables: <code>Vmin</code>, <code>Q1</code>, <code>Q2</code>, <code>Q3</code>, <code>Vmax</code> o ingresa valores numéricos fijos.
                    </p>
                    
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="width:60px; font-weight:bold; font-size:11px; color:#2271b1;">Rango 1:</span>
                            <span style="font-size:12px; color:#646970;">De</span>
                            <input type="text" id="modal-r1-min" class="small-text" placeholder="Vmin" style="width:70px; text-align:center;">
                            <span style="font-size:12px; color:#646970;">a</span>
                            <select id="modal-r1-op"><option value="<">&lt;</option><option value="<=">&lt;=</option><option value="=">=</option></select>
                            <input type="text" id="modal-r1-max" class="small-text" placeholder="Q1" style="width:70px; text-align:center;">
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="width:60px; font-weight:bold; font-size:11px; color:#2271b1;">Rango 2:</span>
                            <span style="font-size:12px; color:#646970;">De</span>
                            <input type="text" id="modal-r2-min" class="small-text" placeholder="Q1" style="width:70px; text-align:center;">
                            <span style="font-size:12px; color:#646970;">a</span>
                            <select id="modal-r2-op"><option value="<">&lt;</option><option value="<=">&lt;=</option><option value="=">=</option></select>
                            <input type="text" id="modal-r2-max" class="small-text" placeholder="Q2" style="width:70px; text-align:center;">
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="width:60px; font-weight:bold; font-size:11px; color:#2271b1;">Rango 3:</span>
                            <span style="font-size:12px; color:#646970;">De</span>
                            <input type="text" id="modal-r3-min" class="small-text" placeholder="Q2" style="width:70px; text-align:center;">
                            <span style="font-size:12px; color:#646970;">a</span>
                            <select id="modal-r3-op"><option value="<">&lt;</option><option value="<=">&lt;=</option><option value="=">=</option></select>
                            <input type="text" id="modal-r3-max" class="small-text" placeholder="Q3" style="width:70px; text-align:center;">
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="width:60px; font-weight:bold; font-size:11px; color:#2271b1;">Rango 4:</span>
                            <span style="font-size:12px; color:#646970;">De</span>
                            <input type="text" id="modal-r4-min" class="small-text" placeholder="Q3" style="width:70px; text-align:center;">
                            <span style="font-size:12px; color:#646970;">a</span>
                            <select id="modal-r4-op"><option value="<">&lt;</option><option value="<=">&lt;=</option><option value="=">=</option></select>
                            <input type="text" id="modal-r4-max" class="small-text" placeholder="Vmax" style="width:70px; text-align:center;">
                        </div>
                    </div>
                </td>
            </tr>

            <tr id="modal-metric-colors-row" style="display: none;">
                <th><label>Colores de Nivel</label></th>
                <td>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="width:60px; font-weight:bold; font-size:11px; color:#2271b1;">Rango 1:</span>
                            <input type="text" id="modal-r1-color" class="siarhe-color-field-modal" value="">
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="width:60px; font-weight:bold; font-size:11px; color:#2271b1;">Rango 2:</span>
                            <input type="text" id="modal-r2-color" class="siarhe-color-field-modal" value="">
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="width:60px; font-weight:bold; font-size:11px; color:#2271b1;">Rango 3:</span>
                            <input type="text" id="modal-r3-color" class="siarhe-color-field-modal" value="">
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="width:60px; font-weight:bold; font-size:11px; color:#2271b1;">Rango 4:</span>
                            <input type="text" id="modal-r4-color" class="siarhe-color-field-modal" value="">
                        </div>
                    </div>
                </td>
            </tr>

        </table>
        <div style="text-align:right; margin-top:20px; border-top: 1px solid #eee; padding-top: 15px;">
            <button type="button" class="button button-secondary" id="close-metric-modal">Cancelar</button>
            <button type="button" class="button button-primary" id="save-metric-btn">Aplicar Cambios</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    if (typeof jQuery !== 'undefined' && jQuery.fn.wpColorPicker) {
        jQuery('.siarhe-color-field-modal').wpColorPicker();
    }

    const inputJson = document.getElementById('siarhe_metricas_config');
    const defaultJson = document.getElementById('siarhe_default_metricas').value;
    const currentUser = document.getElementById('siarhe_current_user').value;
    const currentTime = document.getElementById('siarhe_current_time').value;
    
    const tbody = document.getElementById('siarhe-metricas-tbody');
    const modal = document.getElementById('siarhe-edit-metric-modal');
    
    const searchInput = document.getElementById('siarhe-search-metricas');
    const itemsPerPageSelect = document.getElementById('siarhe-items-per-page');
    const paginationControlsTop = document.getElementById('siarhe-pagination-controls-top');
    const paginationControlsBottom = document.getElementById('siarhe-pagination-controls-bottom');
    const countDisplay = document.getElementById('siarhe-metricas-count');

    const scaleSelect = document.getElementById('modal-metric-scale-type');
    
    const customRangesCb = document.getElementById('modal-enable-custom-ranges');
    const customColorsCb = document.getElementById('modal-enable-custom-colors');
    const rangesRow = document.getElementById('modal-metric-ranges-row');
    const colorsRow = document.getElementById('modal-metric-colors-row');

    customRangesCb.addEventListener('change', function() {
        rangesRow.style.display = this.checked ? 'table-row' : 'none';
    });

    customColorsCb.addEventListener('change', function() {
        colorsRow.style.display = this.checked ? 'table-row' : 'none';
    });

    const lockedVisibilityKeys = ['tasa_total', 'enfermeras_total', 'poblacion'];

    let metricasObj = {};
    try { metricasObj = JSON.parse(inputJson.value); } catch (e) {}

    let currentPage = 1;
    let itemsPerPage = 25;
    let filteredKeys = [];

    const btnFloatingSave = document.getElementById('btn-floating-save');
    const originalSubmitBtn = document.querySelector('input[type="submit"]#submit');
    
    if(btnFloatingSave && originalSubmitBtn) {
        btnFloatingSave.addEventListener('click', () => {
            originalSubmitBtn.click();
        });
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr.replace(' ', 'T')); 
        if (isNaN(d.getTime())) return dateStr;
        
        const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        const dia = d.getDate();
        const mes = meses[d.getMonth()];
        const anio = d.getFullYear();
        
        let horas = d.getHours();
        let minutos = d.getMinutes().toString().padStart(2, '0');
        let ampm = horas >= 12 ? 'p.m.' : 'a.m.';
        horas = horas % 12;
        horas = horas ? horas : 12; 

        return `${dia} ${mes} ${anio}, ${horas}:${minutos} ${ampm}`;
    }

    function applySearchFilter() {
        const term = searchInput.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        const allKeys = Object.keys(metricasObj);

        filteredKeys = allKeys.filter(key => {
            const item = metricasObj[key];
            const searchableText = `${key} ${item.label} ${item.fullLabel} ${item.abrev} ${item.tipo} ${item.pair || ''}`
                .toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            return searchableText.includes(term);
        });

        currentPage = 1;
        renderTable();
    }

    searchInput.addEventListener('input', applySearchFilter);
    
    itemsPerPageSelect.addEventListener('change', (e) => {
        itemsPerPage = e.target.value === 'all' ? 'all' : parseInt(e.target.value, 10);
        currentPage = 1;
        renderTable();
    });

    function renderTable() {
        tbody.innerHTML = '';
        
        const totalItems = filteredKeys.length;
        let totalPages = 1;
        let keysToRender = filteredKeys;

        if (itemsPerPage !== 'all') {
            totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            keysToRender = filteredKeys.slice(start, end);
        }

        if (keysToRender.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 20px; color:#8c8f94;">No se encontraron métricas con los criterios de búsqueda especificados.</td></tr>`;
        } else {
            keysToRender.forEach(key => {
                const item = metricasObj[key];
                const isCore = item.is_core === true;
                
                const abrev = item.abrev || item.label.substring(0,6) + '.';
                const vis = item.visibilidad || 'publico'; 
                
                const badgeType = item.tipo === 'tasa' ? 'success' : 'neutral';
                const badgeLabel = item.tipo === 'tasa' ? 'Tasa' : 'Absoluto';
                const coreBadge = isCore ? '<span class="siarhe-badge brand" style="margin-left:5px;"><span class="dashicons dashicons-lock" style="font-size:12px;width:12px;height:12px;margin-top:2px;"></span> Nativa</span>' : '';
                
                let scaleBadge = '';
                if (item.scale_type === 'rangos') scaleBadge = '<br><span style="font-size:10px; color:#b45309;">(Rangos)</span>';
                if (item.scale_type === 'monocromatico') scaleBadge = '<br><span style="font-size:10px; color:#0369a1;">(Mono)</span>';
                if (!item.scale_type || item.scale_type === 'cuartiles') scaleBadge = '<br><span style="font-size:10px; color:#8c8f94;">(Cuartiles)</span>';

                const autorOriginal = isCore ? 'Sistema' : (item.created_by || item.last_edited_by || 'Desconocido');
                const fechaOriginal = isCore ? 'Integrado en el código' : (item.created_at || item.last_edited_at || '');
                
                let auditHtml = `
                    <div style="margin-bottom: 8px; line-height: 1.3;">
                        <span style="font-size:10px; font-weight:bold; color:#94a3b8; text-transform:uppercase;">Creado por:</span><br>
                        <span style="font-size:12px; color:#0f172a; font-weight:500;">${autorOriginal}</span><br>
                        <span style="color:#64748b; font-size:11px;">${formatDate(fechaOriginal)}</span>
                    </div>
                `;

                if (!isCore && item.last_edited_by && item.last_edited_at !== item.created_at) {
                    auditHtml += `
                        <div style="line-height: 1.3; border-top: 1px dashed #e2e8f0; padding-top: 6px;">
                            <span style="font-size:10px; font-weight:bold; color:#0ea5e9; text-transform:uppercase;">Última edición:</span><br>
                            <span style="font-size:12px; color:#0f172a; font-weight:500;">${item.last_edited_by}</span><br>
                            <span style="color:#64748b; font-size:11px;">${formatDate(item.last_edited_at)}</span>
                        </div>
                    `;
                }

                const btnDelete = isCore 
                    ? `<span class="dashicons dashicons-lock" style="color:#ccc; margin-left:10px;" title="Las métricas nativas no se pueden eliminar"></span>` 
                    : `<button type="button" class="button button-small button-link-delete btn-delete-metrica" data-key="${key}" title="Eliminar métrica"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>`;
                    
                let eyeIcon = 'dashicons-visibility';
                let eyeColor = '#007cba';
                let eyeTitle = 'Público: Visible para todos';
                if (vis === 'registrados') { eyeIcon = 'dashicons-admin-users'; eyeColor = '#e68a00'; eyeTitle = 'Privado: Solo usuarios registrados'; }
                if (vis === 'oculto') { eyeIcon = 'dashicons-hidden'; eyeColor = '#8c8f94'; eyeTitle = 'Oculto: No se mostrará en frontend'; }

                const visIndicator = `<span class="dashicons ${eyeIcon}" style="color:${eyeColor}; margin-right: 8px; cursor: help;" title="${eyeTitle}"></span>`;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td data-label="Clave (CSV)" data-mobile-role="primary">
                        <strong style="color:#2271b1; font-family:monospace;">${key}</strong> ${coreBadge}
                    </td>
                    <td data-label="Etiqueta Larga">
                        <span class="siarhe-break-text" style="font-size:12px; color:#555;">${item.fullLabel}</span>
                    </td>
                    <td data-label="Etiqueta Corta" data-mobile-role="secondary">
                        <strong>${item.label}</strong>
                    </td>
                    <td data-label="Abrev.">
                        <span style="background:#f0f0f1; padding:2px 5px; border-radius:3px; font-size:11px;">${abrev}</span>
                    </td>
                    <td data-label="Tipo / Escala">
                        <span class="siarhe-badge ${badgeType}">${badgeLabel}</span>
                        ${scaleBadge}
                    </td>
                    <td data-label="Auditoría">
                        ${auditHtml}
                    </td>
                    <td data-label="Acciones">
                        ${visIndicator}
                        <button type="button" class="button button-small btn-edit-metrica" data-key="${key}" title="Modificar parámetros">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        ${btnDelete}
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        updatePaginationUI(totalItems, keysToRender.length, totalPages);
        attachEvents();
    }

    function generatePaginationHTML(totalPages) {
        let html = '';
        if (totalPages <= 1) return html;

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (currentPage <= 2) endPage = Math.min(totalPages, 5);
        if (currentPage >= totalPages - 1) startPage = Math.max(1, totalPages - 4);

        html += `<a href="#" class="siarhe-page-btn ${currentPage === 1 ? 'disabled' : ''}" data-page="prev">« Ant</a>`;

        if (startPage > 1) {
            html += `<a href="#" class="siarhe-page-btn" data-page="1">1</a>`;
            if (startPage > 2) html += `<span style="color:#8c8f94;">...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<a href="#" class="siarhe-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</a>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<span style="color:#8c8f94;">...</span>`;
            html += `<a href="#" class="siarhe-page-btn" data-page="${totalPages}">${totalPages}</a>`;
        }

        html += `<a href="#" class="siarhe-page-btn ${currentPage === totalPages ? 'disabled' : ''}" data-page="next">Sig »</a>`;
        
        return html;
    }

    function handlePaginationClick(e) {
        if (!e.target.classList.contains('siarhe-page-btn')) return;
        e.preventDefault();
        
        if (e.target.classList.contains('disabled')) return;

        const pageTarget = e.target.getAttribute('data-page');
        let totalPages = Math.ceil(filteredKeys.length / itemsPerPage);

        if (pageTarget === 'prev' && currentPage > 1) {
            currentPage--;
        } else if (pageTarget === 'next' && currentPage < totalPages) {
            currentPage++;
        } else if (!isNaN(parseInt(pageTarget))) {
            currentPage = parseInt(pageTarget);
        }

        renderTable();
    }

    function updatePaginationUI(totalItems, currentItemsCount, totalPages) {
        if (totalItems === 0) {
            countDisplay.innerHTML = 'No hay registros para mostrar.';
            paginationControlsTop.innerHTML = '';
            paginationControlsBottom.innerHTML = '';
            return;
        }

        let startRange = 1;
        let endRange = totalItems;

        if (itemsPerPage !== 'all') {
            startRange = ((currentPage - 1) * itemsPerPage) + 1;
            endRange = startRange + currentItemsCount - 1;
        }

        countDisplay.innerHTML = `Mostrando del <strong>${startRange}</strong> al <strong>${endRange}</strong> de <strong>${totalItems}</strong> registros`;

        const phtml = generatePaginationHTML(totalPages);
        paginationControlsTop.innerHTML = phtml;
        paginationControlsBottom.innerHTML = phtml;

        paginationControlsTop.querySelectorAll('a').forEach(a => a.addEventListener('click', handlePaginationClick));
        paginationControlsBottom.querySelectorAll('a').forEach(a => a.addEventListener('click', handlePaginationClick));
    }

    function attachEvents() {
        document.querySelectorAll('#siarhe-metricas-table tbody tr').forEach(row => {
            row.removeEventListener('click', handleRowClick);
            row.addEventListener('click', handleRowClick);
        });

        document.querySelectorAll('.btn-delete-metrica').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const key = this.getAttribute('data-key');
                if (confirm(`¿Proceder con la eliminación de la métrica "${key}"? Esta acción no afectará el archivo CSV pero ocultará la información en el mapa.`)) {
                    delete metricasObj[key];
                    updateHiddenInput();
                    renderTable(); 
                }
            });
        });

        document.querySelectorAll('.btn-edit-metrica').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                openModal(this.getAttribute('data-key'));
            });
        });
    }

    function handleRowClick(e) {
        if (window.innerWidth > 767) return;
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
        this.classList.toggle('is-open');
    }

    function openModal(key = null) {
        const isNew = key === null;
        
        const baseItem = { 
            label: '', fullLabel: '', abrev: '', tipo: 'absoluto', pair: '', visibilidad: 'publico', is_core: false, 
            created_by: currentUser, created_at: currentTime,
            scale_type: 'cuartiles', custom_ranges: false, custom_colors: false,
            r1_min: 'Vmin', r1_op: '<', r1_max: 'Q1', r1_color: '',
            r2_min: 'Q1',   r2_op: '<', r2_max: 'Q2', r2_color: '',
            r3_min: 'Q2',   r3_op: '<', r3_max: 'Q3', r3_color: '',
            r4_min: 'Q3',   r4_op: '<=', r4_max: 'Vmax', r4_color: ''
        };

        const item = isNew ? baseItem : Object.assign({}, baseItem, metricasObj[key]);

        document.getElementById('modal-metric-title').textContent = isNew ? 'Añadir Nueva Métrica' : 'Editar Propiedades';
        document.getElementById('modal-metric-original-key').value = isNew ? '' : key;
        document.getElementById('modal-metric-is-core').value = item.is_core ? '1' : '0';
        
        document.getElementById('modal-metric-created-by').value = item.created_by || item.last_edited_by || currentUser;
        document.getElementById('modal-metric-created-at').value = item.created_at || item.last_edited_at || currentTime;

        const keyInput = document.getElementById('modal-metric-key');
        const pairInput = document.getElementById('modal-metric-pair');
        const typeInput = document.getElementById('modal-metric-tipo');
        const visInput = document.getElementById('modal-metric-visibilidad');
        const notice = document.getElementById('modal-core-notice');
        const descVis = document.getElementById('desc-visibilidad');

        keyInput.value = isNew ? '' : key;
        document.getElementById('modal-metric-label').value = item.label || '';
        document.getElementById('modal-metric-full').value = item.fullLabel || '';
        document.getElementById('modal-metric-abrev').value = item.abrev || '';
        typeInput.value = item.tipo;
        pairInput.value = item.pair;
        visInput.value = item.visibilidad || 'publico';

        scaleSelect.value = item.scale_type || 'cuartiles';

        customRangesCb.checked = item.custom_ranges === true;
        customColorsCb.checked = item.custom_colors === true;
        customRangesCb.dispatchEvent(new Event('change'));
        customColorsCb.dispatchEvent(new Event('change'));

        const c1 = item.r1_color || ''; const c2 = item.r2_color || ''; const c3 = item.r3_color || ''; const c4 = item.r4_color || '';
        
        document.getElementById('modal-r1-min').value = item.r1_min; document.getElementById('modal-r1-op').value = item.r1_op; document.getElementById('modal-r1-max').value = item.r1_max;
        document.getElementById('modal-r2-min').value = item.r2_min; document.getElementById('modal-r2-op').value = item.r2_op; document.getElementById('modal-r2-max').value = item.r2_max;
        document.getElementById('modal-r3-min').value = item.r3_min; document.getElementById('modal-r3-op').value = item.r3_op; document.getElementById('modal-r3-max').value = item.r3_max;
        document.getElementById('modal-r4-min').value = item.r4_min; document.getElementById('modal-r4-op').value = item.r4_op || '<='; document.getElementById('modal-r4-max').value = item.r4_max;

        if (typeof jQuery !== 'undefined' && jQuery.fn.wpColorPicker) {
            jQuery('#modal-r1-color').wpColorPicker('color', c1);
            jQuery('#modal-r2-color').wpColorPicker('color', c2);
            jQuery('#modal-r3-color').wpColorPicker('color', c3);
            jQuery('#modal-r4-color').wpColorPicker('color', c4);
        }

        if (item.is_core) {
            keyInput.readOnly = true; keyInput.style.background = '#f0f0f1';
            pairInput.readOnly = true; pairInput.style.background = '#f0f0f1';
            typeInput.disabled = true; typeInput.style.background = '#f0f0f1';
            notice.style.display = 'block';
        } else {
            keyInput.readOnly = false; keyInput.style.background = '#fff';
            pairInput.readOnly = false; pairInput.style.background = '#fff';
            typeInput.disabled = false; typeInput.style.background = '#fff';
            notice.style.display = 'none';
        }

        if (!isNew && lockedVisibilityKeys.includes(key)) {
            visInput.value = 'publico'; visInput.disabled = true; visInput.style.background = '#f0f0f1';
            descVis.innerHTML = '<span style="color:#d63638;">🔒 La visibilidad de esta métrica crítica no puede ser alterada.</span>';
        } else {
            visInput.disabled = false; visInput.style.background = '#fff';
            descVis.innerHTML = 'Controla quién puede ver y seleccionar esta métrica.';
        }

        modal.style.display = 'block';
    }

    document.getElementById('close-metric-modal').addEventListener('click', () => { modal.style.display = 'none'; });
    window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

    document.getElementById('btn-add-metrica').addEventListener('click', () => openModal(null));

    document.getElementById('save-metric-btn').addEventListener('click', () => {
        const originalKey = document.getElementById('modal-metric-original-key').value;
        const newKey = document.getElementById('modal-metric-key').value.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
        const newTipo = document.getElementById('modal-metric-tipo').value;
        const newPair = document.getElementById('modal-metric-pair').value.trim();
        const isCore = document.getElementById('modal-metric-is-core').value === '1';

        if (!newKey) { alert('La clave identificadora es obligatoria.'); return; }

        if (!originalKey && metricasObj.hasOwnProperty(newKey)) {
            alert(`⚠️ ALERTA DE SEGURIDAD: La clave "${newKey}" ya existe en el sistema.`); return; 
        }
        if (originalKey && originalKey !== newKey && metricasObj.hasOwnProperty(newKey)) {
            alert(`⚠️ ALERTA DE SEGURIDAD: No puedes renombrar la clave a "${newKey}" porque ya está en uso.`); return; 
        }

        let pairConflict = false;
        let conflictKey = '';
        for (const [key, item] of Object.entries(metricasObj)) {
            if (key === originalKey) continue; 
            if (item.pair === newPair && item.tipo === newTipo) {
                pairConflict = true; conflictKey = key; break;
            }
        }

        if (pairConflict) {
            alert(`⚠️ ALERTA ESTRUCTURAL: Ya existe una métrica ("${conflictKey}") de tipo "${newTipo}" asociada a la clave relacionada "${newPair}".\n\nEl sistema requiere que cada par tenga máximo un valor Absoluto y una Tasa para mantener la integridad de las columnas en la tabla de datos.`);
            return; 
        }

        if (!isCore && originalKey && originalKey !== newKey) {
            delete metricasObj[originalKey];
        }

        let finalVis = document.getElementById('modal-metric-visibilidad').value;
        if (lockedVisibilityKeys.includes(newKey)) finalVis = 'publico';

        const originalCreator = document.getElementById('modal-metric-created-by').value;
        const originalCreatedAt = document.getElementById('modal-metric-created-at').value;

        let v_r1_min = document.getElementById('modal-r1-min').value.trim();
        let v_r4_max = document.getElementById('modal-r4-max').value.trim();
        if (v_r1_min === '') v_r1_min = 'Vmin';
        if (v_r4_max === '') v_r4_max = 'Vmax';

        metricasObj[newKey] = {
            label: document.getElementById('modal-metric-label').value.trim(),
            fullLabel: document.getElementById('modal-metric-full').value.trim(),
            abrev: document.getElementById('modal-metric-abrev').value.trim(), 
            tipo: newTipo,
            pair: newPair,
            visibilidad: finalVis, 
            is_core: isCore,
            created_by: originalCreator,
            created_at: originalCreatedAt,
            last_edited_by: currentUser,
            last_edited_at: currentTime,
            scale_type: scaleSelect.value,
            custom_ranges: customRangesCb.checked,
            custom_colors: customColorsCb.checked,
            r1_color: document.getElementById('modal-r1-color').value.trim(), r1_min: v_r1_min, r1_op: document.getElementById('modal-r1-op').value, r1_max: document.getElementById('modal-r1-max').value.trim(),
            r2_color: document.getElementById('modal-r2-color').value.trim(), r2_min: document.getElementById('modal-r2-min').value.trim(), r2_op: document.getElementById('modal-r2-op').value, r2_max: document.getElementById('modal-r2-max').value.trim(),
            r3_color: document.getElementById('modal-r3-color').value.trim(), r3_min: document.getElementById('modal-r3-min').value.trim(), r3_op: document.getElementById('modal-r3-op').value, r3_max: document.getElementById('modal-r3-max').value.trim(),
            r4_color: document.getElementById('modal-r4-color').value.trim(), r4_min: document.getElementById('modal-r4-min').value.trim(), r4_op: document.getElementById('modal-r4-op').value, r4_max: v_r4_max
        };

        updateHiddenInput();
        renderTable(); 
        
        modal.style.display = 'none';
        
        if (btnFloatingSave) {
            btnFloatingSave.style.display = 'block';
            setTimeout(() => { btnFloatingSave.style.transform = 'scale(1.05)'; }, 50);
            setTimeout(() => { btnFloatingSave.style.transform = 'scale(1)'; }, 350);
        }
        
        if (!document.getElementById('siarhe-save-notice')) {
            document.querySelector('.wp-header-end').insertAdjacentHTML('afterend', '<div id="siarhe-save-notice" class="notice notice-warning"><p>⚠️ <strong>Atención:</strong> Existen cambios pendientes en la memoria temporal. Es necesario hacer clic en <strong>"Guardar Configuración"</strong> para aplicarlos al sistema.</p></div>');
        }
    });

    document.getElementById('btn-reset-metricas').addEventListener('click', function() {
        if (confirm('⚠️ PRECAUCIÓN: Esta acción purgará cualquier métrica personalizada e inicializará el catálogo a sus valores de fábrica. El proceso es irreversible. ¿Confirmar operación?')) {
            metricasObj = JSON.parse(defaultJson);
            updateHiddenInput();
            this.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> Procesando...';
            this.disabled = true;
            const form = document.querySelector('form[action="options.php"]');
            if (form) HTMLFormElement.prototype.submit.call(form);
        }
    });

    function updateHiddenInput() {
        inputJson.value = JSON.stringify(metricasObj);
        inputJson.dispatchEvent(new Event('change', { bubbles: true }));
        if (originalSubmitBtn) {
            originalSubmitBtn.removeAttribute('disabled');
            originalSubmitBtn.classList.remove('disabled');
        }
    }

    applySearchFilter();
});
</script>