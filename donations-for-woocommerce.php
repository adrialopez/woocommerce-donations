<?php
/**
 * Plugin Name: Donaciones para WooCommerce
 * Plugin URI: https://adria-lopez.com
 * Description: Sistema completo de donaciones para fundaciones con soporte para pagos únicos y recurrentes a través de WooCommerce.
 * Version: 1.0.0
 * Author: Adrià López
 * Author URI: https://adria-lopez.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: donations-wc
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * 
 * @package DonationsWC
 * @author Adrià López
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar versión de PHP
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Donaciones WooCommerce</strong> requiere PHP 7.4 o superior. Tu versión actual es ' . PHP_VERSION . '</p></div>';
    });
    return;
}

/**
 * Constantes del Plugin
 */
define('DONATIONS_WC_VERSION', '1.0.0');
define('DONATIONS_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DONATIONS_WC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DONATIONS_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase Principal del Plugin
 */
class DonationsWooCommerce {
    
    /**
     * Instancia única del plugin
     */
    private static $instance = null;
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Hooks de instalación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // AJAX Handlers
        add_action('wp_ajax_process_donation_form', array($this, 'process_donation_form'));
        add_action('wp_ajax_nopriv_process_donation_form', array($this, 'process_donation_form'));
        
        // Shortcodes
        add_shortcode('donation_form', array($this, 'donation_form_shortcode'));
        add_shortcode('donation_button', array($this, 'donation_button_shortcode'));
        add_shortcode('donation_progress', array($this, 'donation_progress_shortcode'));
        
        // Internacionalización
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // PROCESAR GUARDADO DIRECTO EN EL PLUGIN PRINCIPAL
        add_action('admin_init', array($this, 'handle_admin_save'));
    }
    
    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * MANEJAR GUARDADO DIRECTO - NUEVA FUNCIÓN
     */
    public function handle_admin_save() {
        // Solo procesar en la página de administración del plugin
        if (!isset($_GET['page']) || $_GET['page'] !== 'donations-wc') {
            return;
        }
        
        // Solo procesar POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Verificar nonce
        if (!isset($_POST['donations_save']) || !wp_verify_nonce($_POST['donations_nonce'] ?? '', 'donations_save_action')) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // GUARDAR CONFIGURACIONES
        $saved = 0;
        
        // Campos simples
        $simple_fields = array(
            'foundation_name' => 'text',
            'foundation_description' => 'textarea',
            'min_amount' => 'number',
            'primary_color' => 'text',
            'background_color' => 'text',
            'form_style' => 'text',
            'subscription_handler' => 'text',
            'email_subject' => 'text',
            'email_message' => 'textarea',
            'admin_email' => 'email'
        );
        
        foreach ($simple_fields as $field => $type) {
            $value = $_POST[$field] ?? '';
            
            if ($type === 'text') {
                $value = sanitize_text_field($value);
            } elseif ($type === 'textarea') {
                $value = sanitize_textarea_field($value);
            } elseif ($type === 'number') {
                $value = floatval($value);
            } elseif ($type === 'email') {
                $value = sanitize_email($value);
            }
            
            if (update_option('donations_wc_' . $field, $value)) {
                $saved++;
            }
        }
        
        // Checkboxes
        $checkboxes = array('enable_custom_amount', 'enable_recurring', 'enable_logo', 
                           'paypal_enabled', 'stripe_enabled', 'bacs_enabled', 
                           'email_enabled', 'admin_notifications');
        
        foreach ($checkboxes as $checkbox) {
            $value = isset($_POST[$checkbox]) ? 1 : 0;
            if (update_option('donations_wc_' . $checkbox, $value)) {
                $saved++;
            }
        }
        
        // Amounts array
        $amounts = array();
        for ($i = 1; $i <= 4; $i++) {
            $amount = floatval($_POST["amount_$i"] ?? 0);
            if ($amount > 0) $amounts[] = $amount;
        }
        if (update_option('donations_wc_amounts', $amounts)) {
            $saved++;
        }
        
        // Redirigir con mensaje de éxito
        wp_redirect(admin_url('admin.php?page=donations-wc&saved=' . $saved));
        exit;
    }
    
    /**
     * Inicialización del plugin
     */
    public function init() {
        // Crear producto de donación si no existe
        $this->create_donation_product();
        
        // Crear tablas de base de datos si es necesario
        $this->create_database_tables();
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Verificar versión de PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die(__('Este plugin requiere PHP 7.4 o superior.', 'donations-wc'));
        }
        
        // Verificar WooCommerce
        if (!class_exists('WooCommerce')) {
            wp_die(__('Este plugin requiere WooCommerce. Por favor instala WooCommerce primero.', 'donations-wc'));
        }
        
        // Configurar opciones por defecto
        $this->setup_default_options();
        
        // Crear producto de donación
        $this->create_donation_product();
        
        // Crear tablas de base de datos
        $this->create_database_tables();
        
        // Crear páginas automáticamente
        $this->create_default_pages();
        
        // Limpiar rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Configurar opciones por defecto
     */
    private function setup_default_options() {
        $defaults = array(
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
            'bacs_enabled' => true,
            'subscription_handler' => 'native',
            'email_enabled' => true,
            'email_subject' => '¡Gracias por tu donación!',
            'email_message' => "Estimado/a {donor_name},\n\n¡Muchísimas gracias por tu generosa donación de {amount}!\n\nTu apoyo es fundamental para continuar con nuestra misión.\n\nCon gratitud,\n{foundation_name}",
            'admin_notifications' => true
        );
        
        foreach ($defaults as $key => $value) {
            add_option('donations_wc_' . $key, $value);
        }
        
        // Configurar email admin por defecto
        if (empty(get_option('donations_wc_admin_email'))) {
            update_option('donations_wc_admin_email', get_option('admin_email'));
        }
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Crear producto de donación
     */
    private function create_donation_product() {
        $existing_product_id = get_option('donations_wc_product_id');
        
        // Verificar si el producto ya existe
        if ($existing_product_id && get_post($existing_product_id)) {
            return $existing_product_id;
        }
        
        $product = new WC_Product_Simple();
        $product->set_name(__('Donación a la Fundación', 'donations-wc'));
        $product->set_slug('donacion-fundacion');
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_description(__('Producto para procesar donaciones a la fundación', 'donations-wc'));
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_sold_individually(true);
        $product->set_manage_stock(false);
        $product->set_price(0);
        $product->set_regular_price(0);
        
        $product_id = $product->save();
        
        if ($product_id) {
            update_option('donations_wc_product_id', $product_id);
        }
        
        return $product_id;
    }
    
    /**
     * Crear tablas de base de datos
     */
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'donations_wc';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NULL,
            donor_email varchar(100) NOT NULL,
            donor_name varchar(200) NOT NULL,
            donor_country varchar(10) NULL,
            amount decimal(10,2) NOT NULL,
            frequency varchar(20) DEFAULT 'once',
            payment_method varchar(50) NULL,
            status varchar(20) DEFAULT 'pending',
            message text NULL,
            subscription_id varchar(100) NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY donor_email (donor_email),
            KEY status (status),
            KEY frequency (frequency)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        update_option('donations_wc_db_version', '1.0');
    }
    
    /**
     * Crear páginas por defecto
     */
    private function create_default_pages() {
        // Página de donaciones
        $donation_page = array(
            'post_title' => __('Donaciones', 'donations-wc'),
            'post_content' => '[donation_form]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'donaciones'
        );
        
        $page_id = wp_insert_post($donation_page);
        if ($page_id) {
            update_option('donations_wc_page_id', $page_id);
        }
        
        // Página de agradecimiento
        $thank_you_page = array(
            'post_title' => __('Gracias por tu Donación', 'donations-wc'),
            'post_content' => '<h2>' . __('¡Muchas gracias por tu generosa donación!', 'donations-wc') . '</h2><p>' . __('Tu apoyo es fundamental para continuar con nuestra misión.', 'donations-wc') . '</p>',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'gracias-donacion'
        );
        
        $thank_you_page_id = wp_insert_post($thank_you_page);
        if ($thank_you_page_id) {
            update_option('donations_wc_thank_you_page_id', $thank_you_page_id);
        }
    }
    
    /**
     * Menú de administración
     */
    public function admin_menu() {
        add_menu_page(
            __('Donaciones', 'donations-wc'),
            __('Donaciones', 'donations-wc'),
            'manage_options',
            'donations-wc',
            array($this, 'admin_page'),
            'dashicons-heart',
            30
        );
        
        add_submenu_page(
            'donations-wc',
            __('Configuración', 'donations-wc'),
            __('Configuración', 'donations-wc'),
            'manage_options',
            'donations-wc',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'donations-wc',
            __('Reportes', 'donations-wc'),
            __('Reportes', 'donations-wc'),
            'manage_options',
            'donations-wc-reports',
            array($this, 'reports_page')
        );
    }
    
    /**
     * Página principal de administración - INTEGRADA
     */
    public function admin_page() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('Sin permisos');
        }
        
        // Obtener mensaje de éxito si existe
        $saved = isset($_GET['saved']) ? intval($_GET['saved']) : 0;
        
        // Obtener configuraciones actuales
        $settings = $this->get_admin_settings();
        
        ?>
        <div class="wrap">
            <h1>🎯 Donaciones WooCommerce</h1>
            
            <?php if ($saved > 0): ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Configuración guardada correctamente (<?php echo $saved; ?> opciones actualizadas).</strong></p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="" style="background: white; padding: 30px; border: 1px solid #ddd; border-radius: 8px;">
                <?php wp_nonce_field('donations_save_action', 'donations_nonce'); ?>
                
                <h2>Configuración General</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="foundation_name">Nombre de la Fundación</label>
                        </th>
                        <td>
                            <input type="text" id="foundation_name" name="foundation_name" 
                                   value="<?php echo esc_attr($settings['foundation_name']); ?>" 
                                   class="regular-text" required />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="foundation_description">Descripción</label>
                        </th>
                        <td>
                            <textarea id="foundation_description" name="foundation_description" rows="3" class="large-text"><?php echo esc_textarea($settings['foundation_description']); ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Montos Predefinidos (€)</th>
                        <td>
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                            <input type="number" name="amount_<?php echo $i; ?>" 
                                   value="<?php echo isset($settings['amounts'][$i-1]) ? esc_attr($settings['amounts'][$i-1]) : ''; ?>" 
                                   min="0.01" step="0.01" placeholder="<?php echo ($i * 10); ?>" 
                                   style="width: 80px; margin-right: 10px;" />
                            <?php endfor; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Opciones</th>
                        <td>
                            <label><input type="checkbox" name="enable_custom_amount" value="1" <?php checked($settings['enable_custom_amount']); ?> /> Permitir cantidad personalizada</label><br>
                            <label><input type="checkbox" name="enable_recurring" value="1" <?php checked($settings['enable_recurring']); ?> /> Habilitar donaciones recurrentes</label><br>
                            <label><input type="checkbox" name="enable_logo" value="1" <?php checked($settings['enable_logo']); ?> /> Mostrar logo</label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="min_amount">Monto Mínimo (€)</label>
                        </th>
                        <td>
                            <input type="number" id="min_amount" name="min_amount" 
                                   value="<?php echo esc_attr($settings['min_amount']); ?>" 
                                   min="0.01" step="0.01" style="width: 100px;" />
                        </td>
                    </tr>
                </table>
                
                <h2>Apariencia</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="primary_color">Color Primario</label>
                        </th>
                        <td>
                            <input type="color" id="primary_color" name="primary_color" 
                                   value="<?php echo esc_attr($settings['primary_color']); ?>" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="form_style">Estilo del Formulario</label>
                        </th>
                        <td>
                            <select id="form_style" name="form_style">
                                <option value="modern" <?php selected($settings['form_style'], 'modern'); ?>>Moderno</option>
                                <option value="classic" <?php selected($settings['form_style'], 'classic'); ?>>Clásico</option>
                                <option value="minimal" <?php selected($settings['form_style'], 'minimal'); ?>>Minimalista</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2>Métodos de Pago</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Métodos Habilitados</th>
                        <td>
                            <label><input type="checkbox" name="paypal_enabled" value="1" <?php checked($settings['paypal_enabled']); ?> /> PayPal</label><br>
                            <label><input type="checkbox" name="stripe_enabled" value="1" <?php checked($settings['stripe_enabled']); ?> /> Stripe (Tarjetas)</label><br>
                            <label><input type="checkbox" name="bacs_enabled" value="1" <?php checked($settings['bacs_enabled']); ?> /> Transferencia Bancaria</label>
                        </td>
                    </tr>
                </table>
                
                <h2>Emails</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Configuración de Email</th>
                        <td>
                            <label><input type="checkbox" name="email_enabled" value="1" <?php checked($settings['email_enabled']); ?> /> Enviar email de agradecimiento</label><br>
                            <label><input type="checkbox" name="admin_notifications" value="1" <?php checked($settings['admin_notifications']); ?> /> Notificaciones al admin</label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="email_subject">Asunto del Email</label>
                        </th>
                        <td>
                            <input type="text" id="email_subject" name="email_subject" 
                                   value="<?php echo esc_attr($settings['email_subject']); ?>" 
                                   class="large-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="admin_email">Email del Admin</label>
                        </th>
                        <td>
                            <input type="email" id="admin_email" name="admin_email" 
                                   value="<?php echo esc_attr($settings['admin_email']); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="donations_save" class="button-primary" 
                           value="💾 Guardar Configuración" 
                           style="font-size: 16px; padding: 10px 20px; height: auto;" />
                </p>
            </form>
            
            <div style="background: #f0f8ff; border: 1px solid #0073aa; padding: 20px; border-radius: 4px; margin-top: 20px;">
                <h3>📋 Shortcodes Disponibles</h3>
                <p><code>[donation_form]</code> - Formulario completo de donación</p>
                <p><code>[donation_button amount="25"]</code> - Botón directo de donación</p>
                <p><code>[donation_progress goal="5000"]</code> - Barra de progreso</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Obtener configuraciones de admin
     */
    private function get_admin_settings() {
        $defaults = array(
            'foundation_name' => 'Mi Fundación',
            'foundation_description' => 'Tu donación hace la diferencia',
            'amounts' => array(10, 20, 40, 100),
            'enable_custom_amount' => true,
            'min_amount' => 1,
            'enable_recurring' => true,
            'primary_color' => '#4CAF50',
            'background_color' => '#ffffff',
            'form_style' => 'modern',
            'enable_logo' => false,
            'paypal_enabled' => true,
            'stripe_enabled' => true,
            'bacs_enabled' => true,
            'subscription_handler' => 'native',
            'email_enabled' => true,
            'email_subject' => '¡Gracias por tu donación!',
            'email_message' => "Estimado/a {donor_name},\n\n¡Gracias por tu donación de {amount}!",
            'admin_notifications' => true,
            'admin_email' => get_option('admin_email')
        );

        $settings = array();
        foreach ($defaults as $key => $default) {
            $settings[$key] = get_option('donations_wc_' . $key, $default);
        }

        return $settings;
    }
    
    /**
     * Página de reportes
     */
    public function reports_page() {
        echo '<div class="wrap"><h1>Reportes de Donaciones</h1><p>Próximamente disponible.</p></div>';
    }
    
    /**
     * Enqueue scripts frontend
     */
    public function enqueue_scripts() {
        // Scripts del frontend
    }
    
    /**
     * Enqueue scripts admin
     */
    public function admin_enqueue_scripts($hook) {
        // Scripts del admin
    }
    
    /**
     * Procesar formulario de donación
     */
    public function process_donation_form() {
        wp_send_json_success(array('message' => 'Formulario procesado'));
    }
    
    /**
     * Shortcode del formulario de donación
     */
    public function donation_form_shortcode($atts) {
        // Parsear atributos
        $atts = shortcode_atts(array(
            'style' => '',
            'amounts' => '',
            'title' => '',
            'description' => ''
        ), $atts);
        
        // Obtener configuraciones
        $settings = $this->get_admin_settings();
        
        // Usar atributos personalizados si se proporcionan
        $foundation_name = !empty($atts['title']) ? $atts['title'] : $settings['foundation_name'];
        $description = !empty($atts['description']) ? $atts['description'] : $settings['foundation_description'];
        $form_style = !empty($atts['style']) ? $atts['style'] : $settings['form_style'];
        
        // Procesar amounts personalizados
        if (!empty($atts['amounts'])) {
            $amounts = array_map('floatval', explode(',', $atts['amounts']));
        } else {
            $amounts = $settings['amounts'];
        }
        
        // Símbolo de moneda
        $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '€';
        
        // ID único para este formulario
        $form_id = 'donations_form_' . uniqid();
        
        ob_start();
        ?>
        <div class="donations-wc-form-container <?php echo esc_attr($form_style); ?>" id="<?php echo esc_attr($form_id); ?>">
            <style>
            .donations-wc-form-container {
                max-width: 500px;
                margin: 20px auto;
                background: <?php echo esc_attr($settings['background_color']); ?>;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            .donations-wc-form-container.modern {
                border-radius: 16px;
            }
            
            .donations-wc-form-container.classic {
                border-radius: 4px;
                border: 2px solid #ddd;
            }
            
            .donations-wc-form-container.minimal {
                background: transparent;
                box-shadow: none;
                border: 1px solid #eee;
            }
            
            .donations-header h3 {
                color: <?php echo esc_attr($settings['primary_color']); ?>;
                margin: 0 0 10px 0;
                font-size: 24px;
                font-weight: 600;
                text-align: center;
            }
            
            .donations-description {
                text-align: center;
                color: #666;
                margin-bottom: 25px;
                line-height: 1.5;
            }
            
            .donations-amounts {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 20px;
            }
            
            .amount-option {
                padding: 15px;
                border: 2px solid #e1e5e9;
                border-radius: 8px;
                text-align: center;
                cursor: pointer;
                transition: all 0.3s ease;
                background: white;
                font-weight: 600;
                font-size: 16px;
            }
            
            .amount-option:hover {
                border-color: <?php echo esc_attr($settings['primary_color']); ?>;
                transform: translateY(-2px);
            }
            
            .amount-option.selected {
                border-color: <?php echo esc_attr($settings['primary_color']); ?>;
                background: <?php echo esc_attr($settings['primary_color']); ?>;
                color: white;
            }
            
            .custom-amount {
                margin: 15px 0;
                position: relative;
            }
            
            .custom-amount input {
                width: 100%;
                padding: 15px 15px 15px 45px;
                border: 2px solid #e1e5e9;
                border-radius: 8px;
                font-size: 16px;
                transition: border-color 0.3s ease;
                box-sizing: border-box;
            }
            
            .custom-amount input:focus {
                outline: none;
                border-color: <?php echo esc_attr($settings['primary_color']); ?>;
            }
            
            .currency-symbol {
                position: absolute;
                left: 15px;
                top: 50%;
                transform: translateY(-50%);
                font-weight: bold;
                color: #666;
            }
            
            .recurring-option {
                margin: 20px 0;
                text-align: center;
            }
            
            .recurring-option label {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                cursor: pointer;
                font-size: 14px;
                color: #666;
            }
            
            .donate-button {
                width: 100%;
                padding: 18px;
                background: <?php echo esc_attr($settings['primary_color']); ?>;
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 18px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-top: 20px;
            }
            
            .donate-button:hover {
                background: color-mix(in srgb, <?php echo esc_attr($settings['primary_color']); ?> 90%, black);
                transform: translateY(-2px);
            }
            
            .donate-button:disabled {
                background: #ccc;
                cursor: not-allowed;
                transform: none;
            }
            
            .error-message {
                background: #fee;
                color: #c33;
                padding: 10px;
                border-radius: 4px;
                margin: 10px 0;
                display: none;
            }
            
            .success-message {
                background: #efe;
                color: #3c3;
                padding: 10px;
                border-radius: 4px;
                margin: 10px 0;
                display: none;
            }
            
            @media (max-width: 480px) {
                .donations-wc-form-container {
                    margin: 10px;
                    padding: 20px;
                }
                
                .donations-amounts {
                    grid-template-columns: 1fr;
                }
            }
            </style>
            
            <div class="donations-header">
                <?php if ($settings['enable_logo'] && !empty($settings['logo_url'])): ?>
                <img src="<?php echo esc_url($settings['logo_url']); ?>" alt="Logo" style="max-height: 60px; margin-bottom: 15px;">
                <?php endif; ?>
                
                <h3><?php echo esc_html($foundation_name); ?></h3>
                <p class="donations-description"><?php echo esc_html($description); ?></p>
            </div>
            
            <form class="donations-form" data-min-amount="<?php echo esc_attr($settings['min_amount']); ?>">
                <div class="error-message" id="error-<?php echo esc_attr($form_id); ?>"></div>
                <div class="success-message" id="success-<?php echo esc_attr($form_id); ?>"></div>
                
                <!-- Montos predefinidos -->
                <?php if (!empty($amounts)): ?>
                <div class="donations-amounts">
                    <?php foreach ($amounts as $amount): ?>
                    <div class="amount-option" data-amount="<?php echo esc_attr($amount); ?>">
                        <?php echo $currency_symbol . number_format($amount, 0); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Cantidad personalizada -->
                <?php if ($settings['enable_custom_amount']): ?>
                <div class="custom-amount">
                    <span class="currency-symbol"><?php echo esc_html($currency_symbol); ?></span>
                    <input type="number" 
                           name="custom_amount" 
                           placeholder="Cantidad personalizada" 
                           min="<?php echo esc_attr($settings['min_amount']); ?>" 
                           step="0.01">
                </div>
                <?php endif; ?>
                
                <!-- Opción recurrente -->
                <?php if ($settings['enable_recurring']): ?>
                <div class="recurring-option">
                    <label>
                        <input type="checkbox" name="is_recurring" value="1">
                        <span>💝 Hacer esta donación mensual</span>
                    </label>
                </div>
                <?php endif; ?>
                
                <!-- Botón de donación -->
                <button type="submit" class="donate-button">
                    <span class="button-text">💖 Donar Ahora</span>
                    <span class="button-loading" style="display: none;">Procesando...</span>
                </button>
            </form>
        </div>
        
        <script>
        (function() {
            const container = document.getElementById('<?php echo esc_js($form_id); ?>');
            const form = container.querySelector('.donations-form');
            const amountOptions = container.querySelectorAll('.amount-option');
            const customAmountInput = container.querySelector('input[name="custom_amount"]');
            const submitButton = container.querySelector('.donate-button');
            const errorDiv = container.querySelector('#error-<?php echo esc_js($form_id); ?>');
            const successDiv = container.querySelector('#success-<?php echo esc_js($form_id); ?>');
            
            let selectedAmount = 0;
            const minAmount = parseFloat(form.dataset.minAmount) || 1;
            
            // Manejar selección de montos predefinidos
            amountOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Quitar selección previa
                    amountOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Seleccionar nuevo monto
                    this.classList.add('selected');
                    selectedAmount = parseFloat(this.dataset.amount);
                    
                    // Limpiar cantidad personalizada
                    if (customAmountInput) {
                        customAmountInput.value = '';
                    }
                    
                    updateButton();
                });
            });
            
            // Manejar cantidad personalizada
            if (customAmountInput) {
                customAmountInput.addEventListener('input', function() {
                    // Quitar selección de montos predefinidos
                    amountOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    selectedAmount = parseFloat(this.value) || 0;
                    updateButton();
                });
            }
            
            function updateButton() {
                const buttonText = container.querySelector('.button-text');
                if (selectedAmount >= minAmount) {
                    submitButton.disabled = false;
                    buttonText.textContent = `💖 Donar <?php echo esc_js($currency_symbol); ?>${selectedAmount.toFixed(2)}`;
                } else {
                    submitButton.disabled = true;
                    buttonText.textContent = '💖 Donar Ahora';
                }
            }
            
            function showError(message) {
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
                successDiv.style.display = 'none';
            }
            
            function showSuccess(message) {
                successDiv.textContent = message;
                successDiv.style.display = 'block';
                errorDiv.style.display = 'none';
            }
            
            // Manejar envío del formulario
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validar monto
                if (selectedAmount < minAmount) {
                    showError(`El monto mínimo es <?php echo esc_js($currency_symbol); ?>${minAmount}`);
                    return;
                }
                
                // Obtener datos del formulario
                const isRecurring = container.querySelector('input[name="is_recurring"]')?.checked || false;
                
                // Mostrar estado de carga
                submitButton.disabled = true;
                container.querySelector('.button-text').style.display = 'none';
                container.querySelector('.button-loading').style.display = 'inline';
                
                // Simular procesamiento (aquí irá la integración real con WooCommerce)
                setTimeout(() => {
                    // Redireccionar a checkout de WooCommerce
                    const checkoutUrl = '<?php echo esc_js(function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '/checkout/'); ?>';
                    
                    // Crear formulario oculto para enviar a checkout
                    const hiddenForm = document.createElement('form');
                    hiddenForm.method = 'POST';
                    hiddenForm.action = checkoutUrl;
                    
                    // Agregar campos
                    const fields = {
                        'donation_amount': selectedAmount,
                        'donation_recurring': isRecurring ? '1' : '0',
                        'donation_action': 'add_to_cart'
                    };
                    
                    for (const [key, value] of Object.entries(fields)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        hiddenForm.appendChild(input);
                    }
                    
                    document.body.appendChild(hiddenForm);
                    hiddenForm.submit();
                    
                }, 1000);
            });
        })();
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode del botón de donación
     */
    public function donation_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'amount' => '25',
            'text' => 'Donar'
        ), $atts);
        
        return '<button class="donation-button-simple" data-amount="' . esc_attr($atts['amount']) . '">' . 
               esc_html($atts['text']) . ' €' . $atts['amount'] . '</button>';
    }
    
    /**
     * Shortcode del widget de progreso
     */
    public function donation_progress_shortcode($atts) {
        $atts = shortcode_atts(array(
            'goal' => '5000',
            'current' => '0'
        ), $atts);
        
        $percentage = ($atts['current'] / $atts['goal']) * 100;
        
        return '<div class="donation-progress-widget">
            <h4>Meta de Donaciones</h4>
            <div class="progress-bar" style="background: #eee; border-radius: 10px; overflow: hidden;">
                <div class="progress-fill" style="width: ' . $percentage . '%; background: #4CAF50; height: 20px;"></div>
            </div>
            <p>€' . number_format($atts['current']) . ' / €' . number_format($atts['goal']) . '</p>
        </div>';
    }
    
    /**
     * Cargar archivos de idioma
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'donations-wc',
            false,
            dirname(DONATIONS_WC_PLUGIN_BASENAME) . '/languages'
        );
    }
}

// Verificar WooCommerce y inicializar
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Donaciones WooCommerce</strong> requiere que WooCommerce esté instalado y activado.</p></div>';
        });
        return;
    }
    
    // Inicializar el plugin
    DonationsWooCommerce::get_instance();
});

/**
 * Función de conveniencia para obtener configuraciones
 */
function donations_wc_get_option($key, $default = null) {
    return get_option('donations_wc_' . $key, $default);
}

/**
 * Función de conveniencia para obtener estadísticas
 */
function donations_wc_get_stats($period = 'all') {
    return array(
        'total_amount' => 1250.50,
        'total_donations' => 24,
        'recurring_donations' => 8,
        'average_donation' => 52.10
    );
}
?>