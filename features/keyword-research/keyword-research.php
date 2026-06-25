<?php
/**
 * Keyword Research Feature
 *
 * Provides keyword bank management, project assignments, and bulk upload support.
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

// Prevent direct access.
/*if (!defined('WPINC')) {
    die;
}*/

class SMarkKeywordResearch {
    const OPTION_CENTRAL_BASE_URL = 'smark_central_base_url';
    const DEFAULT_CENTRAL_BASE_URL = 'https://saeedhasani.com';
    const SEARCH_CONSOLE_TOKEN_PREFIX = 'smarksc:v1:';

    /**
     * Database table names.
     *
     * @var string
     */
    private $project_keywords_table;
    private $keyword_bank_table;
    private $projects_table;

    /**
     * Version used for table schema tracking.
     *
     * @var string
     */
    private $schema_version = '1.7';

    private function is_encrypted_search_console_tokens($value) {
        return is_string($value) && strpos($value, self::SEARCH_CONSOLE_TOKEN_PREFIX) === 0;
    }

    private function get_search_console_token_key() {
        $material = wp_salt('auth') . '|' . wp_salt('secure_auth') . '|' . home_url('/');
        return hash('sha256', $material, true);
    }

    private function encode_search_console_tokens($tokens) {
        $json = wp_json_encode(is_array($tokens) ? $tokens : array());
        if (!is_string($json) || $json === '') {
            $json = '{}';
        }

        if (!function_exists('openssl_encrypt') || !function_exists('random_bytes')) {
            return base64_encode($json);
        }

        $cipher = 'aes-256-cbc';
        $iv_length = openssl_cipher_iv_length($cipher);
        if (!$iv_length) {
            return base64_encode($json);
        }

        $iv = random_bytes($iv_length);
        $key = $this->get_search_console_token_key();
        $cipher_text = openssl_encrypt($json, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher_text === false) {
            return base64_encode($json);
        }

        $iv_b64 = base64_encode($iv);
        $cipher_b64 = base64_encode($cipher_text);
        $mac = hash_hmac('sha256', $iv_b64 . '.' . $cipher_b64, $key);

        return self::SEARCH_CONSOLE_TOKEN_PREFIX . base64_encode(wp_json_encode(array(
            'iv' => $iv_b64,
            'value' => $cipher_b64,
            'mac' => $mac,
        )));
    }

    private function decode_search_console_tokens($stored) {
        $stored = is_string($stored) ? trim($stored) : '';
        if ($stored === '') {
            return array();
        }

        if (!$this->is_encrypted_search_console_tokens($stored)) {
            $json = base64_decode($stored, true);
            $decoded = is_string($json) ? json_decode($json, true) : null;
            return is_array($decoded) ? $decoded : array();
        }

        if (!function_exists('openssl_decrypt')) {
            return array();
        }

        $payload_raw = base64_decode(substr($stored, strlen(self::SEARCH_CONSOLE_TOKEN_PREFIX)), true);
        $payload = is_string($payload_raw) ? json_decode($payload_raw, true) : null;
        if (!is_array($payload) || empty($payload['iv']) || empty($payload['value']) || empty($payload['mac'])) {
            return array();
        }

        $key = $this->get_search_console_token_key();
        $expected_mac = hash_hmac('sha256', $payload['iv'] . '.' . $payload['value'], $key);
        if (!hash_equals($expected_mac, (string) $payload['mac'])) {
            return array();
        }

        $iv = base64_decode((string) $payload['iv'], true);
        $cipher_text = base64_decode((string) $payload['value'], true);
        if (!is_string($iv) || !is_string($cipher_text)) {
            return array();
        }

        $json = openssl_decrypt($cipher_text, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        $decoded = is_string($json) ? json_decode($json, true) : null;
        return is_array($decoded) ? $decoded : array();
    }

    private function get_keyword_bank_stats_cache_key() {
        return 'smark_kw_bank_stats_v1';
    }

    /**
     * @return array{total:int,lastUpload:?string,fetched_at:int}|null
     */
    private function get_cached_keyword_bank_stats() {
        $cache_key = $this->get_keyword_bank_stats_cache_key();

        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['total'])) {
            return array(
                'total' => (int) $cached['total'],
                'lastUpload' => isset($cached['lastUpload']) && is_string($cached['lastUpload']) ? $cached['lastUpload'] : null,
                'fetched_at' => isset($cached['fetched_at']) ? (int) $cached['fetched_at'] : 0,
            );
        }

        $stored = get_option($cache_key, null);
        if (is_array($stored) && isset($stored['total'])) {
            return array(
                'total' => (int) $stored['total'],
                'lastUpload' => isset($stored['lastUpload']) && is_string($stored['lastUpload']) ? $stored['lastUpload'] : null,
                'fetched_at' => isset($stored['fetched_at']) ? (int) $stored['fetched_at'] : 0,
            );
        }

