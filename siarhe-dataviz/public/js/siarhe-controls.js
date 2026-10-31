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
            
            const isTableOnly = (opts.targetSelector === '.siarhe-table-controls-placeholder');


            const ph = container.querySelector(opts.targetSelector);
            if (!ph) return; 
            ph.innerHTML = '';
            
            const wrapper = document.createElement('div'); 
            wrapper.className = 'siarhe-controls-layout'; 
            
            if (isTableOnly) {
                wrapper.innerHTML = `
                    <div class="siarhe-controls-row">
                        <div class="siarhe-control-group" id="c-indicador"><label>Indicador de la Tabla</label></div>
                    </div>
                `;
            } else {
                wrapper.innerHTML = `
                    <div class="siarhe-controls-row">
                        <div class="siarhe-control-group" id="c-escala"><label>Estilo de Mapa</label></div>
                        <div class="siarhe-control-group siarhe-control-group--action" id="c-personalizar">
                            <button type="button" class="siarhe-btn siarhe-palette-btn" aria-expanded="false" title="Cambiar los colores del mapa (solo en tu navegador)">
                                <svg class="siarhe-reset-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.3-.5-.8-.5-1.3 0-1.1.9-2 2-2h2.4a3.6 3.6 0 0 0 3.6-3.6C21 6.6 17 3 12 3z"/><circle cx="7.5" cy="11.5" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16.5" cy="11.5" r="1"/></svg>
                                <span>Personalizar</span>
                            </button>
                        </div>
                        <div class="siarhe-control-group" id="c-vista"><label>Vista Geográfica</label></div>
                        <div class="siarhe-control-group siarhe-control-group--action" id="c-reset">
                            <button type="button" class="siarhe-btn siarhe-reset-btn" title="Volver a la configuración inicial">
                                <svg class="siarhe-reset-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 3 3 9 9 9"/></svg>
                                <span>Restablecer</span>
                            </button>
                        </div>
                    </div>
                    <div class="siarhe-controls-row">
                        <div class="siarhe-control-group" id="c-indicador"><label>Indicador Principal</label></div>
                        <div class="siarhe-control-group" id="c-posicion"><label>Marcadores (Posición)</label></div>
                        <div class="siarhe-control-group" id="c-espectro"><label>Marcadores (Densidad)</label></div>
                    </div>
                `;

                const closeBtn = document.createElement('button');
                closeBtn.className = 'siarhe-close-controls-btn';
                closeBtn.innerHTML = '✖';
                closeBtn.title = 'Ocultar panel';
                wrapper.appendChild(closeBtn);
                
                closeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    wrapper.classList.add('is-hidden');
                    const mapDiv = container.querySelector('.siarhe-map-container');
                    // 🌟 INYECTA LA CLASE MÁGICA PARA QUE APAREZCA 🎛️
                    if (mapDiv) mapDiv.classList.add('controls-are-hidden');
                });
            }

            document.addEventListener('click', () => {
                container.querySelectorAll('.siarhe-cs-menu, .mc-menu').forEach(m => m.classList.remove('open'));
            });

            let toastBox = null;
            if (!isTableOnly) {
                toastBox = container.querySelector('.siarhe-smart-toast');
                if (!toastBox) {
                    toastBox = document.createElement('div');
                    toastBox.className = 'siarhe-smart-toast';
                    container.appendChild(toastBox);
                }
            }

            let toastTimeout;
            const showSmartToast = (markerKey, markerLabel) => {
                if (!toastBox) return;
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
                if (!opts.showMarkers || isTableOnly || !toastBox) return; 
                const linkedMarkerKey = Object.keys(state.markerLabels).find(k => {
                    const mk = state.markerLabels[k];
                    return app.utils.isDensity(mk.tipo) && mk.espectro_pair === metricKey;
                });

                if (linkedMarkerKey && !state.activeMarkers.has(linkedMarkerKey)) {
                    const mkLabel = state.markerLabels[linkedMarkerKey].label || linkedMarkerKey;
                    showSmartToast(linkedMarkerKey, mkLabel);
                } else {
                    toastBox.classList.remove('show'); 
                }
            };

            const cInd = wrapper.querySelector('#c-indicador');
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

            // ☀️ OBTENER LA ESCALA PREDETERMINADA AL CARGAR CON SEGURIDAD EXTREMA
            if (!state.colorMode) {
                const infoCurrent = state.metricas[state.currentMetric] || {};
                state.colorMode = infoCurrent.scale_type || 'cuartiles';
            }

            Object.entries(state.metricas).forEach(([key, info]) => {
                const opt = document.createElement('div');
                opt.className = 'siarhe-cs-option';
                opt.dataset.key = key;
                
                if (key === state.currentMetric) {
                    opt.classList.add('selected');
                    triggerInd.innerHTML = `<span>${app.utils.esc(info.label || info.fullLabel)}</span>`;
                }
                
                opt.textContent = info.fullLabel;
                
                opt.addEventListener('click', (e) => {
                    e.stopPropagation();
                    state.currentMetric = key;
                    app.sortConfig = { key: 'tasa', direction: 'desc' };
                    
                    // ☀️ ACTUALIZAR LA ESCALA SEGÚN LA NUEVA MÉTRICA SELECCIONADA
                    const newInfo = state.metricas[key] || {};
                    state.colorMode = newInfo.scale_type || 'cuartiles';
                    const selectEscala = container.querySelector('#siarhe-escala-select');
                    if (selectEscala) selectEscala.value = state.colorMode;
                    
                    triggerInd.innerHTML = `<span>${app.utils.esc(info.label || info.fullLabel)}</span>`;
                    optionsContainerInd.querySelectorAll('.siarhe-cs-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    menuInd.classList.remove('open');
                    
                    onUpdate(); 
                    checkSpectrumLink(key);
                });
                optionsContainerInd.appendChild(opt);
            });

            menuInd.appendChild(optionsContainerInd);
            customSelectInd.append(triggerInd, menuInd);
            cInd.appendChild(customSelectInd);

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

            if (!isTableOnly) {
                const buildMarkerDropdown = (targetId, typeFilter, placeholderTitle) => {
                    const containerDiv = wrapper.querySelector('#' + targetId);
                    const validMarkers = Object.keys(state.markerUrls).filter(k => state.markerUrls[k] && app.utils.markerGroup((state.markerLabels[k] || {}).tipo) === typeFilter);
                    
                    if (validMarkers.length === 0) {
                        containerDiv.innerHTML += `<div class="mc-trigger is-placeholder" style="cursor:not-allowed; background:#f1f5f9;"><span>No disponibles</span></div>`;
                        return;
                    }

                    const field = document.createElement('div'); field.className = 'mc-field';
                    const triggerMc = document.createElement('div'); triggerMc.className = `mc-trigger is-placeholder trigger-${typeFilter}`; 
                    triggerMc.innerHTML = `<span>${app.utils.esc(placeholderTitle)}</span>`;
                    
                    if(typeFilter === 'posicion') state.posTrigger = triggerMc;
                    if(typeFilter === 'espectro') state.specTrigger = triggerMc;
                    
                    const menuMc = document.createElement('div'); menuMc.className = 'mc-menu';
                    const searchBoxMc = document.createElement('div'); searchBoxMc.className = 'siarhe-cs-search';
                    searchBoxMc.addEventListener('click', e => e.stopPropagation());
                    const searchInputMc = document.createElement('input'); searchInputMc.type = 'text'; searchInputMc.placeholder = '🔍 Filtrar...';
                    
                    const btnClearMc = document.createElement('button'); btnClearMc.type = 'button'; btnClearMc.innerHTML = '✖ Limpiar';
                    btnClearMc.addEventListener('click', (e) => {
                        e.stopPropagation();
                        Array.from(state.activeMarkers).forEach(k => { 
                            if(app.utils.markerGroup((state.markerLabels[k] || {}).tipo) === typeFilter) state.activeMarkers.delete(k); 
                        });
                        optionsContainerMc.querySelectorAll('.mc-check').forEach(chk => chk.checked = false);
                        if (app.map) { app.map.updateMarkers(state); app.map.renderMarkerLegend(state); }
                        app.controls.updateMarkerDropdownText(state);
                    });

                    searchBoxMc.append(searchInputMc, btnClearMc); menuMc.appendChild(searchBoxMc);
                    const optionsContainerMc = document.createElement('div'); optionsContainerMc.className = 'siarhe-cs-options';

                    validMarkers.forEach(key => {
                        const mkData = state.markerLabels[key] || {};
                        const label = mkData.label || key;
                        const opt = document.createElement('div'); opt.className = 'mc-option';
                        opt.innerHTML = `<input type="checkbox" class="mc-check" value="${app.utils.esc(key)}"> <span>${app.utils.esc(label)}</span>`;
                        
                        opt.addEventListener('click', (e) => {
                            if (e.target.tagName !== 'INPUT') {
                                const chk = opt.querySelector('input'); chk.checked = !chk.checked; 
                                app.controls.toggleMarker(key, state, container);
                            }
                        });
                        opt.querySelector('input').addEventListener('click', (e) => { 
                            e.stopPropagation(); app.controls.toggleMarker(key, state, container); 
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
                            menuMc.classList.add('open'); searchInputMc.value = '';
                            optionsContainerMc.querySelectorAll('.mc-option').forEach(o => o.style.display = '');
                            setTimeout(() => searchInputMc.focus(), 50);
                        }
                    });

                    field.append(triggerMc, menuMc); containerDiv.appendChild(field);
                };

                if (opts.showMarkers) {
                    buildMarkerDropdown('c-posicion', 'posicion', 'Seleccionar Clínicas...');
                    buildMarkerDropdown('c-espectro', 'espectro', 'Seleccionar Densidad...');
                }

                // ☀️ RENDERIZAR OPCIONES DE ESCALA CONECTADAS AL ESTADO
                const cEscala = wrapper.querySelector('#c-escala');
                const currScale = state.colorMode || 'cuartiles'; 
                
                cEscala.innerHTML += `
                    <select class="siarhe-metric-select" id="siarhe-escala-select">
                        <option value="cuartiles" ${currScale === 'cuartiles' || currScale === 'degradado' ? 'selected' : ''}>Degradado por Colores</option>
                        <option value="monocromatico" ${currScale === 'monocromatico' || currScale === 'mono' ? 'selected' : ''}>Escala Monocromática</option>
                        <option value="rangos" ${currScale === 'rangos' ? 'selected' : ''}>Rangos</option>
                    </select>
                `;
                cEscala.querySelector('select').addEventListener('change', (e) => {
                    state.colorMode = e.target.value; 
                    state.realStyleMode = e.target.value; 
                    if (app.map) app.map.updateVisuals(container, state);
                });

                const cVista = wrapper.querySelector('#c-vista');
                const labelNivel1 = state.isNacional ? 'Entidades Federativas' : 'Municipios';
                const labelNivel2 = state.isNacional ? 'Municipios' : 'Localidades';
                const geoLocUrl = container.dataset.geojsonLoc;

                cVista.innerHTML += `
                    <select class="siarhe-metric-select" id="siarhe-vista-select">
                        <option value="base">${labelNivel1}</option>
                        ${geoLocUrl ? `<option value="detalle">${labelNivel2}</option>` : ''}
                    </select>
                `;
                
                cVista.querySelector('select').addEventListener('change', (e) => {
                    const val = e.target.value;
                    const loading = container.querySelector('.siarhe-loading-overlay');
                    const setLoading = (text) => {
                        if (!loading) return;
                        loading.style.display = 'flex';
                        const p = loading.querySelector('p');
                        if (p) p.textContent = text;
                    };
                    const hideLoading = () => { if (loading) loading.style.display = 'none'; };

                    if (!state.baseGeoData) state.baseGeoData = state.geoData;

                    if (val === 'base') {
                        state.isGeoLocMode = false;
                        if (app.map) app.map.updateGeography(container, state);
                        onUpdate();
                    }
                    else if (val === 'detalle' && geoLocUrl) {
                        setLoading('Cargando ' + labelNivel2 + '...');

                        const dataPromise = state.locGeoData ? Promise.resolve(state.locGeoData) : d3.json(geoLocUrl);
                        dataPromise.then(data => {
                            if (!data || !Array.isArray(data.features) || data.features.length === 0) {
                                throw new Error('El GeoJSON detallado esta vacio o no es valido');
                            }
                            state.locGeoData = data;
                            state.isGeoLocMode = true;
                            if (!app.map) return null;
                            // Los poligonos se dibujan por lotes; el overlay muestra el avance
                            return app.map.updateGeography(container, state, (pct) => {
                                if (pct < 100) setLoading('Dibujando ' + labelNivel2 + '... ' + pct + '%');
                            });
                        }).then(() => {
                            hideLoading();
                            onUpdate();
                        }).catch(err => {
                            console.error("[SIARHE] Error al cargar GeoJSON detallado", err);
                            hideLoading();
                            state.isGeoLocMode = false;
                            e.target.value = 'base';
                        });
                    }
                });
            }

            const resetBtn = wrapper.querySelector('.siarhe-reset-btn');
            if (resetBtn) resetBtn.addEventListener('click', () => app.controls.resetAll(container, state, onUpdate));

            if (!isTableOnly) app.controls.initPalette(container, state, wrapper, onUpdate);

            ph.appendChild(wrapper);
            app.controls.enhanceDropdowns(container, wrapper);
            app.controls.updateMarkerDropdownText(state);
        },

        /**
         * Panel "Personalizar": el visitante cambia los colores del mapa para su vista y para la imagen
         * que descargue. Los cambios viven solo en el navegador (state.customColors); no se guardan en el sitio.
         */
        initPalette: function(container, state, wrapper, onUpdate) {
            const btn = wrapper.querySelector('.siarhe-palette-btn');
            const row = wrapper.querySelector('.siarhe-controls-row');
            if (!btn || !row) return;

            const hex = (c) => { try { const k = d3.color(c); return k ? k.formatHex() : '#000000'; } catch (e) { return '#000000'; } };
            const norm = (m) => (m === 'mono' || m === 'monocromatico') ? 'monocromatico' : (m === 'rangos' ? 'rangos' : 'cuartiles');

            // Colores de los rangos: si la metrica trae los suyos se muestran esos
            const rangeColors = () => {
                const info = state.metricas[state.currentMetric] || {};
                return [1, 2, 3, 4].map(n => (info.custom_colors === true && info['r' + n + '_color']) ? info['r' + n + '_color'] : app.colors.CUSTOM_RANGE[n - 1]);
            };
            const current = () => state.customColors || { RANGE: app.colors.RANGE, MONO: app.colors.MONO, CUSTOM_RANGE: rangeColors(), ZERO: app.colors.ZERO, NULL: app.colors.NULL };

            const GROUPS = [
                { id: 'cuartiles', title: 'Degradado por colores', key: 'RANGE', labels: ['Mínimo', 'Q1', 'Q2', 'Q3', 'Máximo'] },
                { id: 'monocromatico', title: 'Escala monocromática', key: 'MONO', labels: ['Claro', 'Oscuro'] },
                { id: 'rangos', title: 'Rangos', key: 'CUSTOM_RANGE', labels: ['Rango 1', 'Rango 2', 'Rango 3', 'Rango 4'] },
                { id: 'especiales', title: 'Valores especiales', singles: [['ZERO', 'Valor 0'], ['NULL', 'Sin dato (S/D)']] }
            ];

            const panel = document.createElement('div');
            panel.className = 'siarhe-palette-panel';
            panel.hidden = true;
            panel.setAttribute('role', 'group');
            panel.setAttribute('aria-label', 'Personalizar colores del mapa');

            const head = document.createElement('div');
            head.className = 'siarhe-palette-head';
            const hTitle = document.createElement('strong'); hTitle.textContent = 'Personalizar colores del mapa';
            const hNote = document.createElement('span'); hNote.textContent = 'Solo cambia tu vista y la imagen que descargues. No se guarda en el sitio.';
            head.appendChild(hTitle); head.appendChild(hNote);
            panel.appendChild(head);

            const groupsEl = document.createElement('div');
            groupsEl.className = 'siarhe-palette-groups';
            const inputs = [];

            GROUPS.forEach(g => {
                const fs = document.createElement('fieldset');
                fs.className = 'siarhe-palette-group';
                fs.setAttribute('data-style', g.id);
                const lg = document.createElement('legend'); lg.textContent = g.title; fs.appendChild(lg);
                const items = g.singles ? g.singles.map(x => ({ key: x[0], idx: null, label: x[1] }))
                                        : current()[g.key].map((_, i) => ({ key: g.key, idx: i, label: g.labels[i] || ('Color ' + (i + 1)) }));
                items.forEach(it => {
                    const lab = document.createElement('label');
                    lab.className = 'siarhe-palette-item';
                    const inp = document.createElement('input');
                    inp.type = 'color';
                    inp.setAttribute('aria-label', g.title + ': ' + it.label);
                    const txt = document.createElement('span'); txt.textContent = it.label;
                    lab.appendChild(inp); lab.appendChild(txt);
                    fs.appendChild(lab);
                    inputs.push({ el: inp, key: it.key, idx: it.idx });
                });
                groupsEl.appendChild(fs);
            });
            panel.appendChild(groupsEl);

            const actions = document.createElement('div');
            actions.className = 'siarhe-palette-actions';
            const restore = document.createElement('button');
            restore.type = 'button'; restore.className = 'siarhe-btn siarhe-palette-restore'; restore.textContent = 'Restaurar colores del sitio';
            const close = document.createElement('button');
            close.type = 'button'; close.className = 'siarhe-btn siarhe-palette-close'; close.textContent = 'Cerrar';
            actions.appendChild(restore); actions.appendChild(close);
            panel.appendChild(actions);

            row.insertAdjacentElement('afterend', panel);

            const refresh = () => {
                const pal = current();
                inputs.forEach(i => { i.el.value = hex(i.idx === null ? pal[i.key] : pal[i.key][i.idx]); });
                const mode = norm(state.colorMode || (state.metricas[state.currentMetric] || {}).scale_type);
                groupsEl.querySelectorAll('.siarhe-palette-group').forEach(f => { const id = f.getAttribute('data-style'); f.hidden = !(id === mode || id === 'especiales'); });
                restore.disabled = !state.customColors;
                btn.classList.toggle('is-modified', !!state.customColors);
            };
            state._paletteRefresh = refresh;

            let pending = false;
            const schedule = () => {
                if (pending) return;
                pending = true;
                requestAnimationFrame(() => { pending = false; if (typeof onUpdate === 'function') onUpdate(); refresh(); });
            };

            inputs.forEach(i => i.el.addEventListener('input', () => {
                if (!state.customColors) {
                    const c = current();
                    state.customColors = { RANGE: c.RANGE.slice(), MONO: c.MONO.slice(), CUSTOM_RANGE: c.CUSTOM_RANGE.slice(), ZERO: c.ZERO, NULL: c.NULL };
                }
                if (i.idx === null) state.customColors[i.key] = i.el.value; else state.customColors[i.key][i.idx] = i.el.value;
                schedule();
            }));

            const setOpen = (open) => {
                panel.hidden = !open;
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) refresh();
            };
            btn.addEventListener('click', () => setOpen(panel.hidden));
            close.addEventListener('click', () => { setOpen(false); btn.focus(); });
            restore.addEventListener('click', () => { state.customColors = null; refresh(); if (typeof onUpdate === 'function') onUpdate(); });
            panel.addEventListener('keydown', (e) => { if (e.key === 'Escape') { setOpen(false); btn.focus(); } });

            // Si cambian el estilo o el indicador con el panel abierto, se reflejan sus colores
            container.addEventListener('change', (e) => { if (!panel.hidden && e.target && e.target.id === 'siarhe-escala-select') refresh(); });
            panel.addEventListener('pointerenter', () => { if (!state.customColors) refresh(); });
        },

        toggleMarker: async function(type, state, container) {
            const loading = container.querySelector('.siarhe-loading-overlay');
            const msg = loading ? loading.querySelector('p') : null;
            const mkLabel = (state.markerLabels[type] || {}).label || type;

            const showLoading = (text) => {
                if (!loading) return;
                if (msg) msg.textContent = text;
                loading.style.position = 'absolute'; loading.style.top = '0'; loading.style.left = '0';
                loading.style.width = '100%'; loading.style.height = '100%';
                loading.style.background = 'rgba(248, 250, 252, 0.9)';
                loading.style.display = 'flex'; loading.style.flexDirection = 'column';
                loading.style.justifyContent = 'center'; loading.style.alignItems = 'center';
                loading.style.zIndex = '100';
            };

            try {
                if (state.activeMarkers.has(type)) {
                    state.activeMarkers.delete(type);
                } else {
                    state.activeMarkers.add(type);
                    if (!state.markersData[type]) {
                        showLoading(`Procesando ${mkLabel}...`);
                        await app.utils.nextFrame(); // deja pintar el overlay antes del trabajo pesado
                        await app.controls.loadMarkerData(type, state, container);
                    }
                }
                // Sin await a proposito: la aparicion progresiva de los marcadores ya es el indicador de avance
                if (app.map) app.map.updateMarkers(state);
            } catch (e) {
                console.error(`[SIARHE] Error cargando marcadores ${type}:`, e);
                state.activeMarkers.delete(type); // no dejamos el tipo "activo" sin datos
                if (msg) {
                    msg.textContent = `No se pudo cargar ${mkLabel}`;
                    await new Promise(r => setTimeout(r, 1800));
                }
                if (app.map) app.map.updateMarkers(state);
            } finally {
                if (loading) loading.style.display = 'none'; // siempre, incluso si algo fallo
                app.controls.updateMarkerDropdownText(state);
                if (app.urlState) app.urlState.write(container, state);
            }
        },

        /**
         * Worker (hilo en segundo plano) que lee y procesa los CSV de marcadores. Uno por pagina; si el
         * navegador no lo soporta o falla, devuelve null y se usa el procesamiento normal.
         */
        _markerWorker: function(container) {
            if (app._markerWorkerFailed) return null;
            if (app._markerWorker) return app._markerWorker;
            const src = container.dataset.worker;
            if (!src || typeof Worker === 'undefined') return null;
            try {
                const w = new Worker(src);
                w._pending = new Map();
                w._seq = 0;
                w.onmessage = (e) => {
                    const m = e.data || {};
                    const p = w._pending.get(m.id);
                    if (!p) return;
                    w._pending.delete(m.id);
                    if (m.ok) p.resolve(m); else p.reject(new Error(m.error || 'error en el worker'));
                };
                w.onerror = () => {
                    app._markerWorkerFailed = true;
                    app._markerWorker = null;
                    w._pending.forEach(p => p.reject(new Error('el worker no pudo ejecutarse')));
                    w._pending.clear();
                    try { w.terminate(); } catch (err) {}
                };
                app._markerWorker = w;
                return w;
            } catch (err) {
                app._markerWorkerFailed = true;
                return null;
            }
        },

        /**
         * Descarga, filtra y agrupa el CSV de un tipo de marcador. El trabajo se trocea por tiempo
         * (cede el hilo cada ~12 ms) para no disparar el aviso "la pagina esta tardando".
         * Las cargas simultaneas del mismo tipo comparten una sola Promise.
         */
        loadMarkerData: function(type, state, container) {
            state._markerLoads = state._markerLoads || {};
            if (state._markerLoads[type]) return state._markerLoads[type];

            const task = (async () => {
                const url = state.markerUrls ? state.markerUrls[type] : null;
                if (!url) throw new Error(`No hay URL configurada para el marcador ${type}`);

                // Camino rapido: el worker descarga, lee, filtra y agrupa sin bloquear la pagina
                const worker = app.controls._markerWorker(container);
                if (worker) {
                    try {
                        const cfg = state.markerLabels[type] || {};
                        const spec = {
                            type: type,
                            isNacional: !!state.isNacional,
                            currentEnt: (container.dataset.cveEnt || '').toString().padStart(2, '0'),
                            filtroCol: cfg.filtro_col ? cfg.filtro_col.toLowerCase().trim() : null,
                            filtroVal: cfg.filtro_val ? cfg.filtro_val.toString().toLowerCase().trim() : null,
                            agruparCol: cfg.agrupar_col ? cfg.agrupar_col.toLowerCase().trim() : null,
                            reglas: (cfg.reglas_tooltip || []).map(r => ({ col: r.col, val: r.val, label: r.label })),
                            espectroCol: cfg.espectro_col || ''
                        };
                        const tW = performance.now();
                        const res = await new Promise((resolve, reject) => {
                            const id = ++worker._seq;
                            worker._pending.set(id, { resolve: resolve, reject: reject });
                            worker.postMessage({ id: id, url: url, spec: spec });
                        });
                        state.markersData[type] = res.points;
                        app.utils.perf('worker: ' + type + ' (descarga y lectura ' + res.readMs + ' ms, procesado ' + res.procMs + ' ms)', tW, res.rows + ' filas -> ' + res.points.length + ' puntos');
                        return;
                    } catch (err) {
                        console.warn('[SIARHE] El worker no pudo procesar ' + type + ', se usa el modo normal:', err);
                    }
                }

                let rawData;
                if (state.rawCSVDataCache && state.rawCSVDataCache[url]) {
                    rawData = state.rawCSVDataCache[url];
                } else {
                    const tCsv = performance.now();
                    rawData = await d3.csv(url);
                    app.utils.perf('CSV de ' + type + ' descargado y leido', tCsv, rawData.length + ' filas');
                    if (!state.rawCSVDataCache) state.rawCSVDataCache = {};
                    state.rawCSVDataCache[url] = rawData;
                }
                if (!Array.isArray(rawData)) rawData = [];
                const tProc = performance.now();

                const config = state.markerLabels[type] || {};
                const filtroCol = config.filtro_col ? config.filtro_col.toLowerCase().trim() : null;
                const filtroVal = config.filtro_val ? config.filtro_val.toString().toLowerCase().trim() : null;
                const agruparCol = config.agrupar_col ? config.agrupar_col.toLowerCase().trim() : null;
                const reglas = config.reglas_tooltip || [];

                // 🚀 OPTIMIZACIÓN: resolvemos los nombres REALES de columna una sola
                // vez por CSV (no por fila). Antes, cada fila pagaba varios
                // Object.keys().find() (uno por cada campo, ~12 por fila), lo cual
                // es muy costoso en datasets nacionales grandes.
                const colMap = {};
                if (rawData.length > 0) {
                    Object.keys(rawData[0]).forEach(k => { colMap[k.trim().toLowerCase()] = k; });
                }
                const resolveKey = (names) => {
                    for (let n of names) {
                        const real = colMap[n.toLowerCase().trim()];
                        if (real) return real;
                    }
                    return null;
                };
                const fastVal = (d, key) => (key && d[key] !== undefined && d[key] !== null) ? d[key].toString().trim() : '';

                const latKey = resolveKey(['latitud', 'lat']);
                const lonKey = resolveKey(['longitud', 'lon']);
                const cveEntKey = resolveKey(['cve_ent', 'cve ent', 'entidad', 'clave_entidad']);
                const cluesKey = resolveKey(['clues']);
                const institucionKey = resolveKey(['institución', 'institucion', 'clave_institucion']);
                const nombreKey = resolveKey(['nombre_unidad', 'nombre de la unidad', 'unidad']);
                const municipioKey = resolveKey(['municipio']);
                const tipoEstabKey = resolveKey(['tipo_establecimiento', 'tipo establecimiento']);
                const tipologiaKey = resolveKey(['nombre_tipologia', 'nombre tipologia']);
                const nivelAtencionKey = resolveKey(['nivel_atencion', 'nivel atencion']);
                const cveNivelKey = resolveKey(['cve_n_atencion', 'cve_ n_atencion', 'cve n atencion']);
                const jurisdiccionKey = resolveKey(['jurisdiccion', 'jurisdicción']);
                const estratoKey = resolveKey(['estrato_unidad', 'estrato unidad']);

                const filtroColKey = filtroCol ? resolveKey([filtroCol]) : null;
                const agruparColKey = agruparCol ? resolveKey([agruparCol]) : null;
                const reglasResolved = reglas.map(r => ({ ...r, _colKey: resolveKey([r.col]) }));


                const currentEnt = (container.dataset.cveEnt || '').toString().padStart(2, '0');

                // Cede el hilo cuando el trozo actual supera ~12 ms
                let sliceStart = performance.now();
                const tick = async () => {
                    if (performance.now() - sliceStart > 12) {
                        await app.utils.nextFrame();
                        sliceStart = performance.now();
                    }
                };

                // 1) Filtrado
                const filteredData = [];
                for (let i = 0; i < rawData.length; i++) {
                    const d = rawData[i];
                    const lat = parseFloat(fastVal(d, latKey));
                    const lon = parseFloat(fastVal(d, lonKey));

                    let keep = !isNaN(lat) && !isNaN(lon) && lat !== 0 && lon !== 0;

                    if (keep && !state.isNacional && cveEntKey) {
                        // Sin clave de entidad el punto no se puede asignar a ningun mapa: no se dibuja
                        const cveEntMarker = fastVal(d, cveEntKey);
                        if (!cveEntMarker || cveEntMarker.padStart(2, '0') !== currentEnt) keep = false;
                    }

                    if (keep && filtroCol && filtroVal) {
                        keep = !!filtroColKey && fastVal(d, filtroColKey).toLowerCase() === filtroVal;
                    }

                    if (keep) filteredData.push(d);
                    if ((i & 127) === 0) await tick();
                }

                const buildPoint = (d) => ({
                    clues: fastVal(d, cluesKey),
                    institucion: fastVal(d, institucionKey),
                    nombre: fastVal(d, nombreKey),
                    municipio: fastVal(d, municipioKey),
                    lat: parseFloat(fastVal(d, latKey)),
                    lon: parseFloat(fastVal(d, lonKey)),
                    cve_ent: fastVal(d, cveEntKey),
                    tipo_estab: fastVal(d, tipoEstabKey),
                    tipologia: fastVal(d, tipologiaKey),
                    nivel_atencion: fastVal(d, nivelAtencionKey),
                    cve_nivel: fastVal(d, cveNivelKey),
                    jurisdiccion: fastVal(d, jurisdiccionKey),
                    estrato: fastVal(d, estratoKey),
                    tipo: type,
                    _raw: d
                });

                // 2) Agrupado (o mapeo directo)
                let processedData = [];
                if (agruparCol) {
                    const mapGroup = new Map();
                    if (agruparColKey) {
                        for (let i = 0; i < filteredData.length; i++) {
                            const d = filteredData[i];
                            const groupKey = fastVal(d, agruparColKey);

                            if (groupKey) {
                                let targetObj = mapGroup.get(groupKey);
                                if (!targetObj) {
                                    targetObj = buildPoint(d);
                                    targetObj._agrupados_total = 0;
                                    targetObj._conteo_reglas = {};
                                    reglasResolved.forEach(r => { targetObj._conteo_reglas[r.label] = 0; });
                                    mapGroup.set(groupKey, targetObj);
                                }
                                targetObj._agrupados_total += 1;

                                reglasResolved.forEach(r => {
                                    if (r._colKey) {
                                        const rowVal = fastVal(d, r._colKey).toLowerCase();
                                        const targetVal = (r.val === undefined || r.val === null ? '' : r.val).toString().toLowerCase().trim();
                                        if (rowVal === targetVal) targetObj._conteo_reglas[r.label] += 1;
                                    }
                                });
                            }
                            if ((i & 127) === 0) await tick();
                        }
                    }
                    processedData = Array.from(mapGroup.values());
                } else {
                    for (let i = 0; i < filteredData.length; i++) {
                        processedData.push(buildPoint(filteredData[i]));
                        if ((i & 127) === 0) await tick();
                    }
                }

                state.markersData[type] = processedData;
                app.utils.perf('filtrado y agrupado de ' + type, tProc, processedData.length + ' puntos');
            })();

            state._markerLoads[type] = task;
            const cleanup = () => { delete state._markerLoads[type]; };
            task.then(cleanup, cleanup);
            return task;
        },

        /**
         * Devuelve TODO a la configuracion inicial, como al recargar la pagina:
         * indicador, estilo de mapa, vista base, marcadores apagados, zoom, avisos y URL.
         */
        resetAll: function(container, state, onUpdate) {
            // 1) Menus y avisos abiertos
            container.querySelectorAll('.siarhe-cs-menu, .mc-menu').forEach(m => m.classList.remove('open'));
            const toast = container.querySelector('.siarhe-smart-toast');
            if (toast) toast.classList.remove('show');
            if (state.tooltip) state.tooltip.style('opacity', 0).style('display', 'none');

            // 2) Indicador y estilo de mapa iniciales
            const defaults = state._urlDefaults || {};
            if (defaults.metric && state.metricas[defaults.metric]) state.currentMetric = defaults.metric;
            const info = state.metricas[state.currentMetric] || {};
            state.colorMode = info.scale_type || 'cuartiles';
            state.realStyleMode = state.colorMode;
            app.sortConfig = { key: 'tasa', direction: 'desc' };

            const trigger = container.querySelector('#c-indicador .siarhe-cs-trigger');
            if (trigger) trigger.innerHTML = `<span>${app.utils.esc(info.label || info.fullLabel)}</span>`;
            container.querySelectorAll('#c-indicador .siarhe-cs-option').forEach(o => {
                o.classList.toggle('selected', o.dataset.key === state.currentMetric);
            });
            const selEscala = container.querySelector('#siarhe-escala-select');
            if (selEscala) selEscala.value = (state.colorMode === 'degradado' || state.colorMode === 'quartiles') ? 'cuartiles' : (state.colorMode === 'mono' ? 'monocromatico' : state.colorMode);

            // 3) Marcadores apagados
            state.activeMarkers.clear();
            container.querySelectorAll('.mc-check').forEach(chk => { chk.checked = false; });
            if (app.map) app.map.updateMarkers(state);
            app.controls.updateMarkerDropdownText(state);

            // 4) Vista geografica base
            state.isGeoLocMode = false;
            const selVista = container.querySelector('#siarhe-vista-select');
            if (selVista) selVista.value = 'base';
            if (app.map) app.map.updateGeography(container, state);

            // 5) Medicion de distancias fuera y limpia
            if (app.map && app.map.setMeasureMode) app.map.setMeasureMode(state, false);

            // 5b) Zoom y desplazamiento iniciales
            if (state.svg && state.zoom && state.initialTransform) {
                state.svg.transition().duration(app.utils.motionMs(600)).call(state.zoom.transform, state.initialTransform);
            }

            // 5c) Colores personalizados por el visitante
            state.customColors = null;
            if (typeof state._paletteRefresh === 'function') state._paletteRefresh();

            // 6) Recolorear mapa, tabla y totales; la URL vuelve a quedar limpia
            if (typeof onUpdate === 'function') onUpdate();
            if (app.urlState) app.urlState.flush(container, state);
        },

        /**
         * Accesibilidad de los selectores personalizados: tab stop, roles ARIA, flechas/Enter/Espacio/Escape
         * y etiquetas para los <select> nativos.
         */
        enhanceDropdowns: function(container, root) {
            let uid = 0;
            const sync = () => root.querySelectorAll('[aria-haspopup="listbox"]').forEach(t => {
                const m = t.nextElementSibling;
                t.setAttribute('aria-expanded', m && m.classList.contains('open') ? 'true' : 'false');
            });

            root.querySelectorAll('.siarhe-cs-trigger, .mc-trigger').forEach(trigger => {
                if (trigger.style.cursor === 'not-allowed') { trigger.setAttribute('aria-disabled', 'true'); return; }
                const menu = trigger.nextElementSibling;
                if (!menu || !/(siarhe-cs-menu|mc-menu)/.test(menu.className)) return;

                uid++;
                trigger.id = trigger.id || ('siarhe-dd-' + uid + '-' + Math.random().toString(36).slice(2, 6));
                trigger.setAttribute('tabindex', '0');
                trigger.setAttribute('role', 'button');
                trigger.setAttribute('aria-haspopup', 'listbox');
                trigger.setAttribute('aria-expanded', 'false');

                const group = trigger.closest('.siarhe-control-group');
                const label = group ? group.querySelector('label') : null;
                if (label) {
                    label.id = label.id || (trigger.id + '-lbl');
                    trigger.setAttribute('aria-labelledby', label.id + ' ' + trigger.id);
                }

                const list = menu.querySelector('.siarhe-cs-options');
                if (list) list.setAttribute('role', 'listbox');
                menu.querySelectorAll('.siarhe-cs-option, .mc-option').forEach(o => {
                    o.setAttribute('role', 'option');
                    o.setAttribute('tabindex', '-1');
                });

                trigger.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (!menu.classList.contains('open')) trigger.click();
                        const first = menu.querySelector('[role="option"]');
                        if (e.key === 'ArrowDown' && first) setTimeout(() => first.focus(), 60);
                    }
                });

                menu.addEventListener('keydown', (e) => {
                    const opts = Array.from(menu.querySelectorAll('[role="option"]')).filter(o => o.style.display !== 'none');
                    const i = opts.indexOf(document.activeElement);

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const n = opts[i + 1] || opts[0];
                        if (n) n.focus();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (i > 0) opts[i - 1].focus();
                        else { const inp = menu.querySelector('input[type="text"]'); if (inp) inp.focus(); }
                    } else if ((e.key === 'Enter' || e.key === ' ') && i >= 0) {
                        e.preventDefault();
                        opts[i].click();
                        if (!menu.classList.contains('open')) trigger.focus();
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        menu.classList.remove('open');
                        sync();
                        trigger.focus();
                    }
                    setTimeout(sync, 0);
                });
            });

            // <select> nativos: su <label> visible no esta enlazado, se lo damos como nombre accesible
            root.querySelectorAll('select.siarhe-metric-select').forEach(sel => {
                const group = sel.closest('.siarhe-control-group');
                const label = group ? group.querySelector('label') : null;
                if (label) sel.setAttribute('aria-label', label.textContent.trim());
            });

            // aria-expanded sigue a las aperturas/cierres hechos por clic
            container.addEventListener('click', () => setTimeout(sync, 0), true);
        },

        updateMarkerDropdownText: function(state) {
            const upd = (trigger, typeFilter) => {
                if (!trigger) return;
                const sel = Array.from(state.activeMarkers).filter(k => app.utils.markerGroup((state.markerLabels[k] || {}).tipo) === typeFilter);
                
                const clearBtn = trigger.parentElement.querySelector('.siarhe-cs-search button');
                if (clearBtn) {
                    clearBtn.style.display = sel.length > 0 ? 'block' : 'none';
                }

                if (sel.length === 0) { 
                    trigger.innerHTML = `<span>Seleccionar...</span>`; 
                    trigger.classList.add('is-placeholder'); 
                } else {
                    const labels = sel.map(k => (state.markerLabels[k] || {}).label || k);
                    trigger.innerHTML = `<span>${app.utils.esc(labels.join(', '))}</span>`; 
                    trigger.classList.remove('is-placeholder');
                }
            };

            upd(state.posTrigger, 'posicion');
            upd(state.specTrigger, 'espectro');
        },

        setupActionButtons: function(container, state) {
            const btnLabels = container.querySelector('.siarhe-btn-toggle-labels');
            const btnPng = container.querySelector('.siarhe-btn-download-png');
            
            const btnShare = container.querySelector('.siarhe-btn-share');
            if (btnShare) {
                btnShare.addEventListener('click', async (e) => {
                    e.preventDefault();
                    if (app.urlState) app.urlState.flush(container, state); // la URL debe reflejar el estado actual
                    const url = window.location.href;
                    const original = btnShare.innerHTML;
                    try {
                        await navigator.clipboard.writeText(url);
                        btnShare.textContent = '✅ Enlace copiado';
                        setTimeout(() => { btnShare.innerHTML = original; }, 2000);
                    } catch (err) {
                        window.prompt('Copia este enlace:', url);
                    }
                });
            }

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
                
                // 🌟 CORRECCIÓN ARQUITECTÓNICA AL EXPORTAR
                // Extraemos el gafete (pairGroup) y buscamos los nombres REALES de las columnas
                const pairGroup = metricInfo.pair || mKey; 
                const isPob = (mKey === 'poblacion');
                
                const enfDataKey = isPob ? null : app.utils.findAbsoluteKeyForPair(pairGroup, state.metricas); 
                const tasaDataKey = isPob ? null : ((metricInfo.tipo === 'tasa') ? mKey : app.utils.findRateKeyForAbsolute(pairGroup, state.metricas));
                
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
                    if (!isPob && enfDataKey && r[enfDataKey] !== null && r[enfDataKey] !== undefined) {
                        enfStr = r[enfDataKey];
                    }
                    
                    let tasaStr = '-';
                    if (!isPob && tasaDataKey && r[tasaDataKey] !== null && r[tasaDataKey] !== undefined) {
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