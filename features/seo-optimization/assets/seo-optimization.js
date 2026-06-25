(function ($) {
    'use strict';

    const config = window.smarkSeoOptimization || {};

    function initSeoPage() {
        const $page = $('.smark-seo-optimization-page');
        if (!$page.length) {
            return;
        }

        function fixFooterLayout() {
            const wpBody = document.querySelector('#wpbody');
            const wpBodyContent = document.querySelector('#wpbody-content');
            const wrap = document.querySelector('.wrap.smark-seo-optimization-page');
            const footer = document.querySelector('.smark-version-footer');

            if (wpBody && wpBodyContent) {
                wpBodyContent.style.height = getComputedStyle(wpBody).height;
                wpBodyContent.style.minHeight = wpBodyContent.style.height;
                wpBodyContent.style.float = 'none';
                wpBodyContent.style.paddingBottom = '0';
            }

            if (wpBody && wrap) {
                wrap.style.height = getComputedStyle(wpBody).height;
                wrap.style.minHeight = wrap.style.height;
                wrap.style.float = 'none';
            }

            if (wrap) {
                wrap.style.display = 'flex';
                wrap.style.flexDirection = 'column';
            }

            if (footer) {
                footer.style.marginTop = 'auto';
            }
        }

        let notesTimer = null;
        const debounceDelay = 600;

        $page.on('input', '.seo-step-notes', function () {
            const $textarea = $(this);
            const $container = $textarea.closest('.seo-notes');

            if ($container.find('.seo-saving-indicator').length === 0) {
                $container.append(`<span class="seo-saving-indicator">${config.strings.saving}</span>`);
            } else {
                $container.find('.seo-saving-indicator').text(config.strings.saving).show();
            }

            clearTimeout(notesTimer);
            notesTimer = setTimeout(() => {
                const payload = {
                    action: 'smark_seo_save_notes',
                    nonce: config.nonce,
                    step: $textarea.data('step'),
                    notes: $textarea.val()
                };

                $.post(config.ajaxUrl, payload)
                    .done((response) => {
                        if (response && response.success) {
                            $container.find('.seo-saving-indicator').text(config.strings.saved);
                            setTimeout(() => {
                                $container.find('.seo-saving-indicator').fadeOut(200);
                            }, 1200);
                        } else {
                            throw new Error();
                        }
                    })
                    .fail(() => {
                        $container.find('.seo-saving-indicator').text(config.strings.error);
                    });
            }, debounceDelay);
        });


        // Handle language selection change
        $page.on('change', '#smark_language_select', function() {
            const selectedLanguage = $(this).val();

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smark_seo_save_language',
                    nonce: config.nonce,
                    language: selectedLanguage
                },
                success: function(response) {
                    if (response && response.success) {
                        location.reload();
                    }
                },
                error: function(xhr, status, error) {
                }
            });
        });

        fixFooterLayout();

        // Run layout fix multiple times to ensure it works (WordPress admin layout can shift after load).
        setTimeout(fixFooterLayout, 100);
        setTimeout(fixFooterLayout, 500);
        setTimeout(fixFooterLayout, 1000);
    }

    $(document).ready(initSeoPage);
})(jQuery);
