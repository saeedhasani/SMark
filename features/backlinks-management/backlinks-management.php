<?php
/**
 * Backlinks Management Feature
 */

if (!defined('WPINC')) {
    die;
}

// This feature embeds the Backlinks Management Tool inside SMark (no separate WP menu page).
if (!defined('SMARK_BACKLINKS_EMBEDDED')) {
    define('SMARK_BACKLINKS_EMBEDDED', true);
}
if (!function_exists('bmt_create_tables')) {
    /*
     * Backlinks Management Tool (legacy)
     */
    if (!defined('ABSPATH')) {
        exit;
    }

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy feature uses `bmt_` prefix; renaming would be breaking.
    if (!defined('SMARK_CENTRAL_BASE_URL_OPTION')) {
        define('SMARK_CENTRAL_BASE_URL_OPTION', 'smark_central_base_url');
    }
    if (!defined('SMARK_DEFAULT_CENTRAL_BASE_URL')) {
        define('SMARK_DEFAULT_CENTRAL_BASE_URL', 'https://saeedhasani.com');
    }
    
    if (!function_exists('bmt_is_embedded_mode')) {
        function bmt_is_embedded_mode() {
            return defined('SMARK_BACKLINKS_EMBEDDED') && SMARK_BACKLINKS_EMBEDDED;
        }
    }
    
    if (!function_exists('bmt_get_page_slug')) {
        function bmt_get_page_slug() {
            $slug = apply_filters('bmt_page_slug', 'backlinks-management-tool');
            $slug = is_string($slug) ? trim($slug) : 'backlinks-management-tool';
            return $slug !== '' ? $slug : 'backlinks-management-tool';
        }
    }
    
    if (!function_exists('bmt_get_ui_lang')) {
        function bmt_get_ui_lang() {
            $lang = get_option('smark_panel_language', '');
            if (!is_string($lang) || $lang === '') {
                $lang = get_option('SMARK_panel_language', 'en');
            }
            $lang = is_string($lang) ? strtolower(trim($lang)) : 'en';
            return ($lang === 'fa') ? 'fa' : 'en';
        }
    }
    
    if (!function_exists('bmt_get_ui_strings')) {
        function bmt_get_ui_strings($lang = null) {
            $lang = is_string($lang) ? strtolower(trim($lang)) : '';
            if ($lang === '') {
                $lang = bmt_get_ui_lang();
            }
    
            $strings = array(
                'en' => array(
                    'add_prospects' => 'Add Prospects',
                    'bulk_actions' => 'Bulk Actions',
                    'delete_selected' => 'Delete Selected',
                    'apply' => 'Apply',
                    'all_outreach' => 'All Outreach',
                    'all_status' => 'All Status',
                    'all_analysis_types' => 'All Analysis Types',
                    'filter' => 'Filter',
                    'search_placeholder' => 'Search...',
                    'search' => 'Search',
                    'source_domain' => 'Source Domain',
                    'url_example' => 'URL Example',
                    'comment' => 'Comment',
                    'outreach_strategy' => 'Outreach Strategy',
                    'status' => 'Status',
                    'target_page' => 'Target Page',
                    'select_target_page' => 'Select Target Page',
                    'all_target_pages' => 'All Target Pages',
                    'target_page_placeholder' => 'Search target page...',
                    'backlinks_display_title' => 'Backlinks Display',
                    'opportunities_title' => 'New Opportunities',
                    'opportunities_search_placeholder' => 'Search page for opportunities...',
                    'opportunities_empty' => 'Select a page to see backlink opportunities.',
                    'opportunities_none' => 'No new opportunities were found for this page.',
                    'opportunities_source_domain' => 'Source Domain',
                    'opportunities_url' => 'URL Example',
                    'no_title' => '(No title)',
                    'no_links' => 'No links found for this project.',
                    'popup_add_title' => 'Add Multiple Backlink Prospects',
                    'popup_add_help' => 'Enter one URL per line:',
                    'popup_add_links' => 'Add Links',
                    'edit_url_title' => 'Edit URL Example',
                    'edit_comment_title' => 'Edit Comment',
                    'edit_comment_placeholder' => 'Edit Comment',
                    'save' => 'Save',
                    'add_outreach_title' => 'Add New Outreach Strategy',
                    'add_outreach_placeholder' => 'Enter new strategy',
                    'outreach_email' => 'Email',
                    'outreach_social' => 'Social Media',
                    'outreach_guest_post' => 'Guest Post',
                    'outreach_other' => 'Other',
                    'outreach_add_new' => '+ Add Outreach Strategy',
                    'status_pending' => 'Pending',
                    'status_in_progress' => 'In Progress',
                    'status_acquired' => 'Acquired',
                    'status_rejected' => 'Rejected',
                    'analysis_comment' => 'Comment',
                    'analysis_profile' => 'Profile',
                    'analysis_error' => 'Error',
                    'analysis_noting' => 'Noting',
                    'analysis_unanalyzed' => 'No Analysis',
                    'invalid_project' => 'Invalid project ID.',
                    'invalid_data' => 'Invalid data received.',
                    'default_comment' => 'Add Your Comment!',
                    'no_ids' => 'No IDs provided',
                    'failed_delete' => 'Failed to delete records',
                ),
                'fa' => array(
                    'add_prospects' => 'افزودن پروسپکت',
                    'bulk_actions' => 'عملیات گروهی',
                    'delete_selected' => 'حذف انتخاب‌شده‌ها',
                    'apply' => 'اعمال',
                    'all_outreach' => 'همه اوتریچ‌ها',
                    'all_status' => 'همه وضعیت‌ها',
                    'filter' => 'فیلتر',
                    'search_placeholder' => 'جستجو...',
                    'search' => 'جستجو',
                    'source_domain' => 'دامنه منبع',
                    'url_example' => 'نمونه URL',
                    'comment' => 'توضیحات',
                    'outreach_strategy' => 'استراتژی اوتریچ',
                    'status' => 'وضعیت',
                    'target_page' => 'صفحه مقصد',
                    'select_target_page' => 'انتخاب صفحه مقصد',
                    'all_target_pages' => 'همه صفحات مقصد',
                    'target_page_placeholder' => 'جست‌وجوی صفحه مقصد...',
                    'backlinks_display_title' => 'نمایش بک‌لینک‌ها',
                    'opportunities_title' => 'فرصت‌های جدید',
                    'opportunities_search_placeholder' => 'جست‌وجوی صفحه برای فرصت‌ها...',
                    'opportunities_empty' => 'برای مشاهده فرصت‌ها یک صفحه انتخاب کنید.',
                    'opportunities_none' => 'برای این صفحه فرصت جدیدی پیدا نشد.',
                    'opportunities_source_domain' => 'دامنه منبع',
                    'opportunities_url' => 'نمونه URL',
                    'no_title' => '(بدون عنوان)',
                    'no_links' => 'برای این پروژه لینکی یافت نشد.',
                    'popup_add_title' => 'افزودن چند پروسپکت بک‌لینک',
                    'popup_add_help' => 'هر URL را در یک خط وارد کنید:',
                    'popup_add_links' => 'افزودن لینک‌ها',
                    'edit_url_title' => 'ویرایش نمونه URL',
                    'edit_comment_title' => 'ویرایش توضیحات',
                    'edit_comment_placeholder' => 'ویرایش توضیحات',
                    'save' => 'ذخیره',
                    'add_outreach_title' => 'افزودن استراتژی اوتریچ جدید',
                    'add_outreach_placeholder' => 'استراتژی جدید را وارد کنید',
                    'outreach_email' => 'ایمیل',
                    'outreach_social' => 'شبکه‌های اجتماعی',
                    'outreach_guest_post' => 'پست مهمان',
                    'outreach_other' => 'سایر',
                    'outreach_add_new' => '+ افزودن استراتژی اوتریچ',
                    'status_pending' => 'در انتظار',
                    'status_in_progress' => 'در حال انجام',
                    'status_acquired' => 'دریافت‌شده',
                    'status_rejected' => 'رد‌شده',
                    'invalid_project' => 'شناسه پروژه نامعتبر است.',
                    'invalid_data' => 'داده نامعتبر دریافت شد.',
                    'default_comment' => 'توضیحات را وارد کنید!',
                    'no_ids' => 'شناسه‌ای ارسال نشده است.',
                    'failed_delete' => 'حذف رکوردها ناموفق بود.',
                ),
            );

            $strings['fa']['all_analysis_types'] = 'همه نوع‌های آنالیز';
            $strings['fa']['analysis_comment'] = 'کامنت';
            $strings['fa']['analysis_profile'] = 'پروفایل';
            $strings['fa']['analysis_error'] = 'خطا';
            $strings['fa']['analysis_noting'] = 'بدون برچسب';
            $strings['fa']['analysis_unanalyzed'] = 'بدون آنالیز';
    
            return isset($strings[$lang]) ? $strings[$lang] : $strings['en'];
        }
    }
    
    if (!function_exists('bmt_admin_page_url')) {
        function bmt_admin_page_url($args = array()) {
            $args = is_array($args) ? $args : array();
            $args['page'] = bmt_get_page_slug();
            return admin_url('admin.php?' . http_build_query($args));
        }
    }
    
    // Activation hooks are handled by the parent SMark plugin (this file is embedded as a feature include).
function bmt_create_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $projects_table = $wpdb->prefix . 'bmt_projects';
    $links_table = $wpdb->prefix . 'bmt_links';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta("CREATE TABLE $projects_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;");

    dbDelta("CREATE TABLE $links_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        source_domain VARCHAR(255) NOT NULL,
        url_example TEXT NOT NULL,
        comment TEXT,
        outreach_strategy VARCHAR(100),
        status VARCHAR(100),
        target_post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        target_post_ids TEXT,
        label_comment TINYINT(1) NOT NULL DEFAULT 0,
        label_profile TINYINT(1) NOT NULL DEFAULT 0,
        label_error TINYINT(1) NOT NULL DEFAULT 0,
        analysis_done TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;");
}

function bmt_create_outreach_table() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'bmt_outreach_strategies';
	$charset_collate = $wpdb->get_charset_collate();

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta("CREATE TABLE $table_name (
		id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		slug VARCHAR(50) NOT NULL UNIQUE,
		label VARCHAR(100) NOT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP
	) $charset_collate;");
}
// (embedded include) activation handled by SMark parent plugin
    
if (!bmt_is_embedded_mode()) {
        add_action('admin_menu', 'bmt_add_admin_menu');
        function bmt_add_admin_menu() {
            add_menu_page(
                'Backlinks Management',
                'Backlinks Tool',
                'smark_access',
                'backlinks-management-tool',
                'bmt_admin_page',
                'dashicons-admin-links',
                26
            );
        }
    
        add_action('admin_enqueue_scripts', function () {
            wp_enqueue_script('jquery');
            $style_path = __DIR__ . '/style.css';
            $style_ver = defined('SMARK_VERSION') ? SMARK_VERSION : (file_exists($style_path) ? (string) filemtime($style_path) : '1');
            wp_enqueue_style('bmt-admin-style', plugin_dir_url(__FILE__) . 'style.css', array(), $style_ver);
        });
    }
    
