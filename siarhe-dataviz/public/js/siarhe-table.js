/**
 * SIARHE DataViz - Módulo de Tabla (Table)
 * ------------------------------------------------------------------
 * Gestiona la creación, actualización y ordenamiento interactivo
 * de la tabla de datos estadísticos. Recibe los datos procesados del
 * Core y los renderiza manejando nulos de forma segura.
 */

window.SiarheDataViz = window.SiarheDataViz || {};

(function(app) {
    'use strict';

    app.table = {

        // ==========================================
        // 1. CONSTRUCCIÓN DE LA ESTRUCTURA BASE
        // ==========================================
        
        init: function(container, state) {
            const div = container.querySelector('.siarhe-table-container');
            if (!div) return;
            div.innerHTML = '';
            
            const table = document.createElement('table'); 
            table.className = 'siarhe-data-table';
            
            // Guardamos las referencias en el estado para no buscar en el DOM a cada rato
            state.tableElements = { 
                table: table, 
                thead: table.createTHead(), 
                tbody: table.createTBody() 
            };
            
            div.appendChild(table);
            
            // Primer renderizado
            app.table.update(container, state);
        },

        // ==========================================
        // 2. ACTUALIZACIÓN DINÁMICA DE DATOS Y ORDENAMIENTO
        // ==========================================

        update: function(container, state) {
            if (!state.csvData || state.csvData.length === 0) return;
            const { thead, tbody } = state.tableElements;
            
            const mKey = state.currentMetric;
            
            // Verificación de seguridad por si la métrica fue eliminada
            if (!state.metricas[mKey]) return;

            const pKey = state.metricas[mKey].pair || mKey;
            const isPob = (mKey === 'poblacion');
            
            const enfDataKey = isPob ? null : pKey; 
            
            // Búsqueda dinámica de la columna Tasa
            let tasaDataKey = null;
            if (!isPob) {
                tasaDataKey = (state.metricas[mKey].tipo === 'tasa') ? mKey : app.utils.findRateKeyForAbsolute(pKey, state.metricas);
            }

            const labelEntidad = state.isNacional ? 'Entidad Federativa' : 'Municipio';

            const cols = [
                { id: 'estado', label: labelEntidad, isNum: false, align: 'left' },
                { id: 'poblacion', label: 'Población', isNum: true, align: 'right' },
                { id: 'enfermeras', label: 'Enfermeras', isNum: true, dataKey: enfDataKey, dash: isPob, align: 'right' },
                { id: 'tasa', label: 'Tasa', isNum: true, dataKey: tasaDataKey, dash: isPob, align: 'right' }
            ];

            // Renderizado de las Cabeceras (Th)
            thead.innerHTML = ''; 
            const tr = document.createElement('tr');
            cols.forEach(c => {
                const th = document.createElement('th');
                th.style.textAlign = c.align;
                
                let arrow = '↕';
                if (app.sortConfig.key === c.id) {
                    arrow = app.sortConfig.direction === 'asc' ? '▲' : '▼';
                }
                
                th.innerHTML = `${c.label} <small>${arrow}</small>`;
                
                // Evento de ordenamiento
                th.onclick = () => {
                    if (app.sortConfig.key === c.id) {
                        app.sortConfig.direction = app.sortConfig.direction === 'asc' ? 'desc' : 'asc';
                    } else { 
                        app.sortConfig.key = c.id; 
                        app.sortConfig.direction = c.isNum ? 'desc' : 'asc'; 
                    }
                    app.table.update(container, state);
                };
                tr.appendChild(th);
            });
            thead.appendChild(tr);

            // Helper para obtener el valor correcto según la columna elegida para ordenar
            const getVal = (r, colId) => {
                if (colId === 'estado') return r.estado;
                if (colId === 'poblacion') return r.poblacion;
                if (colId === 'enfermeras') return isPob ? null : r[enfDataKey];
                if (colId === 'tasa') return isPob ? null : r[tasaDataKey];
                return null;
            };

            // Filtrado y Ordenamiento
            const rows = state.csvData.filter(r => !r.isTotal && !r.isSpecial).sort((a,b) => {
                const va = getVal(a, app.sortConfig.key); 
                const vb = getVal(b, app.sortConfig.key);
                
                // Tratamiento de los Nulos para evitar que rompan el ordenamiento (siempre van al final)
                if (va === null && vb !== null) return 1; 
                if (vb === null && va !== null) return -1;
                if (va === null && vb === null) return 0;
                
                if (va < vb) return app.sortConfig.direction === 'asc' ? -1 : 1;
                if (va > vb) return app.sortConfig.direction === 'asc' ? 1 : -1;
                return 0;
            });
            
            // Elementos fijos que siempre van al final (Totales / No Disponibles)
            const fixed = state.csvData.filter(r => r.isTotal || r.isSpecial);

            // Renderizado del Cuerpo (Tbody)
            tbody.innerHTML = '';
            [...rows, ...fixed].forEach(r => {
                const row = document.createElement('tr');
                if(r.isTotal) row.className = 'siarhe-row-total';
                
                cols.forEach(c => {
                    const td = document.createElement('td');
                    td.style.textAlign = c.align; 
                    
                    if (c.id === 'estado') { 
                        td.innerHTML = !r.isTotal ? `<strong>${r.estado}</strong>` : r.estado; 
                    } 
                    else if (c.dash) { 
                        td.textContent = '—'; 
                    } 
                    else {
                        const v = (c.id === 'poblacion') ? r.poblacion : r[c.dataKey];
                        
                        // 🌟 Si el valor es null (celda vacía), pintar el guion elegante
                        if (v === null || v === undefined) {
                            td.textContent = '—';
                        } else {
                            if (c.id === 'tasa') td.textContent = v.toFixed(2);
                            else td.textContent = v.toLocaleString('es-MX');
                        }
                    }
                    row.appendChild(td);
                });
                tbody.appendChild(row);
            });
        }
    };

})(window.SiarheDataViz);