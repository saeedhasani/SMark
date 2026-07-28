<?php
/**
 * Telegram CRM feature for SMark.
 *
 * Telegram only allows a single consumer per bot token (webhook OR getUpdates,
 * never both). SMark must never register itself as a bot's webhook, since that
 * would break a merchant's own bot code the moment they paste a token in.
 *
 * Instead, this feature exposes a standing "ingest" URL per project. The
 * merchant's own bot (unmodified, still polling or using its own webhook)
 * forwards a copy of each incoming Telegram Update to this URL - the same
 * pattern as a Google Analytics beacon. SMark stores what it receives and
 * exposes AJAX endpoints for the dashboard's CRM > Messenger panel to list
 * chats and read conversations.
 *
 * This feature also performs a one-time repair: earlier versions called
 * setWebhook automatically, which breaks any bot relying on getUpdates. On
 * load, if a project still has a leftover webhook secret, its webhook is
 * deleted so the bot's own polling starts working again.
 */

if (!defined('WPINC')) {
    die;
}

if (class_exists('SMarkTelegramCrm', false)) {
    return;
}

class SMarkTelegramCrm {
    const TELEGRAM_API_BASE = 'https://api.telegram.org/bot';
    const AJAX_NONCE_ACTION = 'smark_telegram_dashboard_ajax';

