<?php
/**
 * Content Management Feature
 */

if (!defined('WPINC')) {
    die;
}

if (class_exists('SMarkContentManagement', false)) {
    return;
}

class SMarkContentManagement {
    const OPTION_SELECTED = 'smark_cm_selected_content';
    const OPTION_CREATE = 'smark_cm_create_content';
    const OPTION_MARK_CACHE = 'smark_project_mark_cache';
    const OPTION_MARK_PENDING_TOTAL = 'smark_project_mark_pending_total';
    const OPTION_CENTRAL_BASE_URL = 'smark_central_base_url';
    const DEFAULT_CENTRAL_BASE_URL = 'https://saeedhasani.com';
    const CENTRAL_SERPER_SEARCH_PATH = '/wp-json/smark-core/v1/tools/serper/search';
    const SERP_CACHE_TTL = 21600; // 6 hours
    const HEADINGS_CACHE_TTL = 21600; // 6 hours

    const FORREVIEW_META_KEY = '_smark_forreview_enabled';
    const FORREVIEW_QUERY_VAR = 'smark_forreview';
    const FORREVIEW_SLUG_VAR = 'smark_forreview_slug';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_forreview_editor_assets'));
        add_action('add_meta_boxes', array($this, 'register_forreview_metabox'));

        add_action('init', array($this, 'register_forreview_rewrite'));
        add_action('template_redirect', array($this, 'maybe_render_forreview'));
        add_filter('query_vars', array($this, 'register_forreview_query_vars'));
        add_filter('wp_robots', array($this, 'filter_forreview_wp_robots'));
        add_action('rest_api_init', array($this, 'register_forreview_rest_routes'));
        add_action('smark_register_rewrites', array($this, 'register_forreview_rewrite'));
        add_action('transition_post_status', array($this, 'maybe_cleanup_forreview_on_publish'), 10, 3);

        add_action('wp_ajax_SMARK_cm_search_content', array($this, 'ajax_search_content'));
        add_action('wp_ajax_SMARK_cm_get_selected', array($this, 'ajax_get_selected'));
        add_action('wp_ajax_SMARK_cm_add_selected', array($this, 'ajax_add_selected'));
        add_action('wp_ajax_SMARK_cm_remove_selected', array($this, 'ajax_remove_selected'));
        add_action('wp_ajax_SMARK_cm_get_create_items', array($this, 'ajax_get_create_items'));
        add_action('wp_ajax_SMARK_cm_add_create_item', array($this, 'ajax_add_create_item'));
        add_action('wp_ajax_SMARK_cm_update_create_item_type', array($this, 'ajax_update_create_item_type'));
        add_action('wp_ajax_SMARK_cm_create_draft_item', array($this, 'ajax_create_draft_item'));
        add_action('wp_ajax_SMARK_cm_save_language', array($this, 'ajax_save_language'));
        add_action('wp_ajax_SMARK_cm_serp_preview', array($this, 'ajax_serp_preview'));
        add_action('wp_ajax_SMARK_cm_fetch_headings', array($this, 'ajax_fetch_headings'));
        add_action('wp_ajax_SMARK_cm_get_post_headings', array($this, 'ajax_get_post_headings'));
        add_action('wp_ajax_SMARK_cm_ai_write_seo_content', array($this, 'ajax_ai_write_seo_content'));
        add_action('wp_ajax_SMARK_cm_ai_write_seo_intro', array($this, 'ajax_ai_write_seo_intro'));
        add_action('wp_ajax_SMARK_cm_ai_write_seo_conclusion', array($this, 'ajax_ai_write_seo_conclusion'));
        add_action('wp_ajax_SMARK_cm_ai_write_seo_title', array($this, 'ajax_ai_write_seo_title'));
        add_action('wp_ajax_SMARK_cm_insert_to_page', array($this, 'ajax_insert_to_page'));
        add_action('wp_ajax_SMARK_cm_consume_mark', array($this, 'ajax_consume_mark'));

