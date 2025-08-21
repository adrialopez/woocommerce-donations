<?php
/**
 * Página de reportes del plugin Donaciones WooCommerce
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

// Obtener período de reporte
$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '30';
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

// Procesar exportación si se solicita
if (isset($_GET['export']) && wp_verify_nonce($_GET['nonce'], 'donations_export')) {
    donations_wc_export_data($_GET['export'], $period, $start_date, $end_date);
    exit;
}

/**
 * Exportar datos de donaciones
 */
function donations_wc_export_data($format, $period, $start_date = '', $end_date = '') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'donations_wc';
    $where_clause = "WHERE 1=1";
    
    // Filtros de fecha
    if ($period === 'custom' && $start_date && $end_date) {
        $where_clause .= $wpdb->prepare(" AND DATE(created_at) BETWEEN %s AND %s", $start_date, $end_date);
    } elseif ($period !== 'all') {
        $days = intval($period);
        $where_clause .= $wpdb->prepare(" AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);
    }
    
    $donations = $wpdb->get_results("
        SELECT d.*, o.post_status as order_status 
        FROM $table_name d
        LEFT JOIN {$wpdb->posts} o ON d.order_id = o.ID
        $where_clause
        ORDER BY d.created_at DESC
    ");
    
    if ($format === 'csv') {
        donations_wc_export_csv($donations);
    } elseif ($format === 'excel') {
        donations_wc_export_excel($donations);
    }
}

/**
 * Exportar a CSV
 */
function donations_wc_export_csv($donations) {
    $filename = 'donaciones_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, array(
        'ID',
        'Fecha',
        'Donante',
        'Email',
        'País',
        'Monto',
        'Frecuencia',
        'Método de Pago',
        'Estado',
        'Mensaje',
        'ID Pedido'
    ));
    
    // Datos
    foreach ($donations as $donation) {
        fputcsv($output, array(
            $donation->id,
            $donation->created_at,
            $donation->donor_name,
            $donation->donor_email,
            $donation->donor_country,
            $donation->amount,
            $donation->frequency === 'monthly' ? 'Mensual' : 'Única',
            $donation->payment_method,
            $donation->status,
            $donation->message,
            $donation->order_id
        ));
    }
    
    fclose($output);
}

/**
 * Exportar a Excel (HTML table que Excel puede abrir)
 */
