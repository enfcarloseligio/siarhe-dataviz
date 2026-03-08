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
                        .attr("d", d => symbolCache[d.tipo])
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
            let cve = app.utils.getGeoKey(d.properties, state.isNacional);
            const row = state.dataMap.get(cve);
            const nombre = row ? row.estado : (d.properties.NOMGEO || d.properties.NOM_ENT || "Sin Datos");
            
            // OBTENER CONFIGURACIONES DE DISEÑO Y ORDEN
            const tt = state.tooltipConfig || {}; 
            const order = tt.geo_order || ['pob', 'abs', 'rate'];
            const hlVar = tt.highlight_var || 'rate'; // 'none', 'pob', 'abs', 'rate'
            const hlColor = tt.highlight_color || '#06b6d4';
            const bgColor = tt.bg_color || '#0f172a';
            const bgOpacity = (tt.bg_opacity !== undefined ? parseInt(tt.bg_opacity, 10) : 90) / 100;
            const txtColor = tt.text_color || '#f8fafc';
            
            const finalBgColor = app.map.hexToRgba(bgColor, bgOpacity);
            
            const mKey = state.currentMetric; 
            const pKey = state.metricas[mKey] ? state.metricas[mKey].pair : mKey; 
            
            // CONSTRUIR BLOQUES DE DATOS DISPONIBLES
            let blocks = {};
            
            if (tt.geo_pob !== false) {
                blocks['pob'] = {
                    label: 'Población',
                    value: row && row.poblacion ? row.poblacion.toLocaleString('es-MX') : '—'
                };
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

            // ARMAR HTML SEGÚN EL ORDEN Y EL DESTACADO
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

            // Ensamblar caja final
            let html = `
                <div style="font-family:'Roboto', sans-serif;">
                    <div style="font-weight:bold; font-size:14px; margin-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:4px;">
                        ${nombre}
                    </div>
                    ${htmlStandard}
                    ${htmlHighlight}
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

            const markers = state.gMarkers.selectAll("path.siarhe-marker").data(allPoints);
            markers.exit().remove();
            
            markers.enter().append("path")
                .attr("class", "siarhe-marker")
                .merge(markers)
                .attr("d", d => {
                    const styleCfg = app.utils.getMarkerStyle(d.tipo, state);
                    return d3.symbol().type(app.utils.getD3Shape(styleCfg.shape)).size(symbolSize)();
                })
                .attr("transform", d => {
                    const coords = state.projection([d.lon, d.lat]);
                    return coords ? `translate(${coords[0]}, ${coords[1]})` : "translate(0,0)";
                })
                .attr("fill", d => app.utils.getMarkerStyle(d.tipo, state).fill)
                .attr("stroke", d => app.utils.getMarkerStyle(d.tipo, state).stroke)
                .attr("stroke-width", 1.5 / currentK)
                .style("cursor", "pointer")
                .on("mouseover", function(e, d) {
                    let k = 1; if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch(err) {} }
                    d3.select(this).attr("stroke-width", 3 / k).raise(); 

                    // OBTENER DISEÑO PARA TOOLTIP DE MARCADORES
                    const tt = state.tooltipConfig || {}; 
                    const hlColor = tt.highlight_color || '#06b6d4';
                    const bgColor = tt.bg_color || '#0f172a';
                    const bgOpacity = (tt.bg_opacity !== undefined ? parseInt(tt.bg_opacity, 10) : 90) / 100;
                    const txtColor = tt.text_color || '#f8fafc';
                    const finalBgColor = app.map.hexToRgba(bgColor, bgOpacity);
                    
                    // 🌟 LECTURA DINÁMICA DEL NOMBRE DEL MARCADOR 🌟
                    const markerLabel = state.markerLabels[d.tipo] ? state.markerLabels[d.tipo].label : d.tipo;

                    let html = `<div style="font-family:'Roboto', sans-serif; color:${txtColor};">`;
                    html += `<div style="color: ${hlColor}; font-weight:bold; font-size:13px; margin-bottom:4px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:4px;">${markerLabel}</div>`;
                    html += `<div style="font-size:12px; margin-top:4px;">
                        <div style="font-weight:bold; font-family:'IBM Plex Sans', sans-serif;">${d.nombre || 'Desconocido'}</div>`;
                        
                    if (tt.mk_inst !== false || tt.mk_mun !== false) {
                        let instMun = [];
                        if (tt.mk_inst !== false && d.institucion) instMun.push(d.institucion);
                        if (tt.mk_mun !== false && d.municipio) instMun.push(d.municipio);
                        if (instMun.length > 0) {
                            html += `<div style="opacity:0.8; font-size:11px; margin-top:2px;">${instMun.join(' - ')}</div>`;
                        }
                    }
                    
                    if (tt.mk_clues !== false) {
                        html += `<div style="font-size:10px; margin-top:2px; opacity:0.6;">CLUES: ${d.clues}</div>`;
                    }
                    
                    if ((d.tipo_estab || d.nivel_atencion || d.jurisdiccion) && (tt.mk_tipo !== false || tt.mk_nivel !== false || tt.mk_juris !== false)) {
                        html += `<div style="margin-top:6px; padding-top:4px; border-top:1px dashed rgba(255,255,255,0.2); opacity:0.9;">`;
                        if(d.tipo_estab && tt.mk_tipo !== false) html += `<div style="font-size:11px;"><strong style="opacity:0.7;">Tipo:</strong> ${d.tipo_estab}</div>`;
                        if(d.tipologia && tt.mk_tipo !== false) html += `<div style="font-size:11px;"><strong style="opacity:0.7;">Tipología:</strong> ${d.tipologia}</div>`;
                        if(d.nivel_atencion && tt.mk_nivel !== false) html += `<div style="font-size:11px;"><strong style="opacity:0.7;">Nivel:</strong> ${d.nivel_atencion}</div>`;
                        if(d.jurisdiccion && tt.mk_juris !== false) html += `<div style="font-size:10px;"><strong style="opacity:0.7;">Jurisdicción:</strong> ${d.jurisdiccion}</div>`;
                        html += `</div>`;
                    }
                    html += `</div></div>`;

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
                
                // 🌟 LECTURA DINÁMICA DEL NOMBRE DEL MARCADOR EN LA LEYENDA 🌟
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