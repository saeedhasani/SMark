
jQuery(document).ready(function($) {
    // Check body classes

    // Only run on social media pages - check both class and URL
    const isSocialMediaPage = $('body').hasClass('smark-social-media-page') ||
                             window.location.href.includes('smark-social-media');

    if (!isSocialMediaPage) {
        return;
    }
    'use strict';

    /**
     * Social Media Page Layout Fix
     */
    function fixSocialMediaLayout() {
        const p = document.querySelector('#wpbody');
        const c = document.querySelector('#wpbody-content');
        const w = document.querySelector('.wrap.smark-social-media-page');

        if (p && c) {
            c.style.height = getComputedStyle(p).height;
            c.style.minHeight = c.style.height;
            c.style.float = 'none';
            c.style.paddingBottom = '0';

        }

        // Apply the same height to .wrap.smark-social-media-page
        if (p && w) {
            w.style.height = getComputedStyle(p).height;
            w.style.minHeight = w.style.height;
            w.style.float = 'none';
            w.style.paddingBottom = '0';

        }

        // Additional layout fixes
        const socialMediaPage = document.querySelector('.smark-social-media-page');
        const mainContent = document.querySelector('.smark-main-content');
        const footer = document.querySelector('.smark-version-footer');

        if (socialMediaPage) {
            socialMediaPage.style.display = 'flex';
            socialMediaPage.style.flexDirection = 'column';
            socialMediaPage.style.justifyContent = 'space-between';
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

    // Run layout fix multiple times to ensure it works
    fixSocialMediaLayout();

    // Run again after a short delay
    setTimeout(fixSocialMediaLayout, 100);
    setTimeout(fixSocialMediaLayout, 500);

    // Run on window resize
    $(window).on('resize', function() {
        fixSocialMediaLayout();
    });

    // Run when WordPress admin menu is toggled
    $(document).on('wp-window-resized', function() {
        setTimeout(fixSocialMediaLayout, 100);
    });

    let selectedProjectId = null;
    let selectedProjectName = null;
    let isAnalyzing = false;
    let autoSaveTimeout = null;
    let originalFieldValues = {
        headline: '',
        visual_text: '',
        caption: '',
        source: '',
        content_link: '',
        published_link: ''
    };
    let isAutoSaving = false;

    /**
     * Generate analysis HTML for display
     */
    function generateAnalysisHtml(analysis) {

        const hasNumbers = analysis.has_numbers ? SMarkSocialMedia.strings.yes + ' ✓' : SMarkSocialMedia.strings.no + ' ✗';
        const gainsPains = analysis.has_gains_pains ? SMarkSocialMedia.strings.yes + ' ✓' : SMarkSocialMedia.strings.no + ' ✗';

        return `
            <div class="analysis-grid">
                <div class="analysis-item">
                    <div class="analysis-label">${SMarkSocialMedia.strings.has_numbers}</div>
                    <div class="analysis-value ${analysis.has_numbers ? 'status-success' : 'status-error'}">${hasNumbers}</div>
                </div>
                <div class="analysis-item">
                    <div class="analysis-label">${SMarkSocialMedia.strings.words}</div>
                    <div class="analysis-value">${analysis.word_count}</div>
                </div>
                <div class="analysis-item">
                    <div class="analysis-label">${SMarkSocialMedia.strings.gains_pains}</div>
                    <div class="analysis-value ${analysis.has_gains_pains ? 'status-success' : 'status-error'}">${gainsPains}</div>
                </div>
                <div class="analysis-item">
                    <div class="analysis-label">${SMarkSocialMedia.strings.characters}</div>
                    <div class="analysis-value">${analysis.char_count}</div>
                </div>
                <div class="analysis-item">
                    <div class="analysis-label">${SMarkSocialMedia.strings.score}</div>
                    <div class="analysis-value">${analysis.score}</div>
                </div>
            </div>
            ${analysis.gains_pains_explanation ? `
                <div class="ai-explanation">
                    <div class="explanation-header">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <strong>${SMarkSocialMedia.strings.ai_analysis}</strong>
                    </div>
                    <p class="explanation-text">${analysis.gains_pains_explanation}</p>
                </div>
            ` : ''}
        `;
    }

    /**
     * Normalize stored analysis HTML to ensure consistent styling
     */
    function normalizeAnalysisHtml(storedHtml) {

        // If the stored HTML already has the proper structure, return it as is
        if (storedHtml.includes('analysis-grid')) {
            return storedHtml;
        }

        // If it's plain text or old format, we need to parse and restructure it
        let text = storedHtml;

        // Remove any existing HTML tags and get clean text
        const $temp = $('<div>').html(storedHtml);
        text = $temp.text().trim();


        // Parse the Persian analysis text and extract values
        const analysisData = parsePersianAnalysisText(text);

        if (analysisData) {
            // Generate proper HTML using the same function as initial analysis
            return generateAnalysisHtml(analysisData);
        }

        // Fallback: wrap the original HTML in analysis-grid
        return `<div class="analysis-grid">${storedHtml}</div>`;
    }

    /**
     * Parse Persian analysis text to extract structured data
     */
    function parsePersianAnalysisText(text) {

        try {
            // Extract values using regex patterns for Persian text
            const hasNumbersMatch = text.match(/دارای اعداد\s+(خیر|بله|✓|✗)/i);
            const wordsMatch = text.match(/کلمات\s+(\d+)/i);
            const gainsPainsMatch = text.match(/منفعت[‌\s]*ساز[‌\s]*و[‌\s]*دردسرکاه\s+(خیر|بله|✓|✗)/i);
            const charactersMatch = text.match(/کاراکترها\s+(\d+)/i);
            const scoreMatch = text.match(/امتیاز\s+(\d+)/i);

            // Extract AI explanation
            const aiExplanationMatch = text.match(/تحلیل هوش مصنوعی[:\s]*(.+?)(?:\s*$)/i);

            const analysisData = {
                has_numbers: hasNumbersMatch ? (hasNumbersMatch[1].includes('بله') || hasNumbersMatch[1].includes('✓')) : false,
                word_count: wordsMatch ? parseInt(wordsMatch[1]) : 0,
                has_gains_pains: gainsPainsMatch ? (gainsPainsMatch[1].includes('بله') || gainsPainsMatch[1].includes('✓')) : false,
                char_count: charactersMatch ? parseInt(charactersMatch[1]) : 0,
                score: scoreMatch ? parseInt(scoreMatch[1]) : 0,
                gains_pains_explanation: aiExplanationMatch ? aiExplanationMatch[1].trim() : ''
            };

            return analysisData;

        } catch (error) {
            return null;
        }
    }

    /**
     * Load project items
     */
    function loadProjectItems(projectId) {

        const normalizedProjectId = (projectId || '').trim();
        const requestPayload = {
            action: 'SMARK_get_project_items',
            nonce: SMarkSocialMedia.nonce
        };

        if (normalizedProjectId) {
            requestPayload.project_id = normalizedProjectId;
            selectedProjectId = normalizedProjectId;
        } else if (selectedProjectName) {
            requestPayload.project_name = selectedProjectName;
        }

        // Show loading state
        $('#data_table_body').html('<tr class="no-data-row"><td colspan="6">' + SMarkSocialMedia.strings.loading + '</td></tr>');

        // Hide empty state and show tables
        $('#empty_state').fadeOut(300, function() {
            $('#data_table_card').fadeIn(300);
            $('#suggestions_table_card').fadeIn(300);
        });

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: requestPayload,
            success: function(response) {

                // Parse response if it's a string (same as projects)
                let parsedResponse = response;
                if (typeof response === 'string') {
                    try {
                        const cleanResponse = response.trim();
                        parsedResponse = JSON.parse(cleanResponse);
                    } catch (e) {
                        const errorText = SMarkSocialMedia.currentLang === 'fa' ? 'خطا در بارگذاری آیتم‌ها' : 'Error loading items';
                        $('#data_table_body').html('<tr class="no-data-row"><td colspan="6">' + errorText + '</td></tr>');
                        return;
                    }
                }

                if (parsedResponse.success) {
                    const items = parsedResponse.data.items;
                    const projectName = parsedResponse.data.project_name || selectedProjectName || '';
                    const projectIdResp = parsedResponse.data.project_id || normalizedProjectId;
                    // Project name display is intentionally hidden in the new UI.
                    if (projectIdResp) {
                        selectedProjectId = projectIdResp;
                    }
                    if (projectName) {
                        selectedProjectName = projectName;
                    }

                    if (items && items.length > 0) {
                        renderTableItems(items);
                    } else {
                        const noItemsText = SMarkSocialMedia.strings.no_items_found || 'No items found';
                        $('#data_table_body').html('<tr class="no-data-row"><td colspan="6">' + noItemsText + '</td></tr>');
                    }
                } else {
                    const errorText = SMarkSocialMedia.currentLang === 'fa' ? 'خطا در بارگذاری آیتم‌ها' : 'Error loading items';
                    $('#data_table_body').html('<tr class="no-data-row"><td colspan="6">' + errorText + '</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                const errorText = SMarkSocialMedia.currentLang === 'fa' ? 'خطا در بارگذاری آیتم‌ها' : 'Error loading items';
                $('#data_table_body').html('<tr class="no-data-row"><td colspan="6">' + errorText + '</td></tr>');
            }
        });

        // Also load suggestions
        loadProjectSuggestions(normalizedProjectId || selectedProjectId || '');
    }

    /**
     * Load project suggestions
     */
    function loadProjectSuggestions(projectId) {

        const normalizedProjectId = (projectId || '').trim();
        const requestPayload = {
            action: 'SMARK_get_project_suggestions',
            nonce: SMarkSocialMedia.nonce
        };

        if (normalizedProjectId) {
            requestPayload.project_id = normalizedProjectId;
        } else if (selectedProjectName) {
            requestPayload.project_name = selectedProjectName;
        }

        // Show loading state
        $('#suggestions_table_body').html('<tr class="no-data-row"><td colspan="6">' + SMarkSocialMedia.strings.loading + '</td></tr>');

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: requestPayload,
            success: function(response) {

                // Parse response if it's a string (same as projects)
                let parsedResponse = response;
                if (typeof response === 'string') {
                    try {
                        const cleanResponse = response.trim();
                        parsedResponse = JSON.parse(cleanResponse);
                    } catch (e) {
                        const errorText = SMarkSocialMedia.currentLang === 'fa' ? 'خطا در بارگذاری پیشنهادها' : 'Error loading suggestions';
                        $('#suggestions_table_body').html('<tr class="no-data-row"><td colspan="6">' + errorText + '</td></tr>');
                        return;
                    }
                }

                if (parsedResponse.success) {
                    const suggestions = parsedResponse.data.suggestions;
                    const projectName = parsedResponse.data.project_name || selectedProjectName || '';
                    const projectIdResp = parsedResponse.data.project_id || normalizedProjectId;
                    if (projectIdResp) {
                        selectedProjectId = projectIdResp;
                    }
                    if (projectName) {
                        selectedProjectName = projectName;
                    }

                    if (suggestions && suggestions.length > 0) {
                        renderTableSuggestions(suggestions);
                    } else {
                        const noSuggestionsText = SMarkSocialMedia.strings.no_suggestions_found || 'No suggestions found';
                        $('#suggestions_table_body').html('<tr class="no-data-row"><td colspan="6">' + noSuggestionsText + '</td></tr>');
                    }
                } else {
                    const errorText = SMarkSocialMedia.currentLang === 'fa' ? 'خطا در بارگذاری پیشنهادها' : 'Error loading suggestions';
                    $('#suggestions_table_body').html('<tr class="no-data-row"><td colspan="6">' + errorText + '</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                const errorText = SMarkSocialMedia.currentLang === 'fa' ? 'خطا در بارگذاری پیشنهادها' : 'Error loading suggestions';
                $('#suggestions_table_body').html('<tr class="no-data-row"><td colspan="6">' + errorText + '</td></tr>');
            }
        });
    }

    /**
     * Render table items
     */
    function renderTableItems(items) {
        const $tbody = $('#data_table_body');
        $tbody.empty();

        items.forEach(function(item) {
            // Format date based on current language
            const locale = SMarkSocialMedia.currentLang === 'fa' ? 'fa-IR' : 'en-US';
            const dateOptions = {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            };

            // For Persian, use Persian calendar if available, otherwise fallback to Gregorian
            let createdDate;
            try {
                createdDate = new Date(item.created_at).toLocaleDateString(locale, dateOptions);
            } catch (e) {
                // Fallback to basic formatting
                createdDate = new Date(item.created_at).toLocaleDateString('en-US', dateOptions);
            }

            // Score badge with color coding
            const score = parseInt(item.score) || 0;
            const scoreClass = score === 100 ? 'score-badge-perfect' : 'score-badge-normal';
            const scoreBadge = '<span class="score-badge ' + scoreClass + '">' + score + '/100</span>';

            // Visual preview
            let visualPreview = '<span class="no-visual">—</span>';
            if (item.visual) {

                // Use visual_type if available, otherwise fallback to file extension
                const isVideo = item.visual_type ?
                    item.visual_type.startsWith('video/') :
                    item.visual.match(/\.(mp4|mpeg|mov|avi)$/i);

                if (isVideo) {
                    visualPreview = `<div class="visual-thumbnail"><video src="${item.visual}" class="table-visual-preview"></video></div>`;
                } else {
                    visualPreview = `<div class="visual-thumbnail"><img src="${item.visual}" alt="Visual" class="table-visual-preview"></div>`;
                }
            }

            // Status badge with color coding
            const expertStatus = item.expert_approval_status || 'needs_approval';
            let statusClass = '';
            let statusText = '';

            if (expertStatus === 'needs_approval') {
                statusClass = 'status-badge-needs-approval';
                statusText = SMarkSocialMedia.strings.needs_approval || 'Needs Expert Approval';
            } else if (expertStatus === 'sent_to_expert') {
                statusClass = 'status-badge-sent';
                statusText = SMarkSocialMedia.strings.sent_to_expert || 'Sent to Expert';
            } else if (expertStatus === 'approved_by_expert') {
                statusClass = 'status-badge-approved';
                statusText = SMarkSocialMedia.strings.approved_by_expert || 'Approved by Expert';
            } else if (expertStatus === 'published') {
                statusClass = 'status-badge-published';
                statusText = SMarkSocialMedia.strings.published || 'Published';
            } else {
                statusClass = 'status-badge-needs-approval';
                statusText = SMarkSocialMedia.strings.needs_approval || 'Needs Expert Approval';
            }

            const statusBadge = `<span class="status-badge ${statusClass}">${statusText}</span>`;

            const row = `
                <tr data-id="${item.id}">
                    <td>${item.id}</td>
                    <td>${item.headline}</td>
                    <td>${visualPreview}</td>
                    <td>${createdDate}</td>
                    <td>${statusBadge}</td>
                    <td>${scoreBadge}</td>
                    <td>
                        <div class="table-actions">
                            <button class="table-action-btn edit" data-id="${item.id}">${SMarkSocialMedia.strings.edit}</button>
                            <button class="table-action-btn delete" data-id="${item.id}">${SMarkSocialMedia.strings.delete}</button>
                        </div>
                    </td>
                </tr>
            `;

            $tbody.append(row);
        });
    }

    /**
     * Render table suggestions
     */
    function renderTableSuggestions(suggestions) {
        const $tbody = $('#suggestions_table_body');
        $tbody.empty();

        suggestions.forEach(function(suggestion) {
            // Format date based on current language
            const locale = SMarkSocialMedia.currentLang === 'fa' ? 'fa-IR' : 'en-US';
            const dateOptions = {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            };

            // For Persian, use Persian calendar if available, otherwise fallback to Gregorian
            let createdDate;
            try {
                createdDate = new Date(suggestion.created_at).toLocaleDateString(locale, dateOptions);
            } catch (e) {
                // Fallback to basic formatting
                createdDate = new Date(suggestion.created_at).toLocaleDateString('en-US', dateOptions);
            }

            // Score badge - suggestions don't have scores initially
            const score = 0;
            const scoreClass = 'score-badge-normal';
            const scoreBadge = '<span class="score-badge ' + scoreClass + '">' + score + '/100</span>';

            // Visual preview - suggestions usually don't have visuals
            let visualPreview = '<span class="no-visual">—</span>';

            const row = `
                <tr data-id="${suggestion.id}">
                    <td>${suggestion.id}</td>
                    <td>${suggestion.headline}</td>
                    <td>${visualPreview}</td>
                    <td>${createdDate}</td>
                    <td>${scoreBadge}</td>
                    <td style="text-align: right !important; direction: rtl !important;">
                        <div class="table-actions" style="justify-content: flex-start !important; text-align: right !important; direction: rtl !important; width: 100% !important;">
                            <button class="table-action-btn view-suggestion" data-id="${suggestion.id}">${SMarkSocialMedia.strings.view}</button>
                            <button class="table-action-btn transfer-suggestion" data-id="${suggestion.id}">${SMarkSocialMedia.strings.transferToItems}</button>
                            <button class="table-action-btn delete-suggestion" data-id="${suggestion.id}">${SMarkSocialMedia.strings.delete}</button>
                        </div>
                    </td>
                </tr>
            `;

            $tbody.append(row);

            // Force RTL alignment after appending
            setTimeout(function() {
                const $page = $('.smark-social-media-page');
                if ($page.hasClass('rtl') || $page.attr('data-lang') === 'fa') {
                    $tbody.find('.table-actions').css({
                        'justify-content': 'flex-start',
                        'text-align': 'right',
                        'direction': 'rtl',
                        'width': '100%'
                    });
                    $tbody.find('td:last-child').css({
                        'text-align': 'right',
                        'direction': 'rtl'
                    });
                }
            }, 100);
        });
    }

    /**
     * Load suggestion for view/edit
     */
    function loadSuggestionForView(suggestionId) {

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_get_suggestion',
                nonce: SMarkSocialMedia.nonce,
                suggestion_id: suggestionId
            },
            success: function(response) {

                if (response.success) {
                    const suggestion = response.data.suggestion;

                    // Set to View mode
                    $('#item_id').val(suggestion.id);
                    $('#modal_title').text(SMarkSocialMedia.strings.viewSuggestion);

                    // Mark this as a suggestion edit
                    $('#item_id').val(''); // Clear item ID
                    $('#suggestion_id').val(suggestionId); // Set suggestion ID
                    $('#is_viewing_suggestion').val('1'); // Flag to indicate editing suggestion

                    // Fill form (all fields are editable)
                    $('#item_headline').val(suggestion.headline);
                    $('.char-counter-inside').text(suggestion.headline.length + ' / 500');

                    // Store original field values (suggestions don't auto-save, but store for consistency)
                    originalFieldValues = {
                        headline: (suggestion.headline || '').trim(),
                        visual_text: (suggestion.visual_text || '').trim(),
                        caption: (suggestion.caption || '').trim(),
                        source: (suggestion.source || '').trim(),
                        content_link: '',
                        published_link: ''
                    };

                    // Fill caption
                    $('#item_caption').val(suggestion.caption || '');

                    // Fill visual text
                    $('#item_visual_text').val(suggestion.visual_text || '');

                    // Fill source
                    $('#item_source').val(suggestion.source || '');

                    // Fill expert approval status
                    const expertStatus = suggestion.expert_approval_status || 'needs_approval';
                    $('#expert_approval_status').val(expertStatus).attr('data-status', expertStatus);

                    // Handle visual
                    const visualUrl = suggestion.visual;
                    $('#item_visual').val(visualUrl);
                    $('#item_visual_type').val(suggestion.visual_type || '');
                    if (visualUrl) {
                        showVisualPreview(visualUrl, suggestion.visual_type);
                        $('#upload_button_wrapper').hide();
                        $('#visual_preview').show();
                    } else {
                        $('#visual_preview').hide();
                        $('#upload_button_wrapper').show();
                    }

                    // Hide analysis section for suggestions
                    $('#headline_analysis_results_display').hide();
                    $('#no_analysis_message').hide();

                    // Change save button text to "Update Suggestion"
                    $('#save_btn_text').text(SMarkSocialMedia.strings.updateSuggestion || 'Update Suggestion');

                    // Show modal
                    $('#add_item_modal').fadeIn(300);
                    $('#item_headline').focus();
                } else {
                    showNotification(SMarkSocialMedia.strings.error, response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification(SMarkSocialMedia.strings.error, SMarkSocialMedia.strings.errorLoadingSuggestion, 'error');
            }
        });
    }

    /**
     * Update suggestion
     */
    function updateSuggestion(suggestionId, headline) {

        const visual = $('#item_visual').val() || null;
        const visual_type = $('#item_visual_type').val() || null;
        const content_link = $('#item_content_link').val().trim() || null;
        const published_link = $('#item_published_link').val().trim() || null;
        const visual_text = $('#item_visual_text').val().trim() || null;
        const caption = $('#item_caption').val().trim() || null;
        const source = $('#item_source').val().trim() || null;
        const expert_approval_status = $('#expert_approval_status').val();

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_update_suggestion',
                nonce: SMarkSocialMedia.nonce,
                suggestion_id: suggestionId,
                headline: headline,
                visual: visual,
                visual_type: visual_type,
                content_link: content_link,
                visual_text: visual_text,
                caption: caption,
                source: source,
                expert_approval_status: expert_approval_status
            },
            beforeSend: function() {
                $('#save_item_btn').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + SMarkSocialMedia.strings.updating);
            },
            success: function(response) {

                if (response.success) {
                    showNotification(SMarkSocialMedia.strings.success, response.data.message || SMarkSocialMedia.strings.suggestionUpdatedSuccessfully, 'success');

                    // Close modal
                    $('#add_item_modal').fadeOut(300);

                    // Reload suggestions
                    const currentProject = selectedProjectId;
                    if (currentProject) {
                        loadProjectSuggestions(currentProject);
                    }
                } else {
                    showNotification(SMarkSocialMedia.strings.error, response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification(SMarkSocialMedia.strings.error, 'An error occurred. Please try again.', 'error');
            },
            complete: function() {
                $('#save_item_btn').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> <span id="save_btn_text">' + (SMarkSocialMedia.strings.updateSuggestion || 'Update Suggestion') + '</span>');
            }
        });
    }

    /**
     * Transfer suggestion to items
     */
    function transferSuggestionToItem(suggestionId) {

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_transfer_suggestion_to_item',
                nonce: SMarkSocialMedia.nonce,
                suggestion_id: suggestionId
            },
            success: function(response) {

                if (response.success) {
                    showNotification(SMarkSocialMedia.strings.success, response.data.message, 'success');

                    // Close modal if open
                    $('#add_item_modal').fadeOut(300);

                    // Reload both tables
                    const currentProject = selectedProjectId;
                    if (currentProject) {
                        loadProjectItems(currentProject);
                        loadProjectSuggestions(currentProject);
                    }
                } else {
                    showNotification(SMarkSocialMedia.strings.error, response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification(SMarkSocialMedia.strings.error, SMarkSocialMedia.strings.errorTransferringSuggestion, 'error');
            }
        });
    }

    /**
     * Delete suggestion
     */
    function deleteSuggestion(suggestionId) {

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_delete_suggestion',
                nonce: SMarkSocialMedia.nonce,
                suggestion_id: suggestionId
            },
            success: function(response) {

                if (response.success) {
                    showNotification(SMarkSocialMedia.strings.success, response.data.message, 'success');
                    // Reload suggestions table
                    const currentProject = selectedProjectId;
                    if (currentProject) {
                        loadProjectSuggestions(currentProject);
                    }
                } else {
                    showNotification(SMarkSocialMedia.strings.error, response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification(SMarkSocialMedia.strings.error, SMarkSocialMedia.strings.errorDeletingSuggestion, 'error');
            }
        });
    }

    /**
     * Load projects on page load
     */
    function loadProjects() {

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_get_projects',
                nonce: SMarkSocialMedia.nonce
            },
            success: function(response) {

                // Parse response if it's a string
                let parsedResponse = response;
                if (typeof response === 'string') {
                    try {
                        // Clean the response string before parsing
                        const cleanResponse = response.trim();
                        parsedResponse = JSON.parse(cleanResponse);
                    } catch (e) {
                        $('#project_select').html('<option value="">' + SMarkSocialMedia.strings.selectProject + '</option>');
                        return;
                    }
                }

                // Handle both direct array response and success/data structure
                let projects = null;
                if (Array.isArray(parsedResponse)) {
                    // Direct array response
                    projects = parsedResponse;
                } else if (parsedResponse && parsedResponse.success && parsedResponse.data) {
                    // Standard WordPress AJAX response
                    projects = parsedResponse.data;
                } else if (parsedResponse && parsedResponse.data) {
                    // Data without success flag
                    projects = parsedResponse.data;
                }

                if (projects && Array.isArray(projects)) {
                    const $select = $('#project_select');

                    $select.empty();

                    // Always add default option with localized text
                    $select.append('<option value="">' + SMarkSocialMedia.strings.selectProject + '</option>');

                    // Add project options
                    if (projects.length > 0) {
                        projects.forEach(function(project) {
                            const displayName = project.project_id ? project.project_name + ' (' + project.project_id + ')' : project.project_name;
                            $select.append('<option value="' + (project.project_id || '') + '" data-name="' + project.project_name + '">' + displayName + '</option>');
                        });
                    } else {
                    }
                } else {
                    $('#project_select').html('<option value="">' + SMarkSocialMedia.strings.selectProject + '</option>');
                }
            },
            error: function(xhr, status, error) {
                $('#project_select').html('<option value="">' + SMarkSocialMedia.strings.selectProject + '</option>');
            }
        });
    }

    function initDefaultProject() {
        const defaultProject = SMarkSocialMedia.defaultProject || null;
        const projectId = defaultProject && defaultProject.project_id ? String(defaultProject.project_id).trim() : '';
        const projectName = defaultProject && defaultProject.project_name ? String(defaultProject.project_name).trim() : '';

        if (!projectId) {
            return false;
        }

        selectedProjectId = projectId;
        selectedProjectName = projectName;

        loadProjectItems(projectId);
        return true;
    }

    function getCurrentProjectName() {
        if (selectedProjectName) {
            return selectedProjectName;
        }
        const defaultProject = SMarkSocialMedia.defaultProject || null;
        return defaultProject && defaultProject.project_name ? String(defaultProject.project_name).trim() : '';
    }

    /**
     * Create new project
     */
    function createProject(projectName) {
        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_create_project',
                nonce: SMarkSocialMedia.nonce,
                project_name: projectName
            },
            beforeSend: function() {
                $('#create_project_btn').prop('disabled', true).text(SMarkSocialMedia.strings.loading);
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showNotification(SMarkSocialMedia.strings.success, response.data.message, 'success');

                    // Reload projects
                    loadProjects();

                    // Clear and hide form
                    $('#new_project_name').val('');
                    $('#new_project_form').slideUp();

                    // Select the new project
                    setTimeout(function() {
                        const newProjectName = response.data.project.project_name;
                        const newProjectId = response.data.project.project_id || '';
                        $('#project_select').val(newProjectId);

                        // Manually trigger the selection logic
                        selectedProjectName = newProjectName;
                        selectedProjectId = newProjectId;
                        $('.project-selector').slideUp();
                        $('#selected_project_display').slideDown();
                        $('#selected_project_display .project-name').text(newProjectName);
                        loadProjectItems(newProjectId);
                    }, 500);
                } else {
                    showNotification(SMarkSocialMedia.strings.error, response.data.message, 'error');
                }
            },
            error: function() {
                showNotification(SMarkSocialMedia.strings.error, 'An error occurred. Please try again.', 'error');
            },
            complete: function() {
                $('#create_project_btn').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> ' + SMarkSocialMedia.strings.create);
            }
        });
    }

    /**
     * Show notification
     */
    function showNotification(title, message, type) {
        const isRTL = SMarkSocialMedia.currentLang === 'fa';
        const notificationClass = 'smark-notification' + (type === 'error' ? ' error' : '');

        const notification = $('<div class="' + notificationClass + '"></div>')
            .attr({ role: 'status', 'aria-live': 'polite' });
        const $body = $('<div class="smark-notification__body"></div>');
        $('<strong></strong>').text(String(title || '')).appendTo($body);
        $('<span></span>').text(String(message || '')).appendTo($body);
        const $close = $('<button type="button" class="smark-notification__close" aria-label="Close notification"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>');
        $close.on('click', function() {
            notification.fadeOut(function() {
                notification.remove();
            });
        });
        notification.append($body, $close);
        if (isRTL) {
            notification.addClass('rtl');
        }

        const $container = $('.smark-social-media-page');
        if ($container.length) {
            $container.append(notification);
        } else {
            $('body').append(notification);
        }
    }

    /**
     * Generate the same prompt used in attractive title generation
     */
    function generateAttractiveTitlePrompt(sourceUrl, projectName = null) {
        // Get current language
        const currentLang = SMarkSocialMedia.currentLang || 'fa';
        const outputLanguage = (currentLang === 'fa') ? 'Persian (Farsi)' : 'English';
        const languageInstruction = (currentLang === 'fa') ?
            'Write the title in Persian (Farsi) language.' :
            'Write the title in English language.';

        // Create the same prompt as used in Gemini App
        const prompt = `Create a compelling social media title for: ${sourceUrl}

Requirements:
- ${languageInstruction}
- 50-70 characters
- Engaging and click-worthy
- Include power words if relevant

Provide ONLY the title, nothing else:`;

        return prompt;
    }

    /**
     * Initialize event handlers
     */
    function initEventHandlers() {

        // Event: Show new project form
        $(document).on('click', '#show_new_project_form', function(e) {
            e.preventDefault();
            $('#new_project_form').slideDown();
            $('#new_project_name').focus();
        });

        // Event: Cancel new project
        $(document).on('click', '#cancel_project_btn', function(e) {
            e.preventDefault();
            $('#new_project_form').slideUp();
            $('#new_project_name').val('');
        });

        // Event: Create project
        $(document).on('click', '#create_project_btn', function(e) {
            e.preventDefault();

            const projectName = $('#new_project_name').val().trim();

            if (projectName === '') {
                showNotification(SMarkSocialMedia.strings.error, 'Please enter a project name', 'error');
                $('#new_project_name').focus();
                return;
            }

            createProject(projectName);
        });

        // Event: Press Enter in project name input
        $(document).on('keypress', '#new_project_name', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#create_project_btn').click();
            }
        });

        // Event: Project selection change
        $(document).on('change', '#project_select', function() {
            const projectId = $(this).val();
            const projectName = $('#project_select option:selected').data('name');

            if (projectId) {
                selectedProjectId = projectId;
                selectedProjectName = projectName;

                // Hide selector, show selected project
                $('.project-selector').slideUp();
                $('#selected_project_display').slideDown();
                $('#selected_project_display .project-name').text(projectName);

                // Load project items
                loadProjectItems(projectId);
            }
        });

        // Event: Change project
        $(document).on('click', '.change-project-btn', function(e) {
            e.preventDefault();

            selectedProjectId = null;
            selectedProjectName = null;
            $('#project_select').val('');
            $('#selected_project_display').slideUp();
            $('.project-selector').slideDown();

            // Hide tables and show empty state
            $('#data_table_card').slideUp();
            $('#suggestions_table_card').slideUp();
            $('#empty_state').slideDown();
        });

        // Event: Open add item modal
        $(document).on('click', '#add_new_item_btn', function(e) {
            e.preventDefault();

            if (!selectedProjectId) {
                showNotification(SMarkSocialMedia.strings.error, 'Please select a project first', 'error');
                return;
            }

            // Set to Add mode
            $('#item_id').val('');
            $('#modal_title').text(SMarkSocialMedia.strings.addNewItem);
            $('#save_btn_text').text(SMarkSocialMedia.strings.saveItem);

            // Clear form
            $('#item_headline').val('');
            $('#item_caption').val('');
            $('#item_visual_text').val('');
            $('#item_source').val('');
            $('.char-counter-inside').text('0 / 500');

            // Reset auto-save tracking
            originalHeadlineValue = '';
            if (autoSaveTimeout) {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = null;
            }
            $('#auto_save_indicator').text('').hide();

            // Hide analysis

            // Reset analysis results display
            $('#headline_analysis_results_display').hide();
            $('#no_analysis_message').show();

            // Reset expert status to default and update visual state
            $('#expert_approval_status').val('needs_approval').attr('data-status', 'needs_approval');

            // Show modal
            $('#add_item_modal').fadeIn(300);
            $('#item_headline').focus();
        });

        // Event: Edit item
        $(document).on('click', '.table-action-btn.edit', function(e) {
            e.preventDefault();
            const itemId = $(this).data('id');

            loadItemForEdit(itemId);
        });

        // Event: Change expert status select to update styling attribute and auto-save
        $(document).on('change', '#expert_approval_status', function() {
            const statusVal = $(this).val();
            $(this).attr('data-status', statusVal);

            // Auto-save if editing existing item (not suggestions)
            const itemId = $('#item_id').val();
            const isViewingSuggestion = $('#is_viewing_suggestion').val();

            if (itemId && isViewingSuggestion !== '1') {
                autoSaveAllFields(itemId);
            }
        });

        // Event: Delete item
        $(document).on('click', '.table-action-btn.delete', function(e) {
            e.preventDefault();
            const itemId = $(this).data('id');

            if (confirm('Are you sure you want to delete this item?')) {
                deleteItem(itemId);
            }
        });

        // Event: View suggestion
        $(document).on('click', '.table-action-btn.view-suggestion', function(e) {
            e.preventDefault();
            const suggestionId = $(this).data('id');

            loadSuggestionForView(suggestionId);
        });

        // Event: Transfer suggestion to items
        $(document).on('click', '.table-action-btn.transfer-suggestion', function(e) {
            e.preventDefault();
            const suggestionId = $(this).data('id');

            if (confirm(SMarkSocialMedia.strings.confirmTransferSuggestion)) {
                transferSuggestionToItem(suggestionId);
            }
        });

        // Event: Delete suggestion
        $(document).on('click', '.table-action-btn.delete-suggestion', function(e) {
            e.preventDefault();
            const suggestionId = $(this).data('id');

            if (confirm(SMarkSocialMedia.strings.confirmDeleteSuggestion)) {
                deleteSuggestion(suggestionId);
            }
        });


        // Event: Close modal
        $(document).on('click', '#close_modal, #cancel_item_btn, .modal-overlay', function(e) {
            e.preventDefault();
            $('#add_item_modal').fadeOut(300);

            // Reset form fields
            $('#item_caption').val('');
            $('#item_source').val('');
            $('#item_id').val('');
            $('#suggestion_id').val('');
            $('#is_viewing_suggestion').val('0');

            // Reset auto-save tracking
            originalFieldValues = {
                headline: '',
                visual_text: '',
                caption: '',
                source: '',
                content_link: '',
                published_link: ''
            };
            if (autoSaveTimeout) {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = null;
            }
            $('#auto_save_indicator').text('').hide();
            isAutoSaving = false;

            // No need to re-enable fields as they are always editable now

            // Reset visual upload
            $('#item_visual').val('');
            $('#item_visual_type').val('');
            $('#visual_preview').hide();
            $('#visual_preview .preview-wrapper').empty();
            $('#upload_button_wrapper').show();
        });

        // Event: Character counter and auto-save for headline
        $(document).on('input', '#item_headline', function() {
            const length = $(this).val().length;
            $('.char-counter-inside').text(length + ' / 500');

            // Auto-save if editing existing item (not suggestions)
            const itemId = $('#item_id').val();
            const isViewingSuggestion = $('#is_viewing_suggestion').val();

            if (itemId && isViewingSuggestion !== '1') {
                autoSaveAllFields(itemId);
            }
        });

        // Event: Auto-save for visual_text field
        $(document).on('input', '#item_visual_text', function() {
            const itemId = $('#item_id').val();
            const isViewingSuggestion = $('#is_viewing_suggestion').val();

            if (itemId && isViewingSuggestion !== '1') {
                autoSaveAllFields(itemId);
            }
        });

        // Event: Auto-save for caption field
        $(document).on('input', '#item_caption', function() {
            const itemId = $('#item_id').val();
            const isViewingSuggestion = $('#is_viewing_suggestion').val();

            if (itemId && isViewingSuggestion !== '1') {
                autoSaveAllFields(itemId);
            }
        });

        // Event: Auto-save for source field
        $(document).on('input', '#item_source', function() {
            const itemId = $('#item_id').val();
            const isViewingSuggestion = $('#is_viewing_suggestion').val();

            if (itemId && isViewingSuggestion !== '1') {
                autoSaveAllFields(itemId);
            }
        });

        // Event: Auto-save for content_link field
        $(document).on('input', '#item_content_link', function() {
            const itemId = $('#item_id').val();
            const isViewingSuggestion = $('#is_viewing_suggestion').val();

            if (itemId && isViewingSuggestion !== '1') {
                autoSaveAllFields(itemId);
            }
        });

        // Event: Auto-save for published_link field
        $(document).on('input', '#item_published_link', function() {
            const itemId = $('#item_id').val();
            const isViewingSuggestion = $('#is_viewing_suggestion').val();

            if (itemId && isViewingSuggestion !== '1') {
                autoSaveAllFields(itemId);
            }
        });

        // Event: Create attractive title button
        $(document).on('click', '#create_attractive_title_btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const originalText = $btn.html();

            // Check if we have a source URL
            const sourceUrl = $('#item_source').val().trim();
            if (!sourceUrl) {
                showNotification('خطا', 'لطفاً ابتدا لینک منبع را وارد کنید', 'error');
                return;
            }

            // Disable button and show loading
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update"></span>در حال تولید عنوان...');

            // Get current project name
            const currentProject = getCurrentProjectName();

            // Call Gemini API to generate title
            $.ajax({
                url: SMarkSocialMedia.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'SMARK_generate_attractive_title',
                    nonce: SMarkSocialMedia.nonce,
                    source_url: sourceUrl,
                    project_name: currentProject
                },
                success: function(response) {
                    if (response.success && response.data.title) {
                        // Get current headline value
                        const currentHeadline = $('#item_headline').val().trim();
                        const newTitle = response.data.title;

                        // Create new headline with prefix
                        let updatedHeadline;
                        if (currentHeadline) {
                            // If there's existing content, append new title with prefix
                            updatedHeadline = currentHeadline + '\n\nعنوان جدید: ' + newTitle;
                        } else {
                            // If no existing content, just add the new title
                            updatedHeadline = 'عنوان جدید: ' + newTitle;
                        }

                        // Set the updated headline in the field
                        $('#item_headline').val(updatedHeadline);
                        $('.char-counter-inside').text(updatedHeadline.length + ' / 500');

                        // Update original headline value for auto-save comparison (trimmed)
                        const itemId = $('#item_id').val();
                        if (itemId) {
                            originalFieldValues.headline = updatedHeadline.trim();
                        }

                        showNotification('موفق!', 'عنوان جذاب تولید شد', 'success');
                    } else {
                        showNotification('خطا', response.data.message || 'خطا در تولید عنوان', 'error');
                    }
                },
                error: function() {
                    showNotification('خطا', 'خطا در ارتباط با سرور', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $btn.prop('disabled', false);
                    $btn.html(originalText);
                }
            });
        });

        // Event: Create visual text with GPT button
        $(document).on('click', '#create_visual_text_with_gpt_btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const originalText = $btn.html();

            // Check if we have a source URL
            const sourceUrl = $('#item_source').val().trim();
            if (!sourceUrl) {
                showNotification('خطا', 'لطفاً ابتدا لینک منبع را وارد کنید', 'error');
                return;
            }

            // Disable button and show loading
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update"></span>در حال باز کردن ChatGPT...');

            // Get current project name
            const currentProject = getCurrentProjectName();

            // Get the headline (title) from the form
            const headline = $('#item_headline').val().trim();

            // Get the prompt for visual text from Prompt Bank via AJAX
            $.ajax({
                url: SMarkSocialMedia.ajaxUrl,
                type: 'POST',
                timeout: 30000, // Set 30 second timeout
                data: {
                    action: 'SMARK_get_visual_text_prompt',
                    nonce: SMarkSocialMedia.nonce,
                    source_url: sourceUrl,
                    headline: headline, // Add headline to the request
                    project_name: currentProject
                },
                success: function(response) {
                    if (response.success && response.data.prompt) {
                        const prompt = response.data.prompt;

                        // Store the prompt in localStorage for ChatGPT to access
                        localStorage.setItem('SMARK_gpt_prompt', prompt);
                        localStorage.setItem('SMARK_gpt_timestamp', Date.now().toString());

                        // Create the ChatGPT URL with the prompt
                        const encodedPrompt = encodeURIComponent(prompt);
                        const chatgptUrl = `https://chatgpt.com/?prompt=${encodedPrompt}`;

                        // Open ChatGPT in a new tab - use try/catch for better error handling
                        let newWindow = null;
                        try {
                            newWindow = window.open(chatgptUrl, '_blank');
                        } catch (e) {
                            newWindow = null;
                        }

                        // Give the browser a moment to open the window, then check if it succeeded
                        setTimeout(function() {
                            if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
                                // Window failed to open (popup blocker)
                                showNotification('خطا', 'نمی‌توان تب جدید را باز کرد. لطفاً popup blocker مرورگر خود را غیرفعال کنید و دوباره تلاش کنید.', 'error');
                            } else {
                                // Window opened successfully
                                try {
                                    newWindow.focus();
                                    showNotification('موفق', 'پنجره ChatGPT باز شد. پرامپت به طور خودکار در آنجا قرار می‌گیرد.', 'success');
                                } catch (e) {
                                    showNotification('موفق', 'پنجره ChatGPT باز شد.', 'success');
                                }
                            }
                        }, 500);
                    } else {
                        showNotification('خطا', response.data.message || 'خطا در دریافت پرامپت', 'error');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    if (textStatus === 'timeout') {
                        showNotification('خطا', 'زمان درخواست به پایان رسید. لطفاً دوباره تلاش کنید.', 'error');
                    } else {
                        showNotification('خطا', 'خطا در ارتباط با سرور: ' + textStatus, 'error');
                    }
                },
                complete: function() {
                    // Always re-enable button when request is complete
                    $btn.prop('disabled', false);
                    $btn.html(originalText);
                }
            });
        });

        // Event: Create caption with GPT button
        $(document).on('click', '#create_caption_with_gpt_btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const originalText = $btn.html();

            // Get visual text content for caption generation
            const visualText = $('#item_visual_text').val().trim();
            if (!visualText) {
                showNotification('خطا', 'لطفاً ابتدا متن ویدئو یا تصویر را وارد کنید', 'error');
                return;
            }

            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update"></span>در حال باز کردن ChatGPT...');

            const currentProject = getCurrentProjectName();
            const headline = $('#item_headline').val().trim();

            // Use dedicated caption prompt from Prompt Bank
            $.ajax({
                url: SMarkSocialMedia.ajaxUrl,
                type: 'POST',
                timeout: 30000, // Set 30 second timeout
                data: {
                    action: 'SMARK_get_caption_prompt',
                    nonce: SMarkSocialMedia.nonce,
                    visual_text: visualText,
                    headline: headline,
                    project_name: currentProject
                },
                success: function(response) {
                    if (response.success && response.data.prompt) {
                        const prompt = response.data.prompt;

                        localStorage.setItem('SMARK_gpt_prompt', prompt);
                        localStorage.setItem('SMARK_gpt_timestamp', Date.now().toString());

                        const encodedPrompt = encodeURIComponent(prompt);
                        const chatgptUrl = `https://chatgpt.com/?prompt=${encodedPrompt}`;

                        // Open ChatGPT in a new tab - use try/catch for better error handling
                        let newWindow = null;
                        try {
                            newWindow = window.open(chatgptUrl, '_blank');
                        } catch (e) {
                            newWindow = null;
                        }

                        // Give the browser a moment to open the window, then check if it succeeded
                        setTimeout(function() {
                            if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
                                // Window failed to open (popup blocker)
                                showNotification('خطا', 'نمی‌توان تب جدید را باز کرد. لطفاً popup blocker مرورگر خود را غیرفعال کنید و دوباره تلاش کنید.', 'error');
                            } else {
                                // Window opened successfully
                                try {
                                    newWindow.focus();
                                    showNotification('موفق', 'پنجره ChatGPT باز شد. پرامپت به طور خودکار در آنجا قرار می‌گیرد.', 'success');
                                } catch (e) {
                                    showNotification('موفق', 'پنجره ChatGPT باز شد.', 'success');
                                }
                            }
                        }, 500);
                    } else {
                        showNotification('خطا', response.data.message || 'خطا در دریافت پرامپت', 'error');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    if (textStatus === 'timeout') {
                        showNotification('خطا', 'زمان درخواست به پایان رسید. لطفاً دوباره تلاش کنید.', 'error');
                    } else {
                        showNotification('خطا', 'خطا در ارتباط با سرور: ' + textStatus, 'error');
                    }
                },
                complete: function() {
                    // Always re-enable button when request is complete
                    $btn.prop('disabled', false);
                    $btn.html(originalText);
                }
            });
        });

        // Event: Create title with GPT button
        $(document).on('click', '#create_title_with_gpt_btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const originalText = $btn.html();

            // Check if we have a source URL (same as attractive title button)
            const sourceUrl = $('#item_source').val().trim();

            if (!sourceUrl) {
                showNotification('خطا', 'لطفاً ابتدا لینک منبع را وارد کنید', 'error');
                return;
            }

            // Disable button and show loading
            $btn.prop('disabled', true);
            $btn.html('<span class="dashicons dashicons-update"></span>در حال باز کردن ChatGPT...');

            // Get current project name (same as attractive title button)
            const currentProject = getCurrentProjectName();

            // Get the exact same prompt used in attractive title generation via AJAX
            $.ajax({
                url: SMarkSocialMedia.ajaxUrl,
                type: 'POST',
                timeout: 30000, // Set 30 second timeout
                data: {
                    action: 'SMARK_get_attractive_title_prompt',
                    nonce: SMarkSocialMedia.nonce,
                    source_url: sourceUrl,
                    project_name: currentProject
                },
                success: function(response) {
                    if (response.success && response.data.prompt) {
                        const prompt = response.data.prompt;

                        // Store the prompt in localStorage for ChatGPT to access
                        localStorage.setItem('SMARK_gpt_prompt', prompt);
                        localStorage.setItem('SMARK_gpt_timestamp', Date.now().toString());

                        // Create the ChatGPT URL with the prompt using URL parameters
                        const encodedPrompt = encodeURIComponent(prompt);
                        const chatgptUrl = `https://chatgpt.com/?prompt=${encodedPrompt}`;

                        // Open ChatGPT in a new tab - use try/catch for better error handling
                        let newWindow = null;
                        try {
                            newWindow = window.open(chatgptUrl, '_blank');
                        } catch (e) {
                            newWindow = null;
                        }

                        // Give the browser a moment to open the window, then check if it succeeded
                        setTimeout(function() {
                            if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
                                // Window failed to open (popup blocker)
                                showNotification('خطا', 'نمی‌توان تب جدید را باز کرد. لطفاً popup blocker مرورگر خود را غیرفعال کنید و دوباره تلاش کنید.', 'error');
                            } else {
                                // Window opened successfully
                                try {
                                    newWindow.focus();
                                    showNotification('موفق', 'پنجره ChatGPT باز شد. پرامپت به طور خودکار در آنجا قرار می‌گیرد.', 'success');
                                } catch (e) {
                                    showNotification('موفق', 'پنجره ChatGPT باز شد.', 'success');
                                }
                            }
                        }, 500);
                    } else {
                        const errorMsg = response.data && response.data.message ? response.data.message : 'خطا در دریافت پرامپت';
                        showNotification('خطا', errorMsg, 'error');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    if (textStatus === 'timeout') {
                        showNotification('خطا', 'زمان درخواست به پایان رسید. لطفاً دوباره تلاش کنید.', 'error');
                    } else {
                        showNotification('خطا', 'خطا در ارتباط با سرور: ' + textStatus, 'error');
                    }
                },
                complete: function() {
                    // Always re-enable button when request is complete
                    $btn.prop('disabled', false);
                    $btn.html(originalText);
                }
            });
        });

        // Event: Analyze headline button
        $(document).on('click', '#analyze_headline_btn', function(e) {
            e.preventDefault();

            // Prevent multiple simultaneous requests
            if (isAnalyzing) {
                return;
            }

            const headline = $('#item_headline').val().trim();

            if (headline.length === 0) {
                showNotification(SMarkSocialMedia.strings.error, SMarkSocialMedia.strings.pleaseEnterHeadline, 'error');
                return;
            }

            // Set analyzing flag
            isAnalyzing = true;

            // Show loading state
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> ' + SMarkSocialMedia.strings.analyzing);

            // Analyze headline and pass button reference
            analyzeHeadline(headline, $btn);
        });

        // Event: Save item
        $(document).on('click', '#save_item_btn', function(e) {
            e.preventDefault();

            const headline = $('#item_headline').val().trim();
            const itemId = $('#item_id').val();
            const suggestionId = $('#suggestion_id').val();
            const isViewingSuggestion = $('#is_viewing_suggestion').val();

            // If editing a suggestion, update the suggestion
            if (isViewingSuggestion === '1' && suggestionId) {
                updateSuggestion(suggestionId, headline);
                return;
            }

            if (headline === '') {
                showNotification(SMarkSocialMedia.strings.error, 'Please enter a headline', 'error');
                $('#item_headline').focus();
                return;
            }

            if (!selectedProjectName) {
                showNotification(SMarkSocialMedia.strings.error, 'No project selected', 'error');
                return;
            }

            if (itemId) {
                // Update existing item
                updateItem(itemId, headline);
            } else {
                // Add new item
                saveItem(selectedProjectName, headline);
            }
        });

    }

    /**
     * Analyze headline
     */
    function analyzeHeadline(headline, $btn) {

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_analyze_headline_quick',
                nonce: SMarkSocialMedia.nonce,
                headline: headline
            },
            success: function(response) {

                if (response.success) {
                    displayAnalysis(response.data);
                } else {
                    showNotification(SMarkSocialMedia.strings.error, response.data.message || 'Analysis failed', 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification(SMarkSocialMedia.strings.error, 'Failed to analyze headline', 'error');
            },
            complete: function() {
                // Reset analyzing flag
                isAnalyzing = false;

                // Reset button state after request completes
                if ($btn) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-line"></span> ' + SMarkSocialMedia.strings.analyzeHeadline);
                }
            }
        });
    }

    /**
     * Display analysis results
     */
    function displayAnalysis(data) {

        // Generate analysis HTML and display it
        const analysisHtml = generateAnalysisHtml(data);
        $('#analysis_results_content').html(analysisHtml);
        $('#headline_analysis_results_display').show();
        $('#no_analysis_message').hide();

        // Save to database if we have an item ID (for existing items)
        const itemId = $('#item_id').val();
        if (itemId) {
            saveAnalysisResults(itemId, analysisHtml, data.score);
        }
    }

    /**
     * Save analysis results to database
     */
    function saveAnalysisResults(itemId, analysisHtml, score) {
        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_save_analysis_results',
                nonce: SMarkSocialMedia.nonce,
                item_id: itemId,
                analysis_results: analysisHtml,
                score: score
            },
            success: function(response) {
                if (response.success) {
                } else {
                }
            },
            error: function(xhr, status, error) {
            }
        });
    }

    /**
     * Load item for editing
     */
    function loadItemForEdit(itemId) {

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_get_item',
                nonce: SMarkSocialMedia.nonce,
                item_id: itemId
            },
            success: function(response) {

                if (response.success) {
                    const item = response.data.item;

                    // Set to Edit mode
                    $('#item_id').val(item.id);
                    $('#modal_title').text(SMarkSocialMedia.strings.editItem);
                    $('#save_btn_text').text(SMarkSocialMedia.strings.updateItem);

                    // Fill form
                    $('#item_headline').val(item.headline);
                    $('.char-counter-inside').text(item.headline.length + ' / 500');

                    // Store original field values for auto-save comparison (trimmed)
                    originalFieldValues = {
                        headline: (item.headline || '').trim(),
                        visual_text: (item.visual_text || '').trim(),
                        caption: (item.caption || '').trim(),
                        source: (item.source || '').trim(),
                        content_link: (item.content_link || '').trim(),
                        published_link: (item.published_link || '').trim()
                    };

                    // Fill caption
                    $('#item_caption').val(item.caption || '');

                    // Fill visual text
                    $('#item_visual_text').val(item.visual_text || '');

                    // Fill content link
                    $('#item_content_link').val(item.content_link || '');

                    // Fill source
                    $('#item_source').val(item.source || '');

                    // Fill content and published links
                    $('#item_content_link').val(item.content_link || '');
                    $('#item_published_link').val(item.published_link || '');

            // Fill expert approval status
            const expertStatus = item.expert_approval_status || 'needs_approval';
            $('#expert_approval_status').val(expertStatus).attr('data-status', expertStatus);

                    // Handle analysis results
                    if (item.headline_analysis_results) {
                        // Normalize the stored HTML to ensure consistent styling
                        try {
                            let analysisHtml = normalizeAnalysisHtml(item.headline_analysis_results);
                            $('#analysis_results_content').html(analysisHtml);
                            $('#headline_analysis_results_display').show();
                            $('#no_analysis_message').hide();
                        } catch (error) {
                            $('#headline_analysis_results_display').hide();
                            $('#no_analysis_message').show();
                        }
                    } else {
                        $('#headline_analysis_results_display').hide();
                        $('#no_analysis_message').show();
                    }

                    // Handle visual if exists
                    // Check if visual exists and is not empty/null
                    const visualUrl = (item.visual && item.visual.trim() !== '') ? item.visual : '';
                    $('#item_visual').val(visualUrl);
                    $('#item_visual_type').val(item.visual_type || '');
                    if (visualUrl) {
                        showVisualPreview(visualUrl, item.visual_type);
                        $('#upload_button_wrapper').hide();
                        $('#visual_preview').show();
                    } else {
                        $('#visual_preview').hide();
                        $('#upload_button_wrapper').show();
                    }

                    // Don't analyze automatically - let user click the button
                    // analyzeHeadline(item.headline);

                    // Show modal
                    $('#add_item_modal').fadeIn(300);
                    $('#item_headline').focus();
                } else {
                    showNotification(SMarkSocialMedia.strings.error, response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification(SMarkSocialMedia.strings.error, 'Failed to load item', 'error');
            }
        });
    }

    /**
     * Save new item
     */
    function saveItem(projectName, headline) {

        const visual = $('#item_visual').val() || null;
        const visual_type = $('#item_visual_type').val() || null;
        const content_link = $('#item_content_link').val().trim() || null;
        const published_link = $('#item_published_link').val().trim() || null;
        const visual_text = $('#item_visual_text').val().trim() || null;
        const caption = $('#item_caption').val().trim() || null;
        const source = $('#item_source').val().trim() || null;
        const expert_approval_status = $('#expert_approval_status').val();
        const analysis_results = $('#analysis_results_content').html();

        // Extract score from analysis results if available
        let score = 0;
        if (analysis_results && analysis_results.trim() !== '') {

            // Try to extract score specifically from the score section
            let scoreMatch = null;

            // First try: Look for the score section specifically
            const scoreSectionMatch = analysis_results.match(/<div class="analysis-label">امتیاز<\/div>\s*<div class="analysis-value">(\d+)<\/div>/i);
            if (scoreSectionMatch) {
                scoreMatch = scoreSectionMatch;
            } else {
                // Fallback: Look for the last analysis-value div (which should be score)
                const allValues = analysis_results.match(/<div class="analysis-value">(\d+)<\/div>/g);
                if (allValues && allValues.length > 0) {
                    // Get the last one (score is usually the last item)
                    const lastValue = allValues[allValues.length - 1];
                    const numberMatch = lastValue.match(/(\d+)/);
                    if (numberMatch) {
                        scoreMatch = [lastValue, numberMatch[1]];
                    }
                }
            }

            if (scoreMatch) {
                score = parseInt(scoreMatch[1]);
            }
        } else {
        }


        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_add_item',
                nonce: SMarkSocialMedia.nonce,
                project_name: projectName,
                headline: headline,
                visual: visual,
                visual_type: visual_type,
                content_link: content_link,
                published_link: published_link,
                visual_text: visual_text,
                caption: caption,
                source: source,
                expert_approval_status: expert_approval_status,
                headline_analysis_results: analysis_results,
                score: score
            },
            beforeSend: function() {
                $('#save_item_btn').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + SMarkSocialMedia.strings.saving);
            },
            success: function(response) {

                if (response.success) {
                    showNotification(SMarkSocialMedia.strings.success, response.data.message, 'success');

                    // Close modal
                    $('#add_item_modal').fadeOut(300);

                    // Reload items
                    loadProjectItems(selectedProjectId);
                } else {
                    showNotification(SMarkSocialMedia.strings.error, response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification(SMarkSocialMedia.strings.error, 'An error occurred. Please try again.', 'error');
            },
            complete: function() {
                $('#save_item_btn').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> <span id="save_btn_text">Save Item</span>');
            }
        });
    }

    /**
     * Auto-save all fields with debouncing
     */
    function autoSaveAllFields(itemId) {
        if (!itemId) {
            return;
        }

        // Clear existing timeout
        if (autoSaveTimeout) {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = null;
        }

        // Get current field values (trimmed for comparison)
        const currentValues = {
            headline: $('#item_headline').val().trim(),
            visual_text: $('#item_visual_text').val().trim(),
            caption: $('#item_caption').val().trim(),
            source: $('#item_source').val().trim(),
            content_link: $('#item_content_link').val().trim(),
            published_link: $('#item_published_link').val().trim()
        };

        // Check if any field has changed
        let hasChanges = false;
        for (let field in currentValues) {
            const originalValue = (originalFieldValues[field] || '').trim();
            if (currentValues[field] !== originalValue) {
                hasChanges = true;
                break;
            }
        }

        // Don't save if nothing has changed
        if (!hasChanges) {
            return;
        }

        // Don't save if headline is empty (required field)
        if (!currentValues.headline) {
            return;
        }

        // Show saving indicator
        showAutoSaveStatus('saving');

        // Debounce: wait 1 second after user stops typing
        autoSaveTimeout = setTimeout(function() {
            // Check if already saving, if so reschedule
            if (isAutoSaving) {
                autoSaveTimeout = setTimeout(function() {
                    performAutoSave(itemId);
                }, 500);
                return;
            }

            performAutoSave(itemId);
        }, 1000);
    }

    /**
     * Perform the actual auto-save operation
     */
    function performAutoSave(itemId) {
        if (isAutoSaving) {
            return;
        }

        isAutoSaving = true;
        const headline = $('#item_headline').val().trim();

        // Validate headline is not empty (required field)
        if (!headline) {
            isAutoSaving = false;
            showAutoSaveStatus('error');
            return;
        }

        // Call auto-save version of update
        updateItemAutoSave(itemId, headline);
    }

    /**
     * Show auto-save status indicator
     */
    function showAutoSaveStatus(status) {
        let statusIndicator = $('#auto_save_indicator');

        // Create indicator if it doesn't exist
        if (!statusIndicator.length) {
            statusIndicator = $('<span id="auto_save_indicator" style="font-size: 12px; margin-left: 10px; color: #666; display: inline-block;"></span>');
            $('.char-counter-inside').after(statusIndicator);
        }

        // Always show the indicator
        statusIndicator.show();

        const currentLang = SMarkSocialMedia.currentLang || 'fa';

        if (status === 'saving') {
            statusIndicator.text(currentLang === 'fa' ? 'در حال ذخیره...' : 'Saving...').css('color', '#7D2AE7');
        } else if (status === 'saved') {
            statusIndicator.text(currentLang === 'fa' ? 'ذخیره شد ✓' : 'Saved ✓').css('color', '#00a32a');
            setTimeout(function() {
                statusIndicator.fadeOut(500, function() {
                    statusIndicator.text('').show();
                });
            }, 2000);
        } else if (status === 'error') {
            statusIndicator.text(currentLang === 'fa' ? 'خطا در ذخیره ✗' : 'Save error ✗').css('color', '#d63638');
            setTimeout(function() {
                statusIndicator.fadeOut(500, function() {
                    statusIndicator.text('').show();
                });
            }, 3000);
        }
    }

    /**
     * Auto-save version of updateItem (doesn't close modal or reload)
     */
    function updateItemAutoSave(itemId, headline) {
        const visual = $('#item_visual').val() || null;
        const visual_type = $('#item_visual_type').val() || null;
        const content_link = $('#item_content_link').val().trim() || null;
        const published_link = $('#item_published_link').val().trim() || null;
        const visual_text = $('#item_visual_text').val().trim() || null;
        const caption = $('#item_caption').val().trim() || null;
        const source = $('#item_source').val().trim() || null;
        const expert_approval_status = $('#expert_approval_status').val();
        const analysis_results = $('#analysis_results_content').html();

        // Extract score from analysis results if available
        let score = 0;
        if (analysis_results && analysis_results.trim() !== '') {
            let scoreMatch = null;
            const scoreSectionMatch = analysis_results.match(/<div class="analysis-label">امتیاز<\/div>\s*<div class="analysis-value">(\d+)<\/div>/i);
            if (scoreSectionMatch) {
                scoreMatch = scoreSectionMatch;
            } else {
                const allValues = analysis_results.match(/<div class="analysis-value">(\d+)<\/div>/g);
                if (allValues && allValues.length > 0) {
                    const lastValue = allValues[allValues.length - 1];
                    const numberMatch = lastValue.match(/(\d+)/);
                    if (numberMatch) {
                        scoreMatch = [lastValue, numberMatch[1]];
                    }
                }
            }
            if (scoreMatch) {
                score = parseInt(scoreMatch[1]);
            }
        }

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_update_item',
                nonce: SMarkSocialMedia.nonce,
                item_id: itemId,
                headline: headline,
                visual: visual,
                visual_type: visual_type,
                content_link: content_link,
                published_link: published_link,
                visual_text: visual_text,
                caption: caption,
                source: source,
                expert_approval_status: expert_approval_status,
                headline_analysis_results: analysis_results,
                score: score
            },
            success: function(response) {
                if (response.success) {
                    // Update original field values to current values (trimmed)
                    originalFieldValues = {
                        headline: $('#item_headline').val().trim(),
                        visual_text: $('#item_visual_text').val().trim(),
                        caption: $('#item_caption').val().trim(),
                        source: $('#item_source').val().trim(),
                        content_link: $('#item_content_link').val().trim(),
                        published_link: $('#item_published_link').val().trim()
                    };
                    showAutoSaveStatus('saved');
                } else {
                    showAutoSaveStatus('error');
                }
                isAutoSaving = false;
            },
            error: function(xhr, status, error) {
                showAutoSaveStatus('error');
                isAutoSaving = false;
            }
        });
    }

    /**
     * Update existing item
     */
    function updateItem(itemId, headline) {

        const visual = $('#item_visual').val() || null;
        const visual_type = $('#item_visual_type').val() || null;
        const content_link = $('#item_content_link').val().trim() || null;
        const published_link = $('#item_published_link').val().trim() || null;
        const visual_text = $('#item_visual_text').val().trim() || null;
        const caption = $('#item_caption').val().trim() || null;
        const source = $('#item_source').val().trim() || null;
        const expert_approval_status = $('#expert_approval_status').val();
        const analysis_results = $('#analysis_results_content').html();

        // Extract score from analysis results if available
        let score = 0;
        if (analysis_results && analysis_results.trim() !== '') {

            // Try to extract score specifically from the score section
            let scoreMatch = null;

            // First try: Look for the score section specifically
            const scoreSectionMatch = analysis_results.match(/<div class="analysis-label">امتیاز<\/div>\s*<div class="analysis-value">(\d+)<\/div>/i);
            if (scoreSectionMatch) {
                scoreMatch = scoreSectionMatch;
            } else {
                // Fallback: Look for the last analysis-value div (which should be score)
                const allValues = analysis_results.match(/<div class="analysis-value">(\d+)<\/div>/g);
                if (allValues && allValues.length > 0) {
                    // Get the last one (score is usually the last item)
                    const lastValue = allValues[allValues.length - 1];
                    const numberMatch = lastValue.match(/(\d+)/);
                    if (numberMatch) {
                        scoreMatch = [lastValue, numberMatch[1]];
                    }
                }
            }

            if (scoreMatch) {
                score = parseInt(scoreMatch[1]);
            }
        } else {
        }


        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_update_item',
                nonce: SMarkSocialMedia.nonce,
                item_id: itemId,
                headline: headline,
                visual: visual,
                visual_type: visual_type,
                content_link: content_link,
                published_link: published_link,
                visual_text: visual_text,
                caption: caption,
                source: source,
                expert_approval_status: expert_approval_status,
                headline_analysis_results: analysis_results,
                score: score
            },
            beforeSend: function() {
                $('#save_item_btn').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + SMarkSocialMedia.strings.updating);
            },
            success: function(response) {

                if (response.success) {
                    // Update original field values for auto-save tracking (trimmed)
                    originalFieldValues = {
                        headline: $('#item_headline').val().trim(),
                        visual_text: $('#item_visual_text').val().trim(),
                        caption: $('#item_caption').val().trim(),
                        source: $('#item_source').val().trim(),
                        content_link: $('#item_content_link').val().trim(),
                        published_link: $('#item_published_link').val().trim()
                    };

                    showNotification(SMarkSocialMedia.strings.success, response.data.message, 'success');

                    // Close modal
                    $('#add_item_modal').fadeOut(300);

                    // Reload items
                    loadProjectItems(selectedProjectId);
                } else {
                    showNotification(SMarkSocialMedia.strings.error, response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification(SMarkSocialMedia.strings.error, 'An error occurred. Please try again.', 'error');
            },
            complete: function() {
                $('#save_item_btn').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> <span id="save_btn_text">Update Item</span>');
            }
        });
    }

    /**
     * Delete item
     */
    function deleteItem(itemId) {

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_delete_item',
                nonce: SMarkSocialMedia.nonce,
                item_id: itemId
            },
            success: function(response) {

                if (response.success) {
                    showNotification(SMarkSocialMedia.strings.success, response.data.message, 'success');

                    // Reload items
                    loadProjectItems(selectedProjectId);
                } else {
                    showNotification(SMarkSocialMedia.strings.error, response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification(SMarkSocialMedia.strings.error, 'Failed to delete item', 'error');
            }
        });
    }

    /**
     * Handle visual file selection button
     */
    $(document).on('click', '#select_visual_btn', function() {
        $('#visual_file_input').click();
    });

    /**
     * Handle file input change
     */
    $(document).on('change', '#visual_file_input', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file size (10MB max)
        const maxSize = 10 * 1024 * 1024;
        if (file.size > maxSize) {
            showNotification('Error', 'File size exceeds 10MB limit', 'error');
            return;
        }

        // Validate file type
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo'];
        if (!validTypes.includes(file.type)) {
            showNotification('Error', 'Invalid file type. Only images and videos are allowed.', 'error');
            return;
        }

        // Upload file
        uploadVisualFile(file);
    });

    /**
     * Upload visual file to server
     */
    function uploadVisualFile(file) {
        const formData = new FormData();
        formData.append('action', 'SMARK_upload_visual');
        formData.append('nonce', SMarkSocialMedia.nonce);
        formData.append('file', file);

        // Show loading state
        $('#select_visual_btn').prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Uploading...');

        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {

                if (response.success) {
                    const uploadedUrl = response.data.url;
                    const uploadedType = response.data.type;
                    $('#item_visual').val(uploadedUrl);
                    $('#item_visual_type').val(uploadedType);
                    showVisualPreview(uploadedUrl, uploadedType);

                    // Hide upload button, show preview
                    $('#upload_button_wrapper').hide();
                    $('#visual_preview').fadeIn();

                    showNotification('Success', response.data.message, 'success');
                } else {
                    showNotification('Error', response.data.message || 'Failed to upload file', 'error');
                }

                // Reset button state
                $('#select_visual_btn').prop('disabled', false).html('<span class="dashicons dashicons-format-image"></span> Choose File');
                $('#visual_file_input').val('');
            },
            error: function(xhr, status, error) {
                showNotification('Error', 'Failed to upload file', 'error');
                $('#select_visual_btn').prop('disabled', false).html('<span class="dashicons dashicons-format-image"></span> Choose File');
                $('#visual_file_input').val('');
            }
        });
    }

    /**
     * Show visual preview
     */
    function showVisualPreview(url, type) {
        const $previewWrapper = $('#visual_preview .preview-wrapper');
        $previewWrapper.empty();

        if (type && type.startsWith('image/')) {
            $previewWrapper.html(`<img src="${url}" alt="Preview">`);
        } else if (type && type.startsWith('video/')) {
            $previewWrapper.html(`<video src="${url}" controls></video>`);
        } else {
            // Guess from URL extension
            const isVideo = url.match(/\.(mp4|mpeg|mov|avi)$/i);
            if (isVideo) {
                $previewWrapper.html(`<video src="${url}" controls></video>`);
            } else {
                $previewWrapper.html(`<img src="${url}" alt="Preview">`);
            }
        }
    }

    /**
     * Handle remove visual button
     */
    $(document).on('click', '.remove-visual', function() {
        $('#item_visual').val('');
        $('#item_visual_type').val('');
        $('#visual_preview').fadeOut(function() {
            $('#visual_preview .preview-wrapper').empty();
            $('#upload_button_wrapper').fadeIn();
        });
    });

    /**
     * Handle language selection change
     */
    $(document).on('change', '#SMARK_language_select', function() {
        const selectedLanguage = $(this).val();

        // Save language preference via AJAX
        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_save_language',
                nonce: SMarkSocialMedia.nonce,
                language: selectedLanguage
            },
            success: function(response) {
                if (response.success) {

                    // Show notification
                    showNotification(
                        selectedLanguage === 'fa' ? 'موفق!' : 'Success!',
                        selectedLanguage === 'fa' ? 'زبان پنل تغییر یافت. صفحه در حال بارگذاری مجدد است...' : 'Panel language changed. Reloading page...',
                        'success'
                    );

                    // Reload page after a short delay to apply language changes
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotification(
                        'Error',
                        'Failed to save language preference',
                        'error'
                    );
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error', 'Failed to save language preference', 'error');
            }
        });
    });

    /**
     * Fix breadcrumb order based on RTL/LTR
     */
    function fixBreadcrumbOrder() {
        const $page = $('.smark-social-media-page');
        const $breadcrumb = $('.smark-breadcrumb');
        const $breadcrumbLeft = $('.breadcrumb-left');
        const $breadcrumbRight = $('.breadcrumb-right');


        if ($page.hasClass('rtl')) {
            // RTL: In RTL mode with direction:rtl, visual left is order 2, visual right is order 1
            // We want language selector on visual LEFT, so it needs order 2
            // We want breadcrumb text on visual RIGHT, so it needs order 1

            $breadcrumbLeft[0].style.setProperty('order', '1', 'important');
            $breadcrumbRight[0].style.setProperty('order', '2', 'important');
            $breadcrumbLeft[0].style.setProperty('margin-left', 'auto', 'important');
            $breadcrumbLeft[0].style.setProperty('margin-right', '0', 'important');
            $breadcrumbRight[0].style.setProperty('margin-left', '0', 'important');
            $breadcrumbRight[0].style.setProperty('margin-right', '0', 'important');

        } else {
            // LTR: Breadcrumb text (left) should appear first (on left side)

            $breadcrumbLeft[0].style.setProperty('order', '1', 'important');
            $breadcrumbRight[0].style.setProperty('order', '2', 'important');
            $breadcrumbLeft[0].style.setProperty('margin-left', '0', 'important');
            $breadcrumbLeft[0].style.setProperty('margin-right', '0', 'important');
            $breadcrumbRight[0].style.setProperty('margin-left', 'auto', 'important');
            $breadcrumbRight[0].style.setProperty('margin-right', '0', 'important');

        }
    }

    /**
     * Re-apply breadcrumb order after a delay to override any late-loading CSS
     */
    function ensureBreadcrumbOrder() {
        setTimeout(function() {
            fixBreadcrumbOrder();
        }, 500);
    }

    // Initialize everything
    initEventHandlers();
    if (!initDefaultProject()) {
        loadProjectItems('');
    }

    // Fix breadcrumb order immediately
    fixBreadcrumbOrder();

    // Re-apply after page fully loads
    ensureBreadcrumbOrder();

    // Also fix on window load event
    $(window).on('load', function() {
        fixBreadcrumbOrder();
    });

    /**
     * Canva Template Copy Button Handler
     */
    $(document).on('click', '#copy_canva_template_btn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const originalHtml = $btn.html();

        // Check if a project is selected
        if (!selectedProjectId) {
            showNotification('خطا', 'لطفاً ابتدا یک پروژه انتخاب کنید', 'error');
            return;
        }

        // Disable button and show loading
        $btn.prop('disabled', true);
        $btn.html('<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><circle cx="12" cy="12" r="10" stroke="white" stroke-width="2" fill="none" stroke-dasharray="31.4" stroke-dashoffset="10"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></circle></svg> در حال دریافت قالب...');

        // Get Canva template from project management
        $.ajax({
            url: SMarkSocialMedia.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_sm_get_canva_template',
                nonce: SMarkSocialMedia.nonce,
                project_id: selectedProjectId
            },
            success: function(response) {
                if (response.success && response.data.canva_template) {
                    const canvaLink = response.data.canva_template;

                    // Open the Canva template link in a new window
                    window.open(canvaLink, '_blank');

                    showNotification('موفق', 'قالب کانوا در پنجره جدید باز شد', 'success');
                } else {
                    // No template found - show instructions
                    const message = `
                        <div style="text-align: right; direction: rtl; line-height: 1.8;">
                            <h3 style="color: #7D2AE7; margin-bottom: 15px;">⚠️ قالب کانوا یافت نشد</h3>
                            <p style="margin-bottom: 15px;">برای این پروژه هنوز لینک قالب کانوا تنظیم نشده است. برای ایجاد و تنظیم لینک قالب، این مراحل را دنبال کنید:</p>
                            <ol style="padding-right: 20px; margin-bottom: 20px;">
                                <li style="margin-bottom: 10px;">به بخش <strong>"مدیریت پروژه‌ها"</strong> بروید</li>
                                <li style="margin-bottom: 10px;">طرح خود را در Canva باز کنید</li>
                                <li style="margin-bottom: 10px;">روی دکمه <strong>"Share"</strong> کلیک کنید</li>
                                <li style="margin-bottom: 10px;">گزینه <strong>"Template link"</strong> یا <strong>"Share as template"</strong> را انتخاب کنید</li>
                                <li style="margin-bottom: 10px;">لینک ایجاد شده را کپی کنید</li>
                                <li style="margin-bottom: 10px;">در بخش مدیریت پروژه‌ها، لینک را در فیلد "قالب کانوا" وارد کنید</li>
                            </ol>
                            <div style="background: #f0f7ff; padding: 12px; border-radius: 8px; border-right: 4px solid #7D2AE7; margin-top: 15px;">
                                <strong>💡 نکته:</strong> این قابلیت ممکن است نیاز به اشتراک Canva Pro داشته باشد.
                            </div>
                        </div>
                    `;

                    showNotification('راهنمای تنظیم قالب کانوا', message, 'info', 15000);
                }
            },
            error: function(xhr, status, error) {
                showNotification('خطا', 'خطا در دریافت لینک قالب کانوا', 'error');
            },
            complete: function() {
                // Reset button
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

});
