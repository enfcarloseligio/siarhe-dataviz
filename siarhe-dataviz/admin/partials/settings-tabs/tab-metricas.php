<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener usuario actual y hora para la auditoría de cambios
$current_user = wp_get_current_user();
$editor_name = $current_user->display_name ?: $current_user->user_login;
$current_time = current_time('mysql');

// 2. Definir el diccionario de métricas base del sistema (Protegidas)
$defaults = [
    'tasa_total'                 => ['label' => 'Tasa Total', 'fullLabel' => 'Tasa de enfermeras por cada mil habitantes', 'tipo' => 'tasa', 'pair' => 'enfermeras_total', 'is_core' => true],
    'enfermeras_total'           => ['label' => 'Total Enf.', 'fullLabel' => 'Total de profesionales de enfermería', 'tipo' => 'absoluto', 'pair' => 'enfermeras_total', 'is_core' => true],
    'tasa_primer'                => ['label' => 'Tasa 1er Nivel', 'fullLabel' => 'Tasa de enfermeras en 1er Nivel de Atención', 'tipo' => 'tasa', 'pair' => 'enfermeras_primer', 'is_core' => true],
    'enfermeras_primer'          => ['label' => 'Enf. 1er Nivel', 'fullLabel' => 'Enfermeras en 1er Nivel de Atención', 'tipo' => 'absoluto', 'pair' => 'enfermeras_primer', 'is_core' => true],
    'tasa_segundo'               => ['label' => 'Tasa 2do Nivel', 'fullLabel' => 'Tasa de enfermeras en 2do Nivel de Atención', 'tipo' => 'tasa', 'pair' => 'enfermeras_segundo', 'is_core' => true],
    'enfermeras_segundo'         => ['label' => 'Enf. 2do Nivel', 'fullLabel' => 'Enfermeras en 2do Nivel de Atención', 'tipo' => 'absoluto', 'pair' => 'enfermeras_segundo', 'is_core' => true],
    'tasa_tercer'                => ['label' => 'Tasa 3er Nivel', 'fullLabel' => 'Tasa de enfermeras en 3er Nivel de Atención', 'tipo' => 'tasa', 'pair' => 'enfermeras_tercer', 'is_core' => true],
    'enfermeras_tercer'          => ['label' => 'Enf. 3er Nivel', 'fullLabel' => 'Enfermeras en 3er Nivel de Atención', 'tipo' => 'absoluto', 'pair' => 'enfermeras_tercer', 'is_core' => true],
    'tasa_apoyo'                 => ['label' => 'Tasa Apoyo', 'fullLabel' => 'Tasa de enfermeras en establecimientos de apoyo', 'tipo' => 'tasa', 'pair' => 'enfermeras_apoyo', 'is_core' => true],
    'enfermeras_apoyo'           => ['label' => 'Enf. Apoyo', 'fullLabel' => 'Enfermeras en establecimientos de apoyo', 'tipo' => 'absoluto', 'pair' => 'enfermeras_apoyo', 'is_core' => true],
    'tasa_administrativas'       => ['label' => 'Tasa Admin.', 'fullLabel' => 'Tasa de enfermeras con funciones administrativas', 'tipo' => 'tasa', 'pair' => 'enfermeras_administrativas', 'is_core' => true],
    'enfermeras_administrativas' => ['label' => 'Enf. Admin.', 'fullLabel' => 'Enfermeras con funciones administrativas', 'tipo' => 'absoluto', 'pair' => 'enfermeras_administrativas', 'is_core' => true],
    'tasa_escuelas'              => ['label' => 'Tasa Escuelas', 'fullLabel' => 'Tasa de enfermeras en escuelas de enfermería', 'tipo' => 'tasa', 'pair' => 'enfermeras_escuelas', 'is_core' => true],
    'enfermeras_escuelas'        => ['label' => 'Enf. Escuelas', 'fullLabel' => 'Enfermeras en escuelas de enfermería', 'tipo' => 'absoluto', 'pair' => 'enfermeras_escuelas', 'is_core' => true],
    'tasa_no_aplica'             => ['label' => 'Tasa Otros Est.', 'fullLabel' => 'Tasa de enfermeras en otros establecimientos', 'tipo' => 'tasa', 'pair' => 'enfermeras_no_aplica', 'is_core' => true],
    'enfermeras_no_aplica'       => ['label' => 'Enf. Otros Est.', 'fullLabel' => 'Enfermeras en otros establecimientos', 'tipo' => 'absoluto', 'pair' => 'enfermeras_no_aplica', 'is_core' => true],
    'tasa_no_asignado'           => ['label' => 'Tasa No Asignado', 'fullLabel' => 'Tasa de enfermeras con funciones no asignadas', 'tipo' => 'tasa', 'pair' => 'enfermeras_no_asignado', 'is_core' => true],
    'enfermeras_no_asignado'     => ['label' => 'Enf. No Asignado', 'fullLabel' => 'Enfermeras con funciones no asignadas', 'tipo' => 'absoluto', 'pair' => 'enfermeras_no_asignado', 'is_core' => true],
    'poblacion'                  => ['label' => 'Población', 'fullLabel' => 'Población total', 'tipo' => 'absoluto', 'pair' => 'poblacion', 'is_core' => true]
];

