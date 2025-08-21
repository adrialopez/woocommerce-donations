/**
 * JavaScript Admin para Donaciones WooCommerce
 * 
 * @package DonationsWC
 */

(function($) {
    'use strict';

    /**
     * Clase principal para el admin del plugin
     */
    class DonationsWCAdmin {
        constructor() {
            this.settings = window.donationsWCAdmin || {};
            this.currentTab = 'general';
            this.hasUnsavedChanges = false;
            
            this.init();
        }

        /**
         * Inicializar funcionalidades del admin
         */
        init() {
            this.bindEvents();
            this.initializeTabs();
            this.initializeColorPickers();
            this.initializeTooltips();
            this.setupAutoSave();
            this.trackChanges();
            
            console.log('Donations WC Admin initialized');
        }

        /**
         * Vincular eventos
         */
        bindEvents() {
            // Navegación por tabs
            $('.nav-tab').on('click', (e) => this.handleTabClick(e));
            
            // Campos de configuración
            $('input, select, textarea').on('change input', () => this.handleFieldChange());
            
            // Color pickers
            $('.color-picker').on('change', () => this.updatePreview());
            
            // Toggles
            $('.toggle-switch').on('click', (e) => this.handleToggleClick(e));
            
            // Botones de copia
            $('.copy-btn').on('click', (e) => this.handleCopyClick(e));
            
            // Formulario principal
            $('.donations-settings-form').on('submit', (e) => this.handleFormSubmit(e));
            
            // Test de email
            $('#test-email-btn').on('click', () => this.sendTestEmail());
            
            // Reset de configuración
            $('#reset-settings').on('click', () => this.resetSettings());
            
            // Prevenir salir con cambios sin guardar
            $(window).on('beforeunload', () => this.handleBeforeUnload());
            
            // Teclas de acceso rápido
            $(document).on('keydown', (e) => this.handleKeyboardShortcuts(e));
        }

        /**
         * Inicializar sistema de tabs
         */
        initializeTabs() {
            // Restaurar tab activo desde localStorage
            const savedTab = localStorage.getItem('donations_wc_active_tab');
            if (savedTab && $(`.nav-tab[data-tab="${savedTab}"]`).length) {
                this.switchTab(savedTab);
            } else {
                this.switchTab('general');
            }
        }

        /**
         * Manejar clic en tab
         */
        handleTabClick(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const tabId = $tab.data('tab');
            
            if (tabId) {
                this.switchTab(tabId);
            }
        }

        /**
         * Cambiar tab activo
         */
        switchTab(tabId) {
            // Actualizar navegación
            $('.nav-tab').removeClass('nav-tab-active');
            $(`.nav-tab[data-tab="${tabId}"]`).addClass('nav-tab-active');
            
            // Mostrar contenido
            $('.tab-content').removeClass('active');
            $(`#tab-${tabId}`).addClass('active');
            
            // Guardar estado
            this.currentTab = tabId;
            localStorage.setItem('donations_wc_active_tab', tabId);
            
            // Ejecutar callbacks específicos del tab
            this.onTabSwitch(tabId);
        }

        /**
         * Callback al cambiar tab
         */
        onTabSwitch(tabId) {
            switch(tabId) {
                case 'appearance':
                    this.updatePreview();
                    break;
                case 'shortcodes':
                    this.highlightShortcodes();
                    break;
                case 'reports':
                    this.loadReportsData();
                    break;
            }
        }

        /**
         * Inicializar color pickers
         */
        initializeColorPickers() {
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker({
                    change: () => {
                        this.updatePreview();
                        this.hasUnsavedChanges = true;
                    },
                    clear: () => {
                        this.updatePreview();
                        this.hasUnsavedChanges = true;
                    }
                });
            }
        }

        /**
         * Actualizar vista previa
         */
        updatePreview() {
            const primaryColor = $('#primary_color').val() || '#4CAF50';
            const foundationName = $('#foundation_name').val() || 'Mi Fundación';
            const description = $('#foundation_description').val() || 'Tu donación hace la diferencia';
            
            // Actualizar CSS custom properties
            document.documentElement.style.setProperty('--donations-admin-primary', primaryColor);
            
            // Actualizar elementos de vista previa
            $('#previewTitle').text(`Apoya ${foundationName}`);
            $('#previewDescription').text(description);
            $('#previewButton').css('background', primaryColor);
            
            // Actualizar montos en vista previa
            this.updatePreviewAmounts();
        }

        /**
         * Actualizar montos en vista previa
         */
        updatePreviewAmounts() {
            const $previewAmounts = $('.preview-amounts .preview-amount');
            
            for (let i = 1; i <= 4; i++) {
                const amount = $(`input[name="amount_${i}"]`).val();
                const $previewAmount = $previewAmounts.eq(i - 1);
                
                if (amount && $previewAmount.length) {
                    $previewAmount.text(`€${amount}`);
                }
            }
        }

        /**
         * Manejar cambios en campos
         */
        handleFieldChange() {
            this.hasUnsavedChanges = true;
            this.updatePreview();
            
            // Validación en tiempo real
            this.validateFields();
            
            // Auto-save después de 3 segundos de inactividad
            clearTimeout(this.autoSaveTimeout);
            this.autoSaveTimeout = setTimeout(() => {
                this.autoSave();
            }, 3000);
        }

        /**
         * Manejar clic en toggle
         */
        handleToggleClick(e) {
            const $toggle = $(e.currentTarget);
            const $input = $toggle.find('input');
            
            // Cambiar estado
            const isChecked = !$input.prop('checked');
            $input.prop('checked', isChecked);
            
            if (isChecked) {
                $toggle.addClass('active');
            } else {
                $toggle.removeClass('active');
            }
            
            // Disparar evento de cambio
            $input.trigger('change');
            
            // Efectos visuales
            $toggle.addClass('clicked');
            setTimeout(() => $toggle.removeClass('clicked'), 200);
        }

        /**
         * Manejar clic en botón de copia
         */
        handleCopyClick(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const textToCopy = $btn.data('clipboard') || $btn.siblings('code').text();
            
            this.copyToClipboard(textToCopy).then(() => {
                this.showCopySuccess($btn);
            }).catch(() => {
                this.showCopyError($btn);
            });
        }

        /**
         * Copiar texto al portapapeles
         */
        async copyToClipboard(text) {
            if (navigator.clipboard) {
                return navigator.clipboard.writeText(text);
            } else {
                // Fallback para navegadores antiguos
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    return Promise.resolve();
                } catch (err) {
                    return Promise.reject(err);
                } finally {
                    document.body.removeChild(textArea);
                }
            }
        }

        /**
         * Mostrar éxito en copia
         */
        showCopySuccess($btn) {
            const originalContent = $btn.html();
            
            $btn.html('<span class="dashicons dashicons-yes"></span> Copiado')
                .addClass('success')
                .prop('disabled', true);
            
            setTimeout(() => {
                $btn.html(originalContent)
                    .removeClass('success')
                    .prop('disabled', false);
            }, 2000);
        }

        /**
         * Mostrar error en copia
         */
        showCopyError($btn) {
            const originalContent = $btn.html();
            
            $btn.html('<span class="dashicons dashicons-no"></span> Error')
                .addClass('error')
                .prop('disabled', true);
            
            setTimeout(() => {
                $btn.html(originalContent)
                    .removeClass('error')
                    .prop('disabled', false);
            }, 2000);
        }

        /**
         * Manejar envío de formulario
         */
        handleFormSubmit(e) {
            e.preventDefault();
            
            this.saveSettings();
        }

        /**
         * Guardar configuraciones
         */
        saveSettings() {
            const $form = $('.donations-settings-form');
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Estado de loading
            this.setLoadingState($submitBtn, true);
            
            // Recopilar datos
            const formData = this.collectFormData();
            
            // Enviar via AJAX
            $.ajax({
                url: this.settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_donation_settings',
                    settings: $.param(formData),
                    nonce: this.settings.nonce
                },
                success: (response) => this.handleSaveSuccess(response),
                error: () => this.handleSaveError(),
                complete: () => this.setLoadingState($submitBtn, false)
            });
        }

        /**
         * Recopilar datos del formulario
         */
        collectFormData() {
            const formData = {};
            
            // Inputs de texto, email, número, etc.
            $('input[type="text"], input[type="email"], input[type="number"], input[type="url"]').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    formData[name] = $(this).val();
                }
            });
            
            // Textareas
            $('textarea').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    formData[name] = $(this).val();
                }
            });
            
            // Selects
            $('select').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    formData[name] = $(this).val();
                }
            });
            
            // Checkboxes
            $('input[type="checkbox"]').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    formData[name] = $(this).is(':checked') ? '1' : '0';
                }
            });
            
            // Color pickers
            $('.color-picker').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    formData[name] = $(this).val();
                }
            });
            
            return formData;
        }

        /**
         * Manejar éxito al guardar
         */
        handleSaveSuccess(response) {
            if (response.success) {
                this.showNotice('success', response.data.message || 'Configuración guardada correctamente');
                this.hasUnsavedChanges = false;
            } else {
                this.showNotice('error', response.data.message || 'Error al guardar la configuración');
            }
        }

        /**
         * Manejar error al guardar
         */
        handleSaveError() {
            this.showNotice('error', 'Error de conexión. Por favor intenta de nuevo.');
        }

        /**
         * Enviar email de prueba
         */
        sendTestEmail() {
            const $btn = $('#test-email-btn');
            const $result = $('#test-email-result');
            
            this.setLoadingState($btn, true, 'Enviando...');
            
            const emailData = {
                action: 'donations_wc_test_email',
                email: $('#admin_email').val(),
                subject: $('#email_subject').val(),
                message: $('#email_message').val(),
                nonce: this.settings.nonce
            };
            
            $.ajax({
                url: this.settings.ajax_url,
                type: 'POST',
                data: emailData,
                success: (response) => {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: () => {
                    $result.html('<div class="notice notice-error inline"><p>Error al enviar email de prueba</p></div>');
                },
                complete: () => {
                    this.setLoadingState($btn, false, 'Enviar Email de Prueba');
                    setTimeout(() => $result.fadeOut(), 5000);
                }
            });
        }

        /**
         * Resetear configuraciones
         */
        resetSettings() {
            if (!confirm('¿Estás seguro de que quieres restaurar los valores por defecto? Esta acción no se puede deshacer.')) {
                return;
            }
            
            $.ajax({
                url: this.settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'donations_wc_reset_settings',
                    nonce: this.settings.nonce
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        this.showNotice('error', 'Error al restaurar configuración');
                    }
                },
                error: () => {
                    this.showNotice('error', 'Error de conexión');
                }
            });
        }

        /**
         * Configurar auto-guardado
         */
        setupAutoSave() {
            // Auto-save cada 5 minutos si hay cambios
            setInterval(() => {
                if (this.hasUnsavedChanges) {
                    this.autoSave();
                }
            }, 300000); // 5 minutos
        }

        /**
         * Auto-guardar configuraciones
         */
        autoSave() {
            if (!this.hasUnsavedChanges) return;
            
            const formData = this.collectFormData();
            
            $.ajax({
                url: this.settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_donation_settings',
                    settings: $.param(formData),
                    nonce: this.settings.nonce,
                    auto_save: true
                },
                success: (response) => {
                    if (response.success) {
                        this.hasUnsavedChanges = false;
                        this.showAutoSaveIndicator();
                    }
                }
            });
        }

        /**
         * Mostrar indicador de auto-guardado
         */
        showAutoSaveIndicator() {
            let $indicator = $('.auto-save-indicator');
            
            if (!$indicator.length) {
                $indicator = $('<div class="auto-save-indicator">Guardado automáticamente</div>');
                $('body').append($indicator);
            }
            
            $indicator.show().fadeOut(3000);
        }

        /**
         * Seguimiento de cambios
         */
        trackChanges() {
            // Marcar formulario como original
            this.originalFormData = JSON.stringify(this.collectFormData());
            
            // Verificar cambios periódicamente
            setInterval(() => {
                const currentFormData = JSON.stringify(this.collectFormData());
                this.hasUnsavedChanges = currentFormData !== this.originalFormData;
            }, 1000);
        }

        /**
         * Validar campos
         */
        validateFields() {
            // Validar emails
            $('input[type="email"]').each((index, field) => {
                const $field = $(field);
                const email = $field.val();
                
                if (email && !this.isValidEmail(email)) {
                    this.showFieldError($field, 'Email inválido');
                } else {
                    this.clearFieldError($field);
                }
            });
            
            // Validar URLs
            $('input[type="url"]').each((index, field) => {
                const $field = $(field);
                const url = $field.val();
                
                if (url && !this.isValidUrl(url)) {
                    this.showFieldError($field, 'URL inválida');
                } else {
                    this.clearFieldError($field);
                }
            });
            
            // Validar números
            $('input[type="number"]').each((index, field) => {
                const $field = $(field);
                const value = parseFloat($field.val());
                const min = parseFloat($field.attr('min'));
                const max = parseFloat($field.attr('max'));
                
                if (!isNaN(min) && value < min) {
                    this.showFieldError($field, `Valor mínimo: ${min}`);
                } else if (!isNaN(max) && value > max) {
                    this.showFieldError($field, `Valor máximo: ${max}`);
                } else {
                    this.clearFieldError($field);
                }
            });
        }

        /**
         * Validar email
         */
        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        /**
         * Validar URL
         */
        isValidUrl(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        }

        /**
         * Mostrar error en campo
         */
        showFieldError($field, message) {
            $field.addClass('error');
            
            let $error = $field.siblings('.field-error');
            if (!$error.length) {
                $error = $('<div class="field-error"></div>');
                $field.after($error);
            }
            
            $error.text(message).show();
        }

        /**
         * Limpiar error de campo
         */
        clearFieldError($field) {
            $field.removeClass('error');
            $field.siblings('.field-error').hide();
        }

        /**
         * Configurar tooltips
         */
        initializeTooltips() {
            $('[title]').each(function() {
                const $element = $(this);
                const title = $element.attr('title');
                
                if (title) {
                    $element.removeAttr('title');
                    
                    $element.hover(
                        function(e) {
                            this.showTooltip($element, title, e);
                        }.bind(this),
                        function() {
                            this.hideTooltip();
                        }.bind(this)
                    );
                }
            });
        }

        /**
         * Mostrar tooltip
         */
        showTooltip($element, text, e) {
            let $tooltip = $('#admin-tooltip');
            
            if (!$tooltip.length) {
                $tooltip = $('<div id="admin-tooltip" class="admin-tooltip"></div>');
                $('body').append($tooltip);
            }
            
            $tooltip.text(text).show();
            
            // Posicionar tooltip
            $(document).on('mousemove.tooltip', (e) => {
                $tooltip.css({
                    left: e.pageX + 10,
                    top: e.pageY - 30
                });
            });
        }

        /**
         * Ocultar tooltip
         */
        hideTooltip() {
            $('#admin-tooltip').hide();
            $(document).off('mousemove.tooltip');
        }

        /**
         * Resaltar shortcodes
         */
        highlightShortcodes() {
            $('.shortcode-box code').each(function() {
                const $code = $(this);
                const text = $code.text();
                
                // Resaltar sintaxis básica de shortcodes
                const highlighted = text
                    .replace(/(\[|\])/g, '<span class="bracket">$1</span>')
                    .replace(/(\w+)=/g, '<span class="attribute">$1</span>=')
                    .replace(/"([^"]*)"/g, '"<span class="value">$1</span>"');
                
                $code.html(highlighted);
            });
        }

        /**
         * Cargar datos de reportes
         */
        loadReportsData() {
            // Implementar si es necesario cargar datos dinámicos
            console.log('Loading reports data...');
        }

        /**
         * Establecer estado de loading
         */
        setLoadingState($element, loading, text = null) {
            if (loading) {
                $element.prop('disabled', true).addClass('loading');
                if (text) {
                    $element.data('original-text', $element.text()).text(text);
                }
            } else {
                $element.prop('disabled', false).removeClass('loading');
                if ($element.data('original-text')) {
                    $element.text($element.data('original-text'));
                }
            }
        }

        /**
         * Mostrar notificación
         */
        showNotice(type, message) {
            let $notice = $('.admin-notice');
            
            if (!$notice.length) {
                $notice = $('<div class="admin-notice notice"></div>');
                $('.donations-header').after($notice);
            }
            
            $notice.removeClass('notice-success notice-error notice-warning notice-info')
                   .addClass(`notice-${type}`)
                   .html(`<p>${message}</p>`)
                   .show();
            
            // Auto-ocultar después de 5 segundos
            setTimeout(() => $notice.fadeOut(), 5000);
            
            // Scroll a la notificación
            $('html, body').animate({
                scrollTop: $notice.offset().top - 50
            }, 300);
        }

        /**
         * Manejar antes de salir de la página
         */
        handleBeforeUnload() {
            if (this.hasUnsavedChanges) {
                return 'Tienes cambios sin guardar. ¿Estás seguro de que quieres salir?';
            }
        }

        /**
         * Manejar atajos de teclado
         */
        handleKeyboardShortcuts(e) {
            // Ctrl+S para guardar
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
                e.preventDefault();
                this.saveSettings();
            }
            
            // Escape para cerrar modales
            if (e.keyCode === 27) {
                $('.modal, .tooltip').hide();
            }
            
            // Tab navigation mejorada
            if (e.keyCode === 9 && e.ctrlKey) {
                e.preventDefault();
                this.switchToNextTab();
            }
        }

        /**
         * Cambiar al siguiente tab
         */
        switchToNextTab() {
            const tabs = ['general', 'appearance', 'payments', 'emails', 'shortcodes'];
            const currentIndex = tabs.indexOf(this.currentTab);
            const nextIndex = (currentIndex + 1) % tabs.length;
            
            this.switchTab(tabs[nextIndex]);
        }
    }

    /**
     * Clase para gestión de reportes
     */
    class DonationsReports {
        constructor() {
            this.charts = {};
            this.init();
        }

        init() {
            this.bindEvents();
            this.loadCharts();
        }

        bindEvents() {
            $('#period').on('change', () => this.updatePeriodFields());
            $('.export-btn').on('click', (e) => this.handleExport(e));
        }

        updatePeriodFields() {
            const period = $('#period').val();
            
            if (period === 'custom') {
                $('.custom-dates').show();
            } else {
                $('.custom-dates').hide();
            }
        }

        loadCharts() {
            // Implementar carga de gráficos si Chart.js está disponible
            if (typeof Chart !== 'undefined') {
                this.initializeCharts();
            }
        }

        initializeCharts() {
            // Configurar gráficos específicos
            console.log('Initializing charts...');
        }

        handleExport(e) {
            const $btn = $(e.currentTarget);
            const format = $btn.data('format');
            
            $btn.prop('disabled', true).text('Exportando...');
            
            // El enlace ya maneja la descarga
            setTimeout(() => {
                $btn.prop('disabled', false).text($btn.data('original-text') || 'Exportar');
            }, 2000);
        }
    }

    /**
     * Clase para gestión de donantes
     */
    class DonorManagement {
        constructor() {
            this.selectedDonors = [];
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeModals();
        }

        bindEvents() {
            $('#select-all-donors').on('change', () => this.selectAllDonors());
            $('.donor-checkbox').on('change', () => this.updateSelectedDonors());
            $('.view-donor-history').on('click', (e) => this.viewDonorHistory(e));
            $('.send-donor-email').on('click', (e) => this.sendDonorEmail(e));
            $('#apply-bulk-action').on('click', () => this.applyBulkAction());
        }

        selectAllDonors() {
            const isChecked = $('#select-all-donors').is(':checked');
            $('.donor-checkbox').prop('checked', isChecked);
            this.updateSelectedDonors();
        }

        updateSelectedDonors() {
            this.selectedDonors = $('.donor-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
        }

        viewDonorHistory(e) {
            e.preventDefault();
            // Implementar modal de historial
        }

        sendDonorEmail(e) {
            e.preventDefault();
            // Implementar modal de email
        }

        applyBulkAction() {
            const action = $('#bulk-action-selector').val();
            
            if (!action) {
                alert('Selecciona una acción.');
                return;
            }
            
            if (this.selectedDonors.length === 0) {
                alert('Selecciona al menos un donante.');
                return;
            }
            
            // Procesar acción
            console.log('Bulk action:', action, this.selectedDonors);
        }

        initializeModals() {
            // Configurar modales si es necesario
        }
    }

    /**
     * Inicialización cuando el DOM esté listo
     */
    $(document).ready(function() {
        // Inicializar admin principal
        new DonationsWCAdmin();
        
        // Inicializar reportes si estamos en esa página
        if ($('.donations-reports').length) {
            new DonationsReports();
        }
        
        // Inicializar gestión de donantes si estamos en esa página
        if ($('.donors-management').length) {
            new DonorManagement();
        }
        
        // Mejorar experiencia general del admin
        initializeGeneralEnhancements();
    });

    /**
     * Mejoras generales del admin
     */
    function initializeGeneralEnhancements() {
        // Smooth scrolling para enlaces internos
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $($(this).attr('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 50
                }, 300);
            }
        });
        
        // Auto-resize para textareas
        $('textarea').on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Confirmar acciones destructivas
        $('.destructive-action').on('click', function(e) {
            if (!confirm('¿Estás seguro de que quieres realizar esta acción?')) {
                e.preventDefault();
            }
        });
        
        // Mejorar accesibilidad
        $('input, select, textarea').on('focus', function() {
            $(this).closest('.form-group, td').addClass('focused');
        }).on('blur', function() {
            $(this).closest('.form-group, td').removeClass('focused');
        });
    }

})(jQuery);

/**
 * Estilos CSS adicionales para el admin
 */
const adminStyles = `
.auto-save-indicator {
    position: fixed;
    top: 32px;
    right: 20px;
    background: #46b450;
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 12px;
    z-index: 999999;
    display: none;
}

.admin-tooltip {
    position: absolute;
    background: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    z-index: 999999;
    max-width: 200px;
    word-wrap: break-word;
    display: none;
}

.field-error {
    color: #dc3232;
    font-size: 11px;
    margin-top: 4px;
    display: none;
}

.form-group.focused,
td.focused {
    background: rgba(0,115,170,0.03);
    border-radius: 4px;
}

.shortcode-box .bracket { color: #0073aa; font-weight: bold; }
.shortcode-box .attribute { color: #d63384; }
.shortcode-box .value { color: #198754; }

.loading {
    opacity: 0.7;
    pointer-events: none;
}

.copy-btn.success { background: #46b450; }
.copy-btn.error { background: #dc3232; }

.toggle-switch.clicked .toggle-slider {
    transform: scale(0.95);
}
`;

// Agregar estilos al head
const styleElement = document.createElement('style');
styleElement.textContent = adminStyles;
document.head.appendChild(styleElement);