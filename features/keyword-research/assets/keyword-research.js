(function($) {
    const settings = window.SMarkKeywordResearch || {};
    const strings = settings.strings || {};
    const state = {
        projects: [],
        activeProject: null,
        bankTotals: {
            total: 0,
            lastUpload: null,
        },
        projectKeywords: [],
        projectKeywordsView: {
            query: '',
            page: 1,
            pageLinkFilter: '',
            rankingUpdatedFilter: '',
            perPage: 10,
            totalPages: 0,
            totalProjectCount: 0,
            filteredCount: 0,
        },
        projectKeywordsLastRequestId: 0,
        rankMathGap: {
            projectId: 0,
            requestId: 0,
            loaded: false,
        },
    };

    function setRankMathGapNotice(text, status) {
        const $notice = $('#smarkRankMathGapNotice');
        if (!$notice.length) {
            return;
        }
        $notice.removeClass('is-info is-ok is-warn is-error');
        const s = String(status || '').trim();
        if (s) {
            $notice.addClass('is-' + s);
        }
        const t = String(text || '').trim();
        if (!t) {
            $notice.hide().text('');
            return;
        }
        $notice.text(t).show();
    }

    function hideRankMathGapNotice() {
        setRankMathGapNotice('', '');
    }

    function updateRankMathGapNotice(projectId, opts = {}) {
        const pid = parseInt(projectId || 0, 10) || 0;
        if (!pid) {
            hideRankMathGapNotice();
            return;
        }

        const force = !!(opts && opts.force);
        if (!force && state.rankMathGap.loaded && state.rankMathGap.projectId === pid) {
            return;
        }

        state.rankMathGap.projectId = pid;
        const requestId = (state.rankMathGap.requestId || 0) + 1;
        state.rankMathGap.requestId = requestId;

        setRankMathGapNotice(strings.rankMathGapLoading || 'Checking Rank Math keywords…', 'info');

        request('SMARK_keyword_rankmath_gap_stats', { project_id: pid, force: force ? 1 : 0, limit: 300 }, 'GET')
            .done((res) => {
                if (requestId !== state.rankMathGap.requestId) {
                    return;
                }

                if (!res || !res.success) {
                    const msg = buildApiErrorMessage(
                        (isPersian() ? 'بررسی اختلاف رنک‌مث' : 'Rank Math gap check'),
                        res,
                        (strings.rankMathGapError || 'Could not check Rank Math keywords right now.'),
                        { action: 'SMARK_keyword_rankmath_gap_stats' }
                    );
                    setRankMathGapNotice(msg, 'error');
                    return;
                }

                const data = res.data || {};
                const missing = parseInt(data.missing_count || 0, 10) || 0;
                const rmTotal = parseInt(data.rm_total || 0, 10) || 0;
                if (missing <= 0) {
                    state.rankMathGap.loaded = true;
                    if (rmTotal <= 0) {
                        setRankMathGapNotice(strings.rankMathGapNoKeywords || 'No Rank Math focus keywords found to compare.', 'warn');
                    } else {
                        setRankMathGapNotice(strings.rankMathGapOk || 'All Rank Math keywords are already in your project keywords.', 'ok');
                    }
                    return;
                }

                const tpl = strings.rankMathGapMessage || '{missing} keywords are set in Rank Math but not in your project keywords.';
                setRankMathGapNotice(String(tpl).replace('{missing}', String(missing)), 'warn');

                const missingKeywords = Array.isArray(data.missing_keywords) ? data.missing_keywords : [];
                renderRankMathGapModal(missingKeywords, {
                    missingTotal: missing,
                    missingLimit: parseInt(data.missing_limit || 0, 10) || missingKeywords.length,
                });
                openModal('#smarkRankMathGapModal');
                state.rankMathGap.loaded = true;
            })
            .fail((xhr) => {
                if (requestId !== state.rankMathGap.requestId) {
                    return;
                }
                const msg = buildAjaxErrorMessage(
                    (isPersian() ? 'بررسی اختلاف رنک‌مث' : 'Rank Math gap check'),
                    xhr,
                    (strings.rankMathGapError || 'Could not check Rank Math keywords right now.'),
                    { action: 'SMARK_keyword_rankmath_gap_stats' }
                );
                setRankMathGapNotice(msg, 'error');
                // Avoid retry loops that can overload the server.
                state.rankMathGap.loaded = true;
            });
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderRankMathGapModal(keywords, meta = {}) {
        const $tbody = $('#smarkRankMathGapTable tbody');
        const $meta = $('#smarkRankMathGapMeta');
        if (!$tbody.length) {
            return;
        }

        const list = Array.isArray(keywords) ? keywords : [];
        $tbody.empty();

        if (!list.length) {
            $tbody.append(`<tr><td colspan="2">${escapeHtml(strings.noResults || 'No results')}</td></tr>`);
        } else {
            list.forEach((kw) => {
                const safe = escapeHtml(kw);
                $tbody.append(
                    `<tr>
                        <td>${safe}</td>
                        <td class="table-actions">
                            <button type="button" class="btn btn-outline smark-rankmath-gap-add" data-keyword-raw="${encodeURIComponent(String(kw || ''))}">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                ${escapeHtml(strings.rankMathGapAdd || 'Review & add')}
                            </button>
                        </td>
                    </tr>`
                );
            });
        }

        const missingTotal = parseInt(meta.missingTotal || 0, 10) || 0;
        const missingLimit = parseInt(meta.missingLimit || 0, 10) || 0;
        if ($meta.length) {
            if (missingTotal > 0 && missingLimit > 0 && missingTotal > missingLimit) {
                $meta.text((strings.rankMathGapShowing || 'Showing {shown} of {total} missing keywords.')
                    .replace('{shown}', String(missingLimit))
                    .replace('{total}', String(missingTotal)));
            } else if (missingTotal > 0) {
                $meta.text(String(missingTotal) + ' ' + (strings.rankMathGapMissingLabel || 'missing keywords found.'));
            } else {
                $meta.text('');
            }
        }
    }

    function normalizePageLinkFilter(value) {
        const v = String(value || '');
        if (v === 'not_checked' || v === 'no_link' || v === 'has_link') {
            return v;
        }
        return '';
    }

    function normalizeRankingUpdatedFilter(value) {
        const v = String(value || '');
        if (v === 'needs_update' || v === 'updated') {
            return v;
        }
        return '';
    }

    function debounce(fn, waitMs) {
        let timer = null;
        return function(...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), Math.max(0, parseInt(waitMs, 10) || 0));
        };
    }

    function applyRankingUpdatedAtStatus($cell, rawDateTime) {
        $cell.removeClass('is-fresh is-stale');

        if (!rawDateTime) {
            return;
        }

        // rawDateTime expected like "YYYY-MM-DD HH:MM:SS" (WP current_time('mysql'))
        const normalized = String(rawDateTime).replace(' ', 'T');
        const updatedAt = new Date(normalized);
        if (isNaN(updatedAt.getTime())) {
            return;
        }

        const now = new Date();
        const diffMs = now.getTime() - updatedAt.getTime();
        const diffDays = diffMs / (1000 * 60 * 60 * 24);

        if (diffDays > 30) {
            $cell.addClass('is-stale');
        } else {
            $cell.addClass('is-fresh');
        }
    }

    function formatLiveRankValue(rankValue, hasBeenFetched) {
        const rank = parseInt(rankValue, 10);
        if (!hasBeenFetched) {
            return '—';
        }
        if (!isNaN(rank) && rank > 0) {
            return String(rank);
        }
        return strings.liveRankNotTop100 || '100+';
    }

    function buildLiveRankCell(itemId, projectId, liveRankValue, liveRankUpdatedAt) {
        const $cell = $('<td/>').addClass('live-ranking-cell');
        const hasBeenFetched = !!String(liveRankUpdatedAt || '').trim();
        const rank = parseInt(liveRankValue, 10);

        if (hasBeenFetched) {
            const label = formatLiveRankValue(rank, true);
            const textClass = (!isNaN(rank) && rank > 0 && rank <= 10) ? 'ranking-text ranking-text-green' : 'ranking-text';
            const $content = $('<span/>').addClass(textClass).text(label);
            const $updateIcon = $('<span/>', {
                class: 'live-ranking-update-icon',
                'data-item-id': itemId,
                'data-project-id': projectId,
                title: strings.liveRankUpdateTitle || 'Update live rank',
                html: '<span class="dashicons dashicons-update"></span>'
            }).css('cursor', 'pointer').css('margin-left', '8px');
            $cell.append($content).append($updateIcon);
            return $cell;
        }

        const $fetchIcon = $('<span/>', {
            class: 'live-ranking-fetch-icon',
            'data-item-id': itemId,
            'data-project-id': projectId,
            title: strings.liveRankFetchTitle || 'Fetch live rank',
            html: '<span class="dashicons dashicons-chart-line"></span>'
        }).css('cursor', 'pointer');
        $cell.append($fetchIcon);
        return $cell;
    }

    function buildMetricCell(itemId, projectId, oursValue, maxValue, updatedAt, type) {
        const metricType = String(type || '').trim();
        const $cell = $('<td/>').addClass('live-metric-cell live-metric-cell-' + metricType);
        const hasBeenFetched = !!String(updatedAt || '').trim();
        const ours = parseInt(oursValue, 10);
        const max = parseInt(maxValue, 10);

        if (hasBeenFetched) {
            const oursLabel = strings.metricOursLabel || 'Ours';
            const maxLabel = strings.metricMaxLabel || 'Highest';
            const $content = $('<span/>').addClass('live-metric-text').html(
                escapeHtml(oursLabel) + ': ' + escapeHtml(String(!isNaN(ours) && ours > 0 ? ours : 0)) + '<br>' +
                escapeHtml(maxLabel) + ': ' + escapeHtml(String(!isNaN(max) && max > 0 ? max : 0))
            );
            const $updateIcon = $('<span/>', {
                class: 'live-metric-update-icon',
                'data-item-id': itemId,
                'data-project-id': projectId,
                'data-metric-type': metricType,
                title: strings.liveRankUpdateTitle || 'Update live rank',
                html: '<span class="dashicons dashicons-update"></span>'
            }).css('cursor', 'pointer').css('margin-left', '8px');
            $cell.append($content).append($updateIcon);
            return $cell;
        }

        const $fetchIcon = $('<span/>', {
            class: 'live-metric-fetch-icon',
            'data-item-id': itemId,
            'data-project-id': projectId,
            'data-metric-type': metricType,
            title: strings.liveRankFetchTitle || 'Fetch live rank',
            html: '<span class="dashicons dashicons-chart-line"></span>'
        }).css('cursor', 'pointer');
        $cell.append($fetchIcon);
        return $cell;
    }

    function toMetricNumber(value) {
        const parsed = parseInt(value, 10);
        return isNaN(parsed) ? null : parsed;
    }

    function shouldShowAddBacklinkButton(item) {
        if (!item || !item.live_metrics_updated_at) {
            return false;
        }

        const refOurs = toMetricNumber(item.live_refdomains_count);
        const refMax = toMetricNumber(item.live_refdomains_top10_max);
        const backlinksOurs = toMetricNumber(item.live_backlinks_count);
        const backlinksMax = toMetricNumber(item.live_backlinks_top10_max);

        const needsRefdomains = refOurs !== null && refMax !== null && refOurs <= refMax;
        const needsBacklinks = backlinksOurs !== null && backlinksMax !== null && backlinksOurs <= backlinksMax;

        return needsRefdomains || needsBacklinks;
    }

    function syncAddBacklinkAction($row, item) {
        if (!$row || !$row.length || !item) {
            return;
        }

        const $actionsWrap = $row.find('.table-actions').first();
        if (!$actionsWrap.length) {
            return;
        }

        const rowTargetPostId = parseInt($row.data('targetPostId') || '0', 10) || 0;
        const rowPageLinkUrl = $row.data('pageLinkUrl') || '';

        $actionsWrap.find('.btn-add-backlink').remove();

        if (!shouldShowAddBacklinkButton(item)) {
            return;
        }

        const $removeBtn = $actionsWrap.find('.btn-remove').first();
        if (!$removeBtn.length) {
            return;
        }

        const $button = $('<button/>', {
            type: 'button',
            class: 'btn btn-outline btn-add-backlink',
            text: strings.addBacklinkLabel || (isPersian() ? 'افزودن بک‌لینک' : 'Add backlink')
        }).data('id', item.id)
            .data('projectId', state.activeProject ? state.activeProject.id : '')
            .data('keyword', item.keyword || '')
            .data('targetPostId', item.page_link_post_id || rowTargetPostId)
            .data('pageLinkUrl', item.page_link_url || rowPageLinkUrl);

        $removeBtn.before($button);
    }

    function showNotification(message, type = 'info', options = {}) {
        const opts = Object.assign(
            {
                sticky: false,
                durationMs: 3000,
            },
            options || {}
        );

        let $notice = $('.smark-notification');
        if (!$notice.length) {
            $notice = $('<div class="smark-notification" />').appendTo('body');
        }
        $notice.removeClass('success error info visible').addClass(type).text(message).addClass('visible');

        // Clear any existing timeout
        clearTimeout($notice.data('timeout'));
        $notice.data('timeout', null);

        if (opts.sticky || opts.durationMs === null) {
            return;
        }

        // Auto-hide
        const timeout = setTimeout(() => {
            $notice.removeClass('visible');
            // Remove from DOM after animation completes
            setTimeout(() => {
                if (!$notice.hasClass('visible')) {
                    $notice.remove();
                }
            }, 300);
        }, Math.max(0, parseInt(opts.durationMs, 10) || 0));
        $notice.data('timeout', timeout);
    }

    function isPersian() {
        return String(settings.currentLang || '').trim().toLowerCase() === 'fa';
    }

    function compactText(text, maxLen = 220) {
        const raw = String(text || '');
        const cleaned = raw.replace(/\s+/g, ' ').trim();
        if (!cleaned) return '';
        const m = Math.max(40, parseInt(maxLen, 10) || 0);
        return cleaned.length > m ? cleaned.slice(0, m - 1) + '…' : cleaned;
    }

    function extractAjaxErrorPayload(xhr) {
        const status = xhr && typeof xhr.status === 'number' ? xhr.status : 0;
        const statusText = xhr && xhr.statusText ? String(xhr.statusText) : '';
        const responseText = xhr && typeof xhr.responseText === 'string' ? xhr.responseText : '';
        const json = xhr && xhr.responseJSON ? xhr.responseJSON : null;
        const data = json && typeof json === 'object' ? (json.data || json) : null;

        const message =
            data && typeof data === 'object' && data.message
                ? String(data.message)
                : '';
        const code =
            data && typeof data === 'object' && data.code
                ? String(data.code)
                : '';

        return { status, statusText, responseText, message, code, json };
    }

    function buildAjaxErrorMessage(stepLabel, xhr, fallbackMessage, meta = {}) {
        const fa = isPersian();
        const step = String(stepLabel || '').trim();
        const fallback = String(fallbackMessage || '').trim() || (fa ? 'عملیات ناموفق بود.' : 'Request failed.');

        const payload = extractAjaxErrorPayload(xhr);
        const responseTrim = String(payload.responseText || '').trim();

        let msg = payload.message ? String(payload.message) : '';
        if (!msg) {
            if (responseTrim === '-1') {
                msg = fa ? 'اعتبارسنجی امنیتی انجام نشد. صفحه را رفرش کنید.' : 'Security check failed. Refresh the page and try again.';
            } else if (responseTrim === '0') {
                msg = fa ? 'اکشن سمت سرور در دسترس نیست یا پاسخ نداد.' : 'Server action is missing or returned an empty response.';
            } else if (/<(html|!doctype)/i.test(responseTrim)) {
                msg = fa ? 'خطای سرور (پاسخ HTML). لاگ سرور را بررسی کنید.' : 'Server returned an HTML error response. Check server logs.';
            } else if (responseTrim) {
                msg = responseTrim;
            }
        }
        if (!msg) {
            if ((payload.status || 0) === 0) {
                msg = fa ? 'ارتباط با سرور برقرار نشد.' : 'Could not reach the server.';
            } else {
                msg = fallback;
            }
        }

        const details = [];
        if (step) {
            details.push((fa ? 'مرحله' : 'Step') + ': ' + step);
        }
        if (meta && meta.action) {
            details.push('action: ' + String(meta.action));
        }
        if (payload.code) {
            details.push((fa ? 'کد' : 'code') + ': ' + payload.code);
        }
        if (payload.status) {
            details.push('HTTP ' + String(payload.status) + (payload.statusText ? ' ' + payload.statusText : ''));
        }

        const headline = compactText(msg, 260);
        const suffix = details.length ? ' (' + details.join(' | ') + ')' : '';
        return compactText(headline + suffix, 320);
    }

    function buildApiErrorMessage(stepLabel, res, fallbackMessage, meta = {}) {
        const fa = isPersian();
        const step = String(stepLabel || '').trim();
        const fallback = String(fallbackMessage || '').trim() || (fa ? 'عملیات ناموفق بود.' : 'Request failed.');

        const data = res && typeof res === 'object' ? (res.data || {}) : {};
        const msg = data && data.message ? String(data.message) : fallback;
        const code = data && data.code ? String(data.code) : '';

        const details = [];
        if (step) {
            details.push((fa ? 'مرحله' : 'Step') + ': ' + step);
        }
        if (meta && meta.action) {
            details.push('action: ' + String(meta.action));
        }
        if (code) {
            details.push((fa ? 'کد' : 'code') + ': ' + code);
        }

        const headline = compactText(msg, 260);
        const suffix = details.length ? ' (' + details.join(' | ') + ')' : '';
        return compactText(headline + suffix, 320);
    }

    function request(action, data = {}, method = 'GET') {
        const payload = Object.assign({}, data, {
            action: action,
            nonce: settings.nonce
        });
        return $.ajax({
            url: settings.ajaxUrl,
            method: method,
            data: method === 'GET' ? payload : $.param(payload),
            dataType: 'json',
            processData: method !== 'POST' || !(data instanceof FormData),
        });
    }

    function coreRequest(action, data = {}, method = 'POST') {
        const payload = Object.assign({}, data, {
            action: action,
            nonce: settings.coreKeywordBankNonce || ''
        });
        return $.ajax({
            url: settings.ajaxUrl,
            method: method,
            data: method === 'GET' ? payload : $.param(payload),
            dataType: 'json',
            processData: method !== 'POST' || !(data instanceof FormData),
        });
    }

    function openModal(selector) {
        $(selector).addClass('active');
    }

    function closeModal(selector) {
        $(selector).removeClass('active');
    }

    function renderProjects() {
        if (!state.activeProject) {
            return;
        }

        $('#selected_project_display .project-name').text(state.activeProject.name || '');
        $('#keywords_card').show();
        $('#empty_state').hide();

        // Rank Math gap check runs on-demand via the "Check" button.
        state.rankMathGap.loaded = false;
        state.rankMathGap.projectId = parseInt(String(state.activeProject.id || '0'), 10) || 0;
        hideRankMathGapNotice();
    }

    function initDefaultProject() {
        const defaultProject = settings.defaultProject || null;
        const defaultId = defaultProject && defaultProject.id ? parseInt(defaultProject.id, 10) : 0;
        if (!defaultId) {
            return false;
        }

        state.activeProject = {
            id: defaultId,
            name: defaultProject.project_name || defaultProject.name || ''
        };

        state.projectKeywordsView.query = '';
        state.projectKeywordsView.page = 1;
        state.projectKeywordsView.pageLinkFilter = '';
        state.projectKeywordsView.rankingUpdatedFilter = '';
        $('#projectKeywordsSearch').val('');

        renderProjects();
        loadProjectKeywords(state.activeProject.id, { page: 1, query: '' });
        return true;
    }

    function renderProjectKeywords(items, meta = {}) {
        const $tbody = $('#projectKeywordsTable tbody');
        const $empty = $('#projectKeywordsEmpty');
        $tbody.empty();

        const totalProjectCount = typeof meta.totalProjectCount === 'number' ? meta.totalProjectCount : null;
        const filteredCount = typeof meta.filteredCount === 'number' ? meta.filteredCount : null;

        if (filteredCount === 0 || !items.length) {
            $empty.addClass('active');
            $('#projectKeywordCount').text(totalProjectCount !== null ? String(totalProjectCount) : '0');
            renderProjectKeywordsPagination(meta);
            return;
        }

        $empty.removeClass('active');
        $('#projectKeywordCount').text(totalProjectCount !== null ? String(totalProjectCount) : String(items.length));

        const $page = $('.smark-keyword-research-page');
        const isRTL = $page.hasClass('rtl') || $page.attr('data-lang') === 'fa';

        items.forEach(item => {
            const $row = $('<tr/>');
            $row.data('targetPostId', item.page_link_post_id || 0);
            $row.data('pageLinkUrl', item.page_link_url || '');

            const $actionsCell = $('<td/>').addClass('table-actions-column');
            const $actionsWrap = $('<div/>').addClass('table-actions');
            const $removeBtn = $('<button/>', {
                type: 'button',
                class: 'btn btn-outline btn-remove',
                text: strings.deleteLabel || 'Delete'
            }).data('id', item.id);

            // Page link cell
            const $pageLinkCell = $('<td/>').addClass('page-link-cell');
            const pageLinkStatus = item.page_link_status || 'not_checked';
            const pageLinkUrl = item.page_link_url || null;

            let $pageLinkIcon;
            if (pageLinkStatus === 'found' && pageLinkUrl) {
                // Green link icon - keyword found
                $pageLinkIcon = $('<a/>', {
                    href: pageLinkUrl,
                    target: '_blank',
                    class: 'page-link-icon page-link-found',
                    title: pageLinkUrl,
                    html: '<span class="dashicons dashicons-admin-links"></span>'
                });
            } else if (pageLinkStatus === 'not_connected') {
                // Red forbidden icon - WordPress not connected
                $pageLinkIcon = $('<span/>', {
                    class: 'page-link-icon page-link-not-connected',
                    title: 'WordPress not connected',
                    html: '<span class="dashicons dashicons-dismiss"></span>'
                });
            } else if (pageLinkStatus === 'not_found') {
                // Red X icon - keyword not found
                $pageLinkIcon = $('<span/>', {
                    class: 'page-link-icon page-link-not-found',
                    title: 'Keyword not found in Rank Math',
                    html: '<span class="dashicons dashicons-no-alt"></span>'
                });
            } else {
                // Gray/loading icon - not checked yet
                $pageLinkIcon = $('<span/>', {
                    class: 'page-link-icon page-link-not-checked',
                    'data-item-id': item.id,
                    'data-project-id': state.activeProject ? state.activeProject.id : '',
                    title: 'Click to check',
                    html: '<span class="dashicons dashicons-admin-links"></span>'
                }).css('cursor', 'pointer');
            }

            $pageLinkCell.append($pageLinkIcon);

            // Ranking cell
            const $rankingCell = $('<td/>').addClass('ranking-cell');
            // Parse ranking data - if null/undefined/empty, use 0
            let rank3month = 0;
            let rank1month = 0;

            // Check if ranking data has been fetched (not null/undefined means it was fetched, even if 0)
            const hasBeenFetched = (item.rank_3month_avg !== null && item.rank_3month_avg !== undefined) ||
                                   (item.rank_1month_avg !== null && item.rank_1month_avg !== undefined);

            if (item.rank_3month_avg !== null && item.rank_3month_avg !== undefined && item.rank_3month_avg !== '') {
                const parsed = parseFloat(item.rank_3month_avg);
                if (!isNaN(parsed)) {
                    rank3month = parsed;
                }
            }

            if (item.rank_1month_avg !== null && item.rank_1month_avg !== undefined && item.rank_1month_avg !== '') {
                const parsed = parseFloat(item.rank_1month_avg);
                if (!isNaN(parsed)) {
                    rank1month = parsed;
                }
            }

            let rankingColorClass = '';
            if (hasBeenFetched) {
                // Show rankings with update icon (even if both are 0)
                // Use arrow based on RTL/LTR direction
                const isRTL = $('.smark-keyword-research-page').hasClass('rtl') || $('.smark-keyword-research-page').attr('data-lang') === 'fa';
                const arrow = isRTL ? ' ← ' : ' → ';
                const rankingText = rank3month.toFixed(1) + arrow + rank1month.toFixed(1);

                // Determine color based on ranking logic
                // Red: if upward trend (rank3month < rank1month and neither is 0) OR if rank1month is 0
                // Green: otherwise
                rankingColorClass = 'ranking-text-green';
                if (rank1month === 0) {
                    rankingColorClass = 'ranking-text-red';
                } else if (rank3month !== 0 && rank1month !== 0 && rank3month < rank1month) {
                    // Upward trend (worse ranking = higher number)
                    rankingColorClass = 'ranking-text-red';
                }

                const $rankingContent = $('<span/>').addClass('ranking-text ' + rankingColorClass).text(rankingText);
                const $updateIcon = $('<span/>', {
                    class: 'ranking-update-icon',
                    'data-item-id': item.id,
                    'data-project-id': state.activeProject ? state.activeProject.id : '',
                    title: 'Update ranking',
                    html: '<span class="dashicons dashicons-update"></span>'
                }).css('cursor', 'pointer').css('margin-left', '8px');
                $rankingCell.append($rankingContent).append($updateIcon);
            } else {
                // Show fetch icon (data has never been fetched)
                const $fetchIcon = $('<span/>', {
                    class: 'ranking-fetch-icon',
                    'data-item-id': item.id,
                    'data-project-id': state.activeProject ? state.activeProject.id : '',
                    title: 'Fetch ranking from Search Console',
                    html: '<span class="dashicons dashicons-chart-line"></span>'
                }).css('cursor', 'pointer');
                $rankingCell.append($fetchIcon);
            }

            const $liveRankingCell = buildLiveRankCell(
                item.id,
                state.activeProject ? state.activeProject.id : '',
                item.live_rank_position,
                item.live_rank_updated_at
            );
            const $refdomainsCell = buildMetricCell(
                item.id,
                state.activeProject ? state.activeProject.id : '',
                item.live_refdomains_count,
                item.live_refdomains_top10_max,
                item.live_metrics_updated_at,
                'refdomains'
            );
            const $backlinksCell = buildMetricCell(
                item.id,
                state.activeProject ? state.activeProject.id : '',
                item.live_backlinks_count,
                item.live_backlinks_top10_max,
                item.live_metrics_updated_at,
                'backlinks'
            );

            // Ranking updated at cell
            const $rankingUpdatedAtCell = $('<td/>').addClass('ranking-updated-at-cell');
            const rankingUpdatedAtText = item.ranking_updated_at_display || item.ranking_updated_at || '—';
            $rankingUpdatedAtCell.text(rankingUpdatedAtText || '—');
            if (rankingUpdatedAtText && rankingUpdatedAtText !== '—') {
                $rankingUpdatedAtCell.attr('title', rankingUpdatedAtText);
            }
            applyRankingUpdatedAtStatus($rankingUpdatedAtCell, item.ranking_updated_at);

            // Content action buttons (Create/Edit) based on page link and ranking status
            const shouldShowCreateContent = pageLinkStatus === 'not_found' || pageLinkStatus === 'not_connected';
            const shouldShowEditContent = pageLinkStatus === 'found' && !!pageLinkUrl && hasBeenFetched && rankingColorClass === 'ranking-text-red';

            // Show "Update keyword data" when bank has newer data than this project's row.
            const localUpdatedAt = item.updated_at || item.ranking_updated_at || '';
            const bankUpdatedAt = item.bank_updated_at || '';
            let shouldShowUpdateKeywordData = false;
            try {
                if (bankUpdatedAt) {
                    const bankD = new Date(String(bankUpdatedAt).replace(' ', 'T'));
                    const localD = localUpdatedAt ? new Date(String(localUpdatedAt).replace(' ', 'T')) : null;
                    if (!isNaN(bankD.getTime()) && (!localD || isNaN(localD.getTime()) || bankD.getTime() > localD.getTime())) {
                        shouldShowUpdateKeywordData = true;
                    }
                }
            } catch (e) {}

            if (shouldShowCreateContent) {
                const $createBtn = $('<button/>', {
                    type: 'button',
                    class: 'btn btn-outline btn-create-content',
                    text: strings.createContentLabel || (isRTL ? 'ایجاد محتوا' : 'Create content')
                }).data('keyword', item.keyword || '').data('projectId', state.activeProject ? state.activeProject.id : '');
                $actionsWrap.append($createBtn);
            }

            if (shouldShowUpdateKeywordData) {
                const $updateDataBtn = $('<button/>', {
                    type: 'button',
                    class: 'btn btn-outline btn-update-keyword-data',
                    text: strings.updateKeywordDataLabel || (isRTL ? 'بروزرسانی داده کلمات کلیدی' : 'Update keyword data')
                }).data('id', item.id).data('projectId', state.activeProject ? state.activeProject.id : '');
                $actionsWrap.append($updateDataBtn);
            }

            const $refreshKeywordBtn = $('<button/>', {
                type: 'button',
                class: 'btn btn-outline btn-refresh-keyword',
                text: strings.refreshKeywordLabel || (isRTL ? 'بروزرسانی کلمه' : 'Refresh keyword')
            }).data('id', item.id).data('projectId', state.activeProject ? state.activeProject.id : '');
            $actionsWrap.append($refreshKeywordBtn);

            if (shouldShowEditContent) {
                const $editBtn = $('<button/>', {
                    type: 'button',
                    class: 'btn btn-outline btn-edit-content',
                    text: strings.editContentLabel || (isRTL ? 'ویرایش محتوا' : 'Edit content')
                }).data('url', pageLinkUrl).data('keyword', item.keyword || '');
                $actionsWrap.append($editBtn);
            }

            if (shouldShowAddBacklinkButton(item)) {
                const $addBacklinkBtn = $('<button/>', {
                    type: 'button',
                    class: 'btn btn-outline btn-add-backlink',
                    text: strings.addBacklinkLabel || (isRTL ? 'افزودن بک‌لینک' : 'Add backlink')
                }).data('id', item.id)
                    .data('projectId', state.activeProject ? state.activeProject.id : '')
                    .data('keyword', item.keyword || '')
                    .data('targetPostId', item.page_link_post_id || 0)
                    .data('pageLinkUrl', item.page_link_url || '');
                $actionsWrap.append($addBacklinkBtn);
            }

            // Keep delete action last
            $actionsWrap.append($removeBtn);
            $actionsCell.append($actionsWrap);

            if (isRTL) {
                $row.append($('<td/>').text(item.keyword));
                $row.append($('<td/>').text(item.intent || '—'));
                $row.append($('<td/>').text(item.volume !== null ? item.volume : '—'));
                $row.append($('<td/>').text(item.keyword_difficulty !== null ? item.keyword_difficulty : '—'));
                $row.append($('<td/>').text(item.cpc_usd !== null ? item.cpc_usd : '—'));
                $row.append($('<td/>').text(item.serp_features || '—'));
                $row.append($rankingCell);
                $row.append($liveRankingCell);
                $row.append($refdomainsCell);
                $row.append($backlinksCell);
                $row.append($rankingUpdatedAtCell);
                $row.append($pageLinkCell);
                $row.append($actionsCell);
            } else {
                $row.append($('<td/>').text(item.keyword));
                $row.append($('<td/>').text(item.intent || '—'));
                $row.append($('<td/>').text(item.volume !== null ? item.volume : '—'));
                $row.append($('<td/>').text(item.keyword_difficulty !== null ? item.keyword_difficulty : '—'));
                $row.append($('<td/>').text(item.cpc_usd !== null ? item.cpc_usd : '—'));
                $row.append($('<td/>').text(item.serp_features || '—'));
                $row.append($rankingCell);
                $row.append($liveRankingCell);
                $row.append($refdomainsCell);
                $row.append($backlinksCell);
                $row.append($rankingUpdatedAtCell);
                $row.append($pageLinkCell);
                $row.append($actionsCell);
            }

            $tbody.append($row);
        });

        renderProjectKeywordsPagination(meta);
    }

    function renderProjectKeywordsPagination(meta = {}) {
        const $pagination = $('#projectKeywordsPagination');
        if (!$pagination.length) {
            return;
        }

        const currentPage = parseInt(meta.page || state.projectKeywordsView.page || 1, 10) || 1;
        const totalPages = parseInt(meta.totalPages || state.projectKeywordsView.totalPages || 0, 10) || 0;

        $pagination.empty();
        if (totalPages <= 1) {
            return;
        }

        const $page = $('.smark-keyword-research-page');
        const isRTL = $page.hasClass('rtl') || $page.attr('data-lang') === 'fa';
        const prevLabel = isRTL ? 'قبلی' : 'Previous';
        const nextLabel = isRTL ? 'بعدی' : 'Next';

        function addButton(label, pageNumber, opts = {}) {
            const $btn = $('<button/>', {
                type: 'button',
                class: 'smark-page-btn' + (opts.active ? ' is-active' : ''),
                text: label,
                disabled: !!opts.disabled,
            });
            if (!opts.disabled && pageNumber) {
                $btn.attr('data-page', pageNumber);
            }
            $pagination.append($btn);
        }

        function addEllipsis() {
            $pagination.append($('<span/>', { text: '…' }));
        }

        addButton(prevLabel, Math.max(1, currentPage - 1), { disabled: currentPage <= 1 });

        const pages = [];
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) {
                pages.push(i);
            }
        } else {
            pages.push(1);
            let start = Math.max(2, currentPage - 2);
            let end = Math.min(totalPages - 1, currentPage + 2);

            if (start > 2) {
                pages.push('…');
            }
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            if (end < totalPages - 1) {
                pages.push('…');
            }
            pages.push(totalPages);
        }

        pages.forEach(p => {
            if (p === '…') {
                addEllipsis();
                return;
            }
            addButton(String(p), p, { active: p === currentPage });
        });

        addButton(nextLabel, Math.min(totalPages, currentPage + 1), { disabled: currentPage >= totalPages });
    }

    function loadProjectKeywords(projectId, options = {}) {
        // Hide empty state and show keywords card
        $('#empty_state').fadeOut(300, function() {
            $('#keywords_card').fadeIn(300);
        });

        const nextQuery = options.query !== undefined ? String(options.query || '') : String(state.projectKeywordsView.query || '');
        const nextPage = options.page !== undefined ? parseInt(options.page, 10) : parseInt(state.projectKeywordsView.page || 1, 10);
        const nextPageLinkFilter = options.pageLinkFilter !== undefined ? normalizePageLinkFilter(options.pageLinkFilter) : normalizePageLinkFilter(state.projectKeywordsView.pageLinkFilter);
        const nextRankingUpdatedFilter = options.rankingUpdatedFilter !== undefined ? normalizeRankingUpdatedFilter(options.rankingUpdatedFilter) : normalizeRankingUpdatedFilter(state.projectKeywordsView.rankingUpdatedFilter);

        state.projectKeywordsView.query = nextQuery;
        state.projectKeywordsView.page = isNaN(nextPage) || nextPage < 1 ? 1 : nextPage;
        state.projectKeywordsView.pageLinkFilter = nextPageLinkFilter;
        state.projectKeywordsView.rankingUpdatedFilter = nextRankingUpdatedFilter;

        const requestId = (state.projectKeywordsLastRequestId || 0) + 1;
        state.projectKeywordsLastRequestId = requestId;

        request('SMARK_keyword_get_project_items', { projectId: projectId, q: state.projectKeywordsView.query, paged: state.projectKeywordsView.page, pageLinkFilter: state.projectKeywordsView.pageLinkFilter, rankingUpdatedFilter: state.projectKeywordsView.rankingUpdatedFilter })
            .done(res => {
                if (requestId !== state.projectKeywordsLastRequestId) {
                    return;
                }
                if (!res.success) {
                    showNotification(strings.noProjectSelected || 'Select a project', 'error');
                    return;
                }

                const data = res.data || {};
                state.projectKeywordsView.perPage = parseInt(data.perPage || state.projectKeywordsView.perPage || 10, 10) || 10;
                state.projectKeywordsView.page = parseInt(data.page || state.projectKeywordsView.page || 1, 10) || 1;
                state.projectKeywordsView.totalPages = parseInt(data.totalPages || 0, 10) || 0;
                state.projectKeywordsView.totalProjectCount = typeof data.totalProjectCount === 'number' ? data.totalProjectCount : (parseInt(data.totalProjectCount, 10) || 0);
                state.projectKeywordsView.filteredCount = typeof data.filteredCount === 'number' ? data.filteredCount : (parseInt(data.filteredCount, 10) || 0);

                renderProjectKeywords(data.items || [], data);
            })
            .fail(() => {
                showNotification(strings.noProjectSelected || 'Select a project', 'error');
            });
    }

    function renderBank(results) {
        const $tbody = $('#smarkBankTable tbody');
        const $empty = $('#bankEmptyState');
        $tbody.empty();

        if (!results.length) {
            $empty.addClass('active');
        } else {
            $empty.removeClass('active');
        }

        const hasProject = !!state.activeProject;

        results.forEach(row => {
            const $tr = $('<tr/>');
            $tr.append($('<td/>').text(row.keyword));
            $tr.append($('<td/>').text(row.intent || '—'));
            $tr.append($('<td/>').text(row.volume !== null ? row.volume : '—'));
            $tr.append($('<td/>').text(row.keyword_difficulty !== null ? row.keyword_difficulty : '—'));
            $tr.append($('<td/>').text(row.cpc_usd !== null ? row.cpc_usd : '—'));
            $tr.append($('<td/>').text(row.serp_features || '—'));

            if (hasProject) {
                const $checkboxCell = $('<td/>').addClass('select-column');
                const keywordLower = row.keyword.toLowerCase();
                const isInProject = state.projectKeywords.indexOf(keywordLower) !== -1;

                const $checkbox = $('<input/>', {
                    type: 'checkbox',
                    class: 'keyword-checkbox',
                    'data-id': row.id,
                    checked: isInProject,
                    disabled: isInProject
                });
                $checkboxCell.append($checkbox);
                $tr.append($checkboxCell);
            }

            $tbody.append($tr);
        });

        updateBankModalFooter();
    }

    function setBankLoading(isLoading) {
        const $loading = $('#smarkBankLoading');
        if (!$loading.length) {
            return;
        }

        if (isLoading) {
            $loading.show().attr('aria-hidden', 'false');
        } else {
            $loading.hide().attr('aria-hidden', 'true');
            toggleBankHint($('#smarkBankSearch').val());
        }
    }

    function updateBankModalFooter() {
        const hasProject = !!state.activeProject;
        const $selectHeader = $('#smarkBankTable th.select-column');
        const $selectCells = $('#smarkBankTable td.select-column');
        const $addButton = $('#smarkAddSelectedKeywords');

        if (hasProject) {
            // Show header column
            if ($selectHeader.length) {
                $selectHeader.removeAttr('style').css('display', 'table-cell');
            }
            // Show checkbox cells
            $selectCells.show();
            // Show add button
            $addButton.show();
        } else {
            // Hide header column
            if ($selectHeader.length) {
                $selectHeader.css('display', 'none');
            }
            // Hide checkbox cells
            $selectCells.hide();
            // Hide add button
            $addButton.hide();
        }
    }

    function refreshBankStats(total, lastUpload) {
        state.bankTotals.total = total;
        state.bankTotals.lastUpload = lastUpload;
        $('#bankTotalKeywords').text(total || 0);
        $('#keywordBankCount').text(total || 0);
        $('#bankLastUpload').text(lastUpload || '—');
    }

    function toggleBankHint(query) {
        const $hint = $('#smarkBankSearchHint');
        if (!$hint.length) {
            return;
        }
        if (String(query || '').trim() === '') {
            $hint.show();
        } else {
            $hint.hide();
        }
    }

    function getBankMatchType() {
        const raw = String($('#smarkBankMatchType').val() || '').toLowerCase().trim();
        return raw === 'exact' ? 'exact' : 'broad';
    }

    function syncBankMatchLabel(matchType = null) {
        const match = matchType ? String(matchType).toLowerCase().trim() : getBankMatchType();
        const $label = $('.smark-bank-modal__match-label');
        if (!$label.length) {
            return;
        }
        $label.text(match === 'exact' ? 'Exact' : 'Broad');
    }

    function loadBank(query = '', matchType = null) {
        const projectId = state.activeProject ? state.activeProject.id : 0;
        const match = matchType ? String(matchType).toLowerCase().trim() : getBankMatchType();
        setBankLoading(true);
        toggleBankHint(query);
        return request('SMARK_keyword_search_bank', { q: query, projectId: projectId, match: match })
            .done(res => {
                if (!res.success) {
                    return;
                }
                state.projectKeywords = res.data.projectKeywords || [];
                renderBank(res.data.results || []);
                refreshBankStats(state.bankTotals.total || 0, state.bankTotals.lastUpload || null);
                updateBankModalFooter();
            })
            .fail(() => {
                showNotification(strings.noResults || 'No results found', 'error');
            })
            .always(() => {
                setBankLoading(false);
            });
    }

    function setRequestLoading(isLoading) {
        const $loading = $('#smarkKeywordRequestLoading');
        if (!$loading.length) {
            return;
        }

        const $btn = $('#smarkKeywordRequestSubmit');
        const $textarea = $('#smarkKeywordRequestList');

        if (isLoading) {
            $loading.show().attr('aria-hidden', 'false');
            $btn.prop('disabled', true);
            $textarea.prop('disabled', true);
        } else {
            $loading.hide().attr('aria-hidden', 'true');
            $btn.prop('disabled', false);
            $textarea.prop('disabled', false);
        }
    }

    function setAddSelectedLoading(isLoading) {
        const $btn = $('#smarkAddSelectedKeywords');
        if (!$btn.length) {
            return;
        }

        if (isLoading) {
            if ($btn.hasClass('is-loading')) {
                return;
            }

            if (!$btn.data('originalHtml')) {
                $btn.data('originalHtml', $btn.html());
                $btn.data('originalText', $btn.text());
            }

            const label = ($btn.data('originalText') || $btn.text() || '').trim();
            $btn.addClass('is-loading').prop('disabled', true);
            $btn.html(`<span class="dashicons dashicons-update dashicons-spin" aria-hidden="true"></span> ${label}`);
        } else {
            const original = $btn.data('originalHtml');
            if (original) {
                $btn.html(original);
            }
            $btn.removeClass('is-loading').prop('disabled', false);
        }
    }

    function parseKeywordRequestText(text) {
        const lines = String(text || '')
            .split(/\r\n|\r|\n/)
            .map(s => s.trim())
            .filter(Boolean);

        const seen = new Set();
        const cleaned = [];

        lines.forEach(line => {
            const key = line.toLowerCase();
            if (seen.has(key)) {
                return;
            }
            seen.add(key);
            cleaned.push(line);
        });

        return cleaned.slice(0, 200);
    }

    function requireProject(action) {
        if (!state.activeProject) {
            showNotification(strings.noProjectSelected || 'Select a project first', 'error');
            return false;
        }
        action();
        return true;
    }

    function bindEvents() {
        $('#smarkRankMathGapCheckBtn').on('click', () => {
            const pid = state.activeProject && state.activeProject.id ? parseInt(state.activeProject.id, 10) : 0;
            if (!pid) {
                showNotification(strings.noProjectSelected || 'Select a project first.', 'error');
                return;
            }
            updateRankMathGapNotice(pid, { force: true });
        });

        $(document).on('click', '.smark-rankmath-gap-add', function() {
            const $btn = $(this);
            const fa = isPersian();
            const stepFetch = fa ? 'دریافت اطلاعات کلمه' : 'Fetch keyword data';
            const stepAdd = fa ? 'افزودن به پروژه' : 'Add to project';
            const kwRaw = String($btn.data('keywordRaw') || $btn.data('keyword-raw') || '');
            let keyword = '';
            try {
                keyword = decodeURIComponent(kwRaw);
            } catch (e) {
                keyword = kwRaw;
            }

            const pid = state.activeProject && state.activeProject.id ? parseInt(state.activeProject.id, 10) : 0;
            if (!pid) {
                showNotification(strings.noProjectSelected || 'Select a project first.', 'error');
                return;
            }
            if (!keyword) {
                showNotification(strings.rankMathGapAddError || 'Invalid keyword.', 'error');
                return;
            }

            $btn.prop('disabled', true);
            const oldHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update dashicons-spin"></span>' + escapeHtml(strings.loading || 'Loading...'));

            request('SMARK_keyword_fetch_keyword_for_project', { projectId: pid, keyword: keyword }, 'POST')
                .done((res) => {
                    if (!res || !res.success || !res.data || !res.data.keyword) {
                        const msg = buildApiErrorMessage(stepFetch, res, (strings.rankMathGapAddError || 'Failed to fetch keyword data.'), {
                            action: 'SMARK_keyword_fetch_keyword_for_project',
                        });
                        showNotification(msg, 'error', { durationMs: 9000 });
                        return;
                    }

                    const keywordData = res.data.keyword;

                    request('SMARK_keyword_add_rankmath_missing', { projectId: pid, keyword: keywordData }, 'POST')
                        .done((addRes) => {
                            if (!addRes || !addRes.success) {
                                const msg = buildApiErrorMessage(stepAdd, addRes, (strings.rankMathGapAddError || 'Failed to add keyword.'), {
                                    action: 'SMARK_keyword_add_rankmath_missing',
                                });
                                showNotification(msg, 'error', { durationMs: 9000 });
                                return;
                            }

                            showNotification(strings.rankMathGapAddSuccess || 'Keyword added successfully.', 'success');
                            $btn.closest('tr').remove();
                            loadProjectKeywords(pid, { page: 1, query: '' });
                        })
                        .fail((xhr) => {
                            const msg = buildAjaxErrorMessage(stepAdd, xhr, (strings.rankMathGapAddError || 'Failed to add keyword.'), {
                                action: 'SMARK_keyword_add_rankmath_missing',
                            });
                            showNotification(msg, 'error', { durationMs: 9000 });
                        });
                })
                .fail((xhr) => {
                    const status = xhr && xhr.status ? parseInt(String(xhr.status), 10) : 0;
                    if (status === 402) {
                        if (window.SMarkMarkModal && typeof window.SMarkMarkModal.open === 'function') {
                            window.SMarkMarkModal.open();
                            return;
                        }
                    }
                    const msg = buildAjaxErrorMessage(stepFetch, xhr, (strings.rankMathGapAddError || 'Failed to fetch keyword data.'), {
                        action: 'SMARK_keyword_fetch_keyword_for_project',
                    });
                    showNotification(msg, 'error', { durationMs: 9000 });
                })
                .always(() => {
                    $btn.prop('disabled', false);
                    $btn.html(oldHtml);
                });
        });

        $(document).on('click', '[data-close]', function() {
            const target = $(this).data('close');
            if (target === '#smarkKeywordRequestModal') {
                setRequestLoading(false);
            }
            closeModal(target);
        });

        // Language selector like Social Media page
        $(document).on('change', '#SMARK_language_select', function() {
            const lang = $(this).val();
            $.post(settings.ajaxUrl, {
                action: 'SMARK_keyword_save_language',
                nonce: settings.nonce,
                language: lang
            }).always(() => {
                location.reload();
            });
        });

        $('.open-bank-modal').on('click', () => {
            // Add RTL class to modal if page is RTL
            const $page = $('.smark-keyword-research-page');
            const $modal = $('#smarkBankModal');
            if ($page.hasClass('rtl') || $page.attr('data-lang') === 'fa') {
                $modal.addClass('rtl');
            }
            openModal('#smarkBankModal');
            $('#smarkBankSearch').val('');
            $('#smarkBankMatchType').val('broad');
            syncBankMatchLabel('broad');
            loadBank();
        });

        $(document).on('click', '#smarkBankRequestKeywordBtn', function () {
            const $btn = $(this);
            const fa = isPersian();
            const stepFetch = fa ? 'دریافت اطلاعات کلمه' : 'Fetch keyword data';
            const stepAdd = fa ? 'افزودن به پروژه' : 'Add to project';

            const pid = state.activeProject && state.activeProject.id ? parseInt(state.activeProject.id, 10) : 0;
            if (!pid) {
                showNotification(strings.noProjectSelected || 'Select a project first.', 'error');
                return;
            }

            const keyword = String($('#smarkBankSearch').val() || '').trim();
            if (!keyword) {
                showNotification(strings.noKeywordsSelected || 'Please enter a keyword.', 'error');
                return;
            }

            const keywordLower = keyword.toLowerCase();
            if (state.projectKeywords && state.projectKeywords.indexOf(keywordLower) !== -1) {
                showNotification(strings.noKeywordsAdded || 'No keywords were added.', 'info');
                return;
            }

            if ($btn.data('smarkFetching')) {
                return;
            }

            $btn.data('smarkFetching', true);
            $btn.prop('disabled', true);

            const oldHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-update dashicons-spin"></span>' + escapeHtml(strings.loading || 'Loading...'));

            request('SMARK_keyword_fetch_keyword_for_project', { projectId: pid, keyword: keyword }, 'POST')
                .done((res) => {
                    if (!res || !res.success || !res.data || !res.data.keyword) {
                        const msg = buildApiErrorMessage(stepFetch, res, (strings.refreshKeywordError || 'Failed to fetch keyword data.'), {
                            action: 'SMARK_keyword_fetch_keyword_for_project',
                        });
                        showNotification(msg, 'error', { durationMs: 9000 });
                        return;
                    }

                    const keywordData = res.data.keyword;

                    request('SMARK_keyword_add_rankmath_missing', { projectId: pid, keyword: keywordData }, 'POST')
                        .done((addRes) => {
                            if (!addRes || !addRes.success) {
                                const msg = buildApiErrorMessage(stepAdd, addRes, (strings.keywordsAdded || 'Failed to add keyword.'), {
                                    action: 'SMARK_keyword_add_rankmath_missing',
                                });
                                showNotification(msg, 'error', { durationMs: 9000 });
                                return;
                            }

                            showNotification(strings.keywordsAdded || 'Keywords added to project.', 'success');
                            closeModal('#smarkBankModal');
                            loadProjectKeywords(pid, { page: 1, query: '' });
                        })
                        .fail((xhr) => {
                            const msg = buildAjaxErrorMessage(stepAdd, xhr, (strings.keywordsAdded || 'Failed to add keyword.'), {
                                action: 'SMARK_keyword_add_rankmath_missing',
                            });
                            showNotification(msg, 'error', { durationMs: 9000 });
                        });
                })
                .fail((xhr) => {
                    const status = xhr && xhr.status ? parseInt(String(xhr.status), 10) : 0;
                    if (status === 402) {
                        if (window.SMarkMarkModal && typeof window.SMarkMarkModal.open === 'function') {
                            window.SMarkMarkModal.open();
                            return;
                        }
                        showNotification(strings.markInsufficient || (fa ? 'مارک به اندازه کافی ندارید.' : 'You don\'t have enough Mark credits.'), 'warning');
                        return;
                    }
                    const msg = buildAjaxErrorMessage(stepFetch, xhr, (strings.refreshKeywordError || 'Failed to fetch keyword data.'), {
                        action: 'SMARK_keyword_fetch_keyword_for_project',
                    });
                    showNotification(msg, 'error', { durationMs: 9000 });
                })
                .always(() => {
                    $btn.data('smarkFetching', false);
                    $btn.prop('disabled', false);
                    $btn.html(oldHtml);
                });
        });

        $('#smarkKeywordRequestSubmit').on('click', () => {
            const $textarea = $('#smarkKeywordRequestList');
            const keywords = parseKeywordRequestText($textarea.val());

            if (!keywords.length) {
                showNotification(strings.keywordRequestEmpty || 'Please enter at least one keyword.', 'error');
                return;
            }

            setRequestLoading(true);
            request('SMARK_keyword_request_keywords', { keywords: keywords.join("\n") }, 'POST')
                .done(res => {
                    if (!res || !res.success) {
                        const msg = (res && res.data && res.data.message) ? res.data.message : (strings.keywordRequestFailed || 'Failed to submit your request.');
                        showNotification(msg, 'error');
                        return;
                    }
                    showNotification(strings.keywordRequestSuccess || 'Request submitted.', 'success');
                    closeModal('#smarkKeywordRequestModal');
                    $textarea.val('');
                })
                .fail(() => {
                    showNotification(strings.keywordRequestFailed || 'Failed to submit your request.', 'error');
                })
                .always(() => {
                    setRequestLoading(false);
                });
        });

        $('#smarkBankSearch').on('keypress', function(evt) {
            if (evt.which === 13) {
                evt.preventDefault();
                loadBank($(this).val().trim());
            }
        });

        $('#smarkBankSearchButton').on('click', () => {
            const query = $('#smarkBankSearch').val().trim();
            loadBank(query);
        });

        $('#smarkBankMatchType').on('change', () => {
            const query = ($('#smarkBankSearch').val() || '').trim();
            syncBankMatchLabel();
            loadBank(query);
        });

        const runProjectKeywordsSearch = debounce(() => {
            if (!state.activeProject) {
                return;
            }
            const q = ($('#projectKeywordsSearch').val() || '').trim();
            loadProjectKeywords(state.activeProject.id, { query: q, page: 1 });
        }, 250);

        $('#projectKeywordsSearch').on('input', () => {
            runProjectKeywordsSearch();
        });

        function syncPageLinkFilterUI() {
            const filter = String(state.projectKeywordsView.pageLinkFilter || '');
            const $header = $('#projectKeywordsTable th.page-link-header');
            if (!$header.length) {
                return;
            }
            $header.toggleClass('has-active-filter', !!filter);
            $header.find('.page-link-filter-option').removeClass('is-active').attr('aria-checked', 'false');
            const selector = filter ? `.page-link-filter-option[data-filter="${filter}"]` : '.page-link-filter-option[data-filter=""]';
            $header.find(selector).addClass('is-active').attr('aria-checked', 'true');
        }

        function closePageLinkFilterMenu() {
            const $header = $('#projectKeywordsTable th.page-link-header');
            if (!$header.length) {
                return;
            }
            $header.find('.page-link-filter-menu').removeClass('is-open');
            $header.find('.page-link-filter-toggle').attr('aria-expanded', 'false');
        }

        function syncRankingUpdatedFilterUI() {
            const filter = String(state.projectKeywordsView.rankingUpdatedFilter || '');
            const $header = $('#projectKeywordsTable th.ranking-updated-filter-header');
            if (!$header.length) {
                return;
            }
            $header.toggleClass('has-active-filter', !!filter);
            $header.find('.ranking-updated-filter-option').removeClass('is-active').attr('aria-checked', 'false');
            const selector = filter ? `.ranking-updated-filter-option[data-filter="${filter}"]` : '.ranking-updated-filter-option[data-filter=""]';
            $header.find(selector).addClass('is-active').attr('aria-checked', 'true');
        }

        function closeRankingUpdatedFilterMenu() {
            const $header = $('#projectKeywordsTable th.ranking-updated-filter-header');
            if (!$header.length) {
                return;
            }
            $header.find('.ranking-updated-filter-menu').removeClass('is-open');
            $header.find('.ranking-updated-filter-toggle').attr('aria-expanded', 'false');
        }

        $(document).on('click', '.page-link-filter-toggle', function(evt) {
            evt.preventDefault();
            evt.stopPropagation();
            const $header = $(this).closest('th.page-link-header');
            const $menu = $header.find('.page-link-filter-menu');
            const isOpen = $menu.hasClass('is-open');
            closePageLinkFilterMenu();
            closeRankingUpdatedFilterMenu();
            if (!isOpen) {
                syncPageLinkFilterUI();
                $menu.addClass('is-open');
                $(this).attr('aria-expanded', 'true');
            }
        });

        $(document).on('click', '.page-link-filter-option', function(evt) {
            evt.preventDefault();
            evt.stopPropagation();
            const nextFilter = normalizePageLinkFilter($(this).data('filter'));
            state.projectKeywordsView.pageLinkFilter = nextFilter;
            syncPageLinkFilterUI();
            closePageLinkFilterMenu();
            if (state.activeProject) {
                loadProjectKeywords(state.activeProject.id, { page: 1 });
            }
        });

        $(document).on('click', '.ranking-updated-filter-toggle', function(evt) {
            evt.preventDefault();
            evt.stopPropagation();
            const $header = $(this).closest('th.ranking-updated-filter-header');
            const $menu = $header.find('.ranking-updated-filter-menu');
            const isOpen = $menu.hasClass('is-open');
            closePageLinkFilterMenu();
            closeRankingUpdatedFilterMenu();
            if (!isOpen) {
                syncRankingUpdatedFilterUI();
                $menu.addClass('is-open');
                $(this).attr('aria-expanded', 'true');
            }
        });

        $(document).on('click', '.ranking-updated-filter-option', function(evt) {
            evt.preventDefault();
            evt.stopPropagation();
            const nextFilter = normalizeRankingUpdatedFilter($(this).data('filter'));
            state.projectKeywordsView.rankingUpdatedFilter = nextFilter;
            syncRankingUpdatedFilterUI();
            closeRankingUpdatedFilterMenu();
            if (state.activeProject) {
                loadProjectKeywords(state.activeProject.id, { page: 1 });
            }
        });

        $(document).on('click', () => {
            closePageLinkFilterMenu();
            closeRankingUpdatedFilterMenu();
        });

        $(document).on('click', '#projectKeywordsPagination .smark-page-btn', function() {
            const page = parseInt($(this).attr('data-page') || '0', 10);
            if (!page || !state.activeProject) {
                return;
            }
            if (page === (parseInt(state.projectKeywordsView.page || 1, 10) || 1)) {
                return;
            }
            loadProjectKeywords(state.activeProject.id, { page: page });
        });


        $('#projectKeywordsTable').on('click', '.btn-remove', function() {
            const itemId = $(this).data('id');
            if (!itemId) {
                return;
            }
            if (!confirm(strings.deleteConfirm || 'Remove this keyword?')) {
                return;
            }
            const isRTL = $('.smark-keyword-research-page').hasClass('rtl') || $('.smark-keyword-research-page').attr('data-lang') === 'fa';
            showNotification(strings.deleteInProgress || (isRTL ? 'کلمه کلیدی در حال حذف هست…' : 'Deleting keyword…'), 'info', { sticky: true });
            request('SMARK_keyword_remove_project_item', { itemId: itemId }, 'POST')
                .done(res => {
                    if (!res.success) {
                        showNotification(res.data && res.data.message ? res.data.message : (strings.deleteError || 'Unable to delete'), 'error');
                        return;
                    }
                    showNotification(strings.deleteSuccess || 'Removed', 'success');
                    if (state.activeProject) {
                        loadProjectKeywords(state.activeProject.id);
                    }
                })
                .fail(() => {
                    showNotification(strings.deleteError || 'Unable to delete', 'error');
                });
        });

	        $('#projectKeywordsTable').on('click', '.btn-create-content', function() {
	            const keyword = String($(this).data('keyword') || '').trim();
	            const adminBase = settings.ajaxUrl ? settings.ajaxUrl.replace(/admin-ajax\.php.*$/, '') : '';
	            const qs = new URLSearchParams();
	            qs.set('page', 'smark-content-management');
	            qs.set('cm_create_kw', keyword);
	            window.open(adminBase ? `${adminBase}admin.php?${qs.toString()}` : `admin.php?${qs.toString()}`, '_blank', 'noopener,noreferrer');
	        });

        $('#projectKeywordsTable').on('click', '.btn-edit-content', function() {
            const pageUrl = String($(this).data('url') || '').trim();
            const keyword = String($(this).data('keyword') || '').trim();
	            if (!pageUrl) return;
	
	            // Try to derive admin base from ajaxUrl
	            const adminBase = settings.ajaxUrl ? settings.ajaxUrl.replace(/admin-ajax\.php.*$/, '') : '';
	
	            request('SMARK_keyword_get_edit_url', { url: pageUrl }, 'POST')
	                .done(res => {
	                    const postId = res && res.success && res.data && res.data.post_id ? parseInt(res.data.post_id, 10) : 0;
                    if (adminBase) {
                        const qs = new URLSearchParams();
                        qs.set('page', 'smark-content-management');
                        if (postId) {
                            qs.set('focus_post_id', String(postId));
                        }
                        if (keyword) {
                            qs.set('smark_kw', keyword);
                        }
                        window.open(`${adminBase}admin.php?${qs.toString()}`, '_blank', 'noopener,noreferrer');
                        return;
                    }

                    // Fallback: open public URL if we couldn't resolve admin base
                    window.open(pageUrl, '_blank', 'noopener,noreferrer');
                })
                .fail(() => {
                    if (adminBase) {
                        const qs = new URLSearchParams();
                        qs.set('page', 'smark-content-management');
                        if (keyword) {
                            qs.set('smark_kw', keyword);
                        }
                        window.open(`${adminBase}admin.php?${qs.toString()}`, '_blank', 'noopener,noreferrer');
                        return;
                    }
                    window.open(pageUrl, '_blank', 'noopener,noreferrer');
                });
        });

        $('#projectKeywordsTable').on('click', '.btn-update-keyword-data', function() {
            const itemId = parseInt(String($(this).data('id') || '0'), 10) || 0;
            const projectId = state.activeProject ? parseInt(String(state.activeProject.id || '0'), 10) : 0;
            if (!itemId || !projectId) return;
            const isRTL = $('.smark-keyword-research-page').hasClass('rtl') || $('.smark-keyword-research-page').attr('data-lang') === 'fa';

            request('SMARK_keyword_refresh_keyword_data_from_bank', { itemId: itemId, projectId: projectId }, 'POST')
                .done(res => {
                    if (!res || !res.success) {
                        showNotification(strings.error || 'Error', 'error');
                        return;
                    }
                    showNotification(strings.updated || (isRTL ? 'بروزرسانی شد' : 'Updated'), 'success');
                    loadProjectKeywords(projectId);
                })
                .fail(() => {
                    showNotification(strings.error || 'Error', 'error');
                });
        });

        $('#projectKeywordsTable').on('click', '.btn-refresh-keyword', function() {
            const $btn = $(this);
            const itemId = parseInt(String($btn.data('id') || '0'), 10) || 0;
            const projectId = state.activeProject ? parseInt(String(state.activeProject.id || '0'), 10) : 0;
            if (!itemId || !projectId) return;
            const isRTL = $('.smark-keyword-research-page').hasClass('rtl') || $('.smark-keyword-research-page').attr('data-lang') === 'fa';

            const oldText = $btn.text();
            $btn.prop('disabled', true).text(strings.refreshKeywordProgress || (isRTL ? 'در حال بروزرسانی...' : 'Refreshing...'));

            request('SMARK_keyword_refresh_keyword', { itemId: itemId, projectId: projectId }, 'POST')
                .done(res => {
                    if (!res || !res.success) {
                        showNotification(strings.refreshKeywordError || (isRTL ? 'بروزرسانی انجام نشد' : 'Refresh failed'), 'error');
                        return;
                    }

                    const errors = res.data && Array.isArray(res.data.errors) ? res.data.errors : [];
                    if (errors.length) {
                        const msg = strings.refreshKeywordPartial || (isRTL ? 'بروزرسانی انجام شد (با خطا).' : 'Refreshed with warnings.');
                        showNotification(errors[0] ? `${msg} ${errors[0]}` : msg, 'info', { durationMs: 6000 });
                    } else {
                        showNotification(strings.refreshKeywordSuccess || (isRTL ? 'بروزرسانی انجام شد.' : 'Keyword refreshed.'), 'success');
                    }

                    loadProjectKeywords(projectId);
                })
                .fail(() => {
                    showNotification(strings.refreshKeywordError || (isRTL ? 'بروزرسانی انجام نشد' : 'Refresh failed'), 'error');
                })
                .always(() => {
                    $btn.prop('disabled', false).text(oldText);
                });
        });

        $(document).on('click', '.smark-modal', function(evt) {
            if ($(evt.target).is('.smark-modal')) {
                closeModal(this);
            }
        });

        $('#smarkAddSelectedKeywords').on('click', () => {
            if (!state.activeProject) {
                showNotification(strings.noProjectSelected || 'Select a project first', 'error');
                return;
            }

            const selectedIds = [];
            $('#smarkBankTable .keyword-checkbox:checked:not(:disabled)').each(function() {
                const id = $(this).data('id');
                if (id) {
                    selectedIds.push(id);
                }
            });

            if (selectedIds.length === 0) {
                showNotification(strings.noKeywordsSelected || 'Select at least one keyword', 'error');
                return;
            }

            setAddSelectedLoading(true);
            request('SMARK_keyword_add_from_bank', {
                projectId: state.activeProject.id,
                bankIds: selectedIds
            }, 'POST')
                .done(res => {
                    if (!res.success) {
                        const message = res.data && res.data.message ? res.data.message : (strings.uploadError || 'Unable to add keywords');
                        showNotification(message, 'error');
                        return;
                    }
                    const added = res.data.added || 0;
                    if (added > 0) {
                        showNotification(strings.keywordsAdded || `Added ${added} keyword(s)`, 'success');
                    } else {
                        showNotification(strings.noKeywordsAdded || `Added ${added} keyword(s)`, 'info');
                    }
                    closeModal('#smarkBankModal');

                    // Uncheck all checkboxes
                    $('#smarkBankTable .keyword-checkbox').prop('checked', false);

                    // Reload project keywords
                    if (state.activeProject) {
                        loadProjectKeywords(state.activeProject.id);
                    }
                })
                .fail(() => {
                    showNotification(strings.uploadError || 'Unable to add keywords', 'error');
                })
                .always(() => {
                    setAddSelectedLoading(false);
                });
        });

        // Handle page link icon click
        $(document).on('click', '.page-link-not-checked', function() {
            const $icon = $(this);
            const itemId = $icon.data('item-id');
            const projectId = $icon.data('project-id');

            if (!itemId || !projectId) {
                return;
            }

            // Show loading state
            $icon.html('<span class="dashicons dashicons-update dashicons-spin"></span>');
            $icon.removeClass('page-link-not-checked').addClass('page-link-checking');

            request('SMARK_keyword_check_page_link', {
                itemId: itemId,
                projectId: projectId
            }, 'POST')
                .done(res => {
                    if (!res.success) {
                        $icon.html('<span class="dashicons dashicons-admin-links"></span>');
                        $icon.removeClass('page-link-checking').addClass('page-link-not-checked');
                        return;
                    }

                    const status = res.data.status;
                    const url = res.data.url;

                    // Update icon based on status
                    if (status === 'found' && url) {
                        $icon.replaceWith($('<a/>', {
                            href: url,
                            target: '_blank',
                            class: 'page-link-icon page-link-found',
                            title: url,
                            html: '<span class="dashicons dashicons-admin-links"></span>'
                        }));
                    } else if (status === 'not_connected') {
                        $icon.html('<span class="dashicons dashicons-dismiss"></span>');
                        $icon.removeClass('page-link-checking').addClass('page-link-not-connected');
                        $icon.attr('title', 'WordPress not connected');
                    } else {
                        $icon.html('<span class="dashicons dashicons-no-alt"></span>');
                        $icon.removeClass('page-link-checking').addClass('page-link-not-found');
                        $icon.attr('title', 'Keyword not found in Rank Math');
                    }
                })
                .fail(() => {
                    $icon.html('<span class="dashicons dashicons-admin-links"></span>');
                    $icon.html('<span class="dashicons dashicons-admin-links"></span>');
                    $icon.removeClass('page-link-checking').addClass('page-link-not-checked');
                });
        });

        // Handle ranking fetch icon click
        $(document).on('click', '.ranking-fetch-icon', function() {
            const $icon = $(this);
            const itemId = $icon.data('item-id');
            const projectId = $icon.data('project-id');

            if (!itemId || !projectId) {
                return;
            }

            // Show loading state
            $icon.html('<span class="dashicons dashicons-update dashicons-spin"></span>');
            $icon.removeClass('ranking-fetch-icon').addClass('ranking-fetching');

            request('SMARK_keyword_fetch_ranking', {
                itemId: itemId,
                projectId: projectId
            }, 'POST')
                .done(res => {
                    if (!res.success) {
                        $icon.html('<span class="dashicons dashicons-chart-line"></span>');
                        $icon.removeClass('ranking-fetching').addClass('ranking-fetch-icon');
                        showNotification(res.data && res.data.message ? res.data.message : 'Failed to fetch ranking', 'error');
                        return;
                    }

                    const rank3month = res.data.rank_3month_avg !== null && res.data.rank_3month_avg !== undefined ? parseFloat(res.data.rank_3month_avg) : 0;
                    const rank1month = res.data.rank_1month_avg !== null && res.data.rank_1month_avg !== undefined ? parseFloat(res.data.rank_1month_avg) : 0;
                    const isRTL = $('.smark-keyword-research-page').hasClass('rtl') || $('.smark-keyword-research-page').attr('data-lang') === 'fa';
                    const arrow = isRTL ? ' ← ' : ' → ';
                    const rankingText = rank3month.toFixed(1) + arrow + rank1month.toFixed(1);

                    // Determine color based on ranking logic
                    let rankingColorClass = 'ranking-text-green';
                    if (rank1month === 0) {
                        rankingColorClass = 'ranking-text-red';
                    } else if (rank3month !== 0 && rank1month !== 0 && rank3month < rank1month) {
                        rankingColorClass = 'ranking-text-red';
                    }

                    const $rankingContent = $('<span/>').addClass('ranking-text ' + rankingColorClass).text(rankingText);
                    const $updateIcon = $('<span/>', {
                        class: 'ranking-update-icon',
                        'data-item-id': itemId,
                        'data-project-id': projectId,
                        title: 'Update ranking',
                        html: '<span class="dashicons dashicons-update"></span>'
                    }).css('cursor', 'pointer').css('margin-left', '8px');

                    $icon.parent().empty().append($rankingContent).append($updateIcon);

                    const $row = $icon.closest('tr');
                    const $updatedCell = $row.find('.ranking-updated-at-cell');
                    if ($updatedCell.length) {
                        $updatedCell
                            .text(res.data.ranking_updated_at_display || res.data.ranking_updated_at || '—')
                            .attr('title', res.data.ranking_updated_at_display || res.data.ranking_updated_at || '');
                        applyRankingUpdatedAtStatus($updatedCell, res.data.ranking_updated_at);
                    }
                    showNotification('Ranking fetched successfully', 'success');
                })
                .fail(() => {
                    $icon.html('<span class="dashicons dashicons-chart-line"></span>');
                    $icon.removeClass('ranking-fetching').addClass('ranking-fetch-icon');
                    showNotification('Failed to fetch ranking', 'error');
                });
        });

        // Handle ranking update icon click
        $(document).on('click', '.ranking-update-icon', function() {
            const $icon = $(this);
            const itemId = $icon.data('item-id');
            const projectId = $icon.data('project-id');

            if (!itemId || !projectId) {
                return;
            }

            // Show loading state
            const $rankingCell = $icon.closest('.ranking-cell');
            const originalContent = $rankingCell.html();
            $icon.html('<span class="dashicons dashicons-update dashicons-spin"></span>');

            request('SMARK_keyword_fetch_ranking', {
                itemId: itemId,
                projectId: projectId
            }, 'POST')
                .done(res => {
                    if (!res.success) {
                        $rankingCell.html(originalContent);
                        showNotification(res.data && res.data.message ? res.data.message : 'Failed to update ranking', 'error');
                        return;
                    }

                    const rank3month = res.data.rank_3month_avg !== null && res.data.rank_3month_avg !== undefined ? parseFloat(res.data.rank_3month_avg) : 0;
                    const rank1month = res.data.rank_1month_avg !== null && res.data.rank_1month_avg !== undefined ? parseFloat(res.data.rank_1month_avg) : 0;
                    const isRTL = $('.smark-keyword-research-page').hasClass('rtl') || $('.smark-keyword-research-page').attr('data-lang') === 'fa';
                    const arrow = isRTL ? ' ← ' : ' → ';
                    const rankingText = rank3month.toFixed(1) + arrow + rank1month.toFixed(1);

                    // Determine color based on ranking logic
                    let rankingColorClass = 'ranking-text-green';
                    if (rank1month === 0) {
                        rankingColorClass = 'ranking-text-red';
                    } else if (rank3month !== 0 && rank1month !== 0 && rank3month < rank1month) {
                        rankingColorClass = 'ranking-text-red';
                    }

                    const $rankingContent = $('<span/>').addClass('ranking-text ' + rankingColorClass).text(rankingText);
                    const $updateIcon = $('<span/>', {
                        class: 'ranking-update-icon',
                        'data-item-id': itemId,
                        'data-project-id': projectId,
                        title: 'Update ranking',
                        html: '<span class="dashicons dashicons-update"></span>'
                    }).css('cursor', 'pointer').css('margin-left', '8px');

                    $rankingCell.empty().append($rankingContent).append($updateIcon);

                    const $row = $icon.closest('tr');
                    const $updatedCell = $row.find('.ranking-updated-at-cell');
                    if ($updatedCell.length) {
                        $updatedCell
                            .text(res.data.ranking_updated_at_display || res.data.ranking_updated_at || '—')
                            .attr('title', res.data.ranking_updated_at_display || res.data.ranking_updated_at || '');
                        applyRankingUpdatedAtStatus($updatedCell, res.data.ranking_updated_at);
                    }
                    showNotification('Ranking updated successfully', 'success');
                })
                .fail(() => {
                    $rankingCell.html(originalContent);
                    showNotification('Failed to update ranking', 'error');
                });
        });

        function applyLiveSerpMetricsToRow($row, itemId, projectId, data) {
            if (!$row || !$row.length) {
                return;
            }

            const $rankCell = $row.find('.live-ranking-cell').first();
            if ($rankCell.length) {
                const replacement = buildLiveRankCell(
                    itemId,
                    projectId,
                    data && data.live_rank_position !== undefined ? data.live_rank_position : null,
                    data && data.live_rank_updated_at ? data.live_rank_updated_at : ''
                );
                $rankCell.replaceWith(replacement);
            }

            const $refdomainsCell = $row.find('.live-metric-cell-refdomains').first();
            if ($refdomainsCell.length) {
                const replacement = buildMetricCell(
                    itemId,
                    projectId,
                    data && data.live_refdomains_count !== undefined ? data.live_refdomains_count : null,
                    data && data.live_refdomains_top10_max !== undefined ? data.live_refdomains_top10_max : null,
                    data && data.live_metrics_updated_at ? data.live_metrics_updated_at : '',
                    'refdomains'
                );
                $refdomainsCell.replaceWith(replacement);
            }

            const $backlinksCell = $row.find('.live-metric-cell-backlinks').first();
            if ($backlinksCell.length) {
                const replacement = buildMetricCell(
                    itemId,
                    projectId,
                    data && data.live_backlinks_count !== undefined ? data.live_backlinks_count : null,
                    data && data.live_backlinks_top10_max !== undefined ? data.live_backlinks_top10_max : null,
                    data && data.live_metrics_updated_at ? data.live_metrics_updated_at : '',
                    'backlinks'
                );
                $backlinksCell.replaceWith(replacement);
            }

            syncAddBacklinkAction($row, {
                id: itemId,
                keyword: $.trim($row.find('td').first().text()),
                live_refdomains_count: data && data.live_refdomains_count !== undefined ? data.live_refdomains_count : null,
                live_refdomains_top10_max: data && data.live_refdomains_top10_max !== undefined ? data.live_refdomains_top10_max : null,
                live_backlinks_count: data && data.live_backlinks_count !== undefined ? data.live_backlinks_count : null,
                live_backlinks_top10_max: data && data.live_backlinks_top10_max !== undefined ? data.live_backlinks_top10_max : null,
                live_metrics_updated_at: data && data.live_metrics_updated_at ? data.live_metrics_updated_at : ''
            });
        }

        function handleLiveRankResponse($cell, itemId, projectId, data) {
            const replacement = buildLiveRankCell(
                itemId,
                projectId,
                data && data.live_rank_position !== undefined ? data.live_rank_position : null,
                data && data.live_rank_updated_at ? data.live_rank_updated_at : ''
            );
            $cell.replaceWith(replacement);
        }

        $(document).on('click', '.live-ranking-fetch-icon', function() {
            const $icon = $(this);
            const itemId = $icon.data('item-id');
            const projectId = $icon.data('project-id');

            if (!itemId || !projectId) {
                return;
            }

            $icon.html('<span class="dashicons dashicons-update dashicons-spin"></span>');
            $icon.removeClass('live-ranking-fetch-icon').addClass('live-ranking-fetching');

            request('SMARK_keyword_fetch_live_rank', {
                itemId: itemId,
                projectId: projectId
            }, 'POST')
                .done(res => {
                    if (!res.success) {
                        $icon.html('<span class="dashicons dashicons-chart-line"></span>');
                        $icon.removeClass('live-ranking-fetching').addClass('live-ranking-fetch-icon');
                        showNotification(res.data && res.data.message ? res.data.message : (strings.liveRankFetchError || 'Failed to fetch live rank.'), 'error');
                        return;
                    }

                    applyLiveSerpMetricsToRow($icon.closest('tr'), itemId, projectId, res.data || {});
                    showNotification(strings.liveRankFetchSuccess || 'Live rank fetched successfully.', 'success');
                })
                .fail((xhr) => {
                    $icon.html('<span class="dashicons dashicons-chart-line"></span>');
                    $icon.removeClass('live-ranking-fetching').addClass('live-ranking-fetch-icon');
                    const msg = buildAjaxErrorMessage(
                        strings.liveRankFetchTitle || 'Fetch live rank',
                        xhr,
                        strings.liveRankFetchError || 'Failed to fetch live rank.',
                        { action: 'SMARK_keyword_fetch_live_rank' }
                    );
                    showNotification(msg, 'error', { durationMs: 9000 });
                });
        });

        $(document).on('click', '.live-ranking-update-icon', function() {
            const $icon = $(this);
            const itemId = $icon.data('item-id');
            const projectId = $icon.data('project-id');

            if (!itemId || !projectId) {
                return;
            }

            const $cell = $icon.closest('.live-ranking-cell');
            const originalContent = $cell.html();
            $icon.html('<span class="dashicons dashicons-update dashicons-spin"></span>');

            request('SMARK_keyword_fetch_live_rank', {
                itemId: itemId,
                projectId: projectId
            }, 'POST')
                .done(res => {
                    if (!res.success) {
                        $cell.html(originalContent);
                        showNotification(res.data && res.data.message ? res.data.message : (strings.liveRankFetchError || 'Failed to fetch live rank.'), 'error');
                        return;
                    }

                    applyLiveSerpMetricsToRow($icon.closest('tr'), itemId, projectId, res.data || {});
                    showNotification(strings.liveRankFetchSuccess || 'Live rank fetched successfully.', 'success');
                })
                .fail((xhr) => {
                    $cell.html(originalContent);
                    const msg = buildAjaxErrorMessage(
                        strings.liveRankUpdateTitle || 'Update live rank',
                        xhr,
                        strings.liveRankFetchError || 'Failed to fetch live rank.',
                        { action: 'SMARK_keyword_fetch_live_rank' }
                    );
                    showNotification(msg, 'error', { durationMs: 9000 });
                });
        });

        $(document).on('click', '.live-metric-fetch-icon, .live-metric-update-icon', function() {
            const $icon = $(this);
            const itemId = $icon.data('item-id');
            const projectId = $icon.data('project-id');

            if (!itemId || !projectId) {
                return;
            }

            const $row = $icon.closest('tr');
            const $cell = $icon.closest('.live-metric-cell');
            const originalContent = $cell.html();
            $icon.html('<span class="dashicons dashicons-update dashicons-spin"></span>');

            request('SMARK_keyword_fetch_live_rank', {
                itemId: itemId,
                projectId: projectId
            }, 'POST')
                .done(res => {
                    if (!res.success) {
                        $cell.html(originalContent);
                        showNotification(res.data && res.data.message ? res.data.message : (strings.liveRankFetchError || 'Failed to fetch live rank.'), 'error');
                        return;
                    }

                    applyLiveSerpMetricsToRow($row, itemId, projectId, res.data || {});
                    showNotification(strings.liveRankFetchSuccess || 'Live rank fetched successfully.', 'success');
                })
                .fail((xhr) => {
                    $cell.html(originalContent);
                    const msg = buildAjaxErrorMessage(
                        strings.liveRankUpdateTitle || 'Update live rank',
                        xhr,
                        strings.liveRankFetchError || 'Failed to fetch live rank.',
                        { action: 'SMARK_keyword_fetch_live_rank' }
                    );
                    showNotification(msg, 'error', { durationMs: 9000 });
                });
        });

        $('#projectKeywordsTable').on('click', '.btn-add-backlink', function() {
            const targetPostId = parseInt($(this).data('targetPostId') || '0', 10) || 0;
            if (!targetPostId) {
                showNotification(
                    strings.addBacklinkMissingPage || (isPersian() ? 'برای این کلمه کلیدی صفحه متصل پیدا نشد.' : 'No linked page was found for this keyword.'),
                    'error'
                );
                return;
            }

            const baseUrl = settings.backlinksManagementUrl || (settings.ajaxUrl ? settings.ajaxUrl.replace(/admin-ajax\.php.*$/, 'admin.php?page=smark-backlinks-management') : '');
            if (!baseUrl) {
                showNotification(strings.addBacklinkSoon || (isPersian() ? 'صفحه مدیریت بک‌لینک در دسترس نیست.' : 'Backlinks management page is not available.'), 'error');
                return;
            }

            const separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
            window.location.href = baseUrl + separator + 'target_post_id=' + encodeURIComponent(String(targetPostId));
        });
    }

    function fixFooterLayout() {
        const wpBody = document.querySelector('#wpbody');
        const wpBodyContent = document.querySelector('#wpbody-content');
        const wrap = document.querySelector('.wrap.smark-keyword-research-page');
        const mainContent = document.querySelector('.smark-keyword-research-content');
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

    $(function() {
        bindEvents();
        initDefaultProject();
        request('SMARK_keyword_bank_stats', {}, 'GET')
            .done(res => {
                if (!res || !res.success) {
                    const msg = res && res.data && res.data.message ? res.data.message : null;
                    if (msg) {
                        showNotification(msg, 'error');
                    }
                    return;
                }
                if (res.data) {
                    refreshBankStats(res.data.total || 0, res.data.lastUpload || null);
                }
            })
            .fail(xhr => {
                const data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
                const msg = data && data.message ? data.message : null;
                const status = data && data.status ? data.status : (xhr && xhr.status ? xhr.status : null);
                if (msg) {
                    showNotification(status ? `${msg} (${status})` : msg, 'error');
                }
            })
            .always(() => {
                loadBank();
            });
        fixFooterLayout();

        // Run layout fix multiple times to ensure it works
        setTimeout(fixFooterLayout, 100);
        setTimeout(fixFooterLayout, 500);
        setTimeout(fixFooterLayout, 1000);
    });
})(jQuery);
