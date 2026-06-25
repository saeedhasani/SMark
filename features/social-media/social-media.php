<?php
/**
 * Social Media Design Feature
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

class SMarkSocialMedia {

    /**
     * Database table names
     */
    private $table_name;
    private $projects_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'SMARK_social_media';
        $this->projects_table = $this->resolve_projects_table();

        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX actions
        add_action('wp_ajax_SMARK_get_projects', array($this, 'ajax_get_projects'));
        add_action('wp_ajax_SMARK_create_project', array($this, 'ajax_create_project'));
        add_action('wp_ajax_SMARK_get_project_items', array($this, 'ajax_get_project_items'));
        add_action('wp_ajax_SMARK_get_project_suggestions', array($this, 'ajax_get_project_suggestions'));
        add_action('wp_ajax_SMARK_add_item', array($this, 'ajax_add_item'));
        add_action('wp_ajax_SMARK_update_item', array($this, 'ajax_update_item'));
        add_action('wp_ajax_SMARK_delete_item', array($this, 'ajax_delete_item'));
        add_action('wp_ajax_SMARK_get_item', array($this, 'ajax_get_item'));
        add_action('wp_ajax_SMARK_analyze_headline_quick', array($this, 'ajax_analyze_headline_quick'));
        add_action('wp_ajax_SMARK_save_analysis_results', array($this, 'ajax_save_analysis_results'));
        add_action('wp_ajax_SMARK_upload_visual', array($this, 'ajax_upload_visual'));
        add_action('wp_ajax_SMARK_save_language', array($this, 'ajax_save_language'));
        add_action('wp_ajax_SMARK_get_suggestion', array($this, 'ajax_get_suggestion'));
        add_action('wp_ajax_SMARK_update_suggestion', array($this, 'ajax_update_suggestion'));
        add_action('wp_ajax_SMARK_transfer_suggestion_to_item', array($this, 'ajax_transfer_suggestion_to_item'));
        add_action('wp_ajax_SMARK_delete_suggestion', array($this, 'ajax_delete_suggestion'));
        add_action('wp_ajax_SMARK_test_gemini_connection', array($this, 'ajax_test_gemini_connection'));
        add_action('wp_ajax_SMARK_generate_attractive_title', array($this, 'ajax_generate_attractive_title'));
        add_action('wp_ajax_SMARK_get_attractive_title_prompt', array($this, 'ajax_get_attractive_title_prompt'));
        add_action('wp_ajax_SMARK_get_visual_text_prompt', array($this, 'ajax_get_visual_text_prompt'));
        add_action('wp_ajax_SMARK_get_caption_prompt', array($this, 'ajax_get_caption_prompt'));
        add_action('wp_ajax_SMARK_sm_get_canva_template', array($this, 'ajax_get_canva_template'));

        // Check and create table if needed
        $this->maybe_create_table();
    }

    private function resolve_projects_table() {
        global $wpdb;

        $prefix = $wpdb->prefix;
        $candidates = array($prefix . 'SMARK_projects', $prefix . 'smark_projects');

        $existing = array();
        foreach ($candidates as $table) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($found === $table) {
                $existing[] = $table;
            }
        }

        if (count($existing) > 1) {
            // Prefer the table that has the website column (consistent with Project Settings behavior).
            foreach ($existing as $table) {
                $table_sql = $this->escape_db_identifier($table);
                if ($table_sql === '') {
                    continue;
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Table identifier is strictly validated; placeholders not supported for identifiers.
                $has_website = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', 'website'));
                if (!empty($has_website)) {
                    return $table;
                }
            }
        }

        if (!empty($existing)) {
            return $existing[0];
        }

        return $prefix . 'SMARK_projects';
    }

    private function get_gemini_app_instance() {
        global $smark_gemini_app, $SMARK_gemini_app;

        if (isset($smark_gemini_app) && $smark_gemini_app instanceof SMarkGeminiApp) {
            return $smark_gemini_app;
        }

        if (isset($SMARK_gemini_app) && $SMARK_gemini_app instanceof SMarkGeminiApp) {
            return $SMARK_gemini_app;
        }

        if (!class_exists('SMarkGeminiApp', false)) {
            // Best-effort include of Core implementation (handles load order).
            $core_path = defined('SMARK_CORE_PLUGIN_PATH')
                ? rtrim((string) SMARK_CORE_PLUGIN_PATH, '/\\') . '/features/gemini-app/gemini-app.php'
                : rtrim((string) WP_PLUGIN_DIR, '/\\') . '/SMark-Core/features/gemini-app/gemini-app.php';

            if (file_exists($core_path)) {
                require_once $core_path;
            }
        }

        if (!class_exists('SMarkGeminiApp', false)) {
            return null;
        }

        $instance = new SMarkGeminiApp();
        $smark_gemini_app = $instance;
        $SMARK_gemini_app = $instance;

        return $instance;
    }

    private function escape_db_identifier($identifier) {
        if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            return '';
        }

        return '`' . str_replace('`', '', esc_sql($identifier)) . '`';
    }

    private function table_has_column($table_name, $column) {
        global $wpdb;

        $table_sql = $this->escape_db_identifier($table_name);
        if ($table_sql === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Table identifier is strictly validated; placeholders not supported for identifiers.
        $found = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', (string) $column));
        return !empty($found);
    }

    private function get_current_site_project() {
        global $wpdb;

        // Ensure table/columns exist (backfills project_id when missing).
        $this->get_all_projects();

        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            return null;
        }

        $project_db_id = (int) get_option('smark_current_project_db_id', 0);
        if ($project_db_id <= 0) {
            $project_db_id = (int) get_option('SMARK_current_project_db_id', 0);
        }
        if ($project_db_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, project_name, project_id FROM {$projects_table_sql} WHERE id = %d", $project_db_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (is_array($row) && !empty($row)) {
                return $row;
            }
        }

        $website = rtrim((string) home_url('/'), '/');
        if ($website !== '' && $this->table_has_column($this->projects_table, 'website')) {
            $found_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$projects_table_sql} WHERE website = %s ORDER BY id DESC LIMIT 1", $website)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ($found_id > 0) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, project_name, project_id FROM {$projects_table_sql} WHERE id = %d", $found_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                if (is_array($row) && !empty($row) && isset($row['id'])) {
                    update_option('smark_current_project_db_id', (int) $row['id'], false);
                    return $row;
                }
            }
        }

        $row = $wpdb->get_row("SELECT id, project_name, project_id FROM {$projects_table_sql} ORDER BY id DESC LIMIT 1", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if (is_array($row) && !empty($row) && isset($row['id'])) {
            update_option('smark_current_project_db_id', (int) $row['id'], false);
            return $row;
        }

        return null;
    }

    private function log_debug($message, $context = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        if (class_exists('SMarkLogger', false)) {
            SMarkLogger::debug($message, $context);
        }
    }

    private function log_info($message, $context = array()) {
        if (class_exists('SMarkLogger', false)) {
            SMarkLogger::info($message, $context);
        }
    }

    private function log_warning($message, $context = array()) {
        if (class_exists('SMarkLogger', false)) {
            SMarkLogger::warning($message, $context);
        }
    }

    private function log_error($message, $context = array()) {
        if (class_exists('SMarkLogger', false)) {
            SMarkLogger::error($message, $context);
        }
    }

    /**
     * Check if table exists and create if needed
     */
    private function maybe_create_table() {
        global $wpdb;

        // Check Social Media table
        $sm_table_version = get_option('SMARK_social_media_table_version', '0');
        $current_version = '3.9'; // Updated version for published_link column in items table

        if ($sm_table_version !== $current_version) {
            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name));

            if ($table_exists != $this->table_name) {
                $this->create_table();
            } else {
                // Table exists, check if columns exist and add them if needed
                $this->migrate_table_columns();
            }
            update_option('SMARK_social_media_table_version', $current_version);
        }

        // Check Projects table
        $projects_table_version = get_option('SMARK_projects_table_version', '0');
        $projects_current_version = '1.0';

        if ($projects_table_version !== $projects_current_version) {
            $projects_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->projects_table));

            if ($projects_exists != $this->projects_table) {
                $this->create_projects_table();
                update_option('SMARK_projects_table_version', $projects_current_version);
            } else {
                update_option('SMARK_projects_table_version', $projects_current_version);
            }
        }

        // Check Suggestions table
        $suggestions_table_version = get_option('SMARK_suggestions_table_version', '0');
        $suggestions_current_version = '1.0';

        if ($suggestions_table_version !== $suggestions_current_version) {
            $this->migrate_suggestions_table();
            update_option('SMARK_suggestions_table_version', $suggestions_current_version);
        }
    }

    /**
     * Migrate table to add new columns if they don't exist
     */
    private function migrate_table_columns() {
        global $wpdb;

        $table_sql = $this->escape_db_identifier($this->table_name);
        if ($table_sql === '') {
            return;
        }

        // Check if verification_status column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'verification_status'));

        if (empty($column_exists)) {
            // Add verification_status column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN verification_status varchar(20) DEFAULT 'unverified' AFTER headline");
            $this->log_info('Added verification_status column', array('table' => $this->table_name));
        }

        // Check if visual column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $visual_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'visual'));

        if (empty($visual_exists)) {
            // Add visual column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN visual varchar(500) DEFAULT NULL AFTER headline");
            $this->log_info('Added visual column', array('table' => $this->table_name));
        }

        // Check if content_link column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $content_link_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'content_link'));

        if (empty($content_link_exists)) {
            // Add content_link column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN content_link varchar(1000) DEFAULT NULL AFTER visual");
            $this->log_info('Added content_link column', array('table' => $this->table_name));
        }

        // Check if published_link column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $published_link_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'published_link'));

        if (empty($published_link_exists)) {
            // Add published_link column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN published_link varchar(1000) DEFAULT NULL AFTER source");
            $this->log_info('Added published_link column', array('table' => $this->table_name));
        }

        // Check if score column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $score_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'score'));

        if (empty($score_exists)) {
            // Add score column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN score int(3) DEFAULT 0 AFTER verification_status");
            $this->log_info('Added score column', array('table' => $this->table_name));
        }

        // Check if caption column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $caption_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'caption'));

        if (empty($caption_exists)) {
            // Add caption column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN caption text DEFAULT NULL AFTER visual");
            $this->log_info('Added caption column', array('table' => $this->table_name));
        }

        // Check if visual_text column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $visual_text_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'visual_text'));

        if (empty($visual_text_exists)) {
            // Add visual_text column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN visual_text text DEFAULT NULL AFTER visual");
            $this->log_info('Added visual_text column', array('table' => $this->table_name));
        }

        // Check if headline_analysis_results column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $analysis_results_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'headline_analysis_results'));

        if (empty($analysis_results_exists)) {
            // Add headline_analysis_results column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN headline_analysis_results longtext DEFAULT NULL AFTER caption");
            $this->log_info('Added headline_analysis_results column', array('table' => $this->table_name));
        }

        // Check if expert_approval_status column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $expert_approval_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'expert_approval_status'));

        if (empty($expert_approval_exists)) {
            // Add expert_approval_status column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN expert_approval_status varchar(20) DEFAULT 'needs_approval' AFTER visual_text");
            $this->log_info('Added expert_approval_status column', array('table' => $this->table_name));
        }

        // Check if visual_type column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $visual_type_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'visual_type'));

        if (empty($visual_type_exists)) {
            // Add visual_type column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN visual_type varchar(50) DEFAULT NULL AFTER visual");
            $this->log_info('Added visual_type column', array('table' => $this->table_name));
        }

        // Check if source column exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $source_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", 'source'));

        if (empty($source_exists)) {
            // Add source column
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$table_sql} ADD COLUMN source varchar(500) DEFAULT NULL AFTER expert_approval_status");
            $this->log_info('Added source column', array('table' => $this->table_name));
        }

        // Update existing rows with calculated verification status using new scoring system
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $items = $wpdb->get_results("SELECT id, headline FROM {$table_sql}", ARRAY_A);

        // Don't automatically analyze headlines when loading items
        // Let users click the analyze button manually
    }

    /**
     * Create projects table
     */
    private function create_projects_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->projects_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project_name varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY project_name_unique (project_name)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta($sql);

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->projects_table));

        if ($table_exists == $this->projects_table) {
            $this->log_info('Projects table created successfully', array('table' => $this->projects_table));
        } else {
            $this->log_error('Failed to create projects table', array('table' => $this->projects_table));
        }
    }

    /**
     * Create database table
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project varchar(255) NOT NULL,
            headline text NOT NULL,
            visual varchar(500) DEFAULT NULL,
            content_link varchar(1000) DEFAULT NULL,
            visual_type varchar(50) DEFAULT NULL,
            visual_text text DEFAULT NULL,
            expert_approval_status varchar(20) DEFAULT 'needs_approval',
            source varchar(500) DEFAULT NULL,
            published_link varchar(1000) DEFAULT NULL,
            caption text DEFAULT NULL,
            headline_analysis_results longtext DEFAULT NULL,
            verification_status varchar(20) DEFAULT 'unverified',
            score int(3) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_index (project)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta($sql);

        // Verify table was created
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name));

        if ($table_exists == $this->table_name) {
            $this->log_info('Social media table created successfully', array('table' => $this->table_name));
        } else {
            $this->log_error('Failed to create social media table', array('table' => $this->table_name));
        }
    }

    /**
     * Migrate suggestions table to ensure all columns exist
     */
    private function migrate_suggestions_table() {
        global $wpdb;
        $suggestions_table = $wpdb->prefix . 'SMARK_social_media_suggestions';
        $suggestions_table_sql = $this->escape_db_identifier($suggestions_table);
        if ($suggestions_table_sql === '') {
            return;
        }

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $suggestions_table));

        if (!$table_exists) {
            $this->log_warning('Suggestions table does not exist, will be created by main plugin');
            return;
        }

        $this->log_info('Migrating suggestions table columns...');

        // Check and add missing columns
        $columns_to_check = array(
            'caption' => 'text DEFAULT NULL',
            'visual' => 'varchar(500) DEFAULT NULL',
            'visual_type' => 'varchar(50) DEFAULT NULL',
            'visual_text' => 'text DEFAULT NULL',
            'expert_approval_status' => 'varchar(20) DEFAULT "needs_approval"',
            'score' => 'int(3) DEFAULT 0',
            'source' => 'varchar(500) DEFAULT NULL',
            'source_url' => 'text DEFAULT NULL',
            'source_type' => 'varchar(50) DEFAULT "manual"',
            'competitor_name' => 'varchar(255) DEFAULT NULL',
            'published_date' => 'datetime DEFAULT NULL',
            'discovered_at' => 'datetime DEFAULT NULL'
        );

        foreach ($columns_to_check as $column_name => $column_definition) {
            $column_sql = $this->escape_db_identifier($column_name);
            if ($column_sql === '') {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$suggestions_table_sql} LIKE %s", $column_name));

            if (empty($column_exists)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $result = $wpdb->query("ALTER TABLE {$suggestions_table_sql} ADD COLUMN {$column_sql} {$column_definition}");

                if ($result !== false) {
                    $this->log_info('Added suggestions table column', array('column' => $column_name));
                } else {
                    $this->log_error('Failed to add suggestions table column', array('column' => $column_name, 'db_error' => $wpdb->last_error));
                }
            }
        }
    }

    /**
     * Get table name
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * Check if table exists
     */
    public function table_exists() {
        global $wpdb;
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name));
        return $table_exists == $this->table_name;
    }

    /**
     * Get table status info
     */
    public function get_table_info() {
        global $wpdb;

        $info = array(
            'exists' => $this->table_exists(),
            'name' => $this->table_name,
            'version' => get_option('SMARK_social_media_table_version', 'Not set'),
            'row_count' => 0
        );

        if ($info['exists']) {
            $table_sql = $this->escape_db_identifier($this->table_name);
            if ($table_sql !== '') {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $info['row_count'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_sql}");
            }
        }

        return $info;
    }

    /**
     * Insert new record
     */
    public function insert_record($project, $headline) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'project' => sanitize_text_field($project),
                'headline' => sanitize_textarea_field($headline)
            ),
            array('%s', '%s')
        );

        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Get all records
     */
    public function get_all_records() {
        global $wpdb;
        $items_table_sql = $this->escape_db_identifier($this->table_name);
        if ($items_table_sql === '') {
            return array();
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results("SELECT * FROM {$items_table_sql} ORDER BY created_at DESC", ARRAY_A);

        return $results;
    }

    /**
     * Get records by project
     */
    public function get_records_by_project($project) {
        global $wpdb;
        $items_table_sql = $this->escape_db_identifier($this->table_name);
        if ($items_table_sql === '') {
            return array();
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items_table_sql} WHERE project = %s ORDER BY created_at DESC", $project), ARRAY_A);

        return $results;
    }

    /**
     * Update record
     */
    public function update_record($id, $project, $headline) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            array(
                'project' => sanitize_text_field($project),
                'headline' => sanitize_textarea_field($headline)
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete record
     */
    public function delete_record($id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get all projects
     */
    public function get_all_projects() {
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            return array();
        }

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->projects_table));

        if (!$table_exists) {
            $this->create_projects_table();
        }

        // Ensure project_id column exists and backfill missing ones
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$projects_table_sql} LIKE %s", 'project_id'));
        if (empty($column_exists)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->query("ALTER TABLE {$projects_table_sql} ADD COLUMN project_id varchar(50) DEFAULT NULL AFTER id");
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $rows_without_id = $wpdb->get_results("SELECT id FROM {$projects_table_sql} WHERE project_id IS NULL OR project_id = ''", ARRAY_A);
        if (!empty($rows_without_id)) {
            foreach ($rows_without_id as $row) {
                $pid = 'PRJ-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$projects_table_sql} WHERE project_id = %s", $pid));
                if ($exists > 0) {
                    $pid = $pid . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 4));
                }
                $wpdb->update($this->projects_table, array('project_id' => $pid), array('id' => $row['id']), array('%s'), array('%d'));
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results("SELECT * FROM {$projects_table_sql} ORDER BY created_at DESC", ARRAY_A);

        return $results;
    }

    /**
     * Create new project
     */
    public function create_project($project_name) {
        global $wpdb;

        // First insert the project to get the ID
        $result = $wpdb->insert(
            $this->projects_table,
            array('project_name' => sanitize_text_field($project_name)),
            array('%s')
        );

        if ($result !== false) {
            $insert_id = $wpdb->insert_id;

            // Generate project_id
            $project_id = $this->generate_project_id($insert_id);

            // Update the project with the generated project_id
            $wpdb->update(
                $this->projects_table,
                array('project_id' => $project_id),
                array('id' => $insert_id),
                array('%s'),
                array('%d')
            );

            return $insert_id;
        }

        return false;
    }

    /**
     * Generate unique project ID
     * Format: PRJ-XXXXX (e.g., PRJ-00001, PRJ-00002)
     */
    private function generate_project_id($database_id) {
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);

        // Generate ID based on database ID with padding
        $project_id = 'PRJ-' . str_pad($database_id, 5, '0', STR_PAD_LEFT);

        // Check if this ID already exists (unlikely, but safe to check)
        if ($projects_table_sql !== '') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$projects_table_sql} WHERE project_id = %s", $project_id));
        } else {
            $exists = 0;
        }

        // If it exists, add a random suffix
        if ($exists > 0) {
            $project_id = 'PRJ-' . str_pad($database_id, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
        }

        return $project_id;
    }

    /**
     * Check if project exists
     */
    public function project_exists($project_name) {
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$projects_table_sql} WHERE project_name = %s", $project_name));

        return $count > 0;
    }

    /**
     * AJAX: Get all projects
     */
    public function ajax_get_projects() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $projects = $this->get_all_projects();
        wp_send_json_success($projects);
    }

    /**
     * AJAX: Create new project
     */
    public function ajax_create_project() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';

        if (empty($project_name)) {
            wp_send_json_error(array('message' => __('Project name is required', 'smark')));
        }

        if ($this->project_exists($project_name)) {
            wp_send_json_error(array('message' => __('Project already exists', 'smark')));
        }

        $id = $this->create_project($project_name);

        if ($id) {
            // Fetch project_id for the newly created project
            $projects_table_sql = $this->escape_db_identifier($this->projects_table);
            if ($projects_table_sql === '') {
                wp_send_json_error(array('message' => __('Projects table is invalid', 'smark')));
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $project_id = $wpdb->get_var($wpdb->prepare("SELECT project_id FROM {$projects_table_sql} WHERE id = %d", $id));

            wp_send_json_success(array(
                'message' => __('Project created successfully', 'smark'),
                'project' => array(
                    'id' => $id,
                    'project_id' => $project_id,
                    'project_name' => $project_name
                )
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create project', 'smark')));
        }
    }

    /**
     * Add submenu page (hidden from menu)
     */
    public function add_submenu_page() {
        add_submenu_page(
            null, // Hidden from menu
            __('Social Media Designer', 'smark'),
            __('Social Media Designer', 'smark'),
            'smark_access',
            'smark-social-media',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Check if we're on the social media page
        if ($hook !== 'admin_page_smark-social-media') {
            return;
        }

        // Enqueue Google Font VazirMTN for Persian
        wp_enqueue_style('vazirmatn-font', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap', array(), SMARK_VERSION);

        wp_enqueue_style('smark-social-media', SMARK_PLUGIN_URL . 'features/social-media/assets/social-media.css', array(), SMARK_VERSION);

        wp_enqueue_script('smark-social-media', SMARK_PLUGIN_URL . 'features/social-media/assets/social-media.js', array('jquery'), SMARK_VERSION, true);

        // Get current language
        $current_lang = get_option('SMARK_panel_language', 'en');

        // Localize script
        wp_localize_script('smark-social-media', 'SMarkSocialMedia', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('SMARK_social_media_nonce'),
            'currentLang' => $current_lang,
            'defaultProject' => $this->get_current_site_project(),
            'strings' => array(
                'loading' => $this->get_translation('loading'),
                'selectProject' => $this->get_translation('select_a_project'),
                'createNew' => $this->get_translation('create_new_project'),
                'projectName' => $this->get_translation('project_name'),
                'create' => $this->get_translation('create'),
                'cancel' => $this->get_translation('cancel'),
                'success' => $this->get_translation('success'),
                'error' => $this->get_translation('error'),
                'edit' => $this->get_translation('edit'),
                'delete' => $this->get_translation('delete'),
                'verified' => $this->get_translation('verified'),
                'partiallyVerified' => $this->get_translation('partially_verified'),
                'unverified' => $this->get_translation('unverified'),
                'needs_approval' => $this->get_translation('needs_approval'),
                'sent_to_expert' => $this->get_translation('sent_to_expert'),
                'approved_by_expert' => $this->get_translation('approved_by_expert'),
                'published' => $this->get_translation('published'),
                'addNewItem' => $this->get_translation('add_new_item'),
                'editItem' => $this->get_translation('edit_item'),
                'headlineLabel' => $this->get_translation('headline_label'),
                'enterHeadline' => $this->get_translation('enter_headline'),
                'analyzeHeadline' => $this->get_translation('analyze_headline'),
                'analysisResults' => $this->get_translation('analysis_results'),
                'noAnalysisPerformed' => $this->get_translation('no_analysis_performed'),
                'visualLabel' => $this->get_translation('visual_label'),
                'visualTextLabel' => $this->get_translation('visual_text_label'),
                'enterVisualText' => $this->get_translation('enter_visual_text'),
                'captionLabel' => $this->get_translation('caption_label'),
                'enterCaption' => $this->get_translation('enter_caption'),
                'sourceLabel' => $this->get_translation('source_label'),
                'enterSource' => $this->get_translation('enter_source'),
                'sourceHelpText' => $this->get_translation('source_help_text'),
                'chooseFile' => $this->get_translation('choose_file'),
                'saveItem' => $this->get_translation('save_item'),
                'saving' => $this->get_translation('saving'),
                'updateItem' => $this->get_translation('update_item'),
                'updating' => $this->get_translation('updating'),
                'cancelBtn' => $this->get_translation('cancel_btn'),
                'analyzing' => $current_lang === 'fa' ? 'در حال تحلیل...' : 'Analyzing...',
                'yes' => $current_lang === 'fa' ? 'بله' : 'Yes',
                'no' => $current_lang === 'fa' ? 'خیر' : 'No',
                'pleaseEnterHeadline' => $current_lang === 'fa' ? 'لطفاً ابتدا یک عنوان وارد کنید' : 'Please enter a headline first',
                'has_numbers' => $this->get_translation('has_numbers'),
                'words' => $this->get_translation('words'),
                'characters' => $this->get_translation('characters'),
                'score' => $this->get_translation('score'),
                'gains_pains' => $this->get_translation('gains_pains'),
                'ai_analysis' => $this->get_translation('ai_analysis'),
                'view' => $this->get_translation('view'),
                'viewSuggestion' => $this->get_translation('viewSuggestion'),
                'transferToItems' => $this->get_translation('transferToItems'),
                'confirmTransferSuggestion' => $this->get_translation('confirmTransferSuggestion'),
                'confirmDeleteSuggestion' => $this->get_translation('confirmDeleteSuggestion'),
                'errorLoadingSuggestion' => $this->get_translation('errorLoadingSuggestion'),
                'errorTransferringSuggestion' => $this->get_translation('errorTransferringSuggestion'),
                'errorDeletingSuggestion' => $this->get_translation('errorDeletingSuggestion'),
                'no_suggestions_found' => $this->get_translation('no_suggestions_found')
            )
        ));
    }

    /**
     * Get items for a specific project
     */
    private function get_project_items($project_id = null, $project_name = null) {
        global $wpdb;
        $items_table_sql = $this->escape_db_identifier($this->table_name);
        if ($items_table_sql === '') {
            return array();
        }

        // Prefer filtering by project_id when provided
        if (!empty($project_id)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items_table_sql} WHERE project_id = %s ORDER BY created_at DESC", $project_id), ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$items_table_sql} WHERE project = %s ORDER BY created_at DESC", $project_name), ARRAY_A);
        }

        return $items ? $items : array();
    }

    /**
     * AJAX: Get project items
     */
    public function ajax_get_project_items() {
        $this->log_debug('ajax_get_project_items called');

        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        $project_id = isset($_POST['project_id']) ? sanitize_text_field(wp_unslash($_POST['project_id'])) : '';
        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';
        $this->log_debug('Project identifier', array('project_id' => $project_id, 'project_name' => $project_name));

        if (empty($project_id) && empty($project_name)) {
            $current_project = $this->get_current_site_project();
            if (is_array($current_project)) {
                $project_id = isset($current_project['project_id']) ? (string) $current_project['project_id'] : '';
                $project_name = isset($current_project['project_name']) ? (string) $current_project['project_name'] : '';
            }

            if (empty($project_id) && empty($project_name)) {
                $this->log_warning('Project identifier is empty');
                wp_send_json_error(array(
                    'message' => __('Project not specified', 'smark')
                ));
            }
        }

        $items = $this->get_project_items($project_id, $project_name);
        $this->log_debug('Items found', array('count' => count($items)));

        // Debug: Log visual URLs
        foreach ($items as $item) {
            if (!empty($item['visual'])) {
                $this->log_debug('Item visual', array('id' => $item['id'], 'url' => $item['visual'], 'type' => ($item['visual_type'] ?? null)));
            }
        }

        // Resolve project meta (id/name) for UI purposes
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => __('Projects table is invalid', 'smark')));
        }
        if (empty($project_id) && !empty($project_name)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $project_id = $wpdb->get_var($wpdb->prepare("SELECT project_id FROM {$projects_table_sql} WHERE project_name = %s", $project_name));
        }
        if (empty($project_name) && !empty($project_id)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $project_name = $wpdb->get_var($wpdb->prepare("SELECT project_name FROM {$projects_table_sql} WHERE project_id = %s", $project_id));
        }

        wp_send_json_success(array(
            'items' => $items,
            'count' => count($items),
            'project_id' => $project_id
        ));
    }

    /**
     * Get suggestions for a specific project
     */
    private function get_project_suggestions($project_id = null, $project_name = null) {
        global $wpdb;

        $suggestions_table = $wpdb->prefix . 'SMARK_social_media_suggestions';
        $suggestions_table_sql = $this->escape_db_identifier($suggestions_table);
        if ($suggestions_table_sql === '') {
            return array();
        }

        if (!empty($project_id)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $suggestions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$suggestions_table_sql} WHERE project_id = %s ORDER BY created_at DESC", $project_id), ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $suggestions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$suggestions_table_sql} WHERE project = %s ORDER BY created_at DESC", $project_name), ARRAY_A);
        }

        return $suggestions ? $suggestions : array();
    }

    /**
     * AJAX: Get project suggestions
     */
    public function ajax_get_project_suggestions() {
        $this->log_debug('ajax_get_project_suggestions called');

        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        $project_id = isset($_POST['project_id']) ? sanitize_text_field(wp_unslash($_POST['project_id'])) : '';
        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';
        $this->log_debug('Project identifier', array('project_id' => $project_id, 'project_name' => $project_name));

        if (empty($project_id) && empty($project_name)) {
            $current_project = $this->get_current_site_project();
            if (is_array($current_project)) {
                $project_id = isset($current_project['project_id']) ? (string) $current_project['project_id'] : '';
                $project_name = isset($current_project['project_name']) ? (string) $current_project['project_name'] : '';
            }

            if (empty($project_id) && empty($project_name)) {
                $this->log_warning('Project identifier is empty');
                wp_send_json_error(array(
                    'message' => __('Project not specified', 'smark')
                ));
            }
        }

        $suggestions = $this->get_project_suggestions($project_id, $project_name);
        $this->log_debug('Suggestions found', array('count' => count($suggestions)));

        // Resolve project meta (id/name)
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => __('Projects table is invalid', 'smark')));
        }
        if (empty($project_id) && !empty($project_name)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $project_id = $wpdb->get_var($wpdb->prepare("SELECT project_id FROM {$projects_table_sql} WHERE project_name = %s", $project_name));
        }
        if (empty($project_name) && !empty($project_id)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $project_name = $wpdb->get_var($wpdb->prepare("SELECT project_name FROM {$projects_table_sql} WHERE project_id = %s", $project_id));
        }

        wp_send_json_success(array(
            'suggestions' => $suggestions,
            'count' => count($suggestions),
            'project_id' => $project_id,
            'project_name' => $project_name
        ));
    }

    /**
     * Add new item to database
     */
    private function add_item($project_name, $headline, $visual = null, $visual_type = null, $content_link = null, $visual_text = null, $caption = null, $source = null, $published_link = null, $expert_approval_status = 'needs_approval', $headline_analysis_results = null, $score = 0) {
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            return false;
        }

        // Don't analyze headline automatically - let user click analyze button
        // Set default verification status
        $verification_status = 'unverified';

        // Get project_id from project_name
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project_id = $wpdb->get_var($wpdb->prepare("SELECT project_id FROM {$projects_table_sql} WHERE project_name = %s", $project_name));

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'project' => $project_name,
                'project_id' => $project_id,
                'headline' => $headline,
                'visual' => $visual,
                'visual_type' => $visual_type,
                'content_link' => $content_link,
                'visual_text' => $visual_text,
                'expert_approval_status' => $expert_approval_status,
                'caption' => $caption,
                'source' => $source,
                'published_link' => $published_link,
                'headline_analysis_results' => $headline_analysis_results,
                'verification_status' => $verification_status,
                'score' => $score,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        if ($result === false) {
            $this->log_error('Database insert error', array('db_error' => $wpdb->last_error));
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * AJAX: Add new item
     */
    public function ajax_add_item() {
        $this->log_debug('ajax_add_item called');

        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';
        $headline = isset($_POST['headline']) ? sanitize_textarea_field(wp_unslash($_POST['headline'])) : '';
        $visual = isset($_POST['visual']) ? esc_url_raw(wp_unslash($_POST['visual'])) : null;
        $visual_type = (isset($_POST['visual_type']) && !empty($_POST['visual_type'])) ? sanitize_text_field(wp_unslash($_POST['visual_type'])) : null;
        // Normalize and sanitize content_link; accept links without scheme
        // Only process if the field is actually provided and not empty
        $content_link = null;
        if (isset($_POST['content_link'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $content_link_raw = trim((string) wp_unslash($_POST['content_link']));
            if ($content_link_raw !== '') {
                if (!preg_match('#^https?://#i', $content_link_raw)) {
                    $content_link_raw = 'https://' . ltrim($content_link_raw, '/');
                }
                $sanitized_link = esc_url_raw($content_link_raw);
                $content_link = $sanitized_link !== '' ? $sanitized_link : sanitize_text_field($content_link_raw);
            }
            // If empty string, keep as null (don't auto-fill)
        }
        $visual_text = isset($_POST['visual_text']) ? sanitize_textarea_field(wp_unslash($_POST['visual_text'])) : null;
        $caption = isset($_POST['caption']) ? sanitize_textarea_field(wp_unslash($_POST['caption'])) : null;
        $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : null;
        // Published link (optional)
        // Only process if the field is actually provided and not empty
        $published_link = null;
        if (isset($_POST['published_link'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $published_link_raw = trim((string) wp_unslash($_POST['published_link']));
            if ($published_link_raw !== '') {
                if (!preg_match('#^https?://#i', $published_link_raw)) {
                    $published_link_raw = 'https://' . ltrim($published_link_raw, '/');
                }
                $sanitized_published = esc_url_raw($published_link_raw);
                $published_link = $sanitized_published !== '' ? $sanitized_published : sanitize_text_field($published_link_raw);
            }
            // If empty string, keep as null (don't auto-fill)
        }
        $expert_approval_status = isset($_POST['expert_approval_status']) ? sanitize_text_field(wp_unslash($_POST['expert_approval_status'])) : 'needs_approval';
        $headline_analysis_results = isset($_POST['headline_analysis_results']) ? sanitize_textarea_field(wp_unslash($_POST['headline_analysis_results'])) : null;
        $score = isset($_POST['score']) ? intval($_POST['score']) : 0;

        $this->log_debug('Add item parameters', array('project_name' => $project_name, 'headline_len' => strlen($headline), 'visual' => $visual, 'visual_type' => $visual_type, 'content_link' => $content_link, 'expert_approval_status' => $expert_approval_status, 'score' => $score));

        if (empty($project_name)) {
            wp_send_json_error(array(
                'message' => __('Project name is required', 'smark')
            ));
        }

        if (empty($headline)) {
            wp_send_json_error(array(
                'message' => __('Headline is required', 'smark')
            ));
        }

        $item_id = $this->add_item($project_name, $headline, $visual, $visual_type, $content_link, $visual_text, $caption, $source, $published_link, $expert_approval_status, $headline_analysis_results, $score);

        if ($item_id) {
            $this->log_info('Item added successfully', array('item_id' => $item_id));
            wp_send_json_success(array(
                'message' => $this->get_translation('item_added_successfully'),
                'item_id' => $item_id
            ));
        } else {
            $this->log_error('Failed to add item');
            wp_send_json_error(array(
                'message' => $this->get_translation('failed_to_add_item')
            ));
        }
    }

    /**
     * Get single item by ID
     */
    private function get_item($item_id) {
        global $wpdb;
        $items_table_sql = $this->escape_db_identifier($this->table_name);
        if ($items_table_sql === '') {
            return null;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$items_table_sql} WHERE id = %d", $item_id), ARRAY_A);

        return $item;
    }

    /**
     * Update item
     */
    private function update_item($item_id, $headline, $visual = null, $visual_type = null, $content_link = null, $visual_text = null, $caption = null, $source = null, $published_link = null, $expert_approval_status = 'needs_approval', $headline_analysis_results = null, $score = null) {
        global $wpdb;

        $this->log_debug('Update item', array('item_id' => $item_id, 'visual' => $visual, 'visual_type' => $visual_type, 'score' => $score));

        // Don't analyze headline automatically - let user click analyze button
        // Keep existing verification status and score
        $data = array(
            'headline' => $headline,
            'updated_at' => current_time('mysql')
        );

        $formats = array('%s', '%s');

        // Only update visual and visual_type if provided
        if ($visual !== null) {
            $data['visual'] = $visual;
            $formats[] = '%s';
        }
        if ($visual_type !== null) {
            $data['visual_type'] = $visual_type;
            $formats[] = '%s';
        }

        // Always update content_link (can be null to clear the field)
        $data['content_link'] = $content_link;
        $formats[] = '%s';

        if ($visual_text !== null) {
            $data['visual_text'] = $visual_text;
            $formats[] = '%s';
        }

        // Always update published_link (can be null to clear the field)
        $data['published_link'] = $published_link;
        $formats[] = '%s';

        // Only update caption if provided
        if ($caption !== null) {
            $data['caption'] = $caption;
            $formats[] = '%s';
        }

        // Only update source if provided
        if ($source !== null) {
            $data['source'] = $source;
            $formats[] = '%s';
        }

        // Always update expert_approval_status
        $data['expert_approval_status'] = $expert_approval_status;
        $formats[] = '%s';

        // Only update caption if provided
        if ($caption !== null) {
            $data['caption'] = $caption;
            $formats[] = '%s';
        }

        // Only update headline_analysis_results if provided
        if ($headline_analysis_results !== null) {
            $data['headline_analysis_results'] = $headline_analysis_results;
            $formats[] = '%s';
        }

        // Only update score if provided
        if ($score !== null) {
            $data['score'] = $score;
            $formats[] = '%d';
        }

        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $item_id),
            $formats,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Delete item
     */
    private function delete_item($item_id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $item_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * AJAX: Get single item
     */
    public function ajax_get_item() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        if (empty($item_id)) {
            wp_send_json_error(array(
                'message' => __('Item ID is required', 'smark')
            ));
        }

        $item = $this->get_item($item_id);

        if ($item) {
            wp_send_json_success(array('item' => $item));
        } else {
            wp_send_json_error(array(
                'message' => __('Item not found', 'smark')
            ));
        }
    }

    /**
     * AJAX: Update item
     */
    public function ajax_update_item() {
        $this->log_debug('ajax_update_item called');

        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $headline = isset($_POST['headline']) ? sanitize_textarea_field(wp_unslash($_POST['headline'])) : '';
        $visual = isset($_POST['visual']) ? esc_url_raw(wp_unslash($_POST['visual'])) : null;
        $visual_type = (isset($_POST['visual_type']) && !empty($_POST['visual_type'])) ? sanitize_text_field(wp_unslash($_POST['visual_type'])) : null;
        // Normalize and sanitize content_link for update
        // Only process if the field is actually provided and not empty
        $content_link = null;
        if (isset($_POST['content_link'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $content_link_raw = trim((string) wp_unslash($_POST['content_link']));
            if ($content_link_raw !== '') {
                if (!preg_match('#^https?://#i', $content_link_raw)) {
                    $content_link_raw = 'https://' . ltrim($content_link_raw, '/');
                }
                $sanitized_link_update = esc_url_raw($content_link_raw);
                $content_link = $sanitized_link_update !== '' ? $sanitized_link_update : sanitize_text_field($content_link_raw);
            }
            // If empty string, keep as null (don't auto-fill)
        }
        $visual_text = isset($_POST['visual_text']) ? sanitize_textarea_field(wp_unslash($_POST['visual_text'])) : null;
        $caption = isset($_POST['caption']) ? sanitize_textarea_field(wp_unslash($_POST['caption'])) : null;
        $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : null;
        // Normalize and sanitize published_link for update
        // Only process if the field is actually provided and not empty
        $published_link = null;
        if (isset($_POST['published_link'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $published_link_raw = trim((string) wp_unslash($_POST['published_link']));
            if ($published_link_raw !== '') {
                if (!preg_match('#^https?://#i', $published_link_raw)) {
                    $published_link_raw = 'https://' . ltrim($published_link_raw, '/');
                }
                $sanitized_published_update = esc_url_raw($published_link_raw);
                $published_link = $sanitized_published_update !== '' ? $sanitized_published_update : sanitize_text_field($published_link_raw);
            }
            // If empty string, keep as null (don't auto-fill)
        }
        $expert_approval_status = isset($_POST['expert_approval_status']) ? sanitize_text_field(wp_unslash($_POST['expert_approval_status'])) : 'needs_approval';
        $headline_analysis_results = isset($_POST['headline_analysis_results']) ? sanitize_textarea_field(wp_unslash($_POST['headline_analysis_results'])) : null;
        $score = isset($_POST['score']) ? intval($_POST['score']) : null;

        $this->log_debug('Update item parameters', array('item_id' => $item_id, 'headline_len' => strlen($headline), 'visual' => $visual, 'visual_type' => $visual_type, 'content_link' => $content_link, 'expert_approval_status' => $expert_approval_status, 'score' => $score));

        if (empty($item_id)) {
            wp_send_json_error(array(
                'message' => __('Item ID is required', 'smark')
            ));
        }

        if (empty($headline)) {
            wp_send_json_error(array(
                'message' => __('Headline is required', 'smark')
            ));
        }

        $success = $this->update_item($item_id, $headline, $visual, $visual_type, $content_link, $visual_text, $caption, $source, $published_link, $expert_approval_status, $headline_analysis_results, $score);

        if ($success) {
            $this->log_info('Item updated successfully', array('item_id' => $item_id));
            wp_send_json_success(array(
                'message' => $this->get_translation('item_updated_successfully')
            ));
        } else {
            $this->log_error('Failed to update item', array('item_id' => $item_id));
            wp_send_json_error(array(
                'message' => $this->get_translation('failed_to_update_item')
            ));
        }
    }

    /**
     * AJAX: Delete item
     */
    public function ajax_delete_item() {
        $this->log_debug('ajax_delete_item called');

        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        $this->log_debug('Item delete requested', array('item_id' => $item_id));

        if (empty($item_id)) {
            wp_send_json_error(array(
                'message' => __('Item ID is required', 'smark')
            ));
        }

        $success = $this->delete_item($item_id);

        if ($success) {
            $this->log_info('Item deleted successfully', array('item_id' => $item_id));
            wp_send_json_success(array(
                'message' => $this->get_translation('item_deleted_successfully')
            ));
        } else {
            $this->log_error('Failed to delete item', array('item_id' => $item_id));
            wp_send_json_error(array(
                'message' => $this->get_translation('failed_to_delete_item')
            ));
        }
    }

    /**
     * Count words in Persian text properly
     */
    private function count_words_persian($text) {
        // Remove extra whitespace and normalize
        $text = trim($text);
        if (empty($text)) {
            return 0;
        }

        // Split by whitespace and filter out empty strings
        $words = preg_split('/\s+/', $text);
        $words = array_filter($words, function($word) {
            return !empty(trim($word));
        });

        return count($words);
    }

    /**
     * Analyze headline using Headline Analyzer microservice
     * This delegates all analysis to the Headline Analyzer feature
     */
    private function analyze_headline($headline) {
        $this->log_debug('Analyzing headline', array('headline_len' => strlen($headline)));

        // ONLY use Headline Analyzer service - no fallback to prevent duplicate calls
        if (isset($GLOBALS['SMARK_headline_analyzer'])) {
            $this->log_debug('Using Headline Analyzer service');
            try {
                $result = $GLOBALS['SMARK_headline_analyzer']->analyze_headline($headline);
                if ($result && is_array($result)) {
                    $this->log_debug('Headline Analyzer result', array('score' => (isset($result['score']) ? $result['score'] : null)));
                    return $result;
                } else {
                    $this->log_warning('Headline Analyzer returned invalid result');
                }
            } catch (Exception $e) {
                $this->log_error('Headline Analyzer error', array('message' => $e->getMessage()));
            }
        } else {
            $this->log_warning('Headline Analyzer service not available');
        }

        // If Headline Analyzer is not available or fails, return error instead of fallback
        $this->log_warning('Headline Analyzer unavailable, returning error');

        return array(
            'headline' => $headline,
            'score' => 0,
            'char_count' => mb_strlen($headline),
            'word_count' => $this->count_words_persian($headline),
            'has_numbers' => false,
            'has_gains_pains' => false,
            'gains_pains_explanation' => 'Headline Analyzer service unavailable',
            'gains_pains_error' => true
        );
    }

    /**
     * Calculate verification status based on analysis results
     */
    private function calculate_verification_status_from_analysis($analysis) {
        $score = $analysis['score'];

        // Verification criteria:
        // - Score 100 (has both numbers AND gains/pains) = verified
        // - Score 50-99 (has only one factor) = partially verified
        // - Score 0 (has neither) = unverified
        if ($score === 100) {
            $verification_status = 'verified';
        } elseif ($score >= 50) {
            $verification_status = 'partially-verified';
        } else {
            $verification_status = 'unverified';
        }

        return array(
            'status' => $verification_status,
            'score' => $score
        );
    }

    /**
     * Calculate verification status based on headline analysis (legacy method)
     */
    private function calculate_verification_status($headline) {
        $analysis = $this->analyze_headline($headline);
        return $this->calculate_verification_status_from_analysis($analysis);
    }

    /**
     * AJAX: Analyze headline quickly
     */
    public function ajax_analyze_headline_quick() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        $headline = isset($_POST['headline']) ? sanitize_textarea_field(wp_unslash($_POST['headline'])) : '';

        $this->log_debug('Analyze headline request', array('headline_len' => strlen($headline), 'time' => gmdate('Y-m-d H:i:s')));

        if (empty($headline)) {
            $this->log_warning('Analyze headline: empty headline');
            wp_send_json_success(array(
                'score' => 0,
                'char_count' => 0,
                'word_count' => 0,
                'has_numbers' => false
            ));
        }

        $analysis = $this->analyze_headline($headline);
        $this->log_debug('Analyze headline result', array('score' => (isset($analysis['score']) ? $analysis['score'] : null)));
        wp_send_json_success($analysis);
    }

    /**
     * Update item with analysis results and score
     */
    private function update_item_with_score($item_id, $analysis_results, $score) {
        global $wpdb;

        $data = array(
            'headline_analysis_results' => $analysis_results,
            'score' => $score,
            'updated_at' => current_time('mysql')
        );

        $formats = array('%s', '%d', '%s');

        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $item_id),
            $formats,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * AJAX: Save analysis results
     */
    public function ajax_save_analysis_results() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $analysis_results = isset($_POST['analysis_results']) ? sanitize_textarea_field(wp_unslash($_POST['analysis_results'])) : '';
        $score = isset($_POST['score']) ? intval($_POST['score']) : 0;

        if (empty($item_id)) {
            wp_send_json_error(array(
                'message' => __('Item ID is required', 'smark')
            ));
        }

        $success = $this->update_item_with_score($item_id, $analysis_results, $score);

        if ($success) {
            wp_send_json_success(array(
                'message' => __('Analysis results and score saved successfully', 'smark')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to save analysis results', 'smark')
            ));
        }
    }

    private function filter_upload_dir_social_media($dirs) {
        $subdir = '/smark-social-media';
        $dirs['subdir'] = $subdir;
        $dirs['path'] = $dirs['basedir'] . $subdir;
        $dirs['url'] = $dirs['baseurl'] . $subdir;
        return $dirs;
    }

    /**
     * AJAX: Upload visual file (image or video)
     */
    public function ajax_upload_visual() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file was uploaded', 'smark')));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $file = wp_unslash($_FILES['file']);
        if (!isset($file['tmp_name'], $file['name']) || !is_string($file['tmp_name']) || $file['tmp_name'] === '') {
            wp_send_json_error(array('message' => __('Invalid upload data', 'smark')));
        }

        // Check file size (10MB max)
        $max_size = 10 * 1024 * 1024; // 10MB in bytes
        if (isset($file['size']) && (int) $file['size'] > $max_size) {
            wp_send_json_error(array('message' => __('File size exceeds 10MB limit', 'smark')));
        }

        $mimes = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
        );

        add_filter('upload_dir', array($this, 'filter_upload_dir_social_media'));
        $uploaded = wp_handle_upload($file, array('test_form' => false, 'mimes' => $mimes));
        remove_filter('upload_dir', array($this, 'filter_upload_dir_social_media'));

        if (!is_array($uploaded) || isset($uploaded['error'])) {
            $this->log_error('Upload failed', array('error' => (is_array($uploaded) && isset($uploaded['error'])) ? $uploaded['error'] : 'unknown'));
            wp_send_json_error(array('message' => __('Failed to upload file', 'smark')));
        }

        $this->log_info('Upload successful', array('url' => $uploaded['url'], 'type' => $uploaded['type']));

        wp_send_json_success(array(
            'url' => $uploaded['url'],
            'type' => $uploaded['type'],
            'message' => __('File uploaded successfully', 'smark')
        ));
    }

    /**
     * AJAX: Save language preference
     */
    public function ajax_save_language() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '';

        if (empty($language) || !in_array($language, array('en', 'fa'))) {
            wp_send_json_error(array('message' => __('Invalid language', 'smark')));
        }

        // Save language preference
        update_option('SMARK_panel_language', $language);

        // Set WordPress locale based on selection
        if ($language === 'fa') {
            // Persian locale
            add_filter('locale', function() { return 'fa_IR'; });
        } else {
            // English locale
            add_filter('locale', function() { return 'en_US'; });
        }

        wp_send_json_success(array(
            'message' => __('Language preference saved', 'smark'),
            'language' => $language
        ));
    }

    /**
     * AJAX: Get suggestion for view/edit
     */
    public function ajax_get_suggestion() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $suggestion_id = isset($_POST['suggestion_id']) ? intval($_POST['suggestion_id']) : 0;

        if (empty($suggestion_id)) {
            wp_send_json_error(array('message' => __('Suggestion ID is required', 'smark')));
        }

        global $wpdb;
        $suggestions_table = $wpdb->prefix . 'SMARK_social_media_suggestions';
        $suggestions_table_sql = $this->escape_db_identifier($suggestions_table);
        if ($suggestions_table_sql === '') {
            wp_send_json_error(array('message' => __('Suggestions table is invalid', 'smark')));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $suggestion = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$suggestions_table_sql} WHERE id = %d", $suggestion_id));

        if (!$suggestion) {
            wp_send_json_error(array('message' => __('Suggestion not found', 'smark')));
        }

        wp_send_json_success(array(
            'suggestion' => $suggestion
        ));
    }

    /**
     * AJAX: Update suggestion
     */
    public function ajax_update_suggestion() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $suggestion_id = isset($_POST['suggestion_id']) ? intval($_POST['suggestion_id']) : 0;
        $headline = isset($_POST['headline']) ? sanitize_text_field(wp_unslash($_POST['headline'])) : '';
        $visual = isset($_POST['visual']) ? sanitize_text_field(wp_unslash($_POST['visual'])) : null;
        $visual_type = isset($_POST['visual_type']) ? sanitize_text_field(wp_unslash($_POST['visual_type'])) : null;
        $visual_text = isset($_POST['visual_text']) ? sanitize_textarea_field(wp_unslash($_POST['visual_text'])) : null;
        $caption = isset($_POST['caption']) ? sanitize_textarea_field(wp_unslash($_POST['caption'])) : null;
        $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : null;
        $expert_approval_status = isset($_POST['expert_approval_status']) ? sanitize_text_field(wp_unslash($_POST['expert_approval_status'])) : 'needs_approval';

        if (empty($suggestion_id)) {
            wp_send_json_error(array('message' => __('Suggestion ID is required', 'smark')));
        }

        if (empty($headline)) {
            wp_send_json_error(array('message' => __('Headline is required', 'smark')));
        }

        global $wpdb;
        $suggestions_table = $wpdb->prefix . 'SMARK_social_media_suggestions';
        $suggestions_table_sql = $this->escape_db_identifier($suggestions_table);
        if ($suggestions_table_sql === '') {
            wp_send_json_error(array('message' => __('Suggestions table is invalid', 'smark')));
        }

        $this->log_debug('Update suggestion requested', array('suggestion_id' => $suggestion_id));

        // Check if suggestion exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$suggestions_table_sql} WHERE id = %d", $suggestion_id));

        if (!$exists) {
            $this->log_warning('Suggestion not found', array('suggestion_id' => $suggestion_id));
            wp_send_json_error(array('message' => __('Suggestion not found', 'smark')));
        }

        $this->log_debug('Suggestion exists, proceeding with update', array('suggestion_id' => $suggestion_id));

        // Update suggestion
        $result = $wpdb->update(
            $suggestions_table,
            array(
                'headline' => $headline,
                'visual' => $visual,
                'visual_type' => $visual_type,
                'visual_text' => $visual_text,
                'caption' => $caption,
                'source' => $source,
                'expert_approval_status' => $expert_approval_status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $suggestion_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        // Check for database error
        if ($result === false) {
            $this->log_error('Database error updating suggestion', array('db_error' => $wpdb->last_error));
            wp_send_json_error(array('message' => __('Database error: ', 'smark') . $wpdb->last_error));
        }

        $this->log_debug('Update suggestion result', array('result' => $result));

        wp_send_json_success(array(
            'message' => __('Suggestion updated successfully', 'smark')
        ));
    }

    /**
     * AJAX: Transfer suggestion to items
     */
    public function ajax_transfer_suggestion_to_item() {
        SMarkLogger::info('=== Transfer Suggestion to Item - START ===');

        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            SMarkLogger::error('Transfer failed: Permission denied');
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $suggestion_id = isset($_POST['suggestion_id']) ? intval($_POST['suggestion_id']) : 0;
        SMarkLogger::info('Suggestion ID: ' . $suggestion_id);

        if (empty($suggestion_id)) {
            SMarkLogger::error('Transfer failed: Suggestion ID is required');
            wp_send_json_error(array('message' => __('Suggestion ID is required', 'smark')));
        }

        global $wpdb;
        $suggestions_table = $wpdb->prefix . 'SMARK_social_media_suggestions';
        $items_table = $this->table_name; // Use the same table as project items

        SMarkLogger::debug('Suggestions table: ' . $suggestions_table);
        SMarkLogger::debug('Items table: ' . $items_table);

        // Get suggestion data
        $suggestions_table_sql = $this->escape_db_identifier($suggestions_table);
        if ($suggestions_table_sql === '') {
            wp_send_json_error(array('message' => __('Suggestions table is invalid', 'smark')));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $suggestion = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$suggestions_table_sql} WHERE id = %d", $suggestion_id));

        if (!$suggestion) {
            SMarkLogger::error('Transfer failed: Suggestion not found with ID ' . $suggestion_id);
            wp_send_json_error(array('message' => __('Suggestion not found', 'smark')));
        }

        SMarkLogger::debug('Suggestion data', array('suggestion' => $suggestion));

        // Prepare data for insertion - match the table column order
        $insert_data = array(
            'project' => $suggestion->project,
            'headline' => $suggestion->headline,
            'visual' => isset($suggestion->visual) ? $suggestion->visual : null,
            'visual_type' => isset($suggestion->visual_type) ? $suggestion->visual_type : null,
            'visual_text' => isset($suggestion->visual_text) ? $suggestion->visual_text : null,
            'expert_approval_status' => isset($suggestion->expert_approval_status) ? $suggestion->expert_approval_status : 'needs_approval',
            'source' => isset($suggestion->source) ? $suggestion->source : null,
            'caption' => isset($suggestion->caption) ? $suggestion->caption : null,
            'headline_analysis_results' => null,
            'verification_status' => 'unverified',
            'score' => isset($suggestion->score) ? intval($suggestion->score) : 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        SMarkLogger::debug('Insert data prepared', $insert_data);

        // Insert into items table
        $result = $wpdb->insert(
            $items_table,
            $insert_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        if ($result !== false) {
            SMarkLogger::info('Insert successful. Inserted ID: ' . $wpdb->insert_id);

            // Delete suggestion after successful transfer
            $delete_result = $wpdb->delete(
                $suggestions_table,
                array('id' => $suggestion_id),
                array('%d')
            );

            SMarkLogger::info('Delete suggestion result: ' . ($delete_result !== false ? 'Success' : 'Failed'));
            SMarkLogger::info('=== Transfer Suggestion to Item - SUCCESS ===');

            wp_send_json_success(array(
                'message' => $this->get_translation('suggestion_transferred_successfully')
            ));
        } else {
            $error_msg = $wpdb->last_error ? $wpdb->last_error : 'Unknown database error';
            SMarkLogger::error('Insert failed', array(
                'mysql_error' => $error_msg,
                'last_query' => $wpdb->last_query,
                'insert_data' => $insert_data
            ));
            SMarkLogger::error('=== Transfer Suggestion to Item - FAILED ===');

            wp_send_json_error(array(
                'message' => __('Failed to transfer suggestion', 'smark') . ': ' . $error_msg
            ));
        }
    }

    /**
     * AJAX: Delete suggestion
     */
    public function ajax_delete_suggestion() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $suggestion_id = isset($_POST['suggestion_id']) ? intval($_POST['suggestion_id']) : 0;

        if (empty($suggestion_id)) {
            wp_send_json_error(array('message' => __('Suggestion ID is required', 'smark')));
        }

        global $wpdb;
        $suggestions_table = $wpdb->prefix . 'SMARK_social_media_suggestions';

        $result = $wpdb->delete(
            $suggestions_table,
            array('id' => $suggestion_id),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => $this->get_translation('suggestion_deleted_successfully')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to delete suggestion', 'smark')
            ));
        }
    }

    /**
     * AJAX: Test Gemini connection
     */
    public function ajax_test_gemini_connection() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $gemini_app = $this->get_gemini_app_instance();
        if (!$gemini_app) {
            wp_send_json_error(array('message' => __('Gemini App is not available. Please ensure SMark Core is installed and active.', 'smark')));
        }

        $result = $gemini_app->test_simple_gemini_call();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Generate attractive title using Gemini App
     */
    public function ajax_generate_attractive_title() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $source_url = isset($_POST['source_url']) ? esc_url_raw(wp_unslash($_POST['source_url'])) : '';
        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';

        if (empty($source_url)) {
            wp_send_json_error(array('message' => __('Source URL is required', 'smark')));
        }

        // Get project language from database
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => __('Projects table is invalid', 'smark')));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project_language = $wpdb->get_var($wpdb->prepare("SELECT brand_language FROM {$projects_table_sql} WHERE project_name = %s", $project_name));

        // Default to Persian if not found
        if (empty($project_language)) {
            $project_language = 'Persian';
        } else {
            // Convert language code to full name
            $project_language = ($project_language === 'fa') ? 'Persian' : 'English';
        }

        // Get prompt from Prompt Bank
        $prompt_data = SMarkPromptBank::get_prompt_by_key('social_media_attractive_title', array(
            'content' => $source_url,
            'language' => $project_language,
            'brand_name' => $project_name
        ));

        // Use Gemini App feature to generate title
        $gemini_app = $this->get_gemini_app_instance();
        if (!$gemini_app) {
            wp_send_json_error(array('message' => __('Gemini App is not available. Please ensure SMark Core is installed and active.', 'smark')));
        }

        // Generate title using prompt from bank if available
        if ($prompt_data) {
            // Use custom prompt from Prompt Bank
            $result = $gemini_app->generate_attractive_title_with_custom_prompt(
                $source_url,
                $project_name,
                $prompt_data['prompt_content']
            );
        } else {
            // Fallback to original method
            $result = $gemini_app->generate_attractive_title($source_url, $project_name);
        }

        if ($result['success']) {
            wp_send_json_success(array('title' => $result['title']));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }

    /**
     * AJAX: Get attractive title prompt (same as used in generation)
     */
    public function ajax_get_attractive_title_prompt() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        SMarkLogger::info('Social Media GPT: Request received for attractive title prompt');

        if (!current_user_can('smark_access')) {
            SMarkLogger::warning('Social Media GPT: Permission denied');
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $source_url = isset($_POST['source_url']) ? esc_url_raw(wp_unslash($_POST['source_url'])) : '';
        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';

        SMarkLogger::debug('Social Media GPT: Parameters', array(
            'source_url' => $source_url,
            'project_name' => $project_name
        ));

        if (empty($source_url)) {
            SMarkLogger::warning('Social Media GPT: Source URL is empty');
            wp_send_json_error(array('message' => __('Source URL is required', 'smark')));
        }

        // Get project language from database
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => __('Projects table is invalid', 'smark')));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project_language = $wpdb->get_var($wpdb->prepare("SELECT brand_language FROM {$projects_table_sql} WHERE project_name = %s", $project_name));

        // Default to Persian if not found
        if (empty($project_language)) {
            $project_language = 'Persian';
        } else {
            // Convert language code to full name
            $project_language = ($project_language === 'fa') ? 'Persian' : 'English';
        }

        SMarkLogger::debug('Social Media GPT: Project language', array('language' => $project_language));

        // Make sure Prompt Bank class is loaded
        if (!class_exists('SMarkPromptBank')) {
            SMarkLogger::error('Social Media GPT: SMarkPromptBank class not found!');
            wp_send_json_error(array('message' => __('Prompt Bank feature is not available', 'smark')));
        }

        SMarkLogger::info('Social Media GPT: Requesting prompt from Prompt Bank', array(
            'prompt_key' => 'social_media_attractive_title',
            'language' => $project_language,
            'brand_name' => $project_name,
            'source_url' => $source_url
        ));

        // Get prompt from Prompt Bank
        // Support both {content} and {source} placeholders for compatibility
        $prompt_data = SMarkPromptBank::get_prompt_by_key('social_media_attractive_title', array(
            'content' => $source_url,
            'source' => $source_url, // Also support {source} placeholder
            'language' => $project_language,
            'brand_name' => $project_name
        ));

        if (!$prompt_data) {
            SMarkLogger::warning('Social Media GPT: Prompt not found in Prompt Bank, using fallback');

            // Fallback: Use old method if prompt not found in bank
            $gemini_app = $this->get_gemini_app_instance();
            if (!$gemini_app) {
                wp_send_json_error(array('message' => __('Gemini App is not available. Please ensure SMark Core is installed and active.', 'smark')));
            }
            $prompt = $gemini_app->get_attractive_title_prompt($source_url, $project_name);

            if (empty($prompt)) {
                SMarkLogger::error('Social Media GPT: Fallback also failed to generate prompt');
                wp_send_json_error(array('message' => __('Could not generate prompt', 'smark')));
            }

            SMarkLogger::info('Social Media GPT: Using fallback prompt', array('prompt_length' => strlen($prompt)));
        } else {
            // Use prompt from Prompt Bank
            $prompt = $prompt_data['prompt_content'];

            // Double-check that placeholders are replaced
            // If {source} or {content} still exists, replace it manually
            if (strpos($prompt, '{source}') !== false) {
                SMarkLogger::warning('Social Media GPT: {source} placeholder still exists, replacing manually');
                $prompt = str_replace('{source}', $source_url, $prompt);
            }
            if (strpos($prompt, '{content}') !== false) {
                SMarkLogger::warning('Social Media GPT: {content} placeholder still exists, replacing manually');
                $prompt = str_replace('{content}', $source_url, $prompt);
            }

            SMarkLogger::info('Social Media GPT: Successfully retrieved prompt from Prompt Bank', array(
                'prompt_length' => strlen($prompt),
                'contains_source_url' => (strpos($prompt, $source_url) !== false),
                'contains_placeholders' => (strpos($prompt, '{') !== false)
            ));

            // Log a preview of the prompt (first 200 chars) to verify replacement
            SMarkLogger::debug('Social Media GPT: Prompt preview', array(
                'preview' => substr($prompt, 0, 200)
            ));
        }

        if (empty($prompt)) {
            SMarkLogger::error('Social Media GPT: Generated prompt is empty');
            wp_send_json_error(array('message' => __('Generated prompt is empty', 'smark')));
        }

        // Final check: ensure no placeholders remain
        if (strpos($prompt, '{source}') !== false || strpos($prompt, '{content}') !== false) {
            SMarkLogger::error('Social Media GPT: Placeholders still exist in final prompt!', array(
                'has_source' => (strpos($prompt, '{source}') !== false),
                'has_content' => (strpos($prompt, '{content}') !== false)
            ));
        }

        SMarkLogger::info('Social Media GPT: Sending prompt to client successfully');
        wp_send_json_success(array('prompt' => $prompt));
    }

    /**
     * AJAX: Get visual text prompt from Prompt Bank
     */
    public function ajax_get_visual_text_prompt() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $source_url = isset($_POST['source_url']) ? esc_url_raw(wp_unslash($_POST['source_url'])) : '';
        $headline = isset($_POST['headline']) ? sanitize_text_field(wp_unslash($_POST['headline'])) : '';
        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';

        if (empty($source_url)) {
            wp_send_json_error(array('message' => __('Source URL is required', 'smark')));
        }

        // Get project language from database
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => __('Projects table is invalid', 'smark')));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project_language = $wpdb->get_var($wpdb->prepare("SELECT brand_language FROM {$projects_table_sql} WHERE project_name = %s", $project_name));

        // Default to Persian if not found
        if (empty($project_language)) {
            $project_language = 'Persian';
        } else {
            // Convert language code to full name
            $project_language = ($project_language === 'fa') ? 'Persian' : 'English';
        }

        // Get prompt from Prompt Bank
        // Support both {content} and {source} placeholders for compatibility
        $prompt_data = SMarkPromptBank::get_prompt_by_key('social_media_visual_text', array(
            'content' => $source_url,
            'source' => $source_url, // Also support {source} placeholder
            'title' => $headline, // Add title/headline placeholder
            'headline' => $headline, // Also support {headline} placeholder
            'language' => $project_language,
            'brand_name' => $project_name
        ));

        if (!$prompt_data) {
            // Fallback: Create a default prompt if not found in bank
            $prompt = "Based on this article: {$source_url}

Create compelling text for a social media visual (image or video) for {$project_name}.

Requirements:
- 2-3 short, impactful sentences
- Language: {$project_language}
- Make it attention-grabbing
- Suitable for image/video overlay";
        } else {
            // Use prompt from Prompt Bank
            $prompt = $prompt_data['prompt_content'];

            // Double-check that placeholders are replaced
            if (strpos($prompt, '{source}') !== false) {
                $prompt = str_replace('{source}', $source_url, $prompt);
            }
            if (strpos($prompt, '{content}') !== false) {
                $prompt = str_replace('{content}', $source_url, $prompt);
            }
            if (strpos($prompt, '{title}') !== false) {
                $prompt = str_replace('{title}', $headline, $prompt);
            }
            if (strpos($prompt, '{headline}') !== false) {
                $prompt = str_replace('{headline}', $headline, $prompt);
            }
        }

        wp_send_json_success(array('prompt' => $prompt));
    }

    /**
     * AJAX: Get caption prompt for GPT
     */
    public function ajax_get_caption_prompt() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        $visual_text = isset($_POST['visual_text']) ? sanitize_textarea_field(wp_unslash($_POST['visual_text'])) : '';
        $headline = isset($_POST['headline']) ? sanitize_text_field(wp_unslash($_POST['headline'])) : '';
        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';

        if (empty($visual_text)) {
            wp_send_json_error(array('message' => 'Visual text content is required'));
        }

        // Get project language from database
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => __('Projects table is invalid', 'smark')));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project_language = $wpdb->get_var($wpdb->prepare("SELECT language FROM {$projects_table_sql} WHERE project_name = %s LIMIT 1", $project_name));

        // Default to Persian if not found
        if (empty($project_language)) {
            $project_language = 'Persian';
        } else {
            // Convert language code to full name
            $project_language = ($project_language === 'fa') ? 'Persian' : 'English';
        }

        // Get prompt from Prompt Bank using social_media_caption key
        $prompt_data = SMarkPromptBank::get_prompt_by_key('social_media_caption', array(
            'content' => $visual_text,
            'title' => $headline,
            'language' => $project_language,
            'brand_name' => $project_name
        ));

        if (!$prompt_data) {
            // Fallback: Create a default prompt if not found in bank
            $prompt = "You are an expert social media copywriter for {$project_name}. Create a concise caption for a social media post using the following information:

Post Title: {$headline}
Post Content: {$visual_text}

Requirements:
- Short paragraph (max 2 sentences)
- Must include a clear CTA at the end
- Tone: friendly and professional
- Language: {$project_language}";
        } else {
            // Use prompt from Prompt Bank
            $prompt = $prompt_data['prompt_content'];

            // Double-check that placeholders are replaced
            // Caption uses visual_text and headline, not source_url, but check anyway
            if (strpos($prompt, '{visual_text}') !== false) {
                $prompt = str_replace('{visual_text}', $visual_text, $prompt);
            }
            if (strpos($prompt, '{headline}') !== false) {
                $prompt = str_replace('{headline}', $headline, $prompt);
            }
            if (strpos($prompt, '{content}') !== false) {
                $prompt = str_replace('{content}', $visual_text, $prompt);
            }
        }

        wp_send_json_success(array('prompt' => $prompt));
    }

    /**
     * AJAX: Get Canva template link for current project
     */
    public function ajax_get_canva_template() {
        check_ajax_referer('SMARK_social_media_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $project_id = isset($_POST['project_id']) ? sanitize_text_field(wp_unslash($_POST['project_id'])) : '';

        if (empty($project_id)) {
            wp_send_json_error(array('message' => __('Project ID is required', 'smark')));
        }

        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_error(array('message' => __('Projects table is invalid', 'smark')));
        }

        // Get canva_template from projects table using project_id
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $canva_template = $wpdb->get_var($wpdb->prepare("SELECT canva_template FROM {$projects_table_sql} WHERE project_id = %s LIMIT 1", $project_id));

        $this->log_debug('Get Canva template result', array('project_id' => $project_id, 'has_template' => (!empty($canva_template))));

        if (!empty($canva_template)) {
            wp_send_json_success(array(
                'canva_template' => $canva_template,
                'message' => __('Canva template found', 'smark')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No Canva template found for this project', 'smark')
            ));
        }
    }

    /**
     * Get translation
     */
    private function get_translation($key) {
        $lang = get_option('SMARK_panel_language', 'en');

        $translations = array(
            'en' => array(
                'social_media_designer' => 'Social Media Designer',
                'create_stunning' => 'Create stunning social media graphics and posts.',
                'SMARK_dashboard' => 'SMark Dashboard',
                'select_or_create_project' => 'Select or Create Project',
                'choose_existing_project' => 'Choose an existing project or create a new one to get started',
                'select_project' => 'Select Project:',
                'loading' => 'Loading...',
                'or' => 'OR',
                'create_new_project' => 'Create New Project',
                'project_name' => 'Project Name:',
                'enter_project_name' => 'Enter project name...',
                'create' => 'Create',
                'cancel' => 'Cancel',
                'project_items' => 'Project Items',
                'project_suggestions' => 'Project Suggestions',
                'add_new_item' => 'Add New Item',
                'id' => 'ID',
                'headline' => 'Headline',
                'visual' => 'Visual',
                'created' => 'Created',
                'actions' => 'Actions',
                'no_items_found' => 'No items found',
                'no_suggestions_found' => 'No suggestions found',
                'view' => 'View',
                'viewSuggestion' => 'View Suggestion',
                'transferToItems' => 'Transfer to Items',
                'confirmTransferSuggestion' => 'Are you sure you want to transfer this suggestion to items?',
                'confirmDeleteSuggestion' => 'Are you sure you want to delete this suggestion?',
                'errorLoadingSuggestion' => 'Error loading suggestion',
                'errorTransferringSuggestion' => 'Error transferring suggestion',
                'errorDeletingSuggestion' => 'Error deleting suggestion',
                'suggestion_transferred_successfully' => 'Suggestion transferred successfully',
                'suggestion_deleted_successfully' => 'Suggestion deleted successfully',
                'updateSuggestion' => 'Update Suggestion',
                'suggestionUpdatedSuccessfully' => 'Suggestion updated successfully',
                'select_a_project' => 'Current project',
                'please_select_project' => 'Set the current project in Project Settings to view social media items.',
                'success' => 'Success!',
                'error' => 'Error!',
                'edit' => 'Edit',
                'delete' => 'Delete',
                'verified' => 'Fully Verified',
                'partially_verified' => 'Partially Verified',
                'unverified' => 'Unverified',
                'status' => 'Status',
                'needs_approval' => 'Needs Expert Approval',
                'sent_to_expert' => 'Sent to Expert',
                'approved_by_expert' => 'Approved by Expert',
                'published' => 'Published',
                'score' => 'Score',
                'add_new_item' => 'Add New Item',
                'edit_item' => 'Edit Item',
                'headline_label' => 'Headline:',
                'enter_headline' => 'Enter your headline...',
                'analyze_headline' => 'Analyze Headline',
                'analysis_results' => 'Analysis Results:',
                'no_analysis_performed' => 'No analysis performed yet.',
                'visual_label' => 'Visual (Image/Video):',
                'content_link_label' => 'Content Link:',
                'enter_content_link' => 'Paste Canva/Drive link...',
                'content_link_help_text' => 'Optional. A link to the original design or asset.',
                'visual_text_label' => 'Video or Image Text:',
                'enter_visual_text' => 'Enter text used in video or image...',
                'caption_label' => 'Caption:',
                'enter_caption' => 'Enter your caption...',
                'source_label' => 'Source:',
                'enter_source' => 'Enter source information...',
                'source_help_text' => 'Source information is automatically filled when transferred from other features.',
                'choose_file' => 'Choose File',
                'published_link_label' => 'Published Link:',
                'enter_published_link' => 'Paste the social post URL...',
                'published_link_help_text' => 'Optional. Link to the final post on the social network.',
                'save_item' => 'Save Item',
                'saving' => 'Saving...',
                'update_item' => 'Update Item',
                'cancel_btn' => 'Cancel',
                'gains_pains' => 'Gain Creators & Pain Relievers',
                'ai_analysis' => 'AI Analysis:',
                'item_added_successfully' => 'Item added successfully',
                'item_updated_successfully' => 'Item updated successfully',
                'item_deleted_successfully' => 'Item deleted successfully',
                'failed_to_add_item' => 'Failed to add item',
                'failed_to_update_item' => 'Failed to update item',
                'failed_to_delete_item' => 'Failed to delete item',
            ),
            'fa' => array(
                'social_media_designer' => 'طراح رسانه‌های اجتماعی',
                'create_stunning' => 'گرافیک‌ها و پست‌های خیره‌کننده رسانه‌های اجتماعی ایجاد کنید.',
                'SMARK_dashboard' => 'داشبورد اسمارک',
                'select_or_create_project' => 'انتخاب یا ایجاد پروژه',
                'choose_existing_project' => 'یک پروژه موجود را انتخاب کنید یا یک پروژه جدید ایجاد کنید',
                'select_project' => 'انتخاب پروژه:',
                'loading' => 'در حال بارگذاری...',
                'or' => 'یا',
                'create_new_project' => 'ایجاد پروژه جدید',
                'project_name' => 'نام پروژه:',
                'enter_project_name' => 'نام پروژه را وارد کنید...',
                'create' => 'ایجاد',
                'cancel' => 'لغو',
                'project_items' => 'آیتم‌های پروژه',
                'project_suggestions' => 'پیشنهادهای پروژه',
                'add_new_item' => 'افزودن آیتم جدید',
                'id' => 'شناسه',
                'headline' => 'عنوان',
                'visual' => 'تصویر/ویدیو',
                'created' => 'تاریخ ایجاد',
                'actions' => 'عملیات',
                'no_items_found' => 'آیتمی یافت نشد',
                'no_suggestions_found' => 'پیشنهادی یافت نشد',
                'view' => 'مشاهده',
                'viewSuggestion' => 'مشاهده پیشنهاد',
                'transferToItems' => 'انتقال به آیتم‌ها',
                'confirmTransferSuggestion' => 'آیا مطمئنید که می‌خواهید این پیشنهاد را به آیتم‌ها منتقل کنید؟',
                'confirmDeleteSuggestion' => 'آیا مطمئنید که می‌خواهید این پیشنهاد را حذف کنید؟',
                'errorLoadingSuggestion' => 'خطا در بارگذاری پیشنهاد',
                'errorTransferringSuggestion' => 'خطا در انتقال پیشنهاد',
                'errorDeletingSuggestion' => 'خطا در حذف پیشنهاد',
                'suggestion_transferred_successfully' => 'پیشنهاد با موفقیت منتقل شد',
                'suggestion_deleted_successfully' => 'پیشنهاد با موفقیت حذف شد',
                'updateSuggestion' => 'بروزرسانی پیشنهاد',
                'suggestionUpdatedSuccessfully' => 'پیشنهاد با موفقیت بروزرسانی شد',
                'select_a_project' => 'پروژه جاری',
                'please_select_project' => 'برای مشاهده آیتم‌ها، پروژه جاری را از بخش تنظیمات پروژه انتخاب کنید.',
                'success' => 'موفق!',
                'error' => 'خطا!',
                'edit' => 'ویرایش',
                'delete' => 'حذف',
                'verified' => 'کاملاً تایید شده',
                'partially_verified' => 'تایید جزئی',
                'unverified' => 'تایید نشده',
                'status' => 'وضعیت',
                'needs_approval' => 'نیاز به تایید متخصص',
                'sent_to_expert' => 'برای متخصص ارسال شد',
                'approved_by_expert' => 'توسط متخصص تایید شد',
                'published' => 'منتشر شده',
                'score' => 'امتیاز',
                'add_new_item' => 'افزودن آیتم جدید',
                'edit_item' => 'ویرایش آیتم',
                'headline_label' => 'عنوان:',
                'enter_headline' => 'عنوان خود را وارد کنید...',
                'analyze_headline' => 'تحلیل عنوان',
                'analysis_results' => 'نتایج تحلیل:',
                'no_analysis_performed' => 'تحلیلی انجام نشده',
                'visual_label' => 'تصویر/ویدیو:',
                'content_link_label' => 'لینک محتوا:',
                'enter_content_link' => 'لینک کانوا یا درایو را وارد کنید...',
                'content_link_help_text' => 'اختیاری. لینک مرجع طراحی یا فایل اصلی.',
                'visual_text_label' => 'متن ویدئو یا تصویر:',
                'enter_visual_text' => 'متن استفاده شده در ویدئو یا تصویر را وارد کنید...',
                'caption_label' => 'کپشن:',
                'enter_caption' => 'کپشن خود را وارد کنید...',
                'source_label' => 'منبع:',
                'enter_source' => 'اطلاعات منبع را وارد کنید...',
                'source_help_text' => 'اطلاعات منبع هنگام انتقال از سایر فیچرها به طور خودکار پر می‌شود.',
                'choose_file' => 'انتخاب فایل',
                'published_link_label' => 'لینک انتشار:',
                'enter_published_link' => 'لینک پست منتشرشده را وارد کنید...',
                'published_link_help_text' => 'اختیاری. لینک پست نهایی در شبکه اجتماعی.',
                'save_item' => 'ذخیره آیتم',
                'saving' => 'در حال ذخیره...',
                'update_item' => 'به‌روزرسانی آیتم',
                'updating' => 'در حال به‌روزرسانی...',
                'cancel_btn' => 'لغو',
                'headline_analysis' => 'تحلیل عنوان',
                'score' => 'امتیاز',
                'characters' => 'کاراکترها',
                'words' => 'کلمات',
                'has_numbers' => 'دارای اعداد',
                'gains_pains' => 'منفعت‌ساز و دردسرکاه',
                'ai_analysis' => 'تحلیل هوش مصنوعی:',
                'expert_approval_status' => 'وضعیت تایید متخصص:',
                'needs_expert_approval' => 'نیاز به تایید متخصص',
                'sent_to_expert' => 'برای متخصص ارسال شد',
                'approved_by_expert' => 'توسط متخصص تایید شد',
                'item_added_successfully' => 'آیتم با موفقیت اضافه شد',
                'item_updated_successfully' => 'آیتم با موفقیت به‌روزرسانی شد',
                'item_deleted_successfully' => 'آیتم با موفقیت حذف شد',
                'failed_to_add_item' => 'خطا در اضافه کردن آیتم',
                'failed_to_update_item' => 'خطا در به‌روزرسانی آیتم',
                'failed_to_delete_item' => 'خطا در حذف آیتم',
            )
        );

        return isset($translations[$lang][$key]) ? $translations[$lang][$key] : $key;
    }

    /**
     * Render the social media page
     */
    public function render_page() {
        $current_lang = get_option('SMARK_panel_language', 'en');
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';
        $is_rtl = ($current_lang === 'fa');
        ?>
        <div class="wrap smark-social-media-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
            <div class="smark-page-header">
                <h1><?php echo esc_html($this->get_translation('social_media_designer')); ?></h1>
                <p class="description"><?php echo esc_html($this->get_translation('create_stunning')); ?></p>
            </div>

            <div class="smark-breadcrumb">
                <div class="breadcrumb-left">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html($this->get_translation('SMARK_dashboard')); ?></a>
                    <span class="separator"><?php echo $is_rtl ? '‹' : '›'; ?></span>
                    <span class="current"><?php echo esc_html($this->get_translation('social_media_designer')); ?></span>
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

            <div class="smark-social-media-content">
                <div class="content-grid">
                    <!-- Data Table -->
                    <div class="right-column">
                        <div class="data-table-card" id="data_table_card" style="display: none;">
                            <div class="card">
                                <div class="card-header-with-button">
                                    <div>
                                        <h3><?php echo esc_html($this->get_translation('project_items')); ?></h3>
                                    </div>
                                    <button type="button" id="add_new_item_btn" class="btn btn-primary">
                                        <span class="dashicons dashicons-plus-alt"></span>
                                        <?php echo esc_html($this->get_translation('add_new_item')); ?>
                                    </button>
                                </div>

                                <div class="table-wrapper">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo esc_html($this->get_translation('id')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('headline')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('visual')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('created')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('status')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('score')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('actions')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="data_table_body">
                                            <tr class="no-data-row">
                                                <td colspan="7"><?php echo esc_html($this->get_translation('no_items_found')); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Project Suggestions Section -->
                        <div class="data-table-card suggestions-section" id="suggestions_table_card" style="display: none;">
                            <div class="card">
                                <div class="card-header-with-button">
                                    <div>
                                        <h3><?php echo esc_html($this->get_translation('project_suggestions')); ?></h3>
                                    </div>
                                </div>

                                <div class="table-wrapper">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo esc_html($this->get_translation('id')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('headline')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('visual')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('created')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('score')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('actions')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="suggestions_table_body">
                                            <tr class="no-data-row">
                                                <td colspan="6"><?php echo esc_html($this->get_translation('no_suggestions_found')); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div class="empty-state-card" id="empty_state" style="display: block;">
                            <div class="card">
                                <div class="empty-state-content">
                                    <span class="dashicons dashicons-portfolio"></span>
                                    <h3><?php echo esc_html($this->get_translation('select_a_project')); ?></h3>
                                    <p><?php echo esc_html($this->get_translation('please_select_project')); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Item Modal -->
            <div id="add_item_modal" class="smark-modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="modal_title"><?php echo esc_html($this->get_translation('add_new_item')); ?></h3>
                        <button type="button" class="modal-close" id="close_modal">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="add_item_form">
                            <input type="hidden" id="item_id" value="">
                            <input type="hidden" id="suggestion_id" value="">
                            <input type="hidden" id="is_viewing_suggestion" value="0">
                            <div class="form-group">
                                <label for="item_headline"><?php echo esc_html($this->get_translation('headline_label')); ?></label>
                                <div class="textarea-wrapper">
                                    <textarea id="item_headline" name="headline" class="form-control" rows="4" placeholder="<?php echo esc_attr($this->get_translation('enter_headline')); ?>" maxlength="500"></textarea>
                                    <span class="char-counter-inside">0 / 500</span>
                                </div>
                                <div class="headline-button-row">
                                    <button type="button" id="create_attractive_title_btn" class="btn btn-create-title">
                                        <span class="dashicons dashicons-lightbulb"></span>
                                        <?php echo esc_html($current_lang === 'fa' ? 'ساخت عنوان جذاب' : 'Create Attractive Title'); ?>
                                    </button>
                                    <button type="button" id="create_title_with_gpt_btn" class="btn btn-create-title-gpt">
                                        <span class="dashicons dashicons-admin-site"></span>
                                        <?php echo esc_html($current_lang === 'fa' ? 'ساخت عنوان با GPT' : 'Create Title with GPT'); ?>
                                    </button>
                                    <button type="button" id="analyze_headline_btn" class="btn btn-analyze">
                                        <span class="dashicons dashicons-chart-line"></span>
                                        <?php echo esc_html($this->get_translation('analyze_headline')); ?>
                                    </button>
                                </div>

                                <!-- Analysis Results Display Area -->
                                <div id="headline_analysis_results_display" class="analysis-results-display" style="display: none;">
                                    <div class="analysis-results-content">
                                        <div class="analysis-results-header">
                                            <span class="dashicons dashicons-chart-bar"></span>
                                            <strong><?php echo esc_html($this->get_translation('analysis_results')); ?></strong>
                                        </div>
                                        <div class="analysis-results-body">
                                            <div id="analysis_results_content"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- No Analysis Message -->
                                <div id="no_analysis_message" class="no-analysis-message">
                                    <div class="no-analysis-content">
                                        <span class="dashicons dashicons-info"></span>
                                        <span><?php echo esc_html($this->get_translation('no_analysis_performed')); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="item_visual_text"><?php echo esc_html($this->get_translation('visual_text_label')); ?></label>
                                <textarea id="item_visual_text" name="visual_text" class="form-control" rows="3" placeholder="<?php echo esc_attr($this->get_translation('enter_visual_text')); ?>"></textarea>

                                <!-- Visual Text GPT Button -->
                                <div class="visual-text-button-row">
                                    <button type="button" id="create_visual_text_with_gpt_btn" class="btn btn-create-visual-text-gpt">
                                        <span class="dashicons dashicons-admin-site"></span>
                                        <?php echo esc_html($current_lang === 'fa' ? 'ساخت متن با GPT' : 'Create Text with GPT'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="item_visual"><?php echo esc_html($this->get_translation('visual_label')); ?></label>
                                <input type="hidden" id="item_visual" name="visual" value="">
                                <input type="hidden" id="item_visual_type" name="visual_type" value="">
                                <div class="visual-upload-area">
                                    <div id="visual_preview" class="visual-preview" style="display: none;">
                                        <div class="preview-wrapper"></div>
                                        <button type="button" class="remove-visual" title="<?php echo esc_attr__('Remove', 'smark'); ?>">
                                            <span class="dashicons dashicons-no"></span>
                                        </button>
                                    </div>
                                    <div id="upload_button_wrapper" class="upload-button-wrapper">
                                        <input type="file" id="visual_file_input" accept="image/*,video/*" style="display: none;">
                                        <button type="button" id="select_visual_btn" class="btn btn-secondary">
                                            <span class="dashicons dashicons-format-image"></span>
                                            <?php echo esc_html($this->get_translation('choose_file')); ?>
                                        </button>
                                        <span class="upload-hint"><?php echo esc_html__('تصاویر و ویدیوها تا ۱۰ مگابایت', 'smark'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="item_content_link"><?php echo esc_html($this->get_translation('content_link_label')); ?></label>
                                <input type="url" id="item_content_link" name="content_link" class="form-control" placeholder="<?php echo esc_attr($this->get_translation('enter_content_link')); ?>">

                                <!-- Canva Template Copy Button -->
                                <div class="visual-text-button-row">
                                    <button type="button" id="copy_canva_template_btn" class="btn btn-copy-canva-template">
                                        <svg width="18" height="18" viewBox="0 0 500 500" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M382.92 196.36C382.92 214.83 367.88 229.87 349.41 229.87C330.94 229.87 315.9 214.83 315.9 196.36C315.9 177.89 330.94 162.85 349.41 162.85C367.88 162.85 382.92 177.89 382.92 196.36Z" fill="white"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M120.2 54.42C86.67 54.42 59.48 81.61 59.48 115.14V384.86C59.48 418.39 86.67 445.58 120.2 445.58H379.92C413.45 445.58 440.64 418.39 440.64 384.86V115.14C440.64 81.61 413.45 54.42 379.92 54.42H120.2ZM338.81 133.08C320.14 133.08 305.05 148.17 305.05 166.84C305.05 177.29 309.77 186.65 317.03 193.2L195.76 279.85C192.81 274.09 187.59 269.71 181.26 267.99C174.93 266.27 168.2 267.38 162.72 270.99L93.99 316.22V115.14C93.99 100.68 105.74 88.93 120.2 88.93H379.92C394.38 88.93 406.13 100.68 406.13 115.14V248.68L370.41 219.78C363.86 214.55 355.26 212.96 347.37 215.53C339.48 218.1 333.22 224.43 330.74 232.36L305.87 188.42C312.59 181.65 316.97 172.63 316.97 162.52C316.97 144.09 302.08 129.2 283.65 129.2C265.22 129.2 250.33 144.09 250.33 162.52C250.33 180.95 265.22 195.84 283.65 195.84C291.06 195.84 297.85 193.56 303.35 189.66L328.22 233.6C322.05 240.6 318.93 250.03 320.48 259.44C322.03 268.85 328.09 276.82 336.58 281.06L280.02 348.49C274.22 344.18 266.95 342.12 259.65 343.01C252.35 343.9 245.78 347.65 241.47 353.45L206.42 281.56C212.33 274.92 215.76 266.23 215.76 256.76C215.76 238.33 200.87 223.44 182.44 223.44C164.01 223.44 149.12 238.33 149.12 256.76C149.12 275.19 164.01 290.08 182.44 290.08C187.63 290.08 192.53 289 196.98 287.09L232.03 358.98C227.83 364.78 225.77 372.05 226.66 379.35C227.55 386.65 231.3 393.22 237.1 397.53C242.9 401.84 250.17 403.9 257.47 403.01C264.77 402.12 271.34 398.37 275.65 392.57L332.21 325.14C338.89 326.81 346.01 325.72 351.96 321.97C357.91 318.22 362.11 312.23 363.78 305.54L406.13 333.89V384.86C406.13 399.32 394.38 411.07 379.92 411.07H120.2C105.74 411.07 93.99 399.32 93.99 384.86V349.7L162.72 304.47C168.2 308.08 174.93 309.19 181.26 307.47C187.59 305.75 192.81 301.37 195.76 295.61L317.03 382.26C309.77 388.81 305.05 398.17 305.05 408.62C305.05 427.29 320.14 442.38 338.81 442.38C357.48 442.38 372.57 427.29 372.57 408.62C372.57 389.95 357.48 374.86 338.81 374.86C331.4 374.86 324.61 377.14 319.11 381.04L197.84 294.39C199.33 289.43 200.13 284.16 200.13 278.69C200.13 260.26 185.24 245.37 166.81 245.37C148.38 245.37 133.49 260.26 133.49 278.69C133.49 297.12 148.38 312.01 166.81 312.01C174.22 312.01 181.01 309.73 186.51 305.83L255.24 360.6C254.41 365.15 254.13 369.82 254.42 374.48L212.45 402.51C205.73 395.74 196.71 391.36 186.6 391.36C168.17 391.36 153.28 406.25 153.28 424.68C153.28 443.11 168.17 458 186.6 458C205.03 458 219.92 443.11 219.92 424.68C219.92 417.27 217.64 410.48 213.74 404.98L255.71 376.95C260.67 378.44 265.94 379.24 271.41 379.24C289.84 379.24 304.73 364.35 304.73 345.92C304.73 327.49 289.84 312.6 271.41 312.6C253 312.6 238.11 327.47 238.09 345.88L197.84 378.04C195.38 375.92 192.65 374.13 189.73 372.7L223.85 305.12C229.33 308.73 236.06 310.84 243.39 310.95C252.8 311.09 261.73 307.87 268.73 301.77L325.29 369.2C322.51 373.6 320.66 378.66 320.02 384.02L280.02 412.52C273.3 405.75 264.28 401.37 254.17 401.37C235.74 401.37 220.85 416.26 220.85 434.69C220.85 453.12 235.74 468.01 254.17 468.01C272.6 468.01 287.49 453.12 287.49 434.69C287.49 427.28 285.21 420.49 281.31 414.99L321.31 386.49C326.27 387.98 331.54 388.78 337.01 388.78C355.44 388.78 370.33 373.89 370.33 355.46C370.33 337.03 355.44 322.14 337.01 322.14C318.58 322.14 303.69 337.03 303.69 355.46C303.69 360.65 304.77 365.55 306.68 369.94L250.12 302.51C254.43 296.71 256.49 289.44 255.6 282.14C254.71 274.84 250.96 268.27 245.16 263.96C239.36 259.65 232.09 257.59 224.79 258.48C217.49 259.37 210.92 263.12 206.61 268.92L149.25 198.69C151.71 192.84 153.1 186.47 153.1 179.77C153.1 161.34 138.21 146.45 119.78 146.45C101.35 146.45 86.46 161.34 86.46 179.77C86.46 198.2 101.35 213.09 119.78 213.09C128.19 213.09 135.98 209.81 141.98 204.61L199.34 274.84C195.03 280.64 192.97 287.91 193.86 295.21C194.75 302.51 198.5 309.08 204.3 313.39C210.1 317.7 217.37 319.76 224.67 318.87C231.97 317.98 238.54 314.23 242.85 308.43L299.41 375.86C296.63 380.26 294.78 385.32 294.14 390.68L254.14 419.18C247.42 412.41 238.4 408.03 228.29 408.03C209.86 408.03 194.97 422.92 194.97 441.35C194.97 459.78 209.86 474.67 228.29 474.67C246.72 474.67 261.61 459.78 261.61 441.35C261.61 433.94 259.33 427.15 255.43 421.65L295.43 393.15C300.39 394.64 305.66 395.44 311.13 395.44C329.56 395.44 344.45 380.55 344.45 362.12C344.45 343.69 329.56 328.8 311.13 328.8C292.7 328.8 277.81 343.69 277.81 362.12C277.81 367.31 278.89 372.21 280.8 376.6L224.24 309.17C228.55 303.37 230.61 296.1 229.72 288.8C228.83 281.5 225.08 274.93 219.28 270.62C213.48 266.31 206.21 264.25 198.91 265.14C191.61 266.03 185.04 269.78 180.73 275.58L123.37 205.35C125.83 199.5 127.22 193.13 127.22 186.43C127.22 168 112.33 153.11 93.9 153.11C75.47 153.11 60.58 168 60.58 186.43C60.58 204.86 75.47 219.75 93.9 219.75C102.31 219.75 110.1 216.47 116.1 211.27L173.46 281.5C169.15 287.3 167.09 294.57 167.98 301.87C168.87 309.17 172.62 315.74 178.42 320.05C184.22 324.36 191.49 326.42 198.79 325.53C206.09 324.64 212.66 320.89 216.97 315.09L274.33 385.32C271.87 391.17 270.48 397.54 270.48 404.24C270.48 422.67 285.37 437.56 303.8 437.56C322.23 437.56 337.12 422.67 337.12 404.24C337.12 385.81 322.23 370.92 303.8 370.92C295.39 370.92 287.6 374.2 281.6 379.4L224.24 309.17C226.7 303.32 228.09 296.95 228.09 290.25C228.09 271.82 213.2 256.93 194.77 256.93C176.34 256.93 161.45 271.82 161.45 290.25C161.45 308.68 176.34 323.57 194.77 323.57C203.18 323.57 210.97 320.29 216.97 315.09L274.33 385.32C271.87 391.17 270.48 397.54 270.48 404.24C270.48 422.67 285.37 437.56 303.8 437.56C322.23 437.56 337.12 422.67 337.12 404.24C337.12 385.81 322.23 370.92 303.8 370.92" fill="white"/>
                                        </svg>
                                        <?php echo esc_html($current_lang === 'fa' ? 'کپی قالب کانوا' : 'Copy Canva Template'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="form-divider"></div>

                            <div class="form-group">
                                <label for="item_caption"><?php echo esc_html($this->get_translation('caption_label')); ?></label>
                                <textarea id="item_caption" name="caption" class="form-control" rows="4" placeholder="<?php echo esc_attr($this->get_translation('enter_caption')); ?>"></textarea>

                                <!-- Caption GPT Button -->
                                <div class="visual-text-button-row">
                                    <button type="button" id="create_caption_with_gpt_btn" class="btn btn-create-visual-text-gpt">
                                        <span class="dashicons dashicons-admin-site"></span>
                                        <?php echo esc_html($current_lang === 'fa' ? 'ساخت متن با GPT' : 'Create Text with GPT'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="form-divider"></div>

                            <div class="form-group">
                                <label for="item_source"><?php echo esc_html($this->get_translation('source_label')); ?></label>
                                <input type="text" id="item_source" name="source" class="form-control" placeholder="<?php echo esc_attr($this->get_translation('enter_source')); ?>">
                                <small class="form-text text-muted"><?php echo esc_html($this->get_translation('source_help_text')); ?></small>
                            </div>

                            <div class="form-divider"></div>

                            <div class="form-group">
                                <label for="item_published_link"><?php echo esc_html($this->get_translation('published_link_label')); ?></label>
                                <input type="url" id="item_published_link" name="published_link" class="form-control" placeholder="<?php echo esc_attr($this->get_translation('enter_published_link')); ?>">
                                <small class="form-text text-muted"><?php echo esc_html($this->get_translation('published_link_help_text')); ?></small>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <!-- Expert Approval Status Dropdown moved next to action buttons -->
                        <div class="expert-approval-section">
                            <select id="expert_approval_status" name="expert_approval_status" class="expert-status-select">
                                <option value="needs_approval"><?php echo esc_html($this->get_translation('needs_expert_approval')); ?></option>
                                <option value="sent_to_expert"><?php echo esc_html($this->get_translation('sent_to_expert')); ?></option>
                                <option value="approved_by_expert"><?php echo esc_html($this->get_translation('approved_by_expert')); ?></option>
                                <option value="published"><?php echo esc_html($this->get_translation('published')); ?></option>
                            </select>
                        </div>
                        <button type="button" id="cancel_item_btn" class="btn btn-secondary">
                            <span class="dashicons dashicons-no-alt"></span>
                            <?php echo esc_html($this->get_translation('cancel_btn')); ?>
                        </button>
                        <button type="button" id="save_item_btn" class="btn btn-primary">
                            <span class="dashicons dashicons-yes"></span>
                            <span id="save_btn_text"><?php echo esc_html($this->get_translation('save_item')); ?></span>
                        </button>
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
        </div>

        <!-- ChatGPT Auto-fill Script -->
        <script>
        // This script runs on ChatGPT page to auto-fill the prompt
        (function() {
            // Check if we're on ChatGPT page
            if (window.location.hostname === 'chatgpt.com') {
                // Listen for messages from the parent window
                window.addEventListener('message', function(event) {
                    if (event.data && event.data.type === 'SMARK_FILL_PROMPT') {
                        fillChatGPTPrompt(event.data.prompt);
                    }
                });

                // Check URL parameters for prompt
                function checkURLPrompt() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const prompt = urlParams.get('prompt');
                    if (prompt) {
                        fillChatGPTPrompt(decodeURIComponent(prompt));
                        // Clean up URL
                        const newUrl = window.location.origin + window.location.pathname;
                        window.history.replaceState({}, document.title, newUrl);
                    }
                }

                // Also check localStorage for stored prompt
                function checkStoredPrompt() {
                    const storedPrompt = localStorage.getItem('SMARK_gpt_prompt');
                    const timestamp = localStorage.getItem('SMARK_gpt_timestamp');

                    if (storedPrompt && timestamp) {
                        const now = Date.now();
                        const storedTime = parseInt(timestamp);

                        // If the prompt was stored within the last 30 seconds, use it
                        if (now - storedTime < 30000) {
                            fillChatGPTPrompt(storedPrompt);
                            // Clear the stored prompt after using it
                            localStorage.removeItem('SMARK_gpt_prompt');
                            localStorage.removeItem('SMARK_gpt_timestamp');
                        }
                    }
                }

                function fillChatGPTPrompt(prompt) {
                    const selectors = [
                        'textarea[placeholder*="Message"]',
                        'textarea[placeholder*="پیام"]',
                        '#prompt-textarea',
                        '[data-testid="textbox"]',
                        'textarea[role="textbox"]',
                        'textarea[placeholder*="Send a message"]',
                        'textarea[placeholder*="ارسال پیام"]',
                        'textarea[data-id="root"]',
                        'div[contenteditable="true"]',
                        'textarea',
                        'input[type="text"]',
                        'div[role="textbox"]'
                    ];

                    let input = null;
                    for (let selector of selectors) {
                        input = document.querySelector(selector);
                        if (input) break;
                    }

                    if (input) {
                        // Set the value
                        if (input.tagName === 'DIV' && input.contentEditable === 'true') {
                            input.textContent = prompt;
                            input.innerHTML = prompt;
                        } else {
                            input.value = prompt;
                        }

                        // Trigger events
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        input.dispatchEvent(new Event('keyup', { bubbles: true }));
                        input.dispatchEvent(new Event('keydown', { bubbles: true }));

                        // Focus the input
                        input.focus();
                        input.click();

                        // Try to trigger the send button after a short delay
                        setTimeout(function() {
                            const sendButton = document.querySelector('button[data-testid="send-button"], button[aria-label*="Send"], button[title*="Send"], button:has(svg)');
                            if (sendButton) {
                                sendButton.click();
                            }
                        }, 1000);
                    }
                }

                // Check for URL prompt and stored prompt when page loads
                setTimeout(function() {
                    checkURLPrompt();
                    checkStoredPrompt();
                }, 2000);
            }
        })();
        </script>
        <?php
    }
}

// Don't initialize here - it's initialized in the main SMark plugin file.

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
