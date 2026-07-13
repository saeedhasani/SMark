<?php
/**
 * Email Marketing Feature
 */

if (!defined('WPINC')) {
    die;
}

class SMarkEmailMarketing {
    const OPTION_EMAIL_ACCOUNTS = 'smark_email_marketing_email_accounts';
    const OPTION_CONTACTS = 'smark_email_marketing_contacts';
    const OPTION_CONTACT_LISTS = 'smark_email_marketing_contact_lists';
    const OPTION_CONTACT_TAGS = 'smark_email_marketing_contact_tags';
    const OPTION_CAMPAIGN_MESSAGES = 'smark_email_marketing_campaign_messages';
    const OPTION_CAMPAIGN_EVENTS = 'smark_email_marketing_campaign_events';
    const EMAIL_SECRET_PREFIX = 'smarkenc:v1:';
    const AUDIENCE_ALL_SEGMENTS = '__smark_all_contacts__';

    private $campaign_mailer_account = array();
    private $campaign_mail_errors = array();
    private $campaign_mailer_hook_priority = 9999;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_post_smark_email_account_save', array($this, 'handle_email_account_save'));
        add_action('admin_post_smark_email_account_delete', array($this, 'handle_email_account_delete'));
        add_action('admin_post_smark_email_contact_save', array($this, 'handle_email_contact_save'));
        add_action('admin_post_smark_email_contact_delete', array($this, 'handle_email_contact_delete'));
        add_action('admin_post_smark_email_contact_list_save', array($this, 'handle_email_contact_list_save'));
        add_action('admin_post_smark_email_contact_list_delete', array($this, 'handle_email_contact_list_delete'));
        add_action('admin_post_smark_email_contact_tag_save', array($this, 'handle_email_contact_tag_save'));
        add_action('admin_post_smark_email_contact_tag_delete', array($this, 'handle_email_contact_tag_delete'));
        add_action('admin_post_smark_email_campaign_message_save', array($this, 'handle_campaign_message_save'));
        add_action('admin_post_smark_email_campaign_message_delete', array($this, 'handle_campaign_message_delete'));
        add_action('admin_post_smark_email_campaign_message_send', array($this, 'handle_campaign_message_send'));
        add_action('admin_post_smark_email_contacts_import_preview', array($this, 'handle_contacts_import_preview'));
        add_action('admin_post_smark_email_contacts_import', array($this, 'handle_contacts_import'));
        add_action('wp_ajax_smark_email_contacts_import_preview', array($this, 'ajax_contacts_import_preview'));
        add_action('wp_ajax_smark_email_contacts_import', array($this, 'ajax_contacts_import'));
        add_action('wp_ajax_smark_email_contacts_page', array($this, 'ajax_contacts_page'));
        add_action('wp_ajax_smark_dashboard_email_contacts_view', array($this, 'ajax_dashboard_email_contacts_view'));
        add_action('wp_ajax_smark_dashboard_email_accounts_view', array($this, 'ajax_dashboard_email_accounts_view'));
        add_action('wp_ajax_smark_dashboard_email_campaign_message_view', array($this, 'ajax_dashboard_email_campaign_message_view'));
        add_action('wp_ajax_smark_dashboard_email_performance_view', array($this, 'ajax_dashboard_email_performance_view'));
        add_action('wp_ajax_smark_email_contact_save_modal', array($this, 'ajax_email_contact_save_modal'));
        add_action('wp_ajax_smark_email_contact_delete_modal', array($this, 'ajax_email_contact_delete_modal'));
        add_action('wp_ajax_smark_email_campaign_message_send_start', array($this, 'ajax_campaign_message_send_start'));
        add_action('wp_ajax_smark_email_campaign_message_quick_send_start', array($this, 'ajax_campaign_message_quick_send_start'));
        add_action('wp_ajax_smark_email_campaign_message_send_batch', array($this, 'ajax_campaign_message_send_batch'));
        add_action('wp_ajax_smark_email_campaign_message_send', array($this, 'ajax_campaign_message_send'));
        add_action('wp_ajax_smark_email_campaign_message_quick_send', array($this, 'ajax_campaign_message_quick_send'));
        add_action('wp_ajax_smark_email_campaign_message_save_modal', array($this, 'ajax_campaign_message_save_modal'));
        add_action('wp_ajax_smark_email_campaign_message_test_send', array($this, 'ajax_campaign_message_test_send'));
        add_action('wp_ajax_smark_email_campaign_activity_page', array($this, 'ajax_campaign_activity_page'));
        add_action('wp_ajax_smark_email_campaign_failure_retry_start', array($this, 'ajax_campaign_failure_retry_start'));
        add_action('wp_ajax_smark_email_campaign_failure_retry_batch', array($this, 'ajax_campaign_failure_retry_batch'));
        add_action('wp_ajax_smark_email_track_open', array($this, 'track_campaign_open'));
        add_action('wp_ajax_nopriv_smark_email_track_open', array($this, 'track_campaign_open'));
        add_action('wp_ajax_smark_email_track_click', array($this, 'track_campaign_click'));
        add_action('wp_ajax_nopriv_smark_email_track_click', array($this, 'track_campaign_click'));
        add_action('template_redirect', array($this, 'maybe_handle_public_campaign_tracking'), 0);
    }

    public function add_submenu_page() {
        add_submenu_page(
            null,
            __('Email Accounts', 'smark'),
            __('Email Accounts', 'smark'),
            'smark_access',
            'smark-email-accounts',
            array($this, 'render_email_accounts_page')
        );

        add_submenu_page(
            null,
            __('Campaign Message', 'smark'),
            __('Campaign Message', 'smark'),
            'smark_access',
            'smark-email-campaign-message',
            array($this, 'render_campaign_message_page')
        );

        add_submenu_page(
            null,
            __('Performance Tracking', 'smark'),
            __('Performance Tracking', 'smark'),
            'smark_access',
            'smark-email-performance',
            array($this, 'render_performance_page')
        );
    }

    public function enqueue_assets($hook) {
        if (!in_array($hook, array('toplevel_page_smark-dashboard', 'smark_page_smark-dashboard-page', 'admin_page_smark-email-accounts', 'admin_page_smark-email-campaign-message', 'admin_page_smark-email-performance'), true)) {
            return;
        }

        wp_enqueue_style(
            'vazirmatn-font',
            'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap',
            array(),
            SMARK_VERSION
        );

        wp_enqueue_style(
            'smark-email-marketing',
            SMARK_PLUGIN_URL . 'features/seo-optimization/assets/seo-optimization.css',
            array('vazirmatn-font'),
            SMARK_VERSION
        );

        wp_add_inline_style('smark-email-marketing', $this->get_email_accounts_css());

        wp_enqueue_script(
            'smark-email-marketing',
            SMARK_PLUGIN_URL . 'features/seo-optimization/assets/seo-optimization.js',
            array('jquery'),
            SMARK_VERSION,
            true
        );
        wp_enqueue_editor();

        $lang = get_option('smark_panel_language', 'en');
        $lang = ($lang === 'fa') ? 'fa' : 'en';
        wp_localize_script('smark-email-marketing', 'smarkSeoOptimization', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('smark_seo_nonce'),
            'contactsImportNonce' => wp_create_nonce('smark_email_contacts_import_ajax'),
            'contactsPageNonce' => wp_create_nonce('smark_email_contacts_page_ajax'),
            'campaignMessageNonce' => wp_create_nonce('smark_email_campaign_message_ajax'),
            'strings' => array(
                'saving'       => ($lang === 'fa') ? 'در حال ذخیره...' : 'Saving...',
                'saved'        => ($lang === 'fa') ? 'ذخیره شد.' : 'Saved.',
                'error'        => ($lang === 'fa') ? 'خطا در ذخیره‌سازی.' : 'Save failed.',
                'readingFile'  => ($lang === 'fa') ? 'در حال خواندن فایل...' : 'Reading file...',
                'importing'    => ($lang === 'fa') ? 'در حال وارد کردن مخاطبان...' : 'Importing contacts...',
                'sending'      => ($lang === 'fa') ? 'در حال ارسال...' : 'Sending...',
                'resetTitle'   => '',
                'resetConfirm' => '',
                'resetSuccess' => '',
            ),
        ));

        wp_add_inline_script('smark-email-marketing', $this->get_email_marketing_inline_js());

        add_action('admin_body_class', function($classes) {
            if (strpos($classes, 'smark-plugin-page') === false) {
                $classes .= ' smark-plugin-page';
            }
            return $classes;
        });
    }

    public function render_page() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'));
        }

        $current_lang = get_option('smark_panel_language', 'en');
        $current_lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';
        $strings = $this->get_strings($current_lang);
        $tasks = $this->get_tasks($current_lang);
        ?>
        <div class="wrap smark-seo-optimization-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
            <?php $this->render_standard_header($strings, $current_lang, $rtl_class, false); ?>

            <div class="seo-grid">
                <section class="seo-step-card seo-step-card--full" data-step="strategy">
                    <header class="seo-step-header smark-email-workflow-header">
                        <div>
                            <h2><?php echo esc_html($strings['section_title']); ?></h2>
                            <p><?php echo esc_html($strings['section_description']); ?></p>
                        </div>
                    </header>

                    <ul class="seo-task-list">
                        <?php foreach ($tasks as $task) : ?>
                            <li class="seo-task">
                                <?php if (!empty($task['url'])) : ?>
                                    <a class="smark-email-task-link" href="<?php echo esc_url($task['url']); ?>">
                                <?php endif; ?>
                                <div class="seo-task-content">
                                    <span class="seo-task-icon dashicons <?php echo esc_attr($task['icon']); ?>" aria-hidden="true"></span>
                                    <span class="seo-task-text">
                                        <strong><?php echo esc_html($task['title']); ?></strong>
                                        <small><?php echo esc_html($task['description']); ?></small>
                                    </span>
                                </div>
                                <?php if (!empty($task['url'])) : ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>

            <?php $this->render_version_footer($current_lang); ?>
            <?php $this->render_campaign_failure_detail_modal($strings); ?>
        </div>
        <?php
    }

    public function render_email_accounts_page() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'));
        }

        $current_lang = get_option('smark_panel_language', 'en');
        $current_lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';
        $strings = $this->get_email_account_strings($current_lang);
        $accounts = $this->get_email_accounts();
        $daily_sent_counts = $this->get_email_account_daily_sent_counts();
        $message = isset($_GET['smark_message']) ? sanitize_key(wp_unslash($_GET['smark_message'])) : '';
        $notice = $this->get_basic_notice($message, $strings);
        ?>
        <div
            class="wrap smark-seo-optimization-page <?php echo esc_attr($rtl_class); ?>"
            data-lang="<?php echo esc_attr($current_lang); ?>"
            data-smark-notice-message="<?php echo esc_attr($notice['message']); ?>"
            data-smark-notice-type="<?php echo esc_attr($notice['type']); ?>"
        >
            <?php $this->render_standard_header($strings, $current_lang, $rtl_class, true); ?>

            <div class="seo-grid">
                <section class="seo-step-card seo-step-card--full" data-step="strategy" data-smark-email-account-section>
                    <header class="seo-step-header smark-email-account-form-header">
                        <div>
                            <h2 data-smark-provider-text data-email-text="<?php echo esc_attr($strings['form_title_email']); ?>" data-gmail-text="<?php echo esc_attr($strings['form_title_gmail']); ?>" data-outlook-text="<?php echo esc_attr($strings['form_title_outlook']); ?>"><?php echo esc_html($strings['form_title_email']); ?></h2>
                            <p data-smark-provider-text data-email-text="<?php echo esc_attr($strings['form_description_email']); ?>" data-gmail-text="<?php echo esc_attr($strings['form_description_gmail']); ?>" data-outlook-text="<?php echo esc_attr($strings['form_description_outlook']); ?>"><?php echo esc_html($strings['form_description_email']); ?></p>
                        </div>
                        <div class="smark-email-provider-switch">
                            <label for="smark_email_provider"><?php echo esc_html($strings['provider_label']); ?></label>
                            <select id="smark_email_provider" name="provider" form="smarkEmailAccountForm">
                                <option value="email"><?php echo esc_html($strings['provider_email']); ?></option>
                                <option value="gmail"><?php echo esc_html($strings['provider_gmail']); ?></option>
                                <option value="outlook"><?php echo esc_html($strings['provider_outlook']); ?></option>
                            </select>
                        </div>
                    </header>

                    <form id="smarkEmailAccountForm" class="smark-email-account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('smark_email_account_save', 'smark_email_account_nonce'); ?>
                        <input type="hidden" name="action" value="smark_email_account_save">
                        <input type="hidden" name="account_id" value="">

                        <div class="smark-email-form-grid">
                            <label>
                                <span><?php echo esc_html($strings['field_label']); ?></span>
                                <input type="text" name="account_label" required placeholder="<?php echo esc_attr($strings['field_label_placeholder_email']); ?>" data-email-placeholder="<?php echo esc_attr($strings['field_label_placeholder_email']); ?>" data-gmail-placeholder="<?php echo esc_attr($strings['field_label_placeholder_gmail']); ?>" data-outlook-placeholder="<?php echo esc_attr($strings['field_label_placeholder_outlook']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_sender_name']); ?></span>
                                <input type="text" name="sender_name" required placeholder="<?php echo esc_attr($strings['field_sender_name_placeholder']); ?>">
                            </label>

                            <label>
                                <span data-smark-provider-text data-email-text="<?php echo esc_attr($strings['field_email_email']); ?>" data-gmail-text="<?php echo esc_attr($strings['field_email_gmail']); ?>" data-outlook-text="<?php echo esc_attr($strings['field_email_outlook']); ?>"><?php echo esc_html($strings['field_email_email']); ?></span>
                                <input type="email" name="email_address" required placeholder="name@example.com" data-email-placeholder="name@example.com" data-gmail-placeholder="name@gmail.com" data-outlook-placeholder="name@outlook.com">
                            </label>

                            <label>
                                <span>
                                    <span data-smark-provider-text data-email-text="<?php echo esc_attr($strings['field_password_email']); ?>" data-gmail-text="<?php echo esc_attr($strings['field_password_gmail']); ?>" data-outlook-text="<?php echo esc_attr($strings['field_password_outlook']); ?>"><?php echo esc_html($strings['field_password_email']); ?></span>
                                    <span class="smark-email-gmail-app-link" data-smark-gmail-only>
                                        (<?php echo wp_kses_post(sprintf(
                                            '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s <span class="dashicons dashicons-external" aria-hidden="true"></span></a>',
                                            esc_url('https://myaccount.google.com/apppasswords'),
                                            esc_html($strings['gmail_app_password_link'])
                                        )); ?>)
                                    </span>
                                    <span class="smark-email-gmail-app-info" data-smark-gmail-only>
                                        <button type="button" class="smark-email-info-button" aria-label="<?php echo esc_attr($strings['gmail_app_password_tooltip_label']); ?>">
                                            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                                        </button>
                                        <span class="smark-email-info-tooltip" role="tooltip">
                                            <strong><?php echo esc_html($strings['gmail_app_password_tooltip_title']); ?></strong>
                                            <span><?php echo esc_html($strings['gmail_app_password_tooltip_step_1']); ?></span>
                                            <span><?php echo esc_html($strings['gmail_app_password_tooltip_step_2']); ?></span>
                                            <span><?php echo esc_html($strings['gmail_app_password_tooltip_step_3']); ?></span>
                                        </span>
                                    </span>
                                    <span class="smark-email-gmail-app-link" data-smark-outlook-only>
                                        (<?php echo wp_kses_post(sprintf(
                                            '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s <span class="dashicons dashicons-external" aria-hidden="true"></span></a>',
                                            esc_url('https://account.microsoft.com/security'),
                                            esc_html($strings['outlook_app_password_link'])
                                        )); ?>)
                                    </span>
                                    <span class="smark-email-gmail-app-info" data-smark-outlook-only>
                                        <button type="button" class="smark-email-info-button" aria-label="<?php echo esc_attr($strings['outlook_app_password_tooltip_label']); ?>">
                                            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                                        </button>
                                        <span class="smark-email-info-tooltip" role="tooltip">
                                            <strong><?php echo esc_html($strings['outlook_app_password_tooltip_title']); ?></strong>
                                            <span><?php echo esc_html($strings['outlook_app_password_tooltip_step_1']); ?></span>
                                            <span><?php echo esc_html($strings['outlook_app_password_tooltip_step_2']); ?></span>
                                            <span><?php echo esc_html($strings['outlook_app_password_tooltip_step_3']); ?></span>
                                        </span>
                                    </span>
                                </span>
                                <input type="password" name="app_password" required autocomplete="new-password" placeholder="<?php echo esc_attr($strings['field_password_placeholder_email']); ?>" data-email-placeholder="<?php echo esc_attr($strings['field_password_placeholder_email']); ?>" data-gmail-placeholder="<?php echo esc_attr($strings['field_password_placeholder_gmail']); ?>" data-outlook-placeholder="<?php echo esc_attr($strings['field_password_placeholder_outlook']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_daily_limit']); ?></span>
                                <input type="number" name="daily_limit" required min="1" max="2000" value="100">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_smtp_host']); ?></span>
                                <input type="text" name="smtp_host" required placeholder="mail.example.com" data-email-value="" data-gmail-value="smtp.gmail.com" data-outlook-value="smtp.office365.com">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_smtp_port']); ?></span>
                                <select name="smtp_port" required>
                                    <option value="587">587 - TLS</option>
                                    <option value="465">465 - SSL</option>
                                    <option value="25">25</option>
                                    <option value="2525">2525</option>
                                </select>
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_encryption']); ?></span>
                                <select name="encryption" required>
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none"><?php echo esc_html($strings['encryption_none']); ?></option>
                                </select>
                            </label>
                        </div>

                        <p class="smark-email-help" data-smark-provider-text data-email-text="<?php echo esc_attr($strings['email_help']); ?>" data-gmail-text="<?php echo esc_attr($strings['gmail_help']); ?>" data-outlook-text="<?php echo esc_attr($strings['outlook_help']); ?>"><?php echo esc_html($strings['email_help']); ?></p>

                        <div class="smark-email-form-actions">
                            <button type="submit" class="button button-primary"><?php echo esc_html($strings['save_button']); ?></button>
                        </div>
                    </form>
                </section>

                <section class="seo-step-card seo-step-card--full smark-email-accounts-card" data-step="strategy" data-smark-email-account-section>
                    <header class="seo-step-header smark-email-accounts-list-header">
                        <div>
                            <h2><?php echo esc_html($strings['list_title']); ?></h2>
                            <p><?php echo esc_html($strings['list_description']); ?></p>
                        </div>
                    </header>

                    <?php if (empty($accounts)) : ?>
                        <div class="smark-email-empty">
                            <?php echo esc_html($strings['empty_state']); ?>
                        </div>
                    <?php else : ?>
                        <div class="smark-email-table-wrap">
                            <table class="smark-email-accounts-table">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html($strings['column_label']); ?></th>
                                        <th><?php echo esc_html($strings['column_email']); ?></th>
                                        <th><?php echo esc_html($strings['column_smtp']); ?></th>
                                        <th><?php echo esc_html($strings['column_daily_limit']); ?></th>
                                        <th><?php echo esc_html($strings['column_status']); ?></th>
                                        <th><?php echo esc_html($strings['column_actions']); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($accounts as $account) : ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html($account['account_label']); ?></strong>
                                                <small><?php echo esc_html($account['sender_name']); ?></small>
                                            </td>
                                            <td><?php echo esc_html($account['email_address']); ?></td>
                                            <td><?php echo esc_html($account['smtp_host'] . ':' . $account['smtp_port'] . ' / ' . strtoupper($account['encryption'])); ?></td>
                                            <td>
                                                <?php
                                                $daily_limit = max(0, (int) $account['daily_limit']);
                                                $sent_today = isset($daily_sent_counts[$account['id']]) ? (int) $daily_sent_counts[$account['id']] : 0;
                                                $is_daily_limit_reached = $daily_limit > 0 && $sent_today >= $daily_limit;
                                                ?>
                                                <span class="smark-email-daily-usage<?php echo $is_daily_limit_reached ? ' is-limit-reached' : ''; ?>">
                                                    <span class="smark-email-daily-usage__sent" title="<?php echo esc_attr($strings['daily_sent_tooltip']); ?>"><?php echo esc_html(number_format_i18n($sent_today)); ?></span>
                                                    <span class="smark-email-daily-usage__separator" aria-hidden="true">/</span>
                                                    <span class="smark-email-daily-usage__limit" title="<?php echo esc_attr($strings['daily_limit_tooltip']); ?>"><?php echo esc_html(number_format_i18n($daily_limit)); ?></span>
                                                </span>
                                            </td>
                                            <td><span class="smark-email-status"><?php echo esc_html($strings['status_active']); ?></span></td>
                                            <td>
                                                <div class="smark-email-action-row">
                                                    <button
                                                        type="button"
                                                        class="button smark-email-edit-button"
                                                        data-open-smark-account-edit
                                                        data-account-id="<?php echo esc_attr($account['id']); ?>"
                                                        data-provider="<?php echo esc_attr($account['provider'] ?? 'email'); ?>"
                                                        data-account-label="<?php echo esc_attr($account['account_label']); ?>"
                                                        data-sender-name="<?php echo esc_attr($account['sender_name']); ?>"
                                                        data-email-address="<?php echo esc_attr($account['email_address']); ?>"
                                                        data-daily-limit="<?php echo esc_attr((int) $account['daily_limit']); ?>"
                                                        data-smtp-host="<?php echo esc_attr($account['smtp_host']); ?>"
                                                        data-smtp-port="<?php echo esc_attr((int) $account['smtp_port']); ?>"
                                                        data-encryption="<?php echo esc_attr($account['encryption']); ?>"
                                                    ><?php echo esc_html($strings['edit_button']); ?></button>
                                                    <form class="smark-email-inline-action" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js($strings['delete_confirm']); ?>');">
                                                        <?php wp_nonce_field('smark_email_account_delete', 'smark_email_account_nonce'); ?>
                                                        <input type="hidden" name="action" value="smark_email_account_delete">
                                                        <input type="hidden" name="account_id" value="<?php echo esc_attr($account['id']); ?>">
                                                        <button type="submit" class="button smark-email-delete-button"><?php echo esc_html($strings['delete_button']); ?></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <?php $this->render_email_account_edit_modal($strings); ?>
            </div>

            <?php $this->render_version_footer($current_lang); ?>
        </div>
        <?php
    }

    public function ajax_dashboard_email_accounts_view() {
        check_ajax_referer('smark_email_accounts_ajax', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array(
                'message' => esc_html__('You do not have sufficient permissions to access this page.', 'smark'),
            ), 403);
        }

        ob_start();
        $this->render_email_accounts_page();
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
        ));
    }

    public function render_contacts_page() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'));
        }

        $current_lang = get_option('smark_panel_language', 'en');
        $current_lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';
        $strings = $this->get_contact_strings($current_lang);
        $contacts = $this->get_contacts();
        $contact_lists = $this->get_contact_lists();
        $contact_tags = $this->get_contact_tags();
        $daily_sent_hashes = $this->get_daily_sent_contact_hashes();
        $message = isset($_GET['smark_message']) ? sanitize_key(wp_unslash($_GET['smark_message'])) : '';
        $import_token = isset($_GET['import_token']) ? sanitize_key(wp_unslash($_GET['import_token'])) : '';
        $import_preview = $import_token ? $this->get_contacts_import_payload($import_token) : array();
        $notice = $this->get_contact_notice($message, $strings);
        ?>
        <div
            class="wrap smark-seo-optimization-page <?php echo esc_attr($rtl_class); ?>"
            data-lang="<?php echo esc_attr($current_lang); ?>"
            data-smark-notice-message="<?php echo esc_attr($notice['message']); ?>"
            data-smark-notice-type="<?php echo esc_attr($notice['type']); ?>"
            data-smark-open-import="<?php echo !empty($import_preview['rows']) ? '1' : '0'; ?>"
        >
            <?php $this->render_standard_header($strings, $current_lang, $rtl_class, true); ?>

            <div class="seo-grid">
                <section class="seo-step-card seo-step-card--full smark-email-accounts-card" data-step="strategy" data-smark-contact-section>
                    <header class="seo-step-header smark-email-card-header-actions smark-email-contact-lists-header">
                        <div>
                            <h2><?php echo esc_html($strings['lists_title']); ?></h2>
                            <p><?php echo esc_html($strings['lists_description']); ?></p>
                        </div>
                        <button type="button" class="button button-primary" data-open-smark-contact-list>
                            <?php echo esc_html($strings['add_list_button']); ?>
                        </button>
                    </header>

                    <?php $this->render_contact_lists_content($strings, $contacts, $contact_lists); ?>
                </section>

                <section class="seo-step-card seo-step-card--full smark-email-accounts-card" data-step="strategy" data-smark-contact-section>
                    <header class="seo-step-header smark-email-card-header-actions smark-email-contact-tags-header">
                        <div>
                            <h2><?php echo esc_html($strings['tags_title']); ?></h2>
                            <p><?php echo esc_html($strings['tags_description']); ?></p>
                        </div>
                        <button type="button" class="button button-primary" data-open-smark-contact-tag>
                            <?php echo esc_html($strings['add_tag_button']); ?>
                        </button>
                    </header>

                    <?php $this->render_contact_tags_content($strings, $contacts, $contact_tags, $daily_sent_hashes); ?>
                </section>

                <section class="seo-step-card seo-step-card--full smark-email-accounts-card" data-step="strategy" data-smark-contact-section>
                    <header class="seo-step-header smark-email-saved-contacts-header">
                        <div>
                            <h2><?php echo esc_html($strings['list_title']); ?></h2>
                            <p><?php echo esc_html($strings['list_description']); ?></p>
                        </div>
                        <div class="smark-email-contacts-search-bar">
                            <input type="search" data-smark-contacts-search placeholder="<?php echo esc_attr($strings['contacts_search_placeholder']); ?>" autocomplete="off" aria-label="<?php echo esc_attr($strings['contacts_search_aria']); ?>">
                        </div>
                        <div class="smark-email-contacts-actions-wrapper" style="display: flex; gap: 8px; align-items: center;">
                            <span class="smark-email-contacts-count-badge">
                                <?php echo esc_html(sprintf($strings['contacts_count_badge'], number_format_i18n(count($contacts)))); ?>
                            </span>
                            <button type="button" class="button button-primary" data-open-smark-contact-add>
                                <?php echo esc_html($strings['save_button']); ?>
                            </button>
                            <button type="button" class="button button-primary smark-email-open-import" data-open-smark-import>
                                <?php echo esc_html($strings['bulk_button']); ?>
                            </button>
                        </div>
                    </header>

                    <div id="smarkEmailContactsList">
                        <?php $this->render_contacts_list_content($strings, $contacts, $contact_lists, $contact_tags, $daily_sent_hashes); ?>
                    </div>
                </section>

                <?php $this->render_contact_add_modal($strings, $contact_lists, $contact_tags); ?>
                <?php $this->render_contacts_import_modal($strings, $import_token, $import_preview); ?>
                <?php $this->render_contact_list_modal($strings); ?>
                <?php $this->render_contact_tag_modal($strings); ?>
            </div>

            <?php $this->render_contact_edit_modal($strings, $contact_lists, $contact_tags); ?>

            <?php $this->render_version_footer($current_lang); ?>
        </div>
        <?php
    }

    public function ajax_dashboard_email_contacts_view() {
        check_ajax_referer('smark_email_contacts_page_ajax', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array(
                'message' => esc_html__('You do not have sufficient permissions to access this page.', 'smark'),
            ), 403);
        }

        foreach (array('smark_message', 'import_token') as $key) {
            if (isset($_POST[$key])) {
                $_GET[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
            }
        }

        ob_start();
        $this->render_contacts_page();
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
        ));
    }

    public function ajax_dashboard_email_campaign_message_view() {
        check_ajax_referer('smark_email_campaign_message_ajax', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array(
                'message' => esc_html__('You do not have sufficient permissions to access this page.', 'smark'),
            ), 403);
        }

        ob_start();
        $this->render_campaign_message_page();
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
        ));
    }

    public function render_campaign_message_page() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'));
        }

        $current_lang = get_option('smark_panel_language', 'en');
        $current_lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';
        $strings = $this->get_campaign_message_strings($current_lang);
        $contacts = $this->get_contacts();
        $contact_lists = $this->get_contact_lists();
        $contact_tags = $this->get_contact_tags();
        $accounts = $this->get_email_accounts();
        $messages = $this->get_campaign_messages();
        $editing_message_id = isset($_GET['edit_message']) ? sanitize_text_field(wp_unslash($_GET['edit_message'])) : '';
        $editing_message = $editing_message_id !== '' ? $this->get_campaign_message_by_id($editing_message_id) : array();
        $is_editing = !empty($editing_message);
        $form_values = array_merge(
            array(
                'id'                => '',
                'campaign_name'     => '',
                'sender_account_id' => '',
                'sender_account_ids'=> array(),
                'subject_line'      => '',
                'preview_text'      => '',
                'reply_to'          => '',
                'message_status'    => 'draft',
                'target_segments'   => array(),
                'target_contacts'   => array(),
                'target_includes'   => array(),
                'target_excludes'   => array(),
                'email_body'        => '',
                'internal_notes'    => '',
            ),
            $editing_message
        );
        $form_values['target_includes'] = $this->normalize_campaign_audience_tokens($form_values['target_includes']);
        $form_values['target_excludes'] = $this->normalize_campaign_audience_tokens($form_values['target_excludes']);
        $form_values['message_status'] = (!empty($form_values['sent_at']) || $form_values['message_status'] === 'sent') ? 'sent' : 'draft';
        if (empty($form_values['target_includes']) && (!empty($form_values['target_segments']) || !empty($form_values['target_contacts']))) {
            $form_values['target_includes'] = $this->get_legacy_campaign_audience_tokens($form_values);
        }
        $form_values['sender_account_ids'] = $this->get_campaign_sender_account_ids($form_values);
        if (empty($form_values['sender_account_ids']) && !empty($form_values['sender_account_id'])) {
            $form_values['sender_account_ids'] = array((string) $form_values['sender_account_id']);
        }
        if (empty($form_values['sender_account_ids']) && !empty($accounts[0]['id'])) {
            $form_values['sender_account_id'] = (string) $accounts[0]['id'];
            $form_values['sender_account_ids'] = array((string) $accounts[0]['id']);
        }
        $daily_sent_counts = $this->get_email_account_daily_sent_counts();
        $admin_email = $this->get_site_admin_email_for_test();
        $message = isset($_GET['smark_message']) ? sanitize_key(wp_unslash($_GET['smark_message'])) : '';
        $notice = $this->get_basic_notice($message, $strings);
        ?>
        <div
            class="wrap smark-seo-optimization-page <?php echo esc_attr($rtl_class); ?>"
            data-lang="<?php echo esc_attr($current_lang); ?>"
            data-smark-notice-message="<?php echo esc_attr($notice['message']); ?>"
            data-smark-notice-type="<?php echo esc_attr($notice['type']); ?>"
        >
            <?php $this->render_standard_header($strings, $current_lang, $rtl_class, true); ?>

            <div class="seo-grid">
                <section class="seo-step-card seo-step-card--full" data-step="strategy" data-smark-campaign-message-section>
                    <header class="seo-step-header smark-email-campaign-message-header">
                        <div>
                            <h2><?php echo esc_html($strings['form_title']); ?></h2>
                            <p><?php echo esc_html($strings['form_description']); ?></p>
                        </div>
                    </header>

                    <form class="smark-email-account-form" id="smarkEmailCampaignMessageForm" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('smark_email_campaign_message_save', 'smark_email_campaign_message_nonce'); ?>
                        <input type="hidden" name="action" value="smark_email_campaign_message_save">
                        <input type="hidden" name="message_id" value="<?php echo esc_attr($form_values['id']); ?>">

                        <div class="smark-email-form-grid">
                            <label>
                                <span><?php echo esc_html($strings['field_campaign_name']); ?></span>
                                <input type="text" name="campaign_name" required value="<?php echo esc_attr($form_values['campaign_name']); ?>" placeholder="<?php echo esc_attr($strings['field_campaign_name_placeholder']); ?>">
                            </label>

                            <div class="smark-email-form-field">
                                <span><?php echo esc_html($strings['field_sender_account']); ?></span>
                                <div class="smark-email-sender-picker" data-smark-sender-picker>
                                    <button type="button" class="smark-email-sender-picker__trigger" data-smark-sender-picker-toggle aria-expanded="false">
                                        <span data-smark-sender-picker-summary><?php echo esc_html($strings['field_sender_account_empty']); ?></span>
                                        <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                    </button>
                                    <div class="smark-email-sender-picker__panel" data-smark-sender-picker-panel hidden>
                                        <div class="smark-email-sender-picker__inputs" data-smark-sender-picker-inputs></div>
                                        <ul class="smark-email-sender-picker__list">
                                    <?php foreach ($accounts as $account) : ?>
                                        <?php
                                        $account_id = isset($account['id']) ? (string) $account['id'] : '';
                                        $daily_limit = max(0, (int) ($account['daily_limit'] ?? 0));
                                        $sent_today = isset($daily_sent_counts[$account_id]) ? (int) $daily_sent_counts[$account_id] : 0;
                                        $remaining_today = max(0, $daily_limit - $sent_today);
                                        $account_label = $account['account_label'] . ' - ' . $account['email_address'];
                                        $capacity_label = number_format_i18n($remaining_today) . ' ' . $strings['sender_capacity_remaining_suffix'];
                                        ?>
                                            <li>
                                                <label class="smark-email-sender-picker__option">
                                                    <input
                                                        type="checkbox"
                                                        value="<?php echo esc_attr($account_id); ?>"
                                                        <?php checked(in_array($account_id, (array) $form_values['sender_account_ids'], true)); ?>
                                                        data-smark-sender-account-option
                                                        data-label="<?php echo esc_attr($account_label); ?>"
                                                        data-capacity-label="<?php echo esc_attr($capacity_label); ?>"
                                                        data-remaining="<?php echo esc_attr($remaining_today); ?>"
                                                        data-sent="<?php echo esc_attr($sent_today); ?>"
                                                        data-limit="<?php echo esc_attr($daily_limit); ?>"
                                                    >
                                                    <span class="smark-email-sender-picker__check" aria-hidden="true"></span>
                                                    <span class="smark-email-sender-picker__content">
                                                        <strong><?php echo esc_html($account_label); ?></strong>
                                                        <small><?php echo esc_html($capacity_label); ?></small>
                                                    </span>
                                                </label>
                                            </li>
                                    <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                                <small class="smark-email-field-note"><?php echo esc_html($strings['field_sender_account_help']); ?></small>
                                <small class="smark-email-capacity-warning" data-smark-capacity-warning data-warning-template="<?php echo esc_attr($strings['sender_capacity_warning']); ?>" hidden></small>
                            </div>

                            <label>
                                <span><?php echo esc_html($strings['field_subject']); ?></span>
                                <input type="text" name="subject_line" required value="<?php echo esc_attr($form_values['subject_line']); ?>" placeholder="<?php echo esc_attr($strings['field_subject_placeholder']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_preview_text']); ?></span>
                                <input type="text" name="preview_text" maxlength="160" value="<?php echo esc_attr($form_values['preview_text']); ?>" placeholder="<?php echo esc_attr($strings['field_preview_text_placeholder']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_reply_to']); ?></span>
                                <input type="email" name="reply_to" value="<?php echo esc_attr($form_values['reply_to']); ?>" placeholder="reply@example.com">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_status']); ?></span>
                                <select name="message_status">
                                    <option value="draft" <?php selected($form_values['message_status'], 'draft'); ?>><?php echo esc_html($strings['status_draft']); ?></option>
                                    <option value="sent" <?php selected($form_values['message_status'], 'sent'); ?>><?php echo esc_html($strings['status_sent']); ?></option>
                                </select>
                            </label>

                            <?php $this->render_campaign_audience_picker_field('include', $strings, $form_values['target_includes']); ?>

                            <?php $this->render_campaign_audience_picker_field('exclude', $strings, $form_values['target_excludes']); ?>

                            <div class="smark-email-form-field--wide smark-email-editor-field">
                                <span><?php echo esc_html($strings['field_body']); ?></span>
                                <?php
                                wp_editor(
                                    $form_values['email_body'],
                                    'smark_email_body_editor',
                                    array(
                                        'textarea_name' => 'email_body',
                                        'textarea_rows' => 12,
                                        'media_buttons' => false,
                                        'teeny' => false,
                                        'quicktags' => true,
                                        'tinymce' => array(
                                            'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,undo,redo',
                                            'toolbar2' => 'strikethrough,hr,pastetext,removeformat,charmap,outdent,indent,wp_adv',
                                        ),
                                    )
                                );
                                ?>
                            </div>

                            <label class="smark-email-form-field--wide">
                                <span><?php echo esc_html($strings['field_notes']); ?></span>
                                <textarea name="internal_notes" rows="3" placeholder="<?php echo esc_attr($strings['field_notes_placeholder']); ?>"><?php echo esc_textarea($form_values['internal_notes']); ?></textarea>
                            </label>
                        </div>

                        <p class="smark-email-help"><?php echo esc_html($strings['form_help']); ?></p>

                        <div class="smark-email-test-send-box" data-smark-test-send-box hidden>
                            <div>
                                <strong><?php echo esc_html($strings['test_send_confirm_title']); ?></strong>
                                <p><?php echo esc_html($strings['test_send_confirm_message']); ?></p>
                                <label class="smark-email-test-send-recipient">
                                    <span><?php echo esc_html($strings['test_send_email_label']); ?></span>
                                    <input type="email" data-smark-test-send-email value="<?php echo esc_attr($admin_email); ?>" placeholder="<?php echo esc_attr($strings['test_send_email_placeholder']); ?>">
                                </label>
                            </div>
                            <div class="smark-email-test-send-box__actions">
                                <button type="button" class="button button-primary" data-smark-confirm-test-send>
                                    <?php echo esc_html($strings['test_send_confirm_button']); ?>
                                </button>
                                <button type="button" class="button smark-email-secondary-action" data-smark-cancel-test-send>
                                    <?php echo esc_html($strings['test_send_cancel_button']); ?>
                                </button>
                            </div>
                        </div>

                        <div class="smark-email-form-actions">
                            <button type="submit" name="campaign_action" value="save" class="button button-primary"><?php echo esc_html($is_editing ? $strings['update_button'] : $strings['save_button']); ?></button>
                            <button type="submit" name="campaign_action" value="send_now" class="button smark-email-secondary-action"><?php echo esc_html($strings['quick_send_button']); ?></button>
                            <button type="button" class="button smark-email-test-send-action" data-smark-open-test-send><?php echo esc_html($strings['test_send_button']); ?></button>
                        </div>
                    </form>
                </section>

                <section class="seo-step-card seo-step-card--full smark-email-accounts-card" id="smarkEmailCampaignSavedMessages" data-step="strategy" data-smark-campaign-message-section data-smark-campaign-messages-list>
                    <header class="seo-step-header smark-email-campaign-messages-list-header">
                        <div>
                            <h2><?php echo esc_html($strings['list_title']); ?></h2>
                            <p><?php echo esc_html($strings['list_description']); ?></p>
                        </div>
                    </header>

                    <div id="smarkEmailCampaignMessagesList">
                        <?php $this->render_campaign_messages_list_content($strings, $messages); ?>
                    </div>
                </section>

                <?php $this->render_campaign_audience_picker_modal($strings, $contacts, $contact_lists, $contact_tags); ?>
                <?php $this->render_campaign_message_edit_modal($strings, $accounts, $daily_sent_counts); ?>
            </div>

            <?php $this->render_version_footer($current_lang); ?>
            <?php $this->render_campaign_send_progress_modal($strings); ?>
            <?php $this->render_campaign_failure_detail_modal($this->get_performance_strings($current_lang)); ?>
        </div>
        <?php
    }

    public function render_performance_page() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'));
        }

        $current_lang = get_option('smark_panel_language', 'en');
        $current_lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';
        $strings = $this->get_performance_strings($current_lang);
        $message_strings = $this->get_campaign_message_strings($current_lang);
        $modal_strings = array_merge($message_strings, $strings);
        $messages = $this->get_campaign_messages();
        $selected_campaign_id = isset($_GET['campaign_id']) ? sanitize_text_field(wp_unslash($_GET['campaign_id'])) : '';
        if ($selected_campaign_id === '' && isset($_POST['campaign_id'])) {
            $selected_campaign_id = sanitize_text_field(wp_unslash($_POST['campaign_id']));
        }

        if ($selected_campaign_id === '' && !empty($messages[0]['id'])) {
            $selected_campaign_id = (string) $messages[0]['id'];
        }

        $overall = $this->get_campaign_performance_metrics('');
        $selected_metrics = $selected_campaign_id !== '' ? $this->get_campaign_performance_metrics($selected_campaign_id) : array();
        $selected_campaign = $selected_campaign_id !== '' ? $this->get_campaign_message_by_id($selected_campaign_id) : array();
        $accounts = $this->get_email_accounts();
        $retry_failures = $this->get_unresolved_campaign_failures('');
        ?>
        <div class="wrap smark-seo-optimization-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
            <?php $this->render_standard_header($strings, $current_lang, $rtl_class, true); ?>

            <div class="seo-grid">
                <section class="seo-step-card seo-step-card--full smark-email-performance-card" data-step="strategy" data-smark-performance-section>
                    <header class="seo-step-header smark-email-performance-header">
                        <div>
                            <h2><?php echo esc_html($strings['overview_title']); ?></h2>
                            <p><?php echo esc_html($strings['overview_description']); ?></p>
                        </div>
                    </header>

                    <div id="smarkEmailOverallStats">
                        <?php $this->render_overall_campaign_stats_grid($overall, $strings); ?>
                    </div>
                </section>

                <section class="seo-step-card seo-step-card--full" data-step="strategy" data-smark-performance-section>
                    <header class="seo-step-header smark-email-performance-header">
                        <div>
                            <h2><?php echo esc_html($strings['campaign_title']); ?></h2>
                            <p><?php echo esc_html($strings['campaign_description']); ?></p>
                        </div>
                    </header>

                    <?php if (empty($messages)) : ?>
                        <div class="smark-email-empty"><?php echo esc_html($strings['empty_state']); ?></div>
                    <?php else : ?>
                        <form class="smark-email-account-form smark-email-performance-filter" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                            <input type="hidden" name="page" value="smark-email-performance">
                            <label>
                                <span><?php echo esc_html($strings['field_campaign']); ?></span>
                                <select name="campaign_id" data-smark-performance-campaign-select>
                                    <?php foreach ($messages as $message) : ?>
                                        <option value="<?php echo esc_attr($message['id']); ?>" <?php selected($selected_campaign_id, $message['id']); ?>>
                                            <?php echo esc_html($message['campaign_name'] . ' - ' . $message['subject_line']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </form>

                        <?php if (!empty($selected_campaign)) : ?>
                            <?php $this->render_campaign_performance_detail($selected_campaign, $strings, $message_strings); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>

            <?php $this->render_version_footer($current_lang); ?>
            <?php $this->render_campaign_failure_retry_modal($modal_strings, $accounts, count($retry_failures)); ?>
            <?php $this->render_campaign_send_progress_modal($modal_strings); ?>
            <?php $this->render_campaign_failure_detail_modal($strings); ?>
        </div>
        <?php
    }

    public function ajax_dashboard_email_performance_view() {
        check_ajax_referer('smark_email_performance_ajax', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array(
                'message' => esc_html__('You do not have sufficient permissions to access this page.', 'smark'),
            ), 403);
        }

        ob_start();
        $this->render_performance_page();
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
        ));
    }

    private function render_campaign_messages_list_content($strings, $messages) {
        if (empty($messages)) {
            ?>
            <div class="smark-email-empty">
                <?php echo esc_html($strings['empty_state']); ?>
            </div>
            <?php
            return;
        }
        $current_lang = $this->get_current_panel_lang();
        $performance_strings = $this->get_performance_strings($current_lang);
        ?>
        <div class="smark-email-table-wrap">
            <table class="smark-email-accounts-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html($strings['column_campaign']); ?></th>
                        <th><?php echo esc_html($strings['column_subject']); ?></th>
                        <th><?php echo esc_html($strings['column_audience']); ?></th>
                        <th><?php echo esc_html($strings['column_status']); ?></th>
                        <th><?php echo esc_html($strings['column_actions']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $campaign_message) : ?>
                        <?php
                        $message_status = isset($campaign_message['message_status']) ? sanitize_key((string) $campaign_message['message_status']) : 'draft';
                        $message_status = ($message_status === 'sent' || !empty($campaign_message['sent_at'])) ? 'sent' : 'draft';
                        $message_sent_at = isset($campaign_message['sent_at']) ? trim((string) $campaign_message['sent_at']) : '';
                        $campaign_was_sent = ($message_status === 'sent' || $message_sent_at !== '');
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($campaign_message['campaign_name']); ?></strong>
                                <small><?php echo esc_html($campaign_message['created_at']); ?></small>
                            </td>
                            <td>
                                <?php echo esc_html($campaign_message['subject_line']); ?>
                                <?php if (!empty($campaign_message['preview_text'])) : ?>
                                    <small><?php echo esc_html($campaign_message['preview_text']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($this->format_campaign_audience_summary($campaign_message, $strings)); ?></td>
                            <td><span class="smark-email-status smark-email-status--<?php echo esc_attr($message_status); ?>"><?php echo esc_html($this->get_campaign_status_label($message_status, $strings)); ?></span></td>
                            <td>
                                <div class="smark-email-action-row smark-email-campaign-action-row">
                                    <button type="button" class="button smark-email-edit-button" data-open-smark-campaign-edit="<?php echo esc_attr($campaign_message['id']); ?>">
                                        <?php echo esc_html($strings['edit_button']); ?>
                                    </button>
                                    <button type="button" class="button smark-email-performance-button" data-open-smark-campaign-performance="<?php echo esc_attr($campaign_message['id']); ?>">
                                        <?php echo esc_html($strings['performance_button']); ?>
                                    </button>
                                    <?php if (!$campaign_was_sent) : ?>
                                        <form class="smark-email-inline-action smark-email-campaign-send-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php wp_nonce_field('smark_email_campaign_message_send', 'smark_email_campaign_message_nonce'); ?>
                                            <input type="hidden" name="action" value="smark_email_campaign_message_send">
                                            <input type="hidden" name="message_id" value="<?php echo esc_attr($campaign_message['id']); ?>">
                                            <button type="submit" class="button smark-email-send-button"><?php echo esc_html($strings['send_button']); ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <form class="smark-email-inline-action" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js($strings['delete_confirm']); ?>');">
                                        <?php wp_nonce_field('smark_email_campaign_message_delete', 'smark_email_campaign_message_nonce'); ?>
                                        <input type="hidden" name="action" value="smark_email_campaign_message_delete">
                                        <input type="hidden" name="message_id" value="<?php echo esc_attr($campaign_message['id']); ?>">
                                        <button type="submit" class="button smark-email-delete-button"><?php echo esc_html($strings['delete_button']); ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php foreach ($messages as $campaign_message) : ?>
            <script type="application/json" data-smark-campaign-message-json="<?php echo esc_attr($campaign_message['id']); ?>">
                <?php echo wp_json_encode($this->prepare_campaign_message_for_edit_json($campaign_message), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
            </script>
        <?php endforeach; ?>
        <?php foreach ($messages as $campaign_message) : ?>
            <section class="seo-step-card seo-step-card--full smark-email-accounts-card smark-email-campaign-performance-modal smark-email-campaign-performance-section" id="smarkEmailCampaignPerformanceModal-<?php echo esc_attr($campaign_message['id']); ?>" data-step="strategy" aria-hidden="true" hidden>
                    <header class="seo-step-header smark-email-card-header-actions smark-email-contact-workflow-header smark-email-campaign-performance-header">
                        <div>
                            <h2 id="smarkEmailCampaignPerformanceTitle-<?php echo esc_attr($campaign_message['id']); ?>"><?php echo esc_html($performance_strings['campaign_title']); ?></h2>
                            <p><?php echo esc_html($performance_strings['campaign_description']); ?></p>
                        </div>
                        <button type="button" class="smark-email-inline-panel__close" data-close-smark-campaign-performance aria-label="<?php echo esc_attr($strings['performance_close']); ?>">
                            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                        </button>
                    </header>
                    <div class="smark-email-inline-panel__body">
                        <?php $this->render_campaign_performance_detail($campaign_message, $performance_strings, $strings); ?>
                    </div>
            </section>
        <?php endforeach; ?>
        <?php
    }

    private function prepare_campaign_message_for_edit_json($message) {
        $message = is_array($message) ? $message : array();
        $message['target_includes'] = $this->normalize_campaign_audience_tokens($message['target_includes'] ?? array());
        $message['target_excludes'] = $this->normalize_campaign_audience_tokens($message['target_excludes'] ?? array());
        if (empty($message['target_includes']) && (!empty($message['target_segments']) || !empty($message['target_contacts']))) {
            $message['target_includes'] = $this->get_legacy_campaign_audience_tokens($message);
        }
        $message['sender_account_ids'] = $this->get_campaign_sender_account_ids($message);
        $message_status = (!empty($message['sent_at']) || (isset($message['message_status']) && (string) $message['message_status'] === 'sent')) ? 'sent' : 'draft';

        return array(
            'id' => (string) ($message['id'] ?? ''),
            'campaign_name' => (string) ($message['campaign_name'] ?? ''),
            'sender_account_ids' => array_values(array_map('strval', $message['sender_account_ids'])),
            'subject_line' => (string) ($message['subject_line'] ?? ''),
            'preview_text' => (string) ($message['preview_text'] ?? ''),
            'reply_to' => (string) ($message['reply_to'] ?? ''),
            'message_status' => $message_status,
            'target_includes' => array_values(array_map('strval', $message['target_includes'])),
            'target_excludes' => array_values(array_map('strval', $message['target_excludes'])),
            'email_body' => (string) ($message['email_body'] ?? ''),
            'internal_notes' => (string) ($message['internal_notes'] ?? ''),
        );
    }

    private function render_campaign_message_edit_modal($strings, $accounts, $daily_sent_counts) {
        ?>
        <section class="seo-step-card seo-step-card--full smark-email-accounts-card smark-email-campaign-edit-modal smark-email-campaign-edit-section" id="smarkEmailCampaignEditModal" data-step="strategy" aria-hidden="true" hidden>
                <header class="seo-step-header smark-email-card-header-actions smark-email-contact-workflow-header smark-email-campaign-edit-header">
                    <div>
                        <h2 id="smarkEmailCampaignEditTitle"><?php echo esc_html($strings['edit_modal_title']); ?></h2>
                        <p><?php echo esc_html($strings['edit_modal_description']); ?></p>
                    </div>
                    <button type="button" class="smark-email-inline-panel__close" data-close-smark-campaign-edit aria-label="<?php echo esc_attr($strings['edit_modal_close']); ?>">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </header>

                <div class="smark-email-inline-panel__body">
                    <form class="smark-email-account-form" id="smarkEmailCampaignMessageEditForm" method="post">
                        <?php wp_nonce_field('smark_email_campaign_message_save', 'smark_email_campaign_message_nonce'); ?>
                        <input type="hidden" name="action" value="smark_email_campaign_message_save_modal">
                        <input type="hidden" name="message_id" value="">

                        <div class="smark-email-form-grid">
                            <label>
                                <span><?php echo esc_html($strings['field_campaign_name']); ?></span>
                                <input type="text" name="campaign_name" required placeholder="<?php echo esc_attr($strings['field_campaign_name_placeholder']); ?>">
                            </label>

                            <div class="smark-email-form-field">
                                <span><?php echo esc_html($strings['field_sender_account']); ?></span>
                                <div class="smark-email-sender-picker" data-smark-sender-picker>
                                    <button type="button" class="smark-email-sender-picker__trigger" data-smark-sender-picker-toggle aria-expanded="false">
                                        <span data-smark-sender-picker-summary><?php echo esc_html($strings['field_sender_account_empty']); ?></span>
                                        <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                    </button>
                                    <div class="smark-email-sender-picker__panel" data-smark-sender-picker-panel hidden>
                                        <div class="smark-email-sender-picker__inputs" data-smark-sender-picker-inputs></div>
                                        <ul class="smark-email-sender-picker__list">
                                    <?php foreach ($accounts as $account) : ?>
                                        <?php
                                        $account_id = isset($account['id']) ? (string) $account['id'] : '';
                                        $daily_limit = max(0, (int) ($account['daily_limit'] ?? 0));
                                        $sent_today = isset($daily_sent_counts[$account_id]) ? (int) $daily_sent_counts[$account_id] : 0;
                                        $remaining_today = max(0, $daily_limit - $sent_today);
                                        $account_label = $account['account_label'] . ' - ' . $account['email_address'];
                                        $capacity_label = number_format_i18n($remaining_today) . ' ' . $strings['sender_capacity_remaining_suffix'];
                                        ?>
                                            <li>
                                                <label class="smark-email-sender-picker__option">
                                                    <input
                                                        type="checkbox"
                                                        value="<?php echo esc_attr($account_id); ?>"
                                                        data-smark-sender-account-option
                                                        data-label="<?php echo esc_attr($account_label); ?>"
                                                        data-capacity-label="<?php echo esc_attr($capacity_label); ?>"
                                                        data-remaining="<?php echo esc_attr($remaining_today); ?>"
                                                        data-sent="<?php echo esc_attr($sent_today); ?>"
                                                        data-limit="<?php echo esc_attr($daily_limit); ?>"
                                                    >
                                                    <span class="smark-email-sender-picker__check" aria-hidden="true"></span>
                                                    <span class="smark-email-sender-picker__content">
                                                        <strong><?php echo esc_html($account_label); ?></strong>
                                                        <small><?php echo esc_html($capacity_label); ?></small>
                                                    </span>
                                                </label>
                                            </li>
                                    <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                                <small class="smark-email-field-note"><?php echo esc_html($strings['field_sender_account_help']); ?></small>
                                <small class="smark-email-capacity-warning" data-smark-capacity-warning data-warning-template="<?php echo esc_attr($strings['sender_capacity_warning']); ?>" hidden></small>
                            </div>

                            <label>
                                <span><?php echo esc_html($strings['field_subject']); ?></span>
                                <input type="text" name="subject_line" required placeholder="<?php echo esc_attr($strings['field_subject_placeholder']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_preview_text']); ?></span>
                                <input type="text" name="preview_text" maxlength="160" placeholder="<?php echo esc_attr($strings['field_preview_text_placeholder']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_reply_to']); ?></span>
                                <input type="email" name="reply_to" placeholder="reply@example.com">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_status']); ?></span>
                                <select name="message_status">
                                    <option value="draft"><?php echo esc_html($strings['status_draft']); ?></option>
                                    <option value="sent"><?php echo esc_html($strings['status_sent']); ?></option>
                                </select>
                            </label>

                            <?php $this->render_campaign_audience_picker_field('include', $strings, array()); ?>
                            <?php $this->render_campaign_audience_picker_field('exclude', $strings, array()); ?>

                            <div class="smark-email-form-field--wide smark-email-editor-field">
                                <span><?php echo esc_html($strings['field_body']); ?></span>
                                <?php
                                wp_editor(
                                    '',
                                    'smark_email_edit_body_editor',
                                    array(
                                        'textarea_name' => 'email_body',
                                        'textarea_rows' => 12,
                                        'media_buttons' => false,
                                        'teeny' => false,
                                        'quicktags' => true,
                                        'tinymce' => array(
                                            'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,undo,redo',
                                            'toolbar2' => 'strikethrough,hr,pastetext,removeformat,charmap,outdent,indent,wp_adv',
                                        ),
                                    )
                                );
                                ?>
                            </div>

                            <label class="smark-email-form-field--wide">
                                <span><?php echo esc_html($strings['field_notes']); ?></span>
                                <textarea name="internal_notes" rows="3" placeholder="<?php echo esc_attr($strings['field_notes_placeholder']); ?>"></textarea>
                            </label>
                        </div>

                        <div class="smark-email-form-actions">
                            <button type="submit" class="button button-primary"><?php echo esc_html($strings['update_button']); ?></button>
                        </div>
                    </form>
                </div>
        </section>
        <?php
    }

    private function render_campaign_audience_picker_field($mode, $strings, $selected_tokens) {
        $mode = ($mode === 'exclude') ? 'exclude' : 'include';
        $field_name = $mode === 'include' ? 'target_includes[]' : 'target_excludes[]';
        $title = $mode === 'include' ? $strings['field_include_audience'] : $strings['field_exclude_audience'];
        $button = $mode === 'include' ? $strings['select_include_button'] : $strings['select_exclude_button'];
        $help = $mode === 'include' ? $strings['field_include_help'] : $strings['field_exclude_help'];
        ?>
        <div class="smark-email-form-field smark-email-audience-builder" data-smark-audience-builder="<?php echo esc_attr($mode); ?>">
            <span><?php echo esc_html($title); ?></span>
            <div class="smark-email-audience-builder__box" data-smark-audience-display>
                <button type="button" class="button smark-email-secondary-action" data-open-smark-audience-picker data-mode="<?php echo esc_attr($mode); ?>">
                    <?php echo esc_html($button); ?>
                </button>
                <div class="smark-email-audience-builder__chips" data-smark-audience-chips data-empty-text="<?php echo esc_attr($strings['audience_picker_empty']); ?>" data-more-text="<?php echo esc_attr($strings['audience_picker_more']); ?>"></div>
                <div class="smark-email-audience-builder__inputs" data-smark-audience-inputs data-field-name="<?php echo esc_attr($field_name); ?>">
                    <?php foreach ($this->normalize_campaign_audience_tokens($selected_tokens) as $token) : ?>
                        <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($token); ?>">
                    <?php endforeach; ?>
                </div>
            </div>
            <small class="smark-email-field-note"><?php echo esc_html($help); ?></small>
        </div>
        <?php
    }

    private function render_campaign_audience_picker_modal($strings, $contacts, $contact_lists, $contact_tags) {
        $today_sent_contact_ids = $this->get_today_sent_contact_ids($contacts);
        ?>
        <section class="seo-step-card seo-step-card--full smark-email-accounts-card smark-email-audience-modal smark-email-campaign-audience-section" id="smarkEmailAudiencePickerModal" data-step="strategy" aria-hidden="true" hidden>
            <header class="seo-step-header smark-email-card-header-actions smark-email-contact-workflow-header smark-email-campaign-audience-header">
                <div>
                    <h2 id="smarkEmailAudiencePickerTitle" data-include-title="<?php echo esc_attr($strings['audience_picker_include_title']); ?>" data-exclude-title="<?php echo esc_attr($strings['audience_picker_exclude_title']); ?>">
                        <?php echo esc_html($strings['audience_picker_include_title']); ?>
                    </h2>
                    <p><?php echo esc_html($strings['audience_picker_description']); ?></p>
                </div>
                <button type="button" class="smark-email-inline-panel__close" data-close-smark-audience-picker aria-label="<?php echo esc_attr($strings['audience_picker_close']); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </header>

            <div class="smark-email-inline-panel__body">
                    <div class="smark-email-audience-modal__section">
                        <h3><?php echo esc_html($strings['audience_picker_lists']); ?></h3>
                        <label class="smark-email-audience-option">
                            <input type="checkbox" value="all:all" data-label="<?php echo esc_attr($strings['system_list_all']); ?>" data-contact-ids="<?php echo esc_attr(implode(',', $this->get_contact_ids_from_contacts($contacts))); ?>" data-smark-audience-option>
                            <span><?php echo esc_html($strings['system_list_all']); ?></span>
                            <small><?php echo esc_html($strings['audience_picker_all_help']); ?></small>
                        </label>
                        <?php foreach ($contact_lists as $list) : ?>
                            <label class="smark-email-audience-option">
                                <input type="checkbox" value="<?php echo esc_attr('list:' . $list['id']); ?>" data-label="<?php echo esc_attr($list['name']); ?>" data-contact-ids="<?php echo esc_attr(implode(',', $this->get_assigned_contact_ids_for_entity($list))); ?>" data-smark-audience-option>
                                <span><?php echo esc_html($list['name']); ?></span>
                                <small><?php echo esc_html(sprintf($strings['assigned_contacts_count'], number_format_i18n(count($this->get_assigned_contact_ids_for_entity($list))))); ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="smark-email-audience-modal__section">
                        <h3><?php echo esc_html($strings['audience_picker_tags']); ?></h3>
                        <label class="smark-email-audience-option">
                            <input type="checkbox" value="system:today_sent" data-label="<?php echo esc_attr($strings['system_tag_today_sent']); ?>" data-contact-ids="<?php echo esc_attr(implode(',', $today_sent_contact_ids)); ?>" data-smark-audience-option>
                            <span><?php echo esc_html($strings['system_tag_today_sent']); ?></span>
                            <small><?php echo esc_html(sprintf($strings['system_tag_today_sent_help'], number_format_i18n(count($today_sent_contact_ids)))); ?></small>
                        </label>
                        <?php if (!empty($contact_tags)) : ?>
                            <?php foreach ($contact_tags as $tag) : ?>
                                <label class="smark-email-audience-option">
                                    <input type="checkbox" value="<?php echo esc_attr('tag:' . $tag['id']); ?>" data-label="<?php echo esc_attr($tag['name']); ?>" data-contact-ids="<?php echo esc_attr(implode(',', $this->get_assigned_contact_ids_for_entity($tag))); ?>" data-smark-audience-option>
                                    <span><?php echo esc_html($tag['name']); ?></span>
                                    <small><?php echo esc_html(sprintf($strings['assigned_contacts_count'], number_format_i18n(count($this->get_assigned_contact_ids_for_entity($tag))))); ?></small>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="smark-email-audience-modal__section smark-email-audience-modal__section--contacts">
                        <div class="smark-email-audience-contacts-header">
                            <h3><?php echo esc_html($strings['audience_picker_contacts']); ?></h3>
                            <div class="smark-email-audience-contacts-tools">
                                <span class="smark-email-audience-selected-count" data-smark-audience-selected-count data-template="<?php echo esc_attr($strings['audience_picker_selected_count']); ?>">
                                    <?php echo esc_html(sprintf($strings['audience_picker_selected_count'], number_format_i18n(0))); ?>
                                </span>
                                <input type="search" data-smark-audience-contact-search placeholder="<?php echo esc_attr($strings['audience_picker_search_placeholder']); ?>" autocomplete="off">
                            </div>
                        </div>
                        <?php foreach ($contacts as $contact) : ?>
                            <?php
                            $contact_name = trim((string) ($contact['first_name'] ?? '') . ' ' . (string) ($contact['last_name'] ?? ''));
                            $contact_label = ($contact_name !== '' ? $contact_name . ' - ' : '') . (string) ($contact['email_address'] ?? '');
                            $contact_search = trim($contact_label . ' ' . (string) ($contact['phone'] ?? '') . ' ' . (string) ($contact['source'] ?? '') . ' ' . (string) ($contact['status'] ?? 'subscribed'));
                            ?>
                            <label class="smark-email-audience-option" data-smark-audience-contact-row data-search="<?php echo esc_attr(strtolower($contact_search)); ?>">
                                <input type="checkbox" value="<?php echo esc_attr('contact:' . $contact['id']); ?>" data-label="<?php echo esc_attr($contact_label); ?>" data-contact-ids="<?php echo esc_attr((string) $contact['id']); ?>" data-status="<?php echo esc_attr((string) ($contact['status'] ?? 'subscribed')); ?>" data-smark-audience-option>
                                <span><?php echo esc_html($contact_label); ?></span>
                                <small><?php echo esc_html((string) ($contact['status'] ?? 'subscribed')); ?></small>
                            </label>
                        <?php endforeach; ?>
                        <p class="smark-email-audience-contact-limit-note" data-smark-audience-contact-limit-note><?php echo esc_html($strings['audience_picker_contact_limit_note']); ?></p>
                    </div>

                    <div class="smark-email-form-actions">
                        <button type="button" class="button button-primary" data-apply-smark-audience-picker><?php echo esc_html($strings['audience_picker_apply']); ?></button>
                    </div>
            </div>
        </section>
        <?php
    }

    private function render_campaign_send_progress_modal($strings) {
        ?>
        <div class="smark-email-import-modal smark-email-send-progress-modal" id="smarkEmailSendProgressModal" aria-hidden="true">
            <div class="smark-email-import-modal__overlay"></div>
            <div class="smark-email-import-modal__dialog smark-email-send-progress-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="smarkEmailSendProgressTitle">
                <header class="smark-email-import-modal__header">
                    <div>
                        <h2 id="smarkEmailSendProgressTitle"><?php echo esc_html($strings['send_progress_title']); ?></h2>
                        <p><?php echo esc_html($strings['send_progress_description']); ?></p>
                    </div>
                    <button type="button" class="smark-email-import-modal__close" data-close-smark-send-progress aria-label="<?php echo esc_attr($strings['send_progress_close']); ?>" hidden>
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </header>
                <div class="smark-email-import-modal__body">
                    <div class="smark-email-send-progress">
                        <div class="smark-email-send-progress__meta">
                            <strong data-smark-send-progress-status><?php echo esc_html($strings['send_progress_starting']); ?></strong>
                            <span data-smark-send-progress-percent>0%</span>
                        </div>
                        <div class="smark-email-send-progress__bar" aria-hidden="true">
                            <span data-smark-send-progress-bar style="width: 0%;"></span>
                        </div>
                        <p class="smark-email-send-progress__count" data-smark-send-progress-count>
                            <?php echo esc_html(sprintf($strings['send_progress_count'], number_format_i18n(0), number_format_i18n(0))); ?>
                        </p>
                    </div>

                    <div class="smark-email-send-progress__recent">
                        <h3><?php echo esc_html($strings['send_progress_recent_title']); ?></h3>
                        <ul data-smark-send-progress-recent data-empty-text="<?php echo esc_attr($strings['send_progress_recent_empty']); ?>">
                            <li><?php echo esc_html($strings['send_progress_recent_empty']); ?></li>
                        </ul>
                        <p><?php echo esc_html($strings['send_progress_final_note']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_campaign_failure_retry_modal($strings, $accounts, $failure_count) {
        $accounts = is_array($accounts) ? $accounts : array();
        ?>
        <div class="smark-email-import-modal smark-email-failure-retry-modal" id="smarkEmailFailureRetryModal" aria-hidden="true">
            <div class="smark-email-import-modal__overlay" data-close-smark-failure-retry></div>
            <div class="smark-email-import-modal__dialog smark-email-failure-retry-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="smarkEmailFailureRetryTitle">
                <header class="smark-email-import-modal__header">
                    <div>
                        <h2 id="smarkEmailFailureRetryTitle"><?php echo esc_html($strings['failure_retry_title']); ?></h2>
                        <p><?php echo esc_html(sprintf($strings['failure_retry_description'], number_format_i18n((int) $failure_count))); ?></p>
                    </div>
                    <button type="button" class="smark-email-import-modal__close" data-close-smark-failure-retry aria-label="<?php echo esc_attr($strings['failure_retry_close']); ?>">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </header>
                <div class="smark-email-import-modal__body">
                    <form class="smark-email-account-form smark-email-failure-retry-form" id="smarkEmailFailureRetryForm">
                        <div class="smark-email-form-grid">
                            <label>
                                <span><?php echo esc_html($strings['failure_retry_count_label']); ?></span>
                                <input type="number" min="1" max="<?php echo esc_attr(max(1, (int) $failure_count)); ?>" value="<?php echo esc_attr(min(100, max(1, (int) $failure_count))); ?>" name="retry_count" data-smark-failure-retry-count>
                            </label>
                            <label>
                                <span><?php echo esc_html($strings['failure_retry_account_label']); ?></span>
                                <select name="sender_account_id" data-smark-failure-retry-account>
                                    <option value=""><?php echo esc_html($strings['failure_retry_account_placeholder']); ?></option>
                                    <?php foreach ($accounts as $account) : ?>
                                        <?php
                                        $account_id = isset($account['id']) ? (string) $account['id'] : '';
                                        $account_email = isset($account['email_address']) ? sanitize_email((string) $account['email_address']) : '';
                                        $account_label = isset($account['account_label']) && (string) $account['account_label'] !== '' ? (string) $account['account_label'] : $account_email;
                                        if ($account_id === '' || $account_email === '') {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr($account_id); ?>"><?php echo esc_html($account_label . ' - ' . $account_email); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <input type="hidden" name="campaign_id" value="">
                        <div class="smark-email-form-actions">
                            <button type="submit" class="button button-primary" <?php disabled($failure_count <= 0 || empty($accounts)); ?>><?php echo esc_html($strings['failure_retry_start_button']); ?></button>
                            <button type="button" class="button" data-close-smark-failure-retry><?php echo esc_html($strings['failure_retry_cancel_button']); ?></button>
                        </div>
                    </form>

                    <div class="smark-email-send-progress smark-email-failure-retry-progress" data-smark-failure-retry-progress hidden>
                        <div class="smark-email-send-progress__meta">
                            <strong data-smark-failure-retry-status><?php echo esc_html($strings['failure_retry_starting']); ?></strong>
                            <span data-smark-failure-retry-percent>0%</span>
                        </div>
                        <div class="smark-email-send-progress__bar" aria-hidden="true">
                            <span data-smark-failure-retry-bar style="width: 0%;"></span>
                        </div>
                        <p class="smark-email-send-progress__count" data-smark-failure-retry-count-text>
                            <?php echo esc_html(sprintf($strings['send_progress_count'], number_format_i18n(0), number_format_i18n(0))); ?>
                        </p>
                    </div>

                    <div class="smark-email-send-progress__recent smark-email-failure-retry-reports" data-smark-failure-retry-reports-wrap hidden>
                        <h3><?php echo esc_html($strings['send_progress_recent_title']); ?></h3>
                        <ul data-smark-failure-retry-reports data-empty-text="<?php echo esc_attr($strings['send_progress_recent_empty']); ?>">
                            <li><?php echo esc_html($strings['send_progress_recent_empty']); ?></li>
                        </ul>
                        <p><?php echo esc_html($strings['send_progress_final_note']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_campaign_failure_detail_modal($strings) {
        ?>
        <div class="smark-email-import-modal smark-email-failure-detail-modal" id="smarkEmailFailureDetailModal" aria-hidden="true">
            <div class="smark-email-import-modal__overlay" data-close-smark-failure-detail></div>
            <div class="smark-email-import-modal__dialog smark-email-failure-detail-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="smarkEmailFailureDetailTitle">
                <header class="smark-email-import-modal__header">
                    <div>
                        <h2 id="smarkEmailFailureDetailTitle"><?php echo esc_html($strings['failure_modal_title']); ?></h2>
                        <p data-smark-failure-recipient></p>
                    </div>
                    <button type="button" class="smark-email-import-modal__close" data-close-smark-failure-detail aria-label="<?php echo esc_attr($strings['failure_modal_close']); ?>">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </header>
                <div class="smark-email-import-modal__body">
                    <pre class="smark-email-failure-detail-text" data-smark-failure-detail-text></pre>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_email_account_edit_modal($strings) {
        ?>
        <section class="seo-step-card seo-step-card--full smark-email-accounts-card smark-email-account-edit-section" id="smarkEmailAccountEditModal" data-step="strategy" aria-hidden="true" hidden>
            <header class="seo-step-header smark-email-card-header-actions smark-email-contact-workflow-header smark-email-account-edit-header">
                <div>
                    <h2 id="smarkEmailAccountEditTitle"><?php echo esc_html($strings['edit_modal_title']); ?></h2>
                    <p><?php echo esc_html($strings['edit_modal_description']); ?></p>
                </div>
                <button type="button" class="smark-email-inline-panel__close" data-close-smark-account-edit aria-label="<?php echo esc_attr($strings['close_modal']); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </header>

            <div class="smark-email-inline-panel__body">
                <form id="smarkEmailAccountEditForm" class="smark-email-account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('smark_email_account_save', 'smark_email_account_nonce'); ?>
                    <input type="hidden" name="action" value="smark_email_account_save">
                    <input type="hidden" name="account_id" value="">

                    <div class="smark-email-form-grid">
                        <label>
                            <span><?php echo esc_html($strings['provider_label']); ?></span>
                            <select name="provider" required data-smark-edit-provider>
                                <option value="email"><?php echo esc_html($strings['provider_email']); ?></option>
                                <option value="gmail"><?php echo esc_html($strings['provider_gmail']); ?></option>
                                <option value="outlook"><?php echo esc_html($strings['provider_outlook']); ?></option>
                            </select>
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_label']); ?></span>
                            <input type="text" name="account_label" required placeholder="<?php echo esc_attr($strings['field_label_placeholder_email']); ?>">
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_sender_name']); ?></span>
                            <input type="text" name="sender_name" required placeholder="<?php echo esc_attr($strings['field_sender_name_placeholder']); ?>">
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_email']); ?></span>
                            <input type="email" name="email_address" required placeholder="name@example.com">
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_app_password']); ?></span>
                            <input type="password" name="app_password" autocomplete="new-password" placeholder="<?php echo esc_attr($strings['field_password_placeholder_keep']); ?>">
                            <small><?php echo esc_html($strings['field_password_keep_help']); ?></small>
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_daily_limit']); ?></span>
                            <input type="number" name="daily_limit" required min="1" max="2000" value="100">
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_smtp_host']); ?></span>
                            <input type="text" name="smtp_host" required placeholder="mail.example.com" data-gmail-value="smtp.gmail.com" data-outlook-value="smtp.office365.com">
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_smtp_port']); ?></span>
                            <select name="smtp_port" required>
                                <option value="587">587 - TLS</option>
                                <option value="465">465 - SSL</option>
                                <option value="25">25</option>
                                <option value="2525">2525</option>
                            </select>
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_encryption']); ?></span>
                            <select name="encryption" required>
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="none"><?php echo esc_html($strings['encryption_none']); ?></option>
                            </select>
                        </label>
                    </div>

                    <div class="smark-email-form-actions">
                        <button type="submit" class="button button-primary"><?php echo esc_html($strings['update_button']); ?></button>
                    </div>
                </form>
            </div>
        </section>
        <?php
    }

    private function render_contact_list_modal($strings) {
        ?>
        <section class="seo-step-card seo-step-card--full smark-email-accounts-card smark-email-contact-list-section" id="smarkEmailContactListModal" data-step="strategy" aria-hidden="true" hidden>
            <header class="seo-step-header smark-email-card-header-actions smark-email-contact-workflow-header smark-email-contact-list-create-header">
                <div>
                    <h2 id="smarkEmailContactListTitle"><?php echo esc_html($strings['list_modal_title']); ?></h2>
                    <p><?php echo esc_html($strings['list_modal_description']); ?></p>
                </div>
                <button type="button" class="smark-email-inline-panel__close" data-close-smark-contact-list aria-label="<?php echo esc_attr($strings['bulk_close']); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </header>

            <div class="smark-email-inline-panel__body">
                <form class="smark-email-account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('smark_email_contact_list_save', 'smark_email_contact_list_nonce'); ?>
                    <input type="hidden" name="action" value="smark_email_contact_list_save">
                    <div class="smark-email-form-grid">
                        <label class="smark-email-form-field--wide">
                            <span><?php echo esc_html($strings['list_name_label']); ?></span>
                            <input type="text" name="list_name" required placeholder="<?php echo esc_attr($strings['list_name_placeholder']); ?>">
                        </label>
                    </div>
                    <div class="smark-email-form-actions">
                        <button type="submit" class="button button-primary"><?php echo esc_html($strings['create_list_button']); ?></button>
                    </div>
                </form>
            </div>
        </section>
        <?php
    }

    private function render_contact_tag_modal($strings) {
        ?>
        <section class="seo-step-card seo-step-card--full smark-email-accounts-card smark-email-contact-tag-section" id="smarkEmailContactTagModal" data-step="strategy" aria-hidden="true" hidden>
            <header class="seo-step-header smark-email-card-header-actions smark-email-contact-workflow-header smark-email-contact-tag-create-header">
                <div>
                    <h2 id="smarkEmailContactTagTitle"><?php echo esc_html($strings['tag_modal_title']); ?></h2>
                    <p><?php echo esc_html($strings['tag_modal_description']); ?></p>
                </div>
                <button type="button" class="smark-email-inline-panel__close" data-close-smark-contact-tag aria-label="<?php echo esc_attr($strings['bulk_close']); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </header>

            <div class="smark-email-inline-panel__body">
                <form class="smark-email-account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('smark_email_contact_tag_save', 'smark_email_contact_tag_nonce'); ?>
                    <input type="hidden" name="action" value="smark_email_contact_tag_save">
                    <div class="smark-email-form-grid">
                        <label class="smark-email-form-field--wide">
                            <span><?php echo esc_html($strings['tag_name_label']); ?></span>
                            <input type="text" name="tag_name" required placeholder="<?php echo esc_attr($strings['tag_name_placeholder']); ?>">
                        </label>
                    </div>
                    <p class="smark-email-help"><?php echo esc_html($strings['system_tags_help']); ?></p>
                    <div class="smark-email-form-actions">
                        <button type="submit" class="button button-primary"><?php echo esc_html($strings['create_tag_button']); ?></button>
                    </div>
                </form>
            </div>
        </section>
        <?php
    }

    private function render_contact_add_modal($strings, $contact_lists, $contact_tags) {
        ?>
        <section class="seo-step-card seo-step-card--full smark-email-accounts-card smark-email-contact-add-section" id="smarkEmailContactAddModal" data-step="strategy" aria-hidden="true" hidden>
            <header class="seo-step-header smark-email-card-header-actions smark-email-contact-workflow-header smark-email-contact-add-header">
                <div>
                    <h2 id="smarkEmailContactAddTitle"><?php echo esc_html($strings['form_title']); ?></h2>
                    <p><?php echo esc_html($strings['form_description']); ?></p>
                </div>
                <button type="button" class="smark-email-inline-panel__close" data-close-smark-contact-add aria-label="<?php echo esc_attr($strings['edit_modal_close']); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </header>

            <div class="smark-email-inline-panel__body">
                <form class="smark-email-account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('smark_email_contact_save', 'smark_email_contact_nonce'); ?>
                    <input type="hidden" name="action" value="smark_email_contact_save">

                    <div class="smark-email-form-grid">
                        <label>
                            <span><?php echo esc_html($strings['field_first_name']); ?></span>
                            <input type="text" name="first_name" placeholder="<?php echo esc_attr($strings['field_first_name_placeholder']); ?>">
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_last_name']); ?></span>
                            <input type="text" name="last_name" placeholder="<?php echo esc_attr($strings['field_last_name_placeholder']); ?>">
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_email']); ?></span>
                            <input type="email" name="email_address" required placeholder="name@example.com">
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_phone']); ?></span>
                            <input type="tel" name="phone" placeholder="<?php echo esc_attr($strings['field_phone_placeholder']); ?>">
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_segment']); ?></span>
                            <select name="contact_list_id">
                                <option value=""><?php echo esc_html($strings['field_list_empty']); ?></option>
                                <?php foreach ($contact_lists as $contact_list) : ?>
                                    <option value="<?php echo esc_attr($contact_list['id']); ?>"><?php echo esc_html($contact_list['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_source']); ?></span>
                            <input type="text" name="source" placeholder="<?php echo esc_attr($strings['field_source_placeholder']); ?>">
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_status']); ?></span>
                            <select name="status" required>
                                <option value="subscribed"><?php echo esc_html($strings['status_subscribed']); ?></option>
                                <option value="lead"><?php echo esc_html($strings['status_lead']); ?></option>
                                <option value="unsubscribed"><?php echo esc_html($strings['status_unsubscribed']); ?></option>
                            </select>
                        </label>

                        <label>
                            <span><?php echo esc_html($strings['field_tags']); ?></span>
                            <select name="contact_tag_ids[]" multiple size="4">
                                <?php foreach ($contact_tags as $contact_tag) : ?>
                                    <option value="<?php echo esc_attr($contact_tag['id']); ?>"><?php echo esc_html($contact_tag['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="smark-email-field-note"><?php echo esc_html($strings['field_tags_help']); ?></small>
                        </label>

                        <label class="smark-email-form-field--wide">
                            <span><?php echo esc_html($strings['field_notes']); ?></span>
                            <textarea name="notes" rows="3" placeholder="<?php echo esc_attr($strings['field_notes_placeholder']); ?>"></textarea>
                        </label>
                    </div>

                    <p class="smark-email-help"><?php echo esc_html($strings['contact_help']); ?></p>

                    <div class="smark-email-form-actions">
                        <button type="submit" class="button button-primary"><?php echo esc_html($strings['save_button']); ?></button>
                    </div>
                </form>
            </div>
        </section>
        <?php
    }

    private function render_contact_edit_modal($strings, $contact_lists, $contact_tags) {
        ?>
        <div class="smark-email-import-modal smark-email-contact-edit-modal" id="smarkEmailContactEditModal" aria-hidden="true">
            <div class="smark-email-import-modal__overlay" data-close-smark-contact-edit></div>
            <div class="smark-email-import-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="smarkEmailContactEditTitle">
                <header class="smark-email-import-modal__header">
                    <div>
                        <h2 id="smarkEmailContactEditTitle"><?php echo esc_html($strings['edit_modal_title']); ?></h2>
                        <p><?php echo esc_html($strings['edit_modal_description']); ?></p>
                    </div>
                    <button type="button" class="smark-email-import-modal__close" data-close-smark-contact-edit aria-label="<?php echo esc_attr($strings['edit_modal_close']); ?>">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </header>

                <div class="smark-email-import-modal__body">
                    <form class="smark-email-account-form" id="smarkEmailContactEditForm" method="post">
                        <?php wp_nonce_field('smark_email_contact_save', 'smark_email_contact_nonce'); ?>
                        <input type="hidden" name="action" value="smark_email_contact_save_modal">
                        <input type="hidden" name="contact_id" value="">

                        <div class="smark-email-form-grid">
                            <label>
                                <span><?php echo esc_html($strings['field_first_name']); ?></span>
                                <input type="text" name="first_name" placeholder="<?php echo esc_attr($strings['field_first_name_placeholder']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_last_name']); ?></span>
                                <input type="text" name="last_name" placeholder="<?php echo esc_attr($strings['field_last_name_placeholder']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_email']); ?></span>
                                <input type="email" name="email_address" required placeholder="name@example.com">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_phone']); ?></span>
                                <input type="tel" name="phone" placeholder="<?php echo esc_attr($strings['field_phone_placeholder']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_segment']); ?></span>
                                <select name="contact_list_id">
                                    <option value=""><?php echo esc_html($strings['field_list_empty']); ?></option>
                                    <?php foreach ($contact_lists as $contact_list) : ?>
                                        <option value="<?php echo esc_attr($contact_list['id']); ?>"><?php echo esc_html($contact_list['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_source']); ?></span>
                                <input type="text" name="source" placeholder="<?php echo esc_attr($strings['field_source_placeholder']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_status']); ?></span>
                                <select name="status" required>
                                    <option value="subscribed"><?php echo esc_html($strings['status_subscribed']); ?></option>
                                    <option value="lead"><?php echo esc_html($strings['status_lead']); ?></option>
                                    <option value="unsubscribed"><?php echo esc_html($strings['status_unsubscribed']); ?></option>
                                </select>
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_tags']); ?></span>
                                <select name="contact_tag_ids[]" multiple size="4">
                                    <?php foreach ($contact_tags as $contact_tag) : ?>
                                        <option value="<?php echo esc_attr($contact_tag['id']); ?>"><?php echo esc_html($contact_tag['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="smark-email-field-note"><?php echo esc_html($strings['field_tags_help']); ?></small>
                            </label>

                            <label class="smark-email-form-field--wide">
                                <span><?php echo esc_html($strings['field_notes']); ?></span>
                                <textarea name="notes" rows="3" placeholder="<?php echo esc_attr($strings['field_notes_placeholder']); ?>"></textarea>
                            </label>
                        </div>

                        <div class="smark-email-form-actions">
                            <button type="submit" class="button button-primary"><?php echo esc_html($strings['update_button']); ?></button>
                            <button type="button" class="button smark-email-secondary-action" data-close-smark-contact-edit><?php echo esc_html($strings['edit_modal_cancel']); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_contacts_import_modal($strings, $import_token, $import_preview) {
        ?>
        <section class="seo-step-card seo-step-card--full smark-email-accounts-card smark-email-contact-import-section" id="smarkEmailImportModal" data-step="strategy" aria-hidden="true" hidden>
            <header class="seo-step-header smark-email-card-header-actions smark-email-contact-workflow-header smark-email-contact-import-header">
                <div>
                    <h2 id="smarkEmailImportTitle"><?php echo esc_html($strings['bulk_title']); ?></h2>
                    <p><?php echo esc_html($strings['bulk_description']); ?></p>
                </div>
                <button type="button" class="smark-email-inline-panel__close" data-close-smark-import aria-label="<?php echo esc_attr($strings['bulk_close']); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </header>

            <div class="smark-email-inline-panel__body">
                <form class="smark-email-account-form" id="smarkEmailImportPreviewForm" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('smark_email_contacts_import_preview', 'smark_email_contacts_import_nonce'); ?>
                    <input type="hidden" name="action" value="smark_email_contacts_import_preview">

                    <div class="smark-email-import-upload">
                        <label>
                            <span><?php echo esc_html($strings['bulk_file_label']); ?></span>
                            <input type="file" name="contacts_file" accept=".csv,.xlsx" required>
                        </label>
                        <button type="submit" class="button button-primary" data-default-text="<?php echo esc_attr($strings['bulk_preview_button']); ?>"><?php echo esc_html($strings['bulk_preview_button']); ?></button>
                    </div>

                    <p class="smark-email-help"><?php echo esc_html($strings['bulk_help']); ?></p>
                </form>

                <div id="smarkEmailImportMappingContainer">
                    <?php
                    if (!empty($import_preview['rows']) && !empty($import_preview['headers'])) {
                        $this->render_contacts_import_mapping($strings, $import_token, $import_preview);
                    }
                    ?>
                </div>
            </div>
        </section>
        <?php
    }

    private function render_contacts_import_mapping($strings, $import_token, $import_preview) {
        ?>
        <div class="smark-email-import-mapping">
            <h3><?php echo esc_html($strings['bulk_mapping_title']); ?></h3>
            <p class="smark-email-help"><?php echo esc_html(sprintf($strings['bulk_mapping_description'], count($import_preview['rows']))); ?></p>

            <form class="smark-email-account-form" id="smarkEmailImportForm" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('smark_email_contacts_import', 'smark_email_contacts_import_nonce'); ?>
                <input type="hidden" name="action" value="smark_email_contacts_import">
                <input type="hidden" name="import_token" value="<?php echo esc_attr($import_token); ?>">

                <div class="smark-email-import-map-table-wrap">
                    <table class="smark-email-import-map-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html($strings['bulk_plugin_field']); ?></th>
                                <th><?php echo esc_html($strings['bulk_file_column']); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->get_contact_import_fields($strings) as $field_key => $field_label) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($field_label); ?></strong>
                                        <?php if ($field_key === 'email_address') : ?>
                                            <small><?php echo esc_html($strings['bulk_required']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select name="mapping[<?php echo esc_attr($field_key); ?>]" <?php echo in_array($field_key, array('email_address'), true) ? 'required' : ''; ?>>
                                            <option value=""><?php echo esc_html($strings['bulk_ignore_column']); ?></option>
                                            <?php foreach ($import_preview['headers'] as $header_index => $header_label) : ?>
                                                <option value="<?php echo esc_attr($header_index); ?>" <?php selected($this->guess_contact_import_column($field_key, $header_label), true); ?>>
                                                    <?php echo esc_html($header_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td><strong><?php echo esc_html($strings['bulk_default_segment']); ?></strong></td>
                                <td><input type="text" name="default_segment" placeholder="<?php echo esc_attr($strings['field_segment_placeholder']); ?>"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="smark-email-import-preview">
                    <table class="smark-email-accounts-table">
                        <thead>
                            <tr>
                                <?php foreach ($import_preview['headers'] as $header_label) : ?>
                                    <th><?php echo esc_html($header_label); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($import_preview['rows'], 0, 5) as $preview_row) : ?>
                                <tr>
                                    <?php foreach ($import_preview['headers'] as $header_index => $header_label) : ?>
                                        <td><?php echo esc_html(isset($preview_row[$header_index]) ? $preview_row[$header_index] : ''); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="smark-email-form-actions">
                    <button type="submit" class="button button-primary" data-default-text="<?php echo esc_attr($strings['bulk_import_button']); ?>"><?php echo esc_html($strings['bulk_import_button']); ?></button>
                </div>
            </form>
        </div>
        <?php
    }

    private function render_contact_lists_content($strings, $contacts, $contact_lists) {
        ?>
        <div class="smark-email-system-tags smark-email-system-tags--list">
            <span class="smark-email-status smark-email-status--system"><?php echo esc_html($strings['system_list_label']); ?></span>
            <strong><?php echo esc_html($strings['system_list_all']); ?></strong>
            <small><?php echo esc_html(sprintf($strings['system_list_all_help'], number_format_i18n(count($contacts)))); ?></small>
        </div>
        <?php

        if (empty($contact_lists)) : ?>
            <div class="smark-email-empty">
                <?php echo esc_html($strings['lists_empty_state']); ?>
            </div>
        <?php else : ?>
            <div class="smark-email-management-grid">
                <?php foreach ($contact_lists as $list) : ?>
                    <?php $assigned_count = count($this->get_assigned_contact_ids_for_entity($list)); ?>
                    <article class="smark-email-management-card">
                        <header>
                            <div>
                                <h3><?php echo esc_html($list['name']); ?></h3>
                                <p><?php echo esc_html(sprintf($strings['assigned_contacts_count'], number_format_i18n($assigned_count))); ?></p>
                            </div>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js($strings['delete_list_confirm']); ?>');">
                                <?php wp_nonce_field('smark_email_contact_list_delete', 'smark_email_contact_list_nonce'); ?>
                                <input type="hidden" name="action" value="smark_email_contact_list_delete">
                                <input type="hidden" name="list_id" value="<?php echo esc_attr($list['id']); ?>">
                                <button type="submit" class="button button-link-delete"><?php echo esc_html($strings['delete_button']); ?></button>
                            </form>
                        </header>

                        <form class="smark-email-account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('smark_email_contact_list_save', 'smark_email_contact_list_nonce'); ?>
                            <input type="hidden" name="action" value="smark_email_contact_list_save">
                            <input type="hidden" name="list_id" value="<?php echo esc_attr($list['id']); ?>">
                            <input type="hidden" name="list_name" value="<?php echo esc_attr($list['name']); ?>">
                            <?php $this->render_contact_assignment_picker($strings, $contacts, $this->get_assigned_contact_ids_for_entity($list)); ?>
                            <div class="smark-email-form-actions">
                                <button type="submit" class="button button-primary"><?php echo esc_html($strings['save_assignments_button']); ?></button>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }

    private function render_contact_tags_content($strings, $contacts, $contact_tags, $daily_sent_hashes) {
        $system_count = 0;
        foreach ($contacts as $contact) {
            $email = isset($contact['email_address']) ? sanitize_email($contact['email_address']) : '';
            if ($email !== '' && isset($daily_sent_hashes[$this->get_campaign_recipient_hash($email)])) {
                $system_count++;
            }
        }
        ?>
        <div class="smark-email-system-tags">
            <span class="smark-email-status smark-email-status--system"><?php echo esc_html($strings['system_tag_label']); ?></span>
            <strong><?php echo esc_html($strings['system_tag_today_sent']); ?></strong>
            <small><?php echo esc_html(sprintf($strings['system_tag_today_sent_help'], number_format_i18n($system_count))); ?></small>
        </div>

        <?php if (empty($contact_tags)) : ?>
            <div class="smark-email-empty">
                <?php echo esc_html($strings['tags_empty_state']); ?>
            </div>
        <?php else : ?>
            <div class="smark-email-management-grid">
                <?php foreach ($contact_tags as $tag) : ?>
                    <?php $assigned_count = count($this->get_assigned_contact_ids_for_entity($tag)); ?>
                    <article class="smark-email-management-card">
                        <header>
                            <div>
                                <h3><?php echo esc_html($tag['name']); ?></h3>
                                <p><?php echo esc_html(sprintf($strings['assigned_contacts_count'], number_format_i18n($assigned_count))); ?></p>
                            </div>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js($strings['delete_tag_confirm']); ?>');">
                                <?php wp_nonce_field('smark_email_contact_tag_delete', 'smark_email_contact_tag_nonce'); ?>
                                <input type="hidden" name="action" value="smark_email_contact_tag_delete">
                                <input type="hidden" name="tag_id" value="<?php echo esc_attr($tag['id']); ?>">
                                <button type="submit" class="button button-link-delete"><?php echo esc_html($strings['delete_button']); ?></button>
                            </form>
                        </header>

                        <form class="smark-email-account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('smark_email_contact_tag_save', 'smark_email_contact_tag_nonce'); ?>
                            <input type="hidden" name="action" value="smark_email_contact_tag_save">
                            <input type="hidden" name="tag_id" value="<?php echo esc_attr($tag['id']); ?>">
                            <input type="hidden" name="tag_name" value="<?php echo esc_attr($tag['name']); ?>">
                            <?php $this->render_contact_assignment_picker($strings, $contacts, $this->get_assigned_contact_ids_for_entity($tag)); ?>
                            <div class="smark-email-form-actions">
                                <button type="submit" class="button button-primary"><?php echo esc_html($strings['save_assignments_button']); ?></button>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif;
    }

    private function render_contact_assignment_picker($strings, $contacts, $selected_contact_ids) {
        $selected_contact_ids = array_map('strval', (array) $selected_contact_ids);
        ?>
        <label class="smark-email-form-field">
            <span><?php echo esc_html($strings['assignment_contacts_label']); ?></span>
            <select name="contact_ids[]" multiple size="6">
                <?php foreach ($contacts as $contact) : ?>
                    <?php
                    $contact_id = isset($contact['id']) ? (string) $contact['id'] : '';
                    $contact_name = trim((string) ($contact['first_name'] ?? '') . ' ' . (string) ($contact['last_name'] ?? ''));
                    $contact_label = ($contact_name !== '' ? $contact_name . ' - ' : '') . (string) ($contact['email_address'] ?? '');
                    ?>
                    <option value="<?php echo esc_attr($contact_id); ?>" <?php selected(in_array($contact_id, $selected_contact_ids, true)); ?>><?php echo esc_html($contact_label); ?></option>
                <?php endforeach; ?>
            </select>
            <small><?php echo esc_html($strings['assignment_contacts_help']); ?></small>
        </label>
        <?php
    }

    private function filter_contacts_for_search($contacts, $query, $contact_lists = array(), $contact_tags = array(), $daily_sent_hashes = array(), $strings = array()) {
        $lower = function($value) {
            $value = (string) $value;
            return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        };
        $query = trim($lower($query));
        if ($query === '') {
            return $contacts;
        }

        return array_values(array_filter($contacts, function($contact) use ($query, $contact_lists, $contact_tags, $daily_sent_hashes, $strings, $lower) {
            $contact_id = isset($contact['id']) ? (string) $contact['id'] : '';
            $email = isset($contact['email_address']) ? sanitize_email((string) $contact['email_address']) : '';
            $parts = array();

            foreach (array('first_name', 'last_name', 'email_address', 'phone', 'source', 'status', 'notes', 'segment') as $field) {
                if (!empty($contact[$field])) {
                    $parts[] = (string) $contact[$field];
                }
            }

            $parts = array_merge($parts, $this->get_contact_entity_names_for_contact($contact_id, $contact_lists));
            $parts = array_merge($parts, $this->get_contact_entity_names_for_contact($contact_id, $contact_tags));

            if ($email !== '' && isset($daily_sent_hashes[$this->get_campaign_recipient_hash($email)])) {
                $parts[] = $strings['system_tag_today_sent'] ?? 'Today';
                $parts[] = $strings['system_tag_today_sent_short'] ?? 'Today';
                $parts[] = 'today';
            }

            return strpos($lower(implode(' ', $parts)), $query) !== false;
        }));
    }

    private function render_contacts_list_content($strings, $contacts, $contact_lists = array(), $contact_tags = array(), $daily_sent_hashes = array(), $current_page = 1, $per_page = 100, $search_query = '') {
        $has_search = trim((string) $search_query) !== '';
        $contacts = $this->filter_contacts_for_search($contacts, $search_query, $contact_lists, $contact_tags, $daily_sent_hashes, $strings);
        $total_contacts = count($contacts);
        $per_page = max(1, absint($per_page));
        $total_pages = max(1, (int) ceil($total_contacts / $per_page));
        $current_page = min(max(1, absint($current_page)), $total_pages);
        $offset = ($current_page - 1) * $per_page;
        $visible_contacts = array_slice($contacts, $offset, $per_page);

        if (empty($contacts)) : ?>
            <div class="smark-email-empty">
                <?php echo esc_html($has_search ? $strings['contacts_search_empty'] : $strings['empty_state']); ?>
            </div>
        <?php else : ?>
            <div class="smark-email-table-wrap">
                <table class="smark-email-accounts-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html($strings['column_contact']); ?></th>
                            <th><?php echo esc_html($strings['column_email']); ?></th>
                            <th><?php echo esc_html($strings['column_lists']); ?></th>
                            <th><?php echo esc_html($strings['column_tags']); ?></th>
                            <th><?php echo esc_html($strings['column_source']); ?></th>
                            <th><?php echo esc_html($strings['column_status']); ?></th>
                            <th><?php echo esc_html($strings['column_actions']); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visible_contacts as $contact) : ?>
                            <?php
                            $full_name = trim((string) ($contact['first_name'] ?? '') . ' ' . (string) ($contact['last_name'] ?? ''));
                            $full_name = $full_name !== '' ? $full_name : $strings['unnamed_contact'];
                            $status = isset($contact['status']) ? (string) $contact['status'] : 'subscribed';
                            $status_label = $strings['status_subscribed'];
                            if ($status === 'lead') {
                                $status_label = $strings['status_lead'];
                            } elseif ($status === 'unsubscribed') {
                                $status_label = $strings['status_unsubscribed'];
                            }
                            $contact_list_names = $this->get_contact_entity_names_for_contact($contact['id'] ?? '', $contact_lists);
                            $contact_tag_names = $this->get_contact_entity_names_for_contact($contact['id'] ?? '', $contact_tags);
                            $contact_list_ids = $this->get_contact_entity_ids_for_contact($contact['id'] ?? '', $contact_lists);
                            $contact_tag_ids = $this->get_contact_entity_ids_for_contact($contact['id'] ?? '', $contact_tags);
                            $contact_email = isset($contact['email_address']) ? sanitize_email($contact['email_address']) : '';
                            if ($contact_email !== '' && isset($daily_sent_hashes[$this->get_campaign_recipient_hash($contact_email)])) {
                                $contact_tag_names[] = $strings['system_tag_today_sent_short'];
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($full_name); ?></strong>
                                    <?php if (!empty($contact['phone'])) : ?>
                                        <small><?php echo esc_html($contact['phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($contact['email_address']); ?></td>
                                <td><?php echo esc_html(!empty($contact_list_names) ? implode(', ', $contact_list_names) : '-'); ?></td>
                                <td><?php echo esc_html(!empty($contact_tag_names) ? implode(', ', $contact_tag_names) : '-'); ?></td>
                                <td><?php echo esc_html($contact['source']); ?></td>
                                <td><span class="smark-email-status smark-email-status--<?php echo esc_attr($status); ?>"><?php echo esc_html($status_label); ?></span></td>
                                <td>
                                    <div class="smark-email-action-row">
                                        <button type="button" class="button smark-email-edit-button" data-open-smark-contact-edit="<?php echo esc_attr($contact['id']); ?>">
                                            <?php echo esc_html($strings['edit_button']); ?>
                                        </button>
                                        <form class="smark-email-contact-delete-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-delete-confirm="<?php echo esc_attr($strings['delete_confirm']); ?>">
                                            <?php wp_nonce_field('smark_email_contact_delete', 'smark_email_contact_nonce'); ?>
                                            <input type="hidden" name="action" value="smark_email_contact_delete">
                                            <input type="hidden" name="contact_id" value="<?php echo esc_attr($contact['id']); ?>">
                                            <button type="submit" class="button button-link-delete"><?php echo esc_html($strings['delete_button']); ?></button>
                                        </form>
                                    </div>
                                    <script type="application/json" data-smark-contact-json="<?php echo esc_attr($contact['id']); ?>">
                                        <?php echo wp_json_encode(array(
                                            'id' => (string) ($contact['id'] ?? ''),
                                            'first_name' => (string) ($contact['first_name'] ?? ''),
                                            'last_name' => (string) ($contact['last_name'] ?? ''),
                                            'email_address' => (string) ($contact['email_address'] ?? ''),
                                            'phone' => (string) ($contact['phone'] ?? ''),
                                            'contact_list_id' => !empty($contact_list_ids[0]) ? (string) $contact_list_ids[0] : '',
                                            'contact_tag_ids' => array_values(array_map('strval', $contact_tag_ids)),
                                            'source' => (string) ($contact['source'] ?? ''),
                                            'status' => $status,
                                            'notes' => (string) ($contact['notes'] ?? ''),
                                        ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                                    </script>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php $this->render_contacts_pagination($strings, $current_page, $total_pages, $total_contacts, $offset, count($visible_contacts)); ?>
        <?php endif;
    }

    private function render_contacts_pagination($strings, $current_page, $total_pages, $total_contacts, $offset, $visible_count) {
        if ($total_pages <= 1) {
            return;
        }

        $from = $offset + 1;
        $to = min($offset + $visible_count, $total_contacts);
        $pages = array(1, $total_pages);
        for ($page = $current_page - 2; $page <= $current_page + 2; $page++) {
            if ($page > 1 && $page < $total_pages) {
                $pages[] = $page;
            }
        }
        $pages = array_values(array_unique($pages));
        sort($pages);
        ?>
        <nav class="smark-email-pagination" aria-label="<?php echo esc_attr($strings['pagination_label']); ?>">
            <span class="smark-email-pagination__summary">
                <?php echo esc_html(sprintf($strings['pagination_summary'], number_format_i18n($from), number_format_i18n($to), number_format_i18n($total_contacts))); ?>
            </span>
            <div class="smark-email-pagination__links">
                <button type="button" class="button smark-email-pagination__button" data-smark-contacts-page="<?php echo esc_attr($current_page - 1); ?>" <?php disabled($current_page <= 1); ?>>
                    <?php echo esc_html($strings['pagination_previous']); ?>
                </button>
                <?php
                $last_page = 0;
                foreach ($pages as $page) :
                    if ($last_page && $page > $last_page + 1) :
                        ?>
                        <span class="smark-email-pagination__ellipsis"><?php echo esc_html($strings['pagination_ellipsis']); ?></span>
                    <?php endif; ?>
                    <button type="button" class="button smark-email-pagination__button <?php echo $page === $current_page ? 'is-active' : ''; ?>" data-smark-contacts-page="<?php echo esc_attr($page); ?>" <?php disabled($page === $current_page); ?>>
                        <?php echo esc_html(number_format_i18n($page)); ?>
                    </button>
                    <?php
                    $last_page = $page;
                endforeach;
                ?>
                <button type="button" class="button smark-email-pagination__button" data-smark-contacts-page="<?php echo esc_attr($current_page + 1); ?>" <?php disabled($current_page >= $total_pages); ?>>
                    <?php echo esc_html($strings['pagination_next']); ?>
                </button>
            </div>
        </nav>
        <?php
    }

    public function handle_email_account_save() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_account_save', 'smark_email_account_nonce');

        $account_id = isset($_POST['account_id']) ? sanitize_text_field(wp_unslash($_POST['account_id'])) : '';
        $accounts = $this->get_email_accounts();
        $existing_account = null;
        $existing_account_index = null;
        if ($account_id !== '') {
            foreach ($accounts as $index => $saved_account) {
                if (!empty($saved_account['id']) && $saved_account['id'] === $account_id) {
                    $existing_account = $saved_account;
                    $existing_account_index = $index;
                    break;
                }
            }

            if (!is_array($existing_account)) {
                $this->redirect_to_accounts('error');
            }
        }

        $email_address = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';
        if (!$email_address || !is_email($email_address)) {
            $this->redirect_to_accounts('error');
        }

        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : 'email';
        $provider = in_array($provider, array('email', 'gmail', 'outlook'), true) ? $provider : 'email';

        $smtp_port = isset($_POST['smtp_port']) ? absint($_POST['smtp_port']) : 587;
        $smtp_port = in_array($smtp_port, array(25, 465, 587, 2525), true) ? $smtp_port : 587;

        $encryption = isset($_POST['encryption']) ? sanitize_key(wp_unslash($_POST['encryption'])) : 'tls';
        $encryption = in_array($encryption, array('none', 'tls', 'ssl'), true) ? $encryption : 'tls';

        $daily_limit = isset($_POST['daily_limit']) ? absint($_POST['daily_limit']) : 1;
        $daily_limit = max(1, min(2000, $daily_limit));

        $app_password = isset($_POST['app_password']) ? sanitize_text_field(wp_unslash($_POST['app_password'])) : '';
        if ($app_password === '' && is_array($existing_account) && !empty($existing_account['app_password'])) {
            $app_password = (string) $existing_account['app_password'];
        }

        $account = array(
            'id'            => is_array($existing_account) ? $existing_account['id'] : wp_generate_uuid4(),
            'provider'      => $provider,
            'account_label' => isset($_POST['account_label']) ? sanitize_text_field(wp_unslash($_POST['account_label'])) : '',
            'sender_name'   => isset($_POST['sender_name']) ? sanitize_text_field(wp_unslash($_POST['sender_name'])) : '',
            'email_address' => $email_address,
            'app_password'  => $app_password,
            'daily_limit'   => $daily_limit,
            'smtp_host'     => isset($_POST['smtp_host']) ? sanitize_text_field(wp_unslash($_POST['smtp_host'])) : '',
            'smtp_port'     => $smtp_port,
            'encryption'    => $encryption,
            'created_at'    => is_array($existing_account) && !empty($existing_account['created_at']) ? $existing_account['created_at'] : current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        );

        if ($account['account_label'] === '' || $account['sender_name'] === '' || $account['app_password'] === '') {
            $this->redirect_to_accounts('error');
        }

        if ($account['smtp_host'] === '') {
            if ($provider === 'gmail') {
                $account['smtp_host'] = 'smtp.gmail.com';
            } elseif ($provider === 'outlook') {
                $account['smtp_host'] = 'smtp.office365.com';
            } else {
                $this->redirect_to_accounts('error');
            }
        }

        if ($existing_account_index !== null) {
            $accounts[$existing_account_index] = $account;
        } else {
            $accounts[] = $account;
        }
        $this->save_email_accounts($accounts);

        $this->redirect_to_accounts('saved');
    }

    public function handle_email_account_delete() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_account_delete', 'smark_email_account_nonce');

        $account_id = isset($_POST['account_id']) ? sanitize_text_field(wp_unslash($_POST['account_id'])) : '';
        $accounts = array_values(array_filter($this->get_email_accounts(), function($account) use ($account_id) {
            return isset($account['id']) && $account['id'] !== $account_id;
        }));

        $this->save_email_accounts($accounts);
        $this->redirect_to_accounts('deleted');
    }

    public function handle_email_contact_save() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_contact_save', 'smark_email_contact_nonce');

        $result = $this->save_contact_from_request();
        if (is_wp_error($result)) {
            $this->redirect_to_contacts('error');
        }

        $this->redirect_to_contacts('saved');
    }

    public function ajax_email_contact_save_modal() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        check_admin_referer('smark_email_contact_save', 'smark_email_contact_nonce');

        $strings = $this->get_contact_strings($this->get_current_panel_lang());
        $result = $this->save_contact_from_request();
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $strings['notice_error']), 400);
        }

        $search_query = isset($_POST['search_query']) ? sanitize_text_field(wp_unslash($_POST['search_query'])) : '';

        ob_start();
        $this->render_contacts_list_content($strings, $this->get_contacts(), $this->get_contact_lists(), $this->get_contact_tags(), $this->get_daily_sent_contact_hashes(), 1, 100, $search_query);
        $list_html = ob_get_clean();

        wp_send_json_success(array(
            'message' => $strings['notice_saved'],
            'listHtml' => $list_html,
        ));
    }

    private function save_contact_from_request() {
        $email_address = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';
        if (!$email_address || !is_email($email_address)) {
            return new WP_Error('invalid_contact', 'Invalid contact.');
        }

        $contact_id = isset($_POST['contact_id']) ? sanitize_text_field(wp_unslash($_POST['contact_id'])) : '';
        $contact_list_id = isset($_POST['contact_list_id']) ? sanitize_text_field(wp_unslash($_POST['contact_list_id'])) : '';
        $contact_tag_ids = isset($_POST['contact_tag_ids']) && is_array($_POST['contact_tag_ids'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['contact_tag_ids']))
            : array();
        $contact_tag_ids = array_values(array_filter(array_unique($contact_tag_ids)));
        $selected_list = $this->get_contact_entity_by_id($contact_list_id, $this->get_contact_lists());
        $segment = !empty($selected_list['name']) ? (string) $selected_list['name'] : '';

        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'subscribed';
        $status = in_array($status, array('subscribed', 'lead', 'unsubscribed'), true) ? $status : 'subscribed';

        $contacts = $this->get_contacts();
        $existing_index = null;
        foreach ($contacts as $index => $existing_contact) {
            $existing_id = isset($existing_contact['id']) ? (string) $existing_contact['id'] : '';
            if ($contact_id !== '' && $existing_id === $contact_id) {
                $existing_index = $index;
            }
            if (isset($existing_contact['email_address']) && strtolower((string) $existing_contact['email_address']) === strtolower($email_address) && $existing_id !== $contact_id) {
                return new WP_Error('duplicate_contact', 'Duplicate contact.');
            }
        }

        if ($contact_id !== '' && $existing_index === null) {
            return new WP_Error('missing_contact', 'Missing contact.');
        }

        if ($contact_id === '') {
            $contact_id = wp_generate_uuid4();
        }

        $existing_contact = $existing_index !== null ? $contacts[$existing_index] : array();
        $contact = array(
            'id'            => $contact_id,
            'first_name'    => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
            'last_name'     => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
            'email_address' => $email_address,
            'phone'         => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
            'segment'       => $segment,
            'source'        => isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '',
            'status'        => $status,
            'notes'         => isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '',
            'created_at'    => !empty($existing_contact['created_at']) ? $existing_contact['created_at'] : current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        );

        if ($existing_index !== null) {
            $contacts[$existing_index] = array_merge($existing_contact, $contact);
        } else {
            $contacts[] = $contact;
        }

        update_option(self::OPTION_CONTACTS, $contacts, false);
        $this->set_contact_entities_for_contact($contact_id, array($contact_list_id), self::OPTION_CONTACT_LISTS);
        $this->set_contact_entities_for_contact($contact_id, $contact_tag_ids, self::OPTION_CONTACT_TAGS);

        return $contact_id;
    }

    public function handle_email_contact_delete() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_contact_delete', 'smark_email_contact_nonce');

        $contact_id = isset($_POST['contact_id']) ? sanitize_text_field(wp_unslash($_POST['contact_id'])) : '';
        $this->delete_contact_by_id($contact_id);
        $this->redirect_to_contacts('deleted');
    }

    public function ajax_email_contact_delete_modal() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        check_ajax_referer('smark_email_contacts_page_ajax', 'nonce');

        $contact_id = isset($_POST['contact_id']) ? sanitize_text_field(wp_unslash($_POST['contact_id'])) : '';
        $this->delete_contact_by_id($contact_id);

        $strings = $this->get_contact_strings($this->get_current_panel_lang());
        $search_query = isset($_POST['search_query']) ? sanitize_text_field(wp_unslash($_POST['search_query'])) : '';

        ob_start();
        $this->render_contacts_list_content($strings, $this->get_contacts(), $this->get_contact_lists(), $this->get_contact_tags(), $this->get_daily_sent_contact_hashes(), 1, 100, $search_query);
        $list_html = ob_get_clean();

        wp_send_json_success(array(
            'message' => $strings['notice_deleted'],
            'listHtml' => $list_html,
        ));
    }

    private function delete_contact_by_id($contact_id) {
        $contact_id = sanitize_text_field((string) $contact_id);
        if ($contact_id === '') {
            return;
        }

        $contacts = array_values(array_filter($this->get_contacts(), function($contact) use ($contact_id) {
            return isset($contact['id']) && $contact['id'] !== $contact_id;
        }));

        update_option(self::OPTION_CONTACTS, $contacts, false);
        $this->remove_contact_from_contact_entities($contact_id);
    }

    public function handle_email_contact_list_save() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_contact_list_save', 'smark_email_contact_list_nonce');

        $list_id = isset($_POST['list_id']) ? sanitize_text_field(wp_unslash($_POST['list_id'])) : '';
        $list_name = isset($_POST['list_name']) ? sanitize_text_field(wp_unslash($_POST['list_name'])) : '';
        $contact_ids = $this->sanitize_contact_ids_from_request();

        if ($list_name === '') {
            $this->redirect_to_contacts('error');
        }

        $lists = $this->upsert_contact_entity($this->get_contact_lists(), $list_id, $list_name, $contact_ids);
        update_option(self::OPTION_CONTACT_LISTS, $lists, false);
        $this->redirect_to_contacts('list_saved');
    }

    public function handle_email_contact_list_delete() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_contact_list_delete', 'smark_email_contact_list_nonce');

        $list_id = isset($_POST['list_id']) ? sanitize_text_field(wp_unslash($_POST['list_id'])) : '';
        $lists = array_values(array_filter($this->get_contact_lists(), function($list) use ($list_id) {
            return isset($list['id']) && $list['id'] !== $list_id;
        }));

        update_option(self::OPTION_CONTACT_LISTS, $lists, false);
        $this->redirect_to_contacts('list_deleted');
    }

    public function handle_email_contact_tag_save() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_contact_tag_save', 'smark_email_contact_tag_nonce');

        $tag_id = isset($_POST['tag_id']) ? sanitize_text_field(wp_unslash($_POST['tag_id'])) : '';
        $tag_name = isset($_POST['tag_name']) ? sanitize_text_field(wp_unslash($_POST['tag_name'])) : '';
        $contact_ids = $this->sanitize_contact_ids_from_request();

        if ($tag_name === '') {
            $this->redirect_to_contacts('error');
        }

        $tags = $this->upsert_contact_entity($this->get_contact_tags(), $tag_id, $tag_name, $contact_ids);
        update_option(self::OPTION_CONTACT_TAGS, $tags, false);
        $this->redirect_to_contacts('tag_saved');
    }

    public function handle_email_contact_tag_delete() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_contact_tag_delete', 'smark_email_contact_tag_nonce');

        $tag_id = isset($_POST['tag_id']) ? sanitize_text_field(wp_unslash($_POST['tag_id'])) : '';
        $tags = array_values(array_filter($this->get_contact_tags(), function($tag) use ($tag_id) {
            return isset($tag['id']) && $tag['id'] !== $tag_id;
        }));

        update_option(self::OPTION_CONTACT_TAGS, $tags, false);
        $this->redirect_to_contacts('tag_deleted');
    }

    public function handle_campaign_message_save() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_campaign_message_save', 'smark_email_campaign_message_nonce');

        $campaign_message = $this->build_campaign_message_from_request();
        if (is_wp_error($campaign_message)) {
            $this->redirect_to_campaign_messages('error');
        }

        $campaign_action = isset($_POST['campaign_action']) ? sanitize_key(wp_unslash($_POST['campaign_action'])) : 'save';
        if ($campaign_action === 'send_now') {
            $send_result = $this->send_campaign_message($campaign_message);
            if (is_wp_error($send_result)) {
                if ($send_result->get_error_code() === 'sender_capacity_insufficient') {
                    $this->redirect_to_campaign_messages('capacity_error');
                }
                $this->redirect_to_campaign_messages('send_error');
            }

            $campaign_message['message_status'] = 'sent';
            $campaign_message['sent_at'] = current_time('mysql');
            $campaign_message['sent_count'] = (int) $send_result;
            $this->upsert_campaign_message($campaign_message);

            $this->redirect_to_campaign_messages('sent', array('sent' => (int) $send_result));
        }

        $this->upsert_campaign_message($campaign_message);
        $this->redirect_to_campaign_messages('saved');
    }

    public function ajax_campaign_message_save_modal() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'smark')), 403);
        }

        check_admin_referer('smark_email_campaign_message_save', 'smark_email_campaign_message_nonce');

        $current_lang = $this->get_current_panel_lang();
        $strings = $this->get_campaign_message_strings($current_lang);
        $campaign_message = $this->build_campaign_message_from_request();
        if (is_wp_error($campaign_message)) {
            wp_send_json_error(array('message' => $strings['notice_error']), 400);
        }

        $this->upsert_campaign_message($campaign_message);
        $messages = $this->get_campaign_messages();

        ob_start();
        $this->render_campaign_messages_list_content($strings, $messages);
        $html = ob_get_clean();

        wp_send_json_success(array(
            'message' => $strings['notice_saved'],
            'html' => $html,
        ));
    }

    public function handle_campaign_message_delete() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_campaign_message_delete', 'smark_email_campaign_message_nonce');

        $message_id = isset($_POST['message_id']) ? sanitize_text_field(wp_unslash($_POST['message_id'])) : '';
        $messages = array_values(array_filter($this->get_campaign_messages(), function($campaign_message) use ($message_id) {
            return isset($campaign_message['id']) && $campaign_message['id'] !== $message_id;
        }));

        update_option(self::OPTION_CAMPAIGN_MESSAGES, $messages, false);
        $this->redirect_to_campaign_messages('deleted');
    }

    public function handle_campaign_message_send() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_campaign_message_send', 'smark_email_campaign_message_nonce');

        $message_id = isset($_POST['message_id']) ? sanitize_text_field(wp_unslash($_POST['message_id'])) : '';
        $messages = $this->get_campaign_messages();
        $found_index = null;
        $campaign_message = null;

        foreach ($messages as $index => $message) {
            if (isset($message['id']) && $message['id'] === $message_id) {
                $found_index = $index;
                $campaign_message = $message;
                break;
            }
        }

        if ($campaign_message === null) {
            $this->redirect_to_campaign_messages('send_error');
        }

        $send_result = $this->send_campaign_message($campaign_message);
        if (is_wp_error($send_result)) {
            if ($send_result->get_error_code() === 'sender_capacity_insufficient') {
                $this->redirect_to_campaign_messages('capacity_error');
            }
            $this->redirect_to_campaign_messages('send_error');
        }

        $messages[$found_index]['message_status'] = 'sent';
        $messages[$found_index]['sent_at'] = current_time('mysql');
        $messages[$found_index]['sent_count'] = (int) $send_result;
        update_option(self::OPTION_CAMPAIGN_MESSAGES, $messages, false);

        $this->redirect_to_campaign_messages('sent', array('sent' => (int) $send_result));
    }

    public function ajax_campaign_message_send() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'smark')), 403);
        }

        check_ajax_referer('smark_email_campaign_message_ajax', 'nonce');

        $current_lang = get_option('smark_panel_language', 'en');
        $current_lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $strings = $this->get_campaign_message_strings($current_lang);
        $message_id = isset($_POST['message_id']) ? sanitize_text_field(wp_unslash($_POST['message_id'])) : '';
        $messages = $this->get_campaign_messages();
        $found_index = null;
        $campaign_message = null;

        foreach ($messages as $index => $message) {
            if (isset($message['id']) && $message['id'] === $message_id) {
                $found_index = $index;
                $campaign_message = $message;
                break;
            }
        }

        if ($campaign_message === null) {
            wp_send_json_error(array('message' => $strings['notice_send_error']), 404);
        }

        $send_result = $this->send_campaign_message($campaign_message);
        if (is_wp_error($send_result)) {
            wp_send_json_error(array('message' => $this->get_campaign_send_error_message($send_result, $strings)), 400);
        }

        $messages[$found_index]['message_status'] = 'sent';
        $messages[$found_index]['sent_at'] = current_time('mysql');
        $messages[$found_index]['sent_count'] = (int) $send_result;
        update_option(self::OPTION_CAMPAIGN_MESSAGES, $messages, false);

        ob_start();
        $this->render_campaign_messages_list_content($strings, $messages);
        $list_html = ob_get_clean();

        wp_send_json_success(array(
            'message' => sprintf($strings['notice_sent'], number_format_i18n((int) $send_result)),
            'listHtml' => $list_html,
        ));
    }

    public function ajax_campaign_message_quick_send() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'smark')), 403);
        }

        check_ajax_referer('smark_email_campaign_message_ajax', 'nonce');

        $current_lang = get_option('smark_panel_language', 'en');
        $current_lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $strings = $this->get_campaign_message_strings($current_lang);
        $campaign_message = $this->build_campaign_message_from_request();

        if (is_wp_error($campaign_message)) {
            wp_send_json_error(array('message' => $strings['notice_error']), 400);
        }

        $send_result = $this->send_campaign_message($campaign_message);
        if (is_wp_error($send_result)) {
            wp_send_json_error(array('message' => $this->get_campaign_send_error_message($send_result, $strings)), 400);
        }

        $campaign_message['message_status'] = 'sent';
        $campaign_message['sent_at'] = current_time('mysql');
        $campaign_message['sent_count'] = (int) $send_result;
        $messages = $this->upsert_campaign_message($campaign_message);

        ob_start();
        $this->render_campaign_messages_list_content($strings, $messages);
        $list_html = ob_get_clean();

        wp_send_json_success(array(
            'message' => sprintf($strings['notice_sent'], number_format_i18n((int) $send_result)),
            'listHtml' => $list_html,
        ));
    }

    public function ajax_campaign_message_test_send() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'smark')), 403);
        }

        check_ajax_referer('smark_email_campaign_message_ajax', 'nonce');

        $current_lang = get_option('smark_panel_language', 'en');
        $current_lang = ($current_lang === 'fa') ? 'fa' : 'en';
        $strings = $this->get_campaign_message_strings($current_lang);
        $campaign_message = $this->build_campaign_message_from_request();

        if (is_wp_error($campaign_message)) {
            wp_send_json_error(array('message' => $strings['notice_error']), 400);
        }

        $test_email = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';
        if ($test_email === '' || !is_email($test_email)) {
            wp_send_json_error(array('message' => $strings['notice_test_send_error']), 400);
        }

        $send_result = $this->send_campaign_test_message($campaign_message, $test_email);
        if (is_wp_error($send_result)) {
            wp_send_json_error(array('message' => $this->get_campaign_send_error_message($send_result, $strings)), 400);
        }

        wp_send_json_success(array(
            'message' => sprintf($strings['notice_test_sent'], $test_email),
        ));
    }

    public function ajax_campaign_activity_page() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'smark')), 403);
        }

        check_ajax_referer('smark_email_campaign_message_ajax', 'nonce');

        $campaign_id = isset($_POST['campaign_id']) ? sanitize_text_field(wp_unslash($_POST['campaign_id'])) : '';
        $page = isset($_POST['page_number']) ? absint(wp_unslash($_POST['page_number'])) : 1;
        $event_filter = isset($_POST['event_filter']) ? sanitize_key(wp_unslash($_POST['event_filter'])) : 'all';
        $strings = $this->get_performance_strings($this->get_current_panel_lang());

        if ($campaign_id === '') {
            wp_send_json_error(array('message' => $strings['empty_state']), 400);
        }

        ob_start();
        $this->render_campaign_activity_table($campaign_id, $strings, $page, 100, $event_filter);
        $activity_html = ob_get_clean();

        wp_send_json_success(array(
            'activityHtml' => $activity_html,
        ));
    }

    public function ajax_campaign_failure_retry_start() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'smark')), 403);
        }

        check_ajax_referer('smark_email_campaign_message_ajax', 'nonce');

        $current_lang = $this->get_current_panel_lang();
        $strings = array_merge($this->get_campaign_message_strings($current_lang), $this->get_performance_strings($current_lang));
        $retry_count = isset($_POST['retry_count']) ? absint(wp_unslash($_POST['retry_count'])) : 0;
        $sender_account_id = isset($_POST['sender_account_id']) ? sanitize_text_field(wp_unslash($_POST['sender_account_id'])) : '';
        $campaign_id = isset($_POST['campaign_id']) ? sanitize_text_field(wp_unslash($_POST['campaign_id'])) : '';

        $session = $this->create_campaign_failure_retry_session($retry_count, $sender_account_id, $campaign_id);
        if (is_wp_error($session)) {
            wp_send_json_error(array('message' => $this->get_campaign_send_error_message($session, $strings)), 400);
        }

        wp_send_json_success($this->format_campaign_failure_retry_response($session, $strings));
    }

    public function ajax_campaign_failure_retry_batch() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'smark')), 403);
        }

        check_ajax_referer('smark_email_campaign_message_ajax', 'nonce');

        $current_lang = $this->get_current_panel_lang();
        $strings = array_merge($this->get_campaign_message_strings($current_lang), $this->get_performance_strings($current_lang));
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        $session = $this->get_campaign_send_session($session_id);

        if (empty($session) || (($session['type'] ?? '') !== 'failure_retry')) {
            wp_send_json_error(array('message' => $strings['notice_send_error']), 404);
        }

        $session = $this->process_campaign_failure_retry_batch($session, $strings);
        if (is_wp_error($session)) {
            $this->delete_campaign_send_session($session_id);
            wp_send_json_error(array('message' => $this->get_campaign_send_error_message($session, $strings)), 400);
        }

        $response = $this->format_campaign_failure_retry_response($session, $strings);
        if (!empty($session['complete'])) {
            $this->delete_campaign_send_session($session_id);
            $response['message'] = sprintf($strings['failure_retry_complete_notice'], number_format_i18n((int) ($session['sent_count'] ?? 0)), number_format_i18n((int) ($session['failed_count'] ?? 0)));
        } else {
            $this->save_campaign_send_session($session);
        }

        ob_start();
        $this->render_overall_campaign_stats_grid($this->get_campaign_performance_metrics(''), $this->get_performance_strings($current_lang));
        $response['overallHtml'] = ob_get_clean();

        wp_send_json_success($response);
    }

    public function handle_contacts_import_preview() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_contacts_import_preview', 'smark_email_contacts_import_nonce');

        $preview = $this->create_contacts_import_preview_from_upload();
        if (is_wp_error($preview)) {
            $this->redirect_to_contacts('error');
        }

        wp_safe_redirect(add_query_arg(
            array(
                'page' => 'smark-dashboard-page',
                'smark_email_view' => 'contacts',
                'import_token' => sanitize_key($preview['token']),
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function ajax_contacts_import_preview() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        check_ajax_referer('smark_email_contacts_import_ajax', 'nonce');

        $preview = $this->create_contacts_import_preview_from_upload();
        if (is_wp_error($preview)) {
            wp_send_json_error(array('message' => $preview->get_error_message()), 400);
        }

        $strings = $this->get_contact_strings($this->get_current_panel_lang());
        ob_start();
        $this->render_contacts_import_mapping($strings, $preview['token'], $preview['payload']);
        $mapping_html = ob_get_clean();

        wp_send_json_success(array(
            'token' => $preview['token'],
            'mappingHtml' => $mapping_html,
            'message' => sprintf($strings['bulk_mapping_description'], count($preview['payload']['rows'])),
        ));
    }

    public function handle_contacts_import() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_contacts_import', 'smark_email_contacts_import_nonce');

        $token = isset($_POST['import_token']) ? sanitize_key(wp_unslash($_POST['import_token'])) : '';
        $payload = $this->get_contacts_import_payload($token);
        if (empty($payload['rows']) || empty($payload['headers'])) {
            $this->redirect_to_contacts('error');
        }

        $mapping = $this->sanitize_contacts_import_mapping(isset($_POST['mapping']) && is_array($_POST['mapping']) ? wp_unslash($_POST['mapping']) : array());

        if (!isset($mapping['email_address']) || $mapping['email_address'] === '') {
            $this->redirect_to_contacts('error');
        }

        $default_segment = isset($_POST['default_segment']) ? sanitize_text_field(wp_unslash($_POST['default_segment'])) : '';
        $imported_count = $this->import_contacts_from_payload($payload, $mapping, $default_segment);
        delete_transient($this->get_contacts_import_transient_key($token));

        if ($imported_count <= 0) {
            $this->redirect_to_contacts('no_import');
        }

        $this->redirect_to_contacts('imported', array('imported' => $imported_count));
    }

    public function ajax_contacts_import() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        check_ajax_referer('smark_email_contacts_import_ajax', 'nonce');

        $strings = $this->get_contact_strings($this->get_current_panel_lang());
        $token = isset($_POST['import_token']) ? sanitize_key(wp_unslash($_POST['import_token'])) : '';
        $payload = $this->get_contacts_import_payload($token);
        if (empty($payload['rows']) || empty($payload['headers'])) {
            wp_send_json_error(array('message' => $strings['notice_error']), 400);
        }

        $mapping = $this->sanitize_contacts_import_mapping(isset($_POST['mapping']) && is_array($_POST['mapping']) ? wp_unslash($_POST['mapping']) : array());
        if (!isset($mapping['email_address']) || $mapping['email_address'] === '') {
            wp_send_json_error(array('message' => $strings['notice_error']), 400);
        }

        $default_segment = isset($_POST['default_segment']) ? sanitize_text_field(wp_unslash($_POST['default_segment'])) : '';
        $imported_count = $this->import_contacts_from_payload($payload, $mapping, $default_segment);
        delete_transient($this->get_contacts_import_transient_key($token));

        if ($imported_count <= 0) {
            wp_send_json_error(array('message' => $strings['notice_no_import']), 400);
        }

        ob_start();
        $this->render_contacts_list_content($strings, $this->get_contacts(), $this->get_contact_lists(), $this->get_contact_tags(), $this->get_daily_sent_contact_hashes());
        $list_html = ob_get_clean();

        wp_send_json_success(array(
            'imported' => $imported_count,
            'message' => sprintf($strings['notice_imported'], number_format_i18n($imported_count)),
            'listHtml' => $list_html,
        ));
    }

    public function ajax_contacts_page() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        check_ajax_referer('smark_email_contacts_page_ajax', 'nonce');

        $page = isset($_POST['page_number']) ? absint(wp_unslash($_POST['page_number'])) : 1;
        $search_query = isset($_POST['search_query']) ? sanitize_text_field(wp_unslash($_POST['search_query'])) : '';
        $strings = $this->get_contact_strings($this->get_current_panel_lang());

        ob_start();
        $this->render_contacts_list_content($strings, $this->get_contacts(), $this->get_contact_lists(), $this->get_contact_tags(), $this->get_daily_sent_contact_hashes(), $page, 100, $search_query);
        $list_html = ob_get_clean();

        wp_send_json_success(array(
            'listHtml' => $list_html,
        ));
    }

    public function ajax_campaign_message_send_start() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'smark')), 403);
        }

        check_ajax_referer('smark_email_campaign_message_ajax', 'nonce');

        $strings = $this->get_campaign_message_strings($this->get_current_panel_lang());
        $message_id = isset($_POST['message_id']) ? sanitize_text_field(wp_unslash($_POST['message_id'])) : '';
        $campaign_message = $this->get_campaign_message_by_id($message_id);

        if (empty($campaign_message)) {
            wp_send_json_error(array('message' => $strings['notice_send_error']), 404);
        }

        $session = $this->create_campaign_send_session($campaign_message, 'saved');
        if (is_wp_error($session)) {
            wp_send_json_error(array('message' => $this->get_campaign_send_error_message($session, $strings)), 400);
        }

        wp_send_json_success($this->format_campaign_send_progress_response($session, $strings));
    }

    public function ajax_campaign_message_quick_send_start() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'smark')), 403);
        }

        check_ajax_referer('smark_email_campaign_message_ajax', 'nonce');

        $strings = $this->get_campaign_message_strings($this->get_current_panel_lang());
        $campaign_message = $this->build_campaign_message_from_request();

        if (is_wp_error($campaign_message)) {
            wp_send_json_error(array('message' => $strings['notice_error']), 400);
        }

        $session = $this->create_campaign_send_session($campaign_message, 'quick');
        if (is_wp_error($session)) {
            wp_send_json_error(array('message' => $this->get_campaign_send_error_message($session, $strings)), 400);
        }

        wp_send_json_success($this->format_campaign_send_progress_response($session, $strings));
    }

    public function ajax_campaign_message_send_batch() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to perform this action.', 'smark')), 403);
        }

        check_ajax_referer('smark_email_campaign_message_ajax', 'nonce');

        $strings = $this->get_campaign_message_strings($this->get_current_panel_lang());
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
        $session = $this->get_campaign_send_session($session_id);

        if (empty($session)) {
            wp_send_json_error(array('message' => $strings['notice_send_error']), 404);
        }

        $session = $this->process_campaign_send_session_batch($session, $strings);
        if (is_wp_error($session)) {
            $this->delete_campaign_send_session($session_id);
            wp_send_json_error(array('message' => $this->get_campaign_send_error_message($session, $strings)), 400);
        }

        $response = $this->format_campaign_send_progress_response($session, $strings);
        if (!empty($session['complete'])) {
            $messages = $this->finalize_campaign_send_session($session);
            $this->delete_campaign_send_session($session_id);

            ob_start();
            $this->render_campaign_messages_list_content($strings, $messages);
            $response['listHtml'] = ob_get_clean();
            $response['message'] = sprintf($strings['notice_sent'], number_format_i18n((int) ($session['sent_count'] ?? 0)));
        } else {
            $this->save_campaign_send_session($session);
        }

        wp_send_json_success($response);
    }

    private function get_email_accounts() {
        $accounts = get_option(self::OPTION_EMAIL_ACCOUNTS, array());
        if (!is_array($accounts)) {
            return array();
        }

        $needs_migration = false;
        foreach ($accounts as $index => $account) {
            if (!is_array($account)) {
                unset($accounts[$index]);
                $needs_migration = true;
                continue;
            }

            if (isset($account['app_password']) && is_string($account['app_password']) && $account['app_password'] !== '') {
                if ($this->is_encrypted_email_secret($account['app_password'])) {
                    $decrypted = $this->decrypt_email_secret($account['app_password']);
                    if ($decrypted !== false) {
                        $accounts[$index]['app_password'] = $decrypted;
                    } else {
                        $accounts[$index]['app_password'] = '';
                    }
                } else {
                    $needs_migration = true;
                }
            }
        }

        $accounts = array_values($accounts);

        if ($needs_migration) {
            $this->save_email_accounts($accounts);
        }

        return $accounts;
    }

    private function save_email_accounts($accounts) {
        $accounts = is_array($accounts) ? $accounts : array();

        foreach ($accounts as $index => $account) {
            if (!is_array($account)) {
                unset($accounts[$index]);
                continue;
            }

            if (!empty($account['app_password']) && is_string($account['app_password']) && !$this->is_encrypted_email_secret($account['app_password'])) {
                $accounts[$index]['app_password'] = $this->encrypt_email_secret($account['app_password']);
            }
        }

        update_option(self::OPTION_EMAIL_ACCOUNTS, array_values($accounts), false);
    }

    private function is_encrypted_email_secret($value) {
        return is_string($value) && strpos($value, self::EMAIL_SECRET_PREFIX) === 0;
    }

    private function get_email_secret_key() {
        $material = wp_salt('auth') . '|' . wp_salt('secure_auth') . '|' . home_url('/');
        return hash('sha256', $material, true);
    }

    private function encrypt_email_secret($plain_text) {
        $plain_text = is_string($plain_text) ? $plain_text : '';
        if ($plain_text === '' || $this->is_encrypted_email_secret($plain_text) || !function_exists('openssl_encrypt')) {
            return $plain_text;
        }

        $cipher = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($cipher);
        if (!$iv_length) {
            return $plain_text;
        }

        $iv = random_bytes($iv_length);
        $key = $this->get_email_secret_key();
        $cipher_text = openssl_encrypt($plain_text, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher_text === false) {
            return $plain_text;
        }

        $iv_b64 = base64_encode($iv);
        $cipher_b64 = base64_encode($cipher_text);
        $mac = hash_hmac('sha256', $iv_b64 . '.' . $cipher_b64, $key);

        return self::EMAIL_SECRET_PREFIX . base64_encode(wp_json_encode(array(
            'iv' => $iv_b64,
            'value' => $cipher_b64,
            'mac' => $mac,
        )));
    }

    private function decrypt_email_secret($encrypted) {
        if (!$this->is_encrypted_email_secret($encrypted) || !function_exists('openssl_decrypt')) {
            return false;
        }

        $payload_raw = base64_decode(substr($encrypted, strlen(self::EMAIL_SECRET_PREFIX)), true);
        $payload = is_string($payload_raw) ? json_decode($payload_raw, true) : null;
        if (!is_array($payload) || empty($payload['iv']) || empty($payload['value']) || empty($payload['mac'])) {
            return false;
        }

        $key = $this->get_email_secret_key();
        $expected_mac = hash_hmac('sha256', $payload['iv'] . '.' . $payload['value'], $key);
        if (!hash_equals($expected_mac, (string) $payload['mac'])) {
            return false;
        }

        $iv = base64_decode((string) $payload['iv'], true);
        $cipher_text = base64_decode((string) $payload['value'], true);
        if (!is_string($iv) || !is_string($cipher_text)) {
            return false;
        }

        $plain_text = openssl_decrypt($cipher_text, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return is_string($plain_text) ? $plain_text : false;
    }

    private function get_contacts() {
        $contacts = get_option(self::OPTION_CONTACTS, array());
        return is_array($contacts) ? $contacts : array();
    }

    private function get_contact_lists() {
        return $this->normalize_contact_entities(get_option(self::OPTION_CONTACT_LISTS, array()));
    }

    private function get_contact_tags() {
        return $this->normalize_contact_entities(get_option(self::OPTION_CONTACT_TAGS, array()));
    }

    private function normalize_contact_entities($entities) {
        if (!is_array($entities)) {
            return array();
        }

        $normalized = array();
        foreach ($entities as $entity) {
            if (!is_array($entity) || empty($entity['id']) || empty($entity['name'])) {
                continue;
            }

            $normalized[] = array(
                'id' => sanitize_text_field((string) $entity['id']),
                'name' => sanitize_text_field((string) $entity['name']),
                'contact_ids' => isset($entity['contact_ids']) && is_array($entity['contact_ids'])
                    ? array_values(array_filter(array_unique(array_map('sanitize_text_field', $entity['contact_ids']))))
                    : array(),
                'created_at' => isset($entity['created_at']) ? sanitize_text_field((string) $entity['created_at']) : current_time('mysql'),
                'updated_at' => isset($entity['updated_at']) ? sanitize_text_field((string) $entity['updated_at']) : '',
            );
        }

        return $normalized;
    }

    private function sanitize_contact_ids_from_request() {
        $contact_ids = isset($_POST['contact_ids']) && is_array($_POST['contact_ids'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['contact_ids']))
            : array();
        $valid_contact_ids = array();

        foreach ($this->get_contacts() as $contact) {
            if (!empty($contact['id'])) {
                $valid_contact_ids[(string) $contact['id']] = true;
            }
        }

        return array_values(array_filter(array_unique($contact_ids), function($contact_id) use ($valid_contact_ids) {
            return isset($valid_contact_ids[(string) $contact_id]);
        }));
    }

    private function upsert_contact_entity($entities, $entity_id, $name, $contact_ids) {
        $entities = $this->normalize_contact_entities($entities);
        $entity_id = sanitize_text_field($entity_id);
        $name = sanitize_text_field($name);
        $contact_ids = array_values(array_filter(array_unique(array_map('sanitize_text_field', (array) $contact_ids))));
        $updated = false;

        foreach ($entities as $index => $entity) {
            if ($entity_id !== '' && isset($entity['id']) && $entity['id'] === $entity_id) {
                $entities[$index]['name'] = $name;
                $entities[$index]['contact_ids'] = $contact_ids;
                $entities[$index]['updated_at'] = current_time('mysql');
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $entities[] = array(
                'id' => wp_generate_uuid4(),
                'name' => $name,
                'contact_ids' => $contact_ids,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            );
        }

        return array_values($entities);
    }

    private function remove_contact_from_contact_entities($contact_id) {
        $contact_id = sanitize_text_field($contact_id);
        if ($contact_id === '') {
            return;
        }

        foreach (array(self::OPTION_CONTACT_LISTS, self::OPTION_CONTACT_TAGS) as $option_name) {
            $entities = $this->normalize_contact_entities(get_option($option_name, array()));
            foreach ($entities as $index => $entity) {
                $entities[$index]['contact_ids'] = array_values(array_filter($entity['contact_ids'], function($saved_contact_id) use ($contact_id) {
                    return (string) $saved_contact_id !== $contact_id;
                }));
            }
            update_option($option_name, $entities, false);
        }
    }

    private function get_assigned_contact_ids_for_entity($entity) {
        return isset($entity['contact_ids']) && is_array($entity['contact_ids']) ? array_values($entity['contact_ids']) : array();
    }

    private function get_contact_ids_from_contacts($contacts) {
        $contact_ids = array();
        foreach ($contacts as $contact) {
            if (!empty($contact['id'])) {
                $contact_ids[] = (string) $contact['id'];
            }
        }
        return $contact_ids;
    }

    private function get_contact_entity_by_id($entity_id, $entities) {
        $entity_id = sanitize_text_field($entity_id);
        if ($entity_id === '') {
            return array();
        }

        foreach ($entities as $entity) {
            if (!empty($entity['id']) && (string) $entity['id'] === $entity_id) {
                return $entity;
            }
        }

        return array();
    }

    private function add_contact_to_contact_entities($contact_id, $entity_ids, $option_name) {
        $contact_id = sanitize_text_field($contact_id);
        $entity_ids = array_values(array_filter(array_unique(array_map('sanitize_text_field', (array) $entity_ids))));
        if ($contact_id === '' || empty($entity_ids)) {
            return;
        }

        $entities = $this->normalize_contact_entities(get_option($option_name, array()));
        $changed = false;
        foreach ($entities as $index => $entity) {
            if (empty($entity['id']) || !in_array((string) $entity['id'], $entity_ids, true)) {
                continue;
            }

            $contact_ids = $this->get_assigned_contact_ids_for_entity($entity);
            if (!in_array($contact_id, array_map('strval', $contact_ids), true)) {
                $contact_ids[] = $contact_id;
                $entities[$index]['contact_ids'] = array_values(array_unique($contact_ids));
                $entities[$index]['updated_at'] = current_time('mysql');
                $changed = true;
            }
        }

        if ($changed) {
            update_option($option_name, $entities, false);
        }
    }

    private function set_contact_entities_for_contact($contact_id, $entity_ids, $option_name) {
        $contact_id = sanitize_text_field($contact_id);
        if ($contact_id === '') {
            return;
        }

        $entity_ids = array_values(array_filter(array_unique(array_map('sanitize_text_field', (array) $entity_ids))));
        $entities = $this->normalize_contact_entities(get_option($option_name, array()));
        $changed = false;

        foreach ($entities as $index => $entity) {
            if (empty($entity['id'])) {
                continue;
            }

            $entity_changed = false;
            $should_have_contact = in_array((string) $entity['id'], $entity_ids, true);
            $contact_ids = array_values(array_unique(array_map('strval', $this->get_assigned_contact_ids_for_entity($entity))));
            $has_contact = in_array($contact_id, $contact_ids, true);

            if ($should_have_contact && !$has_contact) {
                $contact_ids[] = $contact_id;
                $entity_changed = true;
            } elseif (!$should_have_contact && $has_contact) {
                $contact_ids = array_values(array_filter($contact_ids, function($saved_contact_id) use ($contact_id) {
                    return (string) $saved_contact_id !== $contact_id;
                }));
                $entity_changed = true;
            }

            if ($entity_changed) {
                $entities[$index]['contact_ids'] = $contact_ids;
                $entities[$index]['updated_at'] = current_time('mysql');
                $changed = true;
            }
        }

        if ($changed) {
            update_option($option_name, $entities, false);
        }
    }

    private function get_contact_entity_names_for_contact($contact_id, $entities) {
        $contact_id = (string) $contact_id;
        if ($contact_id === '') {
            return array();
        }

        $names = array();
        foreach ($entities as $entity) {
            $contact_ids = $this->get_assigned_contact_ids_for_entity($entity);
            if (in_array($contact_id, array_map('strval', $contact_ids), true) && !empty($entity['name'])) {
                $names[] = (string) $entity['name'];
            }
        }

        return $names;
    }

    private function get_contact_entity_ids_for_contact($contact_id, $entities) {
        $contact_id = (string) $contact_id;
        if ($contact_id === '') {
            return array();
        }

        $ids = array();
        foreach ($entities as $entity) {
            $contact_ids = $this->get_assigned_contact_ids_for_entity($entity);
            if (in_array($contact_id, array_map('strval', $contact_ids), true) && !empty($entity['id'])) {
                $ids[] = (string) $entity['id'];
            }
        }

        return $ids;
    }

    private function get_campaign_messages() {
        $messages = get_option(self::OPTION_CAMPAIGN_MESSAGES, array());
        return is_array($messages) ? $messages : array();
    }

    private function upsert_campaign_message($campaign_message) {
        $messages = $this->get_campaign_messages();
        $updated = false;

        foreach ($messages as $index => $message) {
            if (isset($message['id'], $campaign_message['id']) && $message['id'] === $campaign_message['id']) {
                $campaign_message['created_at'] = isset($message['created_at']) ? $message['created_at'] : $campaign_message['created_at'];
                $campaign_message['updated_at'] = current_time('mysql');
                $messages[$index] = array_merge($message, $campaign_message);
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $campaign_message['updated_at'] = current_time('mysql');
            $messages[] = $campaign_message;
        }

        update_option(self::OPTION_CAMPAIGN_MESSAGES, $messages, false);

        return $messages;
    }

    private function get_campaign_events() {
        $events = get_option(self::OPTION_CAMPAIGN_EVENTS, array());
        return is_array($events) ? $events : array();
    }

    private function get_daily_sent_contact_hashes() {
        $today = current_time('Y-m-d');
        $hashes = array();

        foreach ($this->get_campaign_events() as $event) {
            if (($event['type'] ?? '') !== 'sent') {
                continue;
            }

            $created_at = isset($event['created_at']) ? (string) $event['created_at'] : '';
            if (substr($created_at, 0, 10) !== $today) {
                continue;
            }

            $recipient_hash = isset($event['recipient_hash']) ? (string) $event['recipient_hash'] : '';
            if ($recipient_hash !== '') {
                $hashes[$recipient_hash] = true;
            }
        }

        return $hashes;
    }

    private function get_today_sent_contact_ids($contacts) {
        $daily_sent_hashes = $this->get_daily_sent_contact_hashes();
        $contact_ids = array();

        foreach ($contacts as $contact) {
            $contact_id = isset($contact['id']) ? (string) $contact['id'] : '';
            $email = isset($contact['email_address']) ? sanitize_email($contact['email_address']) : '';
            if ($contact_id === '' || $email === '') {
                continue;
            }

            if (isset($daily_sent_hashes[$this->get_campaign_recipient_hash($email)])) {
                $contact_ids[] = $contact_id;
            }
        }

        return array_values(array_unique($contact_ids));
    }

    private function get_email_account_daily_sent_counts() {
        $today = current_time('Y-m-d');
        $campaign_senders = array();
        foreach ($this->get_campaign_messages() as $message) {
            $message_id = isset($message['id']) ? (string) $message['id'] : '';
            $sender_account_id = isset($message['sender_account_id']) ? (string) $message['sender_account_id'] : '';
            if ($message_id !== '' && $sender_account_id !== '') {
                $campaign_senders[$message_id] = $sender_account_id;
            }
        }

        $counts = array();
        foreach ($this->get_campaign_events() as $event) {
            if (($event['type'] ?? '') !== 'sent') {
                continue;
            }

            $created_at = isset($event['created_at']) ? (string) $event['created_at'] : '';
            if (substr($created_at, 0, 10) !== $today) {
                continue;
            }

            $account_id = isset($event['account_id']) ? (string) $event['account_id'] : '';
            if ($account_id === '') {
                $campaign_id = isset($event['campaign_id']) ? (string) $event['campaign_id'] : '';
                $account_id = isset($campaign_senders[$campaign_id]) ? $campaign_senders[$campaign_id] : '';
            }

            if ($account_id === '') {
                continue;
            }

            if (!isset($counts[$account_id])) {
                $counts[$account_id] = 0;
            }
            $counts[$account_id]++;
        }

        return $counts;
    }

    private function get_campaign_event_timestamp($event) {
        if (empty($event['created_at'])) {
            return 0;
        }

        $timestamp = strtotime((string) $event['created_at']);
        return $timestamp ? (int) $timestamp : 0;
    }

    private function get_campaign_sent_event_times($events) {
        $sent_times = array();

        foreach ($events as $event) {
            if (($event['type'] ?? '') !== 'sent') {
                continue;
            }

            $campaign_id = isset($event['campaign_id']) ? (string) $event['campaign_id'] : '';
            $recipient_hash = isset($event['recipient_hash']) ? (string) $event['recipient_hash'] : '';
            $sent_at = $this->get_campaign_event_timestamp($event);

            if ($campaign_id === '' || $recipient_hash === '' || !$sent_at) {
                continue;
            }

            $key = $campaign_id . ':' . $recipient_hash;
            if (!isset($sent_times[$key]) || $sent_at < $sent_times[$key]) {
                $sent_times[$key] = $sent_at;
            }
        }

        return $sent_times;
    }

    private function get_campaign_open_grace_period() {
        return (int) apply_filters('smark_email_open_tracking_grace_period', 10);
    }

    private function is_countable_campaign_open_event($event, $sent_times) {
        $campaign_id = isset($event['campaign_id']) ? (string) $event['campaign_id'] : '';
        $recipient_hash = isset($event['recipient_hash']) ? (string) $event['recipient_hash'] : '';

        if ($campaign_id === '' || $recipient_hash === '') {
            return false;
        }

        $key = $campaign_id . ':' . $recipient_hash;
        if (empty($sent_times[$key])) {
            return false;
        }

        $opened_at = $this->get_campaign_event_timestamp($event);
        if (!$opened_at) {
            return false;
        }

        $event_user_agent = isset($event['user_agent']) ? (string) $event['user_agent'] : '';
        if ($this->is_suspected_campaign_open_scanner($event_user_agent)) {
            return false;
        }

        $grace_period = max(0, $this->get_campaign_open_grace_period());
        return ($opened_at - (int) $sent_times[$key]) >= $grace_period;
    }

    private function is_suspected_campaign_open_scanner($user_agent = null) {
        if ($user_agent === null) {
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
        }

        $user_agent = strtolower((string) $user_agent);

        if ($user_agent === '') {
            return true;
        }

        $prefetch_headers = array('HTTP_PURPOSE', 'HTTP_SEC_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ');
        foreach ($prefetch_headers as $header) {
            $value = isset($_SERVER[$header]) ? strtolower((string) wp_unslash($_SERVER[$header])) : '';
            if ($value !== '' && (strpos($value, 'prefetch') !== false || strpos($value, 'preview') !== false)) {
                return true;
            }
        }

        $scanner_signatures = array(
            'bot',
            'crawler',
            'spider',
            'preview',
            'headless',
            'phantom',
            'curl',
            'wget',
            'python-requests',
            'okhttp',
            'facebookexternalhit',
            'slackbot',
            'twitterbot',
            'discordbot',
            'telegrambot',
            'whatsapp',
            'skypeuripreview',
            'linkpreview',
            'urlscan',
            'barracuda',
            'proofpoint',
            'mimecast',
            'prefetch',
        );

        foreach ($scanner_signatures as $signature) {
            if (strpos($user_agent, $signature) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_campaign_open_privacy_proxy($user_agent = null) {
        if ($user_agent === null) {
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
        }

        $user_agent = strtolower((string) $user_agent);
        if ($user_agent === '') {
            return true;
        }

        $proxy_signatures = array(
            'googleimageproxy',
            'googleusercontent',
            'mailprivacy',
            'mail privacy',
            'dataprovider',
        );

        foreach ($proxy_signatures as $signature) {
            if (strpos($user_agent, $signature) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_recordable_campaign_open($campaign_id, $recipient_hash) {
        $campaign_id = sanitize_text_field($campaign_id);
        $recipient_hash = sanitize_text_field($recipient_hash);

        if ($campaign_id === '' || $recipient_hash === '') {
            return false;
        }

        if ($this->is_suspected_campaign_open_scanner()) {
            return false;
        }

        $events = $this->get_campaign_events();
        $sent_times = $this->get_campaign_sent_event_times($events);
        $key = $campaign_id . ':' . $recipient_hash;

        if (empty($sent_times[$key])) {
            return false;
        }

        $grace_period = max(0, $this->get_campaign_open_grace_period());
        $now = current_time('timestamp');
        if ($grace_period > 0 && ($now - (int) $sent_times[$key]) < $grace_period) {
            return false;
        }

        return true;
    }

    private function get_campaign_performance_metrics($campaign_id = '') {
        $messages = $this->get_campaign_messages();
        $events = $this->get_campaign_events();
        $sent_times = $this->get_campaign_sent_event_times($events);
        $metrics = array(
            'campaigns' => 0,
            'sent' => 0,
            'failed' => 0,
            'opens' => 0,
            'unique_opens' => 0,
            'clicks' => 0,
            'unique_clicks' => 0,
            'unsubscribes' => 0,
            'bounces' => 0,
        );

        $campaign_ids = array();
        foreach ($messages as $message) {
            if ($campaign_id !== '' && (!isset($message['id']) || $message['id'] !== $campaign_id)) {
                continue;
            }

            $message_id = isset($message['id']) ? (string) $message['id'] : '';
            if ($message_id === '') {
                continue;
            }

            $campaign_ids[$message_id] = true;
            $metrics['campaigns']++;
            $metrics['sent'] += !empty($message['sent_count']) ? (int) $message['sent_count'] : 0;
        }

        $unique_opens = array();
        $unique_clicks = array();
        $event_sent_count = 0;
        foreach ($events as $event) {
            $event_campaign_id = isset($event['campaign_id']) ? (string) $event['campaign_id'] : '';
            if ($event_campaign_id === '' || !isset($campaign_ids[$event_campaign_id])) {
                continue;
            }

            $type = isset($event['type']) ? (string) $event['type'] : '';
            $recipient_hash = isset($event['recipient_hash']) ? (string) $event['recipient_hash'] : '';

            if ($type === 'sent') {
                $event_sent_count++;
            } elseif ($type === 'failed') {
                $metrics['failed']++;
            } elseif ($type === 'open') {
                if (!$this->is_countable_campaign_open_event($event, $sent_times)) {
                    continue;
                }
                $metrics['opens']++;
                if ($recipient_hash !== '') {
                    $unique_opens[$event_campaign_id . ':' . $recipient_hash] = true;
                }
            } elseif ($type === 'click') {
                $metrics['clicks']++;
                if ($recipient_hash !== '') {
                    $unique_clicks[$event_campaign_id . ':' . $recipient_hash] = true;
                }
            } elseif ($type === 'unsubscribe') {
                $metrics['unsubscribes']++;
            } elseif ($type === 'bounce') {
                $metrics['bounces']++;
            }
        }

        if ($event_sent_count > $metrics['sent']) {
            $metrics['sent'] = $event_sent_count;
        }

        $metrics['unique_opens'] = count($unique_opens);
        $metrics['unique_clicks'] = count($unique_clicks);
        $metrics['open_rate'] = $this->calculate_percentage($metrics['unique_opens'], $metrics['sent']);
        $metrics['click_rate'] = $this->calculate_percentage($metrics['unique_clicks'], $metrics['sent']);
        $metrics['ctor'] = $this->calculate_percentage($metrics['unique_clicks'], $metrics['unique_opens']);
        $metrics['delivery_rate'] = $this->calculate_percentage(max(0, $metrics['sent'] - $metrics['failed']), max(1, $metrics['sent']));

        return $metrics;
    }

    private function get_performance_metric_cards($metrics, $strings) {
        return array(
            array(
                'filter' => 'sent',
                'label' => $strings['metric_sent'],
                'value' => number_format_i18n((int) ($metrics['sent'] ?? 0)),
                'help' => $strings['metric_sent_help'],
            ),
            array(
                'filter' => 'open',
                'label' => $strings['metric_open_rate'],
                'value' => $this->format_percentage($metrics['open_rate'] ?? 0),
                'help' => sprintf($strings['metric_open_help'], number_format_i18n((int) ($metrics['unique_opens'] ?? 0)), number_format_i18n((int) ($metrics['opens'] ?? 0))),
            ),
            array(
                'filter' => 'click',
                'label' => $strings['metric_click_rate'],
                'value' => $this->format_percentage($metrics['click_rate'] ?? 0),
                'help' => sprintf($strings['metric_click_help'], number_format_i18n((int) ($metrics['unique_clicks'] ?? 0)), number_format_i18n((int) ($metrics['clicks'] ?? 0))),
            ),
            array(
                'filter' => 'open',
                'label' => $strings['metric_ctor'],
                'value' => $this->format_percentage($metrics['ctor'] ?? 0),
                'help' => $strings['metric_ctor_help'],
            ),
            array(
                'filter' => 'failed',
                'label' => $strings['metric_failed'],
                'value' => number_format_i18n((int) ($metrics['failed'] ?? 0)),
                'help' => $strings['metric_failed_help'],
            ),
            array(
                'filter' => 'all',
                'label' => $strings['metric_unsub_bounce'],
                'value' => number_format_i18n((int) ($metrics['unsubscribes'] ?? 0) + (int) ($metrics['bounces'] ?? 0)),
                'help' => sprintf($strings['metric_unsub_bounce_help'], number_format_i18n((int) ($metrics['unsubscribes'] ?? 0)), number_format_i18n((int) ($metrics['bounces'] ?? 0))),
            ),
        );
    }

    private function render_overall_campaign_stats_grid($overall, $strings) {
        ?>
        <div class="smark-email-metrics-grid">
            <?php foreach ($this->get_performance_metric_cards($overall, $strings) as $metric) : ?>
                <?php if (($metric['filter'] ?? '') === 'failed') : ?>
                    <button
                        type="button"
                        class="smark-email-metric-card smark-email-metric-card--button"
                        data-open-smark-failure-retry
                        data-failure-count="<?php echo esc_attr((int) ($overall['failed'] ?? 0)); ?>"
                    >
                        <span><?php echo esc_html($metric['label']); ?></span>
                        <strong><?php echo esc_html($metric['value']); ?></strong>
                        <small><?php echo esc_html($metric['help']); ?></small>
                    </button>
                <?php else : ?>
                    <div class="smark-email-metric-card">
                        <span><?php echo esc_html($metric['label']); ?></span>
                        <strong><?php echo esc_html($metric['value']); ?></strong>
                        <small><?php echo esc_html($metric['help']); ?></small>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_campaign_performance_detail($campaign_message, $performance_strings, $message_strings) {
        $campaign_id = isset($campaign_message['id']) ? (string) $campaign_message['id'] : '';
        if ($campaign_id === '') {
            return;
        }

        $metrics = $this->get_campaign_performance_metrics($campaign_id);
        ?>
        <div class="smark-email-performance-summary">
            <div>
                <span><?php echo esc_html($performance_strings['selected_campaign']); ?></span>
                <strong><?php echo esc_html($campaign_message['campaign_name']); ?></strong>
                <small><?php echo esc_html($campaign_message['subject_line']); ?></small>
            </div>
            <div>
                <span><?php echo esc_html($performance_strings['selected_status']); ?></span>
                <strong><?php echo esc_html($this->get_campaign_status_label($campaign_message['message_status'], $message_strings)); ?></strong>
                <small><?php echo esc_html(!empty($campaign_message['sent_at']) ? $campaign_message['sent_at'] : $performance_strings['not_sent_yet']); ?></small>
            </div>
            <div>
                <span><?php echo esc_html($performance_strings['selected_audience']); ?></span>
                <strong><?php echo esc_html($this->format_campaign_audience_summary($campaign_message, $message_strings)); ?></strong>
                <small><?php echo esc_html($performance_strings['audience_help']); ?></small>
            </div>
        </div>

        <div class="smark-email-metrics-grid smark-email-metrics-grid--detail">
            <?php foreach ($this->get_performance_metric_cards($metrics, $performance_strings) as $metric) : ?>
                <button type="button" class="smark-email-metric-card smark-email-metric-card--button" data-smark-activity-filter="<?php echo esc_attr($metric['filter']); ?>" data-campaign-id="<?php echo esc_attr($campaign_id); ?>">
                    <span><?php echo esc_html($metric['label']); ?></span>
                    <strong><?php echo esc_html($metric['value']); ?></strong>
                    <small><?php echo esc_html($metric['help']); ?></small>
                </button>
            <?php endforeach; ?>
        </div>

        <?php $this->render_campaign_activity_table($campaign_id, $performance_strings); ?>
        <?php
    }

    private function normalize_campaign_activity_filter($filter) {
        $filter = sanitize_key((string) $filter);
        return in_array($filter, array('all', 'sent', 'open', 'click', 'failed'), true) ? $filter : 'all';
    }

    private function render_campaign_activity_table($campaign_id, $strings, $current_page = 1, $per_page = 100, $event_filter = 'all') {
        $event_filter = $this->normalize_campaign_activity_filter($event_filter);
        $all_events = $this->get_campaign_events();
        $sent_times = $this->get_campaign_sent_event_times($all_events);
        $events = array_values(array_filter($all_events, function($event) use ($campaign_id, $sent_times) {
            if (!isset($event['campaign_id']) || $event['campaign_id'] !== $campaign_id) {
                return false;
            }

            if (($event['type'] ?? '') === 'open') {
                return $this->is_countable_campaign_open_event($event, $sent_times);
            }

            return true;
        }));

        $rows = array();
        foreach ($events as $event) {
            $recipient_hash = isset($event['recipient_hash']) ? (string) $event['recipient_hash'] : '';
            if ($recipient_hash === '') {
                $recipient_hash = 'unknown:' . (isset($event['recipient_label']) ? (string) $event['recipient_label'] : '');
            }

            $key = (isset($event['campaign_id']) ? (string) $event['campaign_id'] : '') . ':' . $recipient_hash;
            if (!isset($rows[$key])) {
                $rows[$key] = array(
                    'recipient_label' => '',
                    'recipient_email' => '',
                    'events' => array(),
                    'failure_details' => array(),
                    'links' => array(),
                    'times' => array(),
                    'latest_at' => '',
                );
            }

            $type = isset($event['type']) ? sanitize_key($event['type']) : '';
            $created_at = isset($event['created_at']) ? (string) $event['created_at'] : '';
            $recipient_label = isset($event['recipient_label']) ? (string) $event['recipient_label'] : '';

            if ($rows[$key]['recipient_label'] === '' && $recipient_label !== '') {
                $rows[$key]['recipient_label'] = $recipient_label;
            }

            if ($rows[$key]['recipient_email'] === '') {
                $rows[$key]['recipient_email'] = $this->get_campaign_recipient_email_from_event($event);
            }

            if ($type !== '') {
                if ($type === 'open' && isset($rows[$key]['events']['open'])) {
                    continue;
                }

                $rows[$key]['events'][$type] = $created_at;
                if ($type === 'failed') {
                    $rows[$key]['failure_details'] = isset($event['details']) && is_array($event['details']) ? $event['details'] : array();
                }
                if ($created_at !== '') {
                    $rows[$key]['times'][] = $this->get_campaign_event_label($type, $strings) . ': ' . $created_at;
                    if ($rows[$key]['latest_at'] === '' || strcmp($created_at, $rows[$key]['latest_at']) > 0) {
                        $rows[$key]['latest_at'] = $created_at;
                    }
                }
            }

            if (!empty($event['url'])) {
                $rows[$key]['links'][(string) $event['url']] = true;
            }
        }

        usort($rows, function($a, $b) {
            return strcmp((string) ($b['latest_at'] ?? ''), (string) ($a['latest_at'] ?? ''));
        });

        if ($event_filter !== 'all') {
            $rows = array_values(array_filter($rows, function($row) use ($event_filter) {
                return isset($row['events'][$event_filter]);
            }));
        }

        $total_rows = count($rows);
        $per_page = max(1, absint($per_page));
        $total_pages = max(1, (int) ceil($total_rows / $per_page));
        $current_page = min(max(1, absint($current_page)), $total_pages);
        $offset = ($current_page - 1) * $per_page;
        $visible_rows = array_slice($rows, $offset, $per_page);
        ?>
        <div class="smark-email-campaign-activity" data-smark-campaign-activity data-campaign-id="<?php echo esc_attr($campaign_id); ?>" data-event-filter="<?php echo esc_attr($event_filter); ?>">
            <div class="smark-email-table-wrap smark-email-activity-table">
                <table class="smark-email-accounts-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html($strings['column_event']); ?></th>
                            <th><?php echo esc_html($strings['column_recipient']); ?></th>
                            <th><?php echo esc_html($strings['column_link']); ?></th>
                            <th><?php echo esc_html($strings['column_time']); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($visible_rows)) : ?>
                            <tr>
                                <td colspan="4"><?php echo esc_html($strings['no_events']); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($visible_rows as $row) : ?>
                                <tr>
                                    <td>
                                        <div class="smark-email-event-badges">
                                            <?php foreach ($row['events'] as $type => $created_at) : ?>
                                                <?php if ($type === 'failed') : ?>
                                                    <button
                                                        type="button"
                                                        class="smark-email-status smark-email-status--failed smark-email-failure-detail-trigger"
                                                        data-open-smark-failure-detail
                                                        data-failure-title="<?php echo esc_attr($strings['failure_modal_title']); ?>"
                                                        data-failure-recipient="<?php echo esc_attr($row['recipient_email'] !== '' ? $row['recipient_email'] : $row['recipient_label']); ?>"
                                                        data-failure-reason="<?php echo esc_attr($this->format_campaign_failure_detail_text($row['failure_details'], $strings)); ?>"
                                                    ><?php echo esc_html($this->get_campaign_event_label($type, $strings)); ?></button>
                                                <?php else : ?>
                                                    <span class="smark-email-status smark-email-status--<?php echo esc_attr($type); ?>"><?php echo esc_html($this->get_campaign_event_label($type, $strings)); ?></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($row['recipient_email'] !== '' ? $row['recipient_email'] : ($row['recipient_label'] !== '' ? $row['recipient_label'] : $strings['unknown_recipient'])); ?></td>
                                    <td>
                                        <?php if (empty($row['links'])) : ?>
                                            <?php echo esc_html($strings['not_available']); ?>
                                        <?php else : ?>
                                            <div class="smark-email-event-links">
                                                <?php foreach (array_keys($row['links']) as $url) : ?>
                                                    <span><?php echo esc_html($url); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="smark-email-event-times">
                                            <?php foreach ($row['times'] as $time_label) : ?>
                                                <span><?php echo esc_html($time_label); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php $this->render_campaign_activity_pagination($strings, $current_page, $total_pages, $total_rows, $offset, count($visible_rows)); ?>
        </div>
        <?php
    }

    private function get_campaign_recipient_email_from_event($event) {
        $email = isset($event['recipient_email']) ? sanitize_email((string) $event['recipient_email']) : '';
        if ($email !== '' && is_email($email)) {
            return $email;
        }

        $recipient_hash = isset($event['recipient_hash']) ? (string) $event['recipient_hash'] : '';
        if ($recipient_hash === '') {
            return '';
        }

        foreach ($this->get_contacts() as $contact) {
            $contact_email = isset($contact['email_address']) ? sanitize_email((string) $contact['email_address']) : '';
            if ($contact_email !== '' && hash_equals($this->get_campaign_recipient_hash($contact_email), $recipient_hash)) {
                return $contact_email;
            }
        }

        return '';
    }

    private function render_campaign_activity_pagination($strings, $current_page, $total_pages, $total_rows, $offset, $visible_count) {
        if ($total_pages <= 1) {
            return;
        }

        $from = $offset + 1;
        $to = min($offset + $visible_count, $total_rows);
        $pages = array(1, $total_pages);
        for ($page = $current_page - 2; $page <= $current_page + 2; $page++) {
            if ($page > 1 && $page < $total_pages) {
                $pages[] = $page;
            }
        }
        $pages = array_values(array_unique($pages));
        sort($pages);
        ?>
        <nav class="smark-email-pagination" aria-label="<?php echo esc_attr($strings['activity_pagination_label']); ?>">
            <span class="smark-email-pagination__summary">
                <?php echo esc_html(sprintf($strings['activity_pagination_summary'], number_format_i18n($from), number_format_i18n($to), number_format_i18n($total_rows))); ?>
            </span>
            <div class="smark-email-pagination__links">
                <button type="button" class="button smark-email-pagination__button" data-smark-campaign-activity-page="<?php echo esc_attr($current_page - 1); ?>" <?php disabled($current_page <= 1); ?>>
                    <?php echo esc_html($strings['activity_pagination_previous']); ?>
                </button>
                <?php
                $last_page = 0;
                foreach ($pages as $page) :
                    if ($last_page && $page > $last_page + 1) :
                        ?>
                        <span class="smark-email-pagination__ellipsis"><?php echo esc_html($strings['activity_pagination_ellipsis']); ?></span>
                    <?php endif; ?>
                    <button type="button" class="button smark-email-pagination__button <?php echo $page === $current_page ? 'is-active' : ''; ?>" data-smark-campaign-activity-page="<?php echo esc_attr($page); ?>" <?php disabled($page === $current_page); ?>>
                        <?php echo esc_html(number_format_i18n($page)); ?>
                    </button>
                    <?php
                    $last_page = $page;
                endforeach;
                ?>
                <button type="button" class="button smark-email-pagination__button" data-smark-campaign-activity-page="<?php echo esc_attr($current_page + 1); ?>" <?php disabled($current_page >= $total_pages); ?>>
                    <?php echo esc_html($strings['activity_pagination_next']); ?>
                </button>
            </div>
        </nav>
        <?php
    }

    private function format_campaign_failure_detail_text($details, $strings) {
        $details = is_array($details) ? $details : array();
        $lines = array();

        if (!empty($details['reason'])) {
            $lines[] = $strings['failure_reason_label'] . ' ' . (string) $details['reason'];
        } elseif (!empty($details['message'])) {
            $lines[] = $strings['failure_reason_label'] . ' ' . (string) $details['message'];
        } else {
            $lines[] = $strings['failure_reason_unavailable'];
        }

        if (!empty($details['code'])) {
            $lines[] = $strings['failure_code_label'] . ' ' . (string) $details['code'];
        }

        if (!empty($details['message']) && (empty($details['reason']) || $details['message'] !== $details['reason'])) {
            $lines[] = $strings['failure_smtp_message_label'] . ' ' . (string) $details['message'];
        }

        if (!empty($details['sender_email'])) {
            $lines[] = $strings['failure_sender_label'] . ' ' . (string) $details['sender_email'];
        }

        if (!empty($details['smtp_host'])) {
            $smtp = (string) $details['smtp_host'];
            if (!empty($details['smtp_port'])) {
                $smtp .= ':' . (string) $details['smtp_port'];
            }
            $lines[] = $strings['failure_smtp_label'] . ' ' . $smtp;
        }

        $lines[] = '';
        $lines[] = $strings['failure_possible_reasons_label'];
        foreach ($this->get_campaign_failure_reason_catalog($strings) as $reason) {
            $lines[] = '- ' . $reason;
        }

        return implode("\n", $lines);
    }

    private function get_campaign_failure_reason_catalog($strings) {
        return array(
            $strings['failure_reason_auth'],
            $strings['failure_reason_connection'],
            $strings['failure_reason_timeout'],
            $strings['failure_reason_tls'],
            $strings['failure_reason_recipient'],
            $strings['failure_reason_rejected'],
            $strings['failure_reason_rate_limit'],
            $strings['failure_reason_dns'],
            $strings['failure_reason_sender'],
            $strings['failure_reason_content'],
        );
    }

    private function get_campaign_event_label($type, $strings) {
        $labels = array(
            'sent' => $strings['event_sent'],
            'failed' => $strings['event_failed'],
            'open' => $strings['event_open'],
            'click' => $strings['event_click'],
            'unsubscribe' => $strings['event_unsubscribe'],
            'bounce' => $strings['event_bounce'],
        );

        return isset($labels[$type]) ? $labels[$type] : $strings['event_unknown'];
    }

    private function calculate_percentage($value, $total) {
        $value = (float) $value;
        $total = (float) $total;

        if ($total <= 0) {
            return 0;
        }

        return round(($value / $total) * 100, 1);
    }

    private function format_percentage($value) {
        return number_format_i18n((float) $value, 1) . '%';
    }

    private function get_campaign_message_by_id($message_id) {
        if ($message_id === '') {
            return array();
        }

        foreach ($this->get_campaign_messages() as $message) {
            if (isset($message['id']) && $message['id'] === $message_id) {
                return $message;
            }
        }

        return array();
    }

    private function build_campaign_message_from_request() {
        $message_id = isset($_POST['message_id']) ? sanitize_text_field(wp_unslash($_POST['message_id'])) : '';
        $campaign_name = isset($_POST['campaign_name']) ? sanitize_text_field(wp_unslash($_POST['campaign_name'])) : '';
        $subject_line = isset($_POST['subject_line']) ? sanitize_text_field(wp_unslash($_POST['subject_line'])) : '';
        $email_body = isset($_POST['email_body']) ? wp_kses_post(wp_unslash($_POST['email_body'])) : '';

        if ($campaign_name === '' || $subject_line === '' || trim(wp_strip_all_tags($email_body)) === '') {
            return new WP_Error('invalid_campaign_message', 'Invalid campaign message.');
        }

        $message_status = isset($_POST['message_status']) ? sanitize_key(wp_unslash($_POST['message_status'])) : 'draft';
        $message_status = $message_status === 'sent' ? 'sent' : 'draft';

        $target_includes = isset($_POST['target_includes']) && is_array($_POST['target_includes'])
            ? $this->normalize_campaign_audience_tokens(wp_unslash($_POST['target_includes']))
            : array();

        $target_excludes = isset($_POST['target_excludes']) && is_array($_POST['target_excludes'])
            ? $this->normalize_campaign_audience_tokens(wp_unslash($_POST['target_excludes']))
            : array();

        $sender_account_ids = isset($_POST['sender_account_ids']) && is_array($_POST['sender_account_ids'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['sender_account_ids']))
            : array();
        $sender_account_ids = array_values(array_filter(array_unique($sender_account_ids)));
        if (empty($sender_account_ids) && isset($_POST['sender_account_id'])) {
            $legacy_sender_account_id = sanitize_text_field(wp_unslash($_POST['sender_account_id']));
            if ($legacy_sender_account_id !== '') {
                $sender_account_ids[] = $legacy_sender_account_id;
            }
        }

        $existing_message = $this->get_campaign_message_by_id($message_id);

        return array(
            'id'                => $message_id !== '' ? $message_id : wp_generate_uuid4(),
            'campaign_name'     => $campaign_name,
            'sender_account_id' => !empty($sender_account_ids[0]) ? $sender_account_ids[0] : '',
            'sender_account_ids'=> $sender_account_ids,
            'subject_line'      => $subject_line,
            'preview_text'      => isset($_POST['preview_text']) ? sanitize_text_field(wp_unslash($_POST['preview_text'])) : '',
            'reply_to'          => isset($_POST['reply_to']) ? sanitize_email(wp_unslash($_POST['reply_to'])) : '',
            'message_status'    => $message_status,
            'target_segments'   => array(),
            'target_contacts'   => array(),
            'target_includes'   => $target_includes,
            'target_excludes'   => $target_excludes,
            'email_body'        => $email_body,
            'internal_notes'    => isset($_POST['internal_notes']) ? sanitize_textarea_field(wp_unslash($_POST['internal_notes'])) : '',
            'created_at'        => isset($existing_message['created_at']) ? $existing_message['created_at'] : current_time('mysql'),
        );
    }

    private function get_contact_segments($contacts) {
        $segments = array();
        foreach ($contacts as $contact) {
            $segment = isset($contact['segment']) ? trim((string) $contact['segment']) : '';
            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        $segments = array_values(array_unique($segments));
        sort($segments);
        return $segments;
    }

    private function format_campaign_audience_summary($campaign_message, $strings) {
        $include_count = count($this->get_campaign_audience_tokens($campaign_message, 'include'));
        $exclude_count = count($this->get_campaign_audience_tokens($campaign_message, 'exclude'));
        $contact_count = count($this->resolve_campaign_contact_ids($campaign_message));

        if ($include_count === 0 && $contact_count === 0) {
            return $strings['audience_not_selected'];
        }

        return sprintf($strings['audience_summary'], number_format_i18n($include_count), number_format_i18n($exclude_count), number_format_i18n($contact_count));
    }

    private function normalize_campaign_audience_tokens($tokens) {
        $normalized = array();
        foreach ((array) $tokens as $token) {
            $token = sanitize_text_field((string) $token);
            if ($token === 'all:all' || $token === 'system:today_sent' || preg_match('/^(list|tag|contact):[A-Za-z0-9_\-]+$/', $token)) {
                $normalized[] = $token;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function get_campaign_audience_tokens($campaign_message, $mode) {
        $key = ($mode === 'exclude') ? 'target_excludes' : 'target_includes';
        $tokens = isset($campaign_message[$key]) && is_array($campaign_message[$key]) ? $campaign_message[$key] : array();
        $tokens = $this->normalize_campaign_audience_tokens($tokens);

        if ($mode === 'include' && empty($tokens) && (!empty($campaign_message['target_segments']) || !empty($campaign_message['target_contacts']))) {
            $tokens = $this->get_legacy_campaign_audience_tokens($campaign_message);
        }

        return $tokens;
    }

    private function get_legacy_campaign_audience_tokens($campaign_message) {
        $tokens = array();
        $segments = isset($campaign_message['target_segments']) && is_array($campaign_message['target_segments']) ? array_map('strval', $campaign_message['target_segments']) : array();
        $contacts = isset($campaign_message['target_contacts']) && is_array($campaign_message['target_contacts']) ? array_map('strval', $campaign_message['target_contacts']) : array();

        if (in_array(self::AUDIENCE_ALL_SEGMENTS, $segments, true)) {
            $tokens[] = 'all:all';
        }

        foreach ($this->get_contact_lists() as $list) {
            if (!empty($list['name']) && in_array((string) $list['name'], $segments, true)) {
                $tokens[] = 'list:' . (string) $list['id'];
            }
        }

        foreach ($contacts as $contact_id) {
            $tokens[] = 'contact:' . $contact_id;
        }

        return $this->normalize_campaign_audience_tokens($tokens);
    }

    private function resolve_campaign_audience_tokens_to_contact_ids($tokens) {
        $tokens = $this->normalize_campaign_audience_tokens($tokens);
        $contact_ids = array();

        foreach ($tokens as $token) {
            if ($token === 'all:all') {
                $contact_ids = array_merge($contact_ids, $this->get_contact_ids_from_contacts($this->get_contacts()));
                continue;
            }

            list($type, $id) = explode(':', $token, 2);
            if ($type === 'system' && $id === 'today_sent') {
                $contact_ids = array_merge($contact_ids, $this->get_today_sent_contact_ids($this->get_contacts()));
                continue;
            }

            if ($type === 'contact') {
                $contact_ids[] = $id;
                continue;
            }

            $entities = ($type === 'tag') ? $this->get_contact_tags() : $this->get_contact_lists();
            $entity = $this->get_contact_entity_by_id($id, $entities);
            if (!empty($entity)) {
                $contact_ids = array_merge($contact_ids, $this->get_assigned_contact_ids_for_entity($entity));
            }
        }

        return array_values(array_unique(array_filter(array_map('strval', $contact_ids))));
    }

    private function get_campaign_status_label($status, $strings) {
        if ($status === 'sent') {
            return $strings['status_sent'];
        }

        return $strings['status_draft'];
    }

    private function get_campaign_sender_account_ids($campaign_message) {
        $sender_account_ids = array();

        if (isset($campaign_message['sender_account_ids']) && is_array($campaign_message['sender_account_ids'])) {
            $sender_account_ids = array_map('strval', $campaign_message['sender_account_ids']);
        }

        if (empty($sender_account_ids) && !empty($campaign_message['sender_account_id'])) {
            $sender_account_ids[] = (string) $campaign_message['sender_account_id'];
        }

        $sender_account_ids = array_values(array_filter(array_unique(array_map('trim', $sender_account_ids))));
        return $sender_account_ids;
    }

    private function get_site_admin_email_for_test() {
        $admin_email = sanitize_email((string) get_option('admin_email'));
        if ($admin_email !== '' && is_email($admin_email)) {
            return $admin_email;
        }

        $current_user = wp_get_current_user();
        if ($current_user instanceof WP_User && !empty($current_user->user_email) && is_email($current_user->user_email)) {
            return sanitize_email($current_user->user_email);
        }

        return '';
    }

    private function get_campaign_send_session_key($session_id) {
        return 'smark_email_send_session_' . sanitize_key($session_id);
    }

    private function get_campaign_send_session($session_id) {
        $session_id = sanitize_text_field($session_id);
        if ($session_id === '') {
            return array();
        }

        $session = get_transient($this->get_campaign_send_session_key($session_id));
        return is_array($session) ? $session : array();
    }

    private function save_campaign_send_session($session) {
        if (empty($session['id'])) {
            return;
        }

        set_transient($this->get_campaign_send_session_key($session['id']), $session, 2 * HOUR_IN_SECONDS);
    }

    private function delete_campaign_send_session($session_id) {
        $session_id = sanitize_text_field($session_id);
        if ($session_id !== '') {
            delete_transient($this->get_campaign_send_session_key($session_id));
        }
    }

    private function create_campaign_send_session($campaign_message, $type) {
        $recipients = $this->resolve_campaign_recipients($campaign_message);
        $campaign_id = isset($campaign_message['id']) ? (string) $campaign_message['id'] : wp_generate_uuid4();
        $campaign_message['id'] = $campaign_id;

        if (empty($recipients)) {
            return new WP_Error('no_recipients', 'No recipients selected.');
        }

        $subject = isset($campaign_message['subject_line']) ? (string) $campaign_message['subject_line'] : '';
        $body = isset($campaign_message['email_body']) ? (string) $campaign_message['email_body'] : '';
        if ($subject === '' || trim(wp_strip_all_tags($body)) === '') {
            return new WP_Error('invalid_message', 'Invalid message.');
        }

        $sender_pool = $this->get_campaign_sender_pool($campaign_message);
        if (empty($sender_pool)) {
            return new WP_Error('sender_not_configured', 'SMark sender account is not configured.');
        }

        $total_remaining_capacity = array_sum(array_map(function($sender_item) {
            return (int) ($sender_item['remaining'] ?? 0);
        }, $sender_pool));

        if ($total_remaining_capacity < count($recipients)) {
            return new WP_Error('sender_capacity_insufficient', 'Selected sender accounts do not have enough daily capacity.', array(
                'recipient_count' => count($recipients),
                'remaining_capacity' => $total_remaining_capacity,
            ));
        }

        $session = array(
            'id' => wp_generate_uuid4(),
            'type' => $type === 'quick' ? 'quick' : 'saved',
            'campaign_message' => $campaign_message,
            'recipients' => array_values($recipients),
            'index' => 0,
            'total' => count($recipients),
            'sent_count' => 0,
            'failed_count' => 0,
            'recent_reports' => array(),
            'sender_start_index' => 0,
            'complete' => false,
            'started_at' => current_time('mysql'),
        );

        $this->save_campaign_send_session($session);
        $this->log_campaign_mail_debug('progress_send_started', array(
            'campaign_id' => $campaign_id,
            'recipient_count' => count($recipients),
            'session_id' => $session['id'],
        ));

        return $session;
    }

    private function create_campaign_failure_retry_session($retry_count, $sender_account_id, $campaign_id = '') {
        $retry_count = absint($retry_count);
        $sender_account_id = sanitize_text_field($sender_account_id);
        $campaign_id = sanitize_text_field($campaign_id);

        if ($retry_count <= 0) {
            return new WP_Error('no_recipients', 'No recipients selected.');
        }

        $sender = $this->get_email_account_by_id($sender_account_id);
        if (empty($sender) || empty($sender['smtp_host']) || empty($sender['email_address']) || empty($sender['app_password']) || !is_email($sender['email_address'])) {
            return new WP_Error('sender_not_configured', 'SMark sender account is not configured.');
        }

        $sender_pool = $this->get_campaign_sender_pool(array('sender_account_ids' => array($sender_account_id)));
        $remaining = isset($sender_pool[0]['remaining']) ? (int) $sender_pool[0]['remaining'] : 0;
        if ($remaining < $retry_count) {
            return new WP_Error('sender_capacity_insufficient', 'Selected sender accounts do not have enough daily capacity.', array(
                'recipient_count' => $retry_count,
                'remaining_capacity' => $remaining,
            ));
        }

        $failures = array_slice($this->get_unresolved_campaign_failures($campaign_id), 0, $retry_count);
        if (empty($failures)) {
            return new WP_Error('no_recipients', 'No recipients selected.');
        }

        $session = array(
            'id' => wp_generate_uuid4(),
            'type' => 'failure_retry',
            'sender_account_id' => $sender_account_id,
            'sender' => $sender,
            'failures' => array_values($failures),
            'index' => 0,
            'total' => count($failures),
            'sent_count' => 0,
            'failed_count' => 0,
            'recent_reports' => array(),
            'complete' => false,
            'started_at' => current_time('mysql'),
        );

        $this->save_campaign_send_session($session);
        $this->log_campaign_mail_debug('failure_retry_started', array(
            'sender_account_id' => $sender_account_id,
            'recipient_count' => count($failures),
            'session_id' => $session['id'],
        ));

        return $session;
    }

    private function get_unresolved_campaign_failures($campaign_id = '') {
        $campaign_id = sanitize_text_field($campaign_id);
        $events = $this->get_campaign_events();
        $messages = array();
        foreach ($this->get_campaign_messages() as $message) {
            if (!empty($message['id'])) {
                $messages[(string) $message['id']] = $message;
            }
        }

        $states = array();
        foreach ($events as $index => $event) {
            $event_campaign_id = isset($event['campaign_id']) ? (string) $event['campaign_id'] : '';
            if ($event_campaign_id === '' || !isset($messages[$event_campaign_id])) {
                continue;
            }

            if ($campaign_id !== '' && $event_campaign_id !== $campaign_id) {
                continue;
            }

            $type = isset($event['type']) ? sanitize_key($event['type']) : '';
            if ($type !== 'sent' && $type !== 'failed') {
                continue;
            }

            $email = $this->get_campaign_recipient_email_from_event($event);
            if ($email === '' || !is_email($email)) {
                continue;
            }

            $hash = isset($event['recipient_hash']) && (string) $event['recipient_hash'] !== ''
                ? (string) $event['recipient_hash']
                : $this->get_campaign_recipient_hash($email);
            $key = $event_campaign_id . ':' . $hash;
            $created_at = isset($event['created_at']) ? (string) $event['created_at'] : '';

            if (!isset($states[$key]) || strcmp($created_at, (string) ($states[$key]['created_at'] ?? '')) >= 0) {
                $states[$key] = array(
                    'campaign_id' => $event_campaign_id,
                    'message' => $messages[$event_campaign_id],
                    'email' => $email,
                    'recipient_hash' => $hash,
                    'event_index' => (int) $index,
                    'type' => $type,
                    'created_at' => $created_at,
                );
            }
        }

        $failures = array_values(array_filter($states, function($state) {
            return isset($state['type']) && $state['type'] === 'failed';
        }));

        usort($failures, function($a, $b) {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return $failures;
    }

    private function get_campaign_sender_pool($campaign_message) {
        $all_accounts = $this->get_email_accounts();
        $sender_account_ids = $this->get_campaign_sender_account_ids($campaign_message);
        if (empty($sender_account_ids) && !empty($all_accounts[0]['id'])) {
            $sender_account_ids = array((string) $all_accounts[0]['id']);
        }

        $daily_sent_counts = $this->get_email_account_daily_sent_counts();
        $sender_pool = array();
        foreach ($sender_account_ids as $sender_account_id) {
            $sender = $this->get_email_account_by_id($sender_account_id);
            if (empty($sender) || empty($sender['smtp_host']) || empty($sender['email_address']) || empty($sender['app_password']) || !is_email($sender['email_address'])) {
                continue;
            }

            $daily_limit = max(1, (int) ($sender['daily_limit'] ?? 1));
            $sent_today = isset($daily_sent_counts[$sender_account_id]) ? (int) $daily_sent_counts[$sender_account_id] : 0;
            $sender_pool[] = array(
                'id' => $sender_account_id,
                'account' => $sender,
                'remaining' => max(0, $daily_limit - $sent_today),
                'daily_limit' => $daily_limit,
                'sent_today' => $sent_today,
            );
        }

        return $sender_pool;
    }

    private function get_next_campaign_sender_index($sender_pool, $start_index = 0) {
        $start_index = max(0, (int) $start_index);
        foreach ($sender_pool as $pool_index => $sender_item) {
            if ($pool_index < $start_index) {
                continue;
            }

            if ((int) ($sender_item['remaining'] ?? 0) > 0) {
                return $pool_index;
            }
        }

        return null;
    }

    private function get_campaign_sender_start_after_error($sender_pool, $sender_index) {
        $sender_index = (int) $sender_index;
        $last_index = count($sender_pool) - 1;

        if ($last_index <= 0 || $sender_index >= $last_index) {
            return $sender_index;
        }

        for ($next_index = $sender_index + 1; $next_index <= $last_index; $next_index++) {
            if ((int) ($sender_pool[$next_index]['remaining'] ?? 0) > 0) {
                return $next_index;
            }
        }

        return $last_index;
    }

    private function process_campaign_send_session_batch($session, $strings = array()) {
        $campaign_message = isset($session['campaign_message']) && is_array($session['campaign_message']) ? $session['campaign_message'] : array();
        $recipients = isset($session['recipients']) && is_array($session['recipients']) ? array_values($session['recipients']) : array();
        $total = count($recipients);
        $index = isset($session['index']) ? max(0, (int) $session['index']) : 0;

        if (empty($campaign_message) || empty($recipients) || $index >= $total) {
            $session['complete'] = true;
            return $session;
        }

        $campaign_id = isset($campaign_message['id']) ? (string) $campaign_message['id'] : 'temporary';
        $subject = isset($campaign_message['subject_line']) ? (string) $campaign_message['subject_line'] : '';
        $body = isset($campaign_message['email_body']) ? (string) $campaign_message['email_body'] : '';
        $reply_to = isset($campaign_message['reply_to']) ? sanitize_email($campaign_message['reply_to']) : '';
        $html_body = $this->prepare_campaign_email_html($body);
        $sender_pool = $this->get_campaign_sender_pool($campaign_message);

        if (empty($sender_pool)) {
            return new WP_Error('sender_not_configured', 'SMark sender account is not configured.');
        }

        $batch_size = 5;
        $processed = 0;
        $sender_start_index = isset($session['sender_start_index']) ? max(0, (int) $session['sender_start_index']) : 0;

        try {
            while ($index < $total && $processed < $batch_size) {
                $sender_index = $this->get_next_campaign_sender_index($sender_pool, $sender_start_index);
                if ($sender_index === null) {
                    return new WP_Error('sender_capacity_insufficient', 'Selected sender accounts do not have enough daily capacity.');
                }

                $email = sanitize_email((string) $recipients[$index]);
                $index++;
                $processed++;

                if ($email === '' || !is_email($email)) {
                    $session['failed_count'] = (int) ($session['failed_count'] ?? 0) + 1;
                    continue;
                }

                $sender_account_id = (string) $sender_pool[$sender_index]['id'];
                $sender = $sender_pool[$sender_index]['account'];
                $this->campaign_mailer_account = $sender;
                $recipient_hash = $this->get_campaign_recipient_hash($email);
                $recipient_body = $this->add_campaign_tracking_to_html($html_body, $campaign_id, $recipient_hash);
                $send_result = $this->send_campaign_email_via_direct_smtp($email, $subject, $recipient_body, $sender, $reply_to);

                if ($send_result === true) {
                    $session['sent_count'] = (int) ($session['sent_count'] ?? 0) + 1;
                    $sender_pool[$sender_index]['remaining'] = max(0, (int) $sender_pool[$sender_index]['remaining'] - 1);
                    $this->record_campaign_event($campaign_id, 'sent', $email, '', '', $sender_account_id);
                    array_unshift($session['recent_reports'], array(
                        'email' => $email,
                        'time' => current_time('H:i:s'),
                        'status' => 'sent',
                        'statusLabel' => isset($strings['send_progress_report_sent']) ? $strings['send_progress_report_sent'] : 'Sent',
                    ));
                    $session['recent_reports'] = array_slice($session['recent_reports'], 0, 5);
                    $this->log_campaign_mail_debug('progress_recipient_sent', array(
                        'campaign_id' => $campaign_id,
                        'sender_account_id' => $sender_account_id,
                        'recipient' => $this->mask_email_for_log($email),
                    ));
                } else {
                    $session['failed_count'] = (int) ($session['failed_count'] ?? 0) + 1;
                    $this->record_campaign_event($campaign_id, 'failed', $email, '', '', $sender_account_id, $this->get_campaign_failure_details($send_result, $email, $sender));
                    array_unshift($session['recent_reports'], array(
                        'email' => $email,
                        'time' => current_time('H:i:s'),
                        'status' => 'failed',
                        'statusLabel' => isset($strings['send_progress_report_failed']) ? $strings['send_progress_report_failed'] : 'Failed',
                    ));
                    $session['recent_reports'] = array_slice($session['recent_reports'], 0, 5);
                    $this->log_campaign_mail_debug('progress_recipient_send_failed', array(
                        'campaign_id' => $campaign_id,
                        'sender_account_id' => $sender_account_id,
                        'recipient' => $this->mask_email_for_log($email),
                        'next_sender_index' => $this->get_campaign_sender_start_after_error($sender_pool, $sender_index),
                    ));
                    $sender_start_index = $this->get_campaign_sender_start_after_error($sender_pool, $sender_index);
                }
            }
        } finally {
            $this->campaign_mailer_account = array();
        }

        $session['index'] = $index;
        $session['total'] = $total;
        $session['sender_start_index'] = $sender_start_index;
        $session['complete'] = $index >= $total;

        if (!empty($session['complete']) && (int) ($session['sent_count'] ?? 0) <= 0) {
            return new WP_Error('send_failed', 'Send failed.');
        }

        return $session;
    }

    private function process_campaign_failure_retry_batch($session, $strings = array()) {
        $failures = isset($session['failures']) && is_array($session['failures']) ? array_values($session['failures']) : array();
        $total = count($failures);
        $index = isset($session['index']) ? max(0, (int) $session['index']) : 0;
        $sender_account_id = isset($session['sender_account_id']) ? sanitize_text_field((string) $session['sender_account_id']) : '';
        $sender = !empty($session['sender']) && is_array($session['sender']) ? $session['sender'] : $this->get_email_account_by_id($sender_account_id);

        if (empty($failures) || $index >= $total) {
            $session['complete'] = true;
            return $session;
        }

        if (empty($sender) || empty($sender['smtp_host']) || empty($sender['email_address']) || empty($sender['app_password']) || !is_email($sender['email_address'])) {
            return new WP_Error('sender_not_configured', 'SMark sender account is not configured.');
        }

        $batch_size = 5;
        $processed = 0;

        try {
            while ($index < $total && $processed < $batch_size) {
                $failure = isset($failures[$index]) && is_array($failures[$index]) ? $failures[$index] : array();
                $index++;
                $processed++;

                $campaign_message = isset($failure['message']) && is_array($failure['message']) ? $failure['message'] : array();
                $campaign_id = isset($failure['campaign_id']) ? (string) $failure['campaign_id'] : '';
                $email = isset($failure['email']) ? sanitize_email((string) $failure['email']) : '';
                $event_index = isset($failure['event_index']) ? (int) $failure['event_index'] : -1;

                if (empty($campaign_message) || $campaign_id === '' || $email === '' || !is_email($email)) {
                    $session['failed_count'] = (int) ($session['failed_count'] ?? 0) + 1;
                    continue;
                }

                $subject = isset($campaign_message['subject_line']) ? (string) $campaign_message['subject_line'] : '';
                $body = isset($campaign_message['email_body']) ? (string) $campaign_message['email_body'] : '';
                $reply_to = isset($campaign_message['reply_to']) ? sanitize_email($campaign_message['reply_to']) : '';
                $recipient_hash = isset($failure['recipient_hash']) && (string) $failure['recipient_hash'] !== ''
                    ? (string) $failure['recipient_hash']
                    : $this->get_campaign_recipient_hash($email);

                $this->campaign_mailer_account = $sender;
                $recipient_body = $this->add_campaign_tracking_to_html($this->prepare_campaign_email_html($body), $campaign_id, $recipient_hash);
                $send_result = $this->send_campaign_email_via_direct_smtp($email, $subject, $recipient_body, $sender, $reply_to);

                if ($send_result === true) {
                    $session['sent_count'] = (int) ($session['sent_count'] ?? 0) + 1;
                    $this->mark_campaign_failure_event_sent($event_index, $campaign_id, $email, $recipient_hash, $sender_account_id);
                    array_unshift($session['recent_reports'], array(
                        'email' => $email,
                        'time' => current_time('H:i:s'),
                        'status' => 'sent',
                        'statusLabel' => isset($strings['send_progress_report_sent']) ? $strings['send_progress_report_sent'] : 'Sent',
                    ));
                    $this->log_campaign_mail_debug('failure_retry_recipient_sent', array(
                        'campaign_id' => $campaign_id,
                        'sender_account_id' => $sender_account_id,
                        'recipient' => $this->mask_email_for_log($email),
                    ));
                } else {
                    $session['failed_count'] = (int) ($session['failed_count'] ?? 0) + 1;
                    $this->refresh_campaign_failure_event($event_index, $campaign_id, $email, $recipient_hash, $sender_account_id, $this->get_campaign_failure_details($send_result, $email, $sender));
                    array_unshift($session['recent_reports'], array(
                        'email' => $email,
                        'time' => current_time('H:i:s'),
                        'status' => 'failed',
                        'statusLabel' => isset($strings['send_progress_report_failed']) ? $strings['send_progress_report_failed'] : 'Failed',
                    ));
                    $this->log_campaign_mail_debug('failure_retry_recipient_failed', array(
                        'campaign_id' => $campaign_id,
                        'sender_account_id' => $sender_account_id,
                        'recipient' => $this->mask_email_for_log($email),
                    ));
                }

                $session['recent_reports'] = array_slice($session['recent_reports'], 0, 5);
            }
        } finally {
            $this->campaign_mailer_account = array();
        }

        $session['index'] = $index;
        $session['total'] = $total;
        $session['complete'] = $index >= $total;

        return $session;
    }

    private function mark_campaign_failure_event_sent($event_index, $campaign_id, $email, $recipient_hash, $sender_account_id) {
        $this->update_campaign_failure_event($event_index, $campaign_id, $email, $recipient_hash, $sender_account_id, 'sent', array());
    }

    private function refresh_campaign_failure_event($event_index, $campaign_id, $email, $recipient_hash, $sender_account_id, $details) {
        $this->update_campaign_failure_event($event_index, $campaign_id, $email, $recipient_hash, $sender_account_id, 'failed', $details);
    }

    private function update_campaign_failure_event($event_index, $campaign_id, $email, $recipient_hash, $sender_account_id, $type, $details = array()) {
        $events = $this->get_campaign_events();
        $event_index = (int) $event_index;
        if (!isset($events[$event_index]) || !is_array($events[$event_index])) {
            return;
        }

        if (($events[$event_index]['type'] ?? '') !== 'failed') {
            return;
        }

        $events[$event_index]['type'] = sanitize_key($type);
        $events[$event_index]['campaign_id'] = sanitize_text_field($campaign_id);
        $events[$event_index]['account_id'] = sanitize_text_field($sender_account_id);
        $events[$event_index]['recipient_email'] = sanitize_email($email);
        $events[$event_index]['recipient_hash'] = sanitize_text_field($recipient_hash !== '' ? $recipient_hash : $this->get_campaign_recipient_hash($email));
        $events[$event_index]['recipient_label'] = $email !== '' ? $this->mask_email_for_log($email) : '';
        $events[$event_index]['details'] = is_array($details) ? $this->sanitize_campaign_event_details($details) : array();
        $events[$event_index]['created_at'] = current_time('mysql');

        update_option(self::OPTION_CAMPAIGN_EVENTS, $events, false);
    }

    private function finalize_campaign_send_session($session) {
        $campaign_message = isset($session['campaign_message']) && is_array($session['campaign_message']) ? $session['campaign_message'] : array();
        $campaign_message['message_status'] = 'sent';
        $campaign_message['sent_at'] = current_time('mysql');
        $campaign_message['sent_count'] = (int) ($session['sent_count'] ?? 0);

        return $this->upsert_campaign_message($campaign_message);
    }

    private function format_campaign_failure_retry_response($session, $strings) {
        $response = $this->format_campaign_send_progress_response($session, $strings);
        $response['statusText'] = !empty($session['complete']) ? $strings['failure_retry_complete'] : $strings['failure_retry_sending'];
        $response['countText'] = sprintf(
            $strings['failure_retry_count_progress'],
            number_format_i18n((int) ($session['sent_count'] ?? 0)),
            number_format_i18n((int) ($session['failed_count'] ?? 0)),
            number_format_i18n((int) ($session['total'] ?? 0))
        );
        return $response;
    }

    private function format_campaign_send_progress_response($session, $strings) {
        $total = max(0, (int) ($session['total'] ?? 0));
        $processed = max(0, (int) ($session['index'] ?? 0));
        $sent = max(0, (int) ($session['sent_count'] ?? 0));
        $percent = $total > 0 ? min(100, (int) floor(($processed / $total) * 100)) : 0;
        $complete = !empty($session['complete']);

        return array(
            'sessionId' => isset($session['id']) ? (string) $session['id'] : '',
            'processed' => $processed,
            'sent' => $sent,
            'failed' => max(0, (int) ($session['failed_count'] ?? 0)),
            'total' => $total,
            'percent' => $complete ? 100 : $percent,
            'complete' => $complete,
            'statusText' => $complete ? $strings['send_progress_complete'] : $strings['send_progress_sending'],
            'countText' => sprintf($strings['send_progress_count'], number_format_i18n($sent), number_format_i18n($total)),
            'recentReports' => isset($session['recent_reports']) && is_array($session['recent_reports']) ? array_values($session['recent_reports']) : array(),
        );
    }

    private function send_campaign_message($campaign_message) {
        $recipients = $this->resolve_campaign_recipients($campaign_message);
        $campaign_id = isset($campaign_message['id']) ? (string) $campaign_message['id'] : 'temporary';
        $this->campaign_mail_errors = array();

        $this->log_campaign_mail_debug('send_started', array(
            'campaign_id' => $campaign_id,
            'campaign_name' => isset($campaign_message['campaign_name']) ? (string) $campaign_message['campaign_name'] : '',
            'recipient_count' => count($recipients),
            'target_includes_count' => count($this->get_campaign_audience_tokens($campaign_message, 'include')),
            'target_excludes_count' => count($this->get_campaign_audience_tokens($campaign_message, 'exclude')),
        ));

        if (empty($recipients)) {
            $this->log_campaign_mail_debug('send_failed_no_recipients', array(
                'campaign_id' => $campaign_id,
            ));
            return new WP_Error('no_recipients', 'No recipients selected.');
        }

        $subject = isset($campaign_message['subject_line']) ? (string) $campaign_message['subject_line'] : '';
        $body = isset($campaign_message['email_body']) ? (string) $campaign_message['email_body'] : '';
        if ($subject === '' || trim(wp_strip_all_tags($body)) === '') {
            $this->log_campaign_mail_debug('send_failed_invalid_message', array(
                'campaign_id' => $campaign_id,
                'has_subject' => $subject !== '',
                'has_body' => trim(wp_strip_all_tags($body)) !== '',
            ));
            return new WP_Error('invalid_message', 'Invalid message.');
        }

        $reply_to = isset($campaign_message['reply_to']) ? sanitize_email($campaign_message['reply_to']) : '';

        $all_accounts = $this->get_email_accounts();
        $sender_account_ids = $this->get_campaign_sender_account_ids($campaign_message);
        if (empty($sender_account_ids) && !empty($all_accounts[0]['id'])) {
            $sender_account_ids = array((string) $all_accounts[0]['id']);
            $this->log_campaign_mail_debug('default_sender_account_applied', array(
                'campaign_id' => $campaign_id,
                'sender_account_id' => $sender_account_ids[0],
                'sender_email' => isset($all_accounts[0]['email_address']) ? (string) $all_accounts[0]['email_address'] : '',
            ));
        }

        $daily_sent_counts = $this->get_email_account_daily_sent_counts();
        $sender_pool = array();
        foreach ($sender_account_ids as $sender_account_id) {
            $sender = $this->get_email_account_by_id($sender_account_id);
            if (empty($sender) || empty($sender['smtp_host']) || empty($sender['email_address']) || empty($sender['app_password']) || !is_email($sender['email_address'])) {
                continue;
            }

            $daily_limit = max(1, (int) ($sender['daily_limit'] ?? 1));
            $sent_today = isset($daily_sent_counts[$sender_account_id]) ? (int) $daily_sent_counts[$sender_account_id] : 0;
            $sender_pool[] = array(
                'id' => $sender_account_id,
                'account' => $sender,
                'remaining' => max(0, $daily_limit - $sent_today),
                'daily_limit' => $daily_limit,
                'sent_today' => $sent_today,
            );
        }

        $total_remaining_capacity = array_sum(array_map(function($sender_item) {
            return (int) ($sender_item['remaining'] ?? 0);
        }, $sender_pool));

        $this->log_campaign_mail_debug('sender_pool_resolved', array(
            'campaign_id' => $campaign_id,
            'sender_account_ids' => $sender_account_ids,
            'valid_sender_count' => count($sender_pool),
            'recipient_count' => count($recipients),
            'remaining_capacity' => $total_remaining_capacity,
        ));

        if (empty($sender_pool)) {
            $this->log_campaign_mail_debug('send_failed_sender_not_configured', array(
                'campaign_id' => $campaign_id,
                'sender_account_ids' => $sender_account_ids,
            ));

            return new WP_Error('sender_not_configured', 'SMark sender account is not configured.');
        }

        if ($total_remaining_capacity < count($recipients)) {
            $this->log_campaign_mail_debug('send_failed_sender_capacity_insufficient', array(
                'campaign_id' => $campaign_id,
                'recipient_count' => count($recipients),
                'remaining_capacity' => $total_remaining_capacity,
            ));

            return new WP_Error('sender_capacity_insufficient', 'Selected sender accounts do not have enough daily capacity.', array(
                'recipient_count' => count($recipients),
                'remaining_capacity' => $total_remaining_capacity,
            ));
        }

        $sent_count = 0;
        $html_body = $this->prepare_campaign_email_html($body);
        $sender_start_index = 0;

        try {
            foreach ($recipients as $email) {
                $sender_index = $this->get_next_campaign_sender_index($sender_pool, $sender_start_index);
                if ($sender_index === null) {
                    break;
                }

                $sender_account_id = (string) $sender_pool[$sender_index]['id'];
                $sender = $sender_pool[$sender_index]['account'];
                $this->campaign_mailer_account = $sender;
                $recipient_hash = $this->get_campaign_recipient_hash($email);
                $recipient_body = $this->add_campaign_tracking_to_html($html_body, $campaign_id, $recipient_hash);
                $send_result = $this->send_campaign_email_via_direct_smtp($email, $subject, $recipient_body, $sender, $reply_to);

                if ($send_result === true) {
                    $sent_count++;
                    $sender_pool[$sender_index]['remaining'] = max(0, (int) $sender_pool[$sender_index]['remaining'] - 1);
                    $this->record_campaign_event($campaign_id, 'sent', $email, '', '', $sender_account_id);
                    $this->log_campaign_mail_debug('recipient_sent', array(
                        'campaign_id' => $campaign_id,
                        'sender_account_id' => $sender_account_id,
                        'recipient' => $this->mask_email_for_log($email),
                    ));
                } else {
                    if ($send_result instanceof WP_Error) {
                        $this->campaign_mail_errors[] = array(
                            'code' => $send_result->get_error_code(),
                            'message' => $send_result->get_error_message(),
                            'data' => $this->sanitize_mail_error_data_for_log((array) $send_result->get_error_data()),
                        );
                    }

                    $this->record_campaign_event($campaign_id, 'failed', $email, '', '', $sender_account_id, $this->get_campaign_failure_details($send_result, $email, $sender));
                    $this->log_campaign_mail_debug('recipient_send_failed', array(
                        'campaign_id' => $campaign_id,
                        'sender_account_id' => $sender_account_id,
                        'recipient' => $this->mask_email_for_log($email),
                        'mail_errors' => $this->campaign_mail_errors,
                        'next_sender_index' => $this->get_campaign_sender_start_after_error($sender_pool, $sender_index),
                    ));
                    $sender_start_index = $this->get_campaign_sender_start_after_error($sender_pool, $sender_index);
                }
            }
        } finally {
            $this->campaign_mailer_account = array();
        }

        if ($sent_count <= 0) {
            $this->log_campaign_mail_debug('send_failed_all_recipients', array(
                'campaign_id' => $campaign_id,
                'mail_errors' => $this->campaign_mail_errors,
            ));

            return new WP_Error('send_failed', 'Send failed.', array(
                'mail_errors' => $this->campaign_mail_errors,
            ));
        }

        $this->log_campaign_mail_debug('send_finished', array(
            'campaign_id' => $campaign_id,
            'sent_count' => $sent_count,
        ));

        return $sent_count;
    }

    private function send_campaign_test_message($campaign_message, $recipient_email) {
        $recipient_email = sanitize_email($recipient_email);
        if ($recipient_email === '' || !is_email($recipient_email)) {
            return new WP_Error('no_recipients', 'No valid test recipient.');
        }

        $subject = isset($campaign_message['subject_line']) ? (string) $campaign_message['subject_line'] : '';
        $body = isset($campaign_message['email_body']) ? (string) $campaign_message['email_body'] : '';
        if ($subject === '' || trim(wp_strip_all_tags($body)) === '') {
            return new WP_Error('invalid_message', 'Invalid message.');
        }

        $all_accounts = $this->get_email_accounts();
        $sender_account_ids = $this->get_campaign_sender_account_ids($campaign_message);
        if (empty($sender_account_ids) && !empty($all_accounts[0]['id'])) {
            $sender_account_ids = array((string) $all_accounts[0]['id']);
        }

        $sender = array();
        foreach ($sender_account_ids as $sender_account_id) {
            $candidate = $this->get_email_account_by_id($sender_account_id);
            if (!empty($candidate) && !empty($candidate['smtp_host']) && !empty($candidate['email_address']) && !empty($candidate['app_password']) && is_email($candidate['email_address'])) {
                $sender = $candidate;
                break;
            }
        }

        if (empty($sender)) {
            return new WP_Error('sender_not_configured', 'SMark sender account is not configured.');
        }

        $reply_to = isset($campaign_message['reply_to']) ? sanitize_email($campaign_message['reply_to']) : '';
        $html_body = $this->prepare_campaign_email_html($body);
        $send_result = $this->send_campaign_email_via_direct_smtp($recipient_email, '[Test] ' . $subject, $html_body, $sender, $reply_to);

        if ($send_result !== true) {
            return $send_result instanceof WP_Error ? $send_result : new WP_Error('send_failed', 'Test send failed.');
        }

        return true;
    }

    private function send_campaign_email_via_direct_smtp($recipient, $subject, $html_body, $sender, $reply_to = '') {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }

        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer::$validator = static function ($email) {
            return (bool) is_email($email);
        };

        try {
            $mailer->isSMTP();
            $mailer->Host = (string) $sender['smtp_host'];
            $mailer->Port = !empty($sender['smtp_port']) ? (int) $sender['smtp_port'] : 587;
            $mailer->SMTPAuth = true;
            $mailer->Username = (string) $sender['email_address'];
            $mailer->Password = (string) $sender['app_password'];

            $encryption = isset($sender['encryption']) ? strtolower((string) $sender['encryption']) : 'tls';
            $mailer->SMTPSecure = in_array($encryption, array('ssl', 'tls'), true) ? $encryption : '';

            $sender_name = !empty($sender['sender_name']) ? (string) $sender['sender_name'] : get_bloginfo('name');
            $mailer->setFrom((string) $sender['email_address'], $sender_name, false);

            if ($reply_to !== '' && is_email($reply_to)) {
                $mailer->addReplyTo($reply_to);
            }

            $mailer->addAddress($recipient);
            $mailer->CharSet = get_bloginfo('charset');
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $html_body;
            $mailer->AltBody = trim(wp_strip_all_tags($html_body));

            $mailer->send();
            return true;
        } catch (Exception $exception) {
            return new WP_Error('direct_smtp_failed', $exception->getMessage(), array(
                'smtp_host' => isset($sender['smtp_host']) ? (string) $sender['smtp_host'] : '',
                'smtp_port' => isset($sender['smtp_port']) ? (int) $sender['smtp_port'] : 0,
                'sender_email' => isset($sender['email_address']) ? (string) $sender['email_address'] : '',
                'recipient' => $recipient,
            ));
        }
    }

    private function prepare_campaign_email_html($body) {
        $body = (string) $body;
        $body_html = wpautop($body);
        $body_html = make_clickable($body_html);
        $is_rtl = $this->campaign_text_has_rtl_chars($body);
        $direction = $is_rtl ? 'rtl' : 'ltr';
        $text_align = $is_rtl ? 'right' : 'left';
        $language = $is_rtl ? 'fa' : 'en';

        return sprintf(
            '<div lang="%1$s" dir="%2$s" style="direction:%2$s;text-align:%3$s;font-family:Vazirmatn,Tahoma,Arial,sans-serif;font-size:15px;line-height:1.9;color:#1f2937;unicode-bidi:embed;">%4$s</div>',
            esc_attr($language),
            esc_attr($direction),
            esc_attr($text_align),
            $body_html
        );
    }

    private function campaign_text_has_rtl_chars($text) {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', (string) $text);
    }

    private function add_campaign_tracking_to_html($html_body, $campaign_id, $recipient_hash) {
        if ($campaign_id === '' || $recipient_hash === '') {
            return $html_body;
        }

        $html_body = preg_replace_callback('/href=(["\'])(.*?)\1/i', function($matches) use ($campaign_id, $recipient_hash) {
            $url = html_entity_decode($matches[2], ENT_QUOTES, get_bloginfo('charset'));
            if ($url === '' || strpos($url, 'mailto:') === 0 || strpos($url, 'tel:') === 0 || strpos($url, '#') === 0 || strpos($url, 'smark_email_track') !== false) {
                return $matches[0];
            }

            $tracked_url = add_query_arg(
                array(
                    'smark_email_track' => 'click',
                    'cid' => $campaign_id,
                    'rh' => $recipient_hash,
                    'tk' => $this->get_campaign_tracking_token($campaign_id, $recipient_hash, 'click'),
                    'url' => $url,
                ),
                home_url('/')
            );

            return 'href=' . $matches[1] . esc_url($tracked_url) . $matches[1];
        }, $html_body);

        $open_url = add_query_arg(
            array(
                'smark_email_track' => 'open',
                'cid' => $campaign_id,
                'rh' => $recipient_hash,
                'tk' => $this->get_campaign_tracking_token($campaign_id, $recipient_hash, 'open'),
                'v' => time(),
            ),
            home_url('/')
        );

        return $html_body . '<img src="' . esc_url($open_url) . '" width="1" height="1" alt="" style="width:1px;height:1px;border:0;opacity:0;outline:none;text-decoration:none;" />';
    }

    public function maybe_handle_public_campaign_tracking() {
        $tracking_type = isset($_GET['smark_email_track']) ? sanitize_key(wp_unslash($_GET['smark_email_track'])) : '';

        if ($tracking_type === 'open') {
            $this->track_campaign_open();
        }

        if ($tracking_type === 'click') {
            $this->track_campaign_click();
        }
    }

    private function get_campaign_tracking_token($campaign_id, $recipient_hash, $type) {
        $campaign_id = sanitize_text_field($campaign_id);
        $recipient_hash = sanitize_text_field($recipient_hash);
        $type = sanitize_key($type);

        if ($campaign_id === '' || $recipient_hash === '' || $type === '') {
            return '';
        }

        return hash_hmac('sha256', $type . '|' . $campaign_id . '|' . $recipient_hash, wp_salt('nonce'));
    }

    private function is_valid_campaign_tracking_request($campaign_id, $recipient_hash, $type) {
        $token = isset($_GET['tk']) ? sanitize_text_field(wp_unslash(rawurldecode($_GET['tk']))) : '';

        if ($token === '') {
            return false;
        }

        $expected = $this->get_campaign_tracking_token($campaign_id, $recipient_hash, $type);
        return $expected !== '' && hash_equals($expected, $token);
    }

    public function track_campaign_open() {
        $campaign_id = isset($_GET['cid']) ? sanitize_text_field(wp_unslash(rawurldecode($_GET['cid']))) : '';
        $recipient_hash = isset($_GET['rh']) ? sanitize_text_field(wp_unslash(rawurldecode($_GET['rh']))) : '';

        if ($this->is_valid_campaign_tracking_request($campaign_id, $recipient_hash, 'open') && $this->is_recordable_campaign_open($campaign_id, $recipient_hash)) {
            $this->record_campaign_event($campaign_id, 'open', '', $recipient_hash);
            $this->log_campaign_mail_debug('open_tracked', array(
                'campaign_id' => $campaign_id,
                'recipient_hash' => substr($recipient_hash, 0, 12),
            ));
        } else {
            $this->log_campaign_mail_debug('open_ignored', array(
                'campaign_id' => $campaign_id,
                'recipient_hash' => substr($recipient_hash, 0, 12),
                'suspected_scanner' => $this->is_suspected_campaign_open_scanner(),
                'privacy_proxy' => $this->is_campaign_open_privacy_proxy(),
                'valid_token' => $this->is_valid_campaign_tracking_request($campaign_id, $recipient_hash, 'open'),
            ));
        }

        $this->output_campaign_tracking_pixel();
        exit;
    }

    public function track_campaign_click() {
        $campaign_id = isset($_GET['cid']) ? sanitize_text_field(wp_unslash(rawurldecode($_GET['cid']))) : '';
        $recipient_hash = isset($_GET['rh']) ? sanitize_text_field(wp_unslash(rawurldecode($_GET['rh']))) : '';
        $url = isset($_GET['url']) ? esc_url_raw(wp_unslash(rawurldecode($_GET['url']))) : '';

        if ($this->is_valid_campaign_tracking_request($campaign_id, $recipient_hash, 'click') && $campaign_id !== '' && $recipient_hash !== '') {
            $this->record_campaign_event($campaign_id, 'click', '', $recipient_hash, $url);
            $this->log_campaign_mail_debug('click_tracked', array(
                'campaign_id' => $campaign_id,
                'recipient_hash' => substr($recipient_hash, 0, 12),
                'has_url' => $url !== '',
            ));
        } else {
            $this->log_campaign_mail_debug('click_ignored', array(
                'campaign_id' => $campaign_id,
                'recipient_hash' => substr($recipient_hash, 0, 12),
                'valid_token' => $this->is_valid_campaign_tracking_request($campaign_id, $recipient_hash, 'click'),
            ));
        }

        if ($url === '') {
            $url = home_url('/');
        }

        wp_redirect($url);
        exit;
    }

    private function output_campaign_tracking_pixel() {
        nocache_headers();
        header('Content-Type: image/gif');
        header('Content-Length: 43');
        header('X-Robots-Tag: noindex, nofollow', true);
        echo base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');
    }

    private function record_campaign_event($campaign_id, $type, $recipient_email = '', $recipient_hash = '', $url = '', $account_id = '', $details = array()) {
        $campaign_id = sanitize_text_field($campaign_id);
        $type = sanitize_key($type);
        $recipient_email = sanitize_email($recipient_email);
        $recipient_hash = $recipient_hash !== '' ? sanitize_text_field($recipient_hash) : $this->get_campaign_recipient_hash($recipient_email);
        $account_id = $account_id !== '' ? sanitize_text_field($account_id) : '';

        if ($campaign_id === '' || $type === '') {
            return;
        }

        $events = $this->get_campaign_events();
        $events[] = array(
            'id' => wp_generate_uuid4(),
            'campaign_id' => $campaign_id,
            'account_id' => $account_id,
            'type' => $type,
            'recipient_hash' => $recipient_hash,
            'recipient_email' => $recipient_email,
            'recipient_label' => $recipient_email !== '' ? $this->mask_email_for_log($recipient_email) : '',
            'url' => $url !== '' ? esc_url_raw($url) : '',
            'details' => is_array($details) ? $this->sanitize_campaign_event_details($details) : array(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            'created_at' => current_time('mysql'),
        );

        if (count($events) > 5000) {
            $events = array_slice($events, -5000);
        }

        update_option(self::OPTION_CAMPAIGN_EVENTS, $events, false);
    }

    private function sanitize_campaign_event_details($details) {
        $sanitized = array();
        foreach ((array) $details as $key => $value) {
            $key = sanitize_key($key);
            if ($key === '') {
                continue;
            }

            if (is_scalar($value)) {
                $sanitized[$key] = sanitize_text_field((string) $value);
            }
        }

        return $sanitized;
    }

    private function get_campaign_failure_details($send_result, $recipient_email, $sender) {
        $message = '';
        $code = 'send_failed';
        $data = array();

        if ($send_result instanceof WP_Error) {
            $message = $send_result->get_error_message();
            $code = $send_result->get_error_code();
            $data = (array) $send_result->get_error_data();
        }

        $sender_email = isset($sender['email_address']) ? sanitize_email((string) $sender['email_address']) : '';
        $smtp_host = isset($sender['smtp_host']) ? (string) $sender['smtp_host'] : '';
        $smtp_port = isset($sender['smtp_port']) ? (int) $sender['smtp_port'] : 0;

        return array(
            'code' => $code,
            'message' => $message !== '' ? $message : 'Send failed.',
            'reason' => $this->classify_campaign_failure_reason($message, $code),
            'recipient_email' => sanitize_email($recipient_email),
            'sender_email' => $sender_email,
            'smtp_host' => $smtp_host !== '' ? $smtp_host : (isset($data['smtp_host']) ? (string) $data['smtp_host'] : ''),
            'smtp_port' => $smtp_port > 0 ? $smtp_port : (isset($data['smtp_port']) ? (int) $data['smtp_port'] : 0),
        );
    }

    private function classify_campaign_failure_reason($message, $code = '') {
        $text = strtolower((string) $message . ' ' . (string) $code);

        if (strpos($text, 'auth') !== false || strpos($text, 'password') !== false || strpos($text, 'username') !== false || strpos($text, '535') !== false) {
            return 'SMTP authentication failed. Check the sender email username, app password, and provider security settings.';
        }

        if (strpos($text, 'connect') !== false || strpos($text, 'connection') !== false || strpos($text, 'could not instantiate') !== false || strpos($text, '111') !== false) {
            return 'SMTP connection failed. Check SMTP host, port, firewall, and server network access.';
        }

        if (strpos($text, 'timed out') !== false || strpos($text, 'timeout') !== false) {
            return 'SMTP connection timed out. The mail server may be slow, blocked, or unavailable.';
        }

        if (strpos($text, 'tls') !== false || strpos($text, 'ssl') !== false || strpos($text, 'certificate') !== false || strpos($text, 'crypto') !== false) {
            return 'TLS/SSL negotiation failed. Check encryption type, port, and certificate support.';
        }

        if (strpos($text, 'recipient') !== false || strpos($text, 'address') !== false || strpos($text, 'invalid') !== false || strpos($text, '550') !== false || strpos($text, '553') !== false) {
            return 'Recipient address was rejected or is invalid.';
        }

        if (strpos($text, 'quota') !== false || strpos($text, 'limit') !== false || strpos($text, 'rate') !== false || strpos($text, 'too many') !== false || strpos($text, '452') !== false || strpos($text, '421') !== false) {
            return 'Provider quota or rate limit was reached.';
        }

        if (strpos($text, 'spam') !== false || strpos($text, 'blocked') !== false || strpos($text, 'policy') !== false || strpos($text, 'rejected') !== false || strpos($text, '554') !== false) {
            return 'Message was rejected by the provider policy, spam filter, or recipient server.';
        }

        if (strpos($text, 'dns') !== false || strpos($text, 'resolve') !== false || strpos($text, 'getaddrinfo') !== false) {
            return 'SMTP host could not be resolved. Check DNS and SMTP host spelling.';
        }

        return 'The mail server returned a generic send failure. Review the SMTP message and sender configuration.';
    }

    private function get_campaign_recipient_hash($email) {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return '';
        }

        return hash_hmac('sha256', $email, wp_salt('auth'));
    }

    public function force_campaign_mail_from($from_email) {
        if (!empty($this->campaign_mailer_account['email_address']) && is_email($this->campaign_mailer_account['email_address'])) {
            return (string) $this->campaign_mailer_account['email_address'];
        }

        return $from_email;
    }

    public function force_campaign_mail_from_name($from_name) {
        if (!empty($this->campaign_mailer_account['sender_name'])) {
            return (string) $this->campaign_mailer_account['sender_name'];
        }

        if (!empty($this->campaign_mailer_account['account_label'])) {
            return (string) $this->campaign_mailer_account['account_label'];
        }

        return $from_name;
    }

    public function configure_campaign_phpmailer($phpmailer) {
        $account = $this->campaign_mailer_account;
        if (empty($account['smtp_host']) || empty($account['email_address']) || empty($account['app_password'])) {
            $this->log_campaign_mail_debug('phpmailer_config_skipped', array(
                'reason' => 'smtp_host, email_address, or app_password is missing.',
            ));
            return;
        }

        $phpmailer->clearReplyTos();
        $phpmailer->Mailer = 'smtp';
        $phpmailer->isSMTP();
        $phpmailer->Host = (string) $account['smtp_host'];
        $phpmailer->Port = !empty($account['smtp_port']) ? (int) $account['smtp_port'] : 587;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = (string) $account['email_address'];
        $phpmailer->Password = (string) $account['app_password'];
        $phpmailer->SMTPOptions = array();
        $phpmailer->AuthType = '';

        $encryption = isset($account['encryption']) ? strtolower((string) $account['encryption']) : 'tls';
        if (in_array($encryption, array('ssl', 'tls'), true)) {
            $phpmailer->SMTPSecure = $encryption;
        } else {
            $phpmailer->SMTPSecure = '';
        }

        $sender_name = !empty($account['sender_name']) ? (string) $account['sender_name'] : get_bloginfo('name');
        $phpmailer->setFrom((string) $account['email_address'], $sender_name, false);

        $this->log_campaign_mail_debug('phpmailer_configured', array(
            'host' => $phpmailer->Host,
            'port' => $phpmailer->Port,
            'smtp_auth' => (bool) $phpmailer->SMTPAuth,
            'smtp_secure' => $phpmailer->SMTPSecure,
            'username' => $phpmailer->Username,
            'from' => $phpmailer->From,
        ));
    }

    public function capture_campaign_mail_failure($error) {
        if (!($error instanceof WP_Error)) {
            return;
        }

        $error_data = $error->get_error_data();
        $entry = array(
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data' => is_array($error_data) ? $this->sanitize_mail_error_data_for_log($error_data) : $error_data,
        );

        $this->campaign_mail_errors[] = $entry;
        $this->log_campaign_mail_debug('wp_mail_failed', $entry);
    }

    private function resolve_campaign_recipients($campaign_message) {
        $contacts = $this->get_contacts();
        $contact_ids = array_flip($this->resolve_campaign_contact_ids($campaign_message));

        $emails = array();
        foreach ($contacts as $contact) {
            $contact_id = isset($contact['id']) ? (string) $contact['id'] : '';
            $email = isset($contact['email_address']) ? sanitize_email($contact['email_address']) : '';
            $status = isset($contact['status']) ? strtolower((string) $contact['status']) : 'subscribed';

            if ($contact_id === '' || !isset($contact_ids[$contact_id]) || $status === 'unsubscribed' || !$email || !is_email($email)) {
                continue;
            }

            $emails[strtolower($email)] = $email;
        }

        return array_values($emails);
    }

    private function resolve_campaign_contact_ids($campaign_message) {
        $include_tokens = $this->get_campaign_audience_tokens($campaign_message, 'include');
        $exclude_tokens = $this->get_campaign_audience_tokens($campaign_message, 'exclude');
        $included = $this->resolve_campaign_audience_tokens_to_contact_ids($include_tokens);
        $excluded = array_flip($this->resolve_campaign_audience_tokens_to_contact_ids($exclude_tokens));

        if (empty($included)) {
            return array();
        }

        $final = array();
        foreach ($included as $contact_id) {
            if (!isset($excluded[$contact_id])) {
                $final[] = $contact_id;
            }
        }

        return array_values(array_unique($final));
    }

    private function get_campaign_send_error_message($error, $strings) {
        if ($error instanceof WP_Error && $error->get_error_code() === 'no_recipients') {
            return $strings['notice_no_recipients'];
        }

        if ($error instanceof WP_Error && $error->get_error_code() === 'sender_not_configured') {
            return isset($strings['notice_sender_not_configured']) ? $strings['notice_sender_not_configured'] : $strings['notice_send_error'];
        }

        if ($error instanceof WP_Error && $error->get_error_code() === 'sender_capacity_insufficient') {
            return isset($strings['notice_sender_capacity_insufficient']) ? $strings['notice_sender_capacity_insufficient'] : $strings['notice_send_error'];
        }

        if ($error instanceof WP_Error) {
            $error_data = $error->get_error_data();
            if (is_array($error_data) && !empty($error_data['mail_errors'][0]['message'])) {
                return $strings['notice_send_error'] . ' جزئیات: ' . $error_data['mail_errors'][0]['message'];
            }

            if ($error->get_error_message() !== '') {
                return $strings['notice_send_error'] . ' جزئیات: ' . $error->get_error_message();
            }
        }

        return $strings['notice_send_error'];
    }

    private function log_campaign_mail_debug($event, $context = array()) {
        if (!$this->should_log_campaign_mail_debug()) {
            return;
        }

        if (!is_array($context)) {
            $context = array('value' => $context);
        }

        $context = $this->sanitize_mail_error_data_for_log($context);
        $log_line = '[' . current_time('mysql') . '] [SMark Email Campaign] ' . $event . ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        error_log($log_line);

        $log_dir = $this->get_campaign_mail_debug_log_dir();
        if ($log_dir !== '' && wp_mkdir_p($log_dir)) {
            $this->protect_campaign_mail_debug_log_dir($log_dir);
            error_log($log_line . PHP_EOL, 3, trailingslashit($log_dir) . 'smark-email-campaign.log');
        }
    }

    private function should_log_campaign_mail_debug() {
        $enabled = defined('WP_DEBUG') && (bool) WP_DEBUG;

        if (defined('SMARK_EMAIL_CAMPAIGN_FILE_LOG')) {
            $enabled = (bool) SMARK_EMAIL_CAMPAIGN_FILE_LOG;
        }

        return (bool) apply_filters('smark_email_campaign_debug_log_enabled', $enabled);
    }

    private function get_campaign_mail_debug_log_dir() {
        if (defined('SMARK_EMAIL_CAMPAIGN_LOG_DIR') && is_string(SMARK_EMAIL_CAMPAIGN_LOG_DIR) && SMARK_EMAIL_CAMPAIGN_LOG_DIR !== '') {
            $log_dir = (string) SMARK_EMAIL_CAMPAIGN_LOG_DIR;
        } else {
            $site_hash = function_exists('wp_hash') ? wp_hash(home_url('/')) : md5(home_url('/'));
            $log_dir = trailingslashit(sys_get_temp_dir()) . 'smark-email-campaign-logs-' . substr($site_hash, 0, 12);
        }

        $log_dir = (string) apply_filters('smark_email_campaign_debug_log_dir', $log_dir);
        return $log_dir !== '' ? untrailingslashit($log_dir) : '';
    }

    private function protect_campaign_mail_debug_log_dir($log_dir) {
        $log_dir = trailingslashit($log_dir);

        if (!file_exists($log_dir . 'index.php')) {
            error_log("<?php\n// Silence is golden.\n", 3, $log_dir . 'index.php');
        }

        if (!file_exists($log_dir . '.htaccess')) {
            error_log("Deny from all\n", 3, $log_dir . '.htaccess');
        }

        if (!file_exists($log_dir . 'web.config')) {
            $web_config = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <security>\n      <requestFiltering>\n        <hiddenSegments>\n          <add segment=\"smark-email-campaign.log\" />\n        </hiddenSegments>\n      </requestFiltering>\n    </security>\n  </system.webServer>\n</configuration>\n";
            error_log($web_config, 3, $log_dir . 'web.config');
        }
    }

    private function sanitize_mail_error_data_for_log($data) {
        if (!is_array($data)) {
            return $data;
        }

        $sanitized = array();
        foreach ($data as $key => $value) {
            $key_string = is_string($key) ? $key : (string) $key;
            $normalized_key = strtolower($key_string);

            if (strpos($normalized_key, 'password') !== false || strpos($normalized_key, 'pass') !== false || strpos($normalized_key, 'secret') !== false || strpos($normalized_key, 'token') !== false || strpos($normalized_key, 'nonce') !== false) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if ($normalized_key === 'message' && is_string($value) && strlen($value) > 300) {
                $sanitized[$key] = substr($value, 0, 300) . '...';
                continue;
            }

            if ($normalized_key === 'to' && is_array($value)) {
                $sanitized[$key] = array_map(array($this, 'mask_email_for_log'), $value);
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_mail_error_data_for_log($value);
                continue;
            }

            if ($normalized_key === 'recipient' || $normalized_key === 'sender_email' || $normalized_key === 'username' || $normalized_key === 'from') {
                $sanitized[$key] = $this->mask_email_for_log((string) $value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function mask_email_for_log($email) {
        $email = sanitize_email($email);
        if ($email === '' || strpos($email, '@') === false) {
            return $email;
        }

        list($local, $domain) = explode('@', $email, 2);
        $prefix = substr($local, 0, 2);
        return $prefix . '***@' . $domain;
    }

    private function get_email_account_by_id($account_id) {
        if ($account_id === '') {
            return array();
        }

        foreach ($this->get_email_accounts() as $account) {
            if (isset($account['id']) && $account['id'] === $account_id) {
                return $account;
            }
        }

        return array();
    }

    private function get_current_panel_lang() {
        $lang = get_option('smark_panel_language', 'en');
        return ($lang === 'fa') ? 'fa' : 'en';
    }

    private function create_contacts_import_preview_from_upload() {
        if (empty($_FILES['contacts_file']) || !isset($_FILES['contacts_file']['tmp_name'])) {
            return new WP_Error('missing_file', __('No file was uploaded.', 'smark'));
        }

        $file = $_FILES['contacts_file'];
        $file_name = isset($file['name']) ? sanitize_file_name((string) $file['name']) : '';
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($extension, array('csv', 'xlsx'), true)) {
            return new WP_Error('invalid_file', __('Please upload a CSV or XLSX file.', 'smark'));
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $uploaded = wp_handle_upload($file, array(
            'test_form' => false,
            'mimes' => array(
                'csv'  => 'text/csv',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ));

        if (isset($uploaded['error']) || empty($uploaded['file'])) {
            return new WP_Error('upload_failed', isset($uploaded['error']) ? (string) $uploaded['error'] : __('Upload failed.', 'smark'));
        }

        $rows = $this->parse_contacts_import_file((string) $uploaded['file'], $extension);
        @unlink((string) $uploaded['file']);

        if (is_wp_error($rows) || count($rows) < 2) {
            return new WP_Error('parse_failed', __('The file could not be read or does not contain contact rows.', 'smark'));
        }

        $headers = array_map('sanitize_text_field', array_shift($rows));
        $headers = $this->normalize_import_headers($headers);
        $rows = array_slice($rows, 0, 1000);
        $rows = array_values(array_filter($rows, function($row) {
            return is_array($row) && implode('', array_map('strval', $row)) !== '';
        }));

        if (empty($headers) || empty($rows)) {
            return new WP_Error('empty_file', __('The file does not contain usable contacts.', 'smark'));
        }

        $token = wp_generate_password(20, false, false);
        $payload = array(
            'headers' => $headers,
            'rows'    => $rows,
        );

        set_transient($this->get_contacts_import_transient_key($token), $payload, 30 * MINUTE_IN_SECONDS);

        return array(
            'token' => sanitize_key($token),
            'payload' => $payload,
        );
    }

    private function sanitize_contacts_import_mapping($raw_mapping) {
        $mapping = array();
        foreach ((array) $raw_mapping as $field_key => $column_index) {
            $field_key = sanitize_key($field_key);
            $mapping[$field_key] = ($column_index === '') ? '' : absint($column_index);
        }
        return $mapping;
    }

    private function import_contacts_from_payload($payload, $mapping, $default_segment) {
        $contacts = $this->get_contacts();
        $existing_emails = array();
        foreach ($contacts as $contact) {
            if (!empty($contact['email_address'])) {
                $existing_emails[strtolower((string) $contact['email_address'])] = true;
            }
        }

        $imported_count = 0;
        foreach ($payload['rows'] as $row) {
            $email = $this->get_mapped_import_value($row, $mapping, 'email_address');
            $email = sanitize_email($email);
            if (!$email || !is_email($email) || isset($existing_emails[strtolower($email)])) {
                continue;
            }

            $segment = sanitize_text_field($this->get_mapped_import_value($row, $mapping, 'segment'));
            if ($segment === '') {
                $segment = $default_segment;
            }

            $status = sanitize_key($this->get_mapped_import_value($row, $mapping, 'status'));
            $status = $this->normalize_contact_status($status);

            $contacts[] = array(
                'id'            => wp_generate_uuid4(),
                'first_name'    => sanitize_text_field($this->get_mapped_import_value($row, $mapping, 'first_name')),
                'last_name'     => sanitize_text_field($this->get_mapped_import_value($row, $mapping, 'last_name')),
                'email_address' => $email,
                'phone'         => sanitize_text_field($this->get_mapped_import_value($row, $mapping, 'phone')),
                'segment'       => $segment,
                'source'        => sanitize_text_field($this->get_mapped_import_value($row, $mapping, 'source')),
                'status'        => $status,
                'notes'         => sanitize_textarea_field($this->get_mapped_import_value($row, $mapping, 'notes')),
                'created_at'    => current_time('mysql'),
            );

            $existing_emails[strtolower($email)] = true;
            $imported_count++;
        }

        if ($imported_count > 0) {
            update_option(self::OPTION_CONTACTS, $contacts, false);
        }

        return $imported_count;
    }

    private function get_contacts_import_transient_key($token) {
        return 'smark_contacts_import_' . get_current_user_id() . '_' . sanitize_key($token);
    }

    private function get_contacts_import_payload($token) {
        $token = sanitize_key($token);
        if ($token === '') {
            return array();
        }

        $payload = get_transient($this->get_contacts_import_transient_key($token));
        return is_array($payload) ? $payload : array();
    }

    private function parse_contacts_import_file($file_path, $extension) {
        if ($extension === 'csv') {
            return $this->parse_contacts_import_csv($file_path);
        }

        if ($extension === 'xlsx') {
            return $this->parse_contacts_import_xlsx($file_path);
        }

        return new WP_Error('unsupported_file', 'Unsupported file type.');
    }

    private function parse_contacts_import_csv($file_path) {
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('csv_open_failed', 'Unable to open CSV file.');
        }

        $first_line = fgets($handle);
        $delimiter = ',';
        if ($first_line !== false) {
            $delimiter_counts = array(
                ','  => substr_count($first_line, ','),
                ';'  => substr_count($first_line, ';'),
                "\t" => substr_count($first_line, "\t"),
            );
            arsort($delimiter_counts);
            $delimiter = (string) key($delimiter_counts);
            rewind($handle);
        }

        $rows = array();
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]);
            }
            $rows[] = array_map('sanitize_text_field', $data);
            if (count($rows) > 1001) {
                break;
            }
        }

        fclose($handle);
        return $rows;
    }

    private function parse_contacts_import_xlsx($file_path) {
        if (!class_exists('SimpleXLSX')) {
            $lib = dirname(__DIR__) . '/keyword-research/lib/simple_xlsx.php';
            if (is_readable($lib)) {
                require_once $lib;
            }
        }

        if (!class_exists('SimpleXLSX')) {
            return new WP_Error('xlsx_reader_missing', 'XLSX reader is not available.');
        }

        $xlsx = SimpleXLSX::parse($file_path);
        if (!$xlsx) {
            return new WP_Error('xlsx_parse_failed', 'Unable to parse XLSX file.');
        }

        $rows = array();
        foreach ($xlsx->rows() as $row) {
            $rows[] = array_map('sanitize_text_field', $row);
            if (count($rows) > 1001) {
                break;
            }
        }

        return $rows;
    }

    private function normalize_import_headers($headers) {
        foreach ($headers as $index => $header) {
            $header = trim((string) $header);
            if ($header === '') {
                $headers[$index] = 'Column ' . ($index + 1);
            } else {
                $headers[$index] = $header;
            }
        }

        return $headers;
    }

    private function get_contact_import_fields($strings) {
        return array(
            'first_name'    => $strings['field_first_name'],
            'last_name'     => $strings['field_last_name'],
            'email_address' => $strings['field_email'],
            'phone'         => $strings['field_phone'],
            'segment'       => $strings['field_segment'],
            'source'        => $strings['field_source'],
            'status'        => $strings['field_status'],
            'notes'         => $strings['field_notes'],
        );
    }

    private function guess_contact_import_column($field_key, $header_label) {
        $header = strtolower(trim((string) $header_label));
        $header = str_replace(array(' ', '-', '_'), '', $header);

        $aliases = array(
            'first_name'    => array('firstname', 'name', 'نام'),
            'last_name'     => array('lastname', 'family', 'surname', 'نامخانوادگی'),
            'email_address' => array('email', 'emailaddress', 'mail', 'ایمیل'),
            'phone'         => array('phone', 'mobile', 'tel', 'شماره', 'موبایل', 'تلفن'),
            'segment'       => array('segment', 'list', 'group', 'سگمنت', 'لیست', 'گروه'),
            'source'        => array('source', 'utm', 'channel', 'منبع'),
            'status'        => array('status', 'وضعیت'),
            'notes'         => array('notes', 'note', 'description', 'یادداشت', 'توضیحات'),
        );

        foreach ($aliases[$field_key] ?? array() as $alias) {
            $alias = str_replace(array(' ', '-', '_'), '', strtolower($alias));
            if ($header === $alias || strpos($header, $alias) !== false) {
                return true;
            }
        }

        return false;
    }

    private function get_mapped_import_value($row, $mapping, $field_key) {
        if (!isset($mapping[$field_key]) || $mapping[$field_key] === '') {
            return '';
        }

        $index = (int) $mapping[$field_key];
        return isset($row[$index]) ? (string) $row[$index] : '';
    }

    private function normalize_contact_status($status) {
        $status = strtolower(trim((string) $status));
        if (in_array($status, array('lead', 'لید'), true)) {
            return 'lead';
        }
        if (in_array($status, array('unsubscribed', 'unsubscribe', 'لغوعضویت', 'لغو عضویت'), true)) {
            return 'unsubscribed';
        }
        return 'subscribed';
    }

    private function get_email_marketing_inline_js() {
        return <<<'JS'
(function ($) {
    function showNotification(message, type) {
        if (!message) {
            return;
        }

        var $notice = $('.smark-notification');
        if (!$notice.length) {
            $notice = $('<div class="smark-notification" role="status" aria-live="polite" />').appendTo('body');
        }

        var normalizedType = type || 'info';
        var isPageRtl = $('.wrap.smark-seo-optimization-page').hasClass('rtl') || $('.wrap.smark-seo-optimization-page').attr('data-lang') === 'fa';
        var titlesFa = {
            success: 'موفقیت‌آمیز',
            error: 'خطا',
            warning: 'هشدار',
            info: 'اطلاع‌رسانی'
        };
        var titlesEn = {
            success: 'Congratulations!',
            error: 'Something went wrong!',
            warning: 'Warning!',
            info: 'Did you know?'
        };
        var titles = isPageRtl ? titlesFa : titlesEn;
        var icons = {
            success: '✓',
            error: '×',
            warning: '!',
            info: 'i'
        };

        $notice.removeClass('success error warning info visible rtl').addClass(normalizedType).empty();
        var $icon = $('<div class="smark-notification__icon" aria-hidden="true" />').text(icons[normalizedType] || icons.info);
        var $body = $('<div class="smark-notification__body" />');
        $('<strong class="smark-notification__title" />').text(titles[normalizedType] || titles.info).appendTo($body);
        $('<span class="smark-notification__message" />').text(message).appendTo($body);
        var $close = $('<button type="button" class="smark-notification__close" aria-label="Close notification"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>');

        $close.on('click', function () {
            clearTimeout($notice.data('timeout'));
            $notice.removeClass('visible');
            setTimeout(function () {
                if (!$notice.hasClass('visible')) {
                    $notice.remove();
                }
            }, 80);
        });

        $notice.append($icon, $body, $close);

        var $page = $('.wrap.smark-seo-optimization-page');
        if (isPageRtl) {
            $notice.addClass('rtl');
        }

        window.setTimeout(function () {
            $notice.addClass('visible');
        }, 20);

        clearTimeout($notice.data('timeout'));
        $notice.data('timeout', null);
    }

    function openImportModal() {
        var $panel = $('#smarkEmailImportModal');
        var $grid = $panel.closest('.seo-grid');

        $grid.find('[data-smark-contact-section], #smarkEmailContactAddModal, #smarkEmailContactListModal, #smarkEmailContactTagModal')
            .addClass('smark-email-contact-section-hidden')
            .prop('hidden', true)
            .attr('aria-hidden', 'true');

        $panel
            .removeClass('smark-email-contact-section-hidden')
            .prop('hidden', false)
            .attr('aria-hidden', 'false');

        resetContactWorkflowScroll();
    }

    function resetContactWorkflowScroll() {
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
    }

    function closeImportModal() {
        var $panel = $('#smarkEmailImportModal');
        var $grid = $panel.closest('.seo-grid');

        if (!$panel.length || $panel.prop('hidden')) {
            return;
        }

        $panel
            .addClass('smark-email-contact-section-hidden')
            .prop('hidden', true)
            .attr('aria-hidden', 'true');

        $grid.find('[data-smark-contact-section]')
            .removeClass('smark-email-contact-section-hidden')
            .prop('hidden', false)
            .attr('aria-hidden', 'false');
    }

    function openContactListModal() {
        var $panel = $('#smarkEmailContactListModal');
        var $grid = $panel.closest('.seo-grid');

        $grid.find('[data-smark-contact-section], #smarkEmailContactAddModal, #smarkEmailImportModal, #smarkEmailContactTagModal')
            .addClass('smark-email-contact-section-hidden')
            .prop('hidden', true)
            .attr('aria-hidden', 'true');

        $panel
            .removeClass('smark-email-contact-section-hidden')
            .prop('hidden', false)
            .attr('aria-hidden', 'false');

        resetContactWorkflowScroll();
    }

    function closeContactListModal() {
        var $panel = $('#smarkEmailContactListModal');
        var $grid = $panel.closest('.seo-grid');

        if (!$panel.length || $panel.prop('hidden')) {
            return;
        }

        $panel
            .addClass('smark-email-contact-section-hidden')
            .prop('hidden', true)
            .attr('aria-hidden', 'true');

        $grid.find('[data-smark-contact-section]')
            .removeClass('smark-email-contact-section-hidden')
            .prop('hidden', false)
            .attr('aria-hidden', 'false');
    }

    function openContactTagModal() {
        var $panel = $('#smarkEmailContactTagModal');
        var $grid = $panel.closest('.seo-grid');

        $grid.find('[data-smark-contact-section], #smarkEmailContactAddModal, #smarkEmailImportModal, #smarkEmailContactListModal')
            .addClass('smark-email-contact-section-hidden')
            .prop('hidden', true)
            .attr('aria-hidden', 'true');

        $panel
            .removeClass('smark-email-contact-section-hidden')
            .prop('hidden', false)
            .attr('aria-hidden', 'false');

        resetContactWorkflowScroll();
    }

    function closeContactTagModal() {
        var $panel = $('#smarkEmailContactTagModal');
        var $grid = $panel.closest('.seo-grid');

        if (!$panel.length || $panel.prop('hidden')) {
            return;
        }

        $panel
            .addClass('smark-email-contact-section-hidden')
            .prop('hidden', true)
            .attr('aria-hidden', 'true');

        $grid.find('[data-smark-contact-section]')
            .removeClass('smark-email-contact-section-hidden')
            .prop('hidden', false)
            .attr('aria-hidden', 'false');
    }

    function getContactEditData(contactId) {
        var $script = $('[data-smark-contact-json="' + contactId + '"]').first();
        if (!$script.length) {
            return null;
        }

        try {
            return JSON.parse($.trim($script.text() || '{}'));
        } catch (error) {
            return null;
        }
    }

    function openContactEditModal(contactId) {
        var data = getContactEditData(contactId);
        var $modal = $('#smarkEmailContactEditModal');
        var $form = $('#smarkEmailContactEditForm');

        if (!data || !$modal.length || !$form.length) {
            return;
        }

        $form.find('[name="contact_id"]').val(data.id || '');
        $form.find('[name="first_name"]').val(data.first_name || '');
        $form.find('[name="last_name"]').val(data.last_name || '');
        $form.find('[name="email_address"]').val(data.email_address || '');
        $form.find('[name="phone"]').val(data.phone || '');
        $form.find('[name="contact_list_id"]').val(data.contact_list_id || '');
        $form.find('[name="source"]').val(data.source || '');
        $form.find('[name="status"]').val(data.status || 'subscribed');
        $form.find('[name="notes"]').val(data.notes || '');
        $form.find('[name="contact_tag_ids[]"] option').prop('selected', false);
        (data.contact_tag_ids || []).forEach(function (tagId) {
            $form.find('[name="contact_tag_ids[]"] option[value="' + tagId + '"]').prop('selected', true);
        });

        $modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('smark-email-modal-open');
        window.setTimeout(function () {
            $form.find('[name="first_name"]').trigger('focus');
        }, 50);
    }

    function closeContactEditModal() {
        $('#smarkEmailContactEditModal').removeClass('is-open').attr('aria-hidden', 'true');
        if (!$('#smarkEmailImportModal').hasClass('is-open') && !$('#smarkEmailAccountEditModal').hasClass('is-open') && !$('#smarkEmailContactListModal').hasClass('is-open') && !$('#smarkEmailContactTagModal').hasClass('is-open') && !$('#smarkEmailAudiencePickerModal').hasClass('is-open')) {
            $('body').removeClass('smark-email-modal-open');
        }
    }

    function openAccountEditModal($trigger) {
        var $modal = $('#smarkEmailAccountEditModal');
        var $form = $('#smarkEmailAccountEditForm');
        var rawProvider = $trigger.attr('data-provider') || 'email';
        var provider = rawProvider === 'gmail' || rawProvider === 'outlook' ? rawProvider : 'email';

        $form.find('[name="account_id"]').val($trigger.attr('data-account-id') || '');
        $form.find('[name="provider"]').val(provider);
        $form.find('[name="account_label"]').val($trigger.attr('data-account-label') || '');
        $form.find('[name="sender_name"]').val($trigger.attr('data-sender-name') || '');
        $form.find('[name="email_address"]').val($trigger.attr('data-email-address') || '');
        $form.find('[name="app_password"]').val('');
        $form.find('[name="daily_limit"]').val($trigger.attr('data-daily-limit') || '100');
        $form.find('[name="smtp_host"]').val($trigger.attr('data-smtp-host') || '');
        $form.find('[name="smtp_port"]').val($trigger.attr('data-smtp-port') || '587');
        $form.find('[name="encryption"]').val($trigger.attr('data-encryption') || 'tls');

        showEmailAccountWorkflowPanel($modal);
        window.setTimeout(function () {
            $form.find('[name="account_label"]').trigger('focus');
        }, 50);
    }

    function closeAccountEditModal() {
        hideEmailAccountWorkflowPanel($('#smarkEmailAccountEditModal'));
    }

    function showEmailAccountWorkflowPanel($panel) {
        if (!$panel.length) {
            return;
        }

        var $grid = $panel.closest('.seo-grid');
        $grid.find('[data-smark-email-account-section], #smarkEmailAccountEditModal')
            .addClass('smark-email-contact-section-hidden')
            .prop('hidden', true)
            .attr('aria-hidden', 'true');

        $panel
            .removeClass('smark-email-contact-section-hidden')
            .prop('hidden', false)
            .attr('aria-hidden', 'false');

        resetContactWorkflowScroll();
    }

    function hideEmailAccountWorkflowPanel($panel) {
        var $grid = $panel.closest('.seo-grid');

        if (!$panel.length || $panel.prop('hidden')) {
            return;
        }

        $panel
            .addClass('smark-email-contact-section-hidden')
            .prop('hidden', true)
            .attr('aria-hidden', 'true');

        $grid.find('[data-smark-email-account-section]')
            .removeClass('smark-email-contact-section-hidden')
            .prop('hidden', false)
            .attr('aria-hidden', 'false');
    }

    function updateEmailProviderForm(provider) {
        var normalizedProvider = provider === 'gmail' || provider === 'outlook' ? provider : 'email';
        var $form = $('#smarkEmailAccountForm');

        $('[data-smark-provider-text]').each(function () {
            var $el = $(this);
            var text = $el.attr('data-' + normalizedProvider + '-text');
            if (typeof text === 'string') {
                $el.text(text);
            }
        });

        $form.find('[data-' + normalizedProvider + '-placeholder]').each(function () {
            var $el = $(this);
            $el.attr('placeholder', $el.attr('data-' + normalizedProvider + '-placeholder') || '');
        });

        $('[data-smark-gmail-only]').toggle(normalizedProvider === 'gmail');
        $('[data-smark-outlook-only]').toggle(normalizedProvider === 'outlook');

        var $smtpHost = $form.find('[name="smtp_host"]');
        if ($smtpHost.length && !$smtpHost.val()) {
            $smtpHost.val($smtpHost.attr('data-' + normalizedProvider + '-value') || '');
        }

        if (normalizedProvider === 'gmail' || normalizedProvider === 'outlook') {
            $form.find('[name="smtp_port"]').val('587');
            $form.find('[name="encryption"]').val('tls');
            $smtpHost.val($smtpHost.attr('data-' + normalizedProvider + '-value') || (normalizedProvider === 'gmail' ? 'smtp.gmail.com' : 'smtp.office365.com'));
        } else if ($smtpHost.val() === ($smtpHost.attr('data-gmail-value') || 'smtp.gmail.com') || $smtpHost.val() === ($smtpHost.attr('data-outlook-value') || 'smtp.office365.com')) {
            $smtpHost.val('');
        }
    }

    function formatSmarkNumber(value) {
        var number = parseInt(value, 10);
        if (isNaN(number)) {
            number = 0;
        }

        try {
            return number.toLocaleString();
        } catch (error) {
            return String(number);
        }
    }

    function getAudienceTokens(mode, $form) {
        var tokens = [];
        $form = $form && $form.length ? $form : $('#smarkEmailCampaignMessageForm');
        $form.find('[data-smark-audience-builder="' + mode + '"] [data-smark-audience-inputs] input').each(function () {
            tokens.push($(this).val());
        });
        return tokens;
    }

    function getAudienceOptionContacts(token) {
        var $option = $('[data-smark-audience-option]').filter(function () {
            return $(this).val() === token;
        }).first();
        var ids = String($option.attr('data-contact-ids') || '').split(',');
        var contacts = {};
        ids.forEach(function (id) {
            id = $.trim(id);
            if (id) {
                contacts[id] = true;
            }
        });
        return contacts;
    }

    function resolveAudienceContactIds(tokens) {
        var contacts = {};
        tokens.forEach(function (token) {
            var tokenContacts = getAudienceOptionContacts(token);
            Object.keys(tokenContacts).forEach(function (id) {
                contacts[id] = true;
            });
        });
        return contacts;
    }

    function getFinalAudienceContactIds($form) {
        var included = resolveAudienceContactIds(getAudienceTokens('include', $form));
        var excluded = resolveAudienceContactIds(getAudienceTokens('exclude', $form));
        Object.keys(excluded).forEach(function (id) {
            delete included[id];
        });
        return included;
    }

    function getAudienceOptionLabel(token) {
        var $option = $('[data-smark-audience-option]').filter(function () {
            return $(this).val() === token;
        }).first();
        return $option.attr('data-label') || token;
    }

    function renderAudienceBuilder($builder) {
        var mode = $builder.attr('data-smark-audience-builder') || 'include';
        var tokens = [];
        $builder.find('[data-smark-audience-inputs] input').each(function () {
            tokens.push($(this).val());
        });
        var $chips = $builder.find('[data-smark-audience-chips]');
        var emptyText = $chips.attr('data-empty-text') || 'Nothing selected';
        var moreText = $chips.attr('data-more-text') || 'More';

        $chips.empty();
        if (!tokens.length) {
            $('<span class="smark-email-audience-empty" />').text(emptyText).appendTo($chips);
            return;
        }

        tokens.slice(0, 3).forEach(function (token) {
            $('<button type="button" class="smark-email-audience-chip" data-open-smark-audience-picker />')
                .attr('data-mode', mode)
                .text(getAudienceOptionLabel(token))
                .appendTo($chips);
        });

        if (tokens.length > 3) {
            $('<button type="button" class="smark-email-audience-chip smark-email-audience-chip--more" data-open-smark-audience-picker />')
                .attr('data-mode', mode)
                .text(moreText.replace('%s', tokens.length - 3))
                .appendTo($chips);
        }
    }

    function syncAudienceBuilders() {
        $('[data-smark-audience-builder]').each(function () {
            renderAudienceBuilder($(this));
        });
        updateCampaignCapacityWarnings();
    }

    function openAudiencePicker(mode, $trigger) {
        var normalizedMode = mode === 'exclude' ? 'exclude' : 'include';
        var $modal = $('#smarkEmailAudiencePickerModal');
        var $title = $('#smarkEmailAudiencePickerTitle');
        var $form = $trigger && $trigger.length ? $trigger.closest('form') : $('#smarkEmailCampaignMessageForm');
        var formId = $form.attr('id') || 'smarkEmailCampaignMessageForm';
        var returnPanelId = $form.closest('#smarkEmailCampaignEditModal').length ? 'smarkEmailCampaignEditModal' : '';
        var selected = {};
        getAudienceTokens(normalizedMode, $form).forEach(function (token) {
            selected[token] = true;
        });

        $modal.attr('data-mode', normalizedMode);
        $modal.attr('data-target-form', formId);
        $modal.attr('data-return-panel', returnPanelId);
        $title.text(normalizedMode === 'exclude' ? ($title.attr('data-exclude-title') || $title.text()) : ($title.attr('data-include-title') || $title.text()));
        $modal.find('[data-smark-audience-option]').each(function () {
            $(this).prop('checked', !!selected[$(this).val()]);
        });
        $modal.find('[data-smark-audience-contact-search]').val('');
        updateAudienceContactSearch();
        updateAudienceSelectedCount();
        showCampaignAudienceSection($modal);
    }

    function closeAudiencePicker() {
        hideCampaignAudienceSection();
    }

    function showCampaignAudienceSection($panel) {
        showCampaignWorkflowPanel($panel);
    }

    function hideCampaignAudienceSection() {
        var $panel = $('#smarkEmailAudiencePickerModal');
        var returnPanelId = $panel.attr('data-return-panel') || '';
        hideCampaignWorkflowPanel($panel, returnPanelId ? $('#' + returnPanelId) : null);
        $panel.removeAttr('data-return-panel');
    }

    function showCampaignWorkflowPanel($panel) {
        if (!$panel.length) {
            return;
        }

        var $grid = $panel.closest('.seo-grid');
        $grid.find('[data-smark-campaign-message-section], #smarkEmailAudiencePickerModal, #smarkEmailCampaignEditModal, .smark-email-campaign-performance-section')
            .addClass('smark-email-contact-section-hidden')
            .prop('hidden', true)
            .attr('aria-hidden', 'true');

        $panel
            .removeClass('smark-email-contact-section-hidden')
            .prop('hidden', false)
            .attr('aria-hidden', 'false');

        resetContactWorkflowScroll();
    }

    function hideCampaignWorkflowPanel($panel, $returnPanel) {
        var $grid = $panel.closest('.seo-grid');

        if (!$panel.length || $panel.prop('hidden')) {
            return;
        }

        $panel
            .addClass('smark-email-contact-section-hidden')
            .prop('hidden', true)
            .attr('aria-hidden', 'true');

        if ($returnPanel && $returnPanel.length) {
            $returnPanel
                .removeClass('smark-email-contact-section-hidden')
                .prop('hidden', false)
                .attr('aria-hidden', 'false');
            return;
        }

        $grid.find('[data-smark-campaign-message-section]')
            .removeClass('smark-email-contact-section-hidden')
            .prop('hidden', false)
            .attr('aria-hidden', 'false');
    }

    function updateAudienceContactSearch() {
        var $modal = $('#smarkEmailAudiencePickerModal');
        var query = $.trim(String($modal.find('[data-smark-audience-contact-search]').val() || '').toLowerCase());
        var shown = 0;
        var limit = 10;

        $modal.find('[data-smark-audience-contact-row]').each(function () {
            var $row = $(this);
            var haystack = String($row.attr('data-search') || '').toLowerCase();
            var matches = query === '' || haystack.indexOf(query) !== -1;
            var shouldShow = matches && shown < limit;
            $row.toggle(shouldShow);
            if (shouldShow) {
                shown++;
            }
        });

        $modal.find('[data-smark-audience-contact-limit-note]').toggle($modal.find('[data-smark-audience-contact-row]').length > limit);
    }

    function updateAudienceSelectedCount() {
        var $modal = $('#smarkEmailAudiencePickerModal');
        var count = $modal.find('[data-smark-audience-contact-row] [data-smark-audience-option]:checked').length;
        var $counter = $modal.find('[data-smark-audience-selected-count]');
        var template = $counter.attr('data-template') || '%s selected';
        $counter.text(template.replace('%s', formatSmarkNumber(count)));
    }

    function applyAudiencePicker() {
        var $modal = $('#smarkEmailAudiencePickerModal');
        var mode = $modal.attr('data-mode') === 'exclude' ? 'exclude' : 'include';
        var targetFormId = $modal.attr('data-target-form') || 'smarkEmailCampaignMessageForm';
        var $form = $('#' + targetFormId);
        var $builder = $form.find('[data-smark-audience-builder="' + mode + '"]');
        var $inputs = $builder.find('[data-smark-audience-inputs]');
        var fieldName = $inputs.attr('data-field-name') || (mode === 'include' ? 'target_includes[]' : 'target_excludes[]');

        $inputs.empty();
        $modal.find('[data-smark-audience-option]:checked').each(function () {
            $('<input type="hidden" />').attr('name', fieldName).val($(this).val()).appendTo($inputs);
        });
        syncAudienceBuilders();
        closeAudiencePicker();
    }

    function updateCampaignCapacityWarning($form) {
        $form = $form && $form.length ? $form : $('#smarkEmailCampaignMessageForm');
        if (!$form.length) {
            return;
        }

        var audienceCount = Object.keys(getFinalAudienceContactIds($form)).length;
        var remainingCapacity = 0;
        $form.find('[data-smark-sender-account-option]:checked').each(function () {
            var remaining = parseInt($(this).attr('data-remaining'), 10);
            if (!isNaN(remaining)) {
                remainingCapacity += remaining;
            }
        });

        var $warning = $form.find('[data-smark-capacity-warning]');
        if (!$warning.length) {
            return;
        }

        if (audienceCount > 0 && audienceCount > remainingCapacity) {
            var template = $warning.attr('data-warning-template') || '%1$s recipients selected, but capacity is %2$s.';
            $warning
                .text(template.replace('%1$s', formatSmarkNumber(audienceCount)).replace('%2$s', formatSmarkNumber(remainingCapacity)))
                .prop('hidden', false);
        } else {
            $warning.text('').prop('hidden', true);
        }
    }

    function updateCampaignCapacityWarnings() {
        $('#smarkEmailCampaignMessageForm, #smarkEmailCampaignMessageEditForm').each(function () {
            updateCampaignCapacityWarning($(this));
        });
    }

    function updateSenderPicker($picker) {
        var $checked = $picker.find('[data-smark-sender-account-option]:checked');
        var $inputs = $picker.find('[data-smark-sender-picker-inputs]');
        var $summary = $picker.find('[data-smark-sender-picker-summary]');
        var labels = [];

        $inputs.empty();
        $checked.each(function () {
            var $option = $(this);
            labels.push($option.attr('data-label') || $option.val());
            $('<input type="hidden" name="sender_account_ids[]" />').val($option.val()).appendTo($inputs);
        });

        if (!labels.length) {
            var $firstOption = $picker.find('[data-smark-sender-account-option]').first();
            if ($firstOption.length) {
                $firstOption.prop('checked', true);
                updateSenderPicker($picker);
                return;
            }
        }

        if (labels.length === 1) {
            $summary.text(labels[0]);
        } else if (labels.length > 1) {
            $summary.text(labels[0] + ' +' + (labels.length - 1));
        } else {
            $summary.text('');
        }
    }

    function closeSenderPickers($except) {
        $('[data-smark-sender-picker]').each(function () {
            var $picker = $(this);
            if ($except && $picker.is($except)) {
                return;
            }

            $picker.removeClass('is-open');
            $picker.find('[data-smark-sender-picker-toggle]').attr('aria-expanded', 'false');
            $picker.find('[data-smark-sender-picker-panel]').prop('hidden', true);
        });
    }

    function getCampaignMessageEditData(messageId) {
        var $script = $('[data-smark-campaign-message-json="' + messageId + '"]').first();
        if (!$script.length) {
            return null;
        }

        try {
            return JSON.parse($.trim($script.text() || '{}'));
        } catch (error) {
            return null;
        }
    }

    function setCampaignEditEditorContent(content) {
        content = content || '';
        if (window.tinyMCE && window.tinyMCE.get('smark_email_edit_body_editor')) {
            window.tinyMCE.get('smark_email_edit_body_editor').setContent(content);
        }
        $('#smark_email_edit_body_editor').val(content);
    }

    function initializeEmailEditor(editorId, forceRefresh) {
        var $editor = $('#' + editorId);

        if (!$editor.length) {
            return;
        }

        if (window.tinyMCE && window.tinyMCE.get(editorId)) {
            if (!forceRefresh) {
                return;
            }
            window.tinyMCE.execCommand('mceRemoveEditor', false, editorId);
        }

        $editor.closest('.wp-editor-wrap').find('.wp-editor-tabs').remove();

        if (window.wp && window.wp.editor && typeof window.wp.editor.initialize === 'function') {
            window.wp.editor.initialize(editorId, {
                tinymce: {
                    toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,undo,redo',
                    toolbar2: 'strikethrough,hr,pastetext,removeformat,charmap,outdent,indent,wp_adv'
                },
                quicktags: true,
                mediaButtons: false
            });
            return;
        }

        if (window.quicktags && typeof window.quicktags === 'function' && !$('#qt_' + editorId + '_toolbar').length) {
            window.quicktags({ id: editorId });
        }
    }

    function activateVisualEmailEditor(editorId) {
        if (window.switchEditors && typeof window.switchEditors.go === 'function') {
            window.switchEditors.go(editorId, 'tmce');
        }
    }

    function initializeCampaignMessageEditor() {
        initializeEmailEditor('smark_email_body_editor', false);
    }

    function initializeCampaignMessageEditEditor() {
        initializeEmailEditor('smark_email_edit_body_editor', true);
        activateVisualEmailEditor('smark_email_edit_body_editor');
    }

    function scrollToCampaignSavedMessages() {
        var $target = $('[data-smark-campaign-messages-list]').first();
        if (!$target.length) {
            return;
        }

        window.setTimeout(function () {
            $target.get(0).scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 80);
    }

    function populateAudienceBuilder($form, mode, tokens) {
        var $builder = $form.find('[data-smark-audience-builder="' + mode + '"]');
        var $inputs = $builder.find('[data-smark-audience-inputs]');
        var fieldName = $inputs.attr('data-field-name') || (mode === 'include' ? 'target_includes[]' : 'target_excludes[]');

        $inputs.empty();
        (tokens || []).forEach(function (token) {
            $('<input type="hidden" />').attr('name', fieldName).val(token).appendTo($inputs);
        });
        renderAudienceBuilder($builder);
    }

    function openCampaignEditModal(messageId) {
        var data = getCampaignMessageEditData(messageId);
        var $modal = $('#smarkEmailCampaignEditModal');
        var $form = $('#smarkEmailCampaignMessageEditForm');

        if (!data || !$modal.length || !$form.length) {
            return;
        }

        $form.find('[name="message_id"]').val(data.id || '');
        $form.find('[name="campaign_name"]').val(data.campaign_name || '');
        $form.find('[name="subject_line"]').val(data.subject_line || '');
        $form.find('[name="preview_text"]').val(data.preview_text || '');
        $form.find('[name="reply_to"]').val(data.reply_to || '');
        $form.find('[name="message_status"]').val(data.message_status || 'draft');
        $form.find('[name="internal_notes"]').val(data.internal_notes || '');

        var selectedSenders = {};
        (data.sender_account_ids || []).forEach(function (senderId) {
            selectedSenders[String(senderId)] = true;
        });
        $form.find('[data-smark-sender-account-option]').each(function () {
            $(this).prop('checked', !!selectedSenders[String($(this).val())]);
        });
        updateSenderPicker($form.find('[data-smark-sender-picker]'));

        showCampaignWorkflowPanel($modal);
        initializeCampaignMessageEditEditor();
        populateAudienceBuilder($form, 'include', data.target_includes || []);
        populateAudienceBuilder($form, 'exclude', data.target_excludes || []);
        setCampaignEditEditorContent(data.email_body || '');
        updateCampaignCapacityWarning($form);

        window.setTimeout(function () {
            initializeCampaignMessageEditEditor();
            setCampaignEditEditorContent(data.email_body || '');
            $form.find('[name="campaign_name"]').trigger('focus');
        }, 50);
    }

    function closeCampaignEditModal() {
        hideCampaignWorkflowPanel($('#smarkEmailCampaignEditModal'));
    }

    $(function () {
        var $page = $('.wrap.smark-seo-optimization-page');
        showNotification($page.attr('data-smark-notice-message') || '', $page.attr('data-smark-notice-type') || 'info');

        if ($page.attr('data-smark-open-import') === '1') {
            openImportModal();
        }

        updateEmailProviderForm($('#smark_email_provider').val() || 'email');
        $('[data-smark-sender-picker]').each(function () {
            updateSenderPicker($(this));
        });
        syncAudienceBuilders();

        $(document).on('smark:dashboard-embedded-view-loaded', function (event) {
            var detail = event.originalEvent && event.originalEvent.detail ? event.originalEvent.detail : {};
            $('[data-smark-sender-picker]').each(function () {
                updateSenderPicker($(this));
            });
            updateEmailProviderForm($('#smark_email_provider').val() || 'email');
            syncAudienceBuilders();
            if (detail.view === 'campaign-message') {
                initializeCampaignMessageEditor();
                if (window.SMarkEmailCampaignScrollTarget === 'saved-messages') {
                    window.SMarkEmailCampaignScrollTarget = '';
                    scrollToCampaignSavedMessages();
                }
            }
        });

        $(document).on('change', '#smark_email_provider', function () {
            updateEmailProviderForm($(this).val());
        });

        $(document).on('change', '[data-smark-performance-campaign-select]', function () {
            var $select = $(this);
            if ($select.closest('.smark-dashboard-embedded-view').length) {
                document.dispatchEvent(new window.CustomEvent('smark:dashboard-load-email-view', {
                    detail: {
                        view: 'performance-review',
                        params: {
                            campaign_id: $select.val() || ''
                        }
                    }
                }));
                return;
            }

            $select.closest('form').trigger('submit');
        });

        $(document).on('click', '[data-smark-sender-picker-toggle]', function (event) {
            event.preventDefault();
            var $picker = $(this).closest('[data-smark-sender-picker]');
            var isOpen = $picker.hasClass('is-open');
            closeSenderPickers($picker);
            $picker.toggleClass('is-open', !isOpen);
            $(this).attr('aria-expanded', isOpen ? 'false' : 'true');
            $picker.find('[data-smark-sender-picker-panel]').prop('hidden', isOpen);
        });

        $(document).on('click', function (event) {
            if (!$(event.target).closest('[data-smark-sender-picker]').length) {
                closeSenderPickers();
            }
        });

        $(document).on('change', '#smarkEmailCampaignMessageForm [data-smark-sender-account-option], #smarkEmailCampaignMessageEditForm [data-smark-sender-account-option]', function () {
            updateSenderPicker($(this).closest('[data-smark-sender-picker]'));
            updateCampaignCapacityWarning($(this).closest('form'));
        });

        $(document).on('click', '[data-open-smark-import]', function (event) {
            event.preventDefault();
            openImportModal();
        });

        $(document).on('click', '[data-open-smark-audience-picker]', function (event) {
            event.preventDefault();
            openAudiencePicker($(this).attr('data-mode') || $(this).closest('[data-smark-audience-builder]').attr('data-smark-audience-builder') || 'include', $(this));
        });

        $(document).on('click', '[data-close-smark-audience-picker]', function (event) {
            event.preventDefault();
            closeAudiencePicker();
        });

        $(document).on('click', '[data-open-smark-campaign-performance]', function (event) {
            event.preventDefault();
            var $trigger = $(this);
            var campaignId = $(this).attr('data-open-smark-campaign-performance') || '';
            var $modal = $('#smarkEmailCampaignPerformanceModal-' + campaignId);
            var $grid = $trigger.closest('.seo-grid');
            if (!$modal.length) {
                return;
            }
            if ($grid.length && !$modal.parent().is($grid)) {
                $modal.detach().appendTo($grid);
            }
            showCampaignWorkflowPanel($modal);
        });

        $(document).on('click', '[data-close-smark-campaign-performance]', function (event) {
            event.preventDefault();
            hideCampaignWorkflowPanel($(this).closest('.smark-email-campaign-performance-section'));
        });

        $(document).on('click', '[data-open-smark-campaign-edit]', function (event) {
            event.preventDefault();
            openCampaignEditModal($(this).attr('data-open-smark-campaign-edit') || '');
        });

        $(document).on('click', '[data-close-smark-campaign-edit]', function (event) {
            event.preventDefault();
            closeCampaignEditModal();
        });

        $(document).on('submit', '#smarkEmailCampaignMessageEditForm', function (event) {
            event.preventDefault();
            if (window.tinyMCE && typeof window.tinyMCE.triggerSave === 'function') {
                window.tinyMCE.triggerSave();
            }

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            var formData = new FormData(this);
            formData.set('action', 'smark_email_campaign_message_save_modal');

            $button.prop('disabled', true).text((smarkSeoOptimization.strings || {}).saving || 'Saving...');

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (response) {
                if (response && response.success && response.data) {
                    if (response.data.html) {
                        $('#smarkEmailCampaignMessagesList').html(response.data.html);
                    }
                    closeCampaignEditModal();
                    showNotification(response.data.message || '', 'success');
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
            }).always(function () {
                $button.prop('disabled', false).text(originalText);
            });
        });

        $(document).on('click', '[data-open-smark-failure-detail]', function (event) {
            event.preventDefault();
            var $trigger = $(this);
            var $modal = $('#smarkEmailFailureDetailModal');
            $modal.find('#smarkEmailFailureDetailTitle').text($trigger.attr('data-failure-title') || '');
            $modal.find('[data-smark-failure-recipient]').text($trigger.attr('data-failure-recipient') || '');
            $modal.find('[data-smark-failure-detail-text]').text($trigger.attr('data-failure-reason') || '');
            $modal.addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('smark-email-modal-open');
        });

        $(document).on('click', '[data-close-smark-failure-detail]', function (event) {
            event.preventDefault();
            $('#smarkEmailFailureDetailModal').removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('smark-email-modal-open');
        });

        $(document).on('click', '[data-apply-smark-audience-picker]', function (event) {
            event.preventDefault();
            applyAudiencePicker();
        });

        $(document).on('input', '[data-smark-audience-contact-search]', function () {
            updateAudienceContactSearch();
        });

        $(document).on('change', '#smarkEmailAudiencePickerModal [data-smark-audience-option]', function () {
            updateAudienceSelectedCount();
        });

        function showContactAddSection() {
            var $panel = $('#smarkEmailContactAddModal');
            var $grid = $panel.closest('.seo-grid');

            $grid.find('[data-smark-contact-section], #smarkEmailImportModal, #smarkEmailContactListModal, #smarkEmailContactTagModal')
                .addClass('smark-email-contact-section-hidden')
                .prop('hidden', true)
                .attr('aria-hidden', 'true');

            $panel
                .removeClass('smark-email-contact-section-hidden')
                .prop('hidden', false)
                .attr('aria-hidden', 'false');

            resetContactWorkflowScroll();
        }

        function hideContactAddSection() {
            var $panel = $('#smarkEmailContactAddModal');
            var $grid = $panel.closest('.seo-grid');

            $panel
                .addClass('smark-email-contact-section-hidden')
                .prop('hidden', true)
                .attr('aria-hidden', 'true');

            $grid.find('[data-smark-contact-section]')
                .removeClass('smark-email-contact-section-hidden')
                .prop('hidden', false)
                .attr('aria-hidden', 'false');
        }

        $(document).on('click', '[data-open-smark-contact-add]', function (event) {
            event.preventDefault();
            showContactAddSection();
        });

        $(document).on('click', '[data-close-smark-contact-add]', function (event) {
            event.preventDefault();
            hideContactAddSection();
        });

        $(document).on('submit', '#smarkEmailContactAddModal form', function (event) {
            event.preventDefault();
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            var formData = new FormData(this);

            formData.set('action', 'smark_email_contact_save_modal');
            formData.set('search_query', getContactsSearchQuery());
            $button.prop('disabled', true).text((smarkSeoOptimization.strings || {}).saving || 'Saving...');

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (response) {
                if (response && response.success && response.data) {
                    if (response.data.html) {
                        $('#smarkEmailContactsList').html(response.data.html);
                    }
                    if (response.data.badge) {
                        $('.smark-email-contacts-count-badge').text(response.data.badge);
                    }
                    hideContactAddSection();
                    $form[0].reset();
                    showNotification(response.data.message || '', 'success');
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
            }).always(function () {
                $button.prop('disabled', false).text(originalText);
            });
        });

        $(document).on('click', '[data-open-smark-contact-list]', function (event) {
            event.preventDefault();
            openContactListModal();
        });

        $(document).on('click', '[data-close-smark-contact-list]', function (event) {
            event.preventDefault();
            closeContactListModal();
        });

        $(document).on('click', '[data-open-smark-contact-tag]', function (event) {
            event.preventDefault();
            openContactTagModal();
        });

        $(document).on('click', '[data-close-smark-contact-tag]', function (event) {
            event.preventDefault();
            closeContactTagModal();
        });

        $(document).on('click', '[data-open-smark-contact-edit]', function (event) {
            event.preventDefault();
            openContactEditModal($(this).attr('data-open-smark-contact-edit') || '');
        });

        $(document).on('click', '[data-close-smark-contact-edit]', function (event) {
            event.preventDefault();
            closeContactEditModal();
        });

        $(document).on('submit', '#smarkEmailContactEditForm', function (event) {
            event.preventDefault();
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            var formData = new FormData(this);

            formData.set('action', 'smark_email_contact_save_modal');
            formData.set('search_query', getContactsSearchQuery());
            $button.prop('disabled', true).text((smarkSeoOptimization.strings || {}).saving || 'Saving...');

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (response) {
                if (response && response.success && response.data) {
                    $('#smarkEmailContactsList').html(response.data.listHtml || '');
                    closeContactEditModal();
                    showNotification(response.data.message || '', 'success');
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
            }).always(function () {
                $button.prop('disabled', false).text(originalText);
            });
        });

        $(document).on('submit', '.smark-email-contact-delete-form', function (event) {
            event.preventDefault();
            var form = this;
            var $form = $(form);
            var confirmText = $form.attr('data-delete-confirm') || '';

            if (confirmText && !window.confirm(confirmText)) {
                return;
            }

            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            var formData = new FormData(form);

            formData.set('action', 'smark_email_contact_delete_modal');
            formData.set('nonce', smarkSeoOptimization.contactsPageNonce || '');
            formData.set('search_query', getContactsSearchQuery());
            $button.prop('disabled', true).text((smarkSeoOptimization.strings || {}).saving || 'Saving...');

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (response) {
                if (response && response.success && response.data) {
                    $('#smarkEmailContactsList').html(response.data.listHtml || '');
                    showNotification(response.data.message || '', 'success');
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
            }).always(function () {
                $button.prop('disabled', false).text(originalText);
            });
        });

        $(document).on('click', '[data-open-smark-account-edit]', function (event) {
            event.preventDefault();
            openAccountEditModal($(this));
        });

        $(document).on('click', '[data-close-smark-account-edit]', function (event) {
            event.preventDefault();
            closeAccountEditModal();
        });

        $(document).on('change', '#smarkEmailAccountEditForm [data-smark-edit-provider]', function () {
            var $form = $('#smarkEmailAccountEditForm');
            var provider = ($(this).val() === 'gmail' || $(this).val() === 'outlook') ? $(this).val() : 'email';
            var $smtpHost = $form.find('[name="smtp_host"]');

            if (provider === 'gmail' || provider === 'outlook') {
                var defaultHost = provider === 'gmail' ? 'smtp.gmail.com' : 'smtp.office365.com';
                if (!$smtpHost.val() || $smtpHost.val() === 'smtp.gmail.com' || $smtpHost.val() === 'smtp.office365.com') {
                    $smtpHost.val(defaultHost);
                }
                $form.find('[name="smtp_port"]').val('587');
                $form.find('[name="encryption"]').val('tls');
            } else if ($smtpHost.val() === 'smtp.gmail.com' || $smtpHost.val() === 'smtp.office365.com') {
                $smtpHost.val('');
            }
        });

        var smarkContactsSearchTimer = null;

        function getContactsSearchQuery() {
            return $.trim(String($('[data-smark-contacts-search]').val() || ''));
        }

        function loadContactsPage(pageNumber) {
            var $container = $('#smarkEmailContactsList');
            var didReplace = false;
            pageNumber = parseInt(pageNumber, 10);

            if (!$container.length || isNaN(pageNumber) || pageNumber < 1) {
                return;
            }

            $container.addClass('smark-email-contacts-list-loading');
            $container.find('[data-smark-contacts-page]').each(function () {
                $(this).data('smarkWasDisabled', $(this).prop('disabled')).prop('disabled', true);
            });

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smark_email_contacts_page',
                    nonce: smarkSeoOptimization.contactsPageNonce || '',
                    page_number: pageNumber,
                    search_query: getContactsSearchQuery()
                }
            }).done(function (response) {
                if (response && response.success && response.data) {
                    didReplace = true;
                    $container.html(response.data.listHtml || '');
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
            }).always(function () {
                $container.removeClass('smark-email-contacts-list-loading');
                if (!didReplace) {
                    $container.find('[data-smark-contacts-page]').each(function () {
                        $(this).prop('disabled', !!$(this).data('smarkWasDisabled'));
                    });
                }
            });
        }

        $(document).on('input', '[data-smark-contacts-search]', function () {
            window.clearTimeout(smarkContactsSearchTimer);
            smarkContactsSearchTimer = window.setTimeout(function () {
                loadContactsPage(1);
            }, 250);
        });

        $(document).on('click', '[data-smark-contacts-page]', function (event) {
            event.preventDefault();

            if ($(this).prop('disabled')) {
                return;
            }

            loadContactsPage($(this).attr('data-smark-contacts-page'));
        });

        function loadCampaignActivityPage($container, pageNumber, eventFilter) {
            var campaignId = $container.attr('data-campaign-id') || '';
            var didReplace = false;
            eventFilter = eventFilter || $container.attr('data-event-filter') || 'all';
            pageNumber = parseInt(pageNumber, 10);

            if (!$container.length || !campaignId || isNaN(pageNumber) || pageNumber < 1) {
                return;
            }

            $container.addClass('smark-email-campaign-activity-loading');
            $container.find('[data-smark-campaign-activity-page]').each(function () {
                $(this).data('smarkWasDisabled', $(this).prop('disabled')).prop('disabled', true);
            });

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smark_email_campaign_activity_page',
                    nonce: smarkSeoOptimization.campaignMessageNonce || '',
                    campaign_id: campaignId,
                    page_number: pageNumber,
                    event_filter: eventFilter
                }
            }).done(function (response) {
                if (response && response.success && response.data) {
                    didReplace = true;
                    $container.replaceWith(response.data.activityHtml || '');
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
            }).always(function () {
                if (!didReplace) {
                    $container.removeClass('smark-email-campaign-activity-loading');
                    $container.find('[data-smark-campaign-activity-page]').each(function () {
                        $(this).prop('disabled', !!$(this).data('smarkWasDisabled'));
                    });
                }
            });
        }

        $(document).on('click', '[data-smark-campaign-activity-page]', function (event) {
            event.preventDefault();

            if ($(this).prop('disabled')) {
                return;
            }

            loadCampaignActivityPage($(this).closest('[data-smark-campaign-activity]'), $(this).attr('data-smark-campaign-activity-page'));
        });

        $(document).on('click', '[data-smark-activity-filter]', function (event) {
            event.preventDefault();
            var campaignId = $(this).attr('data-campaign-id') || '';
            var $scope = $(this).closest('.smark-email-import-modal, .smark-email-performance-card');
            var $container = $scope.find('[data-smark-campaign-activity][data-campaign-id="' + campaignId + '"]').first();

            if (!$container.length) {
                $container = $('[data-smark-campaign-activity][data-campaign-id="' + campaignId + '"]').first();
            }
            if (!$container.length) {
                return;
            }

            $scope.find('[data-smark-activity-filter]').removeClass('is-active');
            $(this).addClass('is-active');
            loadCampaignActivityPage($container, 1, $(this).attr('data-smark-activity-filter') || 'all');
        });

        function openFailureRetryModal() {
            var count = parseInt($('[data-open-smark-failure-retry]').attr('data-failure-count') || '0', 10);
            var $modal = $('#smarkEmailFailureRetryModal');

            if (isNaN(count)) {
                count = 0;
            }

            $modal.find('[data-smark-failure-retry-count]').attr('max', Math.max(1, count)).val(Math.min(100, Math.max(1, count)));
            $modal.find('[data-smark-failure-retry-progress], [data-smark-failure-retry-reports-wrap]').prop('hidden', true);
            $modal.find('[data-smark-failure-retry-bar]').css('width', '0%');
            $modal.find('[data-smark-failure-retry-percent]').text('0%');
            $modal.find('[data-smark-failure-retry-reports]').empty().append($('<li />').text($modal.find('[data-smark-failure-retry-reports]').attr('data-empty-text') || 'No email has been sent yet.'));
            $modal.addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('smark-email-modal-open');
        }

        function closeFailureRetryModal() {
            $('#smarkEmailFailureRetryModal').removeClass('is-open is-complete').attr('aria-hidden', 'true');
            $('body').removeClass('smark-email-modal-open');
        }

        function renderFailureRetryProgress(data) {
            var $modal = $('#smarkEmailFailureRetryModal');
            var percent = parseInt(data.percent, 10);
            var reports = $.isArray(data.recentReports) ? data.recentReports : [];

            if (isNaN(percent)) {
                percent = 0;
            }

            percent = Math.max(0, Math.min(100, percent));
            $modal.find('[data-smark-failure-retry-progress], [data-smark-failure-retry-reports-wrap]').prop('hidden', false);
            $modal.find('[data-smark-failure-retry-status]').text(data.statusText || '');
            $modal.find('[data-smark-failure-retry-percent]').text(percent + '%');
            $modal.find('[data-smark-failure-retry-bar]').css('width', percent + '%');
            $modal.find('[data-smark-failure-retry-count-text]').text(data.countText || '');

            var $reports = $modal.find('[data-smark-failure-retry-reports]').empty();
            if (!reports.length) {
                $('<li />').text($reports.attr('data-empty-text') || 'No email has been sent yet.').appendTo($reports);
            } else {
                reports.slice(0, 5).forEach(function (item) {
                    var status = item.status === 'failed' ? 'failed' : 'sent';
                    var $meta = $('<span class="smark-email-send-progress__recent-meta" />')
                        .append($('<span />').text(item.time || ''))
                        .append($('<span class="smark-email-status" />').addClass('smark-email-status--' + status).text(item.statusLabel || status));

                    $('<li />')
                        .append($('<strong />').text(item.email || ''))
                        .append($meta)
                        .appendTo($reports);
                });
            }

            $modal.toggleClass('is-complete', !!data.complete);
        }

        function refreshVisibleCampaignActivityTables() {
            $('[data-smark-campaign-activity]').each(function () {
                var $container = $(this);
                loadCampaignActivityPage($container, 1, $container.attr('data-event-filter') || 'all');
            });
        }

        function runFailureRetryBatch(sessionId, $button) {
            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smark_email_campaign_failure_retry_batch',
                    nonce: smarkSeoOptimization.campaignMessageNonce || '',
                    session_id: sessionId
                }
            }).done(function (response) {
                if (response && response.success && response.data) {
                    renderFailureRetryProgress(response.data);
                    if (response.data.overallHtml) {
                        $('#smarkEmailOverallStats').html(response.data.overallHtml);
                    }
                    refreshVisibleCampaignActivityTables();

                    if (response.data.complete) {
                        showNotification(response.data.message || '', 'success');
                        $button.prop('disabled', false).text($button.data('originalText') || $button.text());
                    } else {
                        window.setTimeout(function () {
                            runFailureRetryBatch(sessionId, $button);
                        }, 350);
                    }
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                    $button.prop('disabled', false).text($button.data('originalText') || $button.text());
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                $button.prop('disabled', false).text($button.data('originalText') || $button.text());
            });
        }

        $(document).on('click', '[data-open-smark-failure-retry]', function (event) {
            event.preventDefault();
            openFailureRetryModal();
        });

        $(document).on('click', '[data-close-smark-failure-retry]', function (event) {
            event.preventDefault();
            closeFailureRetryModal();
        });

        $(document).on('submit', '#smarkEmailFailureRetryForm', function (event) {
            event.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var formData = new FormData(this);

            formData.set('action', 'smark_email_campaign_failure_retry_start');
            formData.set('nonce', smarkSeoOptimization.campaignMessageNonce || '');
            $button.data('originalText', $button.text());
            $button.prop('disabled', true).text((smarkSeoOptimization.strings || {}).sending || 'Sending...');

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (response) {
                if (response && response.success && response.data) {
                    renderFailureRetryProgress(response.data);
                    runFailureRetryBatch(response.data.sessionId, $button);
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                    $button.prop('disabled', false).text($button.data('originalText') || $button.text());
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                $button.prop('disabled', false).text($button.data('originalText') || $button.text());
            });
        });

        $(document).on('submit', '#smarkEmailImportPreviewForm', function (event) {
            event.preventDefault();

            var form = this;
            var $form = $(form);
            var $button = $form.find('button[type="submit"]');
            var formData = new FormData(form);

            formData.set('action', 'smark_email_contacts_import_preview');
            formData.set('nonce', smarkSeoOptimization.contactsImportNonce || '');

            $button.data('originalText', $button.text());
            $button.prop('disabled', true).text((smarkSeoOptimization.strings || {}).readingFile || 'Reading file...');

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (response) {
                if (response && response.success && response.data) {
                    $('#smarkEmailImportMappingContainer').html(response.data.mappingHtml || '');
                    showNotification(response.data.message || '', 'success');
                    openImportModal();
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
            }).always(function () {
                $button.prop('disabled', false).text($button.data('originalText') || $button.attr('data-default-text') || $button.text());
            });
        });

        $(document).on('submit', '#smarkEmailImportForm', function (event) {
            event.preventDefault();

            var form = this;
            var $form = $(form);
            var $button = $form.find('button[type="submit"]');
            var formData = new FormData(form);

            formData.set('action', 'smark_email_contacts_import');
            formData.set('nonce', smarkSeoOptimization.contactsImportNonce || '');

            $button.data('originalText', $button.text());
            $button.prop('disabled', true).text((smarkSeoOptimization.strings || {}).importing || 'Importing contacts...');

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (response) {
                if (response && response.success && response.data) {
                    $('#smarkEmailContactsList').html(response.data.listHtml || '');
                    $('#smarkEmailImportMappingContainer').empty();
                    $('#smarkEmailImportPreviewForm')[0].reset();
                    showNotification(response.data.message || '', 'success');
                    closeImportModal();
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
            }).always(function () {
                $button.prop('disabled', false).text($button.data('originalText') || $button.attr('data-default-text') || $button.text());
            });
        });

        function openSendProgressModal() {
            $('#smarkEmailSendProgressModal').addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('smark-email-modal-open');
        }

        function closeSendProgressModal() {
            $('#smarkEmailSendProgressModal').removeClass('is-open is-complete').attr('aria-hidden', 'true');
            $('body').removeClass('smark-email-modal-open');
        }

        function renderSendProgress(data) {
            var $modal = $('#smarkEmailSendProgressModal');
            var percent = parseInt(data.percent, 10);
            var recent = $.isArray(data.recentReports) ? data.recentReports : [];

            if (isNaN(percent)) {
                percent = 0;
            }

            percent = Math.max(0, Math.min(100, percent));
            $modal.find('[data-smark-send-progress-status]').text(data.statusText || '');
            $modal.find('[data-smark-send-progress-percent]').text(percent + '%');
            $modal.find('[data-smark-send-progress-bar]').css('width', percent + '%');
            $modal.find('[data-smark-send-progress-count]').text(data.countText || '');

            var $recent = $modal.find('[data-smark-send-progress-recent]').empty();
            if (!recent.length) {
                $('<li />').text($recent.attr('data-empty-text') || 'Waiting for the first sent email...').appendTo($recent);
            } else {
                recent.slice(0, 5).forEach(function (item) {
                    var status = item.status === 'failed' ? 'failed' : 'sent';
                    var $meta = $('<span class="smark-email-send-progress__recent-meta" />')
                        .append($('<span />').text(item.time || ''))
                        .append($('<span class="smark-email-status" />').addClass('smark-email-status--' + status).text(item.statusLabel || status));

                    $('<li />')
                        .append($('<strong />').text(item.email || ''))
                        .append($meta)
                        .appendTo($recent);
                });
            }

            $modal.find('[data-close-smark-send-progress]').prop('hidden', !data.complete);
            $modal.toggleClass('is-complete', !!data.complete);
        }

        function runCampaignSendBatch(sessionId, $button) {
            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smark_email_campaign_message_send_batch',
                    nonce: smarkSeoOptimization.campaignMessageNonce || '',
                    session_id: sessionId
                }
            }).done(function (response) {
                if (response && response.success && response.data) {
                    renderSendProgress(response.data);
                    if (response.data.complete) {
                        $('#smarkEmailCampaignMessagesList').html(response.data.listHtml || '');
                        showNotification(response.data.message || '', 'success');
                        $button.prop('disabled', false).text($button.data('originalText') || $button.text());
                    } else {
                        window.setTimeout(function () {
                            runCampaignSendBatch(sessionId, $button);
                        }, 350);
                    }
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                    $('#smarkEmailSendProgressModal').find('[data-close-smark-send-progress]').prop('hidden', false);
                    $button.prop('disabled', false).text($button.data('originalText') || $button.text());
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                $('#smarkEmailSendProgressModal').find('[data-close-smark-send-progress]').prop('hidden', false);
                $button.prop('disabled', false).text($button.data('originalText') || $button.text());
            });
        }

        function startCampaignProgressSend(formData, startAction, $button) {
            formData.set('action', startAction);
            formData.set('nonce', smarkSeoOptimization.campaignMessageNonce || '');

            $button.data('originalText', $button.text());
            $button.prop('disabled', true).text((smarkSeoOptimization.strings || {}).sending || 'Sending...');
            openSendProgressModal();

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (response) {
                if (response && response.success && response.data) {
                    renderSendProgress(response.data);
                    runCampaignSendBatch(response.data.sessionId, $button);
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                    $('#smarkEmailSendProgressModal').find('[data-close-smark-send-progress]').prop('hidden', false);
                    $button.prop('disabled', false).text($button.data('originalText') || $button.text());
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                $('#smarkEmailSendProgressModal').find('[data-close-smark-send-progress]').prop('hidden', false);
                $button.prop('disabled', false).text($button.data('originalText') || $button.text());
            });
        }

        $(document).on('submit', '.smark-email-campaign-send-form', function (event) {
            event.preventDefault();

            startCampaignProgressSend(new FormData(this), 'smark_email_campaign_message_send_start', $(this).find('button[type="submit"]'));
        });

        $(document).on('submit', '#smarkEmailCampaignMessageForm', function (event) {
            if (window.tinyMCE && typeof window.tinyMCE.triggerSave === 'function') {
                window.tinyMCE.triggerSave();
            }

            var submitter = event.originalEvent && event.originalEvent.submitter ? event.originalEvent.submitter : null;
            var action = submitter ? $(submitter).val() : '';

            if (action !== 'send_now') {
                return;
            }

            event.preventDefault();
            startCampaignProgressSend(new FormData(this), 'smark_email_campaign_message_quick_send_start', $(submitter));
        });

        $(document).on('click', '[data-close-smark-send-progress]', function (event) {
            event.preventDefault();
            closeSendProgressModal();
        });

        $(document).on('click', '[data-smark-open-test-send]', function (event) {
            event.preventDefault();
            $('[data-smark-test-send-box]').prop('hidden', false);
        });

        $(document).on('click', '[data-smark-cancel-test-send]', function (event) {
            event.preventDefault();
            $('[data-smark-test-send-box]').prop('hidden', true);
        });

        $(document).on('click', '[data-smark-confirm-test-send]', function (event) {
            event.preventDefault();

            if (window.tinyMCE && typeof window.tinyMCE.triggerSave === 'function') {
                window.tinyMCE.triggerSave();
            }

            var form = document.getElementById('smarkEmailCampaignMessageForm');
            if (!form) {
                return;
            }

            var $button = $(this);
            var formData = new FormData(form);
            var testEmail = $.trim(String($('[data-smark-test-send-email]').val() || ''));

            if (!testEmail) {
                showNotification((smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                $('[data-smark-test-send-email]').trigger('focus');
                return;
            }

            formData.set('action', 'smark_email_campaign_message_test_send');
            formData.set('nonce', smarkSeoOptimization.campaignMessageNonce || '');
            formData.set('test_email', testEmail);

            $button.data('originalText', $button.text());
            $button.prop('disabled', true).text((smarkSeoOptimization.strings || {}).sending || 'Sending...');

            $.ajax({
                url: smarkSeoOptimization.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function (response) {
                if (response && response.success && response.data) {
                    $('[data-smark-test-send-box]').prop('hidden', true);
                    showNotification(response.data.message || '', 'success');
                } else {
                    showNotification((response && response.data && response.data.message) || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
                }
            }).fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '';
                showNotification(message || (smarkSeoOptimization.strings || {}).error || 'Error', 'error');
            }).always(function () {
                $button.prop('disabled', false).text($button.data('originalText') || $button.text());
            });
        });

        $(document).on('click', '[data-close-smark-import]', function (event) {
            event.preventDefault();
            closeImportModal();
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                closeImportModal();
                closeAccountEditModal();
                closeContactListModal();
                closeContactTagModal();
                closeContactEditModal();
                closeAudiencePicker();
                closeCampaignEditModal();
                $('.smark-email-campaign-performance-section').each(function () {
                    hideCampaignWorkflowPanel($(this));
                });
                closeSenderPickers();
                $('#smarkEmailFailureDetailModal, #smarkEmailFailureRetryModal').removeClass('is-open').attr('aria-hidden', 'true');
                $('body').removeClass('smark-email-modal-open');
            }
        });
    });
})(jQuery);
JS;
    }

    private function redirect_to_accounts($message) {
        wp_safe_redirect(add_query_arg(
            array(
                'page' => 'smark-email-accounts',
                'smark_message' => sanitize_key($message),
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    private function redirect_to_contacts($message, $extra_args = array()) {
        wp_safe_redirect(add_query_arg(
            array_merge(
                $extra_args,
                array(
                    'page' => 'smark-dashboard-page',
                    'smark_email_view' => 'contacts',
                    'smark_message' => sanitize_key($message),
                )
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    private function redirect_to_campaign_messages($message, $extra_args = array()) {
        wp_safe_redirect(add_query_arg(
            array_merge(
                $extra_args,
                array(
                    'page' => 'smark-email-campaign-message',
                    'smark_message' => sanitize_key($message),
                )
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    private function render_standard_header($strings, $current_lang, $rtl_class, $include_parent_breadcrumb) {
        ?>
        <div class="smark-page-header">
            <h1><?php echo esc_html($strings['page_title']); ?></h1>
            <p class="description"><?php echo esc_html($strings['page_subtitle']); ?></p>
        </div>

        <div class="smark-breadcrumb">
            <div class="breadcrumb-left">
                <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html($strings['breadcrumb_dashboard']); ?></a>
                <span class="separator"><?php echo $rtl_class ? '‹' : '›'; ?></span>
                <?php if ($include_parent_breadcrumb) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html($strings['breadcrumb_parent']); ?></a>
                    <span class="separator"><?php echo $rtl_class ? '‹' : '›'; ?></span>
                <?php endif; ?>
                <span class="current"><?php echo esc_html($strings['breadcrumb_current']); ?></span>
            </div>
            <div class="breadcrumb-right">
                <div class="language-selector">
                    <span class="dashicons dashicons-translation"></span>
                    <select id="smark_language_select" class="language-dropdown">
                        <option value="en" <?php selected($current_lang, 'en'); ?>>English</option>
                        <option value="fa" <?php selected($current_lang, 'fa'); ?>>فارسی</option>
                    </select>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_version_footer($current_lang) {
        ?>
        <div class="smark-version-footer">
            <div class="version-info">
                <span class="version-label"><?php echo esc_html($current_lang === 'fa' ? 'پلاگین اسمارک' : 'SMark Plugin'); ?></span>
                <span class="version-separator">•</span>
                <span class="version-number">v<?php echo esc_html(SMARK_VERSION); ?></span>
            </div>
        </div>
        <?php
    }

    private function get_strings($lang) {
        if ($lang === 'fa') {
            return array(
                'page_title'           => 'ایمیل مارکتینگ',
                'page_subtitle'        => 'برنامه‌ریزی، ساخت و پایش کمپین‌های ایمیلی پروژه.',
                'breadcrumb_dashboard' => 'داشبورد',
                'breadcrumb_current'   => 'ایمیل مارکتینگ',
                'section_title'        => 'برنامه کمپین‌های ایمیلی',
                'section_description'  => 'مخاطب، پیام، زمان‌بندی و عملکرد کمپین‌ها را در یک مسیر منظم مدیریت کنید.',
            );
        }

        return array(
            'page_title'           => 'Email Marketing',
            'page_subtitle'        => 'Plan, build, and monitor email campaigns for your project.',
            'breadcrumb_dashboard' => 'Dashboard',
            'breadcrumb_current'   => 'Email Marketing',
            'section_title'        => 'Email Campaign Workflow',
            'section_description'  => 'Organize audience, messaging, schedule, and campaign performance in one guided workspace.',
        );
    }

    private function get_email_account_strings($lang) {
        if ($lang === 'fa') {
            return array(
                'page_title'                    => 'حساب‌های ایمیل',
                'page_subtitle'                 => 'حساب‌های ارسال ایمیل را اضافه کنید و سقف ارسال روزانه هر حساب را مشخص کنید.',
                'breadcrumb_dashboard'          => 'داشبورد',
                'breadcrumb_parent'             => 'ایمیل مارکتینگ',
                'breadcrumb_current'            => 'حساب‌های ایمیل',
                'form_title'                    => 'افزودن حساب ایمیل',
                'form_title_email'              => 'افزودن حساب ایمیل',
                'form_title_gmail'              => 'افزودن حساب جیمیل',
                'form_title_outlook'            => 'افزودن حساب اوتلوک',
                'edit_form_title_email'         => 'ویرایش حساب ایمیل',
                'edit_form_title_gmail'         => 'ویرایش حساب جیمیل',
                'edit_modal_title'              => 'ویرایش حساب ایمیل',
                'edit_modal_description'        => 'فیلدهای موردنظر را تغییر دهید و حساب را به‌روزرسانی کنید.',
                'form_description'              => 'حساب ارسال ایمیل را با مشخصات SMTP ثبت کنید.',
                'form_description_email'        => 'برای ارسال از ایمیل معمولی، مشخصات SMTP، رمز عبور و سقف ارسال روزانه را وارد کنید.',
                'form_description_gmail'        => 'برای ارسال از جیمیل، آدرس ایمیل، نام فرستنده، App Password و سقف ارسال روزانه لازم است.',
                'form_description_outlook'      => 'برای ارسال از اوتلوک، آدرس ایمیل، نام فرستنده، رمز یا App Password و سقف ارسال روزانه لازم است.',
                'edit_form_description_email'   => 'مشخصات SMTP، فرستنده و سقف ارسال این حساب را به‌روزرسانی کنید.',
                'edit_form_description_gmail'   => 'مشخصات جیمیل، فرستنده، App Password و سقف ارسال این حساب را به‌روزرسانی کنید.',
                'provider_label'                => 'نوع حساب',
                'provider_email'                => 'ایمیل',
                'provider_gmail'                => 'جیمیل',
                'provider_outlook'              => 'اوتلوک',
                'field_label'                   => 'عنوان حساب',
                'field_label_placeholder'       => 'مثلا ایمیل فروش',
                'field_label_placeholder_email' => 'مثلا ایمیل فروش',
                'field_label_placeholder_gmail' => 'مثلا جیمیل فروش',
                'field_label_placeholder_outlook'=> 'مثلا اوتلوک فروش',
                'field_sender_name'             => 'نام فرستنده',
                'field_sender_name_placeholder' => 'مثلا تیم اسمارک',
                'field_email'                   => 'آدرس ایمیل',
                'field_email_email'             => 'آدرس ایمیل',
                'field_email_gmail'             => 'آدرس جیمیل',
                'field_email_outlook'           => 'آدرس اوتلوک',
                'field_app_password'            => 'رمز SMTP',
                'field_password_email'          => 'رمز SMTP / ایمیل',
                'field_password_gmail'          => 'App Password جیمیل',
                'field_password_outlook'        => 'رمز اوتلوک',
                'gmail_app_password_link'       => 'دریافت App Password',
                'gmail_app_password_tooltip_label'=> 'راهنمای دریافت App Password جیمیل',
                'gmail_app_password_tooltip_title'=> 'راهنمای سریع',
                'gmail_app_password_tooltip_step_1'=> 'ابتدا مطمئن شوید ورود دو مرحله‌ای حساب گوگل فعال است.',
                'gmail_app_password_tooltip_step_2'=> 'در صفحه App passwords، نام برنامه را SMark وارد کنید.',
                'gmail_app_password_tooltip_step_3'=> 'کدی را که گوگل می‌دهد در همین فیلد SMark قرار دهید.',
                'outlook_app_password_link'     => 'دریافت App Password',
                'outlook_app_password_tooltip_label'=> 'راهنمای دریافت App Password اوتلوک',
                'outlook_app_password_tooltip_title'=> 'راهنمای سریع',
                'outlook_app_password_tooltip_step_1'=> 'ابتدا وارد بخش Security حساب مایکروسافت شوید و Advanced security options را باز کنید.',
                'outlook_app_password_tooltip_step_2'=> 'اگر ورود دو مرحله‌ای فعال است، در بخش App passwords گزینه ایجاد رمز جدید را انتخاب کنید.',
                'outlook_app_password_tooltip_step_3'=> 'رمزی را که مایکروسافت می‌دهد در همین فیلد SMark قرار دهید.',
                'field_app_password_placeholder'=> 'رمز SMTP یا رمز برنامه',
                'field_password_placeholder_email'=> 'رمز SMTP یا رمز ایمیل',
                'field_password_placeholder_gmail'=> 'رمز برنامه ۱۶ کاراکتری گوگل',
                'field_password_placeholder_outlook'=> 'رمز یا App Password حساب مایکروسافت',
                'field_password_placeholder_keep'=> 'برای حفظ رمز فعلی خالی بگذارید',
                'field_password_keep_help'      => 'اگر رمز جدید وارد نکنید، رمز ذخیره‌شده قبلی حفظ می‌شود.',
                'field_daily_limit'             => 'تعداد ارسال روزانه',
                'field_smtp_host'               => 'SMTP Host',
                'field_smtp_port'               => 'SMTP Port',
                'field_encryption'              => 'نوع رمزنگاری',
                'encryption_none'               => 'بدون رمزنگاری',
                'email_help'                    => 'برای ایمیل معمولی، اطلاعات SMTP را از هاست یا سرویس‌دهنده ایمیل خود وارد کنید.',
                'gmail_help'                    => 'برای جیمیل باید تایید دو مرحله‌ای فعال باشد و از Google Account > Security > App passwords رمز برنامه بسازید. پسورد اصلی جیمیل را وارد نکنید.',
                'outlook_help'                  => 'برای اوتلوک تنظیمات SMTP به‌صورت خودکار روی smtp.office365.com، پورت 587 و TLS قرار می‌گیرد. اگر ورود دو مرحله‌ای فعال است از App Password مایکروسافت استفاده کنید.',
                'save_button'                   => 'افزودن حساب',
                'update_button'                 => 'ذخیره تغییرات',
                'cancel_edit_button'            => 'لغو ویرایش',
                'close_modal'                   => 'بستن پنجره',
                'list_title'                    => 'حساب‌های ثبت‌شده',
                'list_description'              => 'این حساب‌ها بعدا برای انتخاب فرستنده کمپین و کنترل سقف ارسال روزانه استفاده می‌شوند.',
                'empty_state'                   => 'هنوز حساب ایمیلی ثبت نشده است.',
                'column_label'                  => 'حساب',
                'column_email'                  => 'ایمیل',
                'column_smtp'                   => 'SMTP',
                'column_daily_limit'            => 'سقف روزانه',
                'column_status'                 => 'وضعیت',
                'column_actions'                => 'عملیات',
                'daily_sent_tooltip'            => 'تعداد ایمیل‌های ارسال‌شده امروز با این حساب',
                'daily_limit_tooltip'           => 'ظرفیت ارسال روزانه این حساب',
                'status_active'                 => 'فعال',
                'edit_button'                   => 'ویرایش',
                'delete_button'                 => 'حذف',
                'delete_confirm'                => 'این حساب ایمیل حذف شود؟',
                'notice_saved'                  => 'حساب ایمیل ذخیره شد.',
                'notice_deleted'                => 'حساب ایمیل حذف شد.',
                'notice_error'                  => 'اطلاعات حساب کامل یا معتبر نیست.',
                'notice_sent'                   => '%s ایمیل ارسال شد.',
                'notice_send_error'             => 'ارسال انجام نشد. مخاطبان، موضوع، بدنه ایمیل یا تنظیمات ارسال را بررسی کنید.',
            );
        }

        return array(
            'page_title'                    => 'Email Accounts',
            'page_subtitle'                 => 'Add sending accounts and define the daily send limit for each account.',
            'breadcrumb_dashboard'          => 'Dashboard',
            'breadcrumb_parent'             => 'Email Marketing',
            'breadcrumb_current'            => 'Email Accounts',
            'form_title'                    => 'Add Email Account',
            'form_title_email'              => 'Add Email Account',
            'form_title_gmail'              => 'Add Gmail Account',
            'form_title_outlook'            => 'Add Outlook Account',
            'edit_form_title_email'         => 'Edit Email Account',
            'edit_form_title_gmail'         => 'Edit Gmail Account',
            'edit_modal_title'              => 'Edit Email Account',
            'edit_modal_description'        => 'Update the fields you need and save the account changes.',
            'form_description'              => 'Add a sending account with SMTP details.',
            'form_description_email'        => 'For regular email, enter the SMTP details, password, and daily send limit.',
            'form_description_gmail'        => 'Gmail sending requires the email address, sender name, app password, and daily send limit.',
            'form_description_outlook'      => 'Outlook sending requires the email address, sender name, password or app password, and daily send limit.',
            'edit_form_description_email'   => 'Update this account SMTP details, sender identity, and daily send limit.',
            'edit_form_description_gmail'   => 'Update this Gmail account, sender identity, app password, and daily send limit.',
            'provider_label'                => 'Account Type',
            'provider_email'                => 'Email',
            'provider_gmail'                => 'Gmail',
            'provider_outlook'              => 'Outlook',
            'field_label'                   => 'Account Label',
            'field_label_placeholder'       => 'Example: Sales Email',
            'field_label_placeholder_email' => 'Example: Sales Email',
            'field_label_placeholder_gmail' => 'Example: Sales Gmail',
            'field_label_placeholder_outlook'=> 'Example: Sales Outlook',
            'field_sender_name'             => 'Sender Name',
            'field_sender_name_placeholder' => 'Example: SMark Team',
            'field_email'                   => 'Email Address',
            'field_email_email'             => 'Email Address',
            'field_email_gmail'             => 'Gmail Address',
            'field_email_outlook'           => 'Outlook Address',
            'field_app_password'            => 'SMTP Password',
            'field_password_email'          => 'SMTP / Email Password',
            'field_password_gmail'          => 'Gmail App Password',
            'field_password_outlook'        => 'Outlook Password',
            'gmail_app_password_link'       => 'Get app password',
            'gmail_app_password_tooltip_label'=> 'Gmail app password help',
            'gmail_app_password_tooltip_title'=> 'Quick guide',
            'gmail_app_password_tooltip_step_1'=> 'Make sure 2-Step Verification is enabled on your Google account.',
            'gmail_app_password_tooltip_step_2'=> 'On the App passwords page, use SMark as the app name.',
            'gmail_app_password_tooltip_step_3'=> 'Paste the password Google gives you into this SMark field.',
            'outlook_app_password_link'     => 'Get App Password',
            'outlook_app_password_tooltip_label'=> 'Outlook app password help',
            'outlook_app_password_tooltip_title'=> 'Quick guide',
            'outlook_app_password_tooltip_step_1'=> 'Open your Microsoft account Security page and go to Advanced security options.',
            'outlook_app_password_tooltip_step_2'=> 'If two-step verification is enabled, use the App passwords section to create a new password.',
            'outlook_app_password_tooltip_step_3'=> 'Paste the password Microsoft gives you into this SMark field.',
            'field_app_password_placeholder'=> 'SMTP password or app password',
            'field_password_placeholder_email'=> 'SMTP or email password',
            'field_password_placeholder_gmail'=> '16-character Google app password',
            'field_password_placeholder_outlook'=> 'Microsoft account password or app password',
            'field_password_placeholder_keep'=> 'Leave blank to keep the current password',
            'field_password_keep_help'      => 'If you do not enter a new password, the saved password stays unchanged.',
            'field_daily_limit'             => 'Daily Send Limit',
            'field_smtp_host'               => 'SMTP Host',
            'field_smtp_port'               => 'SMTP Port',
            'field_encryption'              => 'Encryption',
            'encryption_none'               => 'None',
            'email_help'                    => 'For regular email, use the SMTP details provided by your host or email service.',
            'gmail_help'                    => 'For Gmail, enable 2-Step Verification and create an app password from Google Account > Security > App passwords. Do not enter the regular Gmail password.',
            'outlook_help'                  => 'For Outlook, SMark automatically uses smtp.office365.com, port 587, and TLS. If two-step verification is enabled, use a Microsoft app password.',
            'save_button'                   => 'Add Account',
            'update_button'                 => 'Save Changes',
            'cancel_edit_button'            => 'Cancel Edit',
            'close_modal'                   => 'Close modal',
            'list_title'                    => 'Saved Accounts',
            'list_description'              => 'These accounts can be used later as campaign senders with per-account daily limits.',
            'empty_state'                   => 'No email accounts have been added yet.',
            'column_label'                  => 'Account',
            'column_email'                  => 'Email',
            'column_smtp'                   => 'SMTP',
            'column_daily_limit'            => 'Daily Limit',
            'column_status'                 => 'Status',
            'column_actions'                => 'Actions',
            'daily_sent_tooltip'            => 'Emails sent today by this account',
            'daily_limit_tooltip'           => 'Daily sending capacity for this account',
            'status_active'                 => 'Active',
            'edit_button'                   => 'Edit',
            'delete_button'                 => 'Delete',
            'delete_confirm'                => 'Delete this email account?',
            'notice_saved'                  => 'Email account saved.',
            'notice_deleted'                => 'Email account deleted.',
            'notice_error'                  => 'The account information is incomplete or invalid.',
            'notice_sent'                   => '%s emails sent.',
            'notice_send_error'             => 'Send failed. Check recipients, subject, body, or sending settings.',
        );
    }

    private function get_contact_strings($lang) {
        if ($lang === 'fa') {
            return array(
                'page_title'                    => 'مخاطبین',
                'page_subtitle'                 => 'مخاطبان ایمیلی را تعریف کنید و برای کمپین‌های بعدی در لیست‌ها و برچسب‌های مشخص نگه دارید.',
                'breadcrumb_dashboard'          => 'داشبورد',
                'breadcrumb_parent'             => 'ایمیل مارکتینگ',
                'breadcrumb_current'            => 'مخاطبین',
                'form_title'                    => 'افزودن مخاطب',
                'form_description'              => 'مشخصات مخاطب، ایمیل و لیست هدف را ثبت کنید تا بعدا در ارسال کمپین استفاده شود.',
                'field_first_name'              => 'نام',
                'field_first_name_placeholder'  => 'مثلا سعید',
                'field_last_name'               => 'نام خانوادگی',
                'field_last_name_placeholder'   => 'مثلا حسنی',
                'field_email'                   => 'ایمیل مخاطب',
                'field_phone'                   => 'شماره تماس',
                'field_phone_placeholder'       => 'اختیاری',
                'field_segment'                 => 'لیست',
                'field_segment_placeholder'     => 'مثلا لیدهای دوره سئو',
                'field_list_empty'              => 'بدون لیست',
                'field_tags'                    => 'برچسب‌ها',
                'field_tags_help'               => 'برای انتخاب چند برچسب از Ctrl/Cmd استفاده کنید. برچسب‌های سیستمی خودکار اعمال می‌شوند.',
                'field_source'                  => 'منبع جذب',
                'field_source_placeholder'      => 'مثلا فرم سایت، وبینار یا خرید',
                'field_status'                  => 'وضعیت',
                'field_notes'                   => 'یادداشت',
                'field_notes_placeholder'       => 'نیاز، علاقه‌مندی یا نکته مهم برای کمپین...',
                'contact_help'                  => 'فقط ایمیل برای هر مخاطب ضروری است. لیست، برچسب، منبع و یادداشت اختیاری هستند و هر ایمیل فقط یک‌بار ثبت می‌شود.',
                'save_button'                   => 'افزودن مخاطب',
                'update_button'                 => 'ذخیره تغییرات',
                'edit_modal_title'              => 'ویرایش مخاطب',
                'edit_modal_description'        => 'مشخصات مخاطب، لیست، برچسب‌ها و یادداشت را ویرایش کنید.',
                'edit_modal_close'              => 'بستن پنجره ویرایش',
                'edit_modal_cancel'             => 'انصراف',
                'lists_title'                   => 'لیست‌ها',
                'lists_description'             => 'لیست‌های مخاطبان را بسازید و هر تعداد مخاطب را داخل لیست مورد نظر قرار دهید.',
                'add_list_button'               => 'افزودن لیست جدید',
                'create_list_button'            => 'ایجاد لیست',
                'list_modal_title'              => 'ایجاد لیست جدید',
                'list_modal_description'        => 'نام لیست را وارد کنید؛ بعد از ایجاد می‌توانید مخاطبان را به آن اختصاص دهید.',
                'list_name_label'               => 'نام لیست',
                'list_name_placeholder'         => 'مثلا مشتریان دوره سئو',
                'lists_empty_state'             => 'هنوز لیستی ساخته نشده است.',
                'delete_list_confirm'           => 'این لیست حذف شود؟',
                'system_list_label'             => 'سیستمی',
                'system_list_all'               => 'همه',
                'system_list_all_help'          => 'این لیست همیشه شامل همه مخاطبین است: %s مخاطب.',
                'tags_title'                    => 'برچسب‌ها',
                'tags_description'              => 'برچسب‌های دلخواه بسازید و آن‌ها را به مخاطبین اختصاص دهید. برچسب‌های سیستمی فقط قابل استفاده هستند و حذف نمی‌شوند.',
                'add_tag_button'                => 'افزودن برچسب جدید',
                'create_tag_button'             => 'ایجاد برچسب',
                'tag_modal_title'               => 'ایجاد برچسب جدید',
                'tag_modal_description'         => 'نام برچسب را وارد کنید؛ سپس مخاطبان مرتبط را انتخاب کنید.',
                'tag_name_label'                => 'نام برچسب',
                'tag_name_placeholder'          => 'مثلا علاقه‌مند به مشاوره',
                'tags_empty_state'              => 'هنوز برچسب سفارشی ساخته نشده است.',
                'delete_tag_confirm'            => 'این برچسب حذف شود؟',
                'system_tags_help'              => 'برچسب‌های سیستمی توسط اسمارک ساخته می‌شوند و قابل حذف نیستند.',
                'system_tag_label'              => 'سیستمی',
                'system_tag_today_sent'         => 'امروز ایمیل دریافت کرده',
                'system_tag_today_sent_short'   => 'امروز',
                'system_tag_today_sent_help'    => 'این برچسب روزانه است و امروز برای %s مخاطب فعال شده است.',
                'assignment_contacts_label'     => 'مخاطبین این مورد',
                'assignment_contacts_help'      => 'برای انتخاب چند مخاطب از Ctrl/Cmd استفاده کنید.',
                'save_assignments_button'       => 'ذخیره انتخاب‌ها',
                'assigned_contacts_count'       => '%s مخاطب',
                'list_title'                    => 'مخاطبان',
                'list_description'              => 'این لیست بعدا برای انتخاب مخاطبان هدف کمپین استفاده می‌شود.',
                'contacts_count_badge'          => '%s مخاطب',
                'contacts_search_aria'          => 'جست‌وجوی مخاطبین',
                'contacts_search_placeholder'   => 'جست‌وجوی مخاطب بر اساس نام، ایمیل، شماره، لیست، برچسب یا وضعیت...',
                'contacts_search_empty'         => 'هیچ مخاطبی با این جست‌وجو پیدا نشد.',
                'empty_state'                   => 'هنوز مخاطبی ثبت نشده است.',
                'column_contact'                => 'مخاطب',
                'column_email'                  => 'ایمیل',
                'column_segment'                => 'سگمنت',
                'column_lists'                  => 'لیست‌ها',
                'column_tags'                   => 'برچسب‌ها',
                'column_source'                 => 'منبع',
                'column_status'                 => 'وضعیت',
                'column_actions'                => 'عملیات',
                'status_subscribed'             => 'عضو لیست',
                'status_lead'                   => 'لید',
                'status_unsubscribed'           => 'لغو عضویت',
                'unnamed_contact'               => 'مخاطب بدون نام',
                'delete_button'                 => 'حذف',
                'edit_button'                   => 'ویرایش',
                'delete_confirm'                => 'این مخاطب حذف شود؟',
                'pagination_label'              => 'صفحه‌بندی مخاطبان',
                'pagination_summary'            => 'نمایش %1$s تا %2$s از %3$s مخاطب',
                'pagination_previous'           => 'قبلی',
                'pagination_next'               => 'بعدی',
                'pagination_ellipsis'           => '...',
                'notice_saved'                  => 'مخاطب ذخیره شد.',
                'notice_deleted'                => 'مخاطب حذف شد.',
                'notice_list_saved'             => 'لیست ذخیره شد.',
                'notice_list_deleted'           => 'لیست حذف شد.',
                'notice_tag_saved'              => 'برچسب ذخیره شد.',
                'notice_tag_deleted'            => 'برچسب حذف شد.',
                'notice_error'                  => 'اطلاعات مخاطب کامل یا معتبر نیست، یا این ایمیل قبلا ثبت شده است.',
                'notice_imported'               => '%s مخاطب با موفقیت وارد شد.',
                'notice_no_import'              => 'هیچ مخاطبی وارد نشد. مپینگ ایمیل را بررسی کنید یا مطمئن شوید ایمیل‌ها تکراری نیستند.',
                'bulk_button'                   => 'افزودن گروهی',
                'bulk_title'                    => 'افزودن گروهی مخاطبان',
                'bulk_description'              => 'فایل CSV یا Excel را بارگذاری کنید، ستون‌ها را به فیلدهای اسمارک مپ کنید و مخاطبان را یک‌جا وارد کنید.',
                'bulk_close'                    => 'بستن پنجره',
                'bulk_file_label'               => 'فایل مخاطبان',
                'bulk_preview_button'           => 'خواندن فایل',
                'bulk_help'                     => 'ردیف اول فایل به عنوان عنوان ستون‌ها استفاده می‌شود. فرمت‌های پشتیبانی‌شده: CSV و XLSX.',
                'bulk_mapping_title'            => 'مپ کردن ستون‌ها',
                'bulk_mapping_description'      => '%d ردیف برای واردسازی آماده است. فقط ستون ایمیل الزامی است و بقیه فیلدها اختیاری هستند.',
                'bulk_plugin_field'             => 'فیلد اسمارک',
                'bulk_file_column'              => 'ستون فایل',
                'bulk_required'                 => 'ضروری',
                'bulk_ignore_column'            => 'متصل نکن',
                'bulk_default_segment'          => 'لیست پیش‌فرض اختیاری',
                'bulk_import_button'            => 'وارد کردن مخاطبان',
            );
        }

        return array(
            'page_title'                    => 'Contacts',
            'page_subtitle'                 => 'Create email contacts and organize them into campaign-ready lists.',
            'breadcrumb_dashboard'          => 'Dashboard',
            'breadcrumb_parent'             => 'Email Marketing',
            'breadcrumb_current'            => 'Contacts',
            'form_title'                    => 'Add Contact',
            'form_description'              => 'Save the contact details, email address, and target list for future campaigns.',
            'field_first_name'              => 'First Name',
            'field_first_name_placeholder'  => 'Example: Alex',
            'field_last_name'               => 'Last Name',
            'field_last_name_placeholder'   => 'Example: Hasani',
            'field_email'                   => 'Contact Email',
            'field_phone'                   => 'Phone',
            'field_phone_placeholder'       => 'Optional',
            'field_segment'                 => 'List',
            'field_segment_placeholder'     => 'Example: SEO course leads',
            'field_list_empty'              => 'No list',
            'field_tags'                    => 'Tags',
            'field_tags_help'               => 'Use Ctrl/Cmd to select multiple tags. System tags are applied automatically.',
            'field_source'                  => 'Acquisition Source',
            'field_source_placeholder'      => 'Example: Website form, webinar, purchase',
            'field_status'                  => 'Status',
            'field_notes'                   => 'Notes',
            'field_notes_placeholder'       => 'Need, interest, or campaign context...',
            'contact_help'                  => 'Only email is required. List, tags, source, and notes are optional, and each email address can only appear once.',
            'save_button'                   => 'Add Contact',
            'update_button'                 => 'Save Changes',
            'edit_modal_title'              => 'Edit Contact',
            'edit_modal_description'        => 'Update the contact details, list, tags, and notes.',
            'edit_modal_close'              => 'Close edit modal',
            'edit_modal_cancel'             => 'Cancel',
            'lists_title'                   => 'Lists',
            'lists_description'             => 'Create contact lists and place as many contacts as needed inside each list.',
            'add_list_button'               => 'Add New List',
            'create_list_button'            => 'Create List',
            'list_modal_title'              => 'Create New List',
            'list_modal_description'        => 'Enter a list name. After creating it, assign the relevant contacts.',
            'list_name_label'               => 'List Name',
            'list_name_placeholder'         => 'Example: SEO course customers',
            'lists_empty_state'             => 'No lists have been created yet.',
            'delete_list_confirm'           => 'Delete this list?',
            'system_list_label'             => 'System',
            'system_list_all'               => 'All',
            'system_list_all_help'          => 'This list always includes every contact: %s contacts.',
            'tags_title'                    => 'Tags',
            'tags_description'              => 'Create custom tags and assign them to contacts. System tags are available for use and cannot be deleted.',
            'add_tag_button'                => 'Add New Tag',
            'create_tag_button'             => 'Create Tag',
            'tag_modal_title'               => 'Create New Tag',
            'tag_modal_description'         => 'Enter a tag name, then select the contacts that should use it.',
            'tag_name_label'                => 'Tag Name',
            'tag_name_placeholder'          => 'Example: Interested in consulting',
            'tags_empty_state'              => 'No custom tags have been created yet.',
            'delete_tag_confirm'            => 'Delete this tag?',
            'system_tags_help'              => 'System tags are created by SMark and cannot be deleted.',
            'system_tag_label'              => 'System',
            'system_tag_today_sent'         => 'Received Email Today',
            'system_tag_today_sent_short'   => 'Today',
            'system_tag_today_sent_help'    => 'This is a daily tag and is active for %s contacts today.',
            'assignment_contacts_label'     => 'Contacts in this item',
            'assignment_contacts_help'      => 'Use Ctrl/Cmd to select multiple contacts.',
            'save_assignments_button'       => 'Save Selection',
            'assigned_contacts_count'       => '%s contacts',
            'list_title'                    => 'Contacts',
            'list_description'              => 'Use this list later to select campaign audiences.',
            'contacts_count_badge'          => '%s contacts total',
            'contacts_search_aria'          => 'Search contacts',
            'contacts_search_placeholder'   => 'Search contacts by name, email, phone, list, tag, or status...',
            'contacts_search_empty'         => 'No contacts match this search.',
            'empty_state'                   => 'No contacts have been added yet.',
            'column_contact'                => 'Contact',
            'column_email'                  => 'Email',
            'column_segment'                => 'Segment',
            'column_lists'                  => 'Lists',
            'column_tags'                   => 'Tags',
            'column_source'                 => 'Source',
            'column_status'                 => 'Status',
            'column_actions'                => 'Actions',
            'status_subscribed'             => 'Subscribed',
            'status_lead'                   => 'Lead',
            'status_unsubscribed'           => 'Unsubscribed',
            'unnamed_contact'               => 'Unnamed contact',
            'delete_button'                 => 'Delete',
            'edit_button'                   => 'Edit',
            'delete_confirm'                => 'Delete this contact?',
            'pagination_label'              => 'Contacts pagination',
            'pagination_summary'            => 'Showing %1$s-%2$s of %3$s contacts',
            'pagination_previous'           => 'Previous',
            'pagination_next'               => 'Next',
            'pagination_ellipsis'           => '...',
            'notice_saved'                  => 'Contact saved.',
            'notice_deleted'                => 'Contact deleted.',
            'notice_list_saved'             => 'List saved.',
            'notice_list_deleted'           => 'List deleted.',
            'notice_tag_saved'              => 'Tag saved.',
            'notice_tag_deleted'            => 'Tag deleted.',
            'notice_error'                  => 'The contact information is incomplete or invalid, or this email already exists.',
            'notice_imported'               => '%s contacts imported successfully.',
            'notice_no_import'              => 'No contacts were imported. Check the email mapping, or make sure the emails are not duplicates.',
            'bulk_button'                   => 'Bulk Add',
            'bulk_title'                    => 'Bulk Add Contacts',
            'bulk_description'              => 'Upload a CSV or Excel file, map its columns to SMark fields, then import contacts in one step.',
            'bulk_close'                    => 'Close modal',
            'bulk_file_label'               => 'Contacts File',
            'bulk_preview_button'           => 'Read File',
            'bulk_help'                     => 'The first row is used as column headers. Supported formats: CSV and XLSX.',
            'bulk_mapping_title'            => 'Map Columns',
            'bulk_mapping_description'      => '%d rows are ready to import. Only email is required; every other field is optional.',
            'bulk_plugin_field'             => 'SMark Field',
            'bulk_file_column'              => 'File Column',
            'bulk_required'                 => 'Required',
            'bulk_ignore_column'            => 'Do not connect',
            'bulk_default_segment'          => 'Optional Default List',
            'bulk_import_button'            => 'Import Contacts',
        );
    }

    private function get_contact_notice($message, $strings) {
        $message = sanitize_key($message);
        if ($message === '') {
            return array('message' => '', 'type' => 'info');
        }

        if ($message === 'saved') {
            return array('message' => $strings['notice_saved'], 'type' => 'success');
        }

        if ($message === 'deleted') {
            return array('message' => $strings['notice_deleted'], 'type' => 'success');
        }

        if ($message === 'list_saved') {
            return array('message' => $strings['notice_list_saved'], 'type' => 'success');
        }

        if ($message === 'list_deleted') {
            return array('message' => $strings['notice_list_deleted'], 'type' => 'success');
        }

        if ($message === 'tag_saved') {
            return array('message' => $strings['notice_tag_saved'], 'type' => 'success');
        }

        if ($message === 'tag_deleted') {
            return array('message' => $strings['notice_tag_deleted'], 'type' => 'success');
        }

        if ($message === 'imported') {
            $count = isset($_GET['imported']) ? absint($_GET['imported']) : 0;
            return array(
                'message' => sprintf($strings['notice_imported'], number_format_i18n($count)),
                'type' => 'success',
            );
        }

        if ($message === 'no_import') {
            return array('message' => $strings['notice_no_import'], 'type' => 'info');
        }

        return array('message' => $strings['notice_error'], 'type' => 'error');
    }

    private function get_basic_notice($message, $strings) {
        $message = sanitize_key($message);
        if ($message === '') {
            return array('message' => '', 'type' => 'info');
        }

        if ($message === 'saved') {
            return array('message' => $strings['notice_saved'], 'type' => 'success');
        }

        if ($message === 'deleted') {
            return array('message' => $strings['notice_deleted'], 'type' => 'success');
        }

        if ($message === 'sent') {
            $count = isset($_GET['sent']) ? absint($_GET['sent']) : 0;
            return array(
                'message' => sprintf($strings['notice_sent'], number_format_i18n($count)),
                'type' => 'success',
            );
        }

        if ($message === 'send_error') {
            return array('message' => $strings['notice_send_error'], 'type' => 'error');
        }

        if ($message === 'capacity_error' && isset($strings['notice_sender_capacity_insufficient'])) {
            return array('message' => $strings['notice_sender_capacity_insufficient'], 'type' => 'warning');
        }

        return array('message' => $strings['notice_error'], 'type' => 'error');
    }

    private function get_campaign_message_strings($lang) {
        if ($lang === 'fa') {
            return array(
                'page_title'                   => 'طراحی پیام کمپین',
                'page_subtitle'                => 'نام کمپین، موضوع، متن ایمیل و مخاطبان هدف را برای ارسال‌های بعدی آماده کنید.',
                'breadcrumb_dashboard'         => 'داشبورد',
                'breadcrumb_parent'            => 'ایمیل مارکتینگ',
                'breadcrumb_current'           => 'طراحی پیام کمپین',
                'form_title'                   => 'ساخت پیام ایمیلی',
                'form_description'             => 'پیام را به صورت پیش‌نویس ذخیره کنید و مخاطبان هدف را از بین سگمنت‌ها یا مخاطبان انتخاب کنید.',
                'edit_modal_title'             => 'ویرایش پیام کمپین',
                'edit_modal_description'       => 'تمامی جزئیات کمپین را بدون خروج از همین صفحه ویرایش کنید.',
                'edit_modal_close'             => 'بستن پنجره ویرایش',
                'edit_modal_cancel'            => 'انصراف',
                'field_campaign_name'          => 'نام ایمیل / کمپین',
                'field_campaign_name_placeholder' => 'مثلا معرفی دوره جدید سئو',
                'field_sender_account'         => 'حساب فرستنده',
                'field_sender_account_empty'   => 'بعدا انتخاب می‌کنم',
                'field_sender_account_help'    => 'می‌توانید چند حساب را انتخاب کنید. ارسال به ترتیب از حساب‌های انتخاب‌شده انجام می‌شود و بعد از پر شدن ظرفیت هر حساب، حساب بعدی استفاده می‌شود.',
                'sender_capacity_remaining_suffix' => 'ظرفیت باقی‌مانده',
                'sender_capacity_warning'      => '%1$s مخاطب انتخاب شده اما ظرفیت باقی‌مانده حساب‌های فرستنده %2$s ایمیل است. حساب‌های بیشتری انتخاب یا اضافه کنید.',
                'field_subject'                => 'موضوع ایمیل',
                'field_subject_placeholder'    => 'مثلا یک پیشنهاد ویژه برای رشد سئوی سایت شما',
                'field_preview_text'           => 'متن پیش‌نمایش',
                'field_preview_text_placeholder' => 'متنی کوتاه که کنار موضوع در inbox دیده می‌شود',
                'field_reply_to'               => 'ایمیل پاسخ‌به',
                'field_status'                 => 'وضعیت پیام',
                'status_draft'                 => 'پیش‌نویس',
                'status_sent'                  => 'ارسال‌شده',
                'field_include_audience'       => 'اینکلود',
                'field_exclude_audience'       => 'اکسکلود',
                'field_include_help'           => 'لیست‌ها، برچسب‌ها یا مخاطبین تکی که باید داخل ارسال باشند را انتخاب کنید.',
                'field_exclude_help'           => 'هر چیزی که اینجا انتخاب شود از اینکلودها کم می‌شود.',
                'select_include_button'        => 'انتخاب اینکلود',
                'select_exclude_button'        => 'انتخاب اکسکلود',
                'audience_picker_empty'        => 'هنوز چیزی انتخاب نشده است.',
                'audience_picker_more'         => '+%s مورد بیشتر',
                'audience_picker_include_title'=> 'انتخاب اینکلودها',
                'audience_picker_exclude_title'=> 'انتخاب اکسکلودها',
                'audience_picker_description'  => 'از بین لیست‌ها، برچسب‌ها و مخاطبین تکی انتخاب کنید.',
                'audience_picker_lists'        => 'لیست‌ها',
                'audience_picker_tags'         => 'برچسب‌ها',
                'audience_picker_contacts'     => 'مخاطبین',
                'audience_picker_search_placeholder' => 'جست‌وجوی مخاطب...',
                'audience_picker_contact_limit_note' => 'برای انتخاب موارد بیشتر جست‌وجو کنید.',
                'audience_picker_selected_count'=> '%s مخاطب انتخاب‌شده',
                'audience_picker_all_help'     => 'همه مخاطبین ذخیره‌شده',
                'audience_picker_apply'        => 'اعمال انتخاب‌ها',
                'audience_picker_close'        => 'بستن پنجره انتخاب',
                'system_list_all'              => 'همه',
                'system_tag_today_sent'        => 'امروز ایمیل دریافت کرده',
                'system_tag_today_sent_help'   => 'تگ سیستمی روزانه، فعال برای %s مخاطب امروز',
                'tags_empty_state'             => 'هنوز برچسب سفارشی ساخته نشده است.',
                'assigned_contacts_count'      => '%s مخاطب',
                'field_body'                   => 'بدنه ایمیل',
                'field_body_placeholder'       => 'متن اصلی ایمیل، پیشنهاد، لینک‌ها و فراخوان اقدام را اینجا بنویسید...',
                'field_notes'                  => 'یادداشت داخلی',
                'field_notes_placeholder'      => 'هدف کمپین، نکات تست A/B یا موارد پیگیری...',
                'form_help'                    => 'برای آماده‌سازی ارسال، موضوع و بدنه ایمیل ضروری هستند. انتخاب مخاطب فعلا اختیاری است و در مرحله ارسال هم قابل کنترل خواهد بود.',
                'save_button'                  => 'ذخیره پیام کمپین',
                'update_button'                => 'به‌روزرسانی پیام کمپین',
                'quick_send_button'            => 'ارسال سریع',
                'test_send_button'             => 'ایمیل تست',
                'test_send_confirm_title'      => 'ارسال ایمیل تست',
                'test_send_confirm_message'    => 'ایمیل مقصد تست را بررسی یا ویرایش کنید:',
                'test_send_email_label'        => 'ایمیل دریافت‌کننده تست',
                'test_send_email_placeholder'  => 'test@example.com',
                'test_send_confirm_button'     => 'تایید و ارسال تست',
                'test_send_cancel_button'      => 'انصراف',
                'send_progress_title'          => 'در حال ارسال کمپین',
                'send_progress_description'    => 'ارسال مرحله‌ای انجام می‌شود. لطفا تا پایان ارسال این پنجره را باز نگه دارید.',
                'send_progress_starting'       => 'در حال آماده‌سازی ارسال...',
                'send_progress_sending'        => 'در حال ارسال ایمیل‌ها...',
                'send_progress_complete'       => 'ارسال کامل شد.',
                'send_progress_count'          => '%1$s از %2$s ایمیل ارسال شده',
                'send_progress_recent_title'   => 'گزارش ایمیل‌ها',
                'send_progress_recent_empty'   => 'هنوز ایمیلی ارسال نشده است.',
                'send_progress_report_sent'    => 'ارسال',
                'send_progress_report_failed'  => 'خطا',
                'send_progress_final_note'     => 'آمار نهایی این کمپین را بعدا می‌توانید در بخش پرفورمنس مشاهده کنید.',
                'send_progress_close'          => 'بستن پنجره ارسال',
                'list_title'                   => 'پیام‌های ذخیره‌شده',
                'list_description'             => 'این پیام‌ها بعدا در تقویم ارسال و اجرای کمپین استفاده می‌شوند.',
                'empty_state'                  => 'هنوز پیامی برای کمپین ذخیره نشده است.',
                'column_campaign'              => 'کمپین',
                'column_subject'               => 'موضوع',
                'column_audience'              => 'مخاطبان',
                'column_status'                => 'وضعیت',
                'column_actions'               => 'عملیات',
                'audience_not_selected'        => 'هنوز انتخاب نشده',
                'audience_summary'             => '%s اینکلود، %s اکسکلود، %s مخاطب نهایی',
                'delete_button'                => 'حذف',
                'edit_button'                  => 'ویرایش',
                'send_button'                  => 'ارسال',
                'performance_button'           => 'عملکرد',
                'performance_close'            => 'بستن پنجره عملکرد',
                'delete_confirm'               => 'این پیام کمپین حذف شود؟',
                'notice_saved'                 => 'پیام کمپین ذخیره شد.',
                'notice_deleted'               => 'پیام کمپین حذف شد.',
                'notice_error'                 => 'اطلاعات پیام کامل یا معتبر نیست.',
                'notice_sent'                  => '%s ایمیل ارسال شد.',
                'notice_test_sent'             => 'ایمیل تست به %s ارسال شد.',
                'notice_test_send_error'       => 'ارسال تست انجام نشد. ایمیل مقصد یا تنظیمات ارسال را بررسی کنید.',
                'notice_send_error'            => 'ارسال انجام نشد. مخاطبان، موضوع، بدنه ایمیل یا تنظیمات ارسال را بررسی کنید.',
                'notice_no_recipients'         => 'هیچ مخاطب قابل ارسالی پیدا نشد. مخاطبان کمپین را دوباره انتخاب و ذخیره کنید.',
                'notice_sender_not_configured' => 'ارسال انجام نشد چون حساب فرستنده داخلی اسمارک انتخاب نشده یا تنظیمات SMTP آن کامل نیست. لطفا حساب جیمیل داخلی را انتخاب کنید و SMTP Host، SMTP Port و App Password را بررسی کنید.',
                'notice_sender_capacity_insufficient' => 'ارسال انجام نشد چون ظرفیت باقی‌مانده حساب‌های فرستنده برای تعداد مخاطبان انتخاب‌شده کافی نیست.',
            );
        }

        return array(
            'page_title'                   => 'Campaign Message',
            'page_subtitle'                => 'Prepare the campaign name, email content, and target audience for future sends.',
            'breadcrumb_dashboard'         => 'Dashboard',
            'breadcrumb_parent'            => 'Email Marketing',
            'breadcrumb_current'           => 'Campaign Message',
            'form_title'                   => 'Build Email Message',
            'form_description'             => 'Save the message as a draft and choose the target audience with include and exclude rules.',
            'edit_modal_title'             => 'Edit Campaign Message',
            'edit_modal_description'       => 'Update every campaign detail without leaving this page.',
            'edit_modal_close'             => 'Close edit modal',
            'edit_modal_cancel'            => 'Cancel',
            'field_campaign_name'          => 'Email / Campaign Name',
            'field_campaign_name_placeholder' => 'Example: New SEO course announcement',
            'field_sender_account'         => 'Sender Account',
            'field_sender_account_empty'   => 'Choose later',
            'field_sender_account_help'    => 'You can select multiple accounts. SMark sends in order and switches to the next account when the current one reaches its daily capacity.',
            'sender_capacity_remaining_suffix' => 'remaining',
            'sender_capacity_warning'      => '%1$s recipients are selected, but the selected sender accounts have %2$s emails of remaining capacity. Select or add more sender accounts.',
            'field_subject'                => 'Subject Line',
            'field_subject_placeholder'    => 'Example: A special offer to grow your site SEO',
            'field_preview_text'           => 'Preview Text',
            'field_preview_text_placeholder' => 'Short text shown next to the subject in the inbox',
            'field_reply_to'               => 'Reply-To Email',
            'field_status'                 => 'Message Status',
            'status_draft'                 => 'Draft',
            'status_sent'                  => 'Sent',
            'field_include_audience'       => 'Include',
            'field_exclude_audience'       => 'Exclude',
            'field_include_help'           => 'Choose lists, tags, or individual contacts that should receive this email.',
            'field_exclude_help'           => 'Anything selected here is removed from the included audience.',
            'select_include_button'        => 'Select Include',
            'select_exclude_button'        => 'Select Exclude',
            'audience_picker_empty'        => 'Nothing selected yet.',
            'audience_picker_more'         => '+%s more',
            'audience_picker_include_title'=> 'Select Includes',
            'audience_picker_exclude_title'=> 'Select Excludes',
            'audience_picker_description'  => 'Choose from lists, tags, and individual contacts.',
            'audience_picker_lists'        => 'Lists',
            'audience_picker_tags'         => 'Tags',
            'audience_picker_contacts'     => 'Contacts',
            'audience_picker_search_placeholder' => 'Search contacts...',
            'audience_picker_contact_limit_note' => 'Search to select more contacts.',
            'audience_picker_selected_count'=> '%s contacts selected',
            'audience_picker_all_help'     => 'Every saved contact',
            'audience_picker_apply'        => 'Apply Selection',
            'audience_picker_close'        => 'Close picker',
            'system_list_all'              => 'All',
            'system_tag_today_sent'        => 'Received Email Today',
            'system_tag_today_sent_help'   => 'Daily system tag, active for %s contacts today',
            'tags_empty_state'             => 'No custom tags have been created yet.',
            'assigned_contacts_count'      => '%s contacts',
            'field_body'                   => 'Email Body',
            'field_body_placeholder'       => 'Write the main email copy, offer, links, and call to action here...',
            'field_notes'                  => 'Internal Notes',
            'field_notes_placeholder'      => 'Campaign goal, A/B test notes, or follow-up details...',
            'form_help'                    => 'Subject and body are required. Audience selection is optional for now and can be finalized during sending.',
            'save_button'                  => 'Save Campaign Message',
            'update_button'                => 'Update Campaign Message',
            'quick_send_button'            => 'Quick Send',
            'test_send_button'             => 'Test Email',
            'test_send_confirm_title'      => 'Send test email',
            'test_send_confirm_message'    => 'Review or edit the test recipient email:',
            'test_send_email_label'        => 'Test recipient email',
            'test_send_email_placeholder'  => 'test@example.com',
            'test_send_confirm_button'     => 'Confirm and Send Test',
            'test_send_cancel_button'      => 'Cancel',
            'send_progress_title'          => 'Sending Campaign',
            'send_progress_description'    => 'Emails are sent in batches. Keep this window open until sending finishes.',
            'send_progress_starting'       => 'Preparing the send...',
            'send_progress_sending'        => 'Sending emails...',
            'send_progress_complete'       => 'Sending complete.',
            'send_progress_count'          => '%1$s of %2$s emails sent',
            'send_progress_recent_title'   => 'Email report',
            'send_progress_recent_empty'   => 'No email has been sent yet.',
            'send_progress_report_sent'    => 'Sent',
            'send_progress_report_failed'  => 'Failed',
            'send_progress_final_note'     => 'You can review the final campaign stats later in Performance.',
            'send_progress_close'          => 'Close send window',
            'list_title'                   => 'Saved Messages',
            'list_description'             => 'These messages can later be used in send scheduling and campaign execution.',
            'empty_state'                  => 'No campaign message has been saved yet.',
            'column_campaign'              => 'Campaign',
            'column_subject'               => 'Subject',
            'column_audience'              => 'Audience',
            'column_status'                => 'Status',
            'column_actions'               => 'Actions',
            'audience_not_selected'        => 'Not selected yet',
            'audience_summary'             => '%s includes, %s excludes, %s final contacts',
            'delete_button'                => 'Delete',
            'edit_button'                  => 'Edit',
            'send_button'                  => 'Send',
            'performance_button'           => 'Performance',
            'performance_close'            => 'Close performance modal',
            'delete_confirm'               => 'Delete this campaign message?',
            'notice_saved'                 => 'Campaign message saved.',
            'notice_deleted'               => 'Campaign message deleted.',
            'notice_error'                 => 'The message information is incomplete or invalid.',
            'notice_sent'                  => '%s emails sent.',
            'notice_test_sent'             => 'Test email sent to %s.',
            'notice_test_send_error'       => 'Test send failed. Check the recipient email or sending settings.',
            'notice_send_error'            => 'Send failed. Check recipients, subject, body, or sending settings.',
            'notice_no_recipients'         => 'No sendable recipient was found. Re-select and save this campaign audience.',
            'notice_sender_not_configured' => 'Send failed because the internal SMark sender account is missing or its SMTP settings are incomplete. Select the Gmail account and check SMTP host, port, and app password.',
            'notice_sender_capacity_insufficient' => 'Send failed because the selected sender accounts do not have enough remaining capacity for the selected audience.',
        );
    }

    private function get_performance_strings($lang) {
        if ($lang === 'fa') {
            return array(
                'page_title'                => 'پایش عملکرد',
                'page_subtitle'             => 'نرخ ارسال، باز شدن، کلیک و رفتار مخاطبان کمپین‌های ایمیلی را بررسی کنید.',
                'breadcrumb_dashboard'      => 'داشبورد',
                'breadcrumb_parent'         => 'ایمیل مارکتینگ',
                'breadcrumb_current'        => 'پایش عملکرد',
                'overview_title'            => 'آمار کلی کمپین‌ها',
                'overview_description'      => 'نمایی خلاصه از عملکرد همه پیام‌های ارسال‌شده و رویدادهای ثبت‌شده.',
                'campaign_title'            => 'جزئیات کمپین',
                'campaign_description'      => 'یک کمپین را انتخاب کنید تا نرخ‌ها، مخاطبان و آخرین فعالیت‌های آن را ببینید.',
                'field_campaign'            => 'انتخاب کمپین',
                'empty_state'               => 'هنوز کمپینی برای پایش وجود ندارد.',
                'selected_campaign'         => 'کمپین انتخاب‌شده',
                'selected_status'           => 'وضعیت ارسال',
                'selected_audience'         => 'مخاطبان هدف',
                'not_sent_yet'              => 'هنوز ارسال نشده',
                'audience_help'             => 'براساس سگمنت‌ها و مخاطبان انتخاب‌شده',
                'metric_sent'               => 'ارسال‌شده',
                'metric_sent_help'          => 'تعداد ایمیل‌هایی که ارسال موفق داشته‌اند.',
                'metric_open_rate'          => 'نرخ باز شدن',
                'metric_open_help'          => '%s باز شدن یکتا، %s باز شدن کل',
                'metric_click_rate'         => 'نرخ کلیک',
                'metric_click_help'         => '%s کلیک یکتا، %s کلیک کل',
                'metric_ctor'               => 'نرخ کلیک به بازشدن',
                'metric_ctor_help'          => 'نسبت کلیک‌کنندگان یکتا به بازکنندگان یکتا.',
                'metric_failed'             => 'خطای ارسال',
                'metric_failed_help'        => 'تعداد گیرنده‌هایی که ارسال برایشان ناموفق بوده است.',
                'metric_unsub_bounce'       => 'لغو/برگشت',
                'metric_unsub_bounce_help'  => '%s لغو عضویت، %s برگشت ایمیل',
                'column_event'              => 'رویدادها',
                'column_recipient'          => 'مخاطب',
                'column_link'               => 'لینک',
                'column_time'               => 'زمان‌ها',
                'no_events'                 => 'هنوز رویدادی برای این کمپین ثبت نشده است. آمار باز شدن و کلیک از ارسال‌های جدید به بعد ثبت می‌شود.',
                'unknown_recipient'         => 'مخاطب ناشناس',
                'not_available'             => '-',
                'activity_pagination_label' => 'صفحه‌بندی گزارش کمپین',
                'activity_pagination_summary' => 'نمایش %1$s تا %2$s از %3$s گزارش',
                'activity_pagination_previous' => 'قبلی',
                'activity_pagination_next'  => 'بعدی',
                'activity_pagination_ellipsis' => '...',
                'event_sent'                => 'ارسال',
                'event_failed'              => 'خطا',
                'event_open'                => 'باز شدن',
                'event_click'               => 'کلیک',
                'event_unsubscribe'         => 'لغو عضویت',
                'event_bounce'              => 'برگشت',
                'event_unknown'             => 'نامشخص',
                'failure_modal_title'        => 'دلیل خطای ارسال',
                'failure_modal_close'        => 'بستن جزئیات خطا',
                'failure_reason_label'       => 'دلیل:',
                'failure_code_label'         => 'کد خطا:',
                'failure_smtp_message_label' => 'پیام SMTP:',
                'failure_sender_label'       => 'فرستنده:',
                'failure_smtp_label'         => 'SMTP:',
                'failure_reason_unavailable' => 'جزئیات دقیق این خطا ذخیره نشده است. این مورد احتمالا قبل از نسخه گزارش جزئیات خطا ثبت شده است.',
                'failure_possible_reasons_label' => 'دلایل احتمالی کامل:',
                'failure_reason_auth'        => 'خطای احراز هویت SMTP: ایمیل فرستنده، نام کاربری، App Password یا تنظیمات امنیتی سرویس‌دهنده را بررسی کنید.',
                'failure_reason_connection'  => 'خطای اتصال SMTP: هاست، پورت، فایروال یا دسترسی شبکه سرور به SMTP را بررسی کنید.',
                'failure_reason_timeout'     => 'Timeout اتصال: سرور ایمیل کند، مسدود یا موقتاً در دسترس نیست.',
                'failure_reason_tls'         => 'خطای TLS/SSL: نوع رمزنگاری، پورت و پشتیبانی certificate را بررسی کنید.',
                'failure_reason_recipient'   => 'گیرنده نامعتبر یا رد شده: آدرس ایمیل مخاطب، دامنه یا mailbox را بررسی کنید.',
                'failure_reason_rejected'    => 'رد شدن توسط سیاست سرویس‌دهنده یا فیلتر اسپم: محتوا، لینک‌ها، reputation دامنه و SPF/DKIM را بررسی کنید.',
                'failure_reason_rate_limit'  => 'محدودیت ارسال یا quota: ظرفیت روزانه/ساعتی سرویس‌دهنده یا rate limit پر شده است.',
                'failure_reason_dns'         => 'خطای DNS: هاست SMTP یا دامنه گیرنده resolve نشده است.',
                'failure_reason_sender'      => 'تنظیمات فرستنده ناقص است: SMTP Host، Port، Encryption و App Password را بررسی کنید.',
                'failure_reason_content'     => 'مشکل محتوای ایمیل: HTML، لینک‌ها، attachment یا کلمات حساس ممکن است باعث reject شده باشد.',
                'failure_retry_title'        => 'ارسال مجدد خطاها',
                'failure_retry_description'  => 'در حال حاضر %s ایمیل ناموفق برای ارسال مجدد آماده است.',
                'failure_retry_close'        => 'بستن ارسال مجدد',
                'failure_retry_count_label'  => 'تعداد ایمیل برای ارسال مجدد',
                'failure_retry_count_help'   => 'حداکثر %s ایمیل ناموفق قابل انتخاب است.',
                'failure_retry_account_label' => 'اکانت ارسال',
                'failure_retry_account_placeholder' => 'یک اکانت ارسال انتخاب کنید',
                'failure_retry_account_help' => 'ارسال مجدد فقط با همین اکانت انجام می‌شود.',
                'failure_retry_start_button' => 'شروع ارسال مجدد',
                'failure_retry_cancel_button' => 'انصراف',
                'failure_retry_starting'     => 'در حال آماده‌سازی ارسال مجدد...',
                'failure_retry_sending'      => 'در حال ارسال مجدد ایمیل‌های ناموفق...',
                'failure_retry_complete'     => 'ارسال مجدد کامل شد.',
                'failure_retry_count_progress' => '%1$s ارسال موفق، %2$s خطای مجدد از %3$s ایمیل',
                'failure_retry_complete_notice' => 'ارسال مجدد کامل شد: %1$s موفق، %2$s ناموفق.',
            );
        }

        return array(
            'page_title'                => 'Performance Tracking',
            'page_subtitle'             => 'Review send, open, click, and audience behavior across email campaigns.',
            'breadcrumb_dashboard'      => 'Dashboard',
            'breadcrumb_parent'         => 'Email Marketing',
            'breadcrumb_current'        => 'Performance Tracking',
            'overview_title'            => 'Overall Campaign Stats',
            'overview_description'      => 'A compact view of sent messages and tracked campaign events.',
            'campaign_title'            => 'Campaign Details',
            'campaign_description'      => 'Select a campaign to review rates, audience, and recent activity.',
            'field_campaign'            => 'Select Campaign',
            'empty_state'               => 'No campaign is available for tracking yet.',
            'selected_campaign'         => 'Selected Campaign',
            'selected_status'           => 'Send Status',
            'selected_audience'         => 'Target Audience',
            'not_sent_yet'              => 'Not sent yet',
            'audience_help'             => 'Based on selected segments and contacts',
            'metric_sent'               => 'Sent',
            'metric_sent_help'          => 'Emails successfully sent.',
            'metric_open_rate'          => 'Open Rate',
            'metric_open_help'          => '%s unique opens, %s total opens',
            'metric_click_rate'         => 'Click Rate',
            'metric_click_help'         => '%s unique clicks, %s total clicks',
            'metric_ctor'               => 'Click-To-Open',
            'metric_ctor_help'          => 'Unique clickers divided by unique openers.',
            'metric_failed'             => 'Send Failures',
            'metric_failed_help'        => 'Recipients whose send attempt failed.',
            'metric_unsub_bounce'       => 'Unsub/Bounce',
            'metric_unsub_bounce_help'  => '%s unsubscribes, %s bounces',
            'column_event'              => 'Events',
            'column_recipient'          => 'Recipient',
            'column_link'               => 'Link',
            'column_time'               => 'Times',
            'no_events'                 => 'No event has been recorded for this campaign yet. Opens and clicks are tracked for new sends.',
            'unknown_recipient'         => 'Unknown recipient',
            'not_available'             => '-',
            'activity_pagination_label' => 'Campaign report pagination',
            'activity_pagination_summary' => 'Showing %1$s-%2$s of %3$s reports',
            'activity_pagination_previous' => 'Previous',
            'activity_pagination_next'  => 'Next',
            'activity_pagination_ellipsis' => '...',
            'event_sent'                => 'Sent',
            'event_failed'              => 'Failed',
            'event_open'                => 'Open',
            'event_click'               => 'Click',
            'event_unsubscribe'         => 'Unsubscribe',
            'event_bounce'              => 'Bounce',
            'event_unknown'             => 'Unknown',
            'failure_modal_title'        => 'Send failure reason',
            'failure_modal_close'        => 'Close failure details',
            'failure_reason_label'       => 'Reason:',
            'failure_code_label'         => 'Error code:',
            'failure_smtp_message_label' => 'SMTP message:',
            'failure_sender_label'       => 'Sender:',
            'failure_smtp_label'         => 'SMTP:',
            'failure_reason_unavailable' => 'Detailed failure data was not stored for this event. It was likely recorded before failure details were added.',
            'failure_possible_reasons_label' => 'Complete list of possible causes:',
            'failure_reason_auth'        => 'SMTP authentication failed: check sender email, username, app password, and provider security settings.',
            'failure_reason_connection'  => 'SMTP connection failed: check host, port, firewall, and server network access.',
            'failure_reason_timeout'     => 'Connection timeout: the mail server may be slow, blocked, or temporarily unavailable.',
            'failure_reason_tls'         => 'TLS/SSL failure: check encryption type, port, and certificate support.',
            'failure_reason_recipient'   => 'Invalid or rejected recipient: check the contact email address, domain, or mailbox.',
            'failure_reason_rejected'    => 'Rejected by provider policy or spam filtering: check content, links, domain reputation, SPF, and DKIM.',
            'failure_reason_rate_limit'  => 'Sending quota or rate limit reached: provider daily/hourly capacity may be exhausted.',
            'failure_reason_dns'         => 'DNS failure: SMTP host or recipient domain could not be resolved.',
            'failure_reason_sender'      => 'Sender configuration is incomplete: check SMTP host, port, encryption, and app password.',
            'failure_reason_content'     => 'Email content issue: HTML, links, attachments, or sensitive terms may have triggered rejection.',
            'failure_retry_title'        => 'Retry send failures',
            'failure_retry_description'  => '%s failed emails are currently available for retry.',
            'failure_retry_close'        => 'Close retry modal',
            'failure_retry_count_label'  => 'Emails to retry',
            'failure_retry_count_help'   => 'You can select up to %s failed emails.',
            'failure_retry_account_label' => 'Sending account',
            'failure_retry_account_placeholder' => 'Select a sending account',
            'failure_retry_account_help' => 'Retry sends will use only this selected account.',
            'failure_retry_start_button' => 'Start Retry',
            'failure_retry_cancel_button' => 'Cancel',
            'failure_retry_starting'     => 'Preparing retry send...',
            'failure_retry_sending'      => 'Retrying failed emails...',
            'failure_retry_complete'     => 'Retry sending complete.',
            'failure_retry_count_progress' => '%1$s resent, %2$s failed again of %3$s emails',
            'failure_retry_complete_notice' => 'Retry sending complete: %1$s succeeded, %2$s failed.',
        );
    }

    private function get_tasks($lang) {
        if ($lang === 'fa') {
            return array(
                array(
                    'icon' => 'dashicons-groups',
                    'title' => 'مخاطبین',
                    'description' => 'لیست‌ها و برچسب‌های مخاطبان را بر اساس هدف کمپین آماده کنید.',
                    'url' => add_query_arg(array('page' => 'smark-dashboard-page', 'smark_email_view' => 'contacts'), admin_url('admin.php')),
                ),
                array(
                    'icon' => 'dashicons-email-alt',
                    'title' => 'طراحی پیام کمپین',
                    'description' => 'موضوع، متن، پیشنهاد و فراخوان اقدام ایمیل را برنامه‌ریزی کنید.',
                    'url' => admin_url('admin.php?page=smark-email-campaign-message'),
                ),
                array(
                    'icon' => 'dashicons-admin-users',
                    'title' => 'حساب‌های ایمیل',
                    'description' => 'مدیریت حساب‌های فرستنده و سقف ارسال روزانه آن‌ها.',
                    'url' => admin_url('admin.php?page=smark-email-accounts'),
                ),
                array(
                    'icon' => 'dashicons-chart-area',
                    'title' => 'پایش عملکرد',
                    'description' => 'نرخ باز شدن، کلیک، تبدیل و خروج از لیست را بررسی کنید.',
                    'url' => admin_url('admin.php?page=smark-email-performance'),
                ),
            );
        }

        return array(
            array(
                'icon' => 'dashicons-groups',
                'title' => 'Contacts',
                'description' => 'Prepare contact lists and tags based on each campaign goal.',
                'url' => add_query_arg(array('page' => 'smark-dashboard-page', 'smark_email_view' => 'contacts'), admin_url('admin.php')),
            ),
            array(
                'icon' => 'dashicons-email-alt',
                'title' => 'Campaign Message',
                'description' => 'Plan the subject, copy, offer, and call to action for each email.',
                'url' => admin_url('admin.php?page=smark-email-campaign-message'),
            ),
            array(
                'icon' => 'dashicons-admin-users',
                'title' => 'Email Accounts',
                'description' => 'Manage sender accounts and daily send limits.',
                'url' => admin_url('admin.php?page=smark-email-accounts'),
            ),
            array(
                'icon' => 'dashicons-chart-area',
                'title' => 'Performance Review',
                'description' => 'Track opens, clicks, conversions, and unsubscribes.',
                'url' => admin_url('admin.php?page=smark-email-performance'),
            ),
        );
    }

    private function get_email_accounts_css() {
        return '
            .smark-email-task-link {
                display: block;
                color: inherit;
                text-decoration: none;
            }

            .smark-email-task-link:hover {
                color: inherit;
                text-decoration: none;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-workflow-header {
                flex-direction: row;
                justify-content: flex-start;
                text-align: left;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-workflow-header > div {
                width: 100%;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-account-form-header {
                flex-direction: row;
                align-items: flex-start;
                justify-content: space-between;
                text-align: left;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-performance-header {
                flex-direction: row;
                justify-content: flex-start;
                text-align: left;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-performance-header > div {
                width: 100%;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-campaign-message-header {
                flex-direction: row;
                justify-content: flex-start;
                text-align: left;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-campaign-message-header > div {
                width: 100%;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-accounts-list-header {
                flex-direction: row;
                justify-content: flex-start;
                text-align: left;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-accounts-list-header > div {
                width: 100%;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-campaign-messages-list-header {
                direction: ltr;
                flex-direction: row;
                justify-content: flex-start;
                text-align: left;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-campaign-messages-list-header > div {
                direction: ltr;
                text-align: left;
                margin-right: auto;
            }

            .smark-email-account-form-header > div:first-child {
                min-width: 0;
            }

            .smark-email-provider-switch {
                display: flex;
                align-items: center;
                gap: 10px;
                flex: 0 0 auto;
            }

            .smark-email-provider-switch label {
                color: #1f2937;
                font-weight: 600;
                white-space: nowrap;
            }

            .smark-email-provider-switch select {
                min-width: 132px;
                min-height: 42px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 12px;
                padding: 8px 34px 8px 12px;
                color: #1f2937;
                background-color: #ffffff;
                box-shadow: none;
            }

            .smark-email-provider-switch select:focus {
                border-color: #6366f1;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
                outline: none;
            }

            .smark-seo-optimization-page.rtl .smark-email-provider-switch {
                flex-direction: row-reverse;
            }

            .smark-email-gmail-app-link {
                display: inline-flex;
                align-items: center;
                gap: 3px;
                margin-left: 6px;
                font-size: 0.92em;
                font-weight: 500;
            }

            .smark-email-gmail-app-link a {
                display: inline-flex;
                align-items: center;
                gap: 3px;
                color: #4f46e5;
                text-decoration: none;
            }

            .smark-email-gmail-app-link a:hover,
            .smark-email-gmail-app-link a:focus {
                color: #3730a3;
                text-decoration: underline;
            }

            .smark-email-gmail-app-link .dashicons {
                width: 14px;
                height: 14px;
                font-size: 14px;
                line-height: 14px;
            }

            .smark-email-gmail-app-info {
                position: relative;
                display: inline-flex;
                align-items: center;
                margin-left: 6px;
                vertical-align: middle;
            }

            .smark-email-info-button {
                width: 18px;
                height: 18px;
                min-height: 18px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                border: 0;
                border-radius: 50%;
                color: #4f46e5;
                background: transparent;
                cursor: help;
            }

            .smark-email-info-button .dashicons {
                width: 16px;
                height: 16px;
                font-size: 16px;
                line-height: 16px;
            }

            .smark-email-info-button:hover,
            .smark-email-info-button:focus {
                color: #3730a3;
                outline: none;
            }

            .smark-email-info-tooltip {
                position: absolute;
                left: 50%;
                bottom: calc(100% + 10px);
                z-index: 20;
                width: min(320px, 82vw);
                display: flex;
                flex-direction: column;
                gap: 6px;
                padding: 12px 14px;
                border: 1px solid rgba(99, 102, 241, 0.18);
                border-radius: 10px;
                background: #ffffff;
                color: #1f2937;
                box-shadow: 0 18px 42px rgba(15, 23, 42, 0.18);
                font-size: 12px;
                font-weight: 500;
                line-height: 1.55;
                opacity: 0;
                pointer-events: none;
                transform: translate(-50%, 6px);
                transition: opacity 0.16s ease, transform 0.16s ease;
            }

            .smark-email-info-tooltip strong {
                color: #111827;
                font-size: 12.5px;
                font-weight: 700;
            }

            .smark-email-info-tooltip::after {
                content: "";
                position: absolute;
                left: 50%;
                top: 100%;
                width: 10px;
                height: 10px;
                background: #ffffff;
                border-right: 1px solid rgba(99, 102, 241, 0.18);
                border-bottom: 1px solid rgba(99, 102, 241, 0.18);
                transform: translate(-50%, -5px) rotate(45deg);
            }

            .smark-email-gmail-app-info:hover .smark-email-info-tooltip,
            .smark-email-gmail-app-info:focus-within .smark-email-info-tooltip {
                opacity: 1;
                transform: translate(-50%, 0);
            }

            .smark-seo-optimization-page.rtl .smark-email-gmail-app-link {
                margin-right: 6px;
                margin-left: 0;
            }

            .smark-seo-optimization-page.rtl .smark-email-gmail-app-info {
                margin-right: 6px;
                margin-left: 0;
            }

            .smark-email-page-actions {
                display: flex;
                justify-content: flex-start;
                margin: 0 0 18px;
            }

            .smark-seo-optimization-page.rtl .smark-email-page-actions {
                justify-content: flex-end;
            }

            .smark-email-page-actions .button-primary {
                min-height: 42px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 18px;
                border-radius: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                border: none !important;
                font-weight: 700;
                color: #ffffff !important;
                text-decoration: none !important;
                box-shadow: 0 12px 24px rgba(99, 102, 241, 0.2) !important;
            }

            .smark-email-card-header-actions {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
            }

            .smark-email-card-header-actions .button-primary,
            .smark-email-saved-contacts-header .button-primary {
                flex: 0 0 auto;
                min-height: 42px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 18px;
                border-radius: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                border: none !important;
                font-weight: 700;
                color: #ffffff !important;
                text-decoration: none !important;
                box-shadow: 0 12px 24px rgba(99, 102, 241, 0.2) !important;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-contacts-header-actions > div {
                order: 1;
                direction: ltr;
                text-align: left;
                margin-right: auto;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-saved-contacts-header {
                direction: ltr;
                flex-direction: row;
                justify-content: flex-start;
                align-items: center;
                gap: 14px;
                flex-wrap: nowrap;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-saved-contacts-header > div:first-child {
                order: 1;
                direction: ltr;
                text-align: left;
                margin-right: auto;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-saved-contacts-header .smark-email-contacts-actions-wrapper {
                order: 3;
            }

            .smark-email-contacts-count-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 34px;
                padding: 7px 13px;
                border-radius: 999px;
                background: rgba(79, 70, 229, 0.1);
                border: 1px solid rgba(79, 70, 229, 0.16);
                color: #4338ca;
                font-size: 13px;
                font-weight: 800;
                white-space: nowrap;
            }

            .smark-email-contacts-search-bar {
                flex: 0 1 320px;
                max-width: 320px;
                min-width: 220px;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-saved-contacts-header .smark-email-contacts-search-bar {
                order: 2;
                margin-left: 0;
                direction: ltr;
            }

            .smark-email-contacts-search-bar input {
                width: 100%;
                min-height: 38px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 12px;
                padding: 8px 12px;
                background: #ffffff;
                color: #111827;
                box-shadow: none;
            }

            .smark-email-contacts-search-bar input:focus {
                border-color: #6366f1;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16);
                outline: none;
            }

            .smark-email-inline-panel {
                margin: 0 0 18px;
                border: 1px solid rgba(148, 163, 184, 0.2);
                border-radius: 16px;
                background: rgba(248, 250, 252, 0.82);
                overflow: hidden;
            }

            .smark-email-inline-panel[hidden] {
                display: none !important;
            }

            .smark-email-contact-section-hidden {
                display: none !important;
            }

            #smarkEmailContactAddModal[hidden],
            #smarkEmailImportModal[hidden],
            #smarkEmailContactListModal[hidden],
            #smarkEmailContactTagModal[hidden],
            #smarkEmailAccountEditModal[hidden],
            #smarkEmailCampaignEditModal[hidden],
            .smark-email-campaign-performance-section[hidden],
            #smarkEmailAudiencePickerModal[hidden] {
                display: none !important;
            }

            .smark-email-inline-panel__header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                padding: 18px 20px;
                border-bottom: 1px solid rgba(148, 163, 184, 0.18);
                background: rgba(255, 255, 255, 0.78);
            }

            .smark-email-inline-panel__header h2 {
                margin: 0 0 6px;
                color: #111827;
                font-size: 1.16rem;
                font-weight: 900;
            }

            .smark-email-inline-panel__header p {
                margin: 0;
                color: #64748b;
                line-height: 1.7;
            }

            .smark-email-inline-panel__close {
                width: 38px;
                height: 38px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 auto;
                border: 1px solid rgba(148, 163, 184, 0.28);
                border-radius: 12px;
                background: #ffffff;
                color: #475569;
                cursor: pointer;
            }

            .smark-email-inline-panel__body {
                padding: 20px;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-contact-workflow-header {
                direction: ltr;
                flex-direction: row;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-contact-workflow-header > div {
                order: 1;
                text-align: left;
                margin-right: auto;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-contact-workflow-header .smark-email-inline-panel__close {
                order: 2;
                margin-left: auto;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-contacts-header-actions {
                flex-direction: row;
                justify-content: flex-start;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-contacts-header-actions .smark-email-open-import {
                order: 2;
                margin-left: auto;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-contact-lists-header,
            .smark-seo-optimization-page[data-lang="en"] .smark-email-contact-tags-header {
                direction: ltr;
                flex-direction: row;
                justify-content: flex-start;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-contact-lists-header > div,
            .smark-seo-optimization-page[data-lang="en"] .smark-email-contact-tags-header > div {
                order: 1;
                direction: ltr;
                text-align: left;
                margin-right: auto;
            }

            .smark-seo-optimization-page[data-lang="en"] .smark-email-contact-lists-header > .button,
            .smark-seo-optimization-page[data-lang="en"] .smark-email-contact-tags-header > .button {
                order: 2;
                margin-left: auto;
            }

            .smark-email-management-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .smark-email-management-card {
                border: 1px solid rgba(148, 163, 184, 0.22);
                border-radius: 14px;
                padding: 16px;
                background: rgba(248, 250, 252, 0.72);
                display: flex;
                flex-direction: column;
                gap: 14px;
            }

            .smark-email-management-card > header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
            }

            .smark-email-management-card h3 {
                margin: 0 0 4px;
                color: #111827;
                font-size: 1.05rem;
                font-weight: 800;
            }

            .smark-email-management-card p {
                margin: 0;
                color: #64748b;
                font-size: 0.88rem;
                font-weight: 700;
            }

            .smark-email-system-tags {
                margin-bottom: 16px;
                border: 1px solid rgba(99, 102, 241, 0.2);
                border-radius: 14px;
                padding: 14px 16px;
                background: rgba(99, 102, 241, 0.06);
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .smark-email-system-tags strong {
                color: #111827;
                font-weight: 800;
            }

            .smark-email-system-tags small {
                color: #64748b;
                font-weight: 600;
            }

            .smark-email-status--system {
                background: #eef2ff;
                color: #4338ca;
            }

            .smark-email-audience-builder__box {
                min-height: 68px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 14px;
                padding: 10px;
                background: #ffffff;
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .smark-email-audience-builder__chips {
                min-width: 0;
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .smark-email-audience-builder__inputs {
                display: none;
            }

            .smark-email-audience-empty {
                color: #64748b;
                font-size: 0.9em;
                font-weight: 600;
            }

            .smark-email-audience-chip {
                min-height: 34px;
                max-width: 220px;
                border: 1px solid rgba(99, 102, 241, 0.18);
                border-radius: 999px;
                padding: 0 12px;
                background: #eef2ff;
                color: #3730a3;
                font-size: 0.86em;
                font-weight: 800;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                cursor: pointer;
            }

            .smark-email-audience-chip--more {
                background: #ffffff;
                color: #4f46e5;
            }

            .smark-email-audience-modal__section {
                border: 1px solid rgba(148, 163, 184, 0.22);
                border-radius: 14px;
                padding: 14px;
                margin-bottom: 14px;
                background: rgba(248, 250, 252, 0.72);
            }

            .smark-email-audience-modal__section h3 {
                margin: 0 0 10px;
                color: #111827;
                font-size: 1rem;
                font-weight: 800;
            }

            .smark-email-audience-contacts-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 10px;
            }

            .smark-email-audience-contacts-header h3 {
                margin: 0;
            }

            .smark-email-audience-contacts-tools {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 10px;
                flex-wrap: wrap;
            }

            .smark-email-audience-selected-count {
                color: #4f46e5;
                font-size: 0.84em;
                font-weight: 800;
                white-space: nowrap;
            }

            .smark-email-audience-contacts-tools input[type="search"] {
                width: min(260px, 46vw);
                min-height: 36px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 10px;
                padding: 7px 10px;
                box-shadow: none;
            }

            .smark-email-audience-contact-limit-note {
                margin: 10px 0 0;
                color: #64748b;
                font-size: 0.86em;
                font-weight: 700;
            }

            .smark-email-audience-option {
                display: grid;
                grid-template-columns: 22px minmax(0, 1fr) auto;
                align-items: center;
                gap: 10px;
                padding: 9px 10px;
                border-radius: 10px;
                color: #1f2937;
                cursor: pointer;
            }

            .smark-email-audience-option:hover {
                background: #ffffff;
            }

            .smark-email-audience-option span {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-weight: 800;
            }

            .smark-email-audience-option small {
                color: #64748b;
                font-size: 0.82em;
                font-weight: 700;
                white-space: nowrap;
            }

            .smark-email-editor-field {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .smark-email-editor-field .wp-editor-wrap {
                width: 100%;
            }

            .smark-email-editor-field .wp-editor-container {
                border: 0 !important;
                border-radius: 0;
                overflow: visible;
                box-shadow: none;
            }

            .smark-email-editor-field .quicktags-toolbar {
                display: none;
                align-items: center;
                gap: 4px;
                flex-wrap: wrap;
                padding: 8px;
                background: #f8fafc;
                border-bottom: 1px solid rgba(148, 163, 184, 0.22);
            }

            .smark-email-editor-field .html-active .quicktags-toolbar {
                display: flex;
            }

            .smark-email-editor-field .quicktags-toolbar input.ed_button,
            .smark-email-editor-field .wp-editor-tabs button {
                width: auto;
                min-height: 28px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin: 0 2px 2px 0;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 12px;
                line-height: 1.6;
            }

            .smark-email-editor-field .wp-editor-tools {
                display: block;
            }

            .smark-email-editor-field .wp-editor-tabs {
                display: flex;
                justify-content: flex-end;
                gap: 4px;
            }

            .smark-email-editor-field textarea.wp-editor-area {
                width: 100%;
                min-height: 320px;
                border: 1px solid rgba(99, 102, 241, 0.32);
                border-radius: 12px;
                color: #111827 !important;
                -webkit-text-fill-color: #111827;
                background: #ffffff !important;
                caret-color: #111827;
            }

            .smark-email-editor-field .mce-tinymce,
            .smark-email-editor-field .mce-container,
            .smark-email-editor-field .mce-panel {
                max-width: 100%;
                box-sizing: border-box;
            }

            .smark-email-editor-field .mce-edit-area {
                border: 1px solid rgba(99, 102, 241, 0.32) !important;
                border-radius: 0 0 12px 12px;
                overflow: hidden;
            }

            .smark-email-account-form {
                display: flex;
                flex-direction: column;
                gap: 18px;
            }

            .smark-email-form-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }

            .smark-email-form-grid label,
            .smark-email-form-field {
                display: flex;
                flex-direction: column;
                gap: 8px;
                color: #1f2937;
                font-weight: 600;
            }

            .smark-email-form-grid label small,
            .smark-email-form-field small,
            .smark-email-field-note {
                color: #64748b;
                font-size: 0.86em;
                font-weight: 500;
                line-height: 1.7;
            }

            .smark-email-capacity-warning {
                border: 1px solid rgba(245, 158, 11, 0.28);
                border-radius: 10px;
                padding: 9px 11px;
                background: rgba(245, 158, 11, 0.1);
                color: #92400e;
                font-size: 0.86em;
                font-weight: 700;
                line-height: 1.7;
            }

            .smark-email-capacity-warning[hidden] {
                display: none;
            }

            .smark-email-sender-picker {
                position: relative;
            }

            .smark-email-sender-picker__trigger {
                width: 100%;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 14px;
                padding: 9px 12px;
                color: #1f2937;
                background: #ffffff;
                box-shadow: none;
                cursor: pointer;
                text-align: left;
            }

            .smark-email-sender-picker__trigger span:first-child {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .smark-email-sender-picker.is-open .smark-email-sender-picker__trigger,
            .smark-email-sender-picker__trigger:focus {
                border-color: #6366f1;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
                outline: none;
            }

            .smark-email-sender-picker__panel {
                position: absolute;
                top: calc(100% + 8px);
                left: 0;
                right: 0;
                z-index: 50;
                max-height: 260px;
                overflow: auto;
                border: 1px solid rgba(148, 163, 184, 0.28);
                border-radius: 14px;
                background: #ffffff;
                box-shadow: 0 18px 42px rgba(15, 23, 42, 0.16);
                padding: 6px;
            }

            .smark-email-sender-picker__panel[hidden] {
                display: none;
            }

            .smark-email-sender-picker__inputs {
                display: none;
            }

            .smark-email-sender-picker__list {
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .smark-email-sender-picker__list li {
                margin: 0;
            }

            .smark-email-sender-picker__option {
                display: grid !important;
                grid-template-columns: 22px minmax(0, 1fr);
                align-items: center;
                gap: 10px !important;
                padding: 9px 10px;
                border-radius: 10px;
                cursor: pointer;
                color: #1f2937;
            }

            .smark-email-sender-picker__option:hover {
                background: #f8fafc;
            }

            .smark-email-sender-picker__option input {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }

            .smark-email-sender-picker__check {
                width: 18px;
                height: 18px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid rgba(99, 102, 241, 0.42);
                border-radius: 5px;
                background: #ffffff;
            }

            .smark-email-sender-picker__option input:checked + .smark-email-sender-picker__check {
                border-color: #4f46e5;
                background: #4f46e5;
            }

            .smark-email-sender-picker__option input:checked + .smark-email-sender-picker__check::after {
                content: "";
                width: 8px;
                height: 5px;
                border-left: 2px solid #ffffff;
                border-bottom: 2px solid #ffffff;
                transform: rotate(-45deg) translate(1px, -1px);
            }

            .smark-email-sender-picker__content {
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: 2px;
            }

            .smark-email-sender-picker__content strong {
                overflow: hidden;
                color: #111827;
                font-size: 0.94em;
                font-weight: 800;
                line-height: 1.35;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .smark-email-sender-picker__content small {
                color: #16a34a;
                font-size: 0.82em;
                font-weight: 700;
            }

            .smark-email-form-grid input,
            .smark-email-form-grid select,
            .smark-email-form-grid textarea,
            .smark-email-form-field select {
                width: 100%;
                min-height: 44px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 14px;
                padding: 9px 12px;
                color: #1f2937;
                background: #ffffff;
                box-shadow: none;
            }

            .smark-email-form-grid input:focus,
            .smark-email-form-grid select:focus,
            .smark-email-form-grid textarea:focus,
            .smark-email-form-field select:focus {
                border-color: #6366f1;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
                outline: none;
            }

            .smark-email-form-grid textarea {
                resize: vertical;
            }

            .smark-email-form-field--wide {
                grid-column: 1 / -1;
            }

            .smark-email-help {
                margin: 0;
                color: #64748b;
                font-size: 0.92em;
                line-height: 1.8;
            }

            .smark-email-test-send-box {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin: 16px 0 0;
                padding: 14px 16px;
                border: 1px solid rgba(79, 70, 229, 0.18);
                border-radius: 14px;
                background: rgba(79, 70, 229, 0.06);
            }

            .smark-email-test-send-box[hidden] {
                display: none;
            }

            .smark-email-test-send-box strong {
                display: block;
                margin-bottom: 4px;
                color: #111827;
                font-size: 14px;
            }

            .smark-email-test-send-box p {
                margin: 0;
                color: #475569;
                font-size: 13px;
                line-height: 1.7;
            }

            .smark-email-test-send-recipient {
                display: grid;
                gap: 6px;
                max-width: 360px;
                margin-top: 8px;
            }

            .smark-email-test-send-recipient span {
                color: #312e81;
                font-size: 12px;
                font-weight: 800;
            }

            .smark-email-test-send-recipient input {
                width: 100%;
                min-height: 38px;
                border: 1px solid rgba(99, 102, 241, 0.22);
                border-radius: 12px;
                padding: 8px 11px;
                background: #ffffff;
                color: #111827;
                font-weight: 700;
                direction: ltr;
                unicode-bidi: plaintext;
            }

            .smark-email-test-send-box__actions {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 8px;
                flex: 0 0 auto;
                flex-wrap: wrap;
            }

            .smark-email-form-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                justify-content: flex-start;
            }

            .smark-seo-optimization-page.rtl .smark-email-form-actions {
                justify-content: flex-end;
            }

            .smark-seo-optimization-page[data-lang="en"] #smarkEmailCampaignMessageForm .smark-email-test-send-action {
                margin-left: auto;
            }

            .smark-email-form-actions .button-primary {
                min-height: 42px;
                padding: 0 22px;
                border-radius: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                font-weight: 600;
                color: #ffffff;
                box-shadow: 0 14px 30px rgba(99, 102, 241, 0.22);
            }

            .smark-email-form-actions .button-primary:hover,
            .smark-email-form-actions .button-primary:focus,
            .smark-email-page-actions .button-primary:hover,
            .smark-email-page-actions .button-primary:focus,
            .smark-email-card-header-actions .button-primary:hover,
            .smark-email-card-header-actions .button-primary:focus,
            .smark-email-saved-contacts-header .button-primary:hover,
            .smark-email-saved-contacts-header .button-primary:focus,
            .smark-email-secondary-action:hover,
            .smark-email-secondary-action:focus,
            .smark-email-test-send-action:hover,
            .smark-email-test-send-action:focus,
            .smark-email-send-button:hover,
            .smark-email-send-button:focus,
            .smark-email-edit-button:hover,
            .smark-email-edit-button:focus,
            .smark-email-performance-button:hover,
            .smark-email-performance-button:focus,
            .smark-email-delete-button:hover,
            .smark-email-delete-button:focus {
                background: linear-gradient(135deg, #5b6ee8 0%, #6d43a0 100%) !important;
                color: #ffffff !important;
                border-color: transparent !important;
                box-shadow: 0 16px 32px rgba(99, 102, 241, 0.28) !important;
            }

            .smark-email-secondary-action,
            .smark-email-test-send-action,
            .smark-email-send-button,
            .smark-email-edit-button,
            .smark-email-performance-button,
            .smark-email-delete-button {
                min-height: 42px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 18px;
                border-radius: 12px;
                border: none !important;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                color: #ffffff !important;
                font-weight: 700;
                text-decoration: none !important;
                box-shadow: 0 12px 24px rgba(99, 102, 241, 0.2) !important;
            }

            .smark-email-action-row {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .smark-email-campaign-action-row {
                flex-wrap: nowrap;
                gap: 6px;
                white-space: nowrap;
            }

            .smark-email-campaign-action-row .button {
                min-height: 34px;
                padding: 0 12px;
                border-radius: 9px;
                font-size: 12px;
                line-height: 34px;
            }

            .smark-email-inline-action {
                display: inline-flex;
                margin: 0;
                vertical-align: middle;
            }

            .smark-seo-optimization-page.rtl .smark-email-inline-action {
                margin: 0;
            }

            .smark-email-metrics-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
            }

            .smark-email-metrics-grid--detail {
                margin-top: 18px;
            }

            .smark-email-metric-card {
                min-height: 132px;
                border: 1px solid rgba(148, 163, 184, 0.22);
                border-radius: 14px;
                padding: 18px;
                background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.92));
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                gap: 10px;
            }

            .smark-email-metric-card--button {
                width: 100%;
                text-align: start;
                cursor: pointer;
            }

            .smark-email-metric-card--button:hover,
            .smark-email-metric-card--button:focus,
            .smark-email-metric-card--button.is-active {
                border-color: rgba(99, 102, 241, 0.5);
                box-shadow: 0 16px 36px rgba(99, 102, 241, 0.14);
                outline: none;
            }

            .smark-email-metric-card span,
            .smark-email-performance-summary span {
                color: #64748b;
                font-size: 0.9em;
                font-weight: 700;
            }

            .smark-email-metric-card strong {
                color: #111827;
                font-size: 2.1em;
                line-height: 1.1;
                font-weight: 800;
            }

            .smark-email-metric-card small,
            .smark-email-performance-summary small {
                color: #64748b;
                line-height: 1.7;
            }

            .smark-email-performance-filter {
                margin-bottom: 18px;
            }

            .smark-email-performance-summary {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
                margin-top: 18px;
            }

            .smark-email-performance-summary > div {
                border: 1px solid rgba(99, 102, 241, 0.18);
                border-radius: 14px;
                background: rgba(99, 102, 241, 0.05);
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .smark-email-performance-summary strong {
                color: #111827;
                font-size: 1.05em;
            }

            .smark-email-activity-table {
                margin-top: 18px;
            }

            @media (max-width: 960px) {
                .smark-email-metrics-grid,
                .smark-email-performance-summary,
                .smark-email-management-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .smark-email-metrics-grid,
                .smark-email-performance-summary,
                .smark-email-management-grid {
                    grid-template-columns: 1fr;
                }
            }

            .smark-email-import-upload {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 14px;
                align-items: end;
            }

            .smark-email-import-upload label {
                display: flex;
                flex-direction: column;
                gap: 8px;
                color: #1f2937;
                font-weight: 600;
            }

            .smark-email-import-upload input[type="file"] {
                width: 100%;
                min-height: 44px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 14px;
                padding: 9px 12px;
                background: #ffffff;
            }

            .smark-email-import-mapping {
                margin-top: 24px;
                padding-top: 22px;
                border-top: 1px solid rgba(148, 163, 184, 0.18);
            }

            .smark-email-import-mapping h3 {
                margin: 0 0 8px;
                color: #111827;
                font-size: 1.08rem;
                font-weight: 800;
            }

            .smark-email-import-map-grid {
                margin-top: 16px;
            }

            .smark-email-import-map-table-wrap {
                overflow-x: auto;
                margin-top: 16px;
            }

            .smark-email-import-map-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                overflow: hidden;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 16px;
                background: #ffffff;
            }

            .smark-email-import-map-table th,
            .smark-email-import-map-table td {
                padding: 13px 16px;
                text-align: start;
                border-bottom: 1px solid rgba(148, 163, 184, 0.16);
                color: #1f2937;
                vertical-align: middle;
            }

            .smark-email-import-map-table th {
                background: #f8fafc;
                color: #475569;
                font-weight: 800;
            }

            .smark-email-import-map-table tr:last-child td {
                border-bottom: none;
            }

            .smark-email-import-map-table td small {
                display: block;
                margin-top: 4px;
                color: #7c3aed;
                font-size: 0.82em;
                font-weight: 700;
            }

            .smark-email-import-map-table select,
            .smark-email-import-map-table input {
                width: 100%;
                min-height: 40px;
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 12px;
                padding: 8px 12px;
                background: #ffffff;
                color: #1f2937;
            }

            .smark-email-import-preview {
                overflow-x: auto;
                max-height: 280px;
                border-radius: 16px;
            }

            .smark-email-import-modal {
                position: fixed;
                inset: 0;
                z-index: 100000;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 28px;
            }

            .smark-email-import-modal.is-open {
                display: flex;
            }

            .smark-email-import-modal__overlay {
                position: absolute;
                inset: 0;
                background: rgba(15, 23, 42, 0.42);
                backdrop-filter: blur(4px);
            }

            .smark-email-import-modal__dialog {
                position: relative;
                z-index: 1;
                width: min(980px, calc(100vw - 40px));
                max-height: min(82vh, 760px);
                overflow: hidden;
                display: flex;
                flex-direction: column;
                background: rgba(255, 255, 255, 0.98);
                border: 1px solid rgba(148, 163, 184, 0.24);
                border-radius: 18px;
                box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28);
            }

            .smark-email-campaign-edit-modal__dialog {
                width: min(1120px, calc(100vw - 40px));
                max-height: min(88vh, 900px);
            }

            .smark-email-import-modal__header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                padding: 22px 24px;
                border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            }

            .smark-email-import-modal__header h2 {
                margin: 0 0 8px;
                color: #111827;
                font-size: 1.28rem;
                font-weight: 900;
            }

            .smark-email-import-modal__header p {
                margin: 0;
                color: #64748b;
                line-height: 1.8;
            }

            .smark-email-import-modal__close {
                width: 38px;
                height: 38px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid rgba(148, 163, 184, 0.28);
                border-radius: 12px;
                background: #ffffff;
                color: #475569;
                cursor: pointer;
            }

            .smark-email-import-modal__body {
                padding: 22px 24px 24px;
                overflow: auto;
            }

            .smark-email-account-edit-modal__dialog {
                width: min(920px, calc(100vw - 40px));
            }

            .smark-email-account-edit-modal .smark-email-form-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .smark-email-send-progress-modal__dialog {
                width: min(760px, calc(100vw - 40px));
            }

            .smark-email-campaign-performance-modal__dialog {
                width: min(1120px, calc(100vw - 40px));
            }

            .smark-email-failure-retry-modal__dialog {
                width: min(760px, calc(100vw - 40px));
            }

            .smark-email-failure-retry-form {
                margin-bottom: 18px;
            }

            .smark-email-failure-retry-form input[type="number"],
            .smark-email-failure-retry-form select {
                height: 44px;
                line-height: 22px;
                padding-top: 10px;
                padding-bottom: 10px;
            }

            .smark-email-failure-retry-form select {
                line-height: 1.4;
                vertical-align: middle;
            }

            .smark-email-failure-retry-form .smark-email-form-actions {
                margin-top: 18px;
            }

            .smark-email-failure-retry-progress[hidden],
            .smark-email-failure-retry-reports[hidden] {
                display: none;
            }

            .smark-email-send-progress {
                display: grid;
                gap: 12px;
            }

            .smark-email-send-progress__meta {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                color: #111827;
                font-weight: 800;
            }

            .smark-email-send-progress__meta span {
                color: #4f46e5;
                font-size: 1rem;
            }

            .smark-email-send-progress__bar {
                height: 14px;
                overflow: hidden;
                border-radius: 999px;
                background: rgba(99, 102, 241, 0.12);
                box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.12);
            }

            .smark-email-send-progress__bar span {
                display: block;
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                transition: width 0.25s ease;
            }

            .smark-email-send-progress__count {
                margin: 0;
                color: #64748b;
                font-size: 13px;
                font-weight: 700;
            }

            .smark-email-send-progress__recent {
                margin-top: 22px;
                padding-top: 18px;
                border-top: 1px solid rgba(148, 163, 184, 0.2);
            }

            .smark-email-send-progress__recent h3 {
                margin: 0 0 12px;
                color: #111827;
                font-size: 1rem;
                font-weight: 900;
            }

            .smark-email-send-progress__recent ul {
                display: grid;
                gap: 8px;
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .smark-email-send-progress__recent li {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 9px 11px;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 10px;
                background: #f8fafc;
                color: #475569;
                font-size: 13px;
            }

            .smark-email-send-progress__recent li strong {
                color: #111827;
                direction: ltr;
                unicode-bidi: plaintext;
                overflow-wrap: anywhere;
            }

            .smark-email-send-progress__recent li > span {
                color: #64748b;
                font-weight: 700;
            }

            .smark-email-send-progress__recent-meta {
                display: inline-flex;
                align-items: center;
                justify-content: flex-end;
                gap: 8px;
                flex: 0 0 auto;
            }

            .smark-email-send-progress__recent p {
                margin: 12px 0 0;
                color: #64748b;
                font-size: 13px;
                line-height: 1.7;
            }

            .smark-email-failure-detail-trigger {
                border: 0;
                cursor: pointer;
                font: inherit;
            }

            .smark-email-failure-detail-trigger:hover,
            .smark-email-failure-detail-trigger:focus {
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.16);
                outline: none;
            }

            .smark-email-failure-detail-modal__dialog {
                width: min(720px, calc(100vw - 40px));
            }

            .smark-email-failure-detail-text {
                margin: 0;
                white-space: pre-wrap;
                direction: ltr;
                unicode-bidi: plaintext;
                color: #1f2937;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
                font-size: 13px;
                line-height: 1.8;
            }

            body.smark-email-modal-open {
                overflow: hidden;
            }

            .smark-notification {
                position: fixed;
                top: 44px;
                right: 24px;
                z-index: 100001;
                width: min(464px, calc(100vw - 40px));
                min-height: 70px;
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 14px 14px 14px 16px;
                border-radius: 15px;
                background: rgba(255, 255, 255, 0.72);
                color: #0f172a;
                border: 1.5px solid rgba(148, 163, 184, 0.34);
                box-shadow: 0 22px 58px rgba(15, 23, 42, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.62);
                backdrop-filter: blur(18px) saturate(150%);
                -webkit-backdrop-filter: blur(18px) saturate(150%);
                transform: translateY(-12px) scale(0.98);
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.22s ease, transform 0.22s ease;
                font-family: "Vazirmatn", -apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, sans-serif;
            }

            .smark-notification.visible {
                opacity: 1;
                transform: translateY(0) scale(1);
                pointer-events: auto;
            }

            .smark-notification.success {
                border-color: rgba(34, 197, 94, 0.78);
                background: linear-gradient(135deg, rgba(240, 253, 244, 0.82), rgba(255, 255, 255, 0.68));
            }

            .smark-notification.error {
                border-color: rgba(248, 113, 113, 0.82);
                background: linear-gradient(135deg, rgba(254, 242, 242, 0.84), rgba(255, 255, 255, 0.68));
            }

            .smark-notification.info {
                border-color: rgba(59, 130, 246, 0.78);
                background: linear-gradient(135deg, rgba(239, 246, 255, 0.84), rgba(255, 255, 255, 0.68));
            }

            .smark-notification.warning {
                border-color: rgba(245, 158, 11, 0.82);
                background: linear-gradient(135deg, rgba(255, 251, 235, 0.86), rgba(255, 255, 255, 0.68));
            }

            .smark-notification.rtl {
                direction: rtl;
                right: 24px;
                left: auto;
            }

            .smark-notification__body {
                flex: 1;
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: 3px;
                line-height: 1.55;
            }

            .smark-notification__title {
                color: #111827;
                font-size: 14px;
                font-weight: 900;
            }

            .smark-notification__message {
                color: #64748b;
                font-size: 13px;
                font-weight: 600;
            }

            .smark-notification__close {
                width: 34px;
                height: 34px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 34px;
                border: 1px solid transparent;
                border-radius: 10px;
                background: transparent;
                color: #475569;
                cursor: pointer;
                transition: background 0.16s ease, color 0.16s ease, border-color 0.16s ease;
            }

            .smark-notification__close:hover,
            .smark-notification__close:focus-visible {
                background: rgba(15, 23, 42, 0.06);
                border-color: rgba(148, 163, 184, 0.24);
                color: #111827;
                outline: none;
            }

            .smark-notification__close .dashicons,
            .smark-notification__close .dashicons:before {
                width: 20px;
                height: 20px;
                font-size: 20px;
            }

            .smark-notification__icon {
                width: 39px;
                height: 39px;
                flex: 0 0 39px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 999px;
                color: #ffffff;
                font-size: 20px;
                font-weight: 900;
                line-height: 1;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.34), 0 8px 20px rgba(15, 23, 42, 0.12);
            }

            .smark-notification.success .smark-notification__icon {
                background: linear-gradient(135deg, #5ee787, #22c55e);
            }

            .smark-notification.info .smark-notification__icon {
                background: linear-gradient(135deg, #60a5fa, #2563eb);
            }

            .smark-notification.warning .smark-notification__icon {
                background: linear-gradient(135deg, #facc15, #f59e0b);
            }

            .smark-notification.error .smark-notification__icon {
                background: linear-gradient(135deg, #fb7185, #ef4444);
            }

            .smark-email-notice,
            .smark-email-empty {
                background: rgba(255, 255, 255, 0.85);
                border: 1px solid rgba(148, 163, 184, 0.2);
                border-radius: 16px;
                padding: 14px 18px;
                color: #1f2937;
                margin: 0 0 18px;
            }

            .smark-email-notice.state-success {
                border-color: rgba(22, 163, 74, 0.35);
                color: #15803d;
            }

            .smark-email-notice.state-error {
                border-color: rgba(220, 38, 38, 0.35);
                color: #b91c1c;
            }

            .smark-email-table-wrap {
                overflow-x: auto;
            }

            .smark-email-contacts-list-loading {
                opacity: 0.65;
                pointer-events: none;
                transition: opacity 0.2s ease;
            }

            .smark-email-campaign-activity-loading {
                opacity: 0.65;
                pointer-events: none;
                transition: opacity 0.2s ease;
            }

            .smark-email-accounts-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                overflow: hidden;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 16px;
                background: #ffffff;
            }

            .smark-email-accounts-table th,
            .smark-email-accounts-table td {
                padding: 14px 16px;
                text-align: start;
                border-bottom: 1px solid rgba(148, 163, 184, 0.16);
                color: #1f2937;
                vertical-align: middle;
            }

            .smark-email-accounts-table th {
                background: #f8fafc;
                color: #475569;
                font-weight: 700;
            }

            .smark-email-accounts-table tr:last-child td {
                border-bottom: none;
            }

            .smark-email-accounts-table td small {
                display: block;
                margin-top: 5px;
                color: #64748b;
            }

            .smark-email-pagination {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-top: 18px;
                flex-wrap: wrap;
            }

            .smark-email-pagination__summary {
                color: #64748b;
                font-size: 13px;
                font-weight: 600;
            }

            .smark-email-pagination__links {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 6px;
                flex-wrap: wrap;
            }

            .smark-email-pagination__button.button {
                min-height: 34px;
                border-color: rgba(37, 99, 235, 0.24);
                border-radius: 10px;
                color: #1d4ed8;
                font-weight: 700;
            }

            .smark-email-pagination__button.button.is-active,
            .smark-email-pagination__button.button.is-active:disabled {
                background: #4f46e5;
                border-color: #4f46e5;
                color: #ffffff;
                opacity: 1;
            }

            .smark-email-pagination__ellipsis {
                color: #94a3b8;
                padding: 0 4px;
                font-weight: 700;
            }

            .smark-email-daily-usage {
                display: inline-flex;
                align-items: baseline;
                gap: 5px;
                font-weight: 800;
                white-space: nowrap;
            }

            .smark-email-daily-usage__sent {
                color: #16a34a;
                cursor: help;
            }

            .smark-email-daily-usage.is-limit-reached .smark-email-daily-usage__sent {
                color: #dc2626;
            }

            .smark-email-daily-usage__separator {
                color: #94a3b8;
                font-weight: 700;
            }

            .smark-email-daily-usage__limit {
                color: #111827;
                cursor: help;
            }

            .smark-email-event-badges,
            .smark-email-event-links,
            .smark-email-event-times {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }

            .smark-email-event-links,
            .smark-email-event-times {
                color: #475569;
                font-size: 0.9em;
                line-height: 1.55;
            }

            .smark-email-event-links span,
            .smark-email-event-times span {
                overflow-wrap: anywhere;
            }

            .smark-email-status {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 4px 10px;
                border-radius: 999px;
                background: rgba(22, 163, 74, 0.12);
                color: #15803d;
                font-weight: 700;
                font-size: 0.85em;
            }

            .smark-email-status--lead {
                background: rgba(37, 99, 235, 0.12);
                color: #1d4ed8;
            }

            .smark-email-status--sent {
                background: rgba(22, 163, 74, 0.12);
                color: #15803d;
            }

            .smark-email-status--open {
                background: rgba(34, 197, 94, 0.12);
                color: #15803d;
            }

            .smark-email-status--click {
                background: rgba(79, 70, 229, 0.12);
                color: #4338ca;
            }

            .smark-email-status--failed,
            .smark-email-status--unsubscribed {
                background: rgba(220, 38, 38, 0.12);
                color: #b91c1c;
            }

            @media (max-width: 768px) {
                .smark-email-form-grid {
                    grid-template-columns: 1fr;
                }

                .smark-email-import-upload {
                    grid-template-columns: 1fr;
                }

                .smark-email-card-header-actions {
                    flex-direction: column;
                }

                .smark-seo-optimization-page[data-lang="en"] .smark-email-account-form-header,
                .smark-email-account-form-header {
                    flex-direction: column;
                    align-items: stretch;
                }

                .smark-email-provider-switch {
                    justify-content: flex-start;
                    flex-wrap: wrap;
                }

                .smark-email-import-modal {
                    padding: 14px;
                }

                .smark-email-import-modal__dialog {
                    width: calc(100vw - 28px);
                    max-height: calc(100vh - 28px);
                }

                .smark-email-account-edit-modal .smark-email-form-grid {
                    grid-template-columns: 1fr;
                }
            }
        ';
    }
}