        return null;
    }

    private function set_cached_keyword_bank_stats($total, $last_upload_display) {
        $cache_key = $this->get_keyword_bank_stats_cache_key();
        $payload = array(
            'total' => (int) $total,
            'lastUpload' => is_string($last_upload_display) && $last_upload_display !== '' ? $last_upload_display : null,
            'fetched_at' => time(),
        );

        set_transient($cache_key, $payload, 30 * MINUTE_IN_SECONDS);
        update_option($cache_key, $payload, false);
    }

    /**
     * @return string[]
     */
    private function get_keyword_bank_count_endpoints() {
        $path = '/wp-json/smark-core/v1/keyword-bank/count';
        $endpoints = array();
        foreach ($this->get_central_keyword_bank_base_urls() as $base) {
            $endpoints[] = $base . $path;
        }

        return array_values(array_unique($endpoints));
    }

    /**
     * @return array{total:int,lastUpload:?string}|WP_Error
     */
    private function fetch_keyword_bank_stats_remote() {
        $args = array(
            'timeout' => 20,
            'headers' => array(),
        );
        $token = $this->get_central_sync_token();
        if ($token !== '') {
            $args['headers']['x-smark-sync-token'] = $token;
        }

        $last_error = null;
        foreach ($this->get_keyword_bank_count_endpoints() as $endpoint) {
            $resp = wp_remote_get($endpoint, $args);
            if (is_wp_error($resp)) {
                $last_error = $resp;
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($resp);
            if ($code !== 200) {
                $last_error = new WP_Error('smark_kw_bank_http', 'Central keyword bank request failed', array('status' => $code));
                continue;
            }

            $data = json_decode((string) wp_remote_retrieve_body($resp), true);
            if (!is_array($data)) {
                $last_error = new WP_Error('smark_kw_bank_invalid', 'Invalid response from central keyword bank', array('status' => 502));
                continue;
            }

            $total = isset($data['count']) ? (int) $data['count'] : 0;
            $last_upload = null;
            if (!empty($data['lastUpdate'])) {
                $last_upload = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $data['lastUpdate']);
                $last_upload = is_string($last_upload) && $last_upload !== '' ? $last_upload : null;
            }

            return array('total' => $total, 'lastUpload' => $last_upload);
        }

        return $last_error instanceof WP_Error ? $last_error : new WP_Error('smark_kw_bank_failed', 'Central keyword bank request failed');
    }

    private function get_central_sync_token() {
        if (defined('SMARK_CENTRAL_SYNC_TOKEN') && is_string(SMARK_CENTRAL_SYNC_TOKEN) && SMARK_CENTRAL_SYNC_TOKEN !== '') {
            return SMARK_CENTRAL_SYNC_TOKEN;
        }

        $token = get_option('smark_central_sync_token', '');
        $token = is_string($token) ? trim($token) : '';
        if ($token !== '') {
            return $token;
        }

        // Producer sites may have SMark Core installed and store the token under a different option name.
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

    private function get_central_keyword_bank_base_url() {
        if (defined('SMARK_CENTRAL_BASE_URL') && is_string(SMARK_CENTRAL_BASE_URL) && SMARK_CENTRAL_BASE_URL !== '') {
            $url = $this->normalize_central_keyword_bank_base_url((string) SMARK_CENTRAL_BASE_URL);
            if ($url !== '') {
                return $url;
            }
        }

        $url = get_option(self::OPTION_CENTRAL_BASE_URL, '');
        $url = is_string($url) ? $this->normalize_central_keyword_bank_base_url($url) : '';
        if ($url !== '') {
            return $url;
        }

        if (is_multisite()) {
            $url = get_site_option(self::OPTION_CENTRAL_BASE_URL, '');
            $url = is_string($url) ? $this->normalize_central_keyword_bank_base_url($url) : '';
            if ($url !== '') {
                return $url;
            }
        }

        $filtered = apply_filters('SMARK_central_base_url', self::DEFAULT_CENTRAL_BASE_URL);
        $filtered = is_string($filtered) ? $this->normalize_central_keyword_bank_base_url($filtered) : '';
        return $filtered !== '' ? $filtered : self::DEFAULT_CENTRAL_BASE_URL;
    }

    private function normalize_central_keyword_bank_base_url($url) {
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

    private function get_central_keyword_bank_base_urls() {
        $primary = rtrim($this->get_central_keyword_bank_base_url(), '/');
        $bases = array($primary);

        if ($primary === self::DEFAULT_CENTRAL_BASE_URL) {
            $bases[] = 'https://www.saeedhasani.com';
        }

        $filtered = apply_filters('SMARK_central_endpoint_bases', $bases);
        if (is_array($filtered) && !empty($filtered)) {
            $bases = $filtered;
        }

        $normalized = array();
        foreach ($bases as $base) {
            $base = $this->normalize_central_keyword_bank_base_url($base);
            if ($base !== '') {
                $normalized[] = $base;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function get_google_client_id() {
        if (class_exists('SMarkToolsIntegration', false)) {
            global $SMARK_tools_integration;
            if ($SMARK_tools_integration) {
                $client_id = $SMARK_tools_integration->get_google_client_id();
                if (!empty($client_id)) {
                    return $client_id;
                }
            }
        }

        $db_client_id = get_option('SMARK_google_client_id', '');
        if (!empty($db_client_id)) {
            return $db_client_id;
        }

        $filter_client_id = apply_filters('SMARK_google_client_id', '');
        if (!empty($filter_client_id)) {
            return $filter_client_id;
        }

        if (defined('SMARK_GOOGLE_CLIENT_ID')) {
            return SMARK_GOOGLE_CLIENT_ID;
        }

        return '504883940536-0v8ppi41kgj02b4opb1bn2qiobmofup0.apps.googleusercontent.com';
    }

    private function get_panel_language() {
        $lang = get_option('SMARK_panel_language', 'en');
        return ($lang === 'fa') ? 'fa' : 'en';
    }

    private function interpret_intent_value($value, $lang) {
        $lang = ($lang === 'fa') ? 'fa' : 'en';
        $value = is_string($value) ? trim($value) : (is_numeric($value) ? (string) $value : '');
        if ($value === '') {
            return null;
        }

        // If this is already a label, return as-is.
        if (preg_match('/[A-Za-z\\x{0600}-\\x{06FF}]/u', $value)) {
            return $value;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $value)), static function ($p) {
            return $p !== '';
        }));
        if (empty($parts)) {
            return null;
        }

        $map_en = array(
            '0' => 'Commercial',
            '1' => 'Informational',
            '2' => 'Navigational',
            '3' => 'Transactional',
        );
        $map_fa = array(
            '0' => 'تجاری',
            '1' => 'اطلاعاتی',
            '2' => 'ناوبری',
            '3' => 'تراکنشی',
        );
        $map = ($lang === 'fa') ? $map_fa : $map_en;

        $labels = array();
        foreach ($parts as $code) {
            $labels[] = isset($map[$code]) ? $map[$code] : (($lang === 'fa') ? ('هدف #' . $code) : ('Intent #' . $code));
        }

        $sep = ($lang === 'fa') ? '، ' : ', ';
        return implode($sep, $labels);
    }

    private function interpret_serp_features_value($value, $lang) {
        $lang = ($lang === 'fa') ? 'fa' : 'en';
        $value = is_string($value) ? trim($value) : (is_numeric($value) ? (string) $value : '');
        if ($value === '') {
            return null;
        }

        // If this is already a label list, return as-is.
        if (preg_match('/[A-Za-z\\x{0600}-\\x{06FF}]/u', $value)) {
            return $value;
        }

        // Only numeric codes separated by comma/spaces.
        if (!preg_match('/^[0-9\\s,]+$/', $value)) {
            return $value;
        }

        $codes = array_values(array_filter(array_map('trim', explode(',', $value)), static function ($p) {
            return $p !== '';
        }));
        if (empty($codes)) {
            return null;
        }

        // Keep this map aligned with Keyword Gap behavior.
        $map_en = array(
            '0' => 'Featured snippet',
            '1' => 'Knowledge panel',
            '2' => 'Knowledge card',
            '3' => 'Reviews',
            '4' => 'Instant answer',
            '5' => 'Image pack',
            '6' => 'Sitelinks',
            '7' => 'Local pack',
            '8' => 'Top stories',
            '9' => 'Video',
            '10' => 'Tweet',
            '11' => 'People also ask',
            '12' => 'Shopping ads',
            '13' => 'Maps',
            '14' => 'Featured video',
            '15' => 'Carousel',
            '16' => 'Related questions',
            '17' => 'Google flights',
            '18' => 'Hotel pack',
            '19' => 'Jobs',
            '20' => 'Twitter carousel',
            '21' => 'People also search for',
            '22' => 'Google ads (top)',
            '23' => 'Google ads (bottom)',
            '24' => 'Google shopping',
            '25' => 'Knowledge panel (music)',
            '26' => 'Knowledge panel (movies)',
            '27' => 'Knowledge panel (shopping)',
            '28' => 'Knowledge panel (social)',
            '29' => 'Knowledge panel (sports)',
            '30' => 'Knowledge panel (travel)',
            '31' => 'Knowledge panel (TV series)',
            '32' => 'Featured snippet (multiple)',
            '34' => 'Popular products',
            '35' => 'Discussions and forums',
            '36' => 'Related searches',
            '37' => 'Google posts',
            '38' => 'Knowledge panel (books)',
            '39' => 'Knowledge panel (education)',
            '40' => 'Knowledge panel (finance)',
            '41' => 'Knowledge panel (health)',
            '42' => 'Knowledge panel (jobs)',
            '43' => 'Knowledge panel (local)',
            '44' => 'Knowledge panel (news)',
            '45' => 'Knowledge panel (podcasts)',
            '46' => 'Knowledge panel (products)',
            '47' => 'Knowledge panel (recipes)',
            '48' => 'Knowledge panel (science)',
            '49' => 'Knowledge panel (technology)',
            '50' => 'Knowledge panel (weather)',
            '51' => 'Knowledge panel (web stories)',
            '52' => 'AI Overview',
        );
        $map_fa = array(
            '0' => 'اسنیپت ویژه',
            '1' => 'پنل دانش',
            '2' => 'کارت دانش',
            '3' => 'نقد و بررسی‌ها',
            '4' => 'پاسخ فوری',
            '5' => 'بسته تصاویر',
            '6' => 'سایت‌لینک‌ها',
            '7' => 'بسته محلی',
            '8' => 'اخبار برتر',
            '9' => 'ویدئو',
            '10' => 'توییت',
            '11' => 'مردم همچنین می‌پرسند',
            '12' => 'تبلیغات خرید',
            '13' => 'نقشه‌ها',
            '14' => 'ویدئوی ویژه',
            '15' => 'کاروسل',
            '16' => 'سوالات مرتبط',
            '17' => 'پروازهای گوگل',
            '18' => 'بسته هتل',
            '19' => 'فرصت‌های شغلی',
            '20' => 'کاروسل توییتر',
            '21' => 'مردم همچنین جستجو می‌کنند',
            '22' => 'تبلیغات گوگل (بالا)',
            '23' => 'تبلیغات گوگل (پایین)',
            '24' => 'خرید گوگل',
            '25' => 'پنل دانش (موسیقی)',
            '26' => 'پنل دانش (فیلم‌ها)',
            '27' => 'پنل دانش (خرید)',
            '28' => 'پنل دانش (شبکه اجتماعی)',
            '29' => 'پنل دانش (ورزش)',
            '30' => 'پنل دانش (سفر)',
            '31' => 'پنل دانش (سریال)',
            '32' => 'اسنیپت ویژه (چندگانه)',
            '34' => 'محصولات محبوب',
            '35' => 'بحث‌ها و فروم‌ها',
            '36' => 'جستجوهای مرتبط',
            '37' => 'پست‌های گوگل',
            '38' => 'پنل دانش (کتاب‌ها)',
            '39' => 'پنل دانش (آموزش)',
            '40' => 'پنل دانش (مالی)',
            '41' => 'پنل دانش (سلامت)',
            '42' => 'پنل دانش (شغل‌ها)',
            '43' => 'پنل دانش (محلی)',
            '44' => 'پنل دانش (اخبار)',
            '45' => 'پنل دانش (پادکست‌ها)',
            '46' => 'پنل دانش (محصولات)',
            '47' => 'پنل دانش (دستورپخت)',
            '48' => 'پنل دانش (علم)',
            '49' => 'پنل دانش (فناوری)',
            '50' => 'پنل دانش (آب‌وهوا)',
            '51' => 'پنل دانش (وب‌استوری‌ها)',
            '52' => 'مرور هوش مصنوعی',
        );
        $map = ($lang === 'fa') ? $map_fa : $map_en;

        $labels = array();
        foreach ($codes as $code) {
            $labels[] = isset($map[$code]) ? $map[$code] : (($lang === 'fa') ? ('ویژگی #' . $code) : ('Feature #' . $code));
        }

        $sep = ($lang === 'fa') ? '، ' : ', ';
        return implode($sep, $labels);
    }

    private function get_google_client_secret() {
        if (class_exists('SMarkToolsIntegration', false)) {
            global $SMARK_tools_integration;
            if ($SMARK_tools_integration) {
                $secret = $SMARK_tools_integration->get_google_client_secret();
                if (!empty($secret)) {
                    return $secret;
                }
            }
        }

        $db_secret = get_option('SMARK_google_client_secret', '');
        if (!empty($db_secret)) {
            return $db_secret;
        }

        $filter_secret = apply_filters('SMARK_google_client_secret', '');
        if (!empty($filter_secret)) {
            return $filter_secret;
        }

        if (defined('SMARK_GOOGLE_CLIENT_SECRET')) {
            return SMARK_GOOGLE_CLIENT_SECRET;
        }

        return '';
    }

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;

        $this->project_keywords_table = $wpdb->prefix . 'SMARK_keyword_research';
        $this->keyword_bank_table     = $wpdb->prefix . 'SMARK_keyword_bank';
        $this->projects_table         = $this->resolve_projects_table();

        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX endpoints.
        add_action('wp_ajax_SMARK_keyword_get_projects', array($this, 'ajax_get_projects'));
        add_action('wp_ajax_SMARK_keyword_create_project', array($this, 'ajax_create_project'));
        add_action('wp_ajax_SMARK_keyword_get_project_items', array($this, 'ajax_get_project_items'));
        add_action('wp_ajax_SMARK_keyword_add_from_bank', array($this, 'ajax_add_from_bank'));
        add_action('wp_ajax_SMARK_keyword_remove_project_item', array($this, 'ajax_remove_project_item'));
        add_action('wp_ajax_SMARK_keyword_search_bank', array($this, 'ajax_search_bank'));
        add_action('wp_ajax_SMARK_keyword_bank_stats', array($this, 'ajax_keyword_bank_stats'));
        add_action('wp_ajax_SMARK_keyword_request_keywords', array($this, 'ajax_request_keywords'));
        add_action('wp_ajax_SMARK_keyword_check_page_link', array($this, 'ajax_check_page_link'));
        add_action('wp_ajax_SMARK_keyword_get_edit_url', array($this, 'ajax_get_edit_url'));
        add_action('wp_ajax_SMARK_keyword_fetch_ranking', array($this, 'ajax_fetch_ranking'));
        add_action('wp_ajax_SMARK_keyword_fetch_live_rank', array($this, 'ajax_fetch_live_rank'));
        add_action('wp_ajax_SMARK_keyword_refresh_keyword_data_from_bank', array($this, 'ajax_refresh_keyword_data_from_bank'));
        add_action('wp_ajax_SMARK_keyword_refresh_keyword', array($this, 'ajax_refresh_keyword'));
        add_action('wp_ajax_SMARK_keyword_rankmath_gap_stats', array($this, 'ajax_rankmath_gap_stats'));
        add_action('wp_ajax_SMARK_keyword_add_rankmath_missing', array($this, 'ajax_add_rankmath_missing_keyword'));
        add_action('wp_ajax_SMARK_keyword_fetch_keyword_for_project', array($this, 'ajax_fetch_keyword_for_project'));
        // Language preference
        add_action('wp_ajax_SMARK_keyword_save_language', array($this, 'ajax_save_language'));

        // REST API endpoint for remote keyword checking
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        $this->maybe_create_tables();
    }

    private function escape_db_identifier($identifier) {
        if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            return '';
        }

        return '`' . str_replace('`', '', esc_sql($identifier)) . '`';
    }

    private function resolve_projects_table() {
        global $wpdb;

        $suffixes = array('SMARK_projects', 'smark_projects');
        $prefixes = array_values(array_unique(array_filter(array(
            isset($wpdb->prefix) ? (string) $wpdb->prefix : '',
            isset($wpdb->base_prefix) ? (string) $wpdb->base_prefix : '',
        ))));

        $candidates = array();

        foreach ($prefixes as $prefix) {
            foreach ($suffixes as $suffix) {
                $table = $prefix . $suffix;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence discovery.
                $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
                if ($exists === $table) {
                    $candidates[] = $table;
                }
            }
        }

        // Last resort: try wildcard discovery (handles unexpected prefixes).
        if (empty($candidates)) {
            foreach ($suffixes as $suffix) {
                $pattern = '%' . $wpdb->esc_like($suffix);
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table discovery.
                $matches = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $pattern));
                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        if (is_string($match) && substr($match, -strlen($suffix)) === $suffix) {
                            $candidates[] = $match;
                        }
                    }
                }
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates)));
        if (empty($candidates)) {
            return (isset($wpdb->prefix) ? (string) $wpdb->prefix : '') . 'SMARK_projects';
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        // Prefer tables that have the website column (most features rely on it).
        $with_website = array();
        foreach ($candidates as $table) {
            if ($this->table_has_column($table, 'website')) {
                $with_website[] = $table;
            }
        }
        $pool = !empty($with_website) ? $with_website : $candidates;

        // Choose the table with the highest row count (usually the "real" table when duplicates exist).
        $best = $pool[0];
        $best_count = -1;
        foreach ($pool as $table) {
            $table_sql = $this->escape_db_identifier($table);
            if ($table_sql === '') {
                continue;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
            $count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $table_sql);
            if ($count > $best_count) {
                $best = $table;
                $best_count = $count;
            }
        }

        return $best;
    }

    private function table_has_column($table_name, $column) {
        global $wpdb;

        $table_sql = $this->escape_db_identifier($table_name);
        if ($table_sql === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema discovery.
        $found = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', (string) $column)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return !empty($found);
    }

    private function ensure_project_mark_column_exists($projects_table) {
        global $wpdb;
        $projects_table = is_string($projects_table) ? trim($projects_table) : '';
        if ($projects_table === '') {
            return false;
        }

        if ($this->table_has_column($projects_table, 'mark')) {
            return true;
        }

        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
        $result = $wpdb->query('ALTER TABLE ' . $projects_table_sql . ' ADD COLUMN mark int(11) NOT NULL DEFAULT 0');
        return $result !== false;
    }

    private function reserve_project_mark_credit($project_db_id, $amount) {
        global $wpdb;
        $project_db_id = (int) $project_db_id;
        $amount = (int) $amount;
        if ($project_db_id <= 0 || $amount <= 0 || $amount > 10) {
            return new WP_Error('invalid', 'Invalid project or amount.');
        }

        $projects_table = $this->projects_table;
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return new WP_Error('db', 'Projects table not found.');
        }

        if (!$this->ensure_project_mark_column_exists($projects_table)) {
            return new WP_Error('db', 'Mark column not available.');
        }

        // Atomic decrement when enough credits exist.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Table identifier validated via escape_db_identifier(); placeholders not supported for identifiers.
        $updated = $wpdb->query($wpdb->prepare("UPDATE {$projects_table_sql} SET mark = GREATEST(mark - %d, 0) WHERE id = %d AND mark >= %d", $amount, $project_db_id, $amount));
        if ($updated === false) {
            $err = isset($wpdb->last_error) ? (string) $wpdb->last_error : '';
            return new WP_Error('db', $err !== '' ? $err : 'Database error.');
        }
        if ((int) $updated !== 1) {
            return new WP_Error('insufficient_mark', 'Insufficient mark credits.');
        }

        // Remaining after reservation (best-effort).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
        $remaining = (int) $wpdb->get_var($wpdb->prepare("SELECT mark FROM {$projects_table_sql} WHERE id = %d", $project_db_id));
        return $remaining;
    }

    private function refund_project_mark_credit($project_db_id, $amount) {
        global $wpdb;
        $project_db_id = (int) $project_db_id;
        $amount = (int) $amount;
        if ($project_db_id <= 0 || $amount <= 0 || $amount > 10) {
            return false;
        }

        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
        $ok = $wpdb->query($wpdb->prepare("UPDATE {$projects_table_sql} SET mark = mark + %d WHERE id = %d", $amount, $project_db_id));
        return $ok !== false;
    }

    /**
     * Central keyword fetch responses have changed over time and can arrive
     * as a direct keyword row, nested under `keyword`, nested under `data.keyword`,
     * or with extra output before/after the JSON body.
     *
     * @param string $body Raw response body.
     * @return array<string,mixed>|null
     */
    private function parse_central_keyword_response_body($body) {
        $body = is_string($body) ? trim($body) : '';
        if ($body === '') {
            return null;
        }

        $candidates = array($body);

        $json_start = strpos($body, '{');
        $json_end = strrpos($body, '}');
        if ($json_start !== false && $json_end !== false && $json_end >= $json_start) {
            $json_fragment = substr($body, $json_start, ($json_end - $json_start) + 1);
            if (is_string($json_fragment) && trim($json_fragment) !== '' && $json_fragment !== $body) {
                $candidates[] = trim($json_fragment);
            }
        }

        foreach ($candidates as $candidate) {
            $data = json_decode($candidate, true);
            if (!is_array($data)) {
                continue;
            }

            $keyword_payload = $this->extract_central_keyword_payload($data);
            if (is_array($keyword_payload)) {
                return $keyword_payload;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    private function extract_central_keyword_payload($payload) {
        if (isset($payload['keyword']) && is_array($payload['keyword'])) {
            return $payload['keyword'];
        }

        if (isset($payload['data']) && is_array($payload['data']) && isset($payload['data']['keyword']) && is_array($payload['data']['keyword'])) {
            return $payload['data']['keyword'];
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            $direct = $this->extract_central_keyword_payload($payload['data']);
            if (is_array($direct)) {
                return $direct;
            }
        }

        if (isset($payload['keyword']) && is_string($payload['keyword'])) {
            $keyword = trim($payload['keyword']);
            if ($keyword !== '') {
                return $payload;
            }
        }

        return null;
    }

    private function fetch_keyword_from_central($keyword) {
        $keyword = is_string($keyword) ? trim($keyword) : '';
        if ($keyword === '') {
            return new WP_Error('invalid', 'Invalid keyword.');
        }

        $website = rtrim((string) home_url('/'), '/');
        if ($website === '') {
            return new WP_Error('invalid', 'Invalid website.');
        }

        $endpoint = $this->get_central_keyword_bank_base_url() . '/wp-json/smark-core/v1/keyword-bank/fetch';
        $args = array(
            'timeout' => 25,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (central-kb-fetch)',
            ),
            'body' => wp_json_encode(array(
                'keyword' => $keyword,
                'website' => $website,
            )),
        );

        $token = $this->get_central_sync_token();
        if ($token !== '') {
            $args['headers']['x-smark-sync-token'] = $token;
        }

        $resp = wp_remote_post($endpoint, $args);
        if (is_wp_error($resp)) {
            return new WP_Error('central_request_failed', $resp->get_error_message());
        }

        $http = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($http < 200 || $http >= 300) {
            $code = 'central_http_' . $http;
            $msg = '';
            if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
                $msg = $data['message'];
            } elseif (is_string($body) && trim($body) !== '') {
                $msg = $body;
            } else {
                $msg = 'Central request failed.';
            }
            if (is_array($data) && isset($data['code']) && is_string($data['code']) && $data['code'] !== '') {
                $code = $data['code'];
            }
            return new WP_Error($code, $msg, array('status' => $http, 'body' => $body));
        }

        $keyword_payload = $this->parse_central_keyword_response_body($body);
        if (!is_array($keyword_payload)) {
            return new WP_Error('central_invalid_response', 'Invalid response from central server.', array('status' => $http, 'body' => $body));
        }

        return $keyword_payload;
    }

    private function get_central_semrush_live_rank_endpoint() {
        return rtrim($this->get_central_keyword_bank_base_url(), '/') . '/wp-json/smark-core/v1/tools/semrush/live-rank';
    }

    private function fetch_live_rank_from_central($keyword, $website, $preferred_url = '') {
        $keyword = is_string($keyword) ? trim($keyword) : '';
        $website = is_string($website) ? rtrim(trim($website), '/') : '';
        $preferred_url = is_string($preferred_url) ? trim($preferred_url) : '';
        if ($keyword === '' || $website === '') {
            return new WP_Error('invalid', 'Invalid live rank request.');
        }

        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (central-live-rank)',
            ),
            'body' => wp_json_encode(array(
                'keyword' => $keyword,
                'website' => $website,
                'preferred_url' => $preferred_url,
            )),
        );

        $token = $this->get_central_sync_token();
        if ($token !== '') {
            $args['headers']['x-smark-sync-token'] = $token;
        }

        $resp = wp_remote_post($this->get_central_semrush_live_rank_endpoint(), $args);
        if (is_wp_error($resp)) {
            return new WP_Error('central_request_failed', $resp->get_error_message());
        }

        $http = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($http < 200 || $http >= 300) {
            $code = 'central_http_' . $http;
            $msg = 'Central live rank request failed.';
            if (is_array($data) && isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
                $msg = $data['message'];
            } elseif (trim($body) !== '') {
                $msg = trim($body);
            }
            if (is_array($data) && isset($data['code']) && is_string($data['code']) && $data['code'] !== '') {
                $code = $data['code'];
            }
            return new WP_Error($code, $msg, array('status' => $http, 'body' => $body));
        }

        if (!is_array($data) || !array_key_exists('rank', $data)) {
            return new WP_Error('central_invalid_response', 'Invalid live rank response from central server.', array('status' => $http, 'body' => $body));
        }

        return array(
            'rank' => (int) $data['rank'],
            'results' => isset($data['results']) && is_array($data['results']) ? $data['results'] : array(),
            'matched_url' => isset($data['matched_url']) ? (string) $data['matched_url'] : '',
            'project_backlinks' => isset($data['project_backlinks']) ? (int) $data['project_backlinks'] : 0,
            'project_refdomains' => isset($data['project_refdomains']) ? (int) $data['project_refdomains'] : 0,
            'top10_backlinks_max' => isset($data['top10_backlinks_max']) ? (int) $data['top10_backlinks_max'] : 0,
            'top10_refdomains_max' => isset($data['top10_refdomains_max']) ? (int) $data['top10_refdomains_max'] : 0,
            'cached' => !empty($data['cached']),
        );
    }

    public function ajax_fetch_keyword_for_project() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('code' => 'permissions', 'message' => $this->translate('error_permissions')), 403);
        }

        $project_id = isset($_POST['projectId']) ? absint(wp_unslash($_POST['projectId'])) : 0;
        $keyword = isset($_POST['keyword']) ? sanitize_text_field((string) wp_unslash($_POST['keyword'])) : '';
        $keyword = trim($keyword);

        if ($project_id <= 0) {
            wp_send_json_error(array('code' => 'missing_project', 'message' => $this->translate('error_missing_project')), 400);
        }
        if ($keyword === '') {
            wp_send_json_error(array('code' => 'missing_keyword', 'message' => $this->translate('error_missing_keywords')), 400);
        }

        $keyword_data = $this->fetch_keyword_from_central($keyword);
        if (is_wp_error($keyword_data)) {
            $err_data = $keyword_data->get_error_data();
            $status = 500;
            if (is_array($err_data) && isset($err_data['status'])) {
                $status = (int) $err_data['status'];
            }
            if ($status < 400 || $status > 599) {
                $status = 500;
            }
            wp_send_json_error(array(
                'code' => $keyword_data->get_error_code(),
                'message' => $keyword_data->get_error_message(),
            ), $status);
        }

        wp_send_json_success(array(
            'keyword' => $keyword_data,
            'remaining_mark' => null,
        ));
    }

    private function get_current_site_project() {
        global $wpdb;

        $projects_table = $this->projects_table;
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return null;
        }

        $project_id = (int) get_option('smark_current_project_db_id', 0);
        if ($project_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, project_name FROM {$projects_table_sql} WHERE id = %d", $project_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (is_array($row) && !empty($row)) {
                return $row;
            }
        }

        $website = rtrim((string) home_url('/'), '/');
        if ($website !== '' && $this->table_has_column($projects_table, 'website')) {
            $found_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$projects_table_sql} WHERE website = %s ORDER BY id DESC LIMIT 1", $website)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ($found_id > 0) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, project_name FROM {$projects_table_sql} WHERE id = %d", $found_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                if (is_array($row) && !empty($row)) {
                    update_option('smark_current_project_db_id', (int) $row['id'], false);
                    return $row;
                }
            }
        }

        $row = $wpdb->get_row("SELECT id, project_name FROM {$projects_table_sql} ORDER BY id DESC LIMIT 1", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if (is_array($row) && !empty($row) && isset($row['id'])) {
            update_option('smark_current_project_db_id', (int) $row['id'], false);
            return $row;
        }

        return null;
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_rest_routes() {
        register_rest_route('smark/v1', '/check-keyword', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_check_keyword'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * REST API: Check keyword in Rank Math.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_check_keyword($request) {
        $keyword = $request->get_param('keyword');

        if (empty($keyword)) {
            return new WP_REST_Response(array('found' => false), 400);
        }

        global $wpdb;

        $post = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE pm.meta_key = 'rank_math_focus_keyword'
                 AND (pm.meta_value = %s OR pm.meta_value LIKE %s OR pm.meta_value LIKE %s OR pm.meta_value LIKE %s)
                 AND p.post_status = 'publish'
                 LIMIT 1",
                $keyword,
                $keyword . ',%',
                '%,' . $keyword,
                '%,' . $keyword . ',%'
            ),
            ARRAY_A
        );

        if ($post) {
            $url = get_permalink($post['ID']);
            return new WP_REST_Response(array('found' => true, 'url' => $url), 200);
        }

        return new WP_REST_Response(array('found' => false), 200);
    }

    /**
     * Create database tables if needed.
     *
     * @return void
     */
    private function maybe_create_tables() {
        global $wpdb;

        $saved_version = get_option('SMARK_keyword_research_schema', '0');

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset_collate = $wpdb->get_charset_collate();

        // Check if we need to add new columns (migration from 1.0 to 1.1)
        if ($saved_version === '1.0') {
            $this->migrate_to_1_1();
            update_option('SMARK_keyword_research_schema', '1.1');
            $saved_version = '1.1';
        }

        // Check if we need to add ranking columns (migration from 1.1 to 1.2)
        if ($saved_version === '1.1') {
            $this->migrate_to_1_2();
            update_option('SMARK_keyword_research_schema', '1.2');
            $saved_version = '1.2';
        }

        // Check if we need to add ranking trend column (migration from 1.2 to 1.3)
        if ($saved_version === '1.2') {
            $this->migrate_to_1_3();
            update_option('SMARK_keyword_research_schema', '1.3');
            $saved_version = '1.3';
        }

        // Ensure page link columns exist (migration from 1.3 to 1.4)
        if ($saved_version === '1.3') {
            $this->migrate_to_1_4();
            update_option('SMARK_keyword_research_schema', '1.4');
            $saved_version = '1.4';
        }

        // Ensure ranking update timestamp column exists (migration from 1.4 to 1.5)
        if ($saved_version === '1.4') {
            $this->migrate_to_1_5();
            update_option('SMARK_keyword_research_schema', '1.5');
            $saved_version = '1.5';
        }

        // Ensure live ranking columns exist (migration from 1.5 to 1.6)
        if ($saved_version === '1.5') {
            $this->migrate_to_1_6();
            update_option('SMARK_keyword_research_schema', '1.6');
            $saved_version = '1.6';
        }

        // Ensure live backlink/refdomain metric columns exist (migration from 1.6 to 1.7)
        if ($saved_version === '1.6') {
            $this->migrate_to_1_7();
            update_option('SMARK_keyword_research_schema', $this->schema_version);
            $saved_version = $this->schema_version;
        }

        if ($saved_version === $this->schema_version) {
            return;
        }

        // Keyword bank table.
        $sql_bank = "CREATE TABLE {$this->keyword_bank_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            intent varchar(100) DEFAULT NULL,
            volume int(11) DEFAULT NULL,
            keyword_difficulty decimal(6,2) DEFAULT NULL,
            cpc_usd decimal(10,2) DEFAULT NULL,
            serp_features text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY keyword_idx (keyword)
        ) {$charset_collate};";

        // Project assignments table.
        $sql_project_keywords = "CREATE TABLE {$this->project_keywords_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id bigint(20) UNSIGNED NOT NULL,
            project_name varchar(255) NOT NULL,
            keyword_bank_id bigint(20) UNSIGNED DEFAULT NULL,
            keyword varchar(255) NOT NULL,
            intent varchar(100) DEFAULT NULL,
            volume int(11) DEFAULT NULL,
            keyword_difficulty decimal(6,2) DEFAULT NULL,
            cpc_usd decimal(10,2) DEFAULT NULL,
            serp_features text DEFAULT NULL,
            page_link_status varchar(20) DEFAULT 'not_checked',
            page_link_url varchar(500) DEFAULT NULL,
            rank_3month_avg decimal(6,2) DEFAULT NULL,
            rank_1month_avg decimal(6,2) DEFAULT NULL,
            ranking_trend varchar(20) DEFAULT NULL,
            ranking_updated_at datetime DEFAULT NULL,
            live_rank_position int(11) DEFAULT NULL,
            live_rank_updated_at datetime DEFAULT NULL,
            live_refdomains_count int(11) DEFAULT NULL,
            live_refdomains_top10_max int(11) DEFAULT NULL,
            live_backlinks_count int(11) DEFAULT NULL,
            live_backlinks_top10_max int(11) DEFAULT NULL,
            live_metrics_updated_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id_idx (project_id),
            KEY keyword_idx (keyword)
        ) {$charset_collate};";

        dbDelta($sql_bank);
        dbDelta($sql_project_keywords);

        update_option('SMARK_keyword_research_schema', $this->schema_version);
    }

    /**
     * Migrate database schema from version 1.0 to 1.1.
     *
     * @return void
     */
    private function migrate_to_1_1() {
        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            return;
        }

        // Check if columns already exist
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$project_keywords_table_sql}");
        $column_names = array_column($columns, 'Field');

        if (!in_array('page_link_status', $column_names)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN page_link_status varchar(20) DEFAULT 'not_checked' AFTER serp_features");
        }

        if (!in_array('page_link_url', $column_names)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN page_link_url varchar(500) DEFAULT NULL AFTER page_link_status");
        }
    }

    /**
     * Migrate database schema from version 1.1 to 1.2.
     *
     * @return void
     */
    private function migrate_to_1_2() {
        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            return;
        }

        // Check if columns already exist
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$project_keywords_table_sql}");
        $column_names = array_column($columns, 'Field');

        if (!in_array('rank_3month_avg', $column_names)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN rank_3month_avg decimal(6,2) DEFAULT NULL AFTER serp_features");
        }

        if (!in_array('rank_1month_avg', $column_names)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN rank_1month_avg decimal(6,2) DEFAULT NULL AFTER rank_3month_avg");
        }
    }

    /**
     * Migrate database schema from version 1.2 to 1.3.
     *
     * @return void
     */
    private function migrate_to_1_3() {
        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            return;
        }

        // Check if column already exists
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$project_keywords_table_sql}");
        $column_names = array_column($columns, 'Field');

        if (!in_array('ranking_trend', $column_names)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN ranking_trend varchar(20) DEFAULT NULL AFTER rank_1month_avg");

            // Calculate and update existing ranking trends
            $this->update_existing_ranking_trends();
        }
    }

    /**
     * Migrate database schema from version 1.3 to 1.4.
     *
     * Ensure the project keywords table contains page link columns.
     *
     * @return void
     */
    private function migrate_to_1_4() {
        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$project_keywords_table_sql}");
        $column_names = array_column($columns, 'Field');

        if (!in_array('page_link_status', $column_names, true)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN page_link_status varchar(20) DEFAULT 'not_checked' AFTER serp_features");
        }

        if (!in_array('page_link_url', $column_names, true)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN page_link_url varchar(500) DEFAULT NULL AFTER page_link_status");
        }
    }

    /**
     * Migrate database schema from version 1.4 to 1.5.
     *
     * Ensure the project keywords table contains ranking_updated_at column.
     *
     * @return void
     */
    private function migrate_to_1_5() {
        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$project_keywords_table_sql}");
        $column_names = array_column($columns, 'Field');

        if (!in_array('ranking_updated_at', $column_names, true)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN ranking_updated_at datetime DEFAULT NULL AFTER ranking_trend");
        }
    }

    /**
     * Migrate database schema from version 1.5 to 1.6.
     *
     * Ensure the project keywords table contains live rank columns.
     *
     * @return void
     */
    private function migrate_to_1_6() {
        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$project_keywords_table_sql}");
        $column_names = array_column($columns, 'Field');

        if (!in_array('live_rank_position', $column_names, true)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN live_rank_position int(11) DEFAULT NULL AFTER ranking_updated_at");
        }

        if (!in_array('live_rank_updated_at', $column_names, true)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN live_rank_updated_at datetime DEFAULT NULL AFTER live_rank_position");
        }
    }

    /**
     * Migrate database schema from version 1.6 to 1.7.
     *
     * Ensure live backlink/refdomain metric columns exist.
     *
     * @return void
     */
    private function migrate_to_1_7() {
        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$project_keywords_table_sql}");
        $column_names = array_column($columns, 'Field');

        if (!in_array('live_refdomains_count', $column_names, true)) {
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN live_refdomains_count int(11) DEFAULT NULL AFTER live_rank_updated_at"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }
        if (!in_array('live_refdomains_top10_max', $column_names, true)) {
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN live_refdomains_top10_max int(11) DEFAULT NULL AFTER live_refdomains_count"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }
        if (!in_array('live_backlinks_count', $column_names, true)) {
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN live_backlinks_count int(11) DEFAULT NULL AFTER live_refdomains_top10_max"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }
        if (!in_array('live_backlinks_top10_max', $column_names, true)) {
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN live_backlinks_top10_max int(11) DEFAULT NULL AFTER live_backlinks_count"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }
        if (!in_array('live_metrics_updated_at', $column_names, true)) {
            $wpdb->query("ALTER TABLE {$project_keywords_table_sql} ADD COLUMN live_metrics_updated_at datetime DEFAULT NULL AFTER live_backlinks_top10_max"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }
    }

    /**
     * Update ranking trends for existing records.
     *
     * @return void
     */
    private function update_existing_ranking_trends() {
        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $items = $wpdb->get_results("SELECT id, rank_3month_avg, rank_1month_avg FROM {$project_keywords_table_sql} WHERE rank_3month_avg IS NOT NULL OR rank_1month_avg IS NOT NULL", ARRAY_A);

        foreach ($items as $item) {
            $rank3month = $item['rank_3month_avg'] !== null ? (float) $item['rank_3month_avg'] : 0;
            $rank1month = $item['rank_1month_avg'] !== null ? (float) $item['rank_1month_avg'] : 0;

            $trend = $this->calculate_ranking_trend($rank3month, $rank1month);

            $wpdb->update(
                $this->project_keywords_table,
                array('ranking_trend' => $trend),
                array('id' => $item['id']),
                array('%s'),
                array('%d')
            );
        }
    }

    /**
     * Calculate ranking trend based on 3-month and 1-month averages.
     *
     * @param float $rank3month 3-month average ranking.
     * @param float $rank1month 1-month average ranking.
     * @return string 'decrease' or 'increase'
     */
    private function calculate_ranking_trend($rank3month, $rank1month) {
        // Red (decrease): if upward trend (rank3month < rank1month and neither is 0) OR if rank1month is 0
        if ($rank1month === 0) {
            return 'decrease';
        } elseif ($rank3month !== 0 && $rank1month !== 0 && $rank3month < $rank1month) {
            return 'decrease';
        }

        // Green (increase): otherwise
        return 'increase';
    }

    /**
     * Register admin submenu page (hidden from menu list).
     *
     * @return void
     */
    public function add_submenu_page() {
        add_submenu_page(
            null,
            __('Keyword Research', 'smark'),
            __('Keyword Research', 'smark'),
            'smark_access',
            'smark-keyword-research',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue assets for admin page.
     *
     * @param string $hook Current admin hook.
     * @return void
     */
    public function enqueue_assets($hook) {
        if (!isset($_GET['page']) || $_GET['page'] !== 'smark-keyword-research') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $current_lang = get_option('SMARK_panel_language', 'en');

        // Enqueue dashicons for icon support - ensure it loads early
        wp_enqueue_style('dashicons');

        // Also ensure dashicons loads in admin head
        add_action('admin_head', function() {
            if (!wp_style_is('dashicons', 'enqueued')) {
                wp_enqueue_style('dashicons');
            }
        }, 1);

        wp_enqueue_style(
            'vazirmatn-font',
            'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap',
            array(),
            SMARK_VERSION
        );

        wp_enqueue_style(
            'smark-keyword-research',
            SMARK_PLUGIN_URL . 'features/keyword-research/assets/keyword-research.css',
            array('dashicons'),
            SMARK_VERSION
        );

        wp_enqueue_script(
            'smark-keyword-research',
            SMARK_PLUGIN_URL . 'features/keyword-research/assets/keyword-research.js',
            array('jquery'),
            SMARK_VERSION,
            true
        );

        wp_localize_script(
            'smark-keyword-research',
            'SMarkKeywordResearch',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('SMARK_keyword_research_nonce'),
                'coreKeywordBankNonce' => wp_create_nonce('smark_core_keyword_bank_nonce'),
                'coreKeywordBankAction' => 'smark_core_kb_fetch_keyword_for_project',
                'strings' => $this->get_localized_strings($current_lang),
                'currentLang' => $current_lang,
                'defaultProject' => $this->get_current_site_project(),
                'backlinksManagementUrl' => admin_url('admin.php?page=smark-backlinks-management'),
            )
        );
    }

    /**
     * Render admin page.
     *
     * @return void
     */
    public function render_page() {
        $current_lang = get_option('SMARK_panel_language', 'en');
        $rtl_class    = $current_lang === 'fa' ? 'rtl' : '';
        $is_rtl       = ($current_lang === 'fa');
        $site_project = $this->get_current_site_project();
        $site_project_name = is_array($site_project) && isset($site_project['project_name']) ? (string) $site_project['project_name'] : '';
        ?>
        <div class="wrap smark-keyword-research-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
            <div class="smark-page-header">
                <h1><?php echo esc_html($this->translate('keyword_research_title')); ?></h1>
                <p class="description"><?php echo esc_html($this->translate('keyword_research_subtitle')); ?></p>
            </div>

            <div class="smark-breadcrumb">
                <div class="breadcrumb-left">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html($this->translate('SMARK_dashboard')); ?></a>
                    <span class="separator"><?php echo $is_rtl ? '‹' : '›'; ?></span>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-seo-optimization')); ?>"><?php echo esc_html($this->translate('breadcrumb_seo')); ?></a>
                    <span class="separator"><?php echo $is_rtl ? '‹' : '›'; ?></span>
                    <span class="current"><?php echo esc_html($this->translate('keyword_research_title')); ?></span>
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

            <div class="smark-keyword-research-content">
                <div class="layout-columns">
                    <div class="left-column">
                        <div class="project-selection-card">
                            <div class="card">
                                <div class="card-header">
                                    <h3><?php echo $is_rtl ? 'پروژه' : 'Project'; ?></h3>
                                </div>

                                <div class="card-body">
                                    <!-- Selected Project Display -->
                                    <div id="selected_project_display" class="selected-project">
                                        <div class="project-badge">
                                            <span class="dashicons dashicons-portfolio"></span>
                                            <?php if ($is_rtl): ?>
                                                <span class="project-label">پروژه:</span>
                                            <?php endif; ?>
                                            <span class="project-name"><?php echo esc_html($site_project_name); ?></span>
                                        </div>
                                        <div class="project-metadata">
                                            <span class="project-count">
                                                <span class="label"><?php echo esc_html($this->translate('keywords_in_project')); ?>:</span>
                                                <span class="value" data-count="project">0</span>
                                            </span>
                                            <span class="project-bank-count">
                                                <span class="label"><?php echo esc_html($this->translate('keywords_in_bank')); ?>:</span>
                                                <span class="value" data-count="bank">0</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="right-column">
                        <!-- Keywords Card (shown when project is selected) -->
                        <div class="card keywords-card" id="keywords_card" style="display: block;">
                            <div class="card-header-with-button">
                                <?php if ($is_rtl): ?>
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-outline open-bank-modal">
                                            <span class="dashicons dashicons-plus-alt2"></span>
                                            <?php echo esc_html($this->translate('add_keywords_button')); ?>
                                        </button>
                                        <button type="button" class="btn btn-outline smark-rankmath-gap-check" id="smarkRankMathGapCheckBtn">
                                            <span class="dashicons dashicons-chart-line"></span>
                                            <?php echo esc_html($this->translate('rankmath_gap_check_button')); ?>
                                        </button>
                                        <div class="project-keywords-search" role="search">
                                            <span class="dashicons dashicons-search" aria-hidden="true"></span>
                                            <input
                                                type="search"
                                                id="projectKeywordsSearch"
                                                placeholder="<?php echo esc_attr($is_rtl ? 'جست‌وجو در کلمات کلیدی پروژه…' : 'Search project keywords…'); ?>"
                                                autocomplete="off"
                                            />
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h3><?php echo esc_html($this->translate('project_keywords_title')); ?></h3>
                                    <div class="project-metadata keyword-research-stats">
                                        <span class="stat">
                                            <span class="label"><?php echo esc_html($this->translate('keywords_in_bank')); ?>:</span>
                                            <span class="value" id="keywordBankCount">0</span>
                                        </span>
                                        <span class="stat">
                                            <span class="label"><?php echo esc_html($this->translate('keywords_in_project')); ?>:</span>
                                            <span class="value" id="projectKeywordCount">0</span>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!$is_rtl): ?>
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-outline open-bank-modal">
                                            <span class="dashicons dashicons-plus-alt2"></span>
                                            <?php echo esc_html($this->translate('add_keywords_button')); ?>
                                        </button>
                                        <button type="button" class="btn btn-outline smark-rankmath-gap-check" id="smarkRankMathGapCheckBtn">
                                            <span class="dashicons dashicons-chart-line"></span>
                                            <?php echo esc_html($this->translate('rankmath_gap_check_button')); ?>
                                        </button>
                                        <div class="project-keywords-search" role="search">
                                            <span class="dashicons dashicons-search" aria-hidden="true"></span>
                                            <input
                                                type="search"
                                                id="projectKeywordsSearch"
                                                placeholder="<?php echo esc_attr($is_rtl ? 'جست‌وجو در کلمات کلیدی پروژه…' : 'Search project keywords…'); ?>"
                                                autocomplete="off"
                                            />
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="smark-rankmath-gap-notice" id="smarkRankMathGapNotice" style="display:none;" role="status" aria-live="polite"></div>

                            <div class="card-body table-wrapper">
                                <table class="data-table" id="projectKeywordsTable" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
                                    <thead>
                                        <tr>
                                            <?php if ($is_rtl): ?>
                                                <th><?php echo esc_html($this->translate('table_keyword')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_intent')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_volume')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_difficulty')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_cpc')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_serp')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_ranking')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_live_rank')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_refdomains')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_backlinks')); ?></th>
                                                <th class="ranking-updated-at-header ranking-updated-filter-header">
                                                    <div class="ranking-updated-filter-header-inner">
                                                        <span class="ranking-updated-filter-header-label"><?php echo esc_html($this->translate('table_ranking_updated_at')); ?></span>
                                                        <button
                                                            type="button"
                                                            class="ranking-updated-filter-toggle"
                                                            aria-haspopup="true"
                                                            aria-expanded="false"
                                                            aria-label="<?php echo esc_attr($this->translate('table_ranking_updated_at')); ?>"
                                                        >
                                                            <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                                        </button>
                                                        <div class="ranking-updated-filter-menu" role="menu" aria-label="<?php echo esc_attr($this->translate('table_ranking_updated_at')); ?>">
                                                            <button type="button" class="ranking-updated-filter-option is-active" data-filter="" role="menuitemradio" aria-checked="true"><?php echo esc_html($this->translate('ranking_updated_filter_all')); ?></button>
                                                            <button type="button" class="ranking-updated-filter-option" data-filter="needs_update" role="menuitemradio" aria-checked="false"><?php echo esc_html($this->translate('ranking_updated_filter_needs_update')); ?></button>
                                                            <button type="button" class="ranking-updated-filter-option" data-filter="updated" role="menuitemradio" aria-checked="false"><?php echo esc_html($this->translate('ranking_updated_filter_updated')); ?></button>
                                                        </div>
                                                    </div>
                                                </th>
                                                <th class="page-link-header">
                                                    <div class="page-link-header-inner">
                                                        <span class="page-link-header-label"><?php echo esc_html($this->translate('table_page_link')); ?></span>
                                                        <button
                                                            type="button"
                                                            class="page-link-filter-toggle"
                                                            aria-haspopup="true"
                                                            aria-expanded="false"
                                                            aria-label="<?php echo esc_attr($this->translate('table_page_link')); ?>"
                                                        >
                                                            <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                                        </button>
                                                        <div class="page-link-filter-menu" role="menu" aria-label="<?php echo esc_attr($this->translate('table_page_link')); ?>">
                                                            <button type="button" class="page-link-filter-option is-active" data-filter="" role="menuitemradio" aria-checked="true"><?php echo esc_html($this->translate('page_link_filter_all')); ?></button>
                                                            <button type="button" class="page-link-filter-option" data-filter="not_checked" role="menuitemradio" aria-checked="false"><?php echo esc_html($this->translate('page_link_filter_not_checked')); ?></button>
                                                            <button type="button" class="page-link-filter-option" data-filter="no_link" role="menuitemradio" aria-checked="false"><?php echo esc_html($this->translate('page_link_filter_no_link')); ?></button>
                                                            <button type="button" class="page-link-filter-option" data-filter="has_link" role="menuitemradio" aria-checked="false"><?php echo esc_html($this->translate('page_link_filter_has_link')); ?></button>
                                                        </div>
                                                    </div>
                                                </th>
                                                <th class="table-actions-column"><?php echo esc_html($this->translate('actions')); ?></th>
                                            <?php else: ?>
                                                <th><?php echo esc_html($this->translate('table_keyword')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_intent')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_volume')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_difficulty')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_cpc')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_serp')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_ranking')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_live_rank')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_refdomains')); ?></th>
                                                <th><?php echo esc_html($this->translate('table_backlinks')); ?></th>
                                                <th class="ranking-updated-at-header ranking-updated-filter-header">
                                                    <div class="ranking-updated-filter-header-inner">
                                                        <span class="ranking-updated-filter-header-label"><?php echo esc_html($this->translate('table_ranking_updated_at')); ?></span>
                                                        <button
                                                            type="button"
                                                            class="ranking-updated-filter-toggle"
                                                            aria-haspopup="true"
                                                            aria-expanded="false"
                                                            aria-label="<?php echo esc_attr($this->translate('table_ranking_updated_at')); ?>"
                                                        >
                                                            <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                                        </button>
                                                        <div class="ranking-updated-filter-menu" role="menu" aria-label="<?php echo esc_attr($this->translate('table_ranking_updated_at')); ?>">
                                                            <button type="button" class="ranking-updated-filter-option is-active" data-filter="" role="menuitemradio" aria-checked="true"><?php echo esc_html($this->translate('ranking_updated_filter_all')); ?></button>
                                                            <button type="button" class="ranking-updated-filter-option" data-filter="needs_update" role="menuitemradio" aria-checked="false"><?php echo esc_html($this->translate('ranking_updated_filter_needs_update')); ?></button>
                                                            <button type="button" class="ranking-updated-filter-option" data-filter="updated" role="menuitemradio" aria-checked="false"><?php echo esc_html($this->translate('ranking_updated_filter_updated')); ?></button>
                                                        </div>
                                                    </div>
                                                </th>
                                                <th class="page-link-header">
                                                    <div class="page-link-header-inner">
                                                        <span class="page-link-header-label"><?php echo esc_html($this->translate('table_page_link')); ?></span>
                                                        <button
                                                            type="button"
                                                            class="page-link-filter-toggle"
                                                            aria-haspopup="true"
                                                            aria-expanded="false"
                                                            aria-label="<?php echo esc_attr($this->translate('table_page_link')); ?>"
                                                        >
                                                            <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                                        </button>
                                                        <div class="page-link-filter-menu" role="menu" aria-label="<?php echo esc_attr($this->translate('table_page_link')); ?>">
                                                            <button type="button" class="page-link-filter-option is-active" data-filter="" role="menuitemradio" aria-checked="true"><?php echo esc_html($this->translate('page_link_filter_all')); ?></button>
                                                            <button type="button" class="page-link-filter-option" data-filter="not_checked" role="menuitemradio" aria-checked="false"><?php echo esc_html($this->translate('page_link_filter_not_checked')); ?></button>
                                                            <button type="button" class="page-link-filter-option" data-filter="no_link" role="menuitemradio" aria-checked="false"><?php echo esc_html($this->translate('page_link_filter_no_link')); ?></button>
                                                            <button type="button" class="page-link-filter-option" data-filter="has_link" role="menuitemradio" aria-checked="false"><?php echo esc_html($this->translate('page_link_filter_has_link')); ?></button>
                                                        </div>
                                                    </div>
                                                </th>
                                                <th class="table-actions-column"><?php echo esc_html($this->translate('actions')); ?></th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                                <div class="project-keywords-pagination" id="projectKeywordsPagination" aria-label="<?php echo esc_attr($is_rtl ? 'صفحه‌بندی' : 'Pagination'); ?>"></div>
                                <div class="empty-state" id="projectKeywordsEmpty">
                                    <div class="empty-state-content">
                                        <h4><?php echo esc_html($this->translate('empty_project_title')); ?></h4>
                                        <p><?php echo esc_html($this->translate('empty_project_subtitle')); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plugin Version Footer -->
            <div class="smark-version-footer">
                <div class="version-info">
                    <span class="version-label">پلاگین اسمارک</span>
                    <span class="version-separator">•</span>
                    <span class="version-number">v<?php echo esc_html(SMARK_VERSION); ?></span>
                </div>
            </div>
        </div>

        <?php $this->render_bank_modal(); ?>
        <?php $this->render_keyword_request_modal(); ?>
        <?php $this->render_rankmath_gap_modal(); ?>
        <?php
    }

    /**
     * Render modal for Rank Math focus keywords missing from the project sheet.
     *
     * @return void
     */
    private function render_rankmath_gap_modal() {
        $current_lang = get_option('SMARK_panel_language', 'en');
        $is_rtl = ($current_lang === 'fa');
        ?>
        <div class="smark-modal smark-rankmath-gap-modal<?php echo $is_rtl ? ' rtl' : ''; ?>" id="smarkRankMathGapModal" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
            <div class="smark-modal-dialog large">
                <div class="modal-header">
                    <h3><?php echo esc_html($this->translate('rankmath_gap_modal_title')); ?></h3>
                    <button type="button" class="modal-close" data-close="#smarkRankMathGapModal" aria-label="<?php echo esc_attr($this->translate('close_button')); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="smark-rankmath-gap-modal__hint"><?php echo esc_html($this->translate('rankmath_gap_modal_hint')); ?></p>
                    <div class="table-wrapper">
                        <table class="data-table" id="smarkRankMathGapTable" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
                            <thead>
                                <tr>
                                    <th><?php echo esc_html($this->translate('table_keyword')); ?></th>
                                    <th class="table-actions-column"><?php echo esc_html($this->translate('actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="smark-rankmath-gap-modal__meta" id="smarkRankMathGapMeta" aria-live="polite"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close="#smarkRankMathGapModal"><?php echo esc_html($this->translate('close_button')); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save language preference for Keyword Research page
     */
    public function ajax_save_language() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '';

        if (empty($language) || !in_array($language, array('en', 'fa'), true)) {
            wp_send_json_error(array('message' => __('Invalid language', 'smark')));
        }

        update_option('SMARK_panel_language', $language);

        if ($language === 'fa') {
            add_filter('locale', function () { return 'fa_IR'; });
        } else {
            add_filter('locale', function () { return 'en_US'; });
        }

        wp_send_json_success(array('language' => $language));
    }
    /**
     * Render modal for creating project.
     *
     * @return void
     */
    private function render_project_modal() {
        ?>
        <div class="smark-modal" id="smarkProjectModal">
            <div class="smark-modal-dialog">
                <div class="modal-header">
                    <h3><?php echo esc_html($this->translate('create_project_heading')); ?></h3>
                    <button type="button" class="modal-close" data-close="#smarkProjectModal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="smarkProjectName"><?php echo esc_html($this->translate('project_name_label')); ?></label>
                        <input type="text" id="smarkProjectName" class="form-control" placeholder="<?php echo esc_attr($this->translate('project_name_placeholder')); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close="#smarkProjectModal"><?php echo esc_html($this->translate('cancel_button')); ?></button>
                    <button type="button" class="btn btn-primary" id="smarkSaveProject"><?php echo esc_html($this->translate('save_button')); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render modal for selecting keywords from bank.
     *
     * @return void
     */
    private function render_bank_modal() {
        $current_lang = get_option('SMARK_panel_language', 'en');
        $is_rtl = ($current_lang === 'fa');
        ?>
        <div class="smark-modal smark-bank-modal<?php echo $is_rtl ? ' rtl' : ''; ?>" id="smarkBankModal" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
            <div class="smark-modal-dialog large">
                <div class="modal-header smark-bank-modal__header">
                    <h3><?php echo esc_html($this->translate('bank_modal_title')); ?></h3>
                    <button type="button" class="modal-close smark-bank-modal__close" data-close="#smarkBankModal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body smark-bank-modal__body">
                    <div class="bank-search-bar smark-bank-modal__search-bar">
                        <?php
                        if ($is_rtl) {
                            // RTL: Input first, then button (button on the left)
                            ?>
                            <input type="search" id="smarkBankSearch" class="form-control smark-bank-modal__search-input" placeholder="<?php echo esc_attr($this->translate('bank_search_placeholder')); ?>">
                            <button type="button" class="btn btn-outline smark-bank-modal__search-button" id="smarkBankSearchButton">
                                <span class="dashicons dashicons-search"></span>
                                <?php echo esc_html($this->translate('search_button')); ?>
                            </button>
                            <div class="smark-bank-modal__match-wrap">
                                <div class="btn btn-outline smark-bank-modal__match-button" aria-hidden="true">
                                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                    <span class="smark-bank-modal__match-label">Broad</span>
                                </div>
                                <select id="smarkBankMatchType" class="smark-bank-modal__match-select" aria-label="Search match type">
                                    <option value="broad">Broad</option>
                                    <option value="exact">Exact</option>
                                </select>
                            </div>
                            <?php
                        } else {
                            // LTR: Input first, then button
                            ?>
                            <input type="search" id="smarkBankSearch" class="form-control smark-bank-modal__search-input" placeholder="<?php echo esc_attr($this->translate('bank_search_placeholder')); ?>">
                            <button type="button" class="btn btn-outline smark-bank-modal__search-button" id="smarkBankSearchButton">
                                <span class="dashicons dashicons-search"></span>
                                <?php echo esc_html($this->translate('search_button')); ?>
                            </button>
                            <div class="smark-bank-modal__match-wrap">
                                <div class="btn btn-outline smark-bank-modal__match-button" aria-hidden="true">
                                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                                    <span class="smark-bank-modal__match-label">Broad</span>
                                </div>
                                <select id="smarkBankMatchType" class="smark-bank-modal__match-select" aria-label="Search match type">
                                    <option value="broad">Broad</option>
                                    <option value="exact">Exact</option>
                                </select>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <div class="bank-results smark-bank-modal__results">
                        <table class="data-table smark-bank-modal__table" id="smarkBankTable" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
                            <thead>
                                <tr>
                                    <th><?php echo esc_html($this->translate('table_keyword')); ?></th>
                                    <th><?php echo esc_html($this->translate('table_intent')); ?></th>
                                    <th><?php echo esc_html($this->translate('table_volume')); ?></th>
                                    <th><?php echo esc_html($this->translate('table_difficulty')); ?></th>
                                    <th><?php echo esc_html($this->translate('table_cpc')); ?></th>
                                    <th><?php echo esc_html($this->translate('table_serp')); ?></th>
                                    <th class="select-column" style="display: none;"><?php echo esc_html($this->translate('select')); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <p class="bank-search-hint smark-bank-modal__hint" id="smarkBankSearchHint"><?php echo esc_html($this->translate('bank_search_hint')); ?></p>
                        <div class="bank-loading smark-bank-modal__loading" id="smarkBankLoading" aria-hidden="true" style="display:none;">
                            <span class="dashicons dashicons-update dashicons-spin"></span>
                        </div>
                        <div class="empty-state smark-bank-modal__empty" id="bankEmptyState">
                            <div class="empty-state-content smark-bank-modal__empty-content">
                                <h4><?php echo esc_html($this->translate('bank_empty_title')); ?></h4>
                                <p><?php echo esc_html($this->translate('bank_empty_subtitle')); ?></p>
                                <button type="button" class="btn btn-primary smark-bank-modal__request-button" id="smarkBankRequestKeywordBtn" title="<?php echo esc_attr($this->translate('mark_cost_tooltip')); ?>">
                                    <?php echo esc_html($this->translate('request_keyword_button')); ?>
                                    <span class="kr-smark-cost-badge" aria-hidden="true">1</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <?php
                    // For RTL, render the close button first so it stays on the right side.
                    if ($is_rtl) {
                        ?>
                        <button type="button" class="btn btn-outline" data-close="#smarkBankModal">
                            <?php echo esc_html($this->translate('close_button')); ?>
                        </button>
                        <button type="button" class="btn btn-success" id="smarkAddSelectedKeywords" style="display: none;">
                            <?php echo esc_html($this->translate('add_selected_keywords')); ?>
                        </button>
                        <?php
                    } else {
                        ?>
                        <button type="button" class="btn btn-success" id="smarkAddSelectedKeywords" style="display: none;">
                            <?php echo esc_html($this->translate('add_selected_keywords')); ?>
                        </button>
                        <button type="button" class="btn btn-outline" data-close="#smarkBankModal">
                            <?php echo esc_html($this->translate('close_button')); ?>
                        </button>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render modal for requesting new keywords to be added to the central bank.
     *
     * @return void
     */
    private function render_keyword_request_modal() {
        $is_rtl = (get_option('SMARK_panel_language', 'en') === 'fa');
        ?>
        <div class="smark-modal smark-keyword-request-modal<?php echo $is_rtl ? ' rtl' : ''; ?>" id="smarkKeywordRequestModal" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
            <div class="smark-modal-dialog smark-keyword-request-modal__dialog">
                <div class="modal-header smark-keyword-request-modal__header">
                    <h3><?php echo esc_html($this->translate('request_modal_title')); ?></h3>
                    <button type="button" class="modal-close smark-keyword-request-modal__close" data-close="#smarkKeywordRequestModal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body smark-keyword-request-modal__body">
                    <p class="request-modal-description smark-keyword-request-modal__description"><?php echo esc_html($this->translate('request_modal_description')); ?></p>
                    <textarea id="smarkKeywordRequestList" class="form-control smark-keyword-request-modal__textarea" rows="8" placeholder="<?php echo esc_attr($this->translate('request_modal_placeholder')); ?>" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>></textarea>
                </div>
                <div class="modal-footer smark-keyword-request-modal__footer">
                    <button type="button" class="btn btn-outline smark-keyword-request-modal__close-btn" data-close="#smarkKeywordRequestModal">
                        <?php echo esc_html($this->translate('close_button')); ?>
                    </button>
                    <button type="button" class="btn btn-success smark-keyword-request-modal__submit" id="smarkKeywordRequestSubmit">
                        <?php echo esc_html($this->translate('request_modal_submit')); ?>
                    </button>
                </div>
                <div class="modal-loading smark-keyword-request-modal__loading" id="smarkKeywordRequestLoading" aria-hidden="true" style="display:none;">
                    <span class="dashicons dashicons-update dashicons-spin"></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle AJAX: Submit keyword request(s) to the central keyword bank.
     *
     * @return void
     */
    public function ajax_request_keywords() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $raw = isset($_POST['keywords']) ? wp_unslash($_POST['keywords']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below based on type.
        $keywords = array();

        if (is_array($raw)) {
            $keywords = array_map('sanitize_text_field', array_map('strval', $raw));
        } else {
            $text = sanitize_textarea_field((string) $raw);
            $lines = preg_split('/\\r\\n|\\r|\\n/', $text);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $keywords[] = sanitize_text_field($line);
                }
            }
        }

        $keywords = array_values(array_unique(array_filter(array_map('trim', $keywords))));
        if (empty($keywords)) {
            wp_send_json_error(array('message' => $this->translate('keyword_request_empty')));
        }

        if (count($keywords) > 200) {
            $keywords = array_slice($keywords, 0, 200);
        }

        $user = wp_get_current_user();
        $payload = array(
            'site_url' => rtrim((string) home_url('/'), '/'),
            'requester_name' => is_object($user) ? (string) $user->display_name : '',
            'requester_email' => is_object($user) ? (string) $user->user_email : '',
            'keywords' => $keywords,
        );

        $endpoint = $this->get_central_keyword_bank_base_url() . '/wp-json/smark-core/v1/keyword-bank/requests';
        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
        );

        $token = $this->get_central_sync_token();
        if ($token !== '') {
            $headers['x-smark-sync-token'] = $token;
        }

        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => 15,
                'headers' => $headers,
                'body' => wp_json_encode($payload),
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            wp_send_json_error(array('message' => $this->translate('keyword_request_failed')));
        }

        wp_send_json_success(array('message' => $this->translate('keyword_request_success')));
    }

    /**
     * Handle AJAX: Fetch projects list.
     *
     * @return void
     */
    public function ajax_get_projects() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => $this->translate('error_missing_project')));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $projects = $wpdb->get_results("SELECT id, project_name FROM {$projects_table_sql} ORDER BY project_name ASC", ARRAY_A);

        wp_send_json_success(
            array(
                'projects' => $projects ?: array(),
            )
        );
    }

    /**
     * Handle AJAX: Create project.
     *
     * @return void
     */
    public function ajax_create_project() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $current_lang = get_option('SMARK_panel_language', 'en');
        $message = ($current_lang === 'fa')
            ? 'ایجاد پروژه جدید در نسخه فعلی غیرفعال است.'
            : 'Creating a new project is disabled in this version.';
        wp_send_json_error(array('message' => $message));

        $project_name = isset($_POST['projectName']) ? sanitize_text_field(wp_unslash($_POST['projectName'])) : '';

        if (empty($project_name)) {
            wp_send_json_error(array('message' => $this->translate('error_missing_project_name')));
        }

        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => $this->translate('error_missing_project')));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$projects_table_sql} WHERE project_name = %s", $project_name));

        if ($exists > 0) {
            wp_send_json_error(array('message' => $this->translate('error_duplicate_project')));
        }

        $inserted = $wpdb->insert(
            $this->projects_table,
            array(
                'project_name' => $project_name,
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ),
            array('%s', '%s', '%s')
        );

        if (!$inserted) {
            wp_send_json_error(array('message' => $this->translate('error_project_create')));
        }

        wp_send_json_success(
            array(
                'id'   => (int) $wpdb->insert_id,
                'name' => $project_name,
            )
        );
    }

    /**
     * Handle AJAX: Fetch project keywords.
     *
     * @return void
     */
    public function ajax_get_project_items() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $project_id = isset($_GET['projectId']) ? (int) $_GET['projectId'] : 0;
        $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
        $query = trim($query);
        $page_link_filter_raw = isset($_GET['pageLinkFilter']) ? sanitize_key(wp_unslash((string) $_GET['pageLinkFilter'])) : '';
        $allowed_filters = array('not_checked', 'no_link', 'has_link');
        $page_link_filter = in_array($page_link_filter_raw, $allowed_filters, true) ? $page_link_filter_raw : '';
        $ranking_updated_filter_raw = isset($_GET['rankingUpdatedFilter']) ? sanitize_key(wp_unslash((string) $_GET['rankingUpdatedFilter'])) : '';
        $allowed_ranking_filters = array('needs_update', 'updated');
        $ranking_updated_filter = in_array($ranking_updated_filter_raw, $allowed_ranking_filters, true) ? $ranking_updated_filter_raw : '';
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        if ($paged < 1) {
            $paged = 1;
        }
        $per_page = 10;

        if ($project_id <= 0) {
            wp_send_json_error(array('message' => $this->translate('error_missing_project')));
        }

        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            wp_send_json_error(array('message' => $this->translate('error_missing_project')));
        }

        // Total keywords in project (unfiltered).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total_project_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $project_keywords_table_sql . ' WHERE project_id = %d', $project_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $where_extra = '';
        $where_args = array();
        if ($page_link_filter === 'not_checked') {
            $where_extra = " AND page_link_status = 'not_checked'";
        } elseif ($page_link_filter === 'no_link') {
            $where_extra = " AND (page_link_status = 'not_found' OR page_link_status = 'not_connected')";
        } elseif ($page_link_filter === 'has_link') {
            $where_extra = " AND page_link_status = 'found' AND page_link_url IS NOT NULL AND page_link_url <> ''";
        }

        $like = ($query !== '') ? '%' . $wpdb->esc_like($query) . '%' : '%';
        $threshold = wp_date('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
        if ($ranking_updated_filter === 'updated') {
            $where_extra .= " AND ranking_updated_at IS NOT NULL AND ranking_updated_at <> '' AND ranking_updated_at >= %s";
            $where_args[] = $threshold;
        } elseif ($ranking_updated_filter === 'needs_update') {
            $where_extra .= " AND (ranking_updated_at IS NULL OR ranking_updated_at = '' OR ranking_updated_at < %s)";
            $where_args[] = $threshold;
        }

        $count_sql = 'SELECT COUNT(*) FROM ' . $project_keywords_table_sql . ' WHERE project_id = %d AND keyword LIKE %s' . $where_extra;
        $count_args = array_merge(array($project_id, $like), $where_args);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $filtered_count = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$count_args)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $total_pages = 0;
        if ($filtered_count > 0) {
            $total_pages = (int) ceil($filtered_count / $per_page);
            if ($paged > $total_pages) {
                $paged = $total_pages;
            }
        } else {
            $paged = 1;
        }

        $offset = ($paged - 1) * $per_page;

        $items = array();
        if ($filtered_count > 0) {
            $items_sql = 'SELECT id, keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features, rank_3month_avg, rank_1month_avg, ranking_trend, ranking_updated_at, live_rank_position, live_rank_updated_at, live_refdomains_count, live_refdomains_top10_max, live_backlinks_count, live_backlinks_top10_max, live_metrics_updated_at, page_link_status, page_link_url, updated_at FROM ' . $project_keywords_table_sql . ' WHERE project_id = %d AND keyword LIKE %s' . $where_extra . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
            $items_args = array_merge(array($project_id, $like), $where_args, array($per_page, $offset));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $items = $wpdb->get_results($wpdb->prepare($items_sql, ...$items_args), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (!is_array($items)) {
                $items = array();
            }
        }

        $bank_table_sql = $this->escape_db_identifier($this->keyword_bank_table);
        $bank_map = array();
        if ($bank_table_sql !== '' && !empty($items)) {
            $keywords = array();
            foreach ($items as $it) {
                $kw = isset($it['keyword']) ? sanitize_text_field((string) $it['keyword']) : '';
                $kw = trim($kw);
                if ($kw !== '') {
                    $keywords[] = $kw;
                }
            }
            $keywords = array_values(array_unique($keywords));

            if (!empty($keywords)) {
                $placeholders = implode(',', array_fill(0, count($keywords), '%s'));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $wpdb->prepare('SELECT keyword, updated_at FROM ' . $bank_table_sql . ' WHERE keyword IN (' . $placeholders . ')', ...$keywords), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    ARRAY_A
                );
                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        $k = isset($row['keyword']) ? (string) $row['keyword'] : '';
                        $u = isset($row['updated_at']) ? (string) $row['updated_at'] : '';
                        if ($k !== '') {
                            $bank_map[$k] = $u;
                        }
                    }
                }
            }
        }

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $display_format = trim($date_format . ' ' . $time_format);
        $lang = $this->get_panel_language();
        foreach ($items as &$item) {
            $item['intent'] = $this->interpret_intent_value(isset($item['intent']) ? (string) $item['intent'] : '', $lang);
            $item['serp_features'] = $this->interpret_serp_features_value(isset($item['serp_features']) ? (string) $item['serp_features'] : '', $lang);

            if (!empty($item['ranking_updated_at'])) {
                $timestamp = mysql2date('U', $item['ranking_updated_at'], false);
                if (!empty($timestamp)) {
                    $item['ranking_updated_at_display'] = wp_date($display_format, (int) $timestamp);
                }
            }
            if (!empty($item['live_rank_updated_at'])) {
                $ts_live = mysql2date('U', $item['live_rank_updated_at'], false);
                if (!empty($ts_live)) {
                    $item['live_rank_updated_at_display'] = wp_date($display_format, (int) $ts_live);
                }
            }
            if (!empty($item['live_metrics_updated_at'])) {
                $ts_metrics = mysql2date('U', $item['live_metrics_updated_at'], false);
                if (!empty($ts_metrics)) {
                    $item['live_metrics_updated_at_display'] = wp_date($display_format, (int) $ts_metrics);
                }
            }
            if (!empty($item['updated_at'])) {
                $ts_updated = mysql2date('U', $item['updated_at'], false);
                if (!empty($ts_updated)) {
                    $item['updated_at_display'] = wp_date($display_format, (int) $ts_updated);
                }
            }
            $kw = isset($item['keyword']) ? (string) $item['keyword'] : '';
            $item['bank_updated_at'] = isset($bank_map[$kw]) ? (string) $bank_map[$kw] : null;
            $item['page_link_post_id'] = 0;
            $page_link_url = isset($item['page_link_url']) ? trim((string) $item['page_link_url']) : '';
            if ($page_link_url !== '' && isset($item['page_link_status']) && (string) $item['page_link_status'] === 'found') {
                $page_link_post_id = (int) url_to_postid($page_link_url);
                if ($page_link_post_id <= 0) {
                    $page_link_post_id = (int) attachment_url_to_postid($page_link_url);
                }
                $item['page_link_post_id'] = max(0, $page_link_post_id);
            }
        }
        unset($item);

        wp_send_json_success(
            array(
                'items'             => $items,
                'totalProjectCount' => $total_project_count,
                'filteredCount'     => $filtered_count,
                'perPage'           => $per_page,
                'page'              => $paged,
                'totalPages'        => $total_pages,
                'query'             => $query,
                'pageLinkFilter'    => $page_link_filter,
                'rankingUpdatedFilter' => $ranking_updated_filter,
            )
        );
    }

    /**
     * Handle AJAX: Refresh keyword data from the keyword bank.
     *
     * @return void
     */
    public function ajax_refresh_keyword_data_from_bank() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $project_id = isset($_POST['projectId']) ? (int) $_POST['projectId'] : 0;
        $item_id = isset($_POST['itemId']) ? (int) $_POST['itemId'] : 0;
        if ($project_id <= 0 || $item_id <= 0) {
            wp_send_json_error(array('message' => $this->translate('error_missing_item')));
        }

        global $wpdb;

        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        $bank_table_sql = $this->escape_db_identifier($this->keyword_bank_table);
        if ($project_keywords_table_sql === '' || $bank_table_sql === '') {
            wp_send_json_error(array('message' => $this->translate('error_generic')));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row($wpdb->prepare('SELECT id, project_id, keyword FROM ' . $project_keywords_table_sql . ' WHERE id = %d LIMIT 1', $item_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if (!is_array($row) || empty($row)) {
            wp_send_json_error(array('message' => $this->translate('error_missing_item')));
        }

        if ((int) $row['project_id'] !== $project_id) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $keyword = isset($row['keyword']) ? sanitize_text_field((string) $row['keyword']) : '';
        $keyword = trim($keyword);
        if ($keyword === '') {
            wp_send_json_error(array('message' => $this->translate('error_missing_item')));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $bank = $wpdb->get_row($wpdb->prepare('SELECT id, keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features, updated_at FROM ' . $bank_table_sql . ' WHERE keyword = %s LIMIT 1', $keyword), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if (!is_array($bank) || empty($bank)) {
            wp_send_json_error(array('message' => $this->translate('error_generic')));
        }

        $lang = $this->get_panel_language();
        $intent = $this->interpret_intent_value(isset($bank['intent']) ? (string) $bank['intent'] : '', $lang);
        $intent = is_string($intent) ? sanitize_text_field($intent) : null;
        $serp_features = $this->interpret_serp_features_value(isset($bank['serp_features']) ? (string) $bank['serp_features'] : '', $lang);
        $serp_features = is_string($serp_features) ? sanitize_text_field($serp_features) : null;

        $data = array(
            'keyword_bank_id' => isset($bank['id']) ? (int) $bank['id'] : null,
            'intent' => $intent,
            'volume' => isset($bank['volume']) ? (int) $bank['volume'] : null,
            'keyword_difficulty' => isset($bank['keyword_difficulty']) ? (float) $bank['keyword_difficulty'] : null,
            'cpc_usd' => isset($bank['cpc_usd']) ? (float) $bank['cpc_usd'] : null,
            'serp_features' => $serp_features,
        );
        $format = array('%d', '%s', '%d', '%f', '%f', '%s');
        $where = array('id' => $item_id, 'project_id' => $project_id);
        $where_format = array('%d', '%d');

        $updated = $wpdb->update($this->project_keywords_table, $data, $where, $format, $where_format);
        if ($updated === false) {
            wp_send_json_error(array('message' => $this->translate('error_generic')));
        }

        // Re-fetch updated row to return accurate values.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $fresh = $wpdb->get_row($wpdb->prepare('SELECT id, keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features, updated_at FROM ' . $project_keywords_table_sql . ' WHERE id = %d LIMIT 1', $item_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if (!is_array($fresh) || empty($fresh)) {
            wp_send_json_success(array('updated' => 1));
        }

        $fresh['intent'] = $this->interpret_intent_value(isset($fresh['intent']) ? (string) $fresh['intent'] : '', $lang);
        $fresh['serp_features'] = $this->interpret_serp_features_value(isset($fresh['serp_features']) ? (string) $fresh['serp_features'] : '', $lang);

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $display_format = trim($date_format . ' ' . $time_format);
        if (!empty($fresh['updated_at'])) {
            $ts = mysql2date('U', $fresh['updated_at'], false);
            if (!empty($ts)) {
                $fresh['updated_at_display'] = wp_date($display_format, (int) $ts);
            }
        }
        $fresh['bank_updated_at'] = isset($bank['updated_at']) ? (string) $bank['updated_at'] : null;

        wp_send_json_success(array('item' => $fresh));
    }

    /**
     * Fetch a central keyword bank item by ID.
     *
     * @param int $id Central bank item ID.
     * @return array|null|WP_Error
     */
    private function fetch_central_keyword_bank_item_by_id($id) {
        $id = absint($id);
        if ($id <= 0) {
            return null;
        }

        $endpoint = $this->get_central_keyword_bank_base_url() . '/wp-json/smark-core/v1/keyword-bank/items';
        $args = array(
            'timeout' => 15,
            'headers' => array(),
        );
        $token = $this->get_central_sync_token();
        if ($token !== '') {
            $args['headers']['x-smark-sync-token'] = $token;
        }

        $url = add_query_arg(array('ids' => (string) $id), $endpoint);
        $resp = wp_remote_get($url, $args);
        if (is_wp_error($resp)) {
            return $resp;
        }

        if ((int) wp_remote_retrieve_response_code($resp) !== 200) {
            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($resp), true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
            return null;
        }

        $item = $data['items'][0];
        return is_array($item) ? $item : null;
    }

    /**
     * Fetch a central keyword bank item by exact keyword match.
     *
     * @param string $keyword Keyword text.
     * @return array|null|WP_Error
     */
    private function fetch_central_keyword_bank_item_by_keyword($keyword) {
        $keyword = is_string($keyword) ? trim($keyword) : '';
        if ($keyword === '') {
            return null;
        }

        $endpoint = $this->get_central_keyword_bank_base_url() . '/wp-json/smark-core/v1/keyword-bank/search';
        $args = array(
            'timeout' => 15,
            'headers' => array(),
        );
        $token = $this->get_central_sync_token();
        if ($token !== '') {
            $args['headers']['x-smark-sync-token'] = $token;
        }

        $url = add_query_arg(
            array(
                'q' => $keyword,
                'limit' => 25,
                'match' => 'exact',
            ),
            $endpoint
        );

        $resp = wp_remote_get($url, $args);
        if (is_wp_error($resp)) {
            return $resp;
        }

        if ((int) wp_remote_retrieve_response_code($resp) !== 200) {
            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($resp), true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
            return null;
        }

        $needle = strtolower(trim($keyword));
        foreach ($data['items'] as $item) {
            if (!is_array($item) || !isset($item['keyword'])) {
                continue;
            }
            $kw = strtolower(trim((string) $item['keyword']));
            if ($kw === $needle) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Refresh project keyword row from central keyword bank (and update local bank cache when possible).
     *
     * @param int $item_id Project keyword row ID.
     * @param int $project_id Project ID.
     * @return array<string,mixed>|WP_Error
     */
    private function refresh_project_keyword_from_central_bank($item_id, $project_id) {
        global $wpdb;

        $item_id = absint($item_id);
        $project_id = absint($project_id);
        if ($item_id <= 0 || $project_id <= 0) {
            return new WP_Error('invalid', $this->translate('error_missing_item'));
        }

        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        $bank_table_sql = $this->escape_db_identifier($this->keyword_bank_table);
        if ($project_keywords_table_sql === '' || $bank_table_sql === '') {
            return new WP_Error('db', $this->translate('error_missing_project'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, project_id, keyword_bank_id, keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features FROM ' . $project_keywords_table_sql . ' WHERE id = %d LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $item_id
            ),
            ARRAY_A
        );
        if (!is_array($row) || empty($row)) {
            return new WP_Error('missing', $this->translate('error_missing_item'));
        }
        if ((int) $row['project_id'] !== $project_id) {
            return new WP_Error('forbidden', $this->translate('error_permissions'));
        }

        $keyword = isset($row['keyword']) ? trim((string) $row['keyword']) : '';
        if ($keyword === '') {
            return new WP_Error('missing', $this->translate('error_missing_item'));
        }

        $central = null;
        $central_source = 'none';

        $central_id = isset($row['keyword_bank_id']) ? absint($row['keyword_bank_id']) : 0;
        if ($central_id > 0) {
            $central = $this->fetch_central_keyword_bank_item_by_id($central_id);
            if (is_wp_error($central)) {
                return $central;
            }
            if (is_array($central)) {
                $central_source = 'id';
            }
        }

        if (!$central) {
            $central = $this->fetch_central_keyword_bank_item_by_keyword($keyword);
            if (is_wp_error($central)) {
                return $central;
            }
            if (is_array($central)) {
                $central_source = 'keyword';
            }
        }

        if (!$central || !is_array($central)) {
            return array(
                'found' => false,
                'changed' => false,
                'source' => 'none',
            );
        }

        $central_keyword = isset($central['keyword']) ? trim((string) $central['keyword']) : $keyword;
        $central_id = isset($central['id']) ? absint($central['id']) : $central_id;

        $central_intent = isset($central['intent']) ? (string) $central['intent'] : null;
        $central_volume = isset($central['volume']) && $central['volume'] !== '' ? (int) $central['volume'] : null;
        $central_kd = isset($central['keyword_difficulty']) && $central['keyword_difficulty'] !== '' ? (float) $central['keyword_difficulty'] : null;
        $central_cpc = isset($central['cpc_usd']) && $central['cpc_usd'] !== '' ? (float) $central['cpc_usd'] : null;
        $central_serp = isset($central['serp_features']) ? (string) $central['serp_features'] : null;
        $central_updated_at = isset($central['updated_at']) && is_string($central['updated_at']) ? trim($central['updated_at']) : '';

        $lang = $this->get_panel_language();
        $central_intent_label = $this->interpret_intent_value($central_intent, $lang);
        $central_intent_label = is_string($central_intent_label) ? sanitize_text_field($central_intent_label) : null;
        $central_serp_label = $this->interpret_serp_features_value($central_serp, $lang);
        $central_serp_label = is_string($central_serp_label) ? sanitize_text_field($central_serp_label) : null;

        if ($central_id > 0 && (int) $row['keyword_bank_id'] !== $central_id) {
            $wpdb->update(
                $this->project_keywords_table,
                array('keyword_bank_id' => $central_id),
                array('id' => $item_id),
                array('%d'),
                array('%d')
            );
        }

        // Upsert into local bank cache by keyword.
        $lookup = strtolower($central_keyword);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $local_bank_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$bank_table_sql} WHERE LOWER(keyword) = %s LIMIT 1", $lookup));

        $bank_payload = array(
            'keyword' => $central_keyword,
            'intent' => $central_intent_label,
            'volume' => $central_volume,
            'keyword_difficulty' => $central_kd,
            'cpc_usd' => $central_cpc,
            'serp_features' => $central_serp_label,
        );
        $bank_format = array('%s', '%s', '%d', '%f', '%f', '%s');
        if ($central_updated_at !== '') {
            $bank_payload['updated_at'] = $central_updated_at;
            $bank_format[] = '%s';
        }

        if ($local_bank_id > 0) {
            $wpdb->update(
                $this->keyword_bank_table,
                $bank_payload,
                array('id' => $local_bank_id),
                $bank_format,
                array('%d')
            );
        } else {
            $bank_payload['created_at'] = current_time('mysql');
            $bank_format[] = '%s';
            $wpdb->insert($this->keyword_bank_table, $bank_payload, $bank_format);
            $local_bank_id = (int) $wpdb->insert_id;
        }

        // Update project keyword fields only if central differs (avoid unnecessary updated_at changes).
        $normalize = static function($v) {
            if ($v === null) {
                return null;
            }
            if (is_string($v)) {
                $v = trim($v);
                return $v === '' ? null : $v;
            }
            return $v;
        };

        $project_current = array(
            'intent' => $normalize($row['intent'] ?? null),
            'volume' => $row['volume'] !== null ? (int) $row['volume'] : null,
            'keyword_difficulty' => $row['keyword_difficulty'] !== null ? (float) $row['keyword_difficulty'] : null,
            'cpc_usd' => $row['cpc_usd'] !== null ? (float) $row['cpc_usd'] : null,
            'serp_features' => $normalize($row['serp_features'] ?? null),
        );
        $project_new = array(
            'intent' => $normalize($central_intent_label),
            'volume' => $central_volume,
            'keyword_difficulty' => $central_kd,
            'cpc_usd' => $central_cpc,
            'serp_features' => $normalize($central_serp_label),
        );

        $changed = false;
        foreach ($project_new as $k => $v) {
            if ($project_current[$k] !== $v) {
                $changed = true;
                break;
            }
        }

        if ($changed) {
            $wpdb->update(
                $this->project_keywords_table,
                array(
                    'intent' => $project_new['intent'],
                    'volume' => $project_new['volume'],
                    'keyword_difficulty' => $project_new['keyword_difficulty'],
                    'cpc_usd' => $project_new['cpc_usd'],
                    'serp_features' => $project_new['serp_features'],
                ),
                array('id' => $item_id, 'project_id' => $project_id),
                array('%s', '%d', '%f', '%f', '%s'),
                array('%d', '%d')
            );
        }

        return array(
            'found' => true,
            'changed' => $changed,
            'source' => $central_source,
            'central_id' => $central_id,
            'local_bank_id' => $local_bank_id,
            'central_updated_at' => $central_updated_at !== '' ? $central_updated_at : null,
        );
    }

    /**
     * Refresh ranking for a keyword item (Search Console).
     *
     * @param int $item_id Item ID.
     * @param int $project_id Project ID.
     * @return array<string,mixed>|WP_Error
     */
    private function refresh_ranking_for_item($item_id, $project_id) {
        $item_id = absint($item_id);
        $project_id = absint($project_id);
        if ($item_id <= 0 || $project_id <= 0) {
            return new WP_Error('invalid', $this->translate('error_missing_item'));
        }

        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($project_keywords_table_sql === '' || $projects_table_sql === '') {
            return new WP_Error('db', $this->translate('error_missing_project'));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $keyword_item = $wpdb->get_row($wpdb->prepare("SELECT keyword, page_link_url FROM {$project_keywords_table_sql} WHERE id = %d", $item_id), ARRAY_A);
        if (!$keyword_item || empty($keyword_item['keyword'])) {
            return new WP_Error('missing', $this->translate('error_missing_item'));
        }
        $keyword = (string) $keyword_item['keyword'];

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project = $wpdb->get_row($wpdb->prepare("SELECT website, search_console_tokens FROM {$projects_table_sql} WHERE id = %d", $project_id), ARRAY_A);
        if (!$project) {
            return new WP_Error('missing_project', $this->translate('error_missing_project'));
        }

        if (empty($project['search_console_tokens'])) {
            return new WP_Error('not_connected', $this->translate('sc_not_connected'));
        }

        $tokens = $this->decode_search_console_tokens((string) $project['search_console_tokens']);
        if (!$tokens || !isset($tokens['access_token'])) {
            return new WP_Error('tokens', $this->translate('sc_invalid_tokens'));
        }

        if (!$this->is_encrypted_search_console_tokens((string) $project['search_console_tokens'])) {
            $wpdb->update(
                $this->projects_table,
                array('search_console_tokens' => $this->encode_search_console_tokens($tokens)),
                array('id' => $project_id),
                array('%s'),
                array('%d')
            );
        }

        $website = isset($project['website']) ? (string) $project['website'] : '';
        $website = trim($website);
        $website = rtrim($website, '/');

        $access_token = $this->get_valid_access_token_for_project($tokens, $project_id, $website);
        if (!$access_token) {
            return new WP_Error('tokens', $this->translate('sc_invalid_tokens'));
        }

        $end_date = gmdate('Y-m-d');
        $start_date_1month = gmdate('Y-m-d', strtotime('-1 month', time()));
        $start_date_3month = gmdate('Y-m-d', strtotime('-3 months', time()));

        $rank_1 = null;
        $rank_3 = null;
        $site_url_used = '';

        $site_url_candidates = $this->build_search_console_site_url_candidates($website, $tokens);
        $forced_refresh = false;
        foreach ($site_url_candidates as $site_url) {
            $attempt = 0;
            $has_401 = false;
            do {
                $attempt++;
                $property_url = urlencode($site_url);
                $rank_1 = $this->fetch_keyword_ranking($access_token, $property_url, $keyword, $start_date_1month, $end_date);
                $rank_3 = $this->fetch_keyword_ranking($access_token, $property_url, $keyword, $start_date_3month, $end_date);

                $rank_1_ok = is_array($rank_1) && !empty($rank_1['ok']);
                $rank_3_ok = is_array($rank_3) && !empty($rank_3['ok']);

                if ($rank_1_ok && $rank_3_ok) {
                    $site_url_used = $site_url;
                    break 2;
                }

                $status_1 = is_array($rank_1) && isset($rank_1['status_code']) ? (int) $rank_1['status_code'] : 0;
                $status_3 = is_array($rank_3) && isset($rank_3['status_code']) ? (int) $rank_3['status_code'] : 0;
                $has_401 = in_array($status_1, array(401), true) || in_array($status_3, array(401), true);

                if ($has_401 && !$forced_refresh) {
                    $new_token = $this->get_valid_access_token_for_project($tokens, $project_id, $website, true);
                    if (is_string($new_token) && trim($new_token) !== '') {
                        $access_token = $new_token;
                    }
                    $forced_refresh = true;
                } else {
                    break;
                }
            } while ($attempt < 2);

            if ($has_401) {
                break;
            }
        }

        $rank_1_ok = is_array($rank_1) && !empty($rank_1['ok']);
        $rank_3_ok = is_array($rank_3) && !empty($rank_3['ok']);

        if ($site_url_used !== '' && is_array($tokens)) {
            $prev_site_url = isset($tokens['smark_sc_site_url']) && is_string($tokens['smark_sc_site_url']) ? trim($tokens['smark_sc_site_url']) : '';
            if ($prev_site_url !== $site_url_used) {
                $tokens['smark_sc_site_url'] = $site_url_used;
                $encrypted_tokens = $this->encode_search_console_tokens($tokens);
                $wpdb->update(
                    $this->projects_table,
                    array('search_console_tokens' => $encrypted_tokens),
                    array('id' => $project_id),
                    array('%s'),
                    array('%d')
                );
            }
        }

        if (!$rank_1_ok || !$rank_3_ok) {
            $status_1 = is_array($rank_1) && isset($rank_1['status_code']) ? (int) $rank_1['status_code'] : 0;
            $status_3 = is_array($rank_3) && isset($rank_3['status_code']) ? (int) $rank_3['status_code'] : 0;
            $auth_error = in_array($status_1, array(401), true) || in_array($status_3, array(401), true);

            if ($auth_error) {
                return new WP_Error('auth', $this->translate('sc_auth_expired'));
            }

            $msg_1 = is_array($rank_1) && !empty($rank_1['message']) ? (string) $rank_1['message'] : '';
            $msg_3 = is_array($rank_3) && !empty($rank_3['message']) ? (string) $rank_3['message'] : '';
            $msg = $msg_1 !== '' ? $msg_1 : $msg_3;
            if ($msg === '') {
                $msg = $this->translate('sc_request_failed');
            }
            return new WP_Error('api', $msg);
        }

        $avg_1month = is_array($rank_1) && array_key_exists('avg', $rank_1) && $rank_1['avg'] !== null ? (float) $rank_1['avg'] : 0.0;
        $avg_3month = is_array($rank_3) && array_key_exists('avg', $rank_3) && $rank_3['avg'] !== null ? (float) $rank_3['avg'] : 0.0;

        $trend = $this->calculate_ranking_trend($avg_3month, $avg_1month);
        $ranking_updated_at = current_time('mysql');

        $wpdb->update(
            $this->project_keywords_table,
            array(
                'rank_1month_avg' => $avg_1month,
                'rank_3month_avg' => $avg_3month,
                'ranking_trend' => $trend,
                'ranking_updated_at' => $ranking_updated_at,
            ),
            array('id' => $item_id),
            array('%f', '%f', '%s', '%s'),
            array('%d')
        );

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $display_format = trim($date_format . ' ' . $time_format);

        return array(
            'rank_1month_avg' => $avg_1month,
            'rank_3month_avg' => $avg_3month,
            'ranking_trend' => $trend,
            'ranking_updated_at' => $ranking_updated_at,
            'ranking_updated_at_display' => wp_date($display_format, (int) mysql2date('U', $ranking_updated_at, false)),
        );
    }

    private function refresh_live_rank_for_item($item_id, $project_id) {
        $item_id = absint($item_id);
        $project_id = absint($project_id);
        if ($item_id <= 0 || $project_id <= 0) {
            return new WP_Error('invalid', $this->translate('error_missing_item'));
        }

        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($project_keywords_table_sql === '' || $projects_table_sql === '') {
            return new WP_Error('db', $this->translate('error_missing_project'));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $keyword_item = $wpdb->get_row($wpdb->prepare("SELECT keyword, page_link_url FROM {$project_keywords_table_sql} WHERE id = %d", $item_id), ARRAY_A);
        if (!$keyword_item || empty($keyword_item['keyword'])) {
            return new WP_Error('missing', $this->translate('error_missing_item'));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project = $wpdb->get_row($wpdb->prepare("SELECT website FROM {$projects_table_sql} WHERE id = %d", $project_id), ARRAY_A);
        if (!$project) {
            return new WP_Error('missing_project', $this->translate('error_missing_project'));
        }

        $keyword = trim((string) $keyword_item['keyword']);
        $preferred_url = isset($keyword_item['page_link_url']) ? trim((string) $keyword_item['page_link_url']) : '';
        $website = isset($project['website']) ? rtrim(trim((string) $project['website']), '/') : '';
        if ($keyword === '' || $website === '') {
            return new WP_Error('invalid_live_rank', $this->translate('live_rank_missing_website'));
        }

        $live_rank = $this->fetch_live_rank_from_central($keyword, $website, $preferred_url);
        if (is_wp_error($live_rank)) {
            return $live_rank;
        }

        $rank = isset($live_rank['rank']) ? max(0, (int) $live_rank['rank']) : 0;
        $live_rank_updated_at = current_time('mysql');
        $project_refdomains = isset($live_rank['project_refdomains']) ? max(0, (int) $live_rank['project_refdomains']) : 0;
        $top10_refdomains_max = isset($live_rank['top10_refdomains_max']) ? max(0, (int) $live_rank['top10_refdomains_max']) : 0;
        $project_backlinks = isset($live_rank['project_backlinks']) ? max(0, (int) $live_rank['project_backlinks']) : 0;
        $top10_backlinks_max = isset($live_rank['top10_backlinks_max']) ? max(0, (int) $live_rank['top10_backlinks_max']) : 0;

        $wpdb->update(
            $this->project_keywords_table,
            array(
                'live_rank_position' => $rank,
                'live_rank_updated_at' => $live_rank_updated_at,
                'live_refdomains_count' => $project_refdomains,
                'live_refdomains_top10_max' => $top10_refdomains_max,
                'live_backlinks_count' => $project_backlinks,
                'live_backlinks_top10_max' => $top10_backlinks_max,
                'live_metrics_updated_at' => $live_rank_updated_at,
            ),
            array('id' => $item_id),
            array('%d', '%s', '%d', '%d', '%d', '%d', '%s'),
            array('%d')
        );

        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $display_format = trim($date_format . ' ' . $time_format);

        return array(
            'live_rank_position' => $rank,
            'live_rank_updated_at' => $live_rank_updated_at,
            'live_rank_updated_at_display' => wp_date($display_format, (int) mysql2date('U', $live_rank_updated_at, false)),
            'live_refdomains_count' => $project_refdomains,
            'live_refdomains_top10_max' => $top10_refdomains_max,
            'live_backlinks_count' => $project_backlinks,
            'live_backlinks_top10_max' => $top10_backlinks_max,
            'live_metrics_updated_at' => $live_rank_updated_at,
            'live_metrics_updated_at_display' => wp_date($display_format, (int) mysql2date('U', $live_rank_updated_at, false)),
            'results' => isset($live_rank['results']) && is_array($live_rank['results']) ? $live_rank['results'] : array(),
            'cached' => !empty($live_rank['cached']),
        );
    }

    private function build_search_console_site_url_candidates($website, $tokens) {
        $candidates = array();

        if (is_array($tokens) && isset($tokens['smark_sc_site_url']) && is_string($tokens['smark_sc_site_url'])) {
            $preferred = trim($tokens['smark_sc_site_url']);
            if ($preferred !== '') {
                $candidates[] = $preferred;
            }
        }

        $website = is_string($website) ? trim($website) : '';
        if ($website === '') {
            return $candidates;
        }

        if (stripos($website, 'sc-domain:') === 0) {
            $candidates[] = $website;
        } else {
            $candidates[] = trailingslashit($website);

            $host = wp_parse_url($website, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $host = strtolower(trim($host));
                $host = preg_replace('/[^a-z0-9.\\-]/', '', $host);
                if ($host !== '') {
                    $candidates[] = 'sc-domain:' . $host;
                    if (strpos($host, 'www.') === 0) {
                        $root = substr($host, 4);
                        if (is_string($root) && $root !== '') {
                            $candidates[] = 'sc-domain:' . $root;
                        }
                    }
                }
            }
        }

        $out = array();
        foreach ($candidates as $site_url) {
            $site_url = is_string($site_url) ? trim($site_url) : '';
            if ($site_url === '') {
                continue;
            }
            if (!in_array($site_url, $out, true)) {
                $out[] = $site_url;
            }
        }
        return $out;
    }

    /**
     * Check and update page link for an item.
     *
     * @param int $item_id Item ID.
     * @param int $project_id Project ID.
     * @return array<string,mixed>|WP_Error
     */
    private function check_and_update_page_link_for_item($item_id, $project_id) {
        $item_id = absint($item_id);
        $project_id = absint($project_id);
        if ($item_id <= 0 || $project_id <= 0) {
            return new WP_Error('invalid', $this->translate('error_missing_item'));
        }

        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($project_keywords_table_sql === '' || $projects_table_sql === '') {
            return new WP_Error('db', $this->translate('error_missing_project'));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $keyword_item = $wpdb->get_row($wpdb->prepare("SELECT keyword FROM {$project_keywords_table_sql} WHERE id = %d", $item_id), ARRAY_A);
        if (!$keyword_item || empty($keyword_item['keyword'])) {
            return new WP_Error('missing', $this->translate('error_missing_item'));
        }
        $keyword = (string) $keyword_item['keyword'];

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$projects_table_sql} WHERE id = %d", $project_id), ARRAY_A);
        if (!$project) {
            return new WP_Error('missing_project', $this->translate('error_missing_project'));
        }

        $post = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE pm.meta_key = 'rank_math_focus_keyword'
                 AND (pm.meta_value = %s OR pm.meta_value LIKE %s OR pm.meta_value LIKE %s OR pm.meta_value LIKE %s)
                 AND p.post_status = 'publish'
                 ORDER BY p.post_modified_gmt DESC, p.post_date_gmt DESC
                 LIMIT 1",
                $keyword,
                $keyword . ',%',
                '%,' . $keyword,
                '%,' . $keyword . ',%'
            ),
            ARRAY_A
        );

        if ($post) {
            $page_url = get_permalink((int) $post['ID']);
            $wpdb->update(
                $this->project_keywords_table,
                array(
                    'page_link_status' => 'found',
                    'page_link_url' => $page_url,
                ),
                array('id' => $item_id),
                array('%s', '%s'),
                array('%d')
            );

            return array('status' => 'found', 'url' => $page_url);
        }

        $wpdb->update(
            $this->project_keywords_table,
            array(
                'page_link_status' => 'not_found',
                'page_link_url' => null,
            ),
            array('id' => $item_id),
            array('%s', '%s'),
            array('%d')
        );

        return array('status' => 'not_found', 'url' => null);
    }

    /**
     * Handle AJAX: Refresh keyword (bank + ranking + page link).
     *
     * @return void
     */
    public function ajax_refresh_keyword() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $item_id = isset($_POST['itemId']) ? (int) $_POST['itemId'] : 0;
        $project_id = isset($_POST['projectId']) ? (int) $_POST['projectId'] : 0;
        if ($item_id <= 0 || $project_id <= 0) {
            wp_send_json_error(array('message' => $this->translate('error_missing_item')));
        }

        $errors = array();

        $bank_result = $this->refresh_project_keyword_from_central_bank($item_id, $project_id);
        if (is_wp_error($bank_result)) {
            $errors[] = (string) $bank_result->get_error_message();
            $bank_result = null;
        }

        $ranking_result = $this->refresh_ranking_for_item($item_id, $project_id);
        if (is_wp_error($ranking_result)) {
            $errors[] = (string) $ranking_result->get_error_message();
            $ranking_result = null;
        }

        $page_link_result = $this->check_and_update_page_link_for_item($item_id, $project_id);
        if (is_wp_error($page_link_result)) {
            $errors[] = (string) $page_link_result->get_error_message();
            $page_link_result = null;
        }

        wp_send_json_success(
            array(
                'bank' => $bank_result,
                'ranking' => $ranking_result,
                'page_link' => $page_link_result,
                'errors' => $errors,
            )
        );
    }

    /**
     * Handle AJAX: Remove keyword from project.
     *
     * @return void
     */
    public function ajax_remove_project_item() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $item_id = isset($_POST['itemId']) ? (int) $_POST['itemId'] : 0;

        if ($item_id <= 0) {
            wp_send_json_error(array('message' => $this->translate('error_missing_item')));
        }

        global $wpdb;

        $table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($table_sql === '') {
            wp_send_json_error(array('message' => $this->translate('error_delete_item')));
        }

        $has_page_link_status = $this->table_has_column($this->project_keywords_table, 'page_link_status');
        $has_page_link_url = $this->table_has_column($this->project_keywords_table, 'page_link_url');

        $select_columns = array('id', 'project_id', 'keyword');
        if ($has_page_link_status) {
            $select_columns[] = 'page_link_status';
        }
        if ($has_page_link_url) {
            $select_columns[] = 'page_link_url';
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
        $row = $wpdb->get_row($wpdb->prepare('SELECT ' . implode(', ', $select_columns) . ' FROM ' . $table_sql . ' WHERE id = %d', $item_id), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!is_array($row) || empty($row['id'])) {
            wp_send_json_error(array('message' => $this->translate('error_missing_item')));
        }

        $project_id = isset($row['project_id']) ? (int) $row['project_id'] : 0;
        $keyword = isset($row['keyword']) ? (string) $row['keyword'] : '';
        $page_link_status = isset($row['page_link_status']) ? (string) $row['page_link_status'] : '';
        $page_link_url = isset($row['page_link_url']) ? (string) $row['page_link_url'] : '';

        $deleted_post_ids = array();
        $removed_from_cm = false;
        $removed_from_cm_create = false;

        $normalize_keyword_key = function ($val) {
            $k = sanitize_text_field((string) $val);
            $k = trim($k);
            $k = preg_replace('/\\s+/u', ' ', $k);
            return is_string($k) ? strtolower($k) : '';
        };
        $needle_kw = $normalize_keyword_key($keyword);

        $candidate_post_ids = array();

        // A) Prefer resolving post/page from page_link_url when available.
        if ($page_link_url !== '' && $page_link_status === 'found') {
            $post_id = url_to_postid($page_link_url);
            if (!$post_id && function_exists('attachment_url_to_postid')) {
                $post_id = attachment_url_to_postid($page_link_url);
            }

            if (!$post_id) {
                $parsed = wp_parse_url($page_link_url);
                $path = is_array($parsed) && isset($parsed['path']) ? (string) $parsed['path'] : '';
                $path = trim($path, '/');
                if ($path !== '') {
                    $segments = explode('/', $path);
                    $slug = (string) end($segments);
                    if ($slug !== '') {
                        $types = get_post_types(array('show_ui' => true), 'names');
                        $types = is_array($types) ? $types : array('post', 'page');
                        $found = get_page_by_path($slug, OBJECT, $types);
                        if ($found && isset($found->ID)) {
                            $post_id = (int) $found->ID;
                        }
                    }
                }
            }

            if ($post_id) {
                $candidate_post_ids[] = (int) $post_id;
            }
        }

        // B) Remove related Content Management "create content" item(s) for this keyword (and collect post IDs).
        if ($project_id > 0 && $needle_kw !== '') {
            $cm_create_opt = get_option('smark_cm_create_content', array());
            if (is_array($cm_create_opt)) {
                $pid_key = (string) (int) $project_id;
                $items = isset($cm_create_opt[$pid_key]) && is_array($cm_create_opt[$pid_key]) ? $cm_create_opt[$pid_key] : array();
                $next = array();
                $did_remove = false;

                foreach ($items as $it) {
                    if (!is_array($it)) {
                        continue;
                    }
                    $it_kw = isset($it['keyword']) ? $normalize_keyword_key($it['keyword']) : '';
                    if ($it_kw !== '' && $it_kw === $needle_kw) {
                        $did_remove = true;
                        $post_id = isset($it['postId']) ? (int) $it['postId'] : 0;
                        if ($post_id > 0) {
                            $candidate_post_ids[] = $post_id;
                        }
                        continue;
                    }
                    $next[] = $it;
                }

                if ($did_remove) {
                    $cm_create_opt[$pid_key] = array_values($next);
                    update_option('smark_cm_create_content', $cm_create_opt, false);
                    $removed_from_cm_create = true;
                }
            }
        }

        $candidate_post_ids = array_values(array_unique(array_filter(array_map('intval', $candidate_post_ids), function ($id) {
            return (int) $id > 0;
        })));

        // If nothing resolved yet, try to map by title inside Content Management selected list (best effort).
        if ($project_id > 0 && $needle_kw !== '' && empty($candidate_post_ids)) {
            $cm_sel_opt = get_option('smark_cm_selected_content', array());
            if (is_array($cm_sel_opt)) {
                $pid_key = (string) (int) $project_id;
                $ids = isset($cm_sel_opt[$pid_key]) && is_array($cm_sel_opt[$pid_key]) ? $cm_sel_opt[$pid_key] : array();
                foreach (array_values(array_unique(array_map('intval', (array) $ids))) as $pid) {
                    if ($pid <= 0) {
                        continue;
                    }
                    $title = get_the_title((int) $pid);
                    if ($normalize_keyword_key($title) === $needle_kw) {
                        $candidate_post_ids[] = (int) $pid;
                    }
                }
                $candidate_post_ids = array_values(array_unique(array_filter(array_map('intval', $candidate_post_ids), function ($id) {
                    return (int) $id > 0;
                })));
            }
        }

        // Delete related posts/pages and remove them from Content Management selected list.
        if ($project_id > 0 && !empty($candidate_post_ids)) {
            $cm_opt = get_option('smark_cm_selected_content', array());
            $cm_opt = is_array($cm_opt) ? $cm_opt : array();
            $pid_key = (string) (int) $project_id;
            $list = isset($cm_opt[$pid_key]) && is_array($cm_opt[$pid_key]) ? $cm_opt[$pid_key] : array();
            $before = $list;

            foreach ($candidate_post_ids as $post_id) {
                if (!current_user_can('delete_post', (int) $post_id)) {
                    wp_send_json_error(array('message' => $this->translate('error_delete_related_post')));
                }

                $list = array_values(array_filter(array_map('intval', (array) $list), function ($id) use ($post_id) {
                    return (int) $id > 0 && (int) $id !== (int) $post_id;
                }));

                $deleted_post = wp_delete_post((int) $post_id, true);
                if (!$deleted_post) {
                    wp_send_json_error(array('message' => $this->translate('error_delete_related_post')));
                }

                $deleted_post_ids[] = (int) $post_id;
            }

            if ($before !== $list) {
                $cm_opt[$pid_key] = $list;
                update_option('smark_cm_selected_content', $cm_opt, false);
                $removed_from_cm = true;
            }
        }

        $deleted = $wpdb->delete(
            $this->project_keywords_table,
            array('id' => $item_id),
            array('%d')
        );

        if (!$deleted) {
            wp_send_json_error(array('message' => $this->translate('error_delete_item')));
        }

        wp_send_json_success(
            array(
                'deletedPostIds' => array_values(array_unique(array_map('intval', $deleted_post_ids))),
                'removedFromContentManagement' => $removed_from_cm,
                'removedFromContentManagementCreate' => $removed_from_cm_create,
            )
        );
    }

    /**
     * Handle AJAX: Check page link for keyword.
     *
     * @return void
     */
    public function ajax_check_page_link() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $item_id = isset($_POST['itemId']) ? (int) $_POST['itemId'] : 0;
        $project_id = isset($_POST['projectId']) ? (int) $_POST['projectId'] : 0;

        if ($item_id <= 0 || $project_id <= 0) {
            wp_send_json_error(array('message' => $this->translate('error_missing_item')));
        }

        $result = $this->check_and_update_page_link_for_item($item_id, $project_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    public function ajax_get_edit_url() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if ($url === '') {
            wp_send_json_error(array('message' => 'Invalid URL'), 400);
        }

        $post_id = url_to_postid($url);
        if (!$post_id && function_exists('attachment_url_to_postid')) {
            $post_id = attachment_url_to_postid($url);
        }

        if (!$post_id) {
            $parsed = wp_parse_url($url);
            $path = is_array($parsed) && isset($parsed['path']) ? (string) $parsed['path'] : '';
            $path = trim($path, '/');
            if ($path !== '') {
                $segments = explode('/', $path);
                $slug = (string) end($segments);
                if ($slug !== '') {
                    $types = get_post_types(array('show_ui' => true), 'names');
                    $types = is_array($types) ? $types : array('post', 'page');
                    $found = get_page_by_path($slug, OBJECT, $types);
                    if ($found && isset($found->ID)) {
                        $post_id = (int) $found->ID;
                    }
                }
            }
        }

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Not found'), 404);
        }

        $edit_url = get_edit_post_link($post_id, 'raw');
        if (!$edit_url) {
            wp_send_json_error(array('message' => 'Not editable'), 404);
        }

        wp_send_json_success(array('edit_url' => $edit_url, 'post_id' => $post_id));
    }

    /**
     * Handle AJAX: Fetch keyword ranking from Search Console.
     *
     * @return void
     */
    public function ajax_fetch_ranking() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $item_id = isset($_POST['itemId']) ? (int) $_POST['itemId'] : 0;
        $project_id = isset($_POST['projectId']) ? (int) $_POST['projectId'] : 0;

        if ($item_id <= 0 || $project_id <= 0) {
            wp_send_json_error(array('message' => $this->translate('error_missing_item')));
        }

        $result = $this->refresh_ranking_for_item($item_id, $project_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle AJAX: Fetch live ranking from Semrush via SMark Core.
     *
     * @return void
     */
    public function ajax_fetch_live_rank() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $item_id = isset($_POST['itemId']) ? (int) $_POST['itemId'] : 0;
        $project_id = isset($_POST['projectId']) ? (int) $_POST['projectId'] : 0;

        if ($item_id <= 0 || $project_id <= 0) {
            wp_send_json_error(array('message' => $this->translate('error_missing_item')));
        }

        $result = $this->refresh_live_rank_for_item($item_id, $project_id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Get valid access token for a project (helper method).
     *
     * @param array $tokens Token data.
     * @param int   $project_id Project ID.
     * @param string $website Project website (used for central refresh authorization).
     * @return string|false Access token or false on failure.
     */
    private function get_central_google_oauth_refresh_endpoint() {
        return rtrim($this->get_central_keyword_bank_base_url(), '/') . '/wp-json/smark-core/v1/tools/google/oauth/refresh';
    }

    private function refresh_access_token_via_central($refresh_token, $website) {
        $refresh_token = is_string($refresh_token) ? trim($refresh_token) : '';
        $website = is_string($website) ? rtrim(trim($website), '/') : '';
        if ($refresh_token === '' || $website === '') {
            return new WP_Error('smark_sc_refresh_invalid', 'Invalid refresh request.');
        }

        $sync_token = $this->get_central_sync_token();
        if ($sync_token === '') {
            return new WP_Error('smark_sc_refresh_missing_token', 'Central sync token is not configured.');
        }

        $endpoint = $this->get_central_google_oauth_refresh_endpoint();
        $resp = wp_remote_post($endpoint, array(
            'timeout' => 20,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'x-smark-sync-token' => $sync_token,
                'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (sc-oauth-refresh)',
            ),
            'body' => wp_json_encode(array(
                'refresh_token' => $refresh_token,
                'website' => $website,
            )),
        ));

        if (is_wp_error($resp)) {
            return $resp;
        }

        $http_code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($http_code < 200 || $http_code >= 300) {
            $msg = 'Central refresh failed (HTTP ' . $http_code . ')';
            if (is_array($data) && isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
                $msg = $data['message'] . ' (HTTP ' . $http_code . ')';
            }
            return new WP_Error('smark_sc_refresh_failed', $msg, array('status' => $http_code, 'body' => $body));
        }

        if (!is_array($data) || empty($data['tokens']) || !is_array($data['tokens'])) {
            return new WP_Error('smark_sc_refresh_invalid_response', 'Invalid token response from central server.');
        }

        return $data['tokens'];
    }

    private function get_valid_access_token_for_project(&$tokens, $project_id, $website = '', $force_refresh = false) {
        if (!is_array($tokens)) {
            return false;
        }

        $access_token = '';
        if (isset($tokens['access_token'])) {
            $access_token = is_string($tokens['access_token']) ? trim($tokens['access_token']) : '';
        }

        $refresh_token = '';
        if (isset($tokens['refresh_token']) && is_string($tokens['refresh_token'])) {
            $refresh_token = trim($tokens['refresh_token']);
        }

        if (!$force_refresh && $access_token === '') {
            return false;
        }

        $now = time();
        $created = 0;
        if (isset($tokens['created']) && is_numeric($tokens['created'])) {
            $created = (int) $tokens['created'];
        } elseif (isset($tokens['created_at']) && is_numeric($tokens['created_at'])) {
            $created = (int) $tokens['created_at'];
        }

        $expires_in = 0;
        if (isset($tokens['expires_in']) && is_numeric($tokens['expires_in'])) {
            $expires_in = (int) $tokens['expires_in'];
        } elseif (isset($tokens['expires']) && is_numeric($tokens['expires'])) {
            $expires_in = (int) $tokens['expires'];
        }

        // Determine absolute expiry timestamp if possible.
        $expires_at = 0;
        if ($created > 0 && $expires_in > 0) {
            $expires_at = $created + $expires_in;
        } elseif (isset($tokens['expires_at']) && is_numeric($tokens['expires_at'])) {
            $expires_at = (int) $tokens['expires_at'];
        } elseif (isset($tokens['expiry_date']) && is_numeric($tokens['expiry_date'])) {
            // Google PHP client uses milliseconds in expiry_date.
            $ms = (int) $tokens['expiry_date'];
            if ($ms > 0) {
                $expires_at = $ms > 100000000000 ? (int) floor($ms / 1000) : $ms;
            }
        }

        if (!$force_refresh) {
            // If we can't determine expiry, assume token is still usable and let API validate it.
            if ($expires_at <= 0) {
                return $access_token;
            }

            // Check if token is expired (with 5 minute buffer)
            if ($expires_at > ($now + 300)) {
                return $access_token;
            }
        }

        // Token expired (or forced refresh), refresh it if possible. If we can't refresh, try the existing token and let the API validate it.
        if ($refresh_token === '') {
            return $access_token !== '' ? $access_token : false;
        }

        $client_id = $this->get_google_client_id();
        $client_secret = $this->get_google_client_secret();

        if (empty($client_id)) {
            return $access_token !== '' ? $access_token : false;
        }

        $body = array(
            'client_id' => $client_id,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token',
        );
        if (!empty($client_secret)) {
            $body['client_secret'] = $client_secret;
        }

        $refresh_response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'timeout' => 15,
            'body' => $body,
        ));

        $refresh_data = null;
        if (!is_wp_error($refresh_response)) {
            $refresh_data = json_decode(wp_remote_retrieve_body($refresh_response), true);
        }

        $refresh_ok = is_array($refresh_data) && !empty($refresh_data['access_token']) && empty($refresh_data['error']);
        if (!$refresh_ok) {
            $central_tokens = $this->refresh_access_token_via_central($tokens['refresh_token'], $website);
            if (is_array($central_tokens) && !empty($central_tokens['access_token'])) {
                $refresh_data = $central_tokens;
                $refresh_ok = true;
            }
        }

        if (!$refresh_ok) {
            return $access_token !== '' ? $access_token : false;
        }

        // Update tokens
        $tokens['access_token'] = (string) $refresh_data['access_token'];
        $tokens['expires_in'] = isset($refresh_data['expires_in']) ? (int) $refresh_data['expires_in'] : 0;
        $tokens['created'] = $now;

        // Save updated tokens
        global $wpdb;
        $projects_table = $this->projects_table;
        $encrypted_tokens = $this->encode_search_console_tokens($tokens);

        $wpdb->update(
            $projects_table,
            array('search_console_tokens' => $encrypted_tokens),
            array('id' => $project_id),
            array('%s'),
            array('%d')
        );

        return $tokens['access_token'];
    }

    /**
     * Fetch keyword ranking from Search Console API.
     *
     * @param string $access_token Access token.
     * @param string $property_url Encoded property URL.
     * @param string $keyword Keyword to search for.
     * @param string $start_date Start date (Y-m-d).
     * @param string $end_date End date (Y-m-d).
     * @return array{ok:bool,avg:?float,status_code:int,message:string} Response payload.
     */
    private function fetch_keyword_ranking($access_token, $property_url, $keyword, $start_date, $end_date) {
        $api_url = 'https://www.googleapis.com/webmasters/v3/sites/' . $property_url . '/searchAnalytics/query';

        $request_body = array(
            'startDate' => $start_date,
            'endDate' => $end_date,
            'dimensions' => array('query'),
            'dimensionFilterGroups' => array(
                array(
                    'filters' => array(
                        array(
                            'dimension' => 'query',
                            'operator' => 'equals',
                            'expression' => $keyword
                        )
                    )
                )
            ),
            'rowLimit' => 1
        );

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_body),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return array(
                'ok' => false,
                'avg' => null,
                'status_code' => 0,
                'message' => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = (string) wp_remote_retrieve_body($response);
            $msg = '';
            try {
                $parsed = json_decode($body, true);
                if (is_array($parsed) && isset($parsed['error']['message'])) {
                    $msg = (string) $parsed['error']['message'];
                }
            } catch (Exception $e) {
                $msg = '';
            }
            if ($msg === '') {
                $msg = 'Search Console API request failed';
            }

            return array(
                'ok' => false,
                'avg' => null,
                'status_code' => (int) $status_code,
                'message' => $msg,
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['rows']) || empty($data['rows'])) {
            return array(
                'ok' => true,
                'avg' => null,
                'status_code' => 200,
                'message' => '',
            );
        }

        // Get average position from first row
        $row = $data['rows'][0];
        if (isset($row['position'])) {
            return array(
                'ok' => true,
                'avg' => (float) $row['position'],
                'status_code' => 200,
                'message' => '',
            );
        }

        return array(
            'ok' => true,
            'avg' => null,
            'status_code' => 200,
            'message' => '',
        );
    }

    /**
     * Handle AJAX: Search keyword bank.
     *
     * @return void
     */
    public function ajax_search_bank() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $search = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $project_id = isset($_GET['projectId']) ? absint($_GET['projectId']) : 0;
        $match = isset($_GET['match']) ? sanitize_key(wp_unslash($_GET['match'])) : 'broad';
        if (!in_array($match, array('broad', 'exact'), true)) {
            $match = 'broad';
        }
        $limit = ($search === '') ? 10 : (($match === 'exact') ? 500 : 50);

        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            wp_send_json_error(array('message' => $this->translate('error_upload_generic')));
        }

        $results = array();

        $endpoint = $this->get_central_keyword_bank_base_url() . '/wp-json/smark-core/v1/keyword-bank/search';
        $args = array(
            'timeout' => 15,
            'headers' => array(),
        );
        $token = $this->get_central_sync_token();
        if ($token !== '') {
            $args['headers']['x-smark-sync-token'] = $token;
        }

        $url = add_query_arg(
            array(
                'q' => $search,
                'limit' => $limit,
                'match' => $match,
            ),
            $endpoint
        );
        $resp = wp_remote_get($url, $args);
        if (!is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) === 200) {
            $body = wp_remote_retrieve_body($resp);
            $data = json_decode($body, true);
            if (is_array($data)) {
                $results = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
            }
        }

        if ($match === 'exact' && $search !== '' && !empty($results)) {
            $needle = strtolower(trim($search));
            $results = array_values(
                array_filter(
                    (array) $results,
                    static function($item) use ($needle) {
                        $kw = '';
                        if (is_array($item) && isset($item['keyword'])) {
                            $kw = (string) $item['keyword'];
                        }
                        return strtolower(trim($kw)) === $needle;
                    }
                )
            );
        }

        $lang = $this->get_panel_language();
        foreach ($results as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $row['intent'] = $this->interpret_intent_value(isset($row['intent']) ? (string) $row['intent'] : '', $lang);
            $row['serp_features'] = $this->interpret_serp_features_value(isset($row['serp_features']) ? (string) $row['serp_features'] : '', $lang);
        }
        unset($row);

        $project_keywords = array();
        if ($project_id > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $project_keywords_raw = $wpdb->get_col($wpdb->prepare("SELECT keyword FROM {$project_keywords_table_sql} WHERE project_id = %d", $project_id));
            $project_keywords = array_map('strtolower', $project_keywords_raw);
        }

        wp_send_json_success(
            array(
                'results'    => $results ?: array(),
                'projectKeywords' => $project_keywords,
            )
        );
    }

    public function ajax_keyword_bank_stats() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')), 403);
        }

        $cached = $this->get_cached_keyword_bank_stats();
        $cached_age = $cached && !empty($cached['fetched_at']) ? (time() - (int) $cached['fetched_at']) : PHP_INT_MAX;

        // If we recently fetched, return cached immediately to avoid flakiness (DNS / timeouts).
        if (is_array($cached) && $cached_age < (5 * MINUTE_IN_SECONDS)) {
            wp_send_json_success(array(
                'total' => (int) $cached['total'],
                'lastUpload' => $cached['lastUpload'],
                'stale' => false,
                'source' => 'cache',
            ));
        }

        $remote = $this->fetch_keyword_bank_stats_remote();
        if (!is_wp_error($remote)) {
            $this->set_cached_keyword_bank_stats($remote['total'], $remote['lastUpload']);
            wp_send_json_success(array(
                'total' => (int) $remote['total'],
                'lastUpload' => $remote['lastUpload'],
                'stale' => false,
                'source' => 'remote',
            ));
        }

        // Remote failed; fall back to last known good cache (prevents user-facing errors).
        if (is_array($cached)) {
            wp_send_json_success(array(
                'total' => (int) $cached['total'],
                'lastUpload' => $cached['lastUpload'],
                'stale' => true,
                'source' => 'cache',
            ));
        }

        // No cache yet; return a stable response shape without throwing a user-facing error.
        wp_send_json_success(array(
            'total' => 0,
            'lastUpload' => null,
            'stale' => true,
            'source' => 'none',
        ));
    }
    /**
     * Handle AJAX: Assign keyword(s) from bank to project.
     *
     * @return void
     */
    public function ajax_add_from_bank() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        $project_id   = isset($_POST['projectId']) ? absint(wp_unslash($_POST['projectId'])) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $bank_ids_raw = isset($_POST['bankIds']) ? wp_unslash($_POST['bankIds']) : array();

        if ($project_id <= 0) {
            wp_send_json_error(array('message' => $this->translate('error_missing_project')));
        }

        if (!is_array($bank_ids_raw)) {
            $bank_ids_raw = explode(',', (string) $bank_ids_raw);
        }

        $bank_ids = array_values(array_filter(array_map('absint', (array) $bank_ids_raw)));

        if (empty($bank_ids)) {
            wp_send_json_error(array('message' => $this->translate('error_missing_keywords')));
        }

        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($projects_table_sql === '' || $project_keywords_table_sql === '') {
            wp_send_json_error(array('message' => $this->translate('error_upload_generic')));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project = $wpdb->get_row($wpdb->prepare("SELECT id, project_name FROM {$projects_table_sql} WHERE id = %d", $project_id), ARRAY_A);

        if (!$project) {
            wp_send_json_error(array('message' => $this->translate('error_missing_project')));
        }

        $keywords = array();
        $endpoint = $this->get_central_keyword_bank_base_url() . '/wp-json/smark-core/v1/keyword-bank/items';
        $args = array(
            'timeout' => 15,
            'headers' => array(),
        );
        $token = $this->get_central_sync_token();
        if ($token !== '') {
            $args['headers']['x-smark-sync-token'] = $token;
        }

        $url = add_query_arg(array('ids' => implode(',', $bank_ids)), $endpoint);
        $resp = wp_remote_get($url, $args);
        if (!is_wp_error($resp) && (int) wp_remote_retrieve_response_code($resp) === 200) {
            $data = json_decode(wp_remote_retrieve_body($resp), true);
            if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
                $keywords = $data['items'];
            }
        }

        if (empty($keywords)) {
            wp_send_json_error(array('message' => $this->translate('error_missing_keywords')));
        }

        $lang = $this->get_panel_language();
        $inserted = 0;
        $failed_inserts = 0;

        foreach ($keywords as $keyword) {
            $already_exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$project_keywords_table_sql} WHERE project_id = %d AND keyword = %s", $project['id'], $keyword['keyword'])); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

            if ($already_exists > 0) {
                continue;
            }

            $intent = $this->interpret_intent_value(isset($keyword['intent']) ? (string) $keyword['intent'] : '', $lang);
            $intent = is_string($intent) ? sanitize_text_field($intent) : null;
            $serp_features = $this->interpret_serp_features_value(isset($keyword['serp_features']) ? (string) $keyword['serp_features'] : '', $lang);
            $serp_features = is_string($serp_features) ? sanitize_text_field($serp_features) : null;

            $insert_result = $wpdb->insert(
                $this->project_keywords_table,
                array(
                    'project_id'         => $project['id'],
                    'project_name'       => $project['project_name'],
                    'keyword_bank_id'    => isset($keyword['id']) ? absint($keyword['id']) : null,
                    'keyword'            => isset($keyword['keyword']) ? (string) $keyword['keyword'] : '',
                    'intent'             => $intent,
                    'volume'             => isset($keyword['volume']) && $keyword['volume'] !== '' ? (int) $keyword['volume'] : null,
                    'keyword_difficulty' => isset($keyword['keyword_difficulty']) && $keyword['keyword_difficulty'] !== '' ? (float) $keyword['keyword_difficulty'] : null,
                    'cpc_usd'            => isset($keyword['cpc_usd']) && $keyword['cpc_usd'] !== '' ? (float) $keyword['cpc_usd'] : null,
                    'serp_features'      => $serp_features,
                    'page_link_status'   => 'not_checked',
                    'page_link_url'      => null,
                    'created_at'         => current_time('mysql'),
                    'updated_at'         => current_time('mysql'),
                ),
                array('%d', '%s', '%d', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s')
            );

            if ($insert_result !== false) {
                $inserted++;
            } else {
                $failed_inserts++;
            }
        }

        if ($inserted === 0 && $failed_inserts > 0) {
            $message = $wpdb->last_error ? $wpdb->last_error : 'Insert failed';
            wp_send_json_error(array('message' => $message));
        }

        wp_send_json_success(array('added' => $inserted));
    }

    /**
     * Handle AJAX: Upload keyword file.
     *
     * @return void
     */
    public function ajax_upload_keywords() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => $this->translate('error_permissions')));
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            wp_send_json_error(array('message' => $this->translate('error_missing_file')));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $file = wp_unslash($_FILES['file']);

        if (!empty($file['error'])) {
            wp_send_json_error(array('message' => $this->translate('error_upload_generic')));
        }

        $allowed_types = array(
            'text/csv',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $file_type = isset($file['type']) ? $file['type'] : '';

        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_type, $allowed_types, true) && !in_array($file_ext, array('csv', 'xls', 'xlsx'), true)) {
            wp_send_json_error(array('message' => $this->translate('error_invalid_file_type')));
        }

        $uploaded = wp_handle_upload(
            $file,
            array(
                'test_form' => false,
                'unique_filename_callback' => array($this, 'generate_upload_filename'),
            )
        );

        if (isset($uploaded['error'])) {
            wp_send_json_error(array('message' => $uploaded['error']));
        }

        $parsed = $this->parse_keyword_file($uploaded['file']);

        if (empty($parsed)) {
            wp_send_json_error(array('message' => $this->translate('error_empty_file')));
        }

        $inserted = $this->import_keywords($parsed);

        wp_send_json_success(
            array(
                'inserted' => $inserted,
            )
        );
    }

    private function normalize_keyword_for_compare($keyword) {
        $keyword = is_string($keyword) ? $keyword : '';
        $keyword = sanitize_text_field($keyword);
        $keyword = trim($keyword);
        if ($keyword === '') {
            return '';
        }

        $keyword = preg_replace('/\\s+/u', ' ', $keyword);
        $keyword = is_string($keyword) ? trim($keyword) : '';
        if ($keyword === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $keyword = mb_strtolower($keyword, 'UTF-8');
        } else {
            $keyword = strtolower($keyword);
        }

        return $keyword;
    }

    private function get_rankmath_focus_keywords_normalized() {
        $cached = get_transient('smark_rankmath_focus_keywords_norm');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $meta_values = $wpdb->get_col(
            "SELECT pm.meta_value
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key IN ('rank_math_focus_keyword', 'rank_math_focus_keywords')
             AND pm.meta_value <> ''
             AND p.post_status NOT IN ('trash','auto-draft')"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $set = array();
        if (is_array($meta_values)) {
            foreach ($meta_values as $raw) {
                $raw = is_string($raw) ? trim($raw) : '';
                if ($raw === '') {
                    continue;
                }
                $parts = preg_split('/[،,]+/u', $raw);
                if (!is_array($parts) || empty($parts)) {
                    continue;
                }
                foreach ($parts as $part) {
                    $kw = $this->normalize_keyword_for_compare($part);
                    if ($kw !== '') {
                        $set[$kw] = true;
                    }
                }
            }
        }

        $keywords = array_keys($set);
        // Cache for 6 hours (Rank Math focus keywords don't change frequently).
        set_transient('smark_rankmath_focus_keywords_norm', $keywords, 6 * HOUR_IN_SECONDS);

        return $keywords;
    }

    public function ajax_rankmath_gap_stats() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array(
                'code' => 'permissions',
                'message' => __('Permission denied', 'smark'),
            ), 403);
        }

        $project_id = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
        if ($project_id <= 0) {
            wp_send_json_error(array(
                'code' => 'invalid_project',
                'message' => __('Invalid project.', 'smark'),
            ), 400);
        }

        $cache_key = 'smark_rankmath_gap_count_' . (string) $project_id;
        $force = isset($_GET['force']) ? (int) $_GET['force'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce already verified above.
        if ($force === 1) {
            delete_transient($cache_key);
            delete_transient('smark_rankmath_focus_keywords_norm');
        }
        $cached = get_transient($cache_key);
        if ($force !== 1 && is_numeric($cached)) {
            wp_send_json_success(array(
                'missing_count' => (int) $cached,
                'cached' => true,
            ));
        }

        global $wpdb;

        $project_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_table_sql === '') {
            wp_send_json_error(array(
                'code' => 'db_table_not_found',
                'message' => __('Database table not found.', 'smark'),
            ), 500);
        }

        $rankmath_keywords = $this->get_rankmath_focus_keywords_normalized();
        $rm_total = is_array($rankmath_keywords) ? count($rankmath_keywords) : 0;

        // Build normalized set of project keywords.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
        $project_keywords = $wpdb->get_col($wpdb->prepare('SELECT keyword FROM ' . $project_table_sql . ' WHERE project_id = %d', $project_id));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $project_set = array();
        if (is_array($project_keywords)) {
            foreach ($project_keywords as $keyword) {
                $kw = $this->normalize_keyword_for_compare($keyword);
                if ($kw !== '') {
                    $project_set[$kw] = true;
                }
            }
        }

        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only.
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $missing_count = 0;
        $missing_sample = array();
        $missing_keywords = array();
        if (is_array($rankmath_keywords)) {
            foreach ($rankmath_keywords as $kw) {
                $kw = $this->normalize_keyword_for_compare($kw);
                if ($kw === '') {
                    continue;
                }
                if (!isset($project_set[$kw])) {
                    $missing_count++;
                    if (count($missing_sample) < 10) {
                        $missing_sample[] = $kw;
                    }
                    if (count($missing_keywords) < $limit) {
                        $missing_keywords[] = $kw;
                    }
                }
            }
        }

        set_transient($cache_key, $missing_count, 30 * MINUTE_IN_SECONDS);

        wp_send_json_success(array(
            'missing_count' => $missing_count,
            'rm_total' => $rm_total,
            'project_total' => count($project_set),
            'missing_sample' => $missing_sample,
            'missing_keywords' => $missing_keywords,
            'missing_limit' => $limit,
        ));
    }

    /**
     * Add a Rank Math missing keyword (fetched from SMark Core keyword bank) into the selected project.
     */
    public function ajax_add_rankmath_missing_keyword() {
        check_ajax_referer('SMARK_keyword_research_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(
                'code' => 'permissions',
                'message' => $this->translate('error_permissions'),
            ), 403);
        }

        $project_id = isset($_POST['projectId']) ? absint(wp_unslash($_POST['projectId'])) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $keyword_raw = isset($_POST['keyword']) ? wp_unslash($_POST['keyword']) : array();
        $keyword_data = is_array($keyword_raw) ? $keyword_raw : array();

        $keyword = isset($keyword_data['keyword']) ? sanitize_text_field((string) $keyword_data['keyword']) : '';
        $keyword = trim($keyword);

        if ($project_id <= 0) {
            wp_send_json_error(array(
                'code' => 'missing_project',
                'message' => $this->translate('error_missing_project'),
            ), 400);
        }
        if ($keyword === '') {
            wp_send_json_error(array(
                'code' => 'missing_keyword',
                'message' => $this->translate('error_missing_keywords'),
            ), 400);
        }

        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($projects_table_sql === '' || $project_keywords_table_sql === '') {
            wp_send_json_error(array(
                'code' => 'db_invalid',
                'message' => $this->translate('error_upload_generic'),
            ), 500);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project = $wpdb->get_row($wpdb->prepare("SELECT id, project_name FROM {$projects_table_sql} WHERE id = %d", $project_id), ARRAY_A);
        if (!$project) {
            wp_send_json_error(array(
                'code' => 'project_not_found',
                'message' => $this->translate('error_missing_project'),
            ), 404);
        }

        // Prevent duplicates.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $already_exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$project_keywords_table_sql} WHERE project_id = %d AND LOWER(keyword) = LOWER(%s)", $project_id, $keyword));
        if ($already_exists > 0) {
            wp_send_json_success(array('added' => 0, 'message' => $this->translate('no_keywords_added_notice')));
        }

        $lang = $this->get_panel_language();
        $intent = $this->interpret_intent_value(isset($keyword_data['intent']) ? (string) $keyword_data['intent'] : '', $lang);
        $intent = is_string($intent) ? sanitize_text_field($intent) : null;
        $serp_features = $this->interpret_serp_features_value(isset($keyword_data['serp_features']) ? (string) $keyword_data['serp_features'] : '', $lang);
        $serp_features = is_string($serp_features) ? sanitize_text_field($serp_features) : null;

        $insert_result = $wpdb->insert(
            $this->project_keywords_table,
            array(
                'project_id'         => $project['id'],
                'project_name'       => $project['project_name'],
                'keyword_bank_id'    => isset($keyword_data['id']) ? absint($keyword_data['id']) : null,
                'keyword'            => $keyword,
                'intent'             => $intent,
                'volume'             => isset($keyword_data['volume']) && $keyword_data['volume'] !== '' ? (int) $keyword_data['volume'] : null,
                'keyword_difficulty' => isset($keyword_data['keyword_difficulty']) && $keyword_data['keyword_difficulty'] !== '' ? (float) $keyword_data['keyword_difficulty'] : null,
                'cpc_usd'            => isset($keyword_data['cpc_usd']) && $keyword_data['cpc_usd'] !== '' ? (float) $keyword_data['cpc_usd'] : null,
                'serp_features'      => $serp_features,
                'page_link_status'   => 'not_checked',
                'page_link_url'      => null,
                'created_at'         => current_time('mysql'),
                'updated_at'         => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s')
        );

        if ($insert_result === false) {
            $message = $wpdb->last_error ? $wpdb->last_error : 'Insert failed';
            wp_send_json_error(array(
                'code' => 'db_insert_failed',
                'message' => $message,
            ), 500);
        }

        delete_transient('smark_rankmath_gap_count_' . (string) (int) $project_id);

        wp_send_json_success(array('added' => 1));
    }

    /**
     * Parse uploaded file and return normalized keyword rows.
     *
     * @param string $file_path Absolute file path.
     * @return array<int, array<string, mixed>>
     */
    private function parse_keyword_file($file_path) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->parse_csv($file_path);
        }

        if (in_array($extension, array('xls', 'xlsx'), true)) {
            return $this->parse_excel($file_path);
        }

        return array();
    }

    /**
     * Parse CSV file.
     *
     * @param string $file_path Absolute file path.
     * @return array
     */
    private function parse_csv($file_path) {
        $rows = array();

        if (!class_exists('WP_Filesystem_Direct')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        $filesystem = new WP_Filesystem_Direct(false);

        $contents = $filesystem->get_contents($file_path);
        if (!is_string($contents) || $contents === '') {
            return $rows;
        }

        $lines = preg_split("/\\r\\n|\\n|\\r/", $contents);

        $headers = array();
        foreach ($lines as $line) {
            if (!is_string($line) || trim($line) === '') {
                continue;
            }
            $data = str_getcsv($line, ',');
            if (empty($headers)) {
                $headers = $this->normalize_headers($data);
                continue;
            }

            $row = $this->map_row($headers, $data);
            if ($row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Parse Excel file (xls/xlsx) using SimpleXLSX reader.
     *
     * @param string $file_path Absolute file path.
     * @return array
     */
    private function parse_excel($file_path) {
        if (!class_exists('SimpleXLSX')) {
            require_once __DIR__ . '/lib/simple_xlsx.php';
        }

        $xlsx = SimpleXLSX::parse($file_path);

        if (!$xlsx) {
            return array();
        }

        $rows    = array();
        $headers = array();

        foreach ($xlsx->rows() as $index => $row) {
            if ($index === 0) {
                $headers = $this->normalize_headers($row);
                continue;
            }

            $mapped = $this->map_row($headers, $row);
            if ($mapped) {
                $rows[] = $mapped;
            }
        }

        return $rows;
    }
    /**
     * Normalize header names for mapping.
     *
     * @param array $headers Raw header names.
     * @return array
     */
    private function normalize_headers($headers) {
        $map = array();

        foreach ($headers as $index => $header) {
            $header = strtolower(trim((string) $header));

            switch ($header) {
                case 'keyword':
                    $map[$index] = 'keyword';
                    break;
                case 'intent':
                    $map[$index] = 'intent';
                    break;
                case 'volume':
                    $map[$index] = 'volume';
                    break;
                case 'keyword difficulty':
                case 'keyword_difficulty':
                case 'difficulty':
                    $map[$index] = 'keyword_difficulty';
                    break;
                case 'cpc (usd)':
                case 'cpc usd':
                case 'cpc':
                    $map[$index] = 'cpc_usd';
                    break;
                case 'serp features':
                case 'serp_features':
                case 'serp':
                    $map[$index] = 'serp_features';
                    break;
            }
        }

        return $map;
    }

    /**
     * Map raw row to normalized structure.
     *
     * @param array $headers Normalized headers map.
     * @param array $row Raw row data.
     * @return array|null
     */
    private function map_row($headers, $row) {
        if (!in_array('keyword', $headers, true)) {
            return null;
        }

        $data = array(
            'keyword'            => '',
            'intent'             => '',
            'volume'             => null,
            'keyword_difficulty' => null,
            'cpc_usd'            => null,
            'serp_features'      => '',
        );

        foreach ($headers as $index => $field) {
            if (!isset($row[$index]) || '' === $field) {
                continue;
            }

            $value = is_string($row[$index]) ? trim($row[$index]) : $row[$index];

            switch ($field) {
                case 'keyword':
                    $data['keyword'] = sanitize_text_field($value);
                    break;
                case 'intent':
                    $data['intent'] = sanitize_text_field($value);
                    break;
                case 'volume':
                    $data['volume'] = is_numeric($value) ? (int) $value : null;
                    break;
                case 'keyword_difficulty':
                    $data['keyword_difficulty'] = is_numeric($value) ? (float) $value : null;
                    break;
                case 'cpc_usd':
                    $data['cpc_usd'] = is_numeric($value) ? (float) $value : null;
                    break;
                case 'serp_features':
                    $data['serp_features'] = sanitize_text_field($value);
                    break;
            }
        }

        if (empty($data['keyword'])) {
            return null;
        }

        return $data;
    }

    /**
     * Insert parsed keywords into bank table.
     *
     * @param array $rows Parsed rows.
     * @return int
     */
    private function import_keywords($rows) {
        global $wpdb;
        $keyword_bank_table_sql = $this->escape_db_identifier($this->keyword_bank_table);
        if ($keyword_bank_table_sql === '') {
            return 0;
        }

        $inserted = 0;

        foreach ($rows as $row) {
            $keyword = sanitize_text_field($row['keyword']);
            $lookup  = strtolower($keyword);

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$keyword_bank_table_sql} WHERE LOWER(keyword) = %s", $lookup));

            if ($existing_id) {
                $wpdb->update(
                    $this->keyword_bank_table,
                    array(
                        'keyword'            => $keyword,
                        'intent'             => $row['intent'],
                        'volume'             => $row['volume'],
                        'keyword_difficulty' => $row['keyword_difficulty'],
                        'cpc_usd'            => $row['cpc_usd'],
                        'serp_features'      => $row['serp_features'],
                        'updated_at'         => current_time('mysql'),
                    ),
                    array('id' => $existing_id),
                    array('%s', '%s', '%d', '%f', '%f', '%s', '%s'),
                    array('%d')
                );
                continue;
            }

            $wpdb->insert(
                $this->keyword_bank_table,
                array(
                    'keyword'            => $keyword,
                    'intent'             => $row['intent'],
                    'volume'             => $row['volume'],
                    'keyword_difficulty' => $row['keyword_difficulty'],
                    'cpc_usd'            => $row['cpc_usd'],
                    'serp_features'      => $row['serp_features'],
                    'created_at'         => current_time('mysql'),
                    'updated_at'         => current_time('mysql'),
                ),
                array('%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s')
            );

            if ($wpdb->insert_id) {
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Generate deterministic filename for uploads.
     *
     * @param string $dir  Directory.
     * @param string $name Filename.
     * @param string $ext  Extension.
     * @return string
     */
    public function generate_upload_filename($dir, $name, $ext) {
        $timestamp = gmdate('Ymd-His');
        return 'smark-keywords-' . $timestamp . $ext;
    }

    /**
     * Provide localized strings for JS.
     *
     * @param string $lang Two-letter lang key.
     * @return array<string, string>
     */
    private function get_localized_strings($lang) {
        $strings = array(
            'loading'                => $this->translate('loading', $lang),
            'noResults'              => $this->translate('no_results', $lang),
            'selectProject'          => $this->translate('select_project', $lang),
            'projectCreated'         => $this->translate('project_created_notice', $lang),
            'projectCreateError'     => $this->translate('error_project_create', $lang),
            'uploadSuccess'          => $this->translate('upload_success', $lang),
            'uploadError'            => $this->translate('error_upload_generic', $lang),
            'noProjectSelected'      => $this->translate('error_missing_project', $lang),
            'keywordsAdded'          => $this->translate('keywords_added_notice', $lang),
            'noKeywordsAdded'        => $this->translate('no_keywords_added_notice', $lang),
            'noKeywordsSelected'     => $this->translate('error_missing_keywords', $lang),
            'missingProjectName'     => $this->translate('error_missing_project_name', $lang),
            'missingFile'            => $this->translate('error_missing_file', $lang),
            'markInsufficient'       => $this->translate('mark_insufficient', $lang),
            'addKeywordsButton'      => $this->translate('add_keywords_button', $lang),
            'deleteLabel'            => $this->translate('delete_button', $lang),
            'deleteConfirm'          => $this->translate('delete_confirm', $lang),
            'deleteInProgress'       => $this->translate('delete_in_progress', $lang),
            'deleteSuccess'          => $this->translate('delete_success', $lang),
            'deleteError'            => $this->translate('error_delete_item', $lang),
            'createContentLabel'     => $this->translate('create_content_button', $lang),
            'editContentLabel'       => $this->translate('edit_content_button', $lang),
            'keywordRequestEmpty'    => $this->translate('keyword_request_empty', $lang),
            'keywordRequestSuccess'  => $this->translate('keyword_request_success', $lang),
            'keywordRequestFailed'   => $this->translate('keyword_request_failed', $lang),
            'refreshKeywordLabel'    => $this->translate('refresh_keyword_button', $lang),
            'refreshKeywordProgress' => $this->translate('refresh_keyword_in_progress', $lang),
            'refreshKeywordSuccess'  => $this->translate('refresh_keyword_success', $lang),
            'refreshKeywordPartial'  => $this->translate('refresh_keyword_partial', $lang),
            'refreshKeywordError'    => $this->translate('refresh_keyword_error', $lang),
            'rankMathGapLoading'     => $this->translate('rankmath_gap_loading', $lang),
            'rankMathGapMessage'     => $this->translate('rankmath_gap_message', $lang),
            'rankMathGapOk'          => $this->translate('rankmath_gap_ok', $lang),
            'rankMathGapError'       => $this->translate('rankmath_gap_error', $lang),
            'rankMathGapNoKeywords'  => $this->translate('rankmath_gap_no_keywords', $lang),
            'rankMathGapAdd'         => $this->translate('rankmath_gap_add_button', $lang),
            'rankMathGapShowing'     => $this->translate('rankmath_gap_showing', $lang),
            'rankMathGapMissingLabel'=> $this->translate('rankmath_gap_missing_label', $lang),
            'rankMathGapAddSoon'     => $this->translate('rankmath_gap_add_soon', $lang),
            'rankMathGapAddSuccess'  => $this->translate('rankmath_gap_add_success', $lang),
            'rankMathGapAddError'    => $this->translate('rankmath_gap_add_error', $lang),
            'liveRankFetchTitle'     => $this->translate('live_rank_fetch_title', $lang),
            'liveRankUpdateTitle'    => $this->translate('live_rank_update_title', $lang),
            'liveRankFetchSuccess'   => $this->translate('live_rank_fetch_success', $lang),
            'liveRankFetchError'     => $this->translate('live_rank_fetch_error', $lang),
            'liveRankNotTop100'      => $this->translate('live_rank_not_top_100', $lang),
            'metricOursLabel'        => $this->translate('metric_ours_label', $lang),
            'metricMaxLabel'         => $this->translate('metric_max_label', $lang),
        );

        return $strings;
    }

    /**
     * Translate key based on panel language.
     *
     * @param string      $key  Translation key.
     * @param string|null $lang Optional language override.
     * @return string
     */
    private function translate($key, $lang = null) {
        if (!$lang) {
            $lang = get_option('SMARK_panel_language', 'en');
        }

        $translations = array(
            'en' => array(
                'SMARK_dashboard'           => 'SMark Dashboard',
                'breadcrumb_seo'            => 'SEO Management',
                'keyword_research_title'    => 'Keyword Research',
                'keyword_research_subtitle' => 'Organize keyword insights for your SEO projects',
                'upload_keywords'           => 'Upload Keywords',
                'select_or_create_project' => 'Select or Create Project',
                'choose_existing_project'   => 'Choose an existing project or create a new one to manage its keywords.',
                'or'                        => 'or',
                'create_new_project'        => 'Create New Project',
                'project_selector_title'    => 'Project selection',
                'project_selector_help'     => 'Choose an existing project or create a new one to manage its keywords.',
                'create_project'            => 'New Project',
                'select_project'            => 'Select a project',
                'keywords_in_project'       => 'Keywords in project',
                'keywords_in_bank'          => 'Keywords in bank',
                'bank_box_title'            => 'Keyword bank',
                'bank_box_help'             => 'Pull keywords from your centralized repository.',
                'add_from_bank'             => 'Add from bank',
                'add_to_bank'               => 'Add to bank',
                'bank_description'          => 'Upload CSV or Excel files to grow your keyword bank. Search anytime and assign them to projects.',
                'bank_total_keywords'       => 'Total keywords',
                'recent_upload_label'       => 'Last upload',
                'project_keywords_title'    => 'Project keywords',
                'project_keywords_help'     => 'Review and curate the keywords assigned to this project.',
                'add_keywords_button'       => 'Add keywords',
                'table_keyword'             => 'Keyword',
                'table_intent'              => 'Intent',
                'table_volume'              => 'Volume',
                'table_difficulty'          => 'Keyword Difficulty',
                'table_cpc'                 => 'CPC (USD)',
                'table_serp'                => 'SERP Features',
                'table_ranking'              => 'Ranking',
                'table_live_rank'           => 'Live Rank',
                'table_refdomains'         => 'Ref. Domains',
                'table_backlinks'          => 'Backlinks',
                'table_ranking_updated_at'   => 'Last update',
                'table_page_link'           => 'Page Link',
                'page_link_filter_all'       => 'All',
                'page_link_filter_not_checked' => 'Not checked',
                'page_link_filter_no_link'   => 'No link',
                'page_link_filter_has_link'  => 'Has link',
                'ranking_updated_filter_all' => 'All',
                'ranking_updated_filter_needs_update' => 'Needs update',
                'ranking_updated_filter_updated' => 'Updated',
                'actions'                   => 'Actions',
                'select'                    => 'Select',
                'empty_project_title'       => 'No keywords yet',
                'empty_project_subtitle'    => 'Use the keyword bank to add relevant ideas to this project.',
                'empty_project_cta'         => 'Browse keyword bank',
                'create_project_heading'    => 'Create new project',
                'project_name_label'        => 'Project name',
                'project_name_placeholder'  => 'e.g. SEO Growth Project',
                'cancel_button'             => 'Cancel',
                'save_button'               => 'Save',
                'bank_modal_title'          => 'Keyword bank',
                'bank_search_placeholder'   => 'Search keyword bank…',
                'search_button'             => 'Search',
                'bank_empty_title'          => 'No keyword found',
                'bank_empty_subtitle'       => 'Try another term or fetch this keyword from the central server.',
                'bank_search_hint'          => 'To find the right keyword, search above.',
                'request_keyword_button'    => 'Fetch keyword',
                'request_modal_title'       => 'Request keyword',
                'request_modal_description' => 'Enter keywords, one per line:',
                'request_modal_placeholder' => "e.g.\nseo agency\ncontent marketing strategy",
                'request_modal_submit'      => 'Send request',
                'mark_cost_tooltip'         => 'Includes 1 Mark',
                'mark_insufficient'         => 'You don\'t have enough Mark credits.',
                'bank_empty_cta'            => 'Upload keywords',
                'close_button'              => 'Close',
                'upload_modal_title'        => 'Upload keywords',
                'upload_modal_description'  => 'Upload CSV or Excel files with the following columns: Keyword, Intent, Volume, Keyword Difficulty, CPC (USD), SERP Features.',
                'upload_modal_hint'         => 'Maximum file size follows your WordPress upload limit.',
                'upload_button'             => 'Upload file',
                'loading'                   => 'Loading…',
                'no_results'                => 'No results found',
                'keyword_request_empty'     => 'Please enter at least one keyword.',
                'keyword_request_success'   => 'Your request has been submitted.',
                'keyword_request_failed'    => 'Failed to submit your request. Please try again.',
                'project_created_notice'    => 'Project created successfully.',
                'error_missing_project_name'=> 'Please enter a project name.',
                'error_duplicate_project'   => 'A project with that name already exists.',
                'error_project_create'      => 'Unable to create project. Please try again.',
                'error_missing_project'     => 'Select a project first.',
                'error_missing_keywords'    => 'Select at least one keyword.',
                'error_missing_item'        => 'Keyword not found.',
                'error_delete_item'         => 'Unable to remove keyword. Please try again.',
                'error_delete_related_post' => 'Unable to delete the related WordPress post/page. Please check permissions and try again.',
                'error_permissions'         => 'You do not have permission to perform this action.',
                'error_nonce'               => 'Security check failed. Refresh the page and try again.',
                'sc_not_connected'          => 'Search Console is not connected to this project.',
                'sc_invalid_tokens'         => 'Invalid Search Console tokens.',
                'sc_auth_expired'           => 'Search Console access has expired. Reconnect it from Project Management to grant access to the plugin again.',
                'sc_request_failed'         => 'Search Console request failed.',
                'live_rank_missing_website' => 'Project website is not configured for live rank lookup.',
                'error_missing_file'        => 'Please choose a file to upload.',
                'error_upload_generic'      => 'Upload failed. Please try again.',
                'error_invalid_file_type'   => 'Unsupported file type. Please upload CSV or Excel files.',
                'error_empty_file'          => 'No keywords found in the file.',
                'upload_success'            => 'File processed successfully.',
                'keywords_added_notice'     => 'Keywords added to project.',
                'no_keywords_added_notice'  => 'No keywords were added.',
                'delete_button'             => 'Delete',
                'delete_confirm'            => 'Deleting this keyword will also delete its related WordPress post/page (if found). Do you want to continue?',
                'delete_in_progress'        => 'Deleting keyword…',
                'delete_success'            => 'Keyword removed.',
                'create_content_button'     => 'Create content',
                'edit_content_button'       => 'Edit content',
                'select_a_project'         => 'Select a Project',
                'please_select_project'     => 'Please select or create a project to view its keywords.',
                'add_selected_keywords'     => 'Add Selected Keywords',
                'refresh_keyword_button'    => 'Refresh keyword',
                'refresh_keyword_in_progress' => 'Refreshing keywordâ€¦',
                'refresh_keyword_success'   => 'Keyword refreshed.',
                'refresh_keyword_partial'   => 'Refreshed with warnings.',
                'refresh_keyword_error'     => 'Refresh failed.',
                'live_rank_fetch_title'     => 'Fetch live rank',
                'live_rank_update_title'    => 'Update live rank',
                'live_rank_fetch_success'   => 'Live rank fetched successfully.',
                'live_rank_fetch_error'     => 'Failed to fetch live rank.',
                'live_rank_not_top_100'     => '100+',
                'metric_ours_label'         => 'Ours',
                'metric_max_label'          => 'Highest',
                'rankmath_gap_loading'      => 'Checking Rank Math keywords…',
                'rankmath_gap_message'      => '{missing} keywords are set in Rank Math but not in your project keywords. Add them to keep your data consistent.',
            ),
            'fa' => array(
                'SMARK_dashboard'           => 'داشبورد اسمارک',
                'breadcrumb_seo'            => 'مدیریت سئو',
                'keyword_research_title'    => 'تحقیق کلمات کلیدی',
                'keyword_research_subtitle' => 'بینش‌های کلمات کلیدی را برای پروژه‌های سئو مدیریت کنید',
                'upload_keywords'           => 'آپلود کلمات کلیدی',
                'select_or_create_project'  => 'انتخاب یا ایجاد پروژه',
                'choose_existing_project'   => 'یک پروژه موجود را انتخاب کنید یا یک پروژه جدید بسازید.',
                'or'                        => 'یا',
                'create_new_project'        => 'ایجاد پروژه جدید',
                'project_selector_title'    => 'انتخاب پروژه',
                'project_selector_help'     => 'یک پروژه موجود را انتخاب کنید یا یک پروژه جدید بسازید.',
                'create_project'            => 'پروژه جدید',
                'select_project'            => 'انتخاب پروژه:',
                'loading'                   => 'در حال بارگذاری...',
                'keywords_in_project'       => 'کلمات در پروژه',
                'keywords_in_bank'          => 'کلمات در بانک',
                'bank_box_title'            => 'بانک کلمات کلیدی',
                'bank_box_help'             => 'کلمات کلیدی را به بانک مرکزی اضافه کنید.',
                'add_from_bank'             => 'افزودن از بانک',
                'add_to_bank'               => 'افزودن به بانک',
                'bank_description'          => 'برای ساخت بانک کلمات، فایل CSV یا Excel آپلود کنید. هر زمان جستجو کنید و به پروژه‌ها اختصاص دهید.',
                'bank_total_keywords'       => 'کل کلمات',
                'recent_upload_label'       => 'آخرین آپلود',
                'project_keywords_title'    => 'کلمات پروژه',
                'project_keywords_help'     => 'کلمات اختصاص داده شده به این پروژه را بررسی و مدیریت کنید.',
                'add_keywords_button'       => 'افزودن کلمات',
                'table_keyword'             => 'کلمه کلیدی',
                'table_intent'              => 'هدف جستجو',
                'table_volume'              => 'حجم جستجو',
                'table_difficulty'          => 'سختی کلمه',
                'table_cpc'                 => 'CPC (دلار)',
                'table_serp'                => 'ویژگی‌های SERP',
                'table_ranking'              => 'رتبه کلمه',
                'table_live_rank'           => 'رتبه لایو',
                'table_refdomains'         => 'دامنه‌های ارجاعی',
                'table_backlinks'          => 'بک‌لینک‌ها',
                'table_ranking_updated_at'   => 'تاریخ بروزرسانی',
                'table_page_link'           => 'لینک صفحه',
                'page_link_filter_all'       => 'همه',
                'page_link_filter_not_checked' => 'تست نشده است',
                'page_link_filter_no_link'   => 'لینک ندارد',
                'page_link_filter_has_link'  => 'لینک دارد',
                'ranking_updated_filter_all' => 'همه',
                'ranking_updated_filter_needs_update' => 'نیازمند بروزرسانی',
                'ranking_updated_filter_updated' => 'بروزرسانی شده',
                'actions'                   => 'عملیات',
                'select'                    => 'انتخاب',
                'empty_project_title'       => 'هنوز کلمه‌ای ثبت نشده است',
                'empty_project_subtitle'    => 'از بانک کلمات برای افزودن ایده‌های مرتبط استفاده کنید.',
                'empty_project_cta'         => 'مشاهده بانک کلمات',
                'create_project_heading'    => 'ساخت پروژه جدید',
                'project_name_label'        => 'نام پروژه',
                'project_name_placeholder'  => 'مثال: سئو آژانس اسمارک',
                'cancel_button'             => 'انصراف',
                'save_button'               => 'ثبت',
                'bank_modal_title'          => 'بانک کلمات کلیدی',
                'bank_search_placeholder'   => 'جستجو در بانک کلمات…',
                'search_button'             => 'جستجو',
                'bank_empty_title'          => 'کلمه کلیدی یافت نشد',
                'bank_empty_subtitle'       => 'می‌توانید عبارت دیگری را جست‌وجو کنید یا این کلمه را از سرور مرکزی دریافت کنید.',
                'bank_search_hint'          => 'برای یافتن کلمه کلیدی مناسب، جست‌وجو کنید.',
                'request_keyword_button'    => 'دریافت کلمه کلیدی',
                'request_modal_title'       => 'درخواست کلمه کلیدی',
                'request_modal_description' => 'کلمات کلیدی را هر خط یک عبارت وارد کنید:',
                'request_modal_placeholder' => "مثلاً:\nسئو سایت\nتبلیغات گوگل\nاستراتژی محتوا",
                'request_modal_submit'      => 'ارسال درخواست',
                'mark_cost_tooltip'         => 'شامل ۱ مارک',
                'mark_insufficient'         => 'مارک به اندازه کافی ندارید.',
                'bank_empty_cta'            => 'آپلود کلمات',
                'close_button'              => 'بستن',
                'upload_modal_title'        => 'آپلود کلمات کلیدی',
                'upload_modal_description'  => 'فایل CSV یا Excel با ستون‌های Keyword، Intent، Volume، Keyword Difficulty، CPC (USD)، SERP Features را آپلود کنید.',
                'upload_modal_hint'         => 'حداکثر اندازه فایل مطابق تنظیمات وردپرس شماست.',
                'upload_button'             => 'آپلود فایل',
                'loading'                   => 'در حال بارگذاری…',
                'no_results'                => 'هیچ نتیجه‌ای یافت نشد',
                'keyword_request_empty'     => 'حداقل یک کلمه کلیدی وارد کنید.',
                'keyword_request_success'   => 'درخواست شما ثبت شد.',
                'keyword_request_failed'    => 'ثبت درخواست با خطا مواجه شد. دوباره تلاش کنید.',
                'project_created_notice'    => 'پروژه با موفقیت ایجاد شد.',
                'error_missing_project_name'=> 'نام پروژه را وارد کنید.',
                'error_duplicate_project'   => 'پروژه‌ای با این نام وجود دارد.',
                'error_project_create'      => 'ساخت پروژه با خطا مواجه شد.',
                'error_missing_project'     => 'ابتدا پروژه را انتخاب کنید.',
                'error_missing_keywords'    => 'حداقل یک کلمه را انتخاب کنید.',
                'error_missing_item'        => 'کلمه مورد نظر یافت نشد.',
                'error_delete_item'         => 'حذف کلمه انجام نشد.',
                'error_delete_related_post' => 'حذف صفحه/پست مرتبط انجام نشد. لطفاً سطح دسترسی را بررسی کنید و دوباره امتحان کنید.',
                'error_permissions'         => 'اجازه انجام این عملیات را ندارید.',
                'error_nonce'               => 'اعتبارسنجی امنیتی انجام نشد. صفحه را رفرش کنید.',
                'sc_not_connected'          => 'سرچ کنسول به این پروژه متصل نیست.',
                'sc_invalid_tokens'         => 'توکن‌های سرچ کنسول نامعتبر هستند.',
                'sc_auth_expired'           => 'دسترسی سرچ کنسول اکسپایر شده است. از بخش مدیریت پروژه دسترسی مجدد را در اختیار پلاگین قرار دهید.',
                'sc_request_failed'         => 'درخواست به سرچ کنسول ناموفق بود.',
                'live_rank_missing_website' => 'آدرس سایت پروژه برای بررسی رتبه لایو تنظیم نشده است.',
                'error_missing_file'        => 'فایلی انتخاب نشده است.',
                'error_upload_generic'      => 'آپلود با خطا مواجه شد.',
                'error_invalid_file_type'   => 'نوع فایل مجاز نیست. لطفاً CSV یا Excel آپلود کنید.',
                'error_empty_file'          => 'هیچ کلمه‌ای در فایل پیدا نشد.',
                'upload_success'            => 'فایل با موفقیت پردازش شد.',
                'keywords_added_notice'     => 'کلمات به پروژه اضافه شدند.',
                'no_keywords_added_notice'  => 'هیچ عبارتی اضافه نشد.',
                'delete_button'             => 'حذف کلمه کلیدی',
                'delete_confirm'            => 'با حذف این کلمه کلیدی، اگر صفحه/پست مرتبطی ساخته شده باشد از وردپرس هم حذف می‌شود. ادامه می‌دهید؟',
                'delete_in_progress'        => 'کلمه کلیدی در حال حذف هست…',
                'delete_success'            => 'کلمه کلیدی حذف شد.',
                'create_content_button'     => 'ایجاد محتوا',
                'edit_content_button'       => 'ویرایش محتوا',
                'select_a_project'          => 'انتخاب پروژه',
                'please_select_project'     => 'لطفاً یک پروژه انتخاب یا ایجاد کنید تا کلمات کلیدی آن را مشاهده کنید.',
                'add_selected_keywords'     => 'افزودن عبارت',
                'refresh_keyword_button'    => 'بروزرسانی کلمه',
                'refresh_keyword_in_progress' => 'در حال بروزرسانی...',
                'refresh_keyword_success'   => 'بروزرسانی انجام شد.',
                'refresh_keyword_partial'   => 'بروزرسانی انجام شد (با خطا).',
                'refresh_keyword_error'     => 'بروزرسانی انجام نشد.',
                'live_rank_fetch_title'     => 'دریافت رتبه لایو',
                'live_rank_update_title'    => 'بروزرسانی رتبه لایو',
                'live_rank_fetch_success'   => 'رتبه لایو با موفقیت دریافت شد.',
                'live_rank_fetch_error'     => 'دریافت رتبه لایو با خطا مواجه شد.',
                'live_rank_not_top_100'     => '100+',
                'metric_ours_label'         => 'ما',
                'metric_max_label'          => 'بیشترین',
            ),
        );

        $translations['en']['rankmath_gap_loading'] = 'Checking Rank Math keywords…';
        $translations['en']['rankmath_gap_message'] = '{missing} keywords are set in Rank Math but not in your project keywords. Add them to keep your data consistent.';
        $translations['en']['rankmath_gap_ok'] = 'All Rank Math keywords are already in your project keywords.';
        $translations['en']['rankmath_gap_error'] = 'Could not check Rank Math keywords right now.';
        $translations['en']['rankmath_gap_no_keywords'] = 'No Rank Math focus keywords found to compare.';
        $translations['en']['rankmath_gap_check_button'] = 'Check Rank Math keywords';
        $translations['en']['rankmath_gap_modal_title'] = 'Rank Math keywords missing from your sheet';
        $translations['en']['rankmath_gap_modal_hint'] = 'These focus keywords exist in Rank Math but are not found in your project keywords.';
        $translations['en']['rankmath_gap_add_button'] = 'Review & add keyword';
        $translations['en']['rankmath_gap_showing'] = 'Showing {shown} of {total} missing keywords.';
        $translations['en']['rankmath_gap_missing_label'] = 'missing keywords found.';
        $translations['en']['rankmath_gap_add_soon'] = 'This action will be available soon.';
        $translations['en']['rankmath_gap_add_success'] = 'Keyword added successfully.';
        $translations['en']['rankmath_gap_add_error'] = 'Failed to add keyword.';
        $translations['fa']['rankmath_gap_add_success'] = 'کلمه کلیدی با موفقیت اضافه شد.';
        $translations['fa']['rankmath_gap_add_error'] = 'افزودن کلمه کلیدی با خطا مواجه شد.';
        $translations['fa']['rankmath_gap_loading'] = 'در حال بررسی کلمات Rank Math…';
        $translations['fa']['rankmath_gap_message'] = '{missing} کلمه کلیدی در Rank Math ثبت شده اما در لیست کلمات کلیدی پروژه وجود ندارد. برای یکدست شدن داده‌ها، آن‌ها را به پروژه اضافه کنید.';
        $translations['fa']['rankmath_gap_ok'] = 'تمام کلمات کلیدی Rank Math در لیست کلمات کلیدی پروژه وجود دارد.';
        $translations['fa']['rankmath_gap_error'] = 'در حال حاضر امکان بررسی کلمات Rank Math وجود ندارد.';
        $translations['fa']['rankmath_gap_no_keywords'] = 'هیچ کلمه کلیدی برای مقایسه در Rank Math پیدا نشد.';
        $translations['fa']['rankmath_gap_check_button'] = 'بررسی کلمات کلیدی رنک‌مث';
        $translations['fa']['rankmath_gap_modal_title'] = 'کلمات کلیدی رنک‌مث که در شیت نیستند';
        $translations['fa']['rankmath_gap_modal_hint'] = 'این کلمات کلیدی به‌عنوان Focus Keyword در Rank Math وجود دارند اما در لیست کلمات کلیدی پروژه پیدا نشدند.';
        $translations['fa']['rankmath_gap_add_button'] = 'بررسی و افزودن کلمه کلیدی';
        $translations['fa']['rankmath_gap_showing'] = 'نمایش {shown} از {total} کلمه کلیدیِ موجود.';
        $translations['fa']['rankmath_gap_missing_label'] = 'کلمه کلیدی پیدا شد.';
        $translations['fa']['rankmath_gap_add_soon'] = 'عملیات افزودن به‌زودی فعال می‌شود.';

        $default_lang = isset($translations[$lang]) ? $lang : 'en';
        $dictionary   = $translations[$default_lang];

        return isset($dictionary[$key]) ? $dictionary[$key] : $key;
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
