<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/class-superwoo-update-manifest.php';

/**
 * Verifies SuperWoo release metadata and packages before WordPress can install them.
 *
 * Production trust anchors intentionally remain empty until Stage 2 has approved
 * key custody and the update service publishes the signed manifest format.
 */
class SuperWoo_GitHub_Updater {
    const API_URL = 'https://digtize.com/plugins/superwoo/update.json';
    const CACHE_KEY = 'superwoo_server_latest_release';
    const SEQUENCE_OPTION = 'superwoo_updater_release_sequence';
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
        } else {
            // Remove stale cached offers when no currently trusted release exists.
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
     * Intercept only this plugin's exact verified package URL. Every failure for
     * that URL returns WP_Error so WordPress cannot fall back to an unsigned fetch.
     */
    public function verified_package_download($reply, $package, $upgrader, $hook_extra) {
        if (!$this->is_superwoo_package_request($package, $hook_extra)) {
            return $reply;
        }
        if (is_wp_error($reply)) {
            return $reply;
        }
        if (false !== $reply) {
            return new WP_Error('superwoo_update_preempted_download', __('SuperWoo update verification cannot accept a package supplied by another downloader.', 'superwoo'));
        }

        $release = $this->latest_release();
        if (!$release || empty($release['package']) || !hash_equals($release['package'], (string) $package)) {
            return new WP_Error('superwoo_update_untrusted_package', __('SuperWoo update package is not covered by trusted release metadata.', 'superwoo'));
        }

        if (!function_exists('wp_tempnam') || !class_exists('ZipArchive')) {
            return new WP_Error('superwoo_update_verification_unavailable', __('SuperWoo update verification is unavailable on this server.', 'superwoo'));
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
            return new WP_Error('superwoo_update_download_failed', __('The SuperWoo update package could not be downloaded safely.', 'superwoo'));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $content_length = wp_remote_retrieve_header($response, 'content-length');
        $actual_size = file_exists($filename) ? filesize($filename) : false;
        if (200 !== $status || false === $actual_size || (int) $actual_size !== (int) $release['package_size'] || $actual_size > self::MAX_PACKAGE_BYTES) {
            $this->cleanup_temporary_package();
            return new WP_Error('superwoo_update_package_size_invalid', __('The SuperWoo update package had an invalid response or size.', 'superwoo'));
        }

        if ((!is_string($content_length) && !is_int($content_length)) || !preg_match('/\A[0-9]+\z/', (string) $content_length) || (string) (int) $content_length !== (string) (int) $release['package_size']) {
            $this->cleanup_temporary_package();
            return new WP_Error('superwoo_update_content_length_invalid', __('The SuperWoo update package response length did not match its signed metadata.', 'superwoo'));
        }

        $digest = hash_file('sha256', $filename);
        if (!is_string($digest) || !hash_equals($release['sha256'], $digest) || !$this->is_expected_plugin_zip($filename)) {
            $this->cleanup_temporary_package();
            return new WP_Error('superwoo_update_integrity_failed', __('The SuperWoo update package failed integrity verification.', 'superwoo'));
        }

        // WordPress owns the returned file during installation; the action and
        // shutdown cleanup below cover both normal completion and interrupted flows.
        register_shutdown_function([$this, 'cleanup_temporary_package']);
        return $filename;
    }

    public function cleanup_temporary_package($upgrader = null, $options = null) {
        if ($this->temporary_package && is_file($this->temporary_package)) {
            @unlink($this->temporary_package);
        }
        $this->temporary_package = '';
    }

    /** Testable trust-anchor seam. Production keys are installed only in Stage 2. */
    protected function trusted_public_keys() {
        return [];
    }

    private function latest_release() {
        $cached = get_site_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            $verified = $this->verify_envelope($cached, false);
            if ($verified) {
                return $verified;
            }
            delete_site_transient(self::CACHE_KEY);
        }

        // Empty production trust anchors deliberately disable unsigned legacy feeds.
        if (!$this->trusted_public_keys()) {
            return [];
        }

        $response = wp_safe_remote_get(self::API_URL, [
            'timeout'             => 15,
            'redirection'         => 0,
            'sslverify'           => true,
            'limit_response_size' => self::MAX_METADATA_BYTES + 1,
            'headers'     => [
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

        $envelope = json_decode($body, true);
        if (!is_array($envelope)) {
            return [];
        }

        $verified = $this->verify_envelope($envelope, true, $body);
        if (!$verified) {
            return [];
        }

        set_site_transient(self::CACHE_KEY, $envelope, 5 * MINUTE_IN_SECONDS);
        return $verified;
    }

    /** Validate canonical JSON, schema, pinned Ed25519 signature and replay state. */
    private function verify_envelope($envelope, $persist_sequence, $raw_body = null) {
        if (!is_array($envelope) || !SuperWoo_Update_Manifest::has_exact_keys($envelope, ['manifest', 'signature']) || !is_array($envelope['manifest']) || !is_string($envelope['signature'])) {
            return false;
        }

        $canonical_envelope = SuperWoo_Update_Manifest::canonical_json($envelope);
        if (false === $canonical_envelope || (null !== $raw_body && !hash_equals($canonical_envelope, $raw_body))) {
            return false;
        }

        $manifest = $envelope['manifest'];
        if (!SuperWoo_Update_Manifest::validate_manifest($manifest, $this->current_utc_timestamp(), self::PACKAGE_HOST, self::PACKAGE_PATH, self::MAX_PACKAGE_BYTES)) {
            return false;
        }

        $keys = $this->trusted_public_keys();
        if (!isset($keys[$manifest['key_id']]) || !SuperWoo_Update_Manifest::verify_manifest_signature($manifest, $envelope['signature'], $keys[$manifest['key_id']])) {
            return false;
        }

        $sequence_state = get_site_option(self::SEQUENCE_OPTION, []);
        $sequence_state = is_array($sequence_state) ? $sequence_state : [];
        $manifest_json = SuperWoo_Update_Manifest::canonical_json($manifest);
        if (!is_string($manifest_json)) {
            return false;
        }
        $manifest_hash = hash('sha256', $manifest_json);
        $last_sequence = isset($sequence_state['sequence']) ? (int) $sequence_state['sequence'] : 0;
        if ($manifest['sequence'] < $last_sequence || ($manifest['sequence'] === $last_sequence && !empty($sequence_state['manifest_hash']) && !hash_equals((string) $sequence_state['manifest_hash'], $manifest_hash))) {
            return false;
        }

        if ($persist_sequence && $manifest['sequence'] > $last_sequence) {
            $new_state = [
                'sequence'      => $manifest['sequence'],
                'manifest_hash' => $manifest_hash,
            ];
            update_site_option(self::SEQUENCE_OPTION, $new_state);
            $stored_state = get_site_option(self::SEQUENCE_OPTION, []);
            if (!is_array($stored_state) || (int) ($stored_state['sequence'] ?? 0) !== $manifest['sequence'] || empty($stored_state['manifest_hash']) || !hash_equals($manifest_hash, (string) $stored_state['manifest_hash'])) {
                return false;
            }
        }

        return $manifest;
    }

    protected function is_approved_package_url($url) {
        return SuperWoo_Update_Manifest::is_approved_package_url($url, self::PACKAGE_HOST, self::PACKAGE_PATH);
    }

    /** UTC clock seam for deterministic expiry tests. */
    protected function current_utc_timestamp() {
        return time();
    }

    private function is_superwoo_package_request($package, $hook_extra) {
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
            if (!is_string($name) || 0 === strpos($name, '/') || false !== strpos($name, "\\") || false !== strpos($name, '//') || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $name)) {
                $valid = false;
                break;
            }
            $first = strtok($name, '/');
            if ('superwoo' !== strtolower((string) $first)) {
                $valid = false;
                break;
            }
            if (0 === strcasecmp($name, 'SuperWoo/superwoo.php')) {
                $found_main_file = true;
            }
            if (method_exists($zip, 'getExternalAttributesIndex')) {
                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($index, $opsys, $attributes) && 3 === $opsys) {
                    $type = ($attributes >> 16) & 0170000;
                    if ($type && !in_array($type, [0100000, 0040000], true)) {
                        $valid = false;
                        break;
                    }
                }
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
