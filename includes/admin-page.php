<?php
/**
 * TEST FINAL DEFINITIVO - LOGGING EXTREMO
 */

// Prevenir acceso directo
if ( !defined( 'ABSPATH' ) ) {
  exit;
}

// LOGGING EXTREMO - REGISTRAR TODO
error_log('=== DONATIONS DEBUG START ===');
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
error_log('USER_AGENT: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));

// Verificar permisos
if ( !current_user_can( 'manage_options' ) ) {
  error_log('DONATIONS: Usuario sin permisos');
  wp_die( 'Sin permisos' );
}

error_log('DONATIONS: Usuario con permisos OK');

// DETECTAR SI ES POST
$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
error_log('DONATIONS: Es POST? ' . ($is_post ? 'SÍ' : 'NO'));

if ($is_post) {
    error_log('DONATIONS: Datos POST recibidos: ' . print_r($_POST, true));
}

// Variables para el mensaje
$message = '';
$message_type = 'info';

// PROCESAR GUARDADO CON LOGGING EXTREMO
if ($is_post && isset($_POST['donations_save'])) {
    error_log('DONATIONS: ¡¡¡BOTÓN DE GUARDADO DETECTADO!!!');
    
    // Verificar nonce
    $nonce_field = $_POST['donations_nonce'] ?? 'NO_PRESENTE';
    error_log('DONATIONS: Nonce recibido: ' . $nonce_field);
    
    $nonce_valid = wp_verify_nonce($nonce_field, 'donations_save_action');
    error_log('DONATIONS: Nonce válido? ' . ($nonce_valid ? 'SÍ' : 'NO'));
    
    if (!$nonce_valid) {
        $message = '❌ Error de seguridad (nonce inválido)';
        $message_type = 'error';
        error_log('DONATIONS: ERROR - Nonce inválido');
    } else {
        error_log('DONATIONS: Procediendo con el guardado...');
        
        // TEST SIMPLE: Guardar solo el nombre de la fundación
        $foundation_name = sanitize_text_field($_POST['foundation_name'] ?? '');
        error_log('DONATIONS: Foundation name a guardar: "' . $foundation_name . '"');
        
        if (empty($foundation_name)) {
            $message = '❌ El nombre de la fundación no puede estar vacío';
            $message_type = 'error';
            error_log('DONATIONS: ERROR - Nombre vacío');
        } else {
            // Intentar guardar
            error_log('DONATIONS: Intentando update_option...');
            $result = update_option('donations_wc_foundation_name', $foundation_name);
            error_log('DONATIONS: Resultado update_option: ' . ($result ? 'TRUE' : 'FALSE'));
            
            // Verificar que se guardó
            $verification = get_option('donations_wc_foundation_name');
            error_log('DONATIONS: Valor recuperado: "' . $verification . '"');
            
            if ($verification === $foundation_name) {
                $message = '✅ ¡ÉXITO! Guardado: ' . $foundation_name;
                $message_type = 'success';
                error_log('DONATIONS: ¡¡¡GUARDADO EXITOSO!!!');
            } else {
                $message = '❌ Error: No se verificó el guardado';
                $message_type = 'error';
                error_log('DONATIONS: ERROR - No se verificó el guardado');
            }
        }
    }
} else if ($is_post) {
    error_log('DONATIONS: Es POST pero NO hay botón donations_save');
    error_log('DONATIONS: Campos POST disponibles: ' . implode(', ', array_keys($_POST)));
}

// Obtener valor actual
$current_name = get_option('donations_wc_foundation_name', 'Mi Fundación');
error_log('DONATIONS: Valor actual en BD: "' . $current_name . '"');

error_log('=== DONATIONS DEBUG END ===');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Final - Donaciones</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        .message { padding: 15px; margin: 20px 0; border-radius: 4px; font-weight: bold; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-section { background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .button { background: #0073aa; color: white; padding: 15px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .button:hover { background: #005a87; }
        .debug-info { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 4px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 4px; font-weight: bold; }
        .status.ok { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔬 Test Final - Plugin Donaciones</h1>
    
    <!-- INFORMACIÓN DE DEBUG -->
    <div class="debug-info">
        <h3>📊 Estado Actual</h3>
        <p><strong>Método de Request:</strong> <span class="status <?php echo $is_post ? 'ok' : 'error'; ?>"><?php echo $_SERVER['REQUEST_METHOD']; ?></span></p>
        <p><strong>Datos POST:</strong> <span class="status <?php echo $is_post ? 'ok' : 'error'; ?>"><?php echo $is_post ? 'Presentes (' . count($_POST) . ')' : 'No presentes'; ?></span></p>
        <p><strong>Usuario:</strong> <span class="status ok"><?php echo wp_get_current_user()->user_login; ?></span></p>
        <p><strong>Permisos:</strong> <span class="status ok">manage_options</span></p>
        <p><strong>Valor en BD:</strong> <code><?php echo esc_html($current_name); ?></code></p>
        
        <?php if ($is_post): ?>
        <details style="margin-top: 15px;">
            <summary><strong>Ver datos POST</strong></summary>
            <pre style="background: #f4f4f4; padding: 10px; border-radius: 4px; margin-top: 10px;"><?php print_r($_POST); ?></pre>
        </details>
        <?php endif; ?>
    </div>
    
    <!-- MENSAJE DE RESULTADO -->
    <?php if (!empty($message)): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo esc_html($message); ?>
    </div>
    <?php endif; ?>
    
    <!-- FORMULARIO ULTRA SIMPLE -->
    <div class="form-section">
        <h2>📝 Test de Guardado Ultra Simple</h2>
        <p>Este test guarda SOLO el nombre de la fundación para verificar que el mecanismo básico funciona.</p>
        
        <form method="post" action="" id="testForm">
            <?php wp_nonce_field('donations_save_action', 'donations_nonce'); ?>
            
            <p>
                <label for="foundation_name"><strong>Nombre de la Fundación:</strong></label><br>
                <input type="text" id="foundation_name" name="foundation_name" 
                       value="<?php echo esc_attr($current_name); ?>" 
                       placeholder="Introduce el nombre de tu fundación" 
                       required />
            </p>
            
            <p style="text-align: center; margin-top: 30px;">
                <input type="submit" name="donations_save" value="🧪 GUARDAR SOLO ESTE CAMPO" class="button" id="submitBtn" />
            </p>
        </form>
    </div>
    
    <!-- INSTRUCCIONES -->
    <div style="background: #e3f2fd; border: 1px solid #2196F3; padding: 20px; border-radius: 4px;">
        <h3>📋 Instrucciones del Test</h3>
        <ol>
            <li><strong>Cambia el nombre</strong> en el campo de arriba</li>
            <li><strong>Haz clic en "GUARDAR SOLO ESTE CAMPO"</strong></li>
            <li><strong>Observa si aparece mensaje de éxito</strong></li>
            <li><strong>Recarga la página</strong> y verifica si el valor se mantiene</li>
            <li><strong>Revisa el debug.log</strong> para ver los logs detallados</li>
        </ol>
        
        <p><strong>Si este test simple NO funciona, entonces hay un problema fundamental con:</strong></p>
        <ul>
            <li>La configuración del servidor</li>
            <li>Conflictos con otros plugins</li>
            <li>Redirecciones automáticas</li>
            <li>Problemas de caché</li>
        </ul>
    </div>
    
    <!-- TEST DE CONECTIVIDAD -->
    <div style="background: #f0f8ff; border: 1px solid #0073aa; padding: 20px; border-radius: 4px; margin-top: 20px;">
        <h3>🌐 Test de Conectividad</h3>
        <p><strong>URL actual:</strong> <code><?php echo esc_html($_SERVER['REQUEST_URI']); ?></code></p>
        <p><strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
    </div>
    
</div>

<!-- JAVASCRIPT MÍNIMO PARA DEBUG -->
<script>
console.log('=== DONATIONS JS DEBUG ===');
console.log('Página cargada:', new Date());
console.log('jQuery disponible:', typeof jQuery !== 'undefined');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM cargado');
    
    const form = document.getElementById('testForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form && submitBtn) {
        console.log('Formulario y botón encontrados');
        
        // Debug del envío
        form.addEventListener('submit', function(e) {
            console.log('=== FORMULARIO ENVIÁNDOSE ===');
            console.log('Datos del formulario:', new FormData(this));
            console.log('Action:', this.action);
            console.log('Method:', this.method);
            
            // Cambiar botón para evitar doble envío
            submitBtn.disabled = true;
            submitBtn.value = 'Guardando...';
            
            // NO prevenir el envío - dejar que se envíe normalmente
            return true;
        });
        
        // Debug de click
        submitBtn.addEventListener('click', function(e) {
            console.log('=== BOTÓN CLICKEADO ===');
            console.log('Botón:', this);
            console.log('Formulario válido:', form.checkValidity());
        });
    } else {
        console.error('FORM O BUTTON NO ENCONTRADOS');
    }
});

// Debug de errores
window.addEventListener('error', function(e) {
    console.error('ERROR JS:', e.error);
});
</script>

</body>
</html>

<?php
// Log final
error_log('DONATIONS: Página renderizada completamente');
?>