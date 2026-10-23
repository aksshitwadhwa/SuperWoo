<?php
defined('ABSPATH') || exit;

/**
 * Retrieves the SuperWoo update offer from the Digtize HTTPS feed.
 *
 * Release metadata is not cryptographically signed. HTTPS, exact host/path
 * checks, redirect rejection, and package hash/layout checks reduce transport
 * and operational risk but do not protect against compromise of the feed host.
 */
class SuperWoo_GitHub_Updater {
    const API_URL = 'https://digtize.com/plugins/superwoo/update.json';
    const CACHE_KEY = 'superwoo_server_latest_release';
    const PACKAGE_HOST = 'digtize.com';
    const PACKAGE_PATH = '/plugins/superwoo/';
    const MAX_METADATA_BYTES = 65536;
    const MAX_PACKAGE_BYTES = 52428800;

    private $temporary_package = '';

    public function hooks() {
        add_filter('update_plugins_digtize.com', [$this, 'check_update_uri'], 10, 4);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_information'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'verified_package_download'], 10, 4);
        add_action('upgrader_process_complete', [$this, 'cleanup_temporary_package'], 10, 2);
    }

    public function check_update_uri($update, $plugin_data, $plugin_file, $locales) {
        if (strtolower(plugin_basename(SUPERWOO_FILE)) !== strtolower($plugin_file)) {
            return $update;
        }
        return $this->get_update($this->latest_release(), $plugin_file) ?: $update;
    }

    public function inject_update($transient) {
        if (!is_object($transient)) {
            return $transient;
        }
        $plugin_file = plugin_basename(SUPERWOO_FILE);
        $update = $this->get_update($this->latest_release(), $plugin_file);
        if ($update) {
            $transient->response[$plugin_file] = (object) $update;
        } else {
            unset($transient->response[$plugin_file]);
        }
        return $transient;
    }

