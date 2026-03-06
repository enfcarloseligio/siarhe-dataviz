<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Obtener configuración o crear valores por defecto (todo encendido)
$tooltip_json = get_option( 'siarhe_tooltip_config', '' );
if ( empty($tooltip_json) ) {
    $defaults = [
        'geo_pob' => true, 'geo_abs' => true, 'geo_rate' => true,
        'mk_inst' => true, 'mk_mun' => true, 'mk_clues' => true,
        'mk_tipo' => true, 'mk_nivel' => true, 'mk_juris' => true
    ];
    $tooltip_json = wp_json_encode($defaults);
} else {
    $tooltip_json = wp_unslash($tooltip_json);
}
?>

<style>
    /* Estilos para Toggle Switches modernos */
    .siarhe-toggle { position: relative; display: inline-block; width: 44px; height: 24px; margin-right: 10px; vertical-align: middle; }
    .siarhe-toggle input { opacity: 0; width: 0; height: 0; }
    .siarhe-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 24px; }
    .siarhe-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    .siarhe-toggle input:checked + .siarhe-slider { background-color: #0A66C2; }
    .siarhe-toggle input:checked + .siarhe-slider:before { transform: translateX(20px); }
    .siarhe-tooltip-row { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
    .siarhe-tooltip-row:last-child { border-bottom: none; }
</style>

<div class="card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <h2 style="margin-top: 0;">ℹ️ Gestor de Contenido en Tooltips</h2>
    <p style="margin-bottom: 0;">Personaliza qué información deseas hacer visible en las tarjetas flotantes cuando los usuarios pasen el ratón sobre el mapa o sobre los marcadores de las unidades de salud. Apagar datos irrelevantes puede hacer la lectura más limpia.</p>
</div>

<input type="hidden" name="siarhe_tooltip_config" id="siarhe_tooltip_config" value="<?php echo esc_attr($tooltip_json); ?>">

<div style="display: flex; gap: 20px; flex-wrap: wrap;">

    <div class="card" style="flex: 1; min-width: 300px; padding: 20px; margin-top: 0;">
        <h3 style="margin-top: 0; border-bottom: 2px solid #0A66C2; padding-bottom: 10px; display: inline-block;">
            🗺️ Tooltip del Mapa (Regiones)
        </h3>
        
        <div class="siarhe-tooltip-row">
            <label class="siarhe-toggle"><input type="checkbox" id="tt_geo_pob"><span class="siarhe-slider"></span></label>
            <div>
                <strong>Población Total</strong><br>
                <small style="color: #64748b;">Muestra la población del estado o municipio.</small>
            </div>
        </div>

        <div class="siarhe-tooltip-row">
            <label class="siarhe-toggle"><input type="checkbox" id="tt_geo_abs"><span class="siarhe-slider"></span></label>
            <div>
                <strong>Valor Absoluto (Total de Elementos)</strong><br>
                <small style="color: #64748b;">Ej: "Enfermeras: 1,450".</small>
            </div>
        </div>

        <div class="siarhe-tooltip-row">
            <label class="siarhe-toggle"><input type="checkbox" id="tt_geo_rate"><span class="siarhe-slider"></span></label>
            <div>
                <strong>Valor Relativo (Tasa)</strong><br>
                <small style="color: #64748b;">Ej: "Tasa: 2.45 por cada mil hab".</small>
            </div>
        </div>
    </div>

    <div class="card" style="flex: 1; min-width: 300px; padding: 20px; margin-top: 0;">
        <h3 style="margin-top: 0; border-bottom: 2px solid #06B6D4; padding-bottom: 10px; display: inline-block;">
            📍 Tooltip de Marcadores (Clínicas)
        </h3>

        <div class="siarhe-tooltip-row">
            <label class="siarhe-toggle"><input type="checkbox" id="tt_mk_inst"><span class="siarhe-slider"></span></label>
            <div>
                <strong>Institución a la que pertenece</strong><br>
                <small style="color: #64748b;">Ej: "IMSS", "ISSSTE", "SSA".</small>
            </div>
        </div>

        <div class="siarhe-tooltip-row">
            <label class="siarhe-toggle"><input type="checkbox" id="tt_mk_mun"><span class="siarhe-slider"></span></label>
            <div>
                <strong>Municipio de Ubicación</strong><br>
                <small style="color: #64748b;">Muestra en qué municipio se encuentra la clínica.</small>
            </div>
        </div>

        <div class="siarhe-tooltip-row">
            <label class="siarhe-toggle"><input type="checkbox" id="tt_mk_clues"><span class="siarhe-slider"></span></label>
            <div>
                <strong>Clave CLUES</strong><br>
                <small style="color: #64748b;">Identificador único oficial del establecimiento.</small>
            </div>
        </div>

        <div class="siarhe-tooltip-row">
            <label class="siarhe-toggle"><input type="checkbox" id="tt_mk_tipo"><span class="siarhe-slider"></span></label>
            <div>
                <strong>Tipo y Tipología del Establecimiento</strong><br>
                <small style="color: #64748b;">Ej: "Hospital General", "Centro de Salud".</small>
            </div>
        </div>

        <div class="siarhe-tooltip-row">
            <label class="siarhe-toggle"><input type="checkbox" id="tt_mk_nivel"><span class="siarhe-slider"></span></label>
            <div>
                <strong>Nivel de Atención</strong><br>
                <small style="color: #64748b;">Primer, Segundo o Tercer nivel.</small>
            </div>
        </div>

        <div class="siarhe-tooltip-row">
            <label class="siarhe-toggle"><input type="checkbox" id="tt_mk_juris"><span class="siarhe-slider"></span></label>
            <div>
                <strong>Jurisdicción Sanitaria</strong><br>
                <small style="color: #64748b;">Muestra la jurisdicción que la regula.</small>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputJson = document.getElementById('siarhe_tooltip_config');
    let configObj = {};
    try { configObj = JSON.parse(inputJson.value); } catch(e) {}

    // Mapeo de IDs de HTML a Claves del JSON
    const uiMap = {
        'tt_geo_pob': 'geo_pob',
        'tt_geo_abs': 'geo_abs',
        'tt_geo_rate': 'geo_rate',
        'tt_mk_inst': 'mk_inst',
        'tt_mk_mun': 'mk_mun',
        'tt_mk_clues': 'mk_clues',
        'tt_mk_tipo': 'mk_tipo',
        'tt_mk_nivel': 'mk_nivel',
        'tt_mk_juris': 'mk_juris'
    };

    // 1. Inicializar botones UI basados en la Base de Datos
    for (const [uiId, jsonKey] of Object.entries(uiMap)) {
        const checkbox = document.getElementById(uiId);
        if (checkbox) {
            // Si no existe la llave en la base de datos, lo encendemos por defecto
            checkbox.checked = (configObj[jsonKey] !== false); 
            
            // 2. Escuchar cambios
            checkbox.addEventListener('change', () => {
                configObj[jsonKey] = checkbox.checked;
                inputJson.value = JSON.stringify(configObj);
                inputJson.dispatchEvent(new Event('change', { bubbles: true }));

                // Animación para recordar guardar
                const btnSubmit = document.querySelector('input[type="submit"]#submit');
                if (btnSubmit) {
                    btnSubmit.style.boxShadow = '0 0 0 4px rgba(10, 102, 194, 0.4)';
                    btnSubmit.style.transform = 'scale(1.05)';
                    btnSubmit.style.transition = 'all 0.3s ease';
                    setTimeout(() => { btnSubmit.style.transform = 'scale(1)'; }, 300);
                }
            });
        }
    }
});
</script>