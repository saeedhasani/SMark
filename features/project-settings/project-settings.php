<?php
/**
 * Project Settings (Setup) page for SMark.
 *
 * This feature is a lightweight wrapper around the projects data stored in SMark-Core (project-management).
 */
 
if (!defined('WPINC')) {
    die;
}
 
if (class_exists('SMarkProjectSettings', false)) {
    return;
}
 
class SMarkProjectSettings {
    const OPTION_CURRENT_PROJECT_DB_ID = 'smark_current_project_db_id';
    const OPTION_SETUP_COMPLETED = 'smark_project_settings_completed';
    const OPTION_CENTRAL_PROJECT_DB_ID = 'smark_central_project_db_id';
    const OPTION_CENTRAL_BASE_URL = 'smark_central_base_url';
    const OPTION_MODULE_VISIBILITY = 'smark_dashboard_module_visibility';
    const CENTRAL_SYNC_TOKEN_HEADER = 'x-smark-sync-token';
    const DEFAULT_CENTRAL_BASE_URL = 'https://saeedhasani.com';
    const CENTRAL_SYNC_PATH = '/wp-json/smark-core/v1/projects/sync';
    const CENTRAL_GOOGLE_OAUTH_EXCHANGE_PATH = '/wp-json/smark-core/v1/tools/google/oauth/exchange';
    const CENTRAL_SC_OAUTH_BROKER_PATH = '/wp-json/smark/v1/sc-oauth';
    const CENTRAL_MARK_CONSUME_PATH = '/wp-json/smark-core/v1/projects/mark/consume';
    const CENTRAL_MARK_BALANCE_PATH = '/wp-json/smark-core/v1/projects/mark/balance';
    const SC_OAUTH_BROKER_ENABLED_OPTION = 'smark_sc_broker_enabled';
    const DEFAULT_GOOGLE_CLIENT_ID = '504883940536-0v8ppi41kgj02b4opb1bn2qiobmofup0.apps.googleusercontent.com';
    const GOOGLE_OAUTH_AUTHORIZE_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    const GOOGLE_OAUTH_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    const SEARCH_CONSOLE_TOKEN_PREFIX = 'smarksc:v1:';