function bmt_admin_page() {
    global $wpdb;
    $projects_table = $wpdb->prefix . 'bmt_projects';
    
    $projects_table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $projects_table);
    $projects_table_sql = $projects_table_clean !== '' ? '`' . esc_sql($projects_table_clean) . '`' : '';

	// ساخت پروژه جدید
    if (isset($_POST['bmt_create_project']) && !empty($_POST['bmt_new_project'])) {
            check_admin_referer('bmt_create_project', 'bmt_create_project_nonce');
            $new_project_name = sanitize_text_field(wp_unslash($_POST['bmt_new_project']));
            $wpdb->insert($projects_table, array('name' => $new_project_name), array('%s')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Admin action.
            wp_safe_redirect(bmt_admin_page_url(array('created' => $new_project_name)));
            exit;
        }
        
	
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only listing; table identifier is sanitized.
    $projects = $projects_table_sql !== '' ? $wpdb->get_results("SELECT * FROM {$projects_table_sql}") : array();
        $t = bmt_get_ui_strings();
        ?>
        <div class="<?php echo bmt_is_embedded_mode() ? 'bmt-embedded-app' : 'wrap'; ?>">
    		<!-- هدر شامل عنوان، پروژه‌ها و دکمه افزودن لینک-->
        <?php if (!bmt_is_embedded_mode()) : ?>
            <h1 style="margin-bottom:16px; display: flex; justify-content: space-between; align-items: center;">
                <span>Backlinks Management Tool</span>
            <div style="display:flex; gap: 10px; align-items:center; margin: 0;">
                <label for="bmt_project" style="font-weight: 500; font-size:14px; margin-bottom: 0;">Projects</label>
                <select id="bmt_project" name="bmt_project">
                    <option value="">-- Select Project --</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo esc_attr($project->id); ?>"><?php echo esc_html($project->name); ?></option>
                    <?php endforeach; ?>
                    <option value="__add_new__">+ Add Project</option>
                </select>
				<span id="bmt-project-load-status" style="margin-left:10px;"></span>
                <form method="post" id="bmt_new_project_form" style="display:none; gap: 10px;">
                    <?php wp_nonce_field('bmt_create_project', 'bmt_create_project_nonce'); ?>
                    <input type="text" id="bmt_new_project" name="bmt_new_project" placeholder="New Project Name">
                    <input type="submit" name="bmt_create_project" class="button" value="Create Project">
                </form>
                <button id="bmt-add-prospects" class="button button-secondary"><?php echo esc_html($t['add_prospects']); ?></button>
    				<button id="bmt-delete-project" class="button button-danger" style="display: none;">Delete Project</button>
            </div>
        </h1>
            <?php else : ?>
                <?php
                    $default_project_id = (int) apply_filters('bmt_default_project_id', 0);
                    $default_project_name = '';
                    if ($default_project_id > 0) {
                        foreach ($projects as $p) {
                            if (isset($p->id) && (int) $p->id === $default_project_id) {
                                $default_project_name = isset($p->name) ? (string) $p->name : '';
                                break;
                            }
                        }
                    }
                ?>
                <select id="bmt_project" name="bmt_project" style="display:none;">
                    <?php if ($default_project_id > 0) : ?>
                        <option value="<?php echo esc_attr($default_project_id); ?>" selected><?php echo esc_html($default_project_name); ?></option>
                    <?php endif; ?>
                </select>
                <div class="bmt-embedded-toolbar">
                    <div class="bmt-embedded-toolbar-title"><?php echo esc_html($t['backlinks_display_title']); ?></div>
                    <button id="bmt-add-prospects" class="btn btn-secondary"><?php echo esc_html($t['add_prospects']); ?></button>
                    <button id="bmt-analyze-links" class="btn btn-outline" type="button" disabled><?php echo (bmt_get_ui_lang() === 'fa') ? 'آنالیز بک‌لینک‌ها' : 'Analyze Backlinks'; ?></button>
                </div>
            <?php endif; ?>
    
        <!-- تابع پیام ساخت پروژه-->
		<?php
        if (isset($_GET['created'])) {
            $created = sanitize_text_field(wp_unslash($_GET['created']));
            echo '<div class="notice notice-success"><p>Project created: ' . esc_html($created) . '</p></div>';
        }
        ?>

        <!-- نمایش فیلد وارد کردن اسم پروژه جدید-->
		<?php if (!bmt_is_embedded_mode()) : ?>
    		<script>
                document.addEventListener('DOMContentLoaded', function () {
                    const select = document.getElementById('bmt_project');
                    const form = document.getElementById('bmt_new_project_form');
    
                select.addEventListener('change', function () {
                    if (this.value === '__add_new__') {
                        form.style.display = 'flex';
                    } else {
                        form.style.display = 'none';
                    }
                });
                });
            </script>
    		<?php endif; ?>
    
        <!-- پاپ آپ وارد کردن لینک‌ها -->
        <div id="bmt-popup" class="bmt-popup-overlay" style="display:none;">
            <div class="bmt-popup-content">
                <button id="bmt-close-popup" class="bmt-close-button">×</button>
                <h2><?php echo esc_html($t['popup_add_title']); ?></h2>
                    <p><?php echo esc_html($t['popup_add_help']); ?></p>
                    <textarea id="bmt-link-textarea" class="form-control" rows="6"></textarea>
                <p id="bmt-error" style="color:red; display:none;"></p>
                <button id="bmt-submit-links" class="btn btn-primary"><?php echo esc_html($t['popup_add_links']); ?></button>
     				<p id="bmt-success" style="color:green; display:none; margin-top:10px;"></p>
            </div>
        </div>

		<!-- پاپ آپ ویرایش لینک‌ها -->
        <div id="bmt-edit-popup" class="bmt-popup-overlay" style="display:none;">
            <div class="bmt-popup-content">
                <button id="bmt-edit-close" class="bmt-close-button">x</button>
                <h2><?php echo esc_html($t['edit_url_title']); ?></h2>
                    <input type="url" id="bmt-edit-url-input" class="form-control" />
                <input type="hidden" id="bmt-edit-link-id" />
                <button id="bmt-save-url-btn" class="btn btn-primary" style="margin-top:10px;">Save</button>
            </div>
        </div>

		<!-- پاپ آپ ویرایش کامنت‌ها -->
        <div id="bmt-comment-popup" class="bmt-popup-overlay" style="display:none;">
            <div class="bmt-popup-content">
                <button id="bmt-comment-close" class="bmt-close-button">×</button>
                <h2><?php echo esc_html($t['edit_comment_title']); ?></h2>
                    <input type="text" id="bmt-edit-comment-input" class="form-control" placeholder="<?php echo esc_attr($t['edit_comment_placeholder']); ?>" />
                    <input type="hidden" id="bmt-edit-comment-id">
                <button id="bmt-save-comment-btn" class="btn btn-primary" style="margin-top:10px;"><?php echo esc_html($t['save']); ?></button>
                </div>
        </div>

		<!-- پاپ آپ افزودن روش اوتریچ  -->
        <div id="bmt-outreach-popup" class="bmt-popup-overlay" style="display:none;">
            <div class="bmt-popup-content">
                <button class="bmt-close-button" id="bmt-close-outreach-popup">&times;</button>
                <h3><?php echo esc_html($t['add_outreach_title']); ?></h3>
                    <input type="text" id="bmt-new-outreach-input" class="form-control" placeholder="<?php echo esc_attr($t['add_outreach_placeholder']); ?>" style="margin-bottom: 10px;" />
                    <button id="bmt-save-new-outreach" class="btn btn-primary"><?php echo esc_html($t['save']); ?></button>
                </div>
        </div>
        
		<div id="bmt-table-controls" class="bmt-table-controls" style="<?php echo bmt_is_embedded_mode() ? 'display:block;' : 'display:none;'; ?>">
    		    <!-- حذف همگانی -->
            <div class="bmt-bulk-actions bmt-controls-row">
                <select id="bmt-bulk-select" class="form-control">
                    <option value=""><?php echo esc_html($t['bulk_actions']); ?></option>
                        <option value="delete"><?php echo esc_html($t['delete_selected']); ?></option>
                    </select>
                    <button id="bmt-bulk-apply" class="btn btn-secondary"><?php echo esc_html($t['apply']); ?></button>
    			    <span id="bmt-bulk-status"></span>

		        <!-- بخش فیلتر -->
			    <select id="bmt-filter-outreach" class="form-control bmt-filter-select">
				    <option value=""><?php echo esc_html($t['all_outreach']); ?></option>
    				    <option value="email"><?php echo esc_html($t['outreach_email']); ?></option>
    				    <option value="social"><?php echo esc_html($t['outreach_social']); ?></option>
    				    <option value="guest_post"><?php echo esc_html($t['outreach_guest_post']); ?></option>
    				    <?php
				        $outreach_options = bmt_get_outreach_options();
    				        foreach ($outreach_options as $opt) {
    				            echo '<option value="' . esc_attr($opt->slug) . '">' . esc_html($opt->label) . '</option>';
    				        }
    				    ?>
    				    <option value="other"><?php echo esc_html($t['outreach_other']); ?></option>
    					<option value="__add_new__"><?php echo esc_html($t['outreach_add_new']); ?></option>
    			    </select>
    
			    <select id="bmt-filter-status" class="form-control bmt-filter-select">
				    <option value=""><?php echo esc_html($t['all_status']); ?></option>
    				    <option value="pending"><?php echo esc_html($t['status_pending']); ?></option>
    				    <option value="in_progress"><?php echo esc_html($t['status_in_progress']); ?></option>
    				    <option value="acquired"><?php echo esc_html($t['status_acquired']); ?></option>
    				    <option value="rejected"><?php echo esc_html($t['status_rejected']); ?></option>
    			    </select>
			    <select id="bmt-filter-analysis" class="form-control bmt-filter-select">
				    <option value=""><?php echo esc_html($t['all_analysis_types']); ?></option>
                        <option value="comment"><?php echo esc_html($t['analysis_comment']); ?></option>
                        <option value="profile"><?php echo esc_html($t['analysis_profile']); ?></option>
                        <option value="error"><?php echo esc_html($t['analysis_error']); ?></option>
                        <option value="noting"><?php echo esc_html($t['analysis_noting']); ?></option>
                        <option value="unanalyzed"><?php echo esc_html($t['analysis_unanalyzed']); ?></option>
                    </select>
                    <div class="bmt-filter-target-group">
                        <div class="bmt-target-page-filter">
                            <input type="text" id="bmt-filter-target-page-label" class="form-control bmt-target-page-input" list="bmt-target-pages-list" placeholder="<?php echo esc_attr($t['all_target_pages']); ?>" autocomplete="off" />
                            <input type="hidden" id="bmt-filter-target-page" value="0" />
                        </div>
    			        <button id="bmt-filter-apply" class="btn btn-secondary"><?php echo esc_html($t['filter']); ?></button>
    			        <span id="bmt-filter-status-box"></span>
                    </div>

			    <!-- بخش جست‌وجو -->
			    <input type="text" id="bmt-search-input" class="form-control bmt-search-input" placeholder="<?php echo esc_attr($t['search_placeholder']); ?>" />
    			    <button type="button" id="bmt-search-btn" class="btn btn-secondary"><?php echo esc_html($t['search']); ?></button>
                    <select id="bmt-per-page" class="form-control bmt-per-page">
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="500">500</option>
                    </select>
                </div>

            <!-- جدول نمایش داده‌ها -->
                <?php echo bmt_render_target_pages_datalist(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div id="bmt_links_container">
                <table id="bmt-prospect-table" class="widefat fixed striped">
                    <thead>
                        <tr>
					        <th><input type="checkbox" id="bmt-check-all"></th>
                            <th><?php echo esc_html($t['source_domain']); ?></th>
                                <th><?php echo esc_html($t['url_example']); ?></th>
                                <th><?php echo esc_html($t['outreach_strategy']); ?></th>
                                <th><?php echo esc_html($t['status']); ?></th>
                                <th><?php echo esc_html($t['target_page']); ?></th>
                                <th><?php echo esc_html($t['comment']); ?></th>
                            </tr>
                    </thead>
                    <tbody id="bmt-rows">
                            <tr><td colspan="7"><?php echo esc_html($t['no_links']); ?></td></tr>
                        </tbody>
                    </table>
                </div>
     		</div>
        </div>
        <?php
    }

function bmt_render_opportunities_section() {
    $t = bmt_get_ui_strings();
    ob_start();
    ?>
    <div class="bmt-opportunities-section">
        <div class="bmt-opportunities-header">
            <div class="bmt-opportunities-heading">
                <div class="bmt-opportunities-title"><?php echo esc_html($t['opportunities_title']); ?></div>
                <div class="bmt-opportunities-subtitle"><?php echo esc_html($t['opportunities_empty']); ?></div>
            </div>
            <div class="bmt-opportunities-search">
                <input type="text" id="bmt-opportunities-target-label" class="form-control bmt-target-page-input" list="bmt-target-pages-list" placeholder="<?php echo esc_attr($t['opportunities_search_placeholder']); ?>" autocomplete="off" />
                <input type="hidden" id="bmt-opportunities-target-id" value="0" />
            </div>
        </div>
        <div id="bmt-opportunities-results" class="bmt-opportunities-results">
            <div class="bmt-opportunities-empty"><?php echo esc_html($t['opportunities_empty']); ?></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
    
// فراخوانی داده‌ها از تیبل و نمایش برای کاربر
add_action('wp_ajax_bmt_get_project_links', 'bmt_get_project_links');

function bmt_render_links_table_html($filter_outreach = '', $filter_status = '', $filter_target_post_id = 0, $filter_analysis = '', $page = 1, $per_page = 100) {
    global $wpdb;
    $t = bmt_get_ui_strings();

    if (function_exists('bmt_ensure_links_label_columns')) {
        bmt_ensure_links_label_columns();
    }
    if (function_exists('bmt_ensure_links_analysis_columns')) {
        bmt_ensure_links_analysis_columns();
    }
    if (function_exists('bmt_ensure_links_target_column')) {
        bmt_ensure_links_target_column();
    }

    $filter_outreach = is_string($filter_outreach) ? sanitize_text_field($filter_outreach) : '';
    $filter_status = is_string($filter_status) ? sanitize_text_field($filter_status) : '';
    $filter_target_post_id = (int) $filter_target_post_id;
    $filter_analysis = is_string($filter_analysis) ? sanitize_text_field($filter_analysis) : '';
    $page = max(1, (int) $page);
    $per_page = (int) $per_page;
    if (!in_array($per_page, array(100, 200, 500), true)) {
        $per_page = 100;
    }

    $links_table = $wpdb->prefix . 'bmt_links';
    $links_table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $links_table);
    $links_table_sql = $links_table_clean !== '' ? '`' . esc_sql($links_table_clean) . '`' : '';

    $where_clauses = array();
    $query_params = array();

    if ($filter_outreach !== '') {
        $where_clauses[] = 'outreach_strategy = %s';
        $query_params[] = $filter_outreach;
    }
    if ($filter_status !== '') {
        $where_clauses[] = 'status = %s';
        $query_params[] = $filter_status;
    }
    if ($filter_target_post_id > 0) {
        $where_clauses[] = 'FIND_IN_SET(%d, target_post_ids) > 0';
        $query_params[] = $filter_target_post_id;
    }
    if ($filter_analysis !== '') {
        switch ($filter_analysis) {
            case 'comment':
                $where_clauses[] = 'label_comment = %d';
                $query_params[] = 1;
                break;
            case 'profile':
                $where_clauses[] = 'label_profile = %d';
                $query_params[] = 1;
                break;
            case 'error':
                $where_clauses[] = 'label_error = %d';
                $query_params[] = 1;
                break;
            case 'noting':
                $where_clauses[] = 'analysis_done = %d AND label_error = %d AND label_comment = %d AND label_profile = %d';
                array_push($query_params, 1, 0, 0, 0);
                break;
            case 'unanalyzed':
                $where_clauses[] = 'analysis_done = %d';
                $query_params[] = 0;
                break;
        }
    }

    $where_sql = !empty($where_clauses) ? implode(' AND ', $where_clauses) : '1=1';

    if (!empty($query_params)) {
        $total = (int) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is sanitized.
            $wpdb->prepare('SELECT COUNT(*) FROM ' . $links_table_sql . ' WHERE ' . $where_sql, $query_params) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; identifiers cannot be placeholders.
        );
    } else {
        $total = (int) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is sanitized.
            $wpdb->prepare('SELECT COUNT(*) FROM ' . $links_table_sql . ' WHERE 1=%d', 1) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; identifiers cannot be placeholders.
        );
    }

    $total_pages = (int) max(1, (int) ceil($total / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }

    $offset = (int) (($page - 1) * $per_page);

    if (!empty($query_params)) {
        $select_params = $query_params;
        $select_params[] = $per_page;
        $select_params[] = $offset;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is sanitized.
        $results = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $links_table_sql . ' WHERE ' . $where_sql . ' ORDER BY id DESC LIMIT %d OFFSET %d', $select_params)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is sanitized.
        $results = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $links_table_sql . ' ORDER BY id DESC LIMIT %d OFFSET %d', $per_page, $offset)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    }

    ob_start();
    ?>
    <table id="bmt-prospect-table" class="widefat fixed striped">
            <thead>
                <tr>
    			    <th><input type="checkbox" id="bmt-check-all"></th>
                    <th><?php echo esc_html($t['source_domain']); ?></th>
                    <th><?php echo esc_html($t['url_example']); ?></th>
                    <th><?php echo esc_html($t['outreach_strategy']); ?></th>
                    <th><?php echo esc_html($t['status']); ?></th>
                    <th><?php echo esc_html($t['target_page']); ?></th>
                    <th><?php echo esc_html($t['comment']); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($results): ?>
                <?php foreach ($results as $link): ?>
                    <tr>
						<td><input type="checkbox" class="bmt-row-checkbox" data-id="<?php echo (int) $link->id; ?>"></td>
                        <td>
                            <div class="bmt-domain-title"><?php echo esc_html($link->source_domain); ?></div>
                            <?php echo bmt_get_link_labels_html($link); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </td>
                        <td class="bmt-url-cell">
                            <span class="bmt-url-text">
							    <a href="<?php echo esc_url($link->url_example); ?>" target="_blank" rel="noopener noreferrer">
							        <?php echo esc_html($link->url_example); ?>
								</a>
							</span>
                            <button class="bmt-edit-url" data-id="<?php echo (int) $link->id; ?>" data-url="<?php echo esc_attr($link->url_example); ?>">✏️</button>
                        </td>
                        <td>
                                <select name="outreach_strategy[]">
                                    <option value="email" <?php selected($link->outreach_strategy, 'email'); ?>><?php echo esc_html($t['outreach_email']); ?></option>
                                    <option value="social" <?php selected($link->outreach_strategy, 'social'); ?>><?php echo esc_html($t['outreach_social']); ?></option>
                                    <option value="guest_post" <?php selected($link->outreach_strategy, 'guest_post'); ?>><?php echo esc_html($t['outreach_guest_post']); ?></option>
    								<?php
                                    $outreach_options = bmt_get_outreach_options();
                                    foreach ($outreach_options as $opt) {
    	                                echo '<option value="' . esc_attr($opt->slug) . '" ' . selected($link->outreach_strategy, $opt->slug, false) . '>' . esc_html($opt->label) . '</option>';
                                    }
    								?>
                                    <option value="other" <?php selected($link->outreach_strategy, 'other'); ?>><?php echo esc_html($t['outreach_other']); ?></option>
    								<option value="__add_new__"><?php echo esc_html($t['outreach_add_new']); ?></option>
                                </select>
    						</td>
                            <td>
                                <?php
                                $status_value = in_array((string) $link->status, array('pending', 'in_progress', 'acquired', 'rejected'), true) ? (string) $link->status : 'pending';
                                $status_class = 'bmt-status-select bmt-status-' . str_replace('_', '-', $status_value);
                                ?>
                                <select name="status[]" class="<?php echo esc_attr($status_class); ?>">
                                    <option value="pending" <?php selected($link->status, 'pending'); ?>><?php echo esc_html($t['status_pending']); ?></option>
                                    <option value="in_progress" <?php selected($link->status, 'in_progress'); ?>><?php echo esc_html($t['status_in_progress']); ?></option>
                                    <option value="acquired" <?php selected($link->status, 'acquired'); ?>><?php echo esc_html($t['status_acquired']); ?></option>
                                    <option value="rejected" <?php selected($link->status, 'rejected'); ?>><?php echo esc_html($t['status_rejected']); ?></option>
                                </select>
    						</td>
                        <td class="bmt-target-page-cell">
                            <?php echo bmt_render_target_page_select((int) $link->id, bmt_get_target_post_ids_for_link($link)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </td>
                        <td class="bmt-comment-cell">
                            <span class="bmt-comment-text"><?php echo esc_html($link->comment); ?></span>
                            <button class="bmt-edit-comment" data-id="<?php echo (int) $link->id; ?>" data-comment="<?php echo esc_attr($link->comment); ?>">✏️</button>
						</td>
                        </tr>
                <?php endforeach; ?>
            <?php else: ?>
                    <tr><td colspan="7"><?php echo esc_html($t['no_links']); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="bmt-pagination" data-page="<?php echo (int) $page; ?>" data-total-pages="<?php echo (int) $total_pages; ?>">
            <button type="button" class="btn btn-outline bmt-page-prev" <?php disabled($page <= 1); ?>><?php echo (bmt_get_ui_lang() === 'fa') ? 'قبلی' : 'Prev'; ?></button>
            <span class="bmt-page-meta"><?php echo esc_html((string) $page . ' / ' . (string) $total_pages); ?></span>
            <button type="button" class="btn btn-outline bmt-page-next" <?php disabled($page >= $total_pages); ?>><?php echo (bmt_get_ui_lang() === 'fa') ? 'بعدی' : 'Next'; ?></button>
            <span class="bmt-page-total"><?php echo esc_html((bmt_get_ui_lang() === 'fa') ? ('تعداد: ' . $total) : ('Total: ' . $total)); ?></span>
        </div>
        <?php

    return ob_get_clean();
}

