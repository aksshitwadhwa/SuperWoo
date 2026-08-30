<?php
defined('ABSPATH') || exit;

class SuperWoo_GitHub_Updater {
    const API_URL = 'https://api.github.com/repos/aksshitwadhwa/SuperWoo/releases/latest';
    const CACHE_KEY = 'superwoo_github_latest_release';

    public function hooks() {
        add_filter('update_plugins_github.com', [$this, 'check_update_uri'], 10, 4);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_information'], 10, 3);
    }
    public function check_update_uri($update, $plugin_data, $plugin_file, $locales) {
        if (strtolower(plugin_basename(SUPERWOO_FILE)) !== strtolower($plugin_file)) { return $update; }
        $release = $this->latest_release(); $version = !empty($release['tag_name']) ? ltrim($release['tag_name'], 'vV') : '';
        $package = !empty($release['assets'][0]['browser_download_url']) ? $release['assets'][0]['browser_download_url'] : ($release['zipball_url'] ?? '');
        if (!$version || version_compare($version, SUPERWOO_VERSION, '<=')) { return $update; }
        return ['id' => self::API_URL, 'slug' => dirname($plugin_file), 'version' => $version, 'url' => 'https://github.com/aksshitwadhwa/SuperWoo', 'package' => $package, 'requires_php' => '7.4'];
    }

    public function inject_update($transient) {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $plugin_file = plugin_basename(SUPERWOO_FILE);
        $release = $this->latest_release();
        $version = !empty($release['tag_name']) ? ltrim($release['tag_name'], 'vV') : '';
        $package = !empty($release['assets'][0]['browser_download_url']) ? $release['assets'][0]['browser_download_url'] : ($release['zipball_url'] ?? '');

        if (!$version || !$package || version_compare($version, SUPERWOO_VERSION, '<=')) {
            return $transient;
        }

        $transient->response[$plugin_file] = (object) [
            'id'           => self::API_URL,
            'slug'         => dirname($plugin_file),
            'plugin'       => $plugin_file,
            'new_version'  => $version,
            'url'          => 'https://github.com/aksshitwadhwa/SuperWoo',
            'package'      => $package,
            'requires_php' => '7.4',
        ];

        return $transient;
    }
    public function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || strtolower(dirname(plugin_basename(SUPERWOO_FILE))) !== strtolower($args->slug)) { return $result; }
        $release = $this->latest_release(); $version = !empty($release['tag_name']) ? ltrim($release['tag_name'], 'vV') : '';
        if (!$version) { return $result; }
        return (object) ['name' => 'SuperWoo', 'slug' => dirname(plugin_basename(SUPERWOO_FILE)), 'version' => $version, 'author' => '<a href="https://digtize.com/">Aksshit Wadhwa</a>', 'homepage' => 'https://github.com/aksshitwadhwa/SuperWoo', 'download_link' => !empty($release['assets'][0]['browser_download_url']) ? $release['assets'][0]['browser_download_url'] : ($release['zipball_url'] ?? ''), 'sections' => ['description' => !empty($release['body']) ? wpautop(wp_kses_post($release['body'])) : 'SuperWoo plugin updates from GitHub.']];
    }
    private function latest_release() {
        $cached = get_site_transient(self::CACHE_KEY); if (false !== $cached) { return is_array($cached) ? $cached : []; }
        $response = wp_remote_get(self::API_URL, ['timeout' => 10, 'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'SuperWoo/' . SUPERWOO_VERSION]]);
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) { set_site_transient(self::CACHE_KEY, [], MINUTE_IN_SECONDS); return []; }
        $release = json_decode(wp_remote_retrieve_body($response), true); $release = is_array($release) ? $release : []; set_site_transient(self::CACHE_KEY, $release, 5 * MINUTE_IN_SECONDS); return $release;
    }
}
