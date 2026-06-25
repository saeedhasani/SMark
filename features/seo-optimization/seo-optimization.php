<?php
/**
 * SEO Optimization Feature
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

class SMarkSeoOptimization {

    /**
     * Option key used to persist SEO process state.
     *
     * @var string
     */
    private $option_key = 'smark_seo_process_state';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('wp_ajax_smark_seo_save_notes', array($this, 'ajax_save_notes'));
        add_action('wp_ajax_smark_seo_reset', array($this, 'ajax_reset_process'));
        add_action('wp_ajax_smark_seo_save_language', array($this, 'ajax_save_language'));
    }

    /**
     * Register hidden submenu page.
     */
    public function add_submenu_page() {
        add_submenu_page(
            null,
            __('SEO Optimization', 'smark'),
            __('SEO Optimization', 'smark'),
            'smark_access',
            'smark-seo-optimization',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue assets for the SEO Optimization page.
     *
     * @param string $hook Current admin hook.
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'admin_page_smark-seo-optimization') {
            return;
        }

        $current_lang = get_option('smark_panel_language', 'en');
        $strings = $this->get_strings($current_lang);

        // Fonts for RTL Persian layout.
        wp_enqueue_style(
            'vazirmatn-font',
            'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap',
            array(),
            SMARK_VERSION
        );

        wp_enqueue_style(
            'smark-seo-optimization',
            plugin_dir_url(__FILE__) . 'assets/seo-optimization.css',
            array(),
            SMARK_VERSION
        );

        wp_enqueue_script(
            'smark-seo-optimization',
            plugin_dir_url(__FILE__) . 'assets/seo-optimization.js',
            array('jquery'),
            SMARK_VERSION,
            true
        );

        $state = $this->get_state();

        wp_localize_script('smark-seo-optimization', 'smarkSeoOptimization', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smark_seo_nonce'),
            'strings' => array(
                'saving' => $strings['notifications']['saving'],
                'saved' => $strings['notifications']['saved'],
                'error' => $strings['notifications']['error'],
                'resetTitle' => $strings['notifications']['reset_title'],
                'resetConfirm' => $strings['notifications']['reset_confirm'],
                'resetSuccess' => $strings['notifications']['reset_success']
            )
        ));

        add_action('admin_body_class', function($classes) {
            if (strpos($classes, 'smark-plugin-page') === false) {
                $classes .= ' smark-plugin-page';
            }
            return $classes;
        });
    }

    /**
     * Render the SEO Optimization admin page.
     */
    public function render_page() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'));
        }

        $current_lang = get_option('smark_panel_language', 'en');
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';

        $strings = $this->get_strings($current_lang);
        $state = $this->get_state();
        $steps = $this->get_step_definitions($current_lang);
        ?>
        <div class="wrap smark-seo-optimization-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
            <div class="smark-page-header">
                <h1><?php echo esc_html($strings['page_title']); ?></h1>
                <p class="description"><?php echo esc_html($strings['page_subtitle']); ?></p>
            </div>

            <div class="smark-breadcrumb">
                <div class="breadcrumb-left">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html($strings['breadcrumb_dashboard']); ?></a>
                    <span class="separator"><?php echo $rtl_class ? '‹' : '›'; ?></span>
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

            <div class="seo-grid">
                <?php foreach ($steps as $step_key => $step_config) : ?>
                    <section class="seo-step-card <?php echo ($step_key === 'strategy') ? 'seo-step-card--full' : ''; ?>" data-step="<?php echo esc_attr($step_key); ?>">
                        <header class="seo-step-header">
                            <?php if ($step_key !== 'strategy') : ?>
                                <span class="seo-step-number"><?php echo esc_html($step_config['order']); ?></span>
                            <?php endif; ?>
                            <div>
                                <h2><?php echo esc_html($step_config['title']); ?></h2>
                                <p><?php echo esc_html($step_config['description']); ?></p>
                            </div>
                        </header>

                        <ul class="seo-task-list">
                            <?php foreach ($step_config['tasks'] as $task_key => $task_config) : ?>
                                <li class="seo-task">
                                    <div class="seo-task-content">
                                        <?php if (!empty($task_config['icon'])) : ?>
                                            <span class="seo-task-icon dashicons <?php echo esc_attr($task_config['icon']); ?>" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <span class="seo-task-text">
                                            <?php if (!empty($task_config['url'])) : ?>
                                                <strong><a href="<?php echo esc_url($task_config['url']); ?>" class="seo-task-title-link"><?php echo esc_html($task_config['title']); ?></a></strong>
                                            <?php else : ?>
                                                <strong><?php echo esc_html($task_config['title']); ?></strong>
                                            <?php endif; ?>
                                            <small><?php echo esc_html($task_config['description']); ?></small>
                                        </span>
                                    </div>
                                    <?php if (!empty($task_config['links'])) : ?>
                                        <div class="seo-task-links">
                                            <?php foreach ($task_config['links'] as $link) : ?>
                                                <a href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php echo esc_html($link['label']); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($step_key !== 'strategy') : ?>
                        <div class="seo-notes">
                            <label for="seo-notes-<?php echo esc_attr($step_key); ?>">
                                <?php echo esc_html($strings['notes_label']); ?>
                            </label>
                            <textarea
                                id="seo-notes-<?php echo esc_attr($step_key); ?>"
                                class="seo-step-notes"
                                data-step="<?php echo esc_attr($step_key); ?>"
                                placeholder="<?php echo esc_attr($strings['notes_placeholder']); ?>"
                                rows="4"
                            ><?php echo esc_textarea(isset($state['notes'][$step_key]) ? $state['notes'][$step_key] : ''); ?></textarea>
                            <p class="seo-notes-hint"><?php echo esc_html($strings['notes_helper']); ?></p>
                        </div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>

            <!-- Plugin Version Footer -->
            <div class="smark-version-footer">
                <div class="version-info">
                    <span class="version-label"><?php echo ($current_lang === 'fa') ? 'پلاگین اسمارک' : 'SMark Plugin'; ?></span>
                    <span class="version-separator">•</span>
                    <span class="version-number">v<?php echo esc_html(SMARK_VERSION); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler: Persist notes for a given step.
     */
    public function ajax_save_notes() {
        check_ajax_referer('smark_seo_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $step_key = isset($_POST['step']) ? sanitize_key(wp_unslash($_POST['step'])) : '';
        $notes = isset($_POST['notes']) ? wp_kses_post(wp_unslash($_POST['notes'])) : '';

        if (empty($step_key)) {
            wp_send_json_error(array('message' => __('Invalid step identifier.', 'smark')));
        }

        $steps = $this->get_step_definitions();
        if (!array_key_exists($step_key, $steps)) {
            wp_send_json_error(array('message' => __('Unknown step identifier.', 'smark')));
        }

        $state = $this->get_state();
        $state['notes'][$step_key] = $notes;
        $state['updated_at'] = current_time('mysql');

        update_option($this->option_key, $state);

        wp_send_json_success(array('success' => true));
    }

    /**
     * AJAX handler: Reset the entire SEO process state.
     */
    public function ajax_reset_process() {
        check_ajax_referer('smark_seo_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $default_state = $this->get_default_state();
        update_option($this->option_key, $default_state);

        wp_send_json_success(array(
            'state' => $default_state,
            'completion' => $this->calculate_completion_percentage($default_state)
        ));
    }

    /**
     * AJAX handler: Save language preference
     */
    public function ajax_save_language() {
        check_ajax_referer('smark_seo_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '';

        if (empty($language) || !in_array($language, array('en', 'fa'))) {
            wp_send_json_error(array('message' => __('Invalid language', 'smark')));
        }

        update_option('smark_panel_language', $language);

        wp_send_json_success(array(
            'message' => __('Language preference saved', 'smark'),
            'language' => $language
        ));
    }

    /**
     * Retrieve persisted state or fall back to defaults.
     *
     * @return array
     */
    private function get_state() {
        $state = get_option($this->option_key, array());
        if (!is_array($state)) {
            $state = array();
        }

        $default_state = $this->get_default_state();

        $merged_tasks = isset($state['tasks']) && is_array($state['tasks'])
            ? array_merge($default_state['tasks'], $state['tasks'])
            : $default_state['tasks'];
        $task_keys = $this->get_task_keys();
        $state['tasks'] = array();
        foreach ($task_keys as $task_key) {
            $state['tasks'][$task_key] = !empty($merged_tasks[$task_key]);
        }

        $merged_notes = isset($state['notes']) && is_array($state['notes'])
            ? array_merge($default_state['notes'], $state['notes'])
            : $default_state['notes'];
        $state['notes'] = array();
        foreach ($this->get_step_definitions() as $step_key => $config) {
            $state['notes'][$step_key] = isset($merged_notes[$step_key]) ? (string) $merged_notes[$step_key] : '';
        }

        if (empty($state['updated_at'])) {
            $state['updated_at'] = current_time('mysql');
        }

        return $state;
    }

    /**
     * Default state structure.
     *
     * @return array
     */
    private function get_default_state() {
        $tasks = array();
        foreach ($this->get_task_keys() as $task_key) {
            $tasks[$task_key] = false;
        }

        $notes = array();
        foreach ($this->get_step_definitions() as $step_key => $config) {
            $notes[$step_key] = '';
        }

        return array(
            'tasks' => $tasks,
            'notes' => $notes,
            'updated_at' => current_time('mysql')
        );
    }

    /**
     * Calculate completion percentage.
     *
     * @param array $state Current state.
     *
     * @return int
     */
    private function calculate_completion_percentage($state) {
        $tasks = isset($state['tasks']) && is_array($state['tasks']) ? $state['tasks'] : array();
        $task_keys = $this->get_task_keys();
        $total = count($task_keys);
        if ($total === 0) {
            return 0;
        }

        $completed = 0;
        foreach ($task_keys as $task_key) {
            if (!empty($tasks[$task_key])) {
                $completed++;
            }
        }

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Get available task identifiers.
     *
     * @return array
     */
    private function get_task_keys() {
        $steps = $this->get_step_definitions('en');
        $task_keys = array();

        foreach ($steps as $step) {
            foreach ($step['tasks'] as $task_key => $task_config) {
                $task_keys[] = $task_key;
            }
        }

        return $task_keys;
    }

    /**
     * Retrieve localized strings for the interface.
     *
     * @param string $lang Current language.
     *
     * @return array
     */
    private function get_strings($lang = 'en') {
        $strings = array(
            'en' => array(
                'page_title' => 'SEO Optimization Hub',
                'page_subtitle' => 'Follow the guided workflow to plan, execute, and measure your SEO initiatives.',
                'progress_helper' => 'Overall completion of the SEO process.',
                'breadcrumb_dashboard' => 'Dashboard',
                'breadcrumb_current' => 'SEO Optimization Hub',
                'notes_label' => 'Execution notes',
                'notes_placeholder' => 'Add decisions, blockers, or follow-up tasks for this pillar...',
                'notes_helper' => 'Notes are saved automatically after you stop typing.',
                'reset_button' => 'Reset progress',
                'back_to_dashboard' => 'Back to dashboard',
                'notifications' => array(
                    'saving' => 'Saving…',
                    'saved' => 'Saved!',
                    'error' => 'Could not save changes. Please try again.',
                    'reset_title' => 'Reset progress',
                    'reset_confirm' => 'Are you sure you want to reset all SEO progress?',
                    'reset_success' => 'SEO progress reset successfully.'
                )
            ),
            'fa' => array(
                'page_title' => 'مرکز مدیریت سئو',
                'page_subtitle' => 'با استفاده از این مسیر هدایت‌شده، فعالیت‌های سئو را برنامه‌ریزی، اجرا و اندازه‌گیری کنید.',
                'progress_helper' => 'درصد پیشرفت کلی فرآیند سئو.',
                'breadcrumb_dashboard' => 'داشبورد',
                'breadcrumb_current' => 'مرکز مدیریت سئو',
                'notes_label' => 'یادداشت‌های اجرایی',
                'notes_placeholder' => 'تصمیم‌ها، موانع یا کارهای پیگیری این بخش را ثبت کنید...',
                'notes_helper' => 'یادداشت‌ها پس از توقف تایپ به صورت خودکار ذخیره می‌شوند.',
                'reset_button' => 'بازنشانی پیشرفت',
                'back_to_dashboard' => 'بازگشت به داشبورد',
                'notifications' => array(
                    'saving' => 'در حال ذخیره…',
                    'saved' => 'ذخیره شد!',
                    'error' => 'ذخیره‌سازی انجام نشد. دوباره تلاش کنید.',
                    'reset_title' => 'بازنشانی پیشرفت',
                    'reset_confirm' => 'آیا از بازنشانی کامل وضعیت سئو مطمئن هستید؟',
                    'reset_success' => 'پیشرفت سئو با موفقیت بازنشانی شد.'
                )
            )
        );

        return isset($strings[$lang]) ? $strings[$lang] : $strings['en'];
    }

    /**
     * Get localized step definitions.
     *
     * @param string $lang Language code.
     *
     * @return array
     */
    private function get_step_definitions($lang = 'en') {
        $steps = array(
            'strategy' => array(
                'order' => 1,
                'title' => array(
                    'en' => 'Strategy & Content',
                    'fa' => 'استراتژی و محتوا'
                ),
                'description' => array(
                    'en' => 'Set goals, research keywords, and plan the content workstream.',
                    'fa' => 'اهداف را مشخص کنید، کلمات کلیدی را تحقیق کنید و جریان کار محتوا را برنامه‌ریزی کنید.'
                ),
                'tasks' => array(
                    'backlinks_management' => array(
                        'icon' => 'dashicons-admin-links',
                        'title' => array(
                            'en' => 'Backlinks management',
                            'fa' => 'مدیریت بک‌لینک‌ها'
                        ),
                        'description' => array(
                            'en' => 'Manage outreach projects, prospect lists, and backlink statuses.',
                            'fa' => 'پروژه‌ها، لیست‌ها و وضعیت بک‌لینک‌ها را مدیریت و پایش کنید.'
                        ),
                        'url' => 'admin.php?page=smark-backlinks-management',
                        'links' => array()
                    ),
                    'keyword_map' => array(
                        'icon' => 'dashicons-search',
                        'title' => array(
                            'en' => 'Keyword Research',
                            'fa' => 'تحقیق کلمات کلیدی'
                        ),
                        'description' => array(
                            'en' => 'Consolidate priority keywords, search intent, and funnel stage.',
                            'fa' => 'کلمات کلیدی اولویت‌دار، نیت جست‌وجو و مرحله قیف را یکپارچه کنید.'
                        ),
                        'url' => admin_url('admin.php?page=smark-keyword-research'),
                        'links' => array()
                    ),
                    'content_management' => array(
                        'icon' => 'dashicons-welcome-write-blog',
                        'title' => array(
                            'en' => 'Content Management',
                            'fa' => 'مدیریت محتوا'
                        ),
                        'description' => array(
                            'en' => 'Select, track, and organize the website content you will work on.',
                            'fa' => 'محتواهای سایت را انتخاب، پیگیری و سامان‌دهی کنید.'
                        ),
                        'url' => admin_url('admin.php?page=smark-content-management'),
                        'links' => array()
                    ),
                    'technical_seo' => array(
                        'icon' => 'dashicons-admin-tools',
                        'title' => array(
                            'en' => 'Technical SEO',
                            'fa' => 'سئو فنی'
                        ),
                        'description' => array(
                            'en' => 'Review crawlability, indexation, speed, and structured data readiness.',
                            'fa' => 'خزش، ایندکس، سرعت و داده‌های ساختاریافته سایت را بررسی و آماده‌سازی کنید.'
                        ),
                        'url' => '',
                        'links' => array()
                    ),
                    'keyword_gap' => array(
                        'icon' => 'dashicons-chart-bar',
                        'title' => array(
                            'en' => 'Keyword Gap',
                            'fa' => 'شکاف کلمات کلیدی'
                        ),
                        'description' => array(
                            'en' => 'Compare competitors and identify missing keyword opportunities.',
                            'fa' => 'رقبا را مقایسه کنید و فرصت‌های کلمات کلیدی از دست‌رفته را پیدا کنید.'
                        ),
                        'url' => admin_url('admin.php?page=smark-keyword-gap'),
                        'links' => array()
                    ),
                )
            ),
            'technical' => array(
                'order' => 2,
                'title' => array(
                    'en' => 'Technical Health',
                    'fa' => 'سلامت فنی'
                ),
                'description' => array(
                    'en' => 'Secure crawlability, speed, and structured data readiness.',
                    'fa' => 'خزندگی، سرعت و داده‌های ساختاریافته را بررسی و بهینه کنید.'
                ),
                'tasks' => array(
                    'core_web_vitals' => array(
                        'title' => array(
                            'en' => 'Review Core Web Vitals',
                            'fa' => 'بررسی Core Web Vitals'
                        ),
                        'description' => array(
                            'en' => 'Measure performance metrics and prioritize fixes for slow templates.',
                            'fa' => 'شاخص‌های عملکرد را بسنجید و اولویت‌بندی رفع کندی قالب‌ها را مشخص کنید.'
                        ),
                        'links' => array(
                            array(
                                'label' => array(
                                    'en' => 'Open PageSpeed Insights',
                                    'fa' => 'PageSpeed Insights'
                                ),
                                'url' => 'https://pagespeed.web.dev/'
                            )
                        )
                    ),
                    'crawl_issues' => array(
                        'title' => array(
                            'en' => 'Resolve crawl issues',
                            'fa' => 'رفع خطاهای خزش'
                        ),
                        'description' => array(
                            'en' => 'Audit Search Console coverage, indexation, and sitemap status.',
                            'fa' => 'پوشش ایندکس، وضعیت نقشه سایت و خطاهای سرچ کنسول را بررسی کنید.'
                        ),
                        'links' => array(
                            array(
                                'label' => array(
                                    'en' => 'Open Google Search Console',
                                    'fa' => 'Google Search Console'
                                ),
                                'url' => 'https://search.google.com/search-console'
                            )
                        )
                    ),
                    'schema_implementation' => array(
                        'title' => array(
                            'en' => 'Validate structured data',
                            'fa' => 'اعتبارسنجی داده‌های ساختاریافته'
                        ),
                        'description' => array(
                            'en' => 'Test schema markup for key templates and fix validation errors.',
                            'fa' => 'نشانه‌گذاری اسکیما را بررسی و خطاهای اعتبارسنجی را برطرف کنید.'
                        ),
                        'links' => array(
                            array(
                                'label' => array(
                                    'en' => 'Rich Results Test',
                                    'fa' => 'Rich Results Test'
                                ),
                                'url' => 'https://search.google.com/test/rich-results'
                            )
                        )
                    )
                )
            ),
            'authority' => array(
                'order' => 3,
                'title' => array(
                    'en' => 'Authority & Monitoring',
                    'fa' => 'اعتبار و پایش'
                ),
                'description' => array(
                    'en' => 'Launch off-page activities and monitor impact on rankings and leads.',
                    'fa' => 'فعالیت‌های برون صفحه را اجرا و اثر آن بر رتبه و سرنخ‌ها را پایش کنید.'
                ),
                'tasks' => array(
                    'link_building_plan' => array(
                        'title' => array(
                            'en' => 'Publish link-building plan',
                            'fa' => 'تدوین برنامه لینک‌سازی'
                        ),
                        'description' => array(
                            'en' => 'Prioritize outreach targets and content assets supporting the campaign.',
                            'fa' => 'هدف‌های ارتباطی و دارایی‌های محتوایی کمپین را اولویت‌بندی کنید.'
                        ),
                        'links' => array()
                    ),
                    'performance_dashboard' => array(
                        'title' => array(
                            'en' => 'Update performance dashboard',
                            'fa' => 'به‌روزرسانی داشبورد عملکرد'
                        ),
                        'description' => array(
                            'en' => 'Log rankings, conversions, and key technical signals weekly.',
                            'fa' => 'رتبه‌ها، تبدیل‌ها و شاخص‌های فنی مهم را هفتگی ثبت کنید.'
                        ),
                        'links' => array()
                    ),
                    'quarterly_review' => array(
                        'title' => array(
                            'en' => 'Schedule quarterly review',
                            'fa' => 'برنامه‌ریزی بازبینی فصلی'
                        ),
                        'description' => array(
                            'en' => 'Align stakeholders on outcomes, learnings, and next sprint priorities.',
                            'fa' => 'ذی‌نفعان را درباره نتایج، درس‌آموخته‌ها و اولویت‌های اسپرینت بعدی هماهنگ کنید.'
                        ),
                        'links' => array()
                    )
                )
            )
        );

        if (isset($steps['strategy']['tasks']) && is_array($steps['strategy']['tasks'])) {
            $strategy_tasks = $steps['strategy']['tasks'];
            $task_order = array('keyword_map', 'keyword_gap', 'content_management', 'backlinks_management', 'technical_seo');

            $ordered_tasks = array();
            foreach ($task_order as $task_key) {
                if (isset($strategy_tasks[$task_key])) {
                    $ordered_tasks[$task_key] = $strategy_tasks[$task_key];
                }
            }

            foreach ($strategy_tasks as $task_key => $task_config) {
                if (!isset($ordered_tasks[$task_key])) {
                    $ordered_tasks[$task_key] = $task_config;
                }
            }

            $steps['strategy']['tasks'] = $ordered_tasks;
        }

        // Disabled sections (not needed in this hub UI).
        unset($steps['technical'], $steps['authority']);

        $lang = ($lang === 'fa') ? 'fa' : 'en';

        $localized = array();
        foreach ($steps as $key => $config) {
            $localized_tasks = array();
            foreach ($config['tasks'] as $task_key => $task_config) {
                $localized_tasks[$task_key] = array(
                    'title' => $task_config['title'][$lang],
                    'description' => $task_config['description'][$lang],
                    'icon' => isset($task_config['icon']) ? $task_config['icon'] : '',
                    'url' => isset($task_config['url']) ? $task_config['url'] : '',
                    'links' => array_map(function($link) use ($lang) {
                        return array(
                            'label' => isset($link['label'][$lang]) ? $link['label'][$lang] : $link['label']['en'],
                            'url' => $link['url']
                        );
                    }, $task_config['links'])
                );
            }

            $localized[$key] = array(
                'order' => $config['order'],
                'title' => isset($config['title'][$lang]) ? $config['title'][$lang] : $config['title']['en'],
                'description' => isset($config['description'][$lang]) ? $config['description'][$lang] : $config['description']['en'],
                'tasks' => $localized_tasks
            );
        }

        return $localized;
    }
}