function bmt_render_opportunities_html($target_post_id = 0, $limit = 5) {
    global $wpdb;
    $t = bmt_get_ui_strings();
    $target_post_id = absint($target_post_id);
    $limit = max(1, min(20, (int) $limit));
    $candidate_limit = min(100, max($limit * 5, $limit));

    if ($target_post_id <= 0) {
        return '<div class="bmt-opportunities-empty">' . esc_html($t['opportunities_empty']) . '</div>';
    }

    if (function_exists('bmt_ensure_links_target_column')) {
        bmt_ensure_links_target_column();
    }

    $links_table = $wpdb->prefix . 'bmt_links';
    $links_table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $links_table);
    $links_table_sql = $links_table_clean !== '' ? '`' . esc_sql($links_table_clean) . '`' : '';
    if ($links_table_sql === '') {
        return '<div class="bmt-opportunities-empty">' . esc_html($t['opportunities_none']) . '</div>';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is sanitized.
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT id, source_domain, url_example, target_post_ids, label_comment, label_profile, label_error, analysis_done FROM ' . $links_table_sql . ' WHERE (target_post_ids IS NULL OR target_post_ids = %s OR FIND_IN_SET(%d, target_post_ids) = 0) AND COALESCE(label_error, 0) = 0 AND NOT (COALESCE(analysis_done, 0) = 1 AND COALESCE(label_comment, 0) = 0 AND COALESCE(label_profile, 0) = 0) ORDER BY id DESC LIMIT %d',
        '',
        $target_post_id,
        $candidate_limit
    )); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.

    if (is_array($rows) && !empty($rows)) {
        $rows = array_values(array_filter($rows, static function ($row) {
            return !bmt_link_has_excluded_opportunity_label($row);
        }));
        $rows = array_slice($rows, 0, $limit);
    }

    ob_start();
    if (!is_array($rows) || empty($rows)) {
        echo '<div class="bmt-opportunities-empty">' . esc_html($t['opportunities_none']) . '</div>';
        return ob_get_clean();
    }
    ?>
    <div class="bmt-opportunities-list">
        <table class="widefat fixed striped bmt-opportunities-table">
            <thead>
                <tr>
                    <th><?php echo esc_html($t['opportunities_source_domain']); ?></th>
                    <th><?php echo esc_html($t['opportunities_url']); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td>
                            <div class="bmt-opportunity-domain-cell">
                                <div class="bmt-domain-title"><?php echo esc_html(isset($row->source_domain) ? (string) $row->source_domain : ''); ?></div>
                                <?php echo bmt_get_link_labels_display_html($row); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(isset($row->url_example) ? (string) $row->url_example : ''); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html(isset($row->url_example) ? (string) $row->url_example : ''); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php

    return ob_get_clean();
}

