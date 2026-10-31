<?php // /admin/partials/settings-tabs/tab-metricas.php
if ( ! defined( 'ABSPATH' ) ) exit;

// Esta pestana guarda al instante cada cambio: no necesita el boton Guardar inferior.
$siarhe_autosave = true;

$current_user = wp_get_current_user();
$editor_name = $current_user->display_name ?: $current_user->user_login;
$current_time = current_time('mysql');

$defaults = siarhe_get_default_metricas();

$defaults_json = wp_json_encode($defaults);

$metricas_json = get_option( 'siarhe_metricas_config', '' );
if ( empty($metricas_json) ) {
    $metricas_json = $defaults_json;
} else {
    $metricas_json = wp_unslash($metricas_json);
}
?>


<div class="siarhe-card siarhe-upload-card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 class="siarhe-mt-0">📊 Gestor de Métricas e Indicadores (CSV)<?php Siarhe_Admin::help('Define que columnas del CSV son indicadores, como se llaman y como se colorean en el mapa. No modifica los archivos CSV.'); ?></h2>
            <p class="siarhe-mb-0">Configura los indicadores que se mostrarán en el mapa y la tabla. Las variables <strong>nativas</strong> están protegidas, pero puedes editar sus descripciones o añadir nuevas variables siempre y cuando existan en tu archivo CSV.</p>
        </div>
        <div class="siarhe-flex-gap">
            <button type="button" class="siarhe-btn-icon button button-secondary" id="btn-add-metrica">
                <span class="dashicons dashicons-plus-alt2"></span> Añadir Nueva
            </button>
            <button type="button" class="siarhe-btn-danger-outline siarhe-btn-icon button button-link-delete" id="btn-reset-metricas">
                <span class="dashicons dashicons-undo"></span> Restaurar Iniciales
            </button>
        </div>
    </div>

    <input type="hidden" name="siarhe_metricas_config" id="siarhe_metricas_config" value="<?php echo esc_attr($metricas_json); ?>">
    <input type="hidden" id="siarhe_default_metricas" value="<?php echo esc_attr($defaults_json); ?>">
    <input type="hidden" id="siarhe_current_user" value="<?php echo esc_attr($editor_name); ?>">
    <input type="hidden" id="siarhe_current_time" value="<?php echo esc_attr($current_time); ?>">
</div>

<div class="siarhe-card siarhe-card--flush">
    <h2 class="siarhe-card__title">Métricas registradas<?php Siarhe_Admin::help( 'Columnas del CSV que el mapa y la tabla pueden mostrar como indicador. Las nativas (con candado) no se pueden eliminar. Puedes buscar, ordenar y editar cada métrica con el botón de acciones.' ); ?></h2>
    <div class="siarhe-toolbar">
        <div class="siarhe-table-controls">
            <label>
                Mostrar 
                <select id="siarhe-items-per-page" class="siarhe-select-sm">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="all">Todos</option>
                </select> 
                registros
            </label>
        </div>

        <div class="siarhe-pagination--bare siarhe-pagination">
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
                <th style="width: 15%;" data-sort-key="key" data-sort="text">Clave (CSV)</th>
                <th style="width: 25%;" data-sort-key="fullLabel" data-sort="text">Etiqueta Larga</th>
                <th style="width: 15%;" data-sort-key="label" data-sort="text">Etiqueta Corta</th>
                <th style="width: 10%;" data-sort-key="abrev" data-sort="text">Abrev.</th>
                <th style="width: 10%;" data-sort-key="tipo" data-sort="text">Tipo / Escala</th>
                <th style="width: 15%;" data-sort-key="audit" data-sort="num">Auditoría</th>
                <th style="width: 10%;">Acciones</th>
            </tr>
        </thead>
        <tbody id="siarhe-metricas-tbody">
            </tbody>
    </table>

    <div class="siarhe-pagination">
        <div id="siarhe-metricas-count" class="siarhe-count"></div>
        <div class="siarhe-page-numbers" id="siarhe-pagination-controls-bottom"></div>
    </div>
</div>

