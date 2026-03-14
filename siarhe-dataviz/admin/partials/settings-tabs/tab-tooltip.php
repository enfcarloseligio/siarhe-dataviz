<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Obtener configuración o crear valores por defecto (todo encendido)
$tooltip_json = get_option( 'siarhe_tooltip_config', '' );
if ( empty($tooltip_json) ) {
    $defaults = [
        // Mapa
        'geo_pob' => true, 'geo_abs' => true, 'geo_rate' => true,
        // Orden por defecto: poblacion -> absoluto -> tasa
        'geo_order' => ['pob', 'abs', 'rate'],
        // Marcadores
        'mk_inst' => true, 'mk_mun' => true, 'mk_clues' => true,
        'mk_tipo' => true, 'mk_nivel' => true, 'mk_juris' => true,
        // Diseño del Tooltip (Mapa)
        'bg_color' => '#0f172a',    // Fondo por defecto (Azul muy oscuro casi negro)
        'bg_opacity' => '90',       // Transparencia (90%)
        'text_color' => '#f8fafc',  // Texto por defecto (Blanco)
        'highlight_var' => 'rate',  // Qué variable destacar por defecto (La tasa)
        'highlight_color' => '#06b6d4', // Color de la variable destacada (Cyan)
        
        // Orden y Diseño del Tooltip (Marcadores)
        'mk_order' => ['mk_inst', 'mk_clues', 'mk_tipo', 'mk_nivel', 'mk_separator', 'mk_juris', 'mk_mun'],
        'mk_bg_color' => '#0f172a',
        'mk_bg_opacity' => '90',
        'mk_text_color' => '#f8fafc',
        'mk_highlight_var' => 'none',
        'mk_highlight_color' => '#06b6d4'
    ];
    $tooltip_json = wp_json_encode($defaults);
} else {
    $tooltip_json = wp_unslash($tooltip_json);
}

// Extraer el objeto para pintar el HTML correctamente en la primera carga
$configObj = json_decode($tooltip_json, true) ?: [];

// Variables seguras para evitar errores de índice indefinido (Mapa)
$bgColor = isset($configObj['bg_color']) ? $configObj['bg_color'] : '#0f172a';
$bgOpacity = isset($configObj['bg_opacity']) ? $configObj['bg_opacity'] : '90';
$textColor = isset($configObj['text_color']) ? $configObj['text_color'] : '#f8fafc';
$hlVar = isset($configObj['highlight_var']) ? $configObj['highlight_var'] : 'rate';
$hlColor = isset($configObj['highlight_color']) ? $configObj['highlight_color'] : '#06b6d4';
$geoOrder = isset($configObj['geo_order']) && is_array($configObj['geo_order']) ? $configObj['geo_order'] : ['pob', 'abs', 'rate'];

// Variables seguras (Marcadores)
$mkBgColor = isset($configObj['mk_bg_color']) ? $configObj['mk_bg_color'] : '#0f172a';
$mkBgOpacity = isset($configObj['mk_bg_opacity']) ? $configObj['mk_bg_opacity'] : '90';
$mkTextColor = isset($configObj['mk_text_color']) ? $configObj['mk_text_color'] : '#f8fafc';
$mkHlVar = isset($configObj['mk_highlight_var']) ? $configObj['mk_highlight_var'] : 'none';
$mkHlColor = isset($configObj['mk_highlight_color']) ? $configObj['mk_highlight_color'] : '#06b6d4';
$mkOrder = isset($configObj['mk_order']) && is_array($configObj['mk_order']) ? $configObj['mk_order'] : ['mk_inst', 'mk_clues', 'mk_tipo', 'mk_nivel', 'mk_separator', 'mk_juris', 'mk_mun'];

?>