function bmt_link_has_excluded_opportunity_label($link) {
    if (!is_object($link)) {
        return false;
    }

    $label_error = isset($link->label_error) ? (int) $link->label_error : 0;
    if ($label_error === 1) {
        return true;
    }

    $analysis_done = isset($link->analysis_done) ? (int) $link->analysis_done : 0;
    $label_comment = isset($link->label_comment) ? (int) $link->label_comment : 0;
    $label_profile = isset($link->label_profile) ? (int) $link->label_profile : 0;

    return $analysis_done === 1 && $label_comment !== 1 && $label_profile !== 1;
}

function bmt_get_project_links() {
        // Backward-compatible nonce: verify when provided, but do not require it (legacy callers may omit).
        if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
        }

     	$filter_outreach = isset($_POST['filter_outreach']) ? sanitize_text_field(wp_unslash($_POST['filter_outreach'])) : '';
     	$filter_status = isset($_POST['filter_status']) ? sanitize_text_field(wp_unslash($_POST['filter_status'])) : '';
        $filter_target_post_id = isset($_POST['filter_target_post_id']) ? absint(wp_unslash($_POST['filter_target_post_id'])) : 0;
        $filter_analysis = isset($_POST['filter_analysis']) ? sanitize_text_field(wp_unslash($_POST['filter_analysis'])) : '';
        $page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 100;

        echo bmt_render_links_table_html($filter_outreach, $filter_status, $filter_target_post_id, $filter_analysis, $page, $per_page); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_die();
}

add_action('wp_ajax_bmt_get_opportunities', 'bmt_get_opportunities');
function bmt_get_opportunities() {
    if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
    }

    $target_post_id = isset($_POST['target_post_id']) ? absint(wp_unslash($_POST['target_post_id'])) : 0;
    echo bmt_render_opportunities_html($target_post_id, 5); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    wp_die();
}

// تابعی برای هندل کردن درخواست AJAX از سمت کاربر لاگین‌شده
add_action('wp_ajax_bmt_save_prospects', 'bmt_save_prospects_callback');

/**
 * Normalize a user-entered URL into an absolute URL (best-effort).
 *
 * Accepts:
 * - https://example.com/path
 * - http://example.com/path
 * - //example.com/path
 * - www.example.com/path
 * - example.com/path
 * - /example.com/path  (common paste mistake)
 *
 * @param mixed $url
 * @return string
 */
function bmt_normalize_url_raw($url) {
    if (!is_string($url)) {
        return '';
    }

    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $url = trim($url, " \t\n\r\0\x0B\"'`");

    // Strip leading slashes if it looks like a domain was pasted with "/" prefix (e.g. "/example.com/path").
    $stripped = ltrim($url, '/');
    if ($stripped !== $url && preg_match('~^[a-z0-9.-]+\\.[a-z]{2,}(/|$)~i', $stripped)) {
        $url = $stripped;
    }

    if (strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    }

    if (preg_match('~^www\\.~i', $url)) {
        $url = 'https://' . $url;
    }

    // If scheme is missing but the value looks like a hostname, default to https://
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) && preg_match('~^[a-z0-9.-]+\\.[a-z]{2,}(/|$)~i', $url)) {
        $url = 'https://' . $url;
    }

    return $url;
}

