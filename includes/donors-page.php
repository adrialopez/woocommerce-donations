<?php
/**
 * Página de gestión de donantes del plugin Donaciones WooCommerce
 * 
 * @package DonationsWC
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'donations-wc'));
}

// Procesar acciones
if (isset($_POST['action']) && wp_verify_nonce($_POST['donors_nonce'], 'donors_action')) {
    donations_wc_process_donor_action();
}

// Parámetros de búsqueda y paginación
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$order_by = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'total_donated';
$order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
$paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$per_page = 20;

/**
 * Procesar acciones de donantes
 */
function donations_wc_process_donor_action() {
    $action = sanitize_text_field($_POST['action']);
    
    switch ($action) {
        case 'send_email':
            $donor_email = sanitize_email($_POST['donor_email']);
            $subject = sanitize_text_field($_POST['email_subject']);
            $message = sanitize_textarea_field($_POST['email_message']);
            
            if (wp_mail($donor_email, $subject, $message)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Email enviado correctamente.', 'donations-wc') . '</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Error al enviar el email.', 'donations-wc') . '</p></div>';
                });
            }
            break;
            
        case 'export_donor':
            $donor_email = sanitize_email($_POST['donor_email']);
            donations_wc_export_donor_data($donor_email);
            break;
    }
}

/**
 * Obtener lista de donantes con paginación
 */
function donations_wc_get_donors($search = '', $order_by = 'total_donated', $order = 'DESC', $limit = 20, $offset = 0) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'donations_wc';
    
    $where_clause = "WHERE 1=1";
    if (!empty($search)) {
        $where_clause .= $wpdb->prepare(" AND (donor_name LIKE %s OR donor_email LIKE %s)", 
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        );
    }
    
    $valid_columns = array('donor_name', 'donor_email', 'total_donated', 'donation_count', 'last_donation');
    if (!in_array($order_by, $valid_columns)) {
        $order_by = 'total_donated';
    }
    
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    
    $sql = "
        SELECT 
            donor_email,
            donor_name,
            donor_country,
            COUNT(*) as donation_count,
            SUM(amount) as total_donated,
            AVG(amount) as avg_donation,
            MAX(created_at) as last_donation,
            MIN(created_at) as first_donation,
            SUM(CASE WHEN frequency = 'monthly' THEN 1 ELSE 0 END) as recurring_count
        FROM $table_name 
        $where_clause
        GROUP BY donor_email
        ORDER BY $order_by $order
        LIMIT %d OFFSET %d
    ";
    
    return $wpdb->get_results($wpdb->prepare($sql, $limit, $offset));
}

/**
 * Contar total de donantes
 */
function donations_wc_count_donors($search = '') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'donations_wc';
    
    $where_clause = "WHERE 1=1";
    if (!empty($search)) {
        $where_clause .= $wpdb->prepare(" AND (donor_name LIKE %s OR donor_email LIKE %s)", 
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        );
    }
    
    $sql = "SELECT COUNT(DISTINCT donor_email) FROM $table_name $where_clause";
    
    return intval($wpdb->get_var($sql));
}

/**
 * Obtener historial de donaciones de un donante
 */
function donations_wc_get_donor_history($donor_email) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'donations_wc';
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table_name 
        WHERE donor_email = %s 
        ORDER BY created_at DESC
    ", $donor_email));
}

/**
 * Exportar datos de un donante
 */
function donations_wc_export_donor_data($donor_email) {
    $history = donations_wc_get_donor_history($donor_email);
    
    if (empty($history)) {
        return;
    }
    
    $filename = 'donante_' . sanitize_file_name($donor_email) . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, array(
        'Fecha',
        'Monto',
        'Frecuencia',
        'Método de Pago',
        'Estado',
        'Mensaje',
        'ID Pedido'
    ));
    
    // Datos
    foreach ($history as $donation) {
        fputcsv($output, array(
            $donation->created_at,
            $donation->amount,
            $donation->frequency === 'monthly' ? 'Mensual' : 'Única',
            $donation->payment_method,
            $donation->status,
            $donation->message,
            $donation->order_id
        ));
    }
    
    fclose($output);
    exit;
}

