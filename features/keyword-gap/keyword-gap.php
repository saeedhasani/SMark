<?php
/**
 * Keyword Gap Feature
 */

// Prevent direct access.
if (!defined('WPINC')) {
    die;
}

class SMarkKeywordGap {
    private const OPTION_CENTRAL_BASE_URL = 'smark_central_base_url';
    private const DEFAULT_CENTRAL_BASE_URL = 'https://saeedhasani.com';
    private const OPTION_MARK_CACHE = 'smark_project_mark_cache';
    private const OPTION_MARK_PENDING_TOTAL = 'smark_project_mark_pending_total';
    private const OPTION_INAPPROPRIATE_PREFIX = 'SMARK_keyword_gap_inappropriate_';

    /**
     * DB tables.
     *
     * @var string
     */
    private $competitors_table;
    private $keywords_table;
    private $project_keywords_table;
    private $projects_table;
    private $schema_error_message = '';
    private $storage_mode = null;

    /**
     * Schema version used for table tracking.
     *
     * @var string
     */
    private $schema_version = '1.1';

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;

        $this->competitors_table = $this->resolve_table_name($this->get_table_candidates('keyword_gap_competitors'));
        $this->keywords_table    = $this->resolve_table_name($this->get_table_candidates('keyword_gap_keywords'));
        $this->project_keywords_table = $this->resolve_keyword_research_table();
        $this->projects_table = $this->resolve_projects_table();

        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX endpoints.
        add_action('wp_ajax_SMARK_keyword_gap_save_language', array($this, 'ajax_save_language'));
        add_action('wp_ajax_SMARK_keyword_gap_get_competitors', array($this, 'ajax_get_competitors'));
        add_action('wp_ajax_SMARK_keyword_gap_add_competitors', array($this, 'ajax_add_competitors'));
        add_action('wp_ajax_SMARK_keyword_gap_upload_competitor_keywords', array($this, 'ajax_upload_competitor_keywords'));
        add_action('wp_ajax_SMARK_keyword_gap_get_competitor_keywords', array($this, 'ajax_get_competitor_keywords'));
        add_action('wp_ajax_SMARK_keyword_gap_semrush_finder', array($this, 'ajax_semrush_gap_finder'));
        add_action('wp_ajax_SMARK_keyword_gap_use_keyword', array($this, 'ajax_use_keyword'));
        add_action('wp_ajax_SMARK_keyword_gap_mark_inappropriate', array($this, 'ajax_mark_inappropriate'));
        add_action('wp_ajax_SMARK_keyword_gap_consume_mark', array($this, 'ajax_consume_mark'));

