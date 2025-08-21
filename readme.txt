=== Donaciones para WooCommerce ===
Contributors: tu-usuario
Donate link: https://tu-web.com/donate
Tags: donations, woocommerce, paypal, stripe, fundacion, recurring, donaciones
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sistema completo de donaciones para fundaciones con WooCommerce. Donaciones únicas y recurrentes con PayPal, Stripe y transferencias.

== Description ==

**Donaciones para WooCommerce** es un plugin completo que permite a fundaciones, ONGs y organizaciones benéficas recibir donaciones a través de su sitio WordPress usando WooCommerce.

### 🎯 Características Principales

* **Donaciones únicas y recurrentes** - Los donantes pueden elegir entre donaciones de una sola vez o mensuales automáticas
* **Múltiples métodos de pago** - PayPal, Stripe (tarjetas de crédito) y transferencias bancarias
* **Formularios personalizables** - Montos predefinidos y cantidad personalizada
* **Panel de administración completo** - Configuración visual, reportes y gestión de donantes
* **Shortcodes flexibles** - Fácil integración en cualquier página o entrada
* **Responsive y accesible** - Funciona perfectamente en móviles y cumple estándares de accesibilidad

### 💰 Métodos de Pago Soportados

* **PayPal** - Pagos únicos y suscripciones automáticas
* **Stripe** - Tarjetas de crédito/débito con suscripciones
* **Transferencia Bancaria** - Para donaciones únicas

### 🛠️ Características Técnicas

* **Integración nativa con WooCommerce** - Aprovecha toda la potencia del checkout de WooCommerce
* **Base de datos propia** - Tracking independiente de donaciones para reportes detallados
* **Donaciones recurrentes SIN plugins premium** - Usa APIs nativas de PayPal y Stripe
* **Emails automáticos** - Confirmaciones personalizables para donantes
* **Exportación de datos** - CSV y Excel para contabilidad
* **Multiidioma** - Preparado para traducciones

### 📊 Reportes y Analytics

* Estadísticas en tiempo real
* Gráficos de tendencias mensuales
* Análisis por país y método de pago
* Top donantes y métricas de rendimiento
* Exportación completa de datos

### 🎨 Personalización

* **Colores personalizables** - Adapta el formulario a tu marca
* **Múltiples estilos** - Moderno, clásico o minimalista
* **Montos configurables** - Define tus propias cantidades sugeridas
* **Textos editables** - Personaliza todos los mensajes

### 📝 Shortcodes Disponibles

* `[donation_form]` - Formulario completo de donación
* `[donation_form style="simple"]` - Versión simplificada
* `[donation_button amount="25"]` - Botón directo de donación
* `[donation_progress goal="5000"]` - Barra de progreso hacia meta

### 🔧 Configuración Fácil

1. Instala y activa el plugin
2. Ve a "Donaciones" en tu admin de WordPress
3. Configura tu fundación y métodos de pago
4. Usa los shortcodes en tus páginas
5. ¡Empieza a recibir donaciones!

### 👥 Gestión de Donantes

* Lista completa con búsqueda y filtros
* Historial detallado por donante
* Envío de emails masivos
* Exportación de datos de donantes
* Gestión de suscripciones recurrentes

### 🚀 Por qué elegir este plugin

* **Gratuito y completo** - No necesitas plugins premium adicionales
* **Sin comisiones extra** - Solo pagas las comisiones normales de PayPal/Stripe
* **Código limpio** - Desarrollado siguiendo estándares de WordPress
* **Soporte profesional** - Documentación completa y soporte técnico
* **Actualizaciones regulares** - Mantenimiento continuo y nuevas características

### 🔒 Seguridad

* Nonces de seguridad en todos los formularios
* Validación y sanitización de datos
* Encriptación SSL requerida para pagos
* Cumple con estándares PCI DSS a través de PayPal y Stripe

### 🌍 Compatibilidad

* WordPress 5.0 o superior
* WooCommerce 5.0 o superior
* PHP 7.4 o superior
* Todos los temas modernos de WordPress

== Installation ==

### Instalación Automática

1. Ve a "Plugins" > "Añadir nuevo" en tu admin de WordPress
2. Busca "Donaciones para WooCommerce"
3. Haz clic en "Instalar ahora" y luego "Activar"
4. Ve a "Donaciones" > "Configuración" para configurar el plugin

### Instalación Manual

1. Descarga el archivo ZIP del plugin
2. Ve a "Plugins" > "Añadir nuevo" > "Subir plugin"
3. Selecciona el archivo ZIP y haz clic en "Instalar ahora"
4. Activa el plugin
5. Ve a "Donaciones" > "Configuración" para configurar

### Configuración Inicial

1. **Configura tu fundación**
   - Nombre de la organización
   - Descripción motivacional
   - Logo (opcional)

2. **Establece los montos**
   - Define 4 cantidades predefinidas
   - Configura el monto mínimo
   - Habilita cantidades personalizadas

