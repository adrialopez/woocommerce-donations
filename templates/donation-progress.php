<?php
/**
 * Template del widget de progreso de donaciones
 * 
 * @package DonationsWC
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener parámetros
$goal = isset($atts['goal']) ? floatval($atts['goal']) : 5000;
$current = isset($atts['current']) ? floatval($atts['current']) : 0;
$title = isset($atts['title']) ? $atts['title'] : __('Meta de Donaciones', 'donations-wc');
$show_percentage = isset($atts['show_percentage']) ? filter_var($atts['show_percentage'], FILTER_VALIDATE_BOOLEAN) : true;
$show_amounts = isset($atts['show_amounts']) ? filter_var($atts['show_amounts'], FILTER_VALIDATE_BOOLEAN) : true;
$color = isset($atts['color']) ? $atts['color'] : get_option('donations_wc_primary_color', '#4CAF50');
$height = isset($atts['height']) ? intval($atts['height']) : 12;
$animate = isset($atts['animate']) ? filter_var($atts['animate'], FILTER_VALIDATE_BOOLEAN) : true;

// Si current no se especifica, obtenerlo de la base de datos
if ($current === 0 && !isset($atts['current'])) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'donations_wc';
    
    $current = floatval($wpdb->get_var("
        SELECT SUM(amount) 
        FROM $table_name 
        WHERE status = 'completed'
    "));
}

// Calcular porcentaje
$percentage = $goal > 0 ? min(($current / $goal) * 100, 100) : 0;
$remaining = max($goal - $current, 0);

// Formatear números
$currency_symbol = get_woocommerce_currency_symbol();

// ID único para este widget
$widget_id = 'donation-progress-' . uniqid();

// Clases CSS
$widget_classes = array(
    'donation-progress-widget',
    $animate ? 'animated' : 'static'
);

if ($percentage >= 100) {
    $widget_classes[] = 'goal-reached';
}
?>

<div id="<?php echo esc_attr($widget_id); ?>" 
     class="<?php echo esc_attr(implode(' ', $widget_classes)); ?>"
     data-goal="<?php echo esc_attr($goal); ?>"
     data-current="<?php echo esc_attr($current); ?>"
     data-percentage="<?php echo esc_attr($percentage); ?>">
     
    <?php if (!empty($title)): ?>
        <h3 class="progress-title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>
    
    <?php if ($show_amounts): ?>
        <div class="progress-info">
            <div class="progress-current">
                <strong><?php echo $currency_symbol . number_format($current, 0); ?></strong>
                <span class="label"><?php _e('recaudado', 'donations-wc'); ?></span>
            </div>
            <div class="progress-goal">
                <span class="label"><?php _e('de', 'donations-wc'); ?></span>
                <strong><?php echo $currency_symbol . number_format($goal, 0); ?></strong>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="progress-bar" style="height: <?php echo esc_attr($height); ?>px;">
        <div class="progress-fill" 
             style="width: 0%; background-color: <?php echo esc_attr($color); ?>;"
             data-target-width="<?php echo esc_attr($percentage); ?>%">
        </div>
        
        <?php if ($percentage >= 100): ?>
            <div class="progress-celebration">🎉</div>
        <?php endif; ?>
    </div>
    
    <div class="progress-stats">
        <?php if ($show_percentage): ?>
            <div class="progress-percentage">
                <span class="percentage-value"><?php echo number_format($percentage, 1); ?>%</span>
                <?php if ($percentage >= 100): ?>
                    <span class="goal-reached-text"><?php _e('¡Meta alcanzada!', 'donations-wc'); ?></span>
                <?php else: ?>
                    <span class="progress-text"><?php _e('completado', 'donations-wc'); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($percentage < 100 && $show_amounts): ?>
            <div class="progress-remaining">
                <span class="remaining-amount"><?php echo $currency_symbol . number_format($remaining, 0); ?></span>
                <span class="remaining-text"><?php _e('restantes', 'donations-wc'); ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
    // Mostrar número de donantes si está disponible
    global $wpdb;
    $table_name = $wpdb->prefix . 'donations_wc';
    $donor_count = intval($wpdb->get_var("
        SELECT COUNT(DISTINCT donor_email) 
        FROM $table_name 
        WHERE status = 'completed'
    "));
    
    if ($donor_count > 0):
    ?>
        <div class="progress-supporters">
            <span class="supporters-icon">👥</span>
            <span class="supporters-count"><?php echo $donor_count; ?></span>
            <span class="supporters-text">
                <?php echo _n('persona ha donado', 'personas han donado', $donor_count, 'donations-wc'); ?>
            </span>
        </div>
    <?php endif; ?>
    
    <?php
    // Agregar botón de donación si se especifica
    if (isset($atts['show_button']) && filter_var($atts['show_button'], FILTER_VALIDATE_BOOLEAN)):
        $button_text = isset($atts['button_text']) ? $atts['button_text'] : __('Donar Ahora', 'donations-wc');
        $donation_page_id = get_option('donations_wc_page_id');
        $button_url = $donation_page_id ? get_permalink($donation_page_id) : '#';
    ?>
        <div class="progress-action">
            <a href="<?php echo esc_url($button_url); ?>" 
               class="progress-donate-button"
               style="background-color: <?php echo esc_attr($color); ?>;">
                <?php echo esc_html($button_text); ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
.donation-progress-widget {
    background: var(--donations-background-color, #ffffff);
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin: 20px 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.progress-title {
    margin: 0 0 20px 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--donations-text-color, #333333);
    text-align: center;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    font-size: 16px;
}

.progress-current strong,
.progress-goal strong {
    color: var(--donations-primary-color, #4CAF50);
    font-size: 18px;
}

.progress-info .label {
    color: #666;
    font-size: 14px;
}

.progress-bar {
    background: #f1f1f1;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
    margin-bottom: 15px;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
}

.progress-fill {
    height: 100%;
    border-radius: 8px;
    transition: width 2s ease-out;
    position: relative;
    background: linear-gradient(90deg, var(--donations-primary-color, #4CAF50), var(--donations-secondary-color, #45a049));
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.progress-celebration {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    font-size: 16px;
    animation: bounce 1s infinite alternate;
}

@keyframes bounce {
    0% { transform: translateY(-50%) scale(1); }
    100% { transform: translateY(-50%) scale(1.2); }
}

.progress-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    color: #666;
}

.progress-percentage {
    text-align: left;
}

.percentage-value {
    font-weight: bold;
    color: var(--donations-primary-color, #4CAF50);
    font-size: 16px;
}

.goal-reached-text {
    color: var(--donations-primary-color, #4CAF50);
    font-weight: 600;
    margin-left: 5px;
}

.progress-remaining {
    text-align: right;
}

.remaining-amount {
    font-weight: 600;
    color: var(--donations-text-color, #333);
}

.progress-supporters {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #f1f1f1;
    font-size: 14px;
    color: #666;
}

.supporters-count {
    font-weight: bold;
    color: var(--donations-primary-color, #4CAF50);
}

.supporters-icon {
    font-size: 16px;
}

.progress-action {
    margin-top: 20px;
    text-align: center;
}

.progress-donate-button {
    display: inline-block;
    padding: 12px 24px;
    background: var(--donations-primary-color, #4CAF50);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.progress-donate-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(76, 175, 80, 0.3);
    color: white;
    text-decoration: none;
}

/* Variante para meta alcanzada */
.donation-progress-widget.goal-reached {
    border: 2px solid var(--donations-primary-color, #4CAF50);
    background: linear-gradient(135deg, rgba(76, 175, 80, 0.05), rgba(76, 175, 80, 0.1));
}

.donation-progress-widget.goal-reached .progress-title {
    color: var(--donations-primary-color, #4CAF50);
}

/* Responsive */
@media (max-width: 768px) {
    .donation-progress-widget {
        padding: 20px;
        margin: 15px 0;
    }
    
    .progress-info {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .progress-stats {
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }
    
    .progress-supporters {
        flex-wrap: wrap;
    }
}

/* Modo oscuro */
@media (prefers-color-scheme: dark) {
    .donation-progress-widget {
        background: #2d2d2d;
        color: #ffffff;
    }
    
    .progress-bar {
        background: #404040;
    }
    
    .progress-supporters {
        border-color: #404040;
    }
}

/* Animaciones de entrada */
.donation-progress-widget.animated {
    animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Estados de hover para interactividad */
.donation-progress-widget:hover .progress-fill::after {
    animation-duration: 1s;
}
</style>

<script>
jQuery(document).ready(function($) {
    const widget = $('#<?php echo esc_js($widget_id); ?>');
    const progressFill = widget.find('.progress-fill');
    const targetWidth = progressFill.data('target-width');
    
    // Función para animar el progreso
    function animateProgress() {
        // Delay inicial para cargar
        setTimeout(function() {
            progressFill.css('width', targetWidth);
        }, 500);
    }
    
    // Intersection Observer para activar animación cuando sea visible
    if ('IntersectionObserver' in window && <?php echo $animate ? 'true' : 'false'; ?>) {
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting && !widget.hasClass('animated-done')) {
                    animateProgress();
                    widget.addClass('animated-done');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.5
        });
        
        observer.observe(widget[0]);
    } else {
        // Animar inmediatamente si no hay soporte o está deshabilitado
        animateProgress();
    }
    
    // Agregar funcionalidad de actualización dinámica
    widget.on('update-progress', function(e, newCurrent) {
        const goal = parseFloat(widget.data('goal'));
        const newPercentage = Math.min((newCurrent / goal) * 100, 100);
        
        // Actualizar valores
        widget.data('current', newCurrent);
        widget.data('percentage', newPercentage);
        
        // Actualizar UI
        widget.find('.progress-current strong').text('<?php echo esc_js($currency_symbol); ?>' + Math.round(newCurrent).toLocaleString());
        widget.find('.percentage-value').text(newPercentage.toFixed(1) + '%');
        widget.find('.progress-fill').css('width', newPercentage + '%');
        
        // Actualizar cantidad restante
        const remaining = Math.max(goal - newCurrent, 0);
        widget.find('.remaining-amount').text('<?php echo esc_js($currency_symbol); ?>' + Math.round(remaining).toLocaleString());
        
        // Verificar si se alcanzó la meta
        if (newPercentage >= 100 && !widget.hasClass('goal-reached')) {
            widget.addClass('goal-reached');
            widget.find('.progress-text').text('¡Meta alcanzada!');
            
            // Efecto de celebración
            if (typeof confetti !== 'undefined') {
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 }
                });
            }
        }
    });
    
    // Tracking de visualización
    if (typeof gtag !== 'undefined') {
        gtag('event', 'donation_progress_viewed', {
            'custom_parameters': {
                'current_amount': <?php echo floatval($current); ?>,
                'goal_amount': <?php echo floatval($goal); ?>,
                'percentage': <?php echo floatval($percentage); ?>
            }
        });
    }
});
</script>

<?php
/**
 * Hook para permitir extensiones adicionales
 */
do_action('donations_wc_after_progress', $atts, $current, $goal, $percentage);
?>