function donations_wc_export_excel($donations) {
    $filename = 'donaciones_' . date('Y-m-d') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Fecha</th>';
    echo '<th>Donante</th>';
    echo '<th>Email</th>';
    echo '<th>País</th>';
    echo '<th>Monto</th>';
    echo '<th>Frecuencia</th>';
    echo '<th>Método de Pago</th>';
    echo '<th>Estado</th>';
    echo '<th>Mensaje</th>';
    echo '<th>ID Pedido</th>';
    echo '</tr>';
    
    foreach ($donations as $donation) {
        echo '<tr>';
        echo '<td>' . $donation->id . '</td>';
        echo '<td>' . $donation->created_at . '</td>';
        echo '<td>' . $donation->donor_name . '</td>';
        echo '<td>' . $donation->donor_email . '</td>';
        echo '<td>' . $donation->donor_country . '</td>';
        echo '<td>' . $donation->amount . '</td>';
        echo '<td>' . ($donation->frequency === 'monthly' ? 'Mensual' : 'Única') . '</td>';
        echo '<td>' . $donation->payment_method . '</td>';
        echo '<td>' . $donation->status . '</td>';
        echo '<td>' . htmlspecialchars($donation->message) . '</td>';
        echo '<td>' . $donation->order_id . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
}

/**
 * Obtener estadísticas detalladas
 */
function donations_wc_get_detailed_stats($period, $start_date = '', $end_date = '') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'donations_wc';
    $where_clause = "WHERE status = 'completed'";
    
    // Filtros de fecha
    if ($period === 'custom' && $start_date && $end_date) {
        $where_clause .= $wpdb->prepare(" AND DATE(created_at) BETWEEN %s AND %s", $start_date, $end_date);
    } elseif ($period !== 'all') {
        $days = intval($period);
        $where_clause .= $wpdb->prepare(" AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)", $days);
    }
    
    $stats = array();
    
    // Estadísticas básicas
    $stats['total_amount'] = floatval($wpdb->get_var("SELECT SUM(amount) FROM $table_name $where_clause"));
    $stats['total_donations'] = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause"));
    $stats['recurring_donations'] = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause AND frequency = 'monthly'"));
    $stats['average_donation'] = $stats['total_donations'] > 0 ? ($stats['total_amount'] / $stats['total_donations']) : 0;
    
    // Donaciones por método de pago
    $stats['by_payment_method'] = $wpdb->get_results("
        SELECT payment_method, COUNT(*) as count, SUM(amount) as total
        FROM $table_name $where_clause
        GROUP BY payment_method
        ORDER BY total DESC
    ");
    
    // Donaciones por país
    $stats['by_country'] = $wpdb->get_results("
        SELECT donor_country, COUNT(*) as count, SUM(amount) as total
        FROM $table_name $where_clause
        GROUP BY donor_country
        ORDER BY total DESC
        LIMIT 10
    ");
    
    // Donaciones por mes (últimos 12 meses)
    $stats['by_month'] = $wpdb->get_results("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(amount) as total
        FROM $table_name 
        WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    
    // Top donantes
    $stats['top_donors'] = $wpdb->get_results("
        SELECT 
            donor_name,
            donor_email,
            COUNT(*) as donation_count,
            SUM(amount) as total_donated,
            MAX(created_at) as last_donation
        FROM $table_name $where_clause
        GROUP BY donor_email
        ORDER BY total_donated DESC
        LIMIT 10
    ");
    
    return $stats;
}

// Obtener estadísticas
$stats = donations_wc_get_detailed_stats($period, $start_date, $end_date);

// Configurar título según período
$period_title = '';
switch ($period) {
    case '7':
        $period_title = __('Últimos 7 días', 'donations-wc');
        break;
    case '30':
        $period_title = __('Últimos 30 días', 'donations-wc');
        break;
    case '90':
        $period_title = __('Últimos 3 meses', 'donations-wc');
        break;
    case '365':
        $period_title = __('Último año', 'donations-wc');
        break;
    case 'custom':
        $period_title = sprintf(__('Del %s al %s', 'donations-wc'), $start_date, $end_date);
        break;
    default:
        $period_title = __('Todos los datos', 'donations-wc');
        break;
}
?>

<div class="wrap donations-reports">
    <div class="donations-header">
        <h1><?php _e('📊 Reportes de Donaciones', 'donations-wc'); ?></h1>
        <p class="subtitle"><?php echo sprintf(__('Estadísticas y análisis - %s', 'donations-wc'), $period_title); ?></p>
    </div>

    <!-- Filtros de Período -->
    <div class="period-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="donations-wc-reports">
            
            <div class="filter-group">
                <label for="period"><?php _e('Período:', 'donations-wc'); ?></label>
                <select name="period" id="period">
                    <option value="7" <?php selected($period, '7'); ?>><?php _e('Últimos 7 días', 'donations-wc'); ?></option>
                    <option value="30" <?php selected($period, '30'); ?>><?php _e('Últimos 30 días', 'donations-wc'); ?></option>
                    <option value="90" <?php selected($period, '90'); ?>><?php _e('Últimos 3 meses', 'donations-wc'); ?></option>
                    <option value="365" <?php selected($period, '365'); ?>><?php _e('Último año', 'donations-wc'); ?></option>
                    <option value="all" <?php selected($period, 'all'); ?>><?php _e('Todos los datos', 'donations-wc'); ?></option>
                    <option value="custom" <?php selected($period, 'custom'); ?>><?php _e('Personalizado', 'donations-wc'); ?></option>
                </select>
            </div>
            
            <div class="filter-group custom-dates" style="<?php echo $period === 'custom' ? 'display: block;' : 'display: none;'; ?>">
                <label for="start_date"><?php _e('Desde:', 'donations-wc'); ?></label>
                <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>">
                
                <label for="end_date"><?php _e('Hasta:', 'donations-wc'); ?></label>
                <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>">
            </div>
            
            <button type="submit" class="button button-primary"><?php _e('Aplicar Filtros', 'donations-wc'); ?></button>
        </form>
    </div>

    <!-- Estadísticas Principales -->
    <div class="stats-overview">
        <div class="stat-card green">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo wc_price($stats['total_amount']); ?></div>
                <div class="stat-label"><?php _e('Total Recaudado', 'donations-wc'); ?></div>
            </div>
        </div>
        
        <div class="stat-card blue">
            <div class="stat-icon">🎯</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['total_donations']; ?></div>
                <div class="stat-label"><?php _e('Total Donaciones', 'donations-wc'); ?></div>
            </div>
        </div>
        
        <div class="stat-card orange">
            <div class="stat-icon">🔄</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['recurring_donations']; ?></div>
                <div class="stat-label"><?php _e('Donaciones Recurrentes', 'donations-wc'); ?></div>
            </div>
        </div>
        
        <div class="stat-card purple">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo wc_price($stats['average_donation']); ?></div>
                <div class="stat-label"><?php _e('Promedio por Donación', 'donations-wc'); ?></div>
            </div>
        </div>
    </div>

    <!-- Gráficos y Análisis -->
    <div class="charts-container">
        <!-- Gráfico de Donaciones por Mes -->
        <div class="chart-card">
            <h3><?php _e('Tendencia de Donaciones (Últimos 12 Meses)', 'donations-wc'); ?></h3>
            <canvas id="monthlyChart" width="400" height="200"></canvas>
        </div>
        
        <!-- Donaciones por Método de Pago -->
        <div class="chart-card">
            <h3><?php _e('Métodos de Pago', 'donations-wc'); ?></h3>
            <div class="payment-methods-stats">
                <?php if (!empty($stats['by_payment_method'])): ?>
                    <?php foreach ($stats['by_payment_method'] as $method): ?>
                        <div class="method-stat">
                            <div class="method-info">
                                <span class="method-name">
                                    <?php 
                                    $method_names = array(
                                        'paypal' => 'PayPal',
                                        'stripe' => 'Tarjeta (Stripe)',
                                        'bacs' => 'Transferencia Bancaria'
                                    );
                                    echo isset($method_names[$method->payment_method]) ? $method_names[$method->payment_method] : ucfirst($method->payment_method);
                                    ?>
                                </span>
                                <span class="method-amount"><?php echo wc_price($method->total); ?></span>
                            </div>
                            <div class="method-count"><?php echo sprintf(__('%d donaciones', 'donations-wc'), $method->count); ?></div>
                            <div class="method-bar">
                                <div class="method-progress" style="width: <?php echo ($method->total / $stats['total_amount']) * 100; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?php _e('No hay datos disponibles para este período.', 'donations-wc'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tablas de Datos -->
    <div class="data-tables">
        <!-- Top Donantes -->
        <div class="table-card">
            <div class="table-header">
                <h3><?php _e('🏆 Top Donantes', 'donations-wc'); ?></h3>
                <div class="table-actions">
                    <a href="<?php echo admin_url('admin.php?page=donations-wc-donors'); ?>" class="button button-secondary">
                        <?php _e('Ver Todos los Donantes', 'donations-wc'); ?>
                    </a>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Donante', 'donations-wc'); ?></th>
                        <th><?php _e('Email', 'donations-wc'); ?></th>
                        <th><?php _e('Total Donado', 'donations-wc'); ?></th>
                        <th><?php _e('Donaciones', 'donations-wc'); ?></th>
                        <th><?php _e('Última Donación', 'donations-wc'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($stats['top_donors'])): ?>
                        <?php foreach ($stats['top_donors'] as $donor): ?>
                            <tr>
                                <td><strong><?php echo esc_html($donor->donor_name); ?></strong></td>
                                <td><?php echo esc_html($donor->donor_email); ?></td>
                                <td><strong><?php echo wc_price($donor->total_donated); ?></strong></td>
                                <td><?php echo $donor->donation_count; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($donor->last_donation)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-data"><?php _e('No hay donantes para mostrar en este período.', 'donations-wc'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Donaciones por País -->
        <div class="table-card">
            <div class="table-header">
                <h3><?php _e('🌍 Donaciones por País', 'donations-wc'); ?></h3>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('País', 'donations-wc'); ?></th>
                        <th><?php _e('Donaciones', 'donations-wc'); ?></th>
                        <th><?php _e('Total', 'donations-wc'); ?></th>
                        <th><?php _e('Promedio', 'donations-wc'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($stats['by_country'])): ?>
                        <?php foreach ($stats['by_country'] as $country): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php 
                                        $countries = array(
                                            'ES' => '🇪🇸 España',
                                            'MX' => '🇲🇽 México',
                                            'AR' => '🇦🇷 Argentina',
                                            'CO' => '🇨🇴 Colombia',
                                            'PE' => '🇵🇪 Perú',
                                            'CL' => '🇨🇱 Chile',
                                            'US' => '🇺🇸 Estados Unidos'
                                        );
                                        echo isset($countries[$country->donor_country]) ? $countries[$country->donor_country] : '🌍 ' . $country->donor_country;
                                        ?>
                                    </strong>
                                </td>
                                <td><?php echo $country->count; ?></td>
                                <td><strong><?php echo wc_price($country->total); ?></strong></td>
                                <td><?php echo wc_price($country->total / $country->count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="no-data"><?php _e('No hay datos de países para mostrar.', 'donations-wc'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Acciones de Exportación -->
    <div class="export-actions">
        <h3><?php _e('📁 Exportar Datos', 'donations-wc'); ?></h3>
        <p><?php _e('Descarga los datos de donaciones para contabilidad o análisis:', 'donations-wc'); ?></p>
        
        <div class="export-buttons">
            <?php $export_nonce = wp_create_nonce('donations_export'); ?>
            
            <a href="<?php echo admin_url('admin.php?page=donations-wc-reports&export=csv&period=' . $period . '&start_date=' . $start_date . '&end_date=' . $end_date . '&nonce=' . $export_nonce); ?>" 
               class="button button-secondary">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <?php _e('Exportar CSV', 'donations-wc'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=donations-wc-reports&export=excel&period=' . $period . '&start_date=' . $start_date . '&end_date=' . $end_date . '&nonce=' . $export_nonce); ?>" 
               class="button button-secondary">
                <span class="dashicons dashicons-media-default"></span>
                <?php _e('Exportar Excel', 'donations-wc'); ?>
            </a>
        </div>
    </div>
</div>

<style>
.donations-reports {
    background: #f1f1f1;
    margin: 20px 0 0 -20px;
    padding: 0;
    min-height: calc(100vh - 32px);
}

.donations-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    margin: 0 0 20px 0;
}

.donations-header h1 {
    font-size: 32px;
    margin: 0 0 10px 0;
    font-weight: 300;
}

.donations-header .subtitle {
    font-size: 16px;
    opacity: 0.9;
}

.period-filters {
    background: white;
    padding: 20px 30px;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.period-filters form {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-group label {
    font-weight: 600;
    white-space: nowrap;
}

.filter-group select,
.filter-group input[type="date"] {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat-card.green { border-left: 5px solid #4CAF50; }
.stat-card.blue { border-left: 5px solid #2196F3; }
.stat-card.orange { border-left: 5px solid #FF9800; }
.stat-card.purple { border-left: 5px solid #9C27B0; }

.stat-icon {
    font-size: 32px;
    opacity: 0.8;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
    color: #23282d;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

.charts-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.chart-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.chart-card h3 {
    margin: 0 0 20px 0;
    color: #23282d;
}

.payment-methods-stats {
    space-y: 15px;
}

.method-stat {
    margin-bottom: 20px;
}

.method-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.method-name {
    font-weight: 600;
    color: #23282d;
}

.method-amount {
    font-weight: bold;
    color: #4CAF50;
}

.method-count {
    font-size: 12px;
    color: #666;
    margin-bottom: 8px;
}

.method-bar {
    height: 8px;
    background: #f1f1f1;
    border-radius: 4px;
    overflow: hidden;
}

.method-progress {
    height: 100%;
    background: linear-gradient(90deg, #4CAF50, #45a049);
    transition: width 0.3s ease;
}

.data-tables {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.table-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.table-header {
    padding: 20px 25px;
    border-bottom: 1px solid #f1f1f1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    margin: 0;
    color: #23282d;
}

.table-card table {
    margin: 0;
}

.table-card .no-data {
    text-align: center;
    padding: 40px 20px;
    color: #666;
    font-style: italic;
}

.export-actions {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.export-actions h3 {
    margin: 0 0 10px 0;
    color: #23282d;
}

.export-actions p {
    color: #666;
    margin-bottom: 20px;
}

.export-buttons {
    display: flex;
    gap: 10px;
}

.export-buttons .button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
}

.export-buttons .dashicons {
    font-size: 16px;
}

@media (max-width: 1200px) {
    .charts-container {
        grid-template-columns: 1fr;
    }
    
    .data-tables {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .donations-header {
        padding: 20px;
    }
    
    .period-filters {
        padding: 15px 20px;
    }
    
    .period-filters form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        flex-direction: column;
        align-items: stretch;
    }
    
    .stats-overview {
        grid-template-columns: 1fr;
    }
    
    .export-buttons {
        flex-direction: column;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
jQuery(document).ready(function($) {
    // Mostrar/ocultar campos de fecha personalizada
    $('#period').change(function() {
        if ($(this).val() === 'custom') {
            $('.custom-dates').show();
        } else {
            $('.custom-dates').hide();
        }
    });
    
    // Crear gráfico de tendencia mensual
    const monthlyData = <?php echo json_encode($stats['by_month']); ?>;
    
    if (monthlyData.length > 0) {
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        
                        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('es-ES', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: '<?php _e('Monto Total', 'donations-wc'); ?>',
                    data: monthlyData.map(item => parseFloat(item.total)),
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: '<?php _e('Número de Donaciones', 'donations-wc'); ?>',
                    data: monthlyData.map(item => parseInt(item.count)),
                    borderColor: '#2196F3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: '<?php _e('Monto (€)', 'donations-wc'); ?>'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: '<?php _e('Cantidad', 'donations-wc'); ?>'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    title: {
                        display: false
                    },
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    }
});
</script>

<?php
// AJAX handlers para esta página
add_action('wp_ajax_donations_wc_get_chart_data', function() {
    check_ajax_referer('donations_wc_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Sin permisos', 'donations-wc')));
    }
    
    $period = sanitize_text_field($_POST['period']);
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);
    
    $stats = donations_wc_get_detailed_stats($period, $start_date, $end_date);
    
    wp_send_json_success($stats);
});
?>