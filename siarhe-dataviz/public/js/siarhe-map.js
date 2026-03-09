/**
 * SIARHE DataViz - Módulo de Mapa (Map & D3)
 * ------------------------------------------------------------------
 * Gestiona la representación visual de los datos GeoJSON y CSV usando D3.js.
 * Se encarga del renderizado de los polígonos, la lógica de colores,
 * el sistema de zoom elástico, los tooltips flotantes y la exportación a PNG.
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    app.map = {
        
        // UTILIDAD: Convierte Hexadecimal a RGBA para aplicar la opacidad del tooltip
        hexToRgba: function(hex, alpha) {
            let c;
            if(/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)){
                c= hex.substring(1).split('');
                if(c.length== 3){
                    c= [c[0], c[0], c[1], c[1], c[2], c[2]];
                }
                c= '0x'+c.join('');
                return 'rgba('+[(c>>16)&255, (c>>8)&255, c&255].join(',')+','+alpha+')';
            }
            return `rgba(15, 23, 42, ${alpha})`; // Fallback oscuro
        },

        // ==========================================
        // 1. RENDERIZADO INICIAL DEL MAPA SVG
        // ==========================================
        
        render: function(container, state, cveEnt) {
            
            if (!document.getElementById('siarhe-fullscreen-styles')) {
                const style = document.createElement('style');
                style.id = 'siarhe-fullscreen-styles';
                style.innerHTML = `
                    .siarhe-controls.is-fullscreen-mode {
                        position: absolute !important;
                        top: 15px;
                        left: 50%;
                        transform: translateX(-50%) scale(0.85);
                        background: rgba(255, 255, 255, 0.95);
                        padding: 10px 25px;
                        border-radius: 8px;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
                        z-index: 1000;
                        display: flex;
                        gap: 15px;
                        transform-origin: center top;
                        border: 1px solid #cbd5e1;
                        width: max-content;
                    }
                    .siarhe-controls.is-fullscreen-mode .mc-menu, 
                    .siarhe-controls.is-fullscreen-mode .siarhe-cs-menu {
                        max-height: 50vh;
                        overflow-y: auto;
                    }
                    @media (max-width: 767px) {
                        .siarhe-controls.is-fullscreen-mode {
                            flex-direction: column;
                            gap: 10px;
                            top: 10px;
                            transform: translateX(-50%) scale(0.8);
                        }
                    }
                `;
                document.head.appendChild(style);
            }

            const mapDiv = container.querySelector('.siarhe-map-container');
            if (!mapDiv) return;
            mapDiv.innerHTML = '';

            const width = 1600; 
            const height = 900; 

            const svg = d3.select(mapDiv).append("svg")
                .attr("viewBox", `0 0 ${width} ${height}`)
                .attr("preserveAspectRatio", "xMidYMid meet")
                .style("width", "100%")
                .style("height", "auto")
                .style("background-color", "#e6f0f8") 
                .style("font-family", "'Roboto', Arial, sans-serif"); 
            
            state.svg = svg;

            const defs = svg.append("defs");
            state.gradientId = `grad-${Math.random().toString(36).substr(2, 5)}`;
            defs.append("linearGradient")
                .attr("id", state.gradientId)
                .attr("x1", "0%").attr("y1", "100%")
                .attr("x2", "0%").attr("y2", "0%");

            const gMain = svg.append("g").attr("class", "map-layer"); 
            state.gMain = gMain;
            
            state.gPaths = gMain.append("g").attr("class", "paths-layer");
            state.gLabels = gMain.append("g").attr("class", "labels-layer").style("display", "none").style("pointer-events", "none");
            state.gMarkers = gMain.append("g").attr("class", "markers-layer");
            
            state.gLegend = svg.append("g").attr("class", "siarhe-legend-group").attr("transform", `translate(30, 40)`); 
            state.gMarkerLegend = svg.append("g").attr("class", "siarhe-marker-legend-group").attr("transform", `translate(30, ${height - 180})`);

            const projection = d3.geoMercator().fitSize([width, height], state.geoData); 
            state.projection = projection;
            const path = d3.geoPath().projection(projection); 
            state.path = path; 

            let initialTransform = d3.zoomIdentity;
            if (cveEnt === '06' || cveEnt === '31') {
                let lons = [], lats = [];
                state.geoData.features.forEach(f => {
                    let center = d3.geoCentroid(f);
                    if (!isNaN(center[0]) && !isNaN(center[1])) { 
                        lons.push(center[0]); 
                        lats.push(center[1]); 
                    }
                });
                lons.sort(d3.ascending); 
                lats.sort(d3.ascending);
                
                if (lons.length > 0 && lats.length > 0) {
                    let medianLon = lons[Math.floor(lons.length / 2)];
                    let medianLat = lats[Math.floor(lats.length / 2)];
                    let targetK = cveEnt === '06' ? 5.5 : 1.6; 
                    let [x, y] = projection([medianLon, medianLat]);
                    let tx = width / 2 - x * targetK;
                    let ty = height / 2 - y * targetK;
                    initialTransform = d3.zoomIdentity.translate(tx, ty).scale(targetK);
                }
            }
            state.initialTransform = initialTransform;

            d3.selectAll(mapDiv.querySelectorAll(".siarhe-tooltip")).remove(); 
            state.tooltip = d3.select(mapDiv).append("div")
                .attr("class", "siarhe-tooltip")
                .style("opacity", 0)
                .style("display", "none")
                .style("position", "absolute")
                .style("pointer-events", "none")
                .style("z-index", "9999");

            state.gPaths.selectAll("path.siarhe-feature")
                .data(state.geoData.features).enter().append("path")
                .attr("d", path).attr("class", "siarhe-feature")
                .attr("stroke", "#fff").attr("stroke-width", "0.5").style("fill", app.colors.NULL)
                .on("mouseover", (e, d) => app.map.showTooltip(e, d, state, mapDiv)) 
                .on("mousemove", (e) => {
                    const [mx, my] = d3.pointer(e, mapDiv);
                    state.tooltip.style("left", (mx + 15) + "px").style("top", (my - 28) + "px");
                })
                .on("mouseout", () => { 
                    state.gPaths.selectAll("path.siarhe-feature").style("stroke", "#fff").style("stroke-width", "0.5"); 
                    state.tooltip.style("opacity", 0).style("display", "none"); 
                })
                .on("click", (e, d) => {
                    const ahora = Date.now();
                    if (ahora - state.lastClickTime < 400) { 
                        app.map.handleMapClick(d, state); 
                    } else { 
                        app.map.showTooltip(e, d, state, mapDiv); 
                    }
                    state.lastClickTime = ahora;
                });

            let maxZoom = state.initialTransform.k * 25; 
            if (maxZoom < 50) maxZoom = 50; 

            const zoom = d3.zoom()
                .scaleExtent([1, maxZoom]) 
                .on("zoom", (e) => {
                    gMain.attr("transform", e.transform);
                    const k = e.transform.k;
                    
                    state.gLabels.selectAll("text.siarhe-label")
                        .style("font-size", `${14 / k}px`)
                        .attr("stroke-width", 2.5 / k);
                    
                    let shrinkFactor = 1;
                    if (k > 8) shrinkFactor = 1 + ((k - 8) * 0.15); 
                    
                    const isMobile = window.innerWidth <= 767;
                    let baseSize = isMobile ? 350 : 250;

                    let symbolSize = (baseSize / shrinkFactor) / (k * k);
                    
                    let minSymbolSize = isMobile ? 0.08 : 0.05;
                    if (symbolSize < minSymbolSize) symbolSize = minSymbolSize;

                    const symbolCache = {};
                    state.activeMarkers.forEach(type => {
                        const styleCfg = app.utils.getMarkerStyle(type, state);
                        symbolCache[type] = d3.symbol().type(app.utils.getD3Shape(styleCfg.shape)).size(symbolSize)();
                    });

                    state.gMarkers.selectAll("path.siarhe-marker")
                        .attr("d", d => {
                            const config = state.markerLabels[d.tipo] || {};
                            if (config.tipo === 'espectro') {
                                let val = d._agrupados_total || 1;
                                if (config.espectro_col && config.espectro_col.trim() !== '') {
                                    const realCol = Object.keys(d._raw).find(k => k.toLowerCase().trim() === config.espectro_col.toLowerCase().trim());
                                    val = realCol ? parseFloat(d._raw[realCol]) || 0 : 0;
                                }
                                const max = (state.espectroMaxVals && state.espectroMaxVals[d.tipo]) ? state.espectroMaxVals[d.tipo] : 1;
                                const scaleArea = d3.scaleLinear().domain([0, max]).range([symbolSize * 0.3, symbolSize * 15]).clamp(true);
                                const styleCfg = app.utils.getMarkerStyle(d.tipo, state);
                                return d3.symbol().type(app.utils.getD3Shape(styleCfg.shape)).size(scaleArea(val))();
                            }
                            return symbolCache[d.tipo];
                        })
                        .attr("stroke-width", 1.5 / k);
                });
            
            svg.call(zoom).on("dblclick.zoom", null); 
            state.zoom = zoom;
            svg.call(zoom.transform, state.initialTransform);
            
            app.map.renderZoomButtons(container, mapDiv, svg, zoom, state); 
            app.map.updateVisuals(container, state);
        },

        // ==========================================
        // 2. ACTUALIZACIÓN VISUAL (Colores y Leyendas)
        // ==========================================

        updateVisuals: function(container, state) {
            const metric = state.currentMetric;
            const mode = state.colorMode || 'quartiles'; 
            
            const values = state.csvData
                .filter(d => !d.isTotal && !d.isSpecial && d[metric] !== null && d[metric] > 0)
                .map(d => d[metric])
                .sort(d3.ascending);
            
            if (values.length === 0) {
                state.gPaths.selectAll("path.siarhe-feature").transition().duration(500)
                    .style("fill", d => {
                        let cve = app.utils.getGeoKey(d.properties, state.isNacional);
                        const row = state.dataMap.get(cve);
                        if (!row || row[metric] === null || row[metric] === undefined) return app.colors.NULL;
                        return app.colors.ZERO;
                    });
                app.map.renderLegend(state, {min: 0, max: 0});
            } else {
                let colorScale;
                let stats = {};

                const min = d3.min(values); 
                const max = d3.max(values);

                if (mode === 'quartiles') {
                    let q1 = d3.quantile(values, 0.25); 
                    let q2 = d3.quantile(values, 0.50); 
                    let q3 = d3.quantile(values, 0.75);
                    
                    let domain = [min, q1, q2, q3, max];
                    
                    if (min === max) {
                        domain = [min * 0.2, min * 0.4, min * 0.6, min * 0.8, max];
                    } else {
                        for (let i = 1; i < domain.length; i++) {
                            if (domain[i] <= domain[i-1]) domain[i] = domain[i-1] + 0.000001; 
                        }
                    }
                    
                    colorScale = d3.scaleLinear().domain(domain).range(app.colors.RANGE).clamp(true);
                    stats = { min, q1, q2, q3, max };

                } else {
                    let domain = [min, max];
                    if (min === max) domain = [0, max];
                    
                    colorScale = d3.scaleLinear().domain(domain).range(app.colors.MONO).clamp(true);
                    stats = { min, max };
                }

                state.gPaths.selectAll("path.siarhe-feature").transition().duration(500)
                    .style("fill", d => {
                        let cve = app.utils.getGeoKey(d.properties, state.isNacional);
                        const row = state.dataMap.get(cve);
                        
                        if (!row) return app.colors.NULL; 
                        if (row[metric] === null || row[metric] === undefined) return app.colors.NULL; 
                        if (row[metric] === 0) return app.colors.ZERO; 
                        
                        return colorScale(row[metric]);
                    });

                app.map.renderLegend(state, stats);
            }

            app.map.renderLabels(state);
        },

        renderLabels: function(state) {
            let currentK = 1; 
            if (state.svg) { try { currentK = d3.zoomTransform(state.svg.node()).k; } catch(e) {} }

            const maxAreaFeatures = new Map();
            state.geoData.features.forEach(d => {
                let cve = app.utils.getGeoKey(d.properties, state.isNacional); 
                let area = d3.geoArea(d);
                if (!maxAreaFeatures.has(cve) || area > maxAreaFeatures.get(cve).area) { 
                    maxAreaFeatures.set(cve, { feature: d, area: area }); 
                }
            });
            const featuresUnicas = Array.from(maxAreaFeatures.values()).map(item => item.feature);

            const labels = state.gLabels.selectAll("text.siarhe-label").data(featuresUnicas);
            labels.exit().remove();
            
            labels.enter().append("text")
                .attr("class", "siarhe-label")
                .merge(labels)
                .attr("x", d => { const c = state.path.centroid(d); return isNaN(c[0]) ? 0 : c[0]; })
                .attr("y", d => { const c = state.path.centroid(d); return isNaN(c[1]) ? 0 : c[1]; })
                .attr("text-anchor", "middle")
                .attr("fill", "#0F172A")
                .attr("stroke", "#ffffff")
                .attr("stroke-width", 2.5 / currentK)
                .attr("paint-order", "stroke fill")
                .attr("stroke-linejoin", "round")
                .style("font-size", `${14 / currentK}px`)
                .style("font-weight", "bold")
                .text(d => { 
                    let cve = app.utils.getGeoKey(d.properties, state.isNacional); 
                    const row = state.dataMap.get(cve); 
                    return row ? row.estado : (d.properties.NOMGEO || d.properties.NOM_ENT || ""); 
                });
        },

        renderLegend: function(state, stats) {
            const g = state.gLegend; 
            g.html("");
            
            const info = state.metricas[state.currentMetric] || {};
            const label = info.abrev || info.label || 'Indicador';
            const mode = state.colorMode || 'quartiles';

            const gradient = state.svg.select(`#${state.gradientId}`);
            gradient.selectAll("stop").remove();
            
            let colorArr = [];
            let domainVals = [];

            if (mode === 'quartiles') {
                colorArr = app.colors.RANGE;
                domainVals = [stats.min, stats.q1, stats.q2, stats.q3, stats.max];
            } else {
                colorArr = app.colors.MONO;
                const stepVal = (stats.max - stats.min) / 4;
                domainVals = [stats.min, stats.min + stepVal, stats.min + stepVal*2, stats.min + stepVal*3, stats.max];
            }

            gradient.selectAll("stop")
                .data(colorArr)
                .enter().append("stop")
                .attr("offset", (d, i) => `${(i / (colorArr.length - 1)) * 100}%`)
                .attr("stop-color", d => d);

            g.append("rect").attr("x", -10).attr("y", -25).attr("width", 140).attr("height", 270)
             .attr("fill", "rgba(255,255,255,0.9)").attr("rx", 5).style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.1))");
            g.append("text").attr("x", 0).attr("y", -10).text(label).style("font-size", "12px").style("font-weight", "bold").style("fill", "#0F172A");
            
            const h = 150, w = 15, step = h/4;
            g.append("rect").attr("x", 0).attr("y", 10).attr("width", w).attr("height", h)
             .style("fill", `url(#${state.gradientId})`).style("stroke", "#ccc");

            domainVals.forEach((v, i) => {
                const y = 10 + h - (i * step);
                g.append("line").attr("x1", w).attr("x2", w+5).attr("y1", y).attr("y2", y).style("stroke", "#666");
                let valStr = (v !== undefined && v !== null) ? v.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}) : "0.00";
                g.append("text").attr("x", w+8).attr("y", y+4).text(valStr).style("font-size", "11px").style("fill", "#475569");
            });

            const g0 = g.append("g").attr("transform", `translate(0, ${h+40})`);
            g0.append("rect").attr("width", 12).attr("height", 12).style("fill", app.colors.ZERO);
            g0.append("text").attr("x", 18).attr("y", 10).text("0.00").style("font-size", "11px").style("fill", "#475569");

            const gN = g.append("g").attr("transform", `translate(0, ${h+65})`);
            gN.append("rect").attr("width", 12).attr("height", 12).style("fill", app.colors.NULL);
            gN.append("text").attr("x", 18).attr("y", 10).text("S/D").style("font-size", "11px").style("fill", "#475569");
        },

        // ==========================================
        // 3. TOOLTIPS Y EVENTOS (DISEÑO DINÁMICO)
        // ==========================================

        showTooltip: function(event, d, state, mapDiv) {
            // Este es el tooltip de Polígonos (Estados o Municipios)
            let cve = app.utils.getGeoKey(d.properties, state.isNacional);
            const row = state.dataMap.get(cve);
            const nombre = row ? row.estado : (d.properties.NOMGEO || d.properties.NOM_ENT || "Sin Datos");
            
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
                            <span style="font-size:12px; opacity:0.9;">${b.label}:</span> 
                            <strong style="color:${hlColor}; font-size:14px; margin-left:15px;">${b.value}</strong>
                        </div>`;
                } else {
                    htmlStandard += `
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:12px;">
                            <span style="opacity:0.8; margin-right:15px;">${b.label}:</span> 
                            <b>${b.value}</b>
                        </div>`;
                }
            });

            // 🌟 MOTOR DE RESUMEN GEOGRÁFICO (ROLLUP) SEGURO 🌟
            let htmlResumenMarcadores = '';
            try {
                if (state.activeMarkers && state.activeMarkers.size > 0) {
                    // Obtener la clave de la entidad de forma segura
                    const wrapper = mapDiv.closest('.siarhe-viz-wrapper');
                    const mapCveEnt = wrapper && wrapper.dataset.cveEnt ? wrapper.dataset.cveEnt.toString().padStart(2, '0') : '';

                    state.activeMarkers.forEach(type => {
                        const config = state.markerLabels[type] || {};
                        
                        // Solo procesar si el usuario activó la opción "resumen_geo"
                        if (config.resumen_geo === true || config.resumen_geo === 'true') {
                            const markerData = state.markersData[type] || [];
                            
                            let totalPuntos = 0;
                            let totalRegistros = 0;
                            let sumReglas = {};

                            // Filtrar marcadores dentro del polígono actual (cve)
                            markerData.forEach(mk => {
                                let match = false;
                                const mkEnt = mk.cve_ent ? mk.cve_ent.toString().padStart(2, '0') : null;

                                if (state.isNacional) {
                                    // En mapa nacional: cruce por CVE_ENT
                                    if (mkEnt === cve) match = true;
                                } else {
                                    // En mapa estatal: cruce por CVE_ENT + CVE_MUN
                                    let mkMun = null;
                                    if (mk._raw) {
                                        mkMun = app.utils.getColValue(mk._raw, ['cve_mun', 'cve mun', 'municipio', 'clave_municipio']);
                                        if (mkMun) mkMun = mkMun.toString().padStart(3, '0');
                                    }
                                    if (mkEnt === mapCveEnt && mkMun === cve) match = true;
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
                                const mkLabelColor = app.utils.getMarkerStyle(type, state).fill;
                                const mkLabelNombre = config.label || type;

                                htmlResumenMarcadores += `
                                    <div style="margin-top:12px; padding-top:8px; border-top:1px dashed rgba(255,255,255,0.3);">
                                        <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px; color:${mkLabelColor}; font-weight:bold; display:flex; align-items:center; gap:5px;">
                                            <div style="width:8px; height:8px; background:${mkLabelColor}; border-radius:50%;"></div>
                                            ${mkLabelNombre}
                                        </div>
                                `;

                                // 🌟 SOLUCIÓN: SIEMPRE MOSTRAR LOS PUNTOS FÍSICOS 🌟
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
                                                    <span style="opacity:0.75;">↳ ${rLabel}:</span> 
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

            let html = `
                <div style="font-family:'Roboto', sans-serif;">
                    <div style="font-weight:bold; font-size:14px; margin-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:4px;">
                        ${nombre}
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
                    .style("opacity", 1)
                    .style("left", (mx + 15) + "px")
                    .style("top", (my - 28) + "px");
            }
        },

        handleMapClick: function(d, state) {
            let cve = app.utils.getGeoKey(d.properties, state.isNacional);
            if (state.entityUrls && state.entityUrls[cve]) {
                window.location.href = state.entityUrls[cve];
            }
        },

        updateMarkers: function(state) {
            let allPoints = [];
            state.activeMarkers.forEach(type => { 
                if(state.markersData[type]) allPoints = allPoints.concat(state.markersData[type]); 
            });

            let currentK = 1; 
            if (state.svg) { try { currentK = d3.zoomTransform(state.svg.node()).k; } catch(e) {} }
            
            let shrinkFactor = 1;
            if (currentK > 8) shrinkFactor = 1 + ((currentK - 8) * 0.15); 
            
            const isMobile = window.innerWidth <= 767;
            let baseSize = isMobile ? 350 : 250;

            let symbolSize = (baseSize / shrinkFactor) / (currentK * currentK);
            let minSymbolSize = isMobile ? 0.08 : 0.05;
            if (symbolSize < minSymbolSize) symbolSize = minSymbolSize;

            state.espectroMaxVals = {};
            state.activeMarkers.forEach(type => {
                const config = state.markerLabels[type] || {};
                if (config.tipo === 'espectro') {
                    const data = state.markersData[type] || [];
                    let max = 0;
                    if (config.espectro_col && config.espectro_col.trim() !== '') {
                        max = d3.max(data, d => {
                             const realCol = Object.keys(d._raw).find(k => k.toLowerCase().trim() === config.espectro_col.toLowerCase().trim());
                             return realCol ? parseFloat(d._raw[realCol]) || 0 : 0;
                        });
                    } else {
                        max = d3.max(data, d => d._agrupados_total || 1);
                    }
                    state.espectroMaxVals[type] = max || 1;
                }
            });

            const markers = state.gMarkers.selectAll("path.siarhe-marker").data(allPoints);
            markers.exit().remove();
            
            markers.enter().append("path")
                .attr("class", "siarhe-marker")
                .merge(markers)
                .attr("d", d => {
                    const config = state.markerLabels[d.tipo] || {};
                    let finalSize = symbolSize; 
                    
                    if (config.tipo === 'espectro') {
                        let val = d._agrupados_total || 1;
                        if (config.espectro_col && config.espectro_col.trim() !== '') {
                            const realCol = Object.keys(d._raw).find(k => k.toLowerCase().trim() === config.espectro_col.toLowerCase().trim());
                            val = realCol ? parseFloat(d._raw[realCol]) || 0 : 0;
                        }
                        const max = state.espectroMaxVals[d.tipo] || 1;
                        const scaleArea = d3.scaleLinear().domain([0, max]).range([symbolSize * 0.3, symbolSize * 15]).clamp(true);
                        finalSize = scaleArea(val);
                    }

                    const styleCfg = app.utils.getMarkerStyle(d.tipo, state);
                    return d3.symbol().type(app.utils.getD3Shape(styleCfg.shape)).size(finalSize)();
                })
                .attr("transform", d => {
                    const coords = state.projection([d.lon, d.lat]);
                    return coords ? `translate(${coords[0]}, ${coords[1]})` : "translate(0,0)";
                })
                .attr("fill", d => {
                    const styleCfg = app.utils.getMarkerStyle(d.tipo, state);
                    const config = state.markerLabels[d.tipo] || {};
                    return (config.tipo === 'espectro') ? app.map.hexToRgba(styleCfg.fill, 0.75) : styleCfg.fill;
                })
                .attr("stroke", d => app.utils.getMarkerStyle(d.tipo, state).stroke)
                .attr("stroke-width", 1.5 / currentK)
                .style("cursor", "pointer")
                .on("mouseover", function(e, d) {
                    let k = 1; if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch(err) {} }
                    d3.select(this).attr("stroke-width", 3 / k).raise(); 

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
                        else zonaTexto = d.estrato;
                    }
                    const titleLabel = zonaTexto ? `Establecimiento en Zona ${zonaTexto}` : (state.markerLabels[d.tipo] ? state.markerLabels[d.tipo].label : d.tipo);

                    let blocks = {};
                    
                    if (tt.mk_inst !== false && d.institucion) blocks['mk_inst'] = { label: 'Institución', value: d.institucion };
                    if (tt.mk_clues !== false && d.clues) blocks['mk_clues'] = { label: 'CLUES', value: d.clues };
                    if (tt.mk_nivel !== false && d.nivel_atencion) blocks['mk_nivel'] = { label: 'Nivel', value: d.nivel_atencion };
                    if (tt.mk_juris !== false && d.jurisdiccion) blocks['mk_juris'] = { label: 'Jurisdicción', value: d.jurisdiccion };
                    
                    if (tt.mk_tipo !== false && (d.tipo_estab || d.tipologia)) {
                        let valTipo = [];
                        if (d.tipo_estab) valTipo.push(d.tipo_estab);
                        if (d.tipologia) valTipo.push(d.tipologia);
                        blocks['mk_tipo'] = { label: 'Tipo', value: valTipo.join(' - ') };
                    }
                    
                    if (tt.mk_mun !== false && (d._raw)) {
                        let ubicacion = [];
                        const colEntidad = app.utils.getColValue(d._raw, ['entidad', 'nom_ent']);
                        const colLocalidad = app.utils.getColValue(d._raw, ['localidad', 'nom_loc']);
                        if (colEntidad) ubicacion.push(colEntidad);
                        if (d.municipio) ubicacion.push(d.municipio);
                        if (colLocalidad) ubicacion.push(colLocalidad);
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
                                    <span style="font-size:11px; opacity:0.8;">${b.label}:</span><br>
                                    <strong style="color:${hlColor}; font-size:12px;">${b.value}</strong>
                                </div>`;
                        } else {
                            htmlStandard += `
                                <div style="font-size:11px; line-height: 1.4; margin-bottom: 3px;">
                                    <span style="color:#06B6D4; opacity:0.9;">${b.label}:</span> 
                                    <strong style="color:${txtColor}; font-family:'IBM Plex Sans', sans-serif;">${b.value}</strong>
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
                                                    <span style="opacity:0.9;">${ruleLabel}:</span> 
                                                    <strong style="color:#f8fafc;">${ruleCount.toLocaleString('es-MX')}</strong>
                                                   </div>`;
                                }
                            });
                        }
                        htmlReglas += `</div>`;
                    }

                    let html = `<div style="font-family:'Roboto', sans-serif; color:${txtColor};">`;
                    html += `<div style="color: #06B6D4; font-weight:bold; font-size:12px; margin-bottom:4px; padding-bottom:4px;">${titleLabel}</div>`;
                    html += `<div style="font-weight:bold; font-size:13px; font-family:'IBM Plex Sans', sans-serif; text-transform: uppercase; margin-bottom: 8px;">${d.nombre || 'Desconocido'}</div>`;
                    html += htmlStandard;
                    html += htmlHighlight;
                    html += htmlReglas; 
                    html += `</div>`;

                    const mapDiv = document.querySelector('.siarhe-map-container');
                    const [mx, my] = d3.pointer(e, mapDiv);

                    state.tooltip.html(html)
                        .style("background-color", finalBgColor)
                        .style("color", txtColor)
                        .style("border-radius", "6px")
                        .style("padding", "10px 14px")
                        .style("box-shadow", "0 4px 15px rgba(0,0,0,0.2)")
                        .style("display", "block")
                        .style("opacity", 1)
                        .style("left", (mx + 10) + "px")
                        .style("top", (my - 20) + "px");
                })
                .on("mouseout", function(e, d) {
                    let k = 1; if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch(err) {} }
                    d3.select(this).attr("stroke-width", 1.5 / k); 
                    state.tooltip.style("display", "none");
                });

            app.map.renderMarkerLegend(state);
        },

        renderMarkerLegend: function(state) {
            const g = state.gMarkerLegend;
            g.html(""); 

            const actives = Array.from(state.activeMarkers);
            if (actives.length === 0) return; 

            g.append("rect")
                .attr("x", -10).attr("y", -20)
                .attr("width", 230) 
                .attr("height", (actives.length * 20) + 30)
                .attr("fill", "rgba(255,255,255,0.9)")
                .attr("rx", 5)
                .style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.1))");
            
            g.append("text").attr("x", 0).attr("y", -5).text("Marcadores Activos").style("font-size", "12px").style("font-weight", "bold").style("fill", "#0F172A");

            actives.forEach((tipo, i) => {
                const yPos = 12 + (i * 20);
                const styleCfg = app.utils.getMarkerStyle(tipo, state);
                
                const markerLabel = state.markerLabels[tipo] ? state.markerLabels[tipo].label : tipo;

                g.append("path")
                    .attr("d", d3.symbol().type(app.utils.getD3Shape(styleCfg.shape)).size(50)())
                    .attr("transform", `translate(5, ${yPos})`)
                    .attr("fill", styleCfg.fill)
                    .attr("stroke", styleCfg.stroke)
                    .attr("stroke-width", 1);
                
                g.append("text").attr("x", 15).attr("y", yPos + 4).text(markerLabel).style("font-size", "11px").style("fill", "#475569");
            });
        },

        renderZoomButtons: function(container, mapDiv, svg, zoom, state) {
            const ctrlDiv = document.createElement('div'); 
            ctrlDiv.className = 'zoom-controles'; 
            mapDiv.appendChild(ctrlDiv);
            
            const createBtn = (l, t, cb) => { 
                const b = document.createElement('button'); 
                b.className = 'boton'; b.innerHTML = l; b.title = t; b.onclick = cb; 
                ctrlDiv.appendChild(b); 
            };
            
            createBtn('+', 'Acercar', (e) => { e.preventDefault(); svg.transition().call(zoom.scaleBy, 1.5); });
            createBtn('–', 'Alejar', (e) => { e.preventDefault(); svg.transition().call(zoom.scaleBy, 0.6); });
            createBtn('⟳', 'Reset', (e) => { e.preventDefault(); svg.transition().duration(750).call(zoom.transform, state.initialTransform); });
            
            const btnFullscreen = document.createElement('button');
            btnFullscreen.className = 'boton'; 
            btnFullscreen.innerHTML = '⛶'; 
            btnFullscreen.title = 'Pantalla Completa';
            const mapContainer = mapDiv; 

            btnFullscreen.onclick = (e) => {
                e.preventDefault();
                
                const controlsEl = container.querySelector('.siarhe-controls');
                const controlsPlaceholder = container.querySelector('.siarhe-controls-placeholder');

                if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                    if (mapContainer.requestFullscreen) { mapContainer.requestFullscreen(); } 
                    else if (mapContainer.webkitRequestFullscreen) { mapContainer.webkitRequestFullscreen(); } 
                    else if (mapContainer.msRequestFullscreen) { mapContainer.msRequestFullscreen(); }
                    
                    btnFullscreen.innerHTML = '🗗'; 
                    mapContainer.classList.add('is-fullscreen');
                    
                    if (controlsEl) {
                        mapContainer.appendChild(controlsEl);
                        controlsEl.classList.add('is-fullscreen-mode');
                    }
                } else {
                    if (document.exitFullscreen) { document.exitFullscreen(); } 
                    else if (document.webkitExitFullscreen) { document.webkitExitFullscreen(); }
                    else if (document.msExitFullscreen) { document.msExitFullscreen(); }
                    
                    btnFullscreen.innerHTML = '⛶'; 
                    mapContainer.classList.remove('is-fullscreen');
                    
                    if (controlsEl && controlsPlaceholder) {
                        controlsPlaceholder.appendChild(controlsEl);
                        controlsEl.classList.remove('is-fullscreen-mode');
                    }
                }
            };

            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement) {
                    btnFullscreen.innerHTML = '⛶'; 
                    mapContainer.classList.remove('is-fullscreen');
                    
                    const controlsEl = mapContainer.querySelector('.siarhe-controls');
                    const controlsPlaceholder = container.querySelector('.siarhe-controls-placeholder');
                    
                    if (controlsEl && controlsPlaceholder) {
                        controlsPlaceholder.appendChild(controlsEl);
                        controlsEl.classList.remove('is-fullscreen-mode');
                    }
                }
            });
            
            ctrlDiv.appendChild(btnFullscreen);

            if (!state.isNacional && state.homeUrl) {
                createBtn('🏠', 'Ir a Nacional', (e) => { e.preventDefault(); window.location.href = state.homeUrl; });
            }
        },

        downloadMapAsPNG: function(container, state, withLabels) {
            const mapDiv = container.querySelector('.siarhe-map-container');
            const svgNode = mapDiv.querySelector('svg');
            if (!svgNode) return;

            if (withLabels) state.gLabels.style("display", "block");

            setTimeout(() => {
                const clone = svgNode.cloneNode(true);
                clone.setAttribute('width', 1600); 
                clone.setAttribute('height', 900);

                const svgData = new XMLSerializer().serializeToString(clone);
                const canvas = document.createElement("canvas");
                canvas.width = 1920; 
                canvas.height = 1080; 
                const ctx = canvas.getContext("2d"); 

                const img = new Image();
                img.onload = function() {
                    ctx.fillStyle = "#e6f0f8"; ctx.fillRect(0, 0, 1920, 1080); 
                    ctx.fillStyle = "#ffffff"; ctx.fillRect(0, 0, 1920, 120); 
                    ctx.fillRect(0, 940, 1920, 140); 
                    
                    ctx.drawImage(img, 160, 120, 1600, 820); 
                    
                    const metricInfo = state.metricas[state.currentMetric];
                    const anioNode = document.querySelector('.siarhe-dynamic-year'); 
                    const anio = anioNode ? anioNode.innerText : new Date().getFullYear();
                    const titleNode = container.querySelector('h2.siarhe-title'); 
                    const entidadNombre = titleNode ? titleNode.innerText.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '').trim() : "México";
                    
                    const titleText = `${metricInfo.fullLabel} en ${entidadNombre} (${anio})`;
                    ctx.fillStyle = "#0A66C2"; 
                    ctx.font = "bold 38px 'IBM Plex Sans', Arial, sans-serif"; 
                    ctx.textAlign = "center"; 
                    ctx.fillText(titleText, 1920 / 2, 75);
                    
                    let refText = "Fuente de Datos: SIARHE.";
                    const refNodes = container.querySelectorAll('.siarhe-ref-col p');
                    if(refNodes.length > 0) { refText = refNodes[0].innerText.replace(/\n/g, ' | '); }

                    ctx.fillStyle = "#475569"; 
                    ctx.font = "20px 'Roboto', sans-serif"; 
                    ctx.textAlign = "center";
                    
                    let words = refText.split(' '), line = '', y = 1000;
                    for(let n = 0; n < words.length; n++) {
                      let testLine = line + words[n] + ' '; 
                      let metrics = ctx.measureText(testLine); 
                      let testWidth = metrics.width;
                      if (testWidth > 1800 && n > 0) { 
                          ctx.fillText(line, 1920 / 2, y); line = words[n] + ' '; y += 28;
                      } else { line = testLine; }
                    }
                    ctx.fillText(line, 1920 / 2, y);
                    
                    const labelClean = (metricInfo.label || metricInfo.fullLabel).normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\./g, "").replace(/\s+/g, '_');
                    const entidadClean = entidadNombre.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, '_');
                    const nombreArchivo = `${labelClean}_${entidadClean}_${anio}${withLabels ? '_Etiquetas' : ''}.png`;
                    
                    const a = document.createElement("a"); a.download = nombreArchivo; a.href = canvas.toDataURL("image/png"); a.click();
                    if (withLabels) state.gLabels.style("display", "none");
                };
                img.src = "data:image/svg+xml;base64," + btoa(unescape(encodeURIComponent(svgData)));
            }, 150);
        }
    };

})(window.SiarheDataViz);