function bmt_ensure_links_label_columns() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'bmt_links';
    $table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table_name);
    if ($table_clean === '') {
        return;
    }
    $table_sql = '`' . esc_sql($table_clean) . '`';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Schema check; table identifier is sanitized.
    $columns = $wpdb->get_col('SHOW COLUMNS FROM ' . $table_sql, 0); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; identifiers cannot be placeholders.
    if (!is_array($columns) || empty($columns)) {
        return;
    }

    if (!in_array('label_comment', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration; table identifier is sanitized.
        $wpdb->query('ALTER TABLE ' . $table_sql . ' ADD COLUMN label_comment TINYINT(1) NOT NULL DEFAULT 0'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; identifiers cannot be placeholders.
    }
    if (!in_array('label_profile', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration; table identifier is sanitized.
        $wpdb->query('ALTER TABLE ' . $table_sql . ' ADD COLUMN label_profile TINYINT(1) NOT NULL DEFAULT 0'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; identifiers cannot be placeholders.
    }
    if (!in_array('label_error', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration; table identifier is sanitized.
        $wpdb->query('ALTER TABLE ' . $table_sql . ' ADD COLUMN label_error TINYINT(1) NOT NULL DEFAULT 0'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; identifiers cannot be placeholders.
    }
}

function bmt_ensure_links_analysis_columns() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'bmt_links';
    $table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table_name);
    if ($table_clean === '') {
        return;
    }
    $table_sql = '`' . esc_sql($table_clean) . '`';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Schema check; table identifier is sanitized.
    $columns = $wpdb->get_col('SHOW COLUMNS FROM ' . $table_sql, 0); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; identifiers cannot be placeholders.
    if (!is_array($columns) || empty($columns)) {
        return;
    }

    if (!in_array('analysis_done', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration; table identifier is sanitized.
        $wpdb->query('ALTER TABLE ' . $table_sql . ' ADD COLUMN analysis_done TINYINT(1) NOT NULL DEFAULT 0'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; identifiers cannot be placeholders.
    }
}

function bmt_ensure_links_target_column() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'bmt_links';
    $table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table_name);
    if ($table_clean === '') {
        return;
    }

    $table_sql = '`' . esc_sql($table_clean) . '`';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Schema check; table identifier is sanitized.
    $columns = $wpdb->get_col('SHOW COLUMNS FROM ' . $table_sql, 0); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    if (!is_array($columns) || empty($columns)) {
        return;
    }

    if (!in_array('target_post_id', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration; table identifier is sanitized.
        $wpdb->query('ALTER TABLE ' . $table_sql . ' ADD COLUMN target_post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER status'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    }
    if (!in_array('target_post_ids', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema migration; table identifier is sanitized.
        $wpdb->query('ALTER TABLE ' . $table_sql . ' ADD COLUMN target_post_ids TEXT NULL AFTER target_post_id'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    }

    if (in_array('target_post_id', $columns, true) && in_array('target_post_ids', $columns, true)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- One-time migration from legacy single target to multi-target storage.
        $wpdb->query('UPDATE ' . $table_sql . ' SET target_post_ids = CAST(target_post_id AS CHAR) WHERE target_post_id > 0 AND (target_post_ids IS NULL OR target_post_ids = \'\')'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    }
}

function bmt_parse_target_post_ids($raw_ids) {
    if (is_string($raw_ids)) {
        $raw_ids = explode(',', $raw_ids);
    }
    if (!is_array($raw_ids)) {
        return array();
    }

    $ids = array();
    foreach ($raw_ids as $raw_id) {
        $post_id = absint($raw_id);
        if ($post_id <= 0) {
            continue;
        }
        $post = get_post($post_id);
        $permalink = $post ? get_permalink($post) : '';
        if (!$post || !is_string($permalink) || trim($permalink) === '') {
            continue;
        }
        $ids[] = $post_id;
    }

    $ids = array_values(array_unique($ids));
    return $ids;
}

function bmt_get_target_post_ids_for_link($link) {
    $ids = array();
    if (is_object($link) && isset($link->target_post_ids) && is_string($link->target_post_ids) && trim($link->target_post_ids) !== '') {
        $ids = bmt_parse_target_post_ids((string) $link->target_post_ids);
    }

    if (empty($ids) && is_object($link) && isset($link->target_post_id) && (int) $link->target_post_id > 0) {
        $ids = bmt_parse_target_post_ids(array((int) $link->target_post_id));
    }

    return $ids;
}

function bmt_get_target_page_options() {
    static $options = null;
    if (is_array($options)) {
        return $options;
    }

    $options = array();
    $post_types = get_post_types(array('public' => true), 'objects');
    if (!is_array($post_types) || empty($post_types)) {
        return $options;
    }

    $allowed_types = array();
    foreach ($post_types as $post_type => $post_type_object) {
        if (!is_object($post_type_object)) {
            continue;
        }
        if (in_array($post_type, array('attachment'), true)) {
            continue;
        }
        $allowed_types[] = $post_type;
    }

    if (empty($allowed_types)) {
        return $options;
    }

    $posts = get_posts(array(
        'post_type' => $allowed_types,
        'post_status' => array('publish', 'private', 'future'),
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => false,
    ));

    if (!is_array($posts) || empty($posts)) {
        return $options;
    }

    $t = bmt_get_ui_strings();

    foreach ($posts as $post) {
        if (!($post instanceof WP_Post)) {
            continue;
        }

        $permalink = get_permalink($post);
        if (!is_string($permalink) || trim($permalink) === '') {
            continue;
        }

        $type_object = get_post_type_object($post->post_type);
        $type_label = ($type_object && isset($type_object->labels->singular_name)) ? (string) $type_object->labels->singular_name : (string) $post->post_type;
        $title = get_the_title($post);
        $title = is_string($title) && trim($title) !== '' ? trim($title) : $t['no_title'];

        $options[] = array(
            'id' => (int) $post->ID,
            'title' => $title,
            'url' => $permalink,
            'label' => $title . ' | ' . $type_label,
        );
    }

    return $options;
}

function bmt_get_target_page_label_by_id($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    foreach (bmt_get_target_page_options() as $option) {
        if ((int) $option['id'] === $post_id) {
            return isset($option['label']) ? (string) $option['label'] : '';
        }
    }

    return '';
}

function bmt_get_target_page_labels_by_ids($post_ids) {
    $post_ids = bmt_parse_target_post_ids($post_ids);
    $labels = array();

    foreach ($post_ids as $post_id) {
        $label = bmt_get_target_page_label_by_id($post_id);
        if ($label !== '') {
            $labels[] = array(
                'id' => $post_id,
                'label' => $label,
            );
        }
    }

    return $labels;
}

function bmt_render_target_pages_datalist() {
    $options = bmt_get_target_page_options();

    ob_start();
    ?>
    <datalist id="bmt-target-pages-list">
        <?php foreach ($options as $option) : ?>
            <option value="<?php echo esc_attr($option['label']); ?>" data-id="<?php echo (int) $option['id']; ?>" data-url="<?php echo esc_attr($option['url']); ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <?php

    return ob_get_clean();
}

function bmt_render_target_page_select($link_id, $selected_post_ids) {
    $link_id = (int) $link_id;
    $t = bmt_get_ui_strings();
    $selected_post_ids = bmt_parse_target_post_ids($selected_post_ids);
    $selected_labels = bmt_get_target_page_labels_by_ids($selected_post_ids);

    ob_start();
    ?>
    <div class="bmt-target-page-picker" data-link-id="<?php echo (int) $link_id; ?>">
        <div class="bmt-target-page-tags">
            <?php foreach ($selected_labels as $selected_item) : ?>
                <button type="button" class="bmt-target-tag" data-id="<?php echo (int) $selected_item['id']; ?>">
                    <span class="bmt-target-tag-label"><?php echo esc_html($selected_item['label']); ?></span>
                    <span class="bmt-target-tag-remove">×</span>
                </button>
            <?php endforeach; ?>
        </div>
        <input
            type="text"
            name="target_page_label[]"
            class="form-control bmt-target-page-input"
            list="bmt-target-pages-list"
            placeholder="<?php echo esc_attr($t['target_page_placeholder']); ?>"
            value=""
            autocomplete="off"
        />
        <input type="hidden" name="target_post_ids[]" class="bmt-target-page-ids" value="<?php echo esc_attr(implode(',', $selected_post_ids)); ?>" />
    </div>
    <?php

    return ob_get_clean();
}

function bmt_migrate_drop_project_id_column() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'bmt_links';
    $table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table_name);
    if ($table_clean === '') {
        return;
    }
    $table_sql = '`' . $table_clean . '`';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table identifier is sanitized.
    $exists = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', 'project_id')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; identifiers cannot be placeholders.
    if (!$exists) {
        return;
    }

    $logger = class_exists('SMarkLogger', false) ? 'SMarkLogger' : null;
    $log = function ($level, $message, $context = array()) use ($logger) {
        if ($logger && is_callable(array($logger, strtolower($level)))) {
            $method = strtolower($level);
            $logger::$method($message, $context);
            return;
        }
        $debug_enabled = defined('WP_DEBUG') && (bool) WP_DEBUG;
        $debug_enabled = (bool) apply_filters('smark_backlinks_migration_debug_log_enabled', $debug_enabled);

        if ($debug_enabled && function_exists('error_log')) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Fallback logger for troubleshooting.
            error_log('[SMark Backlinks] ' . $message . ' ' . wp_json_encode($context));
        }
    };

    // Drop FK constraints referencing project_id (information_schema is more reliable than parsing SHOW CREATE).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
    $fks = $wpdb->get_col($wpdb->prepare(
        "SELECT CONSTRAINT_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = %s
           AND COLUMN_NAME = %s
           AND REFERENCED_TABLE_NAME IS NOT NULL",
        $table_clean,
        'project_id'
    ));

    if (is_array($fks) && !empty($fks)) {
        foreach ($fks as $fk) {
            $fk = preg_replace('/[^A-Za-z0-9_]/', '', (string) $fk);
            if ($fk === '') {
                continue;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Schema migration; identifiers are sanitized (cannot be placeholders).
            $dropped = $wpdb->query('ALTER TABLE ' . $table_sql . ' DROP FOREIGN KEY `' . $fk . '`'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers are sanitized; identifiers cannot be placeholders.
            if ($dropped === false) {
                $log('warning', 'Failed dropping foreign key for project_id.', array('fk' => $fk, 'error' => (string) $wpdb->last_error));
            }
        }
    }

    // Drop index on project_id (if any).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
    $indexes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table identifier is sanitized (cannot be placeholder).
        $wpdb->prepare('SHOW INDEX FROM ' . $table_sql . ' WHERE Column_name = %s', 'project_id'), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
        ARRAY_A
    );
    if (is_array($indexes) && !empty($indexes)) {
        foreach ($indexes as $idx) {
            $key_name = isset($idx['Key_name']) ? (string) $idx['Key_name'] : '';
            if ($key_name !== '' && $key_name !== 'PRIMARY') {
                $key_clean = preg_replace('/[^A-Za-z0-9_]/', '', $key_name);
                if ($key_clean === '') {
                    continue;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Schema migration; identifiers are sanitized (cannot be placeholders).
                $dropped = $wpdb->query('ALTER TABLE ' . $table_sql . ' DROP INDEX `' . $key_clean . '`'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifiers are sanitized; identifiers cannot be placeholders.
                if ($dropped === false) {
                    $log('warning', 'Failed dropping index for project_id.', array('index' => $key_clean, 'error' => (string) $wpdb->last_error));
                }
            }
        }
    }

    // Finally drop the column.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Schema migration; table identifier is sanitized (cannot be placeholder).
    $dropped_col = $wpdb->query('ALTER TABLE ' . $table_sql . ' DROP COLUMN project_id'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; identifiers cannot be placeholders.
    if ($dropped_col === false) {
        update_option('smark_bmt_drop_project_id_error', (string) $wpdb->last_error, false);
        $log('error', 'Failed dropping project_id column from bmt_links.', array('table' => $table_clean, 'error' => (string) $wpdb->last_error));
    } else {
        delete_option('smark_bmt_drop_project_id_error');
        $log('info', 'Dropped project_id column from bmt_links.', array('table' => $table_clean));
    }
}

function bmt_get_link_labels_html($link) {
    if (!is_object($link) || empty($link->id)) {
        return '';
    }

    $id = (int) $link->id;
    $labels = array();
    $analysis_done = isset($link->analysis_done) ? (int) $link->analysis_done : 0;
    $label_error = isset($link->label_error) ? (int) $link->label_error : 0;

    if (isset($link->label_comment) && (int) $link->label_comment === 1) {
        $labels[] = array('key' => 'comment', 'text' => 'comment', 'class' => 'bmt-label-comment');
    }
    if (isset($link->label_profile) && (int) $link->label_profile === 1) {
        $labels[] = array('key' => 'profile', 'text' => 'Profile', 'class' => 'bmt-label-profile');
    }

    if ($label_error !== 1 && empty($labels) && $analysis_done !== 1) {
        return '';
    }

    $out = '<div class="bmt-domain-labels">';
    foreach ($labels as $label) {
        $out .= sprintf(
            '<button type="button" class="bmt-label %1$s" data-id="%2$d" data-label="%3$s"><span class="bmt-label-text">%4$s</span><span class="bmt-label-close" aria-hidden="true">×</span></button>',
            esc_attr($label['class']),
            (int) $id,
            esc_attr($label['key']),
            esc_html($label['text'])
        );
    }

    if ($label_error === 1) {
        $out .= '<span class="bmt-label bmt-label-error"><span class="bmt-label-text">Error</span></span>';
    } elseif (empty($labels) && $analysis_done === 1) {
        $out .= '<span class="bmt-label bmt-label-noting"><span class="bmt-label-text">Noting</span></span>';
    }
    $out .= '</div>';

    return $out;
}

function bmt_get_link_labels_display_html($link) {
    if (!is_object($link) || empty($link->id)) {
        return '';
    }

    $analysis_done = isset($link->analysis_done) ? (int) $link->analysis_done : 0;
    $label_error = isset($link->label_error) ? (int) $link->label_error : 0;
    $labels = array();

    if (isset($link->label_comment) && (int) $link->label_comment === 1) {
        $labels[] = array('text' => 'comment', 'class' => 'bmt-label-comment');
    }
    if (isset($link->label_profile) && (int) $link->label_profile === 1) {
        $labels[] = array('text' => 'Profile', 'class' => 'bmt-label-profile');
    }

    if ($label_error !== 1 && empty($labels) && $analysis_done !== 1) {
        return '';
    }

    $out = '<div class="bmt-domain-labels bmt-domain-labels-static">';
    foreach ($labels as $label) {
        $out .= sprintf(
            '<span class="bmt-label %1$s"><span class="bmt-label-text">%2$s</span></span>',
            esc_attr($label['class']),
            esc_html($label['text'])
        );
    }

    if ($label_error === 1) {
        $out .= '<span class="bmt-label bmt-label-error"><span class="bmt-label-text">Error</span></span>';
    } elseif (empty($labels) && $analysis_done === 1) {
        $out .= '<span class="bmt-label bmt-label-noting"><span class="bmt-label-text">Noting</span></span>';
    }
    $out .= '</div>';

    return $out;
}

function bmt_save_prospects_callback() {
        global $wpdb;
        $t = bmt_get_ui_strings();

    // Backward-compatible nonce: verify when provided, but do not require it (legacy callers may omit).
    if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
    }
    
    // نام جدول با پیشوند صحیح وردپرس
    $table_name = $wpdb->prefix . 'bmt_links';
    $table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table_name);
    $table_sql = $table_clean !== '' ? '`' . esc_sql($table_clean) . '`' : '';

    // Backward-compat: if legacy column still exists (migration failed), keep inserts working.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table identifier is sanitized.
    $has_project_col = $table_sql !== '' ? (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'project_id')) : false;

	
    // گرفتن داده‌های ارسالی از جاوااسکریپت
    $links = isset($_POST['links']) ? (array) wp_unslash($_POST['links']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each item is sanitized below.

    $saved_links = [];

    // بررسی صحت داده‌ها
    if (empty($links) || !is_array($links)) {
            wp_send_json_error(['message' => $t['invalid_data']]);
        }
    
    $insert_failures = array();
    $inserted_count = 0;

    foreach ($links as $link) {
        $domain = isset($link['domain']) ? sanitize_text_field(wp_unslash($link['domain'])) : '';

        $example_input = isset($link['example']) ? (string) $link['example'] : '';
        $example_input = bmt_normalize_url_raw($example_input);
        $example = esc_url_raw($example_input);
        if ($example === '') {
            wp_send_json_error(array('message' => $t['invalid_data']));
        }

        if ($domain === '') {
            $parsed = wp_parse_url($example);
            if (is_array($parsed) && !empty($parsed['host'])) {
                $domain = preg_replace('/^www\./i', '', (string) $parsed['host']);
            }
        }

        $comment = isset($link['comment']) ? sanitize_text_field(wp_unslash($link['comment'])) : $t['default_comment'];
        $outreach = isset($link['outreach_strategy']) ? sanitize_text_field(wp_unslash($link['outreach_strategy'])) : 'email';
        $status   = isset($link['status']) ? sanitize_text_field(wp_unslash($link['status'])) : 'pending';


        // اگر رکورد مشابهی برای همین پروژه و دامنه موجود نیست، درج کن
        $existing = $table_sql !== '' ? (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple lookup.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is sanitized.
            $wpdb->prepare('SELECT COUNT(*) FROM ' . $table_sql . ' WHERE source_domain = %s', $domain)
        ) : 0;

        if (!$existing) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert user-added prospects.
            $insert_data = [
                'source_domain'     => $domain,
                'url_example'       => $example,
                'comment'           => $comment,
                'outreach_strategy' => $outreach,
                'status'            => $status,
            ];
            if ($has_project_col) {
                $insert_data['project_id'] = 1;
            }

            $ok = $wpdb->insert($table_name, $insert_data); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert user-added prospects.

            if (!$ok) {
                $insert_failures[] = array(
                    'domain' => $domain,
                    'example' => $example,
                    'error' => is_string($wpdb->last_error) ? $wpdb->last_error : 'DB insert failed.',
                );
                continue;
            }

			$link_id = (int) $wpdb->insert_id;
            if ($link_id <= 0) {
                $insert_failures[] = array(
                    'domain' => $domain,
                    'example' => $example,
                    'error' => 'DB insert returned empty id.',
                );
                continue;
            }

            $inserted_count++;

            // ذخیره برای برگرداندن در پاسخ
            $saved_links[] = [
				'id' => $link_id,
                'domain' => $domain,
                'example' => $example,
				'comment' => $comment,
				'outreach_strategy' => $outreach,
				'status' => $status,
            ];
        }
    }

    if (!empty($insert_failures)) {
        wp_send_json_error(array(
            'message' => 'Failed to save some prospects to database.',
            'failures' => $insert_failures,
        ));
    }

    // Send inserted backlinks to central Backlinks Bank via SMark Core API (non-blocking).
    if (!empty($saved_links)) {
        $urls = array();
        foreach ($saved_links as $row) {
            if (isset($row['example']) && is_string($row['example']) && trim($row['example']) !== '') {
                $urls[] = trim($row['example']);
            }
        }
        if (!empty($urls)) {
            bmt_import_backlinks_to_central_bank($urls);
        }
    }

    // برگرداندن پاسخ موفق با داده‌هایی که ذخیره شدند
    wp_send_json_success([
        'message'   => ($inserted_count > 0) ? 'Links saved successfully!' : 'No new links added.',
        'prospects' => $saved_links
    ]);
}

/**
 * Import URLs to SMark Core Backlinks Bank (producer) using REST API.
 * This is best-effort and should never block the UI.
 *
 * @param array $urls
 * @return void
 */
function bmt_import_backlinks_to_central_bank($urls) {
    if (!is_array($urls) || empty($urls)) {
        return;
    }

    $base_url = bmt_get_central_base_url();
    if (!is_string($base_url) || $base_url === '') {
        return;
    }

    $endpoint = rtrim($base_url, '/') . '/wp-json/smark-core/v1/backlinks-bank/import';

    $headers = array(
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept' => 'application/json',
    );

    $token = bmt_get_central_sync_token();
    if (is_string($token) && $token !== '') {
        $headers['x-smark-sync-token'] = $token;
    }

    $args = array(
        'method' => 'POST',
        'headers' => $headers,
        'body' => wp_json_encode(array('urls' => array_values($urls))),
        'timeout' => 2,
        'redirection' => 0,
        'blocking' => false,
        'sslverify' => true,
        'user-agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (backlinks-bank-import)',
    );

    try {
        wp_remote_post($endpoint, $args);
    } catch (Exception $e) {
        // Silent failure (do not block prospect saving).
    }
}

function bmt_normalize_central_base_url($url) {
    $url = is_string($url) ? trim($url) : '';
    if ($url === '') {
        return '';
    }

    $url = rtrim($url, '/');
    $scheme = wp_parse_url($url, PHP_URL_SCHEME);
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!in_array($scheme, array('http', 'https'), true) || !is_string($host) || $host === '') {
        return '';
    }

    return $url;
}

function bmt_get_central_base_url() {
    if (defined('SMARK_CENTRAL_BASE_URL') && is_string(SMARK_CENTRAL_BASE_URL) && SMARK_CENTRAL_BASE_URL !== '') {
        $url = bmt_normalize_central_base_url((string) SMARK_CENTRAL_BASE_URL);
        if ($url !== '') {
            return $url;
        }
    }

    $url = get_option(SMARK_CENTRAL_BASE_URL_OPTION, '');
    $url = is_string($url) ? bmt_normalize_central_base_url($url) : '';
    if ($url !== '') {
        return $url;
    }

    if (is_multisite()) {
        $url = get_site_option(SMARK_CENTRAL_BASE_URL_OPTION, '');
        $url = is_string($url) ? bmt_normalize_central_base_url($url) : '';
        if ($url !== '') {
            return $url;
        }
    }

    $filtered = apply_filters('SMARK_central_base_url', SMARK_DEFAULT_CENTRAL_BASE_URL);
    $filtered = is_string($filtered) ? bmt_normalize_central_base_url($filtered) : '';
    return $filtered !== '' ? $filtered : SMARK_DEFAULT_CENTRAL_BASE_URL;
}

function bmt_get_central_sync_token() {
    if (defined('SMARK_CENTRAL_SYNC_TOKEN') && is_string(SMARK_CENTRAL_SYNC_TOKEN) && SMARK_CENTRAL_SYNC_TOKEN !== '') {
        return SMARK_CENTRAL_SYNC_TOKEN;
    }

    $token = get_option('smark_central_sync_token', '');
    $token = is_string($token) ? trim($token) : '';
    if ($token !== '') {
        return $token;
    }

    $fallback = get_option('smark_core_sync_token', '');
    $fallback = is_string($fallback) ? trim($fallback) : '';
    if ($fallback !== '') {
        return $fallback;
    }

    if (is_multisite()) {
        $token = get_site_option('smark_central_sync_token', '');
        $token = is_string($token) ? trim($token) : '';
        if ($token !== '') {
            return $token;
        }

        $fallback = get_site_option('smark_core_sync_token', '');
        return is_string($fallback) ? trim($fallback) : '';
    }

    return '';
}

//به‌روزرسانی PHP برای هندل AJAX تغییر لینک‌ها
add_action('wp_ajax_bmt_update_link_url', 'bmt_update_link_url_callback');

function bmt_update_link_url_callback() {
    global $wpdb;

    // Backward-compatible nonce: verify when provided, but do not require it (legacy callers may omit).
    if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
    }

    $table_name = $wpdb->prefix . 'bmt_links';
    $link_id = isset($_POST['link_id']) ? absint(wp_unslash($_POST['link_id'])) : 0;
    $new_url_input = isset($_POST['new_url']) ? sanitize_text_field(wp_unslash($_POST['new_url'])) : '';
    $new_url_input = bmt_normalize_url_raw($new_url_input);
    $new_url = esc_url_raw($new_url_input);

    if (!$link_id || !$new_url) {
        wp_send_json_error('Invalid input.');
    }

    $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple update.
        $table_name,
        ['url_example' => $new_url],
        ['id' => $link_id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('DB update failed.');
    }
}

//به‌روزرسانی PHP برای هندل AJAX تغییر کامنت‌ها
add_action('wp_ajax_bmt_update_link_comment', 'bmt_update_link_comment');

function bmt_update_link_comment() {
    global $wpdb;
    $table = $wpdb->prefix . 'bmt_links';

    // Backward-compatible nonce: verify when provided, but do not require it (legacy callers may omit).
    if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
    }

    $id = isset($_POST['link_id']) ? absint(wp_unslash($_POST['link_id'])) : 0;
    $new_comment = isset($_POST['new_comment']) ? sanitize_text_field(wp_unslash($_POST['new_comment'])) : '';

    $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple update.
        $table,
        ['comment' => $new_comment],
        ['id' => $id],
        ['%s'],
        ['%d']
    );

    wp_send_json_success(['updated' => $updated]);
}

//بروزرسانی فیلدهای اوتریچ و استتوس
add_action('wp_ajax_bmt_update_outreach_status', 'bmt_update_outreach_status');
function bmt_update_outreach_status() {
	if (!current_user_can('edit_posts')) {
		wp_send_json_error('Unauthorized');
	}

    // Backward-compatible nonce: verify when provided, but do not require it (legacy callers may omit).
    if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
    }

	$link_id = isset($_POST['link_id']) ? absint(wp_unslash($_POST['link_id'])) : 0;
	$outreach_strategy = isset($_POST['outreach_strategy']) ? sanitize_text_field(wp_unslash($_POST['outreach_strategy'])) : '';
	$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

	global $wpdb;
	$table_name = $wpdb->prefix . 'bmt_links';

	$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple update.
		$table_name,
		[
			'outreach_strategy' => $outreach_strategy,
			'status' => $status
		],
		['id' => $link_id],
		['%s', '%s'],
		['%d']
	);

	if ($updated !== false) {
		wp_send_json_success('Updated');
	} else {
		wp_send_json_error('Failed to update');
	}
}

