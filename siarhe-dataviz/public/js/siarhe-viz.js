// 📢 LOG INICIAL
console.log("%c 🚀 SIARHE JS V20: Zoom Exportable, Etiquetas Auto-Escalables y Sin Disclaimer", "background: #222; color: #bada55");

document.addEventListener('DOMContentLoaded', function() {

    if (typeof d3 === 'undefined') { console.error("❌ ERROR: D3.js no cargó."); return; }

    // ==========================================
    // 1. CONFIGURACIÓN
    // ==========================================
    const METRICAS = {
        'tasa_total':             { label: 'Tasa Total', fullLabel: 'Tasa de enfermeras por cada mil habitantes', tipo: 'tasa', pair: 'enfermeras_total' },
        'enfermeras_total':       { label: 'Total Enf.', fullLabel: 'Total de profesionales de enfermería',      tipo: 'absoluto', pair: 'enfermeras_total' },
        'poblacion':              { label: 'Población',  fullLabel: 'Población Total',          tipo: 'absoluto', pair: 'poblacion' },
        
        'tasa_primer':            { label: 'Tasa 1er Nivel', fullLabel: 'Tasa de enfermeras (1er Nivel) por mil hab.',       tipo: 'tasa', pair: 'enfermeras_primer' },
        'enfermeras_primer':      { label: 'Enf. 1er Nivel', fullLabel: 'Enfermeras en 1er Nivel de Atención', tipo: 'absoluto', pair: 'enfermeras_primer' },
        
        'tasa_segundo':           { label: 'Tasa 2do Nivel', fullLabel: 'Tasa de enfermeras (2do Nivel) por mil hab.',       tipo: 'tasa', pair: 'enfermeras_segundo' },
        'enfermeras_segundo':     { label: 'Enf. 2do Nivel', fullLabel: 'Enfermeras en 2do Nivel de Atención', tipo: 'absoluto', pair: 'enfermeras_segundo' },
        
        'tasa_tercer':            { label: 'Tasa 3er Nivel', fullLabel: 'Tasa de enfermeras (3er Nivel) por mil hab.',       tipo: 'tasa', pair: 'enfermeras_tercer' },
        'enfermeras_tercer':      { label: 'Enf. 3er Nivel', fullLabel: 'Enfermeras en 3er Nivel de Atención', tipo: 'absoluto', pair: 'enfermeras_tercer' },
        
        'tasa_administrativas':   { label: 'Tasa Admin.', fullLabel: 'Tasa de enfermeras administrativas',     tipo: 'tasa', pair: 'enfermeras_administrativas' },
        'enfermeras_administrativas': { label: 'Enf. Admin.', fullLabel: 'Enfermeras con funciones Administrativas', tipo: 'absoluto', pair: 'enfermeras_administrativas' }
    };

    const MARCADOR_ESTILOS = { 
        'CATETER': { fill: "#1E5B4F", stroke: "#ffffff" }, 
        'HERIDAS': { fill: "#9B2247", stroke: "#ffffff" } 
    };
    const MARCADOR_NOMBRES = { 'CATETER': "Clínicas de catéteres", 'HERIDAS': "Clínicas de heridas" };

    const styles = getComputedStyle(document.documentElement);
    const getVar = (n, f) => styles.getPropertyValue(n).trim() || f;
    const COLOR_RANGE = [ getVar('--s-map-c1', '#eff3ff'), getVar('--s-map-c2', '#bdd7e7'), getVar('--s-map-c3', '#9ecae1'), getVar('--s-map-c4', '#6baed6'), getVar('--s-map-c5', '#08519c') ];
    const COLOR_ZERO = getVar('--s-map-zero', '#d9d9d9'); 
    const COLOR_NULL = getVar('--s-map-null', '#000000'); 

    let sortConfig = { key: 'tasa', direction: 'desc' };

    // ==========================================
    // 2. HELPERS
    // ==========================================
    function cleanKey(val) { return (val === undefined || val === null) ? "" : val.toString().trim(); }
    function getGeoKey(props) { return cleanKey(props.ID || props.CVE_ENT || props.cve_ent || props.id); }
    function parseNum(val) {
        if (!val) return 0;
        const n = parseFloat(val.toString().replace(/,/g, '').replace(/\s/g, ''));
        return isNaN(n) ? 0 : n;
    }

    // ==========================================
    // 3. INICIALIZACIÓN
    // ==========================================
    const wrappers = document.querySelectorAll('.siarhe-viz-wrapper');
    wrappers.forEach(initVisualization);

    function initVisualization(container) {
        const cveEnt = container.dataset.cveEnt;
        const geojsonUrl = container.dataset.geojson;
        const csvUrl = container.dataset.csv;
        const mode = container.dataset.mode;
        const loading = container.querySelector('.siarhe-loading-overlay');

        let state = {
            geoData: null, csvData: [], dataMap: new Map(),
            currentMetric: 'tasa_total',
            zoom: null, gLegend: null, gradientId: null,
            svg: null, gMain: null, gPaths: null, gLabels: null, gMarkers: null, 
            activeMarkers: new Set(),
            markersData: {},
            tooltip: null,
            markerTrigger: null
        };

        if (!geojsonUrl) return;

        Promise.all([d3.json(geojsonUrl), csvUrl ? d3.csv(csvUrl) : Promise.resolve(null)])
            .then(([geo, csv]) => {
                if(loading) loading.style.display = 'none';
                state.geoData = geo;
                
                if (csv) {
                    state.csvData = processCSV(csv);
                    state.csvData.forEach(row => { if (row.CVE_ENT) state.dataMap.set(row.CVE_ENT, row); });
                    
                    calcularSumaExactaEnfermeras(container, state);
                    calcularTotalHeader(container, state);
                }

                if (mode.includes('M')) {
                    renderMap(container, state, cveEnt);
                    
                    if (csv) {
                        renderMainControls(container, state, () => {
                            updateMapVisuals(container, state);
                            calcularTotalHeader(container, state);
                            if (mode.includes('T')) updateTable(container, state);
                        });
                    }
                    
                    setupActionButtons(container, state);
                }
                
                if (mode.includes('T') && csv) {
                    initTableStructure(container, state);
                }
            })
            .catch(err => { console.error(err); if(loading) loading.innerHTML = "❌ Error: " + err.message; });
    }

    function processCSV(data) {
        return data.map(d => {
            const row = {};
            row.CVE_ENT = cleanKey(d.CVE_ENT || d.cve_ent || d.mapa || d.id);
            row.id_legacy = (d.id || "").toString().trim();
            row.estado = (d.estado || d.Entidad || d.municipio || "").trim();
            const keyPob = Object.keys(d).find(k => k.toLowerCase().startsWith('pob'));
            row.poblacion = parseNum(d[keyPob]);
            Object.keys(METRICAS).forEach(k => { if (k !== 'poblacion') row[k] = parseNum(d[k]); });
            row.isTotal = (row.id_legacy === '9999' || row.CVE_ENT === '33' || row.estado.toLowerCase().includes('total'));
            row.isSpecial = (row.id_legacy === '8888' || row.CVE_ENT === '34' || row.CVE_ENT === '88');
            return row;
        });
    }

    function calcularSumaExactaEnfermeras(container, state) {
        const sumNode = container.querySelector('.siarhe-dynamic-nurses-sum');
        if (!sumNode) return;
        let sumaTotal = 0;
        state.csvData.forEach(row => {
            const cve = parseInt(row.CVE_ENT, 10);
            const isLegacy8888 = (row.id_legacy === '8888' || row.CVE_ENT === '8888' || row.CVE_ENT === '88');
            const isExtranjero = (cve === 34);
            if ((cve >= 1 && cve <= 32) || isExtranjero || isLegacy8888) {
                sumaTotal += (row.enfermeras_total || 0);
            }
        });
        sumNode.textContent = sumaTotal.toLocaleString('es-MX');
    }

    function calcularTotalHeader(container, state) {
        const headerDiv = container.querySelector('.siarhe-dynamic-total');
        if (!headerDiv) return;
        const info = METRICAS[state.currentMetric];
        const pairKey = info.pair || state.currentMetric;
        let totalAbs = 0, valorMuestra = 0;
        
        const rowTotal = state.csvData.find(r => r.isTotal);
        if (rowTotal) {
            totalAbs = rowTotal[pairKey]; valorMuestra = rowTotal[state.currentMetric];
        } else {
            state.csvData.forEach(row => {
                if (!row.isTotal && !row.isSpecial && METRICAS[pairKey].tipo === 'absoluto') totalAbs += row[pairKey];
            });
            valorMuestra = (info.tipo === 'tasa') ? 0 : totalAbs;
        }
        const absFmt = totalAbs.toLocaleString('es-MX');
        const valFmt = valorMuestra.toLocaleString('es-MX', { maximumFractionDigits: 2 });
        headerDiv.innerHTML = info.tipo === 'tasa' 
            ? `<span style="margin-right:15px;">${info.fullLabel}: <strong style="color:#2271b1; font-size:1.2em;">${valFmt}</strong></span><span>Enfermeras: <strong style="color:#2271b1; font-size:1.2em;">${absFmt}</strong></span>`
            : `<span>${info.fullLabel}: <strong style="color:#2271b1; font-size:1.2em;">${valFmt}</strong></span>`;
    }

    // ==========================================
    // 4. MAPA Y ZOOM ESCALABLE
    // ==========================================
    function renderMap(container, state, cveEnt) {
        const mapDiv = container.querySelector('.siarhe-map-container');
        const width = mapDiv.clientWidth || 800;
        const height = 600; 
        mapDiv.innerHTML = '';

        const svg = d3.select(mapDiv).append("svg")
            .attr("width", "100%").attr("height", height)
            .attr("viewBox", `0 0 ${width} ${height}`)
            .style("background-color", "#e6f0f8")
            .style("font-family", "Arial, sans-serif"); 
        
        state.svg = svg;

        const defs = svg.append("defs");
        state.gradientId = `grad-${Math.random().toString(36).substr(2, 5)}`;
        const linearGradient = defs.append("linearGradient")
            .attr("id", state.gradientId).attr("x1", "0%").attr("y1", "100%").attr("x2", "0%").attr("y2", "0%");
        linearGradient.selectAll("stop").data(COLOR_RANGE).enter().append("stop")
            .attr("offset", (d, i) => `${(i / (COLOR_RANGE.length - 1)) * 100}%`).attr("stop-color", d => d);

        const gMain = svg.append("g").attr("class", "map-layer");
        state.gMain = gMain;
        
        state.gPaths = gMain.append("g").attr("class", "paths-layer");
        state.gLabels = gMain.append("g").attr("class", "labels-layer").style("display", "none").style("pointer-events", "none");
        state.gMarkers = gMain.append("g").attr("class", "markers-layer");
        
        state.gLegend = svg.append("g").attr("class", "siarhe-legend-group").attr("transform", `translate(30, 40)`); 

        const projection = d3.geoMercator().fitSize([width, height], state.geoData);
        state.projection = projection;
        const path = d3.geoPath().projection(projection);
        state.path = path; 

        d3.selectAll("#siarhe-global-tooltip").remove();
        state.tooltip = d3.select("body").append("div")
            .attr("id", "siarhe-global-tooltip")
            .attr("class", "siarhe-tooltip")
            .style("opacity", 0) 
            .style("display", "none");

        state.gPaths.selectAll("path.siarhe-feature")
            .data(state.geoData.features).enter().append("path")
            .attr("d", path).attr("class", "siarhe-feature")
            .attr("stroke", "#fff").attr("stroke-width", "0.5").style("fill", COLOR_NULL)
            .on("mouseover", (e, d) => showTooltip(e, d, state)) 
            .on("mousemove", (e) => {
                state.tooltip.style("left", (e.pageX+15)+"px").style("top", (e.pageY-28)+"px");
            })
            .on("mouseout", () => { 
                state.gPaths.selectAll("path.siarhe-feature").style("stroke", "#fff").style("stroke-width", "0.5"); 
                state.tooltip.style("opacity", 0).style("display", "none"); 
            })
            .on("click", (e, d) => handleMapClick(d, state));

        // 🌟 MAGIA DEL ZOOM: Achicamos el tamaño de la letra y el contorno conforme nos acercamos
        const zoom = d3.zoom()
            .scaleExtent([1, 8])
            .on("zoom", (e) => {
                gMain.attr("transform", e.transform);
                state.gMarkers.selectAll("circle")
                    .attr("r", 5 / e.transform.k)
                    .attr("stroke-width", 1 / e.transform.k);
                
                // Reducir la letra y el halo de las etiquetas para que no estorben
                state.gLabels.selectAll("text.siarhe-label")
                    .style("font-size", `${10 / e.transform.k}px`)
                    .attr("stroke-width", 2.5 / e.transform.k);
            });
        
        svg.call(zoom).on("dblclick.zoom", null);
        state.zoom = zoom;
        renderZoomButtons(mapDiv, svg, zoom, cveEnt);
        updateMapVisuals(container, state);
    }

    // ==========================================
    // 5. EXPORTACIÓN PNG
    // ==========================================
    function setupActionButtons(container, state) {
        const btnLabels = container.querySelector('.siarhe-btn-toggle-labels');
        const btnPng = container.querySelector('.siarhe-btn-download-png');

        if (btnPng) {
            btnPng.addEventListener('click', (e) => {
                e.preventDefault();
                downloadMapAsPNG(container, state, false); 
            });
        }
        if (btnLabels) {
            btnLabels.addEventListener('click', (e) => {
                e.preventDefault();
                downloadMapAsPNG(container, state, true); 
            });
        }
    }

    function wrapText(context, text, x, y, maxWidth, lineHeight) {
        let words = text.split(' '), line = '';
        for(let n = 0; n < words.length; n++) {
          let testLine = line + words[n] + ' ';
          let metrics = context.measureText(testLine);
          let testWidth = metrics.width;
          if (testWidth > maxWidth && n > 0) {
            context.fillText(line, x, y);
            line = words[n] + ' ';
            y += lineHeight;
          } else {
            line = testLine;
          }
        }
        context.fillText(line, x, y);
    }

    function downloadMapAsPNG(container, state, withLabels) {
        const mapDiv = container.querySelector('.siarhe-map-container');
        const svgNode = mapDiv.querySelector('svg');
        if (!svgNode) return;

        // 🌟 SE ELIMINÓ EL RESETEO DEL ZOOM (state.svg.call...)
        // Ahora tomará captura exactamente de lo que el usuario esté viendo

        if (withLabels) state.gLabels.style("display", "block");

        setTimeout(() => {
            const clone = svgNode.cloneNode(true);
            const { width, height } = svgNode.getBoundingClientRect();
            clone.setAttribute('width', width);
            clone.setAttribute('height', height);

            const svgData = new XMLSerializer().serializeToString(clone);
            const canvas = document.createElement("canvas");
            
            const scale = 2; 
            const headerHeight = 60; 
            const footerHeight = 80; 
            
            canvas.width = width * scale;
            canvas.height = (height + headerHeight + footerHeight) * scale;
            
            const ctx = canvas.getContext("2d");
            ctx.scale(scale, scale);

            const img = new Image();
            img.onload = function() {
                // Fondo
                ctx.fillStyle = "#e6f0f8";
                ctx.fillRect(0, 0, width, height + headerHeight + footerHeight);
                
                // Franjas Blancas
                ctx.fillStyle = "#ffffff";
                ctx.fillRect(0, 0, width, headerHeight); 
                ctx.fillRect(0, headerHeight + height, width, footerHeight); 
                
                // Dibujar Mapa
                ctx.drawImage(img, 0, headerHeight);
                
                // Titulo Dinámico
                const metricInfo = METRICAS[state.currentMetric];
                const anioNode = document.querySelector('.siarhe-dynamic-year');
                const anio = anioNode ? anioNode.innerText : new Date().getFullYear();
                const titleNode = container.querySelector('h2.siarhe-title');
                const entidadNombre = titleNode ? titleNode.innerText.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ ]/g, '').trim() : "México";
                
                const titleText = `${metricInfo.fullLabel} en ${entidadNombre} (${anio})`;
                ctx.fillStyle = "#111111";
                ctx.font = "bold 20px Arial, sans-serif";
                ctx.textAlign = "center";
                ctx.fillText(titleText, width / 2, 38);
                
                // Referencias
                let refText = "Fuente de Datos: SIARHE.";
                const refNodes = container.querySelectorAll('.siarhe-ref-col p');
                if(refNodes.length > 0) {
                    refText = refNodes[0].innerText.replace(/\n/g, ' | '); 
                }

                ctx.fillStyle = "#444444";
                ctx.font = "12px Arial, sans-serif";
                ctx.textAlign = "center";
                
                // 🌟 Centrado vertical de la referencia y sin disclaimer inferior
                wrapText(ctx, refText, width / 2, height + headerHeight + 40, width - 40, 16);
                
                // Descarga
                const slug = container.dataset.slug || 'mapa';
                const metricSlug = state.currentMetric.replace(/_/g, '-');
                const nombreArchivo = `${slug}-${metricSlug}-${anio}${withLabels ? '-etiquetas' : ''}.png`;
                
                const a = document.createElement("a");
                a.download = nombreArchivo;
                a.href = canvas.toDataURL("image/png");
                a.click();

                if (withLabels) state.gLabels.style("display", "none");
            };
            img.src = "data:image/svg+xml;base64," + btoa(unescape(encodeURIComponent(svgData)));
        }, 150);
    }

    // ==========================================
    // 6. TOOLTIP Y ACTUALIZACIÓN VISUAL
    // ==========================================
    function showTooltip(event, d, state) {
        let cve = getGeoKey(d.properties);
        const row = state.dataMap.get(cve);
        const nombre = row ? row.estado : (d.properties.NOM_ENT || d.properties.NOMGEO || "Sin Datos");
        
        let html = `<div style="font-weight:bold; border-bottom:1px solid #555; padding-bottom:3px; margin-bottom:4px; font-size:13px;">${nombre}</div>`;
        
        if (row) {
            const mKey = state.currentMetric;
            const pKey = METRICAS[mKey].pair || mKey; 
            html += `<div style="display:flex; justify-content:space-between; margin-bottom:2px;"><span style="color:#ccc">Población:</span> <b>${row.poblacion.toLocaleString('es-MX')}</b></div>`;
            if (mKey !== 'poblacion') {
                const labelAbs = METRICAS[pKey].label.replace('Total ','');
                html += `<div style="display:flex; justify-content:space-between; margin-bottom:2px;"><span style="color:#ccc">${labelAbs}:</span> <b>${row[pKey].toLocaleString('es-MX')}</b></div>`;
                html += `<div style="display:flex; justify-content:space-between; margin-top:4px; padding-top:4px; border-top:1px dashed #555; color:#ffcc00;"><span>${METRICAS[mKey].tipo === 'tasa' ? METRICAS[mKey].label : 'Tasa'}:</span> <b>${(METRICAS[mKey].tipo === 'tasa' ? row[mKey] : (row['tasa_'+mKey.replace('enfermeras_','')] || 0)).toFixed(2)}</b></div>`;
            }
        }
        state.tooltip.html(html).style("display", "block").style("opacity", 1).style("left", (event.pageX+15)+"px").style("top", (event.pageY-28)+"px");
    }

    function updateMapVisuals(container, state) {
        const metric = state.currentMetric;
        const values = state.csvData.filter(d => !d.isTotal && !d.isSpecial && d[metric] > 0).map(d => d[metric]).sort(d3.ascending);
        if(values.length === 0) return;

        const min = d3.min(values); const max = d3.max(values);
        const q1 = d3.quantile(values, 0.25); const q2 = d3.quantile(values, 0.50); const q3 = d3.quantile(values, 0.75);
        const colorScale = d3.scaleLinear().domain([min, q1, q2, q3, max]).range(COLOR_RANGE).clamp(true);

        state.gPaths.selectAll("path.siarhe-feature").transition().duration(500)
            .style("fill", d => {
                let cve = getGeoKey(d.properties); const row = state.dataMap.get(cve);
                if (!row) return COLOR_NULL; if (row[metric] === 0) return COLOR_ZERO;
                return colorScale(row[metric]);
            });

        // 🌟 OBTENER ZOOM ACTUAL (Para que las etiquetas nuevas nazcan con el tamaño correcto)
        let currentK = 1;
        if (state.svg) {
            try { currentK = d3.zoomTransform(state.svg.node()).k; } catch(e) {}
        }

        // FILTRO ANTI-ISLAS
        const maxAreaFeatures = new Map();
        state.geoData.features.forEach(d => {
            let cve = getGeoKey(d.properties);
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
            .attr("fill", "#000000") 
            .attr("stroke", "#ffffff") 
            .attr("stroke-width", 2.5 / currentK) // Dinámico al zoom
            .attr("paint-order", "stroke fill") 
            .attr("stroke-linejoin", "round")
            .style("font-size", `${10 / currentK}px`) // Dinámico al zoom
            .style("font-weight", "bold")
            .text(d => {
                let cve = getGeoKey(d.properties);
                const row = state.dataMap.get(cve);
                return row ? row.estado : (d.properties.NOM_ENT || d.properties.NOMGEO || "");
            });

        renderLegend(state, {min, q1, q2, q3, max});
    }

    function renderLegend(state, stats) {
        const g = state.gLegend; g.html("");
        const label = METRICAS[state.currentMetric].label;
        const domain = [stats.min, stats.q1, stats.q2, stats.q3, stats.max];

        g.append("rect").attr("x", -10).attr("y", -25).attr("width", 130).attr("height", 260)
         .attr("fill", "rgba(255,255,255,0.85)").attr("rx", 5).style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.1))");
        g.append("text").attr("x", 0).attr("y", -10).text(label).style("font-size", "11px").style("font-weight", "bold");
        
        const h = 150, w = 15, step = h/4;
        g.append("rect").attr("x", 0).attr("y", 10).attr("width", w).attr("height", h)
         .style("fill", `url(#${state.gradientId})`).style("stroke", "#ccc");

        domain.forEach((v, i) => {
            const y = 10 + h - (i * step);
            g.append("line").attr("x1", w).attr("x2", w+5).attr("y1", y).attr("y2", y).style("stroke", "#666");
            g.append("text").attr("x", w+8).attr("y", y+4).text(v.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2})).style("font-size", "10px");
        });

        const g0 = g.append("g").attr("transform", `translate(0, ${h+35})`);
        g0.append("rect").attr("width", 12).attr("height", 12).style("fill", COLOR_ZERO);
        g0.append("text").attr("x", 18).attr("y", 10).text("0.00").style("font-size", "11px");

        const gN = g.append("g").attr("transform", `translate(0, ${h+60})`);
        gN.append("rect").attr("width", 12).attr("height", 12).style("fill", COLOR_NULL);
        gN.append("text").attr("x", 18).attr("y", 10).text("S/D").style("font-size", "11px");
    }

    // ==========================================
    // 7. CONTROLES Y MARCADORES
    // ==========================================
    function renderMainControls(container, state, onUpdate) {
        const ph = container.querySelector('.siarhe-controls-placeholder');
        if (!ph) return;
        ph.innerHTML = '';

        const wrapper = document.createElement('div');
        wrapper.className = 'siarhe-controls'; 

        const grpInd = document.createElement('div');
        grpInd.className = 'siarhe-control-group';
        grpInd.innerHTML = `<label>Indicador</label>`;
        const selInd = document.createElement('select'); 
        selInd.className = 'siarhe-metric-select'; 
        Object.entries(METRICAS).forEach(([key, info]) => {
            const opt = document.createElement('option');
            opt.value = key; opt.textContent = info.fullLabel; // Usar label completo
            if (key === state.currentMetric) opt.selected = true;
            selInd.appendChild(opt);
        });
        selInd.onchange = (e) => { 
            state.currentMetric = e.target.value; 
            sortConfig = { key: 'tasa', direction: 'desc' };
            onUpdate(); 
        };
        grpInd.appendChild(selInd);
        wrapper.appendChild(grpInd);

        if (typeof siarheData !== 'undefined' && siarheData.markers) {
            const grpMarc = document.createElement('div');
            grpMarc.className = 'siarhe-control-group';
            grpMarc.innerHTML = `<label>Marcadores</label>`;
            const field = document.createElement('div'); field.className = 'mc-field';
            
            const trigger = document.createElement('div'); 
            trigger.className = 'mc-trigger is-placeholder'; 
            trigger.textContent = 'Seleccionar...';
            state.markerTrigger = trigger; 

            const menu = document.createElement('div'); menu.className = 'mc-menu';
            
            Object.keys(siarheData.markers).forEach(key => {
                const label = MARCADOR_NOMBRES[key] || key;
                const opt = document.createElement('div'); opt.className = 'mc-option';
                opt.innerHTML = `<input type="checkbox" class="mc-check" value="${key}"> ${label}`;
                
                opt.addEventListener('click', (e) => {
                    if (e.target.tagName !== 'INPUT') {
                        const chk = opt.querySelector('input');
                        chk.checked = !chk.checked;
                        toggleMarker(key, state);
                    }
                });
                opt.querySelector('input').addEventListener('click', (e) => { e.stopPropagation(); toggleMarker(key, state); });
                menu.appendChild(opt);
            });

            trigger.onclick = (e) => { e.stopPropagation(); menu.classList.toggle('open'); };
            document.addEventListener('click', () => menu.classList.remove('open'));
            field.append(trigger, menu); grpMarc.appendChild(field); wrapper.appendChild(grpMarc);
        }
        ph.appendChild(wrapper);
    }

    async function toggleMarker(type, state) {
        if (state.activeMarkers.has(type)) { state.activeMarkers.delete(type); } 
        else {
            state.activeMarkers.add(type);
            if (!state.markersData[type]) {
                const url = siarheData.markers[type];
                if(url) {
                    try {
                        const raw = await d3.csv(url);
                        state.markersData[type] = raw.map(d => ({
                            clues: d.CLUES || d.clues,
                            institucion: d.Institucion || d.institucion,
                            nombre: d.NOMBRE_UNIDAD || d.Nombre_Unidad || "Unidad sin nombre",
                            municipio: d.MUNICIPIO || d.Municipio,
                            lat: +(d.LATITUD || d.Latitud || 0),
                            lon: +(d.LONGITUD || d.Longitud || 0),
                            tipo: type
                        })).filter(d => !isNaN(d.lat) && d.lat !== 0);
                    } catch(e) { console.error("Error markers", e); }
                }
            }
        }
        updateMarkers(state);
        updateMarkerDropdownText(state);
    }

    function updateMarkerDropdownText(state) {
        if (!state.markerTrigger) return;
        const selected = Array.from(state.activeMarkers);
        if (selected.length === 0) {
            state.markerTrigger.textContent = 'Seleccionar...';
            state.markerTrigger.classList.add('is-placeholder');
        } else {
            const labels = selected.map(k => MARCADOR_NOMBRES[k] || k);
            state.markerTrigger.textContent = labels.join(', ');
            state.markerTrigger.classList.remove('is-placeholder');
        }
    }

    function updateMarkers(state) {
        let allPoints = [];
        state.activeMarkers.forEach(type => { if(state.markersData[type]) allPoints = allPoints.concat(state.markersData[type]); });

        const circles = state.gMarkers.selectAll("circle").data(allPoints);
        circles.exit().remove();
        circles.enter().append("circle")
            .attr("r", 5)
            .merge(circles)
            .attr("cx", d => state.projection([d.lon, d.lat])[0])
            .attr("cy", d => state.projection([d.lon, d.lat])[1])
            .attr("fill", d => MARCADOR_ESTILOS[d.tipo]?.fill || "#000")
            .attr("stroke", "#fff").attr("stroke-width", 1)
            .style("cursor", "pointer")
            .on("mouseover", (e, d) => {
                let html = `<strong>${d.tipo === 'CATETER' ? 'Clínica de Catéter' : 'Clínica de Heridas'}</strong>`;
                html += `<div style="font-size:12px; margin-top:4px;">
                    <div>${d.nombre}</div>
                    <div style="color:#ccc; font-size:11px;">${d.institucion}</div>
                    <div style="color:#ccc; font-size:11px;">${d.municipio || ''}</div>
                    <div style="font-size:10px; margin-top:2px;">CLUES: ${d.clues}</div>
                </div>`;
                state.tooltip.html(html).style("display", "block").style("opacity", 1).style("left", (e.pageX+10)+"px").style("top", (e.pageY-20)+"px");
            })
            .on("mouseout", () => state.tooltip.style("display", "none"));
    }

    // ==========================================
    // 8. TABLA DE DATOS
    // ==========================================
    function handleMapClick(d, state) {
        let cve = getGeoKey(d.properties);
        if (typeof siarheData !== 'undefined' && siarheData.entity_urls && siarheData.entity_urls[cve]) {
            window.location.href = siarheData.entity_urls[cve];
        }
    }

    function renderZoomButtons(container, svg, zoom, cveEnt) {
        const ctrlDiv = document.createElement('div'); ctrlDiv.className = 'zoom-controles'; container.appendChild(ctrlDiv);
        const createBtn = (l, t, cb) => { const b = document.createElement('button'); b.className = 'boton'; b.innerHTML = l; b.title = t; b.onclick = cb; ctrlDiv.appendChild(b); };
        createBtn('+', 'Acercar', (e) => { e.preventDefault(); svg.transition().call(zoom.scaleBy, 1.5); });
        createBtn('–', 'Alejar', (e) => { e.preventDefault(); svg.transition().call(zoom.scaleBy, 0.6); });
        createBtn('⟳', 'Reset', (e) => { e.preventDefault(); svg.transition().call(zoom.transform, d3.zoomIdentity); });
        const isNational = (cveEnt === '33' || cveEnt === '00'); 
        if (!isNational && typeof siarheData !== 'undefined' && siarheData.home_url) {
            createBtn('🏠', 'Ir a Nacional', (e) => { e.preventDefault(); window.location.href = siarheData.home_url; });
        }
    }

    function initTableStructure(container, state) {
        const div = container.querySelector('.siarhe-table-container'); div.innerHTML = '';
        const table = document.createElement('table'); table.className = 'siarhe-data-table';
        state.tableElements = { table, thead: table.createTHead(), tbody: table.createTBody() };
        div.appendChild(table);
        updateTable(container, state);
    }

    function updateTable(container, state) {
        if (!state.csvData.length) return;
        const { thead, tbody } = state.tableElements;
        const mKey = state.currentMetric;
        const pKey = METRICAS[mKey].pair || mKey;
        const isPob = (mKey === 'poblacion');
        
        const enfDataKey = isPob ? null : pKey; 
        const tasaDataKey = isPob ? null : mKey; 

        const cols = [
            { id: 'estado', label: 'Entidad / Mpio.', isNum: false },
            { id: 'poblacion', label: 'Población', isNum: true },
            { id: 'enfermeras', label: 'Enfermeras', isNum: true, dataKey: enfDataKey, dash: isPob },
            { id: 'tasa', label: 'Tasa', isNum: true, dataKey: tasaDataKey, dash: isPob }
        ];

        thead.innerHTML = ''; const tr = document.createElement('tr');
        cols.forEach(c => {
            const th = document.createElement('th');
            let arrow = '↕';
            if (sortConfig.key === c.id) arrow = sortConfig.direction === 'asc' ? '▲' : '▼';
            th.innerHTML = `${c.label} <small>${arrow}</small>`;
            th.onclick = () => {
                if (sortConfig.key === c.id) sortConfig.direction = sortConfig.direction==='asc'?'desc':'asc';
                else { sortConfig.key = c.id; sortConfig.direction = c.isNum ? 'desc':'asc'; }
                updateTable(container, state);
            };
            tr.appendChild(th);
        });
        thead.appendChild(tr);

        const getVal = (r, colId) => {
            if (colId === 'estado') return r.estado;
            if (colId === 'poblacion') return r.poblacion;
            if (colId === 'enfermeras') return isPob ? 0 : (r[enfDataKey] || 0);
            if (colId === 'tasa') return isPob ? 0 : (r[tasaDataKey] || 0);
            return 0;
        };

        const rows = state.csvData.filter(r => !r.isTotal && !r.isSpecial).sort((a,b) => {
            const va = getVal(a, sortConfig.key); const vb = getVal(b, sortConfig.key);
            if (va < vb) return sortConfig.direction === 'asc' ? -1 : 1;
            if (va > vb) return sortConfig.direction === 'asc' ? 1 : -1;
            return 0;
        });
        const fixed = state.csvData.filter(r => r.isTotal || r.isSpecial);

        tbody.innerHTML = '';
        [...rows, ...fixed].forEach(r => {
            const row = document.createElement('tr');
            if(r.isTotal) row.className = 'siarhe-row-total';
            
            cols.forEach(c => {
                const td = document.createElement('td');
                if (c.id === 'estado') {
                    td.innerHTML = !r.isTotal ? `<strong>${r.estado}</strong>` : r.estado;
                } else if (c.dash) {
                    td.textContent = '—';
                } else {
                    const v = (c.id === 'poblacion') ? r.poblacion : (r[c.dataKey] || 0);
                    if (c.id === 'tasa') td.textContent = v.toFixed(2);
                    else td.textContent = v.toLocaleString('es-MX');
                }
                row.appendChild(td);
            });
            tbody.appendChild(row);
        });
    }
});