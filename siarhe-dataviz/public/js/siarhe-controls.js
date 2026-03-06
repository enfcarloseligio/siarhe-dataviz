/**
 * SIARHE DataViz - Módulo de Controles (Controls)
 * ------------------------------------------------------------------
 * Gestiona la interfaz de usuario (UI), como los selectores desplegables
 * de indicadores, la lógica de activación de marcadores en memoria RAM
 * y las funciones de exportación de datos (Excel y botones de PNG).
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    app.controls = {
        
        // ==========================================
        // 1. RENDERIZADO DE SELECTORES PRINCIPALES
        // ==========================================
        
        /**
         * Construye el menú de Indicadores y el de Marcadores (si aplica)
         * y los inyecta en el contenedor designado.
         */
        renderMain: function(container, state, onUpdate, opts = { targetSelector: '.siarhe-controls-placeholder', showMarkers: true }) {
            const ph = container.querySelector(opts.targetSelector);
            if (!ph) return; 
            ph.innerHTML = '';
            
            const wrapper = document.createElement('div'); 
            wrapper.className = 'siarhe-controls'; 

            // --- Selector de Indicador (Métrica) ---
            const grpInd = document.createElement('div'); 
            grpInd.className = 'siarhe-control-group'; 
            grpInd.innerHTML = `<label>Indicador</label>`;
            const selInd = document.createElement('select'); 
            selInd.className = 'siarhe-metric-select'; 
            
            Object.entries(state.metricas).forEach(([key, info]) => {
                const opt = document.createElement('option'); 
                opt.value = key; 
                opt.textContent = info.fullLabel; 
                if (key === state.currentMetric) opt.selected = true; 
                selInd.appendChild(opt);
            });
            
            selInd.onchange = (e) => { 
                state.currentMetric = e.target.value; 
                // Reiniciar ordenamiento al cambiar de métrica
                app.sortConfig = { key: 'tasa', direction: 'desc' }; 
                onUpdate(); 
            };
            grpInd.appendChild(selInd); 
            wrapper.appendChild(grpInd);

            // --- Selector de Marcadores Dinámico ---
            if (opts.showMarkers) {
                const validMarkers = Object.keys(state.markerUrls).filter(k => state.markerUrls[k]);
                
                if (validMarkers.length > 0) {
                    const grpMarc = document.createElement('div'); 
                    grpMarc.className = 'siarhe-control-group'; 
                    grpMarc.innerHTML = `<label>Marcadores</label>`;
                    
                    const field = document.createElement('div'); 
                    field.className = 'mc-field';
                    
                    const trigger = document.createElement('div'); 
                    trigger.className = 'mc-trigger is-placeholder'; 
                    trigger.textContent = 'Seleccionar...';
                    state.markerTrigger = trigger; 
                    
                    const menu = document.createElement('div'); 
                    menu.className = 'mc-menu';
                    
                    validMarkers.forEach(key => {
                        const label = app.constants.MARCADOR_NOMBRES[key] || key;
                        const opt = document.createElement('div'); 
                        opt.className = 'mc-option';
                        opt.innerHTML = `<input type="checkbox" class="mc-check" value="${key}"> ${label}`;
                        
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
                        menu.appendChild(opt);
                    });
                    
                    trigger.onclick = (e) => { e.stopPropagation(); menu.classList.toggle('open'); };
                    document.addEventListener('click', () => menu.classList.remove('open'));
                    field.append(trigger, menu); 
                    grpMarc.appendChild(field); 
                    wrapper.appendChild(grpMarc);
                }
            }
            ph.appendChild(wrapper);
        },

        /**
         * Descarga y cachea en RAM las bases masivas de establecimientos
         * para evitar colapsar la red.
         */
        toggleMarker: async function(type, state, container) {
            const loading = container.querySelector('.siarhe-loading-overlay');
            if (loading) {
                loading.querySelector('p').textContent = `Procesando ${app.constants.MARCADOR_NOMBRES[type]}...`;
                loading.style.position = 'absolute'; loading.style.top = '0'; loading.style.left = '0';
                loading.style.width = '100%'; loading.style.height = '100%';
                loading.style.background = 'rgba(248, 250, 252, 0.9)';
                loading.style.display = 'flex'; loading.style.flexDirection = 'column';
                loading.style.justifyContent = 'center'; loading.style.alignItems = 'center';
                loading.style.zIndex = '100';
            }

            // Pausa forzada para permitir que el DOM renderice el Overlay de Carga
            await new Promise(r => setTimeout(r, 80)); 

            if (state.activeMarkers.has(type)) { 
                state.activeMarkers.delete(type); 
            } else {
                state.activeMarkers.add(type);
                
                if (!state.markersData[type]) {
                    const url = state.markerUrls[type];
                    if (url) {
                        try {
                            let rawData = state.rawEstabData;

                            if (type.startsWith('ESTAB_') && !rawData) {
                                rawData = await d3.csv(url);
                                state.rawEstabData = rawData; 
                            } else if (!type.startsWith('ESTAB_')) {
                                rawData = await d3.csv(url);
                            }

                            const nivelTarget = type.replace('ESTAB_', ''); 

                            state.markersData[type] = rawData.map(d => {
                                const latText = app.utils.getColValue(d, ['latitud', 'lat']);
                                const lonText = app.utils.getColValue(d, ['longitud', 'lon']);
                                const cveNivel = app.utils.getColValue(d, ['cve_n_atencion', 'cve_ n_atencion', 'cve n atencion']);
                                const cveEntMarker = app.utils.getColValue(d, ['cve_ent', 'cve ent', 'entidad', 'clave_entidad']);
                                
                                return {
                                    clues: app.utils.getColValue(d, ['clues']),
                                    institucion: app.utils.getColValue(d, ['institución', 'institucion']),
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
                                    tipo: type
                                };
                            }).filter(d => {
                                const isValidCoord = !isNaN(d.lat) && !isNaN(d.lon) && d.lat !== 0 && d.lon !== 0;
                                if (!isValidCoord) return false;
                                
                                if (!state.isNacional && d.cve_ent) {
                                    let markerEnt = d.cve_ent.toString().padStart(2, '0');
                                    let currentEnt = container.dataset.cveEnt.toString().padStart(2, '0');
                                    if (markerEnt !== currentEnt) return false; 
                                }
                                
                                if (type.startsWith('ESTAB_')) {
                                    return d.cve_nivel === nivelTarget;
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
                state.markerTrigger.textContent = 'Seleccionar...'; 
                state.markerTrigger.classList.add('is-placeholder'); 
            } else {
                const labels = selected.map(k => app.constants.MARCADOR_NOMBRES[k] || k);
                state.markerTrigger.textContent = labels.join(', '); 
                state.markerTrigger.classList.remove('is-placeholder');
            }
        },

        // ==========================================
        // 2. EVENTOS DE EXPORTACIÓN Y BOTONES
        // ==========================================
        
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
                let csvContent = '\uFEFF'; // BOM para forzar UTF-8 en Excel
                const colEntidad = state.isNacional ? 'Entidad Federativa' : 'Municipio';
                csvContent += `${colEntidad},Población,Enfermeras,Tasa\n`;
                
                const mKey = state.currentMetric; 
                const pKey = state.metricas[mKey].pair || mKey; 
                const isPob = (mKey === 'poblacion');
                
                const enfDataKey = isPob ? null : pKey; 
                const tasaDataKey = isPob ? null : ((state.metricas[mKey].tipo === 'tasa') ? mKey : app.utils.findRateKeyForAbsolute(pKey, state.metricas));
                
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
                    
                    // Ordenamiento seguro para Nulos
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
                    
                    // Formateo para exportación de celdas nulas
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
                
                // Creación y descarga del BLOB
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement("a");
                const anioNode = document.querySelector('.siarhe-dynamic-year');
                const anio = anioNode ? anioNode.innerText : new Date().getFullYear();
                const slug = container.dataset.slug || 'datos';
                const metricSlug = state.currentMetric.replace(/_/g, '-');
                a.download = `SIARHE_${slug}_${metricSlug}_${anio}.csv`;
                a.href = url; 
                a.style.display = "none"; 
                document.body.appendChild(a); 
                a.click(); 
                document.body.removeChild(a);
            });
        }
    };

})(window.SiarheDataViz);