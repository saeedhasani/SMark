(function ($) {
    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        const $temp = $('<textarea readonly></textarea>').css({
            position: 'absolute',
            left: '-9999px',
            top: '0'
        }).val(text);

        $('body').append($temp);
        $temp[0].select();
        try {
            document.execCommand('copy');
        } catch (e) {
            // ignore
        }
        $temp.remove();
        return Promise.resolve();
    }

    function renderEnabled($box, url) {
        const strings = (window.SMarkForReviewClassic && window.SMarkForReviewClassic.strings) ? window.SMarkForReviewClassic.strings : {};
        const html = [
            '<p style="margin:0 0 8px">' + 'لینک موقت:' + '</p>',
            '<input type="text" class="widefat smark-forreview-url" readonly value="' + String(url).replace(/"/g, '&quot;') + '">',
            '<p style="display:flex;gap:6px;margin:8px 0 0">',
            '<button type="button" class="button smark-forreview-copy">' + (strings.copy || 'کپی') + '</button>',
            '<button type="button" class="button smark-forreview-disable">' + 'لغو' + '</button>',
            '</p>'
        ].join('');

        $box.html(html);
    }

    function renderDisabled($box) {
        const html = [
            '<button type="button" class="button button-primary smark-forreview-enable">' + 'ساخت لینک موقت' + '</button>',
            '<p style="margin:10px 0 0;color:#666;font-size:12px">' + 'لینک ساخته‌شده noindex است و در سایت‌مپ نمایش داده نمی‌شود.' + '</p>'
        ].join('');

        $box.html(html);
    }

    function postAjax(action, postId, nonce) {
        return $.ajax({
            url: window.SMarkForReviewClassic.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: action,
                nonce: nonce,
                postId: postId
            }
        });
    }

    $(document).on('click', '.smark-forreview-metabox .smark-forreview-enable', function () {
        const strings = (window.SMarkForReviewClassic && window.SMarkForReviewClassic.strings) ? window.SMarkForReviewClassic.strings : {};
        const $container = $(this).closest('.smark-forreview-metabox');
        const postId = parseInt($container.data('post-id'), 10) || 0;
        const nonce = String($container.data('nonce') || '');
        if (!postId || !nonce) {
            return;
        }

        $(this).prop('disabled', true).text(strings.creating || 'در حال ساخت...');
        postAjax('SMARK_cm_forreview_enable', postId, nonce)
            .done(function (res) {
                if (res && res.success && res.data && res.data.url) {
                    renderEnabled($container, res.data.url);
                } else {
                    $(this).prop('disabled', false).text('ساخت لینک موقت');
                    alert(strings.error || 'خطا در انجام عملیات');
                }
            }.bind(this))
            .fail(function () {
                $(this).prop('disabled', false).text('ساخت لینک موقت');
                alert(strings.error || 'خطا در انجام عملیات');
            }.bind(this));
    });

    $(document).on('click', '.smark-forreview-metabox .smark-forreview-disable', function () {
        const strings = (window.SMarkForReviewClassic && window.SMarkForReviewClassic.strings) ? window.SMarkForReviewClassic.strings : {};
        const $container = $(this).closest('.smark-forreview-metabox');
        const postId = parseInt($container.data('post-id'), 10) || 0;
        const nonce = String($container.data('nonce') || '');
        if (!postId || !nonce) {
            return;
        }

        $(this).prop('disabled', true);
        postAjax('SMARK_cm_forreview_disable', postId, nonce)
            .done(function (res) {
                if (res && res.success) {
                    renderDisabled($container);
                } else {
                    $(this).prop('disabled', false);
                    alert(strings.error || 'خطا در انجام عملیات');
                }
            }.bind(this))
            .fail(function () {
                $(this).prop('disabled', false);
                alert(strings.error || 'خطا در انجام عملیات');
            }.bind(this));
    });

    $(document).on('click', '.smark-forreview-metabox .smark-forreview-copy', function () {
        const strings = (window.SMarkForReviewClassic && window.SMarkForReviewClassic.strings) ? window.SMarkForReviewClassic.strings : {};
        const $container = $(this).closest('.smark-forreview-metabox');
        const url = String($container.find('.smark-forreview-url').val() || '');
        if (!url) {
            return;
        }

        const $btn = $(this);
        copyText(url).then(function () {
            const old = $btn.text();
            $btn.text(strings.copied || 'کپی شد');
            setTimeout(function () {
                $btn.text(old);
            }, 1200);
        });
    });
})(jQuery);
