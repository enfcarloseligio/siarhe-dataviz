/**
 * SIARHE DataViz - Módulo de Tooltips
 * ------------------------------------------------------------------
 * Gestiona exclusivamente la construcción y renderizado del HTML 
 * de los tooltips flotantes para polígonos (GeoJSON) y marcadores.
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    app.tooltips = {

        /**
         * Coloca el tooltip junto al puntero sin que se salga del contenedor del mapa:
         * a la derecha por defecto; si no cabe, pasa a la izquierda; si no cabe abajo, sube;
         * si no cabe arriba, baja. Sirve igual para mouse y toque (movil).
         */
        position: function(state, mapDiv, mx, my) {
            const tip = state.tooltip && state.tooltip.node();
            if (!tip || !mapDiv) return;
            const W = mapDiv.clientWidth, H = mapDiv.clientHeight, M = 6;
            tip.style.maxWidth = Math.max(160, Math.min(300, W - 2 * M)) + 'px';
            const tw = tip.offsetWidth, th = tip.offsetHeight;
            if (!tw || !th) return;

            let left = mx + 15;
            if (left + tw > W - M) left = mx - 15 - tw;
            let top = my - 28;
            if (top + th > H - M) top = my - th - 12;
            if (top < M) top = my + 18;

            left = Math.max(M, Math.min(left, W - tw - M));
            top = Math.max(M, Math.min(top, H - th - M));
            state.tooltip.style('left', left + 'px').style('top', top + 'px');
        },

        showGeoTooltip: function(event, d, state, mapDiv) {
            const tc = function(v) { return app.utils.titleCase(v); };
            let cve = app.utils.getGeoKey(d.properties, state.isNacional);
            let row = state.dataMap.get(cve);
            const wrapper = mapDiv.closest('.siarhe-viz-wrapper');
            
            // LÓGICA DE HERENCIA: Si estamos en vista detalle y no hay fila, heredar del padre
            if (!row && state.isGeoLocMode && d.properties.CVE_ENT && d.properties.CVE_MUN) {
                let parentCve = state.isNacional ? d.properties.CVE_ENT : (d.properties.CVE_ENT + d.properties.CVE_MUN);
                row = state.dataMap.get(parentCve);
            }
            
            // LÓGICA INTELIGENTE DE TÍTULOS (NACIONAL VS ESTATAL)
            let nombre = app.utils.esc(tc(row ? row.estado : (d.properties.NOMGEO || d.properties.NOM_ENT || "Sin Datos")));
            
            if (state.isGeoLocMode) {
                let ent = row ? row.estado : (d.properties.NOM_ENT || "");
                let mun = d.properties.NOM_MUN || d.properties.MUNICIPIO || "";
                let loc = d.properties.NOM_LOC || d.properties.LOCALIDAD || "";

                if (state.isNacional) {
                    if (!mun && d.properties.NOMGEO) mun = d.properties.NOMGEO; 
                    if (mun) {
                        nombre = `${app.utils.esc(tc(ent))} / <span style="opacity:0.8; font-weight:normal;">${app.utils.esc(tc(mun))}</span>`;
                    }
                } else {
                    let munName = row ? row.estado : (d.properties.NOM_MUN || d.properties.MUNICIPIO || "Municipio");
                    if (!loc && d.properties.NOMGEO) loc = d.properties.NOMGEO; 
                    if (munName && loc && munName !== loc) {
                        nombre = `${app.utils.esc(tc(munName))} / <span style="opacity:0.8; font-weight:normal;">${app.utils.esc(tc(loc))}</span>`;
                    } else if (loc) {
                        nombre = app.utils.esc(tc(loc));
                    }
                }
            }

            const tt = state.tooltipConfig || {}; 
            const order = tt.geo_order || ['pob', 'abs', 'rate'];
            const hlVar = tt.highlight_var || 'rate'; 
            const hlColor = tt.highlight_color || '#06b6d4';
            const bgColor = tt.bg_color || '#0f172a';
            const bgOpacity = (tt.bg_opacity !== undefined ? parseInt(tt.bg_opacity, 10) : 90) / 100;
            const txtColor = tt.text_color || '#f8fafc';
            
            const finalBgColor = app.map.hexToRgba(bgColor, bgOpacity);
            
            const mKey = state.currentMetric; 
            const pKey = state.metricas[mKey] ? state.metricas[mKey].pair : mKey; 
            
            let blocks = {};
            
            if (tt.geo_pob !== false) {
                blocks['pob'] = { label: 'Población', value: row && row.poblacion ? row.poblacion.toLocaleString('es-MX') : '—' };
            }
            
            if (mKey !== 'poblacion') {
                if (tt.geo_abs !== false) {
                    const labelAbs = state.metricas[pKey] ? state.metricas[pKey].label : pKey;
                    const valAbs = (row && row[pKey] !== null && row[pKey] !== undefined) ? row[pKey].toLocaleString('es-MX') : '—';
                    blocks['abs'] = { label: labelAbs, value: valAbs };
                }
                
                if (tt.geo_rate !== false) {
                    let tasaKey = (state.metricas[mKey].tipo === 'tasa') ? mKey : app.utils.findRateKeyForAbsolute(pKey, state.metricas);
                    let valTasa = row && tasaKey ? row[tasaKey] : null;
                    const valTasaFmt = (valTasa !== null && valTasa !== undefined) ? valTasa.toFixed(2) : '—';
                    const labelTasa = state.metricas[mKey] ? state.metricas[mKey].label : 'Tasa';
                    blocks['rate'] = { label: labelTasa, value: valTasaFmt };
                }
            }

            let htmlStandard = '';
            let htmlHighlight = '';

            order.forEach(itemKey => {
                if (!blocks[itemKey]) return; 
                const b = blocks[itemKey];

                if (hlVar === itemKey) {
                    htmlHighlight = `
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px; padding-top:6px; border-top:1px solid rgba(255,255,255,0.2);">
                            <span style="font-size:12px; opacity:0.9;">${app.utils.esc(b.label)}:</span> 
                            <strong style="color:${hlColor}; font-size:14px; margin-left:15px;">${app.utils.esc(b.value)}</strong>
                        </div>`;
                } else {
                    htmlStandard += `
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:12px;">
                            <span style="opacity:0.8; margin-right:15px;">${app.utils.esc(b.label)}:</span> 
                            <b>${app.utils.esc(b.value)}</b>
                        </div>`;
                }
            });

            let htmlResumenMarcadores = '';
            try {
                if (state.activeMarkers && state.activeMarkers.size > 0) {
                    const mapCveEnt = wrapper && wrapper.dataset.cveEnt ? wrapper.dataset.cveEnt.toString().padStart(2, '0') : '';
                    const mapCveMun = (state.isGeoLocMode && d.properties.CVE_MUN) ? d.properties.CVE_MUN.toString().padStart(3, '0') : cve;

                    state.activeMarkers.forEach(type => {
                        const config = state.markerLabels[type] || {};
                        
                        if (config.resumen_geo === true || config.resumen_geo === 'true') {
                            const markerData = state.markersData[type] || [];
                            
                            let totalPuntos = 0;
                            let totalRegistros = 0;
                            let sumReglas = {};

                            markerData.forEach(mk => {
                                let match = false;
                                const mkEnt = mk.cve_ent ? mk.cve_ent.toString().padStart(2, '0') : null;

                                if (state.isNacional) {
                                    if (mkEnt === cve) match = true;
                                } else {
                                    let mkMun = null;
                                    if (mk._raw) {
                                        mkMun = app.utils.getColValue(mk._raw, ['cve_mun', 'cve mun', 'municipio', 'clave_municipio']);
                                        if (mkMun) mkMun = mkMun.toString().padStart(3, '0');
                                    }
                                    if (mkEnt === mapCveEnt && mkMun === mapCveMun) match = true;
                                }

                                if (match) {
                                    totalPuntos++;
                                    totalRegistros += (mk._agrupados_total !== undefined) ? mk._agrupados_total : 1;

                                    if (mk._conteo_reglas) {
                                        Object.entries(mk._conteo_reglas).forEach(([rLabel, rCount]) => {
                                            if (!sumReglas[rLabel]) sumReglas[rLabel] = 0;
                                            sumReglas[rLabel] += rCount;
                                        });
                                    }
                                }
                            });

                            if (totalPuntos > 0) {
                                const mkStyle = app.utils.getMarkerStyle(type, state);
                                const mkLabelColor = mkStyle.fill.startsWith('rgba') ? mkStyle.fill.replace(/[^,]+(?=\))/, '1') : mkStyle.fill;
                                const mkLabelNombre = config.label || type;

                                htmlResumenMarcadores += `
                                    <div style="margin-top:12px; padding-top:8px; border-top:1px dashed rgba(255,255,255,0.3);">
                                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; color:${mkLabelColor}; font-weight:bold; display:flex; align-items:center; gap:5px;">
                                            <div style="width:8px; height:8px; background:${mkLabelColor}; border-radius:50%;"></div>
                                            ${app.utils.esc(mkLabelNombre)}
                                        </div>
                                `;

                                htmlResumenMarcadores += `
                                    <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:2px;">
                                        <span style="opacity:0.8;">Puntos Físicos (Unidades):</span> 
                                        <strong>${totalPuntos.toLocaleString('es-MX')}</strong>
                                    </div>`;

                                htmlResumenMarcadores += `
                                    <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:4px;">
                                        <span style="opacity:0.9;">Total Registros:</span> 
                                        <strong style="color:#f8fafc;">${totalRegistros.toLocaleString('es-MX')}</strong>
                                    </div>`;

                                if (Object.keys(sumReglas).length > 0) {
                                    Object.entries(sumReglas).forEach(([rLabel, rVal]) => {
                                        if (rVal > 0) {
                                            htmlResumenMarcadores += `
                                                <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:2px; padding-left:10px; border-left:1px solid rgba(255,255,255,0.1);">
                                                    <span style="opacity:0.75;">↳ ${app.utils.esc(rLabel)}:</span> 
                                                    <strong style="color:#f8fafc; font-size:10px;">${rVal.toLocaleString('es-MX')}</strong>
                                                </div>`;
                                        }
                                    });
                                }
                                htmlResumenMarcadores += `</div>`;
                            }
                        }
                    });
                }
            } catch (error) {
                console.error("[SIARHE] Error calculando resumen geográfico:", error);
            }

            // 🌟 SUBTÍTULO DINÁMICO: "Información Estatal" o "Información Municipal"
            const labelNivel = state.isNacional ? 'Información Estatal:' : 'Información Municipal:';

            let html = `
                <div style="font-family:'Roboto', sans-serif;">
                    <div style="font-weight:bold; font-size:14px; margin-bottom:2px;">
                        ${nombre}
                    </div>
                    <div style="font-size:10px; text-transform:uppercase; color:#94a3b8; letter-spacing:0.5px; margin-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:6px;">
                        ${labelNivel}
                    </div>
                    ${htmlStandard}
                    ${htmlHighlight}
                    ${htmlResumenMarcadores}
                </div>
            `;
            
            if (mapDiv) {
                const [mx, my] = d3.pointer(event, mapDiv);
                state.tooltip.html(html)
                    .style("background-color", finalBgColor)
                    .style("color", txtColor)
                    .style("border-radius", "6px")
                    .style("padding", "10px 14px")
                    .style("box-shadow", "0 4px 15px rgba(0,0,0,0.2)")
                    .style("display", "block")
                    .style("opacity", 1);
                app.tooltips.position(state, mapDiv, mx, my);
            }
        },

        showMarkerTooltip: function(event, d, state, mapDiv) {
            const card = app.tooltips.markerCard(d, state);
            const [mx, my] = d3.pointer(event, mapDiv);

            state.tooltip.html(card.html)
                .style("background-color", card.bg)
                .style("color", card.txt)
                .style("border-radius", "6px")
                .style("padding", "10px 14px")
                .style("box-shadow", "0 4px 15px rgba(0,0,0,0.2)")
                .style("display", "block")
                .style("opacity", 1);
            app.tooltips.position(state, mapDiv, mx, my);
        },

        /** Contenido (HTML) y colores de la tarjeta de un marcador; lo usan el tooltip y el panel de marcadores apilados. */
        markerCard: function(d, state) {
            const tc = function(v) { return app.utils.titleCase(v); };
            const tt = state.tooltipConfig || {}; 
            const hlVar = tt.mk_highlight_var || 'none';
            const hlColor = tt.mk_highlight_color || '#06b6d4';
            const bgColor = tt.mk_bg_color || '#0f172a';
            const bgOpacity = (tt.mk_bg_opacity !== undefined ? parseInt(tt.mk_bg_opacity, 10) : 90) / 100;
            const txtColor = tt.mk_text_color || '#f8fafc';
            const finalBgColor = app.map.hexToRgba(bgColor, bgOpacity);
            const mkOrder = tt.mk_order || ['mk_inst', 'mk_clues', 'mk_tipo', 'mk_nivel', 'mk_separator', 'mk_juris', 'mk_mun'];

            let zonaTexto = "";
            if (d.estrato) {
                const estLower = d.estrato.toString().toLowerCase().trim();
                if (estLower === '1' || estLower === 'rural') zonaTexto = "Rural";
                else if (estLower === '2' || estLower === 'urbano') zonaTexto = "Urbana";
                else zonaTexto = tc(d.estrato);
            }
            const titleLabel = zonaTexto ? `Establecimiento en Zona ${zonaTexto}` : (state.markerLabels[d.tipo] ? state.markerLabels[d.tipo].label : d.tipo);

            let blocks = {};
            
            if (tt.mk_inst !== false && d.institucion) blocks['mk_inst'] = { label: 'Institución', value: tc(d.institucion) };
            if (tt.mk_clues !== false && d.clues) blocks['mk_clues'] = { label: 'CLUES', value: d.clues };
            if (tt.mk_nivel !== false && d.nivel_atencion) blocks['mk_nivel'] = { label: 'Nivel', value: tc(d.nivel_atencion) };
            if (tt.mk_juris !== false && d.jurisdiccion) blocks['mk_juris'] = { label: 'Jurisdicción', value: tc(d.jurisdiccion) };
            
            if (tt.mk_tipo !== false && (d.tipo_estab || d.tipologia)) {
                let valTipo = [];
                if (d.tipo_estab) valTipo.push(tc(d.tipo_estab));
                if (d.tipologia) valTipo.push(tc(d.tipologia));
                blocks['mk_tipo'] = { label: 'Tipo', value: valTipo.join(' - ') };
            }
            
            if (tt.mk_mun !== false && (d._raw)) {
                let ubicacion = [];
                const colEntidad = app.utils.getColValue(d._raw, ['entidad', 'nom_ent']);
                const colLocalidad = app.utils.getColValue(d._raw, ['localidad', 'nom_loc']);
                if (colEntidad) ubicacion.push(tc(colEntidad));
                if (d.municipio) ubicacion.push(tc(d.municipio));
                if (colLocalidad) ubicacion.push(tc(colLocalidad));
                if (ubicacion.length > 0) blocks['mk_mun'] = { label: 'Ubicación', value: ubicacion.join(', ') };
            }

            let htmlStandard = '';
            let htmlHighlight = '';

            mkOrder.forEach(itemKey => {
                if (itemKey === 'mk_separator') {
                    htmlStandard += `<div style="border-top: 1px dashed rgba(255,255,255,0.2); margin: 6px 0;"></div>`;
                    return;
                }

                if (!blocks[itemKey]) return; 
                const b = blocks[itemKey];

                if (hlVar === itemKey) {
                    htmlHighlight = `
                        <div style="margin-top:6px; padding-top:6px; border-top:1px solid rgba(255,255,255,0.2);">
                            <span style="font-size:11px; opacity:0.8;">${app.utils.esc(b.label)}:</span><br>
                            <strong style="color:${hlColor}; font-size:12px;">${app.utils.esc(b.value)}</strong>
                        </div>`;
                } else {
                    htmlStandard += `
                        <div style="font-size:11px; line-height: 1.4; margin-bottom: 3px;">
                            <span style="color:#06B6D4; opacity:0.9;">${app.utils.esc(b.label)}:</span> 
                            <strong style="color:${txtColor}; font-family:'IBM Plex Sans', sans-serif;">${app.utils.esc(b.value)}</strong>
                        </div>`;
                }
            });

            let htmlReglas = '';
            if (d._agrupados_total !== undefined && d._agrupados_total > 0) {
                htmlReglas += `<div style="margin-top:8px; padding-top:8px; border-top:1px dashed rgba(255,255,255,0.3);">`;
                htmlReglas += `<div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; opacity:0.7;">Estadísticas del Punto</div>`;
                
                htmlReglas += `<div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:3px;">
                                <span style="opacity:0.9;">Total Registros:</span> 
                                <strong style="color:#f8fafc;">${d._agrupados_total.toLocaleString('es-MX')}</strong>
                               </div>`;

                if (d._conteo_reglas) {
                    Object.entries(d._conteo_reglas).forEach(([ruleLabel, ruleCount]) => {
                        if (ruleCount > 0) {
                            htmlReglas += `<div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:3px;">
                                            <span style="opacity:0.9;">${app.utils.esc(ruleLabel)}:</span> 
                                            <strong style="color:#f8fafc;">${ruleCount.toLocaleString('es-MX')}</strong>
                                           </div>`;
                        }
                    });
                }
                htmlReglas += `</div>`;
            }

            let html = `<div style="font-family:'Roboto', sans-serif; color:${txtColor};">`;
            html += `<div style="color: #06B6D4; font-weight:bold; font-size:12px; margin-bottom:4px; padding-bottom:4px;">${app.utils.esc(titleLabel)}</div>`;
            html += `<div style="font-weight:bold; font-size:13px; font-family:'IBM Plex Sans', sans-serif; margin-bottom: 8px;">${app.utils.esc(tc(d.nombre) || 'Desconocido')}</div>`;
            // Spike: el valor que define la altura y el color del pico
            const mkCfg = state.markerLabels[d.tipo] || {};
            if (mkCfg.tipo === 'spike' && app.map && app.map._markerValue) {
                const spikeVal = app.map._markerValue(d, mkCfg, state, d.tipo);
                const colName = (mkCfg.espectro_col && mkCfg.espectro_col.trim()) ? mkCfg.espectro_col.trim() : 'Registros';
                html += `<div style='font-size:11px; margin-bottom:6px;'><span style='color:#06B6D4;'>${app.utils.esc(colName)}:</span> <strong>${Number(spikeVal).toLocaleString('es-MX')}</strong></div>`;
            }
            html += htmlStandard;
            html += htmlHighlight;
            html += htmlReglas; 
            html += `</div>`;

            return { html: html, bg: finalBgColor, txt: txtColor };
        },

        /**
         * Panel de marcadores apilados (los que se tapan entre si en el mismo punto). A diferencia del tooltip, recibe toques:
         * flechas para pasar al anterior o siguiente, una lista numerada para saltar a cualquiera y, con la medicion
         * activa, un boton para agregar o quitar el marcador mostrado. `stack` = { list, idx, ax, ay } (ax, ay: px dentro del mapa).
         */
        showStackCard: function(state, mapDiv, stack) {
            const esc = app.utils.esc;
            let el = state.stackCard;
            if (!el || !el.isConnected) {
                el = state.stackCard = document.createElement('div');
                el.className = 'siarhe-stack-card';
                el.setAttribute('role', 'dialog');
                el.setAttribute('aria-label', 'Marcadores encimados');
                mapDiv.appendChild(el);
                // Nada de lo que se toque aqui debe llegar al mapa (zoom, arrastre, clic en un poligono)
                ['click', 'dblclick', 'mousedown', 'touchstart', 'pointerdown', 'wheel'].forEach(function(t) {
                    el.addEventListener(t, function(ev) { ev.stopPropagation(); }, { passive: true });
                });
                el.addEventListener('click', function(ev) {
                    const b = ev.target.closest && ev.target.closest('[data-act]');
                    if (!b || !state._stack) return;
                    const st = state._stack, act = b.getAttribute('data-act'), n = st.list.length;
                    if (act === 'prev') st.idx = (st.idx - 1 + n) % n;
                    else if (act === 'next') st.idx = (st.idx + 1) % n;
                    else if (act === 'go') st.idx = parseInt(b.getAttribute('data-i'), 10) || 0;
                    else if (act === 'close') { app.map.stackClose(state); return; }
                    else if (act === 'measure') { app.map.measureAddPoint(state, st.list[st.idx]); }
                    app.map.stackShow(state);
                });
            }

            const list = stack.list, n = list.length, cur = list[stack.idx];
            const card = app.tooltips.markerCard(cur, state);
            const ms = state.measure;
            const cfgCur = state.markerLabels[cur.tipo] || {};

            let html = `<div class="siarhe-stack-head">
                <button type="button" class="siarhe-stack-btn" data-act="prev" aria-label="Marcador anterior">&#9664;</button>
                <span class="siarhe-stack-count" aria-live="polite">${stack.idx + 1} de ${n}</span>
                <button type="button" class="siarhe-stack-btn" data-act="next" aria-label="Marcador siguiente">&#9654;</button>
                <button type="button" class="siarhe-stack-btn siarhe-stack-close" data-act="close" aria-label="Cerrar">&times;</button>
            </div>`;
            html += card.html;
            if (ms && ms.active && cfgCur.tipo === 'posicion') {
                const inMs = ms.points.some(function(p) { return p.uid === cur._uid; });
                html += `<button type="button" class="siarhe-stack-measure" data-act="measure">${inMs ? 'Quitar de la medición' : 'Agregar a la medición'}</button>`;
            }
            html += `<ol class="siarhe-stack-list">`;
            list.forEach(function(d, i) {
                const st = app.utils.getMarkerStyle(d.tipo, state);
                const nm = app.utils.titleCase(d.nombre || d.clues || (state.markerLabels[d.tipo] || {}).label || 'Marcador');
                html += `<li><button type="button" data-act="go" data-i="${i}" class="${i === stack.idx ? 'is-current' : ''}">
                    <span class="siarhe-stack-n">${i + 1}</span>
                    <span class="siarhe-stack-dot" style="background:${esc(st.fill)}"></span>
                    <span class="siarhe-stack-name">${esc(nm)}</span></button></li>`;
            });
            html += `</ol>`;

            el.style.backgroundColor = card.bg;
            el.style.color = card.txt;
            el.innerHTML = html;
            el.style.display = 'block';

            // Posicion: junto al punto, sin salirse del mapa
            const W = mapDiv.clientWidth, H = mapDiv.clientHeight, M = 6;
            el.style.maxWidth = Math.max(180, Math.min(300, W - 2 * M)) + 'px';
            el.style.maxHeight = Math.max(160, H - 2 * M) + 'px';
            const tw = el.offsetWidth, th = el.offsetHeight;
            let left = stack.ax + 15;
            if (left + tw > W - M) left = stack.ax - 15 - tw;
            let top = stack.ay - 28;
            if (top + th > H - M) top = stack.ay - th - 12;
            if (top < M) top = stack.ay + 18;
            el.style.left = Math.max(M, Math.min(left, W - tw - M)) + 'px';
            el.style.top = Math.max(M, Math.min(top, H - th - M)) + 'px';
        }
    };

})(window.SiarheDataViz);