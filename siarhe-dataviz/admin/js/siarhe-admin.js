/* /admin/js/siarhe-admin.js */
/**
 * SIARHE DataViz - Scripts Globales de Administración
 * ------------------------------------------------------------------
 * Contiene funciones de utilidad centralizadas y la inicialización de
 * componentes de WordPress utilizados a través de las pestañas del panel.
 */

window.SiarheAdmin = {
    
    /**
     * Formatea cadenas de fecha MySQL a un formato legible y localizado.
     * Ejemplo: "2024-03-12 14:30:00" -> "12 mar 2024, 02:30 p.m."
     * @param {string} dateStr - Cadena de fecha y hora en formato MySQL.
     * @returns {string} Cadena formateada.
     */
    formatDate: function(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr.replace(' ', 'T')); 
        if (isNaN(d.getTime())) return dateStr;
        
        const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        let horas = d.getHours();
        let minutos = d.getMinutes().toString().padStart(2, '0');
        let ampm = horas >= 12 ? 'p.m.' : 'a.m.';
        horas = horas % 12; 
        horas = horas ? horas : 12; 

        return `${d.getDate()} ${meses[d.getMonth()]} ${d.getFullYear()}, ${horas}:${minutos} ${ampm}`;
    },

    /**
     * Inicializa la lógica de acordeón para las tablas estándar en dispositivos móviles.
     * Se aplica únicamente en resoluciones menores a 768px.
     */
    initMobileTables: function() {
        document.querySelectorAll('.siarhe-data-row, .siarhe-table tbody tr').forEach(row => {
            row.removeEventListener('click', window.SiarheAdmin._handleRowClick);
            row.addEventListener('click', window.SiarheAdmin._handleRowClick);
        });
    },

    /**
     * Manejador interno de eventos para la expansión de filas en vista móvil.
     * Previene la expansión si el objetivo del clic es un elemento interactivo.
     * @private
     */
    _handleRowClick: function(e) {
        if (window.innerWidth > 767) return; 
        
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input') || e.target.closest('select') || e.target.closest('.select2')) {
            return;
        }
        
        this.classList.toggle('is-open');
    },

    /**
     * Aviso emergente no bloqueante (reemplaza alert()).
     * @param {string} msg
     * @param {'info'|'success'|'warning'|'error'} type
     */
    toast: function(msg, type) {
        let box = document.querySelector('.siarhe-toasts');
        if (!box) {
            box = document.createElement('div');
            box.className = 'siarhe-toasts';
            box.setAttribute('role', 'status');
            box.setAttribute('aria-live', 'polite');
            document.body.appendChild(box);
        }
        const t = document.createElement('div');
        t.className = 'siarhe-toast siarhe-toast--' + (type || 'info');
        t.textContent = msg;
        box.appendChild(t);
        setTimeout(() => { t.remove(); }, type === 'error' ? 7000 : 4000);
    },

    /**
     * Modal de confirmación (reemplaza confirm()). Devuelve una Promise<boolean>.
     * @param {string} msg
     * @param {{title?:string, okText?:string, cancelText?:string, danger?:boolean}} opts
     */
    confirm: function(msg, opts) {
        opts = opts || {};
        return new Promise(resolve => {
            const prev = document.activeElement;
            const ov = document.createElement('div');
            ov.className = 'siarhe-modal-overlay is-open';
            const box = document.createElement('div');
            box.className = 'siarhe-modal-content siarhe-confirm';
            box.setAttribute('role', 'alertdialog');
            box.setAttribute('aria-modal', 'true');
            const h = document.createElement('h2');
            h.className = 'siarhe-confirm__title';
            h.id = 'siarhe-confirm-title';
            h.textContent = opts.title || 'Confirmar acción';
            box.setAttribute('aria-labelledby', h.id);
            const p = document.createElement('p');
            p.className = 'siarhe-confirm__body';
            p.textContent = msg;
            const foot = document.createElement('div');
            foot.className = 'siarhe-modal-footer';
            const no = document.createElement('button');
            no.type = 'button'; no.className = 'button'; no.textContent = opts.cancelText || 'Cancelar';
            const ok = document.createElement('button');
            ok.type = 'button';
            ok.className = 'button ' + (opts.danger === false ? 'button-primary' : 'button-danger-solid');
            ok.textContent = opts.okText || 'Aceptar';
            if (opts.cancelText === null) foot.append(ok); else foot.append(no, ok);
            box.append(h, p, foot);
            ov.appendChild(box);
            document.body.appendChild(ov);
            ok.focus();

            const close = val => {
                document.removeEventListener('keydown', onKey, true);
                ov.remove();
                if (prev && prev.focus) prev.focus();
                resolve(val);
            };
            const onKey = e => {
                if (e.key === 'Escape') { e.preventDefault(); close(false); }
                else if (e.key === 'Tab') {
                    e.preventDefault();
                    if (opts.cancelText !== null) (document.activeElement === ok ? no : ok).focus();
                }
            };
            document.addEventListener('keydown', onKey, true);
            no.addEventListener('click', () => close(false));
            ok.addEventListener('click', () => close(true));
            ov.addEventListener('click', e => { if (e.target === ov) close(false); });
        });
    },

    /**
     * Alerta modal (un solo boton). Para errores que el usuario no debe pasar por alto,
     * por ejemplo un archivo que no se pudo subir.
     */
    alert: function(msg, title) {
        return window.SiarheAdmin.confirm(msg, { title: title || 'Atención', okText: 'Entendido', cancelText: null, danger: false });
    },

    /**
     * Copia texto al portapapeles y marca el botón como copiado un instante.
     */
    copy: function(text, btn) {
        const done = () => {
            if (btn) {
                btn.classList.add('is-copied');
                setTimeout(() => btn.classList.remove('is-copied'), 1500);
            }
            window.SiarheAdmin.toast('Copiado al portapapeles', 'success');
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, () => window.SiarheAdmin.toast('No se pudo copiar', 'error'));
        } else {
            const ta = document.createElement('textarea');
            ta.value = text; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); } catch (e) { window.SiarheAdmin.toast('No se pudo copiar', 'error'); }
            ta.remove();
        }
    },

    /**
     * Globo de ayuda para los iconos "?" (.siarhe-help[data-help]).
     * Se muestra al pasar el cursor, al enfocar o al hacer clic/tocar.
     */
    initHelp: function() {
        let tip = null, owner = null;
        const hide = () => { if (tip) { tip.remove(); tip = null; } if (owner) { owner.removeAttribute('aria-describedby'); owner = null; } };
        const show = (btn) => {
            if (owner === btn) return;
            hide();
            owner = btn;
            tip = document.createElement('div');
            tip.className = 'siarhe-help-tip';
            tip.id = 'siarhe-help-tip';
            tip.setAttribute('role', 'tooltip');
            tip.textContent = btn.getAttribute('data-help');
            document.body.appendChild(tip);
            btn.setAttribute('aria-describedby', tip.id);
            const r = btn.getBoundingClientRect();
            const w = tip.offsetWidth, h = tip.offsetHeight;
            let left = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - w - 8);
            let top = r.bottom + 8;
            if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 8);
            tip.style.left = left + 'px';
            tip.style.top = top + 'px';
        };
        const isHelp = e => e.target.closest ? e.target.closest('[data-help]') : null;
        document.addEventListener('mouseover', e => { const b = isHelp(e); if (b) show(b); });
        document.addEventListener('mouseout', e => { if (isHelp(e) && document.activeElement !== isHelp(e)) hide(); });
        document.addEventListener('focusin', e => { const b = isHelp(e); if (b) show(b); });
        document.addEventListener('focusout', e => { if (isHelp(e)) hide(); });
        document.addEventListener('click', e => {
            const b = isHelp(e);
            // Solo los botones "?" alternan el globo al hacer clic; los botones de accion
            // con data-help (editar, eliminar, restaurar...) conservan su comportamiento
            if (b && b.classList.contains('siarhe-help')) { e.preventDefault(); e.stopPropagation(); owner === b ? hide() : show(b); }
            else hide();
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') hide(); });
        window.addEventListener('scroll', hide, true);
    },

    /**
     * Comprobacion previa de archivos antes de subirlos. Los inputs file con
     * data-check ("marker-csv", "static-csv", "geojson") se validan al elegir el
     * archivo; si no cumple, se avisa con un popup y el formulario no se envia.
     * (El servidor vuelve a validar: esto solo ahorra el viaje de ida y vuelta.)
     */
    initUploadChecks: function() {
        const csvHeader = (text) => {
            const first = text.replace(/^\uFEFF/, '').split(/\r?\n/, 1)[0] || '';
            const cols = []; let cur = '', q = false;
            for (const ch of first) {
                if (ch === '"') q = !q;
                else if (ch === ',' && !q) { cols.push(cur.trim()); cur = ''; }
                else cur += ch;
            }
            cols.push(cur.trim());
            return { first, cols };
        };
        const csvBasics = (text, truncated) => {
            const { first, cols } = csvHeader(text);
            if (!first.trim()) return { error: 'El CSV está vacío.' };
            if (!first.includes(',') && (first.includes(';') || first.includes('\t'))) {
                return { error: 'El CSV parece usar punto y coma o tabulador como separador. Guárdalo con comas (CSV UTF-8).' };
            }
            const rest = text.split(/\r?\n/).slice(1).filter(l => l.trim() !== '');
            if (rest.length === 0 && !truncated) return { error: 'El CSV solo tiene encabezado: no hay filas de datos.' };
            return { cols };
        };
        const checks = {
            'marker-csv': (text, truncated) => {
                const b = csvBasics(text, truncated); if (b.error) return b.error;
                const h = b.cols.map(c => c.toLowerCase());
                if (!h.some(c => c === 'latitud' || c === 'lat') || !h.some(c => c === 'longitud' || c === 'lon')) {
                    return 'El CSV de marcadores necesita las columnas de coordenadas: Latitud/Longitud (o lat/lon).';
                }
                return '';
            },
            'static-csv': (text, truncated, form) => {
                const b = csvBasics(text, truncated); if (b.error) return b.error;
                const sel = form.querySelector('[name="entidad_slug"]');
                const national = sel && sel.value === 'republica-mexicana';
                const ids = national ? ['CVE_ENT', 'cve_ent', 'mapa', 'id'] : ['CVE_MUN', 'cve_mun', 'CVE_ENT', 'cve_ent', 'id'];
                const missing = [];
                if (!ids.some(n => b.cols.includes(n))) missing.push('identificador (' + ids.join(', ') + ')');
                if (!['estado', 'Entidad', 'municipio'].some(n => b.cols.includes(n))) missing.push('nombre (estado, Entidad, municipio)');
                if (!b.cols.some(c => c.toLowerCase().startsWith('pob'))) missing.push('población (una columna que empiece con "pob")');
                return missing.length ? 'El CSV no tiene las columnas requeridas: ' + missing.join('; ') + '.' : '';
            },
            'geojson': (text) => {
                let data;
                try { data = JSON.parse(text); } catch (e) { return 'El archivo no es un JSON válido.'; }
                if (!data || data.type !== 'FeatureCollection' || !Array.isArray(data.features) || data.features.length === 0) {
                    return 'El GeoJSON debe ser un FeatureCollection con al menos un elemento en "features".';
                }
                return '';
            }
        };
        const MAX_CSV_READ = 256 * 1024;
        const MAX_GEOJSON_READ = 40 * 1024 * 1024;

        document.querySelectorAll('input[type="file"][data-check]').forEach(input => {
            const form = input.form;
            const kind = input.getAttribute('data-check');
            if (!form || !checks[kind]) return;
            let error = '';

            const note = document.createElement('p');
            note.className = 'description siarhe-field-error';
            note.hidden = true;
            input.insertAdjacentElement('afterend', note);

            const run = () => {
                error = ''; note.hidden = true;
                const file = input.files && input.files[0];
                if (!file) return Promise.resolve();
                const limit = kind === 'geojson' ? MAX_GEOJSON_READ : MAX_CSV_READ;
                if (kind === 'geojson' && file.size > limit) return Promise.resolve(); // demasiado grande: valida el servidor
                const truncated = file.size > limit;
                return file.slice(0, limit).text().then(text => {
                    error = checks[kind](text, truncated, form);
                    if (error) {
                        note.textContent = error; note.hidden = false;
                        window.SiarheAdmin.alert(error, 'No se puede subir este archivo');
                    }
                }).catch(() => {});
            };
            input.addEventListener('change', run);
            const sel = form.querySelector('[name="entidad_slug"]');
            if (sel && kind === 'static-csv') {
                sel.addEventListener('change', run);
                if (window.jQuery) jQuery(sel).on('change', run); // Select2 dispara eventos jQuery
            }
            form.addEventListener('submit', e => {
                if (error) {
                    e.preventDefault(); e.stopImmediatePropagation();
                    window.SiarheAdmin.alert(error, 'No se puede subir este archivo');
                }
            }, true);
        });
    },

    /**
     * Busqueda, filtros y paginacion de una tabla (se ocultan/muestran las filas del DOM).
     * cfg: {
     *   rows:    filas de datos (array o NodeList),
     *   search:  <input> de busqueda (opcional),
     *   perPage: <select> con 25/50/100/all (opcional; si falta se usa defaultPerPage),
     *   filters: [{ el: <select>, match: (fila, valor) => bool }] (opcionales; valor vacio = sin filtro),
     *   pagers:  [elementos donde dibujar los numeros de pagina] (opcional),
     *   count:   elemento para "Mostrando del X al Y de Z" (opcional),
     *   empty:   fila "sin resultados" (opcional),
     *   table:   <table> cuyos <th data-sort="text|num"> permiten ordenar (opcional),
     *   defaultPerPage: 25 | 'all'
     * }
     */
    initTable: function(cfg) {
        const rows = Array.from(cfg.rows || []);
        const pagers = (cfg.pagers || []).filter(Boolean);
        const norm = (t) => t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        const parsePer = (v) => v === 'all' ? 'all' : (parseInt(v, 10) || 25);

        let page = 1;
        let per = cfg.perPage ? parsePer(cfg.perPage.value) : (cfg.defaultPerPage || 25);
        let matched = rows.slice();

        const pagerHtml = (totalPages) => {
            if (totalPages <= 1) return '';
            let start = Math.max(1, page - 2), end = Math.min(totalPages, page + 2);
            if (page <= 2) end = Math.min(totalPages, 5);
            if (page >= totalPages - 1) start = Math.max(1, totalPages - 4);
            let h = '<a href="#" class="siarhe-page-btn' + (page === 1 ? ' disabled' : '') + '" data-page="prev">« Ant</a>';
            if (start > 1) {
                h += '<a href="#" class="siarhe-page-btn" data-page="1">1</a>';
                if (start > 2) h += '<span class="siarhe-page-gap">...</span>';
            }
            for (let i = start; i <= end; i++) {
                h += '<a href="#" class="siarhe-page-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</a>';
            }
            if (end < totalPages) {
                if (end < totalPages - 1) h += '<span class="siarhe-page-gap">...</span>';
                h += '<a href="#" class="siarhe-page-btn" data-page="' + totalPages + '">' + totalPages + '</a>';
            }
            return h + '<a href="#" class="siarhe-page-btn' + (page === totalPages ? ' disabled' : '') + '" data-page="next">Sig »</a>';
        };

        const render = () => {
            const total = matched.length;
            const totalPages = per === 'all' ? 1 : (Math.ceil(total / per) || 1);
            if (page > totalPages) page = totalPages;

            rows.forEach(r => { r.style.display = 'none'; });
            if (cfg.empty) cfg.empty.style.display = total === 0 ? '' : 'none';

            const start = per === 'all' ? 0 : (page - 1) * per;
            const slice = per === 'all' ? matched : matched.slice(start, start + per);
            slice.forEach(r => { r.style.display = ''; });

            if (cfg.count) {
                cfg.count.innerHTML = total === 0
                    ? 'No hay registros para mostrar.'
                    : 'Mostrando del <strong>' + (start + 1) + '</strong> al <strong>' + (start + slice.length) + '</strong> de <strong>' + total + '</strong> registros';
            }
            const html = total === 0 ? '' : pagerHtml(totalPages);
            pagers.forEach(el => { el.innerHTML = html; });
        };

        const apply = () => {
            const term = cfg.search ? norm(cfg.search.value) : '';
            matched = rows.filter(row => {
                if (term && !norm(row.textContent).includes(term)) return false;
                return (cfg.filters || []).every(f => !f.el.value || f.match(row, f.el.value));
            });
            page = 1;
            render();
        };

        pagers.forEach(el => el.addEventListener('click', e => {
            const a = e.target.closest ? e.target.closest('.siarhe-page-btn') : null;
            if (!a) return;
            e.preventDefault();
            if (a.classList.contains('disabled')) return;
            const t = a.getAttribute('data-page');
            const totalPages = per === 'all' ? 1 : (Math.ceil(matched.length / per) || 1);
            if (t === 'prev') page = Math.max(1, page - 1);
            else if (t === 'next') page = Math.min(totalPages, page + 1);
            else page = parseInt(t, 10) || 1;
            render();
        }));

        // Ordenamiento: los <th data-sort="text|num"> de cfg.table se vuelven botones.
        // El valor de cada celda es su data-sort-value o, si no existe, su texto.
        if (cfg.table) {
            const ths = Array.from(cfg.table.querySelectorAll('thead th'));
            const cellVal = (row, i, type) => {
                const td = row.children[i];
                if (!td) return type === 'num' ? -Infinity : '';
                const raw = td.hasAttribute('data-sort-value') ? td.getAttribute('data-sort-value') : td.textContent.trim();
                if (type === 'num') { const n = parseFloat(String(raw).replace(/[^0-9.\-]/g, '')); return isNaN(n) ? -Infinity : n; }
                return norm(raw);
            };
            ths.forEach((th, i) => {
                const type = th.getAttribute('data-sort');
                if (!type) return;
                th.classList.add('siarhe-sortable');
                th.setAttribute('tabindex', '0');
                th.setAttribute('role', 'columnheader');
                th.setAttribute('aria-sort', 'none');
                const sortBy = () => {
                    const dir = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
                    ths.forEach(o => { if (o.hasAttribute('data-sort')) o.setAttribute('aria-sort', 'none'); });
                    th.setAttribute('aria-sort', dir);
                    const k = dir === 'ascending' ? 1 : -1;
                    rows.sort((a, b) => {
                        const x = cellVal(a, i, type), y = cellVal(b, i, type);
                        return (type === 'num' ? x - y : x.localeCompare(y, 'es')) * k;
                    });
                    // Reordenar en el DOM, dejando la fila "sin resultados" al final
                    const parent = rows[0] && rows[0].parentNode;
                    if (parent) rows.forEach(r => parent.insertBefore(r, cfg.empty && cfg.empty.parentNode === parent ? cfg.empty : null));
                    apply();
                };
                th.addEventListener('click', sortBy);
                th.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sortBy(); } });
            });
        }

        if (cfg.search) cfg.search.addEventListener('input', apply);
        (cfg.filters || []).forEach(f => f.el.addEventListener('change', apply));
        if (cfg.perPage) cfg.perPage.addEventListener('change', () => { per = parsePer(cfg.perPage.value); page = 1; render(); });

        apply();
        return { refresh: apply };
    },

    /**
     * Cabeceras ordenables para tablas que gestionan su propio filtrado (Configuracion).
     * Los <th data-sort-key="x" data-sort="text|num"> se vuelven botones; al cambiar el orden
     * se llama a onChange. sorter.apply(lista, (item, clave) => valor) devuelve la lista ordenada.
     */
    initSortHeaders: function(table, onChange) {
        const state = { key: null, dir: 1, type: 'text' };
        const ths = table ? Array.from(table.querySelectorAll('thead th[data-sort-key]')) : [];
        const norm = (t) => String(t == null ? '' : t).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        ths.forEach(th => {
            th.classList.add('siarhe-sortable');
            th.setAttribute('tabindex', '0');
            th.setAttribute('aria-sort', 'none');
            const go = () => {
                state.dir = (state.key === th.getAttribute('data-sort-key') && state.dir === 1) ? -1 : 1;
                state.key = th.getAttribute('data-sort-key');
                state.type = th.getAttribute('data-sort') || 'text';
                ths.forEach(o => o.setAttribute('aria-sort', 'none'));
                th.setAttribute('aria-sort', state.dir === 1 ? 'ascending' : 'descending');
                onChange();
            };
            th.addEventListener('click', go);
            th.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
        });
        return {
            apply: (list, valueOf) => {
                if (state.key === null) return list;
                const k = state.dir;
                const num = state.type === 'num';
                return list.slice().sort((a, b) => {
                    const x = valueOf(a, state.key), y = valueOf(b, state.key);
                    return num ? ((parseFloat(x) || 0) - (parseFloat(y) || 0)) * k : norm(x).localeCompare(norm(y), 'es') * k;
                });
            }
        };
    },

    /**
     * Mejora de los formularios de carga (inputs file con data-check):
     *  - zona para arrastrar y soltar el archivo;
     *  - vista previa (primeras filas del CSV o resumen del GeoJSON);
     *  - barra de progreso real de la subida (XMLHttpRequest).
     * Sin JS el formulario sigue funcionando como un envio normal.
     */
    initUploadEnhance: function() {
        const fmtSize = (n) => n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
        const MAX_PREVIEW_GEOJSON = 8 * 1024 * 1024;

        // Fila CSV con comillas (solo para la vista previa)
        const csvRow = (line) => {
            const out = []; let cur = '', q = false;
            for (let i = 0; i < line.length; i++) {
                const ch = line[i];
                if (q) {
                    if (ch === '"' && line[i + 1] === '"') { cur += '"'; i++; }
                    else if (ch === '"') q = false;
                    else cur += ch;
                } else if (ch === '"') q = true;
                else if (ch === ',') { out.push(cur.trim()); cur = ''; }
                else cur += ch;
            }
            out.push(cur.trim());
            return out;
        };

        const el = (tag, cls, text) => {
            const n = document.createElement(tag);
            if (cls) n.className = cls;
            if (text !== undefined) n.textContent = text;
            return n;
        };

        const previewCsv = (text) => {
            const lines = text.replace(/^\uFEFF/, '').split(/\r?\n/).filter(l => l.trim() !== '').slice(0, 6);
            if (!lines.length) return null;
            const rows = lines.map(csvRow);
            const MAX_COLS = 8;
            const wrap = el('div', 'siarhe-preview-scroll');
            const table = el('table', 'siarhe-preview-table');
            rows.forEach((r, i) => {
                const tr = el('tr');
                r.slice(0, MAX_COLS).forEach(c => tr.appendChild(el(i === 0 ? 'th' : 'td', '', c)));
                if (r.length > MAX_COLS) tr.appendChild(el(i === 0 ? 'th' : 'td', '', '+' + (r.length - MAX_COLS) + ' más'));
                (i === 0 ? table.createTHead() : table.tBodies[0] || table.createTBody()).appendChild(tr);
            });
            wrap.appendChild(table);
            const frag = document.createDocumentFragment();
            frag.appendChild(el('p', 'siarhe-preview-meta', 'Columnas: ' + rows[0].length + ' · Primeras ' + (rows.length - 1) + ' filas de datos'));
            frag.appendChild(wrap);
            return frag;
        };

        const previewGeo = (text) => {
            let data;
            try { data = JSON.parse(text); } catch (e) { return null; }
            if (!data || !Array.isArray(data.features)) return null;
            const types = {};
            data.features.forEach(f => { const t = f && f.geometry && f.geometry.type; if (t) types[t] = (types[t] || 0) + 1; });
            const props = data.features[0] && data.features[0].properties ? Object.keys(data.features[0].properties) : [];
            const frag = document.createDocumentFragment();
            frag.appendChild(el('p', 'siarhe-preview-meta',
                'Elementos: ' + data.features.length.toLocaleString('es-MX') + ' · Geometrías: ' +
                (Object.keys(types).map(t => t + ' (' + types[t] + ')').join(', ') || '—')));
            if (props.length) frag.appendChild(el('p', 'siarhe-preview-meta', 'Campos: ' + props.slice(0, 12).join(', ') + (props.length > 12 ? ' …' : '')));
            return frag;
        };

        document.querySelectorAll('input[type="file"][data-check]').forEach(input => {
            const form = input.form;
            if (!form || input.closest('.siarhe-dropzone')) return;
            const kind = input.getAttribute('data-check');

            // --- zona de arrastre ---
            const zone = el('div', 'siarhe-dropzone');
            input.parentNode.insertBefore(zone, input);
            zone.appendChild(el('span', 'dashicons dashicons-upload'));
            zone.appendChild(el('p', 'siarhe-dropzone__hint', 'Arrastra aquí el archivo o elígelo desde tu equipo'));
            zone.appendChild(input);

            const preview = el('div', 'siarhe-upload-preview');
            preview.hidden = true;
            zone.insertAdjacentElement('afterend', preview);

            const allowed = (input.getAttribute('accept') || '').split(',').map(x => x.trim().toLowerCase()).filter(Boolean);
            const extOk = (name) => !allowed.length || allowed.some(ext => name.toLowerCase().endsWith(ext));

            ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('is-over'); }));
            ['dragleave', 'dragend'].forEach(ev => zone.addEventListener(ev, () => zone.classList.remove('is-over')));
            zone.addEventListener('drop', e => {
                e.preventDefault(); zone.classList.remove('is-over');
                const files = e.dataTransfer && e.dataTransfer.files;
                if (!files || !files.length) return;
                if (files.length > 1) { window.SiarheAdmin.alert('Arrastra un solo archivo a la vez.', 'No se puede subir este archivo'); return; }
                if (!extOk(files[0].name)) { window.SiarheAdmin.alert('Este formulario solo acepta archivos ' + allowed.join(' o ') + '.', 'No se puede subir este archivo'); return; }
                input.files = files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            // --- vista previa ---
            const renderPreview = () => {
                preview.textContent = ''; preview.hidden = true;
                const file = input.files && input.files[0];
                if (!file) return;
                preview.appendChild(el('p', 'siarhe-preview-file', file.name + ' · ' + fmtSize(file.size)));
                preview.hidden = false;
                const isGeo = kind === 'geojson';
                if (isGeo && file.size > MAX_PREVIEW_GEOJSON) return;
                file.slice(0, isGeo ? MAX_PREVIEW_GEOJSON : 64 * 1024).text().then(text => {
                    if (input.files[0] !== file) return; // cambio de archivo mientras se leia
                    const node = isGeo ? previewGeo(text) : previewCsv(text);
                    if (node) preview.appendChild(node);
                }).catch(() => {});
            };
            input.addEventListener('change', renderPreview);

            // --- envio con progreso ---
            if (!window.XMLHttpRequest || !window.FormData) return;
            const bar = el('div', 'siarhe-progress');
            bar.hidden = true;
            const track = el('div', 'siarhe-progress__track');
            const fill = el('div', 'siarhe-progress__fill');
            track.appendChild(fill);
            const label = el('span', 'siarhe-progress__label', '');
            bar.append(track, label);
            const submitBtn = form.querySelector('[type="submit"]');
            (submitBtn ? submitBtn.closest('p, div') || form : form).appendChild(bar);

            const fail = (msg) => {
                bar.hidden = true; fill.style.width = '0%';
                if (submitBtn) submitBtn.disabled = false;
                window.SiarheAdmin.alert(msg, 'No se subió el archivo');
            };

            form.addEventListener('submit', e => {
                if (e.defaultPrevented) return;
                e.preventDefault();
                const data = new FormData(form);
                data.append('siarhe_xhr', '1');
                const xhr = new XMLHttpRequest();
                xhr.open('POST', form.getAttribute('action')); // form.action devolveria el input oculto name="action"
                bar.hidden = false; fill.style.width = '0%'; label.textContent = 'Subiendo… 0 %';
                if (submitBtn) submitBtn.disabled = true;

                xhr.upload.addEventListener('progress', ev => {
                    if (!ev.lengthComputable) return;
                    const pct = Math.round(ev.loaded / ev.total * 100);
                    fill.style.width = pct + '%';
                    label.textContent = pct < 100 ? 'Subiendo… ' + pct + ' %' : 'Procesando en el servidor…';
                });
                xhr.addEventListener('load', () => {
                    let res = null;
                    try { res = JSON.parse(xhr.responseText); } catch (err) {}
                    if (res && res.redirect) { window.location.href = res.redirect; return; }
                    fail('El servidor no aceptó la subida. Si el archivo es muy grande, puede exceder el límite de tamaño de PHP (upload_max_filesize / post_max_size); revísalo en el Dashboard.');
                });
                xhr.addEventListener('error', () => fail('No se pudo completar la subida. Revisa tu conexión e inténtalo de nuevo.'));
                xhr.addEventListener('abort', () => fail('La subida se canceló.'));
                xhr.send(data);
            });
        });
    },

    /**
     * Envia el formulario de opciones de la pagina (Configuracion) para guardar
     * de inmediato lo que se acaba de editar o eliminar.
     */
    submitSettingsForm: function() {
        const form = document.querySelector('form[action="options.php"]');
        if (!form) return;
        document.querySelectorAll('.siarhe-modal-overlay').forEach(m => { m.style.display = 'none'; m.classList.remove('is-open'); });
        HTMLFormElement.prototype.submit.call(form);
    },

    /**
     * Delegación global: formularios con data-confirm piden confirmación en
     * modal; botones con data-copy copian su valor.
     */
    initDelegates: function() {
        document.addEventListener('submit', function(e) {
            const f = e.target;
            if (!f.dataset || !f.dataset.confirm || f.dataset.confirmed === '1') return;
            e.preventDefault();
            window.SiarheAdmin.confirm(f.dataset.confirm, { okText: f.dataset.confirmOk || 'Eliminar' }).then(ok => {
                if (!ok) return;
                f.dataset.confirmed = '1';
                f.submit();
            });
        });
        document.addEventListener('click', function(e) {
            const b = e.target.closest('[data-copy]');
            if (!b) return;
            e.preventDefault(); e.stopPropagation();
            window.SiarheAdmin.copy(b.getAttribute('data-copy'), b);
        });
    },

    /**
     * Inicializa el comportamiento de proxy del botón flotante de guardar.
     */
    initFloatingSaveBtn: function() {
        const existing = document.getElementById('btn-floating-save');

        // Dashboard, Carga de Datos y Configuracion con guardado inmediato:
        // el boton flotante "Subir" lleva al inicio de la pagina
        if (document.querySelector('[data-siarhe-scrolltop]')) {
            if (existing) return;
            const up = document.createElement('button');
            up.type = 'button';
            up.id = 'btn-floating-save';
            up.className = 'button button-primary siarhe-floating-save siarhe-scroll-top';
            up.setAttribute('aria-label', 'Subir al inicio de la página');
            up.innerHTML = '<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span> Subir';
            document.body.appendChild(up);

            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const toggle = () => up.classList.toggle('is-visible', window.scrollY > 240);
            window.addEventListener('scroll', toggle, { passive: true });
            toggle();
            up.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }));
            return;
        }

        // Pantallas de opciones (Personalizar, Enlaces, Tooltips): boton "Guardar"
        const submit = document.querySelector('input[type="submit"]#submit');
        if (!existing && submit) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'btn-floating-save';
            btn.className = 'button button-primary siarhe-floating-save';
            btn.textContent = 'Guardar';
            document.body.appendChild(btn);
            btn.addEventListener('click', () => submit.click());
        }
    },

    /**
     * Muestra y anima sutilmente el botón flotante para llamar la atención del usuario.
     */
    showFloatingSaveBtn: function() {
        const btnFloatingSave = document.getElementById('btn-floating-save');
        if (btnFloatingSave) {
            btnFloatingSave.style.display = 'block';
            setTimeout(() => { btnFloatingSave.style.transform = 'scale(1.05)'; }, 50);
            setTimeout(() => { btnFloatingSave.style.transform = 'scale(1)'; }, 350);
        }
    }
};

// Inicialización general al cargar el DOM
document.addEventListener('DOMContentLoaded', function() {
    window.SiarheAdmin.initFloatingSaveBtn();
    window.SiarheAdmin.initDelegates();
    window.SiarheAdmin.initHelp();
    window.SiarheAdmin.initUploadChecks();
    window.SiarheAdmin.initUploadEnhance();
});

// Inicialización de componentes basados en jQuery requeridos por WordPress
if (window.jQuery) jQuery(document).ready(function($){
    if (!$.fn.wpColorPicker) return;
    
    /**
     * Instancia el selector de color nativo de WordPress (wpColorPicker).
     */
    $('.siarhe-color-field').wpColorPicker({
        change: function(event, ui){
            var element = event.target;
            var color = ui.color.toString();
            
            element.value = color;
            element.dispatchEvent(new Event('change', { bubbles: true }));

            var variable = $(element).data('variable');
            if (variable) {
                document.documentElement.style.setProperty(variable, color);
            }
        },
        clear: function(event){
            // El boton "Borrar" vacia el campo de texto hermano: avisar el cambio
            var input = $(event.target).closest('.wp-picker-container').find('.wp-color-picker')[0];
            if (input) input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

});