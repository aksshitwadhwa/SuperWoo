<?php
defined('ABSPATH') || exit;

class SuperWoo_GitHub_Updater {
    const API_URL = 'https://digtize.com/plugins/superwoo/update.json';
    const CACHE_KEY = 'superwoo_server_latest_release';

    public function hooks() {
        add_filter('update_plugins_digtize.com', [$this, 'check_update_uri'], 10, 4);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_information'], 10, 3);
    }

    public function check_update_uri($update, $plugin_data, $plugin_file, $locales) {
        if (strtolower(plugin_basename(SUPERWOO_FILE)) !== strtolower($plugin_file)) {
            return $update;
        }

        $release = $this->latest_release();
        return $this->get_update($release, $plugin_file) ?: $update;
    }

    public function inject_update($transient) {
        if (!is_object($transient)) {
            return $transient;
        }

        $plugin_file = plugin_basename(SUPERWOO_FILE);
        $update = $this->get_update($this->latest_release(), $plugin_file);
        if ($update) {
            $transient->response[$plugin_file] = (object) $update;
        }

        return $transient;
    }

    public function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || strtolower(dirname(plugin_basename(SUPERWOO_FILE))) !== strtolower($args->slug)) {
            return $result;
        }

        $release = $this->latest_release();
        $version = isset($release['version']) ? (string) $release['version'] : '';
        if (!$version) {
            return $result;
        }

        return (object) [
            'name'          => 'SuperWoo',
            'slug'          => dirname(plugin_basename(SUPERWOO_FILE)),
            'version'       => $version,
            'author'        => '<a href="https://digtize.com/">Aksshit Wadhwa</a>',
            'homepage'      => 'https://digtize.com/plugins/superwoo/',
            'download_link' => isset($release['package']) ? esc_url_raw($release['package']) : '',
            'sections'      => [
                'description' => !empty($release['description']) ? wpautop(wp_kses_post($release['description'])) : __('SuperWoo plugin updates from Digtize.', 'superwoo'),
            ],
        ];
    }

    private function get_update($release, $plugin_file) {
        $version = isset($release['version']) ? (string) $release['version'] : '';
        $package = isset($release['package']) ? esc_url_raw($release['package']) : '';

        if (!$version || !$package || version_compare($version, SUPERWOO_VERSION, '<=')) {
            return false;
        }

        return [
            'id'           => self::API_URL,
            'slug'         => dirname($plugin_file),
            'plugin'       => $plugin_file,
            'new_version'  => $version,
            'version'      => $version,
            'url'          => 'https://digtize.com/plugins/superwoo/',
            'package'      => $package,
            'requires'     => isset($release['requires']) ? (string) $release['requires'] : '6.2',
            'requires_php' => isset($release['requires_php']) ? (string) $release['requires_php'] : '7.4',
        ];
    }

    private function latest_release() {
        $cached = get_site_transient(self::CACHE_KEY);
        if (false !== $cached) {
            return is_array($cached) ? $cached : [];
        }

        $response = wp_remote_get(self::API_URL, [
            'timeout' => 15,
            'headers' => [
                'Accept'     => 'application/json',
                'User-Agent' => 'SuperWoo/' . SUPERWOO_VERSION,
            ],
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_site_transient(self::CACHE_KEY, [], MINUTE_IN_SECONDS);
            return [];
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        $release = is_array($release) ? $release : [];
        set_site_transient(self::CACHE_KEY, $release, 5 * MINUTE_IN_SECONDS);

        return $release;
    }
}
