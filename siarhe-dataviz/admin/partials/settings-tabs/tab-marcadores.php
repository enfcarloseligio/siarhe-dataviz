<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener usuario actual y hora para la auditoría
$current_user = wp_get_current_user();
$editor_name = $current_user->display_name ?: $current_user->user_login;
$current_time = current_time('mysql');

// 2. Leer los archivos CSV disponibles en la carpeta de marcadores
$upload_dir = wp_upload_dir();
$markers_dir = $upload_dir['basedir'] . '/siarhe-data/markers/';
$available_csvs = [];

if ( file_exists($markers_dir) && is_dir($markers_dir) ) {
    $files = scandir($markers_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'csv') {
            $available_csvs[] = $file;
        }
    }
}

// 3. Definir el diccionario base (Los 6 marcadores actuales para no romper el mapa)
$defaults = [
    'CATETER' => ['label' => 'Clínicas de catéteres', 'tipo' => 'posicion', 'archivo' => 'clinicas-cateteres.csv', 'filtro_col' => '', 'filtro_val' => '', 'visibilidad' => 'publico', 'is_core' => true],
    'HERIDAS' => ['label' => 'Clínicas de heridas', 'tipo' => 'posicion', 'archivo' => 'clinicas-heridas.csv', 'filtro_col' => '', 'filtro_val' => '', 'visibilidad' => 'publico', 'is_core' => true],
    'ESTAB_1' => ['label' => 'Establecimientos (1er Nivel)', 'tipo' => 'posicion', 'archivo' => 'establecimientos-salud.csv', 'filtro_col' => 'cve_n_atencion', 'filtro_val' => '1', 'visibilidad' => 'publico', 'is_core' => true],
    'ESTAB_2' => ['label' => 'Establecimientos (2do Nivel)', 'tipo' => 'posicion', 'archivo' => 'establecimientos-salud.csv', 'filtro_col' => 'cve_n_atencion', 'filtro_val' => '2', 'visibilidad' => 'publico', 'is_core' => true],
    'ESTAB_3' => ['label' => 'Establecimientos (3er Nivel)', 'tipo' => 'posicion', 'archivo' => 'establecimientos-salud.csv', 'filtro_col' => 'cve_n_atencion', 'filtro_val' => '3', 'visibilidad' => 'publico', 'is_core' => true],
    'ESTAB_6' => ['label' => 'Establecimientos (No Aplica)', 'tipo' => 'posicion', 'archivo' => 'establecimientos-salud.csv', 'filtro_col' => 'cve_n_atencion', 'filtro_val' => '6', 'visibilidad' => 'publico', 'is_core' => true]
];

$defaults_json = wp_json_encode($defaults);

// 4. Obtener configuración guardada
$marcadores_json = get_option( 'siarhe_marcadores_config', '' );
if ( empty($marcadores_json) ) {
    $marcadores_json = $defaults_json;
} else {
    $marcadores_json = wp_unslash($marcadores_json);
}
?>

