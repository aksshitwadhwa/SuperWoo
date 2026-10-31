<?php
defined('ABSPATH') || defined('SUPERWOO_RELEASE_TOOL') || exit;

/** Canonicalization and signature primitives shared by the updater and signer. */
final class SuperWoo_Update_Manifest {
    const SCHEMA_VERSION = 1;
    const SLUG = 'superwoo';
    const MAX_EXPIRY_SECONDS = 2592000;

    public static function canonical_json($value) {
        if (!is_array($value)) {
            return false;
        }
        $canonical = self::canonicalize($value);
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (defined('JSON_UNESCAPED_LINE_TERMINATORS')) {
            $flags |= JSON_UNESCAPED_LINE_TERMINATORS;
        }
        $json = json_encode($canonical, $flags);
        return is_string($json) ? $json : false;
    }

    public static function canonicalize($value) {
        if (!is_array($value)) {
            return $value;
        }
        if (!self::is_list($value)) {
            foreach (array_keys($value) as $key) {
                if (!is_string($key) || !preg_match('/\A[a-zA-Z0-9_]+\z/', $key)) {
                    return [];
                }
            }
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonicalize($item);
            }
        }
        return $value;
    }

    public static function is_list($value) {
        if ([] === $value) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    public static function has_exact_keys($value, $expected) {
        if (!is_array($value)) {
            return false;
        }
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);
        return $actual === $expected;
    }

    public static function is_version($version) {
        return is_string($version) && strlen($version) <= 64 && (bool) preg_match('/\A[0-9]+\.[0-9]+(?:\.[0-9]+)?(?:-[0-9A-Za-z.-]+)?\z/', $version);
    }

    public static function is_approved_package_url($url, $host, $path_prefix) {
        if (!is_string($url) || strlen($url) > 2048 || false !== strpos($url, '%') || false !== strpos($url, '\\')) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || 'https' !== strtolower($parts['scheme'] ?? '') || strtolower($host) !== strtolower($parts['host'] ?? '') || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && 443 !== (int) $parts['port']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }
        $path = $parts['path'] ?? '';
        if (0 !== strpos($path, $path_prefix) || false !== strpos($path, '//') || false !== strpos($path, '/./') || false !== strpos($path, '/../') || !preg_match('#\A/plugins/superwoo/[A-Za-z0-9._-]+\.zip\z#', $path)) {
            return false;
        }
        return true;
    }

    public static function is_expiration_valid($expires_at, $now, $maximum_lifetime = self::MAX_EXPIRY_SECONDS) {
        if (!is_string($expires_at) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/', $expires_at)) {
            return false;
        }
        $timestamp = strtotime($expires_at);
        if (false === $timestamp || gmdate('Y-m-d\TH:i:s\Z', $timestamp) !== $expires_at) {
            return false;
        }
        return $timestamp > (int) $now && $timestamp <= ((int) $now + (int) $maximum_lifetime);
    }

    public static function validate_manifest($manifest, $now, $host, $path_prefix, $maximum_package_bytes) {
        $keys = ['schema_version', 'slug', 'version', 'requires', 'requires_php', 'package', 'sha256', 'package_size', 'sequence', 'key_id', 'expires_at'];
        if (!self::has_exact_keys($manifest, $keys) || self::SCHEMA_VERSION !== $manifest['schema_version'] || self::SLUG !== $manifest['slug']) {
            return false;
        }
        if (!self::is_version($manifest['version']) || !self::is_version($manifest['requires']) || !self::is_version($manifest['requires_php'])) {
            return false;
        }
        if (!self::is_approved_package_url($manifest['package'], $host, $path_prefix) || basename((string) parse_url($manifest['package'], PHP_URL_PATH)) !== 'SuperWoo-' . $manifest['version'] . '.zip' || !is_string($manifest['sha256']) || !preg_match('/\A[a-f0-9]{64}\z/', $manifest['sha256'])) {
            return false;
        }
        if (!is_int($manifest['package_size']) || $manifest['package_size'] < 1 || $manifest['package_size'] > (int) $maximum_package_bytes || !is_int($manifest['sequence']) || $manifest['sequence'] < 1) {
            return false;
        }
        if (!is_string($manifest['key_id']) || !preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/', $manifest['key_id'])) {
            return false;
        }
        return self::is_expiration_valid($manifest['expires_at'], $now);
    }

    public static function sign_manifest($manifest, $secret_key_base64) {
        $message = self::canonical_json($manifest);
        $secret_key = is_string($secret_key_base64) ? base64_decode($secret_key_base64, true) : false;
        if (!is_string($message) || false === $secret_key || 64 !== strlen($secret_key)) {
            return false;
        }
        if (!function_exists('sodium_crypto_sign_detached')) {
            return false;
        }
        return base64_encode(sodium_crypto_sign_detached($message, $secret_key));
    }

    public static function verify_manifest_signature($manifest, $signature_base64, $public_key_base64) {
        $message = self::canonical_json($manifest);
        $signature = is_string($signature_base64) ? base64_decode($signature_base64, true) : false;
        $public_key = is_string($public_key_base64) ? base64_decode($public_key_base64, true) : false;
        if (!is_string($message) || false === $signature || false === $public_key || 64 !== strlen($signature) || 32 !== strlen($public_key)) {
            return false;
        }
        if (function_exists('sodium_crypto_sign_verify_detached')) {
            return sodium_crypto_sign_verify_detached($signature, $message, $public_key);
        }
        if (class_exists('ParagonIE_Sodium_Compat') && method_exists('ParagonIE_Sodium_Compat', 'crypto_sign_verify_detached')) {
            return ParagonIE_Sodium_Compat::crypto_sign_verify_detached($signature, $message, $public_key);
        }
        return false;
    }
}
