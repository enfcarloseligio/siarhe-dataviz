<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Obtener listado de todas las páginas de WordPress
$pages = get_pages(); 
$options_links = [];

if ( $pages ) {
    foreach ( $pages as $page ) {
        $options_links[$page->ID] = '(Página) ' . $page->post_title;
    }
}

// 1.1 Obtener listado de todas las Categorías
$categories = get_terms([
    'taxonomy'   => 'category',
    'hide_empty' => false,
]);

if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
    foreach ( $categories as $cat ) {
        $options_links['cat_' . $cat->term_id] = '(Categoría) ' . $cat->name;
    }
}

// 2. Obtener configuración guardada de la base de datos
$siarhe_links = get_option( 'siarhe_links_map', [] ); 

// 3. Lista Maestra de Entidades + Clínicas + Legales
$entidades = [
    'republica-mexicana'  => 'República Mexicana (Vista Nacional)',
    'aguascalientes'      => 'Aguascalientes',
    'baja-california'     => 'Baja California',
    'baja-california-sur' => 'Baja California Sur',
    'campeche'            => 'Campeche',
    'chiapas'             => 'Chiapas',
    'chihuahua'           => 'Chihuahua',
    'ciudad-de-mexico'    => 'Ciudad de México',
    'coahuila'            => 'Coahuila',
    'colima'              => 'Colima',
    'durango'             => 'Durango',
    'guanajuato'          => 'Guanajuato',
    'guerrero'            => 'Guerrero',
    'hidalgo'             => 'Hidalgo',
    'jalisco'             => 'Jalisco',
    'mexico'              => 'Estado de México',
    'michoacan'           => 'Michoacán',
    'morelos'             => 'Morelos',
    'nayarit'             => 'Nayarit',
    'nuevo-leon'          => 'Nuevo León',
    'oaxaca'              => 'Oaxaca',
    'puebla'              => 'Puebla',
    'queretaro'           => 'Querétaro',
    'quintana-roo'        => 'Quintana Roo',
    'san-luis-potosi'     => 'San Luis Potosí',
    'sinaloa'             => 'Sinaloa',
    'sonora'              => 'Sonora',
    'tabasco'             => 'Tabasco',
    'tamaulipas'          => 'Tamaulipas',
    'tlaxcala'            => 'Tlaxcala',
    'veracruz'            => 'Veracruz',
    'yucatan'             => 'Yucatán',
    'zacatecas'           => 'Zacatecas',
    'clinicas-heridas'    => 'Clínicas de Heridas',
    'clinicas-cateteres'  => 'Clínicas de Catéteres',
    'legal_terminos'      => '📄 Términos y Condiciones',
    'legal_aviso'         => '⚖️ Aviso Legal (Disclaimer)'
];
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Integración de Select2 con UI de WordPress */
    .select2-container .select2-selection--single { height: 30px; border-color: #8c8f94; border-radius: 3px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 28px; }
    .select2-container { max-width: 100% !important; }
</style>

<div class="card siarhe-upload-card" style="max-width: 100%; padding: 20px; margin-bottom: 20px;">
    <h2>🔗 Mapa de Navegación del Sitio</h2>
    <p class="description">
        Configura aquí hacia dónde debe redirigir cada mapa. <br>
        Puedes buscar y seleccionar <strong>Páginas</strong> o <strong>Categorías</strong>. Cuando un usuario haga clic en un estado, el sistema lo enviará al enlace seleccionado.
    </p>
</div>

<div class="card" style="max-width: 100%; padding: 0; overflow: hidden;">
    
    <div class="siarhe-toolbar">
        <div class="siarhe-table-controls">
            <label style="font-size: 13px; color: #3c434a;">
                Mostrar 
                <select id="siarhe-items-per-page" style="margin: 0 5px; padding: 2px 24px 2px 8px; font-size: 13px; min-height: 28px;">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="all">Todos</option>
                </select> 
                registros
            </label>
        </div>

        <div class="siarhe-search-box">
            <span class="dashicons dashicons-search"></span>
            <input type="text" id="siarhe-search-enlaces" placeholder="Buscar entidad, mapa o legal...">
        </div>
    </div>

    <table id="siarhe-enlaces-table" class="siarhe-table" style="table-layout: fixed; width: 100%;">
        <thead>
            <tr>
                <th style="width: 40%;">Entidad / Mapa / Documento</th>
                <th style="width: 60%;">Destino (Escribe para buscar)</th>
            </tr>
        </thead>
        <tbody id="siarhe-enlaces-tbody">
            <?php foreach ($entidades as $slug => $label) : 
                $selected_val = isset($siarhe_links[$slug]) ? $siarhe_links[$slug] : '';
                
                $link_url = '';
                if ( ! empty($selected_val) ) {
                    if ( strpos((string)$selected_val, 'cat_') === 0 ) {
                        $cat_id = (int) str_replace('cat_', '', $selected_val);
                        $link_url = get_term_link($cat_id, 'category');
                        if ( is_wp_error($link_url) ) $link_url = '';
                    } else {
                        $link_url = get_permalink((int)$selected_val);
                    }
                }
                
                $is_legal = (strpos($slug, 'legal_') === 0);
                $row_style = $is_legal ? 'background-color: #f8fafc;' : '';
            ?>
            <tr style="<?php echo $row_style; ?>" class="siarhe-data-row">
                <td data-label="Entidad / Mapa" data-mobile-role="primary" style="word-wrap: break-word;">
                    <strong><?php echo esc_html($label); ?></strong><br>
                    <code style="color:#999; font-size:10px;">ID: <?php echo esc_html($slug); ?></code>
                </td>
                
                <td data-label="Destino" data-mobile-role="secondary">
                    <div style="display: flex; gap: 10px; align-items: center; width: 100%;">
                        
                        <div style="flex-grow: 1; min-width: 0;"> 
                            <select name="siarhe_links_map[<?php echo esc_attr($slug); ?>]" class="siarhe-searchable-select" style="width: 100%;">
                                <option value="">-- Sin enlace (No clicable) --</option>
                                <?php foreach ($options_links as $val => $title) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected( $selected_val, (string)$val ); ?>>
                                        <?php echo esc_html($title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ( ! empty($link_url) ) : ?>
                            <a href="<?php echo esc_url($link_url); ?>" target="_blank" class="button button-small" title="Probar enlace seleccionado" style="flex-shrink: 0;">
                                <span class="dashicons dashicons-external" style="line-height: 1.3;"></span>
                            </a>
                        <?php endif; ?>

                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="siarhe-enlaces-empty" style="display: none;">
                <td colspan="2" style="text-align:center; padding: 20px; color:#8c8f94;">No se encontraron resultados para su búsqueda.</td>
            </tr>
        </tbody>
    </table>

    <div class="siarhe-pagination">
        <div id="siarhe-enlaces-count" style="font-size: 13px; color: #64748b;"></div>
        <div class="siarhe-page-numbers" id="siarhe-pagination-controls"></div>
    </div>
</div>

<div style="margin-top: 20px; padding: 10px; background: #f0f0f1; border-left: 4px solid #72aee6;">
    <p style="margin: 0;"><strong>Nota:</strong> Debe hacer clic en <strong>"Guardar Configuración"</strong> al final de la página para persistir los cambios en la base de datos.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Inicialización de Select2 (Dependencia jQuery)
    if (typeof jQuery !== 'undefined') {
        jQuery('.siarhe-searchable-select').select2({
            placeholder: "-- Escribe para buscar --",
            allowClear: true,
            width: '100%' 
        });
    }

    // ☀️ Motor de Paginación y Filtrado por DOM Hiding
    const searchInput = document.getElementById('siarhe-search-enlaces');
    const itemsPerPageSelect = document.getElementById('siarhe-items-per-page');
    const paginationControls = document.getElementById('siarhe-pagination-controls');
    const countDisplay = document.getElementById('siarhe-enlaces-count');
    
    const allRows = Array.from(document.querySelectorAll('.siarhe-data-row'));
    const emptyRow = document.getElementById('siarhe-enlaces-empty');

    let currentPage = 1;
    let itemsPerPage = 25;
    let matchedRows = allRows;

    function applySearchFilter() {
        const term = searchInput.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        
        matchedRows = allRows.filter(row => {
            // Evaluamos el contenido textual de la primera columna (Entidad)
            const text = row.cells[0].textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const match = text.includes(term);
            
            // Si no hay match, ocultamos inmediatamente
            if (!match) row.style.display = 'none';
            return match;
        });

        currentPage = 1;
        renderPagination();
    }

    function renderPagination() {
        const totalItems = matchedRows.length;
        let totalPages = 1;
        
        // Ocultar todos los matches temporalmente
        matchedRows.forEach(row => row.style.display = 'none');
        emptyRow.style.display = totalItems === 0 ? '' : 'none';

        if (itemsPerPage === 'all') {
            matchedRows.forEach(row => row.style.display = '');
        } else {
            totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            
            // Mostrar solo los elementos de la página actual
            matchedRows.slice(start, end).forEach(row => {
                row.style.display = '';
            });
        }
        
        updatePaginationUI(totalItems, itemsPerPage === 'all' ? totalItems : Math.min(itemsPerPage, totalItems - (currentPage-1)*itemsPerPage), totalPages);
    }

    function updatePaginationUI(totalItems, currentItemsCount, totalPages) {
        if (totalItems === 0) {
            countDisplay.innerHTML = 'No hay registros para mostrar.';
            paginationControls.innerHTML = '';
            return;
        }

        let startRange = 1;
        let endRange = totalItems;

        if (itemsPerPage !== 'all') {
            startRange = ((currentPage - 1) * itemsPerPage) + 1;
            endRange = startRange + currentItemsCount - 1;
        }

        countDisplay.innerHTML = `Mostrando del <strong>${startRange}</strong> al <strong>${endRange}</strong> de <strong>${totalItems}</strong> registros`;

        paginationControls.innerHTML = '';
        if (totalPages <= 1) return;

        const btnPrev = document.createElement('a');
        btnPrev.className = `siarhe-page-btn ${currentPage === 1 ? 'disabled' : ''}`;
        btnPrev.innerHTML = '« Ant';
        btnPrev.addEventListener('click', (e) => { e.preventDefault(); if(currentPage > 1) { currentPage--; renderPagination(); } });
        paginationControls.appendChild(btnPrev);

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (currentPage <= 2) endPage = Math.min(totalPages, 5);
        if (currentPage >= totalPages - 1) startPage = Math.max(1, totalPages - 4);

        if (startPage > 1) {
            const btnFirst = document.createElement('a');
            btnFirst.className = 'siarhe-page-btn'; btnFirst.innerHTML = '1';
            btnFirst.addEventListener('click', (e) => { e.preventDefault(); currentPage = 1; renderPagination(); });
            paginationControls.appendChild(btnFirst);
            if (startPage > 2) paginationControls.insertAdjacentHTML('beforeend', '<span style="color:#8c8f94;">...</span>');
        }

        for (let i = startPage; i <= endPage; i++) {
            const btnP = document.createElement('a');
            btnP.className = `siarhe-page-btn ${i === currentPage ? 'active' : ''}`;
            btnP.innerHTML = i;
            btnP.addEventListener('click', (e) => { e.preventDefault(); currentPage = i; renderPagination(); });
            paginationControls.appendChild(btnP);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) paginationControls.insertAdjacentHTML('beforeend', '<span style="color:#8c8f94;">...</span>');
            const btnLast = document.createElement('a');
            btnLast.className = 'siarhe-page-btn'; btnLast.innerHTML = totalPages;
            btnLast.addEventListener('click', (e) => { e.preventDefault(); currentPage = totalPages; renderPagination(); });
            paginationControls.appendChild(btnLast);
        }

        const btnNext = document.createElement('a');
        btnNext.className = `siarhe-page-btn ${currentPage === totalPages ? 'disabled' : ''}`;
        btnNext.innerHTML = 'Sig »';
        btnNext.addEventListener('click', (e) => { e.preventDefault(); if(currentPage < totalPages) { currentPage++; renderPagination(); } });
        paginationControls.appendChild(btnNext);
    }

    searchInput.addEventListener('input', applySearchFilter);
    
    itemsPerPageSelect.addEventListener('change', (e) => {
        itemsPerPage = e.target.value === 'all' ? 'all' : parseInt(e.target.value, 10);
        currentPage = 1;
        renderPagination();
    });

    // Lógica interna para acordeón móvil aislando clicks en Select2
    document.querySelectorAll('.siarhe-data-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (window.innerWidth > 767) return;
            // Previene expansión si se interactúa con UI del Select2 o botones nativos
            if (e.target.closest('.select2') || e.target.closest('button') || e.target.closest('a') || e.target.closest('select')) return;
            this.classList.toggle('is-open');
        });
    });

    // Inicialización del módulo
    applySearchFilter();
});
</script>