<div id="siarhe-edit-metric-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content" style="max-width: 750px;">
        <h2 class="siarhe-modal-title">
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
                <th><label>Clave (Columna CSV)</label><?php Siarhe_Admin::help('Nombre exacto de la columna del CSV que contiene esta metrica, respetando mayusculas y acentos.'); ?></th>
                <td>
                    <input type="text" id="modal-metric-key" class="regular-text" required placeholder="ej. tasa_quirurgicas">
                    <p class="description">Debe coincidir de forma exacta con la cabecera en el archivo origen.</p>
                </td>
            </tr>
            <tr>
                <th><label>Etiqueta Larga</label><?php Siarhe_Admin::help('Nombre completo de la metrica, usado en tooltips y descripciones.'); ?></th>
                <td>
                    <input type="text" id="modal-metric-full" class="siarhe-w-full regular-text" required placeholder="ej. Tasa de enfermeras quirúrgicas por mil hab.">
                </td>
            </tr>
            
            <tr>
                <th><label>Etiqueta Corta</label><?php Siarhe_Admin::help('Nombre breve para encabezados de tabla y leyendas del mapa.'); ?></th>
                <td>
                    <input type="text" id="modal-metric-label" class="regular-text" required placeholder="ej. Tasa Quirúr.">
                    <p class="description">Utilizada para las columnas de la tabla y la leyenda visual.</p>
                </td>
            </tr>
            <tr>
                <th><label>Etiqueta Abreviada</label><?php Siarhe_Admin::help('Version minima para pantallas muy pequenas (movil).'); ?></th>
                <td>
                    <input type="text" id="modal-metric-abrev" class="small-text" style="width:100px;" placeholder="ej. T. Qui.">
                    <p class="description">Para uso en interfaces ultra compactas (móviles).</p>
                </td>
            </tr>
            <tr>
                <th><label>Tipo de Valor</label><?php Siarhe_Admin::help('Tasa: numero decimal (por ejemplo por 1,000 habitantes). Absoluto: conteo entero. Cambia el formato con que se muestra.'); ?></th>
                <td>
                    <select id="modal-metric-tipo">
                        <option value="tasa">Tasa (Decimal)</option>
                        <option value="absoluto">Absoluto (Entero)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Visibilidad</label><?php Siarhe_Admin::help('Publico: lo ve cualquier visitante. Privado: solo usuarios con sesion iniciada. Oculto: no aparece en el sitio.'); ?></th>
                <td>
                    <select id="modal-metric-visibilidad">
                        <option value="publico">Público</option>
                        <option value="registrados">Privado</option>
                        <option value="oculto">Oculto</option>
                    </select>
                    <p class="siarhe-mt-1 description" id="desc-visibilidad">Controla quién puede ver y seleccionar esta métrica.</p>
                </td>
            </tr>
            <tr>
                <th><label>Clave Relacionada (Par)</label><?php Siarhe_Admin::help('Clave de la metrica complementaria (la tasa si esta es el absoluto, o al reves). Permite alternar entre ambas en el selector.'); ?></th>
                <td>
                    <input type="text" id="modal-metric-pair" class="regular-text" required placeholder="ej. enfermeras_quirurgicas">
                </td>
            </tr>
            
            <tr class="siarhe-row-divider">
                <th><label>Escala de Mapa por Defecto</label><?php Siarhe_Admin::help('Forma de colorear el mapa al cargar esta metrica: cuartiles automaticos, rangos propios o un solo color en degradado.'); ?></th>
                <td>
                    <select id="modal-metric-scale-type" class="siarhe-w-full">
                        <option value="cuartiles">Degradado por Colores (Cuartiles autocalculados)</option>
                        <option value="rangos">Rangos (Personalizados)</option>
                        <option value="monocromatico">Escala Monocromática</option>
                    </select>
                    <p class="description">Esta es la vista inicial que tomará el mapa al cargar esta métrica.</p>
                </td>
            </tr>

            <tr>
                <th><label>Opciones Avanzadas</label><?php Siarhe_Admin::help('Permite fijar cortes de rango manuales y colores exclusivos para esta metrica, en lugar de los generales de Personalizar.'); ?></th>
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
                <th><label>Valores de Corte</label><?php Siarhe_Admin::help('Limites de cada rango. Usa comodines (Vmin, Q1, Q2, Q3, Vmax) que se calculan con los datos, o numeros fijos.'); ?></th>
                <td>
                    <p class="siarhe-mt-0 siarhe-mb-2 description">
                        Comodines autocalculables: <code>Vmin</code>, <code>Q1</code>, <code>Q2</code>, <code>Q3</code>, <code>Vmax</code> o ingresa valores numéricos fijos.
                    </p>
                    
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <div class="siarhe-inline-row">
                            <span class="siarhe-range-label">Rango 1:</span>
                            <span class="siarhe-text-sm siarhe-text-muted">De</span>
                            <input type="text" id="modal-r1-min" class="siarhe-input-xs small-text" placeholder="Vmin">
                            <span class="siarhe-text-sm siarhe-text-muted">a</span>
                            <select id="modal-r1-op"><option value="<">&lt;</option><option value="<=">&lt;=</option><option value="=">=</option></select>
                            <input type="text" id="modal-r1-max" class="siarhe-input-xs small-text" placeholder="Q1">
                        </div>
                        <div class="siarhe-inline-row">
                            <span class="siarhe-range-label">Rango 2:</span>
                            <span class="siarhe-text-sm siarhe-text-muted">De</span>
                            <input type="text" id="modal-r2-min" class="siarhe-input-xs small-text" placeholder="Q1">
                            <span class="siarhe-text-sm siarhe-text-muted">a</span>
                            <select id="modal-r2-op"><option value="<">&lt;</option><option value="<=">&lt;=</option><option value="=">=</option></select>
                            <input type="text" id="modal-r2-max" class="siarhe-input-xs small-text" placeholder="Q2">
                        </div>
                        <div class="siarhe-inline-row">
                            <span class="siarhe-range-label">Rango 3:</span>
                            <span class="siarhe-text-sm siarhe-text-muted">De</span>
                            <input type="text" id="modal-r3-min" class="siarhe-input-xs small-text" placeholder="Q2">
                            <span class="siarhe-text-sm siarhe-text-muted">a</span>
                            <select id="modal-r3-op"><option value="<">&lt;</option><option value="<=">&lt;=</option><option value="=">=</option></select>
                            <input type="text" id="modal-r3-max" class="siarhe-input-xs small-text" placeholder="Q3">
                        </div>
                        <div class="siarhe-inline-row">
                            <span class="siarhe-range-label">Rango 4:</span>
                            <span class="siarhe-text-sm siarhe-text-muted">De</span>
                            <input type="text" id="modal-r4-min" class="siarhe-input-xs small-text" placeholder="Q3">
                            <span class="siarhe-text-sm siarhe-text-muted">a</span>
                            <select id="modal-r4-op"><option value="<">&lt;</option><option value="<=">&lt;=</option><option value="=">=</option></select>
                            <input type="text" id="modal-r4-max" class="siarhe-input-xs small-text" placeholder="Vmax">
                        </div>
                    </div>
                </td>
            </tr>

            <tr id="modal-metric-colors-row" style="display: none;">
                <th><label>Colores de Nivel</label><?php Siarhe_Admin::help('Color de cada uno de los 4 rangos, solo para esta metrica.'); ?></th>
                <td>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div class="siarhe-inline-row siarhe-inline-row--lg">
                            <span class="siarhe-range-label">Rango 1:</span>
                            <input type="text" id="modal-r1-color" class="siarhe-color-field-modal" value="">
                        </div>
                        <div class="siarhe-inline-row siarhe-inline-row--lg">
                            <span class="siarhe-range-label">Rango 2:</span>
                            <input type="text" id="modal-r2-color" class="siarhe-color-field-modal" value="">
                        </div>
                        <div class="siarhe-inline-row siarhe-inline-row--lg">
                            <span class="siarhe-range-label">Rango 3:</span>
                            <input type="text" id="modal-r3-color" class="siarhe-color-field-modal" value="">
                        </div>
                        <div class="siarhe-inline-row siarhe-inline-row--lg">
                            <span class="siarhe-range-label">Rango 4:</span>
                            <input type="text" id="modal-r4-color" class="siarhe-color-field-modal" value="">
                        </div>
                    </div>
                </td>
            </tr>

        </table>
        <div class="siarhe-modal-footer">
            <button type="button" class="button button-secondary" id="close-metric-modal">Cancelar</button>
            <button type="button" class="button button-primary" id="save-metric-btn">Guardar Cambios</button>
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

    const sorter = window.SiarheAdmin.initSortHeaders(document.getElementById('siarhe-metricas-table'), () => applySearchFilter());

    function applySearchFilter() {
        const term = searchInput.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        const allKeys = Object.keys(metricasObj);

        filteredKeys = allKeys.filter(key => {
            const item = metricasObj[key];
            const searchableText = `${key} ${item.label} ${item.fullLabel} ${item.abrev} ${item.tipo} ${item.pair || ''}`
                .toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            return searchableText.includes(term);
        });
        filteredKeys = sorter.apply(filteredKeys, (k, col) => {
            if (col === 'key') return k;
            const it = metricasObj[k];
            if (col === 'audit') { if (it.is_core) return 0; const t = Date.parse(String(it.last_edited_at || it.created_at || '').replace(' ', 'T')); return isNaN(t) ? 0 : t; }
            return it[col] || '';
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
            tbody.innerHTML = `<tr><td colspan="7" class="siarhe-empty-cell">No se encontraron métricas con los criterios de búsqueda especificados.</td></tr>`;
        } else {
            keysToRender.forEach(key => {
                const item = metricasObj[key];
                const isCore = item.is_core === true;
                
                const abrev = item.abrev || item.label.substring(0,6) + '.';
                const vis = item.visibilidad || 'publico'; 
                
                const badgeType = item.tipo === 'tasa' ? 'success' : 'neutral';
                const badgeLabel = item.tipo === 'tasa' ? 'Tasa' : 'Absoluto';
                const coreBadge = isCore ? '<span class="siarhe-ml-1 siarhe-badge brand"><span class="dashicons dashicons-lock" style="font-size:12px;width:12px;height:12px;margin-top:2px;"></span> Nativa</span>' : '';
                
                let scaleBadge = '';
                if (item.scale_type === 'rangos') scaleBadge = '<br><span style="font-size:10px; color:#b45309;">(Rangos)</span>';
                if (item.scale_type === 'monocromatico') scaleBadge = '<br><span style="font-size:10px; color:#0369a1;">(Mono)</span>';
                if (!item.scale_type || item.scale_type === 'cuartiles') scaleBadge = '<br><span style="font-size:10px; color:#8c8f94;">(Cuartiles)</span>';

                const autorOriginal = isCore ? 'Sistema' : (item.created_by || item.last_edited_by || 'Desconocido');
                const fechaOriginal = isCore ? 'Integrado en el código' : (item.created_at || item.last_edited_at || '');
                
                let auditHtml = `
                    <div class="siarhe-audit">
                        <span class="siarhe-audit__label">Creado por:</span><br>
                        <span class="siarhe-audit__who">${autorOriginal}</span><br>
                        <span class="siarhe-audit__date">${window.SiarheAdmin.formatDate(fechaOriginal)}</span>
                    </div>
                `;

                if (!isCore && item.last_edited_by && item.last_edited_at !== item.created_at) {
                    auditHtml += `
                        <div class="siarhe-audit">
                            <span class="siarhe-audit__label siarhe-audit__label--edit">Última edición:</span><br>
                            <span class="siarhe-audit__who">${item.last_edited_by}</span><br>
                            <span class="siarhe-audit__date">${window.SiarheAdmin.formatDate(item.last_edited_at)}</span>
                        </div>
                    `;
                }

                const btnDelete = isCore 
                    ? `<span class="dashicons dashicons-lock siarhe-action-icon siarhe-action-icon--lock" tabindex="0" data-help="Variable nativa del sistema: no se puede eliminar. Si puedes editar sus etiquetas y parametros."></span>` 
                    : `<button type="button" class="button button-small button-danger btn-delete-metrica" data-key="${key}" title="Eliminar métrica"><span class="dashicons dashicons-trash"></span></button>`;
                    
                let eyeIcon = 'dashicons-visibility';
                let eyeColor = '#007cba';
                let eyeTitle = 'Publico: lo ve cualquier visitante del sitio.';
                if (vis === 'registrados') { eyeIcon = 'dashicons-admin-users'; eyeColor = '#e68a00'; eyeTitle = 'Privado: solo lo ven los usuarios con sesion iniciada.'; }
                if (vis === 'oculto') { eyeIcon = 'dashicons-hidden'; eyeColor = '#8c8f94'; eyeTitle = 'Oculto: no se muestra en el sitio publico.'; }

                const visIndicator = `<span class="dashicons ${eyeIcon} siarhe-action-icon" style="color:${eyeColor};" tabindex="0" data-help="${eyeTitle}"></span>`;

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
                        <div class="siarhe-row-actions">
                        ${visIndicator}
                        <button type="button" class="button button-small btn-edit-metrica" data-key="${key}" title="Modificar parámetros">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        ${btnDelete}
                        </div>
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
            if (startPage > 2) html += `<span class="siarhe-text-faint">...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<a href="#" class="siarhe-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</a>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<span class="siarhe-text-faint">...</span>`;
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
        if(window.SiarheAdmin) window.SiarheAdmin.initMobileTables();

        document.querySelectorAll('.btn-delete-metrica').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const key = this.getAttribute('data-key');
                window.SiarheAdmin.confirm(`¿Proceder con la eliminación de la métrica "${key}"? Esta acción no afectará el archivo CSV pero ocultará la información en el mapa.`, { okText: 'Eliminar' }).then(ok => {
 if (!ok) return;
                    delete metricasObj[key];
                    updateHiddenInput();
                    // 🌟 Refiltrar todo desde cero para que el DOM se olvide de la llave eliminada
                    applySearchFilter(); 
                    
                    window.SiarheAdmin.submitSettingsForm();
                });
            });
        });

        document.querySelectorAll('.btn-edit-metrica').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                openModal(this.getAttribute('data-key'));
            });
        });
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
            descVis.innerHTML = '<span class="siarhe-text-danger">🔒 La visibilidad de esta métrica crítica no puede ser alterada.</span>';
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
        // 🌟 CORRECCIÓN: Permite letras mayúsculas (a-zA-Z)
        const newKey = document.getElementById('modal-metric-key').value.trim().replace(/[^a-zA-Z0-9_]/g, '_');
        const newTipo = document.getElementById('modal-metric-tipo').value;
        const newPair = document.getElementById('modal-metric-pair').value.trim();
        const isCore = document.getElementById('modal-metric-is-core').value === '1';

        if (!newKey) { window.SiarheAdmin.toast('La clave identificadora es obligatoria.', 'error'); return; }

        if (!originalKey && metricasObj.hasOwnProperty(newKey)) {
            window.SiarheAdmin.toast(`⚠️ ALERTA DE SEGURIDAD: La clave "${newKey}" ya existe en el sistema.`, 'error'); return; 
        }
        if (originalKey && originalKey !== newKey && metricasObj.hasOwnProperty(newKey)) {
            window.SiarheAdmin.toast(`⚠️ ALERTA DE SEGURIDAD: No puedes renombrar la clave a "${newKey}" porque ya está en uso.`, 'error'); return; 
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
            window.SiarheAdmin.toast(`⚠️ ALERTA ESTRUCTURAL: Ya existe una métrica ("${conflictKey}") de tipo "${newTipo}" asociada a la clave relacionada "${newPair}".\n\nEl sistema requiere que cada par tenga máximo un valor Absoluto y una Tasa para mantener la integridad de las columnas en la tabla de datos.`, 'error');
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
        applySearchFilter(); 
        
        modal.style.display = 'none';
        
        window.SiarheAdmin.submitSettingsForm();
    });

    document.getElementById('btn-reset-metricas').addEventListener('click', function() {
        window.SiarheAdmin.confirm('⚠️ PRECAUCIÓN: Esta acción purgará cualquier métrica personalizada e inicializará el catálogo a sus valores de fábrica. El proceso es irreversible. ¿Confirmar operación?', { okText: 'Restaurar' }).then(ok => {
 if (!ok) return;
            metricasObj = JSON.parse(defaultJson);
            updateHiddenInput();
            this.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> Procesando...';
            this.disabled = true;
            const form = document.querySelector('form[action="options.php"]');
            if (form) HTMLFormElement.prototype.submit.call(form);
        });
    });

    function updateHiddenInput() {
        inputJson.value = JSON.stringify(metricasObj);
        inputJson.dispatchEvent(new Event('change', { bubbles: true }));
        
        // 🌟 WP DIRTY FIX: Forzamos el formulario a reconocer el cambio
        if (typeof jQuery !== 'undefined') {
            jQuery('#submit').prop('disabled', false).removeClass('disabled');
            jQuery('form[action="options.php"]').trigger('change'); 
        }
    }

    applySearchFilter();
});
</script>