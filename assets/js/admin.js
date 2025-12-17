/**
 * OFAC Admin JavaScript
 * 
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin Module
     */
    const OFACAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initToggles();
            this.initColorPickers();
            this.initApiTest();
            this.initChart();
            this.initGdprTools();
            this.initImportExport();
        },

        /**
         * Bind global events
         */
        bindEvents: function() {
            // Form submit with loading state
            $('.ofac-settings-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Confirm dangerous actions
            $('[data-confirm]').on('click', this.handleConfirm.bind(this));
            
            // Dynamic field visibility
            $('[data-toggle-target]').on('change', this.handleToggleTarget.bind(this));
            
            // Media uploader
            $('.ofac-media-upload').on('click', this.handleMediaUpload.bind(this));
            $('.ofac-media-remove').on('click', this.handleMediaRemove.bind(this));
        },

        /**
         * Initialize tabs
         */
        initTabs: function() {
            const $tabs = $('.ofac-tabs');
            if (!$tabs.length) return;

            $tabs.on('click', '.ofac-tab', function(e) {
                e.preventDefault();
                
                const $tab = $(this);
                const target = $tab.data('tab');
                
                // Update tab states
                $tabs.find('.ofac-tab').removeClass('ofac-tab--active');
                $tab.addClass('ofac-tab--active');
                
                // Update content
                $('.ofac-tab-content').removeClass('ofac-tab-content--active');
                $(`#${target}`).addClass('ofac-tab-content--active');
                
                // Update URL hash
                if (history.pushState) {
                    history.pushState(null, null, `#${target}`);
                }
            });

            // Load tab from hash
            const hash = window.location.hash.slice(1);
            if (hash) {
                $(`.ofac-tab[data-tab="${hash}"]`).trigger('click');
            }
        },

        /**
         * Initialize toggle switches
         */
        initToggles: function() {
            $('.ofac-toggle__input').each(function() {
                const $toggle = $(this);
                const target = $toggle.data('toggle-target');
                
                if (target) {
                    const $target = $(target);
                    $target.toggle($toggle.is(':checked'));
                    
                    $toggle.on('change', function() {
                        $target.slideToggle(200);
                    });
                }
            });
        },

        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            $('.ofac-color-picker__input').each(function() {
                const $input = $(this);
                const $value = $input.siblings('.ofac-color-picker__value');
                
                $input.on('input', function() {
                    $value.text($input.val());
                });
            });

            // Use WordPress color picker if available
            if ($.fn.wpColorPicker) {
                $('.ofac-wp-color-picker').wpColorPicker();
            }
        },

        /**
         * Initialize API test button
         */
        initApiTest: function() {
            const $testBtn = $('#ofac-test-connection');
            const $result = $('#ofac-connection-result');
            
            if (!$testBtn.length) return;

            $testBtn.on('click', function(e) {
                e.preventDefault();
                
                // Get current field values
                const apiUrl = $('#ofac_api_url').val() || '';
                const apiKey = $('#ofac_api_key').val() || '';
                const workspaceSlug = $('#ofac_workspace_slug').val() || '';

                // Validate fields
                if (!apiUrl.trim()) {
                    $result.html('<span class="ofac-result-error">⚠️ Veuillez saisir l\'URL de l\'API</span>');
                    $('#ofac_api_url').focus();
                    return;
                }
                if (!apiKey.trim()) {
                    $result.html('<span class="ofac-result-error">⚠️ Veuillez saisir la clé API</span>');
                    $('#ofac_api_key').focus();
                    return;
                }
                if (!workspaceSlug.trim()) {
                    $result.html('<span class="ofac-result-error">⚠️ Veuillez saisir le slug du workspace</span>');
                    $('#ofac_workspace_slug').focus();
                    return;
                }

                const originalText = $testBtn.text();
                $testBtn.prop('disabled', true).html('<span class="ofac-spinner"></span> Test en cours...');
                $result.html('<span class="ofac-result-loading"><span class="ofac-spinner"></span> Connexion à AnythingLLM...</span>');
                
                $.ajax({
                    url: ofacAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ofac_test_connection',
                        nonce: ofacAdmin.nonce,
                        api_url: apiUrl,
                        api_key: apiKey,
                        workspace_slug: workspaceSlug,
                        save_on_success: 'true'
                    },
                    success: function(response) {
                        if (response.success) {
                            let message = response.data.message;
                            let icon = '✓';
                            let className = 'ofac-result-success';
                            
                            if (response.data.saved) {
                                icon = '✓✓';
                            }
                            
                            $result.html('<span class="' + className + '">' + icon + ' ' + message + '</span>');
                            
                            // Visual feedback on the button
                            $testBtn.html('✓ Connecté !').addClass('ofac-btn-success');
                            setTimeout(function() {
                                $testBtn.removeClass('ofac-btn-success').text(originalText);
                            }, 3000);
                        } else {
                            const errorMsg = response.data.message || response.data || 'Erreur inconnue';
                            $result.html('<span class="ofac-result-error">✗ ' + errorMsg + '</span>');
                            $testBtn.text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMsg = 'Erreur de connexion';
                        if (xhr.status === 0) {
                            errorMsg = 'Impossible de contacter le serveur';
                        } else if (xhr.status === 403) {
                            errorMsg = 'Accès refusé';
                        } else if (xhr.status === 500) {
                            errorMsg = 'Erreur serveur';
                        } else if (error) {
                            errorMsg = error;
                        }
                        $result.html('<span class="ofac-result-error">✗ ' + errorMsg + '</span>');
                        $testBtn.text(originalText);
                    },
                    complete: function() {
                        $testBtn.prop('disabled', false);
                    }
                });
            });
        },

        /**
         * Initialize statistics chart
         */
        initChart: function() {
            const $chartContainer = $('#ofac-stats-chart');
            if (!$chartContainer.length || typeof Chart === 'undefined') return;

            const ctx = $chartContainer[0].getContext('2d');
            const chartData = $chartContainer.data('chart');
            
            if (!chartData) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Conversations',
                            data: chartData.conversations,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Messages',
                            data: chartData.messages,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        },

        /**
         * Initialize GDPR tools
         */
        initGdprTools: function() {
            // Export user data
            $('#ofac-export-user-data').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $btn = $form.find('button');
                const $input = $form.find('input[name="user_identifier"]');
                const identifier = $input.val().trim();

                if (!identifier) {
                    OFACAdmin.showNotice('error', 'Veuillez saisir un identifiant');
                    return;
                }

                $btn.prop('disabled', true);

                $.ajax({
                    url: ofacAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ofac_export_user_data',
                        nonce: ofacAdmin.nonce,
                        identifier: identifier
                    },
                    success: function(response) {
                        if (response.success) {
                            // Download the JSON file
                            const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = `user-data-${identifier}-${Date.now()}.json`;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);

                            OFACAdmin.showNotice('success', 'Données exportées avec succès.');
                        } else {
                            OFACAdmin.showNotice('error', response.data.message || 'Erreur lors de l\'export.');
                        }
                    },
                    error: function() {
                        OFACAdmin.showNotice('error', 'Erreur lors de l\'export.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Delete user data
            $('#ofac-delete-user-data').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $btn = $form.find('button');
                const $input = $form.find('input[name="user_identifier"]');
                const identifier = $input.val().trim();

                if (!identifier) {
                    OFACAdmin.showNotice('error', 'Veuillez saisir un identifiant');
                    return;
                }

                if (!confirm('Êtes-vous sûr de vouloir supprimer ces données ?')) {
                    return;
                }

                $btn.prop('disabled', true);

                $.ajax({
                    url: ofacAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ofac_delete_user_data',
                        nonce: ofacAdmin.nonce,
                        identifier: identifier
                    },
                    success: function(response) {
                        if (response.success) {
                            OFACAdmin.showNotice('success', response.data.message);
                            $input.val('');
                        } else {
                            OFACAdmin.showNotice('error', response.data.message || 'Erreur lors de la suppression.');
                        }
                    },
                    error: function() {
                        OFACAdmin.showNotice('error', 'Erreur lors de la suppression.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Cleanup expired data
            $('#ofac-cleanup-expired').on('click', function(e) {
                e.preventDefault();

                const $btn = $(this);

                if (!confirm('Êtes-vous sûr de vouloir nettoyer les données expirées ?')) {
                    return;
                }

                $btn.prop('disabled', true);

                $.ajax({
                    url: ofacAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ofac_cleanup_expired',
                        nonce: ofacAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            OFACAdmin.showNotice('success', response.data.message);
                        } else {
                            OFACAdmin.showNotice('error', response.data.message || 'Erreur lors du nettoyage.');
                        }
                    },
                    error: function() {
                        OFACAdmin.showNotice('error', 'Erreur lors du nettoyage.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });
        },

        /**
         * Initialize import/export settings
         */
        initImportExport: function() {
            // Export settings
            $('#ofac-export-settings').on('click', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const originalText = $btn.text();
                $btn.prop('disabled', true).text(ofacAdmin.strings.exporting || 'Export en cours...');

                $.ajax({
                    url: ofacAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ofac_export_settings',
                        nonce: ofacAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // The data is already a JSON string from PHP
                            const jsonData = response.data.data;
                            const filename = response.data.filename || `ofac-settings-${Date.now()}.json`;
                            const blob = new Blob([jsonData], { type: 'application/json' });
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);

                            OFACAdmin.showNotice('success', 'Réglages exportés avec succès.');
                        } else {
                            OFACAdmin.showNotice('error', response.data || 'Erreur lors de l\'export.');
                        }
                    },
                    error: function() {
                        OFACAdmin.showNotice('error', 'Erreur lors de l\'export.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Import settings - trigger file input on button click
            $('#ofac-import-settings').on('click', function(e) {
                e.preventDefault();
                $('#ofac-import-file').trigger('click');
            });

            // Handle file selection for import
            $('#ofac-import-file').on('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = function(event) {
                    try {
                        const settings = JSON.parse(event.target.result);

                        if (!confirm(ofacAdmin.strings.confirmReset ? 'Êtes-vous sûr de vouloir importer ces réglages ? Les réglages actuels seront remplacés.' : 'Êtes-vous sûr de vouloir importer ces réglages ?')) {
                            return;
                        }

                        $.ajax({
                            url: ofacAdmin.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'ofac_import_settings',
                                nonce: ofacAdmin.nonce,
                                settings: JSON.stringify(settings)
                            },
                            success: function(response) {
                                if (response.success) {
                                    OFACAdmin.showNotice('success', response.data || 'Réglages importés avec succès.');
                                    setTimeout(() => location.reload(), 1500);
                                } else {
                                    OFACAdmin.showNotice('error', response.data || 'Erreur lors de l\'import.');
                                }
                            },
                            error: function() {
                                OFACAdmin.showNotice('error', 'Erreur lors de l\'import.');
                            }
                        });
                    } catch (err) {
                        OFACAdmin.showNotice('error', 'Fichier JSON invalide.');
                    }
                };
                reader.readAsText(file);

                // Reset file input to allow re-importing same file
                $(this).val('');
            });

            // Reset settings
            $('#ofac-reset-settings').on('click', function(e) {
                e.preventDefault();

                if (!confirm(ofacAdmin.strings.confirmReset || 'Êtes-vous sûr de vouloir réinitialiser les réglages ?')) {
                    return;
                }

                const $btn = $(this);
                const originalText = $btn.text();
                $btn.prop('disabled', true).text('Réinitialisation...');

                $.ajax({
                    url: ofacAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ofac_reset_settings',
                        nonce: ofacAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            OFACAdmin.showNotice('success', response.data || 'Réglages réinitialisés.');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            OFACAdmin.showNotice('error', response.data || 'Erreur lors de la réinitialisation.');
                        }
                    },
                    error: function() {
                        OFACAdmin.showNotice('error', 'Erreur lors de la réinitialisation.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
        },

        /**
         * Handle form submit
         */
        handleFormSubmit: function(e) {
            const $form = $(e.currentTarget);
            const $btn = $form.find('[type="submit"]');

            $btn.prop('disabled', true)
                .html('<span class="ofac-spinner"></span> ' + (ofacAdmin.strings.saved || 'Enregistrement...'));
        },

        /**
         * Handle confirm dialogs
         */
        handleConfirm: function(e) {
            const message = $(e.currentTarget).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        },

        /**
         * Handle toggle target visibility
         */
        handleToggleTarget: function(e) {
            const $input = $(e.currentTarget);
            const target = $input.data('toggle-target');
            const $target = $(target);
            
            if ($input.is(':checkbox')) {
                $target.slideToggle(200);
            } else {
                const value = $input.val();
                const showValues = $input.data('toggle-values');
                
                if (showValues) {
                    $target.toggle(showValues.includes(value));
                }
            }
        },

        /**
         * Handle media upload
         */
        handleMediaUpload: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $field = $button.closest('.ofac-media-field');
            const $input = $field.find('input[type="hidden"]');
            const $preview = $field.find('.ofac-media-preview');
            const $removeBtn = $field.find('.ofac-media-remove');
            
            // Create media frame
            const frame = wp.media({
                title: ofacAdmin.strings.selectImage || 'Sélectionner une image',
                button: {
                    text: ofacAdmin.strings.useImage || 'Utiliser cette image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            // On select
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.id);
                $preview.html('<img src="' + attachment.url + '" style="max-width:150px;max-height:150px;">');
                $removeBtn.show();
            });
            
            frame.open();
        },

        /**
         * Handle media remove
         */
        handleMediaRemove: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $field = $button.closest('.ofac-media-field');
            const $input = $field.find('input[type="hidden"]');
            const $preview = $field.find('.ofac-media-preview');
            
            $input.val('');
            $preview.empty();
            $button.hide();
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            // Remove any existing notices first
            $('.ofac-alert').remove();

            const $notice = $(`
                <div class="ofac-alert ofac-alert--${type}" style="
                    padding: 12px 15px;
                    margin: 15px 0;
                    border-radius: 4px;
                    background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
                    color: ${type === 'success' ? '#155724' : '#721c24'};
                    border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                ">
                    <span>${message}</span>
                    <button type="button" class="ofac-alert__dismiss" style="
                        background: none;
                        border: none;
                        font-size: 20px;
                        cursor: pointer;
                        color: inherit;
                        padding: 0 5px;
                    ">&times;</button>
                </div>
            `);

            // Insert notice after the page title
            const $target = $('.ofac-admin-wrap h1').first();
            if ($target.length) {
                $target.after($notice);
            } else {
                $('.wrap').prepend($notice);
            }

            $notice.find('.ofac-alert__dismiss').on('click', function() {
                $notice.fadeOut(200, function() {
                    $notice.remove();
                });
            });

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(200, function() {
                    $notice.remove();
                });
            }, 5000);
        }
    };

    /**
     * Logs Module
     */
    const OFACLogs = {
        init: function() {
            this.bindEvents();
            this.initModal();
        },

        bindEvents: function() {
            // View conversation messages
            $(document).on('click', '.ofac-view-messages', this.viewMessages.bind(this));

            // Delete conversation
            $(document).on('click', '.ofac-delete-conversation', this.deleteConversation.bind(this));

            // Bulk actions
            $('#ofac-bulk-action-btn').on('click', this.bulkAction.bind(this));

            // Select all
            $('#cb-select-all').on('change', this.selectAll.bind(this));

            // Close modal
            $(document).on('click', '.ofac-modal-close, .ofac-modal', function(e) {
                if ($(e.target).is('.ofac-modal, .ofac-modal-close')) {
                    $('#ofac-messages-modal').hide();
                }
            });
        },

        initModal: function() {
            // Modal escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#ofac-messages-modal').is(':visible')) {
                    $('#ofac-messages-modal').hide();
                }
            });
        },

        viewMessages: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const conversationId = $btn.data('id');
            const $modal = $('#ofac-messages-modal');
            const $messagesList = $('#ofac-messages-list');

            $messagesList.html('<p style="text-align:center;">Chargement...</p>');
            $modal.show();

            $.ajax({
                url: ofacAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ofac_get_conversation_messages',
                    nonce: ofacAdmin.nonce,
                    conversation_id: conversationId
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $messagesList.html(response.data.html);
                    } else {
                        $messagesList.html('<p>Aucun message trouvé.</p>');
                    }
                },
                error: function() {
                    $messagesList.html('<p>Erreur lors du chargement des messages.</p>');
                }
            });
        },

        deleteConversation: function(e) {
            e.preventDefault();

            if (!confirm('Êtes-vous sûr de vouloir supprimer cette conversation ?')) {
                return;
            }

            const $btn = $(e.currentTarget);
            const $row = $btn.closest('tr');
            const conversationId = $btn.data('id');

            $.ajax({
                url: ofacAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ofac_delete_conversation',
                    nonce: ofacAdmin.nonce,
                    conversation_id: conversationId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(200, function() {
                            $row.remove();
                        });
                        OFACAdmin.showNotice('success', 'Conversation supprimée.');
                    } else {
                        OFACAdmin.showNotice('error', response.data || 'Erreur lors de la suppression.');
                    }
                },
                error: function() {
                    OFACAdmin.showNotice('error', 'Erreur lors de la suppression.');
                }
            });
        },

        bulkAction: function(e) {
            e.preventDefault();

            const action = $('#bulk-action-selector-top').val();
            const ids = [];

            $('input[name="conversation_ids[]"]:checked').each(function() {
                ids.push($(this).val());
            });

            if (!action) {
                OFACAdmin.showNotice('error', 'Veuillez sélectionner une action.');
                return;
            }

            if (!ids.length) {
                OFACAdmin.showNotice('error', 'Veuillez sélectionner au moins une conversation.');
                return;
            }

            if (action === 'delete' && !confirm('Êtes-vous sûr de vouloir supprimer les conversations sélectionnées ?')) {
                return;
            }

            $.ajax({
                url: ofacAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ofac_bulk_delete_conversations',
                    nonce: ofacAdmin.nonce,
                    ids: ids
                },
                success: function(response) {
                    if (response.success) {
                        OFACAdmin.showNotice('success', response.data);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        OFACAdmin.showNotice('error', response.data || 'Erreur lors de la suppression.');
                    }
                },
                error: function() {
                    OFACAdmin.showNotice('error', 'Erreur lors de la suppression.');
                }
            });
        },

        selectAll: function(e) {
            const checked = $(e.currentTarget).is(':checked');
            $('input[name="conversation_ids[]"]').prop('checked', checked);
        }
    };

    /**
     * Stats Charts Module
     */
    const OFACCharts = {
        init: function() {
            if (typeof Chart === 'undefined' || typeof ofacChartData === 'undefined') {
                return;
            }

            this.initActivityChart();
            this.initResponseTimeChart();
            this.initErrorsChart();
        },

        initActivityChart: function() {
            const ctx = document.getElementById('ofac-chart-activity');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ofacChartData.labels,
                    datasets: [
                        {
                            label: 'Conversations',
                            data: ofacChartData.conversations,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Messages',
                            data: ofacChartData.messages,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        },

        initResponseTimeChart: function() {
            const ctx = document.getElementById('ofac-chart-response-time');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ofacChartData.labels,
                    datasets: [
                        {
                            label: 'Temps de réponse (s)',
                            data: ofacChartData.response_time || [],
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        initErrorsChart: function() {
            const ctx = document.getElementById('ofac-chart-errors');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ofacChartData.labels,
                    datasets: [
                        {
                            label: 'Erreurs',
                            data: ofacChartData.errors,
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderColor: '#ef4444',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        OFACAdmin.init();
        OFACLogs.init();
        OFACCharts.init();
    });

})(jQuery);