    public function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || strtolower(dirname(plugin_basename(SUPERWOO_FILE))) !== strtolower($args->slug)) {
            return $result;
        }
        $release = $this->latest_release();
        if (empty($release['version']) || empty($release['package'])) {
            return $result;
        }
        return (object) [
            'name'          => 'SuperWoo',
            'slug'          => dirname(plugin_basename(SUPERWOO_FILE)),
            'version'       => $release['version'],
            'author'        => '<a href="https://digtize.com/">Aksshit Wadhwa</a>',
            'homepage'      => 'https://digtize.com/plugins/superwoo/',
            'download_link' => $release['package'],
            'sections'      => [
                'description' => __('SuperWoo plugin updates from Digtize.', 'superwoo'),
            ],
        ];
    }

    /**
     * Download the offered package over verified HTTPS and check its advertised
     * hash and ZIP layout before returning it to WordPress. Since the feed is
     * unsigned, the hash is not an independent authenticity guarantee.
     */
    public function verified_package_download($reply, $package, $upgrader, $hook_extra) {
        if (!$this->is_superwoo_package_request($hook_extra)) {
            return $reply;
        }
        if (is_wp_error($reply)) {
            return $reply;
        }
        if (false !== $reply) {
            return new WP_Error('superwoo_update_download_preempted', __('SuperWoo could not verify the update package download.', 'superwoo'));
        }

        $release = $this->latest_release();
        if (!$release || empty($release['package']) || !hash_equals($release['package'], (string) $package)) {
            return new WP_Error('superwoo_update_package_untrusted', __('The SuperWoo update package URL does not match the current update feed.', 'superwoo'));
        }
        if (empty($release['sha256']) || empty($release['package_size']) || !function_exists('wp_tempnam') || !class_exists('ZipArchive')) {
            return new WP_Error('superwoo_update_verification_unavailable', __('SuperWoo update package verification is unavailable.', 'superwoo'));
        }

        $filename = wp_tempnam('superwoo-update.zip');
        if (!$filename) {
            return new WP_Error('superwoo_update_tempfile_failed', __('Could not create a temporary file for the SuperWoo update.', 'superwoo'));
        }
        $this->temporary_package = $filename;
        $response = wp_safe_remote_get($release['package'], [
            'timeout'             => 60,
            'redirection'         => 0,
            'sslverify'           => true,
            'stream'              => true,
            'filename'            => $filename,
            'limit_response_size' => min(self::MAX_PACKAGE_BYTES, $release['package_size'] + 1),
            'headers'             => [
                'Accept'     => 'application/zip, application/octet-stream;q=0.9',
                'User-Agent' => 'SuperWoo/' . SUPERWOO_VERSION,
            ],
        ]);
        if (is_wp_error($response)) {
            $this->cleanup_temporary_package();
            return new WP_Error('superwoo_update_download_failed', __('The SuperWoo update package could not be downloaded over HTTPS.', 'superwoo'));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $content_length = wp_remote_retrieve_header($response, 'content-length');
        $actual_size = file_exists($filename) ? filesize($filename) : false;
        if (200 !== $status || false === $actual_size || (int) $actual_size !== (int) $release['package_size'] || $actual_size > self::MAX_PACKAGE_BYTES) {
            $this->cleanup_temporary_package();
            return new WP_Error('superwoo_update_package_size_invalid', __('The SuperWoo update package response had an invalid size or status.', 'superwoo'));
        }
        if ((!is_string($content_length) && !is_int($content_length)) || !preg_match('/\A[0-9]+\z/', (string) $content_length) || (string) (int) $content_length !== (string) (int) $release['package_size']) {
            $this->cleanup_temporary_package();
            return new WP_Error('superwoo_update_content_length_invalid', __('The SuperWoo update package response length did not match the update feed.', 'superwoo'));
        }
        $digest = hash_file('sha256', $filename);
        if (!is_string($digest) || !hash_equals($release['sha256'], $digest) || !$this->is_expected_plugin_zip($filename)) {
            $this->cleanup_temporary_package();
            return new WP_Error('superwoo_update_integrity_failed', __('The SuperWoo update package failed its hash or ZIP validation.', 'superwoo'));
        }
        register_shutdown_function([$this, 'cleanup_temporary_package']);
        return $filename;
    }

    public function cleanup_temporary_package($upgrader = null, $options = null) {
        if ($this->temporary_package && is_file($this->temporary_package)) {
            @unlink($this->temporary_package);
        }
        $this->temporary_package = '';
    }

    private function latest_release() {
        $cached = get_site_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            $validated = $this->validate_release($cached);
            if ($validated) {
                return $validated;
            }
            delete_site_transient(self::CACHE_KEY);
        }

        $response = wp_safe_remote_get(self::API_URL, [
            'timeout'             => 15,
            'redirection'         => 0,
            'sslverify'           => true,
            'limit_response_size' => self::MAX_METADATA_BYTES + 1,
            'headers'             => [
                'Accept'     => 'application/json',
                'User-Agent' => 'SuperWoo/' . SUPERWOO_VERSION,
            ],
        ]);
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return [];
        }
        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || '' === $body || strlen($body) > self::MAX_METADATA_BYTES) {
            return [];
        }
        $release = json_decode($body, true);
        $release = is_array($release) ? $this->validate_release($release) : false;
        if (!$release) {
            return [];
        }
        set_site_transient(self::CACHE_KEY, $release, 5 * MINUTE_IN_SECONDS);
        return $release;
    }

    private function validate_release($release) {
        if (!is_array($release) || !isset($release['version'], $release['package']) || !$this->is_valid_version($release['version']) || !$this->is_approved_package_url($release['package'])) {
            return false;
        }
        if (isset($release['sha256']) || isset($release['package_size'])) {
            if (!isset($release['sha256'], $release['package_size']) || !is_string($release['sha256']) || !preg_match('/\A[a-f0-9]{64}\z/', $release['sha256']) || !is_int($release['package_size']) || $release['package_size'] < 1 || $release['package_size'] > self::MAX_PACKAGE_BYTES) {
                return false;
            }
        }
        $release['requires'] = isset($release['requires']) && $this->is_valid_version($release['requires']) ? (string) $release['requires'] : '6.2';
        $release['requires_php'] = isset($release['requires_php']) && $this->is_valid_version($release['requires_php']) ? (string) $release['requires_php'] : '7.4';
        return $release;
    }

    private function is_valid_version($version) {
        return is_string($version) && strlen($version) <= 64 && (bool) preg_match('/\A[0-9]+\.[0-9]+(?:\.[0-9]+)?(?:-[0-9A-Za-z.-]+)?\z/', $version);
    }

    private function is_approved_package_url($url) {
        if (!is_string($url) || strlen($url) > 2048 || false !== strpos($url, '%') || false !== strpos($url, '\\')) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || 'https' !== strtolower($parts['scheme'] ?? '') || self::PACKAGE_HOST !== strtolower($parts['host'] ?? '') || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && 443 !== (int) $parts['port']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }
        $path = $parts['path'] ?? '';
        return 0 === strpos($path, self::PACKAGE_PATH) && (bool) preg_match('#\A/plugins/superwoo/[A-Za-z0-9._-]+\.zip\z#', $path);
    }

    private function is_superwoo_package_request($hook_extra) {
        if (!is_array($hook_extra)) {
            return false;
        }
        $plugin = isset($hook_extra['plugin']) ? (string) $hook_extra['plugin'] : '';
        $slug = isset($hook_extra['slug']) ? (string) $hook_extra['slug'] : '';
        return 'superwoo' === strtolower(dirname($plugin)) || 'superwoo' === strtolower($plugin) || 'superwoo' === strtolower($slug);
    }

    private function is_expected_plugin_zip($filename) {
        $zip = new ZipArchive();
        if (true !== $zip->open($filename)) {
            return false;
        }
        $found_main_file = false;
        $valid = true;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!is_string($name) || 0 === strpos($name, '/') || false !== strpos($name, "\\") || false !== strpos($name, '//') || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $name) || 'superwoo' !== strtolower((string) strtok($name, '/'))) {
                $valid = false;
                break;
            }
            if (0 === strcasecmp($name, 'SuperWoo/superwoo.php')) {
                $found_main_file = true;
            }
        }
        $zip->close();
        return $valid && $found_main_file;
    }

    private function get_update($release, $plugin_file) {
        if (empty($release['version']) || empty($release['package']) || version_compare($release['version'], SUPERWOO_VERSION, '<=')) {
            return false;
        }
        return [
            'id'           => self::API_URL,
            'slug'         => dirname($plugin_file),
            'plugin'       => $plugin_file,
            'new_version'  => $release['version'],
            'version'      => $release['version'],
            'url'          => 'https://digtize.com/plugins/superwoo/',
            'package'      => $release['package'],
            'requires'     => $release['requires'],
            'requires_php' => $release['requires_php'],
        ];
    }
}
