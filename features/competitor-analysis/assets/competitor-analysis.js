jQuery(document).ready(function($) {
    // Only run on competitor analysis pages
    if (!$('body').hasClass('smark-competitor-analysis-page') && !$('.smark-competitor-analysis-page').length) {
        return;
    }
    'use strict';

    let selectedProject = null; // Will store {id: 'PRJ-xxxxx', name: 'ProjectName'}
    let currentItemId = null;
    let isEditMode = false;

    /**
     * Remove WP Rocket notifications
     */
    function removeWPRocketNotifications() {
        $('.notice.notice-success.is-dismissible').each(function() {
            const $notice = $(this);
            const noticeText = $notice.text().toLowerCase();
            if (noticeText.includes('wp rocket') && noticeText.includes('cache cleared')) {
                $notice.remove();
            }
        });
    }

    // Remove WP Rocket notifications on page load
    removeWPRocketNotifications();

    // Also remove them after a short delay in case they load dynamically
    setTimeout(removeWPRocketNotifications, 1000);

    /**
     * Competitor Analysis Page Layout Fix
     * Implements sticky footer functionality similar to social media page
     */
    function fixCompetitorAnalysisLayout() {
        const p = document.querySelector('#wpbody');
        const c = document.querySelector('#wpbody-content');
        const w = document.querySelector('.wrap.smark-competitor-analysis-page');

        if (p && c) {
            c.style.height = getComputedStyle(p).height;
            c.style.minHeight = c.style.height;
            c.style.float = 'none';
            c.style.paddingBottom = '0';

        }

        // Apply the same height to .wrap.smark-competitor-analysis-page
        if (p && w) {
            w.style.height = getComputedStyle(p).height;
            w.style.minHeight = w.style.height;
            w.style.float = 'none';
            w.style.paddingBottom = '0';

        }

        // Additional layout fixes for sticky footer
        const competitorAnalysisPage = document.querySelector('.smark-competitor-analysis-page');
        const mainContent = document.querySelector('.smark-competitor-analysis-content');
        const footer = document.querySelector('.smark-version-footer');

        if (competitorAnalysisPage) {
            competitorAnalysisPage.style.display = 'flex';
            competitorAnalysisPage.style.flexDirection = 'column';
            competitorAnalysisPage.style.justifyContent = 'space-between';
        }

        if (mainContent) {
            mainContent.style.flex = '1';
        }

        if (footer) {
            footer.style.marginTop = 'auto';
        }
    }

    // Run layout fix multiple times to ensure it works
    fixCompetitorAnalysisLayout();

    // Run again after a short delay
    setTimeout(fixCompetitorAnalysisLayout, 100);
    setTimeout(fixCompetitorAnalysisLayout, 500);

    // Run on window resize
    $(window).on('resize', function() {
        fixCompetitorAnalysisLayout();
    });

    // Run when WordPress admin menu is toggled
    $(document).on('wp-window-resized', function() {
        setTimeout(fixCompetitorAnalysisLayout, 100);
    });

    /**
     * Fix undefined text in table
     */
    function fixUndefinedText() {
        // Only fix text nodes without breaking event handlers
        $('*').contents().filter(function() {
            return this.nodeType === 3; // Text nodes
        }).each(function() {
            if (this.nodeValue && this.nodeValue.trim() === 'undefined') {
                this.nodeValue = 'تعریف نشده';
            }
        });
    }

    // Fix undefined text on page load and after table updates
    setTimeout(fixUndefinedText, 500);
    setTimeout(fixUndefinedText, 1500);
    setTimeout(fixUndefinedText, 3000);

    // Only run on AJAX success for competitor analysis pages
    $(document).ajaxSuccess(function(event, xhr, settings) {
        // Only run on competitor analysis AJAX calls
        if (settings.url && settings.url.includes('admin-ajax.php') &&
            settings.data && settings.data.includes('SMARK_competitor')) {
            setTimeout(fixUndefinedText, 200);
        }
    });

    /**
     * Load all projects
     */
    function loadProjects() {
        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_get_projects',
                nonce: SMarkCompetitorAnalysis.nonce
            },
            success: function(response) {
                if (response.success) {
                    const projects = response.data;
                    const $select = $('#project_select');

                    $select.empty();

                    if (projects && projects.length > 0) {
                        $select.append('<option value="">' + SMarkCompetitorAnalysis.strings.selectProject + '</option>');

                        projects.forEach(function(project) {
                            // Store project_id in value and project_name in data attribute
                            const projectId = project.project_id || '';
                            const projectName = project.project_name || '';
                            $select.append('<option value="' + projectId + '" data-project-name="' + projectName + '">' + projectName + '</option>');
                        });
                    } else {
                        $select.append('<option value="">' + SMarkCompetitorAnalysis.strings.selectProject + '</option>');
                    }

                    // Remove loading text
                    $select.removeClass('loading');
                } else {
                    const $select = $('#project_select');
                    $select.empty();
                    $select.append('<option value="">Error loading projects</option>');
                }
            },
            error: function(xhr, status, error) {
                const $select = $('#project_select');
                $select.empty();
                $select.append('<option value="">Error loading projects</option>');
            }
        });
    }

    /**
     * Load project items
     */
    function loadProjectItems(projectId, projectName) {
        // Show loading state
        $('#data_table_body').html('<tr class="no-data-row"><td colspan="5">' + SMarkCompetitorAnalysis.strings.loading + '</td></tr>');

        // Hide empty state and show table
        $('#empty_state').fadeOut(300, function() {
            $('#data_table_card').fadeIn(300);
        });

        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_get_project_items',
                nonce: SMarkCompetitorAnalysis.nonce,
                project_id: projectId
            },
            success: function(response) {
                if (response.success) {
                    const items = response.data.items;
                    $('.current-project-name').text(projectName);

                    if (items && items.length > 0) {
                        renderTableItems(items);
                    } else {
                        $('#data_table_body').html('<tr class="no-data-row"><td colspan="5">' + SMarkCompetitorAnalysis.strings.no_items_found + '</td></tr>');
                    }
                } else {
                    $('#data_table_body').html('<tr class="no-data-row"><td colspan="5">Error loading items</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                $('#data_table_body').html('<tr class="no-data-row"><td colspan="5">Error loading items</td></tr>');
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
            const locale = SMarkCompetitorAnalysis.currentLang === 'fa' ? 'fa-IR' : 'en-US';
            const dateOptions = {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            };

            let createdDate;
            try {
                createdDate = new Date(item.created_at).toLocaleDateString(locale, dateOptions);
            } catch (e) {
                createdDate = new Date(item.created_at).toLocaleDateString('en-US', dateOptions);
            }

            const websiteName = item.website_name || 'N/A';
            const websiteUrl = item.website_url;

            // Handle undefined/null website URL
            let websiteUrlDisplay, websiteUrlLink;

            // Check if websiteUrl is valid
            if (websiteUrl &&
                websiteUrl !== 'undefined' &&
                websiteUrl !== 'null' &&
                websiteUrl !== null &&
                websiteUrl !== '' &&
                websiteUrl !== 'N/A' &&
                typeof websiteUrl === 'string' &&
                websiteUrl.trim() !== '' &&
                websiteUrl.trim() !== 'undefined') {
                websiteUrlDisplay = websiteUrl;
                websiteUrlLink = '<a href="' + websiteUrl + '" target="_blank">' + websiteUrl + '</a>';
            } else {
                // Always use Persian text for undefined
                websiteUrlDisplay = 'تعریف نشده';
                websiteUrlLink = websiteUrlDisplay;
            }

            const $row = $('<tr>');
            $row.append('<td>' + item.id + '</td>');
            $row.append('<td>' + websiteName + '</td>');
            $row.append('<td class="website-url">' + websiteUrlLink + '</td>');
            $row.append('<td>' + createdDate + '</td>');

            // Action buttons
            const $actionsCell = $('<td>');
            const $actionButtons = $('<div class="action-buttons">');

            // Profile button
            const $profileBtn = $('<button class="action-btn profile-btn" data-item-id="' + item.id + '">' +
                '<span class="dashicons dashicons-admin-users"></span>' +
                SMarkCompetitorAnalysis.strings.profile +
                '</button>');
            $actionButtons.append($profileBtn);

            // Edit button
            const $editBtn = $('<button class="action-btn edit-btn" data-item-id="' + item.id + '">' +
                '<span class="dashicons dashicons-edit"></span>' +
                SMarkCompetitorAnalysis.strings.edit +
                '</button>');
            $actionButtons.append($editBtn);

            // Delete button
            const $deleteBtn = $('<button class="action-btn delete-btn" data-item-id="' + item.id + '">' +
                '<span class="dashicons dashicons-trash"></span>' +
                SMarkCompetitorAnalysis.strings.delete +
                '</button>');
            $actionButtons.append($deleteBtn);

            $actionsCell.append($actionButtons);
            $row.append($actionsCell);

            $tbody.append($row);
        });

        // Fix any remaining undefined text after rendering
        setTimeout(fixUndefinedText, 100);
        setTimeout(fixUndefinedText, 500);
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'success') {
        const $notification = $('<div class="smark-notification ' + type + '" role="status" aria-live="polite"></div>');
        const $body = $('<div class="smark-notification__body"></div>').text(String(message || ''));
        const $close = $('<button type="button" class="smark-notification__close" aria-label="Close notification"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>');
        $close.on('click', function() {
            $notification.fadeOut(300, function() {
                $notification.remove();
            });
        });
        $notification.append($body, $close);
        $('body').append($notification);

        setTimeout(function() {
            $notification.fadeIn(300);
        }, 100);
    }

    /**
     * Initialize
     */
    loadProjects();

    /**
     * Event: Project selection changed
     */
    $('#project_select').on('change', function() {
        const projectId = $(this).val();
        const projectName = $(this).find('option:selected').data('project-name') || $(this).find('option:selected').text();

        if (projectId) {
            selectedProject = {
                id: projectId,
                name: projectName
            };

            // Update selected project display
            $('#selected_project_display .project-name').text(projectName);
            $('#selected_project_display').fadeIn(300);

            // Load project items
            loadProjectItems(projectId, projectName);
        } else {
            selectedProject = null;
            $('#selected_project_display').fadeOut(300);
            $('#data_table_card').fadeOut(300, function() {
                $('#empty_state').fadeIn(300);
            });
        }
    });

    /**
     * Event: Show new project form
     */
    $('#show_new_project_form').on('click', function() {
        $('#new_project_form').slideDown(300);
        $('#new_project_name').focus();
    });

    /**
     * Event: Cancel new project
     */
    $('#cancel_project_btn').on('click', function() {
        $('#new_project_form').slideUp(300);
        $('#new_project_name').val('');
    });

    /**
     * Event: Create new project
     */
    $('#create_project_btn').on('click', function() {
        const projectName = $('#new_project_name').val().trim();

        if (!projectName) {
            showNotification(SMarkCompetitorAnalysis.strings.project_name + ' is required', 'error');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Creating...');

        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_create_project',
                nonce: SMarkCompetitorAnalysis.nonce,
                project_name: projectName
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');

                    // Hide form and reset
                    $('#new_project_form').slideUp(300);
                    $('#new_project_name').val('');

                    // Reload projects
                    loadProjects();

                    // Auto-select the new project
                    setTimeout(function() {
                        $('#project_select').val(projectName).trigger('change');
                    }, 500);
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error creating project', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    /**
     * Event: Change project
     */
    $(document).on('click', '.change-project-btn', function() {
        $('#selected_project_display').fadeOut(300);
        $('#data_table_card').fadeOut(300, function() {
            $('#empty_state').fadeIn(300);
        });
        $('#project_select').val('').focus();
        selectedProject = null;
    });

    /**
     * Event: Add new item
     */
    $('#add_new_item_btn').on('click', function() {
        if (!selectedProject) {
            showNotification('Please select a project first', 'error');
            return;
        }

        isEditMode = false;
        currentItemId = null;

        // Reset form
        $('#item_id').val('');
        $('#item_website_url').val('');
        $('#item_website_name').val('');
        $('#item_notes').val('');

        // Update modal title
        $('#modal_title').text(SMarkCompetitorAnalysis.strings.addNewItem);
        $('#save_btn_text').text(SMarkCompetitorAnalysis.strings.saveItem);

        // Show modal
        $('#add_item_modal').fadeIn(300);
    });

    /**
     * Event: Close modal
     */
    $('#close_modal, #cancel_item_btn, .modal-overlay').on('click', function(e) {
        if (e.target === this) {
            $('#add_item_modal').fadeOut(300);
        }
    });

    /**
     * Event: Save item
     */
    $('#save_item_btn').on('click', function() {
        const websiteUrl = $('#item_website_url').val().trim();
        const websiteName = $('#item_website_name').val().trim();
        const notes = $('#item_notes').val().trim();

        if (!websiteUrl) {
            showNotification('Website URL is required', 'error');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="loading-spinner"></span> ' + SMarkCompetitorAnalysis.strings.saving);

        const ajaxData = {
            nonce: SMarkCompetitorAnalysis.nonce,
            project_name: selectedProject.name,
            website_url: websiteUrl,
            website_name: websiteName,
            notes: notes
        };

        if (isEditMode && currentItemId) {
            ajaxData.action = 'SMARK_competitor_update_item';
            ajaxData.item_id = currentItemId;
        } else {
            ajaxData.action = 'SMARK_competitor_add_item';
        }

        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    $('#add_item_modal').fadeOut(300);
                    loadProjectItems(selectedProject.id, selectedProject.name);
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error saving competitor', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    /**
     * Event: Edit item
     */
    $(document).on('click', '.edit-btn', function() {
        const itemId = $(this).data('item-id');
        currentItemId = itemId;
        isEditMode = true;

        // Get item details
        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_get_item',
                nonce: SMarkCompetitorAnalysis.nonce,
                item_id: itemId
            },
            success: function(response) {
                if (response.success) {
                    const item = response.data.item;

                    // Fill form
                    $('#item_id').val(item.id);
                    $('#item_website_url').val(item.website_url);
                    $('#item_website_name').val(item.website_name || '');
                    $('#item_notes').val(item.notes || '');

                    // Update modal title
                    $('#modal_title').text(SMarkCompetitorAnalysis.strings.editItem);
                    $('#save_btn_text').text(SMarkCompetitorAnalysis.strings.updateItem);

                    // Show modal
                    $('#add_item_modal').fadeIn(300);
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error loading item', 'error');
            }
        });
    });

    /**
     * Event: Delete item
     */
    $(document).on('click', '.delete-btn', function() {
        const itemId = $(this).data('item-id');

        const confirmMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
            ? 'آیا از حذف این آیتم اطمینان دارید؟'
            : 'Are you sure you want to delete this item?';

        if (!confirm(confirmMsg)) {
            return;
        }

        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_delete_item',
                nonce: SMarkCompetitorAnalysis.nonce,
                item_id: itemId
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    loadProjectItems(selectedProject.id, selectedProject.name);
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error deleting competitor', 'error');
            }
        });
    });

    /**
     * Event: Show competitor profile
     */
    $(document).on('click', '.profile-btn', function() {
        const itemId = $(this).data('item-id');
        currentItemId = itemId;

        // Show profile modal
        $('#fetch_results').hide();
        $('#fetch_results_body').empty();
        $('#saved_pages_results').hide();
        $('#saved_pages_body').empty();
        $('#no_saved_pages').hide();
        $('#archived_pages_results').hide();
        $('#archived_pages_body').empty();
        $('#no_archived_pages').hide();

        // Reset to new pages tab
        $('.tab-btn').removeClass('active');
        $('.tab-content').removeClass('active');
        $('.tab-btn[data-tab="new-pages"]').addClass('active');
        $('#new-pages-tab').addClass('active');

        $('#competitor_profile_modal').fadeIn(300);
    });

    /**
     * Event: Close profile modal
     */
    $('#close_profile_modal, #close_profile_btn').on('click', function() {
        $('#competitor_profile_modal').fadeOut(300);
        $('#save_pages_btn').hide();
    });

    /**
     * Event: Save fetched pages
     */
    $('#save_pages_btn').on('click', function() {
        if (!currentItemId) {
            const noItemMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
                ? 'هیچ آیتمی انتخاب نشده است'
                : 'No item selected';
            showNotification(noItemMsg, 'error');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.html();

        // Show loading state
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + SMarkCompetitorAnalysis.strings.fetching);

        // Get all fetched pages from the table
        const pages = [];
        $('#fetch_results_body tr').each(function() {
            const $row = $(this);
            const $urlLink = $row.find('.page-url a');
            const url = $urlLink.attr('href') || $urlLink.text();
            const title = $row.find('.page-title').text();
            const $dateCell = $row.find('.page-date');
            const date = $dateCell.data('published-date') || $dateCell.text();
            const type = $row.find('.page-type').text().toLowerCase();

            if (url && title) {
                pages.push({
                    url: url,
                    title: title,
                    date: date,
                    type: type
                });
            }
        });

        if (pages.length === 0) {
            const noPagesMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
                ? 'صفحه‌ای برای ذخیره وجود ندارد'
                : 'No pages to save';
            showNotification(noPagesMsg, 'error');
            $btn.prop('disabled', false).html(originalText);
            return;
        }

        // Save pages to database
        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_save_pages',
                nonce: SMarkCompetitorAnalysis.nonce,
                item_id: currentItemId,
                pages: pages
            },
            success: function(response) {
                if (response.success) {
                    const savedCount = response.data.saved_count || 0;
                    if (savedCount > 0) {
                    showNotification(response.data.message, 'success');
                    $('#save_pages_btn').hide();
                    } else {
                        // All pages already saved - show info message
                        showNotification(response.data.message, 'info');
                    }
                } else {
                    showNotification(response.data.message || 'Error saving pages', 'error');
                }
            },
            error: function() {
                const errorMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
                    ? 'خطا در ذخیره صفحات'
                    : 'Error saving pages';
                showNotification(errorMsg, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    /**
     * Event: Start fetch
     */
    $('#start_fetch_btn').on('click', function() {
        const timeRange = $('#time_range_select').val();

        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="loading-spinner"></span> ' + SMarkCompetitorAnalysis.strings.fetching);

        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_fetch_pages',
                nonce: SMarkCompetitorAnalysis.nonce,
                item_id: currentItemId,
                time_range: timeRange
            },
            success: function(response) {
                if (response.success) {
                    const pages = response.data.pages;
                    const hasPages = response.data.has_pages;

                    if (hasPages && pages && pages.length > 0) {
                        // Show success notification only when pages are found
                        showNotification(response.data.message, 'success');
                        renderFetchResults(pages);
                        $('#fetch_results').fadeIn(300);
                    } else {
                        // Show info notification when no pages are found
                        showNotification(response.data.message, 'info');
                        $('#fetch_results').hide();
                        $('#save_pages_btn').hide();
                    }
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error fetching pages', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    /**
     * Render fetch results
     */
    function renderFetchResults(pages) {
        const $tbody = $('#fetch_results_body');
        $tbody.empty();

        pages.forEach(function(page) {
            const $row = $('<tr>');

            $row.append('<td class="page-title">' + page.title + '</td>');

            const typeClass = (page.type === 'post') ? 'type-post' : 'type-page';
            const typeText = (page.type === 'post') ?
                (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'پست' : 'Post') :
                (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'صفحه' : 'Page');
            $row.append('<td><span class="page-type ' + typeClass + '">' + typeText + '</span></td>');

            const publishedDate = page.published_date
                ? new Date(page.published_date).toLocaleDateString()
                : (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'نامشخص' : 'N/A');
            $row.append('<td class="page-date" data-published-date="' + (page.published_date || '') + '">' + publishedDate + '</td>');

            $row.append('<td class="page-url"><a href="' + page.url + '" target="_blank">' + page.url + '</a></td>');

            $tbody.append($row);
        });

        // Always show save button when pages are displayed
        // The backend will handle checking for duplicates during save
        $('#save_pages_btn').show();
    }

    /**
     * Event: Tab switching
     */
    $(document).on('click', '.tab-btn', function() {
        const tabName = $(this).data('tab');

        // Remove active class from all tabs and contents
        $('.tab-btn').removeClass('active');
        $('.tab-content').removeClass('active');

        // Add active class to clicked tab and corresponding content
        $(this).addClass('active');
        $('#' + tabName + '-tab').addClass('active');

        // If switching to saved pages tab, load saved pages
        if (tabName === 'saved-pages') {
            loadSavedPages();
        }
        // If switching to archived pages tab, load archived pages
        else if (tabName === 'archived-pages') {
            loadArchivedPages();
        }
    });

    /**
     * Event: Load saved pages (removed redundant button)
     */

    /**
     * Load saved pages function
     */
    function loadSavedPages() {
        if (!currentItemId) {
            const noItemMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
                ? 'هیچ آیتمی انتخاب نشده است'
                : 'No item selected';
            showNotification(noItemMsg, 'error');
            return;
        }

        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_get_saved_pages',
                nonce: SMarkCompetitorAnalysis.nonce,
                item_id: currentItemId
            },
            success: function(response) {
                if (response.success) {
                    const pages = response.data.pages;
                    const count = response.data.count;

                    if (count > 0) {
                        renderSavedPages(pages);
                        $('#saved_pages_results').fadeIn(300);
                        $('#no_saved_pages').hide();
                    } else {
                        $('#saved_pages_results').hide();
                        $('#no_saved_pages').fadeIn(300);
                    }
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                const errorMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
                    ? 'خطا در بارگذاری صفحات ذخیره شده'
                    : 'Error loading saved pages';
                showNotification(errorMsg, 'error');
            }
        });
    }

    /**
     * Render saved pages
     */
    function renderSavedPages(pages) {
        const $tbody = $('#saved_pages_body');
        $tbody.empty();

        pages.forEach(function(page) {
            const $row = $('<tr>');

            // Add reviewed class if page is reviewed
            if (page.is_reviewed == 1) {
                $row.addClass('reviewed-page');
            }

            $row.append('<td class="page-title">' + page.page_title + '</td>');

            const typeClass = (page.page_type === 'post') ? 'type-post' : 'type-page';
            const typeText = (page.page_type === 'post') ?
                (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'پست' : 'Post') :
                (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'صفحه' : 'Page');
            $row.append('<td><span class="page-type ' + typeClass + '">' + typeText + '</span></td>');

            const publishedDate = page.published_date
                ? new Date(page.published_date).toLocaleDateString()
                : (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'نامشخص' : 'N/A');
            $row.append('<td class="page-date">' + publishedDate + '</td>');

            $row.append('<td class="page-url"><a href="' + page.page_url + '" target="_blank">' + page.page_url + '</a></td>');

            const discoveredDate = page.discovered_at
                ? new Date(page.discovered_at).toLocaleDateString()
                : (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'نامشخص' : 'N/A');
            $row.append('<td class="discovered-date">' + discoveredDate + '</td>');

            // Operations column
            const $operationsCell = $('<td class="operations-cell">');
            const $operationsDiv = $('<div class="operations-buttons">');

            // Mark as reviewed button
            const reviewedText = SMarkCompetitorAnalysis.currentLang === 'fa' ? 'بررسی شده' : 'Mark as Reviewed';
            const $reviewedBtn = $('<button class="action-btn reviewed-btn" data-page-id="' + page.id + '">' +
                '<span class="dashicons dashicons-yes"></span>' +
                reviewedText +
                '</button>');

            // Send to social button
            const sendText = SMarkCompetitorAnalysis.currentLang === 'fa' ? 'ارسال' : 'Send to Social';
            const $sendBtn = $('<button class="action-btn send-btn" data-page-id="' + page.id + '">' +
                '<span class="dashicons dashicons-share"></span>' +
                sendText +
                '</button>');

            // Disable reviewed button if already reviewed
            if (page.is_reviewed == 1) {
                $reviewedBtn.addClass('disabled').prop('disabled', true);
            }

            $operationsDiv.append($reviewedBtn);
            $operationsDiv.append($sendBtn);
            $operationsCell.append($operationsDiv);
            $row.append($operationsCell);

            $tbody.append($row);
        });
    }

    /**
     * Load archived pages function
     */
    function loadArchivedPages() {
        if (!currentItemId) {
            const noItemMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
                ? 'هیچ آیتمی انتخاب نشده است'
                : 'No item selected';
            showNotification(noItemMsg, 'error');
            return;
        }

        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_get_archived_pages',
                nonce: SMarkCompetitorAnalysis.nonce,
                item_id: currentItemId
            },
            success: function(response) {
                if (response.success) {
                    const pages = response.data.pages;
                    const count = response.data.count;

                    if (count > 0) {
                        renderArchivedPages(pages);
                        $('#archived_pages_results').fadeIn(300);
                        $('#no_archived_pages').hide();
                    } else {
                        $('#archived_pages_results').hide();
                        $('#no_archived_pages').fadeIn(300);
                    }
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                const errorMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
                    ? 'خطا در بارگذاری صفحات آرشیو شده'
                    : 'Error loading archived pages';
                showNotification(errorMsg, 'error');
            }
        });
    }

    /**
     * Render archived pages
     */
    function renderArchivedPages(pages) {
        const $tbody = $('#archived_pages_body');
        $tbody.empty();

        pages.forEach(function(page) {
            const $row = $('<tr>');

            // Archived pages are always reviewed
            $row.addClass('reviewed-page');

            $row.append('<td class="page-title">' + page.page_title + '</td>');

            const typeClass = (page.page_type === 'post') ? 'type-post' : 'type-page';
            const typeText = (page.page_type === 'post') ?
                (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'پست' : 'Post') :
                (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'صفحه' : 'Page');
            $row.append('<td><span class="page-type ' + typeClass + '">' + typeText + '</span></td>');

            const publishedDate = page.published_date
                ? new Date(page.published_date).toLocaleDateString()
                : (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'نامشخص' : 'N/A');
            $row.append('<td class="page-date">' + publishedDate + '</td>');

            $row.append('<td class="page-url"><a href="' + page.page_url + '" target="_blank">' + page.page_url + '</a></td>');

            const discoveredDate = page.discovered_at
                ? new Date(page.discovered_at).toLocaleDateString()
                : (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'نامشخص' : 'N/A');
            $row.append('<td class="discovered-date">' + discoveredDate + '</td>');

            // Operations column
            const $operationsCell = $('<td class="operations-cell">');
            const $operationsDiv = $('<div class="operations-buttons">');

            // Send to social button (only show this in archived)
            const sendText = SMarkCompetitorAnalysis.currentLang === 'fa' ? 'ارسال' : 'Send to Social';
            const $sendBtn = $('<button class="action-btn send-btn" data-page-id="' + page.id + '">' +
                '<span class="dashicons dashicons-share"></span>' +
                sendText +
                '</button>');

            $operationsDiv.append($sendBtn);
            $operationsCell.append($operationsDiv);
            $row.append($operationsCell);

            $tbody.append($row);
        });
    }

    /**
     * Event: Mark page as reviewed
     */
    $(document).on('click', '.reviewed-btn', function() {
        const pageId = $(this).data('page-id');
        const $btn = $(this);
        const $row = $btn.closest('tr');

        if ($btn.hasClass('disabled')) {
            return;
        }

        const processingText = SMarkCompetitorAnalysis.strings.processing || (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'در حال پردازش...' : 'Processing...');
        $btn.prop('disabled', true).html('<span class="loading-spinner"></span> ' + processingText);

        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_mark_reviewed',
                nonce: SMarkCompetitorAnalysis.nonce,
                page_id: pageId
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');

                    // Remove the row from saved pages with animation
                    $row.fadeOut(300, function() {
                        $(this).remove();

                        // Check if there are any rows left
                        const remainingRows = $('#saved_pages_body tr').length;
                        if (remainingRows === 0) {
                            $('#saved_pages_results').hide();
                            $('#no_saved_pages').fadeIn(300);
                        }
                    });
                } else {
                    showNotification(response.data.message, 'error');
                    const reviewedText = SMarkCompetitorAnalysis.currentLang === 'fa' ? 'بررسی شده' : 'Mark as Reviewed';
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> ' + reviewedText);
                }
            },
            error: function() {
                const errorMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
                    ? 'خطا در علامت‌گذاری صفحه'
                    : 'Error marking page as reviewed';
                showNotification(errorMsg, 'error');
                const reviewedText = SMarkCompetitorAnalysis.currentLang === 'fa' ? 'بررسی شده' : 'Mark as Reviewed';
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> ' + reviewedText);
            }
        });
    });

    /**
     * Event: Send page to social media
     */
    $(document).on('click', '.send-btn', function() {
        const pageId = $(this).data('page-id');
        const $btn = $(this);

        // Get current project name
        const projectName = $('.current-project-name').text();

        if (!projectName || projectName.trim() === '') {
            const noProjectMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
                ? 'لطفاً ابتدا یک پروژه انتخاب کنید'
                : 'Please select a project first';
            showNotification(noProjectMsg, 'error');
            return;
        }

        const sendingText = SMarkCompetitorAnalysis.strings.sending || (SMarkCompetitorAnalysis.currentLang === 'fa' ? 'در حال ارسال...' : 'Sending...');
        $btn.prop('disabled', true).html('<span class="loading-spinner"></span> ' + sendingText);

        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_competitor_send_to_social',
                nonce: SMarkCompetitorAnalysis.nonce,
                page_id: pageId,
                project_name: projectName
            },
            success: function(response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification(response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                const errorMsg = SMarkCompetitorAnalysis.currentLang === 'fa'
                    ? 'خطا در ارسال صفحه به سوشال مدیا'
                    : 'Error sending page to social media';
                showNotification(errorMsg, 'error');
            },
            complete: function() {
                const sendText = SMarkCompetitorAnalysis.currentLang === 'fa' ? 'ارسال' : 'Send to Social';
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-share"></span> ' + sendText);
            }
        });
    });

    /**
     * Event: Language change
     */
    $('#SMARK_language_select').on('change', function() {
        const language = $(this).val();

        $.ajax({
            url: SMarkCompetitorAnalysis.ajaxUrl,
            type: 'POST',
            data: {
                action: 'SMARK_save_language',
                nonce: SMarkCompetitorAnalysis.nonce,
                language: language
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to apply new language
                    location.reload();
                }
            },
            error: function(xhr, status, error) {
            }
        });
    });
});