    private $smark_ps_redirect_allowed_hosts = array();
  
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'maybe_force_setup'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_SMARK_project_settings_save_language', array($this, 'ajax_save_language'));
        add_action('wp_ajax_SMARK_pm_connect_search_console', array($this, 'ajax_connect_search_console'));
        // Dedicated action for this feature to avoid conflicts with SMark-Core Project Management.
        add_action('wp_ajax_SMARK_project_settings_connect_search_console', array($this, 'ajax_connect_search_console'));
        add_action('wp_ajax_SMARK_project_settings_claim_search_console', array($this, 'ajax_claim_search_console'));
        add_action('wp_ajax_SMARK_project_settings_store_search_console_tokens', array($this, 'ajax_store_search_console_tokens'));
        add_action('wp_ajax_smark_dashboard_project_settings_view', array($this, 'ajax_dashboard_project_settings_view'));
        add_action('wp_ajax_smark_dashboard_project_settings_save', array($this, 'ajax_dashboard_project_settings_save'));
        add_action('rest_api_init', array($this, 'register_sc_oauth_broker_routes'));
    }

    private function get_panel_language() {
        $lang = get_option('smark_panel_language', '');
        if (!is_string($lang) || $lang === '') {
            $lang = get_option('SMARK_panel_language', 'en');
        }
        $lang = is_string($lang) ? strtolower(trim($lang)) : 'en';
        return $lang === 'fa' ? 'fa' : 'en';
    }

    private function is_persian_site() {
        $locale = function_exists('get_locale') ? (string) get_locale() : '';
        return stripos($locale, 'fa') === 0;
    }

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

    private function get_strings() {
        $lang = $this->get_panel_language();
        $strings = array(
            'en' => array(
                'menu' => 'Project Settings',
                'title' => 'Project Settings',
                'subtitle' => '',
                'setup_notice' => 'Please complete the required project settings to start using SMark.',
                'projects_table_missing' => 'Projects table not found. Please activate SMark Core (Project Management) and try again.',
                'project_name' => 'Project Name',
                'project_name_help' => 'Required. Default is your site name; you can change it anytime.',
                'brand_language' => 'Brand Language',
                'canva_template' => 'Canva Template',
                'website' => 'Website',
                'mark_credit' => 'Mark Credit Balance',
                'mark_credit_help' => 'This value is read from the central server and cannot be edited here.',
                'wp_connection' => 'WordPress Connection',
                'sc_connection' => 'Search Console Connection',
                'connected' => 'Connected',
                'connect' => 'Connect',
                'checking' => 'Checking...',
                'saving' => 'Saving...',
                'save' => 'Save Settings',
                'project_settings_saved' => 'Project settings saved.',
                'project_name_required' => 'Project name is required.',
                'open_pm' => 'Open Project Management',
                'permissions' => 'Permission denied.',
                'db_invalid' => 'Projects table not found.',
                'central_sync_failed' => 'Project was not synced to the central server.',
                'breadcrumb_dashboard' => 'Dashboard',
                'sc_success_notice' => 'Search Console connected successfully.',
                'sc_error_notice' => 'Search Console connection failed.',
                'not_connected' => 'Not connected',
                'sc_client_secret' => 'Google Client Secret',
                'sc_client_secret_help' => 'Required for completing Search Console connection.',
                'sc_save_secret' => 'Save Secret',
                'sc_secret_saved' => 'Client Secret saved. Please click Connect again.',
                'modules' => 'Modules',
                'modules_help' => 'Turn dashboard modules on or off. Disabled modules are hidden from the right dashboard menu.',
                'module_email' => 'Email Marketing',
                'module_seo' => 'SEO',
                'module_social' => 'Social Media',
                'module_on' => 'On',
                'module_off' => 'Off',
            ),
            'fa' => array(
                'menu' => 'تنظیمات پروژه',
                'title' => 'تنظیمات پروژه',
                'subtitle' => '',
                'setup_notice' => 'برای شروع کار با اسمارک، تنظیمات ضروری پروژه را تکمیل کنید.',
                'projects_table_missing' => 'جدول پروژه‌ها پیدا نشد. لطفاً SMark Core (Project Management) را فعال کنید و دوباره تلاش کنید.',
                'project_name' => 'نام پروژه',
                'project_name_help' => 'الزامی. مقدار پیش‌فرض نام سایت شماست و بعداً قابل تغییر است.',
                'brand_language' => 'زبان برند',
                'canva_template' => 'قالب کانوا',
                'website' => 'سایت',
                'mark_credit' => 'میزان اعتبار مارک',
                'mark_credit_help' => 'این مقدار از سرور مرکزی خوانده می‌شود و در اینجا قابل ویرایش نیست.',
                'wp_connection' => 'اتصال به وردپرس',
                'sc_connection' => 'اتصال به سرچ کنسول',
                'connected' => 'متصل هستیم',
                'connect' => 'اتصال',
                'checking' => 'در حال بررسی...',
                'saving' => 'در حال ذخیره...',
                'save' => 'ذخیره تنظیمات',
                'project_settings_saved' => 'تنظیمات پروژه ذخیره شد.',
                'project_name_required' => 'نام پروژه الزامی است.',
                'open_pm' => 'باز کردن مدیریت پروژه',
                'permissions' => 'عدم دسترسی.',
                'db_invalid' => 'جدول پروژه‌ها یافت نشد.',
                'central_sync_failed' => 'پروژه در سایت مرکزی ایجاد/به‌روزرسانی نشد.',
                'breadcrumb_dashboard' => 'داشبورد',
                'sc_success_notice' => 'اتصال به سرچ کنسول با موفقیت انجام شد.',
                'sc_error_notice' => 'اتصال به سرچ کنسول با خطا مواجه شد.',
                'not_connected' => 'متصل نیستیم',
                'sc_client_secret' => 'Client Secret گوگل',
                'sc_client_secret_help' => 'برای تکمیل اتصال به سرچ کنسول ضروری است.',
                'sc_save_secret' => 'ذخیره Client Secret',
                'sc_secret_saved' => 'Client Secret ذخیره شد. دوباره روی اتصال کلیک کنید.',
                'modules' => 'ماژول‌ها',
                'modules_help' => 'ماژول‌های داشبورد را روشن یا خاموش کنید. ماژول‌های خاموش در منوی سمت راست داشبورد نمایش داده نمی‌شوند.',
                'module_email' => 'ایمیل مارکتینگ',
                'module_seo' => 'سئو',
                'module_social' => 'سوشال مدیا',
                'module_on' => 'روشن',
                'module_off' => 'خاموش',
            ),
        );

        return isset($strings[$lang]) ? $strings[$lang] : $strings['en'];
    }

    public static function get_default_module_visibility() {
        return array(
            'email' => true,
            'seo' => true,
            'social' => true,
        );
    }

    public static function get_module_visibility() {
        $defaults = self::get_default_module_visibility();
        $saved = get_option(self::OPTION_MODULE_VISIBILITY, array());
        $saved = is_array($saved) ? $saved : array();
        $visibility = array();

        foreach ($defaults as $module => $enabled) {
            $visibility[$module] = array_key_exists($module, $saved) ? (bool) $saved[$module] : (bool) $enabled;
        }

        return $visibility;
    }

    private function save_module_visibility_from_request() {
        $modules = self::get_default_module_visibility();
        $enabled_modules = isset($_POST['smark_enabled_modules']) ? (array) wp_unslash($_POST['smark_enabled_modules']) : array();
        $enabled_modules = array_map('sanitize_key', $enabled_modules);
        $visibility = array();

        foreach ($modules as $module => $enabled) {
            $visibility[$module] = in_array($module, $enabled_modules, true);
        }

        update_option(self::OPTION_MODULE_VISIBILITY, $visibility, false);
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
            return (string) constant('SMARK_GOOGLE_CLIENT_ID');
        }

        return self::DEFAULT_GOOGLE_CLIENT_ID;
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
            return (string) constant('SMARK_GOOGLE_CLIENT_SECRET');
        }

        return '';
    }

    private function is_sc_oauth_broker_enabled() {
        $enabled = get_option(self::SC_OAUTH_BROKER_ENABLED_OPTION, '');
        $enabled = ($enabled === '1' || $enabled === 1 || $enabled === true);

        if (!$enabled) {
            $site_host = wp_parse_url((string) home_url('/'), PHP_URL_HOST);
            $central_host = wp_parse_url($this->get_sc_oauth_broker_base_url(), PHP_URL_HOST);
            if (is_string($site_host) && is_string($central_host) && strtolower($site_host) === strtolower($central_host)) {
                $enabled = true;
            }
        }
        return (bool) apply_filters('SMARK_sc_oauth_broker_enabled', $enabled);
    }

    private function get_sc_oauth_broker_base_url() {
        $url = get_option('smark_sc_oauth_broker_base_url', '');
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            $url = $this->get_central_endpoint(self::CENTRAL_SC_OAUTH_BROKER_PATH);
        }

        $url = rtrim($url, '/');
        $filtered = apply_filters('SMARK_sc_oauth_broker_base_url', $url);
        $filtered = is_string($filtered) ? trim($filtered) : $url;
        return rtrim($filtered, '/');
    }

    private function build_url_with_fragment($url, $fragment_args) {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '' || !is_array($fragment_args) || empty($fragment_args)) {
            return $url;
        }

        $url = preg_replace('/#.*/', '', $url);
        $fragment = http_build_query($fragment_args, '', '&', PHP_QUERY_RFC3986);
        return $url . '#' . $fragment;
    }

    private function validate_sc_return_url($return_url) {
        $return_url = is_string($return_url) ? trim($return_url) : '';
        if ($return_url === '') {
            return false;
        }

        $validated = function_exists('wp_http_validate_url') ? wp_http_validate_url($return_url) : $return_url;
        if (!$validated) {
            return false;
        }

        $parts = wp_parse_url($return_url);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['path'])) {
            return false;
        }

        $query = array();
        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        if (empty($query['page']) || (string) $query['page'] !== 'smark-project-settings') {
            return false;
        }

        return $return_url;
    }

    public function filter_allowed_redirect_hosts($hosts) {
        if (!is_array($hosts)) {
            $hosts = array();
        }

        foreach ($this->smark_ps_redirect_allowed_hosts as $host) {
            if (is_string($host) && $host !== '') {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    private function safe_redirect($url, $extra_allowed_hosts = array()) {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return;
        }

        $sanitized_url = esc_url_raw($url);
        if (is_string($sanitized_url) && $sanitized_url !== '') {
            $url = $sanitized_url;
        }

        $allowed = array();
        if (is_array($extra_allowed_hosts)) {
            foreach ($extra_allowed_hosts as $host) {
                $host = is_string($host) ? strtolower(trim($host)) : '';
                $host = preg_replace('/[^a-z0-9.\\-]/', '', $host);
                if ($host !== '') {
                    $allowed[] = $host;
                }
            }
        }

        if (!empty($allowed)) {
            $this->smark_ps_redirect_allowed_hosts = $allowed;
            add_filter('allowed_redirect_hosts', array($this, 'filter_allowed_redirect_hosts'));
        }

        wp_safe_redirect($url);
        exit;
    }

    /**
     * AJAX handlers in this feature must return clean JSON because the client
     * immediately consumes auth URLs. Any stray output (warnings/notices/BOM)
     * breaks jQuery's JSON handling and results in a generic "Unable to initiate"
     * message on the page.
     */
    private function clear_ajax_output_buffers() {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    public function register_sc_oauth_broker_routes() {
        register_rest_route('smark/v1', '/sc-oauth/start', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'rest_sc_oauth_broker_start'),
        ));

        register_rest_route('smark/v1', '/sc-oauth/callback', array(
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'rest_sc_oauth_broker_callback'),
        ));

        register_rest_route('smark/v1', '/sc-oauth/claim', array(
            'methods' => array('POST', 'OPTIONS'),
            'permission_callback' => '__return_true',
            'callback' => array($this, 'rest_sc_oauth_broker_claim'),
        ));
    }

    public function rest_sc_oauth_broker_start($request) {
        if (!$this->is_sc_oauth_broker_enabled()) {
            return new WP_Error('smark_sc_broker_disabled', 'OAuth broker is disabled on this site.', array('status' => 403));
        }

        $client_id = $this->get_google_client_id();
        $client_secret = $this->get_google_client_secret();
        if ($client_id === '' || $client_secret === '') {
            return new WP_Error('smark_sc_broker_missing_credentials', 'Google OAuth credentials are not configured on the broker.', array('status' => 500));
        }

        $return_url = $this->validate_sc_return_url($request->get_param('return'));
        $client_state = sanitize_text_field((string) $request->get_param('cs'));
        $site = sanitize_text_field((string) $request->get_param('site'));

        if (!$return_url || $client_state === '') {
            return new WP_Error('smark_sc_broker_invalid_request', 'Invalid OAuth request.', array('status' => 400));
        }

        if ($site !== '') {
            $site_host = wp_parse_url($site, PHP_URL_HOST);
            $return_host = wp_parse_url($return_url, PHP_URL_HOST);
            if (!is_string($site_host) || !is_string($return_host) || strtolower($site_host) !== strtolower($return_host)) {
                return new WP_Error('smark_sc_broker_site_mismatch', 'OAuth request host mismatch.', array('status' => 400));
            }
        }

        $broker_state = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : wp_hash(uniqid('smark_sc_broker_', true));
        set_transient(
            'smark_sc_broker_state_' . $broker_state,
            array(
                'return_url' => $return_url,
                'client_state' => $client_state,
                'site' => $site,
                'created_at' => time(),
            ),
            10 * MINUTE_IN_SECONDS
        );

        $redirect_uri = rest_url('smark/v1/sc-oauth/callback');
        $oauth_args = array(
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $broker_state,
        );

        $auth_url = self::GOOGLE_OAUTH_AUTHORIZE_ENDPOINT . '?' . http_build_query($oauth_args, '', '&', PHP_QUERY_RFC3986);
        $google_host = wp_parse_url(self::GOOGLE_OAUTH_AUTHORIZE_ENDPOINT, PHP_URL_HOST);
        $this->safe_redirect($auth_url, $google_host ? array($google_host) : array());
    }

    private function exchange_google_oauth_code_via_google($code, $redirect_uri) {
        $client_id = $this->get_google_client_id();
        $client_secret = $this->get_google_client_secret();
        $code = is_string($code) ? trim($code) : '';
        $redirect_uri = is_string($redirect_uri) ? trim($redirect_uri) : '';

        if ($client_id === '' || $client_secret === '' || $code === '' || $redirect_uri === '') {
            return new WP_Error('smark_sc_broker_invalid', 'Invalid OAuth exchange request.');
        }

        $resp = wp_remote_post(self::GOOGLE_OAUTH_TOKEN_ENDPOINT, array(
            'timeout' => 20,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) constant('SMARK_VERSION') : '1.0.0') . ' (sc-oauth-broker)',
            ),
            'body' => array(
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ),
        ));

        if (is_wp_error($resp)) {
            return $resp;
        }

        $http_code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($http_code < 200 || $http_code >= 300) {
            $msg = 'OAuth token exchange failed (HTTP ' . $http_code . ')';
            if (is_array($data) && !empty($data['error_description'])) {
                $msg = (string) $data['error_description'] . ' (HTTP ' . $http_code . ')';
            } elseif (is_array($data) && !empty($data['error'])) {
                $msg = (string) $data['error'] . ' (HTTP ' . $http_code . ')';
            }
            return new WP_Error('smark_sc_broker_exchange_failed', $msg, array('status' => $http_code, 'body' => $body));
        }

        if (!is_array($data) || empty($data['access_token'])) {
            return new WP_Error('smark_sc_broker_exchange_invalid', 'Invalid token response from Google.');
        }

        return array(
            'access_token' => (string) $data['access_token'],
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : 0,
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : '',
            'created' => time(),
        );
    }

    public function rest_sc_oauth_broker_callback($request) {
        if (!$this->is_sc_oauth_broker_enabled()) {
            return new WP_Error('smark_sc_broker_disabled', 'OAuth broker is disabled on this site.', array('status' => 403));
        }

        $broker_state = sanitize_text_field((string) $request->get_param('state'));
        $code = sanitize_text_field((string) $request->get_param('code'));
        $error = sanitize_text_field((string) $request->get_param('error'));
        $error_desc = sanitize_text_field((string) $request->get_param('error_description'));

        if ($broker_state === '') {
            return new WP_Error('smark_sc_broker_missing_state', 'Missing state.', array('status' => 400));
        }

        $state = get_transient('smark_sc_broker_state_' . $broker_state);
        if (!is_array($state) || empty($state['return_url']) || empty($state['client_state'])) {
            return new WP_Error('smark_sc_broker_expired_state', 'Expired state.', array('status' => 400));
        }

        $return_url = (string) $state['return_url'];
        $client_state = (string) $state['client_state'];
        $return_host = wp_parse_url($return_url, PHP_URL_HOST);
        $allowed_hosts = is_string($return_host) && $return_host !== '' ? array($return_host) : array();
        delete_transient('smark_sc_broker_state_' . $broker_state);

        if ($error !== '') {
            $redirect = $this->build_url_with_fragment($return_url, array(
                'smark_sc_error' => $error,
                'smark_sc_error_desc' => $error_desc,
                'smark_sc_state' => $client_state,
            ));
            $this->safe_redirect($redirect, $allowed_hosts);
        }

        if ($code === '') {
            $redirect = $this->build_url_with_fragment($return_url, array(
                'smark_sc_error' => 'missing_code',
                'smark_sc_state' => $client_state,
            ));
            $this->safe_redirect($redirect, $allowed_hosts);
        }

        $redirect_uri = rest_url('smark/v1/sc-oauth/callback');
        $tokens = $this->exchange_google_oauth_code_via_google($code, $redirect_uri);
        if (is_wp_error($tokens)) {
            $redirect = $this->build_url_with_fragment($return_url, array(
                'smark_sc_error' => 'exchange_failed',
                'smark_sc_error_desc' => $tokens->get_error_message(),
                'smark_sc_state' => $client_state,
            ));
            $this->safe_redirect($redirect, $allowed_hosts);
        }

        $claim_code = wp_generate_password(48, false, false);
        set_transient(
            'smark_sc_broker_claim_' . $claim_code,
            array(
                'tokens' => $tokens,
                'client_state' => $client_state,
                'created_at' => time(),
            ),
            10 * MINUTE_IN_SECONDS
        );

        $redirect = $this->build_url_with_fragment($return_url, array(
            'smark_sc_claim' => $claim_code,
            'smark_sc_state' => $client_state,
        ));
        $this->safe_redirect($redirect, $allowed_hosts);
    }

    public function rest_sc_oauth_broker_claim($request) {
        if (!$this->is_sc_oauth_broker_enabled()) {
            return new WP_Error('smark_sc_broker_disabled', 'OAuth broker is disabled on this site.', array('status' => 403));
        }

        if (strtoupper((string) $request->get_method()) === 'OPTIONS') {
            $resp = new WP_REST_Response(array('ok' => true));
            $resp->set_headers(array(
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type',
                'Access-Control-Max-Age' => '600',
            ));
            return $resp;
        }

        $claim_code = '';
        $params = $request->get_json_params();
        if (is_array($params) && isset($params['claim_code'])) {
            $claim_code = sanitize_text_field((string) $params['claim_code']);
        } elseif ($request->get_param('claim_code')) {
            $claim_code = sanitize_text_field((string) $request->get_param('claim_code'));
        }

        if ($claim_code === '') {
            return new WP_Error('smark_sc_broker_missing_claim', 'Missing claim code.', array('status' => 400));
        }

        $payload = get_transient('smark_sc_broker_claim_' . $claim_code);
        if (!is_array($payload) || empty($payload['tokens']) || !is_array($payload['tokens'])) {
            return new WP_Error('smark_sc_broker_invalid_claim', 'Invalid or expired claim code.', array('status' => 400));
        }

        delete_transient('smark_sc_broker_claim_' . $claim_code);

        $response = new WP_REST_Response(array(
            'tokens' => $payload['tokens'],
            'client_state' => isset($payload['client_state']) ? (string) $payload['client_state'] : '',
        ));
        $response->set_headers(array(
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ));
        return $response;
    }

    public function ajax_save_language() {
        check_ajax_referer('SMARK_project_settings_language_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => $this->get_strings()['permissions']), 403);
        }

        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '';
        if ($language === '' || !in_array($language, array('en', 'fa'), true)) {
            wp_send_json_error(array('message' => 'Invalid language'), 400);
        }

        // Keep both option keys in sync across features.
        update_option('smark_panel_language', $language);
        update_option('SMARK_panel_language', $language);

        wp_send_json_success(array('language' => $language));
    }

    public function ajax_connect_search_console() {
        check_ajax_referer('SMARK_project_management_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => $this->get_strings()['permissions']));
        }

        $project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
        if ($project_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid project'));
        }

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = self::escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => $this->get_strings()['db_invalid']));
        }

        $this->ensure_projects_table_schema($projects_table);

        global $wpdb;
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$projects_table_sql} WHERE id = %d", $project_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ($exists <= 0) {
            wp_send_json_error(array('message' => 'Project not found'));
        }

        $client_id = $this->get_google_client_id();
        if ($client_id === '') {
            wp_send_json_error(array('message' => 'Google OAuth client_id is not configured'));
        }

        $state_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : wp_hash(uniqid('smark_sc_', true));

        $base_settings_url = admin_url('admin.php?page=smark-project-settings');
        $use_broker = (bool) apply_filters('SMARK_sc_use_oauth_broker', true);

        // For the broker flow, the browser returns with a fragment (hash) to avoid leaking claim codes via referrers.
        // We still store redirect_uri for backward compatibility if direct OAuth is enabled via filter.
        $redirect_uri = $base_settings_url;
        set_transient(
            'smark_sc_oauth_state_' . $state_id,
            array(
                'user_id' => get_current_user_id(),
                'project_id' => $project_id,
                'redirect_uri' => $redirect_uri,
                'flow' => $use_broker ? 'broker' : 'direct',
                'created_at' => time(),
            ),
            10 * MINUTE_IN_SECONDS
        );

        if ($use_broker) {
            $broker_base = $this->get_sc_oauth_broker_base_url();
            if ($broker_base === '' || !wp_http_validate_url($broker_base . '/start')) {
                $this->clear_ajax_output_buffers();
                wp_send_json_error(array('message' => 'Search Console OAuth broker URL is invalid.'), 500);
            }
            $start_url = add_query_arg(array(
                'return' => $base_settings_url,
                'cs' => $state_id,
                'site' => rtrim((string) home_url('/'), '/'),
            ), $broker_base . '/start');
            $this->clear_ajax_output_buffers();
            wp_send_json_success(array('auth_url' => $start_url));
        }

        $oauth_args = array(
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state_id,
        );

        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($oauth_args, '', '&', PHP_QUERY_RFC3986);
        $this->clear_ajax_output_buffers();
        wp_send_json_success(array('auth_url' => $auth_url));
    }

    private function claim_google_oauth_tokens_via_broker($claim_code, $client_state) {
        $claim_code = is_string($claim_code) ? trim($claim_code) : '';
        $client_state = is_string($client_state) ? trim($client_state) : '';
        if ($claim_code === '' || $client_state === '') {
            return new WP_Error('smark_sc_claim_invalid', 'Invalid claim request.');
        }

        $broker_base = $this->get_sc_oauth_broker_base_url();
        $endpoint = $broker_base . '/claim';

        $resp = wp_remote_post($endpoint, array(
            'timeout' => 20,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) constant('SMARK_VERSION') : '1.0.0') . ' (sc-oauth-claim)',
            ),
            'body' => wp_json_encode(array(
                'claim_code' => $claim_code,
            )),
        ));

        if (is_wp_error($resp)) {
            return $resp;
        }

        $http_code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($http_code < 200 || $http_code >= 300) {
            $msg = 'Remote claim failed (HTTP ' . $http_code . ')';
            if (is_array($data) && isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
                $msg = $data['message'] . ' (HTTP ' . $http_code . ')';
            }
            return new WP_Error('smark_sc_claim_failed', $msg, array('status' => $http_code, 'body' => $body));
        }

        if (!is_array($data) || empty($data['tokens']) || !is_array($data['tokens'])) {
            return new WP_Error('smark_sc_claim_invalid_response', 'Invalid claim response from broker.');
        }

        $resp_state = isset($data['client_state']) ? (string) $data['client_state'] : '';
        if ($resp_state !== '' && $resp_state !== $client_state) {
            return new WP_Error('smark_sc_claim_state_mismatch', 'OAuth state mismatch.');
        }

        return $data['tokens'];
    }

    public function ajax_claim_search_console() {
        check_ajax_referer('SMARK_project_management_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => $this->get_strings()['permissions']), 403);
        }

        $claim_code = isset($_POST['claim_code']) ? sanitize_text_field(wp_unslash($_POST['claim_code'])) : '';
        $state_id = isset($_POST['state_id']) ? sanitize_text_field(wp_unslash($_POST['state_id'])) : '';

        if ($claim_code === '' || $state_id === '') {
            wp_send_json_error(array('message' => 'Missing claim_code or state_id'), 400);
        }

        $state = get_transient('smark_sc_oauth_state_' . $state_id);
        if (!is_array($state) || empty($state['user_id']) || empty($state['project_id'])) {
            wp_send_json_error(array('message' => 'Expired state'), 400);
        }

        if ((int) $state['user_id'] !== get_current_user_id()) {
            wp_send_json_error(array('message' => 'User mismatch'), 403);
        }

        $project_id = (int) $state['project_id'];
        if ($project_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid project'), 400);
        }

        $token_data = $this->claim_google_oauth_tokens_via_broker($claim_code, $state_id);
        if (is_wp_error($token_data)) {
            if (class_exists('SMarkLogger')) {
                SMarkLogger::error('Search Console - Broker claim failed', array(
                    'project_id' => $project_id,
                    'error_message' => $token_data->get_error_message(),
                    'error_code' => $token_data->get_error_code(),
                    'error_data' => $token_data->get_error_data(),
                ));
            }
            $err_data = $token_data->get_error_data();
            $status = 500;
            if (is_array($err_data) && isset($err_data['status'])) {
                $status = (int) $err_data['status'];
            }
            if ($status < 400 || $status > 599) {
                $status = 500;
            }
            wp_send_json_error(array(
                'message' => $token_data->get_error_message(),
                'code' => $token_data->get_error_code(),
            ), $status);
        }

        $tokens = array(
            'access_token' => isset($token_data['access_token']) ? (string) $token_data['access_token'] : '',
            'expires_in' => isset($token_data['expires_in']) ? (int) $token_data['expires_in'] : 0,
            'refresh_token' => isset($token_data['refresh_token']) ? (string) $token_data['refresh_token'] : '',
            'created' => time(),
        );

        if ($tokens['access_token'] === '') {
            wp_send_json_error(array('message' => 'Missing access token'), 500);
        }

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = self::escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => $this->get_strings()['db_invalid']), 500);
        }

        $this->ensure_projects_table_schema($projects_table);

        global $wpdb;
        if ($tokens['refresh_token'] === '') {
            $existing_row = $wpdb->get_row($wpdb->prepare("SELECT search_console_tokens FROM {$projects_table_sql} WHERE id = %d", $project_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (is_array($existing_row) && !empty($existing_row['search_console_tokens'])) {
                $decoded = $this->decode_search_console_tokens((string) $existing_row['search_console_tokens']);
                if (is_array($decoded) && !empty($decoded['refresh_token'])) {
                    $tokens['refresh_token'] = (string) $decoded['refresh_token'];
                }
            }
        }

        $encrypted_tokens = $this->encode_search_console_tokens($tokens);
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $projects_table,
            array('search_console_tokens' => $encrypted_tokens),
            array('id' => $project_id),
            array('%s'),
            array('%d')
        );

        delete_transient('smark_sc_oauth_state_' . $state_id);

        wp_send_json_success(array('ok' => true));
    }

    public function ajax_store_search_console_tokens() {
        check_ajax_referer('SMARK_project_management_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => $this->get_strings()['permissions']), 403);
        }

        $state_id = isset($_POST['state_id']) ? sanitize_text_field(wp_unslash($_POST['state_id'])) : '';
        $tokens_raw = isset($_POST['tokens']) ? sanitize_textarea_field(wp_unslash($_POST['tokens'])) : '';
        $tokens_raw = is_string($tokens_raw) ? trim($tokens_raw) : '';

        if ($state_id === '' || $tokens_raw === '') {
            wp_send_json_error(array('message' => 'Missing state_id or tokens'), 400);
        }

        $state = get_transient('smark_sc_oauth_state_' . $state_id);
        if (!is_array($state) || empty($state['user_id']) || empty($state['project_id'])) {
            wp_send_json_error(array('message' => 'Expired state'), 400);
        }

        if ((int) $state['user_id'] !== get_current_user_id()) {
            wp_send_json_error(array('message' => 'User mismatch'), 403);
        }

        $project_id = (int) $state['project_id'];
        if ($project_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid project'), 400);
        }

        $decoded = json_decode($tokens_raw, true);
        if (!is_array($decoded)) {
            wp_send_json_error(array('message' => 'Invalid tokens JSON'), 400);
        }

        $tokens = array(
            'access_token' => isset($decoded['access_token']) ? sanitize_text_field((string) $decoded['access_token']) : '',
            'expires_in' => isset($decoded['expires_in']) ? absint($decoded['expires_in']) : 0,
            'refresh_token' => isset($decoded['refresh_token']) ? sanitize_text_field((string) $decoded['refresh_token']) : '',
            'created' => time(),
        );

        if ($tokens['access_token'] === '') {
            wp_send_json_error(array('message' => 'Missing access token'), 400);
        }

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = self::escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => $this->get_strings()['db_invalid']), 500);
        }

        $this->ensure_projects_table_schema($projects_table);

        global $wpdb;
        if ($tokens['refresh_token'] === '') {
            $existing_row = $wpdb->get_row($wpdb->prepare("SELECT search_console_tokens FROM {$projects_table_sql} WHERE id = %d", $project_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (is_array($existing_row) && !empty($existing_row['search_console_tokens'])) {
                $existing_decoded = $this->decode_search_console_tokens((string) $existing_row['search_console_tokens']);
                if (is_array($existing_decoded) && !empty($existing_decoded['refresh_token'])) {
                    $tokens['refresh_token'] = (string) $existing_decoded['refresh_token'];
                }
            }
        }

        $encrypted_tokens = $this->encode_search_console_tokens($tokens);
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $projects_table,
            array('search_console_tokens' => $encrypted_tokens),
            array('id' => $project_id),
            array('%s'),
            array('%d')
        );

        delete_transient('smark_sc_oauth_state_' . $state_id);

        wp_send_json_success(array('ok' => true));
    }

    private function exchange_google_oauth_code_via_central($code, $redirect_uri) {
        $code = is_string($code) ? trim($code) : '';
        $redirect_uri = is_string($redirect_uri) ? trim($redirect_uri) : '';
        if ($code === '' || $redirect_uri === '') {
            return new WP_Error('smark_sc_invalid', 'Invalid OAuth code or redirect_uri.');
        }

        $token = $this->get_central_sync_token();
        if ($token === '') {
            return new WP_Error('smark_sc_sync_token_missing', 'Central sync token not configured.');
        }

        $payload = array(
            'code' => $code,
            'redirect_uri' => $redirect_uri,
            'website' => rtrim((string) home_url('/'), '/'),
        );

        $response = wp_remote_post($this->get_central_endpoint(self::CENTRAL_GOOGLE_OAUTH_EXCHANGE_PATH), array(
            'timeout' => 20,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                self::CENTRAL_SYNC_TOKEN_HEADER => $token,
                'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) constant('SMARK_VERSION') : '1.0.0') . ' (sc-oauth-exchange)',
            ),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($http_code < 200 || $http_code >= 300) {
            $msg = 'Remote request failed (HTTP ' . $http_code . ')';
            if (is_array($data) && isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
                $msg = $data['message'] . ' (HTTP ' . $http_code . ')';
            }
            return new WP_Error('smark_sc_exchange_failed', $msg, array('status' => $http_code, 'body' => $body));
        }

        if (!is_array($data) || empty($data['tokens']) || !is_array($data['tokens'])) {
            return new WP_Error('smark_sc_exchange_invalid', 'Invalid token response from central server.');
        }

        return $data['tokens'];
    }

    private function handle_search_console_oauth_callback($state_id, $code) {
        $base_settings_url = admin_url('admin.php?page=smark-project-settings');
        $redirect_error = function ($error_code, $error_description = '') use ($base_settings_url) {
            $args = array('sc_error' => (string) $error_code);
            if (is_string($error_description) && $error_description !== '') {
                $args['sc_error_desc'] = $error_description;
            }
            wp_safe_redirect(add_query_arg($args, $base_settings_url));
            exit;
        };

        if ($state_id === '' || $code === '') {
            $redirect_error('invalid_request');
        }

        $state = get_transient('smark_sc_oauth_state_' . $state_id);
        if (!is_array($state) || empty($state['user_id']) || empty($state['project_id'])) {
            $redirect_error('expired_state');
        }

        if ((int) $state['user_id'] !== get_current_user_id()) {
            $redirect_error('user_mismatch');
        }

        $project_id = (int) $state['project_id'];
        $redirect_uri = isset($state['redirect_uri']) && is_string($state['redirect_uri']) ? $state['redirect_uri'] : $base_settings_url;

        $token_data = $this->exchange_google_oauth_code_via_central($code, $redirect_uri);
        if (is_wp_error($token_data)) {
            if (class_exists('SMarkLogger')) {
                SMarkLogger::error('Search Console - Central token exchange failed', array(
                    'project_id' => $project_id,
                    'error_message' => $token_data->get_error_message(),
                    'error_code' => $token_data->get_error_code(),
                    'error_data' => $token_data->get_error_data(),
                ));
            }
            $redirect_error('central_exchange_failed', $token_data->get_error_message());
        }

        $tokens = array(
            'access_token' => isset($token_data['access_token']) ? (string) $token_data['access_token'] : '',
            'expires_in' => isset($token_data['expires_in']) ? (int) $token_data['expires_in'] : 0,
            'refresh_token' => isset($token_data['refresh_token']) ? (string) $token_data['refresh_token'] : '',
            'created' => time(),
        );

        if ($tokens['access_token'] === '') {
            $redirect_error('missing_access_token');
        }

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = self::escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            $redirect_error('db_invalid');
        }

        $this->ensure_projects_table_schema($projects_table);

        global $wpdb;
        if ($tokens['refresh_token'] === '') {
            $existing_row = $wpdb->get_row($wpdb->prepare("SELECT search_console_tokens FROM {$projects_table_sql} WHERE id = %d", $project_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (is_array($existing_row) && !empty($existing_row['search_console_tokens'])) {
                $decoded = $this->decode_search_console_tokens((string) $existing_row['search_console_tokens']);
                if (is_array($decoded) && !empty($decoded['refresh_token'])) {
                    $tokens['refresh_token'] = (string) $decoded['refresh_token'];
                }
            }
        }

        $encrypted_tokens = $this->encode_search_console_tokens($tokens);
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $projects_table,
            array('search_console_tokens' => $encrypted_tokens),
            array('id' => $project_id),
            array('%s'),
            array('%d')
        );

        delete_transient('smark_sc_oauth_state_' . $state_id);

        wp_safe_redirect(add_query_arg(array('sc_success' => '1'), $base_settings_url));
        exit;
    }

    public function add_menu() {
        $strings = $this->get_strings();
        add_submenu_page(
            null,
            $strings['title'],
            $strings['menu'],
            'smark_access',
            'smark-project-settings',
            array($this, 'render_page')
        );
    }
 
    private function is_setup_complete() {
        return (bool) get_option(self::OPTION_SETUP_COMPLETED, false);
    }

    private function projects_table_exists() {
        global $wpdb;
        $table = $this->resolve_projects_table();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $exists === $table;
    }

    private function is_smark_admin_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check.
        if (!is_admin() || !isset($_GET['page'])) {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check.
        $page = sanitize_key(wp_unslash($_GET['page']));

        // Only enforce setup on SMark plugin pages (not SMark-Core pages).
        $smark_pages = array(
            'smark-dashboard',
            'smark-dashboard-page',
            'smark-social-media',
            'smark-seo-optimization',
            'smark-google-docs-converter',
            'smark-headline-analyzer',
            'smark-keyword-research',
            'smark-competitor-analysis',
            'smark-project-settings',
        );

        return in_array($page, $smark_pages, true);
    }

    public function maybe_force_setup() {
        if (!is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if (!current_user_can('smark_access')) {
            return;
        }

        if (!$this->is_smark_admin_page()) {
            return;
        }

        if (!$this->projects_table_exists()) {
            return;
        }

        // Ensure we always have a project row for this site, so connection buttons can work.
        $this->ensure_site_project_row();

        if ($this->is_setup_complete()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for admin routing (no state change).
        $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($current_page === 'smark-project-settings') {
            return;
        }
 
        wp_safe_redirect(admin_url('admin.php?page=smark-project-settings&setup=1'));
        exit;
    }
 
    private static function escape_db_identifier($identifier) {
        $identifier = (string) $identifier;
        if ($identifier === '') {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9_$.]+$/', $identifier)) {
            return '';
        }
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function get_table_columns($table_name) {
        global $wpdb;
        $table_sql = self::escape_db_identifier($table_name);
        if ($table_sql === '') {
            return array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_sql}", ARRAY_A);
        if (!is_array($columns)) {
            return array();
        }

        $names = array();
        foreach ($columns as $col) {
            if (is_array($col) && isset($col['Field'])) {
                $names[] = (string) $col['Field'];
            }
        }

        return array_values(array_unique($names));
    }

    private function table_has_column($table_name, $column) {
        return in_array((string) $column, $this->get_table_columns($table_name), true);
    }

    private function ensure_projects_table_schema($projects_table) {
        global $wpdb;
        $projects_table_sql = self::escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return false;
        }

        if (!$this->table_has_column($projects_table, 'project_id')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
            $wpdb->query("ALTER TABLE {$projects_table_sql} ADD COLUMN project_id varchar(50) DEFAULT NULL AFTER id");
        }

        if (!$this->table_has_column($projects_table, 'brand_language')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
            $wpdb->query("ALTER TABLE {$projects_table_sql} ADD COLUMN brand_language varchar(10) DEFAULT 'fa' AFTER project_name");
        }

        if (!$this->table_has_column($projects_table, 'canva_template')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
            $wpdb->query("ALTER TABLE {$projects_table_sql} ADD COLUMN canva_template varchar(1000) DEFAULT NULL AFTER brand_language");
        }

        if (!$this->table_has_column($projects_table, 'website')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
            $wpdb->query("ALTER TABLE {$projects_table_sql} ADD COLUMN website varchar(1000) DEFAULT NULL AFTER canva_template");
        }

        if (!$this->table_has_column($projects_table, 'wp_connected')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
            $wpdb->query("ALTER TABLE {$projects_table_sql} ADD COLUMN wp_connected tinyint(1) DEFAULT 0 AFTER website");
        }

        if (!$this->table_has_column($projects_table, 'search_console_tokens')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
            $wpdb->query("ALTER TABLE {$projects_table_sql} ADD COLUMN search_console_tokens longtext DEFAULT NULL AFTER wp_connected");
        }

        return true;
    }
 
    private function resolve_projects_table() {
        global $wpdb;
        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
        $candidates = array($prefix . 'SMARK_projects', $prefix . 'smark_projects');

        $existing = array();
        foreach ($candidates as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($exists === $table) {
                $existing[] = $table;
            }
        }

        if (empty($existing)) {
            return $prefix . 'SMARK_projects';
        }

        if (count($existing) === 1) {
            return $existing[0];
        }

        // Prefer the table that already has the website column (best for upserts), otherwise prefer SMARK_projects.
        foreach ($existing as $table) {
            if ($this->table_has_column($table, 'website')) {
                return $table;
            }
        }

        return $prefix . 'SMARK_projects';
    }
 
    private function get_current_project_db_id() {
        return (int) get_option(self::OPTION_CURRENT_PROJECT_DB_ID, 0);
    }
 
    private function set_current_project_db_id($id) {
        update_option(self::OPTION_CURRENT_PROJECT_DB_ID, (int) $id, false);
    }

    private function get_central_project_db_id() {
        return (int) get_option(self::OPTION_CENTRAL_PROJECT_DB_ID, 0);
    }

    private function set_central_project_db_id($id) {
        update_option(self::OPTION_CENTRAL_PROJECT_DB_ID, (int) $id, false);
    }
 
    private function get_current_project() {
        global $wpdb;
        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = self::escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return null;
        }
 
        $db_id = $this->get_current_project_db_id();
        if ($db_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$projects_table_sql} WHERE id = %d", $db_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (is_array($row) && !empty($row)) {
                return $row;
            }
        }
 
        $order_by = $this->table_has_column($projects_table, 'created_at') ? 'created_at DESC, id DESC' : 'id DESC';
        $row = $wpdb->get_row("SELECT * FROM {$projects_table_sql} ORDER BY {$order_by} LIMIT 1", ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if (is_array($row) && !empty($row) && isset($row['id'])) {
            $this->set_current_project_db_id((int) $row['id']);
            return $row;
        }
 
        return null;
    }

    private function ensure_site_project_row() {
        global $wpdb;
        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = self::escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return null;
        }

        $this->ensure_projects_table_schema($projects_table);

        $website = function_exists('home_url') ? home_url('/') : '';
        $website = rtrim((string) $website, '/');
        if ($website === '') {
            return null;
        }

        $existing_id = 0;
        if ($this->table_has_column($projects_table, 'website')) {
            $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$projects_table_sql} WHERE website = %s ORDER BY id DESC LIMIT 1", $website)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }
        if ($existing_id > 0) {
            $this->set_current_project_db_id($existing_id);
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$projects_table_sql} WHERE id = %d", $existing_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }

        $project_name = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
        $brand_language = $this->is_persian_site() ? 'fa' : 'en';

        $insert_data = array(
            'project_name' => $project_name,
            'brand_language' => $brand_language,
            'website' => $website,
        );
        $insert_format = array('%s', '%s', '%s');

        if ($this->table_has_column($projects_table, 'wp_connected')) {
            $insert_data['wp_connected'] = 1;
            $insert_format[] = '%d';
        }

        if ($this->table_has_column($projects_table, 'created_at')) {
            $insert_data['created_at'] = current_time('mysql');
            $insert_format[] = '%s';
        }
        if ($this->table_has_column($projects_table, 'updated_at')) {
            $insert_data['updated_at'] = current_time('mysql');
            $insert_format[] = '%s';
        }

        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $projects_table,
            $insert_data,
            $insert_format
        );

        if ($inserted === false) {
            return null;
        }

        $db_id = (int) $wpdb->insert_id;
        $this->set_current_project_db_id($db_id);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$projects_table_sql} WHERE id = %d", $db_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $this->ensure_project_id($row);
        return $row;
    }

    public function enqueue_assets($hook) {
        $hook = (string) $hook;
        $is_project_settings_page = strpos($hook, 'smark-project-settings') !== false;
        $is_dashboard_page = in_array($hook, array('toplevel_page_smark-dashboard', 'smark_page_smark-dashboard-page'), true);
        if (!$is_project_settings_page && !$is_dashboard_page) {
            return;
        }

        $asset_version = defined('SMARK_VERSION') ? (string) constant('SMARK_VERSION') : '1.0.0';

        // Ensure shared "plugin page" layout styles apply (same as other feature pages).
        add_filter('admin_body_class', function ($classes) {
            if (strpos((string) $classes, 'smark-plugin-page') === false) {
                $classes .= ' smark-plugin-page';
            }
            return $classes;
        });

        $strings = $this->get_strings();
        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'vazirmatn-font',
            'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap',
            array(),
            $asset_version
        );

        $css_version = $asset_version;
        $css_path = plugin_dir_path(__FILE__) . 'assets/project-settings.css';
        if (is_readable($css_path)) {
            $css_version .= '.' . (string) filemtime($css_path);
        }
        wp_enqueue_style(
            'smark-project-settings',
            plugin_dir_url(__FILE__) . 'assets/project-settings.css',
            array('dashicons', 'vazirmatn-font'),
            $css_version
        );

        $js_version = $asset_version;
        $js_path = plugin_dir_path(__FILE__) . 'assets/project-settings.js';
        if (is_readable($js_path)) {
            $js_version .= '.' . (string) filemtime($js_path);
        }
        wp_enqueue_script(
            'smark-project-settings',
            plugin_dir_url(__FILE__) . 'assets/project-settings.js',
            array('jquery'),
            $js_version,
            true
        );

        $project = $this->get_current_project();
        $project_id = is_array($project) && isset($project['id']) ? (int) $project['id'] : 0;
        $project_public_id = is_array($project) && isset($project['project_id']) ? (string) $project['project_id'] : '';
        $project_website = is_array($project) && isset($project['website']) ? (string) $project['website'] : rtrim((string) home_url('/'), '/');
        $central_token = $this->get_central_sync_token();
        $central_project_db_id = $this->get_central_project_db_id();

        $pending_total = 0;
        $cached_mark = null;
        $cached_ts = 0;
        if ($project_id > 0) {
            $pending_all = get_option('smark_project_mark_pending_total', array());
            $pending_all = is_array($pending_all) ? $pending_all : array();
            $pending_total = isset($pending_all[(string) $project_id]) ? max(0, (int) $pending_all[(string) $project_id]) : 0;

            $cache = get_option('smark_project_mark_cache', array());
            $cache = is_array($cache) ? $cache : array();
            $row = isset($cache[(string) $project_id]) ? $cache[(string) $project_id] : null;
            if (is_array($row) && isset($row['mark'])) {
                $cached_mark = (int) $row['mark'];
                $cached_ts = isset($row['ts']) ? (int) $row['ts'] : 0;
            } elseif (is_numeric($row)) {
                $cached_mark = (int) $row;
            }
        }

        wp_localize_script('smark-project-settings', 'SMarkProjectSettings', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pmNonce' => wp_create_nonce('SMARK_project_management_nonce'),
            'languageNonce' => wp_create_nonce('SMARK_project_settings_language_nonce'),
            'currentLang' => $this->get_panel_language(),
            'projectId' => $project_id,
            'projectPublicId' => $project_public_id,
            'projectWebsite' => $project_website,
            'centralProjectDbId' => $central_project_db_id,
            'centralMarkBalanceGetEndpoints' => $this->get_central_mark_balance_get_endpoints(),
            'centralToken' => $central_token,
            'pendingTotal' => $pending_total,
            'cachedMark' => $cached_mark,
            'cachedMarkTs' => $cached_ts,
            'scBrokerBase' => $this->get_sc_oauth_broker_base_url(),
            'strings' => array(
                'connect' => $strings['connect'],
                'connected' => $strings['connected'],
                'checking' => $strings['checking'],
                'saving' => $strings['saving'],
                'saved' => $strings['project_settings_saved'],
                'scSuccess' => $strings['sc_success_notice'],
                'scError' => $strings['sc_error_notice'],
            ),
        ));
    }

    private function ensure_project_id($project_row) {
        if (!is_array($project_row) || empty($project_row['id'])) {
            return;
        }
        if (!empty($project_row['project_id'])) {
            return;
        }
 
        global $wpdb;
        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = self::escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return;
        }

        $this->ensure_projects_table_schema($projects_table);
 
        $database_id = (int) $project_row['id'];
        $project_id = 'PRJ-' . str_pad((string) $database_id, 5, '0', STR_PAD_LEFT);
 
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$projects_table_sql} WHERE project_id = %s", $project_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ($exists > 0) {
            $project_id = 'PRJ-' . str_pad((string) $database_id, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 4));
        }
 
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $projects_table,
            array('project_id' => $project_id),
            array('id' => $database_id),
            array('%s'),
            array('%d')
        );
    }
 
    private function handle_submit() {
        check_admin_referer('smark_project_settings_save', 'smark_project_settings_nonce');
 
        if (!current_user_can('smark_access')) {
            $strings = $this->get_strings();
            wp_die(esc_html($strings['permissions']));
        }
 
        global $wpdb;
        $strings = $this->get_strings();
        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = self::escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            wp_die(esc_html($strings['db_invalid']));
        }

        $this->ensure_projects_table_schema($projects_table);
 
        $db_id = isset($_POST['project_db_id']) ? absint(wp_unslash($_POST['project_db_id'])) : 0;
        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';
        $brand_language = isset($_POST['brand_language']) ? sanitize_key(wp_unslash($_POST['brand_language'])) : 'en';
        $canva_template = isset($_POST['canva_template']) ? esc_url_raw(wp_unslash($_POST['canva_template'])) : '';
        $website = function_exists('home_url') ? home_url('/') : '';
        $website = rtrim((string) $website, '/');
 
        if ($project_name === '') {
            add_settings_error('smark_project_settings', 'project_name_required', $strings['project_name_required'], 'error');
            return false;
        }
 
        if (!in_array($brand_language, array('en', 'fa'), true)) {
            $brand_language = 'en';
        }
 
        $data = array(
            'project_name' => $project_name,
            'brand_language' => $brand_language,
            'canva_template' => $canva_template !== '' ? $canva_template : null,
            'website' => $website !== '' ? $website : null,
        );
        $format = array('%s', '%s', '%s', '%s');

        if ($this->table_has_column($projects_table, 'wp_connected')) {
            $data['wp_connected'] = 1;
            $format[] = '%d';
        }

        if ($this->table_has_column($projects_table, 'updated_at')) {
            $data['updated_at'] = current_time('mysql');
            $format[] = '%s';
        }
 
        // Upsert by website: if this site already has a project, update it.
        $existing_id = 0;
        if ($website !== '' && $this->table_has_column($projects_table, 'website')) {
            $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$projects_table_sql} WHERE website = %s ORDER BY id DESC LIMIT 1", $website)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }

        if ($existing_id > 0) {
            $db_id = $existing_id;
        }

        if ($db_id > 0) {
            $updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $projects_table,
                $data,
                array('id' => $db_id),
                $format,
                array('%d')
            );
            if ($updated === false) {
                add_settings_error('smark_project_settings', 'save_failed', __('Failed to save project settings.', 'smark'), 'error');
                return false;
            }
            $this->set_current_project_db_id($db_id);
        } else {
            $insert_data = $data;
            $insert_format = $format;
            if ($this->table_has_column($projects_table, 'created_at')) {
                $insert_data['created_at'] = current_time('mysql');
                $insert_format[] = '%s';
            }
            if ($this->table_has_column($projects_table, 'updated_at')) {
                $insert_data['updated_at'] = current_time('mysql');
                $insert_format[] = '%s';
            }

            $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $projects_table,
                $insert_data,
                $insert_format
            );
            if ($inserted === false) {
                add_settings_error('smark_project_settings', 'create_failed', __('Failed to create project.', 'smark'), 'error');
                return false;
            }
 
            $db_id = (int) $wpdb->insert_id;
            $this->set_current_project_db_id($db_id);
        }
 
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$projects_table_sql} WHERE id = %d", $db_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $this->ensure_project_id($project);
        $this->maybe_sync_project_to_central($project);
        $this->save_module_visibility_from_request();
  
        update_option(self::OPTION_SETUP_COMPLETED, true, false);
        add_settings_error('smark_project_settings', 'saved', $strings['project_settings_saved'], 'updated');
        return true;
    }

    private function get_central_base_url() {
        if (defined('SMARK_CENTRAL_BASE_URL') && is_string(SMARK_CENTRAL_BASE_URL) && SMARK_CENTRAL_BASE_URL !== '') {
            $url = $this->normalize_central_base_url((string) SMARK_CENTRAL_BASE_URL);
            if ($url !== '') {
                return $url;
            }
        }

        $url = get_option(self::OPTION_CENTRAL_BASE_URL, '');
        $url = is_string($url) ? $this->normalize_central_base_url($url) : '';
        if ($url !== '') {
            return $url;
        }

        if (is_multisite()) {
            $url = get_site_option(self::OPTION_CENTRAL_BASE_URL, '');
            $url = is_string($url) ? $this->normalize_central_base_url($url) : '';
            if ($url !== '') {
                return $url;
            }
        }

        $filtered = apply_filters('SMARK_central_base_url', self::DEFAULT_CENTRAL_BASE_URL);
        $filtered = is_string($filtered) ? $this->normalize_central_base_url($filtered) : '';
        return $filtered !== '' ? $filtered : self::DEFAULT_CENTRAL_BASE_URL;
    }

    private function normalize_central_base_url($url) {
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

    private function get_central_endpoint($path) {
        $path = is_string($path) ? '/' . ltrim($path, '/') : '';
        return rtrim($this->get_central_base_url(), '/') . $path;
    }

    private function get_central_endpoint_bases() {
        $primary = rtrim($this->get_central_base_url(), '/');
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
            $base = $this->normalize_central_base_url($base);
            if ($base !== '') {
                $normalized[] = $base;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function get_central_sync_token() {
        if (defined('SMARK_CENTRAL_SYNC_TOKEN')) {
            $token = constant('SMARK_CENTRAL_SYNC_TOKEN');
            if (is_string($token) && $token !== '') {
                return $token;
            }
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

    private function get_mark_pending_total_for_project($project_db_id) {
        $project_db_id = (int) $project_db_id;
        if ($project_db_id <= 0) {
            return 0;
        }

        $pending_all = get_option('smark_project_mark_pending_total', array());
        $pending_all = is_array($pending_all) ? $pending_all : array();
        return isset($pending_all[(string) $project_db_id]) ? max(0, (int) $pending_all[(string) $project_db_id]) : 0;
    }

    private function clear_mark_pending_total_for_project($project_db_id) {
        $project_db_id = (int) $project_db_id;
        if ($project_db_id <= 0) {
            return;
        }

        $pending_all = get_option('smark_project_mark_pending_total', array());
        $pending_all = is_array($pending_all) ? $pending_all : array();
        $key = (string) $project_db_id;
        if (isset($pending_all[$key])) {
            unset($pending_all[$key]);
            update_option('smark_project_mark_pending_total', $pending_all, false);
        }
    }

    private function set_mark_cache_for_project($project_db_id, $mark) {
        $project_db_id = (int) $project_db_id;
        if ($project_db_id <= 0) {
            return;
        }

        $cache = get_option('smark_project_mark_cache', array());
        $cache = is_array($cache) ? $cache : array();
        $cache[(string) $project_db_id] = array(
            'mark' => max(0, (int) $mark),
            'ts' => time(),
        );
        update_option('smark_project_mark_cache', $cache, false);
    }

    private function sync_local_mark_column($project_db_id, $mark) {
        $project_db_id = (int) $project_db_id;
        if ($project_db_id <= 0) {
            return;
        }

        global $wpdb;
        $projects_table = $this->resolve_projects_table();
        if ($projects_table && $this->table_has_column($projects_table, 'mark')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($projects_table, array('mark' => max(0, (int) $mark)), array('id' => $project_db_id), array('%d'), array('%d'));
        }
    }

    /**
     * @return string[]
     */
    private function get_central_mark_balance_endpoints() {
        $endpoints = array();
        foreach ($this->get_central_endpoint_bases() as $base) {
            $endpoints[] = $base . self::CENTRAL_MARK_BALANCE_PATH;
        }

        $filtered = apply_filters('SMARK_mark_balance_endpoints', $endpoints);
        if (is_array($filtered) && !empty($filtered)) {
            $endpoints = $filtered;
        }

        return array_values(array_unique($endpoints));
    }

    /**
     * @return string[]
     */
    private function get_central_mark_balance_get_endpoints() {
        $endpoints = array();
        foreach ($this->get_central_endpoint_bases() as $base) {
            $endpoints[] = $base . '/wp-json/smark-core/v1/projects/mark/balance-get';
        }

        $filtered = apply_filters('SMARK_mark_balance_get_endpoints', $endpoints);
        if (is_array($filtered) && !empty($filtered)) {
            $endpoints = $filtered;
        }

        return array_values(array_unique($endpoints));
    }

    /**
     * @return string[]
     */
    private function get_central_mark_consume_endpoints() {
        $endpoints = array();
        foreach ($this->get_central_endpoint_bases() as $base) {
            $endpoints[] = $base . self::CENTRAL_MARK_CONSUME_PATH;
        }

        $filtered = apply_filters('SMARK_mark_consume_endpoints', $endpoints);
        if (is_array($filtered) && !empty($filtered)) {
            $endpoints = $filtered;
        }

        return array_values(array_unique($endpoints));
    }

    /**
     * @param int $project_db_id
     * @param string $website
     * @param string $project_id
     * @param int $pending_total
     * @return int|WP_Error|null
     */
    private function reconcile_pending_mark_with_central($project_db_id, $website, $project_id, $pending_total) {
        $project_db_id = (int) $project_db_id;
        $pending_total = (int) $pending_total;
        $website = is_string($website) ? rtrim(trim($website), '/') : '';
        $project_id = is_string($project_id) ? trim($project_id) : '';

        if ($project_db_id <= 0 || $pending_total <= 0 || $website === '') {
            return null;
        }

        $central_id = $this->get_central_project_db_id();
        $token = $this->get_central_sync_token();
        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) constant('SMARK_VERSION') : '1.0.0') . ' (mark-reconcile)',
        );
        if ($token !== '') {
            $headers[self::CENTRAL_SYNC_TOKEN_HEADER] = $token;
        }

        $payload = array(
            'amount' => $pending_total,
            'website' => $website,
            'project_id' => $project_id,
            'id' => $central_id > 0 ? $central_id : 0,
        );

        $try_payloads = array($payload);
        if ($central_id > 0) {
            $fallback_payload = $payload;
            $fallback_payload['id'] = 0;
            $try_payloads[] = $fallback_payload;
        }

        $last_error = null;
        foreach ($try_payloads as $payload_try) {
            $args = array(
                'timeout' => 12,
                'headers' => $headers,
                'body' => wp_json_encode($payload_try),
            );

            foreach ($this->get_central_mark_consume_endpoints() as $endpoint) {
                $resp = wp_remote_post($endpoint, $args);
                if (is_wp_error($resp)) {
                    $last_error = $resp;
                    continue;
                }

                $code = (int) wp_remote_retrieve_response_code($resp);
                $body = (string) wp_remote_retrieve_body($resp);
                $data = json_decode($body, true);

                if ($code < 200 || $code >= 300) {
                    $msg = 'Central mark reconcile request failed (HTTP ' . $code . ')';
                    if (is_array($data) && isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
                        $msg = (string) $data['message'];
                    } elseif (is_array($data) && isset($data['data']['message']) && is_string($data['data']['message']) && $data['data']['message'] !== '') {
                        $msg = (string) $data['data']['message'];
                    }
                    $last_error = new WP_Error('smark_mark_reconcile_http', $msg, array('status' => $code, 'body' => $body));
                    continue;
                }

                if (!is_array($data) || !isset($data['remaining'])) {
                    $last_error = new WP_Error('smark_mark_reconcile_invalid', 'Invalid response from central server.', array('status' => 502, 'body' => $body));
                    continue;
                }

                $remaining = max(0, (int) $data['remaining']);
                if (isset($data['id'])) {
                    $id = (int) $data['id'];
                    if ($id > 0) {
                        $this->set_central_project_db_id($id);
                    }
                }

                $this->clear_mark_pending_total_for_project($project_db_id);
                $this->set_mark_cache_for_project($project_db_id, $remaining);
                $this->sync_local_mark_column($project_db_id, $remaining);
                return $remaining;
            }

            $last_data = ($last_error instanceof WP_Error) ? $last_error->get_error_data() : null;
            $last_status = (is_array($last_data) && isset($last_data['status'])) ? (int) $last_data['status'] : 0;
            if ((int) $payload_try['id'] > 0 && in_array($last_status, array(402, 404), true)) {
                continue;
            }
            break;
        }

        return $last_error instanceof WP_Error ? $last_error : null;
    }

    /**
     * @param string $website
     * @return int|WP_Error
     */
    private function fetch_mark_balance_from_central($website, $project_id = '') {
        $website = is_string($website) ? rtrim(trim($website), '/') : '';
        if ($website === '') {
            return new WP_Error('smark_mark_invalid_website', 'Invalid website.', array('status' => 400));
        }

        $project_id = is_string($project_id) ? trim($project_id) : '';
        $central_id = $this->get_central_project_db_id();

        $token = $this->get_central_sync_token();

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) constant('SMARK_VERSION') : '1.0.0') . ' (mark-balance)',
        );
        if ($token !== '') {
            $headers[self::CENTRAL_SYNC_TOKEN_HEADER] = $token;
        }

        $last_error = null;
        $payload = array(
            'website' => $website,
            'project_id' => $project_id,
            'id' => $central_id > 0 ? $central_id : 0,
        );
        $try_payloads = array($payload);
        if ($central_id > 0) {
            $fallback_payload = $payload;
            $fallback_payload['id'] = 0;
            $try_payloads[] = $fallback_payload;
        }

        foreach ($try_payloads as $payload_try) {
            $args = array(
                'timeout' => 8,
                'headers' => $headers,
                'body' => wp_json_encode($payload_try),
            );

            foreach ($this->get_central_mark_balance_endpoints() as $endpoint) {
                $resp = wp_remote_post($endpoint, $args);
                if (is_wp_error($resp)) {
                    $last_error = $resp;
                    continue;
                }

                $code = (int) wp_remote_retrieve_response_code($resp);
                $body = (string) wp_remote_retrieve_body($resp);
                $data = json_decode($body, true);

                if ($code < 200 || $code >= 300) {
                    $msg = 'Central mark balance request failed (HTTP ' . $code . ')';
                    if (is_array($data) && isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
                        $msg = $data['message'];
                    } elseif (is_array($data) && isset($data['data']['message']) && is_string($data['data']['message']) && $data['data']['message'] !== '') {
                        $msg = $data['data']['message'];
                    }
                    $last_error = new WP_Error('smark_mark_balance_http', $msg, array('status' => $code, 'body' => $body));
                    continue;
                }

                if (!is_array($data) || !isset($data['mark'])) {
                    $last_error = new WP_Error('smark_mark_balance_invalid', 'Invalid response from central server.', array('status' => 502, 'body' => $body));
                    continue;
                }

                $mark = (int) $data['mark'];
                if (isset($data['id'])) {
                    $id = (int) $data['id'];
                    if ($id > 0) {
                        $this->set_central_project_db_id($id);
                    }
                }
                return $mark;
            }

            $last_data = ($last_error instanceof WP_Error) ? $last_error->get_error_data() : null;
            $last_status = (is_array($last_data) && isset($last_data['status'])) ? (int) $last_data['status'] : 0;
            if ((int) $payload_try['id'] > 0 && in_array($last_status, array(402, 404), true)) {
                continue;
            }
            break;
        }

        return $last_error instanceof WP_Error ? $last_error : new WP_Error('smark_mark_balance_failed', 'Central mark balance request failed.', array('status' => 502));
    }

    private function maybe_sync_project_to_central($project_row) {
        $token = $this->get_central_sync_token();
        if (!is_array($project_row)) {
            return;
        }

        $payload = array(
            'website' => isset($project_row['website']) ? (string) $project_row['website'] : rtrim((string) home_url('/'), '/'),
            'project_id' => isset($project_row['project_id']) ? (string) $project_row['project_id'] : '',
            'project_name' => isset($project_row['project_name']) ? (string) $project_row['project_name'] : '',
            'brand_language' => isset($project_row['brand_language']) ? (string) $project_row['brand_language'] : ($this->is_persian_site() ? 'fa' : 'en'),
            'canva_template' => isset($project_row['canva_template']) ? (string) $project_row['canva_template'] : '',
            'wp_connected' => true,
            'plugin_version' => defined('SMARK_VERSION') ? (string) constant('SMARK_VERSION') : '',
            'source' => 'smark-plugin',
        );

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) constant('SMARK_VERSION') : '1.0.0') . ' (project-sync)',
        );
        if ($token !== '') {
            $headers[self::CENTRAL_SYNC_TOKEN_HEADER] = $token;
        }

        $response = wp_remote_post($this->get_central_endpoint(self::CENTRAL_SYNC_PATH), array(
            'timeout' => 15,
            'headers' => $headers,
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            if (class_exists('SMarkLogger')) {
                SMarkLogger::error('Central project sync failed', array('error' => $response->get_error_message()));
            }
            add_settings_error('smark_project_settings', 'central_sync_failed', $this->get_strings()['central_sync_failed'], 'error');
            return;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            if (class_exists('SMarkLogger')) {
                SMarkLogger::error('Central project sync failed', array('status' => $code, 'body' => wp_remote_retrieve_body($response)));
            }
            add_settings_error('smark_project_settings', 'central_sync_failed', $this->get_strings()['central_sync_failed'], 'error');
            return;
        }

        // Persist the central project_id locally so all future requests can reference the same project row.
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (is_array($data) && !empty($data['ok']) && isset($data['id'])) {
            $central_id = (int) $data['id'];
            if ($central_id > 0) {
                $this->set_central_project_db_id($central_id);
            }
        }
        if (is_array($data) && isset($data['project_id']) && is_string($data['project_id'])) {
            $central_project_id = trim($data['project_id']);
            $local_id = isset($project_row['id']) ? (int) $project_row['id'] : 0;
            if ($central_project_id !== '' && $local_id > 0) {
                global $wpdb;
                $projects_table = $this->resolve_projects_table();
                $projects_table_sql = self::escape_db_identifier($projects_table);
                if ($projects_table_sql !== '' && $this->table_has_column($projects_table, 'project_id')) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $wpdb->update($projects_table, array('project_id' => $central_project_id), array('id' => $local_id), array('%s'), array('%d'));
                }
            }
        }
    }
  
    public function render_page() {
        $strings = $this->get_strings();
        $current_lang = $this->get_panel_language();
        $rtl_class = $current_lang === 'fa' ? 'rtl' : '';
        $is_rtl = $rtl_class === 'rtl';

        // Handle Search Console OAuth callback (redirect_uri points to this page).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from Google.
        $sc_state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from Google.
        $sc_code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if ($sc_state !== '' && $sc_code !== '') {
            $this->handle_search_console_oauth_callback($sc_state, $sc_code);
            return;
        }

        if (!$this->projects_table_exists()) {
            ?>
            <div class="wrap smark-project-settings-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
                <div class="smark-page-header">
                    <h1><?php echo esc_html($strings['title']); ?></h1>
                    <p class="description"><?php echo esc_html($strings['subtitle']); ?></p>
                </div>
                <div class="smark-breadcrumb">
                    <div class="breadcrumb-left">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html($strings['breadcrumb_dashboard']); ?></a>
                        <span class="separator"><?php echo $is_rtl ? '‹' : '›'; ?></span>
                        <span class="current"><?php echo esc_html($strings['title']); ?></span>
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
                <div class="smark-project-settings-content">
                    <div class="smark-card smark-project-settings-card">
                        <div class="notice notice-error">
                            <p><?php echo esc_html($strings['projects_table_missing']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="smark-version-footer">
                    <div class="version-info">
                        <span class="version-label"><?php echo esc_html($is_rtl ? 'پلاگین اسمارک' : 'SMark Plugin'); ?></span>
                        <span class="version-separator">•</span>
                        <span class="version-number">v<?php echo esc_html(defined('SMARK_VERSION') ? (string) constant('SMARK_VERSION') : ''); ?></span>
                    </div>
                </div>
            </div>
            <?php
            return;
        }

        if (isset($_POST['smark_project_settings_submit'])) {
            check_admin_referer('smark_project_settings_save', 'smark_project_settings_nonce');
            if ($this->handle_submit()) {
                wp_safe_redirect(admin_url('admin.php?page=smark-dashboard'));
                exit;
            }
        }
 
        $project = $this->ensure_site_project_row();
        if (!is_array($project)) {
            $project = $this->get_current_project();
        }
        if (is_array($project)) {
            $this->ensure_project_id($project);
        }
        $project = $this->get_current_project();
        if (is_array($project) && $this->get_central_project_db_id() <= 0) {
            $this->maybe_sync_project_to_central($project);
            $project = $this->get_current_project();
        }
  
        $defaults = array(
            'id' => 0,
            'project_id' => '',
            'project_name' => function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '',
            'created_at' => '',
            'brand_language' => $this->is_persian_site() ? 'fa' : 'en',
            'canva_template' => '',
            'website' => function_exists('home_url') ? home_url() : '',
            'wp_connected' => 0,
            'search_console_tokens' => null,
        );
        $project = is_array($project) ? array_merge($defaults, $project) : $defaults;
  
        $search_console_connected = !empty($project['search_console_tokens']);

        $local_project_id = isset($project['id']) ? (int) $project['id'] : 0;
        $pending_total = $this->get_mark_pending_total_for_project($local_project_id);

        $mark_balance = null;
        if (!empty($project['website']) && is_string($project['website'])) {
            $website = (string) $project['website'];
            $pid = isset($project['project_id']) ? (string) $project['project_id'] : '';

            if ($pending_total > 0 && $local_project_id > 0) {
                $reconciled = $this->reconcile_pending_mark_with_central($local_project_id, $website, $pid, $pending_total);
                if (!is_wp_error($reconciled) && $reconciled !== null) {
                    $mark_balance = (int) $reconciled;
                    $pending_total = 0;
                }
            }

            if ($mark_balance === null) {
                $mark_balance = $this->fetch_mark_balance_from_central($website, $pid);
            }
        }

        $cached_mark = null;
        $cached_ts = 0;
        if ($local_project_id > 0) {
            $cache = get_option('smark_project_mark_cache', array());
            $cache = is_array($cache) ? $cache : array();
            $row = isset($cache[(string) $local_project_id]) ? $cache[(string) $local_project_id] : null;
            if (is_array($row) && isset($row['mark'])) {
                $cached_mark = (int) $row['mark'];
                $cached_ts = isset($row['ts']) ? (int) $row['ts'] : 0;
            } elseif (is_numeric($row)) {
                $cached_mark = (int) $row;
            }
        }

        $display_mark = null;
        if (!is_wp_error($mark_balance) && $mark_balance !== null) {
            // Display raw central balance to match the authoritative DB cell.
            $display_mark = max(0, (int) $mark_balance);
        } elseif ($cached_mark !== null) {
            $display_mark = max(0, (int) $cached_mark);
        }

        // Best-effort: keep local project mark column in sync (helps Content Management fallback when central is unreachable).
        try {
            if ($local_project_id > 0 && $display_mark !== null) {
                $this->set_mark_cache_for_project($local_project_id, (int) $display_mark);
                $this->sync_local_mark_column($local_project_id, (int) $display_mark);
            }
        } catch (Exception $e) {
            // Ignore sync failures.
        }

        $module_visibility = self::get_module_visibility();
        $dashboard_modules = array(
            'email' => $strings['module_email'],
            'seo' => $strings['module_seo'],
            'social' => $strings['module_social'],
        );
   
        ?>
        <div class="wrap smark-project-settings-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
            <div class="smark-page-header">
                <h1><?php echo esc_html($strings['title']); ?></h1>
                <?php if (!empty($strings['subtitle'])) : ?>
                    <p class="description"><?php echo esc_html($strings['subtitle']); ?></p>
                <?php endif; ?>
            </div>

            <div class="smark-breadcrumb">
                <div class="breadcrumb-left">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html($strings['breadcrumb_dashboard']); ?></a>
                    <span class="separator"><?php echo $is_rtl ? '‹' : '›'; ?></span>
                    <span class="current"><?php echo esc_html($strings['title']); ?></span>
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

            <div class="smark-project-settings-content">
                <?php settings_errors('smark_project_settings'); ?>

                <div class="smark-card smark-project-settings-card">
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=smark-project-settings')); ?>">
                        <?php wp_nonce_field('smark_project_settings_save', 'smark_project_settings_nonce'); ?>
                        <input type="hidden" name="project_db_id" value="<?php echo esc_attr((string) (int) $project['id']); ?>">

                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="smark_project_name"><?php echo esc_html($strings['project_name']); ?></label>
                                    </th>
                                    <td>
                                        <input id="smark_project_name" name="project_name" type="text" class="regular-text" value="<?php echo esc_attr((string) $project['project_name']); ?>" required>
                                        <p class="description"><?php echo esc_html($strings['project_name_help']); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html($strings['brand_language']); ?></th>
                                    <td>
                                        <select name="brand_language">
                                            <option value="en" <?php selected('en', (string) $project['brand_language']); ?>>English</option>
                                            <option value="fa" <?php selected('fa', (string) $project['brand_language']); ?>>Persian (Farsi)</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="smark_canva_template"><?php echo esc_html($strings['canva_template']); ?></label>
                                    </th>
                                    <td>
                                        <input id="smark_canva_template" name="canva_template" type="url" class="regular-text" value="<?php echo esc_attr((string) $project['canva_template']); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="smark_website"><?php echo esc_html($strings['website']); ?></label>
                                    </th>
                                    <td>
                                        <input id="smark_website" type="url" class="regular-text" value="<?php echo esc_attr((string) $project['website']); ?>" readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="smark_mark_credit"><?php echo esc_html($strings['mark_credit']); ?></label>
                                    </th>
                                    <td>
                                        <input id="smark_mark_credit" type="text" class="regular-text" value="<?php echo esc_attr($display_mark === null ? '—' : number_format_i18n((int) $display_mark)); ?>" readonly>

                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html($strings['sc_connection']); ?></th>
                                    <td>
                                        <div class="smark-sc-connection-wrap" aria-live="polite">
                                            <div class="smark-sc-connection">
                                                <button type="button" class="button smark-connect-sc">
                                                    <?php echo esc_html($strings['connect']); ?>
                                                </button>
                                                <span class="smark-sc-badge <?php echo $search_console_connected ? 'is-connected' : 'is-disconnected'; ?>">
                                                    <?php echo esc_html($search_console_connected ? $strings['connected'] : $strings['not_connected']); ?>
                                                </span>
                                            </div>


                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html($strings['modules']); ?></th>
                                    <td>
                                        <div class="smark-module-toggles" role="group" aria-label="<?php echo esc_attr($strings['modules']); ?>">
                                            <?php foreach ($dashboard_modules as $module_key => $module_label) : ?>
                                                <?php $input_id = 'smark_module_' . $module_key; ?>
                                                <label class="smark-module-toggle" for="<?php echo esc_attr($input_id); ?>">
                                                    <input
                                                        id="<?php echo esc_attr($input_id); ?>"
                                                        type="checkbox"
                                                        name="smark_enabled_modules[]"
                                                        value="<?php echo esc_attr($module_key); ?>"
                                                        <?php checked(!empty($module_visibility[$module_key])); ?>
                                                    >
                                                    <span class="smark-module-toggle__switch" aria-hidden="true">
                                                        <span class="smark-module-toggle__knob"></span>
                                                    </span>
                                                    <span class="smark-module-toggle__content">
                                                        <strong><?php echo esc_html($module_label); ?></strong>
                                                        <span class="smark-module-toggle__state">
                                                            <span class="smark-module-toggle__state-on"><?php echo esc_html($strings['module_on']); ?></span>
                                                            <span class="smark-module-toggle__state-off"><?php echo esc_html($strings['module_off']); ?></span>
                                                        </span>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <p class="description"><?php echo esc_html($strings['modules_help']); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <?php submit_button($strings['save'], 'primary', 'smark_project_settings_submit'); ?>
                    </form>
                </div>
            </div>

            <div class="smark-version-footer">
                <div class="version-info">
                    <span class="version-label"><?php echo esc_html($is_rtl ? 'پلاگین اسمارک' : 'SMark Plugin'); ?></span>
                    <span class="version-separator">•</span>
                    <span class="version-number">v<?php echo esc_html(defined('SMARK_VERSION') ? (string) constant('SMARK_VERSION') : ''); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_dashboard_project_settings_view() {
        check_ajax_referer('smark_project_settings_dashboard_ajax', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array(
                'message' => esc_html__('You do not have sufficient permissions to access this page.', 'smark'),
            ), 403);
        }

        ob_start();
        $this->render_page();
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
        ));
    }

    public function ajax_dashboard_project_settings_save() {
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array(
                'message' => esc_html__('You do not have sufficient permissions to perform this action.', 'smark'),
            ), 403);
        }

        $saved = $this->handle_submit();
        if (!$saved) {
            $errors = get_settings_errors('smark_project_settings');
            $message = !empty($errors[0]['message']) ? wp_strip_all_tags((string) $errors[0]['message']) : esc_html__('Unable to save project settings.', 'smark');
            wp_send_json_error(array(
                'message' => $message,
            ), 400);
        }

        $strings = $this->get_strings();
        wp_send_json_success(array(
            'message' => $strings['project_settings_saved'],
            'moduleVisibility' => self::get_module_visibility(),
        ));
    }
}
