/**
 * JavaScript Frontend para Donaciones WooCommerce
 * 
 * @package DonationsWC
 */

(function($) {
    'use strict';

    /**
     * Clase principal para manejar el formulario de donaciones
     */
    class DonationsWCForm {
        constructor(container) {
            this.container = $(container);
            this.form = this.container.find('form');
            this.settings = window.donationsWC || {};
            
            // Elementos del formulario
            this.elements = {
                frequencyInputs: this.form.find('input[name="donation_frequency"]'),
                amountInputs: this.form.find('input[name="preset_amount"]'),
                customAmountInput: this.form.find('#custom_amount'),
                paymentMethodInputs: this.form.find('input[name="payment_method"]'),
                requiredFields: this.form.find('input[required], select[required]'),
                submitButton: this.form.find('.donate-button'),
                
                // Elementos del resumen
                summaryType: this.form.find('#summary-type'),
                summaryAmount: this.form.find('#summary-amount'),
                summaryMethod: this.form.find('#summary-method'),
                summaryTotal: this.form.find('#summary-total'),
                finalAmountInput: this.form.find('#final_amount'),
                
                // Notas y mensajes
                recurringNote: this.form.find('.recurring-note'),
                errorMessage: this.form.find('.error-message')
            };
            
            this.init();
        }

        /**
         * Inicializar el formulario
         */
        init() {
            this.bindEvents();
            this.updateSummary();
            this.validateForm();
            this.applyCustomStyles();
            
            // Auto-focus en el primer campo si es accesible
            if (this.isAccessibleDevice()) {
                this.elements.requiredFields.first().focus();
            }
            
            console.log('Donations WC Form initialized');
        }

        /**
         * Vincular eventos
         */
        bindEvents() {
            // Cambios en frecuencia de donación
            this.elements.frequencyInputs.on('change', () => {
                this.handleFrequencyChange();
                this.updateSummary();
            });

            // Cambios en montos predefinidos
            this.elements.amountInputs.on('change', () => {
                if (this.getSelectedAmount()) {
                    this.elements.customAmountInput.val('');
                    this.clearFieldError(this.elements.customAmountInput);
                }
                this.updateSummary();
            });

            // Cambios en cantidad personalizada
            this.elements.customAmountInput.on('input', () => {
                if (this.elements.customAmountInput.val()) {
                    this.elements.amountInputs.prop('checked', false);
                }
                this.updateSummary();
                this.validateAmount();
            });

            // Cambios en método de pago
            this.elements.paymentMethodInputs.on('change', () => {
                this.handlePaymentMethodChange();
                this.updateSummary();
            });

            // Validación en tiempo real para campos requeridos
            this.elements.requiredFields.on('input change', () => {
                this.validateField($(event.target));
                this.validateForm();
            });

            // Envío del formulario
            this.form.on('submit', (e) => this.handleSubmit(e));

            // Teclas de acceso rápido
            $(document).on('keydown', (e) => this.handleKeyboardShortcuts(e));
        }

        /**
         * Manejar cambio de frecuencia
         */
        handleFrequencyChange() {
            const frequency = this.getSelectedFrequency();
            
            // Mostrar/ocultar nota de donación recurrente
            if (frequency === 'monthly') {
                this.elements.recurringNote.addClass('show');
                this.hidePaymentMethod('bacs'); // Ocultar transferencia bancaria para recurrentes
            } else {
                this.elements.recurringNote.removeClass('show');
                this.showPaymentMethod('bacs');
            }

            // Actualizar texto de ayuda
            this.updateHelpText();
        }

        /**
         * Manejar cambio de método de pago
         */
        handlePaymentMethodChange() {
            const frequency = this.getSelectedFrequency();
            const paymentMethod = this.getSelectedPaymentMethod();
            
            // Validar compatibilidad
            if (frequency === 'monthly' && paymentMethod === 'bacs') {
                this.showError('La transferencia bancaria no está disponible para donaciones mensuales.');
                this.selectPaymentMethod('paypal');
            }
        }

        /**
         * Actualizar resumen
         */
        updateSummary() {
            const frequency = this.getSelectedFrequency();
            const amount = this.getFinalAmount();
            const paymentMethod = this.getSelectedPaymentMethod();
            
            // Actualizar tipo de donación
            const typeText = frequency === 'monthly' ? 'Mensual' : 'Una vez';
            this.elements.summaryType.text(typeText);
            
            // Actualizar método de pago
            const methodLabels = {
                'paypal': 'PayPal',
                'stripe': 'Tarjeta',
                'bacs': 'Transferencia'
            };
            this.elements.summaryMethod.text(methodLabels[paymentMethod] || 'PayPal');
            
            // Actualizar montos
            const formattedAmount = this.formatCurrency(amount);
            this.elements.summaryAmount.text(formattedAmount);
            this.elements.summaryTotal.text(formattedAmount);
            this.elements.finalAmountInput.val(amount);
            
            // Actualizar botón de envío
            this.updateSubmitButton(amount, frequency);
        }

        /**
         * Actualizar botón de envío
         */
        updateSubmitButton(amount, frequency) {
            let buttonText = 'Completar Donación';
            
            if (amount > 0) {
                const formattedAmount = this.formatCurrency(amount);
                if (frequency === 'monthly') {
                    buttonText = `Donar ${formattedAmount} al mes`;
                } else {
                    buttonText = `Donar ${formattedAmount}`;
                }
            } else {
                buttonText = 'Selecciona una cantidad';
            }
            
            this.elements.submitButton.text(buttonText);
        }

        /**
         * Validar formulario completo
         */
        validateForm() {
            const amount = this.getFinalAmount();
            const minAmount = parseFloat(this.settings.min_amount || 1);
            
            let isValid = amount >= minAmount;
            
            // Validar campos requeridos
            this.elements.requiredFields.each((index, field) => {
                const $field = $(field);
                if (!this.validateField($field)) {
                    isValid = false;
                }
            });
            
            // Validar email específicamente
            const emailField = this.form.find('input[type="email"]');
            if (emailField.length && !this.isValidEmail(emailField.val())) {
                isValid = false;
            }
            
            // Habilitar/deshabilitar botón
            this.elements.submitButton.prop('disabled', !isValid);
            
            return isValid;
        }

        /**
         * Validar campo individual
         */
        validateField($field) {
            const value = $field.val().trim();
            const fieldType = $field.attr('type');
            const isRequired = $field.attr('required') !== undefined;
            
            let isValid = true;
            let errorMessage = '';
            
            // Validar campos requeridos
            if (isRequired && !value) {
                isValid = false;
                errorMessage = 'Este campo es obligatorio';
            }
            
            // Validar email
            if (fieldType === 'email' && value && !this.isValidEmail(value)) {
                isValid = false;
                errorMessage = 'Por favor ingresa un email válido';
            }
            
            // Mostrar/ocultar error
            if (isValid) {
                this.clearFieldError($field);
                this.showFieldSuccess($field);
            } else {
                this.showFieldError($field, errorMessage);
            }
            
            return isValid;
        }

        /**
         * Validar cantidad
         */
        validateAmount() {
            const amount = this.getFinalAmount();
            const minAmount = parseFloat(this.settings.min_amount || 1);
            
            if (amount > 0 && amount < minAmount) {
                this.showFieldError(
                    this.elements.customAmountInput, 
                    `El monto mínimo es ${this.formatCurrency(minAmount)}`
                );
                return false;
            }
            
            this.clearFieldError(this.elements.customAmountInput);
            return true;
        }

        /**
         * Manejar envío del formulario
         */
        handleSubmit(e) {
            e.preventDefault();
            
            if (!this.validateForm()) {
                this.showError('Por favor corrige los errores antes de continuar.');
                return;
            }
            
            const formData = this.collectFormData();
            
            this.setLoading(true);
            this.hideError();
            
            // Enviar datos via AJAX
            $.ajax({
                url: this.settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'process_donation_form',
                    ...formData,
                    nonce: this.settings.nonce
                },
                success: (response) => this.handleSubmitSuccess(response),
                error: () => this.handleSubmitError(),
                complete: () => this.setLoading(false)
            });
        }

        /**
         * Recopilar datos del formulario
         */
        collectFormData() {
            const formData = {};
            
            // Datos básicos
            formData.donation_amount = this.getFinalAmount();
            formData.donation_frequency = this.getSelectedFrequency();
            formData.payment_method = this.getSelectedPaymentMethod();
            
            // Datos del donante
            this.form.find('input, select, textarea').each((index, field) => {
                const $field = $(field);
                const name = $field.attr('name');
                
                if (name && !name.startsWith('preset_') && name !== 'custom_amount') {
                    formData[name] = $field.val();
                }
            });
            
            return formData;
        }

        /**
         * Manejar éxito en el envío
         */
        handleSubmitSuccess(response) {
            if (response.success) {
                // Redirigir al checkout
                if (response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    this.showSuccess('¡Gracias por tu donación!');
                }
            } else {
                this.showError(response.data.message || 'Error al procesar la donación.');
            }
        }

        /**
         * Manejar error en el envío
         */
        handleSubmitError() {
            this.showError('Error de conexión. Por favor intenta de nuevo.');
        }

        /**
         * Obtener frecuencia seleccionada
         */
        getSelectedFrequency() {
            return this.elements.frequencyInputs.filter(':checked').val() || 'once';
        }

        /**
         * Obtener monto seleccionado
         */
        getSelectedAmount() {
            const checkedAmount = this.elements.amountInputs.filter(':checked').val();
            return checkedAmount ? parseFloat(checkedAmount) : 0;
        }

        /**
         * Obtener monto final (predefinido o personalizado)
         */
        getFinalAmount() {
            const customAmount = parseFloat(this.elements.customAmountInput.val()) || 0;
            const selectedAmount = this.getSelectedAmount();
            
            return customAmount > 0 ? customAmount : selectedAmount;
        }

        /**
         * Obtener método de pago seleccionado
         */
        getSelectedPaymentMethod() {
            return this.elements.paymentMethodInputs.filter(':checked').val() || 'paypal';
        }

        /**
         * Formatear moneda
         */
        formatCurrency(amount) {
            const symbol = this.settings.currency_symbol || '€';
            return symbol + parseFloat(amount).toFixed(2);
        }

        /**
         * Validar email
         */
        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        /**
         * Mostrar/ocultar método de pago
         */
        hidePaymentMethod(method) {
            this.form.find(`input[value="${method}"]`).closest('.payment-method').hide();
        }

        showPaymentMethod(method) {
            this.form.find(`input[value="${method}"]`).closest('.payment-method').show();
        }

        /**
         * Seleccionar método de pago
         */
        selectPaymentMethod(method) {
            this.form.find(`input[value="${method}"]`).prop('checked', true);
        }

        /**
         * Mostrar error global
         */
        showError(message) {
            this.elements.errorMessage.text(message).addClass('show');
            this.scrollToError();
        }

        /**
         * Ocultar error global
         */
        hideError() {
            this.elements.errorMessage.removeClass('show');
        }

        /**
         * Mostrar éxito
         */
        showSuccess(message) {
            // Crear elemento de éxito si no existe
            let successMessage = this.form.find('.success-message');
            if (!successMessage.length) {
                successMessage = $('<div class="success-message"></div>');
                this.elements.errorMessage.after(successMessage);
            }
            
            successMessage.text(message).show();
            this.scrollToTop();
        }

        /**
         * Mostrar error en campo específico
         */
        showFieldError($field, message) {
            $field.closest('.form-group').addClass('error').removeClass('success');
            
            let errorElement = $field.siblings('.field-error');
            if (!errorElement.length) {
                errorElement = $('<div class="field-error"></div>');
                $field.after(errorElement);
            }
            
            errorElement.text(message).addClass('show');
        }

        /**
         * Limpiar error de campo
         */
        clearFieldError($field) {
            $field.closest('.form-group').removeClass('error');
            $field.siblings('.field-error').removeClass('show');
        }

        /**
         * Mostrar éxito en campo
         */
        showFieldSuccess($field) {
            if ($field.val().trim()) {
                $field.closest('.form-group').addClass('success').removeClass('error');
            }
        }

        /**
         * Establecer estado de carga
         */
        setLoading(loading) {
            if (loading) {
                this.elements.submitButton.prop('disabled', true).addClass('loading');
                this.form.addClass('loading');
            } else {
                this.elements.submitButton.removeClass('loading');
                this.form.removeClass('loading');
                this.validateForm(); // Re-validar para habilitar botón si es necesario
            }
        }

        /**
         * Aplicar estilos personalizados desde configuración
         */
        applyCustomStyles() {
            if (this.settings.primary_color) {
                document.documentElement.style.setProperty('--donations-primary-color', this.settings.primary_color);
            }
            
            if (this.settings.background_color) {
                document.documentElement.style.setProperty('--donations-background-color', this.settings.background_color);
            }
            
            if (this.settings.form_style) {
                this.container.addClass(`style-${this.settings.form_style}`);
            }
        }

        /**
         * Actualizar texto de ayuda
         */
        updateHelpText() {
            const frequency = this.getSelectedFrequency();
            
            // Actualizar tooltips y textos de ayuda según frecuencia
            this.form.find('.help-text').each((index, element) => {
                const $element = $(element);
                const singleText = $element.data('single-text');
                const recurringText = $element.data('recurring-text');
                
                if (frequency === 'monthly' && recurringText) {
                    $element.text(recurringText);
                } else if (singleText) {
                    $element.text(singleText);
                }
            });
        }

        /**
         * Manejar atajos de teclado
         */
        handleKeyboardShortcuts(e) {
            // Esc para limpiar selección
            if (e.keyCode === 27) {
                this.elements.amountInputs.prop('checked', false);
                this.elements.customAmountInput.val('');
                this.updateSummary();
            }
            
            // Enter en campos de cantidad para continuar
            if (e.keyCode === 13 && $(e.target).is(this.elements.customAmountInput)) {
                e.preventDefault();
                this.elements.requiredFields.first().focus();
            }
        }

        /**
         * Detectar si es un dispositivo accesible
         */
        isAccessibleDevice() {
            return !('ontouchstart' in window);
        }

        /**
         * Scroll al error
         */
        scrollToError() {
            this.elements.errorMessage[0].scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }

        /**
         * Scroll al inicio
         */
        scrollToTop() {
            this.container[0].scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }
    }

    /**
     * Clase para el widget de progreso
     */
    class DonationProgressWidget {
        constructor(container) {
            this.container = $(container);
            this.goal = parseFloat(this.container.data('goal')) || 0;
            this.current = parseFloat(this.container.data('current')) || 0;
            
            this.init();
        }

        init() {
            this.updateProgress();
            this.animateProgress();
        }

        updateProgress() {
            const percentage = this.goal > 0 ? Math.min((this.current / this.goal) * 100, 100) : 0;
            
            this.container.find('.progress-fill').css('width', percentage + '%');
            this.container.find('.progress-percentage').text(Math.round(percentage) + '%');
            this.container.find('.progress-current').text(this.formatCurrency(this.current));
            this.container.find('.progress-goal').text(this.formatCurrency(this.goal));
        }

        animateProgress() {
            const progressFill = this.container.find('.progress-fill');
            const targetWidth = progressFill.css('width');
            
            progressFill.css('width', '0%');
            
            setTimeout(() => {
                progressFill.css('width', targetWidth);
            }, 500);
        }

        formatCurrency(amount) {
            const symbol = window.donationsWC?.currency_symbol || '€';
            return symbol + parseFloat(amount).toLocaleString();
        }
    }

    /**
     * Inicialización cuando el DOM esté listo
     */
    $(document).ready(function() {
        // Inicializar formularios de donación
        $('.donations-wc-form-container').each(function() {
            new DonationsWCForm(this);
        });

        // Inicializar widgets de progreso
        $('.donation-progress-widget').each(function() {
            new DonationProgressWidget(this);
        });

        // Manejar botones de donación simples
        $('.donation-button-simple').on('click', function(e) {
            e.preventDefault();
            
            const amount = $(this).data('amount');
            const url = $(this).attr('href');
            
            if (amount && window.donationsWC) {
                // Agregar cantidad al carrito y redirigir
                $.post(window.donationsWC.ajax_url, {
                    action: 'quick_donation',
                    amount: amount,
                    nonce: window.donationsWC.nonce
                }, function(response) {
                    if (response.success && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        alert('Error al procesar la donación rápida');
                    }
                });
            } else {
                // Fallback: ir a la URL directamente
                window.location.href = url;
            }
        });

        // Efecto de hover para botones de donación
        $('.donation-button-simple, .donate-button').hover(
            function() {
                $(this).addClass('hover-effect');
            },
            function() {
                $(this).removeClass('hover-effect');
            }
        );

        // Lazy loading para widgets de progreso
        if ('IntersectionObserver' in window) {
            const progressObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const widget = new DonationProgressWidget(entry.target);
                        progressObserver.unobserve(entry.target);
                    }
                });
            });

            $('.donation-progress-widget').each(function() {
                progressObserver.observe(this);
            });
        }

        // Accessibility improvements
        setupAccessibility();
        
        // Analytics tracking
        setupAnalytics();
    });

    /**
     * Configurar mejoras de accesibilidad
     */
    function setupAccessibility() {
        // Mejorar navegación por teclado
        $('.amount-option input, .payment-method input, .frequency-toggle input').on('keydown', function(e) {
            const $this = $(this);
            const $options = $this.closest('.amount-grid, .payment-methods, .frequency-toggle').find('input[type="radio"]');
            const currentIndex = $options.index(this);
            
            let newIndex = currentIndex;
            
            switch(e.keyCode) {
                case 37: // Left arrow
                case 38: // Up arrow
                    newIndex = currentIndex > 0 ? currentIndex - 1 : $options.length - 1;
                    e.preventDefault();
                    break;
                case 39: // Right arrow
                case 40: // Down arrow
                    newIndex = currentIndex < $options.length - 1 ? currentIndex + 1 : 0;
                    e.preventDefault();
                    break;
                case 32: // Space
                    $this.prop('checked', true).trigger('change');
                    e.preventDefault();
                    break;
            }
            
            if (newIndex !== currentIndex) {
                $options.eq(newIndex).focus();
            }
        });

        // Anuncios para lectores de pantalla
        $('.amount-option input').on('change', function() {
            const amount = $(this).val();
            const announcement = `Monto seleccionado: ${window.donationsWC?.currency_symbol || '€'}${amount}`;
            announceToScreenReader(announcement);
        });

        $('.frequency-toggle input').on('change', function() {
            const frequency = $(this).val() === 'monthly' ? 'mensual' : 'única';
            const announcement = `Donación ${frequency} seleccionada`;
            announceToScreenReader(announcement);
        });
    }

    /**
     * Anunciar texto a lectores de pantalla
     */
    function announceToScreenReader(text) {
        const announcement = $('<div>')
            .attr('aria-live', 'polite')
            .attr('aria-atomic', 'true')
            .addClass('sr-only')
            .text(text);
            
        $('body').append(announcement);
        
        setTimeout(() => {
            announcement.remove();
        }, 1000);
    }

    /**
     * Configurar tracking de analytics
     */
    function setupAnalytics() {
        // Google Analytics / GTM tracking
        $('.donate-button').on('click', function() {
            const amount = $(this).closest('form').find('#final_amount').val();
            const frequency = $(this).closest('form').find('input[name="donation_frequency"]:checked').val();
            
            // Google Analytics 4
            if (typeof gtag !== 'undefined') {
                gtag('event', 'begin_checkout', {
                    currency: 'EUR',
                    value: parseFloat(amount),
                    custom_parameters: {
                        donation_frequency: frequency
                    }
                });
            }
            
            // Facebook Pixel
            if (typeof fbq !== 'undefined') {
                fbq('track', 'InitiateCheckout', {
                    value: parseFloat(amount),
                    currency: 'EUR',
                    content_category: 'donation'
                });
            }
            
            // Custom event for other analytics
            $(document).trigger('donation_checkout_started', {
                amount: amount,
                frequency: frequency
            });
        });

        // Track amount selection
        $('.amount-option input').on('change', function() {
            const amount = $(this).val();
            
            if (typeof gtag !== 'undefined') {
                gtag('event', 'donation_amount_selected', {
                    custom_parameters: {
                        amount: amount,
                        selection_type: 'preset'
                    }
                });
            }
        });

        // Track custom amount entry
        $('#custom_amount').on('blur', function() {
            const amount = $(this).val();
            
            if (amount && typeof gtag !== 'undefined') {
                gtag('event', 'donation_amount_selected', {
                    custom_parameters: {
                        amount: amount,
                        selection_type: 'custom'
                    }
                });
            }
        });
    }

    /**
     * Utilidades globales
     */
    window.DonationsWC = window.DonationsWC || {};
    
    window.DonationsWC.refreshForm = function(containerId) {
        const container = $('#' + containerId);
        if (container.length) {
            new DonationsWCForm(container);
        }
    };
    
    window.DonationsWC.setAmount = function(amount) {
        $('.donations-wc-form-container').each(function() {
            const customInput = $(this).find('#custom_amount');
            const amountInputs = $(this).find('input[name="preset_amount"]');
            
            // Buscar si hay un botón predefinido con este monto
            const matchingInput = amountInputs.filter(`[value="${amount}"]`);
            
            if (matchingInput.length) {
                matchingInput.prop('checked', true).trigger('change');
            } else {
                amountInputs.prop('checked', false);
                customInput.val(amount).trigger('input');
            }
        });
    };
    
    window.DonationsWC.setFrequency = function(frequency) {
        $(`.donations-wc-form-container input[name="donation_frequency"][value="${frequency}"]`)
            .prop('checked', true)
            .trigger('change');
    };

    /**
     * Integración con WooCommerce
     */
    $(document.body).on('updated_wc_div', function() {
        // Reinicializar formularios después de actualizaciones de WooCommerce
        $('.donations-wc-form-container').each(function() {
            if (!$(this).data('donations-initialized')) {
                new DonationsWCForm(this);
                $(this).data('donations-initialized', true);
            }
        });
    });

    /**
     * Prevenir envío accidental del formulario
     */
    $(document).on('keypress', '.donations-wc-form-container input', function(e) {
        if (e.which === 13 && !$(this).is('textarea, input[type="submit"]')) {
            e.preventDefault();
            
            // Si es el último campo requerido, enfocar el botón de envío
            const requiredFields = $(this).closest('form').find('input[required], select[required]');
            const currentIndex = requiredFields.index(this);
            
            if (currentIndex === requiredFields.length - 1) {
                $(this).closest('form').find('.donate-button').focus();
            } else {
                requiredFields.eq(currentIndex + 1).focus();
            }
        }
    });

    /**
     * Manejo de errores global
     */
    window.addEventListener('error', function(e) {
        if (e.filename && e.filename.includes('donations') && window.console) {
            console.error('Donations WC Error:', e.message, e.filename, e.lineno);
        }
    });

    /**
     * Soporte para modo offline
     */
    if ('serviceWorker' in navigator) {
        window.addEventListener('online', function() {
            $('.donations-wc-form-container .offline-notice').fadeOut();
        });

        window.addEventListener('offline', function() {
            const notice = $('<div class="offline-notice" style="background: #f39c12; color: white; padding: 10px; text-align: center; position: fixed; top: 0; left: 0; right: 0; z-index: 9999;">Sin conexión a internet. Las donaciones no se pueden procesar en este momento.</div>');
            $('body').prepend(notice);
        });
    }

    /**
     * Optimizaciones de rendimiento
     */
    
    // Debounce para validación en tiempo real
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Throttle para eventos de scroll
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // Lazy loading de imágenes
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }

    /**
     * Soporte para PWA
     */
    if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
        navigator.serviceWorker.register('/donations-sw.js')
            .then(registration => {
                console.log('Donations SW registered:', registration);
            })
            .catch(error => {
                console.log('Donations SW registration failed:', error);
            });
    }

})(jQuery);

/**
 * Polyfills para navegadores antiguos
 */
if (!Element.prototype.closest) {
    Element.prototype.closest = function(s) {
        var el = this;
        do {
            if (el.matches(s)) return el;
            el = el.parentElement || el.parentNode;
        } while (el !== null && el.nodeType === 1);
        return null;
    };
}

if (!Element.prototype.matches) {
    Element.prototype.matches = Element.prototype.msMatchesSelector || 
                                Element.prototype.webkitMatchesSelector;
}

/**
 * Exportar para uso en módulos
 */
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { DonationsWCForm, DonationProgressWidget };
}