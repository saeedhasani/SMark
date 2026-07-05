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
    const OPTION_CAMPAIGN_MESSAGES = 'smark_email_marketing_campaign_messages';
    const OPTION_CAMPAIGN_EVENTS = 'smark_email_marketing_campaign_events';
    const EMAIL_SECRET_PREFIX = 'smarkenc:v1:';

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
        add_action('admin_post_smark_email_campaign_message_save', array($this, 'handle_campaign_message_save'));
        add_action('admin_post_smark_email_campaign_message_delete', array($this, 'handle_campaign_message_delete'));
        add_action('admin_post_smark_email_campaign_message_send', array($this, 'handle_campaign_message_send'));
        add_action('admin_post_smark_email_contacts_import_preview', array($this, 'handle_contacts_import_preview'));
        add_action('admin_post_smark_email_contacts_import', array($this, 'handle_contacts_import'));
        add_action('wp_ajax_smark_email_contacts_import_preview', array($this, 'ajax_contacts_import_preview'));
        add_action('wp_ajax_smark_email_contacts_import', array($this, 'ajax_contacts_import'));
        add_action('wp_ajax_smark_email_campaign_message_send', array($this, 'ajax_campaign_message_send'));
        add_action('wp_ajax_smark_email_campaign_message_quick_send', array($this, 'ajax_campaign_message_quick_send'));
        add_action('wp_ajax_smark_email_track_open', array($this, 'track_campaign_open'));
        add_action('wp_ajax_nopriv_smark_email_track_open', array($this, 'track_campaign_open'));
        add_action('wp_ajax_smark_email_track_click', array($this, 'track_campaign_click'));
        add_action('wp_ajax_nopriv_smark_email_track_click', array($this, 'track_campaign_click'));
        add_action('template_redirect', array($this, 'maybe_handle_public_campaign_tracking'), 0);
    }

    public function add_submenu_page() {
        add_submenu_page(
            null,
            __('Email Marketing', 'smark'),
            __('Email Marketing', 'smark'),
            'smark_access',
            'smark-email-marketing',
            array($this, 'render_page')
        );

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
            __('Audience Segments', 'smark'),
            __('Audience Segments', 'smark'),
            'smark_access',
            'smark-email-contacts',
            array($this, 'render_contacts_page')
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
        if (!in_array($hook, array('admin_page_smark-email-marketing', 'admin_page_smark-email-accounts', 'admin_page_smark-email-contacts', 'admin_page_smark-email-campaign-message', 'admin_page_smark-email-performance'), true)) {
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

        $lang = get_option('smark_panel_language', 'en');
        $lang = ($lang === 'fa') ? 'fa' : 'en';
        wp_localize_script('smark-email-marketing', 'smarkSeoOptimization', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('smark_seo_nonce'),
            'contactsImportNonce' => wp_create_nonce('smark_email_contacts_import_ajax'),
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
                <section class="seo-step-card seo-step-card--full" data-step="strategy">
                    <header class="seo-step-header smark-email-account-form-header">
                        <div>
                            <h2 data-smark-provider-text data-email-text="<?php echo esc_attr($strings['form_title_email']); ?>" data-gmail-text="<?php echo esc_attr($strings['form_title_gmail']); ?>"><?php echo esc_html($strings['form_title_email']); ?></h2>
                            <p data-smark-provider-text data-email-text="<?php echo esc_attr($strings['form_description_email']); ?>" data-gmail-text="<?php echo esc_attr($strings['form_description_gmail']); ?>"><?php echo esc_html($strings['form_description_email']); ?></p>
                        </div>
                        <div class="smark-email-provider-switch">
                            <label for="smark_email_provider"><?php echo esc_html($strings['provider_label']); ?></label>
                            <select id="smark_email_provider" name="provider" form="smarkEmailAccountForm">
                                <option value="email"><?php echo esc_html($strings['provider_email']); ?></option>
                                <option value="gmail"><?php echo esc_html($strings['provider_gmail']); ?></option>
                            </select>
                        </div>
                    </header>

                    <form id="smarkEmailAccountForm" class="smark-email-account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('smark_email_account_save', 'smark_email_account_nonce'); ?>
                        <input type="hidden" name="action" value="smark_email_account_save">

                        <div class="smark-email-form-grid">
                            <label>
                                <span><?php echo esc_html($strings['field_label']); ?></span>
                                <input type="text" name="account_label" required placeholder="<?php echo esc_attr($strings['field_label_placeholder_email']); ?>" data-email-placeholder="<?php echo esc_attr($strings['field_label_placeholder_email']); ?>" data-gmail-placeholder="<?php echo esc_attr($strings['field_label_placeholder_gmail']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_sender_name']); ?></span>
                                <input type="text" name="sender_name" required placeholder="<?php echo esc_attr($strings['field_sender_name_placeholder']); ?>">
                            </label>

                            <label>
                                <span data-smark-provider-text data-email-text="<?php echo esc_attr($strings['field_email_email']); ?>" data-gmail-text="<?php echo esc_attr($strings['field_email_gmail']); ?>"><?php echo esc_html($strings['field_email_email']); ?></span>
                                <input type="email" name="email_address" required placeholder="name@example.com" data-email-placeholder="name@example.com" data-gmail-placeholder="name@gmail.com">
                            </label>

                            <label>
                                <span>
                                    <span data-smark-provider-text data-email-text="<?php echo esc_attr($strings['field_password_email']); ?>" data-gmail-text="<?php echo esc_attr($strings['field_password_gmail']); ?>"><?php echo esc_html($strings['field_password_email']); ?></span>
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
                                </span>
                                <input type="password" name="app_password" required autocomplete="new-password" placeholder="<?php echo esc_attr($strings['field_password_placeholder_email']); ?>" data-email-placeholder="<?php echo esc_attr($strings['field_password_placeholder_email']); ?>" data-gmail-placeholder="<?php echo esc_attr($strings['field_password_placeholder_gmail']); ?>">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_daily_limit']); ?></span>
                                <input type="number" name="daily_limit" required min="1" max="2000" value="100">
                            </label>

                            <label>
                                <span><?php echo esc_html($strings['field_smtp_host']); ?></span>
                                <input type="text" name="smtp_host" required placeholder="mail.example.com" data-email-value="" data-gmail-value="smtp.gmail.com">
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

                        <p class="smark-email-help" data-smark-provider-text data-email-text="<?php echo esc_attr($strings['email_help']); ?>" data-gmail-text="<?php echo esc_attr($strings['gmail_help']); ?>"><?php echo esc_html($strings['email_help']); ?></p>

                        <div class="smark-email-form-actions">
                            <button type="submit" class="button button-primary"><?php echo esc_html($strings['save_button']); ?></button>
                        </div>
                    </form>
                </section>

                <section class="seo-step-card seo-step-card--full smark-email-accounts-card" data-step="strategy">
                    <header class="seo-step-header">
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
                                            <td><?php echo esc_html(number_format_i18n((int) $account['daily_limit'])); ?></td>
                                            <td><span class="smark-email-status"><?php echo esc_html($strings['status_active']); ?></span></td>
                                            <td>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js($strings['delete_confirm']); ?>');">
                                                    <?php wp_nonce_field('smark_email_account_delete', 'smark_email_account_nonce'); ?>
                                                    <input type="hidden" name="action" value="smark_email_account_delete">
                                                    <input type="hidden" name="account_id" value="<?php echo esc_attr($account['id']); ?>">
                                                    <button type="submit" class="button button-link-delete"><?php echo esc_html($strings['delete_button']); ?></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <?php $this->render_version_footer($current_lang); ?>
        </div>
        <?php
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
                <section class="seo-step-card seo-step-card--full" data-step="strategy">
                    <header class="seo-step-header smark-email-card-header-actions">
                        <div>
                            <h2><?php echo esc_html($strings['form_title']); ?></h2>
                            <p><?php echo esc_html($strings['form_description']); ?></p>
                        </div>
                        <button type="button" class="button button-primary smark-email-open-import" data-open-smark-import>
                            <?php echo esc_html($strings['bulk_button']); ?>
                        </button>
                    </header>

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
                                <input type="text" name="segment" placeholder="<?php echo esc_attr($strings['field_segment_placeholder']); ?>">
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
                </section>

                <section class="seo-step-card seo-step-card--full smark-email-accounts-card" data-step="strategy">
                    <header class="seo-step-header">
                        <div>
                            <h2><?php echo esc_html($strings['list_title']); ?></h2>
                            <p><?php echo esc_html($strings['list_description']); ?></p>
                        </div>
                    </header>

                    <div id="smarkEmailContactsList">
                        <?php $this->render_contacts_list_content($strings, $contacts); ?>
                    </div>
                </section>
            </div>

            <?php $this->render_contacts_import_modal($strings, $import_token, $import_preview); ?>

            <?php $this->render_version_footer($current_lang); ?>
        </div>
        <?php
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
        $segments = $this->get_contact_segments($contacts);
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
                'subject_line'      => '',
                'preview_text'      => '',
                'reply_to'          => '',
                'message_status'    => 'draft',
                'target_segments'   => array(),
                'target_contacts'   => array(),
                'email_body'        => '',
                'internal_notes'    => '',
            ),
            $editing_message
        );
        if (empty($form_values['sender_account_id']) && !empty($accounts[0]['id'])) {
            $form_values['sender_account_id'] = (string) $accounts[0]['id'];
        }
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
                <section class="seo-step-card seo-step-card--full" data-step="strategy">
                    <header class="seo-step-header">
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

                            <label>
                                <span><?php echo esc_html($strings['field_sender_account']); ?></span>
                                <select name="sender_account_id">
                                    <option value=""><?php echo esc_html($strings['field_sender_account_empty']); ?></option>
                                    <?php foreach ($accounts as $account) : ?>
                                        <option value="<?php echo esc_attr($account['id']); ?>" <?php selected($form_values['sender_account_id'], $account['id']); ?>>
                                            <?php echo esc_html($account['account_label'] . ' - ' . $account['email_address']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

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
                                    <option value="ready" <?php selected($form_values['message_status'], 'ready'); ?>><?php echo esc_html($strings['status_ready']); ?></option>
                                </select>
                            </label>

                            <label class="smark-email-audience-field">
                                <span><?php echo esc_html($strings['field_segments']); ?></span>
                                <select name="target_segments[]" multiple size="7">
                                    <?php foreach ($segments as $segment) : ?>
                                        <option value="<?php echo esc_attr($segment); ?>" <?php selected(in_array($segment, (array) $form_values['target_segments'], true)); ?>><?php echo esc_html($segment); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="smark-email-field-note"><?php echo esc_html($strings['field_segments_help']); ?></small>
                            </label>

                            <label class="smark-email-audience-field">
                                <span><?php echo esc_html($strings['field_contacts']); ?></span>
                                <select name="target_contacts[]" multiple size="7">
                                    <?php foreach ($contacts as $contact) : ?>
                                        <?php
                                        $contact_name = trim((string) ($contact['first_name'] ?? '') . ' ' . (string) ($contact['last_name'] ?? ''));
                                        $contact_label = ($contact_name !== '' ? $contact_name . ' - ' : '') . (string) ($contact['email_address'] ?? '');
                                        ?>
                                        <option value="<?php echo esc_attr($contact['id']); ?>" <?php selected(in_array((string) $contact['id'], (array) $form_values['target_contacts'], true)); ?>><?php echo esc_html($contact_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="smark-email-field-note"><?php echo esc_html($strings['field_contacts_help']); ?></small>
                            </label>

                            <label class="smark-email-form-field--wide">
                                <span><?php echo esc_html($strings['field_body']); ?></span>
                                <textarea name="email_body" rows="12" required placeholder="<?php echo esc_attr($strings['field_body_placeholder']); ?>"><?php echo esc_textarea($form_values['email_body']); ?></textarea>
                            </label>

                            <label class="smark-email-form-field--wide">
                                <span><?php echo esc_html($strings['field_notes']); ?></span>
                                <textarea name="internal_notes" rows="3" placeholder="<?php echo esc_attr($strings['field_notes_placeholder']); ?>"><?php echo esc_textarea($form_values['internal_notes']); ?></textarea>
                            </label>
                        </div>

                        <p class="smark-email-help"><?php echo esc_html($strings['form_help']); ?></p>

                        <div class="smark-email-form-actions">
                            <button type="submit" name="campaign_action" value="save" class="button button-primary"><?php echo esc_html($is_editing ? $strings['update_button'] : $strings['save_button']); ?></button>
                            <button type="submit" name="campaign_action" value="send_now" class="button smark-email-secondary-action"><?php echo esc_html($strings['quick_send_button']); ?></button>
                        </div>
                    </form>
                </section>

                <section class="seo-step-card seo-step-card--full smark-email-accounts-card" data-step="strategy">
                    <header class="seo-step-header">
                        <div>
                            <h2><?php echo esc_html($strings['list_title']); ?></h2>
                            <p><?php echo esc_html($strings['list_description']); ?></p>
                        </div>
                    </header>

                    <div id="smarkEmailCampaignMessagesList">
                        <?php $this->render_campaign_messages_list_content($strings, $messages); ?>
                    </div>
                </section>
            </div>

            <?php $this->render_version_footer($current_lang); ?>
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
        $messages = $this->get_campaign_messages();
        $selected_campaign_id = isset($_GET['campaign_id']) ? sanitize_text_field(wp_unslash($_GET['campaign_id'])) : '';

        if ($selected_campaign_id === '' && !empty($messages[0]['id'])) {
            $selected_campaign_id = (string) $messages[0]['id'];
        }

        $overall = $this->get_campaign_performance_metrics('');
        $selected_metrics = $selected_campaign_id !== '' ? $this->get_campaign_performance_metrics($selected_campaign_id) : array();
        $selected_campaign = $selected_campaign_id !== '' ? $this->get_campaign_message_by_id($selected_campaign_id) : array();
        ?>
        <div class="wrap smark-seo-optimization-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
            <?php $this->render_standard_header($strings, $current_lang, $rtl_class, true); ?>

            <div class="seo-grid">
                <section class="seo-step-card seo-step-card--full smark-email-performance-card" data-step="strategy">
                    <header class="seo-step-header smark-email-performance-header">
                        <div>
                            <h2><?php echo esc_html($strings['overview_title']); ?></h2>
                            <p><?php echo esc_html($strings['overview_description']); ?></p>
                        </div>
                    </header>

                    <div class="smark-email-metrics-grid">
                        <?php foreach ($this->get_performance_metric_cards($overall, $strings) as $metric) : ?>
                            <div class="smark-email-metric-card">
                                <span><?php echo esc_html($metric['label']); ?></span>
                                <strong><?php echo esc_html($metric['value']); ?></strong>
                                <small><?php echo esc_html($metric['help']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="seo-step-card seo-step-card--full" data-step="strategy">
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
                                <select name="campaign_id" onchange="this.form.submit()">
                                    <?php foreach ($messages as $message) : ?>
                                        <option value="<?php echo esc_attr($message['id']); ?>" <?php selected($selected_campaign_id, $message['id']); ?>>
                                            <?php echo esc_html($message['campaign_name'] . ' - ' . $message['subject_line']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </form>

                        <?php if (!empty($selected_campaign)) : ?>
                            <div class="smark-email-performance-summary">
                                <div>
                                    <span><?php echo esc_html($strings['selected_campaign']); ?></span>
                                    <strong><?php echo esc_html($selected_campaign['campaign_name']); ?></strong>
                                    <small><?php echo esc_html($selected_campaign['subject_line']); ?></small>
                                </div>
                                <div>
                                    <span><?php echo esc_html($strings['selected_status']); ?></span>
                                    <strong><?php echo esc_html($this->get_campaign_status_label($selected_campaign['message_status'], $this->get_campaign_message_strings($current_lang))); ?></strong>
                                    <small><?php echo esc_html(!empty($selected_campaign['sent_at']) ? $selected_campaign['sent_at'] : $strings['not_sent_yet']); ?></small>
                                </div>
                                <div>
                                    <span><?php echo esc_html($strings['selected_audience']); ?></span>
                                    <strong><?php echo esc_html($this->format_campaign_audience_summary($selected_campaign, $this->get_campaign_message_strings($current_lang))); ?></strong>
                                    <small><?php echo esc_html($strings['audience_help']); ?></small>
                                </div>
                            </div>

                            <div class="smark-email-metrics-grid smark-email-metrics-grid--detail">
                                <?php foreach ($this->get_performance_metric_cards($selected_metrics, $strings) as $metric) : ?>
                                    <div class="smark-email-metric-card">
                                        <span><?php echo esc_html($metric['label']); ?></span>
                                        <strong><?php echo esc_html($metric['value']); ?></strong>
                                        <small><?php echo esc_html($metric['help']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php $this->render_campaign_activity_table($selected_campaign_id, $strings); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>

            <?php $this->render_version_footer($current_lang); ?>
        </div>
        <?php
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
                            <td><span class="smark-email-status smark-email-status--<?php echo esc_attr($campaign_message['message_status']); ?>"><?php echo esc_html($this->get_campaign_status_label($campaign_message['message_status'], $strings)); ?></span></td>
                            <td>
                                <div class="smark-email-action-row">
                                    <a class="button smark-email-edit-button" href="<?php echo esc_url(add_query_arg(array('page' => 'smark-email-campaign-message', 'edit_message' => $campaign_message['id']), admin_url('admin.php'))); ?>">
                                        <?php echo esc_html($strings['edit_button']); ?>
                                    </a>
                                    <form class="smark-email-inline-action smark-email-campaign-send-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('smark_email_campaign_message_send', 'smark_email_campaign_message_nonce'); ?>
                                        <input type="hidden" name="action" value="smark_email_campaign_message_send">
                                        <input type="hidden" name="message_id" value="<?php echo esc_attr($campaign_message['id']); ?>">
                                        <button type="submit" class="button smark-email-send-button"><?php echo esc_html($strings['send_button']); ?></button>
                                    </form>
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
        <?php
    }

    private function render_contacts_import_modal($strings, $import_token, $import_preview) {
        ?>
        <div class="smark-email-import-modal" id="smarkEmailImportModal" aria-hidden="true">
            <div class="smark-email-import-modal__overlay" data-close-smark-import></div>
            <div class="smark-email-import-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="smarkEmailImportTitle">
                <header class="smark-email-import-modal__header">
                    <div>
                        <h2 id="smarkEmailImportTitle"><?php echo esc_html($strings['bulk_title']); ?></h2>
                        <p><?php echo esc_html($strings['bulk_description']); ?></p>
                    </div>
                    <button type="button" class="smark-email-import-modal__close" data-close-smark-import aria-label="<?php echo esc_attr($strings['bulk_close']); ?>">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </header>

                <div class="smark-email-import-modal__body">
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
            </div>
        </div>
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

    private function render_contacts_list_content($strings, $contacts) {
        if (empty($contacts)) : ?>
            <div class="smark-email-empty">
                <?php echo esc_html($strings['empty_state']); ?>
            </div>
        <?php else : ?>
            <div class="smark-email-table-wrap">
                <table class="smark-email-accounts-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html($strings['column_contact']); ?></th>
                            <th><?php echo esc_html($strings['column_email']); ?></th>
                            <th><?php echo esc_html($strings['column_segment']); ?></th>
                            <th><?php echo esc_html($strings['column_source']); ?></th>
                            <th><?php echo esc_html($strings['column_status']); ?></th>
                            <th><?php echo esc_html($strings['column_actions']); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact) : ?>
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
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($full_name); ?></strong>
                                    <?php if (!empty($contact['phone'])) : ?>
                                        <small><?php echo esc_html($contact['phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($contact['email_address']); ?></td>
                                <td><?php echo esc_html($contact['segment']); ?></td>
                                <td><?php echo esc_html($contact['source']); ?></td>
                                <td><span class="smark-email-status smark-email-status--<?php echo esc_attr($status); ?>"><?php echo esc_html($status_label); ?></span></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js($strings['delete_confirm']); ?>');">
                                        <?php wp_nonce_field('smark_email_contact_delete', 'smark_email_contact_nonce'); ?>
                                        <input type="hidden" name="action" value="smark_email_contact_delete">
                                        <input type="hidden" name="contact_id" value="<?php echo esc_attr($contact['id']); ?>">
                                        <button type="submit" class="button button-link-delete"><?php echo esc_html($strings['delete_button']); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif;
    }

    public function handle_email_account_save() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_account_save', 'smark_email_account_nonce');

        $email_address = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';
        if (!$email_address || !is_email($email_address)) {
            $this->redirect_to_accounts('error');
        }

        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : 'email';
        $provider = in_array($provider, array('email', 'gmail'), true) ? $provider : 'email';

        $smtp_port = isset($_POST['smtp_port']) ? absint($_POST['smtp_port']) : 587;
        $smtp_port = in_array($smtp_port, array(25, 465, 587, 2525), true) ? $smtp_port : 587;

        $encryption = isset($_POST['encryption']) ? sanitize_key(wp_unslash($_POST['encryption'])) : 'tls';
        $encryption = in_array($encryption, array('none', 'tls', 'ssl'), true) ? $encryption : 'tls';

        $daily_limit = isset($_POST['daily_limit']) ? absint($_POST['daily_limit']) : 1;
        $daily_limit = max(1, min(2000, $daily_limit));

        $app_password = isset($_POST['app_password']) ? sanitize_text_field(wp_unslash($_POST['app_password'])) : '';

        $account = array(
            'id'            => wp_generate_uuid4(),
            'provider'      => $provider,
            'account_label' => isset($_POST['account_label']) ? sanitize_text_field(wp_unslash($_POST['account_label'])) : '',
            'sender_name'   => isset($_POST['sender_name']) ? sanitize_text_field(wp_unslash($_POST['sender_name'])) : '',
            'email_address' => $email_address,
            'app_password'  => $app_password,
            'daily_limit'   => $daily_limit,
            'smtp_host'     => isset($_POST['smtp_host']) ? sanitize_text_field(wp_unslash($_POST['smtp_host'])) : '',
            'smtp_port'     => $smtp_port,
            'encryption'    => $encryption,
            'created_at'    => current_time('mysql'),
        );

        if ($account['account_label'] === '' || $account['sender_name'] === '' || $account['app_password'] === '') {
            $this->redirect_to_accounts('error');
        }

        if ($account['smtp_host'] === '') {
            if ($provider === 'gmail') {
                $account['smtp_host'] = 'smtp.gmail.com';
            } else {
                $this->redirect_to_accounts('error');
            }
        }

        $accounts = $this->get_email_accounts();
        $accounts[] = $account;
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

        $email_address = isset($_POST['email_address']) ? sanitize_email(wp_unslash($_POST['email_address'])) : '';
        if (!$email_address || !is_email($email_address)) {
            $this->redirect_to_contacts('error');
        }

        $segment = isset($_POST['segment']) ? sanitize_text_field(wp_unslash($_POST['segment'])) : '';

        $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'subscribed';
        $status = in_array($status, array('subscribed', 'lead', 'unsubscribed'), true) ? $status : 'subscribed';

        $contacts = $this->get_contacts();
        foreach ($contacts as $existing_contact) {
            if (isset($existing_contact['email_address']) && strtolower((string) $existing_contact['email_address']) === strtolower($email_address)) {
                $this->redirect_to_contacts('error');
            }
        }

        $contacts[] = array(
            'id'            => wp_generate_uuid4(),
            'first_name'    => isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '',
            'last_name'     => isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '',
            'email_address' => $email_address,
            'phone'         => isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '',
            'segment'       => $segment,
            'source'        => isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '',
            'status'        => $status,
            'notes'         => isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '',
            'created_at'    => current_time('mysql'),
        );

        update_option(self::OPTION_CONTACTS, $contacts, false);
        $this->redirect_to_contacts('saved');
    }

    public function handle_email_contact_delete() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'smark'));
        }

        check_admin_referer('smark_email_contact_delete', 'smark_email_contact_nonce');

        $contact_id = isset($_POST['contact_id']) ? sanitize_text_field(wp_unslash($_POST['contact_id'])) : '';
        $contacts = array_values(array_filter($this->get_contacts(), function($contact) use ($contact_id) {
            return isset($contact['id']) && $contact['id'] !== $contact_id;
        }));

        update_option(self::OPTION_CONTACTS, $contacts, false);
        $this->redirect_to_contacts('deleted');
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
                'page' => 'smark-email-contacts',
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
        $this->render_contacts_list_content($strings, $this->get_contacts());
        $list_html = ob_get_clean();

        wp_send_json_success(array(
            'imported' => $imported_count,
            'message' => sprintf($strings['notice_imported'], number_format_i18n($imported_count)),
            'listHtml' => $list_html,
        ));
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
        return (int) apply_filters('smark_email_open_tracking_grace_period', 120);
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

        if ($this->is_suspected_campaign_open_scanner(isset($event['user_agent']) ? (string) $event['user_agent'] : '')) {
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
            'googleimageproxy',
            'googleusercontent',
            'proxy',
            'prefetch',
            'mailprivacy',
            'mail privacy',
            'dataprovider',
        );

        foreach ($scanner_signatures as $signature) {
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
                'label' => $strings['metric_sent'],
                'value' => number_format_i18n((int) ($metrics['sent'] ?? 0)),
                'help' => $strings['metric_sent_help'],
            ),
            array(
                'label' => $strings['metric_open_rate'],
                'value' => $this->format_percentage($metrics['open_rate'] ?? 0),
                'help' => sprintf($strings['metric_open_help'], number_format_i18n((int) ($metrics['unique_opens'] ?? 0)), number_format_i18n((int) ($metrics['opens'] ?? 0))),
            ),
            array(
                'label' => $strings['metric_click_rate'],
                'value' => $this->format_percentage($metrics['click_rate'] ?? 0),
                'help' => sprintf($strings['metric_click_help'], number_format_i18n((int) ($metrics['unique_clicks'] ?? 0)), number_format_i18n((int) ($metrics['clicks'] ?? 0))),
            ),
            array(
                'label' => $strings['metric_ctor'],
                'value' => $this->format_percentage($metrics['ctor'] ?? 0),
                'help' => $strings['metric_ctor_help'],
            ),
            array(
                'label' => $strings['metric_failed'],
                'value' => number_format_i18n((int) ($metrics['failed'] ?? 0)),
                'help' => $strings['metric_failed_help'],
            ),
            array(
                'label' => $strings['metric_unsub_bounce'],
                'value' => number_format_i18n((int) ($metrics['unsubscribes'] ?? 0) + (int) ($metrics['bounces'] ?? 0)),
                'help' => sprintf($strings['metric_unsub_bounce_help'], number_format_i18n((int) ($metrics['unsubscribes'] ?? 0)), number_format_i18n((int) ($metrics['bounces'] ?? 0))),
            ),
        );
    }

    private function render_campaign_activity_table($campaign_id, $strings) {
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
                    'events' => array(),
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

            if ($type !== '') {
                if ($type === 'open' && isset($rows[$key]['events']['open'])) {
                    continue;
                }

                $rows[$key]['events'][$type] = $created_at;
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

        $rows = array_slice($rows, 0, 20);
        ?>
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
                    <?php if (empty($rows)) : ?>
                        <tr>
                            <td colspan="4"><?php echo esc_html($strings['no_events']); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td>
                                    <div class="smark-email-event-badges">
                                        <?php foreach ($row['events'] as $type => $created_at) : ?>
                                            <span class="smark-email-status smark-email-status--<?php echo esc_attr($type); ?>"><?php echo esc_html($this->get_campaign_event_label($type, $strings)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html($row['recipient_label'] !== '' ? $row['recipient_label'] : $strings['unknown_recipient']); ?></td>
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
        <?php
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
        $message_status = in_array($message_status, array('draft', 'ready'), true) ? $message_status : 'draft';

        $target_segments = isset($_POST['target_segments']) && is_array($_POST['target_segments'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['target_segments']))
            : array();
        $target_segments = array_values(array_filter(array_unique($target_segments)));

        $target_contacts = isset($_POST['target_contacts']) && is_array($_POST['target_contacts'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['target_contacts']))
            : array();
        $target_contacts = array_values(array_filter(array_unique($target_contacts)));

        $existing_message = $this->get_campaign_message_by_id($message_id);

        return array(
            'id'                => $message_id !== '' ? $message_id : wp_generate_uuid4(),
            'campaign_name'     => $campaign_name,
            'sender_account_id' => isset($_POST['sender_account_id']) ? sanitize_text_field(wp_unslash($_POST['sender_account_id'])) : '',
            'subject_line'      => $subject_line,
            'preview_text'      => isset($_POST['preview_text']) ? sanitize_text_field(wp_unslash($_POST['preview_text'])) : '',
            'reply_to'          => isset($_POST['reply_to']) ? sanitize_email(wp_unslash($_POST['reply_to'])) : '',
            'message_status'    => $message_status,
            'target_segments'   => $target_segments,
            'target_contacts'   => $target_contacts,
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
        $segment_count = isset($campaign_message['target_segments']) && is_array($campaign_message['target_segments']) ? count($campaign_message['target_segments']) : 0;
        $contact_count = count($this->resolve_campaign_contact_ids($campaign_message));

        if ($segment_count === 0 && $contact_count === 0) {
            return $strings['audience_not_selected'];
        }

        return sprintf($strings['audience_summary'], number_format_i18n($segment_count), number_format_i18n($contact_count));
    }

    private function get_campaign_status_label($status, $strings) {
        if ($status === 'ready') {
            return $strings['status_ready'];
        }

        if ($status === 'sent') {
            return $strings['status_sent'];
        }

        return $strings['status_draft'];
    }

    private function send_campaign_message($campaign_message) {
        $recipients = $this->resolve_campaign_recipients($campaign_message);
        $campaign_id = isset($campaign_message['id']) ? (string) $campaign_message['id'] : 'temporary';
        $this->campaign_mail_errors = array();

        $this->log_campaign_mail_debug('send_started', array(
            'campaign_id' => $campaign_id,
            'campaign_name' => isset($campaign_message['campaign_name']) ? (string) $campaign_message['campaign_name'] : '',
            'recipient_count' => count($recipients),
            'target_segments_count' => isset($campaign_message['target_segments']) && is_array($campaign_message['target_segments']) ? count($campaign_message['target_segments']) : 0,
            'target_contacts_count' => isset($campaign_message['target_contacts']) && is_array($campaign_message['target_contacts']) ? count($campaign_message['target_contacts']) : 0,
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

        $sender_account_id = isset($campaign_message['sender_account_id']) ? (string) $campaign_message['sender_account_id'] : '';
        $sender = $this->get_email_account_by_id($sender_account_id);
        if (empty($sender) && $sender_account_id === '') {
            $accounts = $this->get_email_accounts();
            if (!empty($accounts[0]) && is_array($accounts[0])) {
                $sender = $accounts[0];
                $sender_account_id = isset($sender['id']) ? (string) $sender['id'] : '';
                $this->log_campaign_mail_debug('default_sender_account_applied', array(
                    'campaign_id' => $campaign_id,
                    'sender_account_id' => $sender_account_id,
                    'sender_email' => isset($sender['email_address']) ? (string) $sender['email_address'] : '',
                ));
            }
        }

        $this->log_campaign_mail_debug('sender_resolved', array(
            'campaign_id' => $campaign_id,
            'sender_account_id' => $sender_account_id,
            'has_sender' => !empty($sender),
            'sender_email' => isset($sender['email_address']) ? (string) $sender['email_address'] : '',
            'smtp_host' => isset($sender['smtp_host']) ? (string) $sender['smtp_host'] : '',
            'smtp_port' => isset($sender['smtp_port']) ? (int) $sender['smtp_port'] : 0,
            'encryption' => isset($sender['encryption']) ? (string) $sender['encryption'] : '',
            'has_app_password' => !empty($sender['app_password']),
        ));

        if (empty($sender) || empty($sender['smtp_host']) || empty($sender['email_address']) || empty($sender['app_password']) || !is_email($sender['email_address'])) {
            $this->log_campaign_mail_debug('send_failed_sender_not_configured', array(
                'campaign_id' => $campaign_id,
                'sender_account_id' => $sender_account_id,
                'has_sender' => !empty($sender),
                'has_smtp_host' => !empty($sender['smtp_host']),
                'has_sender_email' => !empty($sender['email_address']),
                'has_app_password' => !empty($sender['app_password']),
            ));

            return new WP_Error('sender_not_configured', 'SMark sender account is not configured.');
        }

        $sent_count = 0;
        $html_body = $this->prepare_campaign_email_html($body);
        $this->campaign_mailer_account = $sender;
        $this->log_campaign_mail_debug('using_direct_smtp', array(
            'campaign_id' => $campaign_id,
            'smtp_host' => isset($sender['smtp_host']) ? (string) $sender['smtp_host'] : '',
            'smtp_port' => isset($sender['smtp_port']) ? (int) $sender['smtp_port'] : 0,
        ));

        try {
            foreach ($recipients as $email) {
                $recipient_hash = $this->get_campaign_recipient_hash($email);
                $recipient_body = $this->add_campaign_tracking_to_html($html_body, $campaign_id, $recipient_hash);
                $send_result = $this->send_campaign_email_via_direct_smtp($email, $subject, $recipient_body, $sender, $reply_to);

                if ($send_result === true) {
                    $sent_count++;
                    $this->record_campaign_event($campaign_id, 'sent', $email);
                    $this->log_campaign_mail_debug('recipient_sent', array(
                        'campaign_id' => $campaign_id,
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

                    $this->record_campaign_event($campaign_id, 'failed', $email);
                    $this->log_campaign_mail_debug('recipient_send_failed', array(
                        'campaign_id' => $campaign_id,
                        'recipient' => $this->mask_email_for_log($email),
                        'mail_errors' => $this->campaign_mail_errors,
                    ));
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

    private function record_campaign_event($campaign_id, $type, $recipient_email = '', $recipient_hash = '', $url = '') {
        $campaign_id = sanitize_text_field($campaign_id);
        $type = sanitize_key($type);
        $recipient_email = sanitize_email($recipient_email);
        $recipient_hash = $recipient_hash !== '' ? sanitize_text_field($recipient_hash) : $this->get_campaign_recipient_hash($recipient_email);

        if ($campaign_id === '' || $type === '') {
            return;
        }

        $events = $this->get_campaign_events();
        $events[] = array(
            'id' => wp_generate_uuid4(),
            'campaign_id' => $campaign_id,
            'type' => $type,
            'recipient_hash' => $recipient_hash,
            'recipient_label' => $recipient_email !== '' ? $this->mask_email_for_log($recipient_email) : '',
            'url' => $url !== '' ? esc_url_raw($url) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            'created_at' => current_time('mysql'),
        );

        if (count($events) > 5000) {
            $events = array_slice($events, -5000);
        }

        update_option(self::OPTION_CAMPAIGN_EVENTS, $events, false);
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
        $target_segments = isset($campaign_message['target_segments']) && is_array($campaign_message['target_segments']) ? $campaign_message['target_segments'] : array();
        $target_contacts = isset($campaign_message['target_contacts']) && is_array($campaign_message['target_contacts']) ? $campaign_message['target_contacts'] : array();
        $target_segments = array_map('strval', $target_segments);
        $target_contacts = array_map('strval', $target_contacts);

        $emails = array();
        foreach ($contacts as $contact) {
            $contact_id = isset($contact['id']) ? (string) $contact['id'] : '';
            $segment = isset($contact['segment']) ? (string) $contact['segment'] : '';
            $email = isset($contact['email_address']) ? sanitize_email($contact['email_address']) : '';

            if (!$email || !is_email($email)) {
                continue;
            }

            if (in_array($contact_id, $target_contacts, true) || ($segment !== '' && in_array($segment, $target_segments, true))) {
                $emails[strtolower($email)] = $email;
            }
        }

        return array_values($emails);
    }

    private function resolve_campaign_contact_ids($campaign_message) {
        $contacts = $this->get_contacts();
        $target_contacts = isset($campaign_message['target_contacts']) && is_array($campaign_message['target_contacts']) ? array_map('strval', $campaign_message['target_contacts']) : array();
        $contact_ids = array();

        if (empty($target_contacts)) {
            return $contact_ids;
        }

        foreach ($contacts as $contact) {
            $contact_id = isset($contact['id']) ? (string) $contact['id'] : '';
            if ($contact_id !== '' && in_array($contact_id, $target_contacts, true)) {
                $contact_ids[] = $contact_id;
            }
        }

        return array_values(array_unique($contact_ids));
    }

    private function get_campaign_send_error_message($error, $strings) {
        if ($error instanceof WP_Error && $error->get_error_code() === 'no_recipients') {
            return $strings['notice_no_recipients'];
        }

        if ($error instanceof WP_Error && $error->get_error_code() === 'sender_not_configured') {
            return isset($strings['notice_sender_not_configured']) ? $strings['notice_sender_not_configured'] : $strings['notice_send_error'];
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
        $('#smarkEmailImportModal').addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('smark-email-modal-open');
    }

    function closeImportModal() {
        $('#smarkEmailImportModal').removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('smark-email-modal-open');
    }

    function updateEmailProviderForm(provider) {
        var normalizedProvider = provider === 'gmail' ? 'gmail' : 'email';
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

        var $smtpHost = $form.find('[name="smtp_host"]');
        if ($smtpHost.length && !$smtpHost.val()) {
            $smtpHost.val($smtpHost.attr('data-' + normalizedProvider + '-value') || '');
        }

        if (normalizedProvider === 'gmail') {
            $form.find('[name="smtp_port"]').val('587');
            $form.find('[name="encryption"]').val('tls');
            $smtpHost.val($smtpHost.attr('data-gmail-value') || 'smtp.gmail.com');
        } else if ($smtpHost.val() === ($smtpHost.attr('data-gmail-value') || 'smtp.gmail.com')) {
            $smtpHost.val('');
        }
    }

    $(function () {
        var $page = $('.wrap.smark-seo-optimization-page');
        showNotification($page.attr('data-smark-notice-message') || '', $page.attr('data-smark-notice-type') || 'info');

        if ($page.attr('data-smark-open-import') === '1') {
            openImportModal();
        }

        updateEmailProviderForm($('#smark_email_provider').val() || 'email');

        $(document).on('change', '#smark_email_provider', function () {
            updateEmailProviderForm($(this).val());
        });

        $(document).on('click', '[data-open-smark-import]', function (event) {
            event.preventDefault();
            openImportModal();
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

        $(document).on('submit', '.smark-email-campaign-send-form', function (event) {
            event.preventDefault();

            var form = this;
            var $form = $(form);
            var $button = $form.find('button[type="submit"]');
            var formData = new FormData(form);

            formData.set('action', 'smark_email_campaign_message_send');
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
                    $('#smarkEmailCampaignMessagesList').html(response.data.listHtml || '');
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

        $(document).on('submit', '#smarkEmailCampaignMessageForm', function (event) {
            var submitter = event.originalEvent && event.originalEvent.submitter ? event.originalEvent.submitter : null;
            var action = submitter ? $(submitter).val() : '';

            if (action !== 'send_now') {
                return;
            }

            event.preventDefault();

            var form = this;
            var $button = $(submitter);
            var formData = new FormData(form);

            formData.set('action', 'smark_email_campaign_message_quick_send');
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
                    $('#smarkEmailCampaignMessagesList').html(response.data.listHtml || '');
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
                'page' => 'smark-email-contacts',
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
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-email-marketing')); ?>"><?php echo esc_html($strings['breadcrumb_parent']); ?></a>
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
                'form_description'              => 'حساب ارسال ایمیل را با مشخصات SMTP ثبت کنید.',
                'form_description_email'        => 'برای ارسال از ایمیل معمولی، مشخصات SMTP، رمز عبور و سقف ارسال روزانه را وارد کنید.',
                'form_description_gmail'        => 'برای ارسال از جیمیل، آدرس ایمیل، نام فرستنده، App Password و سقف ارسال روزانه لازم است.',
                'provider_label'                => 'نوع حساب',
                'provider_email'                => 'ایمیل',
                'provider_gmail'                => 'جیمیل',
                'field_label'                   => 'عنوان حساب',
                'field_label_placeholder'       => 'مثلا ایمیل فروش',
                'field_label_placeholder_email' => 'مثلا ایمیل فروش',
                'field_label_placeholder_gmail' => 'مثلا جیمیل فروش',
                'field_sender_name'             => 'نام فرستنده',
                'field_sender_name_placeholder' => 'مثلا تیم اسمارک',
                'field_email'                   => 'آدرس ایمیل',
                'field_email_email'             => 'آدرس ایمیل',
                'field_email_gmail'             => 'آدرس جیمیل',
                'field_app_password'            => 'رمز SMTP',
                'field_password_email'          => 'رمز SMTP / ایمیل',
                'field_password_gmail'          => 'App Password جیمیل',
                'gmail_app_password_link'       => 'دریافت App Password',
                'gmail_app_password_tooltip_label'=> 'راهنمای دریافت App Password جیمیل',
                'gmail_app_password_tooltip_title'=> 'راهنمای سریع',
                'gmail_app_password_tooltip_step_1'=> 'ابتدا مطمئن شوید ورود دو مرحله‌ای حساب گوگل فعال است.',
                'gmail_app_password_tooltip_step_2'=> 'در صفحه App passwords، نام برنامه را SMark وارد کنید.',
                'gmail_app_password_tooltip_step_3'=> 'کدی را که گوگل می‌دهد در همین فیلد SMark قرار دهید.',
                'field_app_password_placeholder'=> 'رمز SMTP یا رمز برنامه',
                'field_password_placeholder_email'=> 'رمز SMTP یا رمز ایمیل',
                'field_password_placeholder_gmail'=> 'رمز برنامه ۱۶ کاراکتری گوگل',
                'field_daily_limit'             => 'تعداد ارسال روزانه',
                'field_smtp_host'               => 'SMTP Host',
                'field_smtp_port'               => 'SMTP Port',
                'field_encryption'              => 'نوع رمزنگاری',
                'encryption_none'               => 'بدون رمزنگاری',
                'email_help'                    => 'برای ایمیل معمولی، اطلاعات SMTP را از هاست یا سرویس‌دهنده ایمیل خود وارد کنید.',
                'gmail_help'                    => 'برای جیمیل باید تایید دو مرحله‌ای فعال باشد و از Google Account > Security > App passwords رمز برنامه بسازید. پسورد اصلی جیمیل را وارد نکنید.',
                'save_button'                   => 'افزودن حساب',
                'list_title'                    => 'حساب‌های ثبت‌شده',
                'list_description'              => 'این حساب‌ها بعدا برای انتخاب فرستنده کمپین و کنترل سقف ارسال روزانه استفاده می‌شوند.',
                'empty_state'                   => 'هنوز حساب ایمیلی ثبت نشده است.',
                'column_label'                  => 'حساب',
                'column_email'                  => 'ایمیل',
                'column_smtp'                   => 'SMTP',
                'column_daily_limit'            => 'سقف روزانه',
                'column_status'                 => 'وضعیت',
                'column_actions'                => 'عملیات',
                'status_active'                 => 'فعال',
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
            'form_description'              => 'Add a sending account with SMTP details.',
            'form_description_email'        => 'For regular email, enter the SMTP details, password, and daily send limit.',
            'form_description_gmail'        => 'Gmail sending requires the email address, sender name, app password, and daily send limit.',
            'provider_label'                => 'Account Type',
            'provider_email'                => 'Email',
            'provider_gmail'                => 'Gmail',
            'field_label'                   => 'Account Label',
            'field_label_placeholder'       => 'Example: Sales Email',
            'field_label_placeholder_email' => 'Example: Sales Email',
            'field_label_placeholder_gmail' => 'Example: Sales Gmail',
            'field_sender_name'             => 'Sender Name',
            'field_sender_name_placeholder' => 'Example: SMark Team',
            'field_email'                   => 'Email Address',
            'field_email_email'             => 'Email Address',
            'field_email_gmail'             => 'Gmail Address',
            'field_app_password'            => 'SMTP Password',
            'field_password_email'          => 'SMTP / Email Password',
            'field_password_gmail'          => 'Gmail App Password',
            'gmail_app_password_link'       => 'Get app password',
            'gmail_app_password_tooltip_label'=> 'Gmail app password help',
            'gmail_app_password_tooltip_title'=> 'Quick guide',
            'gmail_app_password_tooltip_step_1'=> 'Make sure 2-Step Verification is enabled on your Google account.',
            'gmail_app_password_tooltip_step_2'=> 'On the App passwords page, use SMark as the app name.',
            'gmail_app_password_tooltip_step_3'=> 'Paste the password Google gives you into this SMark field.',
            'field_app_password_placeholder'=> 'SMTP password or app password',
            'field_password_placeholder_email'=> 'SMTP or email password',
            'field_password_placeholder_gmail'=> '16-character Google app password',
            'field_daily_limit'             => 'Daily Send Limit',
            'field_smtp_host'               => 'SMTP Host',
            'field_smtp_port'               => 'SMTP Port',
            'field_encryption'              => 'Encryption',
            'encryption_none'               => 'None',
            'email_help'                    => 'For regular email, use the SMTP details provided by your host or email service.',
            'gmail_help'                    => 'For Gmail, enable 2-Step Verification and create an app password from Google Account > Security > App passwords. Do not enter the regular Gmail password.',
            'save_button'                   => 'Add Account',
            'list_title'                    => 'Saved Accounts',
            'list_description'              => 'These accounts can be used later as campaign senders with per-account daily limits.',
            'empty_state'                   => 'No email accounts have been added yet.',
            'column_label'                  => 'Account',
            'column_email'                  => 'Email',
            'column_smtp'                   => 'SMTP',
            'column_daily_limit'            => 'Daily Limit',
            'column_status'                 => 'Status',
            'column_actions'                => 'Actions',
            'status_active'                 => 'Active',
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
                'page_title'                    => 'بخش‌بندی مخاطبان',
                'page_subtitle'                 => 'مخاطبان ایمیلی را تعریف کنید و برای کمپین‌های بعدی در سگمنت‌های مشخص نگه دارید.',
                'breadcrumb_dashboard'          => 'داشبورد',
                'breadcrumb_parent'             => 'ایمیل مارکتینگ',
                'breadcrumb_current'            => 'بخش‌بندی مخاطبان',
                'form_title'                    => 'افزودن مخاطب',
                'form_description'              => 'مشخصات مخاطب، ایمیل و سگمنت هدف را ثبت کنید تا بعدا در ارسال کمپین استفاده شود.',
                'field_first_name'              => 'نام',
                'field_first_name_placeholder'  => 'مثلا سعید',
                'field_last_name'               => 'نام خانوادگی',
                'field_last_name_placeholder'   => 'مثلا حسنی',
                'field_email'                   => 'ایمیل مخاطب',
                'field_phone'                   => 'شماره تماس',
                'field_phone_placeholder'       => 'اختیاری',
                'field_segment'                 => 'سگمنت / لیست',
                'field_segment_placeholder'     => 'مثلا لیدهای دوره سئو',
                'field_source'                  => 'منبع جذب',
                'field_source_placeholder'      => 'مثلا فرم سایت، وبینار یا خرید',
                'field_status'                  => 'وضعیت',
                'field_notes'                   => 'یادداشت',
                'field_notes_placeholder'       => 'نیاز، علاقه‌مندی یا نکته مهم برای کمپین...',
                'contact_help'                  => 'فقط ایمیل برای هر مخاطب ضروری است. سگمنت، منبع و یادداشت اختیاری هستند و هر ایمیل فقط یک‌بار ثبت می‌شود.',
                'save_button'                   => 'افزودن مخاطب',
                'list_title'                    => 'مخاطبان ثبت‌شده',
                'list_description'              => 'این لیست بعدا برای انتخاب مخاطبان هدف کمپین و فیلتر بر اساس سگمنت استفاده می‌شود.',
                'empty_state'                   => 'هنوز مخاطبی ثبت نشده است.',
                'column_contact'                => 'مخاطب',
                'column_email'                  => 'ایمیل',
                'column_segment'                => 'سگمنت',
                'column_source'                 => 'منبع',
                'column_status'                 => 'وضعیت',
                'column_actions'                => 'عملیات',
                'status_subscribed'             => 'عضو لیست',
                'status_lead'                   => 'لید',
                'status_unsubscribed'           => 'لغو عضویت',
                'unnamed_contact'               => 'مخاطب بدون نام',
                'delete_button'                 => 'حذف',
                'delete_confirm'                => 'این مخاطب حذف شود؟',
                'notice_saved'                  => 'مخاطب ذخیره شد.',
                'notice_deleted'                => 'مخاطب حذف شد.',
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
                'bulk_default_segment'          => 'سگمنت پیش‌فرض اختیاری',
                'bulk_import_button'            => 'وارد کردن مخاطبان',
            );
        }

        return array(
            'page_title'                    => 'Audience Segments',
            'page_subtitle'                 => 'Create email contacts and organize them into campaign-ready segments.',
            'breadcrumb_dashboard'          => 'Dashboard',
            'breadcrumb_parent'             => 'Email Marketing',
            'breadcrumb_current'            => 'Audience Segments',
            'form_title'                    => 'Add Contact',
            'form_description'              => 'Save the contact details, email address, and target segment for future campaigns.',
            'field_first_name'              => 'First Name',
            'field_first_name_placeholder'  => 'Example: Alex',
            'field_last_name'               => 'Last Name',
            'field_last_name_placeholder'   => 'Example: Hasani',
            'field_email'                   => 'Contact Email',
            'field_phone'                   => 'Phone',
            'field_phone_placeholder'       => 'Optional',
            'field_segment'                 => 'Segment / List',
            'field_segment_placeholder'     => 'Example: SEO course leads',
            'field_source'                  => 'Acquisition Source',
            'field_source_placeholder'      => 'Example: Website form, webinar, purchase',
            'field_status'                  => 'Status',
            'field_notes'                   => 'Notes',
            'field_notes_placeholder'       => 'Need, interest, or campaign context...',
            'contact_help'                  => 'Only email is required. Segment, source, and notes are optional, and each email address can only appear once.',
            'save_button'                   => 'Add Contact',
            'list_title'                    => 'Saved Contacts',
            'list_description'              => 'Use this list later to select campaign audiences and filter by segment.',
            'empty_state'                   => 'No contacts have been added yet.',
            'column_contact'                => 'Contact',
            'column_email'                  => 'Email',
            'column_segment'                => 'Segment',
            'column_source'                 => 'Source',
            'column_status'                 => 'Status',
            'column_actions'                => 'Actions',
            'status_subscribed'             => 'Subscribed',
            'status_lead'                   => 'Lead',
            'status_unsubscribed'           => 'Unsubscribed',
            'unnamed_contact'               => 'Unnamed contact',
            'delete_button'                 => 'Delete',
            'delete_confirm'                => 'Delete this contact?',
            'notice_saved'                  => 'Contact saved.',
            'notice_deleted'                => 'Contact deleted.',
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
            'bulk_default_segment'          => 'Optional Default Segment',
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
                'field_campaign_name'          => 'نام ایمیل / کمپین',
                'field_campaign_name_placeholder' => 'مثلا معرفی دوره جدید سئو',
                'field_sender_account'         => 'حساب فرستنده',
                'field_sender_account_empty'   => 'بعدا انتخاب می‌کنم',
                'field_subject'                => 'موضوع ایمیل',
                'field_subject_placeholder'    => 'مثلا یک پیشنهاد ویژه برای رشد سئوی سایت شما',
                'field_preview_text'           => 'متن پیش‌نمایش',
                'field_preview_text_placeholder' => 'متنی کوتاه که کنار موضوع در inbox دیده می‌شود',
                'field_reply_to'               => 'ایمیل پاسخ‌به',
                'field_status'                 => 'وضعیت پیام',
                'status_draft'                 => 'پیش‌نویس',
                'status_ready'                 => 'آماده ارسال',
                'status_sent'                  => 'ارسال‌شده',
                'field_segments'               => 'انتخاب سگمنت‌ها',
                'field_segments_help'          => 'اگر سگمنتی انتخاب نشود، می‌توانید مخاطبان را به صورت تکی انتخاب کنید.',
                'field_contacts'               => 'انتخاب مخاطب‌ها',
                'field_contacts_help'          => 'برای انتخاب چند مخاطب از Ctrl/Cmd استفاده کنید.',
                'field_body'                   => 'بدنه ایمیل',
                'field_body_placeholder'       => 'متن اصلی ایمیل، پیشنهاد، لینک‌ها و فراخوان اقدام را اینجا بنویسید...',
                'field_notes'                  => 'یادداشت داخلی',
                'field_notes_placeholder'      => 'هدف کمپین، نکات تست A/B یا موارد پیگیری...',
                'form_help'                    => 'برای آماده‌سازی ارسال، موضوع و بدنه ایمیل ضروری هستند. انتخاب مخاطب فعلا اختیاری است و در مرحله ارسال هم قابل کنترل خواهد بود.',
                'save_button'                  => 'ذخیره پیام کمپین',
                'update_button'                => 'به‌روزرسانی پیام کمپین',
                'quick_send_button'            => 'ارسال سریع',
                'list_title'                   => 'پیام‌های ذخیره‌شده',
                'list_description'             => 'این پیام‌ها بعدا در تقویم ارسال و اجرای کمپین استفاده می‌شوند.',
                'empty_state'                  => 'هنوز پیامی برای کمپین ذخیره نشده است.',
                'column_campaign'              => 'کمپین',
                'column_subject'               => 'موضوع',
                'column_audience'              => 'مخاطبان',
                'column_status'                => 'وضعیت',
                'column_actions'               => 'عملیات',
                'audience_not_selected'        => 'هنوز انتخاب نشده',
                'audience_summary'             => '%s سگمنت، %s مخاطب',
                'delete_button'                => 'حذف',
                'edit_button'                  => 'ویرایش',
                'send_button'                  => 'ارسال',
                'delete_confirm'               => 'این پیام کمپین حذف شود؟',
                'notice_saved'                 => 'پیام کمپین ذخیره شد.',
                'notice_deleted'               => 'پیام کمپین حذف شد.',
                'notice_error'                 => 'اطلاعات پیام کامل یا معتبر نیست.',
                'notice_sent'                  => '%s ایمیل ارسال شد.',
                'notice_send_error'            => 'ارسال انجام نشد. مخاطبان، موضوع، بدنه ایمیل یا تنظیمات ارسال را بررسی کنید.',
                'notice_no_recipients'         => 'هیچ مخاطب قابل ارسالی پیدا نشد. مخاطبان کمپین را دوباره انتخاب و ذخیره کنید.',
                'notice_sender_not_configured' => 'ارسال انجام نشد چون حساب فرستنده داخلی اسمارک انتخاب نشده یا تنظیمات SMTP آن کامل نیست. لطفا حساب جیمیل داخلی را انتخاب کنید و SMTP Host، SMTP Port و App Password را بررسی کنید.',
            );
        }

        return array(
            'page_title'                   => 'Campaign Message',
            'page_subtitle'                => 'Prepare the campaign name, email content, and target audience for future sends.',
            'breadcrumb_dashboard'         => 'Dashboard',
            'breadcrumb_parent'            => 'Email Marketing',
            'breadcrumb_current'           => 'Campaign Message',
            'form_title'                   => 'Build Email Message',
            'form_description'             => 'Save the message as a draft and choose the target audience from segments or contacts.',
            'field_campaign_name'          => 'Email / Campaign Name',
            'field_campaign_name_placeholder' => 'Example: New SEO course announcement',
            'field_sender_account'         => 'Sender Account',
            'field_sender_account_empty'   => 'Choose later',
            'field_subject'                => 'Subject Line',
            'field_subject_placeholder'    => 'Example: A special offer to grow your site SEO',
            'field_preview_text'           => 'Preview Text',
            'field_preview_text_placeholder' => 'Short text shown next to the subject in the inbox',
            'field_reply_to'               => 'Reply-To Email',
            'field_status'                 => 'Message Status',
            'status_draft'                 => 'Draft',
            'status_ready'                 => 'Ready to Send',
            'status_sent'                  => 'Sent',
            'field_segments'               => 'Select Segments',
            'field_segments_help'          => 'If no segment is selected, you can still select individual contacts.',
            'field_contacts'               => 'Select Contacts',
            'field_contacts_help'          => 'Use Ctrl/Cmd to select multiple contacts.',
            'field_body'                   => 'Email Body',
            'field_body_placeholder'       => 'Write the main email copy, offer, links, and call to action here...',
            'field_notes'                  => 'Internal Notes',
            'field_notes_placeholder'      => 'Campaign goal, A/B test notes, or follow-up details...',
            'form_help'                    => 'Subject and body are required. Audience selection is optional for now and can be finalized during sending.',
            'save_button'                  => 'Save Campaign Message',
            'update_button'                => 'Update Campaign Message',
            'quick_send_button'            => 'Quick Send',
            'list_title'                   => 'Saved Messages',
            'list_description'             => 'These messages can later be used in send scheduling and campaign execution.',
            'empty_state'                  => 'No campaign message has been saved yet.',
            'column_campaign'              => 'Campaign',
            'column_subject'               => 'Subject',
            'column_audience'              => 'Audience',
            'column_status'                => 'Status',
            'column_actions'               => 'Actions',
            'audience_not_selected'        => 'Not selected yet',
            'audience_summary'             => '%s segments, %s contacts',
            'delete_button'                => 'Delete',
            'edit_button'                  => 'Edit',
            'send_button'                  => 'Send',
            'delete_confirm'               => 'Delete this campaign message?',
            'notice_saved'                 => 'Campaign message saved.',
            'notice_deleted'               => 'Campaign message deleted.',
            'notice_error'                 => 'The message information is incomplete or invalid.',
            'notice_sent'                  => '%s emails sent.',
            'notice_send_error'            => 'Send failed. Check recipients, subject, body, or sending settings.',
            'notice_no_recipients'         => 'No sendable recipient was found. Re-select and save this campaign audience.',
            'notice_sender_not_configured' => 'Send failed because the internal SMark sender account is missing or its SMTP settings are incomplete. Select the Gmail account and check SMTP host, port, and app password.',
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
                'event_sent'                => 'ارسال',
                'event_failed'              => 'خطا',
                'event_open'                => 'باز شدن',
                'event_click'               => 'کلیک',
                'event_unsubscribe'         => 'لغو عضویت',
                'event_bounce'              => 'برگشت',
                'event_unknown'             => 'نامشخص',
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
            'event_sent'                => 'Sent',
            'event_failed'              => 'Failed',
            'event_open'                => 'Open',
            'event_click'               => 'Click',
            'event_unsubscribe'         => 'Unsubscribe',
            'event_bounce'              => 'Bounce',
            'event_unknown'             => 'Unknown',
        );
    }

    private function get_tasks($lang) {
        if ($lang === 'fa') {
            return array(
                array(
                    'icon' => 'dashicons-groups',
                    'title' => 'بخش‌بندی مخاطبان',
                    'description' => 'لیست‌ها و سگمنت‌های مخاطبان را بر اساس هدف کمپین آماده کنید.',
                    'url' => admin_url('admin.php?page=smark-email-contacts'),
                ),
                array(
                    'icon' => 'dashicons-email-alt',
                    'title' => 'طراحی پیام کمپین',
                    'description' => 'موضوع، متن، پیشنهاد و فراخوان اقدام ایمیل را برنامه‌ریزی کنید.',
                    'url' => admin_url('admin.php?page=smark-email-campaign-message'),
                ),
                array(
                    'icon' => 'dashicons-calendar-alt',
                    'title' => 'تقویم ارسال',
                    'description' => 'زمان ارسال کمپین‌ها و پیگیری‌های بعدی را مشخص کنید.',
                ),
                array(
                    'icon' => 'dashicons-randomize',
                    'title' => 'اتوماسیون و دنباله‌ها',
                    'description' => 'مسیرهای خوشامدگویی، پرورش لید و فعال‌سازی مجدد را طراحی کنید.',
                ),
                array(
                    'icon' => 'dashicons-admin-users',
                    'title' => 'حساب‌های ایمیل',
                    'description' => 'حساب‌های جیمیل ارسال‌کننده و سقف ارسال روزانه هر حساب را مدیریت کنید.',
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
                'title' => 'Audience Segments',
                'description' => 'Prepare lists and segments based on each campaign goal.',
                'url' => admin_url('admin.php?page=smark-email-contacts'),
            ),
            array(
                'icon' => 'dashicons-email-alt',
                'title' => 'Campaign Message',
                'description' => 'Plan the subject, copy, offer, and call to action for each email.',
                'url' => admin_url('admin.php?page=smark-email-campaign-message'),
            ),
            array(
                'icon' => 'dashicons-calendar-alt',
                'title' => 'Send Calendar',
                'description' => 'Schedule campaign sends and follow-up touchpoints.',
            ),
            array(
                'icon' => 'dashicons-randomize',
                'title' => 'Automation Flows',
                'description' => 'Map welcome, nurture, and reactivation sequences.',
            ),
            array(
                'icon' => 'dashicons-admin-users',
                'title' => 'Email Accounts',
                'description' => 'Manage Gmail sender accounts and the daily send limit for each account.',
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
                padding: 0 18px;
                border-radius: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                font-weight: 600;
            }

            .smark-email-card-header-actions {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
            }

            .smark-email-card-header-actions .button-primary {
                flex: 0 0 auto;
                min-height: 42px;
                display: inline-flex;
                align-items: center;
                padding: 0 18px;
                border-radius: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                font-weight: 600;
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

            .smark-email-form-grid label {
                display: flex;
                flex-direction: column;
                gap: 8px;
                color: #1f2937;
                font-weight: 600;
            }

            .smark-email-form-grid label small,
            .smark-email-field-note {
                color: #64748b;
                font-size: 0.86em;
                font-weight: 500;
                line-height: 1.7;
            }

            .smark-email-form-grid input,
            .smark-email-form-grid select,
            .smark-email-form-grid textarea {
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
            .smark-email-form-grid textarea:focus {
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

            .smark-email-form-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                justify-content: flex-start;
            }

            .smark-seo-optimization-page.rtl .smark-email-form-actions {
                justify-content: flex-end;
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
            .smark-email-secondary-action:hover,
            .smark-email-secondary-action:focus,
            .smark-email-send-button:hover,
            .smark-email-send-button:focus,
            .smark-email-edit-button:hover,
            .smark-email-edit-button:focus,
            .smark-email-delete-button:hover,
            .smark-email-delete-button:focus {
                background: linear-gradient(135deg, #5b6ee8 0%, #6d43a0 100%) !important;
                color: #ffffff !important;
                border-color: transparent !important;
                box-shadow: 0 16px 32px rgba(99, 102, 241, 0.28) !important;
            }

            .smark-email-secondary-action,
            .smark-email-send-button,
            .smark-email-edit-button,
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
                .smark-email-performance-summary {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .smark-email-metrics-grid,
                .smark-email-performance-summary {
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

            .smark-email-status--ready {
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
            }
        ';
    }
}