$defaults_json = wp_json_encode($defaults);

// 3. Obtener configuración guardada o aplicar valores por defecto
$metricas_json = get_option( 'siarhe_metricas_config', '' );
if ( empty($metricas_json) ) {
    $metricas_json = $defaults_json;
} else {
    $metricas_json = wp_unslash($metricas_json);
}
?>

<div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="margin-top: 0;">📊 Gestor de Métricas e Indicadores (CSV)</h2>
            <p style="margin-bottom: 0;">Configura los indicadores que se mostrarán en el mapa y la tabla. Las variables <strong>nativas</strong> están protegidas, pero puedes editar sus descripciones o añadir nuevas variables (ej. Enfermeras Quirúrgicas) siempre y cuando existan en tu archivo CSV.</p>
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

<div class="card" style="max-width: 100%; padding: 0;">
    <table id="siarhe-metricas-table" class="siarhe-table">
        <thead>
            <tr>
                <th style="width: 20%;">Clave de Columna (CSV)</th>
                <th style="width: 20%;">Etiqueta Corta</th>
                <th style="width: 25%;">Etiqueta Larga</th>
                <th style="width: 10%;">Tipo</th>
                <th style="width: 15%;">Última Edición</th>
                <th style="width: 10%;">Acciones</th>
            </tr>
        </thead>
        <tbody id="siarhe-metricas-tbody">
            </tbody>
    </table>
</div>