<style>
    /* Estilos para el constructor de reglas del Tooltip */
    .siarhe-rule-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; background: #fff; padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px; }
    .siarhe-rule-row input { width: 100%; }
    .siarhe-rule-col { flex: 1; }
    .btn-remove-rule { color: #d63638; cursor: pointer; font-size: 20px; line-height: 1; padding: 5px; }
    .btn-remove-rule:hover { color: #a00; }
    
    /* Toggle Switch Personalizado para el Modal */
    .siarhe-switch { position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; margin-right: 10px; }
    .siarhe-switch input { opacity: 0; width: 0; height: 0; }
    .siarhe-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px; }
    .siarhe-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .3s; border-radius: 50%; }
    .siarhe-switch input:checked + .siarhe-slider { background-color: #007cba; }
    .siarhe-switch input:checked + .siarhe-slider:before { transform: translateX(20px); }
</style>

<div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="margin-top: 0;">📍 Gestor de Marcadores Espaciales (CSV)</h2>
            <p style="margin-bottom: 0;">Administra las capas de puntos sobre el mapa. Puedes configurar marcadores de <strong>Posición</strong> o de <strong>Espectro</strong>, agrupar datos nominales y activar resúmenes geográficos.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="button button-secondary" id="btn-add-marcador">
                <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;"></span> Añadir Nuevo
            </button>
            <button type="button" class="button button-link-delete" id="btn-reset-marcadores" style="color: #d63638; border: 1px solid #d63638; padding: 0 10px; border-radius: 3px; background: #fff;">
                <span class="dashicons dashicons-undo" style="margin-top:3px;"></span> Restaurar Iniciales
            </button>
        </div>
    </div>

    <input type="hidden" name="siarhe_marcadores_config" id="siarhe_marcadores_config" value="<?php echo esc_attr($marcadores_json); ?>">
    
    <input type="hidden" id="siarhe_default_marcadores" value="<?php echo esc_attr($defaults_json); ?>">
    <input type="hidden" id="siarhe_current_user_mk" value="<?php echo esc_attr($editor_name); ?>">
    <input type="hidden" id="siarhe_current_time_mk" value="<?php echo esc_attr($current_time); ?>">
</div>

<div class="card" style="max-width: 100%; padding: 0;">
    <table id="siarhe-marcadores-table" class="siarhe-table">
        <thead>
            <tr>
                <th style="width: 15%;">Clave (Interna)</th>
                <th style="width: 20%;">Etiqueta (Menú)</th>
                <th style="width: 10%;">Tipo</th>
                <th style="width: 20%;">Fuente CSV & Filtro</th>
                <th style="width: 20%;">Edición</th>
                <th style="width: 15%;">Acciones</th>
            </tr>
        </thead>
        <tbody id="siarhe-marcadores-tbody">
            </tbody>
    </table>
</div>

<div id="siarhe-edit-marcador-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content" style="max-width: 650px;">
        <h2 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <span class="dashicons dashicons-location"></span> <span id="modal-mk-title">Editar Marcador</span>
        </h2>
        
        <div class="notice notice-info inline" id="modal-mk-core-notice" style="display:none; margin-bottom:15px;">
            <p>🔒 Este es un marcador <strong>nativo</strong> del sistema. Su clave interna y fuente no pueden modificarse.</p>
        </div>

        <input type="hidden" id="modal-mk-original-key">
        <input type="hidden" id="modal-mk-is-core">

        <table class="form-table">
            <tr>
                <th><label>Clave Interna</label></th>
                <td>
                    <input type="text" id="modal-mk-key" class="regular-text" required placeholder="ej. ESTAB_4">
                    <p class="description">Identificador único en el sistema (sin espacios).</p>
                </td>
            </tr>
            <tr>
                <th><label>Etiqueta Pública</label></th>
                <td>
                    <input type="text" id="modal-mk-label" class="regular-text" style="width:100%;" required placeholder="ej. Unidades Móviles">
                    <p class="description">El nombre que verán los usuarios en el selector y la leyenda.</p>
                </td>
            </tr>
            <tr>
                <th><label>Archivo Fuente (CSV)</label></th>
                <td>
                    <select id="modal-mk-archivo" style="width:100%;">
                        <option value="">-- Seleccionar archivo --</option>
                        <?php foreach($available_csvs as $csv): ?>
                            <option value="<?php echo esc_attr($csv); ?>"><?php echo esc_html($csv); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(empty($available_csvs)): ?>
                        <p class="description" style="color:#d63638;">No hay archivos CSV en la carpeta <code>/siarhe-data/markers/</code>.</p>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th><label>Tipo de Visualización</label></th>
                <td>
                    <select id="modal-mk-tipo">
                        <option value="posicion">📍 Posición (Ícono fijo por establecimiento)</option>
                        <option value="espectro">🔴 Espectro (Burbuja por Densidad/Epidemiología)</option>
                    </select>
                </td>
            </tr>

            <tr id="mk-filtro-row" style="background: #f8fafc;">
                <th><label>Lógica de Filtrado<br><small style="font-weight:normal;">(Opcional)</small></label></th>
                <td>
                    <p class="description" style="margin-top:0; margin-bottom:5px;">Útil si un solo CSV contiene múltiples categorías (Ej. Nivel de Atención).</p>
                    <input type="text" id="modal-mk-filtro-col" class="small-text" style="width:45%;" placeholder="Columna (ej. cve_n_atencion)">
                    <span style="display:inline-block; width:5%; text-align:center;">=</span>
                    <input type="text" id="modal-mk-filtro-val" class="small-text" style="width:45%;" placeholder="Valor (ej. 1)">
                </td>
            </tr>

            <tr id="mk-espectro-row" style="display:none; background: #f0f9ff;">
                <th><label>Configuración de Espectro</label></th>
                <td>
                    <select id="modal-mk-espectro-calc" style="margin-bottom:5px; width:100%;">
                        <option value="absoluto">Basado en Valor Absoluto (Crudo)</option>
                        <option value="relativo">Basado en Tasa Relativa (Poblacional) - Próximamente</option>
                    </select><br>
                    <input type="text" id="modal-mk-espectro-col" class="regular-text" placeholder="Columna a evaluar (ej. total_casos)">
                    <p class="description">El tamaño de la burbuja se calculará en base a los números de esta columna.</p>
                </td>
            </tr>

            <tr style="background: #faf5ff;">
                <th><label>Agrupación y Tooltip<br><small style="font-weight:normal;">(Para bases nominales)</small></label></th>
                <td>
                    <p class="description" style="margin-top:0; margin-bottom:10px;">Si tu base tiene múltiples casos para un mismo punto, agrúpalos aquí para sumar y crear reglas en el tooltip.</p>
                    
                    <label><strong>1. Agrupar puntos por columna:</strong></label><br>
                    <input type="text" id="modal-mk-agrupar-col" class="regular-text" placeholder="Ej. CLUES (Dejar vacío si no aplica)" style="margin-bottom:15px; width: 100%;">

                    <label><strong>2. Reglas de Conteo en Tooltip (Máx 5):</strong></label>
                    <div id="rules-container" style="margin-top:5px;"></div>
                    
                    <button type="button" class="button button-small" id="btn-add-rule" style="margin-top:5px;">
                        <span class="dashicons dashicons-plus-alt2" style="margin-top:2px;"></span> Añadir Regla
                    </button>
                </td>
            </tr>

            <tr style="background: #f0fdf4; border-top: 1px dashed #ccc;">
                <th><label>Resumen Geográfico</label></th>
                <td>
                    <label class="siarhe-switch">
                        <input type="checkbox" id="modal-mk-resumen-geo">
                        <span class="siarhe-slider"></span>
                    </label>
                    <span style="font-weight:bold; color:#166534; vertical-align: middle;">Sumar valores a nivel Municipio/Estado</span>
                    <p class="description" style="margin-top:8px;">Si se activa, cuando el usuario pase el cursor sobre un municipio, se sumarán los datos de este marcador y se mostrarán en el tooltip geográfico principal.</p>
                </td>
            </tr>

            <tr>
                <th><label>Visibilidad</label></th>
                <td>
                    <select id="modal-mk-visibilidad">
                        <option value="publico">Público (Visible para todos)</option>
                        <option value="registrados">Privado (Solo usuarios registrados)</option>
                        <option value="oculto">Oculto (No mostrar en frontend)</option>
                    </select>
                </td>
            </tr>
        </table>
        <div style="text-align:right; margin-top:20px; border-top: 1px solid #eee; padding-top: 15px;">
            <button type="button" class="button button-secondary" id="close-mk-modal">Cancelar</button>
            <button type="button" class="button button-primary" id="save-mk-btn">Aplicar Cambios</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputJson = document.getElementById('siarhe_marcadores_config');
    const defaultJson = document.getElementById('siarhe_default_marcadores').value;
    const currentUser = document.getElementById('siarhe_current_user_mk').value;
    const currentTime = document.getElementById('siarhe_current_time_mk').value;
    
    const tbody = document.getElementById('siarhe-marcadores-tbody');
    const modal = document.getElementById('siarhe-edit-marcador-modal');
    
    const tipoSelect = document.getElementById('modal-mk-tipo');
    const espectroRow = document.getElementById('mk-espectro-row');

    tipoSelect.addEventListener('change', (e) => {
        espectroRow.style.display = e.target.value === 'espectro' ? 'table-row' : 'none';
    });

    let marcadoresObj = {};
    try { marcadoresObj = JSON.parse(inputJson.value); } catch (e) {}

    const rulesContainer = document.getElementById('rules-container');
    const btnAddRule = document.getElementById('btn-add-rule');
    const MAX_RULES = 5;

    function renderRules(rulesArray = []) {
        rulesContainer.innerHTML = '';
        rulesArray.forEach((rule, index) => addRuleRow(rule.label, rule.col, rule.val));
        updateAddRuleBtn();
    }

    function addRuleRow(label = '', col = '', val = '') {
        if (rulesContainer.children.length >= MAX_RULES) return;

        const row = document.createElement('div');
        row.className = 'siarhe-rule-row';
        row.innerHTML = `
            <div class="siarhe-rule-col"><input type="text" class="rule-label" placeholder="Etiqueta (Ej. Graves)" value="${label}"></div>
            <div class="siarhe-rule-col"><input type="text" class="rule-col" placeholder="Columna (Ej. gravedad)" value="${col}"></div>
            <div style="font-weight:bold;">=</div>
            <div class="siarhe-rule-col"><input type="text" class="rule-val" placeholder="Valor (Ej. grave)" value="${val}"></div>
            <span class="dashicons dashicons-no-alt btn-remove-rule" title="Eliminar Regla"></span>
        `;

        row.querySelector('.btn-remove-rule').addEventListener('click', () => {
            row.remove();
            updateAddRuleBtn();
        });

        rulesContainer.appendChild(row);
        updateAddRuleBtn();
    }

    function updateAddRuleBtn() {
        if (rulesContainer.children.length >= MAX_RULES) {
            btnAddRule.style.display = 'none';
        } else {
            btnAddRule.style.display = 'inline-flex';
        }
    }

    btnAddRule.addEventListener('click', () => addRuleRow());

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr.replace(' ', 'T')); 
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute:'2-digit' });
    }

    function renderTable() {
        tbody.innerHTML = '';
        
        Object.keys(marcadoresObj).forEach(key => {
            const item = marcadoresObj[key];
            const isCore = item.is_core === true;
            const vis = item.visibilidad || 'publico'; 
            
            const badgeType = item.tipo === 'posicion' ? 'neutral' : 'warning';
            const badgeLabel = item.tipo === 'posicion' ? '📍 Posición' : '🔴 Espectro';
            const coreBadge = isCore ? '<span class="siarhe-badge brand" style="margin-left:5px;"><span class="dashicons dashicons-lock" style="font-size:12px;width:12px;height:12px;margin-top:2px;"></span> Nativo</span>' : '';
            
            const editInfo = item.last_edited_by 
                ? `<small style="color:#777;">Por: <strong>${item.last_edited_by}</strong><br>${formatDate(item.last_edited_at)}</small>` 
                : '<small style="color:#ccc;">Configuración Inicial</small>';

            const btnDelete = isCore 
                ? `<span class="dashicons dashicons-lock" style="color:#ccc; margin-left:10px;" title="Los marcadores nativos no se pueden eliminar"></span>` 
                : `<button type="button" class="button button-small button-link-delete btn-delete-mk" data-key="${key}" title="Eliminar marcador"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>`;
                
            let eyeIcon = 'dashicons-visibility';
            let eyeColor = '#007cba';
            let eyeTitle = 'Público: Visible para todos';
            if (vis === 'registrados') { eyeIcon = 'dashicons-admin-users'; eyeColor = '#e68a00'; eyeTitle = 'Privado: Solo usuarios registrados'; }
            if (vis === 'oculto') { eyeIcon = 'dashicons-hidden'; eyeColor = '#8c8f94'; eyeTitle = 'Oculto: No se mostrará en frontend'; }

            let fuenteHtml = `<strong>📄 ${item.archivo || '—'}</strong>`;
            if (item.filtro_col && item.filtro_val) {
                fuenteHtml += `<br><small style="color:#0A66C2;">Filtro: ${item.filtro_col} = ${item.filtro_val}</small>`;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="Clave">
                    <strong style="color:#2271b1; font-family:monospace;">${key}</strong> ${coreBadge}
                </td>
                <td data-label="Etiqueta">
                    <strong>${item.label}</strong>
                </td>
                <td data-label="Tipo">
                    <span class="siarhe-badge ${badgeType}">${badgeLabel}</span>
                </td>
                <td data-label="Fuente CSV">
                    ${fuenteHtml}
                </td>
                <td data-label="Última Edición">
                    ${editInfo}
                </td>
                <td data-label="Acciones">
                    <button type="button" class="button button-small btn-toggle-vis-mk" data-key="${key}" title="${eyeTitle}">
                        <span class="dashicons ${eyeIcon}" style="color:${eyeColor};"></span>
                    </button>
                    <button type="button" class="button button-small btn-edit-mk" data-key="${key}" title="Modificar parámetros">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    ${btnDelete}
                </td>
            `;
            tbody.appendChild(tr);
        });

        attachEvents();
    }

    function attachEvents() {
        document.querySelectorAll('.btn-toggle-vis-mk').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const key = this.getAttribute('data-key');
                const currVis = marcadoresObj[key].visibilidad || 'publico';
                let nextVis = 'publico';
                if (currVis === 'publico') nextVis = 'registrados';
                else if (currVis === 'registrados') nextVis = 'oculto';
                marcadoresObj[key].visibilidad = nextVis;
                updateHiddenInput();
                renderTable();
            });
        });

        document.querySelectorAll('.btn-delete-mk').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const key = this.getAttribute('data-key');
                if (confirm(`¿Eliminar la configuración del marcador "${key}"? (El archivo CSV no se borrará)`)) {
                    delete marcadoresObj[key];
                    updateHiddenInput();
                    renderTable();
                }
            });
        });

        document.querySelectorAll('.btn-edit-mk').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                openModal(this.getAttribute('data-key'));
            });
        });
    }

    function openModal(key = null) {
        const isNew = key === null;
        const item = isNew ? { 
            label: '', tipo: 'posicion', archivo: '', 
            filtro_col: '', filtro_val: '', espectro_calc: 'absoluto', espectro_col: '', 
            agrupar_col: '', reglas_tooltip: [], resumen_geo: false, // 🌟 NUEVA VARIABLE: resumen_geo
            visibilidad: 'publico', is_core: false 
        } : marcadoresObj[key];

        document.getElementById('modal-mk-title').textContent = isNew ? 'Añadir Nuevo Marcador' : 'Editar Marcador';
        document.getElementById('modal-mk-original-key').value = isNew ? '' : key;
        document.getElementById('modal-mk-is-core').value = item.is_core ? '1' : '0';

        const keyInput = document.getElementById('modal-mk-key');
        const archivoInput = document.getElementById('modal-mk-archivo');
        const notice = document.getElementById('modal-mk-core-notice');

        keyInput.value = isNew ? '' : key;
        document.getElementById('modal-mk-label').value = item.label || '';
        tipoSelect.value = item.tipo || 'posicion';
        archivoInput.value = item.archivo || '';
        document.getElementById('modal-mk-filtro-col').value = item.filtro_col || '';
        document.getElementById('modal-mk-filtro-val').value = item.filtro_val || '';
        document.getElementById('modal-mk-espectro-calc').value = item.espectro_calc || 'absoluto';
        document.getElementById('modal-mk-espectro-col').value = item.espectro_col || '';
        document.getElementById('modal-mk-visibilidad').value = item.visibilidad || 'publico';
        
        document.getElementById('modal-mk-agrupar-col').value = item.agrupar_col || '';
        renderRules(item.reglas_tooltip || []);
        
        // 🌟 LLENAR EL ESTADO DEL CHECKBOX
        document.getElementById('modal-mk-resumen-geo').checked = item.resumen_geo === true;

        tipoSelect.dispatchEvent(new Event('change'));

        if (item.is_core) {
            keyInput.readOnly = true; keyInput.style.background = '#f0f0f1';
            archivoInput.disabled = true; archivoInput.style.background = '#f0f0f1';
            notice.style.display = 'block';
        } else {
            keyInput.readOnly = false; keyInput.style.background = '#fff';
            archivoInput.disabled = false; archivoInput.style.background = '#fff';
            notice.style.display = 'none';
        }

        modal.style.display = 'block';
    }

    document.getElementById('close-mk-modal').addEventListener('click', () => { modal.style.display = 'none'; });
    document.getElementById('btn-add-marcador').addEventListener('click', () => openModal(null));

    document.getElementById('save-mk-btn').addEventListener('click', () => {
        const originalKey = document.getElementById('modal-mk-original-key').value;
        const newKey = document.getElementById('modal-mk-key').value.trim().toUpperCase().replace(/[^A-Z0-9_]/g, '_');
        const isCore = document.getElementById('modal-mk-is-core').value === '1';

        if (!newKey) { alert('La clave es obligatoria.'); return; }
        
        const archivo = document.getElementById('modal-mk-archivo');
        const archivoVal = isCore && marcadoresObj[originalKey] ? marcadoresObj[originalKey].archivo : archivo.value;

        if (!archivoVal) { alert('Debe seleccionar un archivo fuente CSV.'); return; }

        if (!isCore && originalKey && originalKey !== newKey) {
            delete marcadoresObj[originalKey];
        }

        const collectedRules = [];
        document.querySelectorAll('.siarhe-rule-row').forEach(row => {
            const label = row.querySelector('.rule-label').value.trim();
            const col = row.querySelector('.rule-col').value.trim();
            const val = row.querySelector('.rule-val').value.trim();
            if (label && col && val) {
                collectedRules.push({ label, col, val });
            }
        });

        // 🌟 GUARDAR EL ESTADO DEL CHECKBOX
        const isResumenGeo = document.getElementById('modal-mk-resumen-geo').checked;

        marcadoresObj[newKey] = {
            label: document.getElementById('modal-mk-label').value.trim(),
            tipo: tipoSelect.value,
            archivo: archivoVal,
            filtro_col: document.getElementById('modal-mk-filtro-col').value.trim(),
            filtro_val: document.getElementById('modal-mk-filtro-val').value.trim(),
            espectro_calc: document.getElementById('modal-mk-espectro-calc').value,
            espectro_col: document.getElementById('modal-mk-espectro-col').value.trim(),
            agrupar_col: document.getElementById('modal-mk-agrupar-col').value.trim(), 
            reglas_tooltip: collectedRules, 
            resumen_geo: isResumenGeo, // 🌟 GUARDAR BOOLEAN
            visibilidad: document.getElementById('modal-mk-visibilidad').value,
            is_core: isCore,
            last_edited_by: currentUser,
            last_edited_at: currentTime
        };

        updateHiddenInput();
        renderTable();
        modal.style.display = 'none';
    });

    document.getElementById('btn-reset-marcadores').addEventListener('click', function() {
        if (confirm('⚠️ PRECAUCIÓN: Se restaurarán los marcadores iniciales. ¿Confirmar?')) {
            marcadoresObj = JSON.parse(defaultJson);
            updateHiddenInput();
            this.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> Procesando...';
            this.disabled = true;
            const form = document.querySelector('form[action="options.php"]');
            if (form) HTMLFormElement.prototype.submit.call(form);
        }
    });

    function updateHiddenInput() {
        inputJson.value = JSON.stringify(marcadoresObj);
        
        const btnSubmit = document.querySelector('input[type="submit"]#submit');
        if (btnSubmit) {
            btnSubmit.removeAttribute('disabled');
            btnSubmit.classList.remove('disabled');
            btnSubmit.style.boxShadow = '0 0 0 4px rgba(0, 115, 170, 0.4)';
            btnSubmit.style.transform = 'scale(1.05)';
            btnSubmit.style.transition = 'all 0.3s ease';
            setTimeout(() => { btnSubmit.style.transform = 'scale(1)'; }, 300);
        }
        
        if (!document.getElementById('siarhe-save-notice')) {
            document.querySelector('.wp-header-end').insertAdjacentHTML('afterend', '<div id="siarhe-save-notice" class="notice notice-warning"><p>⚠️ <strong>Atención:</strong> Existen cambios pendientes en la memoria temporal. Haz clic en <strong>"Guardar Configuración"</strong>.</p></div>');
        }
    }

    renderTable();
});
</script>