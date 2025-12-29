<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_Crypto {

    /**
     * Encrypt a string for storage in wp_options.
     * Note: this is best-effort obfuscation; WordPress options are not a secure secret store.
     */
    public static function encrypt($plaintext) {
        $plaintext = (string) $plaintext;
        if ($plaintext === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            // Fallback: return as-is.
            return $plaintext;
        }

        $key = self::key();
        $iv  = random_bytes(16);
        $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return $plaintext;
        }

        return base64_encode($iv . $cipher);
    }

    public static function decrypt($ciphertext) {
        $ciphertext = (string) $ciphertext;
        if ($ciphertext === '') {
            return '';
        }

        // If it doesn't look like base64, assume legacy plain storage.
        $raw = base64_decode($ciphertext, true);
        if ($raw === false || strlen($raw) < 17) {
            return $ciphertext;
        }

        if (!function_exists('openssl_decrypt')) {
            return $ciphertext;
        }

        $iv     = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain  = openssl_decrypt($cipher, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);

        return $plain === false ? '' : $plain;
    }

    private static function key() {
        // AUTH_KEY should exist on any properly configured WP install.
        $material = defined('AUTH_KEY') ? AUTH_KEY : (string) wp_salt('auth');
        // Make sure we get 32 bytes for AES-256.
        return hash('sha256', $material, true);
    }
}