        add_action('wp_ajax_SMARK_cm_forreview_enable', array($this, 'ajax_forreview_enable'));
        add_action('wp_ajax_SMARK_cm_forreview_disable', array($this, 'ajax_forreview_disable'));
    }

    public function register_forreview_rewrite() {
        add_rewrite_rule(
            '^forreview/([^/]+)/?$',
            'index.php?' . self::FORREVIEW_QUERY_VAR . '=1&' . self::FORREVIEW_SLUG_VAR . '=$matches[1]',
            'top'
        );
    }

    public function register_forreview_query_vars($vars) {
        $vars = is_array($vars) ? $vars : array();
        $vars[] = self::FORREVIEW_QUERY_VAR;
        $vars[] = self::FORREVIEW_SLUG_VAR;
        return $vars;
    }

    public function filter_forreview_wp_robots($robots) {
        if ((string) get_query_var(self::FORREVIEW_QUERY_VAR) !== '1') {
            return $robots;
        }

        $robots = is_array($robots) ? $robots : array();
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        $robots['noarchive'] = true;
        $robots['noimageindex'] = true;
        $robots['nosnippet'] = true;
        return $robots;
    }

    private function get_forreview_post_by_slug($slug) {
        $slug = is_string($slug) ? trim($slug) : '';
        if ($slug === '') {
            return null;
        }

        $slug = rawurldecode($slug);
        $slug = trim($slug);
        if ($slug === '' || strpos($slug, '/') !== false) {
            return null;
        }

        $types = get_post_types(array('public' => true), 'names');
        if (!is_array($types) || empty($types)) {
            $types = array('post', 'page');
        }

        $query = new WP_Query(array(
            'name' => $slug,
            'post_type' => $types,
            'post_status' => array('publish', 'private', 'future', 'draft', 'pending'),
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ));

        if ($query->have_posts()) {
            return $query->posts[0];
        }

        $sanitized = sanitize_title($slug);
        if ($sanitized !== '' && $sanitized !== $slug) {
            $query = new WP_Query(array(
                'name' => $sanitized,
                'post_type' => $types,
                'post_status' => array('publish', 'private', 'future', 'draft', 'pending'),
                'posts_per_page' => 1,
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
            ));

            if ($query->have_posts()) {
                return $query->posts[0];
            }
        }

        return null;
    }

    private function get_forreview_url($post) {
        $post_id = is_object($post) && isset($post->ID) ? (int) $post->ID : (int) $post;
        if ($post_id <= 0) {
            return '';
        }

        $slug = get_post_field('post_name', $post_id);
        $slug = is_string($slug) ? $slug : '';
        if ($slug === '') {
            return '';
        }

        return home_url('/forreview/' . $slug . '/');
    }

    private function ensure_post_has_slug($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return '';
        }

        $slug = get_post_field('post_name', $post_id);
        $slug = is_string($slug) ? $slug : '';
        if ($slug !== '') {
            return $slug;
        }

        $post = get_post($post_id);
        if (!$post || !isset($post->post_type)) {
            return '';
        }

        $title = get_the_title($post_id);
        $title = is_string($title) ? $title : '';

        $base = sanitize_title($title);
        if ($base === '') {
            $base = 'review-' . $post_id;
        }

        $unique = wp_unique_post_slug($base, $post_id, (string) $post->post_status, (string) $post->post_type, (int) $post->post_parent);
        $unique = is_string($unique) ? $unique : $base;

        $updated = wp_update_post(
            array(
                'ID' => $post_id,
                'post_name' => $unique,
            ),
            true
        );

        if (is_wp_error($updated)) {
            return '';
        }

        $slug = get_post_field('post_name', $post_id);
        return is_string($slug) ? $slug : '';
    }

    private function is_forreview_enabled($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return false;
        }

        return (bool) get_post_meta($post_id, self::FORREVIEW_META_KEY, true);
    }

    private function normalize_forreview_media($html) {
        $html = is_string($html) ? $html : '';
        if ($html === '' || stripos($html, '<img') === false) {
            return $html;
        }

        if (!class_exists('DOMDocument')) {
            return $html;
        }

        $use_errors = libxml_use_internal_errors(true);
        $doc = new DOMDocument();

        $wrapper_id = 'smark-forreview-wrap';
        $loaded = $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="' . $wrapper_id . '">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if (!$loaded) {
            libxml_use_internal_errors($use_errors);
            return $html;
        }

        $xpath = new DOMXPath($doc);

        $fix_img = static function ($img) {
            if (!($img instanceof DOMElement)) {
                return;
            }

            $src = $img->getAttribute('src');
            $data_src = '';

            foreach (array('data-lazy-src', 'data-src', 'data-original', 'data-orig-src') as $attr) {
                $val = $img->getAttribute($attr);
                if (is_string($val) && trim($val) !== '') {
                    $data_src = trim($val);
                    break;
                }
            }

            $src_trim = is_string($src) ? trim($src) : '';
            $src_empty = $src_trim === '' || stripos($src_trim, 'data:') === 0 || strtolower($src_trim) === 'about:blank';
            $src_placeholder = false;
            if ($src_trim !== '') {
                if (stripos($src_trim, 'wp-rocket') !== false && preg_match('#/(1x1|blank)\\.(gif|png)(\\?.*)?$#i', $src_trim)) {
                    $src_placeholder = true;
                } elseif (preg_match('#/(1x1|pixel|blank)\\.(gif|png)(\\?.*)?$#i', $src_trim)) {
                    $src_placeholder = true;
                }
            }

            if ($data_src !== '' && ($src_empty || $src_placeholder)) {
                $img->setAttribute('src', $data_src);
            }

            $srcset = $img->getAttribute('srcset');
            $data_srcset = '';
            foreach (array('data-lazy-srcset', 'data-srcset') as $attr) {
                $val = $img->getAttribute($attr);
                if (is_string($val) && trim($val) !== '') {
                    $data_srcset = trim($val);
                    break;
                }
            }
            if ($data_srcset !== '' && (!is_string($srcset) || trim($srcset) === '')) {
                $img->setAttribute('srcset', $data_srcset);
            }

            foreach (array('data-lazy-src', 'data-src', 'data-original', 'data-orig-src', 'data-lazy-srcset', 'data-srcset') as $attr) {
                if ($img->hasAttribute($attr)) {
                    $img->removeAttribute($attr);
                }
            }

            $img->setAttribute('loading', 'eager');
        };

        foreach ($xpath->query('//img') as $img) {
            $fix_img($img);
        }

        foreach ($xpath->query('//source') as $source) {
            if (!($source instanceof DOMElement)) {
                continue;
            }

            $srcset = $source->getAttribute('srcset');
            $data_srcset = '';
            foreach (array('data-lazy-srcset', 'data-srcset') as $attr) {
                $val = $source->getAttribute($attr);
                if (is_string($val) && trim($val) !== '') {
                    $data_srcset = trim($val);
                    break;
                }
            }

            if ($data_srcset !== '' && (!is_string($srcset) || trim($srcset) === '')) {
                $source->setAttribute('srcset', $data_srcset);
            }

            foreach (array('data-lazy-srcset', 'data-srcset') as $attr) {
                if ($source->hasAttribute($attr)) {
                    $source->removeAttribute($attr);
                }
            }
        }

        $wrapper = $doc->getElementById($wrapper_id);
        if (!$wrapper) {
            libxml_use_internal_errors($use_errors);
            return $html;
        }

        $out = '';
        foreach ($wrapper->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        libxml_use_internal_errors($use_errors);
        return is_string($out) && $out !== '' ? $out : $html;
    }

    public function maybe_render_forreview() {
        if ((string) get_query_var(self::FORREVIEW_QUERY_VAR) !== '1') {
            return;
        }

        $slug = get_query_var(self::FORREVIEW_SLUG_VAR);
        $slug = is_string($slug) ? $slug : '';
        $post = $this->get_forreview_post_by_slug($slug);
        if (!$post) {
            status_header(404);
            exit;
        }

        $post_id = (int) $post->ID;
        $status = get_post_status($post_id);
        $status = is_string($status) ? $status : '';

        if ($status === 'publish' || $status === 'private') {
            status_header(404);
            exit;
        }

        if (!$this->is_forreview_enabled($post_id)) {
            status_header(404);
            exit;
        }

        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'), true);

        $title = get_the_title($post_id);
        $title = is_string($title) ? $title : '';

        $content = isset($post->post_content) ? (string) $post->post_content : '';
        $content = apply_filters('the_content', $content); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook name.
        $content = $this->normalize_forreview_media($content);

        $lang = get_bloginfo('language');
        $lang = is_string($lang) ? $lang : 'en';
        $dir = is_rtl() ? 'rtl' : 'ltr';

        echo '<!doctype html>';
        echo '<html lang="' . esc_attr($lang) . '" dir="' . esc_attr($dir) . '">';
        echo '<head>';
        echo '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">';
        echo '<title>' . esc_html($title) . '</title>';
        echo '<style>
            :root{color-scheme:light;}
            body{margin:0;background:#fff;color:#111;font:16px/1.7 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;}
            main{max-width:820px;margin:0 auto;padding:32px 18px 56px;}
            h1{font-size:34px;line-height:1.25;margin:0 0 18px;font-weight:750;letter-spacing:-0.02em;}
            .meta{margin:0 0 18px;color:#666;font-size:13px}
            .content :where(h2,h3,h4){margin-top:28px}
            a{color:inherit}
        </style>';
        echo '</head>';
        echo '<body>';
        echo '<main>';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p class="meta">' . esc_html__('For review preview (not indexed).', 'smark') . '</p>';
        echo '<div class="content">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is filtered via the_content.
        echo '</main>';
        echo '</body></html>';
        exit;
    }

    public function maybe_cleanup_forreview_on_publish($new_status, $old_status, $post) {
        if ($new_status === $old_status) {
            return;
        }

        if ($new_status !== 'publish' && $new_status !== 'private') {
            return;
        }

        if (!is_object($post) || !isset($post->ID)) {
            return;
        }

        $post_id = (int) $post->ID;
        if ($post_id <= 0) {
            return;
        }

        delete_post_meta($post_id, self::FORREVIEW_META_KEY);
    }

    private function escape_db_identifier($identifier) {
        if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            return '';
        }

        return '`' . str_replace('`', '', esc_sql($identifier)) . '`';
    }

    private function resolve_post_id_from_url($url) {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return 0;
        }

        $post_id = url_to_postid($url);
        if ($post_id) {
            return (int) $post_id;
        }

        $parsed = wp_parse_url($url);
        if (is_array($parsed) && isset($parsed['path'])) {
            $path = (string) $parsed['path'];
            $home = wp_parse_url(home_url('/'));
            $home_host = is_array($home) && isset($home['host']) ? (string) $home['host'] : '';
            $url_host = isset($parsed['host']) ? (string) $parsed['host'] : '';

            if ($path !== '' && $home_host !== '' && $url_host !== '' && strtolower($home_host) !== strtolower($url_host)) {
                $rebuilt = home_url('/' . ltrim($path, '/'));
                $post_id = url_to_postid($rebuilt);
                if ($post_id) {
                    return (int) $post_id;
                }
            }
        }

        if (function_exists('attachment_url_to_postid')) {
            $post_id = attachment_url_to_postid($url);
            if ($post_id) {
                return (int) $post_id;
            }
        }

        $path = is_array($parsed) && isset($parsed['path']) ? (string) $parsed['path'] : '';
        $path = trim($path, '/');
        if ($path === '') {
            return 0;
        }

        $segments = explode('/', $path);
        $slug = (string) end($segments);
        if ($slug === '') {
            return 0;
        }

        $types = get_post_types(array('show_ui' => true), 'names');
        $types = is_array($types) ? $types : array('post', 'page');
        $found = get_page_by_path($slug, OBJECT, $types);
        if ($found && isset($found->ID)) {
            return (int) $found->ID;
        }

        return 0;
    }

    private function get_posts_needing_review_map($project_id, $post_ids) {
        $project_id = (int) $project_id;
        $post_ids = is_array($post_ids) ? array_values(array_unique(array_map('intval', $post_ids))) : array();
        $post_ids = array_filter($post_ids, function ($id) {
            return $id > 0;
        });

        if ($project_id <= 0 || empty($post_ids)) {
            return array();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'SMARK_keyword_research';
        $table_sql = $this->escape_db_identifier($table);
        if ($table_sql === '') {
            return array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence discovery.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return array();
        }

        $selected_set = array_fill_keys($post_ids, true);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table identifier validated via escape_db_identifier().
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT keyword, page_link_url, rank_3month_avg, rank_1month_avg FROM ' . $table_sql . " WHERE project_id = %d AND page_link_status = 'found' AND page_link_url IS NOT NULL AND page_link_url <> '' AND (rank_3month_avg IS NOT NULL OR rank_1month_avg IS NOT NULL)", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table identifier validated via escape_db_identifier(); placeholders not supported for identifiers.
                $project_id
            ),
            ARRAY_A
        );

        if (!is_array($rows) || empty($rows)) {
            return array();
        }

        $map = array(); // post_id => keywords[]
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $url = isset($row['page_link_url']) ? (string) $row['page_link_url'] : '';
            if ($url === '') {
                continue;
            }

            $rank3_raw = array_key_exists('rank_3month_avg', $row) ? $row['rank_3month_avg'] : null;
            $rank1_raw = array_key_exists('rank_1month_avg', $row) ? $row['rank_1month_avg'] : null;

            // Match Keyword Research JS logic: fetched if either field is not null (even if 0/empty string).
            $has_been_fetched = ($rank3_raw !== null) || ($rank1_raw !== null);
            if (!$has_been_fetched) {
                continue;
            }

            $rank3 = is_numeric($rank3_raw) ? (float) $rank3_raw : 0.0;
            $rank1 = is_numeric($rank1_raw) ? (float) $rank1_raw : 0.0;

            $is_red = false;
            if ($rank1 === 0.0) {
                $is_red = true;
            } elseif ($rank3 !== 0.0 && $rank1 !== 0.0 && $rank3 < $rank1) {
                $is_red = true;
            }

            if (!$is_red) {
                continue;
            }

            $post_id = $this->resolve_post_id_from_url($url);
            if ($post_id <= 0 || !isset($selected_set[$post_id])) {
                continue;
            }

            $kw = isset($row['keyword']) ? (string) $row['keyword'] : '';
            if (!isset($map[$post_id])) {
                $map[$post_id] = array();
            }
            if ($kw !== '') {
                $map[$post_id][] = $kw;
            }
        }

        foreach ($map as $pid => $keywords) {
            $map[$pid] = array_values(array_unique(array_filter(array_map('strval', (array) $keywords))));
        }

        return $map;
    }

    private function get_central_sync_token() {
        if (defined('SMARK_CENTRAL_SYNC_TOKEN') && is_string(SMARK_CENTRAL_SYNC_TOKEN) && SMARK_CENTRAL_SYNC_TOKEN !== '') {
            return (string) SMARK_CENTRAL_SYNC_TOKEN;
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

    private function normalize_heading_text($text) {
        $text = is_string($text) ? $text : '';
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\\s+/u', ' ', $text);
        $text = trim((string) $text);
        return $text;
    }

    private function extract_headings_from_html($html) {
        $html = is_string($html) ? $html : '';
        if ($html === '' || !class_exists('DOMDocument')) {
            return array();
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        if (!$loaded) {
            return array();
        }

        $out = array();
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//h1|//h2|//h3');
        if ($nodes instanceof DOMNodeList) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $tag = strtolower($node->tagName);
                if (!in_array($tag, array('h1', 'h2', 'h3'), true)) {
                    continue;
                }
                $txt = $this->normalize_heading_text($node->textContent);
                if ($txt === '') {
                    continue;
                }
                $out[] = array('level' => $tag, 'text' => $txt);
                if (count($out) >= 60) {
                    break;
                }
            }
        }

        // De-duplicate by (level + text)
        $seen = array();
        $unique = array();
        foreach ($out as $h) {
            $key = strtolower($h['level'] . '|' . $h['text']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $h;
        }

        return $unique;
    }

    private function format_sub_titles_for_prompt($html) {
        $headings = $this->extract_headings_from_html($html);
        if (!is_array($headings) || empty($headings)) {
            return '';
        }

        $lines = array();
        foreach ($headings as $h) {
            if (!is_array($h)) {
                continue;
            }

            $level = isset($h['level']) ? strtolower((string) $h['level']) : '';
            if ($level !== 'h2' && $level !== 'h3') {
                continue;
            }

            $text = isset($h['text']) ? $this->normalize_heading_text((string) $h['text']) : '';
            if ($text === '') {
                continue;
            }

            $prefix = $level === 'h2' ? '## ' : '### ';
            $lines[] = $prefix . $text;
        }

        return implode("\n", $lines);
    }

    private function sync_keyword_research_page_link_for_published_post($project_id, $keyword, $post_id) {
        $project_id = (int) $project_id;
        $post_id = (int) $post_id;
        $keyword = is_string($keyword) ? trim($keyword) : '';
        if ($project_id <= 0 || $post_id <= 0 || $keyword === '') {
            return false;
        }

        $page_url = get_permalink($post_id);
        $page_url = is_string($page_url) ? trim($page_url) : '';
        if ($page_url === '') {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'SMARK_keyword_research';
        $table_sql = $this->escape_db_identifier($table);
        if ($table_sql === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence discovery.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return false;
        }

        if (!$this->table_has_column($table, 'page_link_status') || !$this->table_has_column($table, 'page_link_url')) {
            return false;
        }

        $has_updated_at = $this->table_has_column($table, 'updated_at');
        $now = current_time('mysql');

        $data = array(
            'page_link_status' => 'found',
            'page_link_url' => $page_url,
        );
        $format = array('%s', '%s');
        if ($has_updated_at) {
            $data['updated_at'] = $now;
            $format[] = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier validated via escape_db_identifier().
        $updated = $wpdb->update(
            $table_sql,
            $data,
            array(
                'project_id' => $project_id,
                'keyword' => $keyword,
            ),
            $format,
            array('%d', '%s')
        );

        return $updated !== false;
    }

    private function fetch_page_headings($url) {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return array('ok' => false, 'headings' => array(), 'message' => 'Invalid URL');
        }

        $cache_key = 'smark_cm_headings_' . md5(strtolower($url));
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['headings']) && is_array($cached['headings'])) {
            return array('ok' => true, 'headings' => $cached['headings'], 'message' => '');
        }

        $resp = wp_remote_get($url, array(
            'timeout' => 10,
            'redirection' => 5,
            'user-agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (headings-fetch)',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ),
            'limit_response_size' => 800000,
        ));

        if (is_wp_error($resp)) {
            return array('ok' => false, 'headings' => array(), 'message' => $resp->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        if ($code < 200 || $code >= 300 || $body === '') {
            return array('ok' => false, 'headings' => array(), 'message' => 'Fetch failed');
        }

        $headings = $this->extract_headings_from_html($body);
        set_transient($cache_key, array('headings' => $headings), self::HEADINGS_CACHE_TTL);

        return array('ok' => true, 'headings' => $headings, 'message' => '');
    }

    private function get_post_headings($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return array();
        }

        $post = get_post($post_id);
        if (!$post || !isset($post->post_content)) {
            return array();
        }

        $html = (string) $post->post_content;
        if ($html === '') {
            return array();
        }

        $headings = $this->extract_headings_from_html($html);
        $texts = array();
        foreach ($headings as $h) {
            if (!is_array($h)) {
                continue;
            }
            $txt = isset($h['text']) ? $this->normalize_heading_text((string) $h['text']) : '';
            if ($txt === '') {
                continue;
            }
            $texts[] = $txt;
        }

        $texts = array_values(array_unique($texts));
        return $texts;
    }

    private function fetch_serper_serp($keyword) {
        $keyword = is_string($keyword) ? trim($keyword) : '';
        if ($keyword === '') {
            return array('ok' => false, 'items' => array(), 'message' => 'Missing keyword');
        }

        $token = $this->get_central_sync_token();

        $cache_key = 'smark_cm_serp_' . md5(strtolower($keyword));
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['items'])) {
            return array('ok' => true, 'items' => (array) $cached['items'], 'message' => '');
        }

        $endpoint = $this->get_central_endpoint(self::CENTRAL_SERPER_SEARCH_PATH);
        $payload = wp_json_encode(array(
            'keyword' => $keyword,
            'num' => 10,
            'site_url' => rtrim((string) home_url('/'), '/'),
            'source' => 'smark-plugin/content-management',
        ));

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
        );
        if ($token !== '') {
            $headers['x-smark-sync-token'] = $token;
        }

        $resp = wp_remote_post($endpoint, array(
            'timeout' => 12,
            'redirection' => 3,
            'headers' => $headers,
            'body' => $payload,
            'user-agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (serper-proxy)',
        ));

        if (is_wp_error($resp)) {
            return array('ok' => false, 'items' => array(), 'message' => $resp->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        if ($code === 401 || $code === 403) {
            return array('ok' => false, 'items' => array(), 'message' => 'Central connection is not configured');
        }
        if ($code < 200 || $code >= 300 || $body === '') {
            return array('ok' => false, 'items' => array(), 'message' => 'Search failed');
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return array('ok' => false, 'items' => array(), 'message' => 'Invalid response');
        }

        if (isset($data['success']) && $data['success'] === false) {
            $msg = isset($data['message']) ? (string) $data['message'] : 'Search failed';
            return array('ok' => false, 'items' => array(), 'message' => $msg);
        }

        $items = array();
        $rows = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $link = isset($row['link']) ? esc_url_raw((string) $row['link']) : '';
            if ($link === '') {
                continue;
            }
            $items[] = array(
                'title' => isset($row['title']) ? $this->normalize_heading_text((string) $row['title']) : '',
                'link' => $link,
            );
            if (count($items) >= 10) {
                break;
            }
        }

        set_transient($cache_key, array('items' => $items), self::SERP_CACHE_TTL);

        return array('ok' => true, 'items' => $items, 'message' => '');
    }

    private function get_panel_language() {
        $lang = get_option('smark_panel_language', '');
        if (!is_string($lang) || $lang === '') {
            $lang = get_option('SMARK_panel_language', 'en');
        }
        $lang = is_string($lang) ? strtolower(trim($lang)) : 'en';
        return $lang === 'fa' ? 'fa' : 'en';
    }

    public function add_submenu_page() {
        add_submenu_page(
            null,
            __('Content Management', 'smark'),
            __('Content Management', 'smark'),
            'smark_access',
            'smark-content-management',
            array($this, 'render_page')
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'admin_page_smark-content-management') {
            return;
        }

        $current_lang = $this->get_panel_language();
        $strings = $this->get_strings($current_lang);

        // Classic WordPress editor (TinyMCE/Quicktags) assets for SERP modal draft editor.
        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }

        wp_enqueue_style(
            'vazirmatn-font',
            'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap',
            array(),
            defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0'
        );

        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'smark-content-management',
            plugin_dir_url(__FILE__) . 'assets/content-management.css',
            array('dashicons', 'vazirmatn-font'),
            defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'smark-content-management',
            plugin_dir_url(__FILE__) . 'assets/content-management.js',
            array('jquery'),
            defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0',
            true
        );

        add_action('admin_body_class', function ($classes) {
            if (strpos((string) $classes, 'smark-plugin-page') === false) {
                $classes .= ' smark-plugin-page';
            }
            return $classes;
        });

        wp_localize_script('smark-content-management', 'SMarkContentManagement', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('SMARK_cm_nonce'),
            'lang' => $current_lang === 'fa' ? 'fa' : 'en',
            'postTypes' => $this->get_post_type_choices(),
            'strings' => $strings,
            'currentProjectId' => (int) $this->get_current_project_id(),
        ));
    }

    public function enqueue_forreview_editor_assets($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !isset($screen->post_type) || !post_type_supports((string) $screen->post_type, 'editor')) {
            return;
        }

        wp_enqueue_script(
            'smark-forreview-classic',
            plugin_dir_url(__FILE__) . 'assets/forreview-classic.js',
            array('jquery'),
            defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0',
            true
        );

        wp_localize_script('smark-forreview-classic', 'SMarkForReviewClassic', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('SMARK_cm_forreview'),
            'strings' => array(
                'creating' => 'در حال ساخت...',
                'copy' => 'کپی',
                'copied' => 'کپی شد',
                'error' => 'خطا در انجام عملیات',
            ),
        ));

        if (function_exists('wp_is_block_editor') && wp_is_block_editor()) {
            wp_enqueue_script(
                'smark-forreview-editor',
                plugin_dir_url(__FILE__) . 'assets/forreview-editor.js',
                array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n'),
                defined('SMARK_VERSION') ? SMARK_VERSION : '1.0.0',
                true
            );

            wp_localize_script('smark-forreview-editor', 'SMarkForReview', array(
                'restPath' => '/smark/v1/forreview/',
                'strings' => array(
                    'panelTitle' => 'SMark',
                    'featureTitle' => 'مدیریت محتوا',
                    'buttonCreate' => 'ساخت لینک موقت',
                    'buttonRevoke' => 'لغو لینک موقت',
                    'saveDraftFirst' => 'برای ساخت لینک موقت، ابتدا نوشته را ذخیره کنید تا اسلاگ ساخته شود.',
                    'notAvailable' => 'این قابلیت فقط برای محتوای منتشر نشده است.',
                    'linkLabel' => 'لینک موقت:',
                    'copy' => 'کپی',
                    'copied' => 'کپی شد',
                ),
            ));
        }
    }

    private function get_strings($lang = 'en') {
        $lang = $lang === 'fa' ? 'fa' : 'en';
        $strings = array(
            'en' => array(
                'page_title' => 'Content Management',
                'page_subtitle' => 'Select and track the website content you will work on.',
                'breadcrumb_dashboard' => 'Dashboard',
                'breadcrumb_seo' => 'SEO Management',
                'breadcrumb_current' => 'Content Management',
                'add_content' => 'Add Content',
                'search_placeholder' => 'Search content…',
                'search_selected_placeholder' => 'Search selected…',
                'loading' => 'Loading…',
                'empty' => 'No content selected yet.',
                'add_selected' => 'Select',
                'cancel' => 'Cancel',
                'remove' => 'Remove',
                'review_edit' => 'Review for editing',
                'find_infographic' => 'Find infographic',
                'find_infographic_coming_soon' => 'Coming soon.',
                'serp_title' => 'Top 10 Google URLs',
                'serp_keyword' => 'Keyword',
                'serp_loading' => 'Fetching results…',
                'serp_no_results' => 'No results found.',
                'serp_not_configured' => 'Google results are not configured. Set Serper API Key in SMark Core → Tools Integration.',
                'serp_open' => 'Open',
                'use' => 'Use',
                'used' => 'Used',
                'added' => 'Content added.',
                'removed' => 'Content removed.',
                'error' => 'Something went wrong.',
                'ai_manual_required' => 'Manual AI is not enabled for this project.',
                'ai_opened_chatgpt' => 'ChatGPT opened; prompt copied.',
                'ai_prompt_not_found' => 'Prompt not found in Prompt Bank.',
            ),
            'fa' => array(
                'page_title' => 'مدیریت محتوا',
                'page_subtitle' => 'محتواهای سایت را انتخاب و برای اجرا پیگیری کنید.',
                'breadcrumb_dashboard' => 'داشبورد',
                'breadcrumb_seo' => 'مدیریت سئو',
                'breadcrumb_current' => 'مدیریت محتوا',
                'add_content' => 'افزودن محتوا',
                'search_placeholder' => 'جستجوی محتوا…',
                'search_selected_placeholder' => 'جستجو در محتواهای پروژه…',
                'loading' => 'در حال بارگذاری…',
                'empty' => 'هنوز محتوایی انتخاب نشده است.',
                'add_selected' => 'انتخاب',
                'cancel' => 'انصراف',
                'remove' => 'حذف',
                'review_edit' => 'بررسی نیاز به ویرایش',
                'find_infographic' => 'پیدا کردن اینفوگرافیک',
                'find_infographic_coming_soon' => 'به‌زودی اضافه می‌شود.',
                'serp_title' => '10 آدرس اول گوگل',
                'serp_keyword' => 'کلمه کلیدی',
                'serp_loading' => 'در حال دریافت نتایج…',
                'serp_no_results' => 'نتیجه‌ای یافت نشد.',
                'serp_not_configured' => 'نمایش نتایج گوگل فعال نیست. لطفا در SMark Core → اتصال ابزارها، کلید Serper را تنظیم کنید.',
                'serp_open' => 'باز کردن',
                'use' => 'استفاده کنید',
                'used' => 'استفاده شده',
                'added' => 'محتوا اضافه شد.',
                'removed' => 'محتوا حذف شد.',
                'error' => 'خطایی رخ داد.',
                'ai_manual_required' => 'این پروژه هوش مصنوعی دستی ندارد.',
                'ai_opened_chatgpt' => 'ChatGPT باز شد؛ پرامپت کپی شد.',
                'ai_prompt_not_found' => 'پرامپت در بانک پرامپت پیدا نشد.',
            ),
        );

        return $strings[$lang];
    }

    private function get_post_type_choices() {
        $types = get_post_types(array('show_ui' => true), 'objects');
        $choices = array(
            array('name' => 'all', 'label' => __('All types', 'smark')),
        );

        foreach ($types as $type) {
            if (!isset($type->name) || $type->name === 'attachment') {
                continue;
            }
            $choices[] = array(
                'name' => $type->name,
                'label' => isset($type->labels->singular_name) ? $type->labels->singular_name : $type->name,
            );
        }

        return $choices;
    }

    private function get_current_project_id() {
        $project_id = get_option('smark_current_project_db_id', 0);
        return (int) $project_id;
    }

    private function table_has_column($table_name, $column_name) {
        global $wpdb;
        $table_name = is_string($table_name) ? $table_name : '';
        $column_name = is_string($column_name) ? $column_name : '';
        if ($table_name === '' || $column_name === '') {
            return false;
        }

        $table_sql = $this->escape_db_identifier($table_name);
        if ($table_sql === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Schema check requires direct query; identifier validated via escape_db_identifier().
        $rows = $wpdb->get_results($wpdb->prepare('SHOW COLUMNS FROM ' . $table_sql . ' LIKE %s', $column_name));
        return !empty($rows);
    }

    private function resolve_projects_table() {
        global $wpdb;
        $prefix = isset($wpdb->prefix) ? (string) $wpdb->prefix : '';
        $candidates = array($prefix . 'SMARK_projects', $prefix . 'smark_projects');

        $existing = array();
        foreach ($candidates as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence discovery.
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
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

        // Prefer the table that has the manual_ai column if possible.
        foreach ($existing as $table) {
            if ($this->table_has_column($table, 'manual_ai')) {
                return $table;
            }
        }

        return $prefix . 'SMARK_projects';
    }

    private function ensure_project_mark_column_exists($projects_table) {
        global $wpdb;
        $projects_table = is_string($projects_table) ? $projects_table : '';
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Schema update requires direct query; identifier validated via escape_db_identifier().
        $result = $wpdb->query('ALTER TABLE ' . $projects_table_sql . ' ADD COLUMN mark int(11) NOT NULL DEFAULT 0');
        return $result !== false;
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

    private function get_central_unreachable_message() {
        $lang = $this->get_panel_language();
        return $lang === 'fa'
            ? 'ارتباط با سرور مرکزی برقرار نشد. لطفاً چند لحظه بعد دوباره تلاش کنید.'
            : 'Could not connect to the central server. Please try again in a moment.';
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

    private function seed_cached_mark_from_db_if_available($projects_table, $project_db_id) {
        global $wpdb;
        $projects_table = is_string($projects_table) ? trim($projects_table) : '';
        $project_db_id = (int) $project_db_id;
        if ($projects_table === '' || $project_db_id <= 0) {
            return;
        }

        if (!$this->table_has_column($projects_table, 'mark')) {
            return;
        }

        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $mark = (int) $wpdb->get_var($wpdb->prepare("SELECT mark FROM {$projects_table_sql} WHERE id = %d", $project_db_id));
        $this->set_cached_mark_for_project($project_db_id, max(0, $mark));
    }

    private function reserve_project_mark_credit_local($projects_table, $project_db_id, $amount) {
        global $wpdb;
        $projects_table = is_string($projects_table) ? trim($projects_table) : '';
        $project_db_id = (int) $project_db_id;
        $amount = (int) $amount;

        if ($projects_table === '' || $project_db_id <= 0 || $amount <= 0 || $amount > 10) {
            return new WP_Error('smark_invalid', 'Invalid project or amount.', array('status' => 400));
        }

        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return new WP_Error('smark_table_missing', 'Projects table not found.', array('status' => 500));
        }

        // Prefer DB-based atomic reserve when the column exists; avoid schema changes at runtime.
        if (!$this->table_has_column($projects_table, 'mark')) {
            $cached = $this->get_cached_mark_for_project($project_db_id);
            $cached = $cached === null ? 0 : max(0, (int) $cached);
            if ($cached < $amount) {
                return new WP_Error('smark_local_insufficient', 'Insufficient mark credits locally.', array('status' => 402));
            }
            $remaining = max(0, $cached - $amount);
            $this->set_cached_mark_for_project($project_db_id, $remaining);
            return $remaining;
        }

        // Atomic decrement when enough credits exist.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Table identifier validated via escape_db_identifier(); placeholders not supported for identifiers.
        $updated = $wpdb->query($wpdb->prepare("UPDATE {$projects_table_sql} SET mark = GREATEST(mark - %d, 0) WHERE id = %d AND mark >= %d", $amount, $project_db_id, $amount));
        if ($updated === false) {
            $err = isset($wpdb->last_error) ? (string) $wpdb->last_error : '';
            return new WP_Error('smark_db_error', $err !== '' ? $err : 'Database error.', array('status' => 500));
        }
        if ((int) $updated !== 1) {
            return new WP_Error('smark_local_insufficient', 'Insufficient mark credits locally.', array('status' => 402));
        }

        // Remaining after reservation (best-effort).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Identifier validated via escape_db_identifier().
        $remaining = (int) $wpdb->get_var($wpdb->prepare("SELECT mark FROM {$projects_table_sql} WHERE id = %d", $project_db_id));
        $remaining = max(0, $remaining);
        $this->set_cached_mark_for_project($project_db_id, $remaining);
        return $remaining;
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

    private function consume_project_mark_credit($project_db_id, $amount) {
        $project_db_id = (int) $project_db_id;
        $amount = (int) $amount;
        if ($project_db_id <= 0 || $amount <= 0) {
            return new WP_Error('smark_invalid', 'Invalid project or amount.');
        }

        global $wpdb;

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return new WP_Error('smark_table_missing', 'Projects table not found.');
        }

        // Seed cached mark from DB when available (helps offline stability).
        $this->seed_cached_mark_from_db_if_available($projects_table, $project_db_id);

        $website = '';
        $project_id = '';
        if ($this->table_has_column($projects_table, 'website') || $this->table_has_column($projects_table, 'project_id')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $row = $wpdb->get_row($wpdb->prepare("SELECT website, project_id FROM {$projects_table_sql} WHERE id = %d", $project_db_id), ARRAY_A);
            if (is_array($row)) {
                if (isset($row['website']) && is_string($row['website'])) {
                    $website = rtrim(trim((string) $row['website']), '/');
                }
                if (isset($row['project_id']) && is_string($row['project_id'])) {
                    $project_id = trim((string) $row['project_id']);
                }
            }
        }

        if ($website === '') {
            $website = rtrim((string) home_url('/'), '/');
        }
        if ($website === '') {
            return new WP_Error('smark_invalid', 'Invalid website.');
        }

        $central_db_id = (int) get_option('smark_central_project_db_id', 0);
        $sync_token = $this->get_central_sync_token();
        $sync_token = is_string($sync_token) ? trim($sync_token) : '';

        $pending_total = $this->get_pending_total_for_project($project_db_id);
        $central_amount = $amount + max(0, (int) $pending_total);

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (mark-consume)',
        );
        if ($sync_token !== '') {
            $headers['x-smark-sync-token'] = $sync_token;
        }

        $payload = array(
            'amount' => $central_amount,
            'website' => $website,
            'project_id' => $project_id,
            'id' => $central_db_id > 0 ? $central_db_id : 0,
        );

        $last_error = null;
        $central_unreachable = false;
        $central_forbidden = false;
        $try_payloads = array($payload);
        if ($central_db_id > 0) {
            $fallback_payload = $payload;
            $fallback_payload['id'] = 0;
            $try_payloads[] = $fallback_payload;
        }

        foreach ($try_payloads as $payload_try) {
            $args = array(
                'timeout' => 12,
                'headers' => $headers,
                'body' => wp_json_encode($payload_try),
            );

            foreach ($this->get_central_mark_consume_endpoints() as $endpoint) {
                $resp = wp_remote_post($endpoint, $args);
                if (is_wp_error($resp)) {
                    $central_unreachable = true;
                    $last_error = new WP_Error('smark_central_unreachable', $this->get_central_unreachable_message(), array(
                        'status' => 503,
                        'debug' => $resp->get_error_message(),
                    ));
                    continue;
                }

                $code = (int) wp_remote_retrieve_response_code($resp);
                $body = (string) wp_remote_retrieve_body($resp);
                $body = trim($body);
                $data = $body !== '' ? json_decode($body, true) : null;

                if ($code < 200 || $code >= 300) {
                    $msg = 'Central mark consume request failed (HTTP ' . $code . ')';
                    if (is_array($data) && isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
                        $msg = (string) $data['message'];
                    } elseif (is_array($data) && isset($data['data']['message']) && is_string($data['data']['message']) && $data['data']['message'] !== '') {
                        $msg = (string) $data['data']['message'];
                    }

                    $err_code = ($code === 402) ? 'smark_insufficient' : 'smark_mark_consume_http';
                    $last_error = new WP_Error($err_code, $msg, array('status' => $code, 'body' => $body));
                    if ($code === 401 || $code === 403) {
                        $central_forbidden = true;
                    }
                    continue;
                }

                if (!is_array($data)) {
                    $last_error = new WP_Error('smark_mark_consume_invalid', 'Invalid response from central mark consume.', array('status' => 502, 'body' => $body));
                    continue;
                }

                $remaining = null;
                if (isset($data['remaining'])) {
                    $remaining = (int) $data['remaining'];
                } elseif (isset($data['data']['remaining'])) {
                    $remaining = (int) $data['data']['remaining'];
                }

                if ($remaining === null) {
                    $last_error = new WP_Error('smark_mark_consume_invalid', 'Invalid response from central mark consume.', array('status' => 502, 'body' => $body));
                    continue;
                }

                $remaining = max(0, (int) $remaining);

                $this->set_cached_mark_for_project($project_db_id, $remaining);

                // Best-effort: keep local DB mark in sync (only if column exists; no schema changes here).
                try {
                    if ($this->table_has_column($projects_table, 'mark')) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                        $wpdb->update($projects_table, array('mark' => $remaining), array('id' => $project_db_id), array('%d'), array('%d'));
                    }
                } catch (Exception $e) {}

                // Central is now authoritative again: clear any pending debt.
                $this->clear_pending_total_for_project($project_db_id);

                return $remaining;
            }

            if ($central_unreachable && is_wp_error($last_error) && $last_error->get_error_code() === 'smark_central_unreachable') {
                break;
            }

            // Only retry without id on a central "insufficient" response (common symptom of mismatched central id).
            if (!is_wp_error($last_error) || $last_error->get_error_code() !== 'smark_insufficient') {
                break;
            }
        }

        $data = ($last_error instanceof WP_Error) ? $last_error->get_error_data() : null;
        $status = (is_array($data) && isset($data['status'])) ? (int) $data['status'] : 0;

        if ($central_unreachable || $central_forbidden || $status === 401 || $status === 403) {
            $local = $this->reserve_project_mark_credit_local($projects_table, $project_db_id, $amount);
            if (is_wp_error($local)) {
                $data = $local->get_error_data();
                $status = (is_array($data) && isset($data['status'])) ? (int) $data['status'] : 0;
                if ($status === 402) {
                    return $local;
                }

                // Financial safety: when central is unreachable, require a successful local reserve.
                return new WP_Error('smark_local_insufficient', 'Insufficient mark credits locally.', array('status' => 402));
            }

            $this->add_pending_total_for_project($project_db_id, $amount);

            if (class_exists('SMarkLogger')) {
                SMarkLogger::warning('Central mark consume unreachable; reserved locally (deferred central consume)', array(
                    'project_db_id' => $project_db_id,
                    'amount' => $amount,
                    'remaining_local' => (int) $local,
                ));
            }

            return (int) $local;
        }

        return $last_error instanceof WP_Error ? $last_error : new WP_Error('smark_mark_consume_failed', 'Central mark consume request failed.', array('status' => 502));
    }

    /**
     * Strict central consume for Content Management actions.
     *
     * Requirements:
     * - Always charge the central server first (no local reserve fallback).
     * - If central confirms the decrement, proceed; otherwise return an error (402 triggers purchase flow).
     *
     * @param int $project_db_id
     * @param int $amount
     * @return int|WP_Error Remaining mark credits on success.
     */
    private function consume_project_mark_credit_strict_central($project_db_id, $amount) {
        $project_db_id = (int) $project_db_id;
        $amount = (int) $amount;
        if ($project_db_id <= 0 || $amount <= 0 || $amount > 10) {
            return new WP_Error('smark_invalid', 'Invalid project or amount.', array('status' => 400));
        }

        global $wpdb;

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            return new WP_Error('smark_table_missing', 'Projects table not found.', array('status' => 500));
        }

        $website = '';
        $project_id = '';
        if ($this->table_has_column($projects_table, 'website') || $this->table_has_column($projects_table, 'project_id')) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $row = $wpdb->get_row($wpdb->prepare("SELECT website, project_id FROM {$projects_table_sql} WHERE id = %d", $project_db_id), ARRAY_A);
            if (is_array($row)) {
                if (isset($row['website']) && is_string($row['website'])) {
                    $website = rtrim(trim((string) $row['website']), '/');
                }
                if (isset($row['project_id']) && is_string($row['project_id'])) {
                    $project_id = trim((string) $row['project_id']);
                }
            }
        }

        if ($website === '') {
            $website = rtrim((string) home_url('/'), '/');
        }
        if ($website === '') {
            return new WP_Error('smark_invalid', 'Invalid website.', array('status' => 400));
        }

        $central_db_id = (int) get_option('smark_central_project_db_id', 0);
        $sync_token = $this->get_central_sync_token();
        $sync_token = is_string($sync_token) ? trim($sync_token) : '';

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'SMark/' . (defined('SMARK_VERSION') ? (string) SMARK_VERSION : '1.0.0') . ' (mark-consume)',
        );
        if ($sync_token !== '') {
            $headers['x-smark-sync-token'] = $sync_token;
        }

        $payload = array(
            'amount' => $amount,
            'website' => $website,
            'project_id' => $project_id,
            'id' => $central_db_id > 0 ? $central_db_id : 0,
        );

        $try_payloads = array($payload);
        if ($central_db_id > 0) {
            $fallback_payload = $payload;
            $fallback_payload['id'] = 0;
            $try_payloads[] = $fallback_payload;
        }

        $last_error = null;
        foreach ($try_payloads as $payload_try) {
            $args = array(
                'timeout' => 20,
                'headers' => $headers,
                'body' => wp_json_encode($payload_try),
            );

            foreach ($this->get_central_mark_consume_endpoints() as $endpoint) {
                $resp = wp_remote_post($endpoint, $args);
                if (is_wp_error($resp)) {
                    return new WP_Error('smark_central_unreachable', $this->get_central_unreachable_message(), array(
                        'status' => 503,
                        'debug' => $resp->get_error_message(),
                    ));
                }

                $code = (int) wp_remote_retrieve_response_code($resp);
                $body = (string) wp_remote_retrieve_body($resp);
                $body = trim($body);
                $data = $body !== '' ? json_decode($body, true) : null;

                if ($code < 200 || $code >= 300) {
                    $msg = 'Central mark consume request failed (HTTP ' . $code . ')';
                    if (is_array($data) && isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
                        $msg = (string) $data['message'];
                    } elseif (is_array($data) && isset($data['data']['message']) && is_string($data['data']['message']) && $data['data']['message'] !== '') {
                        $msg = (string) $data['data']['message'];
                    }

                    if ($code === 402) {
                        return new WP_Error('smark_insufficient', 'Insufficient mark credits', array('status' => 402, 'body' => $body));
                    }

                    // Hide central token configuration errors from users; treat as an availability issue.
                    // (Central consume is expected to allow requests when token is not configured.)
                    if ($code === 401 || $code === 403) {
                        $lower = strtolower((string) $msg);
                        $missing_token = (strpos($lower, 'sync token') !== false) && (strpos($lower, 'not configured') !== false);
                        if ($missing_token) {
                            return new WP_Error('smark_central_unreachable', $this->get_central_unreachable_message(), array('status' => 503));
                        }
                    }

                    $last_error = new WP_Error('smark_mark_consume_http', $msg, array('status' => $code, 'body' => $body));
                    continue;
                }

                if (!is_array($data)) {
                    $last_error = new WP_Error('smark_mark_consume_invalid', 'Invalid response from central mark consume.', array('status' => 502, 'body' => $body));
                    continue;
                }

                $remaining = null;
                if (isset($data['remaining'])) {
                    $remaining = (int) $data['remaining'];
                } elseif (isset($data['data']['remaining'])) {
                    $remaining = (int) $data['data']['remaining'];
                }

                if ($remaining === null) {
                    $last_error = new WP_Error('smark_mark_consume_invalid', 'Invalid response from central mark consume.', array('status' => 502, 'body' => $body));
                    continue;
                }

                $remaining = max(0, (int) $remaining);
                $this->set_cached_mark_for_project($project_db_id, $remaining);

                try {
                    if ($this->table_has_column($projects_table, 'mark')) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                        $wpdb->update($projects_table, array('mark' => $remaining), array('id' => $project_db_id), array('%d'), array('%d'));
                    }
                } catch (Exception $e) {}

                // Central is authoritative on success; clear any pending debt to avoid blocking.
                $this->clear_pending_total_for_project($project_db_id);

                return $remaining;
            }

            $last_data = ($last_error instanceof WP_Error) ? $last_error->get_error_data() : null;
            $last_status = (is_array($last_data) && isset($last_data['status'])) ? (int) $last_data['status'] : 0;
            if ((int) $payload_try['id'] > 0 && in_array($last_status, array(402, 404), true)) {
                continue;
            }
            break;
        }

        return $last_error instanceof WP_Error ? $last_error : new WP_Error('smark_mark_consume_failed', 'Central mark consume request failed.', array('status' => 502));
    }

    private function get_selected_option() {
        $all = get_option(self::OPTION_SELECTED, array());
        return is_array($all) ? $all : array();
    }

    private function set_selected_option($all) {
        update_option(self::OPTION_SELECTED, $all, false);
    }

    private function get_create_option() {
        $all = get_option(self::OPTION_CREATE, array());
        return is_array($all) ? $all : array();
    }

    private function set_create_option($all) {
        update_option(self::OPTION_CREATE, $all, false);
    }

    private function normalize_keyword_key($keyword) {
        $k = sanitize_text_field((string) $keyword);
        $k = trim($k);
        $k = preg_replace('/\\s+/u', ' ', $k);
        return is_string($k) ? strtolower($k) : '';
    }

    private function get_create_items_for_project($project_id) {
        $all = $this->get_create_option();
        $key = (string) (int) $project_id;
        $items = isset($all[$key]) && is_array($all[$key]) ? $all[$key] : array();
        return array_values(array_filter($items, function ($it) {
            return is_array($it) && !empty($it['keyword']);
        }));
    }

    private function save_create_items_for_project($project_id, $items) {
        $all = $this->get_create_option();
        $key = (string) (int) $project_id;
        $all[$key] = array_values(is_array($items) ? $items : array());
        $this->set_create_option($all);
    }

    private function build_create_item($keyword, $post_type = 'post') {
        $keyword = sanitize_text_field((string) $keyword);
        $keyword = trim(preg_replace('/\\s+/u', ' ', $keyword));
        if ($keyword === '') {
            return array();
        }

        $post_types = get_post_types(array('show_ui' => true), 'names');
        $post_types = is_array($post_types) ? $post_types : array('post', 'page');
        $post_type = sanitize_key((string) $post_type);
        if (!in_array($post_type, $post_types, true)) {
            $post_type = 'post';
        }

        return array(
            'keyword' => $keyword,
            'postType' => $post_type,
            'postId' => 0,
            'status' => '',
            'updatedAt' => '',
            'editUrl' => '',
            'createdAt' => current_time('mysql'),
        );
    }

    private function get_selected_ids_for_project($project_id) {
        $all = $this->get_selected_option();
        $key = (string) (int) $project_id;
        $ids = isset($all[$key]) && is_array($all[$key]) ? $all[$key] : array();
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });
        return $ids;
    }

    private function save_selected_ids_for_project($project_id, $ids) {
        $all = $this->get_selected_option();
        $key = (string) (int) $project_id;
        $all[$key] = array_values(array_unique(array_map('intval', $ids)));
        $this->set_selected_option($all);
    }

    private function format_post_row($post) {
        $type_obj = get_post_type_object($post->post_type);
        $type_label = $type_obj && isset($type_obj->labels->singular_name) ? $type_obj->labels->singular_name : $post->post_type;
        $updated_at = isset($post->post_modified) ? (string) $post->post_modified : '';

        return array(
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'type' => $post->post_type,
            'typeLabel' => $type_label,
            'status' => $post->post_status,
            'updatedAt' => $updated_at,
            'editUrl' => get_edit_post_link($post->ID, 'raw'),
            'viewUrl' => get_permalink($post->ID),
        );
    }

    public function render_page() {
        if (!current_user_can('smark_access')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'smark'));
        }

        $current_lang = $this->get_panel_language();
        $rtl_class = ($current_lang === 'fa') ? 'rtl' : '';
        $strings = $this->get_strings($current_lang);
        ?>
        <div class="wrap smark-content-management-page <?php echo esc_attr($rtl_class); ?>" data-lang="<?php echo esc_attr($current_lang); ?>">
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

            <div class="smark-content-management-content">
                <div class="cm-card">
                    <div class="cm-card-header">
                        <h3><?php echo esc_html($strings['page_title']); ?></h3>
                        <input type="text" id="cmSelectedSearch" class="cm-selected-search" placeholder="<?php echo esc_attr($strings['search_selected_placeholder']); ?>" <?php echo $rtl_class ? 'dir="rtl"' : ''; ?>>
                        <button type="button" class="btn btn-outline cm-open-picker">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php echo esc_html($strings['add_content']); ?>
                        </button>
                    </div>

                    <div class="cm-table-wrapper">
                        <table class="data-table" id="cmSelectedTable" <?php echo $rtl_class ? 'dir="rtl"' : ''; ?>>
                            <thead>
                                <tr>
                                    <th><?php echo esc_html($rtl_class ? 'عنوان' : 'Title'); ?></th>
                                    <th><?php echo esc_html($rtl_class ? 'نوع' : 'Type'); ?></th>
                                    <th><?php echo esc_html($rtl_class ? 'وضعیت' : 'Status'); ?></th>
                                    <th><?php echo esc_html($rtl_class ? 'تاریخ بروزرسانی' : 'Last updated'); ?></th>
                                    <th class="table-actions-column"><?php echo esc_html($rtl_class ? 'عملیات' : 'Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>

                        <div class="empty-state" id="cmEmptyState">
                            <div class="empty-state-content">
                                <h4><?php echo esc_html($strings['empty']); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cm-card" id="cmCreateContentSection">
                    <div class="cm-card-header">
                        <h3><?php echo esc_html($rtl_class ? 'ایجاد محتوا' : 'Content creation'); ?></h3>
                        <input type="text" id="cmCreateSearch" class="cm-selected-search" placeholder="<?php echo esc_attr($rtl_class ? 'جستجو در ایجاد محتوا…' : 'Search content creation…'); ?>" <?php echo $rtl_class ? 'dir="rtl"' : ''; ?>>
                    </div>

                    <div class="cm-table-wrapper">
                        <table class="data-table" id="cmCreateTable" <?php echo $rtl_class ? 'dir="rtl"' : ''; ?>>
                            <thead>
                                <tr>
                                    <th><?php echo esc_html($rtl_class ? 'عنوان' : 'Title'); ?></th>
                                    <th><?php echo esc_html($rtl_class ? 'نوع' : 'Type'); ?></th>
                                    <th><?php echo esc_html($rtl_class ? 'وضعیت' : 'Status'); ?></th>
                                    <th><?php echo esc_html($rtl_class ? 'تاریخ بروزرسانی' : 'Last updated'); ?></th>
                                    <th class="table-actions-column"><?php echo esc_html($rtl_class ? 'عملیات' : 'Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>

                        <div class="empty-state" id="cmCreateEmptyState">
                            <div class="empty-state-content">
                                <h4><?php echo esc_html($rtl_class ? 'هنوز موردی برای ایجاد محتوا وجود ندارد.' : 'No items to create content for yet.'); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="smark-version-footer">
                <div class="version-info">
                    <span class="version-label"><?php echo ($current_lang === 'fa') ? 'پلاگین اسمارک' : 'SMark Plugin'; ?></span>
                    <span class="version-separator">•</span>
                    <span class="version-number">v<?php echo esc_html(defined('SMARK_VERSION') ? SMARK_VERSION : ''); ?></span>
                </div>
            </div>
        </div>

        <div class="cm-modal <?php echo esc_attr($rtl_class); ?>" id="cmPickerModal" aria-hidden="true">
            <div class="cm-modal__overlay"></div>
            <div class="cm-modal__dialog" role="dialog" aria-modal="true">
                <div class="cm-modal__header">
                    <h3><?php echo esc_html($strings['add_content']); ?></h3>
                    <button type="button" class="cm-modal__close" aria-label="<?php echo esc_attr($strings['cancel']); ?>">×</button>
                </div>
                <div class="cm-modal__body">
                    <div class="cm-picker-toolbar">
                        <input type="text" id="cmSearchInput" placeholder="<?php echo esc_attr($strings['search_placeholder']); ?>">
                        <select id="cmTypeFilter"></select>
                        <button type="button" class="btn btn-primary" id="cmAddSelected">
                            <?php echo esc_html($strings['add_selected']); ?>
                        </button>
                        <button type="button" class="btn btn-secondary" id="cmCancel">
                            <?php echo esc_html($strings['cancel']); ?>
                        </button>
                    </div>

                    <div class="cm-picker-results">
                        <div class="cm-loading" id="cmLoading"><?php echo esc_html($strings['loading']); ?></div>
                        <table class="data-table" id="cmResultsTable" <?php echo $rtl_class ? 'dir="rtl"' : ''; ?>>
                            <thead>
                                <tr>
                                    <th class="cm-col-check"></th>
                                    <th><?php echo esc_html($rtl_class ? 'عنوان' : 'Title'); ?></th>
                                    <th><?php echo esc_html($rtl_class ? 'نوع' : 'Type'); ?></th>
                                    <th><?php echo esc_html($rtl_class ? 'وضعیت' : 'Status'); ?></th>
                                    <th><?php echo esc_html($rtl_class ? 'تاریخ بروزرسانی' : 'Last updated'); ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="cm-modal <?php echo esc_attr($rtl_class); ?>" id="cmSerpModal" aria-hidden="true">
            <div class="cm-modal__overlay"></div>
            <div class="cm-modal__dialog cm-serp-dialog" role="dialog" aria-modal="true">
                <div class="cm-modal__header">
                    <h3><?php echo esc_html($strings['serp_title']); ?></h3>
                    <button type="button" class="cm-modal__close" data-cm-close="serp" aria-label="<?php echo esc_attr($strings['cancel']); ?>">×</button>
                </div>
                <div class="cm-modal__body">
                    <div class="cm-serp-layout">
                        <div class="cm-serp-left">
                            <div class="cm-serp-toolbar">
                                <span class="cm-serp-label">
                                    <span class="cm-serp-label__text"><?php echo esc_html($strings['serp_keyword']); ?></span><span class="cm-serp-label__colon">:</span>
                                </span>
                                <span id="cmSerpKeywordValue" class="cm-serp-keyword-value" dir="auto"></span>
                            </div>

                            <div class="cm-serp-loading" id="cmSerpLoading"><?php echo esc_html($strings['serp_loading']); ?></div>
                            <div class="cm-serp-error" id="cmSerpError" style="display:none;"></div>
                            <div class="cm-serp-results" id="cmSerpResults"></div>
                        </div>

                        <div class="cm-serp-right">
                            <div class="cm-serp-editor-header">
                                <span class="cm-serp-editor-title"><?php echo esc_html($rtl_class ? 'پیش‌نویس محتوا' : 'Content draft'); ?></span>
                            </div>
                            <div class="cm-serp-content-title" id="cmSerpContentTitleBlock" style="display:none;">
                                <label class="cm-serp-content-title__label" for="cmSerpContentTitleInput"><?php echo esc_html($rtl_class ? 'عنوان محتوا' : 'Content title'); ?></label>
                                <div class="cm-serp-content-title__row">
                                    <textarea id="cmSerpContentTitleInput" class="cm-serp-content-title__input" rows="2" wrap="soft" placeholder="<?php echo esc_attr($rtl_class ? 'عنوان را وارد کنید…' : 'Enter a title…'); ?>" <?php echo $rtl_class ? 'dir="rtl"' : ''; ?>></textarea>
                                    <button type="button" id="cmSerpContentTitleAiBtn" class="cm-serp-content-title__ai-btn"><?php echo esc_html($rtl_class ? 'نگارش AI' : 'AI write'); ?></button>
                                </div>
                                <div class="cm-serp-draft-actions" id="cmSerpDraftActions">
                                    <button type="button" class="cm-serp-draft-actions__btn" id="cmSerpWriteIntro"><?php echo esc_html($rtl_class ? 'نگارش مقدمه' : 'Write intro'); ?></button>
                                    <button type="button" class="cm-serp-draft-actions__btn" id="cmSerpWriteConclusion"><?php echo esc_html($rtl_class ? 'نگارش نتیجه‌گیری' : 'Write conclusion'); ?></button>
                                    <button type="button" class="cm-serp-draft-actions__btn cm-serp-draft-actions__btn--insert" id="cmSerpInsertToPage"><?php echo esc_html($rtl_class ? 'وارد کردن به صفحه' : 'Insert to page'); ?></button>
                                </div>
                            </div>
                            <div class="cm-serp-editor">
                                <?php
                                if (function_exists('wp_editor')) {
                                    wp_editor('', 'cm_serp_draft', array(
                                        'textarea_name' => 'cm_serp_draft',
                                        'media_buttons' => false,
                                        'teeny' => false,
                                        'quicktags' => true,
                                        'editor_height' => 520,
                                    ));
                                } else {
                                    ?>
                                    <textarea id="cm_serp_draft" name="cm_serp_draft" aria-label="<?php echo esc_attr($rtl_class ? 'پیش‌نویس محتوا' : 'Content draft'); ?>"></textarea>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_save_language() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '';
        if ($language === '' || !in_array($language, array('en', 'fa'), true)) {
            wp_send_json_error(array('message' => 'Invalid language'), 400);
        }

        update_option('smark_panel_language', $language);
        update_option('SMARK_panel_language', $language);

        wp_send_json_success(array('language' => $language));
    }

    public function ajax_search_content() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : 'all';

        $types = get_post_types(array('public' => true, 'show_ui' => true), 'names');
        $types = array_values(array_filter($types, function ($t) {
            return $t !== 'attachment';
        }));

        if ($post_type !== 'all') {
            if (!in_array($post_type, $types, true)) {
                wp_send_json_error(array('message' => 'Invalid post type'), 400);
            }
            $types = array($post_type);
        }

        $args = array(
            'post_type' => $types,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => 5,
            's' => $query,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $project_id = $this->get_current_project_id();
        if ($project_id > 0) {
            $selected_ids = $this->get_selected_ids_for_project($project_id);
            if (!empty($selected_ids)) {
                $args['post__not_in'] = $selected_ids; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Admin search excludes selected content.
            }
        }

        $q = new WP_Query($args);
        $items = array();
        foreach ($q->posts as $post) {
            $items[] = $this->format_post_row($post);
        }

        wp_send_json_success(array(
            'items' => $items,
        ));
    }

    public function ajax_get_selected() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $project_id = $this->get_current_project_id();
        $ids = $this->get_selected_ids_for_project($project_id);

        // If user navigated from Keyword Research, ensure the focused post is visible here (even if not selected yet).
        $focus_post_id = isset($_GET['focus_post_id']) ? (int) $_GET['focus_post_id'] : 0;
        if ($project_id > 0 && $focus_post_id > 0 && !in_array($focus_post_id, $ids, true)) {
            $focus_post = get_post($focus_post_id);
            if ($focus_post && isset($focus_post->post_type) && $focus_post->post_type !== 'attachment') {
                $ids[] = $focus_post_id;
                $this->save_selected_ids_for_project($project_id, $ids);
            }
        }

        if (empty($ids)) {
            wp_send_json_success(array('items' => array()));
        }

        $review_map = $this->get_posts_needing_review_map($project_id, $ids);

        $posts = get_posts(array(
            'post_type' => 'any',
            'post__in' => $ids,
            'posts_per_page' => count($ids),
            'orderby' => 'post__in',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
        ));

        $items = array();
        foreach ($posts as $post) {
            $row = $this->format_post_row($post);
            $pid = isset($row['id']) ? (int) $row['id'] : 0;
            $row['needsReview'] = ($pid > 0 && isset($review_map[$pid]));
            $row['reviewKeywords'] = ($pid > 0 && isset($review_map[$pid])) ? $review_map[$pid] : array();
            $items[] = $row;
        }

        wp_send_json_success(array('items' => $items));
    }

    public function ajax_consume_mark() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 1;
        if ($amount <= 0 || $amount > 10) {
            wp_send_json_error(array('message' => 'Invalid amount'), 400);
        }

        $project_db_id = (int) $this->get_current_project_id();
        if ($project_db_id <= 0) {
            wp_send_json_error(array('message' => 'Project not selected'), 400);
        }

        $result = $this->consume_project_mark_credit_strict_central($project_db_id, $amount);
        if (is_wp_error($result)) {
            $status = 500;
            $data = $result->get_error_data();
            if (is_array($data) && isset($data['status'])) {
                $status = (int) $data['status'];
            }

            $code = $result->get_error_code();
            if ($code === 'smark_insufficient' || $status === 402) {
                wp_send_json_error(array('message' => 'Insufficient mark credits'), 402);
            }

            $msg = $result->get_error_message();
            // Never surface central sync token misconfiguration to end users; treat as a central availability issue.
            if ($status === 401 || $status === 403) {
                $lower = strtolower((string) $msg);
                if (strpos($lower, 'sync token') !== false) {
                    wp_send_json_error(array('message' => $this->get_central_unreachable_message()), 503);
                }
            }
            wp_send_json_error(array('message' => $msg !== '' ? $msg : 'Error'), $status > 0 ? $status : 500);
        }

        wp_send_json_success(array(
            'remaining' => (int) $result,
            'amount' => $amount,
        ));
    }

    public function ajax_add_selected() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        if (isset($_POST['ids']) && is_string($_POST['ids'])) {
            $ids = sanitize_text_field(wp_unslash($_POST['ids']));
            $ids = preg_split('/[\\s,]+/', $ids, -1, PREG_SPLIT_NO_EMPTY);
            $ids = array_map('absint', $ids);
        } else {
            $ids = isset($_POST['ids']) ? array_map('absint', (array) wp_unslash($_POST['ids'])) : array();
        }
        $ids = array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));

        $project_id = $this->get_current_project_id();
        $existing = $this->get_selected_ids_for_project($project_id);
        $merged = array_values(array_unique(array_merge($existing, $ids)));
        $this->save_selected_ids_for_project($project_id, $merged);

        wp_send_json_success(array('count' => count($merged)));
    }

    public function ajax_remove_selected() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            wp_send_json_error(array('message' => 'Invalid ID'), 400);
        }

        $project_id = $this->get_current_project_id();
        $existing = $this->get_selected_ids_for_project($project_id);
        $existing = array_values(array_filter($existing, function ($v) use ($id) {
            return (int) $v !== $id;
        }));
        $this->save_selected_ids_for_project($project_id, $existing);

        wp_send_json_success(array('count' => count($existing)));
    }

    public function ajax_get_create_items() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $project_id = (int) $this->get_current_project_id();
        $items = $this->get_create_items_for_project($project_id);

        $dirty = false;
        $selected_dirty = false;
        $moved_post_ids = array();
        $selected_ids = $this->get_selected_ids_for_project($project_id);
        $next_items = array();

        foreach ($items as $idx => $it) {
            if (!is_array($it)) {
                continue;
            }

            $post_id = isset($it['postId']) ? (int) $it['postId'] : 0;
            if ($post_id <= 0) {
                $next_items[] = $it;
                continue;
            }

            $post = get_post($post_id);
            if (!$post || !isset($post->ID)) {
                $it['postId'] = 0;
                $it['status'] = '';
                $it['updatedAt'] = '';
                $it['editUrl'] = '';
                $dirty = true;
                $next_items[] = $it;
                continue;
            }

            $status = isset($post->post_status) ? (string) $post->post_status : '';
            $updated_at = isset($post->post_modified) ? (string) $post->post_modified : '';
            $post_type = isset($post->post_type) ? (string) $post->post_type : '';
            $edit_url = get_edit_post_link((int) $post_id, 'raw');

            if (!isset($it['status']) || (string) $it['status'] !== $status) {
                $it['status'] = $status;
                $dirty = true;
            }
            if (!isset($it['updatedAt']) || (string) $it['updatedAt'] !== $updated_at) {
                $it['updatedAt'] = $updated_at;
                $dirty = true;
            }
            if ($post_type !== '' && (!isset($it['postType']) || (string) $it['postType'] !== $post_type)) {
                $it['postType'] = $post_type;
                $dirty = true;
            }
            if (!isset($it['editUrl']) || (string) $it['editUrl'] !== (string) $edit_url) {
                $it['editUrl'] = (string) $edit_url;
                $dirty = true;
            }

            if (strtolower($status) === 'publish') {
                $kw = isset($it['keyword']) ? (string) $it['keyword'] : '';
                $this->sync_keyword_research_page_link_for_published_post($project_id, $kw, $post_id);

                if (!in_array((int) $post_id, $selected_ids, true)) {
                    $selected_ids[] = (int) $post_id;
                    $selected_dirty = true;
                }
                $moved_post_ids[] = (int) $post_id;
                $dirty = true; // Remove from create list
                continue;
            }

            $next_items[] = $it;
        }

        if ($dirty) {
            $this->save_create_items_for_project($project_id, $next_items);
        }

        if ($selected_dirty) {
            $this->save_selected_ids_for_project($project_id, $selected_ids);
        }

        wp_send_json_success(array(
            'items' => $next_items,
            'moved_post_ids' => array_values(array_unique(array_map('intval', $moved_post_ids))),
        ));
    }

    public function ajax_add_create_item() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $project_id = (int) $this->get_current_project_id();
        if ($project_id <= 0) {
            wp_send_json_error(array('message' => 'Project not selected'), 400);
        }

        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $keyword = trim(preg_replace('/\\s+/u', ' ', $keyword));
        if ($keyword === '') {
            wp_send_json_error(array('message' => 'Keyword required'), 400);
        }

        $items = $this->get_create_items_for_project($project_id);
        $needle = $this->normalize_keyword_key($keyword);
        foreach ($items as $it) {
            $k = isset($it['keyword']) ? $this->normalize_keyword_key($it['keyword']) : '';
            if ($k !== '' && $k === $needle) {
                wp_send_json_error(array('message' => 'Already exists'), 409);
            }
        }

        $new = $this->build_create_item($keyword, 'post');
        if (empty($new)) {
            wp_send_json_error(array('message' => 'Keyword required'), 400);
        }

        $items[] = $new;
        $this->save_create_items_for_project($project_id, $items);

        wp_send_json_success(array('item' => $new));
    }

    public function ajax_update_create_item_type() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $project_id = (int) $this->get_current_project_id();
        if ($project_id <= 0) {
            wp_send_json_error(array('message' => 'Project not selected'), 400);
        }

        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';
        $keyword = trim(preg_replace('/\\s+/u', ' ', $keyword));
        if ($keyword === '') {
            wp_send_json_error(array('message' => 'Keyword required'), 400);
        }

        $post_types = get_post_types(array('show_ui' => true), 'names');
        $post_types = is_array($post_types) ? $post_types : array('post', 'page');
        if (!in_array($post_type, $post_types, true)) {
            wp_send_json_error(array('message' => 'Invalid post type'), 400);
        }

        $items = $this->get_create_items_for_project($project_id);
        $needle = $this->normalize_keyword_key($keyword);
        $found = false;
        foreach ($items as &$it) {
            $k = isset($it['keyword']) ? $this->normalize_keyword_key($it['keyword']) : '';
            if ($k !== '' && $k === $needle) {
                $it['postType'] = $post_type;
                $found = true;
                break;
            }
        }
        unset($it);

        if (!$found) {
            wp_send_json_error(array('message' => 'Not found'), 404);
        }

        $this->save_create_items_for_project($project_id, $items);
        wp_send_json_success();
    }

    public function ajax_create_draft_item() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $project_id = (int) $this->get_current_project_id();
        if ($project_id <= 0) {
            wp_send_json_error(array('message' => 'Project not selected'), 400);
        }

        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'post';
        $keyword = trim(preg_replace('/\\s+/u', ' ', $keyword));
        if ($keyword === '') {
            wp_send_json_error(array('message' => 'Keyword required'), 400);
        }

        $post_types = get_post_types(array('show_ui' => true), 'names');
        $post_types = is_array($post_types) ? $post_types : array('post', 'page');
        if (!in_array($post_type, $post_types, true)) {
            wp_send_json_error(array('message' => 'Invalid post type'), 400);
        }

        $items = $this->get_create_items_for_project($project_id);
        $needle = $this->normalize_keyword_key($keyword);
        $found_idx = -1;
        foreach ($items as $idx => $it) {
            $k = isset($it['keyword']) ? $this->normalize_keyword_key($it['keyword']) : '';
            if ($k !== '' && $k === $needle) {
                $found_idx = (int) $idx;
                break;
            }
        }
        if ($found_idx < 0) {
            wp_send_json_error(array('message' => 'Not found'), 404);
        }

        $existing_post_id = isset($items[$found_idx]['postId']) ? (int) $items[$found_idx]['postId'] : 0;
        if ($existing_post_id > 0) {
            wp_send_json_error(array('message' => 'Already created'), 409);
        }

        $post_id = wp_insert_post(array(
            'post_title' => $keyword,
            'post_type' => $post_type,
            'post_status' => 'draft',
            'post_content' => '',
        ), true);

        if (is_wp_error($post_id) || !$post_id) {
            wp_send_json_error(array('message' => 'Create failed'), 500);
        }

        $post = get_post((int) $post_id);
        $updated_at = $post && isset($post->post_modified) ? (string) $post->post_modified : current_time('mysql');
        $items[$found_idx]['postId'] = (int) $post_id;
        $items[$found_idx]['postType'] = $post_type;
        $items[$found_idx]['status'] = $post && isset($post->post_status) ? (string) $post->post_status : 'draft';
        $items[$found_idx]['updatedAt'] = $updated_at;
        $items[$found_idx]['editUrl'] = get_edit_post_link((int) $post_id, 'raw');
        $this->save_create_items_for_project($project_id, $items);

        wp_send_json_success(array('item' => $items[$found_idx]));
    }

    public function ajax_serp_preview() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $keyword = isset($_GET['keyword']) ? sanitize_text_field(wp_unslash($_GET['keyword'])) : '';
        $keyword = trim($keyword);
        if ($keyword === '') {
            wp_send_json_error(array('message' => 'Missing keyword'), 400);
        }

        $res = $this->fetch_serper_serp($keyword);
        if (!$res['ok']) {
            wp_send_json_error(array('message' => $res['message']), 400);
        }

        wp_send_json_success(array(
            'keyword' => $keyword,
            'items' => $res['items'],
        ));
    }

    public function ajax_fetch_headings() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $url = isset($_GET['url']) ? esc_url_raw(wp_unslash($_GET['url'])) : '';
        $url = trim($url);
        if ($url === '') {
            wp_send_json_error(array('message' => 'Missing URL'), 400);
        }

        if (!class_exists('DOMDocument')) {
            wp_send_json_error(array('message' => 'DOM extension is not available'), 500);
        }

        $res = $this->fetch_page_headings($url);
        if (!$res['ok']) {
            wp_send_json_error(array('message' => $res['message']), 400);
        }

        wp_send_json_success(array(
            'url' => $url,
            'headings' => $res['headings'],
        ));
    }

    public function ajax_get_post_headings() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        if ($post_id <= 0) {
            wp_send_json_error(array('message' => 'Missing post_id'), 400);
        }

        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            wp_send_json_error(array('message' => 'DOM extension is not available'), 500);
        }

        $texts = $this->get_post_headings($post_id);
        wp_send_json_success(array(
            'post_id' => $post_id,
            'headings' => $texts,
        ));
    }

    public function ajax_ai_write_seo_content() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_manual_ai_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $post_id = $post_id > 0 ? $post_id : 0;
        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $selected_text = isset($_POST['selected_text']) ? sanitize_text_field(wp_unslash($_POST['selected_text'])) : '';
        $sub_title = isset($_POST['sub_title']) ? sanitize_text_field(wp_unslash($_POST['sub_title'])) : '';
        if ($sub_title === '') {
            $sub_title = $selected_text;
        }
        $draft_html = isset($_POST['draft_html']) ? wp_kses_post(wp_unslash($_POST['draft_html'])) : '';
        $draft_html = is_string($draft_html) ? trim($draft_html) : '';

        $project_db_id = $this->get_current_project_id();
        if ($project_db_id <= 0) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        global $wpdb;

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_success(array('manual_ai' => 0));
        }

        if (!$this->table_has_column($projects_table, 'manual_ai')) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        $select_cols = array('id', 'project_name', 'manual_ai');
        if ($this->table_has_column($projects_table, 'brand_language')) {
            $select_cols[] = 'brand_language';
        }
        if ($this->table_has_column($projects_table, 'language')) {
            $select_cols[] = 'language';
        }

        $cols_sql = implode(', ', array_map(function ($c) {
            return $c;
        }, $select_cols));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project = $wpdb->get_row($wpdb->prepare("SELECT {$cols_sql} FROM {$projects_table_sql} WHERE id = %d LIMIT 1", $project_db_id), ARRAY_A);
        if (!is_array($project) || empty($project)) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        $manual_ai = !empty($project['manual_ai']) ? 1 : 0;
        if (!$manual_ai) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        if (!class_exists('SMarkPromptBank')) {
            wp_send_json_error(array('message' => 'SMarkPromptBank not available'), 500);
        }

        $lang_code = '';
        if (!empty($project['brand_language']) && is_string($project['brand_language'])) {
            $lang_code = $project['brand_language'];
        } elseif (!empty($project['language']) && is_string($project['language'])) {
            $lang_code = $project['language'];
        }
        $lang_code = strtolower(sanitize_text_field($lang_code));
        $lang_name = $lang_code === 'fa' ? 'Persian' : 'English';

        $project_name = isset($project['project_name']) ? sanitize_text_field((string) $project['project_name']) : '';

        $post_title = '';
        $post_url = '';
        if ($post_id > 0) {
            $post_title = get_the_title($post_id);
            $post_url = get_permalink($post_id);
            $post_title = is_string($post_title) ? $post_title : '';
            $post_url = is_string($post_url) ? $post_url : '';
        }

        $prompt_data = SMarkPromptBank::get_prompt_by_key('seo_content', array(
            'project_id' => (int) $project_db_id,
            'project_name' => $project_name,
            'brand_name' => $project_name,
            'language' => $lang_name,
            'keyword' => $keyword,
            'selected_text' => $selected_text,
            'heading' => $selected_text,
            'section_heading' => $selected_text,
            'sub_title' => $sub_title,
            'post_id' => $post_id,
            'post_title' => $post_title,
            'post_url' => $post_url,
            'title' => $post_title,
            'content' => $draft_html !== '' ? $draft_html : $selected_text,
            'draft' => $draft_html,
        ));

        if (!$prompt_data || empty($prompt_data['prompt_content'])) {
            wp_send_json_error(array('message' => 'SEO Content prompt not found'), 404);
        }

        wp_send_json_success(array(
            'manual_ai' => 1,
            'project_id' => (int) $project_db_id,
            'prompt_key' => 'seo_content',
            'prompt' => (string) $prompt_data['prompt_content'],
        ));
    }

    public function ajax_ai_write_seo_intro() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_manual_ai_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $post_id = $post_id > 0 ? $post_id : 0;
        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $current_title = isset($_POST['current_title']) ? sanitize_text_field(wp_unslash($_POST['current_title'])) : '';
        $draft_html = isset($_POST['draft_html']) ? wp_kses_post(wp_unslash($_POST['draft_html'])) : '';
        $draft_html = is_string($draft_html) ? trim($draft_html) : '';

        $project_db_id = $this->get_current_project_id();
        if ($project_db_id <= 0) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        global $wpdb;

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_success(array('manual_ai' => 0));
        }

        if (!$this->table_has_column($projects_table, 'manual_ai')) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        $select_cols = array('id', 'project_name', 'manual_ai');
        if ($this->table_has_column($projects_table, 'brand_language')) {
            $select_cols[] = 'brand_language';
        }
        if ($this->table_has_column($projects_table, 'language')) {
            $select_cols[] = 'language';
        }

        $cols_sql = implode(', ', array_map(function ($c) {
            return $c;
        }, $select_cols));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project = $wpdb->get_row($wpdb->prepare("SELECT {$cols_sql} FROM {$projects_table_sql} WHERE id = %d LIMIT 1", $project_db_id), ARRAY_A);
        if (!is_array($project) || empty($project)) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        $manual_ai = !empty($project['manual_ai']) ? 1 : 0;
        if (!$manual_ai) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        if (!class_exists('SMarkPromptBank')) {
            wp_send_json_error(array('message' => 'SMarkPromptBank not available'), 500);
        }

        $lang_code = '';
        if (!empty($project['brand_language']) && is_string($project['brand_language'])) {
            $lang_code = $project['brand_language'];
        } elseif (!empty($project['language']) && is_string($project['language'])) {
            $lang_code = $project['language'];
        }
        $lang_code = strtolower(sanitize_text_field($lang_code));
        $lang_name = $lang_code === 'fa' ? 'Persian' : 'English';

        $project_name = isset($project['project_name']) ? sanitize_text_field((string) $project['project_name']) : '';

        $post_title = '';
        $post_url = '';
        if ($post_id > 0) {
            $post_title = get_the_title($post_id);
            $post_url = get_permalink($post_id);
            $post_title = is_string($post_title) ? $post_title : '';
            $post_url = is_string($post_url) ? $post_url : '';
        }

        $sub_titles = $this->format_sub_titles_for_prompt($draft_html);

        $prompt_data = SMarkPromptBank::get_prompt_by_key('seo_intro', array(
            'project_id' => (int) $project_db_id,
            'project_name' => $project_name,
            'brand_name' => $project_name,
            'language' => $lang_name,
            'keyword' => $keyword,
            'post_id' => $post_id,
            'post_title' => $post_title,
            'post_url' => $post_url,
            'title' => $current_title !== '' ? $current_title : $post_title,
            'sub_title' => $sub_titles,
            'content' => $draft_html,
            'draft' => $draft_html,
        ));

        if (!$prompt_data || empty($prompt_data['prompt_content'])) {
            wp_send_json_error(array('message' => 'SEO Intro prompt not found'), 404);
        }

        wp_send_json_success(array(
            'manual_ai' => 1,
            'project_id' => (int) $project_db_id,
            'prompt_key' => 'seo_intro',
            'prompt' => (string) $prompt_data['prompt_content'],
        ));
    }

    public function ajax_ai_write_seo_conclusion() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_manual_ai_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $post_id = $post_id > 0 ? $post_id : 0;
        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $current_title = isset($_POST['current_title']) ? sanitize_text_field(wp_unslash($_POST['current_title'])) : '';
        $draft_html = isset($_POST['draft_html']) ? wp_kses_post(wp_unslash($_POST['draft_html'])) : '';
        $draft_html = is_string($draft_html) ? trim($draft_html) : '';

        $project_db_id = $this->get_current_project_id();
        if ($project_db_id <= 0) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        global $wpdb;

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_success(array('manual_ai' => 0));
        }

        if (!$this->table_has_column($projects_table, 'manual_ai')) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        $select_cols = array('id', 'project_name', 'manual_ai');
        if ($this->table_has_column($projects_table, 'brand_language')) {
            $select_cols[] = 'brand_language';
        }
        if ($this->table_has_column($projects_table, 'language')) {
            $select_cols[] = 'language';
        }

        $cols_sql = implode(', ', array_map(function ($c) {
            return $c;
        }, $select_cols));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project = $wpdb->get_row($wpdb->prepare("SELECT {$cols_sql} FROM {$projects_table_sql} WHERE id = %d LIMIT 1", $project_db_id), ARRAY_A);
        if (!is_array($project) || empty($project)) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        $manual_ai = !empty($project['manual_ai']) ? 1 : 0;
        if (!$manual_ai) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        if (!class_exists('SMarkPromptBank')) {
            wp_send_json_error(array('message' => 'SMarkPromptBank not available'), 500);
        }

        $lang_code = '';
        if (!empty($project['brand_language']) && is_string($project['brand_language'])) {
            $lang_code = $project['brand_language'];
        } elseif (!empty($project['language']) && is_string($project['language'])) {
            $lang_code = $project['language'];
        }
        $lang_code = strtolower(sanitize_text_field($lang_code));
        $lang_name = $lang_code === 'fa' ? 'Persian' : 'English';

        $project_name = isset($project['project_name']) ? sanitize_text_field((string) $project['project_name']) : '';

        $post_title = '';
        $post_url = '';
        if ($post_id > 0) {
            $post_title = get_the_title($post_id);
            $post_url = get_permalink($post_id);
            $post_title = is_string($post_title) ? $post_title : '';
            $post_url = is_string($post_url) ? $post_url : '';
        }

        $sub_titles = $this->format_sub_titles_for_prompt($draft_html);

        $prompt_data = SMarkPromptBank::get_prompt_by_key('seo_conclusion', array(
            'project_id' => (int) $project_db_id,
            'project_name' => $project_name,
            'brand_name' => $project_name,
            'language' => $lang_name,
            'keyword' => $keyword,
            'post_id' => $post_id,
            'post_title' => $post_title,
            'post_url' => $post_url,
            'title' => $current_title !== '' ? $current_title : $post_title,
            'sub_title' => $sub_titles,
            'content' => $draft_html,
            'draft' => $draft_html,
        ));

        if (!$prompt_data || empty($prompt_data['prompt_content'])) {
            wp_send_json_error(array('message' => 'SEO Conclusion prompt not found'), 404);
        }

        wp_send_json_success(array(
            'manual_ai' => 1,
            'project_id' => (int) $project_db_id,
            'prompt_key' => 'seo_conclusion',
            'prompt' => (string) $prompt_data['prompt_content'],
        ));
    }

    public function ajax_insert_to_page() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $project_id = (int) $this->get_current_project_id();
        if ($project_id <= 0) {
            wp_send_json_error(array('message' => 'Project not selected'), 400);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($post_id <= 0) {
            wp_send_json_error(array('message' => 'Missing post_id'), 400);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $items = $this->get_create_items_for_project($project_id);
        $found_idx = -1;
        foreach ($items as $idx => $it) {
            $pid = isset($it['postId']) ? (int) $it['postId'] : 0;
            if ($pid > 0 && $pid === $post_id) {
                $found_idx = (int) $idx;
                break;
            }
        }
        if ($found_idx < 0) {
            wp_send_json_error(array('message' => 'Not found'), 404);
        }

        $post = get_post($post_id);
        if (!$post || !isset($post->ID)) {
            wp_send_json_error(array('message' => 'Post not found'), 404);
        }

        $status = isset($post->post_status) ? (string) $post->post_status : '';
        if ($status !== 'draft') {
            wp_send_json_error(array('message' => 'Post is not a draft'), 409);
        }

        $sanitized = isset($_POST['draft_html']) ? wp_kses_post(wp_unslash($_POST['draft_html'])) : '';
        $sanitized = is_string($sanitized) ? trim($sanitized) : '';

        $updated = wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'draft',
            'post_content' => wp_slash($sanitized),
        ), true);

        if (is_wp_error($updated) || !$updated) {
            wp_send_json_error(array('message' => 'Update failed'), 500);
        }

        $post = get_post($post_id);
        $updated_at = $post && isset($post->post_modified) ? (string) $post->post_modified : current_time('mysql');
        $items[$found_idx]['status'] = $post && isset($post->post_status) ? (string) $post->post_status : 'draft';
        $items[$found_idx]['updatedAt'] = $updated_at;
        $items[$found_idx]['editUrl'] = get_edit_post_link((int) $post_id, 'raw');
        $this->save_create_items_for_project($project_id, $items);

        wp_send_json_success(array(
            'post_id' => (int) $post_id,
            'updatedAt' => $updated_at,
            'item' => $items[$found_idx],
        ));
    }

    public function ajax_ai_write_seo_title() {
        check_ajax_referer('SMARK_cm_nonce', 'nonce');

        if (!current_user_can('smark_manual_ai_access')) {
            wp_send_json_error(array('message' => __('Permission denied', 'smark')), 403);
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $post_id = $post_id > 0 ? $post_id : 0;
        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $current_title = isset($_POST['current_title']) ? sanitize_text_field(wp_unslash($_POST['current_title'])) : '';
        $draft_html = isset($_POST['draft_html']) ? wp_kses_post(wp_unslash($_POST['draft_html'])) : '';
        $draft_html = is_string($draft_html) ? trim($draft_html) : '';

        $project_db_id = $this->get_current_project_id();
        if ($project_db_id <= 0) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        global $wpdb;

        $projects_table = $this->resolve_projects_table();
        $projects_table_sql = $this->escape_db_identifier($projects_table);
        if ($projects_table_sql === '') {
            wp_send_json_success(array('manual_ai' => 0));
        }

        if (!$this->table_has_column($projects_table, 'manual_ai')) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        $select_cols = array('id', 'project_name', 'manual_ai');
        if ($this->table_has_column($projects_table, 'brand_language')) {
            $select_cols[] = 'brand_language';
        }
        if ($this->table_has_column($projects_table, 'language')) {
            $select_cols[] = 'language';
        }

        $cols_sql = implode(', ', array_map(function ($c) {
            return $c;
        }, $select_cols));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $project = $wpdb->get_row($wpdb->prepare("SELECT {$cols_sql} FROM {$projects_table_sql} WHERE id = %d LIMIT 1", $project_db_id), ARRAY_A);
        if (!is_array($project) || empty($project)) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        $manual_ai = !empty($project['manual_ai']) ? 1 : 0;
        if (!$manual_ai) {
            wp_send_json_success(array('manual_ai' => 0));
        }

        if (!class_exists('SMarkPromptBank')) {
            wp_send_json_error(array('message' => 'SMarkPromptBank not available'), 500);
        }

        $lang_code = '';
        if (!empty($project['brand_language']) && is_string($project['brand_language'])) {
            $lang_code = $project['brand_language'];
        } elseif (!empty($project['language']) && is_string($project['language'])) {
            $lang_code = $project['language'];
        }
        $lang_code = strtolower(sanitize_text_field($lang_code));
        $lang_name = $lang_code === 'fa' ? 'Persian' : 'English';

        $project_name = isset($project['project_name']) ? sanitize_text_field((string) $project['project_name']) : '';

        $post_title = '';
        $post_url = '';
        if ($post_id > 0) {
            $post_title = get_the_title($post_id);
            $post_url = get_permalink($post_id);
            $post_title = is_string($post_title) ? $post_title : '';
            $post_url = is_string($post_url) ? $post_url : '';
        }

        $sub_titles = $this->format_sub_titles_for_prompt($draft_html);

        $prompt_data = SMarkPromptBank::get_prompt_by_key('seo_title', array(
            'project_id' => (int) $project_db_id,
            'project_name' => $project_name,
            'brand_name' => $project_name,
            'language' => $lang_name,
            'keyword' => $keyword,
            'post_id' => $post_id,
            'post_title' => $post_title,
            'post_url' => $post_url,
            'title' => $current_title !== '' ? $current_title : $post_title,
            'sub_title' => $sub_titles,
            'content' => $draft_html,
            'draft' => $draft_html,
        ));

        if (!$prompt_data || empty($prompt_data['prompt_content'])) {
            wp_send_json_error(array('message' => 'SEO Title prompt not found'), 404);
        }

        wp_send_json_success(array(
            'manual_ai' => 1,
            'project_id' => (int) $project_db_id,
            'prompt_key' => 'seo_title',
            'prompt' => (string) $prompt_data['prompt_content'],
        ));
    }

    public function register_forreview_rest_routes() {
        register_rest_route('smark/v1', '/forreview/(?P<id>\\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'rest_forreview_get'),
                'permission_callback' => array($this, 'rest_forreview_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'rest_forreview_enable'),
                'permission_callback' => array($this, 'rest_forreview_permission'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'rest_forreview_disable'),
                'permission_callback' => array($this, 'rest_forreview_permission'),
            ),
        ));
    }

    public function rest_forreview_permission($request) {
        $post_id = (int) $request->get_param('id');
        return $post_id > 0 && current_user_can('edit_post', $post_id);
    }

    private function rest_forreview_response($post_id) {
        return array(
            'postId' => (int) $post_id,
            'enabled' => $this->is_forreview_enabled($post_id),
            'url' => $this->get_forreview_url($post_id),
        );
    }

    public function rest_forreview_get($request) {
        $post_id = (int) $request->get_param('id');
        if ($post_id <= 0) {
            return new WP_REST_Response(array('message' => 'Invalid post ID'), 400);
        }

        return new WP_REST_Response($this->rest_forreview_response($post_id), 200);
    }

    public function rest_forreview_enable($request) {
        $post_id = (int) $request->get_param('id');
        if ($post_id <= 0) {
            return new WP_REST_Response(array('message' => 'Invalid post ID'), 400);
        }

        $status = get_post_status($post_id);
        $status = is_string($status) ? $status : '';
        if ($status === 'publish' || $status === 'private' || $status === 'trash') {
            return new WP_REST_Response(array('message' => 'Not available for published content'), 400);
        }

        $slug = $this->ensure_post_has_slug($post_id);
        if ($slug === '') {
            return new WP_REST_Response(array('message' => 'Unable to generate slug'), 400);
        }

        if (!$this->is_forreview_enabled($post_id)) {
            update_post_meta($post_id, self::FORREVIEW_META_KEY, time());
        }

        return new WP_REST_Response($this->rest_forreview_response($post_id), 200);
    }

    public function rest_forreview_disable($request) {
        $post_id = (int) $request->get_param('id');
        if ($post_id <= 0) {
            return new WP_REST_Response(array('message' => 'Invalid post ID'), 400);
        }

        delete_post_meta($post_id, self::FORREVIEW_META_KEY);
        return new WP_REST_Response($this->rest_forreview_response($post_id), 200);
    }

    public function register_forreview_metabox() {
        $types = get_post_types(array('show_ui' => true), 'names');
        $types = is_array($types) ? $types : array('post', 'page');

        foreach ($types as $post_type) {
            add_meta_box(
                'smark_forreview',
                'SMark',
                array($this, 'render_forreview_metabox'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    public function render_forreview_metabox($post) {
        if (!is_object($post) || !isset($post->ID)) {
            return;
        }

        $post_id = (int) $post->ID;
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $status = get_post_status($post_id);
        $status = is_string($status) ? $status : '';
        $is_published = ($status === 'publish' || $status === 'private');

        $enabled = $this->is_forreview_enabled($post_id);
        $url = $enabled ? $this->get_forreview_url($post_id) : '';
        $url = is_string($url) ? $url : '';

        echo '<div class="smark-forreview-metabox" data-post-id="' . esc_attr($post_id) . '" data-nonce="' . esc_attr(wp_create_nonce('SMARK_cm_forreview')) . '">';

        if ($is_published) {
            echo '<p>' . esc_html__('This content is already published.', 'smark') . '</p>';
            echo '</div>';
            return;
        }

        if (!$enabled) {
            echo '<button type="button" class="button button-primary smark-forreview-enable">' . esc_html__('ساخت لینک موقت', 'smark') . '</button>';
            echo '<p style="margin:10px 0 0;color:#666;font-size:12px">' . esc_html__('لینک ساخته‌شده noindex است و در سایت‌مپ نمایش داده نمی‌شود.', 'smark') . '</p>';
            echo '</div>';
            return;
        }

        echo '<p style="margin:0 0 8px">' . esc_html__('لینک موقت:', 'smark') . '</p>';
        echo '<input type="text" class="widefat smark-forreview-url" readonly value="' . esc_attr($url) . '">';
        echo '<p style="display:flex;gap:6px;margin:8px 0 0">';
        echo '<button type="button" class="button smark-forreview-copy">' . esc_html__('کپی', 'smark') . '</button>';
        echo '<button type="button" class="button smark-forreview-disable">' . esc_html__('لغو', 'smark') . '</button>';
        echo '</p>';
        echo '</div>';
    }

    public function ajax_forreview_enable() {
        check_ajax_referer('SMARK_cm_forreview', 'nonce');

        $post_id = isset($_POST['postId']) ? (int) $_POST['postId'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
        if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Forbidden'), 403);
        }

        $status = get_post_status($post_id);
        $status = is_string($status) ? $status : '';
        if ($status === 'publish' || $status === 'private' || $status === 'trash') {
            wp_send_json_error(array('message' => 'Not available'), 400);
        }

        $slug = $this->ensure_post_has_slug($post_id);
        if ($slug === '') {
            wp_send_json_error(array('message' => 'Unable to generate slug'), 400);
        }

        update_post_meta($post_id, self::FORREVIEW_META_KEY, time());
        wp_send_json_success(array(
            'enabled' => true,
            'url' => $this->get_forreview_url($post_id),
        ));
    }

    public function ajax_forreview_disable() {
        check_ajax_referer('SMARK_cm_forreview', 'nonce');

        $post_id = isset($_POST['postId']) ? (int) $_POST['postId'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
        if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Forbidden'), 403);
        }

        delete_post_meta($post_id, self::FORREVIEW_META_KEY);
        wp_send_json_success(array(
            'enabled' => false,
            'url' => '',
        ));
    }
}

// Initialized in smark.php alongside other features.