// Obtener datos
$offset = ($paged - 1) * $per_page;
$donors = donations_wc_get_donors($search, $order_by, $order, $per_page, $offset);
$total_donors = donations_wc_count_donors($search);
$total_pages = ceil($total_donors / $per_page);

// URLs para ordenamiento
function donations_wc_get_sort_url($column) {
    global $order_by, $order, $search;
    
    $new_order = ($order_by === $column && $order === 'ASC') ? 'DESC' : 'ASC';
    
    return admin_url('admin.php?page=donations-wc-donors&orderby=' . $column . '&order=' . $new_order . '&s=' . urlencode($search));
}

// Icono de ordenamiento
function donations_wc_get_sort_icon($column) {
    global $order_by, $order;
    
    if ($order_by !== $column) {
        return '<span class="sort-icon"></span>';
    }
    
    return $order === 'ASC' ? '<span class="sort-icon asc">↑</span>' : '<span class="sort-icon desc">↓</span>';
}
?>

<div class="wrap donors-management">
    <div class="donors-header">
        <h1><?php _e('👥 Gestión de Donantes', 'donations-wc'); ?></h1>
        <p class="subtitle"><?php echo sprintf(__('Total: %d donantes registrados', 'donations-wc'), $total_donors); ?></p>
    </div>

    <!-- Barra de Búsqueda y Filtros -->
    <div class="search-box">
        <form method="get" action="">
            <input type="hidden" name="page" value="donations-wc-donors">
            <input type="hidden" name="orderby" value="<?php echo esc_attr($order_by); ?>">
            <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">
            
            <div class="search-form">
                <input type="search" 
                       name="s" 
                       value="<?php echo esc_attr($search); ?>" 
                       placeholder="<?php _e('Buscar por nombre o email...', 'donations-wc'); ?>"
                       class="search-input">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Buscar', 'donations-wc'); ?>
                </button>
                
                <?php if (!empty($search)): ?>
                    <a href="<?php echo admin_url('admin.php?page=donations-wc-donors'); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php _e('Limpiar', 'donations-wc'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
        
        <div class="bulk-actions">
            <select id="bulk-action-selector">
                <option value=""><?php _e('Acciones en lote', 'donations-wc'); ?></option>
                <option value="export"><?php _e('Exportar seleccionados', 'donations-wc'); ?></option>
                <option value="email"><?php _e('Enviar email masivo', 'donations-wc'); ?></option>
            </select>
            <button type="button" id="apply-bulk-action" class="button button-secondary">
                <?php _e('Aplicar', 'donations-wc'); ?>
            </button>
        </div>
    </div>

    <!-- Tabla de Donantes -->
    <div class="donors-table-container">
        <table class="wp-list-table widefat fixed striped donors-table">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="select-all-donors">
                    </td>
                    <th class="manage-column column-primary sortable">
                        <a href="<?php echo donations_wc_get_sort_url('donor_name'); ?>">
                            <?php _e('Donante', 'donations-wc'); ?>
                            <?php echo donations_wc_get_sort_icon('donor_name'); ?>
                        </a>
                    </th>
                    <th class="manage-column sortable">
                        <a href="<?php echo donations_wc_get_sort_url('donor_email'); ?>">
                            <?php _e('Email', 'donations-wc'); ?>
                            <?php echo donations_wc_get_sort_icon('donor_email'); ?>
                        </a>
                    </th>
                    <th class="manage-column sortable">
                        <a href="<?php echo donations_wc_get_sort_url('total_donated'); ?>">
                            <?php _e('Total Donado', 'donations-wc'); ?>
                            <?php echo donations_wc_get_sort_icon('total_donated'); ?>
                        </a>
                    </th>
                    <th class="manage-column sortable">
                        <a href="<?php echo donations_wc_get_sort_url('donation_count'); ?>">
                            <?php _e('Donaciones', 'donations-wc'); ?>
                            <?php echo donations_wc_get_sort_icon('donation_count'); ?>
                        </a>
                    </th>
                    <th class="manage-column"><?php _e('Recurrentes', 'donations-wc'); ?></th>
                    <th class="manage-column sortable">
                        <a href="<?php echo donations_wc_get_sort_url('last_donation'); ?>">
                            <?php _e('Última Donación', 'donations-wc'); ?>
                            <?php echo donations_wc_get_sort_icon('last_donation'); ?>
                        </a>
                    </th>
                    <th class="manage-column"><?php _e('Acciones', 'donations-wc'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($donors)): ?>
                    <?php foreach ($donors as $donor): ?>
                        <tr data-donor-email="<?php echo esc_attr($donor->donor_email); ?>">
                            <td class="check-column">
                                <input type="checkbox" class="donor-checkbox" value="<?php echo esc_attr($donor->donor_email); ?>">
                            </td>
                            <td class="column-primary">
                                <strong class="donor-name"><?php echo esc_html($donor->donor_name); ?></strong>
                                <?php if ($donor->donor_country): ?>
                                    <span class="donor-country">
                                        <?php 
                                        $countries = array(
                                            'ES' => '🇪🇸',
                                            'MX' => '🇲🇽',
                                            'AR' => '🇦🇷',
                                            'CO' => '🇨🇴',
                                            'PE' => '🇵🇪',
                                            'CL' => '🇨🇱',
                                            'US' => '🇺🇸'
                                        );
                                        echo isset($countries[$donor->donor_country]) ? $countries[$donor->donor_country] : '🌍';
                                        ?>
                                    </span>
                                <?php endif; ?>
                                
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="#" class="view-donor-history" data-email="<?php echo esc_attr($donor->donor_email); ?>">
                                            <?php _e('Ver Historial', 'donations-wc'); ?>
                                        </a> |
                                    </span>
                                    <span class="email">
                                        <a href="#" class="send-donor-email" data-email="<?php echo esc_attr($donor->donor_email); ?>" data-name="<?php echo esc_attr($donor->donor_name); ?>">
                                            <?php _e('Enviar Email', 'donations-wc'); ?>
                                        </a> |
                                    </span>
                                    <span class="export">
                                        <a href="<?php echo admin_url('admin.php?page=donations-wc-donors&action=export_donor&donor_email=' . urlencode($donor->donor_email) . '&nonce=' . wp_create_nonce('donors_action')); ?>">
                                            <?php _e('Exportar', 'donations-wc'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($donor->donor_email); ?>" class="donor-email">
                                    <?php echo esc_html($donor->donor_email); ?>
                                </a>
                            </td>
                            <td>
                                <strong class="total-donated"><?php echo wc_price($donor->total_donated); ?></strong>
                                <small class="avg-donation">
                                    (<?php echo sprintf(__('Promedio: %s', 'donations-wc'), wc_price($donor->avg_donation)); ?>)
                                </small>
                            </td>
                            <td class="donation-count">
                                <span class="count-badge"><?php echo $donor->donation_count; ?></span>
                            </td>
                            <td class="recurring-info">
                                <?php if ($donor->recurring_count > 0): ?>
                                    <span class="recurring-badge"><?php echo $donor->recurring_count; ?></span>
                                <?php else: ?>
                                    <span class="no-recurring">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="last-donation">
                                <?php echo date('d/m/Y', strtotime($donor->last_donation)); ?>
                                <small class="first-donation">
                                    <?php echo sprintf(__('Primera: %s', 'donations-wc'), date('d/m/Y', strtotime($donor->first_donation))); ?>
                                </small>
                            </td>
                            <td class="actions">
                                <div class="action-buttons">
                                    <button type="button" class="button button-small view-donor-history" data-email="<?php echo esc_attr($donor->donor_email); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <button type="button" class="button button-small send-donor-email" data-email="<?php echo esc_attr($donor->donor_email); ?>" data-name="<?php echo esc_attr($donor->donor_name); ?>">
                                        <span class="dashicons dashicons-email-alt"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="no-donors">
                            <?php if (!empty($search)): ?>
                                <?php _e('No se encontraron donantes que coincidan con tu búsqueda.', 'donations-wc'); ?>
                            <?php else: ?>
                                <?php _e('No hay donantes registrados aún.', 'donations-wc'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => admin_url('admin.php?page=donations-wc-donors&%_%'),
                    'format' => '&paged=%#%',
                    'current' => $paged,
                    'total' => $total_pages,
                    'prev_text' => '‹',
                    'next_text' => '›',
                    'add_args' => array(
                        'orderby' => $order_by,
                        'order' => $order,
                        's' => $search
                    )
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Historial de Donante -->
<div id="donor-history-modal" class="donations-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="donor-history-title"><?php _e('Historial de Donaciones', 'donations-wc'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="donor-history-content">
            <div class="loading"><?php _e('Cargando...', 'donations-wc'); ?></div>
        </div>
    </div>
</div>

<!-- Modal: Enviar Email -->
<div id="send-email-modal" class="donations-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Enviar Email a Donante', 'donations-wc'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="send-email-form" method="post">
                <?php wp_nonce_field('donors_action', 'donors_nonce'); ?>
                <input type="hidden" name="action" value="send_email">
                <input type="hidden" name="donor_email" id="email-recipient">
                
                <table class="form-table">
                    <tr>
                        <th><label for="email_subject"><?php _e('Asunto:', 'donations-wc'); ?></label></th>
                        <td>
                            <input type="text" id="email_subject" name="email_subject" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email_message"><?php _e('Mensaje:', 'donations-wc'); ?></label></th>
                        <td>
                            <textarea id="email_message" name="email_message" rows="8" class="large-text" required></textarea>
                            <p class="description">
                                <?php _e('Variables disponibles:', 'donations-wc'); ?>
                                <code>{donor_name}</code>, <code>{foundation_name}</code>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="modal-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Enviar Email', 'donations-wc'); ?>
                    </button>
                    <button type="button" class="button button-secondary modal-close">
                        <?php _e('Cancelar', 'donations-wc'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.donors-management {
    background: #f1f1f1;
    margin: 20px 0 0 -20px;
    padding: 0;
    min-height: calc(100vh - 32px);
}

.donors-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    margin: 0 0 20px 0;
}

.donors-header h1 {
    font-size: 32px;
    margin: 0 0 10px 0;
    font-weight: 300;
}

.donors-header .subtitle {
    font-size: 16px;
    opacity: 0.9;
}

.search-box {
    background: white;
    padding: 20px 30px;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.search-form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-input {
    padding: 8px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    width: 300px;
}

.bulk-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.donors-table-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.donors-table th,
.donors-table td {
    padding: 12px 15px;
}

.donors-table .column-cb {
    width: 40px;
}

.donors-table .column-primary {
    width: 200px;
}

.donor-name {
    font-weight: 600;
    color: #23282d;
}

.donor-country {
    margin-left: 8px;
    font-size: 14px;
}

.donor-email {
    color: #0073aa;
    text-decoration: none;
}

.donor-email:hover {
    text-decoration: underline;
}

.total-donated {
    color: #4CAF50;
    font-size: 16px;
}

.avg-donation {
    color: #666;
    display: block;
    font-size: 12px;
    margin-top: 2px;
}

.count-badge {
    background: #0073aa;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.recurring-badge {
    background: #4CAF50;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.no-recurring {
    color: #999;
}

.first-donation {
    color: #666;
    display: block;
    font-size: 11px;
    margin-top: 2px;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.action-buttons .button {
    padding: 4px 8px;
    min-height: auto;
}

.action-buttons .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.sort-icon {
    opacity: 0.5;
    margin-left: 5px;
}

.sort-icon.asc,
.sort-icon.desc {
    opacity: 1;
    color: #0073aa;
}

.no-donors {
    text-align: center;
    padding: 40px 20px;
    color: #666;
    font-style: italic;
}

.donations-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    min-width: 500px;
    max-width: 90vw;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px 30px;
    border-bottom: 1px solid #e1e1e1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
}

.modal-header h3 {
    margin: 0;
    color: #23282d;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: #999;
}

.modal-body {
    padding: 30px;
    max-height: 70vh;
    overflow-y: auto;
}

.modal-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.history-table th,
.history-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #e1e1e1;
}

.history-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.history-table .amount {
    font-weight: bold;
    color: #4CAF50;
}

.history-table .status {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: bold;
}

.history-table .status.completed {
    background: #e8f5e8;
    color: #2e7d32;
}

.history-table .status.pending {
    background: #fff3e0;
    color: #f57c00;
}

.history-table .status.failed {
    background: #ffebee;
    color: #c62828;
}

.loading {
    text-align: center;
    padding: 40px;
    color: #666;
}

@media (max-width: 768px) {
    .donors-header {
        padding: 20px;
    }
    
    .search-box {
        padding: 15px 20px;
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-form {
        flex-direction: column;
    }
    
    .search-input {
        width: 100%;
    }
    
    .donors-table {
        font-size: 14px;
    }
    
    .donors-table th,
    .donors-table td {
        padding: 8px 10px;
    }
    
    .modal-content {
        min-width: auto;
        width: 95vw;
    }
    
    .modal-body {
        padding: 20px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Seleccionar todos los donantes
    $('#select-all-donors').change(function() {
        $('.donor-checkbox').prop('checked', $(this).is(':checked'));
    });
    
    // Ver historial de donante
    $('.view-donor-history').click(function(e) {
        e.preventDefault();
        
        const email = $(this).data('email');
        const modal = $('#donor-history-modal');
        const content = $('#donor-history-content');
        
        modal.show();
        content.html('<div class="loading"><?php _e('Cargando historial...', 'donations-wc'); ?></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_donor_history',
                donor_email: email,
                nonce: '<?php echo wp_create_nonce('donor_history'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    content.html(response.data.html);
                } else {
                    content.html('<div class="error"><?php _e('Error al cargar el historial.', 'donations-wc'); ?></div>');
                }
            },
            error: function() {
                content.html('<div class="error"><?php _e('Error de conexión.', 'donations-wc'); ?></div>');
            }
        });
    });
    
    // Enviar email a donante
    $('.send-donor-email').click(function(e) {
        e.preventDefault();
        
        const email = $(this).data('email');
        const name = $(this).data('name');
        const modal = $('#send-email-modal');
        
        $('#email-recipient').val(email);
        $('#email_subject').val('<?php printf(__('Mensaje de %s', 'donations-wc'), get_option('donations_wc_foundation_name', 'Nuestra Fundación')); ?>');
        $('#email_message').val('<?php printf(__('Estimado/a %s,\n\nEsperamos que te encuentres bien.\n\nCon gratitud,\n%s', 'donations-wc'), '{donor_name}', get_option('donations_wc_foundation_name', 'Nuestra Fundación')); ?>');
        
        modal.show();
    });
    
    // Cerrar modales
    $('.modal-close, .donations-modal').click(function(e) {
        if (e.target === this) {
            $('.donations-modal').hide();
        }
    });
    
    // Prevenir cierre al hacer clic en el contenido del modal
    $('.modal-content').click(function(e) {
        e.stopPropagation();
    });
    
    // Acciones en lote
    $('#apply-bulk-action').click(function() {
        const action = $('#bulk-action-selector').val();
        const selectedEmails = $('.donor-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (!action) {
            alert('<?php _e('Selecciona una acción.', 'donations-wc'); ?>');
            return;
        }
        
        if (selectedEmails.length === 0) {
            alert('<?php _e('Selecciona al menos un donante.', 'donations-wc'); ?>');
            return;
        }
        
        if (action === 'export') {
            // Exportar donantes seleccionados
            const form = $('<form>', {
                method: 'POST',
                action: admin_url + 'admin.php?page=donations-wc-donors'
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'bulk_export'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'donors_nonce',
                value: '<?php echo wp_create_nonce('donors_action'); ?>'
            }));
            
            selectedEmails.forEach(function(email) {
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'selected_donors[]',
                    value: email
                }));
            });
            
            $('body').append(form);
            form.submit();
            form.remove();
            
        } else if (action === 'email') {
            // Email masivo
            const emails = selectedEmails.join(', ');
            $('#email-recipient').val(emails);
            $('#email_subject').val('<?php printf(__('Mensaje de %s', 'donations-wc'), get_option('donations_wc_foundation_name', 'Nuestra Fundación')); ?>');
            $('#send-email-modal').show();
        }
    });
    
    // Procesar formulario de email
    $('#send-email-form').submit(function(e) {
        e.preventDefault();
        
        const btn = $(this).find('button[type="submit"]');
        const originalText = btn.text();
        
        btn.prop('disabled', true).text('<?php _e('Enviando...', 'donations-wc'); ?>');
        
        $.ajax({
            url: '',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#send-email-modal').hide();
                alert('<?php _e('Email enviado correctamente.', 'donations-wc'); ?>');
            },
            error: function() {
                alert('<?php _e('Error al enviar el email.', 'donations-wc'); ?>');
            },
            complete: function() {
                btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Escape key para cerrar modales
    $(document).keyup(function(e) {
        if (e.keyCode === 27) {
            $('.donations-modal').hide();
        }
    });
});
</script>

<?php
// AJAX handler para obtener historial de donante
add_action('wp_ajax_get_donor_history', function() {
    check_ajax_referer('donor_history', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Sin permisos', 'donations-wc')));
    }
    
    $donor_email = sanitize_email($_POST['donor_email']);
    $history = donations_wc_get_donor_history($donor_email);
    
    if (empty($history)) {
        wp_send_json_error(array('message' => __('No se encontró historial', 'donations-wc')));
    }
    
    ob_start();
    ?>
    <div class="donor-summary">
        <h4><?php echo sprintf(__('Historial de %s', 'donations-wc'), esc_html($history[0]->donor_name)); ?></h4>
        <p><strong><?php _e('Email:', 'donations-wc'); ?></strong> <?php echo esc_html($donor_email); ?></p>
        <?php if ($history[0]->donor_country): ?>
            <p><strong><?php _e('País:', 'donations-wc'); ?></strong> <?php echo esc_html($history[0]->donor_country); ?></p>
        <?php endif; ?>
    </div>
    
    <table class="history-table">
        <thead>
            <tr>
                <th><?php _e('Fecha', 'donations-wc'); ?></th>
                <th><?php _e('Monto', 'donations-wc'); ?></th>
                <th><?php _e('Tipo', 'donations-wc'); ?></th>
                <th><?php _e('Método', 'donations-wc'); ?></th>
                <th><?php _e('Estado', 'donations-wc'); ?></th>
                <th><?php _e('Mensaje', 'donations-wc'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $donation): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($donation->created_at)); ?></td>
                    <td class="amount"><?php echo wc_price($donation->amount); ?></td>
                    <td>
                        <?php if ($donation->frequency === 'monthly'): ?>
                            <span class="recurring-badge">Mensual</span>
                        <?php else: ?>
                            <?php _e('Única', 'donations-wc'); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($donation->payment_method); ?></td>
                    <td>
                        <span class="status <?php echo esc_attr($donation->status); ?>">
                            <?php 
                            $statuses = array(
                                'completed' => __('Completada', 'donations-wc'),
                                'pending' => __('Pendiente', 'donations-wc'),
                                'failed' => __('Fallida', 'donations-wc')
                            );
                            echo isset($statuses[$donation->status]) ? $statuses[$donation->status] : $donation->status;
                            ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($donation->message)): ?>
                            <span title="<?php echo esc_attr($donation->message); ?>">
                                <?php echo esc_html(wp_trim_words($donation->message, 8)); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px; text-align: right;">
        <a href="<?php echo admin_url('admin.php?page=donations-wc-donors&action=export_donor&donor_email=' . urlencode($donor_email) . '&nonce=' . wp_create_nonce('donors_action')); ?>" 
           class="button button-secondary">
            <span class="dashicons dashicons-download"></span>
            <?php _e('Exportar Historial', 'donations-wc'); ?>
        </a>
    </div>
    <?php
    
    $html = ob_get_clean();
    
    wp_send_json_success(array('html' => $html));
});
?>