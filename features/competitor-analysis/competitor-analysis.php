<?php
/**
 * Competitor Analysis Feature
 * Analyze competitor websites and track their new pages/blog posts
 */

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

class SMarkCompetitorAnalysis {

    /**
     * Database table names
     */
    private $table_name;
    private $projects_table;
    private $fetched_items_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'SMARK_competitor_items';
        $this->projects_table = $this->resolve_projects_table();
        $this->fetched_items_table = $wpdb->prefix . 'SMARK_competitor_fetched';

        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_head', array($this, 'print_embed_admin_styles'));

        // AJAX actions
        add_action('wp_ajax_SMARK_competitor_get_projects', array($this, 'ajax_get_projects'));
        add_action('wp_ajax_SMARK_competitor_create_project', array($this, 'ajax_create_project'));
        add_action('wp_ajax_SMARK_competitor_get_project_items', array($this, 'ajax_get_project_items'));
        add_action('wp_ajax_SMARK_competitor_add_item', array($this, 'ajax_add_item'));
        add_action('wp_ajax_SMARK_competitor_update_item', array($this, 'ajax_update_item'));
        add_action('wp_ajax_SMARK_competitor_delete_item', array($this, 'ajax_delete_item'));
        add_action('wp_ajax_SMARK_competitor_get_item', array($this, 'ajax_get_item'));
        add_action('wp_ajax_SMARK_competitor_fetch_pages', array($this, 'ajax_fetch_pages'));
        add_action('wp_ajax_SMARK_competitor_save_pages', array($this, 'ajax_save_pages'));
        add_action('wp_ajax_SMARK_competitor_get_saved_pages', array($this, 'ajax_get_saved_pages'));
        add_action('wp_ajax_SMARK_competitor_get_archived_pages', array($this, 'ajax_get_archived_pages'));
        add_action('wp_ajax_SMARK_competitor_mark_reviewed', array($this, 'ajax_mark_reviewed'));
        add_action('wp_ajax_SMARK_competitor_send_to_social', array($this, 'ajax_send_to_social'));
        add_action('wp_ajax_SMARK_save_language', array($this, 'ajax_save_language'));
        // Defer database operations to avoid headers already sent errors
        add_action('init', array($this, 'init_database_operations'), 20);
    }

    private function escape_db_identifier($identifier) {
        if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            return '';
        }

        return '`' . str_replace('`', '', esc_sql($identifier)) . '`';
    }

    private function resolve_projects_table() {
        global $wpdb;

        $prefix = $wpdb->prefix;
        $candidates = array($prefix . 'SMARK_projects', $prefix . 'smark_projects');
        $existing = array();

        foreach ($candidates as $table) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($found === $table) {
                $existing[] = $table;
            }
        }

        if (count($existing) > 1) {
            foreach ($existing as $table) {
                if ($this->table_has_column($table, 'website')) {
                    return $table;
                }
            }
        }

        if (!empty($existing)) {
            return $existing[0];
        }

        return $prefix . 'SMARK_projects';
    }

    private function table_has_column($table_name, $column) {
        global $wpdb;

        $table_sql = $this->escape_db_identifier($table_name);
        if ($table_sql === '') {
            return false;
        }

        $found = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_sql} LIKE %s", (string) $column)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return !empty($found);
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

    private function log_error($message, $context = array()) {
        if (class_exists('SMarkLogger', false)) {
            SMarkLogger::error($message, $context);
        }
    }

    /**
     * Initialize database operations after WordPress is fully loaded
     */
    public function init_database_operations() {
        // Check and create table if needed
        $this->maybe_create_table();

        // Update database schema if needed
        $this->update_database_schema();

        // Update social media suggestions table
        $this->update_social_media_suggestions_table();

        // Clean up existing undefined values in database
        $this->cleanup_undefined_values();

        // Sync project IDs for existing items (one-time sync for items missing project_id)
        $this->sync_project_ids();
    }

    /**
     * Update database schema
     */
    private function update_database_schema() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $fetched_table_sql = $this->escape_db_identifier($this->fetched_items_table);
        $items_table_sql = $this->escape_db_identifier($this->table_name);
        if (empty($fetched_table_sql) || empty($items_table_sql)) {
            return;
        }

        // Check if is_reviewed column exists
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$fetched_table_sql} LIKE %s", 'is_reviewed'));

        if (empty($column_exists)) {
            // Add is_reviewed column
            $wpdb->query("ALTER TABLE {$fetched_table_sql} ADD COLUMN is_reviewed tinyint(1) DEFAULT 0");
            $this->log_debug('Added is_reviewed column to competitor fetched items table');
        }

        // Check if reviewed_at column exists
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$fetched_table_sql} LIKE %s", 'reviewed_at'));

        if (empty($column_exists)) {
            // Add reviewed_at column
            $wpdb->query("ALTER TABLE {$fetched_table_sql} ADD COLUMN reviewed_at datetime DEFAULT NULL");
            $this->log_debug('Added reviewed_at column to competitor fetched items table');
        }

        // Check if project_id column exists in competitor_items table
        $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$items_table_sql} LIKE %s", 'project_id'));

        if (empty($column_exists)) {
            // Add project_id column
            $wpdb->query("ALTER TABLE {$items_table_sql} ADD COLUMN project_id varchar(50) DEFAULT NULL AFTER project");
            $wpdb->query("ALTER TABLE {$items_table_sql} ADD KEY project_id_index (project_id)");
            $this->log_debug('Added project_id column to competitor items table');

            // Sync existing project_ids
            $this->sync_project_ids();
        }

        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Sync project IDs from projects table to competitor items
     */
    private function sync_project_ids() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $items_table_sql = $this->escape_db_identifier($this->table_name);
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if (empty($items_table_sql) || empty($projects_table_sql)) {
            return;
        }

        // Update project_id for all items that have a matching project in projects table
        $result = $wpdb->query("UPDATE {$items_table_sql} AS ci INNER JOIN {$projects_table_sql} AS p ON ci.project = p.project_name SET ci.project_id = p.project_id WHERE ci.project_id IS NULL OR ci.project_id = ''");

        if ($result !== false && $result > 0) {
            $this->log_debug('Synced project_id for competitor items', array('count' => (int) $result));
        }

        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Clean up undefined values in database
     */
    private function cleanup_undefined_values() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $items_table_sql = $this->escape_db_identifier($this->table_name);
        if (empty($items_table_sql)) {
            return;
        }

        // Update undefined website_url values to NULL
        $wpdb->query($wpdb->prepare(
            "UPDATE {$items_table_sql} SET website_url = NULL WHERE website_url = %s OR website_url = %s OR website_url = %s OR website_url = %s",
            'undefined', 'null', 'N/A', ''
        ));

        // Update undefined website_name values to NULL
        $wpdb->query($wpdb->prepare(
            "UPDATE {$items_table_sql} SET website_name = NULL WHERE website_name = %s OR website_name = %s OR website_name = %s OR website_name = %s",
            'undefined', 'null', 'N/A', ''
        ));

        $this->log_debug('SMark Competitor Analysis: Cleaned up undefined values in database');

        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Check if table exists and create if needed
     */
    private function maybe_create_table() {
        global $wpdb;

        // Check Competitor Items table
        $items_table_version = get_option('SMARK_competitor_items_table_version', '0');
        $current_version = '1.0';

        if ($items_table_version !== $current_version) {
            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($table_exists != $this->table_name) {
                $this->create_items_table();
            }
            update_option('SMARK_competitor_items_table_version', $current_version);
        }

        // Check Fetched Items table
        $fetched_table_version = get_option('SMARK_competitor_fetched_table_version', '0');
        $fetched_current_version = '1.0';

        if ($fetched_table_version !== $fetched_current_version) {
            $fetched_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->fetched_items_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($fetched_table_exists != $this->fetched_items_table) {
                $this->create_fetched_items_table();
            }
            update_option('SMARK_competitor_fetched_table_version', $fetched_current_version);
        }

        // Check Projects table (shared with other features)
        $projects_table_version = get_option('SMARK_projects_table_version', '0');
        $projects_current_version = '1.0';

        if ($projects_table_version !== $projects_current_version) {
            $projects_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->projects_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($projects_exists != $this->projects_table) {
                $this->create_projects_table();
                update_option('SMARK_projects_table_version', $projects_current_version);
            }
        }

        // Check Social Media Suggestions table (needed for send to social functionality)
        $social_media_suggestions_table = $wpdb->prefix . 'SMARK_social_media_suggestions';
        $suggestions_table_version = get_option('SMARK_social_media_suggestions_table_version', '0');
        $suggestions_current_version = '1.0';

        if ($suggestions_table_version !== $suggestions_current_version) {
            $suggestions_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $social_media_suggestions_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($suggestions_exists != $social_media_suggestions_table) {
                $this->create_social_media_suggestions_table();
                update_option('SMARK_social_media_suggestions_table_version', $suggestions_current_version);
            }
        }
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

        $this->log_debug('SMark Projects table created successfully', array('table' => $this->projects_table));
    }

    /**
     * Create competitor items table
     */
    private function create_items_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project varchar(255) NOT NULL,
            project_id varchar(50) DEFAULT NULL,
            website_url varchar(500) NOT NULL,
            website_name varchar(255) DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_index (project),
            KEY project_id_index (project_id)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta($sql);

        $this->log_debug('SMark Competitor Items table created successfully', array('table' => $this->table_name));
    }

    /**
     * Create fetched items table
     */
    private function create_fetched_items_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->fetched_items_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            competitor_item_id bigint(20) NOT NULL,
            page_url varchar(500) NOT NULL,
            page_title text DEFAULT NULL,
            page_type varchar(50) DEFAULT 'page',
            published_date datetime DEFAULT NULL,
            discovered_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_reviewed tinyint(1) DEFAULT 0,
            reviewed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_page_url (competitor_item_id, page_url),
            KEY competitor_item_index (competitor_item_id),
            KEY is_reviewed (is_reviewed)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta($sql);

        $this->log_debug('SMark Competitor Fetched Items table created successfully', array('table' => $this->fetched_items_table));
    }

    /**
     * Create social media suggestions table
     */
    private function create_social_media_suggestions_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $social_media_suggestions_table = $wpdb->prefix . 'SMARK_social_media_suggestions';

        $sql = "CREATE TABLE IF NOT EXISTS $social_media_suggestions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project varchar(255) NOT NULL,
            headline text NOT NULL,
            caption text DEFAULT NULL,
            visual varchar(500) DEFAULT NULL,
            visual_type varchar(50) DEFAULT NULL,
            visual_text text DEFAULT NULL,
            expert_approval_status varchar(20) DEFAULT 'needs_approval',
            score int(3) DEFAULT 0,
            source varchar(500) DEFAULT NULL,
            source_url text DEFAULT NULL,
            source_type varchar(50) DEFAULT 'manual',
            competitor_name varchar(255) DEFAULT NULL,
            published_date datetime DEFAULT NULL,
            discovered_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_index (project),
            KEY source_type (source_type)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta($sql);

        // Check if table was created successfully
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $social_media_suggestions_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($table_exists) {
            $this->log_debug('SMark Social Media Suggestions table created successfully', array('table' => $social_media_suggestions_table));
        } else {
            $this->log_error('Failed to create SMark Social Media Suggestions table', array('table' => $social_media_suggestions_table));
        }
    }

    /**
     * Update social media suggestions table to add missing columns
     */
    private function update_social_media_suggestions_table() {
        global $wpdb;

        $social_media_suggestions_table = $wpdb->prefix . 'SMARK_social_media_suggestions';
        $social_media_suggestions_table_sql = $this->escape_db_identifier($social_media_suggestions_table);
        if (empty($social_media_suggestions_table_sql)) {
            return;
        }

        // Check if table exists first
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $social_media_suggestions_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (!$table_exists) {
            $this->log_info('Social media suggestions table missing, creating', array('table' => $social_media_suggestions_table));
            $this->create_social_media_suggestions_table();
            return;
        }

        // Check and add missing columns
        $columns_to_add = array(
            'source' => 'varchar(500) DEFAULT NULL',
            'source_url' => 'text DEFAULT NULL',
            'source_type' => 'varchar(50) DEFAULT "manual"',
            'competitor_name' => 'varchar(255) DEFAULT NULL',
            'published_date' => 'datetime DEFAULT NULL',
            'discovered_at' => 'datetime DEFAULT NULL'
        );

        foreach ($columns_to_add as $column_name => $column_definition) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $column_sql = $this->escape_db_identifier((string) $column_name);
            if (empty($column_sql)) {
                continue;
            }

            $column_exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$social_media_suggestions_table_sql} LIKE %s", $column_name));

            if (empty($column_exists)) {
                $result = $wpdb->query("ALTER TABLE {$social_media_suggestions_table_sql} ADD COLUMN {$column_sql} {$column_definition}");

                if ($result !== false) {
                    $this->log_info('Added column to social media suggestions table', array('column' => $column_name));
                } else {
                    $this->log_error('Failed to add column to social media suggestions table', array('column' => $column_name, 'error' => $wpdb->last_error));
                }
            }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }
    }

    /**
     * Get all projects
     */
    public function get_all_projects() {
        global $wpdb;

        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if (empty($projects_table_sql)) {
            return array();
        }

        $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT * FROM {$projects_table_sql} ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            ARRAY_A
        );

        return $results;
    }

    private function get_current_site_project() {
        global $wpdb;

        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if (empty($projects_table_sql) || !$this->table_has_column($this->projects_table, 'project_id')) {
            return null;
        }

        $project_db_id = (int) get_option('smark_current_project_db_id', 0);
        if ($project_db_id <= 0) {
            $project_db_id = (int) get_option('SMARK_current_project_db_id', 0);
        }

        if ($project_db_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, project_name, project_id FROM {$projects_table_sql} WHERE id = %d", $project_db_id), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (is_array($row) && !empty($row) && !empty($row['project_id'])) {
                return $row;
            }
        }

        $website = rtrim((string) home_url('/'), '/');
        if ($website !== '' && $this->table_has_column($this->projects_table, 'website')) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, project_name, project_id FROM {$projects_table_sql} WHERE website = %s ORDER BY id DESC LIMIT 1", $website), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (is_array($row) && !empty($row) && !empty($row['project_id'])) {
                update_option('smark_current_project_db_id', (int) $row['id'], false);
                return $row;
            }
        }

        $row = $wpdb->get_row("SELECT id, project_name, project_id FROM {$projects_table_sql} WHERE project_id IS NOT NULL AND project_id != '' ORDER BY id DESC LIMIT 1", ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if (is_array($row) && !empty($row) && !empty($row['project_id'])) {
            update_option('smark_current_project_db_id', (int) $row['id'], false);
            return $row;
        }

        return null;
    }

    /**
     * Create new project
     */
    public function create_project($project_name) {
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if (empty($projects_table_sql)) {
            return false;
        }

        // First insert the project to get the ID
        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->projects_table,
            array('project_name' => sanitize_text_field($project_name)),
            array('%s')
        );

        if ($result !== false) {
            $insert_id = $wpdb->insert_id;

            // Generate project_id
            $project_id = 'PRJ-' . str_pad($insert_id, 5, '0', STR_PAD_LEFT);

            // Check if this ID already exists (unlikely, but safe to check)
            $exists = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COUNT(*) FROM {$projects_table_sql} WHERE project_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $project_id
            ));

            // If it exists, add a random suffix
            if ($exists > 0) {
                $project_id = 'PRJ-' . str_pad($insert_id, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
            }

            // Update the project with the generated project_id
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
     * Check if project exists
     */
    public function project_exists($project_name) {
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if (empty($projects_table_sql)) {
            return false;
        }

        $count = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT COUNT(*) FROM {$projects_table_sql} WHERE project_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $project_name
        ));

        return $count > 0;
    }

    /**
     * AJAX: Get all projects
     */
    public function ajax_get_projects() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        // Check if table exists
        global $wpdb;
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->projects_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!$table_exists) {
            $this->create_projects_table();
        }

        $projects = $this->get_all_projects();

        wp_send_json_success($projects);
    }

    /**
     * AJAX: Create new project
     */
    public function ajax_create_project() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

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
            // Get the project_id that was just created
            global $wpdb;
            $projects_table_sql = $this->escape_db_identifier($this->projects_table);
            if (empty($projects_table_sql)) {
                wp_send_json_error(array('message' => __('Database table error', 'smark')));
            }
            $project_id = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT project_id FROM {$projects_table_sql} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $id
            ));

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
     * Get items for a specific project by project_id
     */
    private function get_project_items($project_id) {
        global $wpdb;
        $items_table_sql = $this->escape_db_identifier($this->table_name);
        if (empty($items_table_sql)) {
            return array();
        }

        $items = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT * FROM {$items_table_sql} WHERE project_id = %s ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $project_id
        ), ARRAY_A);

        // Clean up any undefined/null values before returning
        if ($items) {
            foreach ($items as &$item) {
                if (empty($item['website_url']) ||
                    $item['website_url'] === 'undefined' ||
                    $item['website_url'] === 'null' ||
                    $item['website_url'] === 'N/A' ||
                    trim($item['website_url']) === '') {
                    $item['website_url'] = null;
                }
                if (empty($item['website_name']) ||
                    $item['website_name'] === 'undefined' ||
                    $item['website_name'] === 'null' ||
                    $item['website_name'] === 'N/A' ||
                    trim($item['website_name']) === '') {
                    $item['website_name'] = null;
                }
            }
        }

        return $items ? $items : array();
    }

    /**
     * AJAX: Get project items
     */
    public function ajax_get_project_items() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        $project_id = isset($_POST['project_id']) ? sanitize_text_field(wp_unslash($_POST['project_id'])) : '';

        if (empty($project_id)) {
            wp_send_json_error(array(
                'message' => __('Project ID is required', 'smark')
            ));
        }

        $items = $this->get_project_items($project_id);

        wp_send_json_success(array(
            'items' => $items,
            'count' => count($items)
        ));
    }

    /**
     * Add new competitor item
     */
    private function add_item($project_name, $website_url, $website_name = null, $notes = null) {
        global $wpdb;
        $projects_table_sql = $this->escape_db_identifier($this->projects_table);
        if (empty($projects_table_sql)) {
            return false;
        }

        // Get project_id from projects table
        $project_id = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT project_id FROM {$projects_table_sql} WHERE project_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $project_name
        ));

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table_name,
            array(
                'project' => $project_name,
                'project_id' => $project_id,
                'website_url' => $website_url,
                'website_name' => $website_name,
                'notes' => $notes,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            $this->log_error('Database insert error', array('error' => $wpdb->last_error));
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * AJAX: Add new item
     */
    public function ajax_add_item() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';
        $website_url = isset($_POST['website_url']) ? esc_url_raw(wp_unslash($_POST['website_url'])) : '';
        $website_name = isset($_POST['website_name']) ? sanitize_text_field(wp_unslash($_POST['website_name'])) : null;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : null;

        if (empty($project_name)) {
            wp_send_json_error(array(
                'message' => __('Project name is required', 'smark')
            ));
        }

        if (empty($website_url)) {
            wp_send_json_error(array(
                'message' => __('Website URL is required', 'smark')
            ));
        }

        $item_id = $this->add_item($project_name, $website_url, $website_name, $notes);

        if ($item_id) {
            wp_send_json_success(array(
                'message' => $this->get_translation('item_added_successfully'),
                'item_id' => $item_id
            ));
        } else {
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
        if (empty($items_table_sql)) {
            return null;
        }

        $item = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT * FROM {$items_table_sql} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $item_id
        ), ARRAY_A);

        // Clean up any undefined/null values before returning
        if ($item) {
            if (empty($item['website_url']) ||
                $item['website_url'] === 'undefined' ||
                $item['website_url'] === 'null' ||
                $item['website_url'] === 'N/A' ||
                trim($item['website_url']) === '') {
                $item['website_url'] = null;
            }
            if (empty($item['website_name']) ||
                $item['website_name'] === 'undefined' ||
                $item['website_name'] === 'null' ||
                $item['website_name'] === 'N/A' ||
                trim($item['website_name']) === '') {
                $item['website_name'] = null;
            }
        }

        return $item;
    }

    /**
     * AJAX: Get single item
     */
    public function ajax_get_item() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

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
     * Update item
     */
    private function update_item($item_id, $website_url, $website_name = null, $notes = null) {
        global $wpdb;

        $data = array(
            'website_url' => $website_url,
            'updated_at' => current_time('mysql')
        );

        $formats = array('%s', '%s');

        if ($website_name !== null) {
            $data['website_name'] = $website_name;
            $formats[] = '%s';
        }

        if ($notes !== null) {
            $data['notes'] = $notes;
            $formats[] = '%s';
        }

        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table_name,
            $data,
            array('id' => $item_id),
            $formats,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * AJAX: Update item
     */
    public function ajax_update_item() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $website_url = isset($_POST['website_url']) ? esc_url_raw(wp_unslash($_POST['website_url'])) : '';
        $website_name = isset($_POST['website_name']) ? sanitize_text_field(wp_unslash($_POST['website_name'])) : null;
        $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : null;

        if (empty($item_id)) {
            wp_send_json_error(array(
                'message' => __('Item ID is required', 'smark')
            ));
        }

        if (empty($website_url)) {
            wp_send_json_error(array(
                'message' => __('Website URL is required', 'smark')
            ));
        }

        $success = $this->update_item($item_id, $website_url, $website_name, $notes);

        if ($success) {
            wp_send_json_success(array(
                'message' => $this->get_translation('item_updated_successfully')
            ));
        } else {
            wp_send_json_error(array(
                'message' => $this->get_translation('failed_to_update_item')
            ));
        }
    }

    /**
     * Delete item
     */
    private function delete_item($item_id) {
        global $wpdb;

        // First, delete all fetched items for this competitor
        $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->fetched_items_table,
            array('competitor_item_id' => $item_id),
            array('%d')
        );

        // Then delete the competitor item
        $result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table_name,
            array('id' => $item_id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * AJAX: Delete item
     */
    public function ajax_delete_item() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        if (empty($item_id)) {
            wp_send_json_error(array(
                'message' => __('Item ID is required', 'smark')
            ));
        }

        $success = $this->delete_item($item_id);

        if ($success) {
            wp_send_json_success(array(
                'message' => $this->get_translation('item_deleted_successfully')
            ));
        } else {
            wp_send_json_error(array(
                'message' => $this->get_translation('failed_to_delete_item')
            ));
        }
    }

    /**
     * Fetch pages/posts from competitor website
     */
    private function fetch_competitor_pages($item_id, $website_url, $time_range = 'month') {
        global $wpdb;

        // Calculate date range
        $date_limit = null;
        switch ($time_range) {
            case 'week':
                $date_limit = gmdate('Y-m-d H:i:s', strtotime('-1 week'));
                break;
            case 'month':
                $date_limit = gmdate('Y-m-d H:i:s', strtotime('-1 month'));
                break;
            case 'three_months':
                $date_limit = gmdate('Y-m-d H:i:s', strtotime('-3 months'));
                break;
            case 'all':
            default:
                $date_limit = null;
                break;
        }

        // Fetch RSS feed or sitemap
        $new_pages = $this->parse_competitor_content($website_url, $date_limit);

        // Return all fetched pages without filtering by existing records
        // This allows users to fetch pages multiple times and see all available content
        // Pages will only be saved to database when user explicitly clicks "Save Pages" button
        return $new_pages;
    }

    /**
     * Parse competitor content (RSS, Sitemap, or HTML scraping)
     */
    private function parse_competitor_content($website_url, $date_limit = null) {
        $pages = array();

        // Try RSS feed first
        $rss_urls = array(
            trailingslashit($website_url) . 'feed/',
            trailingslashit($website_url) . 'rss/',
            trailingslashit($website_url) . 'feed/rss/',
            trailingslashit($website_url) . 'feed/rss2/',
            trailingslashit($website_url) . 'rss.xml',
            trailingslashit($website_url) . 'feed.xml'
        );

        foreach ($rss_urls as $rss_url) {
            $feed_content = $this->fetch_url($rss_url);
            if ($feed_content) {
                $pages = array_merge($pages, $this->parse_rss_feed($feed_content, $date_limit));
                if (!empty($pages)) {
                    break; // Found RSS feed, no need to try others
                }
            }
        }

        // If RSS didn't work, try sitemap
        if (empty($pages)) {
            $sitemap_urls = array(
                trailingslashit($website_url) . 'sitemap.xml',
                trailingslashit($website_url) . 'sitemap_index.xml',
                trailingslashit($website_url) . 'wp-sitemap.xml'
            );

            foreach ($sitemap_urls as $sitemap_url) {
                $sitemap_content = $this->fetch_url($sitemap_url);
                if ($sitemap_content) {
                    $pages = array_merge($pages, $this->parse_sitemap($sitemap_content, $date_limit));
                    if (!empty($pages)) {
                        break;
                    }
                }
            }
        }

        return $pages;
    }

    /**
     * Fetch URL content
     */
    private function fetch_url($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (WordPress SMark Plugin)'
        ));

        if (is_wp_error($response)) {
            $this->log_error('Error fetching URL', array('url' => $url, 'error' => $response->get_error_message()));
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->log_error('HTTP error fetching URL', array('url' => $url, 'status_code' => (int) $status_code));
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Parse RSS feed
     */
    private function parse_rss_feed($xml_content, $date_limit = null) {
        $pages = array();

        // Suppress XML errors
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xml_content);
        if (!$xml) {
            $this->log_error('Failed to parse RSS feed');
            return $pages;
        }

        // Check if it's RSS or Atom
        if (isset($xml->channel->item)) {
            // RSS format
            foreach ($xml->channel->item as $item) {
                $pub_date = isset($item->pubDate) ? gmdate('Y-m-d H:i:s', strtotime((string) $item->pubDate)) : null;

                // Check date limit
                if ($date_limit && $pub_date && $pub_date < $date_limit) {
                    continue;
                }

                $pages[] = array(
                    'url' => (string)$item->link,
                    'title' => (string)$item->title,
                    'type' => 'post',
                    'published_date' => $pub_date
                );
            }
        } elseif (isset($xml->entry)) {
            // Atom format
            foreach ($xml->entry as $entry) {
                $pub_date = isset($entry->published) ? gmdate('Y-m-d H:i:s', strtotime((string) $entry->published)) : null;

                // Check date limit
                if ($date_limit && $pub_date && $pub_date < $date_limit) {
                    continue;
                }

                $link = '';
                if (isset($entry->link)) {
                    $link = (string)$entry->link['href'];
                }

                $pages[] = array(
                    'url' => $link,
                    'title' => (string)$entry->title,
                    'type' => 'post',
                    'published_date' => $pub_date
                );
            }
        }

        return $pages;
    }

    /**
     * Parse sitemap XML
     */
    private function parse_sitemap($xml_content, $date_limit = null) {
        $pages = array();

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($xml_content);
        if (!$xml) {
            $this->log_error('Failed to parse sitemap');
            return $pages;
        }

        // Register namespaces
        $namespaces = $xml->getNamespaces(true);

        if (isset($xml->sitemap)) {
            // Sitemap index - need to fetch individual sitemaps
            foreach ($xml->sitemap as $sitemap) {
                $sitemap_url = (string)$sitemap->loc;
                $sitemap_content = $this->fetch_url($sitemap_url);
                if ($sitemap_content) {
                    $pages = array_merge($pages, $this->parse_sitemap($sitemap_content, $date_limit));
                }
            }
        } elseif (isset($xml->url)) {
            // URL set
            foreach ($xml->url as $url) {
                $lastmod = isset($url->lastmod) ? gmdate('Y-m-d H:i:s', strtotime((string) $url->lastmod)) : null;

                // Check date limit
                if ($date_limit && $lastmod && $lastmod < $date_limit) {
                    continue;
                }

                $page_url = (string)$url->loc;

                // Try to determine if it's a post or page based on URL structure
                $type = 'page';
                if (preg_match('/(blog|post|article|news)/', $page_url)) {
                    $type = 'post';
                }

                $pages[] = array(
                    'url' => $page_url,
                    'title' => $this->extract_title_from_url($page_url),
                    'type' => $type,
                    'published_date' => $lastmod
                );
            }
        }

        return $pages;
    }

    /**
     * Extract title from URL
     */
    private function extract_title_from_url($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            $path = '';
        }
        $segments = explode('/', trim($path, '/'));
        $last_segment = end($segments);

        // Convert URL slug to title
        $title = str_replace(array('-', '_'), ' ', $last_segment);
        $title = ucwords($title);

        return $title;
    }

    /**
     * AJAX: Fetch pages
     */
    public function ajax_fetch_pages() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $time_range = isset($_POST['time_range']) ? sanitize_text_field(wp_unslash($_POST['time_range'])) : 'month';

        if (empty($item_id)) {
            wp_send_json_error(array(
                'message' => __('Item ID is required', 'smark')
            ));
        }

        // Get item details
        $item = $this->get_item($item_id);
        if (!$item) {
            wp_send_json_error(array(
                'message' => __('Item not found', 'smark')
            ));
        }

        // Fetch new pages
        $new_pages = $this->fetch_competitor_pages($item_id, $item['website_url'], $time_range);

        // Return different response based on whether pages were found
        if (count($new_pages) > 0) {
            wp_send_json_success(array(
                'message' => sprintf(
                    $this->get_translation('found_new_pages'),
                    count($new_pages)
                ),
                'pages' => $new_pages,
                'count' => count($new_pages),
                'has_pages' => true
            ));
        } else {
            wp_send_json_success(array(
                'message' => $this->get_translation('no_new_pages_found'),
                'pages' => $new_pages,
                'count' => 0,
                'has_pages' => false
            ));
        }
    }

    /**
     * AJAX: Save fetched pages to database
     */
    public function ajax_save_pages() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $pages = isset($_POST['pages']) ? wp_unslash($_POST['pages']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
        if (is_string($pages)) {
            $decoded = json_decode($pages, true);
            $pages = is_array($decoded) ? $decoded : array();
        }

        if (empty($item_id)) {
            wp_send_json_error(array('message' => $this->get_translation('invalid_item_id')));
        }

        if (empty($pages) || !is_array($pages)) {
            wp_send_json_error(array('message' => $this->get_translation('no_pages_to_save')));
        }

        global $wpdb;
        $fetched_table_sql = $this->escape_db_identifier($this->fetched_items_table);
        if (empty($fetched_table_sql)) {
            wp_send_json_error(array('message' => __('Database table error', 'smark')));
        }
        $saved_count = 0;

        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            $url = isset($page['url']) ? esc_url_raw((string) $page['url']) : '';
            $title = isset($page['title']) ? sanitize_text_field((string) $page['title']) : '';
            $date_raw = isset($page['date']) ? sanitize_text_field((string) $page['date']) : '';
            $type = isset($page['type']) ? sanitize_key((string) $page['type']) : 'page';
            if ($type !== 'post' && $type !== 'page') {
                $type = 'page';
            }

            if (empty($url) || empty($title)) {
                continue;
            }

            // Convert date to MySQL format if it's not empty and not already in correct format
            $date = null;
            if (!empty($date_raw) && $date_raw !== 'نامشخص' && $date_raw !== 'N/A') {
                // Try to parse the date and convert to MySQL format
                $timestamp = strtotime($date_raw);
                if ($timestamp !== false) {
                    $date = gmdate('Y-m-d H:i:s', $timestamp);
                } else {
                    $this->log_debug('Failed to parse date', array('date_raw' => $date_raw));
                }
            }

            // Check if page already exists
            $exists = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                "SELECT COUNT(*) FROM {$fetched_table_sql} WHERE competitor_item_id = %d AND page_url = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $item_id,
                $url
            ));

            if ($exists > 0) {
                continue; // Skip if already exists
            }

            // Insert new page
            $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $this->fetched_items_table,
                array(
                    'competitor_item_id' => $item_id,
                    'page_url' => $url,
                    'page_title' => $title,
                    'page_type' => $type,
                    'published_date' => $date,
                    'discovered_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );

            if ($result !== false) {
                $saved_count++;
            }
        }

        if ($saved_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(
                    $this->get_translation('pages_saved_successfully'),
                    $saved_count
                ),
                'saved_count' => $saved_count
            ));
        } else {
            wp_send_json_success(array(
                'message' => $this->get_translation('all_pages_already_saved'),
                'saved_count' => 0
            ));
        }
    }

    /**
     * AJAX: Get saved pages for a competitor (only non-reviewed pages)
     */
    public function ajax_get_saved_pages() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        if (empty($item_id)) {
            wp_send_json_error(array(
                'message' => __('Item ID is required', 'smark')
            ));
        }

        global $wpdb;
        $fetched_table_sql = $this->escape_db_identifier($this->fetched_items_table);
        if (empty($fetched_table_sql)) {
            wp_send_json_error(array('message' => __('Database table error', 'smark')));
        }

        // Get saved pages for this competitor (only non-reviewed)
        $saved_pages = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT id, page_url, page_title, page_type, published_date, discovered_at, is_reviewed, reviewed_at FROM {$fetched_table_sql} WHERE competitor_item_id = %d AND is_reviewed = 0 ORDER BY discovered_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $item_id
        ));

        wp_send_json_success(array(
            'pages' => $saved_pages,
            'count' => count($saved_pages)
        ));
    }

    /**
     * AJAX: Get archived pages for a competitor (only reviewed pages)
     */
    public function ajax_get_archived_pages() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        if (empty($item_id)) {
            wp_send_json_error(array(
                'message' => __('Item ID is required', 'smark')
            ));
        }

        global $wpdb;
        $fetched_table_sql = $this->escape_db_identifier($this->fetched_items_table);
        if (empty($fetched_table_sql)) {
            wp_send_json_error(array('message' => __('Database table error', 'smark')));
        }

        // Get archived pages for this competitor (only reviewed)
        $archived_pages = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT id, page_url, page_title, page_type, published_date, discovered_at, is_reviewed, reviewed_at FROM {$fetched_table_sql} WHERE competitor_item_id = %d AND is_reviewed = 1 ORDER BY reviewed_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $item_id
        ));

        wp_send_json_success(array(
            'pages' => $archived_pages,
            'count' => count($archived_pages)
        ));
    }

    /**
     * AJAX: Mark page as reviewed
     */
    public function ajax_mark_reviewed() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;

        if (empty($page_id)) {
            wp_send_json_error(array(
                'message' => __('Page ID is required', 'smark')
            ));
        }

        global $wpdb;

        // Update page as reviewed
        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->fetched_items_table,
            array(
                'is_reviewed' => 1,
                'reviewed_at' => current_time('mysql')
            ),
            array('id' => $page_id),
            array('%d', '%s'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => $this->get_translation('page_marked_reviewed')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to mark page as reviewed', 'smark')
            ));
        }
    }

    /**
     * AJAX: Send page to social media
     */
    public function ajax_send_to_social() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $project_name = isset($_POST['project_name']) ? sanitize_text_field(wp_unslash($_POST['project_name'])) : '';

        if (empty($page_id) || empty($project_name)) {
            wp_send_json_error(array(
                'message' => __('Page ID and Project Name are required', 'smark')
            ));
        }

        global $wpdb;
        $fetched_table_sql = $this->escape_db_identifier($this->fetched_items_table);
        if (empty($fetched_table_sql)) {
            wp_send_json_error(array('message' => __('Database table error', 'smark')));
        }

        // Get page details
        $page = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT * FROM {$fetched_table_sql} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $page_id
        ));

        if (!$page) {
            wp_send_json_error(array(
                'message' => __('Page not found', 'smark')
            ));
        }

        // Get competitor item details
        $competitor_item = $this->get_item($page->competitor_item_id);

        if (!$competitor_item) {
            wp_send_json_error(array(
                'message' => __('Competitor item not found', 'smark')
            ));
        }

        // Create suggestion data with safe fallbacks
        $suggestion_data = array(
            'project' => $project_name,
            'headline' => $page->page_title ?: 'Untitled',
            'source' => $page->page_url ?: ($competitor_item['website_name'] ?: $competitor_item['website_url'] ?: 'Unknown Source'),
            'source_url' => $page->page_url ?: '',
            'source_type' => 'competitor_analysis',
            'competitor_name' => $competitor_item['website_name'] ?: $competitor_item['website_url'] ?: 'Unknown Competitor',
            'published_date' => $page->published_date ?: null,
            'discovered_at' => $page->discovered_at ?: current_time('mysql'),
            'created_at' => current_time('mysql')
        );

        // Insert into social media suggestions table
        $social_media_table = $wpdb->prefix . 'SMARK_social_media_suggestions';

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $social_media_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!$table_exists) {
            $this->create_social_media_suggestions_table();

            // Check again after creation
            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $social_media_table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if (!$table_exists) {
                wp_send_json_error(array(
                    'message' => __('Failed to create social media suggestions table', 'smark')
                ));
            }
        } else {
            // Table exists, but make sure it has all required columns
            $this->update_social_media_suggestions_table();
        }

        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $social_media_table,
            $suggestion_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            $this->log_error('Failed to send page to social media (DB insert failed)', array('error' => $wpdb->last_error));
        }

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => $this->get_translation('page_sent_to_social')
            ));
        } else {
            $error_message = __('Failed to send page to social media', 'smark');
            if (!empty($wpdb->last_error)) {
                $error_message .= ' - Database Error: ' . $wpdb->last_error;
            }
            wp_send_json_error(array(
                'message' => $error_message
            ));
        }
    }

    /**
     * AJAX: Save language preference
     */
    public function ajax_save_language() {
        check_ajax_referer('SMARK_competitor_analysis_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')));
        }

        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '';

        if (empty($language) || !in_array($language, array('en', 'fa'))) {
            wp_send_json_error(array('message' => __('Invalid language', 'smark')));
        }

        // Save language preference
        update_option('SMARK_panel_language', $language);

        wp_send_json_success(array(
            'message' => __('Language preference saved', 'smark'),
            'language' => $language
        ));
    }

    /**
     * Add submenu page (hidden from menu)
     */
    public function add_submenu_page() {
        add_submenu_page(
            null, // Hidden from menu
            __('Competitor Analysis', 'smark'),
            __('Competitor Analysis', 'smark'),
            'smark_access',
            'smark-competitor-analysis',
            array($this, 'render_page')
        );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Check if we're on the competitor analysis page
        if ($hook !== 'admin_page_smark-competitor-analysis') {
            return;
        }

        // Add body class for competitor analysis page
        add_filter('admin_body_class', array($this, 'add_admin_body_class'));

        // Enqueue Google Font VazirMTN for Persian
        wp_enqueue_style('vazirmatn-font', 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap', array(), SMARK_VERSION);

        wp_enqueue_style('smark-competitor-analysis', SMARK_PLUGIN_URL . 'features/competitor-analysis/assets/competitor-analysis.css', array(), SMARK_VERSION);

        wp_enqueue_script('smark-competitor-analysis', SMARK_PLUGIN_URL . 'features/competitor-analysis/assets/competitor-analysis.js', array('jquery'), SMARK_VERSION, true);

        // Get current language
        $current_lang = get_option('SMARK_panel_language', 'en');

        // Localize script
        wp_localize_script('smark-competitor-analysis', 'SMarkCompetitorAnalysis', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('SMARK_competitor_analysis_nonce'),
            'currentLang' => $current_lang,
            'isEmbedded' => $this->is_embed_request(),
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
                'profile' => $this->get_translation('profile'),
                'addNewItem' => $this->get_translation('add_new_item'),
                'editItem' => $this->get_translation('edit_item'),
                'websiteUrl' => $this->get_translation('website_url'),
                'websiteName' => $this->get_translation('website_name'),
                'notes' => $this->get_translation('notes'),
                'saveItem' => $this->get_translation('save_item'),
                'updateItem' => $this->get_translation('update_item'),
                'fetching' => $this->get_translation('fetching'),
                'sending' => $this->get_translation('sending'),
                'processing' => $this->get_translation('processing'),
                'profile' => $this->get_translation('profile'),
                'timeRange' => $this->get_translation('time_range'),
                'week' => $this->get_translation('week'),
                'month' => $this->get_translation('month'),
                'threeMonths' => $this->get_translation('three_months'),
                'all' => $this->get_translation('all'),
                'fetchPages' => $this->get_translation('fetch_pages'),
                'competitorProfile' => $this->get_translation('competitor_profile'),
                'savePages' => $this->get_translation('save_pages'),
                'updatePages' => $this->get_translation('update_pages'),
                'no_items_found' => $this->get_translation('no_items_found'),
                'pagesSavedSuccessfully' => $this->get_translation('pages_saved_successfully'),
                'noNewPagesToSave' => $this->get_translation('no_new_pages_to_save'),
                'invalidItemId' => $this->get_translation('invalid_item_id'),
                'noPagesToSave' => $this->get_translation('no_pages_to_save'),
                'not_defined' => $this->get_translation('not_defined')
            )
        ));
    }

    private function is_embed_request() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag.
        return isset($_GET['smark_embed']) && sanitize_key(wp_unslash($_GET['smark_embed'])) === '1';
    }

    public function print_embed_admin_styles() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only route check.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== 'smark-competitor-analysis' || !$this->is_embed_request()) {
            return;
        }
        ?>
        <style>
            html.wp-toolbar {
                padding-top: 0 !important;
            }
            #wpadminbar,
            #adminmenumain,
            #wpfooter,
            .smark-competitor-analysis-page .smark-page-header,
            .smark-competitor-analysis-page .smark-breadcrumb,
            .smark-competitor-analysis-page .smark-version-footer {
                display: none !important;
            }
            #wpcontent,
            #wpbody-content {
                margin-left: 0 !important;
                padding-left: 0 !important;
                padding-bottom: 0 !important;
            }
            #wpbody {
                margin: 0 !important;
                background: transparent !important;
            }
            body {
                background: transparent !important;
                min-width: 0 !important;
            }
            .wrap.smark-competitor-analysis-page {
                min-height: 0 !important;
                height: auto !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .smark-competitor-analysis-page .left-column {
                display: none !important;
            }
            .smark-competitor-analysis-page .smark-competitor-analysis-content,
            .smark-competitor-analysis-page .content-grid,
            .smark-competitor-analysis-page .right-column {
                display: block !important;
                width: 100% !important;
                max-width: none !important;
                min-height: 0 !important;
                overflow: visible !important;
            }
            .smark-competitor-analysis-page #data_table_card {
                width: 100% !important;
            }
            .smark-competitor-analysis-page .card {
                box-shadow: none !important;
            }
        </style>
        <?php
    }

    /**
     * Get translation
     */
    private function get_translation($key) {
        $lang = get_option('SMARK_panel_language', 'en');

        $translations = array(
            'en' => array(
                'competitor_analysis' => 'Competitor Analysis',
                'track_competitors' => 'Track competitor websites and their new content',
                'SMARK_dashboard' => 'SMark Dashboard',
                'select_or_create_project' => 'Select or Create Project',
                'choose_existing_project' => 'Choose an existing project or create a new one to get started',
                'select_project' => 'Select Project:',
                'loading' => 'Loading...',
                'sending' => 'Sending...',
                'processing' => 'Processing...',
                'or' => 'OR',
                'create_new_project' => 'Create New Project',
                'project_name' => 'Project Name:',
                'enter_project_name' => 'Enter project name...',
                'create' => 'Create',
                'cancel' => 'Cancel',
                'project_items' => 'Competitor Websites',
                'add_new_item' => 'Add Competitor Website',
                'id' => 'ID',
                'website_name' => 'Website Name',
                'website_url' => 'Website URL',
                'created' => 'Created',
                'actions' => 'Actions',
                'no_items_found' => 'No items found',
                'select_a_project' => 'Select a Project',
                'please_select_project' => 'Please select or create a project to view its items',
                'success' => 'Success!',
                'error' => 'Error!',
                'edit' => 'Edit',
                'delete' => 'Delete',
                'profile' => 'Profile',
                'add_new_item' => 'Add New Competitor',
                'edit_item' => 'Edit Competitor',
                'website_url_label' => 'Website URL:',
                'enter_website_url' => 'Enter competitor website URL...',
                'website_name_label' => 'Website Name (Optional):',
                'enter_website_name' => 'Enter website name...',
                'notes_label' => 'Notes (Optional):',
                'enter_notes' => 'Enter notes...',
                'save_item' => 'Save Competitor',
                'saving' => 'Saving...',
                'update_item' => 'Update Competitor',
                'updating' => 'Updating...',
                'cancel_btn' => 'Cancel',
                'item_added_successfully' => 'Competitor added successfully',
                'item_updated_successfully' => 'Competitor updated successfully',
                'item_deleted_successfully' => 'Competitor deleted successfully',
                'failed_to_add_item' => 'Failed to add competitor',
                'failed_to_update_item' => 'Failed to update competitor',
                'failed_to_delete_item' => 'Failed to delete competitor',
                'fetching' => 'Fetching...',
                'fetch_pages' => 'Fetch New Pages',
                'competitor_profile' => 'Profile',
                'save_pages' => 'Save Pages',
                'update_pages' => 'Update Pages',
                'pages_saved_successfully' => '%d pages saved successfully',
                'no_new_pages_to_save' => 'No new pages to save',
                'invalid_item_id' => 'Invalid item ID',
                'no_pages_to_save' => 'No pages to save',
                'time_range' => 'Time Range:',
                'week' => 'Last Week',
                'month' => 'Last Month',
                'three_months' => 'Last 3 Months',
                'all' => 'All Time',
                'found_new_pages' => 'Found %d new pages/posts',
                'no_new_pages_found' => 'No new pages found',
                'all_pages_already_saved' => 'All pages are already saved',
                'saved_pages' => 'Saved Pages',
                'new_pages' => 'New Pages',
                'no_saved_pages' => 'No saved pages found',
                'new_pages_title' => 'New Pages & Posts',
                'page_url' => 'URL',
                'page_title' => 'Title',
                'page_type' => 'Type',
                'published_date' => 'Published',
                'discovered_at' => 'Discovered',
                'operations' => 'Operations',
                'mark_reviewed' => 'Mark as Reviewed',
                'send_to_social' => 'Send to Social',
                'page_marked_reviewed' => 'Page marked as reviewed',
                'page_sent_to_social' => 'Page sent to social media successfully',
                'notes' => 'Notes',
                'not_defined' => 'Not defined',
                'archived_pages' => 'Archived',
                'no_archived_pages' => 'No archived pages found'
            ),
            'fa' => array(
                'competitor_analysis' => 'تحلیل رقبا',
                'track_competitors' => 'وبسایت‌های رقیب و محتوای جدید آن‌ها را دنبال کنید',
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
                'project_items' => 'وبسایت‌های رقیب',
                'add_new_item' => 'افزودن وبسایت رقیب',
                'id' => 'شناسه',
                'website_name' => 'نام وبسایت',
                'website_url' => 'آدرس وبسایت',
                'created' => 'تاریخ ایجاد',
                'actions' => 'عملیات',
                'no_items_found' => 'آیتمی یافت نشد',
                'select_a_project' => 'انتخاب پروژه',
                'please_select_project' => 'لطفاً یک پروژه انتخاب یا ایجاد کنید تا آیتم‌های آن را مشاهده کنید',
                'success' => 'موفق!',
                'error' => 'خطا!',
                'edit' => 'ویرایش',
                'delete' => 'حذف',
                'profile' => 'پروفایل',
                'add_new_item' => 'افزودن رقیب جدید',
                'edit_item' => 'ویرایش رقیب',
                'website_url_label' => 'آدرس وبسایت:',
                'enter_website_url' => 'آدرس وبسایت رقیب را وارد کنید...',
                'website_name_label' => 'نام وبسایت (اختیاری):',
                'enter_website_name' => 'نام وبسایت را وارد کنید...',
                'notes_label' => 'یادداشت‌ها (اختیاری):',
                'enter_notes' => 'یادداشت‌های خود را وارد کنید...',
                'save_item' => 'ذخیره رقیب',
                'saving' => 'در حال ذخیره...',
                'update_item' => 'به‌روزرسانی رقیب',
                'updating' => 'در حال به‌روزرسانی...',
                'cancel_btn' => 'لغو',
                'item_added_successfully' => 'رقیب با موفقیت اضافه شد',
                'item_updated_successfully' => 'رقیب با موفقیت به‌روزرسانی شد',
                'item_deleted_successfully' => 'رقیب با موفقیت حذف شد',
                'failed_to_add_item' => 'خطا در اضافه کردن رقیب',
                'failed_to_update_item' => 'خطا در به‌روزرسانی رقیب',
                'failed_to_delete_item' => 'خطا در حذف رقیب',
                'fetching' => 'در حال دریافت...',
                'sending' => 'در حال ارسال...',
                'processing' => 'در حال پردازش...',
                'fetch_pages' => 'دریافت صفحات جدید',
                'competitor_profile' => 'پروفایل',
                'save_pages' => 'ذخیره صفحات',
                'update_pages' => 'بروزرسانی صفحات',
                'pages_saved_successfully' => '%d صفحه با موفقیت ذخیره شد',
                'no_new_pages_to_save' => 'صفحه جدیدی برای ذخیره وجود ندارد',
                'invalid_item_id' => 'شناسه آیتم نامعتبر است',
                'no_pages_to_save' => 'صفحه‌ای برای ذخیره وجود ندارد',
                'time_range' => 'بازه زمانی:',
                'week' => 'هفته اخیر',
                'month' => 'ماه اخیر',
                'three_months' => '۳ ماه اخیر',
                'all' => 'همه زمان‌ها',
                'found_new_pages' => '%d صفحه/پست جدید یافت شد',
                'no_new_pages_found' => 'صفحه جدیدی یافت نشد',
                'all_pages_already_saved' => 'همه صفحات قبلاً ذخیره شده‌اند',
                'saved_pages' => 'صفحات ذخیره شده',
                'new_pages' => 'صفحات جدید',
                'no_saved_pages' => 'هیچ صفحه ذخیره شده‌ای یافت نشد',
                'new_pages_title' => 'صفحات و پست‌های جدید',
                'page_url' => 'آدرس',
                'page_title' => 'عنوان',
                'page_type' => 'نوع',
                'published_date' => 'تاریخ انتشار',
                'discovered_at' => 'تاریخ کشف',
                'operations' => 'عملیات',
                'mark_reviewed' => 'بررسی شده',
                'send_to_social' => 'ارسال',
                'page_marked_reviewed' => 'صفحه به عنوان بررسی شده علامت‌گذاری شد',
                'page_sent_to_social' => 'صفحه با موفقیت به سوشال مدیا ارسال شد',
                'notes' => 'یادداشت‌ها',
                'not_defined' => 'تعریف نشده',
                'archived_pages' => 'آرشیو شده',
                'no_archived_pages' => 'هیچ صفحه آرشیو شده‌ای یافت نشد'
            )
        );

        return isset($translations[$lang][$key]) ? $translations[$lang][$key] : $key;
    }

    /**
     * Render the competitor analysis page
     */
    public function render_page() {
        $current_lang = get_option('SMARK_panel_language', 'en');
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';
        $is_rtl = ($current_lang === 'fa');
        ?>
        <div class="wrap smark-competitor-analysis-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
            <div class="smark-page-header">
                <h1><?php echo esc_html($this->get_translation('competitor_analysis')); ?></h1>
                <p class="description"><?php echo esc_html($this->get_translation('track_competitors')); ?></p>
            </div>

            <div class="smark-breadcrumb">
                <div class="breadcrumb-left">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smark-dashboard')); ?>"><?php echo esc_html($this->get_translation('SMARK_dashboard')); ?></a>
                    <span class="separator"><?php echo $is_rtl ? '‹' : '›'; ?></span>
                    <span class="current"><?php echo esc_html($this->get_translation('competitor_analysis')); ?></span>
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

            <div class="smark-competitor-analysis-content">
                <div class="content-grid">
                    <!-- Left Column: Project Selection -->
                    <div class="left-column">
                        <div class="project-selection-card">
                            <div class="card">
                                <div class="card-header">
                                    <h3><?php echo esc_html($this->get_translation('select_or_create_project')); ?></h3>
                                    <p><?php echo esc_html($this->get_translation('choose_existing_project')); ?></p>
                                </div>

                                <div class="card-body">
                                    <div class="project-selector">
                                        <div class="selector-group">
                                            <label for="project_select"><?php echo esc_html($this->get_translation('select_project')); ?></label>
                                            <select id="project_select" class="project-dropdown">
                                                <option value=""><?php echo esc_html($this->get_translation('loading')); ?></option>
                                            </select>
                                        </div>

                                        <div class="or-divider">
                                            <span><?php echo esc_html($this->get_translation('or')); ?></span>
                                        </div>

                                        <div class="new-project-group">
                                            <button type="button" id="show_new_project_form" class="btn btn-primary">
                                                <span class="dashicons dashicons-plus-alt"></span>
                                                <?php echo esc_html($this->get_translation('create_new_project')); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- New Project Form (Hidden by default) -->
                                    <div id="new_project_form" class="new-project-form" style="display: none;">
                                        <div class="form-group">
                                            <label for="new_project_name"><?php echo esc_html($this->get_translation('project_name')); ?></label>
                                            <input type="text" id="new_project_name" class="form-control" placeholder="<?php echo esc_attr($this->get_translation('enter_project_name')); ?>" maxlength="255">
                                        </div>
                                        <div class="form-actions">
                                            <button type="button" id="create_project_btn" class="btn btn-primary">
                                                <span class="dashicons dashicons-yes"></span>
                                                <?php echo esc_html($this->get_translation('create')); ?>
                                            </button>
                                            <button type="button" id="cancel_project_btn" class="btn btn-secondary">
                                                <span class="dashicons dashicons-no-alt"></span>
                                                <?php echo esc_html($this->get_translation('cancel')); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Selected Project Display -->
                                    <div id="selected_project_display" class="selected-project" style="display: none;">
                                        <div class="project-badge">
                                            <span class="dashicons dashicons-portfolio"></span>
                                            <span class="project-name"></span>
                                            <button type="button" class="change-project-btn" title="<?php echo esc_attr__('Change Project', 'smark'); ?>">
                                                <span class="dashicons dashicons-update"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Data Table -->
                    <div class="right-column">
                        <div class="data-table-card" id="data_table_card" style="display: none;">
                            <div class="card">
                                <div class="card-header-with-button">
                                    <div>
                                        <h3><?php echo esc_html($this->get_translation('project_items')); ?></h3>
                                        <p class="current-project-name"></p>
                                    </div>
                                    <button type="button" id="add_new_item_btn" class="btn btn-primary">
                                        <span class="dashicons dashicons-plus-alt"></span>
                                        <?php echo esc_html($this->get_translation('add_new_item')); ?>
                                    </button>
                                </div>

                                <div class="table-wrapper">
                                    <table class="competitors-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo esc_html($this->get_translation('id')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('website_name')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('website_url')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('created')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('actions')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="data_table_body">
                                            <tr class="no-data-row">
                                                <td colspan="5"><?php echo esc_html($this->get_translation('no_items_found')); ?></td>
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

                            <div class="form-group">
                                <label for="item_website_url"><?php echo esc_html($this->get_translation('website_url_label')); ?></label>
                                <input type="url" id="item_website_url" name="website_url" class="form-control" placeholder="<?php echo esc_attr($this->get_translation('enter_website_url')); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="item_website_name"><?php echo esc_html($this->get_translation('website_name_label')); ?></label>
                                <input type="text" id="item_website_name" name="website_name" class="form-control" placeholder="<?php echo esc_attr($this->get_translation('enter_website_name')); ?>">
                            </div>

                            <div class="form-group">
                                <label for="item_notes"><?php echo esc_html($this->get_translation('notes_label')); ?></label>
                                <textarea id="item_notes" name="notes" class="form-control" rows="4" placeholder="<?php echo esc_attr($this->get_translation('enter_notes')); ?>"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
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

            <!-- Competitor Profile Modal -->
            <div id="competitor_profile_modal" class="smark-modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-content modal-large">
                    <div class="modal-header">
                        <h3><?php echo esc_html($this->get_translation('competitor_profile')); ?></h3>
                        <button type="button" class="modal-close" id="close_profile_modal">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <!-- Tabs Navigation -->
                        <div class="profile-tabs">
                            <button type="button" class="tab-btn active" data-tab="new-pages">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php echo esc_html($this->get_translation('new_pages')); ?>
                            </button>
                            <button type="button" class="tab-btn" data-tab="saved-pages">
                                <span class="dashicons dashicons-saved"></span>
                                <?php echo esc_html($this->get_translation('saved_pages')); ?>
                            </button>
                            <button type="button" class="tab-btn" data-tab="archived-pages">
                                <span class="dashicons dashicons-archive"></span>
                                <?php echo esc_html($this->get_translation('archived_pages')); ?>
                            </button>
                        </div>

                        <!-- New Pages Tab -->
                        <div id="new-pages-tab" class="tab-content active">
                            <div class="fetch-options">
                                <div class="form-group">
                                    <label for="time_range_select"><?php echo esc_html($this->get_translation('time_range')); ?></label>
                                    <select id="time_range_select" class="form-control">
                                        <option value="week"><?php echo esc_html($this->get_translation('week')); ?></option>
                                        <option value="month" selected><?php echo esc_html($this->get_translation('month')); ?></option>
                                        <option value="three_months"><?php echo esc_html($this->get_translation('three_months')); ?></option>
                                        <option value="all"><?php echo esc_html($this->get_translation('all')); ?></option>
                                    </select>
                                </div>
                                <button type="button" id="start_fetch_btn" class="btn btn-primary">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php echo esc_html($this->get_translation('fetch_pages')); ?>
                                </button>
                            </div>

                            <div id="fetch_results" style="display: none;">
                                <h4><?php echo esc_html($this->get_translation('new_pages_title')); ?></h4>
                                <div class="table-wrapper">
                                    <table class="new-pages-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo esc_html($this->get_translation('page_title')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('page_type')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('published_date')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('page_url')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="fetch_results_body">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Saved Pages Tab -->
                        <div id="saved-pages-tab" class="tab-content">
                            <div id="saved_pages_results" style="display: none;">
                                <div class="table-wrapper">
                                    <table class="saved-pages-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo esc_html($this->get_translation('page_title')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('page_type')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('published_date')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('page_url')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('discovered_at')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('operations')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="saved_pages_body">
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div id="no_saved_pages" style="display: none;" class="empty-state">
                                <div class="empty-state-content">
                                    <span class="dashicons dashicons-saved"></span>
                                    <h3><?php echo esc_html($this->get_translation('no_saved_pages')); ?></h3>
                                    <p><?php echo esc_html($this->get_translation('no_saved_pages')); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Archived Pages Tab -->
                        <div id="archived-pages-tab" class="tab-content">
                            <div id="archived_pages_results" style="display: none;">
                                <div class="table-wrapper">
                                    <table class="archived-pages-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo esc_html($this->get_translation('page_title')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('page_type')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('published_date')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('page_url')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('discovered_at')); ?></th>
                                                <th><?php echo esc_html($this->get_translation('operations')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="archived_pages_body">
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div id="no_archived_pages" style="display: none;" class="empty-state">
                                <div class="empty-state-content">
                                    <span class="dashicons dashicons-archive"></span>
                                    <h3><?php echo esc_html($this->get_translation('no_archived_pages')); ?></h3>
                                    <p><?php echo esc_html($this->get_translation('no_archived_pages')); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="save_pages_btn" class="btn btn-primary" style="display: none;">
                            <span class="dashicons dashicons-saved"></span>
                            <?php echo esc_html($this->get_translation('save_pages')); ?>
                        </button>
                        <button type="button" id="close_profile_btn" class="btn btn-secondary">
                            <?php echo esc_html($this->get_translation('cancel_btn')); ?>
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
        <?php
    }

    /**
     * Add body class for competitor analysis page
     */
    public function add_admin_body_class($classes) {
        $classes .= ' smark-competitor-analysis-page';
        return $classes;
    }
}

// Don't initialize here - it's initialized in the main SMark plugin file.