add_action('wp_ajax_bmt_update_target_page', 'bmt_update_target_page');
function bmt_update_target_page() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized');
    }

    // Backward-compatible nonce: verify when provided, but do not require it (legacy callers may omit).
    if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
    }

    $link_id = isset($_POST['link_id']) ? absint(wp_unslash($_POST['link_id'])) : 0;
    $target_post_ids = isset($_POST['target_post_ids']) ? (array) wp_unslash($_POST['target_post_ids']) : array();
    $target_post_ids = bmt_parse_target_post_ids($target_post_ids);

    if ($link_id <= 0) {
        wp_send_json_error('Invalid link.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'bmt_links';
    if (function_exists('bmt_ensure_links_target_column')) {
        bmt_ensure_links_target_column();
    }

    $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple update.
        $table_name,
        array(
            'target_post_id' => !empty($target_post_ids) ? (int) $target_post_ids[0] : 0,
            'target_post_ids' => implode(',', $target_post_ids),
        ),
        array('id' => $link_id),
        array('%d', '%s'),
        array('%d')
    );

    if ($updated !== false) {
        wp_send_json_success(array('target_post_ids' => $target_post_ids));
    }

    wp_send_json_error('Failed to update');
}

// بولک اکشن و حذف گروهی
add_action('wp_ajax_bmt_bulk_delete_links', 'bmt_bulk_delete_links');
function bmt_bulk_delete_links() {
    	$t = bmt_get_ui_strings();
    	if (!current_user_can('delete_posts')) {
    		wp_send_json_error('Unauthorized');
    	}

    // Backward-compatible nonce: verify when provided, but do not require it (legacy callers may omit).
    if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
    }
     
	$link_ids = isset($_POST['link_ids']) ? array_map('absint', (array) wp_unslash($_POST['link_ids'])) : array();
	$link_ids = array_values(array_filter($link_ids, function ($v) { return $v > 0; }));
    
    	if (empty($link_ids)) {
    		wp_send_json_error($t['no_ids']);
    	}
    
	global $wpdb;
	$table_name = $wpdb->prefix . 'bmt_links';
	$table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table_name);
	if ($table_clean === '') {
		wp_send_json_error($t['failed_delete']);
	}
	$table_sql = '`' . esc_sql($table_clean) . '`';

	$id_list = implode(',', $link_ids);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; ids are absint.
	$deleted = $wpdb->query("DELETE FROM {$table_sql} WHERE id IN ({$id_list})");

	if ($deleted !== false) {
    		wp_send_json_success();
    	} else {
    		wp_send_json_error($t['failed_delete']);
    	}
    }
    
// حذف پروژه‌ها
add_action('wp_ajax_bmt_delete_project', 'bmt_delete_project_callback');
function bmt_delete_project_callback() {
    wp_send_json_error(array('message' => 'Project delete is not supported (single-project mode).'));
}

//ذخیره گزینه اوتریچ اضافه شده
add_action('wp_ajax_bmt_save_outreach_strategy', 'bmt_save_outreach_strategy_callback');
function bmt_save_outreach_strategy_callback() {
	if (!current_user_can('smark_access')) wp_send_json_error();

    // Backward-compatible nonce: verify when provided, but do not require it (legacy callers may omit).
    if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
    }

	global $wpdb;
	$table = $wpdb->prefix . 'bmt_outreach_strategies';
    $table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
    $table_sql = $table_clean !== '' ? '`' . esc_sql($table_clean) . '`' : '';

	$slug = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
	$label = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';

	$exists = $table_sql !== '' ? (int) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple lookup.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is sanitized.
        $wpdb->prepare('SELECT COUNT(*) FROM ' . $table_sql . ' WHERE slug = %s', $slug) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    ) : 0;
	if ($exists) wp_send_json_error('Already exists');

	$result = $wpdb->insert($table, [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Admin action.
		'slug' => $slug,
		'label' => $label
	]);

	if ($result) {
		wp_send_json_success();
	} else {
		wp_send_json_error('DB insert failed');
	}
}

