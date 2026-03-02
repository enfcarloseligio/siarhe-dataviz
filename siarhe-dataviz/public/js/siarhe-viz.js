// 📢 LOG INICIAL
console.log("%c 🚀 SIARHE JS V31: Tooltip en Pantalla Completa Reparado", "background: #222; color: #bada55");

document.addEventListener('DOMContentLoaded', function() {

    if (typeof d3 === 'undefined') { console.error("❌ ERROR: D3.js no cargó."); return; }

    // ==========================================
    // 1. CONFIGURACIÓN
    // ==========================================
    const METRICAS = {
        'tasa_total':                 { label: 'Tasa Total', fullLabel: 'Tasa de enfermeras por cada mil habitantes', tipo: 'tasa', pair: 'enfermeras_total' },
        'enfermeras_total':           { label: 'Total Enf.', fullLabel: 'Total de profesionales de enfermería',      tipo: 'absoluto', pair: 'enfermeras_total' },
        
        'tasa_primer':                { label: 'Tasa 1er Nivel', fullLabel: 'Tasa de enfermeras en 1er Nivel de Atención',       tipo: 'tasa', pair: 'enfermeras_primer' },
        'enfermeras_primer':          { label: 'Enf. 1er Nivel', fullLabel: 'Enfermeras en 1er Nivel de Atención', tipo: 'absoluto', pair: 'enfermeras_primer' },
        
        'tasa_segundo':               { label: 'Tasa 2do Nivel', fullLabel: 'Tasa de enfermeras en 2dor Nivel de Atención',       tipo: 'tasa', pair: 'enfermeras_segundo' },
        'enfermeras_segundo':         { label: 'Enf. 2do Nivel', fullLabel: 'Enfermeras en 2do Nivel de Atención', tipo: 'absoluto', pair: 'enfermeras_segundo' },
        
        'tasa_tercer':                { label: 'Tasa 3er Nivel', fullLabel: 'Tasa de enfermeras en 3er Nivel de Atención',       tipo: 'tasa', pair: 'enfermeras_tercer' },
        'enfermeras_tercer':          { label: 'Enf. 3er Nivel', fullLabel: 'Enfermeras en 3er Nivel de Atención', tipo: 'absoluto', pair: 'enfermeras_tercer' },
        
        'tasa_apoyo':                 { label: 'Tasa Apoyo', fullLabel: 'Tasa de enfermeras en establecimientos de apoyo',       tipo: 'tasa', pair: 'enfermeras_apoyo' },
        'enfermeras_apoyo':           { label: 'Enf. Apoyo', fullLabel: 'Enfermeras en establecimientos de apoyo', tipo: 'absoluto', pair: 'enfermeras_apoyo' },
        
        'tasa_administrativas':       { label: 'Tasa Admin.', fullLabel: 'Tasa de enfermeras con funciones administrativas',     tipo: 'tasa', pair: 'enfermeras_administrativas' },
        'enfermeras_administrativas': { label: 'Enf. Admin.', fullLabel: 'Enfermeras con funciones administrativas', tipo: 'absoluto', pair: 'enfermeras_administrativas' },
        
        'tasa_escuelas':              { label: 'Tasa Escuelas', fullLabel: 'Tasa de enfermeras en escuelas de enfermería',       tipo: 'tasa', pair: 'enfermeras_escuelas' },
        'enfermeras_escuelas':        { label: 'Enf. Escuelas', fullLabel: 'Enfermeras en escuelas de enfermería', tipo: 'absoluto', pair: 'enfermeras_escuelas' },
        
        'tasa_no_aplica':             { label: 'Tasa Otros Est.', fullLabel: 'Tasa de enfermeras en otros establecimientos',       tipo: 'tasa', pair: 'enfermeras_no_aplica' },
        'enfermeras_no_aplica':       { label: 'Enf. Otros Est.', fullLabel: 'Enfermeras en otros establecimientos', tipo: 'absoluto', pair: 'enfermeras_no_aplica' },
        
        'tasa_no_asignado':           { label: 'Tasa No Asignado', fullLabel: 'Tasa de enfermeras con funciones no asignadas',       tipo: 'tasa', pair: 'enfermeras_no_asignado' },
        'enfermeras_no_asignado':     { label: 'Enf. No Asignado', fullLabel: 'Enfermeras con funciones no asignadas', tipo: 'absoluto', pair: 'enfermeras_no_asignado' },
        
        'poblacion':                  { label: 'Población',  fullLabel: 'Población total',          tipo: 'absoluto', pair: 'poblacion' }
    };

    const MARCADOR_NOMBRES = { 
        'CATETER': "Clínicas de catéteres", 
        'HERIDAS': "Clínicas de heridas",
        'ESTAB_1': "Establecimientos (1er Nivel)",
        'ESTAB_2': "Establecimientos (2do Nivel)",
        'ESTAB_3': "Establecimientos (3er Nivel)",
        'ESTAB_6': "Establecimientos (No Aplica)"
    };

    const styles = getComputedStyle(document.documentElement);
    const getVar = (n, f) => styles.getPropertyValue(n).trim() || f;
    const COLOR_RANGE = [ getVar('--s-map-c1', '#eff3ff'), getVar('--s-map-c2', '#bdd7e7'), getVar('--s-map-c3', '#9ecae1'), getVar('--s-map-c4', '#6baed6'), getVar('--s-map-c5', '#08519c') ];
    const COLOR_ZERO = getVar('--s-map-zero', '#d9d9d9'); 
    const COLOR_NULL = getVar('--s-map-null', '#000000'); 

    let sortConfig = { key: 'tasa', direction: 'desc' };

    function cleanKey(val) { return (val === undefined || val === null) ? "" : val.toString().trim(); }
    function getGeoKey(props) { return cleanKey(props.ID || props.CVE_ENT || props.cve_ent || props.id); }
    function parseNum(val) {
        if (!val) return 0;
        const n = parseFloat(val.toString().replace(/,/g, '').replace(/\s/g, ''));
        return isNaN(n) ? 0 : n;
    }
    function getD3Shape(shapeName) {
        const shapes = { 'circle': d3.symbolCircle, 'square': d3.symbolSquare, 'triangle': d3.symbolTriangle, 'diamond': d3.symbolDiamond, 'star': d3.symbolStar, 'cross': d3.symbolCross };
        return shapes[shapeName] || d3.symbolCircle;
    }
    function getMarkerStyle(type, state) {
        if (state.markerStyles[type]) return state.markerStyles[type];
        return { shape: 'circle', fill: '#000000', stroke: '#ffffff' }; 
    }
    function getColValue(row, possibleNames) {
        const keys = Object.keys(row);
        for (let name of possibleNames) {
            const target = name.toLowerCase();
            const foundKey = keys.find(k => k.trim().toLowerCase() === target);
            if (foundKey) return row[foundKey].trim();
        }
        return "";
    }

    const wrappers = document.querySelectorAll('.siarhe-viz-wrapper');
    wrappers.forEach(initVisualization);

    function initVisualization(container) {
        const cveEnt = container.dataset.cveEnt;
        const geojsonUrl = container.dataset.geojson;
        const csvUrl = container.dataset.csv;
        const mode = container.dataset.mode;
        const loading = container.querySelector('.siarhe-loading-overlay');

        let MARCADOR_ESTILOS = {};
        try { MARCADOR_ESTILOS = JSON.parse(container.dataset.markerConfig || '{}'); } catch(e) {}

        let MARCADOR_URLS = {};
        try { MARCADOR_URLS = JSON.parse(container.dataset.markerUrls || '{}'); } catch(e) {}

        let state = {
            geoData: null, csvData: [], dataMap: new Map(),
            currentMetric: 'tasa_total',
            zoom: null, gLegend: null, gMarkerLegend: null, gradientId: null,
            svg: null, gMain: null, gPaths: null, gLabels: null, gMarkers: null, 
            activeMarkers: new Set(), markersData: {}, 
            markerStyles: MARCADOR_ESTILOS, markerUrls: MARCADOR_URLS,
            tooltip: null, markerTrigger: null, lastClickTime: 0,
            rawEstabData: null 
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
                    setupExcelExport(container, state);
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
            if ((cve >= 1 && cve <= 32) || isExtranjero || isLegacy8888) { sumaTotal += (row.enfermeras_total || 0); }
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
        if (rowTotal) { totalAbs = rowTotal[pairKey]; valorMuestra = rowTotal[state.currentMetric]; } 
        else {
            state.csvData.forEach(row => { if (!row.isTotal && !row.isSpecial && METRICAS[pairKey].tipo === 'absoluto') totalAbs += row[pairKey]; });
            valorMuestra = (info.tipo === 'tasa') ? 0 : totalAbs;
        }
        const absFmt = totalAbs.toLocaleString('es-MX');
        const valFmt = valorMuestra.toLocaleString('es-MX', { maximumFractionDigits: 2 });
        headerDiv.innerHTML = info.tipo === 'tasa' 
            ? `<span style="margin-right:15px;">${info.fullLabel}: <strong style="color:#0A66C2; font-size:1.2em;">${valFmt}</strong></span><span>Enfermeras: <strong style="color:#0A66C2; font-size:1.2em;">${absFmt}</strong></span>`
            : `<span>${info.fullLabel}: <strong style="color:#0A66C2; font-size:1.2em;">${valFmt}</strong></span>`;
    }

    function renderMap(container, state, cveEnt) {
        const mapDiv = container.querySelector('.siarhe-map-container');
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
        const linearGradient = defs.append("linearGradient").attr("id", state.gradientId).attr("x1", "0%").attr("y1", "100%").attr("x2", "0%").attr("y2", "0%");
        linearGradient.selectAll("stop").data(COLOR_RANGE).enter().append("stop").attr("offset", (d, i) => `${(i / (COLOR_RANGE.length - 1)) * 100}%`).attr("stop-color", d => d);

        const gMain = svg.append("g").attr("class", "map-layer"); state.gMain = gMain;
        state.gPaths = gMain.append("g").attr("class", "paths-layer");
        state.gLabels = gMain.append("g").attr("class", "labels-layer").style("display", "none").style("pointer-events", "none");
        state.gMarkers = gMain.append("g").attr("class", "markers-layer");
        
        state.gLegend = svg.append("g").attr("class", "siarhe-legend-group").attr("transform", `translate(30, 40)`); 
        state.gMarkerLegend = svg.append("g").attr("class", "siarhe-marker-legend-group").attr("transform", `translate(30, ${height - 180})`);

        const projection = d3.geoMercator().fitSize([width, height], state.geoData); state.projection = projection;
        const path = d3.geoPath().projection(projection); state.path = path; 

        // 🌟 SOLUCIÓN TOOLTIP EN PANTALLA COMPLETA 🌟
        // En lugar de pegarlo en el body, lo pegamos adentro de la caja del mapa
        d3.selectAll(mapDiv.querySelectorAll(".siarhe-tooltip")).remove(); 
        state.tooltip = d3.select(mapDiv).append("div")
            .attr("class", "siarhe-tooltip")
            .style("opacity", 0)
            .style("display", "none");

        state.gPaths.selectAll("path.siarhe-feature")
            .data(state.geoData.features).enter().append("path")
            .attr("d", path).attr("class", "siarhe-feature")
            .attr("stroke", "#fff").attr("stroke-width", "0.5").style("fill", COLOR_NULL)
            .on("mouseover", (e, d) => showTooltip(e, d, state)) 
            .on("mousemove", (e) => {
                // Las coordenadas ahora son relativas a mapDiv, no a la página entera
                const [mx, my] = d3.pointer(e, mapDiv);
                state.tooltip.style("left", (mx + 15) + "px").style("top", (my - 28) + "px");
            })
            .on("mouseout", () => { 
                state.gPaths.selectAll("path.siarhe-feature").style("stroke", "#fff").style("stroke-width", "0.5"); 
                state.tooltip.style("opacity", 0).style("display", "none"); 
            })
            .on("click", (e, d) => {
                const ahora = Date.now();
                if (ahora - state.lastClickTime < 400) { handleMapClick(d, state); } 
                else { showTooltip(e, d, state); }
                state.lastClickTime = ahora;
            });

        const zoom = d3.zoom()
            .scaleExtent([1, 8])
            .on("zoom", (e) => {
                gMain.attr("transform", e.transform);
                const k = e.transform.k;

                state.gLabels.selectAll("text.siarhe-label")
                    .style("font-size", `${14 / k}px`)
                    .attr("stroke-width", 2.5 / k);
                
                const symbolCache = {};
                state.activeMarkers.forEach(type => {
                    const styleCfg = getMarkerStyle(type, state);
                    symbolCache[type] = d3.symbol().type(getD3Shape(styleCfg.shape)).size(150 / (k * k))();
                });

                state.gMarkers.selectAll("path.siarhe-marker")
                    .attr("d", d => symbolCache[d.tipo])
                    .attr("stroke-width", 1.5 / k);
            });
        
        svg.call(zoom).on("dblclick.zoom", null);
        state.zoom = zoom;
        renderZoomButtons(mapDiv, svg, zoom, cveEnt);
        updateMapVisuals(container, state);
    }

    function setupActionButtons(container, state) {
        const btnLabels = container.querySelector('.siarhe-btn-toggle-labels');
        const btnPng = container.querySelector('.siarhe-btn-download-png');
        if (btnPng) btnPng.addEventListener('click', (e) => { e.preventDefault(); downloadMapAsPNG(container, state, false); });
        if (btnLabels) btnLabels.addEventListener('click', (e) => { e.preventDefault(); downloadMapAsPNG(container, state, true); });
    }

    function setupExcelExport(container, state) {
        const btnExcel = container.querySelector('.siarhe-btn-download-excel');
        if (!btnExcel) return;

        btnExcel.addEventListener('click', (e) => {
            e.preventDefault();
            let csvContent = '\uFEFF'; 
            const isNacional = (container.dataset.cveEnt === '33' || container.dataset.slug === 'republica-mexicana');
            const colEntidad = isNacional ? 'Entidad Federativa' : 'Municipio';
            csvContent += `${colEntidad},Población,Enfermeras,Tasa\n`;
            
            const mKey = state.currentMetric; const pKey = METRICAS[mKey].pair || mKey; const isPob = (mKey === 'poblacion');
            const enfDataKey = isPob ? null : pKey; const tasaDataKey = isPob ? null : (METRICAS[mKey].tipo === 'tasa' ? mKey : pKey.replace('enfermeras_', 'tasa_'));
            
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
            
            [...rows, ...fixed].forEach(r => {
                const estado = `"${r.estado}"`; const pob = r.poblacion || 0;
                const enf = isPob ? '-' : (r[enfDataKey] || 0); const tasa = isPob ? '-' : (r[tasaDataKey] || 0).toFixed(2);
                csvContent += `${estado},${pob},${enf},${tasa}\n`;
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            const anioNode = document.querySelector('.siarhe-dynamic-year');
            const anio = anioNode ? anioNode.innerText : new Date().getFullYear();
            const slug = container.dataset.slug || 'datos';
            const metricSlug = state.currentMetric.replace(/_/g, '-');
            a.download = `SIARHE_${slug}_${metricSlug}_${anio}.csv`;
            a.href = url; a.style.display = "none"; document.body.appendChild(a); a.click(); document.body.removeChild(a);
        });
    }

    function wrapText(context, text, x, y, maxWidth, lineHeight) {
        let words = text.split(' '), line = '';
        for(let n = 0; n < words.length; n++) {
          let testLine = line + words[n] + ' '; let metrics = context.measureText(testLine); let testWidth = metrics.width;
          if (testWidth > maxWidth && n > 0) { context.fillText(line, x, y); line = words[n] + ' '; y += lineHeight;
          } else { line = testLine; }
        }
        context.fillText(line, x, y);
    }

    function downloadMapAsPNG(container, state, withLabels) {
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
                
                const metricInfo = METRICAS[state.currentMetric];
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
                wrapText(ctx, refText, 1920 / 2, 1000, 1800, 28);
                
                const slug = container.dataset.slug || 'mapa'; 
                const metricSlug = state.currentMetric.replace(/_/g, '-');
                const nombreArchivo = `${slug}-${metricSlug}-${anio}${withLabels ? '-etiquetas' : ''}-FHD.png`;
                
                const a = document.createElement("a"); a.download = nombreArchivo; a.href = canvas.toDataURL("image/png"); a.click();
                if (withLabels) state.gLabels.style("display", "none");
            };
            img.src = "data:image/svg+xml;base64," + btoa(unescape(encodeURIComponent(svgData)));
        }, 150);
    }

    function showTooltip(event, d, state) {
        let cve = getGeoKey(d.properties);
        const row = state.dataMap.get(cve);
        const nombre = row ? row.estado : (d.properties.NOM_ENT || d.properties.NOMGEO || "Sin Datos");
        
        let html = `<div class="tooltip-header">${nombre}</div>`;
        if (row) {
            const mKey = state.currentMetric; const pKey = METRICAS[mKey].pair || mKey; 
            html += `<div style="display:flex; justify-content:space-between; margin-bottom:2px;"><span style="color:#A5B4C3">Población:</span> <b>${row.poblacion.toLocaleString('es-MX')}</b></div>`;
            if (mKey !== 'poblacion') {
                const labelAbs = METRICAS[pKey].label.replace('Total ','');
                html += `<div style="display:flex; justify-content:space-between; margin-bottom:2px;"><span style="color:#A5B4C3">${labelAbs}:</span> <b>${row[pKey].toLocaleString('es-MX')}</b></div>`;
                html += `<div style="display:flex; justify-content:space-between; margin-top:6px; padding-top:6px; border-top:1px solid #475569;">
                            <span style="color:#F8FAFC">${METRICAS[mKey].tipo === 'tasa' ? METRICAS[mKey].label : 'Tasa'}:</span> 
                            <strong>${(METRICAS[mKey].tipo === 'tasa' ? row[mKey] : (row['tasa_'+mKey.replace('enfermeras_','')] || 0)).toFixed(2)}</strong>
                         </div>`;
            }
        }
        
        // 🌟 SOLUCIÓN TOOLTIP EN PANTALLA COMPLETA 🌟
        // Extraemos el contenedor mapDiv que es el padre del SVG (donde ocurre el evento e)
        // d3.pointer devuelve [x, y] relativos al mapDiv
        const mapDiv = document.querySelector('.siarhe-map-container');
        const [mx, my] = d3.pointer(event, mapDiv);

        state.tooltip.html(html)
            .style("display", "block")
            .style("opacity", 1)
            .style("left", (mx + 15) + "px")
            .style("top", (my - 28) + "px");
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

        let currentK = 1; if (state.svg) { try { currentK = d3.zoomTransform(state.svg.node()).k; } catch(e) {} }

        const maxAreaFeatures = new Map();
        state.geoData.features.forEach(d => {
            let cve = getGeoKey(d.properties); let area = d3.geoArea(d);
            if (!maxAreaFeatures.has(cve) || area > maxAreaFeatures.get(cve).area) { maxAreaFeatures.set(cve, { feature: d, area: area }); }
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
            .attr("fill", "#0F172A").attr("stroke", "#ffffff").attr("stroke-width", 2.5 / currentK).attr("paint-order", "stroke fill").attr("stroke-linejoin", "round")
            .style("font-size", `${14 / currentK}px`).style("font-weight", "bold")
            .text(d => { let cve = getGeoKey(d.properties); const row = state.dataMap.get(cve); return row ? row.estado : (d.properties.NOM_ENT || d.properties.NOMGEO || ""); });

        renderLegend(state, {min, q1, q2, q3, max});
    }

    function renderLegend(state, stats) {
        const g = state.gLegend; g.html("");
        const label = METRICAS[state.currentMetric].label;
        const domain = [stats.min, stats.q1, stats.q2, stats.q3, stats.max];

        g.append("rect").attr("x", -10).attr("y", -25).attr("width", 140).attr("height", 270)
         .attr("fill", "rgba(255,255,255,0.9)").attr("rx", 5).style("filter", "drop-shadow(2px 2px 2px rgba(0,0,0,0.1))");
        g.append("text").attr("x", 0).attr("y", -10).text(label).style("font-size", "12px").style("font-weight", "bold").style("fill", "#0F172A");
        
        const h = 150, w = 15, step = h/4;
        g.append("rect").attr("x", 0).attr("y", 10).attr("width", w).attr("height", h).style("fill", `url(#${state.gradientId})`).style("stroke", "#ccc");

        domain.forEach((v, i) => {
            const y = 10 + h - (i * step);
            g.append("line").attr("x1", w).attr("x2", w+5).attr("y1", y).attr("y2", y).style("stroke", "#666");
            g.append("text").attr("x", w+8).attr("y", y+4).text(v.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2})).style("font-size", "11px").style("fill", "#475569");
        });

        const g0 = g.append("g").attr("transform", `translate(0, ${h+40})`);
        g0.append("rect").attr("width", 12).attr("height", 12).style("fill", COLOR_ZERO);
        g0.append("text").attr("x", 18).attr("y", 10).text("0.00").style("font-size", "11px").style("fill", "#475569");

        const gN = g.append("g").attr("transform", `translate(0, ${h+65})`);
        gN.append("rect").attr("width", 12).attr("height", 12).style("fill", COLOR_NULL);
        gN.append("text").attr("x", 18).attr("y", 10).text("S/D").style("font-size", "11px").style("fill", "#475569");
    }

    function renderMainControls(container, state, onUpdate) {
        const ph = container.querySelector('.siarhe-controls-placeholder');
        if (!ph) return; ph.innerHTML = '';
        const wrapper = document.createElement('div'); wrapper.className = 'siarhe-controls'; 

        const grpInd = document.createElement('div'); grpInd.className = 'siarhe-control-group'; grpInd.innerHTML = `<label>Indicador</label>`;
        const selInd = document.createElement('select'); selInd.className = 'siarhe-metric-select'; 
        Object.entries(METRICAS).forEach(([key, info]) => {
            const opt = document.createElement('option'); opt.value = key; opt.textContent = info.fullLabel; 
            if (key === state.currentMetric) opt.selected = true; selInd.appendChild(opt);
        });
        selInd.onchange = (e) => { state.currentMetric = e.target.value; sortConfig = { key: 'tasa', direction: 'desc' }; onUpdate(); };
        grpInd.appendChild(selInd); wrapper.appendChild(grpInd);

        const validMarkers = Object.keys(state.markerUrls).filter(k => state.markerUrls[k]);
        
        if (validMarkers.length > 0) {
            const grpMarc = document.createElement('div'); grpMarc.className = 'siarhe-control-group'; grpMarc.innerHTML = `<label>Marcadores</label>`;
            const field = document.createElement('div'); field.className = 'mc-field';
            const trigger = document.createElement('div'); trigger.className = 'mc-trigger is-placeholder'; trigger.textContent = 'Seleccionar...';
            state.markerTrigger = trigger; 
            const menu = document.createElement('div'); menu.className = 'mc-menu';
            
            validMarkers.forEach(key => {
                const label = MARCADOR_NOMBRES[key] || key;
                const opt = document.createElement('div'); opt.className = 'mc-option';
                opt.innerHTML = `<input type="checkbox" class="mc-check" value="${key}"> ${label}`;
                opt.addEventListener('click', (e) => {
                    if (e.target.tagName !== 'INPUT') {
                        const chk = opt.querySelector('input'); chk.checked = !chk.checked; toggleMarker(key, state, container);
                    }
                });
                opt.querySelector('input').addEventListener('click', (e) => { e.stopPropagation(); toggleMarker(key, state, container); });
                menu.appendChild(opt);
            });
            trigger.onclick = (e) => { e.stopPropagation(); menu.classList.toggle('open'); };
            document.addEventListener('click', () => menu.classList.remove('open'));
            field.append(trigger, menu); grpMarc.appendChild(field); wrapper.appendChild(grpMarc);
        }
        ph.appendChild(wrapper);
    }

    async function toggleMarker(type, state, container) {
        const loading = container.querySelector('.siarhe-loading-overlay');
        if (loading) {
            loading.querySelector('p').textContent = `Procesando ${MARCADOR_NOMBRES[type]}...`;
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
                        let rawData = state.rawEstabData;

                        if (type.startsWith('ESTAB_') && !rawData) {
                            console.log(`[SIARHE] Descargando base masiva de establecimientos por primera vez...`);
                            rawData = await d3.csv(url);
                            state.rawEstabData = rawData; 
                        } else if (!type.startsWith('ESTAB_')) {
                            rawData = await d3.csv(url);
                        } else {
                            console.log(`[SIARHE] Usando base de establecimientos desde Caché RAM.`);
                        }

                        const nivelTarget = type.replace('ESTAB_', ''); 

                        state.markersData[type] = rawData.map(d => {
                            const latText = getColValue(d, ['latitud', 'lat']);
                            const lonText = getColValue(d, ['longitud', 'lon']);
                            const cveNivel = getColValue(d, ['cve_n_atencion', 'cve_ n_atencion', 'cve n atencion']);
                            
                            return {
                                clues: getColValue(d, ['clues']),
                                institucion: getColValue(d, ['institución', 'institucion']),
                                nombre: getColValue(d, ['nombre_unidad', 'nombre de la unidad', 'unidad']),
                                municipio: getColValue(d, ['municipio']),
                                lat: parseFloat(latText),
                                lon: parseFloat(lonText),
                                tipo_estab: getColValue(d, ['tipo_establecimiento', 'tipo establecimiento']),
                                tipologia: getColValue(d, ['nombre_tipologia', 'nombre tipologia']),
                                nivel_atencion: getColValue(d, ['nivel_atencion', 'nivel atencion']),
                                cve_nivel: cveNivel,
                                jurisdiccion: getColValue(d, ['jurisdiccion', 'jurisdicción']),
                                estrato: getColValue(d, ['estrato_unidad', 'estrato unidad']),
                                tipo: type
                            };
                        }).filter(d => {
                            const isValidCoord = !isNaN(d.lat) && !isNaN(d.lon) && d.lat !== 0 && d.lon !== 0;
                            if (!isValidCoord) return false;
                            
                            if (type.startsWith('ESTAB_')) {
                                return d.cve_nivel === nivelTarget;
                            }
                            return true; 
                        });
                        
                    } catch(e) { console.error(`[SIARHE] Error cargando marcadores ${type}:`, e); }
                }
            }
        }
        
        updateMarkers(state);
        updateMarkerDropdownText(state);

        await new Promise(r => setTimeout(r, 100)); 
        if (loading) loading.style.display = 'none';
    }

    function updateMarkerDropdownText(state) {
        if (!state.markerTrigger) return;
        const selected = Array.from(state.activeMarkers);
        if (selected.length === 0) { state.markerTrigger.textContent = 'Seleccionar...'; state.markerTrigger.classList.add('is-placeholder'); } 
        else {
            const labels = selected.map(k => MARCADOR_NOMBRES[k] || k);
            state.markerTrigger.textContent = labels.join(', '); state.markerTrigger.classList.remove('is-placeholder');
        }
    }

    function updateMarkers(state) {
        let allPoints = [];
        state.activeMarkers.forEach(type => { if(state.markersData[type]) allPoints = allPoints.concat(state.markersData[type]); });

        let currentK = 1; if (state.svg) { try { currentK = d3.zoomTransform(state.svg.node()).k; } catch(e) {} }

        const markers = state.gMarkers.selectAll("path.siarhe-marker").data(allPoints);
        
        markers.exit().remove();
        
        markers.enter().append("path")
            .attr("class", "siarhe-marker")
            .merge(markers)
            .attr("d", d => {
                const styleCfg = getMarkerStyle(d.tipo, state);
                return d3.symbol().type(getD3Shape(styleCfg.shape)).size(150 / (currentK * currentK))();
            })
            .attr("transform", d => {
                const coords = state.projection([d.lon, d.lat]);
                return coords ? `translate(${coords[0]}, ${coords[1]})` : "translate(0,0)";
            })
            .attr("fill", d => getMarkerStyle(d.tipo, state).fill)
            .attr("stroke", d => getMarkerStyle(d.tipo, state).stroke)
            .attr("stroke-width", 1.5 / currentK)
            .style("cursor", "pointer")
            .on("mouseover", function(e, d) {
                d3.select(this).attr("stroke-width", 3 / currentK).raise(); 

                let html = `<div class="tooltip-header" style="color: #06B6D4;">${MARCADOR_NOMBRES[d.tipo] || 'Unidad de Salud'}</div>`;
                html += `<div style="font-size:12px; margin-top:4px;">
                    <div style="font-weight:bold; color:#F8FAFC; font-family:'IBM Plex Sans', sans-serif;">${d.nombre || 'Desconocido'}</div>
                    <div style="color:#A5B4C3; font-size:11px;">${d.institucion} - ${d.municipio}</div>
                    <div style="font-size:10px; margin-top:2px;">CLUES: ${d.clues}</div>`;
                
                if (d.tipo_estab || d.nivel_atencion || d.jurisdiccion) {
                    html += `<div style="margin-top:5px; padding-top:4px; border-top:1px dashed #475569;">`;
                    if(d.tipo_estab) html += `<div style="color:#0A66C2; font-size:11px;"><strong>Tipo:</strong> ${d.tipo_estab}</div>`;
                    if(d.tipologia) html += `<div style="color:#06B6D4; font-size:11px;"><strong>Tipología:</strong> ${d.tipologia}</div>`;
                    if(d.nivel_atencion) html += `<div style="color:#F8FAFC; font-size:11px;"><strong>Nivel:</strong> ${d.nivel_atencion}</div>`;
                    if(d.jurisdiccion) html += `<div style="color:#A5B4C3; font-size:10px;"><strong>Jurisdicción:</strong> ${d.jurisdiccion}</div>`;
                    html += `</div>`;
                }
                html += `</div>`;

                // 🌟 SOLUCIÓN TOOLTIP MARCADORES EN PANTALLA COMPLETA 🌟
                const mapDiv = document.querySelector('.siarhe-map-container');
                const [mx, my] = d3.pointer(e, mapDiv);

                state.tooltip.html(html)
                    .style("display", "block")
                    .style("opacity", 1)
                    .style("left", (mx + 10) + "px")
                    .style("top", (my - 20) + "px");
            })
            .on("mouseout", function(e, d) {
                d3.select(this).attr("stroke-width", 1.5 / currentK); 
                state.tooltip.style("display", "none");
            });

        renderMarkerLegend(state);
    }

    function renderMarkerLegend(state) {
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
            const styleCfg = getMarkerStyle(tipo, state);
            const nombre = MARCADOR_NOMBRES[tipo] || tipo;

            g.append("path")
                .attr("d", d3.symbol().type(getD3Shape(styleCfg.shape)).size(50)())
                .attr("transform", `translate(5, ${yPos})`)
                .attr("fill", styleCfg.fill)
                .attr("stroke", styleCfg.stroke)
                .attr("stroke-width", 1);
            
            g.append("text").attr("x", 15).attr("y", yPos + 4).text(nombre).style("font-size", "11px").style("fill", "#475569");
        });
    }

    function handleMapClick(d, state) {
        let cve = getGeoKey(d.properties);
        if (typeof siarheData !== 'undefined' && siarheData.entity_urls && siarheData.entity_urls[cve]) {
            window.location.href = siarheData.entity_urls[cve];
        }
    }

    function renderZoomButtons(mapDiv, svg, zoom, cveEnt) {
        const ctrlDiv = document.createElement('div'); ctrlDiv.className = 'zoom-controles'; mapDiv.appendChild(ctrlDiv);
        const createBtn = (l, t, cb) => { const b = document.createElement('button'); b.className = 'boton'; b.innerHTML = l; b.title = t; b.onclick = cb; ctrlDiv.appendChild(b); };
        
        createBtn('+', 'Acercar', (e) => { e.preventDefault(); svg.transition().call(zoom.scaleBy, 1.5); });
        createBtn('–', 'Alejar', (e) => { e.preventDefault(); svg.transition().call(zoom.scaleBy, 0.6); });
        createBtn('⟳', 'Reset', (e) => { e.preventDefault(); svg.transition().call(zoom.transform, d3.zoomIdentity); });
        
        const btnFullscreen = document.createElement('button');
        btnFullscreen.className = 'boton';
        btnFullscreen.innerHTML = '⛶'; 
        btnFullscreen.title = 'Pantalla Completa';
        
        const mapContainer = mapDiv; // La caja que vamos a expandir es mapDiv
        
        btnFullscreen.onclick = (e) => {
            e.preventDefault();
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                if (mapContainer.requestFullscreen) { mapContainer.requestFullscreen(); } 
                else if (mapContainer.webkitRequestFullscreen) { mapContainer.webkitRequestFullscreen(); } 
                else if (mapContainer.msRequestFullscreen) { mapContainer.msRequestFullscreen(); }
                btnFullscreen.innerHTML = '🗗'; 
                mapContainer.classList.add('is-fullscreen');
            } else {
                if (document.exitFullscreen) { document.exitFullscreen(); } 
                else if (document.webkitExitFullscreen) { document.webkitExitFullscreen(); }
                else if (document.msExitFullscreen) { document.msExitFullscreen(); }
                btnFullscreen.innerHTML = '⛶';
                mapContainer.classList.remove('is-fullscreen');
            }
        };

        // Si el usuario sale con la tecla ESC, actualizamos el botón
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) {
                btnFullscreen.innerHTML = '⛶';
                mapContainer.classList.remove('is-fullscreen');
            }
        });

        ctrlDiv.appendChild(btnFullscreen);

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
        const tasaDataKey = isPob ? null : (METRICAS[mKey].tipo === 'tasa' ? mKey : pKey.replace('enfermeras_', 'tasa_'));

        const isNacional = (container.dataset.cveEnt === '33' || container.dataset.slug === 'republica-mexicana');
        const labelEntidad = isNacional ? 'Entidad Federativa' : 'Municipio';

        const cols = [
            { id: 'estado', label: labelEntidad, isNum: false, align: 'left' },
            { id: 'poblacion', label: 'Población', isNum: true, align: 'right' },
            { id: 'enfermeras', label: 'Enfermeras', isNum: true, dataKey: enfDataKey, dash: isPob, align: 'right' },
            { id: 'tasa', label: 'Tasa', isNum: true, dataKey: tasaDataKey, dash: isPob, align: 'right' }
        ];

        thead.innerHTML = ''; const tr = document.createElement('tr');
        cols.forEach(c => {
            const th = document.createElement('th');
            th.style.textAlign = c.align;
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
                td.style.textAlign = c.align; 
                if (c.id === 'estado') { td.innerHTML = !r.isTotal ? `<strong>${r.estado}</strong>` : r.estado; } 
                else if (c.dash) { td.textContent = '—'; } 
                else {
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