/**
 * SIARHE DataViz - Módulo de Mapa (Map & D3)
 * ------------------------------------------------------------------
 * Gestiona la representación visual de los datos GeoJSON y CSV usando D3.js.
 * Se encarga del renderizado de los polígonos, la lógica de colores,
 * el sistema de zoom elástico y la exportación a PNG.
 * (Los tooltips ahora se gestionan en siarhe-tooltips.js)
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    app.map = {
        
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
            if(hex.startsWith('rgba') || hex.startsWith('rgb')) return hex;
            return `rgba(15, 23, 42, ${alpha})`; 
        },

        // 🌟 MEJORA UX: Calcula los márgenes negros del SVG y ancla las leyendas a las esquinas físicas
        initResponsiveLegends: function(container, state) {
            const mapDiv = container.querySelector('.siarhe-map-container');
            if (!mapDiv) return;

            const observer = new ResizeObserver(() => {
                if (!state.gLegend || !state.gMarkerLegend) return;
                
                const rect = mapDiv.getBoundingClientRect();
                // Protección críctica contra "División por Cero" si el mapa está oculto en otra pestaña
                if (rect.width <= 0 || rect.height <= 0) return; 
                
                const svgW = 1600;
                const svgH = 900;
                
                let scale = Math.min(rect.width / svgW, rect.height / svgH);
                if (scale <= 0) return;

                let actualW = svgW * scale;
                let actualH = svgH * scale;

                let emptyX = (rect.width - actualW) / 2;
                let emptyY = (rect.height - actualH) / 2;

                let svgOffsetX = -(emptyX / scale);
                let svgOffsetY = -(emptyY / scale);

                // 🌟 CORRECCIÓN 1: Margen de seguridad dinámico para evitar cortes en Fullscreen
                const isFullscreen = mapDiv.classList.contains('is-fullscreen');
                const padValue = isFullscreen ? 40 : 20;

                let padX = padValue / scale; 
                let padY = padValue / scale;

                // Lógica de magnificación para Fullscreen en Móvil
                let isMobile = window.innerWidth <= 767;
                let legendScale = (isMobile && isFullscreen) ? 1.4 : 1; 

                // Leyenda de Colores (Arriba Izquierda)
                state.gLegend.attr("transform", `translate(${svgOffsetX + padX}, ${svgOffsetY + padY}) scale(${legendScale})`);
                
                // Leyenda de Marcadores (Abajo Izquierda)
                let bottomY = svgH + (emptyY / scale) - padY;
                state.gMarkerLegend.attr("transform", `translate(${svgOffsetX + padX}, ${bottomY}) scale(${legendScale})`);
            });
            
            observer.observe(mapDiv);
            state.legendObserver = observer;
        },

        render: function(container, state, cveEnt) {
            // Sin geometrias no hay nada que dibujar: avisamos en vez de romper el render
            if (!state.geoData || !Array.isArray(state.geoData.features) || state.geoData.features.length === 0) {
                const emptyDiv = container.querySelector('.siarhe-map-container');
                if (emptyDiv) emptyDiv.textContent = 'No hay datos geográficos disponibles para esta vista.';
                return;
            }
            
            // CSS 

            const mapDiv = container.querySelector('.siarhe-map-container');
            if (!mapDiv) return;
            mapDiv.innerHTML = '';

            const width = 1600; 
            const height = 900; 

            const svg = d3.select(mapDiv).append("svg")
                .attr("viewBox", `0 0 ${width} ${height}`)
                .attr("preserveAspectRatio", "xMidYMid meet")
                .style("width", "100%")
                .style("height", "100%")
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
            state.gLocPaths = gMain.append("g").attr("class", "loc-paths-layer");
            
            state.gLabels = gMain.append("g").attr("class", "labels-layer").style("display", "none").style("pointer-events", "none");
            state.gMarkers = gMain.append("g").attr("class", "markers-layer");
            
            // Grupos SVG con posición inicial segura (el ResizeObserver lo moverá de inmediato)
            state.gLegend = svg.append("g").attr("class", "siarhe-legend-group").attr("transform", "translate(20, 20)"); 
            state.gMarkerLegend = svg.append("g").attr("class", "siarhe-marker-legend-group").attr("transform", `translate(20, ${height - 20})`);

            const projection = d3.geoMercator().fitSize([width, height], state.geoData); 
            state.projection = projection;
            const path = d3.geoPath().projection(projection); 
            // Menos decimales por coordenada = cadenas 'd' mucho mas cortas (miles de poligonos)
            if (typeof path.digits === 'function') path.digits(2);
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
                .attr("stroke", "#fff").attr("stroke-width", "0.5")
                .style("fill", "#e6f0f8")
                .style("cursor", "pointer")
                .on("mouseover", (e, d) => {
                    if(app.tooltips) app.tooltips.showGeoTooltip(e, d, state, mapDiv);
                }) 
                .on("mousemove", (e) => {
                    const [mx, my] = d3.pointer(e, mapDiv);
                    if (state.tooltip) state.tooltip.style("left", (mx + 15) + "px").style("top", (my - 28) + "px");
                })
                .on("mouseout", () => { 
                    let k = 1; if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch(err) {} }
                    state.gPaths.selectAll("path.siarhe-feature").attr("stroke", "#fff").attr("stroke-width", 0.5 / k); 
                    if (state.tooltip) state.tooltip.style("opacity", 0).style("display", "none"); 
                })
                .on("click", (e, d) => {
                    const ahora = Date.now();
                    if (ahora - (state.lastClickTime || 0) < 400) { 
                        app.map.handleMapClick(d, state); 
                    } else { 
                        if(app.tooltips) app.tooltips.showGeoTooltip(e, d, state, mapDiv); 
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
                    
                    state.gPaths.selectAll("path.siarhe-feature").attr("stroke-width", 0.5 / k);
                    state.gLocPaths.selectAll("path.siarhe-loc-feature").attr("stroke-width", 0.5 / k);

                    let shrinkFactor = 1;
                    if (k > 8) shrinkFactor = 1 + ((k - 8) * 0.15); 
                    
                    // 🌟 MEJORA UX: Áreas táctiles más grandes para móvil/tablet
                    const isTouch = window.innerWidth < 1024;
                    let baseSize = isTouch ? 500 : 250; // Área base mayor en touch

                    let symbolSize = (baseSize / shrinkFactor) / (k * k);
                    
                    // Límite mínimo mucho más permisivo para no perder el área de TAP
                    let minSymbolSize = isTouch ? 0.25 : 0.05; 
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
                                    // 🚀 Reutilizamos la columna ya resuelta en updateMarkers en vez de
                                    // recalcularla con Object.keys().find() por marcador en cada tick de zoom.
                                    const realCol = (state._espectroColKeyCache && state._espectroColKeyCache[d.tipo] !== undefined)
                                        ? state._espectroColKeyCache[d.tipo]
                                        : Object.keys(d._raw).find(k => k.toLowerCase().trim() === config.espectro_col.toLowerCase().trim());
                                    val = realCol ? parseFloat(d._raw[realCol]) || 0 : 0;
                                }
                                const max = (state.espectroMaxVals && state.espectroMaxVals[d.tipo]) ? state.espectroMaxVals[d.tipo] : 1;
                                
                                if (val <= 0) return d3.symbol().type(app.utils.getD3Shape(app.utils.getMarkerStyle(d.tipo, state).shape)).size(0)();
                                const scaleArea = d3.scaleLinear().domain([1, max]).range([symbolSize * 2.5, symbolSize * 25]).clamp(true);
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

            // 🌟 Inicializamos el observador responsivo
            app.map.initResponsiveLegends(container, state);
        },

        /**
         * Registra una sola vez los eventos de la capa de localidades/municipios por delegacion
         * (antes: 3 closures por poligono, decenas de miles en entidades grandes).
         */
        _bindLocEvents: function(layer, state, mapDiv) {
            if (state._locEventsBound) return;
            state._locEventsBound = true;

            const isLoc = (t) => t && t.classList && t.classList.contains('siarhe-loc-feature');
            const zoomK = () => {
                let k = 1;
                if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch (err) {} }
                return k;
            };

            layer
                .on("mouseover", function(e) {
                    const t = e.target;
                    if (!isLoc(t)) return;
                    d3.select(t).attr("stroke", "#ffffff").attr("stroke-width", 1.5 / zoomK());
                    if (app.tooltips) app.tooltips.showGeoTooltip(e, t.__data__, state, mapDiv);
                })
                .on("mousemove", function(e) {
                    if (!isLoc(e.target)) return;
                    const [mx, my] = d3.pointer(e, mapDiv);
                    if (state.tooltip) state.tooltip.style("left", (mx + 15) + "px").style("top", (my - 28) + "px");
                })
                .on("mouseout", function(e) {
                    const t = e.target;
                    if (!isLoc(t)) return;
                    d3.select(t).attr("stroke", "rgba(255, 255, 255, 0.4)").attr("stroke-width", 0.5 / zoomK());
                    if (state.tooltip) state.tooltip.style("opacity", 0).style("display", "none");
                })
                .on("click", function(e) {
                    const t = e.target;
                    if (!isLoc(t)) return;
                    const ahora = Date.now();
                    if (ahora - (state.lastClickTime || 0) < 400) {
                        app.map.handleMapClick(t.__data__, state);
                    } else if (app.tooltips) {
                        app.tooltips.showGeoTooltip(e, t.__data__, state, mapDiv);
                    }
                    state.lastClickTime = ahora;
                });
        },

        /**
         * Muestra u oculta la segunda vista (municipios / localidades).
         * Los poligonos se construyen UNA sola vez, en lotes por cuadro (sin congelar la pagina),
         * y despues solo se muestran con un fundido. Devuelve una Promise que se resuelve al terminar.
         * onProgress(pct) permite informar el avance en el overlay de carga.
         */
        updateGeography: function(container, state, onProgress) {
            const mapDiv = container.querySelector('.siarhe-map-container');
            if (!mapDiv || !state.gLocPaths || !state.path) return Promise.resolve();

            const layer = state.gLocPaths;

            if (!(state.isGeoLocMode && state.locGeoData)) {
                state._locBuildGen = (state._locBuildGen || 0) + 1; // cancela una construccion en curso
                layer.style("display", "none");
                return Promise.resolve();
            }

            const features = Array.isArray(state.locGeoData.features) ? state.locGeoData.features : [];
            if (features.length === 0) {
                layer.style("display", "none");
                return Promise.resolve();
            }

            app.map._bindLocEvents(layer, state, mapDiv);

            const zoomK = () => {
                let k = 1;
                if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch (e) {} }
                return k;
            };

            const reveal = () => {
                layer.style("display", "block").style("opacity", 0)
                    .transition().duration(400).style("opacity", 1);
                layer.selectAll("path.siarhe-loc-feature").attr("stroke-width", 0.5 / zoomK());
            };

            // Ya construida: solo se vuelve a mostrar (antes se recalculaba el 'd' de todos los poligonos)
            if (state._locBuilt === state.locGeoData) {
                reveal();
                return Promise.resolve();
            }

            state._locBuildGen = (state._locBuildGen || 0) + 1;
            const gen = state._locBuildGen;
            layer.selectAll("*").remove();
            layer.interrupt().style("display", "block").style("opacity", 1);

            const ns = "http://www.w3.org/2000/svg";
            const layerNode = layer.node();
            const total = features.length;
            const strokeW = 0.5 / zoomK();
            let i = 0;

            return new Promise((resolve) => {
                const step = () => {
                    if (state._locBuildGen !== gen) { resolve(); return; }

                    const t0 = performance.now();
                    const frag = document.createDocumentFragment();
                    do {
                        const f = features[i++];
                        const dStr = f ? state.path(f) : null;
                        if (dStr) {
                            const el = document.createElementNS(ns, "path");
                            el.setAttribute("class", "siarhe-loc-feature");
                            el.setAttribute("d", dStr);
                            el.setAttribute("stroke", "rgba(255, 255, 255, 0.4)");
                            el.setAttribute("stroke-width", strokeW);
                            el.style.fill = "transparent";
                            el.style.cursor = "pointer";
                            el.__data__ = f;
                            frag.appendChild(el);
                        }
                    } while (i < total && performance.now() - t0 < 10);
                    layerNode.appendChild(frag);

                    if (typeof onProgress === 'function') onProgress(Math.min(100, Math.round((i / total) * 100)));

                    if (i < total) {
                        requestAnimationFrame(step);
                    } else {
                        state._locBuilt = state.locGeoData; // los poligonos ya fueron apareciendo por lotes: sin fundido extra
                        resolve();
                    }
                };
                requestAnimationFrame(step);
            });
        },

        updateVisuals: function(container, state) {
            const metric = state.currentMetric;
            
            const metricConfig = state.metricas[metric] || {};
            const mode = state.colorMode || metricConfig.scale_type || 'cuartiles'; 
            
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
                const emptyStats = {min: 0, q1: 0, q2: 0, q3: 0, max: 0, Vmin: 0, Q1: 0, Q2: 0, Q3: 0, Vmax: 0};
                app.map.renderLegend(state, emptyStats, mode);
            } else {
                let colorScale;
                let stats = {};

                const min = d3.min(values); 
                const max = d3.max(values);

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
                
                stats = { min, q1, q2, q3, max, Vmin: min, Q1: q1, Q2: q2, Q3: q3, Vmax: max };

                if (mode === 'rangos') {
                    const colorEvaluator = app.math.createRangeEvaluator(metricConfig, stats);
                    
                    state.gPaths.selectAll("path.siarhe-feature").transition().duration(500)
                        .style("fill", d => {
                            let cve = app.utils.getGeoKey(d.properties, state.isNacional);
                            const row = state.dataMap.get(cve);
                            if (!row || row[metric] === null || row[metric] === undefined) return app.colors.NULL; 
                            return colorEvaluator(row[metric]);
                        });
                } else {
                    if (mode === 'cuartiles' || mode === 'degradado' || mode === 'quartiles') {
                        colorScale = d3.scaleLinear().domain(domain).range(app.colors.RANGE).clamp(true);
                    } else {
                        let monoRange = app.colors.MONO;
                        if (monoRange.length === 2) {
                            const interpolator = d3.interpolate(monoRange[0], monoRange[1]);
                            monoRange = [ interpolator(0), interpolator(0.25), interpolator(0.50), interpolator(0.75), interpolator(1) ];
                        }
                        colorScale = d3.scaleLinear().domain(domain).range(monoRange).clamp(true);
                    }

                    state.gPaths.selectAll("path.siarhe-feature").transition().duration(500)
                        .style("fill", d => {
                            let cve = app.utils.getGeoKey(d.properties, state.isNacional);
                            const row = state.dataMap.get(cve);
                            
                            if (!row || row[metric] === null || row[metric] === undefined) return app.colors.NULL; 
                            if (row[metric] === 0) return app.colors.ZERO; 
                            
                            return colorScale(row[metric]);
                        });
                }

                app.map.renderLegend(state, stats, mode);
            }
            app.map.renderLabels(state);
        },

        renderLabels: function(state) {
            let currentK = 1;
            if (state.svg) { try { currentK = d3.zoomTransform(state.svg.node()).k; } catch(e) {} }

            const baseGeoData = state.baseGeoData || state.geoData;
            if (!baseGeoData || !Array.isArray(baseGeoData.features)) return;

            // Area y centroide se calculan UNA vez por geometria (antes se repetian en cada cambio de metrica)
            if (!state._labelFeatures || state._labelFeaturesSrc !== baseGeoData) {
                const maxAreaFeatures = new Map();
                baseGeoData.features.forEach(d => {
                    let cve = app.utils.getGeoKey(d.properties, state.isNacional);
                    let area = d3.geoArea(d);
                    if (!maxAreaFeatures.has(cve) || area > maxAreaFeatures.get(cve).area) {
                        maxAreaFeatures.set(cve, { feature: d, area: area });
                    }
                });
                state._labelFeatures = Array.from(maxAreaFeatures.values()).map(item => {
                    const c = state.path.centroid(item.feature);
                    return { feature: item.feature, x: isNaN(c[0]) ? 0 : c[0], y: isNaN(c[1]) ? 0 : c[1] };
                });
                state._labelFeaturesSrc = baseGeoData;
            }

            const labels = state.gLabels.selectAll("text.siarhe-label").data(state._labelFeatures);
            labels.exit().remove();

            labels.enter().append("text")
                .attr("class", "siarhe-label")
                .merge(labels)
                .attr("x", d => d.x)
                .attr("y", d => d.y)
                .attr("text-anchor", "middle")
                .attr("fill", "#0F172A")
                .attr("stroke", "#ffffff")
                .attr("stroke-width", 2.5 / currentK)
                .attr("paint-order", "stroke fill")
                .attr("stroke-linejoin", "round")
                .style("font-size", `${14 / currentK}px`)
                .style("font-weight", "bold")
                .text(d => {
                    let cve = app.utils.getGeoKey(d.feature.properties, state.isNacional);
                    const row = state.dataMap.get(cve);
                    return row ? row.estado : (d.feature.properties.NOMGEO || d.feature.properties.NOM_ENT || "");
                });
        },

        renderLegend: function(state, stats, mode) {
            const g = state.gLegend; 
            g.html("");
            
            const metricInfo = state.metricas[state.currentMetric] || {};
            const label = metricInfo.abrev || metricInfo.label || 'Indicador';
            const currentMode = mode || state.colorMode || 'cuartiles';
            const isRangos = (currentMode === 'rangos');

            const boxWidth = isRangos ? 195 : 140;

            g.append("rect").attr("x", -10).attr("y", -25).attr("width", boxWidth).attr("height", isRangos ? 220 : 270)
             .attr("fill", "rgba(255,255,255,0.9)").attr("rx", 5).style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.1))");
            
            g.append("text").attr("x", 0).attr("y", -10).text(label)
             .style("font-size", "12px").style("font-weight", "bold").style("fill", "#0F172A");

            if (isRangos) {
                const useColors = metricInfo.custom_colors === true;
                const c1 = (useColors && metricInfo.r1_color) ? metricInfo.r1_color : app.colors.CUSTOM_RANGE[0];
                const c2 = (useColors && metricInfo.r2_color) ? metricInfo.r2_color : app.colors.CUSTOM_RANGE[1];
                const c3 = (useColors && metricInfo.r3_color) ? metricInfo.r3_color : app.colors.CUSTOM_RANGE[2];
                const c4 = (useColors && metricInfo.r4_color) ? metricInfo.r4_color : app.colors.CUSTOM_RANGE[3];

                const parseLabel = (raw) => {
                    if (raw === undefined || raw === null || raw === '') return '';
                    let v = raw.toString().trim();
                    const key = Object.keys(stats).find(k => k.toLowerCase() === v.toLowerCase());
                    
                    let numToFormat = key ? stats[key] : parseFloat(v);
                    
                    if (!isNaN(numToFormat)) {
                        if (metricInfo.tipo === 'tasa') {
                            return numToFormat.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        } else {
                            return numToFormat.toLocaleString('es-MX', {maximumFractionDigits: 2});
                        }
                    }
                    return v;
                };

                const formatOp = (op) => op === '<=' ? '≤' : op;
                const useRanges = metricInfo.custom_ranges === true;

                const r4min = (useRanges && metricInfo.r4_min) ? metricInfo.r4_min : 'Q3'; 
                const r4max = (useRanges && metricInfo.r4_max) ? metricInfo.r4_max : 'Vmax'; 
                const r4op  = formatOp((useRanges && metricInfo.r4_op) ? metricInfo.r4_op : '<=');
                
                const r3min = (useRanges && metricInfo.r3_min) ? metricInfo.r3_min : 'Q2'; 
                const r3max = (useRanges && metricInfo.r3_max) ? metricInfo.r3_max : 'Q3';   
                const r3op  = formatOp((useRanges && metricInfo.r3_op) ? metricInfo.r3_op : '<');
                
                const r2min = (useRanges && metricInfo.r2_min) ? metricInfo.r2_min : 'Q1'; 
                const r2max = (useRanges && metricInfo.r2_max) ? metricInfo.r2_max : 'Q2';   
                const r2op  = formatOp((useRanges && metricInfo.r2_op) ? metricInfo.r2_op : '<');
                
                const r1min = (useRanges && metricInfo.r1_min) ? metricInfo.r1_min : 'Vmin'; 
                const r1max = (useRanges && metricInfo.r1_max) ? metricInfo.r1_max : 'Q1'; 
                const r1op  = formatOp((useRanges && metricInfo.r1_op) ? metricInfo.r1_op : '<');

                const blocks = [
                    { color: c4, text: `${parseLabel(r4min)} a ${r4op} ${parseLabel(r4max)}` },
                    { color: c3, text: `${parseLabel(r3min)} a ${r3op} ${parseLabel(r3max)}` },
                    { color: c2, text: `${parseLabel(r2min)} a ${r2op} ${parseLabel(r2max)}` },
                    { color: c1, text: `${parseLabel(r1min)} a ${r1op} ${parseLabel(r1max)}` }
                ];

                blocks.forEach((b, i) => {
                    const y = 15 + (i * 30);
                    g.append("rect").attr("x", 0).attr("y", y).attr("width", 20).attr("height", 20)
                     .style("fill", b.color).style("stroke", "#cbd5e1");
                    g.append("text").attr("x", 28).attr("y", y + 14)
                     .text(b.text).style("font-size", "11px").style("fill", "#475569");
                });

                const g0 = g.append("g").attr("transform", `translate(0, ${15 + (4*30) + 15})`);
                g0.append("rect").attr("width", 12).attr("height", 12).style("fill", app.colors.ZERO);
                g0.append("text").attr("x", 18).attr("y", 10).text("0.00").style("font-size", "11px").style("fill", "#475569");

                const gN = g.append("g").attr("transform", `translate(0, ${15 + (4*30) + 35})`);
                gN.append("rect").attr("width", 12).attr("height", 12).style("fill", app.colors.NULL);
                gN.append("text").attr("x", 18).attr("y", 10).text("S/D").style("font-size", "11px").style("fill", "#475569");

            } else {
                const gradient = state.svg.select(`#${state.gradientId}`);
                gradient.selectAll("stop").remove();
                
                let colorArr = [];
                let domainVals = [stats.min, stats.q1, stats.q2, stats.q3, stats.max];

                if (currentMode === 'cuartiles' || currentMode === 'degradado' || currentMode === 'quartiles') {
                    colorArr = app.colors.RANGE;
                } else {
                    colorArr = app.colors.MONO;
                    if (colorArr.length === 2) {
                        const interpolator = d3.interpolate(colorArr[0], colorArr[1]);
                        colorArr = [ interpolator(0), interpolator(0.25), interpolator(0.50), interpolator(0.75), interpolator(1) ];
                    }
                }

                gradient.selectAll("stop")
                    .data(colorArr)
                    .enter().append("stop")
                    .attr("offset", (d, i) => `${(i / (colorArr.length - 1)) * 100}%`)
                    .attr("stop-color", d => d);
                
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
            }
        },

        handleMapClick: function(d, state) {
            let cve = app.utils.getGeoKey(d.properties, state.isNacional);
            if (state.entityUrls && state.entityUrls[cve]) {
                window.location.href = state.entityUrls[cve];
            }
        },

        // Tamano de simbolo segun el zoom actual (compartido por updateMarkers y el pintado por cuadros)
        _markerMetrics: function(state) {
            let k = 1;
            if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch(e) {} }

            let shrinkFactor = 1;
            if (k > 8) shrinkFactor = 1 + ((k - 8) * 0.15);

            // Areas tactiles mas grandes para movil/tablet
            const isTouch = window.innerWidth < 1024;
            const baseSize = isTouch ? 500 : 250;
            const minSymbolSize = isTouch ? 0.25 : 0.05;

            return { k: k, symbolSize: Math.max((baseSize / shrinkFactor) / (k * k), minSymbolSize) };
        },

        updateMarkers: function(state) {
            if (!state.gMarkers) return Promise.resolve();

            let allPoints = [];
            state.activeMarkers.forEach(type => {
                const data = state.markersData[type];
                if (!Array.isArray(data)) return;
                // Id estable por punto: permite a d3 reutilizar los nodos ya pintados al activar/desactivar otro tipo
                if (data.length > 0 && data[0]._uid === undefined) {
                    for (let i = 0; i < data.length; i++) data[i]._uid = type + ':' + i;
                }
                allPoints = allPoints.concat(data);
            });

            let hasEspectro = false;
            state.activeMarkers.forEach(type => { if ((state.markerLabels[type] || {}).tipo === 'espectro') hasEspectro = true; });

            // Los de espectro (burbujas) van debajo, los grandes antes que los chicos
            allPoints.sort((a, b) => {
                const iA = (state.markerLabels[a.tipo] || {}).tipo === 'espectro', iB = (state.markerLabels[b.tipo] || {}).tipo === 'espectro'; return iA && !iB ? -1 : (!iA && iB ? 1 : (iA ? (b._agrupados_total || 1) - (a._agrupados_total || 1) : 0));
            });

            // Resolvemos la columna real y el maximo de "espectro" UNA vez por tipo (no por punto)
            state.espectroMaxVals = {};
            state._espectroColKeyCache = {};
            state.activeMarkers.forEach(type => {
                const config = state.markerLabels[type] || {};
                if (config.tipo === 'espectro') {
                    const data = state.markersData[type] || [];
                    let max = 0;
                    if (config.espectro_col && config.espectro_col.trim() !== '') {
                        const sample = data.find(d => d._raw);
                        const realCol = sample ? Object.keys(sample._raw).find(k => k.toLowerCase().trim() === config.espectro_col.toLowerCase().trim()) : null;
                        state._espectroColKeyCache[type] = realCol || null;
                        max = d3.max(data, d => realCol ? (parseFloat(d._raw[realCol]) || 0) : 0);
                    } else {
                        max = d3.max(data, d => d._agrupados_total || 1);
                    }
                    state.espectroMaxVals[type] = max || 1;
                }
            });

            const markers = state.gMarkers.selectAll("path.siarhe-marker").data(allPoints, d => d._uid);
            markers.exit().remove();

            // Solo los nodos NUEVOS se crean, se enlazan a eventos y se pintan; los existentes no se tocan
            const entered = markers.enter().append("path")
                .attr("class", "siarhe-marker")
                .style("cursor", "pointer")
                .style("opacity", 0)
                .style("transition", "opacity 0.5s ease-out")
                .on("mouseover", function(e, d) {
                    let k = 1; if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch(err) {} }
                    const config = state.markerLabels[d.tipo] || {};

                    d3.select(this).attr("stroke-width", 3 / k);
                    if (config.tipo !== 'espectro') { d3.select(this).raise(); }

                    const mapDiv = this.closest('.siarhe-map-container');
                    if(app.tooltips && mapDiv) app.tooltips.showMarkerTooltip(e, d, state, mapDiv);
                })
                .on("mouseout", function(e, d) {
                    let k = 1; if (state.svg) { try { k = d3.zoomTransform(state.svg.node()).k; } catch(err) {} }
                    d3.select(this).attr("stroke-width", 1.5 / k);
                    if(state.tooltip) state.tooltip.style("display", "none");
                });

            // El orden en el DOM define el apilado: solo hace falta reordenar si hay burbujas de espectro
            if (hasEspectro) entered.merge(markers).order();

            app.map.renderMarkerLegend(state);

            return app.map._enqueueMarkers(state, entered.nodes());
        },

        /**
         * Encola los marcadores nuevos agrupados por unidad geografica (entidad en el mapa nacional,
         * municipio en el estatal) para que aparezcan como una oleada region por region.
         * Devuelve una Promise que se resuelve cuando la cola queda vacia.
         */
        _enqueueMarkers: function(state, nodes) {
            state._markerQueue = state._markerQueue || [];
            state._markerWaiters = state._markerWaiters || [];

            if (nodes.length > 0) {
                const groups = new Map();
                nodes.forEach(n => {
                    const d = n.__data__ || {};
                    const key = state.isNacional ? (d.cve_ent || '') : (d.municipio || '');
                    if (!groups.has(key)) groups.set(key, []);
                    groups.get(key).push(n);
                });
                Array.from(groups.keys()).sort().forEach(key => {
                    state._markerQueue.push({ nodes: groups.get(key), i: 0 });
                });
            }

            if (!state._markerPainting && state._markerQueue.length === 0) return Promise.resolve();

            // Ritmo constante: la cola completa se reparte en ~70 cuadros (~1.2 s), sin importar cuantos
            // marcadores tenga cada region. Asi el flujo es parejo y no en rafagas por entidad/municipio.
            let pending = 0;
            state._markerQueue.forEach(g => { pending += g.nodes.length - g.i; });
            state._markerRate = Math.max(20, Math.ceil(pending / 70));

            return new Promise(resolve => {
                state._markerWaiters.push(resolve);
                if (!state._markerPainting) {
                    state._markerPainting = true;
                    // Doble rAF: garantiza que los nodos nuevos (opacity 0) ya se estilizaron y el fundido se anime
                    requestAnimationFrame(() => requestAnimationFrame(() => app.map._paintMarkerFrame(state)));
                }
            });
        },

        // Cada cuadro pinta ~_markerRate marcadores (siguiendo el orden por region) con tope de ~12 ms
        _paintMarkerFrame: function(state) {
            const queue = state._markerQueue;
            const metrics = app.map._markerMetrics(state);
            const cache = {};
            const t0 = performance.now();
            const rate = state._markerRate || 50;
            let painted = 0;

            while (queue.length > 0 && painted < rate) {
                const g = queue[0];
                while (g.i < g.nodes.length && painted < rate) {
                    app.map._paintMarkerNode(g.nodes[g.i++], state, metrics, cache);
                    painted++;
                    if ((painted & 31) === 0 && performance.now() - t0 > 12) { painted = rate; break; }
                }
                if (g.i >= g.nodes.length) queue.shift();
            }

            if (queue.length > 0) {
                requestAnimationFrame(() => app.map._paintMarkerFrame(state));
            } else {
                state._markerPainting = false;
                const waiters = state._markerWaiters || [];
                state._markerWaiters = [];
                waiters.forEach(resolve => resolve());
            }
        },

        _paintMarkerNode: function(node, state, metrics, cache) {
            if (!node.isConnected) return; // se desactivo el tipo mientras esperaba en la cola
            const d = node.__data__;
            if (!d) return;

            const coords = state.projection([d.lon, d.lat]);
            if (!coords || isNaN(coords[0]) || isNaN(coords[1])) {
                node.style.display = 'none'; // coordenada fuera de proyeccion: no se dibuja en (0,0)
                return;
            }

            const type = d.tipo;
            const config = state.markerLabels[type] || {};
            const styleCfg = cache['style:' + type] || (cache['style:' + type] = app.utils.getMarkerStyle(type, state));
            const symbol = cache['sym:' + type] || (cache['sym:' + type] = d3.symbol().type(app.utils.getD3Shape(styleCfg.shape)));

            let finalSize = metrics.symbolSize;
            let fillColor = styleCfg.fill;

            if (config.tipo === 'espectro') {
                let val = d._agrupados_total || 1;
                if (config.espectro_col && config.espectro_col.trim() !== '') {
                    const realCol = state._espectroColKeyCache ? state._espectroColKeyCache[type] : null;
                    val = (realCol && d._raw) ? (parseFloat(d._raw[realCol]) || 0) : 0;
                }
                if (val <= 0) {
                    finalSize = 0;
                } else {
                    const scale = cache['scale:' + type] || (cache['scale:' + type] = d3.scaleLinear()
                        .domain([1, (state.espectroMaxVals && state.espectroMaxVals[type]) || 1])
                        .range([metrics.symbolSize * 2.5, metrics.symbolSize * 25]).clamp(true));
                    finalSize = scale(val);
                }
                fillColor = styleCfg.fill.startsWith('rgba') ? styleCfg.fill : app.map.hexToRgba(styleCfg.fill, 0.75);
            }

            node.setAttribute("d", symbol.size(finalSize)());
            node.setAttribute("transform", `translate(${coords[0]}, ${coords[1]})`);
            node.setAttribute("fill", fillColor);
            node.setAttribute("stroke", styleCfg.stroke);
            node.setAttribute("stroke-width", 1.5 / metrics.k);
            node.style.opacity = 1; // dispara el fundido de entrada
        },

        renderMarkerLegend: function(state) {
            const g = state.gMarkerLegend;
            g.html(""); 

            const actives = Array.from(state.activeMarkers);
            if (actives.length === 0) return; 

            const boxHeight = (actives.length * 20) + 30;
            const startY = -boxHeight; 

            g.append("rect")
                .attr("x", -10).attr("y", startY)
                .attr("width", 230) 
                .attr("height", boxHeight)
                .attr("fill", "rgba(255,255,255,0.9)")
                .attr("rx", 5)
                .style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.1))");
            
            g.append("text").attr("x", 0).attr("y", startY + 15).text("Marcadores Activos").style("font-size", "12px").style("font-weight", "bold").style("fill", "#0F172A");

            actives.forEach((tipo, i) => {
                const yPos = startY + 32 + (i * 20); 
                const styleCfg = app.utils.getMarkerStyle(tipo, state);
                const markerLabel = state.markerLabels[tipo] ? state.markerLabels[tipo].label : tipo;

                g.append("path")
                    .attr("d", d3.symbol().type(app.utils.getD3Shape(styleCfg.shape)).size(50)())
                    .attr("transform", `translate(5, ${yPos - 4})`)
                    .attr("fill", styleCfg.fill)
                    .attr("stroke", styleCfg.stroke)
                    .attr("stroke-width", 1);
                
                g.append("text").attr("x", 15).attr("y", yPos).text(markerLabel).style("font-size", "11px").style("fill", "#475569");
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
                return b;
            };
            
            createBtn('+', 'Acercar', (e) => { e.preventDefault(); svg.transition().call(zoom.scaleBy, 1.5); });
            createBtn('–', 'Alejar', (e) => { e.preventDefault(); svg.transition().call(zoom.scaleBy, 0.6); });
            createBtn('⟳', 'Reset', (e) => { e.preventDefault(); svg.transition().duration(750).call(zoom.transform, state.initialTransform); });
            
            const btnFullscreen = document.createElement('button');
            btnFullscreen.className = 'boton'; 
            btnFullscreen.innerHTML = '⤢'; 
            btnFullscreen.title = 'Pantalla Completa';
            const mapContainer = mapDiv; 
            
            const btnShowControls = document.createElement('button');
            btnShowControls.className = 'boton btn-show-controls'; 
            btnShowControls.innerHTML = '🎛️'; 
            btnShowControls.title = 'Mostrar Controles';

            btnShowControls.onclick = (e) => {
                e.preventDefault();
                const controlsEl = container.querySelector('.siarhe-controls-layout');
                if (controlsEl) controlsEl.classList.remove('is-hidden');
                mapContainer.classList.remove('controls-are-hidden');
            };

            btnFullscreen.onclick = (e) => {
                e.preventDefault();
                
                const controlsEl = container.querySelector('.siarhe-controls-layout');

                if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                    if (mapContainer.requestFullscreen) { mapContainer.requestFullscreen(); } 
                    else if (mapContainer.webkitRequestFullscreen) { mapContainer.webkitRequestFullscreen(); } 
                    else if (mapContainer.msRequestFullscreen) { mapContainer.msRequestFullscreen(); }
                    
                    btnFullscreen.innerHTML = '⤡'; 
                    mapContainer.classList.add('is-fullscreen');
                    
                    mapContainer.classList.remove('controls-are-hidden'); 
                    if (controlsEl) {
                        mapContainer.appendChild(controlsEl);
                        controlsEl.classList.add('is-fullscreen-mode');
                        controlsEl.classList.remove('is-hidden');
                    }
                } else {
                    if (document.exitFullscreen) { document.exitFullscreen(); } 
                    else if (document.webkitExitFullscreen) { document.webkitExitFullscreen(); }
                    else if (document.msExitFullscreen) { document.msExitFullscreen(); }
                    
                    btnFullscreen.innerHTML = '⤢'; 
                    mapContainer.classList.remove('is-fullscreen');
                    mapContainer.classList.remove('controls-are-hidden');
                    
                    if (controlsEl) {
                        const controlsPlaceholder = container.querySelector('.siarhe-controls-placeholder');
                        if(controlsPlaceholder) controlsPlaceholder.appendChild(controlsEl);
                        controlsEl.classList.remove('is-fullscreen-mode');
                        controlsEl.classList.remove('is-hidden');
                    }
                }
            };

            document.addEventListener('fullscreenchange', () => {
                if (!document.fullscreenElement) {
                    btnFullscreen.innerHTML = '⤢'; 
                    mapContainer.classList.remove('is-fullscreen');
                    mapContainer.classList.remove('controls-are-hidden');
                    
                    const controlsEl = mapContainer.querySelector('.siarhe-controls-layout');
                    if (controlsEl) {
                        const controlsPlaceholder = container.querySelector('.siarhe-controls-placeholder');
                        if(controlsPlaceholder) controlsPlaceholder.appendChild(controlsEl);
                        controlsEl.classList.remove('is-fullscreen-mode');
                        controlsEl.classList.remove('is-hidden');
                    }
                }
            });
            
            ctrlDiv.appendChild(btnFullscreen);

            if (!state.isNacional && state.homeUrl) {
                createBtn('🏠', 'Ir a Nacional', (e) => { e.preventDefault(); window.location.href = state.homeUrl; });
            }

            ctrlDiv.appendChild(btnShowControls);
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
                    const refNodes = container.querySelectorAll('.siarhe-ref-col');
                    refNodes.forEach(node => {
                        if (node.innerHTML.includes('dashicons-groups') || node.innerHTML.includes('Datos de Enfermería')) {
                            const pTag = node.querySelector('p');
                            if (pTag) refText = pTag.innerText.replace(/\n/g, ' | ');
                        }
                    });

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