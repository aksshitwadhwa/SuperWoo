<?php
defined('ABSPATH') || exit;

class SuperWoo_GitHub_Updater {
    const API_URL = 'https://api.github.com/repos/aksshitwadhwa/SuperWoo/releases/latest';

    public function hooks() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('update_plugins_github.com', [$this, 'check_update_uri'], 10, 4);
        add_filter('plugins_api', [$this, 'plugin_information'], 10, 3);
    }

    public function check_update_uri($update, $plugin_file, $plugin_data, $locales) {
        if (strtolower(plugin_basename(SUPERWOO_FILE)) !== strtolower($plugin_file)) {
            return $update;
        }

        $release = $this->latest_release();
        $version = $this->release_version($release);
        $package = !empty($release['assets'][0]['browser_download_url'])
            ? $release['assets'][0]['browser_download_url']
            : (!empty($release['zipball_url']) ? $release['zipball_url'] : '');

        if (!$version || version_compare($version, SUPERWOO_VERSION, '<=')) {
            return false;
        }

        return (object) [
            'id'          => 'https://github.com/aksshitwadhwa/SuperWoo',
            'slug'        => dirname($plugin_file),
            'plugin'      => $plugin_file,
            'new_version' => $version,
            'url'         => 'https://github.com/aksshitwadhwa/SuperWoo',
            'package'     => $package,
        ];
    }

    public function check_for_update($transient) {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $release = $this->latest_release();
        $version = $this->release_version($release);
        $plugin = plugin_basename(SUPERWOO_FILE);
        // Keep the response key aligned with the key WordPress is checking,
        // including installations whose plugin directory casing differs.
        foreach ((array) $transient->checked as $checked_plugin => $checked_version) {
            if (strtolower(basename($checked_plugin)) === strtolower(basename($plugin))) {
                $plugin = $checked_plugin;
                break;
            }
        }

        if (!$version || version_compare($version, SUPERWOO_VERSION, '<=')) {
            return $transient;
        }

        $package = !empty($release['assets'][0]['browser_download_url'])
            ? $release['assets'][0]['browser_download_url']
            : (!empty($release['zipball_url']) ? $release['zipball_url'] : '');

        if (!$package) {
            return $transient;
        }

        $transient->response[$plugin] = (object) [
            'id'          => 'github.com/aksshitwadhwa/SuperWoo',
            'slug'        => dirname($plugin),
            'plugin'      => $plugin,
            'new_version' => $version,
            'url'         => 'https://github.com/aksshitwadhwa/SuperWoo',
            'package'     => $package,
        ];

        return $transient;
    }

    public function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || strtolower(dirname(plugin_basename(SUPERWOO_FILE))) !== strtolower($args->slug)) {
            return $result;
        }

        $release = $this->latest_release();
        $version = $this->release_version($release);

        if (!$version) {
            return $result;
        }

        return (object) [
            'name'          => 'SuperWoo',
            'slug'          => dirname(plugin_basename(SUPERWOO_FILE)),
            'version'       => $version,
            'author'        => '<a href="https://digtize.com/">Aksshit Wadhwa</a>',
            'homepage'      => 'https://github.com/aksshitwadhwa/SuperWoo',
            'download_link' => !empty($release['assets'][0]['browser_download_url']) ? $release['assets'][0]['browser_download_url'] : $release['zipball_url'],
            'sections'      => ['description' => !empty($release['body']) ? wpautop(wp_kses_post($release['body'])) : 'SuperWoo plugin updates from GitHub.'],
        ];
    }

    private function latest_release() {
        $cached = get_site_transient('superwoo_github_latest_release');
        if (false !== $cached) {
            return is_array($cached) ? $cached : [];
        }

        $response = wp_remote_get(self::API_URL, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'SuperWoo/' . SUPERWOO_VERSION],
        ]);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_site_transient('superwoo_github_latest_release', [], HOUR_IN_SECONDS);
            return [];
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        $release = is_array($release) ? $release : [];
        set_site_transient('superwoo_github_latest_release', $release, HOUR_IN_SECONDS);
        return $release;
    }

    private function release_version($release) {
        $tag = !empty($release['tag_name']) ? $release['tag_name'] : '';
        return ltrim($tag, 'vV');
    }
}
