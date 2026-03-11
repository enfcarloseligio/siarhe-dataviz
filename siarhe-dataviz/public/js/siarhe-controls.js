/**
 * SIARHE DataViz - Módulo de Controles (Controls)
 * ------------------------------------------------------------------
 * Gestiona la interfaz de usuario (UI), como los selectores desplegables
 * de indicadores (ahora con buscador), la lógica de activación de marcadores 
 * en memoria RAM y las funciones de exportación de datos (Excel y PNG).
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    app.controls = {
        
        renderMain: function(container, state, onUpdate, opts = { targetSelector: '.siarhe-controls-placeholder', showMarkers: true }) {
            
            // INYECCIÓN CSS: Estilos para los Dropdowns, el Toggle de Colores y el nuevo TOAST FLOTANTE 🌟
            if (!document.getElementById('siarhe-search-dropdown-styles')) {
                const style = document.createElement('style');
                style.id = 'siarhe-search-dropdown-styles';
                style.innerHTML = `
                    /* SOLUCIÓN FLEXBOX: Obliga a los contenedores a medir lo mismo en PC */
                    .siarhe-control-group { flex: 1 1 0%; min-width: 0; }
                    
                    .siarhe-custom-select, .mc-field { position: relative; width: 100%; font-family: 'Roboto', sans-serif; }
                    
                    /* TRUNCAMIENTO PERFECTO DEL TEXTO CON PUNTOS SUSPENSIVOS (...) */
                    .siarhe-cs-trigger, .mc-trigger { background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 12px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: #334155; height: 38px; box-sizing: border-box; font-size: 14px; transition: border-color 0.2s; overflow: hidden; width: 100%; }
                    .siarhe-cs-trigger > span, .mc-trigger > span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; text-align: left; line-height: 36px; padding-right: 10px; flex: 1; }
                    .siarhe-cs-trigger::after, .mc-trigger::after { content: "▼"; font-size: 10px; color: #94a3b8; flex-shrink: 0; }
                    .siarhe-cs-trigger:hover, .mc-trigger:hover { border-color: #94a3b8; }
                    
                    .siarhe-cs-menu, .mc-menu { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); margin-top: 5px; z-index: 9999; display: none; flex-direction: column; max-height: 350px; overflow: hidden; }
                    .siarhe-cs-menu.open, .mc-menu.open { display: flex; }
                    .siarhe-cs-search { padding: 10px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; z-index: 2; }
                    .siarhe-cs-search input { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; font-size: 13px; outline: none; transition: all 0.2s; }
                    .siarhe-cs-search input:focus { border-color: #0ea5e9; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.2); }
                    .siarhe-cs-options { overflow-y: auto; padding: 4px 0; }
                    .siarhe-cs-option, .mc-option { padding: 8px 12px; cursor: pointer; color: #475569; font-size: 13px; display: flex; align-items: center; gap: 8px; transition: background 0.2s; line-height: 1.3;}
                    .siarhe-cs-option:hover, .mc-option:hover { background: #f1f5f9; color: #0f172a; }
                    .siarhe-cs-option.selected { font-weight: bold; color: #0284c7; background: #f0f9ff; border-left: 3px solid #0284c7; padding-left: 9px; }
                    .is-placeholder span { color: #94a3b8 !important; }
                    
                    /* Estilos del Toggle Switch Monocromático */
                    .siarhe-mode-toggle { display: flex; align-items: center; gap: 8px; margin-left: auto; font-family: 'Roboto', sans-serif; font-size: 13px; color: #475569; }
                    .s-toggle-switch { position: relative; display: inline-block; width: 40px; height: 20px; }
                    .s-toggle-switch input { opacity: 0; width: 0; height: 0; }
                    .s-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 20px; }
                    .s-toggle-slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
                    .s-toggle-switch input:checked + .s-toggle-slider { background-color: #0284c7; }
                    .s-toggle-switch input:checked + .s-toggle-slider:before { transform: translateX(20px); }
                    
                    /* 🌟 ESTILOS DEL TOAST FLOTANTE (SUGERENCIA INTELIGENTE) 🌟 */
                    .siarhe-smart-toast {
                        position: absolute; top: 20px; right: 20px; z-index: 10000;
                        background: #fff; border-left: 4px solid #0ea5e9; border-radius: 6px;
                        box-shadow: 0 10px 25px rgba(0,0,0,0.15); padding: 15px;
                        display: flex; flex-direction: column; gap: 10px; 
                        font-family: 'Roboto', sans-serif; font-size: 13px;
                        width: 280px; max-width: calc(100% - 40px);
                        transform: translateX(120%); opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                        pointer-events: none; /* Ignora clics mientras está oculto */
                    }
                    .siarhe-smart-toast.show {
                        transform: translateX(0); opacity: 1; pointer-events: auto;
                    }
                    .siarhe-toast-header { display: flex; justify-content: space-between; align-items: center; color: #0f172a; font-size: 14px;}
                    .siarhe-toast-close { cursor: pointer; color: #94a3b8; border: none; background: none; font-size: 16px; line-height: 1; padding: 0; margin: 0; }
                    .siarhe-toast-close:hover { color: #d63638; }
                    .siarhe-toast-btn { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-weight: bold; text-align: center; transition: background 0.2s; align-self: flex-start; }
                    .siarhe-toast-btn:hover { background: #e0f2fe; }

                    @media (max-width: 767px) { 
                        /* CORRECCIÓN MÓVIL */
                        .siarhe-control-group { flex: none; width: 100%; }
                        .siarhe-mode-toggle { margin-left: 0; margin-top: 10px; width: 100%; justify-content: space-between; } 
                        .siarhe-smart-toast { top: auto; bottom: 20px; right: 50%; transform: translateX(50%) translateY(120%); }
                        .siarhe-smart-toast.show { transform: translateX(50%) translateY(0); }
                    }
                `;
                document.head.appendChild(style);
            }

            const ph = container.querySelector(opts.targetSelector);
            if (!ph) return; 
            ph.innerHTML = '';
            
            const wrapper = document.createElement('div'); 
            wrapper.className = 'siarhe-controls'; 

            document.addEventListener('click', () => {
                container.querySelectorAll('.siarhe-cs-menu, .mc-menu').forEach(m => m.classList.remove('open'));
            });

            // 🌟 INYECTAR TOAST AL CONTENEDOR PRINCIPAL
            let toastBox = container.querySelector('.siarhe-smart-toast');
            if (!toastBox) {
                toastBox = document.createElement('div');
                toastBox.className = 'siarhe-smart-toast';
                container.appendChild(toastBox);
            }

            let toastTimeout;
            const showSmartToast = (markerKey, markerLabel) => {
                toastBox.innerHTML = `
                    <div class="siarhe-toast-header">
                        <strong>💡 Sugerencia de análisis</strong>
                        <button class="siarhe-toast-close" title="Cerrar">✖</button>
                    </div>
                    <div style="color: #475569; line-height: 1.4;">
                        Existen datos de <strong>${markerLabel}</strong> vinculados a este indicador.
                    </div>
                    <button class="siarhe-toast-btn">📍 Mostrar en mapa</button>
                `;
                
                toastBox.querySelector('.siarhe-toast-close').onclick = () => toastBox.classList.remove('show');
                
                toastBox.querySelector('.siarhe-toast-btn').onclick = () => {
                    toastBox.classList.remove('show');
                    // Simular activación desde el checkbox oculto en el DOM
                    const chk = container.querySelector(`.mc-check[value="${markerKey}"]`);
                    if (chk && !chk.checked) {
                        chk.checked = true;
                        app.controls.toggleMarker(markerKey, state, container);
                    } else if (!chk) {
                        app.controls.toggleMarker(markerKey, state, container);
                    }
                };

                toastBox.classList.add('show');
                clearTimeout(toastTimeout);
                toastTimeout = setTimeout(() => { toastBox.classList.remove('show'); }, 8000);
            };

            const checkSpectrumLink = (metricKey) => {
                if (!opts.showMarkers) return; // Si no hay marcadores habilitados, no hacer nada.
                
                // Buscar si hay algún marcador de tipo espectro asociado a esta métrica
                const linkedMarkerKey = Object.keys(state.markerLabels).find(k => {
                    const mk = state.markerLabels[k];
                    return mk.tipo === 'espectro' && mk.espectro_pair === metricKey;
                });

                // Si existe y NO está activo actualmente, mostrar la sugerencia
                if (linkedMarkerKey && !state.activeMarkers.has(linkedMarkerKey)) {
                    const mkLabel = state.markerLabels[linkedMarkerKey].label || linkedMarkerKey;
                    showSmartToast(linkedMarkerKey, mkLabel);
                } else {
                    toastBox.classList.remove('show'); // Ocultar si cambia a uno sin enlace
                }
            };


            // ====================================================
            // A) CONSTRUCCIÓN DE "INDICADOR"
            // ====================================================
            const grpInd = document.createElement('div'); 
            grpInd.className = 'siarhe-control-group'; 
            grpInd.innerHTML = `<label>Indicador</label>`;

            const customSelectInd = document.createElement('div');
            customSelectInd.className = 'siarhe-custom-select';

            const triggerInd = document.createElement('div');
            triggerInd.className = 'siarhe-cs-trigger';

            const menuInd = document.createElement('div');
            menuInd.className = 'siarhe-cs-menu';

            const searchBoxInd = document.createElement('div');
            searchBoxInd.className = 'siarhe-cs-search';
            searchBoxInd.addEventListener('click', e => e.stopPropagation()); 
            const searchInputInd = document.createElement('input');
            searchInputInd.type = 'text';
            searchInputInd.placeholder = '🔍 Buscar indicador...';
            searchBoxInd.appendChild(searchInputInd);
            menuInd.appendChild(searchBoxInd);

            const optionsContainerInd = document.createElement('div');
            optionsContainerInd.className = 'siarhe-cs-options';

            Object.entries(state.metricas).forEach(([key, info]) => {
                const opt = document.createElement('div');
                opt.className = 'siarhe-cs-option';
                
                if (key === state.currentMetric) {
                    opt.classList.add('selected');
                    triggerInd.innerHTML = `<span>${info.label || info.fullLabel}</span>`;
                }
                
                opt.textContent = info.fullLabel;
                
                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                    state.currentMetric = key;
                    app.sortConfig = { key: 'tasa', direction: 'desc' };
                    
                    triggerInd.innerHTML = `<span>${info.label || info.fullLabel}</span>`;
                    optionsContainerInd.querySelectorAll('.siarhe-cs-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    menuInd.classList.remove('open');
                    
                    onUpdate(); 
                    
                    // 🌟 MAGIA: Verificar si hay un Espectro Vinculado después de actualizar 🌟
                    checkSpectrumLink(key);
                });
                
                optionsContainerInd.appendChild(opt);
            });

            menuInd.appendChild(optionsContainerInd);
            customSelectInd.append(triggerInd, menuInd);
            grpInd.appendChild(customSelectInd);
            wrapper.appendChild(grpInd);

            searchInputInd.addEventListener('keyup', (e) => {
                const val = e.target.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, ""); 
                optionsContainerInd.querySelectorAll('.siarhe-cs-option').forEach(o => {
                    const text = o.textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                    o.style.display = text.includes(val) ? '' : 'none';
                });
            });

            triggerInd.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = menuInd.classList.contains('open');
                container.querySelectorAll('.siarhe-cs-menu, .mc-menu').forEach(m => m.classList.remove('open'));
                
                if (!isOpen) {
                    menuInd.classList.add('open');
                    searchInputInd.value = '';
                    optionsContainerInd.querySelectorAll('.siarhe-cs-option').forEach(o => o.style.display = '');
                    setTimeout(() => searchInputInd.focus(), 50); 
                }
            });


            // ====================================================
            // B) CONSTRUCCIÓN DE "MARCADORES" (CON BOTÓN DE LIMPIAR)
            // ====================================================
            if (opts.showMarkers) {
                const validMarkers = Object.keys(state.markerUrls).filter(k => state.markerUrls[k]);
                
                if (validMarkers.length > 0) {
                    const grpMarc = document.createElement('div'); 
                    grpMarc.className = 'siarhe-control-group'; 
                    grpMarc.innerHTML = `<label>Marcadores</label>`;
                    
                    const field = document.createElement('div'); 
                    field.className = 'mc-field';
                    
                    const triggerMc = document.createElement('div'); 
                    triggerMc.className = 'mc-trigger is-placeholder'; 
                    triggerMc.innerHTML = `<span>Seleccionar...</span>`;
                    state.markerTrigger = triggerMc; 
                    
                    const menuMc = document.createElement('div'); 
                    menuMc.className = 'mc-menu';
                    
                    const searchBoxMc = document.createElement('div');
                    searchBoxMc.className = 'siarhe-cs-search';
                    searchBoxMc.style.display = 'flex';
                    searchBoxMc.style.gap = '8px';
                    searchBoxMc.addEventListener('click', e => e.stopPropagation());
                    
                    const searchInputMc = document.createElement('input');
                    searchInputMc.type = 'text';
                    searchInputMc.placeholder = '🔍 Buscar marcador...';
                    searchInputMc.style.flex = '1';

                    const btnClearMc = document.createElement('button');
                    btnClearMc.type = 'button';
                    btnClearMc.innerHTML = '✖ Limpiar';
                    btnClearMc.style.cssText = 'background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; border-radius: 4px; padding: 0 10px; cursor: pointer; font-size: 12px; font-weight: bold; display: none; white-space: nowrap; height: 33px; line-height: 31px;';
                    btnClearMc.title = 'Desmarcar todas las capas';
                    
                    btnClearMc.addEventListener('click', (e) => {
                        e.stopPropagation();
                        state.activeMarkers.clear();
                        optionsContainerMc.querySelectorAll('.mc-check').forEach(chk => chk.checked = false);
                        if (app.map) app.map.updateMarkers(state);
                        if (app.map && app.map.renderMarkerLegend) app.map.renderMarkerLegend(state);
                        app.controls.updateMarkerDropdownText(state);
                    });

                    searchBoxMc.appendChild(searchInputMc);
                    searchBoxMc.appendChild(btnClearMc); 
                    menuMc.appendChild(searchBoxMc);

                    const optionsContainerMc = document.createElement('div');
                    optionsContainerMc.className = 'siarhe-cs-options';

                    validMarkers.forEach(key => {
                        const mkData = state.markerLabels[key] || {};
                        const label = mkData.label || key;
                        
                        const opt = document.createElement('div'); 
                        opt.className = 'mc-option';
                        opt.innerHTML = `<input type="checkbox" class="mc-check" value="${key}"> <span>${label}</span>`;
                        
                        opt.addEventListener('click', (e) => {
                            if (e.target.tagName !== 'INPUT') {
                                const chk = opt.querySelector('input'); 
                                chk.checked = !chk.checked; 
                                app.controls.toggleMarker(key, state, container);
                            }
                        });
                        opt.querySelector('input').addEventListener('click', (e) => { 
                            e.stopPropagation(); 
                            app.controls.toggleMarker(key, state, container); 
                        });
                        optionsContainerMc.appendChild(opt);
                    });
                    
                    menuMc.appendChild(optionsContainerMc);
                    
                    searchInputMc.addEventListener('keyup', (e) => {
                        const val = e.target.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                        optionsContainerMc.querySelectorAll('.mc-option').forEach(o => {
                            const text = o.textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                            o.style.display = text.includes(val) ? '' : 'none';
                        });
                    });

                    triggerMc.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const isOpen = menuMc.classList.contains('open');
                        container.querySelectorAll('.siarhe-cs-menu, .mc-menu').forEach(m => m.classList.remove('open'));
                        
                        if (!isOpen) {
                            menuMc.classList.add('open');
                            searchInputMc.value = '';
                            optionsContainerMc.querySelectorAll('.mc-option').forEach(o => o.style.display = '');
                            setTimeout(() => searchInputMc.focus(), 50);
                        }
                    });

                    field.append(triggerMc, menuMc); 
                    grpMarc.appendChild(field); 
                    wrapper.appendChild(grpMarc);
                }
            }

            // ====================================================
            // C) TOGGLE DE MODO DE COLOR (Monocromático vs Cuartiles)
            // ====================================================
            if (opts.targetSelector === '.siarhe-controls-placeholder') { 
                const colorToggleContainer = document.createElement('div');
                colorToggleContainer.className = 'siarhe-mode-toggle';
                
                colorToggleContainer.innerHTML = `
                    <span>Cuartiles</span>
                    <label class="s-toggle-switch">
                        <input type="checkbox" id="siarhe_color_mode_toggle" ${state.colorMode === 'mono' ? 'checked' : ''}>
                        <span class="s-toggle-slider"></span>
                    </label>
                    <span>Monocromático</span>
                `;

                const checkbox = colorToggleContainer.querySelector('input');
                checkbox.addEventListener('change', (e) => {
                    state.colorMode = e.target.checked ? 'mono' : 'quartiles';
                    if (app.map) app.map.updateVisuals(container, state);
                });

                wrapper.appendChild(colorToggleContainer);
            }
            
            ph.appendChild(wrapper);
        },

        toggleMarker: async function(type, state, container) {
            const loading = container.querySelector('.siarhe-loading-overlay');
            if (loading) {
                const mkData = state.markerLabels[type] || {};
                const label = mkData.label || type;
                
                loading.querySelector('p').textContent = `Procesando ${label}...`;
                loading.style.position = 'absolute'; loading.style.top = '0'; loading.style.left = '0';
                loading.style.width = '100%'; loading.style.height = '100%';
                loading.style.background = 'rgba(248, 250, 252, 0.9)';
                loading.style.display = 'flex'; loading.style.flexDirection = 'column';
                loading.style.justifyContent = 'center'; loading.style.alignItems = 'center';
                loading.style.zIndex = '100';
            }

            await new Promise(r => setTimeout(r, 80)); 

            if (state.activeMarkers.has(type)) { 
                state.activeMarkers.delete(type); 
            } else {
                state.activeMarkers.add(type);
                
                if (!state.markersData[type]) {
                    const url = state.markerUrls[type];
                    if (url) {
                        try {
                            let rawData;
                            if (state.rawCSVDataCache && state.rawCSVDataCache[url]) {
                                rawData = state.rawCSVDataCache[url];
                            } else {
                                rawData = await d3.csv(url);
                                if (!state.rawCSVDataCache) state.rawCSVDataCache = {};
                                state.rawCSVDataCache[url] = rawData;
                            }

                            const config = state.markerLabels[type] || {};
                            const filtroCol = config.filtro_col ? config.filtro_col.toLowerCase().trim() : null;
                            const filtroVal = config.filtro_val ? config.filtro_val.toString().toLowerCase().trim() : null;
                            
                            const agruparCol = config.agrupar_col ? config.agrupar_col.toLowerCase().trim() : null;
                            const reglas = config.reglas_tooltip || [];

                            let processedData = [];

                            const filteredData = rawData.filter(d => {
                                const latText = app.utils.getColValue(d, ['latitud', 'lat']);
                                const lonText = app.utils.getColValue(d, ['longitud', 'lon']);
                                const lat = parseFloat(latText);
                                const lon = parseFloat(lonText);

                                const isValidCoord = !isNaN(lat) && !isNaN(lon) && lat !== 0 && lon !== 0;
                                if (!isValidCoord) return false;
                                
                                const cveEntMarker = app.utils.getColValue(d, ['cve_ent', 'cve ent', 'entidad', 'clave_entidad']);
                                if (!state.isNacional && cveEntMarker) {
                                    let markerEnt = cveEntMarker.toString().padStart(2, '0');
                                    let currentEnt = container.dataset.cveEnt.toString().padStart(2, '0');
                                    if (markerEnt !== currentEnt) return false; 
                                }
                                
                                if (filtroCol && filtroVal) {
                                    const realColName = Object.keys(d).find(k => k.toLowerCase().trim() === filtroCol);
                                    if (realColName) {
                                        const rowVal = (d[realColName] || '').toString().toLowerCase().trim();
                                        if (rowVal !== filtroVal) return false; 
                                    } else {
                                        return false; 
                                    }
                                }
                                return true; 
                            });

                            if (agruparCol) {
                                const mapGroup = new Map();
                                
                                filteredData.forEach(d => {
                                    const realAgruparCol = Object.keys(d).find(k => k.toLowerCase().trim() === agruparCol);
                                    if (!realAgruparCol) return; 

                                    const groupKey = (d[realAgruparCol] || '').toString().trim();
                                    if (!groupKey) return;

                                    if (!mapGroup.has(groupKey)) {
                                        const lat = parseFloat(app.utils.getColValue(d, ['latitud', 'lat']));
                                        const lon = parseFloat(app.utils.getColValue(d, ['longitud', 'lon']));
                                        const cveNivel = app.utils.getColValue(d, ['cve_n_atencion', 'cve_ n_atencion', 'cve n atencion']);
                                        const cveEntMarker = app.utils.getColValue(d, ['cve_ent', 'cve ent', 'entidad', 'clave_entidad']);

                                        let baseObj = {
                                            clues: app.utils.getColValue(d, ['clues']),
                                            institucion: app.utils.getColValue(d, ['institución', 'institucion', 'clave_institucion']),
                                            nombre: app.utils.getColValue(d, ['nombre_unidad', 'nombre de la unidad', 'unidad']),
                                            municipio: app.utils.getColValue(d, ['municipio']),
                                            lat: lat, lon: lon,
                                            cve_ent: cveEntMarker, 
                                            tipo_estab: app.utils.getColValue(d, ['tipo_establecimiento', 'tipo establecimiento']),
                                            tipologia: app.utils.getColValue(d, ['nombre_tipologia', 'nombre tipologia']),
                                            nivel_atencion: app.utils.getColValue(d, ['nivel_atencion', 'nivel atencion']),
                                            cve_nivel: cveNivel,
                                            jurisdiccion: app.utils.getColValue(d, ['jurisdiccion', 'jurisdicción']),
                                            estrato: app.utils.getColValue(d, ['estrato_unidad', 'estrato unidad']),
                                            tipo: type,
                                            _raw: d,
                                            _agrupados_total: 0,
                                            _conteo_reglas: {}
                                        };

                                        reglas.forEach(r => { baseObj._conteo_reglas[r.label] = 0; });
                                        mapGroup.set(groupKey, baseObj);
                                    }

                                    const targetObj = mapGroup.get(groupKey);
                                    targetObj._agrupados_total += 1;

                                    reglas.forEach(r => {
                                        const realRuleCol = Object.keys(d).find(k => k.toLowerCase().trim() === r.col.toLowerCase().trim());
                                        if (realRuleCol) {
                                            const rowVal = (d[realRuleCol] || '').toString().toLowerCase().trim();
                                            const targetVal = r.val.toString().toLowerCase().trim();
                                            if (rowVal === targetVal) {
                                                targetObj._conteo_reglas[r.label] += 1;
                                            }
                                        }
                                    });
                                });

                                processedData = Array.from(mapGroup.values());

                            } else {
                                processedData = filteredData.map(d => {
                                    const lat = parseFloat(app.utils.getColValue(d, ['latitud', 'lat']));
                                    const lon = parseFloat(app.utils.getColValue(d, ['longitud', 'lon']));
                                    const cveNivel = app.utils.getColValue(d, ['cve_n_atencion', 'cve_ n_atencion', 'cve n atencion']);
                                    const cveEntMarker = app.utils.getColValue(d, ['cve_ent', 'cve ent', 'entidad', 'clave_entidad']);
                                    
                                    return {
                                        clues: app.utils.getColValue(d, ['clues']),
                                        institucion: app.utils.getColValue(d, ['institución', 'institucion', 'clave_institucion']),
                                        nombre: app.utils.getColValue(d, ['nombre_unidad', 'nombre de la unidad', 'unidad']),
                                        municipio: app.utils.getColValue(d, ['municipio']),
                                        lat: lat, lon: lon,
                                        cve_ent: cveEntMarker, 
                                        tipo_estab: app.utils.getColValue(d, ['tipo_establecimiento', 'tipo establecimiento']),
                                        tipologia: app.utils.getColValue(d, ['nombre_tipologia', 'nombre tipologia']),
                                        nivel_atencion: app.utils.getColValue(d, ['nivel_atencion', 'nivel atencion']),
                                        cve_nivel: cveNivel,
                                        jurisdiccion: app.utils.getColValue(d, ['jurisdiccion', 'jurisdicción']),
                                        estrato: app.utils.getColValue(d, ['estrato_unidad', 'estrato unidad']),
                                        tipo: type,
                                        _raw: d
                                    };
                                });
                            }

                            state.markersData[type] = processedData;
                            
                        } catch(e) { console.error(`[SIARHE] Error cargando marcadores ${type}:`, e); }
                    }
                }
            }
            
            if (app.map) app.map.updateMarkers(state);
            app.controls.updateMarkerDropdownText(state);

            await new Promise(r => setTimeout(r, 100)); 
            if (loading) loading.style.display = 'none';
        },

        updateMarkerDropdownText: function(state) {
            if (!state.markerTrigger) return;
            const selected = Array.from(state.activeMarkers);
            
            const clearBtn = state.markerTrigger.parentElement.querySelector('.siarhe-cs-search button');
            if (clearBtn) {
                clearBtn.style.display = selected.length > 0 ? 'block' : 'none';
            }

            if (selected.length === 0) { 
                state.markerTrigger.innerHTML = `<span>Seleccionar...</span>`; 
                state.markerTrigger.classList.add('is-placeholder'); 
            } else {
                const labels = selected.map(k => {
                    const mkData = state.markerLabels[k] || {};
                    return mkData.label || k;
                });
                state.markerTrigger.innerHTML = `<span>${labels.join(', ')}</span>`; 
                state.markerTrigger.classList.remove('is-placeholder');
            }
        },

        setupActionButtons: function(container, state) {
            const btnLabels = container.querySelector('.siarhe-btn-toggle-labels');
            const btnPng = container.querySelector('.siarhe-btn-download-png');
            
            if (btnPng) {
                btnPng.addEventListener('click', (e) => { 
                    e.preventDefault(); 
                    if (app.map) app.map.downloadMapAsPNG(container, state, false); 
                });
            }
            if (btnLabels) {
                btnLabels.addEventListener('click', (e) => { 
                    e.preventDefault(); 
                    if (app.map) app.map.downloadMapAsPNG(container, state, true); 
                });
            }
        },

        setupExcelExport: function(container, state) {
            const btnExcel = container.querySelector('.siarhe-btn-download-excel');
            if (!btnExcel) return;

            btnExcel.addEventListener('click', (e) => {
                e.preventDefault();
                let csvContent = '\uFEFF';
                const colEntidad = state.isNacional ? 'Entidad Federativa' : 'Municipio';
                csvContent += `${colEntidad},Población,Enfermeras,Tasa\n`;
                
                const metricInfo = state.metricas[state.currentMetric];
                const mKey = state.currentMetric; 
                const pKey = metricInfo.pair || mKey; 
                const isPob = (mKey === 'poblacion');
                
                const enfDataKey = isPob ? null : pKey; 
                const tasaDataKey = isPob ? null : ((metricInfo.tipo === 'tasa') ? mKey : app.utils.findRateKeyForAbsolute(pKey, state.metricas));
                
                const getVal = (r, colId) => {
                    if (colId === 'estado') return r.estado;
                    if (colId === 'poblacion') return r.poblacion;
                    if (colId === 'enfermeras') return isPob ? null : r[enfDataKey];
                    if (colId === 'tasa') return isPob ? null : r[tasaDataKey];
                    return null;
                };

                const rows = state.csvData.filter(r => !r.isTotal && !r.isSpecial).sort((a,b) => {
                    const va = getVal(a, app.sortConfig.key); 
                    const vb = getVal(b, app.sortConfig.key);
                    if (va === null && vb !== null) return 1; 
                    if (vb === null && va !== null) return -1;
                    if (va === null && vb === null) return 0;
                    if (va < vb) return app.sortConfig.direction === 'asc' ? -1 : 1;
                    if (va > vb) return app.sortConfig.direction === 'asc' ? 1 : -1;
                    return 0;
                });
                
                const fixed = state.csvData.filter(r => r.isTotal || r.isSpecial);
                
                [...rows, ...fixed].forEach(r => {
                    const estado = `"${r.estado}"`; 
                    const pob = r.poblacion || 0;
                    
                    let enfStr = '-';
                    if (!isPob && r[enfDataKey] !== null && r[enfDataKey] !== undefined) {
                        enfStr = r[enfDataKey];
                    }
                    
                    let tasaStr = '-';
                    if (!isPob && r[tasaDataKey] !== null && r[tasaDataKey] !== undefined) {
                        tasaStr = r[tasaDataKey].toFixed(2);
                    }
                    
                    csvContent += `${estado},${pob},${enfStr},${tasaStr}\n`;
                });
                
                const anioNode = document.querySelector('.siarhe-dynamic-year');
                const anio = anioNode ? anioNode.innerText : new Date().getFullYear();
                
                const titleNode = container.querySelector('h2.siarhe-title'); 
                const entidadNombreRaw = titleNode ? titleNode.innerText.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '').trim() : "Mexico";
                const entidadClean = entidadNombreRaw.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, '_');
                
                const labelRaw = metricInfo.label || metricInfo.fullLabel || 'Datos';
                const labelClean = labelRaw.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\./g, "").replace(/\s+/g, '_');
                
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement("a");
                
                a.download = `${labelClean}_${entidadClean}_${anio}.csv`; 
                a.href = url; 
                a.style.display = "none"; 
                document.body.appendChild(a); 
                a.click(); 
                document.body.removeChild(a);
            });
        }
    };

})(window.SiarheDataViz);