<style>
    /* Estilos para Toggle Switches modernos */
    .siarhe-toggle { position: relative; display: inline-block; width: 44px; height: 24px; margin-right: 10px; vertical-align: middle; }
    .siarhe-toggle input { opacity: 0; width: 0; height: 0; }
    .siarhe-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px; }
    .siarhe-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    .siarhe-toggle input:checked + .siarhe-slider { background-color: #0A66C2; }
    .siarhe-toggle input:checked + .siarhe-slider:before { transform: translateX(20px); }
    
    .siarhe-tooltip-row { display: flex; align-items: center; padding: 12px; border-bottom: 1px solid #f1f5f9; background: #fff; }
    .siarhe-tooltip-row:last-child { border-bottom: none; }
    
    /* Estilos para Drag & Drop */
    .siarhe-draggable-list { border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; background: #f8fafc; }
    .siarhe-draggable-item { cursor: grab; transition: background 0.2s; display: flex; align-items: center; padding: 10px 15px; border-bottom: 1px solid #e2e8f0; background: #fff; position: relative; }
    .siarhe-draggable-item:last-child { border-bottom: none; }
    .siarhe-draggable-item:hover { background: #f1f5f9; }
    .siarhe-draggable-item:active { cursor: grabbing; }
    .siarhe-drag-handle { color: #cbd5e1; margin-right: 15px; cursor: grab; font-size: 20px; line-height: 1; }
    .siarhe-drag-ghost { opacity: 0.4; border: 2px dashed #94a3b8; }
    
    .siarhe-design-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
    .siarhe-design-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; }
</style>

<div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <h2 style="margin-top: 0;">ℹ️ Gestor de Contenido y Diseño en Tooltips</h2>
    <p style="margin-bottom: 0;">Personaliza la información, el orden y los colores de las tarjetas flotantes del mapa y de los marcadores. Arrastra desde el ícono <span class="dashicons dashicons-menu" style="font-size:16px; width:16px; height:16px;"></span> para ordenar.</p>
</div>

<input type="hidden" name="siarhe_tooltip_config" id="siarhe_tooltip_config" value="<?php echo esc_attr($tooltip_json); ?>">

<div class="card siarhe-upload-card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <h3 style="margin-top: 0; border-bottom: 2px solid #0A66C2; padding-bottom: 10px; display: inline-block;">
        🗺️ Tooltip del Mapa (Regiones)
    </h3>

    <div class="siarhe-draggable-list" id="geo-sortable-list" style="margin-bottom: 25px;">
        <?php
        // Función auxiliar para imprimir las filas en el orden guardado
        $filas_html = [
            'pob' => '
                <div class="siarhe-draggable-item" data-id="pob" draggable="true">
                    <span class="dashicons dashicons-menu siarhe-drag-handle"></span>
                    <label class="siarhe-toggle"><input type="checkbox" id="tt_geo_pob"><span class="siarhe-slider"></span></label>
                    <div>
                        <strong>Población Total</strong><br>
                        <small style="color: #64748b;">Muestra la población del estado o municipio.</small>
                    </div>
                </div>',
            'abs' => '
                <div class="siarhe-draggable-item" data-id="abs" draggable="true">
                    <span class="dashicons dashicons-menu siarhe-drag-handle"></span>
                    <label class="siarhe-toggle"><input type="checkbox" id="tt_geo_abs"><span class="siarhe-slider"></span></label>
                    <div>
                        <strong>Valor Absoluto (Total de Elementos)</strong><br>
                        <small style="color: #64748b;">Ej: "Enfermeras: 1,450".</small>
                    </div>
                </div>',
            'rate' => '
                <div class="siarhe-draggable-item" data-id="rate" draggable="true">
                    <span class="dashicons dashicons-menu siarhe-drag-handle"></span>
                    <label class="siarhe-toggle"><input type="checkbox" id="tt_geo_rate"><span class="siarhe-slider"></span></label>
                    <div>
                        <strong>Valor Relativo (Tasa)</strong><br>
                        <small style="color: #64748b;">Ej: "Tasa: 2.45".</small>
                    </div>
                </div>'
        ];

        // Imprimimos según el orden guardado
        foreach ($geoOrder as $item_id) {
            if (isset($filas_html[$item_id])) {
                echo $filas_html[$item_id];
            }
        }
        ?>
    </div>

    <h4 style="margin-top: 0; margin-bottom: 10px;">🎨 Diseño y Colores</h4>
    <div class="siarhe-design-grid">
        <div class="siarhe-design-box">
            <label><strong>Color de Fondo</strong></label><br>
            <input type="text" id="tt_bg_color" value="<?php echo esc_attr($bgColor); ?>" class="siarhe-color-field" style="margin-top:5px;">
        </div>

        <div class="siarhe-design-box">
            <label><strong>Opacidad (Transparencia)</strong></label><br>
            <input type="number" id="tt_bg_opacity" value="<?php echo esc_attr($bgOpacity); ?>" min="10" max="100" style="width: 70px; margin-top:5px;"> %
        </div>

        <div class="siarhe-design-box">
            <label><strong>Color del Texto Base</strong></label><br>
            <input type="text" id="tt_text_color" value="<?php echo esc_attr($textColor); ?>" class="siarhe-color-field" style="margin-top:5px;">
        </div>
    </div>

    <h4 style="margin-top: 25px; margin-bottom: 10px;">Destacar una Variable</h4>
    <p style="color: #64748b; margin-top:0;">La variable destacada se colocará al final del tooltip, separada por una línea sutil, utilizando el color elegido.</p>
    
    <div style="display:flex; gap:20px; flex-wrap:wrap; background:#f0f9ff; padding:15px; border:1px dashed #bae6fd; border-radius:6px;">
        <div style="flex: 1 1 200px;"> <label><strong>¿Qué dato destacar?</strong></label><br>
            <select id="tt_highlight_var" style="margin-top:5px; width:100%;">
                <option value="none" <?php selected($hlVar, 'none'); ?>>Ninguno (Todo estándar)</option>
                <option value="pob" <?php selected($hlVar, 'pob'); ?>>Población Total</option>
                <option value="abs" <?php selected($hlVar, 'abs'); ?>>Valor Absoluto (Ej. Total de Enfermeras)</option>
                <option value="rate" <?php selected($hlVar, 'rate'); ?>>Valor Relativo (La Tasa / Indicador Principal)</option>
            </select>
        </div>
        <div style="flex: 1 1 200px;"> <label><strong>Color del Texto Destacado</strong></label><br>
            <input type="text" id="tt_highlight_color" value="<?php echo esc_attr($hlColor); ?>" class="siarhe-color-field" style="margin-top:5px; width:100%;">
        </div>
    </div>
</div>

<div class="card siarhe-upload-card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <h3 style="margin-top: 0; border-bottom: 2px solid #06B6D4; padding-bottom: 10px; display: inline-block;">
        📍 Tooltip de Marcadores (Instituciones)
    </h3>
    <p style="color: #64748b;">El título de la tarjeta será automático (Ej. "Establecimiento en Zona Urbana" + Nombre de la Unidad). Organiza aquí el resto de la información y arrastra la línea separadora a donde la necesites.</p>

    <div class="siarhe-draggable-list" id="mk-sortable-list" style="margin-bottom: 25px;">
        <?php
        $mk_html = [
            'mk_inst' => '<div class="siarhe-draggable-item" data-id="mk_inst" draggable="true"><span class="dashicons dashicons-menu siarhe-drag-handle"></span><label class="siarhe-toggle"><input type="checkbox" id="tt_mk_inst"><span class="siarhe-slider"></span></label><div><strong>Institución a la que pertenece</strong><br><small style="color: #64748b;">Ej: "IMSS", "ISSSTE", "SSA".</small></div></div>',
            'mk_clues' => '<div class="siarhe-draggable-item" data-id="mk_clues" draggable="true"><span class="dashicons dashicons-menu siarhe-drag-handle"></span><label class="siarhe-toggle"><input type="checkbox" id="tt_mk_clues"><span class="siarhe-slider"></span></label><div><strong>Clave CLUES</strong><br><small style="color: #64748b;">Identificador único oficial del establecimiento.</small></div></div>',
            'mk_tipo' => '<div class="siarhe-draggable-item" data-id="mk_tipo" draggable="true"><span class="dashicons dashicons-menu siarhe-drag-handle"></span><label class="siarhe-toggle"><input type="checkbox" id="tt_mk_tipo"><span class="siarhe-slider"></span></label><div><strong>Tipo y Tipología del Establecimiento</strong><br><small style="color: #64748b;">Ej: "Hospital General", "Centro de Salud".</small></div></div>',
            'mk_nivel' => '<div class="siarhe-draggable-item" data-id="mk_nivel" draggable="true"><span class="dashicons dashicons-menu siarhe-drag-handle"></span><label class="siarhe-toggle"><input type="checkbox" id="tt_mk_nivel"><span class="siarhe-slider"></span></label><div><strong>Nivel de Atención</strong><br><small style="color: #64748b;">Primer, Segundo o Tercer nivel.</small></div></div>',
            'mk_juris' => '<div class="siarhe-draggable-item" data-id="mk_juris" draggable="true"><span class="dashicons dashicons-menu siarhe-drag-handle"></span><label class="siarhe-toggle"><input type="checkbox" id="tt_mk_juris"><span class="siarhe-slider"></span></label><div><strong>Jurisdicción Sanitaria</strong><br><small style="color: #64748b;">Muestra la jurisdicción que la regula.</small></div></div>',
            'mk_mun' => '<div class="siarhe-draggable-item" data-id="mk_mun" draggable="true"><span class="dashicons dashicons-menu siarhe-drag-handle"></span><label class="siarhe-toggle"><input type="checkbox" id="tt_mk_mun"><span class="siarhe-slider"></span></label><div><strong>Ubicación Geográfica</strong><br><small style="color: #64748b;">Entidad, Municipio y Localidad.</small></div></div>',
            
            // ELEMENTO ESPECIAL: LÍNEA SEPARADORA
            'mk_separator' => '<div class="siarhe-draggable-item" data-id="mk_separator" draggable="true" style="background: #f1f5f9; border: 1px dashed #cbd5e1; justify-content: center; color: #64748b; font-weight: bold; padding: 6px 15px;"><span class="dashicons dashicons-menu siarhe-drag-handle" style="position: absolute; left: 15px;"></span><span style="letter-spacing: 2px;">--- LÍNEA SEPARADORA ---</span></div>'
        ];
        foreach ($mkOrder as $item_id) { if (isset($mk_html[$item_id])) echo $mk_html[$item_id]; }
        ?>
    </div>

    <h4 style="margin-top: 0; margin-bottom: 10px;">🎨 Diseño y Colores (Marcadores)</h4>
    <div class="siarhe-design-grid">
        <div class="siarhe-design-box"><label><strong>Color de Fondo</strong></label><br><input type="text" id="tt_mk_bg_color" value="<?php echo esc_attr($mkBgColor); ?>" class="siarhe-color-field" style="margin-top:5px;"></div>
        <div class="siarhe-design-box"><label><strong>Opacidad (Transparencia)</strong></label><br><input type="number" id="tt_mk_bg_opacity" value="<?php echo esc_attr($mkBgOpacity); ?>" min="10" max="100" style="width: 70px; margin-top:5px;"> %</div>
        <div class="siarhe-design-box"><label><strong>Color del Texto Base</strong></label><br><input type="text" id="tt_mk_text_color" value="<?php echo esc_attr($mkTextColor); ?>" class="siarhe-color-field" style="margin-top:5px;"></div>
    </div>

    <div style="display:flex; gap:20px; flex-wrap:wrap; background:#faf5ff; padding:15px; border:1px dashed #d8b4fe; border-radius:6px; margin-top: 15px;">
        <div style="flex: 1 1 200px;"> <label><strong>¿Qué dato destacar?</strong></label><br>
            <select id="tt_mk_highlight_var" style="margin-top:5px; width:100%;">
                <option value="none" <?php selected($mkHlVar, 'none'); ?>>Ninguno (Todo estándar)</option>
                <option value="mk_clues" <?php selected($mkHlVar, 'mk_clues'); ?>>Clave CLUES</option>
                <option value="mk_inst" <?php selected($mkHlVar, 'mk_inst'); ?>>Institución</option>
                <option value="mk_tipo" <?php selected($mkHlVar, 'mk_tipo'); ?>>Tipo y Tipología</option>
            </select>
        </div>
        <div style="flex: 1 1 200px;"> <label><strong>Color del Texto Destacado</strong></label><br>
            <input type="text" id="tt_mk_highlight_color" value="<?php echo esc_attr($mkHlColor); ?>" class="siarhe-color-field" style="margin-top:5px; width:100%;">
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputJson = document.getElementById('siarhe_tooltip_config');
    let configObj = {};
    try { configObj = JSON.parse(inputJson.value); } catch(e) {}

    // Asegurar que exista el array de orden
    if(!configObj.geo_order) configObj.geo_order = ['pob', 'abs', 'rate'];
    if(!configObj.mk_order) configObj.mk_order = ['mk_inst', 'mk_clues', 'mk_tipo', 'mk_nivel', 'mk_separator', 'mk_juris', 'mk_mun'];

    // Mapeo de IDs de HTML a Claves de Visibilidad del JSON
    const uiMap = {
        'tt_geo_pob': 'geo_pob', 'tt_geo_abs': 'geo_abs', 'tt_geo_rate': 'geo_rate',
        'tt_mk_inst': 'mk_inst', 'tt_mk_mun': 'mk_mun', 'tt_mk_clues': 'mk_clues',
        'tt_mk_tipo': 'mk_tipo', 'tt_mk_nivel': 'mk_nivel', 'tt_mk_juris': 'mk_juris'
    };

    const designInputs = {
        'tt_bg_color': 'bg_color', 'tt_bg_opacity': 'bg_opacity', 'tt_text_color': 'text_color',
        'tt_highlight_var': 'highlight_var', 'tt_highlight_color': 'highlight_color',
        'tt_mk_bg_color': 'mk_bg_color', 'tt_mk_bg_opacity': 'mk_bg_opacity', 'tt_mk_text_color': 'mk_text_color',
        'tt_mk_highlight_var': 'mk_highlight_var', 'tt_mk_highlight_color': 'mk_highlight_color'
    };

    // Función visual para animar el botón de guardar
    function triggerSave() {
        inputJson.value = JSON.stringify(configObj);
        inputJson.dispatchEvent(new Event('change', { bubbles: true }));

        const btnSubmit = document.querySelector('input[type="submit"]#submit');
        if (btnSubmit) {
            btnSubmit.style.boxShadow = '0 0 0 4px rgba(10, 102, 194, 0.4)';
            btnSubmit.style.transform = 'scale(1.05)';
            btnSubmit.style.transition = 'all 0.3s ease';
            setTimeout(() => { btnSubmit.style.transform = 'scale(1)'; }, 300);
        }
    }

    // 1. Inicializar botones UI (Toggles)
    for (const [uiId, jsonKey] of Object.entries(uiMap)) {
        const checkbox = document.getElementById(uiId);
        if (checkbox) {
            checkbox.checked = (configObj[jsonKey] !== false); 
            checkbox.addEventListener('change', () => {
                configObj[jsonKey] = checkbox.checked;
                triggerSave();
            });
        }
    }

    // 2. Inicializar Opciones de Diseño (Inputs manuales)
    for (const [uiId, jsonKey] of Object.entries(designInputs)) {
        const inputEl = document.getElementById(uiId);
        if (inputEl) {
            inputEl.addEventListener('change', () => {
                configObj[jsonKey] = inputEl.value;
                triggerSave();
            });
        }
    }

    // 🌟 FIX SUPREMO PARA GUARDAR COLORES DE WORDPRESS 🌟
    // Interceptamos el formulario entero justo antes de guardar en la base de datos
    // y recolectamos el valor FÍSICO que tengan todos los inputs.
    const parentForm = inputJson.closest('form');
    if (parentForm) {
        parentForm.addEventListener('submit', () => {
            // Recolectar diseño y colores
            for (const [uiId, jsonKey] of Object.entries(designInputs)) {
                const el = document.getElementById(uiId);
                if (el) {
                    configObj[jsonKey] = el.value;
                }
            }
            // Recolectar toggles
            for (const [uiId, jsonKey] of Object.entries(uiMap)) {
                const el = document.getElementById(uiId);
                if (el) {
                    configObj[jsonKey] = el.checked;
                }
            }
            // Inyectar el JSON definitivo y perfecto
            inputJson.value = JSON.stringify(configObj);
        });
    }

    // 3. LÓGICA DRAG & DROP MULTIPLE (Mapa y Marcadores)
    let draggedItem = null;

    document.querySelectorAll('.siarhe-draggable-list').forEach(list => {
        list.addEventListener('dragstart', (e) => {
            draggedItem = e.target.closest('.siarhe-draggable-item');
            setTimeout(() => draggedItem.classList.add('siarhe-drag-ghost'), 0);
        });

        list.addEventListener('dragend', () => {
            if(!draggedItem) return;
            draggedItem.classList.remove('siarhe-drag-ghost');
            
            const listId = list.getAttribute('id');
            const newOrder = [];
            list.querySelectorAll('.siarhe-draggable-item').forEach(item => {
                newOrder.push(item.getAttribute('data-id'));
            });
            
            if (listId === 'geo-sortable-list') configObj.geo_order = newOrder;
            if (listId === 'mk-sortable-list') configObj.mk_order = newOrder;
            
            draggedItem = null;
            triggerSave();
        });

        list.addEventListener('dragover', (e) => {
            e.preventDefault(); 
            if (draggedItem && draggedItem.parentElement !== list) return;

            const afterElement = getDragAfterElement(list, e.clientY);
            if (afterElement == null) {
                list.appendChild(draggedItem);
            } else {
                list.insertBefore(draggedItem, afterElement);
            }
        });
    });

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.siarhe-draggable-item:not(.siarhe-drag-ghost)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
});
</script>