<div id="siarhe-edit-metric-modal" class="siarhe-modal-overlay">
    <div class="siarhe-modal-content">
        <h2 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <span class="dashicons dashicons-edit"></span> <span id="modal-metric-title">Editar Métrica</span>
        </h2>
        
        <div class="notice notice-info inline" id="modal-core-notice" style="display:none; margin-bottom:15px;">
            <p>🔒 Esta es una métrica <strong>nativa</strong> del sistema. La clave, el tipo de valor y su par no pueden modificarse para preservar la integridad del mapa principal.</p>
        </div>

        <input type="hidden" id="modal-metric-original-key">
        <input type="hidden" id="modal-metric-is-core">

        <table class="form-table">
            <tr>
                <th><label>Clave (Columna CSV)</label></th>
                <td>
                    <input type="text" id="modal-metric-key" class="regular-text" required placeholder="ej. tasa_quirurgicas">
                    <p class="description">Debe coincidir de forma exacta con la cabecera en el archivo origen.</p>
                </td>
            </tr>
            <tr>
                <th><label>Etiqueta Corta</label></th>
                <td>
                    <input type="text" id="modal-metric-label" class="regular-text" required placeholder="ej. Tasa Quirúr.">
                    <p class="description">Utilizada para las columnas de la tabla de datos y la leyenda visual.</p>
                </td>
            </tr>
            <tr>
                <th><label>Etiqueta Larga</label></th>
                <td>
                    <input type="text" id="modal-metric-full" class="regular-text" style="width:100%;" required placeholder="ej. Tasa de enfermeras quirúrgicas por mil hab.">
                    <p class="description">Texto descriptivo para el selector del menú desplegable.</p>
                </td>
            </tr>
            <tr>
                <th><label>Tipo de Valor</label></th>
                <td>
                    <select id="modal-metric-tipo">
                        <option value="tasa">Tasa (Representación visual en mapa / Formato decimal)</option>
                        <option value="absoluto">Absoluto (Conteo numérico estándar)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Clave Relacionada (Par)</label></th>
                <td>
                    <input type="text" id="modal-metric-pair" class="regular-text" required placeholder="ej. enfermeras_quirurgicas">
                    <p class="description">Vincula el valor de una tasa con su conteo absoluto para la visualización combinada en el Tooltip.</p>
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
    const inputJson = document.getElementById('siarhe_metricas_config');
    const defaultJson = document.getElementById('siarhe_default_metricas').value;
    const currentUser = document.getElementById('siarhe_current_user').value;
    const currentTime = document.getElementById('siarhe_current_time').value;
    
    const tbody = document.getElementById('siarhe-metricas-tbody');
    const modal = document.getElementById('siarhe-edit-metric-modal');

    let metricasObj = {};
    try { metricasObj = JSON.parse(inputJson.value); } catch (e) {}

    /**
     * Formatea cadenas de fecha para el registro de auditoría visual
     */
    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr.replace(' ', 'T')); 
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute:'2-digit' });
    }

    /**
     * Renderiza las filas de la tabla de administración basada en el objeto actual
     */
    function renderTable() {
        tbody.innerHTML = '';
        
        Object.keys(metricasObj).forEach(key => {
            const item = metricasObj[key];
            const isCore = item.is_core === true;
            
            const badgeType = item.tipo === 'tasa' ? 'success' : 'neutral';
            const badgeLabel = item.tipo === 'tasa' ? 'Tasa' : 'Absoluto';
            const coreBadge = isCore ? '<span class="siarhe-badge brand" style="margin-left:5px;"><span class="dashicons dashicons-lock" style="font-size:12px;width:12px;height:12px;margin-top:2px;"></span> Nativa</span>' : '';
            
            const editInfo = item.last_edited_by 
                ? `<small style="color:#777;">Por: <strong>${item.last_edited_by}</strong><br>${formatDate(item.last_edited_at)}</small>` 
                : '<small style="color:#ccc;">Configuración Inicial</small>';

            const btnDelete = isCore 
                ? `<span class="dashicons dashicons-lock" style="color:#ccc; margin-left:10px;" title="Las métricas nativas no se pueden eliminar"></span>` 
                : `<button type="button" class="button button-small button-link-delete btn-delete-metrica" data-key="${key}" title="Eliminar métrica"><span class="dashicons dashicons-trash" style="color:#d63638;"></span></button>`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="Clave (CSV)" data-mobile-role="primary">
                    <strong style="color:#2271b1; font-family:monospace;">${key}</strong> ${coreBadge}
                </td>
                <td data-label="Etiqueta Corta" data-mobile-role="secondary">
                    <strong>${item.label}</strong>
                </td>
                <td data-label="Etiqueta Larga">
                    <span class="siarhe-break-text" style="font-size:12px; color:#555;">${item.fullLabel}</span>
                </td>
                <td data-label="Tipo">
                    <span class="siarhe-badge ${badgeType}">${badgeLabel}</span>
                </td>
                <td data-label="Última Edición">
                    ${editInfo}
                </td>
                <td data-label="Acciones">
                    <button type="button" class="button button-small btn-edit-metrica" data-key="${key}" title="Modificar parámetros">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    ${btnDelete}
                </td>
            `;
            tbody.appendChild(tr);
        });

        attachEvents();
    }

    /**
     * Vincula los controladores de eventos a los elementos del DOM generados
     */
    function attachEvents() {
        // Gestión del acordeón responsivo
        document.querySelectorAll('#siarhe-metricas-table tbody tr').forEach(row => {
            row.removeEventListener('click', handleRowClick);
            row.addEventListener('click', handleRowClick);
        });

        // Eliminación de elementos
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

        // Apertura del modal de modificación
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

    /**
     * Inicializa y despliega la interfaz modal de edición
     */
    function openModal(key = null) {
        const isNew = key === null;
        const item = isNew ? { label: '', fullLabel: '', tipo: 'absoluto', pair: '', is_core: false } : metricasObj[key];

        document.getElementById('modal-metric-title').textContent = isNew ? 'Añadir Nueva Métrica' : 'Editar Propiedades';
        document.getElementById('modal-metric-original-key').value = isNew ? '' : key;
        document.getElementById('modal-metric-is-core').value = item.is_core ? '1' : '0';

        const keyInput = document.getElementById('modal-metric-key');
        const pairInput = document.getElementById('modal-metric-pair');
        const typeInput = document.getElementById('modal-metric-tipo');
        const notice = document.getElementById('modal-core-notice');

        keyInput.value = isNew ? '' : key;
        document.getElementById('modal-metric-label').value = item.label;
        document.getElementById('modal-metric-full').value = item.fullLabel;
        typeInput.value = item.tipo;
        pairInput.value = item.pair;

        if (item.is_core) {
            keyInput.readOnly = true;
            keyInput.style.background = '#f0f0f1';
            pairInput.readOnly = true;
            pairInput.style.background = '#f0f0f1';
            typeInput.disabled = true;
            typeInput.style.background = '#f0f0f1';
            notice.style.display = 'block';
        } else {
            keyInput.readOnly = false;
            keyInput.style.background = '#fff';
            pairInput.readOnly = false;
            pairInput.style.background = '#fff';
            typeInput.disabled = false;
            typeInput.style.background = '#fff';
            notice.style.display = 'none';
        }

        modal.style.display = 'block';
    }

    // Eventos de cierre del modal
    document.getElementById('close-metric-modal').addEventListener('click', () => { modal.style.display = 'none'; });
    window.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

    document.getElementById('btn-add-metrica').addEventListener('click', () => openModal(null));

    // Consolidación de datos del modal
    document.getElementById('save-metric-btn').addEventListener('click', () => {
        const originalKey = document.getElementById('modal-metric-original-key').value;
        const newKey = document.getElementById('modal-metric-key').value.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
        const isCore = document.getElementById('modal-metric-is-core').value === '1';

        if (!newKey) { alert('La clave identificadora es obligatoria.'); return; }

        if (!isCore && originalKey && originalKey !== newKey) {
            delete metricasObj[originalKey];
        }

        metricasObj[newKey] = {
            label: document.getElementById('modal-metric-label').value.trim(),
            fullLabel: document.getElementById('modal-metric-full').value.trim(),
            tipo: document.getElementById('modal-metric-tipo').value,
            pair: document.getElementById('modal-metric-pair').value.trim(),
            is_core: isCore,
            last_edited_by: currentUser,
            last_edited_at: currentTime
        };

        updateHiddenInput();
        renderTable();
        modal.style.display = 'none';
        
        // Indicadores visuales para recordar guardar los ajustes en el sistema
        const btnSubmit = document.querySelector('input[type="submit"]#submit');
        if (btnSubmit) {
            btnSubmit.style.boxShadow = '0 0 0 4px rgba(0, 115, 170, 0.4)';
            btnSubmit.style.transform = 'scale(1.05)';
            btnSubmit.style.transition = 'all 0.3s ease';
            setTimeout(() => { btnSubmit.style.transform = 'scale(1)'; }, 300);
        }
        
        if (!document.getElementById('siarhe-save-notice')) {
            document.querySelector('.wp-header-end').insertAdjacentHTML('afterend', '<div id="siarhe-save-notice" class="notice notice-warning"><p>⚠️ <strong>Atención:</strong> Existen cambios pendientes en la memoria temporal. Es necesario hacer clic en <strong>"Guardar Configuración"</strong> para aplicarlos al sistema.</p></div>');
        }
    });

    // Acción de restablecimiento a valores por defecto
    document.getElementById('btn-reset-metricas').addEventListener('click', function() {
        if (confirm('⚠️ PRECAUCIÓN: Esta acción purgará cualquier métrica personalizada e inicializará el catálogo a sus valores de fábrica. El proceso es irreversible. ¿Confirmar operación?')) {
            
            metricasObj = JSON.parse(defaultJson);
            updateHiddenInput();
            
            // Bloqueo de UI durante la solicitud
            this.innerHTML = '<span class="spinner is-active" style="float:none; margin:0 5px 0 0;"></span> Procesando...';
            this.disabled = true;
            
            // Envío forzado del formulario eludiendo colisiones del DOM (id="submit")
            const form = document.querySelector('form[action="options.php"]');
            if (form) {
                HTMLFormElement.prototype.submit.call(form);
            }
        }
    });

    /**
     * Sincroniza el objeto JavaScript con el input asociado a la base de datos de WP
     */
    function updateHiddenInput() {
        inputJson.value = JSON.stringify(metricasObj);
        
        // Despacha un evento para notificar al DOM de un cambio validado
        inputJson.dispatchEvent(new Event('change', { bubbles: true }));
        
        // Asegura la habilitación de los controles de envío
        const btnSubmit = document.querySelector('input[type="submit"]#submit');
        if (btnSubmit) {
            btnSubmit.removeAttribute('disabled');
            btnSubmit.classList.remove('disabled');
        }
    }

    // Inicialización del módulo
    renderTable();
});
</script>