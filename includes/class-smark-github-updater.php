<?php
/**
 * GitHub release updater for SMark.
 *
 * @package SMark
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SMark_GitHub_Updater')) {
    class SMark_GitHub_Updater {
        private $plugin_file;
        private $plugin_basename;
        private $slug;
        private $github_owner;
        private $github_repo;
        private $github_repo_url;
        private $current_version;
        private $cache_key;

        public function __construct($plugin_file, $current_version, $github_owner, $github_repo) {
            $this->plugin_file = $plugin_file;
            $this->plugin_basename = plugin_basename($plugin_file);
            $this->slug = 'smark';
            $this->github_owner = $github_owner;
            $this->github_repo = $github_repo;
            $this->github_repo_url = 'https://github.com/' . rawurlencode($github_owner) . '/' . rawurlencode($github_repo);
            $this->current_version = $current_version;
            $this->cache_key = 'smark_github_release_' . md5($github_owner . '/' . $github_repo);

            add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
            add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
            add_filter('upgrader_source_selection', array($this, 'fix_github_zip_folder_name'), 10, 4);
            add_filter('http_request_args', array($this, 'add_github_headers'), 10, 2);
        }

        public function check_for_update($transient) {
            if (!is_object($transient)) {
                $transient = new stdClass();
            }

            if (empty($transient->checked) || !isset($transient->checked[$this->plugin_basename])) {
                return $transient;
            }

            $release = $this->get_latest_release();
            if (!$release || empty($release['version']) || empty($release['package'])) {
                return $transient;
            }

            if (version_compare($release['version'], $this->current_version, '<=')) {
                return $transient;
            }

            $transient->response[$this->plugin_basename] = (object) array(
                'id' => $this->github_repo_url,
                'slug' => $this->slug,
                'plugin' => $this->plugin_basename,
                'new_version' => $release['version'],
                'url' => $this->github_repo_url,
                'package' => $release['package'],
                'tested' => isset($release['tested']) ? $release['tested'] : '',
                'requires' => isset($release['requires']) ? $release['requires'] : '',
                'requires_php' => isset($release['requires_php']) ? $release['requires_php'] : '',
            );

            return $transient;
        }

        public function plugin_info($result, $action, $args) {
            if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->slug) {
                return $result;
            }

            $release = $this->get_latest_release();
            if (!$release) {
                return $result;
            }

            return (object) array(
                'name' => 'SMark',
                'slug' => $this->slug,
                'version' => isset($release['version']) ? $release['version'] : $this->current_version,
                'author' => '<a href="https://saeedhasani.com">Saeed Hasani</a>',
                'homepage' => $this->github_repo_url,
                'requires' => isset($release['requires']) ? $release['requires'] : '5.0',
                'tested' => isset($release['tested']) ? $release['tested'] : '',
                'requires_php' => isset($release['requires_php']) ? $release['requires_php'] : '7.4',
                'download_link' => isset($release['package']) ? $release['package'] : '',
                'last_updated' => isset($release['published_at']) ? $release['published_at'] : '',
                'sections' => array(
                    'description' => '<p>SMark is a multi-purpose WordPress plugin for SEO, content, email marketing, social media, backlinks, keyword research, competitor analysis, and AI-assisted marketing workflows.</p>',
                    'changelog' => !empty($release['body']) ? wpautop(esc_html($release['body'])) : '<p>See the GitHub release notes for details.</p>',
                ),
            );
        }

        public function fix_github_zip_folder_name($source, $remote_source, $upgrader, $hook_extra = array()) {
            if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
                return $source;
            }

            global $wp_filesystem;
            if (!$wp_filesystem || strpos(basename($source), $this->github_repo . '-') !== 0) {
                return $source;
            }

            $target = trailingslashit($remote_source) . $this->slug;
            if ($wp_filesystem->exists($target)) {
                $wp_filesystem->delete($target, true);
            }

            if ($wp_filesystem->move($source, $target, true)) {
                return $target;
            }

            return $source;
        }

        public function add_github_headers($args, $url) {
            if (strpos($url, 'api.github.com/repos/' . $this->github_owner . '/' . $this->github_repo) === false) {
                return $args;
            }

            if (empty($args['headers']) || !is_array($args['headers'])) {
                $args['headers'] = array();
            }

            $args['headers']['Accept'] = 'application/vnd.github+json';
            $args['headers']['User-Agent'] = 'SMark WordPress Plugin/' . $this->current_version;

            return $args;
        }

        private function get_latest_release($force_refresh = false) {
            if (!$force_refresh) {
                $cached = get_site_transient($this->cache_key);
                if (is_array($cached)) {
                    return $cached;
                }
            }

            $url = sprintf(
                'https://api.github.com/repos/%s/%s/releases/latest',
                rawurlencode($this->github_owner),
                rawurlencode($this->github_repo)
            );

            $response = wp_remote_get($url, array(
                'timeout' => 10,
                'redirection' => 5,
            ));

            if (is_wp_error($response)) {
                return false;
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            if ($status !== 200) {
                return false;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data) || empty($data['tag_name'])) {
                return false;
            }

            $tag = ltrim((string) $data['tag_name'], 'vV');
            $release = array(
                'version' => $tag,
                'tag_name' => (string) $data['tag_name'],
                'package' => $this->build_release_zip_url((string) $data['tag_name']),
                'body' => isset($data['body']) ? (string) $data['body'] : '',
                'published_at' => isset($data['published_at']) ? (string) $data['published_at'] : '',
                'requires' => '5.0',
                'requires_php' => '7.4',
                'tested' => '',
            );

            set_site_transient($this->cache_key, $release, 6 * HOUR_IN_SECONDS);
            return $release;
        }

        private function build_release_zip_url($tag_name) {
            return sprintf(
                'https://github.com/%s/%s/archive/refs/tags/%s.zip',
                rawurlencode($this->github_owner),
                rawurlencode($this->github_repo),
                rawurlencode($tag_name)
            );
        }
    }
}