        $this->maybe_create_tables();
    }

    private function resolve_keyword_research_table() {
        global $wpdb;

        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
        if ($prefix === '') {
            return '';
        }

        // Keyword Research feature uses SMARK_keyword_research as canonical table name.
        $upper = $prefix . 'SMARK_keyword_research';
        $lower = $prefix . 'smark_keyword_research';

        if ($this->table_exists($upper)) {
            return $upper;
        }
        if ($this->table_exists($lower)) {
            return $lower;
        }

        // Prefer the canonical name even if it doesn't exist yet.
        return $upper;
    }

    private function resolve_projects_table() {
        global $wpdb;

        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
        if ($prefix === '') {
            return '';
        }

        $upper = $prefix . 'SMARK_projects';
        $lower = $prefix . 'smark_projects';

        $existing = array();
        if ($this->table_exists($upper)) {
            $existing[] = $upper;
        }
        if ($this->table_exists($lower)) {
            $existing[] = $lower;
        }

        if (count($existing) > 1) {
            // Prefer the table that has the website column (consistent with Keyword Research behavior).
            foreach ($existing as $table) {
                $table_sql = $this->escape_db_identifier($table);
                if ($table_sql === '') {
                    continue;
                }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $has_website = (string) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $wpdb->prepare(
                        'SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                        'website'
                    )
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                if ($has_website !== '') {
                    return $table;
                }
            }
        }

        if (!empty($existing)) {
            // Default preference: canonical table first.
            return $existing[0];
        }

        // Prefer the canonical name even if it doesn't exist yet.
        return $upper;
    }

    /**
     * Register hidden submenu page.
     */
    public function add_submenu_page() {
        add_submenu_page(
            null,
            __('Keyword Gap', 'smark'),
            __('Keyword Gap', 'smark'),
            'smark_access',
            'smark-keyword-gap',
            array($this, 'render_page')
        );
    }

    /**
     * Resolve panel language option key across features.
     *
     * @return string
     */
    private function get_panel_language() {
        $lang = get_option('smark_panel_language', '');
        if (!is_string($lang) || $lang === '') {
            $lang = get_option('SMARK_panel_language', 'en');
        }

        $lang = is_string($lang) ? trim($lang) : 'en';
        return ($lang === 'fa') ? 'fa' : 'en';
    }

    /**
     * Persist panel language to both option keys for compatibility.
     *
     * @param string $lang Language code.
     *
     * @return void
     */
    private function set_panel_language($lang) {
        $lang = ($lang === 'fa') ? 'fa' : 'en';
        update_option('smark_panel_language', $lang);
        update_option('SMARK_panel_language', $lang);
    }

    private function escape_db_identifier($identifier) {
        if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            return '';
        }

        return '`' . str_replace('`', '', esc_sql($identifier)) . '`';
    }

    private function resolve_table_name($candidates) {
        if (!is_array($candidates)) {
            return '';
        }

        foreach ($candidates as $candidate) {
            $candidate = is_string($candidate) ? $candidate : '';
            if ($candidate !== '' && $this->table_exists($candidate)) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = is_string($candidate) ? $candidate : '';
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function get_table_candidates($suffix) {
        global $wpdb;

        $suffix = is_string($suffix) ? trim($suffix) : '';
        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
        if ($suffix === '' || $prefix === '') {
            return array();
        }

        return array(
            $prefix . 'smark_' . $suffix,
            $prefix . 'SMARK_' . $suffix,
        );
    }

    private function maybe_create_tables() {
        $installed = get_option('SMARK_keyword_gap_schema_version', '');

        $has_competitors = $this->table_exists($this->competitors_table);
        $has_keywords     = $this->table_exists($this->keywords_table);

        if ($installed === $this->schema_version && $has_competitors && $has_keywords) {
            return;
        }

        $this->create_tables();

        // Only mark schema as installed when tables exist.
        if ($this->table_exists($this->competitors_table) && $this->table_exists($this->keywords_table)) {
            update_option('SMARK_keyword_gap_schema_version', $this->schema_version);
        }
    }

    private function is_tables_available() {
        if ($this->storage_mode === 'tables') {
            return true;
        }
        if ($this->storage_mode === 'options') {
            return false;
        }

        $saved_mode = get_option('SMARK_keyword_gap_storage_mode', '');
        $saved_mode = is_string($saved_mode) ? trim($saved_mode) : '';
        if ($saved_mode === 'options') {
            $this->storage_mode = 'options';
            return false;
        }

        if ($saved_mode === 'tables') {
            $ok = $this->table_exists($this->competitors_table) && $this->table_exists($this->keywords_table);
            $this->storage_mode = $ok ? 'tables' : 'options';
            if (!$ok) {
                update_option('SMARK_keyword_gap_storage_mode', 'options', false);
            }
            return $ok;
        }

        $this->maybe_create_tables();

        $ok = $this->table_exists($this->competitors_table) && $this->table_exists($this->keywords_table);
        $this->storage_mode = $ok ? 'tables' : 'options';
        update_option('SMARK_keyword_gap_storage_mode', $this->storage_mode, false);
        return $ok;
    }

    private function get_option_competitors_key() {
        return 'SMARK_keyword_gap_competitors';
    }

    private function get_option_keywords_key($competitor_id) {
        return 'SMARK_keyword_gap_keywords_' . (int) $competitor_id;
    }

    private function get_option_competitors() {
        $data = get_option($this->get_option_competitors_key(), array());
        return is_array($data) ? $data : array();
    }

    private function set_option_competitors($competitors) {
        update_option($this->get_option_competitors_key(), is_array($competitors) ? $competitors : array(), false);
    }

    private function next_option_competitor_id() {
        $key = 'SMARK_keyword_gap_next_id';
        $next = (int) get_option($key, 1);
        if ($next < 1) {
            $next = 1;
        }
        update_option($key, $next + 1, false);
        return $next;
    }

    private function get_option_keywords($competitor_id) {
        $data = get_option($this->get_option_keywords_key($competitor_id), array());
        $data = is_array($data) ? $data : array();

        // Back-compat: older versions stored keywords as plain strings.
        $normalized = array();
        foreach ($data as $row) {
            if (is_string($row)) {
                $kw = $this->normalize_keyword($row);
                if ($kw !== '') {
                    $normalized[] = array(
                        'keyword' => $kw,
                        'intent' => null,
                        'volume' => null,
                        'keyword_difficulty' => null,
                        'cpc_usd' => null,
                        'serp_features' => null,
                    );
                }
                continue;
            }

            if (!is_array($row)) {
                continue;
            }

            $kw = isset($row['keyword']) ? $this->normalize_keyword((string) $row['keyword']) : '';
            if ($kw === '') {
                continue;
            }

            $normalized[] = array(
                'keyword' => $kw,
                'intent' => isset($row['intent']) ? (string) $row['intent'] : null,
                'volume' => isset($row['volume']) ? (int) $row['volume'] : null,
                'keyword_difficulty' => isset($row['keyword_difficulty']) ? (float) $row['keyword_difficulty'] : null,
                'cpc_usd' => isset($row['cpc_usd']) ? (float) $row['cpc_usd'] : null,
                'serp_features' => isset($row['serp_features']) ? (string) $row['serp_features'] : null,
            );
        }

        return $normalized;
    }

    private function set_option_keywords($competitor_id, $keywords) {
        update_option($this->get_option_keywords_key($competitor_id), is_array($keywords) ? $keywords : array(), false);
    }

    private function get_option_inappropriate_key($competitor_id) {
        return self::OPTION_INAPPROPRIATE_PREFIX . (int) $competitor_id;
    }

    private function get_option_inappropriate_map($competitor_id) {
        $competitor_id = (int) $competitor_id;
        if ($competitor_id <= 0) {
            return array();
        }

        $raw = get_option($this->get_option_inappropriate_key($competitor_id), array());
        $raw = is_array($raw) ? $raw : array();
        $map = array();
        foreach ($raw as $k) {
            $key = $this->normalize_keyword_key(is_string($k) ? $k : '');
            if ($key !== '') {
                $map[$key] = true;
            }
        }
        return $map;
    }

    private function add_inappropriate_keyword($competitor_id, $keyword) {
        $competitor_id = (int) $competitor_id;
        $key = $this->normalize_keyword_key($keyword);
        if ($competitor_id <= 0 || $key === '') {
            return false;
        }

        $all = get_option($this->get_option_inappropriate_key($competitor_id), array());
        $all = is_array($all) ? $all : array();
        $all[] = $key;
        $all = array_values(array_unique(array_filter($all, function ($v) {
            return is_string($v) && $v !== '';
        })));
        update_option($this->get_option_inappropriate_key($competitor_id), $all, false);
        return true;
    }

    private function table_exists($table) {
        global $wpdb;

        $table = is_string($table) ? $table : '';
        if ($table === '') {
            return false;
        }

        // Try information_schema first (fast + exact), but some hosts restrict it.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $info = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
                $table
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($info !== null && $info !== false && is_numeric($info)) {
            return ((int) $info) > 0;
        }

        // Fallback: SHOW TABLES LIKE (escape LIKE wildcards).
        $pattern = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), $table);

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $pattern));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return !empty($exists);
    }

    private function create_tables() {
        global $wpdb;

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset_collate = $wpdb->get_charset_collate();
        $this->schema_error_message = '';

        $sql_competitors = "CREATE TABLE {$this->competitors_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            domain VARCHAR(255) NOT NULL,
            keywords_updated_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY domain (domain)
        ) {$charset_collate};";

        $sql_keywords = "CREATE TABLE {$this->keywords_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            competitor_id BIGINT(20) UNSIGNED NOT NULL,
            keyword VARCHAR(255) NOT NULL,
            intent VARCHAR(100) DEFAULT NULL,
            volume INT(11) DEFAULT NULL,
            keyword_difficulty DECIMAL(6,2) DEFAULT NULL,
            cpc_usd DECIMAL(10,2) DEFAULT NULL,
            serp_features TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY competitor_id (competitor_id),
            KEY competitor_keyword (competitor_id, keyword),
            KEY keyword_idx (keyword)
        ) {$charset_collate};";

        dbDelta($sql_competitors);
        dbDelta($sql_keywords);

        // Fallback for environments where dbDelta fails silently.
        if (!$this->table_exists($this->competitors_table)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $res = $wpdb->query(str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $sql_competitors)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL statement; no user input and no placeholders.
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            if ($res === false && $this->schema_error_message === '') {
                $this->schema_error_message = is_string($wpdb->last_error) ? trim($wpdb->last_error) : '';
            }
        }
        if (!$this->table_exists($this->keywords_table)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            $res = $wpdb->query(str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $sql_keywords)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DDL statement; no user input and no placeholders.
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            if ($res === false && $this->schema_error_message === '') {
                $this->schema_error_message = is_string($wpdb->last_error) ? trim($wpdb->last_error) : '';
            }
        }

        // Re-resolve table names (case/collation differences across environments).
        $this->competitors_table = $this->resolve_table_name($this->get_table_candidates('keyword_gap_competitors'));
        $this->keywords_table    = $this->resolve_table_name($this->get_table_candidates('keyword_gap_keywords'));

        if (!$this->table_exists($this->competitors_table) || !$this->table_exists($this->keywords_table)) {
            $this->schema_error_message = is_string($wpdb->last_error) ? trim($wpdb->last_error) : '';
            if ($this->schema_error_message === '') {
                $this->schema_error_message = 'No MySQL error message returned. Check DB user privileges for CREATE/ALTER.';
            }
        }
    }

    private function get_schema_error_message() {
        return is_string($this->schema_error_message) ? trim($this->schema_error_message) : '';
    }

    private function normalize_domain($value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        $parts = wp_parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $host = strtolower((string) $parts['host']);
        $host = preg_replace('/^www\\./i', '', $host);
        $host = trim($host, ". \t\n\r\0\x0B");

        return $host === '' ? '' : $host;
    }

    private function get_site_domain() {
        $home = function_exists('home_url') ? (string) home_url('/') : '';
        $home = rtrim($home, '/');
        if ($home === '') {
            return '';
        }

        return $this->normalize_domain($home);
    }

    private function get_competitor_domain_by_id($competitor_id) {
        $competitor_id = (int) $competitor_id;
        if ($competitor_id <= 0) {
            return '';
        }

        if ($this->is_tables_available()) {
            global $wpdb;
            $competitors_table_sql = $this->escape_db_identifier($this->competitors_table);
            if ($competitors_table_sql === '') {
                return '';
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $domain = $wpdb->get_var($wpdb->prepare('SELECT domain FROM ' . $competitors_table_sql . ' WHERE id = %d LIMIT 1', $competitor_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            return is_string($domain) ? (string) $domain : '';
        }

        foreach ($this->get_option_competitors() as $c) {
            if (isset($c['id']) && (int) $c['id'] === $competitor_id) {
                return isset($c['domain']) ? (string) $c['domain'] : '';
            }
        }

        return '';
    }

    private function get_current_project_id() {
        $project_id = (int) get_option('smark_current_project_db_id', 0);
        if ($project_id > 0) {
            // Validate the saved project id against the resolved projects table (handles legacy/migrated tables).
            if ($this->projects_table && $this->table_exists($this->projects_table)) {
                global $wpdb;
                $projects_table_sql = $this->escape_db_identifier($this->projects_table);
                if ($projects_table_sql !== '') {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $exists = (int) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                        $wpdb->prepare(
                            'SELECT COUNT(*) FROM ' . $projects_table_sql . ' WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                            $project_id
                        )
                    );
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    if ($exists > 0) {
                        return $project_id;
                    }
                }
            } else {
                return $project_id;
            }
        }

        if (!$this->projects_table || !$this->table_exists($this->projects_table)) {
            return 0;
        }

        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            return 0;
        }

        $site_url = rtrim((string) home_url('/'), '/');
        if ($site_url !== '') {
            // Prefer resolving project by website (consistent with dashboard behavior).
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $has_website = (string) $wpdb->get_var(
                $wpdb->prepare(
                    'SHOW COLUMNS FROM ' . $projects_table_sql . ' LIKE %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    'website'
                )
            );
            if ($has_website !== '') {
                $with_slash = $site_url . '/';
                $found = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        'SELECT id FROM ' . $projects_table_sql . ' WHERE website = %s OR website = %s ORDER BY id DESC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                        $site_url,
                        $with_slash
                    )
                );
                if ($found > 0) {
                    update_option('smark_current_project_db_id', $found, false);
                    return $found;
                }
            }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $found = (int) $wpdb->get_var('SELECT id FROM ' . $projects_table_sql . ' ORDER BY id DESC LIMIT 1'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($found > 0) {
            update_option('smark_current_project_db_id', $found, false);
            return $found;
        }

        return 0;
    }

    private function get_project_name_by_id($project_id) {
        $project_id = (int) $project_id;
        if ($project_id <= 0) {
            return '';
        }

        if (!$this->projects_table || !$this->table_exists($this->projects_table)) {
            return '';
        }

        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            return '';
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $name = $wpdb->get_var($wpdb->prepare('SELECT project_name FROM ' . $projects_table_sql . ' WHERE id = %d', $project_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return is_string($name) ? (string) $name : '';
    }

    private function get_used_keywords_map($project_id, $keywords) {
        $project_id = (int) $project_id;
        if ($project_id <= 0 || !is_array($keywords) || empty($keywords)) {
            return array();
        }

        if (!$this->project_keywords_table || !$this->table_exists($this->project_keywords_table)) {
            return array();
        }

        $lookups = array();
        foreach ($keywords as $kw) {
            $kw = $this->normalize_keyword((string) $kw);
            if ($kw !== '') {
                $lookups[] = strtolower($kw);
            }
        }
        $lookups = array_values(array_unique($lookups));
        if (empty($lookups)) {
            return array();
        }

        $existing = $this->get_project_keywords_normalized_set($project_id);
        if (empty($existing)) {
            return array();
        }

        $map = array();
        foreach ($lookups as $lookup) {
            if (isset($existing[$lookup])) {
                $map[$lookup] = true;
            }
        }

        return $map;
    }

    private function get_used_keywords_global_map($keywords) {
        if (!is_array($keywords) || empty($keywords)) {
            return array();
        }

        $lookups = array();
        foreach ($keywords as $kw) {
            $kw = $this->normalize_keyword((string) $kw);
            if ($kw !== '') {
                $lookups[] = strtolower($kw);
            }
        }
        $lookups = array_values(array_unique($lookups));
        if (empty($lookups)) {
            return array();
        }

        global $wpdb;
        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
        $tables = array_unique(array_filter(array(
            $this->project_keywords_table,
            $prefix !== '' ? ($prefix . 'SMARK_keyword_research') : '',
            $prefix !== '' ? ($prefix . 'smark_keyword_research') : '',
        )));

        $map = array();
        $placeholders = implode(',', array_fill(0, count($lookups), '%s'));

        foreach ($tables as $table) {
            if (!$this->table_exists($table)) {
                continue;
            }

            $table_sql = $this->escape_db_identifier($table);
            if ($table_sql === '') {
                continue;
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->prepare(
                    'SELECT keyword FROM ' . $table_sql . ' WHERE LOWER(TRIM(keyword)) IN (' . $placeholders . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    ...$lookups
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            foreach ((array) $rows as $row) {
                $norm = strtolower($this->normalize_keyword((string) $row));
                if ($norm !== '') {
                    $map[$norm] = true;
                }
            }
        }

        return $map;
    }

    private function get_used_keywords_bank_map($keywords) {
        if (!is_array($keywords) || empty($keywords)) {
            return array();
        }

        $lookups = array();
        foreach ($keywords as $kw) {
            $kw = $this->normalize_keyword((string) $kw);
            if ($kw !== '') {
                $lookups[] = strtolower($kw);
            }
        }
        $lookups = array_values(array_unique($lookups));
        if (empty($lookups)) {
            return array();
        }

        global $wpdb;
        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
        $tables = array_unique(array_filter(array(
            $prefix !== '' ? ($prefix . 'SMARK_keyword_bank') : '',
            $prefix !== '' ? ($prefix . 'smark_keyword_bank') : '',
        )));

        $map = array();
        $placeholders = implode(',', array_fill(0, count($lookups), '%s'));

        foreach ($tables as $table) {
            if (!$this->table_exists($table)) {
                continue;
            }

            $table_sql = $this->escape_db_identifier($table);
            if ($table_sql === '') {
                continue;
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->prepare(
                    // Normalize NBSP (160) and NNBSP (8239) to regular spaces before trimming.
                    'SELECT keyword FROM ' . $table_sql . ' WHERE LOWER(TRIM(REPLACE(REPLACE(keyword, CHAR(160), \' \'), CHAR(8239), \' \'))) IN (' . $placeholders . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    ...$lookups
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            foreach ((array) $rows as $row) {
                $norm = strtolower($this->normalize_keyword((string) $row));
                if ($norm !== '') {
                    $map[$norm] = true;
                }
            }
        }

        return $map;
    }

    private function get_project_keywords_normalized_set($project_id) {
        static $cache = array();

        $project_id = (int) $project_id;
        if ($project_id <= 0) {
            return array();
        }

        if (isset($cache[$project_id]) && is_array($cache[$project_id])) {
            return $cache[$project_id];
        }

        global $wpdb;
        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';

        $tables = array_unique(array_filter(array(
            $this->project_keywords_table,
            $prefix !== '' ? ($prefix . 'SMARK_keyword_research') : '',
            $prefix !== '' ? ($prefix . 'smark_keyword_research') : '',
        )));

        if (empty($tables)) {
            $cache[$project_id] = array();
            return $cache[$project_id];
        }

        $set = array();
        foreach ($tables as $table) {
            if (!$this->table_exists($table)) {
                continue;
            }

            $table_sql = $this->escape_db_identifier($table);
            if ($table_sql === '') {
                continue;
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->prepare(
                    'SELECT keyword FROM ' . $table_sql . ' WHERE project_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $project_id
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            foreach ((array) $rows as $row) {
                $norm = strtolower($this->normalize_keyword((string) $row));
                if ($norm !== '') {
                    $set[$norm] = true;
                }
            }
        }

        $cache[$project_id] = $set;
        return $cache[$project_id];
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

        // Keep this map aligned with the UI (keyword-gap.js).
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
            '52' => 'AI overview',
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

    private function get_mark_cache_all() {
        $all = get_option(self::OPTION_MARK_CACHE, array());
        return is_array($all) ? $all : array();
    }

    private function set_mark_cache_all($all) {
        update_option(self::OPTION_MARK_CACHE, is_array($all) ? $all : array(), false);
    }

    private function get_cached_mark_for_project($project_db_id) {
        $project_db_id = (int) $project_db_id;
        if ($project_db_id <= 0) {
            return null;
        }

        $all = $this->get_mark_cache_all();
        $key = (string) $project_db_id;
        if (!isset($all[$key])) {
            return null;
        }

        $row = $all[$key];
        if (is_array($row) && isset($row['mark'])) {
            return (int) $row['mark'];
        }
        if (is_numeric($row)) {
            return (int) $row;
        }

        return null;
    }

    private function set_cached_mark_for_project($project_db_id, $mark) {
        $project_db_id = (int) $project_db_id;
        if ($project_db_id <= 0) {
            return;
        }

        $mark = max(0, (int) $mark);
        $all = $this->get_mark_cache_all();
        $all[(string) $project_db_id] = array(
            'mark' => $mark,
            'ts' => time(),
        );
        $this->set_mark_cache_all($all);
    }

    private function get_pending_total_all() {
        $all = get_option(self::OPTION_MARK_PENDING_TOTAL, array());
        return is_array($all) ? $all : array();
    }

    private function set_pending_total_all($all) {
        update_option(self::OPTION_MARK_PENDING_TOTAL, is_array($all) ? $all : array(), false);
    }

    private function get_pending_total_for_project($project_db_id) {
        $project_db_id = (int) $project_db_id;
        if ($project_db_id <= 0) {
            return 0;
        }

        $all = $this->get_pending_total_all();
        $key = (string) $project_db_id;
        return isset($all[$key]) ? max(0, (int) $all[$key]) : 0;
    }

    private function add_pending_total_for_project($project_db_id, $amount) {
        $project_db_id = (int) $project_db_id;
        $amount = (int) $amount;
        if ($project_db_id <= 0 || $amount <= 0) {
            return 0;
        }

        $all = $this->get_pending_total_all();
        $key = (string) $project_db_id;
        $current = isset($all[$key]) ? max(0, (int) $all[$key]) : 0;
        $next = $current + $amount;
        $all[$key] = $next;
        $this->set_pending_total_all($all);
        return $next;
    }

    private function clear_pending_total_for_project($project_db_id) {
        $project_db_id = (int) $project_db_id;
        if ($project_db_id <= 0) {
            return;
        }

        $all = $this->get_pending_total_all();
        $key = (string) $project_db_id;
        if (isset($all[$key])) {
            unset($all[$key]);
            $this->set_pending_total_all($all);
        }
    }

    private function table_has_column($table, $column) {
        $table = is_string($table) ? trim($table) : '';
        $column = is_string($column) ? trim($column) : '';
        if ($table === '' || $column === '') {
            return false;
        }

        $table_sql = $this->escape_db_identifier($table);
        if ($table_sql === '') {
            return false;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $found = (string) $wpdb->get_var($wpdb->prepare(
            'SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $column
        ));
        return $found !== '';
    }

    private function seed_cached_mark_from_db_if_available($project_db_id) {
        $project_db_id = (int) $project_db_id;
        if ($project_db_id <= 0 || !$this->projects_table || !$this->table_exists($this->projects_table)) {
            return;
        }

        if (!$this->table_has_column($this->projects_table, 'mark')) {
            return;
        }

        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $mark = (int) $wpdb->get_var($wpdb->prepare("SELECT mark FROM {$projects_table_sql} WHERE id = %d", $project_db_id));
        $this->set_cached_mark_for_project($project_db_id, max(0, $mark));
    }

    private function reserve_project_mark_credit_local($project_db_id, $amount) {
        $project_db_id = (int) $project_db_id;
        $amount = (int) $amount;
        if ($project_db_id <= 0 || $amount <= 0 || $amount > 10) {
            return new WP_Error('smark_mark_invalid_amount', 'Invalid amount.', array('status' => 400));
        }

        if (!$this->projects_table || !$this->table_exists($this->projects_table)) {
            return new WP_Error('smark_table_missing', 'Projects table not found.', array('status' => 500));
        }

        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            return new WP_Error('smark_table_missing', 'Projects table not found.', array('status' => 500));
        }

        $this->seed_cached_mark_from_db_if_available($project_db_id);

        if (!$this->table_has_column($this->projects_table, 'mark')) {
            $cached = $this->get_cached_mark_for_project($project_db_id);
            $cached = $cached === null ? 0 : max(0, (int) $cached);
            if ($cached < $amount) {
                return new WP_Error('smark_mark_insufficient', 'Insufficient mark credits', array('status' => 402));
            }
            $remaining = max(0, $cached - $amount);
            $this->set_cached_mark_for_project($project_db_id, $remaining);
            return $remaining;
        }

        // Atomic decrement when enough credits exist.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier(); placeholders not supported for identifiers.
        $updated = $wpdb->query($wpdb->prepare("UPDATE {$projects_table_sql} SET mark = GREATEST(mark - %d, 0) WHERE id = %d AND mark >= %d", $amount, $project_db_id, $amount));
        if ($updated === false) {
            $err = isset($wpdb->last_error) ? (string) $wpdb->last_error : '';
            return new WP_Error('smark_db_error', $err !== '' ? $err : 'Database error.', array('status' => 500));
        }
        if ((int) $updated !== 1) {
            return new WP_Error('smark_mark_insufficient', 'Insufficient mark credits', array('status' => 402));
        }

        // Remaining after reservation (best-effort).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
        $remaining = (int) $wpdb->get_var($wpdb->prepare("SELECT mark FROM {$projects_table_sql} WHERE id = %d", $project_db_id));
        $remaining = max(0, $remaining);
        $this->set_cached_mark_for_project($project_db_id, $remaining);
        return $remaining;
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

    /**
     * @return string[]
     */
    private function get_central_semrush_proxy_endpoints() {
        $path = '/wp-json/smark-core/v1/tools/semrush/proxy';
        $endpoints = array();
        foreach ($this->get_central_endpoint_bases() as $base) {
            $endpoints[] = $base . $path;
        }

        $filtered = apply_filters('SMARK_semrush_proxy_endpoints', $endpoints);
        if (is_array($filtered) && !empty($filtered)) {
            $endpoints = $filtered;
        }

        return array_values(array_unique($endpoints));
    }

    /**
     * @return string[]
     */
    private function get_central_mark_consume_endpoints() {
        $path = '/wp-json/smark-core/v1/projects/mark/consume';
        $endpoints = array();
        foreach ($this->get_central_endpoint_bases() as $base) {
            $endpoints[] = $base . $path;
        }

        $filtered = apply_filters('SMARK_mark_consume_endpoints', $endpoints);
        if (is_array($filtered) && !empty($filtered)) {
            $endpoints = $filtered;
        }

        return array_values(array_unique($endpoints));
    }

    /**
     * Consume Mark credits via central SMark Core (preferred path).
     *
     * @param int $amount
     * @return int|WP_Error Remaining credits on success.
     */
    private function consume_mark_via_central($amount) {
        $amount = (int) $amount;
        if ($amount <= 0 || $amount > 10) {
            return new WP_Error('smark_mark_invalid_amount', 'Invalid amount.', array('status' => 400));
        }

        $sync_token = $this->get_central_sync_token();
        $sync_token = is_string($sync_token) ? trim($sync_token) : '';

        $project_db_id = $this->get_current_project_id();
        if ($project_db_id <= 0) {
            return new WP_Error('smark_mark_invalid_project', 'Invalid project.', array('status' => 400));
        }

        $website = rtrim((string) home_url('/'), '/');
        $project_public_id = '';
        if ($project_db_id > 0 && $this->projects_table && $this->table_exists($this->projects_table)) {
            global $wpdb;
            $projects_table_sql = $this->escape_db_identifier($this->projects_table);
            if ($projects_table_sql !== '') {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $row = $wpdb->get_row($wpdb->prepare('SELECT website, project_id FROM ' . $projects_table_sql . ' WHERE id = %d', (int) $project_db_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                if (is_array($row)) {
                    if (isset($row['website']) && is_string($row['website']) && trim($row['website']) !== '') {
                        $website = rtrim(trim((string) $row['website']), '/');
                    }
                    if (isset($row['project_id']) && is_string($row['project_id'])) {
                        $project_public_id = trim((string) $row['project_id']);
                    }
                }
            }
        }
        $central_db_id = (int) get_option('smark_central_project_db_id', 0);
        $pending_total = $this->get_pending_total_for_project($project_db_id);
        $central_amount = $amount + max(0, (int) $pending_total);

        $args = array(
            'timeout' => 25,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (mark-consume)',
            ),
            'body' => wp_json_encode(array(
                'amount' => $central_amount,
                'website' => $website,
                'project_id' => $project_public_id,
                'id' => $central_db_id > 0 ? $central_db_id : 0,
            )),
        );
        if ($sync_token !== '') {
            $args['headers']['x-smark-sync-token'] = $sync_token;
        }

        $last_error = null;
        $central_unreachable = false;
        $central_forbidden = false;
        foreach ($this->get_central_mark_consume_endpoints() as $endpoint) {
            $resp = wp_remote_post($endpoint, $args);
            if (is_wp_error($resp)) {
                $central_unreachable = true;
                $last_error = $resp;
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($resp);
            $body = (string) wp_remote_retrieve_body($resp);
            $body = trim($body);

            if ($code < 200 || $code >= 300) {
                $msg = 'Central mark consume request failed (HTTP ' . $code . ')';
                $parsed = json_decode($body, true);
                if (is_array($parsed) && isset($parsed['message']) && is_string($parsed['message']) && $parsed['message'] !== '') {
                    $msg = $parsed['message'] . ' (HTTP ' . $code . ')';
                } elseif (is_array($parsed) && isset($parsed['data']['message']) && is_string($parsed['data']['message']) && $parsed['data']['message'] !== '') {
                    $msg = $parsed['data']['message'] . ' (HTTP ' . $code . ')';
                }

                $err_code = ($code === 402) ? 'smark_mark_insufficient' : 'smark_mark_consume_http';
                $last_error = new WP_Error($err_code, $msg, array('status' => $code, 'body' => $body));
                if ($code === 401 || $code === 403) {
                    $central_forbidden = true;
                }
                continue;
            }

            if ($body === '') {
                $last_error = new WP_Error('smark_mark_consume_empty', 'Empty response from central mark consume.', array('status' => 502));
                continue;
            }

            $parsed = json_decode($body, true);
            if (is_array($parsed)) {
                $remaining = null;
                if (isset($parsed['remaining'])) {
                    $remaining = (int) $parsed['remaining'];
                } elseif (isset($parsed['data']['remaining'])) {
                    $remaining = (int) $parsed['data']['remaining'];
                }

                if ($remaining !== null) {
                    $remaining = max(0, (int) $remaining);
                    $this->set_cached_mark_for_project($project_db_id, $remaining);
                    // Best-effort: keep local DB mark in sync (only if column exists; no schema changes here).
                    try {
                        if ($this->projects_table && $this->table_exists($this->projects_table) && $this->table_has_column($this->projects_table, 'mark')) {
                            global $wpdb;
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                            $wpdb->update($this->projects_table, array('mark' => $remaining), array('id' => $project_db_id), array('%d'), array('%d'));
                        }
                    } catch (Exception $e) {}
                    $this->clear_pending_total_for_project($project_db_id);
                    return $remaining;
                }

                if (isset($parsed['message']) && is_string($parsed['message']) && $parsed['message'] !== '') {
                    $last_error = new WP_Error('smark_mark_consume_invalid', (string) $parsed['message'], array('status' => 502, 'body' => $body));
                    continue;
                }
            }

            $last_error = new WP_Error('smark_mark_consume_invalid', 'Invalid response from central mark consume.', array('status' => 502, 'body' => $body));
        }

        $data = ($last_error instanceof WP_Error) ? $last_error->get_error_data() : null;
        $status = (is_array($data) && isset($data['status'])) ? (int) $data['status'] : 0;

        // If central is unreachable or token-protected calls are rejected, fall back to local project credits.
        if ($central_unreachable || $central_forbidden || $status === 401 || $status === 403) {
            $local = $this->reserve_project_mark_credit_local($project_db_id, $amount);
            if (is_wp_error($local)) {
                return $local;
            }
            $this->add_pending_total_for_project($project_db_id, $amount);
            return (int) $local;
        }

        return $last_error instanceof WP_Error ? $last_error : new WP_Error('smark_mark_consume_failed', 'Central mark consume request failed.', array('status' => 502));
    }

    /**
     * Fetch Semrush rows via central SMark Core proxy (preferred path).
     *
     * @param array<string,mixed> $params Semrush params (without API key).
     * @return array<int,array<string,mixed>>|WP_Error
     */
    private function semrush_fetch_rows_via_central($params) {
        $params = is_array($params) ? $params : array();
        $sync_token = $this->get_central_sync_token();

        $args = array(
            'timeout' => 25,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (semrush-proxy)',
            ),
            'body' => wp_json_encode(array(
                'params' => $params,
                'website' => rtrim((string) home_url('/'), '/'),
            )),
        );
        if (is_string($sync_token) && trim($sync_token) !== '') {
            $args['headers']['x-smark-sync-token'] = trim($sync_token);
        }

        $last_error = null;
        foreach ($this->get_central_semrush_proxy_endpoints() as $endpoint) {
            $resp = wp_remote_post($endpoint, $args);
            if (is_wp_error($resp)) {
                $last_error = $resp;
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($resp);
            $body = (string) wp_remote_retrieve_body($resp);
            $body = trim($body);

            if ($code < 200 || $code >= 300) {
                $msg = 'Central Semrush request failed (HTTP ' . $code . ')';
                $parsed = json_decode($body, true);
                if (is_array($parsed) && isset($parsed['message']) && is_string($parsed['message']) && $parsed['message'] !== '') {
                    $msg = $parsed['message'] . ' (HTTP ' . $code . ')';
                } elseif (is_array($parsed) && isset($parsed['data']['message']) && is_string($parsed['data']['message']) && $parsed['data']['message'] !== '') {
                    $msg = $parsed['data']['message'] . ' (HTTP ' . $code . ')';
                }
                $last_error = new WP_Error('smark_semrush_proxy_http', $msg, array('status' => $code, 'body' => $body));
                continue;
            }

            if ($body === '') {
                $last_error = new WP_Error('smark_semrush_proxy_empty', 'Empty response from central Semrush proxy.');
                continue;
            }

            $parsed = json_decode($body, true);
            if (is_array($parsed)) {
                $rows = null;
                if (isset($parsed['rows']) && is_array($parsed['rows'])) {
                    $rows = $parsed['rows'];
                } elseif (isset($parsed['data']['rows']) && is_array($parsed['data']['rows'])) {
                    $rows = $parsed['data']['rows'];
                }

                if (is_array($rows)) {
                    return $rows;
                }

                $csv = '';
                if (isset($parsed['csv']) && is_string($parsed['csv'])) {
                    $csv = (string) $parsed['csv'];
                } elseif (isset($parsed['data']['csv']) && is_string($parsed['data']['csv'])) {
                    $csv = (string) $parsed['data']['csv'];
                } elseif (isset($parsed['body']) && is_string($parsed['body'])) {
                    $csv = (string) $parsed['body'];
                } elseif (isset($parsed['data']['body']) && is_string($parsed['data']['body'])) {
                    $csv = (string) $parsed['data']['body'];
                }

                if ($csv !== '') {
                    return $this->parse_semrush_csv_content($csv);
                }
            }

            // Fallback: treat response as Semrush CSV body.
            return $this->parse_semrush_csv_content($body);
        }

        return $last_error instanceof WP_Error ? $last_error : new WP_Error('smark_semrush_proxy_failed', 'Central Semrush request failed.');
    }

    private function get_encryption_key_compat() {
        $key = '';
        if (defined('AUTH_KEY')) {
            $key .= (string) constant('AUTH_KEY');
        }
        if (defined('AUTH_SALT')) {
            $key .= (string) constant('AUTH_SALT');
        }
        if (defined('SECURE_AUTH_KEY')) {
            $key .= (string) constant('SECURE_AUTH_KEY');
        }
        if (empty($key) && function_exists('wp_salt')) {
            $key = wp_salt('auth');
        }

        return substr(hash('sha256', (string) $key), 0, 32);
    }

    private function decrypt_secret_compat($encrypted) {
        $encrypted = is_string($encrypted) ? trim($encrypted) : '';
        if ($encrypted === '') {
            return '';
        }

        try {
            $data = base64_decode($encrypted);

            if (function_exists('openssl_decrypt') && is_string($data) && strlen($data) > 16) {
                $iv_length = openssl_cipher_iv_length('AES-256-CBC');
                if (is_int($iv_length) && $iv_length > 0 && strlen($data) > $iv_length) {
                    $iv = substr($data, 0, $iv_length);
                    $encrypted_data = substr($data, $iv_length);
                    $key = $this->get_encryption_key_compat();
                    $decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', $key, 0, $iv);
                    if ($decrypted !== false && is_string($decrypted) && $decrypted !== '') {
                        return $decrypted;
                    }
                }
            }

            $decoded = base64_decode($encrypted);
            if (is_string($decoded) && strpos($decoded, '|') !== false) {
                $parts = explode('|', $decoded, 2);
                if (count($parts) === 2) {
                    $secret = (string) $parts[0];
                    $hash = (string) $parts[1];
                    $key = $this->get_encryption_key_compat();
                    if (hash('sha256', $key . $secret) === $hash) {
                        return $secret;
                    }
                }
            }

            return '';
        } catch (Exception $e) {
            return '';
        }
    }

    private function parse_semrush_csv_content($csv) {
        $csv = is_string($csv) ? trim($csv) : '';
        if ($csv === '') {
            return array();
        }

        $lines = preg_split("/\\r\\n|\\r|\\n/", $csv);
        if (!is_array($lines) || empty($lines)) {
            return array();
        }

        $header_map = null;
        $rows = array();
        foreach ($lines as $line_index => $line) {
            $line = is_string($line) ? trim($line) : '';
            if ($line === '') {
                continue;
            }

            $cols = str_getcsv($line, ';');
            if (!is_array($cols) || empty($cols)) {
                continue;
            }

            if ($header_map === null) {
                $header_map = $this->detect_sheet_header_map($cols);
                continue;
            }

            $kw_col = (is_array($header_map) && isset($header_map['keyword'])) ? (int) $header_map['keyword'] : 0;
            $kw_raw = isset($cols[$kw_col]) ? (string) $cols[$kw_col] : '';
            $kw = $this->normalize_keyword($kw_raw);
            if ($kw === '') {
                continue;
            }

            $intent_col = (is_array($header_map) && isset($header_map['intent'])) ? (int) $header_map['intent'] : null;
            $volume_col = (is_array($header_map) && isset($header_map['volume'])) ? (int) $header_map['volume'] : null;
            $kd_col = (is_array($header_map) && isset($header_map['keyword_difficulty'])) ? (int) $header_map['keyword_difficulty'] : null;
            $cpc_col = (is_array($header_map) && isset($header_map['cpc_usd'])) ? (int) $header_map['cpc_usd'] : null;
            $serp_col = (is_array($header_map) && isset($header_map['serp_features'])) ? (int) $header_map['serp_features'] : null;

            $rows[] = array(
                'keyword' => $kw,
                'intent' => $this->normalize_intent(($intent_col !== null && isset($cols[$intent_col])) ? (string) $cols[$intent_col] : ''),
                'volume' => $this->normalize_nullable_int(($volume_col !== null && isset($cols[$volume_col])) ? $cols[$volume_col] : null),
                'keyword_difficulty' => $this->normalize_nullable_decimal(($kd_col !== null && isset($cols[$kd_col])) ? $cols[$kd_col] : null),
                'cpc_usd' => $this->normalize_nullable_decimal(($cpc_col !== null && isset($cols[$cpc_col])) ? $cols[$cpc_col] : null),
                'serp_features' => $this->normalize_serp_features(($serp_col !== null && isset($cols[$serp_col])) ? (string) $cols[$serp_col] : ''),
            );
        }

        $by_keyword = array();
        foreach ($rows as $r) {
            $by_keyword[(string) $r['keyword']] = $r;
        }
        $rows = array_values($by_keyword);
        if (count($rows) > 5000) {
            $rows = array_slice($rows, 0, 5000);
        }

        return $rows;
    }

    private function is_semrush_nothing_found_message($message) {
        $message = is_string($message) ? trim($message) : '';
        if ($message === '') {
            return false;
        }

        $lower = strtolower($message);
        if (strpos($lower, 'nothing found') !== false) {
            return true;
        }

        return preg_match('/^error\\s*50\\b/i', $message) === 1;
    }

    public function ajax_semrush_gap_finder() {
        check_ajax_referer('SMARK_keyword_gap_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $competitor_id = isset($_POST['competitor_id']) ? (int) $_POST['competitor_id'] : 0;
        if ($competitor_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid competitor.', 'smark')));
        }

        // We only want 10 keywords stored (no more).
        $limit = 10;

        $vol_min = isset($_POST['volume_min']) ? (int) $_POST['volume_min'] : null;
        $vol_min = $this->normalize_nullable_int($vol_min);
        if ($vol_min !== null && $vol_min < 0) {
            $vol_min = null;
        }

        $competitor_domain = $this->get_competitor_domain_by_id($competitor_id);
        if ($competitor_domain === '') {
            wp_send_json_error(array('message' => __('Invalid competitor.', 'smark')));
        }

        $database = apply_filters('SMARK_semrush_database', 'us');
        $database = is_string($database) ? trim($database) : 'us';
        if ($database === '') {
            $database = 'us';
        }

        // Semrush supports display_filter syntax: <sign>|<field>|<operation>|<value>.
        $display_filter = ($vol_min !== null) ? ('+|Nq|Gt|' . (string) $vol_min) : '';

        $params_base = array(
            'type' => 'domain_organic',
            'domain' => $competitor_domain,
            'database' => $database,
            'display_sort' => 'kd_asc',
            // Prefer Fp (SERP Features) over Fk (Keywords SERP Features).
            'export_columns' => 'Ph,In,Nq,Kd,Cp,Fp,Fk',
            'export_decode' => 1,
        );
        if ($display_filter !== '') {
            $params_base['display_filter'] = $display_filter;
        }

        $attempts = 0;
        $offset = 0;
        $rows = array();
        $had_page_rows = false;

        while ($attempts < 3 && empty($rows)) {
            $attempts++;

            // Semrush pagination note: when using display_offset, display_limit should be increased by offset.
            // This yields (display_limit - display_offset) rows in output.
            $params = $params_base;
            $params['display_offset'] = $offset;
            $params['display_limit'] = $offset + $limit;

            $page_rows = array();
            $source = 'central';

            $central_rows = $this->semrush_fetch_rows_via_central($params);
            if (!is_wp_error($central_rows)) {
                $page_rows = $central_rows;
            } else {
                $msg = $central_rows->get_error_message();
                $msg = is_string($msg) ? trim($msg) : '';
                if ($msg === '') {
                    $msg = __('Semrush is not configured in SMark Core.', 'smark');
                }
                if ($this->is_semrush_nothing_found_message($msg)) {
                    wp_send_json_success(array('inserted' => 0, 'total' => 0, 'no_results' => true));
                }
                wp_send_json_error(array('message' => $msg));
            }

            if (class_exists('SMarkLogger')) {
                $safe_params = $params;
                if (isset($safe_params['key'])) {
                    $safe_params['key'] = '[redacted]';
                }
                SMarkLogger::info('Keyword Gap Finder - Semrush response', array(
                    'competitor_id' => $competitor_id,
                    'competitor_domain' => $competitor_domain,
                    'attempt' => $attempts,
                    'source' => $source,
                    'params' => $safe_params,
                    'rows_count' => is_array($page_rows) ? count($page_rows) : 0,
                ));
            }

            if (!empty($page_rows)) {
                $had_page_rows = true;
            }

            if (empty($page_rows)) {
                break;
            }

            // Keep only new keywords that are not already stored for this competitor.
            $candidate = array();
            $keywords = array();
            foreach ($page_rows as $r) {
                if (is_array($r) && isset($r['keyword']) && is_string($r['keyword']) && $r['keyword'] !== '') {
                    $keywords[] = (string) $r['keyword'];
                }
            }
            $keywords = array_values(array_unique($keywords));

            if (!empty($keywords)) {
                if ($this->is_tables_available()) {
                    global $wpdb;
                    $keywords_table_sql = $this->escape_db_identifier($this->keywords_table);
                    $placeholders = implode(',', array_fill(0, count($keywords), '%s'));
                    $args = array_merge(array($competitor_id), $keywords);
                    $sql = $keywords_table_sql !== '' ? 'SELECT keyword FROM ' . $keywords_table_sql . ' WHERE competitor_id = %d AND keyword IN (' . $placeholders . ')' : '';
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $existing = $sql !== '' ? $wpdb->get_col($wpdb->prepare($sql, ...$args)) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $existing = is_array($existing) ? $existing : array();
                    $existing_map = array();
                    foreach ($existing as $kw) {
                        $existing_map[(string) $kw] = true;
                    }

                    foreach ($page_rows as $r) {
                        $kw = is_array($r) && isset($r['keyword']) ? (string) $r['keyword'] : '';
                        if ($kw !== '' && !isset($existing_map[$kw])) {
                            $candidate[] = $r;
                        }
                    }
                } else {
                    $existing_rows = $this->get_option_keywords($competitor_id);
                    $existing_rows = is_array($existing_rows) ? $existing_rows : array();
                    $existing_map = array();
                    foreach ($existing_rows as $er) {
                        if (is_array($er) && isset($er['keyword']) && is_string($er['keyword'])) {
                            $existing_map[(string) $er['keyword']] = true;
                        }
                    }

                    foreach ($page_rows as $r) {
                        $kw = is_array($r) && isset($r['keyword']) ? (string) $r['keyword'] : '';
                        if ($kw !== '' && !isset($existing_map[$kw])) {
                            $candidate[] = $r;
                        }
                    }
                }
            }

            if (!empty($candidate)) {
                $rows = array_slice($candidate, 0, $limit);
                break;
            }

            // All keywords already exist; try next page.
            $offset += $limit;
        }

        if (empty($rows)) {
            $resp = array('inserted' => 0, 'total' => 0);
            if (!$had_page_rows && $display_filter !== '') {
                $resp['no_results'] = true;
            }
            wp_send_json_success($resp);
        }

        $now = current_time('mysql');
        $inserted = 0;
        $total = 0;

        if ($this->is_tables_available()) {
            global $wpdb;

            $keywords = array();
            foreach ($rows as $r) {
                if (isset($r['keyword']) && is_string($r['keyword']) && $r['keyword'] !== '') {
                    $keywords[] = (string) $r['keyword'];
                }
            }
            $keywords = array_values(array_unique($keywords));

            if (!empty($keywords)) {
                $placeholders = implode(',', array_fill(0, count($keywords), '%s'));
                $args = array_merge(array($competitor_id), $keywords);
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $keywords_table_sql = $this->escape_db_identifier($this->keywords_table);
                $sql = $keywords_table_sql !== '' ? 'SELECT keyword FROM ' . $keywords_table_sql . ' WHERE competitor_id = %d AND keyword IN (' . $placeholders . ')' : '';
                $existing = $sql !== '' ? $wpdb->get_col($wpdb->prepare($sql, ...$args)) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $existing = is_array($existing) ? $existing : array();
                $existing_map = array();
                foreach ($existing as $kw) {
                    $existing_map[(string) $kw] = true;
                }

                $chunk = array();
                foreach ($rows as $row) {
                    $kw = isset($row['keyword']) ? (string) $row['keyword'] : '';
                    if ($kw === '' || isset($existing_map[$kw])) {
                        continue;
                    }

                    $keyword_sql = $wpdb->prepare('%s', $kw);

                    $intent = isset($row['intent']) ? (string) $row['intent'] : '';
                    $intent_sql = ($intent !== '') ? $wpdb->prepare('%s', $intent) : 'NULL';

                    $volume_sql = (isset($row['volume']) && $row['volume'] !== null && $row['volume'] !== '') ? (string) ((int) $row['volume']) : 'NULL';
                    $kd_sql = (isset($row['keyword_difficulty']) && $row['keyword_difficulty'] !== null && $row['keyword_difficulty'] !== '') ? (string) ((float) $row['keyword_difficulty']) : 'NULL';
                    $cpc_sql = (isset($row['cpc_usd']) && $row['cpc_usd'] !== null && $row['cpc_usd'] !== '') ? (string) ((float) $row['cpc_usd']) : 'NULL';

                    $serp = isset($row['serp_features']) ? (string) $row['serp_features'] : '';
                    $serp_sql = ($serp !== '') ? $wpdb->prepare('%s', $serp) : 'NULL';

                    $created_at_sql = $wpdb->prepare('%s', $now);

                    $chunk[] = '(' . (int) $competitor_id . ',' . $keyword_sql . ',' . $intent_sql . ',' . $volume_sql . ',' . $kd_sql . ',' . $cpc_sql . ',' . $serp_sql . ',' . $created_at_sql . ')';
                    if (count($chunk) >= 200) {
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $sql = "INSERT INTO {$this->keywords_table} (competitor_id, keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features, created_at) VALUES " . implode(',', $chunk);
                        $res = $wpdb->query($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Values are safely prepared.
                        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        if ($res) {
                            $inserted += (int) $res;
                        }
                        $chunk = array();
                    }
                }

                if (!empty($chunk)) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $sql = "INSERT INTO {$this->keywords_table} (competitor_id, keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features, created_at) VALUES " . implode(',', $chunk);
                    $res = $wpdb->query($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Values are safely prepared.
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    if ($res) {
                        $inserted += (int) $res;
                    }
                }

                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $keywords_table_sql = $this->escape_db_identifier($this->keywords_table);
                $total = $keywords_table_sql !== '' ? (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $keywords_table_sql . ' WHERE competitor_id = %d', $competitor_id)) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->update(
                    $this->competitors_table,
                    array('keywords_updated_at' => $now, 'updated_at' => $now),
                    array('id' => $competitor_id),
                    array('%s', '%s'),
                    array('%d')
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            }
        } else {
            $existing = $this->get_option_keywords($competitor_id);
            $existing = is_array($existing) ? $existing : array();

            $by_keyword = array();
            foreach ($existing as $r) {
                if (is_array($r) && isset($r['keyword'])) {
                    $by_keyword[(string) $r['keyword']] = $r;
                }
            }

            foreach ($rows as $r) {
                $kw = isset($r['keyword']) ? (string) $r['keyword'] : '';
                if ($kw === '' || isset($by_keyword[$kw])) {
                    continue;
                }
                $by_keyword[$kw] = $r;
                $inserted++;
            }

            $merged = array_values($by_keyword);
            $this->set_option_keywords($competitor_id, $merged);
            $total = count($merged);

            $competitors = $this->get_option_competitors();
            foreach ($competitors as &$c) {
                if (isset($c['id']) && (int) $c['id'] === $competitor_id) {
                    $c['keywords_updated_at'] = $now;
                    $c['updated_at'] = $now;
                    break;
                }
            }
            unset($c);
            $this->set_option_competitors($competitors);
        }

        wp_send_json_success(array(
            'inserted' => $inserted,
            'total' => $total,
        ));
    }

    public function ajax_consume_mark() {
        check_ajax_referer('SMARK_keyword_gap_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 1;
        if ($amount <= 0 || $amount > 10) {
            wp_send_json_error(array('message' => 'Invalid amount'), 400);
        }

        $remaining = $this->consume_mark_via_central($amount);
        if (is_wp_error($remaining)) {
            $status = 500;
            $data = $remaining->get_error_data();
            if (is_array($data) && isset($data['status'])) {
                $status = (int) $data['status'];
            }

            if ((int) $status === 402 || $remaining->get_error_code() === 'smark_mark_insufficient') {
                wp_send_json_error(array('message' => 'Insufficient mark credits'), 402);
            }

            $msg = $remaining->get_error_message();
            wp_send_json_error(array('message' => $msg !== '' ? $msg : 'Error'), $status > 0 ? $status : 500);
        }

        wp_send_json_success(array(
            'remaining' => (int) $remaining,
            'amount' => $amount,
        ));
    }

    private function get_competitors_rows() {
        if ($this->is_tables_available()) {
            global $wpdb;
            $competitors_table_sql = $this->escape_db_identifier($this->competitors_table);
            $keywords_table_sql = $this->escape_db_identifier($this->keywords_table);
            if ($competitors_table_sql === '' || $keywords_table_sql === '') {
                return array();
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                'SELECT c.id, c.domain, c.keywords_updated_at, c.created_at, c.updated_at, COUNT(k.id) AS keywords_count FROM ' . $competitors_table_sql . ' c LEFT JOIN ' . $keywords_table_sql . ' k ON k.competitor_id = c.id GROUP BY c.id ORDER BY c.domain ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            return is_array($rows) ? $rows : array();
        }

        $rows = array();
        foreach ($this->get_option_competitors() as $c) {
            $id = isset($c['id']) ? (int) $c['id'] : 0;
            $domain = isset($c['domain']) ? (string) $c['domain'] : '';
            if ($id <= 0 || $domain === '') {
                continue;
            }

            $keywords = $this->get_option_keywords($id);
            $rows[] = array(
                'id' => $id,
                'domain' => $domain,
                'keywords_updated_at' => isset($c['keywords_updated_at']) ? $c['keywords_updated_at'] : null,
                'created_at' => isset($c['created_at']) ? (string) $c['created_at'] : '',
                'updated_at' => isset($c['updated_at']) ? (string) $c['updated_at'] : '',
                'keywords_count' => is_array($keywords) ? count($keywords) : 0,
            );
        }

        usort($rows, function ($a, $b) {
            return strcmp((string) $a['domain'], (string) $b['domain']);
        });

        return $rows;
    }

    /**
     * Enqueue assets for Keyword Gap page.
     *
     * @param string $hook Current admin hook.
     *
     * @return void
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'admin_page_smark-keyword-gap') {
            return;
        }

        $asset_version = defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0';
        $css_path = plugin_dir_path(__FILE__) . 'assets/keyword-gap.css';
        $js_path = plugin_dir_path(__FILE__) . 'assets/keyword-gap.js';
        $shell_css_path = plugin_dir_path(__FILE__) . '../keyword-research/assets/keyword-research.css';

        $css_ver = $asset_version;
        $js_ver = $asset_version;
        $shell_ver = $asset_version;
        if (is_string($css_path) && file_exists($css_path)) {
            $css_ver = $asset_version . '.' . (string) filemtime($css_path);
        }
        if (is_string($js_path) && file_exists($js_path)) {
            $js_ver = $asset_version . '.' . (string) filemtime($js_path);
        }
        if (is_string($shell_css_path) && file_exists($shell_css_path)) {
            $shell_ver = $asset_version . '.' . (string) filemtime($shell_css_path);
        }

        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'vazirmatn-font',
            'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap',
            array(),
            $asset_version
        );

        // Reuse Keyword Research layout and component styling.
        wp_enqueue_style(
            'smark-keyword-gap-shell',
            (defined('SMARK_PLUGIN_URL') ? SMARK_PLUGIN_URL : plugin_dir_url(__FILE__) . '../../') . 'features/keyword-research/assets/keyword-research.css',
            array('dashicons', 'vazirmatn-font'),
            $shell_ver
        );

        wp_enqueue_style(
            'smark-keyword-gap',
            plugin_dir_url(__FILE__) . 'assets/keyword-gap.css',
            array('smark-keyword-gap-shell'),
            $css_ver
        );

        wp_enqueue_script(
            'smark-keyword-gap',
            plugin_dir_url(__FILE__) . 'assets/keyword-gap.js',
            array('jquery'),
            $js_ver,
            true
        );

        add_filter('admin_body_class', function ($classes) {
            if (strpos((string) $classes, 'smark-plugin-page') === false) {
                $classes .= ' smark-plugin-page';
            }
            return $classes;
        });

        wp_localize_script('smark-keyword-gap', 'SMarkKeywordGap', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('SMARK_keyword_gap_nonce'),
            'lang'    => $this->get_panel_language(),
            'strings' => $this->get_strings($this->get_panel_language()),
        ));
    }

    /**
     * Render Keyword Gap admin page.
     *
     * @return void
     */
    public function render_page() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'));
        }

        $current_lang = $this->get_panel_language();
        $rtl_class    = ($current_lang === 'fa') ? 'rtl' : '';
        $is_rtl       = ($current_lang === 'fa');
        $strings      = $this->get_strings($current_lang);
        ?>
        <div class="wrap smark-keyword-gap-page smark-keyword-research-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
            <div class="smark-page-header">
                <h1><?php echo esc_html($strings['page_title']); ?></h1>
                <p class="description"><?php echo esc_html($strings['page_subtitle']); ?></p>
            </div>

            <div class="smark-breadcrumb">
                <div class="breadcrumb-left">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html($strings['breadcrumb_dashboard']); ?></a>
                    <span class="separator"><?php echo $is_rtl ? '‹' : '›'; ?></span>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-seo-optimization')); ?>"><?php echo esc_html($strings['breadcrumb_seo']); ?></a>
                    <span class="separator"><?php echo $is_rtl ? '‹' : '›'; ?></span>
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

            <div class="smark-keyword-research-content">
                <div class="layout-columns">
                    <div class="keywords-card card smark-keyword-gap-card">
                        <div class="card-header-with-button">
                            <button type="button" class="btn btn-outline" id="smarkAddCompetitorBtn">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php echo esc_html($strings['add_competitor']); ?>
                            </button>
                        </div>

                        <div class="card-body table-wrapper smark-keyword-gap-table-wrapper">
                            <table class="data-table" id="smarkKeywordGapCompetitorsTable" <?php echo $rtl_class ? 'dir="rtl"' : ''; ?>>
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html($strings['table_domain']); ?></th>
                                        <th class="table-actions-column"><?php echo esc_html($strings['table_competitor_keywords']); ?></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>

                            <div class="empty-state" id="smarkKeywordGapEmpty" style="display:none;">
                                <div class="empty-state-content">
                                    <h4><?php echo esc_html($strings['empty_title']); ?></h4>
                                    <p><?php echo esc_html($strings['empty_subtitle']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plugin Version Footer -->
            <div class="smark-version-footer">
                <div class="version-info">
                    <span class="version-label"><?php echo ($current_lang === 'fa') ? 'پلاگین اسمارک' : 'SMark Plugin'; ?></span>
                    <span class="version-separator">•</span>
                    <span class="version-number">v<?php echo esc_html(defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0'); ?></span>
                </div>
            </div>
        </div>

        <?php $this->render_add_competitors_modal($current_lang); ?>
        <?php $this->render_upload_keywords_modal($current_lang); ?>
        <?php $this->render_gap_finder_modal($current_lang); ?>
        <?php $this->render_view_keywords_modal($current_lang); ?>
        <?php
    }

    private function render_add_competitors_modal($lang) {
        $is_rtl = ($lang === 'fa');
        $strings = $this->get_strings($lang);
        ?>
        <div class="smark-modal<?php echo $is_rtl ? ' rtl' : ''; ?>" id="smarkAddCompetitorModal" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
            <div class="smark-modal-dialog smark-keyword-gap-modal">
                <div class="modal-header">
                    <h3><?php echo esc_html($strings['add_competitor_modal_title']); ?></h3>
                    <button type="button" class="modal-close" data-close="#smarkAddCompetitorModal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="smark-keyword-gap-modal__hint"><?php echo esc_html($strings['add_competitor_modal_hint']); ?></p>
                    <textarea id="smarkCompetitorList" class="form-control" rows="8" placeholder="<?php echo esc_attr($strings['add_competitor_modal_placeholder']); ?>" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close="#smarkAddCompetitorModal"><?php echo esc_html($strings['close_button']); ?></button>
                    <button type="button" class="btn btn-success" id="smarkSaveCompetitors"><?php echo esc_html($strings['save_button']); ?></button>
                </div>
                <div class="modal-loading" id="smarkAddCompetitorLoading" aria-hidden="true" style="display:none;">
                    <span class="dashicons dashicons-update dashicons-spin"></span>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_upload_keywords_modal($lang) {
        $is_rtl = ($lang === 'fa');
        $strings = $this->get_strings($lang);
        ?>
        <div class="smark-modal<?php echo $is_rtl ? ' rtl' : ''; ?>" id="smarkUploadCompetitorKeywordsModal" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
            <div class="smark-modal-dialog smark-keyword-gap-modal">
                <div class="modal-header">
                    <h3><?php echo esc_html($strings['upload_modal_title']); ?></h3>
                    <button type="button" class="modal-close" data-close="#smarkUploadCompetitorKeywordsModal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="smark-keyword-gap-modal__hint" id="smarkUploadModalDomain"></p>
                    <p class="smark-keyword-gap-modal__hint"><?php echo esc_html($strings['upload_modal_description']); ?></p>
                    <input type="hidden" id="smarkUploadCompetitorId" value="0">
                    <input type="file" id="smarkCompetitorKeywordsFile" class="form-control" accept=".csv,.xls,.xlsx" />
                    <p class="smark-keyword-gap-modal__subhint"><?php echo esc_html($strings['upload_modal_hint']); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close="#smarkUploadCompetitorKeywordsModal"><?php echo esc_html($strings['close_button']); ?></button>
                    <button type="button" class="btn btn-primary" id="smarkUploadCompetitorKeywordsSubmit"><?php echo esc_html($strings['upload_button']); ?></button>
                </div>
                <div class="modal-loading" id="smarkUploadCompetitorKeywordsLoading" aria-hidden="true" style="display:none;">
                    <span class="dashicons dashicons-update dashicons-spin"></span>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_gap_finder_modal($lang) {
        $is_rtl = ($lang === 'fa');
        $strings = $this->get_strings($lang);
        ?>
        <div class="smark-modal<?php echo $is_rtl ? ' rtl' : ''; ?>" id="smarkGapFinderModal" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
            <div class="smark-modal-dialog smark-keyword-gap-modal">
                <div class="modal-header">
                    <h3><?php echo esc_html($strings['gap_finder_modal_title']); ?></h3>
                    <button type="button" class="modal-close" data-close="#smarkGapFinderModal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="smarkGapFinderCompetitorId" value="0">
                    <p class="smark-keyword-gap-modal__hint" id="smarkGapFinderDomain"></p>
                    <p class="smark-keyword-gap-modal__hint"><?php echo esc_html($strings['gap_finder_modal_hint']); ?></p>

                    <div class="kg-modal-alert kg-modal-alert--success" id="smarkGapFinderNoResults" role="alert" style="display:none;">
                        <button type="button" class="kg-modal-alert__close" aria-label="Close" data-close-alert="#smarkGapFinderNoResults">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                        <div class="kg-modal-alert__text"><?php echo esc_html($strings['no_results_filter']); ?></div>
                    </div>

                    <div class="kg-finder-filters">
                        <div class="kg-filter-group">
                            <label class="kg-filter-label" for="smarkGapFinderVolMin"><?php echo esc_html($strings['gap_finder_volume_min_label']); ?></label>
                            <div class="kg-filter-range">
                                <input type="number" step="1" min="0" id="smarkGapFinderVolMin" class="form-control" placeholder="<?php echo esc_attr($strings['gap_finder_min']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close="#smarkGapFinderModal"><?php echo esc_html($strings['close_button']); ?></button>
                    <button type="button" class="btn btn-primary" id="smarkGapFinderSubmit" title="<?php echo esc_attr($strings['mark_cost_tooltip']); ?>">
                        <?php echo esc_html($strings['gap_finder_submit']); ?>
                        <span class="kg-smark-cost-badge" aria-hidden="true">1</span>
                    </button>
                </div>
                <div class="modal-loading" id="smarkGapFinderLoading" aria-hidden="true" style="display:none;">
                    <span class="dashicons dashicons-update dashicons-spin"></span>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_view_keywords_modal($lang) {
        $is_rtl = ($lang === 'fa');
        $strings = $this->get_strings($lang);
        ?>
        <div class="smark-modal<?php echo $is_rtl ? ' rtl' : ''; ?>" id="smarkViewCompetitorKeywordsModal" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
            <div class="smark-modal-dialog large smark-keyword-gap-modal smark-keyword-gap-modal--large">
                <div class="modal-header">
                    <h3><?php echo esc_html($strings['view_modal_title']); ?></h3>
                    <button type="button" class="modal-close" data-close="#smarkViewCompetitorKeywordsModal">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="smarkViewCompetitorId" value="0">
                    <p class="smark-keyword-gap-modal__hint" id="smarkViewModalDomain"></p>

                    <div class="bank-search-bar smark-keyword-gap-modal__search-bar">
                        <input type="search" id="smarkCompetitorKeywordsSearch" class="form-control" placeholder="<?php echo esc_attr($strings['search_placeholder']); ?>">
                        <button type="button" class="btn btn-outline" id="smarkCompetitorKeywordsSearchButton">
                            <span class="dashicons dashicons-search"></span>
                            <?php echo esc_html($strings['search_button']); ?>
                        </button>
                    </div>

                    <div class="smark-keyword-gap-modal__results">
                        <table class="data-table" id="smarkCompetitorKeywordsTable" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>>
                            <thead>
                                <tr>
                                    <th><?php echo esc_html($strings['table_keyword']); ?></th>
                                    <th><?php echo esc_html($strings['table_intent']); ?></th>
                                    <th><?php echo esc_html($strings['table_volume']); ?></th>
                                    <th class="kg-difficulty-filter-header">
                                        <div class="kg-difficulty-filter-header-inner">
                                            <span class="kg-difficulty-filter-header-label"><?php echo esc_html($strings['table_difficulty']); ?></span>
                                            <button
                                                type="button"
                                                class="kg-difficulty-filter-toggle"
                                                aria-haspopup="true"
                                                aria-expanded="false"
                                                aria-label="<?php echo esc_attr($strings['table_difficulty']); ?>"
                                            >
                                                <svg class="kg-filter-icon" width="14" height="14" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                                                    <path d="M5.5 7.5a1 1 0 0 1 1.4 0L10 10.6l3.1-3.1a1 1 0 1 1 1.4 1.4l-3.8 3.8a1 1 0 0 1-1.4 0L5.5 8.9a1 1 0 0 1 0-1.4Z" fill="currentColor"></path>
                                                </svg>
                                            </button>
                                            <div class="kg-difficulty-filter-menu" role="menu" aria-label="<?php echo esc_attr($strings['table_difficulty']); ?>">
                                                <div class="kg-difficulty-filter-range" role="group" aria-label="<?php echo esc_attr($strings['table_difficulty']); ?>">
                                                    <label class="kg-difficulty-filter-field">
                                                        <span class="kg-difficulty-filter-field-label"><?php echo esc_html($strings['difficulty_filter_min']); ?></span>
                                                        <input type="number" inputmode="decimal" min="0" max="100" step="0.01" class="form-control kg-difficulty-filter-input" data-field="min" />
                                                    </label>
                                                    <span class="kg-difficulty-filter-sep">-</span>
                                                    <label class="kg-difficulty-filter-field">
                                                        <span class="kg-difficulty-filter-field-label"><?php echo esc_html($strings['difficulty_filter_max']); ?></span>
                                                        <input type="number" inputmode="decimal" min="0" max="100" step="0.01" class="form-control kg-difficulty-filter-input" data-field="max" />
                                                    </label>
                                                </div>
                                                <div class="kg-difficulty-filter-sort" role="group" aria-label="<?php echo esc_attr($strings['table_difficulty']); ?>">
                                                    <button type="button" class="kg-difficulty-sort-option" data-sort="asc" role="menuitemradio" aria-checked="false"><?php echo esc_html($strings['difficulty_sort_asc']); ?></button>
                                                    <button type="button" class="kg-difficulty-sort-option" data-sort="desc" role="menuitemradio" aria-checked="false"><?php echo esc_html($strings['difficulty_sort_desc']); ?></button>
                                                </div>
                                                <button type="button" class="kg-difficulty-filter-reset"><?php echo esc_html($strings['difficulty_filter_reset']); ?></button>
                                            </div>
                                        </div>
                                    </th>
                                    <th><?php echo esc_html($strings['table_cpc']); ?></th>
                                    <th><?php echo esc_html($strings['table_serp']); ?></th>
                                    <th><?php echo esc_html($strings['table_actions']); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <div class="empty-state" id="smarkCompetitorKeywordsEmpty" style="display:none;">
                            <div class="empty-state-content">
                                <h4><?php echo esc_html($strings['empty_keywords_title']); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close="#smarkViewCompetitorKeywordsModal"><?php echo esc_html($strings['close_button']); ?></button>
                </div>
                <div class="modal-loading" id="smarkViewCompetitorKeywordsLoading" aria-hidden="true" style="display:none;">
                    <span class="dashicons dashicons-update dashicons-spin"></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save language preference for Keyword Gap page.
     *
     * @return void
     */
    public function ajax_save_language() {
        check_ajax_referer('SMARK_keyword_gap_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '';
        if ($language !== 'en' && $language !== 'fa') {
            wp_send_json_error(array('message' => __('Invalid language', 'smark')));
        }

        $this->set_panel_language($language);
        wp_send_json_success(array('language' => $language));
    }

    public function ajax_get_competitors() {
        check_ajax_referer('SMARK_keyword_gap_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $rows = $this->get_competitors_rows();
        wp_send_json_success(array('competitors' => $rows));
    }

    public function ajax_add_competitors() {
        check_ajax_referer('SMARK_keyword_gap_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $text = isset($_POST['domains']) ? sanitize_textarea_field(wp_unslash($_POST['domains'])) : '';
        $lines = preg_split('/\\r\\n|\\r|\\n/', $text);
        $domains = array();

        if (is_array($lines)) {
            foreach ($lines as $line) {
                $domain = $this->normalize_domain((string) $line);
                if ($domain !== '') {
                    $domains[] = $domain;
                }
            }
        }

        $domains = array_values(array_unique($domains));
        if (empty($domains)) {
            wp_send_json_error(array('message' => __('No valid domains provided.', 'smark')));
        }

        if (count($domains) > 50) {
            $domains = array_slice($domains, 0, 50);
        }

        $now = current_time('mysql');
        $inserted = 0;

        if ($this->is_tables_available()) {
            global $wpdb;
            $competitors_table_sql = $this->escape_db_identifier($this->competitors_table);
            if ($competitors_table_sql === '') {
                wp_send_json_error(array('message' => __('Database table is not available.', 'smark')));
            }

            foreach ($domains as $domain) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $result = $wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $wpdb->prepare(
                        'INSERT IGNORE INTO ' . $competitors_table_sql . ' (domain, keywords_updated_at, created_at, updated_at) VALUES (%s, NULL, %s, %s)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                        $domain,
                        $now,
                        $now
                    )
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                if ($result) {
                    $inserted++;
                }
            }
        } else {
            $competitors = $this->get_option_competitors();
            $existing = array();
            foreach ($competitors as $c) {
                if (isset($c['domain']) && is_string($c['domain'])) {
                    $existing[strtolower(trim($c['domain']))] = true;
                }
            }

            foreach ($domains as $domain) {
                $key = strtolower(trim($domain));
                if ($key === '' || isset($existing[$key])) {
                    continue;
                }

                $competitors[] = array(
                    'id' => $this->next_option_competitor_id(),
                    'domain' => $domain,
                    'keywords_updated_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                );
                $existing[$key] = true;
                $inserted++;
            }

            $this->set_option_competitors($competitors);
        }

        wp_send_json_success(array(
            'inserted' => $inserted,
            'competitors' => $this->get_competitors_rows(),
        ));
    }

    public function ajax_upload_competitor_keywords() {
        check_ajax_referer('SMARK_keyword_gap_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $competitor_id = isset($_POST['competitor_id']) ? (int) $_POST['competitor_id'] : 0;
        if ($competitor_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid competitor.', 'smark')));
        }

        if ($this->is_tables_available()) {
            global $wpdb;
            $competitors_table_sql = $this->escape_db_identifier($this->competitors_table);
            if ($competitors_table_sql === '') {
                wp_send_json_error(array('message' => __('Database table is not available.', 'smark')));
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $exists = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $competitors_table_sql . ' WHERE id = %d', $competitor_id)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($exists <= 0) {
                wp_send_json_error(array('message' => __('Invalid competitor.', 'smark')));
            }
        } else {
            $found = false;
            foreach ($this->get_option_competitors() as $c) {
                if (isset($c['id']) && (int) $c['id'] === $competitor_id) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                wp_send_json_error(array('message' => __('Invalid competitor.', 'smark')));
            }
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            wp_send_json_error(array('message' => __('Please choose a file to upload.', 'smark')));
        }

        $file_type = isset($_FILES['file']['type']) ? sanitize_mime_type(wp_unslash((string) $_FILES['file']['type'])) : '';
        $file_name = isset($_FILES['file']['name']) ? sanitize_file_name(wp_unslash((string) $_FILES['file']['name'])) : '';
        $file_ext  = strtolower((string) pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_types = array(
            'text/csv',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        if (!in_array($file_type, $allowed_types, true) && !in_array($file_ext, array('csv', 'xls', 'xlsx'), true)) {
            wp_send_json_error(array('message' => __('Unsupported file type. Please upload CSV or Excel files.', 'smark')));
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $uploaded = wp_handle_upload($_FILES['file'], array('test_form' => false));
        if (isset($uploaded['error'])) {
            wp_send_json_error(array('message' => $uploaded['error']));
        }

        $file_path = isset($uploaded['file']) ? (string) $uploaded['file'] : '';
        if ($file_path === '' || !file_exists($file_path)) {
            wp_send_json_error(array('message' => __('Upload failed. Please try again.', 'smark')));
        }

        $rows = $this->parse_keyword_file($file_path);
        if (empty($rows)) {
            wp_send_json_error(array('message' => __('No keywords found in file.', 'smark')));
        }

        $now = current_time('mysql');

        $inserted = 0;

        if ($this->is_tables_available()) {
            global $wpdb;

            // Replace keywords for this competitor.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($this->keywords_table, array('competitor_id' => $competitor_id), array('%d'));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            $chunk = array();
            foreach ($rows as $row) {
                $keyword_sql = $wpdb->prepare('%s', (string) $row['keyword']);

                $intent = isset($row['intent']) ? (string) $row['intent'] : '';
                $intent_sql = ($intent !== '') ? $wpdb->prepare('%s', $intent) : 'NULL';

                $volume_sql = (isset($row['volume']) && $row['volume'] !== null && $row['volume'] !== '') ? (string) ((int) $row['volume']) : 'NULL';

                $kd_sql = (isset($row['keyword_difficulty']) && $row['keyword_difficulty'] !== null && $row['keyword_difficulty'] !== '') ? (string) ((float) $row['keyword_difficulty']) : 'NULL';
                $cpc_sql = (isset($row['cpc_usd']) && $row['cpc_usd'] !== null && $row['cpc_usd'] !== '') ? (string) ((float) $row['cpc_usd']) : 'NULL';

                $serp = isset($row['serp_features']) ? (string) $row['serp_features'] : '';
                $serp_sql = ($serp !== '') ? $wpdb->prepare('%s', $serp) : 'NULL';

                $created_at_sql = $wpdb->prepare('%s', $now);

                $chunk[] = '(' . (int) $competitor_id . ',' . $keyword_sql . ',' . $intent_sql . ',' . $volume_sql . ',' . $kd_sql . ',' . $cpc_sql . ',' . $serp_sql . ',' . $created_at_sql . ')';
                if (count($chunk) >= 200) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $sql = "INSERT INTO {$this->keywords_table} (competitor_id, keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features, created_at) VALUES " . implode(',', $chunk);
                    $res = $wpdb->query($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Values are safely prepared.
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    if ($res) {
                        $inserted += (int) $res;
                    }
                    $chunk = array();
                }
            }

            if (!empty($chunk)) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $sql = "INSERT INTO {$this->keywords_table} (competitor_id, keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features, created_at) VALUES " . implode(',', $chunk);
                $res = $wpdb->query($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Values are safely prepared.
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                if ($res) {
                    $inserted += (int) $res;
                }
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $this->competitors_table,
                array('keywords_updated_at' => $now, 'updated_at' => $now),
                array('id' => $competitor_id),
                array('%s', '%s'),
                array('%d')
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        } else {
            $this->set_option_keywords($competitor_id, $rows);
            $inserted = count($rows);

            $competitors = $this->get_option_competitors();
            foreach ($competitors as &$c) {
                if (isset($c['id']) && (int) $c['id'] === $competitor_id) {
                    $c['keywords_updated_at'] = $now;
                    $c['updated_at'] = $now;
                    break;
                }
            }
            unset($c);
            $this->set_option_competitors($competitors);
        }

        wp_send_json_success(array('inserted' => $inserted));
    }

    private function parse_keyword_file($file_path) {
        $extension = strtolower((string) pathinfo((string) $file_path, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->parse_csv_rows($file_path);
        }

        if (in_array($extension, array('xls', 'xlsx'), true)) {
            return $this->parse_excel_rows($file_path);
        }

        return array();
    }

    private function normalize_intent($value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 100) {
            $value = mb_substr($value, 0, 100);
        }

        return $value;
    }

    private function normalize_nullable_int($value) {
        if ($value === null) {
            return null;
        }

        $value = is_string($value) ? trim($value) : (is_numeric($value) ? (string) $value : '');
        if ($value === '') {
            return null;
        }

        $value = str_replace(array(',', ' '), '', $value);
        if (!is_numeric($value)) {
            return null;
        }

        $int = (int) $value;
        return $int >= 0 ? $int : null;
    }

    private function normalize_nullable_decimal($value) {
        if ($value === null) {
            return null;
        }

        $value = is_string($value) ? trim($value) : (is_numeric($value) ? (string) $value : '');
        if ($value === '') {
            return null;
        }

        $value = str_replace(array(',', ' '), '', $value);
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function normalize_serp_features($value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 2000) {
            $value = mb_substr($value, 0, 2000);
        }

        return $value;
    }

    private function normalize_keyword($value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }

        // Normalize common invisible characters / unicode whitespace to a plain space.
        $value = str_replace(
            array("\xEF\xBB\xBF", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xC2\xA0", "\xE2\x80\xAF"),
            ' ',
            $value
        );
        $value = preg_replace('/[\\p{Z}\\s]+/u', ' ', $value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) > 255) {
            $value = mb_substr($value, 0, 255);
        }

        return $value;
    }

    private function normalize_keyword_key($value) {
        $value = $this->normalize_keyword($value);
        if ($value === '') {
            return '';
        }
        return mb_strtolower($value);
    }

    private function normalize_header_label($value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\\s+/u', ' ', $value);
        $value = trim($value);
        $value = mb_strtolower($value);
        $value = str_replace(array('"', "'", '’', '“', '”'), '', $value);
        return $value;
    }

    private function detect_sheet_header_map($header_row) {
        if (!is_array($header_row) || empty($header_row)) {
            return null;
        }

        $aliases = array(
            'keyword' => array('keyword', 'keywords', 'ph', 'کلمه', 'کلمه کلیدی', 'کلمات کلیدی'),
            'intent' => array('intent', 'intents', 'in', 'هدف'),
            'volume' => array('volume', 'search volume', 'nq', 'حجم'),
            'keyword_difficulty' => array('keyword difficulty', 'keyword difficulty index', 'difficulty', 'kd', 'سختی', 'keyword_difficulty'),
            'cpc_usd' => array('cpc (usd)', 'cpc usd', 'cp', 'cpc', 'cpc($)', 'cpc(usd)', 'cpc-usd', 'cpc_usd', 'cpc (usd$)', 'cpc(usd$)'),
            // Semrush column headers: "SERP Features" (Fp) and "Keywords SERP Features" (Fk).
            'serp_features' => array('serp features', 'serp feature', 'keywords serp features', 'keyword serp features', 'fk', 'fp', 'serp', 'ویژگی‌های serp', 'ویژگیهای serp', 'ویژگی های serp', 'ویژگی‌های سرپ', 'ویژگیهای سرپ'),
        );

        $map = array();
        $serp_by_keyword_idx = null;
        $serp_by_position_idx = null;
        foreach ($header_row as $idx => $cell) {
            $label = $this->normalize_header_label(is_string($cell) ? $cell : (is_numeric($cell) ? (string) $cell : ''));
            if ($label === '') {
                continue;
            }

            // Semrush v3 Analytics sometimes returns explicit SERP-feature headers.
            if ($label === 'serp features by keyword' || $label === 'keywords serp features') {
                $serp_by_keyword_idx = (int) $idx;
                continue;
            }
            if ($label === 'serp features by position' || $label === 'serp features') {
                $serp_by_position_idx = (int) $idx;
                continue;
            }

            foreach ($aliases as $key => $values) {
                if (isset($map[$key])) {
                    continue;
                }

                foreach ($values as $alias) {
                    if ($label === $alias) {
                        $map[$key] = (int) $idx;
                        break 2;
                    }
                }
            }
        }

        if (!isset($map['keyword'])) {
            return null;
        }

        // Prefer SERP feature list (by keyword) over count (by position).
        if ($serp_by_keyword_idx !== null) {
            $map['serp_features'] = $serp_by_keyword_idx;
        } elseif ($serp_by_position_idx !== null && !isset($map['serp_features'])) {
            $map['serp_features'] = $serp_by_position_idx;
        }

        return $map;
    }

    private function parse_csv_rows($file_path) {
        $rows = array();

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        global $wp_filesystem;
        if (!is_object($wp_filesystem)) {
            WP_Filesystem();
        }

        $fs = $wp_filesystem;
        if (!is_object($fs) || !method_exists($fs, 'get_contents_array')) {
            if (!class_exists('WP_Filesystem_Direct')) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
                require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            }
            if (class_exists('WP_Filesystem_Direct')) {
                $fs = new WP_Filesystem_Direct(null);
            }
        }

        if (!is_object($fs) || !method_exists($fs, 'get_contents_array')) {
            return array();
        }

        $lines = $fs->get_contents_array($file_path);
        if (!is_array($lines) || empty($lines)) {
            return array();
        }

        $header_map = null;
        $row_index = 0;
        foreach ($lines as $line) {
            $row_index++;
            $line = is_string($line) ? $line : '';
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            $row = str_getcsv($line, ',');
            if (!is_array($row) || empty($row)) {
                continue;
            }

            if ($row_index === 1) {
                // Strip UTF-8 BOM if present.
                if (isset($row[0]) && is_string($row[0])) {
                    $row[0] = preg_replace('/^\\xEF\\xBB\\xBF/u', '', $row[0]);
                }
                $header_map = $this->detect_sheet_header_map($row);
                if (is_array($header_map)) {
                    continue;
                }
            }

            $kw_col = (is_array($header_map) && isset($header_map['keyword'])) ? (int) $header_map['keyword'] : 0;
            $kw_raw = isset($row[$kw_col]) ? (string) $row[$kw_col] : '';
            $kw_raw = trim($kw_raw);

            $kw = $this->normalize_keyword($kw_raw);
            if ($kw === '') {
                continue;
            }

            $rows[] = array(
                'keyword' => $kw,
                'intent' => $this->normalize_intent(isset($row[(is_array($header_map) && isset($header_map['intent'])) ? (int) $header_map['intent'] : 1]) ? (string) $row[(is_array($header_map) && isset($header_map['intent'])) ? (int) $header_map['intent'] : 1] : ''),
                'volume' => $this->normalize_nullable_int(isset($row[(is_array($header_map) && isset($header_map['volume'])) ? (int) $header_map['volume'] : 2]) ? $row[(is_array($header_map) && isset($header_map['volume'])) ? (int) $header_map['volume'] : 2] : null),
                'keyword_difficulty' => $this->normalize_nullable_decimal(isset($row[(is_array($header_map) && isset($header_map['keyword_difficulty'])) ? (int) $header_map['keyword_difficulty'] : 3]) ? $row[(is_array($header_map) && isset($header_map['keyword_difficulty'])) ? (int) $header_map['keyword_difficulty'] : 3] : null),
                'cpc_usd' => $this->normalize_nullable_decimal(isset($row[(is_array($header_map) && isset($header_map['cpc_usd'])) ? (int) $header_map['cpc_usd'] : 4]) ? $row[(is_array($header_map) && isset($header_map['cpc_usd'])) ? (int) $header_map['cpc_usd'] : 4] : null),
                'serp_features' => $this->normalize_serp_features(isset($row[(is_array($header_map) && isset($header_map['serp_features'])) ? (int) $header_map['serp_features'] : 5]) ? (string) $row[(is_array($header_map) && isset($header_map['serp_features'])) ? (int) $header_map['serp_features'] : 5] : ''),
            );
        }

        $by_keyword = array();
        foreach ($rows as $r) {
            $by_keyword[(string) $r['keyword']] = $r;
        }
        $rows = array_values($by_keyword);
        if (count($rows) > 5000) {
            $rows = array_slice($rows, 0, 5000);
        }

        return $rows;
    }

    private function parse_excel_rows($file_path) {
        if (!class_exists('SimpleXLSX')) {
            $lib = dirname(__DIR__) . '/keyword-research/lib/simple_xlsx.php';
            if (file_exists($lib)) {
                require_once $lib;
            }
        }

        if (!class_exists('SimpleXLSX')) {
            return array();
        }

        $xlsx = SimpleXLSX::parse($file_path);
        if (!$xlsx) {
            return array();
        }

        $rows = array();
        $header_map = null;
        foreach ($xlsx->rows() as $index => $row) {
            if (!is_array($row) || empty($row)) {
                continue;
            }

            if ((int) $index === 0) {
                $header_map = $this->detect_sheet_header_map($row);
                if (is_array($header_map)) {
                    continue;
                }
            }

            $kw_col = (is_array($header_map) && isset($header_map['keyword'])) ? (int) $header_map['keyword'] : 0;
            $kw_raw = isset($row[$kw_col]) ? (string) $row[$kw_col] : '';
            $kw_raw = trim($kw_raw);

            $kw = $this->normalize_keyword($kw_raw);
            if ($kw === '') {
                continue;
            }

            $rows[] = array(
                'keyword' => $kw,
                'intent' => $this->normalize_intent(isset($row[(is_array($header_map) && isset($header_map['intent'])) ? (int) $header_map['intent'] : 1]) ? (string) $row[(is_array($header_map) && isset($header_map['intent'])) ? (int) $header_map['intent'] : 1] : ''),
                'volume' => $this->normalize_nullable_int(isset($row[(is_array($header_map) && isset($header_map['volume'])) ? (int) $header_map['volume'] : 2]) ? $row[(is_array($header_map) && isset($header_map['volume'])) ? (int) $header_map['volume'] : 2] : null),
                'keyword_difficulty' => $this->normalize_nullable_decimal(isset($row[(is_array($header_map) && isset($header_map['keyword_difficulty'])) ? (int) $header_map['keyword_difficulty'] : 3]) ? $row[(is_array($header_map) && isset($header_map['keyword_difficulty'])) ? (int) $header_map['keyword_difficulty'] : 3] : null),
                'cpc_usd' => $this->normalize_nullable_decimal(isset($row[(is_array($header_map) && isset($header_map['cpc_usd'])) ? (int) $header_map['cpc_usd'] : 4]) ? $row[(is_array($header_map) && isset($header_map['cpc_usd'])) ? (int) $header_map['cpc_usd'] : 4] : null),
                'serp_features' => $this->normalize_serp_features(isset($row[(is_array($header_map) && isset($header_map['serp_features'])) ? (int) $header_map['serp_features'] : 5]) ? (string) $row[(is_array($header_map) && isset($header_map['serp_features'])) ? (int) $header_map['serp_features'] : 5] : ''),
            );
        }

        $by_keyword = array();
        foreach ($rows as $r) {
            $by_keyword[(string) $r['keyword']] = $r;
        }
        $rows = array_values($by_keyword);
        if (count($rows) > 5000) {
            $rows = array_slice($rows, 0, 5000);
        }

        return $rows;
    }

    public function ajax_get_competitor_keywords() {
        check_ajax_referer('SMARK_keyword_gap_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $competitor_id = isset($_GET['competitor_id']) ? (int) $_GET['competitor_id'] : 0;
        if ($competitor_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid competitor.', 'smark')));
        }

        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $q = is_string($q) ? trim($q) : '';

        $keywords = array();
        $total = 0;
        $used_map = array();
        $project_id = $this->get_current_project_id();

        if ($this->is_tables_available()) {
            global $wpdb;

            $keywords_table_sql = $this->escape_db_identifier($this->keywords_table);
            if ($keywords_table_sql === '') {
                wp_send_json_error(array('message' => __('Database table is not available.', 'smark')));
            }

            $sql_total = 'SELECT COUNT(*) FROM ' . $keywords_table_sql . ' WHERE competitor_id = %d';
            $sql_rows = 'SELECT keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features FROM ' . $keywords_table_sql . ' WHERE competitor_id = %d';
            $args = array($competitor_id);
            if ($q !== '') {
                $sql_total .= ' AND keyword LIKE %s';
                $sql_rows .= ' AND keyword LIKE %s';
                $args[] = '%' . $wpdb->esc_like($q) . '%';
            }
            $sql_rows .= ' ORDER BY keyword ASC LIMIT 500';

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = (int) $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->prepare($sql_total, ...$args) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            );

            $results = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->prepare($sql_rows, ...$args), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            foreach ((array) $results as $row) {
                if (isset($row['keyword'])) {
                    $keywords[] = array(
                        'keyword' => (string) $row['keyword'],
                        'intent' => isset($row['intent']) ? (string) $row['intent'] : null,
                        'volume' => isset($row['volume']) && $row['volume'] !== null ? (int) $row['volume'] : null,
                        'keyword_difficulty' => isset($row['keyword_difficulty']) && $row['keyword_difficulty'] !== null ? (float) $row['keyword_difficulty'] : null,
                        'cpc_usd' => isset($row['cpc_usd']) && $row['cpc_usd'] !== null ? (float) $row['cpc_usd'] : null,
                        'serp_features' => isset($row['serp_features']) ? (string) $row['serp_features'] : null,
                    );
                }
            }
        } else {
            $all = $this->get_option_keywords($competitor_id);
            $all = is_array($all) ? $all : array();

            if ($q !== '') {
                $filtered = array();
                foreach ($all as $row) {
                    $kw = is_array($row) && isset($row['keyword']) ? (string) $row['keyword'] : '';
                    if ($kw !== '' && stripos($kw, $q) !== false) {
                        $filtered[] = $row;
                    }
                }
                $all = $filtered;
            }

            $total = count($all);
            usort($all, function ($a, $b) {
                $ak = is_array($a) && isset($a['keyword']) ? (string) $a['keyword'] : '';
                $bk = is_array($b) && isset($b['keyword']) ? (string) $b['keyword'] : '';
                return strcmp($ak, $bk);
            });
            $keywords = array_slice($all, 0, 500);
        }

        if (!empty($keywords) && $project_id > 0) {
            $kw_list = array();
            foreach ($keywords as $row) {
                if (is_array($row) && isset($row['keyword'])) {
                    $kw_list[] = (string) $row['keyword'];
                }
            }
            $used_map = $this->get_used_keywords_map($project_id, $kw_list);
            $used_any_map = $this->get_used_keywords_global_map($kw_list);
            $used_bank_map = $this->get_used_keywords_bank_map($kw_list);
        } elseif (!empty($keywords)) {
            $kw_list = array();
            foreach ($keywords as $row) {
                if (is_array($row) && isset($row['keyword'])) {
                    $kw_list[] = (string) $row['keyword'];
                }
            }
            $used_map = $this->get_used_keywords_global_map($kw_list);
            $used_any_map = $used_map;
            $used_bank_map = $this->get_used_keywords_bank_map($kw_list);
        }

        if (!empty($keywords)) {
            foreach ($keywords as &$row) {
                if (is_array($row) && isset($row['keyword'])) {
                    $lookup = strtolower($this->normalize_keyword((string) $row['keyword']));
                    $row['used_in_project'] = isset($used_map[$lookup]);
                    $row['used_in_keyword_research'] = $row['used_in_project'] || isset($used_any_map[$lookup]) || isset($used_bank_map[$lookup]);
                }
            }
            unset($row);
        }

        $inappropriate_map = $this->get_option_inappropriate_map($competitor_id);
        if (!empty($keywords)) {
            foreach ($keywords as &$row) {
                if (is_array($row) && isset($row['keyword'])) {
                    $key = $this->normalize_keyword_key((string) $row['keyword']);
                    $row['inappropriate'] = ($key !== '' && isset($inappropriate_map[$key]));
                }
            }
            unset($row);
        }

        $out = array(
            'total' => $total,
            'keywords' => $keywords,
        );
        if (current_user_can('manage_options')) {
            $project_keywords_set = $project_id > 0 ? $this->get_project_keywords_normalized_set($project_id) : array();
            $debug_like = array();
            if ($project_id > 0 && $q !== '') {
                global $wpdb;
                $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
                $tables = array_unique(array_filter(array(
                    $this->project_keywords_table,
                    $prefix !== '' ? ($prefix . 'SMARK_keyword_research') : '',
                    $prefix !== '' ? ($prefix . 'smark_keyword_research') : '',
                )));

                foreach ($tables as $table) {
                    if (!$this->table_exists($table)) {
                        continue;
                    }
                    $table_sql = $this->escape_db_identifier($table);
                    if ($table_sql === '') {
                        continue;
                    }
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $debug_like[$table] = $wpdb->get_col( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                        $wpdb->prepare(
                            'SELECT keyword FROM ' . $table_sql . ' WHERE project_id = %d AND keyword LIKE %s LIMIT 20', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                            (int) $project_id,
                            '%' . $wpdb->esc_like($q) . '%'
                        )
                    );
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                }
            }

            $out['debug'] = array(
                'project_id' => (int) $project_id,
                'projects_table' => (string) $this->projects_table,
                'project_keywords_table' => (string) $this->project_keywords_table,
                'project_keywords_set_count' => is_array($project_keywords_set) ? count($project_keywords_set) : 0,
                'keyword_research_like' => $debug_like,
            );
        }
        wp_send_json_success($out);
    }

    private function fetch_smart_transfer_candidates($limit = 200) {
        $limit = (int) $limit;
        if ($limit <= 0) {
            $limit = 200;
        }

        $rows = array();

        if ($this->is_tables_available()) {
            if (!$this->keywords_table || !$this->table_exists($this->keywords_table)) {
                return array();
            }

            global $wpdb;
            $keywords_table_sql = $this->escape_db_identifier($this->keywords_table);
            if ($keywords_table_sql === '') {
                return array();
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->prepare(
                    'SELECT competitor_id, keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features FROM ' . $keywords_table_sql . ' ORDER BY volume DESC, keyword_difficulty ASC, id DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $limit
                ),
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        } else {
            foreach ($this->get_option_competitors() as $c) {
                $cid = is_array($c) && isset($c['id']) ? (int) $c['id'] : 0;
                if ($cid <= 0) {
                    continue;
                }
                foreach ($this->get_option_keywords($cid) as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $r['competitor_id'] = $cid;
                    $rows[] = $r;
                    if (count($rows) >= $limit) {
                        break 2;
                    }
                }
            }

            usort($rows, function($a, $b) {
                $va = (is_array($a) && isset($a['volume']) && $a['volume'] !== null) ? (int) $a['volume'] : 0;
                $vb = (is_array($b) && isset($b['volume']) && $b['volume'] !== null) ? (int) $b['volume'] : 0;
                return $vb <=> $va;
            });
        }

        return is_array($rows) ? $rows : array();
    }

    private function insert_project_keyword_from_gap_row($project_id, $row_data) {
        $project_id = (int) $project_id;
        if ($project_id <= 0) {
            return array('ok' => false, 'message' => 'Invalid project');
        }

        if (!$this->project_keywords_table || !$this->table_exists($this->project_keywords_table)) {
            return array('ok' => false, 'message' => __('Keyword research table is not available.', 'smark'));
        }

        if (!is_array($row_data) || empty($row_data['keyword'])) {
            return array('ok' => false, 'message' => __('Keyword not found.', 'smark'));
        }

        $keyword = $this->normalize_keyword((string) $row_data['keyword']);
        if ($keyword === '') {
            return array('ok' => false, 'message' => __('Keyword not found.', 'smark'));
        }

        $project_name = $this->get_project_name_by_id($project_id);
        if ($project_name === '') {
            $project_name = 'Project';
        }

        global $wpdb;
        $insert = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->project_keywords_table,
            array(
                'project_id' => $project_id,
                'project_name' => $project_name,
                'keyword_bank_id' => null,
                'keyword' => $keyword,
                'intent' => $this->interpret_intent_value(isset($row_data['intent']) ? (string) $row_data['intent'] : '', $this->get_panel_language()),
                'volume' => isset($row_data['volume']) && $row_data['volume'] !== null ? (int) $row_data['volume'] : null,
                'keyword_difficulty' => isset($row_data['keyword_difficulty']) && $row_data['keyword_difficulty'] !== null ? (float) $row_data['keyword_difficulty'] : null,
                'cpc_usd' => isset($row_data['cpc_usd']) && $row_data['cpc_usd'] !== null ? (float) $row_data['cpc_usd'] : null,
                'serp_features' => $this->interpret_serp_features_value(isset($row_data['serp_features']) ? (string) $row_data['serp_features'] : '', $this->get_panel_language()),
                'page_link_status' => 'not_checked',
                'page_link_url' => null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s')
        );

        if ($insert === false) {
            return array('ok' => false, 'message' => __('Insert failed.', 'smark'));
        }

        update_option('smark_daily_guide_keyword_gap_transfer_' . (string) (int) $project_id, current_time('Y-m-d'), false);

        return array('ok' => true, 'keyword' => $keyword);
    }

    public function smart_transfer_one_keyword($project_id) {
        $lang = $this->get_panel_language();
        $project_id = (int) $project_id;
        $stage = 'input';

        if ($project_id <= 0) {
            $msg = ($lang === 'fa') ? 'پروژه انتخاب نشده است.' : 'Project is not selected.';
            return array('ok' => false, 'message' => $msg, 'stage' => $stage);
        }

        $stage = 'kw_research_table';
        if (!$this->project_keywords_table || !$this->table_exists($this->project_keywords_table)) {
            $msg = ($lang === 'fa') ? 'جدول تحقیق کلمات کلیدی آماده نیست.' : 'Keyword Research table is not ready.';
            return array('ok' => false, 'message' => $msg, 'stage' => $stage);
        }

        $stage = 'candidates';
        $candidates = $this->fetch_smart_transfer_candidates(250);
        if (empty($candidates)) {
            $msg = ($lang === 'fa') ? 'هیچ کلمه کلیدی‌ای در Keyword Gap پیدا نشد.' : 'No Keyword Gap keywords were found.';
            return array('ok' => false, 'message' => $msg, 'stage' => $stage);
        }

        $keywords = array();
        foreach ($candidates as $r) {
            if (is_array($r) && isset($r['keyword']) && $r['keyword'] !== '') {
                $keywords[] = (string) $r['keyword'];
            }
        }

        $used_project = $this->get_used_keywords_map($project_id, $keywords);
        $used_global = $this->get_used_keywords_global_map($keywords);
        $used_bank = $this->get_used_keywords_bank_map($keywords);

        foreach ($candidates as $r) {
            if (!is_array($r)) {
                continue;
            }

            $cid = isset($r['competitor_id']) ? (int) $r['competitor_id'] : 0;
            $kw = isset($r['keyword']) ? $this->normalize_keyword((string) $r['keyword']) : '';
            if ($cid <= 0 || $kw === '') {
                continue;
            }

            $lookup = strtolower($kw);
            $inappropriate = $this->get_option_inappropriate_map($cid);
            if (isset($inappropriate[$lookup])) {
                continue;
            }

            if (isset($used_project[$lookup]) || isset($used_global[$lookup]) || isset($used_bank[$lookup])) {
                continue;
            }

            $stage = 'insert';
            $ins = $this->insert_project_keyword_from_gap_row($project_id, $r);
            if (is_array($ins) && !empty($ins['ok'])) {
                return array('ok' => true, 'keyword' => isset($ins['keyword']) ? (string) $ins['keyword'] : $kw);
            }
        }

        $msg = ($lang === 'fa') ? 'کلمه کلیدی قابل انتقال پیدا نشد (احتمالاً همه قبلاً استفاده شده‌اند).' : 'No transferable keyword was found (all may already be used).';
        return array('ok' => false, 'message' => $msg, 'stage' => $stage);
    }

    public function ajax_use_keyword() {
        check_ajax_referer('SMARK_keyword_gap_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $competitor_id = isset($_POST['competitor_id']) ? (int) $_POST['competitor_id'] : 0;
        $keyword_raw = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $keyword = $this->normalize_keyword($keyword_raw);

        if ($competitor_id <= 0 || $keyword === '') {
            wp_send_json_error(array('message' => __('Invalid request.', 'smark')));
        }

        $project_id = $this->get_current_project_id();
        if ($project_id <= 0) {
            wp_send_json_error(array('message' => __('Project is not available.', 'smark')));
        }

        if (!$this->project_keywords_table || !$this->table_exists($this->project_keywords_table)) {
            wp_send_json_error(array('message' => __('Keyword research table is not available.', 'smark')));
        }

        $project_name = $this->get_project_name_by_id($project_id);
        if ($project_name === '') {
            $project_name = 'Project';
        }

        $row_data = null;
        if ($this->is_tables_available()) {
            global $wpdb;
            $keywords_table_sql = $this->escape_db_identifier($this->keywords_table);
            if ($keywords_table_sql === '') {
                wp_send_json_error(array('message' => __('Database table is not available.', 'smark')));
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $row_data = $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->prepare(
                    'SELECT keyword, intent, volume, keyword_difficulty, cpc_usd, serp_features FROM ' . $keywords_table_sql . ' WHERE competitor_id = %d AND keyword = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $competitor_id,
                    $keyword
                ),
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        } else {
            foreach ($this->get_option_keywords($competitor_id) as $r) {
                if (is_array($r) && isset($r['keyword']) && strtolower((string) $r['keyword']) === strtolower($keyword)) {
                    $row_data = $r;
                    break;
                }
            }
        }

        if (!is_array($row_data) || empty($row_data['keyword'])) {
            wp_send_json_error(array('message' => __('Keyword not found.', 'smark')));
        }

        global $wpdb;
        $project_keywords_table_sql = $this->escape_db_identifier($this->project_keywords_table);
        if ($project_keywords_table_sql === '') {
            wp_send_json_error(array('message' => __('Keyword research table is not available.', 'smark')));
        }

        $lookup = strtolower($this->normalize_keyword($keyword));
        $project_keywords_set = $this->get_project_keywords_normalized_set($project_id);
        $exists = isset($project_keywords_set[$lookup]) ? 1 : 0;
        if (!$exists) {
            $exists = isset($this->get_used_keywords_global_map(array($keyword))[$lookup]) ? 1 : 0;
        }
        if (!$exists) {
            $exists = isset($this->get_used_keywords_bank_map(array($keyword))[$lookup]) ? 1 : 0;
        }

        if ($exists > 0) {
            wp_send_json_success(array('inserted' => 0, 'already' => true));
        }

        $insert = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->project_keywords_table,
            array(
                'project_id' => $project_id,
                'project_name' => $project_name,
                'keyword_bank_id' => null,
                'keyword' => $keyword,
                'intent' => $this->interpret_intent_value(isset($row_data['intent']) ? (string) $row_data['intent'] : '', $this->get_panel_language()),
                'volume' => isset($row_data['volume']) && $row_data['volume'] !== null ? (int) $row_data['volume'] : null,
                'keyword_difficulty' => isset($row_data['keyword_difficulty']) && $row_data['keyword_difficulty'] !== null ? (float) $row_data['keyword_difficulty'] : null,
                'cpc_usd' => isset($row_data['cpc_usd']) && $row_data['cpc_usd'] !== null ? (float) $row_data['cpc_usd'] : null,
                'serp_features' => $this->interpret_serp_features_value(isset($row_data['serp_features']) ? (string) $row_data['serp_features'] : '', $this->get_panel_language()),
                'page_link_status' => 'not_checked',
                'page_link_url' => null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s')
        );

        if ($insert === false) {
            wp_send_json_error(array('message' => __('Insert failed.', 'smark')));
        }

        // Track today's transfer for Daily Guide on dashboard (per-project, no schema changes).
        update_option('smark_daily_guide_keyword_gap_transfer_' . (string) (int) $project_id, current_time('Y-m-d'), false);

        wp_send_json_success(array('inserted' => 1));
    }

    public function ajax_mark_inappropriate() {
        check_ajax_referer('SMARK_keyword_gap_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $competitor_id = isset($_POST['competitor_id']) ? (int) $_POST['competitor_id'] : 0;
        $keyword_raw = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $keyword = $this->normalize_keyword($keyword_raw);

        if ($competitor_id <= 0 || $keyword === '') {
            wp_send_json_error(array('message' => __('Invalid request.', 'smark')));
        }

        $ok = $this->add_inappropriate_keyword($competitor_id, $keyword);
        if (!$ok) {
            wp_send_json_error(array('message' => __('Invalid request.', 'smark')));
        }

        wp_send_json_success(array('ok' => true));
    }

    /**
     * Get localized strings.
     *
     * @param string $lang Language code.
     *
     * @return array
     */
    private function get_strings($lang) {
        $lang = ($lang === 'fa') ? 'fa' : 'en';

        $strings = array(
            'en' => array(
                'page_title' => 'Keyword Gap',
                'page_subtitle' => 'Compare your website with competitors and find missing keyword opportunities.',
                'breadcrumb_dashboard' => 'Dashboard',
                'breadcrumb_seo' => 'SEO Management',
                'breadcrumb_current' => 'Keyword Gap',
                'add_competitor' => 'Add new competitor',
                'table_domain' => 'Competitor domain',
                'table_competitor_keywords' => 'Competitor keywords',
                'table_keyword' => 'Keyword',
                'table_intent' => 'Intent',
                'table_volume' => 'Volume',
                'table_difficulty' => 'Difficulty',
                'table_cpc' => 'CPC',
                'table_serp' => 'SERP features',
                'table_actions' => 'Actions',
                'gap_finder' => 'Keyword Gap Finder',
                'gap_finder_success' => 'Keywords fetched successfully. Added {inserted} new keywords.',
                'gap_finder_modal_title' => 'Keyword Gap Finder',
                'gap_finder_modal_hint' => 'Set optional filters and request the top 10 competitor keywords from Semrush.',
                'gap_finder_volume_min_label' => 'Minimum Search Volume',
                'gap_finder_min' => 'Min',
                'gap_finder_submit' => 'Send request',
                'mark_cost_tooltip' => 'Includes 1 Mark',
                'mark_insufficient' => 'You don\'t have enough Mark credits.',
                'no_results_filter' => 'Your selected filter does not match any data. Please change the filter and try again.',
                'use_keyword' => 'Use',
                'used_label' => 'Used',
                'inappropriate_button' => 'Not suitable',
                'inappropriate_label' => 'Not suitable',
                'inappropriate_success' => 'Marked as not suitable.',
                'use_success' => 'Keyword added to keyword research.',
                'upload_keywords' => 'Upload competitor keywords',
                'update_keywords' => 'Update keywords',
                'view_keywords' => 'View keywords',
                'search_placeholder' => 'Search keywords…',
                'search_button' => 'Search',
                'empty_title' => 'No competitors yet',
                'empty_subtitle' => 'Click “Add new competitor” to begin.',
                'empty_keywords_title' => 'No keywords yet',
                'add_competitor_modal_title' => 'Add competitors',
                'add_competitor_modal_hint' => 'Enter competitor domains (one per line). You can paste URLs too.',
                'add_competitor_modal_placeholder' => "example.com\ncompetitor.com",
                'upload_modal_title' => 'Upload keywords',
                'upload_modal_description' => 'Upload a CSV or Excel file. The first column should be the keyword list (header “Keyword” is optional).',
                'upload_modal_hint' => 'Maximum file size follows your WordPress upload limit.',
                'upload_button' => 'Upload file',
                'error_missing_file' => 'Please choose a file to upload.',
                'upload_error' => 'Upload failed. Please try again.',
                'view_modal_title' => 'Competitor keywords',
                'save_button' => 'Save',
                'close_button' => 'Close',
                'domain_label' => 'Domain',
                'competitors_saved' => 'Competitors saved.',
                'error' => 'Error',
                'upload_success' => 'Keywords saved.',
            ),
            'fa' => array(
                'page_title' => 'شکاف کلمات کلیدی',
                'page_subtitle' => 'سایت خود را با رقبا مقایسه کنید و فرصت‌های کلمات کلیدی از دست‌رفته را پیدا کنید.',
                'breadcrumb_dashboard' => 'داشبورد',
                'breadcrumb_seo' => 'مدیریت سئو',
                'breadcrumb_current' => 'شکاف کلمات کلیدی',
                'add_competitor' => 'افزودن رقیب جدید',
                'table_domain' => 'دامنه رقیب',
                'table_competitor_keywords' => 'کلمات کلیدی رقبا',
                'table_keyword' => 'کلمه کلیدی',
                'table_intent' => 'هدف',
                'table_volume' => 'حجم',
                'table_difficulty' => 'سختی',
                'table_cpc' => 'CPC',
                'table_serp' => 'ویژگی‌های SERP',
                'table_actions' => 'عملیات',
                'gap_finder' => 'Keyword Gap Finder',
                'gap_finder_success' => 'کلمات کلیدی با موفقیت دریافت شدند. {inserted} کلمه جدید اضافه شد.',
                'gap_finder_modal_title' => 'Keyword Gap Finder',
                'gap_finder_modal_hint' => 'بازه‌های فیلتر را (اختیاری) مشخص کنید و ۱۰ کلمه کلیدی برتر رقیب را از سمراش دریافت کنید.',
                'gap_finder_volume_min_label' => 'حداقل حجم جست‌وجو',
                'gap_finder_min' => 'حداقل',
                'gap_finder_submit' => 'ارسال درخواست',
                'mark_cost_tooltip' => 'شامل ۱ مارک',
                'mark_insufficient' => 'مارک به اندازه کافی ندارید.',
                'no_results_filter' => 'فیلتر انتخاب شده شما شامل هیچ داده ای نمی\u200cشود. لطفاً فیلتر را تغییر دهید و مجدد امتحان کنید.',
                'use_keyword' => 'استفاده کنید',
                'used_label' => 'استفاده شده',
                'use_success' => 'کلمه کلیدی به تحقیق کلمات کلیدی اضافه شد.',
                'upload_keywords' => 'وارد کردن کلمات کلیدی رقبا',
                'update_keywords' => 'بروزرسانی کلمات کلیدی',
                'view_keywords' => 'مشاهده کلمات کلیدی',
                'search_placeholder' => 'جستجو در کلمات کلیدی…',
                'search_button' => 'جستجو',
                'empty_title' => 'هنوز رقیبی اضافه نشده است',
                'empty_subtitle' => 'برای شروع روی «افزودن رقیب جدید» کلیک کنید.',
                'empty_keywords_title' => 'هنوز کلمه کلیدی‌ای اضافه نشده است',
                'add_competitor_modal_title' => 'افزودن رقیب‌ها',
                'add_competitor_modal_hint' => 'دامنه سایت رقبا را هر خط یک مورد وارد کنید (می‌توانید URL هم Paste کنید).',
                'add_competitor_modal_placeholder' => "example.com\ncompetitor.com",
                'upload_modal_title' => 'وارد کردن کلمات کلیدی رقبا',
                'upload_modal_description' => 'فایل CSV یا Excel را آپلود کنید. ستون اول باید لیست کلمات کلیدی باشد (سربرگ «Keyword» اختیاری است).',
                'upload_modal_hint' => 'حداکثر اندازه فایل مطابق تنظیمات وردپرس شماست.',
                'upload_button' => 'آپلود فایل',
                'error_missing_file' => 'لطفاً یک فایل برای آپلود انتخاب کنید.',
                'upload_error' => 'آپلود با خطا مواجه شد. لطفاً دوباره تلاش کنید.',
                'view_modal_title' => 'کلمات کلیدی رقیب',
                'save_button' => 'ذخیره',
                'close_button' => 'بستن',
                'domain_label' => 'دامنه',
                'competitors_saved' => 'رقبا ذخیره شدند.',
                'error' => 'خطا',
                'upload_success' => 'کلمات کلیدی ذخیره شدند.',
            ),
        );

        $strings['en']['difficulty_filter_min'] = 'Min';
        $strings['en']['difficulty_filter_max'] = 'Max';
        $strings['en']['difficulty_sort_asc'] = 'Low to high';
        $strings['en']['difficulty_sort_desc'] = 'High to low';
        $strings['en']['difficulty_filter_reset'] = 'Reset';

        $strings['fa']['difficulty_filter_min'] = 'حداقل';
        $strings['fa']['difficulty_filter_max'] = 'حداکثر';
        $strings['fa']['difficulty_sort_asc'] = 'کم به زیاد';
        $strings['fa']['difficulty_sort_desc'] = 'زیاد به کم';
        $strings['fa']['difficulty_filter_reset'] = 'پاک کردن';

        return $strings[$lang];
    }
}