//گرفتن لیست روش‌ها هنگام نمایش select
function bmt_get_outreach_options() {
	global $wpdb;
	$table = $wpdb->prefix . 'bmt_outreach_strategies';
    $table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
    $table_sql = $table_clean !== '' ? '`' . esc_sql($table_clean) . '`' : '';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only options; table identifier is sanitized.
 	$results = $table_sql !== '' ? $wpdb->get_results("SELECT slug, label FROM {$table_sql} ORDER BY id ASC") : array();

	return $results ?: [];
}
    
}

// Important: this file may be included after the feature class is already loaded (e.g. via another loader).
// Do NOT return early here; AJAX endpoints below must still be registered.

if (!function_exists('bmt_analyze_links_callback')) {
add_action('wp_ajax_bmt_analyze_links', 'bmt_analyze_links_callback');
function bmt_analyze_links_callback() {
    global $wpdb;
    $t = bmt_get_ui_strings();

    // Backward-compatible nonce: verify when provided, but do not require it (legacy callers may omit).
    if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
    }

    if (function_exists('bmt_ensure_links_label_columns')) {
        bmt_ensure_links_label_columns();
    }
    if (function_exists('bmt_ensure_links_analysis_columns')) {
        bmt_ensure_links_analysis_columns();
    }

    $ids = isset($_POST['link_ids']) ? array_map('absint', (array) wp_unslash($_POST['link_ids'])) : array();
    $ids = array_values(array_filter($ids, function ($v) { return $v > 0; }));

    if (empty($ids)) {
        wp_send_json_error(array('message' => $t['no_ids']));
    }

    $table = $wpdb->prefix . 'bmt_links';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-side analysis.
    $table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
    if ($table_clean === '') {
        wp_send_json_error(array('message' => 'Invalid table name.'));
    }
    $table_sql = '`' . $table_clean . '`';

    // Ensure columns exist before selecting.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
    $has_label_comment = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table identifier is sanitized.
        $wpdb->prepare('SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', 'label_comment') // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
    $has_label_profile = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table identifier is sanitized.
        $wpdb->prepare('SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', 'label_profile') // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
    $has_label_error = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table identifier is sanitized.
        $wpdb->prepare('SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', 'label_error') // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
    $has_analysis_done = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema check; table identifier is sanitized.
        $wpdb->prepare('SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', 'analysis_done') // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized.
    );
    if (!$has_label_comment || !$has_label_profile || !$has_label_error || !$has_analysis_done) {
        wp_send_json_error(array('message' => 'Label columns are missing in database (bmt_links).'));
    }

    $id_list = implode(',', $ids);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is sanitized; ids are absint.
    $rows = $wpdb->get_results("SELECT id, url_example, label_comment, label_profile, label_error, analysis_done FROM {$table_sql} WHERE id IN ({$id_list})");

    if (empty($rows)) {
        wp_send_json_success(array('updated' => array()));
    }

    $updated = array();
    $errors = array();

    foreach ($rows as $row) {
        $url = isset($row->url_example) ? bmt_normalize_url_raw((string) $row->url_example) : '';
        if ($url === '') {
            continue;
        }

        $fetch_error = '';
        $html = bmt_fetch_analysis_html($url, $fetch_error);
        $has_fetch_error = ($html === '');

        $has_comment_link = false;
        $has_profile_btn = false;
        if (!$has_fetch_error) {
            $has_comment_link = bmt_detect_comment_link($html);
            $has_profile_btn = bmt_detect_profile_button($html);
        } else {
            $errors[] = array(
                'id' => (int) $row->id,
                'error' => ($fetch_error !== '') ? $fetch_error : 'Failed fetching URL.',
            );
        }

        $data = array(
            'analysis_done' => 1,
            'label_error' => $has_fetch_error ? 1 : 0,
            'label_comment' => $has_comment_link ? 1 : 0,
            'label_profile' => $has_profile_btn ? 1 : 0,
        );
        $format = array('%d', '%d', '%d', '%d');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple update.
        $ok = $wpdb->update($table, $data, array('id' => (int) $row->id), $format, array('%d'));
        if ($ok === false) {
            $errors[] = array(
                'id' => (int) $row->id,
                'error' => is_string($wpdb->last_error) ? $wpdb->last_error : 'DB update failed.',
            );
        }

        $updated[] = array(
            'id' => (int) $row->id,
            'analysis_done' => 1,
            'label_error' => $has_fetch_error ? 1 : 0,
            'label_comment' => $has_comment_link ? 1 : 0,
            'label_profile' => $has_profile_btn ? 1 : 0,
        );
    }

    wp_send_json_success(array('updated' => $updated, 'errors' => $errors));
}
}

if (!function_exists('bmt_remove_link_label_callback')) {
add_action('wp_ajax_bmt_remove_link_label', 'bmt_remove_link_label_callback');
function bmt_remove_link_label_callback() {
    global $wpdb;

    // Backward-compatible nonce: verify when provided, but do not require it (legacy callers may omit).
    if (isset($_POST['nonce']) && is_string($_POST['nonce']) && $_POST['nonce'] !== '') { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'SMARK_cm_nonce');
    }

    $link_id = isset($_POST['link_id']) ? absint(wp_unslash($_POST['link_id'])) : 0;
    $label = isset($_POST['label']) ? sanitize_key(wp_unslash($_POST['label'])) : '';

    if (!$link_id || ($label !== 'comment' && $label !== 'profile')) {
        wp_send_json_error(array('message' => 'Invalid input.'));
    }

    $table = $wpdb->prefix . 'bmt_links';
    $column = ($label === 'comment') ? 'label_comment' : 'label_profile';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple update.
    $wpdb->update($table, array($column => 0), array('id' => $link_id), array('%d'), array('%d'));

    wp_send_json_success();
}
}

