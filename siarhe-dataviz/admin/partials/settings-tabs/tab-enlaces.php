<?php // /admin/partials/settings-tabs/tab-enlaces.php
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

<button type="button" class="button button-primary siarhe-floating-save" id="btn-floating-save">
    Guardar
</button>

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

        <div class="siarhe-pagination" style="border-top: none; padding: 0; background: transparent;">
            <div class="siarhe-page-numbers" id="siarhe-pagination-controls-top"></div>
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
        <div class="siarhe-page-numbers" id="siarhe-pagination-controls-bottom"></div>
    </div>
</div>

<div style="margin-top: 20px; padding: 10px; background: #f0f0f1; border-left: 4px solid #72aee6;">
    <p style="margin: 0;"><strong>Nota:</strong> Debe hacer clic en <strong>"Guardar Configuración"</strong> al final de la página para persistir los cambios en la base de datos.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    if (typeof jQuery !== 'undefined') {
        jQuery('.siarhe-searchable-select').select2({
            placeholder: "-- Escribe para buscar --",
            allowClear: true,
            width: '100%' 
        });
    }

    if (window.SiarheAdmin) window.SiarheAdmin.initMobileTables();

    jQuery('.siarhe-searchable-select').on('change', function() {
        if(window.SiarheAdmin && window.SiarheAdmin.showFloatingSaveBtn) {
            window.SiarheAdmin.showFloatingSaveBtn();
        }
    });

    const searchInput = document.getElementById('siarhe-search-enlaces');
    const itemsPerPageSelect = document.getElementById('siarhe-items-per-page');
    const paginationControlsTop = document.getElementById('siarhe-pagination-controls-top');
    const paginationControlsBottom = document.getElementById('siarhe-pagination-controls-bottom');
    const countDisplay = document.getElementById('siarhe-enlaces-count');
    
    const allRows = Array.from(document.querySelectorAll('.siarhe-data-row'));
    const emptyRow = document.getElementById('siarhe-enlaces-empty');

    let currentPage = 1;
    let itemsPerPage = 25;
    let matchedRows = allRows;

    function applySearchFilter() {
        const term = searchInput.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        
        matchedRows = allRows.filter(row => {
            const text = row.cells[0].textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const match = text.includes(term);
            
            if (!match) row.style.display = 'none';
            return match;
        });

        currentPage = 1;
        renderPagination();
    }

    function renderPagination() {
        const totalItems = matchedRows.length;
        let totalPages = 1;
        
        matchedRows.forEach(row => row.style.display = 'none');
        emptyRow.style.display = totalItems === 0 ? '' : 'none';

        if (itemsPerPage === 'all') {
            matchedRows.forEach(row => row.style.display = '');
        } else {
            totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            
            matchedRows.slice(start, end).forEach(row => {
                row.style.display = '';
            });
        }
        
        updatePaginationUI(totalItems, itemsPerPage === 'all' ? totalItems : Math.min(itemsPerPage, totalItems - (currentPage-1)*itemsPerPage), totalPages);
    }

    function generatePaginationHTML(totalPages) {
        let html = '';
        if (totalPages <= 1) return html;

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);

        if (currentPage <= 2) endPage = Math.min(totalPages, 5);
        if (currentPage >= totalPages - 1) startPage = Math.max(1, totalPages - 4);

        html += `<a href="#" class="siarhe-page-btn ${currentPage === 1 ? 'disabled' : ''}" data-page="prev">« Ant</a>`;

        if (startPage > 1) {
            html += `<a href="#" class="siarhe-page-btn" data-page="1">1</a>`;
            if (startPage > 2) html += `<span style="color:#8c8f94;">...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<a href="#" class="siarhe-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</a>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<span style="color:#8c8f94;">...</span>`;
            html += `<a href="#" class="siarhe-page-btn" data-page="${totalPages}">${totalPages}</a>`;
        }

        html += `<a href="#" class="siarhe-page-btn ${currentPage === totalPages ? 'disabled' : ''}" data-page="next">Sig »</a>`;
        
        return html;
    }

    function handlePaginationClick(e) {
        if (!e.target.classList.contains('siarhe-page-btn')) return;
        e.preventDefault();
        
        if (e.target.classList.contains('disabled')) return;

        const pageTarget = e.target.getAttribute('data-page');
        let totalPages = Math.ceil(matchedRows.length / itemsPerPage);

        if (pageTarget === 'prev' && currentPage > 1) {
            currentPage--;
        } else if (pageTarget === 'next' && currentPage < totalPages) {
            currentPage++;
        } else if (!isNaN(parseInt(pageTarget))) {
            currentPage = parseInt(pageTarget);
        }

        renderPagination();
    }

    function updatePaginationUI(totalItems, currentItemsCount, totalPages) {
        if (totalItems === 0) {
            countDisplay.innerHTML = 'No hay registros para mostrar.';
            paginationControlsTop.innerHTML = '';
            paginationControlsBottom.innerHTML = '';
            return;
        }

        let startRange = 1;
        let endRange = totalItems;

        if (itemsPerPage !== 'all') {
            startRange = ((currentPage - 1) * itemsPerPage) + 1;
            endRange = startRange + currentItemsCount - 1;
        }

        countDisplay.innerHTML = `Mostrando del <strong>${startRange}</strong> al <strong>${endRange}</strong> de <strong>${totalItems}</strong> registros`;

        const phtml = generatePaginationHTML(totalPages);
        paginationControlsTop.innerHTML = phtml;
        paginationControlsBottom.innerHTML = phtml;

        paginationControlsTop.querySelectorAll('a').forEach(a => a.addEventListener('click', handlePaginationClick));
        paginationControlsBottom.querySelectorAll('a').forEach(a => a.addEventListener('click', handlePaginationClick));
    }

    searchInput.addEventListener('input', applySearchFilter);
    
    itemsPerPageSelect.addEventListener('change', (e) => {
        itemsPerPage = e.target.value === 'all' ? 'all' : parseInt(e.target.value, 10);
        currentPage = 1;
        renderPagination();
    });

    applySearchFilter();
});
</script>