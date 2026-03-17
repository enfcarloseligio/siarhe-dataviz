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
     * * @param {string} dateStr - Cadena de fecha y hora en formato MySQL.
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
        document.querySelectorAll('.siarhe-table tbody tr').forEach(row => {
            row.removeEventListener('click', window.SiarheAdmin._handleRowClick);
            row.addEventListener('click', window.SiarheAdmin._handleRowClick);
        });
    },

    /**
     * Manejador interno de eventos para la expansión de filas en vista móvil.
     * Previene la expansión si el objetivo del clic es un elemento interactivo.
     * * @private
     */
    _handleRowClick: function(e) {
        if (window.innerWidth > 767) return; 
        
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input') || e.target.closest('select')) {
            return;
        }
        
        this.classList.toggle('is-open');
    }
};

// Inicialización de componentes basados en jQuery requeridos por WordPress
jQuery(document).ready(function($){
    
    /**
     * Instancia el selector de color nativo de WordPress (wpColorPicker).
     */
    $('.siarhe-color-field').wpColorPicker({
        change: function(event, ui){
            var element = event.target;
            var color = ui.color.toString();
            
            // Actualiza forzosamente el atributo de valor del nodo DOM
            element.value = color;
            
            // Puente de eventos (Event Bridge):
            // La API de wpColorPicker suprime la emisión del evento nativo 'change'.
            // Se despacha manualmente para garantizar la sincronización de estado 
            // con los controladores de eventos encargados de generar las cadenas JSON.
            element.dispatchEvent(new Event('change', { bubbles: true }));

            // Mapeo de variables CSS Custom Properties para renderizado en tiempo real
            var variable = $(element).data('variable');
            if (variable) {
                document.documentElement.style.setProperty(variable, color);
            }
        }
    });

});