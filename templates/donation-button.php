<?php
/**
 * Template del botón de donación simple
 * 
 * @package DonationsWC
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Valores por defecto
$amount = isset($atts['amount']) ? floatval($atts['amount']) : 25;
$text = isset($atts['text']) ? $atts['text'] : sprintf(__('Donar %s', 'donations-wc'), get_woocommerce_currency_symbol() . $amount);
$style = isset($atts['style']) ? $atts['style'] : 'primary';
$size = isset($atts['size']) ? $atts['size'] : 'medium';
$url = isset($atts['url']) ? $atts['url'] : '';

// Generar URL si no se proporciona
if (empty($url)) {
    $donation_page_id = get_option('donations_wc_page_id');
    if ($donation_page_id) {
        $url = get_permalink($donation_page_id) . '?amount=' . $amount;
    } else {
        $url = '#';
    }
}

// Clases CSS
$button_classes = array(
    'donation-button-simple',
    'style-' . $style,
    'size-' . $size
);

// Aplicar configuraciones personalizadas
$primary_color = get_option('donations_wc_primary_color', '#4CAF50');
$secondary_color = get_option('donations_wc_secondary_color', '#45a049');

$custom_css = '';
if ($primary_color !== '#4CAF50') {
    $custom_css .= '--donations-primary-color: ' . $primary_color . ';';
}
if ($secondary_color !== '#45a049') {
    $custom_css .= '--donations-secondary-color: ' . $secondary_color . ';';
}
?>

<a href="<?php echo esc_url($url); ?>" 
   class="<?php echo esc_attr(implode(' ', $button_classes)); ?>"
   data-amount="<?php echo esc_attr($amount); ?>"
   data-donation-button="true"
   <?php if ($custom_css): ?>style="<?php echo esc_attr($custom_css); ?>"<?php endif; ?>
   role="button"
   aria-label="<?php echo esc_attr(sprintf(__('Donar %s a %s', 'donations-wc'), 
                                          get_woocommerce_currency_symbol() . $amount, 
                                          get_option('donations_wc_foundation_name', 'nuestra fundación'))); ?>">
    
    <span class="button-icon">💝</span>
    <span class="button-text"><?php echo esc_html($text); ?></span>
    <span class="button-arrow">→</span>
</a>

<style>
.donation-button-simple {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: var(--donations-primary-color, #4CAF50);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-family: inherit;
    position: relative;
    overflow: hidden;
}

.donation-button-simple::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.donation-button-simple:hover::before {
    left: 100%;
}

.donation-button-simple:hover {
    background: var(--donations-secondary-color, #45a049);
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(76, 175, 80, 0.3);
    color: white;
    text-decoration: none;
}

.donation-button-simple:active {
    transform: translateY(0);
}

.donation-button-simple .button-icon {
    font-size: 18px;
}

.donation-button-simple .button-arrow {
    font-size: 14px;
    transition: transform 0.3s ease;
}

.donation-button-simple:hover .button-arrow {
    transform: translateX(4px);
}

/* Variaciones de estilo */
.donation-button-simple.style-secondary {
    background: #6c757d;
    color: white;
}

.donation-button-simple.style-secondary:hover {
    background: #5a6268;
}

.donation-button-simple.style-outline {
    background: transparent;
    color: var(--donations-primary-color, #4CAF50);
    border: 2px solid var(--donations-primary-color, #4CAF50);
}

.donation-button-simple.style-outline:hover {
    background: var(--donations-primary-color, #4CAF50);
    color: white;
}

.donation-button-simple.style-minimal {
    background: transparent;
    color: var(--donations-primary-color, #4CAF50);
    box-shadow: none;
    border-radius: 0;
    border-bottom: 2px solid var(--donations-primary-color, #4CAF50);
}

.donation-button-simple.style-minimal:hover {
    background: rgba(76, 175, 80, 0.1);
    transform: none;
    box-shadow: none;
}

/* Variaciones de tamaño */
.donation-button-simple.size-small {
    padding: 8px 16px;
    font-size: 14px;
}

.donation-button-simple.size-small .button-icon {
    font-size: 16px;
}

.donation-button-simple.size-large {
    padding: 16px 32px;
    font-size: 18px;
}

.donation-button-simple.size-large .button-icon {
    font-size: 20px;
}

.donation-button-simple.size-xl {
    padding: 20px 40px;
    font-size: 20px;
}

.donation-button-simple.size-xl .button-icon {
    font-size: 24px;
}

/* Responsive */
@media (max-width: 768px) {
    .donation-button-simple {
        width: 100%;
        justify-content: center;
        text-align: center;
    }
    
    .donation-button-simple.size-large,
    .donation-button-simple.size-xl {
        padding: 14px 24px;
        font-size: 16px;
    }
}

/* Estados de accesibilidad */
.donation-button-simple:focus {
    outline: 2px solid var(--donations-primary-color, #4CAF50);
    outline-offset: 2px;
}

/* Animación de pulso para llamar la atención */
@keyframes pulse-glow {
    0%, 100% {
        box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
    }
    50% {
        box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
    }
}

.donation-button-simple.pulse {
    animation: pulse-glow 2s ease-in-out infinite;
}

/* Modo oscuro */
@media (prefers-color-scheme: dark) {
    .donation-button-simple.style-outline {
        border-color: #81c784;
        color: #81c784;
    }
    
    .donation-button-simple.style-minimal {
        border-color: #81c784;
        color: #81c784;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Mejorar accesibilidad del botón
    $('.donation-button-simple').on('keydown', function(e) {
        if (e.keyCode === 13 || e.keyCode === 32) { // Enter o Espacio
            e.preventDefault();
            $(this)[0].click();
        }
    });
    
    // Tracking de clics
    $('.donation-button-simple').on('click', function(e) {
        const amount = $(this).data('amount');
        
        // Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'donation_button_click', {
                'custom_parameters': {
                    'amount': amount,
                    'button_type': 'simple'
                }
            });
        }
        
        // Facebook Pixel
        if (typeof fbq !== 'undefined') {
            fbq('track', 'Lead', {
                value: amount,
                currency: 'EUR',
                content_category: 'donation_button'
            });
        }
        
        // Evento personalizado
        $(document).trigger('donation_button_clicked', {
            amount: amount,
            button: this
        });
    });
});
</script>

<?php
/**
 * Hook para permitir extensiones adicionales
 */
do_action('donations_wc_after_button', $atts);
?>