3. **Configura métodos de pago**
   - PayPal: Configura en WooCommerce > Ajustes > Pagos
   - Stripe: Instala WooCommerce Stripe Gateway
   - Transferencias: Configura datos bancarios

4. **Personaliza emails**
   - Mensaje de agradecimiento
   - Notificaciones para administradores
   - Variables dinámicas disponibles

5. **Usa los shortcodes**
   - Copia los códigos desde "Donaciones" > "Shortcodes"
   - Pégalos en páginas, entradas o widgets

== Frequently Asked Questions ==

= ¿Necesito WooCommerce Subscriptions para donaciones recurrentes? =

No. Este plugin implementa donaciones recurrentes usando las APIs nativas de PayPal y Stripe, sin necesidad de plugins premium adicionales.

= ¿Qué comisiones se cobran? =

El plugin no cobra comisiones. Solo pagas las tarifas normales de PayPal (2.9% + 0.35€) y Stripe (1.4% + 0.25€ para tarjetas europeas).

= ¿Puedo personalizar el diseño del formulario? =

Sí. Puedes cambiar colores, estilos y textos desde el panel de administración. También puedes usar CSS personalizado para cambios más avanzados.

= ¿Los donantes reciben confirmación por email? =

Sí. Se envían emails automáticos de agradecimiento que puedes personalizar completamente, incluyendo variables como nombre del donante y cantidad.

= ¿Puedo exportar los datos de donaciones? =

Sí. El plugin incluye exportación completa a CSV y Excel, ideal para contabilidad y reportes financieros.

= ¿Funciona con mi tema de WordPress? =

Sí. El plugin está diseñado para funcionar con cualquier tema que cumpla estándares de WordPress. Los formularios son completamente responsive.

= ¿Puedo cancelar donaciones recurrentes? =

Los donantes pueden cancelar sus suscripciones directamente desde PayPal o contactando contigo. Tú puedes gestionar suscripciones desde el panel de admin.

= ¿El plugin cumple con GDPR? =

Sí. Solo recopila datos esenciales para procesar donaciones y incluye opciones para exportar y eliminar datos de donantes.

= ¿Puedo usar múltiples formularios con diferentes configuraciones? =

Sí. Puedes usar shortcodes con parámetros personalizados para crear formularios con diferentes montos, estilos y configuraciones.

= ¿Hay límite en el número de donaciones? =

No hay límites impuestos por el plugin. Los únicos límites son los de tu hosting y los métodos de pago que uses.

== Screenshots ==

1. Panel de administración principal con configuración visual
2. Formulario de donación completo en el frontend
3. Página de reportes con estadísticas y gráficos
4. Gestión de donantes con historial detallado
5. Configuración de métodos de pago
6. Shortcodes listos para copiar y pegar
7. Formulario responsive en dispositivos móviles
8. Widget de progreso hacia meta de donaciones

== Changelog ==

= 1.0.0 - 2024-01-20 =
* Lanzamiento inicial del plugin
* Formularios de donación completos
* Donaciones únicas y recurrentes
* Integración con PayPal y Stripe
* Panel de administración con configuración visual
* Sistema de reportes y estadísticas
* Gestión completa de donantes
* Shortcodes flexibles
* Exportación CSV/Excel
* Emails automáticos personalizables
* Responsive design y accesibilidad
* Multiidioma preparado

== Upgrade Notice ==

= 1.0.0 =
Primera versión del plugin. Instala para empezar a recibir donaciones inmediatamente.

== Shortcodes ==

### [donation_form]

Muestra el formulario completo de donación.

**Parámetros:**
* `style` - Estilo del formulario: "complete" (por defecto), "simple"
* `amounts` - Montos personalizados separados por comas: "10,25,50,100"
* `title` - Título personalizado del formulario
* `description` - Descripción personalizada

**Ejemplos:**
```
[donation_form]
[donation_form style="simple" amounts="5,15,30"]
[donation_form title="Ayuda a los Niños" description="Tu donación alimenta a niños necesitados"]
```

### [donation_button]

Botón directo de donación con monto fijo.

**Parámetros:**
* `amount` - Cantidad fija para donar (obligatorio)
* `text` - Texto del botón (por defecto: "Donar €X")
* `style` - Estilo: "primary" (por defecto), "secondary", "outline", "minimal"
* `size` - Tamaño: "small", "medium" (por defecto), "large", "xl"
* `url` - URL personalizada (opcional)

**Ejemplos:**
```
[donation_button amount="25"]
[donation_button amount="50" text="Ayudar con €50" style="outline"]
[donation_button amount="100" size="large" style="secondary"]
```

### [donation_progress]

Widget de progreso hacia una meta de donaciones.