    private $table_ensured = false;
    private $campaigns_table_ensured = false;

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_init', array($this, 'maybe_repair_legacy_webhook'));
        add_action('wp_ajax_smark_telegram_list_chats', array($this, 'ajax_list_chats'));
        add_action('wp_ajax_smark_telegram_list_messages', array($this, 'ajax_list_messages'));
        add_action('wp_ajax_smark_crm_campaign_send', array($this, 'ajax_campaign_send'));
        add_action('wp_ajax_smark_crm_campaign_list', array($this, 'ajax_campaign_list'));
        add_action('smark_crm_run_scheduled_campaign', array($this, 'run_scheduled_campaign'), 10, 1);
    }

    private static function escape_db_identifier($identifier) {
        $identifier = (string) $identifier;
        if ($identifier === '' || !preg_match('/^[A-Za-z0-9_$.]+$/', $identifier)) {
            return '';
        }
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'SMARK_telegram_messages';
    }

    private function get_campaigns_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'SMARK_crm_campaigns';
    }

    private function ensure_campaigns_table() {
        global $wpdb;
        $table = $this->get_campaigns_table_name();
        $table_sql = self::escape_db_identifier($table);
        if ($table_sql === '') {
            return '';
        }
        if ($this->campaigns_table_ensured) {
            return $table_sql;
        }

        $charset_collate = $wpdb->get_charset_collate();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS {$table_sql} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                title VARCHAR(255) NOT NULL,
                message_text LONGTEXT NOT NULL,
                audience VARCHAR(20) NOT NULL DEFAULT 'selected',
                rules_json LONGTEXT NULL,
                recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
                sent_count INT UNSIGNED NOT NULL DEFAULT 0,
                failed_count INT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                scheduled_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                sent_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY project_id (project_id),
                KEY status (status)
            ) {$charset_collate}"
        );
        $this->campaigns_table_ensured = true;
        return $table_sql;
    }

    /**
     * Creates the messages table on first use each request (guarded by CREATE TABLE IF NOT EXISTS),
     * matching the lazy-schema pattern used by the rest of the plugin.
     */
    private function ensure_table() {
        global $wpdb;
        $table = $this->get_table_name();
        $table_sql = self::escape_db_identifier($table);
        if ($table_sql === '') {
            return '';
        }

        if ($this->table_ensured) {
            return $table_sql;
        }

        $charset_collate = $wpdb->get_charset_collate();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS {$table_sql} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                chat_id BIGINT NOT NULL,
                telegram_user_id BIGINT NULL,
                username VARCHAR(191) NULL,
                first_name VARCHAR(191) NULL,
                last_name VARCHAR(191) NULL,
                direction VARCHAR(10) NOT NULL DEFAULT 'in',
                message_text LONGTEXT NULL,
                telegram_message_id BIGINT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY chat_id (chat_id),
                KEY chat_id_msg_id (chat_id, id),
                KEY project_id (project_id)
            ) {$charset_collate}"
        );

        $this->maybe_add_chat_id_index($table);

        $this->table_ensured = true;
        return $table_sql;
    }

    /**
     * CREATE TABLE IF NOT EXISTS only applies the (chat_id, id) index to brand-new
     * installs. Sites that already had this table before pagination was added need
     * it backfilled via ALTER TABLE so the "last N messages" / "load older" queries
     * (WHERE chat_id = ? ORDER BY id ...) stay index-backed instead of scanning the
     * whole chat history.
     */
    private function maybe_add_chat_id_index($table) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = 'chat_id_msg_id'",
            $table
        ));
        if ($exists) {
            return;
        }

        $table_sql = self::escape_db_identifier($table);
        if ($table_sql === '') {
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
        $wpdb->query("ALTER TABLE {$table_sql} ADD INDEX chat_id_msg_id (chat_id, id)");
    }

    private function get_project_settings_feature() {
        global $smark_project_settings;
        return ($smark_project_settings instanceof SMarkProjectSettings) ? $smark_project_settings : null;
    }

    private function get_project_by_ingest_key($key) {
        global $wpdb;
        $key = (string) $key;
        if ($key === '') {
            return null;
        }

        $feature = $this->get_project_settings_feature();
        if (!$feature) {
            return null;
        }

        $table = $feature->get_projects_table_name();
        $table_sql = self::escape_db_identifier($table);
        if ($table_sql === '') {
            return null;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_sql} WHERE telegram_ingest_key = %s LIMIT 1", $key), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function get_project_by_id($project_id) {
        global $wpdb;
        $feature = $this->get_project_settings_feature();
        $project_id = (int) $project_id;
        if (!$feature || $project_id <= 0) {
            return null;
        }
        $table_sql = self::escape_db_identifier($feature->get_projects_table_name());
        if ($table_sql === '') {
            return null;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_sql} WHERE id = %d LIMIT 1", $project_id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function call_telegram_api($token, $method, $params = array()) {
        $token = trim((string) $token);
        $method = (string) $method;
        if ($token === '' || $method === '') {
            return new WP_Error('smark_telegram_invalid_request', 'Missing Telegram token or method.');
        }

        $response = wp_remote_post(self::TELEGRAM_API_BASE . $token . '/' . $method, array(
            'timeout' => 15,
            'body' => $params,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300 || !is_array($body) || empty($body['ok'])) {
            $description = (is_array($body) && !empty($body['description'])) ? (string) $body['description'] : ('HTTP ' . $code);
            return new WP_Error('smark_telegram_api_error', $description);
        }

        return isset($body['result']) ? $body['result'] : true;
    }

    /**
     * One-time repair for sites affected by the earlier (now removed) auto-webhook
     * behavior: if a project still has a leftover telegram_webhook_secret, that means
     * SMark previously called setWebhook using telegram_bot_token, which blocks that
     * bot's own getUpdates polling. Delete the webhook so the bot's own code starts
     * receiving updates again, then clear the flag so this only runs once.
     */
    public function maybe_repair_legacy_webhook() {
        $feature = $this->get_project_settings_feature();
        if (!$feature) {
            return;
        }

        $project = $feature->get_current_project_row();
        if (!is_array($project) || empty($project['id']) || empty($project['telegram_webhook_secret'])) {
            return;
        }

        $old_token = isset($project['telegram_bot_token']) ? trim((string) $project['telegram_bot_token']) : '';
        if ($old_token !== '') {
            $result = $this->call_telegram_api($old_token, 'deleteWebhook');
            if (is_wp_error($result) && class_exists('SMarkLogger')) {
                SMarkLogger::error('Telegram legacy webhook cleanup failed', array('error' => $result->get_error_message()));
            }
        }

        $feature->save_project_columns((int) $project['id'], array('telegram_webhook_secret' => null), array('%s'));
    }

    public function register_routes() {
        register_rest_route('smark/v1', '/telegram/ingest/(?P<key>[A-Za-z0-9]+)', array(
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => array($this, 'rest_ingest'),
        ));
    }

    public function rest_ingest($request) {
        $key = (string) $request->get_param('key');
        $project = $this->get_project_by_ingest_key($key);
        if (!$project) {
            return new WP_REST_Response(array('ok' => false), 404);
        }

        $payload = $request->get_json_params();
        if (is_array($payload) && !empty($payload['message']) && is_array($payload['message'])) {
            $this->store_message($project, $payload['message']);
        }

        return new WP_REST_Response(array('ok' => true));
    }

    private function store_message($project, array $message) {
        global $wpdb;
        $table_sql = $this->ensure_table();
        if ($table_sql === '') {
            return;
        }

        $chat = (isset($message['chat']) && is_array($message['chat'])) ? $message['chat'] : array();
        $from = (isset($message['from']) && is_array($message['from'])) ? $message['from'] : array();
        $direction = !empty($from['is_bot']) ? 'out' : 'in';

        /*
         * Telegram's sendMessage response identifies the bot in `from`, while
         * the customer remains in `chat`. Keep the customer as the conversation
         * identity for outgoing messages and use `from` for incoming messages.
         */
        $contact = $direction === 'out' ? $chat : $from;

        $chat_id = isset($chat['id']) ? (int) $chat['id'] : 0;
        if ($chat_id === 0) {
            return;
        }

        $text = '';
        if (isset($message['text'])) {
            $text = (string) $message['text'];
        } elseif (isset($message['caption'])) {
            $text = (string) $message['caption'];
        } else {
            $unsupported_labels = array(
                'photo' => 'Photo',
                'sticker' => 'Sticker',
                'voice' => 'Voice message',
                'video' => 'Video',
                'video_note' => 'Video message',
                'document' => 'File',
                'audio' => 'Audio',
                'location' => 'Location',
                'contact' => 'Contact',
                'poll' => 'Poll',
            );
            foreach ($unsupported_labels as $key => $label) {
                if (isset($message[$key])) {
                    $text = '[' . $label . ']';
                    break;
                }
            }
        }

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->get_table_name(),
            array(
                'project_id' => isset($project['id']) ? (int) $project['id'] : 0,
                'chat_id' => $chat_id,
                'telegram_user_id' => isset($contact['id']) ? (int) $contact['id'] : 0,
                'username' => isset($contact['username']) ? sanitize_text_field((string) $contact['username']) : null,
                'first_name' => isset($contact['first_name']) ? sanitize_text_field((string) $contact['first_name']) : null,
                'last_name' => isset($contact['last_name']) ? sanitize_text_field((string) $contact['last_name']) : null,
                'direction' => $direction,
                'message_text' => $text !== '' ? sanitize_textarea_field($text) : null,
                'telegram_message_id' => isset($message['message_id']) ? (int) $message['message_id'] : null,
                'created_at' => isset($message['date']) ? gmdate('Y-m-d H:i:s', (int) $message['date']) : current_time('mysql', true),
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }

    private function format_chat_name($row) {
        $first = isset($row['first_name']) ? trim((string) $row['first_name']) : '';
        $last = isset($row['last_name']) ? trim((string) $row['last_name']) : '';
        $name = trim($first . ' ' . $last);
        if ($name !== '') {
            return $name;
        }

        $username = isset($row['username']) ? trim((string) $row['username']) : '';
        if ($username !== '') {
            return '@' . $username;
        }

        return isset($row['chat_id']) ? ('#' . (string) $row['chat_id']) : '';
    }

    /**
     * Chat and message lists both use a "growing window" pagination: each call
     * asks for the most recent N rows (N grows by one page every time the
     * dashboard clicks "load more"). This keeps every fetch - including the
     * periodic polling refresh - self-consistent without needing to merge or
     * dedupe pages of results on the client.
     */
    const LIST_PAGE_SIZE = 5;
    const LIST_MAX_CHATS = 200;
    const LIST_MAX_MESSAGES = 500;

    private static function normalize_limit($raw, $default, $max) {
        $limit = (int) $raw;
        if ($limit < 1) {
            $limit = $default;
        } elseif ($limit > $max) {
            $limit = $max;
        }
        return $limit;
    }

    public function ajax_list_chats() {
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $limit = self::normalize_limit(isset($_POST['limit']) ? $_POST['limit'] : null, self::LIST_PAGE_SIZE, self::LIST_MAX_CHATS);

        global $wpdb;
        $table_sql = $this->ensure_table();
        if ($table_sql === '') {
            wp_send_json_success(array('chats' => array(), 'total' => 0, 'hasMore' => false));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
        $total = (int) $wpdb->get_var("SELECT COUNT(DISTINCT chat_id) FROM {$table_sql}");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t1.chat_id,
                    COALESCE(NULLIF(contact.username, ''), t1.username) AS username,
                    COALESCE(NULLIF(contact.first_name, ''), t1.first_name) AS first_name,
                    COALESCE(NULLIF(contact.last_name, ''), t1.last_name) AS last_name,
                    t1.message_text AS last_message, t1.created_at AS last_message_at,
                    (SELECT COUNT(*) FROM {$table_sql} t3 WHERE t3.chat_id = t1.chat_id) AS message_count
             FROM {$table_sql} t1
             INNER JOIN (
                 SELECT chat_id, MAX(id) AS max_id FROM {$table_sql} GROUP BY chat_id
             ) t2 ON t1.chat_id = t2.chat_id AND t1.id = t2.max_id
             LEFT JOIN {$table_sql} contact ON contact.id = (
                 SELECT MAX(t4.id)
                 FROM {$table_sql} t4
                 WHERE t4.chat_id = t1.chat_id
                   AND t4.telegram_user_id = t4.chat_id
             )
             ORDER BY t1.created_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
        $rows = is_array($rows) ? $rows : array();

        $chats = array();
        foreach ($rows as $row) {
            $chats[] = array(
                'chatId' => (string) $row['chat_id'],
                'name' => $this->format_chat_name($row),
                'lastMessage' => isset($row['last_message']) ? (string) $row['last_message'] : '',
                'lastMessageAt' => isset($row['last_message_at']) ? (string) $row['last_message_at'] : '',
                'messageCount' => isset($row['message_count']) ? (int) $row['message_count'] : 0,
            );
        }

        wp_send_json_success(array(
            'chats' => $chats,
            'total' => $total,
            'hasMore' => $total > count($chats),
        ));
    }

    public function ajax_list_messages() {
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $chat_id = isset($_POST['chat_id']) ? (int) $_POST['chat_id'] : 0;
        if ($chat_id === 0) {
            wp_send_json_error(array('message' => 'Invalid chat.'), 400);
        }

        $limit = self::normalize_limit(isset($_POST['limit']) ? $_POST['limit'] : null, self::LIST_PAGE_SIZE, self::LIST_MAX_MESSAGES);
        $before_id = isset($_POST['before_id']) ? (int) $_POST['before_id'] : 0;
        $after_id = isset($_POST['after_id']) ? (int) $_POST['after_id'] : 0;

        global $wpdb;
        $table_sql = $this->ensure_table();
        if ($table_sql === '') {
            wp_send_json_success(array('messages' => array(), 'hasMore' => false));
        }

        /*
         * Messages are always fetched newest-first with a single indexed query and then
         * reversed for display, instead of a separate COUNT(*) + LIMIT/OFFSET pair. That
         * avoids any window where the two queries could disagree (e.g. a message landing
         * between them) and keeps "last N messages" a single round trip.
         */
        if ($after_id > 0) {
            // Polling for new messages: fetch anything newer than what's already on screen, oldest-first.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, direction, message_text, created_at FROM {$table_sql} WHERE chat_id = %d AND id > %d ORDER BY id ASC LIMIT %d",
                $chat_id,
                $after_id,
                self::LIST_MAX_MESSAGES
            ), ARRAY_A);
            $rows = is_array($rows) ? $rows : array();
            $has_more = null;
        } else {
            if ($before_id > 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, direction, message_text, created_at FROM {$table_sql} WHERE chat_id = %d AND id < %d ORDER BY id DESC LIMIT %d",
                    $chat_id,
                    $before_id,
                    $limit + 1
                ), ARRAY_A);
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier is strictly validated by escape_db_identifier().
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, direction, message_text, created_at FROM {$table_sql} WHERE chat_id = %d ORDER BY id DESC LIMIT %d",
                    $chat_id,
                    $limit + 1
                ), ARRAY_A);
            }
            $rows = is_array($rows) ? $rows : array();
            $has_more = count($rows) > $limit;
            if ($has_more) {
                $rows = array_slice($rows, 0, $limit);
            }
            $rows = array_reverse($rows);
        }

        $messages = array();
        foreach ($rows as $row) {
            $messages[] = array(
                'id' => (int) $row['id'],
                'direction' => (string) $row['direction'],
                'text' => isset($row['message_text']) ? (string) $row['message_text'] : '',
                'createdAt' => isset($row['created_at']) ? (string) $row['created_at'] : '',
            );
        }

        $response = array('messages' => $messages);
        if ($has_more !== null) {
            $response['hasMore'] = $has_more;
        }
        wp_send_json_success($response);
    }

    private function get_campaign_chat_ids($audience, array $rules, $project_id) {
        global $wpdb;
        $table_sql = $this->ensure_table();
        if ($table_sql === '') {
            return array();
        }

        if ($audience === 'all') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $ids = $wpdb->get_col(
                "SELECT DISTINCT chat_id
                 FROM {$table_sql}
                 WHERE project_id = " . (int) $project_id . "
                   AND telegram_user_id = chat_id
                 ORDER BY chat_id ASC
                 LIMIT 500"
            );
            return array_values(array_filter(array_map('intval', (array) $ids)));
        }

        $ids = array();
        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['value'])) {
                continue;
            }
            $value = sanitize_text_field((string) $rule['value']);
            if (strpos($value, 'telegram:') !== 0) {
                continue;
            }
            $chat_id = (int) substr($value, strlen('telegram:'));
            if ($chat_id !== 0) {
                $ids[] = $chat_id;
            }
        }

        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params = array_merge(array((int) $project_id), $ids);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $valid_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT chat_id
             FROM {$table_sql}
             WHERE project_id = %d
               AND telegram_user_id = chat_id
               AND chat_id IN ({$placeholders})",
            $params
        ));
        return array_values(array_filter(array_map('intval', (array) $valid_ids)));
    }

    private function deliver_campaign(array $payload) {
        $project = $this->get_project_by_id(isset($payload['project_id']) ? (int) $payload['project_id'] : 0);
        if (!is_array($project)) {
            return new WP_Error('smark_campaign_project_missing', 'Project settings were not found.');
        }

        $token = isset($project['telegram_bot_token']) ? trim((string) $project['telegram_bot_token']) : '';
        if ($token === '') {
            return new WP_Error('smark_campaign_token_missing', 'Telegram bot token is not configured for this project.');
        }

        $text = isset($payload['text']) ? trim((string) $payload['text']) : '';
        $chat_ids = isset($payload['chat_ids']) && is_array($payload['chat_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $payload['chat_ids']))))
            : array();
        if ($text === '' || empty($chat_ids)) {
            return new WP_Error('smark_campaign_invalid', 'Campaign text or recipients are missing.');
        }

        $sent = 0;
        $failed = 0;
        $errors = array();
        foreach ($chat_ids as $chat_id) {
            $result = $this->call_telegram_api($token, 'sendMessage', array(
                'chat_id' => $chat_id,
                'text' => $text,
            ));
            if (is_wp_error($result)) {
                $failed++;
                $errors[] = $result->get_error_message();
                continue;
            }

            $sent++;
            if (is_array($result)) {
                $this->store_message($project, $result);
            }
        }

        return array(
            'sent' => $sent,
            'failed' => $failed,
            'errors' => array_values(array_unique($errors)),
        );
    }

    private function create_campaign_record(array $data) {
        global $wpdb;
        if ($this->ensure_campaigns_table() === '') {
            return 0;
        }
        $inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->get_campaigns_table_name(),
            array(
                'project_id' => isset($data['project_id']) ? (int) $data['project_id'] : 0,
                'title' => isset($data['title']) ? (string) $data['title'] : '',
                'message_text' => isset($data['text']) ? (string) $data['text'] : '',
                'audience' => isset($data['audience']) ? (string) $data['audience'] : 'selected',
                'rules_json' => isset($data['rules']) ? wp_json_encode($data['rules']) : '[]',
                'recipient_count' => isset($data['recipient_count']) ? (int) $data['recipient_count'] : 0,
                'status' => isset($data['status']) ? (string) $data['status'] : 'pending',
                'scheduled_at' => !empty($data['scheduled_at']) ? (string) $data['scheduled_at'] : null,
                'created_at' => current_time('mysql', true),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    private function update_campaign_record($campaign_id, array $data) {
        global $wpdb;
        $campaign_id = (int) $campaign_id;
        if ($campaign_id <= 0 || empty($data) || $this->ensure_campaigns_table() === '') {
            return false;
        }
        $formats = array();
        foreach ($data as $value) {
            $formats[] = is_int($value) ? '%d' : '%s';
        }
        return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->get_campaigns_table_name(),
            $data,
            array('id' => $campaign_id),
            $formats,
            array('%d')
        );
    }

    public function ajax_campaign_list() {
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');
        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $feature = $this->get_project_settings_feature();
        $project = $feature ? $feature->get_current_project_row() : null;
        if (!is_array($project) || empty($project['id'])) {
            wp_send_json_success(array('campaigns' => array()));
        }

        global $wpdb;
        $table_sql = $this->ensure_campaigns_table();
        if ($table_sql === '') {
            wp_send_json_success(array('campaigns' => array()));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, message_text, recipient_count, sent_count, failed_count, status, scheduled_at, created_at, sent_at
             FROM {$table_sql}
             WHERE project_id = %d
             ORDER BY id DESC
             LIMIT 100",
            (int) $project['id']
        ), ARRAY_A);

        $campaigns = array();
        foreach ((array) $rows as $row) {
            $campaigns[] = array(
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'text' => (string) $row['message_text'],
                'recipientCount' => (int) $row['recipient_count'],
                'sentCount' => (int) $row['sent_count'],
                'failedCount' => (int) $row['failed_count'],
                'status' => (string) $row['status'],
                'scheduledAt' => isset($row['scheduled_at']) ? (string) $row['scheduled_at'] : '',
                'createdAt' => isset($row['created_at']) ? (string) $row['created_at'] : '',
                'sentAt' => isset($row['sent_at']) ? (string) $row['sent_at'] : '',
            );
        }
        wp_send_json_success(array('campaigns' => $campaigns));
    }

    public function ajax_campaign_send() {
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $text = isset($_POST['text']) ? sanitize_textarea_field(wp_unslash($_POST['text'])) : '';
        $audience = isset($_POST['audience']) ? sanitize_key(wp_unslash($_POST['audience'])) : '';
        $timing = isset($_POST['timing']) ? sanitize_key(wp_unslash($_POST['timing'])) : 'now';
        $scheduled_at = isset($_POST['scheduled_at']) ? sanitize_text_field(wp_unslash($_POST['scheduled_at'])) : '';
        $rules_json = isset($_POST['rules']) ? wp_unslash($_POST['rules']) : '[]';
        $rules = json_decode((string) $rules_json, true);
        $rules = is_array($rules) ? $rules : array();

        if ($title === '' || $text === '') {
            wp_send_json_error(array('message' => 'Campaign title and text are required.'), 400);
        }

        $feature = $this->get_project_settings_feature();
        $project = $feature ? $feature->get_current_project_row() : null;
        if (!is_array($project) || empty($project['id'])) {
            wp_send_json_error(array('message' => 'Project settings were not found.'), 400);
        }

        $chat_ids = $this->get_campaign_chat_ids($audience, $rules, (int) $project['id']);
        if (empty($chat_ids)) {
            wp_send_json_error(array('message' => 'No sendable Messenger contacts matched this campaign.'), 400);
        }

        $payload = array(
            'title' => $title,
            'text' => $text,
            'chat_ids' => $chat_ids,
            'project_id' => (int) $project['id'],
        );

        if ($timing === 'scheduled') {
            $timestamp = strtotime($scheduled_at);
            if (!$timestamp || $timestamp <= time()) {
                wp_send_json_error(array('message' => 'Choose a future date and time for this campaign.'), 400);
            }
            $campaign_id = $this->create_campaign_record(array(
                'project_id' => (int) $project['id'],
                'title' => $title,
                'text' => $text,
                'audience' => $audience,
                'rules' => $rules,
                'recipient_count' => count($chat_ids),
                'status' => 'scheduled',
                'scheduled_at' => gmdate('Y-m-d H:i:s', $timestamp),
            ));
            if ($campaign_id <= 0) {
                wp_send_json_error(array('message' => 'The campaign record could not be created.'), 500);
            }
            $payload['campaign_id'] = $campaign_id;
            $event_id = wp_generate_uuid4();
            update_option('smark_crm_scheduled_campaign_' . $event_id, $payload, false);
            $scheduled = wp_schedule_single_event($timestamp, 'smark_crm_run_scheduled_campaign', array($event_id));
            if (is_wp_error($scheduled) || $scheduled === false) {
                delete_option('smark_crm_scheduled_campaign_' . $event_id);
                $this->update_campaign_record($campaign_id, array('status' => 'failed'));
                wp_send_json_error(array('message' => 'The campaign could not be scheduled.'), 500);
            }
            wp_send_json_success(array(
                'scheduled' => true,
                'recipientCount' => count($chat_ids),
                'campaignId' => $campaign_id,
            ));
        }

        $campaign_id = $this->create_campaign_record(array(
            'project_id' => (int) $project['id'],
            'title' => $title,
            'text' => $text,
            'audience' => $audience,
            'rules' => $rules,
            'recipient_count' => count($chat_ids),
            'status' => 'sending',
        ));
        if ($campaign_id <= 0) {
            wp_send_json_error(array('message' => 'The campaign record could not be created.'), 500);
        }
        $payload['campaign_id'] = $campaign_id;
        $result = $this->deliver_campaign($payload);
        if (is_wp_error($result)) {
            $this->update_campaign_record($campaign_id, array(
                'status' => 'failed',
                'failed_count' => count($chat_ids),
            ));
            wp_send_json_error(array('message' => $result->get_error_message()), 500);
        }
        $status = !empty($result['sent']) && empty($result['failed']) ? 'sent' : (!empty($result['sent']) ? 'partial' : 'failed');
        $this->update_campaign_record($campaign_id, array(
            'status' => $status,
            'sent_count' => (int) $result['sent'],
            'failed_count' => (int) $result['failed'],
            'sent_at' => current_time('mysql', true),
        ));
        if (empty($result['sent'])) {
            $message = !empty($result['errors'][0]) ? $result['errors'][0] : 'The campaign could not be sent.';
            wp_send_json_error(array('message' => $message, 'result' => $result), 502);
        }

        wp_send_json_success(array(
            'scheduled' => false,
            'result' => $result,
            'campaignId' => $campaign_id,
        ));
    }

    public function run_scheduled_campaign($event_id) {
        $event_id = sanitize_text_field((string) $event_id);
        if ($event_id === '') {
            return;
        }
        $option_key = 'smark_crm_scheduled_campaign_' . $event_id;
        $payload = get_option($option_key, array());
        delete_option($option_key);
        if (is_array($payload) && !empty($payload)) {
            $result = $this->deliver_campaign($payload);
            $campaign_id = isset($payload['campaign_id']) ? (int) $payload['campaign_id'] : 0;
            if ($campaign_id > 0) {
                if (is_wp_error($result)) {
                    $this->update_campaign_record($campaign_id, array(
                        'status' => 'failed',
                        'failed_count' => isset($payload['chat_ids']) ? count((array) $payload['chat_ids']) : 0,
                    ));
                } else {
                    $status = !empty($result['sent']) && empty($result['failed']) ? 'sent' : (!empty($result['sent']) ? 'partial' : 'failed');
                    $this->update_campaign_record($campaign_id, array(
                        'status' => $status,
                        'sent_count' => (int) $result['sent'],
                        'failed_count' => (int) $result['failed'],
                        'sent_at' => current_time('mysql', true),
                    ));
                }
            }
        }
    }
}
