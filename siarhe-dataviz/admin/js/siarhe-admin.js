jQuery(document).ready(function($){
    // Inicializar el selector de color de WordPress
    $('.siarhe-color-field').wpColorPicker({
        change: function(event, ui){
            // Vista previa en tiempo real (Live Preview)
            var element = $(event.target);
            var color = ui.color.toString();
            var variable = element.data('variable');
            
            // Actualizar la variable CSS en el documento
            document.documentElement.style.setProperty(variable, color);
        }
    });
});