**Parámetros:**
* `goal` - Meta de donaciones (obligatorio)
* `current` - Cantidad actual recaudada (opcional, se calcula automáticamente)
* `title` - Título del widget (por defecto: "Meta de Donaciones")
* `show_percentage` - Mostrar porcentaje: "true" (por defecto), "false"
* `show_amounts` - Mostrar cantidades: "true" (por defecto), "false"
* `color` - Color de la barra de progreso (por defecto: color primario del plugin)
* `height` - Altura de la barra en píxeles (por defecto: 12)
* `animate` - Animación: "true" (por defecto), "false"
* `show_button` - Mostrar botón de donación: "true", "false" (por defecto)
* `button_text` - Texto del botón: "Donar Ahora" (por defecto)

**Ejemplos:**
```
[donation_progress goal="5000"]
[donation_progress goal="10000" title="Campaña de Navidad" show_button="true"]
[donation_progress goal="2500" current="1200" color="#e74c3c" height="20"]
```

== Configuración Avanzada ==

### Hooks para Desarrolladores

El plugin incluye múltiples hooks que permiten a desarrolladores extender su funcionalidad:

**Acciones (Actions):**
* `donations_wc_after_form` - Después del formulario de donación
* `donations_wc_after_button` - Después del botón de donación
* `donations_wc_after_progress` - Después del widget de progreso
* `donations_wc_donation_completed` - Cuando se completa una donación
* `donations_wc_subscription_created` - Cuando se crea una suscripción

**Filtros (Filters):**
* `donations_wc_form_settings` - Modificar configuraciones del formulario
* `donations_wc_payment_methods` - Filtrar métodos de pago disponibles
* `donations_wc_email_content` - Personalizar contenido de emails
* `donations_wc_export_data` - Modificar datos de exportación

### CSS Personalizado

Puedes añadir CSS personalizado en "Apariencia" > "Personalizar" > "CSS Adicional":

```css
/* Cambiar color del botón de donación */
.donate-button {
    background: #your-color !important;
}

/* Personalizar formulario */
.donations-wc-form-container {
    border: 2px solid #your-color;
    border-radius: 15px;
}

/* Ocultar métodos de pago específicos */
.payment-method[data-method="bacs"] {
    display: none;
}
```

### Variables CSS Disponibles

```css
:root {
    --donations-primary-color: #4CAF50;
    --donations-secondary-color: #45a049;
    --donations-background-color: #ffffff;
    --donations-text-color: #333333;
    --donations-border-color: #e1e8ed;
    --donations-border-radius: 8px;
    --donations-shadow: 0 4px 6px rgba(0,0,0,0.1);
    --donations-transition: all 0.3s ease;
}
```

### Integración con Analytics

El plugin incluye soporte automático para:
* Google Analytics 4
* Facebook Pixel
* Eventos personalizados de JavaScript

**Eventos disponibles:**
* `donation_form_loaded` - Formulario cargado
* `donation_amount_selected` - Monto seleccionado
* `donation_checkout_started` - Inicio del checkout
* `donation_completed` - Donación completada

== Soporte ==

### Documentación Completa
Visita [nuestra documentación](https://tu-web.com/docs) para guías detalladas y tutoriales.

### Soporte Técnico
- **Email:** soporte@tu-web.com
- **Foro:** [WordPress.org Support Forum](https://wordpress.org/support/plugin/donations-for-woocommerce)
- **Documentación:** https://tu-web.com/docs/donations-woocommerce

### Reportar Bugs
Si encuentras un error, repórtalo en [GitHub Issues](https://github.com/tu-usuario/donations-for-woocommerce/issues) con:
1. Versión de WordPress
2. Versión de WooCommerce
3. Versión del plugin
4. Descripción detallada del problema
5. Pasos para reproducir el error

### Solicitar Características
¿Tienes una idea para mejorar el plugin? Compártela en nuestro [formulario de sugerencias](https://tu-web.com/suggestions).

== Roadmap ==

### Próximas Características (v1.1.0)
- [ ] Integración con Bizum para España
- [ ] Donaciones por SMS
- [ ] Campañas de donación con fechas límite
- [ ] Integración con Mailchimp/Newsletter
- [ ] Dashboard de donante (frontend)
- [ ] Donaciones en memoria/honor de alguien
- [ ] Certificados de donación automáticos

### Características Futuras (v1.2.0+)
- [ ] Integración con redes sociales
- [ ] Gamificación (insignias, niveles)
- [ ] Donaciones por productos específicos
- [ ] Marketplace de causas
- [ ] App móvil companion
- [ ] Blockchain/crypto donations
- [ ] AI para optimización de conversiones

== Licencia ==

Este plugin está licenciado bajo GPL v2 o posterior. Eres libre de usar, modificar y distribuir este software de acuerdo con los términos de la Licencia Pública General GNU.

== Créditos ==

### Desarrollado por
[Tu Nombre](https://tu-web.com) - Desarrollo completo del plugin

### Librerías de Terceros
- Chart.js para gráficos de reportes
- WordPress Color Picker API
- WooCommerce REST API

### Agradecimientos
Gracias a la comunidad de WordPress y WooCommerce por hacer posible este proyecto.

---

**¿Te gusta este plugin?** ⭐ ¡Deja una reseña de 5 estrellas en WordPress.org!