if (!function_exists('bmt_fetch_analysis_html')) {
function bmt_fetch_analysis_html($url, &$error = '') {
    $error = '';
    $url = is_string($url) ? trim($url) : '';
    if ($url === '') {
        $error = 'Empty URL.';
        return '';
    }

    $common_headers = array(
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'fa,en-US;q=0.9,en;q=0.8',
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
    );

    $attempts = array(
        array('sslverify' => true, 'timeout' => 10, 'redirection' => 7),
        array('sslverify' => false, 'timeout' => 12, 'redirection' => 7),
    );

    $urls_to_try = array($url);
    if (stripos($url, 'https://') === 0) {
        $urls_to_try[] = 'http://' . substr($url, 8);
    } elseif (stripos($url, 'http://') === 0) {
        $urls_to_try[] = 'https://' . substr($url, 7);
    }

    foreach ($urls_to_try as $try_url) {
        foreach ($attempts as $a) {
            $args = array(
                'timeout' => $a['timeout'],
                'redirection' => $a['redirection'],
                'blocking' => true,
                'sslverify' => $a['sslverify'],
                'headers' => $common_headers,
                'httpversion' => '1.1',
                'compress' => true,
            );

            $resp = wp_remote_get($try_url, $args);
            if (is_wp_error($resp)) {
                $error = $resp->get_error_message();
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($resp);
            if ($code < 200 || $code >= 300) {
                $error = 'HTTP ' . $code;
                continue;
            }

            $body = (string) wp_remote_retrieve_body($resp);
            if ($body === '') {
                $error = 'Empty response body.';
                continue;
            }

            return $body;
        }
    }

    if ($error === '') {
        $error = 'Unknown fetch error.';
    }

    return '';
}
}

if (!function_exists('bmt_detect_comment_link')) {
function bmt_detect_comment_link($html) {
    if (!is_string($html) || trim($html) === '') {
        return false;
    }

    if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        // Fallback: if we can't parse DOM, approximate with a heuristic.
        $has_comment_form = (bool) preg_match('~id=(\"|\\\')?(respond|commentform)~i', $html) || (bool) preg_match('~comment-respond|comment-form~i', $html);
        if (!$has_comment_form) {
            return false;
        }
        // Look for any hyperlink and/or raw URL in the comments section (best-effort).
        if (preg_match('~id=(\"|\\\')?comments~i', $html) && preg_match('~<a\\s+[^>]*href=~i', $html)) {
            return true;
        }
        return (bool) preg_match('~https?://~i', $html);
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    if (!$loaded) {
        return false;
    }

    $xpath = new DOMXPath($dom);

    $has_comment_form = (bool) $xpath->query('//*[@id="respond" or @id="commentform" or contains(concat(" ", normalize-space(@class), " "), " comment-respond ") or contains(concat(" ", normalize-space(@class), " "), " comment-form ")]')->length;
    if (!$has_comment_form) {
        // Heuristic: if there is no comment form/respond block, treat comments as not enabled.
        return false;
    }

    $comment_containers = $xpath->query('//*[@id="comments" or contains(concat(" ", normalize-space(@class), " "), " comments-area ") or contains(concat(" ", normalize-space(@class), " "), " comment-list ") or contains(concat(" ", normalize-space(@class), " "), " comment-content ") or contains(concat(" ", normalize-space(@class), " "), " comment-body ") or contains(concat(" ", normalize-space(@class), " "), " comment ")]');
    if (!$comment_containers || $comment_containers->length === 0) {
        // Fallback: search any element with id/class containing "comment".
        $comment_containers = $xpath->query('//*[contains(translate(@id,"COMMENT","comment"),"comment") or contains(translate(@class,"COMMENT","comment"),"comment")]');
        if (!$comment_containers || $comment_containers->length === 0) {
            return false;
        }
    }

    foreach ($comment_containers as $node) {
        $links = $xpath->query('.//a[@href]', $node);
        if ($links && $links->length > 0) {
            foreach ($links as $a) {
                $href = $a->getAttribute('href');
                $href = is_string($href) ? trim($href) : '';
                if ($href !== '' && $href !== '#' && strpos($href, '#comment') !== 0) {
                    return true;
                }
            }
        }
        // Also detect raw URLs inside comment content.
        $text = $node->textContent;
        if (is_string($text) && preg_match('~https?://~i', $text)) {
            return true;
        }
    }

    return false;
}
}

if (!function_exists('bmt_detect_profile_button')) {
function bmt_detect_profile_button($html) {
    if (!is_string($html) || trim($html) === '') {
        return false;
    }

    if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        // Fallback: keyword search across HTML.
        return (bool) preg_match('~\\b(login|log\\s*in|sign\\s*in|signin|sign-in|signup|sign\\s*up|sign-up|register|create\\s*account|account|profile)\\b|ورود|لاگین|ثبت\\s*نام|ثبت‌نام|عضویت|حساب\\s*کاربری|پروفایل~iu', $html);
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    if (!$loaded) {
        return false;
    }

    $xpath = new DOMXPath($dom);

    $keywords = array(
        'login',
        'log in',
        'sign in',
        'signin',
        'sign-in',
        'signup',
        'sign up',
        'sign-up',
        'register',
        'create account',
        'account',
        'profile',
        'ورود',
        'لاگین',
        'ثبت نام',
        'ثبت‌نام',
        'عضویت',
        'حساب کاربری',
        'پروفایل',
    );

    $needle = '~(' . implode('|', array_map(function ($k) {
        return preg_quote($k, '~');
    }, $keywords)) . ')~i';

    $nodes = $xpath->query('//a[@href] | //button | //input[@type="submit" or @type="button"]');
    if (!$nodes || $nodes->length === 0) {
        return false;
    }

    foreach ($nodes as $node) {
        $text = '';
        $href = '';
        $aria = '';

        if ($node->nodeName === 'a') {
            $href = $node->getAttribute('href');
        }
        if ($node->nodeName === 'input') {
            $text = $node->getAttribute('value');
        } else {
            $text = $node->textContent;
        }
        $aria = $node->getAttribute('aria-label');

        $hay = strtolower(trim((string) $text . ' ' . (string) $aria . ' ' . (string) $href));
        if ($hay !== '' && preg_match($needle, $hay)) {
            return true;
        }
    }

    return false;
}
}

if (!class_exists('SMarkBacklinksManagement', false)) {
class SMarkBacklinksManagement {
    const OPTION_PROJECT_MAP = 'smark_bmt_project_map';

    public function __construct() {
        add_action('admin_menu', array($this, 'hide_legacy_menu_page'), 999);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function hide_legacy_menu_page() {
        // Hide standalone plugin menu entry if that plugin is still installed/active.
        remove_menu_page('backlinks-management-tool');
    }

    private function ensure_bmt_schema() {
        if (!function_exists('bmt_create_tables') || !function_exists('bmt_create_outreach_table')) {
            return;
        }

        global $wpdb;
        $projects_table = $wpdb->prefix . 'bmt_projects';
        $links_table = $wpdb->prefix . 'bmt_links';
        $outreach_table = $wpdb->prefix . 'bmt_outreach_strategies';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check requires direct query.
        $has_projects = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $projects_table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check requires direct query.
        $has_links = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $links_table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check requires direct query.
        $has_outreach = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $outreach_table));

        if (!$has_projects || !$has_links) {
            bmt_create_tables();
        }
        if (!$has_outreach) {
            bmt_create_outreach_table();
        }
        if ($has_links) {
            bmt_ensure_links_label_columns();
            if (function_exists('bmt_ensure_links_analysis_columns')) {
                bmt_ensure_links_analysis_columns();
            }
            if (function_exists('bmt_ensure_links_target_column')) {
                bmt_ensure_links_target_column();
            }
            bmt_migrate_drop_project_id_column();
            bmt_ensure_links_label_columns();
            if (function_exists('bmt_ensure_links_analysis_columns')) {
                bmt_ensure_links_analysis_columns();
            }
            if (function_exists('bmt_ensure_links_target_column')) {
                bmt_ensure_links_target_column();
            }
        }
    }

    public function enqueue_assets($hook) {
        // Some admin setups resolve hook_suffix differently; rely on the page slug like other SMark features.
        if (!isset($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== 'smark-backlinks-management') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $this->ensure_bmt_schema();

        $current_lang = get_option('smark_panel_language', 'en');
        $current_lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';

        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'vazirmatn-font',
            'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap',
            array(),
            defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0'
        );

        $asset_version = defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0';
        $initial_target_post_id = isset($_GET['target_post_id']) ? absint(wp_unslash($_GET['target_post_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        // Use Content Management shell styles for header/breadcrumb/footer + body background.
        wp_enqueue_style(
            'smark-backlinks-shell',
            SMARK_PLUGIN_URL . 'features/content-management/assets/content-management.css',
            array('dashicons', 'vazirmatn-font'),
            $asset_version
        );

        wp_enqueue_style(
            'smark-backlinks-management',
            plugin_dir_url(__FILE__) . 'assets/backlinks-management.css',
            array('smark-backlinks-shell'),
            $asset_version
        );

        wp_enqueue_script(
            'smark-backlinks-management',
            plugin_dir_url(__FILE__) . 'assets/backlinks-management.js',
            array('jquery'),
            $asset_version,
            true
        );

        wp_localize_script('smark-backlinks-management', 'SMarkBacklinksManagement', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('SMARK_cm_nonce'),
            'lang' => $current_lang === 'fa' ? 'fa' : 'en',
            'initialTargetPostId' => $initial_target_post_id,
        ));

        // Load legacy assets for the embedded tool.
        wp_enqueue_script('jquery');
        $bmt_i18n = array(
            'saving' => ($current_lang === 'fa') ? 'در حال ذخیره…' : 'Saving...',
            'addProspects' => ($current_lang === 'fa') ? 'افزودن پروسپکت' : 'Add Prospects',
            'eachLineOneLink' => ($current_lang === 'fa') ? 'هر خط باید فقط شامل یک لینک معتبر باشد.' : 'Each line must contain only one valid link.',
            'invalidUrl' => ($current_lang === 'fa') ? 'URL نامعتبر: ' : 'Invalid URL: ',
            'linksSaved' => ($current_lang === 'fa') ? '✅ لینک‌ها با موفقیت ذخیره شدند!' : '✅ Links saved successfully!',
        );
        wp_add_inline_script('smark-backlinks-management', 'window.BMT_I18N=' . wp_json_encode($bmt_i18n) . ';', 'before');

        wp_add_inline_script(
            'smark-backlinks-management',
            'window.BMT_EMBEDDED = { embedded: true, initialTargetPostId: ' . (int) $initial_target_post_id . ' };',
            'before'
        );

        add_action('admin_body_class', function ($classes) use ($rtl_class) {
            if (strpos((string) $classes, 'smark-plugin-page') === false) {
                $classes .= ' smark-plugin-page';
            }
            return $classes;
        });
    }

    private function get_strings($lang) {
        $lang = ($lang === 'fa') ? 'fa' : 'en';
        $strings = array(
            'en' => array(
                'page_title' => 'Backlinks Management',
                'page_subtitle' => 'Track prospects, outreach strategy, and backlink status by project.',
                'breadcrumb_dashboard' => 'Dashboard',
                'breadcrumb_seo' => 'SEO Management',
                'breadcrumb_current' => 'Backlinks Management',
            ),
            'fa' => array(
                'page_title' => 'مدیریت بک‌لینک‌ها',
                'page_subtitle' => 'پروسپکت‌ها، استراتژی ارتباط و وضعیت بک‌لینک‌ها را بر اساس پروژه مدیریت کنید.',
                'breadcrumb_dashboard' => 'داشبورد',
                'breadcrumb_seo' => 'مدیریت سئو',
                'breadcrumb_current' => 'مدیریت بک‌لینک‌ها',
            ),
        );

        return $strings[$lang];
    }

    private function resolve_projects_table() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $candidate = $prefix . 'SMARK_projects';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence discovery.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $candidate));
        if ($exists === $candidate) {
            return $candidate;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback discovery.
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $prefix . '%SMARK_projects'));
        if (is_array($tables) && !empty($tables)) {
            return (string) $tables[0];
        }

        return $candidate;
    }

    private function get_current_smark_project() {
        $project_id = (int) get_option('smark_current_project_db_id', 0);
        if ($project_id <= 0) {
            $project_id = (int) get_option('SMARK_current_project_db_id', 0);
        }

        if ($project_id <= 0) {
            return array('id' => 0, 'name' => '');
        }

        global $wpdb;
        $table = $this->resolve_projects_table();
        $table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
        if ($table_clean === '') {
            return array('id' => $project_id, 'name' => '');
        }
        $table_sql = '`' . $table_clean . '`';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is internal.
        $row = $wpdb->get_row($wpdb->prepare('SELECT project_name FROM ' . $table_sql . ' WHERE id = %d LIMIT 1', $project_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is internal; identifiers cannot be placeholders.
        $name = is_array($row) && isset($row['project_name']) ? sanitize_text_field((string) $row['project_name']) : '';

        return array('id' => $project_id, 'name' => $name);
    }

    private function get_bmt_project_id_for_smark_project($smark_project_id, $smark_project_name) {
        $smark_project_id = (int) $smark_project_id;
        $smark_project_name = is_string($smark_project_name) ? trim($smark_project_name) : '';
        if ($smark_project_id <= 0) {
            return 0;
        }

        $map = get_option(self::OPTION_PROJECT_MAP, array());
        $map = is_array($map) ? $map : array();
        $key = (string) $smark_project_id;
        $mapped = isset($map[$key]) ? (int) $map[$key] : 0;

        global $wpdb;
        $projects_table = $wpdb->prefix . 'bmt_projects';
        $projects_table_clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $projects_table);
        $projects_table_sql = $projects_table_clean !== '' ? '`' . esc_sql($projects_table_clean) . '`' : '';
        if ($mapped > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $exists = $projects_table_sql !== '' ? (int) $wpdb->get_var(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is sanitized.
                $wpdb->prepare('SELECT COUNT(*) FROM ' . $projects_table_sql . ' WHERE id = %d', $mapped)
            ) : 0;
            if ($exists > 0) {
                return $mapped;
            }
        }

        if ($smark_project_name === '') {
            $smark_project_name = 'Project ' . $smark_project_id;
        }

        // Try to reuse by name.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing_id = $projects_table_sql !== '' ? (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is sanitized.
            $wpdb->prepare('SELECT id FROM ' . $projects_table_sql . ' WHERE name = %s LIMIT 1', $smark_project_name)
        ) : 0;
        if ($existing_id > 0) {
            $map[$key] = $existing_id;
            update_option(self::OPTION_PROJECT_MAP, $map, false);
            return $existing_id;
        }

        // Create BMT project row.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = $wpdb->insert($projects_table, array('name' => $smark_project_name), array('%s'));
        if (!$inserted) {
            return 0;
        }

        $new_id = (int) $wpdb->insert_id;
        if ($new_id > 0) {
            $map[$key] = $new_id;
            update_option(self::OPTION_PROJECT_MAP, $map, false);
        }

        return $new_id;
    }

    public function render_page() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'));
        }

        $current_lang = get_option('smark_panel_language', 'en');
        $current_lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';

        $strings = $this->get_strings($current_lang);

        $current_project = $this->get_current_smark_project();
        $bmt_project_id = $this->get_bmt_project_id_for_smark_project($current_project['id'], $current_project['name']);

        add_filter('bmt_default_project_id', function () use ($bmt_project_id) {
            return (int) $bmt_project_id;
        });

        add_filter('bmt_page_slug', function () {
            return 'smark-backlinks-management';
        });

        ?>
        <div class="wrap smark-backlinks-management-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
            <div class="smark-page-header">
                <h1><?php echo esc_html($strings['page_title']); ?></h1>
                <p class="description"><?php echo esc_html($strings['page_subtitle']); ?></p>
            </div>

            <div class="smark-breadcrumb">
                <div class="breadcrumb-left">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html($strings['breadcrumb_dashboard']); ?></a>
                    <span class="separator"><?php echo $rtl_class ? '‹' : '›'; ?></span>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-seo-optimization')); ?>"><?php echo esc_html($strings['breadcrumb_seo']); ?></a>
                    <span class="separator"><?php echo $rtl_class ? '‹' : '›'; ?></span>
                    <span class="current"><?php echo esc_html($strings['breadcrumb_current']); ?></span>
                </div>
                <div class="breadcrumb-right">
                    <div class="language-selector">
                        <span class="dashicons dashicons-translation"></span>
                        <select id="SMARK_language_select" class="language-dropdown">
                            <option value="en" <?php selected($current_lang, 'en'); ?>>English</option>
                            <option value="fa" <?php selected($current_lang, 'fa'); ?>>فارسی</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="smark-backlinks-management-content">
                <div class="smark-backlinks-embedded">
                    <?php if (function_exists('bmt_admin_page')) { bmt_admin_page(); } ?>
                </div>
            </div>

            <div class="smark-backlinks-opportunities-card">
                <?php if (function_exists('bmt_render_opportunities_section')) { echo bmt_render_opportunities_section(); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

            <div class="smark-version-footer">
                <div class="version-info">
                    <span class="version-label"><?php echo ($current_lang === 'fa') ? 'پلاگین اسمارک' : 'SMark Plugin'; ?></span>
                    <span class="version-separator">•</span>
                    <span class="version-number">v<?php echo esc_html(defined('SMARK_VERSION') ? SMARK_VERSION : ''); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
}
}

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals

if (!isset($GLOBALS['smark_backlinks_management']) || !($GLOBALS['smark_backlinks_management'] instanceof SMarkBacklinksManagement)) {
    $GLOBALS['smark_backlinks_management'] = new SMarkBacklinksManagement();
}
