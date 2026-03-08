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
            
            // INYECCIÓN CSS: Estilos para los Dropdowns y el nuevo Toggle de Colores
            if (!document.getElementById('siarhe-search-dropdown-styles')) {
                const style = document.createElement('style');
                style.id = 'siarhe-search-dropdown-styles';
                style.innerHTML = `
                    .siarhe-custom-select, .mc-field { position: relative; width: 100%; min-width: 250px; font-family: 'Roboto', sans-serif; }
                    .siarhe-cs-trigger, .mc-trigger { background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: #334155; min-height: 38px; box-sizing: border-box; font-size: 14px; transition: border-color 0.2s; }
                    .siarhe-cs-trigger:hover, .mc-trigger:hover { border-color: #94a3b8; }
                    .siarhe-cs-trigger::after { content: "▼"; font-size: 10px; color: #94a3b8; margin-left: 8px; }
                    .siarhe-cs-menu, .mc-menu { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); margin-top: 5px; z-index: 9999; display: none; flex-direction: column; max-height: 350px; overflow: hidden; }
                    .siarhe-cs-menu.open, .mc-menu.open { display: flex; }
                    .siarhe-cs-search { padding: 10px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; z-index: 2; }
                    .siarhe-cs-search input { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; font-size: 13px; outline: none; transition: all 0.2s; }
                    .siarhe-cs-search input:focus { border-color: #0ea5e9; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.2); }
                    .siarhe-cs-options { overflow-y: auto; padding: 4px 0; }
                    .siarhe-cs-option, .mc-option { padding: 8px 12px; cursor: pointer; color: #475569; font-size: 13px; display: flex; align-items: center; gap: 8px; transition: background 0.2s; line-height: 1.3;}
                    .siarhe-cs-option:hover, .mc-option:hover { background: #f1f5f9; color: #0f172a; }
                    .siarhe-cs-option.selected { font-weight: bold; color: #0284c7; background: #f0f9ff; border-left: 3px solid #0284c7; padding-left: 9px; }
                    .is-placeholder { color: #94a3b8 !important; }
                    
                    /* Estilos del Toggle Switch Monocromático */
                    .siarhe-mode-toggle { display: flex; align-items: center; gap: 8px; margin-left: auto; font-family: 'Roboto', sans-serif; font-size: 13px; color: #475569; }
                    .s-toggle-switch { position: relative; display: inline-block; width: 40px; height: 20px; }
                    .s-toggle-switch input { opacity: 0; width: 0; height: 0; }
                    .s-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 20px; }
                    .s-toggle-slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
                    .s-toggle-switch input:checked + .s-toggle-slider { background-color: #0284c7; }
                    .s-toggle-switch input:checked + .s-toggle-slider:before { transform: translateX(20px); }
                    @media (max-width: 767px) { .siarhe-mode-toggle { margin-left: 0; margin-top: 10px; width: 100%; justify-content: space-between; } }
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
                    // USO DE ETIQUETA CORTA EN EL BOTÓN PRINCIPAL
                    triggerInd.innerHTML = `<span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${info.label || info.fullLabel}</span>`;
                }
                
                // USO DE ETIQUETA LARGA EN LAS OPCIONES DESPLEGABLES
                opt.textContent = info.fullLabel;
                
                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                    state.currentMetric = key;
                    app.sortConfig = { key: 'tasa', direction: 'desc' };
                    
                    // USO DE ETIQUETA CORTA AL SELECCIONAR
                    triggerInd.innerHTML = `<span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${info.label || info.fullLabel}</span>`;
                    optionsContainerInd.querySelectorAll('.siarhe-cs-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    menuInd.classList.remove('open');
                    
                    onUpdate(); 
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
            // B) CONSTRUCCIÓN DE "MARCADORES" (AHORA DINÁMICOS)
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
                    searchBoxMc.addEventListener('click', e => e.stopPropagation());
                    const searchInputMc = document.createElement('input');
                    searchInputMc.type = 'text';
                    searchInputMc.placeholder = '🔍 Buscar marcador...';
                    searchBoxMc.appendChild(searchInputMc);
                    menuMc.appendChild(searchBoxMc);

                    const optionsContainerMc = document.createElement('div');
                    optionsContainerMc.className = 'siarhe-cs-options';

                    validMarkers.forEach(key => {
                        // 🌟 LECTURA DE ETIQUETA DINÁMICA
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
                // 🌟 LECTURA DE ETIQUETA DINÁMICA PARA EL LOADING
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
                            // 🌟 SISTEMA DE CACHÉ DE CSV (Evita descargas dobles)
                            let rawData;
                            if (state.rawCSVDataCache && state.rawCSVDataCache[url]) {
                                rawData = state.rawCSVDataCache[url];
                            } else {
                                rawData = await d3.csv(url);
                                if (!state.rawCSVDataCache) state.rawCSVDataCache = {};
                                state.rawCSVDataCache[url] = rawData;
                            }

                            // 🌟 MOTOR DE FILTRADO DINÁMICO
                            const config = state.markerLabels[type] || {};
                            const filtroCol = config.filtro_col ? config.filtro_col.toLowerCase().trim() : null;
                            const filtroVal = config.filtro_val ? config.filtro_val.toString().toLowerCase().trim() : null;

                            state.markersData[type] = rawData.map(d => {
                                const latText = app.utils.getColValue(d, ['latitud', 'lat']);
                                const lonText = app.utils.getColValue(d, ['longitud', 'lon']);
                                const cveNivel = app.utils.getColValue(d, ['cve_n_atencion', 'cve_ n_atencion', 'cve n atencion']);
                                const cveEntMarker = app.utils.getColValue(d, ['cve_ent', 'cve ent', 'entidad', 'clave_entidad']);
                                
                                return {
                                    clues: app.utils.getColValue(d, ['clues']),
                                    institucion: app.utils.getColValue(d, ['institución', 'institucion', 'clave_institucion']),
                                    nombre: app.utils.getColValue(d, ['nombre_unidad', 'nombre de la unidad', 'unidad']),
                                    municipio: app.utils.getColValue(d, ['municipio']),
                                    lat: parseFloat(latText),
                                    lon: parseFloat(lonText),
                                    cve_ent: cveEntMarker, 
                                    tipo_estab: app.utils.getColValue(d, ['tipo_establecimiento', 'tipo establecimiento']),
                                    tipologia: app.utils.getColValue(d, ['nombre_tipologia', 'nombre tipologia']),
                                    nivel_atencion: app.utils.getColValue(d, ['nivel_atencion', 'nivel atencion']),
                                    cve_nivel: cveNivel,
                                    jurisdiccion: app.utils.getColValue(d, ['jurisdiccion', 'jurisdicción']),
                                    estrato: app.utils.getColValue(d, ['estrato_unidad', 'estrato unidad']),
                                    tipo: type,
                                    _raw: d // 🌟 Guardar la fila cruda para el filtro dinámico
                                };
                            }).filter(d => {
                                const isValidCoord = !isNaN(d.lat) && !isNaN(d.lon) && d.lat !== 0 && d.lon !== 0;
                                if (!isValidCoord) return false;
                                
                                if (!state.isNacional && d.cve_ent) {
                                    let markerEnt = d.cve_ent.toString().padStart(2, '0');
                                    let currentEnt = container.dataset.cveEnt.toString().padStart(2, '0');
                                    if (markerEnt !== currentEnt) return false; 
                                }
                                
                                // 🌟 APLICACIÓN DEL FILTRO DINÁMICO
                                if (filtroCol && filtroVal) {
                                    const realColName = Object.keys(d._raw).find(k => k.toLowerCase().trim() === filtroCol);
                                    if (realColName) {
                                        const rowVal = (d._raw[realColName] || '').toString().toLowerCase().trim();
                                        if (rowVal !== filtroVal) return false; // Descartar si no coincide
                                    } else {
                                        return false; // Descartar si pide filtro y la columna no existe
                                    }
                                }
                                
                                return true; 
                            });
                            
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
            if (selected.length === 0) { 
                state.markerTrigger.innerHTML = `<span>Seleccionar...</span>`; 
                state.markerTrigger.classList.add('is-placeholder'); 
            } else {
                // 🌟 LECTURA DE ETIQUETA DINÁMICA
                const labels = selected.map(k => {
                    const mkData = state.markerLabels[k] || {};
                    return mkData.label || k;
                });
                state.markerTrigger.innerHTML = `<span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${labels.join(', ')}</span>`; 
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
                
                // LÓGICA DE NOMBRAMIENTO DEL ARCHIVO EXCEL 
                const anioNode = document.querySelector('.siarhe-dynamic-year');
                const anio = anioNode ? anioNode.innerText : new Date().getFullYear();
                
                // Extraer el lugar limpiando acentos
                const titleNode = container.querySelector('h2.siarhe-title'); 
                const entidadNombreRaw = titleNode ? titleNode.innerText.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '').trim() : "Mexico";
                const entidadClean = entidadNombreRaw.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, '_');
                
                // Extraer la etiqueta corta y limpiarla
                const labelRaw = metricInfo.label || metricInfo.fullLabel || 'Datos';
                const labelClean = labelRaw.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\./g, "").replace(/\s+/g, '_');
                
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement("a");
                
                a.download = `${labelClean}_${entidadClean}_${anio}.csv`; // Formato: EtiquetaCorta_Lugar_Anio.csv
                a.href = url; 
                a.style.display = "none"; 
                document.body.appendChild(a); 
                a.click(); 
                document.body.removeChild(a);
            });
        }
    };

})(window.SiarheDataViz);