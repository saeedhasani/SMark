/**
 * SMark Plugin Admin JavaScript
 */
(function($) {
    'use strict';

    // Document ready
    $(document).ready(function() {
        initSmarkAdmin();
    });

    /**
     * Initialize admin functionality
     */
    function initSmarkAdmin() {
        // Add admin page class for styling only on SMark pages
        if ($('body').hasClass('smark-dashboard') ||
            $('body').hasClass('smark-social-media-page') ||
            $('body').hasClass('smark-competitor-analysis-page') ||
            $('body').hasClass('smark-keyword-research-page') ||
            $('body').hasClass('smark-headline-analyzer-page') ||
            $('body').hasClass('smark-converter-page') ||
            $('body').hasClass('smark-seo-optimization-page')) {
            $('.wrap').addClass('smark-admin-page');
        }

        // Special handling for Gemini App page (class is on wrap element, not body)
        if ($('.wrap').hasClass('smark-gemini-app-page')) {
            $('.wrap').addClass('smark-admin-page');
        }

        // Initialize tooltips if needed
        initTooltips();

        // Initialize other admin features
        initAdminFeatures();
    }

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        // Add tooltip functionality if needed
        $('[data-tooltip]').hover(
            function() {
                var tooltip = $(this).data('tooltip');
                $(this).append('<span class="smark-tooltip">' + tooltip + '</span>');
            },
            function() {
                $(this).find('.smark-tooltip').remove();
            }
        );
    }

    /**
     * Initialize other admin features
     */
    function initAdminFeatures() {
        // Add click handlers for future features
        $('.smark-action-btn').on('click', function(e) {
            e.preventDefault();
            var action = $(this).data('action');
            handleAction(action);
        });

        function ensureSmartModal() {
            if ($('#smark-smart-modal-overlay').length) {
                return;
            }

            var $overlay = $('<div/>', {
                id: 'smark-smart-modal-overlay',
                class: 'smark-smart-modal-overlay',
                css: { display: 'none' }
            });

            var $modal = $('<div/>', { class: 'smark-smart-modal', role: 'dialog', 'aria-modal': 'true' });
            var $header = $('<div/>', { class: 'smark-smart-modal__header' });
            var $title = $('<div/>', { class: 'smark-smart-modal__title' });
            var $close = $('<button/>', { type: 'button', class: 'smark-smart-modal__close', 'aria-label': 'Close' }).text('×');
            var $body = $('<div/>', { class: 'smark-smart-modal__body' });

            $header.append($title, $close);
            $modal.append($header, $body);
            $overlay.append($modal);
            $('body').append($overlay);

            function close() {
                $overlay.hide();
                $body.empty();
                $title.text('');
            }

            $close.on('click', close);
            $overlay.on('click', function(evt) {
                if (evt.target === $overlay[0]) {
                    close();
                }
            });
            $(document).on('keydown', function(evt) {
                if (evt.key === 'Escape' && $overlay.is(':visible')) {
                    close();
                }
            });
        }

        function openSmartModalLoading(titleText, bodyText) {
            ensureSmartModal();
            var $overlay = $('#smark-smart-modal-overlay');
            var $title = $overlay.find('.smark-smart-modal__title');
            var $body = $overlay.find('.smark-smart-modal__body');

            $title.text(String(titleText || ''));
            $body.empty();

            var $row = $('<div/>', { class: 'smark-smart-modal__loading' });
            $row.append($('<span/>', { class: 'dashicons dashicons-update dashicons-spin smark-smart-spinner', 'aria-hidden': 'true' }));
            $row.append($('<div/>', { class: 'smark-smart-modal__loading-text' }).text(String(bodyText || '')));
            $body.append($row);

            $overlay.show();
        }

        function openSmartModalResult(titleText, keyword, responseText, promptText, sources) {
            ensureSmartModal();
            var $overlay = $('#smark-smart-modal-overlay');
            var $title = $overlay.find('.smark-smart-modal__title');
            var $body = $overlay.find('.smark-smart-modal__body');

            $title.text(String(titleText || ''));
            $body.empty();

            if (keyword) {
                $body.append($('<div/>', { class: 'smark-smart-modal__meta' }).text(String(keyword)));
            }

            var $actions = $('<div/>', { class: 'smark-smart-modal__actions' });
            var $copy = $('<button/>', { type: 'button', class: 'button button-primary' }).text((window.SMarkAdmin && SMarkAdmin.strings && SMarkAdmin.strings.copy) ? SMarkAdmin.strings.copy : 'Copy');
            $copy.on('click', function() {
                var text = String(responseText || '');
                if (!text) {
                    return;
                }
                try {
                    navigator.clipboard.writeText(text);
                } catch (e) {
                    try {
                        var $tmp = $('<textarea/>').val(text).appendTo('body').select();
                        document.execCommand('copy');
                        $tmp.remove();
                    } catch (e2) {}
                }
            });
            $actions.append($copy);
            $body.append($actions);

            if (sources && sources.length) {
                var $srcWrap = $('<div/>', { class: 'smark-smart-modal__sources' });
                $srcWrap.append($('<div/>', { class: 'smark-smart-modal__section-title' }).text((window.SMarkAdmin && SMarkAdmin.strings && SMarkAdmin.strings.sources) ? SMarkAdmin.strings.sources : 'Sources'));
                var $ul = $('<ul/>');
                sources.forEach(function(u) {
                    var url = String(u || '');
                    if (!url) return;
                    $ul.append($('<li/>').append($('<a/>', { href: url, target: '_blank', rel: 'noopener noreferrer' }).text(url)));
                });
                $srcWrap.append($ul);
                $body.append($srcWrap);
            }

            $body.append($('<div/>', { class: 'smark-smart-modal__section-title' }).text((window.SMarkAdmin && SMarkAdmin.strings && SMarkAdmin.strings.result) ? SMarkAdmin.strings.result : 'Result'));
            $body.append($('<pre/>', { class: 'smark-smart-modal__pre' }).text(String(responseText || '')));

            if (promptText) {
                $body.append($('<details/>', { class: 'smark-smart-modal__details' })
                    .append($('<summary/>').text((window.SMarkAdmin && SMarkAdmin.strings && SMarkAdmin.strings.prompt) ? SMarkAdmin.strings.prompt : 'Prompt'))
                    .append($('<pre/>', { class: 'smark-smart-modal__pre smark-smart-modal__pre--prompt' }).text(String(promptText || '')))
                );
            }

            $overlay.show();
        }

        function getSignalHireFields(strings) {
            return [
                { key: 'profile_name', label: strings.signalhireProfileName || 'Profile name', section: 'profile' },
                { key: 'profile_location', label: strings.signalhireProfileLocation || 'Profile location', section: 'profile' },
                { key: 'job_title', label: strings.signalhireJobTitle || 'Job title', section: 'profile' },
                { key: 'department', label: strings.signalhireDepartment || 'Department', section: 'profile' },
                { key: 'seniority_level', label: strings.signalhireSeniorityLevel || 'Seniority level', section: 'profile' },
                { key: 'years_experience', label: strings.signalhireYearsExperience || 'Years of experience', section: 'profile' },
                { key: 'education', label: strings.signalhireEducation || 'Education', section: 'profile' },
                { key: 'keywords', label: strings.signalhireKeywords || 'Keywords', section: 'profile' },
                { key: 'company_name', label: strings.signalhireCompanyName || 'Company name', section: 'company' },
                { key: 'company_location', label: strings.signalhireCompanyLocation || 'Company location', section: 'company' },
                { key: 'industry', label: strings.signalhireIndustry || 'Industry', section: 'company' },
                { key: 'company_size', label: strings.signalhireCompanySize || 'Company size', section: 'company' }
            ];
        }

        function openSignalHireContactSearchSettings() {
            ensureSmartModal();

            var cfg = window.SMarkAdmin || {};
            var strings = cfg.strings || {};
            var savedSettings = cfg.signalhireContactSearchSettings || {};
            var fields = getSignalHireFields(strings);
            var $overlay = $('#smark-smart-modal-overlay');
            var $title = $overlay.find('.smark-smart-modal__title');
            var $body = $overlay.find('.smark-smart-modal__body');

            $title.text(strings.signalhireTitle || 'SignalHire Contact Search Settings');
            $body.empty();

            var $wrap = $('<div/>', { class: 'smark-signalhire-settings' });
            $wrap.append($('<p/>', { class: 'smark-signalhire-settings__intro' }).text(strings.signalhireIntro || 'Fill at least one field to save future contact search settings.'));
            $wrap.append($('<div/>', { class: 'smark-signalhire-settings__notice' }).text(strings.signalhireInactive || 'Search execution is inactive for now; only settings are saved.'));

            var $form = $('<form/>', { class: 'smark-signalhire-settings__form' });
            var sections = [
                { id: 'profile', title: strings.signalhireProfileSection || 'Profile' },
                { id: 'company', title: strings.signalhireCompanySection || 'Company' }
            ];

            sections.forEach(function(section) {
                var $section = $('<section/>', { class: 'smark-signalhire-settings__section' });
                $section.append($('<h3/>').text(section.title));

                var $grid = $('<div/>', { class: 'smark-signalhire-settings__grid' });
                fields.filter(function(field) {
                    return field.section === section.id;
                }).forEach(function(field) {
                    var value = savedSettings[field.key] || '';
                    var $label = $('<label/>');
                    $label.append($('<span/>').text(field.label));
                    $label.append($('<input/>', {
                        type: 'text',
                        name: field.key,
                        value: value,
                        autocomplete: 'off'
                    }));
                    $grid.append($label);
                });

                $section.append($grid);
                $form.append($section);
            });

            var $message = $('<div/>', { class: 'smark-signalhire-settings__message', hidden: true });
            var $actions = $('<div/>', { class: 'smark-signalhire-settings__actions' });
            var $save = $('<button/>', { type: 'submit', class: 'button button-primary' }).text(strings.signalhireSave || 'Save settings');
            $actions.append($save);
            $form.append($message, $actions);
            $wrap.append($form);
            $body.append($wrap);

            $form.on('submit', function(event) {
                event.preventDefault();

                var settings = {};
                var hasValue = false;
                fields.forEach(function(field) {
                    var value = String($form.find('[name="' + field.key + '"]').val() || '').trim();
                    settings[field.key] = value;
                    if (value) {
                        hasValue = true;
                    }
                });

                if (!hasValue) {
                    $message.removeAttr('hidden').removeClass('is-success').addClass('is-error').text(strings.signalhireValidation || 'Fill at least one field.');
                    return;
                }

                $save.prop('disabled', true).text(strings.saving || 'Saving...');
                $message.attr('hidden', true).removeClass('is-success is-error').text('');

                $.post(cfg.ajaxUrl || window.ajaxurl || '', {
                    action: 'smark_save_signalhire_contact_search_settings',
                    nonce: cfg.signalhireSettingsNonce || '',
                    settings: JSON.stringify(settings)
                })
                    .done(function(response) {
                        if (response && response.success) {
                            cfg.signalhireContactSearchSettings = response.data && response.data.settings ? response.data.settings : settings;
                            window.SMarkAdmin = cfg;
                            $message.removeAttr('hidden').removeClass('is-error').addClass('is-success').text(strings.signalhireSaved || 'Search settings saved.');
                            return;
                        }

                        $message.removeAttr('hidden').removeClass('is-success').addClass('is-error').text((response && response.data && response.data.message) || (strings.smartError || 'Save failed.'));
                    })
                    .fail(function(xhr) {
                        var msg = strings.smartError || 'Save failed.';
                        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            msg = xhr.responseJSON.data.message;
                        }
                        $message.removeAttr('hidden').removeClass('is-success').addClass('is-error').text(msg);
                    })
                    .always(function() {
                        $save.prop('disabled', false).text(strings.signalhireSave || 'Save settings');
                    });
            });

            $overlay.show();
        }

        function requestSignalHireContactSearchPanel() {
            document.dispatchEvent(new window.CustomEvent('smark:open-signalhire-contact-search'));
        }

        document.addEventListener('smark:daily-guide-smart-action', function(event) {
            var detail = event.detail || {};
            var key = String(detail.key || '');
            if (key === 'email_contacts_daily') {
                requestSignalHireContactSearchPanel();
            }
        });

        // Daily Guide "Smart action" buttons (dashboard)
        $(document).on('click', '.daily-guide-btn--smart', function(e) {
            e.preventDefault();

            var cfg = window.SMarkAdmin || {};
            var strings = cfg.strings || {};
            var key = String($(this).attr('data-smark-daily-guide-key') || '');

            if (!cfg.ajaxUrl || !cfg.nonce) {
                showMessage(strings.smartError || 'Smart action is not available right now.', 'error');
                return;
            }

            if (!key) {
                openSmartModalResult(
                    strings.smartTitle || 'Smart action',
                    '',
                    strings.smartNotReady || 'Smart action is not implemented for this item yet.',
                    '',
                    []
                );
                return;
            }

            if (key === 'email_contacts_daily') {
                requestSignalHireContactSearchPanel();
                return;
            }

            var supportedKeys = {
                keyword_no_page: true,
                gap_transfer: true
            };

            if (!supportedKeys[key]) {
                var notReadyMsg = strings.smartNotReady || 'Smart action is not implemented for this item yet.';
                notReadyMsg += (cfg.lang === 'fa' ? ('\nکلید: ' + key) : ('\nKey: ' + key));
                openSmartModalResult(
                    strings.smartTitle || 'Smart action',
                    '',
                    notReadyMsg,
                    '',
                    []
                );
                return;
            }

            var $btn = $(this);
            if ($btn.data('busy')) {
                return;
            }
            $btn.data('busy', true).prop('disabled', true);

            openSmartModalLoading(strings.smartTitle || 'انجام هوشمند', strings.smartRunning || 'Running smart action…');

            $.post(cfg.ajaxUrl, {
                action: 'smark_daily_guide_smart_action',
                nonce: cfg.nonce,
                key: key
            })
                .done(function(resp) {
                    if (!resp || !resp.success) {
                        var msg = (resp && resp.data && resp.data.message) ? String(resp.data.message) : (strings.smartError || 'Smart action failed.');
                        var stage = (resp && resp.data && resp.data.stage) ? String(resp.data.stage) : '';
                        if (stage) {
                            msg += (cfg.lang === 'fa' ? ('\nمرحله: ' + stage) : ('\nStage: ' + stage));
                        }
                        openSmartModalResult(strings.smartTitle || 'انجام هوشمند', '', msg, '', []);
                        return;
                    }

                    var kw = (resp && resp.data && resp.data.keyword) ? String(resp.data.keyword) : '';
                    var okTitle = strings.smartTitleDone || (cfg.lang === 'fa' ? 'نتیجه انجام هوشمند' : 'Smart action result');
                    var aiResponse = (resp && resp.data && resp.data.aiResponse) ? String(resp.data.aiResponse) : '';
                    var prompt = (resp && resp.data && resp.data.prompt) ? String(resp.data.prompt) : '';
                    var sources = (resp && resp.data && resp.data.sources && resp.data.sources.length) ? resp.data.sources : [];
                    openSmartModalResult(okTitle, kw ? (cfg.lang === 'fa' ? ('کلمه انتخاب‌شده: ' + kw) : ('Keyword: ' + kw)) : '', aiResponse || (strings.smartDone || 'Smart action completed.'), prompt, sources);

                    if (resp.data && resp.data.contentManagementUrl) {
                        try {
                            window.open(String(resp.data.contentManagementUrl), '_blank', 'noopener,noreferrer');
                        } catch (e) {}
                    }
                })
                .fail(function(xhr) {
                    var msg = strings.smartError || 'Smart action failed.';
                    var stage = '';

                    try {
                        if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
                            if (xhr.responseJSON.data.message) {
                                msg = String(xhr.responseJSON.data.message);
                            }
                            if (xhr.responseJSON.data.stage) {
                                stage = String(xhr.responseJSON.data.stage);
                            }
                        } else if (xhr && xhr.responseText && String(xhr.responseText).trim() && String(xhr.responseText).trim() !== '-1') {
                            var text = String(xhr.responseText).trim();
                            if (text.length > 600) {
                                text = text.slice(0, 600) + '…';
                            }
                            msg = text;
                        }
                    } catch (e) {}

                    if (stage) {
                        msg += (cfg.lang === 'fa' ? ('\nمرحله: ' + stage) : ('\nStage: ' + stage));
                    } else if (xhr && xhr.status) {
                        msg += (cfg.lang === 'fa' ? ('\nکد خطا: ' + String(xhr.status)) : ('\nHTTP: ' + String(xhr.status)));
                    }

                    openSmartModalResult(strings.smartTitle || 'انجام هوشمند', '', msg, '', []);
                })
                .always(function() {
                    $btn.data('busy', false).prop('disabled', false);
                });
        });

        // Add form validation if needed
        $('.smark-form').on('submit', function(e) {
            if (!validateForm($(this))) {
                e.preventDefault();
            }
        });
    }

    /**
     * Handle admin actions
     */
    function handleAction(action) {
        switch(action) {
            case 'test':
                showMessage('This is a test message!', 'success');
                break;
            default:
                break;
        }
    }

    /**
     * Validate form
     */
    function validateForm($form) {
        var isValid = true;

        $form.find('[required]').each(function() {
            if ($(this).val() === '') {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });

        return isValid;
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        type = type || 'info';
        var alertClass = 'notice-' + type;

        var $message = $('<div class="notice ' + alertClass + ' is-dismissible"></div>');
        $message.append($('<p/>').text(String(message || '')));
        $message.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
        $('.wrap').prepend($message);

        $message.on('click', '.notice-dismiss', function() {
            $message.remove();
        });
    }

    /**
     * Utility functions
     */
    window.SaeedAdmin = {
        showMessage: showMessage,
        validateForm: validateForm
    };

})(jQuery);
