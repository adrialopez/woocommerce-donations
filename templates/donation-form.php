<?php
/**
 * Template del formulario de donación
 * 
 * @package DonationsWC
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener configuraciones
$settings = array();
$default_settings = array(
    'foundation_name' => 'Mi Fundación',
    'foundation_description' => 'Tu donación hace la diferencia en la vida de muchas personas que lo necesitan',
    'amounts' => array(10, 20, 40, 100),
    'enable_custom_amount' => true,
    'min_amount' => 1,
    'enable_recurring' => true,
    'primary_color' => '#4CAF50',
    'background_color' => '#ffffff',
    'form_style' => 'modern',
    'enable_logo' => false,
    'logo_url' => '',
    'paypal_enabled' => true,
    'stripe_enabled' => true,
    'bacs_enabled' => true
);

foreach ($default_settings as $key => $default) {
    $settings[$key] = get_option('donations_wc_' . $key, $default);
}

// Procesar atributos del shortcode
$style = isset($atts['style']) ? $atts['style'] : 'complete';
$custom_amounts = isset($atts['amounts']) ? explode(',', $atts['amounts']) : $settings['amounts'];
$title = isset($atts['title']) ? $atts['title'] : '';
$description = isset($atts['description']) ? $atts['description'] : '';

// Aplicar configuraciones personalizadas
if (!empty($custom_amounts)) {
    $settings['amounts'] = array_map('floatval', $custom_amounts);
}

if (!empty($title)) {
    $settings['foundation_name'] = $title;
}

if (!empty($description)) {
    $settings['foundation_description'] = $description;
}

// Aplicar estilos CSS personalizados
$custom_css = '';
if ($settings['primary_color'] !== '#4CAF50') {
    $custom_css .= '--donations-primary-color: ' . $settings['primary_color'] . ';';
}
if ($settings['background_color'] !== '#ffffff') {
    $custom_css .= '--donations-background-color: ' . $settings['background_color'] . ';';
}

$form_classes = array(
    'donations-wc-form-container',
    'style-' . $settings['form_style']
);

if ($style === 'simple') {
    $form_classes[] = 'simple-form';
}
?>

<div class="<?php echo esc_attr(implode(' ', $form_classes)); ?>" 
     <?php if ($custom_css): ?>style="<?php echo esc_attr($custom_css); ?>"<?php endif; ?>>
     
    <!-- Header del Formulario -->
    <div class="donations-form-header <?php echo esc_attr('style-' . $settings['form_style']); ?>">
        <?php if ($settings['enable_logo'] && !empty($settings['logo_url'])): ?>
            <img src="<?php echo esc_url($settings['logo_url']); ?>" 
                 alt="<?php echo esc_attr($settings['foundation_name']); ?>" 
                 class="foundation-logo">
        <?php endif; ?>
        
        <h1><?php echo esc_html($settings['foundation_name']); ?></h1>
        <p><?php echo esc_html($settings['foundation_description']); ?></p>
    </div>

    <!-- Contenido del Formulario -->
    <div class="donations-form-content">
        <form class="donations-form" method="post" data-style="<?php echo esc_attr($style); ?>">
            <?php wp_nonce_field('donations_wc_form', 'donations_nonce'); ?>
            
            <?php if ($settings['enable_recurring'] && $style !== 'simple'): ?>
                <!-- Frecuencia de Donación -->
                <div class="donations-section frequency-section">
                    <h3><?php _e('Tipo de Donación', 'donations-wc'); ?></h3>
                    <div class="frequency-toggle">
                        <input type="radio" id="frequency-once" name="donation_frequency" value="once" checked>
                        <label for="frequency-once"><?php _e('Una vez', 'donations-wc'); ?></label>
                        <input type="radio" id="frequency-monthly" name="donation_frequency" value="monthly">
                        <label for="frequency-monthly"><?php _e('Mensual', 'donations-wc'); ?></label>
                    </div>
                    <div class="recurring-note">
                        <span class="icon">💡</span>
                        <?php _e('Las donaciones mensuales se procesarán automáticamente cada mes. Puedes cancelar en cualquier momento.', 'donations-wc'); ?>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="donation_frequency" value="once">
            <?php endif; ?>

            <!-- Cantidad de Donación -->
            <div class="donations-section amount-section">
                <h3><?php _e('¿Cuánto te gustaría donar?', 'donations-wc'); ?></h3>
                
                <div class="amount-grid">
                    <?php foreach ($settings['amounts'] as $index => $amount): ?>
                        <div class="amount-option">
                            <input type="radio" 
                                   id="amount-<?php echo $index; ?>" 
                                   name="preset_amount" 
                                   value="<?php echo esc_attr($amount); ?>">
                            <label for="amount-<?php echo $index; ?>">
                                <?php echo get_woocommerce_currency_symbol() . number_format($amount, 0); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($settings['enable_custom_amount']): ?>
                    <div class="custom-amount">
                        <input type="number" 
                               id="custom_amount" 
                               name="custom_amount" 
                               placeholder="<?php echo esc_attr(sprintf(__('Cantidad personalizada (%s)', 'donations-wc'), get_woocommerce_currency_symbol())); ?>" 
                               min="<?php echo esc_attr($settings['min_amount']); ?>" 
                               step="0.01">
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($style !== 'simple'): ?>
                <!-- Información Personal -->
                <div class="donations-section personal-info-section">
                    <h3><?php _e('Información Personal', 'donations-wc'); ?></h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="donor_first_name"><?php _e('Nombre', 'donations-wc'); ?> *</label>
                            <input type="text" 
                                   id="donor_first_name" 
                                   name="donor_first_name" 
                                   required 
                                   autocomplete="given-name">
                        </div>
                        <div class="form-group">
                            <label for="donor_last_name"><?php _e('Apellidos', 'donations-wc'); ?> *</label>
                            <input type="text" 
                                   id="donor_last_name" 
                                   name="donor_last_name" 
                                   required 
                                   autocomplete="family-name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="donor_email"><?php _e('Correo electrónico', 'donations-wc'); ?> *</label>
                        <input type="email" 
                               id="donor_email" 
                               name="donor_email" 
                               required 
                               autocomplete="email">
                    </div>

                    <div class="form-group">
                        <label for="donor_country"><?php _e('País', 'donations-wc'); ?> *</label>
                        <select id="donor_country" name="donor_country" required autocomplete="country">
                            <option value=""><?php _e('Selecciona tu país', 'donations-wc'); ?></option>
                            <?php
                            $countries = array(
                                'ES' => __('España', 'donations-wc'),
                                'MX' => __('México', 'donations-wc'),
                                'AR' => __('Argentina', 'donations-wc'),
                                'CO' => __('Colombia', 'donations-wc'),
                                'PE' => __('Perú', 'donations-wc'),
                                'CL' => __('Chile', 'donations-wc'),
                                'US' => __('Estados Unidos', 'donations-wc'),
                                'FR' => __('Francia', 'donations-wc'),
                                'DE' => __('Alemania', 'donations-wc'),
                                'IT' => __('Italia', 'donations-wc'),
                                'PT' => __('Portugal', 'donations-wc'),
                                'BR' => __('Brasil', 'donations-wc'),
                                'CA' => __('Canadá', 'donations-wc'),
                                'AU' => __('Australia', 'donations-wc'),
                                'other' => __('Otro', 'donations-wc')
                            );
                            
                            foreach ($countries as $code => $name) {
                                echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="donor_message"><?php _e('Mensaje (opcional)', 'donations-wc'); ?></label>
                        <textarea id="donor_message" 
                                  name="donor_message" 
                                  rows="3" 
                                  placeholder="<?php esc_attr_e('Deja un mensaje de apoyo...', 'donations-wc'); ?>"></textarea>
                    </div>
                </div>

                <!-- Método de Pago -->
                <div class="donations-section payment-section">
                    <h3><?php _e('Método de Pago', 'donations-wc'); ?></h3>
                    
                    <div class="payment-methods">
                        <?php if ($settings['paypal_enabled']): ?>
                            <div class="payment-method">
                                <input type="radio" id="payment-paypal" name="payment_method" value="paypal" checked>
                                <label for="payment-paypal">
                                    <div class="payment-icon paypal">PP</div>
                                    <div class="payment-method-info">
                                        <div class="payment-method-name"><?php _e('PayPal', 'donations-wc'); ?></div>
                                        <div class="payment-method-description"><?php _e('Pago seguro con PayPal', 'donations-wc'); ?></div>
                                    </div>
                                </label>
                            </div>
                        <?php endif; ?>

                        <?php if ($settings['stripe_enabled']): ?>
                            <div class="payment-method">
                                <input type="radio" id="payment-stripe" name="payment_method" value="stripe">
                                <label for="payment-stripe">
                                    <div class="payment-icon stripe">💳</div>
                                    <div class="payment-method-info">
                                        <div class="payment-method-name"><?php _e('Tarjeta de Crédito/Débito', 'donations-wc'); ?></div>
                                        <div class="payment-method-description"><?php _e('Visa, Mastercard, American Express', 'donations-wc'); ?></div>
                                    </div>
                                </label>
                            </div>
                        <?php endif; ?>

                        <?php if ($settings['bacs_enabled']): ?>
                            <div class="payment-method">
                                <input type="radio" id="payment-bacs" name="payment_method" value="bacs">
                                <label for="payment-bacs">
                                    <div class="payment-icon bank">🏦</div>
                                    <div class="payment-method-info">
                                        <div class="payment-method-name"><?php _e('Transferencia Bancaria', 'donations-wc'); ?></div>
                                        <div class="payment-method-description"><?php _e('Solo para donaciones únicas', 'donations-wc'); ?></div>
                                    </div>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Formulario simple: solo email requerido -->
                <div class="donations-section simple-info-section">
                    <div class="form-group">
                        <label for="donor_email_simple"><?php _e('Tu email', 'donations-wc'); ?> *</label>
                        <input type="email" 
                               id="donor_email_simple" 
                               name="donor_email" 
                               placeholder="<?php esc_attr_e('correo@ejemplo.com', 'donations-wc'); ?>"
                               required 
                               autocomplete="email">
                    </div>
                </div>
                
                <!-- Método de pago predeterminado para formulario simple -->
                <input type="hidden" name="payment_method" value="paypal">
                <input type="hidden" name="donor_first_name" value="Donante">
                <input type="hidden" name="donor_last_name" value="Anónimo">
                <input type="hidden" name="donor_country" value="ES">
            <?php endif; ?>

            <!-- Resumen -->
            <div class="summary-box">
                <h4><?php _e('Resumen de tu donación', 'donations-wc'); ?></h4>
                <div class="summary-row">
                    <span><?php _e('Tipo:', 'donations-wc'); ?></span>
                    <span id="summary-type"><?php _e('Una vez', 'donations-wc'); ?></span>
                </div>
                <div class="summary-row">
                    <span><?php _e('Cantidad:', 'donations-wc'); ?></span>
                    <span id="summary-amount"><?php echo get_woocommerce_currency_symbol(); ?>0</span>
                </div>
                <?php if ($style !== 'simple'): ?>
                    <div class="summary-row">
                        <span><?php _e('Método:', 'donations-wc'); ?></span>
                        <span id="summary-method"><?php _e('PayPal', 'donations-wc'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="summary-row total">
                    <span><?php _e('Total:', 'donations-wc'); ?></span>
                    <span id="summary-total"><?php echo get_woocommerce_currency_symbol(); ?>0</span>
                </div>
            </div>

            <!-- Campo oculto para monto final -->
            <input type="hidden" id="final_amount" name="donation_amount" value="0">

            <!-- Botón de Donación -->
            <button type="submit" class="donate-button" disabled>
                <?php _e('Selecciona una cantidad', 'donations-wc'); ?>
            </button>

            <!-- Nota de Seguridad -->
            <div class="security-note">
                <span class="icon">🔒</span>
                <?php _e('Tu información está protegida con encriptación SSL de 256 bits', 'donations-wc'); ?>
            </div>

            <!-- Mensaje de Error -->
            <div class="error-message" id="error-message"></div>
        </form>
    </div>
</div>

<?php
// Agregar scripts inline para configuración específica del formulario
?>
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Configurar variables específicas para este formulario
    if (typeof window.donationsWC === 'undefined') {
        window.donationsWC = {};
    }
    
    // Actualizar configuración específica
    $.extend(window.donationsWC, {
        currency_symbol: '<?php echo esc_js(get_woocommerce_currency_symbol()); ?>',
        min_amount: <?php echo floatval($settings['min_amount']); ?>,
        enable_recurring: <?php echo $settings['enable_recurring'] ? 'true' : 'false'; ?>,
        form_style: '<?php echo esc_js($settings['form_style']); ?>',
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('donations_wc_nonce'); ?>'
    });

    // Configurar colores personalizados
    <?php if ($settings['primary_color'] !== '#4CAF50'): ?>
        document.documentElement.style.setProperty('--donations-primary-color', '<?php echo esc_js($settings['primary_color']); ?>');
    <?php endif; ?>
    
    <?php if ($settings['background_color'] !== '#ffffff'): ?>
        document.documentElement.style.setProperty('--donations-background-color', '<?php echo esc_js($settings['background_color']); ?>');
    <?php endif; ?>

    // Auto-seleccionar primer monto si solo hay uno
    <?php if (count($settings['amounts']) === 1): ?>
        $('#amount-0').prop('checked', true).trigger('change');
    <?php endif; ?>

    // Pre-llenar campos si hay datos en la sesión (usuario logueado)
    <?php if (is_user_logged_in()): ?>
        var currentUser = <?php echo json_encode(array(
            'first_name' => wp_get_current_user()->first_name,
            'last_name' => wp_get_current_user()->last_name,
            'email' => wp_get_current_user()->user_email
        )); ?>;
        
        if (currentUser.first_name) {
            $('#donor_first_name').val(currentUser.first_name);
        }
        if (currentUser.last_name) {
            $('#donor_last_name').val(currentUser.last_name);
        }
        if (currentUser.email) {
            $('#donor_email, #donor_email_simple').val(currentUser.email);
        }
    <?php endif; ?>

    // Configurar tooltips de ayuda si existen
    $('.help-tooltip').each(function() {
        $(this).attr('title', $(this).find('.tooltip-text').text());
    });

    // Tracking para analytics
    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
        console.log('Donations WC Form loaded with settings:', window.donationsWC);
    <?php endif; ?>
});
</script>

<?php
/**
 * Hook para permitir extensiones adicionales
 */
do_action('donations_wc_after_form', $settings, $atts);
?>