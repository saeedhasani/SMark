(function ($) {
    const strings = (window.SMarkKeywordGap && SMarkKeywordGap.strings) ? SMarkKeywordGap.strings : {};
    const isRTL = (window.SMarkKeywordGap && SMarkKeywordGap.lang) ? String(SMarkKeywordGap.lang) === 'fa' : $('body').hasClass('rtl');

    const viewState = {
        rawKeywords: [],
        competitorId: 0,
        query: '',
        kdMin: null,
        kdMax: null,
        kdSort: ''
    };

    function parseNullableNumber(value) {
        if (value === null || value === undefined) return null;
        const raw = String(value).trim();
        if (!raw) return null;
        const num = Number(raw);
        return Number.isFinite(num) ? num : null;
    }

    function normalizeKdBounds() {
        if (viewState.kdMin !== null && viewState.kdMax !== null && viewState.kdMin > viewState.kdMax) {
            const tmp = viewState.kdMin;
            viewState.kdMin = viewState.kdMax;
            viewState.kdMax = tmp;
        }
    }

    function syncDifficultyFilterUI() {
        const $th = $('#smarkCompetitorKeywordsTable th.kg-difficulty-filter-header');
        if (!$th.length) return;

        const hasActive = (viewState.kdMin !== null) || (viewState.kdMax !== null) || !!viewState.kdSort;
        $th.toggleClass('has-active-filter', hasActive);

        const $menu = $th.find('.kg-difficulty-filter-menu');
        $menu.find('.kg-difficulty-filter-input[data-field="min"]').val(viewState.kdMin !== null ? String(viewState.kdMin) : '');
        $menu.find('.kg-difficulty-filter-input[data-field="max"]').val(viewState.kdMax !== null ? String(viewState.kdMax) : '');

        $menu.find('.kg-difficulty-sort-option').removeClass('is-active').attr('aria-checked', 'false');
        if (viewState.kdSort) {
            $menu.find(`.kg-difficulty-sort-option[data-sort="${viewState.kdSort}"]`).addClass('is-active').attr('aria-checked', 'true');
        }
    }

    function closeDifficultyFilterMenu() {
        const $th = $('#smarkCompetitorKeywordsTable th.kg-difficulty-filter-header');
        if (!$th.length) return;
        $th.find('.kg-difficulty-filter-menu').removeClass('is-open');
        $th.find('.kg-difficulty-filter-toggle').attr('aria-expanded', 'false');
    }

    function showNotification(message, type = 'info', options = {}) {
        let $notice = $('.smark-notification');
        if (!$notice.length) {
            $notice = $('<div class="smark-notification" role="status" aria-live="polite" />').appendTo('body');
        }

        $notice.removeClass('success error info visible').addClass(type).empty();
        const $body = $('<div class="smark-notification__body" />').text(message);
        const $close = $(
            '<button type="button" class="smark-notification__close" aria-label="Close notification"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>'
        );
        $close.on('click', () => {
            clearTimeout($notice.data('timeout'));
            $notice.removeClass('visible');
            setTimeout(() => {
                if (!$notice.hasClass('visible')) {
                    $notice.remove();
                }
            }, 300);
        });
        $notice.append($body, $close).addClass('visible');
        if (isRTL) {
            $notice.addClass('rtl');
        } else {
            $notice.removeClass('rtl');
        }

        clearTimeout($notice.data('timeout'));
        $notice.data('timeout', null);
    }

    function request(action, data, method) {
        return $.ajax({
            url: SMarkKeywordGap.ajaxUrl,
            type: method || 'POST',
            dataType: 'json',
            data: Object.assign(
                {
                    action: action,
                    nonce: SMarkKeywordGap.nonce
                },
                data || {}
            )
        });
    }

    function requestUpload(competitorId, file) {
        const form = new FormData();
        form.append('action', 'SMARK_keyword_gap_upload_competitor_keywords');
        form.append('nonce', SMarkKeywordGap.nonce);
        form.append('competitor_id', String(competitorId));
        form.append('file', file);

        return $.ajax({
            url: SMarkKeywordGap.ajaxUrl,
            type: 'POST',
            data: form,
            processData: false,
            contentType: false,
            dataType: 'json'
        });
    }

    function openModal(selector) {
        $(selector).css('display', 'flex');
    }

    function closeModal(selector) {
        $(selector).hide();
    }

    function setLoading(selector, isLoading) {
        const $el = $(selector);
        if (!$el.length) return;
        $el.toggle(!!isLoading);
    }

    $(document).on('click', '[data-close-alert]', function () {
        const selector = String($(this).attr('data-close-alert') || '');
        if (!selector) return;
        $(selector).hide();
    });

    function escHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderCompetitors(competitors) {
        const $tbody = $('#smarkKeywordGapCompetitorsTable tbody');
        const $empty = $('#smarkKeywordGapEmpty');

        $tbody.empty();

        if (!competitors || !competitors.length) {
            $empty.show();
            return;
        }

        $empty.hide();

        competitors.forEach(row => {
            const id = Number(row.id || 0);
            const domain = String(row.domain || '');
            const count = Number(row.keywords_count || 0);

            const $tr = $('<tr/>').attr('data-id', id).attr('data-domain', domain).attr('data-count', count);

            const $domain = $('<td/>').text(domain);

            const $actionsTd = $('<td/>').addClass('kg-actions-cell');
            const $actions = $('<div/>').addClass('kg-actions');
            const $count = $('<span/>').addClass('kg-count').text(`${count || 0}`);

            $actions.append($count);

            const $gapFinder = $('<button/>', { type: 'button' })
                .addClass('btn btn-outline kg-gap-finder-btn')
                .attr('title', strings.mark_cost_tooltip || (isRTL ? 'شامل ۱ مارک' : 'Includes 1 Mark'))
                .append($('<span/>').addClass('dashicons dashicons-search'))
                .append(document.createTextNode(` ${strings.gap_finder || 'Keyword Gap Finder'}`))
                .append($('<span/>', { class: 'kg-smark-cost-badge', text: '1' }).attr('aria-hidden', 'true'));

            if (count > 0) {
                const $update = $('<button/>', { type: 'button' })
                    .addClass('btn btn-outline kg-upload-btn')
                    .append($('<span/>').addClass('dashicons dashicons-update'))
                    .append(document.createTextNode(` ${strings.update_keywords || 'Update keywords'}`));

                const $view = $('<button/>', { type: 'button' })
                    .addClass('btn btn-outline kg-view-btn')
                    .append($('<span/>').addClass('dashicons dashicons-visibility'))
                    .append(document.createTextNode(` ${strings.view_keywords || 'View keywords'}`));

                $actions.append($update, $gapFinder, $view);
            } else {
                const $upload = $('<button/>', { type: 'button' })
                    .addClass('btn btn-outline kg-upload-btn')
                    .append($('<span/>').addClass('dashicons dashicons-upload'))
                    .append(document.createTextNode(` ${strings.upload_keywords || 'Upload competitor keywords'}`));

                $actions.append($upload, $gapFinder);
            }

            $actionsTd.append($actions);

            $tr.append($domain, $actionsTd);
            $tbody.append($tr);
        });
    }

    function loadCompetitors() {
        return request('SMARK_keyword_gap_get_competitors', {}, 'GET')
            .done(res => {
                if (!res || !res.success) {
                    const msg = res && res.data && res.data.message ? res.data.message : null;
                    if (msg) {
                        showNotification(String(msg), 'error');
                    }
                    renderCompetitors([]);
                    return;
                }
                renderCompetitors(res.data && res.data.competitors ? res.data.competitors : []);
            })
            .fail(() => {
                showNotification(strings.error || 'Error', 'error');
                renderCompetitors([]);
            });
    }

    function loadCompetitorKeywords(competitorId, query) {
        viewState.competitorId = Number(competitorId || 0);
        viewState.query = String(query || '');
        setLoading('#smarkViewCompetitorKeywordsLoading', true);
        $('#smarkCompetitorKeywordsEmpty').hide();

        return request('SMARK_keyword_gap_get_competitor_keywords', { competitor_id: competitorId, q: query || '' }, 'GET')
            .done(res => {
                const $tbody = $('#smarkCompetitorKeywordsTable tbody');
                $tbody.empty();

                if (!res || !res.success) {
                    viewState.rawKeywords = [];
                    $('#smarkCompetitorKeywordsEmpty').show();
                    syncDifficultyFilterUI();
                    return;
                }

                const keywords = (res.data && res.data.keywords) ? res.data.keywords : [];
                if (!keywords.length) {
                    viewState.rawKeywords = [];
                    $('#smarkCompetitorKeywordsEmpty').show();
                    syncDifficultyFilterUI();
                    return;
                }

                const placeholder = '—';

                const formatIntent = (value) => {
                    if (value === null || value === undefined) return placeholder;
                    const raw = String(value).trim();
                    if (!raw) return placeholder;

                    // Semrush "Intents" can be a single number or comma-separated numbers.
                    const parts = raw.split(',').map(s => s.trim()).filter(Boolean);
                    if (!parts.length) return raw;

                    const mapEn = {
                        '0': 'Commercial',
                        '1': 'Informational',
                        '2': 'Navigational',
                        '3': 'Transactional'
                    };
                    const mapFa = {
                        '0': 'تجاری',
                        '1': 'اطلاعاتی',
                        '2': 'ناوبری',
                        '3': 'تراکنشی'
                    };
                    const map = isRTL ? mapFa : mapEn;

                    const labels = parts.map(p => map[p] || p);
                    return labels.join(isRTL ? '، ' : ', ');
                };
                const normalizeRow = (row) => {
                    if (typeof row === 'string') {
                        return {
                            keyword: row,
                            intent: null,
                            volume: null,
                            keyword_difficulty: null,
                            cpc_usd: null,
                            serp_features: null,
                            used_in_project: false,
                            used_in_keyword_research: false,
                            inappropriate: false
                        };
                    }

                    if (!row || typeof row !== 'object') {
                        return {
                            keyword: '',
                            intent: null,
                            volume: null,
                            keyword_difficulty: null,
                            cpc_usd: null,
                            serp_features: null,
                            used_in_project: false,
                            used_in_keyword_research: false,
                            inappropriate: false
                        };
                    }

                    const keyword = (row.keyword !== undefined) ? row.keyword : (Array.isArray(row) ? row[0] : '');
                    return {
                        keyword: keyword || '',
                        intent: (row.intent !== undefined) ? row.intent : (Array.isArray(row) ? row[1] : null),
                        volume: (row.volume !== undefined) ? row.volume : (Array.isArray(row) ? row[2] : null),
                        keyword_difficulty: (row.keyword_difficulty !== undefined) ? row.keyword_difficulty : (Array.isArray(row) ? row[3] : null),
                        cpc_usd: (row.cpc_usd !== undefined) ? row.cpc_usd : (Array.isArray(row) ? row[4] : null),
                        serp_features: (row.serp_features !== undefined) ? row.serp_features : (Array.isArray(row) ? row[5] : null),
                        used_in_project: !!row.used_in_project,
                        used_in_keyword_research: !!row.used_in_keyword_research,
                        inappropriate: !!row.inappropriate
                    };
                };

                const formatValue = (value) => {
                    if (value === null || value === undefined) return placeholder;
                    const text = String(value).trim();
                    return text === '' ? placeholder : text;
                };

                viewState.rawKeywords = keywords;
                normalizeKdBounds();

                const min = viewState.kdMin;
                const max = viewState.kdMax;
                const sortDir = viewState.kdSort === 'desc' ? -1 : 1;

                let rows = keywords.map(k => normalizeRow(k));

                if (min !== null || max !== null) {
                    rows = rows.filter(r => {
                        const kd = parseNullableNumber(r.keyword_difficulty);
                        if (kd === null) return false;
                        if (min !== null && kd < min) return false;
                        if (max !== null && kd > max) return false;
                        return true;
                    });
                }

                if (viewState.kdSort === 'asc' || viewState.kdSort === 'desc') {
                    rows = rows
                        .map((r, idx) => ({ r, idx, kd: parseNullableNumber(r.keyword_difficulty) }))
                        .sort((a, b) => {
                            const aNull = a.kd === null;
                            const bNull = b.kd === null;
                            if (aNull && bNull) return a.idx - b.idx;
                            if (aNull) return 1;
                            if (bNull) return -1;
                            if (a.kd === b.kd) return a.idx - b.idx;
                            return (a.kd - b.kd) * sortDir;
                        })
                        .map(x => x.r);
                }

                if (!rows.length) {
                    $('#smarkCompetitorKeywordsEmpty').show();
                    syncDifficultyFilterUI();
                    return;
                }

                const serpFeaturesMapEn = {
                    '0': 'Featured snippet',
                    '1': 'Knowledge panel',
                    '2': 'Knowledge card',
                    '3': 'Reviews',
                    '4': 'Instant answer',
                    '5': 'Image pack',
                    '6': 'Sitelinks',
                    '7': 'Local pack',
                    '8': 'Top stories',
                    '9': 'Video',
                    '10': 'Tweet',
                    '11': 'People also ask',
                    '12': 'Shopping ads',
                    '13': 'Maps',
                    '14': 'Featured video',
                    '15': 'Carousel',
                    '16': 'Related questions',
                    '17': 'Google flights',
                    '18': 'Hotel pack',
                    '19': 'Jobs',
                    '20': 'Twitter carousel',
                    '21': 'People also search for',
                    '22': 'Google ads (top)',
                    '23': 'Google ads (bottom)',
                    '24': 'Google shopping',
                    '25': 'Knowledge panel (music)',
                    '26': 'Knowledge panel (movies)',
                    '27': 'Knowledge panel (shopping)',
                    '28': 'Knowledge panel (social)',
                    '29': 'Knowledge panel (sports)',
                    '30': 'Knowledge panel (travel)',
                    '31': 'Knowledge panel (TV series)',
                    '32': 'Featured snippet (multiple)',
                    '34': 'Popular products',
                    '35': 'Discussions and forums',
                    '36': 'Related searches',
                    '37': 'Google posts',
                    '38': 'Knowledge panel (books)',
                    '39': 'Knowledge panel (education)',
                    '40': 'Knowledge panel (finance)',
                    '41': 'Knowledge panel (health)',
                    '42': 'Knowledge panel (jobs)',
                    '43': 'Knowledge panel (local)',
                    '44': 'Knowledge panel (news)',
                    '45': 'Knowledge panel (podcasts)',
                    '46': 'Knowledge panel (products)',
                    '47': 'Knowledge panel (recipes)',
                    '48': 'Knowledge panel (science)',
                    '49': 'Knowledge panel (technology)',
                    '50': 'Knowledge panel (weather)',
                    '51': 'Knowledge panel (web stories)',
                    '52': 'AI overview'
                };

                const serpFeaturesMapFa = {
                    '0': 'اسنیپت ویژه',
                    '1': 'پنل دانش',
                    '2': 'کارت دانش',
                    '3': 'نقد و بررسی‌ها',
                    '4': 'پاسخ فوری',
                    '5': 'بسته تصاویر',
                    '6': 'سایت‌لینک‌ها',
                    '7': 'بسته محلی',
                    '8': 'اخبار برتر',
                    '9': 'ویدئو',
                    '10': 'توییت',
                    '11': 'مردم همچنین می‌پرسند',
                    '12': 'تبلیغات خرید',
                    '13': 'نقشه‌ها',
                    '14': 'ویدئوی ویژه',
                    '15': 'کاروسل',
                    '16': 'سوالات مرتبط',
                    '17': 'پروازهای گوگل',
                    '18': 'بسته هتل',
                    '19': 'فرصت‌های شغلی',
                    '20': 'کاروسل توییتر',
                    '21': 'مردم همچنین جستجو می‌کنند',
                    '22': 'تبلیغات گوگل (بالا)',
                    '23': 'تبلیغات گوگل (پایین)',
                    '24': 'خرید گوگل',
                    '25': 'پنل دانش (موسیقی)',
                    '26': 'پنل دانش (فیلم‌ها)',
                    '27': 'پنل دانش (خرید)',
                    '28': 'پنل دانش (شبکه اجتماعی)',
                    '29': 'پنل دانش (ورزش)',
                    '30': 'پنل دانش (سفر)',
                    '31': 'پنل دانش (سریال)',
                    '32': 'اسنیپت ویژه (چندگانه)',
                    '34': 'محصولات محبوب',
                    '35': 'بحث‌ها و فروم‌ها',
                    '36': 'جستجوهای مرتبط',
                    '37': 'پست‌های گوگل',
                    '38': 'پنل دانش (کتاب‌ها)',
                    '39': 'پنل دانش (آموزش)',
                    '40': 'پنل دانش (مالی)',
                    '41': 'پنل دانش (سلامت)',
                    '42': 'پنل دانش (شغل‌ها)',
                    '43': 'پنل دانش (محلی)',
                    '44': 'پنل دانش (اخبار)',
                    '45': 'پنل دانش (پادکست‌ها)',
                    '46': 'پنل دانش (محصولات)',
                    '47': 'پنل دانش (دستورپخت)',
                    '48': 'پنل دانش (علم)',
                    '49': 'پنل دانش (فناوری)',
                    '50': 'پنل دانش (آب‌وهوا)',
                    '51': 'پنل دانش (وب‌استوری‌ها)',
                    '52': 'مرور هوش مصنوعی'
                };

                const formatSerpFeatures = (value) => {
                    if (Array.isArray(value)) {
                        value = value.join(',');
                    }

                    if (value === null || value === undefined) return '';
                    const raw = String(value).trim();
                    if (!raw) return '';

                    // If it doesn't look like a list of numeric codes, return as-is.
                    if (!/^[0-9\\s,]+$/.test(raw)) {
                        return raw;
                    }

                    const codes = raw.split(',').map(s => s.trim()).filter(Boolean);
                    if (!codes.length) return raw;

                    const map = isRTL ? serpFeaturesMapFa : serpFeaturesMapEn;
                    const labels = codes.map(code => map[code] || (isRTL ? `ویژگی #${code}` : `Feature #${code}`));
                    return labels.join(isRTL ? '، ' : ', ');
                };

                const formatSerp = (value) => {
                    if (Array.isArray(value)) {
                        value = value.join(', ');
                    }
                    const interpreted = formatSerpFeatures(value);
                    const text = interpreted ? interpreted : formatValue(value);
                    if (text === placeholder) return { text, title: '' };
                    if (text.length > 70) {
                        return { text: text.slice(0, 70) + '…', title: text };
                    }
                    return { text, title: '' };
                };

                rows.forEach(row => {
                    const $tr = $('<tr/>');
                    $tr.attr('data-keyword', String(row.keyword || ''));

                    const serp = formatSerp(row.serp_features);
                    const $serpTd = $('<td/>').text(serp.text);
                    if (serp.title) {
                        $serpTd.attr('title', serp.title);
                    }

                    const used = ('used_in_keyword_research' in row) ? !!row.used_in_keyword_research : !!row.used_in_project;
                    const inappropriate = !!row.inappropriate;
                    const $actionsTd = $('<td/>').addClass('kg-row-actions-cell');
                    if (inappropriate) {
                        $tr.addClass('kg-row-inappropriate');
                        $actionsTd.append(
                            $('<span/>')
                                .addClass('kg-inappropriate-label')
                                .text(strings.inappropriate_label || (isRTL ? 'نامناسب' : 'Not suitable'))
                        );
                    } else if (used) {
                        $actionsTd.append($('<span/>').addClass('kg-used-label').text(strings.used_label || (isRTL ? 'استفاده شده' : 'Used')));
                    } else {
                        $actionsTd.append(
                            $('<button/>', { type: 'button' })
                                .addClass('btn btn-primary kg-action-btn kg-use-keyword-btn')
                                .text(strings.use_keyword || (isRTL ? 'استفاده کنید' : 'Use'))
                        );
                    }

                    if (!used && !inappropriate) {
                        $actionsTd.append(
                            $('<button/>', { type: 'button' })
                                .addClass('btn kg-action-btn kg-inappropriate-btn')
                                .text(strings.inappropriate_button || (isRTL ? 'نامناسب' : 'Not suitable'))
                        );
                    }

                    $tr.append($('<td/>').text(formatValue(row.keyword)));
                    $tr.append($('<td/>').text(formatIntent(row.intent)));
                    $tr.append($('<td/>').text(formatValue(row.volume)));
                    $tr.append($('<td/>').text(formatValue(row.keyword_difficulty)));
                    $tr.append($('<td/>').text(formatValue(row.cpc_usd)));
                    $tr.append($serpTd);
                    $tr.append($actionsTd);
                    $tbody.append($tr);
                });

                syncDifficultyFilterUI();
            })
            .always(() => {
                setLoading('#smarkViewCompetitorKeywordsLoading', false);
            });
    }

    function fixFooterLayout() {
        const wpBody = document.querySelector('#wpbody');
        const wpBodyContent = document.querySelector('#wpbody-content');
        const wrap = document.querySelector('.wrap.smark-keyword-gap-page');
        const mainContent = document.querySelector('.wrap.smark-keyword-gap-page .smark-keyword-research-content');
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

        if (mainContent) {
            mainContent.style.flex = '1';
            mainContent.style.display = 'flex';
            mainContent.style.flexDirection = 'column';
        }

        if (footer) {
            footer.style.marginTop = 'auto';
        }
    }

    function bindLanguageSelector() {
        $(document).on('change', '#SMARK_language_select', function () {
            const lang = $(this).val();
            request('SMARK_keyword_gap_save_language', { language: lang }, 'POST')
                .always(function () {
                    window.location.reload();
                });
        });
    }

    function bindModals() {
        $(document).on('click', '[data-close]', function () {
            const target = $(this).attr('data-close');
            if (target) closeModal(target);
        });

        $(document).on('click', '.smark-modal', function (e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
    }

    function bindCompetitorsFlow() {
        $('#smarkAddCompetitorBtn').on('click', function () {
            $('#smarkCompetitorList').val('');
            openModal('#smarkAddCompetitorModal');
        });

        $('#smarkSaveCompetitors').on('click', function () {
            const domains = String($('#smarkCompetitorList').val() || '');
            setLoading('#smarkAddCompetitorLoading', true);
            request('SMARK_keyword_gap_add_competitors', { domains }, 'POST')
                .done(res => {
                    if (!res || !res.success) {
                         const msg = res && res.data && res.data.message ? res.data.message : (strings.error || 'Error');
                         showNotification(String(msg), 'error');
                         return;
                     }
 
                     $tr.find('.kg-inappropriate-btn').remove();

                    closeModal('#smarkAddCompetitorModal');
                    showNotification(strings.competitors_saved || 'Competitors saved.', 'success');
                    const list = res.data && res.data.competitors ? res.data.competitors : null;
                    if (list) {
                        renderCompetitors(list);
                    } else {
                        loadCompetitors();
                    }
                })
                .fail(() => {
                    showNotification(strings.error || 'Error', 'error');
                })
                .always(() => {
                    setLoading('#smarkAddCompetitorLoading', false);
                });
        });

        $(document).on('click', '.kg-upload-btn', function () {
            const $tr = $(this).closest('tr');
            const id = Number($tr.attr('data-id') || 0);
            const domain = String($tr.attr('data-domain') || '');
            if (!id) return;

            $('#smarkUploadCompetitorId').val(String(id));
            $('#smarkCompetitorKeywordsFile').val('');
            $('#smarkUploadModalDomain').text(`${strings.domain_label || 'Domain'}: ${domain}`);
            openModal('#smarkUploadCompetitorKeywordsModal');
        });

        let kdInputTimer = null;

        $(document).on('click', '.kg-difficulty-filter-toggle', function (evt) {
            evt.preventDefault();
            evt.stopPropagation();

            const $th = $(this).closest('th.kg-difficulty-filter-header');
            const $menu = $th.find('.kg-difficulty-filter-menu');
            const isOpen = $menu.hasClass('is-open');

            closeDifficultyFilterMenu();

            if (!isOpen) {
                syncDifficultyFilterUI();
                $menu.addClass('is-open');
                $(this).attr('aria-expanded', 'true');
            }
        });

        $(document).on('click', '.kg-difficulty-filter-menu', function (evt) {
            evt.stopPropagation();
        });

        $(document).on('input', '.kg-difficulty-filter-input', function () {
            const field = String($(this).data('field') || '');
            const val = parseNullableNumber($(this).val());

            if (field === 'min') viewState.kdMin = val;
            if (field === 'max') viewState.kdMax = val;

            normalizeKdBounds();
            syncDifficultyFilterUI();

            clearTimeout(kdInputTimer);
            kdInputTimer = setTimeout(() => {
                const id = Number($('#smarkViewCompetitorId').val() || 0);
                if (id && $('#smarkViewCompetitorKeywordsModal').is(':visible')) {
                    loadCompetitorKeywords(id, String($('#smarkCompetitorKeywordsSearch').val() || ''));
                }
            }, 200);
        });

        $(document).on('click', '.kg-difficulty-sort-option', function (evt) {
            evt.preventDefault();
            evt.stopPropagation();
            const next = String($(this).data('sort') || '');
            viewState.kdSort = (next === 'asc' || next === 'desc') ? next : '';
            syncDifficultyFilterUI();
            closeDifficultyFilterMenu();

            const id = Number($('#smarkViewCompetitorId').val() || 0);
            if (id && $('#smarkViewCompetitorKeywordsModal').is(':visible')) {
                loadCompetitorKeywords(id, String($('#smarkCompetitorKeywordsSearch').val() || ''));
            }
        });

        $(document).on('click', '.kg-difficulty-filter-reset', function (evt) {
            evt.preventDefault();
            evt.stopPropagation();
            viewState.kdMin = null;
            viewState.kdMax = null;
            viewState.kdSort = '';
            syncDifficultyFilterUI();
            closeDifficultyFilterMenu();

            const id = Number($('#smarkViewCompetitorId').val() || 0);
            if (id && $('#smarkViewCompetitorKeywordsModal').is(':visible')) {
                loadCompetitorKeywords(id, String($('#smarkCompetitorKeywordsSearch').val() || ''));
            }
        });

        $(document).on('click', (evt) => {
            const $target = $(evt.target);
            if ($target.closest('#smarkCompetitorKeywordsTable th.kg-difficulty-filter-header').length) {
                return;
            }
            closeDifficultyFilterMenu();
        });

        $('#smarkUploadCompetitorKeywordsSubmit').on('click', function () {
            const competitorId = Number($('#smarkUploadCompetitorId').val() || 0);
            const fileInput = document.getElementById('smarkCompetitorKeywordsFile');
            const file = fileInput && fileInput.files ? fileInput.files[0] : null;

            if (!competitorId || !file) {
                showNotification(strings.error_missing_file || 'Please choose a file to upload.', 'error');
                return;
            }

            setLoading('#smarkUploadCompetitorKeywordsLoading', true);
            requestUpload(competitorId, file)
                .done(res => {
                    if (!res || !res.success) {
                        const msg = res && res.data && res.data.message ? res.data.message : (strings.upload_error || 'Upload failed.');
                        showNotification(String(msg), 'error');
                        return;
                    }
                    closeModal('#smarkUploadCompetitorKeywordsModal');
                    showNotification(strings.upload_success || 'Keywords saved.', 'success');
                    loadCompetitors();
                })
                .fail(() => {
                    showNotification(strings.upload_error || 'Upload failed.', 'error');
                })
                .always(() => {
                    setLoading('#smarkUploadCompetitorKeywordsLoading', false);
                });
        });

        $(document).on('click', '.kg-view-btn', function () {
            const $tr = $(this).closest('tr');
            const id = Number($tr.attr('data-id') || 0);
            const domain = String($tr.attr('data-domain') || '');
            if (!id) return;

            $('#smarkViewCompetitorId').val(String(id));
            $('#smarkCompetitorKeywordsSearch').val('');
            $('#smarkViewModalDomain').text(`${strings.domain_label || 'Domain'}: ${domain}`);

            viewState.rawKeywords = [];
            viewState.competitorId = id;
            viewState.query = '';
            viewState.kdMin = null;
            viewState.kdMax = null;
            viewState.kdSort = '';
            syncDifficultyFilterUI();
            closeDifficultyFilterMenu();

            openModal('#smarkViewCompetitorKeywordsModal');
            loadCompetitorKeywords(id, '');
        });

        $(document).on('click', '.kg-gap-finder-btn', function () {
            const $tr = $(this).closest('tr');
            const id = Number($tr.attr('data-id') || 0);
            const domain = String($tr.attr('data-domain') || '');
            if (!id) return;

            $('#smarkGapFinderCompetitorId').val(String(id));
            $('#smarkGapFinderDomain').text(`${strings.domain_label || 'Domain'}: ${domain}`);
            $('#smarkGapFinderVolMin').val('');
            $('#smarkGapFinderNoResults').hide();
            openModal('#smarkGapFinderModal');
        });

        $('#smarkGapFinderVolMin').on('input', function () {
            $('#smarkGapFinderNoResults').hide();
        });

        $('#smarkGapFinderSubmit').on('click', function () {
            const competitorId = Number($('#smarkGapFinderCompetitorId').val() || 0);
            if (!competitorId) return;

            const volMinRaw = String($('#smarkGapFinderVolMin').val() || '').trim();

            const payload = { competitor_id: competitorId };
            if (volMinRaw !== '') payload.volume_min = volMinRaw;

            setLoading('#smarkGapFinderLoading', true);
            $('#smarkGapFinderSubmit').prop('disabled', true);

            let semrushStarted = false;

            request('SMARK_keyword_gap_consume_mark', { amount: 1 }, 'POST')
                .done(res => {
                    if (!res || !res.success) {
                        const msg = res && res.data && res.data.message ? res.data.message : (strings.error || 'Error');
                        showNotification(String(msg), 'error');
                        return;
                    }

                    semrushStarted = true;

                    request('SMARK_keyword_gap_semrush_finder', payload, 'POST')
                        .done(res2 => {
                            if (!res2 || !res2.success) {
                                const msg = res2 && res2.data && res2.data.message ? res2.data.message : (strings.error || 'Error');
                                showNotification(String(msg), 'error');
                                return;
                            }

                            if (res2 && res2.data && res2.data.no_results) {
                                $('#smarkGapFinderNoResults').show();
                                return;
                            }

                            closeModal('#smarkGapFinderModal');

                            const inserted = res2.data && typeof res2.data.inserted !== 'undefined' ? Number(res2.data.inserted) : 0;
                            const message = (strings.gap_finder_success || 'Keywords fetched successfully.')
                                .replace('{inserted}', String(isNaN(inserted) ? 0 : inserted));
                            showNotification(message, 'success');

                            loadCompetitors();

                            const openId = Number($('#smarkViewCompetitorId').val() || 0);
                            if (openId && openId === competitorId && $('#smarkViewCompetitorKeywordsModal').is(':visible')) {
                                loadCompetitorKeywords(competitorId, String($('#smarkCompetitorKeywordsSearch').val() || ''));
                            }
                        })
                        .fail(() => {
                            showNotification(strings.error || 'Error', 'error');
                        })
                        .always(() => {
                            setLoading('#smarkGapFinderLoading', false);
                            $('#smarkGapFinderSubmit').prop('disabled', false);
                        });
                })
                .fail(xhr => {
                    const status = xhr && xhr.status ? Number(xhr.status) : 0;
                    if (status === 402) {
                        if (window.SMarkMarkModal && typeof window.SMarkMarkModal.open === 'function') {
                            window.SMarkMarkModal.open();
                            return;
                        }
                        showNotification(strings.mark_insufficient || (isRTL ? 'مارک به اندازه کافی ندارید.' : 'You don\'t have enough Mark credits.'), 'warning');
                        return;
                    }
                    const msg = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                        ? xhr.responseJSON.data.message
                        : (strings.error || 'Error');
                    showNotification(String(msg), 'error');
                })
                .always(() => {
                    if (semrushStarted) {
                        return;
                    }
                    setLoading('#smarkGapFinderLoading', false);
                    $('#smarkGapFinderSubmit').prop('disabled', false);
                });
        });

        $('#smarkCompetitorKeywordsSearchButton').on('click', function () {
            const id = Number($('#smarkViewCompetitorId').val() || 0);
            const q = String($('#smarkCompetitorKeywordsSearch').val() || '');
            if (!id) return;
            loadCompetitorKeywords(id, q);
        });

        $('#smarkCompetitorKeywordsSearch').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#smarkCompetitorKeywordsSearchButton').trigger('click');
            }
        });

        $(document).on('click', '.kg-use-keyword-btn', function () {
            const $btn = $(this);
            const $tr = $btn.closest('tr');
            const competitorId = Number($('#smarkViewCompetitorId').val() || 0);
            const keyword = String($tr.attr('data-keyword') || '').trim();
            if (!competitorId || !keyword) return;

            $btn.prop('disabled', true).addClass('is-loading');
            request('SMARK_keyword_gap_use_keyword', { competitor_id: competitorId, keyword }, 'POST')
                .done(res => {
                    if (!res || !res.success) {
                        const msg = res && res.data && res.data.message ? res.data.message : (strings.error || 'Error');
                        showNotification(String(msg), 'error');
                        return;
                    }

                    $btn.replaceWith($('<span/>').addClass('kg-used-label').text(strings.used_label || (isRTL ? 'استفاده شده' : 'Used')));
                    $tr.find('.kg-inappropriate-btn').remove();
                    showNotification(strings.use_success || (isRTL ? 'کلمه کلیدی به تحقیق کلمات کلیدی اضافه شد.' : 'Keyword added.'), 'success');
                })
                .fail(() => {
                    showNotification(strings.error || 'Error', 'error');
                })
                .always(() => {
                    $btn.prop('disabled', false).removeClass('is-loading');
                });
        });

        $(document).on('click', '.kg-inappropriate-btn', function () {
            const $btn = $(this);
            const $tr = $btn.closest('tr');
            const competitorId = Number($('#smarkViewCompetitorId').val() || 0);
            const keyword = String($tr.attr('data-keyword') || '').trim();
            if (!competitorId || !keyword) return;

            $btn.prop('disabled', true).addClass('is-loading');
            $tr.find('.kg-use-keyword-btn').prop('disabled', true);

            request('SMARK_keyword_gap_mark_inappropriate', { competitor_id: competitorId, keyword }, 'POST')
                .done(res => {
                    if (!res || !res.success) {
                        const msg = res && res.data && res.data.message ? res.data.message : (strings.error || 'Error');
                        showNotification(String(msg), 'error');
                        return;
                    }

                    $tr.addClass('kg-row-inappropriate');
                    $tr.find('td.kg-row-actions-cell').empty().append(
                        $('<span/>')
                            .addClass('kg-inappropriate-label')
                            .text(strings.inappropriate_label || (isRTL ? 'نامناسب' : 'Not suitable'))
                    );
                    showNotification(strings.inappropriate_success || (isRTL ? 'به عنوان نامناسب علامت‌گذاری شد.' : 'Marked as not suitable.'), 'info');
                })
                .fail(() => {
                    showNotification(strings.error || 'Error', 'error');
                })
                .always(() => {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    $tr.find('.kg-use-keyword-btn').prop('disabled', false);
                });
        });
    }

    $(function () {
        bindLanguageSelector();
        bindModals();
        bindCompetitorsFlow();
        loadCompetitors();

        fixFooterLayout();
        setTimeout(fixFooterLayout, 100);
        setTimeout(fixFooterLayout, 500);
        setTimeout(fixFooterLayout, 1000);
    });
})(jQuery);
